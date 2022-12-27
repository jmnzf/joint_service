<?php
// REVALORIZACION DE INVENTARIO

defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class InventoryRevaluation extends REST_Controller
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

	//CREAR NUEVA REVALORIZACION
	public function getinventoryRevaluation_get()
	{
		$sqlSelect = " SELECT * FROM diri";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array());

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data' => $resSelect,
				'mensaje' => ''
			);

		} else {

			$respuesta = array(
				'error' => true,
				'data' => array(),
				'mensaje' => 'busqueda sin resultados'
			);

		}
		$this->response($respuesta);
	}

	public function createRevaluation_post()
	{
		$Data = $this->post();
		$sqlInset = "INSERT INTO diri (iri_docnum,iri_doctype, iri_series, iri_cardcode, iri_cardname, iri_docdate, iri_duedate, iri_duedev, iri_comment, iri_currency, iri_slpcode, iri_empid) VALUES(:iri_docnum, :iri_doctype, :iri_series, :iri_cardcode, :iri_cardname, :iri_docdate, :iri_duedate, :iri_duedev, :iri_comment, :iri_currency, :iri_slpcode, :iri_empid)";
		$this->pedeo->trans_begin();
		$resInsert = $this->pedeo->insertRow($sqlInset, array(
			":iri_docnum" => $Data['iri_docnum'],
			":iri_doctype" => $Data['iri_doctype'],
			":iri_series" => $Data['iri_series'],
			":iri_cardcode" => $Data['iri_cardcode'],
			":iri_cardname" => $Data['iri_cardname'],
			":iri_docdate" => $Data['iri_docdate'],
			":iri_duedate" => $Data['iri_duedate'],
			":iri_duedev" => $Data['iri_duedev'],
			":iri_comment" => $Data['iri_comment'],
			":iri_currency" => $Data['iri_currency'],
			":iri_slpcode" => $Data['iri_slpcode'],
			":iri_empid" => $Data['iri_empid'],
		)
		);

		if (is_numeric($resInsert) && $resInsert > 0) {

			$sqlDetail = "INSERT INTO iri1 (ri1_itemcode,ri1_itemname,ri1_whscode,ri1_quantity,ri1_actualcost,ri1_newcost,ri1_increase_account,ri1_declining_account,ri1_costcode,ri1_ubusiness,ri1_project, ri1_docentry, ri1_ubication, ri1_linetotal) VALUES
						(:ri1_itemcode,:ri1_itemname,:ri1_whscode,:ri1_quantity,:ri1_actualcost,:ri1_newcost,:ri1_increase_account,:ri1_declining_account,:ri1_costcode,:ri1_ubusiness,:ri1_project, :ri1_docentry , :ri1_ubication, :ri1_linetotal)";

			$ContenidoDetalle = json_decode($Data['detail'], true);

			if (!is_array($ContenidoDetalle)) {
				$respuesta = array(
					'error' => true,
					'data' => array(),
					'mensaje' => 'No se encontro el detalle'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			// SE VALIDA QUE EL DOCUMENTO TENGA CONTENIDO
			if (!intval(count($ContenidoDetalle)) > 0) {
				$respuesta = array(
					'error' => true,
					'data' => array(),
					'mensaje' => 'Documento sin detalle'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			foreach ($ContenidoDetalle as $key => $detail) {
				$resInsertDetail = $this->pedeo->insertRow($sqlDetail, array(
					":ri1_itemcode" => $detail['ri1_itemcode'],
					":ri1_itemname" => $detail['ri1_itemname'],
					":ri1_whscode" => $detail['ri1_whscode'],
					":ri1_quantity" => $detail['ri1_quantity'],
					":ri1_actualcost" => $detail['ri1_actualcost'],
					":ri1_newcost" => $detail['ri1_newcost'],
					":ri1_increase_account" => $detail['ri1_increase_account'],
					":ri1_declining_account" => $detail['ri1_declining_account'],
					":ri1_costcode" => $detail['ri1_ccost'],
					":ri1_ubusiness" => $detail['ri1_ubussines'],
					":ri1_project" => $detail['ri1_project'],
					":ri1_docentry" => $resInsert,
					":ri1_ubication" => $detail['ri1_ubication'],
					":ri1_linetotal" => $detail['ri1_total']
				)
				);


				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle de la cotizacion se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error' => true,
						'data' => $resInsertDetail,
						'mensaje' => 'No se pudo registrar la revalorizacion'

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
		} else {
			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error' => true,
				'data' => $resInsert,
				'mensaje' => 'No se pudo registrar la revalorizacion'
			);

			$this->response($respuesta);

			return;
		}



	}

	public function getInvDetailDetail_get()
	{
		$Data = $this->get();
		$sqlSelect = " SELECT * FROM iri1 WHERE ri1_docentry = :r1_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':r1_docentry' => $Data['ri1_docentry']));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data' => $resSelect,
				'mensaje' => ''
			);

		} else {

			$respuesta = array(
				'error' => true,
				'data' => array(),
				'mensaje' => 'busqueda sin resultados'
			);

		}

		$this->response($respuesta);
	}

}

