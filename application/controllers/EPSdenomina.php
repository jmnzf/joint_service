<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;



class EPSdenomina extends REST_Controller
{

    private $pdo;
    public function __construct()
    {
        header('Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding');
        header('Access-Control-Allow-Origin: *');
        parent::__construct();
        $this->load->database();
        $this->pdo = $this->load->database('pdo', true)->conn_id;
        $this->load->library('pedeo', [$this->pdo]);
    }



    public function create_post()
    {

        $Data = $this->post();

        if (
            !isset($Data['aes_name']) or
            !isset($Data['aes_status'])
        ) {

            $respuesta = array(
                'error' => true, 'data' => array(), 'mensaje' => 'Faltan parametros'
            );

            return $this->response($respuesta);
        }

        $resInsert = $this->pedeo->insertRow('INSERT INTO naes(aes_name, aes_status, aes_ledaccount) VALUES(:aes_name, :aes_status,:aes_ledaccount)', array(
            ':aes_name' => $Data['aes_name'],
            ':aes_status' => $Data['aes_status'],
            ':aes_ledaccount' => $Data['aes_ledaccount']
        ));

        if (is_numeric($resInsert) && $resInsert > 0) {

            $respuesta = array(
                'error' => false, 'data' => $resInsert, 'mensaje' => 'Registro insertado con exito'
            );

            return $this->response($respuesta);
        } else {

            $respuesta = array(
                'error' => true, 'data' => $resInsert, 'mensaje' => 'Error al insertar el registro'
            );

            return $this->response($respuesta);
        }
    }



    public function update_post()
    {

        $Data = $this->post();

        if (
            !isset($Data['aes_name']) or
            !isset($Data['aes_status']) or
            !isset($Data['aes_id'])
        ) {

            $respuesta = array(
                'error' => true, 'data' => array(), 'mensaje' => 'Faltan parametros'
            );

            return $this->response($respuesta);
        }

        $resUpdate = $this->pedeo->updateRow('UPDATE naes SET aes_name = :aes_name , aes_ledaccount =:aes_ledaccount, aes_status = :aes_status  WHERE aes_id = :aes_id', array(
            'aes_name' => $Data['aes_name'],
            'aes_status' => $Data['aes_status'],
            'aes_ledaccount' => $Data['aes_ledaccount'],
            'aes_id' => $Data['aes_id']
        ));

        if (is_numeric($resUpdate) && $resUpdate == 1) {

            $respuesta = array(
                'error' => false, 'data' => $resUpdate, 'mensaje' => 'Registro actualizado con exito'
            );

            return $this->response($respuesta);
        } else {

            $respuesta = array(
                'error' => true, 'data' => $resUpdate, 'mensaje' => 'Error al actualizar el registro'
            );

            return $this->response($respuesta);
        }
    }



    public function index_get()
    {
        $resSelect = $this->pedeo->queryTable("SELECT aes_name , aes_ledaccount, aes_id , CASE  WHEN aes_status::numeric = 1 THEN 'Activo' WHEN aes_status::numeric = 0 THEN 'Inactivo' END AS aes_status FROM naes ", array());

        if (isset($resSelect[0])) {

            $respuesta = array(
                'error' => false, 'data' => $resSelect, 'mensaje' => ''
            );

            return $this->response($respuesta);
        } else {

            $respuesta = array(
                'error' => true, 'data' => $resSelect, 'mensaje' => 'Busqueda sin resultados'
            );

            return $this->response($respuesta);
        }
    }
}
