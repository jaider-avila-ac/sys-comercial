<?php

namespace App\Observers;

use App\Models\IngresoMostrador;

class IngresoMostradorObserver
{
    use AuditableTrait, ResumenTrait;

    public function created(IngresoMostrador $model): void
    {
        $this->auditar('ingresos_mostrador', 'REGISTRAR', $model->id, "Venta mostrador: {$model->numero} - \${$model->monto}");
        $this->actualizarResumen($model->empresa_id);
    }

    public function updated(IngresoMostrador $model): void
    {
        if ($model->wasChanged('estado') && $model->estado === 'ANULADO') {
            $this->auditar('ingresos_mostrador', 'ANULAR', $model->id, "Venta mostrador anulada: {$model->numero}");
            $this->actualizarResumen($model->empresa_id);
        }
    }
}
