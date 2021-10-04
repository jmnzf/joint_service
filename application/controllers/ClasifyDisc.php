<?php
//CLASIFICACION DE DESCUENTOS PARA SOCIOS DE NEGOCIOS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class ClasifyDisc extends REST_Controller {

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

  //Crear nueva moneda
	public function createClasifyDisc_post(){

      $Data = $this->post();



      $sqlInsert = "INSERT INTO tbdc(bdc_clasify, bdc_concept, bdc_disc1, bdc_disc2)VALUES(:bdc_clasify, :bdc_concept, :bdc_disc1, :bdc_disc2)";




      $resInsert = $this->pedeo->insertRow($sqlInsert, array(

            ':bdc_clasify' => isset($Data['bdc_clasify']) ? $Data['bdc_clasify'] : NULL,
            ':bdc_concept' => isset($Data['bdc_concept']) ? $Data['bdc_concept']: NULL,
            ':bdc_disc1'   => is_numeric($Data['bdc_disc1']) ? $Data['bdc_disc1'] : 0,
            ':bdc_disc2'   => is_numeric($Data['bdc_disc2']) ? $Data['bdc_disc2'] : 0
      ));

      if(is_numeric($resInsert) && $resInsert > 0){

            $respuesta = array(
              'error'		=> false,
              'data' 		=> $resInsert,
              'mensaje' =>'Clasificacion registrada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data' 		=> $resInsert,
              'mensaje'	=> 'No se pudo registrar la clasificacion'
            );

      }

       $this->response($respuesta);
	}

  //Actualizar moneda
  public function updateClasifyDisc_post(){

      $Data = $this->post();

      if(!isset($Data['bdc_id'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'Falta el campo bdc_id para hacer la actualizacion'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }




      $sqlUpdate = "UPDATE tbdc	SET bdc_clasify =:bdc_clasify, bdc_concept =:bdc_concept, bdc_disc1=:bdc_disc1, bdc_disc2=:bdc_disc2
	                  WHERE bdc_id =:bdc_id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

          ':bdc_clasify' => isset($Data['bdc_clasify']) ? $Data['bdc_clasify'] : NULL,
          ':bdc_concept' => isset($Data['bdc_concept']) ? $Data['bdc_concept']: NULL,
          ':bdc_disc1'   => is_numeric($Data['bdc_disc1']) ? $Data['bdc_disc1'] : 0,
          ':bdc_disc2'   => is_numeric($Data['bdc_disc2']) ? $Data['bdc_disc2'] : 0,
          ':bdc_id'      => $Data['bdc_id']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
              'error'   => false,
              'data'    => $resUpdate,
              'mensaje' =>'Clasificacion actualizada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la clasificacion'
            );

      }

       $this->response($respuesta);
  }

  //OBTENER CLACIFICACION DE DESCUENTOS
  public function getClasifyDisc_get(){

        $sqlSelect = " SELECT * FROM tbdc";

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
