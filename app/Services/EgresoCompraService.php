<?php

namespace App\Services;

use App\Models\EgresoCompra;
use App\Repositories\EgresoCompraRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EgresoCompraService
{
    public function __construct(
        private readonly EgresoCompraRepository $egresoCompraRepository,
    ) {}

    public function listar(int $empresaId): Collection
    {
        $egresos = $this->egresoCompraRepository->allByEmpresa($empresaId);
        
        // Agregar URL del archivo a cada registro
        return $egresos->map(function ($egreso) {
            return $this->mapWithFileUrl($egreso);
        });
    }

    public function listarPorCompra(int $compraId): Collection
    {
        $egresos = $this->egresoCompraRepository->allByCompra($compraId);
        
        return $egresos->map(function ($egreso) {
            return $this->mapWithFileUrl($egreso);
        });
    }

    public function obtener(int $id, int $empresaId): EgresoCompra
    {
        $egreso = $this->egresoCompraRepository->findById($id);

        if (! $egreso || $egreso->empresa_id !== $empresaId) {
            throw new HttpException(404, 'Egreso de compra no encontrado.');
        }

        return $this->mapWithFileUrl($egreso);
    }

    /**
     * Registrar un egreso de compra con archivo
     */
    public function registrar(array $data, int $empresaId, int $usuarioId, ?array $archivoData = null): EgresoCompra
    {
        $egresoData = [
            'compra_id'   => $data['compra_id'] ?? null,
            'fecha'       => $data['fecha'],
            'descripcion' => $data['descripcion'],
            'monto'       => $data['monto'],
            'medio_pago'  => $data['medio_pago'],
            'notas'       => $data['notas'] ?? null,
        ];
        
        if ($archivoData) {
            $egresoData['archivo_path'] = $archivoData['path'];
            $egresoData['archivo_mime'] = $archivoData['mime'];
            $egresoData['archivo_nombre'] = $archivoData['nombre'];
        }
        
        $egreso = $this->egresoCompraRepository->registrar($egresoData, $empresaId, $usuarioId);
        
        return $this->mapWithFileUrl($egreso);
    }

    public function anular(int $id, int $empresaId): EgresoCompra
    {
        $egreso = $this->obtener($id, $empresaId);

        if ($egreso->estado === 'ANULADO') {
            throw new HttpException(409, 'El egreso ya está anulado.');
        }

        $egreso = $this->egresoCompraRepository->anular($id, $empresaId);
        
        return $this->mapWithFileUrl($egreso);
    }
    
    /**
     * Agrega la URL pública del archivo al modelo
     */
    private function mapWithFileUrl(EgresoCompra $egreso): EgresoCompra
    {
        if ($egreso->archivo_path) {
            $egreso->archivo_url = Storage::url($egreso->archivo_path);
        } else {
            $egreso->archivo_url = null;
        }
        
        return $egreso;
    }
}