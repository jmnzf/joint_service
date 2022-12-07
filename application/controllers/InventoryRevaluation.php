<?php
// REVALORIZACION DE INVENTARIO

defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class InventoryRevaluation extends REST_Controller {

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

  //CREAR NUEVA REVALORIZACION
  public function getinventoryRevaluation_get()
  {
	$sqlSelect = " SELECT * FROM diri";

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

  public function createRevaluation_post(){

		$Data = $this->post();

		$sqlInset = "INSERT INTO diri (iri_card_code, iri_card_name, iri_docdate, iri_duedate, iri_duedev) VALUES(:iri_card_code, :iri_card_name, :iri_docdate, :iri_duedate, :iri_duedev)";
		$this->pedeo->trans_begin();
		$resInsert = $this->pedeo->insertRow($sqlInset, array(
			":iri_card_code" => $Data['iri_card_code'], 
			":iri_card_name" => $Data['iri_card_name'], 
			":iri_docdate" => $Data['iri_docdate'], 
			":iri_duedate" => $Data['iri_duedate'], 
			":iri_duedev" => $Data['iri_duedev']
			));

		 if(is_numeric($resInsert)  && $resInsert > 0){

			$sqlDetail = "INSERT INTO iri1 (ri1_item_code,ri1_item_name,ri1_wsh_code,ri1_quantity,ri1_actual_cost,ri1_new_cost,ri1_increase_account,ri1_declining_account,ri1_ccost,ri1_ubussines,ri1_project, ri1_docentry, ri1_ubication, ri1_total) VALUES
						(:ri1_item_code,:ri1_item_name,:ri1_wsh_code,:ri1_quantity,:ri1_actual_cost,:ri1_new_cost,:ri1_increase_account,:ri1_declining_account,:ri1_ccost,:ri1_ubussines,:ri1_project, :ri1_docentry , :ri1_ubication, :ri1_total)";

			$ContenidoDetalle = json_decode($Data['detail'], true);

			if (!is_array($ContenidoDetalle)) {
				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'No se encontro el detalle'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			// SE VALIDA QUE EL DOCUMENTO TENGA CONTENIDO
			if (!intval(count($ContenidoDetalle)) > 0) {
				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'Documento sin detalle'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			foreach ($ContenidoDetalle as $key => $detail) {
				$resInsertDetail = $this->pedeo->insertRow($sqlDetail,array(
					":ri1_item_code" => $detail['ri1_item_code'],
					":ri1_item_name" => $detail['ri1_item_name'],
					":ri1_wsh_code" => $detail['ri1_wsh_code'],
					":ri1_quantity" => $detail['ri1_quantity'],
					":ri1_actual_cost" => $detail['ri1_actual_cost'],
					":ri1_new_cost" => $detail['ri1_new_cost'],
					":ri1_increase_account" => $detail['ri1_increase_account'],
					":ri1_declining_account" => $detail['ri1_declining_account'],
					":ri1_ccost" => $detail['ri1_ccost'],
					":ri1_ubussines" => $detail['ri1_ubussines'],
					":ri1_project" => $detail['ri1_project'],
					":ri1_docentry" => $resInsert,				
					":ri1_ubication" => $detail['ri1_ubication'],				
					":ri1_total" => $detail['ri1_total']				
				));

				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle de la cotizacion se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertDetail,
						'mensaje'	=> 'No se pudo registrar la revalorizacion'
					);

					$this->response($respuesta);

					return;
				}
			}

			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'Operacion exitosa'
			);

			$this->response($respuesta);
		 }else{
			$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data' => $resInsert,
					'mensaje'	=> 'No se pudo registrar la revalorizacion'
				);

				$this->response($respuesta);

				return;
		 }


		
  }

}
