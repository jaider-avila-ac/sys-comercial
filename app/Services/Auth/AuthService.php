<?php

namespace App\Services\Auth;

use App\Models\Usuario;
use App\Repositories\UsuarioRepository;
use App\Services\AuditoriaService;
use App\Services\SesionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthService
{
    private const TOKEN_TTL_MINUTES = 5;
    private const CACHE_PREFIX      = 'auth_pretoken:';

    public function __construct(
        private readonly UsuarioRepository $usuarioRepository,
        private readonly AuditoriaService           $auditoriaService,
        private readonly SesionService              $sesionService,
    ) {}

    public function iniciarSesion(string $email): string
    {
        $usuario = $this->usuarioRepository->findByEmail($email);

        if (! $usuario) {
            throw new HttpException(401, 'Credenciales inválidas.');
        }

        if (! $usuario->esActivo()) {
            throw new HttpException(403, 'Usuario inactivo. Contacte al administrador.');
        }

        $preToken = strtoupper(Str::random(6));

        Cache::put(
            self::CACHE_PREFIX . $email,
            $preToken,
            now()->addMinutes(self::TOKEN_TTL_MINUTES)
        );

        return $preToken;
    }

    public function verificarSesion(string $email, string $preToken, string $password): array
    {
        $cacheKey      = self::CACHE_PREFIX . $email;
        $tokenGuardado = Cache::get($cacheKey);

        if (! $tokenGuardado || strtoupper($preToken) !== $tokenGuardado) {
            throw new HttpException(401, 'Token inválido o expirado.');
        }

        $usuario = $this->usuarioRepository->findByEmail($email);

        if (! $usuario) {
            throw new HttpException(401, 'Credenciales inválidas.');
        }

        if (! Hash::check($password, $usuario->password_hash)) {
            $this->sesionService->registrarLoginFallido(
                $usuario->empresa_id,
                $usuario->id,
                request()->ip() ?? '',
                request()->userAgent() ?? '',
            );
            throw new HttpException(401, 'Credenciales inválidas.');
        }

        Cache::forget($cacheKey);

        // ✅ Actualizar last_login_at
        $usuario->last_login_at = now();
        $usuario->save();

        // ✅ Registrar sesión de login
        $this->sesionService->registrarLogin(
            $usuario,
            request()->ip() ?? '',
            request()->userAgent() ?? '',
        );

        $this->auditoriaService->registrar(
            empresaId:   $usuario->empresa_id,
            usuarioId:   $usuario->id,
            entidad:     'usuarios',
            accion:      'LOGIN',
            entidadId:   $usuario->id,
            descripcion: "Login exitoso: {$usuario->email}",
        );

        $accessToken = $usuario->createToken(
            name:      'access',
            abilities: $this->resolverAbilities($usuario),
        )->plainTextToken;

        return [
            'access_token' => $accessToken,
            'token_type'   => 'Bearer',
            'usuario'      => [
                'id'              => $usuario->id,
                'nombre_completo' => $usuario->nombre_completo,
                'email'           => $usuario->email,
                'rol'             => $usuario->rol,
                'empresa_id'      => $usuario->empresa_id,
            ],
        ];
    }

    public function cerrarSesion(Usuario $usuario): void
    {
        $this->sesionService->registrarLogout(
            $usuario,
            request()->ip() ?? '',
            request()->userAgent() ?? '',
        );

        $this->auditoriaService->registrar(
            empresaId:   $usuario->empresa_id,
            usuarioId:   $usuario->id,
            entidad:     'usuarios',
            accion:      'LOGOUT',
            entidadId:   $usuario->id,
            descripcion: "Logout: {$usuario->email}",
        );

        /** @var PersonalAccessToken $token */
        $token = $usuario->currentAccessToken();
        $token->delete();
    }

    private function resolverAbilities(Usuario $usuario): array
    {
        return match ($usuario->rol) {
            'SUPER_ADMIN'   => ['*'],
            'EMPRESA_ADMIN' => ['empresa:*'],
            'OPERATIVO'     => ['empresa:read', 'empresa:operate'],
            default         => [],
        };
    }
}