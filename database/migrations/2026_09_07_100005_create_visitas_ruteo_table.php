<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            
            $table->unsignedBigInteger('client_id')->nullable();
            $table->foreign('client_id')
                ->references('client_id')
                ->on('plan_ruteo')
                ->nullOnDelete();

            $table->string('route', 50)->index();
            $table->string('status', 50);
            $table->boolean('is_opportunity')->default(false);
            $table->string('opportunity_client_name')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 11, 7);
            $table->float('accuracy')->default(0.0);
            $table->string('photo_path');
            $table->text('comments')->nullable();
            $table->dateTime('visited_at');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->index(['user_id', 'visited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitas');
    }
};