<?php

namespace App\Repositories\Contracts;

use App\Models\Compra;
use Illuminate\Support\Collection;

interface CompraRepositoryInterface
{
    public function allByEmpresa(int $empresaId): Collection;

    public function findById(int $id): ?Compra;

    public function create(array $cabecera, array $items): Compra;

    public function confirmar(int $id, string $numero, int $usuarioId): Compra;

    public function registrarPago(int $id, float $monto, int $empresaId, int $usuarioId, array $egresoData): Compra;

    public function anular(int $id, int $empresaId, int $usuarioId): Compra;
}
