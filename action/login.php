<?php
session_start();
include "../config/config.php";


if (isset($_POST['token']) && $_POST['token']!=='') {
   //Contiene las variables de configuracion para conectar a la base de datos
  // include "../config/config.php";

   $username=strip_tags($_POST["username"],ENT_QUOTES);
   $password=sha1(md5($_POST["password"]));
   $sql="SELECT id ";
   $sql=$sql.", kind ";
   $sql=$sql." FROM user ";
   $sql=$sql." WHERE username='".$username."' ";
   $sql=$sql." AND password='".$password."' ";
   $sql=$sql." AND is_active=1 ";

   $query = mysqli_query($conexion,$sql)  or die("Error en: " . mysqli_error($conexion));
   $num=mysqli_num_rows($query);

   if ($num>0) {
      $fila=mysqli_fetch_array($query);
      $_SESSION['user_id'] = $fila['id'];
   	  $_SESSION['option_id']='P';
      $tipo_usuario=$fila['kind'];
      $_SESSION['kind']=$fila['kind'];

      if ($tipo_usuario==0) {
           //Acceso
          echo '
               <html>
             	    <head>
                     <meta http-equiv="REFRESH" content="0;url=../vtanew.php">
                </head>
            </html>
              ';
      }else{


      //Acceso
      echo '
               <html>
             	    <head>
                     <meta http-equiv="REFRESH" content="0;url=../dashboard.php">
                </head>
            </html>
              ';
      }




   } else {
//		$invalid=sha1(md5("contrasena y usuario invalido"));
        do_alert("Contrasena o usuario invalido");
		//header("location: www.gabrielperaltarivero.com/agim/index.php?invalid=$invalid");
            echo '
            <html>
         	    <head>
         	            <meta http-equiv="REFRESH" content="0;url=../index.php">
                </head>
            </html>
             ';
   }
}else{
     do_alert("Contrasena o usuario no valido");
		//header("location: www.gabrielperaltarivero.com/agim/index.php?invalid=$invalid");
            echo '
            <html>
         	    <head>
         	            <meta http-equiv="REFRESH" content="0;url=../index.php">
                </head>
            </html>
             ';

}

?>
