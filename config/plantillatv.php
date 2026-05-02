<?php

    require('../fpdf.php');

//	require 'fpdf/fpdf.php';



	class PDF extends FPDF

	{

		function Header()

		{

            $row=$GLOBALS["empresa"];

            $domicilio=trim($row['domicilio']);
            $municipio=trim($row['municipio']);
            $empresa=trim($row['name']);
            $calle=$domicilio." ".$municipio;
            $rfc=trim($row['rfc']);
            $alias=trim($row['alias']);

            //Logo

            $this->Image('../images/profiles/logo.jpg', 39 ,0, 42 , 40);

            // Salto de línea

            $this->Ln(32);


            $this->SetFont('Arial','B',9);
            $this->Cell(0,6,$empresa,0,0,'C');
            $this->Ln(5);


            $this->Cell(100,6,'R.F.C.:   '.$rfc,0,0,'C');
           $this->Ln(7);

            $this->Cell(100,6,$domicilio,0,0,'C');
            $this->Ln(5);

            $this->Cell(100,6,$municipio,0,0,'C');

            $this->Ln();


            $this->Cell(100,6,$alias,0,0,'C');
            $this->Ln(10);

		}



	}

?>



