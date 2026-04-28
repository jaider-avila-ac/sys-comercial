<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cotizacion extends Model
{
    protected $table = 'cotizaciones';

    protected $fillable = [
        'empresa_id',
        'cliente_id',
        'usuario_id',
        'numero',
        'estado',
        'fecha',
        'fecha_vencimiento',
        'notas',
        'subtotal',
        'total_descuentos',
        'total_iva',
        'total',
    ];

    protected $casts = [
        'fecha'            => 'date',
        'fecha_vencimiento' => 'date',
        'subtotal'         => 'decimal:2',
        'total_descuentos' => 'decimal:2',
        'total_iva'        => 'decimal:2',
        'total'            => 'decimal:2',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    public $timestamps = true;

    const ESTADOS = ['BORRADOR', 'EMITIDA', 'VENCIDA', 'FACTURADA', 'ANULADA'];

    public function lineas()
    {
        return $this->hasMany(CotizacionLinea::class, 'cotizacion_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
