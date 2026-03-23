<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PagoController extends Controller
{
    public function __construct(
        private readonly PagoService $pagoService,
    ) {}

    // GET /api/pagos
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->pagoService->listar($request->empresa_id_ctx)
        );
    }

    // GET /api/pagos/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->pagoService->obtener($id, $request->empresa_id_ctx)
        );
    }

    // GET /api/facturas/{id}/pagos
    public function porFactura(int $id): JsonResponse
    {
        return response()->json(
            $this->pagoService->pagosPorFactura($id)
        );
    }

    // POST /api/pagos
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'factura_id'  => ['required', 'integer'],
            'monto'       => ['required', 'numeric', 'min:0.01'],
            'fecha'       => ['required', 'date'],
            'forma_pago'  => ['required', 'in:EFECTIVO,TRANSFERENCIA,TARJETA,BILLETERA,OTRO'],
            'referencia'  => ['nullable', 'string', 'max:80'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'notas'       => ['nullable', 'string'],
        ]);

        return response()->json(
            $this->pagoService->registrar(
                $data,
                $request->empresa_id_ctx,
                $request->user()->id,
            ),
            201
        );
    }

    // POST /api/pagos/{id}/anular
    public function anular(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->pagoService->anular($id, $request->empresa_id_ctx)
        );
    }
}