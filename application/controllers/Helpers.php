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
				if(isset($Data['filter'])){$filtro = $Data['filter'];}

        $sqlSelect = " SELECT ".$Data['table_camps']." FROM ".$Data['table_name']." WHERE 1=1 ".$filtro. " ORDER BY ".$Data['camps_order']." ".$Data['order']."";

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
