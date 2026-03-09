<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacturaLinea extends Model
{
    protected $table = 'factura_lineas';

    public $timestamps = false;

    protected $fillable = [
        'factura_id',
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

    // Factura a la que pertenece la línea
    public function factura()
    {
        return $this->belongsTo(Factura::class, 'factura_id');
    }

    // Producto / servicio del catálogo
    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}