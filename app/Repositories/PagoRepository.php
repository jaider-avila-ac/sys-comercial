<?php

namespace App\Repositories;

use App\Models\CajaMovimiento;
use App\Models\Factura;
use App\Models\IngresoPago;
use App\Models\PagoAplicacion;
use App\Repositories\Contracts\PagoRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PagoRepository implements PagoRepositoryInterface
{
    public function allByEmpresa(int $empresaId): Collection
    {
        return IngresoPago::where('empresa_id', $empresaId)
            ->with(['aplicaciones.factura', 'usuario'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function findById(int $id): ?IngresoPago
    {
        return IngresoPago::with(['aplicaciones.factura', 'usuario'])->find($id);
    }

    public function allByFactura(int $facturaId): Collection
    {
        return PagoAplicacion::where('factura_id', $facturaId)
            ->with('ingresoPago')
            ->get();
    }

    /**
     * Un pago → una factura.
     * Todo en una transacción: crea el pago, la aplicación,
     * actualiza el saldo de la factura y registra en caja.
     */
    public function registrar(
        array $cabecera,
        int   $facturaId,
        float $monto,
        int   $empresaId,
        int   $usuarioId,
    ): IngresoPago {
        return DB::transaction(function () use ($cabecera, $facturaId, $monto, $empresaId, $usuarioId) {

            // 1. Crear el pago
            $pago = IngresoPago::create($cabecera);

            // 2. Registrar la aplicación (siempre una sola)
            PagoAplicacion::create([
                'ingreso_pago_id' => $pago->id,
                'factura_id'      => $facturaId,
                'empresa_id'      => $empresaId,
                'monto'           => $monto,
            ]);

            // 3. Actualizar saldo de la factura
            $factura = Factura::lockForUpdate()->findOrFail($facturaId);

            $nuevoPagado = round((float) $factura->total_pagado + $monto, 2);
            $nuevoSaldo  = round((float) $factura->total - $nuevoPagado, 2);

            $factura->update([
                'total_pagado' => $nuevoPagado,
                'saldo'        => max(0, $nuevoSaldo),
            ]);

            // 4. Registrar en caja
            CajaMovimiento::create([
                'empresa_id'  => $empresaId,
                'usuario_id'  => $usuarioId,
                'origen_tipo' => 'INGRESO_PAGO',
                'origen_id'   => $pago->id,
                'descripcion' => $cabecera['descripcion'],
                'monto'       => $monto,
                'fecha'       => $cabecera['fecha'],
                'created_at'  => now(),
            ]);

            return $pago->fresh(['aplicaciones.factura', 'usuario']);
        });
    }

    /**
     * Anular revierte el saldo de la factura y elimina el movimiento de caja.
     */
    public function anular(int $id, int $empresaId): IngresoPago
    {
        return DB::transaction(function () use ($id, $empresaId) {

            $pago = IngresoPago::where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->findOrFail($id);

            // Revertir saldo en la factura
            $aplicacion = PagoAplicacion::where('ingreso_pago_id', $id)->first();

            if ($aplicacion) {
                $factura = Factura::lockForUpdate()->find($aplicacion->factura_id);

                if ($factura) {
                    $nuevoPagado = round((float) $factura->total_pagado - (float) $aplicacion->monto, 2);
                    $nuevoSaldo  = round((float) $factura->total - max(0, $nuevoPagado), 2);

                    $factura->update([
                        'total_pagado' => max(0, $nuevoPagado),
                        'saldo'        => $nuevoSaldo,
                    ]);
                }
            }

            // Eliminar movimiento de caja
            CajaMovimiento::where('origen_tipo', 'INGRESO_PAGO')
                ->where('origen_id', $id)
                ->where('empresa_id', $empresaId)
                ->delete();

            $pago->update(['estado' => 'ANULADO']);

            return $pago->fresh(['aplicaciones.factura']);
        });
    }
}
