<?php
// CONSULTAS DINAMICAS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Geo extends REST_Controller {

	private $pdo_mk;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
        $this->pdo_mk = $this->load->database('mk', true)->conn_id;
        $this->load->library('mk', [$this->pdo_mk]);

	}


    public function getGeoData_get(){
		$request = $this->get();
        $where = "";
        $fields = array();

        if(isset($request['geo_id']) AND !empty($request['geo_id'])){
            $fields['geo_id'] = $request['geo_id'];
            $where = "AND geo_id = :geo_id";
        }

		$sqlSelect ="SELECT * FROM geocercas where 1=1 ".$where;
		$resSelect = $this->mk->queryTable($sqlSelect ,$fields);
		
		
        if(isset($resSelect[0])){
           $result = array_map(function($row) {
                $row['geo_geo'] = json_decode($row['geo_geo'], true); // Decodificar el campo JSON
                return $row;
            }, $resSelect);

			$respuesta = array(
				'error' => false,
				'data'  => $result,
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
}