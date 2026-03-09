<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'usuarios';

    protected $fillable = [
        'empresa_id',
        'nombres',
        'apellidos',
        'email',
        'password_hash',
        'rol',
        'is_activo',
        'last_login_at',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'is_activo' => 'boolean',
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Para que Laravel auth use password_hash
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    // Helpers de rol (para Policies / Gates)
    public function isSuperAdmin(): bool
    {
        return $this->rol === 'SUPER_ADMIN';
    }

    public function isEmpresaAdmin(): bool
    {
        return $this->rol === 'EMPRESA_ADMIN';
    }

    public function isOperativo(): bool
    {
        return $this->rol === 'OPERATIVO';
    }

    public function empresa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(\App\Models\Empresa::class, 'empresa_id');
}
}