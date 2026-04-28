<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\EmpresaResumen;
use App\Repositories\CompraRepository;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CompraService
{
    public function __construct(
        private readonly CompraRepository $compraRepository,
        private readonly NumeracionService $numeracionService,
        private readonly ResumenService $resumenService,
    ) {}

    public function listar(int $empresaId): Collection
    {
        return $this->compraRepository->allByEmpresa($empresaId);
    }

    public function obtener(int $id, int $empresaId): Compra
    {
        $compra = $this->compraRepository->findById($id);

        if (! $compra || $compra->empresa_id !== $empresaId) {
            throw new HttpException(404, 'Compra no encontrada.');
        }

        return $compra;
    }

    public function crear(array $data, int $empresaId, int $usuarioId): Compra
    {
        [$cabecera, $items] = $this->prepararDocumento($data, $empresaId, $usuarioId);

        return $this->compraRepository->create($cabecera, $items);
    }

    public function confirmar(int $id, int $empresaId, int $usuarioId): Compra
    {
        $compra = $this->obtener($id, $empresaId);

        if ($compra->estado !== 'PENDIENTE' || $compra->numero !== '') {
            throw new HttpException(409, 'Esta compra ya fue confirmada.');
        }

        if ($compra->condicion_pago === 'CONTADO') {
            $this->validarDisponible($empresaId, (float) $compra->total);
        }

        $numero = $this->numeracionService->siguienteNumero($empresaId, 'COM');

        return $this->compraRepository->confirmar($compra->id, $numero, $usuarioId);
    }

    public function registrarPago(int $id, array $data, int $empresaId, int $usuarioId): Compra
    {
        $compra = $this->obtener($id, $empresaId);

        if ($compra->condicion_pago !== 'CREDITO') {
            throw new HttpException(409, 'Solo se pueden abonar compras a crédito.');
        }

        if (! in_array($compra->estado, ['PENDIENTE', 'PARCIAL'])) {
            throw new HttpException(409, 'La compra no tiene saldo pendiente.');
        }

        $monto = (float) $data['monto'];

        if ($monto > (float) $compra->saldo_pendiente) {
            throw new HttpException(
                409,
                "El abono ({$monto}) supera el saldo pendiente ({$compra->saldo_pendiente})."
            );
        }

        $this->validarDisponible($empresaId, $monto);

        return $this->compraRepository->registrarPago($id, $monto, $empresaId, $usuarioId, $data);
    }

    public function anular(int $id, int $empresaId, int $usuarioId): Compra
    {
        $compra = $this->obtener($id, $empresaId);

        if ($compra->estado === 'ANULADA') {
            throw new HttpException(409, 'La compra ya está anulada.');
        }

        if ($compra->estado === 'PAGADA') {
            throw new HttpException(409, 'No se puede anular una compra totalmente pagada.');
        }

        return $this->compraRepository->anular($id, $empresaId, $usuarioId);
    }

    private function prepararDocumento(array $data, int $empresaId, int $usuarioId): array
    {
        $items = $data['items'];
        $subtotal = 0;

        $itemsCalculados = array_map(function ($item) use (&$subtotal) {
            $sub = round($item['cantidad'] * $item['precio_unitario'], 2);
            $subtotal += $sub;

            return [
                'item_id'         => $item['item_id'],
                'cantidad'        => (int) $item['cantidad'],
                'precio_unitario' => (float) $item['precio_unitario'],
                'subtotal'        => $sub,
            ];
        }, $items);

        $impuestos = round((float) ($data['impuestos'] ?? 0), 2);
        $total     = round($subtotal + $impuestos, 2);
        $condicion = $data['condicion_pago'] ?? 'CONTADO';

        $cabecera = [
            'empresa_id'        => $empresaId,
            'usuario_id'        => $usuarioId,
            'proveedor_id'      => $data['proveedor_id'] ?? null,
            'condicion_pago'    => $condicion,
            'fecha'             => $data['fecha'] ?? now()->toDateString(),
            'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
            'subtotal'          => $subtotal,
            'impuestos'         => $impuestos,
            'total'             => $total,
            'saldo_pendiente'   => $condicion === 'CREDITO' ? $total : 0,
            'numero'            => '',
            'estado'            => 'PENDIENTE',
            'notas'             => $data['notas'] ?? null,
        ];

        return [$cabecera, $itemsCalculados];
    }

    private function validarDisponible(int $empresaId, float $monto): void
    {
        $this->resumenService->recalcular($empresaId);

        $resumen = EmpresaResumen::find($empresaId);
        $disponible = (float) ($resumen?->balance_real ?? 0);

        if ($monto > $disponible) {
            throw new HttpException(
                422,
                "El monto supera lo disponible en caja. Disponible actual: {$disponible}."
            );
        }
    }
}
