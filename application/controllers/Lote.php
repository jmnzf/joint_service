<?php
// CONTROLADOR PARA MANEJAR LOS LOTES DE ARTICULOS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Lote extends REST_Controller {

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

	public function createLote_post(){

      $Data = $this->post();

			$sqlInsert = "INSERT INTO lote(ote_code, ote_createdate, ote_duedate)
                  	VALUES(:ote_code, :ote_createdate, :ote_duedate)";

			$resInsert = $this->pedeo->insertRow($sqlInsert, array(
        ':ote_code' => isset($value['ote_code'])?$value['ote_code']:NULL,
        ':ote_createdate' => $this->validateDate($value['ote_createdate']),
        ':ote_duedate' => $this->validateDate($value['ote_duedate'])
			));

			if(is_numeric($resInsert) && $resInsert > 0 ){
			}else{
				$respuesta = array(
					'error'   => true,
					'data' => array(),
					'mensaje'	=> 'No se pudo registrar la tasa'
				);

				$this->response($respuesta);

				return;

			}

      $respuesta = array(
      'error' => false,
      'data' => $resInsert,
      'mensaje' =>'Lote creado con exito'
      );

			$this->response($respuesta);
	}



  private function validateDate($fecha){
      if(strlen($fecha) == 10 OR strlen($fecha) > 10){
        return true;
      }else{
        return false;
      }
  }


}
