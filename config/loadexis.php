<?php

ob_start();
session_start();

include "../config/config.php";//Contiene funcion que conecta a la base de datos

?>


<script language= javascript type= text/javascript >
	
	
function setfocus(id){
         if (id==0) {  document.head.request.focus(); }
         else {document.item.rqsttype_id.focus(); }
}

	
</script>


<?php


//Busca nombre en base a input

$i = 0;

echo "</script>\n";

$i = 0;

?>

<body>
      <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
      <div class="right_col" role="main"><!-- page content -->
           <div class="">
                <div class="page-title">
					 <div class="clearfix"></div>
                    
                     <!-- /Inicia captura de datos -->
                     <div class="col-md-12 col-sm-12 col-xs-12">
                          <div class="x_panel">
                               <div class="x_title">
                                    <h2><i class="fa-solid fa-clipboard-list"></i> Cargar Rutas</h2>
                                    <div class="clearfix"></div>
                               </div>

							   <div id="rsltroute"></div>
                               <!-- /Inicia captura de datos -->
                   
                               <!-- Forma para capturar el cliente -->
							  
                               <form id='evidence' name='evidence' enctype="multipart/form-data"  autocomplete="off" action="batchexis.php" method="post">
                                 
								    	<div class="form-group">
                                        	<div class="col-md-12 col-sm-12 col-xs-12 ">
												<div class="input-group">
													<span class="input-group-addon">Archivo de datos </span>
												    <input type="hidden" name="MAX_FILE_SIZE" value="2000000">
								   					<input name="evidence" id="evidence" type="file" class="form-control" >
												</div>
											</div>
                                     	</div>
							
                                     	<div class="clearfix"></div>
								   
								    	<div class="form-group">
                                        	<div class="col-md-3 col-sm-3 col-xs-12 ">
                                               <button id="addroute" type="submit" class="btn btn-success">Generar</button>
												 <button type="reset" class="btn btn btn-danger">Cancelar</button>
                                          </div>
                                     	</div>
                               </form>
							  
                               <!-- fin Forma para capturar la partida -->					  
							  
							  </div>
						  <div class="clearfix"></div>
					
					</div>                    
      </div>

       </body>

  