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
        Schema::connection('supervisor')->create('feriados_bolivia', function (Blueprint $table) {
            $table->string('date', 255)->nullable();
            $table->string('type', 255)->nullable();
            $table->string('description', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('feriados_bolivia');
    }
};
