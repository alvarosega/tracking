<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el campo 'ruta' a la tabla users.
 *
 * IMPORTANTE: Esta migración es segura y NO destructiva.
 * - ruta: Código de ruta comercial asignada al vendedor (ej: "TDB 6A", "MZO 12")
 *         El campo username ya existía y se conserva como identificador de login.
 *
 * Para producción, ejecutar el SQL equivalente manualmente (ver abajo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Ruta comercial asignada al vendedor, después de username
            $table->string('ruta', 50)->nullable()->after('username')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['ruta']);
            $table->dropColumn('ruta');
        });
    }
};

