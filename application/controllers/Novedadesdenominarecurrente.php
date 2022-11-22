<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;



class Novedadesdenominarecurrente extends REST_Controller
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
            !isset($Data['anr_empleado']) or
            !isset($Data['anr_concept']) or
            !isset($Data['anr_status'])
        ) {

            $respuesta = array(
                'error' => true, 'data' => array(), 'mensaje' => 'Faltan parametros'
            );

            return $this->response($respuesta);
        }

        $resInsert = $this->pedeo->insertRow('INSERT INTO nanr(anr_empleado, anr_concept, anr_status) VALUES(:anr_empleado, :anr_concept, :anr_status)', array(
            ':anr_empleado' => $Data['anr_empleado'],
            ':anr_concept' => $Data['anr_concept'],
            ':anr_status' => $Data['anr_status']
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
            !isset($Data['anr_empleado']) or
            !isset($Data['anr_concept']) or
            !isset($Data['anr_status']) or
            !isset($Data['anr_id'])
        ) {

            $respuesta = array(
                'error' => true, 'data' => array(), 'mensaje' => 'Faltan parametros'
            );

            return $this->response($respuesta);
        }

        $resUpdate = $this->pedeo->updateRow('UPDATE nanr SET anr_empleado = :anr_empleado , anr_concept = :anr_concept , anr_status = :anr_status  WHERE anr_id = :anr_id', array(
            'anr_empleado' => $Data['anr_empleado'],
            'anr_concept' => $Data['anr_concept'],
            'anr_status' => $Data['anr_status'],
            'anr_id' => $Data['anr_id']
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
        $resSelect = $this->pedeo->queryTable("SELECT napp.app_docnum AS anr_empleado, nalc.alc_name AS anr_concept, CASE  WHEN anr_status::numeric = 1 THEN 'Activo' WHEN anr_status::numeric = 0 THEN 'Inactivo' END AS anr_status , anr_id  FROM nanr LEFT JOIN napp ON nanr.anr_empleado::numeric = napp.app_id LEFT JOIN nalc ON nanr.anr_concept::numeric = nalc.alc_id ", array());

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
