<?php

namespace App\Services;

use App\Models\Usuario;
use App\Repositories\UsuarioRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Carbon; 
use Illuminate\Support\Collection;

class UsuarioService
{
    public function __construct(
        private readonly UsuarioRepository $usuarioRepository,
    ) {}

    public function listarPorEmpresa(int $empresaId, array $filters = [], int $perPage = 20, bool $esSuperAdmin = false): LengthAwarePaginator
    {
        return $this->usuarioRepository->paginate($empresaId, $filters, $perPage, $esSuperAdmin);
    }

    public function obtener(int $id, int $empresaId, bool $esSuperAdmin = false): Usuario
    {
        $usuario = $this->usuarioRepository->findById($id);

        if (! $usuario) {
            throw new HttpException(404, 'Usuario no encontrado.');
        }

        if (! $esSuperAdmin && $usuario->empresa_id !== $empresaId) {
            throw new HttpException(403, 'No tienes acceso a este usuario.');
        }

        return $usuario;
    }

    public function crear(array $data, int $empresaId): Usuario
    {
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
        $this->obtener($id, $empresaId, $esSuperAdmin);

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

    public function usuariosActivosAhora(int $minutos, int $empresaId): Collection
{
    $limite = Carbon::now('America/Bogota')->subMinutes($minutos);
    
    return Usuario::where('empresa_id', $empresaId)
        ->where('last_login_at', '>=', $limite)
        ->orderBy('last_login_at', 'desc')
        ->get();
}
}