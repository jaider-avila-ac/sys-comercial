<?php

namespace App\Observers;

use App\Models\Compra;

class CompraObserver
{
    use AuditableTrait, ResumenTrait;

    public function created(Compra $model): void
    {
        $this->auditar('compras', 'CREAR', $model->id, "Compra creada");
    }

    public function updated(Compra $model): void
    {
        if (! $model->wasChanged('estado')) {
            return;
        }

        $accion = match ($model->estado) {
            'PENDIENTE' => 'CONFIRMAR',
            'PARCIAL'   => 'PAGAR',
            'PAGADA'    => 'PAGAR',
            'ANULADA'   => 'ANULAR',
            default     => 'EDITAR',
        };

        $numero = $model->numero ?: "ID {$model->id}";
        $this->auditar('compras', $accion, $model->id, "Compra {$numero} → {$model->estado}");
        $this->actualizarResumen($model->empresa_id);
    }
}
