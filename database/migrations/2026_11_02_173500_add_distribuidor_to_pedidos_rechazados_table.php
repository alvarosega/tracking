<?php

declare(strict_types=1);

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
        Schema::table('pedidos_rechazados', function (Blueprint $table) {
            if (!Schema::hasColumn('pedidos_rechazados', 'distribuidor')) {
                $table->string('distribuidor', 150)->nullable()->after('ruta')->comment('Nombre del distribuidor/repartidor asignado para entregar');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedidos_rechazados', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos_rechazados', 'distribuidor')) {
                $table->dropColumn('distribuidor');
            }
        });
    }
};

