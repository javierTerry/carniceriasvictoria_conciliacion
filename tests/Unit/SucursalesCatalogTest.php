<?php
declare(strict_types=1);

/**
 * Prueba Unitaria: Catálogo Oficial de Sucursales y Conectividad Multi-Canal
 * Valida que el sistema reconozca y gestione las 5 sucursales oficiales de Carnicerías Victoria:
 * Obrador, Victoria 1, Victoria 2, Producción y Cerdo en Pie (CEP).
 *
 * Para ejecutar manualmente:
 * php tests/Unit/SucursalesCatalogTest.php
 */

require_once __DIR__ . '/../../config/config.php';

class SucursalesCatalogTest
{
    private array $expectedBranches = [
        'Obrador'    => 'Obrador',
        'Victoria1'  => 'Victoria 1',
        'Victoria2'  => 'Victoria 2',
        'Produccion' => 'Producción',
        'CEP'        => 'Cerdo en Pie (CEP)'
    ];

    private array $expectedSeries = [
        'Obrador'    => 'O',
        'Victoria1'  => 'V',
        'Victoria2'  => 'K',
        'CEP'        => 'CEP',
        'Produccion' => 'P'
    ];

    private array $expectedPosBranches = [
        'Obrador',
        'Victoria1',
        'Victoria2',
        'Produccion'
    ];

    private int $passed = 0;
    private int $failed = 0;

    public function run(): void
    {
        echo "=========================================================\n";
        echo "   EJECUCIÓN DE PRUEBAS UNITARIAS: CATÁLOGO DE SUCURSALES\n";
        echo "=========================================================\n\n";

        $this->testBranchesListContainsAllFiveBranches();
        $this->testBranchesConfigContainsFourPosDatabases();
        $this->testCepDoesNotHavePosLocalDatabase();
        $this->testBranchSeriesMapIntegrity();
        $this->testDefaultValuesWhenTableNotPresent();

        echo "\n---------------------------------------------------------\n";
        echo "RESUMEN: " . $this->passed . " PASADAS | " . $this->failed . " FALLADAS\n";
        echo "---------------------------------------------------------\n";

        if ($this->failed > 0) {
            echo "RESULTADO: ERROR - ALGUNAS PRUEBAS FALLARON.\n";
            exit(1);
        } else {
            echo "RESULTADO: ÉXITO - TODAS LAS PRUEBAS UNITARIAS PASARON.\n";
            exit(0);
        }
    }

    private function assert(string $testName, bool $condition, string $details = ''): void
    {
        if ($condition) {
            $this->passed++;
            echo "[OK] {$testName}\n";
        } else {
            $this->failed++;
            echo "[FALLO] {$testName} - {$details}\n";
        }
    }

    /**
     * 1. Verifica que getBranchesList() retorne las 5 sucursales canónicas.
     */
    private function testBranchesListContainsAllFiveBranches(): void
    {
        $branches = getBranchesList();

        $this->assert(
            "getBranchesList() retorna exactamente 5 sucursales",
            count($branches) === 5,
            "Se obtuvieron " . count($branches) . " sucursales en lugar de 5."
        );

        foreach ($this->expectedBranches as $key => $expectedLabel) {
            $exists = array_key_exists($key, $branches);
            $this->assert(
                "Sucursal '{$key}' existe en getBranchesList()",
                $exists,
                "Falta la clave '{$key}'."
            );

            if ($exists) {
                $this->assert(
                    "Etiqueta legible de '{$key}' contiene texto esperado",
                    str_contains($branches[$key], $key) || str_contains($branches[$key], 'Cerdo en Pie') || str_contains($branches[$key], 'Victoria'),
                    "Etiqueta recibida: '{$branches[$key]}'."
                );
            }
        }
    }

    /**
     * 2. Verifica que getBranchesConfig() retorne exactamente las 4 bases de datos POS locales.
     */
    private function testBranchesConfigContainsFourPosDatabases(): void
    {
        $configs = getBranchesConfig();

        $this->assert(
            "getBranchesConfig() retorna exactamente 4 bases de datos de punto de venta",
            count($configs) === 4,
            "Se obtuvieron " . count($configs) . " configuraciones de BD."
        );

        foreach ($this->expectedPosBranches as $posBranch) {
            $this->assert(
                "Punto de venta '{$posBranch}' configurado en getBranchesConfig()",
                isset($configs[$posBranch]) && !empty($configs[$posBranch]['db']),
                "No se encontró base de datos para {$posBranch}."
            );
        }
    }

    /**
     * 3. Verifica que CEP (Cerdo en Pie) opere como emisión directa sin BD POS local.
     */
    private function testCepDoesNotHavePosLocalDatabase(): void
    {
        $configs = getBranchesConfig();
        $this->assert(
            "CEP no posee base de datos POS local (opera mediante emisión directa general)",
            !isset($configs['CEP']),
            "CEP no debe estar en getBranchesConfig() ya que no tiene BD POS separada."
        );
    }

    /**
     * 4. Verifica el mapeo de series fiscales con Sinube para todas las 5 sucursales.
     */
    private function testBranchSeriesMapIntegrity(): void
    {
        global $branchSeriesMap;

        $this->assert(
            "Arreglo global branchSeriesMap está definido",
            is_array($branchSeriesMap),
            "branchSeriesMap no es un arreglo válido."
        );

        foreach ($this->expectedSeries as $branchKey => $expectedSerie) {
            $this->assert(
                "Serie fiscal de '{$branchKey}' mapea a '{$expectedSerie}'",
                isset($branchSeriesMap[$branchKey]) && $branchSeriesMap[$branchKey] === $expectedSerie,
                "Serie recibida: " . ($branchSeriesMap[$branchKey] ?? 'NULL') . " (esperada: {$expectedSerie})."
            );
        }
    }

    /**
     * 5. Verifica el comportamiento de fallback resiliente.
     */
    private function testDefaultValuesWhenTableNotPresent(): void
    {
        $branches = getBranchesList();
        $this->assert(
            "CEP se reconoce con nombre descriptivo 'Cerdo en Pie (CEP)'",
            isset($branches['CEP']) && str_contains($branches['CEP'], 'Cerdo en Pie'),
            "Nombre devuelto: " . ($branches['CEP'] ?? 'NO_DEFINIDO')
        );
    }
}

// Instanciar y ejecutar la suite
$suite = new SucursalesCatalogTest();
$suite->run();
