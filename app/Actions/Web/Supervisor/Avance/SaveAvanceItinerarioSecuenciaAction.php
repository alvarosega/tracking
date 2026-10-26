<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SaveAvanceItinerarioSecuenciaAction
{
    /**
     * Guarda la secuencia cronológica 1..N de clientes para una ruta y día de ruteo.
     *
     * @param string $ruta
     * @param string $diaNorm
     * @param array<int, array{cliente_id: int, orden: int}> $secuencia
     * @return int Cantidad de registros actualizados/insertados
     */
    public function execute(string $ruta, string $diaNorm, array $secuencia): int
    {
        $rutaClean = strtoupper(trim($ruta));
        $diaClean = strtolower(trim($diaNorm));

        if (empty($secuencia)) {
            return 0;
        }

        // Asegurar que la tabla pan_ruteo_cronologico exista
        $this->asegurarTablaExiste();

        $rowsToUpsert = [];
        foreach ($secuencia as $item) {
            $clienteId = (int) ($item['cliente_id'] ?? 0);
            $orden = (int) ($item['orden'] ?? 0);

            if ($clienteId > 0 && $orden > 0) {
                $rowsToUpsert[] = [
                    'ruta' => $rutaClean,
                    'dia_norm' => $diaClean,
                    'cliente_id' => $clienteId,
                    'orden_visita' => $orden,
                ];
            }
        }

        if (empty($rowsToUpsert)) {
            return 0;
        }

        // Realizar upsert en lotes para máxima eficiencia
        $chunks = array_chunk($rowsToUpsert, 100);
        $totalAfectados = 0;

        foreach ($chunks as $chunk) {
            $totalAfectados += DB::connection('supervisor')->table('pan_ruteo_cronologico')->upsert(
                $chunk,
                ['ruta', 'dia_norm', 'cliente_id'],
                ['orden_visita']
            );
        }

        return $totalAfectados;
    }

    private function asegurarTablaExiste(): void
    {
        try {
            if (!Schema::connection('supervisor')->hasTable('pan_ruteo_cronologico')) {
                DB::connection('supervisor')->statement("
                    CREATE TABLE IF NOT EXISTS pan_ruteo_cronologico (
                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        ruta VARCHAR(50) NOT NULL,
                        dia_norm VARCHAR(50) NOT NULL,
                        cliente_id BIGINT UNSIGNED NOT NULL,
                        orden_visita INT NOT NULL,
                        actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY uk_ruta_dia_cliente (ruta, dia_norm, cliente_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }
        } catch (\Throwable $e) {
            // Silencioso o loguear
        }
    }
}

