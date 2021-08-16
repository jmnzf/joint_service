<?php
// DATOS GURPOS DE SOCIOS DE NEGOCIO
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PartGroup extends REST_Controller {

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

  //CREAR NUEVA LISTA DE PRECIO
	public function createPartGroup_post(){

      $Data = $this->post();

      if(!isset($Data['mgs_type']) OR
         !isset($Data['mgs_code']) OR
         !isset($Data['mgs_name']) OR
         !isset($Data['mgs_acct']) OR
         !isset($Data['mgs_enabled'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }


      $sqlSelect = "SELECT mgs_code FROM dmgs WHERE mgs_code = :mgs_code";

      $resSelect = $this->pedeo->queryTable($sqlSelect, array(

          ':mgs_code' => $Data['mgs_code']

      ));


      if(isset($resSelect[0])){

        $respuesta = array(
          'error' => true,
          'data'  => array($Data['mgs_code'], $Data['mgs_code']),
          'mensaje' => 'ya existe un grupo con ese código');

        $this->response($respuesta);

        return;

    }


        $sqlInsert = "INSERT INTO dmgs (mgs_type, mgs_code, mgs_name, mgs_acct, mgs_enabled)
                      VALUES(:mgs_type, :mgs_code, :mgs_name, :mgs_acct, :mgs_enabled)";

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(

              ':mgs_type'    => $Data['mgs_type'],
              ':mgs_code'    => $Data['mgs_code'],
              ':mgs_name'    => $Data['mgs_name'],
              ':mgs_acct'    => $Data['mgs_acct'],
              ':mgs_enabled'    => $Data['mgs_enabled']
        ));

        if(is_numeric($resInsert) && $resInsert > 0){
            $respuesta = array(
              'error'		=> false,
              'data' 		=> $resInsert,
              'mensaje' =>'Grupo de SN registrado con exito'
            );
         }else{

           $respuesta = array(
             'error'   => true,
             'data' 	 => $resInsert,
             'mensaje' => 'No se pudo registrar el grupo de SN'
           );

         }
         $this->response($respuesta);
        }




  //ACTUALIZAR LISTA DE PRECIOS
  public function updatePartGroup_post(){

      $Data = $this->post();

      if(!isset($Data['mgs_type']) OR
         !isset($Data['mgs_code']) OR
         !isset($Data['mgs_name']) OR
         !isset($Data['mgs_acct']) OR
         !isset($Data['mgs_enabled'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }


      $sqlUpdate = "UPDATE dmgs SET mgs_type = :mgs_type,
                                    mgs_code = :mgs_code,
                                    mgs_name = :mgs_name,
                                    mgs_acct = :mgs_acct,
                                    mgs_enabled = :mgs_enabled
                                    WHERE mgs_id = :mgs_id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(


        ':mgs_id'    => $Data['mgs_id'],
        ':mgs_type'    => $Data['mgs_type'],
        ':mgs_code'    => $Data['mgs_code'],
        ':mgs_name'    => $Data['mgs_name'],
        ':mgs_acct'    => $Data['mgs_acct'],
        ':mgs_enabled'    => $Data['mgs_enabled']
      ));


      if(is_numeric($resUpdate) && $resUpdate == 1){

        $respuesta = array(
          'error' => false,
          'data' => $resUpdate,
          'mensaje' =>'Grupo de SN actualizado con exito'
        );


  }else{

        $respuesta = array(
          'error'   => true,
          'data' => $resUpdate,
          'mensaje'	=> 'No se pudo actualizar el Grupo de SN'
        );

  }

   $this->response($respuesta);

  }


  // OBTENER LISTA DE PRECIOS
  public function getPartGroup_get(){

        $sqlSelect = " SELECT * FROM dmgs";

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

  // OBTENER LISTA DE PRECIOS POR ID
  public function getPartGroupById_get(){

        $Data = $this->get();

        if(!isset($Data['mgs_id'])){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = " SELECT * FROM dmgs WHERE mgs_id = :mgs_id";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':mgs_id' => $Data['mgs_id']));

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
