<?php
// DATOS MAESTROS  REGIMEN FISCAL
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class FiscalResponsibility extends REST_Controller {

	private $pdo;

	public function __construct() {

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
        $this->load->library('pedeo', [$this->pdo]);

	}

    // CREAR NUEVO REGIMEN
	public function createFiscalResponsibility_post(){

        $Data = $this->post();
    
        if(
            !isset($Data['prf_code']) OR
            !isset($Data['prf_name']) OR
            !isset($Data['prf_status'])
        ){

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'Faltan parametros'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;

        }

        // VERIFICAR CODIGO DE REGIMEN

        $sqlSearch = "SELECT * FROM tprf WHERE prf_code =:prf_code";
        $resSearch = $this->pedeo->queryTable($sqlSearch, array(
            ':prf_code' => $Data['prf_code'], 
        ));

        if ( isset($resSearch[0]) ) {
            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'Ya esta en uso el código '.$Data['prf_code']
            );

            $this->response($respuesta);

            return;
        }


        $sqlInsert = "INSERT INTO tprf(prf_code, prf_name, prf_status)
          	          VALUES (:prf_code, :prf_name, :prf_status)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            
            ':prf_code' => $Data['prf_code'], 
            ':prf_name' => $Data['prf_name'], 
            ':prf_status' => $Data['prf_status']
        ));

        if(is_numeric($resInsert) && $resInsert > 0 ){

              $respuesta = array(
              'error' => false,
              'data' => $resInsert,
              'mensaje' =>'Se registro la responsabilidad fiscal con exito'
              );

        }else{

              $respuesta = array(
                'error'   => true,
                'data' => array(),
                'mensaje'	=> 'No se pudo registrar la responsabilidad fiscal'
              );

        }

         $this->response($respuesta);
	}


    // ACTUALIZAR REGIMEN
    public function updateFiscalResponsibility_post(){

        $Data = $this->post();

        
        if(
            !isset($Data['prf_id']) OR
            !isset($Data['prf_code']) OR
            !isset($Data['prf_name']) OR
            !isset($Data['prf_status'])
        ){

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'Faltan parametros'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;

        }

        // VERIFICAR CODIGO DE REGIMEN

        $sqlSearch = "SELECT * FROM tprf WHERE prf_code = :prf_code AND prf_id != :prf_id";
        $resSearch = $this->pedeo->queryTable($sqlSearch, array(

            ':prf_code' => $Data['prf_code'], 
            ':prf_id'   => $Data['prf_id']
        ));

        if ( isset($resSearch[0]) ) {
            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'Ya esta en uso el código '.$Data['prf_code']
            );

            $this->response($respuesta);

            return;
        }



        $sqlUpdate = "UPDATE tprf SET prf_code = :prf_code,
                    prf_name = :prf_name,
                    prf_status = :prf_status
                    WHERE prf_id = :prf_id";

        $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

            ':prf_id'     => $Data['prf_id'], 
            ':prf_code'   => $Data['prf_code'], 
            ':prf_name'   => $Data['prf_name'], 
            ':prf_status' => $Data['prf_status']
        ));


        if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
                'error' => false,
                'data' => $resUpdate,
                'mensaje' =>'Responsabilidad fiscal actualizada con exito'
            );


        }else{

            $respuesta = array(
                'error'   => true,
                'data'    => $resUpdate,
                'mensaje'	=> 'No se pudo actualizar la responsabilidad fiscal'
            );

        }

        $this->response($respuesta);
    }


    // OBTENER REGIMEN FISCAL
    public function getFiscalResponsibility_get() {

        $sqlSelect = " SELECT * FROM tprf";

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



}



