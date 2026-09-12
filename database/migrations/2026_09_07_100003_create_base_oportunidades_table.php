<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('base_oportunidades', function (Blueprint $table) {
            $table->id();
            $table->string('client_id')->index();
            $table->string('trade_name')->nullable();
            $table->string('client_name')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('territory')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 11, 7);
            $table->string('status', 20)->default('1');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('base_oportunidades');
    }
};