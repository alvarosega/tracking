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
        Schema::connection('supervisor')->create('bitacora_cambios_ruteo', function (Blueprint $table) {
            $table->id();
            $table->string('archivo_origen', 255);
            $table->bigInteger('cliente_id');
            $table->string('cliente', 255)->nullable();
            $table->string('ruta_anterior', 100)->nullable();
            $table->string('ruta_nueva', 100);
            $table->string('dia_anterior', 50)->nullable();
            $table->string('dia_nuevo', 50);
            $table->string('vendedor_anterior', 255)->nullable();
            $table->string('vendedor_nuevo', 255)->nullable();
            $table->string('tipo_cambio', 50);
            $table->string('estado', 20);
            $table->string('supervisor', 100)->nullable()->default('SUPERVISOR');
            $table->timestamp('procesado_en')->nullable()->useCurrent();

            $table->index('cliente_id', 'idx_bit_cliente');
            $table->index('estado', 'idx_bit_estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('bitacora_cambios_ruteo');
    }
};
