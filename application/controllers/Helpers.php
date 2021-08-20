<?php

// OPERACIONES DE AYUDA

defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Helpers extends REST_Controller {

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

  //Obtener Campos para Llenar Input Select
	public function getFiels_post(){

        $Data = $this->post();

        if(!isset($Data['table_name']) OR
           !isset($Data['table_camps']) OR
           !isset($Data['camps_order']) OR
           !isset($Data['order'])){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

				$filtro = "";
        $limite = "";
				if(isset($Data['filter'])){$filtro = $Data['filter'];}

        if(isset($Data['limit'])){$limite = $Data['limit'];}

        $sqlSelect = " SELECT ".$Data['table_camps']." FROM ".$Data['table_name']." WHERE 1=1 ".$filtro. " ORDER BY ".$Data['camps_order']." ".$Data['order']." ".$limite." ";

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

  public function get_Query_post(){

    $Data = $this->post();

    if(!isset($Data['tabla']) OR
       !isset($Data['campos']) OR
       !isset($Data['where'])){

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' =>'La informacion enviada no es valida'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }

    $sqlSelect = " SELECT ".$Data['table_camps']." FROM ".$Data['table_name']." WHERE ".$Data['where']." ";

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
  
  // OBTENER DATOS DE EMPLEADO DE DEPARTAMENTO DE VENTA 
  public function getVendorData_post(){

    $Data = $this->post();

    if(!isset($Data['mev_id'])){

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' =>'El empleado del departamento de venta NO EXISTE'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }

    $sqlSelect = "SELECT v.mev_id, v.mev_prc_code, v.mev_dpj_pj_code, v.mev_dun_un_code,  v.mev_whs_code
    FROM pgus u
    inner join dmev v
    on u.pgu_id_vendor = v.mev_id where u.pgu_id_usuario = :iduservendor";
    

    $resSelect = $this->pedeo->queryTable($sqlSelect, array(':iduservendor'=> $Data['mev_id']));

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
