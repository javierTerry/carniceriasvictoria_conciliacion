<?php
declare(strict_types=1);

namespace App\Services\SiNube;

use RuntimeException;
use Throwable;

// Asegurar carga de dependencias de Composer (GuzzleHttp)
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

/**
 * Servicio para consulta de clientes en la base de datos de SiNube (DbCliente).
 * Implementa cliente HTTP moderno vía GuzzleHttp\Client con envío POST y fallback cURL.
 */
class SiNubeClienteService
{
    private array $config;
    private string $logPath;

    /**
     * @param array<string, mixed>|null $config Configuración del endpoint de SiNube.
     * @param string|null $logPath Ruta dedicada para logs de auditoría.
     */
    public function __construct(?array $config = null, ?string $logPath = null)
    {
        if ($config !== null) {
            $this->config = $config;
        } else {
            $configFile = __DIR__ . '/../../config/sinube.php';
            if (file_exists($configFile)) {
                $this->config = require $configFile;
            } else {
                $this->config = [
                    'base_url' => 'https://getpost-dot-facturanube.appspot.com/getpost',
                    'tipo_consulta' => 3,
                    'empresa_rfc' => 'URE180429TM6-39',
                    'sucursal' => 'Matriz',
                    'usuario' => 'atencionsolucionesryj@gmail.com',
                    'pwd_comunicaciones' => 'proveedores',
                    'timeout' => 15,
                ];
            }
        }

        $this->logPath = $logPath ?? __DIR__ . '/../../logs/sinube_api.log';
        $logDir = dirname($this->logPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
    }

    /**
     * Consulta el IdCliente dinámicamente en SiNube a partir del RFC.
     * Realiza petición HTTP POST hacia el servlet /getpost de SiNube.
     *
     * @param string $rfc RFC del cliente receptor a consultar.
     * @return int Identificador numérico del cliente en SiNube.
     * @throws RuntimeException En caso de fallo de conexión, código HTTP != 200 o si el RFC no existe.
     */
    public function obtenerIdClientePorRfc(string $rfc): int
    {
        $rfcLimpio = mb_strtoupper(trim($rfc), 'UTF-8');
        if (empty($rfcLimpio)) {
            $this->registrarLog('ERROR', 'RFC vacío proporcionado para consulta de cliente en SiNube', [
                'rfc' => $rfc
            ]);
            throw new RuntimeException("El RFC proporcionado no es válido o se encuentra vacío.");
        }

        $baseUrl = (string) ($this->config['base_url'] ?? 'https://getpost-dot-facturanube.appspot.com/getpost');
        $cnsSql = "SELECT cliente AS IdCliente, rfcCliente, razonSocial, moneda FROM DbCliente WHERE rfcCliente = '{$rfcLimpio}'";

        // Parámetros a transmitir en el cuerpo de la petición POST (x-www-form-urlencoded)
        $formParams = [
            'tipo' => (int) ($this->config['tipo_consulta'] ?? 3),
            'emp' => (string) ($this->config['empresa_rfc'] ?? ''),
            'suc' => (string) ($this->config['sucursal'] ?? 'Matriz'),
            'usu' => (string) ($this->config['usuario'] ?? ''),
            'pas' => (string) ($this->config['pwd_comunicaciones'] ?? ''),
            'cns' => $cnsSql,
        ];

        // 1. Log informativo al iniciar la consulta
        $this->registrarLog('INFO', "Iniciando consulta POST de IdCliente en SiNube para RFC: {$rfcLimpio}", [
            'rfc' => $rfcLimpio,
            'url' => $baseUrl,
            'empresa' => $formParams['emp'],
            'sucursal' => $formParams['suc'],
            'usuario' => $formParams['usu'],
            'cns' => $cnsSql
        ]);

        // Ejecutar petición HTTP POST preferentemente con Guzzle
        if (class_exists(\GuzzleHttp\Client::class)) {
            $rawResponse = $this->ejecutarConGuzzle($baseUrl, $formParams, $rfcLimpio);
        } else {
            $rawResponse = $this->ejecutarConCurl($baseUrl, $formParams, $rfcLimpio);
        }

        // 2. Parser de la respuesta delimitada por '¬' y '|'
        $idCliente = $this->parseRespuestaSiNube($rawResponse, $rfcLimpio);

        $this->registrarLog('INFO', "IdCliente obtenido exitosamente en SiNube: {$idCliente} para RFC: {$rfcLimpio}", [
            'rfc' => $rfcLimpio,
            'idCliente' => $idCliente,
        ]);

        return $idCliente;
    }

    /**
     * Ejecuta la petición POST utilizando GuzzleHttp Client.
     *
     * @param string $url URL destino del endpoint de SiNube.
     * @param array<string, mixed> $formParams Parámetros del formulario.
     * @param string $rfc RFC para trazabilidad.
     * @return string Respuesta en bruto del servidor.
     * @throws RuntimeException
     */
    private function ejecutarConGuzzle(string $url, array $formParams, string $rfc): string
    {
        $timeout = (float) ($this->config['timeout'] ?? 15.0);

        try {
            $client = new \GuzzleHttp\Client([
                'timeout' => $timeout,
                'connect_timeout' => 10.0,
                'verify' => false,
                'http_errors' => false, // Capturar código HTTP manualmente
            ]);

            // Enviar POST con form_params (application/x-www-form-urlencoded)
            $response = $client->request('POST', $url, [
                'form_params' => $formParams,
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                    'User-Agent' => 'Victoria-Conciliacion/1.0',
                    'Accept' => '*/*',
                ],
            ]);

            $httpCode = $response->getStatusCode();
            $rawResponse = (string) $response->getBody();

            $this->registrarLog('DEBUG', "Respuesta recibida vía Guzzle HTTP POST (HTTP {$httpCode})", [
                'rfc' => $rfc,
                'http_code' => $httpCode,
                'raw_response' => $rawResponse,
            ]);

            if ($httpCode !== 200) {
                $errorMsg = "Respuesta no exitosa de SiNube getpost. Código HTTP: {$httpCode}";
                $this->registrarLog('ERROR', $errorMsg, [
                    'rfc' => $rfc,
                    'http_code' => $httpCode,
                    'raw_response' => $rawResponse,
                ]);
                throw new RuntimeException($errorMsg);
            }

            return $rawResponse;

        } catch (Throwable $e) {
            if ($e instanceof RuntimeException) {
                throw $e;
            }

            $errorMsg = "Error en cliente Guzzle al conectar con SiNube: " . $e->getMessage();
            $this->registrarLog('ERROR', $errorMsg, [
                'rfc' => $rfc,
                'exception' => get_class($e),
            ]);
            throw new RuntimeException($errorMsg, 0, $e);
        }
    }

    /**
     * Fallback con cURL nativo en modo POST (application/x-www-form-urlencoded).
     *
     * @param string $url URL destino.
     * @param array<string, mixed> $formParams Parámetros del formulario.
     * @param string $rfc RFC para trazabilidad.
     * @return string Respuesta en bruto del servidor.
     * @throws RuntimeException
     */
    private function ejecutarConCurl(string $url, array $formParams, string $rfc): string
    {
        $timeout = (int) ($this->config['timeout'] ?? 15);
        $postDataString = http_build_query($formParams, '', '&', PHP_QUERY_RFC1738);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postDataString,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'User-Agent: Victoria-Conciliacion/1.0',
            ],
        ]);

        $rawResponse = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        $this->registrarLog('DEBUG', "Respuesta recibida vía cURL HTTP POST (HTTP {$httpCode})", [
            'rfc' => $rfc,
            'http_code' => $httpCode,
            'curl_errno' => $curlErrno,
            'curl_error' => $curlError,
            'raw_response' => $rawResponse !== false ? $rawResponse : null,
        ]);

        if ($rawResponse === false || $curlErrno !== 0) {
            $errorMsg = "Error cURL de comunicación con SiNube (Errno {$curlErrno}): " . ($curlError ?: 'Sin respuesta');
            $this->registrarLog('ERROR', $errorMsg, [
                'rfc' => $rfc,
                'http_code' => $httpCode,
            ]);
            throw new RuntimeException($errorMsg);
        }

        if ($httpCode !== 200) {
            $errorMsg = "Respuesta no exitosa de SiNube getpost. Código HTTP: {$httpCode}";
            $this->registrarLog('ERROR', $errorMsg, [
                'rfc' => $rfc,
                'http_code' => $httpCode,
                'raw_response' => $rawResponse,
            ]);
            throw new RuntimeException($errorMsg);
        }

        return (string) $rawResponse;
    }

    /**
     * Parsea el string plano retornado por SiNube y extrae el IdCliente.
     *
     * Formato esperado:
     * "1|&NullSiNube;|IdCliente|Long|...¬467|HESC870321UY4|CHRISTIAN JAVIER HERNANDEZ|MXN"
     *
     * @param string $rawResponse Respuesta textual de SiNube.
     * @param string $rfc RFC consultado para trazabilidad.
     * @return int IdCliente numérico.
     * @throws RuntimeException Si el RFC no fue encontrado o la estructura es inválida.
     */
    private function parseRespuestaSiNube(string $rawResponse, string $rfc): int
    {
        $contenido = trim($rawResponse);

        if ($contenido === '') {
            $this->registrarLog('ERROR', "Respuesta vacía recibida de SiNube para RFC {$rfc}", [
                'rfc' => $rfc,
                'raw_response' => $rawResponse,
            ]);
            throw new RuntimeException("Respuesta vacía recibida del servidor SiNube.");
        }

        // Si la respuesta es el texto por defecto de la página sin procesar
        if ($contenido === 'SiNube getpost') {
            $this->registrarLog('ERROR', "El servidor SiNube respondió con la cabecera por defecto 'SiNube getpost'. Los parámetros de consulta no fueron procesados.", [
                'rfc' => $rfc,
                'raw_response' => $contenido,
            ]);
            throw new RuntimeException("El servicio de consulta SiNube no procesó la solicitud (respuesta: 'SiNube getpost').");
        }

        // Si la respuesta contiene error explícito de SiNube
        if (stripos($contenido, '<error>') !== false || stripos($contenido, 'error:') === 0) {
            $this->registrarLog('ERROR', "Error reportado por SiNube en consulta de cliente", [
                'rfc' => $rfc,
                'raw_response' => $contenido,
            ]);
            throw new RuntimeException("Error en SiNube al consultar cliente: " . strip_tags($contenido));
        }

        // Dividir por delimitador de metadatos y datos "¬"
        $bloques = explode('¬', $contenido);

        if (count($bloques) < 2 || trim($bloques[1]) === '') {
            $this->registrarLog('ERROR', "RFC '{$rfc}' no encontrado en SiNube (bloque de datos ausente tras delimitador '¬')", [
                'rfc' => $rfc,
                'raw_response' => $contenido,
            ]);
            throw new RuntimeException("El cliente con RFC '{$rfc}' no se encuentra registrado en SiNube.");
        }

        // Bloque de registros de datos
        $bloqueDatos = trim($bloques[1]);

        // Puede contener múltiples registros separados por saltos de línea; tomamos la primera fila
        $filas = preg_split('/\r\n|\r|\n/', $bloqueDatos);
        $primeraFila = trim($filas[0] ?? '');

        if ($primeraFila === '') {
            $this->registrarLog('ERROR', "Fila de registro vacía en SiNube para RFC {$rfc}", [
                'rfc' => $rfc,
                'bloque_datos' => $bloqueDatos,
            ]);
            throw new RuntimeException("El cliente con RFC '{$rfc}' no contiene datos válidos en SiNube.");
        }

        // Separar campos por delimitador "|"
        $campos = explode('|', $primeraFila);
        $idClienteRaw = trim($campos[0] ?? '');

        if (!is_numeric($idClienteRaw) || (int) $idClienteRaw <= 0) {
            $this->registrarLog('ERROR', "IdCliente inválido o no numérico en respuesta de SiNube: '{$idClienteRaw}'", [
                'rfc' => $rfc,
                'fila' => $primeraFila,
                'raw_response' => $contenido,
            ]);
            throw new RuntimeException("IdCliente retornado por SiNube no es un entero válido: '{$idClienteRaw}'.");
        }

        return (int) $idClienteRaw;
    }

    /**
     * Escribe una entrada estructurada en el canal de log dedicado.
     *
     * @param string $nivel INFO, DEBUG, WARNING, ERROR
     * @param string $mensaje Descripción del evento
     * @param array<string, mixed> $contexto Datos adicionales para trazabilidad
     */
    private function registrarLog(string $nivel, string $mensaje, array $contexto = []): void
    {
        $date = date('Y-m-d H:i:s');
        $contextStr = !empty($contexto) ? ' | Contexto: ' . json_encode($contexto, JSON_UNESCAPED_UNICODE) : '';
        $linea = "[{$date}] [{$nivel}] [SINUBE/CLIENTE_QUERY] {$mensaje}{$contextStr}" . PHP_EOL;
        @file_put_contents($this->logPath, $linea, FILE_APPEND);
    }
}
