<?php
// DATOS MAESTROS ACIENTOS CONTABLES
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Gestion extends REST_Controller {

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

	public function get_BusinessPartnerInfo_post(){
		$Data = $this->post();
		

		$sqlSelect = "SELECT dms_card_name, dms_card_code, dmd_state_mm, dmc_contac_id,
							concat(dmc_name, ' ', dmc_last_name) contacto,dmd_adress,dmc_phone1
					from dmsn
							left join dmsd on dms_card_code = dmd_card_code
							left join dmsc on dmd_card_code = dmc_card_code 
					where dms_card_code = :dmd_card_code";
		
		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":dmd_card_code" => $Data['sn_cardcode']));

		if(isset($resSelect[0])){
			$respuesta = array(
				'error' => false,
				'data'  =>$resSelect,
				'mensaje' => ''
			);
		}else{
			$respuesta = array(
				'error' => true,
				'data'  =>$resSelect,
				'mensaje' => ''
			);
		}
		$this->response($respuesta);
	}

}