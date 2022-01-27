<?php
// CONTROLADOR PARA CERRAR DOCUMENTOS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class CloseDoc extends REST_Controller {

      public function __construct(){

        header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
        header("Access-Control-Allow-Origin: *");

        parent::__construct();
        $this->load->database();
        $this->pdo = $this->load->database('pdo', true)->conn_id;
        $this->load->library('pedeo', [$this->pdo]);

      }



    public function setCloseDoc_post(){

          $Data = $this->post();

          if(!isset($Data['doctype']) OR !isset($Data['docentry']) OR !isset($Data['createby'])){

                $respuesta = array(
                  'error' => true,
                  'data'  => array(),
                  'mensaje' =>'La informacion enviada no es valida'
                );

                $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

                return;

          }

          //SE INSERTA EL ESTADO DEL DOCUMENTO

          $sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
                              VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

          $resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


                    ':bed_docentry' => $Data['docentry'],
                    ':bed_doctype' => $Data['doctype'],
                    ':bed_status' => 3, //ESTADO CERRADO
                    ':bed_createby' => $Data['createby'],
                    ':bed_date' => date('Y-m-d'),
                    ':bed_baseentry' => NULL,
                    ':bed_basetype' =>NULL
          ));


          if(is_numeric($resInsertEstado) && $resInsertEstado > 0){


                $respuesta = array(
                  'error'   => false,
                  'data' => $resInsertEstado,
                  'mensaje'	=> 'Operación exitosa'
                );

          }else{

                $respuesta = array(
                  'error'   => true,
                  'data' => $resInsertEstado,
                  'mensaje'	=> 'No se pudo actualizar el documento'
                );
          }

          //FIN PROCESO ESTADO DEL DOCUMENTO

          $this->response($respuesta);

    }

    public function setCancelDoc_post(){

      $Data = $this->post();
      $respuesta = array();

      if(!isset($Data['vov_basetype']) OR !isset($Data['vov_baseentry']) OR !isset($Data['vov_createby'])){

            $respuesta = array(
              'error' => true,
              'data'  => array(),
              'mensaje' =>'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;

      }
      // print_r($Data);
      // exit;

      //SE INSERTA EL ESTADO DEL DOCUMENTO

      $sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
                          VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";
      $this->pedeo->trans_begin();
      $resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


                ':bed_docentry' => $Data['vov_docentry'],
                ':bed_doctype' => $Data['vov_doctype'],
                ':bed_status' => 2, //ESTADO CANCELADO
                ':bed_createby' => $Data['createby'],
                ':bed_date' => date('Y-m-d'),
                ':bed_baseentry' => NULL,
                ':bed_basetype' =>NULL
      ));


      if(is_numeric($resInsertEstado) && $resInsertEstado > 0){
        $sqlInsertEstado2 = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
                          VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

      $resInsertEstado2 = $this->pedeo->insertRow($sqlInsertEstado2, array(


                ':bed_docentry' => $Data['vov_baseentry'],
                ':bed_doctype' => $Data['vov_basetype'],
                ':bed_status' => 1, //ESTADO ABIERTO 
                ':bed_createby' => $Data['createby'],
                ':bed_date' => date('Y-m-d'),
                ':bed_baseentry' => NULL,
                ':bed_basetype' =>NULL
      ));

      if(is_numeric($resInsertEstado2) && $resInsertEstado2 > 0){
        $respuesta = array(
          'error'   => true,
          'data' => $resInsertEstado2,
          'mensaje'	=> 'Documento Cancelado'
        );
        }else{
          $respuesta = array(
            'error'   => true,
            'data' => $resInsertEstado2,
            'mensaje'	=> 'No se pudo cancelar el documento'
          );
          $this->pedeo->trans_rollback();
        }
      }else{

            $respuesta = array(
              'error'   => true,
              'data' => $resInsertEstado,
              'mensaje'	=> 'No se pudo cancelar el documento'
            );

            $this->pedeo->trans_rollback();
      }
      $this->pedeo->trans_commit();
      //FIN PROCESO ESTADO DEL DOCUMENTO

      $this->response($respuesta);

}


}

?>
