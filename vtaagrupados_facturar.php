<?php
ini_set('memory_limit', '512M');
$title = "Facturación de Grupo | Victoria Obrador y Carnicería";
include "head.php";
include "sidebar.php";

$group_id = $_GET['group_id'] ?? '';
$branch = $_GET['branch'] ?? '';

if (empty($group_id) || empty($branch)) {
    echo "<script>window.location.href='vtaagrupados.php?branch=".$branch."';</script>";
    exit;
}
?>
<link rel="stylesheet" href="assets/css/vtaqry.css?v=<?php echo time(); ?>">
<style>
    .action-panel {
        background: #f8f9fa;
        border: 1px dashed #ccc;
        border-radius: 8px;
        min-height: 500px;
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
        min-height: 500px;
    }
    .btn-back {
        margin-bottom: 15px;
    }
</style>

<div class="right_col" role="main">
    <div class="">
        <div class="page-title">
            <div class="clearfix"></div>
            <div class="col-md-12 col-sm-12 col-xs-12">
                
                <a href="vtaagrupados.php?branch=<?php echo $branch; ?>" class="btn btn-default btn-sm btn-back">
                    <i class="fa fa-arrow-left"></i> Regresar a Agrupados
                </a>

                <div class="x_panel">
                    <div class="x_title">
                        <h2>Facturación Global de Grupo - <span class="label label-primary"><?php echo $branch; ?></span></h2>
                        <div class="clearfix"></div>
                    </div>
                    
                    <div class="x_content">
                        <!-- Info del Grupo -->
                        <div class="ticket-header-info">
                            <div>
                                <strong>Gestionando Grupo ID:</strong> #<?php echo htmlspecialchars($group_id); ?>
                            </div>
                            <div class="text-right">
                                <span class="label label-warning" style="font-size: 14px;">ESTATUS: PENDIENTE DE FACTURA</span>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Columna Izquierda: Vista Previa Consolidada (col-md-4) -->
                            <div class="col-md-4 col-sm-12">
                                <div class="x_panel">
                                    <div class="x_title">
                                        <h2><i class="fa fa-file-text-o"></i> Vista Previa (Consolidada)</h2>
                                        <div class="clearfix"></div>
                                    </div>
                                    <div id="ticket_preview">
                                        <div class="text-center" style="margin-top: 100px;">
                                            <i class="fa fa-refresh fa-spin fa-3x" style="color: #eee;"></i>
                                            <p style="color: #999; margin-top: 10px;">Consolidando tickets...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Columna Derecha: Panel de Gestión (col-md-8) -->
                            <div class="col-md-8 col-sm-12">
                                <div class="x_panel">
                                    <div class="x_title">
                                        <h2><i class="fa fa-cogs"></i> Panel de Gestión de Facturación</h2>
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
                                                    <label for="manual_amount" class="control-label" style="margin-bottom: 5px; display: block;">Monto a Facturar:</label>
                                                    <div class="input-group" style="margin-bottom: 0;">
                                                        <span class="input-group-addon">$</span>
                                                        <input type="number" class="form-control" id="manual_amount" placeholder="0.00" step="0.01" min="0">
                                                    </div>
                                                </div>
                                                <div class="col-sm-4 col-xs-12" style="padding-top: 25px;">
                                                    <button type="button" class="btn btn-primary btn-block" id="btn_add_manual_amount" style="margin-bottom: 0;">
                                                        <i class="fa fa-plus"></i> Agregar Proporción
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Resumen de Totales del Grupo -->
                                        <div id="totals_summary" style="width: 100%; background: #fff; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #eee; display: flex; justify-content: space-around; align-items: center; box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);">
                                            <div class="text-center">
                                                <small style="display: block; color: #73879C; font-weight: 600; text-transform: uppercase;">Total Consolidado</small>
                                                <strong id="ticket_total_val" style="font-size: 1.8em; color: #34495e;">$0.00</strong>
                                            </div>
                                            <div style="width: 1px; height: 40px; background: #eee;"></div>
                                            <div class="text-center">
                                                <small style="display: block; color: #73879C; font-weight: 600; text-transform: uppercase;">Asignado</small>
                                                <strong id="accumulated_total_val" style="font-size: 1.8em; color: #26B99A;">$0.00</strong>
                                            </div>
                                            <div style="width: 1px; height: 40px; background: #eee;"></div>
                                            <div class="text-center">
                                                <small style="display: block; color: #73879C; font-weight: 600; text-transform: uppercase;">Por Asignar</small>
                                                <strong id="pending_total_val" style="font-size: 1.8em; color: #e74c3c;">$0.00</strong>
                                            </div>
                                        </div>
                                        
                                        <div id="payment_method_tables_container" style="width: 100%;">
                                            <div id="payment_method_tables">
                                                <!-- Las tablas por método de pago se inyectarán aquí -->
                                            </div>
                                            <div class="text-center" id="empty_management_msg">
                                                <i class="fa fa-info-circle fa-4x" style="color: #eee;"></i>
                                                <p style="color: #999; margin-top: 15px; font-size: 1.1em;">Defina las proporciones de facturación para el grupo.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/vtaagrupados_facturar.js?v=<?php echo time(); ?>" defer></script>
<?php include "footer.php" ?>
