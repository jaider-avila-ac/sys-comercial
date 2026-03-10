<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\Autoriza;
use App\Models\Proveedor;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    use Autoriza;

    private function resolveEmpresaId(Request $request): int
    {
        $u = $this->user($request);
        if ($u->rol === 'SUPER_ADMIN') {
            $id = (int)($request->query('empresa_id') ?? $request->input('empresa_id') ?? 0);
            if ($id <= 0) abort(422, 'empresa_id requerido para SUPER_ADMIN');
            return $id;
        }
        return $this->requireEmpresaId($u);
    }

    /**
     * GET /api/proveedores
     * ?search=   filtra nombre, nit, contacto
     * ?activos=0 incluye inactivos
     */
    public function index(Request $request)
    {
        $empresaId   = $this->resolveEmpresaId($request);
        $q           = trim((string)$request->query('search', ''));
        $soloActivos = $request->query('activos', '1');

        $rows = Proveedor::query()
            ->where('empresa_id', $empresaId)
            ->when($soloActivos !== '0', fn($qq) => $qq->where('is_activo', 1))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('nombre',   'like', "%{$q}%")
                      ->orWhere('nit',     'like', "%{$q}%")
                      ->orWhere('contacto','like', "%{$q}%")
                      ->orWhere('email',   'like', "%{$q}%");
                });
            })
            ->orderBy('nombre')
            ->paginate(20);

        return response()->json($rows);
    }

    /**
     * GET /api/proveedores/{id}
     * Detalle + items habituales + resumen de compras
     * Beneficios: 1, 2, 6, 7
     */
    public function show(Request $request, int $id)
    {
        $empresaId = $this->resolveEmpresaId($request);
        $proveedor = Proveedor::where('empresa_id', $empresaId)->findOrFail($id);

        // Beneficio 7: items que usan este proveedor como habitual
        $items = $proveedor->items()
            ->where('is_activo', 1)
            ->select(['id','nombre','tipo','unidad','precio_compra','controla_inventario'])
            ->orderBy('nombre')
            ->get();

        // Beneficio 4: resumen de compras a este proveedor
        $resumenCompras = $proveedor->compras()
            ->where('empresa_id', $empresaId)
            ->where('estado', 'CONFIRMADA')
            ->selectRaw('COUNT(*) as total_compras, SUM(total) as monto_total, SUM(saldo_pendiente) as deuda_total')
            ->first();

        return response()->json([
            'proveedor'      => $proveedor,
            'items'          => $items,
            'resumen_compras'=> $resumenCompras,
        ]);
    }

    /**
     * POST /api/proveedores
     */
    public function store(Request $request)
    {
        $u = $this->user($request);
        $this->requireAnyRole($u, ['SUPER_ADMIN', 'EMPRESA_ADMIN']);
        $empresaId = $this->resolveEmpresaId($request);

        $data = $request->validate([
            'nombre'               => ['required', 'string', 'max:150'],
            'nit'                  => ['nullable', 'string', 'max:30'],
            'telefono'             => ['nullable', 'string', 'max:30'],
            'email'                => ['nullable', 'email',  'max:100'],
            'contacto'             => ['nullable', 'string', 'max:100'],
            'direccion'            => ['nullable', 'string', 'max:200'],
            'ciudad'               => ['nullable', 'string', 'max:80'],
            'tiempo_entrega_dias'  => ['nullable', 'integer', 'min:0'],
            'notas'                => ['nullable', 'string'],
        ]);

        $proveedor = Proveedor::create(array_merge($data, [
            'empresa_id' => $empresaId,
            'is_activo'  => true,
        ]));

        return response()->json($proveedor, 201);
    }

    /**
     * PUT /api/proveedores/{id}
     */
    public function update(Request $request, int $id)
    {
        $u = $this->user($request);
        $this->requireAnyRole($u, ['SUPER_ADMIN', 'EMPRESA_ADMIN']);
        $empresaId = $this->resolveEmpresaId($request);
        $proveedor = Proveedor::where('empresa_id', $empresaId)->findOrFail($id);

        $data = $request->validate([
            'nombre'               => ['sometimes', 'string', 'max:150'],
            'nit'                  => ['nullable', 'string', 'max:30'],
            'telefono'             => ['nullable', 'string', 'max:30'],
            'email'                => ['nullable', 'email',  'max:100'],
            'contacto'             => ['nullable', 'string', 'max:100'],
            'direccion'            => ['nullable', 'string', 'max:200'],
            'ciudad'               => ['nullable', 'string', 'max:80'],
            'tiempo_entrega_dias'  => ['nullable', 'integer', 'min:0'],
            'notas'                => ['nullable', 'string'],
            'is_activo'            => ['sometimes', 'boolean'],
        ]);

        $proveedor->update($data);
        return response()->json($proveedor);
    }

    /**
     * DELETE /api/proveedores/{id}
     * Soft-delete: desactiva y desvincula items
     */
    public function destroy(Request $request, int $id)
    {
        $u = $this->user($request);
        $this->requireAnyRole($u, ['SUPER_ADMIN', 'EMPRESA_ADMIN']);
        $empresaId = $this->resolveEmpresaId($request);
        $proveedor = Proveedor::where('empresa_id', $empresaId)->findOrFail($id);

        // Desvincular items habituales
        $proveedor->items()->update(['proveedor_id' => null]);

        $proveedor->update(['is_activo' => false]);
        return response()->json(['ok' => true]);
    }
}