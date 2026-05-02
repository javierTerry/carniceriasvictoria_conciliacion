<?php
date_default_timezone_set('America/Mexico_City');



//www gab demo
// Main Branch (Victoria Obrador)
$db_name = "carvic_sysvicobr";
$db_host = "172.17.0.1";
$db_user = "carvic_sysgp";
$db_password = "Notengopalabras.100";

// Branch 1
$db_name_1 = "carvic_branch1";
$db_host_1 = "172.17.0.1";
$db_user_1 = "carvic_sysgp";
$db_password_1 = "Notengopalabras.100";

// Branch 2
$db_name_2 = "carvic_branch2";
$db_host_2 = "172.17.0.1";
$db_user_2 = "carvic_sysgp";
$db_password_2 = "Notengopalabras.100";

// Helper to get all branches config
function getBranchesConfig()
{
    return [
        'Obrador' => [
            'host' => "172.17.0.1",
            'user' => "carvic_sysgp",
            'pass' => "Notengopalabras.100",
            'db' => "carvic_sysvicobr"
        ],
        'Victoria1' => [
            'host' => "172.17.0.1",
            'user' => "carvic_sysgp",
            'pass' => "Notengopalabras.100",
            'db' => "carvic_sysvicm"
        ],
        'Victoria2' => [
            'host' => "172.17.0.1",
            'user' => "carvic_sysgp",
            'pass' => "Notengopalabras.100",
            'db' => "carvic_sysvics"
        ]
    ];
}

// Default Connection (Main)
$conexion = mysqli_connect($db_host, $db_user, $db_password, $db_name);

if (!$conexion) {
    die("<h2 style='text-align:center'>Imposible conectarse a la base de datos Principal! </h2>" . mysqli_error($conexion));
}

if (mysqli_connect_errno()) {
    die("Conexión Principal falló: " . mysqli_connect_errno() . " : " . mysqli_connect_error());
}

//verificar posibles ataques

function proteger($v)
{

    $v = mysql_real_escape_string($v);

    $v = htmlentities($v, ENT_QUOTES);

    $v = trim($v);

    return $v;

}


function encrypt($string, $key)
{

    $result = '';

    for ($i = 0; $i < strlen($string); $i++) {

        $char = substr($string, $i, 1);

        $keychar = substr($key, ($i % strlen($key)) - 1, 1);

        $char = chr(ord($char) + ord($keychar));

        $result .= $char;

    }

    return base64_encode($result);

}



function decrypt($string, $key)
{

    $result = '';

    $string = base64_decode($string);

    for ($i = 0; $i < strlen($string); $i++) {

        $char = substr($string, $i, 1);

        $keychar = substr($key, ($i % strlen($key)) - 1, 1);

        $char = chr(ord($char) - ord($keychar));

        $result .= $char;

    }

    return $result;

}



function do_alert($msg)
{

    echo '<script type="text/javascript">alert("' . $msg . '"); </script>';

}



function conocerDiaSemanaFecha($fecha)
{

    $dias = array('Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado');

    $dia = $dias[date('w', strtotime($fecha))];

    return $dia;

}





function conocerMesFecha($fecha)
{

    $meses = array('   ', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic');

    $dia = date('j', strtotime($fecha));

    $mes = date('n', strtotime($fecha));

    $nommes = $meses[$mes];

    $resultado = $dia . "-" . $nommes;

    return $resultado;

}



function sMesFecha($fecha)
{

    $meses = array('   ', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre');

    $mes = date('n', strtotime($fecha));

    $nommes = $meses[$mes];

    $resultado = $nommes;

    return $resultado;

}













?>