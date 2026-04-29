<?php

namespace App\Services;

use App\Models\SesionLog;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SesionService
{
    /**
     * Registrar inicio de sesión exitoso.
     */
    public function registrarLogin(Usuario $usuario, string $ip, string $userAgent): void
    {
        SesionLog::create([
            'empresa_id'  => $usuario->empresa_id,
            'usuario_id'  => $usuario->id,
            'ip'          => $ip,
            'user_agent'  => substr($userAgent, 0, 300),
            'iniciado_en' => now(),
        ]);
    }

    /**
     * Registrar intento de login fallido (opcional).
     */
    public function registrarLoginFallido(int $empresaId, int $usuarioId, string $ip, string $userAgent): void
    {
        // Si no tienes tabla para fallidos, puedes omitir o crear
    }

    /**
     * Registrar logout (opcional).
     */
    public function registrarLogout(Usuario $usuario, string $ip, string $userAgent): void
    {
        // No hay logout en sesiones_log, solo se registra inicio
    }

    /**
     * Historial de sesiones de un usuario específico (paginado).
     */
    public function historialPorUsuario(int $usuarioId, int $empresaId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $desde = $filters['desde'] ?? null;
        $hasta = $filters['hasta'] ?? null;

        $query = SesionLog::where('empresa_id', $empresaId)
            ->where('usuario_id', $usuarioId)
            ->with('usuario')
            ->orderByDesc('iniciado_en');

        if ($desde) {
            $query->whereDate('iniciado_en', '>=', $desde);
        }
        if ($hasta) {
            $query->whereDate('iniciado_en', '<=', $hasta);
        }

        return $query->paginate($perPage);
    }
}