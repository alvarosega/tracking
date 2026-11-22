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
        Schema::connection('mysql')->create('cache', function (Blueprint $table) {
            $table->string('key', 255);
            $table->mediumText('value');
            $table->bigInteger('expiration');

            $table->primary('key');

            $table->index('expiration', 'cache_expiration_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('cache');
    }
};
