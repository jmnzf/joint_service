<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;



class Riesgoslaborales extends REST_Controller
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
            !isset($Data['arl_name']) or
            !isset($Data['arl_profit']) or
            !isset($Data['arl_status'])
        ) {

            $respuesta = array(
                'error' => true, 'data' => array(), 'mensaje' => 'Faltan parametros'
            );

            return $this->response($respuesta);
        }

        $resInsert = $this->pedeo->insertRow('INSERT INTO narl(arl_name, arl_ledaccount, arl_profit, arl_status) VALUES(:arl_name, :arl_profit,:arl_ledaccount, :arl_status)', array(
            ':arl_name' => $Data['arl_name'],
            ':arl_profit' => $Data['arl_profit'],
            ':arl_ledaccount' => $Data['arl_ledaccount'],
            ':arl_status' => $Data['arl_status']
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
            !isset($Data['arl_name']) or
            !isset($Data['arl_profit']) or
            !isset($Data['arl_status']) or
            !isset($Data['arl_id'])
        ) {

            $respuesta = array(
                'error' => true, 'data' => array(), 'mensaje' => 'Faltan parametros'
            );

            return $this->response($respuesta);
        }

        $resUpdate = $this->pedeo->updateRow('UPDATE narl SET arl_name = :arl_name ,arl_ledaccount =:arl_ledaccount, arl_profit = :arl_profit , arl_status = :arl_status  WHERE arl_id = :arl_id', array(
            'arl_name' => $Data['arl_name'],
            'arl_profit' => $Data['arl_profit'],
            'arl_ledaccount' => $Data['arl_ledaccount'],
            'arl_status' => $Data['arl_status'],
            'arl_id' => $Data['arl_id']
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
        $resSelect = $this->pedeo->queryTable("SELECT arl_name , arl_profit , arl_id ,arl_ledaccount, CASE  WHEN arl_status::numeric = 1 THEN 'Activo' WHEN arl_status::numeric = 0 THEN 'Inactivo' END AS arl_status FROM narl ", array());

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
