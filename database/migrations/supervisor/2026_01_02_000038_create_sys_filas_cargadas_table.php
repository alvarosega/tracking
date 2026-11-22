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
        Schema::connection('supervisor')->create('sys_filas_cargadas', function (Blueprint $table) {
            $table->bigInteger('id')->nullable();
            $table->string('tabla', 255)->nullable();
            $table->string('clave_hash', 255)->nullable();
            $table->string('contenido_hash', 255)->nullable();
            $table->string('cargado_en', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('sys_filas_cargadas');
    }
};
