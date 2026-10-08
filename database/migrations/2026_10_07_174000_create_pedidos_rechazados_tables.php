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
        Schema::create('pedidos_rechazados', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 64)->unique()->comment('Identificador único para idempotencia y sync offline');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('vendedor', 150)->nullable();
            $table->string('vendedor_username', 100)->nullable()->index();
            $table->unsignedBigInteger('cliente_id')->index();
            $table->string('codigo_cliente', 50)->nullable()->index();
            $table->string('cliente_nombre', 255)->nullable();
            $table->unsignedBigInteger('nro_preventa')->nullable()->index();
            $table->date('fecha_preventa')->nullable()->index();
            $table->dateTime('fecha_rechazo')->index();
            $table->string('ruta', 50)->nullable()->index();
            $table->string('motivo', 150)->index();
            $table->string('tipo_rechazo', 20)->default('TOTAL')->comment('TOTAL o PARCIAL');
            $table->text('comentarios')->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->boolean('is_mock_location')->default(false);
            $table->unsignedInteger('total_items_rechazados')->default(0);
            $table->decimal('monto_total_rechazado', 12, 2)->default(0.00);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('pedidos_rechazados_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_rechazado_id')->constrained('pedidos_rechazados')->onDelete('cascade');
            $table->unsignedBigInteger('preventa_item_id')->nullable()->index()->comment('ID de fila en fact_preventas');
            $table->unsignedBigInteger('producto_id')->nullable()->index();
            $table->string('codigo_producto', 50)->nullable();
            $table->string('producto_nombre', 255)->nullable();
            $table->string('categoria', 100)->nullable();
            $table->integer('cantidad_preventa')->default(0);
            $table->integer('cantidad_rechazada')->default(0);
            $table->decimal('precio_unitario', 10, 2)->default(0.00);
            $table->decimal('monto_rechazado', 12, 2)->default(0.00);
            $table->string('motivo_especifico', 150)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedidos_rechazados_items');
        Schema::dropIfExists('pedidos_rechazados');
    }
};

