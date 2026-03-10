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
