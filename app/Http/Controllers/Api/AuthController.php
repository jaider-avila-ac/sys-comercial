<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\SesionLog;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt([
            'email'     => $data['email'],
            'password'  => $data['password'],
            'is_activo' => 1,
        ])) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        // Actualizar último acceso
        DB::table('usuarios')->where('id', $user->id)->update(['last_login_at' => now()]);

        // Registrar sesión en historial
        SesionLog::create([
            'empresa_id'  => $user->empresa_id,
            'usuario_id'  => $user->id,
            'ip'          => $request->ip(),
            'user_agent'  => mb_substr($request->userAgent() ?? '', 0, 300),
            'iniciado_en' => now(),
        ]);

        return response()->json([
            'user' => [
                'id'         => $user->id,
                'empresa_id' => $user->empresa_id,
                'nombres'    => $user->nombres,
                'apellidos'  => $user->apellidos,
                'email'      => $user->email,
                'rol'        => $user->rol,
            ]
        ]);
    }

    public function me(Request $request)
    {
        $u = $request->user();

        return response()->json([
            'id'         => $u->id,
            'empresa_id' => $u->empresa_id,
            'nombres'    => $u->nombres,
            'apellidos'  => $u->apellidos,
            'email'      => $u->email,
            'rol'        => $u->rol,
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }
}