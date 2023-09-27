<?php
// DATOS FORMA DE PAGO
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class DistributionPercentage extends REST_Controller {

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

    public function createDistPorcent_post()
    {
        $Data = $this->post();

        if(!isset($Data['business']) OR 
            !isset($Data['branch']) OR 
            !isset($Data['bpd_porcent']) OR 
            !isset($Data['bpd_status'])){

            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'Información enviada inválida'
            );

            return $this->response($respuesta);
        }

        //
        $insert = "INSERT INTO tbpd (bpd_porcent,bpd_status,business,branch) VALUES (:bpd_porcent,:bpd_status,:business,:branch)";

        $resInsert = $this->pedeo->insertRow($insert,array(
            ':bpd_porcent' => $Data['bpd_porcent'], 
            ':bpd_status' => $Data['bpd_status'], 
            ':business' => $Data['business'], 
            ':branch' => $Data['branch'] 
        ));

        if(is_numeric($resInsert) && $resInsert > 0){

        }else{
            $respuesta = array(
                'error' => true,
                'data' => $resInsert,
                'mensaje' => 'No se puedo registrar el porcentaje'
            );

        }
        $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' => 'Porcentaje registrada con exito'
        );

        $this->response($respuesta);
    }

    public function getDistPorcent_get()
    {
        $Data = $this->get();
        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'No se encontraron datos en la busqueda'
        );
        //
        $sql = "SELECT * FROM tbpd";
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

    public function updateDistPorcent_post()
    {
        $Data = $this->post();

        if(!isset($Data['business']) OR
            !isset($Data['branch']) OR
            !isset($Data['bpd_id'])){

                $respuesta = array(
                    'error' => true,
                    'data' => [],
                    'mensaje' => 'Informacion enviada es invalida'
                );
                return $this->response($respuesta);
            }
        

        $update = "UPDATE 
                        tbpd 
                    SET 
                        bpd_porcent = :bpd_porcent,
                        bpd_status = :bpd_status
                    WHERE bpd_id = :bpd_id";
        
        $resUpdate = $this->pedeo->updateRow($update,array(
            ':bpd_porcent' => $Data['bpd_porcent'], 
            ':bpd_status' => $Data['bpd_status'], 
            ':bpd_id' => $Data['bpd_id']
        ));
        
        if(is_numeric($resUpdate) && $resUpdate == 1){
            $respuesta = array(
                'error' => false,
                'data' => $resUpdate,
                'mensaje' => 'Porcentaje Actualizado'
            );
        }else {
            $respuesta = array(
                'error' => true,
                'data' => $resUpdate,
                'mensaje' => 'No se pudo actualizar el porcentaje'
            );
        }

        $this->response($respuesta);
    }
}