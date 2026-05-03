<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    // POST /api/auth/iniciar
    public function iniciar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $preToken = $this->authService->iniciarSesion($data['email']);

        return response()->json([
            'message'   => 'Token generado. Revisa tu correo.',
            'pre_token' => $preToken, // TODO: eliminar en producción
        ]);
    }

    // POST /api/auth/verificar
    public function verificar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'token'    => ['required', 'string', 'size:6'],
            'password' => ['required', 'string'],
        ]);

        $result = $this->authService->verificarSesion(
            email:    $data['email'],
            preToken: $data['token'],
            password: $data['password'],
        );

        return response()->json($result);
    }

    // POST /api/auth/logout
    public function logout(Request $request): JsonResponse
    {
        $this->authService->cerrarSesion($request->user());
        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    // GET /api/auth/me
    public function me(Request $request): JsonResponse
    {
        $usuario = $request->user();

        return response()->json([
            'id'              => $usuario->id,
            'nombre_completo' => $usuario->nombre_completo,
            'email'           => $usuario->email,
            'rol'             => $usuario->rol,
            'empresa_id'      => $usuario->empresa_id,
        ]);
    }


      public function revokeAllSessions(Request $request): JsonResponse
    {
        $usuario = $request->user();
        
        $this->authService->revocarTodasLasSesiones($usuario);
        
        return response()->json([
            'message' => 'Todas las sesiones han sido cerradas. Debes iniciar sesión nuevamente.'
        ]);
    }
}