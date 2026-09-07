window.current_page = 1;

$(document).ready(function () {
	// Inicializar Select2 en los desplegables con clase select2-victoria
	if (typeof $.fn.select2 !== 'undefined') {
		$('.select2-victoria').select2({
			width: '100%'
		});
	}

	// Manejar submit del formulario de búsqueda para evitar recarga de página
	$('#datos_cotizacion').on('submit', function (e) {
		e.preventDefault();
		load(1);
	});

	// Carga inicial de datos
	load(1);
});

function load(page) {
	window.current_page = page;
	var q = $.trim($("#q").val() || "");
	var fecha = $("#fecha_filter").val() || "";
	var monto = $.trim($("#monto_filter").val() || "");
	var per_page = $("#per_page").val() || 25;
	var branch = $("#branch_filter").val() || "";
	var fpay = $("#fpay_filter").val() || "";
	var status = $("#status_filter").val() || "";

	var params = {
		action: 'ajax',
		page: page,
		q: q,
		fecha: fecha,
		monto: monto,
		per_page: per_page,
		branch: branch,
		fpay: fpay,
		status: status
	};

	$("#loader").fadeIn('slow');
	$.ajax({
		url: './ajax/vtaqry.php',
		type: 'GET',
		data: params,
		beforeSend: function () {
			$('#loader').html('<img src="./images/ajax-loader.gif"> Cargando...');
			$(".outer_div").css('opacity', '0.5');
		},
		success: function (data) {
			$(".outer_div").css('opacity', '1').html(data).fadeIn('slow');
			$('#loader').html('');
		},
		error: function () {
			$(".outer_div").css('opacity', '1').html('<div class="alert alert-danger">Error al cargar las ventas.</div>');
			$('#loader').html('');
		}
	});
}

function limpiarFiltros() {
	$("#q").val('');
	$("#fecha_filter").val('');
	$("#monto_filter").val('');
	
	if ($("#fpay_filter").length) {
		$("#fpay_filter").val('').trigger('change.select2');
	}

	// Si el selector de sucursal no es de solo lectura / oculto
	if ($("#branch_filter").is("select")) {
		var defaultBranch = $("#branch_filter").data('default') || 'all';
		$("#branch_filter").val(defaultBranch).trigger('change.select2');
	}

	if ($("#per_page").length) {
		$("#per_page").val('25').trigger('change.select2');
	}

	load(1);
}

function eliminar(id) {
	var q = $("#q").val();
	if (confirm("¿Realmente deseas cancelar esta venta?")) {
		$.ajax({
			type: "GET",
			url: "./ajax/vtaqry.php",
			data: { "id": id, "q": q },
			beforeSend: function () {
				$("#resultados").html("Mensaje: Cargando...");
			},
			success: function (datos) {
				$("#resultados").html(datos);
				var redirect = $("#resultados .alert").data('redirect');
				if (redirect) {
					window.location.href = redirect;
				} else {
					load(window.current_page || 1);
				}
			}
		});
	}
}


