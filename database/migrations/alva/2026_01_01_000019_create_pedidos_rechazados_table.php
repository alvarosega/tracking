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
        Schema::connection('mysql')->create('pedidos_rechazados', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 64)->comment('Identificador único para idempotencia y sync offline');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('vendedor', 150)->nullable();
            $table->string('vendedor_username', 100)->nullable();
            $table->unsignedBigInteger('cliente_id');
            $table->string('codigo_cliente', 50)->nullable();
            $table->string('cliente_nombre', 255)->nullable();
            $table->unsignedBigInteger('nro_preventa')->nullable();
            $table->date('fecha_preventa')->nullable();
            $table->dateTime('fecha_rechazo');
            $table->string('ruta', 50)->nullable();
            $table->string('distribuidor', 150)->nullable();
            $table->string('motivo', 150);
            $table->string('tipo_rechazo', 20)->default('TOTAL')->comment('TOTAL o PARCIAL');
            $table->text('comentarios')->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->boolean('is_mock_location')->default(false);
            $table->unsignedInteger('total_items_rechazados')->default(0);
            $table->decimal('monto_total_rechazado', 12, 2)->default(0.00);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique('uuid', 'pedidos_rechazados_uuid_unique');
            $table->index('user_id', 'pedidos_rechazados_user_id_index');
            $table->index('vendedor_username', 'pedidos_rechazados_vendedor_username_index');
            $table->index('cliente_id', 'pedidos_rechazados_cliente_id_index');
            $table->index('codigo_cliente', 'pedidos_rechazados_codigo_cliente_index');
            $table->index('nro_preventa', 'pedidos_rechazados_nro_preventa_index');
            $table->index('fecha_preventa', 'pedidos_rechazados_fecha_preventa_index');
            $table->index('fecha_rechazo', 'pedidos_rechazados_fecha_rechazo_index');
            $table->index('ruta', 'pedidos_rechazados_ruta_index');
            $table->index('motivo', 'pedidos_rechazados_motivo_index');

            $table->foreign('user_id', 'pedidos_rechazados_user_id_foreign')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('pedidos_rechazados');
    }
};
