<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class IngestRuteoHistoricoCommand extends Command
{
    /**
     * Firma del comando Artisan CLI.
     *
     * @var string
     */
    protected $signature = 'ingesta:historico-ruteo 
                            {dir=storage/app/historicos/plan_ruteo : Directorio con los archivos .xlsx de ruteo} 
                            {--truncate : Vaciar pan_ruteo, snapshots y auditoría antes de iniciar}';

    /**
     * Descripción del comando en consola.
     *
     * @var string
     */
    protected $description = 'Orquestador de ingesta histórica de ruteo. Procesa archivos cronológicamente mediante subprocesos independientes.';

    /**
     * Ejecuta el comando de consola orquestador.
     */
    public function handle(): int
    {
        $dir = (string) $this->argument('dir');
        $resolvedDir = is_dir($dir)
            ? realpath($dir)
            : (is_dir(base_path($dir)) ? realpath(base_path($dir)) : null);

        if (!$resolvedDir || !is_dir($resolvedDir)) {
            // Intentar crearlo si no existe
            $targetDir = base_path($dir);
            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0755, true);
            }
            $resolvedDir = is_dir($targetDir) ? realpath($targetDir) : null;
        }

        if (!$resolvedDir || !is_dir($resolvedDir)) {
            $this->error("El directorio no existe y no pudo ser creado: {$dir}");
            return Command::FAILURE;
        }

        $this->info("================================================================================");
        $this->info("ORQUESTADOR DE INGESTA HISTÓRICA: PLAN DE RUTEO");
        $this->info("Directorio de trabajo: {$resolvedDir}");
        $this->info("================================================================================");

        $conn = DB::connection('supervisor');

        // Manejo de opción --truncate
        if ($this->option('truncate')) {
            $this->warn("Vaciando tablas del módulo de ruteo (truncate solicitado)...");
            try {
                $conn->statement('TRUNCATE TABLE pan_ruteo');
                $conn->statement('TRUNCATE TABLE pan_ruteo_snapshots');
                $conn->statement('TRUNCATE TABLE auditoria_ruteo_cambios');
                $conn->table('sys_cargas')->where('tipo_entidad', 'PAN_RUTEO')->delete();
                $this->warn("Tablas pan_ruteo, pan_ruteo_snapshots y auditoria_ruteo_cambios vaciadas exitosamente.");
            } catch (Throwable $eTrunc) {
                // Si falla por constraints de clave foránea, usar delete
                $conn->table('pan_ruteo')->delete();
                $conn->table('pan_ruteo_snapshots')->delete();
                $conn->table('auditoria_ruteo_cambios')->delete();
                $conn->table('sys_cargas')->where('tipo_entidad', 'PAN_RUTEO')->delete();
                $this->warn("Tablas vaciadas vía DELETE alternativo por restricciones del motor.");
            }
        }

        // 1. Escaneo de Archivos .xlsx reales (ignorando temporales de Excel ~$*)
        $allFiles = glob($resolvedDir . DIRECTORY_SEPARATOR . '*.xlsx') ?: [];
        $archivosValidos = [];

        foreach ($allFiles as $f) {
            $base = basename($f);
            if (str_starts_with($base, '~$') || str_starts_with($base, '.')) {
                continue;
            }
            if (is_file($f)) {
                $archivosValidos[] = $f;
            }
        }

        $totalArchivos = count($archivosValidos);

        if ($totalArchivos === 0) {
            $this->error("No se encontraron archivos .xlsx válidos en el directorio: {$resolvedDir}");
            $this->line("Por favor coloque los archivos cronológicos en: {$resolvedDir}");
            return Command::FAILURE;
        }

        // 2. Ordenamiento Cronológico Estricto (ascendente lexicográfico: t_0 -> t_1 -> ... -> t_n)
        sort($archivosValidos, SORT_STRING);

        $this->info("Se detectaron {$totalArchivos} archivos para procesar secuencialmente:");
        foreach ($archivosValidos as $idx => $archivo) {
            $tipoEtiqueta = ($idx === 0) ? "[t_0 : BASELINE]" : sprintf("[t_%d]", $idx);
            $this->line("  {$tipoEtiqueta} " . basename($archivo));
        }
        $this->line("");

        // 3. Ejecución Aislada de Workers mediante Subprocesos (Symfony Process)
        $phpFinder = new PhpExecutableFinder();
        $phpBinary = $phpFinder->find() ?: 'php';
        $artisanPath = base_path('artisan');

        $procesadosConExito = 0;
        $tiempoInicio = microtime(true);

        foreach ($archivosValidos as $index => $archivoPath) {
            $numArchivo = $index + 1;
            $nombreArchivo = basename($archivoPath);

            $this->info("--------------------------------------------------------------------------------");
            $this->info("Ejecutando Worker [{$numArchivo}/{$totalArchivos}]: {$nombreArchivo}");

            $cmd = [$phpBinary, $artisanPath, 'ingesta:ruteo-archivo-worker', $archivoPath];
            if ($index === 0) {
                $cmd[] = '--baseline';
            }

            $process = new Process($cmd);
            $process->setTimeout(null); // Sin límite de tiempo por archivo

            $exitCode = $process->run(function ($type, $buffer) {
                $this->output->write($buffer);
            });

            if ($exitCode !== 0 || !$process->isSuccessful()) {
                $this->error("ERROR CRÍTICO: Falló el worker al procesar el archivo: {$archivoPath}");
                $this->error("La orquestación histórica se detuvo para proteger la integridad de los datos.");
                return Command::FAILURE;
            }

            $procesadosConExito++;
        }

        $duracionTotal = round(microtime(true) - $tiempoInicio, 2);

        // 4. Resumen Consolidado Final
        $totalVivo = 0;
        $totalSnapshots = 0;
        $totalAuditoria = 0;

        try {
            $totalVivo = $conn->table('pan_ruteo')->count();
            $totalSnapshots = $conn->table('pan_ruteo_snapshots')->count();
            $totalAuditoria = $conn->table('auditoria_ruteo_cambios')->count();
        } catch (Throwable) {}

        $this->info("================================================================================");
        $this->info("RESUMEN DE ORQUESTACIÓN HISTÓRICA CONSOLIDADA");
        $this->info("================================================================================");
        $this->line("  Archivos procesados exitosamente: {$procesadosConExito} de {$totalArchivos}");
        $this->line("  Tiempo total empleado: {$duracionTotal} segundos");
        $this->line("  Total registros activos en pan_ruteo: " . number_format($totalVivo));
        $this->line("  Total registros en snapshots históricos: " . number_format($totalSnapshots));
        $this->line("  Total eventos generados en auditoria_ruteo_cambios: " . number_format($totalAuditoria));
        $this->info("================================================================================");

        return Command::SUCCESS;
    }
}

