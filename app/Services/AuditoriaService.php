<?php

namespace App\Services;

use App\Models\Auditoria;
use Illuminate\Support\Collection;

class AuditoriaService
{
    /**
     * Registrar una acción en auditoría.
     * Llamado desde observers y desde AuthService.
     */
    public function registrar(
        int    $empresaId,
        int    $usuarioId,
        string $entidad,
        string $accion,
        int    $entidadId,
        string $descripcion,
        string $ip = '',
    ): void {
        Auditoria::create([
            'empresa_id'  => $empresaId,
            'usuario_id'  => $usuarioId,
            'entidad'     => $entidad,
            'accion'      => $accion,
            'entidad_id'  => $entidadId,
            'descripcion' => $descripcion,
            'ip'          => $ip ?: (request()?->ip() ?? ''),
            'ocurrido_en' => now(),
        ]);
    }

    public function listarPorEmpresa(int $empresaId, int $limit = 200): Collection
    {
        return Auditoria::where('empresa_id', $empresaId)
            ->with('usuario')
            ->orderByDesc('ocurrido_en')
            ->limit($limit)
            ->get();
    }

    public function listarPorUsuario(int $usuarioId, int $empresaId): Collection
    {
        return Auditoria::where('empresa_id', $empresaId)
            ->where('usuario_id', $usuarioId)
            ->orderByDesc('ocurrido_en')
            ->limit(100)
            ->get();
    }
}
