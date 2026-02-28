<?php
session_start();
include "../config/config.php";//Contiene funcion que conecta a la base de datos


$lastin_at=date('Y-m-d');
$hsys = date("H:i:s");


//Importar datos del archivo
$nomarch = $_FILES['evidence']['name'];
$tipo = $_FILES['evidence']['type'];
$tamano = $_FILES['evidence']['size'];
$archivotmp = $_FILES['evidence']['tmp_name'];


//Si existe imagen y tiene un tamaño correcto
if (!empty($nomarch) && ($tamano <= 2000000)) 
{
	if (($tipo == "application/csv") 
		|| ($tipo == "text/csv")
		|| ($tipo == "application/vnd.ms-excel")
	   
	   )
		{
	
	
	$lineas=file($archivotmp);
	$i=0;
    $item=0;

	foreach($lineas as $linea)
	{
		//Realiza el conteo de la cantidad de lineas a llenar
		$cregistros=count($lineas);
		$cregistrosa=$cregistros - 1 ; // Quita la celda del encabezado

		if($i!=0)
		{
		
            $item=$item+1;
			//Indica que los archivos estan separados por una ,
			$datos=explode(",",$linea);

			//Separa la los datos leidos de las celdas en variables
			$code=!empty($datos[0]) ? ($datos[0]):'';
			$catid=!empty($datos[1]) ? ($datos[1]):'';
            $price=!empty($datos[2]) ? ($datos[2]):'';
            $cost=!empty($datos[3]) ? ($datos[3]):'';
            $qty=!empty($datos[4]) ? ($datos[4]):'';
                        
            $valtot=floatval($qty) * floatval($cost);
            
            //Trae id del articulo
			$sql="select id from art where code= '".$code."'";			
			$cquery=mysqli_query($conexion,$sql) or die("Error en: " . mysqli_error($conexion));
			$ctype=mysqli_fetch_array($cquery); 
            $art_id=$ctype[0];
            
            $sql="update art";
            $sql=$sql." set qtytot='".$qty."', valtot='".$valtot."', lastin_at='".$lastin_at."' WHERE code='".$code."'";
            $aquery=mysqli_query($conexion, $sql) or  die("Error en: " . mysqli_error($conexion));
		
	

		//Insertar datos en kardex
        $sql="INSERT INTO kdx (";
	    $sql=$sql."cinv_id ";
	    $sql=$sql.",mov_id ";
        $sql=$sql.",created_at ";
	    $sql=$sql.",is_entrada ";
	    $sql=$sql.",item";
	    $sql=$sql.",art_id";
	    $sql=$sql.",qty";
	    $sql=$sql.",cost";
	    $sql=$sql.",valor";
        $sql=$sql.",price";
	    $sql=$sql.",amount";
        $sql=$sql.",qtytot";
	    $sql=$sql.",valtot";
        $sql=$sql.",dsys";
	    $sql=$sql.",hsys";
	    $sql=$sql.",sign";
	    $sql=$sql.") VALUES (";
        $sql=$sql." 5 ";
        $sql=$sql.",'AJINV' ";
        $sql=$sql.",'".$lastin_at."'";
        $sql=$sql.", 1";
        $sql=$sql.",".$item." ";
        $sql=$sql.",".$art_id." ";
        $sql=$sql.", ".$qty." ";
	    $sql=$sql.",".$cost." ";
	    $sql=$sql.", ".$valtot." ";
	    $sql=$sql.",".$price." ";
	    $sql=$sql.",0 ";
        $sql=$sql.", ".$qty." ";
	    $sql=$sql.", ".$valtot." ";
        $sql=$sql.",'".$lastin_at."' ";
        $sql=$sql.",'".$hsys."' ";
        $sql=$sql.",1 ";
        $sql=$sql.") ";
	    //Ejecuto
        $qart = mysqli_query($conexion, $sql) or  die("Error en: " . mysqli_error($conexion));
    
    
			//echo $sql;

		
	}
	
	$i++;		
	
}
		if ($qart){
				echo "La rutas han sido ingresadas satisfactoriamente. ";
			} else{
				echo "Lo siento, algo ha salido mal en la ruta ".$i." intenta nuevamente. ".mysqli_error($conexion);
			}
		
		}
	else{
		
		 echo " Formato de archivo no valido, solo .CSV";
	}
	
}
else 
	{

	  // if($nomarch == !NULL) {
		   
		  echo " Adjunta un archivo .CSV";
	   
	  // }
		
	}
	 


?>