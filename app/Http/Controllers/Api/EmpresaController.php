<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EmpresaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmpresaController extends Controller
{
    public function __construct(
        private readonly EmpresaService $empresaService,
    ) {}

    // GET /api/empresas
    public function index(): JsonResponse
    {
        return response()->json($this->empresaService->listar());
    }

    // GET /api/empresa/me
    public function me(Request $request): JsonResponse
    {
        return response()->json($this->empresaService->obtener($request->empresa_id_ctx));
    }

    // GET /api/empresas/{id}
    public function show(int $id): JsonResponse
    {
        return response()->json($this->empresaService->obtener($id));
    }

    // POST /api/empresas
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre'    => ['required', 'string', 'max:150'],
            'nit'       => ['required', 'string', 'max:30'],
            'email'     => ['nullable', 'email', 'max:120'],
            'telefono'  => ['nullable', 'string', 'max:40'],
            'direccion' => ['nullable', 'string', 'max:180'],
        ]);

        return response()->json($this->empresaService->crear($data), 201);
    }

    // PUT /api/empresas/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'nombre'    => ['sometimes', 'string', 'max:150'],
            'nit'       => ['sometimes', 'string', 'max:30'],
            'email'     => ['sometimes', 'nullable', 'email', 'max:120'],
            'telefono'  => ['sometimes', 'nullable', 'string', 'max:40'],
            'direccion' => ['sometimes', 'nullable', 'string', 'max:180'],
            'is_activa' => ['sometimes', 'boolean'],
        ]);

        return response()->json($this->empresaService->actualizar($id, $data));
    }

    // DELETE /api/empresas/{id}
    public function destroy(int $id): JsonResponse
    {
        $this->empresaService->eliminar($id);
        return response()->json(['message' => 'Empresa eliminada correctamente.']);
    }

    // POST /api/empresas/{id}/logo
    public function uploadLogo(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        return response()->json($this->empresaService->subirLogo($id, $request->file('logo')));
    }

    // DELETE /api/empresas/{id}/logo
    public function deleteLogo(int $id): JsonResponse
    {
        return response()->json($this->empresaService->eliminarLogo($id));
    }
}