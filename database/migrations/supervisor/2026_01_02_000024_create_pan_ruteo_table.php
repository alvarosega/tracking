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
        Schema::connection('supervisor')->create('pan_ruteo', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('nodo_id')->nullable();
            $table->string('nodo', 255)->nullable();
            $table->bigInteger('cliente_id');
            $table->string('cliente', 255)->nullable();
            $table->string('vendedor', 255)->nullable();
            $table->string('tipo_negocio', 100)->nullable();
            $table->string('territorio', 100)->nullable();
            $table->string('mercado', 100)->nullable();
            $table->string('fecha_servidor', 50)->nullable();
            $table->string('fecha_dispositivo', 50)->nullable();
            $table->string('contacto', 255)->nullable();
            $table->string('ci', 50)->nullable();
            $table->string('telefono', 100)->nullable();
            $table->string('celular', 100)->nullable();
            $table->text('direccion')->nullable();
            $table->string('nro', 50)->nullable();
            $table->text('referencia')->nullable();
            $table->string('nombre_factura', 255)->nullable();
            $table->string('nit', 50)->nullable();
            $table->string('tipo_pago', 50)->nullable();
            $table->double('latitud')->nullable();
            $table->double('longitud')->nullable();
            $table->string('intensidad', 50)->nullable();
            $table->string('estado', 50)->nullable();
            $table->string('zona_venta', 100)->nullable();
            $table->string('cod_vendedor', 50)->nullable();
            $table->string('dias_credito', 50)->nullable();
            $table->double('limite')->nullable();
            $table->string('ruta', 100)->nullable();
            $table->string('dia', 50)->nullable();
            $table->string('precio', 50)->nullable();
            $table->string('cluster_t', 50)->nullable();
            $table->string('cluster_c', 50)->nullable();
            $table->string('cliente_norm', 255)->nullable();
            $table->string('vendedor_norm', 255)->nullable();
            $table->string('dia_norm', 50)->nullable();

            $table->index('cliente_id', 'idx_pan_cliente');
            $table->index('ruta', 'idx_pan_ruta');
            $table->index('dia_norm', 'idx_pan_dia_norm');
            $table->index(['cliente_id', 'ruta', 'dia_norm'], 'idx_pan_busqueda');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('pan_ruteo');
    }
};
