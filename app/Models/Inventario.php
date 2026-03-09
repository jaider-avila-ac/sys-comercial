<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventario extends Model
{
    protected $table = 'inventarios';

    public $timestamps = false; // solo tienes updated_at

    protected $fillable = [
        'empresa_id',
        'item_id',
        'cantidad_actual',
        'stock_minimo',
        'updated_at',
    ];

    protected $casts = [
        'cantidad_actual' => 'decimal:3',
        'stock_minimo' => 'decimal:3',
        'updated_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}