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
            'fecha norm' => 'fecha_norm',
            'fechanorm' => 'fecha_norm',
            'nro preventa' => 'nro_preventa',
            'nropreventa' => 'nro_preventa',
            'preventaid' => 'nro_preventa',
            'preventa id' => 'nro_preventa',
            'clienteid' => 'cliente_id',
            'cliente id' => 'cliente_id',
            'codigo cliente' => 'codigo_cliente',
            'codigocliente' => 'codigo_cliente',
            'cod cliente' => 'codigo_cliente',
            'cliente' => 'cliente',
            'almacen' => 'almacen',
            'vendedor' => 'vendedor',
            'username vendedor' => 'username_vendedor',
            'usernamevendedor' => 'username_vendedor',
            'vendedor username' => 'username_vendedor',
            'zona' => 'zona',
            'productoid' => 'producto_id',
            'producto id' => 'producto_id',
            'codigo producto' => 'codigo_producto',
            'codigoproducto' => 'codigo_producto',
            'cod producto' => 'codigo_producto',
            'producto' => 'producto',
            'categoria' => 'categoria',
            'cantidad' => 'cantidad',
            'monto' => 'monto',
            'descuento' => 'descuento',
            'monto final' => 'monto_final',
            'montofinal' => 'monto_final',
            'proveedor' => 'proveedor',
            'tipo negocio' => 'tipo_negocio',
            'tiponegocio' => 'tipo_negocio',
            'mes' => 'mes',
            'ruta' => 'ruta',
            'dia visita' => 'dia_visita',
            'diavisita' => 'dia_visita',
            'precio lista' => 'precio_lista',
            'preciolista' => 'precio_lista',
            'facturar' => 'facturar',
            'nro carga' => 'nro_carga',
            'nrocarga' => 'nro_carga',
        ],
        'FACT_VISITAS' => [
            'id' => 'visita_id',
            'visita id' => 'visita_id',
            'visitaid' => 'visita_id',
            'tipo' => 'tipo',
            'motivo' => 'motivo',
            'cliente' => 'cliente',
            'empleado' => 'empleado',
            'fecha' => 'fecha',
            'fecha norm' => 'fecha_norm',
            'fechanorm' => 'fecha_norm',
            'monto' => 'monto',
            'monto final' => 'monto',
            'montofinal' => 'monto',
            'check in' => 'check_in',
            'checkin' => 'check_in',
            'check out' => 'check_out',
            'checkout' => 'check_out',
            'tiempo pdv' => 'tiempo_pdv',
            'tiempopdv' => 'tiempo_pdv',
            'zona' => 'zona',
            'tipo pago' => 'tipo_pago',
            'tipopago' => 'tipo_pago',
            'tipo negocio' => 'tipo_negocio',
            'tiponegocio' => 'tipo_negocio',
            'cluster' => 'cluster',
        ],
        'FACT_VENTAS' => [
            'fecha' => 'fecha',
            'fecha venta' => 'fecha',
            'fechaventa' => 'fecha',
            'fechanorm' => 'fecha_norm',
            'fecha norm' => 'fecha_norm',
            'hora' => 'hora',
            'hora venta' => 'hora',
            'horaventa' => 'hora',
            'clienteid' => 'cliente_id',
            'cliente id' => 'cliente_id',
            'id cliente' => 'cliente_id',
            'idcliente' => 'cliente_id',
            'codigo cliente' => 'cliente_id',
            'codigocliente' => 'cliente_id',
            'cod cliente' => 'cliente_id',
            'codcliente' => 'cliente_id',
            'cliente' => 'cliente',
            'oficina' => 'oficina',
            'vendedor' => 'vendedor',
            'vendedorid' => 'vendedor_id',
            'vendedor id' => 'vendedor_id',
            'id vendedor' => 'vendedor_id',
            'idvendedor' => 'vendedor_id',
            'cod vendedor' => 'vendedor_id',
            'codvendedor' => 'vendedor_id',
            'vendedorusername' => 'vendedor_username',
            'vendedor username' => 'vendedor_username',
            'username vendedor' => 'vendedor_username',
            'usernamevendedor' => 'vendedor_username',
            'distribuidor' => 'distribuidor',
            'distribuidorid' => 'distribuidor_id',
            'distribuidor id' => 'distribuidor_id',
            'id distribuidor' => 'distribuidor_id',
            'iddistribuidor' => 'distribuidor_id',
            'distribuidorusername' => 'distribuidor_username',
            'distribuidor username' => 'distribuidor_username',
            'username distribuidor' => 'distribuidor_username',
            'usernamedistribuidor' => 'distribuidor_username',
            'zona' => 'zona',
            'tipopago' => 'tipo_pago',
            'tipo pago' => 'tipo_pago',
            'productoid' => 'producto_id',
            'producto id' => 'producto_id',
            'id producto' => 'producto_id',
            'idproducto' => 'producto_id',
            'codigo producto' => 'producto_id',
            'codigoproducto' => 'producto_id',
            'cod producto' => 'producto_id',
            'codproducto' => 'producto_id',
            'codigo' => 'codigo',
            'producto' => 'producto',
            'categoria' => 'categoria',
            'dpgroup' => 'dp_group',
            'cantidad' => 'cantidad',
            'cantidadcajas' => 'cantidad_cajas',
            'cantidad cajas' => 'cantidad_cajas',
            'preciounitario' => 'precio_unitario',
            'precio unitario' => 'precio_unitario',
            'preciopaquete' => 'precio_paquete',
            'precio paquete' => 'precio_paquete',
            'monto' => 'monto',
            'subtotal' => 'monto',
            'descuento' => 'descuento',
            'descuento2' => 'descuento2',
            'descuentosocio' => 'descuento_socio',
            'descuento socio' => 'descuento_socio',
            'descuentocliente' => 'descuento_cliente',
            'descuento cliente' => 'descuento_cliente',
            'montofinal' => 'monto_final',
            'monto final' => 'monto_final',
            'total venta' => 'monto_final',
            'totalventa' => 'monto_final',
            'total' => 'monto_final',
            'proveedor' => 'proveedor',
            'tiponegocio' => 'tipo_negocio',
            'tipo negocio' => 'tipo_negocio',
            'nrofactura' => 'nro_factura',
            'nro factura' => 'nro_factura',
            'factura' => 'nro_factura',
            'cuf' => 'cuf',
            'razonsocial' => 'razon_social',
            'razon social' => 'razon_social',
            'nit' => 'nit',
            'mes' => 'mes',
            'supervisor' => 'supervisor',
            'ventaid' => 'venta_id',
            'venta id' => 'venta_id',
            'nro venta' => 'venta_id',
            'nroventa' => 'venta_id',
            'id venta' => 'venta_id',
            'idventa' => 'venta_id',
            'numero venta' => 'venta_id',
            'numeroventa' => 'venta_id',
            'preventaid' => 'pre_venta_id',
            'preventa id' => 'pre_venta_id',
            'pre venta id' => 'pre_venta_id',
            'nro preventa' => 'pre_venta_id',
            'nropreventa' => 'pre_venta_id',
            'id preventa' => 'pre_venta_id',
            'idpreventa' => 'pre_venta_id',
            'ruta' => 'ruta',
            'diavisita' => 'dia_visita',
            'dia visita' => 'dia_visita',
            'preciolista' => 'precio_lista',
            'precio lista' => 'precio_lista',
            'canal' => 'canal',
            'tipoobjeto' => 'tipo_objeto',
            'tipo objeto' => 'tipo_objeto',
            'mercado' => 'mercado',
            'empresa' => 'empresa',
            'jerarquia1' => 'jerarquia1',
            'jerarquia2' => 'jerarquia2',
            'jerarquia3' => 'jerarquia3',
            'descuentogrupo' => 'descuento_grupo',
            'descuento grupo' => 'descuento_grupo',
            'descuentovolumen' => 'descuento_volumen',
            'descuento volumen' => 'descuento_volumen',
            'escaladescuento' => 'escala_descuento',
            'escala descuento' => 'escala_descuento',
            'escalaproducto' => 'escala_producto',
            'escala producto' => 'escala_producto',
            'clustert' => 'cluster_t',
            'cluster t' => 'cluster_t',
            'clusterc' => 'cluster_c',
            'cluster c' => 'cluster_c',
            'descripcionunificada' => 'descripcion_unificada',
            'descripcion unificada' => 'descripcion_unificada',
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
            $tieneVentaId = isset($headersCompressed['ventaid']) || isset($headersSet['venta id'])
                || isset($headersCompressed['nroventa']) || isset($headersSet['nro venta'])
                || isset($headersCompressed['idventa']) || isset($headersSet['id venta'])
                || isset($headersCompressed['numeroventa']) || isset($headersSet['numero venta']);
            $contextCols = [
                'productoid', 'producto id', 'idproducto', 'id producto', 'codigoproducto', 'codigo producto', 'codproducto', 'cod producto',
                'ruta', 'fecha', 'fechaventa', 'fecha venta', 'montofinal', 'monto final', 'monto', 'total', 'totalventa', 'total venta',
                'clienteid', 'cliente id', 'idcliente', 'id cliente', 'codigocliente', 'codigo cliente', 'codcliente', 'cod cliente'
            ];
            $tieneContexto = false;
            foreach ($contextCols as $c) {
                if (isset($headersCompressed[str_replace(' ', '', $c)]) || isset($headersSet[$c])) {
                    $tieneContexto = true;
                    break;
                }
            }

            if (!$tieneVentaId || !$tieneContexto) {
                $sample = implode(', ', array_slice($headers, 0, 8));
                throw new Exception("El archivo no coincide con el esquema requerido para FACT_VENTAS. Se requiere columna de Venta ('ventaId' o 'nro venta') y atributos de venta (productoId, ruta, fecha, monto, etc.). Cabeceras detectadas: [{$sample}]");
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
