<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CotizacionLinea extends Model
{
    protected $table = 'cotizacion_lineas';

    public $timestamps = false;

    protected $fillable = [
        'cotizacion_id',
        'empresa_id',
        'item_id',
        'descripcion_manual',
        'cantidad',
        'valor_unitario',
        'descuento',
        'iva_pct',
        'iva_valor',
        'total_linea',
    ];

    protected $casts = [
        'cantidad'       => 'decimal:3',
        'valor_unitario' => 'decimal:2',
        'descuento'      => 'decimal:2',
        'iva_pct'        => 'decimal:3',
        'iva_valor'      => 'decimal:2',
        'total_linea'    => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELACIONES
    |--------------------------------------------------------------------------
    */

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}