<?php
/**
 * complete_ticket.php
 * Actualiza el estatus de un ticket a Completado/Facturado (is_active = 3) en la sucursal correspondiente.
 */
session_start();
require_once "../config/config.php";

header('Content-Type: application/json');

$log_dir = __DIR__ . "/../logs";
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0755, true);
}
$log_file = $log_dir . "/facturacion_individual.log";

function log_complete_ticket(string $message, string $level = 'INFO', array $context = []): void
{
    global $log_file;
    $date = date('Y-m-d H:i:s');
    $ctx = !empty($context) ? ' | Contexto: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    @file_put_contents($log_file, "[{$date}] [{$level}] [COMPLETE_TICKET] {$message}{$ctx}\n", FILE_APPEND);
}

if (!isset($_SESSION['user_id'])) {
    log_complete_ticket("Sesión no iniciada al intentar completar ticket", "WARNING", $_POST);
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit;
}

$mov_id = trim((string)($_POST['mov_id'] ?? ''));
$branch_origin = trim((string)($_POST['branch'] ?? ''));

if (empty($mov_id)) {
    log_complete_ticket("ID de ticket no proporcionado", "WARNING", $_POST);
    echo json_encode(['success' => false, 'message' => 'ID de ticket no proporcionado']);
    exit;
}

// Caso especial: Sucursal CEP (Emisión directa en carvic_sysvic_general, no posee tabla vtahead POS)
if (strtoupper($branch_origin) === 'CEP') {
    log_complete_ticket("Ticket CEP completado directamente en BD General", "INFO", [
        'mov_id' => $mov_id,
        'branch' => $branch_origin
    ]);
    echo json_encode([
        'success' => true,
        'message' => "Factura #$mov_id (CEP) procesada correctamente.",
        'details' => ['CEP' => 'Emisión directa en BD General']
    ]);
    exit;
}

$branchesConfigs = getBranchesConfig();
$targetBranches = [];

// Si se indicó la sucursal origen del ticket, actualizar únicamente esa sucursal POS
if (!empty($branch_origin) && isset($branchesConfigs[$branch_origin])) {
    $targetBranches[$branch_origin] = $branchesConfigs[$branch_origin];
} else {
    $targetBranches = $branchesConfigs;
}

$results = [];
$success_count = 0;

foreach ($targetBranches as $branch_name => $config) {
    $conn = null;
    try {
        $conn = @mysqli_connect($config['host'], $config['user'], $config['pass'], $config['db']);
    } catch (\Throwable $connEx) {
        $results[$branch_name] = "Excepción de conexión: " . $connEx->getMessage();
        log_complete_ticket("Error de conexión a BD de sucursal {$branch_name}", "ERROR", [
            'mov_id' => $mov_id,
            'branch' => $branch_name,
            'db' => $config['db'] ?? '',
            'error' => $connEx->getMessage()
        ]);
        continue;
    }

    if (!$conn) {
        $err = mysqli_connect_error();
        $results[$branch_name] = "Error de conexión: " . $err;
        log_complete_ticket("Fallo al conectar a BD de sucursal {$branch_name}", "ERROR", [
            'mov_id' => $mov_id,
            'branch' => $branch_name,
            'error' => $err
        ]);
        continue;
    }

    try {
        mysqli_set_charset($conn, "utf8mb4");

        // Actualizar estatus a 3 (Facturado / Completado)
        $sql = "UPDATE vtahead SET is_active = ? WHERE mov_id = ?";
        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            $status_completado = 3;
            mysqli_stmt_bind_param($stmt, "is", $status_completado, $mov_id);

            if (mysqli_stmt_execute($stmt)) {
                $affected = mysqli_stmt_affected_rows($stmt);
                if ($affected >= 0) {
                    $results[$branch_name] = "Procesado ($affected cambios)";
                    $success_count++;
                }
            } else {
                $results[$branch_name] = "Error al ejecutar: " . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        } else {
            $results[$branch_name] = "Error en preparación SQL: " . mysqli_error($conn);
        }
    } catch (\Throwable $queryEx) {
        $results[$branch_name] = "Excepción SQL: " . $queryEx->getMessage();
        log_complete_ticket("Excepción al actualizar vtahead en {$branch_name}", "ERROR", [
            'mov_id' => $mov_id,
            'branch' => $branch_name,
            'error' => $queryEx->getMessage()
        ]);
    } finally {
        if ($conn) {
            @mysqli_close($conn);
        }
    }
}

if ($success_count > 0) {
    log_complete_ticket("Ticket actualizado exitosamente a is_active = 3", "INFO", [
        'mov_id' => $mov_id,
        'branch' => $branch_origin,
        'results' => $results
    ]);
    echo json_encode([
        'success' => true,
        'message' => "Ticket #$mov_id procesado correctamente.",
        'details' => $results
    ]);
} else {
    log_complete_ticket("No se pudo actualizar el ticket en la sucursal", "ERROR", [
        'mov_id' => $mov_id,
        'branch' => $branch_origin,
        'results' => $results
    ]);
    echo json_encode([
        'success' => false,
        'message' => "No se pudo actualizar el estatus del ticket #$mov_id.",
        'details' => $results
    ]);
}
?>
