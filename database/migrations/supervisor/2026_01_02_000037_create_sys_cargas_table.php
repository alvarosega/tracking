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
        Schema::connection('supervisor')->create('sys_cargas', function (Blueprint $table) {
            $table->id();
            $table->string('archivo_original', 255)->nullable();
            $table->string('archivo_almacenado', 255)->nullable();
            $table->string('sha256', 255)->nullable();
            $table->string('tipo_entidad', 255)->nullable();
            $table->string('estado', 20)->default('PROCESANDO');
            $table->bigInteger('filas_leidas')->nullable();
            $table->bigInteger('filas_afectadas')->nullable();
            $table->bigInteger('filas_nuevas')->nullable();
            $table->bigInteger('filas_actualizadas')->nullable();
            $table->bigInteger('filas_ignoradas')->nullable();
            $table->bigInteger('filas_con_discrepancia')->nullable();
            $table->string('rango_inicio', 255)->nullable();
            $table->string('rango_fin', 255)->nullable();
            $table->string('fecha_ingesta', 50)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('sys_cargas');
    }
};
