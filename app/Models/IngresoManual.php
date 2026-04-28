<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class IngresoManual extends Model
{
    protected $table = 'ingresos_manuales';

    protected $fillable = [
        'empresa_id',
        'usuario_id',
        'fecha',
        'descripcion',
        'monto',
        'notas',
        'archivo_path',
        'archivo_mime',
        'archivo_nombre',
        'estado',
    ];

    protected $casts = [
        'monto'      => 'decimal:2',
        'fecha'      => 'date:Y-m-d',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'archivo_url',
    ];

    public $timestamps = true;

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function getArchivoUrlAttribute(): ?string
    {
        if (! $this->archivo_path) {
            return null;
        }

        return Storage::url($this->archivo_path);
    }
}