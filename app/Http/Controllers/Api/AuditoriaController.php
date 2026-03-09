<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\Autoriza;
use App\Models\Auditoria;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    use Autoriza;

    /**
     * GET /api/auditoria
     *
     * Filtros opcionales:
     *   usuario_id  — ID del actor
     *   entidad     — nombre de la entidad (ej: 'usuarios', 'clientes')
     *   accion      — LOGIN, LOGOUT, CREAR, EDITAR, ELIMINAR, TOGGLE, CAMBIO_CLAVE
     *   desde       — YYYY-MM-DD
     *   hasta       — YYYY-MM-DD
     *   page        — paginación (20 por página)
     *
     * Permisos:
     *   SUPER_ADMIN   → ve toda la auditoría (todas las empresas)
     *   EMPRESA_ADMIN → solo registros de su empresa
     *   OPERATIVO     → 403
     */
    public function index(Request $request)
    {
        $actor = $this->user($request);
        $this->requireAnyRole($actor, ['SUPER_ADMIN', 'EMPRESA_ADMIN']);

        $usuarioId = (int) $request->query('usuario_id', 0);
        $entidad   = trim((string) $request->query('entidad', ''));
        $accion    = trim((string) $request->query('accion', ''));
        $desde     = $request->query('desde');
        $hasta     = $request->query('hasta');

        $q = Auditoria::query()
            ->with(['usuario:id,nombres,apellidos,email'])
            ->orderByDesc('ocurrido_en');

        // Scope por empresa
        if ($actor->rol === 'EMPRESA_ADMIN') {
            $q->where('empresa_id', $actor->empresa_id);
        } else {
            // SUPER_ADMIN puede filtrar opcionalmente por empresa
            $eid = (int) $request->query('empresa_id', 0);
            if ($eid > 0) $q->where('empresa_id', $eid);
        }

        // Filtros
        if ($usuarioId > 0) $q->where('usuario_id', $usuarioId);
        if ($entidad !== '') $q->where('entidad', $entidad);
        if ($accion  !== '') $q->where('accion',  strtoupper($accion));
        if ($desde)          $q->whereDate('ocurrido_en', '>=', $desde);
        if ($hasta)          $q->whereDate('ocurrido_en', '<=', $hasta);

        return response()->json($q->paginate(20));
    }
}
