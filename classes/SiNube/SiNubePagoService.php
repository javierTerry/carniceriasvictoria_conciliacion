<?php

namespace App\Services\SiNube;

use App\DTOs\SiNube\SiNubePagoDTO;
use App\DTOs\SiNube\SiNubePagoResponseDTO;
use Exception;
use SimpleXMLElement;

class SiNubePagoService
{
    private string $logPath;
    private SiNubeXmlBuilder $xmlBuilder;

    /**
     * @param string $rfcEmisor RFC de la empresa emisora
     * @param string $sucursal Sucursal en SiNube (ej. 'Matriz')
     * @param string $usuario Usuario con acceso a SiNube
     * @param string $password Contraseña de comunicaciones SiNube
     * @param string $baseUrl URL base de SiNube (Dev: http://ep-dot-facturanube.appspot.com)
     * @param string|null $logPath Ruta de log dedicada
     * @param SiNubeXmlBuilder|null $xmlBuilder Instancia de constructor XML
     */
    public function __construct(
        private string $rfcEmisor,
        private string $sucursal = 'Matriz',
        private string $usuario = '',
        private string $password = '',
        private string $baseUrl = 'https://ep-dot-facturanube.appspot.com',
        ?string $logPath = null,
        ?SiNubeXmlBuilder $xmlBuilder = null
    ) {
        $this->logPath = $logPath ?? __DIR__ . '/../../logs/depositos.log';
        $this->xmlBuilder = $xmlBuilder ?? new SiNubeXmlBuilder();

        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    /**
     * Envía la solicitud de timbrado de Pago a SiNube (tipo=47).
     */
    public function timbrarPago(SiNubePagoDTO $dto): SiNubePagoResponseDTO
    {
        $xmlPayload = $this->xmlBuilder->build($dto);

        // Generar parámetro codificado en Base64 según la especificación de SiNube
        $paramString = "tipo=47\nemp={$this->rfcEmisor}\nsuc={$this->sucursal}\nusu={$this->usuario}\npwd={$this->password}";
        $parEncoded = base64_encode($paramString);
        $urlEnvio = rtrim($this->baseUrl, '/') . "/blob?par=" . $parEncoded;

        $this->log("Iniciando timbrado de Pago (tipo=47). Serie: {$dto->serie}, Folio: {$dto->folio}, Monto: {$dto->monto}", 'INFO');
        $this->log("Endpoint: {$urlEnvio}", 'DEBUG');
        $this->log("XML Payload:\n" . $xmlPayload, 'DEBUG');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $urlEnvio);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlPayload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 35);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/xml; charset=utf-8',
            'Content-Length: ' . strlen($xmlPayload)
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $this->log("Respuesta recibida de SiNube (HTTP {$httpCode}): " . $response, 'INFO');

        if ($response === false || $httpCode !== 200) {
            $errorMsg = "Error de comunicación con SiNube (HTTP {$httpCode}): " . ($curlError ?: 'Sin respuesta del servidor');
            $this->log($errorMsg, 'ERROR');
            return new SiNubePagoResponseDTO(
                success: false,
                serie: $dto->serie,
                folio: $dto->folio,
                mensaje: $errorMsg,
                rawResponse: $response ?: $curlError
            );
        }

        try {
            libxml_use_internal_errors(true);
            $xmlObj = simplexml_load_string($response);
            if (!$xmlObj) {
                // Si la respuesta no es XML pero contiene error de texto
                if (stripos($response, '<error>') !== false) {
                    preg_match('/<error>(.*?)<\/error>/is', $response, $matches);
                    $errMsg = !empty($matches[1]) ? trim($matches[1]) : strip_tags($response);
                    return new SiNubePagoResponseDTO(
                        success: false,
                        serie: $dto->serie,
                        folio: $dto->folio,
                        mensaje: "Error SiNube: " . $errMsg,
                        rawResponse: $response
                    );
                }
                throw new Exception("La respuesta recibida no es un XML válido: " . substr($response, 0, 300));
            }

            // 1. Validar si contiene nodo de error
            $errorMatches = $xmlObj->xpath("/Respuesta/error");
            if (empty($errorMatches)) {
                $errorMatches = $xmlObj->xpath("//error");
            }

            if (!empty($errorMatches) && trim((string)$errorMatches[0]) !== '') {
                $errorDetalle = trim((string)$errorMatches[0]);
                $this->log("Error reportado por SiNube: {$errorDetalle}", 'ERROR');
                return new SiNubePagoResponseDTO(
                    success: false,
                    serie: $dto->serie,
                    folio: $dto->folio,
                    mensaje: "Error SiNube: {$errorDetalle}",
                    rawResponse: $response
                );
            }

            // 2. Extraer UUID, XML y PDF
            $uuid = (string)($xmlObj['uuid'] ?? ($xmlObj['UUID'] ?? ''));
            if (empty($uuid)) {
                $uuidNodes = $xmlObj->xpath("//@uuid | //@UUID | //UUID | //uuid");
                if (!empty($uuidNodes)) {
                    $uuid = trim((string)$uuidNodes[0]);
                }
            }

            $xmlUrl = (string)($xmlObj['xml'] ?? ($xmlObj['XML'] ?? ''));
            if (empty($xmlUrl)) {
                $xmlNodes = $xmlObj->xpath("//@xml | //@XML | //xml | //XML");
                if (!empty($xmlNodes)) {
                    $xmlUrl = trim((string)$xmlNodes[0]);
                }
            }

            $pdfUrl = (string)($xmlObj['pdf'] ?? ($xmlObj['PDF'] ?? ''));
            if (empty($pdfUrl)) {
                $pdfNodes = $xmlObj->xpath("//@pdf | //@PDF | //pdf | //PDF");
                if (!empty($pdfNodes)) {
                    $pdfUrl = trim((string)$pdfNodes[0]);
                }
            }

            if (empty($uuid)) {
                $msg = "SiNube respondió HTTP 200 pero no devolvió UUID.";
                $this->log($msg, 'ERROR');
                return new SiNubePagoResponseDTO(
                    success: false,
                    serie: $dto->serie,
                    folio: $dto->folio,
                    mensaje: $msg,
                    rawResponse: $response
                );
            }

            $this->log("Pago timbrado exitosamente. UUID: {$uuid}, XML: {$xmlUrl}", 'INFO');

            return new SiNubePagoResponseDTO(
                success: true,
                uuid: $uuid,
                xmlUrl: $xmlUrl,
                pdfUrl: $pdfUrl,
                serie: $dto->serie,
                folio: $dto->folio,
                mensaje: 'CFDI de Pago timbrado exitosamente.',
                rawResponse: $response
            );

        } catch (Exception $e) {
            $this->log("Excepción al procesar XML de respuesta: " . $e->getMessage(), 'ERROR');
            return new SiNubePagoResponseDTO(
                success: false,
                serie: $dto->serie,
                folio: $dto->folio,
                mensaje: "Error procesando respuesta: " . $e->getMessage(),
                rawResponse: $response
            );
        }
    }

    private function log(string $message, string $level = 'INFO'): void
    {
        $date = date('Y-m-d H:i:s');
        $line = "[{$date}] [{$level}] [PAGO_SINUBE] {$message}" . PHP_EOL;
        @file_put_contents($this->logPath, $line, FILE_APPEND);
    }
}
