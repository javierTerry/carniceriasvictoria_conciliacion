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
     * Registro de logs.
     */
    private function log($message, $level = 'INFO') {
        $date = date('Y-m-d H:i:s');
        $formatted = "[" . $date . "] [" . $level . "] " . $message . PHP_EOL;
        @file_put_contents($this->logPath, $formatted, FILE_APPEND);
    }
}
