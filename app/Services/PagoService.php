<?php

namespace App\Services;

use App\Models\Factura;
use App\Models\IngresoPago;
use App\Repositories\PagoRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PagoService
{
    public function __construct(
        private readonly PagoRepository    $pagoRepository,
        private readonly NumeracionService $numeracionService,
    ) {}

    public function listar(int $empresaId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->pagoRepository->paginate($empresaId, $filters, $perPage);
    }

    public function obtener(int $id, int $empresaId): IngresoPago
    {
        $pago = $this->pagoRepository->findById($id);

        if (! $pago || (int) $pago->empresa_id !== (int) $empresaId) {
            throw new HttpException(404, 'Pago no encontrado.');
        }

        return $pago;
    }

    public function pagosPorFactura(int $facturaId, int $empresaId): Collection
    {
        $factura = Factura::find($facturaId);

        if (! $factura || (int) $factura->empresa_id !== (int) $empresaId) {
            throw new HttpException(404, 'Factura no encontrada.');
        }

        return $this->pagoRepository->allByFactura($facturaId);
    }

    /**
     * $data = [
     *   'factura_id'  => 1,
     *   'monto'       => 500000,
     *   'fecha'       => '2025-01-01',
     *   'forma_pago'  => 'EFECTIVO',
     *   'referencia'  => 'opcional',
     *   'descripcion' => 'opcional',
     *   'notas'       => 'opcional',
     * ]
     */
    public function registrar(array $data, int $empresaId, int $usuarioId): IngresoPago
    {
        $facturaId = (int) $data['factura_id'];
        $monto     = round((float) $data['monto'], 2);

        $factura = Factura::find($facturaId);

        if (! $factura || (int) $factura->empresa_id !== (int) $empresaId) {
            throw new HttpException(404, 'Factura no encontrada.');
        }

        if ($factura->estado !== 'EMITIDA') {
            throw new HttpException(409, 'Solo se pueden registrar pagos sobre facturas EMITIDAS.');
        }

        if ($monto <= 0) {
            throw new HttpException(422, 'El monto del pago debe ser mayor a cero.');
        }

        if ($monto > (float) $factura->saldo) {
            throw new HttpException(
                409,
                "El monto del pago ({$monto}) supera el saldo pendiente de la factura ({$factura->saldo})."
            );
        }

        $numero = $this->numeracionService->siguienteNumero($empresaId, 'REC');

        $cabecera = [
            'empresa_id'  => $empresaId,
            'usuario_id'  => $usuarioId,
            'numero'      => $numero,
            'fecha'       => $data['fecha'],
            'descripcion' => $data['descripcion'] ?? "Pago {$numero} - Factura {$factura->numero}",
            'monto'       => $monto,
            'notas'       => $data['notas'] ?? null,
            'forma_pago'  => $data['forma_pago'],
            'referencia'  => $data['referencia'] ?? null,
            'estado'      => 'ACTIVO',
        ];

        return $this->pagoRepository->registrar(
            $cabecera,
            $facturaId,
            $monto,
            $empresaId,
            $usuarioId
        );
    }

    public function anular(int $id, int $empresaId): IngresoPago
    {
        $pago = $this->obtener($id, $empresaId);

        if ($pago->estado === 'ANULADO') {
            throw new HttpException(409, 'El pago ya está anulado.');
        }

        return $this->pagoRepository->anular($id, $empresaId);
    }
}