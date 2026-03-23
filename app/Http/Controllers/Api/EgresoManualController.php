<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EgresoManualService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EgresoManualController extends Controller
{
    public function __construct(
        private readonly EgresoManualService $egresoManualService,
    ) {}

    // GET /api/egresos/manuales
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->egresoManualService->listar($request->empresa_id_ctx)
        );
    }

    // GET /api/egresos/manuales/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->egresoManualService->obtener($id, $request->empresa_id_ctx)
        );
    }

    // POST /api/egresos/manuales
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fecha'       => ['required', 'date'],
            'descripcion' => ['required', 'string', 'max:255'],
            'monto'       => ['required', 'numeric', 'min:0.01'],
            'notas'       => ['nullable', 'string'],
        ]);

        return response()->json(
            $this->egresoManualService->registrar(
                $data,
                $request->empresa_id_ctx,
                $request->user()->id,
            ),
            201
        );
    }

    // POST /api/egresos/manuales/{id}/anular
    public function anular(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->egresoManualService->anular($id, $request->empresa_id_ctx)
        );
    }
}
