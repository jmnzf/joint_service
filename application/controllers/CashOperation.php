<?php
// PARAMETRIZACION DE MODULARES CLASIFICACION DE PAGINA
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class CashOperation extends REST_Controller {

	private $pdo;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
        $this->load->library('pedeo', [$this->pdo]);

	}

    // Lista las cajas creadas por usuario
    public function getPosBox_get(){

        $Data = $this->get();

        $sqlSelect = "SELECT * FROM tbcc WHERE business = :business AND branch = :branch AND bcc_user = :bcc_user AND bcc_status = :bcc_status";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(
            ':business' => $Data['business'],
            ':branch' => $Data['branch'],
            ':bcc_user' => $Data['bcc_user'],
            ':bcc_status' => 1
        ));

        if(isset($resSelect[0])){

        $respuesta = array(
            'error' => false,
            'data'  => $resSelect,
            'mensaje' => '');

        }else{

            $respuesta = array(
            'error'   => true,
            'data' => array(),
            'mensaje'	=> 'busqueda sin resultados'
            );

        }

        $this->response($respuesta);
    }

    // Lista la informacion de la operacion de la caja en el dia actual
    public function getInfoApertura_get(){

        $Data = $this->get();

        $sqlSelect = "SELECT bco_id, bco_boxid, bco_date, bco_time, bco_status, bco_amount, bco_total, current_date as factual
                        FROM tbco
                        WHERE bco_date = current_date
                        AND business = :business 
                        AND branch = :branch 
                        AND bco_boxid = :bco_boxid
                        ORDER BY bco_id DESC LIMIT 1";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(
            ':business'   => $Data['business'],
            ':branch'     => $Data['branch'],
            ':bco_boxid'  => $Data['bcc_id']
        ));

        if(isset($resSelect[0])){

            $respuesta = array(
            'error' => false,
            'data'  => $resSelect,
            'mensaje' => '');

        }else{

            $respuesta = array(
                'error'   => true,
                'data' => array(),
                'mensaje'	=> 'busqueda sin resultados'
            );

        }

        $this->response($respuesta);
    }

    // Aperturar Caja
    public function openBox_post(){

        $Data = $this->post();

        if (!isset($Data['bco_boxid']) OR !isset($Data['bco_amount']) OR !isset($Data['bco_total'])){

            $respuesta = array(
                'error' => true,
                'data'  => [],
                'mensaje' => 'Faltan campos requeridos');

            return $this->response($respuesta);
        }


        $sql = "SELECT * FROM tbco WHERE bco_boxid = :bco_boxid AND bco_status = :bco_status ORDER BY bco_id DESC LIMIT 1";
        $resSql = $this->pedeo->queryTable($sql, array(
            ':bco_boxid'  => $Data['bco_boxid'],
            ':bco_status' => 1
        ));

        if (isset($resSql[0])){

            $respuesta = array(
                'error' => true,
                'data'  => [],
                'mensaje' => 'La caja ya se encuentra en estado aperturado');

            return $this->response($respuesta);
        }

        $sqlInsert = "INSERT INTO tbco(bco_boxid, bco_date, bco_time, bco_status, bco_amount, business, branch, bco_total)VALUES(:bco_boxid, :bco_date, :bco_time, :bco_status, :bco_amount, :business, :branch, :bco_total)";

        $resSqlInsert = $this->pedeo->insertRow($sqlInsert, array(
            
            ':bco_boxid' => $Data['bco_boxid'], 
            ':bco_date' => date('Y-m-d'), 
            ':bco_time' => date('H:i:s'), 
            ':bco_status' => 1, 
            ':bco_amount' => $Data['bco_amount'], 
            ':business' => $Data['business'], 
            ':branch' => $Data['branch'], 
            ':bco_total' => $Data['bco_total']
        ));

        if (is_numeric($resSqlInsert) && $resSqlInsert > 0){

            $respuesta = array(
                'error' => false,
                'data'  => [],
                'mensaje' => 'Caja aperturada');

        }else {
            $respuesta = array(
                'error' => true,
                'data'  => $resSqlInsert,
                'mensaje' => 'No se pudo aperturar la caja');

        }

        $this->response($respuesta);

    }

  

}
