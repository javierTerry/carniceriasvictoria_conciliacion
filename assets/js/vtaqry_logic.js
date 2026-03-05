function printTicket(id) {
    $("#ticketFrame").attr("src", "action/vtaticket.php?xyz=" + id);
    $("#ticketModal").modal("show");
}

function viewTicketHTML(id) {
    $("#htmlTicketBody").html(
        '<div class="text-center"><img src="./images/ajax-loader.gif"> Cargando...</div>',
    );
    $("#htmlTicketModal").modal("show");
    $.ajax({
        url: "ajax/vta_html_ticket.php",
        type: "GET",
        data: { id: id },
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
