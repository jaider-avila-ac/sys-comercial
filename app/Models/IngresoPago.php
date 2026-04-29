<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngresoPago extends Model
{
    protected $table = 'ingresos_pagos';

    protected $fillable = [
        'empresa_id',
        'usuario_id',
        'numero',
        'fecha',
        'descripcion',
        'monto',
        'notas',
        'forma_pago',
        'referencia',
        'archivo_path',
        'archivo_mime',
        'archivo_nombre',
        'estado',
    ];

     protected $casts = [
        'monto'      => 'decimal:2',
        'fecha'      => 'date:Y-m-d',  
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public $timestamps = true;

    const FORMAS_PAGO = ['EFECTIVO', 'TRANSFERENCIA', 'TARJETA', 'BILLETERA', 'OTRO'];

    public function aplicaciones()
    {
        return $this->hasMany(PagoAplicacion::class, 'ingreso_pago_id');
    }

    public function facturas()
    {
        return $this->belongsToMany(Factura::class, 'pago_aplicaciones', 'ingreso_pago_id', 'factura_id')
                    ->withPivot('monto');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}