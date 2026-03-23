<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\IngresoManualService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngresoManualController extends Controller
{
    public function __construct(
        private readonly IngresoManualService $ingresoManualService,
    ) {}

    // GET /api/ingresos/manuales
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->ingresoManualService->listar($request->empresa_id_ctx)
        );
    }

    // GET /api/ingresos/manuales/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->ingresoManualService->obtener($id, $request->empresa_id_ctx)
        );
    }

    // POST /api/ingresos/manuales
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fecha'       => ['required', 'date'],
            'descripcion' => ['required', 'string', 'max:255'],
            'monto'       => ['required', 'numeric', 'min:0.01'],
            'notas'       => ['nullable', 'string'],
        ]);

        return response()->json(
            $this->ingresoManualService->registrar(
                $data,
                $request->empresa_id_ctx,
                $request->user()->id,
            ),
            201
        );
    }

    // POST /api/ingresos/manuales/{id}/anular
    public function anular(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->ingresoManualService->anular($id, $request->empresa_id_ctx)
        );
    }
}
