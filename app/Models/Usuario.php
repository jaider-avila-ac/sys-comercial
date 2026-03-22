<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Model
{
    use HasApiTokens;

    protected $table = 'usuarios';

    protected $fillable = [
        'empresa_id',
        'nombres',
        'apellidos',
        'email',
        'password_hash',
        'rol',
        'is_activo',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'is_activo'     => 'boolean',
        'last_login_at' => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    // Laravel usa CREATED_AT / UPDATED_AT por defecto, pero la tabla los tiene como datetime
    public $timestamps = true;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombres} {$this->apellidos}");
    }

    public function esSuperAdmin(): bool
    {
        return $this->rol === 'SUPER_ADMIN';
    }

    public function esActivo(): bool
    {
        return $this->is_activo === true;
    }

    // ------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}