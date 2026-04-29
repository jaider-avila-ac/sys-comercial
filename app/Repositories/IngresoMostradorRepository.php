<?php

namespace App\Repositories;

use App\Models\CajaMovimiento;
use App\Models\Inventario;
use App\Models\InventarioMovimiento;
use App\Models\IngresoMostrador;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IngresoMostradorRepository
{
    public function paginate(int $empresaId, array $filters = [], int $perPage = 20): LengthAwarePaginator
{
    $search = $filters['search'] ?? '';
    $desde  = $filters['desde']  ?? null;
    $hasta  = $filters['hasta']  ?? null;

    return IngresoMostrador::where('empresa_id', $empresaId)
        ->with(['usuario', 'item'])
        ->when($search, fn($q) => $q->where(function($q) use ($search) {
            $q->where('numero', 'like', "%{$search}%")
              ->orWhere('descripcion', 'like', "%{$search}%")
              ->orWhereHas('item', fn($q) => $q->where('nombre', 'like', "%{$search}%"));
        }))
        ->when($desde, fn($q) => $q->whereDate('fecha', '>=', $desde))
        ->when($hasta, fn($q) => $q->whereDate('fecha', '<=', $hasta))
        ->orderByDesc('fecha')
        ->paginate($perPage);
}
    public function allByEmpresa(int $empresaId): Collection
    {
        return IngresoMostrador::where('empresa_id', $empresaId)
            ->with(['usuario', 'item'])
            ->orderByDesc('fecha')
            ->get();
    }

    public function findById(int $id): ?IngresoMostrador
    {
        return IngresoMostrador::with(['usuario', 'item'])->find($id);
    }

    public function registrar(array $data, int $empresaId, int $usuarioId): IngresoMostrador
    {
        return DB::transaction(function () use ($data, $empresaId, $usuarioId) {
            $ingreso = IngresoMostrador::create([
                ...$data,
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'estado'     => 'ACTIVO',
            ]);

            if ($ingreso->item_id) {
                $item = $ingreso->item;
                if ($item && $item->controla_inventario) {
                    $inventario = Inventario::where('empresa_id', $empresaId)
                        ->where('item_id', $ingreso->item_id)
                        ->lockForUpdate()
                        ->first();

                    if ($inventario) {
                        $nuevasUnidades = max(0, $inventario->unidades_actuales - $ingreso->cantidad);
                        $inventario->update(['unidades_actuales' => $nuevasUnidades, 'updated_at' => now()]);

                        InventarioMovimiento::create([
                            'empresa_id'           => $empresaId,
                            'item_id'              => $ingreso->item_id,
                            'usuario_id'           => $usuarioId,
                            'tipo'                 => 'SALIDA',
                            'motivo'               => "Venta mostrador {$ingreso->numero}",
                            'referencia_tipo'      => 'MOSTRADOR',
                            'referencia_id'        => $ingreso->id,
                            'unidades'             => $ingreso->cantidad,
                            'unidades_resultantes' => $nuevasUnidades,
                            'ocurrido_en'          => now(),
                        ]);
                    }
                }
            }

            CajaMovimiento::create([
                'empresa_id'  => $empresaId,
                'usuario_id'  => $usuarioId,
                'origen_tipo' => 'INGRESO_MOSTRADOR',
                'origen_id'   => $ingreso->id,
                'descripcion' => $ingreso->descripcion,
                'monto'       => $ingreso->monto,
                'fecha'       => $ingreso->fecha,
                'created_at'  => now(),
            ]);

            return $ingreso->fresh(['usuario', 'item']);
        });
    }

    public function anular(int $id, int $empresaId, int $usuarioId): IngresoMostrador
    {
        return DB::transaction(function () use ($id, $empresaId, $usuarioId) {
            $ingreso = IngresoMostrador::where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->findOrFail($id);

            if ($ingreso->item_id) {
                $item = $ingreso->item;
                if ($item && $item->controla_inventario) {
                    $inventario = Inventario::where('empresa_id', $empresaId)
                        ->where('item_id', $ingreso->item_id)
                        ->lockForUpdate()
                        ->first();

                    if ($inventario) {
                        $nuevasUnidades = $inventario->unidades_actuales + $ingreso->cantidad;
                        $inventario->update(['unidades_actuales' => $nuevasUnidades, 'updated_at' => now()]);

                        InventarioMovimiento::create([
                            'empresa_id'           => $empresaId,
                            'item_id'              => $ingreso->item_id,
                            'usuario_id'           => $usuarioId,
                            'tipo'                 => 'ENTRADA',
                            'motivo'               => "Anulación mostrador {$ingreso->numero}",
                            'referencia_tipo'      => 'MOSTRADOR',
                            'referencia_id'        => $ingreso->id,
                            'unidades'             => $ingreso->cantidad,
                            'unidades_resultantes' => $nuevasUnidades,
                            'ocurrido_en'          => now(),
                        ]);
                    }
                }
            }

            CajaMovimiento::where('origen_tipo', 'INGRESO_MOSTRADOR')
                ->where('origen_id', $id)
                ->where('empresa_id', $empresaId)
                ->delete();

            $ingreso->update(['estado' => 'ANULADO']);
            return $ingreso->fresh();
        });
    }
}
