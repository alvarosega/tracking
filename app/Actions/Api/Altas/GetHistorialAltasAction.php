<?php

declare(strict_types=1);

namespace App\Actions\Api\Altas;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GetHistorialAltasAction
{
    /**
     * Devuelve el historial de altas registradas estrictamente por el vendedor autenticado.
     *
     * @param User $user
     * @param string|null $fechaDesdeParam
     * @param string|null $fechaHastaParam
     * @param int|null $diasParam
     * @param string|null $estadoParam
     * @param string|null $searchParam
     * @return array{
     *     total: int,
     *     filtros: array<string, mixed>,
     *     data: array<int, array<string, mixed>>
     * }
     */
    public function execute(
        User $user,
        ?string $fechaDesdeParam = null,
        ?string $fechaHastaParam = null,
        ?int $diasParam = null,
        ?string $estadoParam = null,
        ?string $searchParam = null
    ): array {
        $now = Carbon::now('America/La_Paz');

        $fechaDesde = null;
        $fechaHasta = null;

        if (!empty($fechaDesdeParam)) {
            $fechaDesde = $this->parseDateTime($fechaDesdeParam, false);
        }

        if (!empty($fechaHastaParam)) {
            $fechaHasta = $this->parseDateTime($fechaHastaParam, true);
        }

        // Si no se proporcionaron fechas explícitas, usar ventana de días (30 días por defecto)
        if (!$fechaDesde && !$fechaHasta) {
            $dias = ($diasParam !== null && $diasParam > 0) ? $diasParam : 30;
            $fechaDesde = $now->copy()->subDays($dias)->startOfDay()->format('Y-m-d H:i:s');
            $fechaHasta = $now->copy()->endOfDay()->format('Y-m-d H:i:s');
        } elseif ($fechaDesde && !$fechaHasta) {
            $fechaHasta = $now->copy()->endOfDay()->format('Y-m-d H:i:s');
        } elseif (!$fechaDesde && $fechaHasta) {
            $fechaDesde = Carbon::parse($fechaHasta)->subDays(30)->startOfDay()->format('Y-m-d H:i:s');
        }

        $query = DB::connection('supervisor')->table('saneamiento_base as sb')
            ->select([
                'sb.id',
                'sb.user_id',
                'sb.cliente_id',
                'sb.tipo_registro',
                'sb.route',
                'sb.cliente',
                'sb.nombres',
                'sb.apellido_paterno',
                'sb.apellido_materno',
                'sb.tipo_negocio',
                'sb.zona',
                'sb.lista_precios',
                'sb.contacto',
                'sb.telefono',
                'sb.celular',
                'sb.direccion',
                'sb.referencia',
                'sb.documento_identidad',
                'sb.expedido',
                'sb.complemento',
                'sb.tipo_documento',
                'sb.tipo_nit',
                'sb.nombre_factura',
                'sb.nit',
                'sb.razon_social',
                'sb.latitude',
                'sb.longitude',
                'sb.accuracy',
                'sb.photo_path',
                'sb.estado_revision',
                'sb.revisado_at',
                'sb.created_at',
                'sb.updated_at',
            ])
            ->where('sb.user_id', $user->id)
            ->where('sb.tipo_registro', 'ALTA');

        if ($fechaDesde) {
            $query->where('sb.created_at', '>=', $fechaDesde);
        }

        if ($fechaHasta) {
            $query->where('sb.created_at', '<=', $fechaHasta);
        }

        if ($estadoParam && trim($estadoParam) !== '' && strtoupper(trim($estadoParam)) !== 'TODOS') {
            $query->where('sb.estado_revision', strtoupper(trim($estadoParam)));
        }

        if ($searchParam && trim($searchParam) !== '') {
            $term = '%' . trim($searchParam) . '%';
            $query->where(function ($q) use ($term): void {
                $q->where('sb.cliente', 'LIKE', $term)
                  ->orWhere('sb.nombres', 'LIKE', $term)
                  ->orWhere('sb.apellido_paterno', 'LIKE', $term)
                  ->orWhere('sb.apellido_materno', 'LIKE', $term)
                  ->orWhere('sb.documento_identidad', 'LIKE', $term)
                  ->orWhere('sb.nit', 'LIKE', $term)
                  ->orWhere('sb.direccion', 'LIKE', $term)
                  ->orWhere('sb.contacto', 'LIKE', $term);
            });
        }

        $records = $query->orderBy('sb.created_at', 'desc')->get();

        $items = $records->map(function ($row): array {
            $photoUrl = null;
            if (!empty($row->photo_path)) {
                $photoUrl = str_starts_with((string) $row->photo_path, 'http')
                    ? (string) $row->photo_path
                    : url(Storage::url((string) $row->photo_path));
            }

            $createdAtStr = (string) $row->created_at;
            $fecha = !empty($createdAtStr) ? substr($createdAtStr, 0, 10) : null;
            $hora = !empty($createdAtStr) && strlen($createdAtStr) >= 19 ? substr($createdAtStr, 11, 8) : null;

            return [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'tipo_registro' => (string) $row->tipo_registro,
                'route' => (string) ($row->route ?? ''),
                'ruta' => (string) ($row->route ?? ''),
                'cliente' => (string) ($row->cliente ?? ''),
                'nombre_comercial' => (string) ($row->cliente ?? ''),
                'nombres' => $row->nombres ? (string) $row->nombres : null,
                'apellido_paterno' => $row->apellido_paterno ? (string) $row->apellido_paterno : null,
                'apellido_materno' => $row->apellido_materno ? (string) $row->apellido_materno : null,
                'tipo_negocio' => (string) ($row->tipo_negocio ?? ''),
                'zona' => (string) ($row->zona ?? ''),
                'lista_precios' => $row->lista_precios ? (string) $row->lista_precios : null,
                'contacto' => $row->contacto ? (string) $row->contacto : null,
                'telefono' => $row->telefono ? (string) $row->telefono : null,
                'celular' => $row->celular ? (string) $row->celular : null,
                'direccion' => $row->direccion ? (string) $row->direccion : null,
                'referencia' => $row->referencia ? (string) $row->referencia : null,
                'documento_identidad' => $row->documento_identidad ? (string) $row->documento_identidad : null,
                'expedido' => $row->expedido ? (string) $row->expedido : null,
                'complemento' => $row->complemento ? (string) $row->complemento : null,
                'tipo_documento' => $row->tipo_documento ? (string) $row->tipo_documento : null,
                'tipo_nit' => $row->tipo_nit ? (string) $row->tipo_nit : null,
                'nombre_factura' => $row->nombre_factura ? (string) $row->nombre_factura : null,
                'razon_social' => $row->razon_social ? (string) $row->razon_social : ($row->nombre_factura ? (string) $row->nombre_factura : null),
                'nit' => $row->nit ? (string) $row->nit : null,
                'latitude' => $row->latitude !== null ? (float) $row->latitude : null,
                'longitude' => $row->longitude !== null ? (float) $row->longitude : null,
                'latitud' => $row->latitude !== null ? (float) $row->latitude : null,
                'longitud' => $row->longitude !== null ? (float) $row->longitude : null,
                'accuracy' => (float) ($row->accuracy ?? 0.0),
                'photo_path' => $row->photo_path ? (string) $row->photo_path : null,
                'photo_url' => $photoUrl,
                'foto_url' => $photoUrl,
                'estado_revision' => (string) ($row->estado_revision ?? 'PENDIENTE'),
                'estado' => (string) ($row->estado_revision ?? 'PENDIENTE'),
                'revisado_at' => $row->revisado_at ? (string) $row->revisado_at : null,
                'created_at' => $createdAtStr ?: null,
                'updated_at' => $row->updated_at ? (string) $row->updated_at : null,
                'fecha' => $fecha,
                'hora' => $hora,
            ];
        })->all();

        return [
            'total' => count($items),
            'filtros' => [
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
                'dias' => $diasParam ?? 30,
                'estado' => $estadoParam ?: null,
                'search' => $searchParam ?: null,
            ],
            'data' => $items,
        ];
    }

    private function parseDateTime(string $value, bool $isEnd): ?string
    {
        $value = trim($value);
        if ($value === '' || $value === 'null') {
            return null;
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return $isEnd ? "{$value} 23:59:59" : "{$value} 00:00:00";
            }

            $parsed = Carbon::parse($value, 'America/La_Paz');
            return $parsed->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
