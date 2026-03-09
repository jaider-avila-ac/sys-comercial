<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoService
{
    public function enviarConHtml(
        string $apiKey,
        string $senderName,
        string $senderEmail,
        string $toEmail,
        string $toName,
        string $subject,
        string $html
    ): array {
        $payload = [
            'sender' => [
                'name'  => $senderName,
                'email' => $senderEmail,
            ],
            'to' => [
                [
                    'email' => $toEmail,
                    'name'  => $toName,
                ]
            ],
            'subject'     => $subject,
            'htmlContent' => $html,
        ];

        $res = Http::withoutVerifying()->withHeaders([
    'accept'       => 'application/json',
    'api-key'      => trim($apiKey),
    'content-type' => 'application/json',
])->post('https://api.brevo.com/v3/smtp/email', $payload);

        if (!$res->successful()) {
            Log::error('[BrevoService] Error enviando correo', [
                'status'  => $res->status(),
                'body'    => $res->body(),
                'json'    => $res->json(),
                'payload' => $payload,
            ]);

            $json = $res->json();

            return [
                'ok'         => false,
                'message'    => $json['message'] ?? ('Error HTTP ' . $res->status()),
                'details'    => $json,
                'message_id' => null,
            ];
        }

        $json = $res->json();

        Log::info('[BrevoService] Correo enviado OK', [
            'status' => $res->status(),
            'json'   => $json,
        ]);

        return [
            'ok'         => true,
            'message'    => 'Correo enviado correctamente.',
            'details'    => $json,
            'message_id' => $json['messageId'] ?? null,
        ];
    }

    public function notificarPago(\App\Models\Pago $pago): array
{
    $pago->loadMissing([
        'cliente:id,nombre_razon_social,email',
        'aplicaciones.factura:id,numero,total,saldo,empresa_id',
    ]);

    $factura = optional($pago->aplicaciones->first())->factura;

    if (!$factura) {
        return [
            'ok'      => false,
            'message' => 'El pago no tiene factura asociada.',
            'details' => null,
        ];
    }

    $cfg = \App\Models\BrevoConfig::where('empresa_id', $factura->empresa_id)
        ->where('is_activo', 1)
        ->first();

    if (!$cfg || empty($cfg->api_key)) {
        return [
            'ok'      => false,
            'message' => 'Brevo no configurado o inactivo para la empresa.',
            'details' => null,
        ];
    }

    $cliente = $pago->cliente;
    $toEmail = trim((string)($cliente->email ?? ''));

    if ($toEmail === '') {
        return [
            'ok'      => false,
            'message' => 'El cliente no tiene email registrado.',
            'details' => null,
        ];
    }

    $nombreCliente = $cliente->nombre_razon_social ?? 'Cliente';
    $numeroFactura = $factura->numero ?? '—';
    $montoPagado   = number_format((float)$pago->total_pagado, 2, ',', '.');
    $saldoRestante = number_format((float)$factura->saldo, 2, ',', '.');
    $fechaPago     = $pago->fecha ? \Carbon\Carbon::parse($pago->fecha)->format('d/m/Y') : now()->format('d/m/Y');

    $subject = 'Pago recibido · ' . $numeroFactura;

    $html = "
        <html>
        <body style='font-family: Arial, sans-serif; padding: 24px; color: #111827;'>
            <div style='max-width: 620px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden;'>
                <div style='background: #0ea5e9; color: white; padding: 18px 24px;'>
                    <h2 style='margin: 0; font-size: 20px;'>Pago recibido</h2>
                </div>

                <div style='padding: 24px;'>
                    <p style='margin-top: 0;'>Hola <strong>{$nombreCliente}</strong>,</p>

                    <p>
                        Hemos recibido el pago de tu factura
                        <strong>{$numeroFactura}</strong>.
                    </p>

                    <table style='width: 100%; border-collapse: collapse; margin: 18px 0;'>
                        <tr>
                            <td style='padding: 10px; border: 1px solid #e5e7eb; background: #f9fafb;'><strong>Fecha</strong></td>
                            <td style='padding: 10px; border: 1px solid #e5e7eb;'>{$fechaPago}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px; border: 1px solid #e5e7eb; background: #f9fafb;'><strong>Factura</strong></td>
                            <td style='padding: 10px; border: 1px solid #e5e7eb;'>{$numeroFactura}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px; border: 1px solid #e5e7eb; background: #f9fafb;'><strong>Monto pagado</strong></td>
                            <td style='padding: 10px; border: 1px solid #e5e7eb;'>$ {$montoPagado}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px; border: 1px solid #e5e7eb; background: #f9fafb;'><strong>Saldo restante</strong></td>
                            <td style='padding: 10px; border: 1px solid #e5e7eb;'>$ {$saldoRestante}</td>
                        </tr>
                    </table>

                    <p style='margin-bottom: 0; color: #6b7280; font-size: 13px;'>
                        Este mensaje fue generado automáticamente por el sistema.
                    </p>
                </div>
            </div>
        </body>
        </html>
    ";

    return $this->enviarConHtml(
        apiKey:      $cfg->api_key,
        senderName:  $cfg->sender_name,
        senderEmail: $cfg->sender_email,
        toEmail:     $toEmail,
        toName:      $nombreCliente,
        subject:     $subject,
        html:        $html,
    );
}
}