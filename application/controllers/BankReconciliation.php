<?php

// RECONCIALIACION BANCARIA
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class BankReconciliation extends REST_Controller {

	private $pdo;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
    $this->load->library('pedeo', [$this->pdo]);

	}

  //Crear nueva moneda
	public function createCurrency_post(){

      $DataCurrency = $this->post();

      if(!isset($DataCurrency['Pgm_NameMoneda']) OR
         !isset($DataCurrency['Pgm_Symbol']) OR
         !isset($DataCurrency['Pgm_Enabled']) OR
			   !isset($DataCurrency['pgm_principal'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

			if($DataCurrency['pgm_principal'] == 1){

					$sqlValidarPrincipal = " SELECT pgm_id_moneda, pgm_principal FROM pgec WHERE pgm_principal = :pgm_principal ";

					$resValidarPricipal = $this->pedeo->queryTable($sqlValidarPrincipal, array(

										':pgm_principal' => 1
					));

					if(isset($resValidarPricipal[0])){

							$respuesta = array(
								'error'   => true,
								'data' 		=> $resValidarPricipal[0]['pgm_id_moneda'],
								'mensaje'	=> 'No se puede insertar una moneda como principal si ya existe una.'
							);

							$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

							return;

					}
			}


      $sqlInsert = "INSERT INTO pgec(pgm_name_moneda,  pgm_symbol,  pgm_enabled, pgm_principal)
                    VALUES(:Pgm_NameMoneda, :Pgm_Symbol, :Pgm_Enabled, :pgm_principal)";


      $resInsert = $this->pedeo->insertRow($sqlInsert, array(

            ':Pgm_NameMoneda' => $DataCurrency['Pgm_NameMoneda'],
            ':Pgm_Symbol'     => $DataCurrency['Pgm_Symbol'],
            ':Pgm_Enabled'    => 1,
						':pgm_principal'  => $DataCurrency['pgm_principal']
      ));

      if(is_numeric($resInsert) && $resInsert > 0){

            $respuesta = array(
              'error'		=> false,
              'data' 		=> $resInsert,
              'mensaje' =>'Moneda registrada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data' 		=> $resInsert,
              'mensaje'	=> 'No se pudo registrar la moneda'
            );

      }

       $this->response($respuesta);
	}



  // Obtener Cuentas de Banco
  public function getBankAccounts_get(){

        $sqlSelect = "SELECT * FROM dacc WHERE acc_cash = 1 AND acc_digit = 10 AND cast(acc_code AS VARCHAR)  LIKE '1101021%' ORDER BY acc_code ASC";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array());

        if(isset($resSelect[0])){

          $respuesta = array(
            'error' => false,
            'data'  => $resSelect,
            'mensaje' => '');

        }else{

            $respuesta = array(
              'error'   => true,
              'data' => array(),
              'mensaje'	=> 'busqueda sin resultados'
            );

        }

         $this->response($respuesta);
  }


}
