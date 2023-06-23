<?php
// DATOS FORMA DE PAGO
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class WayPay extends REST_Controller {

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

  //CREAR NUEVA FORMA DE PAGO
	public function createWayPay_post(){

      $Data = $this->post();

      if(!isset($Data['mpf_name']) OR
         !isset($Data['mpf_days'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

        $sqlInsert = "INSERT INTO dmpf(mpf_name, mpf_days, mpf_pay)
                      VALUES(:mpf_name, :mpf_days, :mpf_pay)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':mpf_name' => $Data['mpf_name'],
              ':mpf_days' => $Data['mpf_days'],
              'mpf_pay'   => $Data['mpf_pay']


        ));

        if(is_numeric($resInsert) && $resInsert > 0){

              $respuesta = array(
                'error' 	=> false,
                'data' 		=> $resInsert,
                'mensaje' =>'Forma de pago registrada con exito'
              );


        }else{

              $respuesta = array(
                'error'   => true,
                'data' 		=> $resInsert,
                'mensaje'	=> 'No se pudo registrar la forma de pago'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR FORMA DE PAGO
  public function updateWayPay_post(){

      $Data = $this->post();

      if(!isset($Data['mpf_name']) OR
         !isset($Data['mpf_days']) OR
         !isset($Data['mpf_id'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlUpdate = "UPDATE dmpf SET mpf_name = :mpf_name, mpf_days = :mpf_days, mpf_pay = :mpf_pay WHERE mpf_id = :mpf_id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
              ':mpf_name' => $Data['mpf_name'],
              ':mpf_days' => $Data['mpf_days'],
              ':mpf_pay'  => $Data['mpf_pay'],
              ':mpf_id'   => $Data['mpf_id']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Impuesto actualizado con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar el impuesto'
            );

      }

       $this->response($respuesta);
  }


  // OBTENER FORMA DE PAGO
  public function getWayPay_get(){

        $sqlSelect = " SELECT * FROM dmpf";

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

	// OBTENER FORMA DE PAGO POR ID
	public function getWayPayById_get(){

				$Data = $this->get();

				if(!isset($Data['mpf_id'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM dmpf WHERE mpf_id = :mpf_id";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":mpf_id" => $Data['mpf_id']));

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
