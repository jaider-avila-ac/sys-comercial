<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoAplicacion extends Model
{
    protected $table = 'pago_aplicaciones';

    public $timestamps = false;

    protected $fillable = [
        'pago_id', 'factura_id', 'empresa_id', 'monto',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'pago_id');
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class, 'factura_id');
    }
}
