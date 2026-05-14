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
                    <li
                        class="<?php echo ($q_branch == 'Obrador' && $current_page == 'facturasqry.php') ? 'active' : ''; ?>">
                        <a href="facturasqry.php?branch=Obrador">Facturados</a></li>
                    <li
                        class="<?php echo ($q_branch == 'Obrador' && $current_page == 'vtaagrupados.php') ? 'active' : ''; ?>">
                        <a href="vtaagrupados.php?branch=Obrador">Agrupados</a></li>
                </ul>
            </li>

            <li class="<?php echo ($q_branch == 'Victoria1') ? 'active' : ''; ?>">
                <a><i class="fa fa-store"></i> VICTORIA 1 <span class="fa fa-chevron-down"></span></a>
                <ul class="nav child_menu" style="<?php echo ($q_branch == 'Victoria1') ? 'display: block;' : ''; ?>">
                    <li class="<?php echo ($q_branch == 'Victoria1' && empty($q_status)) ? 'active' : ''; ?>"><a
                            href="vtaqry.php?branch=Victoria1">Tickets</a></li>
                    <li class="<?php echo ($q_branch == 'Victoria1' && $q_status == '2') ? 'active' : ''; ?>"><a
                            href="vtaqry.php?branch=Victoria1&status=2">Pendientes</a></li>
                    <li
                        class="<?php echo ($q_branch == 'Victoria1' && $current_page == 'facturasqry.php') ? 'active' : ''; ?>">
                        <a href="facturasqry.php?branch=Victoria1">Facturados</a></li>
                    <li
                        class="<?php echo ($q_branch == 'Victoria1' && $current_page == 'vtaagrupados.php') ? 'active' : ''; ?>">
                        <a href="vtaagrupados.php?branch=Victoria1">Agrupados</a></li>
                </ul>
            </li>

            <li class="<?php echo ($q_branch == 'Victoria2') ? 'active' : ''; ?>">
                <a><i class="fa fa-store"></i> VICTORIA 2 <span class="fa fa-chevron-down"></span></a>
                <ul class="nav child_menu" style="<?php echo ($q_branch == 'Victoria2') ? 'display: block;' : ''; ?>">
                    <li class="<?php echo ($q_branch == 'Victoria2' && empty($q_status)) ? 'active' : ''; ?>"><a
                            href="vtaqry.php?branch=Victoria2">Tickets</a></li>
                    <li class="<?php echo ($q_branch == 'Victoria2' && $q_status == '2') ? 'active' : ''; ?>"><a
                            href="vtaqry.php?branch=Victoria2&status=2">Pendientes</a></li>
                    <li
                        class="<?php echo ($q_branch == 'Victoria2' && $current_page == 'facturasqry.php') ? 'active' : ''; ?>">
                        <a href="facturasqry.php?branch=Victoria2">Facturados</a></li>
                    <li
                        class="<?php echo ($q_branch == 'Victoria2' && $current_page == 'vtaagrupados.php') ? 'active' : ''; ?>">
                        <a href="vtaagrupados.php?branch=Victoria2">Agrupados</a></li>
                </ul>
            </li>
             <li class="<?php echo ($current_page == 'cep_creacion.php' || ($current_page == 'facturasqry.php' && $q_branch == 'CEP')) ? 'active' : ''; ?>">
                <a><i class="fa fa-paw"></i> CERDO EN PIE (CEP) <span class="fa fa-chevron-down"></span></a>
                <ul class="nav child_menu" style="<?php echo ($current_page == 'cep_creacion.php' || ($current_page == 'facturasqry.php' && $q_branch == 'CEP')) ? 'display: block;' : ''; ?>">
                    <li class="<?php echo ($current_page == 'cep_creacion.php') ? 'active' : ''; ?>">
                        <a href="cep_creacion.php">Creación</a>
                    </li>
                    <li class="<?php echo ($current_page == 'facturasqry.php' && $q_branch == 'CEP') ? 'active' : ''; ?>">
                        <a href="facturasqry.php?branch=CEP">Facturados</a>
                    </li>
                </ul>
            </li>

             <li class="<?php echo ($current_page == 'cust.php') ? 'active' : ''; ?>">
                <a><i class="fa fa-folder-open"></i> CATÁLOGOS <span class="fa fa-chevron-down"></span></a>
                <ul class="nav child_menu" style="<?php echo ($current_page == 'cust.php' || $current_page == 'prod.php') ? 'display: block;' : ''; ?>">
                    <li class="<?php echo ($current_page == 'cust.php') ? 'active' : ''; ?>">
                        <a href="cust.php">Clientes</a>
                    </li>
                    <li class="<?php echo ($current_page == 'prod.php') ? 'active' : ''; ?>">
                        <a href="prod.php">Productos</a>
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

            <div class="nav-title-container"
                style="position: absolute; width: 100%; text-align: center; pointer-events: none; margin-top: 10px;">
                <h1 style="margin: 0; font-size: 1.8em; font-weight: bold; color: #1a2732; text-transform: uppercase;">
                    ConsolidaCión</h1>
                <small style="display: block; font-size: 0.9em; color: #e74c3c; font-weight: bold; margin-top: -5px;">
                    Ambiente <?php echo $ambiente ?? 'Dev'; ?></small>
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