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

  public function setCancelDoc_post()
  {

    $Data = $this->post();
    $respuesta = array();
    // print_r($Data);exit;
    if (!isset($Data['basetype']) or !isset($Data['baseentry']) or !isset($Data['createby'])) {

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'La informacion enviada no es valida'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }

    // TABLAS
    $table = array(
      '1' =>  array('table' =>'dvct','prefix'=>'dvc'),
      '2' =>  array('table' =>'dvov','prefix'=>'vov'),
      '10' =>  array('table' =>'dcsc','prefix'=>'csc'),
      '11' =>  array('table' =>'dcoc','prefix'=>'coc'),
      '12' =>  array('table' =>'dcpo','prefix'=>'cpo')
    );
    $type = $Data['doctype'];
    

    
    // print_r($sqlUpdate);exit;

    $sqlSelect = "SELECT distinct tbmd.*, mdt_docname,estado
                        FROM tbmd
                            INNER JOIN dmdt
                            ON tbmd.bmd_doctype = dmdt.mdt_doctype
                            left join responsestatus
                              on id = bmd_docentry and tipo = bmd_doctype
                        WHERE concat(bmd_tdi, bmd_ndi) IN (SELECT concat(tb1.bmd_tdi, tb1.bmd_ndi)
                        FROM tbmd as tb1
                        WHERE tb1.bmd_doctype  = :cpo_doctype
                        AND tb1.bmd_docentry = :cpo_docentry)
                        AND  estado not in ('Anulado')
                        AND bmd_cardtype = (
                            select tb2.bmd_cardtype
                            from tbmd tb2
                            WHERE tb2.bmd_doctype  = :cpo_doctype
                            AND tb2.bmd_docentry = :cpo_docentry)
                        ORDER BY tbmd.bmd_id ASC";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array(":cpo_docentry" => $Data['docentry'], ":cpo_doctype" => $Data['doctype']));
    
    $posteriores = [];
    $anterior = [];
    foreach ($resSelect as $key => $docs) {
      if ($docs['bmd_doctype'] > $Data['doctype']) {
        array_push($posteriores, $docs);
      }

      // SE SACA EL DOCUMENTO ANTERIOR AL QUE SE ESTA CONSULTANDO
      if (
        $docs['bmd_doctype'] == $Data['doctype'] and
        $docs['bmd_docentry'] == $Data['docentry'] and
        $key > 0
      ) {
        array_push($anterior, $resSelect[$key - 1]);
      }
    }

    // print_r($resSelect);exit;

    // SI EXISTEN DOCUMENTOS POSTERIORES SE ENVIA UN MENSAJE
    if (isset($posteriores[0])) {
      $this->response(array(
        'error' => true,
        'data'  => [],
        'mensaje' => 'No se puede anular el documento, está relacionado con docuemntos posteriores'
      ), REST_Controller::HTTP_BAD_REQUEST);

      return;
    }

    //SE INSERTA EL ESTADO DEL DOCUMENTO
    $sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
                          VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";
    $this->pedeo->trans_begin();
    $resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(
      ':bed_docentry' => $Data['docentry'],
      ':bed_doctype' => $Data['doctype'],
      ':bed_status' => 2, //ESTADO ANULADO
      ':bed_createby' => $Data['createby'],
      ':bed_date' => date('Y-m-d'),
      ':bed_baseentry' => NULL,
      ':bed_basetype' => NULL
    ));

    
    if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
    // SI EL DOCUMENTO ANTERIOR TIENE COMO ESTADO CERRADO ENTONCES SE CAMBIA SU ESTADO A ABIERTO 
      if (isset($anterior[0]) AND $anterior[0]['estado'] == 'Cerrado') {

        $sqlInsertEstado2 = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
        VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

        $resInsertEstado2 = $this->pedeo->insertRow($sqlInsertEstado2, array(


          ':bed_docentry' => $Data['baseentry'],
          ':bed_doctype' => $Data['basetype'],
          ':bed_status' => 1, //ESTADO ABIERTO 
          ':bed_createby' => $Data['createby'],
          ':bed_date' => date('Y-m-d'),
          ':bed_baseentry' => $Data['docentry'],
          ':bed_basetype' => $Data['doctype']
        ));

        if (is_numeric($resInsertEstado2) && $resInsertEstado2 > 0) {
        } else {
          $this->pedeo->trans_rollback();

          $respuesta = array(
            'error'   => true,
            'data' => $resInsertEstado2,
            'mensaje'  => 'No se pudo Anular el documento'
          );          
        }
      }

      $sqlUpdate = "UPDATE {{table}} SET {{prefix}}_canceled = 'Y' 
                  WHERE {{prefix}}_docentry = :docentry AND {{prefix}}_doctype = :doctype ";

      $sqlUpdate = str_replace("{{table}}", $table[$type]['table'], $sqlUpdate);
      $sqlUpdate = str_replace("{{prefix}}", $table[$type]['prefix'], $sqlUpdate);

      $resUpdate = $this->pedeo->updateRow(
        $sqlUpdate,
        array(
          ":docentry" => $Data['docentry'],
          ":doctype" => $Data['doctype']
        )
      );

      if (is_numeric($resUpdate) and $resUpdate > 0) {
      } else {
        // $this->pedeo->trans_rollback();

        $respuesta = array(
          'error'   => true,
          'data' => $resUpdate,
          'mensaje'  => 'No se pudo Anular el documento'
        );
      }

    } else {

      $this->pedeo->trans_rollback();

      $respuesta = array(
        'error'   => true,
        'data' => $resInsertEstado,
        'mensaje'  => 'No se pudo Anular el documento'
      );

      
    }

    $this->pedeo->trans_commit();

    $respuesta = array(
      'error'   => false,
      'data' => $resInsertEstado,
      'mensaje'  => 'Documento Anulado'
    );
   
    //FIN PROCESO ESTADO DEL DOCUMENTO

    $this->response($respuesta);
  }


}

?>
