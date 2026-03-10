<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CompraPago extends Model
{
    protected $table = 'compra_pagos';
    public $timestamps = false;

    protected $fillable = [
        'compra_id',
        'empresa_id',
        'usuario_id',
        'fecha',
        'monto',
        'medio_pago',
        'notas',
        'archivo_path',
        'archivo_mime',
        'archivo_nombre',
        'created_at',
    ];

    protected $casts = [
        'monto'      => 'decimal:2',
        'fecha'      => 'date',
        'created_at' => 'datetime',
    ];

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function egreso(): HasOne
    {
        return $this->hasOne(Egreso::class, 'compra_pago_id');
    }
}