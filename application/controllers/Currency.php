<?php

// MONEDAS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Currency extends REST_Controller {

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

  //Actualizar moneda
  public function updateCurrency_post(){

      $DataCurrency = $this->post();

      if(!isset($DataCurrency['Pgm_NameMoneda']) OR
         !isset($DataCurrency['Pgm_Symbol']) OR
         !isset($DataCurrency['Pgm_Enabled']) OR
         !isset($DataCurrency['Pgm_IdMoneda']) OR
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

					$sqlValidarPrincipal = " SELECT pgm_id_moneda, pgm_principal FROM pgec WHERE pgm_principal = :pgm_principal AND pgm_id_moneda != :pgm_id_moneda";

					$resValidarPricipal = $this->pedeo->queryTable($sqlValidarPrincipal, array(

										':pgm_principal' => 1,
										':pgm_id_moneda' => $DataCurrency['Pgm_IdMoneda']
					));


					if(isset($resValidarPricipal[0])){

								$respuesta = array(
									'error'   => true,
									'data' 		=> $DataCurrency['Pgm_IdMoneda'],
									'mensaje'	=> 'No se puede actualizar una moneda como principal si ya existe una.'
								);

								$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

								return;
					}

			}

      $sqlUpdate = "UPDATE pgec SET pgm_name_moneda = :Pgm_NameMoneda, pgm_symbol = :Pgm_Symbol, pgm_enabled = :Pgm_Enabled, pgm_principal = :pgm_principal WHERE pgm_id_moneda = :Pgm_IdMoneda";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

            ':Pgm_NameMoneda' => $DataCurrency['Pgm_NameMoneda'],
            ':Pgm_Symbol'     => $DataCurrency['Pgm_Symbol'],
            ':Pgm_Enabled'    => $DataCurrency['Pgm_Enabled'],
            ':Pgm_IdMoneda'   => $DataCurrency['Pgm_IdMoneda'],
						':pgm_principal'  => $DataCurrency['pgm_principal']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Moneda actualizada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la informacion de la moneda'
            );

      }

       $this->response($respuesta);
  }

  // Obtener Monedas
  public function getCurrency_get(){

        $sqlSelect = " SELECT * FROM pgec";

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

  // Obtener Sucursal por Id
  public function getCurrencyById_get(){

        $Data = $this->get();

        if(!isset($Data['Pgm_IdMoneda'])){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = " SELECT * FROM pgec WHERE pgm_id_moneda = :Pgm_IdMoneda";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':Pgm_IdMoneda' => $Data['Pgm_IdMoneda']));

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

	//SE OBTIENEN LAS MONEDAS EXTRANJERAS
	public function getIsCurrency_get(){

		$sqlSelect = "SELECT * FROM  pgec WHERE pgm_iscurrency = 1";

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

	//

  //Actualiza el estado de una moneda
   public function updateStatus_post(){

  	 			$Data = $this->post();

  				if(!isset($Data['Pgm_IdMoneda']) OR !isset($Data['Pgm_Enabled'])){

  						$respuesta = array(
  							'error' => true,
  							'data'  => array(),
  							'mensaje' =>'La informacion enviada no es valida'
  						);

  						$this->response($respuesta,  REST_Controller::HTTP_BAD_REQUEST);
  						return;
  				}

  				$sqlUpdate = "UPDATE pgec SET pgm_enabled = :Pgm_Enabled WHERE pgm_id_moneda = :Pgm_IdMoneda";


  				$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

  											':Pgm_Enabled' => $Data['Pgm_Enabled'],
  											':Pgm_IdMoneda' => $Data['Pgm_IdMoneda']

  				));


  				if(is_numeric($resUpdate) && $resUpdate == 1){

  							$respuesta = array(
  								'error'   => false,
  								'data'    => $resUpdate,
  								'mensaje' =>'Moneda actualizada con exito'
  							);


  				}else{

  							$respuesta = array(
  								'error'   => true,
  								'data'    => $resUpdate,
  								'mensaje'	=> 'No se pudo actualizar la informacion de la moneda'
  							);

  				}

  				 $this->response($respuesta);

   }

	 // CONSULTAR LA TASA SEGUN FECHA
	 public function getTasaMLS_get(){

				 	$Data = $this->get();

					$fecha = date('Y-m-d');
					$moneda = "";


					if(isset($Data['fecha']) && !empty($Data['fecha'])){

							$fecha = $Data['fecha'];

					}

					if(isset($Data['moneda']) && !empty($Data['moneda'])){

							$moneda = $Data['moneda'];

					}else{

						$sqlMonedaLoc = "SELECT pgm_symbol FROM pgec WHERE pgm_principal = :pgm_principal";
						$resMonedaLoc = $this->pedeo->queryTable($sqlMonedaLoc, array(':pgm_principal' => 1));

						if ( isset($resMonedaLoc[0]) ){

							$moneda =  $resMonedaLoc[0]['pgm_symbol'];

						}else{

							$respuesta = array(
								'error' => true,
								'data'  => array(),
								'mensaje' =>'No se encrontro la tasa de cambio'
							);
							$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
							return;
						}
					}

					$sqlBusTasa = "SELECT coalesce( get_tax_currency(:moneda, :fecha), 0) AS tsa_value";

					$resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(
						':moneda' => $moneda,
						':fecha' => $fecha

				  ));



					if(  isset($resBusTasa[0]) &&  $resBusTasa[0]['tsa_value'] > 0 ){

						$respuesta = array(
							'error'   => false,
							'data'    => $resBusTasa,
							'mensaje' => ''
						);


					}else{

						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encrontro la tasa de cambio'
						);


					}

					 $this->response($respuesta);
	 }
}
