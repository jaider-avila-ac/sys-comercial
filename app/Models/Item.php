<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Item extends Model
{
    protected $table = 'items';

    protected $fillable = [
        'empresa_id',
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
}