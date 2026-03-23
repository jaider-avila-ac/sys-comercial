<?php

namespace App\Services;

use App\Models\EgresoManual;
use App\Repositories\Contracts\EgresoManualRepositoryInterface;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EgresoManualService
{
    public function __construct(
        private readonly EgresoManualRepositoryInterface $egresoManualRepository,
        private readonly NumeracionService               $numeracionService,
    ) {}

    public function listar(int $empresaId): Collection
    {
        return $this->egresoManualRepository->allByEmpresa($empresaId);
    }

    public function obtener(int $id, int $empresaId): EgresoManual
    {
        $egreso = $this->egresoManualRepository->findById($id);

        if (! $egreso || $egreso->empresa_id !== $empresaId) {
            throw new HttpException(404, 'Egreso no encontrado.');
        }

        return $egreso;
    }

    public function registrar(array $data, int $empresaId, int $usuarioId): EgresoManual
    {
        return $this->egresoManualRepository->registrar($data, $empresaId, $usuarioId);
    }

    public function anular(int $id, int $empresaId): EgresoManual
    {
        $egreso = $this->obtener($id, $empresaId);

        if ($egreso->estado === 'ANULADO') {
            throw new HttpException(409, 'El egreso ya está anulado.');
        }

        return $this->egresoManualRepository->anular($id, $empresaId);
    }
}
