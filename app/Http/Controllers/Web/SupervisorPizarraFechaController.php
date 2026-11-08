<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Web\Supervisor\Preventas\GetCumplimientoDataAction;
use App\Http\Controllers\Controller;
use App\Models\AppModule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorPizarraFechaController extends Controller
{
    /**
     * Muestra la pantalla de configuración de fecha de preventa activa para la Pizarra Móvil.
     */
    public function index(GetCumplimientoDataAction $cumplimientoAction): Response
    {
        $data = $this->obtenerDatosPizarraFecha($cumplimientoAction);

        return Inertia::render('Supervisor/PizarraFecha/Index', [
            'initialData' => $data,
        ]);
    }

    /**
     * Devuelve los datos actualizados en JSON para recargas asíncronas.
     */
    public function data(GetCumplimientoDataAction $cumplimientoAction): JsonResponse
    {
        $data = $this->obtenerDatosPizarraFecha($cumplimientoAction);

        return response()->json($data, 200);
    }

    /**
     * Guarda la configuración de fecha de preventa (modo 'hoy' o 'fecha_fija').
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'modo' => 'required|string|in:hoy,fecha_fija',
            'fecha_especifica' => 'nullable|date_format:Y-m-d',
        ]);

        $modulo = AppModule::firstOrCreate(
            ['code' => 'pizarra'],
            [
                'name' => 'Pizarra Unilever',
                'description' => 'Evaluación de los 8 KPIs sobre 100 puntos en tiempo real (Modelo Unilever DSD)',
                'is_active' => true,
                'order' => 8,
                'config' => [
                    'modo' => 'hoy',
                    'fecha_especifica' => null,
                ],
            ]
        );

        $modo = (string) $validated['modo'];
        $fechaEspecifica = ($modo === 'fecha_fija' && !empty($validated['fecha_especifica']))
            ? (string) $validated['fecha_especifica']
            : null;

        $modulo->setConfigValue('modo', $modo);
        $modulo->setConfigValue('fecha_especifica', $fechaEspecifica);
        $modulo->save();

        $mensaje = ($modo === 'fecha_fija')
            ? "Pizarra fijada en la fecha {$fechaEspecifica} para todas las rutas."
            : 'Pizarra restablecida al modo automático (Día de hoy).';

        return response()->json([
            'success' => true,
            'message' => $mensaje,
            'config' => [
                'modo' => $modo,
                'fecha_especifica' => $fechaEspecifica,
            ],
        ], 200);
    }

    /**
     * Restablece la configuración al modo automático del día de hoy.
     */
    public function reset(): JsonResponse
    {
        $modulo = AppModule::where('code', 'pizarra')->first();
        if ($modulo) {
            $modulo->setConfigValue('modo', 'hoy');
            $modulo->setConfigValue('fecha_especifica', null);
            $modulo->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Configuración restablecida al día en curso (Automático) exitosamente.',
            'config' => [
                'modo' => 'hoy',
                'fecha_especifica' => null,
            ],
        ], 200);
    }

    /**
     * Compila la configuración, fechas recientes y vista previa de rutas.
     *
     * @return array<string, mixed>
     */
    private function obtenerDatosPizarraFecha(GetCumplimientoDataAction $cumplimientoAction): array
    {
        $nowBolivia = Carbon::now('America/La_Paz');
        $fechaHoy = $nowBolivia->toDateString();

        $modulo = AppModule::firstOrCreate(
            ['code' => 'pizarra'],
            [
                'name' => 'Pizarra Unilever',
                'description' => 'Evaluación de los 8 KPIs sobre 100 puntos en tiempo real (Modelo Unilever DSD)',
                'is_active' => true,
                'order' => 8,
                'config' => [
                    'modo' => 'hoy',
                    'fecha_especifica' => null,
                ],
            ]
        );

        $modo = $modulo->getConfigValue('modo', 'hoy');
        $fechaConfigurada = $modulo->getConfigValue('fecha_especifica');

        // Determinar fecha activa efectiva que los vendedores ven en sus celulares
        if ($modo === 'fecha_fija' && !empty($fechaConfigurada)) {
            $fechaActivaEfectiva = (string) $fechaConfigurada;
        } else {
            $tienePreventasHoy = DB::connection('supervisor')
                ->table('fact_preventas')
                ->where('fecha_norm', $fechaHoy)
                ->where('monto_final', '>', 0)
                ->exists();

            if ($tienePreventasHoy) {
                $fechaActivaEfectiva = $fechaHoy;
            } else {
                $maxFecha = DB::connection('supervisor')
                    ->table('fact_preventas')
                    ->where('monto_final', '>', 0)
                    ->max('fecha_norm');
                $fechaActivaEfectiva = $maxFecha ?: $fechaHoy;
            }
        }

        // Obtener historial de las últimas 15 fechas con preventas registradas
        $fechasRecientes = DB::connection('supervisor')
            ->table('fact_preventas')
            ->where('monto_final', '>', 0)
            ->whereNotNull('fecha_norm')
            ->select([
                'fecha_norm as fecha',
                DB::raw('COUNT(DISTINCT nro_pedido) as total_pedidos'),
                DB::raw('COUNT(DISTINCT cliente_id) as total_clientes'),
                DB::raw('COUNT(DISTINCT ruta) as total_rutas'),
                DB::raw('ROUND(SUM(monto_final), 2) as venta_total'),
            ])
            ->groupBy('fecha_norm')
            ->orderBy('fecha_norm', 'desc')
            ->limit(15)
            ->get();

        // Mapear días de la semana
        $diasMap = [
            1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
            5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo',
        ];

        $fechasOpciones = $fechasRecientes->map(function ($item) use ($diasMap, $fechaHoy): array {
            $carbon = Carbon::parse($item->fecha);
            return [
                'fecha' => $item->fecha,
                'dia_semana' => $diasMap[$carbon->dayOfWeekIso] ?? '',
                'es_hoy' => ($item->fecha === $fechaHoy),
                'total_pedidos' => (int) $item->total_pedidos,
                'total_clientes' => (int) $item->total_clientes,
                'total_rutas' => (int) $item->total_rutas,
                'venta_total' => (float) $item->venta_total,
            ];
        })->toArray();

        // Calcular resumen rápido de la pizarra para la fecha activa efectiva
        $preview = [];
        $kpisGlobales = [];
        try {
            $pizarraData = $cumplimientoAction->execute(
                fecha: $fechaActivaEfectiva,
                canales: [],
                rutas: [],
                estrictoHorario: false,
                semanasHistoria: 13
            );

            $kpisGlobales = $pizarraData['kpis_globales'] ?? [];
            foreach ($pizarraData['rutas'] ?? [] as $r) {
                $preview[] = [
                    'rank' => (int) ($r['rank'] ?? 0),
                    'ruta' => (string) ($r['ruta'] ?? ''),
                    'vendedor' => (string) ($r['vendedor'] ?? ''),
                    'canal' => (string) ($r['canal'] ?? ''),
                    'score_total' => (int) ($r['score_total'] ?? 0),
                    'aprobado' => ((int) ($r['score_total'] ?? 0) >= 80),
                    'kpi1_horario' => (bool) ($r['kpi1_horario']['cumple'] ?? false),
                    'kpi2_visitas' => (bool) ($r['kpi2_visitas']['cumple'] ?? false),
                    'kpi3_objetivo' => (bool) ($r['kpi3_objetivo']['cumple'] ?? false),
                    'kpi4_efectividad' => (bool) ($r['kpi4_efectividad']['cumple'] ?? false),
                    'kpi5_compra_minima' => (bool) ($r['kpi5_compra_minima']['cumple'] ?? false),
                    'kpi6_reposicion' => (bool) ($r['kpi6_reposicion']['cumple'] ?? false),
                    'kpi7_ampliacion' => (bool) ($r['kpi7_ampliacion']['cumple'] ?? false),
                    'kpi8_covers' => (bool) ($r['kpi8_covers']['cumple'] ?? false),
                ];
            }
        } catch (\Throwable $e) {
            // Log silently and continue
        }

        $carbonEfectivo = Carbon::parse($fechaActivaEfectiva);
        $diaSemanaEfectivo = $diasMap[$carbonEfectivo->dayOfWeekIso] ?? '';

        return [
            'fecha_hoy' => $fechaHoy,
            'modo' => $modo,
            'fecha_configurada' => $fechaConfigurada,
            'fecha_activa_efectiva' => $fechaActivaEfectiva,
            'dia_semana_efectivo' => $diaSemanaEfectivo,
            'fechas_opciones' => $fechasOpciones,
            'kpis_globales' => $kpisGlobales,
            'preview_rutas' => $preview,
        ];
    }
}
