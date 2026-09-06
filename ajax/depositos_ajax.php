<?php
/**
 * ajax/depositos_ajax.php
 * Controlador AJAX para la gestión de depósitos por sucursal
 */
declare(strict_types=1);

include "../config/config.php";

/**
 * Helper to dynamically extract tax rate from printed invoice XML URL.
 */
function get_invoice_tax_rate($xml_url) {
    if (empty($xml_url)) {
        return 0.16; // default fallback
    }
    $ctx = stream_context_create(['http' => ['timeout' => 3]]);
    $xml_content = @file_get_contents($xml_url, false, $ctx);
    if (!$xml_content) {
        return 0.16; // default fallback
    }
    try {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_content);
        if ($xml) {
            $ns = $xml->getNamespaces(true);
            if (isset($ns['cfdi'])) {
                $xml->registerXPathNamespace('cfdi', $ns['cfdi']);
                $traslados = $xml->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado');
                if (!empty($traslados)) {
                    foreach ($traslados as $traslado) {
                        if (isset($traslado['Impuesto']) && (string)$traslado['Impuesto'] === '002') {
                            if (isset($traslado['TasaOCuota'])) {
                                return floatval($traslado['TasaOCuota']);
                            }
                        }
                    }
                }
                $concept_traslados = $xml->xpath('//cfdi:Concepto/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado');
                if (!empty($concept_traslados)) {
                    foreach ($concept_traslados as $traslado) {
                        if (isset($traslado['Impuesto']) && (string)$traslado['Impuesto'] === '002') {
                            if (isset($traslado['TasaOCuota'])) {
                                return floatval($traslado['TasaOCuota']);
                            }
                        }
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        // ignore and fallback
    }
    return 0.16;
}

$action = $_REQUEST['action'] ?? '';
$workflow = "[DEPOSITOS_CRUD]";

if ($action === 'select2_bancos') {
    header('Content-Type: application/json');
    $q = $_REQUEST['q'] ?? '';
    
    $sWhere = " WHERE is_active = 1 ";
    if (!empty($q)) {
        $sWhere .= " AND nombre LIKE ? ";
    }
    
    $stmt = $conexion_gen->prepare("SELECT id, nombre, rfc FROM bancos $sWhere ORDER BY nombre ASC LIMIT 20");
    if ($stmt) {
        if (!empty($q)) {
            $like_q = "%$q%";
            $stmt->bind_param("s", $like_q);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'id' => $row['id'],
                'text' => $row['nombre'] . ($row['rfc'] ? " (" . $row['rfc'] . ")" : "")
            ];
        }
        $stmt->close();
        echo json_encode(['results' => $data]);
    } else {
        echo json_encode(['results' => [], 'error' => mysqli_error($conexion_gen)]);
    }
    exit;
}

if ($action === 'get_ppd_invoices') {
    header('Content-Type: application/json');
    $cust_id = intval($_GET['cust_id'] ?? 0);
    $invoices = [];
    
    if ($cust_id > 0) {
        // Obtenemos facturas del cliente activas (estatus = 1) y con método de pago PPD/Por definir
        $stmt = $conexion_gen->prepare("
            SELECT F.id, F.mov_id, F.uuid, F.monto, F.serie, F.folio, F.fecha_factura, F.xml_url,
                   (F.monto 
                    - IFNULL((SELECT SUM(monto_pagado) FROM deposito_factura WHERE factura_id = F.id AND is_active = 1), 0)
                    - IFNULL((SELECT SUM(monto) FROM depositos WHERE factura_id = F.id AND is_active = 1 AND parent_id IS NULL), 0)
                   ) AS saldo_restante
            FROM facturas F
            WHERE F.cust_id = ? 
              AND F.estatus = 1 
              AND (F.metodo_pago LIKE '%definir%' OR F.metodo_pago LIKE '%99%')
            ORDER BY F.id DESC
        ");
        if ($stmt) {
            $stmt->bind_param("i", $cust_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $saldo_restante = floatval($row['saldo_restante']);
                if ($saldo_restante <= 0.01) {
                    continue; // Saltar facturas ya pagadas
                }
                $row['saldo_restante'] = $saldo_restante;
                $folio_desc = (!empty($row['serie']) ? $row['serie'] . "-" : "") . $row['folio'];
                $fecha_f = !empty($row['fecha_factura']) ? date('d-m-Y', strtotime($row['fecha_factura'])) : '---';
                $row['display_text'] = "Folio: $folio_desc | Total: $" . number_format((float)$row['monto'], 2) . " | Saldo Pend: $" . number_format($saldo_restante, 2) . " | Fecha: $fecha_f";
                $invoices[] = $row;
            }
            $stmt->close();
        }
    }
    echo json_encode(['status' => 'success', 'invoices' => $invoices]);
    exit;
}

if ($action === 'list') {
    $branch = $_REQUEST['branch'] ?? '';
    $q = $_REQUEST['q'] ?? '';
    $page = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;
    $per_page = isset($_REQUEST['per_page']) ? intval($_REQUEST['per_page']) : 25;
    $offset = ($page - 1) * $per_page;

    if (empty($branch)) {
        echo '<div class="alert alert-danger">Error: Sucursal no especificada.</div>';
        exit;
    }

    $sWhere = " WHERE D.sucursal = ? AND D.is_active = 1 AND D.parent_id IS NULL ";
    if (!empty($q)) {
        $sWhere .= " AND (C.razon_social LIKE ? OR D.referencia LIKE ? OR B.nombre LIKE ?) ";
    }

    // Contar registros
    $count_sql = "
        SELECT count(*) AS numrows 
        FROM depositos D 
        LEFT JOIN cust C ON D.cust_id = C.id
        LEFT JOIN bancos B ON D.banco_id = B.id
        $sWhere
    ";
    
    $stmt_count = $conexion_gen->prepare($count_sql);
    if ($stmt_count) {
        if (!empty($q)) {
            $like_q = "%$q%";
            $stmt_count->bind_param("ssss", $branch, $like_q, $like_q, $like_q);
        } else {
            $stmt_count->bind_param("s", $branch);
        }
        $stmt_count->execute();
        $res_count = $stmt_count->get_result();
        $row_count = $res_count->fetch_assoc();
        $numrows = intval($row_count['numrows'] ?? 0);
        $stmt_count->close();
    } else {
        $numrows = 0;
    }

    $total_pages = ceil($numrows / $per_page);

    // Obtener los datos
    $data_sql = "
        SELECT D.*, C.razon_social AS cliente, C.rfc AS cliente_rfc, B.nombre AS banco, 
               F.serie AS factura_serie, F.folio AS factura_folio, F.uuid AS factura_uuid
        FROM depositos D
        LEFT JOIN cust C ON D.cust_id = C.id
        LEFT JOIN bancos B ON D.banco_id = B.id
        LEFT JOIN facturas F ON D.factura_id = F.id
        $sWhere
        ORDER BY D.id DESC 
        LIMIT ?, ?
    ";

    $stmt_data = $conexion_gen->prepare($data_sql);
    if ($stmt_data) {
        if (!empty($q)) {
            $like_q = "%$q%";
            $stmt_data->bind_param("ssssii", $branch, $like_q, $like_q, $like_q, $offset, $per_page);
        } else {
            $stmt_data->bind_param("sii", $branch, $offset, $per_page);
        }
        $stmt_data->execute();
        $query = $stmt_data->get_result();
        
        if ($numrows > 0) {
            ?>
            <table class="table table-striped jambo_table bulk_action">
                <thead>
                    <tr class="headings">
                        <th>Fecha Creación</th>
                        <th>Cliente</th>
                        <th>Banco</th>
                        <th class="text-right">Monto Original</th>
                        <th class="text-right">Saldo Restante</th>
                        <th>Referencia</th>
                        <th>Factura Ligada</th>
                        <th>Fecha Actualización</th>
                        <th class="text-center no-link last"><span class="nobr">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    while ($row = $query->fetch_assoc()) {
                        $id = $row['id'];
                        $fecha_c = date('d-m-Y H:i', strtotime($row['created_at']));
                        $fecha_a = date('d-m-Y H:i', strtotime($row['updated_at']));
                        $saldo = floatval($row['saldo']);
                        
                        $factura_ligada = "---";
                        if ($row['factura_id']) {
                            $fact_desc = (!empty($row['factura_serie']) ? $row['factura_serie'] . "-" : "") . $row['factura_folio'];
                            $factura_ligada = "<strong>$fact_desc</strong> <br><small class='text-muted'>" . substr($row['factura_uuid'], 0, 15) . "...</small>";
                        }
                        
                        $saldo_color = ($saldo > 0) ? "#26B99A" : "#e74c3c";
                        ?>
                        <tr>
                            <td><?php echo $fecha_c; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['cliente'] ?? ''); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($row['cliente_rfc'] ?? ''); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($row['banco'] ?? ''); ?></td>
                            <td align="right" style="font-weight: bold; color: #34495e;">$<?php echo number_format((float)$row['monto'], 2); ?></td>
                            <td align="right" style="font-weight: bold; color: <?php echo $saldo_color; ?>;">$<?php echo number_format($saldo, 2); ?></td>
                            <td><?php echo htmlspecialchars($row['referencia']); ?></td>
                            <td><?php echo $factura_ligada; ?></td>
                            <td><?php echo $fecha_a; ?></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-info btn-xs" title="Agregar Abono" onclick="openAbonoModal(<?php echo $id; ?>, '<?php echo htmlspecialchars(addslashes($row['cliente'] ?? '')); ?>', <?php echo $saldo; ?>)" style="margin: 0;">
                                    <i class="fa fa-plus"></i> Abono
                                </button>
                                <button type="button" class="btn btn-danger btn-xs" title="Eliminar Depósito" onclick="deleteDeposito(<?php echo $id; ?>)" style="margin: 0;">
                                    <i class="fa fa-trash"></i> Eliminar
                                </button>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            
            <div class="row">
                <div class="col-sm-6">
                    Mostrando <?php echo $offset + 1; ?> a <?php echo min($offset + $per_page, $numrows); ?> de <?php echo $numrows; ?> registros
                </div>
                <div class="col-sm-6 text-right">
                    <ul class="pagination pagination-split" style="margin:0;">
                        <?php if ($page > 1) { ?>
                            <li><a href="javascript:void(0);" onclick="load(<?php echo $page - 1; ?>)">&laquo;</a></li>
                        <?php } ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
                            <?php if ($i > $page - 3 && $i < $page + 3) { ?>
                                <li class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a href="javascript:void(0);" onclick="load(<?php echo $i; ?>)"><?php echo $i; ?></a>
                                </li>
                            <?php } ?>
                        <?php } ?>
                        <?php if ($page < $total_pages) { ?>
                            <li><a href="javascript:void(0);" onclick="load(<?php echo $page + 1; ?>)">&raquo;</a></li>
                        <?php } ?>
                    </ul>
                </div>
            </div>
            <?php
        } else {
            echo '<div class="alert alert-warning">No se encontraron depósitos registrados para esta sucursal.</div>';
        }
        $stmt_data->close();
    } else {
        echo '<div class="alert alert-danger">Error al preparar consulta de listado: ' . mysqli_error($conexion_gen) . '</div>';
    }
    exit;
}

if ($action === 'save') {
    header('Content-Type: application/json');
    
    $branch = $_POST['branch'] ?? '';
    $cust_id = intval($_POST['cust_id'] ?? 0);
    $banco_id = intval($_POST['banco_id'] ?? 0);
    $monto = floatval($_POST['monto'] ?? 0);
    $referencia = trim($_POST['referencia'] ?? '');
    $factura_id = isset($_POST['factura_id']) && !empty($_POST['factura_id']) ? intval($_POST['factura_id']) : null;
    
    $errors = [];
    if (empty($branch)) $errors[] = "La sucursal es obligatoria.";
    if ($cust_id <= 0) $errors[] = "Debe seleccionar un cliente válido.";
    if ($banco_id <= 0) $errors[] = "Debe seleccionar un banco válido.";
    if ($monto <= 0) $errors[] = "El monto de depósito debe ser mayor a 0.";
    if (empty($referencia)) $errors[] = "La referencia de la transferencia es obligatoria.";
    
    if (!empty($errors)) {
        echo json_encode(['status' => 'error', 'message' => implode("<br>", $errors)]);
        exit;
    }
    
    $saldo_inicial = ($factura_id !== null) ? 0.00 : $monto;
    
    $stmt = $conexion_gen->prepare("
        INSERT INTO depositos (sucursal, cust_id, banco_id, monto, referencia, factura_id, parent_id, saldo, is_active)
        VALUES (?, ?, ?, ?, ?, ?, NULL, ?, 1)
    ");
    
    if ($stmt) {
        $stmt->bind_param("siidsid", $branch, $cust_id, $banco_id, $monto, $referencia, $factura_id, $saldo_inicial);
        if ($stmt->execute()) {
            sys_log("$workflow Depósito guardado para cliente ID $cust_id en $branch de $$monto", "INFO");
            echo json_encode(['status' => 'success', 'message' => "Depósito registrado correctamente."]);
        } else {
            echo json_encode(['status' => 'error', 'message' => "Error al ejecutar inserción: " . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => "Error al preparar inserción: " . mysqli_error($conexion_gen)]);
    }
    exit;
}

if ($action === 'delete') {
    header('Content-Type: application/json');
    $id = intval($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => "ID de depósito no válido."]);
        exit;
    }
    
    mysqli_begin_transaction($conexion_gen);
    try {
        $stmt = $conexion_gen->prepare("UPDATE depositos SET is_active = 0 WHERE id = ? OR parent_id = ?");
        if (!$stmt) {
            throw new Exception("Error al preparar deactivación de depósitos: " . mysqli_error($conexion_gen));
        }
        $stmt->bind_param("ii", $id, $id);
        if (!$stmt->execute()) {
            throw new Exception("Error al deactivar depósitos: " . $stmt->error);
        }
        $stmt->close();
        
        $stmt_df = $conexion_gen->prepare("UPDATE deposito_factura SET is_active = 0 WHERE deposito_id = ?");
        if (!$stmt_df) {
            throw new Exception("Error al preparar deactivación de relaciones: " . mysqli_error($conexion_gen));
        }
        $stmt_df->bind_param("i", $id);
        if (!$stmt_df->execute()) {
            throw new Exception("Error al deactivar relaciones: " . $stmt_df->error);
        }
        $stmt_df->close();
        
        mysqli_commit($conexion_gen);
        sys_log("$workflow Depósito y sub-depósitos desactivados (ID: $id)", "INFO");
        echo json_encode(['status' => 'success', 'message' => "Depósito eliminado correctamente."]);
    } catch (Exception $e) {
        mysqli_rollback($conexion_gen);
        echo json_encode(['status' => 'error', 'message' => "Error al ejecutar desactivación: " . $e->getMessage()]);
    }
    exit;
}

if ($action === 'add_sub_deposit') {
    header('Content-Type: application/json');
    $parent_id = intval($_POST['parent_id'] ?? 0);
    $monto = floatval($_POST['monto'] ?? 0);
    $referencia = trim($_POST['referencia'] ?? '');
    
    if ($parent_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID de depósito principal no válido.']);
        exit;
    }
    if ($monto <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'El monto del abono debe ser mayor a 0.']);
        exit;
    }
    if (empty($referencia)) {
        echo json_encode(['status' => 'error', 'message' => 'La referencia es obligatoria.']);
        exit;
    }
    
    // Obtener datos del padre
    $stmt = $conexion_gen->prepare("SELECT sucursal, cust_id, banco_id FROM depositos WHERE id = ? AND is_active = 1");
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Error al preparar consulta del depósito principal.']);
        exit;
    }
    $stmt->bind_param("i", $parent_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $parent = $res->fetch_assoc();
    $stmt->close();
    
    if (!$parent) {
        echo json_encode(['status' => 'error', 'message' => 'El depósito principal no existe o está inactivo.']);
        exit;
    }
    
    mysqli_begin_transaction($conexion_gen);
    try {
        // Insertar el sub-depósito
        $stmt_sub = $conexion_gen->prepare("
            INSERT INTO depositos (sucursal, cust_id, banco_id, monto, referencia, factura_id, parent_id, saldo, is_active)
            VALUES (?, ?, ?, ?, ?, NULL, ?, 0.00, 1)
        ");
        if (!$stmt_sub) {
            throw new Exception("Error al preparar guardado de abono: " . mysqli_error($conexion_gen));
        }
        $stmt_sub->bind_param("siidsi", $parent['sucursal'], $parent['cust_id'], $parent['banco_id'], $monto, $referencia, $parent_id);
        if (!$stmt_sub->execute()) {
            throw new Exception("Error al guardar abono: " . $stmt_sub->error);
        }
        $stmt_sub->close();
        
        // Actualizar el monto y saldo del padre
        $stmt_update = $conexion_gen->prepare("
            UPDATE depositos 
            SET monto = monto + ?, saldo = saldo + ? 
            WHERE id = ?
        ");
        if (!$stmt_update) {
            throw new Exception("Error al preparar actualización de depósito principal: " . mysqli_error($conexion_gen));
        }
        $stmt_update->bind_param("ddi", $monto, $monto, $parent_id);
        if (!$stmt_update->execute()) {
            throw new Exception("Error al actualizar depósito principal: " . $stmt_update->error);
        }
        $stmt_update->close();
        
        mysqli_commit($conexion_gen);
        sys_log("$workflow Abono registrado para depósito ID $parent_id de $$monto", "INFO");
        echo json_encode(['status' => 'success', 'message' => 'Abono registrado correctamente.']);
    } catch (Exception $e) {
        mysqli_rollback($conexion_gen);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_client_deposits') {
    header('Content-Type: application/json');
    $cust_id = intval($_GET['cust_id'] ?? 0);
    $deposits = [];
    
    if ($cust_id > 0) {
        $stmt = $conexion_gen->prepare("
            SELECT D.id, D.monto, D.saldo, D.referencia, D.created_at, B.nombre AS banco
            FROM depositos D
            LEFT JOIN bancos B ON D.banco_id = B.id
            WHERE D.cust_id = ? AND D.is_active = 1 AND D.parent_id IS NULL AND D.saldo > 0.01
            ORDER BY D.id DESC
        ");
        if ($stmt) {
            $stmt->bind_param("i", $cust_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $fecha_c = date('d-m-Y', strtotime($row['created_at']));
                $row['display_text'] = "Banco: {$row['banco']} | Ref: {$row['referencia']} | Saldo Disp: $" . number_format((float)$row['saldo'], 2) . " | Total: $" . number_format((float)$row['monto'], 2) . " | Fecha: $fecha_c";
                $deposits[] = $row;
            }
            $stmt->close();
        }
    }
    echo json_encode(['status' => 'success', 'deposits' => $deposits]);
    exit;
}

if ($action === 'generate_payment_complement') {
    header('Content-Type: application/json');
    
    $cust_id = intval($_POST['cust_id'] ?? 0);
    $branch = $_POST['branch'] ?? '';
    $forma_pago = $_POST['forma_pago'] ?? '03';
    $fecha_pago_input = $_POST['fecha_pago'] ?? '';
    $deposit_id = intval($_POST['deposit_id'] ?? 0);
    $applied_invoices = $_POST['invoices'] ?? []; 
    
    if ($cust_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Debe seleccionar un cliente válido.']);
        exit;
    }
    if (empty($branch)) {
        echo json_encode(['status' => 'error', 'message' => 'La sucursal es obligatoria.']);
        exit;
    }
    if ($deposit_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Debe seleccionar un depósito de origen.']);
        exit;
    }
    if (empty($applied_invoices)) {
        echo json_encode(['status' => 'error', 'message' => 'Debe seleccionar al menos una factura para aplicar el pago.']);
        exit;
    }
    
    $stmt = $conexion_gen->prepare("SELECT id, monto, saldo, referencia, banco_id FROM depositos WHERE id = ? AND is_active = 1");
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Error al preparar consulta de depósito.']);
        exit;
    }
    $stmt->bind_param("i", $deposit_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $deposit = $res->fetch_assoc();
    $stmt->close();
    
    if (!$deposit) {
        echo json_encode(['status' => 'error', 'message' => 'El depósito seleccionado no existe o está inactivo.']);
        exit;
    }
    
    $monto_total_a_pagar = 0;
    foreach ($applied_invoices as $inv_id => $amt) {
        $monto_total_a_pagar += floatval($amt);
    }
    
    if ($monto_total_a_pagar <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'El monto total a aplicar debe ser mayor a 0.']);
        exit;
    }
    
    if ($monto_total_a_pagar > floatval($deposit['saldo']) + 0.01) {
        echo json_encode(['status' => 'error', 'message' => 'El monto total a aplicar ($' . number_format($monto_total_a_pagar, 2) . ') supera el saldo disponible del depósito ($' . number_format((float)$deposit['saldo'], 2) . ').']);
        exit;
    }
    
    $sql_cliente = "SELECT razon_social, rfc, regimen_fiscal, cp FROM cust WHERE id = ?";
    $stmt_cli = $conexion_gen->prepare($sql_cliente);
    if (!$stmt_cli) {
        echo json_encode(['status' => 'error', 'message' => 'Error al preparar consulta de cliente.']);
        exit;
    }
    $stmt_cli->bind_param("i", $cust_id);
    $stmt_cli->execute();
    $res_cli = $stmt_cli->get_result();
    $cli = $res_cli->fetch_assoc();
    $stmt_cli->close();
    
    if (!$cli) {
        echo json_encode(['status' => 'error', 'message' => 'Cliente no encontrado.']);
        exit;
    }
    
    $razonSocial = $cli['razon_social'];
    $rfc_cliente = $cli['rfc'];
    $cp_cliente = $cli['cp'];
    $regimen_cliente = $cli['regimen_fiscal'];
    
    if (strpos($razonSocial, '&') !== false) {
        $razonSocial = str_replace('&amp;', '&', $razonSocial);
        $razonSocial = str_replace('&', '&amp;', $razonSocial);
    }
    
    $target_serie = $branchSeriesMap[$branch] ?? '';
    $log_file = __DIR__ . "/../logs/depositos_ajax.log";
    
    try {
        $sinube = new SinubeHelper($log_file);
        $folioData = $sinube->getFolioActual($api_url_cert, $api_no_certificado, $target_serie);
        $serie = $folioData['serie'];
        $folio = (string) ($folioData['folioActual'] + 1);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error al obtener folio de Sinube: ' . $e->getMessage()]);
        exit;
    }
    
    $doctos_relacionados = "";
    $total_base_traslados = 0;
    $total_iva_traslados = 0;
    $rep_parts = [];
    
    foreach ($applied_invoices as $inv_id => $amt) {
        $inv_id = intval($inv_id);
        $monto_pagado = floatval($amt);
        if ($monto_pagado <= 0) continue;
        
        $stmt_inv = $conexion_gen->prepare("SELECT id, mov_id, uuid, monto, serie, folio, xml_url FROM facturas WHERE id = ?");
        $stmt_inv->bind_param("i", $inv_id);
        $stmt_inv->execute();
        $res_inv = $stmt_inv->get_result();
        $inv = $res_inv->fetch_assoc();
        $stmt_inv->close();
        
        if (!$inv) {
            echo json_encode(['status' => 'error', 'message' => 'Factura ID ' . $inv_id . ' no encontrada.']);
            exit;
        }
        
        $stmt_parc = $conexion_gen->prepare("SELECT COUNT(*) as total FROM deposito_factura WHERE factura_id = ? AND is_active = 1");
        $stmt_parc->bind_param("i", $inv_id);
        $stmt_parc->execute();
        $res_parc = $stmt_parc->get_result();
        $row_parc = $res_parc->fetch_assoc();
        $parcialidad = intval($row_parc['total'] ?? 0) + 1;
        $stmt_parc->close();
        
        $t_mov = !empty($inv['mov_id']) ? trim($inv['mov_id']) : $inv['id'];
        $rep_parts[] = "{$t_mov}-{$parcialidad}";
        
        $stmt_prev = $conexion_gen->prepare("
            SELECT 
                IFNULL((SELECT SUM(monto_pagado) FROM deposito_factura WHERE factura_id = ? AND is_active = 1), 0) +
                IFNULL((SELECT SUM(monto) FROM depositos WHERE factura_id = ? AND is_active = 1 AND parent_id IS NULL), 0)
                AS total_pagado
        ");
        $stmt_prev->bind_param("ii", $inv_id, $inv_id);
        $stmt_prev->execute();
        $res_prev = $stmt_prev->get_result();
        $row_prev = $res_prev->fetch_assoc();
        $total_pagado = floatval($row_prev['total_pagado'] ?? 0);
        $stmt_prev->close();
        
        $saldo_anterior = floatval($inv['monto']) - $total_pagado;
        $saldo_insoluto = $saldo_anterior - $monto_pagado;
        if ($saldo_insoluto < 0) $saldo_insoluto = 0;
        
        $tasa_iva = get_invoice_tax_rate($inv['xml_url']);
        $base = $monto_pagado / (1 + $tasa_iva);
        $iva = $monto_pagado - $base;
        
        $total_base_traslados += $base;
        $total_iva_traslados += $iva;
        
        $tasa_formateada = sprintf("%.6f", $tasa_iva);
        $base_formateada = sprintf("%.2f", $base);
        $iva_formateada = sprintf("%.2f", $iva);
        
        $doctos_relacionados .= <<<XML
            <pago20:DoctoRelacionado IdDocumento="{$inv['uuid']}" Serie="{$inv['serie']}" Folio="{$inv['folio']}" MonedaDR="MXN" MetodoDePagoDR="PPD" NumParcialidad="{$parcialidad}" ImpSaldoAnt="{$saldo_anterior}" ImpPagado="{$monto_pagado}" ImpSaldoInsoluto="{$saldo_insoluto}" ObjetoImpDR="02">
               <pago20:ImpuestosDR>
                  <pago20:TrasladosDR>
                     <pago20:TrasladoDR Base="{$base_formateada}" ImpuestoDR="002" TipoFactorDR="Tasa" TasaOCuotaDR="{$tasa_formateada}" ImporteDR="{$iva_formateada}" />
                  </pago20:TrasladosDR>
               </pago20:ImpuestosDR>
            </pago20:DoctoRelacionado>
XML;
    }
    
    $impuestos_p = "";
    if ($total_iva_traslados > 0) {
        $total_base_formateada = sprintf("%.2f", $total_base_traslados);
        $total_iva_formateada = sprintf("%.2f", $total_iva_traslados);
        $impuestos_p = <<<XML
            <pago20:ImpuestosP>
               <pago20:TrasladosP>
                  <pago20:TrasladoP Base="{$total_base_formateada}" ImpuestoP="002" TipoFactorP="Tasa" TasaOCuotaP="0.160000" ImporteP="{$total_iva_formateada}" />
               </pago20:TrasladosP>
            </pago20:ImpuestosP>
XML;
    } else {
        $total_base_formateada = sprintf("%.2f", $total_base_traslados);
        $impuestos_p = <<<XML
            <pago20:ImpuestosP>
               <pago20:TrasladosP>
                  <pago20:TrasladoP Base="{$total_base_formateada}" ImpuestoP="002" TipoFactorP="Tasa" TasaOCuotaP="0.000000" ImporteP="0.00" />
               </pago20:TrasladosP>
            </pago20:ImpuestosP>
XML;
    }
    
    $fecha_pago = !empty($fecha_pago_input) ? date('Y-m-d\TH:i:s', strtotime($fecha_pago_input)) : date('Y-m-d\TH:i:s');
    $receptor = <<<XML
<Receptor usoCFDI="CP01" rfc="{$rfc_cliente}" nombre="{$razonSocial}" regimenFiscalReceptor="{$regimen_cliente}" domicilioFiscalReceptor="{$cp_cliente}"/>
XML;

    $mov_id = !empty($rep_parts) ? "REP-" . implode(',', $rep_parts) : "REP-" . time();
    $observacion = "Pago de facturas PPD";
    
    $xml_payload = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<Comprobante exportacion="01" version="CFDI 4.0" sistema="{$api_sistema}" generar="Pago" rfcEmisor="{$api_rfc_emisor}" sucursal="{$api_sucursal}" codigoReporte="CFDI 4.0 - CON IVA - SINUBE-COPIA" 
    permiteAgregarProductosNoInv="1" nomArchivoDescarga="{$mov_id}-{$serie}-{$folio}" noCertificado="{$api_no_certificado}" serie="{$serie}" folio="{$folio}"  
    subtotal="0" descuento="0" total="0" monedaSinube="XXX" monedaSAT="XXX" difZonaHoraria="-06" observacion="{$observacion}">
   {$receptor}
   <Conceptos>
     <Concepto ClaveProdServ="84111506" Cantidad="1" ClaveUnidad="ACT" Descripcion="Pago" ValorUnitario="0" Importe="0" ObjetoImp="01" />
   </Conceptos>
   <Complemento>
      <pago20:Pagos Version="2.0" xmlns:pago20="http://www.sat.gob.mx/Pagos20" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
         <pago20:Pago FechaPago="{$fecha_pago}" FormaDePagoP="{$forma_pago}" MonedaP="MXN" Monto="{$monto_total_a_pagar}" TipoCambioP="1">
            {$doctos_relacionados}
            {$impuestos_p}
         </pago20:Pago>
      </pago20:Pagos>
   </Complemento>
</Comprobante>
XML;

    $api_url_pago = "https://ep-dot-facturanube.appspot.com/blob?par=" . base64_encode("tipo=47\nemp=" . $api_rfc_emisor . "\nsuc=" . $api_sucursal . "\nusu=" . $api_usuario . "\npwd=" . $api_password);
    
    $ch2 = curl_init();
    curl_setopt($ch2, CURLOPT_URL, $api_url_pago);
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
                throw new Exception("Sin Objeto XML, Consulta a tu administrador.");
            }
            
            $error_matches = $xml_obj->xpath("/Respuesta/error");
            if (!empty($error_matches) && trim((string) $error_matches[0]) !== '') {
                throw new Exception("Error de facturación: " . trim((string) $error_matches[0]));
            }
            
            $xml_matches = $xml_obj->xpath("/Respuesta/xml");
            $pdf_matches = $xml_obj->xpath("/Respuesta/pdf");
            $uuid_matches = $xml_obj->xpath("/Respuesta/UUID");
            
            $link_xml = isset($xml_matches[0]) ? (string) $xml_matches[0] : '';
            $link_pdf = isset($pdf_matches[0]) ? (string) $pdf_matches[0] : '';
            $uuid = isset($uuid_matches[0]) ? (string) $uuid_matches[0] : '';
            
            if (empty($uuid)) {
                throw new Exception("No se recibió UUID del complemento de pago timbrado.");
            }
            
            mysqli_begin_transaction($conexion_gen);
            
            $stmt_rep = $conexion_gen->prepare("
                INSERT INTO facturas (mov_id, uuid, monto, metodo_pago, usuario_id, serie, folio, xml_url, pdf_url, estatus, estado, cust_id, sucursal)
                VALUES (?, ?, ?, 'Complemento de Pago', ?, ?, ?, ?, ?, 1, 'Activa', ?, ?)
            ");
            if (!$stmt_rep) {
                throw new Exception("Error al preparar guardado de REP en facturas: " . mysqli_error($conexion_gen));
            }
            
            $user_id = intval($_SESSION['user_id'] ?? 1);
            $monto_rep = 0.00;
            $stmt_rep->bind_param("ssdsssssis", $mov_id, $uuid, $monto_rep, $user_id, $serie, $folio, $link_xml, $link_pdf, $cust_id, $branch);
            if (!$stmt_rep->execute()) {
                throw new Exception("Error al guardar REP en facturas: " . $stmt_rep->error);
            }
            $stmt_rep->close();
            
            $stmt_dep_update = $conexion_gen->prepare("UPDATE depositos SET saldo = saldo - ? WHERE id = ?");
            if (!$stmt_dep_update) {
                throw new Exception("Error al preparar actualización de saldo del depósito: " . mysqli_error($conexion_gen));
            }
            $stmt_dep_update->bind_param("di", $monto_total_a_pagar, $deposit_id);
            if (!$stmt_dep_update->execute()) {
                throw new Exception("Error al actualizar saldo del depósito: " . $stmt_dep_update->error);
            }
            $stmt_dep_update->close();
            
            foreach ($applied_invoices as $inv_id => $amt) {
                $inv_id = intval($inv_id);
                $monto_pagado = floatval($amt);
                if ($monto_pagado <= 0) continue;
                
                $stmt_df = $conexion_gen->prepare("
                    INSERT INTO deposito_factura (deposito_id, factura_id, monto_pagado, is_active)
                    VALUES (?, ?, ?, 1)
                ");
                if (!$stmt_df) {
                    throw new Exception("Error al preparar guardado de relación: " . mysqli_error($conexion_gen));
                }
                $stmt_df->bind_param("iid", $deposit_id, $inv_id, $monto_pagado);
                if (!$stmt_df->execute()) {
                    throw new Exception("Error al guardar relación depósito-factura: " . $stmt_df->error);
                }
                $stmt_df->close();
            }
            
            mysqli_commit($conexion_gen);
            
            sys_log("$workflow REP timbrado y guardado correctamente. UUID: $uuid", "INFO");
            echo json_encode([
                'status' => 'success',
                'message' => 'Complemento de Pago (REP) generado y timbrado correctamente.',
                'uuid' => $uuid,
                'pdf' => $link_pdf,
                'xml' => $link_xml
            ]);
            
        } catch (Exception $e) {
            mysqli_rollback($conexion_gen);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al conectar con la API de Sinube (HTTP ' . $http_code_envio . '). Response: ' . $response_envio]);
    }
    exit;
}

