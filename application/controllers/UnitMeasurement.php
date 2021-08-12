<?php
// UNIDAD DE MEDIDAS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class UnitMeasurement extends REST_Controller {

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

  //CREAR NUEVA UNIDAD DE MEDIDA
	public function createUnitMeasurement_post(){

      $Data = $this->post();

      if(!isset($Data['dmu_nameum'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

        $sqlInsert = "INSERT INTO dmum(dmu_nameum)VALUES(:dmu_nameum)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':dmu_nameum' => $Data['dmu_nameum']

        ));

        if($resInsert > 0 ){

              $respuesta = array(
                'error' => false,
                'data' => $resInsert,
                'mensaje' =>'Unidad registrada con exito'
              );


        }else{

              $respuesta = array(
                'error'   => true,
                'data' => array(),
                'mensaje'	=> 'No se pudo registrar la unidad'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR UNIDAD DE MEDIDA
  public function updateUnitMeasurement_post(){

      $Data = $this->post();

      if(!isset($Data['dmu_nameum']) OR !isset($Data['dmu_id'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlUpdate = "UPDATE dmum SET dmu_nameum = :dmu_nameum  WHERE dmu_id = :dmu_id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

              ':dmu_nameum' => $Data['dmu_nameum'],
              ':dmu_id' => $Data['dmu_id']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Unidad actualizada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la unidad'
            );

      }

       $this->response($respuesta);
  }


  // OBTENER UNIDAD DE MEDIDA
  public function getUnitMeasurement_get(){

        $sqlSelect = " SELECT * FROM dmum";

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
