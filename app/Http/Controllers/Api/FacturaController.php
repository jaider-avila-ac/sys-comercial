<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FacturaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FacturaController extends Controller
{
    public function __construct(
        private readonly FacturaService $facturaService,
    ) {}

    // GET /api/facturas
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->facturaService->listar($request->empresa_id_ctx)
        );
    }

    // GET /api/facturas/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->facturaService->obtener($id, $request->empresa_id_ctx)
        );
    }

    // POST /api/facturas
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cliente_id'    => ['required', 'integer'],
            'cotizacion_id' => ['nullable', 'integer'],
            'fecha'         => ['required', 'date'],
            'notas'         => ['nullable', 'string'],
            'lineas'        => ['required', 'array', 'min:1'],
            'lineas.*.item_id'            => ['nullable', 'integer'],
            'lineas.*.descripcion_manual' => ['nullable', 'string', 'max:255'],
            'lineas.*.cantidad'           => ['required', 'integer', 'min:1'],
            'lineas.*.valor_unitario'     => ['required', 'numeric', 'min:0'],
            'lineas.*.descuento'          => ['nullable', 'numeric', 'min:0'],
            'lineas.*.iva_pct'            => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json(
            $this->facturaService->crear(
                $data,
                $request->empresa_id_ctx,
                $request->user()->id,
            ),
            201
        );
    }

    // PUT /api/facturas/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'cliente_id'    => ['sometimes', 'integer'],
            'fecha'         => ['sometimes', 'date'],
            'notas'         => ['nullable', 'string'],
            'lineas'        => ['sometimes', 'array', 'min:1'],
            'lineas.*.item_id'            => ['nullable', 'integer'],
            'lineas.*.descripcion_manual' => ['nullable', 'string', 'max:255'],
            'lineas.*.cantidad'           => ['required_with:lineas', 'integer', 'min:1'],
            'lineas.*.valor_unitario'     => ['required_with:lineas', 'numeric', 'min:0'],
            'lineas.*.descuento'          => ['nullable', 'numeric', 'min:0'],
            'lineas.*.iva_pct'            => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json(
            $this->facturaService->actualizar(
                $id, $data,
                $request->empresa_id_ctx,
                $request->user()->id,
            )
        );
    }

    // POST /api/facturas/{id}/emitir
    public function emitir(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->facturaService->emitir($id, $request->empresa_id_ctx)
        );
    }

    // POST /api/facturas/{id}/anular
    public function anular(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->facturaService->anular($id, $request->empresa_id_ctx)
        );
    }

    // POST /api/facturas/desde-cotizacion/{cotizacionId}
    public function desdeCotizacion(Request $request, int $cotizacionId): JsonResponse
    {
        return response()->json(
            $this->facturaService->convertirDesdeCotizacion(
                $cotizacionId,
                $request->empresa_id_ctx,
                $request->user()->id,
            ),
            201
        );
    }

    // DELETE /api/facturas/{id}
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->facturaService->eliminar($id, $request->empresa_id_ctx);
        return response()->json(['message' => 'Factura eliminada correctamente.']);
    }
}