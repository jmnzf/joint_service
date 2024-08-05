<?php
// MOVIMIENTO DE SERIALES
// ESTADOS DE SERALES
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Serials extends REST_Controller {

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


    // MOVIMIENTO DE SERIALES
    public function getMovimiento_post() {

        $Data = $this->post();

        $where = ' where 1 = 1 and dmws.business = '.$Data['business'].' ';

        if( isset($Data['fi']) && !empty($Data['fi']) && isset($Data['ff']) && !empty($Data['ff'])){
            $where .= " and msn_createat between '".$Data['fi']."' and '".$Data['ff']."'";
        }

        if( isset($Data['whscode']) && !empty($Data['whscode']) ) {
            $where .= " and msn_whscode = '".$Data['whscode']."'";
        }

        if( isset($Data['itemcode']) && !empty($Data['itemcode']) ) {
            $where .= " and msn_itemcode = '".$Data['itemcode']."'";
        }

        if( isset($Data['sn']) && !empty($Data['sn']) ) {
            $where = " and msn_sn = '".$Data['sn']."'";
        }
        

        $sqlSelect = "SELECT msn_docnum as numero_documento,
        msn_comment as comentario_documento,
        msn_itemcode as codigo_item,
        dmar.dma_item_name as nombre_item,
        tmsn.msn_createat  as fecha_movimiento,
        case 
            when msn_status = 1 then 'Dentro del Almacen'
            when msn_status = 2 then 'Fuera del Almacen'
        end as estado,
        msn_whscode as codigo_almacen,
        dmws.dws_name as nombre_almacen,
        dmdt.mdt_docname as nombre_documento,
        msn_sn as numero_serial,
        msn_createby as usuario
        FROM tmsn
        inner join dmdt
        on tmsn.msn_basetype = dmdt.mdt_doctype 
        inner join dmar
        on tmsn.msn_itemcode = dmar.dma_item_code 
        inner join dmws
        on tmsn.msn_whscode = dmws.dws_code " .$where;

        $resSelect = $this->pedeo->queryTable($sqlSelect, array());

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

    // CANTIDAD DE SERIALES
    public function getCantidad_post() {

        $Data = $this->post();

        $where = ' where 1 = 1 and tmsn.business = '.$Data['business'].' and  msn_id in ( select msn_id from tmsn where msn_id  in ( select max(msn_id) from tmsn where msn_status  = 1  group by msn_id ) )';

       

        if( isset($Data['whscode']) && !empty($Data['whscode']) ) {
            $where .= " and msn_whscode = '".$Data['whscode']."'";
        }

        if( isset($Data['itemcode']) && !empty($Data['itemcode']) ) {
            $where .= " and msn_itemcode = '".$Data['itemcode']."'";
        }

        

        $sqlSelect = "SELECT msn_sn as numero_serial,
         msn_whscode as codigo_almacen,
         dmws.dws_name as nombre_almacen,
         msn_itemcode as codigo_item,
         dmar.dma_item_name as nombre_item,
         tmsn.msn_createat  as fecha_movimiento
        FROM tmsn
        inner join dmar
        on tmsn.msn_itemcode = dmar.dma_item_code 
        inner join dmws
        on tmsn.msn_whscode = dmws.dws_code " .$where;

        $resSelect = $this->pedeo->queryTable($sqlSelect, array());

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



}
