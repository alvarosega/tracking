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
        Schema::connection('supervisor')->create('auditoria_preventas_cambios', function (Blueprint $table) {
            $table->id();
            $table->string('archivo_origen', 255);
            $table->string('tipo_evento', 20);
            $table->string('fecha_preventa', 50)->nullable();
            $table->bigInteger('nro_preventa');
            $table->bigInteger('producto_id');
            $table->bigInteger('cliente_id')->nullable();
            $table->text('cliente')->nullable();
            $table->text('vendedor')->nullable();
            $table->bigInteger('cantidad_anterior')->nullable();
            $table->bigInteger('cantidad_nueva')->nullable();
            $table->bigInteger('diferencia_cantidad')->nullable();
            $table->decimal('monto_anterior', 16, 6)->nullable();
            $table->decimal('monto_nuevo', 16, 6)->nullable();
            $table->decimal('diferencia_monto', 16, 6)->nullable();
            $table->string('estado_autorizacion', 20)->nullable()->default('PENDIENTE');
            $table->timestamp('detectado_en')->nullable()->useCurrent();

            $table->index(['nro_preventa', 'producto_id'], 'idx_aud_prev_prod');
            $table->index('tipo_evento', 'idx_aud_prev_evento');
            $table->index('estado_autorizacion', 'idx_aud_prev_estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('auditoria_preventas_cambios');
    }
};
