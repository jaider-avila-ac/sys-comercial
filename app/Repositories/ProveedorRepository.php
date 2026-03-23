<?php

namespace App\Repositories;

use App\Models\Proveedor;
use App\Repositories\Contracts\ProveedorRepositoryInterface;
use Illuminate\Support\Collection;

class ProveedorRepository implements ProveedorRepositoryInterface
{
    public function allByEmpresa(int $empresaId): Collection
    {
        return Proveedor::where('empresa_id', $empresaId)
            ->orderBy('nombre')
            ->get();
    }

    public function findById(int $id): ?Proveedor
    {
        return Proveedor::find($id);
    }

    public function create(array $data): Proveedor
    {
        return Proveedor::create($data);
    }

    public function update(int $id, array $data): Proveedor
    {
        $proveedor = Proveedor::findOrFail($id);
        $proveedor->update($data);
        return $proveedor->fresh();
    }

    public function toggleActivo(int $id): Proveedor
    {
        $proveedor = Proveedor::findOrFail($id);
        $proveedor->update(['is_activo' => ! $proveedor->is_activo]);
        return $proveedor->fresh();
    }

    public function delete(int $id): void
    {
        Proveedor::findOrFail($id)->delete();
    }
}