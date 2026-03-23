<?php

namespace App\Services;

use App\Models\LoginLog;
use App\Models\Personal_access_tokens;
use App\Models\SesionLog;
use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SesionService
{
    /**
     * Registrar inicio de sesión exitoso.
     * Llamado desde AuthService::verificarSesion().
     */
    public function registrarLogin(Usuario $usuario, string $ip, string $userAgent): void
    {
        // Login log
        LoginLog::create([
            'empresa_id'  => $usuario->empresa_id,
            'usuario_id'  => $usuario->id,
            'ip'          => $ip,
            'user_agent'  => substr($userAgent, 0, 255),
            'evento'      => 'LOGIN',
            'ocurrido_en' => now(),
        ]);

        // Sesión activa
        SesionLog::create([
            'empresa_id'  => $usuario->empresa_id,
            'usuario_id'  => $usuario->id,
            'ip'          => $ip,
            'user_agent'  => substr($userAgent, 0, 300),
            'iniciado_en' => now(),
        ]);
    }

    /**
     * Registrar intento de login fallido.
     * Llamado desde AuthService cuando la contraseña es incorrecta.
     */
    public function registrarLoginFallido(int $empresaId, int $usuarioId, string $ip, string $userAgent): void
    {
        LoginLog::create([
            'empresa_id'  => $empresaId,
            'usuario_id'  => $usuarioId,
            'ip'          => $ip,
            'user_agent'  => substr($userAgent, 0, 255),
            'evento'      => 'LOGIN_FAIL',
            'ocurrido_en' => now(),
        ]);
    }

    /**
     * Registrar logout.
     */
    public function registrarLogout(Usuario $usuario, string $ip, string $userAgent): void
    {
        LoginLog::create([
            'empresa_id'  => $usuario->empresa_id,
            'usuario_id'  => $usuario->id,
            'ip'          => $ip,
            'user_agent'  => substr($userAgent, 0, 255),
            'evento'      => 'LOGOUT',
            'ocurrido_en' => now(),
        ]);
    }

    /**
     * Usuarios con sesión activa = usuarios que tienen tokens Sanctum vigentes.
     * No modifica BD — usa personal_access_tokens que ya existe.
     */
    public function sesionesActivas(int $empresaId): Collection
    {
        return DB::table('personal_access_tokens as pat')
            ->join('usuarios as u', 'u.id', '=', 'pat.tokenable_id')
            ->where('pat.tokenable_type', 'App\\Models\\Usuario')
            ->where('u.empresa_id', $empresaId)
            ->whereNull('pat.expires_at')
            ->orWhere('pat.expires_at', '>', now())
            ->select([
                'u.id as usuario_id',
                'u.nombres',
                'u.apellidos',
                'u.email',
                'u.rol',
                'pat.last_used_at',
                'pat.created_at as token_creado_en',
            ])
            ->orderByDesc('pat.last_used_at')
            ->get();
    }

    /**
     * Historial de logins de la empresa.
     */
    public function historialLogin(int $empresaId, int $limit = 100): Collection
    {
        return LoginLog::where('empresa_id', $empresaId)
            ->with('usuario')
            ->orderByDesc('ocurrido_en')
            ->limit($limit)
            ->get();
    }

    /**
     * Historial de sesiones iniciadas por un usuario específico.
     */
    public function historialPorUsuario(int $usuarioId, int $empresaId): Collection
    {
        return LoginLog::where('empresa_id', $empresaId)
            ->where('usuario_id', $usuarioId)
            ->orderByDesc('ocurrido_en')
            ->limit(50)
            ->get();
    }
}
