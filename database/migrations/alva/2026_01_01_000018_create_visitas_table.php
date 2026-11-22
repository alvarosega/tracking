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
        Schema::connection('mysql')->create('visitas', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('route', 50);
            $table->string('status', 50);
            $table->boolean('is_opportunity')->default(false);
            $table->string('opportunity_client_name', 255)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 11, 7);
            $table->double('accuracy')->default(0);
            $table->decimal('distance_to_client', 10, 2)->nullable();
            $table->boolean('is_mock')->default(false);
            $table->string('photo_path', 255);
            $table->text('comments')->nullable();
            $table->dateTime('visited_at');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique('uuid', 'idx_visitas_uuid');
            $table->index('client_id', 'visitas_client_id_foreign');
            $table->index(['user_id', 'visited_at'], 'visitas_user_id_visited_at_index');
            $table->index('route', 'visitas_route_index');

            $table->foreign('user_id', 'visitas_user_id_foreign')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('visitas');
    }
};
