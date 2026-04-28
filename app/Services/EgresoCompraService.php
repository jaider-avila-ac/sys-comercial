<?php

namespace App\Services;

use App\Models\EgresoCompra;
use App\Repositories\EgresoCompraRepository;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EgresoCompraService
{
    public function __construct(
        private readonly EgresoCompraRepository $egresoCompraRepository,
    ) {}

    public function listar(int $empresaId): Collection
    {
        return $this->egresoCompraRepository->allByEmpresa($empresaId);
    }

    public function listarPorCompra(int $compraId): Collection
    {
        return $this->egresoCompraRepository->allByCompra($compraId);
    }

    public function obtener(int $id, int $empresaId): EgresoCompra
    {
        $egreso = $this->egresoCompraRepository->findById($id);

        if (! $egreso || $egreso->empresa_id !== $empresaId) {
            throw new HttpException(404, 'Egreso de compra no encontrado.');
        }

        return $egreso;
    }

    /**
     * Registrar un egreso de compra.
     * Se usa tanto para compras de contado (compra_id puede ser null aún)
     * como para pagos parciales de compras a crédito (compra_id requerido).
     * La actualización del saldo de la compra la hace CompraService al llamar este método.
     */
    public function registrar(array $data, int $empresaId, int $usuarioId): EgresoCompra
    {
        return $this->egresoCompraRepository->registrar($data, $empresaId, $usuarioId);
    }

    public function anular(int $id, int $empresaId): EgresoCompra
    {
        $egreso = $this->obtener($id, $empresaId);

        if ($egreso->estado === 'ANULADO') {
            throw new HttpException(409, 'El egreso ya está anulado.');
        }

        return $this->egresoCompraRepository->anular($id, $empresaId);
    }
}
