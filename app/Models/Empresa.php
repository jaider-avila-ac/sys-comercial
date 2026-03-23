<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    protected $table = 'empresas';

    protected $fillable = [
        'nombre',
        'nit',
        'email',
        'telefono',
        'direccion',
        'logo_path',
        'logo_mime',
        'logo_updated_at',
        'is_activa',
    ];

    protected $casts = [
        'is_activa'       => 'boolean',
        'logo_updated_at' => 'datetime',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    public $timestamps = true;

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'empresa_id');
    }
}