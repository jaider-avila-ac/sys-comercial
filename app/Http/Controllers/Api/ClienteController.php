<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Services\ClienteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ClienteController extends Controller
{
    public function __construct(
        private readonly ClienteService $clienteService,
    ) {}

     public function index(Request $request): JsonResponse
    {
        $search = $request->get('search', '');
        $search = is_string($search) ? $search : '';
        
        // Obtener parámetros de paginación
        $perPage = (int) $request->get('per_page', 10);
        $page = (int) $request->get('page', 1);
        
        return response()->json(
            $this->clienteService->listar(
                $request->empresa_id_ctx,
                $search,
                $perPage,
                $page
            )
        );
    }

    // GET /api/clientes/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json([
            'cliente' => $this->clienteService->obtener($id, $request->empresa_id_ctx),
        ]);
    }

    // POST /api/clientes
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre_razon_social' => ['required', 'string', 'max:160'],
            'contacto'            => ['nullable', 'string', 'max:120'],
            'tipo_documento'      => ['nullable', 'in:CC,NIT,CE,PAS,OTRO'],
            'num_documento'       => ['nullable', 'string', 'max:40'],
            'email'               => ['nullable', 'email', 'max:150'],
            'telefono'            => ['nullable', 'string', 'max:40'],
            'empresa'             => ['nullable', 'string', 'max:160'],
            'direccion'           => ['nullable', 'string', 'max:180'],
        ]);

        return response()->json([
            'cliente' => $this->clienteService->crear($data, $request->empresa_id_ctx),
        ], 201);
    }

    // PUT /api/clientes/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'nombre_razon_social' => ['sometimes', 'string', 'max:160'],
            'contacto'            => ['sometimes', 'nullable', 'string', 'max:120'],
            'tipo_documento'      => ['sometimes', 'nullable', 'in:CC,NIT,CE,PAS,OTRO'],
            'num_documento'       => ['sometimes', 'nullable', 'string', 'max:40'],
            'email'               => ['sometimes', 'nullable', 'email', 'max:150'],
            'telefono'            => ['sometimes', 'nullable', 'string', 'max:40'],
            'empresa'             => ['sometimes', 'nullable', 'string', 'max:160'],
            'direccion'           => ['sometimes', 'nullable', 'string', 'max:180'],
        ]);

        return response()->json([
            'cliente' => $this->clienteService->actualizar($id, $data, $request->empresa_id_ctx),
        ]);
    }

    // PATCH /api/clientes/{id}/toggle
    public function toggle(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->clienteService->toggleActivo($id, $request->empresa_id_ctx)
        );
    }

    // DELETE /api/clientes/{id}
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->clienteService->eliminar($id, $request->empresa_id_ctx);
        return response()->json(['message' => 'Cliente eliminado correctamente.']);
    }

    // POST /api/clientes/importar
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
            $cols   = ['empresa', 'nombre_razon_social', 'tipo_documento', 'num_documento', 'contacto', 'email', 'telefono', 'direccion'];
            $idx    = [];
            foreach ($cols as $c) {
                $pos = array_search($c, $header);
                $idx[$c] = $pos !== false ? $pos : null;
            }

            $empresaId     = $request->empresa_id_ctx;
            $errores       = [];
            $registros     = [];
            $emailsArchivo = [];
            $docsArchivo   = [];

            foreach (array_slice($rawRows, 1) as $i => $row) {
                $fila = $i + 2;
                $val  = fn($c) => isset($idx[$c]) && $idx[$c] !== null ? trim((string)($row[$idx[$c]] ?? '')) : '';

                $nombre  = $val('nombre_razon_social');
                $email   = strtolower($val('email'));
                $numDoc  = $val('num_documento');
                $tipoDoc = strtoupper($val('tipo_documento'));

                if (!$nombre) {
                    $errores[] = ['fila' => $fila, 'campo' => 'nombre_razon_social', 'mensaje' => 'El nombre / razón social es obligatorio'];
                    continue;
                }
                if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errores[] = ['fila' => $fila, 'campo' => 'email', 'mensaje' => "Email inválido: {$email}"];
                    continue;
                }
                if ($tipoDoc && !in_array($tipoDoc, ['CC', 'NIT', 'CE', 'PAS', 'OTRO'])) {
                    $errores[] = ['fila' => $fila, 'campo' => 'tipo_documento', 'mensaje' => "Tipo de documento inválido '{$tipoDoc}'. Válidos: CC, NIT, CE, PAS, OTRO"];
                    continue;
                }
                if ($email && in_array($email, $emailsArchivo)) {
                    $errores[] = ['fila' => $fila, 'campo' => 'email', 'mensaje' => "Email duplicado en el archivo: {$email}"];
                    continue;
                }
                $docKey = $numDoc ? ($tipoDoc ? "{$tipoDoc}|{$numDoc}" : $numDoc) : null;
                if ($docKey && in_array($docKey, $docsArchivo)) {
                    $errores[] = ['fila' => $fila, 'campo' => 'num_documento', 'mensaje' => "Número de documento duplicado en el archivo: {$numDoc}"];
                    continue;
                }
                if ($email && Cliente::where('empresa_id', $empresaId)->where('email', $email)->exists()) {
                    $errores[] = ['fila' => $fila, 'campo' => 'email', 'mensaje' => "Email ya registrado en el sistema: {$email}"];
                    continue;
                }
                if ($numDoc) {
                    $q = Cliente::where('empresa_id', $empresaId)->where('num_documento', $numDoc);
                    if ($tipoDoc) $q->where('tipo_documento', $tipoDoc);
                    if ($q->exists()) {
                        $errores[] = ['fila' => $fila, 'campo' => 'num_documento', 'mensaje' => "Número de documento ya registrado: {$numDoc}"];
                        continue;
                    }
                }

                if ($email) $emailsArchivo[] = $email;
                if ($docKey) $docsArchivo[]  = $docKey;

                $now = now();
                $registros[] = [
                    'empresa_id'          => $empresaId,
                    'empresa'             => $val('empresa') ?: null,
                    'nombre_razon_social' => $nombre,
                    'tipo_documento'      => $tipoDoc ?: null,
                    'num_documento'       => $numDoc ?: null,
                    'contacto'            => $val('contacto') ?: null,
                    'email'               => $email ?: null,
                    'telefono'            => $val('telefono') ?: null,
                    'direccion'           => $val('direccion') ?: null,
                    'created_at'          => $now,
                    'updated_at'          => $now,
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

            DB::table('clientes')->insert($registros);

            return response()->json([
                'message'    => count($registros) . ' clientes importados correctamente.',
                'importados' => count($registros),
            ]);
        } finally {
            Storage::delete($path);
        }
    }
}