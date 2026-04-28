<?php

namespace App\Services;

use App\Models\Empresa;
use App\Repositories\EmpresaRepository;
use App\Repositories\NumeracionRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EmpresaService
{
    public function __construct(
        private readonly EmpresaRepository $empresaRepository,
        private readonly NumeracionRepository $numeracionRepository,
    ) {}

    public function listar(): Collection
    {
        return $this->empresaRepository->all();
    }

    public function obtener(int $id): Empresa
    {
        $empresa = $this->empresaRepository->findById($id);

        if (! $empresa) {
            throw new HttpException(404, 'Empresa no encontrada.');
        }

        return $empresa;
    }

    public function crear(array $data): Empresa
    {
        return DB::transaction(function () use ($data) {
            $empresa = $this->empresaRepository->create($data);

            $this->numeracionRepository->crearParaEmpresa($empresa->id);

            return $empresa;
        });
    }

    public function actualizar(int $id, array $data): Empresa
    {
        $this->obtener($id);

        return $this->empresaRepository->update($id, $data);
    }

    public function eliminar(int $id): void
    {
        $empresa = $this->obtener($id);

        if ($empresa->usuarios()->where('is_activo', true)->exists()) {
            throw new HttpException(409, 'No se puede eliminar una empresa con usuarios activos.');
        }

        $this->empresaRepository->delete($id);
    }

    public function subirLogo(int $id, \Illuminate\Http\UploadedFile $archivo): Empresa
    {
        $empresa = $this->obtener($id);

        if ($empresa->logo_path) {
            Storage::disk('public')->delete($empresa->logo_path);
        }

        $path = $archivo->store("empresas/{$id}/logo", 'public');

        return $this->empresaRepository->update($id, [
            'logo_path'       => $path,
            'logo_mime'       => $archivo->getMimeType(),
            'logo_updated_at' => now(),
        ]);
    }

    public function eliminarLogo(int $id): Empresa
    {
        $empresa = $this->obtener($id);

        if ($empresa->logo_path) {
            Storage::disk('public')->delete($empresa->logo_path);
        }

        return $this->empresaRepository->update($id, [
            'logo_path'       => null,
            'logo_mime'       => null,
            'logo_updated_at' => null,
        ]);
    }
}