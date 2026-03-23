<?php

namespace App\Repositories\Contracts;

use App\Models\Item;
use Illuminate\Support\Collection;

interface ItemRepositoryInterface
{
    public function allByEmpresa(int $empresaId): Collection;

    public function findById(int $id): ?Item;

    public function create(array $data): Item;

    public function update(int $id, array $data): Item;

    public function toggleActivo(int $id): Item;

    public function delete(int $id): void;
}