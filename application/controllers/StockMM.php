<?php
//ANALISIS DE VENTAS - TIPO INFORME ANALISIS DE ARTICULOS
defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class StockMM extends REST_Controller {

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
    // METODO PARA OBTENER DATOS DE STOCK
    public function getStockMM_get(){
        $sqlSelect = "SELECT * FROM tsmm";

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

    // METODO PARA CREAR
    public function createStockMM_post(){
        $Data = $this->post();
        if (!isset($Data['items'])) {
            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' =>'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $sqlSelect = "SELECT * from tsmm where smm_store = :smm_store ";

        $sqlInsert = "INSERT INTO tsmm(smm_store, smm_max, smm_min, smm_itemcode) 
                      VALUES (:smm_store, :smm_max, :smm_min, :smm_itemcode)";

        $this->pedeo->trans_begin();

        $items = $Data['items'];

       if (is_array($items) and intval(count($items)) > 0 ) {
            
        foreach ($items as $key => $value){

            $resSelect = $this->pedeo->queryTable($sqlSelect, array(':smm_store' => $value['smm_store']));

            if(isset($resSelect[0])){
                $respuesta = array(
                    'error' => true,
                    'data'  => array(),
                    'mensaje' =>'No se puede realizar la operacion'
                );
    
                $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
    
                return;

                $this->pedeo->trans_rollback();
            }
            
                $resInsert = $this->pedeo->insertRow($sqlInsert,
                array(':smm_store' => $value['smm_store'],
                    ':smm_max'    => $value['smm_max'],
                    ':smm_min'   => $value['smm_min'],
                    ':smm_itemcode' => $value['smm_itemcode']));

                if(is_numeric($resInsert) && $resInsert > 0){
                    
                }else{
                    $this->pedeo->trans_rollback();
                    $respuesta = array(
                        'error' => true,
                        'data'  => $resInsert,
                        'mensaje' =>'No se pudo realizar la operacion'
                    );
        
                    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
        
                    return;
                }

            }
       }
       
        $this->pedeo->trans_commit();
        $respuesta = array(
	        'error' => false,
	        'data'  => [],
	        'mensaje' => 'Operacion exitosa');

        $this->response($respuesta);
    }

    // METODO PARA ACTUALIZAR
    public function updateStockMM_post(){
        $Data = $this->post();
        if(!isset($Data['smm_store']) OR
            !isset($Data['smm_min']) OR
            !isset($Data['smm_max']) OR
            !isset($Data['smm_itemcode']) OR
            !isset($Data['smm_id'])){
            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' =>'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $sqlSelect = "SELECT * FROM tsmm WHERE smm_store = :smm_store AND smm_id != :smm_id";      
        // $this->pedeo->trans_begin();
        // foreach ($Data['items'] as $key => $value){

            $resSelect = $this->pedeo->queryTable($sqlSelect, array(':smm_store' => $Data['smm_store'],
                                                                    ':smm_id' => $Data['smm_id']));
            if(isset($resSelect[0])){
                // $this->pedeo->trans_rollback();
                $respuesta = array(
                    'error' => true,
                    'data'  => array(),
                    'mensaje' => 'no es posible actualizar ya existe el almacen : '.$Data['smm_store']);
        
                $this->response($respuesta);
        
                return;

            }

            $sqlUpdate = "UPDATE tsmm set smm_store = :smm_store,
                                      smm_min = :smm_min,
                                      smm_max = :smm_max,   
                                      smm_itemcode = :smm_itemcode
                                    WHERE smm_id = :smm_id";

        
            $resUpdate = $this->pedeo->updateRow($sqlUpdate,array(':smm_store' => $Data['smm_store'],
            ':smm_max' => $Data['smm_max'],
            ':smm_min' => $Data['smm_min'],
            ':smm_itemcode' => $Data['smm_itemcode'],
            ':smm_id' => $Data['smm_id']));

                if (is_numeric($resUpdate) AND $resUpdate > 0) {
                    $respuesta = array(
                        'error' => false,
                        'data'  => [],
                        'mensaje' =>'Operacion Existosa'
                        );
                }else{
                    $this->pedeo->trans_rollback();
                $respuesta = array(
                'error' => true,
                'data'  => $resUpdate,
                'mensaje' =>'No se pudo actualizar'
                );
            }
        
        // }

        // $this->pedeo->trans_commit();
        
        $this->response($respuesta);

    } 

    public function getStockMMByArticle_post(){

        $Data = $this->post();
        
        if(!isset($Data['smm_itemcode'])){
            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' =>'No se puede realizar la operacion'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $sqlSelect = "SELECT * FROM tsmm where smm_itemcode = :smm_itemcode";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':smm_itemcode' => $Data['smm_itemcode']));

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

}