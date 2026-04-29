<?php

namespace App\Providers;

use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Cotizacion;
use App\Models\EgresoCompra;
use App\Models\EgresoManual;
use App\Models\Empresa;
use App\Models\Factura;
use App\Models\IngresoManual;
use App\Models\IngresoMostrador;
use App\Models\IngresoPago;
use App\Models\Item;
use App\Models\Proveedor;
use App\Models\Usuario;
use App\Observers\ClienteObserver;
use App\Observers\CompraObserver;
use App\Observers\CotizacionObserver;
use App\Observers\EgresoCompraObserver;
use App\Observers\EgresoManualObserver;
use App\Observers\EmpresaObserver;
use App\Observers\FacturaObserver;
use App\Observers\IngresoManualObserver;
use App\Observers\IngresoMostradorObserver;
use App\Observers\IngresoPagoObserver;
use App\Observers\ItemObserver;
use App\Observers\ProveedorObserver;
use App\Observers\UsuarioObserver;
use App\Repositories\Contracts\ClienteRepositoryInterface;
use App\Repositories\ClienteRepository;
use App\Repositories\UsuarioRepository;
use Illuminate\Support\ServiceProvider;
use App\Services\ReporteService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
      
       
        $this->app->bind(ClienteRepositoryInterface::class,          ClienteRepository::class);
    
    
     
    }

    public function boot(): void
    {
        // ── Registrar todos los observers ─────────────────────────────────────
        Empresa::observe(EmpresaObserver::class);
        Usuario::observe(UsuarioObserver::class);
        Cliente::observe(ClienteObserver::class);
        Item::observe(ItemObserver::class);
        Proveedor::observe(ProveedorObserver::class);
        Cotizacion::observe(CotizacionObserver::class);
        Factura::observe(FacturaObserver::class);
        IngresoPago::observe(IngresoPagoObserver::class);
        Compra::observe(CompraObserver::class);
        IngresoManual::observe(IngresoManualObserver::class);
        IngresoMostrador::observe(IngresoMostradorObserver::class);
        EgresoManual::observe(EgresoManualObserver::class);
        EgresoCompra::observe(EgresoCompraObserver::class);
    }
}