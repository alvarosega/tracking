<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitas_ruteo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->enum('status', ['PREVENTA', 'SIN_DINERO', 'TIENDA_CERRADA', 'AUSENTE']);
            $table->boolean('is_new_client')->default(false);
            $table->string('new_client_name')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 11, 7);
            $table->float('accuracy')->default(0.0);
            $table->float('distance_to_target')->nullable(); // Distancia calculada en backend en metros
            $table->string('photo_path');
            $table->text('comments')->nullable();
            $table->timestamp('visited_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitas_ruteo');
    }
};