<?php

namespace App\Repositories;

use App\Models\Cotizacion;
use App\Models\CotizacionLinea;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CotizacionRepository
{
    public function paginate(int $empresaId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search    = $filters['search']    ?? '';
        $estado    = $filters['estado']    ?? null;
        $clienteId = $filters['cliente_id'] ?? null;
        $desde     = $filters['desde']     ?? null;
        $hasta     = $filters['hasta']     ?? null;

        return Cotizacion::where('empresa_id', $empresaId)
            ->with(['cliente', 'usuario'])
            ->when($search, fn($q) => $q->where(fn($q) =>
                $q->where('numero', 'like', "%{$search}%")
                  ->orWhereHas('cliente', fn($q) =>
                        $q->where('nombre_razon_social', 'like', "%{$search}%"))
            ))
            ->when($estado,    fn($q) => $q->where('estado',     $estado))
            ->when($clienteId, fn($q) => $q->where('cliente_id', $clienteId))
            ->when($desde,     fn($q) => $q->whereDate('fecha',  '>=', $desde))
            ->when($hasta,     fn($q) => $q->whereDate('fecha',  '<=', $hasta))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function allByEmpresa(int $empresaId): Collection
    {
        return Cotizacion::where('empresa_id', $empresaId)
            ->with(['cliente', 'usuario'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function findById(int $id): ?Cotizacion
    {
        return Cotizacion::with(['lineas.item', 'cliente', 'usuario'])->find($id);
    }

    public function create(array $cabecera, array $lineas): Cotizacion
    {
        return DB::transaction(function () use ($cabecera, $lineas) {
            $cotizacion = Cotizacion::create($cabecera);
            $this->sincronizarLineas($cotizacion->id, $lineas);
            return $cotizacion->fresh(['lineas.item', 'cliente', 'usuario']);
        });
    }

    public function update(int $id, array $cabecera, array $lineas): Cotizacion
    {
        return DB::transaction(function () use ($id, $cabecera, $lineas) {
            $cotizacion = Cotizacion::findOrFail($id);
            $cotizacion->update($cabecera);
            $this->sincronizarLineas($id, $lineas);
            return $cotizacion->fresh(['lineas.item', 'cliente', 'usuario']);
        });
    }

    public function cambiarEstado(int $id, string $estado): Cotizacion
    {
        $cotizacion = Cotizacion::findOrFail($id);
        $cotizacion->update(['estado' => $estado]);
        return $cotizacion->fresh();
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            CotizacionLinea::where('cotizacion_id', $id)->delete();
            Cotizacion::findOrFail($id)->delete();
        });
    }

    private function sincronizarLineas(int $cotizacionId, array $lineas): void
    {
        CotizacionLinea::where('cotizacion_id', $cotizacionId)->delete();
        CotizacionLinea::insert(array_map(fn($l) => [...$l, 'cotizacion_id' => $cotizacionId], $lineas));
    }
}
