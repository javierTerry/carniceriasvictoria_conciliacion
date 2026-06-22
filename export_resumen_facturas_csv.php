<?php
session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require_once "config/config.php";

// Escapar inputs para evitar inyección SQL
$branch = isset($_GET['branch']) ? mysqli_real_escape_string($conexion_gen, $_GET['branch']) : 'all';
$date_start = isset($_GET['date_start']) ? mysqli_real_escape_string($conexion_gen, $_GET['date_start']) : '';
$date_end = isset($_GET['date_end']) ? mysqli_real_escape_string($conexion_gen, $_GET['date_end']) : '';

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

// Consulta principal agrupada por sucursal y fecha de creación de factura (sin paginación para exportar completo)
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
             ORDER BY fecha DESC, sucursal ASC";

$query = mysqli_query($conexion_gen, $sql_data);

if (!$query) {
    die("Error en la base de datos: " . mysqli_error($conexion_gen));
}

// Configurar cabeceras para la descarga del CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=resumen_facturas_' . date('Ymd_His') . '.csv');

// Abrir flujo de salida
$output = fopen('php://output', 'w');

// Añadir BOM UTF-8 para que Excel detecte correctamente los caracteres especiales (acentos, Ñ)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Cabeceras del CSV
fputcsv($output, [
    'Sucursal',
    'Fecha',
    'Contado',
    'Abonos',
    'Trans Pub General',
    'Trans Facturada',
    'Tarjeta',
    'Tarjeta Pub General',
    'Tarjeta Facturada',
    'Efectivo Facturado',
    'Cheque',
    'Depósitos',
    'Factura Diaria',
    'Diferencia'
]);

// Recorrer y escribir los datos en el CSV
while ($row = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
    $sucursal = $row['sucursal'] ? $row['sucursal'] : '---';
    $fecha = !empty($row['fecha']) ? date('d-m-Y', strtotime($row['fecha'])) : '---';
    
    $trans_facturada = floatval($row['trans_facturada']);
    $tarjeta_facturada = floatval($row['tarjeta_facturada']);
    $efectivo_facturado = floatval($row['efectivo_facturado']);
    $cheque = floatval($row['cheque']);
    
    // Columnas sin datos o a calcular en un futuro
    $contado = 0.00;
    $abonos = 0.00;
    $trans_pub_general = 0.00;
    $tarjeta = 0.00;
    $tarjeta_pub_general = 0.00;
    $depositos = 0.00;
    $factura_diaria = 0.00;
    $diferencia = 0.00;

    fputcsv($output, [
        $sucursal,
        $fecha,
        number_format($contado, 2, '.', ''),
        number_format($abonos, 2, '.', ''),
        number_format($trans_pub_general, 2, '.', ''),
        number_format($trans_facturada, 2, '.', ''),
        number_format($tarjeta, 2, '.', ''),
        number_format($tarjeta_pub_general, 2, '.', ''),
        number_format($tarjeta_facturada, 2, '.', ''),
        number_format($efectivo_facturado, 2, '.', ''),
        number_format($cheque, 2, '.', ''),
        number_format($depositos, 2, '.', ''),
        number_format($factura_diaria, 2, '.', ''),
        number_format($diferencia, 2, '.', '')
    ]);
}

fclose($output);
exit;
