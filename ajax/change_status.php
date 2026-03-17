<?php
/**
 * change_status.php
 * Actualiza el estatus de un ticket en la sucursal correspondiente.
 */
session_start();
require_once "../config/config.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit;
}

$mov_id = $_POST['mov_id'] ?? '';
$branch = $_POST['branch'] ?? '';
$new_status = $_POST['status'] ?? 2; // Default to Pendiente (2)

if (empty($mov_id) || empty($branch)) {
    echo json_encode(['success' => false, 'message' => 'Datos insuficientes']);
    exit;
}

$branchesConfigs = getBranchesConfig();

if (!isset($branchesConfigs[$branch])) {
    echo json_encode(['success' => false, 'message' => 'Sucursal no válida']);
    exit;
}

$config = $branchesConfigs[$branch];
$branchConn = mysqli_connect($config['host'], $config['user'], $config['pass'], $config['db']);

if (!$branchConn) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la sucursal']);
    exit;
}

mysqli_set_charset($branchConn, "utf8");

// Usamos prepared statement para mayor seguridad
$sql = "UPDATE vtahead SET is_active = ? WHERE mov_id = ?";
$stmt = mysqli_prepare($branchConn, $sql);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "is", $new_status, $mov_id);
    
    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            echo json_encode(['success' => true, 'message' => 'Estatus actualizado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No se encontró el registro o ya tenía ese estatus']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al ejecutar la actualización']);
    }
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['success' => false, 'message' => 'Error en la preparación de la consulta']);
}

mysqli_close($branchConn);
?>
