<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrevoConfig extends Model
{
    protected $table = 'brevo_config';

    protected $fillable = [
        'empresa_id',
        'is_activo',
        'api_key',
        'sender_name',
        'sender_email',
        'template_id',
    ];

    protected $casts = [
        'is_activo'   => 'boolean',
        'template_id' => 'integer',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    // Nunca exponer api_key en respuestas normales
    protected $hidden = ['api_key'];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}