<?php
// PARAMETRIZACION DE MODULARES CLASIFICACION DE PAGINA
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class Panuncios extends REST_Controller
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


	// AGREGA UN PARAMETRO GENERAL A UN TIPO DE ANUNCIO
	public function createGeneralParameters_post()
	{

		$Data = $this->post();

		if (!isset($Data['detail'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$ContenidoDetalle = json_decode($Data['detail'], true);


		$this->pedeo->trans_begin();

		$resUpdateType = $this->pedeo->updateRow("UPDATE artp SET rtp_status = :rtp_status WHERE rtp_typeid = :rtp_typeid", array(":rtp_typeid" => $Data['type'], ":rtp_status" => 0));

		if (is_numeric($resUpdateType) && $resUpdateType > 0 || $resUpdateType == 0) {
		} else {

			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data' 		=> $resUpdateType,
				'mensaje'	=> 'No se pudo registrar la relación de  parametros'
			);

			return $this->response($respuesta);
		}

		foreach ($ContenidoDetalle as $key => $value) {

			$sqlInsert = "INSERT INTO artp(rtp_paramid, rtp_typeid, rtp_status)VALUES(:rtp_paramid, :rtp_typeid, :rtp_status)";


			$resInsert = $this->pedeo->insertRow($sqlInsert, array(
				':rtp_paramid' =>  $value,
				':rtp_typeid'  =>  $Data['type'],
				':rtp_status'  => 1
			));



			if (is_numeric($resInsert) && $resInsert > 0) {
			} else {

				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data' 		=> $resInsert,
					'mensaje'	=> 'No se pudo registrar la relación de  parametros'
				);
			}
		}

		$this->pedeo->trans_commit();

		$respuesta = array(
			'error' => false,
			'data' => $resInsert,
			'mensaje' => 'Relación de parametros registrada con exito'
		);

		$this->response($respuesta);
	}


	// SE CREA UNA LISTA DE PARAMETROS ESPECIFICOS PARA UN TIPO DE ANUNCIO
	public function createSpecificParameters_post()
	{

		$Data = $this->post();

		if (!isset($Data['detail'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}


		$ContenidoDetalle = json_decode($Data['detail'], true);

		// INICIA lA TRANSACCION

		$this->pedeo->trans_begin();

		$sqlInsertListaPrecio = "INSERT INTO alpt(lpt_description, lpt_price, lpt_status)VALUES(:lpt_description, :lpt_price, :lpt_status)";

		$resInsertListaPrecio = $this->pedeo->insertRow($sqlInsertListaPrecio, array(

			':lpt_description' => $Data['descripcion'],
			':lpt_price' 			 => $Data['precio'],
			':lpt_status'			 => 1

		));

		if (is_numeric($resInsertListaPrecio) && $resInsertListaPrecio > 0) {

			$sqlInsertEncabezado = "INSERT INTO aepe(epe_name, epe_docdate, epe_duedate, epe_createby, epe_status,epe_typecode,epe_listpcode)VALUES(:epe_name, :epe_docdate, :epe_duedate, :epe_createby, :epe_status,:epe_typecode,:epe_listpcode)";
			$resInsertEncabezado = $this->pedeo->insertRow($sqlInsertEncabezado, array(

				':epe_name' 	  => $Data['descripcion'],
				':epe_docdate'    => date('Y-m-d'),
				':epe_duedate'    => $Data['fechav'],
				':epe_createby'   => $Data['user'],
				':epe_typecode'   => $Data['type'],
				':epe_listpcode'  => $resInsertListaPrecio,
				':epe_status'     => 1
			));

			if (is_numeric($resInsertEncabezado) && $resInsertEncabezado > 0) {

				foreach ($ContenidoDetalle as $key => $value) {
					foreach ($value as $key => $detalle) {
						$sqlInsertDetalleP = "INSERT INTO epe2(pe2_paramcode,pe2_vparamcode,pe2_epeid)VALUES(:pe2_paramcode,:pe2_vparamcode,:pe2_epeid)";
						$resInsertDetalleP = $this->pedeo->insertRow($sqlInsertDetalleP, array(

							':pe2_paramcode' => $detalle['CodigoParametro'],
							':pe2_vparamcode' => $detalle['ValorParametro'],
							':pe2_epeid'     => $resInsertEncabezado
						));
					}
				}

				if (is_numeric($resInsertDetalleP) && $resInsertDetalleP > 0) {
				} else {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' 		=> $resInsertDetalleP,
						'mensaje'	=> 'No se pudo registrar la relación de  parametros especificos'
					);

					return $this->response($respuesta);
				}
			} else {

				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data' 		=> $resInsertEncabezado,
					'mensaje'	=> 'No se pudo registrar la relación de  parametros especificos (Encabezado)'
				);

				return $this->response($respuesta);
			}
		} else {

			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data' 		=> $resInsertListaPrecio,
				'mensaje'	=> 'No se pudo registrar la relación de  parametros especificos'
			);

			return $this->response($respuesta);
		}

		$this->pedeo->trans_commit();

		$respuesta = array(
			'error'   => false,
			'data'    =>  [],
			'mensaje' => 'Relación de parametros especificos registrada con exito'
		);

		$this->response($respuesta);
	}
	//

	// OBTENER TIPOS DE ANUNCIOS
	public function getAdTypes_get()
	{

		$sqlSelect = "SELECT bta_id, bta_name,
            CASE
            	WHEN bta_status = 1 THEN 'Activo'
            	ELSE 'Inactivo'
            END AS bta_status,
			CASE
				WHEN bta_combo = 1 THEN 'SI'
				ELSE 'NO'
			END AS bta_combo,
			bta_libre,
			bta_status,
			bta_palabras,
			bta_letras,
			bta_precio,
			bta_dias,
			bta_cantidad,
			bta_dias_cons
            FROM tbta
			WHERE bta_status = :bta_status";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':bta_status' => 1));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}

	// se obtienen los parametros generales de la clasificacion
	public function getGeneralParameters_get()
	{

		$sqlSelect = "SELECT pgn_id,pgn_name,pgn_table,pgn_prefix,
					CASE
						WHEN pgn_status = 1 THEN 'Activo'
						ELSE 'Inactivo'
					END AS 	pgn_status
					FROM mpgn";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array());

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}

	// se obtienen los parametros generales del tipo de anuncio
	// POR ID DEL TIPO DE ANUNCIO
	public function getGeneralParametersById_get()
	{

		$Data = $this->get();

		$sqlSelect = "SELECT pgn_id,pgn_name,pgn_table,pgn_prefix,
					CASE
						WHEN pgn_status = 1 THEN 'Activo'
					ELSE 'Inactivo'
					END AS 	pgn_status
					FROM artp
					INNER JOIN mpgn
					ON pgn_id = rtp_paramid
					where rtp_typeid = :rtp_typeid";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':rtp_typeid' => $Data['rtp_typeid']));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}

	// se obtienen los parametros especificos de la clasificacion
	// por Id de clasificacion
	public function getSpecificParametersById_get()
	{

		$Data = $this->get();

		$sqlSelect = "SELECT epe_id,epe_name,epe_docdate,epe_duedate,
					 epe_createby, epe_typecode, case when epe_status = 1 then 'Activo' else 'Vencido' end as epe_status 
					 FROM aepe WHERE epe_typecode = :epe_typecode";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':epe_typecode' => $Data['rtp_typeid']));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}

	// SE OBTIENE EL DETALLE DE LOS PARAMETROS
	public function getSpecificParametersDetail_get()
	{

		$Data = $this->get();

		$sqlSelect = "SELECT * FROM mpgn WHERE pgn_id = :pgn_id";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':pgn_id' => $Data['rcp_idp']));

		if (isset($resSelect[0])) {

			$sqlDetalle = "SELECT * FROM " . $resSelect[0]['pgn_table'];

			$resDetalle = $this->pedeo->queryTable($sqlDetalle, array());

			if (isset($resDetalle[0])) {

				$respuesta = array(
					'error' => false,
					'data'  => $resDetalle,
					'mensaje' => ''
				);
			} else {

				$respuesta = array(
					'error'   => true,
					'data' => array(),
					'mensaje'	=> 'busqueda sin resultados'
				);
			}
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}
	// OBTIENE LAS OPCIONES Y EL PRECIOS PARA UN PARAMETRO ESPECIFICO
	public function getOptions_get()
	{

		$Data = $this->get();

		$sqlSelect = "SELECT lpt_price, 
					pgn_name,
					get_params(pgn_table, pgn_prefix, pe2_vparamcode) AS parametro
					FROM aepe
					INNER JOIN alpt
					ON epe_listpcode = lpt_id
					INNER JOIN epe2
					ON epe_id = pe2_epeid
					INNER JOIN mpgn
					ON pe2_paramcode = pgn_id
					WHERE epe_id = :epe_id
					ORDER BY pgn_name";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':epe_id' => $Data['epe_id']));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}

	
}
