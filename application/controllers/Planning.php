<?php
// RETENCIONES
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class Planning extends REST_Controller
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
    public function getPlanning_get()
    {
        $sqlSelect = "SELECT * FROM tbpl";

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
    public function getPlanningById_get()
    {
        $Data =   $this->get();

        if (!isset($Data['bpl_id'])) {

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $sqlSelect = "SELECT * FROM tbpl where bpl_id = :bpl_id";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(":bpl_id" => $Data['bpl_id']));

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
    public function createPlanning_post()
    {
        $Data = $this->post();

        if (
            !isset($Data['bpl_day']) OR 
            !isset($Data['bpl_qty1']) OR 
            !isset($Data['bpl_qty2']) OR 
            !isset($Data['bpl_qty3']) OR 
            !isset($Data['bpl_qty4']) OR 
            !isset($Data['bpl_comments']) OR 
            !isset($Data['bpl_execution'])
        ) {

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }
        $sqlInsert = "INSERT INTO tbpl (bpl_day, bpl_qty1, bpl_qty2, bpl_qty3, bpl_qty4, bpl_comments, bpl_execution,\"bpl_resourceId\") VALUES(:bpl_day, :bpl_qty1, :bpl_qty2, :bpl_qty3, :bpl_qty4, :bpl_comments, :bpl_execution, :bpl_resourceId)";

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            ":bpl_day" => $Data['bpl_day'], 
            ":bpl_qty1" => $Data['bpl_qty1'], 
            ":bpl_qty2" => $Data['bpl_qty2'],
            ":bpl_qty3" => $Data['bpl_qty3'],
            ":bpl_qty4" => $Data['bpl_qty4'],
            ":bpl_comments" => $Data['bpl_comments'], 
            ":bpl_execution" => $Data['bpl_execution'],
            ":bpl_resourceId"   => $Data['bpl_resourceId']
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
                'mensaje' => 'No se pudo crear la planificacion'
            );
        }

        $this->response($respuesta);
    }
// METODO PARA ACTUALIZAR PROPIEDAD
    public function updatePlanning_post()
    {
        $Data = $this->post();

        if (            
            !isset($Data['bpl_day']) OR 
            !isset($Data['bpl_qty1']) OR 
            !isset($Data['bpl_qty2']) OR 
            !isset($Data['bpl_qty3']) OR 
            !isset($Data['bpl_qty4']) OR 
            !isset($Data['bpl_comments']) OR 
            !isset($Data['bpl_execution']) OR 
            !isset($Data['bpl_id'])
        ) {

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }
        $sqlUpdate = "UPDATE tbpl  SET 
            bpl_day = :bpl_day, 
            bpl_qty1 = :bpl_qty1, 
            bpl_qty2 = :bpl_qty2,
            bpl_qty3 = :bpl_qty3,
            bpl_qty4 = :bpl_qty4,
            bpl_comments = :bpl_comments, 
            bpl_execution = :bpl_execution
            where bpl_id = :bpl_id";

        $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
            ":bpl_day" => $Data['bpl_day'], 
            ":bpl_qty1" => $Data['bpl_qty1'], 
            ":bpl_qty2" => $Data['bpl_qty2'],
            ":bpl_qty3" => $Data['bpl_qty3'],
            ":bpl_qty4" => $Data['bpl_qty4'],
            ":bpl_comments" => $Data['bpl_comments'], 
            ":bpl_execution" => $Data['bpl_execution'],
            ":bpl_id" => $Data['bpl_id']
            
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

    public function getPlanningByResourceId_post(){
        $Data = $this->post();
        $sqlSelect = 'SELECT * FROM tbpl where "bpl_resourceId" = :bpl_resouceId';
        // print_r($sqlSelect);exit;
        $resSelect = $this->pedeo->queryTable($sqlSelect, array(":bpl_resouceId" => $Data['bpl_resouceId']));

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
}
