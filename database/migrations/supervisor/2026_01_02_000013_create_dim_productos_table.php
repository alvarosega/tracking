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
        Schema::connection('supervisor')->create('dim_productos', function (Blueprint $table) {
            $table->bigInteger('producto_id')->nullable();
            $table->string('proveedor', 255)->nullable();
            $table->string('categoria', 255)->nullable();
            $table->string('marca', 255)->nullable();
            $table->string('codigo', 255)->nullable();
            $table->string('codigo_proveedor', 255)->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->string('descripcion50', 255)->nullable();
            $table->string('descripcion25', 255)->nullable();
            $table->string('descripcion_larga', 255)->nullable();
            $table->double('conversion')->nullable();
            $table->string('descripcion_unidad', 255)->nullable();
            $table->string('descripcion_unidad50', 255)->nullable();
            $table->string('descripcion_unidad25', 255)->nullable();
            $table->string('descripcion_unificada', 255)->nullable();
            $table->string('presentacion', 255)->nullable();
            $table->string('unidad_manejo', 255)->nullable();
            $table->string('codigo_barra', 255)->nullable();
            $table->string('ucc14', 255)->nullable();
            $table->string('organizacion', 255)->nullable();
            $table->string('moneda', 255)->nullable();
            $table->string('unidad_vendible', 255)->nullable();
            $table->string('unidad_vendible_paquete', 255)->nullable();
            $table->string('dimension_unidad', 255)->nullable();
            $table->string('dimension_paquete', 255)->nullable();
            $table->double('peso')->nullable();
            $table->double('alto')->nullable();
            $table->double('largo')->nullable();
            $table->double('ancho')->nullable();
            $table->string('producto_promocion', 255)->nullable();
            $table->string('descarga_movil', 255)->nullable();
            $table->string('codigo_sin', 255)->nullable();
            $table->string('actividad_economica', 255)->nullable();
            $table->string('tipo_almacen', 255)->nullable();
            $table->string('salvage', 255)->nullable();
            $table->string('activo', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('dim_productos');
    }
};
