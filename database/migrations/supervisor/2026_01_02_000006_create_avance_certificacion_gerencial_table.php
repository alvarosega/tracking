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
        Schema::connection('supervisor')->create('avance_certificacion_gerencial', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('cliente_id');
            $table->string('ruta', 100);
            $table->string('dia', 50);
            $table->integer('orden_visita')->default(1);
            $table->string('visibilidad_pop', 50)->default('Excelente');
            $table->string('flujo_peatonal', 50)->default('Calle Comercial');
            $table->string('relacion_cliente', 50)->default('Aliado');
            $table->string('alerta_contrabando', 50)->default('Libre de contrabando');
            $table->string('nombre_contacto', 255)->nullable()->default('');
            $table->string('telefono_contacto', 50)->nullable()->default('');
            $table->boolean('apto_gerencia')->default(true);
            $table->string('motivo_descarte', 255)->nullable()->default('');
            $table->date('fecha_validacion');
            $table->text('notas_supervisor')->nullable();
            $table->timestamp('creado_en')->nullable()->useCurrent();
            $table->timestamp('actualizado_en')->nullable()->useCurrent()->useCurrentOnUpdate();

            $table->unique(['cliente_id', 'ruta', 'dia'], 'uk_cliente_ruta_dia');
            $table->index(['ruta', 'dia'], 'idx_ruta_dia');
            $table->index('cliente_id', 'idx_cliente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('avance_certificacion_gerencial');
    }
};
