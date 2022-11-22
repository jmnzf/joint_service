<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;



class Cargosdenomina extends REST_Controller
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
            !isset($Data['apn_name']) or
            !isset($Data['apn_riskclass']) or
            !isset($Data['apn_status'])
        ) {

            $respuesta = array(
                'error' => true, 'data' => array(), 'mensaje' => 'Faltan parametros'
            );

            return $this->response($respuesta);
        }

        $resInsert = $this->pedeo->insertRow('INSERT INTO napn(apn_name, apn_riskclass, apn_status, apn_ledaccount) VALUES(:apn_name, :apn_riskclass, :apn_status, :apn_ledaccount)', array(
            ':apn_name' => $Data['apn_name'],
            ':apn_riskclass' => $Data['apn_riskclass'],
            ':apn_ledaccount' => $Data['apn_ledaccount'],
            ':apn_status' => $Data['apn_status']
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
            !isset($Data['apn_name']) or
            !isset($Data['apn_riskclass']) or
            !isset($Data['apn_status']) or
            !isset($Data['apn_id'])
        ) {

            $respuesta = array(
                'error' => true, 'data' => array(), 'mensaje' => 'Faltan parametros'
            );

            return $this->response($respuesta);
        }

        $resUpdate = $this->pedeo->updateRow('UPDATE napn SET apn_name = :apn_name ,apn_ledaccount =:apn_ledaccount, apn_riskclass = :apn_riskclass , apn_status = :apn_status  WHERE apn_id = :apn_id', array(
            'apn_name' => $Data['apn_name'],
            'apn_riskclass' => $Data['apn_riskclass'],
            'apn_status' => $Data['apn_status'],
            'apn_ledaccount' => $Data['apn_ledaccount'],
            'apn_id' => $Data['apn_id']
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
        $resSelect = $this->pedeo->queryTable("SELECT apn_name ,apn_ledaccount, narl.arl_name AS apn_riskclass, apn_id , CASE  WHEN apn_status::numeric = 1 THEN 'Activo' WHEN apn_status::numeric = 0 THEN 'Inactivo' END AS apn_status FROM napn LEFT JOIN narl ON napn.apn_riskclass::numeric = narl.arl_id ", array());

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
