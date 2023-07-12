<?php
// VALIDACIONES ESPECIFICAS PARA EL NEGOCIO
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class Ven extends REST_Controller
{

  private $pdo;

  public function __construct()
  {

    header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
    header("Access-Control-Allow-Origin: *");

    parent::__construct();
    $this->load->database();
    $this->pdo = $this->load->database('pdo', true)->conn_id;
    $this->load->library('pedeo', [$this->pdo]);
  }


  public function createVen_post()  {

    $Data = $this->post();

    if ( !isset($Data['ven_doctype']) OR !isset($Data['ven_query']) 
         OR !isset($Data['ven_status']) OR !isset($Data['ven_head']) OR !isset($Data['ven_detail'])
         OR !isset($Data['ven_messaje']) OR !isset($Data['ven_name']) ) {

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'La informacion enviada no es valida'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }


    $sqlInsert = "INSERT INTO tven(ven_name, ven_doctype, ven_query, ven_status, ven_head, ven_detail, ven_messaje)
                 VALUES(:ven_name, :ven_doctype, :ven_query, :ven_status, :ven_head, :ven_detail, :ven_messaje)";


    $resInsert = $this->pedeo->insertRow($sqlInsert, array(
        ':ven_doctype' => $Data['ven_doctype'],
        ':ven_query'   => $Data['ven_query'],
        ':ven_status'  => $Data['ven_status'],
        ':ven_head'    => $Data['ven_head'],
        ':ven_detail'  => $Data['ven_detail'],
        ':ven_messaje' => $Data['ven_messaje'],
        ':ven_name'    => $Data['ven_name']
    ));

    if (is_numeric($resInsert) && $resInsert > 0) {

      $respuesta = array(
        'error'    => false,
        'data'     => $resInsert,
        'mensaje' => 'Verificación registrada con exito'
      );
    } else {

      $respuesta = array(
        'error'   => true,
        'data'     => $resInsert,
        'mensaje'  => 'No se pudo registrar la verificación'
      );
    }

    $this->response($respuesta);
  }

  public function updateVen_post()
  {

    $Data = $this->post();

    if ( !isset($Data['ven_doctype']) OR !isset($Data['ven_query']) 
        OR !isset($Data['ven_status']) OR !isset($Data['ven_head']) OR !isset($Data['ven_detail'])
        OR !isset($Data['ven_messaje']) OR !isset($Data['ven_id'])  OR !isset($Data['ven_name'])) {

        $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
    }

    $sqlUpdate = "UPDATE tven SET ven_name = :ven_name, ven_doctype = :ven_doctype, ven_query = :ven_query, 
                  ven_status = :ven_status, ven_head = :ven_head, ven_detail = :ven_detail,
                  ven_messaje = :ven_messaje WHERE ven_id = :ven_id";



    $resUpdate= $this->pedeo->updateRow($sqlUpdate, array(
        ':ven_name' => $Data['ven_name'],
        ':ven_doctype' => $Data['ven_doctype'],
        ':ven_query' => $Data['ven_query'],
        ':ven_status'=> $Data['ven_status'],
        ':ven_head' => $Data['ven_head'],
        ':ven_detail'=> $Data['ven_detail'],
        ':ven_messaje' => $Data['ven_messaje'],
        ':ven_id' => $Data['ven_id']
    ));

   

    if (is_numeric($resUpdate) && $resUpdate == 1) {

      $respuesta = array(
        'error' => false,
        'data' => $resUpdate,
        'mensaje' => 'Verificación actualizada con exito'
      );
    } else {

      $respuesta = array(
        'error'   => true,
        'data'    => $resUpdate,
        'mensaje'  => 'No se pudo actualizar la verificación'
      );
    }

    $this->response($respuesta);
  }



  public function getVen_get()
  {

    $sqlSelect = "SELECT tven.*, mdt_docname 
                FROM tven 
                INNER JOIN dmdt
                ON dmdt.mdt_id = tven.ven_doctype";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array());

    if (isset($resSelect[0])) {

      $respuesta = array(
        'error' => false,
        'data'  => $resSelect,
        'mensaje' => ''
      );
    } else {

      $respuesta = array(
        'error'   => true,
        'data' => array(),
        'mensaje'  => 'busqueda sin resultados'
      );
    }

    $this->response($respuesta);
  }


  public function getVenByDoc_post()
  {

    $Data = $this->post();

    if ( !isset( $Data['ven_doctype'] ) ) {
    
        $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
    }

    $sqlSelect = "SELECT tven.*, mdt_docname 
                FROM tven 
                INNER JOIN dmdt
                ON dmdt.mdt_id = tven.ven_doctype
                WHERE ven_doctype = :ven_doctype
                AND ven_status = :ven_status";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array(":ven_doctype" => $Data['ven_doctype'], ":ven_status" => 1));

    if (isset($resSelect[0])) {

      $respuesta = array(
        'error' => false,
        'data'  => $resSelect,
        'mensaje' => ''
      );
    } else {

      $respuesta = array(
        'error'   => true,
        'data' => array(),
        'mensaje'  => 'busqueda sin resultados'
      );
    }

    $this->response($respuesta);
  }

  public function resultQuery_post(){

    $Data = $this->post();

    if ( !isset( $Data['sql'] ) ) {
    
        $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
    }

    $resSelect = $this->pedeo->queryTable($Data['sql'], array());

    if (isset($resSelect[0])) {

      $respuesta = array(
        'error' => false,
        'data'  => $resSelect,
        'mensaje' => ''
      );
    } else {

      $respuesta = array(
        'error'   => true,
        'data' => array(),
        'mensaje'  => 'busqueda sin resultados'
      );
    }

    $this->response($respuesta);
  }


 
}