<?php
// RECONCIALIACION BANCARIA
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class BankReconciliation extends REST_Controller {

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

  //CREAR NUEVA RECONCIALIACION BANCARIA
	public function createBankReconciliation_post(){

      $Data = $this->post();
			$DocNumVerificado = 0;

			$DetalleAsientoGasto = new stdClass();
			$DetalleAsientoImpuesto = new stdClass();
			$DetalleConsolidadoGasto = [];
			$DetalleConsolidadoImpuesto = [];
			$inArrayGasto = array();
			$inArrayImpuesto = array();
			$llaveGasto = "";
		  $llaveImpuesto = "";
			$posicionGasto = 0;
			$posicionImpuesto = 0;

			$AC1LINE = 1;
			$RequiereContabilidad = 0;
			$RequiereImpuesto = 0;
			$RequiereGasto = 0;
			$RequiereInteresBancario = 0;

			// Se globaliza la variable sqlDetalleAsiento
			$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
													ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
													ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
													ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, ac1_line, ac1_base_tax, ac1_codret)VALUES (:ac1_trans_id, :ac1_account,
													:ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
													:ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
													:ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
													:ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref,:ac1_line, :ac1_base_tax, :ac1_codret)";



			if(!isset($Data['detail'])){

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' =>'No se econtro el detalle'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}


			$ContenidoDetalle = json_decode($Data['detail'], true);


			if(!is_array($ContenidoDetalle)){
					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'No se encontro el detalle de la cotización'
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
			$periodo = $this->generic->ValidatePeriod($Data['crb_docdate'], $Data['crb_startdate'],$Data['crb_enddate'],0);

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

			$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['crb_series']));

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
			$resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $Data['crb_currency'], ':tsa_date' => $Data['crb_startdate']));

			if(isset($resBusTasa[0])){

			}else{

					if(trim($Data['crb_currency']) != $MONEDALOCAL ){

						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encrontro la tasa de cambio para la moneda: '.$Data['crb_currency'].' en la actual fecha del documento: '.$Data['crb_startdate'].' y la moneda local: '.$resMonedaLoc[0]['pgm_symbol']
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
					}
			}


			$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
			$resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $Data['crb_startdate']));

			if(isset($resBusTasa2[0])){

			}else{
					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :'.$Data['crb_startdate']
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
			}

			$TasaDocLoc = isset($resBusTasa[0]['tsa_value']) ? $resBusTasa[0]['tsa_value'] : 1;
			$TasaLocSys = $resBusTasa2[0]['tsa_value'];

			// FIN DEL PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO

			$sqlInsert = "INSERT INTO dcrb(crb_description, crb_docdate, crb_createby, crb_startdate, crb_enddate, crb_account, crb_gbaccount, crb_ivaccount, crb_cost, crb_tax, crb_posting_stardate, crb_posting_enddate, crb_docnum, crb_currency, crb_series, crb_cardcode, crb_doctype, crb_bankint)
										VALUES (:crb_description, :crb_docdate, :crb_createby, :crb_startdate, :crb_enddate, :crb_account, :crb_gbaccount, :crb_ivaccount, :crb_cost, :crb_tax, :crb_posting_stardate, :crb_posting_enddate, :crb_docnum, :crb_currency, :crb_series, :crb_cardcode, :crb_doctype, :crb_bankint)";


			// Se Inicia la transaccion,
			// Todas las consultas de modificacion siguientes
			// aplicaran solo despues que se confirme la transaccion,
			// de lo contrario no se aplicaran los cambios y se devolvera
			// la base de datos a su estado original.

			$this->pedeo->trans_begin();


			$resInsert = $this->pedeo->insertRow($sqlInsert, array(

				':crb_description' => $Data['crb_description'],
				':crb_docdate'		 => $Data['crb_docdate'],
				':crb_createby' 	 => $Data['crb_createby'],
				':crb_startdate'   => $Data['crb_startdate'],
				':crb_enddate'     => $Data['crb_enddate'],
				':crb_account'     => $Data['crb_account'],
				':crb_gbaccount'   => $Data['crb_gbaccount'],
				':crb_ivaccount'   => $Data['crb_ivaccount'],
				':crb_cost'     => $Data['crb_cost'],
				':crb_tax'     => $Data['crb_tax'],
				':crb_posting_stardate'     => $Data['crb_posting_stardate'],
				':crb_posting_enddate'     => $Data['crb_posting_enddate'],
				':crb_docnum' => $DocNumVerificado,
				':crb_currency' => $Data['crb_currency'],
				':crb_series' => $Data['crb_series'],
				':crb_cardcode' => $Data['crb_cardcode'],
				':crb_doctype' => $Data['crb_doctype'],
				':crb_bankint' => $Data['crb_bankint']
			));

			if(is_numeric($resInsert) && $resInsert > 0){

				// Se actualiza la serie de la numeracion del documento

				$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																		 WHERE pgs_id = :pgs_id";

				$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
						':pgs_nextnum' => $DocNumVerificado,
						':pgs_id'      => $Data['crb_series']
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

			  foreach ($ContenidoDetalle as $key => $detail) {



					$sqlInsertDetail = "INSERT INTO crb1(rb1_crbid, rb1_date, rb1_ref, rb1_debit, rb1_credit, rb1_gastob, rb1_imp, rb1_linenumcod, rb1_accounted)
														  VALUES (:rb1_crbid, :rb1_date, :rb1_ref, :rb1_debit, :rb1_credit, :rb1_gastob, :rb1_imp, :rb1_linenumcod, :rb1_accounted)";


					$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(

						':rb1_crbid'      => $resInsert,
						':rb1_date'       => $detail['rb1_date'],
						':rb1_ref'        => $detail['rb1_ref'],
						':rb1_debit'      => $detail['rb1_debit'],
						':rb1_credit'     => $detail['rb1_credit'],
						':rb1_gastob'     => $detail['rb1_gastob'],
						':rb1_imp'        => $detail['rb1_imp'],
						':rb1_linenumcod' => $detail['rb1_linenumcod'],
						':rb1_accounted'  => $detail['rb1_accounted']
					));


					if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
						// Se verifica que el detalle no de error insertando //
					}else{
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' => $resInsertDetail,
							'mensaje'	=> 'No se pudo registrar la conciliación de bancos'
						);

						 $this->response($respuesta);

						 return;
					}


					if ( $detail['rb1_gastob'] == 1 ){

						$RequiereContabilidad = 1;
						$RequiereGasto = 1;



					}

					if ( $detail['rb1_imp'] == 1 ){

						$RequiereContabilidad = 1;
						$RequiereImpuesto = 1;

					}

					if ( $detail['rb1_imp'] == 1 ) {

						$RequiereContabilidad = 1;
						$RequiereInteresBancario = 1;

					}
				}


				// SE VALIDAD QUE SEA NECESARIO CREAR LA CONTABILIDAD





				if ( $RequiereContabilidad == 1 ){
					//CABECERA ASIENTO
					//Se agregan los asientos contables*/*******

					$sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user, mac_currency)
															 VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user, :mac_currency)";


					$resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

							':mac_doc_num' => 1,
							':mac_status' => 1,
							':mac_base_type' => is_numeric($Data['crb_doctype'])?$Data['crb_doctype']:0,
							':mac_base_entry' => $resInsert,
							':mac_doc_date' => $this->validateDate($Data['crb_docdate'])?$Data['crb_docdate']:NULL,
							':mac_doc_duedate' => $this->validateDate($Data['crb_enddate'])?$Data['crb_enddate']:NULL,
							':mac_legal_date' => $this->validateDate($Data['crb_startdate'])?$Data['crb_startdate']:NULL,
							':mac_ref1' => is_numeric($Data['crb_doctype'])?$Data['crb_doctype']:0,
							':mac_ref2' => "",
							':mac_ref3' => "",
							':mac_loc_total' => 0,
							':mac_fc_total' => 0,
							':mac_sys_total' => 0,
							':mac_trans_dode' => 1,
							':mac_beline_nume' => 1,
							':mac_vat_date' => $this->validateDate($Data['crb_startdate'])?$Data['crb_startdate']:NULL,
							':mac_serie' => 1,
							':mac_number' => 1,
							':mac_bammntsys' => 0,
							':mac_bammnt' => 0,
							':mac_wtsum' => 1,
							':mac_vatsum' => 0,
							':mac_comments' => isset($Data['crb_description'])?$Data['crb_description']:NULL,
							':mac_create_date' => $this->validateDate($Data['crb_docdate'])?$Data['crb_docdate']:NULL,
							':mac_made_usuer' => isset($Data['crb_createby'])?$Data['crb_createby']:NULL,
							':mac_update_date' => date("Y-m-d"),
							':mac_update_user' => isset($Data['crb_createby'])?$Data['crb_createby']:NULL,
							':mac_currency' => isset($Data['crb_currency'])?$Data['crb_currency']:NULL
					));


					if(is_numeric($resInsertAsiento) && $resInsertAsiento > 0){
							// Se verifica que el detalle no de error insertando //
					}else{

							// si falla algun insert del detalle de la factura de compras se devuelven los cambios realizados por la transaccion,
							// se retorna el error y se detiene la ejecucion del codigo restante.
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $resInsertAsiento,
									'mensaje'	=> 'No se pudo registrar la conciliación de bancos'
								);

								 $this->response($respuesta);

								 return;
					}

					// GENERANDO ASIENTOS contables

					//GASTOS

					$MontoSysCR = 0;
					$MontoSysDB = 0;
					if ( $RequiereGasto == 1 ) {
							$granTotalGasto = $Data['crb_cost'];
							$granTotalGastoOriginal = $Data['crb_cost'];
							$Sn = $Data['crb_cardcode'];
							$Cuenta = $Data['crb_gbaccount'];



							if(trim($Data['crb_currency']) != $MONEDALOCAL ){
									$granTotalGasto = ($granTotalGasto * $TasaDocLoc);
							}


							if(trim($Data['crb_currency']) != $MONEDASYS ){

									$MontoSysDB = ($granTotalGasto / $TasaLocSys);

							}else{
									$MontoSysDB = $granTotalGastoaOriginal;
							}


							$RequiereGasto = 1;

							$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

									':ac1_trans_id' => $resInsertAsiento,
									':ac1_account' => $Cuenta,
									':ac1_debit' => round($granTotalGasto, 2),
									':ac1_credit' => 0,
									':ac1_debit_sys' => round($MontoSysDB, 2),
									':ac1_credit_sys' => 0,
									':ac1_currex' => 0,
									':ac1_doc_date' => $this->validateDate($Data['crb_startdate'])?$Data['crb_startdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['crb_enddate'])?$Data['crb_enddate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => $resInsert,
									':ac1_font_line' => 1,
									':ac1_font_type' => is_numeric($Data['crb_doctype'])?$Data['crb_doctype']:0,
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
									':ac1_made_user' => isset($Data['crb_createby'])?$Data['crb_createby']:NULL,
									':ac1_accperiod' => 1,
									':ac1_close' => 0,
									':ac1_cord' => 0,
									':ac1_ven_debit' => 0,
									':ac1_ven_credit' => 0,
									':ac1_fiscal_acct' => 0,
									':ac1_taxid' => 0,
									':ac1_isrti' => 0,
									':ac1_basert' => 0,
									':ac1_mmcode' => 0,
									':ac1_legal_num' => $Sn,
									':ac1_codref' => 1,
									':ac1_line' => 0,
									':ac1_base_tax' => 0,
									':ac1_codret' => 0
						));



						if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
						}else{

								// si falla algun insert del detalle de la factura de compras se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resDetalleAsiento,
										'mensaje'	=> 'No se pudo registrar la conciliación de bancos'
									);

									 $this->response($respuesta);

									 return;
						}
					}

					//FIN Procedimiento para llenar GASTOS

					//IMPUESTOS

					$MontoSysCR = 0;
					$MontoSysDB = 0;
					if ( $RequiereImpuesto == 1 ) {
							$granTotalImpuesto = $Data['crb_tax'];
							$granTotalImpuestoOriginal = $Data['crb_tax'];
							$Sn = $Data['crb_cardcode'];
							$Cuenta = $Data['crb_ivaccount'];


							if(trim($Data['crb_currency']) != $MONEDALOCAL ){
									$granTotalImpuesto = ($granTotalImpuesto * $TasaDocLoc);
							}


							if(trim($Data['crb_currency']) != $MONEDASYS ){

									$MontoSysDB = ($granTotalImpuesto / $TasaLocSys);

							}else{
									$MontoSysDB = $granTotalImpuestoOriginal;
							}

							$RequiereImpuesto = 1;

							$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

									':ac1_trans_id' => $resInsertAsiento,
									':ac1_account' => $Cuenta,
									':ac1_debit' => round($granTotalImpuesto, 2),
									':ac1_credit' => 0,
									':ac1_debit_sys' => round($MontoSysDB, 2),
									':ac1_credit_sys' => 0,
									':ac1_currex' => 0,
									':ac1_doc_date' => $this->validateDate($Data['crb_startdate'])?$Data['crb_startdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['crb_enddate'])?$Data['crb_enddate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => $resInsert,
									':ac1_font_line' => 1,
									':ac1_font_type' => is_numeric($Data['crb_doctype'])?$Data['crb_doctype']:0,
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
									':ac1_made_user' => isset($Data['crb_createby'])?$Data['crb_createby']:NULL,
									':ac1_accperiod' => 1,
									':ac1_close' => 0,
									':ac1_cord' => 0,
									':ac1_ven_debit' => 0,
									':ac1_ven_credit' => 0,
									':ac1_fiscal_acct' => 0,
									':ac1_taxid' => 0,
									':ac1_isrti' => 0,
									':ac1_basert' => 0,
									':ac1_mmcode' => 0,
									':ac1_legal_num' => $Sn,
									':ac1_codref' => 1,
									':ac1_line' => 0,
									':ac1_base_tax' => 0,
									':ac1_codret' => 0
						));



						if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
						}else{

								// si falla algun insert del detalle de la factura de compras se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resDetalleAsiento,
										'mensaje'	=> 'No se pudo registrar la conciliación de bancos'
									);

									 $this->response($respuesta);

									 return;
						}
					}

					//FIN Procedimiento para llenar IMPUESTOS


					//INTERESES BANCARIOS

					$MontoSysCR = 0;
					$MontoSysDB = 0;
					if ( $RequiereInteresBancario == 1 ) {
							$granTotalInteres = $Data['crb_bankint'];
							$granTotalInteresOriginal = $Data['crb_bankint'];
							$Sn = $Data['crb_cardcode'];
							$Cuenta = $Data['crb_inbccount'];


							if(trim($Data['crb_currency']) != $MONEDALOCAL ){
									$granTotalInteres = ($granTotalInteres * $TasaDocLoc);
							}


							if(trim($Data['crb_currency']) != $MONEDASYS ){

									$MontoSysCR = ($granTotalInteres / $TasaLocSys);

							}else{
									$MontoSysCR = $granTotalInteresOriginal;
							}

							$RequiereImpuesto = 1;

							$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

									':ac1_trans_id' => $resInsertAsiento,
									':ac1_account' => $Cuenta,
									':ac1_debit' => 0,
									':ac1_credit' => round($granTotalInteres, 2),
									':ac1_debit_sys' => 0,
									':ac1_credit_sys' =>  round($MontoSysCR, 2),
									':ac1_currex' => 0,
									':ac1_doc_date' => $this->validateDate($Data['crb_startdate'])?$Data['crb_startdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['crb_enddate'])?$Data['crb_enddate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => $resInsert,
									':ac1_font_line' => 1,
									':ac1_font_type' => is_numeric($Data['crb_doctype'])?$Data['crb_doctype']:0,
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
									':ac1_made_user' => isset($Data['crb_createby'])?$Data['crb_createby']:NULL,
									':ac1_accperiod' => 1,
									':ac1_close' => 0,
									':ac1_cord' => 0,
									':ac1_ven_debit' => 0,
									':ac1_ven_credit' => 0,
									':ac1_fiscal_acct' => 0,
									':ac1_taxid' => 0,
									':ac1_isrti' => 0,
									':ac1_basert' => 0,
									':ac1_mmcode' => 0,
									':ac1_legal_num' => $Sn,
									':ac1_codref' => 1,
									':ac1_line' => 0,
									':ac1_base_tax' => 0,
									':ac1_codret' => 0
						));



						if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
						}else{

								// si falla algun insert del detalle de la factura de compras se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resDetalleAsiento,
										'mensaje'	=> 'No se pudo registrar la conciliación de bancos'
									);

									 $this->response($respuesta);

									 return;
						}
					}

					//FIN PROCEDIMIENTO  PARA LLENAR INTERESES BANCARIOS

					// CUENTA DE BANCO
					$MontoSysCR = 0;
					$MontoSysDB = 0;
					if ( $RequiereGasto == 1 || $RequiereImpuesto == 1 ){

							$monto = ( $Data['crb_cost'] + $Data['crb_tax'] );
							$montoOriginal = $monto;
							$Cuenta = $Data['crb_account'];


							if(trim($Data['crb_currency']) != $MONEDALOCAL ){
									$monto = ($monto * $TasaDocLoc);
							}


							if(trim($Data['crb_currency']) != $MONEDASYS ){

									$MontoSysCR = ($monto / $TasaLocSys);

							}else{
									$MontoSysCR = $montoOriginal;
							}



							$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

									':ac1_trans_id' => $resInsertAsiento,
									':ac1_account' => $Cuenta,
									':ac1_debit' => 0,
									':ac1_credit' => round($monto, 2),
									':ac1_debit_sys' => 0,
									':ac1_credit_sys' => round($MontoSysCR, 2),
									':ac1_currex' => 0,
									':ac1_doc_date' => $this->validateDate($Data['crb_startdate'])?$Data['crb_startdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['crb_enddate'])?$Data['crb_enddate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => $resInsert,
									':ac1_font_line' => 1,
									':ac1_font_type' => is_numeric($Data['crb_doctype'])?$Data['crb_doctype']:0,
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
									':ac1_made_user' => isset($Data['crb_createby'])?$Data['crb_createby']:NULL,
									':ac1_accperiod' => 1,
									':ac1_close' => 0,
									':ac1_cord' => 0,
									':ac1_ven_debit' => 0,
									':ac1_ven_credit' => 0,
									':ac1_fiscal_acct' => 0,
									':ac1_taxid' => 0,
									':ac1_isrti' => 0,
									':ac1_basert' => 0,
									':ac1_mmcode' => 0,
									':ac1_legal_num' => $Sn,
									':ac1_codref' => 1,
									':ac1_line' => 0,
									':ac1_base_tax' => 0,
									':ac1_codret' => 0
						));



						if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
						}else{

								// si falla algun insert del detalle de la factura de compras se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resDetalleAsiento,
										'mensaje'	=> 'No se pudo registrar la conciliación de bancos'
									);

									 $this->response($respuesta);

									 return;
						}
					}

					//FIN  CUENTA DE BANCO

					// CUENTA DE BANCO CONTRAPARTIDA DE INTERESES BANCARIOS
					$MontoSysCR = 0;
					$MontoSysDB = 0;
					if ( $RequiereInteresBancario == 1 ){

							$monto = $Data['crb_bankint'];
							$montoOriginal = $monto;
							$Cuenta = $Data['crb_account'];


							if(trim($Data['crb_currency']) != $MONEDALOCAL ){
									$monto = ($monto * $TasaDocLoc);
							}


							if(trim($Data['crb_currency']) != $MONEDASYS ){

									$MontoSysDB = ($monto / $TasaLocSys);

							}else{
									$MontoSysDB = $montoOriginal;
							}



							$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

									':ac1_trans_id' => $resInsertAsiento,
									':ac1_account' => $Cuenta,
									':ac1_debit' =>  round($monto, 2),
									':ac1_credit' => 0,
									':ac1_debit_sys' => round($MontoSysDB, 2),
									':ac1_credit_sys' => 0,
									':ac1_currex' => 0,
									':ac1_doc_date' => $this->validateDate($Data['crb_startdate'])?$Data['crb_startdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['crb_enddate'])?$Data['crb_enddate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => $resInsert,
									':ac1_font_line' => 1,
									':ac1_font_type' => is_numeric($Data['crb_doctype'])?$Data['crb_doctype']:0,
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
									':ac1_made_user' => isset($Data['crb_createby'])?$Data['crb_createby']:NULL,
									':ac1_accperiod' => 1,
									':ac1_close' => 0,
									':ac1_cord' => 0,
									':ac1_ven_debit' => 0,
									':ac1_ven_credit' => 0,
									':ac1_fiscal_acct' => 0,
									':ac1_taxid' => 0,
									':ac1_isrti' => 0,
									':ac1_basert' => 0,
									':ac1_mmcode' => 0,
									':ac1_legal_num' => $Sn,
									':ac1_codref' => 1,
									':ac1_line' => 0,
									':ac1_base_tax' => 0,
									':ac1_codret' => 0
						));



						if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
						}else{

								// si falla algun insert del detalle de la factura de compras se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resDetalleAsiento,
										'mensaje'	=> 'No se pudo registrar la conciliación de bancos'
									);

									 $this->response($respuesta);

									 return;
						}
					}

					//FIN  CUENTA DE BANCO CONTRA PARTIDA DE INTERESES
					//


					//VALIDANDDO DIFERENCIA EN PESO DE MONEDA DE SISTEMA

					$sqlDiffPeso = "SELECT sum(coalesce(ac1_debit_sys,0)) as debito, sum(coalesce(ac1_credit_sys,0)) as credito,
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
									':ac1_debit_sys' => round($debito, 2),
									':ac1_credit_sys' => round($credito, 2),
									':ac1_currex' => 0,
									':ac1_doc_date' => $this->validateDate($Data['crb_startdate'])?$Data['crb_startdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['crb_enddate'])?$Data['crb_enddate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => $resInsert,
									':ac1_font_line' => 1,
									':ac1_font_type' => is_numeric($Data['crb_doctype'])?$Data['crb_doctype']:0,
									':ac1_accountvs' => 1,
									':ac1_doctype' => 18,
									':ac1_ref1' => "",
									':ac1_ref2' => "",
									':ac1_ref3' => "",
									':ac1_prc_code' => 0,
									':ac1_uncode' => 0,
									':ac1_prj_code' => NULL,
									':ac1_rescon_date' => NULL,
									':ac1_recon_total' => 0,
									':ac1_made_user' => isset($Data['crb_createby'])?$Data['crb_createby']:NULL,
									':ac1_accperiod' => 1,
									':ac1_close' => 0,
									':ac1_cord' => 0,
									':ac1_ven_debit' => 0,
									':ac1_ven_credit' => 0,
									':ac1_fiscal_acct' => 0,
									':ac1_taxid' => 0,
									':ac1_isrti' => 0,
									':ac1_basert' => 0,
									':ac1_mmcode' => 0,
									':ac1_legal_num' => isset($Data['crb_cardcode'])?$Data['crb_cardcode']:NULL,
									':ac1_codref' => 1,
									':ac1_line' => 0,
									':ac1_base_tax' => 0,
									':ac1_codret' => 0
						));



						if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
						}else{
								// si falla algun insert del detalle  se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resDetalleAsiento,
										'mensaje'	=> 'No se pudo registrar la conciliación de bancos, occurio un error al insertar el detalle del asiento diferencia en cambio'
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
									':ac1_debit' => round($ldebito, 2),
									':ac1_credit' => round($lcredito, 2),
									':ac1_debit_sys' => 0,
									':ac1_credit_sys' => 0,
									':ac1_currex' => 0,
									':ac1_doc_date' => $this->validateDate($Data['crb_startdate'])?$Data['crb_startdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['crb_enddate'])?$Data['crb_enddate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => $resInsert,
									':ac1_font_line' => 1,
									':ac1_font_type' => is_numeric($Data['crb_doctype'])?$Data['crb_doctype']:0,
									':ac1_accountvs' => 1,
									':ac1_doctype' => 18,
									':ac1_ref1' => "",
									':ac1_ref2' => "",
									':ac1_ref3' => "",
									':ac1_prc_code' => 0,
									':ac1_uncode' => 0,
									':ac1_prj_code' => NULL,
									':ac1_rescon_date' => NULL,
									':ac1_recon_total' => 0,
									':ac1_made_user' => isset($Data['crb_createby'])?$Data['crb_createby']:NULL,
									':ac1_accperiod' => 1,
									':ac1_close' => 0,
									':ac1_cord' => 0,
									':ac1_ven_debit' => 0,
									':ac1_ven_credit' => 0,
									':ac1_fiscal_acct' => 0,
									':ac1_taxid' => 0,
									':ac1_isrti' => 0,
									':ac1_basert' => 0,
									':ac1_mmcode' => 0,
									':ac1_legal_num' => isset($Data['crb_cardcode'])?$Data['crb_cardcode']:NULL,
									':ac1_codref' => 1,
									':ac1_line' => 0,
									':ac1_base_tax' => 0,
									':ac1_codret' => 0
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
										'mensaje'	=> 'No se pudo registrar la conciliación de bancos, occurio un error al insertar el detalle del asiento diferencia en cambio'
									);

									 $this->response($respuesta);

									 return;
						}
					}

					// $sqlmac1 = "SELECT * FROM  mac1  where ac1_trans_id = :ac1_trans_id ";
					// $ressqlmac1 = $this->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $resInsertAsiento));
					// print_r(json_encode($ressqlmac1));
					// exit;


					//SE VALIDA LA CONTABILIDAD CREADA
					$validateCont = $this->generic->validateAccountingAccent($resInsertAsiento);


					if( isset($validateCont['error']) && $validateCont['error'] == false ){

					}else{

						 $this->pedeo->trans_rollback();

						 $respuesta = array(
							 'error'   => true,
							 'data' 	 => $validateCont['data'],
							 'mensaje' => $validateCont['mensaje']
						 );

						 $this->response($respuesta);

						 return;
					}
				}

			  //


				$this->pedeo->trans_commit();

				$respuesta = array(
					'error' => false,
					'data' => $resInsert,
					'mensaje' =>'Conciliación registrada con exito'
				);

			}else{
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'    => $resInsert,
					'mensaje'	=> 'No se pudo crear la conciliación de bancos'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}



      $this->response($respuesta);
	}

	//OBTENER RECONCIALIACIONES REALIZADAS
	public function getBankReconciliation_get(){

		$sqlSelect = "SELECT *
									FROM dcrb";

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




  // Obtener Cuentas de Banco
  public function getBankAccounts_get(){

        $sqlSelect = "SELECT * FROM dacc WHERE acc_cash = 1 AND acc_digit = 10 AND cast(acc_code AS VARCHAR)  LIKE '1101021%' ORDER BY acc_code ASC";

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

	// OBTENER MOVIMIENTO DE CUENTAS POR RANGO DE FECHAS
	public function getAccountRange_get(){

				$Data = $this->get();

				if(!isset($Data['startdate']) OR ! isset($Data['enddate']) OR !isset($Data['account'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'Faltan parametros'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = "SELECT mac1.ac1_trans_id as id,
											mac1.ac1_doc_date as fecha,
											mac1.ac1_debit as debit,
											mac1.ac1_credit as credit,
											tmac.mac_base_type as basetype,
											tmac.mac_base_entry as baseentry,
											gbpr.bpr_reftransfer as ref,
											mac1.ac1_line_num as refunica
											FROM gbpr
											INNER JOIN tmac
											ON tmac.mac_base_type = gbpr.bpr_doctype AND tmac.mac_base_entry = gbpr.bpr_docentry
											INNER JOIN mac1
											ON mac1.ac1_trans_id = tmac.mac_trans_id
											WHERE ac1_account = :ac1_account AND ac1_doc_date BETWEEN  :startdate AND :enddate
											UNION ALL
											SELECT mac1.ac1_trans_id as id,
											mac1.ac1_doc_date as fecha,
											mac1.ac1_debit as debit,
											mac1.ac1_credit as credit,
											tmac.mac_base_type as basetype,
											tmac.mac_base_entry as baseentry,
											gbpe.bpe_reftransfer as ref,
											mac1.ac1_line_num as refunica
											FROM gbpe
											INNER JOIN tmac
											ON tmac.mac_base_type = gbpe.bpe_doctype AND tmac.mac_base_entry = gbpe.bpe_docentry
											INNER JOIN mac1
											ON mac1.ac1_trans_id = tmac.mac_trans_id
											WHERE ac1_account = :ac1_account AND ac1_doc_date BETWEEN  :startdate AND :enddate";

				// $sqlSelect = "SELECT * FROM mac1 WHERE ac1_account = :ac1_account AND ac1_doc_date BETWEEN :startdate AND :enddate ";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(
					':ac1_account' => $Data['account'],
					':startdate'   => $Data['startdate'],
					':enddate' 		 => $Data['enddate']
				));

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


	public function getExpenseCostAcct_get(){

		$sqlSelect = "SELECT *
									FROM dacc
									WHERE acc_cash = 0
									AND acc_digit = 10
									AND acc_code > 3999999999
									ORDER BY acc_code ASC";

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
