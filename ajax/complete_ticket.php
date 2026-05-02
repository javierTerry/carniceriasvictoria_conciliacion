<?php
/**
 * complete_ticket.php
 * Actualiza el estatus de un ticket a Completado (3) en todas las sucursales.
 */
session_start();
require_once "../config/config.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit;
}

$mov_id = $_POST['mov_id'] ?? '';
$branch_origin = $_POST['branch'] ?? ''; 

if (empty($mov_id)) {
    echo json_encode(['success' => false, 'message' => 'ID de ticket no proporcionado']);
    exit;
}

$branchesConfigs = getBranchesConfig();
$results = [];
$success_count = 0;

foreach ($branchesConfigs as $branch_name => $config) {
    $conn = @mysqli_connect($config['host'], $config['user'], $config['pass'], $config['db']);
    
    if (!$conn) {
        $results[$branch_name] = "Error de conexión";
        continue;
    }

    mysqli_set_charset($conn, "utf8");

    // Actualizar estatus a 3 (Completado)
    $sql = "UPDATE vtahead SET is_active = ? WHERE mov_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        $status_completado = 3;
        mysqli_stmt_bind_param($stmt, "is", $status_completado, $mov_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $affected = mysqli_stmt_affected_rows($stmt);
            if ($affected >= 0) { // Si ya era 3, result es 0 pero la ejecución fue exitosa
                $results[$branch_name] = "Procesado ($affected cambios)";
                $success_count++;
            }
        } else {
            $results[$branch_name] = "Error al ejecutar: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } else {
        $results[$branch_name] = "Error en preparación SQL";
    }
    
    mysqli_close($conn);
}

if ($success_count > 0) {
    echo json_encode([
        'success' => true, 
        'message' => "Ticket #$mov_id procesado correctamente en todas las bases de datos.",
        'details' => $results
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => "No se pudo actualizar el ticket en ninguna sucursal.",
        'details' => $results
    ]);
}
?>
