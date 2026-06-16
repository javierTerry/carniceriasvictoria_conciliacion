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
     * Soporta tanto el método SMTP tradicional como la API oficial de Gmail.
     *
     * @param string $to Correo del destinatario
     * @param string $subject Asunto del correo
     * @param string $body Contenido en HTML o texto plano
     * @param array $attachments Rutas de archivos opcionales ['ruta/al/archivo.pdf']
     * @return bool True si se envió, lanza una excepción en caso de fallo.
     */
    public function send(string $to, string $subject, string $body, array $attachments = []): bool 
    {
        $method = $this->config['mailer_method'] ?? 'smtp';

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
            $mail->Host       = $this->config['smtp']['host'];
            $mail->SMTPAuth   = $this->config['smtp']['auth'];
            $mail->Username   = $this->config['smtp']['username'];
            $mail->Password   = $this->config['smtp']['password'];
            $mail->SMTPSecure = $this->config['smtp']['encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $this->config['smtp']['port'];
            $mail->CharSet    = 'UTF-8'; // Evita problemas con eñes y acentos

            // Destinatarios y Remitente
            $mail->setFrom($this->config['smtp']['from_email'], $this->config['smtp']['from_name']);
            $mail->addAddress($to);

            // Archivos adjuntos (opcional)
            foreach ($attachments as $filePath) {
                if (file_exists($filePath)) {
                    $mail->addAttachment($filePath);
                }
            }

            // Contenido del correo
            $mail->isHTML(true); 
            $mail->Subject = $subject;
            $mail->Body    = $body;
            // Versión en texto plano automática despojando etiquetas HTML
            $mail->AltBody = strip_tags($body); 

            $mail->send();
            return true;

        } catch (Exception $e) {
            error_log("Error al enviar correo mediante PHPMailer SMTP: {$mail->ErrorInfo}");
            throw new \RuntimeException("No se pudo enviar el correo por SMTP: {$mail->ErrorInfo}");
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
        $mail->addAddress($to);
        $mail->addCC('ocv.facturacion1@gmail.com', 'Sistema de Notificaciones Victoria');
        // Archivos adjuntos
        foreach ($attachments as $filePath) {
            if (file_exists($filePath)) {
                $mail->addAttachment($filePath);
            }
        }

        // Contenido del correo
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
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
            return true;

        } catch (Exception $e) {
            error_log("Error al compilar correo MIME mediante PHPMailer para Gmail API: {$mail->ErrorInfo}");
            throw new \RuntimeException("No se pudo compilar el correo: {$mail->ErrorInfo}");
        } catch (\Exception $e) {
            error_log("Error al transmitir correo mediante Gmail API: " . $e->getMessage());
            throw new \RuntimeException("No se pudo enviar el correo mediante la API de Gmail: " . $e->getMessage());
        }
    }
}