<?php

namespace App\Services;

use App\Models\IngresoMostrador;
use App\Repositories\IngresoMostradorRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IngresoMostradorService
{
    public function __construct(
        private readonly IngresoMostradorRepository $ingresoMostradorRepository,
        private readonly NumeracionService $numeracionService,
    ) {}

    public function listar(int $empresaId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->ingresoMostradorRepository->paginate($empresaId, $filters, $perPage);
    }

    public function obtener(int $id, int $empresaId): IngresoMostrador
    {
        $ingreso = $this->ingresoMostradorRepository->findById($id);

        if (! $ingreso || $ingreso->empresa_id !== $empresaId) {
            throw new HttpException(404, 'Venta mostrador no encontrada.');
        }

        return $ingreso;
    }

    /**
     * Registrar venta mostrador.
     * Calcula monto = cantidad * precio_unitario * (1 + iva_pct/100)
     * Genera número MOS automáticamente.
     */
    public function registrar(array $data, int $empresaId, int $usuarioId): IngresoMostrador
    {
        $cantidad       = (int)   $data['cantidad'];
        $precioUnitario = (float) $data['precio_unitario'];
        $ivaPct         = (float) ($data['iva_pct'] ?? 0);

        $baseConDescuento = $cantidad * $precioUnitario;
        $ivaValor         = round($baseConDescuento * ($ivaPct / 100), 2);
        $monto            = round($baseConDescuento + $ivaValor, 2);

        $numero = $this->numeracionService->siguienteNumero($empresaId, 'MOS');

        $payload = [
            ...$data,
            'numero'          => $numero,
            'monto'           => $monto,
            'descripcion'     => $data['descripcion'] ?? "Venta mostrador {$numero}",
        ];

        return $this->ingresoMostradorRepository->registrar($payload, $empresaId, $usuarioId);
    }

    public function anular(int $id, int $empresaId, int $usuarioId): IngresoMostrador
    {
        $ingreso = $this->obtener($id, $empresaId);

        if ($ingreso->estado === 'ANULADO') {
            throw new HttpException(409, 'La venta ya está anulada.');
        }

        return $this->ingresoMostradorRepository->anular($id, $empresaId, $usuarioId);
    }
}