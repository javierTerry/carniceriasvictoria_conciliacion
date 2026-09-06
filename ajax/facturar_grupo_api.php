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
