<?php

namespace App\Services;

use App\Models\Numeracion;
use App\Repositories\NumeracionRepository;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class NumeracionService
{
    public function __construct(
        private readonly NumeracionRepository $numeracionRepository,
    ) {}

    public function listar(int $empresaId): Collection
    {
        $this->asegurarNumeracionesBase($empresaId);

        return Numeracion::where('empresa_id', $empresaId)
            ->orderByRaw("FIELD(tipo, 'COT','FAC','REC','COM','MOS','EGR')")
            ->get();
    }

    public function siguienteNumero(int $empresaId, string $tipo): string
    {
        $this->validarTipo($tipo);
        $this->asegurarNumeracionesBase($empresaId);

        // ✅ Delegar al repository que ya tiene lockForUpdate + transaction
        return $this->numeracionRepository->incrementar($empresaId, $tipo);
    }

    public function actualizar(int $empresaId, string $tipo, array $data): Numeracion
    {
        $this->validarTipo($tipo);
        $this->asegurarNumeracionesBase($empresaId);

        $permitidos = collect($data)->only(['prefijo', 'relleno'])->toArray();

        if (empty($permitidos)) {
            throw new HttpException(422, 'Solo se puede actualizar prefijo y relleno.');
        }

        $numeracion = $this->numeracionRepository->findByEmpresaYTipo($empresaId, $tipo);

        if (! $numeracion) {
            throw new HttpException(404, "No se encontró la numeración para el tipo {$tipo}.");
        }

        return $this->numeracionRepository->update($empresaId, $tipo, $permitidos);
    }

    private function asegurarNumeracionesBase(int $empresaId): void
    {
        foreach (Numeracion::TIPOS as $tipo) {
            Numeracion::firstOrCreate(
                ['empresa_id' => $empresaId, 'tipo' => $tipo],
                [
                    'prefijo'      => $this->prefijoPorTipo($tipo),
                    'relleno'      => 5,
                    'consecutivo'  => 0,  // ✅ nombre correcto de columna
                    'updated_at'   => now(),
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

    private function validarTipo(string $tipo): void
    {
        if (! in_array($tipo, Numeracion::TIPOS, true)) {
            throw new HttpException(422, "Tipo de numeración inválido: {$tipo}.");
        }
    }
}