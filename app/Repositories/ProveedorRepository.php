<?php

namespace App\Repositories;

use App\Models\Proveedor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProveedorRepository
{
    public function paginate(int $empresaId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search  = $filters['search']  ?? '';
        $activos = $filters['activos'] ?? '1';

        return Proveedor::where('empresa_id', $empresaId)
            ->when($activos !== '0', fn($q) => $q->where('is_activo', true))
            ->when($search, fn($q) => $q->where(fn($q) =>
                $q->where('nombre',   'like', "%{$search}%")
                  ->orWhere('nit',     'like', "%{$search}%")
                  ->orWhere('contacto','like', "%{$search}%")
                  ->orWhere('email',   'like', "%{$search}%")
            ))
            ->orderBy('nombre')
            ->paginate($perPage);
    }

    public function allByEmpresa(int $empresaId): Collection
    {
        return Proveedor::where('empresa_id', $empresaId)
            ->where('is_activo', true)
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
