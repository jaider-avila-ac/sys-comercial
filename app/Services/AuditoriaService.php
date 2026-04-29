<?php

namespace App\Services;

use App\Models\Auditoria;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AuditoriaService
{
    /**
     * Registrar una acción en auditoría.
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

    public function listarPorEmpresa(int $empresaId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $accion = $filters['accion'] ?? null;
        $desde = $filters['desde'] ?? null;
        $hasta = $filters['hasta'] ?? null;

        $query = Auditoria::where('empresa_id', $empresaId)
            ->with('usuario')
            ->when($accion, fn($q) => $q->where('accion', $accion))
            ->when($desde, fn($q) => $q->whereDate('ocurrido_en', '>=', $desde))
            ->when($hasta, fn($q) => $q->whereDate('ocurrido_en', '<=', $hasta))
            ->orderByDesc('ocurrido_en');

        return $query->paginate($perPage);
    }

    public function listarPorUsuario(int $usuarioId, int $empresaId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $accion = $filters['accion'] ?? null;
        $desde = $filters['desde'] ?? null;
        $hasta = $filters['hasta'] ?? null;

        $query = Auditoria::where('empresa_id', $empresaId)
            ->where('usuario_id', $usuarioId)
            ->with('usuario')
            ->when($accion, fn($q) => $q->where('accion', $accion))
            ->when($desde, fn($q) => $q->whereDate('ocurrido_en', '>=', $desde))
            ->when($hasta, fn($q) => $q->whereDate('ocurrido_en', '<=', $hasta))
            ->orderByDesc('ocurrido_en');

        return $query->paginate($perPage);
    }
}