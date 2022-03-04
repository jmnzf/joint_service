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
	public function OpenDelivery_post(){


            $sqlSelect = "SELECT DISTINCT
																	    a.vem_docnum documento,
																	    a.vem_docdate fecha,
																	    a.vem_cardcode SN,
																	    a.vem_cardname N_SN,
																	    c.em1_itemcode codigo_item,
																	    c.em1_itemname nombre_item,
																	    c.em1_quantity cantidad,
																	    coalesce(sum(e.dv1_quantity),0) dev,
																	   coalesce((select sum(aa.fv1_quantity) from vfv1 aa left join dvfv bb on aa.fv1_docentry = bb.dvf_docentry
																	    where bb.dvf_basetype = a.vem_doctype and bb.dvf_baseentry = a.vem_docentry and aa.fv1_itemcode = c.em1_itemcode),0) fact,
																	    coalesce((c.em1_quantity -coalesce(sum(e.dv1_quantity),0)
																	    - coalesce((select sum(aa.fv1_quantity) from vfv1 aa left join dvfv bb on aa.fv1_docentry = bb.dvf_docentry
																	    where bb.dvf_basetype = a.vem_doctype and bb.dvf_baseentry = a.vem_docentry and aa.fv1_itemcode = c.em1_itemcode),0)),0) pendiente
																	from dvem a
																	join responsestatus b on a.vem_docentry = b.id and a.vem_doctype = b.tipo
																	left join vem1 c on a.vem_docentry = c.em1_docentry
																	left join dvdv d on a.vem_docentry = d.vdv_baseentry and a.vem_doctype = d.vdv_basetype
																	left join vdv1 e on d.vdv_docentry = e.dv1_docentry and c.em1_itemcode = e.dv1_itemcode
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


			public function OpenOrder_post(){


								$sqlSelect = "SELECT
																			    a.vov_docnum documento,
																			    a.vov_docdate fecha,
																			    a.vov_cardcode SN,
																			    a.vov_cardname N_SN,
																			    c.ov1_itemcode codigo_item,
																			    c.ov1_itemname nombre_item,
																			    c.ov1_quantity cantidad,
																			    coalesce(sum(e.em1_quantity),0) ent,
																			    coalesce((select sum(aa.fv1_quantity) from vfv1 aa left join dvfv bb on aa.fv1_docentry = bb.dvf_docentry
																			    where bb.dvf_basetype = a.vov_doctype and bb.dvf_baseentry = a.vov_docentry and aa.fv1_itemcode = c.ov1_itemcode),0) fact,
																			    coalesce((c.ov1_quantity - coalesce(sum(e.em1_quantity),0)
																			    - coalesce((select sum(aa.fv1_quantity) from vfv1 aa left join dvfv bb on aa.fv1_docentry = bb.dvf_docentry
																			    where bb.dvf_basetype = a.vov_doctype and bb.dvf_baseentry = a.vov_docentry and aa.fv1_itemcode = c.ov1_itemcode),0)),0) pendiente
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


					public function OpenQuotation_post(){


										$sqlSelect = "SELECT
																					    a.dvc_docnum documento,
																					    a.dvc_docdate fecha,
																					    a.dvc_cardcode SN,
																					    a.dvc_cardname N_SN,
																					    c.vc1_itemcode codigo_item,
																					    c.vc1_itemname nombre_item,
																					    c.vc1_quantity cantidad,

																					     coalesce((select sum(aa.ov1_quantity) from vov1 aa left join dvov bb on aa.ov1_docentry = bb.vov_docentry
																					    where bb.vov_basetype = a.dvc_doctype and bb.vov_baseentry = a.dvc_docentry and aa.ov1_itemcode = c.vc1_itemcode),0) pedido,

																					    coalesce((select sum(aa.em1_quantity) from vem1 aa left join dvem bb on aa.em1_docentry = bb.vem_docentry
																					    where bb.vem_basetype = a.dvc_doctype and bb.vem_baseentry = a.dvc_docentry and aa.em1_itemcode = c.vc1_itemcode),0) entrega,


																					   coalesce((select sum(aa.fv1_quantity) from vfv1 aa left join dvfv bb on aa.fv1_docentry = bb.dvf_docentry
																					    where bb.dvf_basetype = a.dvc_doctype and bb.dvf_baseentry = a.dvc_docentry and aa.fv1_itemcode = c.vc1_itemcode),0) fact,

																					    coalesce((c.vc1_quantity) - (
																					        (coalesce((select sum(aa.ov1_quantity) from vov1 aa left join dvov bb on aa.ov1_docentry = bb.vov_docentry
																					    where bb.vov_basetype = a.dvc_doctype and bb.vov_baseentry = a.dvc_docentry and aa.ov1_itemcode = c.vc1_itemcode),0))
																					        - (coalesce((select sum(aa.em1_quantity) from vem1 aa left join dvem bb on aa.em1_docentry = bb.vem_docentry
																					    where bb.vem_basetype = a.dvc_doctype and bb.vem_baseentry = a.dvc_docentry and aa.em1_itemcode = c.vc1_itemcode),0))
																					        - (coalesce((select sum(aa.fv1_quantity) from vfv1 aa left join dvfv bb on aa.fv1_docentry = bb.dvf_docentry
																					    where bb.dvf_basetype = a.dvc_doctype and bb.dvf_baseentry = a.dvc_docentry and aa.fv1_itemcode = c.vc1_itemcode),0))
																					        ),0) pendiente

																					from dvct a
																					join responsestatus b on a.dvc_docentry = b.id and a.dvc_doctype = b.tipo
																					left join vct1 c on a.dvc_docentry = c.vc1_docentry
																					where b.estado = 'Abierto'
																					group by a.dvc_docnum,
																					    a.dvc_docdate,
																					    a.dvc_cardcode,
																					    a.dvc_cardname,
																					    c.vc1_itemcode,
																					    c.vc1_itemname,
																					    c.vc1_quantity,
																					    a.dvc_doctype,
																					    a.dvc_docentry";


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
																							    a.dvf_docnum documento,
																							    a.dvf_docdate fecha,
																							    a.dvf_cardcode SN,
																							    a.dvf_cardname N_SN,
																							    a.dvf_doctotal total,
																							     coalesce((select distinct sum(aa.vnc_doctotal) from  dvnc aa
																							    where aa.vnc_basetype = a.dvf_doctype and aa.vnc_baseentry = a.dvf_docentry),0) nc,

																							    coalesce((select distinct sum(aa.vnd_doctotal) from  dvnd aa
																							    where aa.vnd_basetype = a.dvf_doctype and aa.vnd_baseentry = a.dvf_docentry),0) nd,

																							    coalesce((select distinct sum(aa.pr1_vlrpaid) from bpr1 aa
																							    where aa.pr1_doctype = a.dvf_doctype and aa.pr1_docentry = a.dvf_docentry),0) pago,

																							    coalesce(((a.dvf_doctotal + coalesce((select distinct sum(aa.vnd_doctotal) from  dvnd aa
																							    where aa.vnd_basetype = a.dvf_doctype and aa.vnd_baseentry = a.dvf_docentry),0)) - ((coalesce((select  distinct sum(aa.vnc_doctotal) from  dvnc aa
																							    where aa.vnc_basetype = a.dvf_doctype and aa.vnc_baseentry = a.dvf_docentry),0) +
																							    coalesce((select distinct sum(aa.pr1_vlrpaid) from bpr1 aa
																							    where aa.pr1_doctype = a.dvf_doctype and aa.pr1_docentry = a.dvf_docentry),0)))),0) pendiente


																							from dvfv a
																							join responsestatus b on a.dvf_docentry = b.id and a.dvf_doctype = b.tipo
																							left join vfv1 c on a.dvf_docentry = c.fv1_docentry
																							where b.estado = 'Abierto'

																							group by
																							    a.dvf_docnum ,
																							    a.dvf_docdate ,
																							    a.dvf_cardcode ,
																							    a.dvf_cardname ,
																							    a.dvf_doctotal,
																							    a.dvf_doctype,
																							    a.dvf_docentry";


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
