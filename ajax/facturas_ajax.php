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
    $metodo_pago_filter = isset($_REQUEST['metodo_pago']) ? mysqli_real_escape_string($conexion_gen, $_REQUEST['metodo_pago']) : '';
    $from_page = isset($_REQUEST['from_page']) ? $_REQUEST['from_page'] : '';
    $is_ppd = ($metodo_pago_filter === 'ppd' || $metodo_pago_filter === 'Por Definir' || $metodo_pago_filter === '99' || (!empty($from_page) && strpos($from_page, 'facturas_ppd.php') !== false));
    $adjacents = 4;
    $offset = ($page - 1) * $per_page;

    $sWhere = " WHERE 1=1 ";

    if ($branch_filter !== '' && $branch_filter !== 'all') {
        $sWhere .= " AND A.sucursal = '$branch_filter' ";
    }

    if ($client_filter !== '') {
        $sWhere .= " AND (C.razon_social LIKE '%$client_filter%' OR C.nombre LIKE '%$client_filter%') ";
    }

    if ($is_ppd) {
        $sWhere .= " AND (A.metodo_pago LIKE '%definir%' OR A.metodo_pago LIKE '%99%' OR A.metodo_pago = 'Por Definir' OR A.metodo_pago = 'PPD') ";
        $sWhere .= " AND (A.estatus = 1 OR A.estatus IS NULL) AND (A.estado != 'Cancelada' OR A.estado IS NULL) ";
        // Excluir facturas liquidadas con saldo remanente <= 0.01
        $sWhere .= " AND (
            A.monto 
            - IFNULL((SELECT SUM(monto_pagado) FROM deposito_factura WHERE factura_id = A.id AND is_active = 1), 0)
            - IFNULL((SELECT SUM(monto) FROM depositos WHERE factura_id = A.id AND is_active = 1 AND parent_id IS NULL AND id NOT IN (SELECT IFNULL(deposito_id, 0) FROM deposito_factura WHERE factura_id = A.id AND is_active = 1)), 0)
        ) > 0.01 ";
    } else if (!empty($metodo_pago_filter)) {
        $sWhere .= " AND A.metodo_pago = '$metodo_pago_filter' ";
    }

    if (!empty($q)) {
        $sWhere .= " AND (A.mov_id LIKE '%$q%' OR A.uuid LIKE '%$q%' OR A.folio LIKE '%$q%')";
    }

    if ($is_ppd) {
        $sql_data = "SELECT A.*, C.razon_social as cliente, C.email as cliente_email,
                     (
                         IFNULL((SELECT SUM(monto_pagado) FROM deposito_factura WHERE factura_id = A.id AND is_active = 1), 0)
                         + IFNULL((SELECT SUM(monto) FROM depositos WHERE factura_id = A.id AND is_active = 1 AND parent_id IS NULL AND id NOT IN (SELECT IFNULL(deposito_id, 0) FROM deposito_factura WHERE factura_id = A.id AND is_active = 1)), 0)
                     ) AS total_pagado,
                     (
                         IFNULL((SELECT COUNT(*) FROM deposito_factura WHERE factura_id = A.id AND is_active = 1), 0)
                         + IFNULL((SELECT COUNT(*) FROM depositos WHERE factura_id = A.id AND is_active = 1 AND parent_id IS NULL AND id NOT IN (SELECT IFNULL(deposito_id, 0) FROM deposito_factura WHERE factura_id = A.id AND is_active = 1)), 0)
                     ) AS total_pagos,
                     (
                         A.monto 
                         - IFNULL((SELECT SUM(monto_pagado) FROM deposito_factura WHERE factura_id = A.id AND is_active = 1), 0)
                         - IFNULL((SELECT SUM(monto) FROM depositos WHERE factura_id = A.id AND is_active = 1 AND parent_id IS NULL AND id NOT IN (SELECT IFNULL(deposito_id, 0) FROM deposito_factura WHERE factura_id = A.id AND is_active = 1)), 0)
                     ) AS saldo_pendiente
                     FROM facturas A
                     LEFT JOIN cust C ON A.cust_id = C.id
                     $sWhere 
                     ORDER BY A.id DESC LIMIT $offset, $per_page";
    } else {
        $sql_data = "SELECT A.*, C.razon_social as cliente, C.email as cliente_email
                     FROM facturas A
                     LEFT JOIN cust C ON A.cust_id = C.id
                     $sWhere 
                     ORDER BY A.id DESC LIMIT $offset, $per_page";
    }

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
    $reload = !empty($from_page) ? $from_page : ($is_ppd ? './facturas_ppd.php' : './facturasqry.php');

    if ($total_records > 0) {
        include 'pagination.php';
        ?>
        <div style="text-align: center; margin-bottom: 15px;">
            <?php echo paginate($reload, $page, $total_pages, $adjacents); ?>
        </div>
        <table class="table table-striped jambo_table bulk_action">
            <thead>
                <tr class="headings">
                    <?php if ($is_ppd): ?>
                        <th style="width: 40px;" class="text-center">
                            <input type="checkbox" id="check_all_ppd" title="Seleccionar todas las facturas visibles del cliente"
                                onchange="toggleSelectAllPPD(this)">
                        </th>
                    <?php endif; ?>
                    <th>Sucursal</th>
                    <th>Ticket</th>
                    <th>Cliente</th>
                    <th>Fecha Fact.</th>
                    <th>UUID / Folio</th>
                    <th class="text-right"><?php echo $is_ppd ? 'Monto Total' : 'Monto'; ?></th>
                    <?php if ($is_ppd): ?>
                        <th class="text-center">Pagos</th>
                        <th class="text-right">Total Pagado</th>
                        <th class="text-right">Saldo Pendiente</th>
                    <?php endif; ?>
                    <th class="text-center">Estatus</th>
                    <?php if (!$is_ppd): ?>
                        <th class="text-center">XML</th>
                        <th class="text-center">PDF</th>
                        <th class="text-center">Email</th>
                    <?php endif; ?>
                    <th class="text-center">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_rows as $r):
                    // Detectar columna de fecha
                    $fecha_raw = isset($r['fecha_factura']) ? $r['fecha_factura'] : (isset($r['fecha']) ? $r['fecha'] : '');
                    $fecha_f = !empty($fecha_raw) ? date('d-m-Y H:i', strtotime($fecha_raw)) : '---';
                    $est = isset($r['estado']) ? $r['estado'] : 'Activa';
                    $is_cancelled = ($est === 'Cancelada' || (isset($r['estatus']) && $r['estatus'] == 0));
                    $total_pagado = floatval($r['total_pagado'] ?? 0);
                    $total_pagos = intval($r['total_pagos'] ?? 0);
                    $saldo_pendiente = floatval($r['saldo_pendiente'] ?? (floatval($r['monto']) - $total_pagado));
                    ?>
                    <tr>
                        <?php if ($is_ppd): ?>
                            <td class="text-center" style="vertical-align: middle;">
                                <?php if (!$is_cancelled && $saldo_pendiente > 0.01): ?>
                                    <input type="checkbox" class="check_ppd_item" value="<?php echo $r['id']; ?>"
                                        data-id="<?php echo $r['id']; ?>" data-cust-id="<?php echo intval($r['cust_id'] ?? 0); ?>"
                                        data-cliente="<?php echo htmlspecialchars($r['cliente'] ?? 'Cliente General'); ?>"
                                        data-serie="<?php echo htmlspecialchars($r['serie'] ?? ''); ?>"
                                        data-folio="<?php echo htmlspecialchars($r['folio'] ?? ''); ?>"
                                        data-uuid="<?php echo htmlspecialchars($r['uuid'] ?? ''); ?>"
                                        data-monto="<?php echo floatval($r['monto'] ?? 0); ?>"
                                        data-saldo="<?php echo floatval($saldo_pendiente); ?>"
                                        data-pagos="<?php echo intval($total_pagos); ?>" onchange="onPpdCheckboxChange(this)">
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
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
                            <?php if (!empty($r['metodo_pago'])): ?>
                                <div><small class="text-muted"><i class="fa fa-credit-card"></i>
                                        <?php echo htmlspecialchars($r['metodo_pago']); ?></small></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-right" style="font-weight: bold; color: #2A3F54;">
                            $<?php echo number_format((float) $r['monto'], 2); ?>
                        </td>

                        <?php if ($is_ppd): ?>
                            <td class="text-center">
                                <span class="badge <?php echo ($total_pagos > 0) ? 'badge-primary' : 'badge-default'; ?>"
                                    style="font-size: 11.5px; padding: 4px 8px; border-radius: 4px;">
                                    <i class="fa fa-list-ol"></i> <?php echo $total_pagos; ?> pago(s)
                                </span>
                            </td>
                            <td class="text-right" style="font-weight: 600; color: #28a745;">
                                $<?php echo number_format($total_pagado, 2); ?>
                            </td>
                            <td class="text-right" style="font-weight: bold; color: #b22222; font-size: 13.5px;">
                                $<?php echo number_format($saldo_pendiente, 2); ?>
                            </td>
                        <?php endif; ?>

                        <!-- Estatus Column -->
                        <td class="text-center">
                            <?php if ($is_cancelled) { ?>
                                <span class="badge"
                                    style="background-color: #1a1a1a; color: #b22222; border: 1px solid #b22222; padding: 5px 10px; font-weight: bold; border-radius: 4px;">Cancelada</span>
                            <?php } else if ($is_ppd) { ?>
                                    <span class="badge badge-warning"
                                        style="background-color: #ff9800; color: #fff; padding: 5px 10px; font-weight: bold; border-radius: 4px;">Pendiente</span>
                            <?php } else { ?>
                                    <span class="badge badge-success"
                                        style="padding: 5px 10px; font-weight: bold; border-radius: 4px;">Activa</span>
                            <?php } ?>
                        </td>

                        <?php if (!$is_ppd): ?>
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
                                    <a href="javascript:void(0);"
                                        onclick="enviarCorreoFactura(<?php echo $r['id']; ?>, '<?php echo htmlspecialchars($r['cliente_email'] ? $r['cliente_email'] : ''); ?>')"
                                        title="Enviar Factura por Correo" style="cursor: pointer;">
                                        <i class="fa fa-envelope" style="font-size: 20px; color: #3498db;"></i>
                                    </a>
                                <?php } else {
                                    echo "-";
                                } ?>
                            </td>
                        <?php endif; ?>

                        <!-- Acciones Column -->
                        <td class="text-center" style="white-space: nowrap;">
                            <?php if ($is_ppd): ?>
                                <?php if (!$is_cancelled) { ?>
                                    <button type="button" class="btn btn-xs btn-success"
                                        style="background-color: #26B99A; border-color: #26B99A; font-weight: bold;"
                                        onclick="abrirModalPago(<?php echo $r['id']; ?>, '<?php echo htmlspecialchars($r['serie'] ?? ''); ?>', '<?php echo htmlspecialchars($r['folio'] ?? ''); ?>', <?php echo floatval($r['monto']); ?>, '<?php echo htmlspecialchars($r['uuid'] ?? ''); ?>', '<?php echo htmlspecialchars(addslashes($r['cliente'] ?? '')); ?>')"
                                        title="Generar Complemento de Pago (REP 2.0)">
                                        <i class="fa fa-money"></i> Pago
                                    </button>
                                <?php } else { ?>
                                    <span class="text-muted" style="font-size: 11px;">-</span>
                                <?php } ?>
                            <?php else: ?>
                                <?php if (!$is_cancelled) { ?>
                                    <a href="javascript:void(0);"
                                        onclick="confirmarCancelacion(<?php echo $r['id']; ?>, '<?php echo htmlspecialchars($r['serie'] ?? ''); ?>', '<?php echo htmlspecialchars($r['folio'] ?? ''); ?>', '<?php echo htmlspecialchars($r['cliente_email'] ? $r['cliente_email'] : ''); ?>')"
                                        title="Cancelar Factura" style="cursor: pointer;">
                                        <i class="fa fa-times-circle" style="font-size: 20px; color: #e74c3c; vertical-align: middle;"></i>
                                    </a>
                                <?php } else { ?>
                                    <i class="fa fa-times-circle" style="font-size: 20px; color: #ccc; cursor: not-allowed;"
                                        title="Ya cancelada"></i>
                                <?php } ?>
                            <?php endif; ?>
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
    $cust_id = intval($r['cust_id'] ?? 0);

    $para_emails = [];
    if (!empty($email)) {
        $para_emails[] = $email;
    }

    if ($cust_id > 0) {
        $sql_add_emails = "SELECT email FROM cust_emails WHERE cust_id = $cust_id AND is_active = 1";
        $res_add_emails = mysqli_query($conexion_gen, $sql_add_emails);
        if ($res_add_emails) {
            while ($row_add = mysqli_fetch_assoc($res_add_emails)) {
                if (!empty($row_add['email'])) {
                    $para_emails[] = trim($row_add['email']);
                }
            }
        }
    }

    if (empty($para_emails)) {
        echo json_encode(['status' => 'error', 'message' => 'El cliente no tiene un correo electrónico registrado en el catálogo.']);
        exit;
    }

    // Enviar correo
    try {
        require_once "../classes/Mailer.php";
        $mailer = new Mailer();

        $para = implode(', ', array_unique($para_emails));
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
                                    <td class='ambiente'>Ambiente:</td>
                                    <td class='value'>$ambiente </td>
                                </tr>
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
} else if ($action == 'cancel_invoice') {
    header('Content-Type: application/json');
    $invoice_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($invoice_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID de factura inválido.']);
        exit;
    }

    // Buscar datos de la factura
    $sql = "SELECT A.*, C.email, C.razon_social, C.rfc FROM facturas A LEFT JOIN cust C ON A.cust_id = C.id WHERE A.id = $invoice_id";
    $query = mysqli_query($conexion_gen, $sql);
    if (!$query || mysqli_num_rows($query) == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Factura no encontrada en la base de datos general.']);
        exit;
    }

    $r = mysqli_fetch_assoc($query);
    if (($r['estado'] ?? '') === 'Cancelada' || ($r['estatus'] ?? 1) == 0) {
        echo json_encode(['status' => 'error', 'message' => 'La factura ya se encuentra cancelada.']);
        exit;
    }

    $serie = $r['serie'] ?? '';
    $folio = $r['folio'] ?? '';
    $uuid = $r['uuid'] ?? '';
    $monto = isset($r['monto']) ? number_format((float) $r['monto'], 2, '.', '') : '0.00';
    $cust_id = $r['cust_id'] ?? 0;
    $email = trim($r['email'] ?? '');
    $nombre_cliente = trim($r['razon_social'] ?? '');
    $rfc_receptor = trim($r['rfc'] ?? '');
    if (empty($rfc_receptor)) {
        $rfc_receptor = "XAXX010101000";
    }

    // Configuración para facturación y cancelación desde config/config.php
    global $api_url_cancel, $api_sistema, $api_zona_horaria, $api_rfc_emisor, $api_pruebas, $api_url_blob;

    // Paso 1: Consultar estatus CFDI ante el SAT (tipo 2000)
    $sinube = new SinubeHelper($log_file);
    $estatus_cfdi_xml = '';
    try {
        $estatus_cfdi_xml = $sinube->consultarEstatusCFDI(
            $api_rfc_emisor,
            $api_pruebas,
            $api_rfc_emisor,
            $rfc_receptor,
            $monto,
            $uuid,
            $api_url_blob
        );
        error_log("[" . date('Y-m-d H:i:s') . "] Estatus CFDI SAT (tipo 2000) obtenido: " . $estatus_cfdi_xml . "\n", 3, $log_file);
    } catch (Exception $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] Error al consultar estatus CFDI SAT (tipo 2000): " . $e->getMessage() . "\n", 3, $log_file);
    }

    $cancelacion_motivo = isset($_POST['cancelacionMotivo']) && !empty($_POST['cancelacionMotivo']) ? trim($_POST['cancelacionMotivo']) : (isset($_POST['motivo']) && !empty($_POST['motivo']) ? trim($_POST['motivo']) : '02');

    // Paso 2: Construir XML Payload para la petición formal de cancelación incorporando estatusCfdiXml
    $xml_payload = '<Factura sistema="' . $api_sistema . '" serie="' . $serie . '" folio="' . $folio . '" zonaHoraria="' . $api_zona_horaria . '" cancelacionMotivo="02" estatusCfdiXml="' . htmlspecialchars($estatus_cfdi_xml, ENT_QUOTES | ENT_XML1, 'UTF-8') . '"/>';
    #$xml_payload = '<Factura sistema="' . $api_sistema . '" serie="' . $serie . '" folio="' . $folio . '" zonaHoraria="' . $api_zona_horaria . '" cancelacionMotivo="02"/>';

    error_log("[" . date('Y-m-d H:i:s') . "] Solicitud de cancelación enviada a Sinube. XML: " . $xml_payload . "\n", 3, $log_file);

    // Consumir el endpoint de Sinube
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url_cancel);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: text/xml',
        'Content-Length: ' . strlen($xml_payload)
    ));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    error_log("[" . date('Y-m-d H:i:s') . "] Respuesta de cancelación de Sinube (HTTP $http_code): " . $response . "\n", 3, $log_file);

    if ($http_code != 200) {
        echo json_encode(['status' => 'error', 'message' => 'Error al comunicarse con Sinube. HTTP Código: ' . $http_code . '. ' . $curl_error]);
        exit;
    }

    // Procesar la respuesta
    libxml_use_internal_errors(true);
    $xml_obj = simplexml_load_string($response);

    $cancellation_message = 'Cancelación exitosa en Sinube';
    if ($xml_obj) {
        $error_matches = $xml_obj->xpath("/Respuesta/error");
        if (!empty($error_matches) && trim((string) $error_matches[0]) !== '') {
            $error_msg = trim((string) $error_matches[0]);
            echo json_encode(['status' => 'error', 'message' => 'Error de cancelación devuelto por Sinube: ' . $error_msg]);
            exit;
        }

        $msg_matches = $xml_obj->xpath("/Respuesta/mensaje");
        if (!empty($msg_matches)) {
            $cancellation_message = trim((string) $msg_matches[0]);
        }
    } else {
        // En caso de que no venga en formato XML correcto pero tenga la palabra "error"
        if (stripos($response, 'error') !== false) {
            echo json_encode(['status' => 'error', 'message' => 'Error devuelto por Sinube: ' . strip_tags($response)]);
            exit;
        }
        $cancellation_message = strip_tags($response);
    }

    // Actualizar la factura en la base de datos general
    $sql_update = "UPDATE facturas SET estatus = 0, estado = 'Cancelada' WHERE id = $invoice_id";
    if (!mysqli_query($conexion_gen, $sql_update)) {
        echo json_encode(['status' => 'error', 'message' => 'CFDI cancelado en Sinube pero error al actualizar BD: ' . mysqli_error($conexion_gen)]);
        exit;
    }

    // Revertir el estatus de los tickets asociados en la sucursal a is_active = 1 (Activo)
    $mov_id_fac = $r['mov_id'] ?? '';
    $sucursal_fac = $r['sucursal'] ?? '';
    $branchesConfigs = getBranchesConfig();

    if (!empty($mov_id_fac)) {
        // Determinar qué sucursales consultar (la de la factura o todas si no está especificada)
        $target_branches = [];
        if (!empty($sucursal_fac) && isset($branchesConfigs[$sucursal_fac])) {
            $target_branches[$sucursal_fac] = $branchesConfigs[$sucursal_fac];
        } else {
            $target_branches = $branchesConfigs;
        }

        // Si es un ticket de grupo (ej: GRP-15)
        if (strpos($mov_id_fac, 'GRP-') === 0) {
            $group_id = intval(substr($mov_id_fac, 4));
            foreach ($target_branches as $bName => $bConfig) {
                $bConn = @mysqli_connect($bConfig['host'], $bConfig['user'], $bConfig['pass'], $bConfig['db']);
                if ($bConn) {
                    mysqli_set_charset($bConn, "utf8mb4");

                    // Actualizar estado del grupo
                    $stmt_grp = mysqli_prepare($bConn, "UPDATE groups_tickets SET status = 1 WHERE id = ?");
                    if ($stmt_grp) {
                        mysqli_stmt_bind_param($stmt_grp, "i", $group_id);
                        mysqli_stmt_execute($stmt_grp);
                        mysqli_stmt_close($stmt_grp);
                    }

                    // Revertir tickets pertenecientes al grupo a is_active = 1
                    $sql_grp_tickets = "UPDATE vtahead SET is_active = 1 WHERE mov_id IN (SELECT mov_id FROM groups_tickets_details WHERE group_id = ?)";
                    $stmt_grp_tck = mysqli_prepare($bConn, $sql_grp_tickets);
                    if ($stmt_grp_tck) {
                        mysqli_stmt_bind_param($stmt_grp_tck, "i", $group_id);
                        mysqli_stmt_execute($stmt_grp_tck);
                        mysqli_stmt_close($stmt_grp_tck);
                    }
                    mysqli_close($bConn);
                }
            }
            error_log("[" . date('Y-m-d H:i:s') . "] [INFO] [CANCELACION] Grupo $group_id revertido a estatus activo (is_active = 1) tras cancelación de factura $invoice_id.\n", 3, $log_file);
        } else {
            // Ticket individual o lista de mov_id
            $clean_mov_ids = array_map('trim', explode(',', $mov_id_fac));
            foreach ($target_branches as $bName => $bConfig) {
                $bConn = @mysqli_connect($bConfig['host'], $bConfig['user'], $bConfig['pass'], $bConfig['db']);
                if ($bConn) {
                    mysqli_set_charset($bConn, "utf8mb4");
                    foreach ($clean_mov_ids as $single_mov) {
                        if (empty($single_mov))
                            continue;
                        $stmt_tck = mysqli_prepare($bConn, "UPDATE vtahead SET is_active = 1 WHERE mov_id = ?");
                        if ($stmt_tck) {
                            mysqli_stmt_bind_param($stmt_tck, "s", $single_mov);
                            mysqli_stmt_execute($stmt_tck);
                            mysqli_stmt_close($stmt_tck);
                        }
                    }
                    mysqli_close($bConn);
                }
            }
            error_log("[" . date('Y-m-d H:i:s') . "] [INFO] [CANCELACION] Ticket(s) '$mov_id_fac' revertido(s) a estatus activo (is_active = 1) tras cancelación de factura $invoice_id.\n", 3, $log_file);
        }
    }

    $email_message = "No se envió correo (sin correo registrado)";

    $para_emails = [];
    if (!empty($email)) {
        $para_emails[] = $email;
    }

    if ($cust_id > 0) {
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

    if (!empty($para_emails)) {
        try {
            require_once "../classes/Mailer.php";
            $mailer = new Mailer();

            $para = implode(', ', array_unique($para_emails));
            $asunto = 'Notificación de Cancelación de Factura Electrónica Victoria - Folio: ' . $serie . ' ' . $folio;

            $mensajeHtml = "
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <title>Factura Electrónica Cancelada</title>
                    <style>
                        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f8f9fa; margin: 0; padding: 20px; color: #2c3e50; }
                        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); border: 1px solid #eef2f5; }
                        .header { background: linear-gradient(135deg, #1a1a1a, #000000); padding: 30px; text-align: center; color: #ffffff; border-bottom: 3px solid #b22222; }
                        .header h2 { margin: 0; font-size: 22px; font-weight: 600; letter-spacing: 0.5px; color: #b22222; text-transform: uppercase; }
                        .content { padding: 35px; }
                        .greeting { font-size: 18px; font-weight: bold; margin-bottom: 20px; color: #1a1a1a; }
                        .details-box { background-color: #fdfefe; border-left: 4px solid #b22222; padding: 20px; margin-bottom: 25px; border-radius: 4px; box-shadow: inset 0 0 10px rgba(0,0,0,0.01); border-top: 1px solid #f0f4f7; border-right: 1px solid #f0f4f7; border-bottom: 1px solid #f0f4f7; }
                        .details-box table { width: 100%; border-collapse: collapse; }
                        .details-box td { padding: 6px 0; font-size: 14px; }
                        .details-box td.label { font-weight: bold; color: #7f8c8d; width: 120px; }
                        .details-box td.value { color: #2c3e50; }
                        .footer { background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #95a5a6; border-top: 1px solid #ecf0f1; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Comprobante Fiscal Cancelado</h2>
                        </div>
                        <div class='content'>
                            <div class='greeting'>Estimado(a) $nombre_cliente,</div>
                            <p>Le informamos que el comprobante fiscal digital detallado a continuación ha sido **CANCELADO** en el sistema de facturación y ante el SAT.</p>
                            
                            <div class='details-box'>
                                <table>
                                    <tr>
                                        <td class='label'>UUID:</td>
                                        <td class='value'>$uuid</td>
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
                                        <td class='label'>Respuesta:</td>
                                        <td class='value' style='font-weight: bold; color: #b22222;'>$cancellation_message</td>
                                    </tr>
                                </table>
                            </div>
                            
                            <p style='font-size: 14px; color: #7f8c8d;'>Si requiere la reposición de este comprobante, favor de comunicarse con el departamento de facturación.</p>
                        </div>
                        <div class='footer'>
                            <p>Este es un envío automático generado por el Sistema de Facturación Victoria.<br>Por favor no responda a este correo.</p>
                        </div>
                    </div>
                </body>
                </html>
            ";

            if ($mailer->send($para, $asunto, $mensajeHtml)) {
                $email_message = "Notificación de cancelación enviada exitosamente a $para";
            } else {
                $email_message = "No se pudo enviar el correo de notificación";
            }
        } catch (\Exception $e) {
            $email_message = "Error al enviar notificación: " . $e->getMessage();
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Factura cancelada exitosamente.',
        'sinube_message' => $cancellation_message,
        'email_message' => $email_message
    ]);
    exit;
} else if ($action == 'get_invoice_ppd_details') {
    header('Content-Type: application/json');
    $ids_raw = isset($_REQUEST['ids']) ? $_REQUEST['ids'] : (isset($_REQUEST['id']) ? [$_REQUEST['id']] : []);
    if (!is_array($ids_raw)) {
        // Podría venir como string separado por comas o JSON
        $decoded = json_decode((string) $ids_raw, true);
        if (is_array($decoded)) {
            $ids_raw = $decoded;
        } else {
            $ids_raw = explode(',', (string) $ids_raw);
        }
    }
    $ids = array_values(array_filter(array_map('intval', $ids_raw), function ($val) {
        return $val > 0;
    }));

    if (empty($ids)) {
        echo json_encode(['status' => 'error', 'message' => 'ID(s) de factura inválido(s).']);
        exit;
    }

    $ids_in = implode(',', $ids);
    $sql = "
        SELECT F.*, C.razon_social as cust_name, C.rfc as cust_rfc, C.regimen_fiscal as cust_regimen, 
               C.cp as cust_zip, C.es_persona_fisica, C.nombre as cust_nombre, 
               C.ap_paterno as cust_ap_paterno, C.ap_materno as cust_ap_materno
        FROM facturas F
        LEFT JOIN cust C ON F.cust_id = C.id
        WHERE F.id IN ($ids_in)
    ";
    $query = mysqli_query($conexion_gen, $sql);
    if (!$query || mysqli_num_rows($query) == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Factura(s) no encontrada(s).']);
        exit;
    }

    $items = [];
    $cust_id_check = null;
    $cliente_info = null;
    $total_monto = 0.0;
    $total_saldo = 0.0;
    $first_branch = 'Obrador';
    $first_serie = 'O';

    while ($inv = mysqli_fetch_assoc($query)) {
        if ($cust_id_check === null) {
            $cust_id_check = $inv['cust_id'];
            $cliente_info = [
                'cust_id' => "213",//$inv['cust_id'],
                'cliente' => $inv['cust_name'] ?? 'Cliente General',
                'rfc' => $inv['cust_rfc'] ?? 'XAXX010101000',
                'regimen_fiscal' => $inv['cust_regimen'] ?? '601',
                'domicilio_fiscal' => $inv['cust_zip'] ?? '87000',
                'es_persona_fisica' => $inv['es_persona_fisica'] ?? '0',
                'nombre' => $inv['cust_nombre'] ?? '',
                'ap_paterno' => $inv['cust_ap_paterno'] ?? '',
                'ap_materno' => $inv['cust_ap_materno'] ?? ''
            ];
            $first_branch = $inv['sucursal'] ?? 'Obrador';
            $first_serie = !empty($inv['serie']) ? trim($inv['serie']) : (isset($branchSeriesMap[$inv['sucursal']]) ? $branchSeriesMap[$inv['sucursal']] : 'O');
        } else if ($cust_id_check != $inv['cust_id']) {
            echo json_encode(['status' => 'error', 'message' => 'Todas las facturas seleccionadas deben pertenecer al mismo cliente.']);
            exit;
        }

        // Obtener parcialidades previas y total pagado
        $inv_id = intval($inv['id']);
        $stmt_prev = $conexion_gen->prepare("
            SELECT 
                IFNULL(SUM(monto_pagado), 0) AS total_pagado,
                COUNT(*) AS total_parcialidades
            FROM deposito_factura 
            WHERE factura_id = ? AND is_active = 1
        ");
        $stmt_prev->bind_param("i", $inv_id);
        $stmt_prev->execute();
        $res_prev = $stmt_prev->get_result();
        $row_prev = $res_prev->fetch_assoc();
        $stmt_prev->close();

        $monto_factura = floatval($inv['monto']);
        $total_pagado = floatval($row_prev['total_pagado'] ?? 0);
        $saldo_pendiente = max(0, $monto_factura - $total_pagado);
        $parcialidad = intval($row_prev['total_parcialidades'] ?? 0) + 1;

        $total_monto += $monto_factura;
        $total_saldo += $saldo_pendiente;

        $items[] = [
            'id' => $inv['id'],
            'uuid' => $inv['uuid'],
            'serie' => $inv['serie'] ?? '',
            'folio' => $inv['folio'] ?? '',
            'monto' => $monto_factura,
            'total_pagado' => $total_pagado,
            'saldo_pendiente' => $saldo_pendiente,
            'parcialidad' => $parcialidad,
            'mov_id' => $inv['mov_id'] ?? '',
            'sucursal' => $inv['sucursal'] ?? 'Obrador',
            'xml_url' => $inv['xml_url'] ?? ''
        ];
    }

    // Obtener serie y folio consecutivo desde Sinube
    $target_serie = $first_serie;
    $next_folio = 1;
    $sinube = new SinubeHelper(__DIR__ . '/../logs/sinube_api.log');
    $sinube->log(__LINE__);

    try {
        $folioData = $sinube->getFolioActual($api_url_cert, $api_no_certificado, $target_serie);
        $next_folio = intval($folioData['folioActual']) + 1;
        $target_serie = $folioData['serie'];
    } catch (\Exception $e) {
        $stmt_fol = $conexion_gen->prepare("SELECT IFNULL(MAX(CAST(folio AS UNSIGNED)), 0) + 1 AS next_fol FROM facturas WHERE serie = ?");
        $stmt_fol->bind_param("s", $target_serie);
        $stmt_fol->execute();
        $res_fol = $stmt_fol->get_result();
        $row_fol = $res_fol->fetch_assoc();
        $next_folio = intval($row_fol['next_fol'] ?? 1);
        $stmt_fol->close();
    }

    $first_item = $items[0] ?? [];

    echo json_encode([
        'status' => 'success',
        'data' => [
            'id' => $first_item['id'] ?? 0,
            'uuid' => $first_item['uuid'] ?? '',
            'serie' => $first_item['serie'] ?? '',
            'folio' => $first_item['folio'] ?? '',
            'serie_pago' => $target_serie,
            'folio_pago' => (string) $next_folio,
            'monto' => (count($items) === 1) ? ($first_item['monto'] ?? 0) : $total_monto,
            'total_pagado' => (count($items) === 1) ? ($first_item['total_pagado'] ?? 0) : 0,
            'saldo_pendiente' => (count($items) === 1) ? ($first_item['saldo_pendiente'] ?? 0) : $total_saldo,
            'parcialidad' => $first_item['parcialidad'] ?? 1,
            'cliente' => $cliente_info['cliente'] ?? 'Cliente General',
            'rfc' => $cliente_info['rfc'] ?? 'XAXX010101000',
            'regimen_fiscal' => $cliente_info['regimen_fiscal'] ?? '601',
            'domicilio_fiscal' => $cliente_info['domicilio_fiscal'] ?? '87000',
            'es_persona_fisica' => $cliente_info['es_persona_fisica'] ?? '0',
            'items' => $items,
            'total_monto' => $total_monto,
            'total_saldo' => $total_saldo,
            'count' => count($items)
        ]
    ]);
    exit;

} else if ($action == 'timbrar_pago_ppd') {
    $sinube = new SinubeHelper(__DIR__ . '/../logs/sinube_api.log');
    header('Content-Type: application/json');
    $forma_pago = isset($_POST['forma_pago']) ? trim($_POST['forma_pago']) : '03';
    $fecha_pago_input = isset($_POST['fecha_pago']) ? trim($_POST['fecha_pago']) : '';
    $num_operacion = isset($_POST['num_operacion']) ? trim($_POST['num_operacion']) : '';

    // Soporte para array de facturas (múltiple o simple)
    $invoices_input = [];
    if (isset($_POST['invoices'])) {
        if (is_array($_POST['invoices'])) {
            $invoices_input = $_POST['invoices'];
        } else {
            $invoices_input = json_decode($_POST['invoices'], true) ?? [];
        }
    } else if (isset($_POST['id'])) {
        $invoices_input[] = [
            'id' => intval($_POST['id']),
            'monto_pago' => floatval($_POST['monto_pago'] ?? 0)
        ];
    }

    if (empty($invoices_input)) {
        echo json_encode(['status' => 'error', 'message' => 'No se especificaron facturas para procesar el pago.']);
        exit;
    }

    require_once __DIR__ . '/../classes/SiNube/autoload.php';

    $ids = [];
    $montos_map = [];
    foreach ($invoices_input as $inv_in) {
        $iid = intval($inv_in['id'] ?? 0);
        $imp = floatval($inv_in['monto_pago'] ?? ($inv_in['monto'] ?? 0));
        if ($iid > 0 && $imp > 0) {
            $ids[] = $iid;
            $montos_map[$iid] = $imp;
        }
    }

    if (empty($ids)) {
        echo json_encode(['status' => 'error', 'message' => 'El monto a pagar debe ser mayor a cero en al menos una factura.']);
        exit;
    }

    $ids_in = implode(',', $ids);
    $sql = "
        SELECT F.*, C.razon_social as cust_name, C.rfc as cust_rfc, C.regimen_fiscal as cust_regimen, 
               C.cp as cust_zip, C.es_persona_fisica, C.nombre as cust_nombre, 
               C.ap_paterno as cust_ap_paterno, C.ap_materno as cust_ap_materno
        FROM facturas F
        LEFT JOIN cust C ON F.cust_id = C.id
        WHERE F.id IN ($ids_in)
    ";
    $query = mysqli_query($conexion_gen, $sql);
    if (!$query || mysqli_num_rows($query) == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Facturas no encontradas en la base de datos.']);
        exit;
    }

    $invoices_db = [];
    $cust_id_check = null;
    $cliente_data = null;
    $first_serie = 'O';
    $first_branch = 'Obrador';

    while ($row = mysqli_fetch_assoc($query)) {
        if ($cust_id_check === null) {
            $cust_id_check = $row['cust_id'];
            $cliente_data = $row;
            $first_branch = $row['sucursal'] ?? 'Obrador';
            $first_serie = !empty($row['serie']) ? trim($row['serie']) : (isset($branchSeriesMap[$row['sucursal']]) ? $branchSeriesMap[$row['sucursal']] : 'O');
        } else if ($cust_id_check != $row['cust_id']) {
            echo json_encode(['status' => 'error', 'message' => 'Todas las facturas a pagar deben pertenecer al mismo cliente.']);
            exit;
        }

        if ($row['estado'] === 'Cancelada' || (isset($row['estatus']) && $row['estatus'] == 0)) {
            echo json_encode(['status' => 'error', 'message' => 'No se puede aplicar pago a una factura cancelada (Folio: ' . ($row['folio'] ?? $row['id']) . ').']);
            exit;
        }

        $invoices_db[intval($row['id'])] = $row;
    }

    // Formateo de fecha de pago (ISO 8601)
    $fecha_pago_iso = !empty($fecha_pago_input) ? date('Y-m-d\TH:i:s', strtotime($fecha_pago_input)) : date('Y-m-d\TH:i:s');

    // Construir lista de documentos relacionados para SiNube
    $documentos_sinube = [];
    $monto_total_rep = 0.0;
    $invoice_payments_to_save = [];

    foreach ($ids as $inv_id) {
        if (!isset($invoices_db[$inv_id])) {
            continue;
        }
        $inv = $invoices_db[$inv_id];
        $monto_pago = $montos_map[$inv_id];

        // Consultar pagos previos
        $stmt_prev = $conexion_gen->prepare("
            SELECT 
                IFNULL(SUM(monto_pagado), 0) AS total_pagado,
                COUNT(*) AS total_parcialidades
            FROM deposito_factura 
            WHERE factura_id = ? AND is_active = 1
        ");
        $stmt_prev->bind_param("i", $inv_id);
        $stmt_prev->execute();
        $res_prev = $stmt_prev->get_result();
        $row_prev = $res_prev->fetch_assoc();
        $stmt_prev->close();

        $monto_factura = floatval($inv['monto']);
        $total_pagado_previo = floatval($row_prev['total_pagado'] ?? 0);
        $saldo_anterior = max(0, $monto_factura - $total_pagado_previo);

        if ($monto_pago > ($saldo_anterior + 0.01)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'El monto a pagar ($' . number_format($monto_pago, 2) . ') supera el saldo pendiente ($' . number_format($saldo_anterior, 2) . ') de la factura ' . ($inv['serie'] ?? '') . '-' . ($inv['folio'] ?? $inv['id']) . '.'
            ]);
            exit;
        }

        $saldo_insoluto = max(0, $saldo_anterior - $monto_pago);
        $parcialidad = intval($row_prev['total_parcialidades'] ?? 0) + 1;

        // Desglose de IVA (REP 2.0)
        $tasa_iva = 0.16;
        if (!empty($inv['xml_url'])) {
            $ctx = stream_context_create(['http' => ['timeout' => 3]]);
            $xml_content = @file_get_contents($inv['xml_url'], false, $ctx);
            if ($xml_content) {
                try {
                    libxml_use_internal_errors(true);
                    $xml_doc = simplexml_load_string($xml_content);
                    if ($xml_doc) {
                        $ns = $xml_doc->getNamespaces(true);
                        if (isset($ns['cfdi'])) {
                            $xml_doc->registerXPathNamespace('cfdi', $ns['cfdi']);
                            $traslados = $xml_doc->xpath('//cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado | //cfdi:Concepto/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado');
                            if (!empty($traslados)) {
                                foreach ($traslados as $tr) {
                                    if (isset($tr['Impuesto']) && (string) $tr['Impuesto'] === '002' && isset($tr['TasaOCuota'])) {
                                        $tasa_iva = floatval($tr['TasaOCuota']);
                                        break;
                                    }
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                }
            }
        }

        $base_iva = ($tasa_iva > 0) ? ($monto_pago / (1 + $tasa_iva)) : $monto_pago;
        $monto_iva = ($tasa_iva > 0) ? ($monto_pago - $base_iva) : 0.0;
        $porcentaje_iva_str = ($tasa_iva > 0) ? (string) round($tasa_iva * 100) : '0';
        $tipo_iva_str = ($tasa_iva > 0) ? '1' : '0';

        $documentos_sinube[] = [
            'serie' => $inv['serie'] ?? '',
            'folio' => $inv['folio'] ?? '',
            'idDocumento' => $inv['uuid'],
            'monedaDR' => 'MXN',
            'metodoDePagoDR' => 'PPD',
            'impPagado' => $monto_pago,
            'numParcialidad' => $parcialidad,
            'impSaldoAnt' => $saldo_anterior,
            'impSaldoInsoluto' => $saldo_insoluto,
            'montoIVA' => round($monto_iva, 2),
            'montoBaseIVA' => round($base_iva, 2),
            'tipoIVA' => $tipo_iva_str,
            'porcentajeIVA' => $porcentaje_iva_str
        ];

        $monto_total_rep += $monto_pago;
        $invoice_payments_to_save[] = [
            'factura_id' => $inv_id,
            'monto_pagado' => $monto_pago,
            'parcialidad' => $parcialidad,
            'saldo_insoluto' => $saldo_insoluto,
            'mov_id' => $inv['mov_id'] ?? ''
        ];
    }

    // Obtener Serie y Folio consecutivo de SiNube
    $target_serie = $first_serie;
    $serie_pago = $target_serie;
    $folio_pago = 1;

    try {
        $folioData = $sinube->getFolioActual($api_url_cert, $api_no_certificado, $target_serie);
        $serie_pago = $folioData['serie'];
        $folio_pago = intval($folioData['folioActual']) + 1;
    } catch (\Exception $e) {
        $stmt_fol = $conexion_gen->prepare("SELECT IFNULL(MAX(CAST(folio AS UNSIGNED)), 0) + 1 AS next_fol FROM facturas WHERE serie = ?");
        $stmt_fol->bind_param("s", $target_serie);
        $stmt_fol->execute();
        $res_fol = $stmt_fol->get_result();
        $row_fol = $res_fol->fetch_assoc();
        $folio_pago = intval($row_fol['next_fol'] ?? 1);
        $stmt_fol->close();
    }

    // Identificador REP compuesto por el id de ticket ($mov_id) y número de pago/parcialidad
    $rep_parts = [];
    foreach ($invoice_payments_to_save as $pay_item) {
        $t_mov = !empty($pay_item['mov_id']) ? trim($pay_item['mov_id']) : $pay_item['factura_id'];
        $p_num = $pay_item['parcialidad'];
        $rep_parts[] = "{$t_mov}-{$p_num}";
    }
    $mov_id_rep = !empty($rep_parts) ? 'REP-' . implode(',', $rep_parts) : 'REP-' . time();

    // Datos del Receptor
    $es_fisica = (!empty($cliente_data['es_persona_fisica']) && in_array((string) $cliente_data['es_persona_fisica'], ['1', 'true', 'TRUE'], true)) ? '1' : '0';
    $apellido_paterno = ($es_fisica === '1') ? trim($cliente_data['cust_ap_paterno'] ?? '') : '';
    $nombre_receptor = ($es_fisica === '1') ? trim($cliente_data['cust_nombre'] ?? '') : '';
    $apellido_materno = ($es_fisica === '1') ? trim($cliente_data['cust_ap_materno'] ?? '') : '';

    $pagoData = [
        'sistema' => $api_sistema ?? 'SegunRFC',
        'noCertificado' => $api_no_certificado ?? '',
        'serie' => $serie_pago,
        'folio' => (string) $folio_pago,
        'monto' => $monto_total_rep,
        'formaDePagoP' => $forma_pago,
        'fechaPago' => $fecha_pago_iso,
        'numOperacion' => $num_operacion,
        'rfcEmisor' => $api_rfc_emisor ?? 'URE180429TM6-39',
        'nomArchivoDescarga' => "{$mov_id_rep}-{$serie_pago}-{$folio_pago}",
        'receptor' => [
            'cliente' => "213",//(string) ($cliente_data['cust_id'] ?? '1'),
            'rfc' => $cliente_data['cust_rfc'] ?? 'XAXX010101000',
            'razonSocial' => $cliente_data['cust_name'] ?? 'PUBLICO EN GENERAL',
            'esPersonaFisica' => $es_fisica,
            'nombre' => $nombre_receptor,
            'apellidoPaterno' => $apellido_paterno,
            'apellidoMaterno' => $apellido_materno,
            'domicilioFiscal' => $cliente_data['cust_zip'] ?? '87000',
            'regimenFiscal' => $cliente_data['cust_regimen'] ?? '601',
            'usoCFDI' => 'CP01'
        ],
        'documentos' => $documentos_sinube
    ];

    // Instanciar servicio y timbrar
    $pagoService = new \App\Services\SiNube\SiNubePagoService(
        rfcEmisor: $api_rfc_emisor ?? 'URE180429TM6-39',
        sucursal: $api_sucursal ?? 'Matriz',
        usuario: $api_usuario ?? '',
        password: $api_password ?? '',
        baseUrl: $api_base_url ?? 'https://ep-dot-facturanube.appspot.com',
        logPath: __DIR__ . '/../logs/depositos.log'
    );

    $pagoDTO = \App\Services\SiNube\SiNubePagoFactory::fromArray($pagoData);
    $response = $pagoService->timbrarPago($pagoDTO);

    if (!$response->success) {
        echo json_encode([
            'status' => 'error',
            'message' => $response->mensaje ?? 'Error al timbrar el pago en SiNube.',
            'raw' => $response->rawResponse
        ]);
        exit;
    }

    // Persistir el comprobante de pago en la base de datos
    mysqli_begin_transaction($conexion_gen);
    try {
        $user_id = intval($_SESSION['user_id'] ?? 1);
        $cust_id = intval($cliente_data['cust_id'] ?? 0);
        $branch = $first_branch;
        $uuid_rep = $response->uuid;
        $xml_rep = $response->xmlUrl ?? '';
        $pdf_rep = $response->pdfUrl ?? '';

        $stmt_rep = $conexion_gen->prepare("
            INSERT INTO facturas (mov_id, uuid, monto, metodo_pago, usuario_id, serie, folio, xml_url, pdf_url, estatus, estado, cust_id, sucursal)
            VALUES (?, ?, ?, 'Complemento de Pago', ?, ?, ?, ?, ?, 1, 'Activa', ?, ?)
        ");
        if (!$stmt_rep) {
            throw new \Exception("Error al preparar guardado de REP: " . mysqli_error($conexion_gen));
        }

        $stmt_rep->bind_param("ssdsssssis", $mov_id_rep, $uuid_rep, $monto_total_rep, $user_id, $serie_pago, $folio_pago, $xml_rep, $pdf_rep, $cust_id, $branch);
        if (!$stmt_rep->execute()) {
            throw new \Exception("Error al guardar REP en base de datos: " . $stmt_rep->error);
        }
        $stmt_rep->close();

        // Registrar relaciones en deposito_factura para cada factura
        $deposito_id_dummy = 0;
        $stmt_df = $conexion_gen->prepare("
            INSERT INTO deposito_factura (deposito_id, factura_id, monto_pagado, is_active)
            VALUES (?, ?, ?, 1)
        ");
        if ($stmt_df) {
            foreach ($invoice_payments_to_save as $pay_item) {
                $f_id = $pay_item['factura_id'];
                $m_pag = $pay_item['monto_pagado'];
                $stmt_df->bind_param("iid", $deposito_id_dummy, $f_id, $m_pag);
                $stmt_df->execute();
            }
            $stmt_df->close();
        }

        mysqli_commit($conexion_gen);

        @file_put_contents(
            __DIR__ . '/../logs/depositos.log',
            "[" . date('Y-m-d H:i:s') . "] [INFO] [REP] Complemento de pago timbrado y registrado con mov_id '{$mov_id_rep}', UUID '{$uuid_rep}', serie/folio '{$serie_pago}-{$folio_pago}', monto {$monto_total_rep}\n",
            FILE_APPEND
        );

        echo json_encode([
            'status' => 'success',
            'message' => 'Complemento de Pago (REP 2.0) timbrado exitosamente en SiNube por $' . number_format($monto_total_rep, 2) . ' para ' . count($invoice_payments_to_save) . ' factura(s).',
            'mov_id' => $mov_id_rep,
            'uuid' => $uuid_rep,
            'xml' => $xml_rep,
            'pdf' => $pdf_rep,
            'serie' => $serie_pago,
            'folio' => $folio_pago,
            'total_facturas' => count($invoice_payments_to_save),
            'monto_total' => $monto_total_rep
        ]);
        exit;

    } catch (\Exception $e) {
        mysqli_rollback($conexion_gen);
        echo json_encode([
            'status' => 'error',
            'message' => 'El pago se timbró en SiNube (UUID: ' . $response->uuid . '), pero ocurrió un error al registrar en BD: ' . $e->getMessage()
        ]);
        exit;
    }
}
?>