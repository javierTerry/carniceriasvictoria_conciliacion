<?php
$user_id = $_SESSION['user_id'];
$user_kind = $_SESSION['user_kind'];

$current_page = basename($_SERVER['PHP_SELF']);
$q_branch = $_GET['branch'] ?? '';
$q_status = $_GET['status'] ?? '';
?>

<div id="sidebar-menu" class="main_menu_side hidden-print main_menu"><!-- sidebar menu -->
    <div class="menu_section">
        <ul class="nav side-menu">
            <?php if ($user_kind == 1 || $user_kind == 2) {
                ?>

                <li class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                    <a href="dashboard.php"><i class="fa fa-bars-progress"></i> Resumen</a>
                </li>
            <?php } ?>

            <li class="<?php echo ($current_page == 'vtaqry.php' && $q_branch == 'all') ? 'active' : ''; ?>">
                <a><i class="fa fa-shopping-cart"></i> TICKETS <span class="fa fa-chevron-down"></span></a>
                <ul class="nav child_menu"
                    style="<?php echo ($current_page == 'vtaqry.php' && $q_branch == 'all') ? 'display: block;' : ''; ?>">
                    <li class="<?php echo ($current_page == 'vtaqry.php' && $q_branch == 'all') ? 'active' : ''; ?>">
                        <a href="vtaqry.php?branch=all"> Ver Todos</a>
                    </li>
                </ul>
            </li>

            <li class="<?php echo ($q_branch == 'Obrador') ? 'active' : ''; ?>">
                <a><i class="fa fa-building"></i> OBRADOR <span class="fa fa-chevron-down"></span></a>
                <ul class="nav child_menu" style="<?php echo ($q_branch == 'Obrador') ? 'display: block;' : ''; ?>">
                    <li class="<?php echo ($q_branch == 'Obrador' && empty($q_status)) ? 'active' : ''; ?>"><a
                            href="vtaqry.php?branch=Obrador">Tickets</a></li>
                    <li class="<?php echo ($q_branch == 'Obrador' && $q_status == '2') ? 'active' : ''; ?>"><a
                            href="vtaqry.php?branch=Obrador&status=2">Pendientes</a></li>
                    <li class="<?php echo ($q_branch == 'Obrador' && $q_status == '3') ? 'active' : ''; ?>"><a
                            href="vtaqry.php?branch=Obrador&status=3">Completado</a></li>
                    <li class="<?php echo ($q_branch == 'Obrador' && $q_status == '4') ? 'active' : ''; ?>"><a
                            href="vtaqry.php?branch=Obrador&status=4">Facturados</a></li>
                </ul>
            </li>

            <li class="<?php echo ($q_branch == 'Vicoria1') ? 'active' : ''; ?>">
                <a><i class="fa fa-store"></i> VICTORIA 1 <span class="fa fa-chevron-down"></span></a>
                <ul class="nav child_menu" style="<?php echo ($q_branch == 'Vicoria1') ? 'display: block;' : ''; ?>">
                    <li class="<?php echo ($q_branch == 'Vicoria1' && empty($q_status)) ? 'active' : ''; ?>"><a
                            href="vtaqry.php?branch=Vicoria1">Tickets</a></li>
                    <li class="<?php echo ($q_branch == 'Vicoria1' && $q_status == '2') ? 'active' : ''; ?>"><a
                            href="vtaqry.php?branch=Vicoria1&status=2">Pendientes</a></li>
                    <li class="<?php echo ($q_branch == 'Vicoria1' && $q_status == '3') ? 'active' : ''; ?>"><a
                            href="vtaqry.php?branch=Vicoria1&status=3">Completado</a></li>
                    <li class="<?php echo ($q_branch == 'Vicoria1' && $q_status == '4') ? 'active' : ''; ?>"><a
                            href="vtaqry.php?branch=Vicoria1&status=4">Facturados</a></li>
                </ul>
            </li>

            <li class="<?php echo ($q_branch == 'Vicoria2') ? 'active' : ''; ?>">
                <a><i class="fa fa-store"></i> VICTORIA 2 <span class="fa fa-chevron-down"></span></a>
                <ul class="nav child_menu" style="<?php echo ($q_branch == 'Vicoria2') ? 'display: block;' : ''; ?>">
                    <li class="<?php echo ($q_branch == 'Vicoria2' && empty($q_status)) ? 'active' : ''; ?>"><a
                            href="vtaqry.php?branch=Vicoria2">Tickets</a></li>
                    <li class="<?php echo ($q_branch == 'Vicoria2' && $q_status == '2') ? 'active' : ''; ?>"><a
                            href="vtaqry.php?branch=Vicoria2&status=2">Pendientes</a></li>
                    <li class="<?php echo ($q_branch == 'Vicoria2' && $q_status == '3') ? 'active' : ''; ?>"><a
                            href="vtaqry.php?branch=Vicoria2&status=3">Completado</a></li>
                    <li class="<?php echo ($q_branch == 'Vicoria2' && $q_status == '4') ? 'active' : ''; ?>"><a
                            href="vtaqry.php?branch=Vicoria2&status=4">Facturados</a></li>
                </ul>
            </li>

            <?php /* if ($user_kind == 1 || $user_kind == 2) { ?>
           <li>
               <a><i class="fa fa-exchange"></i> Inventario <span class="fa fa-chevron-down"></span></a>
               <ul class="nav child_menu">
                   <li class="<?php if (isset($active51)) {
                       echo $active51;
                   } ?>">
                       <a href="invnew.php"> Entrada/Salida</a>
                   </li>
                   <li class="<?php if (isset($active52)) {
                       echo $active52;
                   } ?>">
                       <a href="invqry.php"> Ver</a>
                   </li>
               </ul>
           </li>
       <?php } */ ?>

            <?php /* <li><a><i class="fa fa-print"></i> Reportes <span class="fa fa-chevron-down"></span></a>
           <ul class="nav child_menu">
               <li class="<?php if (isset($active61)) {
                   echo $active61;
               } ?>">
                   <a href="corte.php"> Cierre</a>
               </li>
               <li class="<?php if (isset($active62)) {
                   echo $active62;
               } ?>">
                   <a href="rvtadiat.php"> Documentos de Ventas</a>
               </li>
               <li class="<?php if (isset($active62)) {
                   echo $active62;
               } ?>">
                   <a href="rvtadiac.php"> Analisis de Ingresos</a>
               </li>
               <li class="<?php if (isset($active63)) {
                   echo $active62;
               } ?>">
                   <a href="rvtadia.php"> Analisis de Venta Producto </a>
               </li>
               <!--<li class="<?php if (isset($active64)) {
                   echo $active62;
               } ?>">
                           <a href="rvtadiadet.php"> Venta Dia </a>
                       </li> 
                       <li class="<?php if (isset($active65)) {
                           echo $active62;
                       } ?>">
                           <a href="rvtaprod.php"> Venta Producto </a>
                       </li>
                       -->

               <li class="<?php if (isset($active66)) {
                   echo $active63;
               } ?>">
                   <a href="rcxcdia.php"> Cobranza Dia</a>
               </li>
               <li class="<?php if (isset($active67)) {
                   echo $active64;
               } ?>">
                   <a href="rexidia.php"> Existencia Dia</a>
               </li>
               <li class="<?php if (isset($active68)) {
                   echo $active65;
               } ?>">
                   <a href="rcomdia.php"> Compra Dia</a>
               </li>
               <li class="<?php if (isset($active69)) {
                   echo $active66;
               } ?>">
                   <a href="ranadia.php"> Compra/Venta Dia</a>
               </li>
               <li class="<?php if (isset($active70)) {
                   echo $active67;
               } ?>">
                   <a href="rcat.php"> Catalogos </a>
               </li>
           </ul>
       </li> */ ?>
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

            <div class="nav-title-container"
                style="position: absolute; width: 100%; text-align: center; pointer-events: none; margin-top: 10px;">
                <h1 style="margin: 0; font-size: 1.8em; font-weight: bold; color: #1a2732; text-transform: uppercase;">
                    ConsolidaCión</h1>
                <small style="display: block; font-size: 0.9em; color: #e74c3c; font-weight: bold; margin-top: -5px;">
                    Ambiente Dev</small>
            </div>

            <ul class="nav navbar-nav navbar-right">
                <li class="">
                    <a href="javascript:;" class="user-profile dropdown-toggle" data-toggle="dropdown"
                        aria-expanded="false">
                        <i class="fa fa-solid fa-user"></i><?php echo "  " . $nameusr; ?>
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


<script src="assets/js/sidebar.js" defer></script>