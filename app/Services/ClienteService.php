<?php

namespace App\Services;

use App\Models\Cliente;
use App\Repositories\ClienteRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ClienteService
{
    public function __construct(
        private readonly ClienteRepository $clienteRepository,
    ) {}

     public function listar(int $empresaId, string $search = '', int $perPage = 10, int $page = 1): array
    {
        $paginator = $this->clienteRepository->paginate($empresaId, $search, $perPage, $page);
        
        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
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