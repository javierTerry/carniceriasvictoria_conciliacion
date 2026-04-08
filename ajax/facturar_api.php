<?php
/**
 * facturar_api.php
 * 1. Obtiene Serie y Folio de Facturanube.
 * 2. Envía XML dummy para proceso de facturación.
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    // Validar sesión si es necesario en producción
    // echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    // exit;
}

// Configuración ruta logs
$log_dir = __DIR__ . "/../logs";
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0755, true);
}
$log_file = $log_dir . "/factura_error.log";

// ---------------------------------------------------------
// PASO 1: Obtener Certificado (Folio y Serie)
// ---------------------------------------------------------
$url_cert = "http://ep-dot-facturanube.appspot.com/blob?par=dGlwbz0xMQplbXA9VVJFMTgwNDI5VE02LTM5CnN1Yz1NYXRyaXoKdXN1PWF0ZW5jaW9uc29sdWNpb25lc3J5akBnbWFpbC5jb20KcHdkPXByb3ZlZWRvcmVzCnNpcz1PQlJBRE9SQ0FSTklDRVJJQQ==";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url_cert);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response_cert = curl_exec($ch);
$http_code_cert = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error_cert = curl_error($ch);
curl_close($ch);

if ($response_cert === false || $http_code_cert != 200) {
    $error_msg = "[" . date('Y-m-d H:i:s') . "] Error cURL obteniendo certificado. Code: $http_code_cert, Msg: $curl_error_cert\n";
    error_log($error_msg, 3, $log_file);
    echo json_encode(['success' => false, 'message' => 'Error al consultar folio/serie en Facturanube.']);
    exit;
}

$target_cert = "30001000000500003441";
try {
    libxml_use_internal_errors(true);
    $xml_obj = simplexml_load_string($response_cert);
    if ($xml_obj === false) {
        throw new Exception("XML de certificado malformado.");
    }

    $nodes = $xml_obj->xpath("//*[@noCertificado='$target_cert']");
    if (!$nodes) {
        throw new Exception("Certificado $target_cert no encontrado.");
    }

    $node = $nodes[0];
    if (isset($node->foliador)) {
        $serie = (string) ($node->foliador['serie'] ?? '');
        $folio = (string) ($node->foliador['folioActual'] ?? '');
        $folio++;
    } else {
        throw new Exception("Datos de foliación incompletos valida con tu Administrador.");
    }

    if (empty($serie) || empty($folio)) {
        throw new Exception("Datos de foliación incompletos (Serie: $serie, Folio: $folio).");
    }

} catch (Throwable $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] Error extracción: " . $e->getMessage() . "\n", 3, $log_file);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

// ---------------------------------------------------------
// PASO 2: Armar XML Dummy y enviar a Sinube
// ---------------------------------------------------------
$xml_dummy = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<Comprobante exportacion="01" version="CFDI 4.0" sistema="OBRADORCARNICERIA" generar="Factura" rfcEmisor="URE180429TM6-39" sucursal="Matriz" codigoReporte="CFDI 4.0 - CON IVA - SINUBE" permiteAgregarProductosNoInv="1" nomArchivoDescarga="URE180429TM6_FACT-58" noCertificado="30001000000500003441" serie="{$serie}" folio="{$folio}" formaDePago="03" condicionesDePago="A 30 DIAS" fechaPagoProbable="1774995997474" metodoDePago="PUE" subtotal="700.00" descuento="0" porcentajeIVA="16" montoIVA="112.00" total="812.00" monedaSinube="MXN" monedaSAT="MXN" difZonaHoraria="-06">
   <Receptor rfc="OHM191218EH7" razonSocial="OBRADOR HNOS MIRANDA" usoCFDI="G03" esPersonaFisica="0" regimenFiscal="601" cliente="2"/>
   <ReceptorDireccion pais="MEX" codigoPostal="54030" ></ReceptorDireccion>
   <Conceptos>
       <Concepto productoSinube="0000VAVC1908001" productoSAT="50202206" descripcion="Whisky Chivas Regal 12 anos Escoces 750 ml" cantidad="1" unidadSinube="PIEZA" unidadSAT="H87" valorUnitario="700.00" descuento="0" tipoIVA="Causa IVA" montoBaseIVA="700.00" montoIVA="112.00" importe="700.00" subtotalDet="700.00" objetoImp="02" />
   </Conceptos>
</Comprobante>
XML;

$url_envio = "http://ep-dot-facturanube.appspot.com/blob?par=dGlwbz00CmVtcD1VUkUxODA0MjlUTTYtMzkKc3VjPU1hdHJpegp1c3U9YXRlbmNpb25zb2x1Y2lvbmVzcnlqQGdtYWlsLmNvbQpwd2Q9cHJvdmVlZG9yZXM=";

$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, $url_envio);
curl_setopt($ch2, CURLOPT_POST, 1);
curl_setopt($ch2, CURLOPT_POSTFIELDS, $xml_dummy);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    'Content-Type: text/xml',
    'Content-Length: ' . strlen($xml_dummy)
]);
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);

$response_envio = curl_exec($ch2);
$http_code_envio = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
$curl_error_envio = curl_error($ch2);
curl_close($ch2);


if ($http_code_envio == 200) {
    error_log("[" . date('Y-m-d H:i:s') . "] Éxito envío Facturanube (Folio: $folio, Serie: $serie)\n", 3, $log_file);

    try {
        libxml_use_internal_errors(true);
        $xml_obj = simplexml_load_string($response_envio);
        if ($xml_obj === false) {
            throw new Exception("XML de respuesta malformado.");
        }
        
        $xml_matches = $xml_obj->xpath("/Respuesta/xml");
        $pdf_matches = $xml_obj->xpath("/Respuesta/pdf");
        
        $link_xml = isset($xml_matches[0]) ? (string)$xml_matches[0] : '';
        $link_pdf = isset($pdf_matches[0]) ? (string)$pdf_matches[0] : '';
     
    } catch (Throwable $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] Error extracción links: " . $e->getMessage() . "\n", 3, $log_file);
        echo json_encode(['success' => false, 'message' => "Error al procesar respuesta de Facturanube: " . $e->getMessage()]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Factura procesada correctamente en Facturanube.',
        'data' => [
            'serie' => $serie,
            'folio' => $folio,
            'xml' => $link_xml,
            'pdf' => $link_pdf,
            'api_response' => $response_envio
        ]
    ]);
} else {
    $error_msg = "[" . date('Y-m-d H:i:s') . "] Error envío Facturanube. Code: $http_code_envio, Msg: $curl_error_envio, Response: $response_envio\n";
    error_log($error_msg, 3, $log_file);
    echo json_encode([
        'success' => false,
        'message' => 'Error al enviar XML a Facturanube.',
        'details' => $response_envio
    ]);
}
?>
