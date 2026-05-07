<?php
/**
 * facturar_api.php
 * 1. Obtiene Serie y Folio de SINUBE.
 * 2. Envía XML dummy para proceso de facturación.
 */
session_start();
require_once "../config/config.php";
require_once "../classes/ProductMapper.php";

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? 1; // Fallback or read from session
$mov_id = $_POST['mov_id'] ?? '';
$monto = $_POST['monto'] ?? 0;
$metodo_pago = $_POST['metodo_pago'] ?? '';
$branch = $_POST['branch'] ?? '';
$forma_pago = $_POST['forma_pago'] ?? '03'; // Default to 03 if not provided
$metodo_pago_cfdi = $_POST['metodo_pago_cfdi'] ?? 'PUE';
$uso_cfdi = $_POST['uso_cfdi'] ?? 'G03';

if (empty($mov_id) || empty($branch)) {
    // For now, don't exit since vtapendente.js might not be sending it yet, but it should.
    // echo json_encode(['success' => false, 'message' => 'Datos insuficientes']);
    // exit;
}

$db_error = '';
$branchConn = null;
if (!empty($branch)) {
    $branchesConfigs = getBranchesConfig();
    if (isset($branchesConfigs[$branch])) {
        $config = $branchesConfigs[$branch];
        $branchConn = mysqli_connect($config['host'], $config['user'], $config['pass'], $config['db']);
        if ($branchConn) {
            mysqli_set_charset($branchConn, "utf8");
        } else {
            $db_error = 'Error de conexión a la BD de sucursal';
        }
    } else {
        $db_error = 'Sucursal no válida';
    }
}

// Configuración ruta logs
$log_dir = __DIR__ . "/../logs";
$log_file = $log_dir . "/facturacion_individual.log";

// ---------------------------------------------------------
// PASO 1: Obtener Certificado (Folio y Serie por Sucursal)
// ---------------------------------------------------------
$target_serie = $branchSeriesMap[$branch] ?? '';

try {
    $sinube = new SinubeHelper($log_file);
    $folioData = $sinube->getFolioActual($api_url_cert, $api_no_certificado, $target_serie);
    
    $serie = $folioData['serie'];
    $folio = (string)($folioData['folioActual'] + 1); // Incrementar para la nueva factura

    error_log("[" . date('Y-m-d H:i:s') . "] Folio Obtenido: Serie=$serie, Folio=$folio \n", 3, $log_file);
} catch (Throwable $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] Error en PASO 1: " . $e->getMessage() . "\n", 3, $log_file);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

// ---------------------------------------------------------
// PASO 2: Armar XML Dinámico y enviar a Sinube
// ---------------------------------------------------------

// Decodificar items del ticket (vienen del JS por POST)
// ---------------------------------------------------------
// PASO 2: Armar XML Dinámico y enviar a Sinube
$ticket_items = $_POST['items'] ?? [];

// Nodo Receptor dinámico según selección de cliente
$rfc_receptor = $_POST['rfc_receptor'] ?? "SIN RFC";
$razonSocial = $_POST['razon_social'] ?? "SIN RAZON SOCIAL";
$usoCFDI = $_POST['uso_cfdi'] ?? "G03";
$regimenFiscal = $_POST['regimen_fiscal'] ?? "612";
$esPersonaFisica = $_POST['es_persona_fisica'] ?? "1";
$nombre = $_POST['nombre'] ?? "";
$apellidoPaterno = $_POST['ap_paterno'] ?? "";
$codigoPostal = $_POST['codigo_postal'] ?? "00000";


// Cálculos Globales (IVA 16%)
$totalGlobal = (float)$monto; // El monto recibido incluye IVA
$subtotalGlobal = $totalGlobal;//round($totalGlobal / 1.16, 2);
$ivaGlobal = round($totalGlobal - $subtotalGlobal, 2);
$msTime = round(microtime(true) * 1000); // Tiempo actual en milisegundos
$tipoIVA = "IVA 0%";
$porcentajeIVA = "16";



$conceptos_xml = "";
$mapper = new ProductMapper($conexion_gen);

foreach ($ticket_items as $item) {
    $desc_original = $item['name'] ?? 'Producto General';
    $desc = htmlspecialchars($desc_original, ENT_XML1, 'UTF-8');
    
    // Buscar en el catálogo de productos (arts)
    $productData = $mapper->findByDescription($desc_original);
    
    if (!$productData) {
        $msg = "El producto '{$desc_original}'  no esta en el catalogo de productos .";
        error_log("[" . date('Y-m-d H:i:s') . "] Error: $msg\n", 3, $log_file);
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }

    $productoSAT = $productData['clave_sat'] ?? '01010101';
    $unidadSinube = !empty($productData['unidad']) ? $productData['unidad'] : 'PIEZA';
    $unidadSAT = !empty($productData['unidad_sat']) ? $productData['unidad_sat'] : 'H87';

    $qty = (float)($item['qty'] ?? 1);
    if ($qty <= 0) $qty = 1;

    $qty = round($qty, 4); 
    
    $totalItem = (float)($item['amount'] ?? 0); // Valor Bruto del ticket (ya incluye IVA)
    $priceItem = (float)($item['price'] ?? 0);  // Precio Bruto del ticket
    
    $ivaItemCalculado = "0";
    $valUnitarioItem = round($priceItem, 4); 
    
    $conceptos_xml .= <<<XML
       <Concepto productoSinube="{$productoSAT}" productoSAT="{$productoSAT}" descripcion="{$desc}" cantidad="{$qty}" unidadSinube="{$unidadSinube}" unidadSAT="{$unidadSAT}" valorUnitario="{$valUnitarioItem}" descuento="0" tipoIVA="{$tipoIVA}" montoBaseIVA="{$totalItem}" montoIVA="{$ivaItemCalculado}" importe="{$totalItem}" subtotalDet="{$totalItem}" objetoImp="02" />
XML;
}

$receptor =null;

if ($esPersonaFisica == "1") {
    $receptor = <<<XML
        <Receptor rfc="{$rfc_receptor}" razonSocial="{$razonSocial}" usoCFDI="{$uso_cfdi}" esPersonaFisica="{$esPersonaFisica}" regimenFiscal="{$regimenFiscal}" nombre="{$nombre}" apellidoPaterno="{$apellidoPaterno}"/>
        <ReceptorDireccion pais="MEX" codigoPostal="{$codigoPostal}" ></ReceptorDireccion>
    XML;
} else {
    $receptor = <<<XML
        <Receptor rfc="{$rfc_receptor}" razonSocial="{$razonSocial}" usoCFDI="{$uso_cfdi}" esPersonaFisica="{$esPersonaFisica}" regimenFiscal="{$regimenFiscal}"/>
        <ReceptorDireccion pais="MEX" codigoPostal="{$codigoPostal}" ></ReceptorDireccion>
    XML;
}



$xml_payload = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<Comprobante exportacion="01" version="CFDI 4.0" sistema="OBRADORCARNICERIA" generar="Factura" rfcEmisor="{$api_rfc_emisor}" sucursal="Matriz" codigoReporte="CFDI 4.0 - CON IVA - SINUBE-COPIA" 
    permiteAgregarProductosNoInv="1" nomArchivoDescarga="TCK-{$mov_id}-{$serie}-{$folio}" noCertificado="{$api_no_certificado}" serie="{$serie}" folio="{$folio}"  
    formaDePago="{$forma_pago}" condicionesDePago="CONTADO" fechaPagoProbable="{$msTime}" metodoDePago="{$metodo_pago_cfdi}" subtotal="{$subtotalGlobal}" descuento="0" porcentajeIVA="{$porcentajeIVA}" montoIVA="0" 
    total="{$totalGlobal}" monedaSinube="MXN" monedaSAT="MXN" difZonaHoraria="-06">
   {$receptor}
   <Conceptos>
{$conceptos_xml}   </Conceptos>
</Comprobante>
XML;

$url_envio = $api_url_envio;

// Guardar payload en el log para visualización
error_log("[" . date('Y-m-d H:i:s') . "] XML GENERADO (Folio: $folio, MovID: $mov_id):\n$xml_payload\n-----------------------\n", 3, $log_file);

$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, $url_envio);
curl_setopt($ch2, CURLOPT_POST, 1);
curl_setopt($ch2, CURLOPT_POSTFIELDS, $xml_payload);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    'Content-Type: text/xml',
    'Content-Length: ' . strlen($xml_payload)
]);
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);

$response_envio = curl_exec($ch2);
$http_code_envio = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
$curl_error_envio = curl_error($ch2);
curl_close($ch2);

if ($http_code_envio == 200) {
    error_log("[" . date('Y-m-d H:i:s') . "] Éxito envío SINUBE (Folio: $folio, Serie: $serie)\n", 3, $log_file);

    try {
        libxml_use_internal_errors(true);
        $xml_obj = simplexml_load_string($response_envio);
        if ($xml_obj === false) {
            throw new Exception("XML de respuesta malformado.");
        }

        //error_log("[" . date('Y-m-d H:i:s') . "] $xml_obj \n", 3, $log_file);
        error_log(print_r($xml_obj,true), 3, $log_file);
        
        
        $error_matches = $xml_obj->xpath("error");
        
        error_log(print_r($error_matches,true), 3, $log_file);

        if (isset($error_matches[0]) && !empty((string)$error_matches[0])) {
            throw new Exception("SINUBE devolvió un error: " . (string)$error_matches[0]);
        }

        $xml_matches = $xml_obj->xpath("/Respuesta/xml");
        $pdf_matches = $xml_obj->xpath("/Respuesta/pdf");
        $uuid_matches = $xml_obj->xpath("/Respuesta/UUID");
        
        $link_xml = isset($xml_matches[0]) ? (string)$xml_matches[0] : '';
        $link_pdf = isset($pdf_matches[0]) ? (string)$pdf_matches[0] : '';
        $uuid     = isset($uuid_matches[0]) ? (string)$uuid_matches[0] : '';
        
        if (empty($uuid)) {
            throw new Exception("La respuesta fue exitosa pero no incluye UUID válido.");
        }
     
    } catch (Throwable $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] Error extracción links: " . $e->getMessage() . "\n", 3, $log_file);
        echo json_encode(['success' => false, 'message' => "Error al procesar respuesta de SINUBE: " . $e->getMessage()]);
        exit;
    }


    error_log(print_r(" Se inicia insert",true), 3, $log_file);
    // Intentar guardar en base de datos si tenemos la conexión
    $db_message = 'No se guardó en BD (Faltan parámetros de sucursal o mov_id)';
    if ($branchConn && !empty($mov_id)) {
        $sql = "INSERT INTO facturas (mov_id, uuid, monto, metodo_pago, usuario_id, serie, folio, xml_url, pdf_url, estatus, estado) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'Activa')";
        $stmt = mysqli_prepare($branchConn, $sql);
        error_log(__FILE__."-".__LINE__, 3, $log_file);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ssdssssss", $mov_id, $uuid, $monto, $metodo_pago, $user_id, $serie, $folio, $link_xml, $link_pdf);
            if (mysqli_stmt_execute($stmt)) {
                $db_message = 'Factura guardada en la base de datos correctamente';
            } else {
                $db_message = 'Error al insertar en la tabla facturas: ' . mysqli_stmt_error($stmt);
                error_log("[" . date('Y-m-d H:i:s') . "] $db_message\n", 3, $log_file);
            }
            mysqli_stmt_close($stmt);
        } else {
            error_log(__FILE__."-".__LINE__, 3, $log_file);
            $db_message = 'Error al preparar la consulta de facturas';
        }
        mysqli_close($branchConn);
    } else if ($db_error) {
        error_log(__FILE__."-".__LINE__, 3, $log_file);
        $db_message = $db_error;
    }

    error_log(__FILE__."-".__LINE__, 3, $log_file);

    echo json_encode([
        'success' => true,
        'message' => 'Factura procesada correctamente en SINUBE.',
        'db_message' => $db_message,
        'data' => [
            'serie' => $serie,
            'folio' => $folio,
            'xml' => $link_xml,
            'pdf' => $link_pdf,
            'uuid' => $uuid,
            'api_response' => $response_envio
        ]
    ]);
} else {
    $error_msg = "[" . date('Y-m-d H:i:s') . "] Error envío SINUBE. Code: $http_code_envio, Msg: $curl_error_envio, Response: $response_envio\n";
    error_log($error_msg, 3, $log_file);
    echo json_encode([
        'success' => false,
        'message' => 'Error al enviar XML a SINUBE.',
        'details' => $response_envio
    ]);
}
?>
