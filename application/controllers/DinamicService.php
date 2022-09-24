<?php

//CREACION DE CRUD DINAMICOS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class DinamicService extends REST_Controller {

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

  //CREAR NUEVO CRUD
	public function createDinamicService_post(){

      $Data = $this->post();


      $respuesta = array(
        'error'   => true,
        'data' 		=> [],
        'mensaje'	=> 'No se pudo registrar la sucursal'
      );


      print_r($this->pedeo->queryTable("select currval('1bep1_ep1_id_seq')"));


exit;
       // $this->response($respuesta);
	}


}
