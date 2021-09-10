<?php
// DATOS MAESTROS PROYECTO
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Project extends REST_Controller {

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

  //CREAR NUEVO PROYECTO
	public function createProject_post(){
      $Data = $this->post();

      if(!isset($Data['dpj_pj_code']) OR
         !isset($Data['dpj_pj_name']) OR
         !isset($Data['dpj_pj_date_ini']) OR
         !isset($Data['dpj_pj_end_date'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

        $sqlInsert = "INSERT INTO dmpj(dpj_pj_code, dpj_pj_name, dpj_pj_date_ini, dpj_pj_end_date)
                      VALUES (:dpj_pj_code, :dpj_pj_name, :dpj_pj_date_ini, :dpj_pj_end_date)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(

              ':dpj_pj_code' => $Data['dpj_pj_code'],
              ':dpj_pj_name' => $Data['dpj_pj_name'],
              ':dpj_pj_date_ini' => $Data['dpj_pj_date_ini'],
              ':dpj_pj_end_date' => $Data['dpj_pj_end_date']
        ));

        if(is_numeric($resInsert) && $resInsert > 0){

              $respuesta = array(
	              'error'	  => false,
                'data' 		=> $resInsert,
                'mensaje' =>'Proyecto registrado con exito'
              );


        }else{

              $respuesta = array(
                'error'   => true,
                'data' 		=> $resInsert,
                'mensaje'	=> 'No se pudo registrar el proyecto'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR PROYECTO
  public function updateProject_post(){

      $Data = $this->post();


      if(!isset($Data['dpj_pj_code']) OR
         !isset($Data['dpj_pj_name']) OR
         !isset($Data['dpj_pj_date_ini']) OR
         !isset($Data['dpj_pj_end_date']) OR
         !isset($Data['dpj_id'])){


        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlUpdate = "UPDATE dmpj SET dpj_pj_code = :dpj_pj_code, dpj_pj_name = :dpj_pj_name, dpj_pj_date_ini = :dpj_pj_date_ini,
                    dpj_pj_end_date = :dpj_pj_end_date WHERE dpj_id = :dpj_id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
          ':dpj_pj_code' => $Data['dpj_pj_code'],
          ':dpj_pj_name' => $Data['dpj_pj_name'],
          ':dpj_pj_date_ini' => $Data['dpj_pj_date_ini'],
          ':dpj_pj_end_date' => $Data['dpj_pj_end_date'],
          ':dpj_id' => $Data['dpj_id']

      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Proyecto actualizado con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar el proyecto'
            );

      }

       $this->response($respuesta);
  }


  // OBTENER PROYECTOS
  public function getProject_get(){

        $sqlSelect = " SELECT * FROM dmpj";

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

  // OBTENER PROYECTO  POR ID
  public function getProjectById_get(){

        $Data = $this->get();

        if(!isset($Data['dpj_id'])){


          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = " SELECT * FROM dmpj WHERE dpj_id = :dpj_id ";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':dpj_id' => $Data['dpj_id']));

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
