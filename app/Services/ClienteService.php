<?php

namespace App\Services;

use App\Models\Cliente;
use App\Repositories\Contracts\ClienteRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ClienteService
{
    public function __construct(
        private readonly ClienteRepositoryInterface $clienteRepository,
    ) {}

    public function listar(int $empresaId, string $search = ''): LengthAwarePaginator
    {
        return $this->clienteRepository->paginate($empresaId, $search);
    }

    public function obtener(int $id, int $empresaId): Cliente
    {
        $cliente = $this->clienteRepository->findById($id);

        if (! $cliente || $cliente->empresa_id !== $empresaId) {
            throw new HttpException(404, 'Cliente no encontrado.');
        }

        return $cliente;
    }

    public function crear(array $data, int $empresaId): Cliente
    {
        return $this->clienteRepository->create([
            ...$data,
            'empresa_id' => $empresaId,
            'is_activo'  => true,
        ]);
    }

    public function actualizar(int $id, array $data, int $empresaId): Cliente
    {
        $this->obtener($id, $empresaId);
        return $this->clienteRepository->update($id, $data);
    }

    public function toggleActivo(int $id, int $empresaId): Cliente
    {
        $this->obtener($id, $empresaId);
        return $this->clienteRepository->toggleActivo($id);
    }

    public function eliminar(int $id, int $empresaId): void
    {
        $this->obtener($id, $empresaId);
        $this->clienteRepository->delete($id);
    }
}