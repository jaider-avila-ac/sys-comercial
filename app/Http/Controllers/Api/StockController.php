<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventario;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    /**
     * Verificar stock de uno o múltiples items
     */
    public function verificar(Request $request): JsonResponse
    {
        $request->validate([
            'items' => ['required', 'array'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
        ]);

        $empresaId = $request->empresa_id_ctx;
        $items = $request->input('items');
        $resultados = [];

        foreach ($items as $itemData) {
            $itemId = $itemData['item_id'];
            $cantidadSolicitada = $itemData['cantidad'];

            $item = Item::where('empresa_id', $empresaId)->find($itemId);
            
            if (!$item) {
                $resultados[] = [
                    'item_id' => $itemId,
                    'controla_inventario' => false,
                    'suficiente' => true,
                    'cantidad_disponible' => 0,
                ];
                continue;
            }

            if (!$item->controla_inventario) {
                $resultados[] = [
                    'item_id' => $itemId,
                    'controla_inventario' => false,
                    'suficiente' => true,
                    'cantidad_disponible' => null,
                ];
                continue;
            }

            $inventario = Inventario::where('empresa_id', $empresaId)
                ->where('item_id', $itemId)
                ->first();

            $cantidadDisponible = $inventario?->unidades_actuales ?? 0;
            $suficiente = $cantidadDisponible >= $cantidadSolicitada;

            $resultados[] = [
                'item_id' => $itemId,
                'controla_inventario' => true,
                'suficiente' => $suficiente,
                'cantidad_disponible' => $cantidadDisponible,
            ];
        }

        return response()->json([
            'items' => $resultados,
        ]);
    }
}