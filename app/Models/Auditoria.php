<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Auditoria extends Model
{
    protected $table      = 'auditoria';
    public    $timestamps = false; // solo tiene ocurrido_en

    protected $fillable = [
        'empresa_id',
        'usuario_id',
        'entidad',
        'accion',
        'entidad_id',
        'descripcion',
        'ip',
        'ocurrido_en',
    ];

    protected $casts = [
        'ocurrido_en' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
