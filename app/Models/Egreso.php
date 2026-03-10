<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Egreso extends Model
{
    protected $table = 'egresos';

    protected $fillable = [
        'empresa_id',
        'usuario_id',
        'descripcion',
        'monto',
        'fecha',
        'archivo_path',
        'archivo_mime',
        'archivo_nombre',
        'compra_id',
        'compra_pago_id',
    ];

    protected $casts = [
        'fecha' => 'date:Y-m-d',
        'monto' => 'decimal:2',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function compra(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Compra::class, 'compra_id');
    }

    public function compraPago(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\CompraPago::class, 'compra_pago_id');
    }
}
