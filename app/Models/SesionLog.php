<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SesionLog extends Model
{
    protected $table      = 'sesiones_log';
    public    $timestamps = false;

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

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}