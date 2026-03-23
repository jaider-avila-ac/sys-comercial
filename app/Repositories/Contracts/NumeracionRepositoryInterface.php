<?php

namespace App\Repositories\Contracts;

use App\Models\Numeracion;
use Illuminate\Support\Collection;

interface NumeracionRepositoryInterface
{
    public function findByEmpresaYTipo(int $empresaId, string $tipo): ?Numeracion;

    public function allByEmpresa(int $empresaId): Collection;

    public function crearParaEmpresa(int $empresaId): void;

    public function incrementar(int $empresaId, string $tipo): string;

    public function update(int $empresaId, string $tipo, array $data): Numeracion;
}