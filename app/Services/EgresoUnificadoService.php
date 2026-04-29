<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Storage;

class EgresoUnificadoService
{
    public function listar(int $empresaId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search = $filters['search'] ?? '';
        $tipo   = $filters['tipo']   ?? '';
        $estado = $filters['estado'] ?? '';
        $desde  = $filters['desde']  ?? null;
        $hasta  = $filters['hasta']  ?? null;

        $union = null;

        // 1. Egresos de compras - toma el número de la compra relacionada
        if (!$tipo || $tipo === 'EGRESO_COMPRA') {
            $compras = DB::table('egresos_compras')
                ->leftJoin('compras', 'egresos_compras.compra_id', '=', 'compras.id')
                ->leftJoin('proveedores', 'compras.proveedor_id', '=', 'proveedores.id')
                ->where('egresos_compras.empresa_id', $empresaId)
                ->select(
                    DB::raw("CAST(egresos_compras.id AS CHAR) as id"),
                    // 🔥 Toma el número de la compra, o genera uno si no existe
                    DB::raw("COALESCE(compras.numero, CONCAT('COMP-', compras.id)) as recibo"),
                    'egresos_compras.fecha',
                    DB::raw("'EGRESO_COMPRA' as tipo"),
                    'egresos_compras.descripcion',
                    'egresos_compras.monto',
                    'egresos_compras.medio_pago',
                    'egresos_compras.notas',
                    'egresos_compras.estado',
                    'proveedores.nombre as proveedor_nombre',
                    'egresos_compras.archivo_path',
                    'egresos_compras.archivo_nombre',
                    'egresos_compras.created_at as orden'
                );

            if ($estado) {
                $compras->where('egresos_compras.estado', $estado);
            }

            $union = $compras;
        }

        // 2. Egresos manuales - usan su propio número
        if (!$tipo || $tipo === 'EGRESO_MANUAL') {
            $manuales = DB::table('egresos_manuales')
                ->where('empresa_id', $empresaId)
                ->select(
                    DB::raw("CAST(id AS CHAR) as id"),
                    DB::raw("COALESCE(numero, CONCAT('EGR-', id)) as recibo"),
                    'fecha',
                    DB::raw("'EGRESO_MANUAL' as tipo"),
                    'descripcion',
                    'monto',
                    'medio_pago',
                    'notas',
                    'estado',
                    DB::raw('NULL as proveedor_nombre'),
                    'archivo_path',
                    'archivo_nombre',
                    'created_at as orden'
                );

            if ($estado) {
                $manuales->where('estado', $estado);
            }

            $union = $union === null ? $manuales : $union->union($manuales);
        }

        if ($union === null) {
            return new Paginator(collect(), 0, $perPage, 1, []);
        }

        // Envolver en subquery para poder filtrar y ordenar sobre el UNION
        $query = DB::query()->fromSub($union, 'egresos_union');

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
                  ->orWhere('proveedor_nombre', 'like', "%{$search}%");
            });
        }

        $query->orderBy('fecha', 'desc')->orderBy('orden', 'desc');

        $results = $query->get();

        $items = $results->map(function ($item) {
            $tipoLabel = [
                'EGRESO_COMPRA' => 'Egreso compra',
                'EGRESO_MANUAL' => 'Egreso manual',
            ];

            $tipoIcono = [
                'EGRESO_COMPRA' => 'bi-cart',
                'EGRESO_MANUAL' => 'bi-cash',
            ];

            return [
                'id'               => $item->id,
                'recibo'           => $item->recibo,
                'fecha'            => $item->fecha,
                'tipo'             => $item->tipo,
                'tipo_label'       => $tipoLabel[$item->tipo] ?? $item->tipo,
                'tipo_icono'       => $tipoIcono[$item->tipo] ?? 'bi-arrow-up-right-circle',
                'descripcion'      => $item->descripcion,
                'monto'            => round((float) $item->monto, 2),
                'medio_pago'       => $item->medio_pago,
                'notas'            => $item->notas,
                'estado'           => $item->estado,
                'proveedor_nombre' => $item->proveedor_nombre,
                'archivo_url'      => $item->archivo_path ? Storage::url($item->archivo_path) : null,
                'archivo_nombre'   => $item->archivo_nombre,
            ];
        });

        $currentPage = request()->get('page', 1);
        $offset      = ($currentPage - 1) * $perPage;

        return new Paginator(
            $items->slice($offset, $perPage)->values(),
            $items->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }
}