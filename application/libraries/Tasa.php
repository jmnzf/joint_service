<?php
defined('BASEPATH') OR exit('No direct script access allowed');


class Tasa {

    private $ci;
    private $pdo;

	public function __construct(){

        header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
        header("Access-Control-Allow-Origin: *");

        $this->ci =& get_instance();
        $this->ci->load->database();
        $this->pdo = $this->ci->load->database('pdo', true)->conn_id;
        $this->ci->load->library('pedeo', [$this->pdo]);

	}

	//CABECERA DEL DOCUMENTO AL COPIAR
	public function Tasa($currency,$docdate){

		// PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO
		// SE BUSCA LA MONEDA LOCAL PARAMETRIZADA
		$sqlMonedaLoc = "SELECT pgm_symbol FROM pgec WHERE pgm_principal = :pgm_principal";
		$resMonedaLoc = $this->ci->pedeo->queryTable($sqlMonedaLoc, array(':pgm_principal' => 1));

		if (isset($resMonedaLoc[0])) {
		} else {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro la moneda local.'
			);


			return $respuesta;
		}

		$MONEDALOCAL = trim($resMonedaLoc[0]['pgm_symbol']);

		// SE BUSCA LA MONEDA DE SISTEMA PARAMETRIZADA
		$sqlMonedaSys = "SELECT pgm_symbol FROM pgec WHERE pgm_system = :pgm_system";
		$resMonedaSys = $this->ci->pedeo->queryTable($sqlMonedaSys, array(':pgm_system' => 1));

		if (isset($resMonedaSys[0]) && !empty($resMonedaSys[0])) {
		} else {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro la moneda de sistema.'
			);


			return $respuesta;
		}


		$MONEDASYS = trim($resMonedaSys[0]['pgm_symbol']);

		//SE BUSCA LA TASA DE CAMBIO CON RESPECTO A LA MONEDA QUE TRAE EL DOCUMENTO A CREAR CON LA MONEDA LOCAL
		// Y EN LA MISMA FECHA QUE TRAE EL DOCUMENTO
		$sqlBusTasa = "SELECT tsa_value FROM tasa WHERE tsa_enabled = :tsa_enabled AND TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date ";
		$resBusTasa = $this->ci->pedeo->queryTable($sqlBusTasa, array(':tsa_enabled' => 1,':tsa_curro' => $MONEDALOCAL, ':tsa_currd' => $currency, ':tsa_date' => $docdate));

		
		if (isset($resBusTasa[0]) && !empty($resBusTasa[0])) {
			
		} else {
			
			if (trim($currency) != $MONEDALOCAL) {
				
				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'No se encontro la tasa de cambio para la moneda: ' . $currency. ' en la actual fecha del documento: ' . $docdate . ' y la moneda local: ' . $resMonedaLoc[0]['pgm_symbol']
				);
				return $respuesta;
			}
		}


		$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE tsa_enabled = :tsa_enabled AND TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
		$resBusTasa2 = $this->ci->pedeo->queryTable($sqlBusTasa2, array(':tsa_enabled' => 1,':tsa_curro' => $MONEDALOCAL, ':tsa_currd' => $MONEDASYS, ':tsa_date' => $docdate));
		
		if (isset($resBusTasa2[0]) ) {
		} else {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :' . $docdate
			);


			return $respuesta;
		}
		
		if(isset($resBusTasa2[0]) && isset($resBusTasa[0]) ){
			$respuesta = array(
				'tasaLocal' => isset($resBusTasa[0]['tsa_value']) ? $resBusTasa[0]['tsa_value'] : 1, 
				'tasaSys' => $resBusTasa2[0]['tsa_value'],
				'curLocal' =>$MONEDALOCAL,
				'curSys' => $MONEDASYS
			);
	
			return $respuesta;
		}else{
			$respuesta = array(
				'tasaLocal' => isset($resBusTasa[0]['tsa_value']) ? $resBusTasa[0]['tsa_value'] : 1, 
				'tasaSys' => isset($resBusTasa2[0]['tsa_value']) ? $resBusTasa2[0]['tsa_value'] : 1,
				'curLocal' =>$MONEDALOCAL,
				'curSys' => $MONEDASYS
			);
	
			return $respuesta;
		}

		
	}
}