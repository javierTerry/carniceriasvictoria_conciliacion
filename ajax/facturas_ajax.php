<?php
ini_set('memory_limit', '512M');
session_start();

require_once "../config/config.php";

$user_kind = $_SESSION['user_kind'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;

$action = $_REQUEST['action'] ?? '';

if ($action == 'ajax') {
  $q = $_REQUEST['q'] ?? '';
  $page = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;
  $per_page = isset($_REQUEST['per_page']) ? intval($_REQUEST['per_page']) : 25;
  $branch_filter = $_REQUEST['branch'] ?? '';
  $fpay_filter = $_REQUEST['fpay'] ?? '';
  $adjacents = 4;
  $offset = ($page - 1) * $per_page;

  $sWhere = " WHERE 1=1 ";
  $params = [];
  $types = "";

  if ($fpay_filter !== '') {
    $sWhere .= " AND A.metodo_pago LIKE ? ";
    $params[] = "%$fpay_filter%";
    $types .= "s";
  }

  if (!empty($q)) {
    $sWhere .= " AND (A.mov_id LIKE ? OR A.uuid LIKE ? OR A.folio LIKE ?)";
    $search_q = "%$q%";
    $params[] = $search_q;
    $params[] = $search_q;
    $params[] = $search_q;
    $types .= "sss";
  }

  $all_rows = [];
  $branchesConfigs = getBranchesConfig();

  foreach ($branchesConfigs as $branchLabel => $config) {
    if (!empty($branch_filter) && $branch_filter !== 'all' && $branch_filter !== $branchLabel) {
      continue;
    }

    $branchConn = mysqli_connect($config['host'], $config['user'], $config['pass'], $config['db']);

    if (!$branchConn) {
      continue;
    }

    mysqli_set_charset($branchConn, "utf8");

    $sTable = "facturas A";

    // Opcional: Hacer un LEFT JOIN con vtahead para obtener cust_id o con cust si queremos. 
    // Por ahora nos limitamos a lo básico para mantenerlo estable.
    
    $sql_data = "SELECT A.id, A.mov_id, A.uuid, A.fecha_factura, A.monto, A.metodo_pago, A.serie, A.folio, A.xml_url, A.pdf_url, A.estado 
                 FROM $sTable $sWhere 
                 ORDER BY A.fecha_factura DESC, A.id DESC LIMIT 500"; 
                 // Limited to 500 per branch just to avoid overload

    $stmt_data = mysqli_prepare($branchConn, $sql_data);
    
    if (!$stmt_data) {
        $error = mysqli_error($branchConn);
        error_log("Error SQL en base de datos $branchLabel: $error");
        continue;
    }

    if (!empty($params)) {
      mysqli_stmt_bind_param($stmt_data, $types, ...$params);
    }

    if (mysqli_stmt_execute($stmt_data)) {
      $query_res = mysqli_stmt_get_result($stmt_data);
      while ($row = mysqli_fetch_array($query_res, MYSQLI_ASSOC)) {
        $row['branch_label'] = $branchLabel;
        $row['branch_class'] = 'label-' . strtolower($branchLabel);
        $all_rows[] = $row;
      }
    }
    mysqli_close($branchConn);
  }

  // Sort globally by date
  usort($all_rows, function ($a, $b) {
    $dateA = strtotime($a['fecha_factura']);
    $dateB = strtotime($b['fecha_factura']);
    return $dateB <=> $dateA;
  });

  $numrows = count($all_rows);
  $total_pages = ceil($numrows / $per_page);
  $reload = './facturasqry.php';

  $display_rows = array_slice($all_rows, $offset, $per_page);

  if ($numrows > 0) {
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
          <th>UUID / Folio</th>
          <th>Fecha Factura</th>
          <th>Método Pago</th>
          <th class="text-right">Monto</th>
          <th>Estatus</th>
          <th class="text-center">XML</th>
          <th class="text-center">PDF</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($display_rows as $r):
          $fecha_f = date('d-m-Y H:i:s', strtotime($r['fecha_factura']));
          $estado = $r['estado'];
          ?>
          <tr>
            <td><span class="label <?php echo $r['branch_class']; ?>"><?php echo $r['branch_label']; ?></span></td>
            <td><?php echo $r['mov_id']; ?></td>
            <td>
                <div><small style="color: #555;"><?php echo $r['uuid']; ?></small></div>
                <?php if ($r['folio']) : ?>
                <div><strong><?php echo $r['serie']; ?> - <?php echo $r['folio']; ?></strong></div>
                <?php endif; ?>
            </td>
            <td><?php echo $fecha_f; ?></td>
            <td><?php echo $r['metodo_pago']; ?></td>
            <td align="right" style="font-weight: bold; color: #26B99A;">$<?php echo number_format($r['monto'], 2); ?></td>
            <td>
                <?php if ($estado == 'Activa' || $estado == '1') { ?>
                    <span class="badge badge-success">Activa</span>
                <?php } else { ?>
                    <span class="badge badge-danger"><?php echo $estado; ?></span>
                <?php } ?>
            </td>
            
            <td class="text-center">
              <?php if (!empty($r['xml_url'])) { ?>
                  <a href="<?php echo htmlspecialchars($r['xml_url']); ?>" target="_blank" download title="Descargar XML">
                      <i class="fa fa-file-code-o" style="font-size: 20px; color: #34495e;"></i>
                  </a>
              <?php } else { echo "-"; } ?>
            </td>
            <td class="text-center">
               <?php if (!empty($r['pdf_url'])) { ?>
                  <a href="<?php echo htmlspecialchars($r['pdf_url']); ?>" target="_blank" download title="Descargar PDF">
                      <i class="fa fa-file-pdf-o" style="font-size: 20px; color: #e74c3c;"></i>
                  </a>
              <?php } else { echo "-"; } ?>
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
      <strong>Aviso!</strong> No se encontraron facturas con esos criterios o la tabla está vacía. (Recuerda ejecutar el script de creación de tabla `facturas`).
    </div>
    <?php
  }
}
?>
