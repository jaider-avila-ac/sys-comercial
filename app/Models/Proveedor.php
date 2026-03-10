<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    /** Items que tienen este proveedor como habitual */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'proveedor_id');
    }

    /** Historial de compras a este proveedor */
    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class, 'proveedor_id');
    }
}