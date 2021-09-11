<?php
// Nota crédito de clientesES
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class SalesNc extends REST_Controller {

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

  //CREAR NUEVA Nota crédito de clientes
	public function createSalesNc_post(){

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
            'mensaje' =>'No se encontro el detalle de la Nota crédito de clientes'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }
				//BUSCANDO LA NUMERACION DEL DOCUMENTO
			  $sqlNumeracion = " SELECT pgs_nextnum,pgs_last_num FROM  pgdn WHERE pgs_id = :pgs_id";

				$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['vnc_series']));

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


				$sqlMonedaSys = "SELECT tasa.tsa_value
													FROM  pgec
													INNER JOIN tasa
													ON trim(tasa.tsa_currd) = trim(pgec.pgm_symbol)
													WHERE pgec.pgm_system = :pgm_system AND tasa.tsa_date = :tsa_date";

				$resMonedaSys = $this->pedeo->queryTable($sqlMonedaSys, array(':pgm_system' => 1, ':tsa_date' => $Data['vnc_docdate']));

				if(isset($resMonedaSys[0])){

				}else{
						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encontro la moneda de sistema para el documento'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}

        $sqlInsert = "INSERT INTO dvnc(vnc_series, vnc_docnum, vnc_docdate, vnc_duedate, vnc_duedev, vnc_pricelist, vnc_cardcode,
                      vnc_cardname, vnc_currency, vnc_contacid, vnc_slpcode, vnc_empid, vnc_comment, vnc_doctotal, vnc_baseamnt, vnc_taxtotal,
                      vnc_discprofit, vnc_discount, vnc_createat, vnc_baseentry, vnc_basetype, vnc_doctype, vnc_idadd, vnc_adress, vnc_paytype,
                      vnc_attch,vnc_createby)VALUES(:vnc_series, :vnc_docnum, :vnc_docdate, :vnc_duedate, :vnc_duedev, :vnc_pricelist, :vnc_cardcode, :vnc_cardname,
                      :vnc_currency, :vnc_contacid, :vnc_slpcode, :vnc_empid, :vnc_comment, :vnc_doctotal, :vnc_baseamnt, :vnc_taxtotal, :vnc_discprofit, :vnc_discount,
                      :vnc_createat, :vnc_baseentry, :vnc_basetype, :vnc_doctype, :vnc_idadd, :vnc_adress, :vnc_paytype, :vnc_attch,:vnc_createby)";


				// Se Inicia la transaccion,
				// Todas las consultas de modificacion siguientes
				// aplicaran solo despues que se confirme la transaccion,
				// de lo contrario no se aplicaran los cambios y se devolvera
				// la base de datos a su estado original.

			  $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':vnc_docnum' => $DocNumVerificado,
              ':vnc_series' => is_numeric($Data['vnc_series'])?$Data['vnc_series']:0,
              ':vnc_docdate' => $this->validateDate($Data['vnc_docdate'])?$Data['vnc_docdate']:NULL,
              ':vnc_duedate' => $this->validateDate($Data['vnc_duedate'])?$Data['vnc_duedate']:NULL,
              ':vnc_duedev' => $this->validateDate($Data['vnc_duedev'])?$Data['vnc_duedev']:NULL,
              ':vnc_pricelist' => is_numeric($Data['vnc_pricelist'])?$Data['vnc_pricelist']:0,
              ':vnc_cardcode' => isset($Data['vnc_cardcode'])?$Data['vnc_cardcode']:NULL,
              ':vnc_cardname' => isset($Data['vnc_cardname'])?$Data['vnc_cardname']:NULL,
              ':vnc_currency' => is_numeric($Data['vnc_currency'])?$Data['vnc_currency']:0,
              ':vnc_contacid' => isset($Data['vnc_contacid'])?$Data['vnc_contacid']:NULL,
              ':vnc_slpcode' => is_numeric($Data['vnc_slpcode'])?$Data['vnc_slpcode']:0,
              ':vnc_empid' => is_numeric($Data['vnc_empid'])?$Data['vnc_empid']:0,
              ':vnc_comment' => isset($Data['vnc_comment'])?$Data['vnc_comment']:NULL,
              ':vnc_doctotal' => is_numeric($Data['vnc_doctotal'])?$Data['vnc_doctotal']:0,
              ':vnc_baseamnt' => is_numeric($Data['vnc_baseamnt'])?$Data['vnc_baseamnt']:0,
              ':vnc_taxtotal' => is_numeric($Data['vnc_taxtotal'])?$Data['vnc_taxtotal']:0,
              ':vnc_discprofit' => is_numeric($Data['vnc_discprofit'])?$Data['vnc_discprofit']:0,
              ':vnc_discount' => is_numeric($Data['vnc_discount'])?$Data['vnc_discount']:0,
              ':vnc_createat' => $this->validateDate($Data['vnc_createat'])?$Data['vnc_createat']:NULL,
              ':vnc_baseentry' => is_numeric($Data['vnc_baseentry'])?$Data['vnc_baseentry']:0,
              ':vnc_basetype' => is_numeric($Data['vnc_basetype'])?$Data['vnc_basetype']:0,
              ':vnc_doctype' => is_numeric($Data['vnc_doctype'])?$Data['vnc_doctype']:0,
              ':vnc_idadd' => isset($Data['vnc_idadd'])?$Data['vnc_idadd']:NULL,
              ':vnc_adress' => isset($Data['vnc_adress'])?$Data['vnc_adress']:NULL,
              ':vnc_paytype' => is_numeric($Data['vnc_paytype'])?$Data['vnc_paytype']:0,
							':vnc_createby' => isset($Data['vnc_createby'])?$Data['vnc_createby']:NULL,
              ':vnc_attch' => $this->getUrl(count(trim(($Data['vnc_attch']))) > 0 ? $Data['vnc_attch']:NULL)
						));

        if(is_numeric($resInsert) && $resInsert > 0){

					// Se actualiza la serie de la numeracion del documento

					$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
					$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
							':pgs_nextnum' => $DocNumVerificado,
							':pgs_id'      => $Data['vnc_series']
					));


					if(is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1){

					}else{
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resActualizarNumeracion,
									'mensaje'	=> 'No se pudo crear la Nota crédito de clientes'
								);

								$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

								return;
					}
					// Fin de la actualizacion de la numeracion del documento


					//Se agregan los asientos contables*/*******

					$sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user)
															 VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user)";


					$resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

							':mac_doc_num' => 1,
							':mac_status' => 1,
							':mac_base_type' => is_numeric($Data['vnc_doctype'])?$Data['vnc_doctype']:0,
							':mac_base_entry' => $resInsert,
							':mac_doc_date' => $this->validateDate($Data['vnc_docdate'])?$Data['vnc_docdate']:NULL,
							':mac_doc_duedate' => $this->validateDate($Data['vnc_duedate'])?$Data['vnc_duedate']:NULL,
							':mac_legal_date' => $this->validateDate($Data['vnc_docdate'])?$Data['vnc_docdate']:NULL,
							':mac_ref1' => is_numeric($Data['vnc_doctype'])?$Data['vnc_doctype']:0,
							':mac_ref2' => "",
							':mac_ref3' => "",
							':mac_loc_total' => is_numeric($Data['vnc_doctotal'])?$Data['vnc_doctotal']:0,
							':mac_fc_total' => is_numeric($Data['vnc_doctotal'])?$Data['vnc_doctotal']:0,
							':mac_sys_total' => is_numeric($Data['vnc_doctotal'])?$Data['vnc_doctotal']:0,
							':mac_trans_dode' => 1,
							':mac_beline_nume' => 1,
							':mac_vat_date' => $this->validateDate($Data['vnc_docdate'])?$Data['vnc_docdate']:NULL,
							':mac_serie' => 1,
							':mac_number' => 1,
							':mac_bammntsys' => is_numeric($Data['vnc_baseamnt'])?$Data['vnc_baseamnt']:0,
							':mac_bammnt' => is_numeric($Data['vnc_baseamnt'])?$Data['vnc_baseamnt']:0,
							':mac_wtsum' => 1,
							':mac_vatsum' => is_numeric($Data['vnc_taxtotal'])?$Data['vnc_taxtotal']:0,
							':mac_comments' => isset($Data['vnc_comment'])?$Data['vnc_comment']:NULL,
							':mac_create_date' => $this->validateDate($Data['vnc_createat'])?$Data['vnc_createat']:NULL,
							':mac_made_usuer' => isset($Data['vnc_createby'])?$Data['vnc_createby']:NULL,
							':mac_update_date' => date("Y-m-d"),
							':mac_update_user' => isset($Data['vnc_createby'])?$Data['vnc_createby']:NULL
					));


					if(is_numeric($resInsertAsiento) && $resInsertAsiento > 0){
							// Se verifica que el detalle no de error insertando //
					}else{

							// si falla algun insert del detalle de la Nota crédito de clientes se devuelven los cambios realizados por la transaccion,
							// se retorna el error y se detiene la ejecucion del codigo restante.
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $resInsertAsiento,
									'mensaje'	=> 'No se pudo registrar la Nota crédito de clientes'
								);

								 $this->response($respuesta);

								 return;
					}


          foreach ($ContenidoDetalle as $key => $detail) {

                $sqlInsertDetail = "INSERT INTO vnc1(nc1_docentry, nc1_itemcode, nc1_itemname, nc1_quantity, nc1_uom, nc1_whscode,
                                    nc1_price, nc1_vat, nc1_vatsum, nc1_discount, nc1_linetotal, nc1_costcode, nc1_ubusiness, nc1_project,
                                    nc1_acctcode, nc1_basetype, nc1_doctype, nc1_avprice, nc1_inventory)VALUES(:nc1_docentry, :nc1_itemcode, :nc1_itemname, :nc1_quantity,
                                    :nc1_uom, :nc1_whscode,:nc1_price, :nc1_vat, :nc1_vatsum, :nc1_discount, :nc1_linetotal, :nc1_costcode, :nc1_ubusiness, :nc1_project,
                                    :nc1_acctcode, :nc1_basetype, :nc1_doctype, :nc1_avprice, :nc1_inventory)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':nc1_docentry' => $resInsert,
                        ':nc1_itemcode' => isset($detail['nc1_itemcode'])?$detail['nc1_itemcode']:NULL,
                        ':nc1_itemname' => isset($detail['nc1_itemname'])?$detail['nc1_itemname']:NULL,
                        ':nc1_quantity' => is_numeric($detail['nc1_quantity'])?$detail['nc1_quantity']:0,
                        ':nc1_uom' => isset($detail['nc1_uom'])?$detail['nc1_uom']:NULL,
                        ':nc1_whscode' => isset($detail['nc1_whscode'])?$detail['nc1_whscode']:NULL,
                        ':nc1_price' => is_numeric($detail['nc1_price'])?$detail['nc1_price']:0,
                        ':nc1_vat' => is_numeric($detail['nc1_vat'])?$detail['nc1_vat']:0,
                        ':nc1_vatsum' => is_numeric($detail['nc1_vatsum'])?$detail['nc1_vatsum']:0,
                        ':nc1_discount' => is_numeric($detail['nc1_discount'])?$detail['nc1_discount']:0,
                        ':nc1_linetotal' => is_numeric($detail['nc1_linetotal'])?$detail['nc1_linetotal']:0,
                        ':nc1_costcode' => isset($detail['nc1_costcode'])?$detail['nc1_costcode']:NULL,
                        ':nc1_ubusiness' => isset($detail['nc1_ubusiness'])?$detail['nc1_ubusiness']:NULL,
                        ':nc1_project' => isset($detail['nc1_project'])?$detail['nc1_project']:NULL,
                        ':nc1_acctcode' => is_numeric($detail['nc1_acctcode'])?$detail['nc1_acctcode']:0,
                        ':nc1_basetype' => is_numeric($detail['nc1_basetype'])?$detail['nc1_basetype']:0,
                        ':nc1_doctype' => is_numeric($detail['nc1_doctype'])?$detail['nc1_doctype']:0,
                        ':nc1_avprice' => is_numeric($detail['nc1_avprice'])?$detail['nc1_avprice']:0,
                        ':nc1_inventory' => is_numeric($detail['nc1_inventory'])?$detail['nc1_inventory']:NULL
                ));

								if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
										// Se verifica que el detalle no de error insertando //
								}else{

										// si falla algun insert del detalle de la Nota crédito de clientes se devuelven los cambios realizados por la transaccion,
										// se retorna el error y se detiene la ejecucion del codigo restante.
											$this->pedeo->trans_rollback();

											$respuesta = array(
												'error'   => true,
												'data' => $resInsert,
												'mensaje'	=> 'No se pudo registrar la Nota crédito de clientes'
											);

											 $this->response($respuesta);

											 return;
								}

								// si el item es inventariable
								if( $detail['nc1_articleInv'] == 1 || $detail['nc1_articleInv'] == "1" ){
										//Se aplica el movimiento de inventario
										$sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode, bmi_quantity, bmi_whscode, bmi_createat, bmi_createby, bmy_doctype, bmy_baseentry)
																					 VALUES (:bmi_itemcode, :bmi_quantity, :bmi_whscode, :bmi_createat, :bmi_createby, :bmy_doctype, :bmy_baseentry)";

										$sqlInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(

												 ':bmi_itemcode' => isset($detail['nc1_itemcode'])?$detail['nc1_itemcode']:NULL,
												 ':bmi_quantity' => is_numeric($detail['nc1_quantity'])? $detail['nc1_quantity'] * $Data['invtype']:0,
												 ':bmi_whscode'  => isset($detail['nc1_whscode'])?$detail['nc1_whscode']:NULL,
												 ':bmi_createat' => $this->validateDate($Data['vnc_createat'])?$Data['vnc_createat']:NULL,
												 ':bmi_createby' => isset($Data['vnc_createby'])?$Data['vnc_createby']:NULL,
												 ':bmy_doctype'  => is_numeric($Data['vnc_doctype'])?$Data['vnc_doctype']:0,
												 ':bmy_baseentry' => $resInsert

										));

										if(is_numeric($sqlInserMovimiento) && $sqlInserMovimiento > 0){
												// Se verifica que el detalle no de error insertando //
										}else{

												// si falla algun insert del detalle de la Nota crédito de clientes se devuelven los cambios realizados por la transaccion,
												// se retorna el error y se detiene la ejecucion del codigo restante.
													$this->pedeo->trans_rollback();

													$respuesta = array(
														'error'   => true,
														'data' => $sqlInserMovimiento,
														'mensaje'	=> 'No se pudo registrar la Nota crédito de clientes'
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

														':bdi_itemcode' => $detail['nc1_itemcode'],
														':bdi_whscode'  => $detail['nc1_whscode']
											));

											if(isset($resCostoCantidad[0])){

												if($resCostoCantidad[0]['bdi_quantity'] > 0){

														 $CantidadActual = $resCostoCantidad[0]['bdi_quantity'];
														 $CantidadNueva = $detail['nc1_quantity'];


														 $CantidadTotal = ($CantidadActual - $CantidadNueva);

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
																	 'mensaje'	=> 'No se pudo crear la Nota crédito de clientes'
																 );
														 }

												}else{

																 $this->pedeo->trans_rollback();

																 $respuesta = array(
																	 'error'   => true,
																	 'data'    => $resUpdateCostoCantidad,
																	 'mensaje' => 'No hay existencia para el item: '.$detail['nc1_itemcode']
																 );
												}

											}else{

														$this->pedeo->trans_rollback();

														$respuesta = array(
															'error'   => true,
															'data' 		=> $resInsertCostoCantidad,
															'mensaje'	=> 'El item no existe en el stock '.$detail['nc1_itemcode']
														);

														 $this->response($respuesta);

														 return;
											}

												//FIN de  Aplicacion del movimiento en stock
								}




								//LLENANDO DETALLE ASIENTO CONTABLES



								$DetalleAsientoIngreso = new stdClass();
								$DetalleAsientoIva = new stdClass();
								$DetalleCostoInventario = new stdClass();
								$DetalleCostoCosto = new stdClass();


								$DetalleAsientoIngreso->ac1_account = is_numeric($detail['nc1_acctcode'])?$detail['nc1_acctcode']: 0;
								$DetalleAsientoIngreso->ac1_prc_code = isset($detail['nc1_costcode'])?$detail['nc1_costcode']:NULL;
								$DetalleAsientoIngreso->ac1_uncode = isset($detail['nc1_ubusiness'])?$detail['nc1_ubusiness']:NULL;
								$DetalleAsientoIngreso->ac1_prj_code = isset($detail['nc1_project'])?$detail['nc1_project']:NULL;
								$DetalleAsientoIngreso->nc1_linetotal = is_numeric($detail['nc1_linetotal'])?$detail['nc1_linetotal']:0;
								$DetalleAsientoIngreso->nc1_vat = is_numeric($detail['nc1_vat'])?$detail['nc1_vat']:0;
								$DetalleAsientoIngreso->nc1_vatsum = is_numeric($detail['nc1_vatsum'])?$detail['nc1_vatsum']:0;
								$DetalleAsientoIngreso->nc1_price = is_numeric($detail['nc1_price'])?$detail['nc1_price']:0;
								$DetalleAsientoIngreso->nc1_itemcode = isset($detail['nc1_itemcode'])?$detail['nc1_itemcode']:NULL;
								$DetalleAsientoIngreso->nc1_quantity = is_numeric($detail['nc1_quantity'])?$detail['nc1_quantity']:0;



								$DetalleAsientoIva->ac1_account = is_numeric($detail['nc1_acctcode'])?$detail['nc1_acctcode']: 0;
								$DetalleAsientoIva->ac1_prc_code = isset($detail['nc1_costcode'])?$detail['nc1_costcode']:NULL;
								$DetalleAsientoIva->ac1_uncode = isset($detail['nc1_ubusiness'])?$detail['nc1_ubusiness']:NULL;
								$DetalleAsientoIva->ac1_prj_code = isset($detail['nc1_project'])?$detail['nc1_project']:NULL;
								$DetalleAsientoIva->nc1_linetotal = is_numeric($detail['nc1_linetotal'])?$detail['nc1_linetotal']:0;
								$DetalleAsientoIva->nc1_vat = is_numeric($detail['nc1_vat'])?$detail['nc1_vat']:0;
								$DetalleAsientoIva->nc1_vatsum = is_numeric($detail['nc1_vatsum'])?$detail['nc1_vatsum']:0;
								$DetalleAsientoIva->nc1_price = is_numeric($detail['nc1_price'])?$detail['nc1_price']:0;
								$DetalleAsientoIva->nc1_itemcode = isset($detail['nc1_itemcode'])?$detail['nc1_itemcode']:NULL;
								$DetalleAsientoIva->nc1_quantity = is_numeric($detail['nc1_quantity'])?$detail['nc1_quantity']:0;
								$DetalleAsientoIva->nc1_cuentaIva = is_numeric($detail['nc1_cuentaIva'])?$detail['nc1_cuentaIva']:NULL;



								// se busca la cuenta contable del costoInventario y costoCosto
								$sqlArticulo = "SELECT f2.dma_item_code,  f1.mga_acct_inv, f1.mga_acct_cost FROM dmga f1 JOIN dmar f2 ON f1.mga_id  = f2.dma_group_code WHERE dma_item_code = :dma_item_code";

								$resArticulo = $this->pedeo->queryTable($sqlArticulo, array(":dma_item_code" => $detail['nc1_itemcode']));

								if(!isset($resArticulo[0])){

											$this->pedeo->trans_rollback();

											$respuesta = array(
												'error'   => true,
												'data' => $resArticulo,
												'mensaje'	=> 'No se pudo registrar la Nota crédito de clientes'
											);

											 $this->response($respuesta);

											 return;
								}


								$DetalleCostoInventario->ac1_account = $resArticulo[0]['mga_acct_inv'];
								$DetalleCostoInventario->ac1_prc_code = isset($detail['nc1_costcode'])?$detail['nc1_costcode']:NULL;
								$DetalleCostoInventario->ac1_uncode = isset($detail['nc1_ubusiness'])?$detail['nc1_ubusiness']:NULL;
								$DetalleCostoInventario->ac1_prj_code = isset($detail['nc1_project'])?$detail['nc1_project']:NULL;
								$DetalleCostoInventario->nc1_linetotal = is_numeric($detail['nc1_linetotal'])?$detail['nc1_linetotal']:0;
								$DetalleCostoInventario->nc1_vat = is_numeric($detail['nc1_vat'])?$detail['nc1_vat']:0;
								$DetalleCostoInventario->nc1_vatsum = is_numeric($detail['nc1_vatsum'])?$detail['nc1_vatsum']:0;
								$DetalleCostoInventario->nc1_price = is_numeric($detail['nc1_price'])?$detail['nc1_price']:0;
								$DetalleCostoInventario->nc1_itemcode = isset($detail['nc1_itemcode'])?$detail['nc1_itemcode']:NULL;
								$DetalleCostoInventario->nc1_quantity = is_numeric($detail['nc1_quantity'])?$detail['nc1_quantity']:0;


								$DetalleCostoCosto->ac1_account = $resArticulo[0]['mga_acct_cost'];
								$DetalleCostoCosto->ac1_prc_code = isset($detail['nc1_costcode'])?$detail['nc1_costcode']:NULL;
								$DetalleCostoCosto->ac1_uncode = isset($detail['nc1_ubusiness'])?$detail['nc1_ubusiness']:NULL;
								$DetalleCostoCosto->ac1_prj_code = isset($detail['nc1_project'])?$detail['nc1_project']:NULL;
								$DetalleCostoCosto->nc1_linetotal = is_numeric($detail['nc1_linetotal'])?$detail['nc1_linetotal']:0;
								$DetalleCostoCosto->nc1_vat = is_numeric($detail['nc1_vat'])?$detail['nc1_vat']:0;
								$DetalleCostoCosto->nc1_vatsum = is_numeric($detail['nc1_vatsum'])?$detail['nc1_vatsum']:0;
								$DetalleCostoCosto->nc1_price = is_numeric($detail['nc1_price'])?$detail['nc1_price']:0;
								$DetalleCostoCosto->nc1_itemcode = isset($detail['nc1_itemcode'])?$detail['nc1_itemcode']:NULL;
								$DetalleCostoCosto->nc1_quantity = is_numeric($detail['nc1_quantity'])?$detail['nc1_quantity']:0;

								$codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);

								$DetalleAsientoIngreso->codigoCuenta = $codigoCuenta;
								$DetalleAsientoIva->codigoCuenta = $codigoCuenta;
								$DetalleCostoInventario->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);
								$DetalleCostoInventario->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);


								$llave = $DetalleAsientoIngreso->ac1_uncode.$DetalleAsientoIngreso->ac1_prc_code.$DetalleAsientoIngreso->ac1_prj_code.$DetalleAsientoIngreso->ac1_account;
								$llaveIva = $DetalleAsientoIva->nc1_vat;
								$llaveCostoInventario = $DetalleCostoInventario->ac1_account;
								$llaveCostoCosto = $DetalleCostoCosto->ac1_account;


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


								if(in_array( $llaveCostoInventario, $inArrayCostoInventario )){

										$posicionCostoInventario = $this->buscarPosicion( $llaveCostoInventario, $inArrayCostoInventario );

								}else{

										array_push( $inArrayCostoInventario, $llaveCostoInventario );
										$posicionCostoInventario = $this->buscarPosicion( $llaveCostoInventario, $inArrayCostoInventario );

								}


								if(in_array( $llaveCostoCosto, $inArrayCostoCosto )){

										$posicionCostoCosto = $this->buscarPosicion( $llaveCostoCosto, $inArrayCostoCosto );

								}else{

										array_push( $inArrayCostoCosto, $llaveCostoCosto );
										$posicionCostoCosto = $this->buscarPosicion( $llaveCostoCosto, $inArrayCostoCosto );

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


								if( isset($DetalleConsolidadoCostoInventario[$posicionCostoInventario])){

									if(!is_array($DetalleConsolidadoCostoInventario[$posicionCostoInventario])){
										$DetalleConsolidadoCostoInventario[$posicionCostoInventario] = array();
									}

								}else{
									$DetalleConsolidadoCostoInventario[$posicionCostoInventario] = array();
								}

								array_push( $DetalleConsolidadoCostoInventario[$posicionCostoInventario], $DetalleCostoInventario );


								if( isset($DetalleConsolidadoCostoCosto[$posicionCostoCosto])){

									if(!is_array($DetalleConsolidadoCostoCosto[$posicionCostoCosto])){
										$DetalleConsolidadoCostoCosto[$posicionCostoCosto] = array();
									}

								}else{
									$DetalleConsolidadoCostoCosto[$posicionCostoCosto] = array();
								}

								array_push( $DetalleConsolidadoCostoCosto[$posicionCostoCosto], $DetalleCostoCosto );

          }

					//Procedimiento para llenar Ingreso

					foreach ($DetalleConsolidadoIngreso as $key => $posicion) {
							$granTotalIngreso = 0;
							$codigoCuentaIngreso = "";
							$cuenta = "";
							$proyecto = "";
							$prc = "";
							$unidad = "";
							foreach ($posicion as $key => $value) {
										$granTotalIngreso = ( $granTotalIngreso + $value->nc1_linetotal );
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

							switch ($codigoCuentaIngreso) {
								case 1:
									$debito = $granTotalIngreso;
									$MontoSysDB = ($debito / $resMonedaSys[0]['tsa_value']);
									break;

								case 2:
									$credito = $granTotalIngreso;
									$MontoSysCR = ($credito / $resMonedaSys[0]['tsa_value']);
									break;

								case 3:
									$credito = $granTotalIngreso;
									$MontoSysCR = ($credito / $resMonedaSys[0]['tsa_value']);
									break;

								case 4:
									$credito = $granTotalIngreso;
									$MontoSysCR = ($credito / $resMonedaSys[0]['tsa_value']);
									break;

								case 5:
									$debito = $granTotalIngreso;
									$MontoSysDB = ($debito / $resMonedaSys[0]['tsa_value']);
									break;

								case 6:
									$debito = $granTotalIngreso;
									$MontoSysDB = ($debito / $resMonedaSys[0]['tsa_value']);
									break;

								case 7:
									$debito = $granTotalIngreso;
									$MontoSysDB = ($debito / $resMonedaSys[0]['tsa_value']);
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
									':ac1_doc_date' => $this->validateDate($Data['vnc_docdate'])?$Data['vnc_docdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['vnc_duedate'])?$Data['vnc_duedate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => $resInsert,
									':ac1_font_line' => 1,
									':ac1_font_type' => is_numeric($Data['vnc_doctype'])?$Data['vnc_doctype']:0,
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
									':ac1_made_user' => isset($Data['vnc_createby'])?$Data['vnc_createby']:NULL,
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
									':ac1_legal_num' => isset($Data['vnc_cardcode'])?$Data['vnc_cardcode']:NULL,
									':ac1_codref' => 1
						));



						if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
						}else{
								// si falla algun insert del detalle de la Nota crédito de clientes se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resDetalleAsiento,
										'mensaje'	=> 'No se pudo registrar la Nota crédito de clientes'
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

							foreach ($posicion as $key => $value) {
										$granTotalIva = $granTotalIva + $value->nc1_vatsum;
							}

							$MontoSysDB = ($granTotalIva / $resMonedaSys[0]['tsa_value']);


							$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

									':ac1_trans_id' => $resInsertAsiento,
									':ac1_account' => $value->nc1_cuentaIva,
									':ac1_debit' => 0,
									':ac1_credit' => $granTotalIva,
									':ac1_debit_sys' => 0,
									':ac1_credit_sys' => round($MontoSysDB,2),
									':ac1_currex' => 0,
									':ac1_doc_date' => $this->validateDate($Data['vnc_docdate'])?$Data['vnc_docdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['vnc_duedate'])?$Data['vnc_duedate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => $resInsert,
									':ac1_font_line' => 1,
									':ac1_font_type' => is_numeric($Data['vnc_doctype'])?$Data['vnc_doctype']:0,
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
									':ac1_made_user' => isset($Data['vnc_createby'])?$Data['vnc_createby']:NULL,
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
									':ac1_legal_num' => isset($Data['vnc_cardcode'])?$Data['vnc_cardcode']:NULL,
									':ac1_codref' => 1
						));



						if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
						}else{

								// si falla algun insert del detalle de la Nota crédito de clientes se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resDetalleAsiento,
										'mensaje'	=> 'No se pudo registrar la Nota crédito de clientes'
									);

									 $this->response($respuesta);

									 return;
						}
					}

					//FIN Procedimiento para llenar Impuestos


					//Procedimiento para llenar costo inventario
					foreach ($DetalleConsolidadoCostoInventario as $key => $posicion) {
							$grantotalCostoInventario = 0 ;
							$cuentaInventario = "";
							foreach ($posicion as $key => $value) {

										$sqlArticulo = "SELECT f2.dma_item_code,  f1.mga_acct_inv, f1.mga_acct_cost FROM dmga f1 JOIN dmar f2 ON f1.mga_id  = f2.dma_group_code WHERE dma_item_code = :dma_item_code";

										$resArticulo = $this->pedeo->queryTable($sqlArticulo, array(":dma_item_code" => $value->nc1_itemcode));

										if(isset($resArticulo[0])){
												$dbito = 0;
												$cdito = 0;

												$MontoSysDB = 0;
												$MontoSysCR = 0;

												$sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode";

												$resCosto = $this->pedeo->queryTable($sqlCosto, array(":bdi_itemcode" => $value->nc1_itemcode));

												if( isset( $resCosto[0] ) ){

															$cuentaInventario = $resArticulo[0]['mga_acct_inv'];


															$costoArticulo = $resCosto[0]['bdi_avgprice'];
															$cantidadArticulo = $value->nc1_quantity;
															$grantotalCostoInventario = ($grantotalCostoInventario + ($costoArticulo * $cantidadArticulo));

												}else{

															$this->pedeo->trans_rollback();

															$respuesta = array(
																'error'   => true,
																'data'	  => $resArticulo,
																'mensaje'	=> 'No se encontro el costo para el item: '.$value->nc1_itemcode
															);

															 $this->response($respuesta);

															 return;
												}

										}else{
												// si falla algun insert del detalle de la Nota crédito de clientes se devuelven los cambios realizados por la transaccion,
												// se retorna el error y se detiene la ejecucion del codigo restante.
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data'	  => $resArticulo,
													'mensaje'	=> 'No se encontro la cuenta de inventario y costo para el item '.$value->nc1_itemcode
												);

												 $this->response($respuesta);

												 return;
										}
							}

							$codigo3 = substr($cuentaInventario, 0, 1);

							if( $codigo3 == 1 || $codigo3 == "1" ){
									$cdito = $grantotalCostoInventario;
									$MontoSysCR = ($cdito / $resMonedaSys[0]['tsa_value']);
							}else if( $codigo3 == 2 || $codigo3 == "2" ){
									$cdito = $grantotalCostoInventario;
									$MontoSysCR = ($cdito / $resMonedaSys[0]['tsa_value']);
							}else if( $codigo3 == 3 || $codigo3 == "3" ){
									$cdito = $grantotalCostoInventario;
									$MontoSysCR = ($cdito / $resMonedaSys[0]['tsa_value']);
							}else if( $codigo3 == 4 || $codigo3 == "4" ){
									$cdito = $grantotalCostoInventario;
									$MontoSysCR = ($cdito / $resMonedaSys[0]['tsa_value']);
							}else if( $codigo3 == 5  || $codigo3 == "5" ){
									$dbito = $grantotalCostoInventario;
									$MontoSysDB = ($dbito / $resMonedaSys[0]['tsa_value']);
							}else if( $codigo3 == 6 || $codigo3 == "6" ){
									$dbito = $grantotalCostoInventario;
									$MontoSysDB = ($dbito / $resMonedaSys[0]['tsa_value']);
							}else if( $codigo3 == 7 || $codigo3 == "7" ){
									$dbito = $grantotalCostoInventario;
									$MontoSysDB = ($dbito / $resMonedaSys[0]['tsa_value']);
							}

							$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

									':ac1_trans_id' => $resInsertAsiento,
									':ac1_account' => $cuentaInventario,
									':ac1_debit' => $dbito,
									':ac1_credit' => $cdito,
									':ac1_debit_sys' => round($MontoSysDB,2),
									':ac1_credit_sys' => round($MontoSysCR,2),
									':ac1_currex' => 0,
									':ac1_doc_date' => $this->validateDate($Data['vnc_docdate'])?$Data['vnc_docdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['vnc_duedate'])?$Data['vnc_duedate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => $resInsert,
									':ac1_font_line' => 1,
									':ac1_font_type' => is_numeric($Data['vnc_doctype'])?$Data['vnc_doctype']:0,
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
									':ac1_made_user' => isset($Data['vnc_createby'])?$Data['vnc_createby']:NULL,
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
									':ac1_legal_num' => isset($Data['vnc_cardcode'])?$Data['vnc_cardcode']:NULL,
									':ac1_codref' => 1
						));

						if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
						}else{

								// si falla algun insert del detalle de la Nota crédito de clientes se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resDetalleAsiento,
										'mensaje'	=> 'No se pudo registrar la Nota crédito de clientes'
									);

									 $this->response($respuesta);

									 return;
						}

					}

					//FIN Procedimiento para llenar costo inventario




					// Procedimiento para llenar costo costo

					foreach ($DetalleConsolidadoCostoCosto as $key => $posicion) {
							$grantotalCostoCosto = 0 ;
							$cuentaCosto = "";
							foreach ($posicion as $key => $value) {

										$sqlArticulo = "SELECT f2.dma_item_code,  f1.mga_acct_inv, f1.mga_acct_cost FROM dmga f1 JOIN dmar f2 ON f1.mga_id  = f2.dma_group_code WHERE dma_item_code = :dma_item_code";

										$resArticulo = $this->pedeo->queryTable($sqlArticulo, array(":dma_item_code" => $value->nc1_itemcode));

										if(isset($resArticulo[0])){
												$dbito = 0;
												$cdito = 0;
												$MontoSysDB = 0;
												$MontoSysCR = 0;

												$sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode";

												$resCosto = $this->pedeo->queryTable($sqlCosto, array(":bdi_itemcode" => $value->nc1_itemcode));

												if( isset( $resCosto[0] ) ){

															$cuentaCosto = $resArticulo[0]['mga_acct_cost'];


															$costoArticulo = $resCosto[0]['bdi_avgprice'];
															$cantidadArticulo = $value->nc1_quantity;
															$grantotalCostoCosto = ($grantotalCostoCosto + ($costoArticulo * $cantidadArticulo));

												}else{

															$this->pedeo->trans_rollback();

															$respuesta = array(
																'error'   => true,
																'data'	  => $resArticulo,
																'mensaje'	=> 'No se encontro el costo para el item: '.$value->nc1_itemcode
															);

															 $this->response($respuesta);

															 return;
												}

										}else{
												// si falla algun insert del detalle de la Nota crédito de clientes se devuelven los cambios realizados por la transaccion,
												// se retorna el error y se detiene la ejecucion del codigo restante.
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data'	  => $resArticulo,
													'mensaje'	=> 'No se encontro el costo para el item '.$value->nc1_itemcode
												);

												 $this->response($respuesta);

												 return;
										}
							}

								$codigo3 = substr($cuentaCosto, 0, 1);

								if( $codigo3 == 1 || $codigo3 == "1" ){
									$dbito = 	$grantotalCostoCosto;
									$MontoSysDB = ($dbito / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo3 == 2 || $codigo3 == "2" ){
									$cdito = 	$grantotalCostoCosto;
									$MontoSysCR = ($cdito / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo3 == 3 || $codigo3 == "3" ){
									$cdito = 	$grantotalCostoCosto;
									$MontoSysCR = ($cdito / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo3 == 4 || $codigo3 == "4" ){
									$cdito = 	$grantotalCostoCosto;
									$MontoSysCR = ($cdito / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo3 == 5  || $codigo3 == "5" ){
									$dbito = 	$grantotalCostoCosto;
									$MontoSysDB = ($dbito / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo3 == 6 || $codigo3 == "6" ){
									$dbito = 	$grantotalCostoCosto;
									$MontoSysDB = ($dbito / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo3 == 7 || $codigo3 == "7" ){
									$dbito = 	$grantotalCostoCosto;
									$MontoSysDB = ($dbito / $resMonedaSys[0]['tsa_value']);
								}

								$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

								':ac1_trans_id' => $resInsertAsiento,
								':ac1_account' => $cuentaCosto,
								':ac1_debit' => $dbito,
								':ac1_credit' => $cdito,
								':ac1_debit_sys' => round($MontoSysDB,2),
								':ac1_credit_sys' => round($MontoSysCR,2),
								':ac1_currex' => 0,
								':ac1_doc_date' => $this->validateDate($Data['vnc_docdate'])?$Data['vnc_docdate']:NULL,
								':ac1_doc_duedate' => $this->validateDate($Data['vnc_duedate'])?$Data['vnc_duedate']:NULL,
								':ac1_debit_import' => 0,
								':ac1_credit_import' => 0,
								':ac1_debit_importsys' => 0,
								':ac1_credit_importsys' => 0,
								':ac1_font_key' => $resInsert,
								':ac1_font_line' => 1,
								':ac1_font_type' => is_numeric($Data['vnc_doctype'])?$Data['vnc_doctype']:0,
								':ac1_accountvs' => 1,
								':ac1_doctype' => 18,
								':ac1_ref1' => "",
								':ac1_ref2' => "",
								':ac1_ref3' => "",
								':ac1_prc_code' => $value->ac1_prc_code,
								':ac1_uncode' => $value->ac1_uncode,
								':ac1_prj_code' => $value->ac1_prj_code,
								':ac1_rescon_date' => NULL,
								':ac1_recon_total' => 0,
								':ac1_made_user' => isset($Data['vnc_createby'])?$Data['vnc_createby']:NULL,
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
								':ac1_legal_num' => isset($Data['vnc_cardcode'])?$Data['vnc_cardcode']:NULL,
								':ac1_codref' => 1
								));

								if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
								}else{

								// si falla algun insert del detalle de la Nota crédito de clientes se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $resDetalleAsiento,
									'mensaje'	=> 'No se pudo registrar la Nota crédito de clientes'
								);

								 $this->response($respuesta);

								 return;
								}



					}
				 //FIN Procedimiento para llenar costo costo

				//Procedimiento para llenar cuentas por cobrar

					$sqlcuentaCxP = "SELECT  f1.dms_card_code, f2.mgs_acct FROM dmsn AS f1
													 JOIN dmgs  AS f2
													 ON CAST(f2.mgs_id AS varchar(100)) = f1.dms_group_num
													 WHERE  f1.dms_card_code = :dms_card_code";

					$rescuentaCxP = $this->pedeo->queryTable($sqlcuentaCxP, array(":dms_card_code" => $Data['vnc_cardcode']));



					if(isset( $rescuentaCxP[0] )){

								$debitoo = 0;
								$creditoo = 0;
								$MontoSysDB = 0;
								$MontoSysCR = 0;

								$cuentaCxP = $rescuentaCxP[0]['mgs_acct'];

								$codigo2= substr($rescuentaCxP[0]['mgs_acct'], 0, 1);


								if( $codigo2 == 1 || $codigo2 == "1" ){
										$debitoo = $Data['vnc_doctotal'];
										$MontoSysDB = ($debitoo / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo2 == 2 || $codigo2 == "2" ){
										$creditoo = $Data['vnc_doctotal'];
										$MontoSysCR = ($creditoo / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo2 == 3 || $codigo2 == "3" ){
										$creditoo = $Data['vnc_doctotal'];
										$MontoSysCR = ($creditoo / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo2 == 4 || $codigo2 == "4" ){
									  $creditoo = $Data['vnc_doctotal'];
										$MontoSysCR = ($creditoo / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo2 == 5  || $codigo2 == "5" ){
									  $debitoo = $Data['vnc_doctotal'];
										$MontoSysDB = ($debitoo / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo2 == 6 || $codigo2 == "6" ){
									  $debitoo = $Data['vnc_doctotal'];
										$MontoSysDB = ($debitoo / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo2 == 7 || $codigo2 == "7" ){
									  $debitoo = $Data['vnc_doctotal'];
										$MontoSysDB = ($debitoo / $resMonedaSys[0]['tsa_value']);
								}

								$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

										':ac1_trans_id' => $resInsertAsiento,
										':ac1_account' => $cuentaCxP,
										':ac1_debit' => $debitoo,
										':ac1_credit' => $creditoo,
										':ac1_debit_sys' => round($MontoSysDB,2),
										':ac1_credit_sys' => round($MontoSysCR,2),
										':ac1_currex' => 0,
										':ac1_doc_date' => $this->validateDate($Data['vnc_docdate'])?$Data['vnc_docdate']:NULL,
										':ac1_doc_duedate' => $this->validateDate($Data['vnc_duedate'])?$Data['vnc_duedate']:NULL,
										':ac1_debit_import' => 0,
										':ac1_credit_import' => 0,
										':ac1_debit_importsys' => 0,
										':ac1_credit_importsys' => 0,
										':ac1_font_key' => $resInsert,
										':ac1_font_line' => 1,
										':ac1_font_type' => is_numeric($Data['vnc_doctype'])?$Data['vnc_doctype']:0,
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
										':ac1_made_user' => isset($Data['vnc_createby'])?$Data['vnc_createby']:NULL,
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
										':ac1_legal_num' => isset($Data['vnc_cardcode'])?$Data['vnc_cardcode']:NULL,
										':ac1_codref' => 1
							));

							if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
									// Se verifica que el detalle no de error insertando //
							}else{

									// si falla algun insert del detalle de la Nota crédito de clientes se devuelven los cambios realizados por la transaccion,
									// se retorna el error y se detiene la ejecucion del codigo restante.
										$this->pedeo->trans_rollback();

										$respuesta = array(
											'error'   => true,
											'data'	  => $resDetalleAsiento,
											'mensaje'	=> 'No se pudo registrar la Nota crédito de clientes'
										);

										 $this->response($respuesta);

										 return;
							}

					}else{

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $resDetalleAsiento,
									'mensaje'	=> 'No se pudo registrar la Nota crédito de clientes, el tercero no tiene cuenta asociada'
								);

								 $this->response($respuesta);

								 return;
					}
					//FIN Procedimiento para llenar cuentas por cobrar

					// Si todo sale bien despues de insertar el detalle de la Nota crédito de clientes
					// se confirma la trasaccion  para que los cambios apliquen permanentemente
					// en la base de datos y se confirma la operacion exitosa.
					$this->pedeo->trans_commit();

          $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' =>'Nota crédito de clientes registrada con exito'
          );


        }else{
					// Se devuelven los cambios realizados en la transaccion
					// si occurre un error  y se muestra devuelve el error.
							$this->pedeo->trans_rollback();

              $respuesta = array(
                'error'   => true,
                'data' => $resInsert,
                'mensaje'	=> 'No se pudo registrar la Nota crédito de clientes'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR Nota crédito de clientes
  public function updateSalesNc_post(){

      $Data = $this->post();

			if(!isset($Data['vnc_docentry']) OR !isset($Data['vnc_docnum']) OR
				 !isset($Data['vnc_docdate']) OR !isset($Data['vnc_duedate']) OR
				 !isset($Data['vnc_duedev']) OR !isset($Data['vnc_pricelist']) OR
				 !isset($Data['vnc_cardcode']) OR !isset($Data['vnc_cardname']) OR
				 !isset($Data['vnc_currency']) OR !isset($Data['vnc_contacid']) OR
				 !isset($Data['vnc_slpcode']) OR !isset($Data['vnc_empid']) OR
				 !isset($Data['vnc_comment']) OR !isset($Data['vnc_doctotal']) OR
				 !isset($Data['vnc_baseamnt']) OR !isset($Data['vnc_taxtotal']) OR
				 !isset($Data['vnc_discprofit']) OR !isset($Data['vnc_discount']) OR
				 !isset($Data['vnc_createat']) OR !isset($Data['vnc_baseentry']) OR
				 !isset($Data['vnc_basetype']) OR !isset($Data['vnc_doctype']) OR
				 !isset($Data['vnc_idadd']) OR !isset($Data['vnc_adress']) OR
				 !isset($Data['vnc_paytype']) OR !isset($Data['vnc_attch']) OR
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
            'mensaje' =>'No se encontro el detalle de la Nota crédito de clientes'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }

      $sqlUpdate = "UPDATE dvnc	SET vnc_docdate=:vnc_docdate,vnc_duedate=:vnc_duedate, vnc_duedev=:vnc_duedev, vnc_pricelist=:vnc_pricelist, vnc_cardcode=:vnc_cardcode,
			  						vnc_cardname=:vnc_cardname, vnc_currency=:vnc_currency, vnc_contacid=:vnc_contacid, vnc_slpcode=:vnc_slpcode,
										vnc_empid=:vnc_empid, vnc_comment=:vnc_comment, vnc_doctotal=:vnc_doctotal, vnc_baseamnt=:vnc_baseamnt,
										vnc_taxtotal=:vnc_taxtotal, vnc_discprofit=:vnc_discprofit, vnc_discount=:vnc_discount, vnc_createat=:vnc_createat,
										vnc_baseentry=:vnc_baseentry, vnc_basetype=:vnc_basetype, vnc_doctype=:vnc_doctype, vnc_idadd=:vnc_idadd,
										vnc_adress=:vnc_adress, vnc_paytype=:vnc_paytype, vnc_attch=:vnc_attch WHERE vnc_docentry=:vnc_docentry";

      $this->pedeo->trans_begin();

      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
							':vnc_docnum' => is_numeric($Data['vnc_docnum'])?$Data['vnc_docnum']:0,
							':vnc_docdate' => $this->validateDate($Data['vnc_docdate'])?$Data['vnc_docdate']:NULL,
							':vnc_duedate' => $this->validateDate($Data['vnc_duedate'])?$Data['vnc_duedate']:NULL,
							':vnc_duedev' => $this->validateDate($Data['vnc_duedev'])?$Data['vnc_duedev']:NULL,
							':vnc_pricelist' => is_numeric($Data['vnc_pricelist'])?$Data['vnc_pricelist']:0,
							':vnc_cardcode' => isset($Data['vnc_pricelist'])?$Data['vnc_pricelist']:NULL,
							':vnc_cardname' => isset($Data['vnc_cardname'])?$Data['vnc_cardname']:NULL,
							':vnc_currency' => is_numeric($Data['vnc_currency'])?$Data['vnc_currency']:0,
							':vnc_contacid' => isset($Data['vnc_contacid'])?$Data['vnc_contacid']:NULL,
							':vnc_slpcode' => is_numeric($Data['vnc_slpcode'])?$Data['vnc_slpcode']:0,
							':vnc_empid' => is_numeric($Data['vnc_empid'])?$Data['vnc_empid']:0,
							':vnc_comment' => isset($Data['vnc_comment'])?$Data['vnc_comment']:NULL,
							':vnc_doctotal' => is_numeric($Data['vnc_doctotal'])?$Data['vnc_doctotal']:0,
							':vnc_baseamnt' => is_numeric($Data['vnc_baseamnt'])?$Data['vnc_baseamnt']:0,
							':vnc_taxtotal' => is_numeric($Data['vnc_taxtotal'])?$Data['vnc_taxtotal']:0,
							':vnc_discprofit' => is_numeric($Data['vnc_discprofit'])?$Data['vnc_discprofit']:0,
							':vnc_discount' => is_numeric($Data['vnc_discount'])?$Data['vnc_discount']:0,
							':vnc_createat' => $this->validateDate($Data['vnc_createat'])?$Data['vnc_createat']:NULL,
							':vnc_baseentry' => is_numeric($Data['vnc_baseentry'])?$Data['vnc_baseentry']:0,
							':vnc_basetype' => is_numeric($Data['vnc_basetype'])?$Data['vnc_basetype']:0,
							':vnc_doctype' => is_numeric($Data['vnc_doctype'])?$Data['vnc_doctype']:0,
							':vnc_idadd' => isset($Data['vnc_idadd'])?$Data['vnc_idadd']:NULL,
							':vnc_adress' => isset($Data['vnc_adress'])?$Data['vnc_adress']:NULL,
							':vnc_paytype' => is_numeric($Data['vnc_paytype'])?$Data['vnc_paytype']:0,
							':vnc_attch' => $this->getUrl(count(trim(($Data['vnc_attch']))) > 0 ? $Data['vnc_attch']:NULL),
							':vnc_docentry' => $Data['vnc_docentry']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

						$this->pedeo->queryTable("DELETE FROM vnc1 WHERE nc1_docentry=:nc1_docentry", array(':nc1_docentry' => $Data['vnc_docentry']));

						foreach ($ContenidoDetalle as $key => $detail) {

									$sqlInsertDetail = "INSERT INTO vnc1(nc1_docentry, nc1_itemcode, nc1_itemname, nc1_quantity, nc1_uom, nc1_whscode,
																			nc1_price, nc1_vat, nc1_vatsum, nc1_discount, nc1_linetotal, nc1_costcode, nc1_ubusiness, nc1_project,
																			nc1_acctcode, nc1_basetype, nc1_doctype, nc1_avprice, nc1_inventory)VALUES(:nc1_docentry, :nc1_itemcode, :nc1_itemname, :nc1_quantity,
																			:nc1_uom, :nc1_whscode,:nc1_price, :nc1_vat, :nc1_vatsum, :nc1_discount, :nc1_linetotal, :nc1_costcode, :nc1_ubusiness, :nc1_project,
																			:nc1_acctcode, :nc1_basetype, :nc1_doctype, :nc1_avprice, :nc1_inventory)";

									$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
											':nc1_docentry' => $resInsert,
											':nc1_itemcode' => isset($detail['nc1_itemcode'])?$detail['nc1_itemcode']:NULL,
											':nc1_itemname' => isset($detail['nc1_itemname'])?$detail['nc1_itemname']:NULL,
											':nc1_quantity' => is_numeric($detail['nc1_quantity'])?$detail['nc1_quantity']:0,
											':nc1_uom' => isset($detail['nc1_uom'])?$detail['nc1_uom']:NULL,
											':nc1_whscode' => isset($detail['nc1_whscode'])?$detail['nc1_whscode']:NULL,
											':nc1_price' => is_numeric($detail['nc1_price'])?$detail['nc1_price']:0,
											':nc1_vat' => is_numeric($detail['nc1_vat'])?$detail['nc1_vat']:0,
											':nc1_vatsum' => is_numeric($detail['nc1_vatsum'])?$detail['nc1_vatsum']:0,
											':nc1_discount' => is_numeric($detail['nc1_discount'])?$detail['nc1_discount']:0,
											':nc1_linetotal' => is_numeric($detail['nc1_linetotal'])?$detail['nc1_linetotal']:0,
											':nc1_costcode' => isset($detail['nc1_costcode'])?$detail['nc1_costcode']:NULL,
											':nc1_ubusiness' => isset($detail['nc1_ubusiness'])?$detail['nc1_ubusiness']:NULL,
											':nc1_project' => isset($detail['nc1_project'])?$detail['nc1_project']:NULL,
											':nc1_acctcode' => is_numeric($detail['nc1_acctcode'])?$detail['nc1_acctcode']:0,
											':nc1_basetype' => is_numeric($detail['nc1_basetype'])?$detail['nc1_basetype']:0,
											':nc1_doctype' => is_numeric($detail['nc1_doctype'])?$detail['nc1_doctype']:0,
											':nc1_avprice' => is_numeric($detail['nc1_avprice'])?$detail['nc1_avprice']:0,
											':nc1_inventory' => is_numeric($detail['nc1_inventory'])?$detail['nc1_inventory']:NULL
									));

									if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
											// Se verifica que el detalle no de error insertando //
									}else{

											// si falla algun insert del detalle de la Nota crédito de clientes se devuelven los cambios realizados por la transaccion,
											// se retorna el error y se detiene la ejecucion del codigo restante.
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data' => $resInsert,
													'mensaje'	=> 'No se pudo registrar la Nota crédito de clientes'
												);

												 $this->response($respuesta);

												 return;
									}
						}


						$this->pedeo->trans_commit();

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Nota crédito de clientes actualizada con exito'
            );


      }else{

						$this->pedeo->trans_rollback();

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la Nota crédito de clientes'
            );

      }

       $this->response($respuesta);
  }


  //OBTENER Nota crédito de clientesES
  public function getSalesNc_get(){

        $sqlSelect = " SELECT * FROM dvnc";

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


	//OBTENER Nota crédito de clientes POR ID
	public function getSalesNcById_get(){

				$Data = $this->get();

				if(!isset($Data['vnc_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM dvnc WHERE vnc_docentry =:vnc_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":vnc_docentry" => $Data['vnc_docentry']));

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


	//OBTENER Nota crédito de clientes DETALLE POR ID
	public function getSalesNcDetail_get(){

				$Data = $this->get();

				if(!isset($Data['nc1_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM vnc1 WHERE nc1_docentry =:nc1_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":nc1_docentry" => $Data['nc1_docentry']));

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




}
