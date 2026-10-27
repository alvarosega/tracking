<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Web\Supervisor\Ruteo\GetCatalogoRutasAction;
use App\Actions\Web\Supervisor\Ruteo\GetCercanosInitDataAction;
use App\Actions\Web\Supervisor\Ruteo\GetClientesCercanosAction;
use App\Actions\Web\Supervisor\Ruteo\GetRuteoDataAction;
use App\Actions\Web\Supervisor\Ruteo\GetVentasClienteAction;
use App\Actions\Web\Supervisor\Ruteo\GetVisitasClienteAction;
use App\Actions\Web\Supervisor\Ruteo\ResolveMapsUrlAction;
use App\Actions\Web\Supervisor\Saneamiento\AuditarCalidadAction;
use App\Actions\Web\Supervisor\Saneamiento\AuditarDiasDesalineadosAction;
use App\Actions\Web\Supervisor\Saneamiento\AuditarDuplicadosAction;
use App\Actions\Web\Supervisor\Saneamiento\AuditarSaneamientoBdAction;
use App\Actions\Web\Supervisor\Saneamiento\ChangeDiaPropuestaAction;
use App\Actions\Web\Supervisor\Saneamiento\ChangeEstadoPropuestaAction;
use App\Actions\Web\Supervisor\Saneamiento\GetClientesFronterasAction;
use App\Actions\Web\Supervisor\Saneamiento\GetSaneamientoCatalogoAction;
use App\Actions\Web\Supervisor\Saneamiento\GetSaneamientoFronterasAction;
use App\Actions\Web\Supervisor\Saneamiento\ListPropuestasAction;
use App\Actions\Web\Supervisor\Saneamiento\RevertPropuestaAction;
use App\Actions\Web\Supervisor\Saneamiento\SaveFronteraDiaAction;
use App\Actions\Web\Supervisor\Saneamiento\SaveFronteraRutaAction;
use App\Actions\Web\Supervisor\Saneamiento\SavePropuestaAction;
use App\Actions\Web\Supervisor\Saneamiento\SearchClientesSaneamientoAction;
use App\Actions\Web\Supervisor\Visitas\GetFotosClienteAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Ruteo\GetCercanosDataRequest;
use App\Http\Requests\Web\Ruteo\GetRuteoDataRequest;
use App\Http\Requests\Web\Ruteo\GetVentasClienteRequest;
use App\Models\Supervisor\PanRuteo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorSaneamientoController extends Controller
{
    /**
     * Vista 1: Visor y Fronteras GIS (Mosaico Territorial y Puntos).
     */
    public function visor(GetCatalogoRutasAction $action): Response
    {
        $catalogoData = $action->execute();

        return Inertia::render('Supervisor/Saneamiento/Visor', [
            'catalogo_rutas' => $catalogoData['catalogo'],
            'canales' => $catalogoData['canales'],
            'submoduloActivo' => 'visor',
        ]);
    }

    /**
     * Datos espaciales de clientes y polígonos para Visor y Fronteras.
     */
    public function getVisorData(GetRuteoDataRequest $request, GetRuteoDataAction $action): JsonResponse
    {
        $resultado = $action->execute(
            canales: $request->canales(),
            rutas: $request->rutas(),
            dias: $request->dias(),
            verFronteraRutas: $request->verFronteraRutas(),
            verFronteraDias: $request->verFronteraDias(),
            soloActivos: $request->soloActivos()
        );

        return response()->json($resultado);
    }

    /**
     * Ajusta y modifica de manera dinámica el RTM de un cliente (Ruta y/o Día de visita).
     */
    public function ajustarRtm(Request $request, SavePropuestaAction $propuestaAction): JsonResponse
    {
        $request->validate([
            'cliente_id' => ['required'],
            'ruta_propuesta' => ['required', 'string'],
            'dia_propuesto' => ['nullable', 'string'],
            'aplicar_inmediato' => ['nullable', 'boolean'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $clienteId = (int) $request->input('cliente_id');
        $rutaPropuesta = strtoupper(trim((string) $request->input('ruta_propuesta')));
        $diaPropuesto = strtoupper(trim((string) $request->input('dia_propuesto', '')));
        $aplicarInmediato = $request->boolean('aplicar_inmediato', true);
        $observaciones = (string) ($request->input('observaciones') ?: 'Ajuste dinámico RTM desde Visor y Fronteras');

        $cliente = PanRuteo::query()->where('cliente_id', $clienteId)->first();
        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => "Cliente ID {$clienteId} no encontrado en el padrón.",
            ], 404);
        }

        $rutaActual = (string) ($cliente->ruta ?? '');
        $diaActual = (string) ($cliente->dia_norm ?? 'SIN DIA');

        // Registrar propuesta para trazabilidad en auditoría
        $propuestaId = $propuestaAction->execute([
            'tipo_hallazgo' => 'AJUSTE_RTM',
            'cliente_id_origen' => $clienteId,
            'cliente_nombre' => $cliente->cliente,
            'ruta_actual' => $rutaActual,
            'dia_actual' => $diaActual,
            'ruta_propuesta' => $rutaPropuesta,
            'dia_propuesto' => $diaPropuesto ?: $diaActual,
            'accion_propuesta' => 'REASIGNAR_RTM',
            'observaciones' => $observaciones,
            'estado' => $aplicarInmediato ? 'APLICADO' : 'PENDIENTE',
        ]);

        // Si se aplica de inmediato, actualizar pan_ruteo
        if ($aplicarInmediato) {
            $updateData = ['ruta' => $rutaPropuesta];
            if (!empty($diaPropuesto)) {
                $updateData['dia_norm'] = $diaPropuesto;
            }
            PanRuteo::query()->where('cliente_id', $clienteId)->update($updateData);
        }

        return response()->json([
            'success' => true,
            'message' => $aplicarInmediato
                ? "RTM actualizado: Cliente asignado a Ruta {$rutaPropuesta}" . ($diaPropuesto ? " ({$diaPropuesto})" : "") . "."
                : "Propuesta de cambio RTM registrada como PENDIENTE.",
            'propuesta_id' => $propuestaId,
            'aplicado' => $aplicarInmediato,
            'cliente_id' => $clienteId,
            'ruta_anterior' => $rutaActual,
            'ruta_nueva' => $rutaPropuesta,
            'dia_anterior' => $diaActual,
            'dia_nuevo' => $diaPropuesto ?: $diaActual,
        ]);
    }

    /**
     * Vista 2: Clientes Cercanos (Radar Geográfico Espacial).
     */
    public function cercanos(GetCercanosInitDataAction $action): Response
    {
        $initData = $action->execute();

        return Inertia::render('Supervisor/Saneamiento/Cercanos', [
            'catalogo_rutas' => $initData['catalogo_rutas'] ?? [],
            'canales' => $initData['canales'] ?? [],
            'anios_disponibles' => $initData['anios_disponibles'] ?? [2026],
            'anio_default' => $initData['anio_default'] ?? 2026,
            'meses_default' => $initData['meses_default'] ?? [1, 2, 3],
            'submoduloActivo' => 'cercanos',
        ]);
    }

    /**
     * Clientes en radio de búsqueda para Clientes Cercanos.
     */
    public function getCercanosData(GetCercanosDataRequest $request, GetClientesCercanosAction $action): JsonResponse
    {
        $data = $action->execute(
            lat: $request->latitud(),
            lng: $request->longitud(),
            radio: $request->radio(),
            canales: $request->canales(),
            rutas: $request->rutas(),
            meses: $request->meses(),
            anio: $request->anio(),
            soloActivos: $request->soloActivos()
        );

        return response()->json($data);
    }

    /**
     * Resuelve URLs cortas de Google Maps a coordenadas WGS84.
     */
    public function resolverEnlaceMaps(Request $request, ResolveMapsUrlAction $action): JsonResponse
    {
        $input = (string) ($request->input('url') ?? $request->input('input', ''));
        $coords = $action->execute($input);

        return response()->json($coords);
    }

    /**
     * Historial de ventas del cliente para el panel de cercanos.
     */
    public function getVentasCliente(GetVentasClienteRequest $request, string|int $clienteId, GetVentasClienteAction $action): JsonResponse
    {
        $resultado = $action->execute(
            clienteId: $clienteId,
            anio: $request->anio(),
            meses: $request->meses()
        );

        return response()->json($resultado);
    }

    /**
     * Historial de visitas y fotos del cliente para el panel de cercanos.
     */
    public function getVisitasCliente(string|int $clienteId, GetVisitasClienteAction $action): JsonResponse
    {
        $visitas = $action->execute($clienteId);
        return response()->json($visitas);
    }

    /**
     * Alias de compatibilidad: Fronteras e Intrusos Territoriales.
     */
    public function intrusos(GetCatalogoRutasAction $action): Response
    {
        return $this->visor($action);
    }

    /**
     * Vista 2: Detección y Resolución de Clientes Duplicados.
     */
    public function duplicados(GetSaneamientoCatalogoAction $catalogoAction): Response
    {
        $catalogo = $catalogoAction->execute();

        return Inertia::render('Supervisor/Saneamiento/Duplicados', [
            'catalogo' => $catalogo,
            'submoduloActivo' => 'duplicados',
        ]);
    }

    /**
     * Vista 3: Días Desalineados y Reasignación de Días.
     */
    public function dias(GetSaneamientoCatalogoAction $catalogoAction): Response
    {
        $catalogo = $catalogoAction->execute();

        return Inertia::render('Supervisor/Saneamiento/Dias', [
            'catalogo' => $catalogo,
            'submoduloActivo' => 'dias',
        ]);
    }

    /**
     * Vista 4: Auditoría de Calidad de Datos del Padrón.
     */
    public function calidad(GetSaneamientoCatalogoAction $catalogoAction): Response
    {
        $catalogo = $catalogoAction->execute();

        return Inertia::render('Supervisor/Saneamiento/Calidad', [
            'catalogo' => $catalogo,
            'submoduloActivo' => 'calidad',
        ]);
    }

    /**
     * Vista 5: Matriz y Auditoría Global Saneamiento BD.
     */
    public function bd(GetSaneamientoCatalogoAction $catalogoAction): Response
    {
        $catalogo = $catalogoAction->execute();

        return Inertia::render('Supervisor/Saneamiento/Bd', [
            'catalogo' => $catalogo,
            'submoduloActivo' => 'bd',
        ]);
    }

    /**
     * Vista 6: Historial y Gestión de Propuestas de Saneamiento.
     */
    public function propuestas(GetSaneamientoCatalogoAction $catalogoAction): Response
    {
        $catalogo = $catalogoAction->execute();

        return Inertia::render('Supervisor/Saneamiento/Propuestas', [
            'catalogo' => $catalogo,
            'submoduloActivo' => 'propuestas',
        ]);
    }

    // ── API: Catálogo ──────────────────────────────────────────────────────────

    public function catalogo(GetSaneamientoCatalogoAction $action): JsonResponse
    {
        return response()->json($action->execute());
    }

    // ── API: Fronteras e Intrusos Multi-Ruta ───────────────────────────────────

    public function fronterasTodas(Request $request, GetSaneamientoFronterasAction $action): JsonResponse
    {
        $canal = $request->query('canal');
        return response()->json($action->execute($canal));
    }

    public function clientesActivos(Request $request, GetClientesFronterasAction $action): JsonResponse
    {
        $rutas = (array) $request->input('rutas', []);
        return response()->json($action->execute($rutas));
    }

    public function propuestasPendientesFronteras(Request $request, ListPropuestasAction $action): JsonResponse
    {
        $rutas = $request->input('rutas');
        if (is_string($rutas)) {
            $rutas = explode(',', $rutas);
        }

        $propuestas = $action->execute(
            tipoHallazgo: 'INTRUSO_FRONTERA',
            rutas: is_array($rutas) ? $rutas : null,
            estado: 'PENDIENTE'
        );

        return response()->json($propuestas);
    }

    public function crearPropuestaIntruso(Request $request, SavePropuestaAction $action): JsonResponse
    {
        $payload = $request->all();
        $payload['tipo_hallazgo'] = 'INTRUSO_FRONTERA';
        $payload['accion_propuesta'] = 'REASIGNAR_RUTA';

        $propuestaId = $action->execute($payload);

        return response()->json([
            'status' => 'success',
            'propuesta_id' => $propuestaId,
            'mensaje' => 'Propuesta registrada en estado PENDIENTE.',
        ]);
    }

    public function cambiarEstadoPropuestaIntruso(Request $request, ChangeEstadoPropuestaAction $action): JsonResponse
    {
        $propuestaId = (int) $request->input('propuesta_id', 0);
        $nuevoEstado = (string) $request->input('estado', 'APLICADO');

        $action->execute($propuestaId, $nuevoEstado);

        return response()->json([
            'status' => 'success',
            'propuesta_id' => $propuestaId,
            'nuevo_estado' => strtoupper(trim($nuevoEstado)),
        ]);
    }

    public function cambiarDiaPropuestaIntruso(Request $request, ChangeDiaPropuestaAction $action): JsonResponse
    {
        $propuestaId = (int) $request->input('propuesta_id', 0);
        $nuevoDia = (string) $request->input('dia_propuesto', '');

        $action->execute($propuestaId, $nuevoDia);

        return response()->json([
            'status' => 'success',
            'propuesta_id' => $propuestaId,
            'dia_propuesto' => strtoupper(trim($nuevoDia)),
        ]);
    }

    public function revertirPropuestaIntruso(Request $request, RevertPropuestaAction $action): JsonResponse
    {
        $propuestaId = (int) $request->input('propuesta_id', 0);
        $exito = $action->execute($propuestaId, 'INTRUSO_FRONTERA');

        return response()->json([
            'status' => $exito ? 'success' : 'not_found',
            'revertido' => $exito,
            'propuesta_id' => $propuestaId,
        ]);
    }

    public function guardarFronteraRuta(Request $request, SaveFronteraRutaAction $action): JsonResponse
    {
        $ruta = (string) $request->input('ruta', '');
        $coords = (array) $request->input('coordenadas', []);

        $id = $action->execute($ruta, $coords, 'MANUAL_SUPERVISOR');

        return response()->json([
            'status' => 'ok',
            'frontera_id' => $id,
        ]);
    }

    // ── API: Duplicados ────────────────────────────────────────────────────────

    public function auditarDuplicados(Request $request, AuditarDuplicadosAction $action): JsonResponse
    {
        $rutas = $request->input('rutas');
        $radio = (float) $request->input('radio_metros', 20.0);

        return response()->json($action->execute(
            rutas: is_array($rutas) ? $rutas : null,
            radioMetros: $radio
        ));
    }

    public function propuestasPendientesDuplicados(Request $request, ListPropuestasAction $action): JsonResponse
    {
        $rutas = $request->input('rutas');
        if (is_string($rutas)) {
            $rutas = explode(',', $rutas);
        }

        return response()->json($action->execute(
            tipoHallazgo: 'DUPLICADO',
            rutas: is_array($rutas) ? $rutas : null,
            estado: 'PENDIENTE'
        ));
    }

    public function crearPropuestaDuplicado(Request $request, SavePropuestaAction $action): JsonResponse
    {
        $payload = $request->all();
        $payload['tipo_hallazgo'] = 'DUPLICADO';

        $propuestaId = $action->execute($payload);

        return response()->json([
            'status' => 'success',
            'propuesta_id' => $propuestaId,
            'mensaje' => 'Propuesta de duplicado registrada.',
        ]);
    }

    public function cambiarEstadoPropuestaDuplicado(Request $request, ChangeEstadoPropuestaAction $action): JsonResponse
    {
        $propuestaId = (int) $request->input('propuesta_id', 0);
        $nuevoEstado = (string) $request->input('estado', 'APLICADO');

        $action->execute($propuestaId, $nuevoEstado);

        return response()->json([
            'status' => 'success',
            'propuesta_id' => $propuestaId,
            'nuevo_estado' => strtoupper(trim($nuevoEstado)),
        ]);
    }

    public function revertirPropuestaDuplicado(Request $request, RevertPropuestaAction $action): JsonResponse
    {
        $propuestaId = (int) $request->input('propuesta_id', 0);
        $exito = $action->execute($propuestaId, 'DUPLICADO');

        return response()->json([
            'status' => $exito ? 'success' : 'not_found',
            'revertido' => $exito,
            'propuesta_id' => $propuestaId,
        ]);
    }

    // ── API: Días Desalineados ─────────────────────────────────────────────────

    public function auditarDias(Request $request, AuditarDiasDesalineadosAction $action): JsonResponse
    {
        $ruta = (string) $request->query('ruta', '');
        $buffer = (float) $request->query('buffer', 35.0);

        return response()->json($action->execute($ruta, $buffer));
    }

    public function propuestasPendientesDias(Request $request, ListPropuestasAction $action): JsonResponse
    {
        $ruta = (string) $request->query('ruta', '');
        return response()->json($action->execute(
            tipoHallazgo: 'DIA_DESALINEADO',
            rutas: $ruta !== '' ? [$ruta] : null,
            estado: 'PENDIENTE'
        ));
    }

    public function cambiarDia(
        Request $request,
        SavePropuestaAction $saveAction,
        AuditarDiasDesalineadosAction $diasAction
    ): JsonResponse {
        $payload = $request->all();
        $payload['tipo_hallazgo'] = 'DIA_DESALINEADO';
        $payload['accion_propuesta'] = 'CAMBIO_DIA';

        $propuestaId = $saveAction->execute($payload);

        $ruta = (string) ($payload['ruta'] ?? '');
        $buffer = (float) ($payload['buffer_metros'] ?? 35.0);

        $distribucionActualizada = $ruta !== '' ? $diasAction->execute($ruta, $buffer) : null;

        return response()->json([
            'status' => 'success',
            'propuesta_id' => $propuestaId,
            'mensaje' => 'Cambio de día propuesto registrado.',
            'distribucion_actualizada' => $distribucionActualizada,
        ]);
    }

    public function cambiarEstadoDia(Request $request, ChangeEstadoPropuestaAction $action): JsonResponse
    {
        $propuestaId = (int) $request->input('propuesta_id', 0);
        $nuevoEstado = (string) $request->input('estado', 'APLICADO');

        $action->execute($propuestaId, $nuevoEstado);

        return response()->json([
            'status' => 'success',
            'propuesta_id' => $propuestaId,
            'nuevo_estado' => strtoupper(trim($nuevoEstado)),
        ]);
    }

    public function revertirDia(
        Request $request,
        RevertPropuestaAction $revertAction,
        AuditarDiasDesalineadosAction $diasAction
    ): JsonResponse {
        $propuestaId = (int) $request->input('propuesta_id', 0);
        $ruta = (string) $request->input('ruta', '');

        $exito = $revertAction->execute($propuestaId, 'DIA_DESALINEADO');

        $distribucionActualizada = ($ruta !== '') ? $diasAction->execute($ruta) : null;

        return response()->json([
            'status' => $exito ? 'success' : 'not_found',
            'revertido' => $exito,
            'distribucion_actualizada' => $distribucionActualizada,
        ]);
    }

    public function guardarFronteraDia(Request $request, SaveFronteraDiaAction $action): JsonResponse
    {
        $ruta = (string) $request->input('ruta', '');
        $dia = (string) $request->input('dia', '');
        $coords = (array) $request->input('coordenadas', []);

        $id = $action->execute($ruta, $dia, $coords, 'MANUAL_SUPERVISOR');

        return response()->json([
            'status' => 'success',
            'frontera_dia_id' => $id,
        ]);
    }

    // ── API: Calidad y Matriz BD ───────────────────────────────────────────────

    public function auditarCalidad(Request $request, AuditarCalidadAction $action): JsonResponse
    {
        $rutas = (array) $request->input('rutas', []);
        return response()->json($action->execute($rutas));
    }

    public function matrizBd(Request $request, AuditarSaneamientoBdAction $action): JsonResponse
    {
        $canal = $request->input('canal');
        $rutas = $request->input('rutas');
        $dia = $request->input('dia');
        $tipoError = $request->input('tipo_error');
        $busqueda = $request->input('busqueda');

        return response()->json($action->execute(
            canal: $canal ? (string) $canal : null,
            rutas: is_array($rutas) ? $rutas : null,
            dia: $dia ? (string) $dia : null,
            tipoError: $tipoError ? (string) $tipoError : null,
            busqueda: $busqueda ? (string) $busqueda : null
        ));
    }

    // ── API: Propuestas y Resumen Global ───────────────────────────────────────

    public function historialPropuestas(Request $request, ListPropuestasAction $action): JsonResponse
    {
        $tipo = $request->query('tipo');
        return response()->json($action->execute(
            tipoHallazgo: $tipo ? (string) $tipo : null,
            rutas: null,
            estado: 'TODOS',
            soloUltimasPorCliente: true
        ));
    }

    public function actualizarEstadoPropuesta(Request $request, ChangeEstadoPropuestaAction $action): JsonResponse
    {
        $propuestaId = (int) $request->input('propuesta_id', 0);
        $nuevoEstado = (string) $request->input('estado', 'APLICADO');

        $action->execute($propuestaId, $nuevoEstado);

        return response()->json([
            'status' => 'ok',
            'propuesta_id' => $propuestaId,
            'nuevo_estado' => strtoupper(trim($nuevoEstado)),
        ]);
    }

    public function actualizarEstadoMasivo(Request $request, ChangeEstadoPropuestaAction $action): JsonResponse
    {
        $ids = (array) $request->input('ids', []);
        $nuevoEstado = (string) $request->input('estado', 'APLICADO');

        $count = $action->execute($ids, $nuevoEstado);

        return response()->json([
            'status' => 'ok',
            'actualizados' => $count,
            'nuevo_estado' => strtoupper(trim($nuevoEstado)),
        ]);
    }

    // ── Clientes y Fotografías ─────────────────────────────────────────────────

    public function buscarCliente(Request $request, SearchClientesSaneamientoAction $action): JsonResponse
    {
        $q = (string) $request->query('q', '');
        $resultados = $action->execute($q, 10);

        return response()->json(['resultados' => $resultados]);
    }

    public function fotosCliente(Request $request, GetFotosClienteAction $action): JsonResponse
    {
        $clienteId = (int) ($request->input('cliente_id') ?: $request->query('cliente_id', 0));
        $fotos = $action->execute($clienteId);

        return response()->json([
            'cliente_id' => $clienteId,
            'fotos' => $fotos,
        ]);
    }
}

