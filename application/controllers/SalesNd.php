<?php
// Nota debito de clientesES
defined('BASEPATH') OR exit('No direct script access allowed');


require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class SalesNd extends REST_Controller {

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

  //CREAR NUEVA Nota debito de clientes
	public function createSalesNd_post(){

				try {
					      $Data = $this->post();

								$DetalleAsientoIngreso = new stdClass(); // Cada objeto de las linea del detalle consolidado
								$DetalleAsientoIva = new stdClass();
								$DetalleCostoInventario = new stdClass();
								$DetalleCostoCosto = new stdClass();
								$DetalleConsolidadoIngreso = []; // Array Final con los datos del asiento solo ingreso
								$DetalleConsolidadoCostoInventario = [];
								$DetalleConsolidadoCostoCosto = [];
								$DetalleConsolidadoIva = []; // Array Final con los datos del asiento segun el iva
								$inArrayIngreso = array(); // Array para mantener el indice de las llaves para ingreso
								$inArrayIva = array(); // Array para mantener el indice de las llaves para iva
								$inArrayCostoInventario = array();
								$inArrayCostoCosto = array();
							  $llave = ""; // la comnbinacion entre la cuenta contable,proyecto, unidad de negocio y centro de costo
								$llaveIva = ""; //segun tipo de iva
								$llaveCostoInventario = "";
								$llaveCostoCosto = "";
								$posicion = 0;// contiene la posicion con que se creara en el array DetalleConsolidado
								$posicionIva = 0;
								$posicionCostoInventario = 0;
								$posicionCostoCosto = 0;
								$codigoCuenta = ""; //para saber la naturaleza
								$grantotalCostoInventario = 0;
								$DocNumVerificado = 0;
								$TasaFija = 0;
								$TotalDiferenciaSYS = 0;
								$TotalDiferenciaLOC = 0;
							  $SumaCreditosSYS = 0;
								$SumaDebitosSYS = 0;
								$SumaCreditosLOC = 0;
								$SumaDebitoLOC = 0;
								$ManejaInvetario = 0;
								$IVASINTASAFIJA = 0;
								$AC1LINE = 1;
								$SUMALINEAFIXRATE = 0;
								$TOTALCXCLOC = 0;
								$TOTALCXCSYS = 0;
								$TOTALCXCLOCIVA = 0;
								$TOTALCXCSYSIVA = 0;
								// Se globaliza la variable sqlDetalleAsiento
								$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
																		ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
																		ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
																		ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, ac1_line)VALUES (:ac1_trans_id, :ac1_account,
																		:ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
																		:ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
																		:ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
																		:ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref, :ac1_line)";



					      if(!isset($Data['detail'])){

					        $respuesta = array(
					          'error' => true,
					          'data'  => array(),
					          'mensaje' =>'La informacion enviada no es valida'
					        );

					        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					        return;
					      }

					      $ContenidoDetalle = json_decode($Data['detail'], true);


					      if(!is_array($ContenidoDetalle)){
					          $respuesta = array(
					            'error' => true,
					            'data'  => array(),
					            'mensaje' =>'No se encontro el detalle de la Nota debito de clientes'
					          );

					          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					          return;
					      }

								// SE VALIDA QUE EL DOCUMENTO TENGA CONTENIDO
								if(!intval(count($ContenidoDetalle)) > 0 ){
										$respuesta = array(
											'error' => true,
											'data'  => array(),
											'mensaje' =>'Documento sin detalle'
										);

										$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

										return;
								}
								//
									//BUSCANDO LA NUMERACION DEL DOCUMENTO
								  $sqlNumeracion = " SELECT pgs_nextnum,pgs_last_num FROM  pgdn WHERE pgs_id = :pgs_id";

									$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['vnd_series']));

									if(isset($resNumeracion[0])){

											$numeroActual = $resNumeracion[0]['pgs_nextnum'];
											$numeroFinal  = $resNumeracion[0]['pgs_last_num'];
											$numeroSiguiente = ($numeroActual + 1);

											if( $numeroSiguiente <= $numeroFinal ){

													$DocNumVerificado = $numeroSiguiente;

											}	else {

													$respuesta = array(
														'error' => true,
														'data'  => array(),
														'mensaje' =>'La serie de la numeración esta llena'
													);

													$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

													return;
											}

									}else{

											$respuesta = array(
												'error' => true,
												'data'  => array(),
												'mensaje' =>'No se encontro la serie de numeración para el documento'
											);

											$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

											return;
									}


									// PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO
									// SE BUSCA LA MONEDA LOCAL PARAMETRIZADA
									$sqlMonedaLoc = "SELECT pgm_symbol FROM pgec WHERE pgm_principal = :pgm_principal";
									$resMonedaLoc = $this->pedeo->queryTable($sqlMonedaLoc, array(':pgm_principal' => 1));

									if(isset($resMonedaLoc[0])){

									}else{
											$respuesta = array(
												'error' => true,
												'data'  => array(),
												'mensaje' =>'No se encontro la moneda local.'
											);

											$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

											return;
									}

									$MONEDALOCAL = trim($resMonedaLoc[0]['pgm_symbol']);

									// SE BUSCA LA MONEDA DE SISTEMA PARAMETRIZADA
									$sqlMonedaSys = "SELECT pgm_symbol FROM pgec WHERE pgm_system = :pgm_system";
									$resMonedaSys = $this->pedeo->queryTable($sqlMonedaSys, array(':pgm_system' => 1));

									if(isset($resMonedaSys[0])){

									}else{

											$respuesta = array(
												'error' => true,
												'data'  => array(),
												'mensaje' =>'No se encontro la moneda de sistema.'
											);

											$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

											return;
									}


									$MONEDASYS = trim($resMonedaSys[0]['pgm_symbol']);

									//SE BUSCA LA TASA DE CAMBIO CON RESPECTO A LA MONEDA QUE TRAE EL DOCUMENTO A CREAR CON LA MONEDA LOCAL
									// Y EN LA MISMA FECHA QUE TRAE EL DOCUMENTO
									$sqlBusTasa = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
									$resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $Data['vnd_currency'], ':tsa_date' => $Data['vnd_docdate']));

									if(isset($resBusTasa[0])){

									}else{

											if(trim($Data['vnd_currency']) != $MONEDALOCAL ){

												$respuesta = array(
													'error' => true,
													'data'  => array(),
													'mensaje' =>'No se encrontro la tasa de cambio para la moneda: '.$Data['vnd_currency'].' en la actual fecha del documento: '.$Data['vnd_docdate'].' y la moneda local: '.$resMonedaLoc[0]['pgm_symbol']
												);

												$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

												return;
											}
									}


									$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
									$resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $Data['vnd_docdate']));

									if(isset($resBusTasa2[0])){

									}else{
											$respuesta = array(
												'error' => true,
												'data'  => array(),
												'mensaje' =>'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :'.$Data['vnd_docdate']
											);

											$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

											return;
									}

									$TasaDocLoc = isset($resBusTasa[0]['tsa_value']) ? $resBusTasa[0]['tsa_value'] : 1;
									$TasaLocSys = $resBusTasa2[0]['tsa_value'];

									// FIN DEL PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO


					        $sqlInsert = "INSERT INTO dvnd(vnd_series, vnd_docnum, vnd_docdate, vnd_duedate, vnd_duedev, vnd_pricelist, vnd_cardcode,
					                      vnd_cardname, vnd_currency, vnd_contacid, vnd_slpcode, vnd_empid, vnd_comment, vnd_doctotal, vnd_baseamnt, vnd_taxtotal,
					                      vnd_discprofit, vnd_discount, vnd_createat, vnd_baseentry, vnd_basetype, vnd_doctype, vnd_idadd, vnd_adress, vnd_paytype,
					                      vnd_attch,vnd_createby)VALUES(:vnd_series, :vnd_docnum, :vnd_docdate, :vnd_duedate, :vnd_duedev, :vnd_pricelist, :vnd_cardcode, :vnd_cardname,
					                      :vnd_currency, :vnd_contacid, :vnd_slpcode, :vnd_empid, :vnd_comment, :vnd_doctotal, :vnd_baseamnt, :vnd_taxtotal, :vnd_discprofit, :vnd_discount,
					                      :vnd_createat, :vnd_baseentry, :vnd_basetype, :vnd_doctype, :vnd_idadd, :vnd_adress, :vnd_paytype, :vnd_attch,:vnd_createby)";


									// Se Inicia la transaccion,
									// Todas las consultas de modificacion siguientes
									// aplicaran solo despues que se confirme la transaccion,
									// de lo contrario no se aplicaran los cambios y se devolvera
									// la base de datos a su estado original.



									$this->pedeo->trans_begin();




									$resInsert = $this->pedeo->insertRow($sqlInsert, array(
												':vnd_docnum' => $DocNumVerificado,
												':vnd_series' => is_numeric($Data['vnd_series'])?$Data['vnd_series']:0,
												':vnd_docdate' => $this->validateDate($Data['vnd_docdate'])?$Data['vnd_docdate']:NULL,
												':vnd_duedate' => $this->validateDate($Data['vnd_duedate'])?$Data['vnd_duedate']:NULL,
												':vnd_duedev' => $this->validateDate($Data['vnd_duedev'])?$Data['vnd_duedev']:NULL,
												':vnd_pricelist' => is_numeric($Data['vnd_pricelist'])?$Data['vnd_pricelist']:0,
												':vnd_cardcode' => isset($Data['vnd_cardcode'])?$Data['vnd_cardcode']:NULL,
												':vnd_cardname' => isset($Data['vnd_cardname'])?$Data['vnd_cardname']:NULL,
												':vnd_currency' => isset($Data['vnd_currency'])?$Data['vnd_currency']:NULL,
												':vnd_contacid' => isset($Data['vnd_contacid'])?$Data['vnd_contacid']:NULL,
												':vnd_slpcode' => is_numeric($Data['vnd_slpcode'])?$Data['vnd_slpcode']:0,
												':vnd_empid' => is_numeric($Data['vnd_empid'])?$Data['vnd_empid']:0,
												':vnd_comment' => isset($Data['vnd_comment'])?$Data['vnd_comment']:NULL,
												':vnd_doctotal' => is_numeric($Data['vnd_doctotal'])?$Data['vnd_doctotal']:0,
												':vnd_baseamnt' => is_numeric($Data['vnd_baseamnt'])?$Data['vnd_baseamnt']:0,
												':vnd_taxtotal' => is_numeric($Data['vnd_taxtotal'])?$Data['vnd_taxtotal']:0,
												':vnd_discprofit' => is_numeric($Data['vnd_discprofit'])?$Data['vnd_discprofit']:0,
												':vnd_discount' => is_numeric($Data['vnd_discount'])?$Data['vnd_discount']:0,
												':vnd_createat' => $this->validateDate($Data['vnd_createat'])?$Data['vnd_createat']:NULL,
												':vnd_baseentry' => is_numeric($Data['vnd_baseentry'])?$Data['vnd_baseentry']:0,
												':vnd_basetype' => is_numeric($Data['vnd_basetype'])?$Data['vnd_basetype']:0,
												':vnd_doctype' => is_numeric($Data['vnd_doctype'])?$Data['vnd_doctype']:0,
												':vnd_idadd' => isset($Data['vnd_idadd'])?$Data['vnd_idadd']:NULL,
												':vnd_adress' => isset($Data['vnd_adress'])?$Data['vnd_adress']:NULL,
												':vnd_paytype' => is_numeric($Data['vnd_paytype'])?$Data['vnd_paytype']:0,
												':vnd_createby' => isset($Data['vnd_createby'])?$Data['vnd_createby']:NULL,
												':vnd_attch' => $this->getUrl(count(trim(($Data['vnd_attch']))) > 0 ? $Data['vnd_attch']:NULL)
											));

									if(is_numeric($resInsert) && $resInsert > 0){

										// Se actualiza la serie de la numeracion del documento

										$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																								 WHERE pgs_id = :pgs_id";
										$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
												':pgs_nextnum' => $DocNumVerificado,
												':pgs_id'      => $Data['vnd_series']
										));


										if(is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1){

										}else{
													$this->pedeo->trans_rollback();

													$respuesta = array(
														'error'   => true,
														'data'    => $resActualizarNumeracion,
														'mensaje'	=> 'No se pudo crear la Nota debito de clientes'
													);

													$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

													return;
										}
										// Fin de la actualizacion de la numeracion del documento



										//SE INSERTA EL ESTADO DEL DOCUMENTO

										$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																				VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

										$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


															':bed_docentry' => $resInsert,
															':bed_doctype' => $Data['vnd_doctype'],
															':bed_status' => 1, //ESTADO CERRADO
															':bed_createby' => $Data['vnd_createby'],
															':bed_date' => date('Y-m-d'),
															':bed_baseentry' => NULL,
															':bed_basetype' =>NULL
										));


										if(is_numeric($resInsertEstado) && $resInsertEstado > 0){

										}else{

												 $this->pedeo->trans_rollback();

													$respuesta = array(
														'error'   => true,
														'data' => $resInsertEstado,
														'mensaje'	=> 'No se pudo registrar la nota debito de ventas'
													);


													$this->response($respuesta);

													return;
										}

										//FIN PROCESO ESTADO DEL DOCUMENTO


										//Se agregan los asientos contables*/*******

										$sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user)
																				 VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user)";


										$resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

												':mac_doc_num' => 1,
												':mac_status' => 1,
												':mac_base_type' => is_numeric($Data['vnd_doctype'])?$Data['vnd_doctype']:0,
												':mac_base_entry' => $resInsert,
												':mac_doc_date' => $this->validateDate($Data['vnd_docdate'])?$Data['vnd_docdate']:NULL,
												':mac_doc_duedate' => $this->validateDate($Data['vnd_duedate'])?$Data['vnd_duedate']:NULL,
												':mac_legal_date' => $this->validateDate($Data['vnd_docdate'])?$Data['vnd_docdate']:NULL,
												':mac_ref1' => is_numeric($Data['vnd_doctype'])?$Data['vnd_doctype']:0,
												':mac_ref2' => "",
												':mac_ref3' => "",
												':mac_loc_total' => is_numeric($Data['vnd_doctotal'])?$Data['vnd_doctotal']:0,
												':mac_fc_total' => is_numeric($Data['vnd_doctotal'])?$Data['vnd_doctotal']:0,
												':mac_sys_total' => is_numeric($Data['vnd_doctotal'])?$Data['vnd_doctotal']:0,
												':mac_trans_dode' => 1,
												':mac_beline_nume' => 1,
												':mac_vat_date' => $this->validateDate($Data['vnd_docdate'])?$Data['vnd_docdate']:NULL,
												':mac_serie' => 1,
												':mac_number' => 1,
												':mac_bammntsys' => is_numeric($Data['vnd_baseamnt'])?$Data['vnd_baseamnt']:0,
												':mac_bammnt' => is_numeric($Data['vnd_baseamnt'])?$Data['vnd_baseamnt']:0,
												':mac_wtsum' => 1,
												':mac_vatsum' => is_numeric($Data['vnd_taxtotal'])?$Data['vnd_taxtotal']:0,
												':mac_comments' => isset($Data['vnd_comment'])?$Data['vnd_comment']:NULL,
												':mac_create_date' => $this->validateDate($Data['vnd_createat'])?$Data['vnd_createat']:NULL,
												':mac_made_usuer' => isset($Data['vnd_createby'])?$Data['vnd_createby']:NULL,
												':mac_update_date' => date("Y-m-d"),
												':mac_update_user' => isset($Data['vnd_createby'])?$Data['vnd_createby']:NULL
										));


										if(is_numeric($resInsertAsiento) && $resInsertAsiento > 0){
												// Se verifica que el detalle no de error insertando //
										}else{

												// si falla algun insert del detalle de la Nota debito de clientes se devuelven los cambios realizados por la transaccion,
												// se retorna el error y se detiene la ejecucion del codigo restante.
													$this->pedeo->trans_rollback();

													$respuesta = array(
														'error'   => true,
														'data'	  => $resInsertAsiento,
														'mensaje'	=> 'No se pudo registrar la Nota debito de clientes'
													);

													 $this->response($respuesta);

													 return;
										}


										foreach ($ContenidoDetalle as $key => $detail) {

													$sqlInsertDetail = "INSERT INTO vnd1(nd1_docentry, nd1_itemcode, nd1_itemname, nd1_quantity, nd1_uom, nd1_whscode,
																							nd1_price, nd1_vat, nd1_vatsum, nd1_discount, nd1_linetotal, nd1_costcode, nd1_ubusiness, nd1_project,
																							nd1_acctcode, nd1_basetype, nd1_doctype, nd1_avprice, nd1_inventory)VALUES(:nd1_docentry, :nd1_itemcode, :nd1_itemname, :nd1_quantity,
																							:nd1_uom, :nd1_whscode,:nd1_price, :nd1_vat, :nd1_vatsum, :nd1_discount, :nd1_linetotal, :nd1_costcode, :nd1_ubusiness, :nd1_project,
																							:nd1_acctcode, :nd1_basetype, :nd1_doctype, :nd1_avprice, :nd1_inventory)";

													$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
																	':nd1_docentry' => $resInsert,
																	':nd1_itemcode' => isset($detail['nd1_itemcode'])?$detail['nd1_itemcode']:NULL,
																	':nd1_itemname' => isset($detail['nd1_itemname'])?$detail['nd1_itemname']:NULL,
																	':nd1_quantity' => is_numeric($detail['nd1_quantity'])?$detail['nd1_quantity']:0,
																	':nd1_uom' => isset($detail['nd1_uom'])?$detail['nd1_uom']:NULL,
																	':nd1_whscode' => isset($detail['nd1_whscode'])?$detail['nd1_whscode']:NULL,
																	':nd1_price' => is_numeric($detail['nd1_price'])?$detail['nd1_price']:0,
																	':nd1_vat' => is_numeric($detail['nd1_vat'])?$detail['nd1_vat']:0,
																	':nd1_vatsum' => is_numeric($detail['nd1_vatsum'])?$detail['nd1_vatsum']:0,
																	':nd1_discount' => is_numeric($detail['nd1_discount'])?$detail['nd1_discount']:0,
																	':nd1_linetotal' => is_numeric($detail['nd1_linetotal'])?$detail['nd1_linetotal']:0,
																	':nd1_costcode' => isset($detail['nd1_costcode'])?$detail['nd1_costcode']:NULL,
																	':nd1_ubusiness' => isset($detail['nd1_ubusiness'])?$detail['nd1_ubusiness']:NULL,
																	':nd1_project' => isset($detail['nd1_project'])?$detail['nd1_project']:NULL,
																	':nd1_acctcode' => is_numeric($detail['nd1_acctcode'])?$detail['nd1_acctcode']:0,
																	':nd1_basetype' => is_numeric($detail['nd1_basetype'])?$detail['nd1_basetype']:0,
																	':nd1_doctype' => is_numeric($detail['nd1_doctype'])?$detail['nd1_doctype']:0,
																	':nd1_avprice' => is_numeric($detail['nd1_avprice'])?$detail['nd1_avprice']:0,
																	':nd1_inventory' => is_numeric($detail['nd1_inventory'])?$detail['nd1_inventory']:NULL
													));

													if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
															// Se verifica que el detalle no de error insertando //
													}else{

															// si falla algun insert del detalle de la Nota debito de clientes se devuelven los cambios realizados por la transaccion,
															// se retorna el error y se detiene la ejecucion del codigo restante.
																$this->pedeo->trans_rollback();

																$respuesta = array(
																	'error'   => true,
																	'data' => $resInsert,
																	'mensaje'	=> 'No se pudo registrar la Nota debito de clientes'
																);

																 $this->response($respuesta);

																 return;
													}

													// SE VERIFICA SI EL ARTICULO ESTA MARCADO PARA MANEJARSE EN INVENTARIO
													$sqlItemINV = "SELECT dma_item_inv FROM dmar WHERE dma_item_code = :dma_item_code AND dma_item_inv = :dma_item_inv";
													$resItemINV = $this->pedeo->queryTable($sqlItemINV, array(

																	':dma_item_code' => $detail['nd1_itemcode'],
																	':dma_item_inv'  => 1
													));

													if(isset($resItemINV[0])){

														$ManejaInvetario = 1;

													}else{
														$ManejaInvetario = 0;
													}

													// FIN PROCESO ITEM MANEJA INVENTARIO

													// si el item es inventariable
													if( $ManejaInvetario == 1 ){


																	//LLENANDO DETALLE ASIENTO CONTABLES
																	$DetalleAsientoIngreso = new stdClass();
																	$DetalleAsientoIva = new stdClass();
																	$DetalleCostoInventario = new stdClass();
																	$DetalleCostoCosto = new stdClass();


																	$DetalleAsientoIngreso->ac1_account = is_numeric($detail['nd1_acctcode'])?$detail['nd1_acctcode']: 0;
																	$DetalleAsientoIngreso->ac1_prc_code = isset($detail['nd1_costcode'])?$detail['nd1_costcode']:NULL;
																	$DetalleAsientoIngreso->ac1_uncode = isset($detail['nd1_ubusiness'])?$detail['nd1_ubusiness']:NULL;
																	$DetalleAsientoIngreso->ac1_prj_code = isset($detail['nd1_project'])?$detail['nd1_project']:NULL;
																	$DetalleAsientoIngreso->nd1_linetotal = is_numeric($detail['nd1_linetotal'])?$detail['nd1_linetotal']:0;
																	$DetalleAsientoIngreso->nd1_vat = is_numeric($detail['nd1_vat'])?$detail['nd1_vat']:0;
																	$DetalleAsientoIngreso->nd1_vatsum = is_numeric($detail['nd1_vatsum'])?$detail['nd1_vatsum']:0;
																	$DetalleAsientoIngreso->nd1_price = is_numeric($detail['nd1_price'])?$detail['nd1_price']:0;
																	$DetalleAsientoIngreso->nd1_itemcode = isset($detail['nd1_itemcode'])?$detail['nd1_itemcode']:NULL;
																	$DetalleAsientoIngreso->nd1_quantity = is_numeric($detail['nd1_quantity'])?$detail['nd1_quantity']:0;
																	$DetalleAsientoIngreso->nd1_whscode = isset($detail['nd1_whscode'])?$detail['nd1_whscode']:NULL;
																	$DetalleAsientoIngreso->nd1_fixrate = is_numeric($detail['nd1_fixrate'])?$detail['nd1_fixrate']:0;



																	$DetalleAsientoIva->ac1_account = is_numeric($detail['nd1_acctcode'])?$detail['nd1_acctcode']: 0;
																	$DetalleAsientoIva->ac1_prc_code = isset($detail['nd1_costcode'])?$detail['nd1_costcode']:NULL;
																	$DetalleAsientoIva->ac1_uncode = isset($detail['nd1_ubusiness'])?$detail['nd1_ubusiness']:NULL;
																	$DetalleAsientoIva->ac1_prj_code = isset($detail['nd1_project'])?$detail['nd1_project']:NULL;
																	$DetalleAsientoIva->nd1_linetotal = is_numeric($detail['nd1_linetotal'])?$detail['nd1_linetotal']:0;
																	$DetalleAsientoIva->nd1_vat = is_numeric($detail['nd1_vat'])?$detail['nd1_vat']:0;
																	$DetalleAsientoIva->nd1_vatsum = is_numeric($detail['nd1_vatsum'])?$detail['nd1_vatsum']:0;
																	$DetalleAsientoIva->nd1_price = is_numeric($detail['nd1_price'])?$detail['nd1_price']:0;
																	$DetalleAsientoIva->nd1_itemcode = isset($detail['nd1_itemcode'])?$detail['nd1_itemcode']:NULL;
																	$DetalleAsientoIva->nd1_quantity = is_numeric($detail['nd1_quantity'])?$detail['nd1_quantity']:0;
																	$DetalleAsientoIva->nd1_cuentaIva = is_numeric($detail['nd1_cuentaIva'])?$detail['nd1_cuentaIva']:NULL;
																	$DetalleAsientoIva->nd1_whscode = isset($detail['nd1_whscode'])?$detail['nd1_whscode']:NULL;
																	$DetalleAsientoIva->nd1_fixrate = is_numeric($detail['nd1_fixrate'])?$detail['nd1_fixrate']:0;

																	$codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);

																	$DetalleAsientoIngreso->codigoCuenta = $codigoCuenta;
																	$DetalleAsientoIva->codigoCuenta = $codigoCuenta;
																	$DetalleCostoInventario->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);
																	$DetalleCostoInventario->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);


																	$llave = $DetalleAsientoIngreso->ac1_uncode.$DetalleAsientoIngreso->ac1_prc_code.$DetalleAsientoIngreso->ac1_prj_code.$DetalleAsientoIngreso->ac1_account;
																	$llaveIva = $DetalleAsientoIva->nd1_vat;
																	// $llaveCostoInventario = $DetalleCostoInventario->ac1_account;
																	// $llaveCostoCosto = $DetalleCostoCosto->ac1_account;


																	if(in_array( $llave, $inArrayIngreso )){

																			$posicion = $this->buscarPosicion( $llave, $inArrayIngreso );

																	}else{

																			array_push( $inArrayIngreso, $llave );
																			$posicion = $this->buscarPosicion( $llave, $inArrayIngreso );

																	}


																	if(in_array( $llaveIva, $inArrayIva )){

																			$posicionIva = $this->buscarPosicion( $llaveIva, $inArrayIva );

																	}else{

																			array_push( $inArrayIva, $llaveIva );
																			$posicionIva = $this->buscarPosicion( $llaveIva, $inArrayIva );

																	}


																	if( isset($DetalleConsolidadoIva[$posicionIva])){

																		if(!is_array($DetalleConsolidadoIva[$posicionIva])){
																			$DetalleConsolidadoIva[$posicionIva] = array();
																		}

																	}else{
																		$DetalleConsolidadoIva[$posicionIva] = array();
																	}

																	array_push( $DetalleConsolidadoIva[$posicionIva], $DetalleAsientoIva);


																	if( isset($DetalleConsolidadoIngreso[$posicion])){

																		if(!is_array($DetalleConsolidadoIngreso[$posicion])){
																			$DetalleConsolidadoIngreso[$posicion] = array();
																		}

																	}else{
																		$DetalleConsolidadoIngreso[$posicion] = array();
																	}

																	array_push( $DetalleConsolidadoIngreso[$posicion], $DetalleAsientoIngreso);
												}
										}

										//Procedimiento para llenar Ingreso

										foreach ($DetalleConsolidadoIngreso as $key => $posicion) {
												$granTotalIngreso = 0;
												$granTotalIngresoOriginal = 0;
												$granTotalTasaFija = 0;
												$codigoCuentaIngreso = "";
												$cuenta = "";
												$proyecto = "";
												$prc = "";
												$unidad = "";
												foreach ($posicion as $key => $value) {
														$granTotalIngreso = ($granTotalIngreso + $value->nd1_linetotal);
														$granTotalTasaFija = ($granTotalTasaFija + ($value->nd1_fixrate * $value->nd1_quantity));
														$codigoCuentaIngreso = $value->codigoCuenta;
														$prc = $value->ac1_prc_code;
														$unidad = $value->ac1_uncode;
														$proyecto = $value->ac1_prj_code;
														$cuenta = $value->ac1_account;
												}


												$debito = 0;
												$credito = 0;
												$MontoSysDB = 0;
												$MontoSysCR = 0;
												$granTotalIngresoOriginal = $granTotalIngreso;

												if(trim($Data['vnd_currency']) != $MONEDALOCAL ){
														$granTotalIngreso = ($granTotalIngreso * $TasaDocLoc);
												}

												$MONEDASYS = trim($resMonedaSys[0]['pgm_symbol']);


												$ti = $granTotalIngreso;

												switch ($codigoCuentaIngreso) {
													case 1:
														$credito = $granTotalIngreso;

														if(trim($Data['vnd_currency']) != $MONEDASYS ){
																$ti = ($ti + $granTotalTasaFija);
																$MontoSysCR = ($ti / $TasaLocSys);

														}else{
																$MontoSysCR = $granTotalIngresoOriginal;

														}
														break;

													case 2:
														$credito = $granTotalIngreso;

														if(trim($Data['vnd_currency']) != $MONEDASYS ){
																$ti = ($ti + $granTotalTasaFija);
																$MontoSysCR = ($ti / $TasaLocSys);

														}else{
																$MontoSysCR = $granTotalIngresoOriginal;

														}
														break;

													case 3:
														$credito = $granTotalIngreso;

														if(trim($Data['vnd_currency']) != $MONEDASYS ){
																$ti = ($ti + $granTotalTasaFija);
																$MontoSysCR = ($ti / $TasaLocSys);

														}else{
																$MontoSysCR = $granTotalIngresoOriginal;

														}
														break;

													case 4:
														$credito = $granTotalIngreso;

														if(trim($Data['vnd_currency']) != $MONEDASYS ){
																$ti = ($ti + $granTotalTasaFija);
																$MontoSysCR = ($ti / $TasaLocSys);

														}else{
																$MontoSysCR = $granTotalIngresoOriginal;

														}
														break;

													case 5:
														$credito = $granTotalIngreso;

														if(trim($Data['vnd_currency']) != $MONEDASYS ){
																$ti = ($ti + $granTotalTasaFija);
																$MontoSysCR = ($ti / $TasaLocSys);

														}else{
																$MontoSysCR = $granTotalIngresoOriginal;

														}
														break;

													case 6:
														$credito = $granTotalIngreso;

														if(trim($Data['vnd_currency']) != $MONEDASYS ){
																$ti = ($ti + $granTotalTasaFija);
																$MontoSysCR = ($ti / $TasaLocSys);

														}else{
																$MontoSysCR = $granTotalIngresoOriginal;

														}
														break;

													case 7:
														$credito = $granTotalIngreso;

														if(trim($Data['vnd_currency']) != $MONEDASYS ){
																$ti = ($ti + $granTotalTasaFija);
																$MontoSysCR = ($ti / $TasaLocSys);

														}else{
																$MontoSysCR = $granTotalIngresoOriginal;

														}
														break;
												}

												$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR,2));
												$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB,2));


												$TOTALCXCLOC = ($TOTALCXCLOC + ($debito+$credito));
												$TOTALCXCSYS = ($TOTALCXCSYS + ($MontoSysDB+$MontoSysCR));


												$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

														':ac1_trans_id' => $resInsertAsiento,
														':ac1_account' => $cuenta,
														':ac1_debit' => round($debito, 2),
														':ac1_credit' => round($credito, 2),
														':ac1_debit_sys' => round($MontoSysDB,2),
														':ac1_credit_sys' => round($MontoSysCR,2),
														':ac1_currex' => 0,
														':ac1_doc_date' => $this->validateDate($Data['vnd_docdate'])?$Data['vnd_docdate']:NULL,
														':ac1_doc_duedate' => $this->validateDate($Data['vnd_duedate'])?$Data['vnd_duedate']:NULL,
														':ac1_debit_import' => 0,
														':ac1_credit_import' => 0,
														':ac1_debit_importsys' => 0,
														':ac1_credit_importsys' => 0,
														':ac1_font_key' => $resInsert,
														':ac1_font_line' => 1,
														':ac1_font_type' => is_numeric($Data['vnd_doctype'])?$Data['vnd_doctype']:0,
														':ac1_accountvs' => 1,
														':ac1_doctype' => 18,
														':ac1_ref1' => "",
														':ac1_ref2' => "",
														':ac1_ref3' => "",
														':ac1_prc_code' => $prc,
														':ac1_uncode' => $unidad,
														':ac1_prj_code' => $proyecto,
														':ac1_rescon_date' => NULL,
														':ac1_recon_total' => 0,
														':ac1_made_user' => isset($Data['vnd_createby'])?$Data['vnd_createby']:NULL,
														':ac1_accperiod' => 1,
														':ac1_close' => 0,
														':ac1_cord' => 0,
														':ac1_ven_debit' => 1,
														':ac1_ven_credit' => 1,
														':ac1_fiscal_acct' => 0,
														':ac1_taxid' => 1,
														':ac1_isrti' => 0,
														':ac1_basert' => 0,
														':ac1_mmcode' => 0,
														':ac1_legal_num' => isset($Data['vnd_cardcode'])?$Data['vnd_cardcode']:NULL,
														':ac1_codref' => 1,
														':ac1_line'   => $AC1LINE
											));



											if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
													// Se verifica que el detalle no de error insertando //
											}else{
													// si falla algun insert del detalle de la Nota debito de clientes se devuelven los cambios realizados por la transaccion,
													// se retorna el error y se detiene la ejecucion del codigo restante.
														$this->pedeo->trans_rollback();

														$respuesta = array(
															'error'   => true,
															'data'	  => $resDetalleAsiento,
															'mensaje'	=> 'No se pudo registrar la Nota debito de clientes'
														);

														 $this->response($respuesta);

														 return;
											}
										}
										//FIN Procedimiento para llenar Ingreso


										//Procedimiento para llenar Impuestos

										$granTotalIva = 0;

										foreach ($DetalleConsolidadoIva as $key => $posicion) {
												$granTotalIva = 0;
												$granTotalIva2 = 0;
												$granTotalIvaOriginal = 0;
												$MontoSysCR = 0;

												foreach ($posicion as $key => $value) {
															$granTotalIva = round($granTotalIva + $value->nd1_vatsum,2);

															$v1 = ($value->nd1_linetotal + ($value->nd1_quantity * $value->nd1_fixrate));
															$granTotalIva2 = round($granTotalIva2 + ($v1 * ($value->nd1_vat / 100)), 2);
												}

												$granTotalIvaOriginal = $granTotalIva;



												if(trim($Data['vnd_currency']) != $MONEDALOCAL ){
														$granTotalIva = ($granTotalIva * $TasaDocLoc);
												}



												$TIva = $granTotalIva2;

												if(trim($Data['vnd_currency']) != $MONEDASYS ){
														// $TIva = (($TIva * $TasaFija) / 100) + $TIva;
														$MontoSysDB = ($TIva / $TasaLocSys);

												}else{
														$MontoSysDB = $granTotalIvaOriginal;
												}


												$SumaDebitosSYS = ($SumaDebitosSYS + round($MontoSysDB,2));
												$AC1LINE = $AC1LINE+1;


												$TOTALCXCLOCIVA = ($TOTALCXCLOCIVA + $granTotalIva);
												$TOTALCXCSYSIVA = ($TOTALCXCSYSIVA + $MontoSysDB);

												$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

														':ac1_trans_id' => $resInsertAsiento,
														':ac1_account' => $value->nd1_cuentaIva,
														':ac1_debit' => 0,
														':ac1_credit' => round($granTotalIva, 2),
														':ac1_debit_sys' => 0,
														':ac1_credit_sys' => round($MontoSysDB, 2),
														':ac1_currex' => 0,
														':ac1_doc_date' => $this->validateDate($Data['vnd_docdate'])?$Data['vnd_docdate']:NULL,
														':ac1_doc_duedate' => $this->validateDate($Data['vnd_duedate'])?$Data['vnd_duedate']:NULL,
														':ac1_debit_import' => 0,
														':ac1_credit_import' => 0,
														':ac1_debit_importsys' => 0,
														':ac1_credit_importsys' => 0,
														':ac1_font_key' => $resInsert,
														':ac1_font_line' => 1,
														':ac1_font_type' => is_numeric($Data['vnd_doctype'])?$Data['vnd_doctype']:0,
														':ac1_accountvs' => 1,
														':ac1_doctype' => 18,
														':ac1_ref1' => "",
														':ac1_ref2' => "",
														':ac1_ref3' => "",
														':ac1_prc_code' => NULL,
														':ac1_uncode' => NULL,
														':ac1_prj_code' => NULL,
														':ac1_rescon_date' => NULL,
														':ac1_recon_total' => 0,
														':ac1_made_user' => isset($Data['vnd_createby'])?$Data['vnd_createby']:NULL,
														':ac1_accperiod' => 1,
														':ac1_close' => 0,
														':ac1_cord' => 0,
														':ac1_ven_debit' => 1,
														':ac1_ven_credit' => 1,
														':ac1_fiscal_acct' => 0,
														':ac1_taxid' => 1,
														':ac1_isrti' => 0,
														':ac1_basert' => 0,
														':ac1_mmcode' => 0,
														':ac1_legal_num' => isset($Data['vnd_cardcode'])?$Data['vnd_cardcode']:NULL,
														':ac1_codref' => 1,
														':ac1_line'   => $AC1LINE
											));



											if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
													// Se verifica que el detalle no de error insertando //
											}else{

													// si falla algun insert del detalle de la Nota debito de clientes se devuelven los cambios realizados por la transaccion,
													// se retorna el error y se detiene la ejecucion del codigo restante.
														$this->pedeo->trans_rollback();

														$respuesta = array(
															'error'   => true,
															'data'	  => $resDetalleAsiento,
															'mensaje'	=> 'No se pudo registrar la Nota debito de clientes'
														);

														 $this->response($respuesta);

														 return;
											}
										}

										//FIN Procedimiento para llenar Impuestos


									 //Procedimiento para llenar cuentas por cobrar

										$sqlcuentaCxC = "SELECT  f1.dms_card_code, f2.mgs_acct FROM dmsn AS f1
																		 JOIN dmgs  AS f2
																		 ON CAST(f2.mgs_id AS varchar(100)) = f1.dms_group_num
																		 WHERE  f1.dms_card_code = :dms_card_code
																		 AND f1.dms_card_type = '1'";//1 para clientes";

										$rescuentaCxC = $this->pedeo->queryTable($sqlcuentaCxC, array(":dms_card_code" => $Data['vnd_cardcode']));



										if( isset( $rescuentaCxC[0] )){

													$debitoo = 0;
													$creditoo = 0;
													$MontoSysDB = 0;
													$MontoSysCR = 0;
													$docTotal = 0;
													$docTotalOriginal = 0;

													$cuentaCxC = $rescuentaCxC[0]['mgs_acct'];
													$codigo2= substr($rescuentaCxC[0]['mgs_acct'], 0, 1);


													if( $codigo2 == 1 || $codigo2 == "1" ){

															$debitoo = ($TOTALCXCLOC + $TOTALCXCLOCIVA);
															$MontoSysDB =	($TOTALCXCSYS + $TOTALCXCSYSIVA);

													}else if( $codigo2 == 2 || $codigo2 == "2" ){

															$debitoo = ($TOTALCXCLOC + $TOTALCXCLOCIVA);
															$MontoSysDB =	($TOTALCXCSYS + $TOTALCXCSYSIVA);

													}else if( $codigo2 == 3 || $codigo2 == "3" ){

															$debitoo = ($TOTALCXCLOC + $TOTALCXCLOCIVA);
															$MontoSysDB = ($TOTALCXCSYS + $TOTALCXCSYSIVA);

													}else if( $codigo2 == 4 || $codigo2 == "4" ){

															$debitoo = ($TOTALCXCLOC + $TOTALCXCLOCIVA);
															$MontoSysDB =	($TOTALCXCSYS + $TOTALCXCSYSIVA);

													}else if( $codigo2 == 5  || $codigo2 == "5" ){

															$debitoo = ($TOTALCXCLOC + $TOTALCXCLOCIVA);
															$MontoSysDB =	($TOTALCXCSYS + $TOTALCXCSYSIVA);

													}else if( $codigo2 == 6 || $codigo2 == "6" ){

															$debitoo = ($TOTALCXCLOC + $TOTALCXCLOCIVA);
															$MontoSysDB =	($TOTALCXCSYS + $TOTALCXCSYSIVA);

													}else if( $codigo2 == 7 || $codigo2 == "7" ){

															$debitoo = ($TOTALCXCLOC + $TOTALCXCLOCIVA);
															$MontoSysDB =	($TOTALCXCSYS + $TOTALCXCSYSIVA);
													}


													$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR,2));
													$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB,2));

													$AC1LINE = $AC1LINE+1;

													$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

															':ac1_trans_id' => $resInsertAsiento,
															':ac1_account' => $cuentaCxC,
															':ac1_debit' => round($debitoo, 2),
															':ac1_credit' => round($creditoo, 2),
															':ac1_debit_sys' => round($MontoSysDB,2),
															':ac1_credit_sys' => round($MontoSysCR,2),
															':ac1_currex' => 0,
															':ac1_doc_date' => $this->validateDate($Data['vnd_docdate'])?$Data['vnd_docdate']:NULL,
															':ac1_doc_duedate' => $this->validateDate($Data['vnd_duedate'])?$Data['vnd_duedate']:NULL,
															':ac1_debit_import' => 0,
															':ac1_credit_import' => 0,
															':ac1_debit_importsys' => 0,
															':ac1_credit_importsys' => 0,
															':ac1_font_key' => $resInsert,
															':ac1_font_line' => 1,
															':ac1_font_type' => is_numeric($Data['vnd_doctype'])?$Data['vnd_doctype']:0,
															':ac1_accountvs' => 1,
															':ac1_doctype' => 18,
															':ac1_ref1' => "",
															':ac1_ref2' => "",
															':ac1_ref3' => "",
															':ac1_prc_code' => NULL,
															':ac1_uncode' => NULL,
															':ac1_prj_code' => NULL,
															':ac1_rescon_date' => NULL,
															':ac1_recon_total' => 0,
															':ac1_made_user' => isset($Data['vnd_createby'])?$Data['vnd_createby']:NULL,
															':ac1_accperiod' => 1,
															':ac1_close' => 0,
															':ac1_cord' => 0,
															':ac1_ven_debit' => 1,
															':ac1_ven_credit' => 1,
															':ac1_fiscal_acct' => 0,
															':ac1_taxid' => 1,
															':ac1_isrti' => 0,
															':ac1_basert' => 0,
															':ac1_mmcode' => 0,
															':ac1_legal_num' => isset($Data['vnd_cardcode'])?$Data['vnd_cardcode']:NULL,
															':ac1_codref' => 1,
															':ac1_line'   => $AC1LINE
												));

												if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
														// Se verifica que el detalle no de error insertando //
												}else{

														// si falla algun insert del detalle de la Nota debito de clientes se devuelven los cambios realizados por la transaccion,
														// se retorna el error y se detiene la ejecucion del codigo restante.
															$this->pedeo->trans_rollback();

															$respuesta = array(
																'error'   => true,
																'data'	  => $resDetalleAsiento,
																'mensaje'	=> 'No se pudo registrar la Nota debito de clientes'
															);

															 $this->response($respuesta);

															 return;
												}

										}else{

													$this->pedeo->trans_rollback();

													$respuesta = array(
														'error'   => true,
														'data'	  => $resDetalleAsiento,
														'mensaje'	=> 'No se pudo registrar la Nota debito de clientes, el tercero no tiene cuenta asociada'
													);

													 $this->response($respuesta);

													 return;
										}
										//FIN Procedimiento para llenar cuentas por cobrar

										// Si todo sale bien despues de insertar el detalle de la Nota debito de clientes
										// se confirma la trasaccion  para que los cambios apliquen permanentemente
										// en la base de datos y se confirma la operacion exitosa.
										$this->pedeo->trans_commit();

										$respuesta = array(
											'error' => false,
											'data' => $resInsert,
											'mensaje' =>'Nota debito de clientes registrada con exito'
										);


									}else{
										// Se devuelven los cambios realizados en la transaccion
										// si occurre un error  y se muestra devuelve el error.
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data' => $resInsert,
													'mensaje'	=> 'No se pudo registrar la Nota debito de clientes'
												);

									}




				}catch(\Error $e){

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' 		=> $e,
								'mensaje'	=> 'No se pudo registrar la Nota debito de clientes'
							);

							$this->response($respuesta);

							return;
				}


         $this->response($respuesta);
	}

  //ACTUALIZAR Nota debito de clientes
  public function updateSalesNd_post(){

      $Data = $this->post();

			if(!isset($Data['vnd_docentry']) OR !isset($Data['vnd_docnum']) OR
				 !isset($Data['vnd_docdate']) OR !isset($Data['vnd_duedate']) OR
				 !isset($Data['vnd_duedev']) OR !isset($Data['vnd_pricelist']) OR
				 !isset($Data['vnd_cardcode']) OR !isset($Data['vnd_cardname']) OR
				 !isset($Data['vnd_currency']) OR !isset($Data['vnd_contacid']) OR
				 !isset($Data['vnd_slpcode']) OR !isset($Data['vnd_empid']) OR
				 !isset($Data['vnd_comment']) OR !isset($Data['vnd_doctotal']) OR
				 !isset($Data['vnd_baseamnt']) OR !isset($Data['vnd_taxtotal']) OR
				 !isset($Data['vnd_discprofit']) OR !isset($Data['vnd_discount']) OR
				 !isset($Data['vnd_createat']) OR !isset($Data['vnd_baseentry']) OR
				 !isset($Data['vnd_basetype']) OR !isset($Data['vnd_doctype']) OR
				 !isset($Data['vnd_idadd']) OR !isset($Data['vnd_adress']) OR
				 !isset($Data['vnd_paytype']) OR !isset($Data['vnd_attch']) OR
				 !isset($Data['detail'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }


			$ContenidoDetalle = json_decode($Data['detail'], true);


      if(!is_array($ContenidoDetalle)){
          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'No se encontro el detalle de la Nota debito de clientes'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }

      $sqlUpdate = "UPDATE dvnd	SET vnd_docdate=:vnd_docdate,vnd_duedate=:vnd_duedate, vnd_duedev=:vnd_duedev, vnd_pricelist=:vnd_pricelist, vnd_cardcode=:vnd_cardcode,
			  						vnd_cardname=:vnd_cardname, vnd_currency=:vnd_currency, vnd_contacid=:vnd_contacid, vnd_slpcode=:vnd_slpcode,
										vnd_empid=:vnd_empid, vnd_comment=:vnd_comment, vnd_doctotal=:vnd_doctotal, vnd_baseamnt=:vnd_baseamnt,
										vnd_taxtotal=:vnd_taxtotal, vnd_discprofit=:vnd_discprofit, vnd_discount=:vnd_discount, vnd_createat=:vnd_createat,
										vnd_baseentry=:vnd_baseentry, vnd_basetype=:vnd_basetype, vnd_doctype=:vnd_doctype, vnd_idadd=:vnd_idadd,
										vnd_adress=:vnd_adress, vnd_paytype=:vnd_paytype, vnd_attch=:vnd_attch WHERE vnd_docentry=:vnd_docentry";

      $this->pedeo->trans_begin();

      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
							':vnd_docnum' => is_numeric($Data['vnd_docnum'])?$Data['vnd_docnum']:0,
							':vnd_docdate' => $this->validateDate($Data['vnd_docdate'])?$Data['vnd_docdate']:NULL,
							':vnd_duedate' => $this->validateDate($Data['vnd_duedate'])?$Data['vnd_duedate']:NULL,
							':vnd_duedev' => $this->validateDate($Data['vnd_duedev'])?$Data['vnd_duedev']:NULL,
							':vnd_pricelist' => is_numeric($Data['vnd_pricelist'])?$Data['vnd_pricelist']:0,
							':vnd_cardcode' => isset($Data['vnd_pricelist'])?$Data['vnd_pricelist']:NULL,
							':vnd_cardname' => isset($Data['vnd_cardname'])?$Data['vnd_cardname']:NULL,
							':vnd_currency' => isset($Data['vnd_currency'])?$Data['vnd_currency']:NULL,
							':vnd_contacid' => isset($Data['vnd_contacid'])?$Data['vnd_contacid']:NULL,
							':vnd_slpcode' => is_numeric($Data['vnd_slpcode'])?$Data['vnd_slpcode']:0,
							':vnd_empid' => is_numeric($Data['vnd_empid'])?$Data['vnd_empid']:0,
							':vnd_comment' => isset($Data['vnd_comment'])?$Data['vnd_comment']:NULL,
							':vnd_doctotal' => is_numeric($Data['vnd_doctotal'])?$Data['vnd_doctotal']:0,
							':vnd_baseamnt' => is_numeric($Data['vnd_baseamnt'])?$Data['vnd_baseamnt']:0,
							':vnd_taxtotal' => is_numeric($Data['vnd_taxtotal'])?$Data['vnd_taxtotal']:0,
							':vnd_discprofit' => is_numeric($Data['vnd_discprofit'])?$Data['vnd_discprofit']:0,
							':vnd_discount' => is_numeric($Data['vnd_discount'])?$Data['vnd_discount']:0,
							':vnd_createat' => $this->validateDate($Data['vnd_createat'])?$Data['vnd_createat']:NULL,
							':vnd_baseentry' => is_numeric($Data['vnd_baseentry'])?$Data['vnd_baseentry']:0,
							':vnd_basetype' => is_numeric($Data['vnd_basetype'])?$Data['vnd_basetype']:0,
							':vnd_doctype' => is_numeric($Data['vnd_doctype'])?$Data['vnd_doctype']:0,
							':vnd_idadd' => isset($Data['vnd_idadd'])?$Data['vnd_idadd']:NULL,
							':vnd_adress' => isset($Data['vnd_adress'])?$Data['vnd_adress']:NULL,
							':vnd_paytype' => is_numeric($Data['vnd_paytype'])?$Data['vnd_paytype']:0,
							':vnd_attch' => $this->getUrl(count(trim(($Data['vnd_attch']))) > 0 ? $Data['vnd_attch']:NULL),
							':vnd_docentry' => $Data['vnd_docentry']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

						$this->pedeo->queryTable("DELETE FROM vnd1 WHERE nd1_docentry=:nd1_docentry", array(':nd1_docentry' => $Data['vnd_docentry']));

						foreach ($ContenidoDetalle as $key => $detail) {

									$sqlInsertDetail = "INSERT INTO vnd1(nd1_docentry, nd1_itemcode, nd1_itemname, nd1_quantity, nd1_uom, nd1_whscode,
																			nd1_price, nd1_vat, nd1_vatsum, nd1_discount, nd1_linetotal, nd1_costcode, nd1_ubusiness, nd1_project,
																			nd1_acctcode, nd1_basetype, nd1_doctype, nd1_avprice, nd1_inventory, nd1_acciva)VALUES(:nd1_docentry, :nd1_itemcode, :nd1_itemname, :nd1_quantity,
																			:nd1_uom, :nd1_whscode,:nd1_price, :nd1_vat, :nd1_vatsum, :nd1_discount, :nd1_linetotal, :nd1_costcode, :nd1_ubusiness, :nd1_project,
																			:nd1_acctcode, :nd1_basetype, :nd1_doctype, :nd1_avprice, :nd1_inventory, :nd1_acciva)";

									$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
											':nd1_docentry' => $Data['vnd_docentry'],
											':nd1_itemcode' => isset($detail['nd1_itemcode'])?$detail['nd1_itemcode']:NULL,
											':nd1_itemname' => isset($detail['nd1_itemname'])?$detail['nd1_itemname']:NULL,
											':nd1_quantity' => is_numeric($detail['nd1_quantity'])?$detail['nd1_quantity']:0,
											':nd1_uom' => isset($detail['nd1_uom'])?$detail['nd1_uom']:NULL,
											':nd1_whscode' => isset($detail['nd1_whscode'])?$detail['nd1_whscode']:NULL,
											':nd1_price' => is_numeric($detail['nd1_price'])?$detail['nd1_price']:0,
											':nd1_vat' => is_numeric($detail['nd1_vat'])?$detail['nd1_vat']:0,
											':nd1_vatsum' => is_numeric($detail['nd1_vatsum'])?$detail['nd1_vatsum']:0,
											':nd1_discount' => is_numeric($detail['nd1_discount'])?$detail['nd1_discount']:0,
											':nd1_linetotal' => is_numeric($detail['nd1_linetotal'])?$detail['nd1_linetotal']:0,
											':nd1_costcode' => isset($detail['nd1_costcode'])?$detail['nd1_costcode']:NULL,
											':nd1_ubusiness' => isset($detail['nd1_ubusiness'])?$detail['nd1_ubusiness']:NULL,
											':nd1_project' => isset($detail['nd1_project'])?$detail['nd1_project']:NULL,
											':nd1_acctcode' => is_numeric($detail['nd1_acctcode'])?$detail['nd1_acctcode']:0,
											':nd1_basetype' => is_numeric($detail['nd1_basetype'])?$detail['nd1_basetype']:0,
											':nd1_doctype' => is_numeric($detail['nd1_doctype'])?$detail['nd1_doctype']:0,
											':nd1_avprice' => is_numeric($detail['nd1_avprice'])?$detail['nd1_avprice']:0,
											':nd1_inventory' => is_numeric($detail['nd1_inventory'])?$detail['nd1_inventory']:NULL,
											':nd1_acciva' => is_numeric($detail['nd1_cuentaIva'])?$detail['nd1_cuentaIva']:NULL
									));

									if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
											// Se verifica que el detalle no de error insertando //
									}else{

											// si falla algun insert del detalle de la Nota debito de clientes se devuelven los cambios realizados por la transaccion,
											// se retorna el error y se detiene la ejecucion del codigo restante.
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data' => $resInsert,
													'mensaje'	=> 'No se pudo registrar la Nota debito de clientes'
												);

												 $this->response($respuesta);

												 return;
									}
						}


						$this->pedeo->trans_commit();

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Nota debito de clientes actualizada con exito'
            );


      }else{

						$this->pedeo->trans_rollback();

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la Nota debito de clientes'
            );

      }

       $this->response($respuesta);
  }


  //OBTENER Nota debito de clientesES
  public function getSalesNd_get(){

        $sqlSelect = self::getColumn('dvnd','vnd');
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


	//OBTENER Nota debito de clientes POR ID
	public function getSalesNdById_get(){

				$Data = $this->get();

				if(!isset($Data['vnd_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM dvnd WHERE vnd_docentry =:vnd_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":vnd_docentry" => $Data['vnd_docentry']));

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


	//OBTENER Nota debito de clientes DETALLE POR ID
	public function getSalesNdDetail_get(){

				$Data = $this->get();

				if(!isset($Data['nd1_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM vnd1 WHERE nd1_docentry =:nd1_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":nd1_docentry" => $Data['nd1_docentry']));

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




	//OBTENER NOTA DEBITO DE VENTAS POR SOCIO DE NEGOCIO
	public function getSalesNdBySN_get(){

				$Data = $this->get();

				if(!isset($Data['dms_card_code'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM dvnd WHERE vnd_cardcode =:vnd_cardcode";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":vnd_cardcode" => $Data['dms_card_code']));

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








  private function getUrl($data){
      $url = "";

      if ($data == NULL){

        return $url;

      }

      $ruta = '/var/www/html/serpent/assets/img/anexos/';
      $milliseconds = round(microtime(true) * 1000);


      $nombreArchivo = $milliseconds.".pdf";

      touch($ruta.$nombreArchivo);

      $file = fopen($ruta.$nombreArchivo,"wb");

      if(!empty($data)){

        fwrite($file, base64_decode($data));

        fclose($file);

        $url = "assets/img/anexos/".$nombreArchivo;
      }

      return $url;
  }

	private function buscarPosicion($llave, $inArray){
			$res = 0;
	  	for($i = 0; $i < count($inArray); $i++) {
					if($inArray[$i] == "$llave"){
								$res =  $i;
								break;
					}
			}

			return $res;
	}

	private function validateDate($fecha){
			if(strlen($fecha) == 10 OR strlen($fecha) > 10){
				return true;
			}else{
				return false;
			}
	}



	private function setAprobacion($Encabezado, $Detalle, $Carpeta, $prefijoe, $prefijod){

		$sqlInsert = "INSERT INTO dpap(pap_series, pap_docnum, pap_docdate, pap_duedate, pap_duedev, pap_pricelist, pap_cardcode,
									pap_cardname, pap_currency, pap_contacid, pap_slpcode, pap_empid, pap_comment, pap_doctotal, pap_baseamnt, pap_taxtotal,
									pap_discprofit, pap_discount, pap_createat, pap_baseentry, pap_basetype, pap_doctype, pap_idadd, pap_adress, pap_paytype,
									pap_attch,pap_createby,pap_origen)VALUES(:pap_series, :pap_docnum, :pap_docdate, :pap_duedate, :pap_duedev, :pap_pricelist, :pap_cardcode, :pap_cardname,
									:pap_currency, :pap_contacid, :pap_slpcode, :pap_empid, :pap_comment, :pap_doctotal, :pap_baseamnt, :pap_taxtotal, :pap_discprofit, :pap_discount,
									:pap_createat, :pap_baseentry, :pap_basetype, :pap_doctype, :pap_idadd, :pap_adress, :pap_paytype, :pap_attch,:pap_createby,:pap_origen)";

		// Se Inicia la transaccion,
		// Todas las consultas de modificacion siguientes
		// aplicaran solo despues que se confirme la transaccion,
		// de lo contrario no se aplicaran los cambios y se devolvera
		// la base de datos a su estado original.

		$this->pedeo->trans_begin();

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
					':pap_docnum' => 0,
					':pap_series' => is_numeric($Encabezado[$prefijoe.'_series'])?$Encabezado[$prefijoe.'_series']:0,
					':pap_docdate' => $this->validateDate($Encabezado[$prefijoe.'_docdate'])?$Encabezado[$prefijoe.'_docdate']:NULL,
					':pap_duedate' => $this->validateDate($Encabezado[$prefijoe.'_duedate'])?$Encabezado[$prefijoe.'_duedate']:NULL,
					':pap_duedev' => $this->validateDate($Encabezado[$prefijoe.'_duedev'])?$Encabezado[$prefijoe.'_duedev']:NULL,
					':pap_pricelist' => is_numeric($Encabezado[$prefijoe.'_pricelist'])?$Encabezado[$prefijoe.'_pricelist']:0,
					':pap_cardcode' => isset($Encabezado[$prefijoe.'_cardcode'])?$Encabezado[$prefijoe.'_cardcode']:NULL,
					':pap_cardname' => isset($Encabezado[$prefijoe.'_cardname'])?$Encabezado[$prefijoe.'_cardname']:NULL,
					':pap_currency' => isset($Encabezado[$prefijoe.'_currency'])?$Encabezado[$prefijoe.'_currency']:NULL,
					':pap_contacid' => isset($Encabezado[$prefijoe.'_contacid'])?$Encabezado[$prefijoe.'_contacid']:NULL,
					':pap_slpcode' => is_numeric($Encabezado[$prefijoe.'_slpcode'])?$Encabezado[$prefijoe.'_slpcode']:0,
					':pap_empid' => is_numeric($Encabezado[$prefijoe.'_empid'])?$Encabezado[$prefijoe.'_empid']:0,
					':pap_comment' => isset($Encabezado[$prefijoe.'_comment'])?$Encabezado[$prefijoe.'_comment']:NULL,
					':pap_doctotal' => is_numeric($Encabezado[$prefijoe.'_doctotal'])?$Encabezado[$prefijoe.'_doctotal']:0,
					':pap_baseamnt' => is_numeric($Encabezado[$prefijoe.'_baseamnt'])?$Encabezado[$prefijoe.'_baseamnt']:0,
					':pap_taxtotal' => is_numeric($Encabezado[$prefijoe.'_taxtotal'])?$Encabezado[$prefijoe.'_taxtotal']:0,
					':pap_discprofit' => is_numeric($Encabezado[$prefijoe.'_discprofit'])?$Encabezado[$prefijoe.'_discprofit']:0,
					':pap_discount' => is_numeric($Encabezado[$prefijoe.'_discount'])?$Encabezado[$prefijoe.'_discount']:0,
					':pap_createat' => $this->validateDate($Encabezado[$prefijoe.'_createat'])?$Encabezado[$prefijoe.'_createat']:NULL,
					':pap_baseentry' => is_numeric($Encabezado[$prefijoe.'_baseentry'])?$Encabezado[$prefijoe.'_baseentry']:0,
					':pap_basetype' => is_numeric($Encabezado[$prefijoe.'_basetype'])?$Encabezado[$prefijoe.'_basetype']:0,
					':pap_doctype' => 21,
					':pap_idadd' => isset($Encabezado[$prefijoe.'_idadd'])?$Encabezado[$prefijoe.'_idadd']:NULL,
					':pap_adress' => isset($Encabezado[$prefijoe.'_adress'])?$Encabezado[$prefijoe.'_adress']:NULL,
					':pap_paytype' => is_numeric($Encabezado[$prefijoe.'_paytype'])?$Encabezado[$prefijoe.'_paytype']:0,
					':pap_createby' => isset($Encabezado[$prefijoe.'_createby'])?$Encabezado[$prefijoe.'_createby']:NULL,
					':pap_attch' => $this->getUrl(count(trim(($Encabezado[$prefijoe.'_attch']))) > 0 ? $Encabezado[$prefijoe.'_attch']:NULL, $Carpeta),
					':pap_origen' => is_numeric($Encabezado[$prefijoe.'_doctype'])?$Encabezado[$prefijoe.'_doctype']:0,

				));


				if(is_numeric($resInsert) && $resInsert > 0){

						//SE INSERTA EL ESTADO DEL DOCUMENTO

						$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

						$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


											':bed_docentry' => $resInsert,
											':bed_doctype' =>  21,
											':bed_status' => 5, //ESTADO CERRADO
											':bed_createby' => $Encabezado[$prefijoe.'_createby'],
											':bed_date' => date('Y-m-d'),
											':bed_baseentry' => NULL,
											':bed_basetype' => NULL
						));


						if(is_numeric($resInsertEstado) && $resInsertEstado > 0){

						}else{

								 $this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data' => $resInsertEstado,
										'mensaje'	=> 'No se pudo registrar la cotizacion de ventas'
									);


									$this->response($respuesta);

									return;
						}

						//FIN PROCESO ESTADO DEL DOCUMENTO

						foreach ($Detalle as $key => $detail) {

									$sqlInsertDetail = "INSERT INTO pap1(ap1_docentry, ap1_itemcode, ap1_itemname, ap1_quantity, ap1_uom, ap1_whscode,
																			ap1_price, ap1_vat, ap1_vatsum, ap1_discount, ap1_linetotal, ap1_costcode, ap1_ubusiness, ap1_project,
																			ap1_acctcode, ap1_basetype, ap1_doctype, ap1_avprice, ap1_inventory, ap1_linenum, ap1_acciva)VALUES(:ap1_docentry, :ap1_itemcode, :ap1_itemname, :ap1_quantity,
																			:ap1_uom, :ap1_whscode,:ap1_price, :ap1_vat, :ap1_vatsum, :ap1_discount, :ap1_linetotal, :ap1_costcode, :ap1_ubusiness, :ap1_project,
																			:ap1_acctcode, :ap1_basetype, :ap1_doctype, :ap1_avprice, :ap1_inventory,:ap1_linenum,:ap1_acciva)";

									$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
													':ap1_docentry' => $resInsert,
													':ap1_itemcode' => isset($detail[$prefijod.'_itemcode'])?$detail[$prefijod.'_itemcode']:NULL,
													':ap1_itemname' => isset($detail[$prefijod.'_itemname'])?$detail[$prefijod.'_itemname']:NULL,
													':ap1_quantity' => is_numeric($detail[$prefijod.'_quantity'])?$detail[$prefijod.'_quantity']:0,
													':ap1_uom' => isset($detail[$prefijod.'_uom'])?$detail[$prefijod.'_uom']:NULL,
													':ap1_whscode' => isset($detail[$prefijod.'_whscode'])?$detail[$prefijod.'_whscode']:NULL,
													':ap1_price' => is_numeric($detail[$prefijod.'_price'])?$detail[$prefijod.'_price']:0,
													':ap1_vat' => is_numeric($detail[$prefijod.'_vat'])?$detail[$prefijod.'_vat']:0,
													':ap1_vatsum' => is_numeric($detail[$prefijod.'_vatsum'])?$detail[$prefijod.'_vatsum']:0,
													':ap1_discount' => is_numeric($detail[$prefijod.'_discount'])?$detail[$prefijod.'_discount']:0,
													':ap1_linetotal' => is_numeric($detail[$prefijod.'_linetotal'])?$detail[$prefijod.'_linetotal']:0,
													':ap1_costcode' => isset($detail[$prefijod.'_costcode'])?$detail[$prefijod.'_costcode']:NULL,
													':ap1_ubusiness' => isset($detail[$prefijod.'_ubusiness'])?$detail[$prefijod.'_ubusiness']:NULL,
													':ap1_project' => isset($detail[$prefijod.'_project'])?$detail[$prefijod.'_project']:NULL,
													':ap1_acctcode' => is_numeric($detail[$prefijod.'_acctcode'])?$detail[$prefijod.'_acctcode']:0,
													':ap1_basetype' => is_numeric($detail[$prefijod.'_basetype'])?$detail[$prefijod.'_basetype']:0,
													':ap1_doctype' => is_numeric($detail[$prefijod.'_doctype'])?$detail[$prefijod.'_doctype']:0,
													':ap1_avprice' => is_numeric($detail[$prefijod.'_avprice'])?$detail[$prefijod.'_avprice']:0,
													':ap1_inventory' => is_numeric($detail[$prefijod.'_inventory'])?$detail[$prefijod.'_inventory']:NULL,
													':ap1_linenum' => is_numeric($detail[$prefijod.'_linenum'])?$detail[$prefijod.'_linenum']:NULL,
													':ap1_acciva' => is_numeric($detail[$prefijod.'_acciva'])?$detail[$prefijod.'_acciva']:NULL
									));

									if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
											// Se verifica que el detalle no de error insertando //
									}else{

											// si falla algun insert del detalle de la cotizacion se devuelven los cambios realizados por la transaccion,
											// se retorna el error y se detiene la ejecucion del codigo restante.
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data' => $resInsertDetail,
													'mensaje'	=> 'No se pudo registrar la cotización'
												);

												 $this->response($respuesta);

												 return;
									}


						}


						// Si todo sale bien despues de insertar el detalle de la cotizacion
						// se confirma la trasaccion  para que los cambios apliquen permanentemente
						// en la base de datos y se confirma la operacion exitosa.
						$this->pedeo->trans_commit();

						$respuesta = array(
							'error' => false,
							'data' => $resInsert,
							'mensaje' =>'El documento fue creado, pero es necesario que sea aprobado'
						);

						$this->response($respuesta);

						return;


				}else{

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data'    => $resInsert,
								'mensaje'	=> 'No se pudo crear la cotización'
							);

							$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

							return;
				}

	}


}