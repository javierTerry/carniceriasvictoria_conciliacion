<?php
session_start();
require_once "../config/config.php";

header('Content-Type: application/json');

$response = [
    'trends' => [],
    'hourly' => [],
    'top_customers' => [],
    'summary' => []
];

$hoy = date('Y-m-d');
$mes_atras = date('Y-m-d', strtotime('-180 days'));

// 1. Sales Trend (Last 30 Days)
$sql_trend = "SELECT created_at, SUM(sumimp) as total 
              FROM vtahead 
              WHERE is_active = 1 AND created_at >= ? 
              GROUP BY created_at 
              ORDER BY created_at ASC";
$stmt = mysqli_prepare($conexion, $sql_trend);
mysqli_stmt_bind_param($stmt, "s", $mes_atras);
mysqli_stmt_execute($stmt);
$res_trend = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_array($res_trend, MYSQLI_ASSOC)) {
    $response['trends'][] = [
        'date' => date('d/m', strtotime($row['created_at'])),
        'total' => floatval($row['total'])
    ];
}

// 2. Hourly Distribution (Today)
$sql_hourly = "SELECT HOUR(hour_at) as hora, SUM(sumimp) as total 
               FROM vtahead 
               WHERE is_active = 1 AND created_at = ? 
               GROUP BY HOUR(hour_at) 
               ORDER BY HOUR(hour_at) ASC";
$stmt = mysqli_prepare($conexion, $sql_hourly);
mysqli_stmt_bind_param($stmt, "s", $hoy);
mysqli_stmt_execute($stmt);
$res_hourly = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_array($res_hourly, MYSQLI_ASSOC)) {
    $response['hourly'][] = [
        'hour' => $row['hora'] . ":00",
        'total' => floatval($row['total'])
    ];
}

// 3. Top 5 Customers (All Time or Last 30 Days? Let's go with Last 30 Days)
$sql_top = "SELECT B.name as cliente, SUM(A.sumimp) as total 
            FROM vtahead A 
            INNER JOIN cust B ON A.cust_id = B.id 
            WHERE A.is_active = 1 AND A.created_at >= ? AND A.cust_id != 1
            GROUP BY A.cust_id 
            ORDER BY total DESC 
            LIMIT 5";
$stmt = mysqli_prepare($conexion, $sql_top);
mysqli_stmt_bind_param($stmt, "s", $mes_atras);
mysqli_stmt_execute($stmt);
$res_top = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_array($res_top, MYSQLI_ASSOC)) {
    $response['top_customers'][] = [
        'name' => utf8_decode($row['cliente']),
        'total' => floatval($row['total'])
    ];
}

echo json_encode($response);
?>