<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->float('accuracy')->nullable();
            $table->float('speed')->nullable();
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->boolean('is_mock')->default(false);

            // Métricas inerciales y de movimiento
            $table->boolean('is_moving')->default(false);
            $table->unsignedInteger('step_count')->default(0);
            $table->float('motion_variance', 8, 4)->default(0);

            $table->dateTime('recorded_at');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->index(['user_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};