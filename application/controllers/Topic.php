<?php
// RETENCIONES
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class Topic extends REST_Controller
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
    public function getTopics_get()
    {
        $sqlSelect = "SELECT * FROM tbpr";

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

        if (!isset($Data['bpr_id'])) {

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $sqlSelect = "SELECT * FROM tbpr where bpr_id = :bpr_id";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(":bpr_id" => $Data['bpr_id']));

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
    public function createTopic_post()
    {
        $Data = $this->post();

        if (
            !isset($Data['bpr_name']) or
            !isset($Data['bpr_status'])
        ) {

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }
        $sqlInsert = "INSERT INTO tbpr (bpr_name, bpr_status) VALUES(:bpr_name, :bpr_status)";

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            ":bpr_name" => $Data['bpr_name'],
            ":bpr_status" => $Data['bpr_status']
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
    public function updateTopic_post()
    {
        $Data = $this->post();

        if (
            !isset($Data['bpr_name']) or
            !isset($Data['bpr_status'])
        ) {

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }
        $sqlUpdate = "UPDATE tbpr  SET 
            bpr_name = :bpr_name, 
            bpr_status = :bpr_status
            where bpr_id = :bpr_id";

        $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
            ":bpr_name" => $Data['bpr_name'],
            ":bpr_status" => $Data['bpr_status'],
            ":bpr_id" => $Data['bpr_id'],
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
