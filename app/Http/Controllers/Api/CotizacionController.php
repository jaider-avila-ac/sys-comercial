<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CotizacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CotizacionController extends Controller
{
    public function __construct(
        private readonly CotizacionService $cotizacionService,
    ) {}

    public function index(Request $request): JsonResponse
{
    return response()->json(
        $this->cotizacionService->listar(
            $request->empresa_id_ctx,
            $request->only(['search', 'estado'])
        )
    );
}

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json([
            'cotizacion' => $this->cotizacionService->obtener($id, $request->empresa_id_ctx),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cliente_id'                  => ['required', 'integer'],
            'fecha'                       => ['required', 'date'],
            'fecha_vencimiento'           => ['nullable', 'date', 'after_or_equal:fecha'],
            'notas'                       => ['nullable', 'string'],
            'lineas'                      => ['required', 'array', 'min:1'],
            'lineas.*.item_id'            => ['nullable', 'integer'],
            'lineas.*.descripcion_manual' => ['nullable', 'string', 'max:255'],
            'lineas.*.cantidad'           => ['required', 'integer', 'min:1'],
            'lineas.*.valor_unitario'     => ['required', 'numeric', 'min:0'],
            'lineas.*.descuento'          => ['nullable', 'numeric', 'min:0'],
            'lineas.*.iva_pct'            => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json([
            'cotizacion' => $this->cotizacionService->crear(
                $data,
                $request->empresa_id_ctx,
                $request->user()->id,
            ),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'cliente_id'                  => ['sometimes', 'integer'],
            'fecha'                       => ['sometimes', 'date'],
            'fecha_vencimiento'           => ['nullable', 'date'],
            'notas'                       => ['nullable', 'string'],
            'lineas'                      => ['sometimes', 'array', 'min:1'],
            'lineas.*.item_id'            => ['nullable', 'integer'],
            'lineas.*.descripcion_manual' => ['nullable', 'string', 'max:255'],
            'lineas.*.cantidad'           => ['required_with:lineas', 'integer', 'min:1'],
            'lineas.*.valor_unitario'     => ['required_with:lineas', 'numeric', 'min:0'],
            'lineas.*.descuento'          => ['nullable', 'numeric', 'min:0'],
            'lineas.*.iva_pct'            => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json([
            'cotizacion' => $this->cotizacionService->actualizar(
                $id,
                $data,
                $request->empresa_id_ctx,
                $request->user()->id,
            ),
        ]);
    }

    public function emitir(Request $request, int $id): JsonResponse
    {
        return response()->json([
            'cotizacion' => $this->cotizacionService->emitir($id, $request->empresa_id_ctx),
        ]);
    }

    public function anular(Request $request, int $id): JsonResponse
    {
        return response()->json([
            'cotizacion' => $this->cotizacionService->anular($id, $request->empresa_id_ctx),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->cotizacionService->eliminar($id, $request->empresa_id_ctx);
        return response()->json(['message' => 'Cotización eliminada correctamente.']);
    }
}