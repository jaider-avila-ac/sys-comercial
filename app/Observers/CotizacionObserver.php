<?php

namespace App\Observers;

use App\Models\Cotizacion;

class CotizacionObserver
{
    use AuditableTrait, ResumenTrait;

    public function created(Cotizacion $model): void
    {
        $this->auditar('cotizaciones', 'CREAR', $model->id, "Cotización creada");
        $this->actualizarResumen($model->empresa_id);
    }

    public function updated(Cotizacion $model): void
    {
        if (! $model->wasChanged('estado')) {
            $this->auditar('cotizaciones', 'EDITAR', $model->id, "Cotización editada");
            return;
        }

        $accion = match ($model->estado) {
            'EMITIDA'   => 'EMITIR',
            'ANULADA'   => 'ANULAR',
            'FACTURADA' => 'CONVERTIR',
            default     => 'EDITAR',
        };

        $numero = $model->numero ?: "ID {$model->id}";
        $this->auditar('cotizaciones', $accion, $model->id, "Cotización {$numero} → {$model->estado}");
        $this->actualizarResumen($model->empresa_id);
    }

    public function deleted(Cotizacion $model): void
    {
        $this->auditar('cotizaciones', 'ELIMINAR', $model->id, "Cotización eliminada: {$model->numero}");
        $this->actualizarResumen($model->empresa_id);
    }
}
