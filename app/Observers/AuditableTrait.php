<?php

namespace App\Observers;

use App\Models\Usuario;
use App\Services\AuditoriaService;

trait AuditableTrait
{
    protected function auditar(
        string $entidad,
        string $accion,
        int    $entidadId,
        string $descripcion,
    ): void {
        /** @var Usuario|null $usuario */
        $usuario = request()->user();

        if (! $usuario instanceof Usuario) {
            return;
        }

        app(AuditoriaService::class)->registrar(
            empresaId:   $usuario->empresa_id,
            usuarioId:   $usuario->id,
            entidad:     $entidad,
            accion:      $accion,
            entidadId:   $entidadId,
            descripcion: $descripcion,
        );
    }
}
