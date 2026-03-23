<?php

namespace App\Observers;

use App\Services\ResumenService;

trait ResumenTrait
{
    protected function actualizarResumen(int $empresaId): void
    {
        app(ResumenService::class)->recalcular($empresaId);
    }
}
