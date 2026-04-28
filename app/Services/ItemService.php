<?php

namespace App\Services;

use App\Models\Inventario;
use App\Models\InventarioMovimiento;
use App\Models\Item;
use App\Repositories\ItemRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ItemService
{
    public function __construct(
        private readonly ItemRepository $itemRepository,
        private readonly CompraService $compraService,
    ) {}

    public function listar(int $empresaId): Collection
    {
        return $this->itemRepository->allByEmpresa($empresaId);
    }

    public function paginar(int $empresaId, int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->itemRepository->paginateByEmpresa($empresaId, $perPage, $filters);
    }

    public function obtener(int $id, int $empresaId): Item
    {
        $item = $this->itemRepository->findById($id);

        if (! $item || $item->empresa_id !== $empresaId) {
            throw new HttpException(404, 'Ítem no encontrado.');
        }

        return $item;
    }

    public function crear(array $data, int $empresaId, ?int $usuarioId = null): array
    {
        return DB::transaction(function () use ($data, $empresaId, $usuarioId) {
            $controlaInventario = (bool) ($data['controla_inventario'] ?? false);
            $tipo = $data['tipo'];
            $cantidadInicial = (int) ($data['cantidad_inicial'] ?? 0);
            $precioCompra = (float) ($data['precio_compra'] ?? 0);
            $condicionPago = $data['condicion_pago'] ?? 'LIBRE';
            $fecha = $data['fecha'] ?? now()->toDateString();
            $notas = $data['notas'] ?? null;
            $abonoInicial = (float) ($data['abono_inicial'] ?? 0);

            if ($tipo === 'SERVICIO') {
                $controlaInventario = false;
                $cantidadInicial = 0;
            }

            if ($cantidadInicial > 0 && ! $controlaInventario) {
                throw new HttpException(422, 'No se puede cargar inventario a un ítem que no controla inventario.');
            }

            if ($cantidadInicial > 0 && $tipo === 'SERVICIO') {
                throw new HttpException(422, 'Un servicio no puede tener cantidad inicial en inventario.');
            }

            if ($cantidadInicial > 0 && $precioCompra < 0) {
                throw new HttpException(422, 'El precio de compra no puede ser negativo.');
            }

            if (! in_array($condicionPago, ['CONTADO', 'CREDITO', 'LIBRE'])) {
                throw new HttpException(422, 'La condición de pago es inválida.');
            }

            if ($abonoInicial < 0) {
                throw new HttpException(422, 'El abono inicial no puede ser negativo.');
            }

            $item = $this->itemRepository->create([
                'nombre'                => $data['nombre'],
                'tipo'                  => $tipo,
                'descripcion'           => $data['descripcion'] ?? null,
                'precio_compra'         => $precioCompra,
                'precio_venta_sugerido' => $data['precio_venta_sugerido'] ?? 0,
                'controla_inventario'   => $controlaInventario,
                'unidad'                => $data['unidad'] ?? 'UND',
                'proveedor_id'          => $data['proveedor_id'] ?? null,
                'empresa_id'            => $empresaId,
                'is_activo'             => array_key_exists('is_activo', $data) ? (bool) $data['is_activo'] : true,
            ]);

            if ($controlaInventario) {
                Inventario::create([
                    'empresa_id'        => $empresaId,
                    'item_id'           => $item->id,
                    'unidades_actuales' => 0,
                    'unidades_minimas'  => $data['unidades_minimas'] ?? 0,
                ]);
            }

            if ($cantidadInicial <= 0) {
                return [
                    'modo'    => 'ITEM_SIMPLE',
                    'item'    => $item->fresh(['inventario', 'proveedor']),
                    'compra'  => null,
                    'message' => 'Ítem creado correctamente.',
                ];
            }

            if ($condicionPago === 'LIBRE') {
                $inventario = $item->inventario;

                $nuevasUnidades = (float) $inventario->unidades_actuales + $cantidadInicial;

                $inventario->update([
                    'unidades_actuales' => $nuevasUnidades,
                ]);

                InventarioMovimiento::create([
                    'empresa_id'           => $empresaId,
                    'item_id'              => $item->id,
                    'usuario_id'           => $usuarioId,
                    'tipo'                 => 'ENTRADA',
                    'motivo'               => 'Carga libre inicial',
                    'referencia_tipo'      => 'ITEM',
                    'referencia_id'        => $item->id,
                    'unidades'             => $cantidadInicial,
                    'unidades_resultantes' => $nuevasUnidades,
                    'ocurrido_en'          => now(),
                ]);

                return [
                    'modo'    => 'ITEM_CON_CARGA_LIBRE',
                    'item'    => $item->fresh(['inventario', 'proveedor']),
                    'compra'  => null,
                    'message' => 'Ítem creado y cargado al inventario sin afectar caja.',
                ];
            }

            $compra = $this->compraService->crear([
                'fecha'             => $fecha,
                'proveedor_id'      => $data['proveedor_id'] ?? null,
                'condicion_pago'    => $condicionPago,
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'impuestos'         => $data['impuestos'] ?? 0,
                'notas'             => $notas,
                'items' => [
                    [
                        'item_id'         => $item->id,
                        'cantidad'        => $cantidadInicial,
                        'precio_unitario' => $precioCompra,
                    ]
                ],
            ], $empresaId, $usuarioId);

            $compra = $this->compraService->confirmar($compra->id, $empresaId, $usuarioId);

            if ($condicionPago === 'CREDITO' && $abonoInicial > 0) {
                if ($abonoInicial > (float) $compra->total) {
                    throw new HttpException(422, 'El abono inicial no puede ser mayor al total de la compra.');
                }

                $compra = $this->compraService->registrarPago($compra->id, [
                    'monto'       => $abonoInicial,
                    'fecha'       => $fecha,
                    'medio_pago'  => $data['medio_pago'] ?? 'EFECTIVO',
                    'descripcion' => 'Abono inicial de compra del ítem ' . $item->nombre,
                    'notas'       => $notas,
                ], $empresaId, $usuarioId);
            }

            return [
                'modo'    => 'ITEM_CON_CARGA_' . $condicionPago,
                'item'    => $item->fresh(['inventario', 'proveedor']),
                'compra'  => $compra->fresh(['items.item', 'proveedor', 'egresos']),
                'message' => 'Ítem creado y cargado correctamente.',
            ];
        });
    }

    public function actualizar(int $id, array $data, int $empresaId): Item
    {
        $item = $this->obtener($id, $empresaId);

        $controlaAhora = array_key_exists('controla_inventario', $data)
            ? (bool) $data['controla_inventario']
            : (bool) $item->controla_inventario;

        if (($data['tipo'] ?? $item->tipo) === 'SERVICIO') {
            $controlaAhora = false;
        }

        if ($controlaAhora && ! $item->inventario) {
            Inventario::create([
                'empresa_id'        => $empresaId,
                'item_id'           => $item->id,
                'unidades_actuales' => 0,
                'unidades_minimas'  => $data['unidades_minimas'] ?? 0,
            ]);
        }

        if ($item->inventario && array_key_exists('unidades_minimas', $data)) {
            $item->inventario->update([
                'unidades_minimas' => $data['unidades_minimas'] ?? 0,
            ]);
        }

        $payload = collect($data)
            ->except('unidades_minimas')
            ->toArray();

        $payload['controla_inventario'] = $controlaAhora;

        if (($payload['tipo'] ?? $item->tipo) === 'SERVICIO') {
            $payload['controla_inventario'] = false;
        }

        if (empty($payload['unidad']) && !array_key_exists('unidad', $payload)) {
            $payload['unidad'] = $item->unidad ?? 'UND';
        }

        return $this->itemRepository->update($id, $payload);
    }

    public function toggleActivo(int $id, int $empresaId): Item
    {
        $this->obtener($id, $empresaId);

        return $this->itemRepository->toggleActivo($id);
    }

    public function eliminar(int $id, int $empresaId): void
    {
        $this->obtener($id, $empresaId);
        $this->itemRepository->delete($id);
    }
}