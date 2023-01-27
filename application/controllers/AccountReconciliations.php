<?php
//RECONCILIACION DE CUENTAS
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
		$this->load->library('generic');

	}

	public function applyAccountReconciliations_post(){

			$DECI_MALES =  $this->generic->getDecimals();
  		$Data = $this->post();
      $AC1LINE = 0;
      $DocNumVerificado = 0;
			$DetalleAsiento = new stdClass();
			$DetalleAsientoConsolidado = [];
			$llaveDetalleAsiento = "";
			$posicionDetalleAsiento = 0;
			$cuentaTercero = 0;
			$inArrayDetalleAsiento = array();
			$VlrDiffP = 0;
			$VlrDiffN = 0;
			$VlrTotalOpc = 0; // valor total acumulado de la operacion
			$VlrDiff = 0; // valor diferencia total
			$VlrPagoEfectuado = 0;
			$OP = 0;
			$TasaOrg = 0;

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

			//
			//VALIDANDO PERIODO CONTABLE
			$periodo = $this->generic->ValidatePeriod($Data['crc_docdate'], $Data['crc_docdate'],$Data['crc_docdate'],0);

			if( isset($periodo['error']) && $periodo['error'] == false){

			}else{
				$respuesta = array(
					'error'   => true,
					'data'    => [],
					'mensaje' => isset($periodo['mensaje'])?$periodo['mensaje']:'no se pudo validar el periodo contable'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			//PERIODO CONTABLE
			//

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
                      ':bed_basetype' => NULL
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

									$VrlPagoDetalleNormal  = 0;
									$Equiv = 0;

									//VERIFICANDO DOCUMENTOS
									if( $detail['rc1_doctype'] == 15 || $detail['rc1_doctype'] == 16 || $detail['rc1_doctype'] == 17
											|| $detail['rc1_doctype'] == 19 || $detail['rc1_doctype'] == 18 || $detail['rc1_doctype'] == 20
											|| $detail['rc1_doctype'] == 5 || $detail['rc1_doctype'] == 6 || $detail['rc1_doctype'] ==  7){

										$pf = "";
										$tb  = "";

										if( $detail['rc1_doctype'] == 15 ){
											$pf = "cfc";
											$tb  = "dcfc";
											$OP = 2;
										}else if($detail['rc1_doctype'] == 16){
											$pf = "cnc";
											$tb  = "dcnc";
											$OP = 2;
										}else if($detail['rc1_doctype'] == 17){
											$OP = 2;
										}else if( $detail['rc1_doctype'] == 19 ){
											$pf = "bpe";
											$tb  = "gbpe";
											$OP = 2;
										}else if( $detail['rc1_doctype'] == 5 ){
											$pf = "dvf";
											$tb  = "dvfv";
											$OP = 1;
										}else if($detail['rc1_doctype'] == 6){
											$pf = "vnc";
											$tb  = "dvnc";
											$OP = 1;
										}else if($detail['rc1_doctype'] == 7){
											$OP = 1;
										}else if( $detail['rc1_doctype'] == 20 ){
											$pf = "bpr";
											$tb  = "gbpr";
											$OP = 1;
										}else if($detail['rc1_doctype'] == 18){

											$OP = $detail['ac1_cord'];

										}

										$resVlrPay = $this->generic->validateBalance($detail['rc1_docentry'],$detail['rc1_doctype'],$tb,$pf,$detail['rc1_valapply'],$Data['crc_currency'],$Data['crc_docdate'],$OP,isset($detail['ac1_line_num'])?$detail['ac1_line_num']:0);

										if( isset( $resVlrPay['error'] ) ){

													if( $resVlrPay['error'] == false ){

														$VlrTotalOpc = $resVlrPay['vlrop'];
														$VlrDiff     = ($VlrDiff + $resVlrPay['vlrdiff']);
														$TasaOrg     = $resVlrPay['tasadoc'];
														$Equiv       = $resVlrPay['equiv'];

														$VlrTotalOpc = ($VlrTotalOpc + $Equiv);

														// echo $detail['rc1_doctype']."  \n".$Equiv." \n";

													}else{

														$this->pedeo->trans_rollback();

														$respuesta = array(
																'error'   => true,
																'data'    => [],
																'mensaje'	=> $resVlrPay['mensaje']);

														return $this->response($respuesta);

													}

										}else{

											$this->pedeo->trans_rollback();

											$respuesta = array(
													'error'   => true,
													'data'    => [],
													'mensaje'	=> 'No se pudo validar el saldo actual del documento '.$detail['pe1_docentry']);

											$this->response($respuesta);

											return;
										}
									}
									//FIN VERIFICACION DE DOCUMENTOS


                  if(is_numeric($resInsertDetail) && $resInsertDetail > 0){


										// ACTUALIZANDO VALORES EN DOCUMENTOS
										//ESPACIO PARA VENTAS
										//PAYTODAY
										if($detail['rc1_doctype'] == 5){
										   // se actualiza la factura

											$sqlUpdateFactPay = "UPDATE  dvfv  SET dvf_paytoday = COALESCE(dvf_paytoday,0)+:dvf_paytoday WHERE dvf_docentry = :dvf_docentry and dvf_doctype = :dvf_doctype";

											$resUpdateFactPay = $this->pedeo->updateRow($sqlUpdateFactPay,array(

												':dvf_paytoday' => round( $VlrTotalOpc, $DECI_MALES),
												':dvf_docentry' => $detail['rc1_docentry'],
												':dvf_doctype'  => $detail['rc1_doctype']


											));

											if(is_numeric($resUpdateFactPay) && $resUpdateFactPay == 1){

											}else{
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data' => $resUpdateFactPay,
													'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura '.$detail['rc1_docentry']
												);

												 $this->response($respuesta);

												 return;
											}

											//ASIENTO


											$slqUpdateVenDebit = "UPDATE mac1
																						SET ac1_ven_credit = ac1_ven_credit + :ac1_ven_credit
																						WHERE ac1_line_num = :ac1_line_num";
											$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

												':ac1_ven_credit' => round( $VlrTotalOpc, $DECI_MALES ),
												':ac1_line_num'   => $detail['ac1_line_num']

											));

											if(is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1){

											}else{
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data' 		=> $resUpdateVenDebit,
													'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura '.$detail['rc1_docentry']
												);

												 $this->response($respuesta);

												 return;
											}

											//VERIFICAR PARA CERRAR DOCUMENTO

											$resEstado = $this->generic->validateBalanceAndClose($detail['rc1_docentry'],$detail['rc1_doctype'],'dvfv','dvf');


											if(isset($resEstado['error']) && $resEstado['error'] === true){
														$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																								VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

														$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


																			':bed_docentry' => $detail['rc1_docentry'],
																			':bed_doctype' => $detail['rc1_doctype'],
																			':bed_status' => 3, //ESTADO CERRADO
																			':bed_createby' => $Data['crc_createby'],
																			':bed_date' => date('Y-m-d'),
																			':bed_baseentry' => $resInsert,
																			':bed_basetype' => $Data['crc_doctype']
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


										//PAYTODAY
										if($detail['rc1_doctype'] == 6) { // SE ACTUALIZA EN NOTA DEBITO


											$sqlUpdateFactPay = "UPDATE  dvnc  SET vnc_paytoday = COALESCE(vnc_paytoday,0)+:vnc_paytoday WHERE vnc_docentry = :vnc_docentry and vnc_doctype = :vnc_doctype";

											$resUpdateFactPay = $this->pedeo->updateRow($sqlUpdateFactPay,array(

												':vnc_paytoday' => $VlrTotalOpc,
												':vnc_docentry' => $detail['rc1_docentry'],
												':vnc_doctype'  => $detail['rc1_doctype']


											));

											if(is_numeric($resUpdateFactPay) && $resUpdateFactPay == 1){

											}else{
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data' => $resUpdateFactPay,
													'mensaje'	=> 'No se pudo actualizar el valor del pago en la nota credito '.$detail['rc1_docentry']
												);

												 $this->response($respuesta);

												 return;
											}


											//Asiento

											$slqUpdateVenDebit = "UPDATE mac1
																						SET ac1_ven_debit = ac1_ven_debit + :ac1_ven_debit
																						WHERE ac1_line_num = :ac1_line_num";
											$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

												':ac1_ven_debit' => round( $VlrTotalOpc, $DECI_MALES ),
												':ac1_line_num'  => $detail['ac1_line_num'],

											));

											if(is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1){

											}else{
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data' => $resUpdateFactPay,
													'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura '.$detail['rc1_docentry']
												);

												 $this->response($respuesta);

												 return;
											}

											//ESTADO
											$resEstado = $this->generic->validateBalanceAndClose($detail['rc1_docentry'],$detail['rc1_doctype'],'dvnc','vnc');


											if(isset($resEstado['error']) && $resEstado['error'] === true){
														$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																								VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

														$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


																			':bed_docentry' => $detail['rc1_docentry'],
																			':bed_doctype' => $detail['rc1_doctype'],
																			':bed_status' => 3, //ESTADO CERRADO
																			':bed_createby' => $Data['crc_createby'],
																			':bed_date' => date('Y-m-d'),
																			':bed_baseentry' => $resInsert,
																			':bed_basetype' => $Data['crc_doctype']
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

										//ASIENTO DEL ANTICIPO CLIENTE
										if( $detail['rc1_doctype'] == 20 ){

											$slqUpdateVenDebit = "UPDATE mac1
																						SET ac1_ven_debit = ac1_ven_debit + :ac1_ven_debit
																						WHERE ac1_line_num = :ac1_line_num";
											$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

												':ac1_ven_debit' => round( $VlrTotalOpc, $DECI_MALES ),
												':ac1_line_num'  => $detail['ac1_line_num'],

											));

											if(is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1){

											}else{
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data' => $resUpdateFactPay,
													'mensaje'	=> 'No se pudo actualizar el valor del pago en el anticipo '.$detail['rc1_docentry']
												);

												 $this->response($respuesta);

												 return;
											}
										}

										//FIN DE ESPACIO PARA VENTAS

										//EMPIEZA ESPACIO PARA COMPRAS
										//ACTUALIZAR VALOR PAGADO DE LA FACTURA DE COMPRA

										if( $detail['rc1_doctype'] == 15 ){

											$sqlUpdateFactPay = "UPDATE  dcfc  SET cfc_paytoday = COALESCE(cfc_paytoday,0)+:cfc_paytoday WHERE cfc_docentry = :cfc_docentry and cfc_doctype = :cfc_doctype";

											$resUpdateFactPay = $this->pedeo->updateRow($sqlUpdateFactPay,array(

												':cfc_paytoday' => $VlrTotalOpc,
												':cfc_docentry' => $detail['rc1_docentry'],
												':cfc_doctype' =>  $detail['rc1_doctype']

											));

											if(is_numeric($resUpdateFactPay) && $resUpdateFactPay > 0){

											}else{
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data' => $resUpdateFactPay,
													'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura '.$detail['rc1_docentry']
												);

												 $this->response($respuesta);

												 return;
											}

											//ASIENTO
											$slqUpdateVenDebit = "UPDATE mac1
																						SET ac1_ven_debit = ac1_ven_debit + :ac1_ven_debit
																						WHERE ac1_line_num = :ac1_line_num";

											$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

												':ac1_ven_debit'  => round( $VlrTotalOpc, $DECI_MALES ),
												':ac1_line_num'   => $detail['ac1_line_num']


											));

											if(is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1){

											}else{
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data' => $resUpdateVenDebit,
													'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura '.$detail['rc1_docentry']
												);

												 $this->response($respuesta);

												 return;
											}

											//Estado DOCUMENTO

											$resEstado = $this->generic->validateBalanceAndClose($detail['rc1_docentry'],$detail['rc1_doctype'],'dcfc','cfc');



											if(isset($resEstado['error']) && $resEstado['error'] === true){
														$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																								VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

														$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


																			':bed_docentry' => $detail['rc1_docentry'],
																			':bed_doctype' => $detail['rc1_doctype'],
																			':bed_status' => 3, //ESTADO CERRADO
																			':bed_createby' => $Data['crc_createby'],
																			':bed_date' => date('Y-m-d'),
																			':bed_baseentry' => $resInsert,
																			':bed_basetype' => $Data['crc_doctype']
														));


														if(is_numeric($resInsertEstado) && $resInsertEstado > 0){

														}else{

																 $this->pedeo->trans_rollback();

																	$respuesta = array(
																		'error'   => true,
																		'data' => $resInsertEstado,
																		'mensaje'	=> 'No se pudo registrar la reconciliación'
																	);


																	$this->response($respuesta);

																	return;
														}
											}
										}


										// SE ACTUALIZA EL VALOR DEL CAMPO PAY TODAY EN NOTA CREDITO
										if($detail['rc1_doctype'] == 16) { // SOLO CUANDO ES UNA NOTA CREDITO



											$sqlUpdateFactPay = "UPDATE  dcnc  SET cnc_paytoday = COALESCE(cnc_paytoday,0)+:cnc_paytoday WHERE cnc_docentry = :cnc_docentry and cnc_doctype = :cnc_doctype";

											$resUpdateFactPay = $this->pedeo->updateRow($sqlUpdateFactPay,array(

												':cnc_paytoday' => $VlrTotalOpc,
												':cnc_docentry' => $detail['rc1_docentry'],
												':cnc_doctype'  => $detail['rc1_doctype']


											));

											if(is_numeric($resUpdateFactPay) && $resUpdateFactPay == 1){

											}else{
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data' => $resUpdateFactPay,
													'mensaje'	=> 'No se pudo actualizar el valor del pago en la nota credito '.$detail['rc1_docentry']
												);

												 $this->response($respuesta);

												 return;
											}

											//ASIENTO
											$slqUpdateVenDebit = "UPDATE mac1
																						SET ac1_ven_credit = ac1_ven_credit + :ac1_ven_credit
																						WHERE ac1_line_num = :ac1_line_num";

											$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

												':ac1_ven_credit' => round( $VlrTotalOpc, $DECI_MALES ),
												':ac1_line_num'   => $detail['ac1_line_num']
											));

											if(is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1){

											}else{
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data' => $resUpdateFactPay,
													'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura '.$detail['rc1_docentry']
												);

												 $this->response($respuesta);

												 return;
											}

											//ESTADO DOCUMENTO

											$resEstado = $this->generic->validateBalanceAndClose($detail['rc1_docentry'],$detail['rc1_doctype'],'dcnc','cnc');


											if(isset($resEstado['error']) && $resEstado['error'] === true){
														$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																								VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

														$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


																			':bed_docentry' => $detail['rc1_docentry'],
																			':bed_doctype' => $detail['rc1_doctype'],
																			':bed_status' => 3, //ESTADO CERRADO
																			':bed_createby' => $Data['crc_createby'],
																			':bed_date' => date('Y-m-d'),
																			':bed_baseentry' => $resInsert,
																			':bed_basetype' => $Data['crc_doctype']
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


										if($detail['rc1_doctype'] == 19){

											$slqUpdateVenDebit = "UPDATE mac1
																						SET ac1_ven_credit = ac1_ven_credit + :ac1_ven_credit
																						WHERE ac1_line_num = :ac1_line_num";

											$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

												':ac1_ven_credit' => round( $VlrTotalOpc, $DECI_MALES ),
												':ac1_line_num'   => $detail['ac1_line_num']
											));

											if(is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1){

											}else{
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data' => $resUpdateFactPay,
													'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura '.$detail['rc1_docentry']
												);

												 $this->response($respuesta);

												 return;
											}

										}
										//FIN ESPACIO PARA COMPRAS

										//ASIENTO MANUALES
										if($detail['rc1_doctype'] == 18){

											$VlrPay = $detail['rc1_valapply'];

											if(trim($Data['crc_currency']) != $MONEDALOCAL ){
												$VlrPay = ($detail['rc1_valapply'] * $TasaDocLoc);
											}


											if( $detail['ac1_cord'] == 1){

												$slqUpdateVenDebit = "UPDATE mac1
																							SET ac1_ven_debit = ac1_ven_debit + :ac1_ven_debit
																							WHERE ac1_line_num = :ac1_line_num";

												$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

													':ac1_ven_debit'  => round( $VlrPay, $DECI_MALES ),
													':ac1_line_num'   => $detail['ac1_line_num']
												));

												if(is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1){

												}else{
													$this->pedeo->trans_rollback();

													$respuesta = array(
														'error'   => true,
														'data' => $resUpdateVenDebit,
														'mensaje'	=> 'No se pudo actualizar el valor del pago'.$detail['rc1_docentry']
													);

													 $this->response($respuesta);

													 return;
												}
											}else if( $detail['ac1_cord'] == 0){
												$slqUpdateVenDebit = "UPDATE mac1
																							SET ac1_ven_credit = ac1_ven_credit + :ac1_ven_credit
																							WHERE ac1_line_num = :ac1_line_num";

												$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

													':ac1_ven_credit' => round( $VlrPay, $DECI_MALES ),
													':ac1_line_num'   => $detail['ac1_line_num']
												));

												if(is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1){

												}else{
													$this->pedeo->trans_rollback();

													$respuesta = array(
														'error'   => true,
														'data' => $resUpdateVenDebit,
														'mensaje'	=> 'No se pudo actualizar el valor del pago'.$detail['rc1_docentry']
													);

													 $this->response($respuesta);

													 return;
												}
											}
										}


										//MOVIMIENTO DE DOCUMENTOS
										if( $detail['rc1_doctype'] == 5 || $detail['rc1_doctype'] == 6 || $detail['rc1_doctype'] == 7 || $detail['rc1_doctype'] == 15 || $detail['rc1_doctype'] == 16 || $detail['rc1_doctype'] == 17 || $detail['rc1_doctype'] == 19 || $detail['rc1_doctype'] == 20 ){
											//SE APLICA PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS
											if( isset($detail['rc1_docentry']) && is_numeric($detail['rc1_docentry']) && isset($detail['rc1_doctype']) && is_numeric($detail['rc1_doctype']) ){

												$sqlDocInicio = "SELECT bmd_tdi, bmd_ndi FROM tbmd WHERE  bmd_doctype = :bmd_doctype AND bmd_docentry = :bmd_docentry";
												$resDocInicio = $this->pedeo->queryTable($sqlDocInicio, array(
													 ':bmd_doctype'  => $detail['rc1_doctype'],
													 ':bmd_docentry' => $detail['rc1_docentry']
												));

													$tipoCardCode = 0;

													switch ($detail['rc1_doctype']) {
														case 5:
																$tipoCardCode = 1;
															break;
														case 6:
																$tipoCardCode = 1;
															break;
														case 7:
																$tipoCardCode = 1;
															break;
														case 15:
																$tipoCardCode = 2;
															break;
														case 16:
																$tipoCardCode = 2;
															break;
														case 17:
																$tipoCardCode = 2;
															break;
														case 19:
																$tipoCardCode = 2;
															break;
														case 20:
																$tipoCardCode = 1;
															break;
													}


													if ( isset(	$resDocInicio[0] ) ){

														$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
														bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency,business)
														VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,:bmd_docentryo, :bmd_tdi, :bmd_ndi, 
														:bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency,:business)";

														$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

															':bmd_doctype' => is_numeric($Data['crc_doctype'])?$Data['crc_doctype']:0,
															':bmd_docentry' => $resInsert,
															':bmd_createat' => $this->validateDate($Data['crc_createat'])?$Data['crc_createat']:NULL,
															':bmd_doctypeo' => is_numeric($detail['rc1_doctype'])?$detail['rc1_doctype']:0, //ORIGEN
															':bmd_docentryo' => is_numeric($detail['rc1_docentry'])?$detail['rc1_docentry']:0,  //ORIGEN
															':bmd_tdi' => $resDocInicio[0]['bmd_tdi'], // DOCUMENTO INICIAL
															':bmd_ndi' => $resDocInicio[0]['bmd_ndi'], // DOCUMENTO INICIAL
															':bmd_docnum' => $DocNumVerificado,
															':bmd_doctotal' => $VlrTotalOpc,
															':bmd_cardcode' => isset($detail['rc1_cardcode'])?$detail['rc1_cardcode']:NULL,
															':bmd_cardtype' => $tipoCardCode,
															':bmd_currency' => isset($Data['crc_currency'])?$Data['crc_currency']:NULL,
															':business' =>isset($Data['business']) ? $Data['business'] : NULL
														));

														if( is_numeric($resInsertMD) && $resInsertMD > 0 ){

														}else{

															$this->pedeo->trans_rollback();

															 $respuesta = array(
																 'error'   => true,
																 'data' => $resInsertMD,
																 'mensaje'	=> 'No se pudo registrar el movimiento del documento'
															 );


															 $this->response($respuesta);

															 return;
														}

													}

												}

										}

										//FIN PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS

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
									//ASIENTOS MANUALES
									// LLENANDO PARA DETALLE DE ASIENTOS CONTABLES
									$DetalleAsiento = new stdClass();

									$DetalleAsiento->cuenta   = is_numeric($detail['rc1_acctcode'])?$detail['rc1_acctcode']:0;
									$DetalleAsiento->tercero  = isset($detail['rc1_cardcode'])?$detail['rc1_cardcode']:NULL;
									$DetalleAsiento->tipodoc  = is_numeric($detail['rc1_doctype'])?$detail['rc1_doctype']:0;
									$DetalleAsiento->pagoaply = is_numeric($detail['rc1_valapply'])?$detail['rc1_valapply']:0;
									$DetalleAsiento->cord     = isset($detail['ac1_cord'])?$detail['ac1_cord']:NULL;
									$DetalleAsiento->vlrpaiddesc	= $VlrTotalOpc;
									$DetalleAsiento->tasaoriginaldoc = $TasaOrg ;

									// $llaveDetalleAsiento = $DetalleAsiento->tipodoc.$DetalleAsiento->tasaoriginaldoc;

									$llaveDetalleAsiento = $detail['ac1_line_num'];

									//********************
									if(in_array( $llaveDetalleAsiento, $inArrayDetalleAsiento)){

											$posicionDetalleAsiento  = $this->buscarPosicion( $llaveDetalleAsiento, $inArrayDetalleAsiento);

									}else{

											array_push( $inArrayDetalleAsiento, $llaveDetalleAsiento );
											$posicionDetalleAsiento = $this->buscarPosicion( $llaveDetalleAsiento, $inArrayDetalleAsiento);

									}
									//

									if( isset($DetalleAsientoConsolidado [$posicionDetalleAsiento])){

										if(!is_array($DetalleAsientoConsolidado[$posicionDetalleAsiento])){
											$DetalleAsientoConsolidado[$posicionDetalleAsiento] = array();
										}

									}else{
										$DetalleAsientoConsolidado[$posicionDetalleAsiento] = array();
									}

									array_push( $DetalleAsientoConsolidado[$posicionDetalleAsiento], $DetalleAsiento);


            }

						// INSERTANDO EL DETALLE DE LOS ASIENTOS
						$ACCLINE = 1;
						foreach ($DetalleAsientoConsolidado as $key => $posicion) {
										$TotalPago = 0;
										$TotalPagoOriginal = 0;

										$cuenta = 0;
										$doctype = 0;
										$ac1cord = null;
										$tasadoc = 0;


										foreach ($posicion as $key => $value) {

													$TotalPago = ( $TotalPago + $value->vlrpaiddesc );

													$cuenta  = $value->cuenta;
													$doctype = $value->tipodoc;
													$ac1cord = $value->cord;
													$tasadoc = $value->tasaoriginaldoc;
										}


										$debito = 0;
										$credito = 0;
										$MontoSysDB = 0;
										$MontoSysCR = 0;
										$TotalPagoOriginal = $TotalPago;

										if(	$doctype == 5 || 	$doctype == 7 || 	$doctype == 19 || $doctype == 16 ){

											$credito = $TotalPago;

											if(trim($Data['crc_currency']) != $MONEDASYS ){

													$MontoSysCR = ($credito / $TasaLocSys);

											}else{

													$MontoSysCR = ($credito / $tasadoc);

											}

										}else if(	$doctype == 6  ||  $doctype == 20 || $doctype == 15 ||  $doctype == 17){

											$debito = $TotalPago;

											if(trim($Data['crc_currency']) != $MONEDASYS ){

													$MontoSysDB = ($debito / $TasaLocSys);

											}else{

													$MontoSysDB = ($debito / $tasadoc);
											}

										}else if( $doctype == 18 ){
											if( $ac1cord == 0 ){

												$credito = $TotalPago;

												if(trim($Data['crc_currency']) != $MONEDASYS ){

													  $MontoSysCR = ($credito / $TasaLocSys);

												}else{
														$MontoSysCR = ($credito / $tasadoc);

												}

											}else if( $ac1cord == 1 ){

												$debito = $TotalPago;

												if(trim($Data['crc_currency']) != $MONEDASYS ){

														$MontoSysDB = ($debito / $TasaLocSys);

												}else{

														$MontoSysDB = ($debito / $tasadoc);

												}
											}
										}

										$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

												':ac1_trans_id' => $resInsertAsiento,
												':ac1_account' => $cuenta,
												':ac1_debit' => round($debito, $DECI_MALES),
												':ac1_credit' => round($credito, $DECI_MALES),
												':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
												':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
												':ac1_currex' => 0,
												':ac1_doc_date' => $this->validateDate($Data['crc_docdate'])?$Data['crc_docdate']:NULL,
												':ac1_doc_duedate' => $this->validateDate($Data['crc_docdate'])?$Data['crc_docdate']:NULL,
												':ac1_debit_import' => 0,
												':ac1_credit_import' => 0,
												':ac1_debit_importsys' => 0,
												':ac1_credit_importsys' => 0,
												':ac1_font_key' => $resInsert,
												':ac1_font_line' => 1,
												':ac1_font_type' => 22,
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
												':ac1_made_user' => 0,
												':ac1_accperiod' => 1,
												':ac1_close' => 0,
												':ac1_cord' => 0,
												':ac1_ven_debit' => 0,
												':ac1_ven_credit' => 0,
												':ac1_fiscal_acct' => 0,
												':ac1_taxid' => 1,
												':ac1_isrti' => 0,
												':ac1_basert' => 0,
												':ac1_mmcode' => 0,
												':ac1_legal_num' => isset($Data['crc_cardcode'])?$Data['crc_cardcode']:NULL,
												':ac1_codref' => 1,
												':ac1_line' => $ACCLINE
									));

									$ACCLINE = $ACCLINE+1;

									if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
											// Se verifica que el detalle no de error insertando //
									}else{
											// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
											// se retorna el error y se detiene la ejecucion del codigo restante.
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data'	  => $resDetalleAsiento,
													'mensaje'	=> 'No se pudo continuar con el proceso, occurio un error al insertar el detalle del asiento cuenta tercero'
												);

												 $this->response($respuesta);

												 return;
									}
						}


						//VALIDAR DIFERENCIA EN CAMBIO
						//se verifica si existe diferencia en cambio
						if(trim($Data['crc_currency']) != $MONEDALOCAL ){
							$sqlCuentaDiferenciaCambio = "SELECT pge_acc_dcp, pge_acc_dcn FROM pgem";
							$resCuentaDiferenciaCambio = $this->pedeo->queryTable($sqlCuentaDiferenciaCambio, array());

							$CuentaDiferenciaCambio = [];

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

							// SI ES VENTAS O COMPRAS 1 = ventas 2 = compras
							if ( $OP == 1 ){

								if ( $VlrDiff  <  0 ){

									$VlrDiffN = abs($VlrDiff);

								}else if ( $VlrDiff > 0 ){

									$VlrDiffP = abs($VlrDiff);

								}else if ( $VlrDiff  == 0 ){

									$VlrDiffN = 0;
									$VlrDiffP = 0;

								}



								if ( $VlrDiffP > 0 ){
												$cuentaD = "";
												$credito = 0;
												$debito  = 0;
												$MontoSysDB = 0;
												$MontoSysCR = 0;



												$cuentaD    = $CuentaDiferenciaCambio['pge_acc_dcp'];
												$debito    = $VlrDiffP;
												$MontoSysDB = ($debito / $TasaLocSys);




												$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

														':ac1_trans_id' => $resInsertAsiento,
														':ac1_account' => $cuentaD,
														':ac1_debit' => round($debito, $DECI_MALES),
														':ac1_credit' => 0,
														':ac1_debit_sys' => 0,
														':ac1_credit_sys' => 0,
														':ac1_currex' => 0,
														':ac1_doc_date' => $this->validateDate($Data['crc_docdate'])?$Data['crc_docdate']:NULL,
														':ac1_doc_duedate' => $this->validateDate($Data['crc_docdate'])?$Data['crc_docdate']:NULL,
														':ac1_debit_import' => 0,
														':ac1_credit_import' => 0,
														':ac1_debit_importsys' => 0,
														':ac1_credit_importsys' => 0,
														':ac1_font_key' => $resInsert,
														':ac1_font_line' => 1,
														':ac1_font_type' => 22,
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
														':ac1_made_user' => 0,
														':ac1_accperiod' => 1,
														':ac1_close' => 0,
														':ac1_cord' => 0,
														':ac1_ven_debit' => 0,
														':ac1_ven_credit' => 0,
														':ac1_fiscal_acct' => 0,
														':ac1_taxid' => 1,
														':ac1_isrti' => 0,
														':ac1_basert' => 0,
														':ac1_mmcode' => 0,
														':ac1_legal_num' => isset($Data['crc_cardcode'])?$Data['crc_cardcode']:NULL,
														':ac1_codref' => 1,
														':ac1_line' => 1
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
															'mensaje'	=> 'No se pudo completar el proceso, occurio un error al insertar el detalle del asiento diferencia en cambio'
														);

														 $this->response($respuesta);

														 return;
											}
								}


								if ( $VlrDiffN > 0 ){

												$cuentaD = "";
												$credito = 0;
												$debito  = 0;
												$MontoSysDB = 0;
												$MontoSysCR = 0;



												$cuentaD    = $CuentaDiferenciaCambio['pge_acc_dcp'];
												$credito    = $VlrDiffN;
												$MontoSysCR = ($credito / $TasaLocSys);




												$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

														':ac1_trans_id' => $resInsertAsiento,
														':ac1_account' => $cuentaD,
														':ac1_debit' => 0,
														':ac1_credit' => round($credito, $DECI_MALES),
														':ac1_debit_sys' => 0,
														':ac1_credit_sys' => 0,
														':ac1_currex' => 0,
														':ac1_doc_date' => $this->validateDate($Data['crc_docdate'])?$Data['crc_docdate']:NULL,
														':ac1_doc_duedate' => $this->validateDate($Data['crc_docdate'])?$Data['crc_docdate']:NULL,
														':ac1_debit_import' => 0,
														':ac1_credit_import' => 0,
														':ac1_debit_importsys' => 0,
														':ac1_credit_importsys' => 0,
														':ac1_font_key' => $resInsert,
														':ac1_font_line' => 1,
														':ac1_font_type' => 22,
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
														':ac1_made_user' => 0,
														':ac1_accperiod' => 1,
														':ac1_close' => 0,
														':ac1_cord' => 0,
														':ac1_ven_debit' => 0,
														':ac1_ven_credit' => 0,
														':ac1_fiscal_acct' => 0,
														':ac1_taxid' => 1,
														':ac1_isrti' => 0,
														':ac1_basert' => 0,
														':ac1_mmcode' => 0,
														':ac1_legal_num' => isset($Data['crc_cardcode'])?$Data['crc_cardcode']:NULL,
														':ac1_codref' => 1,
														':ac1_line' => 1
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
															'mensaje'	=> 'No se pudo completar el proceso, occurio un error al insertar el detalle del asiento diferencia en cambio'
														);

														 $this->response($respuesta);

														 return;
											}
								}





							}else if ( $OP == 2 ){

								if ( $VlrDiff  <  0 ){

									$VlrDiffP = abs($VlrDiff);

								}else if ( $VlrDiff > 0 ){

									$VlrDiffN = abs($VlrDiff);

								}else if ( $VlrDiff  == 0 ){

									$VlrDiffN = 0;
									$VlrDiffP = 0;

								}

								if( $VlrDiffP > 0 ){


												$cuentaD    = $CuentaDiferenciaCambio['pge_acc_dcp'];
												$credito    = $VlrDiffP;
												$MontoSysCR = ($credito / $TasaLocSys);



												$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

														':ac1_trans_id' => $resInsertAsiento,
														':ac1_account' => $cuentaD,
														':ac1_debit' => 0,
														':ac1_credit' => round( $VlrDiffP, $DECI_MALES ),
														':ac1_debit_sys' => 0,
														':ac1_credit_sys' => 0,
														':ac1_currex' => 0,
														':ac1_doc_date' => $this->validateDate($Data['crc_docdate'])?$Data['crc_docdate']:NULL,
														':ac1_doc_duedate' => $this->validateDate($Data['crc_docdate'])?$Data['crc_docdate']:NULL,
														':ac1_debit_import' => 0,
														':ac1_credit_import' => 0,
														':ac1_debit_importsys' => 0,
														':ac1_credit_importsys' => 0,
														':ac1_font_key' => $resInsert,
														':ac1_font_line' => 1,
														':ac1_font_type' => 22,
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
														':ac1_made_user' => 0,
														':ac1_accperiod' => 1,
														':ac1_close' => 0,
														':ac1_cord' => 0,
														':ac1_ven_debit' => 0,
														':ac1_ven_credit' => 0,
														':ac1_fiscal_acct' => 0,
														':ac1_taxid' => 1,
														':ac1_isrti' => 0,
														':ac1_basert' => 0,
														':ac1_mmcode' => 0,
														':ac1_legal_num' => isset($Data['crc_cardcode'])?$Data['crc_cardcode']:NULL,
														':ac1_codref' => 1,
														':ac1_line' => 1
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
															'mensaje'	=> 'No se pudo completar el proceso, occurio un error al insertar el detalle del asiento diferencia en cambio'
														);

														 $this->response($respuesta);

														 return;
											}


								}

								if( $VlrDiffN > 0 ){


												$cuentaD    = $CuentaDiferenciaCambio['pge_acc_dcn'];
												$debito     =  $VlrDiffN;
												$MontoSysDB = ($debito / $TasaLocSys);


												$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

														':ac1_trans_id' => $resInsertAsiento,
														':ac1_account' => $cuentaD,
														':ac1_debit' =>  round( $VlrDiffN, $DECI_MALES ),
														':ac1_credit' => 0,
														':ac1_debit_sys' => 0,
														':ac1_credit_sys' => 0,
														':ac1_currex' => 0,
														':ac1_doc_date' => $this->validateDate($Data['crc_docdate'])?$Data['crc_docdate']:NULL,
														':ac1_doc_duedate' => $this->validateDate($Data['crc_docdate'])?$Data['crc_docdate']:NULL,
														':ac1_debit_import' => 0,
														':ac1_credit_import' => 0,
														':ac1_debit_importsys' => 0,
														':ac1_credit_importsys' => 0,
														':ac1_font_key' => $resInsert,
														':ac1_font_line' => 1,
														':ac1_font_type' => 22,
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
														':ac1_made_user' => 0,
														':ac1_accperiod' => 1,
														':ac1_close' => 0,
														':ac1_cord' => 0,
														':ac1_ven_debit' => 0,
														':ac1_ven_credit' => 0,
														':ac1_fiscal_acct' => 0,
														':ac1_taxid' => 1,
														':ac1_isrti' => 0,
														':ac1_basert' => 0,
														':ac1_mmcode' => 0,
														':ac1_legal_num' => isset($Data['crc_cardcode'])?$Data['crc_cardcode']:NULL,
														':ac1_codref' => 1,
														':ac1_line' => 1
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
															'mensaje'	=> 'No se pudo completar el proceso, occurio un error al insertar el detalle del asiento diferencia en cambio'
														);

														 $this->response($respuesta);

														 return;
											}
								}

							}
						}
						//FIN VALIDACION
						//FIN

						//AJUSTE AL PESO EN LA
						//MONEDA DEL SISTEMA
						$sqlDiffPeso =  "SELECT sum(coalesce(ac1_debit_sys,0)) as debito, sum(coalesce(ac1_credit_sys,0)) as credito,
														sum(coalesce(ac1_debit,0)) as ldebito, sum(coalesce(ac1_credit,0)) as lcredito
														from mac1
														where ac1_trans_id = :ac1_trans_id";

						$resDiffPeso = $this->pedeo->queryTable($sqlDiffPeso, array(
							':ac1_trans_id' => $resInsertAsiento
						));

						if( isset($resDiffPeso[0]['debito']) && abs(($resDiffPeso[0]['debito'] - $resDiffPeso[0]['credito'])) > 0 ){

							$sqlCuentaDiferenciaDecimal = "SELECT pge_acc_ajp FROM pgem";
							$resCuentaDiferenciaDecimal = $this->pedeo->queryTable($sqlCuentaDiferenciaDecimal, array());

							if(isset($resCuentaDiferenciaDecimal[0]) && is_numeric($resCuentaDiferenciaDecimal[0]['pge_acc_ajp'])){

							}else{

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $resCuentaDiferenciaDecimal,
									'mensaje'	=> 'No se encontro la cuenta para adicionar la diferencia en decimales'
								);

								 $this->response($respuesta);

								 return;
							}

							$debito  = $resDiffPeso[0]['debito'];
							$credito = $resDiffPeso[0]['credito'];

							if( $debito > $credito ){
								$credito = abs(($debito - $credito));
								$debito = 0;
							}else{
								$debito = abs(($credito - $debito));
								$credito = 0;
							}

							$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

										':ac1_trans_id' => $resInsertAsiento,
										':ac1_account' => $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'],
										':ac1_debit' => 0,
										':ac1_credit' => 0,
										':ac1_debit_sys' => round($debito, $DECI_MALES),
										':ac1_credit_sys' => round($credito, $DECI_MALES),
										':ac1_currex' => 0,
										':ac1_doc_date' => $this->validateDate($Data['crc_docdate'])?$Data['crc_docdate']:NULL,
										':ac1_doc_duedate' => $this->validateDate($Data['crc_docdate'])?$Data['crc_docdate']:NULL,
										':ac1_debit_import' => 0,
										':ac1_credit_import' => 0,
										':ac1_debit_importsys' => 0,
										':ac1_credit_importsys' => 0,
										':ac1_font_key' => $resInsert,
										':ac1_font_line' => 1,
										':ac1_font_type' => 22,
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
										':ac1_ven_debit' => 0,
										':ac1_ven_credit' => 0,
										':ac1_fiscal_acct' => 0,
										':ac1_taxid' => 1,
										':ac1_isrti' => 0,
										':ac1_basert' => 0,
										':ac1_mmcode' => 0,
										':ac1_legal_num' => isset($Data['crc_cardcode'])?$Data['crc_cardcode']:NULL,
										':ac1_codref' => 1,
										':ac1_line' => 1
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



						}else if( isset($resDiffPeso[0]['ldebito']) && abs(($resDiffPeso[0]['ldebito'] - $resDiffPeso[0]['lcredito'])) > 0 ){

							$sqlCuentaDiferenciaDecimal = "SELECT pge_acc_ajp FROM pgem";
							$resCuentaDiferenciaDecimal = $this->pedeo->queryTable($sqlCuentaDiferenciaDecimal, array());

							if(isset($resCuentaDiferenciaDecimal[0]) && is_numeric($resCuentaDiferenciaDecimal[0]['pge_acc_ajp'])){

							}else{

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $resCuentaDiferenciaDecimal,
									'mensaje'	=> 'No se encontro la cuenta para adicionar la diferencia en decimales'
								);

								 $this->response($respuesta);

								 return;
							}

							$ldebito  = $resDiffPeso[0]['ldebito'];
							$lcredito = $resDiffPeso[0]['lcredito'];

							if( $ldebito > $lcredito ){
								$lcredito = abs(($ldebito - $lcredito));
								$ldebito = 0;
							}else{
								$ldebito = abs(($lcredito - $ldebito));
								$lcredito = 0;
							}

							$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

										':ac1_trans_id' => $resInsertAsiento,
										':ac1_account' => $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'],
										':ac1_debit' => round($ldebito, $DECI_MALES),
										':ac1_credit' => round($lcredito, $DECI_MALES),
										':ac1_debit_sys' => 0,
										':ac1_credit_sys' => 0,
										':ac1_currex' => 0,
										':ac1_doc_date' => $this->validateDate($Data['crc_docdate'])?$Data['crc_docdate']:NULL,
										':ac1_doc_duedate' => $this->validateDate($Data['crc_docdate'])?$Data['crc_docdate']:NULL,
										':ac1_debit_import' => 0,
										':ac1_credit_import' => 0,
										':ac1_debit_importsys' => 0,
										':ac1_credit_importsys' => 0,
										':ac1_font_key' => $resInsert,
										':ac1_font_line' => 1,
										':ac1_font_type' => 22,
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
										':ac1_ven_debit' => 0,
										':ac1_ven_credit' => 0,
										':ac1_fiscal_acct' => 0,
										':ac1_taxid' => 1,
										':ac1_isrti' => 0,
										':ac1_basert' => 0,
										':ac1_mmcode' => 0,
										':ac1_legal_num' => isset($Data['crc_cardcode'])?$Data['crc_cardcode']:NULL,
										':ac1_codref' => 1,
										':ac1_line' => 1
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
						//


						// $sqlmac1 = "SELECT * FROM  mac1 order by ac1_line_num";
						// $ressqlmac1 = $this->pedeo->queryTable($sqlmac1, array());
						// print_r(json_encode($ressqlmac1));
						// exit;

						//SE VALIDA LA CONTABILIDAD CREADA
	 					$validateCont = $this->generic->validateAccountingAccent($resInsertAsiento);


	 					if( isset($validateCont['error']) && $validateCont['error'] == false ){

	 					}else{

	 							$this->pedeo->trans_rollback();

	 							$respuesta = array(
	 								'error'   => true,
	 								'data' 	 => '',
	 								'mensaje' => $validateCont['mensaje']
	 							);

	 							$this->response($respuesta);

	 							return;
	 					}
	 				 //




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

        $sqlSelect = "SELECT DISTINCT dcrc.*, tded.ded_description, tded.ded_id
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