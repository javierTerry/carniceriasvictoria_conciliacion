<?php

    require('../fpdf.php');

//	require 'fpdf/fpdf.php';



	class PDF extends FPDF

	{

		function Header()

		{

            //Logo

            $this->Image('../images/profiles/logo.jpg', 10 ,5, 25 , 23);



            $row=$GLOBALS["empresa"];

            $domicilio=trim($row['domicilio']);

            $municipio=trim($row['municipio']);

            $empresa=trim($row['name']);

            $calle=$domicilio." ".$municipio;



            $this->SetFont('Arial','B',12);

            $this->Cell(203,10,$empresa,0,0,'C');

			$this->Ln(4);

            $this->SetFont('Arial','B',8);

            $this->Cell(203,10,$calle,0,0,'C');

			$this->Ln(15);



            $femision=$GLOBALS["emision"];

            $hora=$GLOBALS["hora"];

            $leyenda01=": ".$femision." : ".str_repeat(' ', 70).$hora.str_repeat(' ', 70).'Pagina '.$this->PageNo().'/{nb}';



            $this->SetFont('Arial','B',10);

            $this->Cell(256,6,$leyenda01,0,0,'L');

            $this->Ln(12);



            $titulo=$GLOBALS["titulo"];

            $this->Cell(200,6,$titulo,0,0,'C');

            $this->Ln(4);





            $columnas=$GLOBALS["columnas"];

            if (strlen($columnas)>0) {

               $this->SetFont('Arial','',8);

               $lineas=str_repeat('-',200);

               $this->Cell(210,6,$lineas,0,0,'L');

               $this->Ln(4);



               $this->Cell(210,6,$columnas,0,0,'L');

               $this->Ln(4);

               $this->Cell(210,6,$lineas,0,0,'L');

               $this->Ln(4);

            }

		}



	}

?>



