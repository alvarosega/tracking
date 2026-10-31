<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class TruncateRuteoCommand extends Command
{
    /**
     * Firma del comando Artisan CLI.
     *
     * @var string
     */
    protected $signature = 'ingesta:ruteo-truncate 
                            {--force : Ejecutar sin solicitar confirmación previa}';

    /**
     * Descripción del comando en consola.
     *
     * @var string
     */
    protected $description = 'Vacía las tablas de plan de ruteo (pan_ruteo, snapshots, auditoría y bitácora) para iniciar una carga limpia desde cero.';

    /**
     * Ejecuta el comando de consola.
     */
    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm('¿Está seguro de que desea vaciar todas las tablas de plan de ruteo, snapshots y auditoría? Esta acción es irreversible.', false)) {
            $this->line('Operación cancelada.');
            return Command::SUCCESS;
        }

        $this->warn('Vaciando tablas del módulo de plan de ruteo...');

        $conn = DB::connection('supervisor');

        $tablas = [
            'pan_ruteo',
            'pan_ruteo_snapshots',
            'auditoria_ruteo_cambios',
        ];

        foreach ($tablas as $tabla) {
            try {
                $conn->statement("TRUNCATE TABLE {$tabla}");
                $this->info("  [TRUNCATE] {$tabla} vaciada exitosamente.");
            } catch (Throwable) {
                $conn->table($tabla)->delete();
                $this->line("  [DELETE] {$tabla} vaciada vía DELETE.");
            }
        }

        try {
            $borrados = $conn->table('sys_cargas')->where('tipo_entidad', 'PAN_RUTEO')->delete();
            $this->info("  [BITACORA] sys_cargas limpiada ({$borrados} registros eliminados).");
        } catch (Throwable $eBit) {
            $this->warn("  [BITACORA] No se pudo limpiar sys_cargas: " . $eBit->getMessage());
        }

        $this->info('================================================================================');
        $this->info('Tablas de ruteo vaciadas completamente. Listo para carga limpia inicial (--baseline).');
        $this->info('================================================================================');

        return Command::SUCCESS;
    }
}

