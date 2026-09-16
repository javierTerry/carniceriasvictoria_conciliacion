<?php
// Mailer.php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

error_log(__DIR__);

require_once __DIR__ . '/../config/smtp.php';
// Asegúrate de apuntar correctamente al autoloader de Composer o a tus includes manuales
require_once __DIR__ . '/../vendor/autoload.php';

class Mailer
{
    private array $config;

    public function __construct()
    {
        // Cargamos la configuración de forma aislada
        $this->config = getSmtpConfig();
    }

    /**
     * Envía un correo electrónico estructurado.
     * Soporta Resend API, Gmail API (OAuth2) y SMTP tradicional.
     *
     * @param string $to Correo del destinatario
     * @param string $subject Asunto del correo
     * @param string $body Contenido en HTML o texto plano
     * @param array $attachments Rutas de archivos opcionales ['ruta/al/archivo.pdf']
     * @return bool True si se envió, lanza una excepción en caso de fallo.
     */
    public function send(string $to, string $subject, string $body, array $attachments = []): bool
    {
        $method = $this->config['mailer_method'] ?? 'resend';

        if ($method === 'resend') {
            try {
                return $this->sendViaResend($to, $subject, $body, $attachments);
            } catch (\Throwable $e) {
                // Si Resend falla (ej. API key no válida o error de red), intentar fallback con Gmail API para no detener la operación
                $this->logEvent("Fallo en método primario 'resend' ({$e->getMessage()}). Intentando fallback automático con Gmail API...", 'WARNING');
                try {
                    return $this->sendViaGmailApi($to, $subject, $body, $attachments);
                } catch (\Throwable $fallbackEx) {
                    $this->logEvent("Fallback con Gmail API también falló: " . $fallbackEx->getMessage(), 'ERROR');
                    throw $e; // Re-lanzar el error de Resend con su diagnóstico
                }
            }
        }

        if ($method === 'gmail_api') {
            return $this->sendViaGmailApi($to, $subject, $body, $attachments);
        }

        return $this->sendViaSmtp($to, $subject, $body, $attachments);
    }

    /**
     * Envía correo utilizando el método SMTP tradicional (PHPMailer).
     */
    private function sendViaSmtp(string $to, string $subject, string $body, array $attachments): bool
    {
        $mail = new PHPMailer(true);

        try {
            // Configuración del Servidor SMTP
            $mail->isSMTP();
            $mail->Host = $this->config['smtp']['host'];
            $mail->SMTPAuth = $this->config['smtp']['auth'];
            $mail->Username = $this->config['smtp']['username'];
            $mail->Password = $this->config['smtp']['password'];
            $mail->SMTPSecure = $this->config['smtp']['encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->config['smtp']['port'];
            $mail->CharSet = 'UTF-8'; // Evita problemas con eñes y acentos

            // Destinatarios y Remitente
            $mail->setFrom($this->config['smtp']['from_email'], $this->config['smtp']['from_name']);

            $emails = array_filter(array_map('trim', explode(',', $to)));
            foreach ($emails as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $mail->addAddress($email);
                }
            }

            // Archivos adjuntos (opcional)
            foreach ($attachments as $filePath) {
                if (file_exists($filePath)) {
                    $mail->addAttachment($filePath);
                }
            }

            // Contenido del correo
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            // Versión en texto plano automática despojando etiquetas HTML
            $mail->AltBody = strip_tags($body);

            $mail->send();
            $this->logEvent("Correo enviado exitosamente vía SMTP", 'INFO', [
                'to' => $emails,
                'subject' => $subject,
                'attachments_count' => count($attachments)
            ]);
            return true;

        } catch (Exception $e) {
            $errorMsg = "Error al enviar correo mediante PHPMailer SMTP: {$mail->ErrorInfo}";
            $this->logEvent($errorMsg, 'ERROR', ['to' => $to, 'subject' => $subject]);
            throw new \RuntimeException($errorMsg);
        }
    }

    /**
     * Envía correo utilizando la API oficial de Gmail mediante el SDK de Google.
     */
    private function sendViaGmailApi(string $to, string $subject, string $body, array $attachments): bool
    {
        if (!class_exists('Google\Client')) {
            throw new \RuntimeException("La librería oficial de Google API Client no está instalada. Ejecute 'composer require google/apiclient:^2.15'");
        }

        $gmailConfig = $this->config['gmail_api'] ?? [];

        $client = new \Google\Client();
        $client->setApplicationName('Sistema de Notificaciones Victoria');
        $client->setScopes([\Google\Service\Gmail::GMAIL_SEND]);

        // Intentar configurar credenciales directas primero
        $clientId = $gmailConfig['client_id'] ?? '';
        $clientSecret = $gmailConfig['client_secret'] ?? '';
        $refreshToken = $gmailConfig['refresh_token'] ?? '';

        if (!empty($clientId) && !empty($clientSecret) && !empty($refreshToken)) {
            $client->setClientId($clientId);
            $client->setClientSecret($clientSecret);
            $client->setAccessType('offline');
            $client->refreshToken($refreshToken);
        } else {
            // Intentar cargar mediante archivos de credenciales y token
            $credentialsPath = $gmailConfig['credentials_path'] ?? '';
            $tokenPath = $gmailConfig['token_path'] ?? '';

            if (file_exists($credentialsPath)) {
                $client->setAuthConfig($credentialsPath);
            } else {
                throw new \RuntimeException("Falta la configuración de Google API (Credenciales directas o archivo credentials.json no encontrado).");
            }

            if (file_exists($tokenPath)) {
                $accessToken = json_decode(file_get_contents($tokenPath), true);
                $client->setAccessToken($accessToken);
            } else {
                throw new \RuntimeException("Archivo de token de Google API no encontrado en '$tokenPath'.");
            }
        }

        // Si el token está caducado, refrescarlo automáticamente
        if ($client->isAccessTokenExpired()) {
            $refToken = $client->getRefreshToken();
            if ($refToken) {
                $client->fetchAccessTokenWithRefreshToken($refToken);
                // Si estamos usando un archivo de token físico, lo actualizamos
                $tokenPath = $gmailConfig['token_path'] ?? '';
                if (!empty($tokenPath) && file_exists($tokenPath)) {
                    file_put_contents($tokenPath, json_encode($client->getAccessToken()));
                }
            } else {
                throw new \RuntimeException("El token de acceso de Google API está caducado y no se dispone de un token de actualización (Refresh Token).");
            }
        }

        // Crear servicio de Gmail
        $service = new \Google\Service\Gmail($client);

        // Estructurar el mensaje MIME utilizando PHPMailer
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($gmailConfig['from_email'] ?? 'ocv.facturacion1@gmail.com', $gmailConfig['from_name'] ?? 'Sistema de Notificaciones Victoria');

        $emails = array_filter(array_map('trim', explode(',', $to)));
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mail->addAddress($email);
            }
        }
        //$mail->addCC('cyovictoriafacturacion22@gmail.com', 'Sistema de Notificaciones Victoria');
        // Archivos adjuntos
        foreach ($attachments as $filePath) {
            if (file_exists($filePath)) {
                $mail->addAttachment($filePath);
            }
        }

        // Contenido del correo
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        try {
            // Generar el mensaje RFC 822 en memoria
            $mail->preSend();
            $mimeMessage = $mail->getSentMIMEMessage();

            // Codificar el correo en Base64 seguro para URL
            $base64SafeMime = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($mimeMessage));

            // Crear y enviar el objeto mensaje de la API de Gmail
            $msg = new \Google\Service\Gmail\Message();
            $msg->setRaw($base64SafeMime);

            $service->users_messages->send('me', $msg);
            $this->logEvent("Correo enviado exitosamente vía Gmail API", 'INFO', [
                'to' => $emails,
                'subject' => $subject,
                'attachments_count' => count($attachments)
            ]);
            return true;

        } catch (Exception $e) {
            $errorMsg = "Error al compilar correo MIME mediante PHPMailer para Gmail API: {$mail->ErrorInfo}";
            $this->logEvent($errorMsg, 'ERROR', ['to' => $to, 'subject' => $subject]);
            throw new \RuntimeException("No se pudo compilar el correo: {$mail->ErrorInfo}");
        } catch (\Exception $e) {
            $errorMsg = "Error al transmitir correo mediante Gmail API: " . $e->getMessage();
            $this->logEvent($errorMsg, 'ERROR', ['to' => $to, 'subject' => $subject]);
            throw new \RuntimeException("No se pudo enviar el correo mediante la API de Gmail: " . $e->getMessage());
        }
    }

    /**
     * Envía correo utilizando la API REST oficial de Resend (https://resend.com/).
     *
     * @param string $to Correos destinatarios separados por coma
     * @param string $subject Asunto del correo
     * @param string $body Contenido en HTML
     * @param array $attachments Rutas locales de los archivos adjuntos
     * @return bool True si se envió correctamente, lanza RuntimeException en caso de error.
     */
    private function sendViaResend(string $to, string $subject, string $body, array $attachments): bool
    {
        $resendConfig = $this->config['resend'] ?? [];
        $apiKey = trim($resendConfig['api_key'] ?? '');

        if (empty($apiKey)) {
            $errorMsg = "Falta la clave API de Resend ('api_key') en la configuración de config/smtp.php.";
            $this->logEvent($errorMsg, 'ERROR', ['to' => $to, 'subject' => $subject]);
            throw new \RuntimeException($errorMsg);
        }

        $fromEmail = trim($resendConfig['from_email'] ?? 'noreply@carniceriasvictoria.com.mx');
        $fromName = trim($resendConfig['from_name'] ?? 'Sistema de Notificaciones Victoria');
        $from = !empty($fromName) ? "{$fromName} <{$fromEmail}>" : $fromEmail;

        // Procesar destinatarios
        $emails = array_filter(array_map('trim', explode(',', $to)));
        $validEmails = [];
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validEmails[] = $email;
            }
        }

        if (empty($validEmails)) {
            $errorMsg = "No se especificaron destinatarios válidos para el envío por Resend.";
            $this->logEvent($errorMsg, 'ERROR', ['raw_to' => $to, 'subject' => $subject]);
            throw new \RuntimeException($errorMsg);
        }

        // Procesar archivos adjuntos (XML, PDF) codificados en Base64 según especificación Resend
        $resendAttachments = [];
        foreach ($attachments as $filePath) {
            if (is_string($filePath) && file_exists($filePath)) {
                $content = file_get_contents($filePath);
                if ($content !== false) {
                    $resendAttachments[] = [
                        'filename' => basename($filePath),
                        'content' => base64_encode($content)
                    ];
                }
            }
        }

        $payload = [
            'from' => $from,
            'to' => $validEmails,
            'subject' => $subject,
            'html' => $body,
            'text' => strip_tags($body)
        ];

        if (!empty($resendAttachments)) {
            $payload['attachments'] = $resendAttachments;
        }

        // Petición HTTP POST a la API de Resend mediante cURL nativo
        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'User-Agent: CarniceriasVictoria-Mailer/1.0'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $errorMsg = "Error cURL al conectar con la API de Resend: {$curlError}";
            $this->logEvent($errorMsg, 'ERROR', ['to' => $validEmails, 'subject' => $subject]);
            throw new \RuntimeException($errorMsg);
        }

        $responseData = json_decode($response ?: '', true);

        if ($httpCode >= 200 && $httpCode < 300) {
            $emailId = $responseData['id'] ?? 'desconocido';
            $this->logEvent("Correo enviado exitosamente vía Resend API (ID: {$emailId})", 'INFO', [
                'id' => $emailId,
                'from' => $from,
                'to' => $validEmails,
                'subject' => $subject,
                'attachments_count' => count($resendAttachments)
            ]);
            return true;
        }

        $apiError = $responseData['message'] ?? ($responseData['error'] ?? "HTTP Status {$httpCode}");

        if ($httpCode === 401) {
            $maskedKey = strlen($apiKey) > 10 ? substr($apiKey, 0, 7) . '...' . substr($apiKey, -4) : '***';
            $errorMsg = "Error de autenticación con Resend [401]: La clave API configurada ({$maskedKey}) no es válida o fue revocada en https://resend.com/api-keys. Por favor genera una nueva API Key con permiso 'Full access' y actualízala en config/config.php (\$resend_api_key).";
        } elseif ($httpCode === 403) {
            $errorMsg = "Error de permisos en Resend [403]: {$apiError}. Verifica que el dominio remitente 'carniceriasvictoria.com.mx' esté verificado en https://resend.com/domains.";
        } else {
            $errorMsg = "Error devuelto por la API de Resend [{$httpCode}]: {$apiError}";
        }

        $this->logEvent($errorMsg, 'ERROR', [
            'http_code' => $httpCode,
            'response' => $responseData,
            'to' => $validEmails,
            'subject' => $subject
        ]);

        throw new \RuntimeException($errorMsg);
    }

    /**
     * Registra eventos y errores en el log dedicado del servicio Mailer.
     *
     * @param string $message Mensaje descriptivo
     * @param string $level Nivel de log (INFO, WARNING, ERROR)
     * @param array $context Metadatos adicionales para trazabilidad
     */
    private function logEvent(string $message, string $level = 'INFO', array $context = []): void
    {
        $dir = __DIR__ . '/../logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $date = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Contexto: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $line = "[{$date}] [{$level}] [MAILER] {$message}{$contextStr}" . PHP_EOL;
        @file_put_contents("{$dir}/mailer.log", $line, FILE_APPEND);
    }
}