<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

class IngresoUnificadoService
{
    public function listar(int $empresaId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search = $filters['search'] ?? '';
        $tipo   = $filters['tipo'] ?? ''; 
        $desde = $filters['desde'] ?? null;
        $hasta = $filters['hasta'] ?? null;

        // Iniciar builder para la unión
        $union = null;

        // 1. Pagos de facturas (con datos del cliente) - solo si no hay filtro tipo o tipo es PAGO_FACTURA
        if (!$tipo || $tipo === 'PAGO_FACTURA') {
            $pagos = DB::table('ingresos_pagos')
                ->where('ingresos_pagos.empresa_id', $empresaId)
                ->where('ingresos_pagos.estado', 'ACTIVO')
                ->leftJoin('pago_aplicaciones', 'ingresos_pagos.id', '=', 'pago_aplicaciones.ingreso_pago_id')
                ->leftJoin('facturas', 'pago_aplicaciones.factura_id', '=', 'facturas.id')
                ->leftJoin('clientes', 'facturas.cliente_id', '=', 'clientes.id')
                ->select(
                    DB::raw("CAST(ingresos_pagos.id AS CHAR) as id"),
                    'ingresos_pagos.numero as recibo',
                    'ingresos_pagos.fecha',
                    DB::raw("'PAGO_FACTURA' as tipo"),
                    'ingresos_pagos.monto',
                    'ingresos_pagos.forma_pago',
                    'ingresos_pagos.referencia',
                    'ingresos_pagos.notas',
                    'clientes.nombre_razon_social as cliente_nombre',
                    'ingresos_pagos.created_at as orden'
                );
            $union = $pagos;
        }

        // 2. Ventas mostrador - solo si no hay filtro tipo o tipo es VENTA_MOSTRADOR
        if (!$tipo || $tipo === 'VENTA_MOSTRADOR') {
            $mostrador = DB::table('ingresos_mostrador')
                ->where('empresa_id', $empresaId)
                ->where('estado', 'ACTIVO')
                ->select(
                    DB::raw("CAST(id AS CHAR) as id"),
                    'numero as recibo',
                    'fecha',
                    DB::raw("'VENTA_MOSTRADOR' as tipo"),
                    'monto',
                    'forma_pago',
                    'referencia',
                    'notas',
                    DB::raw('NULL as cliente_nombre'),
                    'created_at as orden'
                );
            
            if ($union === null) {
                $union = $mostrador;
            } else {
                $union = $union->union($mostrador);
            }
        }

        // 3. Ingresos manuales - solo si no hay filtro tipo o tipo es INGRESO_MANUAL
        if (!$tipo || $tipo === 'INGRESO_MANUAL') {
            $manuales = DB::table('ingresos_manuales')
                ->where('empresa_id', $empresaId)
                ->where('estado', 'ACTIVO')
                ->select(
                    DB::raw("CAST(id AS CHAR) as id"),
                    DB::raw("CONCAT('MAN-', id) as recibo"),
                    'fecha',
                    DB::raw("'INGRESO_MANUAL' as tipo"),
                    'monto',
                    DB::raw("'EFECTIVO' as forma_pago"),
                    DB::raw("NULL as referencia"),
                    'notas',
                    DB::raw('NULL as cliente_nombre'),
                    'created_at as orden'
                );
            
            if ($union === null) {
                $union = $manuales;
            } else {
                $union = $union->union($manuales);
            }
        }

        // Si no hay unión, retornar paginador vacío
        if ($union === null) {
            return new Paginator(collect(), 0, $perPage, 1, []);
        }
        
        // ✅ Filtros de fecha
        if ($desde) {
            $union->whereDate('fecha', '>=', $desde);
        }
        if ($hasta) {
            $union->whereDate('fecha', '<=', $hasta);
        }
        if ($search) {
            $union->where(function ($q) use ($search) {
                $q->where('recibo', 'like', "%{$search}%")
                  ->orWhere('notas', 'like', "%{$search}%")
                  ->orWhere('referencia', 'like', "%{$search}%")
                  ->orWhere('cliente_nombre', 'like', "%{$search}%");
            });
        }
        
        // Ordenar por fecha de creación (más reciente primero)
        $union->orderBy('orden', 'desc');
        
        // Obtener resultados
        $results = $union->get();
        
        // Transformar
        $items = $results->map(function ($item) {
            $formaPagoMap = [
                'EFECTIVO' => 'Efectivo',
                'TRANSFERENCIA' => 'Transferencia',
                'TARJETA' => 'Tarjeta',
                'BILLETERA' => 'Billetera',
                'OTRO' => 'Otro',
            ];

            $tipoLabel = [
                'PAGO_FACTURA' => 'Pago factura',
                'VENTA_MOSTRADOR' => 'Venta mostrador',
                'INGRESO_MANUAL' => 'Ingreso manual',
            ];

            $concepto = $item->cliente_nombre ?? ($item->notas ?: '—');

            return [
                'id' => $item->id,
                'recibo' => $item->recibo,
                'fecha' => $item->fecha,
                'tipo' => $item->tipo,
                'tipo_label' => $tipoLabel[$item->tipo] ?? $item->tipo,
                'monto' => round((float) $item->monto, 2),
                'forma_pago' => $formaPagoMap[$item->forma_pago] ?? $item->forma_pago,
                'referencia' => $item->referencia,
                'notas' => $item->notas,
                'cliente_nombre' => $concepto,
            ];
        });
        
        // Paginar manualmente
        $currentPage = request()->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        
        return new Paginator(
            $items->slice($offset, $perPage)->values(),
            $items->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }
}