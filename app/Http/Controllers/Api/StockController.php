<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\Autoriza;
use App\Models\Inventario;
use App\Models\Item;
use Illuminate\Http\Request;

class StockController extends Controller
{
    use Autoriza;

    private function resolveEmpresaId(Request $request): int
    {
        $u = $this->user($request);
        if ($u->rol === 'SUPER_ADMIN') {
            $id = (int)($request->query('empresa_id') ?? $request->input('empresa_id') ?? 0);
            if ($id <= 0) abort(422, 'empresa_id requerido para SUPER_ADMIN');
            return $id;
        }
        return $this->requireEmpresaId($u);
    }

    /**
     * POST /api/stock/verificar
     *
     * Verifica si hay stock suficiente para una lista de items.
     * Usado desde:
     *   - Frontend al ingresar cantidad en factura (tiempo real)
     *   - Al convertir cotización → factura
     *   - Antes de emitir (doble verificación)
     *
     * Request:
     * {
     *   "items": [
     *     { "item_id": 1, "cantidad": 5 },
     *     { "item_id": 2, "cantidad": 10 }
     *   ]
     * }
     *
     * Response (siempre 200, el front decide qué hacer con los resultados):
     * {
     *   "ok": false,          <- true si TODOS tienen stock suficiente
     *   "items": [
     *     {
     *       "item_id": 1,
     *       "nombre": "Tóner HP 85A",
     *       "controla_inventario": true,
     *       "cantidad_solicitada": 5,
     *       "cantidad_disponible": 12,
     *       "suficiente": true,
     *       "faltante": 0
     *     },
     *     {
     *       "item_id": 2,
     *       "nombre": "Resma Papel",
     *       "controla_inventario": true,
     *       "cantidad_solicitada": 10,
     *       "cantidad_disponible": 3,
     *       "suficiente": false,
     *       "faltante": 7
     *     }
     *   ]
     * }
     *
     * Notas:
     *  - Items que NO controlan inventario siempre retornan suficiente: true
     *  - Items no encontrados o inactivos retornan suficiente: false con mensaje
     */
    public function verificar(Request $request)
    {
        $empresaId = $this->resolveEmpresaId($request);

        $data = $request->validate([
            'items'             => ['required', 'array', 'min:1'],
            'items.*.item_id'   => ['required', 'integer', 'min:1'],
            'items.*.cantidad'  => ['required', 'numeric',  'min:0'],
        ]);

        // Una sola consulta para traer todos los items solicitados
        $itemIds = array_column($data['items'], 'item_id');

        $items = Item::query()
            ->whereIn('id', $itemIds)
            ->where('empresa_id', $empresaId)
            ->where('is_activo', 1)
            ->get()
            ->keyBy('id');

        // Una sola consulta para traer todos los inventarios relevantes
        $inventarios = Inventario::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('item_id', $itemIds)
            ->get()
            ->keyBy('item_id');

        $resultados  = [];
        $todoOk      = true;

        foreach ($data['items'] as $linea) {
            $itemId    = (int)$linea['item_id'];
            $solicitado = (float)$linea['cantidad'];
            $item      = $items->get($itemId);

            // Item no encontrado o inactivo
            if (!$item) {
                $resultados[] = [
                    'item_id'              => $itemId,
                    'nombre'               => null,
                    'controla_inventario'  => null,
                    'cantidad_solicitada'  => $solicitado,
                    'cantidad_disponible'  => 0,
                    'suficiente'           => false,
                    'faltante'             => $solicitado,
                    'error'                => 'Item no encontrado o inactivo',
                ];
                $todoOk = false;
                continue;
            }

            // Item que no controla inventario → siempre OK
            if (!$item->controla_inventario) {
                $resultados[] = [
                    'item_id'             => $itemId,
                    'nombre'              => $item->nombre,
                    'controla_inventario' => false,
                    'cantidad_solicitada' => $solicitado,
                    'cantidad_disponible' => null, // no aplica
                    'suficiente'          => true,
                    'faltante'            => 0,
                ];
                continue;
            }

            // Item con control de inventario
            $inv        = $inventarios->get($itemId);
            $disponible = $inv ? (float)$inv->cantidad_actual : 0;
            $suficiente = $solicitado <= $disponible;
            $faltante   = $suficiente ? 0 : round($solicitado - $disponible, 3);

            if (!$suficiente) $todoOk = false;

            $resultados[] = [
                'item_id'             => $itemId,
                'nombre'              => $item->nombre,
                'controla_inventario' => true,
                'cantidad_solicitada' => $solicitado,
                'cantidad_disponible' => $disponible,
                'suficiente'          => $suficiente,
                'faltante'            => $faltante,
            ];
        }

        return response()->json([
            'ok'    => $todoOk,
            'items' => $resultados,
        ]);
    }
}