<?php

namespace App\Repositories;

use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class UsuarioRepository
{
    // ── Auth ──────────────────────────────────────────────────────────────────

    public function findByEmail(string $email): ?Usuario
    {
        return Usuario::where('email', $email)->first();
    }

    public function findById(int $id): ?Usuario
    {
        return Usuario::find($id);
    }

    public function actualizarUltimoLogin(int $id): void
    {
        Usuario::where('id', $id)->update([
            'last_login_at' => Carbon::now('America/Bogota'),
        ]);
    }

    // ── Gestión ───────────────────────────────────────────────────────────────

    public function paginate(int $empresaId, array $filters = [], int $perPage = 20, bool $esSuperAdmin = false): LengthAwarePaginator
    {
        $search = $filters['search'] ?? '';
        $rol = $filters['rol'] ?? null;
        $activo = $filters['activo'] ?? null;

        $query = Usuario::with('empresa')
            ->when(!$esSuperAdmin, function ($q) use ($empresaId) {
                $q->where('empresa_id', $empresaId);
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('nombres', 'like', "%{$search}%")
                        ->orWhere('apellidos', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($rol, fn($q) => $q->where('rol', $rol))
            ->when($activo !== null && $activo !== '', fn($q) => $q->where('is_activo', $activo === '1'));

        return $query->orderBy('nombres')->paginate($perPage);
    }

    public function allByEmpresa(int $empresaId): Collection
    {
        return Usuario::where('empresa_id', $empresaId)
            ->orderBy('nombres')
            ->get();
    }

    public function create(array $data): Usuario
    {
        return Usuario::create($data);
    }

    public function update(int $id, array $data): Usuario
    {
        $usuario = Usuario::findOrFail($id);
        $usuario->update($data);
        return $usuario->fresh();
    }

    public function toggleActivo(int $id): Usuario
    {
        $usuario = Usuario::findOrFail($id);
        $usuario->update(['is_activo' => ! $usuario->is_activo]);
        return $usuario->fresh();
    }
}