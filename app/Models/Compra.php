<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Compra extends Model
{
    protected $table = 'compras';

     protected $fillable = [
        'empresa_id',
        'proveedor_id',
        'usuario_id',
        'numero',
        'fecha',
        'condicion_pago',
        'fecha_vencimiento',
        'subtotal',
        'impuestos',
        'total',
        'saldo_pendiente',
        'estado',
        'notas',
        
    ];

    protected $casts = [
        'fecha'             => 'date',
        'fecha_vencimiento' => 'date',
        'subtotal'          => 'decimal:2',
        'impuestos'         => 'decimal:2',
        'total'             => 'decimal:2',
        'saldo_pendiente'   => 'decimal:2',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    public $timestamps = true;

    // condicion_pago nullable = compra libre
    const CONDICIONES = ['CONTADO', 'CREDITO'];
    const ESTADOS     = ['PENDIENTE', 'PARCIAL', 'PAGADA', 'ANULADA'];

    public function items()
    {
        return $this->hasMany(CompraItem::class, 'compra_id');
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function egresos()
    {
        return $this->hasMany(EgresoCompra::class, 'compra_id');
    }
}