<?php
// CONTROLADOR PARA MANEJAR LOS LOTES DE ARTICULOS
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class Lote extends REST_Controller
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

	public function getLotes_get()
	{

		$result = $this->pedeo->queryTable("SELECT * FROM lote", array());
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

	// SE OBTIENEN LOS LOTES POR ARTICULOS
	// ORDENADOS POR LA FECHA DEL MAS 
	// PROXIMO A VENCER
	public function getLotesByItemCode_get()
	{

		$Data = $this->get();

		if (!isset($Data['itemcode']) or !isset($Data['whscode']) or !isset($Data['business']) or !isset($Data['itemcode'])) {
			$this->response(array(
				'error'  => true,
				'data'   => [],
				'mensaje' => 'La informacion enviada no es valida'
			), REST_Controller::HTTP_BAD_REQUEST);

			return;
		}
		$sqlFilterDate = ' AND ote_duedate > current_date ';
		if (isset($Data['filterdate']) && $Data['filterdate']) {
			$sqlFilterDate = '';
		}
		$sql = "";
		$array = [];

		if ($Data['uselote'] == "0") {
			$sql = "SELECT tbdi.bdi_lote as ote_duedate ,tbdi.* FROM tbdi
			WHERE bdi_itemcode = :itemcode
			AND bdi_whscode = :codewarehouse
			AND tbdi.business = :business
			AND bdi_quantity > 0";
			$array =  array(':codewarehouse' => $Data['whscode'], ':business' => $Data['business'], ':itemcode' => $Data['itemcode']);
		} else {

			if (isset($Data['ubication']) && !empty($Data['ubication'])) {

				$sql = "SELECT tbdi.*, lote.ote_code, lote.ote_code as text, lote.ote_createdate, lote.ote_duedate  FROM tbdi 
						INNER JOIN lote
						ON ote_code = bdi_lote
						WHERE bdi_itemcode = :itemcode
						AND bdi_whscode = :whscode
						AND bdi_ubication = :ubication
						AND ote_duedate > current_date
						AND tbdi.business = :business
						AND bdi_quantity > :bdi_quantity
						ORDER BY ote_duedate asc";


				$array = array(
					':itemcode' => $Data['itemcode'],
					':whscode'  => $Data['whscode'],
					':business' 	=> $Data['business'],
					':ubication' 	=> $Data['ubication'],
					':bdi_quantity' => 0
				);
			} else {

				$sql = "SELECT tbdi.*, lote.ote_code, lote.ote_code as text, lote.ote_createdate, lote.ote_duedate  FROM tbdi 
					INNER JOIN lote
					ON ote_code = bdi_lote
					WHERE bdi_itemcode = :itemcode
					AND bdi_whscode = :whscode 
					" . $sqlFilterDate . " AND tbdi.business = :business
					AND bdi_quantity > :bdi_quantity
					ORDER BY ote_duedate asc";

				$array = array(
					':itemcode' => $Data['itemcode'],
					':whscode'  => $Data['whscode'],
					':business' => $Data['business'],
					':bdi_quantity' => 0
				);
			}
		}
		$result = $this->pedeo->queryTable($sql, $array);
		// VALIDAR RETORNO DE DATOS DE LA CONSULTA.
		if (isset($result[0])) {
			//
			$response = array(
				'error'  => false,
				'data'   => $result,
				'mensaje' => ''
			);
		} else {
			$response = array(
				'error'  => true,
				'data'   => [],
				'mensaje' => 'Busqueda sin resultados'
			);
		}
		$this->response($response);
	}

	public function createLote_post()
	{

		$Data = $this->post();
		if ((!isset($Data['ote_code']) and empty($Data['ote_code'])) or
			!isset($Data['ote_createdate']) or
			!isset($Data['ote_createdate'])
		) {
			$this->response(array(
				'error'  => true,
				'data'   => [],
				'mensaje' => 'La informacion enviada no es valida'
			), REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		if ($Data['ote_createdate'] > $Data['ote_duedate']) {

			$this->response(array(
				'error'  => true,
				'data'   => [],
				'mensaje' => 'Las fechas no son validas'
			), REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlInsert = "INSERT INTO lote(ote_code, ote_createdate, ote_duedate)
				VALUES(:ote_code, :ote_createdate, :ote_duedate)";

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			':ote_code' => !isset($value['ote_code']) ? $Data['ote_code'] : NULL,
			':ote_createdate' => ($this->validateDate($Data['ote_createdate'])) ? $Data['ote_createdate'] : NULL,
			':ote_duedate' => ($this->validateDate($Data['ote_duedate'])) ? $Data['ote_duedate'] : NULL
		));

		if (is_numeric($resInsert) && $resInsert > 0) {
		} else {
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
			'mensaje' => 'Lote creado con exito'
		);

		$this->response($respuesta);
	}

	// OBTENER LOS LOTES POR DIA ESPECIFICO
	// Y ARTICULO
	public function getLotesByDay_post() {

		$Data = $this->post();

		if ( !isset($Data['day']) OR !isset($Data['business']) OR !isset($Data['whscode']) OR !isset($Data['itemcode'])){

			$this->response(array(
				'error'  => true,
				'data'   => [],
				'mensaje' => 'La informacion enviada no es valida'
			), REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT bdi_itemcode, bdi_whscode, bdi_lote, bdi_quantity
						FROM tbdi 
						INNER JOIN lote
						ON bdi_lote = ote_code
						WHERE bdi_whscode = :bdi_whscode
						AND bdi_itemcode = :bdi_itemcode
						AND ote_date = :ote_date
 						AND tbdi.business = :business";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(

			':bdi_whscode' => $Data['whscode'],
			':bdi_itemcode' => $Data['itemcode'],
			':ote_date' => $Data['day'],
			':business' => $Data['business']

		));

		if ( isset($resSelect[0]) ){

			$respuesta = array(
				'error'   => false,
				'data'	  => $resSelect,
				'mensaje' => ''
			);

		}else{

			$respuesta = array(
				'error'     => true,
				'data' 		=> [],
				'mensaje'	=> 'Sin resultados en la busqueda'
			);
		}

		$this->response($respuesta);

	}



	private function validateDate($fecha)
	{
		if (strlen($fecha) == 10 or strlen($fecha) > 10) {
			return true;
		} else {
			return false;
		}
	}
}
