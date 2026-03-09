<?php

namespace App\Services;

class DocumentoCalculos
{
    /**
     * Calcula UNA línea (genérico).
     * Reglas:
     * base = cantidad * valor_unitario
     * base_neta = base - descuento
     * iva = base_neta * (iva_pct/100)
     * total_linea = base_neta + iva
     *
     * Retorna: ['iva_valor' => 0.00, 'total_linea' => 0.00]
     */
    public function calcularLinea(array $linea): array
    {
        $cantidad   = (float)($linea['cantidad'] ?? 1);
        $valorUnit  = (float)($linea['valor_unitario'] ?? 0);
        $descuento  = (float)($linea['descuento'] ?? 0);
        $ivaPct     = (float)($linea['iva_pct'] ?? 0);

        if ($cantidad < 0)  $cantidad = 0;
        if ($valorUnit < 0) $valorUnit = 0;
        if ($descuento < 0) $descuento = 0;
        if ($ivaPct < 0)    $ivaPct = 0;
        if ($ivaPct > 100) $ivaPct = 100;

       $base = $cantidad * $valorUnit;

// IVA se calcula sobre la base (sin restar descuento)
$iva = $base * ($ivaPct / 100);

// Total con IVA
$totalConIva = $base + $iva;

// Descuento se aplica DESPUÉS del IVA
$totalLinea = max(0, $totalConIva - $descuento);

        return [
            'iva_valor'   => round($iva, 2),
            'total_linea' => round($totalLinea, 2),
        ];
    }

    /**
     * Calcula totales de cabecera a partir de un arreglo de líneas.
     * Cada línea debe tener: cantidad, valor_unitario, descuento, iva_valor, total_linea
     *
     * Retorna: ['subtotal','total_descuentos','total_iva','total']
     */
    public function calcularTotales(array $lineas): array
    {
        $subtotal = 0.0;
        $totalDescuentos = 0.0;
        $totalIva = 0.0;
        $total = 0.0;

        foreach ($lineas as $l) {
            $cantidad = (float)($l['cantidad'] ?? 0);
            $valorUnit = (float)($l['valor_unitario'] ?? 0);
            $descuento = (float)($l['descuento'] ?? 0);
            $ivaValor = (float)($l['iva_valor'] ?? 0);
            $totalLinea = (float)($l['total_linea'] ?? 0);

            $base = $cantidad * $valorUnit;

            $subtotal += $base;
            $totalDescuentos += $descuento;
            $totalIva += $ivaValor;
            $total += $totalLinea;
        }

        return [
            'subtotal' => round($subtotal, 2),
            'total_descuentos' => round($totalDescuentos, 2),
            'total_iva' => round($totalIva, 2),
            'total' => round($total, 2),
        ];
    }
}