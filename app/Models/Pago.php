<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pago extends Model
{
    protected $table = 'pagos';

    protected $fillable = [
        'empresa_id',
        'cliente_id',
        'usuario_id',
        'numero_recibo',
        'fecha',
        'forma_pago',
        'referencia',
        'notas',
        'total_pagado',
    ];

    protected $casts = [
        'fecha'        => 'date:Y-m-d',
        'total_pagado' => 'decimal:2',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function aplicaciones(): HasMany
    {
        return $this->hasMany(PagoAplicacion::class, 'pago_id');
    }
}
