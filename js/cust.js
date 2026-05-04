/**
 * js/cust.js
 * Lógica frontend corregida para el catálogo de clientes con validación visual unificada (Bulk)
 */

$(document).ready(function() {
    load(1);

    // Manejo del envío del formulario con validación en bloque
    $("#cust_form").submit(function(event) {
        event.preventDefault();
        if (validateForm()) {
            saveCust();
        }
    });

    // Lógica dinámica basada en el RFC
    $("#rfc").on('input', function() {
        var rfc = $(this).val().trim();
        handlePersonaLogic(rfc);
    });

    // Concatenación para Razón Social (Persona Física)
    $(".persona-fisica-input").on('input', function() {
        var rfc = $("#rfc").val().trim();
        if (rfc.length === 13) {
            updateRazonSocial();
        }
    });
});

/**
 * Maneja la visibilidad y atributos de los campos según el tipo de persona (RFC)
 */
function handlePersonaLogic(rfc) {
    var $divFisica = $("#div_campos_fisica");
    var $razonSocial = $("#razon_social");
    var $fisicaInputs = $(".persona-fisica-input");

    if (rfc.length === 13) {
        // PERSONA FÍSICA
        $divFisica.show();
        $razonSocial.attr('readonly', true);
        $fisicaInputs.prop('disabled', false).attr('required', true); 
        updateRazonSocial();
    } else if (rfc.length === 12) {
        // PERSONA MORAL
        $divFisica.hide();
        $razonSocial.attr('readonly', false);
        $fisicaInputs.val('').prop('disabled', true).removeAttr('required'); 
    } else {
        // RFC incompleto o inválido
        $divFisica.hide();
        $razonSocial.attr('readonly', false);
        $fisicaInputs.val('').prop('disabled', true).removeAttr('required');
    }
}

/**
 * Actualiza el campo Razón Social concatenando Nombre y Apellidos
 */
function updateRazonSocial() {
    var nombre = $("#nombre").val().trim();
    var apPaterno = $("#ap_paterno").val().trim();
    var apMaterno = $("#ap_materno").val().trim();
    
    var full = [nombre, apPaterno, apMaterno].filter(Boolean).join(' ');
    $("#razon_social").val(full);
}

/**
 * Validación unificada de todo el formulario en bloque (Visual)
 */
function validateForm() {
    var isValid = true;
    var messages = [];
    var $form = $("#cust_form");
    var rfc = $("#rfc").val().trim();
    var cp = $("#cp").val().trim();
    var email = $("#email").val().trim();

    // Limpiar estilos previos de error
    $form.find(".has-error").removeClass("has-error");

    // 1. Validar automáticamente todos los campos marcados como 'required' (Bulk Check)
    $form.find("[required]").each(function() {
        var $input = $(this);
        // Si el campo está deshabilitado, no lo validamos
        if ($input.prop('disabled')) return;

        if (!$input.val().trim()) {
            isValid = false;
            $input.closest(".form-group").addClass("has-error");
            
            var label = $input.closest(".form-group").find("label").text().replace('*', '').trim();
            messages.push("El campo <b>" + label + "</b> es obligatorio.");
        }
    });

    // 2. Validaciones de formato adicionales
    
    // RFC (Longitud)
    if (rfc && rfc.length !== 12 && rfc.length !== 13) {
        isValid = false;
        $("#rfc").closest(".form-group").addClass("has-error");
        messages.push("El <b>RFC</b> debe tener exactamente 12 o 13 caracteres.");
    }

    // CP (Exactamente 5 dígitos)
    if (cp && cp.length !== 5) {
        isValid = false;
        $("#cp").closest(".form-group").addClass("has-error");
        messages.push("El <b>CP</b> debe tener exactamente 5 dígitos.");
    }

    // Email (Formato)
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        isValid = false;
        $("#email").closest(".form-group").addClass("has-error");
        messages.push("El formato del <b>Email</b> es incorrecto.");
    }

    if (!isValid) {
        Swal.fire({
            title: 'Resumen de Campos Pendientes',
            html: '<div class="text-left" style="font-size: 14px;">Por favor corrige los siguientes errores:<br><br>' + messages.map(m => '• ' + m).join("<br>") + '</div>',
            icon: 'error',
            confirmButtonText: 'Corregir ahora'
        });
    }

    return isValid;
}

/**
 * Carga la tabla de clientes vía AJAX
 */
function load(page) {
    var q = $("#q").val();
    var per_page = $("#per_page").val();
    $("#loader").fadeIn('slow');
    $.ajax({
        url: 'ajax/cust.php?action=ajax&page=' + page + '&q=' + q + '&per_page=' + per_page,
        success: function(data) {
            $(".outer_div").html(data);
            $('#loader').hide();
        }
    });
}

/**
 * Abre el modal para agregar un nuevo cliente
 */
function openModalAdd() {
    $("#custModalLabel").text("Nuevo Cliente");
    $("#cust_id").val("");
    $("#cust_form")[0].reset();
    $(".has-error").removeClass("has-error");
    handlePersonaLogic(""); 
    $("#custModal").modal("show");
}

/**
 * Abre el modal para editar un cliente existente
 */
function editCust(data) {
    $("#custModalLabel").text("Editar Cliente");
    $("#cust_id").val(data.id);
    $("#rfc").val(data.rfc);
    $("#regimen_fiscal").val(data.regimen_fiscal);
    $("#nombre").val(data.nombre);
    $("#ap_paterno").val(data.ap_paterno);
    $("#ap_materno").val(data.ap_materno);
    $("#razon_social").val(data.razon_social);
    $("#email").val(data.email);
    $("#phone").val(data.phone);
    $("#calle").val(data.calle);
    $("#noext").val(data.noext);
    $("#noint").val(data.noint);
    $("#colonia").val(data.colonia);
    $("#municipio").val(data.municipio);
    $("#estado").val(data.estado);
    $("#cp").val(data.cp);

    $(".has-error").removeClass("has-error");
    handlePersonaLogic(data.rfc); 
    $("#custModal").modal("show");
}

/**
 * Guarda o actualiza un cliente vía AJAX
 */
function saveCust() {
    $("#btn_save").attr("disabled", true).text("Procesando...");

    // Limpiar errores previos de BD
    $(".has-error").removeClass("has-error");

    $.ajax({
        type: "POST",
        url: "ajax/cust.php?action=save",
        data: $("#cust_form").serialize(),
        dataType: "json",
        success: function(response) {
            $("#btn_save").attr("disabled", false).text("Guardar Datos");
            if (response.status === "success") {
                $("#custModal").modal("hide");
                Swal.fire('¡Éxito!', response.message, 'success');
                load(1);
            } else {
                // Si el backend indica un campo específico con error de BD, lo resaltamos
                if (response.field) {
                    var $field = $("#" + response.field);
                    $field.closest(".form-group").addClass("has-error");
                    // Enfocar el campo con error para ayudar al usuario
                    $field.focus();
                }
                
                Swal.fire({
                    title: 'Atención',
                    html: '<div class="text-left">' + response.message + '</div>',
                    icon: 'error'
                });
            }
        },
        error: function() {
            $("#btn_save").attr("disabled", false).text("Guardar Datos");
            Swal.fire('Error', 'Error crítico en el servidor.', 'error');
        }
    });
}

/**
 * Elimina un cliente con confirmación
 */
function deleteCust(id) {
    Swal.fire({
        title: '¿Deseas eliminar este cliente?',
        text: "La acción desactivará el registro.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                type: "POST",
                url: "ajax/cust.php?action=delete",
                data: { id: id },
                dataType: "json",
                success: function(response) {
                    if (response.status === "success") {
                        Swal.fire('Eliminado', response.message, 'success');
                        load(1);
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                }
            });
        }
    });
}
