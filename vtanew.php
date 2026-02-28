<?php

ob_start();
session_start();

//Captura de la venta
$title ="Nueva Venta | ";
include "head.php";
include "sidebar.php";


?>


<script language= javascript type= text/javascript>


var codecust =  new Array();
var namecust = new Array();

var codeart =  new Array();
var nameart = new Array();
var precios = new Array();

var codepxc =  new Array();
var preciosxcli = new Array();


function setfocus(id){
         if (id==0) {  document.head.cust_code.focus(); }
         else {document.item.art_code.focus(); }
}


function imprimirticket(id)  { window.open("action/vtaticket.php?xyz="+id); }

function Valida( formulario )  {
         if (formulario.art_name.value == '')  {
            alert("Error: Se requiere Producto valido");
            formulario.art_code.focus();
            return false
         }
         if (formulario.qty.value == '')  {
            alert("Error: Se requiere Cantidad");
            formulario.qty.focus();
            return false
         } else {
            if (isNaN(formulario.qty.value)) {
               alert("Error: Cantidad debe ser numerico.");
               formulario.qty.focus();
               return false
            } else {
               if (formulario.qty.value==0) {
                  alert("Error: Cantidad debe ser mayor a cero.");
                  formulario.qty.focus();
                  return false
               }
            }
	     }

         if (formulario.price.value == '')  {
            alert("Error: Se requiere Precio");
            formulario.price.focus();
            return false
         } else {
            if (isNaN(formulario.price.value)) {
               alert("Error: Precio debe ser numerico.");
               formulario.price.focus();
               return false
            } else {
               if (formulario.price.value==0) {
                  alert("Error: Precio debe ser mayor a cero.");
                  formulario.price.focus();
                  return false
               }
            }
	     }
}

function eliminaritem(id)  {
         var answer = confirm("Quiere eliminar esta partida?")
         if (answer)  {  window.location.href="action/delvtaitem.php?llave="+id;   }
}

function iraclientes() { window.location="cust_find.php"; }

function iraarticulos() { window.location="art_find.php";  }

function cargarClientes(valor)  {
         var i = 0;
         document.getElementById("cust_name").value="";
         for ( i = 0; i <  codecust.length; i++ ) {
             if ( codecust[i] == valor ) {
                document.getElementById("cust_name").value=namecust[i];
                document.getElementById("cust_num").value=valor;
             }
         }
}

function cargarArticulos(valor)  {
         var i = 0;
         var cli=document.getElementById("cust_code").value
         var key=cli+valor;
         document.getElementById("art_name").value="";
         document.getElementById("price").value="";
         for ( i = 0; i <  codeart.length; i++ ) {
             if ( codeart[i] == valor ) {
                document.getElementById("art_name").value=nameart[i];
                document.getElementById("price").value=parseFloat(precios[i]).toFixed(2);

             }
         }
         for ( i = 0; i <  codepxc.length; i++ ) {
             if ( codepxc[i] == key ) {
                document.getElementById("price").value=preciosxcli[i];
             }
         }

}

</script>


<?php


if (isset($_SESSION['ticket'])) {
    $ticket_id=$_SESSION['ticket'];
    echo "<script>";
    echo "imprimirticket(".$ticket_id.");";
    echo "</script>";
    unset($_SESSION["venta"]);
    unset($_SESSION["ticket"]);
    unset($_SESSION["cust_code"]);
    unset($_SESSION["art_code"]);
}

$_SESSION['pagina']='venta';
if (!isset($_SESSION['cust_code'])) { $cust_code='001'; $_SESSION['cust_code']=$cust_code;  }
else { $cust_code=$_SESSION['cust_code']; }

if (!isset($_SESSION['art_code'])) { $art_code='';   }
else { $art_code=$_SESSION['art_code']; }

//if (!isset($filetmp)) { unset($_SESSION["venta"]);   }

if (!isset($_SESSION['venta'])) {
   $new=false;
   $filetmp="";
   $folio=0;

   //Incremento numero archivo temporal
   $sql = "UPDATE  " ;
   $sql = $sql." emprsa " ;
   $sql = $sql." SET tmpvta=tmpvta+1 " ;
   //Ejecuto
   mysqli_query($conexion, $sql)  or  die("Error en: " . mysqli_error($conexion));


   //Traigo numero archivo temporal
   $sql = "select tmpvta as tmp from emprsa";
   $empresa = mysqli_query($conexion, $sql)  or  die("Error en: " . mysqli_error($conexion));
   $fila=mysqli_fetch_array($empresa);
   $filetmp = $fila['tmp'];
   $filetmp = str_pad((int) $filetmp,7,"0",STR_PAD_LEFT);
   $filetmp = "V".$filetmp;
   //Creo la tabla temporal
   $sql = "CREATE TABLE ".$filetmp." LIKE auxpur";
   mysqli_query($conexion, $sql) or  die("Error en: " . mysqli_error($conexion));



   //Traigo folio
   $sql = "select max(mov_id) as last from vtahead";
   $purs = mysqli_query($conexion, $sql)  or  die("Error en: " . mysqli_error($conexion));
   $fila=mysqli_fetch_array($purs);

   if(count($fila) == 0){ $folio=1; } else { $folio = $fila['last']+1; }
   $folio = str_pad((int) $folio,6,"0",STR_PAD_LEFT);

   $campos[1]=$new;
   $campos[2]=$filetmp;
   $campos[3]=$folio;
   $_SESSION['venta']=$campos;
}

//Llena el Combobox forma de pago
$sql="SELECT id,code,name FROM fpago WHERE is_active=1 and id!=7";
$fquery=mysqli_query($conexion, $sql) or die("Error en: " . mysqli_error($conexion)); 


$sql="select name ";
$sql=$sql." from cust ";
$sql=$sql." where code='".$cust_code."' " ;
$clientes= mysqli_query($conexion, $sql) or die("Error en: " . mysqli_error($conexion));
$r=mysqli_fetch_array($clientes);
$cust_name=$r['name'];


$sql="select name ";
$sql=$sql." from art ";
$sql=$sql." where code='".$art_code."' " ;
$clientes= mysqli_query($conexion, $sql) or die("Error en: " . mysqli_error($conexion));
$r=mysqli_fetch_array($clientes);
$art_name=$r['name'];


$hoy=date('Y-m-d');
$msg="";
$cantidad="";
$costo="";

$campos=$_SESSION['venta'];
$new=$campos[1];
$filetmp=$campos[2];
$folio=$campos[3];



//$sql = "select * from ".$filetmp." ";
$sql = "select * from ".$filetmp." order by id desc ";
$items = mysqli_query($conexion, $sql) or  die("Error en: " . mysqli_error($conexion));

$sql = "select sum(qty) as sumqty ";
$sql = $sql.", sum(amount) as sumimp ";
$sql = $sql.", count(*) as sumreg ";
$sql= $sql." from ".$filetmp." ";
$resumen = mysqli_query($conexion, $sql) or  die("Error en: " . mysqli_error($conexion));
$r=mysqli_fetch_array($resumen);
$totqty=$r['sumqty'];
$totimp=$r['sumimp'];
$totreg=$r['sumreg'];
if($totqty==""){ $totimp=0; $totreg=0; $totqty=0; }


$sql="select code ";
$sql=$sql.",name ";
$sql=$sql.",price ";
$sql=$sql.",unit ";
$sql=$sql.",ctrl_id ";
$sql=$sql." from art ";
$sql=$sql." where is_active=1 ";
$sql=$sql." order by code";
$arts=mysqli_query($conexion,$sql)  or die("Error en: " . mysqli_error($conexion));

$sql="select code,name ";
$sql=$sql." from cust ";
$sql=$sql." where is_active=1 " ;
$sql=$sql." order by code " ;
$clientes= mysqli_query($conexion, $sql) or die("Error en: " . mysqli_error($conexion));

$sql="select B.code as cod_c ";
$sql=$sql.", C.code as cod_a ";
$sql=$sql.", A.price as precio ";
$sql=$sql." from custart A";
$sql=$sql." inner join cust B ";
$sql=$sql." on B.id=A.cust_id ";
$sql=$sql." inner join art C ";
$sql=$sql." on C.id=A.art_id ";
$sql=$sql." order by B.code, C.code " ;
$preciosxcli= mysqli_query($conexion, $sql) or die("Error en: " . mysqli_error($conexion));



$name="";
$stock=0;
$qty=1;



$i = 0;
echo "<script language='javascript'>\n";
while($resultados = mysqli_fetch_array($clientes))   {

     $clave=$resultados[0];
     $nombre=$resultados[1];

     echo "codecust[" . $i . "] = '".$clave."' ;\n";
 	 echo "namecust[" . $i . "] = '".$nombre."' ;\n";
     $i++;
}
echo "</script>\n";


$i = 0;
echo "<script language='javascript'>\n";
while($resultados = mysqli_fetch_array($arts))   {

     $clave=$resultados[0];
     $nombre=$resultados[1];
     $precio=$resultados[2];
     $um=$resultados[3];
     $ctrl=$resultados[4];
     
     if ($ctrl==1)  { $nombre=$nombre." (KILOS) "; } else { $nombre=$nombre." (PIEZAS) "; }

     echo "codeart[" . $i . "] = '".$clave."' ;\n";
 	 echo "nameart[" . $i . "] = '".$nombre."' ;\n";
 	 echo "precios[" . $i . "] = ".$precio." ;\n";
     $i++;
}
echo "</script>\n";

$i = 0;
echo "<script language='javascript'>\n";
while($resultados = mysqli_fetch_array($preciosxcli))   {

     $cli=$resultados[0];
     $art=$resultados[1];
     $precio=$resultados[2];
     $clave=$cli.$art;
     echo "codepxc[" . $i . "] = '".$clave."' ;\n";
 	 echo "preciosxcli[" . $i . "] = ".$precio." ;\n";
     $i++;
}
echo "</script>\n";


?>


<script language= javascript type= text/javascript >
    
    function hdnrecibo(){
                    var fp=document.getElementById("fpago");
                    if (fp.value == 1){
                        $('#hcbx').show();
                       
     
                         
                    } else {
                           $('#hcbx').hide();
                  
                   

                    }
                }
    

function ChecaCambio( formulario )  {
         var x=document.getElementById("cust_name").value;
         if (x == '')  {
            alert("Error: Cliente no valido");
            document.getElementById("cust_code").focus();
            return false
         }
         if (formulario.recibo.value == '' && formulario.fpago.value==1)  {
            alert("Error: Se requiere Recibo");
            formulario.recibo.focus();
            return false
         } else {
           if (isNaN(formulario.recibo.value)) {
              alert("Error: Recibo debe ser numerico.");
              formulario.recibo.focus();
              return false
           } else {
              if (formulario.recibo.value==0 && formulario.fpago.value==1) {
                 alert("Error: Cantidad debe ser mayor a cero.");
                 formulario.recibo.focus();
                 return false
              }
           }
	     }
         if (parseFloat(formulario.total.value) > parseFloat(formulario.recibo.value)) {
            alert("Error: Lo recibido no puede ser menor al total");
            formulario.recibo.focus();
            return false
         }

}


function ChecaCambioAcuenta( formulario )  {
         if (formulario.acuenta.value == '')  {
            alert("Error: Se requiere A cuenta");
            formulario.acuenta.focus();
            return false
         } else {
           if (isNaN(formulario.acuenta.value)) {
              alert("Error: A cuenta debe ser numerico.");
              formulario.acuenta.focus();
              return false
           }
	     }
         if (formulario.recibo.value == '')  {
            alert("Error: Se requiere Recibo");
            formulario.recibo.focus();
            return false
         } else {
           if (isNaN(formulario.recibo.value)) {
              alert("Error: Recibo debe ser numerico.");
              formulario.recibo.focus();
              return false
           }
	     }
         if (parseFloat(formulario.acuenta.value) > parseFloat(formulario.recibo.value)) {
            alert("Error: Lo recibido no puede ser menor A cuenta");
            formulario.recibo.focus();
            return false
         }

}


</script>

<body onload=setfocus(<?php echo $totreg; ?>);>
      <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
    <meta http-equiv="content-type" content="text/html; charset=iso-8859-1" />
      <div class="right_col" role="main"><!-- page content -->
           <div class="">
                <div class="page-title">
                     <!-- Totales parte superior -->
                     <div class="row top_tiles">
                          <div class="animated flipInY col-lg-3 col-md-3 col-sm-6 col-xs-12">
                               <div class="tile-stats">
                                    <div class="count"><?php echo $totreg; ?></div>
                                    <h3>Productos</h3>
                               </div>
                          </div>
                          <div class="animated flipInY col-lg-3 col-md-3 col-sm-6 col-xs-12">
                               <div class="tile-stats">
                                    <div class="count"><?php echo $totqty; ?></div>
                                    <h3>Cantidad</h3>
                               </div>
                          </div>
                          <div class="animated flipInY col-lg-3 col-md-3 col-sm-6 col-xs-12">
                               <div class="tile-stats">
                                    <div class="count"><?php echo number_format($totimp,2); ?></div>
                                    <h3>Monto</h3>
                               </div>
                          </div>
                          <div class="animated flipInY col-lg-3 col-md-3 col-sm-6 col-xs-12">
                               <div class="tile-stats">
                                    <div class="count"><?php echo $folio; ?></div>
                                    <h3>Venta</h3>
                               </div>
                          </div>
                     </div>
                     <div class="clearfix"></div>
                     <!-- /Finaliza totales parte superior -->

                     <!-- /Inicia captura de datos -->
                     <div class="col-md-12 col-sm-12 col-xs-12">
                          <div class="x_panel">
                               <div class="x_title">
                                    <h2><?php  echo "";    ?></h2>
                                    <div class="clearfix"></div>
                               </div>

                               <!-- /Inicia captura de datos -->

                               <!-- Boton para ir a buscar cliente -->
                               <div class="col-md-4">
                                    <button type="button" class="btn btn-default" onclick='iraclientes();' ><span class="glyphicon glyphicon-search" ></span> Clientes</button>
                               </div>
                                <!-- Boton para ir a buscar articulo -->
                               <div class="col-md-4">
                                    <button type="button" class="btn btn-default" onclick='iraarticulos();' ><span class="glyphicon glyphicon-search" ></span> Productos</button>
                               </div>
                               <div class="clearfix"></div>

                               <!-- Forma para capturar el cliente -->
                               <form id='head' name='head' method='post'  autocomplete="off"  action=''  onSubmit='return Valida(this)' >
                                     <div class="form-group">
                                          <div class="col-md-3 col-sm-3 col-xs-6 ">
                                               <div class="input-group">
                                                    <span class="input-group-addon">Cliente&nbsp;&nbsp;&nbsp;&nbsp;</span>
                                                    <input name="cust_code" id="cust_code" value="<?php echo $cust_code; ?>" required type="text" class="form-control" placeholder="Codigo" onblur="cargarClientes(this.value)">
                                               </div>
                                          </div>
                                          <div class="col-md-6 col-sm-6 col-xs-12 ">  
                                              <input name="cust_name" id="cust_name" value="<?php echo $cust_name; ?>" required type="text" class="form-control" placeholder=""  disabled='disabled'>   
                                         </div>

                                     </div>
                                     <div class="clearfix"></div>
                               </form>
                               <!-- fin Forma para capturar el cliente -->

                               <div class="clearfix"></div>

                               <!-- Forma para capturar la partida -->
                               <form id='item' name='item' method='post'  autocomplete="off"  action='action/add_vta_item.php'  onSubmit='return Valida(this)' >
                                     <input type="hidden" id="tmp" name="tmp" value="<?php echo $filetmp; ?>">
                                     <input type="hidden" id="cust_num" name="cust_num" >
                                     <div class="form-group">
                                          <div class="col-md-3 col-sm-3 col-xs-6 ">
                                               <div class="input-group">
                                                    <span class="input-group-addon">Producto</span>
                                                    <input name="art_code" id="art_code" value="<?php echo $art_code; ?>" required type="text" class="form-control" placeholder="Codigo" onblur="cargarArticulos(this.value)" >
                                               </div>
                                          </div>
                                          <div class="col-md-6 col-sm-6 col-xs-12 ">
                                              <input name="art_name" id="art_name" required type="text" value="<?php echo $art_name; ?>" class="form-control" placeholder="Descripcion"  disabled='disabled'>
                                          </div>
                                     </div>
                                     <div class="clearfix"></div>
                                     <div class="form-group">
                                          <div class="col-md-3 col-sm-3 col-xs-6 ">
                                               <div class="input-group">
                                                    <span class="input-group-addon">Cantidad</span>
                                                    <input name="qty" id="qty" required type="text" class="form-control" placeholder="Cantidad" >
                                               </div>
                                          </div>
                                   
                                          <div class="col-md-3 col-sm-3 col-xs-12 ">
                                               <div class="input-group">
                                                    <span class="input-group-addon">Precio</span>
                                                    <input name="price" id="price" required type="text" class="form-control" placeholder="Precio">
                                          
                                               </div>
                                          </div>
                                     </div>
                                     <div class="clearfix"></div>
                                     <div class="form-group">
                                          <div class="col-md-4 col-sm-3 col-xs-12 ">
                                               <button id="upd" type="submit" class="btn btn-success">Agregar</button>
                                          </div>
                                     </div>
                                     <div class="clearfix"></div>
                               </form>
                               <!-- fin Forma para capturar la partida -->
                               <br>

                  <?php
                               if(mysqli_num_rows($items)>0){
                                 // si hay partidas
                  ?>
                                 <div class="x_content">
                                      <div class="table-responsive">
                                           <table class="table table-striped table-hover">
                                                  <thead>
                                                         <th>Cantidad</th>
                                                         <th>UM</th>
                                                         <th>Codigo</th>
                                                         <th>Producto</th>
                                                         <th>Precio</th>
                                                         <th>Importe</th>
                                                         <th class="column-title no-link last"><span class="nobr"></span></th>
                                                  </thead>
                  <?php                           while ($r=mysqli_fetch_array($items)) {
                                                        $id_tmp=$r['id'];
                                                        $code_tmp=$r['code'];
                                                        $name_tmp=$r['name'];
                                                        $qty_tmp=$r['qty'];
                                                        $price_tmp=$r['price'];
                                                        $unit_tmp=$r['unit'];
                                                        $amount_tmp=$r['amount'];
                  ?>
                                                        <tr>
                                                            <td align="center" width="10%"><?php echo $qty_tmp; ?></td>
                                                            <td width="10%"><?php echo $unit_tmp; ?></td>
                                                            <td width="10%"><?php echo $code_tmp; ?></td>
                                                            <td width="50%"><?php echo $name_tmp; ?></td>
                                                            <td width="10%" align="right"><?php echo number_format($price_tmp,2); ?></td>
                                                            <td width="10%" align="right"><?php echo number_format($amount_tmp,2); ?></td>
                                                            <td><a href="#" class='btn btn-default' title='Borrar partida' onclick="eliminaritem('<?php echo encrypt($id_tmp,'cafemx00'); ?>')"><i class="glyphicon glyphicon-trash"></i> </a></span></td>

                                                        </tr>
                  <?php                           }
                  ?>

                                           </table>
                                      </div>
                                 </div>
                  <?php        } else {
                                 echo "<p class='alert alert-danger'>No hay partidas</p>";
                               }
                  ?>

                  <?php
                               if(mysqli_num_rows($items)>0){
                                 // si hay partidas
                  ?>
                                 <!-- Boton para guardar -->
                  <?php
                                 if($cust_code=='001'){
                                   // Venta contado
                  ?>
                                   <form id='form1' name='form1' method='post' autocomplete="off" action='action/add_vta.php' onSubmit='return ChecaCambio(this)'>
                                         <input type="hidden" id="tmp" name="tmp" value="<?php echo $filetmp; ?>">
                                         <input type="hidden" id="total" name="total" value="<?php echo $totimp; ?>">
                                         <input type="hidden" id="acuenta" name="acuenta" value="<?php echo 0; ?>">
                                        <div class="form-group">
                                              <div class="col-md-6 col-sm-6 col-xs-12 ">
                                                   <div class="input-group">
                                                        <span class="input-group-addon">Forma de Pago</span>
                                                       <select class="form-control" id="fpago" name="fpago" onChange="hdnrecibo()" required>
                                                           <option value="">Seleciona una forma de pago</option>
                                                           <?php while ($ftype=mysqli_fetch_array($fquery))  { 
                                                                $fid=$ftype[0];
                                                                $fcode=$ftype[1];
                                                                $fnom=utf8_decode($ftype[2]); ?>
                                                           <option value="<?php echo $fid;?>"><?php echo $fcode.' - '.$fnom; ?></option>
													<?php } ?>
												</select>
                                                       
                                                        
                                                    </div>
                                              </div>
                                          </div>
                                       
                                       
                                        <div class="clearfix"></div>
                                           <div id="hcbx" name="hcbx" hidden="">
                                       <div class="form-group">
                                              <div class="col-md-3 col-sm-6 col-xs-12 ">
                                                   <div class="input-group">
                                                        <span class="input-group-addon">Recibo</span>
                                                        <input name="recibo" id="recibo" type="text"  class="form-control" placeholder="Recibo" >
                                                   </div>
                                              </div>
                                          </div>
                                       </div>
                                        
                                         
                                        <div class="clearfix"></div>
                                       
                                          <div class="form-group">
                                               <div class="col-lg-3">
                                                    <button class="btn btn-primary btn-block">Enviar</button>
                                               </div>
                                          </div>
                                   </form>
                                   <!-- end form procesar -->
                  <?php          } else {
                                   // Venta CREDITO
                  ?>
                                   <!-- Captura venta credito -->
                                   <form id='form11' name='form11' method='post' autocomplete="off" action='action/add_vta.php' onSubmit='return ChecaCambioAcuenta(this)'>
                                         <input type="hidden" id="tmp" name="tmp" value="<?php echo $filetmp; ?>">
                                         <input type="hidden" id="total" name="total" value="<?php echo $totimp; ?>">
                                         <input type="hidden" id="cust_code" name="cust_code" value="<?php echo $cust_code; ?>">
                                         <input type="hidden" id="fpago" name="fpago" value="7">
                                     
                                         
                                         <div class="form-group">
                                              <div class="col-md-3 col-sm-3 col-xs-12 ">
                                                   <div class="input-group">
                                                        <span class="input-group-addon">A cuenta</span>
                                                        <input name="acuenta" id="acuenta"  type="text"  class="form-control" placeholder="A cuenta" >
                                                   </div>
                                              </div>
                                         </div>
                                         <div class="clearfix"></div>
                                     
                                         <div class="form-group">
                                              <div class="col-md-3 col-sm-3 col-xs-12 ">
                                                   <div class="input-group">
                                                        <span class="input-group-addon">Recibo</span>
                                                        <input name="recibo" id="recibo"  type="text"  class="form-control" placeholder="Recibo" >
                                                   </div>
                                              </div>
                                         </div>
                                     
                                         
                                         <div class="clearfix"></div>
                                         <div class="form-group">
                                              <div class="col-lg-3">
                                                   <button class="btn btn-primary btn-block">Enviar</button>
                                              </div>
                                         </div>
                                   </form>
                                   <!-- end form procesar -->
                  <?php          }
                  ?>
                  <?php
                               }
                  ?>

                               <br><br>
                          </div>
                          <div class="clearfix"></div>

                          <!-- Boton para cancelar -->
                          <form id='form2' name='form2' method='post' class="form-horizontal"   autocomplete="off" action='action/killvta.php' >
                                <input type="hidden" id="tmpfolio" name="tmpfolio" value="<?php echo $folio; ?>">
							    <input type="hidden" id="tmp" name="tmp" value="<?php echo $filetmp; ?>">
                                <div class="col-lg-6">
                                     <button class="btn btn btn-danger">Cancelar</button>
                                </div>
                          </form>
                          <!-- end form procesar -->

                          <div class="x_panel">
                               <footer><!-- footer content -->
                                       <div class="pull-right">
                                            Este sistema esta en produccion
                                       </div>
                                       <div class="clearfix"></div>
                               </footer><!-- /footer content -->
                          </div>
                          <br><br>
                     </div>
                     <div class="clearfix"></div>

                     </div>
                </div>
                <div class="clearfix"></div>
           </div>
      </div>


        <!-- jQuery -->
        <script src="js/jquery/dist/jquery.min.js"></script>
        <!-- Bootstrap -->
        <script src="css/bootstrap/dist/js/bootstrap.min.js"></script>
        <!-- FastClick -->
        <script src="js/fastclick/lib/fastclick.js"></script>
        <!-- NProgress -->
        <script src="css/nprogress/nprogress.js"></script>
        <!-- iCheck -->
        <script src="css/iCheck/icheck.min.js"></script>
        <!-- jQuery custom content scroller -->
        <script src="css/malihu-custom-scrollbar-plugin/jquery.mCustomScrollbar.concat.min.js"></script>



        <!-- Datatables -->
        <script src="js/datatables.net/js/jquery.dataTables.min.js"></script>
        <script src="css/datatables.net-bs/js/dataTables.bootstrap.min.js"></script>
        <script src="js/datatables.net-buttons/js/dataTables.buttons.min.js"></script>
        <script src="css/datatables.net-buttons-bs/js/buttons.bootstrap.min.js"></script>
        <script src="js/datatables.net-buttons/js/buttons.flash.min.js"></script>
        <script src="js/datatables.net-buttons/js/buttons.html5.min.js"></script>
        <script src="js/datatables.net-buttons/js/buttons.print.min.js"></script>
        <script src="js/datatables.net-fixedheader/js/dataTables.fixedHeader.min.js"></script>
        <script src="js/datatables.net-keytable/js/dataTables.keyTable.min.js"></script>
        <script src="js/datatables.net-responsive/js/dataTables.responsive.min.js"></script>
        <script src="css/datatables.net-responsive-bs/js/responsive.bootstrap.js"></script>
        <script src="js/datatables.net-scroller/js/dataTables.scroller.min.js"></script>
        <script src="js/jszip/dist/jszip.min.js"></script>
        <script src="js/pdfmake/build/pdfmake.min.js"></script>
        <script src="js/pdfmake/build/vfs_fonts.js"></script>

        <!-- Custom Theme Scripts -->
        <script src="js/custom.min.js"></script>



            <!-- DateJS -->
        <!-- <script src="js/DateJS/build/date.js"></script> -->
        <!-- bootstrap-daterangepicker -->
        <script src="js/moment/min/moment.min.js"></script>
        <script src="css/bootstrap-daterangepicker/daterangepicker.js"></script>


</body>




