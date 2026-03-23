<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CompraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompraController extends Controller
{
    public function __construct(
        private readonly CompraService $compraService,
    ) {}

    // GET /api/compras
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->compraService->listar($request->empresa_id_ctx)
        );
    }

    // GET /api/compras/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->compraService->obtener($id, $request->empresa_id_ctx)
        );
    }

    // POST /api/compras
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fecha'                      => ['required', 'date'],
            'proveedor_id'               => ['nullable', 'integer'],
            'condicion_pago'             => ['nullable', 'in:CONTADO,CREDITO'],
            'fecha_vencimiento'          => ['nullable', 'date', 'after_or_equal:fecha'],
            'impuestos'                  => ['nullable', 'numeric', 'min:0'],
            'notas'                      => ['nullable', 'string'],
            'items'                      => ['required', 'array', 'min:1'],
            'items.*.item_id'            => ['required', 'integer'],
            'items.*.cantidad'           => ['required', 'integer', 'min:1'],
            'items.*.precio_unitario'    => ['required', 'numeric', 'min:0'],
        ]);

        return response()->json(
            $this->compraService->crear(
                $data,
                $request->empresa_id_ctx,
                $request->user()->id,
            ),
            201
        );
    }

    // POST /api/compras/{id}/confirmar
    public function confirmar(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->compraService->confirmar(
                $id,
                $request->empresa_id_ctx,
                $request->user()->id,
            )
        );
    }

    // POST /api/compras/{id}/pagar
    public function pagar(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'monto'       => ['required', 'numeric', 'min:0.01'],
            'fecha'       => ['required', 'date'],
            'medio_pago'  => ['required', 'string', 'max:50'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'notas'       => ['nullable', 'string'],
        ]);

        return response()->json(
            $this->compraService->registrarPago(
                $id,
                $data,
                $request->empresa_id_ctx,
                $request->user()->id,
            )
        );
    }

    // POST /api/compras/{id}/anular
    public function anular(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->compraService->anular(
                $id,
                $request->empresa_id_ctx,
                $request->user()->id,
            )
        );
    }
}