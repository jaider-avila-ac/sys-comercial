<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\Autoriza;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\InventarioMovimiento;
use App\Models\Item;
use App\Models\Numeracion;
use App\Models\Pago;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VentaRapidaController extends Controller
{
    use Autoriza;

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
     * Busca o crea el cliente genérico MOSTRADOR.
     */
    private function getClienteMostrador(int $empresaId): Cliente
{
    return Cliente::firstOrCreate(
        [
            'empresa_id'          => $empresaId,
            'nombre_razon_social' => 'MOSTRADOR',
        ],
        [
            'tipo'            => 'PERSONA_NATURAL',
            'num_documento'   => 'MOSTRADOR',
            'email'           => null,
            'telefono'        => null,
            'direccion'       => null,
            'saldo_a_favor'   => 0,
        ]
    );
}

    private function nextNumero(int $empresaId, string $tipo): string
    {
        $num = Numeracion::where('empresa_id', $empresaId)
            ->where('tipo', $tipo)
            ->lockForUpdate()
            ->first();

        if (!$num) {
            abort(422, "No existe numeración tipo {$tipo}");
        }

        $num->consecutivo = (int) $num->consecutivo + 1;
        $num->updated_at  = now();
        $num->save();

        $consec = str_pad(
            (string) $num->consecutivo,
            max(1, (int) $num->relleno),
            '0',
            STR_PAD_LEFT
        );

        return $num->prefijo . '-' . $consec;
    }

    /**
     * GET /api/ventas-rapidas
     */
    public function index(Request $request)
    {
        $empresaId = $this->resolveEmpresaId($request);

        $mostrador = Cliente::where('empresa_id', $empresaId)
            ->where('nombre_razon_social', 'MOSTRADOR')
            ->first();

        if (!$mostrador) {
            return response()->json([
                'data'         => [],
                'total'        => 0,
                'current_page' => 1,
                'last_page'    => 1,
            ]);
        }

        $rows = Pago::where('empresa_id', $empresaId)
            ->where('cliente_id', $mostrador->id)
            ->when($request->filled('desde'), fn ($q) =>
                $q->whereDate('fecha', '>=', $request->input('desde'))
            )
            ->when($request->filled('hasta'), fn ($q) =>
                $q->whereDate('fecha', '<=', $request->input('hasta'))
            )
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json($rows);
    }

    /**
     * POST /api/ventas-rapidas
     */
    public function store(Request $request)
    {
        $u = $this->user($request);
        $this->requireAnyRole($u, ['SUPER_ADMIN', 'EMPRESA_ADMIN', 'OPERATIVO']);

        $empresaId = $this->resolveEmpresaId($request);

        $data = $request->validate([
            'item_id'        => ['required', 'integer', 'min:1'],
            'cantidad'       => ['required', 'numeric', 'min:0.001'],
            'valor_unitario' => ['required', 'numeric', 'min:0'],
            'forma_pago'     => ['required', 'in:EFECTIVO,TRANSFERENCIA,TARJETA,BILLETERA,OTRO'],
            'referencia'     => ['nullable', 'string', 'max:80'],
        ]);

        try {
            $item = Item::where('empresa_id', $empresaId)
                ->where('id', $data['item_id'])
                ->where('is_activo', 1)
                ->firstOrFail();

            $cantidad      = (float) $data['cantidad'];
            $valorUnitario = (float) $data['valor_unitario'];
            $total         = round($cantidad * $valorUnitario, 2);
            $hoy           = Carbon::now('America/Bogota')->toDateString();

            return DB::transaction(function () use ($u, $empresaId, $item, $cantidad, $valorUnitario, $total, $hoy, $data) {

                $mostrador = $this->getClienteMostrador($empresaId);

                $nuevoStock = null;

                if ($item->controla_inventario) {
                    $inv = Inventario::where('empresa_id', $empresaId)
                        ->where('item_id', $item->id)
                        ->lockForUpdate()
                        ->first();

                    $disponible = $inv ? (float) $inv->cantidad_actual : 0;

                    if ($cantidad > $disponible) {
                        return response()->json([
                            'message' => 'Stock insuficiente. Disponible: ' . number_format($disponible, 2, '.', ','),
                        ], 422);
                    }
                }

                $numeroRecibo = $this->nextNumero($empresaId, 'REC');

                $unidad = $item->unidad ? " {$item->unidad}" : '';
                $notas  = "Venta rápida: {$item->nombre} ({$cantidad}{$unidad} × $" .
                    number_format($valorUnitario, 2, '.', ',' ) . ")";

                $notas = mb_substr($notas, 0, 255);

                $pago = Pago::create([
                    'empresa_id'    => $empresaId,
                    'cliente_id'    => $mostrador->id,
                    'usuario_id'    => $u->id,
                    'numero_recibo' => $numeroRecibo,
                    'fecha'         => $hoy,
                    'forma_pago'    => $data['forma_pago'],
                    'referencia'    => $data['referencia'] ?? null,
                    'notas'         => $notas,
                    'total_pagado'  => $total,
                ]);

                if ($item->controla_inventario) {
                    $inv = Inventario::where('empresa_id', $empresaId)
                        ->where('item_id', $item->id)
                        ->lockForUpdate()
                        ->first();

                    if (!$inv) {
                        return response()->json([
                            'message' => 'El item controla inventario pero no tiene registro en inventarios.',
                        ], 422);
                    }

                    $nuevoStock = max(0, (float) $inv->cantidad_actual - $cantidad);

                    $inv->update([
                        'cantidad_actual' => $nuevoStock,
                        'updated_at'      => now(),
                    ]);

                    InventarioMovimiento::create([
                        'empresa_id'       => $empresaId,
                        'item_id'          => $item->id,
                        'usuario_id'       => $u->id,
                        'tipo'             => 'SALIDA',
                        'motivo'           => "Venta rápida — {$item->nombre}",
                        'referencia_tipo'  => 'OTRO',
                        'referencia_id'    => $pago->id,
                        'cantidad'         => $cantidad,
                        'saldo_resultante' => $nuevoStock,
                        'ocurrido_en'      => now(),
                    ]);
                }

                return response()->json([
                    'ok'            => true,
                    'pago_id'       => $pago->id,
                    'numero_recibo' => $pago->numero_recibo,
                    'descripcion'   => $notas,
                    'total'         => $total,
                    'fecha'         => $hoy,
                    'stock_nuevo'   => $nuevoStock,
                ], 201);
            });
        } catch (\Throwable $e) {
            Log::error('Error registrando venta rápida', [
                'message'   => $e->getMessage(),
                'line'      => $e->getLine(),
                'file'      => $e->getFile(),
                'empresaId' => $empresaId,
                'userId'    => $u->id ?? null,
                'payload'   => $request->all(),
            ]);

            return response()->json([
                'message' => 'Error interno al registrar la venta rápida.',
                'error'   => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }
}