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
        if (Schema::hasTable('cliente_inventario_items')) {
            Schema::table('cliente_inventario_items', function (Blueprint $table) {
                if (!Schema::hasColumn('cliente_inventario_items', 'jerarquia1')) {
                    $table->string('jerarquia1', 100)->nullable()->after('categoria')->comment('Macro-categoría o división de producto');
                }
                if (!Schema::hasColumn('cliente_inventario_items', 'jerarquia2')) {
                    $table->string('jerarquia2', 100)->nullable()->after('jerarquia1')->comment('Categoría o segmento de producto');
                }
                if (!Schema::hasColumn('cliente_inventario_items', 'jerarquia3')) {
                    $table->string('jerarquia3', 100)->nullable()->after('jerarquia2')->comment('Sub-categoría o marca formato');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('cliente_inventario_items')) {
            Schema::table('cliente_inventario_items', function (Blueprint $table) {
                $cols = [];
                if (Schema::hasColumn('cliente_inventario_items', 'jerarquia1')) {
                    $cols[] = 'jerarquia1';
                }
                if (Schema::hasColumn('cliente_inventario_items', 'jerarquia2')) {
                    $cols[] = 'jerarquia2';
                }
                if (Schema::hasColumn('cliente_inventario_items', 'jerarquia3')) {
                    $cols[] = 'jerarquia3';
                }
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};

