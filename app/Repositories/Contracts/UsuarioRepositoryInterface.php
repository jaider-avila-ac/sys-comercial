<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

use App\Models\Usuario;

interface UsuarioRepositoryInterface
{
    public function findByEmail(string $email): ?Usuario;

    public function findById(int $id): ?Usuario;

    public function actualizarUltimoLogin(int $id): void;

    // ── Gestión de usuarios ───────────────────────────────────────────────────
    public function allByEmpresa(int $empresaId): Collection;

    public function create(array $data): Usuario;

    public function update(int $id, array $data): Usuario;

    public function toggleActivo(int $id): Usuario;
}
