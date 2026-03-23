<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = [
        'empresa_id',
        'nombre_razon_social',
        'contacto',
        'tipo_documento',
        'num_documento',
        'email',
        'telefono',
        'empresa',
        'direccion',
        'is_activo',
        'saldo_a_favor',
    ];

    protected $casts = [
        'is_activo'     => 'boolean',
        'saldo_a_favor' => 'decimal:2',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    public $timestamps = true;

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}