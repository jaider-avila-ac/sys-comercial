<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Factura extends Model
{
    protected $table = 'facturas';

    protected $fillable = [
        'empresa_id',
        'cliente_id',
        'usuario_id',
        'cotizacion_id',
        'numero',
        'estado',
        'fecha',
        'notas',
        'subtotal',
        'total_descuentos',
        'total_iva',
        'total',
        'total_pagado',
        'saldo',
    ];

    protected $casts = [
        'fecha'            => 'date:Y-m-d',
        'subtotal'         => 'decimal:2',
        'total_descuentos' => 'decimal:2',
        'total_iva'        => 'decimal:2',
        'total'            => 'decimal:2',
        'total_pagado'     => 'decimal:2',
        'saldo'            => 'decimal:2',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    public $timestamps = true;

    const ESTADOS = ['BORRADOR', 'EMITIDA', 'ANULADA'];

    public function lineas()
    {
        return $this->hasMany(FacturaLinea::class, 'factura_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id');
    }
}