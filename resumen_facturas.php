<?php
ini_set('memory_limit', '512M');
$title = "Resumen de Facturas | Victoria Obrador y Carnicería";
include "head.php";
include "sidebar.php";

// Default date range: last 30 days to today
$date_start = date('Y-m-d', strtotime('-30 days'));
$date_end = date('Y-m-d');
?>
<link rel="stylesheet" href="assets/css/vtaqry.css?v=<?php echo time(); ?>">
<style>
    /* Premium visual styles for the filter panel */
    .filter-card {
        background: #fcfcfc;
        border: 1px solid #eef2f5;
        border-radius: 6px;
        padding: 15px;
        margin-bottom: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.02);
    }
    .filter-label {
        font-weight: 600;
        color: #34495e;
        margin-bottom: 5px;
        display: block;
        font-size: 13px;
    }
    .form-control.input-victoria {
        border-radius: 4px;
        border: 1px solid #ccc;
        height: 34px;
        font-size: 13px;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    .form-control.input-victoria:focus {
        border-color: #26B99A;
        box-shadow: 0 0 5px rgba(38, 185, 154, 0.3);
    }
    .table-responsive {
        margin-top: 15px;
        border: none;
    }
</style>

<div class="right_col" role="main"><!-- page content -->
    <div class="">
        <div class="page-title">
            <div class="clearfix"></div>
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="x_panel">
                    <div class="x_title">
                        <h2>Resumen Conciliación de Facturas</h2>
                        <div class="clearfix"></div>
                    </div>

                    <div class="x_content">
                        <!-- Filters panel -->
                        <div class="filter-card">
                            <form class="form-horizontal" role="form" id="resumen_facturas_filter" onsubmit="event.preventDefault();">
                                <div class="row">
                                    <div class="col-md-2 col-sm-6 col-xs-12">
                                        <label for="branch_filter" class="filter-label">Sucursal</label>
                                        <select class="form-control input-victoria" id="branch_filter">
                                            <option value="all">Ver Todas</option>
                                            <option value="Obrador">Obrador</option>
                                            <option value="Victoria1">Victoria 1</option>
                                            <option value="Victoria2">Victoria 2</option>
                                            <option value="Produccion">Producción</option>
                                        </select>
                                    </div>

                                    <div class="col-md-2 col-sm-6 col-xs-12">
                                        <label for="date_start" class="filter-label">Fecha Inicio</label>
                                        <input type="date" class="form-control input-victoria" id="date_start" value="<?php echo $date_start; ?>">
                                    </div>

                                    <div class="col-md-2 col-sm-6 col-xs-12">
                                        <label for="date_end" class="filter-label">Fecha Fin</label>
                                        <input type="date" class="form-control input-victoria" id="date_end" value="<?php echo $date_end; ?>">
                                    </div>

                                    <div class="col-md-2 col-sm-6 col-xs-12">
                                        <label for="per_page" class="filter-label">Mostrar</label>
                                        <select class="form-control input-victoria" id="per_page">
                                            <option value="10">10 registros</option>
                                            <option value="25" selected>25 registros</option>
                                            <option value="50">50 registros</option>
                                            <option value="100">100 registros</option>
                                            <option value="all">Todos</option>
                                        </select>
                                    </div>

                                    <div class="col-md-2 col-sm-6 col-xs-12">
                                        <label class="filter-label">&nbsp;</label>
                                        <button type="button" class="btn btn-primary btn-block" style="height: 34px; font-size: 13px; font-weight: bold; background-color: #34495e; border-color: #34495e;" onclick="load(1);">
                                            <i class="fa fa-search"></i> Buscar
                                        </button>
                                    </div>

                                    <div class="col-md-2 col-sm-6 col-xs-12">
                                        <label class="filter-label">&nbsp;</label>
                                        <button type="button" class="btn btn-success btn-block" style="height: 34px; font-size: 13px; font-weight: bold; background-color: #26B99A; border-color: #26B99A;" onclick="exportarCSV();">
                                            <i class="fa fa-file-excel-o"></i> Exportar a CSV
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <!-- End Filters panel -->

                        <div class="table-responsive">
                            <!-- ajax response containers -->
                            <div id="resultados"></div><!-- Carga los datos ajax -->
                            <div class='outer_div'></div><!-- Carga los datos ajax -->
                            <!-- /ajax response containers -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div><!-- /page content -->

<?php include "footer.php" ?>

<script>
    function load(page) {
        window.current_page = page;
        var branch = $("#branch_filter").val();
        var date_start = $("#date_start").val();
        var date_end = $("#date_end").val();
        var per_page = $("#per_page").val();
        
        var parametros = {
            "action": "ajax",
            "page": page,
            "branch": branch,
            "date_start": date_start,
            "date_end": date_end,
            "per_page": per_page
        };
        
        $("#resultados").fadeIn('slow');
        $.ajax({
            url: 'ajax/resumen_facturas_ajax.php',
            data: parametros,
            beforeSend: function (objeto) {
                $("#resultados").html('<div class="text-center" style="padding: 20px;"><img src="./images/ajax-loader.gif"> Cargando datos...</div>');
            },
            success: function (data) {
                $(".outer_div").html(data).fadeIn('slow');
                $("#resultados").html("");
            },
            error: function (xhr, status, error) {
                $("#resultados").html('<div class="alert alert-danger">Error al cargar datos: ' + xhr.status + ' ' + error + '</div>');
            }
        });
    }

    function exportarCSV() {
        var branch = $("#branch_filter").val();
        var date_start = $("#date_start").val();
        var date_end = $("#date_end").val();
        
        var url = 'export_resumen_facturas_csv.php?branch=' + encodeURIComponent(branch) + 
                  '&date_start=' + encodeURIComponent(date_start) + 
                  '&date_end=' + encodeURIComponent(date_end);
                  
        window.location.href = url;
    }

    $(document).ready(function () {
        load(1);
    });
</script>
