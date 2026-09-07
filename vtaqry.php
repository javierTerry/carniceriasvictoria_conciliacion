<?php
ini_set('memory_limit', '512M');
$title = "Ver Ventas Globales | Victoria Obrador y Carnicería";
include "head.php";
include "sidebar.php";
?>
<link rel="stylesheet" href="assets/css/vtaqry.css?v=<?php echo time(); ?>">
<div class="right_col" role="main"><!-- page content -->
    <div class="">
        <div class="page-title"> <!-- recuadro A bajo de cabecera gris -->
            <div class="clearfix"></div> <!-- recuadro B bajo de recuadro A gris -->
            <div class="col-md-12 col-sm-12 col-xs-12">
                <?php
                //    include("modal/new_cust.php");  // + boton azul Agregar registro  y rutina GREGAR registros
                //    include("modal/upd_cust.php");  // rutina editar registro
                //   do_alert("esto es 1");
                ?>
                <?php
                $branch_selected = $_GET['branch'] ?? 'all';
                $status_selected = $_GET['status'] ?? '';
                $is_pending_view = ($status_selected === '2');
                $is_global_branch = ($branch_selected === 'all' || empty($branch_selected));
                $title_view = $is_pending_view ? "Ventas Pendientes de Facturación" : "Ventas";

                $branch_badges = [
                    'Obrador' => 'label-obrador',
                    'Victoria1' => 'label-victoria1',
                    'Victoria2' => 'label-victoria2',
                    'Produccion' => 'label-produccion',
                    'CEP' => 'label-cep'
                ];
                $branch_names = [
                    'Obrador' => 'Obrador',
                    'Victoria1' => 'Victoria 1',
                    'Victoria2' => 'Victoria 2',
                    'Produccion' => 'Producción',
                    'CEP' => 'Cerdo en Pie (CEP)'
                ];
                ?>
                <div class="x_panel">
                    <div class="x_title">
                        <h2>
                            <i class="fa fa-shopping-cart"></i> <?php echo $title_view; ?>
                            <?php if (!$is_global_branch && isset($branch_names[$branch_selected])): ?>
                                - <span class="label <?php echo $branch_badges[$branch_selected] ?? 'label-default'; ?>"><?php echo $branch_names[$branch_selected]; ?></span>
                            <?php elseif ($is_global_branch): ?>
                                <small>(Global - Todas las sucursales)</small>
                            <?php endif; ?>
                        </h2>
                        <div class="clearfix"></div>
                    </div>

                    <!-- Panel de Filtros -->
                    <div class="filter-card">
                        <form class="form-horizontal" role="form" id="datos_cotizacion" onsubmit="event.preventDefault(); load(1);">
                            <input type="hidden" id="status_filter" value="<?php echo htmlspecialchars($status_selected); ?>">
                            
                            <div class="row" style="margin-bottom: 12px;">
                                <!-- Buscar por texto (Ticket / Cliente) -->
                                <div class="<?php echo $is_global_branch ? 'col-md-3' : 'col-md-4'; ?> col-sm-6 col-xs-12" style="margin-bottom: 10px;">
                                    <label for="q" class="filter-label"><i class="fa fa-search"></i> Buscar</label>
                                    <input type="text" class="form-control input-victoria" id="q" placeholder="Ticket # o cliente...">
                                </div>

                                <!-- Filtro de Fecha -->
                                <div class="col-md-2 col-sm-6 col-xs-12" style="margin-bottom: 10px;">
                                    <label for="fecha_filter" class="filter-label"><i class="fa fa-calendar"></i> Fecha</label>
                                    <input type="date" class="form-control input-victoria" id="fecha_filter">
                                </div>

                                <!-- Filtro de Monto -->
                                <div class="col-md-2 col-sm-6 col-xs-12" style="margin-bottom: 10px;">
                                    <label for="monto_filter" class="filter-label"><i class="fa fa-dollar"></i> Monto</label>
                                    <input type="number" step="0.01" min="0" class="form-control input-victoria" id="monto_filter" placeholder="0.00">
                                </div>

                                <?php if ($is_global_branch): ?>
                                    <!-- Filtro de Sucursal: Solo visible en la vista global de Todas las Sucursales -->
                                    <div class="col-md-2 col-sm-6 col-xs-12" style="margin-bottom: 10px;">
                                        <label for="branch_filter" class="filter-label"><i class="fa fa-map-marker"></i> Sucursal</label>
                                        <select class="form-control input-victoria select2-victoria" id="branch_filter" data-default="all">
                                            <option value="all" selected>Todas las sucursales</option>
                                            <option value="Obrador">Obrador</option>
                                            <option value="Victoria1">Victoria 1 (Matriz)</option>
                                            <option value="Victoria2">Victoria 2 (Sucursal)</option>
                                            <option value="Produccion">Producción</option>
                                        </select>
                                    </div>
                                <?php else: ?>
                                    <!-- En menús dedicados de cada sucursal, la sucursal es fija e invisible para evitar cruces de datos -->
                                    <input type="hidden" id="branch_filter" value="<?php echo htmlspecialchars($branch_selected); ?>">
                                <?php endif; ?>

                                <!-- Filtro de Forma de Pago -->
                                <div class="<?php echo $is_global_branch ? 'col-md-3' : 'col-md-4'; ?> col-sm-6 col-xs-12" style="margin-bottom: 10px;">
                                    <label for="fpay_filter" class="filter-label"><i class="fa fa-credit-card"></i> Forma de Pago</label>
                                    <select class="form-control input-victoria select2-victoria" id="fpay_filter">
                                        <option value="">Todas las formas</option>
                                        <?php
                                        $sql_fpay = "SELECT id, name FROM fpago WHERE is_active = 1 ORDER BY name";
                                        $res_fpay = mysqli_query($conexion, $sql_fpay);
                                        while ($fpay = mysqli_fetch_array($res_fpay, MYSQLI_ASSOC)) {
                                            echo "<option value='{$fpay['id']}'>" . htmlspecialchars($fpay['name']) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Paginación -->
                                <div class="col-md-2 col-sm-4 col-xs-6" style="margin-bottom: 5px;">
                                    <label for="per_page" class="filter-label"><i class="fa fa-list-ol"></i> Mostrar</label>
                                    <select class="form-control input-victoria select2-victoria" id="per_page">
                                        <option value="10">10 registros</option>
                                        <option value="25" selected>25 registros</option>
                                        <option value="50">50 registros</option>
                                        <option value="75">75 registros</option>
                                        <option value="100">100 registros</option>
                                    </select>
                                </div>

                                <!-- Botón Buscar -->
                                <div class="col-md-2 col-sm-4 col-xs-6" style="margin-bottom: 5px;">
                                    <label class="filter-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary btn-block action-btn-victoria btn-filter-action" id="btn_search">
                                        <i class="fa fa-search"></i> Buscar
                                    </button>
                                </div>

                                <!-- Botón Limpiar -->
                                <div class="col-md-2 col-sm-4 col-xs-12" style="margin-bottom: 5px;">
                                    <label class="filter-label">&nbsp;</label>
                                    <button type="button" class="btn btn-default btn-block btn-filter-action" id="btn_clear" onclick="limpiarFiltros();">
                                        <i class="fa fa-eraser"></i> Limpiar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <!-- Fin Panel de Filtros -->

                    <div class="x_content">
                        <div class="table-responsive">
                            <!-- ajax -->
                            <div id="loader" class="text-center" style="margin: 10px 0;"></div>
                            <div id="resultados"></div><!-- Carga los datos ajax -->
                            <div class='outer_div'></div><!-- Carga los datos ajax -->
                            <!-- /ajax -->
                        </div>
                    </div>
                </div> <!-- recuadro D blanco debajo de recuadro C gris -->
            </div> <!-- recuadro C bajo de recuadro B gris -->
        </div>
    </div>
</div><!-- /page content -->

<!-- Ticket Modal -->
<div class="modal fade" id="ticketModal" tabindex="-1" role="dialog" aria-labelledby="ticketModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="ticketModalLabel"><i class="glyphicon glyphicon-print"></i> Ticket de Venta
                </h4>
            </div>
            <div class="modal-body" style="padding: 0;">
                <iframe id="ticketFrame" src=""></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default action-btn-victoria" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- HTML Ticket Modal -->
<div class="modal fade" id="htmlTicketModal" tabindex="-1" role="dialog" aria-labelledby="htmlTicketModalLabel">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="htmlTicketModalLabel"><i class="glyphicon glyphicon-list-alt"></i> Vista
                    Previa Ticket
                </h4>
            </div>
            <div class="modal-body" id="htmlTicketBody" style="background-color: #f4f4f4; padding: 20px;">
                <!-- Content will be loaded via AJAX -->
                <div class="text-center">
                    <img src="./images/ajax-loader.gif"> Cargando...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default action-btn-victoria" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/vtaqry_logic.js" defer></script>
<?php include "footer.php"; ?>
<script type="text/javascript" src="js/vtaqry.js?v=<?php echo time(); ?>"></script>