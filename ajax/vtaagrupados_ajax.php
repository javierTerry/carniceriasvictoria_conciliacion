<?php
session_start();
require_once "../config/config.php";

$action = $_REQUEST['action'] ?? '';
$branch = $_REQUEST['branch'] ?? '';

if (empty($branch)) {
    exit;
}

$branchesConfigs = getBranchesConfig();
if (!isset($branchesConfigs[$branch])) {
    exit;
}

$config = $branchesConfigs[$branch];
$targetConn = mysqli_connect($config['host'], $config['user'], $config['pass'], $config['db']);
if (!$targetConn) {
    exit;
}
mysqli_set_charset($targetConn, "utf8");

if ($action == 'ajax') {
    $q = $_REQUEST['q'] ?? '';
    $page = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;
    $per_page = isset($_REQUEST['per_page']) ? intval($_REQUEST['per_page']) : 25;
    $offset = ($page - 1) * $per_page;

    $sWhere = " WHERE 1=1 ";
    if (!empty($q)) {
        $sWhere .= " AND (name LIKE '%" . mysqli_real_escape_string($targetConn, $q) . "%' OR id LIKE '%" . mysqli_real_escape_string($targetConn, $q) . "%')";
    }

    $sql_check = "SHOW TABLES LIKE 'groups_tickets'";
    $res_check = mysqli_query($targetConn, $sql_check);
    if (mysqli_num_rows($res_check) == 0) {
        echo '<div class="alert alert-danger">
                <h4><i class="fa fa-warning"></i> Error de Base de Datos</h4>
                Para que esta funcionalidad trabaje, es necesario crear las tablas primero. 
                Por favor, ejecuta el esquema SQL proporcionado en el Plan de Acción.
              </div>';
        exit;
    }

    $sql_count = "SELECT count(*) AS numrows FROM groups_tickets $sWhere";
    $query_count = mysqli_query($targetConn, $sql_count);
    $row_count = mysqli_fetch_array($query_count);
    $numrows = $row_count['numrows'];
    $total_pages = ceil($numrows / $per_page);
    $adjacents = 4;
    $reload = './vtaagrupados.php';

    $sql = "SELECT G.*, C.name as cliente 
            FROM groups_tickets G 
            LEFT JOIN cust C ON G.cust_id = C.id 
            $sWhere 
            ORDER BY G.created_at DESC 
            LIMIT $offset, $per_page";
    $query = mysqli_query($targetConn, $sql);

    if ($numrows > 0) {
        include 'pagination.php';
        ?>
        <div style="text-align: center; margin-bottom: 15px;">
            <?php echo paginate($reload, $page, $total_pages, $adjacents); ?>
        </div>
        <table class="table table-striped jambo_table bulk_action">
            <thead>
                <tr class="headings">
                    <th>ID</th>
                    <th>Nombre / Descripción</th>
                    <th>Cliente</th>
                    <th class="text-right">Monto Depósito</th>
                    <th class="text-right">Suma Tickets</th>
                    <th class="text-center">Cant. Tickets</th>
                    <th class="text-center">Estatus</th>
                    <th class="text-center">Fecha</th>
                    <th class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($r = mysqli_fetch_array($query, MYSQLI_ASSOC)):
                    $status = intval($r['status']);
                    $status_label = ($status == 3) ? '<span class="label label-primary">Facturado</span>' : '<span class="label label-warning">Pendiente</span>';
                    ?>
                    <tr>
                        <td><?php echo $r['id']; ?></td>
                        <td><?php echo $r['name']; ?></td>
                        <td><?php echo $r['cliente'] ?? '<span class="text-muted">N/A</span>'; ?></td>
                        <td align="right">$<?php echo number_format($r['deposit_amount'], 2); ?></td>
                        <td align="right">$<?php echo number_format($r['total_tickets_amount'], 2); ?></td>
                        <td align="center"><?php echo $r['ticket_count']; ?></td>
                        <td align="center"><?php echo $status_label; ?></td>
                        <td align="center"><?php echo date('d/m/Y', strtotime($r['created_at'])); ?></td>
                        <td class="text-right">
                            <button type="button" class="btn btn-default btn-xs" title="Ver Detalles"
                                onclick="viewGroupDetails('<?php echo $r['id']; ?>', '<?php echo $branch; ?>')">
                                <i class="glyphicon glyphicon-list"></i>
                            </button>
                            <?php if ($status != 3): ?>
                                <button type="button" class="btn btn-success btn-xs" title="Facturar Grupo"
                                    onclick="facturarGrupo('<?php echo $r['id']; ?>', '<?php echo $branch; ?>')">
                                    <i class="glyphicon glyphicon-usd"></i> Facturar
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                <tr>
                    <td colspan="9" style="text-align: center;">
                        <?php echo paginate($reload, $page, $total_pages, $adjacents); ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    } else {
        echo '<div class="alert alert-warning">No se encontraron grupos.</div>';
    }
}

if ($action == 'get_details') {
    $group_id = intval($_GET['group_id'] ?? 0);
    $sql = "SELECT * FROM groups_tickets_details WHERE group_id = $group_id";
    $query = mysqli_query($targetConn, $sql);
    ?>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Ticket ID (mov_id)</th>
                <th>Sucursal</th>
                <th class="text-right">Monto</th>
                <th>Agregado el</th>
                <th class="text-center">Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sum = 0;
            // Verificar si el grupo ya está facturado
            $sql_st = "SELECT status FROM groups_tickets WHERE id = $group_id";
            $res_st = mysqli_query($targetConn, $sql_st);
            $group_status = mysqli_fetch_array($res_st)['status'];

            while ($r = mysqli_fetch_array($query, MYSQLI_ASSOC)):
                $sum += $r['amount'];
                ?>
                <tr>
                    <td><?php echo $r['mov_id']; ?></td>
                    <td><?php echo $r['branch']; ?></td>
                    <td align="right">$<?php echo number_format($r['amount'], 2); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($r['created_at'])); ?></td>
                    <td align="center">
                        <?php if ($group_status != 3): ?>
                            <button type="button" class="btn btn-danger btn-xs" title="Quitar de grupo"
                                onclick="desagruparTicket('<?php echo $group_id; ?>', '<?php echo $r['mov_id']; ?>', '<?php echo $branch; ?>')">
                                <i class="glyphicon glyphicon-remove"></i>
                            </button>
                        <?php else: ?>
                            ---
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            <tr style="background: #f9f9f9; font-weight: bold;">
                <td colspan="2" align="right">TOTAL TICKETS:</td>
                <td align="right">$<?php echo number_format($sum, 2); ?></td>
                <td></td>
                <td></td>
            </tr>
        </tbody>
    </table>
    <?php
}

mysqli_close($targetConn);
