<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tabla para control dinámico de módulos y configuraciones móviles/supervisor
        Schema::create('app_modules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('Código único del módulo (ej: inventario_cliente, pedidos_rechazados)');
            $table->string('name', 100)->comment('Nombre visible');
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true)->comment('Habilita o deshabilita el acceso en Android');
            $table->integer('order')->default(0)->comment('Orden de visualización');
            $table->json('config')->nullable()->comment('Parámetros variables en formato JSON (ej: {"dias_historial": 30})');
            $table->timestamps();
        });

        // 2. Tabla cabecera para la toma de inventario en cliente (PDV)
        Schema::create('cliente_inventarios', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 64)->unique()->comment('Identificador único para idempotencia y sync offline');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ruta', 50)->nullable()->index();
            $table->string('vendedor', 150)->nullable();
            $table->unsignedBigInteger('cliente_id')->index();
            $table->string('codigo_cliente', 50)->nullable()->index();
            $table->string('cliente_nombre', 255)->nullable();
            $table->dateTime('fecha_registro')->index();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->boolean('is_mock_location')->default(false);
            $table->unsignedInteger('total_items_censados')->default(0);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        // 3. Tabla detalle de ítems de stock físico contados
        Schema::create('cliente_inventario_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_inventario_id')->constrained('cliente_inventarios')->onDelete('cascade');
            $table->unsignedBigInteger('producto_id')->nullable()->index();
            $table->string('codigo_producto', 50)->nullable();
            $table->string('producto_nombre', 255)->nullable();
            $table->string('categoria', 100)->nullable();
            $table->integer('stock_fisico')->default(0)->comment('Cantidad física contada en la tienda');
            $table->integer('cantidad_historica')->default(0)->comment('Total compras en el periodo histórico configurado');
            $table->date('ultima_compra_fecha')->nullable();
            $table->integer('ultima_compra_cantidad')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });

        // 4. Semillas iniciales de los módulos de la aplicación móvil
        $now = now();
        $initialModules = [
            [
                'code' => 'ruteo',
                'name' => 'Plan de Ruteo',
                'description' => 'Mapa, clientes asignados del día y secuencia de visita',
                'is_active' => true,
                'order' => 1,
                'config' => json_encode([]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'visitas',
                'name' => 'Registro de Visitas',
                'description' => 'Check-in, fotos de evidencia y registro en punto de venta',
                'is_active' => true,
                'order' => 2,
                'config' => json_encode([]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'tracking',
                'name' => 'Seguimiento GPS',
                'description' => 'Transmisión periódica de ubicación y telemetría',
                'is_active' => true,
                'order' => 3,
                'config' => json_encode([]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'workday',
                'name' => 'Control de Jornada',
                'description' => 'Inicio y cierre de jornada laboral diaria',
                'is_active' => true,
                'order' => 4,
                'config' => json_encode([]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'pedidos_rechazados',
                'name' => 'Pedidos Rechazados',
                'description' => 'Auditoría y justificación de preventas rechazadas en reparto',
                'is_active' => true,
                'order' => 5,
                'config' => json_encode([]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'inventario_cliente',
                'name' => 'Inventario en PDV',
                'description' => 'Toma y censo de stock físico en el cliente basado en historial de ventas',
                'is_active' => true,
                'order' => 6,
                'config' => json_encode(['dias_historial' => 30]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'oportunidades',
                'name' => 'Oportunidades de Venta',
                'description' => 'Sugerencias de productos y oportunidades comerciales',
                'is_active' => true,
                'order' => 7,
                'config' => json_encode([]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'saneamiento',
                'name' => 'Saneamiento de Clientes',
                'description' => 'Actualización y corrección de datos de clientes en campo',
                'is_active' => true,
                'order' => 8,
                'config' => json_encode([]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('app_modules')->insert($initialModules);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente_inventario_items');
        Schema::dropIfExists('cliente_inventarios');
        Schema::dropIfExists('app_modules');
    }
};

