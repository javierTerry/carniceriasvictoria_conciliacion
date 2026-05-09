<?php
session_start();
require_once "../config/config.php";
require_once "../classes/ProductMapper.php";

$monto = isset($_POST['monto']) ? $_POST['monto'] : 0;
$metodo_pago = isset($_POST['metodo_pago']) ? $_POST['metodo_pago'] : 'Efectivo';
$mov_id = isset($_POST['mov_id']) ? $_POST['mov_id'] : '';
$branch = isset($_POST['branch']) ? $_POST['branch'] : '';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
$forma_pago = mb_strtoupper(isset($_POST['forma_pago']) ? $_POST['forma_pago'] : '03', 'UTF-8');
$metodo_pago_cfdi = mb_strtoupper(isset($_POST['metodo_pago_cfdi']) ? $_POST['metodo_pago_cfdi'] : 'PUE', 'UTF-8');
$uso_cfdi = mb_strtoupper(isset($_POST['uso_cfdi']) ? $_POST['uso_cfdi'] : 'G03', 'UTF-8');
$cust_id = isset($_POST['cust_id']) ? $_POST['cust_id'] : 1;

if (empty($mov_id) || empty($branch)) {
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

$log_dir = __DIR__ . "/../logs";
$log_file = $log_dir . "/facturacion_individual.log";

$target_serie = isset($branchSeriesMap[$branch]) ? $branchSeriesMap[$branch] : '';

try {
    $sinube = new SinubeHelper($log_file);
    $folioData = $sinube->getFolioActual($api_url_cert, $api_no_certificado, $target_serie);

    $serie = $folioData['serie'];
    $folio = (string) ($folioData['folioActual'] + 1);

    error_log("[" . date('Y-m-d H:i:s') . "] Folio Obtenido: Serie=$serie, Folio=$folio \n", 3, $log_file);
} catch (Exception $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] Error en PASO 1: " . $e->getMessage() . "\n", 3, $log_file);
    echo json_encode(array('success' => false, 'message' => $e->getMessage()));
    exit;
}

$ticket_items = isset($_POST['items']) ? $_POST['items'] : array();

$rfc_receptor = mb_strtoupper(isset($_POST['rfc_receptor']) ? $_POST['rfc_receptor'] : "SIN RFC", 'UTF-8');
$razonSocial = mb_strtoupper(isset($_POST['razon_social']) ? $_POST['razon_social'] : "SIN RAZON SOCIAL", 'UTF-8');
$usoCFDI = mb_strtoupper(isset($_POST['uso_cfdi']) ? $_POST['uso_cfdi'] : "G03", 'UTF-8');
$regimenFiscal = mb_strtoupper(isset($_POST['regimen_fiscal']) ? $_POST['regimen_fiscal'] : "612", 'UTF-8');
$esPersonaFisica = isset($_POST['es_persona_fisica']) ? $_POST['es_persona_fisica'] : "1";
$nombre = mb_strtoupper(isset($_POST['nombre']) ? $_POST['nombre'] : "", 'UTF-8');
$apellidoPaterno = mb_strtoupper(isset($_POST['ap_paterno']) ? $_POST['ap_paterno'] : "", 'UTF-8');
$codigoPostal = str_pad(substr(preg_replace('/[^0-9]/', '', (string) (isset($_POST['codigo_postal']) ? $_POST['codigo_postal'] : "00000")), 0, 5), 5, "0", STR_PAD_LEFT);

$totalGlobal = (float) $monto;
$subtotalGlobal = $totalGlobal;
$ivaGlobal = 0;
$msTime = round(microtime(true) * 1000);
$tipoIVA = "IVA 0%";
$porcentajeIVA = "16";

$conceptos_xml = "";
$mapper = new ProductMapper($conexion_gen);

foreach ($ticket_items as $item) {
    $desc_original = isset($item['name']) ? $item['name'] : 'Producto General';
    $desc_upper = mb_strtoupper($desc_original, 'UTF-8');
    $desc = htmlspecialchars($desc_upper, ENT_XML1, 'UTF-8');

    $productData = $mapper->findByDescription($desc_original);

    if (!$productData) {
        $msg = "El producto '" . $desc_original . "' no esta en el catalogo.";
        error_log("[" . date('Y-m-d H:i:s') . "] Error: " . $msg . "\n", 3, $log_file);
        echo json_encode(array('success' => false, 'message' => $msg));
        exit;
    }

    $productoSAT = mb_strtoupper(isset($productData['clave_sat']) ? $productData['clave_sat'] : '01010101', 'UTF-8');
    $unidadSinube = mb_strtoupper(!empty($productData['unidad']) ? $productData['unidad'] : 'PIEZA', 'UTF-8');
    $unidadSAT = mb_strtoupper(!empty($productData['unidad_sat']) ? $productData['unidad_sat'] : 'H87', 'UTF-8');

    $qty = (float) (isset($item['qty']) ? $item['qty'] : 1);
    if ($qty <= 0)
        $qty = 1;
    $qty = round($qty, 4);

    $totalItem = (float) (isset($item['amount']) ? $item['amount'] : 0);
    $priceItem = (float) (isset($item['price']) ? $item['price'] : 0);

    $ivaItemCalculado = "0";
    $valUnitarioItem = round($priceItem, 4);

    $conceptos_xml .= <<<XML
       <Concepto productoSinube="{$productoSAT}" productoSAT="{$productoSAT}" descripcion="{$desc}" cantidad="{$qty}" unidadSinube="{$unidadSinube}" unidadSAT="{$unidadSAT}" valorUnitario="{$valUnitarioItem}" descuento="0" tipoIVA="{$tipoIVA}" montoBaseIVA="{$totalItem}" montoIVA="{$ivaItemCalculado}" importe="{$totalItem}" subtotalDet="{$totalItem}" objetoImp="02" />
XML;
}

$receptor = "";
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
error_log("[" . date('Y-m-d H:i:s') . "] XML GENERADO:\n" . $xml_payload . "\n", 3, $log_file);

$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, $url_envio);
curl_setopt($ch2, CURLOPT_POST, 1);
curl_setopt($ch2, CURLOPT_POSTFIELDS, $xml_payload);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_HTTPHEADER, array(
    'Content-Type: text/xml',
    'Content-Length: ' . strlen($xml_payload)
));
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);

$response_envio = curl_exec($ch2);
$http_code_envio = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

if ($http_code_envio == 200) {
    try {
        libxml_use_internal_errors(true);
        $xml_obj = simplexml_load_string($response_envio);
        $link_xml = '';
        $link_pdf = '';
        $uuid = '';


        if ($xml_obj) {
            error_log("[" . date('Y-m-d H:i:s') . "] " . print_r($xml_obj, true), 3, $log_file);

            // Validar Errores devueltos por el portal, incluso si es código 200
            $error_matches = $xml_obj->xpath("/Respuesta/error");
            if (!empty($error_matches) && trim((string) $error_matches[0]) !== '') {
                $error_msg = trim((string) $error_matches[0]);
                echo json_encode(array(
                    'success' => false,
                    'message' => "Error de facturación: " . $error_msg,
                    'details' => 'No se cambió estatus'
                ));
                if ($branchConn)
                    mysqli_close($branchConn);
                exit;
            }

            $xml_matches = $xml_obj->xpath("/Respuesta/xml");
            $pdf_matches = $xml_obj->xpath("/Respuesta/pdf");
            $uuid_matches = $xml_obj->xpath("/Respuesta/UUID");
            $fecha_matches = $xml_obj->xpath("/Respuesta/fechaFactura");

            $link_xml = isset($xml_matches[0]) ? (string) $xml_matches[0] : '';
            $link_pdf = isset($pdf_matches[0]) ? (string) $pdf_matches[0] : '';
            $uuid = isset($uuid_matches[0]) ? (string) $uuid_matches[0] : '';
            $fecha_factura = isset($fecha_matches[0]) ? (string) $fecha_matches[0] : '';

            $objTipoProceso = $xml_obj->xpath("/Respuesta/TipoProceso");
            $tipoProceso = isset($objTipoProceso[0]) ? strtoupper((string) $objTipoProceso[0]) : '';
            error_log("[" . date('Y-m-d H:i:s') . "] " . print_r(" $tipoProceso \n", true), 3, $log_file);

            if ($tipoProceso === "CONSULTA") {
                error_log("[" . date('Y-m-d H:i:s') . "] " . __LINE__ . "\n", 3, $log_file);

                if ($branchConn)
                    mysqli_close($branchConn);

                $mensaje = sprintf("Consulte a su administrador, La factura ya fue generada previamente. </br>Fecha:%s </br> Serie:%s y Folio:%s </br> UUID: %s ", $fecha_factura,$serie,$folio,$uuid );
                echo json_encode(array(
                    'success' => false,
                    'is_consulta' => true,
                    'message' => $mensaje,
                    'details' => "Consulta exitosa, sin afectar BD general",
                    'data' => array(
                        'serie' => $serie,
                        'folio' => $folio,
                        'xml' => $link_xml,
                        'pdf' => $link_pdf,
                        'uuid' => $uuid,
                        'fecha' => $fecha_factura
                    )
                ));
                exit;
            }
        } else {
            throw new Exception("Sin Objeto XML, Consuta a tu administrador.");

        }

        $db_message = 'No se guardó en BD';
        if ($conexion_gen && !empty($uuid)) {
            $sql = "INSERT INTO `" . $db_name_gen . "`.`facturas` (mov_id, uuid, monto, metodo_pago, usuario_id, serie, folio, xml_url, pdf_url, estatus, estado, cust_id, sucursal) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'Activa', ?, ?)";


            try {
                $stmt = mysqli_prepare($conexion_gen, $sql);
                error_log("[" . date('Y-m-d H:i:s') . "] " . __LINE__ . "\n", 3, $log_file);
            } catch (\Throwable $th) {
                //throw $th;
                error_log("[" . date('Y-m-d H:i:s') . "] " . $th->getMessage(), 3, $log_file);
            }

            if ($stmt) {
                mysqli_stmt_bind_param(
                    $stmt,
                    "ssssissssis",
                    $mov_id,
                    $uuid,
                    $totalGlobal,
                    $metodo_pago,
                    $user_id,
                    $serie,
                    $folio,
                    $link_xml,
                    $link_pdf,
                    $cust_id,
                    $branch
                );
                if (mysqli_stmt_execute($stmt)) {
                    $db_message = 'Factura guardada en la base de datos GENERAL correctamente';
                    error_log("[" . date('Y-m-d H:i:s') . "] " . $db_message, 3, $log_file);
                } else {
                    $db_message = 'Error al insertar en la tabla facturas GENERAL: ' . mysqli_stmt_error($stmt);
                    error_log("[" . date('Y-m-d H:i:s') . "] " . $db_message, 3, $log_file);
                }
                mysqli_stmt_close($stmt);
            } else {
                $db_message = 'Error al preparar la consulta en la BD GENERAL: ' . mysqli_error($conexion_gen);
                error_log("[" . date('Y-m-d H:i:s') . "] " . $db_message, 3, $log_file);
            }
        }

        if ($branchConn)
            mysqli_close($branchConn);

        echo json_encode(array(
            'success' => true,
            'message' => 'Factura procesada correctamente en SINUBE.',
            'db_message' => $db_message,
            'data' => array(
                'serie' => $serie,
                'folio' => $folio,
                'xml' => $link_xml,
                'pdf' => $link_pdf,
                'uuid' => $uuid
            )
        ));
    } catch (\Throwable $th) {
        //throw $th;
        echo json_encode(array(
            'success' => false,
            'message' => $th->getMessage(),
            'details' => "detalles"
        ));
    }

} else {
    echo json_encode(array(
        'success' => false,
        'message' => 'Error al enviar XML a SINUBE.',
        'details' => $response_envio
    ));
}
?>