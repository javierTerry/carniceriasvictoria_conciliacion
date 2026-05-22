<?php
$title = "Creación Cerdo en Pie (CEP) | Victoria Obrador y Carnicería";
include "head.php";
include "sidebar.php";

// Establecemos la sucursal predeterminada para este módulo, en caso de que sea necesario.
// Puede ser ajustado según la lógica de negocio final.
$branch = "CEP";
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
</style>

<div class="right_col" role="main"><!-- page content -->
    <div class="">
        <div class="page-title">
            <div class="clearfix"></div>
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="x_panel">
                    <div class="x_title">
                        <h2><i class="fa fa-paw"></i> Creación de Ticket - <span class="label label-primary">CEP</span></h2>
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
                                    <div class="col-md-6">
                                        <span class="label label-default" style="font-size: 12px; padding: 6px 10px; background-color: #f0f2f5; color: #73879C; border: 1px solid #e1e8ed; display: block; margin-bottom: 5px;">Razón Social: <span id="lbl_razon_social">---</span></span>
                                    </div>
                                    <div class="col-md-3">
                                        <span class="label label-default" style="font-size: 12px; padding: 6px 10px; background-color: #f0f2f5; color: #73879C; border: 1px solid #e1e8ed; display: block; margin-bottom: 5px;">CP: <span id="lbl_cp">---</span></span>
                                    </div>
                                    <div class="col-md-3">
                                        <span class="label label-default" style="font-size: 12px; padding: 6px 10px; background-color: #f0f2f5; color: #73879C; border: 1px solid #e1e8ed; display: block; margin-bottom: 5px;">Regimen Fiscal: <span id="lbl_regimen_fiscal">---</span></span>
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
                            <div class="col-md-4 col-sm-12">
                                <label class="control-label" style="display: block; margin-bottom: 5px; color: #34495e; font-weight: 600;">Generales del Producto:</label>
                                <div id="product_labels" style="display: flex; flex-direction: column; gap: 5px; justify-content: center; min-height: 38px;">
                                    <span class="label label-default" style="font-size: 12px; padding: 6px 10px; background-color: #f0f2f5; color: #73879C; border: 1px solid #e1e8ed; text-align: left;">Clave SAT: <span id="lbl_clave_sat">---</span></span>
                                    <span class="label label-default" style="font-size: 12px; padding: 6px 10px; background-color: #f0f2f5; color: #73879C; border: 1px solid #e1e8ed; text-align: left;">Unidad SAT: <span id="lbl_unidad_sat">---</span></span>
                                </div>
                                <!-- Campos ocultos para datos del producto -->
                                <input type="hidden" id="prod_clave_sat" value="">
                                <input type="hidden" id="prod_unidad_sat" value="">
                            </div>
                            <div class="col-md-4 col-sm-12">
                                <div class="row">
                                    <div class="col-xs-4">
                                        <label for="num_facturas" class="control-label" style="display: block; margin-bottom: 5px; color: #34495e; font-weight: 600; font-size: 11px;">Cant. Facturas:</label>
                                        <input type="number" class="form-control input-sm" id="num_facturas" placeholder="0" step="1" min="0">
                                    </div>
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
                                </div>
                            </div>
                        </div>

                        <!-- Acción Principal -->
                        <div class="row" style="margin-bottom: 25px;">
                            <div class="col-md-12 text-center">
                                <button type="button" id="btn_facturar_cep" class="btn btn-success" style="font-size: 18px; font-weight: bold; padding: 12px 50px; border-radius: 6px; box-shadow: 0 4px 6px rgba(38,185,154,0.3);">
                                    <i class="fa fa-file-text-o"></i> FACTURAR
                                </button>
                            </div>
                        </div>

                        <!-- Espacio reservado para el área de trabajo del ticket de CEP -->
                        <div class="row">
                            <div class="col-md-12 text-center" style="padding: 50px; background: #f9f9f9; border: 1px dashed #ccc; border-radius: 8px;">
                                <h3 style="color: #999;"><i class="fa fa-wrench"></i> Área de trabajo de Cerdo en Pie</h3>
                                
                                <!-- Aquí se puede implementar la tabla para añadir conceptos, similar a la facturación -->
                            </div>
                        </div>

                    </div> <!-- /x_content -->
                </div> <!-- /x_panel -->
            </div>
        </div>
    </div>
</div><!-- /page content -->

<script src="assets/js/cep_creacion.js?v=<?php echo time(); ?>" defer></script>
<?php include "footer.php" ?>
