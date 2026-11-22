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
        Schema::connection('supervisor')->create('inv_stock_actual', function (Blueprint $table) {
            $table->increments('id');
            $table->string('almacen', 100)->default('CENTRAL');
            $table->string('producto', 255);
            $table->string('producto_norm', 255);
            $table->integer('saldo_unitario')->default(0);
            $table->integer('saldo_paquete')->default(0);
            $table->string('lote', 100)->default(1);
            $table->string('archivo_origen', 255)->nullable();
            $table->dateTime('fecha_ingesta')->useCurrent();

            $table->index('producto_norm', 'idx_inv_stock_producto_norm');
            $table->index('almacen', 'idx_inv_stock_almacen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('inv_stock_actual');
    }
};
