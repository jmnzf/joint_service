<?php
// TRABAJADOR JobTasa : SE ENCARGA DE COLOCAR LA TASA AUTOMATICA
// SEGUN EL DIA ANTERIOR
// BUSCA TODAS LAS TASAS Y LA INSERTA CON LA FECHA DE ACTUAL
defined('BASEPATH') OR exit('No direct script access allowed');
// require_once(APPPATH."/controllers/Lote.php");
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;


class JobTasa extends REST_Controller {

	private $pdo;
	private $controller;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
    	$this->load->library('pedeo', [$this->pdo]);

	}

	public function setTasa_put(){

	    $sql = "SELECT tsa_curro,tsa_value,tsa_currd
							FROM tasa
							WHERE tsa_date = CAST(CURRENT_DATE  - CAST('1 days' AS INTERVAL) AS DATE)";

			$resSql = $this->pedeo->queryTable($sql, array());

			if(isset($resSql[0])){

				$this->pedeo->trans_begin();

				foreach ($resSql as $key => $value) {

							// $sqlInsert = "INSERT INTO tasa(tsa_eq, tsa_curro, tsa_value, tsa_currd, tsa_date, tsa_createby)
							// 							VALUES (:tsa_eq, :tsa_curro, :tsa_value, :tsa_currd, :tsa_date, :tsa_createby)";


							// $resInsert = $this->pedeo->insertRow($sqlInsert, array(
							// 				':tsa_eq'    =>  1,
							// 				':tsa_curro' => $value['tsa_curro'],
							// 				':tsa_value' => $value['tsa_value'],
							// 				':tsa_currd' => $value['tsa_currd'],
							// 				':tsa_createby' => 'system',
							// 				':tsa_date'  => date('Y-m-d')
							// ));

							// if(is_numeric($resInsert) && $resInsert > 0 ){



							// }else{

							// 			$this->pedeo->trans_rollback();

							// 			$respuesta = array(
							// 				'error'   => true,
							// 				'data' => array(),
							// 				'mensaje'	=> 'No se pudo registrar la tasa'
							// 			);

							// 			$this->response($respuesta);

							// 			return;

							// }
				}



				$this->pedeo->trans_commit();

				// $respuesta = array(
				// 'error' => false,
				// 'data' => $resInsert,
				// 'mensaje' =>'Proeso finalizado con exito'
				// );


		}else{

			$respuesta = array(
			'error' => true,
			'data' => [],
			'mensaje' =>'no se encontraron tasas anteriores'
			);

		}

			$this->response($respuesta);
	}

	public function getMetodos_post(){

		// $controller = new lote();

		// $respuesta = $controller->validateDate('2022-11-01');

		

		$this->response($respuesta);
	}
}
