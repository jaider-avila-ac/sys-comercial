<?php

namespace App\Services;

use App\Models\Proveedor;
use App\Repositories\Contracts\ProveedorRepositoryInterface;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProveedorService
{
    public function __construct(
        private readonly ProveedorRepositoryInterface $proveedorRepository,
    ) {}

    public function listar(int $empresaId): Collection
    {
        return $this->proveedorRepository->allByEmpresa($empresaId);
    }

    public function obtener(int $id, int $empresaId): Proveedor
    {
        $proveedor = $this->proveedorRepository->findById($id);

        if (! $proveedor || $proveedor->empresa_id !== $empresaId) {
            throw new HttpException(404, 'Proveedor no encontrado.');
        }

        return $proveedor;
    }

    public function crear(array $data, int $empresaId): Proveedor
    {
        return $this->proveedorRepository->create([
            ...$data,
            'empresa_id' => $empresaId,
            'is_activo'  => true,
        ]);
    }

    public function actualizar(int $id, array $data, int $empresaId): Proveedor
    {
        $this->obtener($id, $empresaId);
        return $this->proveedorRepository->update($id, $data);
    }

    public function toggleActivo(int $id, int $empresaId): Proveedor
    {
        $this->obtener($id, $empresaId);
        return $this->proveedorRepository->toggleActivo($id);
    }

    public function eliminar(int $id, int $empresaId): void
    {
        $proveedor = $this->obtener($id, $empresaId);

        if ($proveedor->items()->exists()) {
            throw new HttpException(409, 'No se puede eliminar un proveedor con ítems asociados.');
        }

        $this->proveedorRepository->delete($id);
    }
}
