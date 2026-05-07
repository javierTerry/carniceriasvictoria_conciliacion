<?php
/**
 * prod.php
 * Catálogo de Productos - CRUD
 */
$title = "Catálogo de Productos | ConsolidaCión";
include "head.php";
include "sidebar.php";
?>

<div class="right_col" role="main">
    <div class="">
        <div class="page-title">
            <div class="title_left">
                <h3>Cat&aacute;logos <small>Gesti&oacute;n de Productos</small></h3>
            </div>
        </div>

        <div class="clearfix"></div>

        <div class="row">
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="x_panel">
                    <div class="x_title">
                        <h2>Lista de Productos</h2>
                        <ul class="nav navbar-right panel_toolbox">
                            <li>
                                <button type="button" class="btn btn-success btn-sm" onclick="openModalAdd()">
                                    <i class="fa fa-plus"></i> Nuevo Producto
                                </button>
                            </li>
                        </ul>
                        <div class="clearfix"></div>
                    </div>

                    <div class="x_content">
                        <!-- Filtros -->
                        <form class="form-horizontal" role="form" id="search_form" onsubmit="event.preventDefault();">
                            <div class="form-group row">
                                <label for="q" class="col-sm-1 control-label">Buscar</label>
                                <div class="col-md-4 col-sm-4">
                                    <input type="text" class="form-control" id="q" placeholder="Descripci&oacute;n o Clave SAT..." onkeyup="load(1);">
                                </div>
                                <label for="per_page" class="col-sm-1 control-label">Ver</label>
                                <div class="col-md-2 col-sm-2">
                                    <select class="form-control" id="per_page" onchange="load(1);">
                                        <option value="10">10</option>
                                        <option value="25" selected>25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>
                                </div>
                            </div>
                        </form>

                        <!-- Tabla AJAX -->
                        <div id="loader" class="text-center" style="display:none;">
                            <img src="images/ajax-loader.gif"> Cargando...
                        </div>
                        <div id="resultados"></div>
                        <div class="outer_div"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Producto -->
<div class="modal fade" id="prodModal" tabindex="-1" role="dialog" aria-labelledby="prodModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="prod_form" class="form-horizontal">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="prodModalLabel">Nuevo Producto</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="prod_id" name="id">
                    <input type="hidden" name="action" value="save">
                    
                    <div class="form-group">
                        <label for="descripcion" class="col-sm-3 control-label">Descripci&oacute;n*</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control" id="descripcion" name="descripcion" required maxlength="100" autocomplete="off">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="clave_sat" class="col-sm-3 control-label">Clave SAT*</label>
                        <div class="col-sm-9">
                            <input type="number" class="form-control" id="clave_sat" name="clave_sat" required min="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="servicios" class="col-sm-3 control-label">Servicios</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control" id="servicios" name="servicios" maxlength="100">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="unidad_sat" class="col-sm-3 control-label">Unidad SAT</label>
                        <div class="col-sm-4">
                            <input type="text" class="form-control" id="unidad_sat" name="unidad_sat" maxlength="4">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="unidad" class="col-sm-3 control-label">Unidad</label>
                        <div class="col-sm-4">
                            <input type="text" class="form-control" id="unidad" name="unidad" maxlength="4">
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary" id="btn_save">Guardar Datos</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>
<script type="text/javascript" src="js/prod.js?v=<?php echo time(); ?>"></script>
