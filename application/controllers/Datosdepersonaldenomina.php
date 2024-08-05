<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;



class Datosdepersonaldenomina extends REST_Controller
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
            !isset($Data['app_doctype']) or
            !isset($Data['app_docnum']) or
            !isset($Data['app_name']) or
            !isset($Data['app_lastname']) or
            !isset($Data['app_mail']) or
            !isset($Data['app_seecolillas'])
        ) {

            $respuesta = array(
                'error' => true, 'data' => array(), 'mensaje' => 'Faltan parametros'
            );

            return $this->response($respuesta);
        }

        $resInsert = $this->pedeo->insertRow('INSERT INTO napp(app_doctype, app_docnum, app_name, app_lastname, app_mail, app_seecolillas, app_business, app_status) VALUES(:app_doctype, :app_docnum, :app_name, :app_lastname, :app_mail, :app_seecolillas, :app_business, :app_status)', array(
            ':app_doctype' => $Data['app_doctype'],
            ':app_docnum' => $Data['app_docnum'],
            ':app_name' => $Data['app_name'],
            ':app_lastname' => $Data['app_lastname'],
            ':app_business' => $Data['app_business'],
            ':app_status' => $Data['app_status'],
            ':app_mail' => $Data['app_mail'],
            ':app_seecolillas' => $Data['app_seecolillas']
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
            !isset($Data['app_doctype']) or
            !isset($Data['app_docnum']) or
            !isset($Data['app_name']) or
            !isset($Data['app_lastname']) or
            !isset($Data['app_mail']) or
            !isset($Data['app_seecolillas']) or
            !isset($Data['app_id'])
        ) {

            $respuesta = array(
                'error' => true, 'data' => array(), 'mensaje' => 'Faltan parametros'
            );

            return $this->response($respuesta);
        }

        $resUpdate = $this->pedeo->updateRow('UPDATE napp SET app_doctype = :app_doctype , app_docnum = :app_docnum ,app_business =:app_business,app_status =:app_status, app_name = :app_name , app_lastname = :app_lastname , app_mail = :app_mail , app_seecolillas = :app_seecolillas  WHERE app_id = :app_id', array(
            'app_doctype' => $Data['app_doctype'],
            'app_docnum' => $Data['app_docnum'],
            'app_name' => $Data['app_name'],
            'app_lastname' => $Data['app_lastname'],
            'app_mail' => $Data['app_mail'],
            'app_status' => $Data['app_status'],
            'app_business' => $Data['app_business'],
            'app_seecolillas' => $Data['app_seecolillas'],
            'app_id' => $Data['app_id']
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
        $resSelect = $this->pedeo->queryTable("SELECT nadc.adc_name AS app_doctype, app_docnum , app_name , app_lastname , app_mail , naen.aen_bussinesname AS empresa, CASE  WHEN app_status::numeric = 1 THEN 'Activo' WHEN app_status::numeric = 0 THEN 'Inactivo' END AS app_status, CASE  WHEN app_seecolillas::numeric = 1 THEN 'Si ' WHEN app_seecolillas::numeric = 0 THEN 'No' END AS app_seecolillas, app_id  FROM napp LEFT JOIN nadc ON napp.app_doctype::numeric = nadc.adc_id LEFT JOIN naen ON napp.app_business::numeric = naen.aen_id ", array());

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
