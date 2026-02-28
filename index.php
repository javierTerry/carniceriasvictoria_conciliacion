<?php

    session_start();
    include "config/config.php";

    if (isset($_SESSION['user_id']) && $_SESSION!==null) {

       header("location: dashboard.php");

    }

?>

<!DOCTYPE html>

<html lang="es">

    <head>		

        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
		<style type="text/css">
  		.img3 {
 			max-width: 100%;
			height: auto;
			}
		</style> 
		

			<!-- Favicon -->
        
         <link rel="shortcut icon" href="images/ico/favicon.png">

		<link rel="apple-touch-icon" sizes="180x180" href="images/ico/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="images/ico/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="images/ico/favicon-16x16.png">
        <link rel="icon" type="image/png" sizes="192x192" href="images/ico/android-chrome-192x192.png">
		<link rel="manifest" href="images/ico/site.webmanifest">
        
		<link rel="mask-icon" href="images/ico/safari-pinned-tab.svg" color="#061316">
		<meta name="apple-mobile-web-app-title" content="SysPV Conciliacion">
		<meta name="application-name" content="Sys - Conciliacion">
		<meta name="msapplication-TileColor" content="#000000">
		<meta name="theme-color" content="#000000">

        <title>Sys Ventas | Conciliacion </title>

        <!-- Bootstrap -->

        <link href="css/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">

        <!-- Font Awesome -->

        <link href="css/font-awesome/css/font-awesome.min.css" rel="stylesheet">

        <!-- NProgress -->

        <link href="css/nprogress/nprogress.css" rel="stylesheet">

        <!-- Animate.css -->

        <link href="css/animate.css/animate.min.css" rel="stylesheet">

        <!-- Custom Theme Style -->

        <link href="css/custom.min.css" rel="stylesheet">
    </head>

    <body class="login" onload=setfocus()>

        <div>

            <a class="hiddenanchor" id="signup"></a>
            <a class="hiddenanchor" id="signin"></a>
            <div class="login_wrapper">
                <div class="animate form login_form">
                    <?php 

                        $invalid=sha1(md5("contrasena y usuario invalido"));

                        if (isset($_GET['invalid']) && $_GET['invalid']==$invalid) {

                            echo "<div class='alert alert-danger alert-dismissible fade in' role='alert'>

                                <strong>Error!</strong> Contraseña o Usuario invalido

                                </div>";
                        }

                    ?>

                    <section class="login_content">
						<img class="img3" src="images/profiles/logo1.png">

                        <form action="action/login.php" method="post" name="head" >
							
                            <h1>Iniciar Sesión</h1>
                            <div>
                                <input type="text" name="username" class="form-control" placeholder="Usuario" required />
                            </div>
                            <div>
                                <input type="password" name="password" class="form-control" placeholder="Contraseña" required/>
                            </div>
                            <div>
                                <button type="submit" name="token" value="Login" class="btn btn-default">Iniciar Sesion</button>
                            </div>
                            <div class="clearfix"></div>

                            <div class="separator">

                                <div class="clearfix"></div>

                                <br />

                                <div>

                                    <h1><i class="fa fa-shopping-cart"></i> SysPV Conciliacion </h1>
                                   <p> <a style="text-decoration: underline;" target="_blank" href="http://gabrielperaltarivero.com">G&amp;A Soluciones </a> Todos los derechos reservados</p>

                                </div>

                            </div>

                        </form>

                    </section>

                </div>

            </div>

        </div>

    </body>

</html>

<script language= javascript type= text/javascript >

function setfocus() {  document.head.username.focus(); }


</script>

