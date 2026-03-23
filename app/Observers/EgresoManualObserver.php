<?php

namespace App\Observers;

use App\Models\EgresoManual;

class EgresoManualObserver
{
    use AuditableTrait, ResumenTrait;

    public function created(EgresoManual $model): void
    {
        $this->auditar('egresos_manuales', 'REGISTRAR', $model->id, "Egreso manual: {$model->descripcion} - \${$model->monto}");
        $this->actualizarResumen($model->empresa_id);
    }

    public function updated(EgresoManual $model): void
    {
        if ($model->wasChanged('estado') && $model->estado === 'ANULADO') {
            $this->auditar('egresos_manuales', 'ANULAR', $model->id, "Egreso manual anulado: {$model->descripcion}");
            $this->actualizarResumen($model->empresa_id);
        }
    }
}
