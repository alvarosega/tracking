<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Ingesta;

use App\Services\Ingesta\ExcelCsvParser;
use App\Services\Ingesta\IngestaValidator;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ProcessRuteoIngestaAction
{
    /**
     * Columnas canónicas de pan_ruteo (excluyendo id autoincremental).
     *
     * @var array<int, string>
     */
    public const CANONICAL_COLUMNS = [
        'nodo_id', 'nodo', 'cliente_id', 'cliente', 'vendedor', 'tipo_negocio',
        'territorio', 'mercado', 'fecha_servidor', 'fecha_dispositivo', 'contacto',
        'ci', 'telefono', 'celular', 'direccion', 'nro', 'referencia',
        'nombre_factura', 'nit', 'tipo_pago', 'latitud', 'longitud', 'intensidad',
        'estado', 'zona_venta', 'cod_vendedor', 'dias_credito', 'limite', 'ruta',
        'dia', 'precio', 'cluster_t', 'cluster_c', 'cliente_norm', 'vendedor_norm',
        'dia_norm',
    ];

    /**
     * Columnas para auditoría general de modificaciones (excluyendo cluster, vendedor, estado, coordenadas y derivadas).
     *
     * @var array<int, string>
     */
    public const GENERAL_DIFF_COLUMNS = [
        'cliente', 'contacto', 'ci', 'telefono', 'celular', 'direccion', 'nro',
        'referencia', 'nombre_factura', 'nit', 'tipo_pago', 'dias_credito',
        'limite', 'precio', 'intensidad', 'territorio', 'mercado', 'zona_venta',
        'nodo_id', 'nodo',
    ];

    public function __construct(
        protected ExcelCsvParser $parser,
        protected IngestaValidator $validator
    ) {}

    /**
     * Procesa el reemplazo maestro o actualización del Plan de Ruteo con snapshot inmutable y auditoría granular.
     *
     * @return array<string, mixed>
     */
    public function execute(UploadedFile|string $file, ?string $originalFilename = null, ?string $fechaArchivo = null): array
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $filePath = is_string($file) ? $file : $file->getRealPath();
        $filename = $originalFilename ?? (is_string($file) ? basename($file) : $file->getClientOriginalName());

        $headers = $this->parser->extractHeaders($filePath, $filename);
        $this->validator->validateSchema($headers, 'PAN_RUTEO');

        $rawRows = $this->parser->parseToAssociativeRows($filePath, 0, $filename);
        $totalLeidas = count($rawRows);

        if ($totalLeidas === 0) {
            throw new Exception("El archivo de ruteo está vacío.");
        }

        $fechaOperativa = $this->determinarFechaOperativa($filePath, $filename, $fechaArchivo);
        $snapshotId = 'snap_' . date('Ymd_His', strtotime($fechaOperativa));

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

        $clientesEnArchivo = [];
        $registrosSnapshot = [];
        $registrosVivos = [];
        $visitasUnicasArchivo = [];

        foreach ($rawRows as $r) {
            $mapped = $this->validator->mapRow($r, 'PAN_RUTEO');

            $cid = isset($mapped['cliente_id']) ? (int) $mapped['cliente_id'] : 0;
            if ($cid <= 0) {
                continue;
            }

            // 1. Ensamble de Nombre de Cliente si viene desglosado
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

            // 2. Normalización de Estado (Activo / Inactivo)
            if (isset($mapped['activo']) && !isset($mapped['estado'])) {
                $actStr = strtoupper(trim((string) $mapped['activo']));
                $estadoNorm = in_array($actStr, ['SI', 'S', '1', 'TRUE', 'ACTIVO'], true) ? 'Activo' : 'Inactivo';
            } elseif (isset($mapped['estado'])) {
                $stStr = strtoupper(trim((string) $mapped['estado']));
                $estadoNorm = in_array($stStr, ['NO', '0', 'INACTIVO', 'BAJA', 'ANULADO'], true) ? 'Inactivo' : 'Activo';
            } else {
                $estadoNorm = 'Activo';
            }

            // 3. Normalización de Día y Día Norm
            $diaRaw = (string) ($mapped['dia_norm'] ?? $mapped['dia'] ?? '');
            $diaNorm = IngestaValidator::sanitizeDia($diaRaw);
            $diaCanonical = ucfirst($diaNorm ?: $diaRaw);

            // 4. Derivados técnicos: cliente_norm y vendedor_norm
            $clienteNorm = $this->cleanUpperAlphaNum($clienteNombre);

            $rutaRaw = trim((string) ($mapped['ruta'] ?? ''));
            $vendedorRaw = trim((string) ($mapped['vendedor'] ?? ''));
            if ($vendedorRaw === '' && $rutaRaw !== '' && isset($vendedoresPorRuta[$rutaRaw])) {
                $vendedorRaw = (string) $vendedoresPorRuta[$rutaRaw];
            }
            $vendedorNorm = $this->cleanUpperAlphaNum($vendedorRaw);

            // 5. Coordenadas numéricas float o null
            $lat = isset($mapped['latitud']) && is_numeric($mapped['latitud']) ? (float) $mapped['latitud'] : null;
            $lon = isset($mapped['longitud']) && is_numeric($mapped['longitud']) ? (float) $mapped['longitud'] : null;

            // 6. Montos y límites
            $limite = isset($mapped['limite']) && is_numeric($mapped['limite']) ? (float) $mapped['limite'] : 0.0;
            $diasCredito = isset($mapped['dias_credito']) && is_numeric($mapped['dias_credito']) ? (int) $mapped['dias_credito'] : 0;

            // 7. Deduplicación intra-archivo por (cliente_id, dia_norm, ruta)
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

            if (!isset($clientesEnArchivo[$cid])) {
                $clientesEnArchivo[$cid] = [];
            }
            $clientesEnArchivo[$cid][] = $filaNorm;

            $snapRow = array_merge($filaNorm, [
                'snapshot_id' => $snapshotId,
                'archivo_origen' => $filename,
                'fecha_carga' => $fechaOperativa,
            ]);

            $registrosSnapshot[] = $snapRow;
            $registrosVivos[] = $filaNorm;
        }

        // Consultar clientes existentes en pan_ruteo
        $filasBd = $conn->table('pan_ruteo')->get();
        $clientesEnBd = [];
        foreach ($filasBd as $r) {
            $cid = (int) $r->cliente_id;
            if ($cid <= 0) continue;
            if (!isset($clientesEnBd[$cid])) {
                $clientesEnBd[$cid] = [];
            }
            $clientesEnBd[$cid][] = (array) $r;
        }

        $cidsArchivo = array_keys($clientesEnArchivo);
        $cidsBd = array_keys($clientesEnBd);
        $setArch = array_flip($cidsArchivo);
        $setBd = array_flip($cidsBd);

        $totalBdAnterior = count($clientesEnBd);
        $totalClientesArchivo = count($clientesEnArchivo);

        // Carga Completa (Reemplazo Maestro) vs Carga Parcial (Sincronización/Upsert Seguro)
        // Se considera Carga Completa si la BD previa está vacía/pequeña (<100) o si el archivo
        // contiene al menos el 70% del universo de clientes existente.
        $esCargaCompleta = ($totalBdAnterior < 100) || ($totalClientesArchivo >= ($totalBdAnterior * 0.70));

        $registrosAuditoria = [];
        $altasCnt = 0;
        $modificadosCnt = 0;
        $eliminadosCnt = 0;
        $sinCambiosCnt = 0;

        $esBaselineEfectivo = ($totalBdAnterior === 0);

        if ($esBaselineEfectivo) {
            // Modo Baseline
            foreach ($registrosVivos as $rv) {
                $altasCnt++;
                $registrosAuditoria[] = [
                    'archivo_origen' => $filename,
                    'cliente_id' => (int) $rv['cliente_id'],
                    'cliente' => (string) ($rv['cliente'] ?? ''),
                    'tipo_evento' => 'NUEVO',
                    'campo_afectado' => 'cliente_alta_total',
                    'valor_anterior' => null,
                    'valor_nuevo' => "Ruta: {$rv['ruta']} | Dia: {$rv['dia']}",
                    'fecha_auditoria' => $fechaOperativa,
                    'estado_auditoria' => 'PENDIENTE',
                    'observaciones' => 'Snapshot inicial baseline.',
                    'auditado_por' => 'SISTEMA_INGESTA_WEB',
                ];
            }
        } else {
            // Modo Diferencial
            // B1. Clientes Desaparecidos (en previos pero no en entrantes)
            if ($esCargaCompleta) {
                foreach ($cidsBd as $cid) {
                    if (!isset($setArch[$cid])) {
                        $eliminadosCnt++;
                        foreach ($clientesEnBd[$cid] as $prev) {
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
                                'auditado_por' => 'SISTEMA_INGESTA_WEB',
                            ];
                        }
                    }
                }
            }

            // B2. Clientes Nuevos (en entrantes pero no en previos)
            foreach ($cidsArchivo as $cid) {
                if (!isset($setBd[$cid])) {
                    $altasCnt++;
                    foreach ($clientesEnArchivo[$cid] as $nuevo) {
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
                            'auditado_por' => 'SISTEMA_INGESTA_WEB',
                        ];
                    }
                }
            }

            // B3. Clientes Existentes (Reconciliación Jerárquica de Visitas)
            foreach ($cidsArchivo as $cid) {
                if (isset($setBd[$cid])) {
                    $listaP = $clientesEnBd[$cid];
                    $listaE = $clientesEnArchivo[$cid];

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

        $chunkSize = 500;

        // 1. Guardar Snapshot Inmutable
        if (!empty($registrosSnapshot)) {
            $chunksSnap = array_chunk($registrosSnapshot, $chunkSize);
            foreach ($chunksSnap as $chunk) {
                $conn->table('pan_ruteo_snapshots')->insert($chunk);
            }
        }

        // 2. Guardar Auditoría
        if (!empty($registrosAuditoria)) {
            $chunksAud = array_chunk($registrosAuditoria, $chunkSize);
            foreach ($chunksAud as $chunk) {
                $conn->table('auditoria_ruteo_cambios')->insert($chunk);
            }
        }

        // 3. Actualización de pan_ruteo
        if ($esCargaCompleta) {
            // Reemplazo Atómico Transaccional Completo
            $conn->transaction(function () use ($conn, $registrosVivos, $chunkSize) {
                $conn->table('pan_ruteo')->delete();

                if (!empty($registrosVivos)) {
                    $chunksVivos = array_chunk($registrosVivos, $chunkSize);
                    foreach ($chunksVivos as $chunk) {
                        $conn->table('pan_ruteo')->insert($chunk);
                    }
                }
            });
        } else {
            // Sincronización / Upsert Parcial Seguro:
            // Borra solo las visitas de los clientes presentes en el archivo y reinserta sus nuevas visitas,
            // preservando intactos todos los demás clientes de pan_ruteo.
            $conn->transaction(function () use ($conn, $cidsArchivo, $registrosVivos, $chunkSize) {
                if (!empty($cidsArchivo)) {
                    $chunksCids = array_chunk($cidsArchivo, 500);
                    foreach ($chunksCids as $chunkCid) {
                        $conn->table('pan_ruteo')->whereIn('cliente_id', $chunkCid)->delete();
                    }
                }

                if (!empty($registrosVivos)) {
                    $chunksVivos = array_chunk($registrosVivos, $chunkSize);
                    foreach ($chunksVivos as $chunk) {
                        $conn->table('pan_ruteo')->insert($chunk);
                    }
                }
            });
        }

        // 4. Registrar Bitácora en sys_cargas
        $totalVivos = count($registrosVivos);
        $sha256 = hash_file('sha256', $filePath) ?: $snapshotId;
        try {
            $cargaData = [
                'archivo_original' => $filename,
                'archivo_almacenado' => $filePath,
                'sha256' => $sha256,
                'tipo_entidad' => 'PAN_RUTEO',
                'filas_leidas' => $totalLeidas,
                'filas_afectadas' => $totalVivos,
                'filas_nuevas' => $altasCnt,
                'filas_actualizadas' => $modificadosCnt,
                'filas_ignoradas' => $sinCambiosCnt,
                'filas_con_discrepancia' => $eliminadosCnt,
                'rango_inicio' => '-',
                'rango_fin' => '-',
                'fecha_ingesta' => $fechaOperativa,
                'estado' => 'COMPLETADO',
            ];

            $conn->table('sys_cargas')->updateOrInsert(
                ['sha256' => $sha256, 'tipo_entidad' => 'PAN_RUTEO'],
                $cargaData
            );
        } catch (Throwable $eSys) {
            Log::warning("No se pudo registrar en sys_cargas: " . $eSys->getMessage());
        }

        $this->parser->clearCache();

        return [
            'status' => 'success',
            'archivo' => $filename,
            'snapshot_id' => $snapshotId,
            'fecha_registrada' => $fechaOperativa,
            'es_carga_completa' => $esCargaCompleta,
            'modo' => $esCargaCompleta ? 'REEMPLAZO_MAESTRO' : 'ACTUALIZACION_PARCIAL',
            'total_registros_auditoria' => count($registrosAuditoria),
            'filas_leidas' => $totalLeidas,
            'filas_afectadas' => $totalVivos,
            'filas_nuevas' => $altasCnt,
            'nuevos' => $altasCnt,
            'filas_actualizadas' => $modificadosCnt,
            'modificados' => $modificadosCnt,
            'filas_con_discrepancia' => $eliminadosCnt,
            'cancelados' => $eliminadosCnt,
            'eliminadas' => $eliminadosCnt,
            'filas_ignoradas' => $sinCambiosCnt,
            'sin_cambios' => $sinCambiosCnt,
            'metricas' => [
                'total_filas_archivo' => $totalLeidas,
                'clientes_unicos_archivo' => count($clientesEnArchivo),
                'total_bd_anterior' => $totalBdAnterior,
                'total_nuevos' => $altasCnt,
                'total_modificados' => $modificadosCnt,
                'total_eliminados' => $eliminadosCnt,
                'total_sin_cambios' => $sinCambiosCnt,
            ],
        ];
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
                        'auditado_por' => 'SISTEMA_INGESTA_WEB',
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
                        'auditado_por' => 'SISTEMA_INGESTA_WEB',
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
                    'auditado_por' => 'SISTEMA_INGESTA_WEB',
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
                    'auditado_por' => 'SISTEMA_INGESTA_WEB',
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
                'auditado_por' => 'SISTEMA_INGESTA_WEB',
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
                'auditado_por' => 'SISTEMA_INGESTA_WEB',
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
                    'auditado_por' => 'SISTEMA_INGESTA_WEB',
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
                    'auditado_por' => 'SISTEMA_INGESTA_WEB',
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
                    'auditado_por' => 'SISTEMA_INGESTA_WEB',
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
                    'auditado_por' => 'SISTEMA_INGESTA_WEB',
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
                        'auditado_por' => 'SISTEMA_INGESTA_WEB',
                    ];
                }
            }
        }

        return $eventos;
    }

    /**
     * Determina la fecha operativa del archivo para consistencia histórica.
     */
    private function determinarFechaOperativa(string $filePath, string $filename, ?string $fechaCustom): string
    {
        if (!empty($fechaCustom)) {
            $fTrim = trim($fechaCustom);
            if (strlen($fTrim) === 10 && $fTrim[4] === '-' && $fTrim[7] === '-') {
                return "{$fTrim} 12:00:00";
            }
            return $fTrim;
        }

        if (preg_match('/ruteo_(\d{4}-\d{2}-\d{2})_(\d{2})(\d{2})(\d{2})/', $filename, $m)) {
            return "{$m[1]} {$m[2]}:{$m[3]}:{$m[4]}";
        }

        if (preg_match('/(\d{4}-\d{2}-\d{2})[_\sT](\d{2})[_\:]?(\d{2})[_\:]?(\d{2})?/', $filename, $m)) {
            $seg = isset($m[4]) ? $m[4] : '00';
            return "{$m[1]} {$m[2]}:{$m[3]}:{$seg}";
        }

        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $filename, $m)) {
            return "{$m[1]} 12:00:00";
        }

        return date('Y-m-d H:i:s', filemtime($filePath) ?: time());
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
