<?php
// ESTADO DE DOCUMENTOS
defined('BASEPATH') or exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH . '/asset/vendor/autoload.php');
require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class DocumentStatus extends REST_Controller
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

    // OBTENER ESTADOS DE DOCUMENTOS PARA ORDER DE FABRICION
    public function getDocumentStatusManufacturingOrder_get()
    {
        $sqlSelect = "SELECT * from tded WHERE ded_id IN(8,7,3)";

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


}
