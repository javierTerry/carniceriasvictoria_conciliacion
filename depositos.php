<?php
/**
 * depositos.php
 * Vista del listado y gestión de depósitos por sucursal
 */
declare(strict_types=1);

$branch = isset($_GET['branch']) ? $_GET['branch'] : '';
if (!in_array($branch, ['Obrador', 'Victoria1', 'Victoria2', 'Produccion'])) {
    die("<h2 style='text-align:center; margin-top:50px; color:#e74c3c;'>Error: Sucursal no válida.</h2>");
}

$title = "Depósitos - " . htmlspecialchars($branch) . " | Victoria Obrador y Carnicería";
include "head.php";
include "sidebar.php";
?>
<meta charset="UTF-8">
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container--default .select2-selection--single {
        height: 38px !important;
        padding: 5px 10px !important;
        border: 1px solid #ccc !important;
        border-radius: 4px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
    }
    .select2-container {
        width: 100% !important;
    }
    .has-error .select2-selection {
        border-color: #a94442 !important;
        background-color: #f2dede !important;
    }
    .card-stat {
        background: #fff;
        border: 1px solid #e1e8ed;
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        margin-bottom: 20px;
    }
    .badge-branch {
        background-color: #34495e;
        color: #fff;
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: bold;
    }
</style>

<div class="right_col" role="main"><!-- page content -->
    <div class="">
        <div class="page-title">
            <div class="clearfix"></div>
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="x_panel" style="border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                    <div class="x_title">
                        <h2>
                            <i class="fa fa-university"></i> Gestión de Depósitos - 
                            <span class="label label-primary"><?php echo htmlspecialchars($branch); ?></span>
                        </h2>
                        <ul class="nav navbar-right panel_toolbox">
                            <li>
                                <button type="button" class="btn btn-info" onclick="openREPModal()" style="font-weight: bold; border-radius: 4px; margin-right: 5px;">
                                    <i class="fa fa-file-text-o"></i> Generar REP
                                </button>
                                <button type="button" class="btn btn-success" onclick="openModalAdd()" style="font-weight: bold; border-radius: 4px;">
                                    <i class="fa fa-plus"></i> Registrar Depósito
                                </button>
                            </li>
                        </ul>
                        <div class="clearfix"></div>
                    </div>
                    
                    <div class="x_content">
                        <!-- Filtros de búsqueda -->
                        <div class="row card-stat" style="background: #fcfcfc;">
                            <form id="search_form" class="form-horizontal" onsubmit="event.preventDefault();">
                                <div class="col-md-5 col-sm-6 col-xs-12">
                                    <label for="q" class="control-label" style="text-align: left; display: block; margin-bottom: 5px; font-weight: 600;">Buscar depósito:</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="q" placeholder="Buscar por Cliente, Banco o Referencia..." onkeyup="load(1);">
                                        <span class="input-group-btn">
                                            <button type="button" class="btn btn-primary" onclick="load(1);"><i class="fa fa-search"></i></button>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-3 col-xs-6">
                                    <label for="per_page" class="control-label" style="text-align: left; display: block; margin-bottom: 5px; font-weight: 600;">Mostrar:</label>
                                    <select class="form-control" id="per_page" onchange="load(1);">
                                        <option value="10">10 registros</option>
                                        <option value="25" selected>25 registros</option>
                                        <option value="50">50 registros</option>
                                        <option value="100">100 registros</option>
                                    </select>
                                </div>
                            </form>
                        </div>

                        <!-- Cargador AJAX -->
                        <div id="loader" class="text-center" style="display:none; padding: 30px;">
                            <i class="fa fa-spinner fa-spin fa-3x fa-fw" style="color: #26B99A;"></i>
                            <span class="sr-only">Cargando...</span>
                            <p style="margin-top: 10px; font-weight: bold; color: #666;">Cargando depósitos...</p>
                        </div>

                        <!-- Resultados del Listado -->
                        <div class="outer_div"></div>
                        
                    </div> <!-- /x_content -->
                </div> <!-- /x_panel -->
            </div>
        </div>
    </div>
</div><!-- /page content -->

<!-- Modal Registrar Depósito -->
<div class="modal fade" id="depositoModal" tabindex="-1" role="dialog" aria-labelledby="depositoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content" style="border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            <form id="deposito_form" class="form-horizontal">
                <input type="hidden" name="branch" value="<?php echo htmlspecialchars($branch); ?>">
                
                <div class="modal-header" style="background: #34495e; color: #fff; border-top-left-radius: 8px; border-top-right-radius: 8px; padding: 15px 20px;">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.8;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="depositoModalLabel" style="font-weight: bold; margin: 0;">
                        <i class="fa fa-university"></i> Registrar Nuevo Depósito
                    </h4>
                </div>
                
                <div class="modal-body" style="padding: 20px 25px;">
                    <!-- Cliente -->
                    <div class="form-group">
                        <label for="cust_id" class="control-label" style="text-align: left; display: block; font-weight: bold; margin-bottom: 5px;">Cliente*</label>
                        <select class="form-control" id="cust_id" name="cust_id" required style="width: 100%;">
                            <option value="">-- Escribe 3 o más caracteres para buscar cliente --</option>
                        </select>
                    </div>

                    <!-- Factura PPD Asociada -->
                    <div class="form-group" id="group_factura" style="display: none;">
                        <label for="factura_id" class="control-label" style="text-align: left; display: block; font-weight: bold; margin-bottom: 5px;">
                            Asociar a Factura PPD <span class="label label-info">Opcional</span>
                        </label>
                        <select class="form-control" id="factura_id" name="factura_id" style="width: 100%;">
                            <option value="">-- No asociar a factura --</option>
                        </select>
                        <span class="help-block" style="font-size: 11px; margin-top: 5px;">
                            Selecciona una factura de la lista si este depósito corresponde al pago de un documento PPD.
                        </span>
                    </div>

                    <!-- Banco -->
                    <div class="form-group">
                        <label for="banco_id" class="control-label" style="text-align: left; display: block; font-weight: bold; margin-bottom: 5px;">Banco Receptor*</label>
                        <select class="form-control" id="banco_id" name="banco_id" required style="width: 100%;">
                            <option value="">-- Escribe 3 o más caracteres para buscar banco --</option>
                        </select>
                    </div>

                    <!-- Monto -->
                    <div class="form-group">
                        <label for="monto" class="control-label" style="text-align: left; display: block; font-weight: bold; margin-bottom: 5px;">Monto del Depósito ($)*</label>
                        <div class="input-group">
                            <span class="input-group-addon" style="font-weight: bold; background: #eef2f5;">$</span>
                            <input type="number" class="form-control" id="monto" name="monto" placeholder="0.00" step="0.01" min="0.01" required style="font-weight: bold; font-size: 16px; color: #26B99A;">
                        </div>
                    </div>

                    <!-- Referencia -->
                    <div class="form-group">
                        <label for="referencia" class="control-label" style="text-align: left; display: block; font-weight: bold; margin-bottom: 5px;">Referencia / Clave Transferencia*</label>
                        <input type="text" class="form-control" id="referencia" name="referencia" placeholder="Ej. TRANS-849202, Ref 12345" required style="border-radius: 4px;">
                    </div>
                </div>
                
                <div class="modal-footer" style="background: #f9f9f9; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; padding: 15px 20px;">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius: 4px;">Cancelar</button>
                    <button type="submit" class="btn btn-success" style="font-weight: bold; border-radius: 4px; padding: 6px 20px;">
                        <i class="fa fa-save"></i> Registrar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Agregar Abono -->
<div class="modal fade" id="abonoModal" tabindex="-1" role="dialog" aria-labelledby="abonoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content" style="border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            <form id="abono_form" class="form-horizontal">
                <input type="hidden" name="parent_id" id="abono_parent_id">
                
                <div class="modal-header" style="background: #17a2b8; color: #fff; border-top-left-radius: 8px; border-top-right-radius: 8px; padding: 15px 20px;">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.8;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="abonoModalLabel" style="font-weight: bold; margin: 0;">
                        <i class="fa fa-plus"></i> Registrar Abono (Sub-depósito)
                    </h4>
                </div>
                
                <div class="modal-body" style="padding: 20px 25px;">
                    <div class="alert alert-info" style="border-radius: 4px;">
                        <strong>Cliente:</strong> <span id="abono_parent_desc"></span><br>
                        <strong>Saldo Disponible Actual:</strong> <span id="abono_parent_saldo" style="font-weight: bold;"></span>
                    </div>

                    <!-- Monto -->
                    <div class="form-group">
                        <label for="abono_monto" class="control-label" style="text-align: left; display: block; font-weight: bold; margin-bottom: 5px;">Monto del Abono ($)*</label>
                        <div class="input-group">
                            <span class="input-group-addon" style="font-weight: bold; background: #eef2f5;">$</span>
                            <input type="number" class="form-control" id="abono_monto" name="monto" placeholder="0.00" step="0.01" min="0.01" required style="font-weight: bold; font-size: 16px; color: #17a2b8;">
                        </div>
                    </div>

                    <!-- Referencia -->
                    <div class="form-group">
                        <label for="abono_referencia" class="control-label" style="text-align: left; display: block; font-weight: bold; margin-bottom: 5px;">Referencia / Clave Transferencia*</label>
                        <input type="text" class="form-control" id="abono_referencia" name="referencia" placeholder="Ej. ABONO-849202" required style="border-radius: 4px;">
                    </div>
                </div>
                
                <div class="modal-footer" style="background: #f9f9f9; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; padding: 15px 20px;">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius: 4px;">Cancelar</button>
                    <button type="submit" class="btn btn-info" style="font-weight: bold; border-radius: 4px; padding: 6px 20px;">
                        <i class="fa fa-save"></i> Registrar Abono
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Generar REP (Complemento de Pago) -->
<div class="modal fade" id="repModal" tabindex="-1" role="dialog" aria-labelledby="repModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            <form id="rep_form" class="form-horizontal">
                <input type="hidden" name="branch" value="<?php echo htmlspecialchars($branch); ?>">
                
                <div class="modal-header" style="background: #34495e; color: #fff; border-top-left-radius: 8px; border-top-right-radius: 8px; padding: 15px 20px;">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.8;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="repModalLabel" style="font-weight: bold; margin: 0;">
                        <i class="fa fa-file-text-o"></i> Generar Complemento de Pago (REP)
                    </h4>
                </div>
                
                <div class="modal-body" style="padding: 20px 25px;">
                    <!-- Paso 1: Cliente -->
                    <div class="form-group">
                        <label for="rep_cust_id" class="control-label" style="text-align: left; display: block; font-weight: bold; margin-bottom: 5px;">1. Selecciona Cliente*</label>
                        <select class="form-control" id="rep_cust_id" name="cust_id" required style="width: 100%;">
                            <option value="">-- Escribe para buscar cliente --</option>
                        </select>
                    </div>

                    <!-- Paso 2: Depósito de origen -->
                    <div class="form-group" id="group_rep_deposit" style="display: none;">
                        <label for="rep_deposit_id" class="control-label" style="text-align: left; display: block; font-weight: bold; margin-bottom: 5px;">2. Selecciona Depósito con Saldo Disponible*</label>
                        <select class="form-control" id="rep_deposit_id" name="deposit_id" required style="width: 100%;">
                            <option value="">-- Selecciona depósito --</option>
                        </select>
                        <div style="margin-top:5px;" id="rep_deposit_balance_info"></div>
                    </div>

                    <!-- Datos adicionales del pago -->
                    <div class="row" id="rep_payment_details" style="display: none; margin-bottom: 15px;">
                        <div class="col-md-6 col-sm-6 col-xs-12">
                            <label for="rep_forma_pago" class="control-label" style="text-align: left; display: block; font-weight: bold; margin-bottom: 5px;">Forma de Pago*</label>
                            <select class="form-control" id="rep_forma_pago" name="forma_pago" required>
                                <option value="03" selected>03 - Transferencia electrónica de fondos</option>
                                <option value="01">01 - Efectivo</option>
                                <option value="02">02 - Cheque nominativo</option>
                                <option value="04">04 - Tarjeta de crédito</option>
                                <option value="28">28 - Tarjeta de débito</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-sm-6 col-xs-12">
                            <label for="rep_fecha_pago" class="control-label" style="text-align: left; display: block; font-weight: bold; margin-bottom: 5px;">Fecha/Hora de Pago (SAT)*</label>
                            <input type="datetime-local" class="form-control" id="rep_fecha_pago" name="fecha_pago" required>
                        </div>
                    </div>

                    <!-- Paso 3: Facturas PPD pendientes -->
                    <div class="form-group" id="group_rep_invoices" style="display: none;">
                        <label class="control-label" style="text-align: left; display: block; font-weight: bold; margin-bottom: 10px;">3. Selecciona Facturas PPD y el Importe a Pagar</label>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="rep_invoices_table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px; text-align: center;">Aplicar</th>
                                        <th>Factura</th>
                                        <th>Fecha</th>
                                        <th class="text-right">Monto Total</th>
                                        <th class="text-right">Saldo Pendiente</th>
                                        <th class="text-right" style="width: 150px;">Importe a Pagar ($)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dynamic -->
                                </tbody>
                            </table>
                        </div>
                        <div class="text-right" style="font-size: 16px; font-weight: bold; margin-top: 10px;">
                            Total a Aplicar: <span id="rep_total_aplicar" style="color: #26B99A;">$0.00</span> / <span id="rep_max_disponible" style="color: #34495e;">$0.00</span>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer" style="background: #f9f9f9; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; padding: 15px 20px;">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius: 4px;">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btn_submit_rep" style="font-weight: bold; border-radius: 4px; padding: 6px 20px;" disabled>
                        <i class="fa fa-send"></i> Generar y Timbrar REP
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Pasar variables PHP a Javascript de forma limpia y segura -->
<script>
    window.CURRENT_BRANCH = <?php echo json_encode($branch); ?>;
</script>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js" defer></script>
<!-- SweetAlert2 JS (incluido en footer, pero por si acaso cargado global) -->
<script src="assets/js/depositos.js?v=<?php echo time(); ?>" defer></script>

<?php include "footer.php"; ?>
