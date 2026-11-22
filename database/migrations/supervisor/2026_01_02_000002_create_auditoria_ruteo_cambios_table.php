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
        Schema::connection('supervisor')->create('auditoria_ruteo_cambios', function (Blueprint $table) {
            $table->id();
            $table->string('archivo_origen', 255);
            $table->bigInteger('cliente_id');
            $table->string('cliente', 255)->nullable();
            $table->string('tipo_evento', 30);
            $table->string('campo_afectado', 100)->nullable();
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo')->nullable();
            $table->timestamp('fecha_auditoria')->nullable()->useCurrent();
            $table->string('estado_auditoria', 30)->default('PENDIENTE');
            $table->text('observaciones')->nullable();
            $table->string('auditado_por', 100)->nullable();
            $table->timestamp('fecha_resolucion')->nullable();

            $table->index('cliente_id', 'idx_aud_ruteo_cliente');
            $table->index('tipo_evento', 'idx_aud_ruteo_evento');
            $table->index('archivo_origen', 'idx_aud_ruteo_archivo');
            $table->index('estado_auditoria', 'idx_aud_ruteo_estado');
            $table->index('fecha_auditoria', 'idx_aud_ruteo_fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('auditoria_ruteo_cambios');
    }
};
