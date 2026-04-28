<?php

namespace App\Repositories;

use App\Models\Factura;
use App\Models\FacturaLinea;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FacturaRepository
{
    public function paginate(int $empresaId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search    = $filters['search'] ?? '';
        $estado    = $filters['estado'] ?? null;
        $clienteId = $filters['cliente_id'] ?? null;
        $desde     = $filters['desde'] ?? null;
        $hasta     = $filters['hasta'] ?? null;

        return Factura::where('empresa_id', $empresaId)
            ->with(['cliente', 'usuario'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('numero', 'like', "%{$search}%")
                        ->orWhereHas('cliente', function ($clienteQ) use ($search) {
                            $clienteQ->where('nombre_razon_social', 'like', "%{$search}%");
                        });
                });
            })
            ->when($estado, fn($q) => $q->where('estado', $estado))
            ->when($clienteId, fn($q) => $q->where('cliente_id', $clienteId))
            ->when($desde, fn($q) => $q->whereDate('fecha', '>=', $desde))
            ->when($hasta, fn($q) => $q->whereDate('fecha', '<=', $hasta))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function allByEmpresa(int $empresaId): Collection
    {
        return Factura::where('empresa_id', $empresaId)
            ->with(['cliente', 'usuario'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function findById(int $id): ?Factura
    {
        return Factura::with(['lineas.item', 'cliente', 'usuario', 'cotizacion'])->find($id);
    }

    public function create(array $cabecera, array $lineas): Factura
    {
        return DB::transaction(function () use ($cabecera, $lineas) {
            $factura = Factura::create($cabecera);
            $this->sincronizarLineas($factura->id, $lineas);

            return $factura->fresh(['lineas.item', 'cliente', 'usuario', 'cotizacion']);
        });
    }

    public function updateConLineas(int $id, array $cabecera, array $lineas): Factura
    {
        return DB::transaction(function () use ($id, $cabecera, $lineas) {
            $factura = Factura::findOrFail($id);
            $factura->update($cabecera);
            $this->sincronizarLineas($id, $lineas);

            return $factura->fresh(['lineas.item', 'cliente', 'usuario', 'cotizacion']);
        });
    }

    public function updateCabecera(int $id, array $cabecera): Factura
    {
        $factura = Factura::findOrFail($id);
        $factura->update($cabecera);

        return $factura->fresh(['lineas.item', 'cliente', 'usuario', 'cotizacion']);
    }

    public function cambiarEstado(int $id, string $estado): Factura
    {
        return $this->updateCabecera($id, ['estado' => $estado]);
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            FacturaLinea::where('factura_id', $id)->delete();
            Factura::findOrFail($id)->delete();
        });
    }

    private function sincronizarLineas(int $facturaId, array $lineas): void
    {
        FacturaLinea::where('factura_id', $facturaId)->delete();

        if (empty($lineas)) {
            return;
        }

        FacturaLinea::insert(
            array_map(
                fn($l) => [...$l, 'factura_id' => $facturaId],
                $lineas
            )
        );
    }
}