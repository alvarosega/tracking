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
        Schema::connection('supervisor')->create('pan_ruteo_cronologico', function (Blueprint $table) {
            $table->id();
            $table->string('ruta', 100);
            $table->string('dia_norm', 50);
            $table->bigInteger('cliente_id');
            $table->integer('orden_visita');
            $table->timestamp('actualizado_en')->nullable()->useCurrent()->useCurrentOnUpdate();

            $table->unique(['ruta', 'dia_norm', 'cliente_id'], 'uq_ruta_dia_cliente');
            $table->index(['ruta', 'dia_norm', 'orden_visita'], 'idx_cronologico_consulta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('pan_ruteo_cronologico');
    }
};
