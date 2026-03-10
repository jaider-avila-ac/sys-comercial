<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\Autoriza;
use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\CompraPago;
use App\Models\Egreso;
use App\Models\IngresoManual;
use App\Models\Inventario;
use App\Models\InventarioMovimiento;
use App\Models\Item;
use App\Models\Numeracion;
use App\Models\Pago;
use App\Models\Proveedor;
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

    /**
     * Balance de caja disponible.
     */
    private function balanceDisponible(int $empresaId): float
    {
        $totalPagos    = (float) Pago::where('empresa_id', $empresaId)->sum('total_pagado');
        $totalManuales = (float) IngresoManual::where('empresa_id', $empresaId)->sum('monto');
        $totalEgresos  = (float) Egreso::where('empresa_id', $empresaId)->sum('monto');

        return ($totalPagos + $totalManuales) - $totalEgresos;
    }

    // =========================================================================
    // GET /api/items
    // =========================================================================
    public function index(Request $request)
    {
        $empresaId = $this->resolveEmpresaId($request);
        $q         = trim((string) $request->query('search', ''));
        $tipo      = trim((string) $request->query('tipo', ''));
        $activos   = $request->query('activos', '1');

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
            ->when(
                (int) $request->query('proveedor_id', 0) > 0,
                fn($qq) => $qq->where('proveedor_id', (int) $request->query('proveedor_id'))
            )
            ->with('proveedor:id,nombre')
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json($items);
    }

    // =========================================================================
    // GET /api/items/{id}
    // =========================================================================
    public function show(Request $request, $id)
    {
        $empresaId = $this->resolveEmpresaId($request);

        $item = Item::query()
            ->with('proveedor:id,nombre,nit,ciudad')
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
            'inventario' => $inventario,
        ]);
    }

    // =========================================================================
    // POST /api/items
    //
    // Crea el item.
    // Si hay stock inicial y precio_compra > 0:
    // - crea compra automática CONFIRMADA
    // - si es contado: crea pago inicial por el total
    // - si es crédito con abono: crea pago inicial por el abono
    // - si es crédito sin abono: no crea pago ni egreso
    //
    // El archivo adjunto SOLO se guarda en compra_pagos, nunca en compras.
    // =========================================================================
    public function store(Request $request)
    {
        $u = $this->user($request);
        $this->requireAnyRole($u, ['SUPER_ADMIN', 'EMPRESA_ADMIN']);

        $empresaId = $this->resolveEmpresaId($request);

        $data = $request->validate([
            'tipo'                  => ['required', 'in:PRODUCTO,SERVICIO,INSUMO'],
            'nombre'                => ['required', 'string', 'max:180'],
            'descripcion'           => ['nullable', 'string'],
            'precio_compra'         => ['nullable', 'numeric', 'min:0'],
            'precio_venta_sugerido' => ['nullable', 'numeric', 'min:0'],
            'controla_inventario'   => ['nullable'],
            'unidad'                => ['nullable', 'string', 'max:30'],
            'is_activo'             => ['nullable'],
            'proveedor_id'          => ['nullable', 'integer'],
            'stock_minimo'          => ['nullable', 'integer', 'min:0'],
            'cantidad_inicial'      => ['nullable', 'integer', 'min:0'],
            'condicion_pago'        => ['nullable', 'in:CONTADO,CREDITO'],
            'fecha_vencimiento'     => ['nullable', 'date'],
            'abono_inicial'         => ['nullable', 'numeric', 'min:0'],
            'archivo'               => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if (!empty($data['proveedor_id'])) {
            $existe = Proveedor::where('empresa_id', $empresaId)
                ->where('id', $data['proveedor_id'])
                ->where('is_activo', 1)
                ->exists();

            if (!$existe) {
                abort(422, 'Proveedor no encontrado o inactivo');
            }
        }

        $controla     = filter_var($data['controla_inventario'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $cantIni      = $controla ? (int) ($data['cantidad_inicial'] ?? 0) : 0;
        $precioCompra = (float) ($data['precio_compra'] ?? 0);

        $costoInicial = ($controla && $cantIni > 0 && $precioCompra > 0)
            ? round($cantIni * $precioCompra, 2)
            : 0.0;

        $condicion    = $data['condicion_pago'] ?? 'CONTADO';
        $abonoInicial = round((float) ($data['abono_inicial'] ?? 0), 2);

        if ($abonoInicial > $costoInicial) {
            abort(422, 'El abono inicial no puede superar el total de la compra');
        }

        $montoPagoInicial = 0.0;

        if ($costoInicial > 0) {
            if ($condicion === 'CONTADO') {
                $montoPagoInicial = $costoInicial;
            } elseif ($condicion === 'CREDITO') {
                $montoPagoInicial = $abonoInicial;
            }
        }

        if ($montoPagoInicial > 0) {
            $balance = $this->balanceDisponible($empresaId);

            if ($montoPagoInicial > $balance) {
                abort(422, sprintf(
                    'Saldo insuficiente. Pago inicial requerido: $%s — Balance disponible: $%s.',
                    number_format($montoPagoInicial, 2),
                    number_format($balance, 2)
                ));
            }
        }

       $archivoPath = null;
$archivoMime = null;
$archivoNombre = null;

if ($request->hasFile('archivo')) {
    $file = $request->file('archivo');
    $archivoNombre = $file->getClientOriginalName();
    $archivoMime   = $file->getMimeType();
    $archivoPath   = $file->store("compras/pagos/{$empresaId}", 'public');
}

        return DB::transaction(function () use (
            $empresaId,
            $u,
            $data,
            $controla,
            $cantIni,
            $precioCompra,
            $costoInicial,
            $condicion,
            $abonoInicial,
            $montoPagoInicial,
            $archivoPath,
            $archivoMime,
            $archivoNombre
        ) {
            $item = Item::create([
                'empresa_id'            => $empresaId,
                'tipo'                  => $data['tipo'],
                'nombre'                => $data['nombre'],
                'descripcion'           => $data['descripcion'] ?? null,
                'precio_compra'         => $data['precio_compra'] ?? null,
                'precio_venta_sugerido' => $data['precio_venta_sugerido'] ?? null,
                'controla_inventario'   => $controla ? 1 : 0,
                'unidad'                => $data['unidad'] ?? null,
                'proveedor_id'          => $data['proveedor_id'] ?? null,
                'is_activo'             => filter_var($data['is_activo'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
            ]);

            if ($controla) {
                $stockMin = (int) ($data['stock_minimo'] ?? 0);

                Inventario::firstOrCreate(
                    [
                        'empresa_id' => $empresaId,
                        'item_id'    => $item->id,
                    ],
                    [
                        'cantidad_actual' => $cantIni,
                        'stock_minimo'    => $stockMin,
                        'updated_at'      => now(),
                    ]
                );

                if ($cantIni > 0) {
                    InventarioMovimiento::create([
                        'empresa_id'       => $empresaId,
                        'item_id'          => $item->id,
                        'usuario_id'       => $u->id,
                        'tipo'             => 'ENTRADA',
                        'motivo'           => 'Stock inicial',
                        'referencia_tipo'  => 'COMPRA',
                        'referencia_id'    => null,
                        'cantidad'         => $cantIni,
                        'saldo_resultante' => $cantIni,
                        'ocurrido_en'      => now(),
                    ]);
                }
            }

            $compra = null;

            if ($costoInicial > 0) {
                $num = Numeracion::query()
                    ->where('empresa_id', $empresaId)
                    ->where('tipo', 'COM')
                    ->lockForUpdate()
                    ->first();

                if (!$num) {
                    abort(422, 'No existe numeración tipo COM para esta empresa');
                }

                $num->consecutivo = (int) $num->consecutivo + 1;
                $num->updated_at  = now();
                $num->save();

                $consec       = str_pad((string) $num->consecutivo, max(1, (int) $num->relleno), '0', STR_PAD_LEFT);
                $numeroCompra = $num->prefijo . '-' . $consec;

                $saldoPendiente = $condicion === 'CONTADO'
                    ? 0
                    : round($costoInicial - $abonoInicial, 2);

                $compra = Compra::create([
                    'empresa_id'        => $empresaId,
                    'proveedor_id'      => $data['proveedor_id'] ?? null,
                    'usuario_id'        => $u->id,
                    'numero'            => $numeroCompra,
                    'fecha'             => now()->toDateString(),
                    'condicion_pago'    => $condicion,
                    'fecha_vencimiento' => $condicion === 'CREDITO'
                        ? ($data['fecha_vencimiento'] ?? null)
                        : null,
                    'subtotal'          => $costoInicial,
                    'impuestos'         => 0,
                    'total'             => $costoInicial,
                    'saldo_pendiente'   => $saldoPendiente,
                    'estado'            => 'CONFIRMADA',
                    'notas'             => 'Compra generada automáticamente desde creación de item',
                ]);

                CompraItem::create([
                    'compra_id'       => $compra->id,
                    'item_id'         => $item->id,
                    'cantidad'        => $cantIni,
                    'precio_unitario' => $precioCompra,
                    'subtotal'        => $costoInicial,
                ]);

               $pago = CompraPago::create([
    'compra_id'      => $compra->id,
    'empresa_id'     => $empresaId,
    'usuario_id'     => $u->id,
    'fecha'          => now()->toDateString(),
    'monto'          => $montoPagoInicial,
    'medio_pago'     => 'EFECTIVO',
    'notas'          => $condicion === 'CONTADO'
        ? 'Pago automático al crear item con stock inicial'
        : ($montoPagoInicial > 0
            ? 'Abono inicial automático al crear item con stock inicial'
            : 'Compra a crédito registrada sin abono inicial'),
    'archivo_path'   => $archivoPath,
    'archivo_mime'   => $archivoMime,
    'archivo_nombre' => $archivoNombre,
    'created_at'     => now(),
]);

$proveedor = !empty($data['proveedor_id'])
    ? Proveedor::find($data['proveedor_id'])
    : null;

$descripcion = $condicion === 'CONTADO'
    ? 'Pago compra ' . $compra->numero
    : ($montoPagoInicial > 0
        ? 'Abono inicial compra ' . $compra->numero
        : 'Compra a crédito ' . $compra->numero);

if ($proveedor) {
    $descripcion .= ' — ' . $proveedor->nombre;
}

Egreso::create([
    'empresa_id'     => $empresaId,
    'usuario_id'     => $u->id,
    'compra_id'      => $compra->id,
    'compra_pago_id' => $pago->id,
    'descripcion'    => $descripcion,
    'monto'          => $montoPagoInicial,
    'fecha'          => now()->toDateString(),
    'archivo_path'   => $archivoPath,
    'archivo_mime'   => $archivoMime,
    'archivo_nombre' => $archivoNombre,
]);
            }

            $item->load('proveedor:id,nombre');

            return response()->json([
                'item'           => $item,
                'compra_id'      => $compra?->id,
                'costo_inicial'  => $costoInicial > 0 ? $costoInicial : null,
                'condicion_pago' => $condicion,
                'abono_inicial'  => $abonoInicial > 0 ? $abonoInicial : null,
                'egreso_creado'  => $montoPagoInicial > 0,
            ], 201);
        });
    }

    // =========================================================================
    // PUT /api/items/{id}
    //
    // El ajuste de stock NO genera egreso. Es una corrección manual.
    // =========================================================================
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
            'precio_compra'         => ['nullable', 'numeric', 'min:0'],
            'precio_venta_sugerido' => ['nullable', 'numeric', 'min:0'],
            'controla_inventario'   => ['nullable', 'boolean'],
            'unidad'                => ['nullable', 'string', 'max:30'],
            'is_activo'             => ['nullable', 'boolean'],
            'proveedor_id'          => ['nullable', 'integer'],
            'stock_minimo'          => ['nullable', 'integer', 'min:0'],
            'cantidad_actual'       => ['nullable', 'integer', 'min:0'],
        ]);

        if (!empty($data['proveedor_id'])) {
            $existe = Proveedor::where('empresa_id', $empresaId)
                ->where('id', $data['proveedor_id'])
                ->where('is_activo', 1)
                ->exists();

            if (!$existe) {
                abort(422, 'Proveedor no encontrado o inactivo');
            }
        }

        return DB::transaction(function () use ($empresaId, $u, $item, $data) {
            $camposItem = array_filter(
                $data,
                fn($k) => in_array($k, [
                    'tipo',
                    'nombre',
                    'descripcion',
                    'precio_compra',
                    'precio_venta_sugerido',
                    'controla_inventario',
                    'unidad',
                    'is_activo',
                    'proveedor_id',
                ]),
                ARRAY_FILTER_USE_KEY
            );

            $item->fill($camposItem);
            $item->save();

            $debeControlar = (bool) $item->controla_inventario;

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
                        'cantidad_actual' => 0,
                        'stock_minimo'    => 0,
                        'updated_at'      => now(),
                    ]);
                }

                if (isset($data['stock_minimo'])) {
                    $inv->stock_minimo = (int) $data['stock_minimo'];
                }

                if (isset($data['cantidad_actual'])) {
                    $nuevaCantidad        = (int) $data['cantidad_actual'];
                    $inv->cantidad_actual = $nuevaCantidad;

                    InventarioMovimiento::create([
                        'empresa_id'       => $empresaId,
                        'item_id'          => $item->id,
                        'usuario_id'       => $u->id,
                        'tipo'             => 'AJUSTE',
                        'motivo'           => 'Ajuste desde edición de item',
                        'referencia_tipo'  => 'AJUSTE',
                        'referencia_id'    => null,
                        'cantidad'         => $nuevaCantidad,
                        'saldo_resultante' => $nuevaCantidad,
                        'ocurrido_en'      => now(),
                    ]);
                }

                $inv->updated_at = now();
                $inv->save();
            }

            $item->load('proveedor:id,nombre');

            $inventario = $debeControlar
                ? Inventario::where('empresa_id', $empresaId)->where('item_id', $item->id)->first()
                : null;

            return response()->json([
                'item'       => $item,
                'inventario' => $inventario,
            ]);
        });
    }

    // =========================================================================
    // DELETE /api/items/{id}
    // =========================================================================
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

        return response()->json(['ok' => true]);
    }
}