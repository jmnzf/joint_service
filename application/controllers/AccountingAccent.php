<?php
// DATOS MAESTROS ASIENTOS CONTABLES
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');
require_once(APPPATH . '/libraries/Generic.php');

use Restserver\libraries\REST_Controller;

class AccountingAccent extends REST_Controller
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
		$this->load->library('account');

	}

	//CREAR NUEVO ASIENTO CONTABLE
	public function createAccountingAccent_post()
	{
		$Data = $this->post();

		if (!isset($Data['business']) or !isset($Data['branch'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}




		$DECI_MALES =  $this->generic->getDecimals();

		$DocNumVerificado = 0;

		$sqlInsertDetail = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate, ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype, ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord, ac1_ven_debit, ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, ac1_card_type, business, branch, ac1_codret, ac1_base_tax)
						VALUES (:ac1_trans_id, :ac1_account, :ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys, :ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode, :ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct, :ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref, :ac1_card_type, :business, :branch, :ac1_codret, :ac1_base_tax)";


		if (!isset($Data['detail'])) {

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
				'mensaje' => 'No se encontro el detalle de la cuenta'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		//
		//VALIDANDO PERIODO CONTABLE
		$periodo = $this->generic->ValidatePeriod($Data['mac_legal_date'], $Data['mac_doc_date'], $Data['mac_doc_duedate'], 0);

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
		


		// //BUSCANDO LA NUMERACION DEL DOCUMENTO
        $DocNumVerificado = $this->documentnumbering->NumberDoc($Data['mac_serie'],$Data['mac_doc_date'],$Data['mac_doc_duedate']);
		
	    if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0){
	
		}else if ($DocNumVerificado['error']){
	
		    return $this->response($DocNumVerificado, REST_Controller::HTTP_BAD_REQUEST);
		}



		// Se Inicia la transaccion,
		// Todas las consultas de modificacion siguientes
		// aplicaran solo despues que se confirme la transaccion,
		// de lo contrario no se aplicaran los cambios y se devolvera
		// la base de datos a su estado original.

		$this->pedeo->trans_begin();

		$sqlInsert = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user, mac_currency, mac_doctype, business, branch, mac_accperiod)
                      VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user, :mac_currency, :mac_doctype, :business, :branch, :mac_accperiod)";


		$resInsert = $this->pedeo->insertRow($sqlInsert, array(

			':mac_doc_num' => $DocNumVerificado,
			':mac_status' => is_numeric($Data['mac_status']) ? $Data['mac_status'] : 0,
			':mac_base_type' => 18,
			':mac_base_entry' => 0,
			':mac_doc_date' => $this->validateDate($Data['mac_doc_date']) ? $Data['mac_doc_date'] : NULL,
			':mac_doc_duedate' => $this->validateDate($Data['mac_doc_duedate']) ? $Data['mac_doc_duedate'] : NULL,
			':mac_legal_date' => $this->validateDate($Data['mac_legal_date']) ? $Data['mac_legal_date'] : NULL,
			':mac_ref1' => isset($Data['mac_ref1']) ? $Data['mac_ref1'] : NULL,
			':mac_ref2' => isset($Data['mac_ref2']) ? $Data['mac_ref2'] : NULL,
			':mac_ref3' => isset($Data['mac_ref3']) ? $Data['mac_ref3'] : NULL,
			':mac_loc_total' => is_numeric($Data['mac_loc_total']) ? $Data['mac_loc_total'] : 0,
			':mac_fc_total' => is_numeric($Data['mac_fc_total']) ? $Data['mac_fc_total'] : 0,
			':mac_sys_total' => is_numeric($Data['mac_sys_total']) ? $Data['mac_sys_total'] : 0,
			':mac_trans_dode' => is_numeric($Data['mac_trans_dode']) ? $Data['mac_trans_dode'] : 0,
			':mac_beline_nume' => is_numeric($Data['mac_beline_nume']) ? $Data['mac_beline_nume'] : 0,
			':mac_vat_date' => $this->validateDate($Data['mac_vat_date']) ? $Data['mac_vat_date'] : null,
			':mac_serie' => is_numeric($Data['mac_serie']) ? $Data['mac_serie'] : 0,
			':mac_number' => is_numeric($Data['mac_number']) ? $Data['mac_number'] : 0,
			':mac_bammntsys' => is_numeric($Data['mac_bammntsys']) ? $Data['mac_bammntsys'] : 0,
			':mac_bammnt' => is_numeric($Data['mac_bammnt']) ? $Data['mac_bammnt'] : 0,
			':mac_wtsum' => is_numeric($Data['mac_wtsum']) ? $Data['mac_wtsum'] : 0,
			':mac_vatsum' => is_numeric($Data['mac_vatsum']) ? $Data['mac_vatsum'] : 0,
			':mac_comments' => isset($Data['mac_comments']) ? $Data['mac_comments'] : NULL,
			':mac_create_date' => $this->validateDate($Data['mac_create_date']) ? $Data['mac_create_date'] : NULL,
			':mac_made_usuer' => isset($Data['mac_made_usuer']) ? $Data['mac_made_usuer'] : NULL,
			':mac_update_date' => $this->validateDate($Data['mac_update_date']) ? $Data['mac_update_date'] : NULL,
			':mac_update_user' => isset($Data['mac_update_user']) ? $Data['mac_update_user'] : NULL,
			':mac_currency' => isset($Data['mac_currency']) ? $Data['mac_currency'] : NULL,
			':mac_doctype' => 18,
			':business' => $Data['business'],
			':branch' => $Data['branch'],
			':mac_accperiod' => $periodo['data']
		));

		if (is_numeric($resInsert) && $resInsert > 0) {

			// Se actualiza la serie de la numeracion del documento

			$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
									WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id'      => $Data['mac_serie']
			));

			if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
			} else {
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'    => $resActualizarNumeracion,
					'mensaje'	=> 'No se pudo crear el asiento'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			// Fin de la actualizacion de la numeracion del documento


			foreach ($ContenidoDetalle as $key => $detail) {

				//VALIDANDO SI EXISTE LA CUENTA CONTABLE******
				$ValidateAccount = $this->pedeo->queryTable("SELECT acc_code FROM dacc WHERE acc_code = :acc_code", array(
					':acc_code' =>  $detail['ac1_account']
				));

				if (!isset($ValidateAccount[0])) {
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'    => $ValidateAccount,
						'mensaje'	=> 'No existe la cuenta contable ' . $detail['ac1_account'] . ' dentro del plan de cuentas'
					);

					$this->response($respuesta);

					return;
				}
				//*******
				//VALIDACION CENTRO DE COSTO, UNIDAD DE NEGOCIO, PROYECTO Y SOCIO DE NEGOCIO

				
				$ValidateDmcc = $this->pedeo->queryTable("SELECT * FROM dmcc WHERE trim(dcc_prc_code) = :dcc_prc_code", array(':dcc_prc_code' => trim($detail['ac1_prc_code'])));
				$ValidateDmun = $this->pedeo->queryTable("SELECT * FROM dmun WHERE trim(dun_un_code)  = :dun_un_code", array(':dun_un_code' => trim($detail['ac1_uncode'])));
				$ValidateDmpj = $this->pedeo->queryTable("SELECT * FROM dmpj WHERE trim(dpj_pj_code)  = :dpj_pj_code", array(':dpj_pj_code' => trim($detail['ac1_prj_code'])));
				$ValidateSn   = $this->pedeo->queryTable("SELECT * FROM dmsn WHERE trim(dms_card_code) = :dms_card_code", array(':dms_card_code' => trim($detail['ac1_legal_num'])));
				
				
				if (!empty(($detail['ac1_uncode']))) {
					if (!isset($ValidateDmun[0])) {
						$this->pedeo->trans_rollback();
	
						$respuesta = array(
							'error'   => true,
							'data'    => $ValidateDmun,
							'mensaje' => 'No existe la unidad de negocio ' . $detail['ac1_uncode']
						);
	
						$this->response($respuesta);
	
						return;
					}
				}

				if (!empty(($detail['ac1_prj_code']))) {
					if (!isset($ValidateDmpj[0])) {
						$this->pedeo->trans_rollback();
	
						$respuesta = array(
							'error'   => true,
							'data'    => $ValidateDmpj,
							'mensaje'	=> 'No existe el proyecto ' . $detail['ac1_prj_code']
						);
	
						$this->response($respuesta);
	
						return;
					}
				}

				if (!empty(($detail['ac1_prc_code']))) {

					if (!isset($ValidateDmcc[0])) {
						$this->pedeo->trans_rollback();
	
						$respuesta = array(
							'error'   => true,
							'data'    => $ValidateDmcc,
							'mensaje'	=> 'No existe el centro de costo ' . $detail['ac1_prc_code']
						);
	
						$this->response($respuesta);
	
						return;
					}
				}

			
				if (!empty(($detail['ac1_legal_num']))) {
					if (!isset($ValidateSn[0])) {
						$this->pedeo->trans_rollback();
	
						$respuesta = array(
							'error'   => true,
							'data'    => $ValidateSn,
							'mensaje'	=> 'No existe el socio de negocio ' . $detail['ac1_legal_num']
						);
	
						$this->response($respuesta);
	
						return;
					}
				}

				// SE AGREGA AL BALANCE
				if ( $detail['ac1_debit'] > 0 ){
					$BALANCE = $this->account->addBalance($periodo['data'], round($detail['ac1_debit'], $DECI_MALES), is_numeric($detail['ac1_account']) ? $detail['ac1_account'] : 0, 1, $detail['ac1_doc_date'], $Data['business'], $Data['branch']);

					$BUDGET = $this->account->validateBudgetAmount( $detail['ac1_account'], $detail['ac1_doc_date'], isset($detail['ac1_prc_code']) ? $detail['ac1_prc_code'] : NULL, isset($detail['ac1_uncode']) ? $detail['ac1_uncode'] : NULL, isset($detail['ac1_prj_code']) ? $detail['ac1_prj_code'] : NULL, round($detail['ac1_debit']), 1, $Data['business'] );
					if (isset($BUDGET['error']) && $BUDGET['error'] == true){
						$this->pedeo->trans_rollback();
	
						$respuesta = array(
							'error' => true,
							'data' => $BUDGET,
							'mensaje' => $BUDGET['mensaje']
						);
	
						return $this->response($respuesta);
					}
					
				}else{
					$BALANCE = $this->account->addBalance($periodo['data'], round($detail['ac1_credit'], $DECI_MALES), is_numeric($detail['ac1_account']) ? $detail['ac1_account'] : 0, 2, $detail['ac1_doc_date'], $Data['business'], $Data['branch']);

					$BUDGET = $this->account->validateBudgetAmount( $detail['ac1_account'], $detail['ac1_doc_date'], isset($detail['ac1_prc_code']) ? $detail['ac1_prc_code'] : NULL, isset($detail['ac1_uncode']) ? $detail['ac1_uncode'] : NULL, isset($detail['ac1_prj_code']) ? $detail['ac1_prj_code'] : NULL, round($detail['ac1_credit'], $DECI_MALES), 2, $Data['business'] );
					if (isset($BUDGET['error']) && $BUDGET['error'] == true){
						$this->pedeo->trans_rollback();
	
						$respuesta = array(
							'error' => true,
							'data' => $BUDGET,
							'mensaje' => $BUDGET['mensaje']
						);
	
						return $this->response($respuesta);
					}
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
				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(

					':ac1_trans_id' => $resInsert,
					':ac1_account' => is_numeric($detail['ac1_account']) ? $detail['ac1_account'] : 0,
					':ac1_debit' => is_numeric($detail['ac1_debit']) ? round($detail['ac1_debit'], $DECI_MALES) : 0,
					':ac1_credit' => is_numeric($detail['ac1_credit']) ? round($detail['ac1_credit'], $DECI_MALES) : 0,
					':ac1_debit_sys' => is_numeric($detail['ac1_debit_sys']) ? round($detail['ac1_debit_sys'], $DECI_MALES) : 0,
					':ac1_credit_sys' => is_numeric($detail['ac1_credit_sys']) ? round($detail['ac1_credit_sys'], $DECI_MALES) : 0,
					':ac1_currex' => is_numeric($detail['ac1_currex']) ? $detail['ac1_currex'] : 0,
					':ac1_doc_date' => $this->validateDate($detail['ac1_doc_date']) ? $detail['ac1_doc_date'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($detail['ac1_doc_duedate']) ? $detail['ac1_doc_duedate'] : NULL,
					':ac1_debit_import' => is_numeric($detail['ac1_debit_import']) ? $detail['ac1_debit_import'] : 0,
					':ac1_credit_import' => is_numeric($detail['ac1_credit_import']) ? $detail['ac1_credit_import'] : 0,
					':ac1_debit_importsys' => is_numeric($detail['ac1_debit_importsys']) ? $detail['ac1_debit_importsys'] : 0,
					':ac1_credit_importsys' => is_numeric($detail['ac1_credit_importsys']) ? $detail['ac1_credit_importsys'] : 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => is_numeric($detail['ac1_font_line']) ? $detail['ac1_font_line'] : 0,
					':ac1_font_type' => 18,
					':ac1_accountvs' => is_numeric($detail['ac1_accountvs']) ? $detail['ac1_accountvs'] : 0,
					':ac1_doctype' => is_numeric($detail['ac1_doctype']) ? $detail['ac1_doctype'] : 0,
					':ac1_ref1' => isset($detail['ac1_ref1']) ? $detail['ac1_ref1'] : NULL,
					':ac1_ref2' => isset($detail['ac1_ref2']) ? $detail['ac1_ref2'] : NULL,
					':ac1_ref3' => isset($detail['ac1_ref3']) ? $detail['ac1_ref3'] : NULL,
					':ac1_prc_code' => isset($detail['ac1_prc_code']) ? $detail['ac1_prc_code'] : NULL,
					':ac1_uncode' => isset($detail['ac1_uncode']) ? $detail['ac1_uncode'] : NULL,
					':ac1_prj_code' => isset($detail['ac1_prj_code']) ? $detail['ac1_prj_code'] : NULL,
					':ac1_rescon_date' => $this->validateDate($detail['ac1_rescon_date']) ? $detail['ac1_rescon_date'] : NULL,
					':ac1_recon_total' => is_numeric($detail['ac1_recon_total']) ? $detail['ac1_recon_total'] : 0,
					':ac1_made_user' => isset($detail['ac1_made_user']) ? $detail['ac1_made_user'] : NULL,
					':ac1_accperiod' => $periodo['data'],
					':ac1_close' => is_numeric($detail['ac1_close']) ? $detail['ac1_close'] : 0,
					':ac1_cord' => is_numeric($detail['ac1_cord']) ? $detail['ac1_cord'] : 0,
					':ac1_ven_debit' => is_numeric($detail['ac1_debit']) ? round($detail['ac1_debit'], $DECI_MALES) : 0,
					':ac1_ven_credit' => is_numeric($detail['ac1_credit']) ? round($detail['ac1_credit'], $DECI_MALES) : 0,
					':ac1_fiscal_acct' => is_numeric($detail['ac1_fiscal_acct']) ? $detail['ac1_fiscal_acct'] : 0,
					':ac1_taxid' => isset($detail['ac1_taxid']) ? $detail['ac1_taxid'] : 0,
					':ac1_isrti' => is_numeric($detail['ac1_isrti']) ? $detail['ac1_isrti'] : 0,
					':ac1_basert' => is_numeric($detail['ac1_basert']) ? $detail['ac1_basert'] : 0,
					':ac1_mmcode' => is_numeric($detail['ac1_mmcode']) ? $detail['ac1_mmcode'] : 0,
					':ac1_legal_num' => isset($detail['ac1_legal_num']) ? $detail['ac1_legal_num'] : NULL,
					':ac1_codref' => is_numeric($detail['ac1_codref']) ? $detail['ac1_codref'] : 0,
					':ac1_card_type' => isset($detail['ac1_card_type']) ? $detail['ac1_card_type'] : 0,
					':business' => $Data['business'],
					':branch'   => $Data['branch'],
					':ac1_codret' => isset($detail['ac1_codret']) && !empty($detail['ac1_codret']) ? $detail['ac1_codret'] : NULL,
					':ac1_base_tax' => isset($detail['ac1_base_tax']) && !empty($detail['ac1_base_tax']) ? $detail['ac1_base_tax'] : NULL		
				));

				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
				} else {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'    => $resInsertDetail,
						'mensaje'	=> 'No se pudo registrar el asiento contable'
					);

					$this->response($respuesta);

					return;
				}
			}


			//VALIDANDDO DIFERENCIA EN PESO DE MONEDA DE SISTEMA

			$sqlDiffPeso = "SELECT sum(coalesce(ac1_debit_sys,0)) as debito, sum(coalesce(ac1_credit_sys,0)) as credito,
														sum(coalesce(ac1_debit,0)) as ldebito, sum(coalesce(ac1_credit,0)) as lcredito
														from mac1
														where ac1_trans_id = :ac1_trans_id";

			$resDiffPeso = $this->pedeo->queryTable($sqlDiffPeso, array(
				':ac1_trans_id' => $resInsert
			));




			if (isset($resDiffPeso[0]['debito']) && abs(($resDiffPeso[0]['debito'] - $resDiffPeso[0]['credito'])) > 0) {

				$sqlCuentaDiferenciaDecimal = "SELECT pge_acc_ajp FROM pgem";
				$resCuentaDiferenciaDecimal = $this->pedeo->queryTable($sqlCuentaDiferenciaDecimal, array());

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

				$resDetalleAsiento = $this->pedeo->insertRow($sqlInsertDetail, array(

					':ac1_trans_id' => $resInsert,
					':ac1_account' => $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'],
					':ac1_debit' => 0,
					':ac1_credit' => 0,
					':ac1_debit_sys' => round($debito, $DECI_MALES),
					':ac1_credit_sys' => round($credito, $DECI_MALES),
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['mac_doc_date']) ? $Data['mac_doc_date'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['mac_doc_duedate']) ? $Data['mac_doc_duedate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => 18,
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
					':ac1_made_user' => isset($Data['mac_made_usuer']) ? $Data['mac_made_usuer'] : NULL,
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
					':ac1_legal_num' => NULL,
					':ac1_codref' => 1,
					':ac1_card_type' => 0,
					':business' => $Data['business'],
					':branch'   => $Data['branch'],
					':ac1_codret' => isset($detail['ac1_codret']) && !empty($detail['ac1_codret']) ? $detail['ac1_codret'] : NULL,
					':ac1_base_tax' => isset($detail['ac1_base_tax']) && !empty($detail['ac1_base_tax']) ? $detail['ac1_base_tax'] : NULL	
				));



				if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {
					// si falla algun insert del detalle  se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $resDetalleAsiento,
						'mensaje'	=> 'No se pudo registrar el asiento contable'
					);

					$this->response($respuesta);

					return;
				}
			} else if (isset($resDiffPeso[0]['ldebito']) && abs(($resDiffPeso[0]['ldebito'] - $resDiffPeso[0]['lcredito'])) > 0) {

				$sqlCuentaDiferenciaDecimal = "SELECT pge_acc_ajp FROM pgem";
				$resCuentaDiferenciaDecimal = $this->pedeo->queryTable($sqlCuentaDiferenciaDecimal, array());

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
					$BALANCE = $this->account->addBalance($periodo['data'], round($ldebito, $DECI_MALES), $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'], 1, $detail['ac1_doc_date'], $Data['business'], $Data['branch']);

					$BUDGET = $this->account->validateBudgetAmount( $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'], $detail['ac1_doc_date'], '', '', '', round($ldebito, $DECI_MALES), 1, $Data['business'] );
					if (isset($BUDGET['error']) && $BUDGET['error'] == true){
						$this->pedeo->trans_rollback();
	
						$respuesta = array(
							'error' => true,
							'data' => $BUDGET,
							'mensaje' => $BUDGET['mensaje']
						);
	
						return $this->response($respuesta);
					}
				}else{
					$BALANCE = $this->account->addBalance($periodo['data'], round($lcredito, $DECI_MALES), $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'], 2, $detail['ac1_doc_date'], $Data['business'], $Data['branch']);

					$BUDGET = $this->account->validateBudgetAmount( $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'], $detail['ac1_doc_date'], '', '', '', round($lcredito, $DECI_MALES), 2, $Data['business'] );
					if (isset($BUDGET['error']) && $BUDGET['error'] == true){
						$this->pedeo->trans_rollback();
	
						$respuesta = array(
							'error' => true,
							'data' => $BUDGET,
							'mensaje' => $BUDGET['mensaje']
						);
	
						return $this->response($respuesta);
					}
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

				$resDetalleAsiento = $this->pedeo->insertRow($sqlInsertDetail, array(

					':ac1_trans_id' => $resInsert,
					':ac1_account' => $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'],
					':ac1_debit' => round($ldebito, $DECI_MALES),
					':ac1_credit' => round($lcredito, $DECI_MALES),
					':ac1_debit_sys' => 0,
					':ac1_credit_sys' => 0,
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['mac_doc_date']) ? $Data['mac_doc_date'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['mac_doc_duedate']) ? $Data['mac_doc_duedate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => 18,
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
					':ac1_made_user' => isset($Data['mac_made_usuer']) ? $Data['mac_made_usuer'] : NULL,
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
					':ac1_legal_num' => NULL,
					':ac1_codref' => 1,
					':ac1_card_type' => 0,
					':business' => $Data['business'],
					':branch'   => $Data['branch'],
					':ac1_codret' => isset($detail['ac1_codret']) && !empty($detail['ac1_codret']) ? $detail['ac1_codret'] : NULL,
					':ac1_base_tax' => isset($detail['ac1_base_tax']) && !empty($detail['ac1_base_tax']) ? $detail['ac1_base_tax'] : NULL	

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
						'mensaje'	=> 'No se encontro la cuenta para adicionar la diferencia en decimales'
					);

					$this->response($respuesta);

					return;
				}
			}


			// $sqlmac1 = "SELECT * FROM  mac1 where ac1_trans_id =:ac1_trans_id";
			// $ressqlmac1 = $this->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $resInsert));
			// print_r(json_encode($ressqlmac1));
			// exit;



			//SE VALIDA LA CONTABILIDAD CREADA
			$validateCont = $this->generic->validateAccountingAccent2($resInsert);


			if (isset($validateCont['error']) && $validateCont['error'] == false) {
			} else {

				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data' 	 => $validateCont['data'],
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
				'mensaje' => 'Asiento contable registrado con exito'
			);
		} else {
			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data'    => $resInsert,
				'mensaje'	=> 'No se pudo registrar el asiento contable'
			);
		}

		$this->response($respuesta);
	}


	// OBTENER ASIENTOS CONTABLES
	public function getAccountingAccent_get()
	{

		$Data = $this->get();

		if (!isset($Data['business']) or !isset($Data['branch'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT	distinct
						t0.mac_trans_id docnum,
						t0.mac_trans_id numero_transaccion,
					case
						when coalesce(t0.mac_base_type,0) = 3 then 'Entrega'
						when coalesce(t0.mac_base_type,0) = 4 then 'Devolucion'
						when coalesce(t0.mac_base_type,0) = 5 then 'Factura Cliente'
						when coalesce(t0.mac_base_type,0) = 6 then 'Nota Credito Cliente'
						when coalesce(t0.mac_base_type,0) = 7 then 'Nota Debito Cliente'
						when coalesce(t0.mac_base_type,0) = 8 then 'Salida Mercancia'
						when coalesce(t0.mac_base_type,0) = 9 then 'Entrada Mercancia'
						when coalesce(t0.mac_base_type,0) = 13 then 'Entrada Compras'
						when coalesce(t0.mac_base_type,0) = 14 then 'Devolucion Compra'
						when coalesce(t0.mac_base_type,0) = 15 then 'Factura Proveedores'
						when coalesce(t0.mac_base_type,0) = 16 then 'Nota Credito Compras'
						when coalesce(t0.mac_base_type,0) = 17 then 'Nota Debito Compras'
						when coalesce(t0.mac_base_type,0) = 18 then 'Asiento Manual'
						when coalesce(t0.mac_base_type,0) = 0 then 'Asiento Manual'
						when coalesce(t0.mac_base_type,0) = 19 then 'Pagos Efectuado'
						when coalesce(t0.mac_base_type,0) = 20 then 'Pagos Recibidos'
						when coalesce(t0.mac_base_type,0) = 22 then 'Reconciliación'
						when coalesce(t0.mac_base_type,0) = 24 then 'Transferencia de Stock'
						when coalesce(t0.mac_base_type,0) = 31 then 'Conciliación Bancaria'
						when coalesce(t0.mac_base_type,0) = 26 then 'Revalorizacion de Inventario'
						when coalesce(t0.mac_base_type,0) = 46 then 'Factura Anticipada de Compras'
						when coalesce(t0.mac_base_type,0) = 34 then 'Factura Anticipada de Ventas'
						when coalesce(t0.mac_base_type,0) = 37 then 'Legalización de Gastos'
						when coalesce(t0.mac_base_type,0) = 27 then 'Emisión de Fabricación'
						when coalesce(t0.mac_base_type,0) = 47 then 'Recibo de Caja Distribución'
						when coalesce(t0.mac_base_type,0) = 48 then 'Cierre de Caja'
						when coalesce(t0.mac_base_type,0) = 49 then 'Anulación de Cierre de Caja'
						when coalesce(t0.mac_base_type,0) = 50 then 'Anulación de Pagos'
						when coalesce(t0.mac_base_type,0) = 30 then 'Recepción de Fabricación'
						when coalesce(t0.mac_base_type,0) = 51 then 'Cierre de Periodos'
						when coalesce(t0.mac_base_type,0) = 33 then 'Pago Masivo'
					end origen,
					case
						when coalesce(t0.mac_base_type,0) = 3 then t1.vem_docnum
						when coalesce(t0.mac_base_type,0) = 4 then t2.vdv_docnum
						when coalesce(t0.mac_base_type,0) = 5 then t3.dvf_docnum
						when coalesce(t0.mac_base_type,0) = 6 then t10.vnc_docnum
						when coalesce(t0.mac_base_type,0) = 6 then t11.vnd_docnum
						when coalesce(t0.mac_base_type,0) = 8 then t5.isi_docnum
						when coalesce(t0.mac_base_type,0) = 9 then t6.iei_docnum
						when coalesce(t0.mac_base_type,0) = 13 then t12.cec_docnum
						when coalesce(t0.mac_base_type,0) = 14 then t13.cdc_docnum
						when coalesce(t0.mac_base_type,0) = 15 then t7.cfc_docnum
						when coalesce(t0.mac_base_type,0) = 16 then t14.cnc_docnum
						when coalesce(t0.mac_base_type,0) = 17 then t15.cnd_docnum
						when coalesce(t0.mac_base_type,0) = 18 then t0.mac_trans_id
						when coalesce(t0.mac_base_type,0) = 0 then t0.mac_trans_id
						when coalesce(t0.mac_base_type,0) = 19 then t8.bpe_docnum
						when coalesce(t0.mac_base_type,0) = 20 then t9.bpr_docnum
						when coalesce(t0.mac_base_type,0) = 22 then t18.crc_docnum
						when coalesce(t0.mac_base_type,0) = 24 then t17.its_docnum
						when coalesce(t0.mac_base_type,0) = 31 then t19.crb_docnum
						when coalesce(t0.mac_base_type,0) = 26 then t20.iri_docnum
						when coalesce(t0.mac_base_type,0) = 46 then t7.cfc_docnum
						when coalesce(t0.mac_base_type,0) = 34 then t3.dvf_docnum
						when coalesce(t0.mac_base_type,0) = 37 then t21.blg_docnum
						when coalesce(t0.mac_base_type,0) = 27 then t22.bep_docnum
						when coalesce(t0.mac_base_type,0) = 47 then t23.vrc_docnum
						when coalesce(t0.mac_base_type,0) = 48 then t24.bco_docnum
						when coalesce(t0.mac_base_type,0) = 48 then t25.bco_docnum
						when coalesce(t0.mac_base_type,0) = 50 then t26.ban_docnum
						when coalesce(t0.mac_base_type,0) = 30 then t27.brp_docnum
						when coalesce(t0.mac_base_type,0) = 51 then t28.mcp_docnum
						when coalesce(t0.mac_base_type,0) = 33 then t29.spm_docnum
					end numero_origen,
					case
						when coalesce(t0.mac_base_type,0) = 3 then t1.vem_currency
						when coalesce(t0.mac_base_type,0) = 4 then t2.vdv_currency
						when coalesce(t0.mac_base_type,0) = 5 then t3.dvf_currency
						when coalesce(t0.mac_base_type,0) = 6 then t10.vnc_currency
						when coalesce(t0.mac_base_type,0) = 6 then t11.vnd_currency
						when coalesce(t0.mac_base_type,0) = 8 then t5.isi_currency
						when coalesce(t0.mac_base_type,0) = 9 then t6.iei_currency
						when coalesce(t0.mac_base_type,0) = 13 then t12.cec_currency
						when coalesce(t0.mac_base_type,0) = 14 then t13.cdc_currency
						when coalesce(t0.mac_base_type,0) = 15 then t7.cfc_currency
						when coalesce(t0.mac_base_type,0) = 16 then t14.cnc_currency
						when coalesce(t0.mac_base_type,0) = 17 then t15.cnd_currency
						when coalesce(t0.mac_base_type,0) = 18 then t0.mac_currency
						when coalesce(t0.mac_base_type,0) = 0 then t0.mac_currency
						when coalesce(t0.mac_base_type,0) = 19 then t8.bpe_currency
						when coalesce(t0.mac_base_type,0) = 20 then t9.bpr_currency
						when coalesce(t0.mac_base_type,0) = 22 then t18.crc_currency
						when coalesce(t0.mac_base_type,0) = 24 then t17.its_currency
						when coalesce(t0.mac_base_type,0) = 31 then t19.crb_currency
						when coalesce(t0.mac_base_type,0) = 26 then t20.iri_currency
						when coalesce(t0.mac_base_type,0) = 46 then t7.cfc_currency
						when coalesce(t0.mac_base_type,0) = 34 then t3.dvf_currency
						when coalesce(t0.mac_base_type,0) = 37 then t21.blg_currency
						when coalesce(t0.mac_base_type,0) = 27 then get_localcur()
						when coalesce(t0.mac_base_type,0) = 47 then t23.vrc_currency
						when coalesce(t0.mac_base_type,0) = 48 then t24.bco_currency
						when coalesce(t0.mac_base_type,0) = 48 then t25.bco_currency
						when coalesce(t0.mac_base_type,0) = 50 then t26.ban_currency
						when coalesce(t0.mac_base_type,0) = 30 then t27.brp_currency
						when coalesce(t0.mac_base_type,0) = 51 then t28.mcp_currency
						when coalesce(t0.mac_base_type,0) = 33 then t29.spm_currency
					end currency,
					case
						when coalesce(t0.mac_base_type,0) = 3 then get_tax_currency(t1.vem_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 4 then get_tax_currency(t2.vdv_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 5 then get_tax_currency(t3.dvf_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 6 then get_tax_currency(t10.vnc_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 6 then get_tax_currency(t11.vnd_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 8 then get_tax_currency(t5.isi_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 9 then get_tax_currency(t6.iei_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 13 then get_tax_currency(t12.cec_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 14 then get_tax_currency(t13.cdc_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 15 then get_tax_currency(t7.cfc_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 16 then get_tax_currency(t14.cnc_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 17 then get_tax_currency(t15.cnd_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 18 or coalesce(t0.mac_base_type,0) = 0 then get_tax_currency(t0.mac_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 19 then get_tax_currency(t8.bpe_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 20 then get_tax_currency(t9.bpr_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 22 then get_tax_currency(t18.crc_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 24 then get_tax_currency(t17.its_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 31 then get_tax_currency(t19.crb_currency,t19.crb_startdate)
						when coalesce(t0.mac_base_type,0) = 26 then get_tax_currency(t20.iri_currency,t20.iri_docdate)
						when coalesce(t0.mac_base_type,0) = 46 then get_tax_currency(t7.cfc_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 34 then get_tax_currency(t3.dvf_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 37 then get_tax_currency(t21.blg_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 27 then get_tax_currency(get_localcur(),mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 47 then get_tax_currency(t23.vrc_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 48 then get_tax_currency(t24.bco_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 48 then get_tax_currency(t25.bco_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 50 then get_tax_currency(t26.ban_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 30 then get_tax_currency(t27.brp_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 51 then get_tax_currency(t28.mcp_currency,mac_doc_date)
						when coalesce(t0.mac_base_type,0) = 33 then get_tax_currency(t29.spm_currency,mac_doc_date)
					end tsa_value,
					t0.*
					from tmac t0
					left join dvem t1 on t0.mac_base_entry = t1.vem_docentry and t0.mac_base_type= t1.vem_doctype
					left join dvdv t2 on t0.mac_base_entry = t2.vdv_docentry and t0.mac_base_type= t2.vdv_doctype
					left join dvfv t3 on t0.mac_base_entry = t3.dvf_docentry and t0.mac_base_type= t3.dvf_doctype
					left join misi t5 on t0.mac_base_entry = t5.isi_docentry and t0.mac_base_type= t5.isi_doctype
					left join miei t6 on t0.mac_base_entry = t6.iei_docentry and t0.mac_base_type= t6.iei_doctype
					left join dcfc t7 on t0.mac_base_entry = t7.cfc_docentry and t0.mac_base_type= t7.cfc_doctype
					left join gbpe t8 on t0.mac_base_entry = t8.bpe_docentry and t0.mac_base_type= t8.bpe_doctype
					left join gbpr t9 on t0.mac_base_entry = t9.bpr_docentry and t0.mac_base_type= t9.bpr_doctype
					left join dvnc t10 on t0.mac_base_entry = t10.vnc_docentry and t0.mac_base_type= t10.vnc_doctype
					left join dvnd t11 on t0.mac_base_entry = t11.vnd_docentry and t0.mac_base_type= t11.vnd_doctype
					left join dcec t12 on t0.mac_base_entry = t12.cec_docentry and t0.mac_base_type= t12.cec_doctype
					left join dcdc t13 on t0.mac_base_entry = t13.cdc_docentry and t0.mac_base_type= t13.cdc_doctype
					left join dcnc t14 on t0.mac_base_entry = t14.cnc_docentry and t0.mac_base_type= t14.cnc_doctype
					left join dcnd t15 on t0.mac_base_entry = t15.cnd_docentry and t0.mac_base_type= t15.cnd_doctype
					left join dits t17 on t0.mac_base_entry = t17.its_docentry  and t0.mac_base_type = t17.its_doctype
					left join dcrc t18 on t0.mac_base_entry = t18.crc_docentry  and t0.mac_base_type = t18.crc_doctype
					left join dcrb t19 on t0.mac_base_entry = t19.crb_id and t0.mac_base_type = t19.crb_doctype
					left join diri t20 on t0.mac_base_entry = t20.iri_docentry and t0.mac_base_type = t20.iri_doctype
					left join tblg t21 on t0.mac_base_entry = t21.blg_docentry and t0.mac_base_type = t21.blg_doctype
					left join tbep t22 on t0.mac_base_entry = t22.bep_docentry and t0.mac_base_type = t22.bep_doctype
					left join dvrc t23 on t0.mac_base_entry = t23.vrc_docentry and t0.mac_base_type = t23.vrc_doctype
					left join tbco t24 on t0.mac_base_entry = t24.bco_id and t0.mac_base_type = t24.bco_doctype
					left join tbco t25 on t0.mac_base_entry = t25.bco_id and t0.mac_base_type = t25.bco_doctype
					left join tban t26 on t0.mac_base_entry = t26.ban_docentry and t0.mac_base_type = t26.ban_doctype
					left join tbrp t27 on t0.mac_base_entry = t27.brp_docentry and t0.mac_base_type = t27.brp_doctype
					left join tmcp t28 on t0.mac_base_entry = t28.mcp_docentry and t0.mac_base_type = t28.mcp_doctype
					left join tspm t29 on t0.mac_base_entry = t29.spm_docentry and t0.mac_base_type = t29.spm_doctype
					WHERE t0.business = :business AND t0.branch = :branch
				ORDER BY mac_trans_id ASC";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":business" => $Data['business'], ":branch" => $Data['branch']));

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


	// OBTENER ASIENTO CONTABLE POR ID
	public function getAccountingAccentById_get()
	{

		$Data = $this->get();

		if (!isset($Data['mac_trans_id'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT DISTINCT
			t0.ac1_trans_id docnum,
			t0.ac1_trans_id numero_transaccion,
			case
				when coalesce(t0.ac1_font_type,0) = 3 then 'Entrega'
				when coalesce(t0.ac1_font_type,0) = 4 then 'Devolucion'
				when coalesce(t0.ac1_font_type,0) = 5 then 'Factura Cliente'
				when coalesce(t0.ac1_font_type,0) = 6 then 'Nota Credito Cliente'
				when coalesce(t0.ac1_font_type,0) = 7 then 'Nota Debito Cliente'
				when coalesce(t0.ac1_font_type,0) = 8 then 'Salida Mercancia'
				when coalesce(t0.ac1_font_type,0) = 9 then 'Entrada Mercancia'
				when coalesce(t0.ac1_font_type,0) = 13 then 'Entrada Compras'
				when coalesce(t0.ac1_font_type,0) = 14 then 'Devolucion Compra'
				when coalesce(t0.ac1_font_type,0) = 15 then 'Factura Proveedores'
				when coalesce(t0.ac1_font_type,0) = 16 then 'Nota Credito Compras'
				when coalesce(t0.ac1_font_type,0) = 17 then 'Nota Debito Compras'
				when coalesce(t0.ac1_font_type,0) = 18 then 'Asiento Manual'
				when coalesce(t0.ac1_font_type,0) = 19 then 'Pagos Efectuado'
				when coalesce(t0.ac1_font_type,0) = 20 then 'Pagos Recibidos'
			end origen,
			case
				when coalesce(t0.ac1_font_type,0) = 3 then t1.vem_docnum
				when coalesce(t0.ac1_font_type,0) = 4 then t2.vdv_docnum
				when coalesce(t0.ac1_font_type,0) = 5 then t3.dvf_docnum
				when coalesce(t0.ac1_font_type,0) = 6 then t10.vnc_docnum
				when coalesce(t0.ac1_font_type,0) = 6 then t11.vnd_docnum
				when coalesce(t0.ac1_font_type,0) = 8 then t5.isi_docnum
				when coalesce(t0.ac1_font_type,0) = 9 then t6.iei_docnum
				when coalesce(t0.ac1_font_type,0) = 13 then t12.cec_docnum
				when coalesce(t0.ac1_font_type,0) = 14 then t13.cdc_docnum
				when coalesce(t0.ac1_font_type,0) = 15 then t14.cnc_docnum
				when coalesce(t0.ac1_font_type,0) = 16 then t15.cnd_docnum
				when coalesce(t0.ac1_font_type,0) = 17 then t12.cec_docnum
				when coalesce(t0.ac1_font_type,0) = 18 then t0.ac1_trans_id
				when coalesce(t0.ac1_font_type,0) = 19 then t8.bpe_docnum
				when coalesce(t0.ac1_font_type,0) = 20 then t9.bpr_docnum
			end numero_origen,
			COALESCE(t4.acc_name,'CUENTA PUENTE') nombre_cuenta,t0.*
			from mac1 t0
			left join dvem t1 on t0.ac1_font_key = t1.vem_docentry and t0.ac1_font_type = t1.vem_doctype
			left join dvdv t2 on t0.ac1_font_key = t2.vdv_docentry and t0.ac1_font_type = t2.vdv_doctype
			left join dvfv t3 on t0.ac1_font_key = t3.dvf_docentry and t0.ac1_font_type = t3.dvf_doctype
			Left join dacc t4 on t0.ac1_account = t4.acc_code
			left join misi t5 on t0.ac1_font_key = t5.isi_docentry and t0.ac1_font_type = t5.isi_doctype
			left join miei t6 on t0.ac1_font_key = t6.iei_docentry and t0.ac1_font_type = t6.iei_doctype
			left join dcfc t7 on t0.ac1_font_key = t7.cfc_docentry and t0.ac1_font_type = t7.cfc_doctype
			left join gbpe t8 on t0.ac1_font_key = t8.bpe_docentry and t0.ac1_font_type = t8.bpe_doctype
			left join gbpr t9 on t0.ac1_font_key = t9.bpr_docentry and t0.ac1_font_type = t9.bpr_doctype
			left join dvnc t10 on t0.ac1_font_key = t10.vnc_docentry and t0.ac1_font_type = t10.vnc_doctype
			left join dvnd t11 on t0.ac1_font_key = t11.vnd_docentry and t0.ac1_font_type = t11.vnd_doctype
			left join dcec t12 on t0.ac1_font_key = t12.cec_docentry and t0.ac1_font_type = t12.cec_doctype
			left join dcdc t13 on t0.ac1_font_key = t13.cdc_docentry and t0.ac1_font_type = t13.cdc_doctype
			left join dcnc t14 on t0.ac1_font_key = t14.cnc_docentry and t0.ac1_font_type = t14.cnc_doctype
			left join dcnd t15 on t0.ac1_font_key = t15.cnd_docentry and t0.ac1_font_type = t15.cnd_doctype
			WHERE mac_trans_id = :mac_trans_id";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':mac_trans_id' => $Data['mac_trans_id']));

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


	//OBTENER DETALLE ASIENTO CONTABLE POR ID ASIENTO
	public function getAccountingAccentDetail_get()
	{

		$Data = $this->get();

		if (!isset($Data['ac1_trans_id'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}
		$sqlSelect = "--ENTREGA DE VENTAS
											SELECT distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											dvem.vem_docnum as numero_origen,
											dvem.vem_currency as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(dvem.vem_currency,dvem.vem_docdate) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join dvem
											on dvem.vem_doctype = mac1.ac1_font_type
											and dvem.vem_docentry = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id
											-- DEVOLUCION DE VENTAS
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											dvdv.vdv_docnum as numero_origen,
											dvdv.vdv_currency as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(dvdv.vdv_currency,dvdv.vdv_docdate) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join dvdv
											on dvdv.vdv_doctype = mac1.ac1_font_type
											and dvdv.vdv_docentry = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id
											--FACTURA DE VENTAS
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											dvfv.dvf_docnum as numero_origen,
											dvfv.dvf_currency as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(dvfv.dvf_currency,dvfv.dvf_docdate) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join dvfv
											on dvfv.dvf_doctype = mac1.ac1_font_type
											and dvfv.dvf_docentry = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id
											--NOTA CREDITO DE VENTAS
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											dvnc.vnc_docnum as numero_origen,
											dvnc.vnc_currency as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(dvnc.vnc_currency,dvnc.vnc_docdate) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join dvnc
											on dvnc.vnc_doctype = mac1.ac1_font_type
											and dvnc.vnc_docentry = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id
											--NOTA DEBITO DE VENTAS
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											dvnd.vnd_docnum as numero_origen,
											dvnd.vnd_currency as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(dvnd.vnd_currency,dvnd.vnd_docdate) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join dvnd
											on dvnd.vnd_doctype = mac1.ac1_font_type
											and dvnd.vnd_docentry = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id
											--ENTRADA DE COMPRAS
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											dcec.cec_docnum as numero_origen,
											dcec.cec_currency as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(dcec.cec_currency,dcec.cec_docdate) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join dcec
											on dcec.cec_doctype = mac1.ac1_font_type
											and dcec.cec_docentry = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id
											--DEVOLUCION DE COMPRAS
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											dcdc.cdc_docnum as numero_origen,
											dcdc.cdc_currency as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(dcdc.cdc_currency,dcdc.cdc_docdate) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join dcdc
											on dcdc.cdc_doctype = mac1.ac1_font_type
											and dcdc.cdc_docentry = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id
											--FACTURA DE COMPRAS
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											dcfc.cfc_docnum as numero_origen,
											dcfc.cfc_currency as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(dcfc.cfc_currency,dcfc.cfc_docdate) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join dcfc
											on dcfc.cfc_doctype = mac1.ac1_font_type
											and dcfc.cfc_docentry = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id
											--NOTA CREDITO DE COMPRAS
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											dcnc.cnc_docnum as numero_origen,
											dcnc.cnc_currency as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(dcnc.cnc_currency,dcnc.cnc_docdate) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join dcnc
											on dcnc.cnc_doctype = mac1.ac1_font_type
											and dcnc.cnc_docentry = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id
											--NOTA DEBITO DE COMPRAS
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											dcnd.cnd_docnum as numero_origen,
											dcnd.cnd_currency as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(dcnd.cnd_currency,dcnd.cnd_docdate) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join dcnd
											on dcnd.cnd_doctype = mac1.ac1_font_type
											and dcnd.cnd_docentry = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id
											--SALIDA DE INVENTARIO
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											misi.isi_docnum as numero_origen,
											misi.isi_currency as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(misi.isi_currency,misi.isi_docdate) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join misi
											on misi.isi_doctype = mac1.ac1_font_type
											and misi.isi_docentry = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id
											--ENTRADA DE INVENTARIO
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											miei.iei_docnum as numero_origen,
											miei.iei_currency as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(miei.iei_currency,miei.iei_docdate) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join miei
											on miei.iei_doctype = mac1.ac1_font_type
											and miei.iei_docentry = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id
											--GESTION DE BANCO PAGOS EFECTUADOS
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											gbpe.bpe_docnum as numero_origen,
											gbpe.bpe_currency as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(gbpe.bpe_currency,gbpe.bpe_docdate) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join gbpe
											on gbpe.bpe_doctype = mac1.ac1_font_type
											and gbpe.bpe_docentry = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id
											--GESTION DE BANCO PAGOS RECIBIDOS
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											gbpr.bpr_docnum as numero_origen,
											gbpr.bpr_currency as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(gbpr.bpr_currency,gbpr.bpr_docdate) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join gbpr
											on gbpr.bpr_doctype = mac1.ac1_font_type
											and gbpr.bpr_docentry = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id
											--RECONCILIACION DE CUENTAS
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											dcrc.crc_docnum as numero_origen,
											dcrc.crc_currency as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(dcrc.crc_currency,dcrc.crc_docdate) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join dcrc
											on dcrc.crc_doctype = mac1.ac1_font_type
											and dcrc.crc_docentry = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id
											-- Asiento manual
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											mac1.ac1_trans_id as numero_origen,
											get_localcur() as currency,
											coalesce(acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(get_localcur(),mac1.ac1_doc_date) as tsa_value,
											mac1.*
											from mac1
											join dmdt on ac1_font_type = mdt_doctype
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where ac1_trans_id = :ac1_trans_id
											and  mac1.ac1_font_type = 18
											-- Conciliación de Bancos
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											dcrb.crb_docnum as numero_origen,
											dcrb.crb_currency as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(dcrb.crb_currency,dcrb.crb_startdate) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join dcrb
											on dcrb.crb_doctype = mac1.ac1_font_type
											and dcrb.crb_id = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id
											--- REVALORIZACION DE INVENTARIO
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											diri.iri_docnum as numero_origen,
											diri.iri_currency as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(diri.iri_currency,diri.iri_docdate) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join diri
											on diri.iri_doctype = mac1.ac1_font_type
											and diri.iri_docentry = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id
											-- LEGALIZACION DE GASTO
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											tblg.blg_docnum as numero_origen,
											tblg.blg_currency as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(tblg.blg_currency,tblg.blg_docdate) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join tblg
											on tblg.blg_doctype = mac1.ac1_font_type
											and tblg.blg_docentry = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id
											-- EMISIÓN DE FABRICACIÓN
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											tbep.bep_docnum as numero_origen,
											get_localcur() as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(get_localcur(),tbep.bep_docdate) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join tbep
											on tbep.bep_doctype = mac1.ac1_font_type
											and tbep.bep_docentry = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id
											-- TRASLADO DE MERCANCIA
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											dits.its_docnum as numero_origen,
											dits.its_currency as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(dits.its_currency,dits.its_docdate) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join dits
											on dits.its_doctype = mac1.ac1_font_type
											and dits.its_docentry = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id
											-- RECIBO DE CAJA CHICA
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											dvrc.vrc_docnum as numero_origen,
											dvrc.vrc_currency as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(dvrc.vrc_currency,dvrc.vrc_docdate) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join dvrc
											on dvrc.vrc_doctype = mac1.ac1_font_type
											and dvrc.vrc_docentry = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id
											--CIERRES DE CAJA
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											tbco.bco_docnum as numero_origen,
											tbco.bco_currency as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(tbco.bco_currency,tbco.bco_date) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join tbco
											on tbco.bco_doctype = mac1.ac1_font_type
											and tbco.bco_id = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id
											-- ANULACION DE PAGOS
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											tban.ban_docnum as numero_origen,
											tban.ban_currency as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(tban.ban_currency,tban.ban_docdate) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join tban
											on tban.ban_doctype = mac1.ac1_font_type
											and tban.ban_docentry = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id
											-- RECEPCIÓN DE FABRICACIÓN
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											tbrp.brp_docnum as numero_origen,
											tbrp.brp_currency as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(tbrp.brp_currency,tbrp.brp_docdate) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join tbrp
											on tbrp.brp_doctype = mac1.ac1_font_type
											and tbrp.brp_docentry = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id
											-- CIERRE DE PERIODOS
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											tmcp.mcp_docnum as numero_origen,
											tmcp.mcp_currency as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(tmcp.mcp_currency,tmcp.mcp_docdate) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join tmcp
											on tmcp.mcp_doctype = mac1.ac1_font_type
											and tmcp.mcp_docentry = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id
											--PAGO MASIVO
											union all
											select distinct
											mac1.ac1_trans_id as docnum,
											mac1.ac1_trans_id as numero_transaccion,
											dmdt.mdt_docname as origen,
											tspm.spm_docnum as numero_origen,
											tspm.spm_currency as currency,
											coalesce(dacc.acc_name,'Cuenta puente') nombre_cuenta,
											get_tax_currency(tspm.spm_currency,tspm.spm_docdate) as tsa_value,
											mac1.*
											from mac1
											inner join dmdt
											on mac1.ac1_font_type = dmdt.mdt_doctype
											inner join tspm
											on tspm.spm_doctype = mac1.ac1_font_type
											and tspm.spm_docentry = mac1.ac1_font_key
											left join dacc
											on mac1.ac1_account = dacc.acc_code
											where mac1.ac1_trans_id = :ac1_trans_id";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':ac1_trans_id' => $Data['ac1_trans_id']));

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

	public function getAccentByDoc_post()
	{
		$Data = $this->post();

		if (!isset($Data['mac_base_type']) or !isset($Data['mac_base_entry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT distinct t0.*,
		dmdt.mdt_docname as origen,
		case
		when coalesce(t0.mac_base_type,0) = 3 then t1.vem_currency
		when coalesce(t0.mac_base_type,0) = 4 then t2.vdv_currency
		when coalesce(t0.mac_base_type,0) = 5 then t3.dvf_currency
		when coalesce(t0.mac_base_type,0) = 6 then t10.vnc_currency
		when coalesce(t0.mac_base_type,0) = 7 then t11.vnd_currency
		when coalesce(t0.mac_base_type,0) = 8 then t5.isi_currency
		when coalesce(t0.mac_base_type,0) = 9 then t6.iei_currency
		when coalesce(t0.mac_base_type,0) = 13 then t12.cec_currency
		when coalesce(t0.mac_base_type,0) = 14 then t13.cdc_currency
		when coalesce(t0.mac_base_type,0) = 15 then t7.cfc_currency
		when coalesce(t0.mac_base_type,0) = 16 then t14.cnc_currency
		when coalesce(t0.mac_base_type,0) = 17 then t15.cnd_currency
		when coalesce(t0.mac_base_type,0) = 18 then get_localcur()
		when coalesce(t0.mac_base_type,0) = 19 then t8.bpe_currency
		when coalesce(t0.mac_base_type,0) = 20 then t9.bpr_currency
		when coalesce(t0.mac_base_type,0) = 22 then t17.crc_currency
		when coalesce(t0.mac_base_type,0) = 26 then t18.iri_currency
		when coalesce(t0.mac_base_type,0) = 37 then t19.blg_currency
		when coalesce(t0.mac_base_type,0) = 46 then t7.cfc_currency
		when coalesce(t0.mac_base_type,0) = 27 then get_localcur()
		end  as currency,
		case
		when coalesce(t0.mac_base_type,0) = 3 then get_tax_currency(t1.vem_currency,t1.vem_docdate)
		when coalesce(t0.mac_base_type,0) = 4 then get_tax_currency(t2.vdv_currency,t2.vdv_docdate)
		when coalesce(t0.mac_base_type,0) = 5 then get_tax_currency(t3.dvf_currency,t3.dvf_docdate)
		when coalesce(t0.mac_base_type,0) = 6 then get_tax_currency(t10.vnc_currency,t10.vnc_docdate)
		when coalesce(t0.mac_base_type,0) = 7 then get_tax_currency(t11.vnd_currency,t11.vnd_docdate)
		when coalesce(t0.mac_base_type,0) = 8 then get_tax_currency(t5.isi_currency,t5.isi_docdate)
		when coalesce(t0.mac_base_type,0) = 9 then get_tax_currency(t6.iei_currency,t6.iei_docdate)
		when coalesce(t0.mac_base_type,0) = 13 then get_tax_currency(t12.cec_currency,t12.cec_docdate)
		when coalesce(t0.mac_base_type,0) = 14 then get_tax_currency(t13.cdc_currency,t13.cdc_docdate)
		when coalesce(t0.mac_base_type,0) = 15 then get_tax_currency(t7.cfc_currency,t7.cfc_docdate)
		when coalesce(t0.mac_base_type,0) = 16 then get_tax_currency(t14.cnc_currency,t14.cnc_docdate)
		when coalesce(t0.mac_base_type,0) = 17 then get_tax_currency(t15.cnd_currency,t15.cnd_docdate)
		when coalesce(t0.mac_base_type,0) = 18 then get_tax_currency(get_localcur(),t0.mac_doc_date)
		when coalesce(t0.mac_base_type,0) = 19 then get_tax_currency(t8.bpe_currency,t8.bpe_docdate)
		when coalesce(t0.mac_base_type,0) = 20 then get_tax_currency(t9.bpr_currency,t9.bpr_docdate)
		when coalesce(t0.mac_base_type,0) = 22 then get_tax_currency(t17.crc_currency,t17.crc_docdate)
		when coalesce(t0.mac_base_type,0) = 26 then get_tax_currency(t18.iri_currency,t18.iri_docdate)
		when coalesce(t0.mac_base_type,0) = 37 then get_tax_currency(t19.blg_currency,t19.blg_docdate)
		when coalesce(t0.mac_base_type,0) = 46 then get_tax_currency(t7.cfc_currency,t7.cfc_docdate)
		when coalesce(t0.mac_base_type,0) = 27 then get_tax_currency(get_localcur(),t0.mac_doc_date)
		end  as tsa_value
		from tmac t0
		LEFT JOIN dvem t1 ON t0.mac_base_entry = t1.vem_docentry AND t0.mac_base_type= t1.vem_doctype
		LEFT JOIN dvdv t2 ON t0.mac_base_entry = t2.vdv_docentry AND t0.mac_base_type= t2.vdv_doctype
		LEFT JOIN dvfv t3 ON t0.mac_base_entry = t3.dvf_docentry AND t0.mac_base_type= t3.dvf_doctype
		LEFT JOIN misi t5 ON t0.mac_base_entry = t5.isi_docentry AND t0.mac_base_type= t5.isi_doctype
		LEFT JOIN miei t6 ON t0.mac_base_entry = t6.iei_docentry AND t0.mac_base_type= t6.iei_doctype
		LEFT JOIN dcfc t7 ON t0.mac_base_entry = t7.cfc_docentry AND t0.mac_base_type= t7.cfc_doctype
		LEFT JOIN gbpe t8 ON t0.mac_base_entry = t8.bpe_docentry AND t0.mac_base_type= t8.bpe_doctype
		LEFT JOIN gbpr t9 ON t0.mac_base_entry = t9.bpr_docentry AND t0.mac_base_type= t9.bpr_doctype
		LEFT JOIN dvnc t10 ON t0.mac_base_entry = t10.vnc_docentry AND t0.mac_base_type= t10.vnc_doctype
		LEFT JOIN dvnd t11 ON t0.mac_base_entry = t11.vnd_docentry AND t0.mac_base_type= t11.vnd_doctype
		LEFT JOIN dcec t12 ON t0.mac_base_entry = t12.cec_docentry AND t0.mac_base_type= t12.cec_doctype
		LEFT JOIN dcdc t13 ON t0.mac_base_entry = t13.cdc_docentry AND t0.mac_base_type= t13.cdc_doctype
		LEFT JOIN dcnc t14 ON t0.mac_base_entry = t14.cnc_docentry AND t0.mac_base_type= t14.cnc_doctype
		LEFT JOIN dcnd t15 ON t0.mac_base_entry = t15.cnd_docentry AND t0.mac_base_type= t15.cnd_doctype
		LEFT JOIN dmdt ON dmdt.mdt_doctype = t0.mac_base_type
		LEFT JOIN tasa t16 ON t0.mac_doc_date = t16.tsa_date and t0.mac_currency = t16.tsa_currd
		LEFT JOIN dcrc t17 ON t0.mac_base_entry = t17.crc_docentry AND t0.mac_base_type= t17.crc_doctype
		LEFT JOIN diri t18 ON t0.mac_base_entry = t18.iri_docentry AND t0.mac_base_type= t18.iri_doctype
		LEFT JOIN tblg t19 ON t0.mac_base_entry = t19.blg_docentry AND t0.mac_base_type= t19.blg_doctype
		LEFT JOIN tbrp t20 ON t0.mac_base_entry = t20.brp_docentry AND t0.mac_base_type= t20.brp_doctype
		WHERE t0.mac_base_type = :mac_base_type
		AND t0.mac_base_entry = :mac_base_entry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':mac_base_type' => $Data['mac_base_type'], ':mac_base_entry' => $Data['mac_base_entry']));

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
				'mensaje'	=> 'El documento en cuestión no incluye un registro o asiento contable'
			);
		}

		$this->response($respuesta);
	}

	public function getAccentByTransID_post()
	{
		$Data = $this->post();

		if (!isset($Data['mac_trans_id'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT distinct t0.*,
		dmdt.mdt_docname as origen,
		case
		when coalesce(t0.mac_base_type,0) = 3 then t1.vem_currency
		when coalesce(t0.mac_base_type,0) = 4 then t2.vdv_currency
		when coalesce(t0.mac_base_type,0) = 5 then t3.dvf_currency
		when coalesce(t0.mac_base_type,0) = 6 then t10.vnc_currency
		when coalesce(t0.mac_base_type,0) = 7 then t11.vnd_currency
		when coalesce(t0.mac_base_type,0) = 8 then t5.isi_currency
		when coalesce(t0.mac_base_type,0) = 9 then t6.iei_currency
		when coalesce(t0.mac_base_type,0) = 13 then t12.cec_currency
		when coalesce(t0.mac_base_type,0) = 14 then t13.cdc_currency
		when coalesce(t0.mac_base_type,0) = 15 then t7.cfc_currency
		when coalesce(t0.mac_base_type,0) = 16 then t14.cnc_currency
		when coalesce(t0.mac_base_type,0) = 17 then t15.cnd_currency
		when coalesce(t0.mac_base_type,0) = 18 then get_localcur()
		when coalesce(t0.mac_base_type,0) = 19 then t8.bpe_currency
		when coalesce(t0.mac_base_type,0) = 20 then t9.bpr_currency
		when coalesce(t0.mac_base_type,0) = 22 then t17.crc_currency
		when coalesce(t0.mac_base_type,0) = 26 then t18.iri_currency
		when coalesce(t0.mac_base_type,0) = 37 then t19.blg_currency
		when coalesce(t0.mac_base_type,0) = 46 then t7.cfc_currency
		end  as currency,
		case
		when coalesce(t0.mac_base_type,0) = 3 then get_tax_currency(t1.vem_currency,t1.vem_docdate)
		when coalesce(t0.mac_base_type,0) = 4 then get_tax_currency(t2.vdv_currency,t2.vdv_docdate)
		when coalesce(t0.mac_base_type,0) = 5 then get_tax_currency(t3.dvf_currency,t3.dvf_docdate)
		when coalesce(t0.mac_base_type,0) = 6 then get_tax_currency(t10.vnc_currency,t10.vnc_docdate)
		when coalesce(t0.mac_base_type,0) = 7 then get_tax_currency(t11.vnd_currency,t11.vnd_docdate)
		when coalesce(t0.mac_base_type,0) = 8 then get_tax_currency(t5.isi_currency,t5.isi_docdate)
		when coalesce(t0.mac_base_type,0) = 9 then get_tax_currency(t6.iei_currency,t6.iei_docdate)
		when coalesce(t0.mac_base_type,0) = 13 then get_tax_currency(t12.cec_currency,t12.cec_docdate)
		when coalesce(t0.mac_base_type,0) = 14 then get_tax_currency(t13.cdc_currency,t13.cdc_docdate)
		when coalesce(t0.mac_base_type,0) = 15 then get_tax_currency(t7.cfc_currency,t7.cfc_docdate)
		when coalesce(t0.mac_base_type,0) = 16 then get_tax_currency(t14.cnc_currency,t14.cnc_docdate)
		when coalesce(t0.mac_base_type,0) = 17 then get_tax_currency(t15.cnd_currency,t15.cnd_docdate)
		when coalesce(t0.mac_base_type,0) = 18 then get_tax_currency(get_localcur(),t0.mac_doc_date)
		when coalesce(t0.mac_base_type,0) = 19 then get_tax_currency(t8.bpe_currency,t8.bpe_docdate)
		when coalesce(t0.mac_base_type,0) = 20 then get_tax_currency(t9.bpr_currency,t9.bpr_docdate)
		when coalesce(t0.mac_base_type,0) = 22 then get_tax_currency(t17.crc_currency,t17.crc_docdate)
		when coalesce(t0.mac_base_type,0) = 26 then get_tax_currency(t18.iri_currency,t18.iri_docdate)
		when coalesce(t0.mac_base_type,0) = 37 then get_tax_currency(t19.blg_currency,t19.blg_docdate)
		when coalesce(t0.mac_base_type,0) = 46 then get_tax_currency(t7.cfc_currency,t7.cfc_docdate)
		end  as tsa_value
		from tmac t0
		LEFT JOIN dvem t1 ON t0.mac_base_entry = t1.vem_docentry AND t0.mac_base_type= t1.vem_doctype
		LEFT JOIN dvdv t2 ON t0.mac_base_entry = t2.vdv_docentry AND t0.mac_base_type= t2.vdv_doctype
		LEFT JOIN dvfv t3 ON t0.mac_base_entry = t3.dvf_docentry AND t0.mac_base_type= t3.dvf_doctype
		LEFT JOIN misi t5 ON t0.mac_base_entry = t5.isi_docentry AND t0.mac_base_type= t5.isi_doctype
		LEFT JOIN miei t6 ON t0.mac_base_entry = t6.iei_docentry AND t0.mac_base_type= t6.iei_doctype
		LEFT JOIN dcfc t7 ON t0.mac_base_entry = t7.cfc_docentry AND t0.mac_base_type= t7.cfc_doctype
		LEFT JOIN gbpe t8 ON t0.mac_base_entry = t8.bpe_docentry AND t0.mac_base_type= t8.bpe_doctype
		LEFT JOIN gbpr t9 ON t0.mac_base_entry = t9.bpr_docentry AND t0.mac_base_type= t9.bpr_doctype
		LEFT JOIN dvnc t10 ON t0.mac_base_entry = t10.vnc_docentry AND t0.mac_base_type= t10.vnc_doctype
		LEFT JOIN dvnd t11 ON t0.mac_base_entry = t11.vnd_docentry AND t0.mac_base_type= t11.vnd_doctype
		LEFT JOIN dcec t12 ON t0.mac_base_entry = t12.cec_docentry AND t0.mac_base_type= t12.cec_doctype
		LEFT JOIN dcdc t13 ON t0.mac_base_entry = t13.cdc_docentry AND t0.mac_base_type= t13.cdc_doctype
		LEFT JOIN dcnc t14 ON t0.mac_base_entry = t14.cnc_docentry AND t0.mac_base_type= t14.cnc_doctype
		LEFT JOIN dcnd t15 ON t0.mac_base_entry = t15.cnd_docentry AND t0.mac_base_type= t15.cnd_doctype
		LEFT JOIN dmdt ON dmdt.mdt_doctype = t0.mac_base_type
		LEFT JOIN tasa t16 ON t0.mac_doc_date = t16.tsa_date and t0.mac_currency = t16.tsa_currd
		LEFT JOIN dcrc t17 ON t0.mac_base_entry = t17.crc_docentry AND t0.mac_base_type= t17.crc_doctype
		LEFT JOIN diri t18 ON t0.mac_base_entry = t18.iri_docentry AND t0.mac_base_type= t18.iri_doctype
		LEFT JOIN tblg t19 ON t0.mac_base_entry = t19.blg_docentry AND t0.mac_base_type= t19.blg_doctype
		WHERE t0.mac_trans_id = :mac_trans_id";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':mac_trans_id' => $Data['mac_trans_id']));

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
				'mensaje'	=> 'El documento en cuestión no incluye un registro o asiento contable'
			);
		}

		$this->response($respuesta);
	}


	private function validateDate($fecha)
	{
		if (strlen($fecha) == 10 or strlen($fecha) > 10) {
			return true;
		} else {
			return false;
		}
	}

	//FUNCION PARA ACTUALIZAR COMENTARIO
	public function updateComments_post()
	{
		$Data = $this->post();
		$respuesta = array();

		// SE INSTANCIA LA CLASE GENERIC PARA VALIDAR EL PERIODO CONTABLE
		$generic = new Generic();

		if (
			!isset($Data['mac_trans_id']) or
			!isset($Data['mac_doc_date']) or
			!isset($Data['mac_doc_duedate']) or
			!isset($Data['mac_comments'])
		) {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		// SE VERIFICA QUE EL PERIODO CONTABLE ESTE ACTIVO
		$periodo = $generic->ValidatePeriod($Data['mac_doc_date'], $Data['mac_doc_date'], $Data['mac_doc_duedate'], 1);

		if ($periodo['error']) {
			$this->response($periodo, REST_Controller::HTTP_BAD_REQUEST);
			return;
		}

		// SE VERIFICA QUE EL DOCUMENTO ESTE ABIERTO
		$sqlSelect = " SELECT * from tmac where mac_trans_id  = :mac_trans_id AND mac_status = 1";
		$resSelect = $this->pedeo->queryTable($sqlSelect, array(
			":mac_trans_id" => $Data['mac_trans_id']
		));

		if (isset($resSelect[0])) {
			// SE ACTUALIZA EN CASO DE QUE ESTE ABIERTO
			$sqlUpdate = "UPDATE tmac SET mac_comments = :mac_comments WHERE mac_trans_id = :mac_trans_id";
			$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
				":mac_trans_id" => $Data['mac_trans_id'],
				":mac_comments" => $Data['mac_comments']
			));

			if (is_numeric($resUpdate) && $resUpdate > 0) {
				$respuesta = array(
					'error' => false,
					'data' => $resUpdate,
					'mensaje' => 'Asiento contable modificado con exito'
				);
			} else {
				$respuesta = array(
					'error' => true,
					'data' => $resUpdate,
					'mensaje' => 'No se pudo realizar la operacion'
				);
			}
		} else {
			$respuesta = array(
				'error' => true,
				'data' => $resSelect,
				'mensaje' => 'El Documento esta cerrado'
			);
		}

		$this->response($respuesta);
	}

	// FUNCION PARA ANULAR UN ASIENTO CONTABLE MANUAL
	public function cancelAccountingEntry_post() {

		$Data = $this->post();
		
		if ( !isset( $Data['mac_trans_id'] ) OR !isset( $Data['mac_comments'] ) ) {
			
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlAsiento = "SELECT * FROM tmac WHERE mac_trans_id = :mac_trans_id ";

		$resAsiento = $this->pedeo->queryTable($sqlAsiento, array(
			":mac_trans_id" => $Data['mac_trans_id']
		));


		if ( isset($resAsiento[0]) ){

			if ( $resAsiento[0]['mac_base_type'] == 18 ){

				$sqlDetalleAsiento = "SELECT * FROM mac1 WHERE ac1_trans_id = :ac1_trans_id ";

				$resDetalleAsiento = $this->pedeo->queryTable($sqlDetalleAsiento, array(
					":ac1_trans_id" => $Data['mac_trans_id']
				));

				if ( isset( $resDetalleAsiento[0] ) ) {

					// SE VERIFICA QUE LAS LINEAS DEL ASIENTO NO SE UTILIZARON EN LOS PROCESOS DE PAGO O RECONCILIACION
					
					foreach ($resDetalleAsiento as $key => $value) {

						// VALIDANDO SI SE USO EN UN PAGO RECIBIDO
						$sqlPr = "SELECT bpr1.pr1_docnum, bpr_docnum, case when responsestatus.estado = 'Anulado' then 1 else 0 end as estado
								FROM bpr1 
								INNER JOIN gbpr ON pr1_docnum = bpr_docentry
								INNER JOIN responsestatus ON responsestatus.id = pr1_docnum  AND  responsestatus.tipo = 20
								WHERE pr1_line_num = :pr1_line_num";

						$resPr = $this->pedeo->queryTable($sqlPr, array(":pr1_line_num" => $value['ac1_line_num']));

						if ( isset($resPr[0]) && $resPr[0]['estado'] == 0 ){

							$respuesta = array(
							'error'   => true,
							'data'    => [],
							'mensaje' => 'Revisando operaciones anteriores, se encontró que en el Pago recibido # '. $resPr[0]['bpr_docnum'] . 
										" ha sido utilizado parcial o total el asiento que intenta anular. Es necesario que anule los documentos anteriores donde esta involucrado el asiento actual si desea anular dicho asiento."
							);

							return $this->response($respuesta);
						}

						//VALIDANDO SI SE USO EN UN PAGO EFECTUADO
						$sqlPe = "SELECT bpe1.pe1_docnum, bpe_docnum, case when responsestatus.estado = 'Anulado' then 1 else 0 end as estado
								FROM bpe1 
								INNER JOIN gbpe ON pe1_docnum = bpe_docentry
								INNER JOIN responsestatus ON responsestatus.id = pe1_docnum  AND  responsestatus.tipo = 19
								WHERE pe1_line_num = :pe1_line_num";

						$resPe = $this->pedeo->queryTable($sqlPe, array(":pe1_line_num" => $value['ac1_line_num']));
						
						if ( isset($resPe[0]) && $resPe[0]['estado'] == 0 ){

							$respuesta = array(
							'error'   => true,
							'data'    => [],
							'mensaje' => 'Revisando operaciones anteriores, se encontró que en el Pago efectuado # '. $resPe[0]['bpe_docnum'] . 
										" ha sido utilizado parcial o total el asiento que intenta anular. Es necesario que anule los documentos anteriores donde esta involucrado el asiento actual si desea anular dicho asiento."
							);

							return $this->response($respuesta);
						}

						$sqlRc = "SELECT crc1.rc1_docentry, crc_docnum, case when responsestatus.estado = 'Anulado' then 1 else 0 end as estado
								FROM crc1 
								INNER JOIN dcrc ON rc1_docentry = dcrc.crc_docentry 
								INNER JOIN responsestatus ON responsestatus.id = rc1_docentry  AND  responsestatus.tipo = 22
								WHERE rc1_line_num = :rc1_line_num";

						$resRc = $this->pedeo->queryTable($sqlRc, array(":rc1_line_num" => $value['ac1_line_num']));

						if ( isset($resRc[0]) && $resRc[0]['estado'] == 0 ){

							$respuesta = array(
							'error'   => true,
							'data'    => [],
							'mensaje' => 'Revisando operaciones anteriores, se encontró que en la Reconciliación # '. $resRc[0]['crc_docnum'] . 
										" ha sido utilizado parcial o total el asiento que intenta anular. Es necesario que anule los documentos anteriores donde esta involucrado el asiento actual si desea anular dicho asiento."
							);

							return $this->response($respuesta);
						}
						
					}
					//

					$this->pedeo->trans_begin();

					// BUSCANDO LA NUMERACION DEL DOCUMENTO
					$DocNumVerificado = $this->documentnumbering->NumberDoc($resAsiento[0]['mac_serie'],$resAsiento[0]['mac_doc_date'],$resAsiento[0]['mac_doc_duedate']);

					if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0){
				
					}else if ($DocNumVerificado['error']){
				
						return $this->response($DocNumVerificado, REST_Controller::HTTP_BAD_REQUEST);
					}

					$sqlInsertHeader = "INSERT INTO tmac( mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_update_date, mac_series, mac_made_usuer, mac_update_user, mac_currency, mac_doctype, business, branch, mac_accperiod)
										VALUES(:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_update_date, :mac_series, :mac_made_usuer, :mac_update_user, :mac_currency, :mac_doctype, :business, :branch, :mac_accperiod)";
					
					$resSqlInsertHeader = $this->pedeo->insertRow($sqlInsertHeader, array(
						'mac_doc_num' => $DocNumVerificado,
						':mac_status' => 2, 
						':mac_base_type' => $resAsiento[0]['mac_base_type'], 
						':mac_base_entry' => $resAsiento[0]['mac_base_entry'], 
						':mac_doc_date' => $resAsiento[0]['mac_doc_date'], 
						':mac_doc_duedate' => $resAsiento[0]['mac_doc_duedate'], 
						':mac_legal_date' => $resAsiento[0]['mac_legal_date'], 
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
						':mac_comments' => $resAsiento[0]['mac_comments'], 
						':mac_create_date' => $resAsiento[0]['mac_create_date'], 
						':mac_update_date' => $resAsiento[0]['mac_update_date'], 
						':mac_series' => $resAsiento[0]['mac_series'], 
						':mac_made_usuer' => $resAsiento[0]['mac_made_usuer'], 
						':mac_update_user' => $resAsiento[0]['mac_update_user'], 
						':mac_currency' => $resAsiento[0]['mac_currency'], 
						':mac_doctype' => $resAsiento[0]['mac_doctype'], 
						':business' => $resAsiento[0]['business'], 
						':branch' => $resAsiento[0]['branch'], 
						':mac_accperiod' => $resAsiento[0]['mac_accperiod']
					));


					if ( is_numeric($resSqlInsertHeader) && $resSqlInsertHeader > 0 ){

						// Se actualiza la serie de la numeracion del documento

						$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
													WHERE pgs_id = :pgs_id";

						$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
							':pgs_nextnum' => $DocNumVerificado,
							':pgs_id'      => $resAsiento[0]['mac_serie']
						));

						if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {

						} else {
							
							$this->pedeo->trans_rollback();

							$respuesta = array(
							'error'   => true,
							'data'    => $resActualizarNumeracion,
							'mensaje'	=> 'No se pudo crear el asiento'
							);

							return $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
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

							
							$resInsertDetalle = $this->pedeo->insertRow($sqlInsertDetalle, array(

								':ac1_trans_id' => $resSqlInsertHeader,
								':ac1_account' => $detalle['ac1_account'], 
								':ac1_debit' => $debito, 
								':ac1_credit' => $credito, 
								':ac1_debit_sys' => $debitosys, 
								':ac1_credit_sys' => $creditosys, 
								':ac1_currex' => $detalle['ac1_currex'], 
								':ac1_doc_date' => $detalle['ac1_doc_date'], 
								':ac1_doc_duedate' => $detalle['ac1_doc_duedate'], 
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
								':ac1_accperiod' => $detalle['ac1_accperiod'],
								':ac1_close' => $detalle['ac1_close'], 
								':ac1_cord' => $detalle['ac1_cord'], 
								':ac1_ven_debit' => $vendebito, 
								':ac1_ven_credit' => $vencredito, 
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

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error' => true,
									'data' => $resSqlInsertHeader,
									'mensaje' => 'Error al insertar la copia del detalle del asiento'
								);

								return $this->response($respuesta);
							}


							// ACTUALIZAR VEN DEBIT Y CREDIT DEL ASIENTO VIEJO

							$sqlUpdate = "UPDATE mac1 SET ac1_ven_debit = :ac1_ven_debit, ac1_ven_credit = :ac1_ven_credit WHERE ac1_line_num = :ac1_line_num";

							$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
								':ac1_ven_credit' => $oldVen,
								':ac1_ven_debit'  => $oldVen,
								':ac1_line_num'   => $detalle['ac1_line_num']

							));


							if (is_numeric($resUpdate) && $resUpdate == 1 ) {

							
							}else{

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error' => true,
									'data' => $resUpdate,
									'mensaje' => 'Error al actualizar el asiento viejo'
								);

								return $this->response($respuesta);
							}
						}

						$update = $this->pedeo->updateRow("UPDATE tmac SET mac_status = :mac_status, mac_comments = concat(mac_comments,' ', '".$Data['mac_comments']."') WHERE mac_trans_id = :mac_trans_id", array("mac_status" => 2, ":mac_trans_id" => $Data['mac_trans_id'] ));

						if ( is_numeric($update) && $update == 1 ){

						}else{

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error' => true,
								'data' => $update,
								'mensaje' => 'No se pudo cambiar el estado del documento'
							);
	
							return $this->response($respuesta);
						}

						$this->pedeo->trans_commit();

						$respuesta = array(
							'error' => false,
							'data' => [],
							'mensaje' => 'Asiento anulado con exito'
						);



					}else{

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error' => true,
							'data' => $resSqlInsertHeader,
							'mensaje' => 'Error al insertar la copia del asiento'
						);

						return $this->response($respuesta);
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

		$this->response($respuesta);
	}

	// LLENAR BALANCE DIARIO
	public function addBalanceDaily_post() {

		$sqlSelect = "SELECT * FROM mac1 where sync = 0 LIMIT 1000";

		$ron_ID = 0;

		$respuesta = array(
			"error" => false,
			"data"  => [],
			"mensaje" => "Proceso finalizado"
		);

		// COMPROBANDO ESTADO DE EJECUCION
		$estadoCron = $this->pedeo->queryTable("SELECT * FROM cron WHERE ron_code = 'BALANCECONTABLEDIARIO' ");
		if (isset($estadoCron[0])) {

			$ron_ID = $estadoCron[0]['ron_id'];

			if ($estadoCron[0]['ron_status'] == 1) {

				$respuesta = array(
					"error" => false,
					"data"  => [],
					"mensaje" => "Proceso en ejecución"
				);

				return $this->response($respuesta);

			} else {

				$resUpdateEstadoCron = $this->pedeo->updateRow(" UPDATE cron set ron_status = :ron_status, ron_date = :ron_date WHERE ron_id = :ron_id", array(
					':ron_status' => 1,
					':ron_date' => date('Y-m-d'),
					':ron_id'=> $estadoCron[0]['ron_id']
				));

				if ( is_numeric($resUpdateEstadoCron) && $resUpdateEstadoCron == 1){
				}else{
					$respuesta = array(
						"error" => true,
						"data"  => $resUpdateEstadoCron,
						"mensaje" => "No se pudo actualizar el estado del proceso"
					);
	
					return $this->response($respuesta);
				}
			}

		} else {

			$resInsertEstadoCron = $this->pedeo->insertRow(" INSERT INTO cron(ron_code, ron_status, ron_date)VALUES(:ron_code, :ron_status, :ron_date)", array(
				':ron_code'=> 'BALANCECONTABLEDIARIO',
				':ron_status'=> 1,
				':ron_date'=> date('Y-m-d')
			));

			if ( is_numeric($resInsertEstadoCron) && $resInsertEstadoCron > 0){
			} else {
				$respuesta = array(
					"error" => true,
					"data"  => $resInsertEstadoCron,
					"mensaje" => "No se pudo crear el estado del proceso"
				);

				return $this->response($respuesta);
			}

			$ron_ID = $resInsertEstadoCron;
		}
		//

		$resCuentas = $this->pedeo->queryTable($sqlSelect, array());

		$this->pedeo->trans_begin();

		foreach ($resCuentas as $key => $cuenta) {


			$sqlDoc = "SELECT * FROM cdoc WHERE 1=1 AND doc_account = :doc_account AND doc_date = :doc_date  AND business = :business";

			$arra =  array (
				':doc_account' => $cuenta['ac1_account'],
				':doc_date' => $cuenta['ac1_doc_date'],
				':business' => $cuenta['business']
			);

			if (!empty($cuenta['ac1_prc_code']) ) {

				$sqlDoc .= "  AND dco_prc_code = :dco_prc_code";
				$arra[':dco_prc_code'] = $cuenta['ac1_prc_code'];
			}

			if (!empty($cuenta['ac1_uncode']) ) {

				$sqlDoc .= "  AND dco_uncode = :dco_uncode";
				$arra[':dco_uncode'] = $cuenta['ac1_uncode'];

			}

			if (!empty($cuenta['ac1_prj_code']) ) {

				$sqlDoc .= "  AND dco_prj_code = :dco_prj_code";
				$arra[':dco_prj_code'] = $cuenta['ac1_prj_code'];

			}
			

			$resDoc = $this->pedeo->queryTable($sqlDoc, $arra);

			$sqlNivel = "SELECT acc_l1, acc_l2, acc_l3, acc_l4, acc_l5 FROM dacc WHERE acc_code = :acc_code";

			$resNivel = $this->pedeo->queryTable($sqlNivel, array(":acc_code" => $cuenta['ac1_account']));

			//

			$cuentasUsadas = [];

			

			if (isset($resNivel[0])) {

				$niveles = [];

				array_push($niveles, $resNivel[0]['acc_l1']);
				array_push($niveles, $resNivel[0]['acc_l2']);
				array_push($niveles, $resNivel[0]['acc_l3']);
				array_push($niveles, $resNivel[0]['acc_l4']);
				array_push($niveles, $resNivel[0]['acc_l5']);
				// array_push($niveles, $resNivel[0]['acc_l6']);

				for ($i=0; $i < count($niveles); $i++) { 


					if (!in_array($niveles[$i], $cuentasUsadas)){

						array_push($cuentasUsadas, $niveles[$i]);

						$array =  array(
							':doc_account' => $niveles[$i],
							':doc_date' => $cuenta['ac1_doc_date'],
							':business' => $cuenta['business']
						);

						$sqlDocN = "SELECT * FROM cdoc WHERE 1=1 AND doc_account = :doc_account AND doc_date = :doc_date  AND business = :business";
						
						
						if (!empty($cuenta['ac1_prc_code']) ) {

							$sqlDocN .= "  AND dco_prc_code = :dco_prc_code";
							$array[':dco_prc_code'] = $cuenta['ac1_prc_code'];
						}

						if (!empty($cuenta['ac1_uncode']) ) {

							$sqlDocN .= "  AND dco_uncode = :dco_uncode";
							$array[':dco_uncode'] = $cuenta['ac1_uncode'];

						}

						if (!empty($cuenta['ac1_prj_code']) ) {

							$sqlDocN .= "  AND dco_prj_code = :dco_prj_code";
							$array[':dco_prj_code'] = $cuenta['ac1_prj_code'];

						}

						
						$resDocN = $this->pedeo->queryTable($sqlDocN, $array);
						
						//
						if (isset($resDocN[0])){

							$sqlUpdate = "UPDATE cdoc SET doc_debit = doc_debit + :doc_debit, doc_credit = doc_credit + :doc_credit WHERE doc_id = :doc_id";

							$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
								':doc_debit' => $cuenta['ac1_debit'],
								':doc_credit' => $cuenta['ac1_credit'],
								':doc_id' => $resDocN[0]['doc_id']
							));


							if (is_numeric($resUpdate) && $resUpdate == 1){
							} else {

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error' => true,
									'data' => $resUpdate,
									'mensaje' => 'No se pudo actualizar la cuenta'
								);

								return $this->response($respuesta);
							}

						}else{

							$sqlInsert = " INSERT INTO cdoc(doc_account, doc_debit, doc_credit, doc_date, business, branch, dco_prc_code, dco_uncode, dco_prj_code)
										VALUES(:doc_account, :doc_debit, :doc_credit, :doc_date, :business, :branch, :dco_prc_code, :dco_uncode, :dco_prj_code)";

							$resInsert = $this->pedeo->InsertRow($sqlInsert, array(
								
								':doc_account' => $niveles[$i], 
								':doc_debit' => $cuenta['ac1_debit'], 
								':doc_credit' => $cuenta['ac1_credit'], 
								':doc_date' => $cuenta['ac1_doc_date'], 
								':business' => $cuenta['business'], 
								':branch' => $cuenta['branch'],
								':dco_prc_code' => $cuenta['ac1_prc_code'], 
								':dco_uncode' => $cuenta['ac1_uncode'], 
								':dco_prj_code' => $cuenta['ac1_prj_code']
							));


							if (is_numeric($resInsert) && $resInsert > 0){
							} else {

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error' => true,
									'data' => $resInsert,
									'mensaje' => 'No se pudo insertar la cuenta'
								);

								return $this->response($respuesta);
							}

						}
					}


				}

			} 

			if (!in_array($cuenta['ac1_account'], $cuentasUsadas)) {

				array_push($cuentasUsadas, $cuenta['ac1_account']);
				
				if (isset($resDoc[0])){

					$sqlUpdate = "UPDATE cdoc SET doc_debit = doc_debit + :doc_debit, doc_credit = doc_credit + :doc_credit WHERE doc_id = :doc_id";
	
					$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
						':doc_debit' => $cuenta['ac1_debit'],
						':doc_credit' => $cuenta['ac1_credit'],
						':doc_id' => $resDoc[0]['doc_id']
					));

					if (is_numeric($resUpdate) && $resUpdate == 1){
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error' => true,
							'data' => $resUpdate,
							'mensaje' => 'No se pudo actualizar la cuenta'
						);

						return $this->response($respuesta);
					}
	
				} else {


					$sqlInsert = " INSERT INTO cdoc(doc_account, doc_debit, doc_credit, doc_date, business, branch, dco_prc_code, dco_uncode, dco_prj_code)
								VALUES(:doc_account, :doc_debit, :doc_credit, :doc_date, :business, :branch, :dco_prc_code, :dco_uncode, :dco_prj_code)";

					$resInsert = $this->pedeo->InsertRow($sqlInsert, array(
						
						':doc_account' => $cuenta['ac1_account'], 
						':doc_debit' => $cuenta['ac1_debit'], 
						':doc_credit' => $cuenta['ac1_credit'], 
						':doc_date' => $cuenta['ac1_doc_date'], 
						':business' => $cuenta['business'], 
						':branch' => $cuenta['branch'],
						':dco_prc_code' => $cuenta['ac1_prc_code'], 
						':dco_uncode' => $cuenta['ac1_uncode'], 
						':dco_prj_code' => $cuenta['ac1_prj_code']
					));

					if (is_numeric($resInsert) && $resInsert > 0){
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error' => true,
							'data' => $resInsert,
							'mensaje' => 'No se pudo insertar la cuenta'
						);

						return $this->response($respuesta);
					}
	
				}
			}



			$updateSync = "UPDATE mac1 set sync = 1 WHERE ac1_line_num = :ac1_line_num";
			$resUpdateSync = $this->pedeo->updateRow($updateSync, array(":ac1_line_num" => $cuenta['ac1_line_num']));

			if (is_numeric($resUpdateSync) && $resUpdateSync == 1){
			} else {

				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error' => true,
					'data' => $resUpdateSync,
					'mensaje' => 'No se pudo actualizar el campo sync'
				);

				return $this->response($respuesta);
			}
			
		}


		$resUpdateEstadoCron = $this->pedeo->updateRow(" UPDATE cron set ron_status = :ron_status, ron_date = :ron_date WHERE ron_id = :ron_id", array(
			':ron_status' => 0,
			':ron_date' => date('Y-m-d'),
			':ron_id'=> $ron_ID
		));

		if ( is_numeric($resUpdateEstadoCron) && $resUpdateEstadoCron == 1){
		}else{

			$this->pedeo->trans_rollback();

			$respuesta = array(
				"error" => true,
				"data"  => [],
				"mensaje" => "No se pudo actualizar el estado del proceso"
			);

			return $this->response($respuesta);
		}

		$this->pedeo->trans_commit();

		$this->response($respuesta);

	}

}
