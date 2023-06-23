<?php
// DATOS MAESTROS PROYECTO
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/asset/vendor/autoload.php');
require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class ElectronicBill extends REST_Controller
{

    private $pdo;
    private $pdo_fe;
    public function __construct()
    {

        header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
        header("Access-Control-Allow-Origin: *");

        parent::__construct();
        $this->load->database();
        $this->pdo_fe = $this->load->database('fe', true)->conn_id;
        $this->load->library('fe', [$this->pdo_fe]);

        //
        $this->load->database();
        $this->pdo = $this->load->database('pdo', true)->conn_id;
        $this->load->library('pedeo', [$this->pdo]);
        $this->load->library('DateFormat');
		$this->load->library('generic');

    }

    public function rutaRepGraphic_post()
    {
        $Data = $this->post();
        //
        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'Faltan datos requeridos'
        );
        //
        if(isset($Data['type']) && $Data['type'] == "FV"){
            //
            $sql = "SELECT dvf_ruta_pdf FROM dvfv WHERE dvf_docentry = :dvf_docentry";
            //
            $resSql = $this->pedeo->queryTable($sql,array(
                ':dvf_docentry' => $Data['docentry']
            ));
            //
            if(isset($resSql[0])){
                $respuesta = array(
                    'error' => false,
                    'data' => $resSql[0]['dvf_ruta_pdf'],
                    'mensaje' => 'OK'
                );
            }else{
                $respuesta = array(
                    'error' => false,
                    'data' => [],
                    'mensaje' => 'No existe el archivo pdf del documento '.$Data['docentry']
                );
            }
        }else if(isset($Data['type']) && $Data['type'] == "NC"){
            //
            $sql = "SELECT vnc_ruta_pdf FROM dvnc WHERE vnc_docentry = :vnc_docentry";
            //
            $resSql = $this->pedeo->queryTable($sql,array(
                ':vnc_docentry' => $Data['docentry']
            ));
            //
            if(isset($resSql[0])){
                $respuesta = array(
                    'error' => false,
                    'data' => $resSql[0]['vnc_ruta_pdf'],
                    'mensaje' => 'OK'
                );
            }else{
                $respuesta = array(
                    'error' => false,
                    'data' => [],
                    'mensaje' => 'No existe el archivo pdf del documento '.$Data['docentry']
                );
            }
        }

        $this->response($respuesta);
    }
}
