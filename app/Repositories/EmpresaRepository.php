<?php

namespace App\Repositories;

use App\Models\Empresa;
use App\Repositories\Contracts\EmpresaRepositoryInterface;
use Illuminate\Support\Collection;

class EmpresaRepository implements EmpresaRepositoryInterface
{
    public function all(): Collection
    {
        return Empresa::orderBy('nombre')->get();
    }

    public function findById(int $id): ?Empresa
    {
        return Empresa::find($id);
    }

    public function create(array $data): Empresa
    {
        return Empresa::create($data);
    }

    public function update(int $id, array $data): Empresa
    {
        $empresa = Empresa::findOrFail($id);
        $empresa->update($data);
        return $empresa->fresh();
    }

    public function delete(int $id): void
    {
        Empresa::findOrFail($id)->delete();
    }
}