<?php

namespace App\Repositories\Contracts;

use App\Models\Cotizacion;
use Illuminate\Support\Collection;

interface CotizacionRepositoryInterface
{
    public function allByEmpresa(int $empresaId): Collection;

    public function findById(int $id): ?Cotizacion;

    public function create(array $cabecera, array $lineas): Cotizacion;

    public function update(int $id, array $cabecera, array $lineas): Cotizacion;

    public function cambiarEstado(int $id, string $estado): Cotizacion;

    public function delete(int $id): void;
}