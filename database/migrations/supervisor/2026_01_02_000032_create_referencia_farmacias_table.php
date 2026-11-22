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
        Schema::connection('supervisor')->create('referencia_farmacias', function (Blueprint $table) {
            $table->id();
            $table->integer('nro')->nullable();
            $table->string('cod_cliente', 50);
            $table->string('cod_sucursal', 50)->nullable();
            $table->string('nit', 50)->nullable();
            $table->string('razon_social', 255)->nullable();
            $table->string('nombre_comercial', 255)->nullable();
            $table->text('direccion')->nullable();
            $table->string('cod_oficina', 50)->nullable();
            $table->string('oficina', 100)->nullable();
            $table->string('cod_region', 50)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('cod_municipio', 50)->nullable();
            $table->string('municipio', 100)->nullable();
            $table->string('cod_provincia', 50)->nullable();
            $table->string('provincia', 100)->nullable();
            $table->string('cod_canal', 50)->nullable();
            $table->string('canal', 100)->nullable();
            $table->string('cod_grupo', 50)->nullable();
            $table->string('grupo_cliente', 100)->nullable();
            $table->string('cond_pago', 50)->nullable();
            $table->decimal('limite_credito', 14, 2)->default(0.00);
            $table->string('cod_vendedor', 50)->nullable();
            $table->string('vendedor', 255)->nullable();
            $table->string('nombre_contacto', 255)->nullable();
            $table->string('tel_contacto', 50)->nullable();
            $table->string('cel_contacto', 50)->nullable();
            $table->string('estado_bloq_gral', 50)->nullable();
            $table->string('estado_bloq_canal', 50)->nullable();
            $table->string('subtipo_cliente', 100)->nullable();
            $table->string('cod_gerente', 50)->nullable();
            $table->string('gerente', 255)->nullable();
            $table->string('cod_jefe', 50)->nullable();
            $table->string('jefe', 255)->nullable();
            $table->string('cod_supervisor', 50)->nullable();
            $table->string('supervisor', 255)->nullable();
            $table->string('clase_abc', 10)->nullable();
            $table->string('grupo_cliente_4', 100)->nullable();
            $table->string('cod_grupo_cliente_4', 50)->nullable();
            $table->string('zona', 150)->nullable();
            $table->string('cod_zona', 50)->nullable();
            $table->string('cod_grupo_vendedor', 50)->nullable();
            $table->string('grupo_vendedor', 100)->nullable();
            $table->string('cod_tipo_cliente', 50)->nullable();
            $table->string('tipo_cliente', 100)->nullable();
            $table->string('cod_subtipo_cliente', 50)->nullable();
            $table->string('gps', 100)->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('comentario')->nullable();
            $table->string('cod_grupo_cliente_3', 50)->nullable();
            $table->string('grupo_cliente_3', 100)->nullable();
            $table->timestamp('creado_en')->nullable()->useCurrent();

            $table->index('cod_cliente', 'idx_rf_cod_cliente');
            $table->index('nit', 'idx_rf_nit');
            $table->index('clase_abc', 'idx_rf_clase_abc');
            $table->index('zona', 'idx_rf_zona');
            $table->index('municipio', 'idx_rf_municipio');
            $table->index(['latitud', 'longitud'], 'idx_rf_coords');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('referencia_farmacias');
    }
};
