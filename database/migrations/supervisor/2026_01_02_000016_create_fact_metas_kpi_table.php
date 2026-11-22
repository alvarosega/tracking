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
        Schema::connection('supervisor')->create('fact_metas_kpi', function (Blueprint $table) {
            $table->id();
            $table->string('ruta', 100);
            $table->string('mes', 10);
            $table->decimal('vol_seda', 12, 2)->default(0.00);
            $table->integer('cob_seda')->default(0);
            $table->decimal('vol_clinical', 12, 2)->default(0.00);
            $table->integer('cob_clinical')->default(0);
            $table->decimal('vol_sedal_post_wash', 12, 2)->default(0.00);
            $table->decimal('vol_dove_post_wash', 12, 2)->default(0.00);
            $table->integer('cob_dove_hair_cross_deos')->default(0);

            $table->unique(['ruta', 'mes'], 'idx_meta_kpi_ruta_mes');
            $table->index('mes', 'idx_meta_kpi_mes');
            $table->index('ruta', 'idx_meta_kpi_ruta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('fact_metas_kpi');
    }
};
