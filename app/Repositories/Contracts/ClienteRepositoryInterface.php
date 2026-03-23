<?php

namespace App\Repositories\Contracts;

use App\Models\Cliente;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ClienteRepositoryInterface
{
    public function paginate(int $empresaId, string $search = '', int $perPage = 20): LengthAwarePaginator;

    public function findById(int $id): ?Cliente;

    public function create(array $data): Cliente;

    public function update(int $id, array $data): Cliente;

    public function toggleActivo(int $id): Cliente;

    public function delete(int $id): void;
}