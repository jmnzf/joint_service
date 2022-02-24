<?php
// Entrega de Ventas
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class OpenDocumentsPurchase extends REST_Controller {

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
																	    a.cpo_docnum documento,
																	    a.cpo_docdate fecha,
																	    a.cpo_cardcode codigo_cliente,
																	    a.cpo_cardname nombre_cliente,
																	    c.po1_itemcode codigo_item,
																	    c.po1_itemname nombre_item,
																	    c.po1_quantity cantidad,
																	    coalesce(sum(e.ec1_quantity),0) ent,
																	    coalesce((select sum(aa.fc1_quantity) from cfc1 aa left join dcfc bb on aa.fc1_docentry = bb.cfc_docentry
																	    where bb.cfc_basetype = a.cpo_doctype and bb.cfc_baseentry = a.cpo_docentry),0) fact,
																	    coalesce((c.po1_quantity - (sum(e.ec1_quantity)
																	    + coalesce((select sum(aa.fc1_quantity) from cfc1 aa left join dcfc bb on aa.fc1_docentry = bb.cfc_docentry
																	    where bb.cfc_basetype = a.cpo_doctype and bb.cfc_baseentry = a.cpo_docentry),0))),0) pendiente
																	from dcpo a
																	join responsestatus b on a.cpo_docentry = b.id and a.cpo_doctype = b.tipo
																	left join cpo1 c on a.cpo_docentry = c.po1_docentry
																	left join dcec d on a.cpo_docentry = d.cec_baseentry and a.cpo_doctype = d.cec_basetype
																	left join cec1 e on d.cec_docentry = e.ec1_docentry and c.po1_itemcode = e.ec1_itemcode
																	where b.estado = 'Abierto'
																	group by a.cpo_docnum,
																	    a.cpo_docdate,
																	    a.cpo_cardcode,
																	    a.cpo_cardname,
																	    c.po1_itemcode,
																	    c.po1_itemname,
																	    c.po1_quantity,
																	    a.cpo_doctype,
																	    a.cpo_docentry";


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

			public function OpenEntry_post(){


								$sqlSelect = "SELECT
																		    a.cec_docnum documento,
																		    a.cec_docdate fecha,
																		    a.cec_cardcode codigo_cliente,
																		    a.cec_cardname nombre_cliente,
																		    c.ec1_itemcode codigo_item,
																		    c.ec1_itemname nombre_item,
																		    c.ec1_quantity cantidad,
																		    coalesce(sum(e.dc1_quantity),0) ent,
																		    coalesce((select sum(aa.fc1_quantity) from cfc1 aa left join dcfc bb on aa.fc1_docentry = bb.cfc_docentry
																		    where bb.cfc_basetype = a.cec_doctype and bb.cfc_baseentry = a.cec_docentry),0) fact,
																		    coalesce((c.ec1_quantity - (coalesce(sum(e.dc1_quantity),0)
																		    + coalesce((select sum(aa.fc1_quantity) from cfc1 aa left join dcfc bb on aa.fc1_docentry = bb.cfc_docentry
																		    where bb.cfc_basetype = a.cec_doctype and bb.cfc_baseentry = a.cec_docentry),0))),0) pendiente
																		from dcec a
																		join responsestatus b on a.cec_docentry = b.id and a.cec_doctype = b.tipo
																		left join cec1 c on a.cec_docentry = c.ec1_docentry
																		left join dcdc d on a.cec_docentry = d.cdc_baseentry and a.cec_doctype = d.cdc_basetype
																		left join cdc1 e on d.cdc_docentry = e.dc1_docentry and c.ec1_itemcode = e.dc1_itemcode
																		where b.estado = 'Abierto'
																		group by a.cec_docnum,
																		    a.cec_docdate,
																		    a.cec_cardcode,
																		    a.cec_cardname,
																		    c.ec1_itemcode,
																		    c.ec1_itemname,
																		    c.ec1_quantity,
																		    a.cec_doctype,
																		    a.cec_docentry";


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
