<?php

declare(strict_types=1);

namespace App\Services\PedidosRechazados;

use App\Models\AppModule;
use App\Models\AppModuleRoute;
use App\Models\PedidoRechazado;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RechazosVentanaService
{
    public const MODULE_CODE = 'pedidos_rechazados';

    /**
     * Catálogo ordenado de días de la semana con su índice ISO (1=Lunes, 7=Domingo).
     */
    public const DIAS_SEMANA = [
        'lunes' => ['nombre' => 'Lunes', 'iso' => 1],
        'martes' => ['nombre' => 'Martes', 'iso' => 2],
        'miercoles' => ['nombre' => 'Miércoles', 'iso' => 3],
        'jueves' => ['nombre' => 'Jueves', 'iso' => 4],
        'viernes' => ['nombre' => 'Viernes', 'iso' => 5],
        'sabado' => ['nombre' => 'Sábado', 'iso' => 6],
        'domingo' => ['nombre' => 'Domingo', 'iso' => 7],
    ];

    /**
     * Configuración por defecto del ciclo operativo de reparto comercial.
     */
    public const DEFAULT_CONFIG = [
        'modo' => 'semanal', // 'semanal' | 'fecha_fija'
        'dias_seleccionados' => ['Viernes', 'Sábado'], // Días de preventa activos para el día de hoy
        'fecha_especifica_activa' => null, // 'YYYY-MM-DD' opcional para feriados
        'fecha_especifica_hasta' => null,  // 'YYYY-MM-DD' opcional para rangos de feriados
        'matriz_semanal' => [
            'lunes' => ['Viernes', 'Sábado'],
            'martes' => ['Lunes'],
            'miercoles' => ['Martes'],
            'jueves' => ['Miércoles'],
            'viernes' => ['Jueves'],
            'sabado' => ['Viernes'],
            'domingo' => ['Viernes', 'Sábado'],
        ],
        'dias_atras_fallback' => 7,
        'incluir_hoy' => false,
    ];

    /**
     * Obtiene el módulo AppModule global de pedidos rechazados asegurando que exista.
     */
    public function getGlobalModule(): AppModule
    {
        return AppModule::firstOrCreate(
            ['code' => self::MODULE_CODE],
            [
                'name' => 'Pedidos Rechazados',
                'description' => 'Auditoría, ventana operativa y justificación de preventas rechazadas en reparto',
                'is_active' => true,
                'order' => 5,
                'config' => self::DEFAULT_CONFIG,
            ]
        );
    }

    /**
     * Obtiene la configuración consolidada (global fusionada con override de ruta si existe).
     *
     * @param string|null $ruta
     * @return array<string, mixed>
     */
    public function getConfig(?string $ruta = null): array
    {
        $modulo = $this->getGlobalModule();
        $globalConfig = is_array($modulo->config) ? $modulo->config : [];
        $merged = array_merge(self::DEFAULT_CONFIG, $globalConfig);

        // Si se provee ruta, buscar si tiene override específico en app_module_routes
        if (!empty($ruta)) {
            $cleanRuta = strtoupper(trim(preg_replace('/\s+/', ' ', $ruta)));
            $override = AppModuleRoute::where('module_code', self::MODULE_CODE)
                ->whereRaw("REPLACE(UPPER(ruta), ' ', '') = ?", [str_replace(' ', '', $cleanRuta)])
                ->first();

            if ($override && is_array($override->config) && !empty($override->config)) {
                $merged = array_merge($merged, $override->config);
                $merged['es_override_ruta'] = true;
            }
        }

        return $merged;
    }

    /**
     * Resuelve las fechas exactas de preventa que se evaluarán para el día actual.
     *
     * @param array<string, mixed> $config
     * @param Carbon|null $now
     * @return array{
     *     modo_aplicado: string,
     *     fechas: array<int, string>,
     *     fecha_min: string,
     *     fecha_max: string,
     *     descripcion: string,
     *     dia_actual: string,
     *     dias_activos: array<int, string>,
     *     es_excepcion: bool
     * }
     */
    public function resolverFechasEvaluadas(array $config, ?Carbon $now = null): array
    {
        $now = $now ?: Carbon::now('America/La_Paz');
        $diaIso = $now->dayOfWeekIso; // 1 = Lunes, 7 = Domingo
        $nombreDiaActualClave = $this->getClaveDiaEspanol($diaIso);
        $nombreDiaActualLegible = $this->getNombreDiaEspanol($diaIso);

        // 1. Prioridad: Excepción manual activa por fecha fija (Feriados o días atípicos seleccionados)
        if (($config['modo'] ?? 'semanal') === 'fecha_fija' && !empty($config['fecha_especifica_activa'])) {
            $fInicio = Carbon::parse($config['fecha_especifica_activa'])->toDateString();
            $fFin = !empty($config['fecha_especifica_hasta'])
                ? Carbon::parse($config['fecha_especifica_hasta'])->toDateString()
                : $fInicio;

            if ($fInicio > $fFin) {
                [$fInicio, $fFin] = [$fFin, $fInicio];
            }

            $fechasLista = [];
            $cursor = Carbon::parse($fInicio);
            $finCarbon = Carbon::parse($fFin);

            while ($cursor->lte($finCarbon)) {
                $fechasLista[] = $cursor->toDateString();
                $cursor->addDay();
            }

            $desc = count($fechasLista) === 1
                ? "Excepción activa (Feriado): Preventa del {$fInicio}"
                : "Excepción activa (Feriado): Preventas del {$fInicio} al {$fFin}";

            return [
                'modo_aplicado' => 'fecha_fija',
                'fechas' => $fechasLista,
                'fecha_min' => $fInicio,
                'fecha_max' => $fFin,
                'descripcion' => $desc,
                'dia_actual' => $nombreDiaActualLegible,
                'dias_activos' => [],
                'es_excepcion' => true,
            ];
        }

        // 2. Modo semanal / selección de días de preventa:
        // Obtener los días de preventa configurados para hoy:
        $diasConfigurados = [];

        // Si se guardaron explícitamente días_seleccionados:
        if (!empty($config['dias_seleccionados']) && is_array($config['dias_seleccionados'])) {
            $diasConfigurados = $config['dias_seleccionados'];
        } else {
            // O leer de la matriz semanal para el día actual
            $matriz = $config['matriz_semanal'] ?? self::DEFAULT_CONFIG['matriz_semanal'];
            $diasConfigurados = $matriz[$nombreDiaActualClave] ?? [];
        }

        // Si aún está vacío, fallback por defecto:
        if (empty($diasConfigurados)) {
            $diasConfigurados = ($diaIso === 1) ? ['Viernes', 'Sábado'] : ['Ayer'];
        }

        $fechasCalculadas = [];
        $diasLegiblesActivos = [];

        foreach ($diasConfigurados as $diaNombre) {
            $diaStr = trim((string) $diaNombre);
            if (empty($diaStr)) {
                continue;
            }

            if (strtolower($diaStr) === 'ayer') {
                $fAyer = $now->copy()->subDay()->toDateString();
                $fechasCalculadas[$fAyer] = true;
                $diasLegiblesActivos[] = 'Ayer';
                continue;
            }

            if (strtolower($diaStr) === 'hoy') {
                $fHoy = $now->toDateString();
                $fechasCalculadas[$fHoy] = true;
                $diasLegiblesActivos[] = 'Hoy';
                continue;
            }

            $targetIso = $this->convertirNombreDiaAIso($diaStr);
            if ($targetIso === null) {
                continue;
            }

            // Calcular cuántos días atrás cayó ese día de la semana
            // E.g., Hoy Lunes (1), Target Viernes (5): (1 - 5 + 7) % 7 = 3 días atrás (Viernes anterior)
            // E.g., Hoy Lunes (1), Target Sábado (6): (1 - 6 + 7) % 7 = 2 días atrás (Sábado anterior)
            $daysAgo = ($diaIso - $targetIso + 7) % 7;
            if ($daysAgo === 0) {
                // Si el día seleccionado es el mismo día actual (ej: Lunes seleccionando Lunes),
                // corresponde al Lunes anterior (hace 7 días)
                $daysAgo = !empty($config['incluir_hoy']) ? 0 : 7;
            }

            $fechaCalculada = $now->copy()->subDays($daysAgo)->toDateString();
            $fechasCalculadas[$fechaCalculada] = true;
            $diasLegiblesActivos[] = $this->getNombreDiaEspanol($targetIso);
        }

        if (empty($fechasCalculadas)) {
            $fPrev = $now->copy()->subDay()->toDateString();
            $fechasCalculadas[$fPrev] = true;
            $diasLegiblesActivos[] = 'Ayer';
        }

        $fechasLista = array_keys($fechasCalculadas);
        sort($fechasLista);

        $fMin = reset($fechasLista);
        $fMax = end($fechasLista);

        $diasStr = implode(' y ', array_unique($diasLegiblesActivos));
        $fechasFormatStr = implode(', ', array_map(fn($f) => Carbon::parse($f)->format('d/m/Y'), $fechasLista));

        return [
            'modo_aplicado' => 'semanal',
            'fechas' => $fechasLista,
            'fecha_min' => $fMin,
            'fecha_max' => $fMax,
            'descripcion' => "Hoy es {$nombreDiaActualLegible}: evaluando preventas del [{$diasStr}] ({$fechasFormatStr})",
            'dia_actual' => $nombreDiaActualLegible,
            'dias_activos' => array_values(array_unique($diasLegiblesActivos)),
            'es_excepcion' => false,
        ];
    }

    /**
     * Retorna las opciones de días de la semana con sus fechas calculadas relativas a hoy.
     * Esto le permite al frontend mostrar directamente el botón con su fecha: ej. "Viernes (30/10)".
     *
     * @param array<int, string> $diasActivos
     * @param Carbon|null $now
     * @return array<int, array<string, mixed>>
     */
    public function getOpcionesDiasConFechas(array $diasActivos = [], ?Carbon $now = null): array
    {
        $now = $now ?: Carbon::now('America/La_Paz');
        $diaIso = $now->dayOfWeekIso;

        // Lista ordenada de días a ofrecer en la interfaz
        $diasOrden = [
            ['nombre' => 'Lunes', 'iso' => 1],
            ['nombre' => 'Martes', 'iso' => 2],
            ['nombre' => 'Miércoles', 'iso' => 3],
            ['nombre' => 'Jueves', 'iso' => 4],
            ['nombre' => 'Viernes', 'iso' => 5],
            ['nombre' => 'Sábado', 'iso' => 6],
            ['nombre' => 'Domingo', 'iso' => 7],
        ];

        $activosNorm = array_map(fn($d) => strtolower(trim(str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], (string)$d))), $diasActivos);

        $opciones = [];
        foreach ($diasOrden as $item) {
            $targetIso = $item['iso'];
            $daysAgo = ($diaIso - $targetIso + 7) % 7;
            if ($daysAgo === 0) {
                $daysAgo = 7;
            }

            $carbonDate = $now->copy()->subDays($daysAgo);
            $cleanNombre = strtolower(trim(str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $item['nombre'])));
            $isSeleccionado = in_array($cleanNombre, $activosNorm, true);

            $opciones[] = [
                'nombre' => $item['nombre'],
                'iso' => $targetIso,
                'fecha_iso' => $carbonDate->toDateString(),
                'fecha_corta' => $carbonDate->format('d/m'),
                'fecha_completa' => $carbonDate->format('d/m/Y'),
                'dias_atras' => $daysAgo,
                'es_hoy' => ($daysAgo === 0),
                'seleccionado' => $isSeleccionado,
            ];
        }

        return $opciones;
    }

    /**
     * Evalúa las preventas y las cruza contra fact_ventas "de esa fecha hacia adelante".
     * Si no tienen factura (o factura < preventa) y no están justificadas en pedidos_rechazados,
     * se retornan como pedidos rechazados pendientes.
     *
     * @param array<int, string> $fechasEvaluadas
     * @param string|null $rutaFiltro
     * @param User|null $user
     * @param bool $incluirJustificados
     * @return array<int, array<string, mixed>>
     */
    public function evaluarPreventas(
        array $fechasEvaluadas,
        ?string $rutaFiltro = null,
        ?User $user = null,
        bool $incluirJustificados = false
    ): array {
        if (empty($fechasEvaluadas)) {
            return [];
        }

        $now = Carbon::now('America/La_Paz');
        $fechaMin = min($fechasEvaluadas);

        // 1. Preventas ya justificadas en la base local (pedidos_rechazados)
        $preventasYaJustificadas = [];
        try {
            $qJust = PedidoRechazado::query()->whereNotNull('nro_preventa');
            if ($user && !$user->isSupervisor() && !$user->isSuperAdmin()) {
                $qJust->where(function ($q) use ($user): void {
                    $q->where('user_id', $user->id)
                      ->orWhere('vendedor_username', $user->username);
                });
            }
            $preventasYaJustificadas = $qJust->pluck('nro_preventa')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning('[RechazosVentanaService] Error leyendo pedidos_rechazados: ' . $e->getMessage());
        }

        // 2. Consultar fact_preventas en supervisor DB para las fechas seleccionadas
        try {
            $query = DB::connection('supervisor')->table('fact_preventas as p')
                ->whereIn('p.fecha_norm', $fechasEvaluadas)
                ->where('p.monto_final', '>', 0)
                ->where('p.cantidad', '>', 0);

            // Filtrado por ruta
            if (!empty($rutaFiltro)) {
                $cleanRuta = str_replace(' ', '', strtoupper(trim($rutaFiltro)));
                $query->where(DB::raw("REPLACE(UPPER(p.ruta), ' ', '')"), $cleanRuta);
            } elseif ($user && !$user->isSupervisor() && !$user->isSuperAdmin()) {
                // Si es un vendedor en Android y no pasó ruta, filtrar por su usuario/ruta
                $query->where(function ($q) use ($user): void {
                    $cleanUser = str_replace(' ', '', strtoupper((string) $user->username));
                    $cleanRuta = str_replace(' ', '', strtoupper((string) $user->ruta));
                    $cleanName = str_replace(' ', '', strtoupper((string) $user->name));

                    $hasCond = false;
                    if (!empty($cleanName)) {
                        $q->where(DB::raw("REPLACE(UPPER(p.vendedor), ' ', '')"), $cleanName);
                        $hasCond = true;
                    }
                    if (!empty($cleanUser)) {
                        $q->orWhere(DB::raw("REPLACE(UPPER(p.username_vendedor), ' ', '')"), $cleanUser)
                          ->orWhere(DB::raw("REPLACE(UPPER(p.ruta), ' ', '')"), $cleanUser);
                        $hasCond = true;
                    }
                    if (!empty($cleanRuta)) {
                        $q->orWhere(DB::raw("REPLACE(UPPER(p.ruta), ' ', '')"), $cleanRuta);
                        $hasCond = true;
                    }
                    if (!$hasCond) {
                        $q->whereRaw('1 = 0');
                    }
                });
            }

            if (!empty($preventasYaJustificadas) && !$incluirJustificados) {
                $query->whereNotIn('p.nro_preventa', $preventasYaJustificadas);
            }

            $items = $query->select([
                'p.id as preventa_item_id',
                'p.nro_preventa',
                'p.fecha_norm as fecha_preventa',
                'p.cliente_id',
                'p.codigo_cliente',
                'p.cliente as cliente_nombre',
                'p.ruta',
                'p.vendedor',
                'p.producto_id',
                'p.codigo_producto',
                'p.producto as producto_nombre',
                'p.categoria',
                'p.cantidad as cantidad_preventa',
                'p.precio_lista',
                'p.monto_final',
            ])
            ->orderByDesc('p.fecha_norm')
            ->orderBy('p.nro_preventa')
            ->get();

            if ($items->isEmpty()) {
                return [];
            }

            // 3. Cruce contra fact_ventas "de esa fecha hacia adelante" (fecha_norm >= fechaMin)
            $nrosPreventa = $items->pluck('nro_preventa')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->toArray();

            $ventasPorItem = [];
            if (!empty($nrosPreventa)) {
                $ventasFacturadas = DB::connection('supervisor')->table('fact_ventas')
                    ->whereIn('pre_venta_id', $nrosPreventa)
                    ->where('fecha_norm', '>=', $fechaMin)
                    ->where('monto_final', '>', 0)
                    ->where('cantidad', '>', 0)
                    ->where(function ($q): void {
                        $q->whereNull('revertida')
                          ->orWhere(function ($sub): void {
                              $sub->where('revertida', '!=', 'Si')
                                  ->where('revertida', '!=', 'SI')
                                  ->where('revertida', '!=', '1');
                          });
                    })
                    ->select([
                        'pre_venta_id',
                        'producto_id',
                        DB::raw('SUM(cantidad) as total_facturado'),
                        DB::raw('SUM(monto_final) as monto_facturado'),
                        DB::raw('MAX(fecha_norm) as fecha_facturada'),
                        DB::raw('MAX(nro_factura) as nro_factura'),
                    ])
                    ->groupBy('pre_venta_id', 'producto_id')
                    ->get();

                foreach ($ventasFacturadas as $v) {
                    $key = trim((string) $v->pre_venta_id) . '_' . trim((string) $v->producto_id);
                    $ventasPorItem[$key] = [
                        'total_facturado' => (int) $v->total_facturado,
                        'monto_facturado' => (float) $v->monto_facturado,
                        'fecha_facturada' => $v->fecha_facturada ? (string) $v->fecha_facturada : null,
                        'nro_factura' => $v->nro_factura ? (string) $v->nro_factura : null,
                    ];
                }
            }

            // 4. Agrupar preventas y determinar ítems rechazados (rechazo total o parcial)
            $preventasTemp = [];
            foreach ($items as $item) {
                $nro = (string) ($item->nro_preventa ?: $item->preventa_item_id);

                if (!isset($preventasTemp[$nro])) {
                    $fechaPrev = (string) $item->fecha_preventa;
                    $diasTranscurridos = (int) Carbon::parse($fechaPrev)->diffInDays($now, false);

                    $preventasTemp[$nro] = [
                        'nro_preventa' => (int) ($item->nro_preventa ?: 0),
                        'pre_venta_id' => (int) ($item->nro_preventa ?: 0),
                        'fecha_preventa' => $fechaPrev,
                        'fecha' => $fechaPrev,
                        'dias_transcurridos' => max(0, $diasTranscurridos),
                        'cliente_id' => (int) $item->cliente_id,
                        'codigo_cliente' => $item->codigo_cliente ?: (string) $item->cliente_id,
                        'cliente_nombre' => $item->cliente_nombre,
                        'cliente' => $item->cliente_nombre,
                        'ruta' => $item->ruta,
                        'vendedor' => $item->vendedor,
                        'tipo_sugerido' => 'TOTAL',
                        'monto_total_preventa' => 0.0,
                        'monto_total_facturado' => 0.0,
                        'monto_total_rechazado' => 0.0,
                        'total_items_preventa' => 0,
                        'total_items_facturados' => 0,
                        'total_items_rechazados' => 0,
                        'fecha_facturada' => null,
                        'nro_factura' => null,
                        'items_rechazados' => [],
                        'items' => [],
                    ];
                }

                $cantPreventa = (int) $item->cantidad_preventa;
                $montoItemPreventa = (float) $item->monto_final;
                $precioUnit = ($cantPreventa > 0 && $montoItemPreventa > 0) ? ($montoItemPreventa / $cantPreventa) : 0.0;

                $itemKey = trim((string) $item->nro_preventa) . '_' . trim((string) $item->producto_id);
                $facturadoInfo = $ventasPorItem[$itemKey] ?? null;

                $cantFacturada = $facturadoInfo ? (int) $facturadoInfo['total_facturado'] : 0;
                $cantRechazada = max(0, $cantPreventa - $cantFacturada);

                $montoRechazado = round($cantRechazada * $precioUnit, 2);
                $montoFacturado = round(min($cantFacturada, $cantPreventa) * $precioUnit, 2);

                if ($facturadoInfo && !empty($facturadoInfo['fecha_facturada'])) {
                    $preventasTemp[$nro]['fecha_facturada'] = $facturadoInfo['fecha_facturada'];
                }
                if ($facturadoInfo && !empty($facturadoInfo['nro_factura'])) {
                    $preventasTemp[$nro]['nro_factura'] = $facturadoInfo['nro_factura'];
                }

                $preventasTemp[$nro]['monto_total_preventa'] += $montoItemPreventa;
                $preventasTemp[$nro]['monto_total_facturado'] += $montoFacturado;
                $preventasTemp[$nro]['monto_total_rechazado'] += $montoRechazado;
                $preventasTemp[$nro]['total_items_preventa'] += $cantPreventa;
                $preventasTemp[$nro]['total_items_facturados'] += $cantFacturada;
                $preventasTemp[$nro]['total_items_rechazados'] += $cantRechazada;

                $itemData = [
                    'preventa_item_id' => $item->preventa_item_id,
                    'producto_id' => (int) $item->producto_id,
                    'codigo_producto' => $item->codigo_producto,
                    'producto_nombre' => $item->producto_nombre,
                    'producto' => $item->producto_nombre,
                    'categoria' => $item->categoria,
                    'cantidad_preventa' => $cantPreventa,
                    'cantidad_facturada' => $cantFacturada,
                    'cantidad_rechazada' => $cantRechazada,
                    'precio_unitario' => round($precioUnit, 2),
                    'monto_preventa' => round($montoItemPreventa, 2),
                    'monto_facturado' => round($montoFacturado, 2),
                    'monto_rechazado' => round($montoRechazado, 2),
                    'es_parcial' => ($cantFacturada > 0 && $cantRechazada > 0),
                    'es_totalmente_rechazado' => ($cantFacturada === 0 && $cantRechazada > 0),
                    'fecha_facturada' => $facturadoInfo['fecha_facturada'] ?? null,
                    'nro_factura' => $facturadoInfo['nro_factura'] ?? null,
                ];

                $preventasTemp[$nro]['items'][] = $itemData;
                if ($cantRechazada > 0) {
                    $preventasTemp[$nro]['items_rechazados'][] = $itemData;
                }
            }

            // 5. Filtrar exclusivamente las que tengan al menos 1 ítem con cantidad rechazada > 0
            $preventasPendientes = [];
            foreach ($preventasTemp as $nro => $prev) {
                if ($prev['total_items_rechazados'] > 0) {
                    $prev['tipo_sugerido'] = ($prev['total_items_facturados'] > 0) ? 'PARCIAL' : 'TOTAL';
                    $prev['monto_total_preventa'] = round($prev['monto_total_preventa'], 2);
                    $prev['monto_total_facturado'] = round($prev['monto_total_facturado'], 2);
                    $prev['monto_total_rechazado'] = round($prev['monto_total_rechazado'], 2);

                    $preventasPendientes[] = $prev;
                }
            }

            return $preventasPendientes;
        } catch (\Throwable $e) {
            Log::error('[RechazosVentanaService] Error evaluando preventas pendientes: ' . $e->getMessage(), [
                'exception' => $e,
                'fechas' => $fechasEvaluadas,
                'ruta' => $rutaFiltro,
            ]);
            return [];
        }
    }

    /**
     * Mapeo de clave de día a nombre legible.
     */
    public function getNombreDiaEspanol(int $iso): string
    {
        $map = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];
        return $map[$iso] ?? 'Lunes';
    }

    public function getClaveDiaEspanol(int $iso): string
    {
        $map = [
            1 => 'lunes',
            2 => 'martes',
            3 => 'miercoles',
            4 => 'jueves',
            5 => 'viernes',
            6 => 'sabado',
            7 => 'domingo',
        ];
        return $map[$iso] ?? 'lunes';
    }

    public function convertirNombreDiaAIso(string $nombre): ?int
    {
        $clean = strtolower(trim(str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $nombre)));
        $map = [
            'lunes' => 1,
            'martes' => 2,
            'miercoles' => 3,
            'jueves' => 4,
            'viernes' => 5,
            'sabado' => 6,
            'domingo' => 7,
        ];
        return $map[$clean] ?? null;
    }
}
