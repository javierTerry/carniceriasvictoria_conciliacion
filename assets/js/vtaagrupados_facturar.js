$(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    const group_id = urlParams.get('group_id');
    const branch = urlParams.get('branch');
    
    let managedItems = {};
    let sectionCounter = 0;
    let availableItems = [];
    let currentTicketFcode = '01'; 

    if (group_id && branch) {
        viewTicketHTML(group_id, branch);
    } else {
        $('#ticket_preview').html('<div class="alert alert-danger">Error: Group ID o Sucursal no proporcionados.</div>');
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

    const parseNum = (val) => {
        if (typeof val === 'number') return val;
        if (!val) return 0;
        const cleaned = String(val).replace(/[^\d.-]/g, '');
        return parseFloat(cleaned) || 0;
    };

    $('#btn_add_manual_amount').on('click', function() {
        if ($(this).hasClass('btn-success')) {
            showCompletionSummary();
            return;
        }

        const fpayment_selector = $('#fpayment_selector');
        const forma_pago_id = fpayment_selector.val();
        const forma_pago_name = $('#fpayment_selector option:selected').data('name');
        const forma_pago_code = $('#fpayment_selector option:selected').data('code') || '01';
        const amountInput = $('#manual_amount');
        let amountToSection = parseNum(amountInput.val());
        const ticketTotal = parseNum($('#global_ticket_total').val());

        let validationErrors = [];
        if (!forma_pago_id) validationErrors.push("Seleccione una forma de pago.");
        if (amountToSection <= 0) validationErrors.push("Ingrese un monto válido mayor a 0.");
        if (availableItems.length === 0) validationErrors.push("No hay artículos consolidados en la vista previa.");
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

        let currentAccumulated = 0;
        Object.values(managedItems).forEach(group => {
            group.items.forEach(item => currentAccumulated += item.amount);
        });

        let pendingBalance = ticketTotal - currentAccumulated;

        if (pendingBalance <= 0.001) {
            Swal.fire({ title: 'Total Cubierto', text: 'El total consolidado ya ha sido asignado.', icon: 'info', confirmButtonColor: '#34495e' });
            return;
        }

        if (amountToSection > (pendingBalance + 0.001)) {
            amountToSection = pendingBalance;
        }

        let selections = [];
        let remainingToFind = amountToSection;

        for (let i = 0; i < availableItems.length; i++) {
            let item = availableItems[i];
            if (item.remainingAmount <= 0.001) continue;

            let takeAmount = Math.min(remainingToFind, item.remainingAmount);
            if (takeAmount <= 0.001) continue;

            let itemPrice = item.price > 0 ? item.price : 1; 
            let takeQty = (takeAmount / itemPrice);

            selections.push({
                name: item.name,
                qty: takeQty,
                price: itemPrice,
                amount: takeAmount,
                originalIndex: i
            });

            item.remainingAmount -= takeAmount;
            item.remainingQty -= takeQty;
            remainingToFind -= takeAmount;
            if (remainingToFind <= 0.001) break;
        }

        if (selections.length === 0) {
            Swal.fire({
                title: 'No se pudo asignar',
                text: 'El monto solicitado no pudo ser asignado al inventario consolidado.',
                icon: 'error',
                confirmButtonColor: '#34495e'
            });
            return;
        }

        sectionCounter++;
        const section_id = `section_${sectionCounter}`;
        selections.forEach(sel => {
            addItemToManagement(section_id, forma_pago_id, forma_pago_name, forma_pago_code, sel);
        });

        amountInput.val('');
        renderManagementTables();
        
        const toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });
        toast.fire({ icon: 'success', title: `Asignado con éxito ($${amountToSection.toFixed(2)})` });
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

        if (sortedKeys.length === 0) $('#empty_management_msg').show();
    }

    window.updateGroupField = function(section_id, field, value) {
        if (managedItems[section_id]) managedItems[section_id][field] = value;
    };

    function updateSummary(accumulated) {
        const ticketTotal = parseFloat($('#global_ticket_total').val()) || 0;
        const pending = ticketTotal - accumulated;

        $('#ticket_total_val').text(`$${ticketTotal.toFixed(2)}`);
        $('#accumulated_total_val').text(`$${accumulated.toFixed(2)}`);
        $('#pending_total_val').text(`$${Math.max(0, pending).toFixed(2)}`);
        
        const btnAdd = $('#btn_add_manual_amount');
        if (pending <= 0.009) {
            $('#pending_total_val').css('color', '#26B99A');
            btnAdd.html('<i class="fa fa-check"></i> Proceder a Facturar').removeClass('btn-primary').addClass('btn-success');
        } else {
            btnAdd.html('<i class="fa fa-plus"></i> Agregar Proporción').removeClass('btn-success').addClass('btn-primary');
            $('#pending_total_val').css('color', '#e74c3c');
        }
    }

    function showCompletionSummary() {
        let summaryHtml = '<div style="text-align: left; margin-top: 10px;">';
        let grandTotal = 0;
        
        Object.values(managedItems).forEach(group => {
            let subtotal = 0;
            group.items.forEach(item => subtotal += item.amount);
            grandTotal += subtotal;
            summaryHtml += `<div style="display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #eee;">
                                <span><strong>${group.name}</strong></span>
                                <span style="color: #26B99A; font-weight: bold;">$${subtotal.toFixed(2)}</span>
                            </div>`;
        });
        
        summaryHtml += `<div style="display: flex; justify-content: space-between; padding: 10px 0; font-size: 1.25em; border-top: 2px solid #34495e; margin-top: 10px;">
                            <span><strong>TOTAL GRUPO</strong></span>
                            <span style="color: #34495e; font-weight: 800;">$${grandTotal.toFixed(2)}</span>
                        </div></div>`;

        Swal.fire({
            title: '¿Confirmar Facturación de Grupo?',
            html: summaryHtml,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#26B99A',
            confirmButtonText: 'Sí, generar facturas'
        }).then((result) => {
            if (result.isConfirmed) completeTicketAction();
        });
    }

    function completeTicketAction() {
        let invoices = [];
        Object.keys(managedItems).forEach(section_id => {
            let group = managedItems[section_id];
            let subtotal = 0;
            let itemsPayload = group.items.map(item => ({ name: item.name, qty: item.qty, price: item.price, amount: item.amount }));
            group.items.forEach(item => subtotal += item.amount);
            
            // Obtener el nombre del grupo para usarlo como mov_id (regla de negocio)
            const finalMovId = $('#raw_group_name').val() || ("GRP-" + group_id);

            if (subtotal > 0) {
                invoices.push({
                    mov_id: finalMovId, 
                    branch: branch,
                    monto: subtotal,
                    metodo_pago: group.name,
                    forma_pago: group.code || '01',
                    metodo_pago_cfdi: group.metodo_pago_cfdi,
                    uso_cfdi: group.uso_cfdi,
                    // Datos del cliente seleccionado
                    cust_id: $('#client_selector').val(),
                    rfc_receptor: $('#client_rfc_receptor').val() || $('#client_rfc').val(),
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

        if (invoices.length === 0) return Swal.fire('Error', 'No hay montos asignados.', 'error');

        Swal.fire({ title: 'Procesando Grupo...', text: `Facturando 0 de ${invoices.length}...`, allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

        let completed = 0;
        let successCount = 0;
        let firstError = null;

        let ajaxPromises = invoices.map(invData => {
            return $.ajax({ url: "ajax/facturar_api.php", type: "POST", dataType: 'json', data: invData })
            .done(res => {
                completed++;
                Swal.update({ text: `Facturando ${completed} de ${invoices.length}...` });
                if (res && res.success) successCount++;
                else if (!firstError) firstError = res.message || "Error en Sinube";
            })
            .fail(() => {
                completed++;
                if (!firstError) firstError = "Fallo de conexión";
            });
        });

        $.when.apply($, ajaxPromises).always(function() {
            if (successCount === invoices.length) proceedWithLocalCompletion();
            else Swal.fire('Error Parcial', `Se facturaron ${successCount} de ${invoices.length}. Error: ${firstError}`, 'error');
        });
    }

    function proceedWithLocalCompletion() {
        $.ajax({
            url: "ajax/complete_group_ticket.php",
            type: "POST",
            data: { group_id: group_id, branch: branch },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    Swal.fire('¡Éxito!', 'Grupo facturado y cerrado correctamente.', 'success').then(() => {
                        window.location.href = `facturasqry.php?branch=${branch}`;
                    });
                } else {
                    Swal.fire('Error de Cierre', res.message, 'error');
                }
            }
        });
    }

    window.removeItem = function(section_id, index) {
        const item = managedItems[section_id].items[index];
        if (item.originalIndex !== undefined && availableItems[item.originalIndex]) {
            availableItems[item.originalIndex].remainingAmount += parseNum(item.amount);
            availableItems[item.originalIndex].remainingQty += parseNum(item.qty);
        }
        managedItems[section_id].items.splice(index, 1);
        if (managedItems[section_id].items.length === 0) delete managedItems[section_id];
        renderManagementTables();
    };

    window.removeTable = function(section_id) {
        managedItems[section_id].items.forEach(item => {
            if (item.originalIndex !== undefined && availableItems[item.originalIndex]) {
                availableItems[item.originalIndex].remainingAmount += parseNum(item.amount);
                availableItems[item.originalIndex].remainingQty += parseNum(item.qty);
            }
        });
        delete managedItems[section_id];
        renderManagementTables();
    };

    function viewTicketHTML(gid, br) {
        $('#ticket_preview').html('<div class="text-center" style="margin-top: 50px;"><img src="./images/ajax-loader.gif"> Procesando grupo...</div>');
        $.ajax({
            url: "ajax/vta_html_group_ticket.php",
            type: "GET",
            data: { group_id: gid, branch: br, manage: 1 },
            success: function (response) {
                $("#ticket_preview").html(response);
                
                // Actualizar descripción del grupo en la cabecera
                const groupName = $('#raw_group_name').val();
                if (groupName) {
                    $('#group_name_display').text(`(${groupName})`);
                }

                availableItems = [];
                try {
                    $("#ticket_preview .ticket-table tbody tr").each(function() {
                        const qty = parseNum($(this).find("td").eq(0).text());
                        const name = $(this).find("td").eq(1).text().trim();
                        const price = parseNum($(this).find("td").eq(2).text());
                        const amount = parseNum($(this).find("td").eq(3).text());
                        if (amount > 0) {
                            availableItems.push({ name: name, qty: qty, price: price, amount: amount, originalIndex: availableItems.length, remainingQty: qty, remainingAmount: amount });
                        }
                    });
                } catch(e) { console.error("Error parsing group items:", e); }

                setTimeout(() => {
                    const totalVal = parseNum($('#raw_ticket_total', '#ticket_preview').val());
                    if ($('#global_ticket_total').length === 0) $('body').append(`<input type="hidden" id="global_ticket_total" value="${totalVal}">`);
                    else $('#global_ticket_total').val(totalVal);
                    updateSummary(0);
                }, 100);
            },
            error: function () { $("#ticket_preview").html('<div class="alert alert-danger">Error al cargar consolidado.</div>'); }
        });
    }
});
