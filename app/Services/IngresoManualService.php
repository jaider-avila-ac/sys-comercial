<?php

namespace App\Services;

use App\Models\IngresoManual;
use App\Repositories\Contracts\IngresoManualRepositoryInterface;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IngresoManualService
{
    public function __construct(
        private readonly IngresoManualRepositoryInterface $ingresoManualRepository,
    ) {}

    public function listar(int $empresaId): Collection
    {
        return $this->ingresoManualRepository->allByEmpresa($empresaId);
    }

    public function obtener(int $id, int $empresaId): IngresoManual
    {
        $ingreso = $this->ingresoManualRepository->findById($id);

        if (! $ingreso || $ingreso->empresa_id !== $empresaId) {
            throw new HttpException(404, 'Ingreso no encontrado.');
        }

        return $ingreso;
    }

    public function registrar(array $data, int $empresaId, int $usuarioId): IngresoManual
    {
        return $this->ingresoManualRepository->registrar($data, $empresaId, $usuarioId);
    }

    public function anular(int $id, int $empresaId): IngresoManual
    {
        $ingreso = $this->obtener($id, $empresaId);

        if ($ingreso->estado === 'ANULADO') {
            throw new HttpException(409, 'El ingreso ya está anulado.');
        }

        return $this->ingresoManualRepository->anular($id, $empresaId);
    }
}
