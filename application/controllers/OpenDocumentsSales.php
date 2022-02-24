<?php
// Entrega de Ventas
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class OpenDocumentsSales extends REST_Controller {

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

  //CREAR NUEVA Entrega de Ventas
	public function OpenOrder_post(){


            $sqlSelect = "SELECT
																	    a.vem_docnum documento,
																	    a.vem_docdate fecha,
																	    a.vem_cardcode codigo_cliente,
																	    a.vem_cardname nombre_cliente,
																	    c.em1_itemcode codigo_item,
																	    c.em1_itemname nombre_item,
																	    c.em1_quantity cantidad,
																	    sum(e.dv1_quantity) dev,
																	    --sum(g.fv1_quantity) fact,
																	    (select sum(aa.fv1_quantity) from vfv1 aa left join dvfv bb on aa.fv1_docentry = bb.dvf_docentry
																	    where bb.dvf_basetype = a.vem_doctype and bb.dvf_baseentry = a.vem_docentry) fact,
																	    (c.em1_quantity - (sum(e.dv1_quantity)
																	    + (select sum(aa.fv1_quantity) from vfv1 aa left join dvfv bb on aa.fv1_docentry = bb.dvf_docentry
																	    where bb.dvf_basetype = a.vem_doctype and bb.dvf_baseentry = a.vem_docentry)
																	    /*+ sum(g.fv1_quantity*/)) pendiente
																	from dvem a
																	join responsestatus b on a.vem_docentry = b.id and a.vem_doctype = b.tipo
																	left join vem1 c on a.vem_docentry = c.em1_docentry
																	left join dvdv d on a.vem_docentry = d.vdv_baseentry and a.vem_doctype = d.vdv_basetype
																	left join vdv1 e on d.vdv_docentry = e.dv1_docentry and c.em1_itemcode = e.dv1_itemcode
																	--left join dvfv f on a.vem_docentry = f.dvf_baseentry and a.vem_doctype = f.dvf_basetype
																	--left join vfv1 g on f.dvf_docentry = g.fv1_docentry and c.em1_itemcode = g.fv1_itemcode
																	where b.estado = 'Abierto'
																	group by a.vem_docnum,
																	    a.vem_docdate,
																	    a.vem_cardcode,
																	    a.vem_cardname,
																	    c.em1_itemcode,
																	    c.em1_itemname,
																	    c.em1_quantity,
																	    a.vem_doctype,
																	    a.vem_docentry";


            $resSelect = $this->pedeo->queryTable($sqlSelect, array());

            if(isset($resSelect[0])){

              $respuesta = array(
                'error' => false,
                'data'  => $resSelect,
                'mensaje' => 'OK');

            }else{

                $respuesta = array(
                  'error'   => true,
                  'data' => array(),
                  'mensaje'	=> 'busqueda sin resultados'
                );

            }

             $this->response($respuesta);
      }


			public function OpenDelivery_post(){


								$sqlSelect = "SELECT
																			    a.vov_docnum documento,
																			    a.vov_docdate fecha,
																			    a.vov_cardcode codigo_cliente,
																			    a.vov_cardname nombre_cliente,
																			    c.ov1_itemcode codigo_item,
																			    c.ov1_itemname nombre_item,
																			    c.ov1_quantity cantidad,
																			    coalesce(sum(e.em1_quantity),0) ent,
																			    coalesce((select sum(aa.fv1_quantity) from vfv1 aa left join dvfv bb on aa.fv1_docentry = bb.dvf_docentry
																			    where bb.dvf_basetype = a.vov_doctype and bb.dvf_baseentry = a.vov_docentry),0) fact,
																			    coalesce((c.ov1_quantity - (sum(e.em1_quantity)
																			    + coalesce((select sum(aa.fv1_quantity) from vfv1 aa left join dvfv bb on aa.fv1_docentry = bb.dvf_docentry
																			    where bb.dvf_basetype = a.vov_doctype and bb.dvf_baseentry = a.vov_docentry),0))),0) pendiente
																			from dvov a
																			join responsestatus b on a.vov_docentry = b.id and a.vov_doctype = b.tipo
																			left join vov1 c on a.vov_docentry = c.ov1_docentry
																			left join dvem d on a.vov_docentry = d.vem_baseentry and a.vov_doctype = d.vem_basetype
																			left join vem1 e on d.vem_docentry = e.em1_docentry and c.ov1_itemcode = e.em1_itemcode
																			where b.estado = 'Abierto'
																			group by a.vov_docnum,
																			    a.vov_docdate,
																			    a.vov_cardcode,
																			    a.vov_cardname,
																			    c.ov1_itemcode,
																			    c.ov1_itemname,
																			    c.ov1_quantity,
																			    a.vov_doctype,
																			    a.vov_docentry";


								$resSelect = $this->pedeo->queryTable($sqlSelect, array());

								if(isset($resSelect[0])){

									$respuesta = array(
										'error' => false,
										'data'  => $resSelect,
										'mensaje' => 'OK');

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
