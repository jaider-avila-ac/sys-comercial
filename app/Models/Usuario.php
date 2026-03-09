<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'usuarios';

    protected $fillable = [
        'empresa_id','nombres','apellidos','email','password_hash','rol','is_activo'
    ];

    protected $hidden = [
        'password_hash',
    ];

    // Laravel espera "password", pero tú tienes "password_hash"
    public function getAuthPassword()
    {
        return $this->password_hash;
    }
}