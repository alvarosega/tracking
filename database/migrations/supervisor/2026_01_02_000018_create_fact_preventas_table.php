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
        Schema::connection('supervisor')->create('fact_preventas', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->date('fecha_norm');
            $table->bigInteger('nro_preventa');
            $table->bigInteger('cliente_id');
            $table->string('codigo_cliente', 50)->nullable();
            $table->string('cliente', 255)->nullable();
            $table->string('almacen', 100)->nullable();
            $table->string('vendedor', 255)->nullable();
            $table->string('username_vendedor', 100)->nullable();
            $table->string('zona', 100)->nullable();
            $table->bigInteger('producto_id');
            $table->string('codigo_producto', 100)->nullable();
            $table->string('producto', 255)->nullable();
            $table->string('categoria', 100)->nullable();
            $table->integer('cantidad')->default(0);
            $table->decimal('monto', 16, 6)->default(0.000000);
            $table->decimal('descuento', 16, 6)->default(0.000000);
            $table->decimal('monto_final', 16, 6)->default(0.000000);
            $table->string('proveedor', 100)->nullable();
            $table->string('tipo_negocio', 100)->nullable();
            $table->integer('mes')->nullable();
            $table->string('ruta', 100)->nullable();
            $table->string('dia_visita', 50)->nullable();
            $table->string('precio_lista', 50)->nullable();
            $table->string('facturar', 50)->nullable();
            $table->string('nro_carga', 50)->nullable();

            $table->index(['nro_preventa', 'producto_id'], 'idx_fp_prev_prod');
            $table->index('fecha_norm', 'idx_fp_fecha_norm');
            $table->index('cliente_id', 'idx_fp_cliente');
            $table->index('ruta', 'idx_fp_ruta');
            $table->index(['fecha_norm', 'ruta', 'producto_id'], 'idx_fp_analitica');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('fact_preventas');
    }
};
