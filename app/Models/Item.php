<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'controla_inventario'  => 'boolean',
        'is_activo'            => 'boolean',
        'precio_compra'        => 'decimal:2',
        'precio_venta_sugerido'=> 'decimal:2',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
    ];

    public $timestamps = true;

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function inventario()
    {
        return $this->hasOne(Inventario::class, 'item_id');
    }

    public function facturaLineas()
{
    return $this->hasMany(FacturaLinea::class, 'item_id');
}
}