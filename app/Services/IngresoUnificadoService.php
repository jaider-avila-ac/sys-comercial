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
        $tipo   = $filters['tipo']   ?? '';
        $estado = $filters['estado'] ?? '';
        $desde  = $filters['desde']  ?? null;
        $hasta  = $filters['hasta']  ?? null;

        // Iniciar builder para la unión
        $union = null;

        // 1. Pagos de facturas (con datos del cliente) - solo si no hay filtro tipo o tipo es PAGO_FACTURA
        if (!$tipo || $tipo === 'PAGO_FACTURA') {
            $pagos = DB::table('ingresos_pagos')
                ->where('ingresos_pagos.empresa_id', $empresaId)
                ->leftJoin('pago_aplicaciones', 'ingresos_pagos.id', '=', 'pago_aplicaciones.ingreso_pago_id')
                ->leftJoin('facturas', 'pago_aplicaciones.factura_id', '=', 'facturas.id')
                ->leftJoin('clientes', 'facturas.cliente_id', '=', 'clientes.id')
                ->leftJoin('usuarios as u_reg', 'ingresos_pagos.usuario_id', '=', 'u_reg.id')
                ->leftJoin('usuarios as u_anul', 'ingresos_pagos.anulado_por_id', '=', 'u_anul.id')
                ->select(
                    DB::raw("CAST(ingresos_pagos.id AS CHAR) as id"),
                    'ingresos_pagos.numero as recibo',
                    'ingresos_pagos.fecha',
                    DB::raw("'PAGO_FACTURA' as tipo"),
                    'ingresos_pagos.monto',
                    'ingresos_pagos.forma_pago',
                    'ingresos_pagos.referencia',
                    'ingresos_pagos.notas',
                    'facturas.numero as descripcion',
                    'clientes.nombre_razon_social as cliente_nombre',
                    'ingresos_pagos.created_at as orden',
                    'ingresos_pagos.estado',
                    DB::raw("TRIM(CONCAT_WS(' ', u_reg.nombres, u_reg.apellidos)) as usuario_nombre"),
                    DB::raw("TRIM(CONCAT_WS(' ', u_anul.nombres, u_anul.apellidos)) as anulado_por_nombre")
                );
            $union = $pagos;
        }

        // 2. Ventas mostrador - solo si no hay filtro tipo o tipo es VENTA_MOSTRADOR
        if (!$tipo || $tipo === 'VENTA_MOSTRADOR') {
            $mostrador = DB::table('ingresos_mostrador')
                ->where('ingresos_mostrador.empresa_id', $empresaId)
                ->leftJoin('usuarios as u_reg', 'ingresos_mostrador.usuario_id', '=', 'u_reg.id')
                ->leftJoin('usuarios as u_anul', 'ingresos_mostrador.anulado_por_id', '=', 'u_anul.id')
                ->select(
                    DB::raw("CAST(ingresos_mostrador.id AS CHAR) as id"),
                    'ingresos_mostrador.numero as recibo',
                    'ingresos_mostrador.fecha',
                    DB::raw("'VENTA_MOSTRADOR' as tipo"),
                    'ingresos_mostrador.monto',
                    'ingresos_mostrador.forma_pago',
                    'ingresos_mostrador.referencia',
                    'ingresos_mostrador.notas',
                    'ingresos_mostrador.descripcion',
                    DB::raw('NULL as cliente_nombre'),
                    'ingresos_mostrador.created_at as orden',
                    'ingresos_mostrador.estado',
                    DB::raw("TRIM(CONCAT_WS(' ', u_reg.nombres, u_reg.apellidos)) as usuario_nombre"),
                    DB::raw("TRIM(CONCAT_WS(' ', u_anul.nombres, u_anul.apellidos)) as anulado_por_nombre")
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
                ->where('ingresos_manuales.empresa_id', $empresaId)
                ->leftJoin('usuarios as u_reg', 'ingresos_manuales.usuario_id', '=', 'u_reg.id')
                ->leftJoin('usuarios as u_anul', 'ingresos_manuales.anulado_por_id', '=', 'u_anul.id')
                ->select(
                    DB::raw("CAST(ingresos_manuales.id AS CHAR) as id"),
                    DB::raw("CONCAT('MAN-', ingresos_manuales.id) as recibo"),
                    'ingresos_manuales.fecha',
                    DB::raw("'INGRESO_MANUAL' as tipo"),
                    'ingresos_manuales.monto',
                    DB::raw("'EFECTIVO' as forma_pago"),
                    DB::raw("NULL as referencia"),
                    'ingresos_manuales.notas',
                    'ingresos_manuales.descripcion',
                    DB::raw('NULL as cliente_nombre'),
                    'ingresos_manuales.created_at as orden',
                    'ingresos_manuales.estado',
                    DB::raw("TRIM(CONCAT_WS(' ', u_reg.nombres, u_reg.apellidos)) as usuario_nombre"),
                    DB::raw("TRIM(CONCAT_WS(' ', u_anul.nombres, u_anul.apellidos)) as anulado_por_nombre")
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

        // Envolver en subquery para poder filtrar y ordenar sobre el UNION completo
        $query = DB::query()->fromSub($union, 'ingresos_union');

        if ($estado) {
            $query->where('estado', $estado);
        }
        if ($desde) {
            $query->whereDate('fecha', '>=', $desde);
        }
        if ($hasta) {
            $query->whereDate('fecha', '<=', $hasta);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('recibo', 'like', "%{$search}%")
                  ->orWhere('descripcion', 'like', "%{$search}%")
                  ->orWhere('notas', 'like', "%{$search}%")
                  ->orWhere('referencia', 'like', "%{$search}%")
                  ->orWhere('cliente_nombre', 'like', "%{$search}%");
            });
        }

        $query->orderBy('orden', 'desc');

        // Obtener resultados
        $results = $query->get();
        
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
                'descripcion' => $item->descripcion,
                'cliente_nombre' => $concepto,
                'estado' => $item->estado ?? 'ACTIVO',
                'usuario' => ($item->usuario_nombre ?? '') !== '' ? ['nombre_completo' => $item->usuario_nombre] : null,
                'anulado_por' => ($item->anulado_por_nombre ?? '') !== '' ? ['nombre_completo' => $item->anulado_por_nombre] : null,
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