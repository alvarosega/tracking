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
        Schema::connection('mysql')->create('reference_clients', function (Blueprint $table) {
            $table->id('id_cliente');
            $table->string('client_name', 255);
            $table->string('address', 255)->nullable();
            $table->string('reference', 255)->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('status', 30)->default('Activo');
            $table->string('route', 255)->nullable();
            $table->string('day', 20)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->index('route', 'reference_clients_route_index');
            $table->index('day', 'reference_clients_day_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('reference_clients');
    }
};
