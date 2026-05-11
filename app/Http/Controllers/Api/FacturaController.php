<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FacturaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FacturaController extends Controller
{
    public function __construct(
        private readonly FacturaService $facturaService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search'     => $request->query('search'),
            'estado'     => $request->query('estado'),
            'cliente_id' => $request->query('cliente_id'),
            'desde'      => $request->query('desde'),
            'hasta'      => $request->query('hasta'),
            'pendiente'  => $request->query('pendiente'),
        ];

        $perPage  = (int) $request->query('per_page', 20);
        $empresaId = $request->empresa_id_ctx;

        $paginado = $this->facturaService->listar($empresaId, $filters, $perPage);

        $totales = DB::table('facturas')
            ->where('empresa_id', $empresaId)
            ->whereIn('estado', ['EMITIDA', 'BORRADOR'])
            ->selectRaw("
                COUNT(*) as total_activas,
                SUM(CASE WHEN estado = 'EMITIDA' THEN 1 ELSE 0 END) as total_emitidas,
                SUM(CASE WHEN estado = 'BORRADOR' THEN 1 ELSE 0 END) as total_borrador,
                COALESCE(SUM(saldo), 0) as total_saldo_pendiente,
                COALESCE(SUM(total_pagado), 0) as total_pagado
            ")
            ->first();

        return response()->json([
            'data'         => $paginado->items(),
            'current_page' => $paginado->currentPage(),
            'last_page'    => $paginado->lastPage(),
            'per_page'     => $paginado->perPage(),
            'total'        => $paginado->total(),
            'totales'      => $totales,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json([
            'factura' => $this->facturaService->obtener($id, $request->empresa_id_ctx)
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cliente_id'    => ['required', 'integer'],
            'cotizacion_id' => ['nullable', 'integer'],
            'fecha'         => ['required', 'date'],
            'notas'         => ['nullable', 'string'],
            'lineas'        => ['required', 'array', 'min:1'],

            'lineas.*.item_id'            => ['required', 'integer'],
            'lineas.*.descripcion_manual' => ['required', 'string', 'max:255'],
            'lineas.*.cantidad'           => ['required', 'integer', 'min:1'],
            'lineas.*.valor_unitario'     => ['required', 'numeric', 'min:0'],
            'lineas.*.descuento'          => ['nullable', 'numeric', 'min:0'],
            'lineas.*.iva_pct'            => ['nullable', 'numeric', 'min:0'],
        ]);

        $factura = $this->facturaService->crear(
            $data,
            $request->empresa_id_ctx,
            $request->user()->id,
        );

        return response()->json([
            'message' => 'Factura creada correctamente.',
            'factura' => $factura,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'cliente_id'    => ['sometimes', 'integer'],
            'fecha'         => ['sometimes', 'date'],
            'notas'         => ['nullable', 'string'],
            'lineas'        => ['sometimes', 'array', 'min:1'],

            'lineas.*.item_id'            => ['required_with:lineas', 'integer'],
            'lineas.*.descripcion_manual' => ['required_with:lineas', 'string', 'max:255'],
            'lineas.*.cantidad'           => ['required_with:lineas', 'integer', 'min:1'],
            'lineas.*.valor_unitario'     => ['required_with:lineas', 'numeric', 'min:0'],
            'lineas.*.descuento'          => ['nullable', 'numeric', 'min:0'],
            'lineas.*.iva_pct'            => ['nullable', 'numeric', 'min:0'],
        ]);

        $factura = $this->facturaService->actualizar(
            $id,
            $data,
            $request->empresa_id_ctx,
            $request->user()->id,
        );

        return response()->json([
            'message' => 'Factura actualizada correctamente.',
            'factura' => $factura,
        ]);
    }

    public function emitir(Request $request, int $id): JsonResponse
    {
        $factura = $this->facturaService->emitir($id, $request->empresa_id_ctx);

        return response()->json([
            'message' => 'Factura emitida correctamente.',
            'factura' => $factura,
        ]);
    }

    public function anular(Request $request, int $id): JsonResponse
    {
        $factura = $this->facturaService->anular($id, $request->empresa_id_ctx);

        return response()->json([
            'message' => 'Factura anulada correctamente.',
            'factura' => $factura,
        ]);
    }

    public function desdeCotizacion(Request $request, int $cotizacionId): JsonResponse
    {
        $factura = $this->facturaService->convertirDesdeCotizacion(
            $cotizacionId,
            $request->empresa_id_ctx,
            $request->user()->id,
        );

        return response()->json([
            'message' => 'Factura creada desde cotización.',
            'factura' => $factura,
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->facturaService->eliminar($id, $request->empresa_id_ctx);

        return response()->json([
            'message' => 'Factura eliminada correctamente.'
        ]);
    }
}