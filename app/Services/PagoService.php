<?php

namespace App\Services;

use App\Models\Factura;
use App\Models\IngresoPago;
use App\Repositories\Contracts\PagoRepositoryInterface;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PagoService
{
    public function __construct(
        private readonly PagoRepositoryInterface $pagoRepository,
        private readonly NumeracionService       $numeracionService,
    ) {}

    public function listar(int $empresaId): Collection
    {
        return $this->pagoRepository->allByEmpresa($empresaId);
    }

    public function obtener(int $id, int $empresaId): IngresoPago
    {
        $pago = $this->pagoRepository->findById($id);

        if (! $pago || $pago->empresa_id !== $empresaId) {
            throw new HttpException(404, 'Pago no encontrado.');
        }

        return $pago;
    }

    public function pagosPorFactura(int $facturaId): Collection
    {
        return $this->pagoRepository->allByFactura($facturaId);
    }

    /**
     * Registrar un pago para UNA factura.
     *
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
        $facturaId = (int)   $data['factura_id'];
        $monto     = (float) $data['monto'];

        // Validar que la factura exista, pertenezca a la empresa y esté EMITIDA
        $factura = Factura::find($facturaId);

        if (! $factura || $factura->empresa_id !== $empresaId) {
            throw new HttpException(404, 'Factura no encontrada.');
        }

        if ($factura->estado !== 'EMITIDA') {
            throw new HttpException(409, "La factura {$factura->numero} no está en estado EMITIDA.");
        }

        if ($monto > (float) $factura->saldo) {
            throw new HttpException(409, "El monto ($monto) supera el saldo pendiente de la factura ({$factura->saldo}).");
        }

        // Generar número consecutivo REC
        $numero = $this->numeracionService->siguienteNumero($empresaId, 'REC');

        $cabecera = [
            'empresa_id'  => $empresaId,
            'usuario_id'  => $usuarioId,
            'numero'      => $numero,
            'fecha'       => $data['fecha'],
            'descripcion' => $data['descripcion'] ?? "Pago {$numero} - Factura {$factura->numero}",
            'monto'       => $monto,
            'notas'       => $data['notas']      ?? null,
            'forma_pago'  => $data['forma_pago'],
            'referencia'  => $data['referencia'] ?? null,
            'estado'      => 'ACTIVO',
        ];

        return $this->pagoRepository->registrar($cabecera, $facturaId, $monto, $empresaId, $usuarioId);
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
