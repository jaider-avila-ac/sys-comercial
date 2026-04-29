<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\IngresoMostradorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngresoMostradorController extends Controller
{
    public function __construct(
        private readonly IngresoMostradorService $ingresoMostradorService,
    ) {}

    // GET /api/ingresos/mostrador
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->get('search'),
            'desde'  => $request->get('desde'),
            'hasta'  => $request->get('hasta'),
        ];
        
        $filters = array_filter($filters, fn($v) => $v !== null && $v !== '');
        $perPage = (int) $request->get('per_page', 20);
        
        return response()->json(
            $this->ingresoMostradorService->listar($request->empresa_id_ctx, $filters, $perPage)
        );
    }

    // GET /api/ingresos/mostrador/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->ingresoMostradorService->obtener($id, $request->empresa_id_ctx)
        );
    }

    // POST /api/ingresos/mostrador
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fecha'          => ['required', 'date'],
            'descripcion'    => ['nullable', 'string', 'max:255'],
            'item_id'        => ['nullable', 'integer'],
            'cantidad'       => ['required', 'integer', 'min:1'],
            'precio_unitario'=> ['required', 'numeric', 'min:0'],
            'iva_pct'        => ['nullable', 'numeric', 'min:0'],
            'forma_pago'     => ['required', 'in:EFECTIVO,TRANSFERENCIA,TARJETA,BILLETERA,OTRO'],
            'referencia'     => ['nullable', 'string', 'max:80'],
            'notas'          => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(
            $this->ingresoMostradorService->registrar(
                $data,
                $request->empresa_id_ctx,
                $request->user()->id,
            ),
            201
        );
    }

    // POST /api/ingresos/mostrador/{id}/anular
    public function anular(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->ingresoMostradorService->anular(
                $id,
                $request->empresa_id_ctx,
                $request->user()->id,
            )
        );
    }
}