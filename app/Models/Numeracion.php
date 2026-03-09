<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Numeracion extends Model
{
    protected $table = 'numeraciones';
    public $timestamps = false;

    protected $fillable = [
        'empresa_id','tipo','prefijo','consecutivo','relleno','updated_at'
    ];

    protected $casts = [
        'consecutivo' => 'integer',
        'relleno' => 'integer',
        'updated_at' => 'datetime',
    ];
}