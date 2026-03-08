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
  $adjacents = 4;
  $offset = ($page - 1) * $per_page;

  $sWhere = " WHERE A.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) ";
  $params = [];
  $types = "";

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
    if (!empty($branch_filter) && $branch_filter !== $branchLabel) {
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

  // Manual Slicing for Pagination
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
          <th>Cliente</th>
          <th>Fecha</th>
          <th>Hora</th>
          <th>Método Pago</th>
          <th>Fecha Fact.</th>
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
          ?>
          <tr class="<?php echo $row_class; ?>">
            <td><span class="label <?php echo $r['branch_class']; ?>"><?php echo $r['branch_label']; ?></span></td>
            <td><?php echo $r['mov_id']; ?></td>
            <td><?php echo $r['cliente']; ?></td>
            <td><?php echo $fecha_f; ?></td>
            <td><?php echo $r['hour_at']; ?></td>
            <td><?php echo $r['fname']; ?></td>
            <td>---</td>
            <td align="right"><?php echo number_format($r['sumimp'], 2); ?></td>
            <td>
              <span class="badge <?php echo ($status == 1) ? 'badge-success' : 'badge-danger'; ?>">
                <?php echo ($status == 1) ? "Activo" : "Inactivo"; ?>
              </span>
            </td>
            <td class="text-right">
              <?php if ($status == 1 && $user_kind != 0 && $fecha_f == $hoy): ?>
                <button type="button" class='btn btn-default btn-xs action-btn-victoria' title='Cancelar Venta'
                  onclick="eliminar('<?php echo $r['id']; ?>')">
                  <i class="glyphicon glyphicon-trash"></i>
                </button>
              <?php endif; ?>
              <button type="button" class='btn btn-default btn-xs action-btn-victoria' title='Imprimir Venta'
                onclick="printTicket('<?php echo $r['id']; ?>')">
                <i class="glyphicon glyphicon-print"></i>
              </button>
              <button type="button" class='btn btn-default btn-xs action-btn-victoria' title='Ver Ticket (HTML)'
                onclick="viewTicketHTML('<?php echo $r['id']; ?>')">
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