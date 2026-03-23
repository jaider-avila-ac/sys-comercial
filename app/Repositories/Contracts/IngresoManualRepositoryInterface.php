<?php

namespace App\Repositories\Contracts;

use App\Models\IngresoManual;
use Illuminate\Support\Collection;

interface IngresoManualRepositoryInterface
{
    public function allByEmpresa(int $empresaId): Collection;

    public function findById(int $id): ?IngresoManual;

    public function registrar(array $data, int $empresaId, int $usuarioId): IngresoManual;

    public function anular(int $id, int $empresaId): IngresoManual;
}
