<?php
// CONSULTAS DINAMICAS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Reason extends REST_Controller {

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


    public function getReasons_get(){
        $sqlSelect = "SELECT * FROM tbmt";

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

    public function createReason_post(){
        $Data = $this->post();

        if (!isset($Data['bmt_name']) OR
            !isset($Data['bmt_status']) ) 
            {
            
                $respuesta = array(
                    'error' => true,
                    'data'  => array(),
                    'mensaje' =>'La informacion enviada no es valida'
                );
    
                $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
    
                return;
        }

        $sqlInsert = "INSERT INTO tbmt (bmt_name,bmt_status) values (:bmt_name, :bmt_status)";

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            ":bmt_name" => $Data['bmt_name'],
            ":bmt_status" => $Data['bmt_status']
        ));

        if (is_numeric($resInsert) && $resInsert > 0) {
            $respuesta = array(
                'error' => false,
                'data'  => $resInsert,
                'mensaje' =>'Operacion exitosa'
            );
        } else {
            $respuesta = array(
                'error' => true,
                'data'  => $resInsert,
                'mensaje' =>'No se pudo crear Motivo'
            );
        }

        $this->response($respuesta);
    }

    public function updateReason_post(){
        $Data = $this->post();

        if (!isset($Data['bmt_id']) OR
            !isset($Data['bmt_name']) OR
            !isset($Data['bmt_status']) ) 
            {
            
                $respuesta = array(
                    'error' => true,
                    'data'  => array(),
                    'mensaje' =>'La informacion enviada no es valida'
                );
    
                $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
    
                return;
        }

        $sqlUpdate = "UPDATE tbmt SET bmt_name = :bmt_name , bmt_status = :bmt_status WHERE bmt_id = :bmt_id";

        $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
            ":bmt_name" => $Data['bmt_name'],
            ":bmt_status" => $Data['bmt_status'],
            ":bmt_id" => $Data['bmt_id']
        ));

        if (is_numeric($resUpdate) && $resUpdate > 0) {
            $respuesta = array(
                'error' => false,
                'data'  => $resUpdate,
                'mensaje' =>'Operacion exitosa'
            );
        } else {
            $respuesta = array(
                'error' => true,
                'data'  => $resUpdate,
                'mensaje' =>'No se pudo crear Motivo'
            );
        }

        $this->response($respuesta);
    }
}