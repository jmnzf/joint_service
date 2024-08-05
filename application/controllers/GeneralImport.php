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
                    'mensaje'	=> 'No se pudo importar los datos'
                );

                $this->response($respuesta);

                return;
            }
        }

        $this->pedeo->trans_commit();

            $respuesta = array(
            'error' => false,
            'data' => [],
            'mensaje' =>'Datos importados con exito'
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

    public function get_TableInfo_post(){
        $Data = $this->post();

        $sqlSelect = "SELECT 
        table_info.column_name, 
        case 
        when table_info.is_nullable = 'YES' then 1 
        ELSE  0 
        end required
         FROM 
        information_schema.columns as table_info
        join information_schema.key_column_usage as key_info 
        on table_info.table_name = key_info.table_name
        where
        table_info.table_name = :table and table_info.column_name <> key_info.column_name";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":table" => $Data['table']));

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