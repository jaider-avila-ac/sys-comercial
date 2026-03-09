<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\CotizacionLinea;
use App\Models\Item;
use App\Models\Numeracion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CotizacionService
{
    public function __construct(
        private DocumentoCalculos $calc
    ) {}

    // =========================
    // 1) Crear cabecera BORRADOR
    // =========================
    public function crearCotizacion(User $u, int $empresaId, array $data): Cotizacion
    {
        return DB::transaction(function () use ($u, $empresaId, $data) {
            $cot = new Cotizacion();
            $cot->empresa_id = $empresaId;
            $cot->cliente_id = (int)$data['cliente_id'];
            $cot->usuario_id = $u->id;

            $cot->numero = 'BORRADOR';
            $cot->estado = 'BORRADOR';
            $cot->fecha = $data['fecha'] ?? now()->toDateString();
            $cot->fecha_vencimiento = $data['fecha_vencimiento'] ?? now()->addDays(7)->toDateString();
            $cot->notas = $data['notas'] ?? null;

            $cot->subtotal = 0;
            $cot->total_descuentos = 0;
            $cot->total_iva = 0;
            $cot->total = 0;

            $cot->save();
            return $cot;
        });
    }

    // =========================
    // 2) Actualizar cabecera (NO recalcula)
    // =========================
    public function actualizarCabecera(User $u, Cotizacion $cot, array $data): Cotizacion
    {
        return DB::transaction(function () use ($cot, $data) {
            $cot->fill(array_filter([
                'cliente_id' => $data['cliente_id'] ?? null,
                'fecha' => $data['fecha'] ?? null,
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'notas' => array_key_exists('notas', $data) ? $data['notas'] : null,
            ], fn($v) => $v !== null));

            $cot->save();
            return $cot;
        });
    }

    // =========================
    // 3) Agregar línea + recalcular
    // permite item_id o descripcion_manual o crear_item
    // =========================
    public function agregarLinea(User $u, Cotizacion $cot, array $data): CotizacionLinea
    {
        return DB::transaction(function () use ($cot, $data) {

            $this->assertEditable($cot);

            // crear item rápido (opcional)
            if (empty($data['item_id']) && !empty($data['crear_item']) && is_array($data['crear_item'])) {
                $data['item_id'] = $this->crearItemRapido($cot->empresa_id, $data['crear_item'])->id;
            }

            // si llega item_id y no llega valor_unitario, usa sugerido
            if (!empty($data['item_id']) && empty($data['valor_unitario'])) {
                $it = Item::query()
                    ->where('empresa_id', $cot->empresa_id)
                    ->where('id', (int)$data['item_id'])
                    ->first();

                if ($it) $data['valor_unitario'] = $it->precio_venta_sugerido ?? 0;
            }

            $linea = new CotizacionLinea();
            $linea->cotizacion_id = $cot->id;
            $linea->empresa_id = $cot->empresa_id;

            $linea->item_id = !empty($data['item_id']) ? (int)$data['item_id'] : null;
            $linea->descripcion_manual = $data['descripcion_manual'] ?? null;

            $linea->cantidad = $data['cantidad'] ?? 1;
            $linea->valor_unitario = $data['valor_unitario'] ?? 0;
            $linea->descuento = $data['descuento'] ?? 0;
            $linea->iva_pct = $data['iva_pct'] ?? 0;

            // ✅ cálculo compartido (reutilizable para factura)
            $r = $this->calc->calcularLinea($linea->toArray());
            $linea->iva_valor = $r['iva_valor'];
            $linea->total_linea = $r['total_linea'];

            $linea->save();

            // ✅ recalcular cabecera SOLO porque cambió líneas
            $this->recalcularTotales($cot);

            return $linea;
        });
    }

    // =========================
    // 4) Actualizar línea + recalcular
    // =========================
    public function actualizarLinea(User $u, Cotizacion $cot, CotizacionLinea $linea, array $data): CotizacionLinea
    {
        return DB::transaction(function () use ($cot, $linea, $data) {

            $this->assertEditable($cot);

            if ((int)$linea->cotizacion_id !== (int)$cot->id) {
                abort(404, 'Línea no pertenece a la cotización');
            }

            if (array_key_exists('item_id', $data)) {
                $linea->item_id = $data['item_id'] ? (int)$data['item_id'] : null;
            }
            if (array_key_exists('descripcion_manual', $data)) {
                $linea->descripcion_manual = $data['descripcion_manual'];
            }

            if (array_key_exists('cantidad', $data)) $linea->cantidad = $data['cantidad'];
            if (array_key_exists('valor_unitario', $data)) $linea->valor_unitario = $data['valor_unitario'];
            if (array_key_exists('descuento', $data)) $linea->descuento = $data['descuento'];
            if (array_key_exists('iva_pct', $data)) $linea->iva_pct = $data['iva_pct'];

            // ✅ cálculo compartido
            $r = $this->calc->calcularLinea($linea->toArray());
            $linea->iva_valor = $r['iva_valor'];
            $linea->total_linea = $r['total_linea'];

            $linea->save();

            // ✅ recalcular cabecera SOLO porque cambió líneas
            $this->recalcularTotales($cot);

            return $linea;
        });
    }

    // =========================
    // 5) Eliminar línea + recalcular
    // =========================
    public function eliminarLinea(User $u, Cotizacion $cot, CotizacionLinea $linea): void
    {
        DB::transaction(function () use ($cot, $linea) {

            $this->assertEditable($cot);

            if ((int)$linea->cotizacion_id !== (int)$cot->id) {
                abort(404, 'Línea no pertenece a la cotización');
            }

            $linea->delete();
            $this->recalcularTotales($cot);
        });
    }

    // =========================
    // 6) Emitir: asigna número + recalcula
    // =========================
    public function emitir(User $u, Cotizacion $cot): Cotizacion
    {
        return DB::transaction(function () use ($cot) {

            if (in_array($cot->estado, ['ANULADA','FACTURADA'], true)) {
                abort(409, 'No se puede emitir en este estado');
            }

            // si vencida -> marcar y parar
            if ($this->estaVencida($cot)) {
                $cot->estado = 'VENCIDA';
                $cot->save();
                abort(409, 'La cotización está vencida. Actualiza la fecha de vencimiento.');
            }

            if ($cot->lineas()->count() <= 0) {
                abort(422, 'Agrega al menos una línea antes de emitir.');
            }

            // ✅ recalcular SOLO aquí (emitir)
            $this->recalcularTotales($cot);

            if (!$cot->numero || $cot->numero === 'BORRADOR') {
                $cot->numero = $this->siguienteNumero($cot->empresa_id, 'COT');
            }

            $cot->estado = 'EMITIDA';
            $cot->save();

            return $cot;
        });
    }

    // =========================
    // 7) Confirmar vigencia (actualiza fecha_vencimiento)
    // =========================
    public function confirmarVigencia(User $u, Cotizacion $cot, string $nuevaFechaVencimiento): Cotizacion
    {
        return DB::transaction(function () use ($cot, $nuevaFechaVencimiento) {

            if (in_array($cot->estado, ['ANULADA','FACTURADA'], true)) {
                abort(409, 'No se puede confirmar vigencia en este estado');
            }

            $cot->fecha_vencimiento = $nuevaFechaVencimiento;

            if (!$this->estaVencida($cot)) {
                $cot->estado = ($cot->numero && $cot->numero !== 'BORRADOR') ? 'EMITIDA' : 'BORRADOR';
            } else {
                $cot->estado = 'VENCIDA';
            }

            $cot->save();
            return $cot;
        });
    }

    // =========================
    // 8) Convertir a factura (stub payload)
    // ✅ recalcula SOLO aquí también
    // =========================
    public function convertirAFactura(User $u, Cotizacion $cot): array
    {
        return DB::transaction(function () use ($cot) {

            if ($cot->estado !== 'EMITIDA') {
                abort(409, 'Solo se puede convertir una cotización EMITIDA.');
            }

            if ($this->estaVencida($cot)) {
                $cot->estado = 'VENCIDA';
                $cot->save();
                abort(409, 'Cotización vencida. Confirma vigencia o actualiza fecha_vencimiento.');
            }

            // ✅ recalcular SOLO aquí
            $this->recalcularTotales($cot);

            $payload = [
                'empresa_id' => $cot->empresa_id,
                'cliente_id' => $cot->cliente_id,
                'cotizacion_id' => $cot->id,
                'fecha' => now()->toDateString(),
                'notas' => $cot->notas,
                'subtotal' => $cot->subtotal,
                'total_descuentos' => $cot->total_descuentos,
                'total_iva' => $cot->total_iva,
                'total' => $cot->total,
                'lineas' => $cot->lineas()->get()->map(fn($l) => [
                    'item_id' => $l->item_id,
                    'descripcion_manual' => $l->descripcion_manual,
                    'cantidad' => (string)$l->cantidad,
                    'valor_unitario' => (string)$l->valor_unitario,
                    'descuento' => (string)$l->descuento,
                    'iva_pct' => (string)$l->iva_pct,
                    'iva_valor' => (string)$l->iva_valor,
                    'total_linea' => (string)$l->total_linea,
                ])->all(),
            ];

            $cot->estado = 'FACTURADA';
            $cot->save();

            return $payload;
        });
    }

    // =========================
    // HELPERS internos
    // =========================

    public function recalcularTotales(Cotizacion $cot): Cotizacion
    {
        // OJO: no recalcula líneas aquí, solo suma lo que ya está guardado.
        $lineas = $cot->lineas()->get()->map(fn($l) => $l->toArray())->all();

        $tot = $this->calc->calcularTotales($lineas);

        $cot->subtotal = $tot['subtotal'];
        $cot->total_descuentos = $tot['total_descuentos'];
        $cot->total_iva = $tot['total_iva'];
        $cot->total = $tot['total'];
        $cot->save();

        return $cot;
    }

    private function assertEditable(Cotizacion $cot): void
    {
        if (in_array($cot->estado, ['ANULADA','FACTURADA'], true)) {
            abort(409, 'No se puede modificar en este estado.');
        }
    }

    private function estaVencida(Cotizacion $cot): bool
    {
        $fv = Carbon::parse($cot->fecha_vencimiento)->endOfDay();
        return now()->gt($fv);
    }

    private function siguienteNumero(int $empresaId, string $tipo): string
    {
        $num = Numeracion::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo', $tipo)
            ->lockForUpdate()
            ->firstOrFail();

        $num->consecutivo = ((int)$num->consecutivo) + 1;
        $num->updated_at = now();
        $num->save();

        $relleno = max(1, (int)$num->relleno);
        $consec = str_pad((string)$num->consecutivo, $relleno, '0', STR_PAD_LEFT);

        return $num->prefijo . $consec;
    }

    private function crearItemRapido(int $empresaId, array $data): Item
    {
        $it = new Item();
        $it->empresa_id = $empresaId;
        $it->tipo = $data['tipo'] ?? 'SERVICIO';
        $it->nombre = $data['nombre'] ?? 'Item rápido';
        $it->descripcion = $data['descripcion'] ?? null;
        $it->precio_compra = $data['precio_compra'] ?? null;
        $it->precio_venta_sugerido = $data['precio_venta_sugerido'] ?? null;
        $it->controla_inventario = (int)($data['controla_inventario'] ?? 0);
        $it->unidad = $data['unidad'] ?? null;
        $it->is_activo = 1;
        $it->save();

        return $it;
    }
}