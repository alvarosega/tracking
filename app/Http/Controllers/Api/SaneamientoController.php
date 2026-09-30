<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SaneamientoController extends Controller
{
    private const TIPOS_NEGOCIO = [
        'TDB A',
        'TDB B',
        'TDB C',
        'MINORISTA',
        'CASETA',
        'FERIA',
        'FRIAL O CARNICERIA',
        'ABARROTES',
        'MAYORISTA',
        'SUPERMERCADO',
        'MICROMERCADO',
        'ALMACEN',
        'ALMACEN DE LIMPIEZA',
        'FARMACIA ZONAL',
        'CADENA DE FARMACIAS',
        'HOTEL, MOTEL, ALOJAMIENTO',
        'HOSPITAL, POSTA MEDICA',
        'UNIVERSIDAD O COLEGIO',
        'EMPRESAS, BANCOS, COOPERATIVAS, SINDICATOS',
        'RESTAURANTE, PENSIÓN O CAFETERIA',
        'PELUQUERIA',
        'LAVANDERIA',
        'LICORERIA',
        'ESTACION DE SERVICIO',
        'BALNEARIO O PISCINA',
        'VENTA OFICINA',
        'FRIAL Y CARNICERIA - TDB',
        'FRIAL Y CARNICERIA - MZO',
        'CASETA - TDB',
        'CASETA - MZO',
        'PROVINCIA',
        'Prov. MAY',
        'Prov MZO',
    ];

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autorizado. Se requiere token de sesión válido.'
            ], 401);
        }

        $tipoRegistro = strtoupper(trim($request->input('tipo_registro', '')));
        $esAlta = ($tipoRegistro === 'ALTA');

        $validated = $request->validate([
            'tipo_registro' => ['required', Rule::in(['ALTA', 'EDICION'])],
            'route' => ['required', 'string', 'max:50'],
            'cliente' => ['required', 'string', 'max:255'],
            'tipo_negocio' => ['required', 'string', Rule::in(self::TIPOS_NEGOCIO)],
            'cliente_id' => [
                Rule::requiredIf(!$esAlta),
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($esAlta) {
                    if (!$esAlta && $value) {
                        $exists = DB::connection('supervisor')
                            ->table('pan_ruteo')
                            ->where('cliente_id', $value)
                            ->exists();

                        if (!$exists) {
                            $fail("El cliente_id {$value} no existe en el plan de ruteo maestro.");
                        }
                    }
                },
            ],
            'contacto' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'celular' => ['nullable', 'string', 'max:50'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'nombre_factura' => ['nullable', 'string', 'max:255'],
            'nit' => ['nullable', 'string', 'max:50'],
            'latitude' => [
                Rule::requiredIf($esAlta),
                'nullable',
                'numeric',
                'between:-90,90'
            ],
            'longitude' => [
                Rule::requiredIf($esAlta),
                'nullable',
                'numeric',
                'between:-180,180'
            ],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'photo' => [
                Rule::requiredIf($esAlta),
                'nullable',
                'image',
                'max:10240'
            ],
            'created_at' => ['nullable', 'date_format:Y-m-d H:i:s'],
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('saneamiento', 'public');
        }

        $nowBolivia = Carbon::now('America/La_Paz')->format('Y-m-d H:i:s');
        $fechaCreacion = $validated['created_at'] ?? $nowBolivia;

        $insertData = [
            'cliente_id' => $esAlta ? null : (int) $validated['cliente_id'],
            'tipo_registro' => $tipoRegistro,
            'route' => trim($validated['route']),
            'cliente' => trim($validated['cliente']),
            'tipo_negocio' => trim($validated['tipo_negocio']),
            'contacto' => !empty($validated['contacto']) ? trim($validated['contacto']) : null,
            'telefono' => !empty($validated['telefono']) ? trim($validated['telefono']) : null,
            'celular' => !empty($validated['celular']) ? trim($validated['celular']) : null,
            'direccion' => !empty($validated['direccion']) ? trim($validated['direccion']) : null,
            'referencia' => !empty($validated['referencia']) ? trim($validated['referencia']) : null,
            'nombre_factura' => !empty($validated['nombre_factura']) ? trim($validated['nombre_factura']) : null,
            'nit' => !empty($validated['nit']) ? trim($validated['nit']) : null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'accuracy' => $validated['accuracy'] ?? null,
            'photo_path' => $photoPath,
            'created_at' => $fechaCreacion,
            'updated_at' => $nowBolivia,
        ];

        // Inserción directa en u967339252_supervisor.saneamiento_base
        $saneamientoId = DB::connection('supervisor')
            ->table('saneamiento_base')
            ->insertGetId($insertData);

        return response()->json([
            'message' => 'Registro de saneamiento procesado correctamente.',
            'id' => $saneamientoId,
            'tipo_registro' => $tipoRegistro,
            'photo_url' => $photoPath ? Storage::url($photoPath) : null,
        ], 201);
    }
}