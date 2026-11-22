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
        Schema::connection('supervisor')->create('plan_ruteo_propuesta', function (Blueprint $table) {
            $table->bigInteger('id')->nullable();
            $table->bigInteger('cliente_id')->nullable();
            $table->bigInteger('codigo_cliente')->nullable();
            $table->text('cliente')->nullable();
            $table->string('cliente_norm', 255)->nullable();
            $table->string('canal', 255)->nullable();
            $table->string('territorio', 255)->nullable();
            $table->string('zona_venta', 255)->nullable();
            $table->text('direccion')->nullable();
            $table->text('referencia')->nullable();
            $table->double('latitud')->nullable();
            $table->double('longitud')->nullable();
            $table->string('ruta_origen', 255)->nullable();
            $table->string('dia_origen', 255)->nullable();
            $table->string('vendedor_origen', 255)->nullable();
            $table->string('ruta_propuesta', 255)->nullable();
            $table->string('dia_propuesta', 255)->nullable();
            $table->string('vendedor_propuesta', 255)->nullable();
            $table->string('estado_asignacion', 255)->nullable();
            $table->string('anomalia_detectada', 255)->nullable();
            $table->double('venta_ly')->nullable();
            $table->double('venta_lm')->nullable();
            $table->double('potencial_estimado')->nullable();
            $table->double('venta_ly_prom')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('plan_ruteo_propuesta');
    }
};
