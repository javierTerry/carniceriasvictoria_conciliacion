<?php
session_start();
require_once "../config/config.php";

header('Content-Type: application/json');

$group_id = $_POST['group_id'] ?? 0;
$branch = $_POST['branch'] ?? '';

if (empty($group_id) || empty($branch)) {
    echo json_encode(['success' => false, 'message' => 'Datos insuficientes para el cierre.']);
    exit;
}

$branchesConfigs = getBranchesConfig();
if (!isset($branchesConfigs[$branch])) {
    echo json_encode(['success' => false, 'message' => 'Sucursal inválida.']);
    exit;
}

$config = $branchesConfigs[$branch];
$targetConn = mysqli_connect($config['host'], $config['user'], $config['pass'], $config['db']);
if (!$targetConn) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión para cierre.']);
    exit;
}
mysqli_set_charset($targetConn, "utf8");

mysqli_begin_transaction($targetConn);

try {
    // 1. Obtener todos los mov_id del grupo
    $sql_rel = "SELECT mov_id FROM groups_tickets_details WHERE group_id = " . intval($group_id);
    $res_rel = mysqli_query($targetConn, $sql_rel);
    $mov_ids = [];
    while($row = mysqli_fetch_array($res_rel)) {
        $mov_ids[] = "'" . mysqli_real_escape_string($targetConn, $row['mov_id']) . "'";
    }

    if (!empty($mov_ids)) {
        // 2. Actualizar Estatus de los tickets individuales en vtahead a 3 (Facturado)
        $sql_upd_vta = "UPDATE vtahead SET is_active = 3 WHERE mov_id IN (" . implode(',', $mov_ids) . ")";
        if (!mysqli_query($targetConn, $sql_upd_vta)) {
            throw new Exception("Error al actualizar estatus de tickets: " . mysqli_error($targetConn));
        }
    }

    // 3. Actualizar Estatus del grupo en groups_tickets a 3 (Facturado)
    $sql_upd_grp = "UPDATE groups_tickets SET status = 3 WHERE id = " . intval($group_id);
    if (!mysqli_query($targetConn, $sql_upd_grp)) {
        throw new Exception("Error al actualizar estatus del grupo: " . mysqli_error($targetConn));
    }

    mysqli_commit($targetConn);
    echo json_encode(['success' => true, 'message' => 'Grupo y tickets actualizados exitosamente.']);

} catch (Exception $e) {
    mysqli_rollback($targetConn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

mysqli_close($targetConn);
