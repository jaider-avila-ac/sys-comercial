<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpresaResumen extends Model
{
    protected $table = 'empresa_resumen';
    protected $primaryKey = 'empresa_id';
    public $incrementing = false;
    public $timestamps   = false;

    protected $fillable = [
        'empresa_id',
        'total_clientes',
        'total_items',
        'cotizaciones_activas',
        'facturas_borrador',
        'facturas_emitidas',
        'total_facturado',
        'total_pagado',
        'saldo_pendiente',
        'ingresos_facturas',
        'ingresos_mostrador',
        'ingresos_manuales',
        'total_en_caja',
        'egresos_compras',
        'egresos_manuales_tot',
        'total_egresos',
        'balance_real',
        'ultima_actividad',
    ];

    protected $casts = [
        'total_clientes'       => 'integer',
        'total_items'          => 'integer',
        'cotizaciones_activas' => 'integer',
        'facturas_borrador'    => 'integer',
        'facturas_emitidas'    => 'integer',
        'total_facturado'      => 'decimal:2',
        'total_pagado'         => 'decimal:2',
        'saldo_pendiente'      => 'decimal:2',
        'ingresos_facturas'    => 'decimal:2',
        'ingresos_mostrador'   => 'decimal:2',
        'ingresos_manuales'    => 'decimal:2',
        'total_en_caja'        => 'decimal:2',
        'egresos_compras'      => 'decimal:2',
        'egresos_manuales_tot' => 'decimal:2',
        'total_egresos'        => 'decimal:2',
        'balance_real'         => 'decimal:2',
        'ultima_actividad'     => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
