<?php
// TRABAJADOR JobTasa : SE ENCARGA DE COLOCAR LA TASA AUTOMATICA
// SEGUN EL DIA ANTERIOR
// BUSCA TODAS LAS TASAS Y LA INSERTA CON LA FECHA DE ACTUAL
defined('BASEPATH') OR exit('No direct script access allowed');
// require_once(APPPATH."/controllers/Lote.php");
require_once(APPPATH.'/controllers/Tasa.php');
// use Restserver\libraries\REST_Controller;


class LevelCash extends Tasa {

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

	public function createLevelCash_post()
    {
        $Data = $this->post();

        if(!isset($Data['business']) OR !isset($Data['branch'])){

            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'Informacion enviada invalida'
            );

            $this->response($respuesta);
            return;
        }

        $insert = "INSERT INTO tbnt (bnt_name,bnt_status,business,branch) VALUES (:bnt_name,:bnt_status,:business,:branch)";
        $resInsert = $this->pedeo->insertRow($insert,array(
            ':bnt_name' => $Data['bnt_name'],
            ':bnt_status' => $Data['bnt_status'],
            ':business' => $Data['business'],
            ':branch' => $Data['branch'],
        ));

        if(is_numeric($resInsert) && $resInsert > 0){
            $respuesta = array(
                'error' => false,
                'data' => $resInsert,
                'mensaje' => 'Nivel de tesoreria registrado con exito'
            ); 
        }else{
            $respuesta = array(
                'error' => true,
                'data' => $resInsert,
                'mensaje' => 'No se pudo registrar el nivel de tesoreria'
            ); 
        }

        $this->response($respuesta);
    }

    public function updateLevelCash_post()
    {
        $Data = $this->post();

        if(!isset($Data['bnt_id'])){
            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'Informacion enviada invalida'
            );

            $this->response($respuesta);
            return;
        }

        $update = "UPDATE tbnt SET bnt_name = :bnt_name, bnt_status = :bnt_status WHERE bnt_id = :bnt_id";
        $resUpdate = $this->pedeo->updateRow($update,array(
            ':bnt_name' => $Data['bnt_name'],
            ':bnt_status' => $Data['bnt_status'],
            ':bnt_id' => $Data['bnt_id']
        ));

        if(is_numeric($resUpdate) && $resUpdate == 1){
            $respuesta = array(
                'error' => false,
                'data' => $resUpdate,
                'mensaje' => 'Nivel de tesoreria actualizado con exito'
            );
        }else{
            $respuesta = array(
                'error' => true,
                'data' => $resUpdate,
                'mensaje' => 'No se pudo actualizar el nivel de tesoreria'
            );
        }

        $this->response($respuesta);
    }

    public function getLevelCash_get()
    {
        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'No se encontraron datos en la busqueda'
        );

        $sql = "SELECT * FROM tbnt";
        $resSql = $this->pedeo->queryTable($sql,array());

        if(isset($resSql[0])){
            $respuesta = array(
                'error' => false,
                'data' => $resSql,
                'mensaje' => 'OK'
            );
        }

        $this->response($respuesta);
    }

}
