<?php
/**
 * cust.php
 * Catálogo de Clientes - CRUD moderno (Vista simplificada sin pestañas)
 */
$title = "Catálogo de Clientes | ConsolidaCión";
include "head.php";
include "sidebar.php";
?>

<style>
    .has-error .form-control {
        border-color: #a94442 !important;
        box-shadow: inset 0 1px 1px rgba(0,0,0,.075), 0 0 8px rgba(169, 68, 66, .6) !important;
        background-color: #f2dede !important;
    }
    .has-error label {
        color: #a94442 !important;
    }
</style>

<div class="right_col" role="main">
    <div class="">
        <div class="page-title">
            <div class="title_left">
                <h3>Catálogos <small>Gestión de Clientes</small></h3>
            </div>
        </div>

        <div class="clearfix"></div>

        <div class="row">
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="x_panel">
                    <div class="x_title">
                        <h2>Lista de Clientes</h2>
                        <ul class="nav navbar-right panel_toolbox">
                            <li>
                                <button type="button" class="btn btn-success btn-sm" onclick="openModalAdd()">
                                    <i class="fa fa-plus"></i> Nuevo Cliente
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
                                    <input type="text" class="form-control" id="q" placeholder="Nombre, RFC o Código..." onkeyup="load(1);">
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

<!-- Modal Cliente -->
<div class="modal fade" id="custModal" tabindex="-1" role="dialog" aria-labelledby="custModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form id="cust_form" class="form-horizontal" novalidate>
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="custModalLabel">Nuevo Cliente</h4>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                    <input type="hidden" id="cust_id" name="id">
                    
                    <!-- SECCIÓN FISCAL -->
                    <div class="row">
                        <div class="col-md-12">
                            <h5 style="border-bottom: 1px solid #eee; padding-bottom: 5px; color: #2A3F54;"><i class="fa fa-file-text"></i> Datos Fiscales</h5>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="rfc" class="col-sm-4 control-label">RFC*</label>
                                <div class="col-sm-8">
                                    <input type="text" class="form-control" id="rfc" name="rfc" maxlength="13" required style="text-transform: uppercase;" autocomplete="off">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="regimen_fiscal" class="col-sm-4 control-label">Reg. Fiscal*</label>
                                <div class="col-sm-8">
                                    <input type="text" class="form-control" id="regimen_fiscal" name="regimen_fiscal" placeholder="Ej. 601" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN DE IDENTIFICACIÓN -->
                    <div class="row">
                        <div class="col-md-12">
                            <h5 style="border-bottom: 1px solid #eee; padding-bottom: 5px; color: #2A3F54;"><i class="fa fa-user"></i> Identificación</h5>
                        </div>
                        
                        <!-- Campos para Persona Física (Se ocultan/muestran dinámicamente) -->
                        <div id="div_campos_fisica">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="nombre" class="col-sm-2 control-label">Nombre(s)*</label>
                                    <div class="col-sm-10">
                                        <input type="text" class="form-control persona-fisica-input" id="nombre" name="nombre">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="ap_paterno" class="col-sm-4 control-label">Ap. Paterno*</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control persona-fisica-input" id="ap_paterno" name="ap_paterno">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="ap_materno" class="col-sm-4 control-label">Ap. Materno*</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control persona-fisica-input" id="ap_materno" name="ap_materno">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="razon_social" class="col-sm-2 control-label">Razón Social*</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" id="razon_social" name="razon_social" required readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN DE CONTACTO -->
                    <div class="row">
                        <div class="col-md-12">
                            <h5 style="border-bottom: 1px solid #eee; padding-bottom: 5px; color: #2A3F54;"><i class="fa fa-envelope"></i> Contacto</h5>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="email" class="col-sm-4 control-label">Email*</label>
                                <div class="col-sm-8">
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="phone" class="col-sm-4 control-label">Teléfono</label>
                                <div class="col-sm-8">
                                    <input type="text" class="form-control" id="phone" name="phone">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN DIRECCIÓN -->
                    <div class="row">
                        <div class="col-md-12">
                            <h5 style="border-bottom: 1px solid #eee; padding-bottom: 5px; color: #2A3F54;"><i class="fa fa-map-marker"></i> Dirección</h5>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="calle" class="col-sm-2 control-label">Calle</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" id="calle" name="calle">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="noext" class="col-sm-4 control-label">Ext</label>
                                <div class="col-sm-8">
                                    <input type="text" class="form-control" id="noext" name="noext">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="noint" class="col-sm-4 control-label">Int</label>
                                <div class="col-sm-8">
                                    <input type="text" class="form-control" id="noint" name="noint">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="colonia" class="col-sm-4 control-label">Colonia</label>
                                <div class="col-sm-8">
                                    <input type="text" class="form-control" id="colonia" name="colonia">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="municipio" class="col-sm-4 control-label">Municipio</label>
                                <div class="col-sm-8">
                                    <input type="text" class="form-control" id="municipio" name="municipio">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="estado" class="col-sm-4 control-label">Estado</label>
                                <div class="col-sm-8">
                                    <input type="text" class="form-control" id="estado" name="estado">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="cp" class="col-sm-4 control-label">CP*</label>
                                <div class="col-sm-8">
                                    <input type="text" class="form-control" id="cp" name="cp" maxlength="5" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    </div><!-- Fin Row Dirección -->

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
<script type="text/javascript" src="js/cust.js?v=<?php echo time(); ?>"></script>
