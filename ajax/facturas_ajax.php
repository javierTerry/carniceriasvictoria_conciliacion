<?php
require_once "../config/config.php";

$log_dir = __DIR__ . "/../logs";
$log_file = $log_dir . "/facturas_ajax.log";



$user_kind = isset($_SESSION['user_kind']) ? $_SESSION['user_kind'] : 0;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

if ($action == 'ajax') {
    // Escapar para evitar inyección (Compatibilidad PHP 5.6+)
    $q = isset($_REQUEST['q']) ? mysqli_real_escape_string($conexion_gen, $_REQUEST['q']) : '';
    $page = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;
    $per_page = isset($_REQUEST['per_page']) ? intval($_REQUEST['per_page']) : 25;
    $branch_filter = isset($_REQUEST['branch']) ? mysqli_real_escape_string($conexion_gen, $_REQUEST['branch']) : '';
    $client_filter = isset($_REQUEST['client']) ? mysqli_real_escape_string($conexion_gen, $_REQUEST['client']) : '';
    $adjacents = 4;
    $offset = ($page - 1) * $per_page;

    $sWhere = " WHERE 1=1 ";

    if ($branch_filter !== '' && $branch_filter !== 'all') {
        $sWhere .= " AND A.sucursal = '$branch_filter' ";
    }

    if ($client_filter !== '') {
        $sWhere .= " AND C.name LIKE '%$client_filter%' ";
    }

    if (!empty($q)) {
        $sWhere .= " AND (A.mov_id LIKE '%$q%' OR A.uuid LIKE '%$q%' OR A.folio LIKE '%$q%')";
    }

    // Consulta simplificada para máxima compatibilidad
    // Intentamos obtener fecha_factura o fecha (usamos coalesce si es necesario, o solo el campo si sabemos cual es)
    $sql_data = "SELECT A.*, C.razon_social as cliente, C.email as cliente_email
                 FROM facturas A
                 LEFT JOIN cust C ON A.cust_id = C.id
                 $sWhere 
                 ORDER BY A.id DESC LIMIT $offset, $per_page";

    $query = mysqli_query($conexion_gen, $sql_data);

    if (!$query) {
        // Si falla por el nombre de la tabla (prefijo), intentamos con prefijo
        $sql_data_alt = "SELECT A.*, C.razon_social as cliente, C.email as cliente_email FROM `" . $db_name_gen . "`.`facturas` A LEFT JOIN `" . $db_name_gen . "`.`cust` C ON A.cust_id = C.id $sWhere ORDER BY A.id DESC LIMIT $offset, $per_page";
        $query = mysqli_query($conexion_gen, $sql_data_alt);
    }

    if (!$query) {
        echo "<div class='alert alert-danger'>Error en la base de datos general: " . mysqli_error($conexion_gen) . "</div>";
        exit;
    }

    $all_rows = array();
    while ($row = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
        $row['branch_label'] = $row['sucursal'] ? $row['sucursal'] : '---';
        $row['branch_class'] = 'label-' . strtolower((string) $row['sucursal']);
        $all_rows[] = $row;
    }

    // Conteo total para paginación
    $sql_count = "SELECT count(*) FROM facturas A LEFT JOIN cust C ON A.cust_id = C.id $sWhere";
    $query_count = mysqli_query($conexion_gen, $sql_count);
    $total_records = 0;
    if ($query_count) {
        $row_count = mysqli_fetch_row($query_count);
        $total_records = $row_count[0];
    } else {
        $sql_count_alt = "SELECT count(*) FROM `" . $db_name_gen . "`.`facturas` A LEFT JOIN `" . $db_name_gen . "`.`cust` C ON A.cust_id = C.id $sWhere";
        $query_count_alt = mysqli_query($conexion_gen, $sql_count_alt);
        if ($query_count_alt) {
            $row_count = mysqli_fetch_row($query_count_alt);
            $total_records = $row_count[0];
        }
    }

    $total_pages = ceil($total_records / $per_page);
    $reload = './facturasqry.php';

    if ($total_records > 0) {
        include 'pagination.php';
        ?>
        <div style="text-align: center; margin-bottom: 15px;">
            <?php echo paginate($reload, $page, $total_pages, $adjacents); ?>
        </div>
        <table class="table table-striped jambo_table bulk_action">
            <thead>
                <tr class="headings">
                    <th>Sucursal</th>
                    <th>Ticket</th>
                    <th>Cliente</th>
                    <th>Fecha Fact.</th>
                    <th>UUID / Folio</th>
                    <th class="text-right">Monto</th>
                    <th class="text-center">XML</th>
                    <th class="text-center">PDF</th>
                    <th class="text-center">Email</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_rows as $r):
                    // Detectar columna de fecha
                    $fecha_raw = isset($r['fecha_factura']) ? $r['fecha_factura'] : (isset($r['fecha']) ? $r['fecha'] : '');
                    $fecha_f = !empty($fecha_raw) ? date('d-m-Y H:i', strtotime($fecha_raw)) : '---';
                    ?>
                    <tr>
                        <td><span class="label <?php echo $r['branch_class']; ?>"><?php echo $r['branch_label']; ?></span></td>
                        <td><?php echo $r['mov_id']; ?></td>
                        <td><?php echo isset($r['cliente']) ? $r['cliente'] : '---'; ?></td>
                        <td><?php echo $fecha_f; ?></td>
                        <td>
                            <div><small style="color: #555;"><?php echo $r['uuid']; ?></small></div>
                            <?php if (isset($r['folio']) && $r['folio']): ?>
                                <div><strong><?php echo isset($r['serie']) ? $r['serie'] : ''; ?> - <?php echo $r['folio']; ?></strong>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td align="right" style="font-weight: bold; color: #26B99A;">$<?php echo number_format($r['monto'], 2); ?>
                        </td>
                        <td class="text-center">
                            <?php if (!empty($r['xml_url'])) { ?>
                                <a href="<?php echo htmlspecialchars($r['xml_url']); ?>" target="_blank" download title="Descargar XML">
                                    <i class="fa fa-file-code-o" style="font-size: 20px; color: #34495e;"></i>
                                </a>
                            <?php } else {
                                echo "-";
                            } ?>
                        </td>
                        <td class="text-center">
                            <?php if (!empty($r['pdf_url'])) { ?>
                                <a href="<?php echo htmlspecialchars($r['pdf_url']); ?>" target="_blank" download title="Descargar PDF">
                                    <i class="fa fa-file-pdf-o" style="font-size: 20px; color: #e74c3c;"></i>
                                </a>
                            <?php } else {
                                echo "-";
                            } ?>
                        </td>
                         
                        <td class="text-center">
                            <?php if (!empty($r['xml_url']) || !empty($r['pdf_url'])) { ?>
                                <a href="javascript:void(0);" onclick="enviarCorreoFactura(<?php echo $r['id']; ?>, '<?php echo htmlspecialchars($r['cliente_email'] ? $r['cliente_email'] : ''); ?>')" title="Enviar Factura por Correo" style="cursor: pointer;">
                                    <i class="fa fa-envelope" style="font-size: 20px; color: #3498db;"></i>
                                </a>
                            <?php } else {
                                echo "-";
                            } ?>
                        </td>
                        
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    } else {
        ?>
        <div class="alert alert-warning alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span
                    aria-hidden="true">&times;</span></button>
            <strong>Aviso!</strong> No se encontraron facturas registradas.
        </div>
        <?php
    }
} else if ($action == 'send_email') {
    header('Content-Type: application/json');
    $invoice_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($invoice_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID de factura inválido.']);
        exit;
    }
    
    // Buscar datos de la factura y del cliente
    $sql = "SELECT A.*, C.email, C.razon_social FROM facturas A LEFT JOIN cust C ON A.cust_id = C.id WHERE A.id = $invoice_id";
    $query = mysqli_query($conexion_gen, $sql);
    if (!$query || mysqli_num_rows($query) == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Factura no encontrada.']);
        exit;
    }
    
    $r = mysqli_fetch_assoc($query);
    $email = trim($r['email'] ?? '');
    $nombre_cliente = trim($r['razon_social'] ?? '');
    
    if (empty($email)) {
        echo json_encode(['status' => 'error', 'message' => 'El cliente no tiene un correo electrónico registrado en el catálogo.']);
        exit;
    }
    
    // Enviar correo
    try {
        require_once "../classes/Mailer.php";
        $mailer = new Mailer();
        
        $para = $email;
        $asunto = 'Reenvío de Factura Electrónica Victoria - Folio: ' . ($r['serie'] ?? '') . ' ' . ($r['folio'] ?? '');
        
        $link_xml = $r['xml_url'] ?? '';
        $link_pdf = $r['pdf_url'] ?? '';
        $mov_id = $r['mov_id'] ?? '';
        $serie = $r['serie'] ?? '';
        $folio = $r['folio'] ?? '';
        $totalGlobal = floatval($r['monto'] ?? 0);
        error_log("[" . date('Y-m-d H:i:s') . "] " . __LINE__, 3, $log_file);
        // Plantilla premium idéntica a la automática para mantener uniformidad
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
                        <p>Le hacemos llegar el reenvío de su comprobante fiscal correspondiente a su consumo en <strong>Carnicerías Victoria</strong>.</p>
                        
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
        
        if ($mailer->send($para, $asunto, $mensajeHtml)) {
            echo json_encode(['status' => 'success', 'message' => "Correo enviado exitosamente a $para."]);
            error_log("[" . date('Y-m-d H:i:s') . "] " . __LINE__, 3, $log_file);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No se pudo enviar el correo.']);
        }
    } catch (\Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error al enviar: ' . $e->getMessage()]);
    }
    exit;
}
?>