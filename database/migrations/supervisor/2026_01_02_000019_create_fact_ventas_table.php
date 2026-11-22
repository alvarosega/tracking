<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('supervisor')->create('fact_ventas', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('venta_id');
            $table->bigInteger('producto_id');
            $table->date('fecha');
            $table->date('fecha_norm');
            $table->string('hora', 10)->nullable();
            $table->bigInteger('cliente_id');
            $table->string('cliente', 255)->nullable();
            $table->string('cliente_norm', 255)->nullable();
            $table->string('oficina', 100)->nullable();
            $table->string('vendedor', 255)->nullable();
            $table->string('vendedor_norm', 255)->nullable();
            $table->bigInteger('vendedor_id')->nullable();
            $table->string('vendedor_username', 100)->nullable();
            $table->string('distribuidor', 255)->nullable();
            $table->bigInteger('distribuidor_id')->nullable();
            $table->string('distribuidor_username', 100)->nullable();
            $table->string('zona', 100)->nullable();
            $table->string('tipo_pago', 50)->nullable();
            $table->string('codigo', 100)->nullable();
            $table->string('producto', 255)->nullable();
            $table->string('categoria', 100)->nullable();
            $table->string('dp_group', 100)->nullable();
            $table->integer('cantidad')->default(0);
            $table->decimal('cantidad_cajas', 10, 2)->nullable()->default(0.00);
            $table->decimal('precio_unitario', 16, 6)->nullable()->default(0.000000);
            $table->decimal('precio_paquete', 16, 6)->nullable()->default(0.000000);
            $table->decimal('monto', 16, 6)->default(0.000000);
            $table->decimal('descuento', 16, 6)->default(0.000000);
            $table->decimal('descuento2', 16, 6)->default(0.000000);
            $table->decimal('descuento_socio', 16, 6)->default(0.000000);
            $table->decimal('descuento_cliente', 16, 6)->default(0.000000);
            $table->decimal('monto_final', 16, 6)->default(0.000000);
            $table->decimal('precio', 16, 6)->default(0.000000);
            $table->string('proveedor', 100)->nullable();
            $table->string('tipo_negocio', 100)->nullable();
            $table->string('nro_factura', 100)->nullable();
            $table->string('cuf', 255)->nullable();
            $table->string('razon_social', 255)->nullable();
            $table->string('nit', 50)->nullable();
            $table->integer('mes')->nullable();
            $table->string('supervisor', 255)->nullable();
            $table->bigInteger('pre_venta_id')->nullable();
            $table->string('ruta', 100)->nullable();
            $table->string('dia_visita', 50)->nullable();
            $table->string('precio_lista', 50)->nullable();
            $table->string('canal', 100)->nullable();
            $table->string('tipo_objeto', 100)->nullable();
            $table->string('mercado', 100)->nullable();
            $table->string('empresa', 100)->nullable();
            $table->string('jerarquia1', 100)->nullable();
            $table->string('jerarquia2', 100)->nullable();
            $table->string('jerarquia3', 100)->nullable();
            $table->string('escala_descuento', 100)->nullable();
            $table->string('escala_producto', 100)->nullable();
            $table->string('cluster_t', 50)->nullable();
            $table->string('cluster_c', 50)->nullable();
            $table->string('descripcion_unificada', 255)->nullable();
            $table->string('marca', 100)->nullable();
            $table->string('revertida', 10)->default('No');
            $table->string('origen', 50)->nullable();

            $table->index(['venta_id', 'producto_id'], 'idx_fv_venta_prod');
            $table->index('fecha_norm', 'idx_fv_fecha_norm');
            $table->index('cliente_id', 'idx_fv_cliente');
            $table->index('ruta', 'idx_fv_ruta');
            $table->index('vendedor_id', 'idx_fv_vendedor');
            $table->index('mes', 'idx_fv_mes');
            $table->index(['fecha_norm', 'ruta', 'producto_id'], 'idx_fv_analitica');
            $table->index(['cliente_id', 'fecha', 'monto_final'], 'wdbi_cliente_id_fecha_monto_final_f8ec180bcc1e');
            $table->index(['fecha', 'monto_final'], 'wdbi_fecha_monto_final_689071ee5a6f');
            $table->index(['fecha_norm', 'revertida', 'monto_final', 'ruta', 'cliente_id', 'codigo', 'producto', 'jerarquia3', 'cantidad', 'vendedor'], 'wdbi_fecha_norm_revertida_monto_final_ruta_cliente__5c464082a5c1');
            $table->index(['fecha_norm', 'venta_id', 'producto_id', 'monto_final', 'cantidad', 'revertida', 'cliente_id'], 'wdbi_fecha_norm_venta_id_producto_id_monto_final_ca_a68f93cdc897');
            $table->index(['pre_venta_id', 'producto_id', 'revertida', 'cantidad'], 'wdbi_pre_venta_id_producto_id_revertida_cantidad_46d679f6dc33');
            $table->index('monto_final', 'wdbi_monto_final_9beda2c87786');
            $table->index(['fecha_norm', 'venta_id', 'producto_id'], 'idx_fact_ventas_natural');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('fact_ventas');
    }
};
