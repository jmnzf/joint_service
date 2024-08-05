<?php
defined('BASEPATH') OR exit('No direct script access allowed');


class CostoBO {

    private $ci;
    private $pdo;

	public function __construct() {

        header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
        header("Access-Control-Allow-Origin: *");

        $this->ci =& get_instance();
        $this->ci->load->database();
        $this->pdo = $this->ci->load->database('pdo', true)->conn_id;
        $this->ci->load->library('pedeo', [$this->pdo]);
        $this->ci->load->library('generic');

	}

    public function validateCost($usa_tasa, $tasa, $precio, $tasa_iva, $des, $cant) {
        
		$DECI_MALES =  $this->ci->generic->getDecimals();

        $des = $this->validateDesc($des, $cant);
		
		$costo = 0;

		if ( $usa_tasa == 1 ){

			if ( $des > 0 ){

				$precio = $precio - $des;
				$costo = $precio - ( ( ( ( $precio * $tasa ) / 100 ) * $tasa_iva ) / 100 );

			}else{

				$costo = $precio - ( ( ( ( $precio * $tasa ) / 100 ) * $tasa_iva ) / 100 );
			}



		}else{

			if ( $des > 0 ) {

				$precio = $precio - $des;
				$costo = $precio - ( ($precio * $tasa_iva ) / 100 );

			}else{

				$costo = $precio - ( ($precio * $tasa_iva ) / 100 );
			}
			
		}

		return round($costo, $DECI_MALES);

    }

    // RETORNA EL DESCUENTO POR UNIDAD
    public function validateDesc($des, $cant){

        $DECI_MALES =  $this->ci->generic->getDecimals();

        $desc = 0;

        $desc = ($des / $cant);


        return round($desc, $DECI_MALES);
    }

}