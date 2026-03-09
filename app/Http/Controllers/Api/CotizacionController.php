<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cotizacion;
use App\Models\CotizacionLinea;
use App\Models\Numeracion;
use App\Services\DocumentoCalculos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CotizacionController extends Controller
{
    private DocumentoCalculos $calculos;

    public function __construct()
    {
        $this->calculos = new DocumentoCalculos();
    }

    // =========================================================================
    // LISTAR  GET /api/cotizaciones
    // =========================================================================
    public function index(Request $request)
    {
        $u = $request->user();

        $q         = trim((string)$request->query('search', ''));
        $estado    = trim((string)$request->query('estado', ''));
        $clienteId = $request->query('cliente_id');

        $query = Cotizacion::query()
            ->with('cliente:id,nombre_razon_social');

        if ($u->rol !== 'SUPER_ADMIN') {
            if (!$u->empresa_id) return response()->json(['message' => 'Usuario sin empresa'], 403);
            $query->where('empresa_id', $u->empresa_id);
        } else {
            $empresaId = $request->query('empresa_id');
            if ($empresaId) $query->where('empresa_id', (int)$empresaId);
        }

        if ($estado !== '')  $query->where('estado', $estado);
        if ($clienteId)      $query->where('cliente_id', (int)$clienteId);

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('numero', 'like', "%{$q}%")
                    ->orWhere('notas', 'like', "%{$q}%");
            });
        }

        return response()->json($query->orderByDesc('id')->paginate(15));
    }

    // =========================================================================
    // VER  GET /api/cotizaciones/{id}
    // =========================================================================
    public function show(Request $request, $id)
    {
        $u   = $request->user();
        $cot = Cotizacion::with([
            'lineas',
            'cliente',
            'empresa',
            'usuario',
        ])->findOrFail($id);

        if ($u->rol !== 'SUPER_ADMIN' && (int)$cot->empresa_id !== (int)$u->empresa_id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        return response()->json(['cotizacion' => $cot]);
    }

    // =========================================================================
    // CREAR  POST /api/cotizaciones
    // =========================================================================
    public function store(Request $request)
    {
        $u = $request->user();

        if (!in_array($u->rol, ['SUPER_ADMIN', 'EMPRESA_ADMIN', 'OPERATIVO'], true)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $empresaId = $this->resolveEmpresaIdOrFail($request);

        $payload = $request->validate([
            'cliente_id'                  => ['required', 'integer', 'min:1'],
            'fecha'                       => ['required', 'date'],
            'fecha_vencimiento'           => ['required', 'date'],
            'notas'                       => ['nullable', 'string'],
            'lineas'                      => ['required', 'array', 'min:1'],
            'lineas.*.item_id'            => ['nullable', 'integer', 'min:1'],
            'lineas.*.descripcion_manual' => ['nullable', 'string', 'max:255'],
            // cantidad: entero positivo, sin decimales
            'lineas.*.cantidad'           => ['required', 'integer', 'min:1'],
            // valor_unitario, descuento, iva_pct: numéricos no negativos
            'lineas.*.valor_unitario'     => ['required', 'numeric', 'min:0'],
            'lineas.*.descuento'          => ['nullable', 'numeric', 'min:0'],
            'lineas.*.iva_pct'            => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        foreach ($payload['lineas'] as $i => $l) {
            if (empty($l['item_id']) && trim((string)($l['descripcion_manual'] ?? '')) === '') {
                return response()->json([
                    'message' => "La línea #" . ($i + 1) . " debe tener producto/servicio o descripción."
                ], 422);
            }
        }

        return DB::transaction(function () use ($u, $empresaId, $payload) {
            $numero           = $this->nextNumeroCotizacion($empresaId);
            $lineasCalculadas = $this->calcularLineas($payload['lineas']);
            $tot              = $this->calculos->calcularTotales($lineasCalculadas);

            $cot = Cotizacion::create([
                'empresa_id'        => $empresaId,
                'cliente_id'        => (int)$payload['cliente_id'],
                'usuario_id'        => (int)$u->id,
                'numero'            => $numero,
                'estado'            => 'BORRADOR',
                'fecha'             => $payload['fecha'],
                'fecha_vencimiento' => $payload['fecha_vencimiento'],
                'notas'             => $payload['notas'] ?? null,
                'subtotal'          => $tot['subtotal'],
                'total_descuentos'  => $tot['total_descuentos'],
                'total_iva'         => $tot['total_iva'],
                'total'             => $tot['total'],
            ]);

            $this->guardarLineas($cot, $empresaId, $lineasCalculadas);

            // Solo cargamos lineas — NO cliente aquí para evitar crash si la relación
            // no se resuelve correctamente dentro de la transacción
            return response()->json(['cotizacion' => $cot->load('lineas.item')], 201);
        });
    }

    // =========================================================================
    // ACTUALIZAR  PUT /api/cotizaciones/{id}
    // =========================================================================
    public function update(Request $request, $id)
    {
        $u   = $request->user();
        $cot = Cotizacion::with('lineas')->findOrFail($id);

        if (!in_array($u->rol, ['SUPER_ADMIN', 'EMPRESA_ADMIN', 'OPERATIVO'], true)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if ($u->rol !== 'SUPER_ADMIN' && (int)$cot->empresa_id !== (int)$u->empresa_id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if (in_array($cot->estado, ['ANULADA', 'FACTURADA'], true)) {
            return response()->json(['message' => 'No se puede editar una cotización ' . $cot->estado], 422);
        }

        $payload = $request->validate([
            'cliente_id'                  => ['sometimes', 'required', 'integer', 'min:1'],
            'fecha'                       => ['sometimes', 'required', 'date'],
            'fecha_vencimiento'           => ['sometimes', 'required', 'date'],
            'notas'                       => ['nullable', 'string'],
            'lineas'                      => ['sometimes', 'required', 'array', 'min:1'],
            'lineas.*.item_id'            => ['nullable', 'integer', 'min:1'],
            'lineas.*.descripcion_manual' => ['nullable', 'string', 'max:255'],
            'lineas.*.cantidad'           => ['required_with:lineas', 'integer', 'min:1'],
            'lineas.*.valor_unitario'     => ['required_with:lineas', 'numeric', 'min:0'],
            'lineas.*.descuento'          => ['nullable', 'numeric', 'min:0'],
            'lineas.*.iva_pct'            => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        return DB::transaction(function () use ($cot, $payload) {

            if (array_key_exists('lineas', $payload)) {
                foreach ($payload['lineas'] as $i => $l) {
                    if (empty($l['item_id']) && trim((string)($l['descripcion_manual'] ?? '')) === '') {
                        return response()->json([
                            'message' => "La línea #" . ($i + 1) . " debe tener producto/servicio o descripción."
                        ], 422);
                    }
                }

                $lineasCalculadas = $this->calcularLineas($payload['lineas']);
                $tot              = $this->calculos->calcularTotales($lineasCalculadas);

                CotizacionLinea::where('cotizacion_id', $cot->id)->delete();
                $this->guardarLineas($cot, $cot->empresa_id, $lineasCalculadas);

                $cot->subtotal         = $tot['subtotal'];
                $cot->total_descuentos = $tot['total_descuentos'];
                $cot->total_iva        = $tot['total_iva'];
                $cot->total            = $tot['total'];
            }

            if (array_key_exists('cliente_id', $payload))       $cot->cliente_id        = (int)$payload['cliente_id'];
            if (array_key_exists('fecha', $payload))             $cot->fecha             = $payload['fecha'];
            if (array_key_exists('fecha_vencimiento', $payload)) $cot->fecha_vencimiento = $payload['fecha_vencimiento'];
            if (array_key_exists('notas', $payload))             $cot->notas             = $payload['notas'];

            $cot->save();

            return response()->json(['cotizacion' => $cot->fresh()->load('lineas.item')]);
        });
    }

    // =========================================================================
    // ELIMINAR  DELETE /api/cotizaciones/{id}
    // =========================================================================
    public function destroy(Request $request, $id)
    {
        $u   = $request->user();
        $cot = Cotizacion::findOrFail($id);

        if (!in_array($u->rol, ['SUPER_ADMIN', 'EMPRESA_ADMIN'], true)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if ($u->rol !== 'SUPER_ADMIN' && (int)$cot->empresa_id !== (int)$u->empresa_id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if ($cot->estado === 'FACTURADA') {
            return response()->json(['message' => 'No se puede eliminar una cotización FACTURADA'], 422);
        }

        $cot->delete();
        return response()->json(['ok' => true]);
    }

    // =========================================================================
    // EMITIR  POST /api/cotizaciones/{id}/emitir
    // =========================================================================
  public function emitir(Request $request, $id)
{
    $cot = $this->getCotAutorizada($request, $id);

    if (!in_array($cot->estado, ['BORRADOR', 'VENCIDA'], true)) {
        return response()->json(['message' => 'No se puede emitir desde estado ' . $cot->estado], 422);
    }

    if ($cot->estado === 'VENCIDA') {
        return response()->json(['message' => 'Cotización VENCIDA. Confirma vigencia o actualiza fecha.'], 422);
    }

    return DB::transaction(function () use ($cot) {
        $cot->estado = 'EMITIDA';
        $cot->save();

        // ✅ Cotización NO descuenta inventario. No se registran movimientos aquí.

        return response()->json(['cotizacion' => $cot]);
    });
}

    public function anular(Request $request, $id)
    {
        $cot = $this->getCotAutorizada($request, $id);

        if (in_array($cot->estado, ['FACTURADA', 'ANULADA'], true)) {
            return response()->json(['message' => 'No se puede anular en estado ' . $cot->estado], 422);
        }

        $cot->estado = 'ANULADA';
        $cot->save();

        return response()->json(['cotizacion' => $cot]);
    }

    public function marcarVencida(Request $request, $id)
    {
        $cot = $this->getCotAutorizada($request, $id);

        if (!in_array($cot->estado, ['EMITIDA', 'BORRADOR'], true)) {
            return response()->json(['message' => 'No aplica desde ' . $cot->estado], 422);
        }

        $cot->estado = 'VENCIDA';
        $cot->save();

        return response()->json(['cotizacion' => $cot]);
    }

    public function confirmarVigencia(Request $request, $id)
    {
        $cot  = $this->getCotAutorizada($request, $id);

        if ($cot->estado !== 'VENCIDA') {
            return response()->json(['message' => 'Solo aplica si está VENCIDA'], 422);
        }

        $data = $request->validate(['fecha_vencimiento' => ['required', 'date']]);

        $cot->fecha_vencimiento = $data['fecha_vencimiento'];
        $cot->estado            = 'EMITIDA';
        $cot->save();

        return response()->json(['cotizacion' => $cot]);
    }

    public function convertirFactura(Request $request, $id)
    {
        $u   = $request->user();
        $cot = $this->getCotAutorizada($request, $id);

        if ($cot->estado === 'FACTURADA') return response()->json(['message' => 'Ya está FACTURADA'], 422);
        if ($cot->estado === 'ANULADA')   return response()->json(['message' => 'No se puede convertir una ANULADA'], 422);
        if ($cot->estado === 'VENCIDA')   return response()->json(['message' => 'Cotización VENCIDA. Confirma vigencia primero.'], 422);
        if ($cot->estado !== 'EMITIDA')   return response()->json(['message' => 'Primero emite la cotización'], 422);

        return DB::transaction(function () use ($u, $cot) {

            // Si ya existe una factura ligada a esta cotización, devolverla (idempotente)
            $ya = \App\Models\Factura::query()
                ->where('empresa_id', $cot->empresa_id)
                ->where('cotizacion_id', $cot->id)
                ->with('lineas')
                ->first();

            if ($ya) {
                // Asegurar que la cotización quede marcada como FACTURADA
                if ($cot->estado !== 'FACTURADA') {
                    $cot->estado = 'FACTURADA';
                    $cot->save();
                }

                return response()->json([
                    'message' => 'Ya existía una factura para esta cotización.',
                    'factura' => $ya,
                ]);
            }

            // Cargar líneas de cotización
            $cot->load('lineas.item');

            // Numeración FAC
            $numero = $this->nextNumeroFactura($cot->empresa_id);

            // Crear factura (BORRADOR)
            $fac = \App\Models\Factura::create([
                'empresa_id'       => $cot->empresa_id,
                'cliente_id'       => $cot->cliente_id,
                'usuario_id'       => $u->id,
                'cotizacion_id'    => $cot->id,
                'numero'           => $numero,
                'estado'           => 'BORRADOR',
                'fecha'            => $cot->fecha,     // o now()->toDateString() si prefieres
                'notas'            => $cot->notas,

                'subtotal'         => $cot->subtotal,
                'total_descuentos' => $cot->total_descuentos,
                'total_iva'        => $cot->total_iva,
                'total'            => $cot->total,

                'total_pagado'     => 0,
                'saldo'            => $cot->total,
            ]);

            // Copiar líneas (FacturaLinea)
            foreach ($cot->lineas as $l) {
                \App\Models\FacturaLinea::create([
                    'factura_id'         => $fac->id,
                    'empresa_id'         => $cot->empresa_id,
                    'item_id'            => $l->item_id,
                    'descripcion_manual' => $l->descripcion_manual,
                    'cantidad'           => (int)$l->cantidad,
                    'valor_unitario'     => (float)$l->valor_unitario,
                    'descuento'          => (float)$l->descuento,
                    'iva_pct'            => (float)$l->iva_pct,
                    'iva_valor'          => (float)$l->iva_valor,
                    'total_linea'        => (float)$l->total_linea,
                ]);
            }

            // Marcar cotización como FACTURADA
            $cot->estado = 'FACTURADA';
            $cot->save();

            return response()->json([
                'message' => 'Cotización convertida a factura (BORRADOR).',
                'factura' => $fac->load('lineas'),
            ], 201);
        });
    }

    /**
     * Numeración FAC (igual a nextNumeroCotizacion pero tipo FAC)
     */
    private function nextNumeroFactura(int $empresaId): string
    {
        $num = Numeracion::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo', 'FAC')
            ->lockForUpdate()
            ->first();

        if (!$num) abort(422, 'No existe numeración tipo FAC para la empresa');

        $num->consecutivo = (int)$num->consecutivo + 1;
        $num->updated_at  = now();
        $num->save();

        $consec = str_pad((string)$num->consecutivo, max(1, (int)$num->relleno), '0', STR_PAD_LEFT);
        return $num->prefijo . '-' . $consec;
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    private function calcularLineas(array $lineas): array
    {
        return array_map(function ($l) {
            $calc = $this->calculos->calcularLinea($l);
            return [
                'item_id'            => $l['item_id'] ?? null,
                'descripcion_manual' => $l['descripcion_manual'] ?? null,
                'cantidad'           => (int)$l['cantidad'],          // entero
                'valor_unitario'     => (float)$l['valor_unitario'],
                'descuento'          => (float)($l['descuento'] ?? 0),
                'iva_pct'            => (float)($l['iva_pct'] ?? 0),
                'iva_valor'          => (float)$calc['iva_valor'],
                'total_linea'        => (float)$calc['total_linea'],
            ];
        }, $lineas);
    }

    private function guardarLineas(Cotizacion $cot, int $empresaId, array $lineas): void
    {
        foreach ($lineas as $l) {
            CotizacionLinea::create([
                'cotizacion_id'      => $cot->id,
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



    private function resolveEmpresaIdOrFail(Request $request): int
    {
        $u = $request->user();

        if ($u->rol === 'SUPER_ADMIN') {
            $empresaId = (int)($request->input('empresa_id') ?? $request->query('empresa_id') ?? 0);
            if ($empresaId <= 0) abort(422, 'SUPER_ADMIN debe enviar empresa_id');
            return $empresaId;
        }

        if (!$u->empresa_id) abort(403, 'Usuario sin empresa asignada');
        return (int)$u->empresa_id;
    }

    private function getCotAutorizada(Request $request, $id): Cotizacion
    {
        $u   = $request->user();
        $cot = Cotizacion::findOrFail($id);

        if ($u->rol !== 'SUPER_ADMIN' && (int)$cot->empresa_id !== (int)$u->empresa_id) {
            abort(403, 'No autorizado');
        }

        return $cot;
    }

    private function nextNumeroCotizacion(int $empresaId): string
    {
        $num = Numeracion::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo', 'COT')
            ->lockForUpdate()
            ->first();

        if (!$num) abort(422, 'No existe numeración tipo COT para la empresa');

        $num->consecutivo = (int)$num->consecutivo + 1;
        $num->updated_at  = now();
        $num->save();

        $consec = str_pad((string)$num->consecutivo, max(1, (int)$num->relleno), '0', STR_PAD_LEFT);
        return $num->prefijo . '-' . $consec;
    }
}
