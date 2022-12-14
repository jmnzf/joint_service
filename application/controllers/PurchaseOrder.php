<?php
// ORDEN DE COMPRA
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PurchaseOrder extends REST_Controller {

	private $pdo;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
    $this->load->library('pedeo', [$this->pdo]);
		$this->load->library('generic');

	}

  //CREAR ORDEN DE COMPRA
	public function createPurchaseOrder_post(){

      $Data = $this->post();
			$DetalleAsientoIva = new stdClass();
			$DetalleConsolidadoIva = [];
			$inArrayIva = array(); // Array para mantener el indice de las llaves para iva
			$llaveIva = ""; //segun tipo de iva
			$posicionIva = 0;
			$codigoCuenta = ""; //para saber la naturaleza

			$DetalleAsientoCuentaArticulo = new stdClass();
			$DetalleConsolidadoCuentaArticulo = [];
			$inArrayCuentaArticulo = array();
			$llaveCuentaArticulo = "";
			$posicionCuentaArticulo = 0;
			$MONEDALOCAL = 0;

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
            'mensaje' =>'No se encontro el detalle de la orden de compra'
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

			$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['dpo_series']));

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
			//

			//OBTENER CARPETA PRINCIPAL DEL PROYECTO
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
		//

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

		//SE BUSCA LA TASA DE CAMBIO CON RESPECTO A LA MONEDA QUE TRAE EL DOCUMENTO A CREAR CON LA MONEDA LOCAL
		// Y EN LA MISMA FECHA QUE TRAE EL DOCUMENTO


		$sqlBusTasa = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
		$resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $Data['dpo_currency'], ':tsa_date' => $Data['dpo_docdate']));

		if(isset($resBusTasa[0])){

		}else{

				if(trim($Data['cec_currency']) != $MONEDALOCAL ){

						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encrontro la tasa de cambio para la moneda: '.$Data['dpo_currency'].' en la actual fecha del documento: '.$Data['dpo_docdate'].' y la moneda local: '.$resMonedaLoc[0]['pgm_symbol']
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}
		}

		$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
		$resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $Data['dpo_docdate']));

		if(isset($resBusTasa2[0])){

		}else{
				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' =>'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :'.$Data['dpo_docdate']
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
		}

		$TasaDocLoc = isset($resBusTasa[0]['tsa_value']) ? $resBusTasa[0]['tsa_value'] : 1;
		$TasaLocSys = $resBusTasa2[0]['tsa_value'];

		// FIN DEL PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO

        $sqlInsert = "INSERT INTO dcpo (dpo_series, dpo_docnum, dpo_docdate, dpo_duedate, dpo_duedev, dpo_pricelist, dpo_cardcode,
                      dpo_cardname, dpo_currency, dpo_contacid, dpo_slpcode, dpo_empid, dpo_comment, dpo_doctotal, dpo_baseamnt, dpo_taxtotal,
                      dpo_discprofit, dpo_discount, dpo_createat, dpo_baseentry, dpo_basetype, dpo_doctype, dpo_idadd, dpo_adress, dpo_paytype,
                      dpo_attch,dpo_createby)VALUES(:dpo_series, :dpo_docnum, :dpo_docdate, :dpo_duedate, :dpo_duedev, :dpo_pricelist, :dpo_cardcode, :dpo_cardname,
                      :dpo_currency, :dpo_contacid, :dpo_slpcode, :dpo_empid, :dpo_comment, :dpo_doctotal, :dpo_baseamnt, :dpo_taxtotal, :dpo_discprofit, :dpo_discount,
                      :dpo_createat, :dpo_baseentry, :dpo_basetype, :dpo_doctype, :dpo_idadd, :dpo_adress, :dpo_paytype, :dpo_attch,:dpo_createby)";


				// Se Inicia la transaccion,
				// Todas las consultas de modificacion siguientes
				// aplicaran solo despues que se confirme la transaccion,
				// de lo contrario no se aplicaran los cambios y se devolvera
				// la base de datos a su estado original.

			  $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':dpo_docnum' => is_numeric($Data['dpo_docnum'])?$Data['dpo_docnum']:0,
              ':dpo_series' => is_numeric($Data['dpo_series'])?$Data['dpo_series']:0,
              ':dpo_docdate' => $this->validateDate($Data['dpo_docdate'])?$Data['dpo_docdate']:NULL,
              ':dpo_duedate' => $this->validateDate($Data['dpo_duedate'])?$Data['dpo_duedate']:NULL,
              ':dpo_duedev' => $this->validateDate($Data['dpo_duedev'])?$Data['dpo_duedev']:NULL,
              ':dpo_pricelist' => is_numeric($Data['dpo_pricelist'])?$Data['dpo_pricelist']:0,
              ':dpo_cardcode' => isset($Data['dpo_cardcode'])?$Data['dpo_cardcode']:NULL,
              ':dpo_cardname' => isset($Data['dpo_cardname'])?$Data['dpo_cardname']:NULL,
              ':dpo_currency' => is_numeric($Data['dpo_currency'])?$Data['dpo_currency']:0,
              ':dpo_contacid' => isset($Data['dpo_contacid'])?$Data['dpo_contacid']:NULL,
              ':dpo_slpcode' => is_numeric($Data['dpo_slpcode'])?$Data['dpo_slpcode']:0,
              ':dpo_empid' => is_numeric($Data['dpo_empid'])?$Data['dpo_empid']:0,
              ':dpo_comment' => isset($Data['dpo_comment'])?$Data['dpo_comment']:NULL,
              ':dpo_doctotal' => is_numeric($Data['dpo_doctotal'])?$Data['dpo_doctotal']:0,
              ':dpo_baseamnt' => is_numeric($Data['dpo_baseamnt'])?$Data['dpo_baseamnt']:0,
              ':dpo_taxtotal' => is_numeric($Data['dpo_taxtotal'])?$Data['dpo_taxtotal']:0,
              ':dpo_discprofit' => is_numeric($Data['dpo_discprofit'])?$Data['dpo_discprofit']:0,
              ':dpo_discount' => is_numeric($Data['dpo_discount'])?$Data['dpo_discount']:0,
              ':dpo_createat' => $this->validateDate($Data['dpo_createat'])?$Data['dpo_createat']:NULL,
              ':dpo_baseentry' => is_numeric($Data['dpo_baseentry'])?$Data['dpo_baseentry']:0,
              ':dpo_basetype' => is_numeric($Data['dpo_basetype'])?$Data['dpo_basetype']:0,
              ':dpo_doctype' => is_numeric($Data['dpo_doctype'])?$Data['dpo_doctype']:0,
              ':dpo_idadd' => isset($Data['dpo_idadd'])?$Data['dpo_idadd']:NULL,
              ':dpo_adress' => isset($Data['dpo_adress'])?$Data['dpo_adress']:NULL,
              ':dpo_paytype' => is_numeric($Data['dpo_paytype'])?$Data['dpo_paytype']:0,
							':dpo_createby' => isset($Data['dpo_createby'])?$Data['dpo_createby']:NULL,
              ':dpo_attch' => $this->getUrl(count(trim(($Data['dpo_attch']))) > 0 ? $Data['dpo_attch']:NULL)
						));

        if(is_numeric($resInsert) && $resInsert > 0){

					//Se agregan los asientos contables*/*******

					$sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user)
															 VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user)";


					$resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

							':mac_doc_num' => 1,
							':mac_status' => 1,
							':mac_base_type' => is_numeric($Data['dpo_doctype'])?$Data['dpo_doctype']:0,
							':mac_base_entry' => $resInsert,
							':mac_doc_date' => $this->validateDate($Data['dpo_docdate'])?$Data['dpo_docdate']:NULL,
							':mac_doc_duedate' => $this->validateDate($Data['dpo_duedate'])?$Data['dpo_duedate']:NULL,
							':mac_legal_date' => $this->validateDate($Data['dpo_docdate'])?$Data['dpo_docdate']:NULL,
							':mac_ref1' => is_numeric($Data['dpo_doctype'])?$Data['dpo_doctype']:'0',
							':mac_ref2' => "",
							':mac_ref3' => "",
							':mac_loc_total' => is_numeric($Data['dpo_doctotal'])?$Data['dpo_doctotal']:0,
							':mac_fc_total' => is_numeric($Data['dpo_doctotal'])?$Data['dpo_doctotal']:0,
							':mac_sys_total' => is_numeric($Data['dpo_doctotal'])?$Data['dpo_doctotal']:0,
							':mac_trans_dode' => 1,
							':mac_beline_nume' => 1,
							':mac_vat_date' => $this->validateDate($Data['dpo_docdate'])?$Data['dpo_docdate']:NULL,
							':mac_serie' => 1,
							':mac_number' => 1,
							':mac_bammntsys' => is_numeric($Data['dpo_baseamnt'])?$Data['dpo_baseamnt']:0,
							':mac_bammnt' => is_numeric($Data['dpo_baseamnt'])?$Data['dpo_baseamnt']:0,
							':mac_wtsum' => 1,
							':mac_vatsum' => is_numeric($Data['dpo_taxtotal'])?$Data['dpo_taxtotal']:0,
							':mac_comments' => isset($Data['dpo_comment'])?$Data['dpo_comment']:NULL,
							':mac_create_date' => $this->validateDate($Data['dpo_createat'])?$Data['dpo_createat']:NULL,
							':mac_made_usuer' => isset($Data['dpo_createby'])?$Data['dpo_createby']:NULL,
							':mac_update_date' => date("Y-m-d"),
							':mac_update_user' => isset($Data['dpo_createby'])?$Data['dpo_createby']:NULL
					));


					if(is_numeric($resInsertAsiento) && $resInsertAsiento > 0){
							// Se verifica que el detalle no de error insertando //
					}else{

							// si falla algun insert del detalle de la orden de compra se devuelven los cambios realizados por la transaccion,
							// se retorna el error y se detiene la ejecucion del codigo restante.
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $resInsertAsiento,
									'mensaje'	=> 'No se pudo registrar la orden de compra'
								);

								 $this->response($respuesta);

								 return;
					}


          foreach ($ContenidoDetalle as $key => $detail) {

                $sqlInsertDetail = "INSERT INTO cpo1(po1_docentry, po1_itemcode, po1_itemname, po1_quantity, po1_uom, po1_whscode,
                                    po1_price, po1_vat, po1_vatsum, po1_discount, po1_linetotal, po1_costcode, po1_ubusiness, po1_project,
                                    po1_acctcode, po1_basetype, po1_doctype, po1_avprice, po1_inventory, po1_ubication)VALUES(:po1_docentry, :po1_itemcode, :po1_itemname, :po1_quantity,
                                    :po1_uom, :po1_whscode,:po1_price, :po1_vat, :po1_vatsum, :po1_discount, :po1_linetotal, :po1_costcode, :po1_ubusiness, :po1_project,
                                    :po1_acctcode, :po1_basetype, :po1_doctype, :po1_avprice, :po1_inventory, :po1_ubication)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':po1_docentry' => $resInsert,
                        ':po1_itemcode' => isset($detail['po1_itemcode'])?$detail['po1_itemcode']:NULL,
                        ':po1_itemname' => isset($detail['po1_itemname'])?$detail['po1_itemname']:NULL,
                        ':po1_quantity' => is_numeric($detail['po1_quantity'])?$detail['po1_quantity']:0,
                        ':po1_uom' => isset($detail['po1_uom'])?$detail['po1_uom']:NULL,
                        ':po1_whscode' => isset($detail['po1_whscode'])?$detail['po1_whscode']:NULL,
                        ':po1_price' => is_numeric($detail['po1_price'])?$detail['po1_price']:0,
                        ':po1_vat' => is_numeric($detail['po1_vat'])?$detail['po1_vat']:0,
                        ':po1_vatsum' => is_numeric($detail['po1_vatsum'])?$detail['po1_vatsum']:0,
                        ':po1_discount' => is_numeric($detail['po1_discount'])?$detail['po1_discount']:0,
                        ':po1_linetotal' => is_numeric($detail['po1_linetotal'])?$detail['po1_linetotal']:0,
                        ':po1_costcode' => isset($detail['po1_costcode'])?$detail['po1_costcode']:NULL,
                        ':po1_ubusiness' => isset($detail['po1_ubusiness'])?$detail['po1_ubusiness']:NULL,
                        ':po1_project' => isset($detail['po1_project'])?$detail['po1_project']:NULL,
                        ':po1_acctcode' => is_numeric($detail['po1_acctcode'])?$detail['po1_acctcode']:0,
                        ':po1_basetype' => is_numeric($detail['po1_basetype'])?$detail['po1_basetype']:0,
                        ':po1_doctype' => is_numeric($detail['po1_doctype'])?$detail['po1_doctype']:0,
                        ':po1_avprice' => is_numeric($detail['po1_avprice'])?$detail['po1_avprice']:0,
                        ':po1_inventory' => is_numeric($detail['po1_inventory'])?$detail['po1_inventory']:NULL,
                        ':po1_ubication' => is_numeric($detail['po1_ubication'])?$detail['po1_ubication']:NULL
                ));

								if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
										// Se verifica que el detalle no de error insertando //
								}else{

										// si falla algun insert del detalle de la orden de compra se devuelven los cambios realizados por la transaccion,
										// se retorna el error y se detiene la ejecucion del codigo restante.
											$this->pedeo->trans_rollback();

											$respuesta = array(
												'error'   => true,
												'data' => $resInsert,
												'mensaje'	=> 'No se pudo registrar la Orden de Compra'
											);

											 $this->response($respuesta);

											 return;
								}

								// //Se aplica el movimiento de inventario
								// //Solo si el item es inventariable
								// if($detail['po1_inventory'] == 1 || $detail['po1_inventory']  == '1'){
								//
								// 			$sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode, bmi_quantity, bmi_whscode, bmi_createat, bmi_createby, bmy_doctype, bmy_baseentry)
								// 														 VALUES (:bmi_itemcode, :bmi_quantity, :bmi_whscode, :bmi_createat, :bmi_createby, :bmy_doctype, :bmy_baseentry)";
								//
								// 			$sqlInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(
								//
								// 					 ':bmi_itemcode' => isset($detail['po1_itemcode'])?$detail['po1_itemcode']:NULL,
								// 					 ':bmi_quantity' => is_numeric($detail['po1_quantity'])? $detail['po1_quantity'] * $Data['invtype']:0,
								// 					 ':bmi_whscode'  => isset($detail['po1_whscode'])?$detail['po1_whscode']:NULL,
								// 					 ':bmi_createat' => $this->validateDate($Data['dpo_createat'])?$Data['dpo_createat']:NULL,
								// 					 ':bmi_createby' => isset($Data['dpo_createby'])?$Data['dpo_createby']:NULL,
								// 					 ':bmy_doctype'  => is_numeric($Data['dpo_doctype'])?$Data['dpo_doctype']:0,
								// 					 ':bmy_baseentry' => $resInsert
								//
								// 			));
								//
								// 			if(is_numeric($sqlInserMovimiento) && $sqlInserMovimiento > 0){
								// 					// Se verifica que el detalle no de error insertando //
								// 			}else{
								//
								// 					// si falla algun insert del detalle de la orden de compra se devuelven los cambios realizados por la transaccion,
								// 					// se retorna el error y se detiene la ejecucion del codigo restante.
								// 						$this->pedeo->trans_rollback();
								//
								// 						$respuesta = array(
								// 							'error'   => true,
								// 							'data' => $sqlInserMovimiento,
								// 							'mensaje'	=> 'No se pudo registrar la Orden de Compra'
								// 						);
								//
								// 						 $this->response($respuesta);
								//
								// 						 return;
								// 			}
								// }


								// //Se Aplica el movimiento en stock y se cambio el precio ponderado
								// //Solo si el articulo es inventariable
								//
								// if($detail['po1_inventory'] == 1 || $detail['po1_inventory']  == '1'){
								//
								// 	$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
								// 												FROM tbdi
								// 												WHERE bdi_itemcode = :bdi_itemcode
								// 												AND bdi_whscode = :bdi_whscode";
								//
								// 	$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(
								//
								// 				':bdi_itemcode' => $detail['po1_itemcode'],
								// 				':bdi_whscode'  => $detail['po1_whscode']
								// 	));
								//
								// 	if(isset($resCostoCantidad[0])){
								//
								// 		if($resCostoCantidad[0]['bdi_quantity'] > 0){
								//
								// 					$CantidadActual = $resCostoCantidad[0]['bdi_quantity'];
								// 					$CostoActual = $resCostoCantidad[0]['bdi_avgprice'];
								//
								// 					$CantidadNueva = $detail['po1_quantity'];
								// 					$CostoNuevo = $detail['po1_price'];
								//
								// 					$CantidadTotal = ($CantidadActual + $CantidadNueva);
								//
								// 					$NuevoCostoPonderado = ($CantidadActual  *  $CostoActual) + ($CantidadNueva * $CostoNuevo );
								// 					$NuevoCostoPonderado = round(($NuevoCostoPonderado / $CantidadTotal),2);
								//
								// 					$sqlUpdateCostoCantidad = "UPDATE tbdi
								// 																		 SET bdi_quantity = :bdi_quantity
								// 																		 ,bdi_avgprice = :bdi_avgprice
								// 																		 WHERE  bdi_id = :bdi_id";
								//
								// 				 $resUpdateCostoCantidad = $this->pedeo->updateRow($sqlUpdateCostoCantidad, array(
								//
								// 							 ':bdi_quantity' => $CantidadTotal,
								// 							 ':bdi_avgprice' => $NuevoCostoPonderado,
								// 							 ':bdi_id' 			 => $resCostoCantidad[0]['bdi_id']
								// 				 ));
								//
								// 				 if(is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad == 1){
								//
								// 				 }else{
								//
								// 						 $this->pedeo->trans_rollback();
								//
								// 						 $respuesta = array(
								// 							 'error'   => true,
								// 							 'data'    => $resUpdateCostoCantidad,
								// 							 'mensaje'	=> 'No se pudo crear la Orden de Compra'
								// 						 );
								// 				 }
								//
								// 		}else{
								//
								// 				 $CantidadActual = $resCostoCantidad[0]['bdi_quantity'];
								// 				 $CantidadNueva  = ($CantidadActual + $detail['po1_quantity']);
								//
								// 				 $sqlUpdateCostoCantidad = "UPDATE tbdi
								// 																		SET bdi_quantity = :bdi_quantity, bdi_avgprice = :bdi_avgprice
								// 																		WHERE  bdi_id = :bdi_id";
								//
								// 				 $resUpdateCostoCantidad = $this->pedeo->updateRow($sqlUpdateCostoCantidad, array(
								//
								// 							 ':bdi_quantity' => $CantidadNueva,
								// 							 ':bdi_avgprice' => $detail['po1_price'],
								// 							 ':bdi_id' 			 => $resCostoCantidad[0]['bdi_id']
								// 				 ));
								//
								// 				 if(is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad == 1){
								//
								// 				 }else{
								//
								// 						 $this->pedeo->trans_rollback();
								//
								// 						 $respuesta = array(
								// 							 'error'   => true,
								// 							 'data'    => $resUpdateCostoCantidad,
								// 							 'mensaje'	=> 'No se pudo crear la Orden de Compra'
								// 						 );
								// 				 }
								//
								// 		}
								//
								// 	}else{
								//
								// 				$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice)
								// 																	 VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice)";
								//
								//
								// 				$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(
								//
								// 							':bdi_itemcode' => $detail['po1_itemcode'],
								// 							':bdi_whscode'  => $detail['po1_whscode'],
								// 							':bdi_quantity' => $detail['po1_quantity'],
								// 							':bdi_avgprice' => $detail['po1_price']
								// 				));
								//
								//
								// 				if(is_numeric($resInsertCostoCantidad) && $resInsertCostoCantidad > 0){
								// 						// Se verifica que el detalle no de error insertando //
								// 				}else{
								//
								// 						// si falla algun insert del detalle de la orden de compra se devuelven los cambios realizados por la transaccion,
								// 						// se retorna el error y se detiene la ejecucion del codigo restante.
								// 							$this->pedeo->trans_rollback();
								//
								// 							$respuesta = array(
								// 								'error'   => true,
								// 								'data' 		=> $resInsertCostoCantidad,
								// 								'mensaje'	=> 'No se pudo registrar la Orden de Compra'
								// 							);
								//
								// 							 $this->response($respuesta);
								//
								// 							 return;
								// 				}
								// 	}
								//
								// }

								//LLENANDO DETALLE ASIENTO CONTABLES
								$DetalleAsientoIva->ac1_account = is_numeric($detail['po1_acctcode'])?$detail['po1_acctcode']:0;
								$DetalleAsientoIva->ac1_prc_code = isset($detail['po1_costcode'])?$detail['po1_costcode']:NULL;
								$DetalleAsientoIva->ac1_uncode =  isset($detail['po1_ubusiness'])?$detail['po1_ubusiness']:NULL;
								$DetalleAsientoIva->ac1_prj_code = isset($detail['po1_project'])?$detail['po1_project']:NULL;
								$DetalleAsientoIva->vc1_linetotal = is_numeric($detail['po1_linetotal'])?$detail['po1_linetotal']:0;
								$DetalleAsientoIva->vc1_vat = is_numeric($detail['po1_vat'])?$detail['po1_vat']:0;
								$DetalleAsientoIva->vc1_vatsum = is_numeric($detail['po1_vatsum'])?$detail['po1_vatsum']:0;
								$DetalleAsientoIva->vc1_price = is_numeric($detail['po1_price'])?$detail['po1_price']:0;
								$DetalleAsientoIva->vc1_itemcode = isset($detail['po1_itemcode'])?$detail['po1_itemcode']:NULL;
								$DetalleAsientoIva->vc1_quantity = is_numeric($detail['po1_quantity'])?$detail['po1_quantity']:0;


								$DetalleAsientoCuentaArticulo->ac1_account = is_numeric($detail['po1_acctcode'])?$detail['po1_acctcode']:0;
								$DetalleAsientoCuentaArticulo->ac1_prc_code = isset($detail['po1_costcode'])?$detail['po1_costcode']:NULL;
								$DetalleAsientoCuentaArticulo->ac1_uncode =  isset($detail['po1_ubusiness'])?$detail['po1_ubusiness']:NULL;
								$DetalleAsientoCuentaArticulo->ac1_prj_code = isset($detail['po1_project'])?$detail['po1_project']:NULL;
								$DetalleAsientoCuentaArticulo->vc1_linetotal = is_numeric($detail['po1_linetotal'])?$detail['po1_linetotal']:0;
								$DetalleAsientoCuentaArticulo->vc1_vat = is_numeric($detail['po1_vat'])?$detail['po1_vat']:0;
								$DetalleAsientoCuentaArticulo->vc1_vatsum = is_numeric($detail['po1_vatsum'])?$detail['po1_vatsum']:0;
								$DetalleAsientoCuentaArticulo->vc1_price = is_numeric($detail['po1_price'])?$detail['po1_price']:0;
								$DetalleAsientoCuentaArticulo->vc1_itemcode = isset($detail['po1_itemcode'])?$detail['po1_itemcode']:NULL;
								$DetalleAsientoCuentaArticulo->vc1_quantity = is_numeric($detail['po1_quantity'])?$detail['po1_quantity']:0;

								$codigoCuenta = substr($DetalleAsientoCuentaArticulo->ac1_account, 0, 1);

								$DetalleAsientoCuentaArticulo->codigoCuenta = $codigoCuenta;


								$llaveIva = $DetalleAsientoIva->vc1_vat;
								$llaveCuentaArticulo = $DetalleAsientoCuentaArticulo->ac1_uncode.$DetalleAsientoCuentaArticulo->ac1_prc_code.$DetalleAsientoCuentaArticulo->ac1_prj_code.$DetalleAsientoCuentaArticulo->ac1_account;

								//PARA IVA*******************

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

								//PARA IVA******************


								//PARA CuentaArticulo*******************

								if(in_array( $llaveCuentaArticulo, $inArrayCuentaArticulo )){

										$posicionCuentaArticulo = $this->buscarPosicion( $llaveCuentaArticulo, $inArrayCuentaArticulo );

								}else{

										array_push( $inArrayCuentaArticulo, $llaveCuentaArticulo );
										$posicionCuentaArticulo = $this->buscarPosicion( $llaveCuentaArticulo, $inArrayCuentaArticulo );

								}


								if( isset($DetalleConsolidadoCuentaArticulo[$posicionCuentaArticulo])){

									if(!is_array($DetalleConsolidadoCuentaArticulo[$posicionCuentaArticulo])){
										$DetalleConsolidadoCuentaArticulo[$posicionCuentaArticulo] = array();
									}

								}else{
									$DetalleConsolidadoCuentaArticulo[$posicionCuentaArticulo] = array();
								}

								array_push( $DetalleConsolidadoCuentaArticulo[$posicionCuentaArticulo], $DetalleAsientoCuentaArticulo);

								//PARA CuentaArticulo******************



          }
					//FIN DEL LLENADO DETALLE


					// //Procedimiento para llenar Cuenta Articulo
					//
					// foreach ($DetalleConsolidadoCuentaArticulo as $key => $posicion) {
					// 		$granTotalIngreso = 0;
					// 		$codigoCuentaIngreso = "";
					// 		$cuenta = "";
					// 		$proyecto = "";
					// 		$prc = "";
					// 		$unidad = "";
					// 		foreach ($posicion as $key => $value) {
					// 					$granTotalIngreso = ( $granTotalIngreso + $value->vc1_linetotal );
					// 					$codigoCuentaIngreso = $value->codigoCuenta;
					// 					$prc = $value->ac1_prc_code;
					// 					$unidad = $value->ac1_uncode;
					// 					$proyecto = $value->ac1_prj_code;
					// 					$cuenta = $value->ac1_account;
					// 		}
					//
					//
					// 		$debito = 0;
					// 		$credito = 0;
					//
					// 		switch ($codigoCuentaIngreso) {
					// 			case 1:
					// 				$debito = $granTotalIngreso;
					// 				break;
					//
					// 			case 2:
					// 				$credito = $granTotalIngreso;
					// 				break;
					//
					// 			case 3:
					// 				$credito = $granTotalIngreso;
					// 				break;
					//
					// 			case 4:
					// 				$credito = $granTotalIngreso;
					// 				break;
					//
					// 			case 5:
					// 				$debito = $granTotalIngreso;
					// 				break;
					//
					// 			case 6:
					// 				$debito = $granTotalIngreso;
					// 				break;
					//
					// 			case 7:
					// 				$debito = $granTotalIngreso;
					// 				break;
					// 		}
					//
					//
					// 		$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(
					//
					// 				':ac1_trans_id' => $resInsertAsiento,
					// 				':ac1_account' => $cuenta,
					// 				':ac1_debit' => $debito,
					// 				':ac1_credit' => $credito,
					// 				':ac1_debit_sys' => 0,
					// 				':ac1_credit_sys' => 0,
					// 				':ac1_currex' => 0,
					// 				':ac1_doc_date' => $this->validateDate($Data['dpo_docdate'])?$Data['dpo_docdate']:NULL,
					// 				':ac1_doc_duedate' => $this->validateDate($Data['dpo_duedate'])?$Data['dpo_duedate']:NULL,
					// 				':ac1_debit_import' => 0,
					// 				':ac1_credit_import' => 0,
					// 				':ac1_debit_importsys' => 0,
					// 				':ac1_credit_importsys' => 0,
					// 				':ac1_font_key' => $resInsert,
					// 				':ac1_font_line' => 1,
					// 				':ac1_font_type' => is_numeric($Data['dpo_doctype'])?$Data['dpo_doctype']:0,
					// 				':ac1_accountvs' => 1,
					// 				':ac1_doctype' => 18,
					// 				':ac1_ref1' => "",
					// 				':ac1_ref2' => "",
					// 				':ac1_ref3' => "",
					// 				':ac1_prc_code' => $prc,
					// 				':ac1_uncode' => $unidad,
					// 				':ac1_prj_code' => $proyecto,
					// 				':ac1_rescon_date' => NULL,
					// 				':ac1_recon_total' => 0,
					// 				':ac1_made_user' => isset($Data['dpo_createby'])?$Data['dpo_createby']:NULL,
					// 				':ac1_accperiod' => 1,
					// 				':ac1_close' => 0,
					// 				':ac1_cord' => 0,
					// 				':ac1_ven_debit' => 1,
					// 				':ac1_ven_credit' => 1,
					// 				':ac1_fiscal_acct' => 0,
					// 				':ac1_taxid' => 1,
					// 				':ac1_isrti' => 0,
					// 				':ac1_basert' => 0,
					// 				':ac1_mmcode' => 0,
					// 				':ac1_legal_num' => isset($Data['dpo_cardcode'])?$Data['dpo_cardcode']:NULL,
					// 				':ac1_codref' => 1
					// 	));
					//
					//
					//
					// 	if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
					// 			// Se verifica que el detalle no de error insertando //
					// 	}else{
					// 			// si falla algun insert del detalle de la orden de comprar se devuelven los cambios realizados por la transaccion,
					// 			// se retorna el error y se detiene la ejecucion del codigo restante.
					// 				$this->pedeo->trans_rollback();
					//
					// 				$respuesta = array(
					// 					'error'   => true,
					// 					'data'	  => $resDetalleAsiento,
					// 					'mensaje'	=> 'No se pudo registrar la orden de compra'
					// 				);
					//
					// 				 $this->response($respuesta);
					//
					// 				 return;
					// 	}
					// }
					//FIN Procedimiento para llenar Cuenta Articulo



					//Procedimiento para llenar Impuestos

					$granTotalIva = 0;

					foreach ($DetalleConsolidadoIva as $key => $posicion) {
							$granTotalIva = 0;

							foreach ($posicion as $key => $value) {
										$granTotalIva = $granTotalIva + $value->vc1_vatsum;
							}

							$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

									':ac1_trans_id' => $resInsertAsiento,
									':ac1_account' => is_numeric($Data['ac1_account'])?$Data['ac1_account']:0,
									':ac1_debit' => $granTotalIva,
									':ac1_credit' => 0,
									':ac1_debit_sys' => 0,
									':ac1_credit_sys' => 0,
									':ac1_currex' => 0,
									':ac1_doc_date' =>  $this->validateDate($Data['dpo_docdate'])?$Data['dpo_docdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['dpo_duedate'])?$Data['dpo_duedate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => $resInsert,
									':ac1_font_line' => 1,
									':ac1_font_type' => is_numeric($Data['dpo_doctype'])?$Data['dpo_doctype']:0,
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
									':ac1_made_user' => isset($Data['dpo_createby'])?$Data['dpo_createby']:NULL,
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
									':ac1_legal_num' => isset($Data['dpo_cardcode'])?$Data['dpo_cardcode']:NULL,
									':ac1_codref' => 1
						));



						if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
						}else{

								// si falla algun insert del detalle de la orden de compra se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resDetalleAsiento,
										'mensaje'	=> 'No se pudo registrar la orden compra'
									);

									 $this->response($respuesta);

									 return;
						}
					}

					//FIN Procedimiento para llenar Impuestos


					//Procedimiento para llenar cuentas por pagar

						$sqlcuentaCxP = "SELECT  f1.dms_card_code, f2.mgs_acct FROM dmsn AS f1
														 JOIN dmgs  AS f2
														 ON CAST(f2.mgs_acct AS varchar(100)) = f1.dms_group_num
														 WHERE  f1.dms_card_code = :dms_card_code";

						$rescuentaCxP = $this->pedeo->queryTable($sqlcuentaCxP, array(":dms_card_code" => $Data['dpo_cardcode']));



						if(isset( $rescuentaCxP[0] )){

									$debitoo = 0;
									$creditoo = 0;

									$cuentaCxP = $rescuentaCxP[0]['mgs_acct'];

									$codigo2= substr($rescuentaCxP[0]['mgs_acct'], 0, 1);


									if( $codigo2 == 1 || $codigo2 == "1" ){
											$debitoo = $Data['dpo_doctotal'];
									}else if( $codigo2 == 2 || $codigo2 == "2" ){
											$creditoo = $Data['dpo_doctotal'];
									}else if( $codigo2 == 3 || $codigo2 == "3" ){
											$creditoo = $Data['dpo_doctotal'];
									}else if( $codigo2 == 4 || $codigo2 == "4" ){
											$creditoo = $Data['dpo_doctotal'];
									}else if( $codigo2 == 5  || $codigo2 == "5" ){
											$debitoo = $Data['dpo_doctotal'];
									}else if( $codigo2 == 6 || $codigo2 == "6" ){
											$debitoo = $Data['dpo_doctotal'];
									}else if( $codigo2 == 7 || $codigo2 == "7" ){
											$debitoo = $Data['dpo_doctotal'];
									}

									$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

											':ac1_trans_id' => $resInsertAsiento,
											':ac1_account' => $cuentaCxP,
											':ac1_debit' => $debitoo,
											':ac1_credit' => $creditoo,
											':ac1_debit_sys' => 0,
											':ac1_credit_sys' => 0,
											':ac1_currex' => 0,
											':ac1_doc_date' => $this->validateDate($Data['dpo_docdate'])?$Data['dpo_docdate']:NULL,
											':ac1_doc_duedate' => $this->validateDate($Data['dpo_duedate'])?$Data['dpo_duedate']:NULL,
											':ac1_debit_import' => 0,
											':ac1_credit_import' => 0,
											':ac1_debit_importsys' => 0,
											':ac1_credit_importsys' => 0,
											':ac1_font_key' => $resInsert,
											':ac1_font_line' => 1,
											':ac1_font_type' => is_numeric($Data['dpo_doctype'])?$Data['dpo_doctype']:0,
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
											':ac1_made_user' => isset($Data['dpo_createby'])?$Data['dpo_createby']:NULL,
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
											':ac1_legal_num' => isset($Data['dpo_cardcode'])?$Data['dpo_cardcode']:NULL,
											':ac1_codref' => 1
								));

								if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
										// Se verifica que el detalle no de error insertando //
								}else{

										// si falla algun insert del detalle de la orden de compra se devuelven los cambios realizados por la transaccion,
										// se retorna el error y se detiene la ejecucion del codigo restante.
											$this->pedeo->trans_rollback();

											$respuesta = array(
												'error'   => true,
												'data'	  => $resDetalleAsiento,
												'mensaje'	=> 'No se pudo registrar la orden de compra'
											);

											 $this->response($respuesta);

											 return;
								}

						}else{

									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resDetalleAsiento,
										'mensaje'	=> 'No se pudo registrar la orden de compra, el tercero no tiene cuenta asociada'
									);

									 $this->response($respuesta);

									 return;
						}
						//FIN Procedimiento para llenar cuentas por pagar
						if ($Data['cpo_basetype'] == 10) {


							$sqlEstado1 = "SELECT
																			       count(t1.sc1_itemcode) item,
																			       sum(t1.sc1_quantity) cantidad
																			from dcsc t0
																			inner join csc1 t1 on t0.csc_docentry = t1.sc1_docentry
																			where t0.csc_docentry = :csc_docentry and t0.csc_doctype = :csc_doctype";


							$resEstado1 = $this->pedeo->queryTable($sqlEstado1, array(
								':csc_docentry' => $Data['cpo_baseentry'],
								':csc_doctype' => $Data['cpo_basetype']
								// ':vc1_itemcode' => $detail['ov1_itemcode']
							));

							$sqlEstado2 = "SELECT
																			       coalesce(count(distinct t3.po1_itemcode),0) item,
																			       coalesce(sum(t3.po1_quantity),0) cantidad
																			from dcsc t0
																			inner join csc1 t1 on t0.csc_docentry = t1.sc1_docentry
																			left join dcpo t2 on t0.csc_docentry = t2.cpo_baseentry and t0.csc_doctype = t2.cpo_basetype
																			left join cpo1 t3 on t2.cpo_docentry = t3.po1_docentry and t1.sc1_itemcode = t3.po1_itemcode
																			where t0.csc_docentry = :csc_docentry and t0.csc_doctype = :csc_doctype";


							$resEstado2 = $this->pedeo->queryTable($sqlEstado2, array(
								':csc_docentry' => $Data['cpo_baseentry'],
								':csc_doctype' => $Data['cpo_basetype']

							));

							$item_cot = $resEstado1[0]['item'];
						  $cantidad_cot = $resEstado1[0]['cantidad'];
							$item_ord = $resEstado2[0]['item'];
							$cantidad_ord = $resEstado2[0]['cantidad'];


							if(isset($resEstado[0]) && $resEstado[0]['estado'] == 1){

										$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																				VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

										$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


															':bed_docentry' => $Data['cpo_baseentry'],
															':bed_doctype' => $Data['cpo_basetype'],
															':bed_status' => 3, //ESTADO CERRADO
															':bed_createby' => $Data['cpo_createby'],
															':bed_date' => date('Y-m-d'),
															':bed_baseentry' => $resInsert,
															':bed_basetype' => $Data['cpo_doctype']
										));


										if(is_numeric($resInsertEstado) && $resInsertEstado > 0){

										}else{

												 $this->pedeo->trans_rollback();

													$respuesta = array(
														'error'   => true,
														'data' => $resInsertEstado,
														'mensaje'	=> 'No se pudo registrar la orden de compra'
													);


													$this->response($respuesta);

													return;
										}

							}

						}else if ($Data['cpo_basetype'] == 11) {


							$sqlEstado1 = 'SELECT distinct
													  count(t1.oc1_itemcode) item,
														sum(t1.oc1_quantity) cantidad
														from dcoc t0
														inner join coc1 t1 on t0.coc_docentry = t1.oc1_docentry
														where t0.coc_docentry = :coc_docentry and t0.coc_doctype = :coc_doctype
														';


							$resEstado1 = $this->pedeo->queryTable($sqlEstado1, array(
								':coc_docentry' => $Data['cpo_baseentry'],
								':coc_doctype' => $Data['cpo_basetype']
							));

							$sqlEstado2 = "SELECT
																			 coalesce(count(distinct t3.po1_itemcode),0) item,
																			 coalesce(count(t3.po1_quantity),0) cantidad
																			 FROM dcoc t0
																			 left join coc1 t1 on t0.coc_docentry = t1.oc1_docentry
																			 left join dcpo t2 on t0.coc_docentry = t2.cpo_docentry and t0.coc_doctype = t2.cpo_basetype
																			 left join cpo1 t3 on t2.cpo_docentry = t3.po1_docentry and t1.sc1_itemcode = t3.po1_itemcode
																			 where t0.coc_docentry = :coc_docentry and t0.coc_doctype = :coc_doctype";

							$resEstado2 = $this->pedeo->queryTable($sqlEstado2,array(
								':coc_docentry' => $Data['cpo_baseentry'],
								':coc_doctype' => $Data['cpo_basetype']
							));

							$item_sol = $resEstado1[0]['item'];
							$item_ord = $resEstado2[0]['item'];
							$cantidad_sol = $resEstado1[0]['cantidad'];
							$cantidad_ord = $resEstado2[0]['cantidad'];


							// print_r($item_sol);
							// print_r($item_ord);
							// print_r($cantidad_sol);
							// print_r($cantidad_ord);

							if($item_sol == $item_ord  &&  $cantidad_sol == $cantidad_ord){

										$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																				VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

										$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


															':bed_docentry' => $Data['cpo_baseentry'],
															':bed_doctype' => $Data['cpo_basetype'],
															':bed_status' => 3, //ESTADO CERRADO
															':bed_createby' => $Data['cpo_createby'],
															':bed_date' => date('Y-m-d'),
															':bed_baseentry' => $resInsert,
															':bed_basetype' => $Data['cpo_doctype']
										));


										if(is_numeric($resInsertEstado) && $resInsertEstado > 0){

										}else{

												 $this->pedeo->trans_rollback();

													$respuesta = array(
														'error'   => true,
														'data' => $resInsertEstado,
														'mensaje'	=> 'No se pudo registrar la la factura de compra'
													);


													$this->response($respuesta);

													return;
										}

							}

						}else if ($Data['cpo_basetype'] == 21) {
						
							//BUSCAR EL DOCENTRY Y DOCTYPE DEL COD ORIGEN
							$sql_aprov = "SELECT
											pap_doctype,
											pap_basetype,
											pap_baseentry
										FROM dpap
										WHERE pap_origen = ".$Data['cpo_doctype']." and pap_doctype = :pap_doctype
										and pap_docentry = :pap_docentry";
	
							$result_aprov = $this->pedeo->queryTable($sql_aprov,array(
								':pap_doctype' => $Data['cpo_doctype'],
								':pap_docentry' => $Data['cpo_baseentry']
							));
	
							// print_r($result_aprov);exit();die();
							if($result_aprov[0]['pap_basetype'] == 10){
	
							$sqlEstado1 = "SELECT
												count(t1.sc1_itemcode) item,
												sum(t1.sc1_quantity) cantidad
											from dcsc t0
											inner join csc1 t1 on t0.csc_docentry = t1.sc1_docentry
											where t0.csc_docentry = :csc_docentry and t0.csc_doctype = :csc_doctype";
	
	
							$resEstado1 = $this->pedeo->queryTable($sqlEstado1, array(
								':csc_docentry' => $result_aprov[0]['pap_baseentry'],
								':csc_doctype' => $result_aprov[0]['pap_basetype']
								// ':vc1_itemcode' => $detail['ov1_itemcode']
							));
	
							$sqlEstado2 = "SELECT
												coalesce(count(distinct t3.oc1_itemcode),0) item,
												coalesce(sum(t3.oc1_quantity),0) cantidad
											from dcsc t0
											inner join csc1 t1 on t0.csc_docentry = t1.sc1_docentry
											left join dcpo t2 on t0.csc_docentry = ".$result_aprov[0]['pap_baseentry']." and t0.csc_doctype = ".$result_aprov[0]['pap_basetype']."
											left join cpo1 t3 on t2.cpo_docentry = t3.po1_docentry and t1.sc1_itemcode = t3.po1_itemcode
											where t0.cpo_docentry = :cpo_docentry and t0.cpo_doctype = :cpo_doctype";
	
	
							$resEstado2 = $this->pedeo->queryTable($sqlEstado2, array(
								':cpo_docentry' => $result_aprov[0]['pap_baseentry'],
								':cpo_doctype' => $result_aprov[0]['pap_basetype']
	
							));
	
							$item_cot = $resEstado1[0]['item'];
							$cantidad_cot = $resEstado1[0]['cantidad'];
							$item_ord = $resEstado2[0]['item'];
							$cantidad_ord = $resEstado2[0]['cantidad'];
	
							// print_r($item_cot);
							// print_r($item_ord);
							// print_r($cantidad_cot);
							// print_r($cantidad_ord);exit();die();
	
	
							if($item_cot == $item_ord  &&  $cantidad_cot == $cantidad_ord){
	
										$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																				VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";
	
										$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(
	
	
															':bed_docentry' => $result_aprov[0]['pap_baseentry'],
															':bed_doctype' => $result_aprov[0]['pap_basetype'],
															':bed_status' => 3, //ESTADO CERRADO
															':bed_createby' => $Data['coc_createby'],
															':bed_date' => date('Y-m-d'),
															':bed_baseentry' => $resInsert,
															':bed_basetype' => $Data['coc_doctype']
										));
	
	
										if(is_numeric($resInsertEstado) && $resInsertEstado > 0){
	
										}else{
	
												 $this->pedeo->trans_rollback();
	
													$respuesta = array(
														'error'   => true,
														'data' => $resInsertEstado,
														'mensaje'	=> 'No se pudo registrar la orden de compra'
													);
	
	
													$this->response($respuesta);
	
													return;
										}
	
							}

						}else if($result_aprov[0]['pap_basetype'] == 11){
	
							$sqlEstado1 = "SELECT
												count(t1.oc1_itemcode) item,
												sum(t1.oc1_quantity) cantidad
											from dcoc t0
											inner join coc1 t1 on t0.coc_docentry = t1.oc1_docentry
											where t0.coc_docentry = :coc_docentry and t0.coc_doctype = :coc_doctype";
	
	
							$resEstado1 = $this->pedeo->queryTable($sqlEstado1, array(
								':coc_docentry' => $result_aprov[0]['pap_baseentry'],
								':coc_doctype' => $result_aprov[0]['pap_basetype']
								// ':vc1_itemcode' => $detail['ov1_itemcode']
							));
	
							$sqlEstado2 = "SELECT
												coalesce(count(distinct t3.oc1_itemcode),0) item,
												coalesce(sum(t3.oc1_quantity),0) cantidad
											from dcoc t0
											inner join coc1 t1 on t0.coc_docentry = t1.oc1_docentry
											left join dcpo t2 on t0.coc_docentry = ".$result_aprov[0]['pap_baseentry']." and t0.csc_doctype = ".$result_aprov[0]['pap_basetype']."
											left join cpo1 t3 on t2.cpo_docentry = t3.po1_docentry and t1.sc1_itemcode = t3.po1_itemcode
											where t0.cpo_docentry = :cpo_docentry and t0.cpo_doctype = :cpo_doctype";
	
	
							$resEstado2 = $this->pedeo->queryTable($sqlEstado2, array(
								':cpo_docentry' => $result_aprov[0]['pap_baseentry'],
								':cpo_doctype' => $result_aprov[0]['pap_basetype']
	
							));
	
							$item_cot = $resEstado1[0]['item'];
							$cantidad_cot = $resEstado1[0]['cantidad'];
							$item_ord = $resEstado2[0]['item'];
							$cantidad_ord = $resEstado2[0]['cantidad'];
	
							// print_r($item_cot);
							// print_r($item_ord);
							// print_r($cantidad_cot);
							// print_r($cantidad_ord);exit();die();
	
	
							if($item_cot == $item_ord  &&  $cantidad_cot == $cantidad_ord){
	
										$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																				VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";
	
										$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(
	
	
															':bed_docentry' => $result_aprov[0]['pap_baseentry'],
															':bed_doctype' => $result_aprov[0]['pap_basetype'],
															':bed_status' => 3, //ESTADO CERRADO
															':bed_createby' => $Data['coc_createby'],
															':bed_date' => date('Y-m-d'),
															':bed_baseentry' => $resInsert,
															':bed_basetype' => $Data['coc_doctype']
										));
	
	
										if(is_numeric($resInsertEstado) && $resInsertEstado > 0){
	
										}else{
	
												 $this->pedeo->trans_rollback();
	
													$respuesta = array(
														'error'   => true,
														'data' => $resInsertEstado,
														'mensaje'	=> 'No se pudo registrar la orden de compra'
													);
	
	
													$this->response($respuesta);
	
													return;
										}
	
							}

						}
	
						}

						// Si todo sale bien despues de insertar el detalle de la orden de compra
						// se confirma la trasaccion  para que los cambios apliquen permanentemente
						// en la base de datos y se confirma la operacion exitosa.
						$this->pedeo->trans_commit();

	          $respuesta = array(
	            'error' => false,
	            'data' => $resInsert,
	            'mensaje' =>'Orden de Compra registrada con exito'
	          );


        }else{
					// Se devuelven los cambios realizados en la transaccion
					// si occurre un error  y se muestra devuelve el error.
							$this->pedeo->trans_rollback();

              $respuesta = array(
                'error'   => true,
                'data' => $resInsert,
                'mensaje'	=> 'No se pudo registrar la Orden de Compra'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR ORDEN DE COMPRA
  public function updatePurchaseOrder_post(){

      $Data = $this->post();

			if(!isset($Data['dpo_docentry']) OR !isset($Data['dpo_docnum']) OR
				 !isset($Data['dpo_docdate']) OR !isset($Data['dpo_duedate']) OR
				 !isset($Data['dpo_duedev']) OR !isset($Data['dpo_pricelist']) OR
				 !isset($Data['dpo_cardcode']) OR !isset($Data['dpo_cardname']) OR
				 !isset($Data['dpo_currency']) OR !isset($Data['dpo_contacid']) OR
				 !isset($Data['dpo_slpcode']) OR !isset($Data['dpo_empid']) OR
				 !isset($Data['dpo_comment']) OR !isset($Data['dpo_doctotal']) OR
				 !isset($Data['dpo_baseamnt']) OR !isset($Data['dpo_taxtotal']) OR
				 !isset($Data['dpo_discprofit']) OR !isset($Data['dpo_discount']) OR
				 !isset($Data['dpo_createat']) OR !isset($Data['dpo_baseentry']) OR
				 !isset($Data['dpo_basetype']) OR !isset($Data['dpo_doctype']) OR
				 !isset($Data['dpo_idadd']) OR !isset($Data['dpo_adress']) OR
				 !isset($Data['dpo_paytype']) OR !isset($Data['dpo_attch']) OR
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
            'mensaje' =>'No se encontro el detalle de la Orden de Compra'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }

      $sqlUpdate = "UPDATE dcpo SET dpo_docdate=:dpo_docdate,dpo_duedate=:dpo_duedate, dpo_duedev=:dpo_duedev, dpo_pricelist=:dpo_pricelist, dpo_cardcode=:dpo_cardcode,
			  						dpo_cardname=:dpo_cardname, dpo_currency=:dpo_currency, dpo_contacid=:dpo_contacid, dpo_slpcode=:dpo_slpcode,
										dpo_empid=:dpo_empid, dpo_comment=:dpo_comment, dpo_doctotal=:dpo_doctotal, dpo_baseamnt=:dpo_baseamnt,
										dpo_taxtotal=:dpo_taxtotal, dpo_discprofit=:dpo_discprofit, dpo_discount=:dpo_discount, dpo_createat=:dpo_createat,
										dpo_baseentry=:dpo_baseentry, dpo_basetype=:dpo_basetype, dpo_doctype=:dpo_doctype, dpo_idadd=:dpo_idadd,
										dpo_adress=:dpo_adress, dpo_paytype=:dpo_paytype, dpo_attch=:dpo_attch WHERE dpo_docentry=:dpo_docentry";

      $this->pedeo->trans_begin();

      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
							':dpo_docnum' => is_numeric($Data['dpo_docnum'])?$Data['dpo_docnum']:0,
							':dpo_docdate' => $this->validateDate($Data['dpo_docdate'])?$Data['dpo_docdate']:NULL,
							':dpo_duedate' => $this->validateDate($Data['dpo_duedate'])?$Data['dpo_duedate']:NULL,
							':dpo_duedev' => $this->validateDate($Data['dpo_duedev'])?$Data['dpo_duedev']:NULL,
							':dpo_pricelist' => is_numeric($Data['dpo_pricelist'])?$Data['dpo_pricelist']:0,
							':dpo_cardcode' => isset($Data['dpo_pricelist'])?$Data['dpo_pricelist']:NULL,
							':dpo_cardname' => isset($Data['dpo_cardname'])?$Data['dpo_cardname']:NULL,
							':dpo_currency' => is_numeric($Data['dpo_currency'])?$Data['dpo_currency']:0,
							':dpo_contacid' => isset($Data['dpo_contacid'])?$Data['dpo_contacid']:NULL,
							':dpo_slpcode' => is_numeric($Data['dpo_slpcode'])?$Data['dpo_slpcode']:0,
							':dpo_empid' => is_numeric($Data['dpo_empid'])?$Data['dpo_empid']:0,
							':dpo_comment' => isset($Data['dpo_comment'])?$Data['dpo_comment']:NULL,
							':dpo_doctotal' => is_numeric($Data['dpo_doctotal'])?$Data['dpo_doctotal']:0,
							':dpo_baseamnt' => is_numeric($Data['dpo_baseamnt'])?$Data['dpo_baseamnt']:0,
							':dpo_taxtotal' => is_numeric($Data['dpo_taxtotal'])?$Data['dpo_taxtotal']:0,
							':dpo_discprofit' => is_numeric($Data['dpo_discprofit'])?$Data['dpo_discprofit']:0,
							':dpo_discount' => is_numeric($Data['dpo_discount'])?$Data['dpo_discount']:0,
							':dpo_createat' => $this->validateDate($Data['dpo_createat'])?$Data['dpo_createat']:NULL,
							':dpo_baseentry' => is_numeric($Data['dpo_baseentry'])?$Data['dpo_baseentry']:0,
							':dpo_basetype' => is_numeric($Data['dpo_basetype'])?$Data['dpo_basetype']:0,
							':dpo_doctype' => is_numeric($Data['dpo_doctype'])?$Data['dpo_doctype']:0,
							':dpo_idadd' => isset($Data['dpo_idadd'])?$Data['dpo_idadd']:NULL,
							':dpo_adress' => isset($Data['dpo_adress'])?$Data['dpo_adress']:NULL,
							':dpo_paytype' => is_numeric($Data['dpo_paytype'])?$Data['dpo_paytype']:0,
							':dpo_attch' => $this->getUrl(count(trim(($Data['dpo_attch']))) > 0 ? $Data['dpo_attch']:NULL),
							':dpo_docentry' => $Data['dpo_docentry']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

						$this->pedeo->queryTable("DELETE FROM cpo1 WHERE po1_docentry=:po1_docentry", array(':po1_docentry' => $Data['dpo_docentry']));

						foreach ($ContenidoDetalle as $key => $detail) {

									$sqlInsertDetail = "INSERT INTO cpo1(po1_docentry, po1_itemcode, po1_itemname, po1_quantity, po1_uom, po1_whscode,
																			po1_price, po1_vat, po1_vatsum, po1_discount, po1_linetotal, po1_costcode, po1_ubusiness, po1_project,
																			po1_acctcode, po1_basetype, po1_doctype, po1_avprice, po1_inventory, po1_ubication)VALUES(:po1_docentry, :po1_itemcode, :po1_itemname, :po1_quantity,
																			:po1_uom, :po1_whscode,:po1_price, :po1_vat, :po1_vatsum, :po1_discount, :po1_linetotal, :po1_costcode, :po1_ubusiness, :po1_project,
																			:po1_acctcode, :po1_basetype, :po1_doctype, :po1_avprice, :po1_inventory, :po1_ubication)";

									$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
											':po1_docentry' => $resInsert,
											':po1_itemcode' => isset($detail['po1_itemcode'])?$detail['po1_itemcode']:NULL,
											':po1_itemname' => isset($detail['po1_itemname'])?$detail['po1_itemname']:NULL,
											':po1_quantity' => is_numeric($detail['po1_quantity'])?$detail['po1_quantity']:0,
											':po1_uom' => isset($detail['po1_uom'])?$detail['po1_uom']:NULL,
											':po1_whscode' => isset($detail['po1_whscode'])?$detail['po1_whscode']:NULL,
											':po1_price' => is_numeric($detail['po1_price'])?$detail['po1_price']:0,
											':po1_vat' => is_numeric($detail['po1_vat'])?$detail['po1_vat']:0,
											':po1_vatsum' => is_numeric($detail['po1_vatsum'])?$detail['po1_vatsum']:0,
											':po1_discount' => is_numeric($detail['po1_discount'])?$detail['po1_discount']:0,
											':po1_linetotal' => is_numeric($detail['po1_linetotal'])?$detail['po1_linetotal']:0,
											':po1_costcode' => isset($detail['po1_costcode'])?$detail['po1_costcode']:NULL,
											':po1_ubusiness' => isset($detail['po1_ubusiness'])?$detail['po1_ubusiness']:NULL,
											':po1_project' => isset($detail['po1_project'])?$detail['po1_project']:NULL,
											':po1_acctcode' => is_numeric($detail['po1_acctcode'])?$detail['po1_acctcode']:0,
											':po1_basetype' => is_numeric($detail['po1_basetype'])?$detail['po1_basetype']:0,
											':po1_doctype' => is_numeric($detail['po1_doctype'])?$detail['po1_doctype']:0,
											':po1_avprice' => is_numeric($detail['po1_avprice'])?$detail['po1_avprice']:0,
											':po1_inventory' => is_numeric($detail['po1_inventory'])?$detail['po1_inventory']:NULL,
											':po1_ubication' => is_numeric($detail['po1_ubication'])?$detail['po1_ubication']:NULL
									));

									if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
											// Se verifica que el detalle no de error insertando //
									}else{

											// si falla algun insert del detalle de la orden de compra se devuelven los cambios realizados por la transaccion,
											// se retorna el error y se detiene la ejecucion del codigo restante.
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data' => $resInsert,
													'mensaje'	=> 'No se pudo registrar la Orden de Compra'
												);

												 $this->response($respuesta);

												 return;
									}
						}


						$this->pedeo->trans_commit();

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Orden de Compra actualizada con exito'
            );


      }else{

						$this->pedeo->trans_rollback();

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la Orden de Compra'
            );

      }

       $this->response($respuesta);
  }


  //OBTENER ORDEN DE COMPRAS
  public function getPurchaseOrder_get(){

				$DECI_MALES =  $this->generic->getDecimals();

        $sqlSelect = self::getColumn('dcpo','cpo','','',$DECI_MALES);


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


	//OBTENER ORDEN DE COMPRA POR ID
	public function getPurchaseOrderById_get(){

				$Data = $this->get();

				if(!isset($Data['dpo_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM dcpo  WHERE dpo_docentry =:dpo_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":dpo_docentry" => $Data['dpo_docentry']));

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


	//OBTENER ORDEN DE COMPRA DETALLE POR ID
	public function getPurchaseOrderDetail_get(){

				$Data = $this->get();

				if(!isset($Data['po1_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM cpo1 WHERE po1_docentry =:po1_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":po1_docentry" => $Data['po1_docentry']));

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


	//OBTENER ORDEN DE COMPRA POR SOCIO DE NEGOCIO
	public function getPurchaseOrderBySN_get(){

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

				$sqlSelect = "SELECT
												t0.*
											FROM dcpo t0
											left join estado_doc t1 on t0.cpo_docentry = t1.entry and t0.cpo_doctype = t1.tipo
											left join responsestatus t2 on t1.entry = t2.id and t1.tipo = t2.tipo
											where t2.estado = 'Abierto' and t0.cpo_cardcode =:cpo_cardcode";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":cpo_cardcode" => $Data['dms_card_code']));

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
