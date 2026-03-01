<?php
$title ="Ver Ventas Globales | ";
include "head.php";
include "sidebar.php";
?>
    <div class="right_col" role="main"><!-- page content -->
        <div class="">
            <div class="page-title"> <!-- recuadro A bajo de cabecera gris -->
                <div class="clearfix"></div> <!-- recuadro B bajo de recuadro A gris -->
                <div class="col-md-12 col-sm-12 col-xs-12">
                    <?php
                    //    include("modal/new_cust.php");  // + boton azul Agregar registro  y rutina GREGAR registros
                    //    include("modal/upd_cust.php");  // rutina editar registro
                     //   do_alert("esto es 1");
                    ?>
                    <div class="x_panel">
                        <div class="x_title">
                            <h2>Ventas (Global)</h2>  <!-- titulo de los registros -->
                            <ul class="nav navbar-right panel_toolbox">
                                <li><a class="collapse-link"><i class="fa fa-chevron-up"></i></a>
                                </li>  <!-- simbolo ordenar asc/desc -->
                                <li><a class="close-link"><i class="fa fa-close"></i></a>
                                </li>  <!-- simbolo cerrar ventana -->
                            </ul> <!-- define una lista desordenada -->
                            <div class="clearfix"></div> <!-- ajusta imagen al cuadro -->
                        </div>  <!-- titulo Proveedor -->
                        

                        <!-- form search -->
                        <form class="form-horizontal" role="form" id="datos_cotizacion">
                            <div class="form-group row">
                                <label for="q" class="col-md-2 control-label">Venta</label>
                                <div class="col-md-4">
                                    <input type="text" class="form-control" id="q" placeholder="Número, Cliente, Fecha" onkeyup='load(1);'>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-default" onclick='load(1);'>
                                        <span class="glyphicon glyphicon-search" ></span> Buscar</button>
                                    <!-- <span id="loader"></span> -->
                                </div>
                            </div>
                        </form>
                        <!-- end form search -->

                        <div class="x_content">
                            <div class="table-responsive">
                                <!-- ajax -->
                                    <div id="resultados"></div><!-- Carga los datos ajax -->
                                    <div class='outer_div'></div><!-- Carga los datos ajax -->
                                <!-- /ajax -->
                            </div>
                        </div>
                    </div>  <!-- recuadro D blanco debajo de recuadro C gris -->
                </div>  <!-- recuadro C bajo de recuadro B gris -->
            </div>
        </div>
    </div><!-- /page content -->

<?php include "footer.php" ?>


<script type="text/javascript" src="js/vtaqry.js"></script>


