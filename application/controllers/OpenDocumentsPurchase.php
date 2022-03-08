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
	public function OpenRequest_post(){


            $sqlSelect = "SELECT
																	    a.csc_docnum documento,
																	    a.csc_docdate fecha,
																	    a.csc_cardcode SN,
																	    a.csc_cardname N_SN,
																	    c.sc1_itemcode codigo_item,
																	    c.sc1_itemname nombre_item,
																	    c.sc1_quantity cantidad,

																	     coalesce((select sum(aa.oc1_quantity) from coc1 aa left join dcoc bb on aa.oc1_docentry = bb.coc_docentry
																	    where bb.coc_basetype = a.csc_doctype and bb.coc_baseentry = a.csc_docentry and aa.oc1_itemcode = c.sc1_itemcode),0) oferta,

																	    coalesce((select sum(aa.po1_quantity) from cpo1 aa left join dcpo bb on aa.po1_docentry = bb.cpo_docentry
																	    where bb.cpo_basetype = a.csc_doctype and bb.cpo_baseentry = a.csc_docentry and aa.po1_itemcode = c.sc1_itemcode),0) pedido,


																	   coalesce((c.sc1_quantity) - ((
																	        coalesce((select sum(aa.oc1_quantity) from coc1 aa left join dcoc bb on aa.oc1_docentry = bb.coc_docentry
																	    where bb.coc_basetype = a.csc_doctype and bb.coc_baseentry = a.csc_docentry and aa.oc1_itemcode = c.sc1_itemcode),0)
																	          ) - (coalesce((select sum(aa.po1_quantity) from cpo1 aa left join dcpo bb on aa.po1_docentry = bb.cpo_docentry
																	    where bb.cpo_basetype = a.csc_doctype and bb.cpo_baseentry = a.csc_docentry and aa.po1_itemcode = c.sc1_itemcode),0))),0) cantidad_pendiente

																	from dcsc a
																	join responsestatus b on a.csc_docentry = b.id and a.csc_doctype = b.tipo
																	left join csc1 c on a.csc_docentry = c.sc1_docentry
																	where b.estado = 'Abierto'
																	group by a.csc_docnum,
																	    a.csc_docdate,
																	    a.csc_cardcode,
																	    a.csc_cardname,
																	    c.sc1_itemcode,
																	    c.sc1_itemname,
																	    c.sc1_quantity,
																	    a.csc_doctype,
																	    a.csc_docentry";


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



	public function OpenOrder_post(){


            $sqlSelect = "SELECT
																	    a.cpo_docnum documento,
																	    a.cpo_docdate fecha,
																	    a.cpo_cardcode SN,
																	    a.cpo_cardname N_SN,
																	    c.po1_itemcode codigo_item,
																	    c.po1_itemname nombre_item,
																	    c.po1_quantity cantidad,
																	    coalesce(sum(e.ec1_quantity),0) ent,
																	    coalesce((select sum(aa.fc1_quantity) from cfc1 aa left join dcfc bb on aa.fc1_docentry = bb.cfc_docentry
																	    where bb.cfc_basetype = a.cpo_doctype and bb.cfc_baseentry = a.cpo_docentry),0) fact,
																	    coalesce((c.po1_quantity - (sum(e.ec1_quantity)
																	    + coalesce((select sum(aa.fc1_quantity) from cfc1 aa left join dcfc bb on aa.fc1_docentry = bb.cfc_docentry
																	    where bb.cfc_basetype = a.cpo_doctype and bb.cfc_baseentry = a.cpo_docentry),0))),0) cantidad_pendiente
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
																		    a.cec_cardcode SN,
																		    a.cec_cardname N_SN,
																		    c.ec1_itemcode codigo_item,
																		    c.ec1_itemname nombre_item,
																		    c.ec1_quantity cantidad,
																		    coalesce(sum(e.dc1_quantity),0) ent,
																		    coalesce((select sum(aa.fc1_quantity) from cfc1 aa left join dcfc bb on aa.fc1_docentry = bb.cfc_docentry
																		    where bb.cfc_basetype = a.cec_doctype and bb.cfc_baseentry = a.cec_docentry),0) fact,
																		    coalesce((c.ec1_quantity - (coalesce(sum(e.dc1_quantity),0)
																		    + coalesce((select sum(aa.fc1_quantity) from cfc1 aa left join dcfc bb on aa.fc1_docentry = bb.cfc_docentry
																		    where bb.cfc_basetype = a.cec_doctype and bb.cfc_baseentry = a.cec_docentry),0))),0) cantidad_pendiente
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

					public function OpenOfert_post(){


										$sqlSelect = "SELECT
																						    a.coc_docnum documento,
																						    a.coc_docdate fecha,
																						    a.coc_cardcode SN,
																						    a.coc_cardname N_SN,
																						    c.oc1_itemcode codigo_item,
																						    c.oc1_itemname nombre_item,
																						    c.oc1_quantity cantidad,

																						    coalesce((select sum(aa.po1_quantity) from cpo1 aa left join dcpo bb on aa.po1_docentry = bb.cpo_docentry
																						    where bb.cpo_basetype = a.coc_doctype and bb.cpo_baseentry = a.coc_docentry and aa.po1_itemcode = c.oc1_itemcode),0) pedido,


																						   coalesce((c.oc1_quantity) - ((coalesce((select sum(aa.po1_quantity) from cpo1 aa left join dcpo bb on aa.po1_docentry = bb.cpo_docentry
																						    where bb.cpo_basetype = a.coc_doctype and bb.cpo_baseentry = a.coc_docentry and aa.po1_itemcode = c.oc1_itemcode),0))),0) cantidad_pendiente

																						from dcoc a
																						join responsestatus b on a.coc_docentry = b.id and a.coc_doctype = b.tipo
																						left join coc1 c on a.coc_docentry = c.oc1_docentry
																						where b.estado = 'Abierto'
																						group by a.coc_docnum,
																						    a.coc_docdate,
																						    a.coc_cardcode,
																						    a.coc_cardname,
																						    c.oc1_itemcode,
																						    c.oc1_itemname,
																						    c.oc1_quantity,
																						    a.coc_doctype,
																						    a.coc_docentry";


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

							public function OpenInvoices_post(){


												$sqlSelect = "SELECT
																							    a.cfc_docnum documento,
																							    a.cfc_docdate fecha,
																							    a.cfc_cardcode SN,
																							    a.cfc_cardname N_SN,
																							    a.cfc_doctotal total,
																							     coalesce((select distinct sum(aa.cnc_doctotal) from  dcnc aa
																							    where aa.cnc_basetype = a.cfc_doctype and aa.cnc_baseentry = a.cfc_docentry),0) nc,

																							    coalesce((select distinct sum(aa.cnd_doctotal) from  dcnd aa
																							    where aa.cnd_basetype = a.cfc_doctype and aa.cnd_baseentry = a.cfc_docentry),0) nd,

																							    coalesce((select distinct sum(aa.pe1_vlrpaid) from bpe1 aa
																							    where aa.pe1_doctype = a.cfc_doctype and aa.pe1_docentry = a.cfc_docentry),0) pago,

																							    coalesce(((a.cfc_doctotal + coalesce((select distinct sum(aa.cnd_doctotal) from  dcnd aa
																							    where aa.cnd_basetype = a.cfc_doctype and aa.cnd_baseentry = a.cfc_docentry),0)) - ((coalesce((select  distinct sum(aa.cnc_doctotal) from  dcnc aa
																							    where aa.cnc_basetype = a.cfc_doctype and aa.cnc_baseentry = a.cfc_docentry),0) +
																							    coalesce((select distinct sum(aa.pe1_vlrpaid) from bpe1 aa
																							    where aa.pe1_doctype = a.cfc_doctype and aa.pe1_docentry = a.cfc_docentry),0)))),0) saldo_pendiente


																							from dcfc a
																							join responsestatus b on a.cfc_docentry = b.id and a.cfc_doctype = b.tipo
																							left join cfc1 c on a.cfc_docentry = c.fc1_docentry
																							where b.estado = 'Abierto'

																							group by
																							    a.cfc_docnum ,
																							    a.cfc_docdate ,
																							    a.cfc_cardcode ,
																							    a.cfc_cardname ,
																							    a.cfc_doctotal,
																							    a.cfc_doctype,
																							    a.cfc_docentry";


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
