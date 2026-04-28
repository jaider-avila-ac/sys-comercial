<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EgresoCompraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EgresoCompraController extends Controller
{
    public function __construct(
        private readonly EgresoCompraService $egresoCompraService,
    ) {}

    // GET /api/egresos/compras
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->egresoCompraService->listar($request->empresa_id_ctx)
        );
    }

    // GET /api/egresos/compras/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->egresoCompraService->obtener($id, $request->empresa_id_ctx)
        );
    }

    // GET /api/compras/{compraId}/egresos
    public function porCompra(int $compraId): JsonResponse
    {
        return response()->json(
            $this->egresoCompraService->listarPorCompra($compraId)
        );
    }

    // POST /api/egresos/compras
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'compra_id'   => ['nullable', 'integer'],
            'fecha'       => ['required', 'date'],
            'descripcion' => ['required', 'string', 'max:255'],
            'monto'       => ['required', 'numeric', 'min:0.01'],
            'medio_pago'  => ['required', 'string', 'max:50'],
            'notas'       => ['nullable', 'string'],
        ]);
        
        // Manejar archivo si existe
        $archivoData = null;
        if ($request->hasFile('archivo')) {
            $archivo = $request->file('archivo');
            $path = $archivo->store('comprobantes/egresos', 'public');
            $archivoData = [
                'path' => $path,
                'mime' => $archivo->getMimeType(),
                'nombre' => $archivo->getClientOriginalName(),
            ];
        }

        return response()->json(
            $this->egresoCompraService->registrar(
                $data,
                $request->empresa_id_ctx,
                $request->user()->id,
                $archivoData
            ),
            201
        );
    }

    // POST /api/egresos/compras/{id}/anular
    public function anular(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->egresoCompraService->anular($id, $request->empresa_id_ctx)
        );
    }
}