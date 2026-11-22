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
        Schema::connection('mysql')->create('app_module_routes', function (Blueprint $table) {
            $table->id();
            $table->string('module_code', 50);
            $table->string('ruta', 50);
            $table->boolean('is_active')->default(true);
            $table->longText('config')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['module_code', 'ruta'], 'app_module_routes_code_ruta_unique');
            $table->index('ruta', 'app_module_routes_ruta_index');
            $table->index('module_code', 'app_module_routes_module_code_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('app_module_routes');
    }
};
