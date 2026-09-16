<?php
declare(strict_types=1);

/**
 * Script de Diagnóstico de API Key de Resend
 * Prueba directamente la autenticación contra la API de Resend (https://api.resend.com/api-keys o /emails)
 * 
 * Uso manual:
 * php tests/test_resend_api.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/smtp.php';

echo "=========================================================\n";
echo "   DIAGNÓSTICO DE CONEXIÓN CON LA API DE RESEND\n";
echo "=========================================================\n\n";

$smtpConfig = getSmtpConfig();
$resendConfig = $smtpConfig['resend'] ?? [];
$apiKey = trim($resendConfig['api_key'] ?? '');

echo "1. Método configurado: " . ($smtpConfig['mailer_method'] ?? 'no definido') . "\n";
echo "2. Remitente: " . ($resendConfig['from_name'] ?? '') . " <" . ($resendConfig['from_email'] ?? '') . ">\n";

if (empty($apiKey)) {
    echo "❌ ERROR: No hay ninguna API Key configurada en config/config.php.\n";
    exit(1);
}

// Mostrar los primeros y últimos caracteres de la clave para verificar que coincida con Resend
$maskedKey = substr($apiKey, 0, 7) . '...' . substr($apiKey, -6);
echo "3. API Key configurada: " . $maskedKey . " (Longitud: " . strlen($apiKey) . " chars)\n\n";

echo "4. Consultando endpoint de dominios / estado de cuenta en Resend...\n";

// Endpoint de prueba de autenticación de Resend: GET https://api.resend.com/api-keys o /domains
$ch = curl_init('https://api.resend.com/domains');
curl_setopt_array($ch, [
    CURLOPT_HTTPGET => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'User-Agent: CarniceriasVictoria-Diagnostics/1.0'
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "❌ Error de red / cURL: " . $curlError . "\n";
    exit(1);
}

echo "HTTP Status Code: " . $httpCode . "\n";
echo "Respuesta del PAC Resend:\n" . $response . "\n\n";

if ($httpCode === 200) {
    echo "✅ ÉXITO: La API Key es VÁLIDA y tiene permisos para consultar la cuenta en Resend.\n";
    $data = json_decode($response, true);
    if (isset($data['data']) && is_array($data['data'])) {
        echo "Dominios configurados en esta cuenta de Resend:\n";
        foreach ($data['data'] as $domain) {
            $name = $domain['name'] ?? 'N/A';
            $status = $domain['status'] ?? 'N/A';
            echo " - {$name} (Estatus: {$status})\n";
        }
    }
} elseif ($httpCode === 401) {
    echo "❌ ERROR 401 (API key is invalid):\n";
    echo "   La clave '{$maskedKey}' fue rechazada por Resend.\n";
    echo "   Posibles causas:\n";
    echo "   a) La clave fue eliminada o revocada en https://resend.com/api-keys.\n";
    echo "   b) La clave fue generada con permisos restringidos o copiada de forma incompleta.\n";
    echo "   c) Solución: Entra a https://resend.com/api-keys, crea una nueva clave con 'Full access', cópiala y colócala en config/config.php en \$resend_api_key.\n";
} else {
    echo "⚠️ Respuesta inesperada: HTTP {$httpCode}.\n";
}
