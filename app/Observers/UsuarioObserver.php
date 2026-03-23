<?php

namespace App\Observers;

use App\Models\Usuario;

class UsuarioObserver
{
    use AuditableTrait;

    public function created(Usuario $model): void
    {
        $this->auditar('usuarios', 'CREAR', $model->id, "Usuario creado: {$model->email}");
    }

    public function updated(Usuario $model): void
    {
        if ($model->wasChanged('password_hash')) {
            $this->auditar('usuarios', 'CAMBIAR_PASSWORD', $model->id, "Contraseña cambiada: {$model->email}");
            return;
        }

        if ($model->wasChanged('is_activo')) {
            $estado = $model->is_activo ? 'activado' : 'desactivado';
            $this->auditar('usuarios', 'TOGGLE', $model->id, "Usuario {$estado}: {$model->email}");
            return;
        }

        $this->auditar('usuarios', 'EDITAR', $model->id, "Usuario editado: {$model->email}");
    }
}
