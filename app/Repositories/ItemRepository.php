<?php

namespace App\Repositories;

use App\Models\Item;
use App\Repositories\Contracts\ItemRepositoryInterface;
use Illuminate\Support\Collection;

class ItemRepository implements ItemRepositoryInterface
{
    public function allByEmpresa(int $empresaId): Collection
    {
        return Item::where('empresa_id', $empresaId)
            ->with('inventario')
            ->orderBy('nombre')
            ->get();
    }

    public function findById(int $id): ?Item
    {
        return Item::with('inventario')->find($id);
    }

    public function create(array $data): Item
    {
        return Item::create($data);
    }

    public function update(int $id, array $data): Item
    {
        $item = Item::findOrFail($id);
        $item->update($data);
        return $item->fresh(['inventario']);
    }

    public function toggleActivo(int $id): Item
    {
        $item = Item::findOrFail($id);
        $item->update(['is_activo' => ! $item->is_activo]);
        return $item->fresh(['inventario']);
    }

    public function delete(int $id): void
    {
        Item::findOrFail($id)->delete();
    }
}