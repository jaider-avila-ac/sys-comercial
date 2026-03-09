<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Auditoria;
use Illuminate\Http\Request;

trait AuditLog
{
    /**
     * Registra una acción en la tabla auditoria.
     *
     * @param  Request     $request   Para extraer IP y usuario autenticado
     * @param  string      $entidad   Nombre de la entidad (ej: 'usuarios', 'clientes')
     * @param  string      $accion    Verbo de la acción  (ej: 'CREAR', 'EDITAR', 'ELIMINAR')
     * @param  int         $entidadId ID del registro afectado
     * @param  string      $desc      Descripción legible (ej: 'Creó el usuario juan@mail.com')
     * @param  int|null    $empresaId Empresa del actor (null = SUPER_ADMIN sin empresa)
     */
    protected function audit(
        Request $request,
        string  $entidad,
        string  $accion,
        int     $entidadId,
        string  $desc,
        ?int    $empresaId = null
    ): void {
        try {
            Auditoria::create([
                'empresa_id'  => $empresaId,
                'usuario_id'  => $request->user()?->id,
                'entidad'     => $entidad,
                'accion'      => strtoupper($accion),
                'entidad_id'  => $entidadId,
                'descripcion' => mb_substr($desc, 0, 255),
                'ip'          => $request->ip(),
                'ocurrido_en' => now(),
            ]);
        } catch (\Throwable) {
            // La auditoría nunca debe romper el flujo principal
        }
    }
}
