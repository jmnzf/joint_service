<?php
// PARAMETRIZACION DE MODULARES CLASIFICACION DE PAGINA
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PosBox extends REST_Controller {

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


	// CREAR CAJA
	public function createPosBox_post(){

		$Data = $this->post();


        if(!isset($Data['bcc_description']) OR !isset($Data['bcc_user']) OR !isset($Data['bcc_acount']) OR !isset($Data['business']) OR !isset($Data['branch'])){

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' =>'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

        $sql = "SELECT bcc_user FROM tbcc WHERE bcc_user = :bcc_user AND bcc_status = :bcc_status AND business = :business AND branch = :branch";
        $resSql = $this->pedeo->queryTable($sql, array(
            ':bcc_user' => $Data['bcc_user'],
            ':bcc_status' => 1,
            ':business' => $Data['business'],
            ':branch' => $Data['branch']
        ));

        if (isset($resSql[0])){
            
            $respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' =>'Un usuario solo puede tener una caja asignada'
			);

			return $this->response($respuesta);
        }

        $sqlInsert = "INSERT INTO tbcc (bcc_description, bcc_user, bcc_createdby, bcc_createdat, bcc_acount, bcc_status, business, branch)VALUES(:bcc_description, :bcc_user, :bcc_createdby, :bcc_createdat, :bcc_acount, :bcc_status, :business, :branch)";

        $resSqlInsert = $this->pedeo->insertRow($sqlInsert, array(
            
            ':bcc_description' => $Data['bcc_description'], 
            ':bcc_user' => $Data['bcc_user'], 
            ':bcc_createdby' => $Data['bcc_createdby'], 
            ':bcc_createdat' => date('Y-m-d'),
            ':bcc_acount' => $Data['bcc_acount'], 
            ':bcc_status' => $Data['bcc_status'],
            ':business' => $Data['business'],
            ':branch' => $Data['branch']
        ));

        if (is_numeric($resSqlInsert) && $resSqlInsert > 0){
            $respuesta = array(
				'error' => false,
				'data'  => $resSqlInsert,
				'mensaje' =>'Caja creada con exito'
			);
        }else{
            $respuesta = array(
				'error' => true,
				'data'  => $resSqlInsert,
				'mensaje' =>'No se pudo crear la caja'
			);
        }
		

		$this->response($respuesta);
	}


	// ACTUALIZAR CAJA
	public function updatePosBox_post(){

		$Data = $this->post();
       
		if(!isset($Data['bcc_id']) OR !isset($Data['bcc_description']) OR !isset($Data['bcc_user']) OR !isset($Data['bcc_acount'])){

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' =>'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

        $sql = "SELECT bcc_user FROM tbcc WHERE bcc_id != :bcc_id AND bcc_status = :bcc_status AND business = :business AND branch = :branch";
        $resSql = $this->pedeo->queryTable($sql, array(
            ':bcc_id' => $Data['bcc_id'],
            ':bcc_status' => 1,
            ':business' => $Data['business'],
            ':branch' => $Data['branch']
        ));

        if (isset($resSql[0])){
            
            $respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' =>'Un usuario solo puede tener una caja asignada'
			);

			return $this->response($respuesta);
        }

        $sqlUpdate = "UPDATE tbcc SET  bcc_description =:bcc_description, bcc_user=:bcc_user, bcc_createdby=:bcc_createdby, bcc_createdat=:bcc_createdat, bcc_acount=:bcc_acount, bcc_status=:bcc_status WHERE bcc_id =:bcc_id";

        $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
            ':bcc_id' => $Data['bcc_id'],
            ':bcc_description' => $Data['bcc_description'],
            ':bcc_user' => $Data['bcc_user'],
            ':bcc_createdby' => $Data['bcc_createdby'],
            ':bcc_createdat' => date('Y-m-d'),
            ':bcc_acount' => $Data['bcc_acount'],
            ':bcc_status' => 1
        ));

        if ( is_numeric( $resUpdate ) &&  $resUpdate == 1){

            $respuesta = array(
                'error' => false,
                'data'  => $resUpdate,
                'mensaje' => '');
        }else{
            $respuesta = array(
                'error' => false,
                'data'  => [],
                'mensaje' => 'No se pudo actualizar la caja');
        }
	

		$this->response($respuesta);

	}
	//

  // Lista las cajas creadas
  public function getPosBox_get(){

    $Data = $this->get();

    $sqlSelect = "SELECT * FROM tbcc WHERE business = :business AND branch = :branch";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array(
        ':business' => $Data['business'],
        ':branch' => $Data['branch']
    ));

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
