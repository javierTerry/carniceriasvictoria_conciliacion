<?php
/**
 * SinubeHelper.php
 * Clase para gestionar la interacción con la API de Sinube.
 * Versión compatible con PHP 5.6+.
 */

class SinubeHelper
{
    private $logPath;

    public function __construct($logPath = "")
    {
        if (empty($logPath)) {
            $this->logPath = __DIR__ . '/../logs/sinube_api.log';
        } else {
            $this->logPath = $logPath;
        }

        $log_dir = dirname((string) $this->logPath);
        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0755, true);
        }
    }

    /**
     * Obtiene el folio actual desde Sinube validando certificado y serie.
     */
    public function getFolioActual($url, $targetCert, $targetSerie)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            $this->log("Error cURL consultando certificado. Code: " . $httpCode . ", Error: " . $curlError, 'ERROR');
            throw new Exception("Error al consultar folio/serie en SINUBE. (HTTP " . $httpCode . ")");
        }

        try {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($response);
            if ($xml === false) {
                throw new Exception("XML de respuesta de Sinube malformado.");
            }
            $this->log($targetSerie, 'INFO');
            $this->log((string) $xml, 'INFO');

            $certNodes = $xml->xpath("//certificado[@noCertificado='" . $targetCert . "']");
            if (!$certNodes || count($certNodes) === 0) {
                $this->log("Certificado '" . $targetCert . "' no encontrado en la respuesta.", 'ERROR');
                throw new Exception("El certificado configurado (" . $targetCert . ") no se encuentra activo en Sinube.");
            }

            $certNode = $certNodes[0];

            $foliadorNodes = $certNode->xpath("foliador[@serie='" . $targetSerie . "']");
            if (!$foliadorNodes || count($foliadorNodes) === 0) {
                $this->log("Serie '" . $targetSerie . "' no encontrada para el certificado '" . $targetCert . "'.", 'ERROR');
                throw new Exception("La serie '" . $targetSerie . "' no está autorizada para este certificado en Sinube.");
            }

            $foliador = $foliadorNodes[0];
            $serie = (string) (isset($foliador['serie']) ? $foliador['serie'] : '');
            $folioActual = (int) (isset($foliador['folioActual']) ? $foliador['folioActual'] : 0);

            error_log("FOLIOOOOOOOOOOOOOOOOOOOOOO");
            $this->log("Folio obtenido exitosamente: Serie=" . $serie . ", FolioActual=" . $folioActual, 'INFO');

            return array(
                'serie' => $serie,
                'folioActual' => $folioActual
            );

        } catch (Exception $e) {
            $this->log("Error procesando XML de Sinube: " . $e->getMessage(), 'ERROR');
            throw new Exception("Error al procesar datos de foliación: " . $e->getMessage());
        }
    }

    /**
     * Consulta el estatus del CFDI ante el SAT (tipo=2000).
     *
     * @param string $emp RFC de la empresa
     * @param string $pruebas '1' para pruebas, '0' para producción
     * @param string $rfcEmi RFC del emisor del comprobante
     * @param string $rfcRec RFC del receptor del comprobante
     * @param string|float $total Monto total del comprobante
     * @param string $uuid UUID del comprobante
     * @param string $urlBlob URL base del servicio POST (ej. https://ep-dot-facturanube.appspot.com/blob)
     * @return string XML de respuesta del servicio
     */
    public function consultarEstatusCFDI($emp, $pruebas, $rfcEmi, $rfcRec, $total, $uuid, $urlBlob = 'https://ep-dot-facturanube.appspot.com/blob')
    {
        $totalFormatted = is_numeric($total) ? number_format((float) $total, 2, '.', '') : (string) $total;
        $paramString = "tipo=2000\nemp=" . trim((string) $emp) . "\npruebas=" . trim((string) $pruebas) . "\nrfcEmi=" . trim((string) $rfcEmi) . "\nrfcRec=" . trim((string) $rfcRec) . "\ntotal=" . $totalFormatted . "\nuuid=" . trim((string) $uuid);
        $parEncoded = base64_encode($paramString);
        $url = $urlBlob . "?par=" . $parEncoded;

        $this->log("Consultando estatus CFDI (tipo 2000) para UUID: " . $uuid . " (URL: " . $url . ")", 'INFO');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $this->log("Respuesta estatus CFDI SAT (HTTP " . $httpCode . "): " . $response, 'INFO');

        if ($response === false || $httpCode !== 200) {
            $this->log("Error cURL consultando estatus CFDI SAT. Code: " . $httpCode . ", Error: " . $curlError, 'ERROR');
            throw new Exception("Error al consultar estatus de CFDI en SAT/Sinube. (HTTP " . $httpCode . ")");
        }

        return $response;
    }

    /**
     * Registra eventos en el archivo de log.
     */
    public function log($message, $level = 'INFO')
    {
        $date = date('Y-m-d H:i:s');
        $formatted = "[" . $date . "] [" . $level . "] " . $message . PHP_EOL;
        @file_put_contents($this->logPath, $formatted, FILE_APPEND);
    }
}

