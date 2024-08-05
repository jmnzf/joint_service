<?php
// PAGOS RECIBIDOS
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class PaymentsReceived extends REST_Controller
{

	private $pdo;

	public function __construct()
	{

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
		$this->load->library('pedeo', [$this->pdo]);
		$this->load->library('generic');
		$this->load->library('DocumentNumbering');
		$this->load->library('CancelPay');
		$this->load->library('account');
	}

	// Obtener pagos recibidos
	public function getPaymentsReceived_get()
	{

		$Data = $this->get();

		if (!isset($Data['business']) OR !isset($Data['branch'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}


		$filtro = "";
		if (!empty($Data['bpr_cardcode'])) {
			$filtro .= " AND bpr_cardcode  = '{$Data['bpr_cardcode']}'";
		}
		if (!empty($Data['bpr_docdate'])) {
			$filtro .= " AND bpr_docdate  BETWEEN '{$Data['bpr_docdate']}'  AND '{$Data['bpr_taxdate']}' ";
		}

		if (!empty($Data['bpr_createby'])) {
			$filtro .= " AND bpr_createby  = '{$Data['bpr_createby']}'";
		}


		$sqlSelect = "SELECT 
						coalesce(r.estado,'Cerrado') as estado ,
						concat(bpr_currency,' ',trim(to_char(bpr_vlrpaid, '999,999,999.00'))) as bpr_vlrpaid,
						bpr_docentry,
						bpr_cardcode,
						bpr_cardname,
						bpr_address,
						bpr_perscontact,
						bpr_series,
						bpr_docnum,
						bpr_docdate,
						bpr_taxdate,
						bpr_ref,
						bpr_transid,
						bpr_comments,
						bpr_memo,
						bpr_acctransfer,
						bpr_datetransfer,
						bpr_reftransfer,
						bpr_doctotal,
						bpr_project,
						bpr_createby,
						bpr_createat,
						bpr_payment,
						bpr_doctype,
						bpr_currency,
						bpr_paytoday,
						business,
						branch
					from
						gbpr
					left join
						responsestatus r on gbpr.bpr_docentry = r.id and gbpr.bpr_doctype = r.tipo 
					where
						1 = 1
						and business = :business
						and branch = :branch " . $filtro;
		// print_r($sqlSelect);exit;
		$resSelect = $this->pedeo->queryTable($sqlSelect, array(
			':business' => $Data['business'],
			':branch' => $Data['branch']
		));

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

	//CREAR NUEVO PAGO
	public function createPaymentsReceived_post()
	{

		$DECI_MALES =  $this->generic->getDecimals();
		$Data = $this->post();

		$DocNumVerificado = 0;
		$DetalleAsientoCuentaTercero = new stdClass();
		$DetalleConsolidadoAsientoCuentaTercero = [];
		$llaveAsientoCuentaTercero = "";
		$posicionAsientoCuentaTercero = 0;
		$cuentaTercero = 0;
		$inArrayAsientoCuentaTercero = array();
		$VlrTotalOpc = 0;
		$VlrDiff = 0;
		$VlrPagoEfectuado = 0;
		$TasaOrg = 0;
		$VlrDiffN = 0;
		$VlrDiffP = 0;
		$DFPC = 0; // diferencia en peso credito
		$DFPD = 0; // diferencia en peso debito
		$DFPCS = 0; // del sistema en credito
		$DFPDS = 0; // del sistema en debito
		// Se globaliza la variable sqlDetalleAsiento
		$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
							ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
							ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
							ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, business, branch)VALUES (:ac1_trans_id, :ac1_account,
							:ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
							:ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
							:ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
							:ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref, :business, :branch)";

		if (!isset($Data['detail']) or !isset($Data['bpr_billpayment']) OR !isset($Data['business']) OR !isset($Data['branch'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$ContenidoDetalle = json_decode($Data['detail'], true);


		if (!is_array($ContenidoDetalle)) {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro el detalle del pago'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		// SE VALIDA QUE EL DOCUMENTO TENGA CONTENIDO
		if (!intval(count($ContenidoDetalle)) > 0) {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'Documento sin detalle'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}
		//
		//
		//VALIDANDO PERIODO CONTABLE
		$periodo = $this->generic->ValidatePeriod($Data['bpr_docdate'], $Data['bpr_docdate'], $Data['bpr_docdate'], 0);

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
		//
		// //BUSCANDO LA NUMERACION DEL DOCUMENTO
		$DocNumVerificado = $this->documentnumbering->NumberDoc($Data['bpr_series'],$Data['bpr_docdate'],$Data['bpr_duedate']);
		
		if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0){

		}else if ($DocNumVerificado['error']){

			return $this->response($DocNumVerificado, REST_Controller::HTTP_BAD_REQUEST);
		}

		//Obtener Carpeta Principal del Proyecto
		$sqlMainFolder = " SELECT * FROM params";
		$resMainFolder = $this->pedeo->queryTable($sqlMainFolder, array());

		if (!isset($resMainFolder[0])) {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro la caperta principal del proyecto'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}


		// PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO
		// SE BUSCA LA MONEDA LOCAL PARAMETRIZADA
		$sqlMonedaLoc = "SELECT pgm_symbol FROM pgec WHERE pgm_principal = :pgm_principal";
		$resMonedaLoc = $this->pedeo->queryTable($sqlMonedaLoc, array(':pgm_principal' => 1));

		if (isset($resMonedaLoc[0])) {
		} else {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro la moneda local.'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$MONEDALOCAL = trim($resMonedaLoc[0]['pgm_symbol']);

		// SE BUSCA LA MONEDA DE SISTEMA PARAMETRIZADA
		$sqlMonedaSys = "SELECT pgm_symbol FROM pgec WHERE pgm_system = :pgm_system";
		$resMonedaSys = $this->pedeo->queryTable($sqlMonedaSys, array(':pgm_system' => 1));

		if (isset($resMonedaSys[0])) {
		} else {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro la moneda de sistema.'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}


		$MONEDASYS = trim($resMonedaSys[0]['pgm_symbol']);

		//SE BUSCA LA TASA DE CAMBIO CON RESPECTO A LA MONEDA QUE TRAE EL DOCUMENTO A CREAR CON LA MONEDA LOCAL
		// Y EN LA MISMA FECHA QUE TRAE EL DOCUMENTO
		$sqlBusTasa = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
		$resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $Data['bpr_currency'], ':tsa_date' => $Data['bpr_docdate']));

		if (isset($resBusTasa[0])) {
		} else {

			if (trim($Data['bpr_currency']) != $MONEDALOCAL) {

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'No se encrontro la tasa de cambio para la moneda: ' . $Data['bpr_currency'] . ' en la actual fecha del documento: ' . $Data['bpr_docdate'] . ' y la moneda local: ' . $resMonedaLoc[0]['pgm_symbol']
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
		}

		$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
		$resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $Data['bpr_docdate']));

		if (isset($resBusTasa2[0])) {
		} else {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :' . $Data['bpr_docdate']
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

		if (isset($resCuentaTercero[0])) {

			$cuentaTercero = $resCuentaTercero[0]['mgs_acct'];
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => $resCuentaTercero,
				'mensaje'	=> 'No se pudo registrar el pago, el tercero no tiene la cuenta asociada (' . $Data['bpr_cardcode'] . ')'
			);

			$this->response($respuesta);

			return;
		}

		//FIN BUSQUEDA CUENTA TERCERO

		$sqlInsert = "INSERT INTO
                            	gbpr (bpr_cardcode,bpr_doctype,bpr_cardname,bpr_address,bpr_perscontact,bpr_series,bpr_docnum,bpr_docdate,bpr_taxdate,bpr_ref,bpr_transid,
                                    bpr_comments,bpr_memo,bpr_acctransfer,bpr_datetransfer,bpr_reftransfer,bpr_doctotal,bpr_vlrpaid,bpr_project,bpr_createby,
                                    bpr_createat,bpr_payment,bpr_currency, business, branch,bpr_levelcash)
                      VALUES (:bpr_cardcode,:bpr_doctype,:bpr_cardname,:bpr_address,:bpr_perscontact,:bpr_series,:bpr_docnum,:bpr_docdate,:bpr_taxdate,:bpr_ref,:bpr_transid,
                              :bpr_comments,:bpr_memo,:bpr_acctransfer,:bpr_datetransfer,:bpr_reftransfer,:bpr_doctotal,:bpr_vlrpaid,:bpr_project,:bpr_createby,
                              :bpr_createat,:bpr_payment,:bpr_currency, :business, :branch,:bpr_levelcash)";


		// Se Inicia la transaccion,
		// Todas las consultas de modificacion siguientes
		// aplicaran solo despues que se confirme la transaccion,
		// de lo contrario no se aplicaran los cambios y se devolvera
		// la base de datos a su estado original.

		$this->pedeo->trans_begin();

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			':bpr_cardcode' => isset($Data['bpr_cardcode']) ? $Data['bpr_cardcode'] : NULL,
			':bpr_doctype' => is_numeric($Data['bpr_doctype']) ? $Data['bpr_doctype'] : 0,
			':bpr_cardname' => isset($Data['bpr_cardname']) ? $Data['bpr_cardname'] : NULL,
			':bpr_address' => isset($Data['bpr_address']) ? $Data['bpr_address'] : NULL,
			':bpr_perscontact' => is_numeric($Data['bpr_perscontact']) ? $Data['bpr_perscontact'] : 0,
			':bpr_series' => is_numeric($Data['bpr_series']) ? $Data['bpr_series'] : 0,
			':bpr_docnum' => $DocNumVerificado,
			':bpr_docdate' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
			':bpr_taxdate' => $this->validateDate($Data['bpr_taxdate']) ? $Data['bpr_taxdate'] : NULL,
			':bpr_ref' => isset($Data['bpr_ref']) ? $Data['bpr_ref'] : NULL,
			':bpr_transid' => is_numeric($Data['bpr_transid']) ? $Data['bpr_transid'] : 0,
			':bpr_comments' => isset($Data['bpr_comments']) ? $Data['bpr_comments'] : NULL,
			':bpr_memo' => isset($Data['bpr_memo']) ? $Data['bpr_memo'] : NULL,
			':bpr_acctransfer' => isset($Data['bpr_acctransfer']) ? $Data['bpr_acctransfer'] : NULL,
			':bpr_datetransfer' => $this->validateDate($Data['bpr_datetransfer']) ? $Data['bpr_datetransfer'] : NULL,
			':bpr_reftransfer' => isset($Data['bpr_reftransfer']) ? $Data['bpr_reftransfer'] : NULL,
			':bpr_doctotal' => is_numeric($Data['bpr_doctotal']) ? $Data['bpr_doctotal'] : 0,
			':bpr_vlrpaid' => is_numeric($Data['bpr_vlrpaid']) ? $Data['bpr_vlrpaid'] : 0,
			':bpr_project' => isset($Data['bpr_project']) ? $Data['bpr_project'] : NULL,
			':bpr_createby' => isset($Data['bpr_createby']) ? $Data['bpr_createby'] : NULL,
			':bpr_createat' => $this->validateDate($Data['bpr_createat']) ? $Data['bpr_createat'] : NULL,
			':bpr_payment' => isset($Data['bpr_payment']) ? $Data['bpr_payment'] : NULL,
			':bpr_currency' => isset($Data['bpr_currency']) ? $Data['bpr_currency'] : NULL,
			':business' => $Data['business'],
			':branch' => $Data['branch'],
			':bpr_levelcash' => $Data['bpr_levelcash']
		));

		if (is_numeric($resInsert) && $resInsert > 0) {

			// Se actualiza la serie de la numeracion del documento

			$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
										WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id'      => $Data['bpr_series']
			));


			if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
			} else {
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

			// se agregar el estado del documento cerrado por defecto
			$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
							VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

			$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


				':bed_docentry' => $resInsert,
				':bed_doctype' => $Data['bpr_doctype'],
				':bed_status' => 3, //ESTADO CERRADO
				':bed_createby' => $Data['bpr_createby'],
				':bed_date' => date('Y-m-d'),
				':bed_baseentry' => NULL,
				':bed_basetype' => NULL
			));


			if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
			} else {

				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data' => $resInsertEstado,
					'mensaje'	=> 'No se pudo registrar el pago'
				);


				$this->response($respuesta);

				return;
			}



			//Se agregan los asientos contables*/*******

			$sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user, business, branch)
								 VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user, :business, :branch)";


			$resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

				':mac_doc_num' => 1,
				':mac_status' => 1,
				':mac_base_type' => is_numeric($Data['bpr_doctype']) ? $Data['bpr_doctype'] : 0,
				':mac_base_entry' => $resInsert,
				':mac_doc_date' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
				':mac_doc_duedate' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
				':mac_legal_date' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
				':mac_ref1' => is_numeric($Data['bpr_doctype']) ? $Data['bpr_doctype'] : 0,
				':mac_ref2' => "",
				':mac_ref3' => "",
				':mac_loc_total' => is_numeric($Data['bpr_doctotal']) ? $Data['bpr_doctotal'] : 0,
				':mac_fc_total' => is_numeric($Data['bpr_doctotal']) ? $Data['bpr_doctotal'] : 0,
				':mac_sys_total' => is_numeric($Data['bpr_doctotal']) ? $Data['bpr_doctotal'] : 0,
				':mac_trans_dode' => 1,
				':mac_beline_nume' => 1,
				':mac_vat_date' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
				':mac_serie' => 1,
				':mac_number' => 1,
				':mac_bammntsys' => 0,
				':mac_bammnt' => 0,
				':mac_wtsum' => 1,
				':mac_vatsum' => 0,
				':mac_comments' => isset($Data['bpr_comments']) ? $Data['bpr_comments'] : NULL,
				':mac_create_date' => $this->validateDate($Data['bpr_createat']) ? $Data['bpr_createat'] : NULL,
				':mac_made_usuer' => isset($Data['bpr_createby']) ? $Data['bpr_createby'] : NULL,
				':mac_update_date' => date("Y-m-d"),
				':mac_update_user' => isset($Data['bpr_createby']) ? $Data['bpr_createby'] : NULL,
				':business' => $Data['business'],
				':branch' => $Data['branch']
			));


			if (is_numeric($resInsertAsiento) && $resInsertAsiento > 0) {
				// Se verifica que el detalle no de error insertando //
			} else {

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
				$VrlPagoDetalleNormal  = 0;
				$Equiv = 0;
				$EsAnticipoDoc = 0;
				// SOLO SI NO ES UN ANTICIPO CLIENTE
				if ($Data['bpr_billpayment'] == '0' || $Data['bpr_billpayment'] == 0) {
					//VALIDAR EL VALOR QUE SE ESTA PAGANDO NO SEA MAYOR AL SALDO DEL DOCUMENTO
					if ($detail['pr1_doctype'] == 5 || $detail['pr1_doctype'] == 6 || $detail['pr1_doctype'] == 7 || $detail['pr1_doctype'] == 20 ||  $detail['pr1_doctype'] == 18 || $detail['pr1_doctype'] == 34 || $detail['pr1_doctype'] == 47) {

						$pf = "";
						$tb  = "";

						if ($detail['pr1_doctype'] == 5 || $detail['pr1_doctype'] == 34){
							$pf = "dvf";
							$tb  = "dvfv";
						} else if ($detail['pr1_doctype'] == 6) {
							$pf = "vnc";
							$tb  = "dvnc";
						} else if ($detail['pr1_doctype'] == 7) {
						} else if ($detail['pr1_doctype'] == 20) {
							$pf = "bpr";
							$tb  = "gbpr";
						}else if ($detail['pr1_doctype'] == 47){

							$sqlPsnk = "SELECT 
										CASE 
											WHEN ac1_credit > 0 THEN 1
											ELSE 0
										END AS op	
										FROM mac1 
										WHERE ac1_line_num = :ac1_line_num";
							$resPsnk = $this->pedeo->queryTable($sqlPsnk, array(
								':ac1_line_num' => $detail['ac1_line_num']
							));
							
							if (isset($resPsnk[0])){

								if ( $resPsnk[0]['op'] == 1 ){
									$pf = "";
									$tb  = "";
									$EsAnticipoDoc = 1;
								}else{
									$pf = "vrc";
									$tb  = "dvrc";
									$EsAnticipoDoc = 0;
								}

							}else{
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $resPsnk,
									'mensaje'	=> 'No se pudo validar el tipo de operación para el tipo de documento 47'
								);
				
								return $this->response($respuesta);
							}

						}

		
						$resVlrPay = $this->generic->validateBalance($detail['pr1_docentry'], $detail['pr1_doctype'], $tb, $pf, $detail['pr1_vlrpaid'], $Data['bpr_currency'], $Data['bpr_docdate'], 1, isset($detail['ac1_line_num']) ? $detail['ac1_line_num'] : 0);

						if (isset($resVlrPay['error'])) {

							if ($resVlrPay['error'] === false) {

								$VlrTotalOpc = $resVlrPay['vlrop'];
								$VlrDiff     = ($VlrDiff + $resVlrPay['vlrdiff']);
								$TasaOrg     = $resVlrPay['tasadoc'];
								$Equiv       = $resVlrPay['equiv'];

								$VlrTotalOpc = ($VlrTotalOpc + $Equiv);
							} else {
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => [],
									'mensaje'	=> $resVlrPay['mensaje']
								);

								$this->response($respuesta);

								return;
							}
						} else {

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data'    => [],
								'mensaje'	=> 'No se pudo validar el saldo actual del documento ' . $detail['pe1_docentry']
							);

							$this->response($respuesta);

							return;
						}
					}
				}



				$sqlInsertDetail = "INSERT INTO
                                        	bpr1 (pr1_docnum,pr1_docentry,pr1_numref,pr1_docdate,pr1_vlrtotal,pr1_vlrpaid,pr1_comments,pr1_porcdiscount,pr1_doctype,
                                            pr1_docduedate,pr1_daysbackw,pr1_vlrdiscount,pr1_ocrcode, pr1_accountid, pr1_line_num)
                                    VALUES (:pr1_docnum,:pr1_docentry,:pr1_numref,:pr1_docdate,:pr1_vlrtotal,:pr1_vlrpaid,:pr1_comments,:pr1_porcdiscount,
                                            :pr1_doctype,:pr1_docduedate,:pr1_daysbackw,:pr1_vlrdiscount,:pr1_ocrcode, :pr1_accountid ,:pr1_line_num)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':pr1_docnum' => $resInsert,
					':pr1_docentry' => is_numeric($detail['pr1_docentry']) ? $detail['pr1_docentry'] : 0,
					':pr1_numref' => isset($detail['pr1_numref']) ? $detail['pr1_numref'] : NULL,
					':pr1_docdate' =>  $this->validateDate($detail['pr1_docdate']) ? $detail['pr1_docdate'] : NULL,
					':pr1_vlrtotal' => is_numeric($detail['pr1_vlrtotal']) ? $detail['pr1_vlrtotal'] : 0,
					':pr1_vlrpaid' => is_numeric($detail['pr1_vlrpaid']) ? $detail['pr1_vlrpaid'] : 0,
					':pr1_comments' => isset($detail['pr1_comments']) ? $detail['pr1_comments'] : NULL,
					':pr1_porcdiscount' => is_numeric($detail['pr1_porcdiscount']) ? $detail['pr1_porcdiscount'] : 0,
					':pr1_doctype' => is_numeric($detail['pr1_doctype']) ? $detail['pr1_doctype'] : 0,
					':pr1_docduedate' => $this->validateDate($detail['pr1_docdate']) ? $detail['pr1_docdate'] : NULL,
					':pr1_daysbackw' => is_numeric($detail['pr1_daysbackw']) ? $detail['pr1_daysbackw'] : 0,
					':pr1_vlrdiscount' => is_numeric($detail['pr1_vlrdiscount']) ? $detail['pr1_vlrdiscount'] : 0,
					':pr1_ocrcode' => isset($detail['pr1_ocrcode']) ? $detail['pr1_ocrcode'] : NULL,
					':pr1_accountid' => is_numeric($detail['pr1_accountid']) ? $detail['pr1_accountid'] : 0,
					':pr1_line_num' => isset($detail['ac1_line_num']) && is_numeric($detail['ac1_line_num']) ? $detail['ac1_line_num'] : 0

				));

				// Se verifica que el detalle no de error insertando //
				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// SI NO ES UN ANTICIPO
					if ($Data['bpr_billpayment'] == '0' || $Data['bpr_billpayment'] == 0) {


						//MOVIMIENTO DE DOCUMENTOS
						if ($detail['pr1_doctype'] == 5 || $detail['pr1_doctype'] == 34 || $detail['pr1_doctype'] == 6 || $detail['pr1_doctype'] == 7) {
							//SE APLICA PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS
							if (isset($detail['pr1_docentry']) && is_numeric($detail['pr1_docentry']) && isset($detail['pr1_doctype']) && is_numeric($detail['pr1_doctype'])) {

								$sqlDocInicio = "SELECT bmd_tdi, bmd_ndi FROM tbmd WHERE  bmd_doctype = :bmd_doctype AND bmd_docentry = :bmd_docentry";
								$resDocInicio = $this->pedeo->queryTable($sqlDocInicio, array(
									':bmd_doctype' => $detail['pr1_doctype'],
									':bmd_docentry' => $detail['pr1_docentry']
								));


								if (isset($resDocInicio[0])) {

									$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
																										bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency)
																										VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
																										:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency)";

									$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

										':bmd_doctype' => is_numeric($Data['bpr_doctype']) ? $Data['bpr_doctype'] : 0,
										':bmd_docentry' => $resInsert,
										':bmd_createat' => $this->validateDate($Data['bpr_createat']) ? $Data['bpr_createat'] : NULL,
										':bmd_doctypeo' => is_numeric($detail['pr1_doctype']) ? $detail['pr1_doctype'] : 0, //ORIGEN
										':bmd_docentryo' => is_numeric($detail['pr1_docentry']) ? $detail['pr1_docentry'] : 0,  //ORIGEN
										':bmd_tdi' => $resDocInicio[0]['bmd_tdi'], // DOCUMENTO INICIAL
										':bmd_ndi' => $resDocInicio[0]['bmd_ndi'], // DOCUMENTO INICIAL
										':bmd_docnum' => $DocNumVerificado,
										':bmd_doctotal' => $VlrTotalOpc,
										':bmd_cardcode' => isset($detail['pr1_tercero']) ? $detail['pr1_tercero'] : NULL,
										':bmd_cardtype' => 1,
										':bmd_currency' => isset($Data['bpr_currency'])?$Data['bpr_currency']:NULL,
									));

									if (is_numeric($resInsertMD) && $resInsertMD > 0) {
									} else {

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

						//
						if ( $detail['pr1_doctype'] == 5 || $detail['pr1_doctype'] == 34 ) { // SOLO CUANDO ES UNA FACTURA

							$sqlUpdateFactPay = "UPDATE  dvfv  SET dvf_paytoday = COALESCE(dvf_paytoday,0)+:dvf_paytoday WHERE dvf_docentry = :dvf_docentry and dvf_doctype = :dvf_doctype";

							$resUpdateFactPay = $this->pedeo->updateRow($sqlUpdateFactPay, array(

								':dvf_paytoday' => round($VlrTotalOpc, $DECI_MALES),
								':dvf_docentry' => $detail['pr1_docentry'],
								':dvf_doctype'  => $detail['pr1_doctype']


							));

							if (is_numeric($resUpdateFactPay) && $resUpdateFactPay == 1) {
							} else {
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $resUpdateFactPay,
									'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura ' . $detail['pr1_docentry']
								);

								$this->response($respuesta);

								return;
							}
						}

						if ( $detail['pr1_doctype'] == 47 ) { // RECIBO  O ANTICIPO DE DISTRIBUCION

							if ($EsAnticipoDoc == 0){

								$sqlUpdateFactPay = "UPDATE  dvrc  SET vrc_paytoday = COALESCE(vrc_paytoday,0)+:vrc_paytoday WHERE vrc_docentry = :vrc_docentry and vrc_doctype = :vrc_doctype";

								$resUpdateFactPay = $this->pedeo->updateRow($sqlUpdateFactPay, array(
	
									':vrc_paytoday' => round($VlrTotalOpc, $DECI_MALES),
									':vrc_docentry' => $detail['pr1_docentry'],
									':vrc_doctype'  => $detail['pr1_doctype']
	
	
								));
	
								if (is_numeric($resUpdateFactPay) && $resUpdateFactPay == 1) {
								} else {
									$this->pedeo->trans_rollback();
	
									$respuesta = array(
										'error'   => true,
										'data' => $resUpdateFactPay,
										'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura ' . $detail['pr1_docentry']
									);
	
									$this->response($respuesta);
	
									return;
								}
							}
						}

						// SE ACTUALIZA EL VALOR DEL CAMPO PAY TODAY EN NOTA CREDITO
						if ($detail['pr1_doctype'] == 6) { // SOLO CUANDO ES UNA NOTA CREDITO

							$sqlUpdateFactPay = "UPDATE  dvnc  SET vnc_paytoday = COALESCE(vnc_paytoday,0)+:vnc_paytoday WHERE vnc_docentry = :vnc_docentry and vnc_doctype = :vnc_doctype";

							$resUpdateFactPay = $this->pedeo->updateRow($sqlUpdateFactPay, array(

								':vnc_paytoday' => round($VlrTotalOpc, $DECI_MALES),
								':vnc_docentry' => $detail['pr1_docentry'],
								':vnc_doctype'  => $detail['pr1_doctype']


							));

							if (is_numeric($resUpdateFactPay) && $resUpdateFactPay == 1) {
							} else {
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $resUpdateFactPay,
									'mensaje'	=> 'No se pudo actualizar el valor del pago en la nota credito ' . $detail['pr1_docentry']
								);

								$this->response($respuesta);

								return;
							}
						}




						// ACTUALIZAR REFERENCIA DE PAGO EN ASIENTO CONTABLE DE LA FACTURA
						if ($detail['pr1_doctype'] == 5 || $detail['pr1_doctype'] == 34 ) { // SOLO CUANDO ES UNA FACTURA


							$slqUpdateVenDebit = "UPDATE mac1
												SET ac1_ven_credit = ac1_ven_credit + :ac1_ven_credit
												WHERE ac1_line_num = :ac1_line_num";
							$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

								':ac1_ven_credit' => round($VlrTotalOpc, $DECI_MALES),
								':ac1_line_num' 	=> $detail['ac1_line_num']


							));

							if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
							} else {
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $resUpdateVenDebit,
									'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura ' . $detail['pr1_docentry']
								);

								$this->response($respuesta);

								return;
							}
						}

						//
						if ($detail['pr1_doctype'] == 47) { // RECIBO DE DISTRIBUCION O ANTICIPO 

							$slqUpdateVenDebit = "";

							if ($EsAnticipoDoc == 0){

								$slqUpdateVenDebit = "UPDATE mac1
								SET ac1_ven_credit = ac1_ven_credit + :ac1_ven
								WHERE ac1_line_num = :ac1_line_num";

							}else{
								$slqUpdateVenDebit = "UPDATE mac1
								SET ac1_ven_debit = ac1_ven_debit + :ac1_ven
								WHERE ac1_line_num = :ac1_line_num";
							}

							$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

								':ac1_ven' => round($VlrTotalOpc, $DECI_MALES),
								':ac1_line_num' 	=> $detail['ac1_line_num']


							));

							if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
							} else {
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $resUpdateVenDebit,
									'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura o anticipo' . $detail['pr1_docentry']
								);

								$this->response($respuesta);

								return;
							}
						}

						// SE ACTUALIZA EL VALOR DEL ANTICIPO PARA IR DESCONTANDO LO USADO
						// O EN SU DEFECTO TAMBIEN LA NOTA CREDITO
						if ($detail['pr1_doctype'] == 20 || $detail['pr1_doctype'] == 6) {


							$slqUpdateVenDebit = "UPDATE mac1
												SET ac1_ven_debit = ac1_ven_debit + :ac1_ven_debit
												WHERE ac1_line_num = :ac1_line_num";

							$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

								':ac1_ven_debit' => round($VlrTotalOpc, $DECI_MALES),
								':ac1_line_num' => $detail['ac1_line_num']

							));

							if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
							} else {
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $resUpdateFactPay,
									'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura ' . $detail['pr1_docentry']
								);

								$this->response($respuesta);

								return;
							}
						}
						// SE VALIDA SALDOS PARA CERRAR FACTURA
						if ($detail['pr1_doctype'] == 5 || $detail['pr1_doctype'] == 34 ) {

							$resEstado = $this->generic->validateBalanceAndClose($detail['pr1_docentry'], $detail['pr1_doctype'], 'dvfv', 'dvf');

							if (isset($resEstado['error']) && $resEstado['error'] === true) {
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


								if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
								} else {

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

						// VALIDAR PARA CERRAR SOLO EL RECIBO DE DISTRIBUCION
						if ($detail['pr1_doctype'] == 47 ) {

							if ( $EsAnticipoDoc == 0 ){
								$resEstado = $this->generic->validateBalanceAndClose($detail['pr1_docentry'], $detail['pr1_doctype'], 'dvrc', 'vrc');

								if (isset($resEstado['error']) && $resEstado['error'] === true) {
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
	
	
									if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
									} else {
	
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
						}

						// SE VALIDA SALDOS PARA CERRAR NOTA CREDITO
						if ($detail['pr1_doctype'] == 6) {

							$resEstado = $this->generic->validateBalanceAndClose($detail['pr1_docentry'], $detail['pr1_doctype'], 'dvnc', 'vnc');

							if (isset($resEstado['error']) && $resEstado['error'] === true) {
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

								if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
								} else {

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

						//ASIENTO MANUALES
						if ($detail['pr1_doctype'] == 18) {

							if ($detail['ac1_cord'] == 1) {

								$slqUpdateVenDebit = "UPDATE mac1
													SET ac1_ven_debit = ac1_ven_debit + :ac1_ven_debit
													WHERE ac1_line_num = :ac1_line_num";

								$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

									':ac1_ven_debit' => round($VlrTotalOpc, $DECI_MALES),
									':ac1_line_num'  => $detail['ac1_line_num']
								));

								if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
								} else {
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data' => $resUpdateVenDebit,
										'mensaje'	=> 'No se pudo actualizar el valor del pago' . $detail['pr1_docentry']
									);

									$this->response($respuesta);

									return;
								}
							} else if ($detail['ac1_cord'] == 0) {

								$slqUpdateVenDebit = "UPDATE mac1
											SET ac1_ven_credit = ac1_ven_credit + :ac1_ven_credit
											WHERE ac1_line_num = :ac1_line_num";

								$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

									':ac1_ven_credit' => round($VlrTotalOpc, $DECI_MALES),
									':ac1_line_num'   => $detail['ac1_line_num']
								));

								if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
								} else {
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data' 		=> $resUpdateVenDebit,
										'mensaje'	=> 'No se pudo actualizar el valor del pago' . $detail['pr1_docentry']
									);

									$this->response($respuesta);

									return;
								}
							}
						}
						//ASIENTOS MANUALES
						//
					}
				} else {

					// si falla algun insert del detalle de la cotizacion se devuelven los cambios realizados por la transaccion,
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

				$DetalleAsientoCuentaTercero->bpr_cardcode  = isset($Data['bpr_cardcode']) ? $Data['bpr_cardcode'] : NULL;
				$DetalleAsientoCuentaTercero->pr1_doctype   = is_numeric($detail['pr1_doctype']) ? $detail['pr1_doctype'] : 0;
				$DetalleAsientoCuentaTercero->pr1_docentry  = is_numeric($detail['pr1_docentry']) ? $detail['pr1_docentry'] : 0;
				$DetalleAsientoCuentaTercero->cuentatercero = is_numeric($detail['pr1_cuenta']) ? $detail['pr1_cuenta'] : 0;
				$DetalleAsientoCuentaTercero->cuentatercero = ($DetalleAsientoCuentaTercero->cuentatercero == 0) ? $detail['pr1_accountid'] : $DetalleAsientoCuentaTercero->cuentatercero;
				$DetalleAsientoCuentaTercero->cuentaNaturaleza = substr($DetalleAsientoCuentaTercero->cuentatercero, 0, 1);
				$DetalleAsientoCuentaTercero->pr1_vlrpaid = is_numeric($detail['pr1_vlrpaid']) ? $detail['pr1_vlrpaid'] : 0;
				$DetalleAsientoCuentaTercero->pr1_docdate	= $this->validateDate($detail['pr1_docdate']) ? $detail['pr1_docdate'] : NULL;
				$DetalleAsientoCuentaTercero->cord	= isset($detail['ac1_cord']) ? $detail['ac1_cord'] : NULL;
				$DetalleAsientoCuentaTercero->vlrpaiddesc	 = $VlrTotalOpc;
				$DetalleAsientoCuentaTercero->tasaoriginaldoc = $TasaOrg;
				$DetalleAsientoCuentaTercero->anticipodoc = $EsAnticipoDoc;

				$llaveAsientoCuentaTercero = $DetalleAsientoCuentaTercero->bpr_cardcode . $DetalleAsientoCuentaTercero->pr1_doctype . $DetalleAsientoCuentaTercero->tasaoriginaldoc .$DetalleAsientoCuentaTercero->anticipodoc;


				//********************
				if (in_array($llaveAsientoCuentaTercero, $inArrayAsientoCuentaTercero)) {

					$posicion = $this->buscarPosicion($llaveAsientoCuentaTercero, $inArrayAsientoCuentaTercero);
				} else {

					array_push($inArrayAsientoCuentaTercero, $llaveAsientoCuentaTercero);
					$posicionAsientoCuentaTercero = $this->buscarPosicion($llaveAsientoCuentaTercero, $inArrayAsientoCuentaTercero);
				}


				if (isset($DetalleConsolidadoAsientoCuentaTercero[$posicionAsientoCuentaTercero])) {

					if (!is_array($DetalleConsolidadoAsientoCuentaTercero[$posicionAsientoCuentaTercero])) {
						$DetalleConsolidadoAsientoCuentaTercero[$posicionAsientoCuentaTercero] = array();
					}
				} else {
					$DetalleConsolidadoAsientoCuentaTercero[$posicionAsientoCuentaTercero] = array();
				}

				array_push($DetalleConsolidadoAsientoCuentaTercero[$posicionAsientoCuentaTercero], $DetalleAsientoCuentaTercero);

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
			$codigoCuentaIngreso = substr($cuenta, 0, 1);
			$granTotalIngreso = $Data['bpr_vlrpaid'];
			$granTotalIngresoOriginal = $granTotalIngreso;

			if (trim($Data['bpr_currency']) != $MONEDALOCAL) {
				$granTotalIngreso = ($granTotalIngreso * $TasaDocLoc);
			}


			switch ($codigoCuentaIngreso) {
				case 1:
					$debito = $granTotalIngreso;
					if (trim($Data['bpr_currency']) != $MONEDASYS) {

						$MontoSysDB = ($debito / $TasaLocSys);
					} else {

						$MontoSysDB = $granTotalIngresoOriginal;
					}
					break;

				case 2:
					$debito = $granTotalIngreso;
					if (trim($Data['bpr_currency']) != $MONEDASYS) {

						$MontoSysDB = ($debito / $TasaLocSys);
					} else {

						$MontoSysDB = $granTotalIngresoOriginal;
					}
					break;

				case 3:
					$debito = $granTotalIngreso;
					if (trim($Data['bpr_currency']) != $MONEDASYS) {

						$MontoSysDB = ($debito / $TasaLocSys);
					} else {

						$MontoSysDB = $granTotalIngresoOriginal;
					}
					break;

				case 4:
					$debito = $granTotalIngreso;
					if (trim($Data['bpr_currency']) != $MONEDASYS) {

						$MontoSysDB = ($debito / $TasaLocSys);
					} else {

						$MontoSysDB = $granTotalIngresoOriginal;
					}
					break;

				case 5:
					$debito = $granTotalIngreso;
					if (trim($Data['bpr_currency']) != $MONEDASYS) {

						$MontoSysDB = ($debito / $TasaLocSys);
					} else {

						$MontoSysDB = $granTotalIngresoOriginal;
					}
					break;

				case 6:
					$debito = $granTotalIngreso;
					if (trim($Data['bpr_currency']) != $MONEDASYS) {

						$MontoSysDB = ($debito / $TasaLocSys);
					} else {

						$MontoSysDB = $granTotalIngresoOriginal;
					}
					break;

				case 7:
					$debito = $granTotalIngreso;
					if (trim($Data['bpr_currency']) != $MONEDASYS) {

						$MontoSysDB = ($debito / $TasaLocSys);
					} else {

						$MontoSysDB = $granTotalIngresoOriginal;
					}
					break;
			}

			$VlrPagoEfectuado = $debito;

			$DFPC  = $DFPC + round($credito, $DECI_MALES);
			$DFPD  = $DFPD + round($debito, $DECI_MALES);
			$DFPCS = $DFPCS + round($MontoSysCR, $DECI_MALES);
			$DFPDS = $DFPDS + round($MontoSysDB, $DECI_MALES);

			// SE AGREGA AL BALANCE
			if ( $debito > 0 ) {
				$BALANCE = $this->account->addBalance($periodo['data'], round($debito, $DECI_MALES), $cuenta, 1, $Data['bpr_docdate'], $Data['business'], $Data['branch']);
			} else {
				$BALANCE = $this->account->addBalance($periodo['data'], round($credito, $DECI_MALES), $cuenta, 2, $Data['bpr_docdate'], $Data['business'], $Data['branch']);
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
				':ac1_account' => $cuenta,
				':ac1_debit' => round($debito, $DECI_MALES),
				':ac1_credit' => round($credito, $DECI_MALES),
				':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
				':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
				':ac1_currex' => 0,
				':ac1_doc_date' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
				':ac1_doc_duedate' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
				':ac1_debit_import' => 0,
				':ac1_credit_import' => 0,
				':ac1_debit_importsys' => 0,
				':ac1_credit_importsys' => 0,
				':ac1_font_key' => $resInsert,
				':ac1_font_line' => 1,
				':ac1_font_type' => 20,
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
				':ac1_made_user' => isset($Data['bpr_createby']) ? $Data['bpr_createby'] : NULL,
				':ac1_accperiod' => $periodo['data'],
				':ac1_close' => 0,
				':ac1_cord' => 0,
				':ac1_ven_debit' => 0,
				':ac1_ven_credit' => 0,
				':ac1_fiscal_acct' => 0,
				':ac1_taxid' => 1,
				':ac1_isrti' => 0,
				':ac1_basert' => 0,
				':ac1_mmcode' => 0,
				':ac1_legal_num' => isset($Data['bpr_cardcode']) ? $Data['bpr_cardcode'] : NULL,
				':ac1_codref' => 1,
				':business' => $Data['business'],
				':branch' => $Data['branch']
			));



			if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
				// Se verifica que el detalle no de error insertando //
			} else {
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

			// echo "stop";exit;


			if ($Data['bpr_billpayment'] == '0' || $Data['bpr_billpayment'] == 0) {

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
					$ac1cord = null;
					$tasadoc = 0;
					$anticipodoc = 0;

					foreach ($posicion as $key => $value) {

						$TotalPagoRecibido = ($TotalPagoRecibido + $value->vlrpaiddesc);


						$docentry = $value->pr1_docentry;
						$doctype  = $value->pr1_doctype;
						$cuenta   = $value->cuentaNaturaleza;
						$fechaDocumento = $value->pr1_docdate;
						$cuentaLinea = $value->cuentatercero;
						$ac1cord = $value->cord;
						$tasadoc = $value->tasaoriginaldoc;
						$anticipodoc = $value->anticipodoc;
					}


					$debito = 0;
					$credito = 0;
					$MontoSysDB = 0;
					$MontoSysCR = 0;
					$TotalPagoRecibidoOriginal = $TotalPagoRecibido;




					if ($doctype == 20 || $doctype == 6 || $doctype == 47 && $anticipodoc == 1) {
						switch ($cuenta) {
							case 1:
								$debito = $TotalPagoRecibido;

								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysDB = ($debito / $TasaLocSys);
								} else {
									$MontoSysDB = ($debito / $TasaLocSys);
								}

								break;

							case 2:
								$debito = $TotalPagoRecibido;
								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysDB = ($debito / $TasaLocSys);
								} else {
									$MontoSysDB = ($debito / $TasaLocSys);
								}
								break;

							case 3:
								$debito = $TotalPagoRecibido;
								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysDB = ($debito / $TasaLocSys);
								} else {

									$MontoSysDB = ($debito / $TasaLocSys);
								}
								break;

							case 4:
								$debito = $TotalPagoRecibido;
								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysDB = ($debito / $TasaLocSys);
								} else {

									$MontoSysDB = ($debito / $TasaLocSys);
								}
								break;

							case 5:
								$debito = $TotalPagoRecibido;
								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysDB = ($debito / $TasaLocSys);
								} else {

									$MontoSysDB = ($debito / $TasaLocSys);
								}
								break;

							case 6:
								$debito = $TotalPagoRecibido;
								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysDB = ($debito / $TasaLocSys);
								} else {

									$MontoSysDB = ($debito / $TasaLocSys);
								}
								break;

							case 7:
								$debito = $TotalPagoRecibido;
								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysDB = ($debito / $TasaLocSys);
								} else {

									$MontoSysDB = ($debito / $TasaLocSys);
								}
								break;
						}
					} else if ($doctype == 18) {

						if ($ac1cord == 0) {
							$credito = $TotalPagoRecibido;

							if (trim($Data['bpr_currency']) != $MONEDASYS) {

								$MontoSysCR = ($credito / $TasaLocSys);
							} else {
								$MontoSysCR = ($credito / $TasaLocSys);
							}
						} else if ($ac1cord == 1) {
							$debito = $TotalPagoRecibido;

							if (trim($Data['bpr_currency']) != $MONEDASYS) {

								$MontoSysDB = ($debito / $TasaLocSys);
							} else {
								$MontoSysDB = ($debito / $TasaLocSys);
							}
						}
					} else {

						switch ($cuenta) {
							case 1:
								$credito = $TotalPagoRecibido;

								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysCR = ($credito / $TasaLocSys);
								} else {
									$MontoSysCR = ($credito / $TasaLocSys);
								}

								break;

							case 2:
								$credito = $TotalPagoRecibido;
								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysCR = ($credito / $TasaLocSys);
								} else {

									$MontoSysCR = ($credito / $TasaLocSys);
								}
								break;

							case 3:
								$credito = $TotalPagoRecibido;
								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysCR = ($credito / $TasaLocSys);
								} else {

									$MontoSysCR = ($credito / $TasaLocSys);
								}
								break;

							case 4:
								$credito = $TotalPagoRecibido;
								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysCR = ($credito / $TasaLocSys);
								} else {

									$MontoSysCR = ($credito / $TasaLocSys);
								}
								break;

							case 5:
								$credito = $TotalPagoRecibido;
								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysCR = ($credito / $TasaLocSys);
								} else {

									$MontoSysCR = ($credito / $TasaLocSys);
								}
								break;

							case 6:
								$credito = $TotalPagoRecibido;
								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysCR = ($credito / $TasaLocSys);
								} else {

									$MontoSysCR = ($credito / $TasaLocSys);
								}
								break;

							case 7:
								$credito = $TotalPagoRecibido;
								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysCR = ($credito / $TasaLocSys);
								} else {

									$MontoSysCR = ($credito / $TasaLocSys);
								}
								break;
						}
					}

					$DFPC  = $DFPC + round($credito, $DECI_MALES);
					$DFPD  = $DFPD + round($debito, $DECI_MALES);
					$DFPCS = $DFPCS + round($MontoSysCR, $DECI_MALES);
					$DFPDS = $DFPDS + round($MontoSysDB, $DECI_MALES);

					// SE AGREGA AL BALANCE
					if ( $debito > 0 ) {
						$BALANCE = $this->account->addBalance($periodo['data'], round($debito, $DECI_MALES), $cuentaLinea, 1, $Data['bpr_docdate'], $Data['business'], $Data['branch']);
					} else {
						$BALANCE = $this->account->addBalance($periodo['data'], round($credito, $DECI_MALES), $cuentaLinea, 2, $Data['bpr_docdate'], $Data['business'], $Data['branch']);
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
						':ac1_account' => $cuentaLinea,
						':ac1_debit' => round($debito, $DECI_MALES),
						':ac1_credit' => round($credito, $DECI_MALES),
						':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
						':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
						':ac1_currex' => 0,
						':ac1_doc_date' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
						':ac1_doc_duedate' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
						':ac1_debit_import' => 0,
						':ac1_credit_import' => 0,
						':ac1_debit_importsys' => 0,
						':ac1_credit_importsys' => 0,
						':ac1_font_key' => $resInsert,
						':ac1_font_line' => 1,
						':ac1_font_type' => 20,
						':ac1_accountvs' => 1,
						':ac1_doctype' => 18,
						':ac1_ref1' => "",
						':ac1_ref2' => "",
						':ac1_ref3' => "",
						':ac1_prc_code' => 0,
						':ac1_uncode' => 0,
						':ac1_prj_code' => isset($Data['bpr_project']) ? $Data['bpr_project'] : NULL,
						':ac1_rescon_date' => NULL,
						':ac1_recon_total' => 0,
						':ac1_made_user' => isset($Data['bpr_createby']) ? $Data['bpr_createby'] : NULL,
						':ac1_accperiod' => $periodo['data'],
						':ac1_close' => 0,
						':ac1_cord' => 0,
						':ac1_ven_debit' => round($credito, $DECI_MALES),
						':ac1_ven_credit' => round($credito, $DECI_MALES),
						':ac1_fiscal_acct' => 0,
						':ac1_taxid' => 1,
						':ac1_isrti' => 0,
						':ac1_basert' => 0,
						':ac1_mmcode' => 0,
						':ac1_legal_num' => isset($Data['bpr_cardcode']) ? $Data['bpr_cardcode'] : NULL,
						':ac1_codref' => 1,
						':business' => $Data['business'],
						':branch' => $Data['branch']
					));



					if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
						// Se verifica que el detalle no de error insertando //
					} else {
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
				//FIN Procedimiento PARA LLENAR ASIENTO CON CUENTA TERCERO SEGUN GRUPO DE CUENTAS


			} else { // EN CASO CONTRARIO ES UN ANTICIPO A CLIENTE
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

						$TotalPagoRecibido = ($TotalPagoRecibido + $value->pr1_vlrpaid);


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

					if (trim($Data['bpr_currency']) != $MONEDALOCAL) {
						$TotalPagoRecibido = ($TotalPagoRecibido * $TasaDocLoc);
					}

					switch ($cuenta) {
						case 1:
							$credito = $TotalPagoRecibido;

							if (trim($Data['bpr_currency']) != $MONEDASYS) {

								$MontoSysCR = ($credito / $TasaLocSys);
							} else {

								$MontoSysCR = $TotalPagoRecibidoOriginal;
							}

							break;

						case 2:
							$credito = $TotalPagoRecibido;
							if (trim($Data['bpr_currency']) != $MONEDASYS) {

								$MontoSysCR = ($credito / $TasaLocSys);
							} else {

								$MontoSysCR = $TotalPagoRecibidoOriginal;
							}
							break;

						case 3:
							$credito = $TotalPagoRecibido;
							if (trim($Data['bpr_currency']) != $MONEDASYS) {

								$MontoSysCR = ($credito / $TasaLocSys);
							} else {

								$MontoSysCR = $TotalPagoRecibidoOriginal;
							}
							break;

						case 4:
							$credito = $TotalPagoRecibido;
							if (trim($Data['bpr_currency']) != $MONEDASYS) {

								$MontoSysCR = ($credito / $TasaLocSys);
							} else {

								$MontoSysCR = $TotalPagoRecibidoOriginal;
							}
							break;

						case 5:
							$credito = $TotalPagoRecibido;
							if (trim($Data['bpr_currency']) != $MONEDASYS) {

								$MontoSysCR = ($credito / $TasaLocSys);
							} else {

								$MontoSysCR = $TotalPagoRecibidoOriginal;
							}
							break;

						case 6:
							$credito = $TotalPagoRecibido;
							if (trim($Data['bpr_currency']) != $MONEDASYS) {

								$MontoSysCR = ($credito / $TasaLocSys);
							} else {

								$MontoSysCR = $TotalPagoRecibidoOriginal;
							}
							break;

						case 7:
							$credito = $TotalPagoRecibido;
							if (trim($Data['bpr_currency']) != $MONEDASYS) {

								$MontoSysCR = ($credito / $TasaLocSys);
							} else {

								$MontoSysCR = $TotalPagoRecibidoOriginal;
							}
							break;
					}

					$DFPC  = $DFPC + round($credito, $DECI_MALES);
					$DFPD  = $DFPD + round($debito, $DECI_MALES);
					$DFPCS = $DFPCS + round($MontoSysCR, $DECI_MALES);
					$DFPDS = $DFPDS + round($MontoSysDB, $DECI_MALES);


					// SE AGREGA AL BALANCE
					if ( $debito > 0 ) {
						$BALANCE = $this->account->addBalance($periodo['data'], round($debito, $DECI_MALES), $cuentaLinea, 1, $Data['bpr_docdate'], $Data['business'], $Data['branch']);
					} else {
						$BALANCE = $this->account->addBalance($periodo['data'], round($credito, $DECI_MALES), $cuentaLinea, 2, $Data['bpr_docdate'], $Data['business'], $Data['branch']);
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
						':ac1_account' => $cuentaLinea,
						':ac1_debit' => round($debito, $DECI_MALES),
						':ac1_credit' => round($credito, $DECI_MALES),
						':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
						':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
						':ac1_currex' => 0,
						':ac1_doc_date' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
						':ac1_doc_duedate' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
						':ac1_debit_import' => 0,
						':ac1_credit_import' => 0,
						':ac1_debit_importsys' => 0,
						':ac1_credit_importsys' => 0,
						':ac1_font_key' => $resInsert,
						':ac1_font_line' => 1,
						':ac1_font_type' => 20,
						':ac1_accountvs' => 1,
						':ac1_doctype' => 18,
						':ac1_ref1' => "",
						':ac1_ref2' => "",
						':ac1_ref3' => "",
						':ac1_prc_code' => 0,
						':ac1_uncode' => 0,
						':ac1_prj_code' => isset($Data['bpr_project']) ? $Data['bpr_project'] : NULL,
						':ac1_rescon_date' => NULL,
						':ac1_recon_total' => 0,
						':ac1_made_user' => isset($Data['bpr_createby']) ? $Data['bpr_createby'] : NULL,
						':ac1_accperiod' => $periodo['data'],
						':ac1_close' => 0,
						':ac1_cord' => 0,
						':ac1_ven_debit' => 0,
						':ac1_ven_credit' => round($credito, $DECI_MALES),
						':ac1_fiscal_acct' => 0,
						':ac1_taxid' => 1,
						':ac1_isrti' => 0,
						':ac1_basert' => 0,
						':ac1_mmcode' => 0,
						':ac1_legal_num' => isset($Data['bpr_cardcode']) ? $Data['bpr_cardcode'] : NULL,
						':ac1_codref' => 1,
						':business' => $Data['business'],
						':branch' => $Data['branch']
					));



					if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
						// Se verifica que el detalle no de error insertando //

						$sqlUpdatePagoR = "UPDATE bpr1 set pr1_line_num = :pr1_line_num WHERE pr1_docnum = :pr1_docnum AND pr1_doctype = :pr1_doctype";
						$resUpdatePagoR = $this->pedeo->updateRow($sqlUpdatePagoR, array(
							':pr1_line_num' => $resDetalleAsiento,
							':pr1_docnum'   => $resInsert,
							':pr1_doctype'  => 20
						));

						if (is_numeric($resUpdatePagoR) && $resUpdatePagoR == 1){
						}else{
							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data'	  => $resUpdatePagoR,
								'mensaje' => 'No se pudo registrar el pago recibido, occurio un error al actualizar el pago'
							);

							return $this->response($respuesta);
						}

					} else {
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


			if (trim($Data['bpr_currency']) != $MONEDALOCAL) {
				if ($Data['bpr_billpayment'] == '0' || $Data['bpr_billpayment'] == 0) {
					//se verifica si existe diferencia en cambio
					$sqlCuentaDiferenciaCambio = "SELECT pge_acc_dcp, pge_acc_dcn FROM pgem WHERE pge_id = :business";
					$resCuentaDiferenciaCambio = $this->pedeo->queryTable($sqlCuentaDiferenciaCambio, array(':business' => $Data['business']));

					$CuentaDiferenciaCambio = [];

					if (isset($resCuentaDiferenciaCambio[0])) {

						$CuentaDiferenciaCambio = $resCuentaDiferenciaCambio[0];
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' => 'No se encontro la cuenta para aplicar la diferencia en cambio'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
					}


					if ($VlrDiff  <  0) {

						$VlrDiffN = abs($VlrDiff);

					} else if ($VlrDiff > 0) {

						$VlrDiffP = abs($VlrDiff);

					} else if ($VlrDiff  == 0) {

						$VlrDiffN = 0;
						$VlrDiffP = 0;
					}



					if ($VlrDiffP > 0) {
						$cuentaD = "";
						$credito = 0;
						$debito  = 0;
						$MontoSysDB = 0;
						$MontoSysCR = 0;



						$cuentaD    = $CuentaDiferenciaCambio['pge_acc_dcp'];
						$debito     = $VlrDiffP;

						if (trim($Data['bpr_currency']) != $MONEDALOCAL) {
							$debito = $VlrDiffP;
							$MontoSysDB = ($debito / $TasaLocSys);
						}else{
							$debito = ($VlrDiffP * $TasaDocLoc);
							$MontoSysDB = $VlrDiffP;
						}



						$DFPC  = $DFPC + 0;
						$DFPD  = $DFPD + round($debito, $DECI_MALES);
						$DFPCS = $DFPCS + 0;
						$DFPDS = $DFPDS + round($MontoSysDB, $DECI_MALES);

						// SE AGREGA AL BALANCE
					
						$BALANCE = $this->account->addBalance($periodo['data'], round($debito, $DECI_MALES), $cuentaD, 1, $Data['bpr_docdate'], $Data['business'], $Data['branch']);
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
							':ac1_account' => $cuentaD,
							':ac1_debit' => round($debito, $DECI_MALES),
							':ac1_credit' => 0,
							':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
							':ac1_credit_sys' => 0,
							':ac1_currex' => 0,
							':ac1_doc_date' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
							':ac1_doc_duedate' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
							':ac1_debit_import' => 0,
							':ac1_credit_import' => 0,
							':ac1_debit_importsys' => 0,
							':ac1_credit_importsys' => 0,
							':ac1_font_key' => $resInsert,
							':ac1_font_line' => 1,
							':ac1_font_type' => 20,
							':ac1_accountvs' => 1,
							':ac1_doctype' => 18,
							':ac1_ref1' => "",
							':ac1_ref2' => "",
							':ac1_ref3' => "",
							':ac1_prc_code' => 0,
							':ac1_uncode' => 0,
							':ac1_prj_code' => isset($Data['bpr_project']) ? $Data['bpr_project'] : NULL,
							':ac1_rescon_date' => NULL,
							':ac1_recon_total' => 0,
							':ac1_made_user' => isset($Data['bpr_createby']) ? $Data['bpr_createby'] : NULL,
							':ac1_accperiod' => $periodo['data'],
							':ac1_close' => 0,
							':ac1_cord' => 0,
							':ac1_ven_debit' => 0,
							':ac1_ven_credit' => 0,
							':ac1_fiscal_acct' => 0,
							':ac1_taxid' => 1,
							':ac1_isrti' => 0,
							':ac1_basert' => 0,
							':ac1_mmcode' => 0,
							':ac1_legal_num' => isset($Data['bpr_cardcode']) ? $Data['bpr_cardcode'] : NULL,
							':ac1_codref' => 1,
							':business' => $Data['business'],
							':branch' => $Data['branch']
						));



						if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
							
						} else {
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


					if ($VlrDiffN > 0) {

						$cuentaD = "";
						$credito = 0;
						$debito  = 0;
						$MontoSysDB = 0;
						$MontoSysCR = 0;



						$cuentaD    = $CuentaDiferenciaCambio['pge_acc_dcp'];
						$credito    = $VlrDiffN;

						if (trim($Data['bpr_currency']) != $MONEDALOCAL) {
							$credito = $VlrDiffN;
							$MontoSysCR = ($credito / $TasaLocSys);
						}else{
							$credito = ($VlrDiffN * $TasaDocLoc);
							$MontoSysCR = $VlrDiffN;
						}



						$DFPC  = $DFPC + round($credito, $DECI_MALES);
						$DFPD  = $DFPD + 0;
						$DFPCS = $DFPCS + round($MontoSysCR, $DECI_MALES);
						$DFPDS = $DFPDS + 0;

						// SE AGREGA AL BALANCE

						$BALANCE = $this->account->addBalance($periodo['data'], round($credito, $DECI_MALES), $cuentaD, 1, $Data['bpr_docdate'], $Data['business'], $Data['branch']);
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
							':ac1_account' => $cuentaD,
							':ac1_debit' => 0,
							':ac1_credit' => round($credito, $DECI_MALES),
							':ac1_debit_sys' => 0,
							':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
							':ac1_currex' => 0,
							':ac1_doc_date' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
							':ac1_doc_duedate' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
							':ac1_debit_import' => 0,
							':ac1_credit_import' => 0,
							':ac1_debit_importsys' => 0,
							':ac1_credit_importsys' => 0,
							':ac1_font_key' => $resInsert,
							':ac1_font_line' => 1,
							':ac1_font_type' => 20,
							':ac1_accountvs' => 1,
							':ac1_doctype' => 18,
							':ac1_ref1' => "",
							':ac1_ref2' => "",
							':ac1_ref3' => "",
							':ac1_prc_code' => 0,
							':ac1_uncode' => 0,
							':ac1_prj_code' => isset($Data['bpr_project']) ? $Data['bpr_project'] : NULL,
							':ac1_rescon_date' => NULL,
							':ac1_recon_total' => 0,
							':ac1_made_user' => isset($Data['bpr_createby']) ? $Data['bpr_createby'] : NULL,
							':ac1_accperiod' => $periodo['data'],
							':ac1_close' => 0,
							':ac1_cord' => 0,
							':ac1_ven_debit' => 0,
							':ac1_ven_credit' => 0,
							':ac1_fiscal_acct' => 0,
							':ac1_taxid' => 1,
							':ac1_isrti' => 0,
							':ac1_basert' => 0,
							':ac1_mmcode' => 0,
							':ac1_legal_num' => isset($Data['bpr_cardcode']) ? $Data['bpr_cardcode'] : NULL,
							':ac1_codref' => 1,
							':business' => $Data['business'],
							':branch' => $Data['branch']
						));



						if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
							
						} else {
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
			}

			//VERIFICAR SI EXISTE DIFERENCIA EN PESO
			//PARA AJUSTAR LOS DECIMALES MONEDA DEL SISTEMA

			$sqlDiffPeso = "SELECT sum(coalesce(ac1_debit_sys,0)) as debito, sum(coalesce(ac1_credit_sys,0)) as credito,
													sum(coalesce(ac1_debit,0)) as ldebito, sum(coalesce(ac1_credit,0)) as lcredito
													from mac1
													where ac1_trans_id = :ac1_trans_id";

			$resDiffPeso = $this->pedeo->queryTable($sqlDiffPeso, array(
				':ac1_trans_id' => $resInsertAsiento
			));

			if (isset($resDiffPeso[0]['debito']) && abs(($resDiffPeso[0]['debito'] - $resDiffPeso[0]['credito'])) > 0) {

				$sqlCuentaDiferenciaDecimal = "SELECT pge_acc_ajp FROM pgem WHERE pge_id = :business";
				$resCuentaDiferenciaDecimal = $this->pedeo->queryTable($sqlCuentaDiferenciaDecimal, array(':business' => $Data['business']));

				if (isset($resCuentaDiferenciaDecimal[0]) && is_numeric($resCuentaDiferenciaDecimal[0]['pge_acc_ajp'])) {
				} else {

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

				if ($debito > $credito) {
					$credito = abs(($debito - $credito));
					$debito = 0;
				} else {
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
					':ac1_doc_date' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => 20,
					':ac1_accountvs' => 1,
					':ac1_doctype' => 18,
					':ac1_ref1' => "",
					':ac1_ref2' => "",
					':ac1_ref3' => "",
					':ac1_prc_code' => 0,
					':ac1_uncode' => 0,
					':ac1_prj_code' => isset($Data['bpr_project']) ? $Data['bpr_project'] : NULL,
					':ac1_rescon_date' => NULL,
					':ac1_recon_total' => 0,
					':ac1_made_user' => isset($Data['bpr_createby']) ? $Data['bpr_createby'] : NULL,
					':ac1_accperiod' => $periodo['data'],
					':ac1_close' => 0,
					':ac1_cord' => 0,
					':ac1_ven_debit' => 0,
					':ac1_ven_credit' => 0,
					':ac1_fiscal_acct' => 0,
					':ac1_taxid' => 1,
					':ac1_isrti' => 0,
					':ac1_basert' => 0,
					':ac1_mmcode' => 0,
					':ac1_legal_num' => isset($Data['bpr_cardcode']) ? $Data['bpr_cardcode'] : NULL,
					':ac1_codref' => 1,
					':business' => $Data['business'],
					':branch' => $Data['branch']
				));



				if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {
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
			} else if (isset($resDiffPeso[0]['ldebito']) && abs(($resDiffPeso[0]['ldebito'] - $resDiffPeso[0]['lcredito'])) > 0) {

				$sqlCuentaDiferenciaDecimal = "SELECT pge_acc_ajp FROM pgem WHERE pge_id = :business";
				$resCuentaDiferenciaDecimal = $this->pedeo->queryTable($sqlCuentaDiferenciaDecimal, array(':business' => $Data['business']));

				if (isset($resCuentaDiferenciaDecimal[0]) && is_numeric($resCuentaDiferenciaDecimal[0]['pge_acc_ajp'])) {
				} else {

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

				if ($ldebito > $lcredito) {
					$lcredito = abs(($ldebito - $lcredito));
					$ldebito = 0;
				} else {
					$ldebito = abs(($lcredito - $ldebito));
					$lcredito = 0;
				}

				// SE AGREGA AL BALANCE
				if ( $ldebito > 0 ){
					$BALANCE = $this->account->addBalance($periodo['data'], round($ldebito, $DECI_MALES), $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'], 1, $Data['bpr_docdate'], $Data['business'], $Data['branch']);
				}else{
					$BALANCE = $this->account->addBalance($periodo['data'], round($lcredito, $DECI_MALES), $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'], 2, $Data['bpr_docdate'], $Data['business'], $Data['branch']);
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
					':ac1_doc_date' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => 20,
					':ac1_accountvs' => 1,
					':ac1_doctype' => 18,
					':ac1_ref1' => "",
					':ac1_ref2' => "",
					':ac1_ref3' => "",
					':ac1_prc_code' => 0,
					':ac1_uncode' => 0,
					':ac1_prj_code' => isset($Data['bpr_project']) ? $Data['bpr_project'] : NULL,
					':ac1_rescon_date' => NULL,
					':ac1_recon_total' => 0,
					':ac1_made_user' => isset($Data['bpr_createby']) ? $Data['bpr_createby'] : NULL,
					':ac1_accperiod' => $periodo['data'],
					':ac1_close' => 0,
					':ac1_cord' => 0,
					':ac1_ven_debit' => 0,
					':ac1_ven_credit' => 0,
					':ac1_fiscal_acct' => 0,
					':ac1_taxid' => 1,
					':ac1_isrti' => 0,
					':ac1_basert' => 0,
					':ac1_mmcode' => 0,
					':ac1_legal_num' => isset($Data['bpr_cardcode']) ? $Data['bpr_cardcode'] : NULL,
					':ac1_codref' => 1,
					':business' => $Data['business'],
					':branch' => $Data['branch']
				));



				if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {
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


			// Esto es para validar el resultado de la contabilidad
			// $sqlmac1 = "SELECT * FROM  mac1 WHERE ac1_trans_id = :ac1_trans_id";
			// $ressqlmac1 = $this->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $resInsertAsiento ));
			// print_r(json_encode($ressqlmac1));
			// exit;
		

		

			//SE VALIDA LA CONTABILIDAD CREADA
			$validateCont = $this->generic->validateAccountingAccent2($resInsertAsiento);


			if (isset($validateCont['error']) && $validateCont['error'] == false) {
			} else {

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

	



			// Si todo sale bien despues de insertar el detalle de la cotizacion
			// se confirma la trasaccion  para que los cambios apliquen permanentemente
			// en la base de datos y se confirma la operacion exitosa.
			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'Pago registrado con exito'
			);
		} else {
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
	public function getPaymentsReceived1_get()
	{

		if ( !isset($Data['business']) OR !isset($Data['branch'])){

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM gbpr business = :business AND branch = :branch";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':business' => $Data['business'], 'branch' => $Data['branch']));

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


	//OBTENER COTIZACION POR ID
	public function getPymentsById_get()
	{

		$Data = $this->get();

		if (!isset($Data['bpr_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM gbpr WHERE bpr_docentry =:bpr_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":bpr_docentry" => $Data['bpr_docentry']));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' 		=> array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}


	//OBTENER COTIZACION DETALLE POR ID
	public function getQuotationDetail_get()
	{

		$Data = $this->get();

		if (!isset($Data['pr1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM bpr1 WHERE pr1_docentry =:pr1_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":pr1_docentry" => $Data['pr1_docentry']));

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



	//OBTENER COTIZACIONES POR SOCIO DE NEGOCIO
	public function getPymentsBySN_get()
	{

		$Data = $this->get();

		if (!isset($Data['dms_card_code']) OR !isset($Data['business']) OR !isset($Data['branch'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM gbpr WHERE bpr_cardcode =:bpr_cardcode AND business = :business AND branch = :branch";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":bpr_cardcode" => $Data['dms_card_code'], ":business" => $Data['business'], ":branch" => $Data['branch']));

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


	public function getDetails_get()
	{
		$Data = $this->get();

		if (!isset($Data['pr1_docnum'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "-- FACTURA DE VENTAS
					SELECT bpr1.*, dmdt.mdt_docname, dvfv.dvf_docnum  as docnumoriginal
					FROM bpr1 
					INNER JOIN dmdt 
					ON dmdt.mdt_doctype = bpr1.pr1_doctype
					inner join dvfv 
					on bpr1.pr1_docentry = dvfv.dvf_docentry and bpr1.pr1_doctype = dvfv.dvf_doctype 
					WHERE pr1_docnum = :pr1_docnum
					-- PAGOS RECIBIDOS
					union all 
					SELECT bpr1.*, dmdt.mdt_docname, gbpr.bpr_docnum  as docnumoriginal
					FROM bpr1 
					INNER JOIN dmdt 
					ON dmdt.mdt_doctype = bpr1.pr1_doctype
					inner join gbpr 
					on bpr1.pr1_docnum = gbpr.bpr_docentry and bpr1.pr1_doctype = gbpr.bpr_doctype 
					WHERE pr1_docnum = :pr1_docnum
					-- NOTA CREDITO
					union all 
					SELECT bpr1.*, dmdt.mdt_docname, dvnc.vnc_docnum  as docnumoriginal
					FROM bpr1 
					INNER JOIN dmdt 
					ON dmdt.mdt_doctype = bpr1.pr1_doctype
					inner join dvnc 
					on bpr1.pr1_docentry = dvnc.vnc_docentry and bpr1.pr1_doctype = dvnc.vnc_doctype 
					WHERE pr1_docnum = :pr1_docnum
					-- ASIENTO MANUAL
					union all 
					SELECT bpr1.*, dmdt.mdt_docname, tmac.mac_doc_num  as docnumoriginal
					FROM bpr1 
					INNER JOIN dmdt 
					ON dmdt.mdt_doctype = bpr1.pr1_doctype
					inner join tmac 
					on bpr1.pr1_docentry = tmac.mac_trans_id  and bpr1.pr1_doctype = tmac.mac_doctype 
					WHERE pr1_docnum = :pr1_docnum";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":pr1_docnum" => $Data['pr1_docnum']));

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

	private function buscarPosicion($llave, $inArray)
	{
		$res = 0;
		for ($i = 0; $i < count($inArray); $i++) {
			if ($inArray[$i] == "$llave") {
				$res =  $i;
				break;
			}
		}

		return $res;
	}

	private function validateDate($fecha)
	{
		if (strlen($fecha) == 10 or strlen($fecha) > 10) {
			return true;
		} else {
			return false;
		}
	}

	public function cancelPay_post()
	{
		$Data = $this->post();
		$docentry = $Data['bpr_docentry'];
		$create_by = $Data['bpr_createby'];

		$sql = "SELECT
					distinct
					g.bpr_docnum ,g.bpr_docentry,g.bpr_doctype ,g.bpr_comments ,m.ac1_trans_id ,g.bpr_createby
				from gbpr g 
				inner join mac1 m on g.bpr_docentry = m.ac1_font_key  
				and g.bpr_doctype = m.ac1_font_type
				where g.bpr_docentry = :docentry";
		$resSql = $this->pedeo->queryTable($sql,array(':docentry' => $docentry));
		
		if(isset($resSql[0])){
			$data = array(
				'mac_trans_id' => $resSql[0]['ac1_trans_id'],
				'doctype' => $resSql[0]['bpe_doctype'],
				'mac_comments' => "Asiento de pago #".$resSql[0]['bpe_docnum']." Anualdo"
			);

			
        	$process = $this->cancelpay->cancelAccountingEntry($data);
			
			if($process['error'] == false){
				// print_r("hola");exit;
				$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
									VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

				$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


					':bed_docentry' => $docentry,
					':bed_doctype' => $resSql[0]['bpr_doctype'],
					':bed_status' => 2, //ESTADO CERRADO
					':bed_createby' => $create_by,
					':bed_date' => date('Y-m-d'),
					':bed_baseentry' => $docentry,
					':bed_basetype' => $resSql[0]['bpr_doctype']
				));


				if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
				} else {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertEstado,
						'mensaje'	=> 'No se pudo registrar la anulacion'
					);


					$this->response($respuesta);

					return;
				}
			}

			$this->response($process);
		}	
	}


	public function automaticPayment_post() {

		
		$estado = $this->pedeo->queryTable("SELECT * FROM cron WHERE ron_code = :ron_code", array(":ron_code" => 'AUTOMATICPAYMENT'));
		$valorEstado = null;

		if (isset($estado[0])) {

			$valorEstado = $estado[0];

			if ( $valorEstado['ron_status'] == 0 ) {

				$updateEstado = $this->pedeo->updateRow("UPDATE cron set ron_status = :ron_status,
														ron_hour = :ron_hour WHERE ron_id = :ron_id", array(
															":ron_status" => 1,
															":ron_hour"   => date("H:i:s"),
															":ron_id"     => $valorEstado['ron_id']
														));

				if ( is_numeric($updateEstado) && $updateEstado == 1 ) { 
				} else {
					$respuesta = array(
						'error'   => false,
						'data'    => $updateEstado,
						'mensaje' => 'Proceso finalizado'
					);
	
					return $this->response($respuesta);
				}

			} else {

				$f = new DateTime($valorEstado['ron_date'].' '.$valorEstado['ron_hour']);
				$fechaActual = new DateTime();
				// Calcula la diferencia
				$diferencia = $fechaActual->diff($f);
				// Obtiene la diferencia en minutos
				$minutos = $diferencia->days * 24 * 60 + $diferencia->h * 60 + $diferencia->i;

				if ( $minutos > 5 ) {

					$updateEstado = $this->pedeo->updateRow("UPDATE cron set ron_status = :ron_status,
					ron_hour = :ron_hour WHERE ron_id = :ron_id", array(
						":ron_status" => 1,
						":ron_hour"   => date("H:i:s"),
						":ron_id"     => $valorEstado['ron_id']
					));

					if ( is_numeric($updateEstado) && $updateEstado == 1 ) { 
					} else {
						$respuesta = array(
							'error'   => false,
							'data'    => $updateEstado,
							'mensaje' => 'Proceso finalizado'
						);

						return $this->response($respuesta);
					}

				} else {

					$respuesta = array(
						'error'   => false,
						'data'    => [],
						'mensaje' => 'Proceso en ejecucion'
					);
	
					return $this->response($respuesta);
				}
			}

		} else {

			$insertEstado = $this->pedeo->insertRow("INSERT INTO cron(ron_code,ron_status,ron_date,ron_hour)VALUES(:ron_code,
							:ron_status,:ron_date,:ron_hour)", array(
								':ron_code'   => 'AUTOMATICPAYMENT',
								':ron_status' => 1,
								':ron_date'   => date('Y-m-d'),
								':ron_hour'   => date('H:i:s')
							));

			if (is_numeric($insertEstado) && $insertEstado > 0) {
			}else{
				$respuesta = array(
					'error'   => false,
					'data'    => $insertEstado,
					'mensaje' => 'Proceso finalizado'
				);

				return $this->response($respuesta);
			}
			
		}

		$sqlSelect = "SELECT tbpp.*, get_localcur() as currency 
					FROM tbpp 
					LEFT JOIN gbpr ON tbpp.bpp_reference = bpr_reftransfer 
					WHERE bpp_status = :bpp_status AND bpp_status2 = :bpp_status2 AND gbpr.bpr_docentry is null";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(
			':bpp_status'  => 0,
			':bpp_status2' => 'APPROVED'
		));

		if ( isset($resSelect[0]) ) {

			foreach ($resSelect as $key => $pagos) {

				// SE BUSCA CUENTA DE BANCO Y NIVEL DE TESORERIA EN LA EMPRESA

				$resEmpresa = $this->pedeo->queryTable("SELECT pge_gateway_account,pge_treasury_level FROM pgem WHERE pge_id =:pge_id", array(
					":pge_id" => $pagos['business']
				));

				if ( isset($resEmpresa[0]) && $resEmpresa[0]['pge_gateway_account'] > 0 && $resEmpresa[0]['pge_treasury_level'] > 0 ) {


					// SE BUSCA LA NUMERACION

					$resNumberDoc = $this->pedeo->queryTable("SELECT pgs_id FROM pgdn WHERE pgs_id_doc_type = :pgs_id_doc_type AND business = :business AND pgs_enabled = :pgs_enabled AND pgs_doc_pre = :pgs_doc_pre", array(
						":pgs_id_doc_type" => 20,
						":business"        => $pagos['business'],
						":pgs_enabled"     => 1,
						":pgs_doc_pre"     => 1 
					));


					if (isset($resNumberDoc[0])) {

						// SE BUSCA EL DETALLE DEL PAGO
						$resContenido = $this->pedeo->queryTable("SELECT * FROM bpp1 WHERE pp1_docid = :pp1_docid",array(
							":pp1_docid" => $pagos['bpp_id']
						));


						$string = "";

						if ( isset($resContenido[0]) ) {


							$header = array(

								"bpr_cardcode" => $pagos['bpp_cardcode'],
								"bpr_cardname" => $pagos['bpp_cardname'],
								"bpr_address" => '', 
								"bpr_perscontact" => 0, 
								"bpr_series" => $resNumberDoc[0]['pgs_id'], 
								"bpr_docnum" => 0, 
								"bpr_docdate" => date("Y-m-d"), 
								"bpr_duedate" => date("Y-m-d"), 
								"bpr_taxdate" => date("Y-m-d"), 
								"bpr_ref" => $pagos['bpp_reference'], 
								"bpr_transid" => '',
								"bpr_comments" => '', 
								"bpr_memo" => 'Pago recibido - '.$pagos['bpp_cardcode'].' - '.$pagos['bpp_cardname'], 
								"bpr_acctransfer" => $resEmpresa[0]['pge_gateway_account'], 
								"bpr_datetransfer" => date("Y-m-d"), 
								"bpr_reftransfer" => $pagos['bpp_reference'], 
								"bpr_doctotal" => $pagos['bpp_doctotal'], 
								"bpr_vlrpaid" => $pagos['bpp_doctotal'], 
								"bpr_project" => '', 
								"bpr_createby" => 'manager', 
								"bpr_createat" => date("Y-m-d H:i:s"), 
								"bpr_payment" => 0,// falta este campo 
								"bpr_currency" => $pagos['currency'], 
								"bpr_billpayment" => 0, 
								"bpr_doctype" => 20,
								"bpr_accountsn" => '',
								"bpr_revalue" => 0, 
								"bpr_levelcash" => $resEmpresa[0]['pge_treasury_level'],
								"detail" => [],
								"business" => $pagos['business'],
								"branch" => $pagos['branch']

							);

							foreach ($resContenido as $key => $detalle) {

								array_push($header['detail'], array(

									"pr1_docnum"       => '',
									"pr1_docentry"     => $detalle['pp1_docentry'],
									"pr1_numref"       => '',
									"pr1_docdate"      => null,
									"pr1_vlrtotal"     => $detalle['pp1_vlrtotal'],
									"pr1_vlrpaid"      => $detalle['pp1_monto'],
									"pr1_payval"       => 0,
									"pr1_porcdiscount" => 0,
									"pr1_doctype"      => $detalle['pp1_doctype'],
									"pr1_docduedate"   => $detalle['pp1_docduedate'],
									"pr1_daysbackw"    => $detalle['pp1_daysbackw'],
									"pr1_vlrdiscount"  => 0,
									"pr1_ocrcode"      => 0,
									"pr1_accountid"    => 0,
									"pr1_cuenta"       => $detalle['pp1_cuenta'],
									"pr1_tercero"      => $pagos['bpp_cardcode'],
									"ac1_line_num"     => $detalle['pp1_line_num'],
									"ac1_cord"         => 0

								));
								

								$string = $string.' '.$detalle['pp1_docnum'];
							}


							$string = 'SE REALIZA PAGO AUTOMATICO PARA DOCUMENTOS: '.$string;

							$header['bpr_comments'] = $string;

							$resultado = self:: createPayment($header);

						}

					}
					
				}
			
			}

			$updateEstado = $this->pedeo->updateRow(
					"UPDATE cron set ron_status = :ron_status,
					ron_hour = :ron_hour WHERE ron_id = :ron_id", array(
						":ron_status" => 0,
						":ron_hour"   => date("H:i:s"),
						":ron_id"     => $valorEstado['ron_id']
			));

			if ( is_numeric($updateEstado) && $updateEstado == 1 ) { 
			} else {
				$respuesta = array(
					'error'   => false,
					'data'    => $updateEstado,
					'mensaje' => 'Proceso finalizado'
				);

				return $this->response($respuesta);
			}


			$respuesta = array(
				'error' => false,
				'data'  => array(),
				'mensaje' => 'Proceso finalizado'
			);

			return $this->response($respuesta);

		} else {

			$updateEstado = $this->pedeo->updateRow(
				"UPDATE cron set ron_status = :ron_status,
				ron_hour = :ron_hour WHERE ron_id = :ron_id", array(
					":ron_status" => 0,
					":ron_hour"   => date("H:i:s"),
					":ron_id"     => $valorEstado['ron_id']
			));

			if ( is_numeric($updateEstado) && $updateEstado == 1 ) { 
			} else {
				$respuesta = array(
					'error'   => false,
					'data'    => $updateEstado,
					'mensaje' => 'Proceso finalizado'
				);

				return $this->response($respuesta);
			}

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'Sin datos para procesar'
			);

			return $this->response($respuesta);
		}

	}

	//CREAR NUEVO PAGO LOCALMENTE
	public function createPayment($Data)
	{

		$DECI_MALES =  $this->generic->getDecimals();

		

		$DocNumVerificado = 0;
		$DetalleAsientoCuentaTercero = new stdClass();
		$DetalleConsolidadoAsientoCuentaTercero = [];
		$llaveAsientoCuentaTercero = "";
		$posicionAsientoCuentaTercero = 0;
		$cuentaTercero = 0;
		$inArrayAsientoCuentaTercero = array();
		$VlrTotalOpc = 0;
		$VlrDiff = 0;
		$VlrPagoEfectuado = 0;
		$TasaOrg = 0;
		$VlrDiffN = 0;
		$VlrDiffP = 0;
		$DFPC = 0; // diferencia en peso credito
		$DFPD = 0; // diferencia en peso debito
		$DFPCS = 0; // del sistema en credito
		$DFPDS = 0; // del sistema en debito
		// Se globaliza la variable sqlDetalleAsiento
		$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
							ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
							ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
							ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, business, branch)VALUES (:ac1_trans_id, :ac1_account,
							:ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
							:ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
							:ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
							:ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref, :business, :branch)";

		if (!isset($Data['detail']) or !isset($Data['bpr_billpayment']) OR !isset($Data['business']) OR !isset($Data['branch'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			return $respuesta;

			
		}

		$ContenidoDetalle = $Data['detail'];


		if (!is_array($ContenidoDetalle)) {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro el detalle del pago'
			);

			return $respuesta;

			
		}

		// SE VALIDA QUE EL DOCUMENTO TENGA CONTENIDO
		if (!intval(count($ContenidoDetalle)) > 0) {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'Documento sin detalle'
			);

			return $respuesta;
		}
		//
		//
		//VALIDANDO PERIODO CONTABLE
		$periodo = $this->generic->ValidatePeriod($Data['bpr_docdate'], $Data['bpr_docdate'], $Data['bpr_docdate'], 0);

		if (isset($periodo['error']) && $periodo['error'] == false) {
		} else {
			$respuesta = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => isset($periodo['mensaje']) ? $periodo['mensaje'] : 'no se pudo validar el periodo contable'
			);

			return $respuesta;
		}
		//PERIODO CONTABLE
		//
		// //BUSCANDO LA NUMERACION DEL DOCUMENTO
		$DocNumVerificado = $this->documentnumbering->NumberDoc($Data['bpr_series'],$Data['bpr_docdate'],$Data['bpr_duedate']);
		
		if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0){

		}else if ($DocNumVerificado['error']){

			return $DocNumVerificado;
		}

		//Obtener Carpeta Principal del Proyecto
		$sqlMainFolder = " SELECT * FROM params";
		$resMainFolder = $this->pedeo->queryTable($sqlMainFolder, array());

		if (!isset($resMainFolder[0])) {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro la caperta principal del proyecto'
			);

			return $respuesta;
		}


		// PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO
		// SE BUSCA LA MONEDA LOCAL PARAMETRIZADA
		$sqlMonedaLoc = "SELECT pgm_symbol FROM pgec WHERE pgm_principal = :pgm_principal";
		$resMonedaLoc = $this->pedeo->queryTable($sqlMonedaLoc, array(':pgm_principal' => 1));

		if (isset($resMonedaLoc[0])) {
		} else {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro la moneda local.'
			);

			return $respuesta;
		}

		$MONEDALOCAL = trim($resMonedaLoc[0]['pgm_symbol']);

		// SE BUSCA LA MONEDA DE SISTEMA PARAMETRIZADA
		$sqlMonedaSys = "SELECT pgm_symbol FROM pgec WHERE pgm_system = :pgm_system";
		$resMonedaSys = $this->pedeo->queryTable($sqlMonedaSys, array(':pgm_system' => 1));

		if (isset($resMonedaSys[0])) {
		} else {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro la moneda de sistema.'
			);

			return $respuesta;
		}


		$MONEDASYS = trim($resMonedaSys[0]['pgm_symbol']);

		//SE BUSCA LA TASA DE CAMBIO CON RESPECTO A LA MONEDA QUE TRAE EL DOCUMENTO A CREAR CON LA MONEDA LOCAL
		// Y EN LA MISMA FECHA QUE TRAE EL DOCUMENTO
		$sqlBusTasa = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
		$resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $Data['bpr_currency'], ':tsa_date' => $Data['bpr_docdate']));

		if (isset($resBusTasa[0])) {
		} else {

			if (trim($Data['bpr_currency']) != $MONEDALOCAL) {

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'No se encrontro la tasa de cambio para la moneda: ' . $Data['bpr_currency'] . ' en la actual fecha del documento: ' . $Data['bpr_docdate'] . ' y la moneda local: ' . $resMonedaLoc[0]['pgm_symbol']
				);

				return $respuesta;
			}
		}

		$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
		$resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $Data['bpr_docdate']));

		if (isset($resBusTasa2[0])) {
		} else {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :' . $Data['bpr_docdate']
			);

			return $respuesta;
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

		if (isset($resCuentaTercero[0])) {

			$cuentaTercero = $resCuentaTercero[0]['mgs_acct'];
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => $resCuentaTercero,
				'mensaje'	=> 'No se pudo registrar el pago, el tercero no tiene la cuenta asociada (' . $Data['bpr_cardcode'] . ')'
			);
			return $respuesta;
		}

		//FIN BUSQUEDA CUENTA TERCERO

		$sqlInsert = "INSERT INTO
                            	gbpr (bpr_cardcode,bpr_doctype,bpr_cardname,bpr_address,bpr_perscontact,bpr_series,bpr_docnum,bpr_docdate,bpr_taxdate,bpr_ref,bpr_transid,
                                    bpr_comments,bpr_memo,bpr_acctransfer,bpr_datetransfer,bpr_reftransfer,bpr_doctotal,bpr_vlrpaid,bpr_project,bpr_createby,
                                    bpr_createat,bpr_payment,bpr_currency, business, branch,bpr_levelcash)
                      VALUES (:bpr_cardcode,:bpr_doctype,:bpr_cardname,:bpr_address,:bpr_perscontact,:bpr_series,:bpr_docnum,:bpr_docdate,:bpr_taxdate,:bpr_ref,:bpr_transid,
                              :bpr_comments,:bpr_memo,:bpr_acctransfer,:bpr_datetransfer,:bpr_reftransfer,:bpr_doctotal,:bpr_vlrpaid,:bpr_project,:bpr_createby,
                              :bpr_createat,:bpr_payment,:bpr_currency, :business, :branch,:bpr_levelcash)";


		// Se Inicia la transaccion,
		// Todas las consultas de modificacion siguientes
		// aplicaran solo despues que se confirme la transaccion,
		// de lo contrario no se aplicaran los cambios y se devolvera
		// la base de datos a su estado original.

		$this->pedeo->trans_begin();

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			':bpr_cardcode' => isset($Data['bpr_cardcode']) ? $Data['bpr_cardcode'] : NULL,
			':bpr_doctype' => is_numeric($Data['bpr_doctype']) ? $Data['bpr_doctype'] : 0,
			':bpr_cardname' => isset($Data['bpr_cardname']) ? $Data['bpr_cardname'] : NULL,
			':bpr_address' => isset($Data['bpr_address']) ? $Data['bpr_address'] : NULL,
			':bpr_perscontact' => is_numeric($Data['bpr_perscontact']) ? $Data['bpr_perscontact'] : 0,
			':bpr_series' => is_numeric($Data['bpr_series']) ? $Data['bpr_series'] : 0,
			':bpr_docnum' => $DocNumVerificado,
			':bpr_docdate' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
			':bpr_taxdate' => $this->validateDate($Data['bpr_taxdate']) ? $Data['bpr_taxdate'] : NULL,
			':bpr_ref' => isset($Data['bpr_ref']) ? $Data['bpr_ref'] : NULL,
			':bpr_transid' => is_numeric($Data['bpr_transid']) ? $Data['bpr_transid'] : 0,
			':bpr_comments' => isset($Data['bpr_comments']) ? $Data['bpr_comments'] : NULL,
			':bpr_memo' => isset($Data['bpr_memo']) ? $Data['bpr_memo'] : NULL,
			':bpr_acctransfer' => isset($Data['bpr_acctransfer']) ? $Data['bpr_acctransfer'] : NULL,
			':bpr_datetransfer' => $this->validateDate($Data['bpr_datetransfer']) ? $Data['bpr_datetransfer'] : NULL,
			':bpr_reftransfer' => isset($Data['bpr_reftransfer']) ? $Data['bpr_reftransfer'] : NULL,
			':bpr_doctotal' => is_numeric($Data['bpr_doctotal']) ? $Data['bpr_doctotal'] : 0,
			':bpr_vlrpaid' => is_numeric($Data['bpr_vlrpaid']) ? $Data['bpr_vlrpaid'] : 0,
			':bpr_project' => isset($Data['bpr_project']) ? $Data['bpr_project'] : NULL,
			':bpr_createby' => isset($Data['bpr_createby']) ? $Data['bpr_createby'] : NULL,
			':bpr_createat' => $this->validateDate($Data['bpr_createat']) ? $Data['bpr_createat'] : NULL,
			':bpr_payment' => isset($Data['bpr_payment']) ? $Data['bpr_payment'] : NULL,
			':bpr_currency' => isset($Data['bpr_currency']) ? $Data['bpr_currency'] : NULL,
			':business' => $Data['business'],
			':branch' => $Data['branch'],
			':bpr_levelcash' => $Data['bpr_levelcash']
		));

		if (is_numeric($resInsert) && $resInsert > 0) {

			// Se actualiza la serie de la numeracion del documento

			$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
										WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id'      => $Data['bpr_series']
			));


			if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
			} else {
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'    => $resActualizarNumeracion,
					'mensaje'	=> 'No se pudo crear el pago'
				);

				return $respuesta;
			}
			// Fin de la actualizacion de la numeracion del documento

			// se agregar el estado del documento cerrado por defecto
			$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
							VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

			$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


				':bed_docentry' => $resInsert,
				':bed_doctype' => $Data['bpr_doctype'],
				':bed_status' => 3, //ESTADO CERRADO
				':bed_createby' => $Data['bpr_createby'],
				':bed_date' => date('Y-m-d'),
				':bed_baseentry' => NULL,
				':bed_basetype' => NULL
			));


			if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
			} else {

				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data' => $resInsertEstado,
					'mensaje'	=> 'No se pudo registrar el pago'
				);

				return $respuesta;
			}



			//Se agregan los asientos contables*/*******

			$sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user, business, branch)
								 VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user, :business, :branch)";


			$resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

				':mac_doc_num' => 1,
				':mac_status' => 1,
				':mac_base_type' => is_numeric($Data['bpr_doctype']) ? $Data['bpr_doctype'] : 0,
				':mac_base_entry' => $resInsert,
				':mac_doc_date' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
				':mac_doc_duedate' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
				':mac_legal_date' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
				':mac_ref1' => is_numeric($Data['bpr_doctype']) ? $Data['bpr_doctype'] : 0,
				':mac_ref2' => "",
				':mac_ref3' => "",
				':mac_loc_total' => is_numeric($Data['bpr_doctotal']) ? $Data['bpr_doctotal'] : 0,
				':mac_fc_total' => is_numeric($Data['bpr_doctotal']) ? $Data['bpr_doctotal'] : 0,
				':mac_sys_total' => is_numeric($Data['bpr_doctotal']) ? $Data['bpr_doctotal'] : 0,
				':mac_trans_dode' => 1,
				':mac_beline_nume' => 1,
				':mac_vat_date' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
				':mac_serie' => 1,
				':mac_number' => 1,
				':mac_bammntsys' => 0,
				':mac_bammnt' => 0,
				':mac_wtsum' => 1,
				':mac_vatsum' => 0,
				':mac_comments' => isset($Data['bpr_comments']) ? $Data['bpr_comments'] : NULL,
				':mac_create_date' => $this->validateDate($Data['bpr_createat']) ? $Data['bpr_createat'] : NULL,
				':mac_made_usuer' => isset($Data['bpr_createby']) ? $Data['bpr_createby'] : NULL,
				':mac_update_date' => date("Y-m-d"),
				':mac_update_user' => isset($Data['bpr_createby']) ? $Data['bpr_createby'] : NULL,
				':business' => $Data['business'],
				':branch' => $Data['branch']
			));


			if (is_numeric($resInsertAsiento) && $resInsertAsiento > 0) {
				// Se verifica que el detalle no de error insertando //
			} else {

				// si falla algun insert del detalle de la Entrega de Ventas se devuelven los cambios realizados por la transaccion,
				// se retorna el error y se detiene la ejecucion del codigo restante.
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'	  => $resInsertAsiento,
					'mensaje'	=> 'No se pudo registrar el pago'
				);

				return $respuesta;
			}


			// INICIA INSERCION DEL DETALLE

			foreach ($ContenidoDetalle as $key => $detail) {
				$VrlPagoDetalleNormal  = 0;
				$Equiv = 0;
				$EsAnticipoDoc = 0;
				// SOLO SI NO ES UN ANTICIPO CLIENTE
				if ($Data['bpr_billpayment'] == '0' || $Data['bpr_billpayment'] == 0) {
					//VALIDAR EL VALOR QUE SE ESTA PAGANDO NO SEA MAYOR AL SALDO DEL DOCUMENTO
					if ($detail['pr1_doctype'] == 5 || $detail['pr1_doctype'] == 6 || $detail['pr1_doctype'] == 7 || $detail['pr1_doctype'] == 20 ||  $detail['pr1_doctype'] == 18 || $detail['pr1_doctype'] == 34 || $detail['pr1_doctype'] == 47) {

						$pf = "";
						$tb  = "";

						if ($detail['pr1_doctype'] == 5 || $detail['pr1_doctype'] == 34){
							$pf = "dvf";
							$tb  = "dvfv";
						} else if ($detail['pr1_doctype'] == 6) {
							$pf = "vnc";
							$tb  = "dvnc";
						} else if ($detail['pr1_doctype'] == 7) {
						} else if ($detail['pr1_doctype'] == 20) {
							$pf = "bpr";
							$tb  = "gbpr";
						}else if ($detail['pr1_doctype'] == 47){

							$sqlPsnk = "SELECT 
										CASE 
											WHEN ac1_credit > 0 THEN 1
											ELSE 0
										END AS op	
										FROM mac1 
										WHERE ac1_line_num = :ac1_line_num";
							$resPsnk = $this->pedeo->queryTable($sqlPsnk, array(
								':ac1_line_num' => $detail['ac1_line_num']
							));
							
							if (isset($resPsnk[0])){

								if ( $resPsnk[0]['op'] == 1 ){
									$pf = "";
									$tb  = "";
									$EsAnticipoDoc = 1;
								}else{
									$pf = "vrc";
									$tb  = "dvrc";
									$EsAnticipoDoc = 0;
								}

							}else{
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $resPsnk,
									'mensaje'	=> 'No se pudo validar el tipo de operación para el tipo de documento 47'
								);
				
								return $respuesta;
							}

						}

		
						$resVlrPay = $this->generic->validateBalance($detail['pr1_docentry'], $detail['pr1_doctype'], $tb, $pf, $detail['pr1_vlrpaid'], $Data['bpr_currency'], $Data['bpr_docdate'], 1, isset($detail['ac1_line_num']) ? $detail['ac1_line_num'] : 0);

						if (isset($resVlrPay['error'])) {

							if ($resVlrPay['error'] === false) {

								$VlrTotalOpc = $resVlrPay['vlrop'];
								$VlrDiff     = ($VlrDiff + $resVlrPay['vlrdiff']);
								$TasaOrg     = $resVlrPay['tasadoc'];
								$Equiv       = $resVlrPay['equiv'];

								$VlrTotalOpc = ($VlrTotalOpc + $Equiv);
							} else {
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => [],
									'mensaje'	=> $resVlrPay['mensaje']
								);

								return $respuesta;
							}
						} else {

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data'    => [],
								'mensaje'	=> 'No se pudo validar el saldo actual del documento ' . $detail['pe1_docentry']
							);

							return $respuesta;
						}
					}
				}



				$sqlInsertDetail = "INSERT INTO
                                        	bpr1 (pr1_docnum,pr1_docentry,pr1_numref,pr1_docdate,pr1_vlrtotal,pr1_vlrpaid,pr1_comments,pr1_porcdiscount,pr1_doctype,
                                            pr1_docduedate,pr1_daysbackw,pr1_vlrdiscount,pr1_ocrcode, pr1_accountid, pr1_line_num)
                                    VALUES (:pr1_docnum,:pr1_docentry,:pr1_numref,:pr1_docdate,:pr1_vlrtotal,:pr1_vlrpaid,:pr1_comments,:pr1_porcdiscount,
                                            :pr1_doctype,:pr1_docduedate,:pr1_daysbackw,:pr1_vlrdiscount,:pr1_ocrcode, :pr1_accountid ,:pr1_line_num)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':pr1_docnum' => $resInsert,
					':pr1_docentry' => is_numeric($detail['pr1_docentry']) ? $detail['pr1_docentry'] : 0,
					':pr1_numref' => isset($detail['pr1_numref']) ? $detail['pr1_numref'] : NULL,
					':pr1_docdate' =>  $this->validateDate($detail['pr1_docdate']) ? $detail['pr1_docdate'] : NULL,
					':pr1_vlrtotal' => is_numeric($detail['pr1_vlrtotal']) ? $detail['pr1_vlrtotal'] : 0,
					':pr1_vlrpaid' => is_numeric($detail['pr1_vlrpaid']) ? $detail['pr1_vlrpaid'] : 0,
					':pr1_comments' => isset($detail['pr1_comments']) ? $detail['pr1_comments'] : NULL,
					':pr1_porcdiscount' => is_numeric($detail['pr1_porcdiscount']) ? $detail['pr1_porcdiscount'] : 0,
					':pr1_doctype' => is_numeric($detail['pr1_doctype']) ? $detail['pr1_doctype'] : 0,
					':pr1_docduedate' => $this->validateDate($detail['pr1_docdate']) ? $detail['pr1_docdate'] : NULL,
					':pr1_daysbackw' => is_numeric($detail['pr1_daysbackw']) ? $detail['pr1_daysbackw'] : 0,
					':pr1_vlrdiscount' => is_numeric($detail['pr1_vlrdiscount']) ? $detail['pr1_vlrdiscount'] : 0,
					':pr1_ocrcode' => isset($detail['pr1_ocrcode']) ? $detail['pr1_ocrcode'] : NULL,
					':pr1_accountid' => is_numeric($detail['pr1_accountid']) ? $detail['pr1_accountid'] : 0,
					':pr1_line_num' => isset($detail['ac1_line_num']) && is_numeric($detail['ac1_line_num']) ? $detail['ac1_line_num'] : 0

				));

				// Se verifica que el detalle no de error insertando //
				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// SI NO ES UN ANTICIPO
					if ($Data['bpr_billpayment'] == '0' || $Data['bpr_billpayment'] == 0) {


						//MOVIMIENTO DE DOCUMENTOS
						if ($detail['pr1_doctype'] == 5 || $detail['pr1_doctype'] == 34 || $detail['pr1_doctype'] == 6 || $detail['pr1_doctype'] == 7) {
							//SE APLICA PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS
							if (isset($detail['pr1_docentry']) && is_numeric($detail['pr1_docentry']) && isset($detail['pr1_doctype']) && is_numeric($detail['pr1_doctype'])) {

								$sqlDocInicio = "SELECT bmd_tdi, bmd_ndi FROM tbmd WHERE  bmd_doctype = :bmd_doctype AND bmd_docentry = :bmd_docentry";
								$resDocInicio = $this->pedeo->queryTable($sqlDocInicio, array(
									':bmd_doctype' => $detail['pr1_doctype'],
									':bmd_docentry' => $detail['pr1_docentry']
								));


								if (isset($resDocInicio[0])) {

									$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
																										bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency)
																										VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
																										:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency)";

									$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

										':bmd_doctype' => is_numeric($Data['bpr_doctype']) ? $Data['bpr_doctype'] : 0,
										':bmd_docentry' => $resInsert,
										':bmd_createat' => $this->validateDate($Data['bpr_createat']) ? $Data['bpr_createat'] : NULL,
										':bmd_doctypeo' => is_numeric($detail['pr1_doctype']) ? $detail['pr1_doctype'] : 0, //ORIGEN
										':bmd_docentryo' => is_numeric($detail['pr1_docentry']) ? $detail['pr1_docentry'] : 0,  //ORIGEN
										':bmd_tdi' => $resDocInicio[0]['bmd_tdi'], // DOCUMENTO INICIAL
										':bmd_ndi' => $resDocInicio[0]['bmd_ndi'], // DOCUMENTO INICIAL
										':bmd_docnum' => $DocNumVerificado,
										':bmd_doctotal' => $VlrTotalOpc,
										':bmd_cardcode' => isset($detail['pr1_tercero']) ? $detail['pr1_tercero'] : NULL,
										':bmd_cardtype' => 1,
										':bmd_currency' => isset($Data['bpr_currency'])?$Data['bpr_currency']:NULL,
									));

									if (is_numeric($resInsertMD) && $resInsertMD > 0) {
									} else {

										$this->pedeo->trans_rollback();

										$respuesta = array(
											'error'   => true,
											'data' => $resInsertMD,
											'mensaje'	=> 'No se pudo registrar el movimiento del documento'
										);


										return $respuesta;
									}
								}
								
							}
						}

						//FIN PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS

						//
						if ( $detail['pr1_doctype'] == 5 || $detail['pr1_doctype'] == 34 ) { // SOLO CUANDO ES UNA FACTURA

							$sqlUpdateFactPay = "UPDATE  dvfv  SET dvf_paytoday = COALESCE(dvf_paytoday,0)+:dvf_paytoday WHERE dvf_docentry = :dvf_docentry and dvf_doctype = :dvf_doctype";

							$resUpdateFactPay = $this->pedeo->updateRow($sqlUpdateFactPay, array(

								':dvf_paytoday' => round($VlrTotalOpc, $DECI_MALES),
								':dvf_docentry' => $detail['pr1_docentry'],
								':dvf_doctype'  => $detail['pr1_doctype']


							));

							if (is_numeric($resUpdateFactPay) && $resUpdateFactPay == 1) {
							} else {
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $resUpdateFactPay,
									'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura ' . $detail['pr1_docentry']
								);

								return $respuesta;
							}
						}

						if ( $detail['pr1_doctype'] == 47 ) { // RECIBO  O ANTICIPO DE DISTRIBUCION

							if ($EsAnticipoDoc == 0){

								$sqlUpdateFactPay = "UPDATE  dvrc  SET vrc_paytoday = COALESCE(vrc_paytoday,0)+:vrc_paytoday WHERE vrc_docentry = :vrc_docentry and vrc_doctype = :vrc_doctype";

								$resUpdateFactPay = $this->pedeo->updateRow($sqlUpdateFactPay, array(
	
									':vrc_paytoday' => round($VlrTotalOpc, $DECI_MALES),
									':vrc_docentry' => $detail['pr1_docentry'],
									':vrc_doctype'  => $detail['pr1_doctype']
	
	
								));
	
								if (is_numeric($resUpdateFactPay) && $resUpdateFactPay == 1) {
								} else {
									$this->pedeo->trans_rollback();
	
									$respuesta = array(
										'error'   => true,
										'data' => $resUpdateFactPay,
										'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura ' . $detail['pr1_docentry']
									);
	
									return $respuesta;
								}
							}
						}

						// SE ACTUALIZA EL VALOR DEL CAMPO PAY TODAY EN NOTA CREDITO
						if ($detail['pr1_doctype'] == 6) { // SOLO CUANDO ES UNA NOTA CREDITO

							$sqlUpdateFactPay = "UPDATE  dvnc  SET vnc_paytoday = COALESCE(vnc_paytoday,0)+:vnc_paytoday WHERE vnc_docentry = :vnc_docentry and vnc_doctype = :vnc_doctype";

							$resUpdateFactPay = $this->pedeo->updateRow($sqlUpdateFactPay, array(

								':vnc_paytoday' => round($VlrTotalOpc, $DECI_MALES),
								':vnc_docentry' => $detail['pr1_docentry'],
								':vnc_doctype'  => $detail['pr1_doctype']


							));

							if (is_numeric($resUpdateFactPay) && $resUpdateFactPay == 1) {
							} else {
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $resUpdateFactPay,
									'mensaje'	=> 'No se pudo actualizar el valor del pago en la nota credito ' . $detail['pr1_docentry']
								);

								return $respuesta;
							}
						}




						// ACTUALIZAR REFERENCIA DE PAGO EN ASIENTO CONTABLE DE LA FACTURA
						if ($detail['pr1_doctype'] == 5 || $detail['pr1_doctype'] == 34 ) { // SOLO CUANDO ES UNA FACTURA


							$slqUpdateVenDebit = "UPDATE mac1
												SET ac1_ven_credit = ac1_ven_credit + :ac1_ven_credit
												WHERE ac1_line_num = :ac1_line_num";
							$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

								':ac1_ven_credit' => round($VlrTotalOpc, $DECI_MALES),
								':ac1_line_num' 	=> $detail['ac1_line_num']


							));

							if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
							} else {
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $resUpdateVenDebit,
									'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura ' . $detail['pr1_docentry']
								);

								return $respuesta;
							}
						}

						//
						if ($detail['pr1_doctype'] == 47) { // RECIBO DE DISTRIBUCION O ANTICIPO 

							$slqUpdateVenDebit = "";

							if ($EsAnticipoDoc == 0){

								$slqUpdateVenDebit = "UPDATE mac1
								SET ac1_ven_credit = ac1_ven_credit + :ac1_ven
								WHERE ac1_line_num = :ac1_line_num";

							}else{
								$slqUpdateVenDebit = "UPDATE mac1
								SET ac1_ven_debit = ac1_ven_debit + :ac1_ven
								WHERE ac1_line_num = :ac1_line_num";
							}

							$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

								':ac1_ven' => round($VlrTotalOpc, $DECI_MALES),
								':ac1_line_num' 	=> $detail['ac1_line_num']


							));

							if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
							} else {
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $resUpdateVenDebit,
									'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura o anticipo' . $detail['pr1_docentry']
								);

								return $respuesta;
							}
						}

						// SE ACTUALIZA EL VALOR DEL ANTICIPO PARA IR DESCONTANDO LO USADO
						// O EN SU DEFECTO TAMBIEN LA NOTA CREDITO
						if ($detail['pr1_doctype'] == 20 || $detail['pr1_doctype'] == 6) {


							$slqUpdateVenDebit = "UPDATE mac1
												SET ac1_ven_debit = ac1_ven_debit + :ac1_ven_debit
												WHERE ac1_line_num = :ac1_line_num";

							$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

								':ac1_ven_debit' => round($VlrTotalOpc, $DECI_MALES),
								':ac1_line_num' => $detail['ac1_line_num']

							));

							if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
							} else {
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $resUpdateFactPay,
									'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura ' . $detail['pr1_docentry']
								);

								return $respuesta;
							}
						}
						// SE VALIDA SALDOS PARA CERRAR FACTURA
						if ($detail['pr1_doctype'] == 5 || $detail['pr1_doctype'] == 34 ) {

							$resEstado = $this->generic->validateBalanceAndClose($detail['pr1_docentry'], $detail['pr1_doctype'], 'dvfv', 'dvf');

							if (isset($resEstado['error']) && $resEstado['error'] === true) {
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


								if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
								} else {

									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data' => $resInsertEstado,
										'mensaje'	=> 'No se pudo registrar el pago'
									);


									return $respuesta;
								}
							}
						}

						// VALIDAR PARA CERRAR SOLO EL RECIBO DE DISTRIBUCION
						if ($detail['pr1_doctype'] == 47 ) {

							if ( $EsAnticipoDoc == 0 ){
								$resEstado = $this->generic->validateBalanceAndClose($detail['pr1_docentry'], $detail['pr1_doctype'], 'dvrc', 'vrc');

								if (isset($resEstado['error']) && $resEstado['error'] === true) {
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
	
	
									if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
									} else {
	
										$this->pedeo->trans_rollback();
	
										$respuesta = array(
											'error'   => true,
											'data' => $resInsertEstado,
											'mensaje'	=> 'No se pudo registrar el pago'
										);
	
	
										return $respuesta;
									}
								}
							}
						}

						// SE VALIDA SALDOS PARA CERRAR NOTA CREDITO
						if ($detail['pr1_doctype'] == 6) {

							$resEstado = $this->generic->validateBalanceAndClose($detail['pr1_docentry'], $detail['pr1_doctype'], 'dvnc', 'vnc');

							if (isset($resEstado['error']) && $resEstado['error'] === true) {
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

								if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
								} else {

									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data' => $resInsertEstado,
										'mensaje'	=> 'No se pudo registrar el pago'
									);


									return $respuesta;
								}
							}
						}

						//ASIENTO MANUALES
						if ($detail['pr1_doctype'] == 18) {

							if ($detail['ac1_cord'] == 1) {

								$slqUpdateVenDebit = "UPDATE mac1
													SET ac1_ven_debit = ac1_ven_debit + :ac1_ven_debit
													WHERE ac1_line_num = :ac1_line_num";

								$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

									':ac1_ven_debit' => round($VlrTotalOpc, $DECI_MALES),
									':ac1_line_num'  => $detail['ac1_line_num']
								));

								if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
								} else {
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data' => $resUpdateVenDebit,
										'mensaje'	=> 'No se pudo actualizar el valor del pago' . $detail['pr1_docentry']
									);

									return $respuesta;
								}
							} else if ($detail['ac1_cord'] == 0) {

								$slqUpdateVenDebit = "UPDATE mac1
											SET ac1_ven_credit = ac1_ven_credit + :ac1_ven_credit
											WHERE ac1_line_num = :ac1_line_num";

								$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

									':ac1_ven_credit' => round($VlrTotalOpc, $DECI_MALES),
									':ac1_line_num'   => $detail['ac1_line_num']
								));

								if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
								} else {
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data' 		=> $resUpdateVenDebit,
										'mensaje'	=> 'No se pudo actualizar el valor del pago' . $detail['pr1_docentry']
									);

									return $respuesta;
								}
							}
						}
						//ASIENTOS MANUALES
						//
					}
				} else {

					// si falla algun insert del detalle de la cotizacion se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertDetail,
						'mensaje'	=> 'No se pudo registrar el pago'
					);

					return $respuesta;
				}


				// LLENANDO DETALLE ASIENTOS CONTABLES (AGRUPACION)
				$DetalleAsientoCuentaTercero = new stdClass();

				$DetalleAsientoCuentaTercero->bpr_cardcode  = isset($Data['bpr_cardcode']) ? $Data['bpr_cardcode'] : NULL;
				$DetalleAsientoCuentaTercero->pr1_doctype   = is_numeric($detail['pr1_doctype']) ? $detail['pr1_doctype'] : 0;
				$DetalleAsientoCuentaTercero->pr1_docentry  = is_numeric($detail['pr1_docentry']) ? $detail['pr1_docentry'] : 0;
				$DetalleAsientoCuentaTercero->cuentatercero = is_numeric($detail['pr1_cuenta']) ? $detail['pr1_cuenta'] : 0;
				$DetalleAsientoCuentaTercero->cuentatercero = ($DetalleAsientoCuentaTercero->cuentatercero == 0) ? $detail['pr1_accountid'] : $DetalleAsientoCuentaTercero->cuentatercero;
				$DetalleAsientoCuentaTercero->cuentaNaturaleza = substr($DetalleAsientoCuentaTercero->cuentatercero, 0, 1);
				$DetalleAsientoCuentaTercero->pr1_vlrpaid = is_numeric($detail['pr1_vlrpaid']) ? $detail['pr1_vlrpaid'] : 0;
				$DetalleAsientoCuentaTercero->pr1_docdate	= $this->validateDate($detail['pr1_docdate']) ? $detail['pr1_docdate'] : NULL;
				$DetalleAsientoCuentaTercero->cord	= isset($detail['ac1_cord']) ? $detail['ac1_cord'] : NULL;
				$DetalleAsientoCuentaTercero->vlrpaiddesc	 = $VlrTotalOpc;
				$DetalleAsientoCuentaTercero->tasaoriginaldoc = $TasaOrg;
				$DetalleAsientoCuentaTercero->anticipodoc = $EsAnticipoDoc;

				$llaveAsientoCuentaTercero = $DetalleAsientoCuentaTercero->bpr_cardcode . $DetalleAsientoCuentaTercero->pr1_doctype . $DetalleAsientoCuentaTercero->tasaoriginaldoc .$DetalleAsientoCuentaTercero->anticipodoc;


				//********************
				if (in_array($llaveAsientoCuentaTercero, $inArrayAsientoCuentaTercero)) {

					$posicion = $this->buscarPosicion($llaveAsientoCuentaTercero, $inArrayAsientoCuentaTercero);
				} else {

					array_push($inArrayAsientoCuentaTercero, $llaveAsientoCuentaTercero);
					$posicionAsientoCuentaTercero = $this->buscarPosicion($llaveAsientoCuentaTercero, $inArrayAsientoCuentaTercero);
				}


				if (isset($DetalleConsolidadoAsientoCuentaTercero[$posicionAsientoCuentaTercero])) {

					if (!is_array($DetalleConsolidadoAsientoCuentaTercero[$posicionAsientoCuentaTercero])) {
						$DetalleConsolidadoAsientoCuentaTercero[$posicionAsientoCuentaTercero] = array();
					}
				} else {
					$DetalleConsolidadoAsientoCuentaTercero[$posicionAsientoCuentaTercero] = array();
				}

				array_push($DetalleConsolidadoAsientoCuentaTercero[$posicionAsientoCuentaTercero], $DetalleAsientoCuentaTercero);

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
			$codigoCuentaIngreso = substr($cuenta, 0, 1);
			$granTotalIngreso = $Data['bpr_vlrpaid'];
			$granTotalIngresoOriginal = $granTotalIngreso;

			if (trim($Data['bpr_currency']) != $MONEDALOCAL) {
				$granTotalIngreso = ($granTotalIngreso * $TasaDocLoc);
			}


			switch ($codigoCuentaIngreso) {
				case 1:
					$debito = $granTotalIngreso;
					if (trim($Data['bpr_currency']) != $MONEDASYS) {

						$MontoSysDB = ($debito / $TasaLocSys);
					} else {

						$MontoSysDB = $granTotalIngresoOriginal;
					}
					break;

				case 2:
					$debito = $granTotalIngreso;
					if (trim($Data['bpr_currency']) != $MONEDASYS) {

						$MontoSysDB = ($debito / $TasaLocSys);
					} else {

						$MontoSysDB = $granTotalIngresoOriginal;
					}
					break;

				case 3:
					$debito = $granTotalIngreso;
					if (trim($Data['bpr_currency']) != $MONEDASYS) {

						$MontoSysDB = ($debito / $TasaLocSys);
					} else {

						$MontoSysDB = $granTotalIngresoOriginal;
					}
					break;

				case 4:
					$debito = $granTotalIngreso;
					if (trim($Data['bpr_currency']) != $MONEDASYS) {

						$MontoSysDB = ($debito / $TasaLocSys);
					} else {

						$MontoSysDB = $granTotalIngresoOriginal;
					}
					break;

				case 5:
					$debito = $granTotalIngreso;
					if (trim($Data['bpr_currency']) != $MONEDASYS) {

						$MontoSysDB = ($debito / $TasaLocSys);
					} else {

						$MontoSysDB = $granTotalIngresoOriginal;
					}
					break;

				case 6:
					$debito = $granTotalIngreso;
					if (trim($Data['bpr_currency']) != $MONEDASYS) {

						$MontoSysDB = ($debito / $TasaLocSys);
					} else {

						$MontoSysDB = $granTotalIngresoOriginal;
					}
					break;

				case 7:
					$debito = $granTotalIngreso;
					if (trim($Data['bpr_currency']) != $MONEDASYS) {

						$MontoSysDB = ($debito / $TasaLocSys);
					} else {

						$MontoSysDB = $granTotalIngresoOriginal;
					}
					break;
			}

			$VlrPagoEfectuado = $debito;

			$DFPC  = $DFPC + round($credito, $DECI_MALES);
			$DFPD  = $DFPD + round($debito, $DECI_MALES);
			$DFPCS = $DFPCS + round($MontoSysCR, $DECI_MALES);
			$DFPDS = $DFPDS + round($MontoSysDB, $DECI_MALES);

			// SE AGREGA AL BALANCE
			if ( $debito > 0 ) {
				$BALANCE = $this->account->addBalance($periodo['data'], round($debito, $DECI_MALES), $cuenta, 1, $Data['bpr_docdate'], $Data['business'], $Data['branch']);
			} else {
				$BALANCE = $this->account->addBalance($periodo['data'], round($credito, $DECI_MALES), $cuenta, 2, $Data['bpr_docdate'], $Data['business'], $Data['branch']);
			}
			if (isset($BALANCE['error']) && $BALANCE['error'] == true){

				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error' => true,
					'data' => $BALANCE,
					'mensaje' => $BALANCE['mensaje']
				);

				return $respuesta;
			}	

			//

			$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

				':ac1_trans_id' => $resInsertAsiento,
				':ac1_account' => $cuenta,
				':ac1_debit' => round($debito, $DECI_MALES),
				':ac1_credit' => round($credito, $DECI_MALES),
				':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
				':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
				':ac1_currex' => 0,
				':ac1_doc_date' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
				':ac1_doc_duedate' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
				':ac1_debit_import' => 0,
				':ac1_credit_import' => 0,
				':ac1_debit_importsys' => 0,
				':ac1_credit_importsys' => 0,
				':ac1_font_key' => $resInsert,
				':ac1_font_line' => 1,
				':ac1_font_type' => 20,
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
				':ac1_made_user' => isset($Data['bpr_createby']) ? $Data['bpr_createby'] : NULL,
				':ac1_accperiod' => $periodo['data'],
				':ac1_close' => 0,
				':ac1_cord' => 0,
				':ac1_ven_debit' => 0,
				':ac1_ven_credit' => 0,
				':ac1_fiscal_acct' => 0,
				':ac1_taxid' => 1,
				':ac1_isrti' => 0,
				':ac1_basert' => 0,
				':ac1_mmcode' => 0,
				':ac1_legal_num' => isset($Data['bpr_cardcode']) ? $Data['bpr_cardcode'] : NULL,
				':ac1_codref' => 1,
				':business' => $Data['business'],
				':branch' => $Data['branch']
			));



			if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
				// Se verifica que el detalle no de error insertando //
			} else {
				// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
				// se retorna el error y se detiene la ejecucion del codigo restante.
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'	  => $resDetalleAsiento,
					'mensaje'	=> 'No se pudo registrar el pago recibido, ocurrio un error al ingresar el asiento del ingreso'
				);

				return $respuesta;
			}

			// FIN PROCESO ASIENTO INGRESO

			// echo "stop";exit;


			if ($Data['bpr_billpayment'] == '0' || $Data['bpr_billpayment'] == 0) {

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
					$ac1cord = null;
					$tasadoc = 0;
					$anticipodoc = 0;

					foreach ($posicion as $key => $value) {

						$TotalPagoRecibido = ($TotalPagoRecibido + $value->vlrpaiddesc);


						$docentry = $value->pr1_docentry;
						$doctype  = $value->pr1_doctype;
						$cuenta   = $value->cuentaNaturaleza;
						$fechaDocumento = $value->pr1_docdate;
						$cuentaLinea = $value->cuentatercero;
						$ac1cord = $value->cord;
						$tasadoc = $value->tasaoriginaldoc;
						$anticipodoc = $value->anticipodoc;
					}


					$debito = 0;
					$credito = 0;
					$MontoSysDB = 0;
					$MontoSysCR = 0;
					$TotalPagoRecibidoOriginal = $TotalPagoRecibido;




					if ($doctype == 20 || $doctype == 6 || $doctype == 47 && $anticipodoc == 1) {
						switch ($cuenta) {
							case 1:
								$debito = $TotalPagoRecibido;

								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysDB = ($debito / $TasaLocSys);
								} else {
									$MontoSysDB = ($debito / $TasaLocSys);
								}

								break;

							case 2:
								$debito = $TotalPagoRecibido;
								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysDB = ($debito / $TasaLocSys);
								} else {
									$MontoSysDB = ($debito / $TasaLocSys);
								}
								break;

							case 3:
								$debito = $TotalPagoRecibido;
								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysDB = ($debito / $TasaLocSys);
								} else {

									$MontoSysDB = ($debito / $TasaLocSys);
								}
								break;

							case 4:
								$debito = $TotalPagoRecibido;
								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysDB = ($debito / $TasaLocSys);
								} else {

									$MontoSysDB = ($debito / $TasaLocSys);
								}
								break;

							case 5:
								$debito = $TotalPagoRecibido;
								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysDB = ($debito / $TasaLocSys);
								} else {

									$MontoSysDB = ($debito / $TasaLocSys);
								}
								break;

							case 6:
								$debito = $TotalPagoRecibido;
								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysDB = ($debito / $TasaLocSys);
								} else {

									$MontoSysDB = ($debito / $TasaLocSys);
								}
								break;

							case 7:
								$debito = $TotalPagoRecibido;
								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysDB = ($debito / $TasaLocSys);
								} else {

									$MontoSysDB = ($debito / $TasaLocSys);
								}
								break;
						}
					} else if ($doctype == 18) {

						if ($ac1cord == 0) {
							$credito = $TotalPagoRecibido;

							if (trim($Data['bpr_currency']) != $MONEDASYS) {

								$MontoSysCR = ($credito / $TasaLocSys);
							} else {
								$MontoSysCR = ($credito / $TasaLocSys);
							}
						} else if ($ac1cord == 1) {
							$debito = $TotalPagoRecibido;

							if (trim($Data['bpr_currency']) != $MONEDASYS) {

								$MontoSysDB = ($debito / $TasaLocSys);
							} else {
								$MontoSysDB = ($debito / $TasaLocSys);
							}
						}
					} else {

						switch ($cuenta) {
							case 1:
								$credito = $TotalPagoRecibido;

								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysCR = ($credito / $TasaLocSys);
								} else {
									$MontoSysCR = ($credito / $TasaLocSys);
								}

								break;

							case 2:
								$credito = $TotalPagoRecibido;
								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysCR = ($credito / $TasaLocSys);
								} else {

									$MontoSysCR = ($credito / $TasaLocSys);
								}
								break;

							case 3:
								$credito = $TotalPagoRecibido;
								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysCR = ($credito / $TasaLocSys);
								} else {

									$MontoSysCR = ($credito / $TasaLocSys);
								}
								break;

							case 4:
								$credito = $TotalPagoRecibido;
								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysCR = ($credito / $TasaLocSys);
								} else {

									$MontoSysCR = ($credito / $TasaLocSys);
								}
								break;

							case 5:
								$credito = $TotalPagoRecibido;
								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysCR = ($credito / $TasaLocSys);
								} else {

									$MontoSysCR = ($credito / $TasaLocSys);
								}
								break;

							case 6:
								$credito = $TotalPagoRecibido;
								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysCR = ($credito / $TasaLocSys);
								} else {

									$MontoSysCR = ($credito / $TasaLocSys);
								}
								break;

							case 7:
								$credito = $TotalPagoRecibido;
								if (trim($Data['bpr_currency']) != $MONEDASYS) {

									$MontoSysCR = ($credito / $TasaLocSys);
								} else {

									$MontoSysCR = ($credito / $TasaLocSys);
								}
								break;
						}
					}

					$DFPC  = $DFPC + round($credito, $DECI_MALES);
					$DFPD  = $DFPD + round($debito, $DECI_MALES);
					$DFPCS = $DFPCS + round($MontoSysCR, $DECI_MALES);
					$DFPDS = $DFPDS + round($MontoSysDB, $DECI_MALES);

					// SE AGREGA AL BALANCE
					if ( $debito > 0 ) {
						$BALANCE = $this->account->addBalance($periodo['data'], round($debito, $DECI_MALES), $cuentaLinea, 1, $Data['bpr_docdate'], $Data['business'], $Data['branch']);
					} else {
						$BALANCE = $this->account->addBalance($periodo['data'], round($credito, $DECI_MALES), $cuentaLinea, 2, $Data['bpr_docdate'], $Data['business'], $Data['branch']);
					}
					if (isset($BALANCE['error']) && $BALANCE['error'] == true){

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error' => true,
							'data' => $BALANCE,
							'mensaje' => $BALANCE['mensaje']
						);

						return $respuesta;
					}	

					//

					$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

						':ac1_trans_id' => $resInsertAsiento,
						':ac1_account' => $cuentaLinea,
						':ac1_debit' => round($debito, $DECI_MALES),
						':ac1_credit' => round($credito, $DECI_MALES),
						':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
						':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
						':ac1_currex' => 0,
						':ac1_doc_date' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
						':ac1_doc_duedate' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
						':ac1_debit_import' => 0,
						':ac1_credit_import' => 0,
						':ac1_debit_importsys' => 0,
						':ac1_credit_importsys' => 0,
						':ac1_font_key' => $resInsert,
						':ac1_font_line' => 1,
						':ac1_font_type' => 20,
						':ac1_accountvs' => 1,
						':ac1_doctype' => 18,
						':ac1_ref1' => "",
						':ac1_ref2' => "",
						':ac1_ref3' => "",
						':ac1_prc_code' => 0,
						':ac1_uncode' => 0,
						':ac1_prj_code' => isset($Data['bpr_project']) ? $Data['bpr_project'] : NULL,
						':ac1_rescon_date' => NULL,
						':ac1_recon_total' => 0,
						':ac1_made_user' => isset($Data['bpr_createby']) ? $Data['bpr_createby'] : NULL,
						':ac1_accperiod' => $periodo['data'],
						':ac1_close' => 0,
						':ac1_cord' => 0,
						':ac1_ven_debit' => round($credito, $DECI_MALES),
						':ac1_ven_credit' => round($credito, $DECI_MALES),
						':ac1_fiscal_acct' => 0,
						':ac1_taxid' => 1,
						':ac1_isrti' => 0,
						':ac1_basert' => 0,
						':ac1_mmcode' => 0,
						':ac1_legal_num' => isset($Data['bpr_cardcode']) ? $Data['bpr_cardcode'] : NULL,
						':ac1_codref' => 1,
						':business' => $Data['business'],
						':branch' => $Data['branch']
					));



					if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
						// Se verifica que el detalle no de error insertando //
					} else {
						// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
						// se retorna el error y se detiene la ejecucion del codigo restante.
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'	  => $resDetalleAsiento,
							'mensaje'	=> 'No se pudo registrar el pago recibido, occurio un error al insertar el detalle del asiento cuenta tercero'
						);

						return $respuesta;
					}
				}
				//FIN Procedimiento PARA LLENAR ASIENTO CON CUENTA TERCERO SEGUN GRUPO DE CUENTAS


			} else { // EN CASO CONTRARIO ES UN ANTICIPO A CLIENTE
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

						$TotalPagoRecibido = ($TotalPagoRecibido + $value->pr1_vlrpaid);


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

					if (trim($Data['bpr_currency']) != $MONEDALOCAL) {
						$TotalPagoRecibido = ($TotalPagoRecibido * $TasaDocLoc);
					}

					switch ($cuenta) {
						case 1:
							$credito = $TotalPagoRecibido;

							if (trim($Data['bpr_currency']) != $MONEDASYS) {

								$MontoSysCR = ($credito / $TasaLocSys);
							} else {

								$MontoSysCR = $TotalPagoRecibidoOriginal;
							}

							break;

						case 2:
							$credito = $TotalPagoRecibido;
							if (trim($Data['bpr_currency']) != $MONEDASYS) {

								$MontoSysCR = ($credito / $TasaLocSys);
							} else {

								$MontoSysCR = $TotalPagoRecibidoOriginal;
							}
							break;

						case 3:
							$credito = $TotalPagoRecibido;
							if (trim($Data['bpr_currency']) != $MONEDASYS) {

								$MontoSysCR = ($credito / $TasaLocSys);
							} else {

								$MontoSysCR = $TotalPagoRecibidoOriginal;
							}
							break;

						case 4:
							$credito = $TotalPagoRecibido;
							if (trim($Data['bpr_currency']) != $MONEDASYS) {

								$MontoSysCR = ($credito / $TasaLocSys);
							} else {

								$MontoSysCR = $TotalPagoRecibidoOriginal;
							}
							break;

						case 5:
							$credito = $TotalPagoRecibido;
							if (trim($Data['bpr_currency']) != $MONEDASYS) {

								$MontoSysCR = ($credito / $TasaLocSys);
							} else {

								$MontoSysCR = $TotalPagoRecibidoOriginal;
							}
							break;

						case 6:
							$credito = $TotalPagoRecibido;
							if (trim($Data['bpr_currency']) != $MONEDASYS) {

								$MontoSysCR = ($credito / $TasaLocSys);
							} else {

								$MontoSysCR = $TotalPagoRecibidoOriginal;
							}
							break;

						case 7:
							$credito = $TotalPagoRecibido;
							if (trim($Data['bpr_currency']) != $MONEDASYS) {

								$MontoSysCR = ($credito / $TasaLocSys);
							} else {

								$MontoSysCR = $TotalPagoRecibidoOriginal;
							}
							break;
					}

					$DFPC  = $DFPC + round($credito, $DECI_MALES);
					$DFPD  = $DFPD + round($debito, $DECI_MALES);
					$DFPCS = $DFPCS + round($MontoSysCR, $DECI_MALES);
					$DFPDS = $DFPDS + round($MontoSysDB, $DECI_MALES);


					// SE AGREGA AL BALANCE
					if ( $debito > 0 ) {
						$BALANCE = $this->account->addBalance($periodo['data'], round($debito, $DECI_MALES), $cuentaLinea, 1, $Data['bpr_docdate'], $Data['business'], $Data['branch']);
					} else {
						$BALANCE = $this->account->addBalance($periodo['data'], round($credito, $DECI_MALES), $cuentaLinea, 2, $Data['bpr_docdate'], $Data['business'], $Data['branch']);
					}
					if (isset($BALANCE['error']) && $BALANCE['error'] == true){

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error' => true,
							'data' => $BALANCE,
							'mensaje' => $BALANCE['mensaje']
						);

						return $respuesta;
					}	

					//

					$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

						':ac1_trans_id' => $resInsertAsiento,
						':ac1_account' => $cuentaLinea,
						':ac1_debit' => round($debito, $DECI_MALES),
						':ac1_credit' => round($credito, $DECI_MALES),
						':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
						':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
						':ac1_currex' => 0,
						':ac1_doc_date' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
						':ac1_doc_duedate' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
						':ac1_debit_import' => 0,
						':ac1_credit_import' => 0,
						':ac1_debit_importsys' => 0,
						':ac1_credit_importsys' => 0,
						':ac1_font_key' => $resInsert,
						':ac1_font_line' => 1,
						':ac1_font_type' => 20,
						':ac1_accountvs' => 1,
						':ac1_doctype' => 18,
						':ac1_ref1' => "",
						':ac1_ref2' => "",
						':ac1_ref3' => "",
						':ac1_prc_code' => 0,
						':ac1_uncode' => 0,
						':ac1_prj_code' => isset($Data['bpr_project']) ? $Data['bpr_project'] : NULL,
						':ac1_rescon_date' => NULL,
						':ac1_recon_total' => 0,
						':ac1_made_user' => isset($Data['bpr_createby']) ? $Data['bpr_createby'] : NULL,
						':ac1_accperiod' => $periodo['data'],
						':ac1_close' => 0,
						':ac1_cord' => 0,
						':ac1_ven_debit' => 0,
						':ac1_ven_credit' => round($credito, $DECI_MALES),
						':ac1_fiscal_acct' => 0,
						':ac1_taxid' => 1,
						':ac1_isrti' => 0,
						':ac1_basert' => 0,
						':ac1_mmcode' => 0,
						':ac1_legal_num' => isset($Data['bpr_cardcode']) ? $Data['bpr_cardcode'] : NULL,
						':ac1_codref' => 1,
						':business' => $Data['business'],
						':branch' => $Data['branch']
					));



					if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
						// Se verifica que el detalle no de error insertando //

						$sqlUpdatePagoR = "UPDATE bpr1 set pr1_line_num = :pr1_line_num WHERE pr1_docnum = :pr1_docnum AND pr1_doctype = :pr1_doctype";
						$resUpdatePagoR = $this->pedeo->updateRow($sqlUpdatePagoR, array(
							':pr1_line_num' => $resDetalleAsiento,
							':pr1_docnum'   => $resInsert,
							':pr1_doctype'  => 20
						));

						if (is_numeric($resUpdatePagoR) && $resUpdatePagoR == 1){
						}else{
							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data'	  => $resUpdatePagoR,
								'mensaje' => 'No se pudo registrar el pago recibido, occurio un error al actualizar el pago'
							);

							return $respuesta;
						}

					} else {
						// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
						// se retorna el error y se detiene la ejecucion del codigo restante.
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'	  => $resDetalleAsiento,
							'mensaje'	=> 'No se pudo registrar el pago recibido, occurio un error al insertar el detalle del asiento cuenta tercero'
						);

						return $respuesta;
					}
				}
				//FIN DEL PROCESO PARA AGREGAR ASIENTO A CUENTA DE LA LINEA DE ANTICIPO
			}
			// FIN PROCESO ANTERIOR


			if (trim($Data['bpr_currency']) != $MONEDALOCAL) {
				if ($Data['bpr_billpayment'] == '0' || $Data['bpr_billpayment'] == 0) {
					//se verifica si existe diferencia en cambio
					$sqlCuentaDiferenciaCambio = "SELECT pge_acc_dcp, pge_acc_dcn FROM pgem WHERE pge_id = :business";
					$resCuentaDiferenciaCambio = $this->pedeo->queryTable($sqlCuentaDiferenciaCambio, array(':business' => $Data['business']));

					$CuentaDiferenciaCambio = [];

					if (isset($resCuentaDiferenciaCambio[0])) {

						$CuentaDiferenciaCambio = $resCuentaDiferenciaCambio[0];
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' => 'No se encontro la cuenta para aplicar la diferencia en cambio'
						);

						return $respuesta;
					}


					if ($VlrDiff  <  0) {

						$VlrDiffN = abs($VlrDiff);

					} else if ($VlrDiff > 0) {

						$VlrDiffP = abs($VlrDiff);

					} else if ($VlrDiff  == 0) {

						$VlrDiffN = 0;
						$VlrDiffP = 0;
					}



					if ($VlrDiffP > 0) {
						$cuentaD = "";
						$credito = 0;
						$debito  = 0;
						$MontoSysDB = 0;
						$MontoSysCR = 0;



						$cuentaD    = $CuentaDiferenciaCambio['pge_acc_dcp'];
						$debito     = $VlrDiffP;

						if (trim($Data['bpr_currency']) != $MONEDALOCAL) {
							$debito = $VlrDiffP;
							$MontoSysDB = ($debito / $TasaLocSys);
						}else{
							$debito = ($VlrDiffP * $TasaDocLoc);
							$MontoSysDB = $VlrDiffP;
						}



						$DFPC  = $DFPC + 0;
						$DFPD  = $DFPD + round($debito, $DECI_MALES);
						$DFPCS = $DFPCS + 0;
						$DFPDS = $DFPDS + round($MontoSysDB, $DECI_MALES);

						// SE AGREGA AL BALANCE
					
						$BALANCE = $this->account->addBalance($periodo['data'], round($debito, $DECI_MALES), $cuentaD, 1, $Data['bpr_docdate'], $Data['business'], $Data['branch']);
						if (isset($BALANCE['error']) && $BALANCE['error'] == true){

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error' => true,
								'data' => $BALANCE,
								'mensaje' => $BALANCE['mensaje']
							);
	
							return $respuesta;
						}	
						//

						$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

							':ac1_trans_id' => $resInsertAsiento,
							':ac1_account' => $cuentaD,
							':ac1_debit' => round($debito, $DECI_MALES),
							':ac1_credit' => 0,
							':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
							':ac1_credit_sys' => 0,
							':ac1_currex' => 0,
							':ac1_doc_date' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
							':ac1_doc_duedate' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
							':ac1_debit_import' => 0,
							':ac1_credit_import' => 0,
							':ac1_debit_importsys' => 0,
							':ac1_credit_importsys' => 0,
							':ac1_font_key' => $resInsert,
							':ac1_font_line' => 1,
							':ac1_font_type' => 20,
							':ac1_accountvs' => 1,
							':ac1_doctype' => 18,
							':ac1_ref1' => "",
							':ac1_ref2' => "",
							':ac1_ref3' => "",
							':ac1_prc_code' => 0,
							':ac1_uncode' => 0,
							':ac1_prj_code' => isset($Data['bpr_project']) ? $Data['bpr_project'] : NULL,
							':ac1_rescon_date' => NULL,
							':ac1_recon_total' => 0,
							':ac1_made_user' => isset($Data['bpr_createby']) ? $Data['bpr_createby'] : NULL,
							':ac1_accperiod' => $periodo['data'],
							':ac1_close' => 0,
							':ac1_cord' => 0,
							':ac1_ven_debit' => 0,
							':ac1_ven_credit' => 0,
							':ac1_fiscal_acct' => 0,
							':ac1_taxid' => 1,
							':ac1_isrti' => 0,
							':ac1_basert' => 0,
							':ac1_mmcode' => 0,
							':ac1_legal_num' => isset($Data['bpr_cardcode']) ? $Data['bpr_cardcode'] : NULL,
							':ac1_codref' => 1,
							':business' => $Data['business'],
							':branch' => $Data['branch']
						));



						if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
							
						} else {
							// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
							// se retorna el error y se detiene la ejecucion del codigo restante.
							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data'	  => $resDetalleAsiento,
								'mensaje'	=> 'No se pudo registrar el pago recibido, occurio un error al insertar el detalle del asiento diferencia en cambio'
							);

							return $respuesta;
						}
					}


					if ($VlrDiffN > 0) {

						$cuentaD = "";
						$credito = 0;
						$debito  = 0;
						$MontoSysDB = 0;
						$MontoSysCR = 0;



						$cuentaD    = $CuentaDiferenciaCambio['pge_acc_dcp'];
						$credito    = $VlrDiffN;

						if (trim($Data['bpr_currency']) != $MONEDALOCAL) {
							$credito = $VlrDiffN;
							$MontoSysCR = ($credito / $TasaLocSys);
						}else{
							$credito = ($VlrDiffN * $TasaDocLoc);
							$MontoSysCR = $VlrDiffN;
						}



						$DFPC  = $DFPC + round($credito, $DECI_MALES);
						$DFPD  = $DFPD + 0;
						$DFPCS = $DFPCS + round($MontoSysCR, $DECI_MALES);
						$DFPDS = $DFPDS + 0;

						// SE AGREGA AL BALANCE

						$BALANCE = $this->account->addBalance($periodo['data'], round($credito, $DECI_MALES), $cuentaD, 1, $Data['bpr_docdate'], $Data['business'], $Data['branch']);
						if (isset($BALANCE['error']) && $BALANCE['error'] == true){

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error' => true,
								'data' => $BALANCE,
								'mensaje' => $BALANCE['mensaje']
							);
	
							return $respuesta;
						}	
						//

						$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

							':ac1_trans_id' => $resInsertAsiento,
							':ac1_account' => $cuentaD,
							':ac1_debit' => 0,
							':ac1_credit' => round($credito, $DECI_MALES),
							':ac1_debit_sys' => 0,
							':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
							':ac1_currex' => 0,
							':ac1_doc_date' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
							':ac1_doc_duedate' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
							':ac1_debit_import' => 0,
							':ac1_credit_import' => 0,
							':ac1_debit_importsys' => 0,
							':ac1_credit_importsys' => 0,
							':ac1_font_key' => $resInsert,
							':ac1_font_line' => 1,
							':ac1_font_type' => 20,
							':ac1_accountvs' => 1,
							':ac1_doctype' => 18,
							':ac1_ref1' => "",
							':ac1_ref2' => "",
							':ac1_ref3' => "",
							':ac1_prc_code' => 0,
							':ac1_uncode' => 0,
							':ac1_prj_code' => isset($Data['bpr_project']) ? $Data['bpr_project'] : NULL,
							':ac1_rescon_date' => NULL,
							':ac1_recon_total' => 0,
							':ac1_made_user' => isset($Data['bpr_createby']) ? $Data['bpr_createby'] : NULL,
							':ac1_accperiod' => $periodo['data'],
							':ac1_close' => 0,
							':ac1_cord' => 0,
							':ac1_ven_debit' => 0,
							':ac1_ven_credit' => 0,
							':ac1_fiscal_acct' => 0,
							':ac1_taxid' => 1,
							':ac1_isrti' => 0,
							':ac1_basert' => 0,
							':ac1_mmcode' => 0,
							':ac1_legal_num' => isset($Data['bpr_cardcode']) ? $Data['bpr_cardcode'] : NULL,
							':ac1_codref' => 1,
							':business' => $Data['business'],
							':branch' => $Data['branch']
						));



						if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
							
						} else {
							// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
							// se retorna el error y se detiene la ejecucion del codigo restante.
							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data'	  => $resDetalleAsiento,
								'mensaje'	=> 'No se pudo registrar el pago recibido, occurio un error al insertar el detalle del asiento diferencia en cambio'
							);

							return $respuesta;
						}
					}
				}
			}

			//VERIFICAR SI EXISTE DIFERENCIA EN PESO
			//PARA AJUSTAR LOS DECIMALES MONEDA DEL SISTEMA

			$sqlDiffPeso = "SELECT sum(coalesce(ac1_debit_sys,0)) as debito, sum(coalesce(ac1_credit_sys,0)) as credito,
													sum(coalesce(ac1_debit,0)) as ldebito, sum(coalesce(ac1_credit,0)) as lcredito
													from mac1
													where ac1_trans_id = :ac1_trans_id";

			$resDiffPeso = $this->pedeo->queryTable($sqlDiffPeso, array(
				':ac1_trans_id' => $resInsertAsiento
			));

			if (isset($resDiffPeso[0]['debito']) && abs(($resDiffPeso[0]['debito'] - $resDiffPeso[0]['credito'])) > 0) {

				$sqlCuentaDiferenciaDecimal = "SELECT pge_acc_ajp FROM pgem WHERE pge_id = :business";
				$resCuentaDiferenciaDecimal = $this->pedeo->queryTable($sqlCuentaDiferenciaDecimal, array(':business' => $Data['business']));

				if (isset($resCuentaDiferenciaDecimal[0]) && is_numeric($resCuentaDiferenciaDecimal[0]['pge_acc_ajp'])) {
				} else {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $resCuentaDiferenciaDecimal,
						'mensaje'	=> 'No se encontro la cuenta para adicionar la diferencia en decimales'
					);

					return $respuesta;
				}

				$debito  = $resDiffPeso[0]['debito'];
				$credito = $resDiffPeso[0]['credito'];

				if ($debito > $credito) {
					$credito = abs(($debito - $credito));
					$debito = 0;
				} else {
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
					':ac1_doc_date' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => 20,
					':ac1_accountvs' => 1,
					':ac1_doctype' => 18,
					':ac1_ref1' => "",
					':ac1_ref2' => "",
					':ac1_ref3' => "",
					':ac1_prc_code' => 0,
					':ac1_uncode' => 0,
					':ac1_prj_code' => isset($Data['bpr_project']) ? $Data['bpr_project'] : NULL,
					':ac1_rescon_date' => NULL,
					':ac1_recon_total' => 0,
					':ac1_made_user' => isset($Data['bpr_createby']) ? $Data['bpr_createby'] : NULL,
					':ac1_accperiod' => $periodo['data'],
					':ac1_close' => 0,
					':ac1_cord' => 0,
					':ac1_ven_debit' => 0,
					':ac1_ven_credit' => 0,
					':ac1_fiscal_acct' => 0,
					':ac1_taxid' => 1,
					':ac1_isrti' => 0,
					':ac1_basert' => 0,
					':ac1_mmcode' => 0,
					':ac1_legal_num' => isset($Data['bpr_cardcode']) ? $Data['bpr_cardcode'] : NULL,
					':ac1_codref' => 1,
					':business' => $Data['business'],
					':branch' => $Data['branch']
				));



				if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {
					// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $resDetalleAsiento,
						'mensaje'	=> 'No se pudo registrar el pago recibido, occurio un error al insertar el detalle del asiento diferencia en cambio'
					);

					return $respuesta;
				}
			} else if (isset($resDiffPeso[0]['ldebito']) && abs(($resDiffPeso[0]['ldebito'] - $resDiffPeso[0]['lcredito'])) > 0) {

				$sqlCuentaDiferenciaDecimal = "SELECT pge_acc_ajp FROM pgem WHERE pge_id = :business";
				$resCuentaDiferenciaDecimal = $this->pedeo->queryTable($sqlCuentaDiferenciaDecimal, array(':business' => $Data['business']));

				if (isset($resCuentaDiferenciaDecimal[0]) && is_numeric($resCuentaDiferenciaDecimal[0]['pge_acc_ajp'])) {
				} else {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $resCuentaDiferenciaDecimal,
						'mensaje'	=> 'No se encontro la cuenta para adicionar la diferencia en decimales'
					);

					return $respuesta;
				}

				$ldebito  = $resDiffPeso[0]['ldebito'];
				$lcredito = $resDiffPeso[0]['lcredito'];

				if ($ldebito > $lcredito) {
					$lcredito = abs(($ldebito - $lcredito));
					$ldebito = 0;
				} else {
					$ldebito = abs(($lcredito - $ldebito));
					$lcredito = 0;
				}

				// SE AGREGA AL BALANCE
				if ( $ldebito > 0 ){
					$BALANCE = $this->account->addBalance($periodo['data'], round($ldebito, $DECI_MALES), $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'], 1, $Data['bpr_docdate'], $Data['business'], $Data['branch']);
				}else{
					$BALANCE = $this->account->addBalance($periodo['data'], round($lcredito, $DECI_MALES), $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'], 2, $Data['bpr_docdate'], $Data['business'], $Data['branch']);
				}
				if (isset($BALANCE['error']) && $BALANCE['error'] == true){

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error' => true,
						'data' => $BALANCE,
						'mensaje' => $BALANCE['mensaje']
					);

					return $respuesta;
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
					':ac1_doc_date' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['bpr_docdate']) ? $Data['bpr_docdate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => 20,
					':ac1_accountvs' => 1,
					':ac1_doctype' => 18,
					':ac1_ref1' => "",
					':ac1_ref2' => "",
					':ac1_ref3' => "",
					':ac1_prc_code' => 0,
					':ac1_uncode' => 0,
					':ac1_prj_code' => isset($Data['bpr_project']) ? $Data['bpr_project'] : NULL,
					':ac1_rescon_date' => NULL,
					':ac1_recon_total' => 0,
					':ac1_made_user' => isset($Data['bpr_createby']) ? $Data['bpr_createby'] : NULL,
					':ac1_accperiod' => $periodo['data'],
					':ac1_close' => 0,
					':ac1_cord' => 0,
					':ac1_ven_debit' => 0,
					':ac1_ven_credit' => 0,
					':ac1_fiscal_acct' => 0,
					':ac1_taxid' => 1,
					':ac1_isrti' => 0,
					':ac1_basert' => 0,
					':ac1_mmcode' => 0,
					':ac1_legal_num' => isset($Data['bpr_cardcode']) ? $Data['bpr_cardcode'] : NULL,
					':ac1_codref' => 1,
					':business' => $Data['business'],
					':branch' => $Data['branch']
				));



				if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {
					// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $resDetalleAsiento,
						'mensaje'	=> 'No se pudo registrar el pago recibido, occurio un error al insertar el detalle del asiento diferencia en cambio'
					);

					return $respuesta;
				}
			}


			// Esto es para validar el resultado de la contabilidad
			// $sqlmac1 = "SELECT * FROM  mac1 WHERE ac1_trans_id = :ac1_trans_id";
			// $ressqlmac1 = $this->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $resInsertAsiento ));
			// print_r(json_encode($ressqlmac1));
			// exit;
		

		

			//SE VALIDA LA CONTABILIDAD CREADA
			$validateCont = $this->generic->validateAccountingAccent2($resInsertAsiento);


			if (isset($validateCont['error']) && $validateCont['error'] == false) {
			} else {

				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data' 	 => '',
					'mensaje' => $validateCont['mensaje']
				);

				return $respuesta;
			}
			//

	



			// Si todo sale bien despues de insertar el detalle de la cotizacion
			// se confirma la trasaccion  para que los cambios apliquen permanentemente
			// en la base de datos y se confirma la operacion exitosa.
			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'Pago registrado con exito'
			);
		} else {
			// Se devuelven los cambios realizados en la transaccion
			// si occurre un error  y se muestra devuelve el error.
			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data' => $resInsert,
				'mensaje'	=> 'No se pudo registrar el pago'
			);
		}

		return $respuesta;
	}
}