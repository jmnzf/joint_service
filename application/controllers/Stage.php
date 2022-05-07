<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') or exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH . '/asset/vendor/autoload.php');
require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class Stage extends REST_Controller
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

    // LISTAR ETAPAS
    public function getStage_get()
    {

        $respuesta = array();
        $sqlSelect = "SELECT * FROM tbet";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array());

        if (isset($resSelect[0])) {
            $respuesta = array(
                'error' => false,
                'data'  => $resSelect,
                'mensaje' => ''
            );
        } else {
            $respuesta = array(
                'error' => true,
                'data'  => [],
                'mensaje' => 'No existen datos asociados a la consulta'
            );
        }
        $this->response($respuesta);
    }

    // CREAR ETAPA
    public function createStage_post()
    {
        $Data = $this->post();

        if (
            !isset($Data['bet_name']) or
            !isset($Data['bet_porcent'])
        ) {
            $this->response(array(
                'error'  => true,
                'data'   => [],
                'mensaje' => 'La informacion enviada no es valida'
            ), REST_Controller::HTTP_BAD_REQUEST);

            return;
        }
        $sqlInsert = "INSERT INTO tbet (bet_name,bet_porcent,bet_status) VALUES (:bet_name, :bet_porcent, :bet_status)";
        $resInsert = $this->pedeo->insertRow(
            $sqlInsert,
            array(
                ":bet_name" => $Data['bet_name'],
                ":bet_porcent" => $Data['bet_porcent'],
                ":bet_status" => $Data['bet_status']
            )
        );

        if (is_numeric($resInsert) && $resInsert > 0) {
            $respuesta = array(
                'error' => false,
                'data'  => $resInsert,
                'mensaje' => 'Etapa registrada con exito'
            );
        } else {
            $respuesta = array(
                'error' => true,
                'data'  => $resInsert,
                'mensaje' => 'No se pudo registrar la etapa'
            );
        }
        $this->response($respuesta);
    }

    public function updateStage_post(){
        $Data = $this->post();

        if (
            !isset($Data['bet_name']) or
            !isset($Data['bet_porcent']) or
            !isset($Data['bet_id'])
        ) {
            $this->response(array(
                'error'  => true,
                'data'   => [],
                'mensaje' => 'La informacion enviada no es valida'
            ), REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $sqlUpdate = "UPDATE tbet SET bet_name = :bet_name,bet_porcent = :bet_porcent, bet_status = :bet_status WHERE bet_id = :bet_id";

        $resUpdate = $this->pedeo->updateRow($sqlUpdate,array(":bet_name" => $Data['bet_name'],
                                                              ":bet_porcent" => $Data['bet_porcent'],
                                                              ":bet_status" => $Data['bet_status'],
                                                              ":bet_id" => $Data['bet_id']));

        if (is_numeric($resUpdate) && $resUpdate > 0) {
            $respuesta = array(
                'error' => false,
                'data'  => $resUpdate,
                'mensaje' => 'Etapa registrada con exito'
            );
        } else {
            $respuesta = array(
                'error' => true,
                'data'  => $resUpdate,
                'mensaje' => 'No se pudo registrar la etapa'
            );
        }
        $this->response($respuesta);
    }
}
