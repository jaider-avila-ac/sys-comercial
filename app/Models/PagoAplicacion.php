<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoAplicacion extends Model
{
    protected $table = 'pago_aplicaciones';

    protected $fillable = [
        'ingreso_pago_id',
        'factura_id',
        'empresa_id',
        'monto',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public $timestamps = false;

    public function ingresoPago()
    {
        return $this->belongsTo(IngresoPago::class, 'ingreso_pago_id');
    }

    public function factura()
    {
        return $this->belongsTo(Factura::class, 'factura_id');
    }
}