<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngresoMostrador extends Model
{
    protected $table = 'ingresos_mostrador';

    protected $fillable = [
        'empresa_id',
        'usuario_id',
        'numero',
        'fecha',
        'descripcion',
        'monto',
        'notas',
        'forma_pago',
        'referencia',
        'item_id',
        'cantidad',
        'precio_unitario',
        'iva_pct',
        'archivo_path',
        'archivo_mime',
        'archivo_nombre',
        'estado',
    ];

    protected $casts = [
        'monto'          => 'decimal:2',
        'precio_unitario'=> 'decimal:2',
        'iva_pct'        => 'decimal:3',
        'cantidad'       => 'integer',
        'fecha'          => 'date',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    public $timestamps = true;

    const FORMAS_PAGO = ['EFECTIVO', 'TRANSFERENCIA', 'TARJETA', 'BILLETERA', 'OTRO'];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
