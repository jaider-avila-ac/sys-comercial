<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ResolvesEmpresa;
use App\Services\IndicadoresComercialesService;
use App\Models\Factura;
use App\Models\Pago;
use Illuminate\Http\Request;

class PagoController extends Controller
{
    use ResolvesEmpresa;

    public function __construct(
        private IndicadoresComercialesService $indicadores
    ) {}

    public function resumen(Request $request)
    {
        $empresaId = $this->resolveEmpresaId($request);
        $hoy       = now()->toDateString();
        $inicioMes = now()->startOfMonth()->toDateString();

        return response()->json(
            $this->indicadores->resumenIngresos($empresaId, $inicioMes, $hoy)
        );
    }

    public function index(Request $request)
    {
        $empresaId = $this->resolveEmpresaId($request);

        $pagos = Pago::with([
                'cliente',
                'aplicaciones.factura',
            ])
            ->when($empresaId, fn($q) => $q->where('empresa_id', $empresaId))
            ->when($request->search, fn($q, $s) =>
                $q->where('numero_recibo', 'like', "%$s%")
            )
            ->when($request->forma_pago, fn($q, $f) =>
                $q->where('forma_pago', $f)
            )
            ->when($request->fecha_desde, fn($q, $d) =>
                $q->whereDate('fecha', '>=', $d)
            )
            ->when($request->fecha_hasta, fn($q, $h) =>
                $q->whereDate('fecha', '<=', $h)
            )
            ->latest('fecha')
            ->latest('id')
            ->paginate(100);

        return response()->json($pagos);
    }

    public function facturasPendientes(Request $request)
    {
        $empresaId = $this->resolveEmpresaId($request);

        $facturas = Factura::with('cliente')
            ->where('estado', 'EMITIDA')
            ->where('saldo', '>', 0)
            ->when($empresaId, fn($q) => $q->where('empresa_id', $empresaId))
            ->when($request->search, fn($q, $s) =>
                $q->where(function ($q) use ($s) {
                    $q->where('numero', 'like', "%$s%")
                      ->orWhereHas('cliente', fn($q) =>
                          $q->where('nombre_razon_social', 'like', "%$s%")
                      );
                })
            )
            ->latest('fecha')
            ->paginate(200);

        return response()->json($facturas);
    }
}