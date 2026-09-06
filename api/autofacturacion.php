<?php
/**
 * api/autofacturacion.php
 * API segura para el portal público de autofacturación.
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Content-Type: application/json");

// Manejo de peticiones preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once "../config/config.php";
require_once "../classes/ProductMapper.php";
require_once "../classes/Mailer.php";

$action = $_REQUEST['action'] ?? '';

if ($action === 'validate') {
    $ticket = trim($_POST['ticket'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $branch = trim($_POST['branch'] ?? '');
    $rfc = strtoupper(trim($_POST['rfc'] ?? ''));

    if (empty($ticket) || $amount <= 0 || empty($branch)) {
        echo json_encode(['success' => false, 'message' => 'Datos de ticket incompletos.']);
        exit;
    }

    $branchesConfigs = getBranchesConfig();
    if (!isset($branchesConfigs[$branch])) {
        echo json_encode(['success' => false, 'message' => 'La sucursal especificada no es válida.']);
        exit;
    }

    $config = $branchesConfigs[$branch];
    $branchConn = @mysqli_connect($config['host'], $config['user'], $config['pass'], $config['db']);

    if (!$branchConn) {
        echo json_encode(['success' => false, 'message' => 'Error al conectar con la base de datos de la sucursal.']);
        exit;
    }

    mysqli_set_charset($branchConn, "utf8");

    // Buscar ticket
    $sql = "SELECT id, mov_id, cust_id, created_at, sumimp, fpago, is_active FROM vtahead WHERE mov_id = ?";
    $stmt = mysqli_prepare($branchConn, $sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Error al preparar la consulta de venta.']);
        mysqli_close($branchConn);
        exit;
    }

    mysqli_stmt_bind_param($stmt, "s", $ticket);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $vta = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$vta) {
        echo json_encode(['success' => false, 'message' => 'El ticket no existe.']);
        mysqli_close($branchConn);
        exit;
    }

    // Validar monto (permitiendo un margen pequeño por decimales)
    if (abs(floatval($vta['sumimp']) - $amount) > 0.05) {
        echo json_encode([
            'success' => false,
            'message' => 'El monto ingresado no coincide con el total del ticket.'
        ]);
        mysqli_close($branchConn);
        exit;
    }

    // Validar estatus
    $status = intval($vta['is_active']);
    if ($status === 3) {
        echo json_encode(['success' => false, 'message' => 'Este ticket ya ha sido facturado.']);
        mysqli_close($branchConn);
        exit;
    }

    if ($status !== 1 && $status !== 2) {
        echo json_encode(['success' => false, 'message' => 'Este ticket no está disponible para facturación (Estatus inválido).']);
        mysqli_close($branchConn);
        exit;
    }

    // Obtener artículos del ticket
    $sql_items = "SELECT A.qty, B.name, A.price, A.amount 
                  FROM vtaitem A 
                  INNER JOIN art B ON B.id = A.art_id 
                  WHERE A.vta_id = ?";
    $stmt_items = mysqli_prepare($branchConn, $sql_items);
    $items = [];
    if ($stmt_items) {
        mysqli_stmt_bind_param($stmt_items, "i", $vta['id']);
        mysqli_stmt_execute($stmt_items);
        $result_items = mysqli_stmt_get_result($stmt_items);
        while ($item = mysqli_fetch_assoc($result_items)) {
            $items[] = [
                'qty' => floatval($item['qty']),
                'name' => $item['name'],
                'price' => floatval($item['price']),
                'amount' => floatval($item['amount'])
            ];
        }
        mysqli_stmt_close($stmt_items);
    }
    mysqli_close($branchConn);

    // Obtener Forma de Pago
    $fpago_id = intval($vta['fpago']);
    $forma_pago_code = '03'; // Fallback Transferencia o Efectivo
    $sql_fp = "SELECT code FROM fpago WHERE id = ?";
    $stmt_fp = mysqli_prepare($conexion, $sql_fp);
    if ($stmt_fp) {
        mysqli_stmt_bind_param($stmt_fp, "i", $fpago_id);
        mysqli_stmt_execute($stmt_fp);
        $res_fp = mysqli_stmt_get_result($stmt_fp);
        if ($row_fp = mysqli_fetch_assoc($res_fp)) {
            $forma_pago_code = $row_fp['code'];
        }
        mysqli_stmt_close($stmt_fp);
    }

    // Buscar si el cliente ya existe por RFC
    $rfc_exists = false;
    $client_data = null;
    if (!empty($rfc)) {
        $sql_cust = "SELECT * FROM cust WHERE rfc = ? AND is_active = 1 LIMIT 1";
        $stmt_cust = mysqli_prepare($conexion_gen, $sql_cust);
        if ($stmt_cust) {
            mysqli_stmt_bind_param($stmt_cust, "s", $rfc);
            mysqli_stmt_execute($stmt_cust);
            $res_cust = mysqli_stmt_get_result($stmt_cust);
            if ($row_cust = mysqli_fetch_assoc($res_cust)) {
                $rfc_exists = true;
                $client_data = [
                    'id' => intval($row_cust['id']),
                    'rfc' => $row_cust['rfc'],
                    'razon_social' => $row_cust['razon_social'],
                    'cp' => $row_cust['cp'],
                    'email' => $row_cust['email'],
                    'regimen_fiscal' => $row_cust['regimen_fiscal'],
                    'es_persona_fisica' => intval($row_cust['es_persona_fisica']),
                    'nombre' => $row_cust['nombre'],
                    'ap_paterno' => $row_cust['ap_paterno'],
                    'metodo_pago_code' => $row_cust['metodo_pago_code'] ?: 'PUE',
                    'uso_cfdi_code' => $row_cust['uso_cfdi_code'] ?: 'G03'
                ];
            }
            mysqli_stmt_close($stmt_cust);
        }
    }

    echo json_encode([
        'success' => true,
        'ticket' => [
            'mov_id' => $vta['mov_id'],
            'created_at' => $vta['created_at'],
            'amount' => floatval($vta['sumimp']),
            'forma_pago_code' => $forma_pago_code,
            'items' => $items
        ],
        'rfc_exists' => $rfc_exists,
        'client' => $client_data
    ]);
    exit;
}

if ($action === 'invoice') {
    $ticket = trim($_POST['ticket'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $branch = trim($_POST['branch'] ?? '');

    // Fiscal data from client
    $rfc_receptor = strtoupper(trim($_POST['rfc_receptor'] ?? ''));
    $razon_social = strtoupper(trim($_POST['razon_social'] ?? ''));
    $uso_cfdi = strtoupper(trim($_POST['uso_cfdi'] ?? 'G03'));
    $regimen_fiscal = trim($_POST['regimen_fiscal'] ?? '');
    $es_persona_fisica = trim($_POST['es_persona_fisica'] ?? '1');
    $nombre = strtoupper(trim($_POST['nombre'] ?? ''));
    $ap_paterno = strtoupper(trim($_POST['ap_paterno'] ?? ''));
    $codigo_postal = trim($_POST['codigo_postal'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $metodo_pago_cfdi = strtoupper(trim($_POST['metodo_pago_cfdi'] ?? 'PUE'));
    $forma_pago = trim($_POST['forma_pago'] ?? '03');
    $cust_id = intval($_POST['cust_id'] ?? 1);

    if (empty($ticket) || $amount <= 0 || empty($branch) || empty($rfc_receptor) || empty($razon_social) || empty($codigo_postal) || empty($regimen_fiscal)) {
        echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios para generar la factura.']);
        exit;
    }

    $branchesConfigs = getBranchesConfig();
    if (!isset($branchesConfigs[$branch])) {
        echo json_encode(['success' => false, 'message' => 'La sucursal especificada no es válida.']);
        exit;
    }

    $config = $branchesConfigs[$branch];
    $branchConn = @mysqli_connect($config['host'], $config['user'], $config['pass'], $config['db']);

    if (!$branchConn) {
        echo json_encode(['success' => false, 'message' => 'Error al conectar con la base de datos de la sucursal.']);
        exit;
    }

    mysqli_set_charset($branchConn, "utf8");

    // Buscar y re-validar ticket
    $sql = "SELECT id, mov_id, cust_id, created_at, sumimp, fpago, is_active FROM vtahead WHERE mov_id = ?";
    $stmt = mysqli_prepare($branchConn, $sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Error al preparar la consulta de venta.']);
        mysqli_close($branchConn);
        exit;
    }
    mysqli_stmt_bind_param($stmt, "s", $ticket);
    mysqli_stmt_execute($stmt);
    $vta = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$vta) {
        echo json_encode(['success' => false, 'message' => 'El ticket no existe.']);
        mysqli_close($branchConn);
        exit;
    }

    if (abs(floatval($vta['sumimp']) - $amount) > 0.05) {
        echo json_encode(['success' => false, 'message' => 'El monto ingresado no coincide con el total del ticket.']);
        mysqli_close($branchConn);
        exit;
    }

    if (intval($vta['is_active']) === 3) {
        echo json_encode(['success' => false, 'message' => 'Este ticket ya ha sido facturado.']);
        mysqli_close($branchConn);
        exit;
    }

    // Obtener artículos del ticket para la facturación (origen seguro de datos)
    $sql_items = "SELECT A.qty, B.name, A.price, A.amount 
                  FROM vtaitem A 
                  INNER JOIN art B ON B.id = A.art_id 
                  WHERE A.vta_id = ?";
    $stmt_items = mysqli_prepare($branchConn, $sql_items);
    $ticket_items = [];
    if ($stmt_items) {
        mysqli_stmt_bind_param($stmt_items, "i", $vta['id']);
        mysqli_stmt_execute($stmt_items);
        $result_items = mysqli_stmt_get_result($stmt_items);
        while ($item = mysqli_fetch_assoc($result_items)) {
            $ticket_items[] = $item;
        }
        mysqli_stmt_close($stmt_items);
    }

    if (empty($ticket_items)) {
        echo json_encode(['success' => false, 'message' => 'El ticket no contiene artículos para facturar.']);
        mysqli_close($branchConn);
        exit;
    }

    // Obtener Folio y Serie
    $target_serie = $branchSeriesMap[$branch] ?? '';
    $log_file = __DIR__ . "/../logs/autofacturacion.log";
    $mov_id = $vta['mov_id'];

    try {
        $sinube = new SinubeHelper($log_file);
        $folioData = $sinube->getFolioActual($api_url_cert, $api_no_certificado, $target_serie);
        $serie = $folioData['serie'];
        $folio = (string)($folioData['folioActual'] + 1);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al obtener folio de Sinube: ' . $e->getMessage()]);
        mysqli_close($branchConn);
        exit;
    }

    // Construcción de XML
    if (strpos($razon_social, '&') !== false) {
        $razon_social = str_replace('&amp;', '&', $razon_social);
        $razon_social = str_replace('&', '&amp;', $razon_social);
    }
    if (strpos($nombre, '&') !== false) {
        $nombre = str_replace('&amp;', '&', $nombre);
        $nombre = str_replace('&', '&amp;', $nombre);
    }

    $totalGlobal = floatval($vta['sumimp']);
    $subtotalGlobal = $totalGlobal;
    $msTime = round(microtime(true) * 1000);
    $tipoIVA = "IVA 0%";
    $porcentajeIVA = "16";

    $conceptos_xml = "";
    $mapper = new ProductMapper($conexion_gen);

    foreach ($ticket_items as $item) {
        $desc_original = $item['name'];
        $desc_upper = mb_strtoupper($desc_original, 'UTF-8');
        $desc = htmlspecialchars($desc_upper, ENT_XML1, 'UTF-8');

        $productData = $mapper->findByDescription($desc_original);
        if (!$productData) {
            echo json_encode(['success' => false, 'message' => "El producto '{$desc_original}' no está configurado en el catálogo SAT de consolidación."]);
            mysqli_close($branchConn);
            exit;
        }

        $productoSAT = mb_strtoupper($productData['clave_sat'] ?? '01010101', 'UTF-8');
        $unidadSinube = mb_strtoupper($productData['unidad'] ?: 'PIEZA', 'UTF-8');
        $unidadSAT = mb_strtoupper($productData['unidad_sat'] ?: 'H87', 'UTF-8');

        $qty = round(floatval($item['qty']), 4);
        if ($qty <= 0) $qty = 1;

        $totalItem = floatval($item['amount']);
        $priceItem = floatval($item['price']);
        $valUnitarioItem = round($priceItem, 4);

        $conceptos_xml .= <<<XML
       <Concepto productoSinube="{$productoSAT}" productoSAT="{$productoSAT}" descripcion="{$desc}" cantidad="{$qty}" unidadSinube="{$unidadSinube}" unidadSAT="{$unidadSAT}" valorUnitario="{$valUnitarioItem}" descuento="0" tipoIVA="{$tipoIVA}" montoBaseIVA="{$totalItem}" montoIVA="0" importe="{$totalItem}" subtotalDet="{$totalItem}" objetoImp="02" />
XML;
    }

    $receptor = "";
    if ($es_persona_fisica === "1") {
        $receptor = <<<XML
        <Receptor rfc="{$rfc_receptor}" razonSocial="{$razon_social}" usoCFDI="{$uso_cfdi}" esPersonaFisica="{$es_persona_fisica}" regimenFiscal="{$regimen_fiscal}" nombre="{$nombre}" apellidoPaterno="{$ap_paterno}"/>
        <ReceptorDireccion pais="MEX" codigoPostal="{$codigo_postal}" ></ReceptorDireccion>
XML;
    } else {
        $receptor = <<<XML
        <Receptor rfc="{$rfc_receptor}" razonSocial="{$razon_social}" usoCFDI="{$uso_cfdi}" esPersonaFisica="{$es_persona_fisica}" regimenFiscal="{$regimen_fiscal}"/>
        <ReceptorDireccion pais="MEX" codigoPostal="{$codigo_postal}" ></ReceptorDireccion>
XML;
    }

    $observacion = "AUTOFACTURACION TCK: " . $mov_id;
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

    $ch2 = curl_init();
    curl_setopt($ch2, CURLOPT_URL, $api_url_envio);
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
    curl_close($ch2);

    if ($http_code_envio == 200) {
        try {
            libxml_use_internal_errors(true);
            $xml_obj = simplexml_load_string($response_envio);

            if (!$xml_obj) {
                throw new Exception("Sin Objeto XML de respuesta de Sinube.");
            }

            $error_matches = $xml_obj->xpath("/Respuesta/error");
            if (!empty($error_matches) && trim((string)$error_matches[0]) !== '') {
                echo json_encode(['success' => false, 'message' => "Error de Sinube: " . trim((string)$error_matches[0])]);
                mysqli_close($branchConn);
                exit;
            }

            $xml_matches = $xml_obj->xpath("/Respuesta/xml");
            $pdf_matches = $xml_obj->xpath("/Respuesta/pdf");
            $uuid_matches = $xml_obj->xpath("/Respuesta/UUID");
            $fecha_matches = $xml_obj->xpath("/Respuesta/fechaFactura");

            $link_xml = isset($xml_matches[0]) ? (string)$xml_matches[0] : '';
            $link_pdf = isset($pdf_matches[0]) ? (string)$pdf_matches[0] : '';
            $uuid = isset($uuid_matches[0]) ? (string)$uuid_matches[0] : '';
            $fecha_factura = isset($fecha_matches[0]) ? (string)$fecha_matches[0] : '';

            $objTipoProceso = $xml_obj->xpath("/Respuesta/@tipoProceso");
            $tipoProceso = isset($objTipoProceso[0]) ? strtoupper((string)$objTipoProceso[0]) : '';

            if ($tipoProceso === "CONSULTA") {
                echo json_encode([
                    'success' => false,
                    'message' => 'Esta factura ya fue generada previamente.',
                    'data' => [
                        'serie' => $serie,
                        'folio' => $folio,
                        'xml' => $link_xml,
                        'pdf' => $link_pdf,
                        'uuid' => $uuid
                    ]
                ]);
                mysqli_close($branchConn);
                exit;
            }

            // Guardar factura en base de datos GENERAL
            $db_message = 'No se guardó en BD';
            if ($conexion_gen && !empty($uuid)) {
                // Obtener método de pago legible (Efectivo, Tarjeta, etc.) basado en código de forma de pago
                $metodo_pago_txt = 'Efectivo';
                $sql_fp_name = "SELECT name FROM fpago WHERE code = ?";
                $stmt_fp_name = mysqli_prepare($conexion, $sql_fp_name);
                if ($stmt_fp_name) {
                    mysqli_stmt_bind_param($stmt_fp_name, "s", $forma_pago);
                    mysqli_stmt_execute($stmt_fp_name);
                    $res_fp_name = mysqli_stmt_get_result($stmt_fp_name);
                    if ($row_fp_name = mysqli_fetch_assoc($res_fp_name)) {
                        $metodo_pago_txt = $row_fp_name['name'];
                    }
                    mysqli_stmt_close($stmt_fp_name);
                }

                $sql_insert = "INSERT INTO `{$db_name_gen}`.`facturas` (mov_id, uuid, monto, metodo_pago, usuario_id, serie, folio, xml_url, pdf_url, estatus, estado, cust_id, sucursal) 
                               VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, 1, 'Activa', ?, ?)";
                $stmt_ins = mysqli_prepare($conexion_gen, $sql_insert);
                if ($stmt_ins) {
                    mysqli_stmt_bind_param($stmt_ins, "ssssssssis", $mov_id, $uuid, $totalGlobal, $metodo_pago_txt, $serie, $folio, $link_xml, $link_pdf, $cust_id, $branch);
                    if (mysqli_stmt_execute($stmt_ins)) {
                        $db_message = 'Factura guardada correctamente.';
                        $new_auto_id = mysqli_insert_id($conexion_gen);
                        if ($new_auto_id > 0 && !empty($observacion)) {
                            $stmt_obs = mysqli_prepare($conexion_gen, "UPDATE `{$db_name_gen}`.`facturas` SET observacion = ? WHERE id = ?");
                            if ($stmt_obs) {
                                mysqli_stmt_bind_param($stmt_obs, "si", $observacion, $new_auto_id);
                                mysqli_stmt_execute($stmt_obs);
                                mysqli_stmt_close($stmt_obs);
                            }
                        }
                    }
                    mysqli_stmt_close($stmt_ins);
                }
            }

            // Marcar ticket como facturado en la base de datos de la sucursal
            $sql_update = "UPDATE vtahead SET is_active = 3 WHERE mov_id = ?";
            $stmt_up = mysqli_prepare($branchConn, $sql_update);
            if ($stmt_up) {
                mysqli_stmt_bind_param($stmt_up, "s", $mov_id);
                mysqli_stmt_execute($stmt_up);
                mysqli_stmt_close($stmt_up);
            }
            mysqli_close($branchConn);

            // Enviar Correo
            if (!empty($email)) {
                try {
                    $mailer = new Mailer();
                    $asunto = 'Factura Electrónica Victoria (Autofacturado) - Folio: ' . $serie . ' ' . $folio;
                    
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
                                    <div class='greeting'>Estimado(a) {$razon_social},</div>
                                    <p>Le informamos que se ha generado exitosamente su comprobante fiscal correspondiente a su consumo en <strong>Carnicerías Victoria</strong>.</p>
                                    
                                    <div class='details-box'>
                                        <table>
                                            <tr>
                                                <td class='label'>Ticket:</td>
                                                <td class='value'>{$mov_id}</td>
                                            </tr>
                                            <tr>
                                                <td class='label'>Serie:</td>
                                                <td class='value'>{$serie}</td>
                                            </tr>
                                            <tr>
                                                <td class='label'>Folio:</td>
                                                <td class='value'>{$folio}</td>
                                            </tr>
                                            <tr>
                                                <td class='label'>Monto Total:</td>
                                                <td class='value' style='font-weight: bold; color: #27ae60;'>$" . number_format($totalGlobal, 2) . " MXN</td>
                                            </tr>
                                        </table>
                                    </div>
                                    
                                    <p style='text-align: center; font-size: 14px; color: #7f8c8d; margin-bottom: 20px;'>Puede descargar los archivos digitales de su factura utilizando los siguientes botones:</p>
                                    
                                    <div class='btn-container'>
                                        <a href='{$link_xml}' class='btn btn-xml' target='_blank'>Descargar XML</a>
                                        <a href='{$link_pdf}' class='btn btn-pdf' target='_blank'>Descargar PDF</a>
                                    </div>
                                </div>
                                <div class='footer'>
                                    <p>Este es un envío automático generado por el Sistema de Autofacturación Victoria.<br>Por favor no responda a este correo.</p>
                                </div>
                            </div>
                        </body>
                        </html>
                    ";
                    
                    $mailer->send($email, $asunto, $mensajeHtml, []);
                } catch (Exception $ex) {
                    // Ignore email error for final response
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Factura generada exitosamente.',
                'db_message' => $db_message,
                'data' => [
                    'serie' => $serie,
                    'folio' => $folio,
                    'xml' => $link_xml,
                    'pdf' => $link_pdf,
                    'uuid' => $uuid
                ]
            ]);
            exit;

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error al procesar la factura: ' . $e->getMessage()]);
            if ($branchConn) mysqli_close($branchConn);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al comunicar con Sinube (HTTP ' . $http_code_envio . ').']);
        mysqli_close($branchConn);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
exit;
