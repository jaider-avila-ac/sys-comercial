<?php

namespace App\Services;

use App\Models\Factura;
use App\Repositories\Contracts\CotizacionRepositoryInterface;
use App\Repositories\Contracts\FacturaRepositoryInterface;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FacturaService
{
    public function __construct(
        private readonly FacturaRepositoryInterface    $facturaRepository,
        private readonly CotizacionRepositoryInterface $cotizacionRepository,
        private readonly LineaCalculoService           $calculoService,
        private readonly NumeracionService             $numeracionService,
    ) {}

    public function listar(int $empresaId): Collection
    {
        return $this->facturaRepository->allByEmpresa($empresaId);
    }

    public function obtener(int $id, int $empresaId): Factura
    {
        $factura = $this->facturaRepository->findById($id);

        if (! $factura || $factura->empresa_id !== $empresaId) {
            throw new HttpException(404, 'Factura no encontrada.');
        }

        return $factura;
    }

    public function crear(array $data, int $empresaId, int $usuarioId): Factura
    {
        [$cabecera, $lineas] = $this->prepararDocumento($data, $empresaId, $usuarioId);

        $cabecera['numero']       = '';
        $cabecera['estado']       = 'BORRADOR';
        $cabecera['total_pagado'] = 0;
        $cabecera['saldo']        = $cabecera['total'];
        $cabecera['cotizacion_id']= $data['cotizacion_id'] ?? null;

        return $this->facturaRepository->create($cabecera, $lineas);
    }

    public function actualizar(int $id, array $data, int $empresaId, int $usuarioId): Factura
    {
        $factura = $this->obtener($id, $empresaId);

        if ($factura->estado !== 'BORRADOR') {
            throw new HttpException(409, 'Solo se pueden editar facturas en BORRADOR.');
        }

        [$cabecera, $lineas] = $this->prepararDocumento($data, $empresaId, $usuarioId);
        $cabecera['saldo'] = $cabecera['total'];

        return $this->facturaRepository->update($id, $cabecera, $lineas);
    }

    /**
     * Emitir: asigna número consecutivo y cambia estado a EMITIDA.
     */
    public function emitir(int $id, int $empresaId): Factura
    {
        $factura = $this->obtener($id, $empresaId);

        if ($factura->estado !== 'BORRADOR') {
            throw new HttpException(409, 'Solo se pueden emitir facturas en BORRADOR.');
        }

        $numero = $this->numeracionService->siguienteNumero($empresaId, 'FAC');

        return $this->facturaRepository->update($id, [
            'numero' => $numero,
            'estado' => 'EMITIDA',
        ], []);
    }

    public function anular(int $id, int $empresaId): Factura
    {
        $factura = $this->obtener($id, $empresaId);

        if ($factura->estado === 'ANULADA') {
            throw new HttpException(409, 'La factura ya está anulada.');
        }

        if ($factura->total_pagado > 0) {
            throw new HttpException(409, 'No se puede anular una factura con pagos registrados.');
        }

        return $this->facturaRepository->cambiarEstado($id, 'ANULADA');
    }

    /**
     * Convierte una cotización EMITIDA en una factura BORRADOR.
     * La cotización pasa a estado FACTURADA.
     * No se puede hacer al revés.
     */
    public function convertirDesdeCotizacion(int $cotizacionId, int $empresaId, int $usuarioId): Factura
    {
        $cotizacion = $this->cotizacionRepository->findById($cotizacionId);

        if (! $cotizacion || $cotizacion->empresa_id !== $empresaId) {
            throw new HttpException(404, 'Cotización no encontrada.');
        }

        if ($cotizacion->estado !== 'EMITIDA') {
            throw new HttpException(409, 'Solo se pueden convertir cotizaciones en estado EMITIDA.');
        }

        // Copiar líneas de la cotización a formato de factura
        $lineasCalculadas = $cotizacion->lineas->map(fn($l) => [
            'item_id'            => $l->item_id,
            'descripcion_manual' => $l->descripcion_manual,
            'cantidad'           => $l->cantidad,
            'valor_unitario'     => $l->valor_unitario,
            'descuento'          => $l->descuento,
            'iva_pct'            => $l->iva_pct,
            'iva_valor'          => $l->iva_valor,
            'total_linea'        => $l->total_linea,
        ])->toArray();

        $cabecera = [
            'empresa_id'    => $empresaId,
            'usuario_id'    => $usuarioId,
            'cliente_id'    => $cotizacion->cliente_id,
            'cotizacion_id' => $cotizacion->id,
            'numero'        => '',
            'estado'        => 'BORRADOR',
            'fecha'         => now()->toDateString(),
            'notas'         => $cotizacion->notas,
            'subtotal'      => $cotizacion->subtotal,
            'total_descuentos' => $cotizacion->total_descuentos,
            'total_iva'     => $cotizacion->total_iva,
            'total'         => $cotizacion->total,
            'total_pagado'  => 0,
            'saldo'         => $cotizacion->total,
        ];

        $factura = $this->facturaRepository->create($cabecera, $lineasCalculadas);

        // Marcar cotización como facturada
        $this->cotizacionRepository->cambiarEstado($cotizacionId, 'FACTURADA');

        return $factura;
    }

    public function eliminar(int $id, int $empresaId): void
    {
        $factura = $this->obtener($id, $empresaId);

        if ($factura->estado !== 'BORRADOR') {
            throw new HttpException(409, 'Solo se pueden eliminar facturas en BORRADOR.');
        }

        $this->facturaRepository->delete($id);
    }

    // ── Privado ───────────────────────────────────────────────────────────────

    private function prepararDocumento(array $data, int $empresaId, int $usuarioId): array
    {
        $lineasCalculadas = array_map(
            fn($l) => $this->calculoService->calcularLinea($l),
            $data['lineas']
        );

        $totales = $this->calculoService->calcularTotalesDocumento($lineasCalculadas);

        $cabecera = [
            'empresa_id' => $empresaId,
            'usuario_id' => $usuarioId,
            'cliente_id' => $data['cliente_id'],
            'fecha'      => $data['fecha'],
            'notas'      => $data['notas'] ?? null,
            ...$totales,
        ];

        return [$cabecera, $lineasCalculadas];
    }
}
