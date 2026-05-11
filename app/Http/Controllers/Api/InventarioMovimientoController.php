<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Factura;
use App\Models\InventarioMovimiento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventarioMovimientoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $empresaId = $request->empresa_id_ctx;
        
        $perPage = (int) $request->get('per_page', 20);
        
        $query = InventarioMovimiento::where('empresa_id', $empresaId)
            ->with(['item', 'usuario'])
            ->orderBy('ocurrido_en', 'desc');
        
        // Filtrar por item_id
        if ($request->has('item_id') && $request->item_id) {
            $query->where('item_id', $request->item_id);
        }
        
        // Filtrar por fecha desde
        if ($request->has('desde') && $request->desde) {
            $query->whereDate('ocurrido_en', '>=', $request->desde);
        }
        
        // Filtrar por fecha hasta
        if ($request->has('hasta') && $request->hasta) {
            $query->whereDate('ocurrido_en', '<=', $request->hasta);
        }
        
        $movimientos = $query->paginate($perPage);

        // Batch-load números de documento para no hacer N consultas
        $facturaIds = $movimientos->getCollection()
            ->where('referencia_tipo', 'FACTURA')
            ->pluck('referencia_id')
            ->filter()
            ->unique();

        $facturaNumeros = $facturaIds->isNotEmpty()
            ? Factura::whereIn('id', $facturaIds)->pluck('numero', 'id')
            : collect();

        // Transformar datos para el frontend
        $movimientos->getCollection()->transform(function ($mov) use ($facturaNumeros) {
            $refNumero = null;
            if ($mov->referencia_tipo === 'FACTURA') {
                $refNumero = $facturaNumeros[$mov->referencia_id] ?? null;
            }

            return [
                'id' => $mov->id,
                'tipo' => $mov->tipo,
                'cantidad' => $mov->unidades,
                'saldo_resultante' => $mov->unidades_resultantes,
                'motivo' => $mov->motivo,
                'ocurrido_en' => $mov->ocurrido_en,
                'referencia_tipo' => $mov->referencia_tipo,
                'referencia_id' => $mov->referencia_id,
                'referencia_numero' => $refNumero,
                'usuario_id' => $mov->usuario_id,
                'usuario_nombres' => $mov->usuario?->nombres,
                'usuario_apellidos' => $mov->usuario?->apellidos,
                'usuario_email' => $mov->usuario?->email,
                'item_id' => $mov->item_id,
                'item_nombre' => $mov->item?->nombre,
            ];
        });

        return response()->json($movimientos);
    }
}