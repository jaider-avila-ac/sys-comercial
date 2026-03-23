<?php

namespace App\Observers;

use App\Models\Cliente;

class ClienteObserver
{
    use AuditableTrait, ResumenTrait;

    public function created(Cliente $model): void
    {
        $this->auditar('clientes', 'CREAR', $model->id, "Cliente creado: {$model->nombre_razon_social}");
        $this->actualizarResumen($model->empresa_id);
    }

    public function updated(Cliente $model): void
    {
        if ($model->wasChanged('is_activo')) {
            $estado = $model->is_activo ? 'activado' : 'desactivado';
            $this->auditar('clientes', 'TOGGLE', $model->id, "Cliente {$estado}: {$model->nombre_razon_social}");
            $this->actualizarResumen($model->empresa_id);
            return;
        }
        $this->auditar('clientes', 'EDITAR', $model->id, "Cliente editado: {$model->nombre_razon_social}");
    }

    public function deleted(Cliente $model): void
    {
        $this->auditar('clientes', 'ELIMINAR', $model->id, "Cliente eliminado: {$model->nombre_razon_social}");
        $this->actualizarResumen($model->empresa_id);
    }
}
