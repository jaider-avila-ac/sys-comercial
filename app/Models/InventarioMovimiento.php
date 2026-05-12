<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioMovimiento extends Model
{
    protected $table = 'inventario_movimientos';

    protected $fillable = [
        'empresa_id',
        'item_id',
        'usuario_id',
        'tipo',
        'subtipo',
        'motivo',
        'referencia_tipo',
        'referencia_id',
        'compra_id',
        'unidades',
        'unidades_resultantes',
        'ocurrido_en',
    ];

    protected $casts = [
    'tipo'                 => 'string', 
    'referencia_tipo'      => 'string',
    'unidades'             => 'integer',
    'unidades_resultantes' => 'integer',
    'ocurrido_en'          => 'datetime',
];

    public $timestamps = false;

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}