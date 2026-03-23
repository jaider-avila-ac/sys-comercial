<?php

namespace App\Observers;

use App\Models\EgresoCompra;

class EgresoCompraObserver
{
    use AuditableTrait, ResumenTrait;

    public function created(EgresoCompra $model): void
    {
        $this->auditar('egresos_compras', 'REGISTRAR', $model->id, "Egreso compra: {$model->descripcion} - \${$model->monto}");
        $this->actualizarResumen($model->empresa_id);
    }

    public function updated(EgresoCompra $model): void
    {
        if ($model->wasChanged('estado') && $model->estado === 'ANULADO') {
            $this->auditar('egresos_compras', 'ANULAR', $model->id, "Egreso compra anulado: {$model->descripcion}");
            $this->actualizarResumen($model->empresa_id);
        }
    }
}
