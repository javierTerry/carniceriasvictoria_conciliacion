/**
 * js/prod.js
 * Lógica frontend para el catálogo de productos.
 */

$(document).ready(function() {
    load(1);
});

/**
 * Carga la lista de productos vía AJAX.
 */
function load(page) {
    var q = $("#q").val();
    var per_page = $("#per_page").val();
    $("#loader").fadeIn('slow');
    $.ajax({
        url: 'ajax/prod_qry.php?action=ajax&page=' + page + '&q=' + q + '&per_page=' + per_page,
        beforeSend: function(objeto) {
            $('#loader').html('<img src="images/ajax-loader.gif"> Cargando...');
        },
        success: function(data) {
            $(".outer_div").html(data).fadeIn('slow');
            $('#loader').html('');
        }
    });
}

/**
 * Abre el modal para agregar un nuevo producto.
 */
function openModalAdd() {
    $("#prod_form")[0].reset();
    $("#prod_id").val('');
    $("#prodModalLabel").html("Nuevo Producto");
    $("#prodModal").modal('show');
    $("#resultados").html('');
}

/**
 * Maneja el envío del formulario de producto (Guardar/Actualizar).
 */
$("#prod_form").submit(function(event) {
    $('#btn_save').attr("disabled", true);
    var parametros = $(this).serialize();
    $.ajax({
        type: "POST",
        url: "ajax/prod_action.php",
        data: parametros,
        beforeSend: function(objeto) {
            // Mostrar un indicador de carga si se desea (o dejar vacío)
        },
        success: function(datos) {
            if (datos.success) {
                // Cerrar modal y recargar datos inmediatamente
                $("#prodModal").modal('hide');
                load(1);
                
                // Mostrar SweetAlert que se cierra en 2 segundos
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: datos.message,
                        showConfirmButton: false,
                        timer: 2000
                    });
                } else {
                    alert(datos.message);
                }
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: datos.message
                    });
                } else {
                    alert(datos.message);
                }
            }
            $('#btn_save').attr("disabled", false);
        },
        error: function() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error de comunicación con el servidor'
                });
            } else {
                alert('Error de comunicación con el servidor');
            }
            $('#btn_save').attr("disabled", false);
        }
    });
    event.preventDefault();
});

/**
 * Obtiene los datos de un producto y abre el modal para editar.
 */
function editProduct(id) {
    $.ajax({
        type: "POST",
        url: "ajax/prod_action.php",
        data: { action: 'get', id: id },
        success: function(datos) {
            if (datos.success) {
                $("#prod_id").val(datos.data.id);
                $("#descripcion").val(datos.data.descripcion);
                $("#clave_sat").val(datos.data.clave_sat);
                $("#servicios").val(datos.data.servicios);
                $("#unidad_sat").val(datos.data.unidad_sat);
                $("#unidad").val(datos.data.unidad);
                
                $("#prodModalLabel").html("Editar Producto");
                $("#prodModal").modal('show');
                $("#resultados").html('');
            } else {
                alert(datos.message);
            }
        }
    });
}

/**
 * Elimina un producto previa confirmación con SweetAlert.
 */
function deleteProduct(id, descripcion, clave_sat) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: '¿Confirmas la eliminación?',
            html: 'Se realizará el eliminado del producto:<br><b>' + descripcion + '</b><br>Clave SAT: ' + clave_sat,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                executeDelete(id);
            }
        });
    } else {
        if (confirm("¿Realmente deseas eliminar el producto: " + descripcion + "?")) {
            executeDelete(id);
        }
    }
}

/**
 * Función interna para ejecutar el AJAX de eliminación.
 */
function executeDelete(id) {
    $.ajax({
        type: "POST",
        url: "ajax/prod_action.php",
        data: { action: 'delete', id: id },
        success: function(datos) {
            if (datos.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Eliminado',
                        text: datos.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
                load(1);
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: datos.message
                    });
                } else {
                    alert(datos.message);
                }
            }
        },
        error: function() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error de comunicación al intentar eliminar'
                });
            } else {
                alert('Error de comunicación con el servidor');
            }
        }
    });
}
