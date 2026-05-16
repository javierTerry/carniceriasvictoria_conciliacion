<?php
session_start();
require_once "../config/config.php";
require_once "../config/numtolet.php";

$id = $_GET['id'] ?? null;
$mov_id = $_GET['mov_id'] ?? null;
$branch = $_GET['branch'] ?? '';

if (!$id && !$mov_id) {
    die("<div class='alert alert-danger'>Identificador de venta no proporcionado.</div>");
}

// Select connection based on branch
$targetConn = $conexion; // Default to main connection (Obrador)
if (!empty($branch)) {
    $branchesConfigs = getBranchesConfig();
    if (isset($branchesConfigs[$branch])) {
        $config = $branchesConfigs[$branch];
        $targetConn = mysqli_connect($config['host'], $config['user'], $config['pass'], $config['db']);
        if (!$targetConn) {
            die("<div class='alert alert-danger'>Error: No se pudo conectar a la base de datos de la sucursal $branch.</div>");
        }
    }
}

// 1. Empresa
$sql_empresa = "SELECT name, rfc, domicilio, municipio, alias FROM emprsa LIMIT 1";
$res_empresa = mysqli_query($targetConn, $sql_empresa);
$empresa = mysqli_fetch_array($res_empresa, MYSQLI_ASSOC);

// 2. Venta
$sql_venta = "SELECT A.id, A.mov_id, A.cust_id, A.created_at, A.hour_at, A.sumqty, A.sumimp, A.items, 
                     B.name as cliente, A.recibo, A.acuenta, A.fpago, 
                     CONCAT(U.name, ' ', U.lastname) as uname,
                     F.code as fcode, F.name as fname
              FROM vtahead A
              INNER JOIN cust B ON A.cust_id = B.id
              INNER JOIN user U ON A.user_id = U.id
              INNER JOIN fpago F ON A.fpago = F.id
              WHERE " . ($mov_id ? "A.mov_id = '" . mysqli_real_escape_string($targetConn, $mov_id) . "'" : "A.id = " . intval($id));
$res_venta = mysqli_query($targetConn, $sql_venta);
$sale = mysqli_fetch_array($res_venta, MYSQLI_ASSOC);

if (!$sale) {
    die("<div class='alert alert-danger'>Venta no encontrada en la sucursal $branch (ID: $id).</div>");
}

// 3. Items
mysqli_set_charset($targetConn, "utf8mb4");
$vta_internal_id = $sale['id'] ?? 0;
$sql_items = "SELECT A.qty, B.name, A.price, A.amount 
              FROM vtaitem A 
              INNER JOIN art B ON B.id = A.art_id 
              WHERE A.vta_id = $vta_internal_id";
$res_items = mysqli_query($targetConn, $sql_items);

$cambio = ($sale['cust_id'] == 1) ? ($sale['recibo'] - $sale['sumimp']) : ($sale['recibo'] - $sale['acuenta']);
?>

<link rel="stylesheet" href="assets/css/vta_html_ticket.css">

<div class="ticket-html">
    <div class="ticket-header">
        <img src="images/profiles/logo.jpg" style="width: 80px; display: block; margin: 0 auto 10px;">
        <h4>
            <?php echo $empresa['name']; ?>
        </h4>
        <p>R.F.C.:
            <?php echo $empresa['rfc']; ?><br>
            <?php echo $empresa['domicilio']; ?><br>
            <?php echo $empresa['municipio']; ?><br>
            <?php echo $empresa['alias']; ?>
        </p>
    </div>

    <div class="ticket-info">
        Venta:
        <?php echo $sale['mov_id']; ?><br>
        Fecha:
        <?php echo date('d-m-Y', strtotime($sale['created_at'])); ?>
        <?php echo $sale['hour_at']; ?><br>
        Cliente: <span class="ticket-bold">
            <?php echo utf8_decode($sale['cliente']); ?>
        </span>
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
            <?php while ($item = mysqli_fetch_array($res_items, MYSQLI_ASSOC)): ?>
                <tr>
                    <td>
                        <?php echo number_format($item['qty'], 3); ?>
                    </td>
                    <td>
                        <?php echo ($item['name']); ?>
                    </td>
                    <td class="text-right">
                        <?php echo number_format($item['price'], 2); ?>
                    </td>
                    <td class="text-right">
                        <?php echo number_format($item['amount'], 2); ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="ticket-totals">
        <div class="row">
            <div class="col-xs-6">TOTAL UNIDADES:</div>
            <div class="col-xs-6 text-right ticket-bold">
                <?php echo number_format($sale['sumqty'], 3); ?>
            </div>
        </div>
        <div class="row" style="font-size: 1.1em;">
            <div class="col-xs-6 ticket-bold">TOTAL:</div>
            <div class="col-xs-6 text-right ticket-bold">$
                <?php echo number_format($sale['sumimp'], 2); ?>
            </div>
        </div>
        <p style="font-size: 10px; margin-top: 5px;">
            <?php echo utf8_decode(NumLetras($sale['sumimp'])); ?>
        </p>

        <div class="row" style="margin-top: 10px;">
            <div class="col-xs-6">Productos:</div>
            <div class="col-xs-6 text-right">
                <?php echo $sale['items']; ?>
            </div>
        </div>
        <?php if ($sale['cust_id'] != 1): ?>
            <div class="row">
                <div class="col-xs-6">A cuenta:</div>
                <div class="col-xs-6 text-right">$
                    <?php echo number_format($sale['acuenta'], 2); ?>
                </div>
            </div>
        <?php endif; ?>
        <div class="row">
            <div class="col-xs-6">Recibido:</div>
            <div class="col-xs-6 text-right">$
                <?php echo number_format($sale['recibo'], 2); ?>
            </div>
        </div>
        <div class="row">
            <div class="col-xs-6">Cambio:</div>
            <div class="col-xs-6 text-right">$
                <?php echo number_format($cambio, 2); ?>
            </div>
        </div>
        <div class="row">
            <div class="col-xs-12" id="ticket_payment_info" data-fcode="<?php echo $sale['fcode']; ?>">Forma de pago:
                <?php echo "{$sale['fcode']} - {$sale['fname']}"; ?>
            </div>
        </div>
    </div>

    <div class="ticket-footer">
        <p class="ticket-bold">ATENDIO:
            <?php echo utf8_decode($sale['uname']); ?>
        </p>
        <p>SERVICIO A DOMICILIO TEL. 55 52 04 16 20</p>
        <p class="ticket-bold">ESTIMADO CLIENTE</p>
        <p>Para solicitar factura envíe un mensaje de WhatsApp al 5522939605, contará con 5 días hábiles a partir de la
            fecha de su compra.</p>
        <p class="ticket-bold">NO SE REALIZARÁN FACTURAS DE MESES ANTERIORES</p>
        <p>***AGRADECEMOS SU PREFERENCIA***</p>
        <p class="ticket-bold">ORIGINAL</p>
    </div>

    <?php if (isset($_GET['manage'])): ?>
        <input type="hidden" id="raw_ticket_total" value="<?php echo $sale['sumimp']; ?>">
    <?php endif; ?>
</div>