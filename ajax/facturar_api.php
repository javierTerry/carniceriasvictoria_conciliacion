<?php
ob_start();
session_start();
require_once "../config/config.php";
require_once "../classes/ProductMapper.php";
require_once "../classes/Mailer.php";

header('Content-Type: application/json; charset=utf-8');

$log_dir = __DIR__ . "/../logs";
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0755, true);
}
$log_file = $log_dir . "/facturacion_individual.log";

if (!function_exists('log_checkpoint')) {
    function log_checkpoint(string $log_file, string $step, string $message, array $context = []): void
    {
        $ctx = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $line = "[" . date('Y-m-d H:i:s') . "] [CHECKPOINT:{$step}] {$message}{$ctx}\n";
        @error_log($line, 3, $log_file);
    }
}

if (!function_exists('send_json_and_exit')) {
    function send_json_and_exit(string $log_file, string $step, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        log_checkpoint($log_file, $step, "Enviando respuesta JSON al cliente", ['payload' => $payload]);
        if (ob_get_length()) {
            @ob_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo $json;
        exit;
    }
}

$action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';

// =========================================================================
// CHECKPOINT DESDE EL NAVEGADOR (FRONTEND JS LOGS)
// =========================================================================
if ($action === 'log_checkpoint') {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $step_js = isset($_POST['step']) ? trim((string)$_POST['step']) : 'JS_STEP';
    $msg_js = isset($_POST['message']) ? trim((string)$_POST['message']) : '';
    $ctx_js = isset($_POST['context']) ? $_POST['context'] : [];
    if (is_string($ctx_js)) {
        $decoded = json_decode($ctx_js, true);
        $ctx_js = is_array($decoded) ? $decoded : ['raw' => $ctx_js];
    }
    log_checkpoint($log_file, $step_js, $msg_js, is_array($ctx_js) ? $ctx_js : []);
    if (ob_get_length()) {
        @ob_clean();
    }
    echo json_encode(['success' => true]);
    exit;
}

// =========================================================================
// PRE-CHECK EXCLUSIVO DE LOS PRODUCTOS DEL TICKET A FACTURAR
// =========================================================================
if ($action === 'precheck_products') {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $branch_pre = isset($_POST['branch']) ? trim((string)$_POST['branch']) : '';
    $mov_id_pre = isset($_POST['mov_id']) ? trim((string)$_POST['mov_id']) : '';

    log_checkpoint($log_file, 'CP-01_PRECHECK_START', "Iniciando validación de productos del ticket", [
        'branch' => $branch_pre,
        'mov_id' => $mov_id_pre,
        'post_keys' => array_keys($_POST)
    ]);

    try {
        // Recopilar únicamente los nombres únicos de los productos del ticket enviado
        $raw_names = [];
        $source_list = [];

        if (!empty($_POST['ticket_products']) && is_array($_POST['ticket_products'])) {
            $source_list = $_POST['ticket_products'];
        } elseif (!empty($_POST['items']) && is_array($_POST['items'])) {
            $source_list = $_POST['items'];
        }

        foreach ($source_list as $it) {
            $n = is_array($it) ? ($it['name'] ?? ($it['descripcion'] ?? '')) : (string)$it;
            $n = trim((string)$n);
            if ($n !== '') {
                $raw_names[] = $n;
            }
        }

        // Si no se envió lista explícita de productos desde JS, consultar directamente los productos del ticket en la BD POS de la sucursal
        if (empty($raw_names) && !empty($branch_pre)) {
            $branchesConfigsPre = getBranchesConfig();
            if (isset($branchesConfigsPre[$branch_pre])) {
                $cfgPre = $branchesConfigsPre[$branch_pre];
                $connPre = @mysqli_connect($cfgPre['host'], $cfgPre['user'], $cfgPre['pass'], $cfgPre['db']);
                if ($connPre) {
                    mysqli_set_charset($connPre, "utf8mb4");
                    $group_id_pre = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;

                    if ($group_id_pre > 0) {
                        $sql_tck_items = "SELECT DISTINCT B.name
                                          FROM vtaitem A
                                          INNER JOIN art B ON B.id = A.art_id
                                          INNER JOIN vtahead VH ON VH.id = A.vta_id
                                          INNER JOIN groups_tickets_details GTD ON GTD.mov_id = VH.mov_id
                                          WHERE GTD.group_id = ? AND A.amount > 0";
                        $stmt_tck = mysqli_prepare($connPre, $sql_tck_items);
                        if ($stmt_tck) {
                            mysqli_stmt_bind_param($stmt_tck, "i", $group_id_pre);
                            if (mysqli_stmt_execute($stmt_tck)) {
                                $res_tck = mysqli_stmt_get_result($stmt_tck);
                                while ($row_tck = mysqli_fetch_assoc($res_tck)) {
                                    $pName = trim((string)($row_tck['name'] ?? ''));
                                    if ($pName !== '') {
                                        $raw_names[] = $pName;
                                    }
                                }
                            }
                            mysqli_stmt_close($stmt_tck);
                        }
                    } elseif ($mov_id_pre !== '') {
                        $sql_tck_items = "SELECT DISTINCT B.name
                                          FROM vtahead H
                                          INNER JOIN vtaitem A ON A.vta_id = H.id
                                          INNER JOIN art B ON B.id = A.art_id
                                          WHERE H.mov_id = ? AND A.amount > 0";
                        $stmt_tck = mysqli_prepare($connPre, $sql_tck_items);
                        if ($stmt_tck) {
                            mysqli_stmt_bind_param($stmt_tck, "s", $mov_id_pre);
                            if (mysqli_stmt_execute($stmt_tck)) {
                                $res_tck = mysqli_stmt_get_result($stmt_tck);
                                while ($row_tck = mysqli_fetch_assoc($res_tck)) {
                                    $pName = trim((string)($row_tck['name'] ?? ''));
                                    if ($pName !== '') {
                                        $raw_names[] = $pName;
                                    }
                                }
                            }
                            mysqli_stmt_close($stmt_tck);
                        }
                    }
                    mysqli_close($connPre);
                }
            }
        }

        $unique_names = array_values(array_unique($raw_names));

        log_checkpoint($log_file, 'CP-02_PRECHECK_LIST', "Productos únicos extraídos del ticket", [
            'branch' => $branch_pre,
            'mov_id' => $mov_id_pre,
            'count' => count($unique_names),
            'products' => $unique_names
        ]);

        if (empty($unique_names)) {
            $msg = "No se recibieron productos del ticket para validar.";
            send_json_and_exit($log_file, 'CP-03_PRECHECK_EMPTY', [
                'success' => false,
                'missing_products' => [],
                'message' => $msg
            ]);
        }

        $mapper = new ProductMapper($conexion_gen);
        $missing_products = [];

        foreach ($unique_names as $prod_name) {
            $productData = $mapper->findByDescription($prod_name);
            if (!$productData) {
                $missing_products[] = $prod_name;
                log_checkpoint($log_file, 'CP-02B_ITEM_MISSING', "Producto del ticket NO existe en catálogo arts", [
                    'mov_id' => $mov_id_pre,
                    'product' => $prod_name
                ]);
            } elseif (empty(trim((string)($productData['clave_sat'] ?? ''))) || empty(trim((string)($productData['unidad_sat'] ?? '')))) {
                $missing_products[] = $prod_name . " (sin Clave SAT o Unidad SAT)";
                log_checkpoint($log_file, 'CP-02B_ITEM_INCOMPLETE', "Producto del ticket incompleto en catálogo arts", [
                    'mov_id' => $mov_id_pre,
                    'product' => $prod_name,
                    'data' => $productData
                ]);
            } else {
                log_checkpoint($log_file, 'CP-02B_ITEM_OK', "Producto del ticket validado OK", [
                    'mov_id' => $mov_id_pre,
                    'product' => $prod_name,
                    'clave_sat' => $productData['clave_sat'],
                    'unidad_sat' => $productData['unidad_sat']
                ]);
            }
        }

        if (!empty($missing_products)) {
            $failed_list_str = implode(', ', $missing_products);
            $msg = "El ticket #{$mov_id_pre} contiene productos que no están registrados en el catálogo SAT: " . $failed_list_str;
            send_json_and_exit($log_file, 'CP-03_PRECHECK_FAIL', [
                'success' => false,
                'missing_products' => $missing_products,
                'message' => $msg
            ]);
        }

        send_json_and_exit($log_file, 'CP-03_PRECHECK_OK', [
            'success' => true,
            'validated_count' => count($unique_names),
            'ticket_products' => $unique_names,
            'message' => 'Productos del ticket validados correctamente.'
        ]);
    } catch (\Throwable $preEx) {
        send_json_and_exit($log_file, 'CP-03_PRECHECK_EXCEPTION', [
            'success' => false,
            'missing_products' => [],
            'message' => 'Error al validar los productos del ticket: ' . $preEx->getMessage()
        ]);
    }
}

$monto = isset($_POST['monto']) ? $_POST['monto'] : 0;
$metodo_pago = isset($_POST['metodo_pago']) ? $_POST['metodo_pago'] : 'Efectivo';
$mov_id = isset($_POST['mov_id']) ? $_POST['mov_id'] : '';
$branch = isset($_POST['branch']) ? $_POST['branch'] : '';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
$forma_pago = mb_strtoupper(isset($_POST['forma_pago']) ? $_POST['forma_pago'] : '03', 'UTF-8');
$metodo_pago_cfdi = mb_strtoupper(isset($_POST['metodo_pago_cfdi']) ? $_POST['metodo_pago_cfdi'] : 'PUE', 'UTF-8');
$uso_cfdi = mb_strtoupper(isset($_POST['uso_cfdi']) ? $_POST['uso_cfdi'] : 'G03', 'UTF-8');
$cust_id = isset($_POST['cust_id']) ? $_POST['cust_id'] : 1;
$observacion_raw = isset($_POST['observacion']) ? $_POST['observacion'] : '';
$observacion = htmlspecialchars(trim($observacion_raw), ENT_XML1, 'UTF-8');

$db_error = '';
$branchConn = null;
if (!empty($branch)) {
    $branchesConfigs = getBranchesConfig();
    if (isset($branchesConfigs[$branch])) {
        $config = $branchesConfigs[$branch];
        try {
            $branchConn = @mysqli_connect($config['host'], $config['user'], $config['pass'], $config['db']);
            if ($branchConn) {
                mysqli_set_charset($branchConn, "utf8mb4");
            } else {
                $db_error = 'Error de conexión a la BD de sucursal';
            }
        } catch (\Throwable $connEx) {
            $branchConn = null;
            $db_error = 'Excepción de conexión a la BD de sucursal: ' . $connEx->getMessage();
        }
    } else {
        $db_error = 'Sucursal no válida';
    }
}

$ticket_items = isset($_POST['items']) ? $_POST['items'] : array();

log_checkpoint($log_file, 'CP-04_FACTURA_START', "Iniciando proceso de timbrado individual", [
    'mov_id' => $mov_id,
    'branch' => $branch,
    'monto' => $monto,
    'metodo_pago' => $metodo_pago,
    'forma_pago' => $forma_pago,
    'items_count' => is_array($ticket_items) ? count($ticket_items) : 0
]);

// PASO 0: Pre-validar TODOS los productos de la factura ANTES de solicitar folio o timbrar en Sinube
$mapper = new ProductMapper($conexion_gen);
$missing_in_invoice = [];
$resolved_products = [];

foreach ($ticket_items as $idx => $item) {
    $desc_original = isset($item['name']) ? trim((string)$item['name']) : 'Producto General';
    $productData = $mapper->findByDescription($desc_original);

    if (!$productData) {
        $missing_in_invoice[] = $desc_original;
    } elseif (empty(trim((string)($productData['clave_sat'] ?? ''))) || empty(trim((string)($productData['unidad_sat'] ?? '')))) {
        $missing_in_invoice[] = $desc_original . " (sin Clave SAT o Unidad SAT)";
    } else {
        $resolved_products[$idx] = $productData;
    }
}

if (!empty($missing_in_invoice)) {
    $unique_missing = array_values(array_unique($missing_in_invoice));
    $msg = count($unique_missing) === 1
        ? "El producto '" . $unique_missing[0] . "' no está en el catálogo."
        : "Los siguientes productos no están en el catálogo: " . implode(', ', $unique_missing);
    if ($branchConn) {
        mysqli_close($branchConn);
    }
    send_json_and_exit($log_file, 'CP-05_PASO0_PRODUCTOS_FAIL', array(
        'success' => false,
        'missing_products' => $unique_missing,
        'message' => $msg
    ));
}

log_checkpoint($log_file, 'CP-05_PASO0_PRODUCTOS_OK', "Todos los productos de la factura fueron validados en PASO 0", [
    'mov_id' => $mov_id,
    'branch' => $branch
]);

$target_serie = isset($branchSeriesMap[$branch]) ? $branchSeriesMap[$branch] : '';

try {
    $sinube = new SinubeHelper($log_file);
    $folioData = $sinube->getFolioActual($api_url_cert, $api_no_certificado, $target_serie);

    $serie = $folioData['serie'];
    $folio = (string) ($folioData['folioActual'] + 1);

    log_checkpoint($log_file, 'CP-06_PASO1_FOLIO_OK', "Folio obtenido de Sinube", [
        'mov_id' => $mov_id,
        'serie' => $serie,
        'folio' => $folio
    ]);
} catch (\Throwable $e) {
    if ($branchConn) {
        mysqli_close($branchConn);
    }
    send_json_and_exit($log_file, 'CP-06_PASO1_FOLIO_FAIL', array(
        'success' => false,
        'message' => $e->getMessage()
    ));
}

$rfc_receptor = mb_strtoupper(isset($_POST['rfc_receptor']) ? $_POST['rfc_receptor'] : "SIN RFC", 'UTF-8');
$razonSocial = mb_strtoupper(isset($_POST['razon_social']) ? $_POST['razon_social'] : "SIN RAZON SOCIAL", 'UTF-8');
$usoCFDI = mb_strtoupper(isset($_POST['uso_cfdi']) ? $_POST['uso_cfdi'] : "G03", 'UTF-8');
$regimenFiscal = mb_strtoupper(isset($_POST['regimen_fiscal']) ? $_POST['regimen_fiscal'] : "612", 'UTF-8');
$esPersonaFisica = isset($_POST['es_persona_fisica']) ? $_POST['es_persona_fisica'] : "1";
$nombre = mb_strtoupper(isset($_POST['nombre']) ? $_POST['nombre'] : "", 'UTF-8');
$apellidoPaterno = mb_strtoupper(isset($_POST['ap_paterno']) ? $_POST['ap_paterno'] : "", 'UTF-8');
$codigoPostal = str_pad(substr(preg_replace('/[^0-9]/', '', (string) (isset($_POST['codigo_postal']) ? $_POST['codigo_postal'] : "00000")), 0, 5), 5, "0", STR_PAD_LEFT);

// Convert character '&' to '&amp;' if detected, avoiding double-encoding if already encoded
if (strpos($razonSocial, '&') !== false) {
    $razonSocial = str_replace('&amp;', '&', $razonSocial);
    $razonSocial = str_replace('&', '&amp;', $razonSocial);
}
if (strpos($nombre, '&') !== false) {
    $nombre = str_replace('&amp;', '&', $nombre);
    $nombre = str_replace('&', '&amp;', $nombre);
}

$totalGlobal = (float) $monto;
$subtotalGlobal = $totalGlobal;
$ivaGlobal = 0;
$msTime = round(microtime(true) * 1000);
$tipoIVA = "IVA 0%";
$porcentajeIVA = "16";

$conceptos_xml = "";

foreach ($ticket_items as $idx => $item) {
    $desc_original = isset($item['name']) ? trim((string)$item['name']) : 'Producto General';
    $desc_upper = mb_strtoupper($desc_original, 'UTF-8');
    $desc = htmlspecialchars($desc_upper, ENT_XML1, 'UTF-8');

    $productData = $resolved_products[$idx];

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
    total="{$totalGlobal}" monedaSinube="MXN" monedaSAT="MXN" difZonaHoraria="-06" observacion="{$observacion}">
   {$receptor}
   <Conceptos>
{$conceptos_xml}   </Conceptos>
</Comprobante>
XML;

$url_envio = $api_url_envio;

// Log estructurado para debug de timbrado con XML en Base64
$nombre_cliente_log = !empty($razonSocial) && $razonSocial !== 'SIN RAZON SOCIAL' ? $razonSocial : $nombre;
$xml_base64 = base64_encode($xml_payload);
$log_timbrado = "[" . date('Y-m-d H:i:s') . "] [TIMBRADO_ENVIO] RFC: {$rfc_receptor} | Nombre: {$nombre_cliente_log} | Serie: {$serie} | Folio: {$folio} | XML_BASE64: {$xml_base64}\n";
error_log($log_timbrado, 3, $log_file);
@file_put_contents(__DIR__ . "/../logs/sinube_api.log", $log_timbrado, FILE_APPEND);

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

        error_log("[" . date('Y-m-d H:i:s') . "] " .__FILE__."-".__LINE__."\n" , 3, $log_file);
        if ($xml_obj) {
            error_log("[" . date('Y-m-d H:i:s') . "] " . print_r($xml_obj, true), 3, $log_file);

            // Validar Errores devueltos por el portal, incluso si es código 200
            $error_matches = $xml_obj->xpath("/Respuesta/error");

            error_log("[" . date('Y-m-d H:i:s') . "] " .print_r($error_matches,true)."\n" , 3, $log_file);
            error_log("[" . date('Y-m-d H:i:s') . "] " .__FILE__."-".__LINE__."\n" , 3, $log_file);

            if (!empty($error_matches) && trim((string) $error_matches[0]) !== '') {
                $error_msg = trim((string) $error_matches[0]);

                error_log("[" . date('Y-m-d H:i:s') . "] " .__FILE__."-".__LINE__."\n" , 3, $log_file);
                error_log("[" . date('Y-m-d H:i:s') . "] " .print_r($error_msg,true)."\n" , 3, $log_file);
                echo json_encode(array(
                    'success' => false,
                    'message' => "Error de facturación: " . $error_msg,
                    'details' => 'No se cambió estatus'
                ));
               
                if ($branchConn)
                    mysqli_close($branchConn);
                exit();
            }

            error_log("[" . date('Y-m-d H:i:s') . "] " .__FILE__."-".__LINE__."\n" , 3, $log_file);
            $xml_matches = $xml_obj->xpath("/Respuesta/xml");
            $pdf_matches = $xml_obj->xpath("/Respuesta/pdf");
            $uuid_matches = $xml_obj->xpath("/Respuesta/UUID");
            $fecha_matches = $xml_obj->xpath("/Respuesta/fechaFactura");

            $link_xml = isset($xml_matches[0]) ? (string) $xml_matches[0] : '';
            $link_pdf = isset($pdf_matches[0]) ? (string) $pdf_matches[0] : '';
            $uuid = isset($uuid_matches[0]) ? (string) $uuid_matches[0] : '';
            $fecha_factura = isset($fecha_matches[0]) ? (string) $fecha_matches[0] : '';

            $objTipoProceso = $xml_obj->xpath("/Respuesta/@tipoProceso");
            $tipoProceso = isset($objTipoProceso[0]) ? strtoupper((string) $objTipoProceso[0]) : '';
            error_log("[" . date('Y-m-d H:i:s') . "] " . print_r(" $tipoProceso \n", true), 3, $log_file);

            if ($tipoProceso === "CONSULTA") {
                error_log("[" . date('Y-m-d H:i:s') . "] " . __LINE__ . "\n", 3, $log_file);

                if ($branchConn)
                    mysqli_close($branchConn);

                $mensaje = sprintf("Consulte a su administrador, La factura ya fue generada previamente. </br>Fecha:%s </br> Serie:%s y Folio:%s </br> UUID: %s ", $fecha_factura, $serie, $folio, $uuid);
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
                    $new_invoice_id = mysqli_insert_id($conexion_gen);
                    if (!empty($observacion_raw) && $new_invoice_id > 0) {
                        $stmt_obs = mysqli_prepare($conexion_gen, "UPDATE `" . $db_name_gen . "`.`facturas` SET observacion = ? WHERE id = ?");
                        if ($stmt_obs) {
                            mysqli_stmt_bind_param($stmt_obs, "si", $observacion_raw, $new_invoice_id);
                            mysqli_stmt_execute($stmt_obs);
                            mysqli_stmt_close($stmt_obs);
                        }
                    }
                    $db_message = 'Factura guardada en la base de datos GENERAL correctamente';
                    error_log("[" . date('Y-m-d H:i:s') . "] " . $db_message, 3, $log_file);

                    
                    try {
                        $mailer = new Mailer();

                        // Buscar el correo electrónico y nombre del cliente en el catálogo 'cust'
                        $para_emails = [];
                        $nombre_cliente = $razonSocial;
                        if (!empty($cust_id)) {
                            $sql_cust_email = "SELECT email, razon_social FROM cust WHERE id = " . intval($cust_id);
                            $res_cust_email = mysqli_query($conexion_gen, $sql_cust_email);
                            if ($res_cust_email && $row_cust = mysqli_fetch_assoc($res_cust_email)) {
                                if (!empty($row_cust['email'])) {
                                    $para_emails[] = trim($row_cust['email']);
                                }
                                if (!empty($row_cust['razon_social'])) {
                                    $nombre_cliente = $row_cust['razon_social'];
                                }
                            }
                            // Buscar correos adicionales del cliente
                            $sql_add_emails = "SELECT email FROM cust_emails WHERE cust_id = " . intval($cust_id) . " AND is_active = 1";
                            $res_add_emails = mysqli_query($conexion_gen, $sql_add_emails);
                            if ($res_add_emails) {
                                while ($row_add = mysqli_fetch_assoc($res_add_emails)) {
                                    if (!empty($row_add['email'])) {
                                        $para_emails[] = trim($row_add['email']);
                                    }
                                }
                            }
                        }
                        
                        $para = implode(', ', array_unique($para_emails));

                        // Si el cliente no tiene correo registrado, usamos un fallback y dejamos constancia en logs
                        $email_sent_to = $para;
                        if (empty($para)) {
                            $para = 'ocv.facturacion1@gmail.com'; // Fallback
                            $email_sent_to = "fallback ($para)";
                        }
                        
                        $asunto = 'Factura Electrónica Victoria - Folio: ' . $serie . ' ' . $folio;
                        
                        // Cuerpo del correo con diseño HTML premium y responsivo
                        $mensajeHtml = "
                            <html>
                            <head>
                                <meta charset='UTF-8'>
                                <title>Factura Electrónica</title>
                                <style>
                                    body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f8f9fa; margin: 0; padding: 20px; color: #2c3e50; }
                                    .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); border: 1px solid #eef2f5; }
                                    .header { background: linear-gradient(135deg, #2c3e50, #1a252f); padding: 30px; text-align: center; color: #ffffff; }
                                    .header h2 { margin: 0; font-size: 22px; font-weight: 600; letter-spacing: 0.5px; }
                                    .content { padding: 35px; }
                                    .greeting { font-size: 18px; font-weight: bold; margin-bottom: 20px; color: #2c3e50; }
                                    .details-box { background-color: #fdfefe; border-left: 4px solid #27ae60; padding: 20px; margin-bottom: 25px; border-radius: 4px; box-shadow: inset 0 0 10px rgba(0,0,0,0.01); border-top: 1px solid #f0f4f7; border-right: 1px solid #f0f4f7; border-bottom: 1px solid #f0f4f7; }
                                    .details-box table { width: 100%; border-collapse: collapse; }
                                    .details-box td { padding: 6px 0; font-size: 14px; }
                                    .details-box td.label { font-weight: bold; color: #7f8c8d; width: 120px; }
                                    .details-box td.value { color: #2c3e50; }
                                    .btn-container { text-align: center; margin: 30px 0 10px; }
                                    .btn { display: inline-block; padding: 12px 24px; font-size: 14px; font-weight: bold; text-decoration: none; border-radius: 5px; transition: all 0.3s ease; text-align: center; margin: 0 8px; }
                                    .btn-xml { background-color: #34495e; color: #ffffff !important; border: 1px solid #2c3e50; }
                                    .btn-pdf { background-color: #e74c3c; color: #ffffff !important; border: 1px solid #c0392b; }
                                    .footer { background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #95a5a6; border-top: 1px solid #ecf0f1; }
                                </style>
                            </head>
                            <body>
                                <div class='container'>
                                    <div class='header'>
                                        <h2>Comprobante Fiscal Digital</h2>
                                    </div>
                                    <div class='content'>
                                        <div class='greeting'>Estimado(a) $nombre_cliente,</div>
                                        <p>Le informamos que se ha generado exitosamente su comprobante fiscal correspondiente a su consumo en <strong>Carnicerías Victoria</strong>.</p>
                                        
                                        <div class='details-box'>
                                            <table>
                                                <tr>
                                                    <td class='label'>Ticket:</td>
                                                    <td class='value'>$mov_id</td>
                                                </tr>
                                                <tr>
                                                    <td class='label'>Serie:</td>
                                                    <td class='value'>$serie</td>
                                                </tr>
                                                <tr>
                                                    <td class='label'>Folio:</td>
                                                    <td class='value'>$folio</td>
                                                </tr>
                                                <tr>
                                                    <td class='label'>Monto Total:</td>
                                                    <td class='value' style='font-weight: bold; color: #27ae60;'>$" . number_format($totalGlobal, 2) . " MXN</td>
                                                </tr>
                                                <tr>
                                                    <td class='ambiente'>Ambiente:</td>
                                                    <td class='value'>$ambiente </td>
                                                </tr>
                                            </table>
                                        </div>
                                        
                                        <p style='text-align: center; font-size: 14px; color: #7f8c8d; margin-bottom: 20px;'>Puede descargar los archivos digitales de su factura utilizando los siguientes botones:</p>
                                        
                                        <div class='btn-container'>
                                            <a href='$link_xml' class='btn btn-xml' target='_blank'>Descargar XML</a>
                                            <a href='$link_pdf' class='btn btn-pdf' target='_blank'>Descargar PDF</a>
                                        </div>
                                    </div>
                                    <div class='footer'>
                                        <p>Este es un envío automático generado por el Sistema de Facturación Victoria.<br>Por favor no responda a este correo.</p>
                                    </div>
                                </div>
                            </body>
                            </html>
                        ";

                        $adjuntos = [];

                        // Ejecución modular
                        if ($mailer->send($para, $asunto, $mensajeHtml, $adjuntos)) {
                            $db_message = sprintf("%s\n El correo fue enviado exitosamente a %s.", $db_message, $email_sent_to);
                            error_log("[" . date('Y-m-d H:i:s') . "] " . $db_message, 3, $log_file);
                        }

                    } catch (\Throwable $e) {
                        $db_message = sprintf("%s\n El correo no fue enviado a %s. Detalle: %s", $db_message, $email_sent_to, $e->getMessage());
                        error_log("[" . date('Y-m-d H:i:s') . "] " . $db_message, 3, $log_file);
                    }
                    

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

        send_json_and_exit($log_file, 'CP-11_PASO6_FACTURA_OK', array(
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
        if ($branchConn)
            mysqli_close($branchConn);
        send_json_and_exit($log_file, 'CP-11_PASO6_EXCEPTION', array(
            'success' => false,
            'message' => $th->getMessage(),
            'details' => "detalles"
        ));
    }

} else {
    if ($branchConn)
        mysqli_close($branchConn);
    send_json_and_exit($log_file, 'CP-11_PASO6_HTTP_ERROR', array(
        'success' => false,
        'message' => 'Error al enviar XML a SINUBE.',
        'details' => $response_envio
    ));
}
?>