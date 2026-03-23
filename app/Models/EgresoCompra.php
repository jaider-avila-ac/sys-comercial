<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EgresoCompra extends Model
{
    protected $table = 'egresos_compras';

    protected $fillable = [
        'empresa_id',
        'usuario_id',
        'compra_id',
        'fecha',
        'descripcion',
        'monto',
        'notas',
        'medio_pago',
        'archivo_path',
        'archivo_mime',
        'archivo_nombre',
        'estado',
    ];

    protected $casts = [
        'monto'      => 'decimal:2',
        'fecha'      => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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

    public function compra()
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }
}
