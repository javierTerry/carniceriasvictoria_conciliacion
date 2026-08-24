<?php

ob_start();
session_start();

require_once "../config/config.php";   // Conecta a la base de datos
require_once "../config/numtolet.php"; // Función número en letras
require_once "../config/plantillatv.php";

// Recibo id venta y sucursal
$id = isset($_GET['xyz']) ? intval($_GET['xyz']) : 0;
$branch = isset($_GET['branch']) ? $_GET['branch'] : '';

if ($id <= 0) {
   die("ID de venta no válido.");
}

// Select connection based on branch
$targetConn = $conexion; // Default to main connection (Obrador)
if (!empty($branch)) {
   $branchesConfigs = getBranchesConfig();
   if (isset($branchesConfigs[$branch])) {
      $config = $branchesConfigs[$branch];
      $targetConn = mysqli_connect($config['host'], $config['user'], $config['pass'], $config['db']);
      if (!$targetConn) {
         die("Error: No se pudo conectar a la base de datos de la sucursal $branch.");
      }
   }
}

/**
 * Función para renderizar una sección del ticket (ORIGINAL o COPIA)
 */
function renderTicketSection($pdf, $data, $items_array, $type_label)
{
   $pdf->AddPage();

   // Leyenda de Factura Cancelada o Ticket Inactivo
   if (!empty($data['is_fac_cancelled'])) {
      $pdf->SetTextColor(178, 34, 34);
      $pdf->SetFont('Arial', 'B', 11);
      $pdf->Cell(100, 6, utf8_decode('*** FACTURA CANCELADA ***'), 0, 1, 'C');
      if (!empty($data['fac_folio'])) {
         $pdf->SetFont('Arial', 'B', 8);
         $pdf->Cell(100, 5, utf8_decode('Serie: ' . $data['fac_serie'] . '  Folio: ' . $data['fac_folio'] . ' (SAT)'), 0, 1, 'C');
      }
      $pdf->SetTextColor(0, 0, 0);
      $pdf->Ln(2);
   } elseif (!empty($data['is_ticket_inactive'])) {
      $pdf->SetTextColor(108, 117, 125);
      $pdf->SetFont('Arial', 'B', 11);
      $pdf->Cell(100, 6, utf8_decode('*** TICKET INACTIVO ***'), 0, 1, 'C');
      $pdf->SetTextColor(0, 0, 0);
      $pdf->Ln(2);
   }

   $pdf->SetFont('Arial', 'B', 9);

   // Encabezado del ticket
   $leyenda_header = sprintf(
      "Venta: %-15s Fecha: %-20s %s",
      $data['mov_id'],
      date('d-m-Y', strtotime($data['created_at'])),
      $data['hour_at']
   );
   $pdf->Cell(100, 6, $leyenda_header, 0, 1, 'L');

   $lineas_sep = str_repeat('-', 90);
   $pdf->SetFont('Arial', '', 9);
   $pdf->Cell(100, 6, $lineas_sep, 0, 1, 'C');

   $pdf->SetFont('Arial', 'B', 9);
   $pdf->Cell(100, 6, utf8_decode($data['cliente']), 0, 1, 'C');

   $header_cols = " Peso   Producto" . str_repeat(' ', 40) . "Precio" . str_repeat(' ', 5) . "Importe";
   $pdf->Cell(100, 6, $header_cols, 0, 1, 'L');

   $pdf->SetFont('Arial', '', 9);
   $pdf->Cell(100, 6, $lineas_sep, 0, 1, 'C');
   $pdf->Ln(2);

   // Partidas
   $pdf->SetFont('Arial', 'B', 9);
   foreach ($items_array as $item) {
      $pdf->Cell(12, 6, number_format($item['qty'], 3), 0, 0, 'R');
      $pdf->Cell(56, 6, utf8_decode($item['name']), 0, 0, 'L');
      $pdf->Cell(12, 6, number_format($item['price'], 2), 0, 0, 'R');
      $pdf->Cell(20, 6, number_format($item['amount'], 2), 0, 0, 'R');
      $pdf->Ln();
   }

   $pdf->SetFont('Arial', '', 9);
   $pdf->Cell(100, 6, $lineas_sep, 0, 1, 'C');

   // Totales
   $pdf->SetFont('Arial', 'B', 9);
   $pdf->Cell(12, 6, number_format($data['sumqty'], 3), 0, 0, 'R');
   $pdf->Cell(68, 6, "TOTAL  $", 0, 0, 'R');
   $pdf->Cell(18, 6, number_format($data['sumimp'], 2), 0, 1, 'R');

   // Total en letras
   $pdf->SetFont('Arial', 'B', 8);
   $pdf->Cell(100, 6, utf8_decode(NumLetras($data['sumimp'])), 0, 1, 'L');

   // Información de pago y cambio
   $pdf->SetFont('Arial', '', 9);
   if ($data['cust_id'] == 1) {
      $cambio = $data['recibo'] - $data['sumimp'];
      $info_pago = sprintf(
         "Productos: %d   Recibo: %.2f   Cambio: %.2f",
         $data['items'],
         $data['recibo'],
         $cambio
      );
   } else {
      $cambio = $data['recibo'] - $data['acuenta'];
      $info_pago = sprintf(
         "Productos: %d   A cuenta: %.2f   Recibo: %.2f   Cambio: %.2f",
         $data['items'],
         $data['acuenta'],
         $data['recibo'],
         $cambio
      );
   }
   $pdf->Cell(100, 6, $info_pago, 0, 1, 'L');
   $pdf->Cell(100, 6, utf8_decode("Forma de pago: {$data['fcode']} - {$data['fname']}"), 0, 1, 'L');
   $pdf->Ln(4);

   // Atendió y Pie
   $pdf->SetFont('Arial', 'B', 9);
   $pdf->Cell(100, 6, utf8_decode("ATENDIO: " . $data['uname']), 0, 1, 'C');
   $pdf->Ln(4);
   $pdf->Cell(100, 6, "SERVICIO A DOMICILIO" . str_repeat(' ', 15) . "TEL. 55 52 04 16 20", 0, 1, 'C');
   $pdf->Ln(4);

   $pdf->SetFont('Arial', 'BI', 9);
   $pdf->Cell(100, 6, 'ESTIMADO CLIENTE', 0, 1, 'C');
   $pdf->SetFont('Arial', '', 8);
   $pdf->MultiCell(100, 4, utf8_decode('Para solicitar factura envíe un mensaje de WhatsApp al 5522939605, contará con 5 días hábiles a partir de la fecha de su compra.'), 0, 'J');
   $pdf->Ln(2);
   $pdf->SetFont('Arial', 'B', 9);
   $pdf->Cell(100, 6, utf8_decode('NO SE REALIZARÁN FACTURAS DE MESES ANTERIORES'), 0, 1, 'C');
   $pdf->Ln(4);
   $pdf->Cell(100, 6, '***AGRADECEMOS SU PREFERENCIA***', 0, 1, 'C');
   $pdf->Ln(4);
   $pdf->Cell(100, 6, $type_label, 0, 1, 'C');
}

// 1. Traer datos de la empresa
$sql_empresa = "SELECT linea1, linea2, linea3, name, rfc, domicilio, municipio, alias FROM emprsa LIMIT 1";
$res_empresa = mysqli_query($targetConn, $sql_empresa) or die("Error Empresa: " . mysqli_error($targetConn));
$GLOBALS["empresa"] = mysqli_fetch_array($res_empresa);

// 2. Traer datos de la venta (Prepared Statement)
$sql_venta = "SELECT A.mov_id, A.cust_id, A.created_at, A.hour_at, A.sumqty, A.sumimp, A.items, A.is_active,
                     B.name as cliente, A.recibo, A.acuenta, A.fpago, 
                     CONCAT(U.name, ' ', U.lastname) as uname,
                     F.code as fcode, F.name as fname
              FROM vtahead A
              INNER JOIN cust B ON A.cust_id = B.id
              INNER JOIN user U ON A.user_id = U.id
              INNER JOIN fpago F ON A.fpago = F.id
              WHERE A.id = ?";

$stmt = mysqli_prepare($targetConn, $sql_venta);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res_venta = mysqli_stmt_get_result($stmt);
$sale_data = mysqli_fetch_array($res_venta, MYSQLI_ASSOC);

if (!$sale_data) {
   die("Venta no encontrada en la sucursal $branch (ID: $id).");
}

// Validar si la factura está cancelada o si el ticket está inactivo
$sale_data['is_ticket_inactive'] = (isset($sale_data['is_active']) && intval($sale_data['is_active']) === 0);
$sale_data['is_fac_cancelled'] = false;
$sale_data['fac_serie'] = '';
$sale_data['fac_folio'] = '';

if (isset($conexion_gen) && !empty($sale_data['mov_id'])) {
   $stmt_fac = mysqli_prepare($conexion_gen, "SELECT id, serie, folio, uuid, estatus, estado FROM `" . $db_name_gen . "`.`facturas` WHERE mov_id = ? ORDER BY id DESC LIMIT 1");
   if ($stmt_fac) {
      mysqli_stmt_bind_param($stmt_fac, "s", $sale_data['mov_id']);
      if (mysqli_stmt_execute($stmt_fac)) {
         $res_fac = mysqli_stmt_get_result($stmt_fac);
         if ($fac_row = mysqli_fetch_assoc($res_fac)) {
            if ((($fac_row['estado'] ?? '') === 'Cancelada' || (isset($fac_row['estatus']) && $fac_row['estatus'] == 0)) && $sale_data['is_ticket_inactive']) {
               $sale_data['is_fac_cancelled'] = true;
               $sale_data['fac_serie'] = $fac_row['serie'] ?? '';
               $sale_data['fac_folio'] = $fac_row['folio'] ?? '';
            }
         }
      }
      mysqli_stmt_close($stmt_fac);
   }
}

// 3. Traer items de la venta (Prepared Statement)
$sql_items = "SELECT A.qty, B.name, A.price, A.amount 
              FROM vtaitem A 
              INNER JOIN art B ON B.id = A.art_id 
              WHERE A.vta_id = ?";

$stmt_items = mysqli_prepare($targetConn, $sql_items);
mysqli_stmt_bind_param($stmt_items, "i", $id);
mysqli_stmt_execute($stmt_items);
$res_items = mysqli_stmt_get_result($stmt_items);

$items_array = [];
while ($row = mysqli_fetch_array($res_items, MYSQLI_ASSOC)) {
   $items_array[] = $row;
}

// 4. Generación del PDF
$pdf = new PDF('P', 'mm', array(230, 120));
$pdf->AliasNbPages();
$pdf->SetTitle('Venta ' . $sale_data['mov_id'] . ' | Obrador Victoria');

// Etiqueta de pie
if ($sale_data['is_fac_cancelled']) {
   $type_label_final = '*** FACTURA CANCELADA ***';
} elseif ($sale_data['is_ticket_inactive']) {
   $type_label_final = '*** TICKET INACTIVO ***';
} else {
   $type_label_final = "ORIGINAL";
}

// Renderizar Original
renderTicketSection($pdf, $sale_data, $items_array, $type_label_final);

// Renderizar Copia si no es público en general (cust_id != 1)
if ($sale_data['cust_id'] != 1) {
   #renderTicketSection($pdf, $sale_data, $items_array, "COPIA");
}

$pdfname = 'V' . $sale_data['mov_id'] . '.pdf';
$pdf->Output($pdfname, 'I');

// Redirección
echo '<html><head><meta http-equiv="REFRESH" content="0;url=vtanew.php"></head></html>';
?>