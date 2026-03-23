<?php

namespace App\Repositories;

use App\Models\Usuario;
use App\Repositories\Contracts\UsuarioRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class UsuarioRepository implements UsuarioRepositoryInterface
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