<?php

namespace App\Repositories;

use App\Models\CajaMovimiento;
use App\Models\Factura;
use App\Models\IngresoPago;
use App\Models\PagoAplicacion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PagoRepository
{
    public function paginate(int $empresaId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search = $filters['search'] ?? '';
        $desde  = $filters['desde']  ?? null;
        $hasta  = $filters['hasta']  ?? null;

        return IngresoPago::where('empresa_id', $empresaId)
            ->with(['aplicaciones.factura', 'usuario'])
            ->when($search, fn($q) => $q->where(fn($q) =>
                $q->where('numero',      'like', "%{$search}%")
                  ->orWhere('descripcion','like', "%{$search}%")
            ))
            ->when($desde, fn($q) => $q->whereDate('fecha', '>=', $desde))
            ->when($hasta, fn($q) => $q->whereDate('fecha', '<=', $hasta))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

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

    public function registrar(
        array $cabecera,
        int   $facturaId,
        float $monto,
        int   $empresaId,
        int   $usuarioId,
    ): IngresoPago {
        return DB::transaction(function () use ($cabecera, $facturaId, $monto, $empresaId, $usuarioId) {
            $pago = IngresoPago::create($cabecera);

            PagoAplicacion::create([
                'ingreso_pago_id' => $pago->id,
                'factura_id'      => $facturaId,
                'empresa_id'      => $empresaId,
                'monto'           => $monto,
            ]);

            $factura     = Factura::lockForUpdate()->findOrFail($facturaId);
            $nuevoPagado = round((float) $factura->total_pagado + $monto, 2);
            $nuevoSaldo  = round((float) $factura->total - $nuevoPagado, 2);

            $factura->update([
                'total_pagado' => $nuevoPagado,
                'saldo'        => max(0, $nuevoSaldo),
            ]);

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

    public function anular(int $id, int $empresaId): IngresoPago
    {
        return DB::transaction(function () use ($id, $empresaId) {
            $pago = IngresoPago::where('empresa_id', $empresaId)->lockForUpdate()->findOrFail($id);

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

            CajaMovimiento::where('origen_tipo', 'INGRESO_PAGO')
                ->where('origen_id', $id)
                ->where('empresa_id', $empresaId)
                ->delete();

            $pago->update(['estado' => 'ANULADO']);
            return $pago->fresh(['aplicaciones.factura']);
        });
    }
}
