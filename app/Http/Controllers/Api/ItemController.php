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

    // GET /api/items
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->itemService->listar($request->empresa_id_ctx)
        );
    }

    // GET /api/items/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->itemService->obtener($id, $request->empresa_id_ctx)
        );
    }

    // POST /api/items
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre'                => ['required', 'string', 'max:180'],
            'tipo'                  => ['required', 'in:PRODUCTO,SERVICIO,INSUMO'],
            'descripcion'           => ['nullable', 'string'],
            'precio_compra'         => ['nullable', 'numeric', 'min:0'],
            'precio_venta_sugerido' => ['nullable', 'numeric', 'min:0'],
            'controla_inventario'   => ['boolean'],
            'unidad'                => ['nullable', 'string', 'max:30'],
            'proveedor_id'          => ['nullable', 'integer'],
            'unidades_minimas'      => ['nullable', 'integer', 'min:0'],
        ]);

        return response()->json(
            $this->itemService->crear($data, $request->empresa_id_ctx),
            201
        );
    }

    // PUT /api/items/{id}
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
        ]);

        return response()->json(
            $this->itemService->actualizar($id, $data, $request->empresa_id_ctx)
        );
    }

    // PATCH /api/items/{id}/toggle
    public function toggle(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->itemService->toggleActivo($id, $request->empresa_id_ctx)
        );
    }

    // DELETE /api/items/{id}
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->itemService->eliminar($id, $request->empresa_id_ctx);
        return response()->json(['message' => 'Ítem eliminado correctamente.']);
    }
}