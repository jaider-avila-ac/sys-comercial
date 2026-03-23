<?php

namespace App\Services;

/**
 * Servicio puro de cálculo — sin estado, sin BD.
 * Lo usan CotizacionService y FacturaService para no repetir lógica.
 *
 * IVA global vs por línea:
 *   El frontend decide. Si quiere IVA global manda el mismo iva_pct en todas
 *   las líneas. Si por línea, cada una trae el suyo. El backend solo calcula.
 */
class LineaCalculoService
{
    /**
     * Calcula los valores de una línea a partir de los datos crudos.
     *
     * @param  array{
     *   item_id: int|null,
     *   descripcion_manual: string|null,
     *   cantidad: int,
     *   valor_unitario: float,
     *   descuento: float,
     *   iva_pct: float,
     * } $linea
     */
    public function calcularLinea(array $linea): array
    {
        $cantidad      = (int)   ($linea['cantidad']       ?? 1);
        $valorUnitario = (float) ($linea['valor_unitario'] ?? 0);
        $descuento     = (float) ($linea['descuento']      ?? 0);
        $ivaPct        = (float) ($linea['iva_pct']        ?? 0);

        $subtotalLinea = $cantidad * $valorUnitario;
        $baseIva       = $subtotalLinea - $descuento;
        $ivaValor      = round($baseIva * ($ivaPct / 100), 2);
        $totalLinea    = round($baseIva + $ivaValor, 2);

        return [
            'item_id'            => $linea['item_id']            ?? null,
            'descripcion_manual' => $linea['descripcion_manual'] ?? null,
            'cantidad'           => $cantidad,
            'valor_unitario'     => $valorUnitario,
            'descuento'          => $descuento,
            'iva_pct'            => $ivaPct,
            'iva_valor'          => $ivaValor,
            'total_linea'        => $totalLinea,
        ];
    }

    /**
     * Calcula los totales del documento a partir de líneas ya calculadas.
     *
     * @param  array[] $lineasCalculadas  Resultado de calcularLinea() por cada línea
     * @return array{ subtotal, total_descuentos, total_iva, total }
     */
    public function calcularTotalesDocumento(array $lineasCalculadas): array
    {
        $subtotal        = 0;
        $totalDescuentos = 0;
        $totalIva        = 0;

        foreach ($lineasCalculadas as $linea) {
            $subtotal        += $linea['cantidad'] * $linea['valor_unitario'];
            $totalDescuentos += $linea['descuento'];
            $totalIva        += $linea['iva_valor'];
        }

        return [
            'subtotal'         => round($subtotal, 2),
            'total_descuentos' => round($totalDescuentos, 2),
            'total_iva'        => round($totalIva, 2),
            'total'            => round($subtotal - $totalDescuentos + $totalIva, 2),
        ];
    }
}