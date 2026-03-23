<?php

namespace App\Observers;

use App\Models\Item;

class ItemObserver
{
    use AuditableTrait, ResumenTrait;

    public function created(Item $model): void
    {
        $this->auditar('items', 'CREAR', $model->id, "Ítem creado: {$model->nombre}");
        $this->actualizarResumen($model->empresa_id);
    }

    public function updated(Item $model): void
    {
        if ($model->wasChanged('is_activo')) {
            $estado = $model->is_activo ? 'activado' : 'desactivado';
            $this->auditar('items', 'TOGGLE', $model->id, "Ítem {$estado}: {$model->nombre}");
            $this->actualizarResumen($model->empresa_id);
            return;
        }
        $this->auditar('items', 'EDITAR', $model->id, "Ítem editado: {$model->nombre}");
    }

    public function deleted(Item $model): void
    {
        $this->auditar('items', 'ELIMINAR', $model->id, "Ítem eliminado: {$model->nombre}");
        $this->actualizarResumen($model->empresa_id);
    }
}
