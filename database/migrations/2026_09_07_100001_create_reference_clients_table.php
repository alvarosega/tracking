<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_clients', function (Blueprint $table) {
            $table->unsignedBigInteger('id_cliente')->primary();
            $table->string('ruta', 50)->index();
            $table->string('dia', 20)->index();
            $table->text('direccion');
            $table->decimal('latitud', 10, 8)->nullable();
            $table->decimal('longitud', 11, 8)->nullable();
            $table->boolean('is_audited')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_clients');
    }
};