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
        Schema::connection('supervisor')->create('route_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('route', 50);
            $table->unsignedTinyInteger('day_of_week')->comment('1: Lunes, ..., 6: Sabado, 7: Domingo');
            $table->time('start_time')->default('07:20:00');
            $table->time('end_time')->default('19:00:00');
            $table->boolean('is_working_day')->default(true);
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();

            $table->unique(['route', 'day_of_week'], 'idx_route_day');
            $table->index('route', 'idx_route');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('route_schedules');
    }
};
