<?php
// DEVOLUCION DE COMPRAS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PurchaseRet extends REST_Controller {

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

  //CREAR NUEVA devolucion DE compras
	public function createPurchaseRet_post(){

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
            'mensaje' =>'No se encontro el detalle de la devolucion de compras'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }
				//BUSCANDO LA NUMERACION DEL DOCUMENTO
			  $sqlNumeracion = " SELECT pgs_nextnum,pgs_last_num FROM  pgdn WHERE pgs_id = :pgs_id";

				$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['cdc_series']));

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

				$resMonedaSys = $this->pedeo->queryTable($sqlMonedaSys, array(':pgm_system' => 1, ':tsa_date' => $Data['cdc_docdate']));

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

        $sqlInsert = "INSERT INTO dcdc(cdc_series, cdc_docnum, cdc_docdate, cdc_duedate, cdc_duedev, cdc_pricelist, cdc_cardcode,
                      cdc_cardname, cdc_currency, cdc_contacid, cdc_slpcode, cdc_empid, cdc_comment, cdc_doctotal, cdc_baseamnt, cdc_taxtotal,
                      cdc_discprofit, cdc_discount, cdc_createat, cdc_baseentry, cdc_basetype, cdc_doctype, cdc_idadd, cdc_adress, cdc_paytype,
                      cdc_attch,cdc_createby)VALUES(:cdc_series, :cdc_docnum, :cdc_docdate, :cdc_duedate, :cdc_duedev, :cdc_pricelist, :cdc_cardcode, :cdc_cardname,
                      :cdc_currency, :cdc_contacid, :cdc_slpcode, :cdc_empid, :cdc_comment, :cdc_doctotal, :cdc_baseamnt, :cdc_taxtotal, :cdc_discprofit, :cdc_discount,
                      :cdc_createat, :cdc_baseentry, :cdc_basetype, :cdc_doctype, :cdc_idadd, :cdc_adress, :cdc_paytype, :cdc_attch,:cdc_createby)";


				// Se Inicia la transaccion,
				// Todas las consultas de modificacion siguientes
				// aplicaran solo despues que se confirme la transaccion,
				// de lo contrario no se aplicaran los cambios y se devolvera
				// la base de datos a su estado original.

			  $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':cdc_docnum' => $DocNumVerificado,
              ':cdc_series' => is_numeric($Data['cdc_series'])?$Data['cdc_series']:0,
              ':cdc_docdate' => $this->validateDate($Data['cdc_docdate'])?$Data['cdc_docdate']:NULL,
              ':cdc_duedate' => $this->validateDate($Data['cdc_duedate'])?$Data['cdc_duedate']:NULL,
              ':cdc_duedev' => $this->validateDate($Data['cdc_duedev'])?$Data['cdc_duedev']:NULL,
              ':cdc_pricelist' => is_numeric($Data['cdc_pricelist'])?$Data['cdc_pricelist']:0,
              ':cdc_cardcode' => isset($Data['cdc_cardcode'])?$Data['cdc_cardcode']:NULL,
              ':cdc_cardname' => isset($Data['cdc_cardname'])?$Data['cdc_cardname']:NULL,
              ':cdc_currency' => isset($Data['cdc_currency'])?$Data['cdc_currency']:NULL,
              ':cdc_contacid' => isset($Data['cdc_contacid'])?$Data['cdc_contacid']:NULL,
              ':cdc_slpcode' => is_numeric($Data['cdc_slpcode'])?$Data['cdc_slpcode']:0,
              ':cdc_empid' => is_numeric($Data['cdc_empid'])?$Data['cdc_empid']:0,
              ':cdc_comment' => isset($Data['cdc_comment'])?$Data['cdc_comment']:NULL,
              ':cdc_doctotal' => is_numeric($Data['cdc_doctotal'])?$Data['cdc_doctotal']:0,
              ':cdc_baseamnt' => is_numeric($Data['cdc_baseamnt'])?$Data['cdc_baseamnt']:0,
              ':cdc_taxtotal' => is_numeric($Data['cdc_taxtotal'])?$Data['cdc_taxtotal']:0,
              ':cdc_discprofit' => is_numeric($Data['cdc_discprofit'])?$Data['cdc_discprofit']:0,
              ':cdc_discount' => is_numeric($Data['cdc_discount'])?$Data['cdc_discount']:0,
              ':cdc_createat' => $this->validateDate($Data['cdc_createat'])?$Data['cdc_createat']:NULL,
              ':cdc_baseentry' => is_numeric($Data['cdc_baseentry'])?$Data['cdc_baseentry']:0,
              ':cdc_basetype' => is_numeric($Data['cdc_basetype'])?$Data['cdc_basetype']:0,
              ':cdc_doctype' => is_numeric($Data['cdc_doctype'])?$Data['cdc_doctype']:0,
              ':cdc_idadd' => isset($Data['cdc_idadd'])?$Data['cdc_idadd']:NULL,
              ':cdc_adress' => isset($Data['cdc_adress'])?$Data['cdc_adress']:NULL,
              ':cdc_paytype' => is_numeric($Data['cdc_paytype'])?$Data['cdc_paytype']:0,
							':cdc_createby' => isset($Data['cdc_createby'])?$Data['cdc_createby']:NULL,
              ':cdc_attch' => $this->getUrl(count(trim(($Data['cdc_attch']))) > 0 ? $Data['cdc_attch']:NULL, $resMainFolder[0]['main_folder'])
						));

        if(is_numeric($resInsert) && $resInsert > 0){

					// Se actualiza la serie de la numeracion del documento

					$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
					$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
							':pgs_nextnum' => $DocNumVerificado,
							':pgs_id'      => $Data['cdc_series']
					));


					if(is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1){

					}else{
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resActualizarNumeracion,
									'mensaje'	=> 'No se pudo crear la devolucion de compras'
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
										':bed_doctype' => $Data['cdc_doctype'],
										':bed_status' => 1, //ESTADO CERRADO
										':bed_createby' => $Data['cdc_createby'],
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
									'mensaje'	=> 'No se pudo registrar la devolucion de compras'
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
							':mac_base_type' => is_numeric($Data['cdc_doctype'])?$Data['cdc_doctype']:0,
							':mac_base_entry' => $resInsert,
							':mac_doc_date' => $this->validateDate($Data['cdc_docdate'])?$Data['cdc_docdate']:NULL,
							':mac_doc_duedate' => $this->validateDate($Data['cdc_duedate'])?$Data['cdc_duedate']:NULL,
							':mac_legal_date' => $this->validateDate($Data['cdc_docdate'])?$Data['cdc_docdate']:NULL,
							':mac_ref1' => is_numeric($Data['cdc_doctype'])?$Data['cdc_doctype']:0,
							':mac_ref2' => "",
							':mac_ref3' => "",
							':mac_loc_total' => is_numeric($Data['cdc_doctotal'])?$Data['cdc_doctotal']:0,
							':mac_fc_total' => is_numeric($Data['cdc_doctotal'])?$Data['cdc_doctotal']:0,
							':mac_sys_total' => is_numeric($Data['cdc_doctotal'])?$Data['cdc_doctotal']:0,
							':mac_trans_dode' => 1,
							':mac_beline_nume' => 1,
							':mac_vat_date' => $this->validateDate($Data['cdc_docdate'])?$Data['cdc_docdate']:NULL,
							':mac_serie' => 1,
							':mac_number' => 1,
							':mac_bammntsys' => is_numeric($Data['cdc_baseamnt'])?$Data['cdc_baseamnt']:0,
							':mac_bammnt' => is_numeric($Data['cdc_baseamnt'])?$Data['cdc_baseamnt']:0,
							':mac_wtsum' => 1,
							':mac_vatsum' => is_numeric($Data['cdc_taxtotal'])?$Data['cdc_taxtotal']:0,
							':mac_comments' => isset($Data['cdc_comment'])?$Data['cdc_comment']:NULL,
							':mac_create_date' => $this->validateDate($Data['cdc_createat'])?$Data['cdc_createat']:NULL,
							':mac_made_usuer' => isset($Data['cdc_createby'])?$Data['cdc_createby']:NULL,
							':mac_update_date' => date("Y-m-d"),
							':mac_update_user' => isset($Data['cdc_createby'])?$Data['cdc_createby']:NULL
					));


					if(is_numeric($resInsertAsiento) && $resInsertAsiento > 0){
							// Se verifica que el detalle no de error insertando //
					}else{

							// si falla algun insert del detalle de la devolucion de compras se devuelven los cambios realizados por la transaccion,
							// se retorna el error y se detiene la ejecucion del codigo restante.
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $resInsertAsiento,
									'mensaje'	=> 'No se pudo registrar la devolucion de compras'
								);

								 $this->response($respuesta);

								 return;
					}



          foreach ($ContenidoDetalle as $key => $detail) {

                $sqlInsertDetail = "INSERT INTO cdc1(dc1_docentry, dc1_itemcode, dc1_itemname, dc1_quantity, dc1_uom, dc1_whscode,
                                    dc1_price, dc1_vat, dc1_vatsum, dc1_discount, dc1_linetotal, dc1_costcode, dc1_ubusiness, dc1_project,
                                    dc1_acctcode, dc1_basetype, dc1_doctype, dc1_avprice, dc1_inventory, dc1_acciva)VALUES(:dc1_docentry, :dc1_itemcode, :dc1_itemname, :dc1_quantity,
                                    :dc1_uom, :dc1_whscode,:dc1_price, :dc1_vat, :dc1_vatsum, :dc1_discount, :dc1_linetotal, :dc1_costcode, :dc1_ubusiness, :dc1_project,
                                    :dc1_acctcode, :dc1_basetype, :dc1_doctype, :dc1_avprice, :dc1_inventory, :dc1_acciva)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':dc1_docentry' => $resInsert,
                        ':dc1_itemcode' => isset($detail['dc1_itemcode'])?$detail['dc1_itemcode']:NULL,
                        ':dc1_itemname' => isset($detail['dc1_itemname'])?$detail['dc1_itemname']:NULL,
                        ':dc1_quantity' => is_numeric($detail['dc1_quantity'])?$detail['dc1_quantity']:0,
                        ':dc1_uom' => isset($detail['dc1_uom'])?$detail['dc1_uom']:NULL,
                        ':dc1_whscode' => isset($detail['dc1_whscode'])?$detail['dc1_whscode']:NULL,
                        ':dc1_price' => is_numeric($detail['dc1_price'])?$detail['dc1_price']:0,
                        ':dc1_vat' => is_numeric($detail['dc1_vat'])?$detail['dc1_vat']:0,
                        ':dc1_vatsum' => is_numeric($detail['dc1_vatsum'])?$detail['dc1_vatsum']:0,
                        ':dc1_discount' => is_numeric($detail['dc1_discount'])?$detail['dc1_discount']:0,
                        ':dc1_linetotal' => is_numeric($detail['dc1_linetotal'])?$detail['dc1_linetotal']:0,
                        ':dc1_costcode' => isset($detail['dc1_costcode'])?$detail['dc1_costcode']:NULL,
                        ':dc1_ubusiness' => isset($detail['dc1_ubusiness'])?$detail['dc1_ubusiness']:NULL,
                        ':dc1_project' => isset($detail['dc1_project'])?$detail['dc1_project']:NULL,
                        ':dc1_acctcode' => is_numeric($detail['dc1_acctcode'])?$detail['dc1_acctcode']:0,
                        ':dc1_basetype' => is_numeric($detail['dc1_basetype'])?$detail['dc1_basetype']:0,
                        ':dc1_doctype' => is_numeric($detail['dc1_doctype'])?$detail['dc1_doctype']:0,
                        ':dc1_avprice' => is_numeric($detail['dc1_avprice'])?$detail['dc1_avprice']:0,
                        ':dc1_inventory' => is_numeric($detail['dc1_inventory'])?$detail['dc1_inventory']:NULL,
												':dc1_acciva'  => is_numeric($detail['dc1_cuentaIva'])?$detail['dc1_cuentaIva']:0,
                ));

								if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
										// Se verifica que el detalle no de error insertando //
								}else{

										// si falla algun insert del detalle de la devolucion de compras se devuelven los cambios realizados por la transaccion,
										// se retorna el error y se detiene la ejecucion del codigo restante.
											$this->pedeo->trans_rollback();

											$respuesta = array(
												'error'   => true,
												'data' => $resInsertDetail,
												'mensaje'	=> 'No se pudo registrar la devolucion de compras'
											);

											 $this->response($respuesta);

											 return;
								}

								// // si el item es inventariable
								// if( $detail['dc1_articleInv'] == 1 || $detail['dc1_articleInv'] == "1" ){
								//
								// 		// se verifica de donde viene  el documento
								// 	  if($Data['cdc_basetype'] != 3){
								//
								// 			//se busca el costo del item en el momento de la creacion del documento de compra
								// 			// para almacenar en el movimiento de inventario
								//
								// 			$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode";
								// 			$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(':bdi_whscode' => $detail['dc1_whscode'], ':bdi_itemcode' => $detail['dc1_itemcode']));
								//
								//
								// 			if(isset($resCostoMomentoRegistro[0])){
								//
								//
								// 				//Se aplica el movimiento de inventario
								// 				$sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode, bmi_quantity, bmi_whscode, bmi_createat, bmi_createby, bmy_doctype, bmy_baseentry,bmi_cost)
								// 															 VALUES (:bmi_itemcode, :bmi_quantity, :bmi_whscode, :bmi_createat, :bmi_createby, :bmy_doctype, :bmy_baseentry, :bmi_cost)";
								//
								// 				$sqlInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(
								//
								// 						 ':bmi_itemcode' => isset($detail['dc1_itemcode'])?$detail['dc1_itemcode']:NULL,
								// 						 ':bmi_quantity' => is_numeric($detail['dc1_quantity'])? $detail['dc1_quantity'] * $Data['invtype']:0,
								// 						 ':bmi_whscode'  => isset($detail['dc1_whscode'])?$detail['dc1_whscode']:NULL,
								// 						 ':bmi_createat' => $this->validateDate($Data['cdc_createat'])?$Data['cdc_createat']:NULL,
								// 						 ':bmi_createby' => isset($Data['cdc_createby'])?$Data['cdc_createby']:NULL,
								// 						 ':bmy_doctype'  => is_numeric($Data['cdc_doctype'])?$Data['cdc_doctype']:0,
								// 						 ':bmy_baseentry' => $resInsert,
								// 						 ':bmi_cost'      => $resCostoMomentoRegistro[0]['bdi_avgprice']
								//
								// 				));
								//
								// 				if(is_numeric($sqlInserMovimiento) && $sqlInserMovimiento > 0){
								// 						// Se verifica que el detalle no de error insertando //
								// 				}else{
								//
								// 						// si falla algun insert del detalle de la devolucion de compras se devuelven los cambios realizados por la transaccion,
								// 						// se retorna el error y se detiene la ejecucion del codigo restante.
								// 							$this->pedeo->trans_rollback();
								//
								// 							$respuesta = array(
								// 								'error'   => true,
								// 								'data' => $sqlInserMovimiento,
								// 								'mensaje'	=> 'No se pudo registrar la devolucion de compras'
								// 							);
								//
								// 							 $this->response($respuesta);
								//
								// 							 return;
								// 				}
								//
								//
								//
								// 			}else{
								//
								// 					$this->pedeo->trans_rollback();
								//
								// 					$respuesta = array(
								// 						'error'   => true,
								// 						'data' => $resCostoMomentoRegistro,
								// 						'mensaje'	=> 'No se pudo registrar la devolucion de compras, no se encontro el costo del articulo'
								// 					);
								//
								// 					 $this->response($respuesta);
								//
								// 					 return;
								// 			}
								//
								// 			//FIN aplicacion de movimiento de inventario
								//
								//
								// 				//Se Aplica el movimiento en stock ***************
								// 				// Buscando item en el stock
								// 				$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
								// 															FROM tbdi
								// 															WHERE bdi_itemcode = :bdi_itemcode
								// 															AND bdi_whscode = :bdi_whscode";
								//
								// 				$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(
								//
								// 							':bdi_itemcode' => $detail['dc1_itemcode'],
								// 							':bdi_whscode'  => $detail['dc1_whscode']
								// 				));
								//
								// 				if(isset($resCostoCantidad[0])){
								//
								// 					if($resCostoCantidad[0]['bdi_quantity'] > 0){
								//
								// 							 $CantidadActual = $resCostoCantidad[0]['bdi_quantity'];
								// 							 $CantidadNueva = $detail['dc1_quantity'];
								//
								//
								// 							 $CantidadTotal = ($CantidadActual - $CantidadNueva);
								//
								// 							 $sqlUpdateCostoCantidad =  "UPDATE tbdi
								// 																					 SET bdi_quantity = :bdi_quantity
								// 																					 WHERE  bdi_id = :bdi_id";
								//
								// 							 $resUpdateCostoCantidad = $this->pedeo->updateRow($sqlUpdateCostoCantidad, array(
								//
								// 										 ':bdi_quantity' => $CantidadTotal,
								// 										 ':bdi_id' 			 => $resCostoCantidad[0]['bdi_id']
								// 							 ));
								//
								// 							 if(is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad == 1){
								//
								// 							 }else{
								//
								// 									 $this->pedeo->trans_rollback();
								//
								// 									 $respuesta = array(
								// 										 'error'   => true,
								// 										 'data'    => $resUpdateCostoCantidad,
								// 										 'mensaje'	=> 'No se pudo crear la devolucion de compras'
								// 									 );
								// 							 }
								//
								// 					}else{
								//
								// 									 $this->pedeo->trans_rollback();
								//
								// 									 $respuesta = array(
								// 										 'error'   => true,
								// 										 'data'    => $resUpdateCostoCantidad,
								// 										 'mensaje' => 'No hay existencia para el item: '.$detail['dc1_itemcode']
								// 									 );
								// 					}
								//
								// 				}else{
								//
								// 							$this->pedeo->trans_rollback();
								//
								// 							$respuesta = array(
								// 								'error'   => true,
								// 								'data' 		=> $resInsertCostoCantidad,
								// 								'mensaje'	=> 'El item no existe en el stock '.$detail['dc1_itemcode']
								// 							);
								//
								// 							 $this->response($respuesta);
								//
								// 							 return;
								// 				}
								//
								// 					//FIN de  Aplicacion del movimiento en stock
								//
								// 		}// EN CASO CONTRARIO NO SE MUEVE INVENTARIO
								//
								// }

								//LLENANDO DETALLE ASIENTO CONTABLES
								$DetalleAsientoIngreso = new stdClass();
								$DetalleAsientoIva = new stdClass();
								$DetalleCostoInventario = new stdClass();
								$DetalleCostoCosto = new stdClass();


								$DetalleAsientoIngreso->ac1_account = is_numeric($detail['dc1_acctcode'])?$detail['dc1_acctcode']: 0;
								$DetalleAsientoIngreso->ac1_prc_code = isset($detail['dc1_costcode'])?$detail['dc1_costcode']:NULL;
								$DetalleAsientoIngreso->ac1_uncode = isset($detail['dc1_ubusiness'])?$detail['dc1_ubusiness']:NULL;
								$DetalleAsientoIngreso->ac1_prj_code = isset($detail['dc1_project'])?$detail['dc1_project']:NULL;
								$DetalleAsientoIngreso->dc1_linetotal = is_numeric($detail['dc1_linetotal'])?$detail['dc1_linetotal']:0;
								$DetalleAsientoIngreso->dc1_vat = is_numeric($detail['dc1_vat'])?$detail['dc1_vat']:0;
								$DetalleAsientoIngreso->dc1_vatsum = is_numeric($detail['dc1_vatsum'])?$detail['dc1_vatsum']:0;
								$DetalleAsientoIngreso->dc1_price = is_numeric($detail['dc1_price'])?$detail['dc1_price']:0;
								$DetalleAsientoIngreso->dc1_itemcode = isset($detail['dc1_itemcode'])?$detail['dc1_itemcode']:NULL;
								$DetalleAsientoIngreso->dc1_quantity = is_numeric($detail['dc1_quantity'])?$detail['dc1_quantity']:0;
								$DetalleAsientoIngreso->em1_whscode = isset($detail['dc1_whscode'])?$detail['dc1_whscode']:NULL;



								$DetalleAsientoIva->ac1_account = is_numeric($detail['dc1_acctcode'])?$detail['dc1_acctcode']: 0;
								$DetalleAsientoIva->ac1_prc_code = isset($detail['dc1_costcode'])?$detail['dc1_costcode']:NULL;
								$DetalleAsientoIva->ac1_uncode = isset($detail['dc1_ubusiness'])?$detail['dc1_ubusiness']:NULL;
								$DetalleAsientoIva->ac1_prj_code = isset($detail['dc1_project'])?$detail['dc1_project']:NULL;
								$DetalleAsientoIva->dc1_linetotal = is_numeric($detail['dc1_linetotal'])?$detail['dc1_linetotal']:0;
								$DetalleAsientoIva->dc1_vat = is_numeric($detail['dc1_vat'])?$detail['dc1_vat']:0;
								$DetalleAsientoIva->dc1_vatsum = is_numeric($detail['dc1_vatsum'])?$detail['dc1_vatsum']:0;
								$DetalleAsientoIva->dc1_price = is_numeric($detail['dc1_price'])?$detail['dc1_price']:0;
								$DetalleAsientoIva->dc1_itemcode = isset($detail['dc1_itemcode'])?$detail['dc1_itemcode']:NULL;
								$DetalleAsientoIva->dc1_quantity = is_numeric($detail['dc1_quantity'])?$detail['dc1_quantity']:0;
								$DetalleAsientoIva->dc1_cuentaIva = is_numeric($detail['dc1_cuentaIva'])?$detail['dc1_cuentaIva']:NULL;
								$DetalleAsientoIva->em1_whscode = isset($detail['dc1_whscode'])?$detail['dc1_whscode']:NULL;



								// se busca la cuenta contable del costoInventario y costoCosto
								$sqlArticulo = "SELECT f2.dma_item_code,  f1.mga_acct_inv, f1.mga_acct_cost FROM dmga f1 JOIN dmar f2 ON f1.mga_id  = f2.dma_group_code WHERE dma_item_code = :dma_item_code";

								$resArticulo = $this->pedeo->queryTable($sqlArticulo, array(":dma_item_code" => $detail['dc1_itemcode']));

								if(!isset($resArticulo[0])){

											$this->pedeo->trans_rollback();

											$respuesta = array(
												'error'   => true,
												'data' => $resArticulo,
												'mensaje'	=> 'No se pudo registrar la devolucion de compras'
											);

											 $this->response($respuesta);

											 return;
								}


								$DetalleCostoInventario->ac1_account = $resArticulo[0]['mga_acct_inv'];
								$DetalleCostoInventario->ac1_prc_code = isset($detail['dc1_costcode'])?$detail['dc1_costcode']:NULL;
								$DetalleCostoInventario->ac1_uncode = isset($detail['dc1_ubusiness'])?$detail['dc1_ubusiness']:NULL;
								$DetalleCostoInventario->ac1_prj_code = isset($detail['dc1_project'])?$detail['dc1_project']:NULL;
								$DetalleCostoInventario->dc1_linetotal = is_numeric($detail['dc1_linetotal'])?$detail['dc1_linetotal']:0;
								$DetalleCostoInventario->dc1_vat = is_numeric($detail['dc1_vat'])?$detail['dc1_vat']:0;
								$DetalleCostoInventario->dc1_vatsum = is_numeric($detail['dc1_vatsum'])?$detail['dc1_vatsum']:0;
								$DetalleCostoInventario->dc1_price = is_numeric($detail['dc1_price'])?$detail['dc1_price']:0;
								$DetalleCostoInventario->dc1_itemcode = isset($detail['dc1_itemcode'])?$detail['dc1_itemcode']:NULL;
								$DetalleCostoInventario->dc1_quantity = is_numeric($detail['dc1_quantity'])?$detail['dc1_quantity']:0;
								$DetalleCostoInventario->em1_whscode = isset($detail['dc1_whscode'])?$detail['dc1_whscode']:NULL;


								$DetalleCostoCosto->ac1_account = $resArticulo[0]['mga_acct_cost'];
								$DetalleCostoCosto->ac1_prc_code = isset($detail['dc1_costcode'])?$detail['dc1_costcode']:NULL;
								$DetalleCostoCosto->ac1_uncode = isset($detail['dc1_ubusiness'])?$detail['dc1_ubusiness']:NULL;
								$DetalleCostoCosto->ac1_prj_code = isset($detail['dc1_project'])?$detail['dc1_project']:NULL;
								$DetalleCostoCosto->dc1_linetotal = is_numeric($detail['dc1_linetotal'])?$detail['dc1_linetotal']:0;
								$DetalleCostoCosto->dc1_vat = is_numeric($detail['dc1_vat'])?$detail['dc1_vat']:0;
								$DetalleCostoCosto->dc1_vatsum = is_numeric($detail['dc1_vatsum'])?$detail['dc1_vatsum']:0;
								$DetalleCostoCosto->dc1_price = is_numeric($detail['dc1_price'])?$detail['dc1_price']:0;
								$DetalleCostoCosto->dc1_itemcode = isset($detail['dc1_itemcode'])?$detail['dc1_itemcode']:NULL;
								$DetalleCostoCosto->dc1_quantity = is_numeric($detail['dc1_quantity'])?$detail['dc1_quantity']:0;
								$DetalleCostoCosto->em1_whscode = isset($detail['dc1_whscode'])?$detail['dc1_whscode']:NULL;

								$codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);

								$DetalleAsientoIngreso->codigoCuenta = $codigoCuenta;
								$DetalleAsientoIva->codigoCuenta = $codigoCuenta;
								$DetalleCostoInventario->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);
								$DetalleCostoInventario->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);


								$llave = $DetalleAsientoIngreso->ac1_uncode.$DetalleAsientoIngreso->ac1_prc_code.$DetalleAsientoIngreso->ac1_prj_code.$DetalleAsientoIngreso->ac1_account;
								$llaveIva = $DetalleAsientoIva->dc1_vat;
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
										$granTotalIngreso = ( $granTotalIngreso + $value->dc1_linetotal );
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
									':ac1_doc_date' => $this->validateDate($Data['cdc_docdate'])?$Data['cdc_docdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['cdc_duedate'])?$Data['cdc_duedate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => $resInsert,
									':ac1_font_line' => 1,
									':ac1_font_type' => is_numeric($Data['cdc_doctype'])?$Data['cdc_doctype']:0,
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
									':ac1_made_user' => isset($Data['cdc_createby'])?$Data['cdc_createby']:NULL,
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
									':ac1_legal_num' => isset($Data['cdc_cardcode'])?$Data['cdc_cardcode']:NULL,
									':ac1_codref' => 1
						));



						if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
						}else{
								// si falla algun insert del detalle de la devolucion de compras se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resDetalleAsiento,
										'mensaje'	=> 'No se pudo registrar la devolucion de compras'
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
										$granTotalIva = $granTotalIva + $value->dc1_vatsum;
							}

							$MontoSysDB = ($granTotalIva / $resMonedaSys[0]['tsa_value']);


							$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

									':ac1_trans_id' => $resInsertAsiento,
									':ac1_account' => $value->dc1_cuentaIva,
									':ac1_debit' => 0,
									':ac1_credit' => $granTotalIva,
									':ac1_debit_sys' => 0,
									':ac1_credit_sys' => round($MontoSysDB,2),
									':ac1_currex' => 0,
									':ac1_doc_date' => $this->validateDate($Data['cdc_docdate'])?$Data['cdc_docdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['cdc_duedate'])?$Data['cdc_duedate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => $resInsert,
									':ac1_font_line' => 1,
									':ac1_font_type' => is_numeric($Data['cdc_doctype'])?$Data['cdc_doctype']:0,
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
									':ac1_made_user' => isset($Data['cdc_createby'])?$Data['cdc_createby']:NULL,
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
									':ac1_legal_num' => isset($Data['cdc_cardcode'])?$Data['cdc_cardcode']:NULL,
									':ac1_codref' => 1
						));



						if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
						}else{

								// si falla algun insert del detalle de la devolucion de compras se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resDetalleAsiento,
										'mensaje'	=> 'No se pudo registrar la devolucion de compras'
									);

									 $this->response($respuesta);

									 return;
						}
					}

					//FIN Procedimiento para llenar Impuestos

					if($Data['cdc_basetype'] != 3){ // solo si el documento no viene de una entrada
							//Procedimiento para llenar costo inventario
							foreach ($DetalleConsolidadoCostoInventario as $key => $posicion) {
									$grantotalCostoInventario = 0 ;
									$cuentaInventario = "";
									foreach ($posicion as $key => $value) {

												$sqlArticulo = "SELECT f2.dma_item_code,  f1.mga_acct_inv, f1.mga_acct_cost FROM dmga f1 JOIN dmar f2 ON f1.mga_id  = f2.dma_group_code WHERE dma_item_code = :dma_item_code";

												$resArticulo = $this->pedeo->queryTable($sqlArticulo, array(":dma_item_code" => $value->dc1_itemcode));

												if(isset($resArticulo[0])){
														$dbito = 0;
														$cdito = 0;

														$MontoSysDB = 0;
														$MontoSysCR = 0;

														$sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode";

														$resCosto = $this->pedeo->queryTable($sqlCosto, array(":bdi_itemcode" => $value->dc1_itemcode));

														if( isset( $resCosto[0] ) ){

																	$cuentaInventario = $resArticulo[0]['mga_acct_inv'];


																	$costoArticulo = $resCosto[0]['bdi_avgprice'];
																	$cantidadArticulo = $value->dc1_quantity;
																	$grantotalCostoInventario = ($grantotalCostoInventario + ($costoArticulo * $cantidadArticulo));

														}else{

																	$this->pedeo->trans_rollback();

																	$respuesta = array(
																		'error'   => true,
																		'data'	  => $resArticulo,
																		'mensaje'	=> 'No se encontro el costo para el item: '.$value->dc1_itemcode
																	);

																	 $this->response($respuesta);

																	 return;
														}

												}else{
														// si falla algun insert del detalle de la devolucion de compras se devuelven los cambios realizados por la transaccion,
														// se retorna el error y se detiene la ejecucion del codigo restante.
														$this->pedeo->trans_rollback();

														$respuesta = array(
															'error'   => true,
															'data'	  => $resArticulo,
															'mensaje'	=> 'No se encontro la cuenta de inventario y costo para el item '.$value->dc1_itemcode
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
											':ac1_doc_date' => $this->validateDate($Data['cdc_docdate'])?$Data['cdc_docdate']:NULL,
											':ac1_doc_duedate' => $this->validateDate($Data['cdc_duedate'])?$Data['cdc_duedate']:NULL,
											':ac1_debit_import' => 0,
											':ac1_credit_import' => 0,
											':ac1_debit_importsys' => 0,
											':ac1_credit_importsys' => 0,
											':ac1_font_key' => $resInsert,
											':ac1_font_line' => 1,
											':ac1_font_type' => is_numeric($Data['cdc_doctype'])?$Data['cdc_doctype']:0,
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
											':ac1_made_user' => isset($Data['cdc_createby'])?$Data['cdc_createby']:NULL,
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
											':ac1_legal_num' => isset($Data['cdc_cardcode'])?$Data['cdc_cardcode']:NULL,
											':ac1_codref' => 1
								));

								if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
										// Se verifica que el detalle no de error insertando //
								}else{

										// si falla algun insert del detalle de la devolucion de compras se devuelven los cambios realizados por la transaccion,
										// se retorna el error y se detiene la ejecucion del codigo restante.
											$this->pedeo->trans_rollback();

											$respuesta = array(
												'error'   => true,
												'data'	  => $resDetalleAsiento,
												'mensaje'	=> 'No se pudo registrar la devolucion de compras'
											);

											 $this->response($respuesta);

											 return;
								}

							}
					}	//FIN Procedimiento para llenar costo inventario




					// Procedimiento para llenar costo costo
					foreach ($DetalleConsolidadoCostoCosto as $key => $posicion) {
							$grantotalCostoCosto = 0 ;
							$cuentaCosto = "";
							$dbito = 0;
							$cdito = 0;
							$MontoSysDB = 0;
							$MontoSysCR = 0;
							foreach ($posicion as $key => $value) {

										if($Data['cdc_basetype'] != 3){

												$sqlArticulo = "SELECT f2.dma_item_code,  f1.mga_acct_inv, f1.mga_acct_cost FROM dmga f1 JOIN dmar f2 ON f1.mga_id  = f2.dma_group_code WHERE dma_item_code = :dma_item_code";
												$resArticulo = $this->pedeo->queryTable($sqlArticulo, array(":dma_item_code" => $value->dc1_itemcode));

												if(isset($resArticulo[0])){
														$dbito = 0;
														$cdito = 0;
														$MontoSysDB = 0;
														$MontoSysCR = 0;

														$sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode";

														$resCosto = $this->pedeo->queryTable($sqlCosto, array(":bdi_itemcode" => $value->dc1_itemcode));

														if( isset( $resCosto[0] ) ){

																	$cuentaCosto = $resArticulo[0]['mga_acct_cost'];


																	$costoArticulo = $resCosto[0]['bdi_avgprice'];
																	$cantidadArticulo = $value->dc1_quantity;
																	$grantotalCostoCosto = ($grantotalCostoCosto + ($costoArticulo * $cantidadArticulo));

														}else{

																	$this->pedeo->trans_rollback();

																	$respuesta = array(
																		'error'   => true,
																		'data'	  => $resArticulo,
																		'mensaje'	=> 'No se encontro el costo para el item: '.$value->dc1_itemcode
																	);

																	 $this->response($respuesta);

																	 return;
														}

												}else{
														// si falla algun insert del detalle de la devolucion de compras se devuelven los cambios realizados por la transaccion,
														// se retorna el error y se detiene la ejecucion del codigo restante.
														$this->pedeo->trans_rollback();

														$respuesta = array(
															'error'   => true,
															'data'	  => $resArticulo,
															'mensaje'	=> 'No se encontro la cuenta puente para costo'
														);

														 $this->response($respuesta);

														 return;
												}

										}else if($Data['cdc_basetype'] == 3){//Procedimiento cuando sea tipo documento 3

												$sqlArticulo = "SELECT pge_bridge_inv FROM pgem"; // Cuenta costo puente
												$resArticulo = $this->pedeo->queryTable($sqlArticulo, array());// Cuenta costo puente

												if(isset($resArticulo[0])){
														$dbito = 0;
														$cdito = 0;
														$MontoSysDB = 0;
														$MontoSysCR = 0;


														$sqlCosto = "SELECT
																					CASE
																						WHEN bmi_quantity < 0 THEN bmi_quantity * -1
																						ELSE bmi_quantity
																					END AS cantidad, bmi_cost,bmy_baseentry,bmy_doctype
																				FROM tbmi
																				WHERE bmy_doctype = :bmy_doctype
																				AND bmy_baseentry = :bmy_baseentry
																				AND bmi_itemcode  = :bmi_itemcode";

														$resCosto = $this->pedeo->queryTable($sqlCosto, array(":bmi_itemcode" => $value->dc1_itemcode, ':bmy_doctype' => $Data['cdc_basetype'], ':bmy_baseentry' => $Data['cdc_baseentry']));

														if( isset( $resCosto[0] ) ){

																	$cuentaCosto = $resArticulo[0]['pge_bridge_inv'];
																	$costoArticulo = $resCosto[0]['bmi_cost'];

																	// SE VALIDA QUE LA CANTIDAD DEL ITEM A DEVOLVER NO SUPERE LA CANTIDAD EN EL DOCUMENTO DE ENTREGA

																	if($value->dc1_quantity > $resCosto[0]['cantidad']){
																				//Se devuelve la transaccion
																				$this->pedeo->trans_rollback();

																				$respuesta = array(
																					'error'   => true,
																					'data'	  => $resArticulo,
																					'mensaje'	=> 'La cantidad a devolucion es mayor a la entregada, para el item: '.$value->dc1_itemcode
																				);

																				 $this->response($respuesta);

																				 return;
																	}

																	//SE VALIDA QUE EL TOTAL devolucionDO NO SUPERE EL TOTAL ENTEGRADO

																	$sqldevoluciondoItem = "SELECT coalesce((SUM(dc1_quantity)), 0) AS cantidaditem
																												FROM dcdc
																												INNER JOIN cdc1
																												ON cdc_docentry = dc1_docentry
																												WHERE cdc_baseentry = :cdc_baseentry
																												AND dc1_itemcode = :dc1_itemcode
																												AND cdc_basetype = :cdc_basetype";


																	$resdevoluciondoItem = $this->pedeo->queryTable($sqldevoluciondoItem, array(

																					':cdc_baseentry' =>  $resCosto[0]['bmy_baseentry'],
																					':dc1_itemcode'  =>  $value->dc1_itemcode,
																					':cdc_basetype'  =>  $resCosto[0]['bmy_doctype']
																	));


																	if ( isset($resdevoluciondoItem[0]) ){

																			$CantidadOriginal = ($resdevoluciondoItem[0]['cantidaditem'] - $value->dc1_quantity);

																			if ( $CantidadOriginal >= $resCosto[0]['cantidad'] ){
																						//Se devuelve la transaccion
																						$this->pedeo->trans_rollback();
																						$respuesta = array(
																							'error'   => true,
																							'data'	  => $resArticulo,
																							'mensaje'	=> 'No se puede devolver una cantidad mayor a la entregada, para el item: '.$value->dc1_itemcode
																						);

																						 $this->response($respuesta);

																						 return;
																			}else{

																					$resto = ($resCosto[0]['cantidad'] - $CantidadOriginal);

																					if($value->dc1_quantity > $resto){

																							$this->pedeo->trans_rollback();
																							$respuesta = array(
																								'error'   => true,
																								'data'	  => $resArticulo,
																								'mensaje'	=> 'No se puede devolver una cantidad mayor a la entregada, para el item: '.$value->dc1_itemcode
																							);

																							 $this->response($respuesta);

																							 return;
																					}

																			}
																	}

																	$cantidadArticulo = $value->dc1_quantity;
																	$grantotalCostoCosto = ($grantotalCostoCosto + ($costoArticulo * $cantidadArticulo));

														}else{
																	//Se devuelve la transaccion
																	$this->pedeo->trans_rollback();

																	$respuesta = array(
																		'error'   => true,
																		'data'	  => $resArticulo,
																		'mensaje'	=> 'No se encontro el costo para el item: '.$value->dc1_itemcode
																	);

																	 $this->response($respuesta);

																	 return;
														}

												}else{
														// si falla algun insert del detalle de la devolucion de compras se devuelven los cambios realizados por la transaccion,
														// se retorna el error y se detiene la ejecucion del codigo restante.
														$this->pedeo->trans_rollback();

														$respuesta = array(
															'error'   => true,
															'data'	  => $resArticulo,
															'mensaje'	=> 'No se encontro la cuenta puente para costo'
														);

														 $this->response($respuesta);

														 return;
												}

										}

							}



								$codigo3 = substr($cuentaCosto, 0, 1);

								if( $codigo3 == 1 || $codigo3 == "1" ){
									$cdito = 	$grantotalCostoCosto; //Se voltearon las cuenta
									$MontoSysCR = ($dbito / $resMonedaSys[0]['tsa_value']); //Se voltearon las cuenta
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
								':ac1_doc_date' => $this->validateDate($Data['cdc_docdate'])?$Data['cdc_docdate']:NULL,
								':ac1_doc_duedate' => $this->validateDate($Data['cdc_duedate'])?$Data['cdc_duedate']:NULL,
								':ac1_debit_import' => 0,
								':ac1_credit_import' => 0,
								':ac1_debit_importsys' => 0,
								':ac1_credit_importsys' => 0,
								':ac1_font_key' => $resInsert,
								':ac1_font_line' => 1,
								':ac1_font_type' => is_numeric($Data['cdc_doctype'])?$Data['cdc_doctype']:0,
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
								':ac1_made_user' => isset($Data['cdc_createby'])?$Data['cdc_createby']:NULL,
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
								':ac1_legal_num' => isset($Data['cdc_cardcode'])?$Data['cdc_cardcode']:NULL,
								':ac1_codref' => 1
								));

								if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //

								}else{

										// si falla algun insert del detalle de la devolucion de compras se devuelven los cambios realizados por la transaccion,
										// se retorna el error y se detiene la ejecucion del codigo restante.
										$this->pedeo->trans_rollback();

										$respuesta = array(
											'error'   => true,
											'data'	  => $resDetalleAsiento,
											'mensaje'	=> 'No se pudo registrar la devolucion de compra'
										);

										 $this->response($respuesta);

								 	 	 return;
								}

					}

				 //SOLO SI ES CUENTA 3

				 if($Data['cdc_basetype'] == 3){

						foreach ($DetalleConsolidadoCostoCosto as $key => $posicion) {
								$grantotalCostoCosto = 0 ;
								$cuentaCosto = "";
								$dbito = 0;
								$cdito = 0;
								$MontoSysDB = 0;
								$MontoSysCR = 0;
								foreach ($posicion as $key => $value) {

													$sqlArticulo = "SELECT f2.dma_item_code,  f1.mga_acct_inv, f1.mga_acct_cost FROM dmga f1 JOIN dmar f2 ON f1.mga_id  = f2.dma_group_code WHERE dma_item_code = :dma_item_code";
													$resArticulo = $this->pedeo->queryTable($sqlArticulo, array(":dma_item_code" => $value->dc1_itemcode));

													if(isset($resArticulo[0])){
															$dbito = 0;
															$cdito = 0;
															$MontoSysDB = 0;
															$MontoSysCR = 0;

															$sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode";

															$resCosto = $this->pedeo->queryTable($sqlCosto, array(":bdi_itemcode" => $value->dc1_itemcode));

															if( isset( $resCosto[0] ) ){

																		$cuentaCosto = $resArticulo[0]['mga_acct_cost'];


																		$costoArticulo = $resCosto[0]['bdi_avgprice'];
																		$cantidadArticulo = $value->dc1_quantity;
																		$grantotalCostoCosto = ($grantotalCostoCosto + ($costoArticulo * $cantidadArticulo));

															}else{

																		$this->pedeo->trans_rollback();

																		$respuesta = array(
																			'error'   => true,
																			'data'	  => $resArticulo,
																			'mensaje'	=> 'No se encontro el costo para el item: '.$value->dc1_itemcode
																		);

																		 $this->response($respuesta);

																		 return;
															}

													}else{
															// si falla algun insert del detalle de la devolucion de compras se devuelven los cambios realizados por la transaccion,
															// se retorna el error y se detiene la ejecucion del codigo restante.
															$this->pedeo->trans_rollback();

															$respuesta = array(
																'error'   => true,
																'data'	  => $resArticulo,
																'mensaje'	=> 'No se encontro la cuenta puente para costo'
															);

															 $this->response($respuesta);

															 return;
													}

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
									':ac1_doc_date' => $this->validateDate($Data['cdc_docdate'])?$Data['cdc_docdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['cdc_duedate'])?$Data['cdc_duedate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => $resInsert,
									':ac1_font_line' => 1,
									':ac1_font_type' => is_numeric($Data['cdc_doctype'])?$Data['cdc_doctype']:0,
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
									':ac1_made_user' => isset($Data['cdc_createby'])?$Data['cdc_createby']:NULL,
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
									':ac1_legal_num' => isset($Data['cdc_cardcode'])?$Data['cdc_cardcode']:NULL,
									':ac1_codref' => 1
									));

									if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
									// Se verifica que el detalle no de error insertando //
									}else{

										// si falla algun insert del detalle de la devolucion de compras se devuelven los cambios realizados por la transaccion,
										// se retorna el error y se detiene la ejecucion del codigo restante.
										$this->pedeo->trans_rollback();

										$respuesta = array(
											'error'   => true,
											'data'	  => $resDetalleAsiento,
											'mensaje'	=> 'No se pudo registrar la devolucion de compras'
										);

										 $this->response($respuesta);

										 return;
									}

				 }

				 //SOLO SI ES CUENTA 3

				 //FIN Procedimiento para llenar costo costo

				//Procedimiento para llenar cuentas por cobrar

					$sqlcuentaCxC = "SELECT  f1.dms_card_code, f2.mgs_acct FROM dmsn AS f1
													 JOIN dmgs  AS f2
													 ON CAST(f2.mgs_id AS varchar(100)) = f1.dms_group_num
													 WHERE  f1.dms_card_code = :dms_card_code";


					$rescuentaCxC = $this->pedeo->queryTable($sqlcuentaCxC, array(":dms_card_code" => $Data['cdc_cardcode']));



					if(isset( $rescuentaCxC[0] )){

								$debitoo = 0;
								$creditoo = 0;
								$MontoSysDB = 0;
								$MontoSysCR = 0;

								$cuentaCxP = $rescuentaCxC[0]['mgs_acct'];

								$codigo2= substr($rescuentaCxC[0]['mgs_acct'], 0, 1);


								if( $codigo2 == 1 || $codigo2 == "1" ){
										$debitoo = $Data['cdc_doctotal'];
										$MontoSysDB = ($debitoo / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo2 == 2 || $codigo2 == "2" ){
										$creditoo = $Data['cdc_doctotal'];
										$MontoSysCR = ($creditoo / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo2 == 3 || $codigo2 == "3" ){
										$creditoo = $Data['cdc_doctotal'];
										$MontoSysCR = ($creditoo / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo2 == 4 || $codigo2 == "4" ){
									  $creditoo = $Data['cdc_doctotal'];
										$MontoSysCR = ($creditoo / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo2 == 5  || $codigo2 == "5" ){
									  $debitoo = $Data['cdc_doctotal'];
										$MontoSysDB = ($debitoo / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo2 == 6 || $codigo2 == "6" ){
									  $debitoo = $Data['cdc_doctotal'];
										$MontoSysDB = ($debitoo / $resMonedaSys[0]['tsa_value']);
								}else if( $codigo2 == 7 || $codigo2 == "7" ){
									  $debitoo = $Data['cdc_doctotal'];
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
										':ac1_doc_date' => $this->validateDate($Data['cdc_docdate'])?$Data['cdc_docdate']:NULL,
										':ac1_doc_duedate' => $this->validateDate($Data['cdc_duedate'])?$Data['cdc_duedate']:NULL,
										':ac1_debit_import' => 0,
										':ac1_credit_import' => 0,
										':ac1_debit_importsys' => 0,
										':ac1_credit_importsys' => 0,
										':ac1_font_key' => $resInsert,
										':ac1_font_line' => 1,
										':ac1_font_type' => is_numeric($Data['cdc_doctype'])?$Data['cdc_doctype']:0,
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
										':ac1_made_user' => isset($Data['cdc_createby'])?$Data['cdc_createby']:NULL,
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
										':ac1_legal_num' => isset($Data['cdc_cardcode'])?$Data['cdc_cardcode']:NULL,
										':ac1_codref' => 1
							));

							if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
									// Se verifica que el detalle no de error insertando //
							}else{

									// si falla algun insert del detalle de la devolucion de compras se devuelven los cambios realizados por la transaccion,
									// se retorna el error y se detiene la ejecucion del codigo restante.
										$this->pedeo->trans_rollback();

										$respuesta = array(
											'error'   => true,
											'data'	  => $resDetalleAsiento,
											'mensaje'	=> 'No se pudo registrar la devolucion de compras'
										);

										 $this->response($respuesta);

										 return;
							}

					}else{

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $rescuentaCxC,
									'mensaje'	=> 'No se pudo registrar la devolucion de compras, el tercero no tiene cuenta asociada'
								);

								 $this->response($respuesta);

								 return;
					}
					//FIN Procedimiento para llenar cuentas por cobrar

					//FIN DE OPERACIONES VITALES

					// VALIDANDO ESTADOS DE DOCUMENTOS

					if ($Data['cdc_basetype'] == 1) {


						$sqlEstado = 'select distinct
													case
														when (sum(t3.ov1_quantity) - t1.vc1_quantity) = 0
															then 1
														else 0
													end "estado"
												from dvct t0
												left join vct1 t1 on t0.dvc_docentry = t1.vc1_docentry
												left join dvov t2 on t0.dvc_docentry = t2.vov_baseentry
												left join vov1 t3 on t2.vov_docentry = t3.ov1_docentry and t1.vc1_itemcode = t3.ov1_itemcode
												where t0.dvc_docentry = :dvc_docentry
												group by
													t1.vc1_quantity';


						$resEstado = $this->pedeo->queryTable($sqlEstado, array(':dvc_docentry' => $Data['cdc_baseentry']));

						if(isset($resEstado[0]) && $resEstado[0]['estado'] == 1){

									$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																			VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

									$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


														':bed_docentry' => $Data['cdc_baseentry'],
														':bed_doctype' => $Data['cdc_basetype'],
														':bed_status' => 3, //ESTADO CERRADO
														':bed_createby' => $Data['cdc_createby'],
														':bed_date' => date('Y-m-d'),
														':bed_baseentry' => $resInsert,
														':bed_basetype' => $Data['cdc_doctype']
									));


									if(is_numeric($resInsertEstado) && $resInsertEstado > 0){

									}else{

											 $this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data' => $resInsertEstado,
													'mensaje'	=> 'No se pudo registrar la devolucion de compras'
												);


												$this->response($respuesta);

												return;
									}

						}

					} else if ($Data['cdc_basetype'] == 2) {


								$sqlEstado = 'select distinct
																case
																	when (sum(t3.em1_quantity) - t1.ov1_quantity) = 0
																		then 1
																	else 0
																end "estado"
															from dvov t0
															left join vov1 t1 on t0.vov_docentry = t1.ov1_docentry
															left join dvem t2 on t0.vov_docentry = t2.vem_baseentry
															left join vem1 t3 on t2.vem_docentry = t3.em1_docentry and t1.ov1_itemcode = t3.em1_itemcode
															where t0.vov_docentry = :vov_docentry
															group by
															t1.ov1_quantity';


								$resEstado = $this->pedeo->queryTable($sqlEstado, array(':vov_docentry' => $Data['cdc_baseentry']));

								if(isset($resEstado[0]) && $resEstado[0]['estado'] == 1){

											$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																					VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

											$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


																':bed_docentry' => $Data['cdc_baseentry'],
																':bed_doctype' => $Data['cdc_basetype'],
																':bed_status' => 3, //ESTADO CERRADO
																':bed_createby' => $Data['cdc_createby'],
																':bed_date' => date('Y-m-d'),
																':bed_baseentry' => $resInsert,
																':bed_basetype' => $Data['cdc_doctype']
											));


											if(is_numeric($resInsertEstado) && $resInsertEstado > 0){

											}else{

													 $this->pedeo->trans_rollback();

														$respuesta = array(
															'error'   => true,
															'data' => $resInsertEstado,
															'mensaje'	=> 'No se pudo registrar la devolucion de compras'
														);


														$this->response($respuesta);

														return;
											}

								}




					} else if ($Data['cdc_basetype'] == 3) {

							 $sqlEstado = 'select distinct
															case
																when (sum(t3.dc1_quantity) - t1.em1_quantity) = 0
																	then 1
																else 0
															end "estado"
														from dvem t0
														left join vem1 t1 on t0.vem_docentry = t1.em1_docentry
														left join dcdc t2 on t0.vem_docentry = t2.cdc_baseentry
														left join cdc1 t3 on t2.cdc_docentry = t3.dc1_docentry and t1.em1_itemcode = t3.dc1_itemcode
														where t0.vem_docentry = :vem_docentry
														group by t1.em1_quantity';

								$resEstado = $this->pedeo->queryTable($sqlEstado, array(':vem_docentry' => $Data['cdc_baseentry']));

								if(isset($resEstado[0]) && $resEstado[0]['estado'] == 1){

											$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																					VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

											$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


																':bed_docentry' => $Data['cdc_baseentry'],
																':bed_doctype' => $Data['cdc_basetype'],
																':bed_status' => 3, //ESTADO CERRADO
																':bed_createby' => $Data['cdc_createby'],
																':bed_date' => date('Y-m-d'),
																':bed_baseentry' => $resInsert,
																':bed_basetype' => $Data['cdc_doctype']
											));


											if(is_numeric($resInsertEstado) && $resInsertEstado > 0){

											}else{

													 $this->pedeo->trans_rollback();

														$respuesta = array(
															'error'   => true,
															'data' => $resInsertEstado,
															'mensaje'	=> 'No se pudo registrar la devolucion de compras'
														);


														$this->response($respuesta);

														return;
											}

								}

					}

					// FIN VALIDACION DE ESTADOS

					// Si todo sale bien despues de insertar el detalle de la devolucion de compras
					// se confirma la trasaccion  para que los cambios apliquen permanentemente
					// en la base de datos y se confirma la operacion exitosa.
					$this->pedeo->trans_commit();

          $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' =>'Devolucion de compras registrada con exito'
          );


        }else{
					// Se devuelven los cambios realizados en la transaccion
					// si occurre un error  y se muestra devuelve el error.
							$this->pedeo->trans_rollback();

              $respuesta = array(
                'error'   => true,
                'data' => $resInsert,
                'mensaje'	=> 'No se pudo registrar la devolucion de compras'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR devolucion de compras
  public function updatePurchaseRet_post(){

      $Data = $this->post();

			if(!isset($Data['cdc_docentry']) OR !isset($Data['cdc_docnum']) OR
				 !isset($Data['cdc_docdate']) OR !isset($Data['cdc_duedate']) OR
				 !isset($Data['cdc_duedev']) OR !isset($Data['cdc_pricelist']) OR
				 !isset($Data['cdc_cardcode']) OR !isset($Data['cdc_cardname']) OR
				 !isset($Data['cdc_currency']) OR !isset($Data['cdc_contacid']) OR
				 !isset($Data['cdc_slpcode']) OR !isset($Data['cdc_empid']) OR
				 !isset($Data['cdc_comment']) OR !isset($Data['cdc_doctotal']) OR
				 !isset($Data['cdc_baseamnt']) OR !isset($Data['cdc_taxtotal']) OR
				 !isset($Data['cdc_discprofit']) OR !isset($Data['cdc_discount']) OR
				 !isset($Data['cdc_createat']) OR !isset($Data['cdc_baseentry']) OR
				 !isset($Data['cdc_basetype']) OR !isset($Data['cdc_doctype']) OR
				 !isset($Data['cdc_idadd']) OR !isset($Data['cdc_adress']) OR
				 !isset($Data['cdc_paytype']) OR !isset($Data['cdc_attch']) OR
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
            'mensaje' =>'No se encontro el detalle de la devolucion de compras'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }

      $sqlUpdate = "UPDATE dcdc	SET cdc_docdate=:cdc_docdate,cdc_duedate=:cdc_duedate, cdc_duedev=:cdc_duedev, cdc_pricelist=:cdc_pricelist, cdc_cardcode=:cdc_cardcode,
			  						cdc_cardname=:cdc_cardname, cdc_currency=:cdc_currency, cdc_contacid=:cdc_contacid, cdc_slpcode=:cdc_slpcode,
										cdc_empid=:cdc_empid, cdc_comment=:cdc_comment, cdc_doctotal=:cdc_doctotal, cdc_baseamnt=:cdc_baseamnt,
										cdc_taxtotal=:cdc_taxtotal, cdc_discprofit=:cdc_discprofit, cdc_discount=:cdc_discount, cdc_createat=:cdc_createat,
										cdc_baseentry=:cdc_baseentry, cdc_basetype=:cdc_basetype, cdc_doctype=:cdc_doctype, cdc_idadd=:cdc_idadd,
										cdc_adress=:cdc_adress, cdc_paytype=:cdc_paytype, cdc_attch=:cdc_attch WHERE cdc_docentry=:cdc_docentry";

      $this->pedeo->trans_begin();

      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
							':cdc_docnum' => is_numeric($Data['cdc_docnum'])?$Data['cdc_docnum']:0,
							':cdc_docdate' => $this->validateDate($Data['cdc_docdate'])?$Data['cdc_docdate']:NULL,
							':cdc_duedate' => $this->validateDate($Data['cdc_duedate'])?$Data['cdc_duedate']:NULL,
							':cdc_duedev' => $this->validateDate($Data['cdc_duedev'])?$Data['cdc_duedev']:NULL,
							':cdc_pricelist' => is_numeric($Data['cdc_pricelist'])?$Data['cdc_pricelist']:0,
							':cdc_cardcode' => isset($Data['cdc_pricelist'])?$Data['cdc_pricelist']:NULL,
							':cdc_cardname' => isset($Data['cdc_cardname'])?$Data['cdc_cardname']:NULL,
							':cdc_currency' => isset($Data['cdc_currency'])?$Data['cdc_currency']:NULL,
							':cdc_contacid' => isset($Data['cdc_contacid'])?$Data['cdc_contacid']:NULL,
							':cdc_slpcode' => is_numeric($Data['cdc_slpcode'])?$Data['cdc_slpcode']:0,
							':cdc_empid' => is_numeric($Data['cdc_empid'])?$Data['cdc_empid']:0,
							':cdc_comment' => isset($Data['cdc_comment'])?$Data['cdc_comment']:NULL,
							':cdc_doctotal' => is_numeric($Data['cdc_doctotal'])?$Data['cdc_doctotal']:0,
							':cdc_baseamnt' => is_numeric($Data['cdc_baseamnt'])?$Data['cdc_baseamnt']:0,
							':cdc_taxtotal' => is_numeric($Data['cdc_taxtotal'])?$Data['cdc_taxtotal']:0,
							':cdc_discprofit' => is_numeric($Data['cdc_discprofit'])?$Data['cdc_discprofit']:0,
							':cdc_discount' => is_numeric($Data['cdc_discount'])?$Data['cdc_discount']:0,
							':cdc_createat' => $this->validateDate($Data['cdc_createat'])?$Data['cdc_createat']:NULL,
							':cdc_baseentry' => is_numeric($Data['cdc_baseentry'])?$Data['cdc_baseentry']:0,
							':cdc_basetype' => is_numeric($Data['cdc_basetype'])?$Data['cdc_basetype']:0,
							':cdc_doctype' => is_numeric($Data['cdc_doctype'])?$Data['cdc_doctype']:0,
							':cdc_idadd' => isset($Data['cdc_idadd'])?$Data['cdc_idadd']:NULL,
							':cdc_adress' => isset($Data['cdc_adress'])?$Data['cdc_adress']:NULL,
							':cdc_paytype' => is_numeric($Data['cdc_paytype'])?$Data['cdc_paytype']:0,
							':cdc_attch' => $this->getUrl(count(trim(($Data['cdc_attch']))) > 0 ? $Data['cdc_attch']:NULL, $resMainFolder[0]['main_folder']),
							':cdc_docentry' => $Data['cdc_docentry']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

						$this->pedeo->queryTable("DELETE FROM cdc1 WHERE dc1_docentry=:dc1_docentry", array(':dc1_docentry' => $Data['cdc_docentry']));

						foreach ($ContenidoDetalle as $key => $detail) {

									$sqlInsertDetail = "INSERT INTO cdc1(dc1_docentry, dc1_itemcode, dc1_itemname, dc1_quantity, dc1_uom, dc1_whscode,
																			dc1_price, dc1_vat, dc1_vatsum, dc1_discount, dc1_linetotal, dc1_costcode, dc1_ubusiness, dc1_project,
																			dc1_acctcode, dc1_basetype, dc1_doctype, dc1_avprice, dc1_inventory, dc1_acciva)VALUES(:dc1_docentry, :dc1_itemcode, :dc1_itemname, :dc1_quantity,
																			:dc1_uom, :dc1_whscode,:dc1_price, :dc1_vat, :dc1_vatsum, :dc1_discount, :dc1_linetotal, :dc1_costcode, :dc1_ubusiness, :dc1_project,
																			:dc1_acctcode, :dc1_basetype, :dc1_doctype, :dc1_avprice, :dc1_inventory, :dc1_acciva)";

									$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
											':dc1_docentry' => $Data['cdc_docentry'],
											':dc1_itemcode' => isset($detail['dc1_itemcode'])?$detail['dc1_itemcode']:NULL,
											':dc1_itemname' => isset($detail['dc1_itemname'])?$detail['dc1_itemname']:NULL,
											':dc1_quantity' => is_numeric($detail['dc1_quantity'])?$detail['dc1_quantity']:0,
											':dc1_uom' => isset($detail['dc1_uom'])?$detail['dc1_uom']:NULL,
											':dc1_whscode' => isset($detail['dc1_whscode'])?$detail['dc1_whscode']:NULL,
											':dc1_price' => is_numeric($detail['dc1_price'])?$detail['dc1_price']:0,
											':dc1_vat' => is_numeric($detail['dc1_vat'])?$detail['dc1_vat']:0,
											':dc1_vatsum' => is_numeric($detail['dc1_vatsum'])?$detail['dc1_vatsum']:0,
											':dc1_discount' => is_numeric($detail['dc1_discount'])?$detail['dc1_discount']:0,
											':dc1_linetotal' => is_numeric($detail['dc1_linetotal'])?$detail['dc1_linetotal']:0,
											':dc1_costcode' => isset($detail['dc1_costcode'])?$detail['dc1_costcode']:NULL,
											':dc1_ubusiness' => isset($detail['dc1_ubusiness'])?$detail['dc1_ubusiness']:NULL,
											':dc1_project' => isset($detail['dc1_project'])?$detail['dc1_project']:NULL,
											':dc1_acctcode' => is_numeric($detail['dc1_acctcode'])?$detail['dc1_acctcode']:0,
											':dc1_basetype' => is_numeric($detail['dc1_basetype'])?$detail['dc1_basetype']:0,
											':dc1_doctype' => is_numeric($detail['dc1_doctype'])?$detail['dc1_doctype']:0,
											':dc1_avprice' => is_numeric($detail['dc1_avprice'])?$detail['dc1_avprice']:0,
											':dc1_inventory' => is_numeric($detail['dc1_inventory'])?$detail['dc1_inventory']:NULL,
											':dc1_acciva' => is_numeric($detail['dc1_cuentaIva'])?$detail['dc1_cuentaIva']:0
									));

									if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
											// Se verifica que el detalle no de error insertando //
									}else{

											// si falla algun insert del detalle de la devolucion de compras se devuelven los cambios realizados por la transaccion,
											// se retorna el error y se detiene la ejecucion del codigo restante.
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data' => $resInsert,
													'mensaje'	=> 'No se pudo registrar la devolucion de compras'
												);

												 $this->response($respuesta);

												 return;
									}
						}


						$this->pedeo->trans_commit();

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Devolucion de compras actualizada con exito'
            );


      }else{

						$this->pedeo->trans_rollback();

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la devolucion de compras'
            );

      }

       $this->response($respuesta);
  }


  //OBTENER devolucion de compras
  public function getPurchaseRet_get(){

        $sqlSelect = self::getColumn('dcdc','cdc');


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


	//OBTENER devolucion de compras POR ID
	public function getPurchaseRetById_get(){

				$Data = $this->get();

				if(!isset($Data['cdc_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM dcdc WHERE cdc_docentry =:cdc_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":cdc_docentry" => $Data['cdc_docentry']));

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


	//OBTENER devolucion de compras DETALLE POR ID
	public function getPurchaseRetDetail_get(){

				$Data = $this->get();

				if(!isset($Data['dc1_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM cdc1 WHERE dc1_docentry =:dc1_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":dc1_docentry" => $Data['dc1_docentry']));

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




	//OBTENER devolucion de compra POR ID SOCIO DE NEGOCIO
	public function getPurchaseRetoiceBySN_get(){

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

				$sqlSelect = " SELECT * FROM dcdc WHERE cdc_cardcode =:cdc_cardcode";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":cdc_cardcode" => $Data['dms_card_code']));

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
