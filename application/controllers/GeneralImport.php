<?php
// FACTURA DE COMPRAS
defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class GeneralImport extends REST_Controller {

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

    public  function Import_post(){
        $Data = $this->post();
        
        $table = $Data['table'];
        $targets = json_decode($Data['headers'],true);
        $headers = implode(",", $targets);
        $values = ":".implode(",:", $targets);

       
        $this->pedeo->trans_begin();

        $sqlInsert = "INSERT INTO {$table} ({$headers}) VALUES ({$values})";
        
        $data = json_decode($Data['data'],true);

        // print_r($sqlInsert);exit;
        foreach ($data as $key => $value) {
            
            $resInsert = $this->pedeo->insertRow($sqlInsert,$this->dataFormat($value));

            if(is_numeric($resInsert) && $resInsert > 0){

            }else{

                $this->pedeo->trans_rollback();

                $respuesta = array(
                    'error'   => true,
                    'data'    => $resInsert,
                    'mensaje'	=> 'No se pudo registrar el asiento contable'
                );

                $this->response($respuesta);

                return;
            }
        }

        $this->pedeo->trans_commit();

            $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' =>'Asiento contable registrado con exito'
            );

        $this->response($respuesta);
    }

    public function get_forms_get(){
        $sqlSelect = "SELECT * FROM tbif where bif_status <> 0";

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

    private function dataFormat($data){
        $dataformated =  array();
       foreach ($data as $key => $item) {
         $dataformated[":".$key] = $data[$key]; 
       }

       return $dataformated;
    }
}