<?php
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
         !isset($DataCurrency['Pgm_Enabled'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlInsert = "INSERT INTO pgec(pgm_name_moneda,  pgm_symbol,  pgm_enabled)
                   VALUES(:Pgm_NameMoneda, :Pgm_Symbol, :Pgm_Enabled)";


      $resInsert = $this->pedeo->insertRow($sqlInsert, array(

            ':Pgm_NameMoneda' => $DataCurrency['Pgm_NameMoneda'],
            ':Pgm_Symbol'     => $DataCurrency['Pgm_Symbol'],
            ':Pgm_Enabled'    => 1
      ));

      if($resInsert > 0 ){

            $respuesta = array(
              'error' => false,
              'data' => $resInsert,
              'mensaje' =>'Moneda registrada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data' => array(),
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
         !isset($DataCurrency['Pgm_IdMoneda'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlUpdate = "UPDATE pgec SET pgm_name_moneda = :Pgm_NameMoneda, pgm_symbol = :Pgm_Symbol, pgm_enabled = :Pgm_Enabled WHERE pgm_id_moneda = :Pgm_IdMoneda";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

            ':Pgm_NameMoneda' => $DataCurrency['Pgm_NameMoneda'],
            ':Pgm_Symbol'     => $DataCurrency['Pgm_Symbol'],
            ':Pgm_Enabled'    => $DataCurrency['Pgm_Enabled'],
            ':Pgm_IdMoneda'   => $DataCurrency['Pgm_IdMoneda']
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
}
