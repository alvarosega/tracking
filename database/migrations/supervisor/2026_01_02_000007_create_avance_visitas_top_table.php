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
        Schema::connection('supervisor')->create('avance_visitas_top', function (Blueprint $table) {
            $table->id();
            $table->string('ruta', 100);
            $table->bigInteger('cliente_id');
            $table->string('cliente', 255)->nullable();
            $table->text('direccion')->nullable();
            $table->string('dia', 50);
            $table->integer('orden_visita')->default(1);
            $table->string('frecuencia', 50)->nullable()->default('SEMANAL');
            $table->string('prioridad', 20)->nullable()->default('TOP');
            $table->text('notas')->nullable();
            $table->string('creado_por', 100)->nullable()->default('SUPERVISOR');
            $table->timestamp('creado_en')->nullable()->useCurrent();
            $table->timestamp('actualizado_en')->nullable()->useCurrent()->useCurrentOnUpdate();

            $table->unique(['ruta', 'cliente_id', 'dia'], 'uq_vtop_ruta_cliente_dia');
            $table->index('ruta', 'idx_vtop_ruta');
            $table->index('cliente_id', 'idx_vtop_cliente');
            $table->index('dia', 'idx_vtop_dia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('avance_visitas_top');
    }
};
