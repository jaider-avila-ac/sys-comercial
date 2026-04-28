<?php

namespace App\Services;

use App\Models\IngresoManual;
use App\Repositories\IngresoManualRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IngresoManualService
{
    public function __construct(
        private readonly IngresoManualRepository $ingresoManualRepository,
    ) {}

    public function listar(int $empresaId, array $filters = []): LengthAwarePaginator
    {
        return $this->ingresoManualRepository->paginate($empresaId, $filters);
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

    public function actualizar(int $id, array $data, int $empresaId): IngresoManual
    {
        $this->obtener($id, $empresaId);

        return $this->ingresoManualRepository->actualizar($id, $data, $empresaId);
    }

    public function anular(int $id, int $empresaId): IngresoManual
    {
        $ingreso = $this->obtener($id, $empresaId);

        if ($ingreso->estado === 'ANULADO') {
            throw new HttpException(422, 'El ingreso ya está anulado.');
        }

        return $this->ingresoManualRepository->anular($id, $empresaId);
    }
}