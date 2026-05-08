<?php
/**
 * ajax/prod_action.php
 * Endpoint para acciones CRUD de productos.
 */
include "../config/config.php";
include "../classes/ProductMapper.php";

header('Content-Type: application/json');

$mapper = new ProductMapper($conexion_gen);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            
            // Validación de tipos y saneamiento básico
            $data = [
                'descripcion' => strip_tags((string)($_POST['descripcion'] ?? '')),
                'clave_sat'   => (int)($_POST['clave_sat'] ?? 0),
                'servicios'   => strip_tags((string)($_POST['servicios'] ?? '')),
                'unidad_sat'  => substr(strip_tags((string)($_POST['unidad_sat'] ?? '')), 0, 4),
                'unidad'      => substr(strip_tags((string)($_POST['unidad'] ?? '')), 0, 4)
            ];

            if (empty($data['descripcion'])) {
                throw new Exception("La descripci&oacute;n es obligatoria.");
            }

            if ($id > 0) {
                $success = $mapper->update($id, $data);
                $msg = $success ? "Producto actualizado correctamente." : "Error al actualizar producto.";
            } else {
                $success = $mapper->create($data);
                $msg = $success ? "Producto guardado correctamente." : "Error al guardar producto.";
            }

            echo json_encode(['success' => $success, 'message' => $msg]);

        } elseif ($action === 'delete') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($id <= 0) throw new Exception("ID inv&aacute;lido.");

            $success = $mapper->delete($id);
            echo json_encode(['success' => $success, 'message' => $success ? "Producto eliminado." : "Error al eliminar."]);

        } elseif ($action === 'get') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $product = $mapper->findById($id);
            if ($product) {
                echo json_encode(['success' => true, 'data' => $product]);
            } else {
                echo json_encode(['success' => false, 'message' => "Producto no encontrado."]);
            }
        } else {
            throw new Exception("Acci&oacute;n no v&aacute;lida.");
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
