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
        Schema::connection('mysql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->string('username', 255)->nullable();
            $table->string('ruta', 255)->nullable();
            $table->string('password', 255);
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique('username', 'users_username_unique');
            $table->index('ruta', 'users_ruta_index');

            $table->foreign('role_id', 'users_role_id_foreign')->references('id')->on('roles');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('users');
    }
};
