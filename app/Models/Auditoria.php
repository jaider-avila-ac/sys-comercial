<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auditoria extends Model
{
    protected $table = 'auditoria';

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

    // Solo tiene ocurrido_en, no created_at/updated_at estándar
    public $timestamps = false;

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}