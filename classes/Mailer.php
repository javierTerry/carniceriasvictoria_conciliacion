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
     *
     * @param string $to Correo del destinatario
     * @param string $subject Asunto del correo
     * @param string $body Contenido en HTML o texto plano
     * @param array $attachments Rutas de archivos opcionales ['ruta/al/archivo.pdf']
     * @return bool True si se envió, lanza una excepción en caso de fallo.
     */
    public function send(string $to, string $subject, string $body, array $attachments = []): bool 
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
            // En entornos de producción, es mejor registrar esto en un log en lugar de romper el flujo
            error_log("Error al enviar correo mediante PHPMailer: {$mail->ErrorInfo}");
            throw new \RuntimeException("No se pudo enviar el correo: {$mail->ErrorInfo}");
        }
    }
}