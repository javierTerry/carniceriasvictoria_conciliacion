<?php
require_once "../config/config.php";

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
    $sql_data = "SELECT A.*, C.razon_social as cliente
                 FROM facturas A
                 LEFT JOIN cust C ON A.cust_id = C.id
                 $sWhere 
                 ORDER BY A.id DESC LIMIT $offset, $per_page";

    $query = mysqli_query($conexion_gen, $sql_data);

    if (!$query) {
        // Si falla por el nombre de la tabla (prefijo), intentamos con prefijo
        $sql_data_alt = "SELECT A.*, C.razon_social as cliente FROM `" . $db_name_gen . "`.`facturas` A LEFT JOIN `" . $db_name_gen . "`.`cust` C ON A.cust_id = C.id $sWhere ORDER BY A.id DESC LIMIT $offset, $per_page";
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
}
?>