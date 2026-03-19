$(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    const id = urlParams.get('id');
    const branch = urlParams.get('branch');
    
    if (id && branch) {
        viewTicketHTML(id, branch);
    } else {
        $('#ticket_preview').html('<div class="alert alert-danger">Error: ID o Sucursal no proporcionados.</div>');
    }
});

function viewTicketHTML(id, branch) {
    $('#ticket_preview').html(
        '<div class="text-center" style="margin-top: 50px;"><img src="./images/ajax-loader.gif"> Cargando...</div>',
    );
    $.ajax({
        url: "ajax/vta_html_ticket.php",
        type: "GET",
        data: { id: id, branch: branch },
        success: function (response) {
            $("#ticket_preview").html(response);
        },
        error: function () {
            $("#ticket_preview").html(
                '<div class="alert alert-danger" style="margin-top: 50px;">Error al cargar el ticket.</div>',
            );
        },
    });
}
