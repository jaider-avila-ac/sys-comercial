<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SesionLog extends Model
{
    protected $table = 'sesiones_log';

    protected $fillable = [
        'empresa_id',
        'usuario_id',
        'ip',
        'user_agent',
        'pais',
        'ciudad',
        'iniciado_en',
    ];

    protected $casts = [
        'iniciado_en' => 'datetime',
    ];

    public $timestamps = false;

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
