<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;



class Conceptosderecursos extends REST_Controller
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
            !isset($Data['drs_concepto']) or
            !isset($Data['drs_cuentacontable']) or
            !isset($Data['drs_status'])
        ) {

            $respuesta = array(
                'error' => true, 'data' => array(), 'mensaje' => 'Faltan parametros'
            );

            return $this->response($respuesta);
        }

        $resInsert = $this->pedeo->insertRow('INSERT INTO cdrs(drs_concepto, drs_cuentacontable, drs_status) VALUES(:drs_concepto, :drs_cuentacontable, :drs_status)', array(
            ':drs_concepto' => $Data['drs_concepto'],
            ':drs_cuentacontable' => $Data['drs_cuentacontable'],
            ':drs_status' => $Data['drs_status']
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
            !isset($Data['drs_concepto']) or
            !isset($Data['drs_cuentacontable']) or
            !isset($Data['drs_status']) or
            !isset($Data['drs_id'])
        ) {

            $respuesta = array(
                'error' => true, 'data' => array(), 'mensaje' => 'Faltan parametros'
            );

            return $this->response($respuesta);
        }

        $resUpdate = $this->pedeo->updateRow('UPDATE cdrs SET drs_concepto = :drs_concepto , drs_cuentacontable = :drs_cuentacontable , drs_status = :drs_status  WHERE drs_id = :drs_id', array(
            'drs_concepto' => $Data['drs_concepto'],
            'drs_cuentacontable' => $Data['drs_cuentacontable'],
            'drs_status' => $Data['drs_status'],
            'drs_id' => $Data['drs_id']
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
        $resSelect = $this->pedeo->queryTable("SELECT drs_concepto , dacc.acc_name AS drs_nombrecuenta, drs_cuentacontable, drs_id , CASE  WHEN drs_status = 1 THEN 'Activo' WHEN drs_status = 0 THEN 'Inactivo' END AS drs_status FROM cdrs LEFT JOIN dacc ON cdrs.drs_cuentacontable = dacc.acc_code ", array());

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
