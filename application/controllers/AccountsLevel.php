<?php
// FACTURA DE COMPRAS
defined('BASEPATH') or exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH . '/asset/vendor/autoload.php');
require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class AccountsLevel extends REST_Controller
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

    // METODO PARA OBTENER LOS PRESUPUESTOS
    public function geAccountLevels_get()
    {
        $sqlSelect = "SELECT * from tbnc";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array());

        if (isset($resSelect[0])) {

            $respuesta = array(
                'error' => false,
                'data'  => $resSelect,
                'mensaje' => ''
            );
        } else {

            $respuesta = array(
                'error'   => true,
                'data' => array(),
                'mensaje'    => 'busqueda sin resultados'
            );
        }

        $this->response($respuesta);
    }

    public function createAccountLevel_post()
    {
        $Data = $this->post();
        if (
            !isset($Data['bnc_name']) or
            !isset($Data['bnc_status'])
        ) {
            $this->response(array(
                'error'  => true,
                'data'   => [],
                'mensaje' => 'La informacion enviada no es valida'
            ), REST_Controller::HTTP_BAD_REQUEST);

            return;
        }
        $sqlInsert = "INSERT INTO tbnc (bnc_name,bnc_status) values (:bnc_name, :bnc_status)";

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            ':bnc_name' => $Data['bnc_name'],
            ':bnc_status' => $Data['bnc_status']
        ));

        if (is_numeric($resInsert) && $resInsert > 0) {
            $respuesta = array(
                'error' => false,
                'data'  => $resInsert,
                'mensaje' => 'Nivel de cuenta creada con exito'
            );
        } else {
            $respuesta = array(
                'error' => true,
                'data'  => $resInsert,
                'mensaje' => 'No se pudo crear nivel'
            );
        }

        $this->response($respuesta);
    }

    public function updateAccountLevel_post()
    {
        $Data = $this->post();

        if (
            !isset($Data['bnc_name']) or
            !isset($Data['bnc_status']) or
            !isset($Data['bnc_id'])
        ) {
            $this->response(array(
                'error'  => true,
                'data'   => [],
                'mensaje' => 'La informacion enviada no es valida'
            ), REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $sqlUpdate = "UPDATE tbnc SET bnc_name = :bnc_name,
                                      bnc_status = :bnc_status
                                      where bnc_id = :bnc_id";

        $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
            ':bnc_name' => $Data['bnc_name'],
            ':bnc_status' => $Data['bnc_status'],
            ':bnc_id' => $Data['bnc_id']
        ));

        if (is_numeric($resUpdate) && $resUpdate > 0) {
            $respuesta = array(
                'error' => false,
                'data'  => $resUpdate,
                'mensaje' => 'Nivel de cuenta creada con exito'
            );
        } else {
            $respuesta = array(
                'error' => true,
                'data'  => $resUpdate,
                'mensaje' => 'No se pudo crear nivel'
            );
        }

        $this->response($respuesta);
    }
}
