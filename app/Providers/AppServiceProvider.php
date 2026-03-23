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
use App\Repositories\Contracts\CotizacionRepositoryInterface;
use App\Repositories\Contracts\EgresoCompraRepositoryInterface;
use App\Repositories\Contracts\EgresoManualRepositoryInterface;
use App\Repositories\Contracts\EmpresaRepositoryInterface;
use App\Repositories\Contracts\FacturaRepositoryInterface;
use App\Repositories\Contracts\IngresoManualRepositoryInterface;
use App\Repositories\Contracts\IngresoMostradorRepositoryInterface;
use App\Repositories\Contracts\ItemRepositoryInterface;
use App\Repositories\Contracts\NumeracionRepositoryInterface;
use App\Repositories\Contracts\PagoRepositoryInterface;
use App\Repositories\Contracts\ProveedorRepositoryInterface;
use App\Repositories\Contracts\UsuarioRepositoryInterface;
use App\Repositories\ClienteRepository;
use App\Repositories\CotizacionRepository;
use App\Repositories\EgresoCompraRepository;
use App\Repositories\EgresoManualRepository;
use App\Repositories\EmpresaRepository;
use App\Repositories\FacturaRepository;
use App\Repositories\IngresoManualRepository;
use App\Repositories\IngresoMostradorRepository;
use App\Repositories\ItemRepository;
use App\Repositories\NumeracionRepository;
use App\Repositories\PagoRepository;
use App\Repositories\ProveedorRepository;
use App\Repositories\UsuarioRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UsuarioRepositoryInterface::class,          UsuarioRepository::class);
        $this->app->bind(EmpresaRepositoryInterface::class,          EmpresaRepository::class);
        $this->app->bind(ClienteRepositoryInterface::class,          ClienteRepository::class);
        $this->app->bind(ItemRepositoryInterface::class,             ItemRepository::class);
        $this->app->bind(NumeracionRepositoryInterface::class,       NumeracionRepository::class);
        $this->app->bind(ProveedorRepositoryInterface::class,        ProveedorRepository::class);
        $this->app->bind(CotizacionRepositoryInterface::class,       CotizacionRepository::class);
        $this->app->bind(FacturaRepositoryInterface::class,          FacturaRepository::class);
        $this->app->bind(PagoRepositoryInterface::class,             PagoRepository::class);
        $this->app->bind(EgresoManualRepositoryInterface::class,     EgresoManualRepository::class);
        $this->app->bind(EgresoCompraRepositoryInterface::class,     EgresoCompraRepository::class);
        $this->app->bind(IngresoManualRepositoryInterface::class,    IngresoManualRepository::class);
        $this->app->bind(IngresoMostradorRepositoryInterface::class, IngresoMostradorRepository::class);
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