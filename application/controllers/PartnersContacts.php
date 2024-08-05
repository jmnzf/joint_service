<?php
//  Socios  Contactos
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PartnersContacts extends REST_Controller {

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

  //Crear nuevo contacto de socio
	public function createPartnersContacts_post(){

      $Data = $this->post();

      if(!isset($Data['dmc_card_code']) OR
         !isset($Data['dmc_contac_id']) OR
         !isset($Data['dmc_name']) OR
         !isset($Data['dmc_last_name']) OR
         !isset($Data['dmc_status']) OR
         !isset($Data['dmc_email']) OR
         !isset($Data['dmc_phone1']) OR
         !isset($Data['dmc_cel'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }


      $sqlInsert = "INSERT INTO dmsc(dmc_card_code, dmc_contac_id, dmc_name, dmc_last_name, dmc_status, dmc_email,
                    dmc_phone1, dmc_cel, dmc_cardtype, dmc_uso)
                    VALUES (:dmc_card_code, :dmc_contac_id, :dmc_name, :dmc_last_name, :dmc_status,
                    :dmc_email, :dmc_phone1, :dmc_cel, :dmc_cardtype, :dmc_uso)";

      $resInsert = $this->pedeo->insertRow($sqlInsert, array(

             ':dmc_card_code' => $Data['dmc_card_code'],
             ':dmc_contac_id' => $Data['dmc_contac_id'],
             ':dmc_name' => $Data['dmc_name'],
             ':dmc_last_name' => $Data['dmc_last_name'],
             ':dmc_status' => $Data['dmc_status'],
             ':dmc_email' => $Data['dmc_email'],
             ':dmc_phone1' => $Data['dmc_phone1'],
             ':dmc_cel' => $Data['dmc_cel'],
             ':dmc_cardtype' => isset($Data['dmc_cardtype']) ? $Data['dmc_cardtype'] : null,
             ':dmc_uso' => isset($Data['dmc_uso']) ? $Data['dmc_uso'] : null

      ));

      if(is_numeric($resInsert) && $resInsert > 0){


            $respuesta = array(
              'error' 	=> false,
              'data' 		=> $resInsert,
              'mensaje' =>'Contacto registrado con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data' 		=> $resInsert,
              'mensaje'	=> 'No se pudo registrar el contacto'
            );

      }

       $this->response($respuesta);
	}

  //Actualizar Contacto de Socio negocio
  public function updatePartnersContacts_post(){

      $Data = $this->post();

      if(!isset($Data['dmc_card_code']) OR
         !isset($Data['dmc_contac_id']) OR
         !isset($Data['dmc_name']) OR
         !isset($Data['dmc_last_name']) OR
         !isset($Data['dmc_status']) OR
         !isset($Data['dmc_email']) OR
         !isset($Data['dmc_phone1']) OR
         !isset($Data['dmc_cel']) OR
         !isset($Data['dmc_id'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }


      $sqlUpdate = "UPDATE dmsc
                  	SET dmc_card_code = :dmc_card_code, dmc_contac_id = :dmc_contac_id, dmc_name = :dmc_name,
                    dmc_last_name = :dmc_last_name, dmc_status = :dmc_status, dmc_email = :dmc_email,
                    dmc_phone1 = :dmc_phone1, dmc_cel = :dmc_cel, dmc_cardtype = :dmc_cardtype, dmc_uso = :dmc_uso 
                    WHERE dmc_id = :dmc_id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

            ':dmc_card_code' => $Data['dmc_card_code'],
            ':dmc_contac_id' => $Data['dmc_contac_id'],
            ':dmc_name' => $Data['dmc_name'],
            ':dmc_last_name' => $Data['dmc_last_name'],
            ':dmc_status' => $Data['dmc_status'],
            ':dmc_email' => $Data['dmc_email'],
            ':dmc_phone1' => $Data['dmc_phone1'],
            ':dmc_cel' => $Data['dmc_cel'],
            ':dmc_id' => $Data['dmc_id'],
            ':dmc_cardtype' => isset($Data['dmc_cardtype']) ? $Data['dmc_cardtype'] : null,
            ':dmc_uso' => isset($Data['dmc_uso']) ? $Data['dmc_uso'] : null
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Contacto actualizado con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar dirección'
            );

      }

       $this->response($respuesta);
  }
  
  // Obtener contacto por id de socio de negocio
  public function getPartnersContactsById_get(){

        $Data = $this->get();

        if(!isset($Data['dmc_card_code'])){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = "SELECT * FROM dmsc WHERE dmc_card_code = :dmc_card_code";
        
        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':dmc_card_code' => $Data['dmc_card_code']));

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

  // Obtener contactos por id de socio de negocio
  public function getPartnersContacts_get(){

        $Data = $this->get();

        if(!isset($Data['dmc_card_code'])){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = " SELECT * FROM dmsc WHERE dmc_card_code = :dmc_card_code";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':dmc_card_code' => $Data['dmc_card_code']));

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

  //ACTUALIZAR DIRECCION DE SOCIO DE NEGOCIO
  //COMO LA PRINCIPAL
  public function updateContactByPr_post()
  {
    $Data = $this->post();

    if (!isset($Data['dmc_card_code']) or !isset($Data['dmc_id'])) {

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'La informacion enviada no es valida'
      );

      $this->response($respuesta,  REST_Controller::HTTP_BAD_REQUEST);
      return;
    }

    $resUpdate = NULL;

    $update = $this->pedeo->updateRow('UPDATE dmsc SET dmc_ppal = 0 WHERE dmc_card_code = :dmc_card_code', [':dmc_card_code' => $Data['dmc_card_code']]);

    if (is_numeric($update) && $update > 0) {
      //
      $resUpdate = $this->pedeo->updateRow(
        "UPDATE dmsc SET dmc_ppal = :dmc_ppal WHERE dmc_id = :dmc_id",
        array(
          ':dmc_ppal' => 1,
          ':dmc_id' => $Data['dmc_id']
        )
      );
    }

    $respuesta = array(
      'error'   => true,
      'data'    => $update,
      'mensaje'  => 'No se pudo actualizar el contacto del socio de negocio'
    );

    if (is_numeric($resUpdate) && $resUpdate == 1) {
      $respuesta = array(
        'error'   => false,
        'data'    => $resUpdate,
        'mensaje' => 'Se actualizó el contacto del socio de negocio'
      );
    }

    $this->response($respuesta);
  }
}