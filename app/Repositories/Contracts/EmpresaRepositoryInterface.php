<?php

namespace App\Repositories\Contracts;

use App\Models\Empresa;
use Illuminate\Support\Collection;

interface EmpresaRepositoryInterface
{
    public function all(): Collection;

    public function findById(int $id): ?Empresa;

    public function create(array $data): Empresa;

    public function update(int $id, array $data): Empresa;

    public function delete(int $id): void;
}