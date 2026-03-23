<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProveedorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function __construct(
        private readonly ProveedorService $proveedorService,
    ) {}

    // GET /api/proveedores
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->proveedorService->listar($request->empresa_id_ctx)
        );
    }

    // GET /api/proveedores/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->proveedorService->obtener($id, $request->empresa_id_ctx)
        );
    }

    // POST /api/proveedores
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre'              => ['required', 'string', 'max:150'],
            'nit'                 => ['nullable', 'string', 'max:30'],
            'telefono'            => ['nullable', 'string', 'max:30'],
            'email'               => ['nullable', 'email', 'max:100'],
            'contacto'            => ['nullable', 'string', 'max:100'],
            'direccion'           => ['nullable', 'string', 'max:200'],
            'ciudad'              => ['nullable', 'string', 'max:80'],
            'tiempo_entrega_dias' => ['nullable', 'integer', 'min:0'],
            'notas'               => ['nullable', 'string'],
        ]);

        return response()->json(
            $this->proveedorService->crear($data, $request->empresa_id_ctx),
            201
        );
    }

    // PUT /api/proveedores/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'nombre'              => ['sometimes', 'string', 'max:150'],
            'nit'                 => ['sometimes', 'nullable', 'string', 'max:30'],
            'telefono'            => ['sometimes', 'nullable', 'string', 'max:30'],
            'email'               => ['sometimes', 'nullable', 'email', 'max:100'],
            'contacto'            => ['sometimes', 'nullable', 'string', 'max:100'],
            'direccion'           => ['sometimes', 'nullable', 'string', 'max:200'],
            'ciudad'              => ['sometimes', 'nullable', 'string', 'max:80'],
            'tiempo_entrega_dias' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'notas'               => ['sometimes', 'nullable', 'string'],
        ]);

        return response()->json(
            $this->proveedorService->actualizar($id, $data, $request->empresa_id_ctx)
        );
    }

    // PATCH /api/proveedores/{id}/toggle
    public function toggle(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->proveedorService->toggleActivo($id, $request->empresa_id_ctx)
        );
    }

    // DELETE /api/proveedores/{id}
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->proveedorService->eliminar($id, $request->empresa_id_ctx);
        return response()->json(['message' => 'Proveedor eliminado correctamente.']);
    }
}