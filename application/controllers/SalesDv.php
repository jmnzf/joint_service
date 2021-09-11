<?php
// Devolución de clientesES
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class SalesDv extends REST_Controller {

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

  //CREAR NUEVA Devolución de clientes
	public function createSalesDv_post(){

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
            'mensaje' =>'No se encontro el detalle de la Devolución de clientes'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }
				//BUSCANDO LA NUMERACION DEL DOCUMENTO
			  $sqlNumeracion = " SELECT pgs_nextnum,pgs_last_num FROM  pgdn WHERE pgs_id = :pgs_id";

				$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['vdv_series']));

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

				$resMonedaSys = $this->pedeo->queryTable($sqlMonedaSys, array(':pgm_system' => 1, ':tsa_date' => $Data['vdv_docdate']));

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

        $sqlInsert = "INSERT INTO dvdv(vdv_series, vdv_docnum, vdv_docdate, vdv_duedate, vdv_duedev, vdv_pricelist, vdv_cardcode,
                      vdv_cardname, vdv_currency, vdv_contacid, vdv_slpcode, vdv_empid, vdv_comment, vdv_doctotal, vdv_baseamnt, vdv_taxtotal,
                      vdv_discprofit, vdv_discount, vdv_createat, vdv_baseentry, vdv_basetype, vdv_doctype, vdv_idadd, vdv_adress, vdv_paytype,
                      vdv_attch,vdv_createby)VALUES(:vdv_series, :vdv_docnum, :vdv_docdate, :vdv_duedate, :vdv_duedev, :vdv_pricelist, :vdv_cardcode, :vdv_cardname,
                      :vdv_currency, :vdv_contacid, :vdv_slpcode, :vdv_empid, :vdv_comment, :vdv_doctotal, :vdv_baseamnt, :vdv_taxtotal, :vdv_discprofit, :vdv_discount,
                      :vdv_createat, :vdv_baseentry, :vdv_basetype, :vdv_doctype, :vdv_idadd, :vdv_adress, :vdv_paytype, :vdv_attch,:vdv_createby)";


				// Se Inicia la transaccion,
				// Todas las consultas de modificacion siguientes
				// aplicaran solo despues que se confirme la transaccion,
				// de lo contrario no se aplicaran los cambios y se devolvera
				// la base de datos a su estado original.

			  $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':vdv_docnum' => $DocNumVerificado,
              ':vdv_series' => is_numeric($Data['vdv_series'])?$Data['vdv_series']:0,
              ':vdv_docdate' => $this->validateDate($Data['vdv_docdate'])?$Data['vdv_docdate']:NULL,
              ':vdv_duedate' => $this->validateDate($Data['vdv_duedate'])?$Data['vdv_duedate']:NULL,
              ':vdv_duedev' => $this->validateDate($Data['vdv_duedev'])?$Data['vdv_duedev']:NULL,
              ':vdv_pricelist' => is_numeric($Data['vdv_pricelist'])?$Data['vdv_pricelist']:0,
              ':vdv_cardcode' => isset($Data['vdv_cardcode'])?$Data['vdv_cardcode']:NULL,
              ':vdv_cardname' => isset($Data['vdv_cardname'])?$Data['vdv_cardname']:NULL,
              ':vdv_currency' => is_numeric($Data['vdv_currency'])?$Data['vdv_currency']:0,
              ':vdv_contacid' => isset($Data['vdv_contacid'])?$Data['vdv_contacid']:NULL,
              ':vdv_slpcode' => is_numeric($Data['vdv_slpcode'])?$Data['vdv_slpcode']:0,
              ':vdv_empid' => is_numeric($Data['vdv_empid'])?$Data['vdv_empid']:0,
              ':vdv_comment' => isset($Data['vdv_comment'])?$Data['vdv_comment']:NULL,
              ':vdv_doctotal' => is_numeric($Data['vdv_doctotal'])?$Data['vdv_doctotal']:0,
              ':vdv_baseamnt' => is_numeric($Data['vdv_baseamnt'])?$Data['vdv_baseamnt']:0,
              ':vdv_taxtotal' => is_numeric($Data['vdv_taxtotal'])?$Data['vdv_taxtotal']:0,
              ':vdv_discprofit' => is_numeric($Data['vdv_discprofit'])?$Data['vdv_discprofit']:0,
              ':vdv_discount' => is_numeric($Data['vdv_discount'])?$Data['vdv_discount']:0,
              ':vdv_createat' => $this->validateDate($Data['vdv_createat'])?$Data['vdv_createat']:NULL,
              ':vdv_baseentry' => is_numeric($Data['vdv_baseentry'])?$Data['vdv_baseentry']:0,
              ':vdv_basetype' => is_numeric($Data['vdv_basetype'])?$Data['vdv_basetype']:0,
              ':vdv_doctype' => is_numeric($Data['vdv_doctype'])?$Data['vdv_doctype']:0,
              ':vdv_idadd' => isset($Data['vdv_idadd'])?$Data['vdv_idadd']:NULL,
              ':vdv_adress' => isset($Data['vdv_adress'])?$Data['vdv_adress']:NULL,
              ':vdv_paytype' => is_numeric($Data['vdv_paytype'])?$Data['vdv_paytype']:0,
							':vdv_createby' => isset($Data['vdv_createby'])?$Data['vdv_createby']:NULL,
              ':vdv_attch' => $this->getUrl(count(trim(($Data['vdv_attch']))) > 0 ? $Data['vdv_attch']:NULL)
						));

        if(is_numeric($resInsert) && $resInsert > 0){

					// Se actualiza la serie de la numeracion del documento

					$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
					$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
							':pgs_nextnum' => $DocNumVerificado,
							':pgs_id'      => $Data['vdv_series']
					));


					if(is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1){

					}else{
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resActualizarNumeracion,
									'mensaje'	=> 'No se pudo crear la Devolución de clientes'
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
							':mac_base_type' => is_numeric($Data['vdv_doctype'])?$Data['vdv_doctype']:0,
							':mac_base_entry' => $resInsert,
							':mac_doc_date' => $this->validateDate($Data['vdv_docdate'])?$Data['vdv_docdate']:NULL,
							':mac_doc_duedate' => $this->validateDate($Data['vdv_duedate'])?$Data['vdv_duedate']:NULL,
							':mac_legal_date' => $this->validateDate($Data['vdv_docdate'])?$Data['vdv_docdate']:NULL,
							':mac_ref1' => is_numeric($Data['vdv_doctype'])?$Data['vdv_doctype']:0,
							':mac_ref2' => "",
							':mac_ref3' => "",
							':mac_loc_total' => is_numeric($Data['vdv_doctotal'])?$Data['vdv_doctotal']:0,
							':mac_fc_total' => is_numeric($Data['vdv_doctotal'])?$Data['vdv_doctotal']:0,
							':mac_sys_total' => is_numeric($Data['vdv_doctotal'])?$Data['vdv_doctotal']:0,
							':mac_trans_dode' => 1,
							':mac_beline_nume' => 1,
							':mac_vat_date' => $this->validateDate($Data['vdv_docdate'])?$Data['vdv_docdate']:NULL,
							':mac_serie' => 1,
							':mac_number' => 1,
							':mac_bammntsys' => is_numeric($Data['vdv_baseamnt'])?$Data['vdv_baseamnt']:0,
							':mac_bammnt' => is_numeric($Data['vdv_baseamnt'])?$Data['vdv_baseamnt']:0,
							':mac_wtsum' => 1,
							':mac_vatsum' => is_numeric($Data['vdv_taxtotal'])?$Data['vdv_taxtotal']:0,
							':mac_comments' => isset($Data['vdv_comment'])?$Data['vdv_comment']:NULL,
							':mac_create_date' => $this->validateDate($Data['vdv_createat'])?$Data['vdv_createat']:NULL,
							':mac_made_usuer' => isset($Data['vdv_createby'])?$Data['vdv_createby']:NULL,
							':mac_update_date' => date("Y-m-d"),
							':mac_update_user' => isset($Data['vdv_createby'])?$Data['vdv_createby']:NULL
					));


					if(is_numeric($resInsertAsiento) && $resInsertAsiento > 0){
							// Se verifica que el detalle no de error insertando //
					}else{

							// si falla algun insert del detalle de la Devolución de clientes se devuelven los cambios realizados por la transaccion,
							// se retorna el error y se detiene la ejecucion del codigo restante.
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $resInsertAsiento,
									'mensaje'	=> 'No se pudo registrar la Devolución de clientes'
								);

								 $this->response($respuesta);

								 return;
					}


          foreach ($ContenidoDetalle as $key => $detail) {

                $sqlInsertDetail = "INSERT INTO vdv1(dv1_docentry, dv1_itemcode, dv1_itemname, dv1_quantity, dv1_uom, dv1_whscode,
                                    dv1_price, dv1_vat, dv1_vatsum, dv1_discount, dv1_linetotal, dv1_costcode, dv1_ubusiness, dv1_project,
                                    dv1_acctcode, dv1_basetype, dv1_doctype, dv1_avprice, dv1_inventory)VALUES(:dv1_docentry, :dv1_itemcode, :dv1_itemname, :dv1_quantity,
                                    :dv1_uom, :dv1_whscode,:dv1_price, :dv1_vat, :dv1_vatsum, :dv1_discount, :dv1_linetotal, :dv1_costcode, :dv1_ubusiness, :dv1_project,
                                    :dv1_acctcode, :dv1_basetype, :dv1_doctype, :dv1_avprice, :dv1_inventory)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':dv1_docentry' => $resInsert,
                        ':dv1_itemcode' => isset($detail['dv1_itemcode'])?$detail['dv1_itemcode']:NULL,
                        ':dv1_itemname' => isset($detail['dv1_itemname'])?$detail['dv1_itemname']:NULL,
                        ':dv1_quantity' => is_numeric($detail['dv1_quantity'])?$detail['dv1_quantity']:0,
                        ':dv1_uom' => isset($detail['dv1_uom'])?$detail['dv1_uom']:NULL,
                        ':dv1_whscode' => isset($detail['dv1_whscode'])?$detail['dv1_whscode']:NULL,
                        ':dv1_price' => is_numeric($detail['dv1_price'])?$detail['dv1_price']:0,
                        ':dv1_vat' => is_numeric($detail['dv1_vat'])?$detail['dv1_vat']:0,
                        ':dv1_vatsum' => is_numeric($detail['dv1_vatsum'])?$detail['dv1_vatsum']:0,
                        ':dv1_discount' => is_numeric($detail['dv1_discount'])?$detail['dv1_discount']:0,
                        ':dv1_linetotal' => is_numeric($detail['dv1_linetotal'])?$detail['dv1_linetotal']:0,
                        ':dv1_costcode' => isset($detail['dv1_costcode'])?$detail['dv1_costcode']:NULL,
                        ':dv1_ubusiness' => isset($detail['dv1_ubusiness'])?$detail['dv1_ubusiness']:NULL,
                        ':dv1_project' => isset($detail['dv1_project'])?$detail['dv1_project']:NULL,
                        ':dv1_acctcode' => is_numeric($detail['dv1_acctcode'])?$detail['dv1_acctcode']:0,
                        ':dv1_basetype' => is_numeric($detail['dv1_basetype'])?$detail['dv1_basetype']:0,
                        ':dv1_doctype' => is_numeric($detail['dv1_doctype'])?$detail['dv1_doctype']:0,
                        ':dv1_avprice' => is_numeric($detail['dv1_avprice'])?$detail['dv1_avprice']:0,
                        ':dv1_inventory' => is_numeric($detail['dv1_inventory'])?$detail['dv1_inventory']:NULL
                ));

								if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
										// Se verifica que el detalle no de error insertando //
								}else{

										// si falla algun insert del detalle de la Devolución de clientes se devuelven los cambios realizados por la transaccion,
										// se retorna el error y se detiene la ejecucion del codigo restante.
											$this->pedeo->trans_rollback();

											$respuesta = array(
												'error'   => true,
												'data' => $resInsert,
												'mensaje'	=> 'No se pudo registrar la Devolución de clientes'
											);

											 $this->response($respuesta);

											 return;
								}

								// si el item es inventariable
								if( $detail['dv1_articleInv'] == 1 || $detail['dv1_articleInv'] == "1" ){
										//Se aplica el movimiento de inventario
										$sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode, bmi_quantity, bmi_whscode, bmi_createat, bmi_createby, bmy_doctype, bmy_baseentry)
																					 VALUES (:bmi_itemcode, :bmi_quantity, :bmi_whscode, :bmi_createat, :bmi_createby, :bmy_doctype, :bmy_baseentry)";

										$sqlInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(

												 ':bmi_itemcode' => isset($detail['dv1_itemcode'])?$detail['dv1_itemcode']:NULL,
												 ':bmi_quantity' => is_numeric($detail['dv1_quantity'])? $detail['dv1_quantity'] * $Data['invtype']:0,
												 ':bmi_whscode'  => isset($detail['dv1_whscode'])?$detail['dv1_whscode']:NULL,
												 ':bmi_createat' => $this->validateDate($Data['vdv_createat'])?$Data['vdv_createat']:NULL,
												 ':bmi_createby' => isset($Data['vdv_createby'])?$Data['vdv_createby']:NULL,
												 ':bmy_doctype'  => is_numeric($Data['vdv_doctype'])?$Data['vdv_doctype']:0,
												 ':bmy_baseentry' => $resInsert

										));

										if(is_numeric($sqlInserMovimiento) && $sqlInserMovimiento > 0){
												// Se verifica que el detalle no de error insertando //
										}else{

												// si falla algun insert del detalle de la Devolución de clientes se devuelven los cambios realizados por la transaccion,
												// se retorna el error y se detiene la ejecucion del codigo restante.
													$this->pedeo->trans_rollback();

													$respuesta = array(
														'error'   => true,
														'data' => $sqlInserMovimiento,
														'mensaje'	=> 'No se pudo registrar la Devolución de clientes'
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

														':bdi_itemcode' => $detail['dv1_itemcode'],
														':bdi_whscode'  => $detail['dv1_whscode']
											));

											if(isset($resCostoCantidad[0])){

												if($resCostoCantidad[0]['bdi_quantity'] > 0){

														 $CantidadActual = $resCostoCantidad[0]['bdi_quantity'];
														 $CantidadNueva = $detail['dv1_quantity'];


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
																	 'mensaje'	=> 'No se pudo crear la Devolución de clientes'
																 );
														 }

												}else{

																 $this->pedeo->trans_rollback();

																 $respuesta = array(
																	 'error'   => true,
																	 'data'    => $resUpdateCostoCantidad,
																	 'mensaje' => 'No hay existencia para el item: '.$detail['dv1_itemcode']
																 );
												}

											}else{

														$this->pedeo->trans_rollback();

														$respuesta = array(
															'error'   => true,
															'data' 		=> $resInsertCostoCantidad,
															'mensaje'	=> 'El item no existe en el stock '.$detail['dv1_itemcode']
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


								$DetalleAsientoIngreso->ac1_account = is_numeric($detail['dv1_acctcode'])?$detail['dv1_acctcode']: 0;
								$DetalleAsientoIngreso->ac1_prc_code = isset($detail['dv1_costcode'])?$detail['dv1_costcode']:NULL;
								$DetalleAsientoIngreso->ac1_uncode = isset($detail['dv1_ubusiness'])?$detail['dv1_ubusiness']:NULL;
								$DetalleAsientoIngreso->ac1_prj_code = isset($detail['dv1_project'])?$detail['dv1_project']:NULL;
								$DetalleAsientoIngreso->dv1_linetotal = is_numeric($detail['dv1_linetotal'])?$detail['dv1_linetotal']:0;
								$DetalleAsientoIngreso->dv1_vat = is_numeric($detail['dv1_vat'])?$detail['dv1_vat']:0;
								$DetalleAsientoIngreso->dv1_vatsum = is_numeric($detail['dv1_vatsum'])?$detail['dv1_vatsum']:0;
								$DetalleAsientoIngreso->dv1_price = is_numeric($detail['dv1_price'])?$detail['dv1_price']:0;
								$DetalleAsientoIngreso->dv1_itemcode = isset($detail['dv1_itemcode'])?$detail['dv1_itemcode']:NULL;
								$DetalleAsientoIngreso->dv1_quantity = is_numeric($detail['dv1_quantity'])?$detail['dv1_quantity']:0;



								$DetalleAsientoIva->ac1_account = is_numeric($detail['dv1_acctcode'])?$detail['dv1_acctcode']: 0;
								$DetalleAsientoIva->ac1_prc_code = isset($detail['dv1_costcode'])?$detail['dv1_costcode']:NULL;
								$DetalleAsientoIva->ac1_uncode = isset($detail['dv1_ubusiness'])?$detail['dv1_ubusiness']:NULL;
								$DetalleAsientoIva->ac1_prj_code = isset($detail['dv1_project'])?$detail['dv1_project']:NULL;
								$DetalleAsientoIva->dv1_linetotal = is_numeric($detail['dv1_linetotal'])?$detail['dv1_linetotal']:0;
								$DetalleAsientoIva->dv1_vat = is_numeric($detail['dv1_vat'])?$detail['dv1_vat']:0;
								$DetalleAsientoIva->dv1_vatsum = is_numeric($detail['dv1_vatsum'])?$detail['dv1_vatsum']:0;
								$DetalleAsientoIva->dv1_price = is_numeric($detail['dv1_price'])?$detail['dv1_price']:0;
								$DetalleAsientoIva->dv1_itemcode = isset($detail['dv1_itemcode'])?$detail['dv1_itemcode']:NULL;
								$DetalleAsientoIva->dv1_quantity = is_numeric($detail['dv1_quantity'])?$detail['dv1_quantity']:0;
								$DetalleAsientoIva->dv1_cuentaIva = is_numeric($detail['dv1_cuentaIva'])?$detail['dv1_cuentaIva']:NULL;



								// se busca la cuenta contable del costoInventario y costoCosto
								$sqlArticulo = "SELECT f2.dma_item_code,  f1.mga_acct_inv, f1.mga_acct_cost FROM dmga f1 JOIN dmar f2 ON f1.mga_id  = f2.dma_group_code WHERE dma_item_code = :dma_item_code";

								$resArticulo = $this->pedeo->queryTable($sqlArticulo, array(":dma_item_code" => $detail['dv1_itemcode']));

								if(!isset($resArticulo[0])){

											$this->pedeo->trans_rollback();

											$respuesta = array(
												'error'   => true,
												'data' => $resArticulo,
												'mensaje'	=> 'No se pudo registrar la Devolución de clientes'
											);

											 $this->response($respuesta);

											 return;
								}


								$DetalleCostoInventario->ac1_account = $resArticulo[0]['mga_acct_inv'];
								$DetalleCostoInventario->ac1_prc_code = isset($detail['dv1_costcode'])?$detail['dv1_costcode']:NULL;
								$DetalleCostoInventario->ac1_uncode = isset($detail['dv1_ubusiness'])?$detail['dv1_ubusiness']:NULL;
								$DetalleCostoInventario->ac1_prj_code = isset($detail['dv1_project'])?$detail['dv1_project']:NULL;
								$DetalleCostoInventario->dv1_linetotal = is_numeric($detail['dv1_linetotal'])?$detail['dv1_linetotal']:0;
								$DetalleCostoInventario->dv1_vat = is_numeric($detail['dv1_vat'])?$detail['dv1_vat']:0;
								$DetalleCostoInventario->dv1_vatsum = is_numeric($detail['dv1_vatsum'])?$detail['dv1_vatsum']:0;
								$DetalleCostoInventario->dv1_price = is_numeric($detail['dv1_price'])?$detail['dv1_price']:0;
								$DetalleCostoInventario->dv1_itemcode = isset($detail['dv1_itemcode'])?$detail['dv1_itemcode']:NULL;
								$DetalleCostoInventario->dv1_quantity = is_numeric($detail['dv1_quantity'])?$detail['dv1_quantity']:0;


								$DetalleCostoCosto->ac1_account = $resArticulo[0]['mga_acct_cost'];
								$DetalleCostoCosto->ac1_prc_code = isset($detail['dv1_costcode'])?$detail['dv1_costcode']:NULL;
								$DetalleCostoCosto->ac1_uncode = isset($detail['dv1_ubusiness'])?$detail['dv1_ubusiness']:NULL;
								$DetalleCostoCosto->ac1_prj_code = isset($detail['dv1_project'])?$detail['dv1_project']:NULL;
								$DetalleCostoCosto->dv1_linetotal = is_numeric($detail['dv1_linetotal'])?$detail['dv1_linetotal']:0;
								$DetalleCostoCosto->dv1_vat = is_numeric($detail['dv1_vat'])?$detail['dv1_vat']:0;
								$DetalleCostoCosto->dv1_vatsum = is_numeric($detail['dv1_vatsum'])?$detail['dv1_vatsum']:0;
								$DetalleCostoCosto->dv1_price = is_numeric($detail['dv1_price'])?$detail['dv1_price']:0;
								$DetalleCostoCosto->dv1_itemcode = isset($detail['dv1_itemcode'])?$detail['dv1_itemcode']:NULL;
								$DetalleCostoCosto->dv1_quantity = is_numeric($detail['dv1_quantity'])?$detail['dv1_quantity']:0;

								$codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);

								$DetalleAsientoIngreso->codigoCuenta = $codigoCuenta;
								$DetalleAsientoIva->codigoCuenta = $codigoCuenta;
								$DetalleCostoInventario->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);
								$DetalleCostoInventario->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);


								$llave = $DetalleAsientoIngreso->ac1_uncode.$DetalleAsientoIngreso->ac1_prc_code.$DetalleAsientoIngreso->ac1_prj_code.$DetalleAsientoIngreso->ac1_account;
								$llaveIva = $DetalleAsientoIva->dv1_vat;
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
										$granTotalIngreso = ( $granTotalIngreso + $value->dv1_linetotal );
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
									':ac1_doc_date' => $this->validateDate($Data['vdv_docdate'])?$Data['vdv_docdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['vdv_duedate'])?$Data['vdv_duedate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => $resInsert,
									':ac1_font_line' => 1,
									':ac1_font_type' => is_numeric($Data['vdv_doctype'])?$Data['vdv_doctype']:0,
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
									':ac1_made_user' => isset($Data['vdv_createby'])?$Data['vdv_createby']:NULL,
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
									':ac1_legal_num' => isset($Data['vdv_cardcode'])?$Data['vdv_cardcode']:NULL,
									':ac1_codref' => 1
						));



						if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
						}else{
								// si falla algun insert del detalle de la Devolución de clientes se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resDetalleAsiento,
										'mensaje'	=> 'No se pudo registrar la Devolución de clientes'
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
										$granTotalIva = $granTotalIva + $value->dv1_vatsum;
							}

							$MontoSysDB = ($granTotalIva / $resMonedaSys[0]['tsa_value']);


							$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

									':ac1_trans_id' => $resInsertAsiento,
									':ac1_account' => $value->dv1_cuentaIva,
									':ac1_debit' => 0,
									':ac1_credit' => $granTotalIva,
									':ac1_debit_sys' => 0,
									':ac1_credit_sys' => round($MontoSysDB,2),
									':ac1_currex' => 0,
									':ac1_doc_date' => $this->validateDate($Data['vdv_docdate'])?$Data['vdv_docdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['vdv_duedate'])?$Data['vdv_duedate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => $resInsert,
									':ac1_font_line' => 1,
									':ac1_font_type' => is_numeric($Data['vdv_doctype'])?$Data['vdv_doctype']:0,
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
									':ac1_made_user' => isset($Data['vdv_createby'])?$Data['vdv_createby']:NULL,
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
									':ac1_legal_num' => isset($Data['vdv_cardcode'])?$Data['vdv_cardcode']:NULL,
									':ac1_codref' => 1
						));



						if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
						}else{

								// si falla algun insert del detalle de la Devolución de clientes se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resDetalleAsiento,
										'mensaje'	=> 'No se pudo registrar la Devolución de clientes'
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

										$resArticulo = $this->pedeo->queryTable($sqlArticulo, array(":dma_item_code" => $value->dv1_itemcode));

										if(isset($resArticulo[0])){
												$dbito = 0;
												$cdito = 0;

												$MontoSysDB = 0;
												$MontoSysCR = 0;

												$sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode";

												$resCosto = $this->pedeo->queryTable($sqlCosto, array(":bdi_itemcode" => $value->dv1_itemcode));

												if( isset( $resCosto[0] ) ){

															$cuentaInventario = $resArticulo[0]['mga_acct_inv'];


															$costoArticulo = $resCosto[0]['bdi_avgprice'];
															$cantidadArticulo = $value->dv1_quantity;
															$grantotalCostoInventario = ($grantotalCostoInventario + ($costoArticulo * $cantidadArticulo));

												}else{

															$this->pedeo->trans_rollback();

															$respuesta = array(
																'error'   => true,
																'data'	  => $resArticulo,
																'mensaje'	=> 'No se encontro el costo para el item: '.$value->dv1_itemcode
															);

															 $this->response($respuesta);

															 return;
												}

										}else{
												// si falla algun insert del detalle de la Devolución de clientes se devuelven los cambios realizados por la transaccion,
												// se retorna el error y se detiene la ejecucion del codigo restante.
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data'	  => $resArticulo,
													'mensaje'	=> 'No se encontro la cuenta de inventario y costo para el item '.$value->dv1_itemcode
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
									':ac1_doc_date' => $this->validateDate($Data['vdv_docdate'])?$Data['vdv_docdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['vdv_duedate'])?$Data['vdv_duedate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => $resInsert,
									':ac1_font_line' => 1,
									':ac1_font_type' => is_numeric($Data['vdv_doctype'])?$Data['vdv_doctype']:0,
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
									':ac1_made_user' => isset($Data['vdv_createby'])?$Data['vdv_createby']:NULL,
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
									':ac1_legal_num' => isset($Data['vdv_cardcode'])?$Data['vdv_cardcode']:NULL,
									':ac1_codref' => 1
						));

						if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
						}else{

								// si falla algun insert del detalle de la Devolución de clientes se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resDetalleAsiento,
										'mensaje'	=> 'No se pudo registrar la Devolución de clientes'
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

										$resArticulo = $this->pedeo->queryTable($sqlArticulo, array(":dma_item_code" => $value->dv1_itemcode));

										if(isset($resArticulo[0])){
												$dbito = 0;
												$cdito = 0;
												$MontoSysDB = 0;
												$MontoSysCR = 0;

												$sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode";

												$resCosto = $this->pedeo->queryTable($sqlCosto, array(":bdi_itemcode" => $value->dv1_itemcode));

												if( isset( $resCosto[0] ) ){

															$cuentaCosto = $resArticulo[0]['mga_acct_cost'];


															$costoArticulo = $resCosto[0]['bdi_avgprice'];
															$cantidadArticulo = $value->dv1_quantity;
															$grantotalCostoCosto = ($grantotalCostoCosto + ($costoArticulo * $cantidadArticulo));

												}else{

															$this->pedeo->trans_rollback();

															$respuesta = array(
																'error'   => true,
																'data'	  => $resArticulo,
																'mensaje'	=> 'No se encontro el costo para el item: '.$value->dv1_itemcode
															);

															 $this->response($respuesta);

															 return;
												}

										}else{
												// si falla algun insert del detalle de la Devolución de clientes se devuelven los cambios realizados por la transaccion,
												// se retorna el error y se detiene la ejecucion del codigo restante.
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data'	  => $resArticulo,
													'mensaje'	=> 'No se encontro el costo para el item '.$value->dv1_itemcode
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
								':ac1_doc_date' => $this->validateDate($Data['vdv_docdate'])?$Data['vdv_docdate']:NULL,
								':ac1_doc_duedate' => $this->validateDate($Data['vdv_duedate'])?$Data['vdv_duedate']:NULL,
								':ac1_debit_import' => 0,
								':ac1_credit_import' => 0,
								':ac1_debit_importsys' => 0,
								':ac1_credit_importsys' => 0,
								':ac1_font_key' => $resInsert,
								':ac1_font_line' => 1,
								':ac1_font_type' => is_numeric($Data['vdv_doctype'])?$Data['vdv_doctype']:0,
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
								':ac1_made_user' => isset($Data['vdv_createby'])?$Data['vdv_createby']:NULL,
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
								':ac1_legal_num' => isset($Data['vdv_cardcode'])?$Data['vdv_cardcode']:NULL,
								':ac1_codref' => 1
								));

								if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
								}else{

								// si falla algun insert del detalle de la Devolución de clientes se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $resDetalleAsiento,
									'mensaje'	=> 'No se pudo registrar la Devolución de clientes'
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

					$rescuentaCxP = $this->pedeo->queryTable($sqlcuentaCxP, array(":dms_card_code" => $Data['vdv_cardcode']));



					if(isset( $rescuentaCxP[0] )){

								$debitoo = 0;
								$creditoo = 0;
								$MontoSysDB = 0;
								$MontoSysCR = 0;

								$cuentaCxP = $rescuentaCxP[0]['mgs_acct'];

								$codigo2= substr($rescuentaCxP[0]['mgs_acct'], 0, 1);


								if( $codigo2 == 1 || $codigo2 == "1" ){
										$debitoo = $Data['vdv_doctotal'];
										$MontoSysDB = ($debitoo / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo2 == 2 || $codigo2 == "2" ){
										$creditoo = $Data['vdv_doctotal'];
										$MontoSysCR = ($creditoo / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo2 == 3 || $codigo2 == "3" ){
										$creditoo = $Data['vdv_doctotal'];
										$MontoSysCR = ($creditoo / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo2 == 4 || $codigo2 == "4" ){
									  $creditoo = $Data['vdv_doctotal'];
										$MontoSysCR = ($creditoo / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo2 == 5  || $codigo2 == "5" ){
									  $debitoo = $Data['vdv_doctotal'];
										$MontoSysDB = ($debitoo / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo2 == 6 || $codigo2 == "6" ){
									  $debitoo = $Data['vdv_doctotal'];
										$MontoSysDB = ($debitoo / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo2 == 7 || $codigo2 == "7" ){
									  $debitoo = $Data['vdv_doctotal'];
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
										':ac1_doc_date' => $this->validateDate($Data['vdv_docdate'])?$Data['vdv_docdate']:NULL,
										':ac1_doc_duedate' => $this->validateDate($Data['vdv_duedate'])?$Data['vdv_duedate']:NULL,
										':ac1_debit_import' => 0,
										':ac1_credit_import' => 0,
										':ac1_debit_importsys' => 0,
										':ac1_credit_importsys' => 0,
										':ac1_font_key' => $resInsert,
										':ac1_font_line' => 1,
										':ac1_font_type' => is_numeric($Data['vdv_doctype'])?$Data['vdv_doctype']:0,
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
										':ac1_made_user' => isset($Data['vdv_createby'])?$Data['vdv_createby']:NULL,
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
										':ac1_legal_num' => isset($Data['vdv_cardcode'])?$Data['vdv_cardcode']:NULL,
										':ac1_codref' => 1
							));

							if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
									// Se verifica que el detalle no de error insertando //
							}else{

									// si falla algun insert del detalle de la Devolución de clientes se devuelven los cambios realizados por la transaccion,
									// se retorna el error y se detiene la ejecucion del codigo restante.
										$this->pedeo->trans_rollback();

										$respuesta = array(
											'error'   => true,
											'data'	  => $resDetalleAsiento,
											'mensaje'	=> 'No se pudo registrar la Devolución de clientes'
										);

										 $this->response($respuesta);

										 return;
							}

					}else{

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $resDetalleAsiento,
									'mensaje'	=> 'No se pudo registrar la Devolución de clientes, el tercero no tiene cuenta asociada'
								);

								 $this->response($respuesta);

								 return;
					}
					//FIN Procedimiento para llenar cuentas por cobrar

					// Si todo sale bien despues de insertar el detalle de la Devolución de clientes
					// se confirma la trasaccion  para que los cambios apliquen permanentemente
					// en la base de datos y se confirma la operacion exitosa.
					$this->pedeo->trans_commit();

          $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' =>'Devolución de clientes registrada con exito'
          );


        }else{
					// Se devuelven los cambios realizados en la transaccion
					// si occurre un error  y se muestra devuelve el error.
							$this->pedeo->trans_rollback();

              $respuesta = array(
                'error'   => true,
                'data' => $resInsert,
                'mensaje'	=> 'No se pudo registrar la Devolución de clientes'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR Devolución de clientes
  public function updateSalesDv_post(){

      $Data = $this->post();

			if(!isset($Data['vdv_docentry']) OR !isset($Data['vdv_docnum']) OR
				 !isset($Data['vdv_docdate']) OR !isset($Data['vdv_duedate']) OR
				 !isset($Data['vdv_duedev']) OR !isset($Data['vdv_pricelist']) OR
				 !isset($Data['vdv_cardcode']) OR !isset($Data['vdv_cardname']) OR
				 !isset($Data['vdv_currency']) OR !isset($Data['vdv_contacid']) OR
				 !isset($Data['vdv_slpcode']) OR !isset($Data['vdv_empid']) OR
				 !isset($Data['vdv_comment']) OR !isset($Data['vdv_doctotal']) OR
				 !isset($Data['vdv_baseamnt']) OR !isset($Data['vdv_taxtotal']) OR
				 !isset($Data['vdv_discprofit']) OR !isset($Data['vdv_discount']) OR
				 !isset($Data['vdv_createat']) OR !isset($Data['vdv_baseentry']) OR
				 !isset($Data['vdv_basetype']) OR !isset($Data['vdv_doctype']) OR
				 !isset($Data['vdv_idadd']) OR !isset($Data['vdv_adress']) OR
				 !isset($Data['vdv_paytype']) OR !isset($Data['vdv_attch']) OR
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
            'mensaje' =>'No se encontro el detalle de la Devolución de clientes'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }

      $sqlUpdate = "UPDATE dvdv	SET vdv_docdate=:vdv_docdate,vdv_duedate=:vdv_duedate, vdv_duedev=:vdv_duedev, vdv_pricelist=:vdv_pricelist, vdv_cardcode=:vdv_cardcode,
			  						vdv_cardname=:vdv_cardname, vdv_currency=:vdv_currency, vdv_contacid=:vdv_contacid, vdv_slpcode=:vdv_slpcode,
										vdv_empid=:vdv_empid, vdv_comment=:vdv_comment, vdv_doctotal=:vdv_doctotal, vdv_baseamnt=:vdv_baseamnt,
										vdv_taxtotal=:vdv_taxtotal, vdv_discprofit=:vdv_discprofit, vdv_discount=:vdv_discount, vdv_createat=:vdv_createat,
										vdv_baseentry=:vdv_baseentry, vdv_basetype=:vdv_basetype, vdv_doctype=:vdv_doctype, vdv_idadd=:vdv_idadd,
										vdv_adress=:vdv_adress, vdv_paytype=:vdv_paytype, vdv_attch=:vdv_attch WHERE vdv_docentry=:vdv_docentry";

      $this->pedeo->trans_begin();

      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
							':vdv_docnum' => is_numeric($Data['vdv_docnum'])?$Data['vdv_docnum']:0,
							':vdv_docdate' => $this->validateDate($Data['vdv_docdate'])?$Data['vdv_docdate']:NULL,
							':vdv_duedate' => $this->validateDate($Data['vdv_duedate'])?$Data['vdv_duedate']:NULL,
							':vdv_duedev' => $this->validateDate($Data['vdv_duedev'])?$Data['vdv_duedev']:NULL,
							':vdv_pricelist' => is_numeric($Data['vdv_pricelist'])?$Data['vdv_pricelist']:0,
							':vdv_cardcode' => isset($Data['vdv_pricelist'])?$Data['vdv_pricelist']:NULL,
							':vdv_cardname' => isset($Data['vdv_cardname'])?$Data['vdv_cardname']:NULL,
							':vdv_currency' => is_numeric($Data['vdv_currency'])?$Data['vdv_currency']:0,
							':vdv_contacid' => isset($Data['vdv_contacid'])?$Data['vdv_contacid']:NULL,
							':vdv_slpcode' => is_numeric($Data['vdv_slpcode'])?$Data['vdv_slpcode']:0,
							':vdv_empid' => is_numeric($Data['vdv_empid'])?$Data['vdv_empid']:0,
							':vdv_comment' => isset($Data['vdv_comment'])?$Data['vdv_comment']:NULL,
							':vdv_doctotal' => is_numeric($Data['vdv_doctotal'])?$Data['vdv_doctotal']:0,
							':vdv_baseamnt' => is_numeric($Data['vdv_baseamnt'])?$Data['vdv_baseamnt']:0,
							':vdv_taxtotal' => is_numeric($Data['vdv_taxtotal'])?$Data['vdv_taxtotal']:0,
							':vdv_discprofit' => is_numeric($Data['vdv_discprofit'])?$Data['vdv_discprofit']:0,
							':vdv_discount' => is_numeric($Data['vdv_discount'])?$Data['vdv_discount']:0,
							':vdv_createat' => $this->validateDate($Data['vdv_createat'])?$Data['vdv_createat']:NULL,
							':vdv_baseentry' => is_numeric($Data['vdv_baseentry'])?$Data['vdv_baseentry']:0,
							':vdv_basetype' => is_numeric($Data['vdv_basetype'])?$Data['vdv_basetype']:0,
							':vdv_doctype' => is_numeric($Data['vdv_doctype'])?$Data['vdv_doctype']:0,
							':vdv_idadd' => isset($Data['vdv_idadd'])?$Data['vdv_idadd']:NULL,
							':vdv_adress' => isset($Data['vdv_adress'])?$Data['vdv_adress']:NULL,
							':vdv_paytype' => is_numeric($Data['vdv_paytype'])?$Data['vdv_paytype']:0,
							':vdv_attch' => $this->getUrl(count(trim(($Data['vdv_attch']))) > 0 ? $Data['vdv_attch']:NULL),
							':vdv_docentry' => $Data['vdv_docentry']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

						$this->pedeo->queryTable("DELETE FROM vdv1 WHERE dv1_docentry=:dv1_docentry", array(':dv1_docentry' => $Data['vdv_docentry']));

						foreach ($ContenidoDetalle as $key => $detail) {

									$sqlInsertDetail = "INSERT INTO vdv1(dv1_docentry, dv1_itemcode, dv1_itemname, dv1_quantity, dv1_uom, dv1_whscode,
																			dv1_price, dv1_vat, dv1_vatsum, dv1_discount, dv1_linetotal, dv1_costcode, dv1_ubusiness, dv1_project,
																			dv1_acctcode, dv1_basetype, dv1_doctype, dv1_avprice, dv1_inventory)VALUES(:dv1_docentry, :dv1_itemcode, :dv1_itemname, :dv1_quantity,
																			:dv1_uom, :dv1_whscode,:dv1_price, :dv1_vat, :dv1_vatsum, :dv1_discount, :dv1_linetotal, :dv1_costcode, :dv1_ubusiness, :dv1_project,
																			:dv1_acctcode, :dv1_basetype, :dv1_doctype, :dv1_avprice, :dv1_inventory)";

									$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
											':dv1_docentry' => $resInsert,
											':dv1_itemcode' => isset($detail['dv1_itemcode'])?$detail['dv1_itemcode']:NULL,
											':dv1_itemname' => isset($detail['dv1_itemname'])?$detail['dv1_itemname']:NULL,
											':dv1_quantity' => is_numeric($detail['dv1_quantity'])?$detail['dv1_quantity']:0,
											':dv1_uom' => isset($detail['dv1_uom'])?$detail['dv1_uom']:NULL,
											':dv1_whscode' => isset($detail['dv1_whscode'])?$detail['dv1_whscode']:NULL,
											':dv1_price' => is_numeric($detail['dv1_price'])?$detail['dv1_price']:0,
											':dv1_vat' => is_numeric($detail['dv1_vat'])?$detail['dv1_vat']:0,
											':dv1_vatsum' => is_numeric($detail['dv1_vatsum'])?$detail['dv1_vatsum']:0,
											':dv1_discount' => is_numeric($detail['dv1_discount'])?$detail['dv1_discount']:0,
											':dv1_linetotal' => is_numeric($detail['dv1_linetotal'])?$detail['dv1_linetotal']:0,
											':dv1_costcode' => isset($detail['dv1_costcode'])?$detail['dv1_costcode']:NULL,
											':dv1_ubusiness' => isset($detail['dv1_ubusiness'])?$detail['dv1_ubusiness']:NULL,
											':dv1_project' => isset($detail['dv1_project'])?$detail['dv1_project']:NULL,
											':dv1_acctcode' => is_numeric($detail['dv1_acctcode'])?$detail['dv1_acctcode']:0,
											':dv1_basetype' => is_numeric($detail['dv1_basetype'])?$detail['dv1_basetype']:0,
											':dv1_doctype' => is_numeric($detail['dv1_doctype'])?$detail['dv1_doctype']:0,
											':dv1_avprice' => is_numeric($detail['dv1_avprice'])?$detail['dv1_avprice']:0,
											':dv1_inventory' => is_numeric($detail['dv1_inventory'])?$detail['dv1_inventory']:NULL
									));

									if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
											// Se verifica que el detalle no de error insertando //
									}else{

											// si falla algun insert del detalle de la Devolución de clientes se devuelven los cambios realizados por la transaccion,
											// se retorna el error y se detiene la ejecucion del codigo restante.
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data' => $resInsert,
													'mensaje'	=> 'No se pudo registrar la Devolución de clientes'
												);

												 $this->response($respuesta);

												 return;
									}
						}


						$this->pedeo->trans_commit();

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Devolución de clientes actualizada con exito'
            );


      }else{

						$this->pedeo->trans_rollback();

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la Devolución de clientes'
            );

      }

       $this->response($respuesta);
  }


  //OBTENER Devolución de clientesES
  public function getSalesDv_get(){

        $sqlSelect = " SELECT * FROM dvdv";

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


	//OBTENER Devolución de clientes POR ID
	public function getSalesDvById_get(){

				$Data = $this->get();

				if(!isset($Data['vdv_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM dvdv WHERE vdv_docentry =:vdv_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":vdv_docentry" => $Data['vdv_docentry']));

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


	//OBTENER Devolución de clientes DETALLE POR ID
	public function getSalesDvDetail_get(){

				$Data = $this->get();

				if(!isset($Data['dv1_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM vdv1 WHERE dv1_docentry =:dv1_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":dv1_docentry" => $Data['dv1_docentry']));

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
