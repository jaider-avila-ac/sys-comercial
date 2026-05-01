<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ClienteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function __construct(
        private readonly ClienteService $clienteService,
    ) {}

     public function index(Request $request): JsonResponse
    {
        $search = $request->get('search', '');
        $search = is_string($search) ? $search : '';
        
        // Obtener parámetros de paginación
        $perPage = (int) $request->get('per_page', 10);
        $page = (int) $request->get('page', 1);
        
        return response()->json(
            $this->clienteService->listar(
                $request->empresa_id_ctx,
                $search,
                $perPage,
                $page
            )
        );
    }

    // GET /api/clientes/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json([
            'cliente' => $this->clienteService->obtener($id, $request->empresa_id_ctx),
        ]);
    }

    // POST /api/clientes
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre_razon_social' => ['required', 'string', 'max:160'],
            'contacto'            => ['nullable', 'string', 'max:120'],
            'tipo_documento'      => ['nullable', 'in:CC,NIT,CE,PAS,OTRO'],
            'num_documento'       => ['nullable', 'string', 'max:40'],
            'email'               => ['nullable', 'email', 'max:150'],
            'telefono'            => ['nullable', 'string', 'max:40'],
            'empresa'             => ['nullable', 'string', 'max:160'],
            'direccion'           => ['nullable', 'string', 'max:180'],
        ]);

        return response()->json([
            'cliente' => $this->clienteService->crear($data, $request->empresa_id_ctx),
        ], 201);
    }

    // PUT /api/clientes/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'nombre_razon_social' => ['sometimes', 'string', 'max:160'],
            'contacto'            => ['sometimes', 'nullable', 'string', 'max:120'],
            'tipo_documento'      => ['sometimes', 'nullable', 'in:CC,NIT,CE,PAS,OTRO'],
            'num_documento'       => ['sometimes', 'nullable', 'string', 'max:40'],
            'email'               => ['sometimes', 'nullable', 'email', 'max:150'],
            'telefono'            => ['sometimes', 'nullable', 'string', 'max:40'],
            'empresa'             => ['sometimes', 'nullable', 'string', 'max:160'],
            'direccion'           => ['sometimes', 'nullable', 'string', 'max:180'],
        ]);

        return response()->json([
            'cliente' => $this->clienteService->actualizar($id, $data, $request->empresa_id_ctx),
        ]);
    }

    // PATCH /api/clientes/{id}/toggle
    public function toggle(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->clienteService->toggleActivo($id, $request->empresa_id_ctx)
        );
    }

    // DELETE /api/clientes/{id}
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->clienteService->eliminar($id, $request->empresa_id_ctx);
        return response()->json(['message' => 'Cliente eliminado correctamente.']);
    }
}