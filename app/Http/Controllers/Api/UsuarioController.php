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
        $filters = [
            'search' => $request->query('search'),
            'rol'    => $request->query('rol'),
            'activo' => $request->query('activo'),
        ];
        
        $perPage = (int) $request->query('per_page', 20);
        
        return response()->json(
            $this->usuarioService->listarPorEmpresa(
                $request->empresa_id_ctx,
                $filters,
                $perPage,
                $request->user()->esSuperAdmin()
            )
        );
    }

    // GET /api/usuarios/{id}
    public function show(Request $request, $id): JsonResponse  // ✅ Cambiar int a $id (sin tipo)
    {
        $usuarioId = (int) $id;  // ✅ Convertir a entero
        
        return response()->json([
            'usuario' => $this->usuarioService->obtener(
                id:           $usuarioId,
                empresaId:    $request->empresa_id_ctx,
                esSuperAdmin: $request->user()->esSuperAdmin(),
            )
        ]);
    }

    // POST /api/usuarios - MODIFICAR para SUPER_ADMIN
public function store(Request $request): JsonResponse
{
    $isSuperAdmin = $request->user()->esSuperAdmin();
    
    $rules = [
        'nombres'   => ['required', 'string', 'max:120'],
        'apellidos' => ['required', 'string', 'max:120'],
        'email'     => ['required', 'email', 'max:150'],
        'password'  => ['required', 'string', 'min:8'],
        'rol'       => ['required', 'in:SUPER_ADMIN,EMPRESA_ADMIN,OPERATIVO'],
    ];
    
    // SUPER_ADMIN puede asignar empresa_id y todos los roles
    if ($isSuperAdmin) {
        $rules['empresa_id'] = ['required', 'integer', 'exists:empresas,id'];
    } else {
        // EMPRESA_ADMIN solo puede crear OPERATIVO para su empresa
        $rules['rol'] = ['required', 'in:OPERATIVO'];
    }
    
    $data = $request->validate($rules);
    
    $empresaId = $isSuperAdmin ? $data['empresa_id'] : $request->empresa_id_ctx;
    
    return response()->json([
        'usuario' => $this->usuarioService->crear(
            data:      $data,
            empresaId: $empresaId,
        )
    ], 201);
}

    // PUT /api/usuarios/{id}
    public function update(Request $request, $id): JsonResponse
    {
        $usuarioId = (int) $id;
        
        $data = $request->validate([
            'nombres'   => ['sometimes', 'string', 'max:120'],
            'apellidos' => ['sometimes', 'string', 'max:120'],
            'email'     => ['sometimes', 'email', 'max:150'],
            'rol'       => ['sometimes', 'in:EMPRESA_ADMIN,OPERATIVO'],
        ]);

        return response()->json([
            'usuario' => $this->usuarioService->actualizar(
                id:           $usuarioId,
                data:         $data,
                empresaId:    $request->empresa_id_ctx,
                esSuperAdmin: $request->user()->esSuperAdmin(),
            )
        ]);
    }

    // PATCH /api/usuarios/{id}/toggle
    public function toggle(Request $request, int $id): JsonResponse
    {
        $usuario = $this->usuarioService->toggleActivo($id, $request->empresa_id_ctx, $request->user()->rol === 'SUPER_ADMIN');
        
        // Si el usuario fue desactivado, revocar todos sus tokens
        if (!$usuario->is_activo) {
            $this->usuarioService->revocarTodosLosTokens($id, $request->empresa_id_ctx, $request->user()->rol === 'SUPER_ADMIN');
        }
        
        return response()->json([
            'usuario' => $usuario,
            'message' => $usuario->is_activo ? 'Usuario activado' : 'Usuario desactivado y sesiones cerradas'
        ]);
    }

    // PATCH /api/usuarios/{id}/password
    public function changePassword(Request $request, $id): JsonResponse
    {
        $usuarioId = (int) $id;
        
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $this->usuarioService->cambiarPassword(
            id:            $usuarioId,
            nuevaPassword: $data['password'],
            empresaId:     $request->empresa_id_ctx,
            esSuperAdmin:  $request->user()->esSuperAdmin(),
        );

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }


    
    
    // GET /api/usuarios/activos-ahora
    public function activosAhora(Request $request): JsonResponse
    {
        $minutos = (int) $request->query('minutos', 30);
        
        $usuarios = $this->usuarioService->usuariosActivosAhora($minutos, $request->empresa_id_ctx);
        
        return response()->json([
            'data' => $usuarios
        ]);
    }


    
}