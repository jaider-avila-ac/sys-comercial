<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CajaMovimiento extends Model
{
    protected $table = 'caja_movimientos';

    protected $fillable = [
        'empresa_id',
        'usuario_id',
        'origen_tipo',
        'origen_id',
        'descripcion',
        'monto',
        'fecha',
    ];

    protected $casts = [
        'monto'      => 'decimal:2',
        'fecha'      => 'date',
        'created_at' => 'datetime',
    ];

    // Solo tiene created_at
    public $timestamps  = false;
    const CREATED_AT    = 'created_at';

    const ORIGENES = [
        'INGRESO_PAGO',
        'INGRESO_MOSTRADOR',
        'INGRESO_MANUAL',
        'EGRESO_COMPRA',
        'EGRESO_MANUAL',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
