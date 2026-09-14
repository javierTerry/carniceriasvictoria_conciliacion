<?php
declare(strict_types=1);

/**
 * Configuración de integración con API externa SiNube (Facturanube) y helpers de consulta.
 * Soporta parametrización para entornos DEV y PRD.
 */

if (!function_exists('getSinubeConfig')) {
    /**
     * Retorna los parámetros de configuración de SiNube según el entorno actual.
     *
     * @param string|null $env Entorno solicitado ('DEV' o 'PRD'). Si es null, se detecta automáticamente.
     * @return array<string, mixed>
     */
    function getSinubeConfig(?string $env = null): array
    {
        global $ambiente;

        // Detección de entorno: DEV o PRD
        if ($env === null) {
            $currentEnv = strtoupper((string) (getenv('APP_ENV') ?: ($ambiente ?? 'DEV')));
            $env = in_array($currentEnv, ['PRD', 'PROD', 'PRODUCTION'], true) ? 'PRD' : 'DEV';
        } else {
            $env = strtoupper($env);
        }

        $config = [
            'DEV' => [
                'base_url'           => 'https://getpost-dot-facturanube.appspot.com/getpost',
                'tipo_consulta'      => 3,
                'empresa_rfc'        => 'URE180429TM6-39',
                'sucursal'           => 'Matriz',
                'usuario'            => 'atencionsolucionesryj@gmail.com',
                'pwd_comunicaciones' => 'proveedores',
                'timeout'            => 15,
            ],
            'PRD' => [
                'base_url'           => 'https://getpost-dot-facturanube.appspot.com/getpost',
                'tipo_consulta'      => 3,
                'empresa_rfc'        => 'URE180429TM6-39',
                'sucursal'           => 'Matriz',
                'usuario'            => 'atencionsolucionesryj@gmail.com',
                'pwd_comunicaciones' => 'proveedores',
                'timeout'            => 15,
            ],
        ];

        return $config[$env] ?? $config['DEV'];
    }
}

if (!function_exists('obtenerIdClienteSiNube')) {
    /**
     * Consulta dinámicamente el IdCliente en la base de datos de SiNube (DbCliente) a partir del RFC.
     *
     * @param string $rfc RFC del cliente receptor a consultar.
     * @return int Identificador numérico del cliente en SiNube.
     * @throws RuntimeException En caso de fallo de conexión, código HTTP != 200 o si el RFC no fue encontrado.
     */
    function obtenerIdClienteSiNube(string $rfc): int
    {
        require_once __DIR__ . '/../classes/SiNube/autoload.php';

        $service = new \App\Services\SiNube\SiNubeClienteService(getSinubeConfig());
        return $service->obtenerIdClientePorRfc($rfc);
    }
}

return getSinubeConfig();
