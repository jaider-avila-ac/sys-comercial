<?php

namespace App\Repositories\Contracts;

use App\Models\EgresoCompra;
use Illuminate\Support\Collection;

interface EgresoCompraRepositoryInterface
{
    public function allByEmpresa(int $empresaId): Collection;

    public function allByCompra(int $compraId): Collection;

    public function findById(int $id): ?EgresoCompra;

    public function registrar(array $data, int $empresaId, int $usuarioId): EgresoCompra;

    public function anular(int $id, int $empresaId): EgresoCompra;
}
