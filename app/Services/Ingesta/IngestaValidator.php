<?php

declare(strict_types=1);

namespace App\Services\Ingesta;

use Exception;

class IngestaValidator
{
    /**
     * Esquemas mínimos reconocibles por cada entidad.
     *
     * @var array<string, array<int, string>>
     */
    public const SCHEMAS_REQUERIDOS = [
        'FACT_PREVENTAS' => ['fecha', 'nro preventa', 'clienteid', 'productoid', 'monto final'],
        'FACT_VISITAS' => ['id', 'tipo', 'cliente', 'empleado', 'fecha'],
        'PAN_RUTEO' => ['cliente id', 'cliente', 'ruta', 'dia'],
        'FACT_VENTAS' => ['ventaid'],
        'DIM_CLIENTES' => ['cliente id', 'cliente', 'zona venta'],
        'DIM_RUTAS' => ['ruta', 'canal', 'vendedor'],
        'DIM_PRODUCTOS' => ['idproducto', 'descripcion'],
        'FACT_METAS' => ['vendedorid', 'objetivo de venta', 'mes'],
    ];

    /**
     * Mapeo canónico de columnas de archivo a columnas de la base de datos MySQL.
     *
     * @var array<string, array<string, string>>
     */
    public const COLUMN_MAPPINGS = [
        'PAN_RUTEO' => [
            'nodo id' => 'nodo_id',
            'nodo' => 'nodo',
            'cliente id' => 'cliente_id',
            'cliente' => 'cliente',
            'vendedor' => 'vendedor',
            'tipo negocio' => 'tipo_negocio',
            'territorio' => 'territorio',
            'mercado' => 'mercado',
            'fecha servidor' => 'fecha_servidor',
            'fecha dispositivo' => 'fecha_dispositivo',
            'contacto' => 'contacto',
            'ci' => 'ci',
            'telefono' => 'telefono',
            'celular' => 'celular',
            'direccion' => 'direccion',
            'nro' => 'nro',
            'referencia' => 'referencia',
            'nombre factura' => 'nombre_factura',
            'nit' => 'nit',
            'tipo pago' => 'tipo_pago',
            'latitud' => 'latitud',
            'longitud' => 'longitud',
            'intensidad' => 'intensidad',
            'estado' => 'estado',
            'zona venta' => 'zona_venta',
            'cod vendedor' => 'cod_vendedor',
            'dias credito' => 'dias_credito',
            'limite' => 'limite',
            'ruta' => 'ruta',
            'dia' => 'dia',
            'precio' => 'precio',
            'clustert' => 'cluster_t',
            'clusterc' => 'cluster_c',
            'dia norm' => 'dia_norm',
            'cliente norm' => 'cliente_norm',
            'vendedor norm' => 'vendedor_norm',
        ],
        'FACT_PREVENTAS' => [
            'fecha' => 'fecha',
            'nro preventa' => 'nro_preventa',
            'clienteid' => 'cliente_id',
            'codigo cliente' => 'codigo_cliente',
            'cliente' => 'cliente',
            'almacen' => 'almacen',
            'vendedor' => 'vendedor',
            'username vendedor' => 'username_vendedor',
            'zona' => 'zona',
            'productoid' => 'producto_id',
            'codigo producto' => 'codigo_producto',
            'producto' => 'producto',
            'categoria' => 'categoria',
            'cantidad' => 'cantidad',
            'monto' => 'monto',
            'descuento' => 'descuento',
            'monto final' => 'monto_final',
            'proveedor' => 'proveedor',
            'tipo negocio' => 'tipo_negocio',
            'mes' => 'mes',
            'ruta' => 'ruta',
            'dia visita' => 'dia_visita',
            'precio lista' => 'precio_lista',
            'facturar' => 'facturar',
            'nro carga' => 'nro_carga',
        ],
        'FACT_VISITAS' => [
            'id' => 'id',
            'tipo' => 'tipo',
            'motivo' => 'motivo',
            'cliente' => 'cliente',
            'empleado' => 'empleado',
            'fecha' => 'fecha',
            'monto' => 'monto',
            'checkin' => 'check_in',
            'checkout' => 'check_out',
            'tiempopdv' => 'tiempo_pdv',
            'zona' => 'zona',
            'tipo pago' => 'tipo_pago',
            'tipo negocio' => 'tipo_negocio',
            'cluster' => 'cluster',
        ],
        'FACT_VENTAS' => [
            'fecha' => 'fecha',
            'hora' => 'hora',
            'clienteid' => 'cliente_id',
            'cliente' => 'cliente',
            'oficina' => 'oficina',
            'vendedor' => 'vendedor',
            'vendedorid' => 'vendedor_id',
            'vendedorusername' => 'vendedor_username',
            'distribuidor' => 'distribuidor',
            'distribuidorid' => 'distribuidor_id',
            'distribuidorusername' => 'distribuidor_username',
            'zona' => 'zona',
            'tipopago' => 'tipo_pago',
            'productoid' => 'producto_id',
            'codigo' => 'codigo',
            'producto' => 'producto',
            'categoria' => 'categoria',
            'dpgroup' => 'dp_group',
            'cantidad' => 'cantidad',
            'cantidadcajas' => 'cantidad_cajas',
            'preciounitario' => 'precio_unitario',
            'preciopaquete' => 'precio_paquete',
            'monto' => 'monto',
            'descuento' => 'descuento',
            'descuento2' => 'descuento2',
            'descuentosocio' => 'descuento_socio',
            'descuentocliente' => 'descuento_cliente',
            'montofinal' => 'monto_final',
            'proveedor' => 'proveedor',
            'tiponegocio' => 'tipo_negocio',
            'nrofactura' => 'nro_factura',
            'cuf' => 'cuf',
            'razonsocial' => 'razon_social',
            'nit' => 'nit',
            'mes' => 'mes',
            'supervisor' => 'supervisor',
            'ventaid' => 'venta_id',
            'preventaid' => 'pre_venta_id',
            'ruta' => 'ruta',
            'diavisita' => 'dia_visita',
            'preciolista' => 'precio_lista',
            'canal' => 'canal',
            'tipoobjeto' => 'tipo_objeto',
            'mercado' => 'mercado',
            'empresa' => 'empresa',
            'jerarquia1' => 'jerarquia1',
            'jerarquia2' => 'jerarquia2',
            'jerarquia3' => 'jerarquia3',
            'descuentogrupo' => 'descuento_grupo',
            'descuentovolumen' => 'descuento_volumen',
            'escaladescuento' => 'escala_descuento',
            'escalaproducto' => 'escala_producto',
            'clustert' => 'cluster_t',
            'clusterc' => 'cluster_c',
            'descripcionunificada' => 'descripcion_unificada',
            'marca' => 'marca',
            'revertida' => 'revertida',
            'origen' => 'origen',
        ],
    ];

    /**
     * Valida si las cabeceras coinciden con el esquema de la entidad esperada.
     *
     * @param array<int, string> $headers
     * @throws Exception
     */
    public function validateSchema(array $headers, string $expectedEntity): void
    {
        if (empty($headers)) {
            throw new Exception("El archivo está completamente vacío o no contiene cabeceras en la fila 1.");
        }

        $headersSet = [];
        $headersCompressed = [];

        foreach ($headers as $h) {
            $hClean = strtolower(trim(str_replace(['_', "\xEF\xBB\xBF"], [' ', ''], (string)$h)));
            $headersSet[$hClean] = true;
            $hComp = str_replace(' ', '', $hClean);
            $headersCompressed[$hComp] = true;
        }

        $req = self::SCHEMAS_REQUERIDOS[$expectedEntity] ?? null;
        if (!$req) {
            throw new Exception("Entidad no reconocida para validación: '{$expectedEntity}'.");
        }

        if ($expectedEntity === 'FACT_VENTAS') {
            $tieneVentaId = isset($headersCompressed['ventaid']) || isset($headersSet['venta id']);
            $contextCols = ['productoid', 'producto id', 'ruta', 'fecha', 'montofinal', 'monto final', 'monto', 'clienteid', 'cliente id'];
            $tieneContexto = false;
            foreach ($contextCols as $c) {
                if (isset($headersCompressed[str_replace(' ', '', $c)]) || isset($headersSet[$c])) {
                    $tieneContexto = true;
                    break;
                }
            }

            if (!$tieneVentaId || !$tieneContexto) {
                $sample = implode(', ', array_slice($headers, 0, 8));
                throw new Exception("El archivo no coincide con el esquema requerido para FACT_VENTAS. Se requiere 'ventaId' y atributos de venta (productoId, ruta, fecha, monto). Cabeceras detectadas: [{$sample}]");
            }
            return;
        }

        $faltantes = [];
        foreach ($req as $col) {
            $colComp = str_replace(' ', '', $col);
            if (!isset($headersSet[$col]) && !isset($headersCompressed[$colComp])) {
                $faltantes[] = $col;
            }
        }

        if (!empty($faltantes)) {
            $faltantesStr = implode(', ', $faltantes);
            $sample = implode(', ', array_slice($headers, 0, 8));
            throw new Exception("El archivo no coincide con el esquema requerido para {$expectedEntity}. Columnas faltantes: [{$faltantesStr}]. Cabeceras detectadas: [{$sample}]");
        }
    }

    /**
     * Mapea un array asociativo proveniente del parser al nombre estándar de columnas en MySQL.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function mapRow(array $row, string $entity): array
    {
        $mapping = self::COLUMN_MAPPINGS[$entity] ?? [];
        $mapped = [];

        foreach ($row as $key => $val) {
            $cleanKey = strtolower(trim(str_replace(['_', "\xEF\xBB\xBF"], [' ', ''], (string)$key)));
            $compKey = str_replace(' ', '', $cleanKey);

            $targetCol = $mapping[$cleanKey] ?? ($mapping[$compKey] ?? null);

            if (!$targetCol) {
                // Si la clave ya tiene formato snake_case directo (ej: cliente_id, monto_final)
                $snakeKey = strtolower(trim((string)$key));
                $targetCol = $snakeKey;
            }

            $mapped[$targetCol] = $val;
        }

        return $mapped;
    }

    /**
     * Sanitiza el nombre del día a minúsculas canónicas ('lunes', 'martes', etc.).
     */
    public static function sanitizeDia(mixed $val): string
    {
        if ($val === null) {
            return '';
        }

        $dia = mb_strtolower(trim((string)$val), 'UTF-8');
        $dia = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $dia);

        $diasValidos = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
        foreach ($diasValidos as $d) {
            if (str_starts_with($dia, substr($d, 0, 3))) {
                return $d;
            }
        }

        return $dia;
    }
}
