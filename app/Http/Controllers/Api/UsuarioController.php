<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsuarioController extends Controller
{
    public function __construct(
        private readonly UsuarioService $usuarioService,
    ) {}

    // GET /api/usuarios
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->usuarioService->listarPorEmpresa($request->empresa_id_ctx)
        );
    }

    // GET /api/usuarios/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->usuarioService->obtener(
            id:           $id,
            empresaId:    $request->empresa_id_ctx,
            esSuperAdmin: $request->user()->esSuperAdmin(),
        ));
    }

    // POST /api/usuarios
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombres'   => ['required', 'string', 'max:120'],
            'apellidos' => ['required', 'string', 'max:120'],
            'email'     => ['required', 'email', 'max:150'],
            'password'  => ['required', 'string', 'min:8'],
            'rol'       => ['required', 'in:EMPRESA_ADMIN,OPERATIVO'],
        ]);

        return response()->json($this->usuarioService->crear(
            data:      $data,
            empresaId: $request->empresa_id_ctx,
        ), 201);
    }

    // PUT /api/usuarios/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'nombres'   => ['sometimes', 'string', 'max:120'],
            'apellidos' => ['sometimes', 'string', 'max:120'],
            'email'     => ['sometimes', 'email', 'max:150'],
            'rol'       => ['sometimes', 'in:EMPRESA_ADMIN,OPERATIVO'],
        ]);

        return response()->json($this->usuarioService->actualizar(
            id:           $id,
            data:         $data,
            empresaId:    $request->empresa_id_ctx,
            esSuperAdmin: $request->user()->esSuperAdmin(),
        ));
    }

    // PATCH /api/usuarios/{id}/toggle
    public function toggle(Request $request, int $id): JsonResponse
    {
        return response()->json($this->usuarioService->toggleActivo(
            id:           $id,
            empresaId:    $request->empresa_id_ctx,
            esSuperAdmin: $request->user()->esSuperAdmin(),
        ));
    }

    // PATCH /api/usuarios/{id}/password
    public function changePassword(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $this->usuarioService->cambiarPassword(
            id:            $id,
            nuevaPassword: $data['password'],
            empresaId:     $request->empresa_id_ctx,
            esSuperAdmin:  $request->user()->esSuperAdmin(),
        );

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }
}