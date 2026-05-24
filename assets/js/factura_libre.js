$(document).ready(function () {
    // Array para almacenar los conceptos agregados en memoria
    let added_items = [];

    // Llenar catálogos SAT si están disponibles globalmente (desde head.php)
    if (window.SAT_METODO_PAGO && window.SAT_METODO_PAGO.length > 0) {
        window.SAT_METODO_PAGO.forEach(function(opt) {
            $('#metodo_pago_code').append(new Option(opt.name, opt.code));
        });
    }

    if (window.SAT_USO_CFDI && window.SAT_USO_CFDI.length > 0) {
        window.SAT_USO_CFDI.forEach(function(opt) {
            $('#uso_cfdi_code').append(new Option(opt.name, opt.code));
        });
    }

    // Inicializar Select2 para búsqueda de clientes
    $('#client_selector').select2({
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
        placeholder: '-- Buscar Cliente por Nombre o RFC --',
        minimumInputLength: 3,
        language: {
            inputTooShort: function () { return "Por favor ingrese 3 o más caracteres"; },
            searching: function () { return "Buscando..."; },
            noResults: function () { return "No se encontraron resultados"; }
        }
    }).on('select2:select', function (e) {
        const data = e.params.data.client_data;
        
        // Asignar a inputs ocultos (para cuando se envíe el formulario)
        $('#client_rfc').val(data.rfc);
        $('#client_razon_social').val(data.razon_social);
        $('#client_regimen').val(data.regimen_fiscal);
        $('#client_es_fisica').val(data.es_persona_fisica);
        $('#client_nombre').val(data.nombre);
        $('#client_ap_paterno').val(data.ap_paterno);
        $('#client_cp').val(data.cp);

        // Actualizar etiquetas visuales estáticas
        $('#lbl_rfc').text(data.rfc || '---');
        $('#lbl_razon_social').text(data.razon_social || '---');
        $('#lbl_regimen_fiscal').text(data.regimen_fiscal || '---');
        $('#lbl_cp').text(data.cp || '---');
        $('#lbl_email').text(data.email || '---');
        
        // Seleccionar los valores por defecto en los combos, si existen
        if (data.metodo_pago_code) {
            $('#metodo_pago_code').val(data.metodo_pago_code);
        }
        if (data.uso_cfdi_code) {
            $('#uso_cfdi_code').val(data.uso_cfdi_code);
        }

        // Notificación de éxito
        if (typeof Swal !== 'undefined') {
            const toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
            toast.fire({
                icon: 'success',
                title: 'Cliente Seleccionado',
                text: data.razon_social
            });
        }
    });

    // Inicializar Select2 para búsqueda de productos
    $('#product_selector').select2({
        ajax: {
            url: 'ajax/prod_select2.php',
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
        placeholder: '-- Buscar Producto --',
        minimumInputLength: 3,
        language: {
            inputTooShort: function () { return "Por favor ingrese 3 o más caracteres"; },
            searching: function () { return "Buscando..."; },
            noResults: function () { return "No se encontraron resultados"; }
        }
    }).on('select2:select', function (e) {
        const data = e.params.data.prod_data;
        
        // Asignar a inputs ocultos
        $('#prod_clave_sat').val(data.clave_sat);
        $('#prod_unidad_sat').val(data.unidad_sat);

        // Actualizar etiquetas visuales
        $('#lbl_clave_sat').text(data.clave_sat || '---');
        $('#lbl_unidad_sat').text(data.unidad_sat || '---');

        // Enfocar cantidad
        $('#prod_qty').focus();

        // Calcular subtotal si hay valores
        recalcularSubtotalConcepto();

        // Notificación de éxito
        if (typeof Swal !== 'undefined') {
            const toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 1500,
                timerProgressBar: true
            });
            toast.fire({
                icon: 'success',
                title: 'Producto Seleccionado'
            });
        }
    });

    // Recalcular subtotal del producto actual al cambiar cantidad o precio
    $('#prod_qty, #prod_price').on('input change', function () {
        recalcularSubtotalConcepto();
    });

    function recalcularSubtotalConcepto() {
        const qty = parseFloat($('#prod_qty').val()) || 0;
        const price = parseFloat($('#prod_price').val()) || 0;
        const subtotal = qty * price;
        $('#prod_subtotal').val(subtotal.toFixed(4));
    }

    // Agregar concepto al listado
    $('#btn_agregar_concepto').on('click', function () {
        const product_id = $('#product_selector').val();
        const product_name = $('#product_selector option:selected').text();
        const clave_sat = $('#prod_clave_sat').val();
        const unidad_sat = $('#prod_unidad_sat').val();
        const qty = parseFloat($('#prod_qty').val()) || 0;
        const price = parseFloat($('#prod_price').val()) || 0;
        const subtotal = parseFloat($('#prod_subtotal').val()) || 0;

        let errors = [];
        if (!product_id) errors.push("Debe seleccionar un producto.");
        if (qty <= 0) errors.push("La cantidad debe ser mayor a 0.");
        if (price <= 0) errors.push("El precio debe ser mayor a 0.");

        if (errors.length > 0) {
            Swal.fire({
                title: 'Campos Incompletos',
                html: `<ul style="text-align:left;">${errors.map(e => `<li>${e}</li>`).join('')}</ul>`,
                icon: 'warning',
                confirmButtonColor: '#34495e'
            });
            return;
        }

        // Agregar al array en memoria
        added_items.push({
            product_id: product_id,
            name: product_name,
            clave_sat: clave_sat,
            unidad_sat: unidad_sat,
            qty: qty,
            price: price,
            amount: subtotal
        });

        // Limpiar campos de producto
        $('#product_selector').val(null).trigger('change');
        $('#prod_clave_sat').val('');
        $('#prod_unidad_sat').val('');
        $('#lbl_clave_sat').text('---');
        $('#lbl_unidad_sat').text('---');
        $('#prod_qty').val('');
        $('#prod_price').val('');
        $('#prod_subtotal').val('');

        // Renderizar tabla
        renderTable();

        // Foco de nuevo al buscador de productos
        $('#product_selector').select2('open');
    });

    // Delegación de evento para eliminar fila
    $('#tbl_conceptos tbody').on('click', '.btn-delete-row', function () {
        const index = $(this).data('index');
        added_items.splice(index, 1);
        renderTable();
    });

    // Renderizar tabla de conceptos agregados
    function renderTable() {
        const tbody = $('#tbl_conceptos tbody');
        tbody.empty();

        if (added_items.length === 0) {
            tbody.append(`
                <tr id="tr_empty_row">
                    <td colspan="6" class="text-center" style="color: #999; padding: 25px; font-style: italic;">
                        <i class="fa fa-info-circle"></i> No se han agregado productos a la factura.
                    </td>
                </tr>
            `);
            $('#td_grand_total').text('$0.00');
            $('#btn_facturar_libre').prop('disabled', true);
            return;
        }

        let grand_total = 0;

        added_items.forEach((item, index) => {
            grand_total += item.amount;
            tbody.append(`
                <tr>
                    <td>${item.clave_sat}</td>
                    <td>${item.name}</td>
                    <td class="text-right">${item.qty.toFixed(4)}</td>
                    <td class="text-right">$${item.price.toFixed(4)}</td>
                    <td class="text-right" style="font-weight: 600;">$${item.amount.toFixed(2)}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-danger btn-xs btn-delete-row" data-index="${index}" style="margin:0;">
                            <i class="fa fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `);
        });

        $('#td_grand_total').text('$' + grand_total.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        $('#btn_facturar_libre').prop('disabled', false);
    }

    // Acción del botón Facturar
    $('#btn_facturar_libre').on('click', function() {
        const client_razon_social = $('#client_razon_social').val();
        const client_rfc = $('#client_rfc').val();
        const client_uso_cfdi = $('#uso_cfdi_code').val();
        const client_metodo_pago = $('#metodo_pago_code').val();
        const fpago = $('#fpayment_selector').val();
        const countProductos = added_items.length

        // Validaciones
        let errors = [];
        if (!client_rfc) errors.push("Por favor seleccione un cliente.");
        if (!client_uso_cfdi) errors.push("Por favor seleccione un Uso de CFDI.");
        if (!client_metodo_pago) errors.push("Por favor seleccione un Método de Pago (CFDI).");
        if (!fpago) errors.push("Por favor seleccione una Forma de Pago.");
        if (added_items.length === 0) errors.push("Debe agregar al menos un producto a la factura.");

        if (errors.length > 0) {
            Swal.fire({
                title: 'Atención',
                html: `<ul style="text-align:left;">${errors.map(e => `<li>${e}</li>`).join('')}</ul>`,
                icon: 'warning',
                confirmButtonColor: '#34495e'
            });
            return;
        }

        // Calcular el gran total
        let grandTotal = 0;
        //let conceptsHtml = '';
        added_items.forEach(item => {
            grandTotal += item.amount;

            //se valdira si se carga los prductro sen el resuemn
            /*
            conceptsHtml += `
                <tr>
                    <td style="font-size:11px; padding:4px;">${item.clave_sat}</td>
                    <td style="font-size:11px; padding:4px; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${item.name}</td>
                    <td style="font-size:11px; padding:4px;" class="text-right">${item.qty.toFixed(2)}</td>
                    <td style="font-size:11px; padding:4px;" class="text-right">$${item.price.toFixed(2)}</td>
                    <td style="font-size:11px; padding:4px;" class="text-right"><strong>$${item.amount.toFixed(2)}</strong></td>
                </tr>
            `;
            */
        });

        // Resumen para confirmar en SweetAlert2
        let summaryHtml = `
            <div style="text-align: left; margin-top: 15px; font-size: 13px;">
                <p style="margin-bottom:5px;"><strong>Sucursal:</strong> <span class="label label-primary">${window.CURRENT_BRANCH}</span></p>
                <p style="margin-bottom:5px;"><strong>Serie a utilizar:</strong> <span class="label label-default" style="background-color:#34495e;">${window.CURRENT_SERIE}</span></p>
                <p style="margin-bottom:5px;"><strong>Cliente:</strong> ${client_razon_social}</p>
                <p style="margin-bottom:5px;"><strong>RFC:</strong> ${client_rfc}</p>
                <p style="margin-bottom:5px;"><strong>Uso CFDI:</strong> ${client_uso_cfdi}</p>
                <p style="margin-bottom:5px;"><strong>Método Pago:</strong> ${client_metodo_pago}</p>
                <p style="margin-bottom:5px;"><strong>Forma Pago:</strong> ${$('#fpayment_selector option:selected').text()}</p>
                <p style="margin-bottom:5px;"><strong>Productos (#):</strong> ${countProductos}</p>
                <div style="display: flex; justify-content: space-between; padding: 12px 0; font-size: 1.25em; border-top: 2px solid #34495e; margin-top: 10px; background: #f9f9f9;">
                    <span><strong>TOTAL A FACTURAR:</strong></span>
                    <span style="color: #26B99A; font-weight: 800;">$${grandTotal.toFixed(2)}</span>
                </div>
            </div>
        `;

        Swal.fire({
            title: 'Confirmación de Factura Libre',
            html: summaryHtml,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#26B99A',
            cancelButtonColor: '#d33',
            confirmButtonText: '<i class="fa fa-check"></i> Proceder a Facturar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Frases motivacionales durante la espera
                const phrases = [
                    "El éxito es la suma de pequeños esfuerzos repetidos día tras día.",
                    "La paciencia es amarga, pero su fruto es dulce.",
                    "Todo lo bueno toma su tiempo.",
                    "La calidad nunca es un accidente; siempre es el resultado de un esfuerzo inteligente."
                ];
                const randomPhrase = phrases[Math.floor(Math.random() * phrases.length)];

                Swal.fire({
                    title: 'Procesando...',
                    html: `
                        <div style="font-size: 16px; margin-bottom: 15px;">Favor de esperar, se está procesando en SINUBE.<br><b>Favor de no actualizar y ser paciente.</b></div>
                        <div style="font-style: italic; color: #ccc;">"${randomPhrase}"</div>
                        <div style="margin-top: 20px; font-weight: bold; color: #26B99A;">Facturando conceptos...</div>
                    `,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    background: 'rgba(20, 20, 20, 0.90)',
                    color: '#fff',
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Preparación de datos a enviar
                const forma_pago_name = $('#fpayment_selector option:selected').data('name');
                const forma_pago_code = $('#fpayment_selector option:selected').data('code') || '03';
                const timestamp = new Date().getTime();

                // Armar items para facturar_api
                const itemsPayload = added_items.map(item => {
                    return {
                        name: item.name,
                        qty: item.qty,
                        price: item.price,
                        amount: item.amount.toFixed(2)
                    };
                });

                const requestPayload = {
                    mov_id: 'FL-' + window.CURRENT_BRANCH.toUpperCase() + '-' + timestamp,
                    branch: window.CURRENT_BRANCH,
                    monto: grandTotal.toFixed(2),
                    metodo_pago: forma_pago_name,
                    forma_pago: forma_pago_code,
                    metodo_pago_cfdi: client_metodo_pago,
                    uso_cfdi: client_uso_cfdi,
                    cust_id: $('#client_selector').val(),
                    rfc_receptor: client_rfc,
                    razon_social: client_razon_social,
                    regimen_fiscal: $('#client_regimen').val(),
                    es_persona_fisica: $('#client_es_fisica').val(),
                    nombre: $('#client_nombre').val(),
                    ap_paterno: $('#client_ap_paterno').val(),
                    codigo_postal: $('#client_cp').val(),
                    observacion: $('#observacion').val(),
                    items: itemsPayload
                };

                $.ajax({
                    url: "ajax/facturar_api.php",
                    type: "POST",
                    dataType: 'json',
                    data: requestPayload
                }).done(function(certResponse) {
                    if (certResponse && certResponse.success) {
                        Swal.fire({
                            title: '¡Éxito!',
                            text: 'Se ha generado la Factura Libre correctamente.',
                            icon: 'success',
                            confirmButtonColor: '#34495e'
                        }).then(() => {
                            window.location.href = `facturasqry.php?branch=${window.CURRENT_BRANCH}`;
                        });
                    } else if (certResponse && certResponse.is_consulta) {
                        Swal.fire({
                            title: 'Factura Existente',
                            html: `<div style="text-align:left; font-size:14px;">${certResponse.message}</div>`,
                            icon: 'warning',
                            confirmButtonColor: '#34495e'
                        });
                    } else {
                        const errMsg = (certResponse && certResponse.message) ? certResponse.message : "Error al procesar la factura.";
                        Swal.fire({
                            title: 'Error de Facturación',
                            text: errMsg,
                            icon: 'error',
                            confirmButtonColor: '#34495e'
                        });
                    }
                }).fail(function() {
                    Swal.fire({
                        title: 'Error de Conexión',
                        text: 'Fallo al conectar con el servidor de facturación.',
                        icon: 'error',
                        confirmButtonColor: '#34495e'
                    });
                });
            }
        });
    });
});
