<?php

namespace App\Observers;

use App\Models\EgresoManual;

class EgresoManualObserver
{
    use AuditableTrait, ResumenTrait;

    public function created(EgresoManual $model): void
    {
        $this->auditar(
            'egresos_manuales',
            'REGISTRAR',
            $model->id,
            "Egreso manual registrado: {$model->descripcion} - \${$model->monto}"
        );

        $this->actualizarResumen($model->empresa_id);
    }

    public function updated(EgresoManual $model): void
    {
        if ($model->wasChanged('estado') && $model->estado === 'ANULADO') {
            $this->auditar(
                'egresos_manuales',
                'ANULAR',
                $model->id,
                "Egreso manual anulado: {$model->descripcion}"
            );
        } else {
            $this->auditar(
                'egresos_manuales',
                'ACTUALIZAR',
                $model->id,
                "Egreso manual actualizado: {$model->descripcion}"
            );
        }

        $this->actualizarResumen($model->empresa_id);

        if ($model->wasChanged('empresa_id') && $model->getOriginal('empresa_id')) {
            $this->actualizarResumen((int) $model->getOriginal('empresa_id'));
        }
    }

    public function deleted(EgresoManual $model): void
    {
        $this->auditar(
            'egresos_manuales',
            'ELIMINAR',
            $model->id,
            "Egreso manual eliminado: {$model->descripcion}"
        );

        $this->actualizarResumen($model->empresa_id);
    }
}