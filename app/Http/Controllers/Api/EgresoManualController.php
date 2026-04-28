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
        ]);
        
        // Manejar archivo si existe
        $archivoData = null;
        if ($request->hasFile('archivo')) {
            $archivo = $request->file('archivo');
            $path = $archivo->store('comprobantes/egresos_manuales', 'public');
            $archivoData = [
                'path' => $path,
                'mime' => $archivo->getMimeType(),
                'nombre' => $archivo->getClientOriginalName(),
            ];
        }

        return response()->json(
            $this->egresoManualService->registrar(
                $data,
                $request->empresa_id_ctx,
                $request->user()->id,
                $archivoData
            ),
            201
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'descripcion' => ['sometimes', 'string', 'max:255'],
            'monto'       => ['sometimes', 'numeric', 'min:0.01'],
            'notas'       => ['sometimes', 'nullable', 'string'],
        ]);
        
        // Manejar archivo si existe
        $archivoData = null;
        if ($request->hasFile('archivo')) {
            $archivo = $request->file('archivo');
            $path = $archivo->store('comprobantes/egresos_manuales', 'public');
            $archivoData = [
                'path' => $path,
                'mime' => $archivo->getMimeType(),
                'nombre' => $archivo->getClientOriginalName(),
            ];
        }

        return response()->json(
            $this->egresoManualService->actualizar($id, $data, $request->empresa_id_ctx, $archivoData)
        );
    }

    public function anular(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->egresoManualService->anular($id, $request->empresa_id_ctx)
        );
    }
}