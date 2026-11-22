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
        Schema::connection('mysql')->create('cliente_inventarios', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 64);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ruta', 50)->nullable();
            $table->string('vendedor', 150)->nullable();
            $table->unsignedBigInteger('cliente_id');
            $table->string('codigo_cliente', 50)->nullable();
            $table->string('cliente_nombre', 255)->nullable();
            $table->dateTime('fecha_registro');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->boolean('is_mock_location')->default(false);
            $table->unsignedInteger('total_items_censados')->default(0);
            $table->text('observaciones')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique('uuid', 'cliente_inventarios_uuid_unique');
            $table->index('user_id', 'cliente_inventarios_user_id_index');
            $table->index('ruta', 'cliente_inventarios_ruta_index');
            $table->index('cliente_id', 'cliente_inventarios_cliente_id_index');
            $table->index('codigo_cliente', 'cliente_inventarios_codigo_cliente_index');
            $table->index('fecha_registro', 'cliente_inventarios_fecha_registro_index');

            $table->foreign('user_id', 'cliente_inventarios_user_id_foreign')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('cliente_inventarios');
    }
};
