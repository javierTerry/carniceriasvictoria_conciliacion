<?php
ini_set('memory_limit', '512M');
$title = "Ver Ventas Globales | Victoria Obrador y Carnicería";
include "head.php";
include "sidebar.php";
?>
<link rel="stylesheet" href="assets/css/vtaqry.css">
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
                <div class="x_panel">
                    <div class="x_title">
                        <h2>Ventas (Global)</h2> <!-- titulo de los registros -->
                        <div class="clearfix"></div> <!-- ajusta imagen al cuadro -->
                    </div> <!-- titulo Proveedor -->


                    <!-- form search -->
                    <form class="form-horizontal" role="form" id="datos_cotizacion">
                        <div class="form-group row">
                            <label for="q" class="col-md-1 control-label">Venta</label>
                            <div class="col-md-2">
                                <input type="text" class="form-control" id="q" placeholder="Buscar..."
                                    onkeyup='load(1);'>
                            </div>

                            <input type="hidden" id="branch_filter" value="<?php echo $_GET['branch'] ?? ''; ?>">

                            <label for="fpay_filter" class="col-md-1 control-label">Pago</label>
                            <div class="col-md-2">
                                <select class="form-control select-victoria" id="fpay_filter" onchange="load(1);">
                                    <option value="">Todos</option>
                                    <?php
                                    $sql_fpay = "SELECT id, name FROM fpago WHERE is_active = 1 ORDER BY name";
                                    $res_fpay = mysqli_query($conexion, $sql_fpay);
                                    while ($fpay = mysqli_fetch_array($res_fpay, MYSQLI_ASSOC)) {
                                        echo "<option value='{$fpay['id']}'>{$fpay['name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <label for="per_page" class="col-md-1 control-label">Ver</label>
                            <div class="col-md-2">
                                <select class="form-control select-victoria" id="per_page" onchange="load(1);">
                                    <option value="25" selected>25</option>
                                    <option value="50">50</option>
                                    <option value="75">75</option>
                                    <option value="100">100</option>
                                </select>
                            </div>

                        </div>
                    </form>
                    <!-- end form search -->

                    <div class="x_content">
                        <div class="table-responsive">
                            <!-- ajax -->
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
<script type="text/javascript" src="js/vtaqry.js?v=<?php echo time(); ?>"></script>
<?php include "footer.php" ?>