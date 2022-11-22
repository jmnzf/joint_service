<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;



class Conceptosdeliquidacion extends REST_Controller
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
            !isset($Data['alc_name']) or
            !isset($Data['alc_conceptnature']) or
            !isset($Data['alc_conceptgroup']) or
            !isset($Data['alc_profitliq']) or
            !isset($Data['alc_status'])
        ) {

            $respuesta = array(
                'error' => true, 'data' => array(), 'mensaje' => 'Faltan parametros'
            );

            return $this->response($respuesta);
        }

        $resInsert = $this->pedeo->insertRow('INSERT INTO nalc(alc_name, alc_conceptnature, alc_conceptgroup, alc_profitliq, alc_status, alc_ledaccount) VALUES(:alc_name, :alc_conceptnature, :alc_conceptgroup, :alc_profitliq, :alc_status, :alc_ledaccount)', array(
            ':alc_name' => $Data['alc_name'],
            ':alc_conceptnature' => $Data['alc_conceptnature'],
            ':alc_conceptgroup' => $Data['alc_conceptgroup'],
            ':alc_profitliq' => $Data['alc_profitliq'],
            ':alc_ledaccount' => $Data['alc_ledaccount'],
            ':alc_status' => $Data['alc_status']
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
            !isset($Data['alc_name']) or
            !isset($Data['alc_conceptnature']) or
            !isset($Data['alc_conceptgroup']) or
            !isset($Data['alc_profitliq']) or
            !isset($Data['alc_status']) or
            !isset($Data['alc_id'])
        ) {

            $respuesta = array(
                'error' => true, 'data' => array(), 'mensaje' => 'Faltan parametros'
            );

            return $this->response($respuesta);
        }

        $resUpdate = $this->pedeo->updateRow('UPDATE nalc SET alc_name = :alc_name , alc_conceptnature = :alc_conceptnature , alc_conceptgroup = :alc_conceptgroup , alc_profitliq = :alc_profitliq , alc_status = :alc_status, alc_ledaccount = :alc_ledaccount  WHERE alc_id = :alc_id', array(
            'alc_name' => $Data['alc_name'],
            'alc_conceptnature' => $Data['alc_conceptnature'],
            'alc_conceptgroup' => $Data['alc_conceptgroup'],
            'alc_profitliq' => $Data['alc_profitliq'],
            'alc_status' => $Data['alc_status'],
            'alc_ledaccount' => $Data['alc_ledaccount'],
            'alc_id' => $Data['alc_id']
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
        $resSelect = $this->pedeo->queryTable("SELECT alc_name ,alc_ledaccount, nanc.anc_name AS alc_conceptnature, nacl.acl_name AS alc_conceptgroup, alc_profitliq , alc_id , CASE  WHEN alc_status::numeric = 1 THEN 'Activo' WHEN alc_status::numeric = 0 THEN 'Inactivo' END AS alc_status FROM nalc LEFT JOIN nanc ON nalc.alc_conceptnature::numeric = nanc.anc_id LEFT JOIN nacl ON nalc.alc_conceptgroup::numeric = nacl.acl_id ", array());

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
