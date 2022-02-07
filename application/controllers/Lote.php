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

	public function getLotes_get(){
	
		$result = $this->pedeo->queryTable("SELECT * FROM lote", array());
		// VALIDAR RETORNO DE DATOS DE LA CONSULTA.
		if(isset($result[0])){
			//
			  $response = array(
				'error'  => false,
				'data'   => $result,
				'mensaje'=> ''
			);
		}
		//
		 $this->response($response);
	}

	public function createLote_post(){

     	 $Data = $this->post();
		  if( (!isset($Data['ote_code']) AND empty($Data['ote_code'])) OR
				!isset($Data['ote_createdate']) OR
				!isset($Data['ote_createdate'])
			){
			$this->response(array(
				'error'  => true,
				'data'   => [],
				'mensaje'=>'La informacion enviada no es valida'
			), REST_Controller::HTTP_BAD_REQUEST);

			return ;
			}

			if($Data['ote_createdate']>$Data['ote_duedate']){

				$this->response(array(
					'error'  => true,
					'data'   => [],
					'mensaje'=>'Las fechas no son validas'
				), REST_Controller::HTTP_BAD_REQUEST);
	
				return ;
			}

		$sqlInsert = "INSERT INTO lote(ote_code, ote_createdate, ote_duedate)
				VALUES(:ote_code, :ote_createdate, :ote_duedate)";

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
        ':ote_code' => !isset($value['ote_code'])?$Data['ote_code']:NULL,
        ':ote_createdate' => ($this->validateDate($Data['ote_createdate']))?$Data['ote_createdate']:NULL,
        ':ote_duedate' => ($this->validateDate($Data['ote_duedate']))? $Data['ote_duedate']:NULL
		));

		if(is_numeric($resInsert) && $resInsert > 0 ){
			
		}else{
			$respuesta = array(
				'error'   => true,
				'data' => $resInsert,
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
