<?php

use App\Http\Controllers\Api\AuditoriaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\CotizacionController;
use App\Http\Controllers\Api\EgresoCompraController;
use App\Http\Controllers\Api\EgresoManualController;
use App\Http\Controllers\Api\EmpresaController;
use App\Http\Controllers\Api\FacturaController;
use App\Http\Controllers\Api\IngresoManualController;
use App\Http\Controllers\Api\IngresoMostradorController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\PagoController;
use App\Http\Controllers\Api\ProveedorController;
use App\Http\Controllers\Api\SesionController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\BrevoConfigController;
use App\Http\Middleware\ResolveEmpresaContext;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\InventarioMovimientoController;
use App\Http\Controllers\Api\CompraController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\IngresoUnificadoController;


use Illuminate\Support\Facades\Route;

Route::get('/ping', fn() => ['ok' => true]);

Route::prefix('auth')->group(function () {
    Route::post('iniciar',   [AuthController::class, 'iniciar']);
    Route::post('verificar', [AuthController::class, 'verificar']);
});

Route::middleware(['auth:sanctum', ResolveEmpresaContext::class])->group(function () {



    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me',      [AuthController::class, 'me']);
    });

    Route::get('empresa/me', [EmpresaController::class, 'me']);
    Route::prefix('empresas')->group(function () {
        Route::get('/',             [EmpresaController::class, 'index']);
        Route::post('/',            [EmpresaController::class, 'store']);
        Route::get('/{id}',         [EmpresaController::class, 'show']);
        Route::put('/{id}',         [EmpresaController::class, 'update']);
        Route::delete('/{id}',      [EmpresaController::class, 'destroy']);
        Route::post('/{id}/logo',   [EmpresaController::class, 'uploadLogo']);
        Route::delete('/{id}/logo', [EmpresaController::class, 'deleteLogo']);
    });

    Route::prefix('usuarios')->group(function () {
    Route::get('/',                [UsuarioController::class, 'index']);
    Route::post('/',               [UsuarioController::class, 'store']);
    // ✅ Las rutas fijas van PRIMERO
    Route::get('/activos-ahora',   [UsuarioController::class, 'activosAhora']);
    // ✅ Las rutas con {id} van al FINAL
    Route::get('/{id}',            [UsuarioController::class, 'show']);
    Route::put('/{id}',            [UsuarioController::class, 'update']);
    Route::patch('/{id}/toggle',   [UsuarioController::class, 'toggle']);
    Route::patch('/{id}/password', [UsuarioController::class, 'changePassword']);
    Route::get('/{id}/auditoria',  [AuditoriaController::class, 'porUsuario']);
    Route::get('/{id}/sesiones',   [SesionController::class,    'porUsuario']);
});

    Route::prefix('reportes')->group(function () {
    Route::get('/financiero', [ReporteController::class, 'financiero']);
});

    Route::prefix('clientes')->group(function () {
        Route::get('/',              [ClienteController::class, 'index']);
        Route::post('/',             [ClienteController::class, 'store']);
        Route::get('/{id}',          [ClienteController::class, 'show']);
        Route::put('/{id}',          [ClienteController::class, 'update']);
        Route::patch('/{id}/toggle', [ClienteController::class, 'toggle']);
        Route::delete('/{id}',       [ClienteController::class, 'destroy']);
    });

    Route::prefix('inventario')->group(function () {
    Route::get('/movimientos', [InventarioMovimientoController::class, 'index']);
});

Route::prefix('ingresos')->group(function () {
    Route::get('/unificados', [IngresoUnificadoController::class, 'index']);
});

    Route::prefix('items')->group(function () {
        Route::get('/',              [ItemController::class, 'index']);
        Route::post('/',             [ItemController::class, 'store']);
        Route::get('/{id}',          [ItemController::class, 'show']);
        Route::put('/{id}',          [ItemController::class, 'update']);
        Route::patch('/{id}/toggle', [ItemController::class, 'toggle']);
        Route::delete('/{id}',       [ItemController::class, 'destroy']);
    });

    Route::prefix('proveedores')->group(function () {
        Route::get('/',              [ProveedorController::class, 'index']);
        Route::post('/',             [ProveedorController::class, 'store']);
        Route::get('/{id}',          [ProveedorController::class, 'show']);
        Route::put('/{id}',          [ProveedorController::class, 'update']);
        Route::patch('/{id}/toggle', [ProveedorController::class, 'toggle']);
        Route::delete('/{id}',       [ProveedorController::class, 'destroy']);
    });

    Route::prefix('cotizaciones')->group(function () {
        Route::get('/',             [CotizacionController::class, 'index']);
        Route::post('/',            [CotizacionController::class, 'store']);
        Route::get('/{id}',         [CotizacionController::class, 'show']);
        Route::put('/{id}',         [CotizacionController::class, 'update']);
        Route::post('/{id}/emitir', [CotizacionController::class, 'emitir']);
        Route::post('/{id}/anular', [CotizacionController::class, 'anular']);
        Route::delete('/{id}',      [CotizacionController::class, 'destroy']);
    });

    Route::prefix('facturas')->group(function () {
        Route::get('/',                                 [FacturaController::class, 'index']);
        Route::post('/',                                [FacturaController::class, 'store']);
        Route::get('/{id}',                             [FacturaController::class, 'show']);
        Route::put('/{id}',                             [FacturaController::class, 'update']);
        Route::post('/{id}/emitir',                     [FacturaController::class, 'emitir']);
        Route::post('/{id}/anular',                     [FacturaController::class, 'anular']);
        Route::delete('/{id}',                          [FacturaController::class, 'destroy']);
        Route::post('/desde-cotizacion/{cotizacionId}', [FacturaController::class, 'desdeCotizacion']);
        Route::get('/{id}/pagos',                       [PagoController::class,    'porFactura']);
    });

    Route::prefix('pagos')->group(function () {
        Route::get('/',             [PagoController::class, 'index']);
        Route::post('/',            [PagoController::class, 'store']);
        Route::get('/{id}',         [PagoController::class, 'show']);
        Route::post('/{id}/anular', [PagoController::class, 'anular']);
    });

   Route::prefix('egresos/manuales')->group(function () {
    Route::get('/',             [EgresoManualController::class, 'index']);
    Route::post('/',            [EgresoManualController::class, 'store']);
    Route::get('/{id}',         [EgresoManualController::class, 'show']);
    Route::put('/{id}',         [EgresoManualController::class, 'update']);   
    Route::post('/{id}/anular', [EgresoManualController::class, 'anular']);
});

    Route::prefix('egresos/compras')->group(function () {
        Route::get('/',             [EgresoCompraController::class, 'index']);
        Route::post('/',            [EgresoCompraController::class, 'store']);
        Route::get('/{id}',         [EgresoCompraController::class, 'show']);
        Route::post('/{id}/anular', [EgresoCompraController::class, 'anular']);
    });

    Route::prefix('compras')->group(function () {
    Route::get('/', [CompraController::class, 'index']);
    Route::get('/cuentas-por-pagar', [CompraController::class, 'cuentasPorPagar']);
    Route::get('/{id}', [CompraController::class, 'show']);
    Route::post('/', [CompraController::class, 'store']);
    Route::post('/{id}/confirmar', [CompraController::class, 'confirmar']);
    Route::post('/{id}/pagar', [CompraController::class, 'pagar']);
    Route::post('/{id}/anular', [CompraController::class, 'anular']);
});

    

    Route::prefix('ingresos/manuales')->group(function () {
    Route::get('/',             [IngresoManualController::class, 'index']);
    Route::post('/',            [IngresoManualController::class, 'store']);
    Route::get('/{id}',         [IngresoManualController::class, 'show']);
    Route::put('/{id}',         [IngresoManualController::class, 'update']);   
    Route::delete('/{id}',      [IngresoManualController::class, 'destroy']);  
    Route::post('/{id}/anular', [IngresoManualController::class, 'anular']);
});

    Route::prefix('ingresos/mostrador')->group(function () {
        Route::get('/',             [IngresoMostradorController::class, 'index']);
        Route::post('/',            [IngresoMostradorController::class, 'store']);
        Route::get('/{id}',         [IngresoMostradorController::class, 'show']);
        Route::post('/{id}/anular', [IngresoMostradorController::class, 'anular']);
    });

    // Auditoría
    Route::get('auditoria', [AuditoriaController::class, 'index']);
Route::post('/stock/verificar', [StockController::class, 'verificar']);
    // Sesiones
    Route::prefix('sesiones')->group(function () {
        Route::get('activas',        [SesionController::class, 'activas']);
        Route::get('historial',      [SesionController::class, 'historial']);
        Route::get('usuario/{id}',   [SesionController::class, 'porUsuario']);
    });

    Route::prefix('egresos/compras')->group(function () {
    Route::get('/',             [EgresoCompraController::class, 'index']);
    Route::post('/',            [EgresoCompraController::class, 'store']);
    Route::get('/{id}',         [EgresoCompraController::class, 'show']);
    Route::post('/{id}/anular', [EgresoCompraController::class, 'anular']);
    // 👇 AGREGAR ESTA LÍNEA
    Route::get('/por-compra/{compraId}', [EgresoCompraController::class, 'porCompra']);
});

    // Dashboard empresa (cualquier rol autenticado)
    Route::get('dashboard', [DashboardController::class, 'index']);

    // Dashboard SUPER_ADMIN — todas las empresas
    Route::get('dashboard/empresas', [DashboardController::class, 'todasLasEmpresas']);


    Route::get('brevo/config',  [BrevoConfigController::class, 'show']);
    Route::post('brevo/config',  [BrevoConfigController::class, 'upsert']);
    Route::post('brevo/test',    [BrevoConfigController::class, 'test']);
});
