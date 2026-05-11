<?php

namespace App\Console\Commands;

use App\Models\Compra;
use App\Models\EgresoCompra;
use App\Models\Empresa;
use App\Models\Factura;
use App\Models\PagoAplicacion;
use App\Services\ResumenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalcularTodo extends Command
{
    protected $signature   = 'comercial:recalcular-todo';
    protected $description = 'Recalcula total_pagado/saldo de facturas, saldo_pendiente de compras y empresa_resumen';

    public function __construct(private readonly ResumenService $resumenService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Iniciando recalculación completa...');

        DB::transaction(function () {
            $this->recalcularFacturas();
            $this->recalcularCompras();
        });

        $this->recalcularResumenes();

        $this->info('Recalculación completada.');
        return Command::SUCCESS;
    }

    private function recalcularFacturas(): void
    {
        $facturas = Factura::where('estado', 'EMITIDA')->get();
        $this->info("Facturas EMITIDAS: {$facturas->count()}");

        foreach ($facturas as $factura) {
            $totalPagado = PagoAplicacion::where('factura_id', $factura->id)
                ->whereHas('ingresoPago', fn($q) => $q->where('estado', 'ACTIVO'))
                ->sum('monto');

            $totalPagado = round((float) $totalPagado, 2);
            $saldo       = round(max(0, (float) $factura->total - $totalPagado), 2);

            if ((float) $factura->total_pagado !== $totalPagado || (float) $factura->saldo !== $saldo) {
                $factura->updateQuietly(['total_pagado' => $totalPagado, 'saldo' => $saldo]);
                $this->line("  Factura #{$factura->numero}: pagado={$totalPagado} saldo={$saldo}");
            }
        }
    }

    private function recalcularCompras(): void
    {
        $compras = Compra::whereNotNull('numero')
            ->whereNotIn('estado', ['ANULADA'])
            ->get();

        $this->info("Compras confirmadas activas: {$compras->count()}");

        foreach ($compras as $compra) {
            $pagado = EgresoCompra::where('compra_id', $compra->id)
                ->where('estado', 'ACTIVO')
                ->sum('monto');

            $pagado      = round((float) $pagado, 2);
            $nuevoSaldo  = round(max(0, (float) $compra->total - $pagado), 2);
            $nuevoEstado = $nuevoSaldo <= 0
                ? 'PAGADA'
                : ($pagado > 0 ? 'PARCIAL' : 'PENDIENTE');

            if ((float) $compra->saldo_pendiente !== $nuevoSaldo || $compra->estado !== $nuevoEstado) {
                $compra->updateQuietly(['saldo_pendiente' => $nuevoSaldo, 'estado' => $nuevoEstado]);
                $this->line("  Compra #{$compra->numero}: saldo={$nuevoSaldo} estado={$nuevoEstado}");
            }
        }
    }

    private function recalcularResumenes(): void
    {
        $empresas = Empresa::where('is_activa', true)->get();
        $this->info("Empresas activas: {$empresas->count()}");

        foreach ($empresas as $empresa) {
            $this->resumenService->recalcular($empresa->id);
            $this->line("  Resumen empresa ID={$empresa->id} actualizado.");
        }
    }
}
