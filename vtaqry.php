<?php
ini_set('memory_limit', '512M');
$title = "Ver Ventas Globales | Victoria Obrador y Carnicería";
include "head.php";
include "sidebar.php";
?>
<style>
    :root {
        --victoria-red: #B22222;
        --victoria-gold: #D4AF37;
        --victoria-black: #1A1A1A;
        --victoria-white: #FFFFFF;
    }

    /* Premium Header */
    .x_title {
        background-color: var(--victoria-black) !important;
        color: var(--victoria-white) !important;
        border-bottom: 2px solid var(--victoria-gold) !important;
        padding: 10px 15px !important;
        border-radius: 4px 4px 0 0;
    }

    .x_title h2 {
        color: var(--victoria-white) !important;
        font-weight: 700 !important;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* Action Buttons Victoria Style */
    .action-btn-victoria {
        background-color: var(--victoria-red) !important;
        color: var(--victoria-white) !important;
        border: 1px solid var(--victoria-gold) !important;
        transition: all 0.3s ease;
    }

    .action-btn-victoria:hover {
        background-color: var(--victoria-gold) !important;
        color: var(--victoria-black) !important;
        transform: scale(1.05);
    }

    /* Status Highlighting */
    .paid-highlight {
        border-left: 4px solid var(--victoria-gold) !important;
    }

    .badge-success {
        background-color: #28a745 !important;
        border: 1px solid var(--victoria-gold);
    }

    /* Centered table cells */
    .table td,
    .table th {
        text-align: center !important;
        vertical-align: middle !important;
        font-size: 14px !important;
    }

    /* Branch Specific Colors (Bubbles) */
    .label-obrador {
        background-color: var(--victoria-gold) !important;
        color: var(--victoria-black) !important;
        font-weight: 700;
        font-size: 14px !important;
        padding: 4px 8px !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .label-vicoria1 {
        background-color: var(--victoria-red) !important;
        color: var(--victoria-white) !important;
        font-weight: 700;
        font-size: 14px !important;
        padding: 4px 8px !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .label-vicoria2 {
        background-color: #4B0082 !important;
        /* Indigo */
        color: var(--victoria-white) !important;
        font-weight: 700;
        font-size: 14px !important;
        padding: 4px 8px !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    /* Select Victoria Style */
    .select-victoria {
        border: 1px solid var(--victoria-gold) !important;
        color: var(--victoria-black) !important;
        font-weight: 600 !important;
    }

    .select-victoria:focus {
        border-color: var(--victoria-red) !important;
        box-shadow: 0 0 5px rgba(178, 34, 34, 0.5) !important;
    }

    .form-horizontal .control-label {
        color: var(--victoria-black);
        font-weight: 700;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .form-group.row {
            margin-bottom: 15px;
        }

        .form-horizontal .control-label {
            text-align: left !important;
            margin-bottom: 5px;
        }
    }

    /* Modal Ticket Style */
    #ticketModal .modal-content {
        border: 2px solid var(--victoria-gold);
        border-radius: 8px;
        overflow: hidden;
    }

    #ticketModal .modal-header {
        background-color: var(--victoria-black);
        color: var(--victoria-white);
        border-bottom: 2px solid var(--victoria-gold);
    }

    #ticketModal .modal-title {
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    #ticketModal .close {
        color: var(--victoria-white);
        opacity: 0.8;
    }

    #ticketModal .close:hover {
        opacity: 1;
    }

    #ticketFrame {
        width: 100%;
        height: 500px;
        border: none;
    }
</style>
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

                            <label for="branch_filter" class="col-md-1 control-label">Sucursal</label>
                            <div class="col-md-2">
                                <select class="form-control select-victoria" id="branch_filter" onchange="load(1);">
                                    <option value="">Todas</option>
                                    <option value="Obrador">Obrador</option>
                                    <option value="Vicoria1">Victoria 1</option>
                                    <option value="Vicoria2">Victoria 2</option>
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

                            <div class="col-md-2">
                                <button type="button" class="btn btn-default action-btn-victoria btn-block"
                                    onclick='load(1);'>
                                    <span class="glyphicon glyphicon-search"></span> Buscar</button>
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

<script type="text/javascript">
    function printTicket(id) {
        $("#ticketFrame").attr("src", "action/vtaticket.php?xyz=" + id);
        $("#ticketModal").modal("show");
    }

    function viewTicketHTML(id) {
        $("#htmlTicketBody").html('<div class="text-center"><img src="./images/ajax-loader.gif"> Cargando...</div>');
        $("#htmlTicketModal").modal("show");
        $.ajax({
            url: "ajax/vta_html_ticket.php",
            type: "GET",
            data: { id: id },
            success: function (response) {
                $("#htmlTicketBody").html(response);
            },
            error: function () {
                $("#htmlTicketBody").html('<div class="alert alert-danger">Error al cargar el ticket.</div>');
            }
        });
    }
</script>
<script type="text/javascript" src="js/vtaqry.js?v=<?php echo time(); ?>"></script>
<?php include "footer.php" ?>