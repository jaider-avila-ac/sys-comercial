<?php

namespace App\Repositories;

use App\Models\Cliente;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClienteRepository
{
   public function paginate(int $empresaId, string $search = '', int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        return Cliente::where('empresa_id', $empresaId)
            ->when($search, function($q) use ($search) {
                return $q->where(function($q) use ($search) {
                    $q->where('nombre_razon_social', 'like', "%{$search}%")
                      ->orWhere('num_documento', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('telefono', 'like', "%{$search}%");
                });
            })
            ->orderBy('nombre_razon_social')
            ->paginate($perPage, ['*'], 'page', $page);
    }


    public function findById(int $id): ?Cliente
    {
        return Cliente::find($id);
    }

    public function create(array $data): Cliente
    {
        return Cliente::create($data);
    }

    public function update(int $id, array $data): Cliente
    {
        $cliente = Cliente::findOrFail($id);
        $cliente->update($data);
        return $cliente->fresh();
    }

    public function toggleActivo(int $id): Cliente
    {
        $cliente = Cliente::findOrFail($id);
        $cliente->update(['is_activo' => ! $cliente->is_activo]);
        return $cliente->fresh();
    }

    public function delete(int $id): void
    {
        Cliente::findOrFail($id)->delete();
    }
}