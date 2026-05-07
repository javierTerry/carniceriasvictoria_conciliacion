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
            $("#resultados").html('<div class="alert alert-info">Procesando...</div>');
        },
        success: function(datos) {
            if (datos.success) {
                $("#resultados").html('<div class="alert alert-success">' + datos.message + '</div>');
                setTimeout(function() {
                    $("#prodModal").modal('hide');
                    $("#resultados").html('');
                }, 1500);
                load(1);
            } else {
                $("#resultados").html('<div class="alert alert-danger">' + datos.message + '</div>');
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
 * Elimina un producto previa confirmación.
 */
function deleteProduct(id) {
    if (confirm("\u00bfRealmente deseas eliminar este producto?")) {
        $.ajax({
            type: "POST",
            url: "ajax/prod_action.php",
            data: { action: 'delete', id: id },
            success: function(datos) {
                if (datos.success) {
                    load(1);
                } else {
                    alert(datos.message);
                }
            }
        });
    }
}
