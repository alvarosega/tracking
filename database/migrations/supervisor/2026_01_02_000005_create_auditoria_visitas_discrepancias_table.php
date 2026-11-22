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
        Schema::connection('supervisor')->create('auditoria_visitas_discrepancias', function (Blueprint $table) {
            $table->id();
            $table->string('archivo_origen', 255);
            $table->bigInteger('visita_id');
            $table->string('tipo_evento', 20)->default('MODIFICADO');
            $table->string('fecha', 50)->nullable();
            $table->string('cliente', 255)->nullable();
            $table->string('empleado', 255)->nullable();
            $table->string('tipo_discrepancia', 50);
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo')->nullable();
            $table->decimal('monto_anterior', 16, 6)->nullable();
            $table->decimal('monto_nuevo', 16, 6)->nullable();
            $table->string('estado_revision', 20)->nullable()->default('REGISTRADO');
            $table->timestamp('detectado_en')->nullable()->useCurrent();

            $table->index('visita_id', 'idx_aud_vis_id');
            $table->index('tipo_evento', 'idx_aud_vis_evento');
            $table->index('tipo_discrepancia', 'idx_aud_vis_disc');
            $table->index('estado_revision', 'idx_aud_vis_estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('auditoria_visitas_discrepancias');
    }
};
