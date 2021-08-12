<?php
// DATOS MAESTROS CONFIGURACION DE DOCUMENTOS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class DocumentSettings extends REST_Controller {

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

  //CREAR NUEVA CONFIGURACION DE DOCUMENTO
	public function createDocumentSettings_post(){
      $Data = $this->post();

      if(!isset($Data['cdm_type']) OR
         !isset($Data['cdm_doc_num']) OR
         !isset($Data['cdm_comments']) OR
         !isset($Data['cdm_legal_num'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

        $sqlInsert = "INSERT INTO cfdm(cdm_type, cdm_doc_num, cdm_comments, cdm_legal_num)
                      VALUES (:cdm_type, :cdm_doc_num, :cdm_comments, :cdm_legal_num)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(

              ':cdm_type' => $Data['cdm_type'],
              ':cdm_doc_num' => $Data['cdm_doc_num'],
              ':cdm_comments' => $Data['cdm_comments'],
              ':cdm_legal_num' => $Data['cdm_legal_num']
        ));

        if($resInsert > 0 ){

              $respuesta = array(
                'error' => false,
                'data' => $resInsert,
                'mensaje' =>'Configuración de documento registrada con exito'
              );


        }else{

              $respuesta = array(
                'error'   => true,
                'data' => array(),
                'mensaje'	=> 'No se pudo registrar el unidad de negocio'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR CONFIGURACION DE DOCUMENTO
  public function updateDocumentSettings_post(){

      $Data = $this->post();

      if(!isset($Data['cdm_type']) OR
         !isset($Data['cdm_doc_num']) OR
         !isset($Data['cdm_comments']) OR
         !isset($Data['cdm_legal_num']) OR
         !isset($Data['cdm_id'])){


        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlUpdate = "UPDATE cfdm SET cdm_type = :cdm_type, cdm_doc_num = :cdm_doc_num, cdm_comments = :cdm_comments,
                    cdm_legal_num = :cdm_legal_num WHERE cdm_id = :cdm_id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
            ':cdm_type' => $Data['cdm_type'],
            ':cdm_doc_num' => $Data['cdm_doc_num'],
            ':cdm_comments' => $Data['cdm_comments'],
            ':cdm_legal_num' => $Data['cdm_legal_num'],
            ':cdm_id' => $Data['cdm_id']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Configuración de documento actualizada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la configuración del documento'
            );

      }

       $this->response($respuesta);
  }


  // OBTENER CONFIGURACION DE DOCUMENTO
  public function getDocumentSettings_get(){

        $sqlSelect = " SELECT * FROM cfdm";

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

  // OBTENER UNIDAD DE NEGOCIO POR ID
  public function getDocumentSettingsById_get(){

        $Data = $this->get();

        if(!isset($Data['cdm_id'])){


          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = " SELECT * FROM cfdm WHERE cdm_id = :cdm_id ";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':cdm_id' => $Data['cdm_id']));

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
