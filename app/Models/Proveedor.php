<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Compra;

class Proveedor extends Model
{
    protected $table = 'proveedores';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'nit',
        'telefono',
        'email',
        'contacto',
        'direccion',
        'ciudad',
        'tiempo_entrega_dias',
        'notas',
        'is_activo',
    ];

    protected $casts = [
        'is_activo'           => 'boolean',
        'tiempo_entrega_dias' => 'integer',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];

    public $timestamps = true;

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function items()
    {
        return $this->hasMany(Item::class, 'proveedor_id');
    }

    public function compras()
{
    return $this->hasMany(Compra::class, 'proveedor_id');
}
}