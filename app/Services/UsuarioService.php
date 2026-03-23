<?php

namespace App\Services;

use App\Models\Usuario;
use App\Repositories\Contracts\UsuarioRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UsuarioService
{
    public function __construct(
        private readonly UsuarioRepositoryInterface $usuarioRepository,
    ) {}

    public function listarPorEmpresa(int $empresaId): Collection
    {
        return $this->usuarioRepository->allByEmpresa($empresaId);
    }

    public function obtener(int $id, int $empresaId, bool $esSuperAdmin = false): Usuario
    {
        $usuario = $this->usuarioRepository->findById($id);

        if (! $usuario) {
            throw new HttpException(404, 'Usuario no encontrado.');
        }

        // Verificar que el usuario pertenece a la empresa (excepto SUPER_ADMIN)
        if (! $esSuperAdmin && $usuario->empresa_id !== $empresaId) {
            throw new HttpException(403, 'No tienes acceso a este usuario.');
        }

        return $usuario;
    }

    public function crear(array $data, int $empresaId): Usuario
    {
        // Verificar email único dentro de la empresa
        $existe = $this->usuarioRepository->findByEmail($data['email']);

        if ($existe) {
            throw new HttpException(409, 'Ya existe un usuario con ese correo.');
        }

        return $this->usuarioRepository->create([
            ...$data,
            'empresa_id'    => $empresaId,
            'password_hash' => Hash::make($data['password']),
            'is_activo'     => true,
        ]);
    }

    public function actualizar(int $id, array $data, int $empresaId, bool $esSuperAdmin = false): Usuario
    {
        $this->obtener($id, $empresaId, $esSuperAdmin); // valida acceso

        $payload = collect($data)->except(['password', 'empresa_id'])->toArray();

        return $this->usuarioRepository->update($id, $payload);
    }

    public function cambiarPassword(int $id, string $nuevaPassword, int $empresaId, bool $esSuperAdmin = false): void
    {
        $this->obtener($id, $empresaId, $esSuperAdmin);

        $this->usuarioRepository->update($id, [
            'password_hash' => Hash::make($nuevaPassword),
        ]);
    }

    public function toggleActivo(int $id, int $empresaId, bool $esSuperAdmin = false): Usuario
    {
        $this->obtener($id, $empresaId, $esSuperAdmin);
        return $this->usuarioRepository->toggleActivo($id);
    }
}