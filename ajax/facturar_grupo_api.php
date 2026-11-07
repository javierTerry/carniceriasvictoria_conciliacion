<?php
session_start();
require_once "../config/config.php";

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? 1;
$group_id = $_POST['group_id'] ?? 0;
$branch = $_POST['branch'] ?? '';

if (empty($group_id) || empty($branch)) {
    echo json_encode(['success' => false, 'message' => 'Datos insuficientes']);
    exit;
}

$observacion_raw = isset($_POST['observacion']) ? $_POST['observacion'] : '';
$observacion = htmlspecialchars(trim($observacion_raw), ENT_XML1, 'UTF-8');

$branchesConfigs = getBranchesConfig();
if (!isset($branchesConfigs[$branch])) {
    echo json_encode(['success' => false, 'message' => 'Sucursal no válida']);
    exit;
}

$config = $branchesConfigs[$branch];
$targetConn = mysqli_connect($config['host'], $config['user'], $config['pass'], $config['db']);
if (!$targetConn) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la BD de la sucursal']);
    exit;
}
mysqli_set_charset($targetConn, "utf8");

// 1. Obtener información del grupo y tickets
$sql_group = "SELECT * FROM groups_tickets WHERE id = " . intval($group_id);
$res_group = mysqli_query($targetConn, $sql_group);
$group_info = mysqli_fetch_array($res_group, MYSQLI_ASSOC);

if (!$group_info) {
    echo json_encode(['success' => false, 'message' => 'Grupo no encontrado']);
    exit;
}

$sql_details = "SELECT mov_id FROM groups_tickets_details WHERE group_id = " . intval($group_id);
$res_details = mysqli_query($targetConn, $sql_details);
$ticket_mov_ids = [];
while ($row = mysqli_fetch_array($res_details)) {
    $ticket_mov_ids[] = "'" . mysqli_real_escape_string($targetConn, $row['mov_id']) . "'";
}

if (empty($ticket_mov_ids)) {
    echo json_encode(['success' => false, 'message' => 'El grupo no tiene tickets relacionados']);
    exit;
}

// 2. Obtener IDs internos de vtahead para buscar items
$sql_head = "SELECT id FROM vtahead WHERE mov_id IN (" . implode(',', $ticket_mov_ids) . ")";
$res_head = mysqli_query($targetConn, $sql_head);
$vta_ids = [];
while ($row = mysqli_fetch_array($res_head)) {
    $vta_ids[] = $row['id'];
}

// 3. Consolidar Items
$sql_items = "SELECT B.name, A.price, SUM(A.qty) as total_qty, SUM(A.amount) as total_amount 
              FROM vtaitem A 
              INNER JOIN art B ON B.id = A.art_id 
              WHERE A.vta_id IN (" . implode(',', $vta_ids) . ")
              GROUP BY A.art_id, A.price";
$res_items = mysqli_query($targetConn, $sql_items);

$ticket_items = [];
while ($item = mysqli_fetch_array($res_items, MYSQLI_ASSOC)) {
    $ticket_items[] = [
        'name' => $item['name'],
        'qty' => $item['total_qty'],
        'price' => $item['price'],
        'amount' => $item['total_amount']
    ];
}

// ---------------------------------------------------------
// REUTILIZACIÓN DE LÓGICA DE SINUBE (Basado en SinubeHelper)
// ---------------------------------------------------------
$log_dir = __DIR__ . "/../logs";
$log_file = $log_dir . "/facturacion_grupal.log";
$target_serie = $branchSeriesMap[$branch] ?? '';

// PASO 1: Obtener Certificado (Folio y Serie)
try {
    $sinube = new SinubeHelper($log_file);
    $folioData = $sinube->getFolioActual($api_url_cert, $api_no_certificado, $target_serie);
    
    $serie = $folioData['serie'];
    $folio = (string)($folioData['folioActual'] + 1);

    error_log("[" . date('Y-m-d H:i:s') . "] Folio Grupo Obtenido: Serie=$serie, Folio=$folio \n", 3, $log_file);
} catch (Throwable $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] Error en PASO 1 Grupal: " . $e->getMessage() . "\n", 3, $log_file);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

// PASO 2: Armar XML Dinámico
$monto_total = $group_info['total_tickets_amount'];
$msTime = round(microtime(true) * 1000);

$conceptos_xml = "";
foreach ($ticket_items as $item) {
    $desc = htmlspecialchars($item['name'], ENT_XML1, 'UTF-8');
    $qty = round($item['qty'], 4);
    $totalItem = $item['amount'];
    $priceItem = round($item['price'], 4);
    
    $conceptos_xml .= <<<XML
       <Concepto productoSinube="01010101" productoSAT="01010101" descripcion="{$desc}" cantidad="{$qty}" unidadSinube="PIEZA" unidadSAT="H87" valorUnitario="{$priceItem}" descuento="0" tipoIVA="IVA 0%" montoBaseIVA="{$totalItem}" montoIVA="0" importe="{$totalItem}" subtotalDet="{$totalItem}" objetoImp="02" />
XML;
}

$xml_payload = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<Comprobante exportacion="01" version="CFDI 4.0" sistema="OBRADORCARNICERIA" generar="Factura" rfcEmisor="URE180429TM6-39" sucursal="Matriz" codigoReporte="CFDI 4.0 - CON IVA - SINUBE-COPIA" 
    permiteAgregarProductosNoInv="1" nomArchivoDescarga="GRP-{$group_id}-{$serie}-{$folio}" noCertificado="30001000000500003441" serie="{$serie}" folio="{$folio}"  
    formaDePago="01" condicionesDePago="CONTADO" fechaPagoProbable="{$msTime}" metodoDePago="PUE" subtotal="{$monto_total}" descuento="0" porcentajeIVA="16" montoIVA="0" 
    total="{$monto_total}" monedaSinube="MXN" monedaSAT="MXN" difZonaHoraria="-06" observacion="{$observacion}">
   <Receptor rfc="OHM191218EH7" razonSocial="OBRADOR HNOS MIRANDA" usoCFDI="G03" esPersonaFisica="0" regimenFiscal="601"/>
   <ReceptorDireccion pais="MEX" codigoPostal="54030" ></ReceptorDireccion>
   <Conceptos>
{$conceptos_xml}   </Conceptos>
</Comprobante>
XML;

$url_envio = $api_url_envio;

// Log estructurado para debug de timbrado con XML en Base64
$rfc_receptor_grp = "OHM191218EH7";
$nombre_receptor_grp = "OBRADOR HNOS MIRANDA";
$xml_base64 = base64_encode($xml_payload);
$log_timbrado = "[" . date('Y-m-d H:i:s') . "] [TIMBRADO_ENVIO] RFC: {$rfc_receptor_grp} | Nombre: {$nombre_receptor_grp} | Serie: {$serie} | Folio: {$folio} | XML_BASE64: {$xml_base64}\n";
error_log($log_timbrado, 3, $log_file);
@file_put_contents(__DIR__ . "/../logs/sinube_api.log", $log_timbrado, FILE_APPEND);

$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, $url_envio);
curl_setopt($ch2, CURLOPT_POST, 1);
curl_setopt($ch2, CURLOPT_POSTFIELDS, $xml_payload);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: text/xml']);

$response_envio = curl_exec($ch2);
$http_code = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

if ($http_code == 200) {
    $xml_resp = simplexml_load_string($response_envio);
    
    // Validar Errores devueltos por el portal, incluso si es código 200
    $error_matches = $xml_resp->xpath("/Respuesta/error");
    if (!empty($error_matches) && trim((string)$error_matches[0]) !== '') {
        $error_msg = trim((string)$error_matches[0]);
        echo json_encode(array(
            'success' => false, 'message' => "Error de facturación grupal: " . $error_msg
        ));
        mysqli_close($targetConn);
        exit;
    }

    $uuid_matches = $xml_resp->xpath("/Respuesta/UUID");
    $xml_matches = $xml_resp->xpath("/Respuesta/xml");
    $pdf_matches = $xml_resp->xpath("/Respuesta/pdf");
    $fecha_matches = $xml_resp->xpath("/Respuesta/fechaFactura");
    
    $uuid = isset($uuid_matches[0]) ? (string)$uuid_matches[0] : '';
    $link_xml = isset($xml_matches[0]) ? (string)$xml_matches[0] : '';
    $link_pdf = isset($pdf_matches[0]) ? (string)$pdf_matches[0] : '';
    $fecha_factura = isset($fecha_matches[0]) ? (string)$fecha_matches[0] : '';

    $objTipoProceso = $xml_resp->xpath("/Respuesta/@tipoProceso");
    $tipoProceso = isset($objTipoProceso[0]) ? strtoupper((string)$objTipoProceso[0]) : '';

    if ($tipoProceso === "CONSULTA") {
        mysqli_close($targetConn);
        echo json_encode(array(
            'success' => false, 
            'is_consulta' => true,
            'message' => "La factura grupal ya fue generada previamente con esta Serie y Folio. UUID: " . $uuid . " (Fecha: " . $fecha_factura . ")",
            'uuid' => $uuid
        ));
        exit;
    }

    if (!empty($uuid)) {
        mysqli_begin_transaction($targetConn);
        try {
            // Actualizar tickets a estatus 3 (Facturado)
            $sql_upd_tickets = "UPDATE vtahead SET is_active = 3 WHERE id IN (" . implode(',', $vta_ids) . ")";
            mysqli_query($targetConn, $sql_upd_tickets);

            // Actualizar grupo a estatus 3
            $sql_upd_group = "UPDATE groups_tickets SET status = 3 WHERE id = $group_id";
            mysqli_query($targetConn, $sql_upd_group);

            // Insertar en tabla facturas
            $sql_invoice = "INSERT INTO facturas (mov_id, uuid, monto, metodo_pago, usuario_id, serie, folio, xml_url, pdf_url, estatus, estado) 
                            VALUES ('GRP-$group_id', '$uuid', $monto_total, 'Agrupado', $user_id, '$serie', '$folio', '$link_xml', '$link_pdf', 1, 'Activa')";
            mysqli_query($targetConn, $sql_invoice);
            $new_grp_inv_id = mysqli_insert_id($targetConn);
            if (!empty($observacion_raw) && $new_grp_inv_id > 0) {
                $stmt_obs = mysqli_prepare($targetConn, "UPDATE facturas SET observacion = ? WHERE id = ?");
                if ($stmt_obs) {
                    mysqli_stmt_bind_param($stmt_obs, "si", $observacion_raw, $new_grp_inv_id);
                    mysqli_stmt_execute($stmt_obs);
                    mysqli_stmt_close($stmt_obs);
                }
            }

            mysqli_commit($targetConn);

            // Enviar copia de factura grupal a ocv.facturacion1@gmail.com
            try {
                require_once __DIR__ . '/../classes/Mailer.php';
                $mailer = new Mailer();
                $para_grp = 'ocv.facturacion1@gmail.com';
                $asunto_grp = 'Factura Electrónica Grupal Victoria - Folio: ' . $serie . ' ' . $folio;
                $ambiente_str = $ambiente ?? 'PRODUCCIÓN';

                $mensajeHtmlGrp = "
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <title>Factura Electrónica Grupal</title>
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
                                <h2>Comprobante Fiscal Digital (Grupal)</h2>
                            </div>
                            <div class='content'>
                                <div class='greeting'>Estimado(a) {$nombre_receptor_grp},</div>
                                <p>Le informamos que se ha generado exitosamente el comprobante fiscal grupal en <strong>Carnicerías Victoria</strong>.</p>
                                
                                <div class='details-box'>
                                    <table>
                                        <tr>
                                            <td class='label'>Grupo:</td>
                                            <td class='value'>GRP-{$group_id}</td>
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
                                            <td class='value' style='font-weight: bold; color: #27ae60;'>$" . number_format((float)$monto_total, 2) . " MXN</td>
                                        </tr>
                                        <tr>
                                            <td class='label'>Ambiente:</td>
                                            <td class='value'>{$ambiente_str}</td>
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
                                <p>Este es un envío automático generado por el Sistema de Facturación Victoria.<br>Por favor no responda a este correo.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";

                $mailer->send($para_grp, $asunto_grp, $mensajeHtmlGrp, []);
            } catch (\Throwable $mailEx) {
                error_log("[" . date('Y-m-d H:i:s') . "] Error al enviar correo grupal: " . $mailEx->getMessage() . "\n", 3, $log_file);
            }

            echo json_encode(['success' => true, 'uuid' => $uuid]);
        } catch (Exception $e) {
            mysqli_rollback($targetConn);
            echo json_encode(['success' => false, 'message' => 'Error al actualizar base de datos: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Sinube no devolvió UUID', 'raw' => $response_envio]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Error al enviar a Sinube', 'http_code' => $http_code]);
}

mysqli_close($targetConn);
