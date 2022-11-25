<?php
// UNIDAD DE MEDIDAS
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class TypeUnitMeasurement extends REST_Controller
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

  //CREAR NUEVA UNIDAD DE MEDIDA
  public function createTypeUnitMeasurement_post()
  {

    $Data = $this->post();

    if (!isset($Data['dum_name'])) {

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'La informacion enviada no es valida'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }

    $sqlInsert = "INSERT INTO tdum(dum_name, dum_status)VALUES(:dum_name, :dum_status)";


    $resInsert = $this->pedeo->insertRow($sqlInsert, array(
      ':dum_name' => $Data['dum_name'],
      ':dum_status' => $Data['dum_status'],

    ));

    if (is_numeric($resInsert) && $resInsert > 0) {

      $respuesta = array(
        'error'    => false,
        'data'     => $resInsert,
        'mensaje' => 'Tipo de Unidad registrada con exito'
      );
    } else {

      $respuesta = array(
        'error'   => true,
        'data'     => $resInsert,
        'mensaje'  => 'No se pudo registrar el tipo'
      );
    }

    $this->response($respuesta);
  }

  //ACTUALIZAR UNIDAD DE MEDIDA
  public function updateTypeUnitMeasurement_post()
  {

    $Data = $this->post();

    if (!isset($Data['dum_name']) or !isset($Data['dum_id'])) {

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'La informacion enviada no es valida'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }

    $sqlUpdate = "UPDATE tdum SET dum_name = :dum_name, dum_status = :dum_status  WHERE dum_id = :dum_id";
    $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

      ':dum_name' => $Data['dum_name'],
      ':dum_status' => $Data['dum_status'],
      ':dum_id' => $Data['dum_id']
    ));

    if (is_numeric($resUpdate) && $resUpdate == 1) {

      $respuesta = array(
        'error' => false,
        'data' => $resUpdate,
        'mensaje' => 'Tipo actualizado con exito'
      );
    } else {

      $respuesta = array(
        'error'   => true,
        'data'    => $resUpdate,
        'mensaje'  => 'No se pudo actualizar el tipo'
      );
    }

    $this->response($respuesta);
  }


  // OBTENER UNIDAD DE MEDIDA
  public function getTypeUnitMeasurement_get()
  {

    $sqlSelect = " SELECT * FROM tdum";

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

}
