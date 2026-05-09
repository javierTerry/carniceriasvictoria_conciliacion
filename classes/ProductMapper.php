<?php
/**
 * ProductMapper.php
 * Clase Data Mapper para gestionar el CRUD de la tabla 'arts' (Productos).
 * Versión compatible con PHP 5.6+ (sin tipos de retorno ni nullable types).
 */

class ProductMapper {
    private $db;
    private $allowedFields = array('descripcion', 'clave_sat', 'servicios', 'unidad_sat', 'unidad');
    private $logPath;

    public function __construct($db, $logPath = "") {
        $this->db = $db;
        $this->logPath = $logPath ? $logPath : __DIR__ . '/../logs/prod.log';
        
        $log_dir = dirname($this->logPath);
        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0755, true);
        }
    }

    /**
     * Obtiene un producto por su descripción exacta.
     */
    public function findByDescription($description) {
        $sql = "SELECT id, descripcion, clave_sat, servicios, unidad_sat, unidad FROM arts WHERE descripcion = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            $this->log("Error en prepare findByDescription: " . $this->db->error, "ERROR");
            return null;
        }
        
        $stmt->bind_param("s", $description);
        $stmt->execute();
        
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $stmt->close();
            return null;
        }

        $stmt->bind_result($id, $desc, $clave_sat, $servicios, $unidad_sat, $unidad);
        $data = null;
        if ($stmt->fetch()) {
            $data = array(
                'id' => $id,
                'descripcion' => $desc,
                'clave_sat' => $clave_sat,
                'servicios' => $servicios,
                'unidad_sat' => $unidad_sat,
                'unidad' => $unidad
            );
        }
        $stmt->close();

        return $data;
    }

    /**
     * Obtiene un producto por su ID único.
     */
    public function findById($id) {
        $sql = "SELECT id, descripcion, clave_sat, servicios, unidad_sat, unidad FROM arts WHERE id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            $this->log("Error en prepare findById: " . $this->db->error, "ERROR");
            return null;
        }
        
        $id = intval($id);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows === 0) {
            $stmt->close();
            return null;
        }

        $stmt->bind_result($r_id, $desc, $clave_sat, $servicios, $unidad_sat, $unidad);
        $data = null;
        if ($stmt->fetch()) {
            $data = array(
                'id' => $r_id,
                'descripcion' => $desc,
                'clave_sat' => $clave_sat,
                'servicios' => $servicios,
                'unidad_sat' => $unidad_sat,
                'unidad' => $unidad
            );
        }
        $stmt->close();
        return $data;
    }

    /**
     * Obtiene productos con soporte para búsqueda y paginación.
     */
    public function findAll($q = '', $limit = 25, $offset = 0) {
        $sWhere = " WHERE is_active = 1 ";
        $params = array();
        $types = "";

        if (!empty($q)) {
            $sWhere .= " AND (descripcion LIKE ? OR clave_sat LIKE ?) ";
            $search_q = "%$q%";
            $params[] = $search_q;
            $params[] = $search_q;
            $types = "ss";
        }

        $sql = "SELECT id, descripcion, clave_sat, servicios, unidad_sat, unidad FROM arts $sWhere ORDER BY descripcion ASC LIMIT ?, ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            $this->log("Error en prepare findAll: " . $this->db->error, "ERROR");
            return array();
        }

        $params[] = intval($offset);
        $params[] = intval($limit);
        $types .= "ii";

        $bind_params = array($types);
        foreach ($params as $key => $value) {
            $bind_params[] = &$params[$key];
        }
        call_user_func_array(array($stmt, 'bind_param'), $bind_params);
        
        $stmt->execute();
        $stmt->store_result();
        
        $stmt->bind_result($id, $desc, $clave_sat, $servicios, $unidad_sat, $unidad);
        $results = array();
        while ($stmt->fetch()) {
            $results[] = array(
                'id' => $id,
                'descripcion' => $desc,
                'clave_sat' => $clave_sat,
                'servicios' => $servicios,
                'unidad_sat' => $unidad_sat,
                'unidad' => $unidad
            );
        }
        $stmt->close();
        return $results;
    }

    /**
     * Cuenta el total de productos con soporte para filtros.
     */
    public function count($q = '') {
        $sWhere = " WHERE is_active = 1 ";
        if (!empty($q)) {
            $sWhere .= " AND (descripcion LIKE ? OR clave_sat LIKE ?) ";
        }

        $sql = "SELECT COUNT(*) FROM arts $sWhere";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            $this->log("Error en prepare count: " . $this->db->error, "ERROR");
            return 0;
        }

        if (!empty($q)) {
            $search_q = "%$q%";
            $stmt->bind_param("ss", $search_q, $search_q);
        }
        
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        
        return intval($count);
    }

    /**
     * Crea un nuevo producto en el catálogo.
     */
    public function create($data) {
        $this->log("Iniciando creación de producto: " . json_encode($data));
        
        $sql = "INSERT INTO arts (descripcion, clave_sat, servicios, unidad_sat, unidad) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        
        if (!$stmt) {
            $this->log("Error en prepare create: " . $this->db->error, "ERROR");
            return false;
        }

        $stmt->bind_param("sisss", 
            $data['descripcion'], 
            $data['clave_sat'], 
            $data['servicios'], 
            $data['unidad_sat'], 
            $data['unidad']
        );

        if ($stmt->execute()) {
            $this->log("Producto creado exitosamente. ID: " . $stmt->insert_id);
            $stmt->close();
            return true;
        } else {
            $this->log("Error al ejecutar create: " . $stmt->error, "ERROR");
            $stmt->close();
            return false;
        }
    }

    /**
     * Actualiza un producto existente.
     */
    public function update($id, $data) {
        $this->log("Iniciando actualización de producto ID $id: " . json_encode($data));
        
        $sql = "UPDATE arts SET descripcion = ?, clave_sat = ?, servicios = ?, unidad_sat = ?, unidad = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        if (!$stmt) {
            $this->log("Error en prepare update: " . $this->db->error, "ERROR");
            return false;
        }

        $id = intval($id);
        $stmt->bind_param("sisssi", 
            $data['descripcion'], 
            $data['clave_sat'], 
            $data['servicios'], 
            $data['unidad_sat'], 
            $data['unidad'],
            $id
        );

        if ($stmt->execute()) {
            $this->log("Producto actualizado exitosamente. Filas afectadas: " . $stmt->affected_rows);
            $stmt->close();
            return true;
        } else {
            $this->log("Error al ejecutar update: " . $stmt->error, "ERROR");
            $stmt->close();
            return false;
        }
    }

    /**
     * Elimina un producto por su ID (Eliminación lógica / Soft delete).
     */
    public function delete($id) {
        $this->log("Iniciando eliminación (soft-delete) de producto ID $id");
        
        // Intentamos con la columna 'is_active'
        $sql = "UPDATE arts SET is_active = 0 WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        if (!$stmt) {
            $this->log("Error en prepare delete con is_active: " . $this->db->error, "WARNING");
            
            // Si falla, intentamos con 'estado'
            $sql = "UPDATE arts SET estado = 0 WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            
            if (!$stmt) {
                $this->log("Error en prepare delete con estado: " . $this->db->error, "ERROR");
                return false;
            }
        }

        $id = intval($id);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $this->log("Producto eliminado (soft-delete) exitosamente. Filas afectadas: " . $stmt->affected_rows);
            $stmt->close();
            return true;
        } else {
            $this->log("Error al ejecutar delete (soft): " . $stmt->error, "ERROR");
            $stmt->close();
            return false;
        }
    }

    /**
     * Registro de logs.
     */
    private function log($message, $level = 'INFO') {
        $date = date('Y-m-d H:i:s');
        $formatted = "[" . $date . "] [" . $level . "] " . $message . PHP_EOL;
        @file_put_contents($this->logPath, $formatted, FILE_APPEND);
    }
}
