<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compra extends Model
{
    protected $table = 'compras';

    protected $fillable = [
        'empresa_id',
        'proveedor_id',
        'usuario_id',
        'numero',
        'fecha',
        'condicion_pago',
        'fecha_vencimiento',
        'subtotal',
        'impuestos',
        'total',
        'saldo_pendiente',
        'estado',
        'notas',
        'archivo_path',
        'archivo_mime',
        'archivo_nombre',
    ];

    protected $casts = [
        'fecha'             => 'date',
        'fecha_vencimiento' => 'date',
        'subtotal'          => 'decimal:2',
        'impuestos'         => 'decimal:2',
        'total'             => 'decimal:2',
        'saldo_pendiente'   => 'decimal:2',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CompraItem::class, 'compra_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(CompraPago::class, 'compra_id');
    }
}