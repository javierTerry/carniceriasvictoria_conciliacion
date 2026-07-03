<?php
ini_set('memory_limit', '512M');
$title = "Ver Facturas | Victoria Obrador y Carnicería";
include "head.php";
include "sidebar.php";
?>
<link rel="stylesheet" href="assets/css/vtaqry.css?v=<?php echo time(); ?>">
<div class="right_col" role="main"><!-- page content -->
    <div class="">
        <div class="page-title">
            <div class="clearfix"></div>
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="x_panel">
                    <div class="x_title">
                        <h2>Facturas Generadas</h2>
                        <div class="clearfix"></div>
                    </div>

                    <!-- form search -->
                    <form class="form-horizontal" role="form" id="datos_cotizacion" onsubmit="event.preventDefault();">
                        <div class="form-group row">
                            <label for="q" class="col-md-1 control-label">Buscar / UUID</label>
                            <div class="col-md-3">
                                <input type="text" class="form-control" id="q" placeholder="Folio, UUID o Mov ID..."
                                    onkeyup='load(1);'>
                            </div>

                            <input type="hidden" id="branch_filter" value="<?php echo isset($_GET['branch']) ? $_GET['branch'] : ''; ?>">

                            <label for="client_filter" class="col-md-1 control-label">Cliente</label>
                            <div class="col-md-3">
                                <input type="text" class="form-control" id="client_filter" placeholder="Nombre del cliente..."
                                    onkeyup='load(1);'>
                            </div>

                            <label for="per_page" class="col-md-1 control-label">Ver</label>
                            <div class="col-md-2">
                                <select class="form-control select-victoria" id="per_page" onchange="load(1);">
                                    <option value="10" >10</option>    
                                    <option value="25" selected>25</option>
                                    <option value="50">50</option>
                                    <option value="75">75</option>
                                    <option value="100">100</option>
                                </select>
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
                </div>
            </div>
        </div>
    </div>
</div><!-- /page content -->

<?php include "footer.php" ?>

<script>
    function load(page) {
        window.current_page = page;
        var q = $("#q").val();
        var per_page = $("#per_page").val();
        var branch = $("#branch_filter").val();
        var client = $("#client_filter").val();
        var parametros = {
            "action": "ajax",
            "page": page,
            "q": q,
            "per_page": per_page,
            "branch": branch,
            "client": client
        };
        $("#resultados").fadeIn('slow');
        $.ajax({
            url: 'ajax/facturas_ajax.php',
            data: parametros,
            beforeSend: function (objeto) {
                $("#resultados").html('<img src="./images/ajax-loader.gif"> Cargando...');
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

    function enviarCorreoFactura(id, email) {
        let textMsg = "Se enviará el XML y el PDF de esta factura al correo registrado del cliente en el catálogo.";
        if (email && email.trim() !== '') {
            textMsg = "Se enviará el XML y el PDF de esta factura";
            textTitle = "¿Enviar factura a <strong style='color:#3498db;'>"+ email +"</strong>?";
        } else {
            textMsg = "Se enviará el XML y el PDF de esta factura al correo registrado en el catálogo (no se encontró correo configurado).";
            textTitle = "¿Enviar factura a <strong style='color:#3498db;'>"+ email +"</strong>?";
        }

        Swal.fire({
            title: textTitle,
            html: textMsg,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#26B99A',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, enviar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Enviando Correo',
                    text: 'Por favor, espere un momento...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    allowEnterKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    type: "POST",
                    url: "ajax/facturas_ajax.php",
                    data: { action: "send_email", id: id },
                    dataType: "json",
                    success: function (response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                title: '¡Enviado!',
                                text: response.message,
                                icon: 'success',
                                confirmButtonColor: '#26B99A'
                            });
                        } else {
                            Swal.fire({
                                title: 'Atención',
                                html: response.message,
                                icon: 'warning',
                                confirmButtonColor: '#3085d6'
                            });
                        }
                    },
                    error: function (xhr, status, error) {
                        Swal.fire({
                            title: 'Error',
                            text: 'No se pudo procesar la solicitud. Intente nuevamente más tarde.',
                            icon: 'error',
                            confirmButtonColor: '#d33'
                        });
                    }
                });
            }
        });
    }

    function confirmarCancelacion(id, serie, folio, email) {
        let textMsg = "Esta acción cancelará el comprobante fiscal Serie: <strong>" + (serie || '') + "</strong> Folio: <strong>" + (folio || '') + "</strong> en Sinube y ante el SAT.<br><br>";
        if (email && email.trim() !== '') {
            textMsg += "Se enviará la notificación de cancelación a <strong style='color:#e74c3c;'>" + email + "</strong>.";
        } else {
            textMsg += "<span style='color:#e74c3c;'>El cliente no tiene un correo registrado, no se enviará notificación.</span>";
        }

        Swal.fire({
            title: '¿Confirmar Cancelación de CFDI?',
            html: textMsg,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, cancelar factura',
            cancelButtonText: 'No, regresar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Procesando Cancelación',
                    text: 'Por favor, espere un momento...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    allowEnterKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    type: "POST",
                    url: "ajax/facturas_ajax.php",
                    data: { action: "cancel_invoice", id: id },
                    dataType: "json",
                    success: function (response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                title: '¡Factura Cancelada!',
                                html: '<strong>Detalle Sinube:</strong> ' + response.sinube_message + '<br><strong>Notificación:</strong> ' + response.email_message,
                                icon: 'success',
                                confirmButtonColor: '#26B99A'
                            }).then(() => {
                                load(window.current_page || 1);
                            });
                        } else {
                            Swal.fire({
                                title: 'Atención / Fallo',
                                html: response.message,
                                icon: 'warning',
                                confirmButtonColor: '#3085d6'
                            });
                        }
                    },
                    error: function (xhr, status, error) {
                        Swal.fire({
                            title: 'Error',
                            text: 'No se pudo procesar la solicitud de cancelación. Intente nuevamente más tarde.',
                            icon: 'error',
                            confirmButtonColor: '#d33'
                        });
                    }
                });
            }
        });
    }

    $(document).ready(function () {
        load(1);
    });
</script>
