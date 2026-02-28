<?php
$user_id=$_SESSION['user_id'];
$user_kind=$_SESSION['user_kind'];

//pagos realizados
//pagos recibidos
//cuentas por pagar
//cuentas por cobrar

?>

     <div id="sidebar-menu" class="main_menu_side hidden-print main_menu"><!-- sidebar menu -->
          <div class="menu_section">
               <ul class="nav side-menu">
                    <?php  if ($user_kind==1 || $user_kind==2)  {
                   ?>
                    
                        <li class="<?php if(isset($active01)){echo $active01;}?>">
                            <a href="dashboard.php"><i class="fa fa-bars-progress"></i> Resumen</a>
                        </li>
                   <?php } ?>

                   <li><a><i class="fa fa-shopping-cart"></i> Ventas <span class="fa fa-chevron-down"></span></a>
                       <ul class="nav child_menu">
                           <li class="<?php if(isset($active11)){echo $active11;}?>">
                               <a href="vtanew.php"> Nueva</a>
                           </li>
                           <li class="<?php if(isset($active12)){echo $active12;}?>">
                               <a href="vtaqrym.php"> Ver</a>
                           </li>
                           <li class="<?php if(isset($active13)){echo $active12;}?>">
                               <a href="vtaqry.php"> Ver Todas</a>
                           </li>
                       </ul>
                   </li>

                    <?php  if ($user_kind==1 || $user_kind==2)  {?>
                        <li>
                            <a><i class="fa fa-exchange"></i> Inventario <span class="fa fa-chevron-down"></span></a>
                            <ul class="nav child_menu">
                            <li class="<?php if(isset($active51)){echo $active51;}?>">
                                <a href="invnew.php"> Entrada/Salida</a>
                            </li>
                            <li class="<?php if(isset($active52)){echo $active52;}?>">
                                <a href="invqry.php">  Ver</a>
                            </li>
                            </ul>
                        </li>                   
                    <?php } ?>

                    <li><a><i class="fa fa-print"></i> Reportes <span class="fa fa-chevron-down"></span></a>
                       <ul class="nav child_menu">
                            <li class="<?php if(isset($active61)){echo $active61;}?>">
                                <a href="corte.php"> Cierre</a>
                            </li>
                            <li class="<?php if(isset($active62)){echo $active62;}?>">
                                <a href="rvtadiat.php"> Documentos de Ventas</a>
                            </li>
                           <li class="<?php if(isset($active62)){echo $active62;}?>">
                                <a href="rvtadiac.php"> Analisis de Ingresos</a>
                            </li>
                            <li class="<?php if(isset($active63)){echo $active62;}?>">
                                <a href="rvtadia.php"> Analisis de Venta Producto  </a>
                            </li>
							<!--<li class="<?php if(isset($active64)){echo $active62;}?>">
                                <a href="rvtadiadet.php"> Venta Dia </a>
                            </li> 
							<li class="<?php if(isset($active65)){echo $active62;}?>">
                                <a href="rvtaprod.php"> Venta Producto </a>
                            </li>
							-->
							
                            <li class="<?php if(isset($active66)){echo $active63;}?>">
                                <a href="rcxcdia.php"> Cobranza Dia</a>
                            </li>
                            <li class="<?php if(isset($active67)){echo $active64;}?>">
                                <a href="rexidia.php"> Existencia Dia</a>
                            </li>
                            <li class="<?php if(isset($active68)){echo $active65;}?>">
                                <a href="rcomdia.php"> Compra Dia</a>
                            </li>
                            <li class="<?php if(isset($active69)){echo $active66;}?>">
                                <a href="ranadia.php"> Compra/Venta Dia</a>
                            </li>
                            <li class="<?php if(isset($active70)){echo $active67;}?>">
                                <a href="rcat.php"> Catalogos </a>
                            </li>
                       </ul>
                    </li>
                </ul>

            </div>
        </div><!-- /sidebar menu -->
    </div>
</div> 
     
<div class="top_nav"><!-- top navigation -->
    <div class="nav_menu">
        <nav>
            <div class="nav toggle">
                <a id="menu_toggle"><i class="fa fa-bars"></i></a>
            </div>
            <ul class="nav navbar-nav navbar-right">
                <li class="">
                    <a href="javascript:;" class="user-profile dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                        <i class="fa fa-solid fa-user"></i><?php echo "  ".$nameusr;?>
                        <span class=" fa fa-angle-down"></span>
                    </a>
                    <ul class="dropdown-menu dropdown-usermenu pull-right">
                        <li><a href="action/logout.php"><i class="fa fa-sign-out pull-right"></i> Cerrar Sesión</a></li>
                    </ul>
                </li>
            </ul>
        </nav>
    </div>
</div><!-- /top navigation -->    
    
<?php

function recarga()
{
    $_SESSION['option_id'] = 'S';

}

?>


<script>
 function reiniciar(ventana)
  {
     window.location.href=ventana;
  }

</script>
