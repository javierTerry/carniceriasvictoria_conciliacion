<?php
/**
 * ajax/cust.php
 * Controlador AJAX para el catálogo de clientes
 * Corregido: Código autogenerado en el INSERT
 */
declare(strict_types=1);

include "../config/config.php";

$action = $_REQUEST['action'] ?? '';
$workflow = "[CLIENTES_CRUD]";

/**
 * Función para sanitizar y validar datos de entrada (Data Mapper)
 */
function mapCustomerData(array $input): array {
    $allowedFields = [
        'razon_social', 'calle', 'noext', 'noint', 'colonia', 
        'municipio', 'estado', 'cp', 'email', 'phone', 
        'nombre', 'ap_paterno', 'ap_materno', 
        'rfc', 'regimen_fiscal', 'metodo_pago_code', 'uso_cfdi_code'
    ];
    
    $mapped = [];
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $mapped[$field] = $input[$field];
        } else {
            $mapped[$field] = '';
        }
    }
    
    // Lógica de RFC para es_persona_fisica
    $rfc = strtoupper(trim($mapped['rfc']));
    $mapped['rfc'] = $rfc;
    $mapped['es_persona_fisica'] = (strlen($rfc) === 13) ? 1 : 0;
    
    // Si es moral, aseguramos que los campos de nombre estén vacíos
    if ($mapped['es_persona_fisica'] === 0) {
        $mapped['nombre'] = '';
        $mapped['ap_paterno'] = '';
        $mapped['ap_materno'] = '';
    }
    
    // Formatear CP a 5 dígitos con ceros a la izquierda
    $mapped['cp'] = str_pad(substr(preg_replace('/[^0-9]/', '', (string)($mapped['cp'] ?? "00000")), 0, 5), 5, "0", STR_PAD_LEFT);

    return $mapped;
}

/**
 * Validaciones de Backend
 */
function validateCustomerData(array $data): array {
    $errors = [];
    $rfc = $data['rfc'] ?? '';
    
    if (empty($rfc)) {
        $errors[] = "El RFC es obligatorio.";
    } else if (strlen($rfc) !== 12 && strlen($rfc) !== 13) {
        $errors[] = "El RFC debe tener exactamente 12 o 13 caracteres.";
    }

    if (empty($data['razon_social'])) {
        $errors[] = "La Razón Social es obligatoria.";
    }

    if (empty($data['regimen_fiscal'])) {
        $errors[] = "El Régimen Fiscal es obligatorio.";
    }

    if (empty($data['metodo_pago_code'])) {
        $errors[] = "El Método de Pago (CFDI) es obligatorio.";
    }

    if (empty($data['uso_cfdi_code'])) {
        $errors[] = "El Uso de CFDI es obligatorio.";
    }

    if (strlen($rfc) === 13) {
        if (empty($data['nombre'])) $errors[] = "El Nombre es obligatorio para personas físicas.";
        if (empty($data['ap_paterno'])) $errors[] = "El Apellido Paterno es obligatorio para personas físicas.";
    }
    
    if (empty($data['email'])) {
        $errors[] = "El Email es obligatorio.";
    } else if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "El formato del Email es inválido.";
    }

    if (empty($data['cp'])) {
        $errors[] = "El Código Postal (CP) es obligatorio.";
    }
    
    return $errors;
}

/**
 * Genera el siguiente código de cliente
 */
function generateNextCode($conexion): string {
    $res = mysqli_query($conexion, "SELECT MAX(id) as last_id FROM cust");
    $row = mysqli_fetch_assoc($res);
    $next_id = intval($row['last_id'] ?? 0) + 1;
    return "C" . str_pad((string)$next_id, 5, "0", STR_PAD_LEFT);
}

// --- ACCIONES ---

if ($action === 'select2') {
    header('Content-Type: application/json');
    $q = $_REQUEST['q'] ?? '';
    
    $sWhere = " WHERE is_active = 1 ";
    if (!empty($q)) {
        $sWhere .= " AND (nombre LIKE ? OR rfc LIKE ? OR razon_social LIKE ?) ";
    }
    
    $stmt = $conexion_gen->prepare("SELECT * FROM cust $sWhere ORDER BY razon_social ASC LIMIT 20");
    if (!empty($q)) {
        $like_q = "%$q%";
        $stmt->bind_param("sss", $like_q, $like_q, $like_q);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => $row['id'],
            'text' => $row['rfc'] . " - " . $row['razon_social'],
            'client_data' => $row 
        ];
    }
    
    echo json_encode(['results' => $data]);
    exit;
}

if ($action === 'ajax') {
    // Listado con filtros
    $q = $_REQUEST['q'] ?? '';
    $page = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;
    $per_page = isset($_REQUEST['per_page']) ? intval($_REQUEST['per_page']) : 25;
    $offset = ($page - 1) * $per_page;

    $sWhere = " WHERE is_active = 1 ";
    if (!empty($q)) {
        $sWhere .= " AND (nombre LIKE ? OR rfc LIKE ? OR code LIKE ? OR razon_social LIKE ?) ";
    }

    $stmt_count = $conexion_gen->prepare("SELECT count(*) AS numrows FROM cust $sWhere");
    if (!empty($q)) {
        $like_q = "%$q%";
        $stmt_count->bind_param("ssss", $like_q, $like_q, $like_q, $like_q);
    }
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $row_count = $result_count->fetch_assoc();
    $numrows = $row_count['numrows'];
    $total_pages = ceil($numrows / $per_page);

    $stmt = $conexion_gen->prepare("SELECT * FROM cust $sWhere ORDER BY id DESC LIMIT ?, ?");
    if (!empty($q)) {
        $stmt->bind_param("ssssii", $like_q, $like_q, $like_q, $like_q, $offset, $per_page);
    } else {
        $stmt->bind_param("ii", $offset, $per_page);
    }
    $stmt->execute();
    $query = $stmt->get_result();

    if ($numrows > 0) {
        ?>
        <table class="table table-striped jambo_table bulk_action">
            <thead>
                <tr class="headings">
                    <th class="column-title">Código </th>
                    <th class="column-title">Razón Social </th>
                    <th class="column-title">RFC </th>
                    <th class="column-title">Tipo </th>
                    <th class="column-title">Email </th>
                    <th class="column-title no-link last text-right"><span class="nobr">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                <?php
                while ($row = $query->fetch_assoc()) {
                    $id = $row['id'];
                    $tipo = ($row['es_persona_fisica'] == 1) ? 'Física' : 'Moral';
                    ?>
                    <tr class="even pointer">
                        <td><?php echo $row['code']; ?></td>
                        <td><?php echo $row['razon_social']; ?></td>
                        <td><?php echo $row['rfc']; ?></td>
                        <td><span class="label <?php echo ($row['es_persona_fisica'] == 1) ? 'label-info' : 'label-primary'; ?>"><?php echo $tipo; ?></span></td>
                        <td><?php echo $row['email']; ?></td>
                        <td class="text-right">
                            <button type="button" class="btn btn-info btn-xs" title="Editar" onclick='editCust(<?php echo json_encode($row); ?>)'>
                                <i class="fa fa-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-danger btn-xs" title="Eliminar" onclick="deleteCust(<?php echo $id; ?>)">
                                <i class="fa fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        
        <div class="row">
            <div class="col-sm-6">
                Mostrando <?php echo $offset + 1; ?> a <?php echo min($offset + $per_page, $numrows); ?> de <?php echo $numrows; ?> registros
            </div>
            <div class="col-sm-6 text-right">
                <ul class="pagination pagination-split" style="margin:0;">
                    <?php if ($page > 1) { ?>
                        <li><a href="javascript:void(0);" onclick="load(<?php echo $page - 1; ?>)">&laquo;</a></li>
                    <?php } ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
                        <?php if ($i > $page - 3 && $i < $page + 3) { ?>
                            <li class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a href="javascript:void(0);" onclick="load(<?php echo $i; ?>)"><?php echo $i; ?></a>
                            </li>
                        <?php } ?>
                    <?php } ?>
                    <?php if ($page < $total_pages) { ?>
                        <li><a href="javascript:void(0);" onclick="load(<?php echo $page + 1; ?>)">&raquo;</a></li>
                    <?php } ?>
                </ul>
            </div>
        </div>
        <?php
    } else {
        echo '<div class="alert alert-warning">No se encontraron clientes.</div>';
    }
}

if ($action === 'save') {
    header('Content-Type: application/json');
    $id = intval($_POST['id'] ?? 0);
    
    $data = mapCustomerData($_POST);
    $errors = validateCustomerData($data);
    
    if (!empty($errors)) {
        sys_log("$workflow Fallo en validación backend: " . implode(", ", $errors), "WARNING");
        echo json_encode(['status' => 'error', 'message' => implode("<br>", $errors)]);
        exit;
    }

    try {
        if ($id > 0) {
            $sql = "UPDATE cust SET 
                    razon_social=?, calle=?, noext=?, noint=?, colonia=?, municipio=?, 
                    estado=?, cp=?, email=?, phone=?, 
                    nombre=?, ap_paterno=?, ap_materno=?, rfc=?, regimen_fiscal=?, 
                    metodo_pago_code=?, uso_cfdi_code=?, es_persona_fisica=? 
                    WHERE id=?";
            $stmt = $conexion_gen->prepare($sql);
            $stmt->bind_param("ssssssssssssssssssi", 
                $data['razon_social'], $data['calle'], $data['noext'], $data['noint'], 
                $data['colonia'], $data['municipio'], $data['estado'], $data['cp'], $data['email'], 
                $data['phone'], $data['nombre'], $data['ap_paterno'], $data['ap_materno'], 
                $data['rfc'], $data['regimen_fiscal'], 
                $data['metodo_pago_code'], $data['uso_cfdi_code'], 
                $data['es_persona_fisica'], $id
            );
            $msg = "Cliente actualizado correctamente.";
        } else {
            // Autogenerar código para nuevos registros
            $new_code = generateNextCode($conexion_gen);
            
            $sql = "INSERT INTO cust (
                    code, razon_social, calle, noext, noint, colonia, municipio, 
                    estado, cp, email, phone, 
                    nombre, ap_paterno, ap_materno, rfc, regimen_fiscal, 
                    metodo_pago_code, uso_cfdi_code, es_persona_fisica,
                    balance, is_new, is_active, lastin_at, lastout_at
                    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 0.00, 1, 1, CURDATE(), CURDATE())";
            $stmt = $conexion_gen->prepare($sql);
            $stmt->bind_param("ssssssssssssssssssi", 
                $new_code, $data['razon_social'], $data['calle'], $data['noext'], $data['noint'], 
                $data['colonia'], $data['municipio'], $data['estado'], $data['cp'], $data['email'], 
                $data['phone'], $data['nombre'], $data['ap_paterno'], $data['ap_materno'], 
                $data['rfc'], $data['regimen_fiscal'], 
                $data['metodo_pago_code'], $data['uso_cfdi_code'], 
                $data['es_persona_fisica']
            );
            $msg = "Cliente registrado con éxito (Código: $new_code).";
        }

        if ($stmt->execute()) {
            sys_log("$workflow $msg (RFC: {$data['rfc']})", "INFO");
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception($stmt->error);
        }
    } catch (Exception $e) {
        $errorMsg = "Error interno: " . $e->getMessage();
        $errorField = "";
        
        //die($e->getMessage());
        sys_log($e->getMessage());
        
        // Humanizar errores de duplicidad (MySQL Error 1062)
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            if (strpos($e->getMessage(), 'rfc') !== false) {
                $errorMsg = "Atención: El <b>RFC</b> ingresado ya está asignado a otro cliente. Por favor, verifícalo.";
                $errorField = "rfc";
            } else if (strpos($e->getMessage(), 'razon_social') !== false) {
                $errorMsg = "Atención: La <b>Razón Social</b> ya se encuentra registrada para otro cliente.";
                $errorField = "razon_social";
            } else if (strpos($e->getMessage(), 'code') !== false) {
                $errorMsg = "Atención: El Código de cliente generado ya existe. Por favor, intenta guardar nuevamente.";
                $errorField = "code";
            } else {
                $errorMsg = "Atención: Ya existe un registro con datos duplicados.";
            }
        }

        sys_log("$workflow Error en DB CRUD: " . $e->getMessage(), "ERROR");
        echo json_encode([
            'status' => 'error', 
            'message' => $errorMsg,
            'field' => $errorField
        ]);
    }
}

if ($action === 'delete') {
    header('Content-Type: application/json');
    $id = intval($_POST['id'] ?? 0);
    
    $stmt = $conexion_gen->prepare("UPDATE cust SET is_active = 0 WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        sys_log("$workflow Cliente desactivado (ID: $id)", "INFO");
        echo json_encode(['status' => 'success', 'message' => "Cliente desactivado correctamente."]);
    } else {
        echo json_encode(['status' => 'error', 'message' => "Error al eliminar."]);
    }
}
