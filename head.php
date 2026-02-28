<?php


if(!isset($_SESSION)) { session_start();  }
include "config/config.php";

if (!isset($_SESSION['user_id'])) {
    echo  ' <html>
         	     <head>
     	               <meta http-equiv="REFRESH" content="0;url=index.php">
                 </head>
            </html>  ';
    exit;
}
if ($_SESSION['user_id']==null) {
    echo  ' <html>
         	     <head>
     	               <meta http-equiv="REFRESH" content="0;url=index.php">
                 </head>
            </html>  ';
    exit;
}

$id=$_SESSION['user_id'];

if (empty($id)) {
    echo  ' <html>
         	     <head>
     	               <meta http-equiv="REFRESH" content="0;url=index.php">
                 </head>
            </html>  ';
    exit;
}

if ($id=='') {
    echo  ' <html>
         	     <head>
     	               <meta http-equiv="REFRESH" content="0;url=index.php">
                 </head>
            </html>  ';
    exit;
    header("location: index.php");
}
if (strlen($id)==0) {
    echo  ' <html>
         	     <head>
     	               <meta http-equiv="REFRESH" content="0;url=index.php">
                 </head>
            </html>  ';
    exit;
}


$sql="SELECT * from user where id=".$id;
$usuario = mysqli_query($conexion, $sql)  or  die("Error en: " . mysqli_error($conexion));
$row=mysqli_fetch_array($usuario);
if(count($row) == 0){
   header("location: index.php");
} else {
  $username = $row['username'];
  $nameusr = $row['name'].' '.$row['lastname'];
  $user_kind = $row['kind'];
  //do_alert($user_kind);
  $_SESSION['user_kind']=$user_kind;
  $profile_pic = $row['profile_pic'];
  $created_at = $row['created_at'];
}

?>
<!DOCTYPE html>
<html lang="es">
      <head>
           <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
           <meta charset="utf-8">
           <meta http-equiv="X-UA-Compatible" content="IE=edge">
           <meta name="viewport" content="width=device-width, initial-scale=1">
          
          <!-- Favicon -->

			 <link rel="shortcut icon" href="images/ico/favicon.png">

            <link rel="apple-touch-icon" sizes="180x180" href="images/ico/apple-touch-icon.png">
            <link rel="icon" type="image/png" sizes="32x32" href="images/ico/favicon-32x32.png">
            <link rel="icon" type="image/png" sizes="16x16" href="images/ico/favicon-16x16.png">
            <link rel="icon" type="image/png" sizes="192x192" href="images/ico/android-chrome-192x192.png">
            <link rel="manifest" href="images/ico/site.webmanifest">

            <link rel="mask-icon" href="images/ico/safari-pinned-tab.svg" color="#061316">
            <meta name="apple-mobile-web-app-title" content="SysPV Conciliacion">
            <meta name="application-name" content="SysPV Conciliacion">
            <meta name="msapplication-TileColor" content="#000000">
            <meta name="theme-color" content="#000000">
          
           <title><?php echo $title." ".$nameusr; ?> </title>

           <!-- Bootstrap -->
           <link href="css/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
           <!-- Font Awesome -->
           <link href="css/font-awesome/css/font-awesome.min.css" rel="stylesheet">
           <!-- NProgress -->
           <link href="css/nprogress/nprogress.css" rel="stylesheet">
           <!-- iCheck -->
           <link href="css/iCheck/skins/flat/green.css" rel="stylesheet">
           <!-- Datatables -->
           <link href="css/datatables.net-bs/css/dataTables.bootstrap.min.css" rel="stylesheet">
           <link href="css/datatables.net-buttons-bs/css/buttons.bootstrap.min.css" rel="stylesheet">
           <link href="css/datatables.net-fixedheader-bs/css/fixedHeader.bootstrap.min.css" rel="stylesheet">
           <link href="css/datatables.net-responsive-bs/css/responsive.bootstrap.min.css" rel="stylesheet">
           <link href="css/datatables.net-scroller-bs/css/scroller.bootstrap.min.css" rel="stylesheet">
           <!-- jQuery custom content scroller -->
           <link href="css/malihu-custom-scrollbar-plugin/jquery.mCustomScrollbar.min.css" rel="stylesheet"/>

           <!-- bootstrap-daterangepicker -->
           <link href="css/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet">

           <!-- Custom Theme Style -->
           <link href="css/custom.min.css" rel="stylesheet">

           <!-- MICSS button[type="file"] -->
           <link rel="stylesheet" href="css/micss.css">

      </head>

      <body class="nav-md">
            <div class="container body">
                 <div class="main_container">
                      <div class="col-md-3 left_col">
                           <div class="left_col scroll-view">
                                 <div class="navbar nav_title" style="border: 0;" align="center">
                                   <a href="#" class="site_title">
                                        <b>
                                             <span>CONCILIACION</span>
                                        </b>
                                        
                                   </a>
                                   
                                </div>
                                <div class="clearfix"></div>

                                <!-- menu profile quick info -->
                                <div class="profile clearfix">
                                     <div class="profile_pic">
                                          <img src="images/profiles/logo1.png" alt="<?php echo $nameusr;?>" class="img-circle profile_img">
                                     </div>
                                     <div class="profile_info">
                                     <span>Bienvenido,</span>
                                     <h2><?php echo $nameusr;?></h2>
                                </div>
                           </div>
                           <!-- /menu profile quick info -->
                           <br />
