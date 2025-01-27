<?php
// RECONCIALIACION BANCARIA
defined('BASEPATH') OR exit('No direct script access allowed');
// exit;
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
		$this->load->library('account');

	}

  //CREAR NUEVA RECONCIALIACION BANCARIA
	public function createBankReconciliation_post() {

			$DECI_MALES =  $this->generic->getDecimals();
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
													ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, ac1_line, ac1_base_tax, ac1_codret, business, branch)VALUES (:ac1_trans_id, :ac1_account,
													:ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
													:ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
													:ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
													:ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref,:ac1_line, :ac1_base_tax, :ac1_codret, :business, :branch)";



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
			$ConceptosBancarios = json_decode($Data['crb_concepts'],true);


			if(!is_array($ContenidoDetalle)){
					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'No se encontro el detalle de la reconciliación'
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
			$periodo = $this->generic->ValidatePeriod($Data['crb_docdate'], $Data['crb_posting_stardate'],$Data['crb_posting_enddate'],0);

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
			$resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $Data['crb_currency'], ':tsa_date' => $Data['crb_posting_stardate']));

			if(isset($resBusTasa[0])){

			}else{

					if(trim($Data['crb_currency']) != $MONEDALOCAL ){

						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encrontro la tasa de cambio para la moneda: '.$Data['crb_currency'].' en la actual fecha del documento: '.$Data['crb_posting_stardate'].' y la moneda local: '.$resMonedaLoc[0]['pgm_symbol']
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
					}
			}


			$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
			$resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $Data['crb_posting_stardate']));

			if(isset($resBusTasa2[0])){

			}else{
					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :'.$Data['crb_posting_stardate']
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
			}

			$TasaDocLoc = isset($resBusTasa[0]['tsa_value']) ? $resBusTasa[0]['tsa_value'] : 1;
			$TasaLocSys = $resBusTasa2[0]['tsa_value'];

			// FIN DEL PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO

			$sqlInsert = "INSERT INTO dcrb(crb_description, crb_docdate, crb_createby, crb_startdate, crb_enddate, crb_account, crb_gbaccount, crb_ivaccount, crb_cost, crb_tax, crb_posting_stardate, crb_posting_enddate, crb_docnum, crb_currency, crb_series, crb_cardcode, crb_doctype, crb_bankint, business, branch)
						VALUES (:crb_description, :crb_docdate, :crb_createby, :crb_startdate, :crb_enddate, :crb_account, :crb_gbaccount, :crb_ivaccount, :crb_cost, :crb_tax, :crb_posting_stardate, :crb_posting_enddate, :crb_docnum, :crb_currency, :crb_series, :crb_cardcode, :crb_doctype, :crb_bankint, :business, :branch)";


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
				':crb_gbaccount'   => is_numeric($Data['crb_gbaccount']) ? $Data['crb_gbaccount'] : NULL,
				':crb_ivaccount'   => is_numeric($Data['crb_ivaccount']) ? $Data['crb_ivaccount'] : NULL,
				':crb_cost'     => is_numeric($Data['crb_cost']) ? $Data['crb_cost'] : NULL,
				':crb_tax'     => is_numeric($Data['crb_tax']) ? $Data['crb_tax'] : NULL,
				':crb_posting_stardate'     => $Data['crb_posting_stardate'],
				':crb_posting_enddate'     => $Data['crb_posting_enddate'],
				':crb_docnum' => $DocNumVerificado,
				':crb_currency' => $Data['crb_currency'],
				':crb_series' => $Data['crb_series'],
				':crb_cardcode' => $Data['crb_cardcode'],
				':crb_doctype' => $Data['crb_doctype'],
				':crb_bankint' => is_numeric($Data['crb_bankint'])?$Data['crb_bankint']: 0,
				':business' => $Data['business'], 
				':branch' => $Data['branch']
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





				if (isset($ConceptosBancarios[0])){
					//CABECERA ASIENTO
					//Se agregan los asientos contables*/*******

					$sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user, mac_currency,mac_accperiod, business, branch)
															 VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user, :mac_currency,:mac_accperiod, :business, :branch)";


					$resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

							':mac_doc_num' => 1,
							':mac_status' => 1,
							':mac_base_type' => is_numeric($Data['crb_doctype'])?$Data['crb_doctype']:0,
							':mac_base_entry' => $resInsert,
							':mac_doc_date' => $this->validateDate($Data['crb_posting_stardate'])?$Data['crb_posting_stardate']:NULL,
							':mac_doc_duedate' => $this->validateDate($Data['crb_posting_enddate'])?$Data['crb_posting_enddate']:NULL,
							':mac_legal_date' => $this->validateDate($Data['crb_posting_stardate'])?$Data['crb_posting_stardate']:NULL,
							':mac_ref1' => is_numeric($Data['crb_doctype'])?$Data['crb_doctype']:0,
							':mac_ref2' => "",
							':mac_ref3' => "",
							':mac_loc_total' => 0,
							':mac_fc_total' => 0,
							':mac_sys_total' => 0,
							':mac_trans_dode' => 1,
							':mac_beline_nume' => 1,
							':mac_vat_date' => $this->validateDate($Data['crb_posting_stardate'])?$Data['crb_posting_stardate']:NULL,
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
							':mac_currency' => isset($Data['crb_currency'])?$Data['crb_currency']:NULL,
							':mac_accperiod' => $periodo['data'],
							':business' => $Data['business'], 
							':branch' => $Data['branch']
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
					$creditoAcum = 0;
					$debitoAcum = 0;
					foreach ($ConceptosBancarios as $key => $concepto) {
						$MontoSysCR = 0;
						$MontoSysDB = 0;
						
						$sqlConcept = "SELECT icm_cord, icm_acctcode FROM micm where icm_id = :icm_id";
						$resConcept = $this->pedeo->queryTable($sqlConcept, array(":icm_id" => $concepto['id']));

						if(isset($resConcept[0])){
							$naturaleza = $resConcept[0]['icm_cord'];
							$costo = $concepto['value'];
							$costoOriginal = $concepto['value'];
							$Sn = $Data['crb_cardcode'];				
							$accountCode = $resConcept[0]['icm_acctcode'];
							$cordTypeNumber = ($naturaleza == "DEBIT") ? 1 : 2 ;

							if(trim($Data['crb_currency']) != $MONEDALOCAL ){
								$costo = ($costo * $TasaDocLoc);
								}


							if(trim($Data['crb_currency']) != $MONEDASYS ){
								$valueCalculated = ($costo / $TasaLocSys);
								$MontoSysDB = ($naturaleza == "DEBIT") ? $valueCalculated : 0;
								$MontoSysCR = ($naturaleza == "CREDIT") ? $valueCalculated : 0;
							}else{
									$MontoSysDB = ($naturaleza == "DEBIT") ? $costoOriginal : 0;
									$MontoSysCR = ($naturaleza == "CREDIT") ? $costoOriginal : 0;
							}

							$BALANCE = $this->account->addBalance($periodo['data'], round($costo, $DECI_MALES), $accountCode, $cordTypeNumber, $Data['crb_posting_stardate'], $Data['business'], $Data['branch']);
							
							if (isset($BALANCE['error']) && $BALANCE['error'] == true){

								$this->pedeo->trans_rollback();
	
								$respuesta = array(
									'error' => true,
									'data' => $BALANCE,
									'mensaje' => $BALANCE['mensaje']
								);
		
								return $this->response($respuesta);
							}

							if($naturaleza == 'CREDIT'){
								$creditoAcum = $creditoAcum + $costo;
							}

							if($naturaleza == 'DEBIT'){
								$debitoAcum = $debitoAcum + $costo;
							}
							
							$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

									':ac1_trans_id' => $resInsertAsiento,
									':ac1_account' => $accountCode,
									':ac1_debit' =>($naturaleza == "DEBIT") ? round($costo, $DECI_MALES) : 0,
									':ac1_credit' =>($naturaleza == "CREDIT") ? round($costo, $DECI_MALES) : 0,
									':ac1_debit_sys' => ($MontoSysDB > 0)? round($MontoSysDB, $DECI_MALES) : 0,
									':ac1_credit_sys' => ($MontoSysCR > 0)? round($MontoSysCR, $DECI_MALES): 0,
									':ac1_currex' => 0,
									':ac1_doc_date' => $this->validateDate($Data['crb_posting_stardate'])?$Data['crb_posting_stardate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['crb_posting_enddate'])?$Data['crb_posting_enddate']:NULL,
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
									':ac1_accperiod' => $periodo['data'],
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
									':ac1_codret' => 0,
									':business' => $Data['business'], 
									':branch' => $Data['branch']
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
					}
				}

					// // CUENTA DE BANCO
					$MontoSysCR = 0;
					$MontoSysDB = 0;
					if ( true){

							$monto = $debitoAcum;
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

							// SE AGREGA AL BALANCE

							$BALANCE = $this->account->addBalance($periodo['data'], round($monto, $DECI_MALES), $Cuenta, 2, $Data['crb_posting_stardate'], $Data['business'], $Data['branch']);
							if (isset($BALANCE['error']) && $BALANCE['error'] == true){

								$this->pedeo->trans_rollback();
	
								$respuesta = array(
									'error' => true,
									'data' => $BALANCE,
									'mensaje' => $BALANCE['mensaje']
								);
		
								return $this->response($respuesta);
							}	
							//

							$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

									':ac1_trans_id' => $resInsertAsiento,
									':ac1_account' => $Cuenta,
									':ac1_debit' => 0,
									':ac1_credit' => round($monto, $DECI_MALES),
									':ac1_debit_sys' => 0,
									':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
									':ac1_currex' => 0,
									':ac1_doc_date' => $this->validateDate($Data['crb_posting_stardate'])?$Data['crb_posting_stardate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['crb_posting_enddate'])?$Data['crb_posting_enddate']:NULL,
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
									':ac1_accperiod' => $periodo['data'],
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
									':ac1_codret' => 0,
									':business' => $Data['business'], 
									':branch' => $Data['branch']
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

					// //FIN  CUENTA DE BANCO

					// CUENTA DE BANCO CONTRAPARTIDA DE BANCOS el lado del debito
					$MontoSysCR = 0;
					$MontoSysDB = 0;
					if ( $creditoAcum > 0 ){

							$monto = $creditoAcum;
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

							// SE AGREGA AL BALANCE

							$BALANCE = $this->account->addBalance($periodo['data'], round($monto, $DECI_MALES), $Cuenta, 1, $Data['crb_posting_stardate'], $Data['business'], $Data['branch']);
							if (isset($BALANCE['error']) && $BALANCE['error'] == true){

								$this->pedeo->trans_rollback();
	
								$respuesta = array(
									'error' => true,
									'data' => $BALANCE,
									'mensaje' => $BALANCE['mensaje']
								);
		
								return $this->response($respuesta);
							}	
							//

							$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

									':ac1_trans_id' => $resInsertAsiento,
									':ac1_account' => $Cuenta,
									':ac1_debit' =>  round($monto, $DECI_MALES),
									':ac1_credit' => 0,
									':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
									':ac1_credit_sys' => 0,
									':ac1_currex' => 0,
									':ac1_doc_date' => $this->validateDate($Data['crb_posting_stardate'])?$Data['crb_posting_stardate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['crb_posting_enddate'])?$Data['crb_posting_enddate']:NULL,
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
									':ac1_accperiod' => $periodo['data'],
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
									':ac1_codret' => 0,
									':business' => $Data['business'], 
									':branch' => $Data['branch']
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
									':ac1_debit_sys' => round($debito, $DECI_MALES),
									':ac1_credit_sys' => round($credito, $DECI_MALES),
									':ac1_currex' => 0,
									':ac1_doc_date' => $this->validateDate($Data['crb_posting_stardate'])?$Data['crb_posting_stardate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['crb_posting_enddate'])?$Data['crb_posting_enddate']:NULL,
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
									':ac1_accperiod' => $periodo['data'],
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
									':ac1_codret' => 0,
									':business' => $Data['business'], 
									':branch' => $Data['branch']
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

						// SE AGREGA AL BALANCE
						if ( $ldebito > 0 ){
							$BALANCE = $this->account->addBalance($periodo['data'], round($ldebito, $DECI_MALES), $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'], 1, $Data['crb_posting_stardate'], $Data['business'], $Data['branch']);
						}else{
							$BALANCE = $this->account->addBalance($periodo['data'], round($lcredito, $DECI_MALES), $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'], 2, $Data['crb_posting_stardate'], $Data['business'], $Data['branch']);
						}
						if (isset($BALANCE['error']) && $BALANCE['error'] == true){

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error' => true,
								'data' => $BALANCE,
								'mensaje' => $BALANCE['mensaje']
							);
	
							return $this->response($respuesta);
						}	

						//

						$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

									':ac1_trans_id' => $resInsertAsiento,
									':ac1_account' => $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'],
									':ac1_debit' => round($ldebito, $DECI_MALES),
									':ac1_credit' => round($lcredito, $DECI_MALES),
									':ac1_debit_sys' => 0,
									':ac1_credit_sys' => 0,
									':ac1_currex' => 0,
									':ac1_doc_date' => $this->validateDate($Data['crb_posting_stardate'])?$Data['crb_posting_stardate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['crb_posting_enddate'])?$Data['crb_posting_enddate']:NULL,
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
									':ac1_accperiod' => $periodo['data'],
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
									':ac1_codret' => 0,
									':business' => $Data['business'], 
									':branch' => $Data['branch']
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

						$ressqlmac1 = [];
						$sqlmac1 = "SELECT acc_name, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys FROM  mac1 inner join dacc on ac1_account = acc_code WHERE ac1_trans_id = :ac1_trans_id";
						$ressqlmac1['contabilidad'] = $this->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $resInsertAsiento ));

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' 	  => $ressqlmac1,
							'mensaje' => $validateCont['mensaje'],
							
						);

						$this->response($respuesta);

						return;
					}
				// }

			  //
				$sqlInsertDetail2 = "INSERT INTO crb2 (rb2_date, rb2_ref, rb2_amuont, rb2_expense, rb2_tax, rb2_bankinterest, rb2_docentry, rb2_linestatus, rb2_concept) 
				values (:rb2_date, :rb2_ref, :rb2_amuont, :rb2_expense, :rb2_tax, :rb2_bankinterest, :rb2_docentry, :rb2_linestatus, :rb2_concept)";

				$detail2 = json_decode($Data['detail2'], true);
				
				foreach ($detail2 as $key => $value) {
					$resInsert2 = $this->pedeo->insertRow($sqlInsertDetail2, array(
						":rb2_date" => $value['rb2_date'],
						":rb2_ref" => $value['rb2_ref'],
						":rb2_amuont" => $value['rb2_amount'],
						":rb2_expense" => $value['rb2_expense'],
						":rb2_tax" => $value['rb2_tax'],
						":rb2_bankinterest" => $value['rb2_bankinterest'],
						":rb2_linestatus" => $value['rb2_linestatus'],
						":rb2_concept" => $value['rb2_concept'],
						":rb2_docentry" => $resInsert
					));

					if(is_numeric($resInsert2) && $resInsert2 > 0  ){
						
					}else{
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'    => $resInsert2,
							'mensaje'	=> 'No se pudo crear la conciliación de bancos'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
					}
				}


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
					'mensaje'	=> 'No se pudo crear la reconciliación de bancos'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}



      $this->response($respuesta);
	}

	//OBTENER RECONCIALIACIONES REALIZADAS
	public function getBankReconciliation_get(){

		$sqlSelect = "SELECT * FROM dcrb";

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

				$opeComp = ($Data['isedit'] == 1) ? "=": "!=" ;

				$sqlSelect = "SELECT mac1.ac1_trans_id as id,
											gbpr.bpr_datetransfer as fecha,
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
											WHERE ac1_account = :ac1_account AND bpr_datetransfer BETWEEN  :startdate AND :enddate
											and ac1_line_num not in (select distinct rb1_linenumcod from  crb1 where rb1_linenumcod {comp} 0)
											UNION ALL
											SELECT mac1.ac1_trans_id as id,
											gbpe.bpe_datetransfer as fecha,
											mac1.ac1_debit as debit,
											mac1.ac1_credit * -1 as credit,
											tmac.mac_base_type as basetype,
											tmac.mac_base_entry as baseentry,
											gbpe.bpe_reftransfer as ref,
											mac1.ac1_line_num as refunica
											FROM gbpe
											INNER JOIN tmac
											ON tmac.mac_base_type = gbpe.bpe_doctype AND tmac.mac_base_entry = gbpe.bpe_docentry
											INNER JOIN mac1
											ON mac1.ac1_trans_id = tmac.mac_trans_id
											WHERE ac1_account = :ac1_account AND bpe_datetransfer BETWEEN  :startdate AND :enddate
											and ac1_line_num not in (select distinct rb1_linenumcod from  crb1 where rb1_linenumcod {comp} 0)";

				// $sqlSelect = "SELECT * FROM mac1 WHERE ac1_account = :ac1_account AND ac1_doc_date BETWEEN :startdate AND :enddate ";
				$sqlSelect = str_replace("{comp}",$opeComp, $sqlSelect);
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
				WHERE acc_enabled = 1
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

	public function getBackReconciliationById_get()
	{
		$Data = $this->get();

		if (!isset($Data['crb_id'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM dcrb where crb_id =:crb_id";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":crb_id" => $Data['crb_id']));
		
		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}
	
	public function getBackReconciliationDetailById_get(){
		$Data = $this->get();
		if (!isset($Data['crb_id'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM crb1 where rb1_crbid =:rb1_crbid";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":rb1_crbid" => $Data['rb1_crbid']));
		
		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}
		
		$this->response($respuesta);
	}

	public function getExcelData_get(){
		$Data = $this->get();

		if (!isset($Data['docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM crb2 where rb2_docentry =:rb2_docentry order by rb2_id asc";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":rb2_docentry" => $Data['docentry']));
		
		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

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

	public function updateBankReconciliation_post()
	{

			$Data = $this->post();

			$sqlUpdate = "UPDATE dcrb SET crb_description = :crb_description,
				crb_docdate = :crb_docdate ,
				crb_createby = :crb_createby ,
				crb_startdate = :crb_startdate ,
				crb_enddate = :crb_enddate ,
				crb_account = :crb_account ,
				crb_gbaccount = :crb_gbaccount ,
				crb_ivaccount = :crb_ivaccount ,
				crb_cost = :crb_cost ,
				crb_tax = :crb_tax ,
				crb_posting_stardate = :crb_posting_stardate ,
				crb_posting_enddate = :crb_posting_enddate ,
				crb_currency = :crb_currency ,
				crb_series = :crb_series ,
				crb_cardcode = :crb_cardcode ,
				crb_doctype = :crb_doctype ,
				crb_bankint = :crb_bankint
				where crb_id = :crb_id";

				$ContenidoDetalle = json_decode($Data['detail'], true);


				if (!is_array($ContenidoDetalle)) {
					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' => 'No se encontro el detalle de la conciliacion bancaria'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				if (!is_array($ContenidoDetalle)) {
					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' => 'No se encontro el detalle de la conciliacion bancaria'
					);
		
					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
		
					return;
				}

			$this->pedeo->trans_begin();


			$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
				':crb_description' => $Data['crb_description'],
				':crb_docdate'		 => $Data['crb_docdate'],
				':crb_createby' 	 => $Data['crb_createby'],
				':crb_startdate'   => $Data['crb_startdate'],
				':crb_enddate'     => $Data['crb_enddate'],
				':crb_account'     => $Data['crb_account'],
				':crb_gbaccount'   => is_numeric($Data['crb_gbaccount']) ? $Data['crb_gbaccount'] : NULL,
				':crb_ivaccount'   => is_numeric($Data['crb_ivaccount']) ? $Data['crb_ivaccount'] : NULL,
				':crb_cost'     => is_numeric($Data['crb_cost']) ? $Data['crb_cost'] : NULL,
				':crb_tax'     => is_numeric($Data['crb_tax']) ? $Data['crb_tax'] : NULL,
				':crb_posting_stardate'     => $Data['crb_posting_stardate'],
				':crb_posting_enddate'     => $Data['crb_posting_enddate'],
				':crb_currency' => $Data['crb_currency'],
				':crb_series' => $Data['crb_series'],
				':crb_cardcode' => $Data['crb_cardcode'],
				':crb_doctype' => $Data['crb_doctype'],
				':crb_id' => $Data['crb_id'],
				':crb_bankint' => is_numeric($Data['crb_bankint'])?$Data['crb_bankint']: 0
			));
			if (is_numeric($resUpdate) && $resUpdate == 1) {
				$this->pedeo->queryTable("DELETE FROM crb1 WHERE rb1_crbid = :rb1_crbid", array(':rb1_crbid' => $Data['crb_id']));

				foreach ($ContenidoDetalle as $key => $detail) {
					$sqlInsertDetail = "INSERT INTO crb1(rb1_crbid, rb1_date, rb1_ref, rb1_debit, rb1_credit, rb1_gastob, rb1_imp, rb1_linenumcod, rb1_accounted)
														  VALUES (:rb1_crbid, :rb1_date, :rb1_ref, :rb1_debit, :rb1_credit, :rb1_gastob, :rb1_imp, :rb1_linenumcod, :rb1_accounted)";


					$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(

						':rb1_crbid'      => $Data['crb_id'],
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
							'mensaje'	=> 'No se pudo actualizar la conciliación bancaria'
						);

						 $this->response($respuesta);

						 return;
					}
				}

				$this->pedeo->queryTable("DELETE FROM crb2 WHERE rb2_docentry=:rb2_docentry", array(':rb2_docentry' => $Data['crb_id']));
 
				$sqlInsertDetail2 = "INSERT INTO crb2 (rb2_date, rb2_ref, rb2_amuont, rb2_expense, rb2_tax, rb2_bankinterest, rb2_docentry, rb2_linestatus, rb2_concept) 
				values (:rb2_date, :rb2_ref, :rb2_amuont, :rb2_expense, :rb2_tax, :rb2_bankinterest, :rb2_docentry, :rb2_linestatus, :rb2_concept)";

				$detail2 = json_decode($Data['detail2'], true);
				
				foreach ($detail2 as $key => $value) {
					$resInsert2 = $this->pedeo->insertRow($sqlInsertDetail2, array(
						":rb2_date" => $value['rb2_date'],
						":rb2_ref" => $value['rb2_ref'],
						":rb2_amuont" => $value['rb2_amount'],
						":rb2_expense" => $value['rb2_expense'],
						":rb2_tax" => $value['rb2_tax'],
						":rb2_bankinterest" => $value['rb2_bankinterest'],
						":rb2_linestatus" => $value['rb2_linestatus'],
						":rb2_concept" => $value['rb2_concept'],
						":rb2_docentry" => $Data['crb_id']
					));

					if(is_numeric($resInsert2) && $resInsert2 > 0  ){
						
					}else{
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'    => $resInsert2,
							'mensaje'	=> 'No se pudo crear la conciliación bancaria'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
					}
				}


				$this->pedeo->trans_commit();

				$respuesta = array(
					'error' => false,
					'data' => $resUpdate,
					'mensaje' =>'Conciliación registrada con exito'
				);

			}else{
				$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data'    => $resUpdate,
				'mensaje'	=> 'No se pudo actualizar la conciliacion bancaria'
			);

			$this->response($respuesta);

			return;
			}

			$this->response($respuesta);
	}

}
