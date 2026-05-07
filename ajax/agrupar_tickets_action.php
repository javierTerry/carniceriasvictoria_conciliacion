<?php
session_start();
require_once "../config/config.php";

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';
$branch = $_REQUEST['branch'] ?? '';

if (empty($branch)) {
    echo json_encode(['success' => false, 'message' => 'Falta sucursal']);
    exit;
}

// Select connection based on branch
$branchesConfigs = getBranchesConfig();
if (!isset($branchesConfigs[$branch])) {
    echo json_encode(['success' => false, 'message' => 'Sucursal no válida']);
    exit;
}

$config = $branchesConfigs[$branch];
$targetConn = mysqli_connect($config['host'], $config['user'], $config['pass'], $config['db']);
if (!$targetConn) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la BD de la sucursal']);
    exit;
}
mysqli_set_charset($targetConn, "utf8");

if ($action == 'get_groups') {
    // Obtener grupos activos (status = 1) - Se quita el filtro de cust_id para permitir agrupación global
    $sql = "SELECT id, name, deposit_amount, total_tickets_amount, ticket_count FROM groups_tickets WHERE status = 1 ORDER BY created_at DESC";
    $query = mysqli_query($targetConn, $sql);
    $groups = [];
    while ($row = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
        $groups[] = $row;
    }
    echo json_encode(['success' => true, 'groups' => $groups]);
    exit;
}

if ($action == 'add_to_group') {
    $group_id = $_POST['group_id'] ?? 0;
    $mov_id = $_POST['mov_id'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    $group_name = $_POST['group_name'] ?? '';
    $deposit_amount = floatval($_POST['deposit_amount'] ?? 0);
    $cust_id = intval($_POST['cust_id'] ?? 0);

    if (empty($cust_id)) {
        echo json_encode(['success' => false, 'message' => 'No se identificó el cliente del ticket.']);
        exit;
    }

    mysqli_begin_transaction($targetConn);

    try {
        if ($group_id == 'new') {
            // Regla: Reemplazar espacios por guiones bajos
            $clean_name = str_replace(' ', '_', trim($group_name));

            // Create new group con cust_id
            $stmt = mysqli_prepare($targetConn, "INSERT INTO groups_tickets (name, deposit_amount, cust_id, status) VALUES (?, ?, ?, 1)");
            mysqli_stmt_bind_param($stmt, "sdi", $clean_name, $deposit_amount, $cust_id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error al crear el grupo: " . mysqli_error($targetConn));
            }
            $group_id = mysqli_insert_id($targetConn);
            mysqli_stmt_close($stmt);

            // Regla: Agregar -ID al final de la descripción
            $final_group_name = $clean_name . "-" . $group_id;
            $stmt_upd = mysqli_prepare($targetConn, "UPDATE groups_tickets SET name = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_upd, "si", $final_group_name, $group_id);
            mysqli_stmt_execute($stmt_upd);
            mysqli_stmt_close($stmt_upd);
            
            $group_name = $final_group_name;
        } else {
            // Se elimina la validación de coincidencia de cliente para permitir agrupación global
        }

        // Validate if ticket already in group
        $stmt_val = mysqli_prepare($targetConn, "SELECT id FROM groups_tickets_details WHERE group_id = ? AND mov_id = ?");
        mysqli_stmt_bind_param($stmt_val, "is", $group_id, $mov_id);
        mysqli_stmt_execute($stmt_val);
        mysqli_stmt_store_result($stmt_val);
        if (mysqli_stmt_num_rows($stmt_val) > 0) {
            throw new Exception("Este ticket ya se encuentra en el grupo seleccionado.");
        }
        mysqli_stmt_close($stmt_val);

        // Add ticket to details
        $stmt_det = mysqli_prepare($targetConn, "INSERT INTO groups_tickets_details (group_id, mov_id, branch, amount) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt_det, "issd", $group_id, $mov_id, $branch, $amount);
        if (!mysqli_stmt_execute($stmt_det)) {
            throw new Exception("Error al agregar ticket al grupo: " . mysqli_error($targetConn));
        }
        mysqli_stmt_close($stmt_det);

        // Update ticket status to 4 (Agrupado)
        $stmt_upd_vta = mysqli_prepare($targetConn, "UPDATE vtahead SET is_active = 4 WHERE mov_id = ?");
        mysqli_stmt_bind_param($stmt_upd_vta, "s", $mov_id);
        mysqli_stmt_execute($stmt_upd_vta);
        mysqli_stmt_close($stmt_upd_vta);

        // Update group totals
        $sql_update = "UPDATE groups_tickets 
                       SET total_tickets_amount = (SELECT IFNULL(SUM(amount),0) FROM groups_tickets_details WHERE group_id = $group_id),
                           ticket_count = (SELECT COUNT(*) FROM groups_tickets_details WHERE group_id = $group_id)
                       WHERE id = $group_id";
        if (!mysqli_query($targetConn, $sql_update)) {
            throw new Exception("Error al actualizar totales del grupo: " . mysqli_error($targetConn));
        }

        // Fetch updated info for the success message
        $res_info = mysqli_query($targetConn, "SELECT name, deposit_amount, total_tickets_amount FROM groups_tickets WHERE id = $group_id");
        $info = mysqli_fetch_array($res_info, MYSQLI_ASSOC);

        mysqli_commit($targetConn);
        echo json_encode([
            'success' => true,
            'message' => 'Ticket agrupado correctamente y estatus actualizado.',
            'group_name' => $info['name'],
            'deposit_amount' => $info['deposit_amount'],
            'total_tickets_amount' => $info['total_tickets_amount'],
            'mov_id' => $mov_id
        ]);

    } catch (Exception $e) {
        mysqli_rollback($targetConn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

if ($action == 'ungroup_ticket') {
    $group_id = $_POST['group_id'] ?? 0;
    $mov_id = $_POST['mov_id'] ?? '';

    mysqli_begin_transaction($targetConn);
    try {
        // Delete relationship
        $stmt_del = mysqli_prepare($targetConn, "DELETE FROM groups_tickets_details WHERE group_id = ? AND mov_id = ?");
        mysqli_stmt_bind_param($stmt_del, "is", $group_id, $mov_id);
        if (!mysqli_stmt_execute($stmt_del)) {
            throw new Exception("Error al eliminar relación.");
        }
        mysqli_stmt_close($stmt_del);

        // Restore ticket status to 1 (Activo)
        $stmt_upd_vta = mysqli_prepare($targetConn, "UPDATE vtahead SET is_active = 1 WHERE mov_id = ?");
        mysqli_stmt_bind_param($stmt_upd_vta, "s", $mov_id);
        mysqli_stmt_execute($stmt_upd_vta);
        mysqli_stmt_close($stmt_upd_vta);

        // Update group totals
        $sql_update = "UPDATE groups_tickets 
                       SET total_tickets_amount = (SELECT IFNULL(SUM(amount),0) FROM groups_tickets_details WHERE group_id = $group_id),
                           ticket_count = (SELECT COUNT(*) FROM groups_tickets_details WHERE group_id = $group_id)
                       WHERE id = $group_id";
        mysqli_query($targetConn, $sql_update);

        mysqli_commit($targetConn);
        echo json_encode(['success' => true, 'message' => 'Ticket desagrupado correctamente.']);
    } catch (Exception $e) {
        mysqli_rollback($targetConn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

mysqli_close($targetConn);
