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
        Schema::connection('mysql')->create('cliente_inventario_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cliente_inventario_id');
            $table->unsignedBigInteger('producto_id')->nullable();
            $table->string('codigo_producto', 50)->nullable();
            $table->string('producto_nombre', 255)->nullable();
            $table->string('categoria', 100)->nullable();
            $table->integer('stock_fisico')->default(0);
            $table->integer('cantidad_historica')->default(0);
            $table->date('ultima_compra_fecha')->nullable();
            $table->integer('ultima_compra_cantidad')->default(0);
            $table->dateTime('created_at')->nullable()->useCurrent();

            $table->index('producto_id', 'cliente_inventario_items_producto_id_index');

            $table->foreign('cliente_inventario_id', 'cliente_inventario_items_cliente_inventario_id_foreign')->references('id')->on('cliente_inventarios')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('cliente_inventario_items');
    }
};
