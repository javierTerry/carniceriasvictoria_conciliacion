<?php
ini_set('memory_limit', '512M');
$title = "Facturas PPD (Por Definir) | Victoria Obrador y Carnicería";
include "head.php";
include "sidebar.php";

$branch_param = isset($_GET['branch']) ? htmlspecialchars($_GET['branch']) : '';
?>
<link rel="stylesheet" href="assets/css/vtaqry.css?v=<?php echo time(); ?>">
<style>
    /* Estilos dedicados para selección de facturas PPD */
    .bar-seleccion-ppd {
        display: none;
        background: linear-gradient(135deg, #2A3F54, #1a2736);
        color: #ffffff;
        padding: 12px 20px;
        border-radius: 6px;
        margin-bottom: 15px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        border-left: 5px solid #26B99A;
        transition: all 0.3s ease;
    }
    .check_ppd_item {
        width: 17px;
        height: 17px;
        cursor: pointer;
    }
    #check_all_ppd {
        width: 17px;
        height: 17px;
        cursor: pointer;
    }
    .modal-resumen-table th {
        background-color: #f4f6f9;
        color: #333;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .modal-resumen-table td {
        vertical-align: middle !important;
        font-size: 13px;
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
                            <i class="fa fa-file-text-o"></i> Facturas PPD (Por Definir)
                            <?php if (!empty($branch_param)): ?>
                                <small style="font-size: 15px; font-weight: bold; color: #26B99A;">— <?php echo $branch_param; ?></small>
                            <?php endif; ?>
                        </h2>
                        <div class="clearfix"></div>
                        <p class="text-muted" style="margin: 5px 0 0 0; font-size: 13px;">
                            <i class="fa fa-info-circle"></i> Listado de facturas timbradas con método de pago <strong>Por Definir (PPD / Clave 99)</strong>. Puede seleccionar una o varias facturas del mismo cliente para generar su Complemento de Pago (REP 2.0).
                        </p>
                    </div>

                    <!-- form search -->
                    <form class="form-horizontal" role="form" id="datos_cotizacion" onsubmit="event.preventDefault();">
                        <div class="form-group row">
                            <label for="q" class="col-md-1 control-label">Buscar / UUID</label>
                            <div class="col-md-3">
                                <input type="text" class="form-control" id="q" placeholder="Folio, UUID o Mov ID..."
                                    onkeyup='load(1);' oninput='load(1);'>
                            </div>

                            <input type="hidden" id="branch_filter" value="<?php echo isset($_GET['branch']) ? $_GET['branch'] : ''; ?>">

                            <label for="client_filter" class="col-md-1 control-label">Cliente</label>
                            <div class="col-md-3">
                                <input type="text" class="form-control" id="client_filter" placeholder="Nombre del cliente..."
                                    onkeyup='load(1);' oninput='load(1);'>
                            </div>

                            <label for="per_page" class="col-md-1 control-label">Ver</label>
                            <div class="col-md-2">
                                <select class="form-control select-victoria" id="per_page" onchange="load(1);">
                                    <option value="10">10</option>    
                                    <option value="25" selected>25</option>
                                    <option value="50">50</option>
                                    <option value="75">75</option>
                                    <option value="100">100</option>
                                </select>
                            </div>
                        </div>
                    </form>
                    <!-- end form search -->

                    <!-- Barra de Resumen y Acción de Selección Múltiple -->
                    <div id="barSeleccionPPD" class="bar-seleccion-ppd">
                        <div class="row" style="display: flex; align-items: center; flex-wrap: wrap;">
                            <div class="col-md-6 col-sm-6 col-xs-12" style="padding-top: 4px; padding-bottom: 4px;">
                                <span style="font-size: 15px; font-weight: bold;">
                                    <i class="fa fa-check-square-o" style="color: #26B99A;"></i> 
                                    <span id="txtSeleccionadasCount">0</span> Factura(s) seleccionada(s)
                                </span>
                                <div id="lblClienteSeleccionado" style="font-size: 12.5px; color: #cedece; margin-top: 2px;">
                                    <i class="fa fa-user"></i> Cliente: <span id="txtClienteNombre">---</span>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-6 col-xs-12 text-right" style="padding-top: 4px; padding-bottom: 4px;">
                                <span style="font-size: 14px; margin-right: 15px; vertical-align: middle;">
                                    Total Saldo: <strong id="txtTotalSaldoSeleccionadas" style="color: #26B99A; font-size: 16px;">$0.00</strong>
                                </span>
                                <button type="button" class="btn btn-default btn-sm" onclick="limpiarSeleccionPPD();" style="margin-right: 5px; color: #333;">
                                    <i class="fa fa-eraser"></i> Limpiar
                                </button>
                                <button type="button" class="btn btn-success btn-sm" id="btnPagarSeleccionadas" onclick="abrirModalPagoSeleccionadas();" style="background-color: #26B99A; border-color: #26B99A; font-weight: bold; padding: 6px 14px;">
                                    <i class="fa fa-money"></i> Pagar Facturas Seleccionadas (<span id="btnPagarCount">0</span>)
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="x_content">
                        <div class="table-responsive">
                            <!-- ajax -->
                            <div id="resultados"></div><!-- Carga los datos ajax -->
                            <div class='outer_div'></div><!-- Carga los datos ajax -->
                            <!-- /ajax -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div><!-- /page content -->

<!-- Modal Registro y Timbrado de Pago (REP 2.0 / CFDI 4.0) -->
<div class="modal fade" id="modalPagoPPD" tabindex="-1" role="dialog" aria-labelledby="modalPagoPPDLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document" style="max-width: 900px; width: 92%;">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #2A3F54; color: #fff;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.8;">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="modalPagoPPDLabel">
                    <i class="fa fa-money"></i> Registrar y Timbrar Pago (REP 2.0 / CFDI 4.0)
                </h4>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <form id="formPagoPPD" onsubmit="event.preventDefault(); procesarPagoPPD();">
                    
                    <!-- Ficha del Cliente Receptor -->
                    <div class="panel panel-default" style="border-left: 4px solid #26B99A; margin-bottom: 15px; background-color: #f9fbfb;">
                        <div class="panel-body" style="padding: 12px 15px;">
                            <div class="row">
                                <div class="col-md-7 col-sm-6 col-xs-12">
                                    <p style="margin: 0 0 4px 0; font-size: 14px;">
                                        <strong><i class="fa fa-user"></i> Cliente Receptor:</strong> 
                                        <span id="pago_lbl_cliente" style="font-weight: bold; color: #2A3F54;">---</span>
                                    </p>
                                    <p style="margin: 0; font-size: 12px; color: #555;">
                                        <strong>RFC:</strong> <span id="pago_lbl_rfc">---</span> | 
                                        <strong>Régimen:</strong> <span id="pago_lbl_regimen">---</span> | 
                                        <strong>C.P.:</strong> <span id="pago_lbl_cp">---</span>
                                    </p>
                                </div>
                                <div class="col-md-5 col-sm-6 col-xs-12 text-right">
                                    <p style="margin: 0 0 4px 0; font-size: 13px;">
                                        <strong>Serie / Folio REP:</strong> 
                                        <span id="pago_lbl_serie_folio_rep" class="badge" style="background-color: #34495e; font-size: 13px;">Consultando...</span>
                                    </p>
                                    <p style="margin: 0; font-size: 13px;">
                                        <strong>Facturas a Pagar:</strong> 
                                        <span id="pago_lbl_total_facturas_count" class="badge" style="background-color: #26B99A; font-size: 13px;">1</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Resumen Detallado de Facturas Seleccionadas -->
                    <div class="panel panel-default" style="margin-bottom: 15px;">
                        <div class="panel-heading" style="background-color: #f4f6f9; font-weight: bold; color: #2A3F54; padding: 8px 15px;">
                            <i class="fa fa-list"></i> Desglose de Facturas Seleccionadas
                        </div>
                        <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                            <table class="table table-bordered table-striped modal-resumen-table" style="margin-bottom: 0;">
                                <thead>
                                    <tr>
                                        <th>Factura</th>
                                        <th>UUID / Ticket</th>
                                        <th class="text-right">Monto Orig.</th>
                                        <th class="text-right">Saldo Pendiente</th>
                                        <th class="text-center" style="width: 150px;">Monto a Pagar ($)</th>
                                        <th class="text-center" style="width: 70px;">Parc.</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyFacturasPPDModal">
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">Cargando datos de facturas...</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr style="background-color: #eef2f5; font-weight: bold;">
                                        <td colspan="2" class="text-right">TOTALES:</td>
                                        <td class="text-right" id="modal_tot_monto_orig">$0.00</td>
                                        <td class="text-right" id="modal_tot_saldo_pend" style="color: #b22222;">$0.00</td>
                                        <td class="text-right" id="modal_tot_monto_pagar" style="color: #26B99A; font-size: 15px;">$0.00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Campos Generales del Pago -->
                    <div class="row">
                        <div class="col-md-6 col-sm-6 col-xs-12 form-group">
                            <label for="pago_forma" class="control-label">
                                <i class="fa fa-credit-card"></i> Forma de Pago <span class="text-danger">*</span>
                            </label>
                            <select class="form-control select-victoria" id="pago_forma" required>
                                <option value="03" selected>03 - Transferencia electrónica de fondos</option>
                                <option value="01">01 - Efectivo</option>
                                <option value="04">04 - Tarjeta de crédito</option>
                                <option value="28">28 - Tarjeta de débito</option>
                                <option value="02">02 - Cheque nominativo</option>
                                <option value="05">05 - Monedero electrónico</option>
                                <option value="06">06 - Dinero electrónico</option>
                                <option value="99">99 - Por definir</option>
                            </select>
                        </div>

                        <div class="col-md-6 col-sm-6 col-xs-12 form-group">
                            <label for="pago_fecha" class="control-label">
                                <i class="fa fa-calendar"></i> Fecha y Hora de Pago <span class="text-danger">*</span>
                            </label>
                            <input type="datetime-local" class="form-control" id="pago_fecha" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 col-sm-6 col-xs-12 form-group">
                            <label for="pago_monto_total_display" class="control-label">
                                <i class="fa fa-dollar"></i> Monto Total del Pago (REP) ($ MXN) <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="pago_monto_total_display" readonly 
                                style="font-size: 18px; font-weight: bold; color: #26B99A; background-color: #f9fbfb;" value="$0.00">
                            <small class="text-muted">Suma total de los montos aplicados a las facturas seleccionadas.</small>
                        </div>

                        <div class="col-md-3 col-sm-3 col-xs-12 form-group">
                            <label for="pago_serie_display" class="control-label">
                                <i class="fa fa-tag"></i> Serie / Folio Pago
                            </label>
                            <input type="text" class="form-control" id="pago_serie_display" readonly style="background-color: #eee; font-weight: bold;" value="Consultando...">
                            <input type="hidden" id="pago_serie" value="">
                        </div>

                        <div class="col-md-3 col-sm-3 col-xs-12 form-group">
                            <label for="pago_num_operacion" class="control-label">
                                <i class="fa fa-hashtag"></i> No. Operación / Ref.
                            </label>
                            <input type="text" class="form-control" id="pago_num_operacion" placeholder="Ej. 123456 (Opcional)">
                        </div>
                    </div>

                    <div class="alert alert-info" style="margin-top: 10px; font-size: 12px; margin-bottom: 0;">
                        <i class="fa fa-info-circle"></i> Al confirmar, se generará el XML CFDI 4.0 con Complemento de Recepción de Pagos (REP 2.0) agrupando todas las facturas seleccionadas bajo un único comprobante fiscal en Sinube.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <i class="fa fa-times"></i> Cancelar
                </button>
                <button type="button" class="btn btn-success" id="btnSubmitPagoPPD" style="background-color: #26B99A; border-color: #26B99A; font-weight: bold;" onclick="procesarPagoPPD();">
                    <i class="fa fa-cloud-upload"></i> Timbrar Pago en SiNube
                </button>
            </div>
        </div>
    </div>
</div>

<?php include "footer.php" ?>

<script>
    // Estado de facturas seleccionadas
    var selectedInvoices = {};
    var selectedCustomerId = null;
    var selectedCustomerName = null;
    var currentModalInvoices = [];

    function load(page) {
        window.current_page = page;
        var q = $("#q").val();
        var per_page = $("#per_page").val();
        var branch = $("#branch_filter").val();
        var client = $("#client_filter").val();
        var parametros = {
            "action": "ajax",
            "page": page,
            "q": q,
            "per_page": per_page,
            "branch": branch,
            "client": client,
            "metodo_pago": "ppd",
            "from_page": "./facturas_ppd.php"
        };
        $("#resultados").fadeIn('slow');
        $.ajax({
            url: 'ajax/facturas_ajax.php',
            data: parametros,
            beforeSend: function (objeto) {
                $("#resultados").html('<img src="./images/ajax-loader.gif"> Cargando facturas PPD...');
            },
            success: function (data) {
                $(".outer_div").html(data).fadeIn('slow');
                $("#resultados").html("");
                syncCheckboxesAfterLoad();
            },
            error: function (xhr, status, error) {
                $("#resultados").html('<div class="alert alert-danger">Error al cargar datos: ' + xhr.status + ' ' + error + '</div>');
            }
        });
    }

    // Sincronizar checkboxes visibles con el estado acumulado
    function syncCheckboxesAfterLoad() {
        var visibleCount = 0;
        var checkedVisibleCount = 0;

        $(".check_ppd_item").each(function () {
            visibleCount++;
            var id = $(this).val();
            if (selectedInvoices[id]) {
                $(this).prop('checked', true);
                checkedVisibleCount++;
            } else {
                $(this).prop('checked', false);
            }
        });

        if (visibleCount > 0 && visibleCount === checkedVisibleCount) {
            $("#check_all_ppd").prop('checked', true);
        } else {
            $("#check_all_ppd").prop('checked', false);
        }

        actualizarBarraSeleccionPPD();
    }

    // Manejo de cambio en checkbox individual con validación estricta de mismo cliente
    function onPpdCheckboxChange(checkbox) {
        var $cb = $(checkbox);
        var id = $cb.val();
        var custId = $cb.data('cust-id');
        var cliente = $cb.data('cliente') || 'Cliente General';
        var isChecked = $cb.is(':checked');

        if (isChecked) {
            // Validar restricción de cliente
            if (selectedCustomerId !== null && selectedCustomerId !== custId) {
                $cb.prop('checked', false);
                Swal.fire({
                    title: 'Restricción de Cliente',
                    html: '<p>Solo se pueden seleccionar facturas pertenecientes al <strong>mismo cliente</strong>.</p>' +
                          '<div class="alert alert-warning" style="text-align: left; font-size: 13px; margin-top: 10px;">' +
                          '<strong>Cliente Activo:</strong> ' + selectedCustomerName + '<br>' +
                          '<strong>Cliente Seleccionado:</strong> ' + cliente + '</div>',
                    icon: 'warning',
                    confirmButtonColor: '#26B99A',
                    confirmButtonText: 'Entendido'
                });
                return;
            }

            // Establecer cliente activo
            selectedCustomerId = custId;
            selectedCustomerName = cliente;

            selectedInvoices[id] = {
                id: id,
                custId: custId,
                cliente: cliente,
                serie: $cb.data('serie'),
                folio: $cb.data('folio'),
                uuid: $cb.data('uuid'),
                monto: parseFloat($cb.data('monto') || 0),
                saldo: parseFloat($cb.data('saldo') || 0),
                pagos: parseInt($cb.data('pagos') || 0)
            };
        } else {
            delete selectedInvoices[id];
            if (Object.keys(selectedInvoices).length === 0) {
                selectedCustomerId = null;
                selectedCustomerName = null;
            }
        }

        actualizarBarraSeleccionPPD();
    }

    // Seleccionar o deseleccionar todas las visibles respetando el cliente activo
    function toggleSelectAllPPD(masterCheckbox) {
        var isChecked = $(masterCheckbox).is(':checked');

        if (isChecked) {
            var visibleBoxes = $(".check_ppd_item");
            if (visibleBoxes.length === 0) return;

            // Si aún no hay cliente seleccionado, tomar el del primer checkbox visible
            if (selectedCustomerId === null) {
                var $first = $(visibleBoxes[0]);
                selectedCustomerId = $first.data('cust-id');
                selectedCustomerName = $first.data('cliente') || 'Cliente General';
            }

            var matchingCount = 0;
            var nonMatchingCount = 0;

            visibleBoxes.each(function () {
                var $cb = $(this);
                var id = $cb.val();
                var custId = $cb.data('cust-id');

                if (custId === selectedCustomerId) {
                    $cb.prop('checked', true);
                    selectedInvoices[id] = {
                        id: id,
                        custId: custId,
                        cliente: $cb.data('cliente') || 'Cliente General',
                        serie: $cb.data('serie'),
                        folio: $cb.data('folio'),
                        uuid: $cb.data('uuid'),
                        monto: parseFloat($cb.data('monto') || 0),
                        saldo: parseFloat($cb.data('saldo') || 0),
                        pagos: parseInt($cb.data('pagos') || 0)
                    };
                    matchingCount++;
                } else {
                    $cb.prop('checked', false);
                    nonMatchingCount++;
                }
            });

            if (nonMatchingCount > 0) {
                Swal.fire({
                    title: 'Selección Parcial por Cliente',
                    html: 'Se seleccionaron <strong>' + matchingCount + '</strong> factura(s) de <strong>' + selectedCustomerName + '</strong>.<br><small class="text-muted">' + nonMatchingCount + ' factura(s) de otros clientes fueron omitidas por la regla de cliente único.</small>',
                    icon: 'info',
                    timer: 3000,
                    showConfirmButton: false
                });
            }
        } else {
            $(".check_ppd_item").each(function () {
                var id = $(this).val();
                delete selectedInvoices[id];
                $(this).prop('checked', false);
            });
            if (Object.keys(selectedInvoices).length === 0) {
                selectedCustomerId = null;
                selectedCustomerName = null;
            }
        }

        actualizarBarraSeleccionPPD();
    }

    // Limpiar toda la selección acumulada
    function limpiarSeleccionPPD() {
        selectedInvoices = {};
        selectedCustomerId = null;
        selectedCustomerName = null;
        $(".check_ppd_item").prop('checked', false);
        $("#check_all_ppd").prop('checked', false);
        actualizarBarraSeleccionPPD();
    }

    // Actualizar visualmente la barra de selección
    function actualizarBarraSeleccionPPD() {
        var count = Object.keys(selectedInvoices).length;
        if (count > 0) {
            var totalSaldo = 0.0;
            $.each(selectedInvoices, function (id, item) {
                totalSaldo += parseFloat(item.saldo || 0);
            });

            $("#txtSeleccionadasCount").text(count);
            $("#btnPagarCount").text(count);
            $("#txtClienteNombre").text(selectedCustomerName || '---');
            $("#txtTotalSaldoSeleccionadas").text('$' + totalSaldo.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            $("#barSeleccionPPD").slideDown('fast');
        } else {
            $("#barSeleccionPPD").slideUp('fast');
            $("#check_all_ppd").prop('checked', false);
        }
    }

    // Abrir modal para una única factura individual desde el botón de la fila
    function abrirModalPago(id, serie, folio, monto, uuid, cliente) {
        selectedInvoices = {};
        selectedCustomerId = null;
        selectedCustomerName = null;
        $(".check_ppd_item").prop('checked', false);

        cargarYMostrarModalPago([id]);
    }

    // Abrir modal con todas las facturas seleccionadas
    function abrirModalPagoSeleccionadas() {
        var ids = Object.keys(selectedInvoices);
        if (ids.length === 0) {
            Swal.fire('Atención', 'Seleccione al menos una factura para registrar el pago.', 'warning');
            return;
        }
        cargarYMostrarModalPago(ids);
    }

    // Cargar detalles vía AJAX y renderizar tabla en el modal
    function cargarYMostrarModalPago(ids) {
        // Fecha actual en formato YYYY-MM-DDTHH:mm
        var now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        var fechaISO = now.toISOString().slice(0, 16);
        $("#pago_fecha").val(fechaISO);
        $("#pago_forma").val("03");
        $("#pago_num_operacion").val("");
        $("#pago_serie_display").val('Consultando...');
        $("#pago_lbl_serie_folio_rep").text('Consultando...');
        $("#tbodyFacturasPPDModal").html('<tr><td colspan="6" class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i> Cargando resumen de facturas...</td></tr>');
        
        $('#modalPagoPPD').modal('show');

        $.ajax({
            type: "POST",
            url: "ajax/facturas_ajax.php",
            data: { action: "get_invoice_ppd_details", ids: ids.join(',') },
            dataType: "json",
            success: function (res) {
                if (res.status === 'success' && res.data) {
                    var d = res.data;
                    currentModalInvoices = d.items || [];

                    // Encabezado del cliente
                    $("#pago_lbl_cliente").text(d.cliente || 'Cliente General');
                    $("#pago_lbl_rfc").text(d.rfc || 'XAXX010101000');
                    $("#pago_lbl_regimen").text(d.regimen_fiscal || '601');
                    $("#pago_lbl_cp").text(d.domicilio_fiscal || '87000');
                    $("#pago_serie").val(d.serie_pago || '');
                    $("#pago_serie_display").val((d.serie_pago || '') + ' - ' + (d.folio_pago || '1'));
                    $("#pago_lbl_serie_folio_rep").text((d.serie_pago || '') + ' - ' + (d.folio_pago || '1'));
                    $("#pago_lbl_total_facturas_count").text(currentModalInvoices.length);

                    // Renderizar filas de la tabla
                    var rowsHtml = '';
                    var totOrig = 0.0;
                    var totSaldo = 0.0;
                    var totPagar = 0.0;

                    $.each(currentModalInvoices, function (idx, item) {
                        var fId = item.id;
                        var fSerieFolio = (item.serie ? item.serie + '-' : '') + (item.folio || item.id);
                        var fMonto = parseFloat(item.monto || 0);
                        var fSaldo = parseFloat(item.saldo_pendiente || 0);
                        var fParc = item.parcialidad || 1;

                        totOrig += fMonto;
                        totSaldo += fSaldo;
                        totPagar += fSaldo;

                        rowsHtml += '<tr id="row_ppd_modal_' + fId + '">' +
                            '<td><strong>' + fSerieFolio + '</strong></td>' +
                            '<td><small style="color: #555; word-break: break-all;">' + (item.uuid || item.mov_id || '---') + '</small></td>' +
                            '<td class="text-right">$' + fMonto.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>' +
                            '<td class="text-right" style="font-weight: bold; color: #b22222;">$' + fSaldo.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>' +
                            '<td>' +
                                '<input type="number" step="0.01" min="0.01" max="' + fSaldo.toFixed(2) + '" ' +
                                'class="form-control input-sm text-right input-monto-ppd" ' +
                                'data-id="' + fId + '" ' +
                                'data-saldo-max="' + fSaldo.toFixed(2) + '" ' +
                                'value="' + fSaldo.toFixed(2) + '" ' +
                                'oninput="recalcularTotalPagoModal();" style="font-weight: bold; color: #26B99A;">' +
                            '</td>' +
                            '<td class="text-center"><span class="badge" style="background-color: #3498db;">#' + fParc + '</span></td>' +
                        '</tr>';
                    });

                    $("#tbodyFacturasPPDModal").html(rowsHtml);
                    $("#modal_tot_monto_orig").text('$' + totOrig.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                    $("#modal_tot_saldo_pend").text('$' + totSaldo.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                    $("#modal_tot_monto_pagar").text('$' + totPagar.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                    $("#pago_monto_total_display").val('$' + totPagar.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

                } else {
                    Swal.fire('Error', res.message || 'No se pudieron obtener los detalles de las facturas.', 'error');
                    $('#modalPagoPPD').modal('hide');
                }
            },
            error: function () {
                Swal.fire('Error de Comunicación', 'No se pudo consultar la información de las facturas.', 'error');
                $('#modalPagoPPD').modal('hide');
            }
        });
    }

    // Recalcular montos en tiempo real al editar las celdas del modal
    function recalcularTotalPagoModal() {
        var totPagar = 0.0;
        var hasExceeded = false;

        $(".input-monto-ppd").each(function () {
            var val = parseFloat($(this).val()) || 0;
            var maxSaldo = parseFloat($(this).data('saldo-max')) || 0;

            if (val > (maxSaldo + 0.01)) {
                $(this).css('border-color', '#e74c3c');
                hasExceeded = true;
            } else {
                $(this).css('border-color', '');
            }
            totPagar += val;
        });

        $("#modal_tot_monto_pagar").text('$' + totPagar.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        $("#pago_monto_total_display").val('$' + totPagar.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

        if (hasExceeded) {
            $("#btnSubmitPagoPPD").prop('disabled', true);
        } else {
            $("#btnSubmitPagoPPD").prop('disabled', false);
        }
    }

    // Procesar y timbrar el pago individual o agrupado
    function procesarPagoPPD() {
        var forma = $("#pago_forma").val();
        var fecha = $("#pago_fecha").val();
        var serie = $("#pago_serie").val();
        var numOp = $("#pago_num_operacion").val();

        var invoicesToPay = [];
        var totalAPagar = 0.0;
        var errorValidacion = null;

        $(".input-monto-ppd").each(function () {
            var fId = parseInt($(this).data('id'));
            var monto = parseFloat($(this).val()) || 0;
            var maxSaldo = parseFloat($(this).data('saldo-max')) || 0;

            if (monto <= 0) {
                errorValidacion = 'Cada factura debe tener un monto a pagar mayor a cero.';
                return false;
            }
            if (monto > (maxSaldo + 0.01)) {
                errorValidacion = 'El monto asignado ($' + monto.toFixed(2) + ') supera el saldo pendiente ($' + maxSaldo.toFixed(2) + ').';
                return false;
            }

            invoicesToPay.push({
                id: fId,
                monto_pago: monto
            });
            totalAPagar += monto;
        });

        if (errorValidacion) {
            Swal.fire('Atención', errorValidacion, 'warning');
            return;
        }

        if (invoicesToPay.length === 0 || totalAPagar <= 0) {
            Swal.fire('Atención', 'Debe especificar al menos una factura con monto válido.', 'warning');
            return;
        }

        Swal.fire({
            title: '¿Confirmar Emisión de REP?',
            html: 'Se generará y timbrará el Complemento de Pago (REP 2.0) para <strong>' + invoicesToPay.length + ' factura(s)</strong> por un total de <strong>$' + totalAPagar.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</strong> ante el SAT / SiNube.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#26B99A',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, timbrar pago',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#modalPagoPPD').modal('hide');

                Swal.fire({
                    title: 'Timbrando Pago en SiNube',
                    html: 'Por favor, espere mientras se emite el CFDI ante el SAT...<br><br><i class="fa fa-spinner fa-spin fa-2x" style="color: #26B99A;"></i>',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    allowEnterKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    type: "POST",
                    url: "ajax/facturas_ajax.php",
                    data: {
                        action: "timbrar_pago_ppd",
                        invoices: JSON.stringify(invoicesToPay),
                        forma_pago: forma,
                        fecha_pago: fecha,
                        serie_pago: serie,
                        num_operacion: numOp
                    },
                    dataType: "json",
                    success: function (res) {
                        if (res.status === 'success') {
                            var linksHtml = '';
                            if (res.xml) {
                                linksHtml += '<a href="' + res.xml + '" target="_blank" download class="btn btn-default btn-sm" style="margin-right: 5px;"><i class="fa fa-file-code-o"></i> Descargar XML</a>';
                            }
                            if (res.pdf) {
                                linksHtml += '<a href="' + res.pdf + '" target="_blank" download class="btn btn-danger btn-sm"><i class="fa fa-file-pdf-o"></i> Descargar PDF</a>';
                            }

                            Swal.fire({
                                title: '¡Pago Timbrado Exitosamente!',
                                html: '<p>' + res.message + '</p>' +
                                      '<p><strong>UUID REP:</strong> <br><small style="color:#2A3F54;">' + (res.uuid || '') + '</small></p>' +
                                      '<p><strong>Serie/Folio:</strong> ' + (res.serie || '') + '-' + (res.folio || '') + ' | <strong>Facturas Pagadas:</strong> ' + (res.total_facturas || invoicesToPay.length) + '</p>' +
                                      '<div style="margin-top: 15px;">' + linksHtml + '</div>',
                                icon: 'success',
                                confirmButtonColor: '#26B99A',
                                confirmButtonText: 'Aceptar'
                            }).then(() => {
                                limpiarSeleccionPPD();
                                load(window.current_page || 1);
                            });
                        } else {
                            Swal.fire({
                                title: 'Error al Timbrar',
                                html: '<p style="margin-top: 10px; font-size: 15px;">' + res.message + '</p>',
                                icon: 'warning',
                                confirmButtonColor: '#3085d6'
                            });
                        }
                    },
                    error: function (xhr, status, error) {
                        Swal.fire({
                            title: 'Error de Comunicación',
                            text: 'No se pudo completar la solicitud de timbrado. Error HTTP: ' + xhr.status,
                            icon: 'error',
                            confirmButtonColor: '#d33'
                        });
                    }
                });
            }
        });
    }

    $(document).ready(function () {
        load(1);
    });
</script>
