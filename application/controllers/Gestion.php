<?php
// DATOS MAESTROS ACIENTOS CONTABLES
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class Gestion extends REST_Controller
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

	public function get_DocumentList_get()
	{

		$sqlSelect = "SELECT * FROM dmdt";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array());

		if (isset($resSelect[0])) {
			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {
			$respuesta = array(
				'error' => true,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		}
		$this->response($respuesta);
	}

	public function setGestion_post()
	{

		$Data = $this->post();
		if (
			!isset($Data['mgt_type']) or
			!isset($Data['mgt_issue']) or
			!isset($Data['mgt_userto']) or
			!isset($Data['mgt_userfrom']) or
			!isset($Data['mgt_sn']) or
			!isset($Data['mgt_date']) or
			!isset($Data['mgt_duedate']) or
			!isset($Data['mgt_priority']) or
			!isset($Data['mgt_content']) or
			!isset($Data['mgt_comments']) or
			!isset($Data['mgt_num'])
		) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$respuesta = array();
		$sqlInsert = "INSERT INTO dmgt ( mgt_type, mgt_issue, mgt_userfrom, mgt_userto, mgt_sn, mgt_contact, mgt_date, mgt_duedate, mgt_priority, mgt_content, mgt_dclass, mgt_dnumber,mgt_comments,mgt_centerc,mgt_docnum)
		VALUES ( :mgt_type, :mgt_issue, :mgt_userfrom, :mgt_userto, :mgt_sn, :mgt_contact, :mgt_date, :mgt_duedate, :mgt_priority, :mgt_content, :mgt_dclass, :mgt_dnumber,:mgt_comments,:mgt_centerc,:mgt_docnum )";

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			":mgt_type" => $Data['mgt_type'],
			":mgt_issue" => $Data['mgt_issue'],
			":mgt_userfrom" =>  $Data['mgt_userto'],
			":mgt_userto" => $Data['mgt_userfrom'],
			":mgt_sn" => $Data['mgt_sn'],
			":mgt_contact" => (isset($Data['mgt_contact']) ? $Data['mgt_contact'] : NULL),
			":mgt_date" => $Data['mgt_date'],
			":mgt_duedate" => $Data['mgt_duedate'],
			":mgt_priority" => $Data['mgt_priority'],
			":mgt_content" => $Data['mgt_content'],
			":mgt_dclass" => isset($Data['mgt_dclass']) ? $Data['mgt_dclass'] :0,
			":mgt_dnumber" => isset($Data['mgt_dnumber']) ? $Data['mgt_dnumber'] :0,
			":mgt_comments" => $Data['mgt_comments'],
			":mgt_centerc" => $Data['mgt_centerc'],
			":mgt_docnum" => (!empty($Data['mgt_num']) ? $Data['mgt_num'] : 1)
		));

		if (is_numeric($resInsert) && $resInsert > 0) {
			$respuesta = array(
				'error' => false,
				'data'  => $resInsert,
				'mensaje' => 'Operacion exitosa'
			);
		} else {

			$respuesta = array(
				'error' => true,
				'data'  => $resInsert,
				'mensaje' => 'No se pudo realizar la operacion'
			);
		}

		$this->response($respuesta);
	}

	public function getDocuments_post()
	{

		$Data = $this->post();

		$type = $Data['doctype'];
		$tables = array(
			'1' =>  array('table' => 'dvct', 'prefix' => 'dvc', 'detailTable' => 'vct1', 'detailPrefix' => 'vc1'),
			'2' =>  array('table' => 'dvov', 'prefix' => 'vov', 'detailTable' => 'vov1', 'detailPrefix' => 'ov1'),
			'3' =>  array('table' => 'dvec', 'prefix' => 'vec', 'detailTable' => 'vec1', 'detailPrefix' => 've1'),
			'4' =>  array('table' => 'dvdv', 'prefix' => 'vdv', 'detailTable' => 'vdv1', 'detailPrefix' => 'dv1'),
			'5' =>  array('table' => 'dvfv', 'prefix' => 'dvf', 'detailTable' => 'vfv1', 'detailPrefix' => 'fv1'),
			'6' =>  array('table' => 'dvnc', 'prefix' => 'vnc', 'detailTable' => 'vnc1', 'detailPrefix' => 'nc1'),
			'7' =>  array('table' => 'dvnd', 'prefix' => 'vnd', 'detailTable' => 'vnd1', 'detailPrefix' => 'nd1'),
			'8' =>  array('table' => 'dvem', 'prefix' => 'vem', 'detailTable' => 'vem1', 'detailPrefix' => 'em1'),
			'10' =>  array('table' => 'dcsc', 'prefix' => 'csc', 'detailTable' => 'csc1', 'detailPrefix' => 'sc1'),
			'11' =>  array('table' => 'dcoc', 'prefix' => 'coc', 'detailTable' => 'coc1', 'detailPrefix' => 'oc1'),
			'12' =>  array('table' => 'dcpo', 'prefix' => 'cpo', 'detailTable' => 'cpo1', 'detailPrefix' => 'po1'),
			'13' =>  array('table' => 'dcec', 'prefix' => 'cec', 'detailTable' => 'cec1', 'detailPrefix' => 'ce1'),
			'14' =>  array('table' => 'dcdc', 'prefix' => 'cdc', 'detailTable' => 'cdc1', 'detailPrefix' => 'dc1'),
			'15' =>  array('table' => 'dcfc', 'prefix' => 'cfc', 'detailTable' => 'cfc1', 'detailPrefix' => 'fc1'),
			'16' =>  array('table' => 'dcnc', 'prefix' => 'cnc', 'detailTable' => 'cnc1', 'detailPrefix' => 'nc1'),
			'17' =>  array('table' => 'dcnd', 'prefix' => 'cnd', 'detailTable' => 'cnd1', 'detailPrefix' => 'nd1'),
			'18' =>  array('table' => 'dacc', 'prefix' => 'acc', 'detailTable' => 'acc1', 'detailPrefix' => 'cc1'),
			'19' =>  array('table' => 'gbpe', 'prefix' => 'bpe', 'detailTable' => 'bpe1', 'detailPrefix' => 'pe1'),
			'20' =>  array('table' => 'gbpr', 'prefix' => 'bpr', 'detailTable' => 'bpr1', 'detailPrefix' => 'pr1'),
			'22' =>  array('table' => 'dcrc', 'prefix' => 'crc', 'detailTable' => 'crc1', 'detailPrefix' => 'rc1')
		);
		$table = $tables[$type]['table'];
		$prefix = $tables[$type]['prefix'];
		$sqlSelect = "SELECT {{prefix}}_docnum id ,{{prefix}}_docentry,{{prefix}}_cardname AS text
						FROM {{table}} WHERE {{prefix}}_cardcode = :sn";

		$sqlSelect = str_replace('{{table}}', $table, $sqlSelect);
		$sqlSelect = str_replace('{{prefix}}', $prefix, $sqlSelect);

		// print_r($sqlSelect);exit;
		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":sn" => $Data['sn']));

		if (isset($resSelect[0])) {
			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {
			$respuesta = array(
				'error' => true,
				'data'  => $resSelect,
				'mensaje' => 'Datos no encontrados'
			);
		}
		$this->response($respuesta);
	}

	public function getGestion_get()
	{

		$sqlSelect = "SELECT distinct on (mgt_id) mgt_id as id, dmgt.*,dms_card_name
		from dmgt
		join dmsn on dms_card_code  = mgt_sn";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array());

		if (isset($resSelect[0])) {
			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {
			$respuesta = array(
				'error' => true,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		}
		$this->response($respuesta);
	}
	public function getTypeGestion_get()
	{

		$sqlSelect = "SELECT * from tbtg where btg_status = 1";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array());

		if (isset($resSelect[0])) {
			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {
			$respuesta = array(
				'error' => true,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		}
		$this->response($respuesta);
	}

	public function getSequence_get()
	{

		$sqlSelect = "SELECT (mgt_id + 1) next from dmgt order by mgt_id desc limit 1";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array());

		if (isset($resSelect[0])) {
			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {
			$respuesta = array(
				'error' => true,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		}
		$this->response($respuesta);
	}
}
