<?php
$branch = isset($_GET['branch']) ? $_GET['branch'] : '';
if (!in_array($branch, ['Obrador', 'Victoria1', 'Victoria2', 'Produccion'])) {
    die("<h2 style='text-align:center; margin-top:50px; color:#e74c3c;'>Error: Sucursal no válida.</h2>");
}

$title = "Factura Libre - " . htmlspecialchars($branch) . " | Victoria Obrador y Carnicería";
include "head.php";
include "sidebar.php";

// Obtenemos la serie que corresponde a la sucursal para mostrarla
$branchSeriesMap = [
    'Obrador' => 'O',
    'Victoria1' => 'V',
    'Victoria2' => 'K',
    'Produccion' => 'O'
];
$display_serie = isset($branchSeriesMap[$branch]) ? $branchSeriesMap[$branch] : '';
?>
<meta charset="UTF-8">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container--default .select2-selection--single {
        height: 38px !important;
        padding: 5px !important;
        border: 1px solid #ccc !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
    }
    .badge-serie {
        background-color: #34495e;
        color: #fff;
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: bold;
        margin-left: 5px;
    }
</style>

<div class="right_col" role="main"><!-- page content -->
    <div class="">
        <div class="page-title">
            <div class="clearfix"></div>
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="x_panel">
                    <div class="x_title">
                        <h2>
                            <i class="fa fa-edit"></i> Factura Libre - 
                            <span class="label label-primary"><?php echo htmlspecialchars($branch); ?></span>
                            <span class="badge-serie">Serie: <?php echo htmlspecialchars($display_serie); ?></span>
                        </h2>
                        <ul class="nav navbar-right panel_toolbox">
                             <li><a class="collapse-link"><i class="fa fa-chevron-up"></i></a></li>
                        </ul>
                        <div class="clearfix"></div>
                    </div>
                    
                    <div class="x_content">
                        <!-- Sección de Cliente para Facturación -->
                        <div class="row" style="background: #fdfefe; border: 1px solid #e1e8ed; border-radius: 8px; margin: 0 0 20px 0; padding: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                            <div class="col-md-4 col-sm-12">
                                <label for="client_selector" class="control-label" style="display: block; margin-bottom: 5px; color: #34495e; font-weight: 600;">Cliente para Facturación:</label>
                                <select class="form-control" id="client_selector" style="width: 100%;">
                                    <option value="">-- Buscar Cliente por Nombre o RFC --</option>
                                </select>
                                <div style="margin-top: 15px;">
                                    <label for="observacion" class="control-label" style="display: block; margin-bottom: 5px; color: #34495e; font-weight: 600;">Observación:</label>
                                    <input type="text" class="form-control" id="observacion" placeholder="Observaciones de la factura..." style="border-radius: 4px;">
                                </div>
                            </div>
                            <div class="col-md-8 col-sm-12">
                                <label class="control-label" style="display: block; margin-bottom: 5px; color: #34495e; font-weight: 600;">Datos Fiscales:</label>
                                <div class="row" style="margin-bottom: 10px;">
                                    <div class="col-md-4 col-sm-6">
                                        <span class="label label-default" style="font-size: 12px; padding: 6px 10px; background-color: #f0f2f5; color: #73879C; border: 1px solid #e1e8ed; display: block; margin-bottom: 5px;">Razón Social: <span id="lbl_razon_social">---</span></span>
                                    </div>
                                    <div class="col-md-2 col-sm-6">
                                        <span class="label label-default" style="font-size: 12px; padding: 6px 10px; background-color: #f0f2f5; color: #73879C; border: 1px solid #e1e8ed; display: block; margin-bottom: 5px;">RFC: <span id="lbl_rfc">---</span></span>
                                    </div>
                                    <div class="col-md-2 col-sm-4">
                                        <span class="label label-default" style="font-size: 12px; padding: 6px 10px; background-color: #f0f2f5; color: #73879C; border: 1px solid #e1e8ed; display: block; margin-bottom: 5px;">CP: <span id="lbl_cp">---</span></span>
                                    </div>
                                    <div class="col-md-2 col-sm-4">
                                        <span class="label label-default" style="font-size: 12px; padding: 6px 10px; background-color: #f0f2f5; color: #73879C; border: 1px solid #e1e8ed; display: block; margin-bottom: 5px;">Regimen: <span id="lbl_regimen_fiscal">---</span></span>
                                     </div>
                                    <div class="col-md-2 col-sm-4">
                                        <span class="label label-default" style="font-size: 12px; padding: 6px 10px; background-color: #f0f2f5; color: #73879C; border: 1px solid #e1e8ed; display: block; margin-bottom: 5px;">Email: <span id="lbl_email">---</span></span>
                                     </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <label style="font-size: 11px; color: #73879C;">Método Pago (CFDI):</label>
                                        <select class="form-control input-sm" id="metodo_pago_code">
                                            <option value="">-- Seleccionar --</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label style="font-size: 11px; color: #73879C;">Uso CFDI:</label>
                                        <select class="form-control input-sm" id="uso_cfdi_code">
                                            <option value="">-- Seleccionar --</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label style="font-size: 11px; color: #73879C;">Forma Pago:</label>
                                        <select class="form-control input-sm" id="fpayment_selector">
                                            <option value="">-- Seleccionar --</option>
                                            <?php
                                            $sql_fpay = "SELECT id, name, code FROM fpago WHERE is_active = 1 ORDER BY name";
                                            $res_fpay = mysqli_query($conexion, $sql_fpay);
                                            if($res_fpay) {
                                                while ($fpay = mysqli_fetch_array($res_fpay, MYSQLI_ASSOC)) {
                                                    echo "<option value='{$fpay['id']}' data-name='{$fpay['name']}' data-code='{$fpay['code']}'>{$fpay['name']}</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <!-- Campos ocultos para datos fiscales del cliente -->
                                <input type="hidden" id="client_rfc" value="">
                                <input type="hidden" id="client_razon_social" value="">
                                <input type="hidden" id="client_regimen" value="">
                                <input type="hidden" id="client_es_fisica" value="">
                                <input type="hidden" id="client_nombre" value="">
                                <input type="hidden" id="client_ap_paterno" value="">
                                <input type="hidden" id="client_cp" value="">
                            </div>
                        </div>

                        <!-- Sección de Catálogo de Productos y Precios -->
                        <div class="row" style="background: #fdfefe; border: 1px solid #e1e8ed; border-radius: 8px; margin: 0 0 20px 0; padding: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                            <div class="col-md-4 col-sm-12">
                                <label for="product_selector" class="control-label" style="display: block; margin-bottom: 5px; color: #34495e; font-weight: 600;">Catálogo de Productos:</label>
                                <select class="form-control" id="product_selector" style="width: 100%;">
                                    <option value="">-- Buscar Producto --</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-12">
                                <label class="control-label" style="display: block; margin-bottom: 5px; color: #34495e; font-weight: 600;">Generales del Producto:</label>
                                <div id="product_labels" style="display: flex; flex-direction: column; gap: 5px; justify-content: center; min-height: 38px;">
                                    <span class="label label-default" style="font-size: 12px; padding: 6px 10px; background-color: #f0f2f5; color: #73879C; border: 1px solid #e1e8ed; text-align: left;">Clave SAT: <span id="lbl_clave_sat">---</span></span>
                                    <span class="label label-default" style="font-size: 12px; padding: 6px 10px; background-color: #f0f2f5; color: #73879C; border: 1px solid #e1e8ed; text-align: left;">Unidad SAT: <span id="lbl_unidad_sat">---</span></span>
                                </div>
                                <!-- Campos ocultos para datos del producto -->
                                <input type="hidden" id="prod_clave_sat" value="">
                                <input type="hidden" id="prod_unidad_sat" value="">
                            </div>
                            <div class="col-md-5 col-sm-12">
                                <div class="row">
                                    <div class="col-xs-4">
                                        <label for="prod_qty" class="control-label" style="display: block; margin-bottom: 5px; color: #34495e; font-weight: 600; font-size: 11px;">Cantidad:</label>
                                        <input type="number" class="form-control input-sm" id="prod_qty" placeholder="0.0000" step="0.0001" min="0">
                                    </div>
                                    <div class="col-xs-4">
                                        <label for="prod_price" class="control-label" style="display: block; margin-bottom: 5px; color: #34495e; font-weight: 600; font-size: 11px;">Precio:</label>
                                        <div class="input-group">
                                            <span class="input-group-addon" style="padding: 5px;">$</span>
                                            <input type="number" class="form-control input-sm" id="prod_price" placeholder="0.0000" step="0.0001" min="0">
                                        </div>
                                    </div>
                                    <div class="col-xs-4">
                                        <label for="prod_subtotal" class="control-label" style="display: block; margin-bottom: 5px; color: #34495e; font-weight: 600; font-size: 11px;">Subtotal:</label>
                                        <div class="input-group">
                                            <span class="input-group-addon" style="padding: 5px;">$</span>
                                            <input type="text" class="form-control input-sm" id="prod_subtotal" placeholder="0.00" readonly style="background-color:#eef2f5; font-weight:bold;">
                                        </div>
                                    </div>
                                </div>
                                <div class="row" style="margin-top: 15px;">
                                    <div class="col-md-12 text-right">
                                        <button type="button" id="btn_agregar_concepto" class="btn btn-primary" style="font-weight: bold; border-radius: 4px; padding: 6px 20px;">
                                            <i class="fa fa-plus"></i> Agregar Concepto
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tabla de Conceptos Agregados (Área de Trabajo) -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="x_panel" style="border: 1px solid #e1e8ed; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                                    <div class="x_title" style="border-bottom: 2px solid #34495e; padding-bottom: 10px; margin-bottom: 15px;">
                                        <h3 style="margin: 0; color: #34495e; font-size: 16px; font-weight: bold;">
                                            <i class="fa fa-list"></i> Conceptos Agregados para Facturar
                                        </h3>
                                        <div class="clearfix"></div>
                                    </div>
                                    <div class="x_content" style="padding: 0;">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-bordered" id="tbl_conceptos" style="margin-bottom: 0;">
                                                <thead style="background: #34495e; color: #fff;">
                                                    <tr>
                                                        <th style="width: 15%;">Clave SAT</th>
                                                        <th style="width: 45%;">Descripción</th>
                                                        <th style="text-align: right; width: 10%;">Cantidad</th>
                                                        <th style="text-align: right; width: 12%;">Precio</th>
                                                        <th style="text-align: right; width: 13%;">Subtotal</th>
                                                        <th style="text-align: center; width: 5%;">Acción</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr id="tr_empty_row">
                                                        <td colspan="6" class="text-center" style="color: #999; padding: 25px; font-style: italic;">
                                                            <i class="fa fa-info-circle"></i> No se han agregado productos a la factura.
                                                        </td>
                                                    </tr>
                                                </tbody>
                                                <tfoot>
                                                    <tr style="background: #f9f9f9; font-size: 15px;">
                                                        <td colspan="4" style="text-align: right; font-weight: bold; vertical-align: middle;">TOTAL A FACTURAR:</td>
                                                        <td style="text-align: right; font-weight: 800; color: #26B99A; font-size: 16px; vertical-align: middle;" id="td_grand_total">$0.00</td>
                                                        <td></td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Acción Principal -->
                        <div class="row" style="margin-top: 15px; margin-bottom: 25px;">
                            <div class="col-md-12 text-center">
                                <button type="button" id="btn_facturar_libre" class="btn btn-success" style="font-size: 18px; font-weight: bold; padding: 12px 50px; border-radius: 6px; box-shadow: 0 4px 6px rgba(38,185,154,0.3);" disabled>
                                    <i class="fa fa-file-text-o"></i> FACTURAR
                                </button>
                            </div>
                        </div>

                    </div> <!-- /x_content -->
                </div> <!-- /x_panel -->
            </div>
        </div>
    </div>
</div><!-- /page content -->

<!-- Pasar la sucursal actual a JavaScript de forma segura -->
<script>
    window.CURRENT_BRANCH = <?php echo json_encode($branch); ?>;
    window.CURRENT_SERIE = <?php echo json_encode($display_serie); ?>;
</script>

<script src="assets/js/factura_libre.js?v=<?php echo time(); ?>" defer></script>
<?php include "footer.php" ?>
