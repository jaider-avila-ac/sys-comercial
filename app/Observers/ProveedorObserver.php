<?php

namespace App\Observers;

use App\Models\Proveedor;

class ProveedorObserver
{
    use AuditableTrait;

    public function created(Proveedor $model): void
    {
        $this->auditar('proveedores', 'CREAR', $model->id, "Proveedor creado: {$model->nombre}");
    }

    public function updated(Proveedor $model): void
    {
        if ($model->wasChanged('is_activo')) {
            $estado = $model->is_activo ? 'activado' : 'desactivado';
            $this->auditar('proveedores', 'TOGGLE', $model->id, "Proveedor {$estado}: {$model->nombre}");
            return;
        }
        $this->auditar('proveedores', 'EDITAR', $model->id, "Proveedor editado: {$model->nombre}");
    }

    public function deleted(Proveedor $model): void
    {
        $this->auditar('proveedores', 'ELIMINAR', $model->id, "Proveedor eliminado: {$model->nombre}");
    }
}
