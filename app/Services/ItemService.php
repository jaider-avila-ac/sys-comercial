<?php

namespace App\Services;

use App\Models\Inventario;
use App\Models\Item;
use App\Repositories\Contracts\ItemRepositoryInterface;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ItemService
{
    public function __construct(
        private readonly ItemRepositoryInterface $itemRepository,
    ) {}

    public function listar(int $empresaId): Collection
    {
        return $this->itemRepository->allByEmpresa($empresaId);
    }

    public function obtener(int $id, int $empresaId): Item
    {
        $item = $this->itemRepository->findById($id);

        if (! $item || $item->empresa_id !== $empresaId) {
            throw new HttpException(404, 'Ítem no encontrado.');
        }

        return $item;
    }

    public function crear(array $data, int $empresaId): Item
    {
        $item = $this->itemRepository->create([
            ...$data,
            'empresa_id' => $empresaId,
            'is_activo'  => true,
        ]);

        // Si controla inventario, crear registro inicial en inventarios
        if ($item->controla_inventario) {
            Inventario::create([
                'empresa_id'        => $empresaId,
                'item_id'           => $item->id,
                'unidades_actuales' => 0,
                'unidades_minimas'  => $data['unidades_minimas'] ?? 0,
            ]);
        }

        return $item->fresh(['inventario']);
    }

    public function actualizar(int $id, array $data, int $empresaId): Item
    {
        $item = $this->obtener($id, $empresaId);

        // Si ahora controla inventario y antes no tenía registro, crearlo
        $controlaAhora = $data['controla_inventario'] ?? $item->controla_inventario;

        if ($controlaAhora && ! $item->inventario) {
            Inventario::create([
                'empresa_id'        => $empresaId,
                'item_id'           => $item->id,
                'unidades_actuales' => 0,
                'unidades_minimas'  => $data['unidades_minimas'] ?? 0,
            ]);
        }

        // Actualizar mínimas si se envían
        if ($item->inventario && isset($data['unidades_minimas'])) {
            $item->inventario->update([
                'unidades_minimas' => $data['unidades_minimas'],
            ]);
        }

        return $this->itemRepository->update($id, collect($data)->except('unidades_minimas')->toArray());
    }

    public function toggleActivo(int $id, int $empresaId): Item
    {
        $this->obtener($id, $empresaId);
        return $this->itemRepository->toggleActivo($id);
    }

    public function eliminar(int $id, int $empresaId): void
    {
        $this->obtener($id, $empresaId);
        $this->itemRepository->delete($id);
    }
}
