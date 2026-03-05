var codecust = new Array();
var namecust = new Array();

var codeart = new Array();
var nameart = new Array();
var precios = new Array();

var codepxc = new Array();
var preciosxcli = new Array();

function setfocus(id) {
    if (id == 0) {
        document.head.cust_code.focus();
    } else {
        document.item.art_code.focus();
    }
}

function imprimirticket(id) {
    window.open("action/vtaticket.php?xyz=" + id);
}

function Valida(formulario) {
    if (formulario.art_name.value == "") {
        alert("Error: Se requiere Producto valido");
        formulario.art_code.focus();
        return false;
    }
    if (formulario.qty.value == "") {
        alert("Error: Se requiere Cantidad");
        formulario.qty.focus();
        return false;
    } else {
        if (isNaN(formulario.qty.value)) {
            alert("Error: Cantidad debe ser numerico.");
            formulario.qty.focus();
            return false;
        } else {
            if (formulario.qty.value == 0) {
                alert("Error: Cantidad debe ser mayor a cero.");
                formulario.qty.focus();
                return false;
            }
        }
    }

    if (formulario.price.value == "") {
        alert("Error: Se requiere Precio");
        formulario.price.focus();
        return false;
    } else {
        if (isNaN(formulario.price.value)) {
            alert("Error: Precio debe ser numerico.");
            formulario.price.focus();
            return false;
        } else {
            if (formulario.price.value == 0) {
                alert("Error: Precio debe ser mayor a cero.");
                formulario.price.focus();
                return false;
            }
        }
    }
}

function eliminaritem(id) {
    var answer = confirm("Quiere eliminar esta partida?");
    if (answer) {
        window.location.href = "action/delvtaitem.php?llave=" + id;
    }
}

function iraclientes() {
    window.location = "cust_find.php";
}

function iraarticulos() {
    window.location = "art_find.php";
}

function cargarClientes(valor) {
    var i = 0;
    document.getElementById("cust_name").value = "";
    for (i = 0; i < codecust.length; i++) {
        if (codecust[i] == valor) {
            document.getElementById("cust_name").value = namecust[i];
            document.getElementById("cust_num").value = valor;
        }
    }
}

function cargarArticulos(valor) {
    var i = 0;
    var cli = document.getElementById("cust_code").value;
    var key = cli + valor;
    document.getElementById("art_name").value = "";
    document.getElementById("price").value = "";
    for (i = 0; i < codeart.length; i++) {
        if (codeart[i] == valor) {
            document.getElementById("art_name").value = nameart[i];
            document.getElementById("price").value = parseFloat(
                precios[i],
            ).toFixed(2);
        }
    }
    for (i = 0; i < codepxc.length; i++) {
        if (codepxc[i] == key) {
            document.getElementById("price").value = preciosxcli[i];
        }
    }
}

function hdnrecibo() {
    var fp = document.getElementById("fpago");
    if (fp.value == 1) {
        $("#hcbx").show();
    } else {
        $("#hcbx").hide();
    }
}

function ChecaCambio(formulario) {
    var x = document.getElementById("cust_name").value;
    if (x == "") {
        alert("Error: Cliente no valido");
        document.getElementById("cust_code").focus();
        return false;
    }
    if (formulario.recibo.value == "" && formulario.fpago.value == 1) {
        alert("Error: Se requiere Recibo");
        formulario.recibo.focus();
        return false;
    } else {
        if (isNaN(formulario.recibo.value)) {
            alert("Error: Recibo debe ser numerico.");
            formulario.recibo.focus();
            return false;
        } else {
            if (formulario.recibo.value == 0 && formulario.fpago.value == 1) {
                alert("Error: Cantidad debe ser mayor a cero.");
                formulario.recibo.focus();
                return false;
            }
        }
    }
    if (
        parseFloat(formulario.total.value) > parseFloat(formulario.recibo.value)
    ) {
        alert("Error: Lo recibido no puede ser menor al total");
        formulario.recibo.focus();
        return false;
    }
}

function ChecaCambioAcuenta(formulario) {
    if (formulario.acuenta.value == "") {
        alert("Error: Se requiere A cuenta");
        formulario.acuenta.focus();
        return false;
    } else {
        if (isNaN(formulario.acuenta.value)) {
            alert("Error: A cuenta debe ser numerico.");
            formulario.acuenta.focus();
            return false;
        }
    }
    if (formulario.recibo.value == "") {
        alert("Error: Se requiere Recibo");
        formulario.recibo.focus();
        return false;
    } else {
        if (isNaN(formulario.recibo.value)) {
            alert("Error: Recibo debe ser numerico.");
            formulario.recibo.focus();
            return false;
        }
    }
    if (
        parseFloat(formulario.acuenta.value) >
        parseFloat(formulario.recibo.value)
    ) {
        alert("Error: Lo recibido no puede ser menor A cuenta");
        formulario.recibo.focus();
        return false;
    }
}
