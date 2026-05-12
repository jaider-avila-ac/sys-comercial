<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    // POST /api/auth/login
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $result = $this->authService->login($data['email'], $data['password']);

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

    // POST /api/auth/revoke-all-sessions
    public function revokeAllSessions(Request $request): JsonResponse
    {
        $this->authService->revocarTodasLasSesiones($request->user());

        return response()->json([
            'message' => 'Todas las sesiones han sido cerradas. Debes iniciar sesión nuevamente.',
        ]);
    }
}
