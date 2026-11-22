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
        Schema::connection('supervisor')->create('ref_clientes_no_atendidos', function (Blueprint $table) {
            $table->bigInteger('id')->nullable();
            $table->string('comprador', 255)->nullable();
            $table->string('codigo_nit', 255)->nullable();
            $table->string('lista_precios', 255)->nullable();
            $table->string('perfil_impositivo', 255)->nullable();
            $table->string('tipo_cobranza', 255)->nullable();
            $table->string('prestamo_devolucion', 255)->nullable();
            $table->string('cod_rechazo', 255)->nullable();
            $table->string('localidad', 255)->nullable();
            $table->string('departamento', 255)->nullable();
            $table->string('subcmkt', 255)->nullable();
            $table->string('ramo', 255)->nullable();
            $table->string('ruta', 255)->nullable();
            $table->double('latitud')->nullable();
            $table->double('longitud')->nullable();
            $table->string('estado_gestion', 255)->nullable();
            $table->string('fecha_registro', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('ref_clientes_no_atendidos');
    }
};
