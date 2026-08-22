<?php
ini_set('memory_limit', '2024M');
session_start();

require_once "../config/config.php";

$user_kind = $_SESSION['user_kind'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;
$hoy = date('d-m-Y');

$action = $_REQUEST['action'] ?? '';

// 1. Handle Deletion (Cancel Sale)
if (isset($_GET['id'])) {
  $id_expense = intval($_GET['id']);

  // Check if sale exists using prepared statement
  $stmt_check = mysqli_prepare($conexion, "SELECT 1 FROM vtahead WHERE id = ?");
  mysqli_stmt_bind_param($stmt_check, "i", $id_expense);
  mysqli_stmt_execute($stmt_check);
  $res_check = mysqli_stmt_get_result($stmt_check);

  if (mysqli_num_rows($res_check) > 0) {
    // Instead of echoing a script, we return a status that the JS can handle
    // However, the original code called cancelingregistro(id) which did window.location.href="action/del_vta.php?xyz="+id;
    // We will keep this for now but returning it as a data attribute or a specific message
    ?>
    <div class="alert alert-success alert-dismissible" role="alert"
      data-redirect="action/del_vta.php?xyz=<?php echo $id_expense; ?>">
      <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span
          aria-hidden="true">&times;</span></button>
      <strong>Aviso!</strong> Redirigiendo para cancelar venta...
    </div>
    <?php
  } else {
    ?>
    <div class="alert alert-danger alert-dismissible" role="alert">
      <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span
          aria-hidden="true">&times;</span></button>
      <strong>Error!</strong> Venta no encontrada.
    </div>
    <?php
  }
}

// 2. Handle AJAX Listing
if ($action == 'ajax') {
  $q = $_REQUEST['q'] ?? '';
  $page = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;
  $per_page = isset($_REQUEST['per_page']) ? intval($_REQUEST['per_page']) : 25;
  $branch_filter = $_REQUEST['branch'] ?? '';
  $status_filter = $_REQUEST['status'] ?? '';
  $fpay_filter = $_REQUEST['fpay'] ?? '';
  $adjacents = 4;
  $offset = ($page - 1) * $per_page;

  $sWhere = " WHERE (YEAR(A.created_at) = YEAR(CURDATE()) OR A.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) ";
  
  $params = [];
  $types = "";

  if ($status_filter !== '') {
    $sWhere .= " AND A.is_active = ? ";
    $params[] = intval($status_filter);
    $types .= "i";
  }

  if ($fpay_filter !== '') {
    $sWhere .= " AND A.fpago = ? ";
    $params[] = intval($fpay_filter);
    $types .= "i";
  }

  if (!empty($q)) {
    $sWhere .= " AND (B.name LIKE ? OR A.mov_id LIKE ? OR A.created_at LIKE ?)";
    $search_q = "%$q%";
    $params[] = $search_q;
    $params[] = $search_q;
    $params[] = $search_q;
    $types .= "sss";
  }

  $all_rows = [];
  $branchesConfigs = getBranchesConfig();

  foreach ($branchesConfigs as $branchLabel => $config) {
    // Apply branch filter if selected
    if (!empty($branch_filter) && $branch_filter !== 'all' && $branch_filter !== $branchLabel) {
      continue;
    }

    $branchConn = mysqli_connect($config['host'], $config['user'], $config['pass'], $config['db']);

    if (!$branchConn) {
      error_log("Could not connect to branch: $branchLabel");
      continue;
    }

    mysqli_set_charset($branchConn, "utf8");

    $sTable = "vtahead A 
                   INNER JOIN cust B ON A.cust_id = B.id 
                   INNER JOIN user C ON A.user_id = C.id
                   INNER JOIN fpago F ON A.fpago = F.id";

    $sql_data = "SELECT A.id, A.mov_id, A.cust_id, B.name as cliente, A.created_at, A.hour_at, 
                            A.items, A.sumqty, A.sumimp, A.balance, A.is_active as status, C.name as usuario,
                            F.name as fname 
                     FROM $sTable $sWhere 
                     ORDER BY A.created_at DESC, A.mov_id DESC";

    $stmt_data = mysqli_prepare($branchConn, $sql_data);
    if (!empty($params)) {
      mysqli_stmt_bind_param($stmt_data, $types, ...$params);
    }

    if (mysqli_stmt_execute($stmt_data)) {
      $query_res = mysqli_stmt_get_result($stmt_data);
      while ($row = mysqli_fetch_array($query_res, MYSQLI_ASSOC)) {
        $row['branch_label'] = $branchLabel;
        // Assign CSS class based on branch label
        $row['branch_class'] = 'label-' . strtolower($branchLabel);
        $all_rows[] = $row;
      }
    }
    mysqli_close($branchConn);
  }

  // Sort all aggregated rows by created_at DESC
  usort($all_rows, function ($a, $b) {
    $dateA = strtotime($a['created_at'] . ' ' . $a['hour_at']);
    $dateB = strtotime($b['created_at'] . ' ' . $b['hour_at']);
    return $dateB <=> $dateA;
  });

  $numrows = count($all_rows);
  $total_pages = ceil($numrows / $per_page);
  $reload = './vtaqry.php';

  // Squeeze and collect all mov_ids from display_rows to query general invoices in a single batch
  $display_rows = array_slice($all_rows, $offset, $per_page);

  $facturas_map = [];
  if (!empty($display_rows) && isset($conexion_gen)) {
    $mov_ids = array_unique(array_filter(array_column($display_rows, 'mov_id')));
    if (!empty($mov_ids)) {
      $placeholders = implode(',', array_fill(0, count($mov_ids), '?'));
      $sql_fac = "SELECT id, mov_id, sucursal, serie, folio, uuid, estatus, estado, xml_url, pdf_url, fecha_factura 
                  FROM `" . $db_name_gen . "`.`facturas` 
                  WHERE mov_id IN ($placeholders) 
                  ORDER BY id DESC";
      $stmt_fac = mysqli_prepare($conexion_gen, $sql_fac);
      if ($stmt_fac) {
        $types_fac = str_repeat('s', count($mov_ids));
        mysqli_stmt_bind_param($stmt_fac, $types_fac, ...$mov_ids);
        if (mysqli_stmt_execute($stmt_fac)) {
          $res_fac = mysqli_stmt_get_result($stmt_fac);
          while ($fac_row = mysqli_fetch_assoc($res_fac)) {
            $key_branch = $fac_row['mov_id'] . '_' . ($fac_row['sucursal'] ?? '');
            if (!isset($facturas_map[$key_branch])) {
              $facturas_map[$key_branch] = $fac_row;
            }
            if (!isset($facturas_map[$fac_row['mov_id']])) {
              $facturas_map[$fac_row['mov_id']] = $fac_row;
            }
          }
        }
        mysqli_stmt_close($stmt_fac);
      }
    }
  }

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
          <th>Cliente</th>
          <th>Fecha</th>
          <th>Hora</th>
          <th>Forma Pago</th>
          <th>Monto</th>
          <th>Estatus</th>
          <th class="text-right">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($display_rows as $r):
          $status = intval($r['status']);
          $fecha_f = date('d-m-Y', strtotime($r['created_at']));
          $row_class = ($status == 1) ? "paid-highlight" : "";

          // Invoice status check
          $mov_id = $r['mov_id'];
          $branch_label = $r['branch_label'];
          $fac = $facturas_map[$mov_id . '_' . $branch_label] ?? ($facturas_map[$mov_id] ?? null);
          $has_invoice = !empty($fac);
          $is_invoice_cancelled = $has_invoice && (($fac['estado'] ?? '') === 'Cancelada' || (isset($fac['estatus']) && $fac['estatus'] == 0));
          $is_invoice_active = $has_invoice && !$is_invoice_cancelled;
          ?>
          <tr class="<?php echo $row_class; ?>">
            <td><span class="label <?php echo $r['branch_class']; ?>"><?php echo $r['branch_label']; ?></span></td>
            <td><?php echo $r['mov_id']; ?></td>
            <td><?php echo $r['cliente']; ?></td>
            <td><?php echo $fecha_f; ?></td>
            <td><?php echo $r['hour_at']; ?></td>
            <td><?php echo $r['fname']; ?></td>
            <td align="right"><?php echo number_format($r['sumimp'], 2); ?></td>
            <td>
              <?php
              if ($is_invoice_cancelled) {
                ?>
                <span class="badge badge-cancelled" style="background-color: #1a1a1a; color: #e74c3c; border: 1px solid #e74c3c; padding: 4px 8px; font-weight: bold; border-radius: 4px;" title="Factura Cancelada <?php echo htmlspecialchars(($fac['serie'] ?? '') . ' ' . ($fac['folio'] ?? '')); ?>">
                  <i class="glyphicon glyphicon-ban-circle"></i> Factura Cancelada
                </span>
                <?php if (!empty($fac['folio'])): ?>
                  <br><small style="color: #b22222; font-weight: bold; font-size: 11px;"><?php echo htmlspecialchars(($fac['serie'] ?? '') . '-' . $fac['folio']); ?> (Cancelada)</small>
                <?php endif; ?>
                <?php
              } elseif ($status == 3 || $is_invoice_active) {
                $folio_tag = ($has_invoice && (!empty($fac['serie']) || !empty($fac['folio']))) ? ' (' . htmlspecialchars(($fac['serie'] ?? '') . '-' . ($fac['folio'] ?? '')) . ')' : '';
                ?>
                <span class="badge badge-primary" style="padding: 4px 8px; font-weight: bold; border-radius: 4px;" title="<?php echo htmlspecialchars($fac['uuid'] ?? 'Facturado'); ?>">
                  <i class="glyphicon glyphicon-file"></i> Facturado<?php echo $folio_tag; ?>
                </span>
                <?php
              } else {
                switch ($status) {
                  case 1:
                    echo '<span class="badge badge-success" style="padding: 4px 8px; font-weight: bold; border-radius: 4px;">Activo</span>';
                    break;
                  case 0:
                    echo '<span class="badge" style="background-color: #6c757d; color: #ffffff; padding: 4px 8px; font-weight: bold; border-radius: 4px;" title="Ticket Inactivo en Punto de Venta"><i class="glyphicon glyphicon-minus-sign"></i> Inactivo</span>';
                    break;
                  case 2:
                    echo '<span class="badge badge-warning" style="padding: 4px 8px; font-weight: bold; border-radius: 4px;">Pendiente</span>';
                    break;
                  case 4:
                    echo '<span class="badge badge-info" style="padding: 4px 8px; font-weight: bold; border-radius: 4px;">Agrupado</span>';
                    break;
                  default:
                    echo '<span class="badge">' . $status . '</span>';
                }
              }
              ?>
            </td>
            <td class="text-right">
              <?php if ($status_filter === ''): ?>
                <?php if ($status == 1 && !$is_invoice_cancelled): ?>
                  <button type="button" class='btn btn-default btn-xs action-btn-victoria' title='Agrupar Ticket'
                    onclick="agruparTicket('<?php echo $r['mov_id']; ?>', '<?php echo $r['branch_label']; ?>', '<?php echo number_format($r['sumimp'], 2, '.', ''); ?>', '<?php echo $r['cust_id']; ?>', '<?php echo htmlspecialchars($r['cliente'], ENT_QUOTES); ?>')">
                    <i class="glyphicon glyphicon-link"></i>
                  </button>
                <?php endif; ?>
              <?php endif; ?>

              <?php if ($status == 1 && !$is_invoice_cancelled): ?>
                <?php if ($user_kind != 0 && $fecha_f == $hoy): ?>
                  <button type="button" class='btn btn-default btn-xs action-btn-victoria' title='Cancelar Venta'
                    onclick="eliminar('<?php echo $r['id']; ?>')">
                    <i class="glyphicon glyphicon-trash"></i>
                  </button>
                <?php endif; ?>

                <button type="button" class='btn btn-default btn-xs action-btn-victoria' title='Cambiar Estatus'
                  onclick="changeStatusPrompt('<?php echo $r['mov_id']; ?>', '<?php echo $r['branch_label']; ?>', '<?php echo htmlspecialchars($r['cliente'], ENT_QUOTES); ?>', '<?php echo number_format($r['sumimp'], 2, '.', ''); ?>', '<?php echo htmlspecialchars($r['fname'], ENT_QUOTES); ?>', '<?php echo $status; ?>')">
                  <i class="glyphicon glyphicon-retweet"></i>
                </button>
              <?php endif; ?>

              <?php if (($status == 2 || $status == 4) && $status_filter == '2' && !$is_invoice_cancelled): ?>
                <?php if ($status == 2): ?>
                  <button type="button" class='btn btn-default btn-xs action-btn-victoria' title='Cambiar Estatus a Activo'
                    onclick="changeStatusPrompt('<?php echo $r['mov_id']; ?>', '<?php echo $r['branch_label']; ?>', '<?php echo htmlspecialchars($r['cliente'], ENT_QUOTES); ?>', '<?php echo number_format($r['sumimp'], 2, '.', ''); ?>', '<?php echo htmlspecialchars($r['fname'], ENT_QUOTES); ?>', '<?php echo $status; ?>')">
                    <i class="glyphicon glyphicon-retweet"></i>
                  </button>
                <?php endif; ?>
                <button type="button" class='btn btn-default btn-xs action-btn-victoria' title='Gestionar Acción Ticket'
                  onclick="window.location.href='vtapendente.php?mov_id=<?php echo $r['mov_id']; ?>&branch=<?php echo $r['branch_label']; ?>'">
                  <i class="glyphicon glyphicon-cog"></i>
                </button>
              <?php endif; ?>

              <?php if ($has_invoice && !empty($fac['xml_url'])): ?>
                <a href="<?php echo htmlspecialchars($fac['xml_url']); ?>" target="_blank" download class="btn btn-default btn-xs action-btn-victoria" title="Descargar XML Factura">
                  <i class="fa fa-file-code-o"></i>
                </a>
              <?php endif; ?>

              <?php if ($has_invoice && !empty($fac['pdf_url'])): ?>
                <a href="<?php echo htmlspecialchars($fac['pdf_url']); ?>" target="_blank" download class="btn btn-default btn-xs action-btn-victoria" title="Descargar PDF Factura">
                  <i class="fa fa-file-pdf-o"></i>
                </a>
              <?php endif; ?>

              <!-- Visualización e Impresión de Ticket -->
              <button type="button" class='btn btn-default btn-xs action-btn-victoria' title='Imprimir Venta'
                onclick="printTicket('<?php echo $r['id']; ?>', '<?php echo $r['branch_label']; ?>')">
                <i class="glyphicon glyphicon-print"></i>
              </button>
              <button type="button" class='btn btn-default btn-xs action-btn-victoria' title='Ver Ticket (HTML)'
                onclick="viewTicketHTML('<?php echo $r['id']; ?>', '<?php echo $r['branch_label']; ?>')">
                <i class="glyphicon glyphicon-list-alt"></i>
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td colspan="10" style="text-align: center;">
            <?php echo paginate($reload, $page, $total_pages, $adjacents); ?>
          </td>
        </tr>
      </tbody>
    </table>
    <?php
  } else {
    ?>
    <div class="alert alert-warning alert-dismissible" role="alert">
      <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span
          aria-hidden="true">&times;</span></button>
      <strong>Aviso!</strong> No hay datos para mostrar.
    </div>
    <?php
  }
}
?>