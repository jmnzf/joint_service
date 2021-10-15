<?php
// COTIZACIONES
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class InventoryEntry extends REST_Controller {

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

  //CREAR NUEVA ENTRADA
	public function createInventoryEntry_post(){

      $Data = $this->post();
			$DocNumVerificado = 0;
			$DetalleCuentaLineaDocumento = new stdClass();
			$DetalleConsolidadoCuentaLineaDocumento = [];
			$inArrayCuentaLineaDocumento = array();
			$llaveCuentaLineaDocumento = "";
			$posicionCuentaLineaDocumento = 0;
			$DetalleCuentaGrupo = new stdClass();
			$DetalleConsolidadoCuentaGrupo = [];
			$inArrayCuentaGrupo = array();
			$llaveCuentaGrupo = "";
			$posicionCuentaGrupo = 0;
			// Se globaliza la variable sqlDetalleAsiento
			$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
													ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
													ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
													ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref)VALUES (:ac1_trans_id, :ac1_account,
													:ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
													:ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
													:ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
													:ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref)";

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
            'mensaje' =>'No se encontro el detalle de la entrada'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }
				//BUSCANDO LA NUMERACION DEL DOCUMENTO
			  $sqlNumeracion = " SELECT pgs_nextnum,pgs_last_num FROM  pgdn WHERE pgs_id = :pgs_id";

				$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['iei_series']));

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

				//Obtener Carpeta Principal del Proyecto
				$sqlMainFolder = " SELECT * FROM params";
				$resMainFolder = $this->pedeo->queryTable($sqlMainFolder, array());

				if(!isset($resMainFolder[0])){
						$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'No se encontro la caperta principal del proyecto'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}

				// SE BUSCA LA MONEDA DE SISTEMA PARAMETRIZADA

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

				$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
				$resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $Data['iei_docdate']));

				if(isset($resBusTasa2[0])){

				}else{
						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :'.$Data['iei_docdate']
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}

				$TasaLocSys = $resBusTasa2[0]['tsa_value'];
				// FIN PROCEDIMIENTO PARA OBTENER MONEDA DE SISTEMA


        $sqlInsert = "INSERT INTO miei (iei_docnum, iei_docdate, iei_duedate, iei_duedev, iei_pricelist, iei_cardcode, iei_cardname, iei_contacid, iei_slpcode, iei_empid, iei_comment, iei_doctotal,
                      iei_taxtotal, iei_discprofit, iei_discount, iei_createat, iei_baseentry, iei_basetype, iei_doctype, iei_idadd, iei_adress, iei_paytype, iei_attch,
                      iei_baseamnt, iei_series, iei_createby, iei_currency)
                      VALUES
                      (:iei_docnum, :iei_docdate, :iei_duedate, :iei_duedev, :iei_pricelist, :iei_cardcode, :iei_cardname, :iei_contacid, :iei_slpcode, :iei_empid, :iei_comment, :iei_doctotal,
                      :iei_taxtotal, :iei_discprofit, :iei_discount, :iei_createat, :iei_baseentry, :iei_basetype, :iei_doctype, :iei_idadd, :iei_adress, :iei_paytype, :iei_attch,
                      :iei_baseamnt, :iei_series, :iei_createby, :iei_currency)";


				// Se Inicia la transaccion,
				// Todas las consultas de modificacion siguientes
				// aplicaran solo despues que se confirme la transaccion,
				// de lo contrario no se aplicaran los cambios y se devolvera
				// la base de datos a su estado original.

			  $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(

                              ':iei_docnum' => $DocNumVerificado,
                              ':iei_docdate'  => $this->validateDate($Data['iei_docdate'])?$Data['iei_docdate']:NULL,
                              ':iei_duedate' => $this->validateDate($Data['iei_duedate'])?$Data['iei_duedate']:NULL,
                              ':iei_duedev' => $this->validateDate($Data['iei_duedev'])?$Data['iei_duedev']:NULL,
                              ':iei_pricelist' => is_numeric($Data['iei_pricelist'])?$Data['iei_pricelist']:0,
                              ':iei_cardcode' => isset($Data['iei_cardcode'])?$Data['iei_cardcode']:NULL,
                              ':iei_cardname' => isset($Data['iei_cardname'])?$Data['iei_cardname']:NULL,
                              ':iei_contacid' => is_numeric($Data['iei_contacid'])?$Data['iei_contacid']:0,
                              ':iei_slpcode' => is_numeric($Data['iei_slpcode'])?$Data['iei_slpcode']:0,
                              ':iei_empid' => is_numeric($Data['iei_empid'])?$Data['iei_empid']:0,
                              ':iei_comment' => isset($Data['iei_comment'])?$Data['iei_comment']:NULL,
                              ':iei_doctotal' => is_numeric($Data['iei_doctotal'])?$Data['iei_doctotal']:0,
                              ':iei_baseamnt' => is_numeric($Data['iei_baseamnt'])?$Data['iei_baseamnt']:0,
                              ':iei_taxtotal' => is_numeric($Data['iei_taxtotal'])?$Data['iei_taxtotal']:0,
                              ':iei_discprofit' => is_numeric($Data['iei_discprofit'])?$Data['iei_discprofit']:0,
                              ':iei_discount' => is_numeric($Data['iei_discount'])?$Data['iei_discount']:0,
                              ':iei_createat' => $this->validateDate($Data['iei_createat'])?$Data['iei_createat']:NULL,
                              ':iei_baseentry' => is_numeric($Data['iei_baseentry'])?$Data['iei_baseentry']:0,
                              ':iei_basetype' => is_numeric($Data['iei_basetype'])?$Data['iei_basetype']:0,
                              ':iei_doctype' => is_numeric($Data['iei_doctype'])?$Data['iei_doctype']:0,
                              ':iei_idadd' => isset($Data['iei_idadd'])?$Data['iei_idadd']:NULL,
                              ':iei_adress' => isset($Data['iei_adress'])?$Data['iei_adress']:NULL,
                              ':iei_paytype' => is_numeric($Data['iei_paytype'])?$Data['iei_paytype']:0,
                              ':iei_attch' => $this->getUrl(count(trim(($Data['iei_attch']))) > 0 ? $Data['iei_attch']:NULL, $resMainFolder[0]['main_folder']),
                              ':iei_series' => is_numeric($Data['iei_series'])?$Data['iei_series']:0,
                              ':iei_createby' => isset($Data['iei_createby'])?$Data['iei_createby']:NULL,
                              ':iei_currency' => isset($Data['iei_currency'])?$Data['iei_currency']:NULL

						));

        if(is_numeric($resInsert) && $resInsert > 0){

					//Se agregan los asientos contables*/*******

					$sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user)
															 VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user)";


					$resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

							':mac_doc_num' => 1,
							':mac_status' => 1,
							':mac_base_type' => is_numeric($Data['iei_doctype'])?$Data['iei_doctype']:0,
							':mac_base_entry' => $resInsert,
							':mac_doc_date' => $this->validateDate($Data['iei_docdate'])?$Data['iei_docdate']:NULL,
							':mac_doc_duedate' => $this->validateDate($Data['iei_duedate'])?$Data['iei_duedate']:NULL,
							':mac_legal_date' => $this->validateDate($Data['iei_docdate'])?$Data['iei_docdate']:NULL,
							':mac_ref1' => is_numeric($Data['iei_doctype'])?$Data['iei_doctype']:0,
							':mac_ref2' => "",
							':mac_ref3' => "",
							':mac_loc_total' => is_numeric($Data['iei_doctotal'])?$Data['iei_doctotal']:0,
							':mac_fc_total' => is_numeric($Data['iei_doctotal'])?$Data['iei_doctotal']:0,
							':mac_sys_total' => is_numeric($Data['iei_doctotal'])?$Data['iei_doctotal']:0,
							':mac_trans_dode' => 1,
							':mac_beline_nume' => 1,
							':mac_vat_date' => $this->validateDate($Data['iei_docdate'])?$Data['iei_docdate']:NULL,
							':mac_serie' => 1,
							':mac_number' => 1,
							':mac_bammntsys' => is_numeric($Data['iei_baseamnt'])?$Data['iei_baseamnt']:0,
							':mac_bammnt' => is_numeric($Data['iei_baseamnt'])?$Data['iei_baseamnt']:0,
							':mac_wtsum' => 1,
							':mac_vatsum' => is_numeric($Data['iei_taxtotal'])?$Data['iei_taxtotal']:0,
							':mac_comments' => isset($Data['iei_comment'])?$Data['iei_comment']:NULL,
							':mac_create_date' => $this->validateDate($Data['iei_createat'])?$Data['iei_createat']:NULL,
							':mac_made_usuer' => isset($Data['iei_createby'])?$Data['iei_createby']:NULL,
							':mac_update_date' => date("Y-m-d"),
							':mac_update_user' => isset($Data['iei_createby'])?$Data['iei_createby']:NULL
					));


					if(is_numeric($resInsertAsiento) && $resInsertAsiento > 0){
							// Se verifica que el detalle no de error insertando //
					}else{

							// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
							// se retorna el error y se detiene la ejecucion del codigo restante.
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $resInsertAsiento,
									'mensaje'	=> 'No se pudo registrar la salida de inventario'
								);

								 $this->response($respuesta);

								 return;
					}



					// Se actualiza la serie de la numeracion del documento
					$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
					$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
							':pgs_nextnum' => $DocNumVerificado,
							':pgs_id'      => $Data['iei_series']
					));


					if(is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1){

					}else{
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resActualizarNumeracion,
									'mensaje'	=> 'No se pudo crear la entrada  '
								);

								$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

								return;
					}
					// Fin de la actualizacion de la numeracion del documento



          foreach ($ContenidoDetalle as $key => $detail) {

                $sqlInsertDetail = "INSERT INTO iei1 (ei1_docentry, ei1_itemcode, ei1_itemname, ei1_quantity, ei1_uom, ei1_whscode, ei1_price, ei1_vat, ei1_vatsum, ei1_discount, ei1_linetotal,
																		ei1_costcode, ei1_ubusiness,ei1_project, ei1_acctcode, ei1_basetype, ei1_doctype, ei1_avprice, ei1_inventory,  ei1_linenum, ei1_acciva,ei1_concept)
                                    VALUES
                                    (:ei1_docentry, :ei1_itemcode, :ei1_itemname, :ei1_quantity, :ei1_uom, :ei1_whscode, :ei1_price, :ei1_vat, :ei1_vatsum, :ei1_discount, :ei1_linetotal,
																		 :ei1_costcode, :ei1_ubusiness,:ei1_project, :ei1_acctcode, :ei1_basetype, :ei1_doctype, :ei1_avprice, :ei1_inventory, :ei1_linenum, :ei1_acciva,:ei1_concept)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(

                                    ':ei1_docentry'  => $resInsert,
                                    ':ei1_itemcode' => isset($detail['ei1_itemcode'])?$detail['ei1_itemcode']:NULL,
                                    ':ei1_itemname' => isset($detail['ei1_itemname'])?$detail['ei1_itemname']:NULL,
                                    ':ei1_quantity' => is_numeric($detail['ei1_quantity'])?$detail['ei1_quantity']:0,
                                    ':ei1_uom' => isset($detail['ei1_uom'])?$detail['ei1_uom']:NULL,
                                    ':ei1_whscode' => isset($detail['ei1_whscode'])?$detail['ei1_whscode']:NULL,
                                    ':ei1_price' => is_numeric($detail['ei1_price'])?$detail['ei1_price']:0,
                                    ':ei1_vat' => is_numeric($detail['ei1_vat'])?$detail['ei1_vat']:0,
                                    ':ei1_vatsum' => is_numeric($detail['ei1_vatsum'])?$detail['ei1_vatsum']:0,
                                    ':ei1_discount' => is_numeric($detail['ei1_discount'])?$detail['ei1_discount']:0,
                                    ':ei1_linetotal' => is_numeric($detail['ei1_linetotal'])?$detail['ei1_linetotal']:0,
                                    ':ei1_costcode' => isset($detail['ei1_costcode'])?$detail['ei1_costcode']:NULL,
                                    ':ei1_ubusiness' => isset($detail['ei1_ubusiness'])?$detail['ei1_ubusiness']:NULL,
                                    ':ei1_project' => isset($detail['ei1_project'])?$detail['ei1_project']:NULL,
                                    ':ei1_acctcode' => is_numeric($detail['ei1_acctcode'])?$detail['ei1_acctcode']:0,
                                    ':ei1_basetype' => is_numeric($detail['ei1_basetype'])?$detail['ei1_basetype']:0,
                                    ':ei1_doctype' => is_numeric($detail['ei1_doctype'])?$detail['ei1_doctype']:0,
                                    ':ei1_avprice' => is_numeric($detail['ei1_avprice'])?$detail['ei1_avprice']:0,
                                    ':ei1_inventory' => is_numeric($detail['ei1_inventory'])?$detail['ei1_inventory']:0,
                                    ':ei1_linenum' => is_numeric($detail['ei1_linenum'])?$detail['ei1_linenum']:0,
                                    ':ei1_acciva'=> is_numeric($detail['ei1_acciva'])?$detail['ei1_acciva']:0,
																		':ei1_concept' => is_numeric($detail['ei1_concept'])?$detail['ei1_concept']:0
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
												'mensaje'	=> 'No se pudo registrar la entrada '
											);

											 $this->response($respuesta);

											 return;
								}

								// si el item es inventariable
								// if( $detail['ei1_articleInv'] == 1 || $detail['ei1_articleInv'] == "1" ){
								if( $detail['ei1_inventory'] == 1 || $detail['ei1_inventory'] == "1" ){

											//se busca el costo del item en el momento de la creacion del documento de venta
											// para almacenar en el movimiento de inventario

											$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode";
											$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(':bdi_whscode' => $detail['ei1_whscode'], ':bdi_itemcode' => $detail['ei1_itemcode']));


											if(isset($resCostoMomentoRegistro[0])){


												//Se aplica el movimiento de inventario
												$sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode, bmi_quantity, bmi_whscode, bmi_createat, bmi_createby, bmy_doctype, bmy_baseentry,bmi_cost)
																							 VALUES (:bmi_itemcode, :bmi_quantity, :bmi_whscode, :bmi_createat, :bmi_createby, :bmy_doctype, :bmy_baseentry, :bmi_cost)";

												$resInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(

														 ':bmi_itemcode' => isset($detail['ei1_itemcode'])?$detail['ei1_itemcode']:NULL,
														 ':bmi_quantity' => is_numeric($detail['ei1_quantity'])? $detail['ei1_quantity'] * $Data['invtype']:0,
														 ':bmi_whscode'  => isset($detail['ei1_whscode'])?$detail['ei1_whscode']:NULL,
														 ':bmi_createat' => $this->validateDate($Data['iei_createat'])?$Data['iei_createat']:NULL,
														 ':bmi_createby' => isset($Data['iei_createby'])?$Data['iei_createby']:NULL,
														 ':bmy_doctype'  => is_numeric($Data['iei_doctype'])?$Data['iei_doctype']:0,
														 ':bmy_baseentry' => $resInsert,
														 ':bmi_cost'      => $resCostoMomentoRegistro[0]['bdi_avgprice']

												));

												if(is_numeric($resInserMovimiento) && $resInserMovimiento > 0){
														// Se verifica que el detalle no de error insertando //
												}else{

														// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
														// se retorna el error y se detiene la ejecucion del codigo restante.
															$this->pedeo->trans_rollback();

															$respuesta = array(
																'error'   => true,
																'data' => $resInserMovimiento,
																'mensaje'	=> 'No se pudo registra la entrada de inventario'
															);

															 $this->response($respuesta);

															 return;
												}



											}else{

													$this->pedeo->trans_rollback();

													$respuesta = array(
														'error'   => true,
														'data' => $resCostoMomentoRegistro,
														'mensaje'	=> 'No se pudo relizar el movimiento, no se encontro el costo del articulo'
													);

													 $this->response($respuesta);

													 return;
											}

											//FIN aplicacion de movimiento de inventario


												//Se Aplica el movimiento en stock ***************
												// Buscando item en el stock
												$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
																							FROM tbdi
																							WHERE bdi_itemcode = :bdi_itemcode
																							AND bdi_whscode = :bdi_whscode";

												$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(

															':bdi_itemcode' => $detail['ei1_itemcode'],
															':bdi_whscode'  => $detail['ei1_whscode']
												));

												if(isset($resCostoCantidad[0])){

													if($resCostoCantidad[0]['bdi_quantity'] > 0){

															 $CantidadActual = $resCostoCantidad[0]['bdi_quantity'];
															 $CantidadNueva = $detail['ei1_quantity'];


															 $CantidadTotal = ($CantidadActual + $CantidadNueva);

															 $sqlUpdateCostoCantidad =  "UPDATE tbdi
																													 SET bdi_quantity = :bdi_quantity
																													 WHERE  bdi_id = :bdi_id";

															 $resUpdateCostoCantidad = $this->pedeo->updateRow($sqlUpdateCostoCantidad, array(

																		 ':bdi_quantity' => $CantidadTotal,
																		 ':bdi_id' 			 => $resCostoCantidad[0]['bdi_id']
															 ));

															 if(is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad == 1){

															 }else{

																	 $this->pedeo->trans_rollback();

																	 $respuesta = array(
																		 'error'   => true,
																		 'data'    => $resUpdateCostoCantidad,
																		 'mensaje'	=> 'No se pudo registrar el movimiento en el stock'
																	 );


																	 $this->response($respuesta);

																	 return;
															 }

													}else{

																	 $this->pedeo->trans_rollback();

																	 $respuesta = array(
																		 'error'   => true,
																		 'data'    => $resUpdateCostoCantidad,
																		 'mensaje' => 'No hay existencia para el item: '.$detail['ei1_itemcode']
																	 );


																	 $this->response($respuesta);

																	 return;
													}

												}else{

															$this->pedeo->trans_rollback();

															$respuesta = array(
																'error'   => true,
																'data' 		=> $resInsertCostoCantidad,
																'mensaje'	=> 'El item no existe en el stock '.$detail['ei1_itemcode']
															);

															 $this->response($respuesta);

															 return;
												}

													//FIN de  Aplicacion del movimiento en stock

								}


								//LLENANDO AGRUPADOS
								$DetalleCuentaLineaDocumento = new stdClass();
								$DetalleCuentaGrupo = new stdClass();

								$DetalleCuentaLineaDocumento->ei1_acctcode = is_numeric($detail['ei1_acctcode'])?$detail['ei1_acctcode']: 0;
								$DetalleCuentaLineaDocumento->ei1_costcode = isset($detail['ei1_costcode'])?$detail['ei1_costcode']:NULL;
								$DetalleCuentaLineaDocumento->ei1_ubusiness = isset($detail['ei1_ubusiness'])?$detail['ei1_ubusiness']:NULL;
								$DetalleCuentaLineaDocumento->ei1_project = isset($detail['ei1_project'])?$detail['ei1_project']:NULL;
								$DetalleCuentaLineaDocumento->ei1_linetotal = is_numeric($detail['ei1_linetotal'])?$detail['ei1_linetotal']:0;
								$DetalleCuentaLineaDocumento->ei1_vat = is_numeric($detail['ei1_vat'])?$detail['ei1_vat']:0;
								$DetalleCuentaLineaDocumento->ei1_vatsum = is_numeric($detail['ei1_vatsum'])?$detail['ei1_vatsum']:0;
								$DetalleCuentaLineaDocumento->ei1_price = is_numeric($detail['ei1_price'])?$detail['ei1_price']:0;
								$DetalleCuentaLineaDocumento->ei1_itemcode = isset($detail['ei1_itemcode'])?$detail['ei1_itemcode']:NULL;
								$DetalleCuentaLineaDocumento->ei1_quantity = is_numeric($detail['ei1_quantity'])?$detail['ei1_quantity']:0;
								$DetalleCuentaLineaDocumento->ei1_whscode = isset($detail['ei1_whscode'])?$detail['ei1_whscode']:NULL;




								$codigoCuenta = substr($DetalleCuentaLineaDocumento->ei1_acctcode, 0, 1);
								$llaveCuentaLineaDocumento = $DetalleCuentaLineaDocumento->ei1_ubusiness.$DetalleCuentaLineaDocumento->ei1_costcode.$DetalleCuentaLineaDocumento->ei1_project.$DetalleCuentaLineaDocumento->ei1_acctcode;

								$DetalleCuentaLineaDocumento->codigoCuenta = $codigoCuenta;

								//-***************************
								if(in_array( $llaveCuentaLineaDocumento, $inArrayCuentaLineaDocumento )){

										$posicionCuentaLineaDocumento = $this->buscarPosicion( $llaveCuentaLineaDocumento, $inArrayCuentaLineaDocumento );

								}else{

										array_push( $inArrayCuentaLineaDocumento, $llaveCuentaLineaDocumento );
										$posicionCuentaLineaDocumento = $this->buscarPosicion( $llaveCuentaLineaDocumento, $inArrayCuentaLineaDocumento );

								}


								if( isset($DetalleConsolidadoCuentaLineaDocumento[$posicionCuentaLineaDocumento])){

									if(!is_array($DetalleConsolidadoCuentaLineaDocumento[$posicionCuentaLineaDocumento])){
										$DetalleConsolidadoCuentaLineaDocumento[$posicionCuentaLineaDocumento] = array();
									}

								}else{
									$DetalleConsolidadoCuentaLineaDocumento[$posicionCuentaLineaDocumento] = array();
								}

								array_push( $DetalleConsolidadoCuentaLineaDocumento[$posicionCuentaLineaDocumento], $DetalleCuentaLineaDocumento);
								//****************************-

								$DetalleCuentaGrupo->ei1_itemcode = isset($detail['ei1_itemcode'])?$detail['ei1_itemcode']:NULL;
								$DetalleCuentaGrupo->ei1_acctcode = is_numeric($detail['ei1_acctcode'])?$detail['ei1_acctcode']: 0;
								$DetalleCuentaGrupo->ei1_quantity = is_numeric($detail['ei1_quantity'])?$detail['ei1_quantity']:0;

								$llaveCuentaGrupo = $DetalleCuentaGrupo->ei1_acctcode;
								//********************************
								if(in_array( $llaveCuentaGrupo, $inArrayCuentaGrupo )){

										$posicionCuentaGrupo = $this->buscarPosicion( $llaveCuentaGrupo, $inArrayCuentaGrupo );

								}else{

										array_push( $inArrayCuentaGrupo, $llaveCuentaGrupo );
										$posicionCuentaGrupo = $this->buscarPosicion( $llaveCuentaGrupo, $inArrayCuentaGrupo );

								}

								if( isset($DetalleConsolidadoCuentaGrupo[$posicionCuentaGrupo])){

									if(!is_array($DetalleConsolidadoCuentaGrupo[$posicionCuentaGrupo])){
										$DetalleConsolidadoCuentaGrupo[$posicionCuentaGrupo] = array();
									}

								}else{
									$DetalleConsolidadoCuentaGrupo[$posicionCuentaGrupo] = array();
								}

								array_push( $DetalleConsolidadoCuentaGrupo[$posicionCuentaGrupo], $DetalleCuentaGrupo);

								//*******************************************

          }

					//FIN DETALLE ENTRADA




					//PROCEDIMIENTO PARA LLENAR CUENTA LINEA DOCUMENTO

					foreach ($DetalleConsolidadoCuentaLineaDocumento as $key => $posicion) {
							$grantotalLinea = 0;
							$grantotalLineaOriginal = 0;
							$codigoCuentaLinea = "";
							$cuenta = "";
							$proyecto = "";
							$prc = "";
							$unidad = "";
							foreach ($posicion as $key => $value) {
										$grantotalLinea = ( $grantotalLinea + $value->ei1_linetotal );
										$codigoCuentaLinea = $value->codigoCuenta;
										$prc = $value->ei1_costcode;
										$unidad = $value->ei1_ubusiness;
										$proyecto = $value->ei1_project;
										$cuenta = $value->ei1_acctcode;
							}


							$debito = 0;
							$credito = 0;
							$MontoSysDB = 0;
							$MontoSysCR = 0;
							$grantotalLineaOriginal = $grantotalLinea;



							switch ($codigoCuentaLinea) {
								case 1:
									$credito = $grantotalLinea;
									$MontoSysCR = ($credito / $TasaLocSys);
									break;
								case 2:
									$credito = $grantotalLinea;
									$MontoSysCR = ($credito / $TasaLocSys);
									break;
								case 3:
									$credito = $grantotalLinea;
									$MontoSysCR = ($credito / $TasaLocSys);
									break;
								case 4:
									$credito = $grantotalLinea;
									$MontoSysCR = ($credito / $TasaLocSys);
									break;
								case 5:
									$credito = $grantotalLinea;
									$MontoSysCR = ($credito / $TasaLocSys);
									break;
								case 6:
									$credito = $grantotalLinea;
									$MontoSysCR = ($credito / $TasaLocSys);
									break;
								case 7:
									$credito = $grantotalLinea;
									$MontoSysCR = ($credito / $TasaLocSys);
									break;
							}


							$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

									':ac1_trans_id' => $resInsertAsiento,
									':ac1_account' => $cuenta,
									':ac1_debit' => $debito,
									':ac1_credit' => $credito,
									':ac1_debit_sys' => round($MontoSysDB,2),
									':ac1_credit_sys' => round($MontoSysCR,2),
									':ac1_currex' => 0,
									':ac1_doc_date' => $this->validateDate($Data['iei_docdate'])?$Data['iei_docdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['iei_duedate'])?$Data['iei_duedate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => $resInsert,
									':ac1_font_line' => 1,
									':ac1_font_type' => is_numeric($Data['iei_doctype'])?$Data['iei_doctype']:0,
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
									':ac1_made_user' => isset($Data['iei_createby'])?$Data['iei_createby']:NULL,
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
									':ac1_legal_num' => isset($Data['iei_cardcode'])?$Data['iei_cardcode']:NULL,
									':ac1_codref' => 1
						));



						if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
						}else{
								// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resDetalleAsiento,
										'mensaje'	=> 'No se pudo registrar la entrada de inventariio'
									);

									 $this->response($respuesta);

									 return;
						}
					}
					//FIN PROCEDIMIENTO PARA LLENAR CUENTA LINEA DOCUMENTO


					// PROCEDIMIENTO PARA LLENAR CUENTA GRUPO
					foreach ($DetalleConsolidadoCuentaGrupo as $key => $posicion) {
							$grantotalCuentaGrupo = 0 ;
							$grantotalCuentaGrupoOriginal = 0;
							$cuentaGrupo = "";
							foreach ($posicion as $key => $value) {

										$sqlArticulo = "SELECT f2.dma_item_code,  f1.mga_acct_inv, f1.mga_acct_cost FROM dmga f1 JOIN dmar f2 ON f1.mga_id  = f2.dma_group_code WHERE dma_item_code = :dma_item_code";

										$resArticulo = $this->pedeo->queryTable($sqlArticulo, array(":dma_item_code" => $value->ei1_itemcode));

										if(isset($resArticulo[0])){
												$dbito = 0;
												$cdito = 0;

												$MontoSysDB = 0;
												$MontoSysCR = 0;

												$sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode";

												$resCosto = $this->pedeo->queryTable($sqlCosto, array(":bdi_itemcode" => $value->ei1_itemcode));

												if( isset( $resCosto[0] ) ){

															$cuentaGrupo = $resArticulo[0]['mga_acct_inv'];


															$costoArticulo = $resCosto[0]['bdi_avgprice'];
															$cantidadArticulo = $value->ei1_quantity;
															$grantotalCuentaGrupo = ($grantotalCuentaGrupo + ($costoArticulo * $cantidadArticulo));

												}else{

															$this->pedeo->trans_rollback();

															$respuesta = array(
																'error'   => true,
																'data'	  => $resArticulo,
																'mensaje'	=> 'No se encontro el costo para el item: '.$value->ei1_itemcode
															);

															 $this->response($respuesta);

															 return;
												}

										}else{
												// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
												// se retorna el error y se detiene la ejecucion del codigo restante.
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data'	  => $resArticulo,
													'mensaje'	=> 'No se encontro la cuenta del grupo de articulo para el item '.$value->ei1_itemcode
												);

												 $this->response($respuesta);

												 return;
										}
							}

							$codigo3 = substr($cuentaGrupo, 0, 1);

							$grantotalCuentaGrupoOriginal = $grantotalCuentaGrupo;


							if( $codigo3 == 1 || $codigo3 == "1" ){
									$dbito = $grantotalCuentaGrupo;
									$MontoSysDB = ($dbito / $TasaLocSys);
							}else if( $codigo3 == 2 || $codigo3 == "2" ){
									$dbito = $grantotalCuentaGrupo;
									$MontoSysDB = ($dbito / $TasaLocSys);
							}else if( $codigo3 == 3 || $codigo3 == "3" ){
									$dbito = $grantotalCuentaGrupo;
									$MontoSysDB = ($dbito / $TasaLocSys);
							}else if( $codigo3 == 4 || $codigo3 == "4" ){
									$dbito = $grantotalCuentaGrupo;
									$MontoSysDB = ($dbito / $TasaLocSys);
							}else if( $codigo3 == 5  || $codigo3 == "5" ){
									$dbito = $grantotalCuentaGrupo;
									$MontoSysDB = ($dbito / $TasaLocSys);
							}else if( $codigo3 == 6 || $codigo3 == "6" ){
									$dbito = $grantotalCuentaGrupo;
									$MontoSysDB = ($dbito / $TasaLocSys);
							}else if( $codigo3 == 7 || $codigo3 == "7" ){
									$dbito = $grantotalCuentaGrupo;
									$MontoSysDB = ($dbito / $TasaLocSys);
							}

							$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

									':ac1_trans_id' => $resInsertAsiento,
									':ac1_account' => $cuentaGrupo,
									':ac1_debit' => $dbito,
									':ac1_credit' => $cdito,
									':ac1_debit_sys' => round($MontoSysDB,2),
									':ac1_credit_sys' => round($MontoSysCR,2),
									':ac1_currex' => 0,
									':ac1_doc_date' => $this->validateDate($Data['iei_docdate'])?$Data['iei_docdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['iei_duedate'])?$Data['iei_duedate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => $resInsert,
									':ac1_font_line' => 1,
									':ac1_font_type' => is_numeric($Data['iei_doctype'])?$Data['iei_doctype']:0,
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
									':ac1_made_user' => isset($Data['iei_createby'])?$Data['iei_createby']:NULL,
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
									':ac1_legal_num' => isset($Data['iei_cardcode'])?$Data['iei_cardcode']:NULL,
									':ac1_codref' => 1
						));

						if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
						}else{

								// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resDetalleAsiento,
										'mensaje'	=> 'No se pudo registrar la entrada de inventario'
									);

									 $this->response($respuesta);

									 return;
						}

					}
					//FIN PROCEDIMIENTO PARA LLENAR CUENTA GRUPO



					// Si todo sale bien despues de insertar el detalle de la entrada de
					// se confirma la trasaccion  para que los cambios apliquen permanentemente
					// en la base de datos y se confirma la operacion exitosa.
					$this->pedeo->trans_commit();

          $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' =>'Entrada registrada con exito'
          );


        }else{
					// Se devuelven los cambios realizados en la transaccion
					// si occurre un error  y se muestra devuelve el error.
							$this->pedeo->trans_rollback();

              $respuesta = array(
                'error'   => true,
                'data' => $resInsert,
                'mensaje'	=> 'No se pudo registrar la entrada'
              );

        }

         $this->response($respuesta);
	}



  //OBTENER ENTRADAS DE
  public function getInventoryEntry_get(){

        $sqlSelect = "SELECT
											t2.mdt_docname,
											t0.iei_docnum,
											t0.iei_docdate,
											t0.iei_cardname,
											t0.iei_comment,
											CONCAT(T0.iei_currency,' ',to_char(t0.iei_doctotal,'999,999,999,999.00')) iei_doctotal,
											t1.mev_names iei_slpcode
										 FROM miei t0
										 LEFT JOIN dmev t1 on t0.iei_slpcode = t1.mev_id
										 LEFT JOIN dmdt t2 on t0.iei_doctype = t2.mdt_doctype";

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


	//OBTENER ENTRADA DE  POR ID
	public function getInventoryEntryById_get(){

				$Data = $this->get();

				if(!isset($Data['iei_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM miei WHERE iei_docentry =:iei_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":iei_docentry" => $Data['iei_docentry']));

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


	//OBTENER ENTADA DE  DETALLE POR ID
	public function getInventoryEntryDetail_get(){

				$Data = $this->get();

				if(!isset($Data['ei1_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM iei WHERE ei1_docentry =:ei1_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":ei1_docentry" => $Data['ei1_docentry']));

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



	//OBTENER ENTRADAS DE  POR SOCIO DE NEGOCIO
	public function getInventoryEntryBySN_get(){

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

				$sqlSelect = " SELECT * FROM miei WHERE iei_cardcode =:iei_cardcode";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":iei_cardcode" => $Data['dms_card_code']));

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









	private function getUrl($data, $caperta){
      $url = "";

      if ($data == NULL){

        return $url;

      }

			$ruta = '/var/www/html/'.$caperta.'/assets/img/anexos/';

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




}
