<?php
// DIAS FERIADOS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Holidays extends REST_Controller {

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


    public function index_get(){

        $sqlSelect = "SELECT * FROM tbdf";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array());

        if ( isset($resSelect[0]) ){

            $respuesta = array(
                'error'     => false,
                'data'      => $resSelect,
                'mensaje'   => ''
              );

        }else{

            $respuesta = array(
                'error'     => true,
                'data'      => [],
                'mensaje'   => ''
              );
        }


        $this->response($respuesta);

    }

    public function create_post(){

        $Data = $this->post();

    
        if( !isset($Data['bdf_description']) OR
            !isset($Data['bdf_date']) ){


            $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
            );

            return $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
        }

        $sqlInsert = "INSERT INTO tbdf(bdf_description, bdf_date)VALUES(:bdf_description, :bdf_date)";

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            
            ':bdf_description' => $Data['bdf_description'], 
            ':bdf_date' => $Data['bdf_date']
        ));

        if ( is_numeric($resInsert) && $resInsert > 0 ) {

            $respuesta = array(
                'error'     => false,
                'data'      => $resInsert,
                'mensaje'   => ''
              );

        }else{

            $respuesta = array(
                'error'     => true,
                'data'      => [],
                'mensaje'   => 'No se pudo guardar el día feriado'
              );
        }

        $this->response($respuesta);

    }


    public function update_post(){

        $Data = $this->post();

    
        if( !isset($Data['bdf_description']) OR
            !isset($Data['bdf_date']) OR
            !isset($Data['bdf_id'])) {


            $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
            );

            return $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
        }

        $sqlUpdate = "UPDATE tbdf SET bdf_description = :bdf_description, bdf_date = :bdf_date WHERE bdf_id = :bdf_id";

        $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
            
            ':bdf_description' => $Data['bdf_description'], 
            ':bdf_date' => $Data['bdf_date'],
            ':bdf_id' => $Data['bdf_id']
        ));

        if ( is_numeric($resUpdate) && $resUpdate == 1 ) {

            $respuesta = array(
                'error'     => false,
                'data'      => $resUpdate,
                'mensaje'   => ''
              );

        }else{

            $respuesta = array(
                'error'     => true,
                'data'      => [],
                'mensaje'   => 'No se pudo actualizar el día feriado'
              );
        }
        
        $this->response($respuesta);

    }
}
