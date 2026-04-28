<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function __construct(
        private readonly ItemService $itemService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 15);

        $filters = [
            'search'               => $request->get('search'),
            'tipo'                 => $request->get('tipo'),
            'controla_inventario'  => $request->has('controla_inventario')
                ? filter_var($request->get('controla_inventario'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null,
        ];

        $filters = array_filter($filters, static function ($value) {
            return $value !== null && $value !== '';
        });

        $items = $this->itemService->paginar(
            $request->empresa_id_ctx,
            $perPage,
            $filters
        );

        return response()->json($items);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->itemService->obtener($id, $request->empresa_id_ctx)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre'                => ['required', 'string', 'max:180'],
            'tipo'                  => ['required', 'in:PRODUCTO,SERVICIO,INSUMO'],
            'descripcion'           => ['nullable', 'string'],
            'precio_compra'         => ['nullable', 'numeric', 'min:0'],
            'precio_venta_sugerido' => ['nullable', 'numeric', 'min:0'],
            'controla_inventario'   => ['nullable', 'boolean'],
            'unidad'                => ['nullable', 'string', 'max:30'],
            'proveedor_id'          => ['nullable', 'integer'],
            'unidades_minimas'      => ['nullable', 'integer', 'min:0'],
            'cantidad_inicial'      => ['nullable', 'integer', 'min:0'],
            'fecha'                 => ['nullable', 'date'],
            'condicion_pago'        => ['nullable', 'in:CONTADO,CREDITO,LIBRE'],
            'fecha_vencimiento'     => ['nullable', 'date', 'after_or_equal:fecha'],
            'abono_inicial'         => ['nullable', 'numeric', 'min:0'],
            'medio_pago'            => ['nullable', 'string', 'max:50'],
            'impuestos'             => ['nullable', 'numeric', 'min:0'],
            'notas'                 => ['nullable', 'string'],
            'is_activo'             => ['nullable', 'boolean'],
        ]);

        return response()->json(
            $this->itemService->crear(
                $data,
                $request->empresa_id_ctx,
                $request->user()->id,
            ),
            201
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'nombre'                => ['sometimes', 'string', 'max:180'],
            'tipo'                  => ['sometimes', 'in:PRODUCTO,SERVICIO,INSUMO'],
            'descripcion'           => ['sometimes', 'nullable', 'string'],
            'precio_compra'         => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'precio_venta_sugerido' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'controla_inventario'   => ['sometimes', 'boolean'],
            'unidad'                => ['sometimes', 'nullable', 'string', 'max:30'],
            'proveedor_id'          => ['sometimes', 'nullable', 'integer'],
            'unidades_minimas'      => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_activo'             => ['sometimes', 'boolean'],
        ]);

        return response()->json(
            $this->itemService->actualizar($id, $data, $request->empresa_id_ctx)
        );
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->itemService->toggleActivo($id, $request->empresa_id_ctx)
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->itemService->eliminar($id, $request->empresa_id_ctx);

        return response()->json([
            'message' => 'Ítem eliminado correctamente.',
        ]);
    }
}