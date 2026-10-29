<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ingesta\ExcelCsvParser;
use App\Services\Ingesta\IngestaValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class IngestRuteoArchivoWorkerCommand extends Command
{
    /**
     * Firma del comando Artisan CLI.
     *
     * @var string
     */
    protected $signature = 'ingesta:ruteo-archivo-worker 
                            {path : Ruta absoluta o relativa del archivo} 
                            {--fecha= : Timestamp retroactivo YYYY-MM-DD HH:MM:SS} 
                            {--baseline : Marca si es el snapshot inicial sin auditoría previa}';

    /**
     * Descripción del comando en consola.
     *
     * @var string
     */
    protected $description = 'Procesa un único archivo .xlsx de ruteo en un proceso aislado, generando snapshot inmutable, auditoría diferencial y actualización de pan_ruteo.';

    /**
     * Tamaño del lote para inserción masiva en MariaDB.
     */
    private const CHUNK_SIZE = 500;

    /**
     * Columnas canónicas de pan_ruteo (excluyendo autoincremental id).
     *
     * @var array<int, string>
     */
    private const CANONICAL_COLUMNS = [
        'nodo_id', 'nodo', 'cliente_id', 'cliente', 'vendedor', 'tipo_negocio',
        'territorio', 'mercado', 'fecha_servidor', 'fecha_dispositivo', 'contacto',
        'ci', 'telefono', 'celular', 'direccion', 'nro', 'referencia',
        'nombre_factura', 'nit', 'tipo_pago', 'latitud', 'longitud', 'intensidad',
        'estado', 'zona_venta', 'cod_vendedor', 'dias_credito', 'limite', 'ruta',
        'dia', 'precio', 'cluster_t', 'cluster_c', 'cliente_norm', 'vendedor_norm',
        'dia_norm',
    ];

    /**
     * Columnas para auditoría general de modificaciones (excluyendo las tratadas por reglas especiales).
     *
     * @var array<int, string>
     */
    private const GENERAL_DIFF_COLUMNS = [
        'cliente', 'contacto', 'ci', 'telefono', 'celular', 'direccion', 'nro',
        'referencia', 'nombre_factura', 'nit', 'tipo_pago', 'dias_credito',
        'limite', 'precio', 'intensidad', 'territorio', 'mercado', 'zona_venta',
        'nodo_id', 'nodo',
    ];

    public function __construct(
        protected ExcelCsvParser $parser,
        protected IngestaValidator $validator
    ) {
        parent::__construct();
    }

    /**
     * Ejecuta el procesamiento atómico del archivo en aislamiento.
     */
    public function handle(): int
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $path = (string) $this->argument('path');
        $resolvedPath = file_exists($path)
            ? realpath($path)
            : (file_exists(base_path($path)) ? realpath(base_path($path)) : null);

        if (!$resolvedPath || !is_file($resolvedPath)) {
            $this->error("El archivo no existe en la ruta especificada: {$path}");
            return Command::FAILURE;
        }

        $filename = basename($resolvedPath);
        $fechaOperativa = $this->extraerFechaOperativa($resolvedPath, $filename);
        $snapshotId = 'snap_' . date('Ymd_His', strtotime($fechaOperativa));

        $this->info("================================================================================");
        $this->info("Procesando archivo: {$filename}");
        $this->line("  Ruta: {$resolvedPath}");
        $this->line("  Fecha operativa: {$fechaOperativa}");
        $this->line("  Snapshot ID: {$snapshotId}");

        $isBaseline = (bool) $this->option('baseline');

        $conn = DB::connection('supervisor');

        // Precargar catálogo de vendedores por ruta desde dim_rutas para enriquecimiento
        $vendedoresPorRuta = [];
        try {
            $vendedoresPorRuta = $conn->table('dim_rutas')
                ->whereNotNull('ruta')
                ->where('ruta', '!=', '')
                ->pluck('vendedor', 'ruta')
                ->toArray();
        } catch (Throwable) {}

        // 1. Lectura y Normalización del Archivo
        $this->line("  Leyendo y normalizando filas del archivo...");
        $rawRows = $this->parser->parseToAssociativeRows($resolvedPath, 0, $filename);
        $totalLeidas = count($rawRows);

        if ($totalLeidas === 0) {
            $this->warn("  El archivo no contiene filas o está vacío.");
            return Command::SUCCESS;
        }

        $registrosNormalizados = [];
        $visitasUnicasArchivo = [];
        $descartadasCount = 0;

        foreach ($rawRows as $r) {
            $mapped = $this->validator->mapRow($r, 'PAN_RUTEO');

            $cid = isset($mapped['cliente_id']) ? (int) $mapped['cliente_id'] : 0;
            if ($cid <= 0) {
                $descartadasCount++;
                continue;
            }

            // Ensamble del nombre de cliente si viene desglosado
            $clienteNombre = trim((string) ($mapped['cliente'] ?? ''));
            if ($clienteNombre === '') {
                $partes = array_filter([
                    trim((string) ($mapped['apellido_paterno'] ?? '')),
                    trim((string) ($mapped['apellido_materno'] ?? '')),
                    trim((string) ($mapped['nombres'] ?? '')),
                ], fn($p) => $p !== '' && $p !== '.');

                $clienteNombre = !empty($partes)
                    ? implode(' ', $partes)
                    : trim((string) ($mapped['razon_social'] ?? $mapped['nombre_factura'] ?? ''));
            }

            // Normalización de Estado
            if (isset($mapped['activo']) && !isset($mapped['estado'])) {
                $actStr = strtoupper(trim((string) $mapped['activo']));
                $estadoNorm = in_array($actStr, ['SI', 'S', '1', 'TRUE', 'ACTIVO'], true) ? 'Activo' : 'Inactivo';
            } elseif (isset($mapped['estado'])) {
                $stStr = strtoupper(trim((string) $mapped['estado']));
                $estadoNorm = in_array($stStr, ['NO', '0', 'INACTIVO', 'BAJA', 'ANULADO'], true) ? 'Inactivo' : 'Activo';
            } else {
                $estadoNorm = 'Activo';
            }

            // Normalización de Día y Día Norm
            $diaRaw = (string) ($mapped['dia_norm'] ?? $mapped['dia'] ?? '');
            $diaNorm = IngestaValidator::sanitizeDia($diaRaw);
            $diaCanonical = ucfirst($diaNorm ?: $diaRaw);

            // Normalización de cliente_norm y vendedor_norm (mayúsculas, sin tildes, alfanumérico limpio)
            $clienteNorm = $this->cleanUpperAlphaNum($clienteNombre);

            $rutaRaw = trim((string) ($mapped['ruta'] ?? ''));
            $vendedorRaw = trim((string) ($mapped['vendedor'] ?? ''));
            if ($vendedorRaw === '' && $rutaRaw !== '' && isset($vendedoresPorRuta[$rutaRaw])) {
                $vendedorRaw = (string) $vendedoresPorRuta[$rutaRaw];
            }
            $vendedorNorm = $this->cleanUpperAlphaNum($vendedorRaw);

            // Coordenadas numéricas float o null
            $lat = isset($mapped['latitud']) && is_numeric($mapped['latitud']) ? (float) $mapped['latitud'] : null;
            $lon = isset($mapped['longitud']) && is_numeric($mapped['longitud']) ? (float) $mapped['longitud'] : null;

            // Montos y límites
            $limite = isset($mapped['limite']) && is_numeric($mapped['limite']) ? (float) $mapped['limite'] : 0.0;
            $diasCredito = isset($mapped['dias_credito']) && is_numeric($mapped['dias_credito']) ? (int) $mapped['dias_credito'] : 0;

            // Deduplicación intra-archivo por (cliente_id, dia_norm, ruta)
            $claveVisita = "{$cid}_{$diaNorm}_" . strtoupper($rutaRaw);
            if (isset($visitasUnicasArchivo[$claveVisita])) {
                continue;
            }
            $visitasUnicasArchivo[$claveVisita] = true;

            $filaNorm = [
                'nodo_id' => isset($mapped['nodo_id']) && is_numeric($mapped['nodo_id']) ? (int) $mapped['nodo_id'] : 0,
                'nodo' => $mapped['nodo'] ?? null,
                'cliente_id' => $cid,
                'cliente' => $clienteNombre,
                'vendedor' => $vendedorRaw,
                'tipo_negocio' => $mapped['tipo_negocio'] ?? null,
                'territorio' => $mapped['territorio'] ?? null,
                'mercado' => $mapped['mercado'] ?? null,
                'fecha_servidor' => !empty($mapped['fecha_servidor']) ? (string) $mapped['fecha_servidor'] : substr($fechaOperativa, 0, 10),
                'fecha_dispositivo' => $mapped['fecha_dispositivo'] ?? null,
                'contacto' => $mapped['contacto'] ?? null,
                'ci' => $mapped['ci'] ?? null,
                'telefono' => $mapped['telefono'] ?? null,
                'celular' => $mapped['celular'] ?? null,
                'direccion' => $mapped['direccion'] ?? null,
                'nro' => $mapped['nro'] ?? null,
                'referencia' => $mapped['referencia'] ?? null,
                'nombre_factura' => $mapped['nombre_factura'] ?? null,
                'nit' => $mapped['nit'] ?? null,
                'tipo_pago' => $mapped['tipo_pago'] ?? null,
                'latitud' => $lat,
                'longitud' => $lon,
                'intensidad' => $mapped['intensidad'] ?? null,
                'estado' => $estadoNorm,
                'zona_venta' => $mapped['zona_venta'] ?? null,
                'cod_vendedor' => $mapped['cod_vendedor'] ?? null,
                'dias_credito' => (string) $diasCredito,
                'limite' => $limite,
                'ruta' => $rutaRaw,
                'dia' => $diaCanonical,
                'precio' => $mapped['precio'] ?? null,
                'cluster_t' => $mapped['cluster_t'] ?? null,
                'cluster_c' => $mapped['cluster_c'] ?? null,
                'cliente_norm' => $clienteNorm,
                'vendedor_norm' => $vendedorNorm,
                'dia_norm' => $diaNorm,
            ];

            $registrosNormalizados[] = $filaNorm;
        }

        $totalValidas = count($registrosNormalizados);
        $this->line("  Filas válidas normalizadas: {$totalValidas} (Descartadas: {$descartadasCount})");

        // 2. Persistencia Inmutable en pan_ruteo_snapshots
        $this->line("  Guardando snapshot inmutable en pan_ruteo_snapshots...");
        $snapshotRows = [];
        foreach ($registrosNormalizados as $rn) {
            $snapItem = array_merge($rn, [
                'snapshot_id' => $snapshotId,
                'archivo_origen' => $filename,
                'fecha_carga' => $fechaOperativa,
            ]);
            $snapshotRows[] = $snapItem;
        }

        foreach (array_chunk($snapshotRows, self::CHUNK_SIZE) as $chunk) {
            $conn->table('pan_ruteo_snapshots')->insert($chunk);
        }

        // 3. Motor Diferencial de Auditoría (auditoria_ruteo_cambios)
        $this->line("  Ejecutando motor diferencial de auditoría...");
        $filasPrevias = $conn->table('pan_ruteo')->get();
        $previosPorCliente = [];
        foreach ($filasPrevias as $fp) {
            $cid = (int) $fp->cliente_id;
            if ($cid > 0) {
                $previosPorCliente[$cid][] = (array) $fp;
            }
        }

        $nuevosPorCliente = [];
        foreach ($registrosNormalizados as $rn) {
            $cid = (int) $rn['cliente_id'];
            $nuevosPorCliente[$cid][] = $rn;
        }

        $registrosAuditoria = [];
        $altasCnt = 0;
        $modificadosCnt = 0;
        $eliminadosCnt = 0;
        $sinCambiosCnt = 0;

        $cidsPrevios = array_keys($previosPorCliente);
        $cidsNuevos = array_keys($nuevosPorCliente);
        $setPrevios = array_flip($cidsPrevios);
        $setNuevos = array_flip($cidsNuevos);

        $esBaselineEfectivo = $isBaseline || count($previosPorCliente) === 0;

        if ($esBaselineEfectivo) {
            $this->line("  Modo Baseline activado (Padrón inicial).");
            foreach ($registrosNormalizados as $rn) {
                $altasCnt++;
                $registrosAuditoria[] = [
                    'archivo_origen' => $filename,
                    'cliente_id' => (int) $rn['cliente_id'],
                    'cliente' => (string) ($rn['cliente'] ?? ''),
                    'tipo_evento' => 'NUEVO',
                    'campo_afectado' => 'cliente_alta_total',
                    'valor_anterior' => null,
                    'valor_nuevo' => "Ruta: {$rn['ruta']} | Dia: {$rn['dia']}",
                    'fecha_auditoria' => $fechaOperativa,
                    'estado_auditoria' => 'PENDIENTE',
                    'observaciones' => 'Snapshot inicial baseline.',
                    'auditado_por' => 'SISTEMA_INGESTA_HISTORICA',
                ];
            }
        } else {
            // Modo Diferencial
            // B1. Clientes Desaparecidos (en previos pero no en entrantes)
            foreach ($cidsPrevios as $cid) {
                if (!isset($setNuevos[$cid])) {
                    $eliminadosCnt++;
                    foreach ($previosPorCliente[$cid] as $prev) {
                        $registrosAuditoria[] = [
                            'archivo_origen' => $filename,
                            'cliente_id' => $cid,
                            'cliente' => (string) ($prev['cliente'] ?? ''),
                            'tipo_evento' => 'ELIMINADO',
                            'campo_afectado' => 'cliente_baja_total',
                            'valor_anterior' => "Ruta: {$prev['ruta']} | Dia: {$prev['dia']}",
                            'valor_nuevo' => 'Desaparecido de Ruteo',
                            'fecha_auditoria' => $fechaOperativa,
                            'estado_auditoria' => 'PENDIENTE',
                            'observaciones' => 'Baja total de cliente. Eliminado de la cartera comercial.',
                            'auditado_por' => 'SISTEMA_INGESTA_HISTORICA',
                        ];
                    }
                }
            }

            // B2. Clientes Nuevos (en entrantes pero no en previos)
            foreach ($cidsNuevos as $cid) {
                if (!isset($setPrevios[$cid])) {
                    $altasCnt++;
                    foreach ($nuevosPorCliente[$cid] as $nuevo) {
                        $registrosAuditoria[] = [
                            'archivo_origen' => $filename,
                            'cliente_id' => $cid,
                            'cliente' => (string) ($nuevo['cliente'] ?? ''),
                            'tipo_evento' => 'NUEVO',
                            'campo_afectado' => 'cliente_alta_total',
                            'valor_anterior' => null,
                            'valor_nuevo' => "Ruta: {$nuevo['ruta']} | Dia: {$nuevo['dia']}",
                            'fecha_auditoria' => $fechaOperativa,
                            'estado_auditoria' => 'PENDIENTE',
                            'observaciones' => 'Alta total de cliente. Incorporado a la cartera comercial.',
                            'auditado_por' => 'SISTEMA_INGESTA_HISTORICA',
                        ];
                    }
                }
            }

            // B3. Clientes Existentes (Reconciliación Jerárquica)
            foreach ($cidsNuevos as $cid) {
                if (isset($setPrevios[$cid])) {
                    $listaP = $previosPorCliente[$cid];
                    $listaE = $nuevosPorCliente[$cid];

                    $huboCambioEnCliente = false;
                    $eventosCliente = $this->conciliarVisitasCliente(
                        $cid,
                        $listaP,
                        $listaE,
                        $filename,
                        $fechaOperativa,
                        $huboCambioEnCliente
                    );

                    foreach ($eventosCliente as $ev) {
                        $registrosAuditoria[] = $ev;
                    }

                    if ($huboCambioEnCliente) {
                        $modificadosCnt++;
                    } else {
                        $sinCambiosCnt++;
                    }
                }
            }
        }

        // Inserción en auditoria_ruteo_cambios
        $totalEventosAuditoria = count($registrosAuditoria);
        $this->line("  Guardando {$totalEventosAuditoria} eventos de auditoría...");
        if ($totalEventosAuditoria > 0) {
            foreach (array_chunk($registrosAuditoria, self::CHUNK_SIZE) as $chunk) {
                $conn->table('auditoria_ruteo_cambios')->insert($chunk);
            }
        }

        // 4. Actualización Atómica de pan_ruteo
        $this->line("  Actualizando tabla viva pan_ruteo...");
        $conn->transaction(function () use ($conn, $registrosNormalizados) {
            $conn->table('pan_ruteo')->delete();
            foreach (array_chunk($registrosNormalizados, self::CHUNK_SIZE) as $chunk) {
                $conn->table('pan_ruteo')->insert($chunk);
            }
        });

        // 5. Bitácora en sys_cargas
        $this->line("  Registrando bitácora en sys_cargas...");
        $sha256 = hash_file('sha256', $resolvedPath) ?: $snapshotId;
        $cargaData = [
            'archivo_original' => $filename,
            'archivo_almacenado' => $resolvedPath,
            'sha256' => $sha256,
            'tipo_entidad' => 'PAN_RUTEO',
            'filas_leidas' => $totalLeidas,
            'filas_afectadas' => $totalValidas,
            'filas_nuevas' => $altasCnt,
            'filas_actualizadas' => $modificadosCnt,
            'filas_ignoradas' => $sinCambiosCnt,
            'filas_con_discrepancia' => $eliminadosCnt,
            'rango_inicio' => '-',
            'rango_fin' => '-',
            'fecha_ingesta' => $fechaOperativa,
            'estado' => 'COMPLETADO',
        ];

        try {
            $conn->table('sys_cargas')->updateOrInsert(
                ['sha256' => $sha256, 'tipo_entidad' => 'PAN_RUTEO'],
                $cargaData
            );
        } catch (Throwable $eSys) {
            Log::warning("No se pudo registrar sys_cargas en worker: " . $eSys->getMessage());
        }

        $this->parser->clearCache();

        $this->info("Archivo completado exitosamente: {$filename}");
        $this->line("  Leidas: {$totalLeidas} | Afectadas: {$totalValidas} | Nuevos: {$altasCnt} | Modificados: {$modificadosCnt} | Eliminados: {$eliminadosCnt} | Eventos Auditoria: {$totalEventosAuditoria}");
        $this->info("================================================================================");

        return Command::SUCCESS;
    }

    /**
     * Algoritmo de Reconciliación Jerárquica de Visitas para Clientes Existentes.
     *
     * @param array<int, array<string, mixed>> $P Visitas previas
     * @param array<int, array<string, mixed>> $E Visitas entrantes
     * @return array<int, array<string, mixed>> Eventos de auditoría
     */
    private function conciliarVisitasCliente(
        int $cid,
        array $P,
        array $E,
        string $filename,
        string $fechaOperativa,
        bool &$huboCambio
    ): array {
        $eventos = [];
        $N = count($P);
        $M = count($E);

        $dispP = array_keys($P);
        $dispE = array_keys($E);
        $pares = [];

        // Paso 1: Match Exacto (ruta === ruta && dia_norm === dia_norm)
        foreach ($dispP as $kP => $i) {
            $rP = strtoupper(trim((string) ($P[$i]['ruta'] ?? '')));
            $dP = $P[$i]['dia_norm'] ?? '';
            foreach ($dispE as $kE => $j) {
                $rE = strtoupper(trim((string) ($E[$j]['ruta'] ?? '')));
                $dE = $E[$j]['dia_norm'] ?? '';
                if ($rP === $rE && $dP === $dE) {
                    $pares[] = ['p' => $P[$i], 'e' => $E[$j]];
                    unset($dispP[$kP], $dispE[$kE]);
                    break;
                }
            }
        }

        // Paso 2: Match por Ruta (con las restantes, si coincide ruta)
        foreach ($dispP as $kP => $i) {
            $rP = strtoupper(trim((string) ($P[$i]['ruta'] ?? '')));
            foreach ($dispE as $kE => $j) {
                $rE = strtoupper(trim((string) ($E[$j]['ruta'] ?? '')));
                if ($rP === $rE) {
                    $pares[] = ['p' => $P[$i], 'e' => $E[$j]];
                    $huboCambio = true;
                    $eventos[] = [
                        'archivo_origen' => $filename,
                        'cliente_id' => $cid,
                        'cliente' => (string) ($E[$j]['cliente'] ?? $P[$i]['cliente'] ?? ''),
                        'tipo_evento' => 'MODIFICADO_DIA',
                        'campo_afectado' => 'dia',
                        'valor_anterior' => (string) ($P[$i]['dia'] ?? ''),
                        'valor_nuevo' => (string) ($E[$j]['dia'] ?? ''),
                        'fecha_auditoria' => $fechaOperativa,
                        'estado_auditoria' => 'PENDIENTE',
                        'observaciones' => 'Modificación de día en la misma ruta.',
                        'auditado_por' => 'SISTEMA_INGESTA_HISTORICA',
                    ];
                    unset($dispP[$kP], $dispE[$kE]);
                    break;
                }
            }
        }

        // Paso 3: Match por Día (con las restantes, si coincide dia_norm)
        foreach ($dispP as $kP => $i) {
            $dP = $P[$i]['dia_norm'] ?? '';
            foreach ($dispE as $kE => $j) {
                $dE = $E[$j]['dia_norm'] ?? '';
                if ($dP === $dE && $dP !== '') {
                    $pares[] = ['p' => $P[$i], 'e' => $E[$j]];
                    $huboCambio = true;
                    $eventos[] = [
                        'archivo_origen' => $filename,
                        'cliente_id' => $cid,
                        'cliente' => (string) ($E[$j]['cliente'] ?? $P[$i]['cliente'] ?? ''),
                        'tipo_evento' => 'MODIFICADO_RUTA',
                        'campo_afectado' => 'ruta',
                        'valor_anterior' => (string) ($P[$i]['ruta'] ?? ''),
                        'valor_nuevo' => (string) ($E[$j]['ruta'] ?? ''),
                        'fecha_auditoria' => $fechaOperativa,
                        'estado_auditoria' => 'PENDIENTE',
                        'observaciones' => 'Modificación de ruta en el mismo día.',
                        'auditado_por' => 'SISTEMA_INGESTA_HISTORICA',
                    ];
                    unset($dispP[$kP], $dispE[$kE]);
                    break;
                }
            }
        }

        // Paso 4: Match Residual 1:1 con pares sobrantes
        $dispP = array_values($dispP);
        $dispE = array_values($dispE);
        while (!empty($dispP) && !empty($dispE)) {
            $i = array_shift($dispP);
            $j = array_shift($dispE);

            $pares[] = ['p' => $P[$i], 'e' => $E[$j]];

            $diaAnt = (string) ($P[$i]['dia'] ?? '');
            $diaNue = (string) ($E[$j]['dia'] ?? '');
            if ($P[$i]['dia_norm'] !== $E[$j]['dia_norm']) {
                $huboCambio = true;
                $eventos[] = [
                    'archivo_origen' => $filename,
                    'cliente_id' => $cid,
                    'cliente' => (string) ($E[$j]['cliente'] ?? $P[$i]['cliente'] ?? ''),
                    'tipo_evento' => 'MODIFICADO_DIA',
                    'campo_afectado' => 'dia',
                    'valor_anterior' => $diaAnt,
                    'valor_nuevo' => $diaNue,
                    'fecha_auditoria' => $fechaOperativa,
                    'estado_auditoria' => 'PENDIENTE',
                    'observaciones' => 'Modificación residual de día.',
                    'auditado_por' => 'SISTEMA_INGESTA_HISTORICA',
                ];
            }

            $rutaAnt = (string) ($P[$i]['ruta'] ?? '');
            $rutaNue = (string) ($E[$j]['ruta'] ?? '');
            if (strtoupper(trim($rutaAnt)) !== strtoupper(trim($rutaNue))) {
                $huboCambio = true;
                $eventos[] = [
                    'archivo_origen' => $filename,
                    'cliente_id' => $cid,
                    'cliente' => (string) ($E[$j]['cliente'] ?? $P[$i]['cliente'] ?? ''),
                    'tipo_evento' => 'MODIFICADO_RUTA',
                    'campo_afectado' => 'ruta',
                    'valor_anterior' => $rutaAnt,
                    'valor_nuevo' => $rutaNue,
                    'fecha_auditoria' => $fechaOperativa,
                    'estado_auditoria' => 'PENDIENTE',
                    'observaciones' => 'Modificación residual de ruta.',
                    'auditado_por' => 'SISTEMA_INGESTA_HISTORICA',
                ];
            }
        }

        // Paso 5: Variación de Frecuencia Logística
        // Sobran filas nuevas (M > N): adición de frecuencia
        foreach ($dispE as $j) {
            $huboCambio = true;
            $eventos[] = [
                'archivo_origen' => $filename,
                'cliente_id' => $cid,
                'cliente' => (string) ($E[$j]['cliente'] ?? ''),
                'tipo_evento' => 'NUEVO',
                'campo_afectado' => 'frecuencia_visita',
                'valor_anterior' => null,
                'valor_nuevo' => "Ruta: {$E[$j]['ruta']} | Dia: {$E[$j]['dia']}",
                'fecha_auditoria' => $fechaOperativa,
                'estado_auditoria' => 'PENDIENTE',
                'observaciones' => "Adición de frecuencia logística (Paso a Multivisita: {$N} -> {$M}).",
                'auditado_por' => 'SISTEMA_INGESTA_HISTORICA',
            ];
        }

        // Sobran filas previas (N > M): reducción de frecuencia
        foreach ($dispP as $i) {
            $huboCambio = true;
            $eventos[] = [
                'archivo_origen' => $filename,
                'cliente_id' => $cid,
                'cliente' => (string) ($P[$i]['cliente'] ?? ''),
                'tipo_evento' => 'ELIMINADO',
                'campo_afectado' => 'frecuencia_visita',
                'valor_anterior' => "Ruta: {$P[$i]['ruta']} | Dia: {$P[$i]['dia']}",
                'valor_nuevo' => null,
                'fecha_auditoria' => $fechaOperativa,
                'estado_auditoria' => 'PENDIENTE',
                'observaciones' => "Reducción de frecuencia logística (Paso a Monovisita: {$N} -> {$M}). El cliente permanece en cartera.",
                'auditado_por' => 'SISTEMA_INGESTA_HISTORICA',
            ];
        }

        // Auditoría Fina de Columnas para cada par emparejado (Pasos 1, 2, 3 y 4)
        foreach ($pares as $par) {
            $pRow = $par['p'];
            $eRow = $par['e'];
            $cliNombre = (string) ($eRow['cliente'] ?? $pRow['cliente'] ?? '');

            // Regla CAMBIO_DE_CLUSTER: Ignorar cluster_c. Evaluar tipo_negocio y cluster_t
            $antTN = trim((string) ($pRow['tipo_negocio'] ?? ''));
            $newTN = trim((string) ($eRow['tipo_negocio'] ?? ''));
            $antCT = trim((string) ($pRow['cluster_t'] ?? ''));
            $newCT = trim((string) ($eRow['cluster_t'] ?? ''));

            if ($antTN !== $newTN || $antCT !== $newCT) {
                $huboCambio = true;
                $eventos[] = [
                    'archivo_origen' => $filename,
                    'cliente_id' => $cid,
                    'cliente' => $cliNombre,
                    'tipo_evento' => 'CAMBIO_DE_CLUSTER',
                    'campo_afectado' => 'cluster',
                    'valor_anterior' => "tipo_negocio: {$antTN} | cluster_t: {$antCT}",
                    'valor_nuevo' => "tipo_negocio: {$newTN} | cluster_t: {$newCT}",
                    'fecha_auditoria' => $fechaOperativa,
                    'estado_auditoria' => 'PENDIENTE',
                    'observaciones' => 'Cambio de cluster o tipología de negocio.',
                    'auditado_por' => 'SISTEMA_INGESTA_HISTORICA',
                ];
            }

            // Regla Vendedor: cambio en vendedor o cod_vendedor
            $antV = trim((string) ($pRow['vendedor'] ?? ''));
            $newV = trim((string) ($eRow['vendedor'] ?? ''));
            $antCV = trim((string) ($pRow['cod_vendedor'] ?? ''));
            $newCV = trim((string) ($eRow['cod_vendedor'] ?? ''));

            if ($antV !== $newV || $antCV !== $newCV) {
                $huboCambio = true;
                $eventos[] = [
                    'archivo_origen' => $filename,
                    'cliente_id' => $cid,
                    'cliente' => $cliNombre,
                    'tipo_evento' => 'MODIFICADO_VENDEDOR',
                    'campo_afectado' => 'vendedor',
                    'valor_anterior' => $antV !== '' ? $antV : ($antCV !== '' ? "Cod: {$antCV}" : null),
                    'valor_nuevo' => $newV !== '' ? $newV : ($newCV !== '' ? "Cod: {$newCV}" : null),
                    'fecha_auditoria' => $fechaOperativa,
                    'estado_auditoria' => 'PENDIENTE',
                    'observaciones' => 'Modificación de preventista o vendedor asignado.',
                    'auditado_por' => 'SISTEMA_INGESTA_HISTORICA',
                ];
            }

            // Regla Estado: Activo a Inactivo / Inactivo a Activo
            $antSt = trim((string) ($pRow['estado'] ?? ''));
            $newSt = trim((string) ($eRow['estado'] ?? ''));

            if (strtolower($antSt) !== strtolower($newSt) && $antSt !== '' && $newSt !== '') {
                $huboCambio = true;
                $tipoEv = 'MODIFICADO';
                if (strtolower($antSt) === 'activo' && strtolower($newSt) === 'inactivo') {
                    $tipoEv = 'INACTIVADO';
                } elseif (strtolower($antSt) === 'inactivo' && strtolower($newSt) === 'activo') {
                    $tipoEv = 'REACTIVADO';
                }

                $eventos[] = [
                    'archivo_origen' => $filename,
                    'cliente_id' => $cid,
                    'cliente' => $cliNombre,
                    'tipo_evento' => $tipoEv,
                    'campo_afectado' => 'estado',
                    'valor_anterior' => $antSt,
                    'valor_nuevo' => $newSt,
                    'fecha_auditoria' => $fechaOperativa,
                    'estado_auditoria' => 'PENDIENTE',
                    'observaciones' => "Transición de estado: {$antSt} a {$newSt}.",
                    'auditado_por' => 'SISTEMA_INGESTA_HISTORICA',
                ];
            }

            // Regla Coordenadas: max(|lat1 - lat2|, |lon1 - lon2|) > 0.00001
            $lat1 = isset($pRow['latitud']) && is_numeric($pRow['latitud']) ? (float) $pRow['latitud'] : null;
            $lon1 = isset($pRow['longitud']) && is_numeric($pRow['longitud']) ? (float) $pRow['longitud'] : null;
            $lat2 = isset($eRow['latitud']) && is_numeric($eRow['latitud']) ? (float) $eRow['latitud'] : null;
            $lon2 = isset($eRow['longitud']) && is_numeric($eRow['longitud']) ? (float) $eRow['longitud'] : null;

            $cambioCoord = false;
            if ($lat1 !== null && $lon1 !== null && $lat2 !== null && $lon2 !== null) {
                if (max(abs($lat1 - $lat2), abs($lon1 - $lon2)) > 0.00001) {
                    $cambioCoord = true;
                }
            } elseif (($lat1 !== null || $lon1 !== null) !== ($lat2 !== null || $lon2 !== null)) {
                $cambioCoord = true;
            }

            if ($cambioCoord) {
                $huboCambio = true;
                $eventos[] = [
                    'archivo_origen' => $filename,
                    'cliente_id' => $cid,
                    'cliente' => $cliNombre,
                    'tipo_evento' => 'MODIFICADO_COORDENADAS',
                    'campo_afectado' => 'coordenadas',
                    'valor_anterior' => $lat1 !== null ? "{$lat1}, {$lon1}" : null,
                    'valor_nuevo' => $lat2 !== null ? "{$lat2}, {$lon2}" : null,
                    'fecha_auditoria' => $fechaOperativa,
                    'estado_auditoria' => 'PENDIENTE',
                    'observaciones' => 'Desplazamiento geográfico de coordenadas.',
                    'auditado_por' => 'SISTEMA_INGESTA_HISTORICA',
                ];
            }

            // Regla Atributos Generales (trim, tratando null y '' como iguales)
            foreach (self::GENERAL_DIFF_COLUMNS as $col) {
                $valP = trim((string) ($pRow[$col] ?? ''));
                $valE = trim((string) ($eRow[$col] ?? ''));

                if ($valP !== $valE) {
                    $huboCambio = true;
                    $eventos[] = [
                        'archivo_origen' => $filename,
                        'cliente_id' => $cid,
                        'cliente' => $cliNombre,
                        'tipo_evento' => 'MODIFICADO',
                        'campo_afectado' => $col,
                        'valor_anterior' => $valP !== '' ? $valP : null,
                        'valor_nuevo' => $valE !== '' ? $valE : null,
                        'fecha_auditoria' => $fechaOperativa,
                        'estado_auditoria' => 'PENDIENTE',
                        'observaciones' => "Actualización del atributo {$col}.",
                        'auditado_por' => 'SISTEMA_INGESTA_HISTORICA',
                    ];
                }
            }
        }

        return $eventos;
    }

    /**
     * Extrae el timestamp operativo desde opciones, regex en el nombre de archivo o filemtime.
     */
    private function extraerFechaOperativa(string $filePath, string $filename): string
    {
        $optFecha = $this->option('fecha');
        if (!empty($optFecha)) {
            $fTrim = trim((string) $optFecha);
            if (strlen($fTrim) === 10 && $fTrim[4] === '-' && $fTrim[7] === '-') {
                return "{$fTrim} 12:00:00";
            }
            return $fTrim;
        }

        // Regex solicitada: /ruteo_(\d{4}-\d{2}-\d{2})_(\d{2})(\d{2})(\d{2})/
        if (preg_match('/ruteo_(\d{4}-\d{2}-\d{2})_(\d{2})(\d{2})(\d{2})/', $filename, $m)) {
            return "{$m[1]} {$m[2]}:{$m[3]}:{$m[4]}";
        }

        // Patrón alternativo con guiones / guiones bajos
        if (preg_match('/(\d{4}-\d{2}-\d{2})[_\sT](\d{2})[_\:]?(\d{2})[_\:]?(\d{2})?/', $filename, $m)) {
            $seg = isset($m[4]) ? $m[4] : '00';
            return "{$m[1]} {$m[2]}:{$m[3]}:{$seg}";
        }

        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $filename, $m)) {
            return "{$m[1]} 12:00:00";
        }

        return date('Y-m-d H:i:s', filemtime($filePath));
    }

    /**
     * Limpia un texto a mayúsculas alfanuméricas sin tildes ni símbolos especiales.
     */
    private function cleanUpperAlphaNum(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $replacements = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'ñ' => 'N', 'Ñ' => 'N', 'ü' => 'U', 'Ü' => 'U',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        ];
        $str = strtr($text, $replacements);
        $unaccented = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
        if ($unaccented === false) {
            $unaccented = $str;
        }

        $cleaned = preg_replace('/[^A-Za-z0-9 ]/', '', (string) $unaccented);
        return trim(strtoupper((string) preg_replace('/\s+/', ' ', (string) $cleaned)));
    }
}

