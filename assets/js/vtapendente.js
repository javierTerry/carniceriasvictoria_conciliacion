$(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    const id = urlParams.get('id');
    const branch = urlParams.get('branch');
    
    // Store items per payment method
    let managedItems = {};

    if (id && branch) {
        viewTicketHTML(id, branch);
    } else {
        $('#ticket_preview').html('<div class="alert alert-danger">Error: ID o Sucursal no proporcionados.</div>');
    }

    // Delegate event for copy buttons
    $(document).on('click', '.btn-copy-item', function() {
        const fpay_id = $('#fpay_selector').val();
        const fpay_name = $('#fpay_selector option:selected').data('name');

        if (!fpay_id) {
            Swal.fire({
                title: 'Atención',
                text: 'Seleccione un método de pago antes de copiar partidas.',
                icon: 'warning',
                confirmButtonColor: '#34495e'
            });
            return;
        }

        const itemData = {
            name: $(this).data('name'),
            qty: parseFloat($(this).data('qty')),
            price: parseFloat($(this).data('price')),
            amount: parseFloat($(this).data('amount'))
        };

        addItemToManagement(fpay_id, fpay_name, itemData);
        
        // Visual feedback
        $(this).removeClass('btn-primary').addClass('btn-success').find('i').removeClass('fa-copy').addClass('fa-check');
        setTimeout(() => {
            $(this).removeClass('btn-success').addClass('btn-primary').find('i').removeClass('fa-check').addClass('fa-copy');
        }, 1000);
    });

    // Add manual amount
    $('#btn_add_manual_amount').on('click', function() {
        const fpay_id = $('#fpay_selector').val();
        const fpay_name = $('#fpay_selector option:selected').data('name');
        const amount = parseFloat($('#manual_amount').val());
        const ticketTotal = parseFloat($('#raw_ticket_total').val()) || 0;

        // Calculate current accumulated total
        let accumulated = 0;
        Object.values(managedItems).forEach(group => {
            group.items.forEach(item => {
                accumulated += item.amount;
            });
        });

        // Validation: No more additions if pending is already 0
        if (ticketTotal - accumulated <= 0.01) {
            Swal.fire({
                title: 'Atención',
                text: 'El saldo pendiente ya es cero. No se pueden agregar más montos.',
                icon: 'warning',
                confirmButtonColor: '#34495e'
            });
            return;
        }

        if (!fpay_id) {
            Swal.fire({
                title: 'Atención',
                text: 'Seleccione un método de pago.',
                icon: 'warning',
                confirmButtonColor: '#34495e'
            });
            return;
        }

        if (isNaN(amount) || amount <= 0) {
            Swal.fire({
                title: 'Atención',
                text: 'Ingrese un monto válido mayor a 0.',
                icon: 'warning',
                confirmButtonColor: '#34495e'
            });
            return;
        }

        if (amount > (ticketTotal + 0.01)) {
            Swal.fire({
                title: 'Atención',
                text: 'El monto no puede ser mayor al total del ticket ($' + ticketTotal.toFixed(2) + ').',
                icon: 'warning',
                confirmButtonColor: '#34495e'
            });
            return;
        }

        const itemData = {
            name: 'Monto seccionado',
            qty: 1,
            price: amount,
            amount: amount
        };

        addItemToManagement(fpay_id, fpay_name, itemData);
        $('#manual_amount').val(''); // Clear input
    });

    function addItemToManagement(fpay_id, fpay_name, item) {
        if (!managedItems[fpay_id]) {
            managedItems[fpay_id] = {
                name: fpay_name,
                items: []
            };
        }
        
        managedItems[fpay_id].items.push(item);
        renderManagementTables();
    }

    function renderManagementTables() {
        $('#empty_management_msg').hide();
        let html = '';
        let totalAccumulated = 0;
        
        // Sort keys to maintain consistent order
        const sortedKeys = Object.keys(managedItems).sort();
        
        sortedKeys.forEach(fpay_id => {
            const group = managedItems[fpay_id];
            let subtotal = 0;
            
            html += `
            <div class="management-table-container" style="margin-bottom: 30px; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <div style="background: #34495e; color: white; padding: 10px 15px; font-weight: bold; display: flex; justify-content: space-between; align-items: center;">
                    <span style="text-transform: uppercase; letter-spacing: 1px;"><i class="fa fa-credit-card"></i> ${group.name}</span>
                    <button class="btn btn-link btn-xs" style="color: #ff9f9f;" onclick="removeTable('${fpay_id}')" title="Eliminar Tabla">
                        <i class="fa fa-trash"></i> Eliminar Sección
                    </button>
                </div>
                <table class="table table-condensed" style="margin-bottom: 0;">
                     <thead style="background: #f9f9f9; border-bottom: 2px solid #eee;">
                        <tr>
                            <th style="padding: 10px;">Producto</th>
                            <th class="text-right" style="padding: 10px;">Cant</th>
                            <th class="text-right" style="padding: 10px;">Precio</th>
                            <th class="text-right" style="padding: 10px;">Total</th>
                            <th style="width: 40px;"></th>
                        </tr>
                     </thead>
                     <tbody>`;
            
            group.items.forEach((item, index) => {
                subtotal += item.amount;
                totalAccumulated += item.amount;
                html += `
                <tr style="border-bottom: 1px solid #f4f4f4;">
                    <td style="padding: 8px 10px;">${item.name}</td>
                    <td class="text-right" style="padding: 8px 10px;">${item.qty.toFixed(3)}</td>
                    <td class="text-right" style="padding: 8px 10px;">${item.price.toFixed(2)}</td>
                    <td class="text-right" style="padding: 8px 10px; font-weight: 600;">$${item.amount.toFixed(2)}</td>
                    <td class="text-center" style="padding: 8px 10px;">
                        <button class="btn btn-link btn-xs text-danger" onclick="removeItem('${fpay_id}', ${index})" title="Quitar">
                            <i class="fa fa-times"></i>
                        </button>
                    </td>
                </tr>`;
            });
            
            html += `
                     </tbody>
                     <tfoot style="background: #fafffe; font-weight: bold; border-top: 2px solid #eee;">
                        <tr>
                            <td colspan="3" class="text-right" style="padding: 12px 10px; font-size: 1.1em;">TOTAL ${group.name.toUpperCase()}:</td>
                            <td class="text-right" style="padding: 12px 10px; color: #34495e; font-size: 1.3em;">$${subtotal.toFixed(2)}</td>
                            <td></td>
                        </tr>
                     </tfoot>
                </table>
            </div>`;
        });
        
        $('#payment_method_tables').html(html);
        updateSummary(totalAccumulated);

        if (sortedKeys.length === 0) {
            $('#empty_management_msg').show();
        }
    }

    function updateSummary(accumulated) {
        const ticketTotal = parseFloat($('#raw_ticket_total').val()) || 0;
        const pending = ticketTotal - accumulated;

        $('#ticket_total_val').text(`$${ticketTotal.toFixed(2)}`);
        $('#accumulated_total_val').text(`$${accumulated.toFixed(2)}`);
        $('#pending_total_val').text(`$${pending.toFixed(2)}`);
        
        // Visual warning if pending is negative (over-categorization)
        if (pending < -0.01) {
            $('#pending_total_val').css('color', '#d9534f').attr('title', 'Cuidado: Excede el total del ticket');
        } else {
            $('#pending_total_val').css('color', '#e74c3c').removeAttr('title');
        }
    }

    window.removeItem = function(fpay_id, index) {
        managedItems[fpay_id].items.splice(index, 1);
        if (managedItems[fpay_id].items.length === 0) {
            delete managedItems[fpay_id];
        }
        renderManagementTables();
    };

    window.removeTable = function(fpay_id) {
        Swal.fire({
            title: '¿Eliminar sección?',
            text: "Se borrarán todas las partidas copiadas a este método de pago.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
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
            
            // Extract and set initial total from the hidden input we added
            setTimeout(() => {
                const total = parseFloat($('#raw_ticket_total', '#ticket_preview').val()) || 0;
                $('#ticket_total_val').text(`$${total.toFixed(2)}`);
                $('#pending_total_val').text(`$${total.toFixed(2)}`);
                // Move the hidden input to a safer place in the main document if needed
                // for now just make sure we can find it
                if ($('#raw_ticket_total', '#ticket_preview').length > 0) {
                    const rawVal = $('#raw_ticket_total', '#ticket_preview').val();
                    if ($('#main_raw_total').length === 0) {
                        $('body').append(`<input type="hidden" id="raw_ticket_total" value="${rawVal}">`);
                    } else {
                        $('#raw_ticket_total').val(rawVal);
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
