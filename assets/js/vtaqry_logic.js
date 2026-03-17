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

function changeStatusPrompt(mov_id, cliente, monto, fname, status) {
    // Normalizamos los valores para evitar fallos por espacios o mayúsculas
    const normalizedFname = fname ? fname.toLowerCase().trim() : "";
    const isEfectivo = normalizedFname.includes('efectivo');
    const isActive = parseInt(status) === 1;

    // La funcionalidad de alerta solo se activa para pagos en Efectivo y Estatus Activo (1)
    if (!isEfectivo || !isActive) {
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
        confirmButtonColor: '#e74c3c', // Rojo corporativo/Premium
        cancelButtonColor: '#95a5a6',  // Gris apagado
        confirmButtonText: '<i class="fa fa-retweet" style="margin-right: 5px;"></i> Sí, pasar a Pendiente',
        cancelButtonText: '<i class="fa fa-times" style="margin-right: 5px;"></i> Cancelar',
        customClass: {
            popup: 'premium-swal-popup',
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-default'
        },
        buttonsStyling: false // Utilizamos nuestras clases de bootstrap/css
    }).then((result) => {
        if (result.isConfirmed) {
            // Aquí en el futuro se agregará la llamada AJAX para cambiar en BD.
            
            // Simulación de Toast de éxito
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });

            Toast.fire({
                icon: 'success',
                title: 'Estatus cambiado a Pendiente exitosamente'
            });
        }
    });
}
