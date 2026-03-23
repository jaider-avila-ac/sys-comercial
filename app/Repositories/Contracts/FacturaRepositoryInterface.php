<?php

namespace App\Repositories\Contracts;

use App\Models\Factura;
use Illuminate\Support\Collection;

interface FacturaRepositoryInterface
{
    public function allByEmpresa(int $empresaId): Collection;

    public function findById(int $id): ?Factura;

    public function create(array $cabecera, array $lineas): Factura;

    public function update(int $id, array $cabecera, array $lineas): Factura;

    public function cambiarEstado(int $id, string $estado): Factura;

    public function delete(int $id): void;
}