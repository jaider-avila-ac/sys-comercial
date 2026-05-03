<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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

    protected $appends = ['logo_url'];

    public $timestamps = true;

    /**
     * Obtiene la URL pública del logo (SIEMPRE protegida por token)
     */
   public function getLogoUrlAttribute(): ?string
{
    if (!$this->logo_path) {
        return null;
    }
    
    // Siempre usar la ruta API (funciona en cualquier servidor)
    return url('/api/empresa/logo');
}

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'empresa_id');
    }
}