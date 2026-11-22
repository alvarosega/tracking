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
        Schema::connection('supervisor')->create('route_visit_days', function (Blueprint $table) {
            $table->id();
            $table->string('route', 50)->comment('Código de ruta comercial (ej: TDB 5A)');
            $table->unsignedTinyInteger('day_of_week')->comment('Día laboral: 1=Lunes, 2=Martes, 3=Miércoles, 4=Jueves, 5=Viernes, 6=Sábado');
            $table->string('dia_nombre', 20)->comment('Nombre del día laboral (Lunes a Sábado)');
            $table->longText('dias_visita')->comment('Días de pan_ruteo asignados, ej: ["Lunes"] o ["Lunes", "Martes"]');
            $table->boolean('is_active')->default(true)->comment('1=Activo, 0=Inactivo');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('ID del superadmin que modificó el registro');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();

            $table->unique(['route', 'day_of_week'], 'uniq_route_visit_days_route_dow');
            $table->index(['route', 'is_active'], 'idx_route_is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('route_visit_days');
    }
};
