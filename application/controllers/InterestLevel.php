<?php
// DATOS MAESTROS ACIENTOS CONTABLES
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class InterestLevel extends REST_Controller
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

    public function getInterestLevel_get()
    {
        $respuesta = array();
        $sqlSelect = "SELECT * FROM tbil";

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
                'data'  => $resSelect,
                'mensaje' => ''
            );
        }
        $this->response($respuesta);
    }

    public function createLevel_post()
    {
        $Data = $this->post();
        $respuesta = array();
        if (
            !isset($Data['bil_name']) or
            !isset($Data['bil_status'])
        ) {

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }
        $sqlInsert = "INSERT INTO tbil (bil_name,bil_status) VALUES (:bil_name, :bil_status)";

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(":bil_name" => $Data['bil_name'], ":bil_status" => $Data['bil_status']));

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
                'mensaje' => 'No se pudo realizar la operacion'
            );
        }
        $this->response($respuesta);
    }

    public function updateLevel_post()
    {
        $Data = $this->post();
        $respuesta = array();
        $sqlUpdate = "UPDATE tbil SET bil_name = :bil_name,
                                      bil_status = :bil_status 
                                      WHERE  bil_id = :bil_id";

        $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
            ":bil_name" => $Data['bil_name'],
            ":bil_status" => $Data['bil_status'],
            ":bil_id" => $Data['bil_id']
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
                'mensaje' => 'No se pudo realizar la operacion'
            );
        }
        $this->response($respuesta);
    }
}
