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
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search', '');
        $perPage = (int) $request->query('per_page', 20);
        
        $empresas = $this->empresaService->listar($search, $perPage);
        
        return response()->json($empresas);
    }

    // GET /api/empresa/me
    public function me(Request $request): JsonResponse
    {
        $empresa = $this->empresaService->obtener($request->empresa_id_ctx);
        
        return response()->json([
            'empresa' => $empresa
        ]);
    }

    // GET /api/empresas/{id}
    public function show(int $id): JsonResponse
    {
        $empresa = $this->empresaService->obtener($id);
        
        return response()->json([
            'empresa' => $empresa
        ]);
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

        $empresa = $this->empresaService->crear($data);
        
        return response()->json([
            'empresa' => $empresa
        ], 201);
    }

    // PUT /api/empresas/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'nombre'     => ['sometimes', 'string', 'max:150'],
            'nit'        => ['sometimes', 'string', 'max:30'],
            'matricula'  => ['sometimes', 'nullable', 'string', 'max:80'],
            'email'      => ['sometimes', 'nullable', 'email', 'max:120'],
            'pagina_web' => ['sometimes', 'nullable', 'string', 'max:120'],
            'telefono'   => ['sometimes', 'nullable', 'string', 'max:40'],
            'direccion'  => ['sometimes', 'nullable', 'string', 'max:180'],
            'is_activa'  => ['sometimes', 'boolean'],
        ]);

        $empresa = $this->empresaService->actualizar($id, $data);

        return response()->json([
            'empresa' => $empresa
        ]);
    }

    // PUT /api/empresa/tema
    public function guardarTema(Request $request): JsonResponse
    {
        $data = $request->validate([
            'doc_tema'  => ['required', 'string', 'in:1,2,3,4'],
            'doc_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $empresa = $this->empresaService->actualizar($request->empresa_id_ctx, $data);

        return response()->json(['empresa' => $empresa]);
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

        $empresa = $this->empresaService->subirLogo($id, $request->file('logo'));
        
        return response()->json([
            'empresa' => $empresa
        ]);
    }

    // DELETE /api/empresas/{id}/logo
    public function deleteLogo(int $id): JsonResponse
    {
        $empresa = $this->empresaService->eliminarLogo($id);
        
        return response()->json([
            'empresa' => $empresa
        ]);
    }
}