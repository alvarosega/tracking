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
        Schema::connection('supervisor')->create('saneamiento_base', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->bigInteger('cliente_id')->nullable();
            $table->string('tipo_registro', 20);
            $table->string('route', 50);
            $table->string('cliente', 255);
            $table->string('tipo_negocio', 100);
            $table->string('zona', 100);
            $table->string('lista_precios', 50)->nullable();
            $table->string('contacto', 255)->nullable();
            $table->string('telefono', 100)->nullable();
            $table->string('celular', 100)->nullable();
            $table->text('direccion')->nullable();
            $table->text('referencia')->nullable();
            $table->string('nombre_factura', 255)->nullable();
            $table->string('nit', 50)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 11, 7)->nullable();
            $table->float('accuracy')->nullable()->default(0);
            $table->string('photo_path', 255)->nullable();
            $table->string('estado_revision', 20)->default('PENDIENTE');
            $table->dateTime('revisado_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();

            $table->index('user_id', 'idx_saneamiento_user');
            $table->index(['tipo_registro', 'estado_revision'], 'idx_saneamiento_tipo_estado');
            $table->index('cliente_id', 'idx_saneamiento_cliente');
            $table->index('route', 'idx_saneamiento_ruta');
            $table->index(['user_id', 'created_at'], 'idx_saneamiento_user_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('saneamiento_base');
    }
};
