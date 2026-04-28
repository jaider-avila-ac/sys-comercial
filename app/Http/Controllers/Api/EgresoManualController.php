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

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->egresoManualService->listar(
                $request->empresa_id_ctx,
                $request->only(['search', 'desde', 'hasta', 'estado'])
            )
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->egresoManualService->obtener($id, $request->empresa_id_ctx)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'descripcion' => ['required', 'string', 'max:255'],
            'monto'       => ['required', 'numeric', 'min:0.01'],
            'notas'       => ['nullable', 'string'],
            'archivo'     => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
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

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'descripcion' => ['sometimes', 'string', 'max:255'],
            'monto'       => ['sometimes', 'numeric', 'min:0.01'],
            'notas'       => ['nullable', 'string'],
            'archivo'     => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);

        return response()->json(
            $this->egresoManualService->actualizar($id, $data, $request->empresa_id_ctx)
        );
    }

    public function anular(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->egresoManualService->anular($id, $request->empresa_id_ctx)
        );
    }
}