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
        Schema::connection('supervisor')->create('dim_rutas', function (Blueprint $table) {
            $table->string('ruta', 255)->nullable();
            $table->string('canal', 255)->nullable();
            $table->text('vendedor')->nullable();
            $table->string('vendedor_norm', 255)->nullable();

            $table->index('ruta', 'idx_dr_ruta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('dim_rutas');
    }
};
