$(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    const id = urlParams.get('id');
    const branch = urlParams.get('branch');
    
    // Store items per payment method
    let managedItems = {};
    // Pool of available items from the ticket
    let availableItems = [];

    if (id && branch) {
        viewTicketHTML(id, branch);
    } else {
        $('#ticket_preview').html('<div class="alert alert-danger">Error: ID o Sucursal no proporcionados.</div>');
    }

    // Add manual amount with automatic item selection (Greedy)
    $('#btn_add_manual_amount').on('click', function() {
        const fpay_selector = $('#fpay_selector');
        const fpay_id = fpay_selector.val();
        const fpay_name = $('#fpay_selector option:selected').data('name');
        const amountInput = $('#manual_amount');
        let amountToSection = parseFloat(amountInput.val());
        const ticketTotal = parseFloat($('#global_ticket_total').val()) || 0;

        // 1 & 2. Validation: Payment Method and Amount (Combined check)
        let validationErrors = [];
        if (!fpay_id) {
            validationErrors.push("Seleccione un método de pago.");
        }
        if (isNaN(amountToSection) || amountToSection <= 0) {
            validationErrors.push("Ingrese un monto válido mayor a 0.");
        }

        if (validationErrors.length > 0) {
            Swal.fire({
                title: 'Atención',
                html: `<ul style="margin-top:10px;">${validationErrors.map(err => `<li style="text-align:left; margin-bottom:5px;">${err}</li>`).join('')}</ul>`,
                icon: 'warning',
                confirmButtonColor: '#34495e'
            });
            return;
        }

        // Calculate current accumulated total in management panel
        let currentAccumulated = 0;
        Object.values(managedItems).forEach(group => {
            group.items.forEach(item => currentAccumulated += item.amount);
        });

        const pendingBalance = ticketTotal - currentAccumulated;

        // 3. Validation: Pending Balance Check
        if (pendingBalance <= 0.009) {
            Swal.fire({ 
                title: 'Venta Completada', 
                text: 'El total de la venta ya ha sido cubierto por los métodos de pago agregados.', 
                icon: 'info', 
                confirmButtonColor: '#34495e' 
            });
            return;
        }

        // 4. Validation: Amount does not exceed total/pending
        if (amountToSection > (pendingBalance + 0.01)) {
            Swal.fire({ 
                title: 'Monto Excedido', 
                text: 'El monto ingresado ($' + amountToSection.toFixed(2) + ') no puede superar el saldo pendiente actual ($' + pendingBalance.toFixed(2) + ').', 
                icon: 'warning', 
                confirmButtonColor: '#e74c3c' 
            });
            return;
        }

        // Add a DUMMY entry for now as requested
        const dummyItem = {
            name: `Abono a ${fpay_name}`,
            qty: 1,
            price: amountToSection,
            amount: amountToSection
        };

        addItemToManagement(fpay_id, fpay_name, dummyItem);

        amountInput.val('');
        renderManagementTables();
        
        // Success micro-feedback
        const toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });
        toast.fire({
            icon: 'success',
            title: `Se agregaron $${amountToSection.toFixed(2)} a ${fpay_name}`
        });
    });

    function addItemToManagement(fpay_id, fpay_name, item) {
        if (!managedItems[fpay_id]) {
            managedItems[fpay_id] = {
                name: fpay_name,
                items: []
            };
        }
        managedItems[fpay_id].items.push(item);
    }

    function renderManagementTables() {
        $('#empty_management_msg').hide();
        let html = '';
        let totalAccumulated = 0;
        const sortedKeys = Object.keys(managedItems).sort();
        
        sortedKeys.forEach(fpay_id => {
            const group = managedItems[fpay_id];
            let subtotal = 0;
            
            html += `
            <div class="management-table-container" style="margin-bottom: 15px; border: 1px solid #e1e8ed; border-radius: 4px; overflow: hidden; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                <div style="background: #f8f9fa; color: #34495e; padding: 6px 10px; font-weight: bold; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e1e8ed;">
                    <span style="text-transform: uppercase; letter-spacing: 0.5px; font-size: 10px; color: #73879C;">
                        <i class="fa fa-credit-card" style="color: #26B99A; margin-right: 5px;"></i> ${group.name}
                    </span>
                    <button class="btn btn-link btn-xs" style="color: #d9534f; text-decoration: none; padding: 0; font-size: 10px; height: auto;" onclick="removeTable('${fpay_id}')" title="Eliminar Sección">
                        <i class="fa fa-trash"></i> Eliminar
                    </button>
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
                        <button class="btn btn-default btn-xs" style="border-radius: 50%; color: #d9534f; border-color: #f2dede; padding: 0 4px; font-size: 9px;" onclick="removeItem('${fpay_id}', ${index})" title="Quitar">
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

    function updateSummary(accumulated) {
        const ticketTotal = parseFloat($('#global_ticket_total').val()) || 0;
        const pending = ticketTotal - accumulated;

        $('#ticket_total_val').text(`$${ticketTotal.toFixed(2)}`);
        $('#accumulated_total_val').text(`$${accumulated.toFixed(2)}`);
        $('#pending_total_val').text(`$${Math.max(0, pending).toFixed(2)}`);
        
        const btnAdd = $('#btn_add_manual_amount');
        if (pending <= 0.009) {
            $('#pending_total_val').css('color', '#26B99A'); // Green for zero/covered
            btnAdd.html('<i class="fa fa-check"></i> Completar').removeClass('btn-primary').addClass('btn-success');
        } else {
            btnAdd.html('<i class="fa fa-plus"></i> Agregar').removeClass('btn-success').addClass('btn-primary');
            if (pending < -0.01) {
                $('#pending_total_val').css('color', '#d9534f').attr('title', 'Cuidado: Excede el total del ticket');
            } else {
                $('#pending_total_val').css('color', '#e74c3c').removeAttr('title');
            }
        }
    }

    window.removeItem = function(fpay_id, index) {
        const item = managedItems[fpay_id].items[index];
        // Return to pool so it can be re-allocated later
        if (item.originalIndex !== undefined && availableItems[item.originalIndex]) {
            availableItems[item.originalIndex].remainingAmount += item.amount;
            availableItems[item.originalIndex].remainingQty += item.qty;
        }

        managedItems[fpay_id].items.splice(index, 1);
        if (managedItems[fpay_id].items.length === 0) {
            delete managedItems[fpay_id];
        }
        renderManagementTables();
    };

    window.removeTable = function(fpay_id) {
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
                managedItems[fpay_id].items.forEach(item => {
                    if (item.originalIndex !== undefined && availableItems[item.originalIndex]) {
                        availableItems[item.originalIndex].remainingAmount += item.amount;
                        availableItems[item.originalIndex].remainingQty += item.qty;
                    }
                });
                delete managedItems[fpay_id];
                renderManagementTables();
            }
        });
    };
});

function viewTicketHTML(id, branch) {
    $('#ticket_preview').html(
        '<div class="text-center" style="margin-top: 50px;"><img src="./images/ajax-loader.gif"> Cargando...</div>',
    );
    $.ajax({
        url: "ajax/vta_html_ticket.php",
        type: "GET",
        data: { id: id, branch: branch, manage: 1 },
        success: function (response) {
            $("#ticket_preview").html(response);
            
            // Parse the HTML table directly to avoid any PHP JSON encoding issues
            availableItems = [];
            try {
                $("#ticket_preview .ticket-table tbody tr").each(function() {
                    const qtyStr = $(this).find("td").eq(0).text().trim().replace(/,/g, '');
                    const nameStr = $(this).find("td").eq(1).text().trim();
                    const priceStr = $(this).find("td").eq(2).text().trim().replace(/,/g, '');
                    const amountStr = $(this).find("td").eq(3).text().trim().replace(/,/g, '');
                    
                    const qty = parseFloat(qtyStr);
                    const price = parseFloat(priceStr);
                    const amount = parseFloat(amountStr);
                    
                    if (!isNaN(amount) && amount > 0) {
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
            } catch(e) {
                console.error("Error parsing ticket items from DOM:", e);
            }

            setTimeout(() => {
                const totalEl = $('#raw_ticket_total', '#ticket_preview');
                if (totalEl.length > 0) {
                    const totalVal = parseFloat(totalEl.val()) || 0;
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
