<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\Autoriza;
use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\CompraPago;
use App\Models\Egreso;
use App\Models\Inventario;
use App\Models\InventarioMovimiento;
use App\Models\Item;
use App\Models\Numeracion;
use App\Models\Pago;
use App\Models\IngresoManual;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    use Autoriza;

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    private function resolveEmpresaId(Request $request): int
    {
        $u = $this->user($request);

        if ($u->rol === 'SUPER_ADMIN') {
            $id = (int) ($request->query('empresa_id') ?? $request->input('empresa_id') ?? 0);
            if ($id <= 0) {
                abort(422, 'empresa_id requerido para SUPER_ADMIN');
            }
            return $id;
        }

        return $this->requireEmpresaId($u);
    }

    /**
     * Usa la tabla numeraciones igual que FacturaController.
     * Requiere que exista una fila tipo='COM' para la empresa.
     */
    private function nextNumero(int $empresaId): string
    {
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

        $consec = str_pad((string) $num->consecutivo, max(1, (int) $num->relleno), '0', STR_PAD_LEFT);

        return $num->prefijo . '-' . $consec;
    }

    /**
     * Balance de caja disponible.
     */
    private function balanceDisponible(int $empresaId, float $excluirEgreso = 0): float
    {
        $totalPagos    = (float) Pago::where('empresa_id', $empresaId)->sum('total_pagado');
        $totalManuales = (float) IngresoManual::where('empresa_id', $empresaId)->sum('monto');
        $totalEgresos  = (float) Egreso::where('empresa_id', $empresaId)->sum('monto');

        return ($totalPagos + $totalManuales) - ($totalEgresos - $excluirEgreso);
    }

    // =========================================================================
    // GET /api/compras
    // ?proveedor_id=  ?estado=  ?desde=  ?hasta=  ?pendientes=1
    // =========================================================================
    public function index(Request $request)
{
    $empresaId   = $this->resolveEmpresaId($request);
    $proveedorId = (int) $request->query('proveedor_id', 0);
    $estado      = trim((string) $request->query('estado', ''));
    $desde       = $request->query('desde');
    $hasta       = $request->query('hasta');
    $pendientes  = $request->query('pendientes', '0');

    $rows = Compra::query()
        ->with([
            'proveedor:id,nombre,contacto,telefono',
            'items.item:id,nombre,unidad',
        ])
        ->where('empresa_id', $empresaId)
        ->when($proveedorId > 0, fn($q) => $q->where('proveedor_id', $proveedorId))
        ->when($estado !== '', fn($q) => $q->where('estado', $estado))
        ->when($desde, fn($q) => $q->whereDate('fecha', '>=', $desde))
        ->when($hasta, fn($q) => $q->whereDate('fecha', '<=', $hasta))
        ->when($pendientes === '1', fn($q) => $q->where('saldo_pendiente', '>', 0))
        ->orderByDesc('id')
        ->paginate(20);

    $rows->getCollection()->transform(function ($compra) {
        $linea = $compra->items->first();

        $compra->detalle_item = $linea ? [
            'nombre'         => $linea->item?->nombre,
            'unidad'         => $linea->item?->unidad,
            'cantidad'       => $linea->cantidad,
            'precio_unitario'=> $linea->precio_unitario,
            'subtotal'       => $linea->subtotal,
        ] : null;

        return $compra;
    });

    return response()->json($rows);
}

    // =========================================================================
    // GET /api/compras/cuentas-por-pagar
    // Compras CONFIRMADAS con saldo > 0 ordenadas por vencimiento
    // =========================================================================
   public function cuentasPorPagar(Request $request)
{
    $empresaId = $this->resolveEmpresaId($request);

    $rows = Compra::query()
        ->with([
            'proveedor:id,nombre,contacto,telefono',
            'items.item:id,nombre,unidad',
        ])
        ->where('empresa_id', $empresaId)
        ->where('estado', 'CONFIRMADA')
        ->where('saldo_pendiente', '>', 0)
        ->orderBy('fecha_vencimiento')
        ->get([
            'id',
            'numero',
            'proveedor_id',
            'fecha',
            'fecha_vencimiento',
            'total',
            'saldo_pendiente',
            'condicion_pago',
        ]);

    $rows->transform(function ($compra) {
        $linea = $compra->items->first();

        $compra->detalle_item = $linea ? [
            'nombre'         => $linea->item?->nombre,
            'unidad'         => $linea->item?->unidad,
            'cantidad'       => $linea->cantidad,
            'precio_unitario'=> $linea->precio_unitario,
            'subtotal'       => $linea->subtotal,
        ] : null;

        return $compra;
    });

    return response()->json($rows);
}

    // =========================================================================
    // GET /api/compras/{id}
    // =========================================================================
    public function show(Request $request, int $id)
    {
        $empresaId = $this->resolveEmpresaId($request);

        $compra = Compra::where('empresa_id', $empresaId)
            ->with([
                'proveedor:id,nombre,nit,contacto,telefono,email,tiempo_entrega_dias',
                'items.item:id,nombre,unidad',
                'pagos',
            ])
            ->findOrFail($id);

        $data = $compra->toArray();

        $data['pagos'] = $compra->pagos->map(function ($p) {
            return [
                'id'             => $p->id,
                'fecha'          => $p->fecha,
                'monto'          => $p->monto,
                'medio_pago'     => $p->medio_pago,
                'notas'          => $p->notas,
                'archivo_nombre' => $p->archivo_nombre,
                'archivo_mime'   => $p->archivo_mime,
                'archivo_url'    => $p->archivo_path ? asset('storage/' . $p->archivo_path) : null,
            ];
        })->values();

        return response()->json($data);
    }

    // =========================================================================
    // POST /api/compras
    // Crea compra en BORRADOR.
    // Ya NO guarda adjunto en compra.
    // =========================================================================
    public function store(Request $request)
    {
        $u = $this->user($request);
        $this->requireAnyRole($u, ['SUPER_ADMIN', 'EMPRESA_ADMIN', 'OPERATIVO']);

        $empresaId = $this->resolveEmpresaId($request);

        $data = $request->validate([
            'proveedor_id'            => ['nullable', 'integer'],
            'fecha'                   => ['required', 'date'],
            'condicion_pago'          => ['required', 'in:CONTADO,CREDITO'],
            'fecha_vencimiento'       => ['nullable', 'date'],
            'impuestos'               => ['nullable', 'numeric', 'min:0'],
            'notas'                   => ['nullable', 'string'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.item_id'         => ['required', 'integer'],
            'items.*.cantidad'        => ['required', 'numeric', 'min:0.001'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0'],
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

        return DB::transaction(function () use ($empresaId, $u, $data) {
            $numero   = $this->nextNumero($empresaId);
            $subtotal = 0;
            $lineas   = [];

            foreach ($data['items'] as $linea) {
                $item = Item::where('empresa_id', $empresaId)
                    ->where('id', $linea['item_id'])
                    ->where('is_activo', 1)
                    ->firstOrFail();

                $lineaSub  = round((float) $linea['cantidad'] * (float) $linea['precio_unitario'], 2);
                $subtotal += $lineaSub;

                $lineas[] = [
                    'item_id'         => $item->id,
                    'cantidad'        => (float) $linea['cantidad'],
                    'precio_unitario' => (float) $linea['precio_unitario'],
                    'subtotal'        => $lineaSub,
                ];
            }

            $impuestos = round((float) ($data['impuestos'] ?? 0), 2);
            $total     = round($subtotal + $impuestos, 2);

            $compra = Compra::create([
                'empresa_id'        => $empresaId,
                'proveedor_id'      => $data['proveedor_id'] ?? null,
                'usuario_id'        => $u->id,
                'numero'            => $numero,
                'fecha'             => $data['fecha'],
                'condicion_pago'    => $data['condicion_pago'],
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'subtotal'          => $subtotal,
                'impuestos'         => $impuestos,
                'total'             => $total,
                'saldo_pendiente'   => $total,
                'estado'            => 'BORRADOR',
                'notas'             => $data['notas'] ?? null,
            ]);

            foreach ($lineas as $linea) {
                CompraItem::create(array_merge(['compra_id' => $compra->id], $linea));
            }

            $result = $compra->load('items.item:id,nombre,unidad')->toArray();

            return response()->json($result, 201);
        });
    }

    // =========================================================================
    // POST /api/compras/{id}/confirmar
    //
    // Si es CONTADO:
    // - valida balance
    // - confirma
    // - crea pago automático sin archivo
    // - crea egreso por el total
    //
    // Si es CREDITO:
    // - confirma
    // - deja saldo_pendiente = total
    // - no crea egreso aquí
    // =========================================================================
    public function confirmar(Request $request, int $id)
    {
        $u = $this->user($request);
        $this->requireAnyRole($u, ['SUPER_ADMIN', 'EMPRESA_ADMIN']);

        $empresaId = $this->resolveEmpresaId($request);

        $compra = Compra::where('empresa_id', $empresaId)->findOrFail($id);

        if ($compra->estado !== 'BORRADOR') {
            abort(422, 'Solo se pueden confirmar compras en BORRADOR');
        }

        if ($compra->condicion_pago === 'CONTADO') {
            $balance = $this->balanceDisponible($empresaId);

            if ((float) $compra->total > $balance) {
                abort(422, sprintf(
                    'Saldo de caja insuficiente. Total compra: $%s — Balance disponible: $%s',
                    number_format($compra->total, 2),
                    number_format($balance, 2)
                ));
            }
        }

        return DB::transaction(function () use ($empresaId, $u, $compra) {
            $compra->update([
                'estado' => 'CONFIRMADA',
                'saldo_pendiente' => $compra->condicion_pago === 'CONTADO'
                    ? 0
                    : $compra->total,
            ]);

            $lineas = CompraItem::where('compra_id', $compra->id)->get();

            foreach ($lineas as $linea) {
                $item = Item::find($linea->item_id);
                if (!$item) {
                    continue;
                }

                $item->update([
                    'precio_compra' => $linea->precio_unitario,
                ]);

                if (!$item->controla_inventario) {
                    continue;
                }

                $inv = Inventario::where('empresa_id', $empresaId)
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

                $nuevo = (float) $inv->cantidad_actual + (float) $linea->cantidad;

                $inv->update([
                    'cantidad_actual' => $nuevo,
                    'updated_at'      => now(),
                ]);

                InventarioMovimiento::create([
                    'empresa_id'       => $empresaId,
                    'item_id'          => $item->id,
                    'usuario_id'       => $u->id,
                    'tipo'             => 'ENTRADA',
                    'motivo'           => 'Compra ' . $compra->numero,
                    'referencia_tipo'  => 'COMPRA',
                    'referencia_id'    => $compra->id,
                    'cantidad'         => (float) $linea->cantidad,
                    'saldo_resultante' => $nuevo,
                    'ocurrido_en'      => now(),
                ]);
            }

            if ($compra->condicion_pago === 'CONTADO') {
                $pago = CompraPago::create([
                    'compra_id'      => $compra->id,
                    'empresa_id'     => $empresaId,
                    'usuario_id'     => $u->id,
                    'fecha'          => $compra->fecha,
                    'monto'          => $compra->total,
                    'medio_pago'     => 'EFECTIVO',
                    'notas'          => 'Pago automático al confirmar compra de contado',
                    'archivo_path'   => null,
                    'archivo_mime'   => null,
                    'archivo_nombre' => null,
                    'created_at'     => now(),
                ]);

                $proveedor = $compra->proveedor_id
                    ? Proveedor::find($compra->proveedor_id)
                    : null;

                $descripcionEgreso = 'Pago compra ' . $compra->numero;
                if ($proveedor) {
                    $descripcionEgreso .= ' — ' . $proveedor->nombre;
                }

                Egreso::create([
                    'empresa_id'     => $empresaId,
                    'usuario_id'     => $u->id,
                    'compra_id'      => $compra->id,
                    'compra_pago_id' => $pago->id,
                    'descripcion'    => $descripcionEgreso,
                    'monto'          => $compra->total,
                    'fecha'          => $compra->fecha,
                    'archivo_path'   => null,
                    'archivo_mime'   => null,
                    'archivo_nombre' => null,
                ]);
            }

            return response()->json([
                'ok'        => true,
                'compra_id' => $compra->id,
            ]);
        });
    }

    // =========================================================================
    // POST /api/compras/{id}/anular
    // Revierte inventario si estaba CONFIRMADA y elimina egresos/pagos automáticos
    // =========================================================================
    public function anular(Request $request, int $id)
    {
        $u = $this->user($request);
        $this->requireAnyRole($u, ['SUPER_ADMIN', 'EMPRESA_ADMIN']);

        $empresaId = $this->resolveEmpresaId($request);

        $compra = Compra::where('empresa_id', $empresaId)->findOrFail($id);

        if ($compra->estado === 'ANULADA') {
            abort(422, 'La compra ya está anulada');
        }

        return DB::transaction(function () use ($empresaId, $u, $compra) {
            if ($compra->estado === 'CONFIRMADA') {
                $lineas = CompraItem::where('compra_id', $compra->id)->get();

                foreach ($lineas as $linea) {
                    $item = Item::find($linea->item_id);
                    if (!$item || !$item->controla_inventario) {
                        continue;
                    }

                    $inv = Inventario::where('empresa_id', $empresaId)
                        ->where('item_id', $item->id)
                        ->lockForUpdate()
                        ->first();

                    if (!$inv) {
                        continue;
                    }

                    $nuevo = max(0, (float) $inv->cantidad_actual - (float) $linea->cantidad);

                    $inv->update([
                        'cantidad_actual' => $nuevo,
                        'updated_at'      => now(),
                    ]);

                    InventarioMovimiento::create([
                        'empresa_id'       => $empresaId,
                        'item_id'          => $item->id,
                        'usuario_id'       => $u->id,
                        'tipo'             => 'SALIDA',
                        'motivo'           => 'Anulación compra ' . $compra->numero,
                        'referencia_tipo'  => 'COMPRA',
                        'referencia_id'    => $compra->id,
                        'cantidad'         => (float) $linea->cantidad,
                        'saldo_resultante' => $nuevo,
                        'ocurrido_en'      => now(),
                    ]);
                }

                Egreso::where('empresa_id', $empresaId)
                    ->where('compra_id', $compra->id)
                    ->delete();

                CompraPago::where('empresa_id', $empresaId)
                    ->where('compra_id', $compra->id)
                    ->delete();
            }

            $compra->update([
                'estado'          => 'ANULADA',
                'saldo_pendiente' => 0,
            ]);

            return response()->json(['ok' => true]);
        });
    }

    // =========================================================================
    // POST /api/compras/{id}/pagar
    // Abono a compra a crédito — descuenta saldo y crea egreso con adjunto
    // =========================================================================
    public function pagar(Request $request, int $id)
    {
        $u = $this->user($request);
        $this->requireAnyRole($u, ['SUPER_ADMIN', 'EMPRESA_ADMIN']);

        $empresaId = $this->resolveEmpresaId($request);

        $compra = Compra::where('empresa_id', $empresaId)->findOrFail($id);

        if ($compra->estado !== 'CONFIRMADA') {
            abort(422, 'Solo se pueden registrar pagos en compras CONFIRMADAS');
        }

        if ((float) $compra->saldo_pendiente <= 0) {
            abort(422, 'Esta compra ya está totalmente pagada');
        }

        $data = $request->validate([
            'fecha'      => ['required', 'date'],
            'monto'      => ['required', 'numeric', 'min:0.01'],
            'medio_pago' => ['nullable', 'string', 'max:50'],
            'notas'      => ['nullable', 'string'],
            'archivo'    => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ((float) $data['monto'] > (float) $compra->saldo_pendiente) {
            abort(422, 'El monto supera el saldo pendiente de $' . number_format($compra->saldo_pendiente, 2));
        }

        $balance = $this->balanceDisponible($empresaId);
        if ((float) $data['monto'] > $balance) {
            abort(422, 'Saldo insuficiente. Balance disponible: $' . number_format($balance, 2));
        }

        $archivoPath   = null;
        $archivoMime   = null;
        $archivoNombre = null;

        if ($request->hasFile('archivo')) {
            $file = $request->file('archivo');

            $archivoNombre = $file->getClientOriginalName();
            $archivoMime   = $file->getMimeType();
            $archivoPath   = $file->store("compras/pagos/{$empresaId}", 'public');
        }

        return DB::transaction(function () use ($empresaId, $u, $compra, $data, $archivoPath, $archivoMime, $archivoNombre) {
            $pago = CompraPago::create([
                'compra_id'      => $compra->id,
                'empresa_id'     => $empresaId,
                'usuario_id'     => $u->id,
                'fecha'          => $data['fecha'],
                'monto'          => $data['monto'],
                'medio_pago'     => $data['medio_pago'] ?? null,
                'notas'          => $data['notas'] ?? null,
                'archivo_path'   => $archivoPath,
                'archivo_mime'   => $archivoMime,
                'archivo_nombre' => $archivoNombre,
                'created_at'     => now(),
            ]);

            $nuevoSaldo = round((float) $compra->saldo_pendiente - (float) $data['monto'], 2);

            $compra->update([
                'saldo_pendiente' => $nuevoSaldo,
            ]);

            $proveedor = $compra->proveedor_id
                ? Proveedor::find($compra->proveedor_id)
                : null;

            $descripcion = 'Pago compra ' . $compra->numero;
            if ($proveedor) {
                $descripcion .= ' — ' . $proveedor->nombre;
            }

            Egreso::create([
                'empresa_id'     => $empresaId,
                'usuario_id'     => $u->id,
                'compra_id'      => $compra->id,
                'compra_pago_id' => $pago->id,
                'descripcion'    => $descripcion,
                'monto'          => $data['monto'],
                'fecha'          => $data['fecha'],
                'archivo_path'   => $archivoPath,
                'archivo_mime'   => $archivoMime,
                'archivo_nombre' => $archivoNombre,
            ]);

            return response()->json([
                'ok'              => true,
                'saldo_pendiente' => $nuevoSaldo,
            ]);
        });
    }
}