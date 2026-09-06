/**
 * assets/js/depositos.js
 * Funcionalidad Javascript para la gestión de depósitos por sucursal
 */

$(document).ready(function () {
    // Cargar el listado inicial
    load(1);

    // Inicializar Select2 para Clientes
    $('#cust_id').select2({
        dropdownParent: $('#depositoModal'),
        ajax: {
            url: 'ajax/cust.php?action=select2',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term };
            },
            processResults: function (data) {
                return { results: data.results };
            },
            cache: true
        },
        placeholder: '-- Escribe 3 o más caracteres para buscar cliente --',
        minimumInputLength: 3,
        language: {
            inputTooShort: function () { return "Por favor ingrese 3 o más caracteres"; },
            searching: function () { return "Buscando..."; },
            noResults: function () { return "No se encontraron resultados"; }
        }
    });

    // Inicializar Select2 para Bancos
    $('#banco_id').select2({
        dropdownParent: $('#depositoModal'),
        ajax: {
            url: 'ajax/depositos_ajax.php?action=select2_bancos',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term };
            },
            processResults: function (data) {
                return { results: data.results };
            },
            cache: true
        },
        placeholder: '-- Escribe 3 o más caracteres para buscar banco --',
        minimumInputLength: 3,
        language: {
            inputTooShort: function () { return "Por favor ingrese 3 o más caracteres"; },
            searching: function () { return "Buscando..."; },
            noResults: function () { return "No se encontraron resultados"; }
        }
    });

    // Detectar selección de Cliente para consultar sus facturas
    $('#cust_id').on('select2:select', function (e) {
        const cust_id = $(this).val();
        if (cust_id) {
            $.ajax({
                url: 'ajax/depositos_ajax.php',
                type: 'GET',
                dataType: 'json',
                data: { action: 'get_ppd_invoices', cust_id: cust_id },
                success: function (response) {
                    const selectFactura = $('#factura_id');
                    selectFactura.empty().append('<option value="">-- No asociar a factura --</option>');
                    
                    if (response.status === 'success' && response.invoices && response.invoices.length > 0) {
                        response.invoices.forEach(function (inv) {
                            selectFactura.append(new Option(inv.display_text, inv.id));
                        });
                        $('#group_factura').slideDown(300);
                    } else {
                        $('#group_factura').slideUp(300);
                    }
                },
                error: function () {
                    $('#group_factura').slideUp(300);
                }
            });
        } else {
            $('#group_factura').slideUp(300);
        }
    });

    // Procesar envío del formulario de registro
    $('#deposito_form').on('submit', function (e) {
        e.preventDefault();

        // Validaciones básicas
        const cust_id = $('#cust_id').val();
        const banco_id = $('#banco_id').val();
        const monto = parseFloat($('#monto').val()) || 0;
        const referencia = $.trim($('#referencia').val());

        let errors = [];
        if (!cust_id) errors.push("Debe seleccionar un cliente.");
        if (!banco_id) errors.push("Debe seleccionar un banco receptor.");
        if (monto <= 0) errors.push("El monto del depósito debe ser mayor a $0.00.");
        if (!referencia) errors.push("La referencia de transferencia es obligatoria.");

        if (errors.length > 0) {
            Swal.fire({
                title: 'Atención',
                html: `<ul style="text-align:left;">${errors.map(err => `<li>${err}</li>`).join('')}</ul>`,
                icon: 'warning',
                confirmButtonColor: '#34495e'
            });
            return;
        }

        // Mostrar cargando
        Swal.fire({
            title: 'Registrando...',
            text: 'Favor de esperar un momento.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Petición AJAX para guardar
        $.ajax({
            url: 'ajax/depositos_ajax.php?action=save',
            type: 'POST',
            dataType: 'json',
            data: $('#deposito_form').serialize(),
            success: function (response) {
                if (response.status === 'success') {
                    Swal.fire({
                        title: '¡Éxito!',
                        text: response.message,
                        icon: 'success',
                        confirmButtonColor: '#26B99A'
                    }).then(() => {
                        $('#depositoModal').modal('hide');
                        resetForm();
                        load(1);
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        html: response.message,
                        icon: 'error',
                        confirmButtonColor: '#34495e'
                    });
                }
            },
            error: function () {
                Swal.fire({
                    title: 'Error de Conexión',
                    text: 'No se pudo comunicar con el servidor para registrar el depósito.',
                    icon: 'error',
                    confirmButtonColor: '#34495e'
                });
            }
        });
    });

    // Procesar envío del formulario de abonos (sub-depósitos)
    $('#abono_form').on('submit', function (e) {
        e.preventDefault();
        const parent_id = $('#abono_parent_id').val();
        const monto = parseFloat($('#abono_monto').val()) || 0;
        const referencia = $.trim($('#abono_referencia').val());

        if (monto <= 0) {
            Swal.fire('Atención', 'El monto del abono debe ser mayor a $0.00.', 'warning');
            return;
        }
        if (!referencia) {
            Swal.fire('Atención', 'La referencia de transferencia es obligatoria.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Registrando abono...',
            text: 'Favor de esperar un momento.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: 'ajax/depositos_ajax.php?action=add_sub_deposit',
            type: 'POST',
            dataType: 'json',
            data: $('#abono_form').serialize(),
            success: function (response) {
                if (response.status === 'success') {
                    Swal.fire({
                        title: '¡Éxito!',
                        text: response.message,
                        icon: 'success',
                        confirmButtonColor: '#26B99A'
                    }).then(() => {
                        $('#abonoModal').modal('hide');
                        load(1);
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: response.message,
                        icon: 'error',
                        confirmButtonColor: '#34495e'
                    });
                }
            },
            error: function () {
                Swal.fire({
                    title: 'Error de Conexión',
                    text: 'No se pudo comunicar con el servidor para registrar el abono.',
                    icon: 'error',
                    confirmButtonColor: '#34495e'
                });
            }
        });
    });

    // Inicializar Select2 para Clientes en el modal de REP
    $('#rep_cust_id').select2({
        dropdownParent: $('#repModal'),
        ajax: {
            url: 'ajax/cust.php?action=select2',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term };
            },
            processResults: function (data) {
                return { results: data.results };
            },
            cache: true
        },
        placeholder: '-- Escribe 3 o más caracteres para buscar cliente --',
        minimumInputLength: 3,
        language: {
            inputTooShort: function () { return "Por favor ingrese 3 o más caracteres"; },
            searching: function () { return "Buscando..."; },
            noResults: function () { return "No se encontraron resultados"; }
        }
    });

    // Detectar selección de cliente en el modal de REP
    $('#rep_cust_id').on('select2:select', function (e) {
        const cust_id = $(this).val();
        if (cust_id) {
            // Cargar depósitos con saldo del cliente
            $.ajax({
                url: 'ajax/depositos_ajax.php',
                type: 'GET',
                dataType: 'json',
                data: { action: 'get_client_deposits', cust_id: cust_id },
                success: function (response) {
                    const selectDep = $('#rep_deposit_id');
                    selectDep.empty().append('<option value="">-- Selecciona depósito --</option>');
                    
                    if (response.status === 'success' && response.deposits && response.deposits.length > 0) {
                        currentClientDeposits = response.deposits;
                        response.deposits.forEach(function (dep) {
                            selectDep.append(new Option(dep.display_text, dep.id));
                        });
                        $('#group_rep_deposit').slideDown(300);
                        $('#rep_payment_details').slideDown(300);
                    } else {
                        currentClientDeposits = [];
                        $('#group_rep_deposit').slideUp(300);
                        $('#rep_payment_details').slideUp(300);
                        Swal.fire({
                            title: 'Atención',
                            text: 'Este cliente no tiene depósitos con saldo disponible.',
                            icon: 'warning',
                            confirmButtonColor: '#34495e'
                        });
                    }
                }
            });

            // Cargar facturas PPD pendientes del cliente
            $.ajax({
                url: 'ajax/depositos_ajax.php',
                type: 'GET',
                dataType: 'json',
                data: { action: 'get_ppd_invoices', cust_id: cust_id },
                success: function (response) {
                    const tbody = $('#rep_invoices_table tbody');
                    tbody.empty();
                    
                    if (response.status === 'success' && response.invoices && response.invoices.length > 0) {
                        response.invoices.forEach(function (inv) {
                            const folio_desc = (inv.serie ? inv.serie + "-" : "") + inv.folio;
                            const fecha_f = inv.fecha_factura ? inv.fecha_factura.split(' ')[0].split('-').reverse().join('-') : '---';
                            tbody.append(`
                                <tr data-id="${inv.id}">
                                    <td class="text-center" style="vertical-align: middle;">
                                        <input type="checkbox" class="invoice-check" value="${inv.id}">
                                    </td>
                                    <td style="vertical-align: middle;">
                                        <strong>${folio_desc}</strong><br>
                                        <small class="text-muted">${inv.uuid}</small>
                                    </td>
                                    <td style="vertical-align: middle;">${fecha_f}</td>
                                    <td class="text-right" style="vertical-align: middle;">$${parseFloat(inv.monto).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</td>
                                    <td class="text-right" style="vertical-align: middle; font-weight: bold;">$${parseFloat(inv.saldo_restante).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</td>
                                    <td class="text-right" style="vertical-align: middle;">
                                        <input type="number" class="form-control text-right invoice-amount" 
                                               name="invoices[${inv.id}]" 
                                               step="0.01" min="0.01" max="${parseFloat(inv.saldo_restante).toFixed(2)}"
                                               disabled style="font-weight: bold; width: 120px; display: inline-block;">
                                    </td>
                                </tr>
                            `);
                        });
                        $('#group_rep_invoices').slideDown(300);
                    } else {
                        $('#group_rep_invoices').slideUp(300);
                        Swal.fire({
                            title: 'Atención',
                            text: 'Este cliente no tiene facturas PPD pendientes de pago.',
                            icon: 'warning',
                            confirmButtonColor: '#34495e'
                        });
                    }
                }
            });
        } else {
            $('#group_rep_deposit').slideUp(300);
            $('#rep_payment_details').slideUp(300);
            $('#group_rep_invoices').slideUp(300);
        }
    });

    // Detectar selección de depósito
    $('#rep_deposit_id').on('change', function () {
        const depId = parseInt($(this).val()) || 0;
        const deposit = currentClientDeposits.find(d => d.id === depId);
        
        if (deposit) {
            currentDepositMaxBalance = parseFloat(deposit.saldo);
            $('#rep_deposit_balance_info').html(`
                <div class="alert alert-success" style="margin-top: 5px; padding: 8px 12px; border-radius: 4px; font-weight: 500;">
                    <strong>Saldo Disponible en este depósito:</strong> $${currentDepositMaxBalance.toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                </div>
            `);
            $('#rep_max_disponible').text('$' + currentDepositMaxBalance.toLocaleString('es-MX', { minimumFractionDigits: 2 }));
        } else {
            currentDepositMaxBalance = 0;
            $('#rep_deposit_balance_info').empty();
            $('#rep_max_disponible').text('$0.00');
        }
        recalculateREPTotals();
    });

    // Enviar formulario de generación de REP
    $('#rep_form').on('submit', function (e) {
        e.preventDefault();
        
        const cust_id = $('#rep_cust_id').val();
        const deposit_id = $('#rep_deposit_id').val();
        const total = parseFloat($('#rep_total_aplicar').text().replace('$', '').replace(/,/g, '')) || 0;
        
        if (!cust_id) {
            Swal.fire('Atención', 'Debe seleccionar un cliente.', 'warning');
            return;
        }
        if (!deposit_id) {
            Swal.fire('Atención', 'Debe seleccionar un depósito de origen.', 'warning');
            return;
        }
        if (total <= 0) {
            Swal.fire('Atención', 'Debe aplicar al menos un monto de pago a las facturas.', 'warning');
            return;
        }
        if (total > currentDepositMaxBalance + 0.01) {
            Swal.fire('Atención', 'El total a aplicar supera el saldo disponible del depósito.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Generando Complemento de Pago (REP)...',
            text: 'Este proceso timbra el documento ante el SAT vía Sinube API.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const formData = {
            action: 'generate_payment_complement',
            cust_id: cust_id,
            deposit_id: deposit_id,
            branch: window.CURRENT_BRANCH,
            forma_pago: $('#rep_forma_pago').val(),
            fecha_pago: $('#rep_fecha_pago').val(),
            invoices: {}
        };

        $('.invoice-check:checked').each(function () {
            const row = $(this).closest('tr');
            const invId = $(this).val();
            const amt = parseFloat(row.find('.invoice-amount').val()) || 0;
            if (amt > 0) {
                formData.invoices[invId] = amt;
            }
        });

        $.ajax({
            url: 'ajax/depositos_ajax.php',
            type: 'POST',
            dataType: 'json',
            data: formData,
            success: function (response) {
                if (response.status === 'success') {
                    Swal.fire({
                        title: '¡Timbrado Exitoso!',
                        html: `
                            <p>${response.message}</p>
                            <p><strong>UUID:</strong> ${response.uuid}</p>
                            <div style="margin-top: 15px;">
                                <a href="${response.pdf}" target="_blank" class="btn btn-danger btn-sm" style="font-weight:bold; margin-right:5px;">
                                    <i class="fa fa-file-pdf-o"></i> Descargar PDF
                                </a>
                                <a href="${response.xml}" target="_blank" class="btn btn-default btn-sm" style="font-weight:bold;">
                                    <i class="fa fa-file-code-o"></i> Ver XML
                                </a>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonColor: '#26B99A'
                    }).then(() => {
                        $('#repModal').modal('hide');
                        load(1);
                    });
                } else {
                    Swal.fire('Error al Generar REP', response.message, 'error');
                }
            },
            error: function (xhr) {
                let errText = 'No se pudo comunicar con el servidor para generar el REP.';
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res && res.message) errText = res.message;
                } catch(e){}
                Swal.fire('Error de Conexión / Timbrado', errText, 'error');
            }
        });
    });
});

let currentClientDeposits = [];
let currentDepositMaxBalance = 0;

/**
 * Carga el listado de depósitos vía AJAX
 * @param {number} page
 */
function load(page) {
    const q = $('#q').val();
    const per_page = $('#per_page').val();
    const branch = window.CURRENT_BRANCH;

    $('#loader').show();
    $('.outer_div').empty();

    $.ajax({
        url: 'ajax/depositos_ajax.php',
        type: 'GET',
        data: {
            action: 'list',
            branch: branch,
            q: q,
            page: page,
            per_page: per_page
        },
        success: function (data) {
            $('#loader').hide();
            $('.outer_div').html(data);
        },
        error: function () {
            $('#loader').hide();
            $('.outer_div').html('<div class="alert alert-danger">Error al cargar el listado de depósitos.</div>');
        }
    });
}

/**
 * Abre el modal para registrar un nuevo depósito y resetea el formulario
 */
function openModalAdd() {
    resetForm();
    $('#depositoModal').modal('show');
}

/**
 * Limpia y resetea los campos del formulario
 */
function resetForm() {
    $('#deposito_form')[0].reset();
    $('#cust_id').val(null).trigger('change');
    $('#banco_id').val(null).trigger('change');
    $('#factura_id').empty().append('<option value="">-- No asociar a factura --</option>');
    $('#group_factura').hide();
}

/**
 * Abre el modal para registrar un abono (sub-depósito)
 */
function openAbonoModal(parentId, clientName, currentSaldo) {
    $('#abono_parent_id').val(parentId);
    $('#abono_parent_desc').text(clientName);
    $('#abono_parent_saldo').text('$' + parseFloat(currentSaldo).toLocaleString('es-MX', { minimumFractionDigits: 2 }));
    $('#abono_monto').val('');
    $('#abono_referencia').val('');
    $('#abonoModal').modal('show');
}

/**
 * Abre el modal para generar el REP
 */
function openREPModal() {
    resetREPForm();
    $('#repModal').modal('show');
}

/**
 * Limpia y resetea el formulario de REP
 */
function resetREPForm() {
    $('#rep_form')[0].reset();
    $('#rep_cust_id').val(null).trigger('change');
    $('#rep_deposit_id').empty().append('<option value="">-- Selecciona depósito --</option>');
    $('#group_rep_deposit').hide();
    $('#rep_payment_details').hide();
    $('#group_rep_invoices').hide();
    $('#rep_deposit_balance_info').empty();
    $('#rep_invoices_table tbody').empty();
    $('#rep_total_aplicar').text('$0.00');
    $('#rep_max_disponible').text('$0.00');
    $('#btn_submit_rep').prop('disabled', true);
    currentClientDeposits = [];
    currentDepositMaxBalance = 0;
    
    // Set default payment date to local time formatted as YYYY-MM-DDTHH:MM
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    $('#rep_fecha_pago').val(now.toISOString().slice(0, 16));
}

/**
 * Obtiene el total ya aplicado excluyendo una factura en específico
 */
function getREPTotalAppliedSoFar(excludeInvoiceId) {
    let total = 0;
    $('.invoice-check:checked').each(function () {
        const row = $(this).closest('tr');
        const invId = parseInt(row.data('id')) || 0;
        if (invId !== excludeInvoiceId) {
            total += parseFloat(row.find('.invoice-amount').val()) || 0;
        }
    });
    return total;
}

/**
 * Recalcula los totales del REP y habilita/deshabilita el botón de envío
 */
function recalculateREPTotals() {
    let total = 0;
    $('.invoice-check:checked').each(function () {
        const row = $(this).closest('tr');
        total += parseFloat(row.find('.invoice-amount').val()) || 0;
    });
    
    $('#rep_total_aplicar').text('$' + total.toLocaleString('es-MX', { minimumFractionDigits: 2 }));
    
    if (total > 0 && total <= currentDepositMaxBalance + 0.01) {
        $('#btn_submit_rep').prop('disabled', false);
        $('#rep_total_aplicar').css('color', '#26B99A');
    } else {
        $('#btn_submit_rep').prop('disabled', true);
        if (total > currentDepositMaxBalance) {
            $('#rep_total_aplicar').css('color', '#e74c3c');
        } else {
            $('#rep_total_aplicar').css('color', '#34495e');
        }
    }
}

// Escuchar cambios en la tabla de facturas del REP
$(document).on('change', '.invoice-check', function () {
    const row = $(this).closest('tr');
    const inputAmount = row.find('.invoice-amount');
    const isChecked = $(this).is(':checked');
    
    if (isChecked) {
        inputAmount.prop('disabled', false);
        const saldoRestante = parseFloat(inputAmount.attr('max')) || 0;
        const totalAplicado = getREPTotalAppliedSoFar(parseInt(row.data('id')) || 0);
        const saldoDisponible = Math.max(0, currentDepositMaxBalance - totalAplicado);
        const defaultAmt = Math.min(saldoRestante, saldoDisponible);
        inputAmount.val(defaultAmt.toFixed(2));
    } else {
        inputAmount.val('');
        inputAmount.prop('disabled', true);
    }
    recalculateREPTotals();
});

$(document).on('input change', '.invoice-amount', function () {
    const val = parseFloat($(this).val()) || 0;
    const max = parseFloat($(this).attr('max')) || 0;
    
    if (val > max) {
        $(this).val(max.toFixed(2));
    } else if (val < 0) {
        $(this).val('0.00');
    }
    recalculateREPTotals();
});

/**
 * Elimina un depósito mediante AJAX
 * @param {number} id
 */
function deleteDeposito(id) {
    Swal.fire({
        title: '¿Está seguro de eliminar este depósito?',
        text: "Esta acción no se puede deshacer y el depósito quedará inactivo.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Eliminando...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: 'ajax/depositos_ajax.php?action=delete',
                type: 'POST',
                dataType: 'json',
                data: { id: id },
                success: function (response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            title: '¡Eliminado!',
                            text: response.message,
                            icon: 'success',
                            confirmButtonColor: '#26B99A'
                        }).then(() => {
                            load(1);
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.message,
                            icon: 'error',
                            confirmButtonColor: '#34495e'
                        });
                    }
                },
                error: function () {
                    Swal.fire({
                        title: 'Error de Conexión',
                        text: 'No se pudo comunicar con el servidor para eliminar el depósito.',
                        icon: 'error',
                        confirmButtonColor: '#34495e'
                    });
                }
            });
        }
    });
}
