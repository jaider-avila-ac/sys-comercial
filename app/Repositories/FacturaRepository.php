<?php

namespace App\Repositories;

use App\Models\Factura;
use App\Models\FacturaLinea;
use App\Repositories\Contracts\FacturaRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FacturaRepository implements FacturaRepositoryInterface
{
    public function allByEmpresa(int $empresaId): Collection
    {
        return Factura::where('empresa_id', $empresaId)
            ->with(['cliente', 'usuario'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function findById(int $id): ?Factura
    {
        return Factura::with(['lineas.item', 'cliente', 'usuario', 'cotizacion'])->find($id);
    }

    public function create(array $cabecera, array $lineas): Factura
    {
        return DB::transaction(function () use ($cabecera, $lineas) {
            $factura = Factura::create($cabecera);
            $this->sincronizarLineas($factura->id, $lineas);
            return $factura->fresh(['lineas.item', 'cliente', 'usuario']);
        });
    }

    public function update(int $id, array $cabecera, array $lineas): Factura
    {
        return DB::transaction(function () use ($id, $cabecera, $lineas) {
            $factura = Factura::findOrFail($id);
            $factura->update($cabecera);
            $this->sincronizarLineas($id, $lineas);
            return $factura->fresh(['lineas.item', 'cliente', 'usuario']);
        });
    }

    public function cambiarEstado(int $id, string $estado): Factura
    {
        $factura = Factura::findOrFail($id);
        $factura->update(['estado' => $estado]);
        return $factura->fresh();
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            FacturaLinea::where('factura_id', $id)->delete();
            Factura::findOrFail($id)->delete();
        });
    }

    // ── Privado ───────────────────────────────────────────────────────────────

    private function sincronizarLineas(int $facturaId, array $lineas): void
    {
        FacturaLinea::where('factura_id', $facturaId)->delete();

        $registros = array_map(fn($l) => [
            ...$l,
            'factura_id' => $facturaId,
        ], $lineas);

        FacturaLinea::insert($registros);
    }
}
