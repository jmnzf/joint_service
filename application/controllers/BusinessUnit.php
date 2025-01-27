<?php
// DATOS MAESTROS DE UNIDAD DE NEGOCIOS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class BusinessUnit extends REST_Controller {

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

  //CREAR NUEVA UNIDAD DE NEGOCIO
	public function createBusinessUnit_post(){
      $Data = $this->post();

      if(!isset($Data['dun_un_code']) OR
         !isset($Data['dun_un_name']) OR
         !isset($Data['dun_date_ini']) OR
         !isset($Data['dun_un_end_date']) or
         !isset($Data['business'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }


      $sqlVerify = "SELECT * FROM dmun WHERE UPPER(dun_un_code) = UPPER(:dun_un_code) AND business = :business";

      $resVerify = $this->pedeo->queryTable($sqlVerify, array(
          ':dun_un_code' => $Data['dun_un_code'],
					':business' => $Data['business']
      ));

      if ( isset($resVerify[0]) ){

        $respuesta = array(
          'error'   => true,
          'data' 		=> $resVerify,
          'mensaje'	=> 'Ya existe una unidad de negocio con codigo '.$Data['dun_un_code']
        );

        return $this->response($respuesta);
      }

        $sqlInsert = "INSERT INTO dmun(dun_un_code, dun_un_name, dun_date_ini, dun_un_end_date,business, dun_un_enabled)
                      VALUES (:dun_un_code, :dun_un_name, :dun_date_ini, :dun_un_end_date,:business, :dun_un_enabled)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(

              ':dun_un_code' => $Data['dun_un_code'],
              ':dun_un_name' => $Data['dun_un_name'],
              ':dun_date_ini' => $Data['dun_date_ini'],
              ':dun_un_end_date' => $Data['dun_un_end_date'],
              ':dun_un_enabled' => $Data['dun_un_enabled'],
              ':business' => $Data['business']
        ));

        if(is_numeric($resInsert) && $resInsert > 0){

              $respuesta = array(
                'error' => false,
                'data' => $resInsert,
                'mensaje' =>'Unidad de negocio registrada con exito'
              );


        }else{

              $respuesta = array(
                'error'   => true,
                'data' 		=> $resInsert,
                'mensaje'	=> 'No se pudo registrar el unidad de negocio'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR UNIDAD DE NEGOCIO
  public function updateBusinessUnit_post(){

      $Data = $this->post();

      if(!isset($Data['dun_un_name']) OR
         !isset($Data['dun_date_ini']) OR
         !isset($Data['dun_un_end_date']) OR
         !isset($Data['dun_id']) or
         !isset($Data['business'])){


        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlUpdate = "UPDATE dmun SET dun_un_name = :dun_un_name, dun_date_ini = :dun_date_ini,
                    dun_un_end_date = :dun_un_end_date,business = :business , dun_un_enabled = :dun_un_enabled WHERE dun_id = :dun_id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
          ':dun_un_name' => $Data['dun_un_name'],
          ':dun_date_ini' => $Data['dun_date_ini'],
          ':dun_un_end_date' => $Data['dun_un_end_date'],
          ':dun_un_enabled' => $Data['dun_un_enabled'],
          ':dun_id' => $Data['dun_id'],
          ':business' => $Data['business']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Unidad de negocio actualizada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la unidad de negocio'
            );

      }

       $this->response($respuesta);
  }


  // OBTENER UNIDAD DE NEGOCIO
  public function getBusinessUnit_get(){

      $Data = $this->get();

      if(!isset($Data['business'])){
        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

        $sqlSelect = " SELECT * FROM dmun where business = :business";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':business' => $Data['business']));

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

  // OBTENER UNIDAD DE NEGOCIO POR ID
  public function getBusinessUnitById_get(){

        $Data = $this->get();

        if(!isset($Data['dun_id']) or
          !isset($Data['business'])){
          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = " SELECT * FROM dmun WHERE dun_id = :dun_id ";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':dun_id' => $Data['dun_id'], ':business' => $Data['business']));

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
