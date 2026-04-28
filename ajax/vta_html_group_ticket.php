<?php
session_start();
require_once "../config/config.php";
require_once "../config/numtolet.php";

$group_id = $_GET['group_id'] ?? null;
$branch = $_GET['branch'] ?? '';

if (!$group_id) {
    die("<div class='alert alert-danger'>ID de grupo no proporcionado.</div>");
}

$branchesConfigs = getBranchesConfig();
if (!isset($branchesConfigs[$branch])) {
    die("<div class='alert alert-danger'>Sucursal no válida.</div>");
}

$config = $branchesConfigs[$branch];
$targetConn = mysqli_connect($config['host'], $config['user'], $config['pass'], $config['db']);
if (!$targetConn) {
    die("<div class='alert alert-danger'>Error de conexión a la sucursal.</div>");
}
mysqli_set_charset($targetConn, "utf8");

// 1. Empresa
$sql_empresa = "SELECT name, rfc, domicilio, municipio, alias FROM emprsa LIMIT 1";
$res_empresa = mysqli_query($targetConn, $sql_empresa);
$empresa = mysqli_fetch_array($res_empresa, MYSQLI_ASSOC);

// 2. Info del Grupo
$sql_group = "SELECT G.*, C.name as cliente 
              FROM groups_tickets G 
              LEFT JOIN cust C ON G.cust_id = C.id 
              WHERE G.id = " . intval($group_id);
$res_group = mysqli_query($targetConn, $sql_group);
$group = mysqli_fetch_array($res_group, MYSQLI_ASSOC);

if (!$group) {
    die("<div class='alert alert-danger'>Grupo no encontrado.</div>");
}

// 3. Consolidar Items de todos los tickets del grupo
$sql_items = "SELECT B.name, A.price, SUM(A.qty) as total_qty, SUM(A.amount) as total_amount 
              FROM vtaitem A 
              INNER JOIN art B ON B.id = A.art_id 
              INNER JOIN vtahead VH ON VH.id = A.vta_id
              INNER JOIN groups_tickets_details GTD ON GTD.mov_id = VH.mov_id
              WHERE GTD.group_id = " . intval($group_id) . "
              GROUP BY A.art_id, A.price";
$res_items = mysqli_query($targetConn, $sql_items);
?>

<link rel="stylesheet" href="assets/css/vta_html_ticket.css">

<div class="ticket-html">
    <div class="ticket-header">
        <img src="images/profiles/logo.jpg" style="width: 80px; display: block; margin: 0 auto 10px;">
        <h4><?php echo $empresa['name']; ?></h4>
        <p>R.F.C.: <?php echo $empresa['rfc']; ?><br>
            <?php echo $empresa['domicilio']; ?><br>
            <?php echo $empresa['municipio']; ?><br>
            <?php echo $empresa['alias']; ?>
        </p>
    </div>

    <div class="ticket-info">
        GRUPO: <strong><?php echo htmlspecialchars($group['name']); ?></strong><br>
        Cliente: <strong><?php echo utf8_decode($group['cliente'] ?? 'N/A'); ?></strong><br>
        ID Grupo: <?php echo $group['id']; ?><br>
        Fecha: <?php echo date('d-m-Y', strtotime($group['created_at'])); ?><br>
        Tickets Relacionados: <?php echo $group['ticket_count']; ?>
    </div>

    <table class="ticket-table">
        <thead>
            <tr>
                <th>Peso</th>
                <th>Producto</th>
                <th class="text-right">Precio</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $calculated_total_units = 0;
            $calculated_total_amount = 0;
            $item_count = 0;
            while ($item = mysqli_fetch_array($res_items, MYSQLI_ASSOC)): 
                $calculated_total_units += $item['total_qty'];
                $calculated_total_amount += $item['total_amount'];
                $item_count++;
                ?>
                <tr>
                    <td><?php echo number_format($item['total_qty'], 3); ?></td>
                    <td><?php echo utf8_decode($item['name']); ?></td>
                    <td class="text-right"><?php echo number_format($item['price'], 2); ?></td>
                    <td class="text-right"><?php echo number_format($item['total_amount'], 2); ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="ticket-totals">
        <div class="row">
            <div class="col-xs-6">TOTAL UNIDADES:</div>
            <div class="col-xs-6 text-right ticket-bold"><?php echo number_format($calculated_total_units, 3); ?></div>
        </div>
        <div class="row" style="font-size: 1.1em;">
            <div class="col-xs-6 ticket-bold">TOTAL AGREGADO:</div>
            <div class="col-xs-6 text-right ticket-bold">$<?php echo number_format($calculated_total_amount, 2); ?></div>
        </div>
        <p style="font-size: 10px; margin-top: 5px;">
            <?php echo utf8_decode(NumLetras($calculated_total_amount)); ?>
        </p>

        <div class="row" style="margin-top: 10px;">
            <div class="col-xs-6">Monto Depósito (Manual):</div>
            <div class="col-xs-6 text-right">$<?php echo number_format($group['deposit_amount'], 2); ?></div>
        </div>
        <div class="row">
            <div class="col-xs-6">Partidas Consolidadas:</div>
            <div class="col-xs-6 text-right"><?php echo $item_count; ?></div>
        </div>
        <div class="row">
            <div class="col-xs-12" id="ticket_payment_info" data-fcode="01">Forma de pago Sugerida: 01 - EFECTIVO</div>
        </div>
    </div>

    <div class="ticket-footer">
        <p class="ticket-bold">RESUMEN DE GRUPO</p>
        <p>Este documento consolida la suma de todos los tickets incluidos en el grupo para fines de facturación seccionada.</p>
        <p>*** TICKET CONSOLIDADO ***</p>
    </div>

    <?php if (isset($_GET['manage'])): ?>
        <input type="hidden" id="raw_ticket_total" value="<?php echo $calculated_total_amount; ?>">
    <?php endif; ?>
</div>
<?php mysqli_close($targetConn); ?>
