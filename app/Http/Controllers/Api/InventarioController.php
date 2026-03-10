<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\Autoriza;
use App\Models\Inventario;
use App\Models\InventarioMovimiento;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventarioController extends Controller
{
    use Autoriza;

    private function resolveEmpresaId(Request $request): int
    {
        $u = $this->user($request);

        if ($u->rol === 'SUPER_ADMIN') {
            $empresaId = (int)($request->query('empresa_id') ?? $request->input('empresa_id') ?? 0);
            if ($empresaId <= 0) abort(422, 'empresa_id es requerido para SUPER_ADMIN');
            return $empresaId;
        }

        return $this->requireEmpresaId($u);
    }

    public function index(Request $request)
    {
        $empresaId = $this->resolveEmpresaId($request);

        $q = trim((string)$request->query('search', ''));
        $soloControla = $request->query('solo_controla', '1');
        $tipo = trim((string)$request->query('tipo', ''));

        $subVendidos = InventarioMovimiento::query()
            ->select([
                'item_id',
                DB::raw('COALESCE(SUM(cantidad), 0) as vendido_total'),
            ])
            ->where('empresa_id', $empresaId)
            ->where('tipo', 'SALIDA')
            ->where('referencia_tipo', 'FACTURA')
            ->groupBy('item_id');

        $rows = Item::query()
            ->select([
                'items.id',
                'items.tipo',
                'items.nombre',
                'items.controla_inventario',
                'items.is_activo',
                DB::raw('COALESCE(inventarios.cantidad_actual, 0) as cantidad_actual'),
                DB::raw('COALESCE(inventarios.stock_minimo, 0) as stock_minimo'),
                DB::raw('COALESCE(vendidos.vendido_total, 0) as vendido_total'),
            ])
            ->leftJoin('inventarios', function ($join) use ($empresaId) {
                $join->on('inventarios.item_id', '=', 'items.id')
                    ->where('inventarios.empresa_id', '=', $empresaId);
            })
            ->leftJoinSub($subVendidos, 'vendidos', function ($join) {
                $join->on('vendidos.item_id', '=', 'items.id');
            })
            ->where('items.empresa_id', $empresaId)
            ->where('items.is_activo', 1)
            ->when($soloControla !== '0', fn($qq) => $qq->where('items.controla_inventario', 1))
            ->when($tipo !== '', fn($qq) => $qq->where('items.tipo', $tipo))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('items.nombre', 'like', "%{$q}%")
                      ->orWhere('items.descripcion', 'like', "%{$q}%");
                });
            })
            ->orderBy('items.nombre')
            ->paginate(15);

        return response()->json($rows);
    }

    public function ajustar(Request $request)
    {
        $u = $this->user($request);
        $this->requireAnyRole($u, ['SUPER_ADMIN', 'EMPRESA_ADMIN', 'OPERATIVO']);

        $empresaId = $this->resolveEmpresaId($request);

        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'tipo' => ['required', 'in:ENTRADA,SALIDA,AJUSTE'],
            'cantidad' => ['required', 'numeric', 'min:0.001'],
            'motivo' => ['nullable', 'string', 'max:120'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'referencia_tipo' => ['nullable', 'in:FACTURA,AJUSTE,COMPRA,OTRO'],
            'referencia_id' => ['nullable', 'integer'],
        ]);

        $item = Item::query()
            ->where('empresa_id', $empresaId)
            ->where('id', $data['item_id'])
            ->where('is_activo', 1)
            ->firstOrFail();

        if (!$item->controla_inventario) {
            abort(422, 'Este item no controla inventario');
        }

        $cantidad = (float)$data['cantidad'];

        return DB::transaction(function () use ($empresaId, $u, $item, $data, $cantidad) {
            $inv = Inventario::query()
                ->where('empresa_id', $empresaId)
                ->where('item_id', $item->id)
                ->lockForUpdate()
                ->first();

            if (!$inv) {
                $inv = Inventario::create([
                    'empresa_id' => $empresaId,
                    'item_id' => $item->id,
                    'cantidad_actual' => 0.0,
                    'stock_minimo' => 0.0,
                    'updated_at' => now(),
                ]);
            }

            $actual = (float)$inv->cantidad_actual;
            $nuevo = $actual;

            if ($data['tipo'] === 'ENTRADA') {
                $nuevo = $actual + $cantidad;
            } elseif ($data['tipo'] === 'SALIDA') {
                $nuevo = $actual - $cantidad;
                if ($nuevo < 0) {
                    abort(422, 'Stock insuficiente para salida');
                }
            } else {
                $nuevo = $cantidad;
            }

            $inv->cantidad_actual = $nuevo;

            if (isset($data['stock_minimo'])) {
                $inv->stock_minimo = (float)$data['stock_minimo'];
            }

            $inv->updated_at = now();
            $inv->save();

            InventarioMovimiento::create([
                'empresa_id' => $empresaId,
                'item_id' => $item->id,
                'usuario_id' => $u->id,
                'tipo' => $data['tipo'],
                'motivo' => $data['motivo'] ?? null,
                'referencia_tipo' => $data['referencia_tipo'] ?? 'AJUSTE',
                'referencia_id' => $data['referencia_id'] ?? null,
                'cantidad' => $cantidad,
                'saldo_resultante' => $nuevo,
                'ocurrido_en' => now(),
            ]);

            return response()->json([
                'ok' => true,
                'item_id' => $item->id,
                'cantidad_anterior' => $actual,
                'cantidad_actual' => $nuevo,
            ]);
        });
    }

    public function movimientos(Request $request)
{
    $empresaId = $this->resolveEmpresaId($request);

    $itemId = (int)$request->query('item_id', 0);
    $desde = $request->query('desde');
    $hasta = $request->query('hasta');

    $q = InventarioMovimiento::query()
        ->leftJoin('usuarios as u', 'u.id', '=', 'inventario_movimientos.usuario_id')
        ->leftJoin('items as i', 'i.id', '=', 'inventario_movimientos.item_id')
        ->leftJoin('facturas as f', function ($join) {
            $join->on('f.id', '=', 'inventario_movimientos.referencia_id')
                ->where('inventario_movimientos.referencia_tipo', '=', 'FACTURA');
        })
        ->leftJoin('compras as c', function ($join) {
            $join->on('c.id', '=', 'inventario_movimientos.referencia_id')
                ->where('inventario_movimientos.referencia_tipo', '=', 'COMPRA');
        })
        ->select([
            'inventario_movimientos.*',
            'u.nombres as usuario_nombres',
            'u.apellidos as usuario_apellidos',
            'u.email as usuario_email',
            'i.nombre as item_nombre',
            'f.numero as factura_numero',
            'c.numero as compra_numero',
        ])
        ->where('inventario_movimientos.empresa_id', $empresaId)
        ->when($itemId > 0, fn($qq) => $qq->where('inventario_movimientos.item_id', $itemId))
        ->when($desde, fn($qq) => $qq->whereDate('inventario_movimientos.ocurrido_en', '>=', $desde))
        ->when($hasta, fn($qq) => $qq->whereDate('inventario_movimientos.ocurrido_en', '<=', $hasta))
        ->orderByDesc('inventario_movimientos.ocurrido_en')
        ->orderByDesc('inventario_movimientos.id')
        ->paginate(20);

    return response()->json($q);
}
}