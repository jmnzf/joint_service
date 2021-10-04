<?php
// RETENCIONES
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class RetFisc extends REST_Controller {

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

  //CREAR RETENCION
	public function createRetFisc_post(){
        $Data = $this->post();


        $sqlSelect = " SELECT * FROM dmrt WHERE mrt_code =:mrt_code";
        $resSelect = $this->pedeo->queryTable($sqlSelect, array(

                    ':mrt_code' => $Data['mrt_code']
        ));

        if(isset($resSelect[0])){
            $respuesta = array(
              'error' => true,
              'data'  => array(),
              'mensaje' =>'Ya existe el codigo de la retencion'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $sqlInsert = "INSERT INTO dmrt(mrt_code, mrt_enabled, mrt_name, mrt_type, mrt_action, mrt_dateini, mrt_base, mrt_tipobase, mrt_tasa, mrt_codeof, mrt_acctcode, mrt_mm, mrt_typeret, mrt_acctlost, mrt_minbase, mrt_rmm, mrt_selftret, mrt_pos, mrt_selfttype, mrt_typetax, mrt_sustra)
	                    VALUES (:mrt_code, :mrt_enabled, :mrt_name, :mrt_type, :mrt_action, :mrt_dateini, :mrt_base, :mrt_tipobase, :mrt_tasa, :mrt_codeof, :mrt_acctcode, :mrt_mm, :mrt_typeret, :mrt_acctlost, :mrt_minbase, :mrt_rmm, :mrt_selftret, :mrt_pos, :mrt_selfttype, :mrt_typetax, :mrt_sustra)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':mrt_code' => $Data['mrt_code'],
              ':mrt_enabled' => $Data['mrt_enabled'],
              ':mrt_name' => $Data['mrt_name'],
              ':mrt_type' => $Data['mrt_type'],
              ':mrt_action' => $Data['mrt_action'],
              ':mrt_dateini' => $Data['mrt_dateini'],
              ':mrt_base' => $Data['mrt_base'],
              ':mrt_tipobase' => $Data['mrt_tipobase'],
              ':mrt_tasa' => $Data['mrt_tasa'],
              ':mrt_codeof' => $Data['mrt_codeof'],
              ':mrt_acctcode' => $Data['mrt_acctcode'],
              ':mrt_mm' => $Data['mrt_mm'],
              ':mrt_typeret' => $Data['mrt_typeret'],
              ':mrt_acctlost' => $Data['mrt_acctlost'],
              ':mrt_minbase' => $Data['mrt_minbase'],
              ':mrt_rmm' => $Data['mrt_rmm'],
              ':mrt_selftret' => $Data['mrt_selftret'],
              ':mrt_pos' => $Data['mrt_pos'],
              ':mrt_selfttype' => $Data['mrt_selfttype'],
              ':mrt_typetax' => $Data['mrt_typetax'],
              ':mrt_sustra' => $Data['mrt_sustra']

        ));

        if(is_numeric($resInsert) && $resInsert > 0){

                $respuesta = array(
                  'error' 	=> false,
                  'data' 		=> $resInsert,
                  'mensaje' =>'Retencion registrada con exito'
                );


        }else{

                $respuesta = array(
                  'error'   => true,
                  'data'		=> $resInsert,
                  'mensaje'	=> 'No se pudo registrar la retencion'
                );

        }


      	$this->response($respuesta);
	}


	//OBTENER RETENCIONES
	public function getRetFisc_get(){

    $sqlSelect = " SELECT * FROM dmrt";

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
