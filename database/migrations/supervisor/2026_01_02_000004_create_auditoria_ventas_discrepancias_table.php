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
        Schema::connection('supervisor')->create('auditoria_ventas_discrepancias', function (Blueprint $table) {
            $table->id();
            $table->string('archivo_origen', 255);
            $table->bigInteger('venta_id');
            $table->bigInteger('producto_id');
            $table->date('fecha_norm');
            $table->string('fecha', 50)->nullable();
            $table->bigInteger('cliente_id')->nullable();
            $table->decimal('monto_anterior', 16, 6)->nullable();
            $table->decimal('monto_nuevo', 16, 6)->nullable();
            $table->bigInteger('cantidad_anterior')->nullable();
            $table->bigInteger('cantidad_nueva')->nullable();
            $table->string('revertida_anterior', 10)->nullable();
            $table->string('revertida_nueva', 10)->nullable();
            $table->string('estado_revision', 20)->nullable()->default('PENDIENTE');
            $table->timestamp('detectado_en')->nullable()->useCurrent();

            $table->index(['venta_id', 'producto_id'], 'idx_aud_vta_prod');
            $table->index('estado_revision', 'idx_aud_estado');
            $table->index(['fecha_norm', 'venta_id', 'producto_id'], 'idx_aud_natural');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('auditoria_ventas_discrepancias');
    }
};
