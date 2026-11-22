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
        Schema::connection('supervisor')->create('fact_visitas', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('visita_id')->nullable();
            $table->string('tipo', 50)->nullable();
            $table->string('motivo', 100)->nullable();
            $table->string('cliente', 255)->nullable();
            $table->string('cliente_norm', 255)->nullable();
            $table->string('empleado', 255)->nullable();
            $table->string('empleado_norm', 255)->nullable();
            $table->string('fecha', 50)->nullable();
            $table->date('fecha_norm');
            $table->decimal('monto', 16, 6)->default(0.000000);
            $table->string('check_in', 20)->nullable();
            $table->string('check_out', 20)->nullable();
            $table->string('tiempo_pdv', 20)->nullable();
            $table->string('zona', 100)->nullable();
            $table->string('tipo_pago', 50)->nullable();
            $table->string('tipo_negocio', 100)->nullable();
            $table->string('cluster', 50)->nullable();

            $table->index('fecha_norm', 'idx_fvis_fecha_norm');
            $table->index('cliente_norm', 'idx_fvis_cliente_norm');
            $table->index('empleado_norm', 'idx_fvis_empleado_norm');
            $table->index('tipo', 'idx_fvis_tipo');
            $table->index(['fecha_norm', 'empleado_norm'], 'idx_fvis_analitica');
            $table->index('visita_id', 'idx_fv_visita_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('fact_visitas');
    }
};
