<?php

session_start();
include "../config/config.php";//Contiene funcion que conecta a la base de datos


$dsys=date('Y-m-d');
$hsys = date("H:i:s");

$sql="select * ";
$sql=$sql." FROM art WHERE category_id IN (1, 2, 3, 5, 6, 7, 8, 9, 10, 11, 12, 13)  ";
$query=mysqli_query($conexion, $sql) or  die("Error en: " . mysqli_error($conexion));

$item=0;
//INSERTAR PARTIDAS
while  ($r = mysqli_fetch_array($query))  {

$item=$item+1;
$art_id = $r['id'];
$cost = $r['cost'];
$price = $r['price'];
$qty=$r['qtytot'];
$valor= $qty*$cost;

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
        $sql=$sql." 8 ";
        $sql=$sql.",'INCERO' ";
        $sql=$sql.",'2023-09-26' ";
        $sql=$sql.", 0";
        $sql=$sql.",".$item." ";
        $sql=$sql.",".$art_id." ";
        $sql=$sql.", ".$qty." ";
	    $sql=$sql.",".$cost." ";
	    $sql=$sql.", ".$valor." ";
	    $sql=$sql.",".$price." ";
	    $sql=$sql.",0 ";
        $sql=$sql.", 0 ";
	    $sql=$sql.", 0 ";
        $sql=$sql.",'".$dsys."' ";
        $sql=$sql.",'".$hsys."' ";
        $sql=$sql.",-1 ";
        $sql=$sql.") ";
	    //Ejecuto
        mysqli_query($conexion, $sql) or  die("Error en: " . mysqli_error($conexion));
    
    
    $sql="update art";
    $sql=$sql." set qtytot=0, valtot=0, lastout_at='".$dsys."' WHERE id=".$art_id;
    $aquery=mysqli_query($conexion, $sql) or  die("Error en: " . mysqli_error($conexion));
  
    echo "Articulo ".$art_id." modificado.";

}
?>