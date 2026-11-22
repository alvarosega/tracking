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
        Schema::connection('supervisor')->create('dim_clientes', function (Blueprint $table) {
            $table->bigInteger('nodo_id')->nullable();
            $table->string('nodo', 255)->nullable();
            $table->bigInteger('cliente_id')->nullable();
            $table->text('cliente')->nullable();
            $table->text('vendedor')->nullable();
            $table->string('tipo_negocio', 255)->nullable();
            $table->string('territorio', 255)->nullable();
            $table->string('mercado', 255)->nullable();
            $table->string('fecha_servidor', 255)->nullable();
            $table->string('fecha_dispositivo', 255)->nullable();
            $table->string('contacto', 255)->nullable();
            $table->string('ci', 255)->nullable();
            $table->string('telefono', 255)->nullable();
            $table->string('celular', 255)->nullable();
            $table->text('direccion')->nullable();
            $table->string('nro', 255)->nullable();
            $table->text('referencia')->nullable();
            $table->string('nombre_factura', 255)->nullable();
            $table->string('nit', 255)->nullable();
            $table->string('tipo_pago', 255)->nullable();
            $table->double('latitud')->nullable();
            $table->double('longitud')->nullable();
            $table->string('intensidad', 255)->nullable();
            $table->string('estado', 255)->nullable();
            $table->string('zona_venta', 255)->nullable();
            $table->string('ruta_venta', 255)->nullable();
            $table->bigInteger('cod_vendedor')->nullable();
            $table->bigInteger('dias_credito')->nullable();
            $table->double('limite')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('dim_clientes');
    }
};
