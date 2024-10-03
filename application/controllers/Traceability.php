<?php
// TRAZABILIDAD DE DOCUMENTOS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Traceability extends REST_Controller {

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

  //Consultar TRAZABILIDAD DE UN DOCUMENTO
	public function getTraceability_post(){

      $Data = $this->post();

      if(!isset($Data['bmd_doctype']) OR
         !isset($Data['bmd_docentry'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlSelect = "SELECT tbmd.*, mdt_docname, t1.estado
                    FROM tbmd
                    INNER JOIN dmdt ON tbmd.bmd_doctype = dmdt.mdt_doctype
                    INNER JOIN responsestatus t1 ON tbmd.bmd_docentry = t1.id and tbmd.bmd_doctype = t1.tipo
                    inner join (SELECT distinct concat(tb1.bmd_tdi, tb1.bmd_ndi) as con, tb1.bmd_id as id, tb1.bmd_cardtype as cardtype FROM tbmd as tb1
                    WHERE tb1.bmd_doctype  = :bmd_doctype
                    AND tb1.bmd_docentry = :bmd_docentry) as regs
                    on concat(bmd_tdi, bmd_ndi) = regs.con
                    where bmd_id >= regs.id and bmd_cardtype = regs.cardtype
                    ORDER BY tbmd.bmd_id ASC";

      $resSelect = $this->pedeo->queryTable($sqlSelect, array(
        ':bmd_doctype'  => $Data['bmd_doctype'],
        ':bmd_docentry' => $Data['bmd_docentry']
      ));

      if( isset($resSelect[0]) ){

            $respuesta = array(
              'error' => false,
              'data' => $resSelect,
              'mensaje' =>''
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data' 		=> $resSelect,
              'mensaje'	=> 'Busqueda sin resultados'
            );

      }

       $this->response($respuesta);
	}

}