<?php
//
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class AccountReconciliations extends REST_Controller {

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

	public function applyAccountReconciliations_post(){

  		$Data = $this->post();
      $AC1LINE = 0;
      $DocNumVerificado = 0;


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
            'mensaje' =>'No se encontro el detalle de la Nota crédito de clientes'
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

      //BUSCANDO LA NUMERACION DEL DOCUMENTO
      $sqlNumeracion = " SELECT pgs_nextnum,pgs_last_num FROM  pgdn WHERE pgs_id = :pgs_id";

      $resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['crc_series']));

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
			$resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $Data['crc_currency'], ':tsa_date' => $Data['crc_docdate']));

			if(isset($resBusTasa[0])){

			}else{

					if(trim($Data['crc_currency']) != $MONEDALOCAL ){

						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encrontro la tasa de cambio para la moneda: '.$Data['crc_currency'].' en la actual fecha del documento: '.$Data['crc_docdate'].' y la moneda local: '.$resMonedaLoc[0]['pgm_symbol']
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
					}
			}


			$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
			$resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $Data['crc_docdate']));

			if(isset($resBusTasa2[0])){

			}else{
					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :'.$Data['crc_docdate']
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
			}

			$TasaDocLoc = isset($resBusTasa[0]['tsa_value']) ? $resBusTasa[0]['tsa_value'] : 1;
			$TasaLocSys = $resBusTasa2[0]['tsa_value'];

			// FIN DEL PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO

			$this->pedeo->trans_begin();


			$sqlInsert = "INSERT INTO dcrc(crc_docnum, crc_docdate, crc_series, crc_cardcode, crc_currency, crc_comment, crc_doctype, crc_createby)
	                  VALUES (:crc_docnum, :crc_docdate, :crc_series, :crc_cardcode, :crc_currency, :crc_comment, :crc_doctype, :crc_createby)";


			$resInsert = $this->pedeo->insertRow($sqlInsert, array(

             ':crc_docnum'   => $DocNumVerificado,
             ':crc_docdate'  => $this->validateDate($Data['crc_docdate'])?$Data['crc_docdate']:NULL,
             ':crc_series'   => is_numeric($Data['crc_series'])?$Data['crc_series']:0,
             ':crc_cardcode' => isset($Data['crc_cardcode'])?$Data['crc_cardcode']:NULL,
             ':crc_currency' => isset($Data['crc_currency'])?$Data['crc_currency']:NULL,
             ':crc_comment'  => isset($Data['crc_comment'])?$Data['crc_comment']:NULL,
             ':crc_doctype'  => is_numeric($Data['crc_doctype'])?$Data['crc_doctype']:NULL,
             ':crc_createby' => isset($Data['crc_createby'])?$Data['crc_createby']:NULL

			));


			if(is_numeric($resInsert) && $resInsert > 0){

            // Se actualiza la serie de la numeracion del documento

            $sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
                                         WHERE pgs_id = :pgs_id";

            $resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
                ':pgs_nextnum' => $DocNumVerificado,
                ':pgs_id'      => $Data['crc_series']
            ));


            if(is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1){

            }else{
                  $this->pedeo->trans_rollback();

                  $respuesta = array(
                    'error'   => true,
                    'data'    => $resActualizarNumeracion,
                    'mensaje'	=> 'No se pudo actualizar la serie del documento'
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
                      ':bed_doctype' => $Data['crc_doctype'],
                      ':bed_status' => 3, //ESTADO CERRADO
                      ':bed_createby' => $Data['crc_createby'],
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
                    'mensaje'	=> 'No se pudo agregar el estado del documento'
                  );


                  $this->response($respuesta);

                  return;
            }//FIN PROCESO ESTADO DEL DOCUMENTO


            //SE AGREGA ASIENTO CONTABLE

            $sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user)
                                 VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user)";


            $resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

                ':mac_doc_num' => 1,
                ':mac_status' => 1,
                ':mac_base_type' => is_numeric($Data['crc_doctype'])?$Data['crc_doctype']:0,
                ':mac_base_entry' => $resInsert,
                ':mac_doc_date' => $this->validateDate($Data['crc_docdate'])?$Data['crc_docdate']:NULL,
                ':mac_doc_duedate' => $this->validateDate($Data['crc_docdate'])?$Data['crc_docdate']:NULL,
                ':mac_legal_date' => $this->validateDate($Data['crc_docdate'])?$Data['crc_docdate']:NULL,
                ':mac_ref1' => is_numeric($Data['crc_doctype'])?$Data['crc_doctype']:0,
                ':mac_ref2' => "",
                ':mac_ref3' => "",
                ':mac_loc_total' => 0,
                ':mac_fc_total' => 0,
                ':mac_sys_total' => 0,
                ':mac_trans_dode' => 1,
                ':mac_beline_nume' => 1,
                ':mac_vat_date' => $this->validateDate($Data['crc_docdate'])?$Data['crc_docdate']:NULL,
                ':mac_serie' => 1,
                ':mac_number' => 1,
                ':mac_bammntsys' => 0,
                ':mac_bammnt' => 0,
                ':mac_wtsum' => 1,
                ':mac_vatsum' => 0,
                ':mac_comments' => isset($Data['crc_comment'])?$Data['crc_comment']:NULL,
                ':mac_create_date' => $this->validateDate($Data['crc_createat'])?$Data['crc_createat']:NULL,
                ':mac_made_usuer' => isset($Data['crc_createby'])?$Data['crc_createby']:NULL,
                ':mac_update_date' => date("Y-m-d"),
                ':mac_update_user' => isset($Data['crc_createby'])?$Data['crc_createby']:NULL
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
                    'mensaje'	=> 'No se pudo registrar el documento'
                  );

                   $this->response($respuesta);

                   return;
            }//FIN PROCEDIMIENTO PARA CREAR ASIENTO CONTABLE

            //INICIA PROCESO PARA INSERTAR EL DETALLE DE LA RECONCILIACION
            foreach ($ContenidoDetalle as $key => $detail) {

                  $sqlInsertDetail = "INSERT INTO crc1(rc1_docentry, rc1_baseentry, rc1_basetype, rc1_docnum, rc1_docdate, rc1_docduedev, rc1_doctotal, rc1_valapply, rc1_acctcode, rc1_cardcode)
	                                    VALUES (:rc1_docentry, :rc1_baseentry, :rc1_basetype, :rc1_docnum, :rc1_docdate, :rc1_docduedev, :rc1_doctotal, :rc1_valapply, :rc1_acctcode, :rc1_cardcode)";

                  $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(

                        ':rc1_docentry'  => $resInsert,
                        ':rc1_basetype'  => is_numeric($detail['rc1_doctype'])?$detail['rc1_doctype']:0,
												':rc1_baseentry' => is_numeric($detail['rc1_docentry'])?$detail['rc1_docentry']:0,
                        ':rc1_docnum'    => is_numeric($detail['rc1_docnum'])?$detail['rc1_docnum']:0,
                        ':rc1_docdate'   => $this->validateDate($detail['rc1_docdate'])?$detail['rc1_docdate']:NULL,
                        ':rc1_docduedev' => $this->validateDate($detail['rc1_docduedev'])?$detail['rc1_docduedev']:NULL,
                        ':rc1_doctotal'  => is_numeric($detail['rc1_doctotal'])?( $detail['rc1_doctotal'] * -1 ):0,
                        ':rc1_valapply'  => is_numeric($detail['rc1_valapply'])?$detail['rc1_valapply']:0,
                        ':rc1_acctcode'  => is_numeric($detail['rc1_acctcode'])?$detail['rc1_acctcode']:0,
                        ':rc1_cardcode'  => isset($detail['rc1_cardcode'])?$detail['rc1_cardcode']:NULL
                  ));


                  if(is_numeric($resInsertDetail) && $resInsertDetail > 0){

                        if(  $AC1LINE == 0){

                              $AC1LINE = 1;
                        }else{

                             $AC1LINE = $AC1LINE + 1;
                        }

                        //CARGANDO DETALLE ASIENTOS
                        $doctotal = $detail['rc1_doctotal'];
                        $apply = $detail['rc1_valapply'];
                        $debito = 0;
                        $credito = 0;
												$MontoSysDB = 0;
												$MontoSysCR = 0;

												if(trim($Data['crc_currency']) != $MONEDALOCAL ){

													 $apply = ($apply * $TasaDocLoc);

												}

                        if( $doctotal < 0 ){
                          $debito = $apply;
													$MontoSysDB = ($apply / $TasaLocSys);
                        }else if( $doctotal > 0){
                          $credito = $apply;
													$MontoSysCR = ($apply / $TasaLocSys);
                        }

                        $resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

                            ':ac1_trans_id' => $resInsertAsiento,
                            ':ac1_account' => $detail['rc1_acctcode'],
                            ':ac1_debit' => round($debito, 2),
                            ':ac1_credit' => round($credito, 2),
                            ':ac1_debit_sys' => round($MontoSysDB, 2),
                            ':ac1_credit_sys' => round($MontoSysCR, 2),
                            ':ac1_currex' => 0,
                            ':ac1_doc_date' => $this->validateDate($detail['rc1_docdate'])?$detail['rc1_docdate']:NULL,
                            ':ac1_doc_duedate' => $this->validateDate($detail['rc1_docduedev'])?$detail['rc1_docduedev']:NULL,
                            ':ac1_debit_import' => 0,
                            ':ac1_credit_import' => 0,
                            ':ac1_debit_importsys' => 0,
                            ':ac1_credit_importsys' => 0,
                            ':ac1_font_key' => $resInsert,
                            ':ac1_font_line' => 1,
                            ':ac1_font_type' => is_numeric($detail['rc1_doctype'])?$detail['rc1_doctype']:0,
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
                            ':ac1_made_user' => isset($Data['crc_createby'])?$Data['crc_createby']:NULL,
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
                            ':ac1_legal_num' => isset($detail['rc1_cardcode'])?$detail['rc1_cardcode']:NULL,
                            ':ac1_codref' => 1,
                            ':ac1_line'   => $AC1LINE
                      ));



                      if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){

														if( $detail['rc1_doctype'] == 5 ){// 5 DOCUMENTO FACTURA
															//VALIDAR EL VALOR QUE SE ESTA PAGANDO NO SEA MAYOR AL SALDO DE LA FACTURA
															$VlrPayFact = "SELECT COALESCE(dvf_paytoday,0) as dvf_paytoday,dvf_doctotal from dvfv WHERE dvf_docentry = :dvf_docentry and dvf_doctype = :dvf_doctype";
															$resVlrPayFact = $this->pedeo->queryTable($VlrPayFact, array(
																':dvf_docentry' => $detail['rc1_docentry'],
																':dvf_doctype' => $detail['rc1_doctype']
															));


															if(isset($resVlrPayFact[0])){

																$VlrPaidActual = $apply;
																$VlrPaidFact = $resVlrPayFact[0]['dvf_paytoday'];

																$SumVlr =  $VlrPaidActual + $VlrPaidFact ;

																if($SumVlr <= $resVlrPayFact[0]['dvf_doctotal'] ){


																}else{
																	$this->pedeo->trans_rollback();

																	$respuesta = array(
																		'error'   => true,
																		'data' => '',
																		'mensaje'	=> 'El valor a pagar no puede ser mayor al saldo de la factura '.$detail['rc1_docnum'].''
																	);

																	 $this->response($respuesta);

																	 return;
																}

															}else{
																$this->pedeo->trans_rollback();
																$respuesta = array(
																	'error'   => true,
																	'data' => $resVlrPayFact,
																	'mensaje'	=> 'No se encontro el documento para actualizar el valor cruzado');

																	$this->response($respuesta);

																 return;
															}


															$sqlUpdateFactPay = "UPDATE  dvfv  SET dvf_paytoday = COALESCE(dvf_paytoday,0)+:dvf_paytoday WHERE dvf_docentry = :dvf_docentry and dvf_doctype = :dvf_doctype";

															$resUpdateFactPay = $this->pedeo->updateRow($sqlUpdateFactPay,array(

																':dvf_paytoday' => round($apply, 2),
																':dvf_docentry' => $detail['rc1_docentry'],
																':dvf_doctype'  => $detail['rc1_doctype']


															));

															if(is_numeric($resUpdateFactPay) && $resUpdateFactPay > 0){



															}else{

																$this->pedeo->trans_rollback();

																$respuesta = array(
																	'error'   => true,
																	'data' => $resUpdateFactPay,
																	'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura '.$detail['rc1_docnum']
																);

																 $this->response($respuesta);

																 return;
															}
														}

														if( $detail['rc1_doctype'] == 6 ){// 5 NOTA CREDITO

															//VALIDAR EL VALOR QUE SE ESTA CURZANDO NO SEA MAYOR AL SALDO DE LA NOTA CREDITO
															$VlrPayNc = "SELECT COALESCE(vnc_paytoday,0) as vnc_paytoday,vnc_doctotal from dvnc WHERE vnc_docentry = :vnc_docentry and vnc_doctype = :vnc_doctype";
															$resVlrPayNc = $this->pedeo->queryTable($VlrPayNc, array(
																':vnc_docentry' => $detail['rc1_docentry'],
																':vnc_doctype' => $detail['rc1_doctype']
															));


															if(isset($resVlrPayNc[0])){

																$VlrPaidActual = $apply;
																$VlrPaidFact = $resVlrPayNc[0]['vnc_paytoday'];

																$SumVlr =  $VlrPaidActual + $VlrPaidFact ;

																if($SumVlr <= $resVlrPayNc[0]['vnc_doctotal'] ){


																}else{
																	$this->pedeo->trans_rollback();

																	$respuesta = array(
																		'error'   => true,
																		'data' => '',
																		'mensaje'	=> 'El valor a pagar no puede ser mayor al saldo de la factura '.$detail['rc1_docnum'].''
																	);

																	 $this->response($respuesta);

																	 return;
																}

															}else{

																$this->pedeo->trans_rollback();

																$respuesta = array(
																	'error'   => true,
																	'data' => $resVlrPayNc,
																	'mensaje'	=> 'No se encontro el documento para actualizar el valor cruzado');

																	$this->response($respuesta);

																 return;
															}


															$sqlUpdateNcPay = "UPDATE  dvnc  SET vnc_paytoday = COALESCE(vnc_paytoday,0)+:vnc_paytoday WHERE vnc_docentry = :vnc_docentry and vnc_doctype = :vnc_doctype";

															$resUpdateNcPay = $this->pedeo->updateRow($sqlUpdateNcPay,array(

																':vnc_paytoday' => round($apply, 2),
																':vnc_docentry' => $detail['rc1_docentry'],
																':vnc_doctype'  => $detail['rc1_doctype']


															));

															if(is_numeric($resUpdateNcPay) && $resUpdateNcPay > 0){



															}else{

																$this->pedeo->trans_rollback();

																$respuesta = array(
																	'error'   => true,
																	'data' => $resUpdateNcPay,
																	'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura '.$detail['rc1_docnum']
																);

																 $this->response($respuesta);

																 return;
															}


														}

														if( $detail['rc1_doctype'] == 7 ){// 5 NOTA DEBITO

															//VALIDAR EL VALOR QUE SE ESTA CURZANDO NO SEA MAYOR AL SALDO DE LA NOTA DEBITO
															$VlrPayNd = "SELECT COALESCE(vnd_paytoday,0) as vnd_paytoday,vnd_doctotal from dvnd WHERE vnd_docentry = :vnd_docentry and vnd_doctype = :vnd_doctype";
															$resVlrPayNd = $this->pedeo->queryTable($VlrPayNd, array(
																':vnd_docentry' => $detail['rc1_docentry'],
																':vnd_doctype' => $detail['rc1_doctype']
															));


															if(isset($resVlrPayNd[0])){

																$VlrPaidActual = $apply;
																$VlrPaidFact = $resVlrPayNd[0]['vnd_paytoday'];

																$SumVlr =  $VlrPaidActual + $VlrPaidFact ;

																if($SumVlr <= $resVlrPayNd[0]['vnd_doctotal'] ){


																}else{
																	$this->pedeo->trans_rollback();

																	$respuesta = array(
																		'error'   => true,
																		'data' => '',
																		'mensaje'	=> 'El valor a pagar no puede ser mayor al saldo de la factura '.$detail['rc1_docnum'].''
																	);

																	 $this->response($respuesta);

																	 return;
																}

															}else{

																$this->pedeo->trans_rollback();

																$respuesta = array(
																	'error'   => true,
																	'data' => $resVlrPayNd,
																	'mensaje'	=> 'No se encontro el documento para actualizar el valor cruzado');

																	$this->response($respuesta);

																 return;
															}


															$sqlUpdateNdPay = "UPDATE  dvnd  SET vnd_paytoday = COALESCE(vnd_paytoday,0)+:vnd_paytoday WHERE vnd_docentry = :vnd_docentry and vnd_doctype = :vnd_doctype";

															$resUpdateNdPay = $this->pedeo->updateRow($sqlUpdateNdPay,array(

																':vnd_paytoday' => round($apply, 2),
																':vnd_docentry' => $detail['rc1_docentry'],
																':vnd_doctype'  => $detail['rc1_doctype']


															));

															if(is_numeric($resUpdateNdPay) && $resUpdateNdPay > 0){



															}else{

																$this->pedeo->trans_rollback();

																$respuesta = array(
																	'error'   => true,
																	'data' => $resUpdateNdPay,
																	'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura '.$detail['rc1_docnum']
																);

																 $this->response($respuesta);

																 return;
															}


														}


                      }else{

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
                          'data' => $resInsert,
                          'mensaje'	=> 'No se pudo registrar el documento'
                        );

                         $this->response($respuesta);

                         return;
                  }

            }


            $this->pedeo->trans_commit();

						$respuesta = array(
							'error' => false,
							'data' => $resInsert,
							'mensaje' =>'Se completo el procedimiento con exito'
						);



			}else{

						$respuesta = array(
							'error'   => true,
							'data' 		=> $resInsert,
							'mensaje'	=> 'No se pudo registrar el documento'
						);

			}

			$this->response($respuesta);
	}

  //OBTENER RECONCILIACION
  public function getAccountReconciliations_get(){

        $sqlSelect = "SELECT dcrc.*, tded.ded_description, tded.ded_id
											FROM dcrc
											INNER JOIN tbed
											ON dcrc.crc_docentry = tbed.bed_docentry
											AND dcrc.crc_doctype = tbed.bed_doctype
											INNER JOIN tded
											ON tbed.bed_status  = tded.ded_id";

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



  private function validateDate($fecha){
      if(strlen($fecha) == 10 OR strlen($fecha) > 10){
        return true;
      }else{
        return false;
      }
  }


}
