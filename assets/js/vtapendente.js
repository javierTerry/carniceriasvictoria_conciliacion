$(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    const mov_id = urlParams.get('mov_id') || urlParams.get('id');
    const branch = urlParams.get('branch');
    
    // Store items per section (unique key)
    let managedItems = {};
    let sectionCounter = 0;
    // Pool of available items from the ticket
    let availableItems = [];
    let currentTicketFcode = '03'; // Default to Trasferencia or similar

    if (mov_id && branch) {
        viewTicketHTML(mov_id, branch);
    } else {
        $('#ticket_preview').html('<div class="alert alert-danger">Error: Mov ID o Sucursal no proporcionados.</div>');
    }

    // Inicializar Select2 para clientes
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
        $('#client_rfc').val(data.rfc);
        $('#client_razon_social').val(data.razon_social);
        $('#client_regimen').val(data.regimen_fiscal);
        $('#client_es_fisica').val(data.es_persona_fisica);
        $('#client_nombre').val(data.nombre);
        $('#client_ap_paterno').val(data.ap_paterno);
        $('#client_cp').val(data.cp);
        $('#client_metodo_pago').val(data.metodo_pago_code);
        $('#client_uso_cfdi').val(data.uso_cfdi_code);

        // Actualizar etiquetas visuales
        $('#lbl_rfc').text(data.rfc || '---');
        $('#lbl_razon_social').text(data.razon_social || '---');
        $('#lbl_cp').text(data.cp || '---');
        $('#lbl_metodo_pago').text(data.metodo_pago_code || '---');
        $('#lbl_uso_cfdi').text(data.uso_cfdi_code || '---');

        // Aplicar defaults del cliente a todas las secciones ya creadas
        Object.keys(managedItems).forEach(section_id => {
            if (data.metodo_pago_code) managedItems[section_id].metodo_pago_cfdi = data.metodo_pago_code;
            if (data.uso_cfdi_code) managedItems[section_id].uso_cfdi = data.uso_cfdi_code;
        });
        renderManagementTables();
        
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
    });

    // Helper to clean and parse numbers reliably
    const parseNum = (val) => {
        if (typeof val === 'number') return val;
        if (!val) return 0;
        // Remove everything except numbers, dots, and minus signs
        const cleaned = String(val).replace(/[^\d.-]/g, '');
        return parseFloat(cleaned) || 0;
    };

    // Add manual amount with automatic item selection (Greedy)
    $('#btn_add_manual_amount').on('click', function() {
        // Check if we are in "Completar" mode (button is green/success)
        if ($(this).hasClass('btn-success')) {
            showCompletionSummary();
            return;
        }

        const fpayment_selector = $('#fpayment_selector');
        const forma_pago_id = fpayment_selector.val();
        const forma_pago_name = $('#fpayment_selector option:selected').data('name');
        const forma_pago_code = $('#fpayment_selector option:selected').data('code') || '03';
        const amountInput = $('#manual_amount');
        let amountToSection = parseNum(amountInput.val());
        const ticketTotal = parseNum($('#global_ticket_total').val());

        // 1 & 2. Validation: Basic requirements
        let validationErrors = [];
        if (!forma_pago_id) validationErrors.push("Seleccione una forma de pago.");
        if (amountToSection <= 0) validationErrors.push("Ingrese un monto válido mayor a 0.");
        if (availableItems.length === 0) validationErrors.push("No hay artículos cargados en la vista previa del ticket.");
        if (!$('#client_rfc').val()) validationErrors.push("Por favor seleccione un cliente para la facturación.");

        if (validationErrors.length > 0) {
            Swal.fire({
                title: 'Atención',
                html: `<ul style="margin-top:10px;">${validationErrors.map(err => `<li style="text-align:left; margin-bottom:5px;">${err}</li>`).join('')}</ul>`,
                icon: 'warning',
                confirmButtonColor: '#34495e'
            });
            return;
        }

        // Calculate current accumulated total
        let currentAccumulated = 0;
        Object.values(managedItems).forEach(group => {
            group.items.forEach(item => currentAccumulated += item.amount);
        });

        let pendingBalance = ticketTotal - currentAccumulated;

        // Validation: Already completed
        if (pendingBalance <= 0.001) {
            Swal.fire({ 
                title: 'Venta Completada', 
                text: 'El total de la venta ya ha sido cubierto.', 
                icon: 'info', 
                confirmButtonColor: '#34495e' 
            });
            return;
        }

        // PERMISSIVE: Clamp amount to pending balance instead of erroring out
        if (amountToSection > (pendingBalance + 0.001)) {
            amountToSection = pendingBalance;
        }

        // Greedy matching algorithm
        let selections = [];
        let remainingToFind = amountToSection;

        for (let i = 0; i < availableItems.length; i++) {
            let item = availableItems[i];
            
            // Skip if this entire item pool has already been consumed
            if (item.remainingAmount <= 0.001) continue;

            // Determine how much we can take from this item
            let takeAmount = Math.min(remainingToFind, item.remainingAmount);
            if (takeAmount <= 0.001) continue;

            // Calculate weight based on price
            let itemPrice = item.price > 0 ? item.price : 1; 
            let takeQty = (takeAmount / itemPrice);

            // Create a portion entry
            selections.push({
                name: item.name,
                qty: takeQty,
                price: itemPrice,
                amount: takeAmount,
                originalIndex: i
            });

            // Update the available pool
            item.remainingAmount -= takeAmount;
            item.remainingQty -= takeQty;
            
            // Update what we still need to find
            remainingToFind -= takeAmount;

            // If we've found enough, we can stop
            if (remainingToFind <= 0.001) break;
        }

        // Final check: Did we actually select anything?
        if (selections.length === 0) {
            Swal.fire({
                title: 'No se pudo asignar',
                text: 'El monto solicitado no pudo ser asignado. Asegúrese de que los productos tengan saldo pendiente en la vista previa del ticket.',
                icon: 'error',
                confirmButtonColor: '#34495e'
            });
            return;
        }

        // Generate a unique section ID for this "Agregar" action
        sectionCounter++;
        const section_id = `section_${sectionCounter}`;

        // Add the calculated selections to the managed items structure
        selections.forEach(sel => {
            addItemToManagement(section_id, forma_pago_id, forma_pago_name, forma_pago_code, sel);
        });

        amountInput.val('');
        renderManagementTables();
        
        // Success feedback
        const toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });
        toast.fire({
            icon: 'success',
            title: `Partidas asignadas con éxito ($${amountToSection.toFixed(2)})`
        });
    });

    function addItemToManagement(section_id, forma_pago_id, forma_pago_name, forma_pago_code, item) {
        if (!managedItems[section_id]) {
            managedItems[section_id] = {
                forma_pago_id: forma_pago_id,
                name: forma_pago_name,
                code: forma_pago_code,
                metodo_pago_cfdi: $('#client_metodo_pago').val() || 'PUE', 
                uso_cfdi: $('#client_uso_cfdi').val() || 'G03',         
                items: []
            };
        }
        managedItems[section_id].items.push(item);
    }

    function renderManagementTables() {
        $('#empty_management_msg').hide();
        let html = '';
        let totalAccumulated = 0;
        const sortedKeys = Object.keys(managedItems);
        
        sortedKeys.forEach(section_id => {
            const group = managedItems[section_id];
            let subtotal = 0;
            
            html += `
            <div class="management-table-container" style="margin-bottom: 15px; border: 1px solid #e1e8ed; border-radius: 4px; overflow: hidden; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                <div style="background: #f8f9fa; color: #34495e; padding: 6px 10px; font-weight: bold; border-bottom: 1px solid #e1e8ed;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                        <span style="text-transform: uppercase; letter-spacing: 0.5px; font-size: 10px; color: #73879C;">
                            <i class="fa fa-credit-card" style="color: #26B99A; margin-right: 5px;"></i> ${group.name}
                        </span>
                        <button class="btn btn-link btn-xs" style="color: #d9534f; text-decoration: none; padding: 0; font-size: 10px; height: auto;" onclick="removeTable('${section_id}')" title="Eliminar Sección">
                            <i class="fa fa-trash"></i> Eliminar
                        </button>
                    </div>
                    
                    <div class="row" style="margin: 0; padding-top: 5px; border-top: 1px solid #eee;">
                        <div class="col-xs-6" style="padding-left: 0; padding-right: 5px;">
                            <label style="font-size: 9px; color: #999; margin-bottom: 2px;">MÉTODO PAGO (CFDI):</label>
                            <select class="form-control input-xs" style="height: 22px; font-size: 10px; padding: 2px 5px;" onchange="updateGroupField('${section_id}', 'metodo_pago_cfdi', this.value)">
                                ${(window.SAT_METODO_PAGO || []).map(opt => `<option value="${opt.code}" ${group.metodo_pago_cfdi === opt.code ? 'selected' : ''}>${opt.name}</option>`).join('')}
                            </select>
                        </div>
                        <div class="col-xs-6" style="padding-right: 0; padding-left: 5px;">
                            <label style="font-size: 9px; color: #999; margin-bottom: 2px;">USO CFDI:</label>
                            <select class="form-control input-xs" style="height: 22px; font-size: 10px; padding: 2px 5px;" onchange="updateGroupField('${section_id}', 'uso_cfdi', this.value)">
                                ${(window.SAT_USO_CFDI || []).map(opt => `<option value="${opt.code}" ${group.uso_cfdi === opt.code ? 'selected' : ''}>${opt.name}</option>`).join('')}
                            </select>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-condensed" style="margin-bottom: 0; font-size: 11px;">
                         <thead style="background: #ffffff;">
                            <tr style="color: #999; font-size: 9px; text-transform: uppercase;">
                                <th style="padding: 4px 10px; border-top: none;">Producto</th>
                                <th class="text-right" style="padding: 4px 10px; border-top: none;">Cant</th>
                                <th class="text-right" style="padding: 4px 10px; border-top: none;">Precio</th>
                                <th class="text-right" style="padding: 4px 10px; border-top: none;">Total</th>
                                <th style="width: 30px; border-top: none;"></th>
                            </tr>
                         </thead>
                         <tbody>`;
            
            group.items.forEach((item, index) => {
                subtotal += item.amount;
                totalAccumulated += item.amount;
                html += `
                <tr style="border-bottom: 1px solid #f8f9fa;">
                    <td style="padding: 4px 10px; vertical-align: middle;">${item.name}</td>
                    <td class="text-right" style="padding: 4px 10px; vertical-align: middle; color: #34495e;">${item.qty.toFixed(3)}</td>
                    <td class="text-right" style="padding: 4px 10px; vertical-align: middle; color: #73879C;">$${item.price.toFixed(2)}</td>
                    <td class="text-right" style="padding: 4px 10px; vertical-align: middle; font-weight: 600; color: #34495e;">$${item.amount.toFixed(2)}</td>
                    <td class="text-center" style="padding: 4px 10px; vertical-align: middle;">
                        <button class="btn btn-default btn-xs" style="border-radius: 50%; color: #d9534f; border-color: #f2dede; padding: 0 4px; font-size: 9px;" onclick="removeItem('${section_id}', ${index})" title="Quitar">
                            <i class="fa fa-times" style="font-size: 9px;"></i>
                        </button>
                    </td>
                </tr>`;
            });
            
            html += `
                         </tbody>
                         <tfoot style="background: #fcfdfd; font-weight: bold; border-top: 1px solid #e1e8ed;">
                            <tr>
                                <td colspan="3" class="text-right" style="padding: 6px 10px; font-size: 10px; color: #999;">SUB TOTAL:</td>
                                <td class="text-right" style="padding: 6px 10px; color: #26B99A; font-size: 11px;">$${subtotal.toFixed(2)}</td>
                                <td></td>
                            </tr>
                         </tfoot>
                    </table>
                </div>
            </div>`;
        });
        
        $('#payment_method_tables').html(html);
        updateSummary(totalAccumulated);

        if (sortedKeys.length === 0) {
            $('#empty_management_msg').show();
        }
    }

    window.updateGroupField = function(section_id, field, value) {
        if (managedItems[section_id]) {
            managedItems[section_id][field] = value;
        }
    };

    function updateSummary(accumulated) {
        const ticketTotal = parseFloat($('#global_ticket_total').val()) || 0;
        const pending = ticketTotal - accumulated;

        $('#ticket_total_val').text(`$${ticketTotal.toFixed(2)}`);
        $('#accumulated_total_val').text(`$${accumulated.toFixed(2)}`);
        $('#pending_total_val').text(`$${Math.max(0, pending).toFixed(2)}`);
        
        const btnAdd = $('#btn_add_manual_amount');
        if (pending <= 0.009) {
            $('#pending_total_val').css('color', '#26B99A'); // Green for zero/covered
            btnAdd.html('<i class="fa fa-check"></i> Facturar').removeClass('btn-primary').addClass('btn-success');
        } else {
            btnAdd.html('<i class="fa fa-plus"></i> Agregar').removeClass('btn-success').addClass('btn-primary');
            if (pending < -0.01) {
                $('#pending_total_val').css('color', '#d9534f').attr('title', 'Cuidado: Excede el total del ticket');
            } else {
                $('#pending_total_val').css('color', '#e74c3c').removeAttr('title');
            }
        }
    }

    function showCompletionSummary() {
        let summaryHtml = '<div style="text-align: left; margin-top: 10px;">';
        let grandTotal = 0;
        
        Object.values(managedItems).forEach(group => {
            let subtotal = 0;
            group.items.forEach(item => subtotal += item.amount);
            grandTotal += subtotal;
            
            summaryHtml += `
                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee;">
                    <span><i class="fa fa-credit-card" style="color: #26B99A; width: 20px;"></i> <strong>${group.name}</strong></span>
                    <span style="color: #26B99A; font-weight: bold;">$${subtotal.toFixed(2)}</span>
                </div>`;
        });
        
        summaryHtml += `
            <div style="display: flex; justify-content: space-between; padding: 12px 0; font-size: 1.25em; border-top: 2px solid #34495e; margin-top: 10px; background: #f9f9f9; padding-left: 5px; padding-right: 5px;">
                <span><strong>TOTAL CONCILIADO</strong></span>
                <span style="color: #34495e; font-weight: 800;">$${grandTotal.toFixed(2)}</span>
            </div>
        </div>`;

        Swal.fire({
            title: '¿Confirmar Factura?',
            html: summaryHtml,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#26B99A',
            cancelButtonColor: '#34495e',
            confirmButtonText: '<i class="fa fa-check"></i> Sí, facturar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                completeTicketAction();
            }
        });
    }

    function completeTicketAction() {
        // Collect all invoices to be generated from managedItems
        let invoices = [];
        Object.keys(managedItems).forEach(section_id => {
            let group = managedItems[section_id];
            let subtotal = 0;
            
            // Reconstruir el catálogo de conceptos dinámico para esta proporción de pago
            let itemsPayload = group.items.map(item => ({
                name: item.name,
                qty: item.qty,
                price: item.price,
                amount: item.amount
            }));

            group.items.forEach(item => subtotal += item.amount);
            
            if (subtotal > 0) {
                invoices.push({
                    mov_id: mov_id,
                    branch: branch,
                    monto: subtotal,
                    metodo_pago: group.name,
                    forma_pago: group.code || '03',
                    metodo_pago_cfdi: group.metodo_pago_cfdi,
                    uso_cfdi: group.uso_cfdi,
                    // Datos del cliente seleccionado
                    cust_id: $('#client_selector').val(),
                    rfc_receptor: $('#client_rfc').val(),
                    razon_social: $('#client_razon_social').val(),
                    regimen_fiscal: $('#client_regimen').val(),
                    es_persona_fisica: $('#client_es_fisica').val(),
                    nombre: $('#client_nombre').val(),
                    ap_paterno: $('#client_ap_paterno').val(),
                    codigo_postal: $('#client_cp').val(),
                    items: itemsPayload
                });
            }
        });

        if (invoices.length === 0) {
            Swal.fire({
                title: 'Error',
                text: 'No hay montos asignados para facturar.',
                icon: 'error',
                confirmButtonColor: '#34495e'
            });
            return;
        }

        let completedInvoices = 0;
        let totalInvoices = invoices.length;
        let successCount = 0;
        let firstError = null;

        Swal.fire({
            title: 'Procesando...',
            text: `Facturando 0 de ${totalInvoices}...`,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Execute ajax requests concurrently but track completion
        let ajaxPromises = invoices.map(invData => {
            return $.ajax({
                url: "ajax/facturar_api.php",
                type: "POST",
                dataType: 'json',
                data: invData
            }).done(function(certResponse) {
                completedInvoices++;
                Swal.update({ text: `Facturando ${completedInvoices} de ${totalInvoices}...` });
                if (certResponse && certResponse.success) {
                    successCount++;
                } else {
                    if (!firstError) firstError = (certResponse && certResponse.message) ? certResponse.message : "Error al obtener datos.";
                }
            }).fail(function() {
                completedInvoices++;
                Swal.update({ text: `Facturando ${completedInvoices} de ${totalInvoices}...` });
                if (!firstError) firstError = "Fallo al conectar con el servidor para la factura de " + invData.metodo_pago + ".";
            });
        });

        $.when.apply($, ajaxPromises).always(function() {
            if (successCount === totalInvoices) {
                proceedWithLocalCompletion();
            } else {
                Swal.fire({
                    title: 'Error en Facturación',
                    text: `Se lograron facturar ${successCount} de ${totalInvoices}. Error: ${firstError}`,
                    icon: 'error',
                    confirmButtonColor: '#34495e',
                    confirmButtonText: 'Cerrar'
                });
            }
        });
    }

    function proceedWithLocalCompletion() {
        Swal.fire({
            title: 'Procesando...',
            text: 'Actualizando estatus',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: "ajax/complete_ticket.php",
            type: "POST",
            data: { 
                mov_id: mov_id,
                branch: branch 
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    Swal.fire({
                        title: '¡Éxito!',
                        text: response.message,
                        icon: 'success',
                        confirmButtonColor: '#34495e'
                    }).then(() => {
                        window.location.href = `facturasqry.php?branch=${branch}`;
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
                    title: 'Error de Red',
                    text: 'No se pudo comunicar con el servidor.',
                    icon: 'error',
                    confirmButtonColor: '#34495e'
                });
            }
        });
    }

    window.removeItem = function(section_id, index) {
        const item = managedItems[section_id].items[index];
        // Return to pool so it can be re-allocated later
        if (item.originalIndex !== undefined && availableItems[item.originalIndex]) {
            availableItems[item.originalIndex].remainingAmount += parseNum(item.amount);
            availableItems[item.originalIndex].remainingQty += parseNum(item.qty);
        }

        managedItems[section_id].items.splice(index, 1);
        if (managedItems[section_id].items.length === 0) {
            delete managedItems[section_id];
        }
        renderManagementTables();
    };

    window.removeTable = function(section_id) {
        Swal.fire({
            title: '¿Eliminar sección?',
            text: "Se devolverán los importes al saldo pendiente.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Return all items in this table to pool
                managedItems[section_id].items.forEach(item => {
                    if (item.originalIndex !== undefined && availableItems[item.originalIndex]) {
                        availableItems[item.originalIndex].remainingAmount += parseNum(item.amount);
                        availableItems[item.originalIndex].remainingQty += parseNum(item.qty);
                    }
                });
                delete managedItems[section_id];
                renderManagementTables();
            }
        });
    };
    function viewTicketHTML(mov_id, branch) {
        $('#ticket_preview').html(
            '<div class="text-center" style="margin-top: 50px;"><img src="./images/ajax-loader.gif"> Cargando...</div>',
        );
        $.ajax({
            url: "ajax/vta_html_ticket.php",
            type: "GET",
            data: { mov_id: mov_id, branch: branch, manage: 1 },
            success: function (response) {
                $("#ticket_preview").html(response);
                
                // Parse the HTML table directly to avoid any PHP JSON encoding issues
                availableItems = [];
                try {
                    $("#ticket_preview .ticket-table tbody tr").each(function() {
                        const qtyStr = $(this).find("td").eq(0).text().trim();
                        const nameStr = $(this).find("td").eq(1).text().trim();
                        const priceStr = $(this).find("td").eq(2).text().trim();
                        const amountStr = $(this).find("td").eq(3).text().trim();
                        
                        // Simple helper accessible in this scope for initialization
                        const pNum = (s) => parseFloat(String(s).replace(/[^\d.-]/g, '')) || 0;
                        
                        const qty = pNum(qtyStr);
                        const price = pNum(priceStr);
                        const amount = pNum(amountStr);
                        
                        if (amount > 0) {
                            availableItems.push({
                                name: nameStr,
                                qty: qty,
                                price: price,
                                amount: amount,
                                originalIndex: availableItems.length,
                                remainingQty: qty,
                                remainingAmount: amount
                            });
                        }
                    });
                    console.log("Ticket items loaded into pool:", availableItems.length);
                    
                    // Capture fcode from HTML
                    const fcodeInfo = $("#ticket_payment_info");
                    if (fcodeInfo.length > 0) {
                        currentTicketFcode = fcodeInfo.data("fcode") || '03';
                        console.log("Captured fcode:", currentTicketFcode);
                    }
                } catch(e) {
                    console.error("Error parsing ticket items from DOM:", e);
                }

                setTimeout(() => {
                    const totalEl = $('#raw_ticket_total', '#ticket_preview');
                    if (totalEl.length > 0) {
                        const tVal = (s) => parseFloat(String(s).replace(/[^\d.-]/g, '')) || 0;
                        const totalVal = tVal(totalEl.val());
                        $('#ticket_total_val').text(`$${totalVal.toFixed(2)}`);
                        $('#pending_total_val').text(`$${totalVal.toFixed(2)}`);
                        
                        // Maintain a reliable global reference for the calculation logic
                        if ($('#global_ticket_total').length === 0) {
                            $('body').append(`<input type="hidden" id="global_ticket_total" value="${totalVal}">`);
                        } else {
                            $('#global_ticket_total').val(totalVal);
                        }
                    }
                }, 100);
            },
            error: function () {
                $("#ticket_preview").html(
                    '<div class="alert alert-danger" style="margin-top: 50px;">Error al cargar el ticket.</div>',
                );
            },
        });
    }
});
