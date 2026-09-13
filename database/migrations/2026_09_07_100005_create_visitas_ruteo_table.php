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
            $table->string('route');
            $table->string('status');
            $table->boolean('is_opportunity')->default(false);
            $table->string('opportunity_client_name')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 11, 7);
            $table->float('accuracy');
            $table->string('photo_path');
            $table->text('comments')->nullable();
            $table->dateTime('visited_at');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitas');
    }
};