<?php

namespace App\Observers;

use App\Models\Factura;

class FacturaObserver
{
    use AuditableTrait, ResumenTrait;

    public function created(Factura $model): void
    {
        $this->auditar('facturas', 'CREAR', $model->id, "Factura creada");
        $this->actualizarResumen($model->empresa_id);
    }

    public function updated(Factura $model): void
    {
        if (! $model->wasChanged('estado')) {
            $this->actualizarResumen($model->empresa_id);
            return;
        }

        $accion = match ($model->estado) {
            'EMITIDA' => 'EMITIR',
            'ANULADA' => 'ANULAR',
            default   => 'EDITAR',
        };

        $numero = $model->numero ?: "ID {$model->id}";
        $this->auditar('facturas', $accion, $model->id, "Factura {$numero} → {$model->estado}");
        $this->actualizarResumen($model->empresa_id);
    }

    public function deleted(Factura $model): void
    {
        $this->auditar('facturas', 'ELIMINAR', $model->id, "Factura eliminada: {$model->numero}");
        $this->actualizarResumen($model->empresa_id);
    }
}
