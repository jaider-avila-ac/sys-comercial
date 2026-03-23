<?php

namespace App\Repositories\Contracts;

use App\Models\EgresoManual;
use Illuminate\Support\Collection;

interface EgresoManualRepositoryInterface
{
    public function allByEmpresa(int $empresaId): Collection;

    public function findById(int $id): ?EgresoManual;

    public function registrar(array $data, int $empresaId, int $usuarioId): EgresoManual;

    public function anular(int $id, int $empresaId): EgresoManual;
}
