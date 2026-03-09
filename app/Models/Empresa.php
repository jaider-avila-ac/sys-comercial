<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    protected $table = 'empresas';

    protected $fillable = [
        'nombre','nit','email','telefono','direccion',
        'logo_path','logo_mime','logo_updated_at',
        'is_activa',
    ];

    protected $casts = [
        'is_activa' => 'boolean',
        'logo_updated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'empresa_id');
    }
}