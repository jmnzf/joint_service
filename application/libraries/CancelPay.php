<?php
defined('BASEPATH') OR exit('No direct script access allowed');


class CancelPay {

    private $ci;
    private $pdo;

	public function __construct() {

        header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
        header("Access-Control-Allow-Origin: *");

        $this->ci =& get_instance();
        $this->ci->load->database();
        $this->pdo = $this->ci->load->database('pdo', true)->conn_id;
        $this->ci->load->library('pedeo', [$this->pdo]);
        $this->ci->load->library('generic');


	}


    public function cancelPayment($Data) {

		// $FechaOper =  FECHA DE OPERACION DEL DOCUMENTO DE ANULACION SI ES CON LA FECHA DEL DOCUMENTO ORIGEN O FECHA SELECIONADA DEL CLIENTE

		$DECI_MALES = $this->ci->generic->getDecimals();

		$FechaOper = '';
		$TipoGenDoc = 0;

		if ( !isset( $Data['trans_id'] ) OR !isset( $Data['comments'] ) ) {
			
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La información enviada no es valida'
			);

			return $respuesta;
		}

		$sqlAsiento = "SELECT * FROM tmac WHERE mac_trans_id = :mac_trans_id ";

		$resAsiento = $this->ci->pedeo->queryTable($sqlAsiento, array(
			":mac_trans_id" => $Data['trans_id']
		));

		if ( $Data['is_fechadoc'] == 1 ){

			$FechaOper = $Data['fecha_doc'];

		}else{
			$FechaOper = $Data['fecha_select'];
		}	

		if ( $Data['Op'] == 1 ){
			$TipoGenDoc = 1;
		}else{
			$TipoGenDoc = 0;
		}


		//VALIDANDO PERIODO CONTABLE
		$periodo = $this->ci->generic->ValidatePeriod($FechaOper, $FechaOper, $FechaOper, $TipoGenDoc);

		if (isset($periodo['error']) && $periodo['error'] == false) {
		} else {
			$respuesta = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => isset($periodo['mensaje']) ? $periodo['mensaje'] : 'no se pudo validar el periodo contable'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}
		//PERIODO CONTABLE



		if ( isset($resAsiento[0]) ){

			if ( $resAsiento[0]['mac_base_type'] == $Data['doctype'] ){

				$sqlDetalleAsiento = "SELECT * FROM mac1 WHERE ac1_trans_id = :ac1_trans_id ";

				$resDetalleAsiento = $this->ci->pedeo->queryTable($sqlDetalleAsiento, array(
					":ac1_trans_id" => $Data['trans_id']
				));

				if ( isset( $resDetalleAsiento[0] ) ) {

					// //BUSCANDO LA NUMERACION DEL DOCUMENTO
					$DocNumVerificado = $this->ci->documentnumbering->NumberDoc($resAsiento[0]['mac_serie'],$resAsiento[0]['mac_doc_date'],$resAsiento[0]['mac_doc_duedate']);

					if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0){
				
					}else if ($DocNumVerificado['error']){
				
						return $DocNumVerificado;
					}
					//

					//BUSCANDO LA NUMERACION DEL DOCUMENTO ANULACION
					$DocNumVerificado2 = $this->ci->documentnumbering->NumberDoc($Data['series'],$FechaOper,$FechaOper);

					if (isset($DocNumVerificado2) && is_numeric($DocNumVerificado2) && $DocNumVerificado2 > 0){
				
					}else if ($DocNumVerificado2['error']){
				
						return $DocNumVerificado;
					}
					//

					$sqlInsertAnulacion = "INSERT INTO tban(ban_docnum,ban_docdate,ban_comment,ban_createat,ban_baseentry,ban_basetype,ban_doctype,ban_series,ban_createby,ban_currency,business,branch)
										VALUES(:ban_docnum,:ban_docdate,:ban_comment,:ban_createat,:ban_baseentry,:ban_basetype,:ban_doctype,:ban_series,:ban_createby,:ban_currency,:business,:branch)";

					$resInsertAnulacion = $this->ci->pedeo->insertRow($sqlInsertAnulacion, array(

						':ban_docnum' => $DocNumVerificado2,
						':ban_docdate' => $FechaOper,
						':ban_comment' => $Data['comments'],
						':ban_createat' => date('Y-m-d'),
						':ban_baseentry' => NULL,
						':ban_basetype' => NULL,
						':ban_doctype' => 50,
						':ban_series' => $Data['series'],
						':ban_createby' => $Data['createby'],
						':ban_currency' => $Data['currency'],
						':business' => $Data['business'],
						':branch' => $Data['branch']

					));

					if ( is_numeric($resInsertAnulacion) && $resInsertAnulacion > 0 ) {

					} else {

						$respuesta = array(
								'error'   => true,
								'data'    => $resInsertAnulacion,
								'mensaje' => 'No se pudo crear la anulación'
							);

						return $respuesta;
					}
					

					$sqlInsertHeader = "INSERT INTO tmac( mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_update_date, mac_series, mac_made_usuer, mac_update_user, mac_currency, mac_doctype, business, branch, mac_accperiod)
										VALUES(:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_update_date, :mac_series, :mac_made_usuer, :mac_update_user, :mac_currency, :mac_doctype, :business, :branch, :mac_accperiod)";
					
					$resSqlInsertHeader = $this->ci->pedeo->insertRow($sqlInsertHeader, array(
						'mac_doc_num' => $DocNumVerificado,
						':mac_status' => 2, 
						':mac_base_type' => 50, 
						':mac_base_entry' => $resInsertAnulacion, 
						':mac_doc_date' => $FechaOper, 
						':mac_doc_duedate' => $FechaOper, 
						':mac_legal_date' => $FechaOper, 
						':mac_ref1' => $resAsiento[0]['mac_ref1'], 
						':mac_ref2' => $resAsiento[0]['mac_ref2'], 
						':mac_ref3' => $resAsiento[0]['mac_ref3'], 
						':mac_loc_total' => $resAsiento[0]['mac_loc_total'], 
						':mac_fc_total' => $resAsiento[0]['mac_fc_total'], 
						':mac_sys_total' => $resAsiento[0]['mac_sys_total'], 
						':mac_trans_dode' => $resAsiento[0]['mac_trans_dode'], 
						':mac_beline_nume' => $resAsiento[0]['mac_beline_nume'], 
						':mac_vat_date' => $resAsiento[0]['mac_vat_date'], 
						':mac_serie' => $resAsiento[0]['mac_serie'], 
						':mac_number' => $resAsiento[0]['mac_number'], 
						':mac_bammntsys' => $resAsiento[0]['mac_bammntsys'], 
						':mac_bammnt' => $resAsiento[0]['mac_bammnt'], 
						':mac_wtsum' => $resAsiento[0]['mac_wtsum'], 
						':mac_vatsum' => $resAsiento[0]['mac_vatsum'], 
						':mac_comments' => $Data['comments'], 
						':mac_create_date' => $resAsiento[0]['mac_create_date'], 
						':mac_update_date' => $resAsiento[0]['mac_update_date'], 
						':mac_series' => $resAsiento[0]['mac_series'], 
						':mac_made_usuer' => $resAsiento[0]['mac_made_usuer'], 
						':mac_update_user' => $resAsiento[0]['mac_update_user'], 
						':mac_currency' => $resAsiento[0]['mac_currency'], 
						':mac_doctype' => $resAsiento[0]['mac_doctype'], 
						':business' => $resAsiento[0]['business'], 
						':branch' => $resAsiento[0]['branch'], 
						':mac_accperiod' => $periodo['data']
					));


					if ( is_numeric($resSqlInsertHeader) && $resSqlInsertHeader > 0 ){

						// Se actualiza la serie de la numeracion del documento

						$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
													WHERE pgs_id = :pgs_id";

						$resActualizarNumeracion = $this->ci->pedeo->updateRow($sqlActualizarNumeracion, array(
							':pgs_nextnum' => $DocNumVerificado,
							':pgs_id'      => $resAsiento[0]['mac_serie']
						));

						if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {

						} else {

							$respuesta = array(
							'error'   => true,
							'data'    => $resActualizarNumeracion,
							'mensaje'	=> 'No se pudo crear el asiento'
							);

							return $respuesta;
						}
						// Fin de la actualizacion de la numeracion del documento

						// Se actualiza la serie de la numeracion del documento anulación

						$sqlActualizarNumeracion2  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
						WHERE pgs_id = :pgs_id";

						$resActualizarNumeracion2 = $this->ci->pedeo->updateRow($sqlActualizarNumeracion2, array(
							':pgs_nextnum' => $DocNumVerificado2,
							':pgs_id'      => $Data['series']
						));

						if (is_numeric($resActualizarNumeracion2) && $resActualizarNumeracion2 == 1) {

						} else {

							$respuesta = array(
							'error'   => true,
							'data'    => $resActualizarNumeracion2,
							'mensaje'	=> 'No se pudo crear el asiento'
							);

							return $respuesta;
						}
						// Fin de la actualizacion de la numeracion del documento

						foreach ($resDetalleAsiento as $key => $detalle) {

							$sqlInsertDetalle = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate, ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype, ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord, ac1_ven_debit, ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, ac1_card_type, business, branch, ac1_codret, ac1_base_tax)
												 VALUES (:ac1_trans_id, :ac1_account, :ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys, :ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode, :ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct, :ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref, :ac1_card_type, :business, :branch, :ac1_codret, :ac1_base_tax)";
							
							$debito = 0;
							$credito = 0;

							$debitosys = 0;
							$creditosys= 0;

							$vendebito = 0;
							$vencredito = 0;

							$oldVen = 0;

							if ( $detalle['ac1_debit'] > 0 ){
							
								$debito = 0;
								$credito = $detalle['ac1_debit'];

								$oldVen = $detalle['ac1_debit'];
								
							}

							if ( $detalle['ac1_credit'] > 0 ){
							
								$debito = $detalle['ac1_credit'];;
								$credito = 0;
								
								$oldVen = $detalle['ac1_credit'];
							}

							if ( $detalle['ac1_debit_sys'] > 0 ){
							
								$debitosys = 0;
								$creditosys = $detalle['ac1_debit_sys'];
								
							}

							if ( $detalle['ac1_credit_sys'] > 0 ){
							
								$debitosys = $detalle['ac1_credit_sys'];
								$creditosys = 0;
								
							}

							$resInsertDetalle = $this->ci->pedeo->insertRow($sqlInsertDetalle, array(

								':ac1_trans_id' => $resSqlInsertHeader,
								':ac1_account' => $detalle['ac1_account'], 
								':ac1_debit' => $debito, 
								':ac1_credit' => $credito, 
								':ac1_debit_sys' => $debitosys, 
								':ac1_credit_sys' => $creditosys, 
								':ac1_currex' => $detalle['ac1_currex'], 
								':ac1_doc_date' => $FechaOper, 
								':ac1_doc_duedate' => $FechaOper, 
								':ac1_debit_import' => $detalle['ac1_debit_import'], 
								':ac1_credit_import' => $detalle['ac1_credit_import'], 
								':ac1_debit_importsys' => $detalle['ac1_debit_importsys'], 
								':ac1_credit_importsys' => $detalle['ac1_credit_importsys'], 
								':ac1_font_key' => $resSqlInsertHeader,
								':ac1_font_line' => $detalle['ac1_font_line'], 
								':ac1_font_type' => 18,
								':ac1_accountvs' => $detalle['ac1_accountvs'], 
								':ac1_doctype' => $detalle['ac1_doctype'],
								':ac1_ref1' => $detalle['ac1_ref1'], 
								':ac1_ref2' => $detalle['ac1_ref2'], 
								':ac1_ref3' => $detalle['ac1_ref3'], 
								':ac1_prc_code' => $detalle['ac1_prc_code'], 
								':ac1_uncode' => $detalle['ac1_uncode'], 
								':ac1_prj_code' => $detalle['ac1_prj_code'], 
								':ac1_rescon_date' => $detalle['ac1_rescon_date'],
								':ac1_recon_total' => $detalle['ac1_recon_total'], 
								':ac1_made_user' => $detalle['ac1_made_user'], 
								':ac1_accperiod' => $periodo['data'],
								':ac1_close' => $detalle['ac1_close'], 
								':ac1_cord' => $detalle['ac1_cord'], 
								':ac1_ven_debit' => 0, 
								':ac1_ven_credit' => 0, 
								':ac1_fiscal_acct' => $detalle['ac1_fiscal_acct'], 
								':ac1_taxid' => $detalle['ac1_taxid'], 
								':ac1_isrti' => $detalle['ac1_isrti'],
								':ac1_basert' => $detalle['ac1_basert'],
								':ac1_mmcode' => $detalle['ac1_mmcode'],
								':ac1_legal_num' => $detalle['ac1_legal_num'], 
								':ac1_codref' => $detalle['ac1_codref'], 
								':ac1_card_type' => $detalle['ac1_card_type'], 
								':business' => $detalle['business'],
								':branch'   => $detalle['branch'], 
								':ac1_codret' => $detalle['ac1_codret'], 
								':ac1_base_tax' => $detalle['ac1_base_tax'] 
							));

							if (is_numeric($resInsertDetalle) && $resInsertDetalle > 0 ) {

							
							}else{

								$respuesta = array(
									'error' => true,
									'data' => $resInsertDetalle,
									'mensaje' => 'Error al insertar la copia del detalle del asiento'
								);

								return $respuesta;
							}


							// ACTUALIZAR VEN DEBIT Y CREDIT DEL ASIENTO VIEJO

							$sqlUpdate = "UPDATE mac1 SET ac1_ven_debit = :ac1_ven_debit, ac1_ven_credit = :ac1_ven_credit WHERE ac1_line_num = :ac1_line_num";

							$resUpdate = $this->ci->pedeo->updateRow($sqlUpdate, array(
								':ac1_ven_credit' => 0,
								':ac1_ven_debit'  => 0,
								':ac1_line_num'   => $detalle['ac1_line_num']

							));


							if (is_numeric($resUpdate) && $resUpdate == 1 ) {

							
							}else{

								$respuesta = array(
									'error' => true,
									'data' => $resUpdate,
									'mensaje' => 'Error al actualizar el asiento viejo'
								);

								return $respuesta;
							}
						}

						// SE CAMBIA DE LA CABECERA DEL ASIENTO VIEJO
						$update = $this->ci->pedeo->updateRow("UPDATE tmac SET mac_status = :mac_status WHERE mac_trans_id = :mac_trans_id", array(":mac_status" => 2, ":mac_trans_id" => $Data['trans_id'] ));

						if ( is_numeric($update) && $update == 1 ){

						}else{

							$respuesta = array(
								'error' => true,
								'data' => $update,
								'mensaje' => 'No se pudo cambiar el estado del documento'
							);
	
							return $respuesta;
						}
						//

						
						//SE INSERTA EL ESTADO DEL DOCUMENTO

						$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
						VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

						$resInsertEstado = $this->ci->pedeo->insertRow($sqlInsertEstado, array(


							':bed_docentry' => $Data['docentry'],
							':bed_doctype' => $Data['doctype'],
							':bed_status' => 2, //ESTADO ANULADO
							':bed_createby' => $Data['createby'],
							':bed_date' => date('Y-m-d'),
							':bed_baseentry' => $resInsertAnulacion,
							':bed_basetype' => 50
						));


						if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
						} else {

							$respuesta = array(
								'error'   => true,
								'data' => $resInsertEstado,
								'mensaje'	=> 'No se pudo cambiar el estado de pago'
							);

							return $respuesta;
							
                		}

						$complementoProceso = [];

						if ( $Data['doctype'] == 20 ){ // PAGO RECIBIDO

							$complementoProceso = $this->reversePaymentReceived($Data['docentry'], $Data['doctype'], $resInsertAnulacion, 50, $Data['createby']);

							if (isset($complementoProceso['error']) && $complementoProceso['error'] == false) {

							} else {

								$respuesta = array(
									'error'    => true,
									'data' 	   => $complementoProceso,
									'mensaje'  => $complementoProceso['mensaje']
								);
	
								return $respuesta;
							}

						}else if ($Data['doctype'] == 19) { // PAGO EFECTUADO

							$complementoProceso = $this->reversePaymentMade($Data['docentry'], $Data['doctype'], $resInsertAnulacion, 50, $Data['createby']);

							if (isset($complementoProceso['error']) && $complementoProceso['error'] == false) {

							} else {

								$respuesta = array(
									'error'    => true,
									'data' 	   => $complementoProceso,
									'mensaje'  => $complementoProceso['mensaje']
								);
	
								return $respuesta;
							}
							
						}else if ($Data['doctype'] == 22) { // RECONCILIACION
							$complementoProceso = $this->reverseCompensation($Data['docentry'], $Data['doctype'], $resInsertAnulacion, 50, $Data['createby']);

							if (isset($complementoProceso['error']) && $complementoProceso['error'] == false) {

							} else {

								$respuesta = array(
									'error'    => true,
									'data' 	   => $complementoProceso,
									'mensaje'  => $complementoProceso['mensaje']
								);
	
								return $respuesta;
							}
						} 


						//


						$respuesta = array(
							'error' => false,
							'data' => [],
							'mensaje' => 'Documento anulado con exito'
						);



					}else{

						$respuesta = array(
							'error' => true,
							'data' => $resSqlInsertHeader,
							'mensaje' => 'Error al insertar la copia del asiento'
						);

						return $respuesta;
					}

				}else{

					$respuesta = array(
						'error' => true,
						'data' => $resDetalleAsiento,
						'mensaje' => 'No se encontro el detalle del asiento'
					);
				}

			}else{

				$respuesta = array(
					'error' => true,
					'data' => $resAsiento,
					'mensaje' => 'No se puede anular el asiento actual, el documento afectado no es del tipo correcto'
				);
			}

		}else{

			$respuesta = array(
				'error' => true,
				'data' => $resAsiento,
				'mensaje' => 'No se encontro el asiento'
			);
		}

		return $respuesta;
	}

	// reversar movimiento de los documentos originales, para el caso de los pagos recibidos
	public function reversePaymentReceived($docentry, $doctype, $anulacion, $tpa, $user) {

		//$docentry =  DOCENTRY DEL DOCUMENTO QUE SE ESTA ANULANDO 
		//$doctype =  DOCTYPE DEL DOCUMENTO QUE SE ESTA ANULANDO
		//$anulacion =  DOCENTRY DE LA ANULACION
		//$tpa == TIPO DE DOCUMENTO DE LA ANULACION

		if ($doctype == 20){

			// se buscan los documentos afectados en el movimiento 
			$sqlDocuments = "SELECT * FROM bpr1 WHERE pr1_docnum = :pr1_docnum";
			$resDocuments = $this->ci->pedeo->queryTable($sqlDocuments, array(
				':pr1_docnum' => $docentry
			));

			if (isset($resDocuments[0])){

				foreach ($resDocuments as $key => $document) {

					// CASO PARA FACTURA NORMAL Y ANTICIPADA DE VENTAS
					if ( $document['pr1_doctype']  == 5 || $document['pr1_doctype'] == 34 ){

						// PAY TODAY
						$sqlUpdateFactPay = "UPDATE  dvfv  SET dvf_paytoday = COALESCE(dvf_paytoday,0)-:dvf_paytoday WHERE dvf_docentry = :dvf_docentry and dvf_doctype = :dvf_doctype";

						$resUpdateFactPay = $this->ci->pedeo->updateRow($sqlUpdateFactPay, array(

							':dvf_paytoday' => $document['pr1_vlrpaid'],
							':dvf_docentry' => $document['pr1_docentry'],
							':dvf_doctype'  => $document['pr1_doctype']


						));

						if (is_numeric($resUpdateFactPay) && $resUpdateFactPay == 1) {
						} else {
							
							return array(
								'error'   => true,
								'data' 	  => $resUpdateFactPay,
								'mensaje' => 'No se pudo actualizar el valor del pago en la factura para el documento' . $document['pr1_docentry']
							);
						}

						// ASIENTO DE LA FACTURA DONDE SE CAUSA LA CXC
						$slqUpdateVenDebit = "UPDATE mac1
											  SET ac1_ven_credit = ac1_ven_credit - :ac1_ven_credit
											  WHERE ac1_line_num = :ac1_line_num";

						$resUpdateVenDebit = $this->ci->pedeo->updateRow($slqUpdateVenDebit, array(

							':ac1_ven_credit' => $document['pr1_vlrpaid'],
							':ac1_line_num'   => $document['pr1_line_num']


						));

						if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
						} else {
							

							return array(
								'error'   => true,
								'data'    => $resUpdateVenDebit,
								'mensaje' => 'No se pudo actualizar el valor del pago en la factura para el asiento' . $document['pr1_docentry']
							);

							
						}

						// SE VALIDA EL SALDO PARA CAMBIAR O MANTENER EL ESTADO DEL DOCUMENTO
						if ( $document['pr1_vlrtotal'] == $document['pr1_vlrpaid'] ){ // SI EL MONTO TOTAL DEL DOCUMENTO ES IGUAL AL MONTO APLICADO SE DEBE CAMBIAR EL ESTADO PUESTO QUE EL DOCUMENTO ESTA CERRADO ACTUALMENTE

							$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
												VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

							$resInsertEstado = $this->ci->pedeo->insertRow($sqlInsertEstado, array(

								':bed_docentry' => $document['pr1_docentry'],
								':bed_doctype' => $document['pr1_doctype'],
								':bed_status' => 1, //ESTADO ABIERTO
								':bed_createby' => $user,
								':bed_date' => date('Y-m-d'),
								':bed_baseentry' => $anulacion,
								':bed_basetype' => $tpa
							));


							if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
							} else {

								return array(
									'error'   => true,
									'data'    => $resInsertEstado,
									'mensaje' => 'No se pudo cambiar el estado del documento'
								);
							}
						}

					}

					// SI EL DOCUMENTO ES UNA FACTURA POS REALIZADA POR EL MODULO DE  DISTRIBUCION 
					if ( $document['pr1_doctype']  == 47 ) {

						// SE VALIDA QUE NO SEA UN ANTICIPO
						$EsAnticipoDoc = 0;

						$sqlPsnk = "SELECT 
										CASE 
											WHEN ac1_credit > 0 THEN 1
											ELSE 0
										END AS op	
										FROM mac1 
										WHERE ac1_line_num = :ac1_line_num";

						$resPsnk = $this->ci->pedeo->queryTable($sqlPsnk, array(
							':ac1_line_num' => $document['pr1_line_num']
						));

						if (isset($resPsnk[0])){

							if ( $resPsnk[0]['op'] == 1 ){
								$EsAnticipoDoc = 1;
							}else{
								$EsAnticipoDoc = 0;
							}

						}else{
							return array(
								'error'   => true,
								'data'	  => $resPsnk,
								'mensaje'	=> 'No se pudo validar el tipo de operación para el tipo de documento 47'
							);
						}


						// PAY TODAY
						// 0 QUE INDICA QUE ES UNA FACTURA O RECIBO MAS NO EL ANTICIPO
						if ($EsAnticipoDoc == 0){

							$sqlUpdateFactPay = "UPDATE  dvrc  SET vrc_paytoday = COALESCE(vrc_paytoday,0)-:vrc_paytoday WHERE vrc_docentry = :vrc_docentry and vrc_doctype = :vrc_doctype";

							$resUpdateFactPay = $this->ci->pedeo->updateRow($sqlUpdateFactPay, array(

								':vrc_paytoday' => $document['pr1_vlrpaid'],
								':vrc_docentry' => $document['pr1_docentry'],
								':vrc_doctype'  => $document['pr1_doctype']


							));

							if (is_numeric($resUpdateFactPay) && $resUpdateFactPay == 1) {
							} else {
								return array(
									'error'   => true,
									'data' => $resUpdateFactPay,
									'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura ' . $document['pr1_docentry']
								);
							}
						}

						// ASIENTO DE LA FACTURA DONDE SE CAUSA LA CXC O EL ANTICIPO A CUENTA

						$slqUpdateVenDebit = "";

						if ($EsAnticipoDoc == 0){

							$slqUpdateVenDebit = "UPDATE mac1
							SET ac1_ven_credit = ac1_ven_credit - :ac1_ven
							WHERE ac1_line_num = :ac1_line_num";

						}else{
							$slqUpdateVenDebit = "UPDATE mac1
							SET ac1_ven_debit = ac1_ven_debit - :ac1_ven
							WHERE ac1_line_num = :ac1_line_num";
						}

						$resUpdateVenDebit = $this->ci->pedeo->updateRow($slqUpdateVenDebit, array(

							':ac1_ven' 		=> $document['pr1_vlrpaid'],
							':ac1_line_num' => $document['pr1_line_num']


						));

						if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
						} else {

							return array(
								'error'   => true,
								'data' 	  => $resUpdateVenDebit,
								'mensaje' => 'No se pudo actualizar el valor del pago en la factura o anticipo' . $document['pr1_docentry']
							);
						}

						// SOLO CUANDO NO ES EL ANTICIPO
						if ($EsAnticipoDoc == 0){
							// SE VALIDA EL SALDO PARA CAMBIAR O MANTENER EL ESTADO DEL DOCUMENTO
							if ( $document['pr1_vlrtotal'] == $document['pr1_vlrpaid'] ){ // SI EL MONTO TOTAL DEL DOCUMENTO ES IGUAL AL MONTO APLICADO SE DEBE CAMBIAR EL ESTADO PUESTO QUE EL DOCUMENTO ESTA CERRADO ACTUALMENTE

								$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
													VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

								$resInsertEstado = $this->ci->pedeo->insertRow($sqlInsertEstado, array(

									':bed_docentry' => $document['pr1_docentry'],
									':bed_doctype' => $document['pr1_doctype'],
									':bed_status' => 1, //ESTADO ABIERTO
									':bed_createby' => $user,
									':bed_date' => date('Y-m-d'),
									':bed_baseentry' => $anulacion,
									':bed_basetype' => $tpa
								));


								if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
								} else {

									return array(
										'error'   => true,
										'data'    => $resInsertEstado,
										'mensaje' => 'No se pudo cambiar el estado del documento'
									);
								}
							}
						}
					}

					// NOTA CREDITO
					// SE ACTUALIZA EL VALOR DEL CAMPO PAY TODAY EN NOTA CREDITO
					if ($document['pr1_doctype'] == 6) { // SOLO CUANDO ES UNA NOTA CREDITO

						// PAY TODAY
						$sqlUpdateFactPay = "UPDATE  dvnc  SET vnc_paytoday = COALESCE(vnc_paytoday,0)-:vnc_paytoday WHERE vnc_docentry = :vnc_docentry and vnc_doctype = :vnc_doctype";

						$resUpdateFactPay = $this->ci->pedeo->updateRow($sqlUpdateFactPay, array(

							':vnc_paytoday' => $document['pr1_vlrpaid'],
							':vnc_docentry' => $document['pr1_docentry'],
							':vnc_doctype'  => $document['pr1_doctype']


						));

						if (is_numeric($resUpdateFactPay) && $resUpdateFactPay == 1) {
						} else {

							return array(
								'error'   => true,
								'data'    => $resUpdateFactPay,
								'mensaje' => 'No se pudo actualizar el valor del pago en la nota credito ' . $document['pr1_docentry']
							);
						}

						// LINEA DE ASIENTO CONTABLE DONDE SE CAUSO DE VUELTA LA CXC
						$slqUpdateVenDebit = "UPDATE mac1
							SET ac1_ven_debit = ac1_ven_debit - :ac1_ven_debit
							WHERE ac1_line_num = :ac1_line_num";

						$resUpdateVenDebit = $this->ci->pedeo->updateRow($slqUpdateVenDebit, array(

							':ac1_ven_debit' => $document['pr1_vlrpaid'],
							':ac1_line_num' =>  $document['pr1_line_num']

						));

						if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
						} else {

							return array(
								'error'   => true,
								'data'    => $resUpdateFactPay,
								'mensaje' => 'No se pudo actualizar el valor del pago en la factura ' . $document['pr1_docentry']
							);
						}

						// SE VALIDA EL SALDO PARA CAMBIAR O MANTENER EL ESTADO DEL DOCUMENTO
						if ( $document['pr1_vlrtotal'] == $document['pr1_vlrpaid'] ){ // SI EL MONTO TOTAL DEL DOCUMENTO ES IGUAL AL MONTO APLICADO SE DEBE CAMBIAR EL ESTADO PUESTO QUE EL DOCUMENTO ESTA CERRADO ACTUALMENTE

							$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
												VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

							$resInsertEstado = $this->ci->pedeo->insertRow($sqlInsertEstado, array(

								':bed_docentry' => $document['pr1_docentry'],
								':bed_doctype' => $document['pr1_doctype'],
								':bed_status' => 1, //ESTADO ABIERTO
								':bed_createby' => $user,
								':bed_date' => date('Y-m-d'),
								':bed_baseentry' => $anulacion,
								':bed_basetype' => $tpa
							));


							if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
							} else {

								return array(
									'error'   => true,
									'data'    => $resInsertEstado,
									'mensaje' => 'No se pudo cambiar el estado del documento'
								);
							}
						}
					}

					//ASIENTO MANUALES
					if ($document['pr1_doctype'] == 18) {

						// PRIMERO SE VERIFICA DE QUE LADO ESTA EL SALDO (O ES DEBITO O ES CREDITO)
						$sqlLadoCD = "SELECT 
							CASE 
								WHEN ac1_credit > 0 THEN 1
								ELSE 0
							END AS op	
							FROM mac1 
							WHERE ac1_line_num = :ac1_line_num";

						$resLadoCD = $this->ci->pedeo->queryTable($sqlLadoCD, array(
							':ac1_line_num' => $document['pr1_line_num']
						));

						if (isset($resLadoCD[0])){

							$slqUpdateVenDebit = "";

							if ($resLadoCD[0]['op'] == 1){ // SI ES CREDITO

								$slqUpdateVenDebit = "UPDATE mac1
									SET ac1_ven_debit = ac1_ven_debit - :ac1_ven
									WHERE ac1_line_num = :ac1_line_num";

							}else{ // SI ES DEBITO
								$slqUpdateVenDebit = "UPDATE mac1
									SET ac1_ven_credit = ac1_ven_credit - :ac1_ven
									WHERE ac1_line_num = :ac1_line_num";
							}

							$resUpdateVenDebit = $this->ci->pedeo->updateRow($slqUpdateVenDebit, array(

								':ac1_ven'      => $document['pr1_vlrpaid'],
								':ac1_line_num' => $document['pr1_line_num']
							));

							if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
							} else {
								

								return array(
									'error'   => true,
									'data' 		=> $resUpdateVenDebit,
									'mensaje'	=> 'No se pudo actualizar el valor del pago (documento manual)' . $document['pr1_docentry']
								);

							
							}

						}else{
							return array(
								'error'   => true,
								'data'    => $resInsertEstado,
								'mensaje' => 'No se encontro la naturaleza del documento manual'
							);
						}

					}

					//PAGO RECIBIDO
					if ( $document['pr1_doctype']  == 20 ){

						if ( $document['pr1_docentry'] == 0 ){

						
							$slqUpdateVenDebit = "UPDATE mac1
									SET ac1_ven_credit = :ac1_ven_credit
									WHERE ac1_font_key = :ac1_font_key
									AND ac1_font_type = :ac1_font_type
									AND ac1_credit > :ac1_credit";

							$resUpdateVenDebit = $this->ci->pedeo->updateRow($slqUpdateVenDebit, array(

									':ac1_ven_credit' => 0,
									':ac1_font_key'  => $document['pr1_docnum'],
									':ac1_font_type'  => $document['pr1_doctype'],
									':ac1_credit'     => 0

							));

							if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
							} else {	
								return array(
									'error'   => true,
									'data'    =>   $resUpdateVenDebit,
									'mensaje' => 'No se puedo actualizar el valor del pago recibido ' . $document['pr1_docentry']
								);
							}

						}else{

							$slqUpdateVenDebit = "UPDATE mac1
												SET ac1_ven_debit = ac1_ven_debit - :ac1_ven_debit
												WHERE ac1_line_num = :ac1_line_num";

							$resUpdateVenDebit = $this->ci->pedeo->updateRow($slqUpdateVenDebit, array(

									':ac1_ven_debit' => $document['pr1_vlrpaid'],
									':ac1_line_num'  => $document['pr1_line_num']

							));

							if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
							} else {	
								return array(
									'error'   => true,
									'data'    =>   $resUpdateVenDebit,
									'mensaje' => 'No se puedo actualizar el valor del pago recibido ' . $document['pr1_docentry']
								);
							}
						}
					}
				}


				return  array(
					'error' => false,
					'data' => [],
					'mensaje' => 'Sin error'
				);

			}else{
				return  array(
					'error' => true,
					'data' => [],
					'mensaje' => 'No se encontro el detalle de los documentos que fueron afectados en el pago'
				);
			}

		}else{

			return  array(
				'error' => true,
				'data' => [],
				'mensaje' => 'Tipo de documento incorrecto para realizar esta operación, (Anulación de pago recibido)'
			);
		}

	}

	// reversar movimiento de los documentos originales, para el caso de los pagos realizados
	public function reversePaymentMade($docentry, $doctype, $anulacion, $tpa, $user) {

		//$docentry =  DOCENTRY DEL DOCUMENTO QUE SE ESTA ANULANDO 
		//$doctype =  DOCTYPE DEL DOCUMENTO QUE SE ESTA ANULANDO
		//$anulacion =  DOCENTRY DE LA ANULACION
		//$tpa == TIPO DE DOCUMENTO DE LA ANULACION

		if ($doctype == 19){

			// se buscan los documentos afectados en el movimiento 
			$sqlDocuments = "SELECT * FROM bpe1 WHERE pe1_docnum = :pe1_docnum";
			$resDocuments = $this->ci->pedeo->queryTable($sqlDocuments, array(
				':pe1_docnum' => $docentry
			));

			if (isset($resDocuments[0])){

				foreach ($resDocuments as $key => $document) {
					//CASO PARA FACTURA NORMAL Y ANTICIDAPA DE COMPRAS
					if ($document['pe1_doctype'] == 15 || $document['pe1_doctype'] == 46) {

						//PAY TODAY
						$sqlUpdateFactPay = "UPDATE  dcfc  SET cfc_paytoday = COALESCE(cfc_paytoday,0)-:cfc_paytoday WHERE cfc_docentry = :cfc_docentry and cfc_doctype = :cfc_doctype";

						$resUpdateFactPay = $this->ci->pedeo->updateRow($sqlUpdateFactPay, array(

							':cfc_paytoday' => $document['pe1_vlrpaid'],
							':cfc_docentry' => $document['pe1_docentry'],
							':cfc_doctype' =>  $document['pe1_doctype']

						));


						if (is_numeric($resUpdateFactPay) && $resUpdateFactPay > 0) {
						} else {
							

							return array(
								'error'   => true,
								'data' => $resUpdateFactPay,
								'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura ' . $document['pe1_docentry']
							);

						
						}
						// ASIENTO DE LA FACTURA DONDE SE CAUSA LA CXP
						$slqUpdateVenDebit = "UPDATE mac1
							SET ac1_ven_debit = ac1_ven_debit - :ac1_ven_debit
							WHERE ac1_line_num = :ac1_line_num";

						$resUpdateVenDebit = $this->ci->pedeo->updateRow($slqUpdateVenDebit, array(

							':ac1_ven_debit' => $document['pe1_vlrpaid'],
							':ac1_line_num'  => $document['pe1_line_num']

						));


						if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
						} else {
						
							return array(
								'error'   => true,
								'data' => $resUpdateVenDebit,
								'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura ' . $document['pe1_docentry']
							);
						}

						// SE VALIDA EL SALDO PARA CAMBIAR O MANTENER EL ESTADO DEL DOCUMENTO
						if ( $document['pe1_vlrtotal'] == $document['pe1_vlrpaid'] ) { // SI EL MONTO TOTAL DEL DOCUMENTO ES IGUAL AL MONTO APLICADO SE DEBE CAMBIAR EL ESTADO PUESTO QUE EL DOCUMENTO ESTA CERRADO ACTUALMENTE
							$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
												VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

							$resInsertEstado = $this->ci->pedeo->insertRow($sqlInsertEstado, array(

								':bed_docentry' => $document['pe1_docentry'],
								':bed_doctype' => $document['pe1_doctype'],
								':bed_status' => 1, //ESTADO ABIERTO
								':bed_createby' => $user,
								':bed_date' => date('Y-m-d'),
								':bed_baseentry' => $anulacion,
								':bed_basetype' => $tpa
							));


							if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
							} else {

								return array(
									'error'   => true,
									'data'    => $resInsertEstado,
									'mensaje' => 'No se pudo cambiar el estado del documento'
								);
							}
						}


					}

					// CASO PARA SOLICTUD DE ANTICPO PROVEEDOR
					if ($document['pe1_doctype'] == 36) { // SOLO CUANDO ES UNA SOLICITUD DE ANTICIPO PROVEEDOR

						// PAY TODAY
						$sqlUpdateFactPay = "UPDATE  dcsa  SET csa_paytoday = COALESCE(csa_paytoday,0)-:csa_paytoday WHERE csa_docentry = :csa_docentry and csa_doctype = :csa_doctype";

						$resUpdateFactPay = $this->ci->pedeo->updateRow($sqlUpdateFactPay, array(

							':csa_paytoday' => $document['pe1_vlrpaid'],
							':csa_docentry' => $document['pe1_docentry'],
							':csa_doctype'  => $document['pe1_doctype']


						));

						if (is_numeric($resUpdateFactPay) && $resUpdateFactPay == 1) {
						} else {
							

							return array(
								'error'   => true,
								'data' 	  => $resUpdateFactPay,
								'mensaje' => 'No se pudo actualizar el valor del pago en la solicitud del anticipo ' . $document['pe1_docentry']
							);

						
						}

						// SE VALIDA EL SALDO PARA CAMBIAR O MANTENER EL ESTADO DEL DOCUMENTO
						if ( $document['pe1_vlrtotal'] == $document['pe1_vlrpaid'] ) { 

							$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
											VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

							$resInsertEstado = $this->ci->pedeo->insertRow($sqlInsertEstado, array(

								':bed_docentry' => $document['pe1_docentry'],
								':bed_doctype' => $document['pe1_doctype'],
								':bed_status' => 1, //ESTADO ABIERTO
								':bed_createby' => $user,
								':bed_date' => date('Y-m-d'),
								':bed_baseentry' => $anulacion,
								':bed_basetype' => $tpa
							));


							if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
							} else {

								return array(
									'error'   => true,
									'data'    => $resInsertEstado,
									'mensaje' => 'No se pudo cambiar el estado del documento'
								);
							}

						}


					}

					// NOTA CREDITO DE COMRPAS
					// SE ACTUALIZA EL VALOR DEL CAMPO PAY TODAY EN NOTA CREDITO
					if ($document['pe1_doctype'] == 16) { // SOLO CUANDO ES UNA NOTA CREDITO

						// PAY TODAY
						$sqlUpdateFactPay = "UPDATE  dcnc  SET cnc_paytoday = COALESCE(cnc_paytoday,0)-:cnc_paytoday WHERE cnc_docentry = :cnc_docentry and cnc_doctype = :cnc_doctype";

						$resUpdateFactPay = $this->ci->pedeo->updateRow($sqlUpdateFactPay, array(

							':cnc_paytoday' => $document['pe1_vlrpaid'],
							':cnc_docentry' => $document['pe1_docentry'],
							':cnc_doctype'  => $document['pe1_doctype']


						));

						if (is_numeric($resUpdateFactPay) && $resUpdateFactPay == 1) {
						} else {
							

							return array(
								'error'   => true,
								'data' => $resUpdateFactPay,
								'mensaje'	=> 'No se pudo actualizar el valor del pago en la nota credito ' . $document['pe1_docentry']
							);

							
						}

						// ASIENTO DE LA CONTRA PARTIDAD PARA LA CXP

						$slqUpdateVenDebit = "UPDATE mac1
						SET ac1_ven_credit = ac1_ven_credit - :ac1_ven_credit
						WHERE ac1_line_num = :ac1_line_num";

						$resUpdateVenDebit = $this->ci->pedeo->updateRow($slqUpdateVenDebit, array(

							':ac1_ven_credit' => $document['pe1_vlrpaid'],
							':ac1_line_num'   => $document['pe1_line_num'],


						));

						if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
						} else {
							

							return array(
								'error'   => true,
								'data' => $resUpdateFactPay,
								'mensaje'	=> 'No se pudo actualizar el valor del pago en la CXP de la nota credito ' . $document['pe1_docentry']
							);

						}

						// SE VALIDA EL SALDO PARA CAMBIAR O MANTENER EL ESTADO DEL DOCUMENTO
						if ( $document['pe1_vlrtotal'] == $document['pe1_vlrpaid'] ) { 

							$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
											VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

							$resInsertEstado = $this->ci->pedeo->insertRow($sqlInsertEstado, array(

								':bed_docentry' => $document['pe1_docentry'],
								':bed_doctype' => $document['pe1_doctype'],
								':bed_status' => 1, //ESTADO ABIERTO
								':bed_createby' => $user,
								':bed_date' => date('Y-m-d'),
								':bed_baseentry' => $anulacion,
								':bed_basetype' => $tpa
							));


							if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
							} else {

								return array(
									'error'   => true,
									'data'    => $resInsertEstado,
									'mensaje' => 'No se pudo cambiar el estado del documento'
								);
							}

						}
					}

					//ASIENTO MANUALES
					if ($document['pe1_doctype'] == 18) {
						// PRIMERO SE VERIFICA DE QUE LADO ESTA EL SALDO (O ES DEBITO O ES CREDITO)
						$sqlLadoCD = "SELECT 
							CASE 
								WHEN ac1_credit > 0 THEN 1
								ELSE 0
							END AS op	
							FROM mac1 
							WHERE ac1_line_num = :ac1_line_num";

						$resLadoCD = $this->ci->pedeo->queryTable($sqlLadoCD, array(
							':ac1_line_num' => $document['pe1_line_num']
						));

						if (isset($resLadoCD[0])){

							$slqUpdateVenDebit = "";

							if ($resLadoCD[0]['op'] == 1){ // SI ES CREDITO

								$slqUpdateVenDebit = "UPDATE mac1
									SET ac1_ven_debit = ac1_ven_debit - :ac1_ven
									WHERE ac1_line_num = :ac1_line_num";

							}else{ // SI ES DEBITO
								$slqUpdateVenDebit = "UPDATE mac1
									SET ac1_ven_credit = ac1_ven_credit - :ac1_ven
									WHERE ac1_line_num = :ac1_line_num";
							}

							$resUpdateVenDebit = $this->ci->pedeo->updateRow($slqUpdateVenDebit, array(

								':ac1_ven'      => $document['pe1_vlrpaid'],
								':ac1_line_num' => $document['pe1_line_num']
							));

							if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
							} else {
					
								return array(
									'error'   => true,
									'data' 		=> $resUpdateVenDebit,
									'mensaje'	=> 'No se pudo actualizar el valor del pago (documento manual)' . $document['pe1_docentry']
								);
							}

						}else{
							return array(
								'error'   => true,
								'data'    => $resInsertEstado,
								'mensaje' => 'No se encontro la naturaleza del documento manual'
							);
						}
					}
					// PAGO EFECTUADO
					if ($document['pe1_doctype'] == 19) {

		
						if ($document['pe1_docentry'] == 0){

							$slqUpdateVenDebit = "UPDATE mac1
								SET ac1_ven_debit = :ac1_ven_debit
								WHERE ac1_font_key = :ac1_font_key
								AND ac1_font_type = :ac1_font_type
								AND ac1_debit > :ac1_debit";
								
					
							$resUpdateVenDebit = $this->ci->pedeo->updateRow($slqUpdateVenDebit, array(

									':ac1_ven_debit' => 0,
									':ac1_font_key'  => $document['pe1_docnum'],
									':ac1_font_type'  => $document['pe1_doctype'],
									':ac1_debit'     => 0

							));

							if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
							} else {
								

								return array(
									'error'   => true,
									'data'    => $resUpdateVenDebit,
									'mensaje' => 'No se pudo actualizar el valor del pago efectuado ' . $document['pe1_docentry']
								);

							}

						}else{

							$slqUpdateVenDebit = "UPDATE mac1
								SET ac1_ven_credit = ac1_ven_credit - :ac1_ven_credit
								WHERE ac1_line_num = :ac1_line_num";
					
							$resUpdateVenDebit = $this->ci->pedeo->updateRow($slqUpdateVenDebit, array(

									':ac1_ven_credit' => $document['pe1_vlrpaid'],
									':ac1_line_num'  => $document['pe1_line_num'],

							));

							if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
							} else {
								

								return array(
									'error'   => true,
									'data'    => $resUpdateVenDebit,
									'mensaje' => 'No se pudo actualizar el valor del pago efectuado ' . $document['pe1_docentry']
								);

							}
						}


					}

				}

				return  array(
					'error' => false,
					'data' => [],
					'mensaje' => 'Sin error'
				);

			}else{
				return  array(
					'error' => true,
					'data' => $resDocuments,
					'mensaje' => 'No se encontro el detalle de los documentos que fueron afectados en el pago'
				);
			}

		}else{
			return  array(
				'error' => true,
				'data' => $doctype,
				'mensaje' => 'Tipo de documento incorrecto para realizar esta operación, (Anulación de pago realizado)'
			);
		}

		
	}

	// reversar movimiento de los documentos originales, para el caso de compensacion de cuentas entre terceros
	public function reverseCompensation($docentry, $doctype, $anulacion, $tpa, $user) {

		//$docentry =  DOCENTRY DEL DOCUMENTO QUE SE ESTA ANULANDO 
		//$doctype =  DOCTYPE DEL DOCUMENTO QUE SE ESTA ANULANDO
		//$anulacion =  DOCENTRY DE LA ANULACION
		//$tpa == TIPO DE DOCUMENTO DE LA ANULACION

		if ($doctype == 22){

			// se buscan los documentos afectados en el movimiento 
			$sqlDocuments = "SELECT * FROM crc1 WHERE rc1_docentry = :pr1_docnum";
			$resDocuments = $this->ci->pedeo->queryTable($sqlDocuments, array(
				':pr1_docnum' => $docentry
			));

			if (isset($resDocuments[0])){


				
				foreach ($resDocuments as $key => $document) {
					// CASO DE DOCUMENTOS DE VENTAS
					// CASO PARA FACTURA NORMAL Y ANTICIPADA DE VENTAS
					if ( $document['rc1_basetype']  == 5 || $document['rc1_basetype'] == 34 ){

						// PAY TODAY
						$sqlUpdateFactPay = "UPDATE  dvfv  SET dvf_paytoday = COALESCE(dvf_paytoday,0)-:dvf_paytoday WHERE dvf_docentry = :dvf_docentry and dvf_doctype = :dvf_doctype";

						$resUpdateFactPay = $this->ci->pedeo->updateRow($sqlUpdateFactPay, array(

							':dvf_paytoday' => $document['rc1_valapply'],
							':dvf_docentry' => $document['rc1_baseentry'],
							':dvf_doctype'  => $document['rc1_basetype']


						));

						if (is_numeric($resUpdateFactPay) && $resUpdateFactPay == 1) {
						} else {
							
							return array(
								'error'   => true,
								'data' 	  => $resUpdateFactPay,
								'mensaje' => 'No se pudo actualizar el valor del pago en la factura para el documento' . $document['rc1_baseentry']
							);
						}

						// ASIENTO DE LA FACTURA DONDE SE CAUSA LA CXC
						$slqUpdateVenDebit = "UPDATE mac1
											  SET ac1_ven_credit = ac1_ven_credit - :ac1_ven_credit
											  WHERE ac1_line_num = :ac1_line_num";

						$resUpdateVenDebit = $this->ci->pedeo->updateRow($slqUpdateVenDebit, array(

							':ac1_ven_credit' => $document['rc1_valapply'],
							':ac1_line_num'   => $document['rc1_line_num']


						));

						if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
						} else {
							

							return array(
								'error'   => true,
								'data'    => $resUpdateVenDebit,
								'mensaje' => 'No se pudo actualizar el valor del pago en la factura para el asiento' . $document['rc1_baseentry']
							);

							
						}

						// SE VALIDA EL SALDO PARA CAMBIAR O MANTENER EL ESTADO DEL DOCUMENTO
						if ( abs($document['rc1_doctotal']) == $document['rc1_valapply'] ){ // SI EL MONTO TOTAL DEL DOCUMENTO ES IGUAL AL MONTO APLICADO SE DEBE CAMBIAR EL ESTADO PUESTO QUE EL DOCUMENTO ESTA CERRADO ACTUALMENTE

							$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
												VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

							$resInsertEstado = $this->ci->pedeo->insertRow($sqlInsertEstado, array(

								':bed_docentry' => $document['rc1_baseentry'],
								':bed_doctype' => $document['rc1_basetype'],
								':bed_status' => 1, //ESTADO ABIERTO
								':bed_createby' => $user,
								':bed_date' => date('Y-m-d'),
								':bed_baseentry' => $anulacion,
								':bed_basetype' => $tpa
							));


							if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
							} else {

								return array(
									'error'   => true,
									'data'    => $resInsertEstado,
									'mensaje' => 'No se pudo cambiar el estado del documento'
								);
							}
						}

					}

					// SI EL DOCUMENTO ES UNA FACTURA POS REALIZADA POR EL MODULO DE  DISTRIBUCION 
					if ( $document['rc1_basetype']  == 47 ) {

						// SE VALIDA QUE NO SEA UN ANTICIPO
						$EsAnticipoDoc = 0;

						$sqlPsnk = "SELECT 
										CASE 
											WHEN ac1_credit > 0 THEN 1
											ELSE 0
										END AS op	
										FROM mac1 
										WHERE ac1_line_num = :ac1_line_num";

						$resPsnk = $this->ci->pedeo->queryTable($sqlPsnk, array(
							':ac1_line_num' => $document['rc1_line_num']
						));

						if (isset($resPsnk[0])){

							if ( $resPsnk[0]['op'] == 1 ){
								$EsAnticipoDoc = 1;
							}else{
								$EsAnticipoDoc = 0;
							}

						}else{
							return array(
								'error'   => true,
								'data'	  => $resPsnk,
								'mensaje'	=> 'No se pudo validar el tipo de operación para el tipo de documento 47'
							);
						}


						// PAY TODAY
						// 0 QUE INDICA QUE ES UNA FACTURA O RECIBO MAS NO EL ANTICIPO
						if ($EsAnticipoDoc == 0){

							$sqlUpdateFactPay = "UPDATE  dvrc  SET vrc_paytoday = COALESCE(vrc_paytoday,0)-:vrc_paytoday WHERE vrc_docentry = :vrc_docentry and vrc_doctype = :vrc_doctype";

							$resUpdateFactPay = $this->ci->pedeo->updateRow($sqlUpdateFactPay, array(

								':vrc_paytoday' => $document['rc1_valapply'],
								':vrc_docentry' => $document['rc1_baseentry'],
								':vrc_doctype'  => $document['rc1_basetype']


							));

							if (is_numeric($resUpdateFactPay) && $resUpdateFactPay == 1) {
							} else {
								return array(
									'error'   => true,
									'data' => $resUpdateFactPay,
									'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura ' . $document['rc1_baseentry']
								);
							}
						}

						// ASIENTO DE LA FACTURA DONDE SE CAUSA LA CXC O EL ANTICIPO A CUENTA

						$slqUpdateVenDebit = "";

						if ($EsAnticipoDoc == 0){

							$slqUpdateVenDebit = "UPDATE mac1
							SET ac1_ven_credit = ac1_ven_credit - :ac1_ven
							WHERE ac1_line_num = :ac1_line_num";

						}else{
							$slqUpdateVenDebit = "UPDATE mac1
							SET ac1_ven_debit = ac1_ven_debit - :ac1_ven
							WHERE ac1_line_num = :ac1_line_num";
						}

						$resUpdateVenDebit = $this->ci->pedeo->updateRow($slqUpdateVenDebit, array(

							':ac1_ven' 		=> $document['rc1_valapply'],
							':ac1_line_num' => $document['rc1_line_num']


						));

						if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
						} else {

							return array(
								'error'   => true,
								'data' 	  => $resUpdateVenDebit,
								'mensaje' => 'No se pudo actualizar el valor del pago en la factura o anticipo' . $document['rc1_baseentry']
							);
						}

						// SOLO CUANDO NO ES EL ANTICIPO
						if ($EsAnticipoDoc == 0){
							// SE VALIDA EL SALDO PARA CAMBIAR O MANTENER EL ESTADO DEL DOCUMENTO
							if ( abs($document['rc1_doctotal']) == $document['rc1_valapply'] ){ // SI EL MONTO TOTAL DEL DOCUMENTO ES IGUAL AL MONTO APLICADO SE DEBE CAMBIAR EL ESTADO PUESTO QUE EL DOCUMENTO ESTA CERRADO ACTUALMENTE

								$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
													VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

								$resInsertEstado = $this->ci->pedeo->insertRow($sqlInsertEstado, array(

									':bed_docentry' => $document['rc1_baseentry'],
									':bed_doctype' => $document['rc1_basetype'],
									':bed_status' => 1, //ESTADO ABIERTO
									':bed_createby' => $user,
									':bed_date' => date('Y-m-d'),
									':bed_baseentry' => $anulacion,
									':bed_basetype' => $tpa
								));


								if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
								} else {

									return array(
										'error'   => true,
										'data'    => $resInsertEstado,
										'mensaje' => 'No se pudo cambiar el estado del documento'
									);
								}
							}
						}
					}

					// NOTA CREDITO
					// SE ACTUALIZA EL VALOR DEL CAMPO PAY TODAY EN NOTA CREDITO
					if ($document['rc1_basetype'] == 6) { // SOLO CUANDO ES UNA NOTA CREDITO

						// PAY TODAY
						$sqlUpdateFactPay = "UPDATE  dvnc  SET vnc_paytoday = COALESCE(vnc_paytoday,0)-:vnc_paytoday WHERE vnc_docentry = :vnc_docentry and vnc_doctype = :vnc_doctype";

						$resUpdateFactPay = $this->ci->pedeo->updateRow($sqlUpdateFactPay, array(

							':vnc_paytoday' => $document['rc1_valapply'],
							':vnc_docentry' => $document['rc1_baseentry'],
							':vnc_doctype'  => $document['rc1_basetype']


						));

						if (is_numeric($resUpdateFactPay) && $resUpdateFactPay == 1) {
						} else {

							return array(
								'error'   => true,
								'data'    => $resUpdateFactPay,
								'mensaje' => 'No se pudo actualizar el valor del pago en la nota credito ' . $document['rc1_baseentry']
							);
						}

						// LINEA DE ASIENTO CONTABLE DONDE SE CAUSO DE VUELTA LA CXC
						$slqUpdateVenDebit = "UPDATE mac1
							SET ac1_ven_debit = ac1_ven_debit - :ac1_ven_debit
							WHERE ac1_line_num = :ac1_line_num";

						$resUpdateVenDebit = $this->ci->pedeo->updateRow($slqUpdateVenDebit, array(

							':ac1_ven_debit' => $document['rc1_valapply'],
							':ac1_line_num' => $document['rc1_line_num']

						));

						if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
						} else {

							return array(
								'error'   => true,
								'data'    => $resUpdateFactPay,
								'mensaje' => 'No se pudo actualizar el valor del pago en la factura ' . $document['rc1_baseentry']
							);
						}

						// SE VALIDA EL SALDO PARA CAMBIAR O MANTENER EL ESTADO DEL DOCUMENTO
						if ( abs($document['rc1_doctotal']) == $document['rc1_valapply'] ){ // SI EL MONTO TOTAL DEL DOCUMENTO ES IGUAL AL MONTO APLICADO SE DEBE CAMBIAR EL ESTADO PUESTO QUE EL DOCUMENTO ESTA CERRADO ACTUALMENTE

							$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
												VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

							$resInsertEstado = $this->ci->pedeo->insertRow($sqlInsertEstado, array(

								':bed_docentry' => $document['rc1_baseentry'],
								':bed_doctype' => $document['rc1_basetype'],
								':bed_status' => 1, //ESTADO ABIERTO
								':bed_createby' => $user,
								':bed_date' => date('Y-m-d'),
								':bed_baseentry' => $anulacion,
								':bed_basetype' => $tpa
							));


							if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
							} else {

								return array(
									'error'   => true,
									'data'    => $resInsertEstado,
									'mensaje' => 'No se pudo cambiar el estado del documento'
								);
							}
						}
					}

					// ASIENTO MANUALES
					if ($document['rc1_basetype'] == 18) {

						// PRIMERO SE VERIFICA DE QUE LADO ESTA EL SALDO (O ES DEBITO O ES CREDITO)
						$sqlLadoCD = "SELECT 
							CASE 
								WHEN ac1_credit > 0 THEN 1
								ELSE 0
							END AS op	
							FROM mac1 
							WHERE ac1_line_num = :ac1_line_num";

						$resLadoCD = $this->ci->pedeo->queryTable($sqlLadoCD, array(
							':ac1_line_num' => $document['rc1_line_num']
						));

						if (isset($resLadoCD[0])){

							$slqUpdateVenDebit = "";

							if ($resLadoCD[0]['op'] == 1){ // SI ES CREDITO

								$slqUpdateVenDebit = "UPDATE mac1
									SET ac1_ven_debit = ac1_ven_debit - :ac1_ven
									WHERE ac1_line_num = :ac1_line_num";

							}else{ // SI ES DEBITO
								$slqUpdateVenDebit = "UPDATE mac1
									SET ac1_ven_credit = ac1_ven_credit - :ac1_ven
									WHERE ac1_line_num = :ac1_line_num";
							}

							$resUpdateVenDebit = $this->ci->pedeo->updateRow($slqUpdateVenDebit, array(

								':ac1_ven'      => $document['rc1_valapply'],
								':ac1_line_num' => $document['rc1_line_num']
							));

							if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
							} else {
								
								return array(
									'error'   => true,
									'data' 		=> $resUpdateVenDebit,
									'mensaje'	=> 'No se pudo actualizar el valor del pago (documento manual)' . $document['rc1_baseentry']
								);

								
							}

							$sqlComp = "SELECT ac1_ven_debit,ac1_ven_credit FROM mac1 WHERE ac1_line_num = :ac1_line_num";
							$resComp = $this->ci->pedeo->queryTable($sqlComp, array(":ac1_line_num" => $document['rc1_line_num']));


							if ($resLadoCD[0]['op'] == 1){ // SI ES CREDITO

								if ( $resComp[0]['ac1_ven_debit'] < 0 ){

									return array(
										'error'     => true,
										'data' 		=> $resComp,
										'mensaje'	=> 'El valor a descontar supero el total del documento'
									);
								}
								

							}else{ // SI ES DEBITO

								if ( $resComp[0]['ac1_ven_credit'] < 0 ){

									return array(
										'error'     => true,
										'data' 		=> $resComp,
										'mensaje'	=> 'El valor a descontar supero el total del documento'
									);
								}
							}

						}else{
							return array(
								'error'   => true,
								'data'    => $resInsertEstado,
								'mensaje' => 'No se encontro la naturaleza del documento manual'
							);
						}

					}

					// CASO PARA DOCUMENTOS DE COMPRAS
					//CASO PARA FACTURA NORMAL Y ANTICIDAPA DE COMPRAS
					if ($document['rc1_basetype'] == 15 || $document['rc1_basetype'] == 46) {

						//PAY TODAY
						$sqlUpdateFactPay = "UPDATE  dcfc  SET cfc_paytoday = COALESCE(cfc_paytoday,0)-:cfc_paytoday WHERE cfc_docentry = :cfc_docentry and cfc_doctype = :cfc_doctype";

						$resUpdateFactPay = $this->ci->pedeo->updateRow($sqlUpdateFactPay, array(
							
							':cfc_paytoday' => $document['rc1_valapply'],
							':cfc_docentry' => $document['rc1_baseentry'],
							':cfc_doctype' =>  $document['rc1_basetype']

						));

						if (is_numeric($resUpdateFactPay) && $resUpdateFactPay > 0) {
						} else {
							

							return array(
								'error'   => true,
								'data' => $resUpdateFactPay,
								'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura ' . $document['rc1_baseentry']
							);

							
						}
						// ASIENTO DE LA FACTURA DONDE SE CAUSA LA CXP
						$slqUpdateVenDebit = "UPDATE mac1
							SET ac1_ven_debit = ac1_ven_debit - :ac1_ven_debit
							WHERE ac1_line_num = :ac1_line_num";

						$resUpdateVenDebit = $this->ci->pedeo->updateRow($slqUpdateVenDebit, array(

							':ac1_ven_debit' => $document['rc1_valapply'],
							':ac1_line_num'  => $document['rc1_line_num']

						));

						if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
						} else {
						
							return array(
								'error'   => true,
								'data' => $resUpdateVenDebit,
								'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura ' . $document['rc1_baseentry']
							);
						}

						// SE VALIDA EL SALDO PARA CAMBIAR O MANTENER EL ESTADO DEL DOCUMENTO
						if ( abs($document['rc1_doctotal']) == $document['rc1_valapply'] ) {  // SI EL MONTO TOTAL DEL DOCUMENTO ES IGUAL AL MONTO APLICADO SE DEBE CAMBIAR EL ESTADO PUESTO QUE EL DOCUMENTO ESTA CERRADO ACTUALMENTE
							$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
												VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

							$resInsertEstado = $this->ci->pedeo->insertRow($sqlInsertEstado, array(

								':bed_docentry' => $document['rc1_baseentry'],
								':bed_doctype' => $document['rc1_basetype'],
								':bed_status' => 1, //ESTADO ABIERTO
								':bed_createby' => $user,
								':bed_date' => date('Y-m-d'),
								':bed_baseentry' => $anulacion,
								':bed_basetype' => $tpa
							));


							if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
							} else {

								return array(
									'error'   => true,
									'data'    => $resInsertEstado,
									'mensaje' => 'No se pudo cambiar el estado del documento'
								);
							}
						}


					}

					// CASO PARA SOLICTUD DE ANTICPO PROVEEDOR
					if ($document['rc1_basetype'] == 36) { // SOLO CUANDO ES UNA SOLICITUD DE ANTICIPO PROVEEDOR

						// PAY TODAY
						$sqlUpdateFactPay = "UPDATE  dcsa  SET csa_paytoday = COALESCE(csa_paytoday,0)-:csa_paytoday WHERE csa_docentry = :csa_docentry and csa_doctype = :csa_doctype";

						$resUpdateFactPay = $this->ci->pedeo->updateRow($sqlUpdateFactPay, array(

							':csa_paytoday' => $document['rc1_valapply'],
							':csa_docentry' => $document['rc1_baseentry'],
							':csa_doctype'  => $document['rc1_basetype']


						));

						if (is_numeric($resUpdateFactPay) && $resUpdateFactPay == 1) {
						} else {

							return array(
								'error'   => true,
								'data' 	  => $resUpdateFactPay,
								'mensaje' => 'No se pudo actualizar el valor del pago en la solicitud del anticipo ' . $document['rc1_baseentry']
							);

						}

						// SE VALIDA EL SALDO PARA CAMBIAR O MANTENER EL ESTADO DEL DOCUMENTO
						if ( abs($document['rc1_doctotal']) == $document['rc1_valapply'] ) { 

							$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
											VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

							$resInsertEstado = $this->ci->pedeo->insertRow($sqlInsertEstado, array(

								':bed_docentry' => $document['rc1_baseentry'],
								':bed_doctype' => $document['rc1_basetype'],
								':bed_status' => 1, //ESTADO ABIERTO
								':bed_createby' => $user,
								':bed_date' => date('Y-m-d'),
								':bed_baseentry' => $anulacion,
								':bed_basetype' => $tpa
							));


							if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
							} else {

								return array(
									'error'   => true,
									'data'    => $resInsertEstado,
									'mensaje' => 'No se pudo cambiar el estado del documento'
								);
							}

						}

						//--

					}

					// NOTA CREDITO DE COMRPAS
					// SE ACTUALIZA EL VALOR DEL CAMPO PAY TODAY EN NOTA CREDITO
					if ($document['rc1_basetype'] == 16) { // SOLO CUANDO ES UNA NOTA CREDITO

						// PAY TODAY
						$sqlUpdateFactPay = "UPDATE  dcnc  SET cnc_paytoday = COALESCE(cnc_paytoday,0)-:cnc_paytoday WHERE cnc_docentry = :cnc_docentry and cnc_doctype = :cnc_doctype";

						$resUpdateFactPay = $this->ci->pedeo->updateRow($sqlUpdateFactPay, array(

							':cnc_paytoday' => $document['rc1_valapply'],
							':cnc_docentry' => $document['rc1_baseentry'],
							':cnc_doctype'  => $document['rc1_basetype']


						));

						if (is_numeric($resUpdateFactPay) && $resUpdateFactPay == 1) {
						} else {
							

							return array(
								'error'   => true,
								'data' => $resUpdateFactPay,
								'mensaje'	=> 'No se pudo actualizar el valor del pago en la nota credito ' . $document['rc1_baseentry']
							);

							
						}

						// ASIENTO DE LA CONTRA PARTIDAD PARA LA CXP

						$slqUpdateVenDebit = "UPDATE mac1
						SET ac1_ven_credit = ac1_ven_credit - :ac1_ven_credit
						WHERE ac1_line_num = :ac1_line_num";

						$resUpdateVenDebit = $this->ci->pedeo->updateRow($slqUpdateVenDebit, array(

							':ac1_ven_credit' => $document['rc1_valapply'],
							':ac1_line_num'   => $document['rc1_line_num'],


						));

						if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
						} else {
							

							return array(
								'error'   => true,
								'data' => $resUpdateFactPay,
								'mensaje'	=> 'No se pudo actualizar el valor del pago en la CXP de la nota credito ' . $document['rc1_baseentry']
							);

						}

						// SE VALIDA EL SALDO PARA CAMBIAR O MANTENER EL ESTADO DEL DOCUMENTO
						if ( abs($document['rc1_doctotal']) == $document['rc1_valapply'] ) { 

							$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
											VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

							$resInsertEstado = $this->ci->pedeo->insertRow($sqlInsertEstado, array(

								':bed_docentry' => $document['rc1_baseentry'],
								':bed_doctype' => $document['rc1_basetype'],
								':bed_status' => 1, //ESTADO ABIERTO
								':bed_createby' => $user,
								':bed_date' => date('Y-m-d'),
								':bed_baseentry' => $anulacion,
								':bed_basetype' => $tpa
							));


							if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
							} else {

								return array(
									'error'   => true,
									'data'    => $resInsertEstado,
									'mensaje' => 'No se pudo cambiar el estado del documento'
								);
							}

						}
					}

					//PAGO RECIBIDO
					if ( $document['rc1_basetype']  == 20 ){

						$slqUpdateVenDebit = "UPDATE mac1
												SET ac1_ven_debit = ac1_ven_debit - :ac1_ven_debit
												WHERE ac1_line_num = :ac1_line_num";

						$resUpdateVenDebit = $this->ci->pedeo->updateRow($slqUpdateVenDebit, array(

								':ac1_ven_debit' => $document['rc1_valapply'],
								':ac1_line_num'  => $document['rc1_line_num']

						));

						if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
						} else {
							
							return array(
								'error'   => true,
								'data'    => $resUpdateVenDebit,
								'mensaje' => 'No se puedo actualizar el valor del pago recibido ' . $document['rc1_baseentry']
							);

						}
					}

					// PAGO EFECTUADO
					if ($document['rc1_basetype'] == 19) {

						$slqUpdateVenDebit = "UPDATE mac1
												SET ac1_ven_credit = ac1_ven_credit - :ac1_ven_credit
												WHERE ac1_line_num = :ac1_line_num";
										
						$resUpdateVenDebit = $this->ci->pedeo->updateRow($slqUpdateVenDebit, array(

								':ac1_ven_credit' => $document['rc1_valapply'],
								':ac1_line_num'  => $document['rc1_line_num'],

						));

						if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
						} else {
							
							return array(
								'error'   => true,
								'data'    => $resUpdateVenDebit,
								'mensaje' => 'No se pudo actualizar el valor del pago efectuado ' . $document['rc1_baseentry']
							);
						}
					}
				}


				return  array(
					'error' => false,
					'data' => [],
					'mensaje' => 'Sin error'
				);

			}else{
				return  array(
					'error' => true,
					'data' => [],
					'mensaje' => 'No se encontro el detalle de los documentos que fueron afectados en el pago'
				);
			}

		}else{

			return  array(
				'error' => true,
				'data' => [],
				'mensaje' => 'Tipo de documento incorrecto para realizar esta operación, (Anulación de pago recibido)'
			);
		}

	}
}