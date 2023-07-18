<?php
// Estados de Orden de Fabricación
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/libraries/REST_Controller.php';

use Restserver\libraries\REST_Controller;

class ManufacturingOrderStatuses extends REST_Controller
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

    public function getManufacturingOrderStatuses_get()
    {

        $result = $this->pedeo->queryTable("SELECT * FROM teof ", array());
        // VALIDAR RETORNO DE DATOS DE LA CONSULTA.
        if (isset($result[0])) {
            
            $response = array(
                'error' => false,
                'data' => $result,
                'mensaje' => '',
            );

        }else{

            $response = array(
                'error' => false,
                'data' => $result,
                'mensaje' => 'Busqueda sin resultados',
            );
        }
      
        $this->response($response);
    }

    public function getManufacturingOrderStatusesAct_get()
    {

        $result = $this->pedeo->queryTable("SELECT * FROM teof WHERE eof_status = :eof_status", array(':eof_status' => 1));
        // VALIDAR RETORNO DE DATOS DE LA CONSULTA.
        if (isset($result[0])) {
            
            $response = array(
                'error' => false,
                'data' => $result,
                'mensaje' => '',
            );

        }else{

            $response = array(
                'error' => false,
                'data' => $result,
                'mensaje' => 'Busqueda sin resultados',
            );
        }
      
        $this->response($response);
    }

    public function createManufacturingOrderStatuses_post()
    {

        $Data = $this->post();

        if (!isset($Data['eof_name']) and empty($Data['eof_status'])) {

            $this->response(array(
                'error' => true,
                'data' => [],
                'mensaje' => 'La informacion enviada no es valida'
            ), REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $sqlInsert = "INSERT INTO teof(eof_name, eof_status)VALUES(:eof_name, :eof_status)";

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            ':eof_name' => $Data['eof_name'],
            ':eof_status' => $Data['eof_status']
        ));

        if (is_numeric($resInsert) && $resInsert > 0) {
        } else {
            $respuesta = array(
                'error' => true,
                'data' => $resInsert,
                'mensaje' => 'No se pudo registrar el estado',
            );

            $this->response($respuesta);

            return;
        }

        $respuesta = array(

            'error' => false,
            'data' => $resInsert,
            'mensaje' => 'Estado creado con exito',
        );

        $this->response($respuesta);
    }


    public function updateManufacturingOrderStatuses_post()
    {

        $Data = $this->post();

        if (!isset($Data['eof_id']) OR !isset($Data['eof_name']) OR !isset($Data['eof_status'])) {

            $this->response(array(
                'error' => true,
                'data' => [],
                'mensaje' => 'La informacion enviada no es valida'
            ), REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $sqlInsert = "UPDATE teof SET eof_name = :eof_name, eof_status = :eof_status WHERE eof_id = :eof_id";

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            ':eof_name' => $Data['eof_name'],
            ':eof_status' => $Data['eof_status'],
            ':eof_id' => $Data['eof_id']
        ));

        if (is_numeric($resInsert) && $resInsert > 0) {
        } else {
            $respuesta = array(
                'error' => true,
                'data' => $resInsert,
                'mensaje' => 'No se pudo actualizar el estado',
            );

            $this->response($respuesta);

            return;
        }

        $respuesta = array(
            
            'error' => false,
            'data' => $resInsert,
            'mensaje' => 'Estado actualizado con exito',
        );

        $this->response($respuesta);
    }

}
