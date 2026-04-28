<?php

namespace App\Services;

use App\Models\EgresoManual;
use App\Models\EmpresaResumen;
use App\Repositories\EgresoManualRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EgresoManualService
{
    public function __construct(
        private readonly EgresoManualRepository $egresoManualRepository,
        private readonly ResumenService $resumenService,
    ) {}

    public function listar(int $empresaId, array $filters = []): LengthAwarePaginator
    {
        return $this->egresoManualRepository->paginate($empresaId, $filters);
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
        $monto = (float) ($data['monto'] ?? 0);

        $this->validarMontoDisponibleParaRegistrar($empresaId, $monto);

        return $this->egresoManualRepository->registrar($data, $empresaId, $usuarioId);
    }

    public function actualizar(int $id, array $data, int $empresaId): EgresoManual
    {
        $egreso = $this->obtener($id, $empresaId);

        if ($egreso->estado === 'ANULADO') {
            throw new HttpException(422, 'No puedes editar un egreso anulado.');
        }

        $montoNuevo = array_key_exists('monto', $data)
            ? (float) $data['monto']
            : (float) $egreso->monto;

        $this->validarMontoDisponibleParaActualizar($empresaId, $egreso, $montoNuevo);

        return $this->egresoManualRepository->actualizar($id, $data, $empresaId);
    }

    public function anular(int $id, int $empresaId): EgresoManual
    {
        $egreso = $this->obtener($id, $empresaId);

        if ($egreso->estado === 'ANULADO') {
            throw new HttpException(422, 'El egreso ya está anulado.');
        }

        return $this->egresoManualRepository->anular($id, $empresaId);
    }

    private function validarMontoDisponibleParaRegistrar(int $empresaId, float $monto): void
    {
        $this->resumenService->recalcular($empresaId);

        $resumen = EmpresaResumen::find($empresaId);
        $disponible = (float) ($resumen?->balance_real ?? 0);

        if ($monto > $disponible) {
            throw new HttpException(
                422,
                "El monto del egreso supera lo disponible en caja. Disponible actual: {$disponible}."
            );
        }
    }

    private function validarMontoDisponibleParaActualizar(int $empresaId, EgresoManual $egresoActual, float $montoNuevo): void
    {
        $this->resumenService->recalcular($empresaId);

        $resumen = EmpresaResumen::find($empresaId);
        $balanceActual = (float) ($resumen?->balance_real ?? 0);

        // Como el balance actual ya tiene descontado este egreso,
        // lo “devolvemos” temporalmente para calcular cuánto realmente puede volver a gastar.
        $disponibleAjustado = $balanceActual + (float) $egresoActual->monto;

        if ($montoNuevo > $disponibleAjustado) {
            throw new HttpException(
                422,
                "El nuevo monto del egreso supera lo disponible. Disponible ajustado: {$disponibleAjustado}."
            );
        }
    }
}