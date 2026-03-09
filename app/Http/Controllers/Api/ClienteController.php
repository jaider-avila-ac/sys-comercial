<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\Autoriza;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    use Autoriza;

    /**
     * GET /api/clientes?search=&activo=1&page=
     * - EMPRESA_ADMIN/OPERATIVO: solo su empresa
     * - SUPER_ADMIN: puede filtrar por empresa_id (opcional)
     */
    public function index(Request $request)
    {
        $u = $this->user($request);

        // Roles permitidos para ver/listar clientes
        $this->requireAnyRole($u, ['SUPER_ADMIN', 'EMPRESA_ADMIN', 'OPERATIVO']);

        $search = trim((string) $request->query('search', ''));
        $activo = $request->query('activo', null); // "1" | "0" | null
        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(5, min($perPage, 50));

        // Empresa scope:
        // - si es SUPER_ADMIN puede mandar empresa_id, si no manda, ve todas (opcional)
        // - si no es SUPER_ADMIN, obligamos su empresa_id
        $empresaId = null;

        if ($u->rol === 'SUPER_ADMIN') {
            $empresaId = $request->query('empresa_id', null);
            $empresaId = $empresaId !== null ? (int)$empresaId : null;
        } else {
            $empresaId = $this->requireEmpresaId($u);
        }

        $q = Cliente::query()
            ->when($empresaId !== null, fn($qq) => $qq->where('empresa_id', $empresaId))
            ->when($activo !== null && $activo !== '', function ($qq) use ($activo) {
                $qq->where('is_activo', (int)$activo === 1 ? 1 : 0);
            })
            ->when($search !== '', function ($qq) use ($search) {
                $qq->where(function ($w) use ($search) {
                    $w->where('nombre_razon_social', 'like', "%{$search}%")
                        ->orWhere('contacto', 'like', "%{$search}%")
                        ->orWhere('num_documento', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('telefono', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($q);
    }

    /**
     * GET /api/clientes/{id}
     */
    public function show(Request $request, $id)
    {
        $u = $this->user($request);
        $this->requireAnyRole($u, ['SUPER_ADMIN', 'EMPRESA_ADMIN', 'OPERATIVO']);

        $cliente = Cliente::findOrFail($id);

        // si no es superadmin, solo puede ver clientes de su empresa
        $this->ensureSameEmpresa($u, (int)$cliente->empresa_id);

        return response()->json(['cliente' => $cliente]);
    }

    /**
     * POST /api/clientes
     * - EMPRESA_ADMIN/OPERATIVO: crea en su empresa
     * - SUPER_ADMIN: puede crear en empresa_id enviado (obligatorio)
     */
    public function store(Request $request)
    {
        $u = $this->user($request);
        $this->requireAnyRole($u, ['SUPER_ADMIN', 'EMPRESA_ADMIN', 'OPERATIVO']);

        // Definir empresa_id de creación
        $empresaId = null;

        if ($u->rol === 'SUPER_ADMIN') {
            $empresaId = (int) $request->input('empresa_id');
            if (!$empresaId) {
                return response()->json(['message' => 'empresa_id es requerido para SUPER_ADMIN'], 422);
            }
        } else {
            $empresaId = $this->requireEmpresaId($u);
        }

        $data = $request->validate([
            // si SUPER_ADMIN, lo validamos, si no, lo ignoramos abajo
            'empresa_id' => ['nullable', 'integer'],

            'nombre_razon_social' => ['required', 'string', 'max:160'],
            'contacto'            => ['nullable', 'string', 'max:120'],
            'tipo_documento'      => ['required', Rule::in(['CC', 'NIT', 'CE', 'PAS', 'OTRO'])],
            'num_documento'       => ['required', 'string', 'max:40'],
            'email'               => ['nullable', 'email', 'max:150'],
            'telefono'            => ['nullable', 'string', 'max:40'],
            'empresa'             => ['nullable', 'string', 'max:160'],
            'direccion'           => ['nullable', 'string', 'max:180'],
            'is_activo'           => ['nullable', 'boolean'],
        ]);

        $data['nombre_razon_social'] = strtoupper($data['nombre_razon_social']);
        if (isset($data['contacto']))  $data['contacto'] = strtoupper($data['contacto']);
        if (isset($data['empresa']))   $data['empresa'] = strtoupper($data['empresa']);
        if (isset($data['direccion'])) $data['direccion'] = strtoupper($data['direccion']);

        // Forzar empresa_id por seguridad
        $data['empresa_id'] = $empresaId;
        if (!array_key_exists('is_activo', $data)) $data['is_activo'] = 1;

        // Validación de unicidad por empresa (equivale al unique compuesto en DB)
        $exists = Cliente::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo_documento', $data['tipo_documento'])
            ->where('num_documento', $data['num_documento'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Ya existe un cliente con ese documento en esta empresa.'
            ], 422);
        }

        $cliente = Cliente::create($data);

        return response()->json(['cliente' => $cliente], 201);
    }

    /**
     * PUT /api/clientes/{id}
     */
    public function update(Request $request, $id)
    {
        $u = $this->user($request);
        $this->requireAnyRole($u, ['SUPER_ADMIN', 'EMPRESA_ADMIN', 'OPERATIVO']);

        $cliente = Cliente::findOrFail($id);

        // scoping
        $this->ensureSameEmpresa($u, (int)$cliente->empresa_id);

        $data = $request->validate([
            'nombre_razon_social' => ['sometimes', 'required', 'string', 'max:160'],
            'contacto'            => ['nullable', 'string', 'max:120'],
            'tipo_documento'      => ['sometimes', 'required', Rule::in(['CC', 'NIT', 'CE', 'PAS', 'OTRO'])],
            'num_documento'       => ['sometimes', 'required', 'string', 'max:40'],
            'email'               => ['nullable', 'email', 'max:150'],
            'telefono'            => ['nullable', 'string', 'max:40'],
            'empresa'             => ['nullable', 'string', 'max:160'],
            'direccion'           => ['nullable', 'string', 'max:180'],
            'is_activo'           => ['nullable', 'boolean'],
        ]);

        // PROHIBIDO cambiar empresa_id desde API
        unset($data['empresa_id']);

        // Si cambia documento, validar unicidad por empresa
        $nuevoTipo = $data['tipo_documento'] ?? $cliente->tipo_documento;
        $nuevoNum  = $data['num_documento'] ?? $cliente->num_documento;

        $exists = Cliente::query()
            ->where('empresa_id', $cliente->empresa_id)
            ->where('tipo_documento', $nuevoTipo)
            ->where('num_documento', $nuevoNum)
            ->where('id', '<>', $cliente->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Ya existe otro cliente con ese documento en esta empresa.'
            ], 422);
        }

        $cliente->fill($data)->save();

        return response()->json(['cliente' => $cliente]);
    }

    /**
     * DELETE /api/clientes/{id}
     * Recomendado: solo EMPRESA_ADMIN o SUPER_ADMIN
     */
    public function destroy(Request $request, $id)
    {
        $u = $this->user($request);

        // Solo EMPRESA_ADMIN o SUPER_ADMIN
        $this->requireAnyRole($u, ['SUPER_ADMIN', 'EMPRESA_ADMIN']);

        $cliente = Cliente::findOrFail($id);

        $this->ensureSameEmpresa($u, (int)$cliente->empresa_id);

        $cliente->delete();

        return response()->json(['ok' => true]);
    }
}
