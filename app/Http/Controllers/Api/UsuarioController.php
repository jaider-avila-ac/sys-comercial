<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\Autoriza;
use App\Http\Controllers\Api\Concerns\AuditLog;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * UsuarioController
 *
 * Permisos por rol:
 *   SUPER_ADMIN   → gestiona usuarios de CUALQUIER empresa + puede crear EMPRESA_ADMIN
 *   EMPRESA_ADMIN → gestiona solo usuarios de SU empresa  (no puede tocar SUPER_ADMIN)
 *   OPERATIVO     → sin acceso (403)
 */
class UsuarioController extends Controller
{
    use Autoriza, AuditLog;

    // ────────────────────────────────────────────────────────────────────────
    // GET /api/usuarios
    // SUPER_ADMIN: todos (filtrable por empresa_id)
    // EMPRESA_ADMIN: solo su empresa
    // ────────────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $actor = $this->user($request);
        $this->requireAnyRole($actor, ['SUPER_ADMIN', 'EMPRESA_ADMIN']);

        $search     = trim((string) $request->query('search', ''));
        $soloActivo = $request->query('activo'); // '1', '0' o null (todos)
        $rolFiltro  = trim((string) $request->query('rol', ''));

        $q = User::query()->with('empresa:id,nombre');

        // ── Scope por empresa ──────────────────────────────────────────────
        if ($actor->rol === 'EMPRESA_ADMIN') {
            // Solo ve usuarios de su empresa y nunca SUPER_ADMIN
            $q->where('empresa_id', $actor->empresa_id)
              ->where('rol', '!=', 'SUPER_ADMIN');
        } else {
            // SUPER_ADMIN puede filtrar por empresa_id opcionalmente
            $eid = (int) $request->query('empresa_id', 0);
            if ($eid > 0) $q->where('empresa_id', $eid);
        }

        // ── Filtros ────────────────────────────────────────────────────────
        if ($search !== '') {
            $q->where(fn($sub) =>
                $sub->where('nombres',   'like', "%{$search}%")
                    ->orWhere('apellidos','like', "%{$search}%")
                    ->orWhere('email',    'like', "%{$search}%")
            );
        }
        if ($soloActivo !== null && $soloActivo !== '') {
            $q->where('is_activo', (bool)(int)$soloActivo);
        }
        if ($rolFiltro !== '') {
            $q->where('rol', $rolFiltro);
        }

        $q->orderByDesc('id');

        // Selección de columnas (sin password_hash)
        $q->select([
            'id','empresa_id','nombres','apellidos','email',
            'rol','is_activo','last_login_at','created_at','updated_at',
        ]);

        return response()->json($q->paginate(20));
    }

    // GET /api/usuarios/{id}/sesiones
public function sesiones(Request $request, int $id)
{
    $actor   = $this->user($request);
    $usuario = $this->findAndAuthorize($actor, $id);

    $desde = $request->query('desde');
    $hasta = $request->query('hasta');

    $q = \App\Models\SesionLog::where('usuario_id', $usuario->id)
        ->when($desde, fn($q) => $q->where('iniciado_en', '>=', $desde . ' 00:00:00'))
        ->when($hasta, fn($q) => $q->where('iniciado_en', '<=', $hasta . ' 23:59:59'))
        ->orderByDesc('iniciado_en');

    return response()->json($q->paginate(50));
}

    // ────────────────────────────────────────────────────────────────────────
    // POST /api/usuarios
    // ────────────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $actor = $this->user($request);
        $this->requireAnyRole($actor, ['SUPER_ADMIN', 'EMPRESA_ADMIN']);

        $rolesPermitidos = $actor->rol === 'SUPER_ADMIN'
            ? ['SUPER_ADMIN', 'EMPRESA_ADMIN', 'OPERATIVO']
            : ['EMPRESA_ADMIN', 'OPERATIVO'];

        $data = $request->validate([
            'nombres'    => ['required', 'string', 'max:80'],
            'apellidos'  => ['nullable', 'string', 'max:80'],
            'email'      => ['required', 'email', 'max:120', 'unique:usuarios,email'],
            'password'   => ['required', 'string', Password::min(8)->letters()->numbers()],
            'rol'        => ['required', Rule::in($rolesPermitidos)],
            'empresa_id' => ['nullable', 'integer', 'exists:empresas,id'],
            'is_activo'  => ['nullable', 'boolean'],
        ]);

        // EMPRESA_ADMIN solo puede crear dentro de su empresa
        if ($actor->rol === 'EMPRESA_ADMIN') {
            $data['empresa_id'] = $actor->empresa_id;
        } elseif (empty($data['empresa_id']) && $data['rol'] !== 'SUPER_ADMIN') {
            return response()->json(['message' => 'empresa_id es obligatorio para este rol.'], 422);
        }

        $usuario = User::create([
            'empresa_id'    => $data['empresa_id'] ?? null,
            'nombres'       => $data['nombres'],
            'apellidos'     => $data['apellidos'] ?? null,
            'email'         => $data['email'],
            'password_hash' => Hash::make($data['password']),
            'rol'           => $data['rol'],
            'is_activo'     => $data['is_activo'] ?? true,
        ]);

        $this->audit(
            $request, 'usuarios', 'CREAR', $usuario->id,
            "Creó el usuario {$usuario->email} (rol: {$usuario->rol})",
            $actor->empresa_id
        );

        return response()->json(['usuario' => $this->safeUser($usuario)], 201);
    }

    // ────────────────────────────────────────────────────────────────────────
    // GET /api/usuarios/{id}
    // ────────────────────────────────────────────────────────────────────────
    public function show(Request $request, int $id)
    {
        $actor   = $this->user($request);
        $usuario = $this->findAndAuthorize($actor, $id);

        return response()->json(['usuario' => $this->safeUser($usuario->load('empresa:id,nombre'))]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // PUT /api/usuarios/{id}
    // Actualiza datos generales (no contraseña)
    // ────────────────────────────────────────────────────────────────────────
    public function update(Request $request, int $id)
    {
        $actor   = $this->user($request);
        $usuario = $this->findAndAuthorize($actor, $id);

        $rolesPermitidos = $actor->rol === 'SUPER_ADMIN'
            ? ['SUPER_ADMIN', 'EMPRESA_ADMIN', 'OPERATIVO']
            : ['EMPRESA_ADMIN', 'OPERATIVO'];

        $data = $request->validate([
            'nombres'   => ['sometimes', 'required', 'string', 'max:80'],
            'apellidos' => ['nullable', 'string', 'max:80'],
            'email'     => ['sometimes', 'required', 'email', 'max:120',
                             Rule::unique('usuarios', 'email')->ignore($usuario->id)],
            'rol'       => ['sometimes', 'required', Rule::in($rolesPermitidos)],
            'is_activo' => ['sometimes', 'boolean'],
            'empresa_id'=> ['sometimes', 'nullable', 'integer', 'exists:empresas,id'],
        ]);

        // EMPRESA_ADMIN no puede mover usuarios a otra empresa
        if ($actor->rol === 'EMPRESA_ADMIN') {
            unset($data['empresa_id']);
        }

        $usuario->fill($data)->save();

        $this->audit(
            $request, 'usuarios', 'EDITAR', $usuario->id,
            "Editó el usuario {$usuario->email}",
            $actor->empresa_id
        );

        return response()->json(['usuario' => $this->safeUser($usuario)]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // PATCH /api/usuarios/{id}/toggle
    // Habilitar / deshabilitar usuario
    // ────────────────────────────────────────────────────────────────────────
    public function toggle(Request $request, int $id)
    {
        $actor   = $this->user($request);
        $usuario = $this->findAndAuthorize($actor, $id);

        // No puede desactivarse a sí mismo
        if ($usuario->id === $actor->id) {
            return response()->json(['message' => 'No puedes desactivar tu propio usuario.'], 422);
        }

        $usuario->is_activo = !$usuario->is_activo;
        $usuario->save();

        $estado = $usuario->is_activo ? 'habilitó' : 'deshabilitó';
        $this->audit(
            $request, 'usuarios', 'TOGGLE', $usuario->id,
            "Se {$estado} el usuario {$usuario->email}",
            $actor->empresa_id
        );

        return response()->json(['usuario' => $this->safeUser($usuario)]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // PATCH /api/usuarios/{id}/password
    // Cambiar contraseña de otro usuario (no requiere la actual)
    // ────────────────────────────────────────────────────────────────────────
    public function changePassword(Request $request, int $id)
    {
        $actor   = $this->user($request);
        $usuario = $this->findAndAuthorize($actor, $id);

        $data = $request->validate([
            'password' => ['required', 'string', Password::min(8)->letters()->numbers(), 'confirmed'],
        ]);

        $usuario->password_hash = Hash::make($data['password']);
        $usuario->save();

        $this->audit(
            $request, 'usuarios', 'CAMBIO_CLAVE', $usuario->id,
            "Cambió la contraseña del usuario {$usuario->email}",
            $actor->empresa_id
        );

        return response()->json(['ok' => true]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // GET /api/usuarios/activos-ahora
    // Usuarios con last_login_at en los últimos N minutos (default 30)
    // ────────────────────────────────────────────────────────────────────────
    public function activosAhora(Request $request)
    {
        $actor = $this->user($request);
        $this->requireAnyRole($actor, ['SUPER_ADMIN', 'EMPRESA_ADMIN']);

        $minutos = (int) $request->query('minutos', 30);
        $minutos = max(5, min($minutos, 1440)); // entre 5 min y 24 h

        $q = User::query()
            ->where('last_login_at', '>=', now()->subMinutes($minutos))
            ->where('is_activo', true)
            ->select(['id','empresa_id','nombres','apellidos','email','rol','last_login_at']);

        if ($actor->rol === 'EMPRESA_ADMIN') {
            $q->where('empresa_id', $actor->empresa_id)
              ->where('rol', '!=', 'SUPER_ADMIN');
        }

        return response()->json([
            'minutos' => $minutos,
            'data'    => $q->orderByDesc('last_login_at')->get(),
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // GET /api/usuarios/{id}/auditoria
    // Historial de acciones del usuario o sobre el usuario
    // ────────────────────────────────────────────────────────────────────────
    public function auditoria(Request $request, int $id)
    {
        $actor   = $this->user($request);
        $usuario = $this->findAndAuthorize($actor, $id);

        $tipo = $request->query('tipo', 'sobre'); // 'sobre' | 'por'

        $q = \App\Models\Auditoria::query()
            ->with('usuario:id,nombres,apellidos,email');

        if ($tipo === 'por') {
            // Acciones que hizo este usuario
            $q->where('usuario_id', $usuario->id);
        } else {
            // Acciones sobre este usuario
            $q->where('entidad', 'usuarios')->where('entidad_id', $usuario->id);
        }

        $q->orderByDesc('ocurrido_en');

        return response()->json($q->paginate(30));
    }

    // ────────────────────────────────────────────────────────────────────────
    // Helpers privados
    // ────────────────────────────────────────────────────────────────────────

    /** Busca el usuario y verifica que el actor tiene permiso sobre él */
    private function findAndAuthorize($actor, int $id): User
    {
        $usuario = User::findOrFail($id);

        if ($actor->rol === 'EMPRESA_ADMIN') {
            // Solo usuarios de su empresa, nunca SUPER_ADMIN
            if ((int) $usuario->empresa_id !== (int) $actor->empresa_id
                || $usuario->rol === 'SUPER_ADMIN') {
                abort(403, 'Sin permiso sobre este usuario.');
            }
        } elseif ($actor->rol !== 'SUPER_ADMIN') {
            abort(403, 'Sin permiso.');
        }

        return $usuario;
    }

    /** Retorna el usuario sin password_hash */
    private function safeUser(User $u): array
    {
        return [
            'id'            => $u->id,
            'empresa_id'    => $u->empresa_id,
            'empresa'       => $u->relationLoaded('empresa') ? $u->empresa : null,
            'nombres'       => $u->nombres,
            'apellidos'     => $u->apellidos,
            'email'         => $u->email,
            'rol'           => $u->rol,
            'is_activo'     => $u->is_activo,
            'last_login_at' => $u->last_login_at?->toIso8601String(),
            'created_at'    => $u->created_at?->toIso8601String(),
            'updated_at'    => $u->updated_at?->toIso8601String(),
        ];
    }
}
