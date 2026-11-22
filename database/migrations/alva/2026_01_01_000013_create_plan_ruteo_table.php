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
        Schema::connection('mysql')->create('plan_ruteo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->string('client_name', 255);
            $table->string('seller_name', 255)->nullable();
            $table->string('business_type', 255)->nullable();
            $table->string('territory', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('reference', 255)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 11, 7);
            $table->string('status', 30)->default('Activo');
            $table->string('route', 255)->nullable();
            $table->string('day', 20)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique('client_id', 'plan_ruteo_client_id_unique');
            $table->index('route', 'plan_ruteo_route_index');
            $table->index('day', 'plan_ruteo_day_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('plan_ruteo');
    }
};
