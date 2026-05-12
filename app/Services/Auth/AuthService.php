<?php

namespace App\Services\Auth;

use App\Models\Usuario;
use App\Repositories\UsuarioRepository;
use App\Services\AuditoriaService;
use App\Services\SesionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthService
{
    private const ALIVE_TTL              = 300;   // segundos — inactividad de sesión activa
    private const USER_KEY_TTL           = 86400; // segundos — 24 horas
    private const MAX_PASSWORD_ATTEMPTS  = 5;
    private const ATTEMPTS_TTL           = 15;    // minutos — ventana de bloqueo

    // Hash falso para mantener tiempo de respuesta constante (anti-timing attack)
    private const DUMMY_HASH = '$2y$12$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ012345';

    public function __construct(
        private readonly UsuarioRepository $usuarioRepository,
        private readonly AuditoriaService  $auditoriaService,
        private readonly SesionService     $sesionService,
    ) {}

    // ── Login (correo + contraseña en una sola llamada) ───────────────────────

    public function login(string $email, string $password): array
    {
        $usuario = $this->usuarioRepository->findByEmail($email);

        // Correo no encontrado o inactivo — misma respuesta y tiempo que contraseña incorrecta
        if (!$usuario || !$usuario->esActivo()) {
            Hash::check($password, self::DUMMY_HASH);
            throw new HttpException(401, 'Credenciales inválidas.');
        }

        // Verificar bloqueo por intentos fallidos (por usuario)
        $attemptsKey = "auth_attempts:user:{$usuario->id}";
        $attempts    = (int) Cache::get($attemptsKey, 0);

        if ($attempts >= self::MAX_PASSWORD_ATTEMPTS) {
            throw new HttpException(429, 'Demasiados intentos fallidos. Espera unos minutos e intenta de nuevo.');
        }

        if (!Hash::check($password, $usuario->password_hash)) {
            $newAttempts = $attempts + 1;
            Cache::put($attemptsKey, $newAttempts, now()->addMinutes(self::ATTEMPTS_TTL));

            $this->sesionService->registrarLoginFallido(
                $usuario->empresa_id,
                $usuario->id,
                request()->ip() ?? '',
                request()->userAgent() ?? '',
            );

            if ($newAttempts >= self::MAX_PASSWORD_ATTEMPTS) {
                throw new HttpException(429, 'Demasiados intentos fallidos. Espera unos minutos e intenta de nuevo.');
            }

            throw new HttpException(401, 'Credenciales inválidas.');
        }

        // Contraseña correcta — limpiar contador de intentos
        Cache::forget($attemptsKey);

        // ── Sesión única: verificar si ya hay una sesión activa ───────────────
        $userKey         = "session:user:{$usuario->id}";
        $existingTokenId = Cache::get($userKey);

        if ($existingTokenId !== null) {
            $aliveKey = "session:alive:{$existingTokenId}";

            if (Cache::has($aliveKey)) {
                throw new HttpException(409, 'Ya hay una sesión activa para este usuario.');
            }

            // Sesión caducada por inactividad — limpiar
            $usuario->tokens()->where('id', $existingTokenId)->delete();
            Cache::forget($userKey);
            Cache::forget($aliveKey);
        }

        // ── Crear nuevo token Sanctum ─────────────────────────────────────────
        $newToken    = $usuario->createToken(
            name:      'access',
            abilities: $this->resolverAbilities($usuario),
        );
        $accessToken = $newToken->plainTextToken;
        $tokenId     = $newToken->accessToken->id;

        Cache::put($userKey, $tokenId, self::USER_KEY_TTL);
        Cache::put("session:alive:{$tokenId}", true, self::ALIVE_TTL);

        // ── Registro y auditoría ──────────────────────────────────────────────
        $usuario->last_login_at = now();
        $usuario->save();

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

    // ── Logout ────────────────────────────────────────────────────────────────

    public function cerrarSesion(Usuario $usuario): void
    {
        /** @var PersonalAccessToken|null $token */
        $token   = $usuario->currentAccessToken();
        $tokenId = $token?->id;

        if ($tokenId) {
            Cache::forget("session:alive:{$tokenId}");
        }
        Cache::forget("session:user:{$usuario->id}");

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

        $token?->delete();
    }

    // ── Revocar todas las sesiones ────────────────────────────────────────────

    public function revocarTodasLasSesiones(Usuario $usuario): void
    {
        $tokenIds = $usuario->tokens()->pluck('id');
        foreach ($tokenIds as $id) {
            Cache::forget("session:alive:{$id}");
        }
        Cache::forget("session:user:{$usuario->id}");

        $usuario->tokens()->delete();

        $this->auditoriaService->registrar(
            empresaId:   $usuario->empresa_id,
            usuarioId:   $usuario->id,
            entidad:     'usuarios',
            accion:      'REVOKE_ALL_SESSIONS',
            entidadId:   $usuario->id,
            descripcion: "Se revocaron todas las sesiones del usuario: {$usuario->email}",
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function verificarTokenValido(Usuario $usuario): bool
    {
        return $usuario->is_activo
            && $usuario->tokens()->where('id', $usuario->currentAccessToken()?->id)->exists();
    }

    public function getCurrentToken(Usuario $usuario): ?PersonalAccessToken
    {
        return $usuario->currentAccessToken();
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
