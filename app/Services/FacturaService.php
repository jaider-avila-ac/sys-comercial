<?php

namespace App\Services;

use App\Models\Factura;
use App\Models\Inventario;
use App\Models\InventarioMovimiento;
use App\Models\Item;
use App\Models\Cliente;
use App\Repositories\CotizacionRepository;
use App\Repositories\FacturaRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FacturaService
{
    public function __construct(
        private readonly FacturaRepository $facturaRepository,
        private readonly CotizacionRepository $cotizacionRepository,
        private readonly LineaCalculoService $calculoService,
        private readonly NumeracionService $numeracionService,
    ) {}

    public function listar(int $empresaId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->facturaRepository->paginate($empresaId, $filters, $perPage);
    }

    public function obtener(int $id, int $empresaId): Factura
    {
        $factura = $this->facturaRepository->findById($id);

        if (! $factura || (int) $factura->empresa_id !== (int) $empresaId) {
            throw new HttpException(404, 'Factura no encontrada.');
        }

        return $factura;
    }

    public function crear(array $data, int $empresaId, int $usuarioId): Factura
    {
        $this->validarCliente($data['cliente_id'], $empresaId);
        $this->validarLineas($data['lineas'] ?? []);

        [$cabecera, $lineas] = $this->prepararDocumentoCrear($data, $empresaId, $usuarioId);

        $this->validarStockDisponibleParaDocumento($lineas, $empresaId);

        $cabecera = [
            ...$cabecera,
            'numero'        => '',
            'estado'        => 'BORRADOR',
            'total_pagado'  => 0,
            'saldo'         => $cabecera['total'],
            'cotizacion_id' => $data['cotizacion_id'] ?? null,
        ];

        return $this->facturaRepository->create($cabecera, $lineas);
    }

    public function actualizar(int $id, array $data, int $empresaId, int $usuarioId): Factura
    {
        $factura = $this->obtener($id, $empresaId);

        if ($factura->estado !== 'BORRADOR') {
            throw new HttpException(409, 'Solo se pueden editar facturas en BORRADOR.');
        }

        if (isset($data['cliente_id'])) {
            $this->validarCliente($data['cliente_id'], $empresaId);
        }

        if (isset($data['lineas'])) {
            $this->validarLineas($data['lineas']);
        }

        [$cabecera, $lineas] = $this->prepararDocumentoActualizar($factura, $data, $empresaId, $usuarioId);

        $this->validarStockDisponibleParaDocumento($lineas, $empresaId);

        $cabecera['saldo'] = max(0, round($cabecera['total'] - $factura->total_pagado, 2));
        $cabecera['total_pagado'] = $factura->total_pagado;
        $cabecera['cotizacion_id'] = $factura->cotizacion_id;

        return $this->facturaRepository->updateConLineas($id, $cabecera, $lineas);
    }

    public function emitir(int $id, int $empresaId): Factura
    {
        return DB::transaction(function () use ($id, $empresaId) {

            $factura = $this->bloquearFactura($id, $empresaId);

            if ($factura->estado !== 'BORRADOR') {
                throw new HttpException(409, 'Solo se pueden emitir facturas en BORRADOR.');
            }

            if ($factura->lineas->isEmpty()) {
                throw new HttpException(422, 'La factura no tiene líneas.');
            }

            $this->procesarSalidaInventario($factura, $empresaId);

            $numero = $this->numeracionService->siguienteNumero($empresaId, 'FAC');

            $factura->update([
                'numero' => $numero,
                'estado' => 'EMITIDA',
            ]);

            return $factura->fresh(['lineas.item', 'cliente', 'usuario', 'cotizacion']);
        });
    }

    public function anular(int $id, int $empresaId): Factura
    {
        return DB::transaction(function () use ($id, $empresaId) {

            $factura = $this->bloquearFactura($id, $empresaId);

            if ($factura->estado === 'ANULADA') {
                throw new HttpException(409, 'La factura ya está anulada.');
            }

            if ((float) $factura->total_pagado > 0) {
                throw new HttpException(409, 'No se puede anular una factura con pagos registrados.');
            }

            if ($factura->estado === 'EMITIDA') {
                $this->procesarEntradaInventario($factura, $empresaId);
            }

            return $this->facturaRepository->cambiarEstado($id, 'ANULADA');
        });
    }

    public function convertirDesdeCotizacion(int $cotizacionId, int $empresaId, int $usuarioId): Factura
    {
        $cotizacion = $this->cotizacionRepository->findById($cotizacionId);

        if (! $cotizacion || $cotizacion->empresa_id !== $empresaId) {
            throw new HttpException(404, 'Cotización no encontrada.');
        }

        if ($cotizacion->estado !== 'EMITIDA') {
            throw new HttpException(409, 'Solo se pueden convertir cotizaciones en estado EMITIDA.');
        }

        $lineas = $cotizacion->lineas->map(fn($l) => [
            'item_id'            => $l->item_id,
            'descripcion_manual' => $l->descripcion_manual,
            'cantidad'           => $l->cantidad,
            'valor_unitario'     => $l->valor_unitario,
            'descuento'          => $l->descuento,
            'iva_pct'            => $l->iva_pct,
        ])->toArray();

        $this->validarStockDisponibleParaDocumento($lineas, $empresaId);

        $factura = $this->crear([
            'cliente_id'    => $cotizacion->cliente_id,
            'fecha'         => now()->toDateString(),
            'notas'         => $cotizacion->notas,
            'lineas'        => $lineas,
            'cotizacion_id' => $cotizacion->id,
        ], $empresaId, $usuarioId);

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

    // ================== MÉTODOS PRIVADOS ==================

    private function validarCliente(int $clienteId, int $empresaId): void
    {
        if (! Cliente::where('empresa_id', $empresaId)->find($clienteId)) {
            throw new HttpException(422, 'El cliente no existe o no pertenece a la empresa.');
        }
    }

    private function validarLineas(array $lineas): void
    {
        if (empty($lineas)) {
            throw new HttpException(422, 'La factura debe tener al menos una línea.');
        }
    }

    private function bloquearFactura(int $id, int $empresaId): Factura
    {
        $factura = Factura::where('id', $id)
            ->where('empresa_id', $empresaId)
            ->with(['lineas.item', 'cliente', 'usuario', 'cotizacion'])
            ->lockForUpdate()
            ->first();

        if (! $factura) {
            throw new HttpException(404, 'Factura no encontrada.');
        }

        return $factura;
    }

    private function procesarSalidaInventario(Factura $factura, int $empresaId): void
    {
        $cantidades = $this->agruparCantidadesPorItem($factura->lineas->toArray(), $empresaId);

        foreach ($cantidades as $itemId => $cantidad) {

            $inventario = Inventario::where('empresa_id', $empresaId)
                ->where('item_id', $itemId)
                ->lockForUpdate()
                ->first();

            if (! $inventario) {
                throw new HttpException(422, "Inventario no configurado para item {$itemId}");
            }

            if ($cantidad > $inventario->unidades_actuales) {
                throw new HttpException(422, "Stock insuficiente.");
            }

            $nuevo = $inventario->unidades_actuales - $cantidad;

            $inventario->update(['unidades_actuales' => $nuevo]);

            $this->registrarMovimiento($factura, $itemId, 'SALIDA', $cantidad, $nuevo, $empresaId);
        }
    }

    private function procesarEntradaInventario(Factura $factura, int $empresaId): void
    {
        $cantidades = $this->agruparCantidadesPorItem($factura->lineas->toArray(), $empresaId);

        foreach ($cantidades as $itemId => $cantidad) {

            $inventario = Inventario::where('empresa_id', $empresaId)
                ->where('item_id', $itemId)
                ->lockForUpdate()
                ->first();

            $nuevo = $inventario->unidades_actuales + $cantidad;

            $inventario->update(['unidades_actuales' => $nuevo]);

            $this->registrarMovimiento($factura, $itemId, 'ENTRADA', $cantidad, $nuevo, $empresaId);
        }
    }

    private function registrarMovimiento(Factura $factura, int $itemId, string $tipo, int $cantidad, int $resultado, int $empresaId): void
    {
        InventarioMovimiento::create([
            'empresa_id'           => $empresaId,
            'item_id'              => $itemId,
            'usuario_id'           => $factura->usuario_id,
            'tipo'                 => $tipo,
            'motivo'               => $tipo === 'SALIDA' ? 'Emisión de factura' : 'Anulación de factura',
            'referencia_tipo'      => 'FACTURA',
            'referencia_id'        => $factura->id,
            'unidades'             => $cantidad,
            'unidades_resultantes' => $resultado,
            'ocurrido_en'          => now(),
        ]);
    }
private function validarStockDisponibleParaDocumento(array $lineas, int $empresaId): void
{
    $cantidades = $this->agruparCantidadesPorItem($lineas, $empresaId);

    foreach ($cantidades as $itemId => $cantidad) {

        $inventario = Inventario::where('empresa_id', $empresaId)
            ->where('item_id', $itemId)
            ->first();

        if (! $inventario || $cantidad > $inventario->unidades_actuales) {
            throw new HttpException(422, "Stock insuficiente para item {$itemId}");
        }
    }
}

    private function agruparCantidadesPorItem(array $lineas, int $empresaId): array
    {
        $resultado = [];

        foreach ($lineas as $l) {

            $item = Item::where('empresa_id', $empresaId)->find($l['item_id']);

            if (! $item) {
                throw new HttpException(422, "El ítem {$l['item_id']} no pertenece a la empresa.");
            }

            if (! $item->controla_inventario) {
                continue;
            }

            $resultado[$item->id] = ($resultado[$item->id] ?? 0) + $l['cantidad'];
        }

        return $resultado;
    }

    private function prepararDocumentoCrear(array $data, int $empresaId, int $usuarioId): array
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

private function prepararDocumentoActualizar(Factura $factura, array $data, int $empresaId, int $usuarioId): array
{
    $lineasFuente = $data['lineas'] ?? $factura->lineas->map(fn($l) => [
        'item_id'            => $l->item_id,
        'descripcion_manual' => $l->descripcion_manual,
        'cantidad'           => $l->cantidad,
        'valor_unitario'     => $l->valor_unitario,
        'descuento'          => $l->descuento,
        'iva_pct'            => $l->iva_pct,
    ])->toArray();

    $lineasCalculadas = array_map(
        fn($l) => $this->calculoService->calcularLinea($l),
        $lineasFuente
    );

    $totales = $this->calculoService->calcularTotalesDocumento($lineasCalculadas);

    $cabecera = [
        'empresa_id' => $empresaId,
        'usuario_id' => $usuarioId,
        'cliente_id' => $data['cliente_id'] ?? $factura->cliente_id,
        'fecha'      => $data['fecha'] ?? $factura->fecha,
        'notas'      => array_key_exists('notas', $data) ? $data['notas'] : $factura->notas,
        ...$totales,
    ];

    return [$cabecera, $lineasCalculadas];
}
}