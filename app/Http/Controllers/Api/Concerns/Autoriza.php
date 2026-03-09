<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\User;
use Illuminate\Http\Request;

trait Autoriza
{
    protected function user(Request $request): User
    {
        /** @var User $u */
        $u = $request->user();
        return $u;
    }

    /** Requiere 1 rol exacto */
    protected function requireRole(User $u, string $role): void
    {
        if ($u->rol !== $role) {
            abort(403, 'No autorizado');
        }
    }

    /** Requiere estar en cualquiera de estos roles */
    protected function requireAnyRole(User $u, array $roles): void
    {
        if (!in_array($u->rol, $roles, true)) {
            abort(403, 'No autorizado');
        }
    }

    /** Requiere empresa_id (para EMPRESA_ADMIN/OPERATIVO) */
    protected function requireEmpresaId(User $u): int
    {
        if (!$u->empresa_id) {
            abort(403, 'Usuario sin empresa asignada');
        }
        return (int) $u->empresa_id;
    }

    /** Si NO es SUPER_ADMIN, obliga a que empresaId coincida con el usuario */
    protected function ensureSameEmpresa(User $u, int $empresaId): void
    {
        if ($u->rol === 'SUPER_ADMIN') return;

        if ((int)$u->empresa_id !== (int)$empresaId) {
            abort(403, 'No autorizado');
        }
    }
}