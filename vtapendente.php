<?php
ini_set('memory_limit', '512M');
$title = "Gestión de Acción Ticket | Victoria Obrador y Carnicería";
include "head.php";
include "sidebar.php";

$id = $_GET['id'] ?? 0;
$branch = $_GET['branch'] ?? '';
?>
<link rel="stylesheet" href="assets/css/vtaqry.css?v=<?php echo time(); ?>">
<style>
    .action-panel {
        background: #f8f9fa;
        border: 1px dashed #ccc;
        border-radius: 8px;
        min-height: 400px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        color: #777;
        padding: 20px;
    }
    .ticket-header-info {
        margin-bottom: 20px;
        padding: 15px;
        background: #f0f2f5;
        border-radius: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    #ticket_preview {
        background: white;
        padding: 10px;
        border: 1px solid #eee;
        border-radius: 4px;
        min-height: 400px;
    }
    .btn-back {
        margin-bottom: 15px;
    }
</style>

<div class="right_col" role="main"><!-- page content -->
    <div class="">
        <div class="page-title">
            <div class="clearfix"></div>
            <div class="col-md-12 col-sm-12 col-xs-12">
                
                <a href="vtaqry.php?branch=<?php echo $branch; ?>&status=2" class="btn btn-default btn-sm btn-back">
                    <i class="fa fa-arrow-left"></i> Regresar a Pendientes
                </a>

                <div class="x_panel">
                    <div class="x_title">
                        <h2>Acción de Ticket - <span class="label label-primary"><?php echo $branch; ?></span></h2>
                        <ul class="nav navbar-right panel_toolbox">
                             <li><a class="collapse-link"><i class="fa fa-chevron-up"></i></a></li>
                        </ul>
                        <div class="clearfix"></div>
                    </div>
                    
                    <div class="x_content">
                        <!-- Info del Ticket -->
                        <div class="ticket-header-info">
                            <div>
                                <strong>Gestionando Ticket ID:</strong> #<?php echo htmlspecialchars($id); ?>
                            </div>
                            <div class="text-right">
                                <span class="label label-warning" style="font-size: 14px;">ESTATUS: PENDIENTE</span>
                            </div>
                        </div>

                        <!-- Grid de 2 Columnas responsivas -->
                        <div class="row">
                            <!-- Columna Izquierda: Vista Previa Ticket (1/3) -->
                            <div class="col-md-4 col-sm-12">
                                <div class="x_panel">
                                    <div class="x_title">
                                        <h2><i class="fa fa-file-text-o"></i> Vista Previa</h2>
                                        <div class="clearfix"></div>
                                    </div>
                                    <div id="ticket_preview">
                                        <div class="text-center" style="margin-top: 100px;">
                                            <i class="fa fa-refresh fa-spin fa-3x" style="color: #eee;"></i>
                                            <p style="color: #999; margin-top: 10px;">Cargando ticket...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Columna Derecha: Acciones / Leyenda Trabando (2/3) -->
                            <div class="col-md-8 col-sm-12">
                                <div class="x_panel">
                                    <div class="x_title">
                                        <h2><i class="fa fa-cogs"></i> Panel de Gestión</h2>
                                        <div class="clearfix"></div>
                                    </div>
                                    <div class="action-panel">
                                        <div class="text-center">
                                            <i class="fa fa-refresh fa-spin fa-4x" style="color: #34495e; margin-bottom: 20px;"></i>
                                            <h3 style="color: #2c3e50; font-weight: bold;">Trabajando...</h3>
                                            <p style="font-size: 16px; margin-top: 10px;">Seguimos validando este ticket.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div> <!-- /row -->
                    </div> <!-- /x_content -->
                </div> <!-- /x_panel -->
            </div>
        </div>
    </div>
</div><!-- /page content -->

<script src="assets/js/vtapendente.js?v=<?php echo time(); ?>" defer></script>
<?php include "footer.php" ?>
