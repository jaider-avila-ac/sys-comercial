<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioMovimiento extends Model
{
    protected $table = 'inventario_movimientos';

    public $timestamps = false; // ocurrido_en

    protected $fillable = [
        'empresa_id',
        'item_id',
        'usuario_id',
        'tipo',
        'motivo',
        'referencia_tipo',
        'referencia_id',
        'cantidad',
        'saldo_resultante',
        'ocurrido_en',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'saldo_resultante' => 'decimal:3',
        'referencia_id' => 'integer',
        'ocurrido_en' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}