<?php

namespace App\Repositories\Contracts;

use App\Models\IngresoPago;
use Illuminate\Support\Collection;

interface PagoRepositoryInterface
{
    public function allByEmpresa(int $empresaId): Collection;

    public function findById(int $id): ?IngresoPago;

    public function allByFactura(int $facturaId): Collection;

    public function registrar(array $cabecera, int $facturaId, float $monto, int $empresaId, int $usuarioId): IngresoPago;

    public function anular(int $id, int $empresaId): IngresoPago;
}
