<?php



date_default_timezone_set('America/Mexico_City');



//www gab demo
$db_name="carvic_sysvicobr";
$db_host="172.17.0.1";
$db_user="carvic_sysgp";
$db_password="Notengopalabras.100";




//Conexion www

$conexion = mysqli_connect($db_host,$db_user,$db_password,$db_name);



if(!$conexion){

      die("<h2 style='text-align:center'>Imposible conectarse a la base de datos! </h2>".mysqli_error($conexion));

    }

if (mysqli_connect_errno()) {

      die("Conexión falló: ".mysqli_connect_errno()." : ". mysqli_connect_error());

    }







//verificar posibles ataques

function proteger($v)

{

         $v=mysql_real_escape_string($v);

		 $v=htmlentities($v,ENT_QUOTES);

		 $v=trim($v);

		 return $v;

}





function encrypt($string, $key) {

   $result = '';

   for($i=0; $i<strlen($string); $i++) {

      $char = substr($string, $i, 1);

      $keychar = substr($key, ($i % strlen($key))-1, 1);

      $char = chr(ord($char)+ord($keychar));

      $result.=$char;

   }

   return base64_encode($result);

}



function decrypt($string, $key) {

   $result = '';

   $string = base64_decode($string);

   for($i=0; $i<strlen($string); $i++) {

      $char = substr($string, $i, 1);

      $keychar = substr($key, ($i % strlen($key))-1, 1);

      $char = chr(ord($char)-ord($keychar));

      $result.=$char;

   }

   return $result;

}



function do_alert($msg) {

         echo '<script type="text/javascript">alert("' . $msg . '"); </script>';

}



function conocerDiaSemanaFecha($fecha) {

    $dias = array('Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado');

    $dia = $dias[date('w', strtotime($fecha))];

    return $dia;

}





function conocerMesFecha($fecha) {

    $meses=array('   ','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic');

    $dia = date('j', strtotime($fecha));

    $mes = date('n', strtotime($fecha));

    $nommes=$meses[$mes];

    $resultado=$dia."-".$nommes;

    return $resultado;

}



function sMesFecha($fecha) {

    $meses=array('   ','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre');

    $mes = date('n', strtotime($fecha));

    $nommes=$meses[$mes];

    $resultado=$nommes;

    return $resultado;

}













?>







