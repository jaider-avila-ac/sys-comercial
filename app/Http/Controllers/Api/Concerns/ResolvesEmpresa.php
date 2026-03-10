<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;

trait ResolvesEmpresa
{
    protected function resolveEmpresaId(Request $request, bool $requiredForSuperAdmin = false): ?int
    {
        $u = $request->user();

        if ($u->rol === 'SUPER_ADMIN') {
            $eid = (int)($request->query('empresa_id') ?? $request->input('empresa_id') ?? 0);

            if ($requiredForSuperAdmin && $eid <= 0) {
                abort(422, 'empresa_id requerido para SUPER_ADMIN');
            }

            return $eid > 0 ? $eid : null;
        }

        if (!$u->empresa_id) {
            abort(403, 'Sin empresa');
        }

        return (int)$u->empresa_id;
    }
}