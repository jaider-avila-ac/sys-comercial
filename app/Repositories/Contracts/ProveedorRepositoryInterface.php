<?php

namespace App\Repositories\Contracts;

use App\Models\Proveedor;
use Illuminate\Support\Collection;

interface ProveedorRepositoryInterface
{
    public function allByEmpresa(int $empresaId): Collection;

    public function findById(int $id): ?Proveedor;

    public function create(array $data): Proveedor;

    public function update(int $id, array $data): Proveedor;

    public function toggleActivo(int $id): Proveedor;

    public function delete(int $id): void;
}