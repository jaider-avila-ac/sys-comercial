<?php

namespace App\Repositories\Contracts;

use App\Models\IngresoMostrador;
use Illuminate\Support\Collection;

interface IngresoMostradorRepositoryInterface
{
    public function allByEmpresa(int $empresaId): Collection;

    public function findById(int $id): ?IngresoMostrador;

    public function registrar(array $data, int $empresaId, int $usuarioId): IngresoMostrador;

    public function anular(int $id, int $empresaId, int $usuarioId): IngresoMostrador;
}
