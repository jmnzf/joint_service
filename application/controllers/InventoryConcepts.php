<?php
// CONCEPTOS DE INVENTARIO
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class InventoryConcepts extends REST_Controller
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

  //CREAR NUEVO CONCEPTO DE INVENTARIO
  public function createInventoryConcepts_post()
  {

    $Data = $this->post();

    if (
      !isset($Data['icm_name']) or
      !isset($Data['icm_description'])  or
      !isset($Data['icm_acctcode']) or
      !isset($Data['icm_enabled']) or
      !isset($Data['icm_type'])
    ) {

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'La informacion enviada no es valida'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }


    $sqlSelect = "SELECT icm_name FROM micm WHERE icm_name = :icm_name";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array(

      ':icm_name' => $Data['icm_name']

    ));


    if (isset($resSelect[0])) {

      $respuesta = array(
        'error' => true,
        'data'  => array($Data['icm_name'], $Data['icm_name']),
        'mensaje' => 'ya existe un Concepto con ese nombre'
      );

      $this->response($respuesta);

      return;
    }


    $sqlInsert = "INSERT INTO micm  (icm_name , icm_description, icm_acctcode, icm_type, icm_enabled)
                      VALUES            (:icm_name, :icm_description, :icm_acctcode, :icm_type, :icm_enabled)";

    $resInsert = $this->pedeo->insertRow($sqlInsert, array(

      ':icm_name'                 => $Data['icm_name'],
      ':icm_description'          => $Data['icm_description'],
      ':icm_acctcode'             => $Data['icm_acctcode'],
      ':icm_type'                 => $Data['icm_type'],
      ':icm_enabled'              => $Data['icm_enabled']
    ));

    if ($resInsert > 0) {
      $respuesta = array(
        'error' => false,
        'data' => $resInsert,
        'mensaje' => 'Concepto de inventario registrado con exito'
      );
    } else {

      $respuesta = array(
        'error'   => true,
        'data' => array(),
        'mensaje' => 'No se pudo registrar el concepto de inventario'
      );
    }
    $this->response($respuesta);
  }




  //ACTUALIZAR LISTA DE PRECIOS
  public function updateInventoryConcepts_post()
  {

    $Data = $this->post();
    if (
      !isset($Data['icm_name']) or
      !isset($Data['icm_description'])  or
      !isset($Data['icm_acctcode']) or
      !isset($Data['icm_enabled']) or
      !isset($Data['icm_type'])
    ) {

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'La informacion enviada no es valida'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }


    $sqlUpdate = "UPDATE micm SET icm_name = :icm_name, 
                                    icm_description = :icm_description, 
                                    icm_acctcode = :icm_acctcode,
                                    icm_enabled = :icm_enabled,
                                    icm_type = :icm_type
                                    WHERE icm_id = :icm_id";


    $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(


      ':icm_id'                   => $Data['icm_id'],
      ':icm_name'                 => $Data['icm_name'],
      ':icm_description'          => $Data['icm_description'],
      ':icm_acctcode'             => $Data['icm_acctcode'],
      ':icm_type'                 => $Data['icm_type'],
      ':icm_enabled'              => $Data['icm_enabled']
    ));


    if (is_numeric($resUpdate) && $resUpdate == 1) {

      $respuesta = array(
        'error' => false,
        'data' => $resUpdate,
        'mensaje' => 'Concepto de inventario actualizado con exito'
      );
    } else {

      $respuesta = array(
        'error'   => true,
        'data' => $resUpdate,
        'mensaje'  => 'No se pudo actualizar Concepto de inventario creado'
      );
    }

    $this->response($respuesta);
  }


  // OBTENER LISTA DE PRECIOS
  public function getInventoryConcepts_get()
  {

    $sqlSelect = " SELECT * FROM micm";

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
