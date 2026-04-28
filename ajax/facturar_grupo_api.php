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
// REUTILIZACIÓN DE LÓGICA DE SINUBE (Basado en facturar_api.php)
// ---------------------------------------------------------

// PASO 1: Obtener Certificado (Folio y Serie)
$url_cert = "http://ep-dot-facturanube.appspot.com/blob?par=dGlwbz0xMQplbXA9VVJFMTgwNDI5VE02LTM5CnN1Yz1NYXRyaXoKdXN1PWF0ZW5jaW9uc29sdWNpb25lc3J5akBnbWFpbC5jb20KcHdkPXByb3ZlZWRvcmVzCnNpcz1PQlJBRE9SQ0FSTklDRVJJQQ==";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url_cert);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response_cert = curl_exec($ch);
curl_close($ch);

if (!$response_cert) {
    echo json_encode(['success' => false, 'message' => 'Error al consultar folio/serie en SINUBE.']);
    exit;
}

$target_cert = "30001000000500003441";
try {
    $xml_obj = simplexml_load_string($response_cert);
    $nodes = $xml_obj->xpath("//*[@noCertificado='$target_cert']");
    if (!$nodes) throw new Exception("Certificado no encontrado.");
    $node = $nodes[0];
    $serie = (string) ($node->foliador['serie'] ?? '');
    $folio = (string) ($node->foliador['folioActual'] ?? '');
    $folio++;
} catch (Exception $e) {
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
    total="{$monto_total}" monedaSinube="MXN" monedaSAT="MXN" difZonaHoraria="-06">
   <Receptor rfc="OHM191218EH7" razonSocial="OBRADOR HNOS MIRANDA" usoCFDI="G03" esPersonaFisica="0" regimenFiscal="601"/>
   <ReceptorDireccion pais="MEX" codigoPostal="54030" ></ReceptorDireccion>
   <Conceptos>
{$conceptos_xml}   </Conceptos>
</Comprobante>
XML;

$url_envio = "http://ep-dot-facturanube.appspot.com/blob?par=dGlwbz00CmVtcD1VUkUxODA0MjlUTTYtMzkKc3VjPU1hdHJpegp1c3U9YXRlbmNpb25zb2x1Y2lvbmVzcnlqQGdtYWlsLmNvbQpwd2Q9cHJvdmVlZG9yZXM=";

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
    $uuid_matches = $xml_resp->xpath("/Respuesta/UUID");
    $uuid = isset($uuid_matches[0]) ? (string)$uuid_matches[0] : '';
    $link_xml = (string)$xml_resp->xpath("/Respuesta/xml")[0];
    $link_pdf = (string)$xml_resp->xpath("/Respuesta/pdf")[0];

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
