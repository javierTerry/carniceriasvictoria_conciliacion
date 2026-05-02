<?php
ini_set('memory_limit', '512M');
$title = "Tickets Agrupados | Victoria Obrador y Carnicería";
include "head.php";
include "sidebar.php";

$branch = $_GET['branch'] ?? '';
if (empty($branch)) {
    echo "<script>window.location.href='dashboard.php';</script>";
    exit;
}
?>
<link rel="stylesheet" href="assets/css/vtaqry.css?v=<?php echo time(); ?>">
<div class="right_col" role="main">
    <div class="">
        <div class="page-title">
            <div class="clearfix"></div>
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="x_panel">
                    <div class="x_title">
                        <h2>Tickets Agrupados - <span class="label label-primary"><?php echo $branch; ?></span></h2>
                        <div class="clearfix"></div>
                    </div>

                    <!-- form search -->
                    <form class="form-horizontal" role="form" id="datos_busqueda" onsubmit="event.preventDefault();">
                        <div class="form-group row">
                            <label for="q" class="col-md-1 control-label">Filtro</label>
                            <div class="col-md-3">
                                <input type="text" class="form-control" id="q" placeholder="Nombre o ID del grupo..." onkeyup='load(1);'>
                            </div>
                            <input type="hidden" id="branch_filter" value="<?php echo $branch; ?>">
                            
                            <label for="per_page" class="col-md-1 control-label">Ver</label>
                            <div class="col-md-2">
                                <select class="form-control" id="per_page" onchange="load(1);">
                                    <option value="10">10</option>
                                    <option value="25" selected>25</option>
                                    <option value="50">50</option>
                                </select>
                            </div>
                        </div>
                    </form>

                    <div class="x_content">
                        <div class="table-responsive">
                            <div id="loader"></div>
                            <div class='outer_div'></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para ver detalles del grupo -->
<div class="modal fade" id="groupDetailModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title">Detalles del Grupo</h4>
            </div>
            <div class="modal-body" id="groupDetailBody">
                <!-- Content via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
function load(page) {
    var q = $("#q").val();
    var per_page = $("#per_page").val();
    var branch = $("#branch_filter").val();
    $("#loader").fadeIn('slow');
    $.ajax({
        url: './ajax/vtaagrupados_ajax.php?action=ajax&page=' + page + '&q=' + q + '&per_page=' + per_page + '&branch=' + branch,
        beforeSend: function (objeto) {
            $('#loader').html('<img src="./images/ajax-loader.gif"> Cargando...');
        },
        success: function (data) {
            $(".outer_div").html(data).fadeIn('slow');
            $('#loader').html('');
        }
    })
}

function viewGroupDetails(group_id, branch) {
    $("#groupDetailBody").html('<div class="text-center"><img src="./images/ajax-loader.gif"> Cargando detalles...</div>');
    $("#groupDetailModal").modal('show');
    $.ajax({
        url: 'ajax/vtaagrupados_ajax.php',
        type: 'GET',
        data: { action: 'get_details', group_id: group_id, branch: branch },
        success: function(response) {
            $("#groupDetailBody").html(response);
        }
    });
}

function desagruparTicket(group_id, mov_id, branch) {
    if (confirm('¿Estás seguro de quitar este ticket del grupo? El ticket volverá a estatus Pendiente.')) {
        $.ajax({
            url: 'ajax/agrupar_tickets_action.php',
            type: 'POST',
            data: { action: 'ungroup_ticket', group_id: group_id, mov_id: mov_id, branch: branch },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    Swal.fire('Éxito', res.message, 'success');
                    viewGroupDetails(group_id, branch); // Recargar detalles
                    load(1); // Recargar tabla principal
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }
        });
    }
}

function facturarGrupo(group_id, branch) {
    window.location.href = `vtaagrupados_facturar.php?group_id=${group_id}&branch=${branch}`;
}

window.addEventListener('load', function() {
    load(1);
});
</script>

<?php include "footer.php" ?>
