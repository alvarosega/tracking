<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

class CoversClassifier
{
    /**
     * Clasifica los covers de un conjunto de ítems de preventa (para un cliente en el día).
     *
     * Cover 1: Por la compra de 3 unidades Sedal 340ml, se bonifica 1 Sedal SH Toque de Seda 40ml.
     * Cover 2: Por la compra de 3 Pepsodent (90g o 130g), se bonifica 1 Sedal SH Toque de Seda 40ml.
     * Cover 3: Por cada 12 unidades de Sedal 40ml, se bonifican 2 unidades (descuento en Sedal 40ml).
     *
     * @param iterable<mixed> $items
     * @return array{
     *     covers: array<string>,
     *     tiene_cover1: bool,
     *     tiene_cover2: bool,
     *     tiene_cover3: bool,
     *     tiene_descuento: bool,
     *     cant_sedal_340: int,
     *     cant_pepsodent: int,
     *     cant_toque_seda_bonif: int,
     *     desc_toque_seda: float,
     *     cant_sedal_40: int,
     *     desc_sedal_40: float,
     *     descuento_total: float,
     *     items_etiquetados: array<array<string, mixed>>
     * }
     */
    public static function clasificar(iterable $items): array
    {
        $cantSedal340 = 0;
        $cantPepsodent = 0;
        $hasToqueSedaBonif = false;
        $toqueSedaBonifCant = 0;
        $toqueSedaDescTotal = 0.0;
        $hasSedal40Promo = false;
        $sedal40Cant = 0;
        $sedal40DescTotal = 0.0;
        $descuentoTotal = 0.0;

        foreach ($items as $it) {
            $prod = strtoupper(trim((string) (is_array($it) ? ($it['producto'] ?? '') : ($it->producto ?? ''))));
            $cant = (int) (is_array($it) ? ($it['cantidad'] ?? 0) : ($it->cantidad ?? 0));
            $desc = (float) (is_array($it) ? ($it['descuento'] ?? 0.0) : ($it->descuento ?? 0.0));
            $monto = (float) (is_array($it) ? ($it['monto_final'] ?? ($it['monto'] ?? 0.0)) : ($it->monto_final ?? ($it->monto ?? 0.0)));

            $descuentoTotal += $desc;

            // Sedal 340ml (Shampoo o Acondicionador)
            if (str_contains($prod, 'SEDAL') && str_contains($prod, '340ML')) {
                $cantSedal340 += $cant;
            }

            // Pepsodent 90g o 130g
            if (str_contains($prod, 'PEPSODENT') && (str_contains($prod, '90G') || str_contains($prod, '130G'))) {
                $cantPepsodent += $cant;
            }

            // Bonificación de Sedal Toque de Seda 40ml (monto 0 o descuento total)
            if (str_contains($prod, 'TOQUE DE SEDA') && str_contains($prod, '40ML') && $desc > 0 && $monto <= 0.01) {
                $hasToqueSedaBonif = true;
                $toqueSedaBonifCant += $cant;
                $toqueSedaDescTotal += $desc;
            } elseif (str_contains($prod, 'SEDAL') && str_contains($prod, '40ML') && $desc > 0) {
                // Promo Sedal 40ml (bonificación 2 por cada 12 o descuento aplicado a Sedal 40ml)
                $hasSedal40Promo = true;
                $sedal40Cant += $cant;
                $sedal40DescTotal += $desc;
            }
        }

        $tieneCover1 = false;
        $tieneCover2 = false;
        $tieneCover3 = $hasSedal40Promo;

        if ($hasToqueSedaBonif) {
            if ($cantSedal340 >= 3 && $cantPepsodent >= 3) {
                $tieneCover1 = true;
                $tieneCover2 = true;
            } elseif ($cantSedal340 >= 3) {
                $tieneCover1 = true;
            } elseif ($cantPepsodent >= 3) {
                $tieneCover2 = true;
            } else {
                // Fallback por descarte si la cantidad registrada fue menor pero se aplicó la bonificación
                if ($cantSedal340 > 0 && $cantPepsodent === 0) {
                    $tieneCover1 = true;
                } elseif ($cantPepsodent > 0 && $cantSedal340 === 0) {
                    $tieneCover2 = true;
                } else {
                    $tieneCover1 = true;
                }
            }
        }

        $covers = [];
        if ($tieneCover1) {
            $covers[] = 'cover1';
        }
        if ($tieneCover2) {
            $covers[] = 'cover2';
        }
        if ($tieneCover3) {
            $covers[] = 'cover3';
        }

        // Etiquetar items individuales para auditoría visual
        $itemsEtiquetados = [];
        foreach ($items as $it) {
            $prod = strtoupper(trim((string) (is_array($it) ? ($it['producto'] ?? '') : ($it->producto ?? ''))));
            $cant = (int) (is_array($it) ? ($it['cantidad'] ?? 0) : ($it->cantidad ?? 0));
            $desc = (float) (is_array($it) ? ($it['descuento'] ?? 0.0) : ($it->descuento ?? 0.0));
            $monto = (float) (is_array($it) ? ($it['monto_final'] ?? ($it['monto'] ?? 0.0)) : ($it->monto_final ?? ($it->monto ?? 0.0)));
            $precioLista = (float) (is_array($it) ? ($it['precio_lista'] ?? 0.0) : ($it->precio_lista ?? 0.0));

            $tag = null;
            $tagTipo = null;

            if (str_contains($prod, 'TOQUE DE SEDA') && str_contains($prod, '40ML') && $desc > 0 && $monto <= 0.01) {
                if ($tieneCover1 && $tieneCover2) {
                    $tag = 'C1+C2 BONIF';
                    $tagTipo = 'cover1_2';
                } elseif ($tieneCover1) {
                    $tag = 'C1 BONIF';
                    $tagTipo = 'cover1';
                } elseif ($tieneCover2) {
                    $tag = 'C2 BONIF';
                    $tagTipo = 'cover2';
                } else {
                    $tag = 'BONIF';
                    $tagTipo = 'bonif';
                }
            } elseif (str_contains($prod, 'SEDAL') && str_contains($prod, '40ML') && $desc > 0) {
                $tag = 'C3 PROMO';
                $tagTipo = 'cover3';
            } elseif ($desc > 0) {
                $tag = 'DESC';
                $tagTipo = 'desc';
            } elseif (str_contains($prod, 'SEDAL') && str_contains($prod, '340ML') && $tieneCover1) {
                $tag = 'C1 REQ';
                $tagTipo = 'req1';
            } elseif (str_contains($prod, 'PEPSODENT') && (str_contains($prod, '90G') || str_contains($prod, '130G')) && $tieneCover2) {
                $tag = 'C2 REQ';
                $tagTipo = 'req2';
            }

            $itemData = is_array($it) ? $it : (array) $it;
            $itemData['producto'] = (string) (is_array($it) ? ($it['producto'] ?? 'SKU') : ($it->producto ?? 'SKU'));
            $itemData['codigo_producto'] = (string) (is_array($it) ? ($it['codigo_producto'] ?? '') : ($it->codigo_producto ?? ''));
            $itemData['categoria'] = (string) (is_array($it) ? ($it['categoria'] ?? 'GENERAL') : ($it->categoria ?? 'GENERAL'));
            $itemData['cantidad'] = $cant;
            $itemData['precio_lista'] = round($precioLista, 2);
            $itemData['descuento'] = round($desc, 2);
            $itemData['monto_final'] = round($monto, 2);
            $itemData['con_descuento'] = ($desc > 0);
            $itemData['tag'] = $tag;
            $itemData['tag_tipo'] = $tagTipo;
            $itemsEtiquetados[] = $itemData;
        }

        return [
            'covers' => $covers,
            'tiene_cover1' => $tieneCover1,
            'tiene_cover2' => $tieneCover2,
            'tiene_cover3' => $tieneCover3,
            'tiene_descuento' => ($descuentoTotal > 0),
            'cant_sedal_340' => $cantSedal340,
            'cant_pepsodent' => $cantPepsodent,
            'cant_toque_seda_bonif' => $toqueSedaBonifCant,
            'desc_toque_seda' => round($toqueSedaDescTotal, 2),
            'cant_sedal_40' => $sedal40Cant,
            'desc_sedal_40' => round($sedal40DescTotal, 2),
            'descuento_total' => round($descuentoTotal, 2),
            'items_etiquetados' => $itemsEtiquetados,
        ];
    }
}
