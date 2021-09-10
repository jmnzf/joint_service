<?php
// Entrega de VentasES
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class SalesDel extends REST_Controller {

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
	public function createSalesDel_post(){

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
            'mensaje' =>'No se encontro el detalle de la Entrega de ventas'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }
				//BUSCANDO LA NUMERACION DEL DOCUMENTO
			  $sqlNumeracion = " SELECT pgs_nextnum,pgs_last_num FROM  pgdn WHERE pgs_id = :pgs_id";

				$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['vem_series']));

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

				$resMonedaSys = $this->pedeo->queryTable($sqlMonedaSys, array(':pgm_system' => 1, ':tsa_date' => $Data['vem_docdate']));

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

        $sqlInsert = "INSERT INTO dvem(vem_series, vem_docnum, vem_docdate, vem_duedate, vem_duedev, vem_pricelist, vem_cardcode,
                      vem_cardname, vem_currency, vem_contacid, vem_slpcode, vem_empid, vem_comment, vem_doctotal, vem_baseamnt, vem_taxtotal,
                      vem_discprofit, vem_discount, vem_createat, vem_baseentry, vem_basetype, vem_doctype, vem_idadd, vem_adress, vem_paytype,
                      vem_attch,vem_createby)VALUES(:vem_series, :vem_docnum, :vem_docdate, :vem_duedate, :vem_duedev, :vem_pricelist, :vem_cardcode, :vem_cardname,
                      :vem_currency, :vem_contacid, :vem_slpcode, :vem_empid, :vem_comment, :vem_doctotal, :vem_baseamnt, :vem_taxtotal, :vem_discprofit, :vem_discount,
                      :vem_createat, :vem_baseentry, :vem_basetype, :vem_doctype, :vem_idadd, :vem_adress, :vem_paytype, :vem_attch,:vem_createby)";


				// Se Inicia la transaccion,
				// Todas las consultas de modificacion siguientes
				// aplicaran solo despues que se confirme la transaccion,
				// de lo contrario no se aplicaran los cambios y se devolvera
				// la base de datos a su estado original.

			  $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':vem_docnum' => $DocNumVerificado,
              ':vem_series' => is_numeric($Data['vem_series'])?$Data['vem_series']:0,
              ':vem_docdate' => $this->validateDate($Data['vem_docdate'])?$Data['vem_docdate']:NULL,
              ':vem_duedate' => $this->validateDate($Data['vem_duedate'])?$Data['vem_duedate']:NULL,
              ':vem_duedev' => $this->validateDate($Data['vem_duedev'])?$Data['vem_duedev']:NULL,
              ':vem_pricelist' => is_numeric($Data['vem_pricelist'])?$Data['vem_pricelist']:0,
              ':vem_cardcode' => isset($Data['vem_cardcode'])?$Data['vem_cardcode']:NULL,
              ':vem_cardname' => isset($Data['vem_cardname'])?$Data['vem_cardname']:NULL,
              ':vem_currency' => is_numeric($Data['vem_currency'])?$Data['vem_currency']:0,
              ':vem_contacid' => isset($Data['vem_contacid'])?$Data['vem_contacid']:NULL,
              ':vem_slpcode' => is_numeric($Data['vem_slpcode'])?$Data['vem_slpcode']:0,
              ':vem_empid' => is_numeric($Data['vem_empid'])?$Data['vem_empid']:0,
              ':vem_comment' => isset($Data['vem_comment'])?$Data['vem_comment']:NULL,
              ':vem_doctotal' => is_numeric($Data['vem_doctotal'])?$Data['vem_doctotal']:0,
              ':vem_baseamnt' => is_numeric($Data['vem_baseamnt'])?$Data['vem_baseamnt']:0,
              ':vem_taxtotal' => is_numeric($Data['vem_taxtotal'])?$Data['vem_taxtotal']:0,
              ':vem_discprofit' => is_numeric($Data['vem_discprofit'])?$Data['vem_discprofit']:0,
              ':vem_discount' => is_numeric($Data['vem_discount'])?$Data['vem_discount']:0,
              ':vem_createat' => $this->validateDate($Data['vem_createat'])?$Data['vem_createat']:NULL,
              ':vem_baseentry' => is_numeric($Data['vem_baseentry'])?$Data['vem_baseentry']:0,
              ':vem_basetype' => is_numeric($Data['vem_basetype'])?$Data['vem_basetype']:0,
              ':vem_doctype' => is_numeric($Data['vem_doctype'])?$Data['vem_doctype']:0,
              ':vem_idadd' => isset($Data['vem_idadd'])?$Data['vem_idadd']:NULL,
              ':vem_adress' => isset($Data['vem_adress'])?$Data['vem_adress']:NULL,
              ':vem_paytype' => is_numeric($Data['vem_paytype'])?$Data['vem_paytype']:0,
							':vem_createby' => isset($Data['vem_createby'])?$Data['vem_createby']:NULL,
              ':vem_attch' => $this->getUrl(count(trim(($Data['vem_attch']))) > 0 ? $Data['vem_attch']:NULL)
						));

        if(is_numeric($resInsert) && $resInsert > 0){

					// Se actualiza la serie de la numeracion del documento

					$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
					$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
							':pgs_nextnum' => $DocNumVerificado,
							':pgs_id'      => $Data['vem_series']
					));


					if(is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1){

					}else{
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resActualizarNumeracion,
									'mensaje'	=> 'No se pudo crear la Entrega de ventas'
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
							':mac_base_type' => is_numeric($Data['vem_doctype'])?$Data['vem_doctype']:0,
							':mac_base_entry' => $resInsert,
							':mac_doc_date' => $this->validateDate($Data['vem_docdate'])?$Data['vem_docdate']:NULL,
							':mac_doc_duedate' => $this->validateDate($Data['vem_duedate'])?$Data['vem_duedate']:NULL,
							':mac_legal_date' => $this->validateDate($Data['vem_docdate'])?$Data['vem_docdate']:NULL,
							':mac_ref1' => is_numeric($Data['vem_doctype'])?$Data['vem_doctype']:0,
							':mac_ref2' => "",
							':mac_ref3' => "",
							':mac_loc_total' => is_numeric($Data['vem_doctotal'])?$Data['vem_doctotal']:0,
							':mac_fc_total' => is_numeric($Data['vem_doctotal'])?$Data['vem_doctotal']:0,
							':mac_sys_total' => is_numeric($Data['vem_doctotal'])?$Data['vem_doctotal']:0,
							':mac_trans_dode' => 1,
							':mac_beline_nume' => 1,
							':mac_vat_date' => $this->validateDate($Data['vem_docdate'])?$Data['vem_docdate']:NULL,
							':mac_serie' => 1,
							':mac_number' => 1,
							':mac_bammntsys' => is_numeric($Data['vem_baseamnt'])?$Data['vem_baseamnt']:0,
							':mac_bammnt' => is_numeric($Data['vem_baseamnt'])?$Data['vem_baseamnt']:0,
							':mac_wtsum' => 1,
							':mac_vatsum' => is_numeric($Data['vem_taxtotal'])?$Data['vem_taxtotal']:0,
							':mac_comments' => isset($Data['vem_comment'])?$Data['vem_comment']:NULL,
							':mac_create_date' => $this->validateDate($Data['vem_createat'])?$Data['vem_createat']:NULL,
							':mac_made_usuer' => isset($Data['vem_createby'])?$Data['vem_createby']:NULL,
							':mac_update_date' => date("Y-m-d"),
							':mac_update_user' => isset($Data['vem_createby'])?$Data['vem_createby']:NULL
					));


					if(is_numeric($resInsertAsiento) && $resInsertAsiento > 0){
							// Se verifica que el detalle no de error insertando //
					}else{

							// si falla algun insert del detalle de la Entrega de Ventas se devuelven los cambios realizados por la transaccion,
							// se retorna el error y se detiene la ejecucion del codigo restante.
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $resInsertAsiento,
									'mensaje'	=> 'No se pudo registrar la Entrega de ventas'
								);

								 $this->response($respuesta);

								 return;
					}


          foreach ($ContenidoDetalle as $key => $detail) {

                $sqlInsertDetail = "INSERT INTO vem1(em1_docentry, em1_itemcode, em1_itemname, em1_quantity, em1_uom, em1_whscode,
                                    em1_price, em1_vat, em1_vatsum, em1_discount, em1_linetotal, em1_costcode, em1_ubusiness, em1_project,
                                    em1_acctcode, em1_basetype, em1_doctype, em1_avprice, em1_inventory)VALUES(:em1_docentry, :em1_itemcode, :em1_itemname, :em1_quantity,
                                    :em1_uom, :em1_whscode,:em1_price, :em1_vat, :em1_vatsum, :em1_discount, :em1_linetotal, :em1_costcode, :em1_ubusiness, :em1_project,
                                    :em1_acctcode, :em1_basetype, :em1_doctype, :em1_avprice, :em1_inventory)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':em1_docentry' => $resInsert,
                        ':em1_itemcode' => isset($detail['em1_itemcode'])?$detail['em1_itemcode']:NULL,
                        ':em1_itemname' => isset($detail['em1_itemname'])?$detail['em1_itemname']:NULL,
                        ':em1_quantity' => is_numeric($detail['em1_quantity'])?$detail['em1_quantity']:0,
                        ':em1_uom' => isset($detail['em1_uom'])?$detail['em1_uom']:NULL,
                        ':em1_whscode' => isset($detail['em1_whscode'])?$detail['em1_whscode']:NULL,
                        ':em1_price' => is_numeric($detail['em1_price'])?$detail['em1_price']:0,
                        ':em1_vat' => is_numeric($detail['em1_vat'])?$detail['em1_vat']:0,
                        ':em1_vatsum' => is_numeric($detail['em1_vatsum'])?$detail['em1_vatsum']:0,
                        ':em1_discount' => is_numeric($detail['em1_discount'])?$detail['em1_discount']:0,
                        ':em1_linetotal' => is_numeric($detail['em1_linetotal'])?$detail['em1_linetotal']:0,
                        ':em1_costcode' => isset($detail['em1_costcode'])?$detail['em1_costcode']:NULL,
                        ':em1_ubusiness' => isset($detail['em1_ubusiness'])?$detail['em1_ubusiness']:NULL,
                        ':em1_project' => isset($detail['em1_project'])?$detail['em1_project']:NULL,
                        ':em1_acctcode' => is_numeric($detail['em1_acctcode'])?$detail['em1_acctcode']:0,
                        ':em1_basetype' => is_numeric($detail['em1_basetype'])?$detail['em1_basetype']:0,
                        ':em1_doctype' => is_numeric($detail['em1_doctype'])?$detail['em1_doctype']:0,
                        ':em1_avprice' => is_numeric($detail['em1_avprice'])?$detail['em1_avprice']:0,
                        ':em1_inventory' => is_numeric($detail['em1_inventory'])?$detail['em1_inventory']:NULL
                ));

								if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
										// Se verifica que el detalle no de error insertando //
								}else{

										// si falla algun insert del detalle de la Entrega de Ventas se devuelven los cambios realizados por la transaccion,
										// se retorna el error y se detiene la ejecucion del codigo restante.
											$this->pedeo->trans_rollback();

											$respuesta = array(
												'error'   => true,
												'data' => $resInsert,
												'mensaje'	=> 'No se pudo registrar la Entrega de ventas'
											);

											 $this->response($respuesta);

											 return;
								}

								// si el item es inventariable
								if( $detail['em1_articleInv'] == 1 || $detail['em1_articleInv'] == "1" ){
										//Se aplica el movimiento de inventario
										$sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode, bmi_quantity, bmi_whscode, bmi_createat, bmi_createby, bmy_doctype, bmy_baseentry)
																					 VALUES (:bmi_itemcode, :bmi_quantity, :bmi_whscode, :bmi_createat, :bmi_createby, :bmy_doctype, :bmy_baseentry)";

										$sqlInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(

												 ':bmi_itemcode' => isset($detail['em1_itemcode'])?$detail['em1_itemcode']:NULL,
												 ':bmi_quantity' => is_numeric($detail['em1_quantity'])? $detail['em1_quantity'] * $Data['invtype']:0,
												 ':bmi_whscode'  => isset($detail['em1_whscode'])?$detail['em1_whscode']:NULL,
												 ':bmi_createat' => $this->validateDate($Data['vem_createat'])?$Data['vem_createat']:NULL,
												 ':bmi_createby' => isset($Data['vem_createby'])?$Data['vem_createby']:NULL,
												 ':bmy_doctype'  => is_numeric($Data['vem_doctype'])?$Data['vem_doctype']:0,
												 ':bmy_baseentry' => $resInsert

										));

										if(is_numeric($sqlInserMovimiento) && $sqlInserMovimiento > 0){
												// Se verifica que el detalle no de error insertando //
										}else{

												// si falla algun insert del detalle de la Entrega de Ventas se devuelven los cambios realizados por la transaccion,
												// se retorna el error y se detiene la ejecucion del codigo restante.
													$this->pedeo->trans_rollback();

													$respuesta = array(
														'error'   => true,
														'data' => $sqlInserMovimiento,
														'mensaje'	=> 'No se pudo registrar la Entrega de ventas'
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

														':bdi_itemcode' => $detail['em1_itemcode'],
														':bdi_whscode'  => $detail['em1_whscode']
											));

											if(isset($resCostoCantidad[0])){

												if($resCostoCantidad[0]['bdi_quantity'] > 0){

														 $CantidadActual = $resCostoCantidad[0]['bdi_quantity'];
														 $CantidadNueva = $detail['em1_quantity'];


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
																	 'mensaje'	=> 'No se pudo crear la Entrega de Ventas'
																 );
														 }

												}else{

																 $this->pedeo->trans_rollback();

																 $respuesta = array(
																	 'error'   => true,
																	 'data'    => $resUpdateCostoCantidad,
																	 'mensaje' => 'No hay existencia para el item: '.$detail['em1_itemcode']
																 );
												}

											}else{

														$this->pedeo->trans_rollback();

														$respuesta = array(
															'error'   => true,
															'data' 		=> $resInsertCostoCantidad,
															'mensaje'	=> 'El item no existe en el stock '.$detail['em1_itemcode']
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


								$DetalleAsientoIngreso->ac1_account = is_numeric($detail['em1_acctcode'])?$detail['em1_acctcode']: 0;
								$DetalleAsientoIngreso->ac1_prc_code = isset($detail['em1_costcode'])?$detail['em1_costcode']:NULL;
								$DetalleAsientoIngreso->ac1_uncode = isset($detail['em1_ubusiness'])?$detail['em1_ubusiness']:NULL;
								$DetalleAsientoIngreso->ac1_prj_code = isset($detail['em1_project'])?$detail['em1_project']:NULL;
								$DetalleAsientoIngreso->em1_linetotal = is_numeric($detail['em1_linetotal'])?$detail['em1_linetotal']:0;
								$DetalleAsientoIngreso->em1_vat = is_numeric($detail['em1_vat'])?$detail['em1_vat']:0;
								$DetalleAsientoIngreso->em1_vatsum = is_numeric($detail['em1_vatsum'])?$detail['em1_vatsum']:0;
								$DetalleAsientoIngreso->em1_price = is_numeric($detail['em1_price'])?$detail['em1_price']:0;
								$DetalleAsientoIngreso->em1_itemcode = isset($detail['em1_itemcode'])?$detail['em1_itemcode']:NULL;
								$DetalleAsientoIngreso->em1_quantity = is_numeric($detail['em1_quantity'])?$detail['em1_quantity']:0;



								$DetalleAsientoIva->ac1_account = is_numeric($detail['em1_acctcode'])?$detail['em1_acctcode']: 0;
								$DetalleAsientoIva->ac1_prc_code = isset($detail['em1_costcode'])?$detail['em1_costcode']:NULL;
								$DetalleAsientoIva->ac1_uncode = isset($detail['em1_ubusiness'])?$detail['em1_ubusiness']:NULL;
								$DetalleAsientoIva->ac1_prj_code = isset($detail['em1_project'])?$detail['em1_project']:NULL;
								$DetalleAsientoIva->em1_linetotal = is_numeric($detail['em1_linetotal'])?$detail['em1_linetotal']:0;
								$DetalleAsientoIva->em1_vat = is_numeric($detail['em1_vat'])?$detail['em1_vat']:0;
								$DetalleAsientoIva->em1_vatsum = is_numeric($detail['em1_vatsum'])?$detail['em1_vatsum']:0;
								$DetalleAsientoIva->em1_price = is_numeric($detail['em1_price'])?$detail['em1_price']:0;
								$DetalleAsientoIva->em1_itemcode = isset($detail['em1_itemcode'])?$detail['em1_itemcode']:NULL;
								$DetalleAsientoIva->em1_quantity = is_numeric($detail['em1_quantity'])?$detail['em1_quantity']:0;
								$DetalleAsientoIva->em1_cuentaIva = is_numeric($detail['em1_cuentaIva'])?$detail['em1_cuentaIva']:NULL;



								// se busca la cuenta contable del costoInventario y costoCosto
								$sqlArticulo = "SELECT f2.dma_item_code,  f1.mga_acct_inv, f1.mga_acct_cost FROM dmga f1 JOIN dmar f2 ON f1.mga_id  = f2.dma_group_code WHERE dma_item_code = :dma_item_code";

								$resArticulo = $this->pedeo->queryTable($sqlArticulo, array(":dma_item_code" => $detail['em1_itemcode']));

								if(!isset($resArticulo[0])){

											$this->pedeo->trans_rollback();

											$respuesta = array(
												'error'   => true,
												'data' => $resArticulo,
												'mensaje'	=> 'No se pudo registrar la Entrega de ventas'
											);

											 $this->response($respuesta);

											 return;
								}


								$DetalleCostoInventario->ac1_account = $resArticulo[0]['mga_acct_inv'];
								$DetalleCostoInventario->ac1_prc_code = isset($detail['em1_costcode'])?$detail['em1_costcode']:NULL;
								$DetalleCostoInventario->ac1_uncode = isset($detail['em1_ubusiness'])?$detail['em1_ubusiness']:NULL;
								$DetalleCostoInventario->ac1_prj_code = isset($detail['em1_project'])?$detail['em1_project']:NULL;
								$DetalleCostoInventario->em1_linetotal = is_numeric($detail['em1_linetotal'])?$detail['em1_linetotal']:0;
								$DetalleCostoInventario->em1_vat = is_numeric($detail['em1_vat'])?$detail['em1_vat']:0;
								$DetalleCostoInventario->em1_vatsum = is_numeric($detail['em1_vatsum'])?$detail['em1_vatsum']:0;
								$DetalleCostoInventario->em1_price = is_numeric($detail['em1_price'])?$detail['em1_price']:0;
								$DetalleCostoInventario->em1_itemcode = isset($detail['em1_itemcode'])?$detail['em1_itemcode']:NULL;
								$DetalleCostoInventario->em1_quantity = is_numeric($detail['em1_quantity'])?$detail['em1_quantity']:0;


								$DetalleCostoCosto->ac1_account = $resArticulo[0]['mga_acct_cost'];
								$DetalleCostoCosto->ac1_prc_code = isset($detail['em1_costcode'])?$detail['em1_costcode']:NULL;
								$DetalleCostoCosto->ac1_uncode = isset($detail['em1_ubusiness'])?$detail['em1_ubusiness']:NULL;
								$DetalleCostoCosto->ac1_prj_code = isset($detail['em1_project'])?$detail['em1_project']:NULL;
								$DetalleCostoCosto->em1_linetotal = is_numeric($detail['em1_linetotal'])?$detail['em1_linetotal']:0;
								$DetalleCostoCosto->em1_vat = is_numeric($detail['em1_vat'])?$detail['em1_vat']:0;
								$DetalleCostoCosto->em1_vatsum = is_numeric($detail['em1_vatsum'])?$detail['em1_vatsum']:0;
								$DetalleCostoCosto->em1_price = is_numeric($detail['em1_price'])?$detail['em1_price']:0;
								$DetalleCostoCosto->em1_itemcode = isset($detail['em1_itemcode'])?$detail['em1_itemcode']:NULL;
								$DetalleCostoCosto->em1_quantity = is_numeric($detail['em1_quantity'])?$detail['em1_quantity']:0;

								$codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);

								$DetalleAsientoIngreso->codigoCuenta = $codigoCuenta;
								$DetalleAsientoIva->codigoCuenta = $codigoCuenta;
								$DetalleCostoInventario->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);
								$DetalleCostoInventario->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);


								$llave = $DetalleAsientoIngreso->ac1_uncode.$DetalleAsientoIngreso->ac1_prc_code.$DetalleAsientoIngreso->ac1_prj_code.$DetalleAsientoIngreso->ac1_account;
								$llaveIva = $DetalleAsientoIva->em1_vat;
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
										$granTotalIngreso = ( $granTotalIngreso + $value->em1_linetotal );
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
									':ac1_doc_date' => $this->validateDate($Data['vem_docdate'])?$Data['vem_docdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['vem_duedate'])?$Data['vem_duedate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => $resInsert,
									':ac1_font_line' => 1,
									':ac1_font_type' => is_numeric($Data['vem_doctype'])?$Data['vem_doctype']:0,
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
									':ac1_made_user' => isset($Data['vem_createby'])?$Data['vem_createby']:NULL,
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
									':ac1_legal_num' => isset($Data['vem_cardcode'])?$Data['vem_cardcode']:NULL,
									':ac1_codref' => 1
						));



						if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
						}else{
								// si falla algun insert del detalle de la Entrega de Ventas se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resDetalleAsiento,
										'mensaje'	=> 'No se pudo registrar la Entrega de ventas'
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
										$granTotalIva = $granTotalIva + $value->em1_vatsum;
							}

							$MontoSysDB = ($granTotalIva / $resMonedaSys[0]['tsa_value']);


							$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

									':ac1_trans_id' => $resInsertAsiento,
									':ac1_account' => $value->em1_cuentaIva,
									':ac1_debit' => 0,
									':ac1_credit' => $granTotalIva,
									':ac1_debit_sys' => 0,
									':ac1_credit_sys' => round($MontoSysDB,2),
									':ac1_currex' => 0,
									':ac1_doc_date' => $this->validateDate($Data['vem_docdate'])?$Data['vem_docdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['vem_duedate'])?$Data['vem_duedate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => $resInsert,
									':ac1_font_line' => 1,
									':ac1_font_type' => is_numeric($Data['vem_doctype'])?$Data['vem_doctype']:0,
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
									':ac1_made_user' => isset($Data['vem_createby'])?$Data['vem_createby']:NULL,
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
									':ac1_legal_num' => isset($Data['vem_cardcode'])?$Data['vem_cardcode']:NULL,
									':ac1_codref' => 1
						));



						if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
						}else{

								// si falla algun insert del detalle de la Entrega de Ventas se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resDetalleAsiento,
										'mensaje'	=> 'No se pudo registrar la Entrega de ventas'
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

										$resArticulo = $this->pedeo->queryTable($sqlArticulo, array(":dma_item_code" => $value->em1_itemcode));

										if(isset($resArticulo[0])){
												$dbito = 0;
												$cdito = 0;

												$MontoSysDB = 0;
												$MontoSysCR = 0;

												$sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode";

												$resCosto = $this->pedeo->queryTable($sqlCosto, array(":bdi_itemcode" => $value->em1_itemcode));

												if( isset( $resCosto[0] ) ){

															$cuentaInventario = $resArticulo[0]['mga_acct_inv'];


															$costoArticulo = $resCosto[0]['bdi_avgprice'];
															$cantidadArticulo = $value->em1_quantity;
															$grantotalCostoInventario = ($grantotalCostoInventario + ($costoArticulo * $cantidadArticulo));

												}else{

															$this->pedeo->trans_rollback();

															$respuesta = array(
																'error'   => true,
																'data'	  => $resArticulo,
																'mensaje'	=> 'No se encontro el costo para el item: '.$value->em1_itemcode
															);

															 $this->response($respuesta);

															 return;
												}

										}else{
												// si falla algun insert del detalle de la Entrega de Ventas se devuelven los cambios realizados por la transaccion,
												// se retorna el error y se detiene la ejecucion del codigo restante.
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data'	  => $resArticulo,
													'mensaje'	=> 'No se encontro la cuenta de inventario y costo para el item '.$value->em1_itemcode
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
									':ac1_doc_date' => $this->validateDate($Data['vem_docdate'])?$Data['vem_docdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['vem_duedate'])?$Data['vem_duedate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => $resInsert,
									':ac1_font_line' => 1,
									':ac1_font_type' => is_numeric($Data['vem_doctype'])?$Data['vem_doctype']:0,
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
									':ac1_made_user' => isset($Data['vem_createby'])?$Data['vem_createby']:NULL,
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
									':ac1_legal_num' => isset($Data['vem_cardcode'])?$Data['vem_cardcode']:NULL,
									':ac1_codref' => 1
						));

						if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
						}else{

								// si falla algun insert del detalle de la Entrega de Ventas se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resDetalleAsiento,
										'mensaje'	=> 'No se pudo registrar la Entrega de ventas'
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

										$resArticulo = $this->pedeo->queryTable($sqlArticulo, array(":dma_item_code" => $value->em1_itemcode));

										if(isset($resArticulo[0])){
												$dbito = 0;
												$cdito = 0;
												$MontoSysDB = 0;
												$MontoSysCR = 0;

												$sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode";

												$resCosto = $this->pedeo->queryTable($sqlCosto, array(":bdi_itemcode" => $value->em1_itemcode));

												if( isset( $resCosto[0] ) ){

															$cuentaCosto = $resArticulo[0]['mga_acct_cost'];


															$costoArticulo = $resCosto[0]['bdi_avgprice'];
															$cantidadArticulo = $value->em1_quantity;
															$grantotalCostoCosto = ($grantotalCostoCosto + ($costoArticulo * $cantidadArticulo));

												}else{

															$this->pedeo->trans_rollback();

															$respuesta = array(
																'error'   => true,
																'data'	  => $resArticulo,
																'mensaje'	=> 'No se encontro el costo para el item: '.$value->em1_itemcode
															);

															 $this->response($respuesta);

															 return;
												}

										}else{
												// si falla algun insert del detalle de la Entrega de Ventas se devuelven los cambios realizados por la transaccion,
												// se retorna el error y se detiene la ejecucion del codigo restante.
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data'	  => $resArticulo,
													'mensaje'	=> 'No se encontro el costo para el item '.$value->em1_itemcode
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
								':ac1_doc_date' => $this->validateDate($Data['vem_docdate'])?$Data['vem_docdate']:NULL,
								':ac1_doc_duedate' => $this->validateDate($Data['vem_duedate'])?$Data['vem_duedate']:NULL,
								':ac1_debit_import' => 0,
								':ac1_credit_import' => 0,
								':ac1_debit_importsys' => 0,
								':ac1_credit_importsys' => 0,
								':ac1_font_key' => $resInsert,
								':ac1_font_line' => 1,
								':ac1_font_type' => is_numeric($Data['vem_doctype'])?$Data['vem_doctype']:0,
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
								':ac1_made_user' => isset($Data['vem_createby'])?$Data['vem_createby']:NULL,
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
								':ac1_legal_num' => isset($Data['vem_cardcode'])?$Data['vem_cardcode']:NULL,
								':ac1_codref' => 1
								));

								if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
								}else{

								// si falla algun insert del detalle de la Entrega de Ventas se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $resDetalleAsiento,
									'mensaje'	=> 'No se pudo registrar la Entrega de ventas'
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

					$rescuentaCxP = $this->pedeo->queryTable($sqlcuentaCxP, array(":dms_card_code" => $Data['vem_cardcode']));



					if(isset( $rescuentaCxP[0] )){

								$debitoo = 0;
								$creditoo = 0;
								$MontoSysDB = 0;
								$MontoSysCR = 0;

								$cuentaCxP = $rescuentaCxP[0]['mgs_acct'];

								$codigo2= substr($rescuentaCxP[0]['mgs_acct'], 0, 1);


								if( $codigo2 == 1 || $codigo2 == "1" ){
										$debitoo = $Data['vem_doctotal'];
										$MontoSysDB = ($debitoo / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo2 == 2 || $codigo2 == "2" ){
										$creditoo = $Data['vem_doctotal'];
										$MontoSysCR = ($creditoo / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo2 == 3 || $codigo2 == "3" ){
										$creditoo = $Data['vem_doctotal'];
										$MontoSysCR = ($creditoo / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo2 == 4 || $codigo2 == "4" ){
									  $creditoo = $Data['vem_doctotal'];
										$MontoSysCR = ($creditoo / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo2 == 5  || $codigo2 == "5" ){
									  $debitoo = $Data['vem_doctotal'];
										$MontoSysDB = ($debitoo / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo2 == 6 || $codigo2 == "6" ){
									  $debitoo = $Data['vem_doctotal'];
										$MontoSysDB = ($debitoo / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo2 == 7 || $codigo2 == "7" ){
									  $debitoo = $Data['vem_doctotal'];
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
										':ac1_doc_date' => $this->validateDate($Data['vem_docdate'])?$Data['vem_docdate']:NULL,
										':ac1_doc_duedate' => $this->validateDate($Data['vem_duedate'])?$Data['vem_duedate']:NULL,
										':ac1_debit_import' => 0,
										':ac1_credit_import' => 0,
										':ac1_debit_importsys' => 0,
										':ac1_credit_importsys' => 0,
										':ac1_font_key' => $resInsert,
										':ac1_font_line' => 1,
										':ac1_font_type' => is_numeric($Data['vem_doctype'])?$Data['vem_doctype']:0,
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
										':ac1_made_user' => isset($Data['vem_createby'])?$Data['vem_createby']:NULL,
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
										':ac1_legal_num' => isset($Data['vem_cardcode'])?$Data['vem_cardcode']:NULL,
										':ac1_codref' => 1
							));

							if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
									// Se verifica que el detalle no de error insertando //
							}else{

									// si falla algun insert del detalle de la Entrega de Ventas se devuelven los cambios realizados por la transaccion,
									// se retorna el error y se detiene la ejecucion del codigo restante.
										$this->pedeo->trans_rollback();

										$respuesta = array(
											'error'   => true,
											'data'	  => $resDetalleAsiento,
											'mensaje'	=> 'No se pudo registrar la Entrega de ventas'
										);

										 $this->response($respuesta);

										 return;
							}

					}else{

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $resDetalleAsiento,
									'mensaje'	=> 'No se pudo registrar la Entrega de ventas, el tercero no tiene cuenta asociada'
								);

								 $this->response($respuesta);

								 return;
					}
					//FIN Procedimiento para llenar cuentas por cobrar

					// Si todo sale bien despues de insertar el detalle de la Entrega de Ventas
					// se confirma la trasaccion  para que los cambios apliquen permanentemente
					// en la base de datos y se confirma la operacion exitosa.
					$this->pedeo->trans_commit();

          $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' =>'Entrega de ventas registrada con exito'
          );


        }else{
					// Se devuelven los cambios realizados en la transaccion
					// si occurre un error  y se muestra devuelve el error.
							$this->pedeo->trans_rollback();

              $respuesta = array(
                'error'   => true,
                'data' => $resInsert,
                'mensaje'	=> 'No se pudo registrar la Entrega de ventas'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR Entrega de Ventas
  public function updateSalesDel_post(){

      $Data = $this->post();

			if(!isset($Data['vem_docentry']) OR !isset($Data['vem_docnum']) OR
				 !isset($Data['vem_docdate']) OR !isset($Data['vem_duedate']) OR
				 !isset($Data['vem_duedev']) OR !isset($Data['vem_pricelist']) OR
				 !isset($Data['vem_cardcode']) OR !isset($Data['vem_cardname']) OR
				 !isset($Data['vem_currency']) OR !isset($Data['vem_contacid']) OR
				 !isset($Data['vem_slpcode']) OR !isset($Data['vem_empid']) OR
				 !isset($Data['vem_comment']) OR !isset($Data['vem_doctotal']) OR
				 !isset($Data['vem_baseamnt']) OR !isset($Data['vem_taxtotal']) OR
				 !isset($Data['vem_discprofit']) OR !isset($Data['vem_discount']) OR
				 !isset($Data['vem_createat']) OR !isset($Data['vem_baseentry']) OR
				 !isset($Data['vem_basetype']) OR !isset($Data['vem_doctype']) OR
				 !isset($Data['vem_idadd']) OR !isset($Data['vem_adress']) OR
				 !isset($Data['vem_paytype']) OR !isset($Data['vem_attch']) OR
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
            'mensaje' =>'No se encontro el detalle de la Entrega de ventas'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }

      $sqlUpdate = "UPDATE dvem	SET vem_docdate=:vem_docdate,vem_duedate=:vem_duedate, vem_duedev=:vem_duedev, vem_pricelist=:vem_pricelist, vem_cardcode=:vem_cardcode,
			  						vem_cardname=:vem_cardname, vem_currency=:vem_currency, vem_contacid=:vem_contacid, vem_slpcode=:vem_slpcode,
										vem_empid=:vem_empid, vem_comment=:vem_comment, vem_doctotal=:vem_doctotal, vem_baseamnt=:vem_baseamnt,
										vem_taxtotal=:vem_taxtotal, vem_discprofit=:vem_discprofit, vem_discount=:vem_discount, vem_createat=:vem_createat,
										vem_baseentry=:vem_baseentry, vem_basetype=:vem_basetype, vem_doctype=:vem_doctype, vem_idadd=:vem_idadd,
										vem_adress=:vem_adress, vem_paytype=:vem_paytype, vem_attch=:vem_attch WHERE vem_docentry=:vem_docentry";

      $this->pedeo->trans_begin();

      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
							':vem_docnum' => is_numeric($Data['vem_docnum'])?$Data['vem_docnum']:0,
							':vem_docdate' => $this->validateDate($Data['vem_docdate'])?$Data['vem_docdate']:NULL,
							':vem_duedate' => $this->validateDate($Data['vem_duedate'])?$Data['vem_duedate']:NULL,
							':vem_duedev' => $this->validateDate($Data['vem_duedev'])?$Data['vem_duedev']:NULL,
							':vem_pricelist' => is_numeric($Data['vem_pricelist'])?$Data['vem_pricelist']:0,
							':vem_cardcode' => isset($Data['vem_pricelist'])?$Data['vem_pricelist']:NULL,
							':vem_cardname' => isset($Data['vem_cardname'])?$Data['vem_cardname']:NULL,
							':vem_currency' => is_numeric($Data['vem_currency'])?$Data['vem_currency']:0,
							':vem_contacid' => isset($Data['vem_contacid'])?$Data['vem_contacid']:NULL,
							':vem_slpcode' => is_numeric($Data['vem_slpcode'])?$Data['vem_slpcode']:0,
							':vem_empid' => is_numeric($Data['vem_empid'])?$Data['vem_empid']:0,
							':vem_comment' => isset($Data['vem_comment'])?$Data['vem_comment']:NULL,
							':vem_doctotal' => is_numeric($Data['vem_doctotal'])?$Data['vem_doctotal']:0,
							':vem_baseamnt' => is_numeric($Data['vem_baseamnt'])?$Data['vem_baseamnt']:0,
							':vem_taxtotal' => is_numeric($Data['vem_taxtotal'])?$Data['vem_taxtotal']:0,
							':vem_discprofit' => is_numeric($Data['vem_discprofit'])?$Data['vem_discprofit']:0,
							':vem_discount' => is_numeric($Data['vem_discount'])?$Data['vem_discount']:0,
							':vem_createat' => $this->validateDate($Data['vem_createat'])?$Data['vem_createat']:NULL,
							':vem_baseentry' => is_numeric($Data['vem_baseentry'])?$Data['vem_baseentry']:0,
							':vem_basetype' => is_numeric($Data['vem_basetype'])?$Data['vem_basetype']:0,
							':vem_doctype' => is_numeric($Data['vem_doctype'])?$Data['vem_doctype']:0,
							':vem_idadd' => isset($Data['vem_idadd'])?$Data['vem_idadd']:NULL,
							':vem_adress' => isset($Data['vem_adress'])?$Data['vem_adress']:NULL,
							':vem_paytype' => is_numeric($Data['vem_paytype'])?$Data['vem_paytype']:0,
							':vem_attch' => $this->getUrl(count(trim(($Data['vem_attch']))) > 0 ? $Data['vem_attch']:NULL),
							':vem_docentry' => $Data['vem_docentry']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

						$this->pedeo->queryTable("DELETE FROM vem1 WHERE em1_docentry=:em1_docentry", array(':em1_docentry' => $Data['vem_docentry']));

						foreach ($ContenidoDetalle as $key => $detail) {

									$sqlInsertDetail = "INSERT INTO vem1(em1_docentry, em1_itemcode, em1_itemname, em1_quantity, em1_uom, em1_whscode,
																			em1_price, em1_vat, em1_vatsum, em1_discount, em1_linetotal, em1_costcode, em1_ubusiness, em1_project,
																			em1_acctcode, em1_basetype, em1_doctype, em1_avprice, em1_inventory)VALUES(:em1_docentry, :em1_itemcode, :em1_itemname, :em1_quantity,
																			:em1_uom, :em1_whscode,:em1_price, :em1_vat, :em1_vatsum, :em1_discount, :em1_linetotal, :em1_costcode, :em1_ubusiness, :em1_project,
																			:em1_acctcode, :em1_basetype, :em1_doctype, :em1_avprice, :em1_inventory)";

									$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
											':em1_docentry' => $resInsert,
											':em1_itemcode' => isset($detail['em1_itemcode'])?$detail['em1_itemcode']:NULL,
											':em1_itemname' => isset($detail['em1_itemname'])?$detail['em1_itemname']:NULL,
											':em1_quantity' => is_numeric($detail['em1_quantity'])?$detail['em1_quantity']:0,
											':em1_uom' => isset($detail['em1_uom'])?$detail['em1_uom']:NULL,
											':em1_whscode' => isset($detail['em1_whscode'])?$detail['em1_whscode']:NULL,
											':em1_price' => is_numeric($detail['em1_price'])?$detail['em1_price']:0,
											':em1_vat' => is_numeric($detail['em1_vat'])?$detail['em1_vat']:0,
											':em1_vatsum' => is_numeric($detail['em1_vatsum'])?$detail['em1_vatsum']:0,
											':em1_discount' => is_numeric($detail['em1_discount'])?$detail['em1_discount']:0,
											':em1_linetotal' => is_numeric($detail['em1_linetotal'])?$detail['em1_linetotal']:0,
											':em1_costcode' => isset($detail['em1_costcode'])?$detail['em1_costcode']:NULL,
											':em1_ubusiness' => isset($detail['em1_ubusiness'])?$detail['em1_ubusiness']:NULL,
											':em1_project' => isset($detail['em1_project'])?$detail['em1_project']:NULL,
											':em1_acctcode' => is_numeric($detail['em1_acctcode'])?$detail['em1_acctcode']:0,
											':em1_basetype' => is_numeric($detail['em1_basetype'])?$detail['em1_basetype']:0,
											':em1_doctype' => is_numeric($detail['em1_doctype'])?$detail['em1_doctype']:0,
											':em1_avprice' => is_numeric($detail['em1_avprice'])?$detail['em1_avprice']:0,
											':em1_inventory' => is_numeric($detail['em1_inventory'])?$detail['em1_inventory']:NULL
									));

									if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
											// Se verifica que el detalle no de error insertando //
									}else{

											// si falla algun insert del detalle de la Entrega de Ventas se devuelven los cambios realizados por la transaccion,
											// se retorna el error y se detiene la ejecucion del codigo restante.
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data' => $resInsert,
													'mensaje'	=> 'No se pudo registrar la Entrega de ventas'
												);

												 $this->response($respuesta);

												 return;
									}
						}


						$this->pedeo->trans_commit();

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Entrega de ventas actualizada con exito'
            );


      }else{

						$this->pedeo->trans_rollback();

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la Entrega de ventas'
            );

      }

       $this->response($respuesta);
  }


  //OBTENER Entrega de VentasES
  public function getSalesDel_get(){

        $sqlSelect = " SELECT * FROM dvem";

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


	//OBTENER Entrega de Ventas POR ID
	public function getSalesDelById_get(){

				$Data = $this->get();

				if(!isset($Data['vem_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM dvem WHERE vem_docentry =:vem_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":vem_docentry" => $Data['vem_docentry']));

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


	//OBTENER Entrega de Ventas DETALLE POR ID
	public function getSalesDelDetail_get(){

				$Data = $this->get();

				if(!isset($Data['em1_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM vem1 WHERE em1_docentry =:em1_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":em1_docentry" => $Data['em1_docentry']));

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
