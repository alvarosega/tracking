<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Web\Supervisor\Preventas\GetCumplimientoDataAction;
use App\Http\Controllers\Controller;
use App\Models\AppModule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PizarraController extends Controller
{
    /**
     * Retorna la información resumida de la Pizarra Modelo Unilever para el vendedor en tiempo real.
     * Consulta directamente la acción oficial del módulo Web (GetCumplimientoDataAction)
     * sin duplicar lógica ni crear tablas adicionales.
     *
     * GET/POST /api/pizarra
     * GET/POST /api/pizarra/resumen
     */
    public function resumen(Request $request, GetCumplimientoDataAction $cumplimientoAction): JsonResponse
    {
        $user = $request->user();

        // 1. Resolver fecha de evaluación (Bolivia UTC-4 por defecto)
        $nowBolivia = Carbon::now('America/La_Paz');
        $fechaParam = $request->input('fecha');

        if (!empty($fechaParam)) {
            $fecha = (string) $fechaParam;
        } else {
            // Consultar si el supervisor configuró una fecha fija para la Pizarra
            $modulo = AppModule::where('code', 'pizarra')->first();
            $modo = $modulo?->getConfigValue('modo', 'hoy') ?? 'hoy';
            $fechaConfig = $modulo?->getConfigValue('fecha_especifica');

            if ($modo === 'fecha_fija' && !empty($fechaConfig)) {
                $fecha = (string) $fechaConfig;
            } else {
                $fechaHoy = $nowBolivia->toDateString();
                $tienePreventasHoy = DB::connection('supervisor')
                    ->table('fact_preventas')
                    ->where('fecha_norm', $fechaHoy)
                    ->where('monto_final', '>', 0)
                    ->exists();

                if ($tienePreventasHoy) {
                    $fecha = $fechaHoy;
                } else {
                    $maxFecha = DB::connection('supervisor')
                        ->table('fact_preventas')
                        ->where('monto_final', '>', 0)
                        ->max('fecha_norm');
                    $fecha = $maxFecha ?: $fechaHoy;
                }
            }
        }

        // 2. Resolver ruta comercial del vendedor
        $rutaInput = $request->input('ruta');
        if (!empty($rutaInput)) {
            $rutaUsuario = trim((string) $rutaInput);
        } elseif ($user) {
            $rutaUsuario = trim((string) ($user->ruta ?: $user->username));
        } else {
            $rutaUsuario = '';
        }
        $rutaClean = strtoupper(trim($rutaUsuario));

        // 3. Consultar la Pizarra oficial calculada por Web
        try {
            $data = $cumplimientoAction->execute(
                fecha: $fecha,
                canales: (array) $request->input('canales', []),
                rutas: [], // evalúa el universo para obtener ranking relativo
                estrictoHorario: (bool) $request->boolean('estricto_horario', false),
                semanasHistoria: 13
            );
        } catch (\Throwable $e) {
            Log::error("PizarraController error consultando GetCumplimientoDataAction: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            return response()->json([
                'status' => 'error',
                'error' => 'Error al obtener datos de la Pizarra Unilever: ' . $e->getMessage(),
            ], 500);
        }

        $rutasEvaluadas = $data['rutas'] ?? [];
        $totalRutas = count($rutasEvaluadas);

        // 4. Ubicar la evaluación de la ruta del vendedor
        $miEvaluacion = null;
        foreach ($rutasEvaluadas as $r) {
            if (strtoupper(trim((string) ($r['ruta'] ?? ''))) === $rutaClean) {
                $miEvaluacion = $r;
                break;
            }
        }

        if ($miEvaluacion === null && !empty($rutaClean)) {
            foreach ($rutasEvaluadas as $r) {
                if (str_contains(strtoupper((string) ($r['ruta'] ?? '')), $rutaClean) ||
                    str_contains(strtoupper((string) ($r['vendedor'] ?? '')), $rutaClean)) {
                    $miEvaluacion = $r;
                    break;
                }
            }
        }

        // 5. Estructurar el resumen de los 8 KPIs para el vendedor móvil
        $miResumen = null;
        if ($miEvaluacion !== null) {
            $score = (int) ($miEvaluacion['score_total'] ?? 0);
            $miResumen = [
                'ruta' => (string) ($miEvaluacion['ruta'] ?? $rutaClean),
                'vendedor' => (string) ($miEvaluacion['vendedor'] ?? ''),
                'canal' => (string) ($miEvaluacion['canal'] ?? ''),
                'score_total' => $score,
                'aprobado' => ($score >= 80),
                'rank' => (int) ($miEvaluacion['rank'] ?? 1),
                'total_rutas' => $totalRutas,
                'kpis' => [
                    'kpi1_horario' => [
                        'nombre' => '1. Horario de Inicio',
                        'puntos' => (int) ($miEvaluacion['kpi1_horario']['puntos'] ?? 0),
                        'puntos_max' => 10,
                        'cumple' => (bool) ($miEvaluacion['kpi1_horario']['cumple'] ?? false),
                        'estado' => (string) ($miEvaluacion['kpi1_horario']['estado'] ?? ''),
                        'primer_checkin' => $miEvaluacion['kpi1_horario']['primer_checkin'] ?? null,
                        'hora_web' => $miEvaluacion['kpi1_horario']['hora_web'] ?? null,
                        'hora_movil' => $miEvaluacion['kpi1_horario']['hora_movil'] ?? null,
                        'umbral' => (string) ($miEvaluacion['kpi1_horario']['umbral'] ?? '08:10:00'),
                        'retraso_minutos' => (int) ($miEvaluacion['kpi1_horario']['retraso_minutos'] ?? 0),
                    ],
                    'kpi2_visitas' => [
                        'nombre' => '2. Cobertura de Visitas',
                        'puntos' => (int) ($miEvaluacion['kpi2_visitas']['puntos'] ?? 0),
                        'puntos_max' => 5,
                        'cumple' => (bool) ($miEvaluacion['kpi2_visitas']['cumple'] ?? false),
                        'cartera_plan' => (int) ($miEvaluacion['kpi2_visitas']['cartera_plan'] ?? 0),
                        'visitados_activos' => (int) ($miEvaluacion['kpi2_visitas']['visitados_activos'] ?? 0),
                        'visitados_web' => (int) ($miEvaluacion['kpi2_visitas']['visitados_web'] ?? 0),
                        'visitados_app' => (int) ($miEvaluacion['kpi2_visitas']['visitados_app'] ?? 0),
                        'total_visitados' => (int) ($miEvaluacion['kpi2_visitas']['total_visitados'] ?? 0),
                        'cobertura_pct' => round((float) ($miEvaluacion['kpi2_visitas']['cobertura_pct'] ?? 0.0), 1),
                    ],
                    'kpi3_objetivo' => [
                        'nombre' => '3. Objetivo Diario',
                        'puntos' => (int) ($miEvaluacion['kpi3_objetivo']['puntos'] ?? 0),
                        'puntos_max' => 10,
                        'cumple' => (bool) ($miEvaluacion['kpi3_objetivo']['cumple'] ?? false),
                        'cuota_diaria' => round((float) ($miEvaluacion['kpi3_objetivo']['cuota_diaria'] ?? 0.0), 2),
                        'venta_hoy' => round((float) ($miEvaluacion['kpi3_objetivo']['venta_hoy'] ?? 0.0), 2),
                        'alcance_pct' => round((float) ($miEvaluacion['kpi3_objetivo']['alcance_pct'] ?? 0.0), 1),
                        'meta_mensual' => round((float) ($miEvaluacion['kpi3_objetivo']['meta_mensual'] ?? 0.0), 2),
                        'facturado_previo' => round((float) ($miEvaluacion['kpi3_objetivo']['facturado_previo'] ?? 0.0), 2),
                        'brecha' => round((float) ($miEvaluacion['kpi3_objetivo']['brecha'] ?? 0.0), 2),
                        'dias_habiles_restantes' => (int) ($miEvaluacion['kpi3_objetivo']['dias_habiles_restantes'] ?? 0),
                    ],
                    'kpi4_efectividad' => [
                        'nombre' => '4. Efectividad de Venta',
                        'puntos' => (int) ($miEvaluacion['kpi4_efectividad']['puntos'] ?? 0),
                        'puntos_max' => 30,
                        'cumple' => (bool) ($miEvaluacion['kpi4_efectividad']['cumple'] ?? false),
                        'cartera_plan' => (int) ($miEvaluacion['kpi4_efectividad']['cartera_plan'] ?? 0),
                        'compradores' => (int) ($miEvaluacion['kpi4_efectividad']['compradores'] ?? 0),
                        'compradores_sobre_70' => (int) ($miEvaluacion['kpi4_efectividad']['compradores_sobre_70'] ?? 0),
                        'compradores_bajo_70' => (int) ($miEvaluacion['kpi4_efectividad']['compradores_bajo_70'] ?? 0),
                        'efectividad_pct' => round((float) ($miEvaluacion['kpi4_efectividad']['efectividad_pct'] ?? 0.0), 1),
                        'umbral_pct' => round((float) ($miEvaluacion['kpi4_efectividad']['umbral_pct'] ?? 80.0), 1),
                    ],
                    'kpi5_compra_minima' => [
                        'nombre' => '5. Compra Mínima (Drop Size)',
                        'puntos' => (int) ($miEvaluacion['kpi5_compra_minima']['puntos'] ?? 0),
                        'puntos_max' => 5,
                        'cumple' => (bool) ($miEvaluacion['kpi5_compra_minima']['cumple'] ?? false),
                        'compradores' => (int) ($miEvaluacion['kpi5_compra_minima']['compradores'] ?? 0),
                        'compradores_bajo_umbral' => (int) ($miEvaluacion['kpi5_compra_minima']['compradores_bajo_70'] ?? 0),
                        'compradores_sobre_umbral' => max(0, (int) ($miEvaluacion['kpi5_compra_minima']['compradores'] ?? 0) - (int) ($miEvaluacion['kpi5_compra_minima']['compradores_bajo_70'] ?? 0)),
                        'monto_minimo_estandar' => round((float) ($miEvaluacion['kpi5_compra_minima']['monto_minimo_estandar'] ?? 70.0), 2),
                    ],
                    'kpi6_reposicion' => [
                        'nombre' => '6. Reposición (Canasta Habitual)',
                        'puntos' => (int) ($miEvaluacion['kpi6_reposicion']['puntos'] ?? 0),
                        'puntos_max' => 15,
                        'cumple' => (bool) ($miEvaluacion['kpi6_reposicion']['cumple'] ?? false),
                        'evaluado' => (bool) ($miEvaluacion['kpi6_reposicion']['evaluado'] ?? true),
                        'compradores' => (int) ($miEvaluacion['kpi6_reposicion']['compradores'] ?? 0),
                        'evaluables' => (int) ($miEvaluacion['kpi6_reposicion']['evaluables'] ?? 0),
                        'tasa_pct' => round((float) ($miEvaluacion['kpi6_reposicion']['tasa_pct'] ?? 0.0), 1),
                        'umbral_pct' => round((float) ($miEvaluacion['kpi6_reposicion']['umbral_pct'] ?? 20.0), 1),
                        'skus_match' => (int) ($miEvaluacion['kpi6_reposicion']['skus_match'] ?? 0),
                        'skus_esperados' => (int) ($miEvaluacion['kpi6_reposicion']['skus_esperados'] ?? 0),
                    ],
                    'kpi7_ampliacion' => [
                        'nombre' => '7. Ampliación de Portafolio',
                        'puntos' => 0,
                        'puntos_max' => 20,
                        'cumple' => false,
                        'evaluado' => false,
                        'pendiente_desarrollo' => true,
                        'estado' => 'PENDIENTE_DESARROLLO',
                        'tasa_pct' => 0.0,
                        'umbral_pct' => 30.0,
                        'skus_ampliacion' => 0,
                        'total_skus' => 0,
                    ],
                    'kpi8_covers' => [
                        'nombre' => '8. Covers / Descuentos',
                        'puntos' => (int) ($miEvaluacion['kpi8_covers']['puntos'] ?? 0),
                        'puntos_max' => 5,
                        'cumple' => (bool) ($miEvaluacion['kpi8_covers']['cumple'] ?? false),
                        'evaluado' => (bool) ($miEvaluacion['kpi8_covers']['evaluado'] ?? true),
                        'total_compradores' => (int) ($miEvaluacion['kpi8_covers']['total_compradores'] ?? 0),
                        'cumplen' => (int) ($miEvaluacion['kpi8_covers']['cumplen'] ?? 0),
                        'tasa_pct' => round((float) ($miEvaluacion['kpi8_covers']['tasa_pct'] ?? 0.0), 1),
                        'umbral_pct' => round((float) ($miEvaluacion['kpi8_covers']['umbral_pct'] ?? 50.0), 1),
                    ],
                ],
            ];
        }

        // 6. Ranking general simplificado (para que el vendedor compare su desempeño)
        $ranking = array_map(function ($r) use ($rutaClean): array {
            return [
                'rank' => (int) ($r['rank'] ?? 0),
                'ruta' => (string) ($r['ruta'] ?? ''),
                'vendedor' => (string) ($r['vendedor'] ?? ''),
                'canal' => (string) ($r['canal'] ?? ''),
                'score_total' => (int) ($r['score_total'] ?? 0),
                'aprobado' => ((int) ($r['score_total'] ?? 0) >= 80),
                'es_mi_ruta' => (strtoupper(trim((string) ($r['ruta'] ?? ''))) === $rutaClean),
            ];
        }, $rutasEvaluadas);

        return response()->json([
            'status' => 'success',
            'fecha' => $data['fecha'] ?? $fecha,
            'dia_semana' => $data['dia_semana'] ?? '',
            'mi_ruta' => $miResumen,
            'kpis_globales' => $data['kpis_globales'] ?? [],
            'resumen_kpis' => $data['kpis_globales']['resumen_kpis'] ?? [],
            'ranking' => $ranking,
        ], 200);
    }
}

