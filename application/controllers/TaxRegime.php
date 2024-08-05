<?php
// DATOS MAESTROS  REGIMEN FISCAL
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class TaxRegime extends REST_Controller {

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
	public function createTaxRegime_post(){

        $Data = $this->post();
    
        if(
            !isset($Data['tre_name']) OR
            !isset($Data['tre_code']) OR
            !isset($Data['tre_dateini']) OR
            !isset($Data['tre_dateend']) OR
            !isset($Data['tre_status'])
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

        $sqlSearch = "SELECT * FROM ttre WHERE tre_code =:tre_code";
        $resSearch = $this->pedeo->queryTable($sqlSearch, array(
            ':tre_code' => $Data['tre_code'], 
        ));

        if ( isset($resSearch[0]) ) {
            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'Ya esta en uso el código '.$Data['tre_code']
            );

            $this->response($respuesta);

            return;
        }

        $sqlInsert = "INSERT INTO ttre(tre_code, tre_name, tre_dateini, tre_dateend, tre_status)
          	          VALUES (:tre_code, :tre_name, :tre_dateini, :tre_dateend, :tre_status)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            
            ':tre_code' => $Data['tre_code'], 
            ':tre_name' => $Data['tre_name'], 
            ':tre_dateini' => $Data['tre_dateini'], 
            ':tre_dateend' => $Data['tre_dateend'], 
            ':tre_status' => $Data['tre_status']
        ));

        if(is_numeric($resInsert) && $resInsert > 0 ){

              $respuesta = array(
              'error' => false,
              'data' => $resInsert,
              'mensaje' =>'Se registro el regimen con exito'
              );

        }else{

              $respuesta = array(
                'error'   => true,
                'data' => array(),
                'mensaje'	=> 'No se pudo registrar la tasa'
              );

        }

         $this->response($respuesta);
	}


    // ACTUALIZAR REGIMEN
    public function updateTaxRegime_post(){

        $Data = $this->post();

        
        if(
            !isset($Data['tre_id']) OR
            !isset($Data['tre_name']) OR
            !isset($Data['tre_code']) OR
            !isset($Data['tre_dateini']) OR
            !isset($Data['tre_dateend']) OR
            !isset($Data['tre_status'])
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

        $sqlSearch = "SELECT * FROM ttre WHERE tre_code = :tre_code AND tre_id != :tre_id";
        $resSearch = $this->pedeo->queryTable($sqlSearch, array(

            ':tre_code' => $Data['tre_code'], 
            ':tre_id'   => $Data['tre_id']
        ));

        if ( isset($resSearch[0]) ) {
            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'Ya esta en uso el código '.$Data['tre_code']
            );

            $this->response($respuesta);

            return;
        }

        $sqlUpdate = "UPDATE ttre SET tre_name = :tre_name,
                    tre_code = :tre_code,
                    tre_dateini = :tre_dateini,
                    tre_dateend = :tre_dateend,
                    tre_status = :tre_status
                    WHERE tre_id = :tre_id";

        $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

            ':tre_id'       => $Data['tre_id'], 
            ':tre_code'     => $Data['tre_code'], 
            ':tre_name'     => $Data['tre_name'], 
            ':tre_dateini'  => $Data['tre_dateini'], 
            ':tre_dateend'  => $Data['tre_dateend'], 
            ':tre_status'   => $Data['tre_status']
        ));


        if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
                'error' => false,
                'data' => $resUpdate,
                'mensaje' =>'Regimen actualizado con exito'
            );


        }else{

            $respuesta = array(
                'error'   => true,
                'data'    => $resUpdate,
                'mensaje'	=> 'No se pudo actualizar el regimen'
            );

        }

        $this->response($respuesta);
    }


    // OBTENER REGIMEN FISCAL
    public function getTaxRegime_get(){

            $sqlSelect = " SELECT * FROM ttre";

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
