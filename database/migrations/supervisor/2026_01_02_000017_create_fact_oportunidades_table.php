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
        Schema::connection('supervisor')->create('fact_oportunidades', function (Blueprint $table) {
            $table->id();
            $table->string('cdp', 50)->nullable();
            $table->bigInteger('cliid');
            $table->string('nombre_fantasia', 255)->nullable();
            $table->string('comprador', 255)->nullable();
            $table->string('clinom', 255)->nullable();
            $table->string('nit', 50)->nullable();
            $table->text('clidom')->nullable();
            $table->string('telefono', 100)->nullable();
            $table->string('terr', 50)->nullable();
            $table->string('zonentid', 50)->nullable();
            $table->string('zonenttxt', 255)->nullable();
            $table->string('cuadrante', 50)->nullable();
            $table->string('descuadrante', 255)->nullable();
            $table->string('nucleoflete', 50)->nullable();
            $table->string('desnucleoflete', 255)->nullable();
            $table->string('flete', 50)->nullable();
            $table->string('fechalta', 50)->nullable();
            $table->string('estatus', 50)->nullable();
            $table->string('categoria', 50)->nullable();
            $table->double('longitud');
            $table->double('latitud');
            $table->string('diavisita', 50)->nullable();
            $table->string('desde', 20)->nullable();
            $table->string('hasta', 20)->nullable();
            $table->string('diaalternativo', 50)->nullable();
            $table->string('desdealter', 20)->nullable();
            $table->string('hastaalter', 20)->nullable();
            $table->string('tmpdescarga', 50)->nullable();
            $table->string('lista_de_precio', 50)->nullable();
            $table->string('nro_de_domicilio', 100)->nullable();
            $table->string('personeria_juridica', 100)->nullable();
            $table->string('nombre_reducido', 255)->nullable();
            $table->string('perfil_impositivo', 50)->nullable();
            $table->string('codigo_de_nit', 50)->nullable();
            $table->string('tipo_de_cobranza', 50)->nullable();
            $table->string('prestamo_devolucion', 50)->nullable();
            $table->string('cod_rechazo', 50)->nullable();
            $table->string('localidad', 100)->nullable();
            $table->string('departamento', 100)->nullable();
            $table->string('subcmkt', 50)->nullable();
            $table->string('ramo', 50)->nullable();
            $table->string('dpto', 50)->nullable();
            $table->timestamp('creado_en')->nullable()->useCurrent();

            $table->index('cliid', 'idx_oportunidades_cliid');
            $table->index(['latitud', 'longitud'], 'idx_oportunidades_coords');
            $table->index(['categoria', 'ramo'], 'idx_oportunidades_cat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('fact_oportunidades');
    }
};
