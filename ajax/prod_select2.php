<?php
include "../config/config.php";

header('Content-Type: application/json');

$q = isset($_GET['q']) ? $_GET['q'] : '';
$q = $conexion_gen->real_escape_string($q);

$sql = "SELECT id, descripcion, clave_sat, unidad_sat FROM arts WHERE is_active=1";
if ($q !== '') {
    $sql .= " AND (descripcion LIKE '%$q%' OR clave_sat LIKE '%$q%')";
}
$sql .= " ORDER BY descripcion ASC LIMIT 30";

$res = $conexion_gen->query($sql);
$results = [];

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $results[] = [
            'id' => $row['id'],
            'text' => $row['descripcion'],
            'prod_data' => [
                'clave_sat' => $row['clave_sat'],
                'unidad_sat' => $row['unidad_sat']
            ]
        ];
    }
}

echo json_encode(['results' => $results]);
