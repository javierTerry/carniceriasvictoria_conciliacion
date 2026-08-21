<?php
require_once "../config/config.php";

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

if ($action == 'ajax') {
    // Escapar inputs para evitar inyección SQL
    $branch = isset($_REQUEST['branch']) ? mysqli_real_escape_string($conexion_gen, $_REQUEST['branch']) : 'all';
    $date_start = isset($_REQUEST['date_start']) ? mysqli_real_escape_string($conexion_gen, $_REQUEST['date_start']) : '';
    $date_end = isset($_REQUEST['date_end']) ? mysqli_real_escape_string($conexion_gen, $_REQUEST['date_end']) : '';
    $page = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;
    $per_page_raw = isset($_REQUEST['per_page']) ? $_REQUEST['per_page'] : '25';
    $per_page = ($per_page_raw === 'all') ? 999999 : intval($per_page_raw);
    if ($per_page <= 0) {
        $per_page = 25;
    }
    
    $adjacents = 4;
    $offset = ($page - 1) * $per_page;

    // Construcción del filtro WHERE
    // Solo consideramos facturas activas para la conciliación de ventas
    $sWhere = " WHERE estatus = 1 ";

    if ($branch !== '' && $branch !== 'all') {
        $sWhere .= " AND sucursal = '$branch' ";
    }

    if ($date_start !== '') {
        $sWhere .= " AND DATE(fecha_factura) >= '$date_start' ";
    }

    if ($date_end !== '') {
        $sWhere .= " AND DATE(fecha_factura) <= '$date_end' ";
    }

    // Consulta para obtener la cuenta total de registros agrupados (para la paginación)
    $sql_count = "SELECT COUNT(*) FROM (
                      SELECT 1 
                      FROM facturas 
                      $sWhere 
                      GROUP BY sucursal, DATE(fecha_factura)
                  ) as temp";
                  
    $query_count = mysqli_query($conexion_gen, $sql_count);
    $total_records = 0;
    if ($query_count) {
        $row_count = mysqli_fetch_row($query_count);
        $total_records = $row_count[0];
    }

    $total_pages = ceil($total_records / $per_page);
    $reload = './resumen_facturas.php';

    // Consulta principal agrupada por sucursal y fecha de creación de factura
    $sql_data = "SELECT 
                    sucursal,
                    DATE(fecha_factura) as fecha,
                    SUM(CASE WHEN metodo_pago IN ('Transferencia electrónica de fondos', 'Transferencia electrónica') THEN monto ELSE 0 END) as trans_facturada,
                    SUM(CASE WHEN metodo_pago IN ('Tarjeta de crédito', 'Tarjeta de débito') THEN monto ELSE 0 END) as tarjeta_facturada,
                    SUM(CASE WHEN metodo_pago = 'Efectivo' THEN monto ELSE 0 END) as efectivo_facturado,
                    SUM(CASE WHEN metodo_pago IN ('Cheque', 'Cheque nominativo') THEN monto ELSE 0 END) as cheque
                 FROM facturas 
                 $sWhere 
                 GROUP BY sucursal, DATE(fecha_factura) 
                 ORDER BY fecha DESC, sucursal ASC 
                 LIMIT $offset, $per_page";

    $query = mysqli_query($conexion_gen, $sql_data);

    if (!$query) {
        echo "<div class='alert alert-danger'>Error en la base de datos: " . mysqli_error($conexion_gen) . "</div>";
        exit;
    }

    if ($total_records > 0) {
        include 'pagination.php';
        ?>
        <div style="text-align: center; margin-bottom: 15px;">
            <?php echo paginate($reload, $page, $total_pages, $adjacents); ?>
        </div>
        <table class="table table-striped jambo_table bulk_action">
            <thead>
                <tr class="headings">
                    <th class="column-title">Sucursal</th>
                    <th class="column-title">Fecha</th>
                    <th class="column-title text-right">Contado</th>
                    <th class="column-title text-right">Abonos</th>
                    <th class="column-title text-right">Trans Pub General</th>
                    <th class="column-title text-right">Trans Facturada</th>
                    <th class="column-title text-right">Tarjeta</th>
                    <th class="column-title text-right">Tarjeta Pub General</th>
                    <th class="column-title text-right">Tarjeta Facturada</th>
                    <th class="column-title text-right">Efectivo Facturado</th>
                    <th class="column-title text-right">Cheque</th>
                    <th class="column-title text-right">Depósitos</th>
                    <th class="column-title text-right">Factura Diaria</th>
                    <th class="column-title text-right">Diferencia</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                while ($row = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
                    $sucursal = htmlspecialchars($row['sucursal'] ? $row['sucursal'] : '---');
                    $fecha = !empty($row['fecha']) ? date('d-m-Y', strtotime($row['fecha'])) : '---';
                    
                    // Colores por sucursal
                    $branch_class = 'label-' . strtolower((string) $row['sucursal']);
                    
                    // Columnas calculadas de facturación activa
                    $trans_facturada = floatval($row['trans_facturada']);
                    $tarjeta_facturada = floatval($row['tarjeta_facturada']);
                    $efectivo_facturado = floatval($row['efectivo_facturado']);
                    $cheque = floatval($row['cheque']);
                    
                    // Columnas sin datos para cálculo por el momento
                    $contado = 0.00;
                    $abonos = 0.00;
                    $trans_pub_general = 0.00;
                    $tarjeta = 0.00;
                    $tarjeta_pub_general = 0.00;
                    $depositos = 0.00;
                    $factura_diaria = 0.00;
                    $diferencia = 0.00;
                    ?>
                    <tr>
                        <td><span class="label <?php echo $branch_class; ?>"><?php echo $sucursal; ?></span></td>
                        <td><?php echo $fecha; ?></td>
                        <td align="right" style="color: #95a5a6;">$<?php echo number_format($contado, 2); ?></td>
                        <td align="right" style="color: #95a5a6;">$<?php echo number_format($abonos, 2); ?></td>
                        <td align="right" style="color: #95a5a6;">$<?php echo number_format($trans_pub_general, 2); ?></td>
                        <td align="right" style="font-weight: bold; color: #3498db;">$<?php echo number_format($trans_facturada, 2); ?></td>
                        <td align="right" style="color: #95a5a6;">$<?php echo number_format($tarjeta, 2); ?></td>
                        <td align="right" style="color: #95a5a6;">$<?php echo number_format($tarjeta_pub_general, 2); ?></td>
                        <td align="right" style="font-weight: bold; color: #3498db;">$<?php echo number_format($tarjeta_facturada, 2); ?></td>
                        <td align="right" style="font-weight: bold; color: #26B99A;">$<?php echo number_format($efectivo_facturado, 2); ?></td>
                        <td align="right" style="font-weight: bold; color: #1abc9c;">$<?php echo number_format($cheque, 2); ?></td>
                        <td align="right" style="color: #95a5a6;">$<?php echo number_format($depositos, 2); ?></td>
                        <td align="right" style="color: #95a5a6;">$<?php echo number_format($factura_diaria, 2); ?></td>
                        <td align="right" style="color: #95a5a6;">$<?php echo number_format($diferencia, 2); ?></td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
        <div style="text-align: center; margin-top: 15px;">
            <?php echo paginate($reload, $page, $total_pages, $adjacents); ?>
        </div>
        <?php
    } else {
        ?>
        <div class="alert alert-warning alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <strong>Aviso!</strong> No se encontraron facturas registradas en el rango de fechas y filtros seleccionados.
        </div>
        <?php
    }
}
?>
