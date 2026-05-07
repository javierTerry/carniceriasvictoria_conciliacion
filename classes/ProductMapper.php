<?php
declare(strict_types=1);

/**
 * ProductMapper.php
 * Clase Data Mapper para gestionar el CRUD de la tabla 'arts' (Productos).
 */

class ProductMapper {
    private mysqli $db;
    private array $allowedFields = ['descripcion', 'clave_sat', 'servicios', 'unidad_sat', 'unidad'];
    private string $logPath;

    public function __construct(mysqli $db, string $logPath = "") {
        $this->db = $db;
        $this->logPath = $logPath ?: __DIR__ . '/../logs/prod.log';
        
        $log_dir = dirname($this->logPath);
        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0755, true);
        }
    }

    /**
     * Obtiene una lista paginada de productos.
     */
    public function findAll(string $searchTerm = '', int $limit = 25, int $offset = 0): array {
        $sql = "SELECT * FROM arts";
        $params = [];
        $types = "";

        if (!empty($searchTerm)) {
            $sql .= " WHERE descripcion LIKE ? OR servicios LIKE ? OR clave_sat LIKE ?";
            $likeSearch = "%$searchTerm%";
            $params = [$likeSearch, $likeSearch, $likeSearch];
            $types = "sss";
        }

        $sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            $this->log("Error preparing findAll: " . $this->db->error, "ERROR");
            return [];
        }

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $data;
    }

    /**
     * Cuenta el total de productos (para paginación).
     */
    public function count(string $searchTerm = ''): int {
        $sql = "SELECT COUNT(*) as total FROM arts";
        $params = [];
        $types = "";

        if (!empty($searchTerm)) {
            $sql .= " WHERE descripcion LIKE ? OR servicios LIKE ? OR clave_sat LIKE ?";
            $likeSearch = "%$searchTerm%";
            $params = [$likeSearch, $likeSearch, $likeSearch];
            $types = "sss";
        }

        $stmt = $this->db->prepare($sql);
        if (!$stmt) return 0;

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return (int)($row['total'] ?? 0);
    }

    /**
     * Obtiene un producto por su descripción exacta.
     */
    public function findByDescription(string $description): ?array {
        $stmt = $this->db->prepare("SELECT * FROM arts WHERE descripcion = ? LIMIT 1");
        if (!$stmt) return null;
        
        $stmt->bind_param("s", $description);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();

        return $data;
    }

    /**
     * Obtiene un producto por su ID.
     */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM arts WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();

        return $data;
    }

    /**
     * Crea un nuevo producto.
     */
    public function create(array $data): bool {
        $filtered = $this->filterData($data);
        if (empty($filtered)) return false;

        $fields = array_keys($filtered);
        $placeholders = array_fill(0, count($fields), "?");
        
        $sql = "INSERT INTO arts (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $placeholders) . ")";
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            $this->log("Error preparing create: " . $this->db->error, "ERROR");
            return false;
        }

        $types = $this->getTypes($filtered);
        $values = array_values($filtered);
        
        $stmt->bind_param($types, ...$values);
        $success = $stmt->execute();
        
        if (!$success) {
            $this->log("Error executing create: " . $stmt->error, "ERROR");
        }
        
        $stmt->close();
        return $success;
    }

    /**
     * Actualiza un producto existente.
     */
    public function update(int $id, array $data): bool {
        $filtered = $this->filterData($data);
        if (empty($filtered)) return false;

        $sets = [];
        foreach (array_keys($filtered) as $field) {
            $sets[] = "$field = ?";
        }

        $sql = "UPDATE arts SET " . implode(", ", $sets) . " WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            $this->log("Error preparing update: " . $this->db->error, "ERROR");
            return false;
        }

        $types = $this->getTypes($filtered) . "i";
        $values = array_values($filtered);
        $values[] = $id;

        $stmt->bind_param($types, ...$values);
        $success = $stmt->execute();

        if (!$success) {
            $this->log("Error executing update: " . $stmt->error, "ERROR");
        }

        $stmt->close();
        return $success;
    }

    /**
     * Elimina un producto.
     */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM arts WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Filtra los campos permitidos (evita Mass Assignment).
     */
    private function filterData(array $data): array {
        $filtered = [];
        foreach ($this->allowedFields as $field) {
            if (isset($data[$field])) {
                $filtered[$field] = $data[$field];
            }
        }
        return $filtered;
    }

    /**
     * Determina los tipos para bind_param.
     */
    private function getTypes(array $data): string {
        $types = "";
        foreach ($data as $value) {
            if (is_int($value)) $types .= "i";
            elseif (is_double($value)) $types .= "d";
            else $types .= "s";
        }
        return $types;
    }

    /**
     * Registro de logs.
     */
    private function log(string $message, string $level = 'INFO'): void {
        $date = date('Y-m-d H:i:s');
        $formatted = "[$date] [$level] $message" . PHP_EOL;
        @file_put_contents($this->logPath, $formatted, FILE_APPEND);
    }
}
