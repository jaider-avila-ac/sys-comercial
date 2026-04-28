<?php

namespace App\Services;

use App\Models\Numeracion;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class NumeracionService
{
    public function listar(int $empresaId): Collection
    {
        $this->asegurarNumeracionesBase($empresaId);

        return Numeracion::where('empresa_id', $empresaId)
            ->orderBy('tipo')
            ->get();
    }

    public function siguienteNumero(int $empresaId, string $tipo): string
    {
        $this->validarTipo($tipo);
        $this->asegurarNumeracionesBase($empresaId);

        $numeracion = Numeracion::where('empresa_id', $empresaId)
            ->where('tipo', $tipo)
            ->lockForUpdate()
            ->first();

        if (! $numeracion) {
            throw new HttpException(404, "No se encontró la numeración para el tipo {$tipo}.");
        }

        $siguiente = $numeracion->consecutivo_actual + 1;

        $numeracion->update([
            'consecutivo_actual' => $siguiente,
        ]);

        return $this->formatearNumero(
            $numeracion->prefijo,
            $siguiente,
            $numeracion->relleno
        );
    }

    public function actualizar(int $empresaId, string $tipo, array $data): Numeracion
    {
        $this->validarTipo($tipo);
        $this->asegurarNumeracionesBase($empresaId);

        $permitidos = collect($data)->only(['prefijo', 'relleno'])->toArray();

        if (empty($permitidos)) {
            throw new HttpException(422, 'Solo se puede actualizar prefijo y relleno.');
        }

        $numeracion = Numeracion::where('empresa_id', $empresaId)
            ->where('tipo', $tipo)
            ->first();

        if (! $numeracion) {
            throw new HttpException(404, "No se encontró la numeración para el tipo {$tipo}.");
        }

        $numeracion->update($permitidos);

        return $numeracion->fresh();
    }

    private function asegurarNumeracionesBase(int $empresaId): void
    {
        foreach (Numeracion::TIPOS as $tipo) {
            Numeracion::firstOrCreate(
                [
                    'empresa_id' => $empresaId,
                    'tipo'       => $tipo,
                ],
                [
                    'prefijo'             => $this->prefijoPorTipo($tipo),
                    'relleno'             => 5,
                    'consecutivo_actual'  => 0,
                ]
            );
        }
    }

    private function prefijoPorTipo(string $tipo): string
    {
        return match ($tipo) {
            'FAC' => 'FAC',
            'COT' => 'COT',
            'COM' => 'COM',
            default => $tipo,
        };
    }

    private function formatearNumero(string $prefijo, int $consecutivo, int $relleno): string
    {
        return $prefijo . '-' . str_pad((string) $consecutivo, $relleno, '0', STR_PAD_LEFT);
    }

    private function validarTipo(string $tipo): void
    {
        if (! in_array($tipo, Numeracion::TIPOS, true)) {
            throw new HttpException(422, "Tipo de numeración inválido: {$tipo}.");
        }
    }
}