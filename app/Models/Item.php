<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Item extends Model
{
    protected $table = 'items';

    protected $fillable = [
        'empresa_id',
        'proveedor_id',
        'tipo',
        'nombre',
        'descripcion',
        'precio_compra',
        'precio_venta_sugerido',
        'controla_inventario',
        'unidad',
        'is_activo',
    ];

    protected $casts = [
        'controla_inventario' => 'boolean',
        'is_activo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function inventario(): HasOne
    {
        return $this->hasOne(Inventario::class, 'item_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Proveedor::class, 'proveedor_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(InventarioMovimiento::class, 'item_id');
    }
}