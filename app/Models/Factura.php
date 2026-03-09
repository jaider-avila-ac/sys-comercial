<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Factura extends Model
{
    protected $table = 'facturas';

    protected $fillable = [
        'empresa_id', 'cliente_id', 'usuario_id', 'cotizacion_id',
        'numero', 'estado', 'fecha', 'notas',
        'subtotal', 'total_descuentos', 'total_iva', 'total',
        'total_pagado', 'saldo',
    ];

    protected $casts = [
        'fecha'            => 'date:Y-m-d',
        'subtotal'         => 'decimal:2',
        'total_descuentos' => 'decimal:2',
        'total_iva'        => 'decimal:2',
        'total'            => 'decimal:2',
        'total_pagado'     => 'decimal:2',
        'saldo'            => 'decimal:2',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(FacturaLinea::class, 'factura_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PagoAplicacion::class, 'factura_id');
    }
}