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
        Schema::connection('supervisor')->create('auditoria_saneamiento_propuestas', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_hallazgo', 50);
            $table->bigInteger('cliente_id_origen');
            $table->bigInteger('cliente_id_vinculado')->nullable();
            $table->string('cliente_nombre', 255)->nullable();
            $table->string('ruta_actual', 100);
            $table->string('dia_actual', 50);
            $table->string('ruta_propuesta', 100)->nullable();
            $table->string('dia_propuesto', 50)->nullable();
            $table->string('accion_propuesta', 50);
            $table->double('distancia_metros')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('estado', 20)->nullable()->default('PENDIENTE');
            $table->timestamp('creado_en')->nullable()->useCurrent();

            $table->index('tipo_hallazgo', 'idx_san_tipo');
            $table->index('cliente_id_origen', 'idx_san_cli');
            $table->index('estado', 'idx_san_estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('auditoria_saneamiento_propuestas');
    }
};
