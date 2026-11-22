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
        Schema::connection('mysql')->create('password_reset_tokens', function (Blueprint $table) {
            $table->string('username', 255);
            $table->string('token', 255);
            $table->dateTime('created_at')->nullable();

            $table->primary('username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('password_reset_tokens');
    }
};
