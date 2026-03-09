<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmpresaController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\InventarioController;
use App\Http\Controllers\Api\CotizacionController;
use App\Http\Controllers\Api\FacturaController;
use App\Http\Controllers\Api\PagoController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReportesController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\AuditoriaController;
use App\Http\Controllers\Api\IngresoController;
use App\Http\Controllers\Api\EgresoController;
use App\Http\Controllers\Api\BrevoConfigController;



Route::get('/ping', fn() => ['ok' => true]);

// Auth
Route::post('/auth/login',  [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout']);
Route::middleware('auth:sanctum')->get('/auth/me', [AuthController::class, 'me']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('brevo/config', [BrevoConfigController::class, 'show']);
    Route::post('brevo/config', [BrevoConfigController::class, 'upsert']);
    Route::post('brevo/test',   [BrevoConfigController::class, 'test']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Empresa
    Route::get('/empresa/me',          [EmpresaController::class, 'me']);
    Route::get('/empresas',             [EmpresaController::class, 'index']);
    Route::post('/empresas',             [EmpresaController::class, 'store']);
    Route::get('/empresas/{id}',        [EmpresaController::class, 'show']);
    Route::put('/empresas/{id}',        [EmpresaController::class, 'update']);
    Route::delete('/empresas/{id}',        [EmpresaController::class, 'destroy']);
    // Logo
    Route::post('/empresas/{id}/logo',   [EmpresaController::class, 'uploadLogo']);
    Route::delete('/empresas/{id}/logo',   [EmpresaController::class, 'deleteLogo']);

    // Clientes
    Route::get('/clientes',       [ClienteController::class, 'index']);
    Route::post('/clientes',       [ClienteController::class, 'store']);
    Route::get('/clientes/{id}',  [ClienteController::class, 'show']);
    Route::put('/clientes/{id}',  [ClienteController::class, 'update']);
    Route::delete('/clientes/{id}',  [ClienteController::class, 'destroy']);

    // Items
    Route::get('/items',       [ItemController::class, 'index']);
    Route::post('/items',       [ItemController::class, 'store']);
    Route::get('/items/{id}',  [ItemController::class, 'show']);
    Route::put('/items/{id}',  [ItemController::class, 'update']);
    Route::delete('/items/{id}',  [ItemController::class, 'destroy']);

    // Inventario
    Route::get('/inventario',             [InventarioController::class, 'index']);
    Route::post('/inventario/ajustar',     [InventarioController::class, 'ajustar']);
    Route::get('/inventario/movimientos', [InventarioController::class, 'movimientos']);

    // Cotizaciones
    Route::get('/cotizaciones',       [CotizacionController::class, 'index']);
    Route::post('/cotizaciones',       [CotizacionController::class, 'store']);
    Route::get('/cotizaciones/{id}',  [CotizacionController::class, 'show']);
    Route::put('/cotizaciones/{id}',  [CotizacionController::class, 'update']);
    Route::delete('/cotizaciones/{id}',  [CotizacionController::class, 'destroy']);

    Route::post('/cotizaciones/{id}/emitir',            [CotizacionController::class, 'emitir']);
    Route::post('/cotizaciones/{id}/anular',            [CotizacionController::class, 'anular']);
    Route::post('/cotizaciones/{id}/marcar-vencida',    [CotizacionController::class, 'marcarVencida']);
    Route::post('/cotizaciones/{id}/confirmar-vigencia', [CotizacionController::class, 'confirmarVigencia']);
    Route::post('/cotizaciones/{id}/convertir-factura', [CotizacionController::class, 'convertirFactura']);


    // Facturas
    Route::get('/facturas',              [FacturaController::class, 'index']);
    Route::post('/facturas',              [FacturaController::class, 'store']);
    Route::get('/facturas/{id}',         [FacturaController::class, 'show']);
    Route::put('/facturas/{id}',         [FacturaController::class, 'update']);
    Route::delete('/facturas/{id}',         [FacturaController::class, 'destroy']);

    Route::post('/facturas/{id}/emitir',  [FacturaController::class, 'emitir']);
    Route::post('/facturas/{id}/anular',  [FacturaController::class, 'anular']);
    Route::get('/facturas/{id}/pagos',   [FacturaController::class, 'pagos']);
    Route::post('/facturas/{id}/pagos',   [FacturaController::class, 'registrarPago']);


    // Módulo Pagos — rutas fijas ANTES del parámetro {id}
    Route::get('/pagos/resumen',             [PagoController::class, 'resumen']);
    Route::get('/pagos/facturas-pendientes', [PagoController::class, 'facturasPendientes']);
    Route::get('/pagos',                     [PagoController::class, 'index']);


    // Reportes (nuevo)
    Route::get('/reportes/ventas-lineas',   [ReportesController::class, 'ventasLineas']);
    Route::get('/reportes/ventas-resumen',  [ReportesController::class, 'ventasResumen']);
    Route::get('/reportes/recaudos-resumen', [ReportesController::class, 'recaudosResumen']);
    Route::get('/reportes/saldo-al-cierre', [ReportesController::class, 'saldoAlCierre']);

    // Opcional: mensual
    Route::get('/reportes/flujo-mensual',   [ReportesController::class, 'flujoMensual']);


    // ── Egresos ───────────────────────────────────────────────────
    Route::get('egresos',       [EgresoController::class, 'index']);
    Route::post('egresos',       [EgresoController::class, 'store']);
    Route::get('egresos/{id}',  [EgresoController::class, 'show']);
    Route::post('egresos/{id}',  [EgresoController::class, 'update']);   // POST porque lleva archivo
    Route::delete('egresos/{id}',  [EgresoController::class, 'destroy']);

    // ── Ingresos ──────────────────────────────────────────────────
    Route::get('ingresos/resumen',           [IngresoController::class, 'resumen']);
    Route::get('ingresos/pagos', [IngresoController::class, 'pagos']);
    Route::get('ingresos/manuales',          [IngresoController::class, 'index']);
    Route::post('ingresos/manuales',          [IngresoController::class, 'store']);
    Route::put('ingresos/manuales/{id}',     [IngresoController::class, 'update']);
    Route::delete('ingresos/manuales/{id}',     [IngresoController::class, 'destroy']);


    Route::get('/usuarios',                    [UsuarioController::class, 'index']);
    Route::post('/usuarios',                    [UsuarioController::class, 'store']);
    Route::get('/usuarios/activos-ahora',      [UsuarioController::class, 'activosAhora']); // ← antes de {id}
    Route::get('/usuarios/{id}',               [UsuarioController::class, 'show']);
    Route::put('/usuarios/{id}',               [UsuarioController::class, 'update']);
    Route::patch('/usuarios/{id}/toggle',        [UsuarioController::class, 'toggle']);
    Route::patch('/usuarios/{id}/password',      [UsuarioController::class, 'changePassword']);
    Route::get('/usuarios/{id}/auditoria',     [UsuarioController::class, 'auditoria']);
    Route::get('/auditoria', [AuditoriaController::class, 'index']);
    Route::get('/usuarios/{id}/sesiones', [UsuarioController::class, 'sesiones']);
});
