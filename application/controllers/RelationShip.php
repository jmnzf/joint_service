<?php
// REPORTES DE INVENTARIO
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class RelationShip extends REST_Controller {

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

  //INFORME AUDITORIA STOCK
	public function RelationShipSales_post(){

      	$request = $this->post();



      	$sql = 'SELECT
                      t0.dvc_cardcode codigo_cliente,
                      t0.dvc_cardname nombre_cliente,
                      t0.dvc_docnum num_cotizacion,
                      t0.dvc_docdate fecha_cotizacion,
                      t0.dvc_doctotal total_cotizacion,
                      t1.vov_docnum num_pedido,
                      t1.vov_docdate fecha_pedido,
                      t1.vov_doctotal total_pedido,
                      t2.vem_docnum num_entrega,
                      t2.vem_docdate fecha_entrega,
                      t2.vem_doctotal total_entrega,
                      t3.vdv_docnum num_devolucion,
                      t3.vdv_docdate fecha_devolucion,
                      t3.vdv_doctotal total_devolucion,
                      t4.dvf_docnum num_devolucion,
                      t4.dvf_docdate fecha_devolucion,
                      t4.dvf_doctotal total_devolucion

                  from dvct t0
                  left join dvov t1 on t0.dvc_docentry = t1.vov_baseentry and t0.dvc_doctype = t1.vov_basetype
                  left join dvem t2 on t1.vov_docentry = t2.vem_baseentry and t1.vov_doctype = t2.vem_basetype
                  left join dvdv t3 on t2.vem_docentry = t3.vdv_baseentry and t2.vem_doctype = t3.vdv_basetype
                  left join dvfv t4 on t2.vem_docentry = t4.dvf_baseentry and t2.vem_doctype = t4.dvf_basetype ';


      	$result = $this->pedeo->queryTable($sql,array());
// print_r($result);exit();die();
		if(isset($result[0])){

			$respuesta = array(
				'error'   => false,
				'data'    => $result,
				'mensaje' =>''
			);

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
