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
        Schema::connection('mysql')->create('pedidos_rechazados_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pedido_rechazado_id');
            $table->unsignedBigInteger('preventa_item_id')->nullable()->comment('ID de fila en fact_preventas');
            $table->unsignedBigInteger('producto_id')->nullable();
            $table->string('codigo_producto', 50)->nullable();
            $table->string('producto_nombre', 255)->nullable();
            $table->string('categoria', 100)->nullable();
            $table->integer('cantidad_preventa')->default(0);
            $table->integer('cantidad_rechazada')->default(0);
            $table->decimal('precio_unitario', 10, 2)->default(0.00);
            $table->decimal('monto_rechazado', 12, 2)->default(0.00);
            $table->string('motivo_especifico', 150)->nullable();
            $table->dateTime('created_at')->nullable()->useCurrent();

            $table->index('preventa_item_id', 'pedidos_rechazados_items_preventa_item_id_index');
            $table->index('producto_id', 'pedidos_rechazados_items_producto_id_index');

            $table->foreign('pedido_rechazado_id', 'pedidos_rechazados_items_pedido_rechazado_id_foreign')->references('id')->on('pedidos_rechazados')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('pedidos_rechazados_items');
    }
};
