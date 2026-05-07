<?php
/**
 * SinubeHelper.php
 * Clase para gestionar la interacción con la API de Sinube.
 */

class SinubeHelper {
    private string $logPath;

    public function __construct(string $logPath = "") {
        if (empty($logPath)) {
            $this->logPath = __DIR__ . '/../logs/sinube_api.log';
        } else {
            $this->logPath = $logPath;
        }
        
        $log_dir = dirname($this->logPath);
        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0755, true);
        }
    }

    /**
     * Obtiene el folio actual desde Sinube validando certificado y serie.
     * 
     * @param string $url URL de la API de Sinube para consulta de certificados
     * @param string $targetCert Número de certificado configurado
     * @param string $targetSerie Serie asignada a la sucursal
     * @return array Con la serie y el folio actual encontrado
     * @throws Exception Si ocurre un error en la comunicación o validación
     */
    public function getFolioActual(string $url, string $targetCert, string $targetSerie): array {
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
            $this->log("Error cURL consultando certificado. Code: $httpCode, Error: $curlError", 'ERROR');
            throw new Exception("Error al consultar folio/serie en SINUBE. (HTTP $httpCode)");
        }

        try {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($response);
            if ($xml === false) {
                throw new Exception("XML de respuesta de Sinube malformado.");
            }

            // 1. Validar el certificado de la respuesta vs $api_no_certificado
            // Buscamos el nodo certificado con el atributo noCertificado correspondiente
            $certNodes = $xml->xpath("//certificado[@noCertificado='$targetCert']");
            if (!$certNodes || count($certNodes) === 0) {
                $this->log("Certificado '$targetCert' no encontrado en la respuesta.", 'ERROR');
                throw new Exception("El certificado configurado ($targetCert) no se encuentra activo en Sinube.");
            }

            $certNode = $certNodes[0];

            // 2. El foliador se debe comparar vs la serie de la sucursal
            $foliadorNodes = $certNode->xpath("foliador[@serie='$targetSerie']");
            if (!$foliadorNodes || count($foliadorNodes) === 0) {
                $this->log("Serie '$targetSerie' no encontrada para el certificado '$targetCert'.", 'ERROR');
                throw new Exception("La serie '$targetSerie' no está autorizada para este certificado en Sinube.");
            }

            $foliador = $foliadorNodes[0];
            $serie = (string)($foliador['serie'] ?? '');
            $folioActual = (int)($foliador['folioActual'] ?? 0);

            $this->log("Folio obtenido exitosamente: Serie=$serie, FolioActual=$folioActual", 'INFO');

            return [
                'serie' => $serie,
                'folioActual' => $folioActual
            ];

        } catch (Throwable $e) {
            $this->log("Error procesando XML de Sinube: " . $e->getMessage(), 'ERROR');
            throw new Exception("Error al procesar datos de foliación: " . $e->getMessage());
        }
    }

    /**
     * Registra eventos en el archivo de log.
     */
    public function log(string $message, string $level = 'INFO'): void {
        $date = date('Y-m-d H:i:s');
        $formatted = "[$date] [$level] $message" . PHP_EOL;
        @file_put_contents($this->logPath, $formatted, FILE_APPEND);
    }
}
