<?php

namespace App\Observers;

use App\Models\Empresa;

class EmpresaObserver
{
    use AuditableTrait;

    public function created(Empresa $model): void
    {
        $this->auditar('empresas', 'CREAR', $model->id, "Empresa creada: {$model->nombre}");
    }

    public function updated(Empresa $model): void
    {
        $this->auditar('empresas', 'EDITAR', $model->id, "Empresa actualizada: {$model->nombre}");
    }

    public function deleted(Empresa $model): void
    {
        $this->auditar('empresas', 'ELIMINAR', $model->id, "Empresa eliminada: {$model->nombre}");
    }
}
