<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'fecha'             => 'date',
        'fecha_vencimiento' => 'date',
        'subtotal'          => 'decimal:2',
        'total_descuentos'  => 'decimal:2',
        'total_iva'         => 'decimal:2',
        'total'             => 'decimal:2',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    /** FIX: relación cliente para devolver nombre en JSON */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(CotizacionLinea::class, 'cotizacion_id');
    }
}