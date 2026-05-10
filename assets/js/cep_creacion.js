$(document).ready(function () {
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
        $('#lbl_cp').text(data.cp || '---');
        
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
                title: 'Producto Listo'
            });
        }
    });

    // Acción del botón Facturar
    $('#btn_facturar_cep').on('click', function() {
        const numFacturas = parseInt($('#num_facturas').val(), 10) || 0;
        const qty = parseFloat($('#prod_qty').val()) || 0;
        const price = parseFloat($('#prod_price').val()) || 0;
        
        const client_razon_social = $('#client_razon_social').val();
        const client_rfc = $('#client_rfc').val();
        const client_uso_cfdi = $('#uso_cfdi_code').val(); // Tomar del combo
        const client_metodo_pago = $('#metodo_pago_code').val(); // Tomar del combo
        const fpago = $('#fpayment_selector').val(); // Tomar del combo
        
        // Validaciones básicas
        let errors = [];
        if (!client_rfc) errors.push("Por favor seleccione un cliente.");
        if (!client_uso_cfdi) errors.push("Por favor seleccione un Uso de CFDI.");
        if (!client_metodo_pago) errors.push("Por favor seleccione un Método de Pago (CFDI).");
        if (!fpago) errors.push("Por favor seleccione una Forma de Pago.");
        if (!$('#prod_clave_sat').val()) errors.push("Por favor seleccione un producto.");
        if (numFacturas <= 0) errors.push("Ingrese una cantidad de facturas válida mayor a 0.");
        if (qty <= 0) errors.push("Ingrese una cantidad de producto válida mayor a 0.");
        if (price <= 0) errors.push("Ingrese un precio válido mayor a 0.");
        
        if (errors.length > 0) {
            Swal.fire({
                title: 'Atención',
                html: `<ul style="text-align:left;">${errors.map(e => `<li>${e}</li>`).join('')}</ul>`,
                icon: 'warning',
                confirmButtonColor: '#34495e'
            });
            return;
        }
        
        const totalFacturaIndividual = qty * price;
        const granTotal = totalFacturaIndividual * numFacturas;
        
        // Resumen para confirmar
        let summaryHtml = `
            <div style="text-align: left; margin-top: 15px; font-size: 14px;">
                <p><strong>Cliente:</strong> ${client_razon_social}</p>
                <p><strong>RFC:</strong> ${client_rfc}</p>
                <p><strong>Uso CFDI:</strong> ${client_uso_cfdi || '---'}</p>
                <p><strong>Método de Pago:</strong> ${client_metodo_pago || '---'}</p>
                <p><strong>Forma de Pago:</strong> ${$('#fpayment_selector option:selected').text()}</p>
                <hr>
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;"><span><strong>Cantidad de facturas:</strong></span> <span>${numFacturas}</span></div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;"><span><strong>Cantidad (por factura):</strong></span> <span>${qty.toFixed(4)}</span></div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;"><span><strong>Precio del Producto:</strong></span> <span>$${price.toFixed(4)}</span></div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px; padding-top: 5px; border-top: 1px dashed #ccc;">
                    <span><strong>Total por factura (Cantidad * Precio):</strong></span> <span>$${totalFacturaIndividual.toFixed(2)}</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 12px 0; font-size: 1.25em; border-top: 2px solid #34495e; margin-top: 10px; background: #f9f9f9;">
                    <span><strong>GRAN TOTAL:</strong></span>
                    <span style="color: #26B99A; font-weight: 800;">$${granTotal.toFixed(2)}</span>
                </div>
            </div>
        `;
        
        Swal.fire({
            title: 'Confirmación de Factura CEP',
            html: summaryHtml,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#26B99A',
            cancelButtonColor: '#d33',
            confirmButtonText: '<i class="fa fa-check"></i> Proceder a Facturar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
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
                        <div style="font-size: 16px; margin-bottom: 15px;">Favor de esperar, se está procesando.<br><b>Favor de no actualizar y ser paciente.</b></div>
                        <div style="font-style: italic; color: #ccc;">"${randomPhrase}"</div>
                        <div style="margin-top: 20px; font-weight: bold; color: #26B99A;" id="swal-progress">Facturando 0 de ${numFacturas}...</div>
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

                let invoices = [];
                const forma_pago_name = $('#fpayment_selector option:selected').data('name');
                const forma_pago_code = $('#fpayment_selector option:selected').data('code') || '03';
                const product_name = $('#product_selector option:selected').text();
                
                const timestamp = new Date().getTime();

                for (let i = 0; i < numFacturas; i++) {
                    invoices.push({
                        mov_id: 'CEP-' + timestamp + '-' + i,
                        branch: 'CEP',
                        monto: totalFacturaIndividual.toFixed(2),
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
                        items: [{
                            name: product_name,
                            qty: qty,
                            price: price,
                            amount: totalFacturaIndividual.toFixed(2)
                        }]
                    });
                }

                let completedInvoices = 0;
                let successCount = 0;
                let firstError = null;
                let consultaMessages = [];

                let ajaxPromises = invoices.map(invData => {
                    return $.ajax({
                        url: "ajax/facturar_api.php",
                        type: "POST",
                        dataType: 'json',
                        data: invData
                    }).done(function(certResponse) {
                        completedInvoices++;
                        $('#swal-progress').text(`Facturando ${completedInvoices} de ${numFacturas}...`);
                        if (certResponse && certResponse.success) {
                            successCount++;
                        } else if (certResponse && certResponse.is_consulta) {
                            consultaMessages.push(certResponse.message);
                        } else {
                            if (!firstError) firstError = (certResponse && certResponse.message) ? certResponse.message : "Error al obtener datos.";
                        }
                    }).fail(function() {
                        completedInvoices++;
                        $('#swal-progress').text(`Facturando ${completedInvoices} de ${numFacturas}...`);
                        if (!firstError) firstError = "Fallo al conectar con el servidor para la factura " + invData.mov_id + ".";
                    });
                });

                $.when.apply($, ajaxPromises).always(function() {
                    if (successCount === numFacturas && numFacturas > 0) {
                        Swal.fire({
                            title: '¡Éxito!',
                            text: 'Se han generado todas las facturas correctamente.',
                            icon: 'success',
                            confirmButtonColor: '#34495e'
                        }).then(() => {
                            window.location.href = 'facturasqry.php?branch=CEP';
                        });
                    } else {
                        let alertTitle = 'Atención en Facturación';
                        let alertIcon = 'warning';
                        let alertText = '';
                        
                        if (consultaMessages.length > 0) {
                            alertText = consultaMessages.join("<br><br>");
                            if (firstError) {
                                alertText += "<br><br><b>Además ocurrió un error:</b> " + firstError;
                            }
                            if (successCount > 0) {
                                alertText += `<br><br><em>(Solo se generaron exitosamente ${successCount} de ${numFacturas} solicitadas)</em>`;
                            }
                        } else {
                            alertIcon = 'error';
                            alertText = `Se lograron facturar ${successCount} de ${numFacturas}. Error: ${firstError}`;
                        }

                        Swal.fire({
                            title: alertTitle,
                            html: `<div style="text-align:left; font-size:14px;">${alertText}</div>`,
                            icon: alertIcon,
                            confirmButtonColor: '#34495e',
                            confirmButtonText: 'Entendido'
                        });
                    }
                });
            }
        });
    });
});
