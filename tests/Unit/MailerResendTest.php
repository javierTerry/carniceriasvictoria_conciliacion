<?php
declare(strict_types=1);

/**
 * Prueba Unitaria: Integración de Resend y Servicio Mailer
 * Valida la configuración de Resend en config/smtp.php, el remitente noreply@carniceriasvictoria.com.mx,
 * y los mecanismos de control y excepciones de la clase Mailer.
 *
 * Para ejecutar manualmente:
 * php tests/Unit/MailerResendTest.php
 */

require_once __DIR__ . '/../../config/smtp.php';
require_once __DIR__ . '/../../classes/Mailer.php';

class MailerResendTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void
    {
        echo "=========================================================\n";
        echo "   EJECUCIÓN DE PRUEBAS UNITARIAS: MAILER & RESEND API\n";
        echo "=========================================================\n\n";

        $this->testConfigContainsResendSection();
        $this->testResendFromEmailIsNoreply();
        $this->testResendApiKeyLoadedFromConfigPhp();
        $this->testResendRequiresApiKeyWhenSending();
        $this->testMailerClassCanBeInstantiated();
        $this->testResendRejectsInvalidEmails();

        echo "\n---------------------------------------------------------\n";
        echo "RESUMEN: " . $this->passed . " PASADAS | " . $this->failed . " FALLADAS\n";
        echo "---------------------------------------------------------\n";

        if ($this->failed > 0) {
            echo "RESULTADO: ERROR - ALGUNAS PRUEBAS FALLARON.\n";
            exit(1);
        } else {
            echo "RESULTADO: ÉXITO - TODAS LAS PRUEBAS PASARON CORRECTAMENTE.\n";
            exit(0);
        }
    }

    private function testResendApiKeyLoadedFromConfigPhp(): void
    {
        $config = getSmtpConfig();
        $apiKey = $config['resend']['api_key'] ?? '';
        if (!empty($apiKey) && str_starts_with((string) $apiKey, 're_')) {
            $this->assert(true, "getSmtpConfig() carga correctamente la API Key de Resend desde config/config.php.");
        } else {
            $this->assert(false, "getSmtpConfig() no devolvió una clave válida que inicie con 're_'.");
        }
    }

    private function testConfigContainsResendSection(): void
    {
        $config = getSmtpConfig();
        if (isset($config['resend']) && is_array($config['resend'])) {
            $this->assert(true, "getSmtpConfig() incluye la sección 'resend'.");
        } else {
            $this->assert(false, "getSmtpConfig() NO incluye la sección 'resend'.");
        }
    }

    private function testResendFromEmailIsNoreply(): void
    {
        $config = getSmtpConfig();
        $fromEmail = $config['resend']['from_email'] ?? '';
        if ($fromEmail === 'noreply@carniceriasvictoria.com.mx') {
            $this->assert(true, "El remitente de Resend está configurado como 'noreply@carniceriasvictoria.com.mx'.");
        } else {
            $this->assert(false, "El remitente de Resend esperado era 'noreply@carniceriasvictoria.com.mx', se obtuvo '{$fromEmail}'.");
        }
    }

    private function testMailerClassCanBeInstantiated(): void
    {
        try {
            $mailer = new Mailer();
            $this->assert($mailer instanceof Mailer, "La clase Mailer se instancia correctamente.");
        } catch (\Throwable $e) {
            $this->assert(false, "Fallo al instanciar Mailer: " . $e->getMessage());
        }
    }

    private function testResendRequiresApiKeyWhenSending(): void
    {
        $config = getSmtpConfig();
        // Si no hay api_key configurada, send() debe lanzar RuntimeException por falta de API Key
        if (empty($config['resend']['api_key'])) {
            try {
                $mailer = new Mailer();
                $mailer->send('cliente@ejemplo.com', 'Prueba', '<p>Hola</p>');
                $this->assert(false, "Se esperaba RuntimeException por falta de API Key en Resend.");
            } catch (\RuntimeException $e) {
                $hasApiKeyMessage = strpos($e->getMessage(), 'api_key') !== false;
                $this->assert($hasApiKeyMessage, "Mailer arroja excepción clara al faltar la API Key de Resend: " . $e->getMessage());
            } catch (\Throwable $e) {
                $this->assert(false, "Se esperaba RuntimeException, se capturó: " . get_class($e));
            }
        } else {
            $this->assert(true, "API Key configurada en config/smtp.php.");
        }
    }

    private function testResendRejectsInvalidEmails(): void
    {
        // Prueba de que un correo inválido es rechazado adecuadamente
        try {
            $mailer = new Mailer();
            $reflection = new \ReflectionClass($mailer);
            $method = $reflection->getMethod('sendViaResend');
            $method->setAccessible(true);

            // Inyectamos temporalmente una API key dummy para llegar a la validación de email
            $configProp = $reflection->getProperty('config');
            $configProp->setAccessible(true);
            $dummyConfig = $configProp->getValue($mailer);
            $dummyConfig['resend']['api_key'] = 're_dummy_123';
            $configProp->setValue($mailer, $dummyConfig);

            try {
                $method->invoke($mailer, 'correo_invalido_sin_arroba', 'Asunto', 'Cuerpo', []);
                $this->assert(false, "sendViaResend debía rechazar un correo inválido.");
            } catch (\RuntimeException $e) {
                $isEmailError = strpos($e->getMessage(), 'destinatarios válidos') !== false;
                $this->assert($isEmailError, "sendViaResend valida y rechaza correos con formato inválido.");
            }
        } catch (\Throwable $e) {
            $this->assert(false, "Error en la prueba de validación de correo: " . $e->getMessage());
        }
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            echo " [OK] {$message}\n";
        } else {
            $this->failed++;
            echo " [FAIL] {$message}\n";
        }
    }
}

$test = new MailerResendTest();
$test->run();
