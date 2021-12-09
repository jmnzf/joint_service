<?php
// MODELO DE APROBACIONES
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class TypeRt extends REST_Controller {

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
  public function getTypeRetention_get(){

    $sqlSelect = "SELECT * FROM ttrt";

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

	public function getTypeRetentionById_get(){
		$Data = $this->get();
		$sqlSelect = "SELECT * FROM ttrt WHERE trt_id = :trt_id AND trt_status = :trt_status";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':trt_id'=>$Data['trt_id'], ':trt_status' => 1));

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
  public function setTypeRetention_post(){
    $Data = $this->post();

    if( !isset($Data['trt_description']) ){

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' =>'La informacion enviada no es valida'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }

    $sqlInsert = "INSERT INTO ttrt (trt_description,trt_status) VALUES (:trt_description, :trt_status)";

    $resInsert = $this->pedeo->insertRow($sqlInsert, array(
      ':trt_description' => $Data['trt_description'],
			':trt_status' => $Data['trt_status']
    ));

    if(is_numeric($resInsert) && $resInsert > 0){
      $respuesta = array(
        'error' => false,
        'data'  => $resInsert,
        'mensaje' =>'Registro agregado satisfactoriamente'
      );
    }else{
      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' =>'No se pudo realizar la operacion'
      );
    }
     $this->response($respuesta);
  }

  public function updateTypeRetention_post(){
    $Data = $this->post();

    if( !isset($Data['trt_description']) OR !isset($Data['trt_status']) ){
      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' =>'La informacion enviada no es valida'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }
    $sqlUpdate = "UPDATE ttrt SET trt_description = :trt_description, trt_status = :trt_status WHERE trt_id = :trt_id";
    $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
    ':trt_description' => $Data['trt_description'],
		':trt_status' => $Data['trt_status'],
    ':trt_id' 		=> $Data['trt_id']
    ));


	  if(is_numeric($resUpdate) && $resUpdate == 1){

	    $respuesta = array(
	    	'error'   => false,
	    	'data'    => $resUpdate,
	    	'mensaje' =>'Operacion exitosa'
	    	);


	  }else{

	    	$respuesta = array(
	    		'error'   => true,
	    	'data'    => $resUpdate,
	    		'mensaje'	=> 'No se puedo actualizar el tipo de retencion'
	    	);

	    }

	   $this->response($respuesta);
  }

}
