<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\Autoriza;
use App\Models\Item;
use App\Models\Inventario;
use App\Models\InventarioMovimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller
{
    use Autoriza;

    private function resolveEmpresaId(Request $request): int
    {
        $u = $this->user($request);

        if ($u->rol === 'SUPER_ADMIN') {
            $empresaId = (int)($request->query('empresa_id') ?? $request->input('empresa_id') ?? 0);
            if ($empresaId <= 0) {
                abort(422, 'empresa_id es requerido para SUPER_ADMIN');
            }
            return $empresaId;
        }

        return $this->requireEmpresaId($u);
    }

    /** GET /api/items */
    public function index(Request $request)
    {
        $empresaId = $this->resolveEmpresaId($request);

        $q      = trim((string)$request->query('search', ''));
        $tipo   = trim((string)$request->query('tipo', ''));
        $activos = $request->query('activos', '1');

        $items = Item::query()
            ->where('empresa_id', $empresaId)
            ->when($activos !== '0', fn($qq) => $qq->where('is_activo', 1))
            ->when($tipo !== '', fn($qq) => $qq->where('tipo', $tipo))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('nombre', 'like', "%{$q}%")
                      ->orWhere('descripcion', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json($items);
    }

    /**
     * GET /api/items/{id}
     * FIX: también retorna datos de inventario (stock_minimo, cantidad_actual)
     * para que el form de edición pueda cargarlos.
     */
    public function show(Request $request, $id)
    {
        $empresaId = $this->resolveEmpresaId($request);

        $item = Item::query()
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->firstOrFail();

        $inventario = null;
        if ($item->controla_inventario) {
            $inventario = Inventario::query()
                ->where('empresa_id', $empresaId)
                ->where('item_id', $id)
                ->first();
        }

        return response()->json([
            'item'       => $item,
            'inventario' => $inventario, // null si no existe aún
        ]);
    }

    /** POST /api/items */
    public function store(Request $request)
    {
        $u = $this->user($request);
        $this->requireAnyRole($u, ['SUPER_ADMIN', 'EMPRESA_ADMIN']);

        $empresaId = $this->resolveEmpresaId($request);

        $data = $request->validate([
            'tipo'                   => ['required', 'in:PRODUCTO,SERVICIO,INSUMO'],
            'nombre'                 => ['required', 'string', 'max:180'],
            'descripcion'            => ['nullable', 'string'],
            'precio_compra'          => ['nullable', 'numeric'],
            'precio_venta_sugerido'  => ['nullable', 'numeric'],
            'controla_inventario'    => ['nullable', 'boolean'],
            'unidad'                 => ['nullable', 'string', 'max:30'],
            'is_activo'              => ['nullable', 'boolean'],
            // Solo enteros — no se aceptan decimales
            'stock_minimo'           => ['nullable', 'integer', 'min:0'],
            'cantidad_inicial'       => ['nullable', 'integer', 'min:0'],
        ]);

        $controla = (bool)($data['controla_inventario'] ?? false);

        return DB::transaction(function () use ($empresaId, $u, $data, $controla) {
            $item = Item::create([
                'empresa_id'             => $empresaId,
                'tipo'                   => $data['tipo'],
                'nombre'                 => $data['nombre'],
                'descripcion'            => $data['descripcion'] ?? null,
                'precio_compra'          => $data['precio_compra'] ?? null,
                'precio_venta_sugerido'  => $data['precio_venta_sugerido'] ?? null,
                'controla_inventario'    => $controla ? 1 : 0,
                'unidad'                 => $data['unidad'] ?? null,
                'is_activo'              => array_key_exists('is_activo', $data) ? (int)(bool)$data['is_activo'] : 1,
            ]);

            if ($controla) {
                $stockMin = isset($data['stock_minimo'])     ? (int)$data['stock_minimo']     : 0;
                $cantIni  = isset($data['cantidad_inicial']) ? (int)$data['cantidad_inicial'] : 0;

                $inv = Inventario::query()->firstOrCreate(
                    ['empresa_id' => $empresaId, 'item_id' => $item->id],
                    [
                        'cantidad_actual' => $cantIni,
                        'stock_minimo'    => $stockMin,
                        'updated_at'      => now(),
                    ]
                );

                // Registrar movimiento inicial si hay cantidad > 0
                if ($cantIni > 0) {
                    InventarioMovimiento::create([
                        'empresa_id'       => $empresaId,
                        'item_id'          => $item->id,
                        'usuario_id'       => $u->id,
                        'tipo'             => 'ENTRADA',
                        'motivo'           => 'Stock inicial',
                        'referencia_tipo'  => 'AJUSTE',
                        'referencia_id'    => null,
                        'cantidad'         => $cantIni,
                        'saldo_resultante' => $cantIni,
                        'ocurrido_en'      => now(),
                    ]);
                }
            }

            return response()->json(['item' => $item], 201);
        });
    }

    /**
     * PUT /api/items/{id}
     * FIX:
     *  - Actualiza stock_minimo aunque el item ya controlara inventario antes.
     *  - Registra InventarioMovimiento tipo AJUSTE cuando cambia cantidad_actual.
     */
    public function update(Request $request, $id)
    {
        $u = $this->user($request);
        $this->requireAnyRole($u, ['SUPER_ADMIN', 'EMPRESA_ADMIN']);

        $empresaId = $this->resolveEmpresaId($request);

        $item = Item::query()
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->firstOrFail();

        $data = $request->validate([
            'tipo'                  => ['sometimes', 'required', 'in:PRODUCTO,SERVICIO,INSUMO'],
            'nombre'                => ['sometimes', 'required', 'string', 'max:180'],
            'descripcion'           => ['nullable', 'string'],
            'precio_compra'         => ['nullable', 'numeric'],
            'precio_venta_sugerido' => ['nullable', 'numeric'],
            'controla_inventario'   => ['nullable', 'boolean'],
            'unidad'                => ['nullable', 'string', 'max:30'],
            'is_activo'             => ['nullable', 'boolean'],
            'stock_minimo'          => ['nullable', 'integer', 'min:0'],
            // FIX: permitir ajustar cantidad desde el form de edición
            'cantidad_actual'       => ['nullable', 'integer', 'min:0'],
        ]);

        return DB::transaction(function () use ($empresaId, $u, $item, $data) {

            // Guardar solo campos que pertenecen a items
            $itemFields = array_filter(
                $data,
                fn($k) => in_array($k, [
                    'tipo', 'nombre', 'descripcion', 'precio_compra',
                    'precio_venta_sugerido', 'controla_inventario', 'unidad', 'is_activo',
                ]),
                ARRAY_FILTER_USE_KEY
            );
            $item->fill($itemFields);
            $item->save();

            $debeControlar = (bool)$item->controla_inventario;

            // FIX: actualizar inventario si controla (ya sea que acabe de activarse o ya lo hacía)
            if ($debeControlar && (isset($data['stock_minimo']) || isset($data['cantidad_actual']))) {

                $inv = Inventario::query()
                    ->where('empresa_id', $empresaId)
                    ->where('item_id', $item->id)
                    ->lockForUpdate()
                    ->first();

                if (!$inv) {
                    $inv = Inventario::create([
                        'empresa_id'      => $empresaId,
                        'item_id'         => $item->id,
                        'cantidad_actual' => 0.0,
                        'stock_minimo'    => 0.0,
                        'updated_at'      => now(),
                    ]);
                }

                $cantidadAnterior = (float)$inv->cantidad_actual;

                if (isset($data['stock_minimo'])) {
                    $inv->stock_minimo = (int)$data['stock_minimo'];
                }

                // FIX: si mandan nueva cantidad_actual, hacer ajuste y registrar movimiento
                if (isset($data['cantidad_actual'])) {
                    $nuevaCantidad = (int)$data['cantidad_actual'];
                    $inv->cantidad_actual = $nuevaCantidad;

                    InventarioMovimiento::create([
                        'empresa_id'       => $empresaId,
                        'item_id'          => $item->id,
                        'usuario_id'       => $u->id,
                        'tipo'             => 'AJUSTE',
                        'motivo'           => 'Ajuste desde edición de item',
                        'referencia_tipo'  => 'AJUSTE',
                        'referencia_id'    => null,
                        'cantidad'         => $nuevaCantidad,      // nuevo stock total
                        'saldo_resultante' => $nuevaCantidad,
                        'ocurrido_en'      => now(),
                    ]);
                }

                $inv->updated_at = now();
                $inv->save();
            }

            // Recargar inventario para incluirlo en la respuesta
            $inventario = $debeControlar
                ? Inventario::query()
                    ->where('empresa_id', $empresaId)
                    ->where('item_id', $item->id)
                    ->first()
                : null;

            return response()->json([
                'item'       => $item,
                'inventario' => $inventario,
            ]);
        });
    }

    /** DELETE /api/items/{id} */
    public function destroy(Request $request, $id)
    {
        $u = $this->user($request);
        $this->requireAnyRole($u, ['SUPER_ADMIN', 'EMPRESA_ADMIN']);

        $empresaId = $this->resolveEmpresaId($request);

        $item = Item::query()
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->firstOrFail();

        $item->is_activo = 0;
        $item->save();

        // FIX: typo corregido (response()- >json → response()->json)
        return response()->json(['ok' => true]);
    }
}