<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\FacturaLinea;
use App\Models\Inventario;
use App\Models\InventarioMovimiento;
use App\Models\Item;
use App\Models\Numeracion;
use App\Models\Pago;
use App\Models\PagoAplicacion;
use App\Services\DocumentoCalculos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Services\BrevoService;
use Illuminate\Support\Facades\Log;

class FacturaController extends Controller
{
    private DocumentoCalculos $calculos;

    public function __construct()
    {
        $this->calculos = new DocumentoCalculos();
    }

    // =========================================================================
    // LISTAR  GET /api/facturas
    // =========================================================================
    public function index(Request $request)
    {
        $u      = $request->user();
        $q      = trim((string)$request->query('search', ''));
        $estado = trim((string)$request->query('estado', ''));

        $query = Factura::query()->with('cliente:id,nombre_razon_social');

        if ($u->rol !== 'SUPER_ADMIN') {
            if (!$u->empresa_id) return response()->json(['message' => 'Sin empresa'], 403);
            $query->where('empresa_id', $u->empresa_id);
        } else {
            $eid = $request->query('empresa_id');
            if ($eid) $query->where('empresa_id', (int)$eid);
        }

        if ($estado) $query->where('estado', $estado);

        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('numero', 'like', "%{$q}%")
                    ->orWhere('notas', 'like', "%{$q}%");
            });
        }

        return response()->json($query->orderByDesc('id')->paginate(15));
    }

    // =========================================================================
    // VER  GET /api/facturas/{id}
    // =========================================================================
    public function show(Request $request, $id)
    {
        $u = $request->user();

        $fac = Factura::with([
            'cliente:id,nombre_razon_social',
            'empresa',
            'usuario',
            'cotizacion:id,numero',
            'pagos.pago',
            'lineas.item:id,nombre'   // ← ESTA ES LA CLAVE
        ])->findOrFail($id);

        if ($u->rol !== 'SUPER_ADMIN' && (int)$fac->empresa_id !== (int)$u->empresa_id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        return response()->json([
            'factura' => $fac
        ]);
    }

    // =========================================================================
    // CREAR  POST /api/facturas
    // =========================================================================
    public function store(Request $request)
    {
        $u = $request->user();
        if (!in_array($u->rol, ['SUPER_ADMIN', 'EMPRESA_ADMIN', 'OPERATIVO'], true))
            return response()->json(['message' => 'No autorizado'], 403);

        $empresaId = $this->resolveEmpresaId($request);

        $payload = $request->validate([
            'cliente_id'                  => ['required', 'integer', 'min:1'],
            'cotizacion_id'               => ['nullable', 'integer'],
            'fecha'                       => ['required', 'date'],
            'notas'                       => ['nullable', 'string'],
            'lineas'                      => ['required', 'array', 'min:1'],
            'lineas.*.item_id'            => ['nullable', 'integer', 'min:1'],
            'lineas.*.descripcion_manual' => ['nullable', 'string', 'max:255'],
            'lineas.*.cantidad'           => ['required', 'integer', 'min:1'],
            'lineas.*.valor_unitario'     => ['required', 'numeric', 'min:0'],
            'lineas.*.descuento'          => ['nullable', 'numeric', 'min:0'],
            'lineas.*.iva_pct'            => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        foreach ($payload['lineas'] as $i => $l) {
            if (empty($l['item_id']) && trim((string)($l['descripcion_manual'] ?? '')) === '') {
                return response()->json(['message' => "Línea #" . ($i + 1) . ": producto o descripción requerido."], 422);
            }
        }

        return DB::transaction(function () use ($u, $empresaId, $payload) {
            $lineasCalc = $this->calcularLineas($payload['lineas']);
            $tot        = $this->calculos->calcularTotales($lineasCalc);
            $numero     = $this->nextNumero($empresaId, 'FAC');

            $fac = Factura::create([
                'empresa_id'       => $empresaId,
                'cliente_id'       => (int)$payload['cliente_id'],
                'usuario_id'       => (int)$u->id,
                'cotizacion_id'    => $payload['cotizacion_id'] ?? null,
                'numero'           => $numero,
                'estado'           => 'BORRADOR',
                'fecha'            => $payload['fecha'],
                'notas'            => $payload['notas'] ?? null,
                'subtotal'         => $tot['subtotal'],
                'total_descuentos' => $tot['total_descuentos'],
                'total_iva'        => $tot['total_iva'],
                'total'            => $tot['total'],
                'total_pagado'     => 0,
                'saldo'            => $tot['total'],
            ]);

            $this->guardarLineas($fac, $empresaId, $lineasCalc);

            return response()->json(['factura' => $fac->load('lineas')], 201);
        });
    }

    // =========================================================================
    // ACTUALIZAR  PUT /api/facturas/{id}
    // =========================================================================
    public function update(Request $request, $id)
    {
        $u   = $request->user();
        $fac = Factura::with('lineas')->findOrFail($id);

        if (!in_array($u->rol, ['SUPER_ADMIN', 'EMPRESA_ADMIN', 'OPERATIVO'], true))
            return response()->json(['message' => 'No autorizado'], 403);
        if ($u->rol !== 'SUPER_ADMIN' && (int)$fac->empresa_id !== (int)$u->empresa_id)
            return response()->json(['message' => 'No autorizado'], 403);
        if (in_array($fac->estado, ['ANULADA', 'EMITIDA'], true))
            return response()->json(['message' => 'No se puede editar una factura ' . $fac->estado], 422);

        $payload = $request->validate([
            'cliente_id'                  => ['sometimes', 'required', 'integer', 'min:1'],
            'fecha'                       => ['sometimes', 'required', 'date'],
            'notas'                       => ['nullable', 'string'],
            'lineas'                      => ['sometimes', 'required', 'array', 'min:1'],
            'lineas.*.item_id'            => ['nullable', 'integer', 'min:1'],
            'lineas.*.descripcion_manual' => ['nullable', 'string', 'max:255'],
            'lineas.*.cantidad'           => ['required_with:lineas', 'integer', 'min:1'],
            'lineas.*.valor_unitario'     => ['required_with:lineas', 'numeric', 'min:0'],
            'lineas.*.descuento'          => ['nullable', 'numeric', 'min:0'],
            'lineas.*.iva_pct'            => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        return DB::transaction(function () use ($fac, $payload) {
            if (array_key_exists('lineas', $payload)) {
                foreach ($payload['lineas'] as $i => $l) {
                    if (empty($l['item_id']) && trim((string)($l['descripcion_manual'] ?? '')) === '') {
                        return response()->json(['message' => "Línea #" . ($i + 1) . ": producto o descripción requerido."], 422);
                    }
                }
                $lineasCalc = $this->calcularLineas($payload['lineas']);
                $tot        = $this->calculos->calcularTotales($lineasCalc);

                FacturaLinea::where('factura_id', $fac->id)->delete();
                $this->guardarLineas($fac, $fac->empresa_id, $lineasCalc);

                $fac->subtotal         = $tot['subtotal'];
                $fac->total_descuentos = $tot['total_descuentos'];
                $fac->total_iva        = $tot['total_iva'];
                $fac->total            = $tot['total'];
                $fac->saldo            = max(0, $tot['total'] - (float)$fac->total_pagado);
            }

            if (isset($payload['cliente_id'])) $fac->cliente_id = (int)$payload['cliente_id'];
            if (isset($payload['fecha']))       $fac->fecha      = $payload['fecha'];
            if (array_key_exists('notas', $payload)) $fac->notas = $payload['notas'];

            $fac->save();

            return response()->json(['factura' => $fac->fresh()->load('lineas')]);
        });
    }

    // =========================================================================
    // EMITIR  POST /api/facturas/{id}/emitir
    // =========================================================================
    // =========================================================================
    // EMITIR  POST /api/facturas/{id}/emitir
    // Valida stock suficiente ANTES de emitir.
    // =========================================================================
    public function emitir(Request $request, $id)
    {
        $fac = $this->getFacAutorizada($request, $id);
        $u   = $request->user();

        if ($fac->estado !== 'BORRADOR')
            return response()->json(['message' => 'Solo se puede emitir desde BORRADOR'], 422);

        // ── Validar stock suficiente para cada línea ──
        $lineas = FacturaLinea::where('factura_id', $fac->id)
            ->whereNotNull('item_id')
            ->get();

        $erroresStock = [];

        foreach ($lineas as $linea) {
            $item = Item::where('empresa_id', $fac->empresa_id)
                ->where('id', $linea->item_id)
                ->where('controla_inventario', 1)
                ->first();

            if (!$item) continue;

            $inv = Inventario::where('empresa_id', $fac->empresa_id)
                ->where('item_id', $item->id)
                ->first();

            $disponible = $inv ? (float)$inv->cantidad_actual : 0;
            $solicitado = (int)$linea->cantidad;

            if ($solicitado > $disponible) {
                $erroresStock[] = [
                    'item_id'    => $item->id,
                    'nombre'     => $item->nombre,
                    'disponible' => $disponible,
                    'solicitado' => $solicitado,
                    'faltante'   => $solicitado - $disponible,
                ];
            }
        }

        if (!empty($erroresStock)) {
            return response()->json([
                'message' => 'Stock insuficiente para uno o más productos',
                'items'   => $erroresStock,
            ], 422);
        }

        return DB::transaction(function () use ($u, $fac) {
            $fac->estado = 'EMITIDA';
            $fac->save();
            $this->registrarMovimientosInventario($u, $fac);
            return response()->json(['factura' => $fac]);
        });
    }

    // =========================================================================
    // ANULAR  POST /api/facturas/{id}/anular
    // =========================================================================
    public function anular(Request $request, $id)
    {
        $fac = $this->getFacAutorizada($request, $id);

        if ($fac->estado === 'ANULADA')
            return response()->json(['message' => 'Ya está ANULADA'], 422);

        $fac->estado = 'ANULADA';
        $fac->save();

        return response()->json(['factura' => $fac]);
    }

    // =========================================================================
    // ELIMINAR  DELETE /api/facturas/{id}
    // =========================================================================
    public function destroy(Request $request, $id)
    {
        $u   = $request->user();
        $fac = Factura::findOrFail($id);

        if (!in_array($u->rol, ['SUPER_ADMIN', 'EMPRESA_ADMIN'], true))
            return response()->json(['message' => 'No autorizado'], 403);
        if ($u->rol !== 'SUPER_ADMIN' && (int)$fac->empresa_id !== (int)$u->empresa_id)
            return response()->json(['message' => 'No autorizado'], 403);
        if ($fac->estado === 'EMITIDA')
            return response()->json(['message' => 'No se puede eliminar una factura EMITIDA'], 422);

        $fac->delete();
        return response()->json(['ok' => true]);
    }

    // =========================================================================
    // REGISTRAR PAGO  POST /api/facturas/{id}/pagos
    //
    // Reglas de negocio:
    //
    // 1. Si el cliente tiene saldo_a_favor, se consume PRIMERO automáticamente
    //    antes de necesitar dinero nuevo. Así el operador solo ingresa lo que
    //    el cliente entregó hoy.
    //
    // 2. Si monto_aplicado > saldo pendiente de la factura (después de consumir
    //    saldo_a_favor), el exceso queda acumulado en clientes.saldo_a_favor.
    //    Ejemplo: factura $7.580, cliente paga $8.000 → saldo_a_favor += $420.
    //
    // 3. El billete físico NO se almacena. El front calcula el cambio a devolver
    //    localmente. Lo único que importa es cuánto abonó el cliente.
    //
    // Campos del request:
    //   fecha          (requerido)
    //   forma_pago     (requerido)
    //   monto_aplicado (requerido) — lo que el cliente entregó / transfirió hoy
    //   referencia     (opcional)
    //   notas          (opcional)
    // =========================================================================
    public function registrarPago(Request $request, $id)
    {
        $u   = $request->user();
        $fac = $this->getFacAutorizada($request, $id);

        if ($fac->estado !== 'EMITIDA') {
            return response()->json(['message' => 'Solo se pueden registrar pagos en facturas EMITIDAS'], 422);
        }

        $payload = $request->validate([
            'fecha'          => ['required', 'date'],
            'forma_pago'     => ['required', 'in:EFECTIVO,TRANSFERENCIA,TARJETA,BILLETERA,OTRO'],
            'monto_aplicado' => ['required', 'numeric', 'min:0.01'],
            'referencia'     => ['nullable', 'string', 'max:80'],
            'notas'          => ['nullable', 'string', 'max:255'],
        ]);

        $montoRecibidoHoy = (float)$payload['monto_aplicado'];

        $resultado = DB::transaction(function () use ($u, $fac, $payload, $montoRecibidoHoy) {

            $cliente                = Cliente::lockForUpdate()->findOrFail($fac->cliente_id);
            $saldoFavorActual       = (float)$cliente->saldo_a_favor;
            $saldoFactura           = (float)$fac->saldo;

            $saldoFavorConsumido    = min($saldoFavorActual, $saldoFactura);
            $saldoFacturaTrasCredito = $saldoFactura - $saldoFavorConsumido;

            $abonoEfectivo          = min($montoRecibidoHoy, $saldoFacturaTrasCredito);
            $exceso                 = max(0, $montoRecibidoHoy - $saldoFacturaTrasCredito);

            $totalAbonadoFactura    = $saldoFavorConsumido + $abonoEfectivo;

            $pago = Pago::create([
                'empresa_id'    => $fac->empresa_id,
                'cliente_id'    => $fac->cliente_id,
                'usuario_id'    => $u->id,
                'numero_recibo' => $this->nextNumero($fac->empresa_id, 'REC'),
                'fecha'         => $payload['fecha'],
                'forma_pago'    => $payload['forma_pago'],
                'referencia'    => $payload['referencia'] ?? null,
                'notas'         => $payload['notas'] ?? null,
                'total_pagado'  => $montoRecibidoHoy,
            ]);

            PagoAplicacion::create([
                'pago_id'    => $pago->id,
                'factura_id' => $fac->id,
                'empresa_id' => $fac->empresa_id,
                'monto'      => $totalAbonadoFactura,
            ]);

            $nuevoTotalPagado  = (float)$fac->total_pagado + $totalAbonadoFactura;
            $nuevoSaldo        = max(0, (float)$fac->total - $nuevoTotalPagado);

            $fac->total_pagado = $nuevoTotalPagado;
            $fac->saldo        = $nuevoSaldo;
            $fac->save();

            $nuevoSaldoFavor        = max(0, $saldoFavorActual - $saldoFavorConsumido + $exceso);
            $cliente->saldo_a_favor = $nuevoSaldoFavor;
            $cliente->save();

            return [
                'pago'                  => $pago->fresh()->load('aplicaciones'),
                'factura'               => $fac->fresh(),
                'saldo_favor_consumido' => round($saldoFavorConsumido, 2),
                'exceso_nuevo_favor'    => round($exceso, 2),
                'nuevo_saldo_favor'     => round($nuevoSaldoFavor, 2),
            ];
        });

        // Intentar enviar email DESPUÉS de guardar todo
        try {
            $brevoResult = app(BrevoService::class)->notificarPago($resultado['pago']);

            if (!$brevoResult['ok']) {
                Log::warning('[FacturaController@registrarPago] Pago guardado pero email no enviado', [
                    'pago_id'    => $resultado['pago']->id ?? null,
                    'factura_id' => $resultado['factura']->id ?? null,
                    'message'    => $brevoResult['message'] ?? null,
                    'details'    => $brevoResult['details'] ?? null,
                ]);
            } else {
                Log::info('[FacturaController@registrarPago] Email de pago enviado correctamente', [
                    'pago_id'    => $resultado['pago']->id ?? null,
                    'factura_id' => $resultado['factura']->id ?? null,
                    'message_id' => $brevoResult['message_id'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[FacturaController@registrarPago] Pago guardado pero ocurrió excepción enviando email', [
                'pago_id'    => $resultado['pago']->id ?? null,
                'factura_id' => $resultado['factura']->id ?? null,
                'error'      => $e->getMessage(),
            ]);
        }

        return response()->json($resultado, 201);
    }
    // =========================================================================
    // HELPERS
    // =========================================================================

    private function calcularLineas(array $lineas): array
    {
        return array_map(function ($l) {
            $calc = $this->calculos->calcularLinea($l);
            return [
                'item_id'            => $l['item_id'] ?? null,
                'descripcion_manual' => $l['descripcion_manual'] ?? null,
                'cantidad'           => (int)$l['cantidad'],
                'valor_unitario'     => (float)$l['valor_unitario'],
                'descuento'          => (float)($l['descuento'] ?? 0),
                'iva_pct'            => (float)($l['iva_pct'] ?? 0),
                'iva_valor'          => (float)$calc['iva_valor'],
                'total_linea'        => (float)$calc['total_linea'],
            ];
        }, $lineas);
    }

    private function guardarLineas(Factura $fac, int $empresaId, array $lineas): void
    {
        foreach ($lineas as $l) {
            FacturaLinea::create([
                'factura_id'         => $fac->id,
                'empresa_id'         => $empresaId,
                'item_id'            => $l['item_id'],
                'descripcion_manual' => $l['descripcion_manual'],
                'cantidad'           => $l['cantidad'],
                'valor_unitario'     => $l['valor_unitario'],
                'descuento'          => $l['descuento'],
                'iva_pct'            => $l['iva_pct'],
                'iva_valor'          => $l['iva_valor'],
                'total_linea'        => $l['total_linea'],
            ]);
        }
    }

    private function registrarMovimientosInventario($u, Factura $fac): void
    {
        $lineas = FacturaLinea::where('factura_id', $fac->id)
            ->whereNotNull('item_id')
            ->get();

        foreach ($lineas as $linea) {
            $item = Item::query()
                ->where('empresa_id', $fac->empresa_id)
                ->where('id', $linea->item_id)
                ->where('controla_inventario', 1)
                ->first();

            if (!$item) continue;

            $cantidad = (int)$linea->cantidad;
            if ($cantidad <= 0) continue;

            $inv = Inventario::query()
                ->where('empresa_id', $fac->empresa_id)
                ->where('item_id', $item->id)
                ->lockForUpdate()
                ->first();

            if (!$inv) {
                $inv = Inventario::create([
                    'empresa_id'      => $fac->empresa_id,
                    'item_id'         => $item->id,
                    'cantidad_actual' => 0,
                    'stock_minimo'    => 0,
                    'updated_at'      => now(),
                ]);
            }

            $anterior = (float)$inv->cantidad_actual;
            $nuevo    = max(0, $anterior - $cantidad);

            $inv->cantidad_actual = $nuevo;
            $inv->updated_at      = now();
            $inv->save();

            InventarioMovimiento::create([
                'empresa_id'       => $fac->empresa_id,
                'item_id'          => $item->id,
                'usuario_id'       => $u->id,
                'tipo'             => 'SALIDA',
                'motivo'           => 'Factura emitida',
                'referencia_tipo'  => 'FACTURA',
                'referencia_id'    => $fac->id,
                'cantidad'         => $cantidad,
                'saldo_resultante' => $nuevo,
                'ocurrido_en'      => now(),
            ]);
        }
    }

    private function nextNumero(int $empresaId, string $tipo): string
    {
        $num = Numeracion::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo', $tipo)
            ->lockForUpdate()
            ->first();

        if (!$num) abort(422, "No existe numeración tipo {$tipo}");

        $num->consecutivo = (int)$num->consecutivo + 1;
        $num->updated_at  = now();
        $num->save();

        $consec = str_pad((string)$num->consecutivo, max(1, (int)$num->relleno), '0', STR_PAD_LEFT);
        return $num->prefijo . '-' . $consec;
    }

    private function resolveEmpresaId(Request $request): int
    {
        $u = $request->user();
        if ($u->rol === 'SUPER_ADMIN') {
            $eid = (int)($request->input('empresa_id') ?? 0);
            if ($eid <= 0) abort(422, 'SUPER_ADMIN debe enviar empresa_id');
            return $eid;
        }
        if (!$u->empresa_id) abort(403, 'Sin empresa');
        return (int)$u->empresa_id;
    }

    private function getFacAutorizada(Request $request, $id): Factura
    {
        $u   = $request->user();
        $fac = Factura::findOrFail($id);
        if ($u->rol !== 'SUPER_ADMIN' && (int)$fac->empresa_id !== (int)$u->empresa_id)
            abort(403, 'No autorizado');
        return $fac;
    }


    // =========================================================================
// PAGOS DE UNA FACTURA  GET /api/facturas/{id}/pagos
// Devuelve PagoAplicacion aplanado para el frontend:
// { id, monto, pago_id, numero_recibo, fecha, forma_pago, referencia, notas }
// =========================================================================
public function pagos(Request $request, $id)
{
    $fac = $this->getFacAutorizada($request, $id);

    $pagos = PagoAplicacion::query()
        ->where('factura_id', $fac->id)
        ->with('pago')
        ->orderByDesc('id')
        ->get()
        ->map(function ($pa) {
            return [
                'id'            => $pa->id,
                'monto'         => (float) $pa->monto,
                'pago_id'       => $pa->pago_id,
                'numero_recibo' => $pa->pago->numero_recibo ?? null,
                'fecha'         => $pa->pago->fecha ?? null,
                'forma_pago'    => $pa->pago->forma_pago ?? null,
                'referencia'    => $pa->pago->referencia ?? null,
                'notas'         => $pa->pago->notas ?? null,
            ];
        })
        ->values();

    return response()->json([
        'pagos' => $pagos,
    ]);
}
}
