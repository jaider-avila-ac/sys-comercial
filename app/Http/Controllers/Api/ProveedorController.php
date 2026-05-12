<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proveedor;
use App\Services\ProveedorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProveedorController extends Controller
{
    public function __construct(
        private readonly ProveedorService $proveedorService,
    ) {}

    // GET /api/proveedores
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->proveedorService->listar(
                $request->empresa_id_ctx,
                $request->only(['search', 'activos'])
            )
        );
    }

    // GET /api/proveedores/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        $proveedor = $this->proveedorService->obtener($id, $request->empresa_id_ctx);

        $items = $proveedor->items()->get();

        // ✅ Verificar que la relación existe antes de usarla
        $resumen = [
            'total_compras' => 0,
            'monto_total'   => 0,
            'deuda_total'   => 0,
        ];

        if (method_exists($proveedor, 'compras')) {
            $compras = $proveedor->compras()->where('empresa_id', $request->empresa_id_ctx);
            $resumen = [
                'total_compras' => $compras->count(),
                'monto_total'   => $compras->sum('total'),
                'deuda_total'   => $compras->sum('saldo_pendiente'),
            ];
        }

        return response()->json([
            'proveedor'       => $proveedor,
            'items'           => $items,
            'resumen_compras' => $resumen,
        ]);
    }
    // POST /api/proveedores
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre'              => ['required', 'string', 'max:150'],
            'nit'                 => ['nullable', 'string', 'max:30'],
            'telefono'            => ['nullable', 'string', 'max:30'],
            'email'               => ['nullable', 'email', 'max:100'],
            'contacto'            => ['nullable', 'string', 'max:100'],
            'direccion'           => ['nullable', 'string', 'max:200'],
            'ciudad'              => ['nullable', 'string', 'max:80'],
            'tiempo_entrega_dias' => ['nullable', 'integer', 'min:0'],
            'notas'               => ['nullable', 'string'],
        ]);

        return response()->json(
            $this->proveedorService->crear($data, $request->empresa_id_ctx),
            201
        );
    }

    // PUT /api/proveedores/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'nombre'              => ['sometimes', 'string', 'max:150'],
            'nit'                 => ['sometimes', 'nullable', 'string', 'max:30'],
            'telefono'            => ['sometimes', 'nullable', 'string', 'max:30'],
            'email'               => ['sometimes', 'nullable', 'email', 'max:100'],
            'contacto'            => ['sometimes', 'nullable', 'string', 'max:100'],
            'direccion'           => ['sometimes', 'nullable', 'string', 'max:200'],
            'ciudad'              => ['sometimes', 'nullable', 'string', 'max:80'],
            'tiempo_entrega_dias' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'notas'               => ['sometimes', 'nullable', 'string'],
            'is_activo'           => ['sometimes', 'boolean'],
        ]);

        return response()->json(
            $this->proveedorService->actualizar($id, $data, $request->empresa_id_ctx)
        );
    }

    // PATCH /api/proveedores/{id}/toggle
    public function toggle(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->proveedorService->toggleActivo($id, $request->empresa_id_ctx)
        );
    }

    // DELETE /api/proveedores/{id}
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->proveedorService->eliminar($id, $request->empresa_id_ctx);
        return response()->json(['message' => 'Proveedor eliminado correctamente.']);
    }

    // POST /api/proveedores/importar
    public function importar(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120']]);

        $path = $request->file('file')->store('temp_imports');

        try {
            $spreadsheet = IOFactory::load(Storage::path($path));
            $rawRows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

            if (count($rawRows) < 2) {
                return response()->json(['message' => 'El archivo no tiene datos.'], 422);
            }

            $header = array_map(fn($h) => strtolower(trim((string)$h)), $rawRows[0]);
            $cols   = ['nombre', 'nit', 'contacto', 'email', 'telefono', 'direccion'];
            $idx    = [];
            foreach ($cols as $c) {
                $pos = array_search($c, $header);
                $idx[$c] = $pos !== false ? $pos : null;
            }

            $empresaId   = $request->empresa_id_ctx;
            $errores     = [];
            $registros   = [];
            $emailsArchivo = [];
            $nitsArchivo   = [];

            foreach (array_slice($rawRows, 1) as $i => $row) {
                $fila = $i + 2;
                $val  = fn($c) => isset($idx[$c]) && $idx[$c] !== null ? trim((string)($row[$idx[$c]] ?? '')) : '';

                $nombre = $val('nombre');
                $email  = strtolower($val('email'));
                $nit    = $val('nit');

                if (!$nombre) {
                    $errores[] = ['fila' => $fila, 'campo' => 'nombre', 'mensaje' => 'El nombre es obligatorio'];
                    continue;
                }
                if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errores[] = ['fila' => $fila, 'campo' => 'email', 'mensaje' => "Email inválido: {$email}"];
                    continue;
                }
                if ($email && in_array($email, $emailsArchivo)) {
                    $errores[] = ['fila' => $fila, 'campo' => 'email', 'mensaje' => "Email duplicado en el archivo: {$email}"];
                    continue;
                }
                if ($nit && in_array($nit, $nitsArchivo)) {
                    $errores[] = ['fila' => $fila, 'campo' => 'nit', 'mensaje' => "NIT duplicado en el archivo: {$nit}"];
                    continue;
                }
                if ($email && Proveedor::where('empresa_id', $empresaId)->where('email', $email)->exists()) {
                    $errores[] = ['fila' => $fila, 'campo' => 'email', 'mensaje' => "Email ya registrado en el sistema: {$email}"];
                    continue;
                }
                if ($nit && Proveedor::where('empresa_id', $empresaId)->where('nit', $nit)->exists()) {
                    $errores[] = ['fila' => $fila, 'campo' => 'nit', 'mensaje' => "NIT ya registrado en el sistema: {$nit}"];
                    continue;
                }

                if ($email) $emailsArchivo[] = $email;
                if ($nit)   $nitsArchivo[]   = $nit;

                $now = now();
                $registros[] = [
                    'empresa_id' => $empresaId,
                    'nombre'     => $nombre,
                    'nit'        => $nit ?: null,
                    'contacto'   => $val('contacto') ?: null,
                    'email'      => $email ?: null,
                    'telefono'   => $val('telefono') ?: null,
                    'direccion'  => $val('direccion') ?: null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($errores)) {
                return response()->json([
                    'message' => 'Se encontraron errores. No se importó ningún registro.',
                    'errores' => $errores,
                ], 422);
            }

            if (empty($registros)) {
                return response()->json(['message' => 'No se encontraron registros para importar.'], 422);
            }

            DB::table('proveedores')->insert($registros);

            return response()->json([
                'message'    => count($registros) . ' proveedores importados correctamente.',
                'importados' => count($registros),
            ]);
        } finally {
            Storage::delete($path);
        }
    }
}
