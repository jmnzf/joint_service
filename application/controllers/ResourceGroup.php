<?php
// RETENCIONES
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class ResourceGroup extends REST_Controller
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
    // METODO PARA OBTENER LISTADO DE PROPIEDADES
    public function getResourceGroup_get()
    {
        $sqlSelect = "SELECT * FROM tbgr";

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
                'mensaje'    => 'busqueda sin resultados'
            );
        }

        $this->response($respuesta);
    }

    // METODO PARA OBTENER PROPIEDAD POR ID 
    public function getTopicsById_get()
    {
        $Data =   $this->get();

        if (!isset($Data['bgr_id'])) {

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $sqlSelect = "SELECT * FROM tbgr where bgr_id = :bgr_id";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(":bgr_id" => $Data['bgr_id']));

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
                'mensaje'    => 'busqueda sin resultados'
            );
        }

        $this->response($respuesta);
    }
    //  METODO PARA CREAR PROPIEDADES
    public function createResourceGroup_post()
    {
        $Data = $this->post();

        if (
            !isset($Data['bgr_name']) or
            !isset($Data['bgr_status'])
        ) {

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }
        $sqlInsert = "INSERT INTO tbgr (bgr_name, bgr_status) VALUES(:bgr_name, :bgr_status)";

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            ":bgr_name" => $Data['bgr_name'],
            ":bgr_status" => $Data['bgr_status']
        ));

        if (is_numeric($resInsert) && $resInsert > 0) {
            $respuesta = array(
                'error' => false,
                'data'  => $resInsert,
                'mensaje' => 'Operacion exitosa'
            );
        } else {
            $respuesta = array(
                'error' => true,
                'data'  => $resInsert,
                'mensaje' => 'No se pudo crear la propiedad'
            );
        }

        $this->response($respuesta);
    }
// METODO PARA ACTUALIZAR PROPIEDAD
    public function updateResourceGroup_post()
    {
        $Data = $this->post();

        if (
            !isset($Data['bgr_id']) or
            !isset($Data['bgr_name']) or
            !isset($Data['bgr_status'])
        ) {

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }
        $sqlUpdate = "UPDATE tbgr  SET 
            bgr_name = :bgr_name, 
            bgr_status = :bgr_status
            where bgr_id = :bgr_id";

        $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
            ":bgr_name" => $Data['bgr_name'],
            ":bgr_status" => $Data['bgr_status'],
            ":bgr_id" => $Data['bgr_id'],
        ));

        if (is_numeric($resUpdate) && $resUpdate > 0) {
            $respuesta = array(
                'error' => false,
                'data'  => $resUpdate,
                'mensaje' => 'Operacion exitosa'
            );
        } else {
            $respuesta = array(
                'error' => true,
                'data'  => $resUpdate,
                'mensaje' => 'No se pudo crear la propiedad'
            );
        }

        $this->response($respuesta);
    }
}
