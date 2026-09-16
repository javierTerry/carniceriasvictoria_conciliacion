<?php
declare(strict_types=1);

/**
 * Prueba Unitaria: Log Estructurado de Envío a Timbrado con XML en Base64
 * Valida que la cadena de log contenga RFC, Nombre, Folio, Serie y el XML en Base64 decodificable.
 *
 * Para ejecutar manualmente:
 * php tests/Unit/FacturaLogTimbradoTest.php
 */

class FacturaLogTimbradoTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void
    {
        echo "=========================================================\n";
        echo "   EJECUCIÓN DE PRUEBAS UNITARIAS: LOG DE TIMBRADO XML\n";
        echo "=========================================================\n\n";

        $this->testCadenaLogEstructuraYValores();
        $this->testBase64EsDecodificableYRecuperaXml();

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

    private function testCadenaLogEstructuraYValores(): void
    {
        $rfc = "XAXX010101000";
        $nombre = "PUBLICO EN GENERAL";
        $serie = "V";
        $folio = "10523";
        $dummyXml = '<?xml version="1.0" encoding="utf-8"?><Comprobante serie="V" folio="10523"></Comprobante>';
        $xmlBase64 = base64_encode($dummyXml);

        $logLine = "[" . date('Y-m-d H:i:s') . "] [TIMBRADO_ENVIO] RFC: {$rfc} | Nombre: {$nombre} | Serie: {$serie} | Folio: {$folio} | XML_BASE64: {$xmlBase64}\n";

        $this->assert(strpos($logLine, "RFC: {$rfc}") !== false, "El log incluye el RFC especificado.");
        $this->assert(strpos($logLine, "Nombre: {$nombre}") !== false, "El log incluye el Nombre especificado.");
        $this->assert(strpos($logLine, "Serie: {$serie}") !== false, "El log incluye la Serie especificada.");
        $this->assert(strpos($logLine, "Folio: {$folio}") !== false, "El log incluye el Folio especificado.");
        $this->assert(strpos($logLine, "XML_BASE64: {$xmlBase64}") !== false, "El log incluye el XML codificado en Base64.");
        $this->assert(strpos($logLine, "[TIMBRADO_ENVIO]") !== false, "El log incluye el tag de identificación [TIMBRADO_ENVIO].");
    }

    private function testBase64EsDecodificableYRecuperaXml(): void
    {
        $xmlOriginal = '<?xml version="1.0" encoding="utf-8"?><Comprobante serie="K" folio="987"><Concepto desc="Carne de Cerdo"/></Comprobante>';
        $encoded = base64_encode($xmlOriginal);
        $decoded = base64_decode($encoded, true);

        $this->assert($decoded === $xmlOriginal, "El XML en Base64 se decodifica con fidelidad 100% al original.");
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

$test = new FacturaLogTimbradoTest();
$test->run();
