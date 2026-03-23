<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginLog extends Model
{
    protected $table = 'login_logs';

    protected $fillable = [
        'empresa_id',
        'usuario_id',
        'ip',
        'user_agent',
        'evento',
        'ocurrido_en',
    ];

    protected $casts = [
        'ocurrido_en' => 'datetime',
    ];

    public $timestamps = false;

    const EVENTOS = ['LOGIN', 'LOGOUT', 'LOGIN_FAIL'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
