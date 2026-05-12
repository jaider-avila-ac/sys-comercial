<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
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

    // ✅ AGREGAR ESTE MÉTODO
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
            ->except(['unidades_minimas', 'proveedor_ids'])
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

    public function eliminar(int $id, int $empresaId, int $usuarioId): void
    {
        $item = $this->obtener($id, $empresaId);

        // Si el ítem tiene CUALQUIER historial, solo se desactiva — nunca se elimina físicamente
        $tieneHistorial =
            DB::table('compra_items')->where('item_id', $id)->exists()
            || DB::table('inventario_movimientos')->where('item_id', $id)->exists()
            || DB::table('factura_lineas')->where('item_id', $id)->exists();

        if ($tieneHistorial) {
            $this->itemRepository->update($id, ['is_activo' => false]);
            return;
        }

        // Ítem sin historial: eliminación física segura
        DB::transaction(function () use ($item, $id) {
            if ($item->inventario) {
                $item->inventario->delete();
            }
            $this->itemRepository->delete($id);
        });
    }

    public function crear(array $data, int $empresaId, ?int $usuarioId = null, ?UploadedFile $archivo = null): array
    {
        return DB::transaction(function () use ($data, $empresaId, $usuarioId, $archivo) {
            $controlaInventario = (bool) ($data['controla_inventario'] ?? false);
            $tipo               = $data['tipo'];
            $cantidadInicial    = (int) ($data['cantidad_inicial'] ?? 0);
            $precioCompra       = (float) ($data['precio_compra'] ?? 0);
            $condicionPago      = $data['condicion_pago'] ?? 'LIBRE';
            $fecha              = $data['fecha'] ?? now()->toDateString();
            $notas              = $data['notas'] ?? null;
            $abonoInicial       = (float) ($data['abono_inicial'] ?? 0);
            $medioPago          = $data['medio_pago'] ?? 'EFECTIVO';
            $impuestos          = (float) ($data['impuestos'] ?? 0);
            $fechaVencimiento   = $data['fecha_vencimiento'] ?? null;

            if ($tipo === 'SERVICIO') {
                $controlaInventario = false;
                $cantidadInicial = 0;
            }

            if ($cantidadInicial > 0 && !$controlaInventario) {
                throw new HttpException(422, 'No se puede cargar inventario a un ítem que no controla inventario.');
            }

            if ($cantidadInicial > 0 && $tipo === 'SERVICIO') {
                throw new HttpException(422, 'Un servicio no puede tener cantidad inicial en inventario.');
            }

            $proveedorId = $data['proveedor_id'] ?? null;

            // Crear el ítem
            $item = $this->itemRepository->create([
                'nombre'                => $data['nombre'],
                'tipo'                  => $tipo,
                'descripcion'           => $data['descripcion'] ?? null,
                'precio_compra'         => $precioCompra,
                'precio_venta_sugerido' => $data['precio_venta_sugerido'] ?? 0,
                'controla_inventario'   => $controlaInventario,
                'unidad'                => $data['unidad'] ?? 'UND',
                'proveedor_id'          => $proveedorId,
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

            // Sin cantidad inicial
            if ($cantidadInicial <= 0) {
                return [
                    'modo'    => 'ITEM_SIMPLE',
                    'item'    => $item->fresh(['inventario', 'proveedor']),
                    'compra'  => null,
                    'message' => 'Ítem creado correctamente.',
                ];
            }

            // Carga libre
            if ($condicionPago === 'LIBRE') {
                $inventario = $item->inventario;
                $nuevasUnidades = (float) $inventario->unidades_actuales + $cantidadInicial;
                $inventario->update(['unidades_actuales' => $nuevasUnidades]);

                InventarioMovimiento::create([
                    'empresa_id'           => $empresaId,
                    'item_id'              => $item->id,
                    'usuario_id'           => $usuarioId,
                    'tipo'                 => 'ENTRADA',
                    'subtipo'              => 'COMPRA_LIBRE',
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

            // Preparar archivo
            $archivoData = null;
            if ($archivo) {
                $path = $archivo->store('comprobantes/egresos', 'public');
                $archivoData = [
                    'path' => $path,
                    'mime' => $archivo->getMimeType(),
                    'nombre' => $archivo->getClientOriginalName(),
                ];
            }

            // Crear compra
            $compra = $this->compraService->crear([
                'fecha'             => $fecha,
                'proveedor_id'      => $proveedorId,
                'condicion_pago'    => $condicionPago,
                'fecha_vencimiento' => $fechaVencimiento,
                'impuestos'         => $impuestos,
                'notas'             => $notas,
                'items' => [
                    [
                        'item_id'         => $item->id,
                        'cantidad'        => $cantidadInicial,
                        'precio_unitario' => $precioCompra,
                    ],
                ],
            ], $empresaId, $usuarioId);

            // Confirmar compra
            $compra = $this->compraService->confirmar(
                $compra->id,
                $empresaId,
                $usuarioId,
                ($condicionPago === 'CONTADO') ? $archivoData : null
            );

            // Para CRÉDITO con abono inicial
            if ($condicionPago === 'CREDITO' && $abonoInicial > 0) {
                if ($abonoInicial > (float) $compra->total) {
                    throw new HttpException(422, 'El abono inicial no puede ser mayor al total de la compra.');
                }

                $this->compraService->registrarPago($compra->id, [
                    'monto'       => $abonoInicial,
                    'fecha'       => $fecha,
                    'medio_pago'  => $medioPago,
                    'descripcion' => "Abono inicial compra {$compra->numero} - {$item->nombre}",
                    'notas'       => $notas,
                    ...(isset($archivoData) ? [
                        'archivo_path'   => $archivoData['path'],
                        'archivo_mime'   => $archivoData['mime'],
                        'archivo_nombre' => $archivoData['nombre'],
                    ] : []),
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

    public function listarCompras(int $itemId, int $empresaId): array
    {
        $this->obtener($itemId, $empresaId);
        return $this->compraService->listarPorItem($itemId, $empresaId);
    }

    public function editarCompra(int $itemId, int $compraId, array $data, int $empresaId, int $usuarioId): array
    {
        $item = $this->obtener($itemId, $empresaId);

        $compra = $this->compraService->obtener($compraId, $empresaId);

        $compraItem = $compra->items->firstWhere('item_id', $itemId);
        if (! $compraItem) {
            throw new HttpException(404, 'Este ítem no pertenece a esa compra.');
        }

        $nuevaCantidad = (int) $data['cantidad'];
        $nuevoPrecio   = isset($data['precio_unitario'])
            ? (float) $data['precio_unitario']
            : (float) $compraItem->precio_unitario;
        $motivo = $data['motivo'] ?? null;

        if ($nuevaCantidad <= 0) {
            throw new HttpException(422, 'La cantidad debe ser mayor a cero.');
        }

        // Minimum quantity: can't go below what's already been consumed from this purchase
        $stockActual      = (float) ($item->inventario?->unidades_actuales ?? 0);
        $cantidadOriginal = (int) $compraItem->cantidad;
        $cantidadMinima   = max(1, $cantidadOriginal - (int) $stockActual);

        if ($nuevaCantidad < $cantidadMinima) {
            throw new HttpException(
                422,
                "No se puede reducir a {$nuevaCantidad} unidades. " .
                "Mínimo permitido: {$cantidadMinima} " .
                "(stock actual: {$stockActual}, compra original: {$cantidadOriginal})."
            );
        }

        return $this->compraService->ajustarCompraItem(
            $compraId,
            $itemId,
            $nuevaCantidad,
            $nuevoPrecio,
            $motivo,
            $empresaId,
            $usuarioId
        );
    }

    public function registrarMovimiento(int $id, array $data, int $empresaId, ?int $usuarioId, ?UploadedFile $archivo = null): array
    {
        $item = $this->obtener($id, $empresaId);

        if (!$item->controla_inventario) {
            throw new HttpException(422, 'Este ítem no controla inventario.');
        }

        $inventario = $item->inventario;
        if (!$inventario) {
            throw new HttpException(422, 'El ítem no tiene inventario configurado.');
        }

        $accion   = $data['accion'];
        $cantidad = (int) $data['cantidad'];
        $motivo   = $data['motivo'] ?? null;

        if ($cantidad <= 0) {
            throw new HttpException(422, 'La cantidad debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($item, $inventario, $data, $accion, $cantidad, $motivo, $empresaId, $usuarioId, $archivo) {

            if ($accion === 'RETIRAR') {
                if ($cantidad > (float) $inventario->unidades_actuales) {
                    throw new HttpException(422, "Stock insuficiente. Disponible: {$inventario->unidades_actuales}");
                }
                $nuevas = (float) $inventario->unidades_actuales - $cantidad;
                $inventario->update(['unidades_actuales' => $nuevas]);

                InventarioMovimiento::create([
                    'empresa_id'           => $empresaId,
                    'item_id'              => $item->id,
                    'usuario_id'           => $usuarioId,
                    'tipo'                 => 'SALIDA',
                    'subtipo'              => 'RETIRO_MANUAL',
                    'motivo'               => $motivo ?? 'Retiro manual',
                    'referencia_tipo'      => 'AJUSTE',
                    'referencia_id'        => null,
                    'unidades'             => $cantidad,
                    'unidades_resultantes' => $nuevas,
                    'ocurrido_en'          => now(),
                ]);

                return [
                    'modo'    => 'RETIRO',
                    'item'    => $item->fresh(['inventario', 'proveedor']),
                    'compra'  => null,
                    'message' => 'Retiro de inventario registrado.',
                ];
            }

            // AGREGAR
            $condicionPago   = $data['condicion_pago'] ?? 'LIBRE';
            $proveedorId     = isset($data['proveedor_id']) ? (int) $data['proveedor_id'] : null;

            if (in_array($condicionPago, ['CONTADO', 'CREDITO']) && ! $proveedorId) {
                throw new HttpException(422, 'El proveedor es obligatorio para compras a contado o crédito.');
            }
            $fecha           = $data['fecha'] ?? now()->toDateString();
            $precioUnitario  = (float) ($data['precio_unitario'] ?? $item->precio_compra ?? 0);
            $impuestos       = (float) ($data['impuestos'] ?? 0);
            $abonoInicial    = (float) ($data['abono_inicial'] ?? 0);
            $medioPago       = $data['medio_pago'] ?? 'EFECTIVO';
            $fechaVencimiento = $data['fecha_vencimiento'] ?? null;

            if ($condicionPago === 'LIBRE') {
                $nuevas = (float) $inventario->unidades_actuales + $cantidad;
                $inventario->update(['unidades_actuales' => $nuevas]);

                InventarioMovimiento::create([
                    'empresa_id'           => $empresaId,
                    'item_id'              => $item->id,
                    'usuario_id'           => $usuarioId,
                    'tipo'                 => 'ENTRADA',
                    'subtipo'              => 'COMPRA_LIBRE',
                    'motivo'               => $motivo ?? 'Entrada manual libre',
                    'referencia_tipo'      => 'AJUSTE',
                    'referencia_id'        => null,
                    'unidades'             => $cantidad,
                    'unidades_resultantes' => $nuevas,
                    'ocurrido_en'          => now(),
                ]);

                if ($proveedorId) {
                    $item->update(['proveedor_id' => $proveedorId]);
                }

                return [
                    'modo'    => 'ENTRADA_LIBRE',
                    'item'    => $item->fresh(['inventario', 'proveedor']),
                    'compra'  => null,
                    'message' => 'Entrada registrada (sin afectar caja).',
                ];
            }

            // CONTADO o CREDITO — crear compra
            $archivoData = null;
            if ($archivo) {
                $path = $archivo->store('comprobantes/egresos', 'public');
                $archivoData = [
                    'path'   => $path,
                    'mime'   => $archivo->getMimeType(),
                    'nombre' => $archivo->getClientOriginalName(),
                ];
            }

            $compra = $this->compraService->crear([
                'fecha'             => $fecha,
                'proveedor_id'      => $proveedorId,
                'condicion_pago'    => $condicionPago,
                'fecha_vencimiento' => $fechaVencimiento,
                'impuestos'         => $impuestos,
                'notas'             => $motivo ?? "Reposición stock — {$item->nombre}",
                'items'             => [[
                    'item_id'         => $item->id,
                    'cantidad'        => $cantidad,
                    'precio_unitario' => $precioUnitario,
                ]],
            ], $empresaId, $usuarioId);

            $compra = $this->compraService->confirmar(
                $compra->id,
                $empresaId,
                $usuarioId,
                $condicionPago === 'CONTADO' ? $archivoData : null
            );

            if ($condicionPago === 'CREDITO' && $abonoInicial > 0) {
                if ($abonoInicial > (float) $compra->total) {
                    throw new HttpException(422, 'El abono inicial no puede ser mayor al total.');
                }
                $this->compraService->registrarPago($compra->id, [
                    'monto'       => $abonoInicial,
                    'fecha'       => $fecha,
                    'medio_pago'  => $medioPago,
                    'descripcion' => "Abono inicial compra {$compra->numero} — {$item->nombre}",
                    'notas'       => $motivo ?? null,
                ], $empresaId, $usuarioId);
            }

            if ($proveedorId) {
                $item->update(['proveedor_id' => $proveedorId]);
            }

            return [
                'modo'    => 'ENTRADA_' . $condicionPago,
                'item'    => $item->fresh(['inventario', 'proveedor']),
                'compra'  => $compra->fresh(['items.item', 'proveedor', 'egresos']),
                'message' => 'Entrada de inventario y compra registradas correctamente.',
            ];
        });
    }
}