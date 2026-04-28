<?php

namespace App\Services;

use App\Models\EgresoManual;
use App\Models\EmpresaResumen;
use App\Repositories\EgresoManualRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EgresoManualService
{
    public function __construct(
        private readonly EgresoManualRepository $egresoManualRepository,
        private readonly ResumenService $resumenService,
    ) {}

    public function listar(int $empresaId, array $filters = []): LengthAwarePaginator
    {
        $paginator = $this->egresoManualRepository->paginate($empresaId, $filters);
        
        // Agregar URL del archivo a cada registro
        $paginator->getCollection()->transform(function ($egreso) {
            if ($egreso->archivo_path) {
                $egreso->archivo_url = Storage::url($egreso->archivo_path);
            } else {
                $egreso->archivo_url = null;
            }
            return $egreso;
        });
        
        return $paginator;
    }

    public function obtener(int $id, int $empresaId): EgresoManual
    {
        $egreso = $this->egresoManualRepository->findById($id);

        if (! $egreso || $egreso->empresa_id !== $empresaId) {
            throw new HttpException(404, 'Egreso no encontrado.');
        }
        
        if ($egreso->archivo_path) {
            $egreso->archivo_url = Storage::url($egreso->archivo_path);
        }

        return $egreso;
    }

    public function registrar(array $data, int $empresaId, int $usuarioId, ?array $archivoData = null): EgresoManual
    {
        $monto = (float) ($data['monto'] ?? 0);

        $this->validarMontoDisponibleParaRegistrar($empresaId, $monto);
        
        $egresoData = [
            'descripcion' => $data['descripcion'],
            'monto'       => $monto,
            'notas'       => $data['notas'] ?? null,
        ];
        
        if ($archivoData) {
            $egresoData['archivo_path'] = $archivoData['path'];
            $egresoData['archivo_mime'] = $archivoData['mime'];
            $egresoData['archivo_nombre'] = $archivoData['nombre'];
        }
        
        $egreso = $this->egresoManualRepository->registrar($egresoData, $empresaId, $usuarioId);
        
        if ($egreso->archivo_path) {
            $egreso->archivo_url = Storage::url($egreso->archivo_path);
        }

        return $egreso;
    }

    public function actualizar(int $id, array $data, int $empresaId, ?array $archivoData = null): EgresoManual
    {
        $egreso = $this->obtener($id, $empresaId);

        if ($egreso->estado === 'ANULADO') {
            throw new HttpException(422, 'No puedes editar un egreso anulado.');
        }

        $montoNuevo = array_key_exists('monto', $data)
            ? (float) $data['monto']
            : (float) $egreso->monto;

        $this->validarMontoDisponibleParaActualizar($empresaId, $egreso, $montoNuevo);
        
        $egresoData = [];
        
        if (array_key_exists('descripcion', $data)) {
            $egresoData['descripcion'] = $data['descripcion'];
        }
        
        if (array_key_exists('monto', $data)) {
            $egresoData['monto'] = $montoNuevo;
        }
        
        if (array_key_exists('notas', $data)) {
            $egresoData['notas'] = $data['notas'];
        }
        
        // Si hay nuevo archivo, reemplazar el anterior
        if ($archivoData) {
            // Eliminar archivo anterior si existe
            if ($egreso->archivo_path) {
                Storage::disk('public')->delete($egreso->archivo_path);
            }
            $egresoData['archivo_path'] = $archivoData['path'];
            $egresoData['archivo_mime'] = $archivoData['mime'];
            $egresoData['archivo_nombre'] = $archivoData['nombre'];
        }

        $egreso = $this->egresoManualRepository->actualizar($id, $egresoData, $empresaId);
        
        if ($egreso->archivo_path) {
            $egreso->archivo_url = Storage::url($egreso->archivo_path);
        }

        return $egreso;
    }

    public function anular(int $id, int $empresaId): EgresoManual
    {
        $egreso = $this->obtener($id, $empresaId);

        if ($egreso->estado === 'ANULADO') {
            throw new HttpException(422, 'El egreso ya está anulado.');
        }

        $egreso = $this->egresoManualRepository->anular($id, $empresaId);
        
        if ($egreso->archivo_path) {
            $egreso->archivo_url = Storage::url($egreso->archivo_path);
        }

        return $egreso;
    }

    private function validarMontoDisponibleParaRegistrar(int $empresaId, float $monto): void
    {
        $this->resumenService->recalcular($empresaId);

        $resumen = EmpresaResumen::find($empresaId);
        $disponible = (float) ($resumen?->balance_real ?? 0);

        if ($monto > $disponible) {
            throw new HttpException(
                422,
                "El monto del egreso supera lo disponible en caja. Disponible actual: {$disponible}."
            );
        }
    }

    private function validarMontoDisponibleParaActualizar(int $empresaId, EgresoManual $egresoActual, float $montoNuevo): void
    {
        $this->resumenService->recalcular($empresaId);

        $resumen = EmpresaResumen::find($empresaId);
        $balanceActual = (float) ($resumen?->balance_real ?? 0);

        // Como el balance actual ya tiene descontado este egreso,
        // lo "devolvemos" temporalmente para calcular cuánto realmente puede volver a gastar.
        $disponibleAjustado = $balanceActual + (float) $egresoActual->monto;

        if ($montoNuevo > $disponibleAjustado) {
            throw new HttpException(
                422,
                "El nuevo monto del egreso supera lo disponible. Disponible ajustado: {$disponibleAjustado}."
            );
        }
    }
}