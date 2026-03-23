<?php

namespace App\Repositories;

use App\Models\CajaMovimiento;
use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\EgresoCompra;
use App\Models\Inventario;
use App\Models\InventarioMovimiento;
use App\Repositories\Contracts\CompraRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompraRepository implements CompraRepositoryInterface
{
    public function allByEmpresa(int $empresaId): Collection
    {
        return Compra::where('empresa_id', $empresaId)
            ->with(['proveedor', 'usuario', 'items.item'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function findById(int $id): ?Compra
    {
        return Compra::with(['proveedor', 'usuario', 'items.item', 'egresos'])->find($id);
    }

    /**
     * Crear compra en PENDIENTE sin número aún — como borrador.
     */
    public function create(array $cabecera, array $items): Compra
    {
        return DB::transaction(function () use ($cabecera, $items) {
            $compra = Compra::create($cabecera);
            $this->sincronizarItems($compra->id, $items);
            return $compra->fresh(['items.item', 'proveedor']);
        });
    }

    /**
     * Confirmar: asigna número, entra inventario y según condición_pago:
     * - CONTADO → crea egreso_compra + movimiento caja automático
     * - CREDITO → solo deja saldo_pendiente = total
     * - null (libre) → solo entra inventario, sin egreso ni deuda
     */
    public function confirmar(int $id, string $numero, int $usuarioId): Compra
    {
        return DB::transaction(function () use ($id, $numero, $usuarioId) {
            $compra = Compra::with('items.item')->lockForUpdate()->findOrFail($id);

            // Asignar número y calcular estado inicial
            $estado          = 'PENDIENTE';
            $saldoPendiente  = 0;

            if ($compra->condicion_pago === 'CREDITO') {
                $saldoPendiente = $compra->total;
            }

            $compra->update([
                'numero'          => $numero,
                'estado'          => $estado,
                'saldo_pendiente' => $saldoPendiente,
            ]);

            // Entrar inventario por cada item que controla_inventario
            foreach ($compra->items as $compraItem) {
                $item = $compraItem->item;

                if (! $item || ! $item->controla_inventario) {
                    continue;
                }

                $inventario = Inventario::where('empresa_id', $compra->empresa_id)
                    ->where('item_id', $item->id)
                    ->lockForUpdate()
                    ->first();

                if ($inventario) {
                    $nuevasUnidades = $inventario->unidades_actuales + $compraItem->cantidad;

                    $inventario->update([
                        'unidades_actuales' => $nuevasUnidades,
                        'updated_at'        => now(),
                    ]);

                    InventarioMovimiento::create([
                        'empresa_id'           => $compra->empresa_id,
                        'item_id'              => $item->id,
                        'usuario_id'           => $usuarioId,
                        'tipo'                 => 'ENTRADA',
                        'motivo'               => "Compra {$numero}",
                        'referencia_tipo'      => 'COMPRA',
                        'referencia_id'        => $compra->id,
                        'unidades'             => $compraItem->cantidad,
                        'unidades_resultantes' => $nuevasUnidades,
                        'ocurrido_en'          => now(),
                    ]);
                }
            }

            // CONTADO → egreso automático + caja
            if ($compra->condicion_pago === 'CONTADO') {
                $egreso = EgresoCompra::create([
                    'empresa_id'  => $compra->empresa_id,
                    'usuario_id'  => $usuarioId,
                    'compra_id'   => $compra->id,
                    'fecha'       => $compra->fecha,
                    'descripcion' => "Pago contado compra {$numero}",
                    'monto'       => $compra->total,
                    'medio_pago'  => 'CONTADO',
                    'estado'      => 'ACTIVO',
                ]);

                CajaMovimiento::create([
                    'empresa_id'  => $compra->empresa_id,
                    'usuario_id'  => $usuarioId,
                    'origen_tipo' => 'EGRESO_COMPRA',
                    'origen_id'   => $egreso->id,
                    'descripcion' => $egreso->descripcion,
                    'monto'       => $egreso->monto,
                    'fecha'       => $egreso->fecha,
                    'created_at'  => now(),
                ]);

                $compra->update(['estado' => 'PAGADA', 'saldo_pendiente' => 0]);
            }

            return $compra->fresh(['items.item', 'proveedor', 'egresos']);
        });
    }

    /**
     * Registrar abono a una compra a CRÉDITO.
     * Actualiza saldo y estado. Crea egreso_compra y movimiento de caja.
     */
    public function registrarPago(int $id, float $monto, int $empresaId, int $usuarioId, array $egresoData): Compra
    {
        return DB::transaction(function () use ($id, $monto, $empresaId, $usuarioId, $egresoData) {
            $compra = Compra::where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->findOrFail($id);

            $nuevoSaldo = round((float) $compra->saldo_pendiente - $monto, 2);
            $nuevoSaldo = max(0, $nuevoSaldo);
            $nuevoEstado = $nuevoSaldo <= 0 ? 'PAGADA' : 'PARCIAL';

            $compra->update([
                'saldo_pendiente' => $nuevoSaldo,
                'estado'          => $nuevoEstado,
            ]);

            // Crear egreso de compra
            $egreso = EgresoCompra::create([
                'empresa_id'  => $empresaId,
                'usuario_id'  => $usuarioId,
                'compra_id'   => $compra->id,
                'fecha'       => $egresoData['fecha'],
                'descripcion' => $egresoData['descripcion'] ?? "Abono compra {$compra->numero}",
                'monto'       => $monto,
                'medio_pago'  => $egresoData['medio_pago'],
                'notas'       => $egresoData['notas'] ?? null,
                'estado'      => 'ACTIVO',
            ]);

            // Registrar en caja
            CajaMovimiento::create([
                'empresa_id'  => $empresaId,
                'usuario_id'  => $usuarioId,
                'origen_tipo' => 'EGRESO_COMPRA',
                'origen_id'   => $egreso->id,
                'descripcion' => $egreso->descripcion,
                'monto'       => $monto,
                'fecha'       => $egreso->fecha,
                'created_at'  => now(),
            ]);

            return $compra->fresh(['items.item', 'proveedor', 'egresos']);
        });
    }

    /**
     * Anular: solo si está PENDIENTE o PARCIAL.
     * Revierte inventario de los items que lo controlan.
     */
    public function anular(int $id, int $empresaId, int $usuarioId): Compra
    {
        return DB::transaction(function () use ($id, $empresaId, $usuarioId) {
            $compra = Compra::with('items.item')
                ->where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->findOrFail($id);

            // Revertir inventario
            foreach ($compra->items as $compraItem) {
                $item = $compraItem->item;

                if (! $item || ! $item->controla_inventario) {
                    continue;
                }

                $inventario = Inventario::where('empresa_id', $empresaId)
                    ->where('item_id', $item->id)
                    ->lockForUpdate()
                    ->first();

                if ($inventario) {
                    $nuevasUnidades = max(0, $inventario->unidades_actuales - $compraItem->cantidad);

                    $inventario->update([
                        'unidades_actuales' => $nuevasUnidades,
                        'updated_at'        => now(),
                    ]);

                    InventarioMovimiento::create([
                        'empresa_id'           => $empresaId,
                        'item_id'              => $item->id,
                        'usuario_id'           => $usuarioId,
                        'tipo'                 => 'SALIDA',
                        'motivo'               => "Anulación compra {$compra->numero}",
                        'referencia_tipo'      => 'COMPRA',
                        'referencia_id'        => $compra->id,
                        'unidades'             => $compraItem->cantidad,
                        'unidades_resultantes' => $nuevasUnidades,
                        'ocurrido_en'          => now(),
                    ]);
                }
            }

            // Anular egresos y limpiar caja
            foreach ($compra->egresos()->where('estado', 'ACTIVO')->get() as $egreso) {
                CajaMovimiento::where('origen_tipo', 'EGRESO_COMPRA')
                    ->where('origen_id', $egreso->id)
                    ->where('empresa_id', $empresaId)
                    ->delete();

                $egreso->update(['estado' => 'ANULADO']);
            }

            $compra->update(['estado' => 'ANULADA']);

            return $compra->fresh();
        });
    }

    // ── Privado ───────────────────────────────────────────────────────────────

    private function sincronizarItems(int $compraId, array $items): void
    {
        CompraItem::where('compra_id', $compraId)->delete();

        $registros = array_map(fn($item) => [
            'compra_id'       => $compraId,
            'item_id'         => $item['item_id'],
            'cantidad'        => $item['cantidad'],
            'precio_unitario' => $item['precio_unitario'],
            'subtotal'        => round($item['cantidad'] * $item['precio_unitario'], 2),
        ], $items);

        CompraItem::insert($registros);
    }
}
