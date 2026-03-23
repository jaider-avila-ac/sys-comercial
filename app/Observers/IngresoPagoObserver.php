<?php

namespace App\Observers;

use App\Models\IngresoPago;

class IngresoPagoObserver
{
    use AuditableTrait, ResumenTrait;

    public function created(IngresoPago $model): void
    {
        $this->auditar('ingresos_pagos', 'REGISTRAR', $model->id, "Pago registrado: {$model->numero} - \${$model->monto}");
        $this->actualizarResumen($model->empresa_id);
    }

    public function updated(IngresoPago $model): void
    {
        if ($model->wasChanged('estado') && $model->estado === 'ANULADO') {
            $this->auditar('ingresos_pagos', 'ANULAR', $model->id, "Pago anulado: {$model->numero}");
            $this->actualizarResumen($model->empresa_id);
        }
    }
}
