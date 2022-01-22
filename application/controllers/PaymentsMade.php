<?php
// PAGOS REALIZADOS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PaymentsMade extends REST_Controller {

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

	//OBTENER PAGOS REALIZADOS
  public function getPaymentsMade_get(){

    $sqlSelect = "SELECT * FROM gbpe";

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
	public function createPaymentsMade_post(){

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

			// SE VERIFICA QUE SE PUEDA PAGAR EL
			$sqlPe = " SELECT distinct sum(bpe_vlrpaid) from gbpe
								join bpe1 b on bpe_docentry = pe1_docnum
								where pe1_docentry = :pe1_docentry";
								$det = json_decode($Data['detail'],true);

			$resPe= $this->pedeo->queryTable($sqlPe, array(':pe1_docentry' => $det[0]['pe1_docentry']));

				if(isset($resPe[0])){
					$suma = $resPe[0];
					if( $suma['sum']>$det[0]['pe1_vlrtotal']){
						$respuesta = array(
		          'error' => true,
		          'data'  => array(),
		          'mensaje' =>'El valor del pago excede el valor de la factura'
		        );

		        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

		        return;
					}
				}

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

				$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['bpe_series']));

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
				$resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $Data['bpe_currency'], ':tsa_date' => $Data['bpe_docdate']));

				if(isset($resBusTasa[0])){

				}else{

						if(trim($Data['bpe_currency']) != $MONEDALOCAL ){

							$respuesta = array(
								'error' => true,
								'data'  => array(),
								'mensaje' =>'No se encrontro la tasa de cambio para la moneda: '.$Data['bpe_currency'].' en la actual fecha del documento: '.$Data['bpe_docdate'].' y la moneda local: '.$resMonedaLoc[0]['pgm_symbol']
							);

							$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

							return;
						}
				}

				$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
				$resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $Data['bpe_docdate']));

				if(isset($resBusTasa2[0])){

				}else{
						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :'.$Data['bpe_docdate']
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
														 AND f1.dms_card_type = '2'"; // 2 para proveedor

				$resCuentaTercero = $this->pedeo->queryTable($sqlCuentaTercero, array(":dms_card_code" => $Data['bpe_cardcode']));

				if(isset($resCuentaTercero[0])){

						$cuentaTercero = $resCuentaTercero[0]['mgs_acct'];

				}else{

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' => $resInsertDetail,
							'mensaje'	=> 'No se pudo registrar el pago, el tercero no tiene la cuenta asociada ('.$Data['bpe_cardcode'].')'
						);

						 $this->response($respuesta);

						 return;
				}
				//FIN BUSQUEDA CUENTA TERCERO

        $sqlInsert = "INSERT INTO
                            	gbpe (bpe_cardcode,bpe_doctype,bpe_cardname,bpe_address,bpe_perscontact,bpe_series,bpe_docnum,bpe_docdate,bpe_taxdate,bpe_ref,bpe_transid,
                                    bpe_comments,bpe_memo,bpe_acctransfer,bpe_datetransfer,bpe_reftransfer,bpe_doctotal,bpe_vlrpaid,bpe_project,bpe_createby,
                                    bpe_createat,bpe_payment,bpe_currency)
                      VALUES (:bpe_cardcode,:bpe_doctype,:bpe_cardname,:bpe_address,:bpe_perscontact,:bpe_series,:bpe_docnum,:bpe_docdate,:bpe_taxdate,:bpe_ref,:bpe_transid,
                              :bpe_comments,:bpe_memo,:bpe_acctransfer,:bpe_datetransfer,:bpe_reftransfer,:bpe_doctotal,:bpe_vlrpaid,:bpe_project,:bpe_createby,
                              :bpe_createat,:bpe_payment,:bpe_currency)";


				// Se Inicia la transaccion,
				// Todas las consultas de modificacion siguientes
				// aplicaran solo despues que se confirme la transaccion,
				// de lo contrario no se aplicaran los cambios y se devolvera
				// la base de datos a su estado original.

			  $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':bpe_cardcode' => isset($Data['bpe_cardcode'])?$Data['bpe_cardcode']:NULL,
							':bpe_doctype' => is_numeric($Data['bpe_doctype'])?$Data['bpe_doctype']:0,
              ':bpe_cardname' => isset($Data['bpe_cardname'])?$Data['bpe_cardname']:NULL,
              ':bpe_address' => isset($Data['bpe_address'])?$Data['bpe_address']:NULL,
              ':bpe_perscontact' => is_numeric($Data['bpe_perscontact'])?$Data['bpe_perscontact']:0,
              ':bpe_series' => is_numeric($Data['bpe_series'])?$Data['bpe_series']:0,
              ':bpe_docnum' => $DocNumVerificado,
              ':bpe_docdate' => $this->validateDate($Data['bpe_docdate'])?$Data['bpe_docdate']:NULL,
              ':bpe_taxdate' => $this->validateDate($Data['bpe_taxdate'])?$Data['bpe_taxdate']:NULL,
              ':bpe_ref' => isset($Data['bpe_ref'])?$Data['bpe_ref']:NULL,
              ':bpe_transid' => is_numeric($Data['bpe_transid'])?$Data['bpe_transid']:0,
              ':bpe_comments' => isset($Data['bpe_comments'])?$Data['bpe_comments']:NULL,
              ':bpe_memo' => isset($Data['bpe_memo'])?$Data['bpe_memo']:NULL,
              ':bpe_acctransfer' => isset($Data['bpe_acctransfer'])?$Data['bpe_acctransfer']:NULL,
              ':bpe_datetransfer' => $this->validateDate($Data['bpe_datetransfer'])?$Data['bpe_datetransfer']:NULL,
              ':bpe_reftransfer' => isset($Data['bpe_reftransfer'])?$Data['bpe_reftransfer']:NULL,
              ':bpe_doctotal' => is_numeric($Data['bpe_doctotal'])?$Data['bpe_doctotal']:0,
              ':bpe_vlrpaid' => is_numeric($Data['bpe_vlrpaid'])?$Data['bpe_vlrpaid']:0,
              ':bpe_project' => isset($Data['bpe_project'])?$Data['bpe_project']:NULL,
              ':bpe_createby' => isset($Data['bpe_createby'])?$Data['bpe_createby']:NULL,
              ':bpe_createat' => $this->validateDate($Data['bpe_createat'])?$Data['bpe_createat']:NULL,
              ':bpe_payment' => isset($Data['bpe_payment'])?$Data['bpe_payment']:NULL),
							':bpe_currency'=> isset($Data['bpe_currency'])?$Data['bpe_currency']:NULL)
						);

        if(is_numeric($resInsert) && $resInsert > 0){

				// Se actualiza la serie de la numeracion del documento

					$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
					$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
							':pgs_nextnum' => $DocNumVerificado,
							':pgs_id'      => $Data['bpe_series']
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

					// FIN PROCESO PARA ACTUALIZAR NUMERACION

					//Se agregan los asientos contables*/*******

					$sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user)
															 VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user)";


					$resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

							':mac_doc_num' => 1,
							':mac_status' => 1,
							':mac_base_type' => is_numeric($Data['bpe_doctype'])?$Data['bpe_doctype']:0,
							':mac_base_entry' => $resInsert,
							':mac_doc_date' => $this->validateDate($Data['bpe_docdate'])?$Data['bpe_docdate']:NULL,
							':mac_doc_duedate' => $this->validateDate($Data['bpe_docdate'])?$Data['bpe_docdate']:NULL,
							':mac_legal_date' => $this->validateDate($Data['bpe_docdate'])?$Data['bpe_docdate']:NULL,
							':mac_ref1' => is_numeric($Data['bpe_doctype'])?$Data['bpe_doctype']:0,
							':mac_ref2' => "",
							':mac_ref3' => "",
							':mac_loc_total' => is_numeric($Data['bpe_doctotal'])?$Data['bpe_doctotal']:0,
							':mac_fc_total' => is_numeric($Data['bpe_doctotal'])?$Data['bpe_doctotal']:0,
							':mac_sys_total' => is_numeric($Data['bpe_doctotal'])?$Data['bpe_doctotal']:0,
							':mac_trans_dode' => 1,
							':mac_beline_nume' => 1,
							':mac_vat_date' => $this->validateDate($Data['bpe_docdate'])?$Data['bpe_docdate']:NULL,
							':mac_serie' => 1,
							':mac_number' => 1,
							':mac_bammntsys' => 0,
							':mac_bammnt' => 0,
							':mac_wtsum' => 1,
							':mac_vatsum' => 0,
							':mac_comments' => isset($Data['bpe_comments'])?$Data['bpe_comments']:NULL,
							':mac_create_date' => $this->validateDate($Data['bpe_createat'])?$Data['bpe_createat']:NULL,
							':mac_made_usuer' => isset($Data['bpe_createby'])?$Data['bpe_createby']:NULL,
							':mac_update_date' => date("Y-m-d"),
							':mac_update_user' => isset($Data['bpe_createby'])?$Data['bpe_createby']:NULL
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
									'mensaje'	=> 'No se pudo registrar la el pago de ventas'
								);

								 $this->response($respuesta);

								 return;
					}

					// FINALIZA EL PROCESO PARA INSERTAR LA CABECERA DEL ASIENTO

					// INICIA INSERCION DEL DETALLE

          foreach ($ContenidoDetalle as $key => $detail) {

								//VALIDAR EL VALOR QUE SE ESTA PAGANDO NO SEA MAYOR AL SALDO DE LA FACTURA
								$VlrPayFact = "SELECT COALESCE(cfc_paytoday,0) as cfc_paytoday,cfc_doctotal from dcfc WHERE cfc_docentry = :cfc_docentry and cfc_doctype = :cfc_doctype";
								$resVlrPayFact = $this->pedeo->queryTable($VlrPayFact, array(
									':cfc_docentry' => $detail['pe1_docentry'],
									':cfc_doctype' => $detail['pe1_doctype']
								));

									$VlrPaidActual = $detail['pe1_vlrpaid'];
									$VlrPaidFact = $resVlrPayFact[0]['cfc_paytoday'];

									$SumVlr =  $VlrPaidActual + $VlrPaidFact ;
								if(isset($resVlrPayFact[0])){

									if($SumVlr <= $resVlrPayFact[0]['cfc_doctotal'] ){


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

								}
								else{
									$this->pedeo->trans_rollback();
									$respuesta = array(
										'error'   => true,
										'data' => $resVlrPayFact,
										'mensaje'	=> 'No tiene valor para realizar la operacion');

										$this->response($respuesta);

 									 return;

								}

                $sqlInsertDetail = "INSERT INTO
                                        	bpe1 (pe1_docnum,pe1_docentry,pe1_numref,pe1_docdate,pe1_vlrtotal,pe1_vlrpaid,pe1_comments,pe1_porcdiscount,pe1_doctype,
                                            pe1_docduedate,pe1_daysbackw,pe1_vlrdiscount,pe1_ocrcode)
                                    VALUES (:pe1_docnum,:pe1_docentry,:pe1_numref,:pe1_docdate,:pe1_vlrtotal,:pe1_vlrpaid,:pe1_comments,:pe1_porcdiscount,
                                            :pe1_doctype,:pe1_docduedate,:pe1_daysbackw,:pe1_vlrdiscount,:pe1_ocrcode)";



                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':pe1_docnum' => $resInsert,
                        ':pe1_docentry' => is_numeric($detail['pe1_docentry'])?$detail['pe1_docentry']:0,
                        ':pe1_numref' => isset($detail['pe1_numref'])?$detail['pe1_numref']:NULL,
                        ':pe1_docdate' =>  $this->validateDate($detail['pe1_docdate'])?$detail['pe1_docdate']:NULL,
                        ':pe1_vlrtotal' => is_numeric($detail['pe1_vlrtotal'])?$detail['pe1_vlrtotal']:0,
                        ':pe1_vlrpaid' => is_numeric($detail['pe1_vlrpaid'])?$detail['pe1_vlrpaid']:0,
                        ':pe1_comments' => isset($detail['pe1_comments'])?$detail['pe1_comments']:NULL,
                        ':pe1_porcdiscount' => is_numeric($detail['pe1_porcdiscount'])?$detail['pe1_porcdiscount']:0,
                        ':pe1_doctype' => is_numeric($detail['pe1_doctype'])?$detail['pe1_doctype']:0,
                        ':pe1_docduedate' => $this->validateDate($detail['pe1_docduedate'])?$detail['pe1_docduedate']:NULL,
                        ':pe1_daysbackw' => is_numeric($detail['pe1_daysbackw'])?$detail['pe1_daysbackw']:0,
                        ':pe1_vlrdiscount' => is_numeric($detail['pe1_vlrdiscount'])?$detail['pe1_vlrdiscount']:0,
                        ':pe1_ocrcode' => isset($detail['pe1_ocrcode'])?$detail['pe1_ocrcode']:NULL
                        // ':pe1_ocrcode1' => isset($detail['pe1_ocrcode1'])?$detail['pe1_ocrcode1']:NULL
                ));


								if(is_numeric($resInsertDetail) && $resInsertDetail > 0){

										//ACTUALIZAR VALOR PAGADO DE LA FACTURA DE COMPRA

									$sqlUpdateFactPay = "UPDATE  dcfc  SET cfc_paytoday = COALESCE(cfc_paytoday,0)+:cfc_paytoday WHERE cfc_docentry = :cfc_docentry and cfc_doctype = :cfc_doctype";

									$resUpdateFactPay = $this->pedeo->updateRow($sqlUpdateFactPay,array(

										':cfc_paytoday' => $detail['pe1_vlrpaid'],
										':cfc_docentry' => $detail['pe1_docentry'],
										':cfc_doctype' => $detail['pe1_doctype']


									));

									if(is_numeric($resUpdateFactPay) && $resUpdateFactPay > 0){

								}	else{
										$this->pedeo->trans_rollback();

										$respuesta = array(
											'error'   => true,
											'data' => $resUpdateFactPay,
											'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura '.$detail['pe1_docentry']
										);

										 $this->response($respuesta);

										 return;
									}


								}else{

										// si falla algun insert del detalle del pago se devuelven los cambios realizados por la transaccion,
										// se retorna el error y se detiene la ejecucion del codigo restante.
											$this->pedeo->trans_rollback();

											$respuesta = array(
												'error'   => true,
												'data' => $resInsertDetail,
												'mensaje'	=> 'No se pudo registrar el pago'
											);

											 $this->response($respuesta);

											 return;
								}


								// LLENANDO DETALLE ASIENTOS CONTABLES (AGRUPACION)



								$DetalleAsientoCuentaTercero = new stdClass();

								$DetalleAsientoCuentaTercero->bpe_cardcode  = isset($Data['bpe_cardcode'])?$Data['bpe_cardcode']:NULL;
								$DetalleAsientoCuentaTercero->pe1_doctype   = is_numeric($detail['pe1_doctype'])?$detail['pe1_doctype']:0;
								$DetalleAsientoCuentaTercero->pe1_docentry  = is_numeric($detail['pe1_docentry'])?$detail['pe1_docentry']:0;
								$DetalleAsientoCuentaTercero->cuentatercero = $cuentaTercero;
								$DetalleAsientoCuentaTercero->cuentaNaturaleza = substr($cuentaTercero, 0, 1);
								$DetalleAsientoCuentaTercero->pe1_vlrpaid = is_numeric($detail['pe1_vlrpaid'])?$detail['pe1_vlrpaid']:0;
								$DetalleAsientoCuentaTercero->pe1_docdate	= $this->validateDate($detail['pe1_docdate'])?$detail['pe1_docdate']:NULL;


								$llaveAsientoCuentaTercero = $DetalleAsientoCuentaTercero->bpe_cardcode.$DetalleAsientoCuentaTercero->pe1_docentry.$DetalleAsientoCuentaTercero->pe1_doctype;


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
					//FIN DE ACTUALIZACION DEL VALOR PAGADO EN LA FACTURA


					// SE INSERTA ASIENTO INGRESO

							$debito = 0;
							$credito = 0;
							$MontoSysDB = 0;
							$MontoSysCR = 0;
							$cuenta = $Data['bpe_acctransfer'];
							$codigoCuentaIngreso = substr($cuenta , 0, 1);
							$granTotalIngreso = $Data['bpe_vlrpaid'];
							$granTotalIngresoOriginal = $granTotalIngreso;

							if(trim($Data['bpe_currency']) != $MONEDALOCAL ){
									$granTotalIngreso = ($granTotalIngreso * $TasaDocLoc);
							}
							switch ($codigoCuentaIngreso) {
								case 1: // ESTABLECIDO COMO CREDITO
									$credito = $granTotalIngreso;
									if(trim($Data['bpe_currency']) != $MONEDASYS ){

											$MontoSysCR = ($credito / $TasaLocSys);

									}else{

											$MontoSysCR = $granTotalIngresoOriginal;
									}
									break;

								case 2:
									$credito = $granTotalIngreso;
									if(trim($Data['bpe_currency']) != $MONEDASYS ){

											$MontoSysCR = ($credito / $TasaLocSys);

									}else{

											$MontoSysCR = $granTotalIngresoOriginal;
									}
									break;

								case 3:
									$credito = $granTotalIngreso;
									if(trim($Data['bpe_currency']) != $MONEDASYS ){

											$MontoSysCR = ($credito / $TasaLocSys);

									}else{

											$MontoSysCR = $granTotalIngresoOriginal;
									}
									break;

								case 4:
									$credito = $granTotalIngreso;
									if(trim($Data['bpe_currency']) != $MONEDASYS ){

											$MontoSysCR = ($credito / $TasaLocSys);

									}else{

											$MontoSysCR = $granTotalIngresoOriginal;
									}
									break;

								case 5:
									$debito = $granTotalIngreso;
									if(trim($Data['bpe_currency']) != $MONEDASYS ){

											$MontoSysDB = ($debito / $TasaLocSys);

									}else{

											$MontoSysDB = $granTotalIngresoOriginal;
									}
									break;

								case 6:
									$debito = $granTotalIngreso;
									if(trim($Data['bpe_currency']) != $MONEDASYS ){

											$MontoSysDB = ($debito / $TasaLocSys);

									}else{

											$MontoSysDB = $granTotalIngresoOriginal;
									}
									break;

								case 7:
									$debito = $granTotalIngreso;
									if(trim($Data['bpe_currency']) != $MONEDASYS ){

											$MontoSysDB = ($debito / $TasaLocSys);

									}else{

											$MontoSysDB = $granTotalIngresoOriginal;
									}
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
									':ac1_doc_date' => $this->validateDate($Data['bpe_docdate'])?$Data['bpe_docdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['bpe_docdate'])?$Data['bpe_docdate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => $detail['pe1_docentry'],
									':ac1_font_line' => 1,
									':ac1_font_type' => is_numeric($detail['pe1_doctype'])?$detail['pe1_doctype']:0,
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
									':ac1_made_user' => isset($Data['bpe_createby'])?$Data['bpe_createby']:NULL,
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
									':ac1_legal_num' => isset($Data['bpe_cardcode'])?$Data['bpe_cardcode']:NULL,
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


					//Procedimiento para llenar ASIENTO CON CUENTA TERCERO SEGUN GRUPO DE CUENTAS

					foreach ($DetalleConsolidadoAsientoCuentaTercero as $key => $posicion) {
									$TotalPagoRecibido = 0;
									$TotalPagoRecibidoOriginal = 0;
									$TotalDiferencia = 0;
									$cuenta = 0;
									$docentry = 0;
									$doctype = 0;
									$fechaDocumento = '';
									$TasaOld = 0;
									$DireferenciaCambio = 0; // SOLO SE USA PARA SABER SI EXISTE UNA DIFERENCIA DE CAMBIO
									$CuentaDiferenciaCambio = 0;

									foreach ($posicion as $key => $value) {

												$TotalPagoRecibido = ( $TotalPagoRecibido + $value->pe1_vlrpaid );


												$docentry = $value->pe1_docentry;
												$doctype  = $value->pe1_doctype;
												$cuenta   = $value->cuentaNaturaleza;
												$fechaDocumento =$value->pe1_docdate;

									}

									$debito = 0;
									$credito = 0;
									$MontoSysDB = 0;
									$MontoSysCR = 0;
									$TotalPagoRecibidoOriginal = $TotalPagoRecibido;


									if(trim($Data['bpe_currency']) != $MONEDALOCAL ){

												//SE BUSCA LA TASA DE CAMBIO CON QUE SE CREO EL DOCUEMENTO ORIGINAL
												// Y EN LA MISMA FECHA QUE TRAE EL DOCUMENTO
												$sqlBusTasaOriginal = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
												$resBusTasaOriginal = $this->pedeo->queryTable($sqlBusTasaOriginal, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $Data['bpe_currency'], ':tsa_date' => $fechaDocumento));

												if(isset($resBusTasaOriginal[0])){// si esta la tasa se almacena en la variable

														$TasaOld = $resBusTasaOriginal[0]['tsa_value'];

												}else{

															$this->pedeo->trans_rollback();

															$respuesta = array(
																'error' => true,
																'data'  => array(),
																'mensaje' =>'No esta la tasa de cambio para la moneda: '.$Data['bpe_currency'].' en la actual fecha del documento: '.$Data['bpe_docdate'].' y la moneda local: '.$resMonedaLoc[0]['pgm_symbol']
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

											if(trim($Data['bpe_currency']) != $MONEDALOCAL ){
													$TotalPagoRecibido = ($TotalPagoRecibido * $TasaDocLoc);
											}

									}

									switch ($cuenta) {
										case 1:
											$debito = $TotalPagoRecibido;

											if(trim($Data['bpe_currency']) != $MONEDASYS ){

													$MontoSysDB = ($debito / $TasaLocSys);

											}else{

													$MontoSysDB = $TotalPagoRecibidoOriginal;
											}

											break;

										case 2:
											$debito = $TotalPagoRecibido;
											if(trim($Data['bpe_currency']) != $MONEDASYS ){

													$MontoSysDB = ($debito / $TasaLocSys);

											}else{

													$MontoSysDB = $TotalPagoRecibidoOriginal;
											}
											break;

										case 3:
											$credito = $TotalPagoRecibido;
											if(trim($Data['bpe_currency']) != $MONEDASYS ){

													$MontoSysCR = ($credito / $TasaLocSys);

											}else{

													$MontoSysCR = $TotalPagoRecibidoOriginal;
											}
											break;

										case 4:
											$credito = $TotalPagoRecibido;
											if(trim($Data['bpe_currency']) != $MONEDASYS ){

													$MontoSysCR = ($credito / $TasaLocSys);

											}else{

													$MontoSysCR = $TotalPagoRecibidoOriginal;
											}
											break;

										case 5:
											$debito = $TotalPagoRecibido;
											if(trim($Data['bpe_currency']) != $MONEDASYS ){

													$MontoSysDB = ($debito / $TasaLocSys);

											}else{

													$MontoSysDB = $TotalPagoRecibidoOriginal;
											}
											break;

										case 6:
											$debito = $TotalPagoRecibido;
											if(trim($Data['bpe_currency']) != $MONEDASYS ){

													$MontoSysDB = ($debito / $TasaLocSys);

											}else{

													$MontoSysDB = $TotalPagoRecibidoOriginal;
											}
											break;

										case 7:
											$debito = $TotalPagoRecibido;
											if(trim($Data['bpe_currency']) != $MONEDASYS ){

													$MontoSysDB = ($debito / $TasaLocSys);

											}else{

													$MontoSysDB = $TotalPagoRecibidoOriginal;
											}
											break;
									}

									// ACTUALIZAR EL ASIENTO DE LA FACTURA QUE SE ESTA AFECTANDO CON EL PAGO RECIBIDO
									// $sqlUpdateDoc = "UPDATE mac1
									// 								 SET ac1_credit_import = ac1_credit_import + :ac1_credit_import
									// 								 ,ac1_debit_import = ac1_debit_import + :ac1_debit_import
									// 								 ,ac1_credit_importsys = ac1_credit_importsys + :ac1_credit_importsys
									// 								 ,ac1_debit_importsys = ac1_debit_importsys + :ac1_debit_importsys
									// 								 WHERE ac1_legal_num = :ac1_legal_num
									// 								 AND ac1_font_type = :ac1_font_type
									// 								 AND ac1_font_key =  :ac1_font_key
									// 								 AND ac1_account = :ac1_account";
									//
									// $resUpdateDoc = $this->pedeo->updateRow($sqlUpdateDoc, array(
									// 						':ac1_legal_num' => $Data['bpe_cardcode'],
									// 						':ac1_font_type' => $doctype,
									// 						':ac1_font_key'  => $docentry,
									// 						':ac1_account'   => $cuentaTercero,
									// 						':ac1_credit_import' => $credito,
									// 						':ac1_debit_import' => $debito,
									// 						':ac1_credit_importsys' => $MontoSysCR,
									// 						':ac1_debit_importsys' => $MontoSysDB
									// ));
									//
									//
									// if(is_numeric($resUpdateDoc) && $resUpdateDoc == 1){
									//
									// }else{
									//
									// 			$this->pedeo->trans_rollback();
									//
									// 			$respuesta = array(
									// 				'error'   => true,
									// 				'data'    => $resUpdateDoc,
									// 				'mensaje'	=> 'No se pudo actualizar el asiento del documento: '.$docentry
									// 			);
									//
									// 			$this->response($respuesta);
									//
									// 			return;
									//
									// }

									// FIN PROCEDIMIENTO ACTUALIZAR EL ASIENTO DE LA FACTURA QUE SE ESTA AFECTANDO CON EL PAGO RECIBIDO

									$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

											':ac1_trans_id' => $resInsertAsiento,
											':ac1_account' => $cuentaTercero,
											':ac1_debit' => $debito,
											':ac1_credit' => $credito,
											':ac1_debit_sys' => round($MontoSysDB,2),
											':ac1_credit_sys' => round($MontoSysCR,2),
											':ac1_currex' => 0,
											':ac1_doc_date' => $this->validateDate($Data['bpe_docdate'])?$Data['bpe_docdate']:NULL,
											':ac1_doc_duedate' => $this->validateDate($Data['bpe_docdate'])?$Data['bpe_docdate']:NULL,
											':ac1_debit_import' => 0,
											':ac1_credit_import' => 0,
											':ac1_debit_importsys' => 0,
											':ac1_credit_importsys' => 0,
											':ac1_font_key' => $detail['pe1_docentry'],
											':ac1_font_line' => 1,
											':ac1_font_type' => is_numeric($detail['pe1_doctype'])?$detail['pe1_doctype']:0,
											':ac1_accountvs' => 1,
											':ac1_doctype' => 18,
											':ac1_ref1' => "",
											':ac1_ref2' => "",
											':ac1_ref3' => "",
											':ac1_prc_code' => 0,
											':ac1_uncode' => 0,
											':ac1_prj_code' => isset($Data['bpe_project'])?$Data['bpe_project']:NULL,
											':ac1_rescon_date' => NULL,
											':ac1_recon_total' => 0,
											':ac1_made_user' => isset($Data['bpe_createby'])?$Data['bpe_createby']:NULL,
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
											':ac1_legal_num' => isset($Data['bpe_cardcode'])?$Data['bpe_cardcode']:NULL,
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
														':ac1_doc_date' => $this->validateDate($Data['bpe_docdate'])?$Data['bpe_docdate']:NULL,
														':ac1_doc_duedate' => $this->validateDate($Data['bpe_docdate'])?$Data['bpe_docdate']:NULL,
														':ac1_debit_import' => 0,
														':ac1_credit_import' => 0,
														':ac1_debit_importsys' => 0,
														':ac1_credit_importsys' => 0,
														':ac1_font_key' => $detail['pe1_docentry'],
														':ac1_font_line' => 1,
														':ac1_font_type' => is_numeric($detail['pe1_doctype'])?$detail['pe1_docentry']:0,
														':ac1_accountvs' => 1,
														':ac1_doctype' => 18,
														':ac1_ref1' => "",
														':ac1_ref2' => "",
														':ac1_ref3' => "",
														':ac1_prc_code' => 0,
														':ac1_uncode' => 0,
														':ac1_prj_code' => isset($Data['bpe_project'])?$Data['bpe_project']:NULL,
														':ac1_rescon_date' => NULL,
														':ac1_recon_total' => 0,
														':ac1_made_user' => isset($Data['bpe_createby'])?$Data['bpe_createby']:NULL,
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
														':ac1_legal_num' => isset($Data['bpe_cardcode'])?$Data['bpe_cardcode']:NULL,
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
					if($detail['pe1_doctype'] == 15) {


					$sqlEstado = 'SELECT case when (cfc_doctotal - COALESCE(cfc_paytoday,0)) = 0 then 1 else 0 end estado
												from dcfc
												where cfc_docentry = :cfc_docentry';


					$resEstado = $this->pedeo->queryTable($sqlEstado, array(':cfc_docentry' => $detail['pe1_docentry']));

					if(isset($resEstado[0]) && $resEstado[0]['estado'] == 1){
								$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																		VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

								$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


													':bed_docentry' => $detail['pe1_docentry'],
													':bed_doctype' => $detail['pe1_doctype'],
													':bed_status' => 3, //ESTADO CERRADO
													':bed_createby' => $Data['bpe_createby'],
													':bed_date' => date('Y-m-d'),
													':bed_baseentry' => $resInsert,
													':bed_basetype' => $Data['bpe_doctype']
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

	//FIN DE CRACION DEL PAGO

  //OBTENER PAGOS EFECTUADOS
  public function getPaymentsMade1_get(){

        $sqlSelect = " SELECT * FROM gbpe";

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


	//OBTENER PAGOS POR ID
	public function getPymentsById_get(){

				$Data = $this->get();

				if(!isset($Data['bpe_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM gbpe WHERE bpe_docentry =:bpe_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":bpe_docentry" => $Data['bpe_docentry']));

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


	//OBTENER PAGOS DETALLE POR ID
	public function getQuotationDetail_get(){

				$Data = $this->get();

				if(!isset($Data['pe1_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM bpe1 WHERE pe1_docentry =:pe1_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":pe1_docentry" => $Data['pe1_docentry']));

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



	//OBTENER PAGOS POR SOCIO DE NEGOCIO
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

				$sqlSelect = " SELECT * FROM gbpe WHERE bpe_cardcode =:bpe_cardcode";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":bpe_cardcode" => $Data['dms_card_code']));

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

		if(!isset($Data['pe1_docentry'])){

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' =>'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM bpe1 WHERE pe1_docnum =:pe1_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":pe1_docentry" => $Data['pe1_docentry']));

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