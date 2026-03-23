<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Numeracion extends Model
{
    protected $table = 'numeraciones';

    protected $fillable = [
        'empresa_id',
        'tipo',
        'prefijo',
        'consecutivo',
        'relleno',
    ];

    protected $casts = [
        'consecutivo' => 'integer',
        'relleno'     => 'integer',
        'updated_at'  => 'datetime',
    ];

    // La tabla solo tiene updated_at, no created_at
    public $timestamps  = false;
    const UPDATED_AT    = 'updated_at';

    // Tipos disponibles
    const TIPOS = ['COT', 'FAC', 'REC', 'COM', 'MOS', 'EGR'];

    // Prefijos por defecto al crear una empresa
    const PREFIJOS_DEFAULT = [
        'COT' => 'COT',
        'FAC' => 'FAC',
        'REC' => 'REC',
        'COM' => 'COM',
        'MOS' => 'MOS',
        'EGR' => 'EGR',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}