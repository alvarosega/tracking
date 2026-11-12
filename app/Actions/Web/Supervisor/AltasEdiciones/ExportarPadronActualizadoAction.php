<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\AltasEdiciones;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use XMLWriter;
use ZipArchive;

class ExportarPadronActualizadoAction
{
    /**
     * Genera un archivo nativo .xlsx con la totalidad de clientes correspondientes a las rutas filtradas,
     * reemplazando los campos que hayan sido corregidos en saneamiento_base y formateando las celdas
     * modificadas (y cabeceras) en NEGRILLA (estilo OOXML).
     *
     * @param array<int, string> $canales
     * @param array<int, string> $rutas
     * @param array<int, string> $tipos
     * @param array<int, string> $estados
     * @return string Ruta absoluta del archivo .xlsx generado temporalmente
     */
    public function generateXlsx(
        array $canales = [],
        array $rutas = [],
        array $tipos = [],
        array $estados = []
    ): string {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        // 1. Filtrar rutas por canal si corresponde
        $rutasFiltradas = $rutas;
        if (!empty($canales)) {
            $rutasPorCanal = DB::connection('supervisor')->table('dim_rutas')
                ->whereIn('canal', $canales)
                ->pluck('ruta')
                ->toArray();

            $rutasFiltradas = !empty($rutas)
                ? array_values(array_intersect($rutas, $rutasPorCanal))
                : $rutasPorCanal;
        }

        if (empty($rutasFiltradas)) {
            $rutasFiltradas = DB::connection('supervisor')->table('dim_rutas')
                ->pluck('ruta')
                ->toArray();
        }

        // 2. Consultar solicitudes de saneamiento_base según los filtros activos
        $querySb = DB::connection('supervisor')->table('saneamiento_base as sb')
            ->select([
                'sb.id',
                'sb.cliente_id',
                'sb.tipo_registro',
                'sb.route',
                'sb.cliente',
                'sb.tipo_negocio',
                'sb.zona',
                'sb.lista_precios',
                'sb.contacto',
                'sb.telefono',
                'sb.celular',
                'sb.direccion',
                'sb.referencia',
                'sb.nombre_factura',
                'sb.nit',
                'sb.latitude',
                'sb.longitude',
                'sb.estado_revision',
                'sb.created_at',
            ])
            ->whereNotNull('sb.cliente_id')
            ->orderBy('sb.created_at', 'asc');

        if (!empty($rutasFiltradas)) {
            $querySb->whereIn('sb.route', $rutasFiltradas);
        }

        if (!empty($tipos)) {
            $tiposNorm = [];
            foreach ($tipos as $t) {
                $tUp = strtoupper(trim((string) $t));
                if (str_contains($tUp, 'EDIC') || str_contains($tUp, 'MODIF')) {
                    $tiposNorm[] = 'EDICION';
                    $tiposNorm[] = 'MODIFICACION';
                    $tiposNorm[] = 'EDICIÓN';
                    $tiposNorm[] = 'MODIFICACIÓN';
                } elseif (str_contains($tUp, 'ALTA')) {
                    $tiposNorm[] = 'ALTA';
                    $tiposNorm[] = 'NUEVA_ALTA';
                } else {
                    $tiposNorm[] = $t;
                }
            }
            $tiposNorm = array_values(array_unique($tiposNorm));
            $querySb->where(function ($q) use ($tiposNorm) {
                $q->whereIn('sb.tipo_registro', $tiposNorm)
                  ->orWhereIn(DB::raw('UPPER(TRIM(sb.tipo_registro))'), $tiposNorm);
            });
        }

        if (!empty($estados)) {
            $querySb->whereIn('sb.estado_revision', $estados);
        }

        $correccionesSb = $querySb->get();

        $correccionesMap = [];
        foreach ($correccionesSb as $c) {
            $cid = (int) $c->cliente_id;
            $rKey = strtoupper(trim((string) $c->route));
            $correccionesMap["{$cid}_{$rKey}"] = $c;
            $correccionesMap[(string) $cid] = $c;
        }

        // 3. Consultar clientes de pan_ruteo
        $queryPan = DB::connection('supervisor')->table('pan_ruteo');
        if (!empty($rutasFiltradas)) {
            $queryPan->whereIn('ruta', $rutasFiltradas);
        }

        $clientesPan = $queryPan->orderBy('ruta')->get();

        if ($clientesPan->isEmpty()) {
            throw new RuntimeException('No se encontraron clientes en el padrón para las rutas seleccionadas.');
        }

        // 4. Crear archivo temporal .xlsx
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }
        $tempFile = $tempDir . '/padron_' . uniqid('', true) . '.xlsx';

        $zip = new ZipArchive();
        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("No se pudo crear el archivo temporal Excel en {$tempFile}");
        }

        // Estructuras OOXML requeridas
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Padron Actualizado" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>');

        // Estilos: Font 0 = Normal, Font 1 = Bold. xf 0 = Normal, xf 1 = Bold
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><name val="Calibri"/><sz val="11"/></font>
    <font><b/><name val="Calibri"/><sz val="11"/><color rgb="FF000000"/></font>
  </fonts>
  <fills count="2">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
  </fills>
  <borders count="1">
    <border><left/><right/><top/><bottom/><diagonal/></border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>
  </cellXfs>
</styleSheet>');

        // Generar XML de sheet1 usando XMLWriter en memoria/archivo
        $xmlWriter = new XMLWriter();
        $xmlWriter->openMemory();
        $xmlWriter->startDocument('1.0', 'UTF-8');
        $xmlWriter->startElementNS(null, 'worksheet', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $xmlWriter->startElement('sheetData');

        $headers = [
            'IdCliente', 'Codigo', 'ApellidoPaterno', 'ApellidoMaterno', 'Nombres',
            'TipoNegocio', 'TipoPago', 'DiasPago', 'TopeCredito', 'Latitud', 'Longitud',
            'Activo', 'Direccion', 'UV', 'Referencia', 'Zona', 'Provincia', 'Mercado',
            'DocumentoIdentidad', 'Expedido', 'Telefono', 'Celular', 'Email', 'PaginaWeb',
            'RazonSocial', 'Cluster', 'TipoDocumento', 'TipoNit', 'NombreFactura', 'Nit',
            'Complemento', 'PrecioLista', 'Ruta', 'Dia', 'SecuenciaVenta', 'SecuenciaDistribuidor'
        ];

        // Fila 1: Cabeceras en Negrilla (s="1")
        $xmlWriter->startElement('row');
        $xmlWriter->writeAttribute('r', '1');
        foreach ($headers as $colIdx => $h) {
            $colLetter = $this->getColumnLetter($colIdx + 1);
            $xmlWriter->startElement('c');
            $xmlWriter->writeAttribute('r', "{$colLetter}1");
            $xmlWriter->writeAttribute('s', '1'); // Bold
            $xmlWriter->writeAttribute('t', 'inlineStr');
            $xmlWriter->startElement('is');
            $xmlWriter->writeElement('t', (string) $h);
            $xmlWriter->endElement();
            $xmlWriter->endElement(); // c
        }
        $xmlWriter->endElement(); // row

        $rowNum = 2;
        foreach ($clientesPan as $pr) {
            $cid = (int) ($pr->cliente_id ?? 0);
            $rKey = strtoupper(trim((string) ($pr->ruta ?? '')));
            $corr = $correccionesMap["{$cid}_{$rKey}"] ?? ($correccionesMap[(string) $cid] ?? null);

            // Comparar y determinar si cada campo fue modificado (Negrilla: true)
            // 1. IdCliente
            $f1 = [(string)($pr->cliente_id ?? ''), false];
            // 2. Codigo
            $f2 = [(string)($pr->nodo_id ?: ($pr->cliente_id ?? '')), false];
            // 3, 4 y 5: División algorítmica de Nombre Completo en ApellidoPaterno, ApellidoMaterno y Nombres
            $origNombre = trim((string)($pr->cliente ?: ($pr->cliente_norm ?? '')));
            $modNombre = $corr && !empty(trim((string)($corr->cliente ?? ''))) ? trim((string)$corr->cliente) : null;
            $nombreBold = $modNombre !== null && $this->isDiferente($origNombre, $modNombre);
            $nombreFinal = $nombreBold ? $modNombre : $origNombre;

            [$apellidoPaterno, $apellidoMaterno, $nombres] = $this->dividirNombreCompleto($nombreFinal);

            // 3. ApellidoPaterno
            $f3 = [$apellidoPaterno, $nombreBold && $apellidoPaterno !== ''];
            // 4. ApellidoMaterno
            $f4 = [$apellidoMaterno, $nombreBold && $apellidoMaterno !== ''];
            // 5. Nombres
            $f5 = [$nombres, $nombreBold && $nombres !== ''];

            // 6. TipoNegocio
            $origTN = trim((string)($pr->tipo_negocio ?? ''));
            $modTN = $corr && !empty(trim((string)($corr->tipo_negocio ?? ''))) ? trim((string)$corr->tipo_negocio) : null;
            $tnBold = $modTN !== null && $this->isDiferente($origTN, $modTN);
            $f6 = [$tnBold ? $modTN : $origTN, $tnBold];

            // 7. TipoPago
            $f7 = [(string)($pr->tipo_pago ?? ''), false];
            // 8. DiasPago
            $f8 = [(string)($pr->dias_credito ?? ''), false];
            // 9. TopeCredito
            $f9 = [(string)($pr->limite ?? ''), false];

            // 10 & 11. GPS Latitud y Longitud
            $origLat = $pr->latitud ?? '';
            $origLng = $pr->longitud ?? '';
            $modLat = null;
            $modLng = null;
            $gpsBold = false;
            if ($corr && isset($corr->latitude, $corr->longitude) && is_numeric($corr->latitude) && is_numeric($corr->longitude)) {
                $cLat = (float) $corr->latitude;
                $cLng = (float) $corr->longitude;
                if ($cLat != 0.0 && $cLng != 0.0 && $cLat >= -90 && $cLat <= 90 && $cLng >= -180 && $cLng <= 180) {
                    $modLat = $cLat;
                    $modLng = $cLng;
                    $gpsBold = $this->isGpsDiferente($origLat, $origLng, $modLat, $modLng);
                }
            }
            $f10 = [$gpsBold ? (string)$modLat : (string)$origLat, $gpsBold];
            $f11 = [$gpsBold ? (string)$modLng : (string)$origLng, $gpsBold];

            // 12. Activo
            $f12 = [(string)($pr->estado ?? 'Activo'), false];

            // 13. Direccion
            $origDir = trim((string)($pr->direccion ?? ''));
            $modDir = $corr && !empty(trim((string)($corr->direccion ?? ''))) ? trim((string)$corr->direccion) : null;
            $dirBold = $modDir !== null && $this->isDiferente($origDir, $modDir);
            $f13 = [$dirBold ? $modDir : $origDir, $dirBold];

            // 14. UV
            $f14 = ['', false];

            // 15. Referencia
            $origRef = trim((string)($pr->referencia ?? ''));
            $modRef = $corr && !empty(trim((string)($corr->referencia ?? ''))) ? trim((string)$corr->referencia) : null;
            $refBold = $modRef !== null && $this->isDiferente($origRef, $modRef);
            $f15 = [$refBold ? $modRef : $origRef, $refBold];

            // 16. Zona
            $origZona = trim((string)($pr->territorio ?: ($pr->zona_venta ?? '')));
            $modZona = $corr && !empty(trim((string)($corr->zona ?? ''))) ? trim((string)$corr->zona) : null;
            $zonaBold = $modZona !== null && $this->isDiferente($origZona, $modZona);
            $f16 = [$zonaBold ? $modZona : $origZona, $zonaBold];

            // 17. Provincia
            $f17 = ['', false];
            // 18. Mercado
            $f18 = [(string)($pr->mercado ?? ''), false];
            // 19. DocumentoIdentidad
            $f19 = [(string)($pr->ci ?? ''), false];
            // 20. Expedido
            $f20 = ['', false];

            // 21. Telefono
            $origTel = trim((string)($pr->telefono ?? ''));
            $modTel = $corr && !empty(trim((string)($corr->telefono ?? ''))) ? trim((string)$corr->telefono) : null;
            $telBold = $modTel !== null && $this->isDiferente($origTel, $modTel);
            $f21 = [$telBold ? $modTel : $origTel, $telBold];

            // 22. Celular
            $origCel = trim((string)($pr->celular ?? ''));
            $modCel = $corr && !empty(trim((string)($corr->celular ?? ''))) ? trim((string)$corr->celular) : null;
            $celBold = $modCel !== null && $this->isDiferente($origCel, $modCel);
            $f22 = [$celBold ? $modCel : $origCel, $celBold];

            // 23. Email
            $f23 = ['', false];
            // 24. PaginaWeb
            $f24 = ['', false];
            // 25. RazonSocial
            $f25 = ['', false];
            // 26. Cluster
            $f26 = [(string)($pr->cluster_t ?: ($pr->cluster_c ?? '')), false];
            // 27. TipoDocumento
            $f27 = ['', false];
            // 28. TipoNit
            $f28 = ['', false];

            // 29. NombreFactura
            $origNF = trim((string)($pr->nombre_factura ?? ''));
            $modNF = $corr && !empty(trim((string)($corr->nombre_factura ?? ''))) ? trim((string)$corr->nombre_factura) : null;
            $nfBold = $modNF !== null && $this->isDiferente($origNF, $modNF);
            $f29 = [$nfBold ? $modNF : $origNF, $nfBold];

            // 30. Nit
            $origNit = trim((string)($pr->nit ?? ''));
            $modNit = $corr && !empty(trim((string)($corr->nit ?? ''))) ? trim((string)$corr->nit) : null;
            $nitBold = $modNit !== null && $this->isDiferente($origNit, $modNit);
            $f30 = [$nitBold ? $modNit : $origNit, $nitBold];

            // 31. Complemento
            $f31 = ['', false];

            // 32. PrecioLista
            $origPrecio = trim((string)($pr->precio ?? ''));
            $modPrecio = $corr && !empty(trim((string)($corr->lista_precios ?? ''))) ? trim((string)$corr->lista_precios) : null;
            $precioBold = $modPrecio !== null && $this->isDiferente($origPrecio, $modPrecio);
            $f32 = [$precioBold ? $modPrecio : $origPrecio, $precioBold];

            // 33. Ruta
            $origRuta = trim((string)($pr->ruta ?? ''));
            $modRuta = $corr && !empty(trim((string)($corr->route ?? ''))) ? trim((string)$corr->route) : null;
            $rutaBold = $modRuta !== null && $this->isDiferente($origRuta, $modRuta);
            $f33 = [$rutaBold ? $modRuta : $origRuta, $rutaBold];

            // 34. Dia
            $f34 = [(string)($pr->dia ?? ''), false];
            // 35. SecuenciaVenta
            $f35 = [(string)($pr->secuencia_venta ?? ''), false];
            // 36. SecuenciaDistribuidor
            $f36 = [(string)($pr->secuencia_distribuidor ?? ''), false];

            $rowCells = [
                $f1, $f2, $f3, $f4, $f5, $f6, $f7, $f8, $f9, $f10,
                $f11, $f12, $f13, $f14, $f15, $f16, $f17, $f18, $f19, $f20,
                $f21, $f22, $f23, $f24, $f25, $f26, $f27, $f28, $f29, $f30,
                $f31, $f32, $f33, $f34, $f35, $f36
            ];

            $xmlWriter->startElement('row');
            $xmlWriter->writeAttribute('r', (string) $rowNum);
            foreach ($rowCells as $cIdx => $cellData) {
                [$val, $isBold] = $cellData;
                $colLetter = $this->getColumnLetter($cIdx + 1);
                $xmlWriter->startElement('c');
                $xmlWriter->writeAttribute('r', "{$colLetter}{$rowNum}");
                if ($isBold) {
                    $xmlWriter->writeAttribute('s', '1'); // Estilo en Negrilla
                } else {
                    $xmlWriter->writeAttribute('s', '0');
                }
                $xmlWriter->writeAttribute('t', 'inlineStr');
                $xmlWriter->startElement('is');
                $xmlWriter->writeElement('t', (string) $val);
                $xmlWriter->endElement();
                $xmlWriter->endElement(); // c
            }
            $xmlWriter->endElement(); // row

            $rowNum++;
        }

        $xmlWriter->endElement(); // sheetData
        $xmlWriter->endElement(); // worksheet
        $xmlWriter->endDocument();

        $zip->addFromString('xl/worksheets/sheet1.xml', $xmlWriter->outputMemory());
        $zip->close();

        return $tempFile;
    }

    private function isDiferente(?string $a, ?string $b): bool
    {
        $normA = strtoupper(trim((string) $a));
        $normB = strtoupper(trim((string) $b));
        return $normA !== $normB;
    }

    private function isGpsDiferente($lat1, $lng1, $lat2, $lng2): bool
    {
        if ($lat2 === null || $lng2 === null || !is_numeric($lat2) || !is_numeric($lng2)) {
            return false;
        }
        if ($lat1 === null || $lng1 === null || !is_numeric($lat1) || !is_numeric($lng1)) {
            return true;
        }
        return abs((float) $lat1 - (float) $lat2) > 0.0001 || abs((float) $lng1 - (float) $lng2) > 0.0001;
    }

    private function getColumnLetter(int $colIndex): string
    {
        $letter = '';
        while ($colIndex > 0) {
            $rem = ($colIndex - 1) % 26;
            $letter = chr(65 + $rem) . $letter;
            $colIndex = (int) (($colIndex - 1) / 26);
        }
        return $letter;
    }

    /**
     * Algoritmo de partición de nombres según número de palabras:
     * - 1 palabra: Nombres = palabra 1, Apellidos vacíos
     * - 2 palabras: Nombres = palabra 1, ApellidoPaterno = palabra 2, ApellidoMaterno vacío
     * - 3 palabras: Nombres = palabra 1, ApellidoPaterno = palabra 2, ApellidoMaterno = palabra 3
     * - 4 o más palabras: ApellidoMaterno = última, ApellidoPaterno = penúltima, Nombres = todas las anteriores
     *
     * @param string $fullName
     * @return array{0: string, 1: string, 2: string} [ApellidoPaterno, ApellidoMaterno, Nombres]
     */
    private function dividirNombreCompleto(string $fullName): array
    {
        $tokens = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY);
        $total = count($tokens);

        if ($total === 0) {
            return ['', '', ''];
        }

        if ($total === 1) {
            return ['', '', $tokens[0]];
        }

        if ($total === 2) {
            return [$tokens[1], '', $tokens[0]];
        }

        // 3 o más palabras: las 2 últimas son Paterno y Materno, todo lo anterior es Nombres
        $materno = array_pop($tokens);
        $paterno = array_pop($tokens);
        $nombres = implode(' ', $tokens);

        return [$paterno, $materno, $nombres];
    }
}
