<?php

namespace App\Providers;

use App\Models\Empresa;
use App\Policies\EmpresaPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Empresa::class => EmpresaPolicy::class,
    ];

    public function boot(): void
    {
        //
    }
}