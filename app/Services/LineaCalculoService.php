<?php

namespace App\Services;

use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Models\Item; // 🔥 IMPORTANTE

class LineaCalculoService
{
    public function calcularLinea(array $linea): array
    {
        $itemId            = $this->toInt($linea['item_id'] ?? null);
        $descripcionManual = trim((string) ($linea['descripcion_manual'] ?? ''));
        $cantidad          = $this->toInt($linea['cantidad'] ?? 1);
        $valorUnitario     = $this->toFloat($linea['valor_unitario'] ?? 0);
        $descuento         = $this->toFloat($linea['descuento'] ?? 0);
        $ivaPct            = $this->toFloat($linea['iva_pct'] ?? 0);

        $this->validarLinea($itemId, $descripcionManual, $cantidad, $valorUnitario, $descuento, $ivaPct);

        // 🔹 Cálculos
        $subtotalLinea = $this->round2($cantidad * $valorUnitario);

        if ($descuento > $subtotalLinea) {
            throw new HttpException(422, 'El descuento no puede superar el subtotal de la línea.');
        }

        $baseIva    = $this->round2($subtotalLinea - $descuento);
        $ivaValor   = $this->round2($baseIva * ($ivaPct / 100));
        $totalLinea = $this->round2($baseIva + $ivaValor);

        return [
            'item_id'            => $itemId,
            'descripcion_manual' => $descripcionManual,
            'cantidad'           => $cantidad,
            'valor_unitario'     => $valorUnitario,
            'descuento'          => $descuento,
            'iva_pct'            => $ivaPct,
            'iva_valor'          => $ivaValor,
            'total_linea'        => $totalLinea,
        ];
    }

    public function calcularTotalesDocumento(array $lineasCalculadas): array
    {
        $subtotal = 0;
        $totalDescuentos = 0;
        $totalIva = 0;

        foreach ($lineasCalculadas as $linea) {
            $subtotal += $this->round2($linea['cantidad'] * $linea['valor_unitario']);
            $totalDescuentos += $this->round2($linea['descuento']);
            $totalIva += $this->round2($linea['iva_valor']);
        }

        $subtotal = $this->round2($subtotal);
        $totalDescuentos = $this->round2($totalDescuentos);
        $totalIva = $this->round2($totalIva);

        return [
            'subtotal'         => $subtotal,
            'total_descuentos' => $totalDescuentos,
            'total_iva'        => $totalIva,
            'total'            => $this->round2($subtotal - $totalDescuentos + $totalIva),
        ];
    }

    // ================== VALIDACIONES ==================

    private function validarLinea(
        ?int $itemId,
        string $descripcion,
        int $cantidad,
        float $valorUnitario,
        float $descuento,
        float $ivaPct
    ): void {

        if (! $itemId || $itemId <= 0) {
            throw new HttpException(422, 'Cada línea debe tener un item_id válido.');
        }

        // 🔥 NUEVA VALIDACIÓN (PUNTO 4)
        $item = Item::find($itemId);

        if (! $item) {
            throw new HttpException(422, "El ítem {$itemId} no existe.");
        }

        if ($descripcion === '') {
            throw new HttpException(422, 'La descripción de cada línea es obligatoria.');
        }

        if ($cantidad < 1) {
            throw new HttpException(422, 'La cantidad debe ser mayor o igual a 1.');
        }

        if ($valorUnitario < 0) {
            throw new HttpException(422, 'El valor unitario no puede ser negativo.');
        }

        if ($descuento < 0) {
            throw new HttpException(422, 'El descuento no puede ser negativo.');
        }

        if ($ivaPct < 0) {
            throw new HttpException(422, 'El IVA no puede ser negativo.');
        }
    }

    // ================== HELPERS ==================

    private function round2(float $value): float
    {
        return round($value, 2);
    }

    private function toFloat($value): float
    {
        return (float) $value;
    }

    private function toInt($value): int
    {
        return (int) $value;
    }
}