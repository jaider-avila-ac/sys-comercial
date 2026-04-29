<?php

namespace App\Repositories;

use App\Models\Item;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ItemRepository
{
    public function allByEmpresa(int $empresaId): Collection
{
    return Item::where('empresa_id', $empresaId)
        ->with(['inventario', 'proveedor'])
        ->withCount([
            'facturaLineas as total_vendido' => function ($query) {
                $query->select(\DB::raw('SUM(cantidad)'));
            }
        ])
        ->orderBy('nombre')
        ->get();
}

   public function paginateByEmpresa(int $empresaId, int $perPage = 15, array $filters = []): LengthAwarePaginator
{
    $query = Item::where('empresa_id', $empresaId)
        ->with(['inventario', 'proveedor'])
        ->withCount([
            'facturaLineas as total_vendido' => function ($query) {
                $query->select(\DB::raw('SUM(cantidad)'));
            }
        ])
        ->orderBy('nombre');

    if (!empty($filters['search'])) {
        $query->where(function ($q) use ($filters) {
            $q->where('nombre', 'like', '%' . $filters['search'] . '%')
              ->orWhere('descripcion', 'like', '%' . $filters['search'] . '%');
        });
    }

    if (!empty($filters['tipo'])) {
        $query->where('tipo', $filters['tipo']);
    }

    if (array_key_exists('controla_inventario', $filters) && $filters['controla_inventario'] !== null) {
        $query->where('controla_inventario', $filters['controla_inventario']);
    }

    return $query->paginate($perPage);
}

    public function findById(int $id): ?Item
    {
        return Item::with(['inventario', 'proveedor'])->find($id);
    }

    public function create(array $data): Item
    {
        return Item::create($data);
    }

    public function update(int $id, array $data): Item
    {
        $item = Item::findOrFail($id);
        $item->update($data);

        return $item->fresh(['inventario', 'proveedor']);
    }

    public function toggleActivo(int $id): Item
    {
        $item = Item::findOrFail($id);
        $item->update([
            'is_activo' => ! $item->is_activo,
        ]);

        return $item->fresh(['inventario', 'proveedor']);
    }

    public function delete(int $id): void
    {
        Item::findOrFail($id)->delete();
    }
}