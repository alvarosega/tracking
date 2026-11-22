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
        Schema::connection('mysql')->create('locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->double('accuracy')->nullable();
            $table->double('speed')->nullable();
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->boolean('is_mock')->default(false);
            $table->boolean('is_moving')->default(false);
            $table->unsignedInteger('step_count')->default(0);
            $table->float('motion_variance')->default(0);
            $table->dateTime('recorded_at');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->index(['user_id', 'recorded_at'], 'locations_user_id_recorded_at_index');

            $table->foreign('user_id', 'locations_user_id_foreign')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('locations');
    }
};
