<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Repositories\CotizacionRepository;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CotizacionService
{
    public function __construct(
        private readonly CotizacionRepository $cotizacionRepository,
        private readonly LineaCalculoService           $calculoService,
        private readonly NumeracionService             $numeracionService,
    ) {}

   public function listar(int $empresaId, array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
{
    return $this->cotizacionRepository->paginar($empresaId, $filters);
}

    public function obtener(int $id, int $empresaId): Cotizacion
    {
        $cotizacion = $this->cotizacionRepository->findById($id);

        if (! $cotizacion || $cotizacion->empresa_id !== $empresaId) {
            throw new HttpException(404, 'Cotización no encontrada.');
        }

        return $cotizacion;
    }

    public function crear(array $data, int $empresaId, int $usuarioId): Cotizacion
{
    [$cabecera, $lineas] = $this->prepararDocumento($data, $empresaId, $usuarioId);

    $cabecera['numero'] = null; // ✅ NULL permite múltiples borradores
    $cabecera['estado'] = 'BORRADOR';

    return $this->cotizacionRepository->create($cabecera, $lineas);
}

    public function actualizar(int $id, array $data, int $empresaId, int $usuarioId): Cotizacion
    {
        $cotizacion = $this->obtener($id, $empresaId);

        if (! in_array($cotizacion->estado, ['BORRADOR'])) {
            throw new HttpException(409, 'Solo se pueden editar cotizaciones en BORRADOR.');
        }

        [$cabecera, $lineas] = $this->prepararDocumento($data, $empresaId, $usuarioId);

        return $this->cotizacionRepository->update($id, $cabecera, $lineas);
    }

    /**
     * Emitir: asigna número consecutivo y cambia estado a EMITIDA.
     */
    public function emitir(int $id, int $empresaId): Cotizacion
    {
        $cotizacion = $this->obtener($id, $empresaId);

        if ($cotizacion->estado !== 'BORRADOR') {
            throw new HttpException(409, 'Solo se pueden emitir cotizaciones en BORRADOR.');
        }

        $numero = $this->numeracionService->siguienteNumero($empresaId, 'COT');

        return $this->cotizacionRepository->update($id, [
            'numero' => $numero,
            'estado' => 'EMITIDA',
        ], []);
    }

    public function anular(int $id, int $empresaId): Cotizacion
    {
        $cotizacion = $this->obtener($id, $empresaId);

        if ($cotizacion->estado === 'ANULADA') {
            throw new HttpException(409, 'La cotización ya está anulada.');
        }

        if ($cotizacion->estado === 'FACTURADA') {
            throw new HttpException(409, 'No se puede anular una cotización ya facturada.');
        }

        return $this->cotizacionRepository->cambiarEstado($id, 'ANULADA');
    }

    public function eliminar(int $id, int $empresaId): void
    {
        $cotizacion = $this->obtener($id, $empresaId);

        if ($cotizacion->estado !== 'BORRADOR') {
            throw new HttpException(409, 'Solo se pueden eliminar cotizaciones en BORRADOR.');
        }

        $this->cotizacionRepository->delete($id);
    }

    // ── Privado ───────────────────────────────────────────────────────────────

    private function prepararDocumento(array $data, int $empresaId, int $usuarioId): array
    {
        $lineasCalculadas = array_map(
            fn($l) => $this->calculoService->calcularLinea($l),
            $data['lineas']
        );

        $totales = $this->calculoService->calcularTotalesDocumento($lineasCalculadas);

        $cabecera = [
            'empresa_id'        => $empresaId,
            'usuario_id'        => $usuarioId,
            'cliente_id'        => $data['cliente_id'],
            'fecha'             => $data['fecha'],
            'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
            'notas'             => $data['notas'] ?? null,
            ...$totales,
        ];

        return [$cabecera, $lineasCalculadas];
    }
}