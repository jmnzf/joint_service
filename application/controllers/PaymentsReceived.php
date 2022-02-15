<?php
// PAGOS RECIBIDOS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PaymentsReceived extends REST_Controller {

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

	// Obtener pagos recibidos
  public function getPaymentsReceived_get(){
    //
    $sqlSelect = "SELECT * FROM gbpr";

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

  //CREAR NUEVO PAGO
	public function createPaymentsReceived_post(){

      $Data = $this->post();
			$DocNumVerificado = 0;
			$DetalleAsientoCuentaTercero = new stdClass();
			$DetalleConsolidadoAsientoCuentaTercero = [];
			$llaveAsientoCuentaTercero = "";
			$posicionAsientoCuentaTercero = 0;
			$cuentaTercero = 0;
			$inArrayAsientoCuentaTercero = array();

			// Se globaliza la variable sqlDetalleAsiento
			$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
													ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
													ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
													ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref)VALUES (:ac1_trans_id, :ac1_account,
													:ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
													:ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
													:ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
													:ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref)";

      if(!isset($Data['detail']) OR !isset($Data['bpr_billpayment'])){

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
            'mensaje' =>'No se encontro el detalle del pago'
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

				$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['bpr_series']));

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
				$resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $Data['bpr_currency'], ':tsa_date' => $Data['bpr_docdate']));

				if(isset($resBusTasa[0])){

				}else{

						if(trim($Data['bpr_currency']) != $MONEDALOCAL ){

							$respuesta = array(
								'error' => true,
								'data'  => array(),
								'mensaje' =>'No se encrontro la tasa de cambio para la moneda: '.$Data['bpr_currency'].' en la actual fecha del documento: '.$Data['bpr_docdate'].' y la moneda local: '.$resMonedaLoc[0]['pgm_symbol']
							);

							$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

							return;
						}
				}

				$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
				$resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $Data['bpr_docdate']));

				if(isset($resBusTasa2[0])){

				}else{
						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :'.$Data['bpr_docdate']
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}

				$TasaDocLoc = isset($resBusTasa[0]['tsa_value']) ? $resBusTasa[0]['tsa_value'] : 1;
				$TasaLocSys = $resBusTasa2[0]['tsa_value'];

				// FIN DEL PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO


				// BUSCANDO LA CUENTA DEL TERCERO

				$sqlCuentaTercero = "SELECT  f1.dms_card_code, f2.mgs_acct FROM dmsn AS f1
														 JOIN dmgs  AS f2
														 ON CAST(f2.mgs_id AS varchar(100)) = f1.dms_group_num
														 WHERE  f1.dms_card_code = :dms_card_code
														 AND f1.dms_card_type = '1'"; // 1 para clientes

				$resCuentaTercero = $this->pedeo->queryTable($sqlCuentaTercero, array(":dms_card_code" => $Data['bpr_cardcode']));

				if(isset($resCuentaTercero[0])){

						$cuentaTercero = $resCuentaTercero[0]['mgs_acct'];

				}else{

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' => $resInsertDetail,
							'mensaje'	=> 'No se pudo registrar el pago, el tercero no tiene la cuenta asociada ('.$Data['bpr_cardcode'].')'
						);

						 $this->response($respuesta);

						 return;
				}

				//FIN BUSQUEDA CUENTA TERCERO

        $sqlInsert = "INSERT INTO
                            	gbpr (bpr_cardcode,bpr_doctype,bpr_cardname,bpr_address,bpr_perscontact,bpr_series,bpr_docnum,bpr_docdate,bpr_taxdate,bpr_ref,bpr_transid,
                                    bpr_comments,bpr_memo,bpr_acctransfer,bpr_datetransfer,bpr_reftransfer,bpr_doctotal,bpr_vlrpaid,bpr_project,bpr_createby,
                                    bpr_createat,bpr_payment,bpr_currency)
                      VALUES (:bpr_cardcode,:bpr_doctype,:bpr_cardname,:bpr_address,:bpr_perscontact,:bpr_series,:bpr_docnum,:bpr_docdate,:bpr_taxdate,:bpr_ref,:bpr_transid,
                              :bpr_comments,:bpr_memo,:bpr_acctransfer,:bpr_datetransfer,:bpr_reftransfer,:bpr_doctotal,:bpr_vlrpaid,:bpr_project,:bpr_createby,
                              :bpr_createat,:bpr_payment,:bpr_currency)";


				// Se Inicia la transaccion,
				// Todas las consultas de modificacion siguientes
				// aplicaran solo despues que se confirme la transaccion,
				// de lo contrario no se aplicaran los cambios y se devolvera
				// la base de datos a su estado original.

			  $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':bpr_cardcode' => isset($Data['bpr_cardcode'])?$Data['bpr_cardcode']:NULL,
							':bpr_doctype' => is_numeric($Data['bpr_doctype'])?$Data['bpr_doctype']:0,
              ':bpr_cardname' => isset($Data['bpr_cardname'])?$Data['bpr_cardname']:NULL,
              ':bpr_address' => isset($Data['bpr_address'])?$Data['bpr_address']:NULL,
              ':bpr_perscontact' => is_numeric($Data['bpr_perscontact'])?$Data['bpr_perscontact']:0,
              ':bpr_series' => is_numeric($Data['bpr_series'])?$Data['bpr_series']:0,
              ':bpr_docnum' => $DocNumVerificado,
              ':bpr_docdate' => $this->validateDate($Data['bpr_docdate'])?$Data['bpr_docdate']:NULL,
              ':bpr_taxdate' => $this->validateDate($Data['bpr_taxdate'])?$Data['bpr_taxdate']:NULL,
              ':bpr_ref' => isset($Data['bpr_ref'])?$Data['bpr_ref']:NULL,
              ':bpr_transid' => is_numeric($Data['bpr_transid'])?$Data['bpr_transid']:0,
              ':bpr_comments' => isset($Data['bpr_comments'])?$Data['bpr_comments']:NULL,
              ':bpr_memo' => isset($Data['bpr_memo'])?$Data['bpr_memo']:NULL,
              ':bpr_acctransfer' => isset($Data['bpr_acctransfer'])?$Data['bpr_acctransfer']:NULL,
              ':bpr_datetransfer' => $this->validateDate($Data['bpr_datetransfer'])?$Data['bpr_datetransfer']:NULL,
              ':bpr_reftransfer' => isset($Data['bpr_reftransfer'])?$Data['bpr_reftransfer']:NULL,
              ':bpr_doctotal' => is_numeric($Data['bpr_doctotal'])?$Data['bpr_doctotal']:0,
              ':bpr_vlrpaid' => is_numeric($Data['bpr_vlrpaid'])?$Data['bpr_vlrpaid']:0,
              ':bpr_project' => isset($Data['bpr_project'])?$Data['bpr_project']:NULL,
              ':bpr_createby' => isset($Data['bpr_createby'])?$Data['bpr_createby']:NULL,
              ':bpr_createat' => $this->validateDate($Data['bpr_createat'])?$Data['bpr_createat']:NULL,
              ':bpr_payment' => isset($Data['bpr_payment'])?$Data['bpr_payment']:NULL,
							':bpr_currency' => isset($Data['bpr_currency'])?$Data['bpr_currency']:NULL
						));

        if(is_numeric($resInsert) && $resInsert > 0){

					// Se actualiza la serie de la numeracion del documento

					$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
					$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
							':pgs_nextnum' => $DocNumVerificado,
							':pgs_id'      => $Data['bpr_series']
					));


					if(is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1){

					}else{
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resActualizarNumeracion,
									'mensaje'	=> 'No se pudo crear el pago'
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
							':mac_base_type' => is_numeric($Data['bpr_doctype'])?$Data['bpr_doctype']:0,
							':mac_base_entry' => $resInsert,
							':mac_doc_date' => $this->validateDate($Data['bpr_docdate'])?$Data['bpr_docdate']:NULL,
							':mac_doc_duedate' => $this->validateDate($Data['bpr_docdate'])?$Data['bpr_docdate']:NULL,
							':mac_legal_date' => $this->validateDate($Data['bpr_docdate'])?$Data['bpr_docdate']:NULL,
							':mac_ref1' => is_numeric($Data['bpr_doctype'])?$Data['bpr_doctype']:0,
							':mac_ref2' => "",
							':mac_ref3' => "",
							':mac_loc_total' => is_numeric($Data['bpr_doctotal'])?$Data['bpr_doctotal']:0,
							':mac_fc_total' => is_numeric($Data['bpr_doctotal'])?$Data['bpr_doctotal']:0,
							':mac_sys_total' => is_numeric($Data['bpr_doctotal'])?$Data['bpr_doctotal']:0,
							':mac_trans_dode' => 1,
							':mac_beline_nume' => 1,
							':mac_vat_date' => $this->validateDate($Data['bpr_docdate'])?$Data['bpr_docdate']:NULL,
							':mac_serie' => 1,
							':mac_number' => 1,
							':mac_bammntsys' => 0,
							':mac_bammnt' => 0,
							':mac_wtsum' => 1,
							':mac_vatsum' => 0,
							':mac_comments' => isset($Data['bpr_comments'])?$Data['bpr_comments']:NULL,
							':mac_create_date' => $this->validateDate($Data['bpr_createat'])?$Data['bpr_createat']:NULL,
							':mac_made_usuer' => isset($Data['bpr_createby'])?$Data['bpr_createby']:NULL,
							':mac_update_date' => date("Y-m-d"),
							':mac_update_user' => isset($Data['bpr_createby'])?$Data['bpr_createby']:NULL
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
									'mensaje'	=> 'No se pudo registrar el pago'
								);

								 $this->response($respuesta);

								 return;
					}


					// INICIA INSERCION DEL DETALLE

          foreach ($ContenidoDetalle as $key => $detail) {


								// SOLO SI NO ES UN ANTICIPO CLIENTE
								if($Data['bpr_billpayment'] == '0' || $Data['bpr_billpayment'] == 0){
											//VALIDAR EL VALOR QUE SE ESTA PAGANDO NO SEA MAYOR AL SALDO DE LA FACTURA
											$VlrPayFact = "SELECT COALESCE(dvf_paytoday,0) as dvf_paytoday,dvf_doctotal from dvfv WHERE dvf_docentry = :dvf_docentry and dvf_doctype = :dvf_doctype";
											$resVlrPayFact = $this->pedeo->queryTable($VlrPayFact, array(
												':dvf_docentry' => $detail['pr1_docentry'],
												':dvf_doctype' => $detail['pr1_doctype']
											));


											if(isset($resVlrPayFact[0])){

												$VlrPaidActual = $detail['pr1_vlrpaid'];
												$VlrPaidFact = $resVlrPayFact[0]['dvf_paytoday'];

												$SumVlr =  $VlrPaidActual + $VlrPaidFact ;

												if($SumVlr <= $resVlrPayFact[0]['dvf_doctotal'] ){


												}else{
													$this->pedeo->trans_rollback();

													$respuesta = array(
														'error'   => true,
														'data' => '',
														'mensaje'	=> 'El valor a pagar no puede ser mayor al saldo de la factura'
													);

													 $this->response($respuesta);

													 return;
												}

											}else{
												$this->pedeo->trans_rollback();
												$respuesta = array(
													'error'   => true,
													'data' => $resVlrPayFact,
													'mensaje'	=> 'No tiene valor para realizar la operacion');

													$this->response($respuesta);

												 return;

											}
								}



                $sqlInsertDetail = "INSERT INTO
                                        	bpr1 (pr1_docnum,pr1_docentry,pr1_numref,pr1_docdate,pr1_vlrtotal,pr1_vlrpaid,pr1_comments,pr1_porcdiscount,pr1_doctype,
                                            pr1_docduedate,pr1_daysbackw,pr1_vlrdiscount,pr1_ocrcode, pr1_accountid)
                                    VALUES (:pr1_docnum,:pr1_docentry,:pr1_numref,:pr1_docdate,:pr1_vlrtotal,:pr1_vlrpaid,:pr1_comments,:pr1_porcdiscount,
                                            :pr1_doctype,:pr1_docduedate,:pr1_daysbackw,:pr1_vlrdiscount,:pr1_ocrcode, :pr1_accountid)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':pr1_docnum' => $resInsert,
                        ':pr1_docentry' => $resInsert,
                        ':pr1_numref' => isset($detail['pr1_numref'])?$detail['pr1_numref']:NULL,
                        ':pr1_docdate' =>  $this->validateDate($detail['pr1_docdate'])?$detail['pr1_docdate']:NULL,
                        ':pr1_vlrtotal' => is_numeric($detail['pr1_vlrtotal'])?$detail['pr1_vlrtotal']:0,
                        ':pr1_vlrpaid' => is_numeric($detail['pr1_vlrpaid'])?$detail['pr1_vlrpaid']:0,
                        ':pr1_comments' => isset($detail['pr1_comments'])?$detail['pr1_comments']:NULL,
                        ':pr1_porcdiscount' => is_numeric($detail['pr1_porcdiscount'])?$detail['pr1_porcdiscount']:0,
                        ':pr1_doctype' => is_numeric($detail['pr1_doctype'])?$detail['pr1_doctype']:0,
                        ':pr1_docduedate' => $this->validateDate($detail['pr1_docdate'])?$detail['pr1_docdate']:NULL,
                        ':pr1_daysbackw' => is_numeric($detail['pr1_daysbackw'])?$detail['pr1_daysbackw']:0,
                        ':pr1_vlrdiscount' => is_numeric($detail['pr1_vlrdiscount'])?$detail['pr1_vlrdiscount']:0,
                        ':pr1_ocrcode' => isset($detail['pr1_ocrcode'])?$detail['pr1_ocrcode']:NULL,
												':pr1_accountid' => is_numeric($detail['pr1_accountid'])?$detail['pr1_accountid']:0

                ));
								// Se verifica que el detalle no de error insertando //
								// if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
								// 	  	// SOLO SI NO ES UN ANTICIPO
								// 			if($Data['bpr_billpayment'] == '0' || $Data['bpr_billpayment'] == 0){
								//
								// 						$sqlUpdateFactPay = "UPDATE  dvfv  SET dvf_paytoday = COALESCE(dvf_paytoday,0)+:dvf_paytoday WHERE dvf_docentry = :dvf_docentry and dvf_doctype = :dvf_doctype";
								//
								// 						$resUpdateFactPay = $this->pedeo->updateRow($sqlUpdateFactPay,array(
								//
								// 							':dvf_paytoday' => $detail['pr1_vlrpaid'],
								// 							':dvf_docentry' => $detail['pr1_docentry'],
								// 							':dvf_doctype'  => $detail['pr1_doctype']
								//
								//
								// 						));
								//
								// 						if(is_numeric($resUpdateFactPay) && $resUpdateFactPay == 1){
								//
								//
								//
								// 						}else{
								// 							$this->pedeo->trans_rollback();
								//
								// 							$respuesta = array(
								// 								'error'   => true,
								// 								'data' => $resUpdateFactPay,
								// 								'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura '.$detail['pr1_docentry']
								// 							);
								//
								// 							 $this->response($respuesta);
								//
								// 							 return;
								// 						}
								// 			}
								//
								// }else{
								//
								// 		// si falla algun insert del detalle de la cotizacion se devuelven los cambios realizados por la transaccion,
								// 		// se retorna el error y se detiene la ejecucion del codigo restante.
								// 			$this->pedeo->trans_rollback();
								//
								// 			$respuesta = array(
								// 				'error'   => true,
								// 				'data' => $resInsertDetail,
								// 				'mensaje'	=> 'No se pudo registrar el pago'
								// 			);
								//
								// 			 $this->response($respuesta);
								//
								// 			 return;
								// }


								// LLENANDO DETALLE ASIENTOS CONTABLES (AGRUPACION)
								$DetalleAsientoCuentaTercero = new stdClass();

								$DetalleAsientoCuentaTercero->bpr_cardcode  = isset($Data['bpr_cardcode'])?$Data['bpr_cardcode']:NULL;
								$DetalleAsientoCuentaTercero->pr1_doctype   = is_numeric($detail['pr1_doctype'])?$detail['pr1_doctype']:0;
								$DetalleAsientoCuentaTercero->pr1_docentry  = is_numeric($detail['pr1_docentry'])?$detail['pr1_docentry']:0;
								$DetalleAsientoCuentaTercero->cuentatercero = is_numeric($detail['pr1_cuenta'])?$detail['pr1_cuenta']:0;
								$DetalleAsientoCuentaTercero->cuentatercero = ($DetalleAsientoCuentaTercero->cuentatercero == 0)? $detail['pr1_accountid']:$DetalleAsientoCuentaTercero->cuentatercero;
								$DetalleAsientoCuentaTercero->cuentaNaturaleza = substr($DetalleAsientoCuentaTercero->cuentatercero, 0, 1);
								$DetalleAsientoCuentaTercero->pr1_vlrpaid = is_numeric($detail['pr1_vlrpaid'])?$detail['pr1_vlrpaid']:0;
								$DetalleAsientoCuentaTercero->pr1_docdate	= $this->validateDate($detail['pr1_docdate'])?$detail['pr1_docdate']:NULL;

								$llaveAsientoCuentaTercero = $DetalleAsientoCuentaTercero->bpr_cardcode.$DetalleAsientoCuentaTercero->pr1_docentry.$DetalleAsientoCuentaTercero->pr1_doctype;


								//********************
								if(in_array( $llaveAsientoCuentaTercero, $inArrayAsientoCuentaTercero )){

										$posicion = $this->buscarPosicion( $llaveAsientoCuentaTercero, $inArrayAsientoCuentaTercero );

								}else{

										array_push( $inArrayAsientoCuentaTercero, $llaveAsientoCuentaTercero );
										$posicionAsientoCuentaTercero = $this->buscarPosicion( $llaveAsientoCuentaTercero, $inArrayAsientoCuentaTercero );

								}


								if( isset($DetalleConsolidadoAsientoCuentaTercero[$posicionAsientoCuentaTercero])){

									if(!is_array($DetalleConsolidadoAsientoCuentaTercero[$posicionAsientoCuentaTercero])){
										$DetalleConsolidadoAsientoCuentaTercero[$posicionAsientoCuentaTercero] = array();
									}

								}else{
									$DetalleConsolidadoAsientoCuentaTercero[$posicionAsientoCuentaTercero] = array();
								}

								array_push( $DetalleConsolidadoAsientoCuentaTercero[$posicionAsientoCuentaTercero], $DetalleAsientoCuentaTercero);

								//*******************************************************\\

								//FIN LLENADO DETALLE ASIENTOS CONTABLE (AGRUPACION)

          }

					//FIN PROCEDIMIENTO DETALLE PAGO RECIBIDO


					// SE INSERTA ASIENTO INGRESO

							$debito = 0;
							$credito = 0;
							$MontoSysDB = 0;
							$MontoSysCR = 0;
							$cuenta = $Data['bpr_acctransfer'];
							$codigoCuentaIngreso = substr($cuenta , 0, 1);
							$granTotalIngreso = $Data['bpr_vlrpaid'];
							$granTotalIngresoOriginal = $granTotalIngreso;

							if(trim($Data['bpr_currency']) != $MONEDALOCAL ){
									$granTotalIngreso = ($granTotalIngreso * $TasaDocLoc);
							}


							switch ($codigoCuentaIngreso) {
								case 1:
									$debito = $granTotalIngreso;
									if(trim($Data['bpr_currency']) != $MONEDASYS ){

											$MontoSysDB = ($debito / $TasaLocSys);

									}else{

											$MontoSysDB = $granTotalIngresoOriginal;
									}
									break;

								case 2:
									$debito = $granTotalIngreso;
									if(trim($Data['bpr_currency']) != $MONEDASYS ){

											$MontoSysDB = ($debito / $TasaLocSys);

									}else{

											$MontoSysDB = $granTotalIngresoOriginal;
									}
									break;

								case 3:
									$debito = $granTotalIngreso;
									if(trim($Data['bpr_currency']) != $MONEDASYS ){

											$MontoSysDB = ($debito / $TasaLocSys);

									}else{

											$MontoSysDB = $granTotalIngresoOriginal;
									}
									break;

								case 4:
									$debito = $granTotalIngreso;
									if(trim($Data['bpr_currency']) != $MONEDASYS ){

											$MontoSysDB = ($debito / $TasaLocSys);

									}else{

											$MontoSysDB = $granTotalIngresoOriginal;
									}
									break;

								case 5:
									$debito = $granTotalIngreso;
									if(trim($Data['bpr_currency']) != $MONEDASYS ){

											$MontoSysDB = ($debito / $TasaLocSys);

									}else{

											$MontoSysDB = $granTotalIngresoOriginal;
									}
									break;

								case 6:
									$debito = $granTotalIngreso;
									if(trim($Data['bpr_currency']) != $MONEDASYS ){

											$MontoSysDB = ($debito / $TasaLocSys);

									}else{

											$MontoSysDB = $granTotalIngresoOriginal;
									}
									break;

								case 7:
									$debito = $granTotalIngreso;
									if(trim($Data['bpr_currency']) != $MONEDASYS ){

											$MontoSysDB = ($debito / $TasaLocSys);

									}else{

											$MontoSysDB = $granTotalIngresoOriginal;
									}
									break;
							}


							$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

									':ac1_trans_id' => $resInsertAsiento,
									':ac1_account' => $cuenta,
									':ac1_debit' => round($debito, 2),
									':ac1_credit' => round($credito, 2),
									':ac1_debit_sys' => round($MontoSysDB,2),
									':ac1_credit_sys' => round($MontoSysCR,2),
									':ac1_currex' => 0,
									':ac1_doc_date' => $this->validateDate($Data['bpr_docdate'])?$Data['bpr_docdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['bpr_docdate'])?$Data['bpr_docdate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => 0,
									':ac1_font_line' => 1,
									':ac1_font_type' => 0,
									':ac1_accountvs' => 1,
									':ac1_doctype' => 18,
									':ac1_ref1' => "",
									':ac1_ref2' => "",
									':ac1_ref3' => "",
									':ac1_prc_code' => 0,
									':ac1_uncode' => 0,
									':ac1_prj_code' => 0,
									':ac1_rescon_date' => NULL,
									':ac1_recon_total' => 0,
									':ac1_made_user' => isset($Data['bpr_createby'])?$Data['bpr_createby']:NULL,
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
									':ac1_legal_num' => isset($Data['bpr_cardcode'])?$Data['bpr_cardcode']:NULL,
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
										'mensaje'	=> 'No se pudo registrar el pago recibido, ocurrio un error al ingresar el asiento del ingreso'
									);

									 $this->response($respuesta);

									 return;
						}

					// FIN PROCESO ASIENTO INGRESO




					if($Data['bpr_billpayment'] == '0' || $Data['bpr_billpayment'] == 0){

						//Procedimiento para llenar ASIENTO CON CUENTA TERCERO SEGUN GRUPO DE CUENTAS


						foreach ($DetalleConsolidadoAsientoCuentaTercero as $key => $posicion) {
										$TotalPagoRecibido = 0;
										$TotalPagoRecibidoOriginal = 0;
										$TotalDiferencia = 0;
										$cuenta = 0;
										$cuentaLinea = 0;
										$docentry = 0;
										$doctype = 0;
										$fechaDocumento = '';
										$TasaOld = 0;
										$DireferenciaCambio = 0; // SOLO SE USA PARA SABER SI EXISTE UNA DIFERENCIA DE CAMBIO
										$CuentaDiferenciaCambio = 0;

										foreach ($posicion as $key => $value) {

													$TotalPagoRecibido = ( $TotalPagoRecibido + $value->pr1_vlrpaid );


													$docentry = $value->pr1_docentry;
													$doctype  = $value->pr1_doctype;
													$cuenta   = $value->cuentaNaturaleza;
													$fechaDocumento = $value->pr1_docdate;
													$cuentaLinea = $value->cuentatercero;

										}


										$debito = 0;
										$credito = 0;
										$MontoSysDB = 0;
										$MontoSysCR = 0;
										$TotalPagoRecibidoOriginal = $TotalPagoRecibido;


										if(trim($Data['bpr_currency']) != $MONEDALOCAL ){

													//SE BUSCA LA TASA DE CAMBIO CON QUE SE CREO EL DOCUEMENTO ORIGINAL
													// Y EN LA MISMA FECHA QUE TRAE EL DOCUMENTO
													$sqlBusTasaOriginal = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
													$resBusTasaOriginal = $this->pedeo->queryTable($sqlBusTasaOriginal, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $Data['bpr_currency'], ':tsa_date' => $fechaDocumento));

													if(isset($resBusTasaOriginal[0])){// si esta la tasa se almacena en la variable

															$TasaOld = $resBusTasaOriginal[0]['tsa_value'];

													}else{

																$this->pedeo->trans_rollback();

																$respuesta = array(
																	'error' => true,
																	'data'  => array(),
																	'mensaje' =>'No esta la tasa de cambio para la moneda: '.$Data['bpr_currency'].' en la actual fecha del documento: '.$Data['bpr_docdate'].' y la moneda local: '.$resMonedaLoc[0]['pgm_symbol']
																);

																$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

																return;

													}



													if($TasaDocLoc == $TasaOld){

															$DireferenciaCambio = 0;

													}else{
															// Se BUSCA LA CUENTA PARA APLICAR LA DIFERENCIA EN CAMBIO
															$DireferenciaCambio = 1;
															$sqlCuentaDiferenciaCambio = "SELECT pge_acc_dcp, pge_acc_dcn FROM pgem";
															$resCuentaDiferenciaCambio = $this->pedeo->queryTable($sqlCuentaDiferenciaCambio, array());

															if(isset($resCuentaDiferenciaCambio[0])){

																			$CuentaDiferenciaCambio = $resCuentaDiferenciaCambio[0];

															}else{

																	$this->pedeo->trans_rollback();

																	$respuesta = array(
																		'error' => true,
																		'data'  => array(),
																		'mensaje' =>'No se encontro la cuenta para aplicar la diferencia en cambio'
																	);

																	$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

																	return;
															}
													}


													if($DireferenciaCambio == 1){
															$VG = $TotalPagoRecibido;
															$TotalPagoRecibido = $TotalPagoRecibido * $TasaOld;

															$TotalDiferencia = $VG * $TasaDocLoc;
															$TotalDiferencia = $TotalDiferencia - $TotalPagoRecibido ;
													}

										}



										if($DireferenciaCambio == 0){//SOLO SI NO HAY DIFERENCIA EN CAMBIO

												if(trim($Data['bpr_currency']) != $MONEDALOCAL ){
														$TotalPagoRecibido = ($TotalPagoRecibido * $TasaDocLoc);
												}

										}

										switch ($cuenta) {
											case 1:
												$credito = $TotalPagoRecibido;

												if(trim($Data['bpr_currency']) != $MONEDASYS ){

														$MontoSysCR = ($credito / $TasaLocSys);

												}else{

														$MontoSysCR = $TotalPagoRecibidoOriginal;
												}

												break;

											case 2:
												$credito = $TotalPagoRecibido;
												if(trim($Data['bpr_currency']) != $MONEDASYS ){

														$MontoSysCR = ($credito / $TasaLocSys);

												}else{

														$MontoSysCR = $TotalPagoRecibidoOriginal;
												}
												break;

											case 3:
												$credito = $TotalPagoRecibido;
												if(trim($Data['bpr_currency']) != $MONEDASYS ){

														$MontoSysCR = ($credito / $TasaLocSys);

												}else{

														$MontoSysCR = $TotalPagoRecibidoOriginal;
												}
												break;

											case 4:
												$credito = $TotalPagoRecibido;
												if(trim($Data['bpr_currency']) != $MONEDASYS ){

														$MontoSysCR = ($credito / $TasaLocSys);

												}else{

														$MontoSysCR = $TotalPagoRecibidoOriginal;
												}
												break;

											case 5:
												$credito = $TotalPagoRecibido;
												if(trim($Data['bpr_currency']) != $MONEDASYS ){

														$MontoSysCR = ($credito / $TasaLocSys);

												}else{

														$MontoSysCR = $TotalPagoRecibidoOriginal;
												}
												break;

											case 6:
												$credito = $TotalPagoRecibido;
												if(trim($Data['bpr_currency']) != $MONEDASYS ){

														$MontoSysCR = ($credito / $TasaLocSys);

												}else{

														$MontoSysCR = $TotalPagoRecibidoOriginal;
												}
												break;

											case 7:
												$credito = $TotalPagoRecibido;
												if(trim($Data['bpr_currency']) != $MONEDASYS ){

														$MontoSysCR = ($credito / $TasaLocSys);

												}else{

														$MontoSysCR = $TotalPagoRecibidoOriginal;
												}
												break;
										}


										$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

												':ac1_trans_id' => $resInsertAsiento,
												':ac1_account' => $cuentaLinea,
												':ac1_debit' => round($debito, 2),
												':ac1_credit' => round($credito, 2),
												':ac1_debit_sys' => round($MontoSysDB,2),
												':ac1_credit_sys' => round($MontoSysCR,2),
												':ac1_currex' => 0,
												':ac1_doc_date' => $this->validateDate($Data['bpr_docdate'])?$Data['bpr_docdate']:NULL,
												':ac1_doc_duedate' => $this->validateDate($Data['bpr_docdate'])?$Data['bpr_docdate']:NULL,
												':ac1_debit_import' => 0,
												':ac1_credit_import' => 0,
												':ac1_debit_importsys' => 0,
												':ac1_credit_importsys' => 0,
												':ac1_font_key' => ($detail['pr1_docentry']== null)? 18 : $detail['pr1_docentry'],
												':ac1_font_line' => 1,
												':ac1_font_type' => ($detail['pr1_doctype'] == null) ? $Data['bpr_doctype'] : $detail['pr1_doctype'],
												':ac1_accountvs' => 1,
												':ac1_doctype' => 18,
												':ac1_ref1' => "",
												':ac1_ref2' => "",
												':ac1_ref3' => "",
												':ac1_prc_code' => 0,
												':ac1_uncode' => 0,
												':ac1_prj_code' => isset($Data['bpr_project'])?$Data['bpr_project']:NULL,
												':ac1_rescon_date' => NULL,
												':ac1_recon_total' => 0,
												':ac1_made_user' => isset($Data['bpr_createby'])?$Data['bpr_createby']:NULL,
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
												':ac1_legal_num' => isset($Data['bpr_cardcode'])?$Data['bpr_cardcode']:NULL,
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
													'mensaje'	=> 'No se pudo registrar el pago recibido, occurio un error al insertar el detalle del asiento cuenta tercero'
												);

												 $this->response($respuesta);

												 return;
									}



									if($DireferenciaCambio == 1){

													$cuentaD = "";
													$credito = 0;
													$debito  = 0;
													$MontoSysDB = 0;
													$MontoSysCR = 0;

													if($TotalDiferencia > 0){

																$cuentaD    = $CuentaDiferenciaCambio['pge_acc_dcp'];
																$credito    = $TotalDiferencia;
																// $MontoSysCR = $TotalDiferencia / $TasaLocSys;

													}else{

																$cuentaD    = $CuentaDiferenciaCambio['pge_acc_dcn'];
																$debito     =  abs($TotalDiferencia);
																// $MontoSysDB =  abs($TotalDiferencia) / $TasaLocSys;
													}

													$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

															':ac1_trans_id' => $resInsertAsiento,
															':ac1_account' => $cuentaD,
															':ac1_debit' => $debito,
															':ac1_credit' => $credito,
															':ac1_debit_sys' => 0,
															':ac1_credit_sys' => 0,
															':ac1_currex' => 0,
															':ac1_doc_date' => $this->validateDate($Data['bpr_docdate'])?$Data['bpr_docdate']:NULL,
															':ac1_doc_duedate' => $this->validateDate($Data['bpr_docdate'])?$Data['bpr_docdate']:NULL,
															':ac1_debit_import' => 0,
															':ac1_credit_import' => 0,
															':ac1_debit_importsys' => 0,
															':ac1_credit_importsys' => 0,
															':ac1_font_key' => ($detail['pr1_docentry']== null)? 18 : $detail['pr1_docentry'],
															':ac1_font_line' => 1,
															':ac1_font_type' => ($detail['pr1_doctype']== null)? $Data['bpr_doctype'] : $detail['bpr_doctype'],
															':ac1_accountvs' => 1,
															':ac1_doctype' => 18,
															':ac1_ref1' => "",
															':ac1_ref2' => "",
															':ac1_ref3' => "",
															':ac1_prc_code' => 0,
															':ac1_uncode' => 0,
															':ac1_prj_code' => isset($Data['bpr_project'])?$Data['bpr_project']:NULL,
															':ac1_rescon_date' => NULL,
															':ac1_recon_total' => 0,
															':ac1_made_user' => isset($Data['bpr_createby'])?$Data['bpr_createby']:NULL,
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
															':ac1_legal_num' => isset($Data['bpr_cardcode'])?$Data['bpr_cardcode']:NULL,
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
																'mensaje'	=> 'No se pudo registrar el pago recibido, occurio un error al insertar el detalle del asiento diferencia en cambio'
															);

															 $this->response($respuesta);

															 return;
												}


									}


						}
						//FIN Procedimiento PARA LLENAR ASIENTO CON CUENTA TERCERO SEGUN GRUPO DE CUENTAS




					}else{ // EN CASO CONTRARIO ES UN ANTICIPO A CLIENTE

					//SE AGREGA ASIENTO A CUENTA DE LA LINEA DE ANTICIPO

												foreach ($DetalleConsolidadoAsientoCuentaTercero as $key => $posicion) {
																$TotalPagoRecibido = 0;
																$TotalPagoRecibidoOriginal = 0;
																$TotalDiferencia = 0;
																$cuenta = 0;
																$cuentaLinea = 0;
																$docentry = 0;
																$doctype = 0;
																$fechaDocumento = '';
																$TasaOld = 0;
																$DireferenciaCambio = 0; // SOLO SE USA PARA SABER SI EXISTE UNA DIFERENCIA DE CAMBIO
																$CuentaDiferenciaCambio = 0;

																foreach ($posicion as $key => $value) {

																			$TotalPagoRecibido = ( $TotalPagoRecibido + $value->pr1_vlrpaid );


																			$docentry = $value->pr1_docentry;
																			$doctype  = $value->pr1_doctype;
																			$cuenta   = $value->cuentaNaturaleza;
																			$fechaDocumento =$value->pr1_docdate;
																			$cuentaLinea = $value->cuentatercero;

																}


																$debito = 0;
																$credito = 0;
																$MontoSysDB = 0;
																$MontoSysCR = 0;
																$TotalPagoRecibidoOriginal = $TotalPagoRecibido;

																switch ($cuenta) {
																	case 1:
																		$credito = $TotalPagoRecibido;

																		if(trim($Data['bpr_currency']) != $MONEDASYS ){

																				$MontoSysCR = ($credito / $TasaLocSys);

																		}else{

																				$MontoSysCR = $TotalPagoRecibidoOriginal;
																		}

																		break;

																	case 2:
																		$credito = $TotalPagoRecibido;
																		if(trim($Data['bpr_currency']) != $MONEDASYS ){

																				$MontoSysCR = ($credito / $TasaLocSys);

																		}else{

																				$MontoSysCR = $TotalPagoRecibidoOriginal;
																		}
																		break;

																	case 3:
																		$credito = $TotalPagoRecibido;
																		if(trim($Data['bpr_currency']) != $MONEDASYS ){

																				$MontoSysCR = ($credito / $TasaLocSys);

																		}else{

																				$MontoSysCR = $TotalPagoRecibidoOriginal;
																		}
																		break;

																	case 4:
																		$credito = $TotalPagoRecibido;
																		if(trim($Data['bpr_currency']) != $MONEDASYS ){

																				$MontoSysCR = ($credito / $TasaLocSys);

																		}else{

																				$MontoSysCR = $TotalPagoRecibidoOriginal;
																		}
																		break;

																	case 5:
																		$credito = $TotalPagoRecibido;
																		if(trim($Data['bpr_currency']) != $MONEDASYS ){

																				$MontoSysCR = ($credito / $TasaLocSys);

																		}else{

																				$MontoSysCR = $TotalPagoRecibidoOriginal;
																		}
																		break;

																	case 6:
																		$credito = $TotalPagoRecibido;
																		if(trim($Data['bpr_currency']) != $MONEDASYS ){

																				$MontoSysCR = ($credito / $TasaLocSys);

																		}else{

																				$MontoSysCR = $TotalPagoRecibidoOriginal;
																		}
																		break;

																	case 7:
																		$credito = $TotalPagoRecibido;
																		if(trim($Data['bpr_currency']) != $MONEDASYS ){

																				$MontoSysCR = ($credito / $TasaLocSys);

																		}else{

																				$MontoSysCR = $TotalPagoRecibidoOriginal;
																		}
																		break;
																}

																$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

																		':ac1_trans_id' => $resInsertAsiento,
																		':ac1_account' => $cuentaLinea,
																		':ac1_debit' => round($debito, 2),
																		':ac1_credit' => round($credito, 2),
																		':ac1_debit_sys' => round($MontoSysDB,2),
																		':ac1_credit_sys' => round($MontoSysCR,2),
																		':ac1_currex' => 0,
																		':ac1_doc_date' => $this->validateDate($Data['bpr_docdate'])?$Data['bpr_docdate']:NULL,
																		':ac1_doc_duedate' => $this->validateDate($Data['bpr_docdate'])?$Data['bpr_docdate']:NULL,
																		':ac1_debit_import' => 0,
																		':ac1_credit_import' => 0,
																		':ac1_debit_importsys' => 0,
																		':ac1_credit_importsys' => 0,
																		':ac1_font_key' => ($detail['pr1_docentry'] == null) ? 18 : $detail['pr1_docentry'],
																		':ac1_font_line' => 1,
																		':ac1_font_type' => ($detail['pr1_doctype'] == null) ? $Data['bpr_doctype'] : $detail['pr1_doctype'],
																		':ac1_accountvs' => 1,
																		':ac1_doctype' => 18,
																		':ac1_ref1' => "",
																		':ac1_ref2' => "",
																		':ac1_ref3' => "",
																		':ac1_prc_code' => 0,
																		':ac1_uncode' => 0,
																		':ac1_prj_code' => isset($Data['bpr_project'])?$Data['bpr_project']:NULL,
																		':ac1_rescon_date' => NULL,
																		':ac1_recon_total' => 0,
																		':ac1_made_user' => isset($Data['bpr_createby'])?$Data['bpr_createby']:NULL,
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
																		':ac1_legal_num' => isset($Data['bpr_cardcode'])?$Data['bpr_cardcode']:NULL,
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
																			'mensaje'	=> 'No se pudo registrar el pago recibido, occurio un error al insertar el detalle del asiento cuenta tercero'
																		);

																		 $this->response($respuesta);

																		 return;
															}
														}
																//FIN DEL PROCESO PARA AGREGAR ASIENTO A CUENTA DE LA LINEA DE ANTICIPO
					}
					// FIN PROCESO ANTERIOR



					if($detail['pr1_doctype'] == 5) {


									$sqlEstado = 'SELECT case when (dvf_doctotal - COALESCE(dvf_paytoday,0)) = 0 then 1 else 0 end estado
																from dvfv
																where dvf_docentry = :dvf_docentry';


									$resEstado = $this->pedeo->queryTable($sqlEstado, array(':dvf_docentry' => $detail['pr1_docentry']));

									if(isset($resEstado[0]) && $resEstado[0]['estado'] == 1){
												$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																						VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

												$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


																	':bed_docentry' => $detail['pr1_docentry'],
																	':bed_doctype' => $detail['pr1_doctype'],
																	':bed_status' => 3, //ESTADO CERRADO
																	':bed_createby' => $Data['bpr_createby'],
																	':bed_date' => date('Y-m-d'),
																	':bed_baseentry' => $resInsert,
																	':bed_basetype' => $Data['bpr_doctype']
												));


												if(is_numeric($resInsertEstado) && $resInsertEstado > 0){

												}else{

														 $this->pedeo->trans_rollback();

															$respuesta = array(
																'error'   => true,
																'data' => $resInsertEstado,
																'mensaje'	=> 'No se pudo registrar el pago'
															);


															$this->response($respuesta);

															return;
												}

									}

					 }

					// Si todo sale bien despues de insertar el detalle de la cotizacion
					// se confirma la trasaccion  para que los cambios apliquen permanentemente
					// en la base de datos y se confirma la operacion exitosa.
					$this->pedeo->trans_commit();

          $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' =>'Pago registrado con exito'
          );


        }else{
					// Se devuelven los cambios realizados en la transaccion
					// si occurre un error  y se muestra devuelve el error.
							$this->pedeo->trans_rollback();

              $respuesta = array(
                'error'   => true,
                'data' => $resInsert,
                'mensaje'	=> 'No se pudo registrar el pago'
              );

        }

         $this->response($respuesta);
	}




  //OBTENER PAGOS
  public function getPaymentsReceived1_get(){

        $sqlSelect = " SELECT * FROM gbpr";

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


	//OBTENER COTIZACION POR ID
	public function getPymentsById_get(){

				$Data = $this->get();

				if(!isset($Data['bpr_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM gbpr WHERE bpr_docentry =:bpr_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":bpr_docentry" => $Data['bpr_docentry']));

				if(isset($resSelect[0])){

					$respuesta = array(
						'error' => false,
						'data'  => $resSelect,
						'mensaje' => '');

				}else{

						$respuesta = array(
							'error'   => true,
							'data' 		=> array(),
							'mensaje'	=> 'busqueda sin resultados'
						);

				}

				 $this->response($respuesta);
	}


	//OBTENER COTIZACION DETALLE POR ID
	public function getQuotationDetail_get(){

				$Data = $this->get();

				if(!isset($Data['pr1_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM bpr1 WHERE pr1_docentry =:pr1_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":pr1_docentry" => $Data['pr1_docentry']));

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



	//OBTENER COTIZACIONES POR SOCIO DE NEGOCIO
	public function getPymentsBySN_get(){

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

				$sqlSelect = " SELECT * FROM gbpr WHERE bpr_cardcode =:bpr_cardcode";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":bpr_cardcode" => $Data['dms_card_code']));

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


public function getDetails_get(){
	$Data = $this->get();

	if(!isset($Data['pr1_docentry'])){

		$respuesta = array(
			'error' => true,
			'data'  => array(),
			'mensaje' =>'La informacion enviada no es valida'
		);

		$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

		return;
	}

	$sqlSelect = " SELECT * FROM bpr1 WHERE pr1_docentry =:pr1_docentry";

	$resSelect = $this->pedeo->queryTable($sqlSelect, array(":pr1_docentry" => $Data['pr1_docentry']));

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
