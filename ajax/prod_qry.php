<?php
/**
 * ajax/prod_qry.php
 * Endpoint para listar productos con paginación y búsqueda.
 */
include "../config/config.php";
include "../classes/ProductMapper.php";

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
if ($action == 'ajax') {
    // Sanitización básica para búsqueda
    $q = isset($_REQUEST['q']) ? mysqli_real_escape_string($conexion_gen, strip_tags($_REQUEST['q'])) : '';
    
    $page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? (int)$_REQUEST['page'] : 1;
    $per_page = (isset($_REQUEST['per_page']) && !empty($_REQUEST['per_page'])) ? (int)$_REQUEST['per_page'] : 25;
    $adjacents = 4;
    $offset = ($page - 1) * $per_page;

    $mapper = new ProductMapper($conexion_gen);
    $products = $mapper->findAll($q, $per_page, $offset);
    $numrows = $mapper->count($q);
    $total_pages = ceil($numrows / $per_page);

    include "../ajax/pagination.php";

    if ($numrows > 0) {
        ?>
        <div class="table-responsive">
            <table class="table table-striped jambo_table bulk_action">
                <thead>
                    <tr class="headings">
                        <th class="column-title">Descripci&oacute;n</th>
                        <th class="column-title">Clave SAT</th>
                        <th class="column-title">Servicios</th>
                        <th class="column-title">Unidad SAT</th>
                        <th class="column-title">Unidad</th>
                        <th class="column-title no-link last"><span class="nobr">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($products as $product) {
                        $id = $product['id'];
                        $descripcion = $product['descripcion'];
                        $clave_sat = $product['clave_sat'];
                        $servicios = $product['servicios'];
                        $unidad_sat = $product['unidad_sat'];
                        $unidad = $product['unidad'];
                        ?>
                        <tr class="even pointer">
                            <td><?php echo htmlspecialchars((string)$descripcion); ?></td>
                            <td><?php echo htmlspecialchars((string)$clave_sat); ?></td>
                            <td><?php echo htmlspecialchars((string)$servicios); ?></td>
                            <td><?php echo htmlspecialchars((string)$unidad_sat); ?></td>
                            <td><?php echo htmlspecialchars((string)$unidad); ?></td>
                            <td class="last">
                                <button type="button" class='btn btn-default btn-xs' title='Editar' onclick="editProduct('<?php echo $id; ?>');"><i class="fa fa-pencil"></i></button>
                                <button type="button" class='btn btn-danger btn-xs' title='Borrar' onclick="deleteProduct('<?php echo $id; ?>');"><i class="fa fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
            <div class="row">
                <div class="col-md-12 text-center">
                    <?php echo paginate('', $page, $total_pages, $adjacents); ?>
                </div>
            </div>
        </div>
        <?php
    } else {
        ?>
        <div class="alert alert-warning alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <strong>Aviso!</strong> No hay datos para mostrar
        </div>
        <?php
    }
}
