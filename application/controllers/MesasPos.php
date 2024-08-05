<?php
// CONTROLADOR PARA MANEJAR LOS LOTES DE ARTICULOS
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class MesasPos extends REST_Controller
{

	private $pdo;

	public function __construct()
	{

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
		$this->load->library('pedeo', [$this->pdo]);
	}

	public function getMesasPos_get()
	{
        $Data = $this->get();
        
        $result = $this->pedeo->queryTable("SELECT * FROM tbms WHERE business = :business AND branch = :branch", array(
            ":business" => $Data['business'],
            ":branch"   => $Data['branch']
        ));

		// VALIDAR RETORNO DE DATOS DE LA CONSULTA.
		if (isset($result[0])) {
			//
			$response = array(
				'error'  => false,
				'data'   => $result,
				'mensaje' => ''
			);
		}
		//
		$this->response($response);
	}

	public function createMesasPos_post()
	{

		$Data = $this->post();

		if ((!isset($Data['bms_name']) and empty($Data['bms_status'])) or
			!isset($Data['business']) or
			!isset($Data['branch'])
		) {
			$this->response(array(
				'error'  => true,
				'data'   => [],
				'mensaje' => 'La informacion enviada no es valida'
			), REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

	
		$sqlInsert = "INSERT INTO tbms(bms_name, bms_status, business, branch)
				VALUES(:bms_name, :bms_status, :business, :branch)";

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
        
            ":bms_name" => $Data['bms_name'],
            ":bms_status" => $Data['bms_status'],
            ":business" => $Data['business'],
            ":branch" => $Data['branch']
		));

		if (is_numeric($resInsert) && $resInsert > 0) {
		} else {
			$respuesta = array(
				'error'   => true,
				'data'    => $resInsert,
				'mensaje' => 'No se pudo registrar la mesa'
			);

			$this->response($respuesta);

			return;
		}

		$respuesta = array(
			'error' => false,
			'data' => $resInsert,
			'mensaje' => 'Mesa creada con exito'
		);

		$this->response($respuesta);
	}

	public function updateMesasPos_post() {

		$Data = $this->post();

		if (!isset($Data['bms_name']) or 
            !isset($Data['bms_id']) or
            !isset($Data['bms_status']) or
			!isset($Data['business']) or
			!isset($Data['branch'])
		) {
			$this->response(array(
				'error'  => true,
				'data'   => [],
				'mensaje' => 'La informacion enviada no es valida'
			), REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

        $sqlUpdate = " UPDATE tbms set bms_name = :bms_name, bms_status = :bms_status WHERE bms_id = :bms_id";


        $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
            
            ":bms_name" => $Data['bms_name'],
            ":bms_status" => $Data['bms_status'],
            ":bms_id" => $Data['bms_id'],
           
        ));
		

		if ( is_numeric($resUpdate) && $resUpdate == 1 ){

			$respuesta = array(
				'error'   => false,
				'data'	  => $resUpdate,
				'mensaje' => 'Actualización exitosa'
			);

		}else{

			$respuesta = array(
				'error'     => true,
				'data' 		=> [],
				'mensaje'	=> 'No se pudo actualizar la mesa'
			);
		}

		$this->response($respuesta);

	}

}
