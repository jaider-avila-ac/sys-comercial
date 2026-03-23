<?php

namespace App\Observers;

use App\Models\IngresoManual;

class IngresoManualObserver
{
    use AuditableTrait, ResumenTrait;

    public function created(IngresoManual $model): void
    {
        $this->auditar('ingresos_manuales', 'REGISTRAR', $model->id, "Ingreso manual: {$model->descripcion} - \${$model->monto}");
        $this->actualizarResumen($model->empresa_id);
    }

    public function updated(IngresoManual $model): void
    {
        if ($model->wasChanged('estado') && $model->estado === 'ANULADO') {
            $this->auditar('ingresos_manuales', 'ANULAR', $model->id, "Ingreso manual anulado: {$model->descripcion}");
            $this->actualizarResumen($model->empresa_id);
        }
    }
}
