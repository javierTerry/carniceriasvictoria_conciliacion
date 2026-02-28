<?php 
$title ="Dashboard - ";
include "head.php";
include "sidebar.php";

$id=$_SESSION['kind'];

/*
if ($id==0) {
    echo  ' <html>
         	     <head>
     	               <meta http-equiv="REFRESH" content="0;url=vtanew.php">
                 </head>
            </html>  ';
    exit;
}
*/
$hoy=date('Y-m-d');


$sql = "select count(mov_id) as ventas ";
$sql = $sql.", sum(sumqty) as kilos ";
$sql = $sql.", sum(sumimp) as monto ";
$sql = $sql." from vtahead ";
$sql = $sql." where is_active=1  ";
$sql = $sql." and created_at='".$hoy."' ";
$resumen = mysqli_query($conexion, $sql) or  die("Error en: " . mysqli_error($conexion));
$r=mysqli_fetch_array($resumen);
$ventas=$r['ventas'];
$kilos=$r['kilos'];
$monto=$r['monto'];


$sql = "select sum(sumimp) as contado ";
$sql = $sql." from vtahead ";
$sql = $sql." where cust_id=1  ";
$sql = $sql." and created_at='".$hoy."' ";
$resumen = mysqli_query($conexion, $sql) or  die("Error en: " . mysqli_error($conexion));
$r=mysqli_fetch_array($resumen);
$contado=$r['contado'];

$sql = "select sum(acuenta) as credito ";
$sql = $sql." from vtahead ";
$sql = $sql." where cust_id<>1  ";
$sql = $sql." and created_at='".$hoy."' ";
$resumen = mysqli_query($conexion, $sql) or  die("Error en: " . mysqli_error($conexion));
$r=mysqli_fetch_array($resumen);
$credito=$r['credito'];
$ingresos=$contado+$credito;


$sql = "select sum(amount) as egresos ";
$sql = $sql." from caja ";
$sql = $sql." where is_active=1  ";
$sql = $sql." and created_at='".$hoy."' ";
$sql = $sql." and is_ingreso=2 ";
$resumen = mysqli_query($conexion, $sql) or  die("Error en: " . mysqli_error($conexion));
$r=mysqli_fetch_array($resumen);
$egresos=$r['egresos'];

$sql = "select sum(qtytot) as stock ";
$sql = $sql.", sum(qtytot*cost) as valor ";
$sql = $sql." from art ";
$sql = $sql." where is_active=1  ";
$sql = $sql." and kdx_id=1 ";
$resumen = mysqli_query($conexion, $sql) or  die("Error en: " . mysqli_error($conexion));
$r=mysqli_fetch_array($resumen);
$stock=$r['stock'];
$valor=$r['valor'];


$sql = "select sum(amount*ccxc_sign) as porcobrar ";
$sql = $sql." from cxc ";
$sql = $sql." where created_at<='".$hoy."' ";
$resumen = mysqli_query($conexion, $sql) or  die("Error en: " . mysqli_error($conexion));
$r=mysqli_fetch_array($resumen);
$porcobrar=$r['porcobrar'];


$sql = "select sum(amount*ccxp_sign) as porpagar ";
$sql = $sql." from cxp ";
$sql = $sql." where created_at<='".$hoy."' ";
$resumen = mysqli_query($conexion, $sql) or  die("Error en: " . mysqli_error($conexion));
$r=mysqli_fetch_array($resumen);
$porpagar=$r['porpagar'];


$sql = "select sum(sumimp) as imp ";
$sql = $sql.", sum(sumqty) as qty ";
$sql = $sql.", count(mov_id) as compras ";
$sql = $sql." from comhead ";
$sql = $sql." where created_at='".$hoy."' ";
$sql = $sql." and is_active=1  ";
$resumen = mysqli_query($conexion, $sql) or  die("Error en: " . mysqli_error($conexion));
$r=mysqli_fetch_array($resumen);
$comqty=$r['qty'];
$comimp=$r['imp'];
$compras=$r['compras'];

$sql = "select sum(amount) as cobrado ";
$sql = $sql." from cxc ";
$sql = $sql." inner join concxc ";
$sql = $sql." on cxc.ccxc_id=concxc.id ";
$sql = $sql." where created_at='".$hoy."' ";
$sql = $sql." and ccxc_type='A' ";
$sql = $sql." and is_money=1 ";
$resumen = mysqli_query($conexion, $sql) or  die("Error en: " . mysqli_error($conexion));
$r=mysqli_fetch_array($resumen);
$cobrado=$r['cobrado'];

$sql = "select sum(amount) as pagado ";
$sql = $sql." from cxp ";
$sql = $sql." inner join concxp ";
$sql = $sql." on cxp.ccxp_id=concxp.id ";
$sql = $sql." where created_at='".$hoy."' ";
$sql = $sql." and ccxp_type='A' ";
$sql = $sql." and is_money=1 ";
$resumen = mysqli_query($conexion, $sql) or  die("Error en: " . mysqli_error($conexion));
$r=mysqli_fetch_array($resumen);
$pagado=$r['pagado'];



$sql = "select caja ";
$sql = $sql." from emprsa ";
$resumen = mysqli_query($conexion, $sql) or  die("Error en: " . mysqli_error($conexion));
$r=mysqli_fetch_array($resumen);
$caja=$r['caja'];


?>
<div class="right_col" role="main"> <!-- page content -->
     <div class="">
          <div class="page-title">

               <div class="row top_tiles">
                    <div class="animated flipInY col-lg-4 col-md-4 col-sm-6 col-xs-12">
                         <div class="tile-stats">
                              <div class="icon"><i class="fa fa-shopping-basket"></i></div>
                              <div class="count"><?php echo number_format($compras,0); ?></div>
                              <h3>Compras </h3>
                         </div>
                    </div>
                    <div class="animated flipInY col-lg-4 col-md-4 col-sm-6 col-xs-12">
                         <div class="tile-stats">
                              <div class="icon"><i class="fa fa-balance-scale"></i></div>
                              <div class="count"><?php echo number_format($comqty,2) ?></div>
                              <h3>Cant/Kgs </h3>
                         </div>
                    </div>
                    <div class="animated flipInY col-lg-4 col-md-4 col-sm-6 col-xs-12">
                         <div class="tile-stats">
                              <div class="icon"><i class="fa fa-money-bill-transfer"></i></div>
                              <div class="count"><?php echo number_format($comimp,2) ?></div>
                              <h3>Monto </h3>
                         </div>
                    </div>
               </div>

               <div class="clearfix"></div>


               <div class="row top_tiles">
                    <div class="animated flipInY col-lg-4 col-md-4 col-sm-6 col-xs-12">
                         <div class="tile-stats">
                              <div class="icon"><i class="fa fa-shopping-cart"></i></div>
                              <div class="count"><?php echo number_format($ventas,0); ?></div>
                              <h3>Ventas </h3>
                         </div>
                    </div>
                    <div class="animated flipInY col-lg-4 col-md-4 col-sm-6 col-xs-12">
                         <div class="tile-stats">
                              <div class="icon"><i class="fa fa-balance-scale"></i></div>
                              <div class="count"><?php echo number_format($kilos,2) ?></div>
                              <h3>Cant/Kgs </h3>
                         </div>
                    </div>
                    <div class="animated flipInY col-lg-4 col-md-4 col-sm-6 col-xs-12">
                         <div class="tile-stats">
                              <div class="icon"><i class="fa fa-money-bill-transfer"></i></div>
                              <div class="count"><?php echo number_format($monto,2) ?></div>
                              <h3>Monto </h3>
                         </div>
                    </div>
               </div>

               <div class="clearfix"></div>

               <!-- content -->

               <div class="row top_tiles">
                    <div class="animated flipInY col-lg-4 col-md-4 col-sm-6 col-xs-12">
                         <div class="tile-stats">
                              <div class="icon"><i class="fa fa-cash-register"></i></div>
                              <div class="count"><?php echo number_format($caja,2); ?></div>
                              <h3>Caja </h3>
                         </div>
                    </div>
                    <div class="animated flipInY col-lg-4 col-md-4 col-sm-6 col-xs-12">
                         <div class="tile-stats">
                              <div class="icon"><i class="fa fa-arrow-left"></i></div>
                              <div class="count"><?php echo number_format($ingresos,2) ?></div>
                              <h3>Ingresos </h3>
                         </div>
                    </div>
                    <div class="animated flipInY col-lg-4 col-md-4 col-sm-6 col-xs-12">
                         <div class="tile-stats">
                              <div class="icon"><i class="fa fa-arrow-right"></i></div>
                              <div class="count"><?php echo number_format($egresos,2) ?></div>
                              <h3>Egresos</h3>
                         </div>
                    </div>
               </div>

               <div class="clearfix"></div>


               <!-- content -->

               <div class="row top_tiles">
                    <div class="animated flipInY col-lg-4 col-md-4 col-sm-6 col-xs-12">
                         <div class="tile-stats">
                              <div class="icon"><i class="fa fa-balance-scale"></i></div>
                              <div class="count"><?php echo number_format($stock,2); ?></div>
                              <h3>Existencia </h3>
                         </div>
                    </div>
                    <div class="animated flipInY col-lg-4 col-md-4 col-sm-6 col-xs-12">
                         <div class="tile-stats">
                              <div class="icon"><i class="fa fa-hand-holding-dollar"></i></div>
                              <div class="count"><?php echo number_format($valor,2) ?></div>
                              <h3>Valor </h3>
                         </div>
                    </div>
               </div>

               <div class="clearfix"></div>

               <div class="row top_tiles">
                    <div class="animated flipInY col-lg-4 col-md-4 col-sm-6 col-xs-12">
                         <div class="tile-stats">
                              <div class="icon"><i class="fa fa-money"></i></div>
                              <div class="count"><?php echo number_format($cobrado,2); ?></div>
                              <h3>Cobrado </h3>
                         </div>
                    </div>
                    <div class="animated flipInY col-lg-4 col-md-4 col-sm-6 col-xs-12">
                         <div class="tile-stats">
                              <div class="icon"><i class="fa fa-money"></i></div>
                              <div class="count"><?php echo number_format($porcobrar,2); ?></div>
                              <h3>Por Cobrar </h3>
                         </div>
                    </div>
               </div>

               <div class="clearfix"></div>

               <div class="row top_tiles">
                    <div class="animated flipInY col-lg-4 col-md-4 col-sm-6 col-xs-12">
                         <div class="tile-stats">
                              <div class="icon"><i class="fa fa-money"></i></div>
                              <div class="count"><?php echo number_format($pagado,2); ?></div>
                              <h3>Pagado </h3>
                         </div>
                    </div>
                    <div class="animated flipInY col-lg-4 col-md-4 col-sm-6 col-xs-12">
                         <div class="tile-stats">
                              <div class="icon"><i class="fa fa-money"></i></div>
                              <div class="count"><?php echo number_format($porpagar,2); ?></div>
                              <h3>Por Pagar </h3>
                         </div>
                    </div>
               </div>

             
          </div>
		
     </div>

</div><!-- /page content -->
 <?php include "footer.php" ?>



