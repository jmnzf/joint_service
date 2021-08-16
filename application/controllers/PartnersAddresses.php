<?php
//  Socio de Direcciones
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PartnersAddresses extends REST_Controller {

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

  //Crear nueva direccion de socio
	public function createPartnersAddresses_post(){

      $Data = $this->post();

      if(!isset($Data['dmd_state_mm']) OR
         !isset($Data['dmd_state']) OR
         !isset($Data['dmd_id_add']) OR
         !isset($Data['dmd_delivery_add']) OR
         !isset($Data['dmd_country']) OR
         !isset($Data['dmd_city']) OR
         !isset($Data['dmd_card_code']) OR
         !isset($Data['dmd_adress']) OR
				 !isset($Data['dmd_tonw'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }


      $sqlInsert = "INSERT INTO dmsd(dmd_state_mm, dmd_state, dmd_id_add, dmd_delivery_add, dmd_country, dmd_city,
                    dmd_card_code, dmd_adress, dmd_tonw)VALUES(:dmd_state_mm, :dmd_state, :dmd_id_add, :dmd_delivery_add,
                    :dmd_country, :dmd_city, :dmd_card_code, :dmd_adress, :dmd_tonw)";

      $resInsert = $this->pedeo->insertRow($sqlInsert, array(

             ':dmd_state_mm' => $Data['dmd_state_mm'],
             ':dmd_state' => $Data['dmd_state'],
             ':dmd_id_add' => $Data['dmd_id_add'],
             ':dmd_delivery_add' => $Data['dmd_delivery_add'],
             ':dmd_country' => $Data['dmd_country'],
             ':dmd_city' => $Data['dmd_city'],
             ':dmd_card_code' => $Data['dmd_card_code'],
             ':dmd_adress' => $Data['dmd_adress'],
             ':dmd_tonw' => $Data['dmd_tonw']

      ));

      if(is_numeric($resInsert) && $resInsert > 0){


            $respuesta = array(
              'error' 	=> false,
              'data' 		=> $resInsert,
              'mensaje' =>'Dirección registrada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data' 		=> $resInsert,
              'mensaje'	=> 'No se pudo registrar la dirección'
            );

      }

       $this->response($respuesta);
	}

  //Actualizar Direcion de Socio negocio
  public function updatePartnersAddresses_post(){

      $Data = $this->post();

      if(!isset($Data['dmd_state_mm']) OR
         !isset($Data['dmd_state']) OR
         !isset($Data['dmd_id_add']) OR
         !isset($Data['dmd_delivery_add']) OR
         !isset($Data['dmd_country']) OR
         !isset($Data['dmd_city']) OR
         !isset($Data['dmd_card_code']) OR
         !isset($Data['dmd_adress']) OR
         !isset($Data['dmd_tonw']) OR
         !isset($Data['dmd_id'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }


      $sqlUpdate = "UPDATE dmsd
                  	SET dmd_state_mm = :dmd_state_mm, dmd_state = :dmd_state, dmd_id_add = :dmd_id_add,
                    dmd_delivery_add = :dmd_delivery_add, dmd_country = :dmd_country, dmd_city = :dmd_city,
                    dmd_card_code = :dmd_card_code, dmd_adress = :dmd_adress, dmd_tonw = :dmd_tonw
                  	WHERE dmd_id = :dmd_id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

            ':dmd_state_mm' => $Data['dmd_state_mm'],
            ':dmd_state' => $Data['dmd_state'],
            ':dmd_id_add' => $Data['dmd_id_add'],
            ':dmd_delivery_add' => $Data['dmd_delivery_add'],
            ':dmd_country' => $Data['dmd_country'],
            ':dmd_city' => $Data['dmd_city'],
            ':dmd_card_code' => $Data['dmd_card_code'],
            ':dmd_adress' => $Data['dmd_adress'],
            ':dmd_tonw' => $Data['dmd_tonw'],
            ':dmd_id' => $Data['dmd_id']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Dirección actualizada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la dirección'
            );

      }

       $this->response($respuesta);
  }

  // Obtener direccion por id de socio de negocio
  public function getPartnersAddressesById_get(){

        $Data = $this->get();

        if(!isset($Data['dmd_card_code'])){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = " SELECT * FROM dmsd WHERE dmd_card_code = :dmd_card_code";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':dmd_card_code' => $Data['dmd_card_code']));

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
