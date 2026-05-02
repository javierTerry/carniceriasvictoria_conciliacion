function printTicket(id, branch) {
    $("#ticketFrame").attr("src", "action/vtaticket.php?xyz=" + id + "&branch=" + branch);
    $("#ticketModal").modal("show");
}

function viewTicketHTML(id, branch) {
    $("#htmlTicketBody").html(
        '<div class="text-center"><img src="./images/ajax-loader.gif"> Cargando...</div>',
    );
    $("#htmlTicketModal").modal("show");
    $.ajax({
        url: "ajax/vta_html_ticket.php",
        type: "GET",
        data: { id: id, branch: branch },
        success: function (response) {
            $("#htmlTicketBody").html(response);
        },
        error: function () {
            $("#htmlTicketBody").html(
                '<div class="alert alert-danger">Error al cargar el ticket.</div>',
            );
        },
    });
}

function changeStatusPrompt(mov_id, branch, cliente, monto, fname, status) {
    const isActive = parseInt(status) === 1;
// turbo
    // La funcionalidad de alerta solo se activa para Estatus Activo (1)
    if (!isActive) {
        return; 
    }

    Swal.fire({
        title: '<strong style="color: #1a2732; font-size: 24px;">¿Pasar a Pendiente?</strong>',
        html: `
            <div style="text-align: left; font-size: 14px; background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #eee;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 12px; border-bottom: 1px solid #f0f0f0; padding-bottom: 8px;">
                    <span style="color: #73879C; font-weight: 600; text-transform: uppercase; font-size: 12px;">Ticket</span>
                    <strong style="color: #1a2732; font-size: 16px;">#${mov_id}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 12px; border-bottom: 1px solid #f0f0f0; padding-bottom: 8px;">
                    <span style="color: #73879C; font-weight: 600; text-transform: uppercase; font-size: 12px;">Sucursal</span>
                    <strong style="color: #34495E; font-size: 14px;">${branch}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 12px; border-bottom: 1px solid #f0f0f0; padding-bottom: 8px;">
                    <span style="color: #73879C; font-weight: 600; text-transform: uppercase; font-size: 12px;">Cliente</span>
                    <strong style="color: #1a2732; font-size: 15px;">${cliente}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 12px; border-bottom: 1px solid #f0f0f0; padding-bottom: 8px;">
                    <span style="color: #73879C; font-weight: 600; text-transform: uppercase; font-size: 12px;">Método</span>
                    <span class="label" style="background-color: #34495E; color: white; padding: 4px 10px; border-radius: 4px; font-size: 12px;">${fname}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
                    <span style="color: #1a2732; font-weight: bold; font-size: 16px;">Total</span>
                    <strong style="color: #26B99A; font-size: 24px;">$${monto}</strong>
                </div>
            </div>
            <p style="margin-top: 20px; font-size: 15px; color: #555;">¿Estás seguro de cambiar el estatus de este ticket a <b>Pendiente</b>?</p>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#95a5a6',
        confirmButtonText: '<i class="fa fa-retweet" style="margin-right: 5px;"></i> Sí, pasar a Pendiente',
        cancelButtonText: '<i class="fa fa-times" style="margin-right: 5px;"></i> Cancelar',
        customClass: {
            popup: 'premium-swal-popup',
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-default'
        },
        buttonsStyling: false,
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return $.ajax({
                url: 'ajax/change_status.php',
                type: 'POST',
                data: {
                    mov_id: mov_id,
                    branch: branch,
                    status: 2 // Pendiente
                },
                dataType: 'json'
            }).done(response => {
                if (!response.success) {
                    Swal.showValidationMessage(`Error: ${response.message}`);
                }
                return response;
            }).fail(() => {
                Swal.showValidationMessage('Error en el servidor. Intente más tarde.');
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed && result.value.success) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });

            Toast.fire({
                icon: 'success',
                title: 'Estatus cambiado a Pendiente exitosamente'
            });

            // Recargamos los datos de la tabla
            if (typeof load === 'function') {
                load(1);
            }
        }
    });
}

function agruparTicket(mov_id, branch, monto, cust_id, cliente) {
    // 1. Obtener grupos activos (opcionalmente filtrados por cliente en el futuro, por ahora validamos en acción)
    $.ajax({
        url: 'ajax/agrupar_tickets_action.php',
        type: 'GET',
        data: { action: 'get_groups', branch: branch, cust_id: cust_id },
        dataType: 'json',
        success: function(response) {
            if (!response.success) {
                Swal.fire('Error', response.message, 'error');
                return;
            }

            let groupOptions = '<option value="new">-- Crear Nuevo Grupo --</option>';
            response.groups.forEach(g => {
                groupOptions += `<option value="${g.id}">${g.name} (Suma: $${parseFloat(g.total_tickets_amount).toFixed(2)} | Depósito: $${parseFloat(g.deposit_amount).toFixed(2)})</option>`;
            });

            Swal.fire({
                title: 'Agrupar Tickets - ' + cliente,
                html: `
                    <div style="text-align: left;">
                        <div class="form-group">
                            <label>Seleccionar Grupo:</label>
                            <select id="swal_group_id" class="form-control">
                                ${groupOptions}
                            </select>
                        </div>
                        <div id="new_group_fields">
                            <div class="form-group">
                                <label>Nombre del Grupo:</label>
                                <input type="text" id="swal_group_name" class="form-control" placeholder="Ej: Pago Global Cliente X">
                            </div>
                            <div class="form-group">
                                <label>Monto de Depósito (Manual):</label>
                                <input type="number" id="swal_deposit_amount" class="form-control" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Agrupar',
                cancelButtonText: 'Cancelar',
                didOpen: () => {
                    const groupSelect = document.getElementById('swal_group_id');
                    const newGroupFields = document.getElementById('new_group_fields');
                    groupSelect.onchange = () => {
                        newGroupFields.style.display = groupSelect.value === 'new' ? 'block' : 'none';
                    };
                },
                preConfirm: () => {
                    const group_id = document.getElementById('swal_group_id').value;
                    const group_name = document.getElementById('swal_group_name').value;
                    const deposit_amount = document.getElementById('swal_deposit_amount').value;

                    if (group_id === 'new') {
                        if (!group_name) {
                            Swal.showValidationMessage('El nombre del grupo es obligatorio');
                            return false;
                        }
                        if (!deposit_amount || parseFloat(deposit_amount) <= 0) {
                            Swal.showValidationMessage('El monto de depósito debe ser mayor a 0');
                            return false;
                        }
                    }

                    return {
                        group_id: group_id,
                        group_name: group_name,
                        deposit_amount: deposit_amount,
                        mov_id: mov_id,
                        amount: monto,
                        branch: branch,
                        cust_id: cust_id
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const data = result.value;
                    data.action = 'add_to_group';

                    $.ajax({
                        url: 'ajax/agrupar_tickets_action.php',
                        type: 'POST',
                        data: data,
                        dataType: 'json',
                        success: function(res) {
                            if (res.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Agrupación Exitosa!',
                                    html: `
                                        <div style="text-align: left; font-size: 14px;">
                                            <p>El ticket <b>#${res.mov_id}</b> ha sido asignado al grupo: <br><b>${res.group_name}</b></p>
                                            <hr>
                                            <p><b>Monto Depósito:</b> $${parseFloat(res.deposit_amount).toFixed(2)}</p>
                                            <p><b>Acumulado Tickets:</b> $${parseFloat(res.total_tickets_amount).toFixed(2)}</p>
                                        </div>
                                    `,
                                    confirmButtonText: 'Entendido'
                                }).then(() => {
                                    if (typeof load === 'function') load(window.current_page || 1);
                                });
                            } else {
                                Swal.fire('Error', res.message, 'error');
                            }
                        }
                    });
                }
            });
        }
    });
}
