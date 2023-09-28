<?php

//CREACION DE CRUD DINAMICOS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class DinamicCrud extends REST_Controller {

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
      $Data = $this->get();

      if (!isset($Data['table'])){

          $respuesta = array(
              'error' => true,
              'data'  => array(),
              'mensaje' =>'No se encontro la tabla'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
      }

      $sqlSelect = "SELECT * FROM ".$Data['table'];

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

  public function create_post(){
    $Data = $this->post();

    if (!isset($Data['table'])){

        $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'No se encontro la tabla'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
    }

    $res = $this->getInsert($Data);

    if( isset($res[1]) ){

      $response = $this->pedeo->insertRow($res[0],$res[1]);

      if (is_numeric($response) && $response > 0 ){

        $respuesta = array(
          'error' => false,
          'data'  => $response,
          'mensaje' =>'Datos procesados con exito'
        );

      }else{

        $respuesta = array(
          'error' => true,
          'data'  => $response,
          'mensaje' =>'No se pudo procesar la información'
        );

      }

    }else{

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' =>'No hay datos para insertar'
      );

    }

    $this->response($respuesta);
  }

  public function update_post(){
    $Data = $this->post();

    if (!isset($Data['table']) OR !isset($Data['pkey'])){

        $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'Faltan parametros'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
    }

    $res = $this->getUpdate($Data);

    if( isset($res[1]) ){

      $response = $this->pedeo->updateRow($res[0],$res[1]);

      if (is_numeric($response) && $response == 1 ){

        $respuesta = array(
          'error' => false,
          'data'  => $response,
          'mensaje' =>'Datos actualizados con exito'
        );

      }else{

        $respuesta = array(
          'error' => true,
          'data'  => $response,
          'mensaje' =>'No se pudo procesar la información'
        );

      }

    }else{

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' =>'No hay datos para insertar'
      );

    }

    $this->response($respuesta);

  }

  private function getInsert($Data){

    $insert = "INSERT INTO ".$Data['table']."( ";
		$campos = "";
		$values = "";
		$objInsert = new \stdClass;
		$arrayValidateInsert = [];
    $sql = "";


    try {
      foreach ( $Data as $key => $element ) {

        if ($key != "table" && $key != "pkey" && $key != $Data['pkey'] ){

          $campos.= trim($key).', ';
          $values.= trim(':'.$key).', ';

          $val = ':'.$key;

          $objInsert->$val = trim($element);
        }

      }

      $campos = substr(trim($campos), 0, -1);
      $values = substr(trim($values), 0, -1);

      $sql.= $insert.$campos." ) VALUES ( ".$values." )";
      $array = json_decode(json_encode($objInsert), true);



      array_push($arrayValidateInsert, $sql);
      array_push($arrayValidateInsert, $array);

    } catch (\Throwable $th) {
      $arrayValidateInsert = [];
    }



    return $arrayValidateInsert;

  }

  public function getUpdate($Data){
    $update = "UPDATE ".$Data['table']." SET ";
		$campos = "";
		$objUpdate = new \stdClass;
		$arrayValidateUpdate = [];
    $sql = "";
    $where = "";

    try {

      foreach ( $Data as $key => $element ) {

        if ($key != "table" && $key != "pkey" && $key != $Data['pkey'] ){

          $campos.= trim($key).' = '.trim(':'.$key).', ';
          $val = ':'.$key;
          $objUpdate->$val = trim($element);

        }

        if ( $key == $Data['pkey'] ){
          $val = ':'.$key;
          $objUpdate->$val = trim($element);
          $where = " WHERE ".$key." = :".$Data['pkey'];
        }
      }


      $campos = substr(trim($campos), 0, -1);

      $sql.= $update.$campos.$where;
      $array = json_decode(json_encode($objUpdate), true);


      array_push($arrayValidateUpdate, $sql);
      array_push($arrayValidateUpdate, $array);


    } catch (\Throwable $th) {
      $arrayValidateUpdate = [];
    }


    return $arrayValidateUpdate;

  }

  public function select_get(){

    $Data = $this->get();

    if (!isset($Data['table']) OR !isset($Data['id']) OR !isset($Data['text']) ){

        $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'Faltan parametros'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
    }


    $nvlCuenta = "SELECT acc_level FROM params";
    $resNvlCuenta = $this->pedeo->queryTable($nvlCuenta, array());

    $nvl = 0;

    if (isset($resNvlCuenta[0])){
      $nvl = $resNvlCuenta[0]['acc_level'];
    }


    $datawhere = '';
    if (isset($Data['where'])) {
      $datawhere = str_replace('|', ' ', $Data['where']);
      // print_r();exit;
    }

    if ($Data['table'] == 'dacc'){
      $datawhere = " where acc_level = ".$nvl;
    }
    
    $sqlSelect = "SELECT ".$Data['id']." AS id, ".$Data['text']." AS text FROM ".$Data['table']. " ".$datawhere;

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

  public function selectById_get(){
    $Data = $this->get();

    if (!isset($Data['table']) OR !isset($Data['id']) OR !isset($Data['pkey']) ){

        $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'Faltan parametros'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
    }

    $sqlSelect = "SELECT * FROM ".$Data['table']." WHERE ".$Data['pkey']." = :value";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array(':value' => $Data['id']));

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