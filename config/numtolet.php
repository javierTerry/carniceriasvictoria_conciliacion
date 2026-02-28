<?php


Function NumLetras($x)  {

  If ($x < 0) { $x = Abs($x); }

  $entero=intval($x);
  $decimal=($x-$entero)*100;
  $numtmp1 = str_pad((int) $entero,16,"0",STR_PAD_LEFT);
  $numtmp2 = str_pad((int) $decimal,2,"0",STR_PAD_LEFT);
  $numtmp=$numtmp1;
  //.".".$numtmp2;
  
//  do_alert($numtmp);


  $c01 = 1;
  $pos = 1;
  $tfnumero = "";
  $leyenda="";
  
  
  
  //Para extraer tres digitos cada vez
  While ($c01 <= 5)  {
  
        $c02 = 1;
        While ($c02 <= 3) {
              //Extrae un digito cada vez de izquierda a derecha
              $dig = intval(substr($numtmp, $pos, 1));
              switch  ($c02) {
                    Case 1:
                          $cen=$dig;
                          break;
                    Case 2:
                          $dec=$dig;
                          break;
                    Case 3:
                          $uni=$dig;
                          break;
              }
              $c02=$c02 + 1;
              $pos=$pos + 1;
        }
//        do_alert("vuelta ".$c01." cen ".$cen);
//        do_alert("vuelta ".$c01." dec ".$dec);
//        do_alert("vuelta ".$c01." uni ".$uni);
        $letra3 = Centena($uni, $dec, $cen);
        $letra2 = Decena($uni, $dec);
        $letra1 = Unidad($uni, $dec);

        switch ($c01) {
                   Case 1:
                         If ($cen + $dec + $uni == 1) {
                            $leyenda = "Billon "; }
                         ElseIf ($cen + $dec + $uni > 1) { $leyenda = "Billones ";  }
                         break;
                   Case 2:
                         If ($cen + $dec + $uni >= 1 && intVal(substr($numtmp, 7, 3)) == 0) {
                            $leyenda = "Mil Millones "; }
                         ElseIf ($cen + $dec + $uni >= 1) { $leyenda = "Mil "; }
                         break;
                   Case 3:
                         If ($cen + $dec == 0 && $uni == 1) {
                            $leyenda = "Millon "; }
                         ElseIf ($cen > 0 Or $dec > 0 Or $uni > 1) {
                                $leyenda = "Millones ";  }
                         break;
                   Case 4:
                         If ($cen + $dec + $uni >= 1) {
                            $leyenda = "Mil " ;
                         }
                         break;
                   Case 5:
                         If ($cen + $dec + $uni >= 1) {
                            $leyenda = "";
                         }
                         break;
        }

        $c01 = $c01 + 1;

        $tfnumero = $tfnumero.$letra3.$letra2.$letra1.$leyenda;

        $leyenda = "";
        $letra1 = "";
        $letra2 = "";
        $letra3 = "";

  }


  If (intVal($numtmp) == 0 Or intVal($numtmp) < 1) {
     $leyenda1 = "Cero Pesos "; }
  ElseIf (intVal($numtmp) == 1 Or intVal($numtmp) < 2) {
         $leyenda1 = "Peso "; }
  ElseIf (intVal(substr($numtmp, 4, 12)) == 0 Or intVal(substr($numtmp, 10, 6)) == 0) {
         $leyenda1 = "de Pesos ";  }
  Else {
      $leyenda1 = "Pesos " ;
  }

  // a mayusculas
  //$str = strtoupper($str);
  // a minusculas
  //$str = strtolower($str);
  //primer mayuscula
  //$foo = 'hello world!';
  //$foo = ucwords($foo);             // Hello World!


  $tfnumero = strtoupper($tfnumero.$leyenda1);
//  $tfnumero = $tfnumero.substr($numtmp, 17)."/100 M.N.";
  $tfnumero = $tfnumero.$numtmp2."/100 M.N.";
  $NumLetras = $tfnumero;
  return ($NumLetras);

}

Function Centena($uni,$dec,$cen)   {

switch ($cen) {
       Case 1:
             If ($dec + $uni == 0) {
                $cTexto = "cien "; }
             Else {
                $cTexto = "ciento ";
             }
             break;
       Case 2:
             $cTexto = "doscientos ";
             break;
       Case 3:
             $cTexto = "trescientos ";
             break;
       Case 4:
             $cTexto = "cuatrocientos ";
             break;
       Case 5:
             $cTexto = "quinientos ";
             break;
       Case 6:
             $cTexto = "seiscientos ";
             break;
       Case 7:
             $cTexto = "setecientos ";
             break;
       Case 8:
             $cTexto = "ochocientos ";
             break;
       Case 9:
             $cTexto = "novecientos ";
             break;
      default:
             $cTexto = "";
 }
 $Centena = $cTexto;
 return ($Centena);

}

Function Decena($uni,$dec) {


switch ($dec) {
       Case 1:
             switch ($uni)  {
                    Case 0:
                         $cTexto = "diez ";
                         break;
                    Case 1:
                         $cTexto = "once ";
                         break;
                    Case 2:
                         $cTexto = "doce ";
                         break;
                    Case 3:
                         $cTexto = "trece ";
                         break;
                    Case 4:
                         $cTexto = "catorce ";
                         break;
                    Case 5:
                         $cTexto = "quince ";
                         break;
                    default:
                         $cTexto = "dieci";
             }
             break;
       Case 2:
             If ($uni == 0) {
                $cTexto = "veinte "; }
             ElseIf ($uni > 0) {
                    $cTexto = "veinti";
             }
             break;
       Case 3:
             $cTexto = "treinta ";
             break;
       Case 4:
             $cTexto = "cuarenta ";
             break;
       Case 5:
             $cTexto = "cincuenta ";
             break;
       Case 6:
             $cTexto = "sesenta ";
             break;
       Case 7:
             $cTexto = "setenta ";
             break;
       Case 8:
             $cTexto = "ochenta ";
             break;
       Case 9:
             $cTexto = "noventa ";
             break;
       default:
             $cTexto = "";
  }

  If ($uni > 0 && $dec > 2) {
      $cTexto = $cTexto."y ";
  }
  $decena = $cTexto;
  return ($decena);
}


Function Unidad($uni,$dec) {

$cTexto="";

If ($dec <> 1) {
   switch ($uni) {
          Case 1:
                $cTexto = "un ";
                break;
          Case 2:
                $cTexto = "dos ";
                break;
          Case 3:
                $cTexto = "tres ";
                break;
          Case 4:
                $cTexto = "cuatro ";
                break;
          Case 5:
                $cTexto = "cinco ";
                break;
   }
}
switch ($uni) {
         Case 6:
               $cTexto = "seis ";
               break;
         Case 7:
               $cTexto = "siete ";
               break;
         Case 8:
               $cTexto = "ocho ";
               break;
         Case 9:
               $cTexto = "nueve ";
               break;
}

$unidad=$cTexto;

return ($unidad);

}





?>

