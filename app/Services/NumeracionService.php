<?php

namespace App\Services;

use App\Models\Numeracion;
use App\Repositories\Contracts\NumeracionRepositoryInterface;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class NumeracionService
{
    public function __construct(
        private readonly NumeracionRepositoryInterface $numeracionRepository,
    ) {}

    /**
     * Retorna todas las numeraciones de la empresa.
     * Se usa en el panel de Ajustes para visualizar/editar.
     */
    public function listar(int $empresaId): Collection
    {
        return $this->numeracionRepository->allByEmpresa($empresaId);
    }

    /**
     * Genera el siguiente número consecutivo para el tipo dado.
     * Llamado internamente desde otros servicios (Facturas, Cotizaciones, etc.)
     * Ejemplo resultado: "FAC-00001"
     */
    public function siguienteNumero(int $empresaId, string $tipo): string
    {
        $this->validarTipo($tipo);
        return $this->numeracionRepository->incrementar($empresaId, $tipo);
    }

    /**
     * Permite al admin editar prefijo y relleno de una numeración.
     * No se permite editar el consecutivo directamente.
     */
    public function actualizar(int $empresaId, string $tipo, array $data): \App\Models\Numeracion
    {
        $this->validarTipo($tipo);

        $permitidos = collect($data)->only(['prefijo', 'relleno'])->toArray();

        if (empty($permitidos)) {
            throw new HttpException(422, 'Solo se puede actualizar prefijo y relleno.');
        }

        return $this->numeracionRepository->update($empresaId, $tipo, $permitidos);
    }

    // ── Privados ──────────────────────────────────────────────────────────────

    private function validarTipo(string $tipo): void
    {
        if (! in_array($tipo, Numeracion::TIPOS)) {
            throw new HttpException(422, "Tipo de numeración inválido: {$tipo}.");
        }
    }
}