<?php
ini_set('memory_limit', '512M');
$title = "Gestión de Acción Ticket | Victoria Obrador y Carnicería";
include "head.php";
include "sidebar.php";

$mov_id = $_GET['mov_id'] ?? $_GET['id'] ?? '';
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
        justify-content: flex-start;
        flex-direction: column;
        color: #777;
        padding: 15px;
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
                                <strong>Gestionando Ticket ID:</strong> #<?php echo htmlspecialchars($mov_id); ?>
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
                                    <div class="action-panel" id="management_panel">
                                        <div style="width: 100%; margin-bottom: 15px;">
                                            <div class="form-group row" style="margin: 0; display: flex; align-items: center; flex-wrap: wrap;">
                                                <div class="col-sm-4 col-xs-12">
                                                    <label for="fpayment_selector" class="control-label" style="margin-bottom: 5px; display: block;">Forma Pago:</label>
                                                    <select class="form-control select-victoria" id="fpayment_selector">
                                                        <option value="">-- Seleccionar --</option>
                                                        <?php
                                                        $sql_fpay = "SELECT id, name, code FROM fpago WHERE is_active = 1 ORDER BY name";
                                                        $res_fpay = mysqli_query($conexion, $sql_fpay);
                                                        while ($fpay = mysqli_fetch_array($res_fpay, MYSQLI_ASSOC)) {
                                                            echo "<option value='{$fpay['id']}' data-name='{$fpay['name']}' data-code='{$fpay['code']}'>{$fpay['name']}</option>";
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                                <div class="col-sm-4 col-xs-12">
                                                    <label for="manual_amount" class="control-label" style="margin-bottom: 5px; display: block;">Monto a Seccionar:</label>
                                                    <div class="input-group" style="margin-bottom: 0;">
                                                        <span class="input-group-addon">$</span>
                                                        <input type="number" class="form-control" id="manual_amount" placeholder="0.00" step="0.01" min="0">
                                                    </div>
                                                </div>
                                                <div class="col-sm-4 col-xs-12" style="padding-top: 25px;">
                                                    <button type="button" class="btn btn-primary btn-block" id="btn_add_manual_amount" style="margin-bottom: 0;">
                                                        <i class="fa fa-plus"></i> Agregar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Summary Totals Area -->
                                        <div id="totals_summary" style="width: 100%; background: #fff; padding: 10px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #eee; display: flex; justify-content: space-around; align-items: center;">
                                            <div class="text-center">
                                                <small style="display: block; color: #73879C; font-weight: 600; text-transform: uppercase;">Total Ticket</small>
                                                <strong id="ticket_total_val" style="font-size: 1.4em; color: #34495e;">$0.00</strong>
                                            </div>
                                            <div style="width: 1px; height: 30px; background: #eee;"></div>
                                            <div class="text-center">
                                                <small style="display: block; color: #73879C; font-weight: 600; text-transform: uppercase;">Acumulado</small>
                                                <strong id="accumulated_total_val" style="font-size: 1.4em; color: #26B99A;">$0.00</strong>
                                            </div>
                                            <div style="width: 1px; height: 30px; background: #eee;"></div>
                                            <div class="text-center">
                                                <small style="display: block; color: #73879C; font-weight: 600; text-transform: uppercase;">Pendiente</small>
                                                <strong id="pending_total_val" style="font-size: 1.4em; color: #e74c3c;">$0.00</strong>
                                            </div>
                                        </div>
                                        
                                        <div id="payment_method_tables_container" style="width: 100%;">
                                            <div id="payment_method_tables">
                                                <!-- Dynamic tables per payment method will be injected here -->
                                            </div>
                                            <div class="text-center" id="empty_management_msg">
                                                <i class="fa fa-info-circle fa-3x" style="color: #eee;"></i>
                                                <p style="color: #999; margin-top: 10px;">Seleccione un método de pago y agregue monto.</p>
                                            </div>
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
