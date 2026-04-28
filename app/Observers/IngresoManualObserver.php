<?php

namespace App\Observers;

use App\Models\IngresoManual;

class IngresoManualObserver
{
    use AuditableTrait, ResumenTrait;

    public function created(IngresoManual $model): void
    {
        $this->auditar(
            'ingresos_manuales',
            'REGISTRAR',
            $model->id,
            "Ingreso manual registrado: {$model->descripcion} - \${$model->monto}"
        );

        $this->actualizarResumen($model->empresa_id);
    }

    public function updated(IngresoManual $model): void
    {
        if ($model->wasChanged('estado') && $model->estado === 'ANULADO') {
            $this->auditar(
                'ingresos_manuales',
                'ANULAR',
                $model->id,
                "Ingreso manual anulado: {$model->descripcion}"
            );
        } else {
            $this->auditar(
                'ingresos_manuales',
                'ACTUALIZAR',
                $model->id,
                "Ingreso manual actualizado: {$model->descripcion}"
            );
        }

        $this->actualizarResumen($model->empresa_id);

        if ($model->wasChanged('empresa_id') && $model->getOriginal('empresa_id')) {
            $this->actualizarResumen((int) $model->getOriginal('empresa_id'));
        }
    }

    public function deleted(IngresoManual $model): void
    {
        $this->auditar(
            'ingresos_manuales',
            'ELIMINAR',
            $model->id,
            "Ingreso manual eliminado: {$model->descripcion}"
        );

        $this->actualizarResumen($model->empresa_id);
    }
}