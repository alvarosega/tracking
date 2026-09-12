<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_ruteo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->index();
            $table->string('client_name');
            $table->string('seller_name')->nullable();
            $table->string('business_type')->nullable();
            $table->string('territory')->nullable();
            $table->string('address')->nullable();
            $table->string('reference')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 11, 7);
            $table->string('status', 30)->default('Activo');
            $table->string('route')->index();      // Ej: TDB 6A
            $table->string('day', 20)->index();    // Ej: Jueves
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_ruteo');
    }
};