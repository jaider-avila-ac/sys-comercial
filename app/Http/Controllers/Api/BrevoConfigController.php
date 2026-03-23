<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BrevoConfig;
use App\Services\BrevoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BrevoConfigController extends Controller
{
    // GET /api/brevo/config
    public function show(Request $request): JsonResponse
    {
        $usuario = $request->user();

        if (! in_array($usuario->rol, ['SUPER_ADMIN', 'EMPRESA_ADMIN'])) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        // ResolveEmpresaContext ya resolvió el empresa_id correcto
        $cfg = BrevoConfig::where('empresa_id', $request->empresa_id_ctx)->first();

        if (! $cfg) {
            return response()->json(['config' => null]);
        }

        return response()->json([
            'config' => [
                'id'           => $cfg->id,
                'empresa_id'   => $cfg->empresa_id,
                'is_activo'    => $cfg->is_activo,
                'api_key_hint' => $cfg->api_key
                    ? '••••••••' . substr($cfg->api_key, -8)
                    : null,
                'tiene_key'    => ! empty($cfg->api_key),
                'sender_name'  => $cfg->sender_name,
                'sender_email' => $cfg->sender_email,
                'template_id'  => $cfg->template_id,
            ],
        ]);
    }

    // POST /api/brevo/config
    public function upsert(Request $request): JsonResponse
    {
        $usuario = $request->user();

        if (! in_array($usuario->rol, ['SUPER_ADMIN', 'EMPRESA_ADMIN'])) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $data = $request->validate([
            'is_activo'    => ['required', 'boolean'],
            'api_key'      => ['nullable', 'string', 'max:2048'],
            'sender_name'  => ['required', 'string', 'max:80'],
            'sender_email' => ['required', 'email', 'max:120'],
            'template_id'  => ['nullable', 'integer', 'min:1'],
        ]);

        $data['sender_name']  = trim($data['sender_name']);
        $data['sender_email'] = trim(strtolower($data['sender_email']));
        $data['api_key']      = isset($data['api_key']) ? trim($data['api_key']) : null;

        $empresaId = $request->empresa_id_ctx;
        $existing  = BrevoConfig::where('empresa_id', $empresaId)->first();

        if ($existing) {
            // No sobrescribir api_key si viene vacío — se mantiene la anterior
            if (empty($data['api_key'])) {
                unset($data['api_key']);
            }

            $existing->update([...$data, 'empresa_id' => $empresaId]);

            return response()->json(['ok' => true, 'message' => 'Configuración actualizada correctamente.']);
        }

        // Primera vez — api_key es obligatorio
        if (empty($data['api_key'])) {
            return response()->json([
                'message' => 'El API Key es requerido en la primera configuración.',
            ], 422);
        }

        BrevoConfig::create([...$data, 'empresa_id' => $empresaId]);

        return response()->json(['ok' => true, 'message' => 'Configuración creada correctamente.'], 201);
    }

    // POST /api/brevo/test
    public function test(Request $request): JsonResponse
    {
        $usuario = $request->user();

        if (! in_array($usuario->rol, ['SUPER_ADMIN', 'EMPRESA_ADMIN'])) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $empresaId = $request->empresa_id_ctx;

        $cfg = BrevoConfig::where('empresa_id', $empresaId)->first();

        if (! $cfg) {
            return response()->json(['ok' => false, 'message' => 'No hay configuración guardada para esta empresa.'], 422);
        }

        if (empty($cfg->api_key)) {
            return response()->json(['ok' => false, 'message' => 'La configuración no tiene API Key.'], 422);
        }

        if (! $cfg->is_activo) {
            return response()->json(['ok' => false, 'message' => 'La integración Brevo está inactiva.'], 422);
        }

        try {
            /** @var BrevoService $svc */
            $svc = app(BrevoService::class);

            $result = $svc->enviarConHtml(
                apiKey:      $cfg->api_key,
                senderName:  $cfg->sender_name,
                senderEmail: $cfg->sender_email,
                toEmail:     trim(strtolower($data['email'])),
                toName:      'Prueba',
                subject:     'Prueba de conexión · SYS Comercial',
                html:        "
                    <html><body style='font-family:Arial;padding:32px'>
                        <h2 style='color:#16a34a'>Conexión exitosa</h2>
                        <p>Este es un email de prueba enviado desde <strong>SYS Comercial</strong> usando Brevo.</p>
                        <p><strong>Empresa ID:</strong> {$empresaId}</p>
                        <p><strong>Remitente configurado:</strong> {$cfg->sender_email}</p>
                    </body></html>
                ",
            );

            if (! $result['ok']) {
                return response()->json([
                    'ok'      => false,
                    'message' => $result['message'] ?: 'Brevo rechazó el envío.',
                    'details' => $result['details'] ?? null,
                ], 422);
            }

            return response()->json([
                'ok'        => true,
                'message'   => 'Email de prueba enviado. Revisa tu bandeja de entrada.',
                'messageId' => $result['message_id'] ?? null,
            ]);

        } catch (\Throwable $e) {
            Log::error('[BrevoConfigController@test] ' . $e->getMessage(), [
                'empresa_id' => $empresaId,
                'trace'      => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ok'      => false,
                'message' => 'Excepción al enviar el email de prueba.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}