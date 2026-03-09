<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\Autoriza;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmpresaController extends Controller
{
    use Autoriza;

    /** GET /api/empresa/me — EMPRESA_ADMIN */
    public function me(Request $request)
    {
        $u = $this->user($request);
        $this->requireRole($u, 'EMPRESA_ADMIN');
        $empresa = Empresa::findOrFail($this->requireEmpresaId($u));
        return response()->json(['empresa' => $empresa]);
    }

    /** GET /api/empresas — SUPER_ADMIN */
    public function index(Request $request)
    {
        $u = $this->user($request);
        $this->requireRole($u, 'SUPER_ADMIN');

        $q = trim((string) $request->query('search', ''));

        $empresas = Empresa::query()
            ->when($q !== '', fn($query) =>
                $query->where('nombre', 'like', "%{$q}%")
                      ->orWhere('nit', 'like', "%{$q}%")
            )
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json($empresas);
    }

    /** POST /api/empresas — SUPER_ADMIN */
    public function store(Request $request)
    {
        $u = $this->user($request);
        $this->requireRole($u, 'SUPER_ADMIN');

        $data = $request->validate([
            'nombre'    => ['required', 'string', 'max:150'],
            'nit'       => ['nullable', 'string', 'max:30', 'unique:empresas,nit'],
            'email'     => ['nullable', 'email', 'max:120'],
            'telefono'  => ['nullable', 'string', 'max:40'],
            'direccion' => ['nullable', 'string', 'max:180'],
            'is_activa' => ['nullable', 'boolean'],
        ]);

        $empresa = Empresa::create($data);
        return response()->json(['empresa' => $empresa], 201);
    }

    /** GET /api/empresas/{id} */
    public function show(Request $request, $id)
    {
        $u = $this->user($request);
        $this->requireAnyRole($u, ['SUPER_ADMIN', 'EMPRESA_ADMIN']);
        $empresa = Empresa::findOrFail($id);
        $this->ensureSameEmpresa($u, (int) $empresa->id);
        return response()->json(['empresa' => $empresa]);
    }

    /** PUT /api/empresas/{id} */
    public function update(Request $request, $id)
    {
        $u = $this->user($request);
        $this->requireAnyRole($u, ['SUPER_ADMIN', 'EMPRESA_ADMIN']);
        $empresa = Empresa::findOrFail($id);
        $this->ensureSameEmpresa($u, (int) $empresa->id);

        $data = $request->validate([
            'nombre'    => ['sometimes', 'required', 'string', 'max:150'],
            'nit'       => ['nullable', 'string', 'max:30', "unique:empresas,nit,{$empresa->id}"],
            'email'     => ['nullable', 'email', 'max:120'],
            'telefono'  => ['nullable', 'string', 'max:40'],
            'direccion' => ['nullable', 'string', 'max:180'],
            'is_activa' => ['nullable', 'boolean'],
        ]);

        // EMPRESA_ADMIN no puede cambiar is_activa
        if ($u->rol === 'EMPRESA_ADMIN') {
            unset($data['is_activa']);
        }

        $empresa->fill($data)->save();
        return response()->json(['empresa' => $empresa]);
    }

    /** DELETE /api/empresas/{id} — SUPER_ADMIN */
    public function destroy(Request $request, $id)
    {
        $u = $this->user($request);
        $this->requireRole($u, 'SUPER_ADMIN');
        $empresa = Empresa::findOrFail($id);

        // Eliminar logo si existe
        if ($empresa->logo_path) {
            Storage::disk('public')->delete($empresa->logo_path);
        }

        $empresa->delete();
        return response()->json(['ok' => true]);
    }

    // =========================================================================
    // LOGO
    // =========================================================================

    /**
     * POST /api/empresas/{id}/logo
     * Sube o reemplaza el logo de la empresa.
     * Campo: logo (file, png/jpg/webp, max 2 MB)
     */
    public function uploadLogo(Request $request, $id)
    {
        $u = $this->user($request);
        $this->requireAnyRole($u, ['SUPER_ADMIN', 'EMPRESA_ADMIN']);
        $empresa = Empresa::findOrFail($id);
        $this->ensureSameEmpresa($u, (int) $empresa->id);

        $request->validate([
            'logo' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        // Borrar logo anterior
        if ($empresa->logo_path) {
            Storage::disk('public')->delete($empresa->logo_path);
        }

        $file = $request->file('logo');
        $ext  = $file->getClientOriginalExtension();
        $path = $file->storeAs(
            "logos",
            "empresa_{$empresa->id}_" . Str::random(8) . ".{$ext}",
            'public'
        );

        $empresa->logo_path       = $path;
        $empresa->logo_mime       = $file->getMimeType();
        $empresa->logo_updated_at = now();
        $empresa->save();

        return response()->json(['empresa' => $empresa]);
    }

    /**
     * DELETE /api/empresas/{id}/logo
     * Elimina el logo de la empresa.
     */
    public function deleteLogo(Request $request, $id)
    {
        $u = $this->user($request);
        $this->requireAnyRole($u, ['SUPER_ADMIN', 'EMPRESA_ADMIN']);
        $empresa = Empresa::findOrFail($id);
        $this->ensureSameEmpresa($u, (int) $empresa->id);

        if ($empresa->logo_path) {
            Storage::disk('public')->delete($empresa->logo_path);
        }

        $empresa->logo_path       = null;
        $empresa->logo_mime       = null;
        $empresa->logo_updated_at = null;
        $empresa->save();

        return response()->json(['empresa' => $empresa]);
    }
}