<?php
// MODELO DE APROBACIONES
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Params extends REST_Controller {

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

  public function getParams_get(){
	  $sqlSelect = "SELECT * FROM params";

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

	public function getDecimals_get(){

		$sqlSelect = "SELECT coalesce(decimals, 0) as decimals FROM params";

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


  public function setParams_post(){
      $Data = $this->post();
      $cm = 0;

      if( !isset($Data['main_folder']) OR !isset($Data['fixrate'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlInsert = "INSERT INTO params(main_folder, fixrate)
                    VALUES (:main_folder, :fixrate)";


      $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            ':main_folder'    => $Data['main_folder'],
            ':fixrate'   => $Data['fixrate']
      ));


      if(is_numeric($resInsert) && $resInsert > 0){
        $respuesta = array(
          'error' => false,
          'data'  => $resInsert,
          'mensaje' => '');
      }else{
        $respuesta = array(
          'error' => false,
          'data'  => $resInsert,
          'mensaje' => 'No se pudo realizar la operacion');
      }
    }

  public function updateParams_post(){
      $Data = $this->post();
      if( !isset($Data['main_folder']) OR !isset($Data['fixrate'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlUpdate = "UPDATE params SET main_folder = :main_folder, fixrate = :fixrate	WHERE main_folder = :main_folder";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

            ':main_folder' => $Data['main_folder'],
            ':fixrate' 		=> $Data['fixrate']
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
              'mensaje'	=> 'No se puedo realizar la operacion'
            );

      }

     $this->response($respuesta);

  }
}
