<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventario extends Model
{
    protected $table = 'inventarios';

    protected $fillable = [
        'empresa_id',
        'item_id',
        'unidades_actuales',
        'unidades_minimas',
    ];

    protected $casts = [
        'unidades_actuales' => 'integer',
        'unidades_minimas'  => 'integer',
        'updated_at'        => 'datetime',
    ];

    public $timestamps  = false;
    const UPDATED_AT    = 'updated_at';

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}