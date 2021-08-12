<?php
// DATOS MAESTROS CENTROS DE COSTOS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class CostCenter extends REST_Controller {

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

  //CREAR NUEVO CENTRO DE COSTO
	public function createCostCenter_post(){
      $Data = $this->post();

      if(!isset($Data['dcc_prc_code']) OR
         !isset($Data['dcc_prc_name']) OR
         !isset($Data['dcc_prc_date_ini']) OR
         !isset($Data['dcc_prc_end_date'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

        $sqlInsert = "INSERT INTO dmcc(dcc_prc_code, dcc_prc_name, dcc_prc_date_ini, dcc_prc_end_date)
                      VALUES (:dcc_prc_code, :dcc_prc_name, :dcc_prc_date_ini, :dcc_prc_end_date)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(

              ':dcc_prc_code' => $Data['dcc_prc_code'],
              ':dcc_prc_name' => $Data['dcc_prc_name'],
              ':dcc_prc_date_ini' => $Data['dcc_prc_date_ini'],
              ':dcc_prc_end_date' => $Data['dcc_prc_end_date']
        ));

        if($resInsert > 0 ){

              $respuesta = array(
                'error' => false,
                'data' => $resInsert,
                'mensaje' =>'Centro de costo registrado con exito'
              );


        }else{

              $respuesta = array(
                'error'   => true,
                'data' => array(),
                'mensaje'	=> 'No se pudo registrar el centro de costo'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR CENTRO DE COSTO
  public function updateCostCenter_post(){

      $Data = $this->post();

      if(!isset($Data['dcc_prc_code']) OR
         !isset($Data['dcc_prc_name']) OR
         !isset($Data['dcc_prc_date_ini']) OR
         !isset($Data['dcc_prc_end_date']) OR
         !isset($Data['dcc_id'])){


        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlUpdate = "UPDATE dmcc SET dcc_prc_code = :dcc_prc_code, dcc_prc_name = :dcc_prc_name, dcc_prc_date_ini = :dcc_prc_date_ini,
                    dcc_prc_end_date = :dcc_prc_end_date WHERE dcc_id = :dcc_id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
            ':dcc_prc_code' => $Data['dcc_prc_code'],
            ':dcc_prc_name' => $Data['dcc_prc_name'],
            ':dcc_prc_date_ini' => $Data['dcc_prc_date_ini'],
            ':dcc_prc_end_date' => $Data['dcc_prc_end_date'],
            ':dcc_id' => $Data['dcc_id']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Centro de costo actualizado con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar el centro de costo'
            );

      }

       $this->response($respuesta);
  }


  // OBTENER CENTRO DE COSTO
  public function getCostCenter_get(){

        $sqlSelect = " SELECT * FROM dmcc";

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

  // OBTENER CENTRO DE COSTO
  public function getCostCenterById_get(){

        $Data = $this->get();

        if(!isset($Data['dcc_id'])){


          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = " SELECT * FROM dmcc WHERE dcc_id = :dcc_id ";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':dcc_id' => $Data['dcc_id']));

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
