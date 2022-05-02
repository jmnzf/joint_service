<?php
// TRAZABILIDAD DE DOCUMENTOS
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class TypeGestion extends REST_Controller
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

    public function getTypeGestion_get()
    {

        $sqlSelect = "SELECT * from tbtg";

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
                'data' => $resSelect,
                'mensaje' => 'Busqueda sin resultados'
            );
        }

        $this->response($respuesta);
    }

    public function setTypeGestion_post(){
        $Data = $this->post();
        $respuesta = array();
        
        if(!isset($Data['btg_name']) OR
            !isset($Data['btg_status'])){
            $this->response(array(
                'error' => true,
                'data' => [],
                'mensaje' => 'No se puede realizar la operacion'
            ), REST_Controller::HTTP_BAD_REQUEST);
        }

        $sqlInsert = "INSERT INTO tbtg (btg_name,btg_status) values (:btg_name, :btg_status)";

        $resInsert = $this->pedeo->insertRow($sqlInsert, 
                                        array(":btg_name" => $Data['btg_name'],
                                              ":btg_status" => $Data['btg_status']));

        if(is_numeric($resInsert) AND $resInsert > 0){
            $respuesta = array(
                'error' => false,
                'data' => $resInsert,
                'mensaje' => 'Operacion exitosa'
            );
        }else{
            $respuesta = array(
                'error' => true,
                'data' => $resInsert,
                'mensaje' => 'No se puede realizar la operacion'
            );
        }
        $this->response($respuesta);

    } 
    
    public function updateTypeGestion_post(){
        $Data = $this->post();
        $respuesta = array();

        $sqlUpdate = "UPDATE tbtg SET btg_name = :btg_name,btg_status = :btg_status WHERE btg_id = :btg_id";

        $resUpdate = $this->pedeo->updateRow($sqlUpdate, 
                                        array(":btg_name" => $Data['btg_name'],
                                              ":btg_status" => $Data['btg_status'],
                                              ":btg_id" => $Data['btg_id']));

        if(is_numeric($resUpdate) AND $resUpdate > 0){
            $respuesta = array(
                'error' => false,
                'data' => $resUpdate,
                'mensaje' => 'Operacion exitosa'
            );
        }else{
            $respuesta = array(
                'error' => true,
                'data' => $resUpdate,
                'mensaje' => 'No se puede realizar la operacion'
            );
        }
        $this->response($respuesta);

    }  
}
