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

		$sqlInsertDetail = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate, ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype, ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord, ac1_ven_debit, ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, ac1_card_type, business, branch)
						VALUES (:ac1_trans_id, :ac1_account, :ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys, :ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode, :ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct, :ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref, :ac1_card_type, :business, :branch)";


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
		//


		//BUSCANDO LA NUMERACION DEL DOCUMENTO
		$sqlNumeracion = " SELECT pgs_nextnum,pgs_last_num FROM  pgdn WHERE pgs_id = :pgs_id";

		$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['mac_serie']));

		if (isset($resNumeracion[0])) {

			$numeroActual = $resNumeracion[0]['pgs_nextnum'];
			$numeroFinal  = $resNumeracion[0]['pgs_last_num'];
			$numeroSiguiente = ($numeroActual + 1);

			if ($numeroSiguiente <= $numeroFinal) {

				$DocNumVerificado = $numeroSiguiente;
			} else {

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'La serie de la numeración esta llena'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
		} else {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro la serie de numeración para el documento'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}



		// Se Inicia la transaccion,
		// Todas las consultas de modificacion siguientes
		// aplicaran solo despues que se confirme la transaccion,
		// de lo contrario no se aplicaran los cambios y se devolvera
		// la base de datos a su estado original.

		$this->pedeo->trans_begin();

		$sqlInsert = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user, mac_currency, mac_doctype, business, branch)
                      VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user, :mac_currency, :mac_doctype, :business, :branch)";


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
			':branch' => $Data['branch']
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
				$ValidateDmun = $this->pedeo->queryTable("SELECT * FROM dmun WHERE trim(dun_un_code)  = :dun_un_code", array(':dun_un_code' => trim($detail['ac1_uncode'])));
				$ValidateDmpj = $this->pedeo->queryTable("SELECT * FROM dmpj WHERE trim(dpj_pj_code)  = :dpj_pj_code", array(':dpj_pj_code' => trim($detail['ac1_prj_code'])));
				$ValidateDmcc = $this->pedeo->queryTable("SELECT * FROM dmcc WHERE trim(dcc_prc_code) = :dcc_prc_code", array(':dcc_prc_code' => trim($detail['ac1_prc_code'])));
				$ValidateSn   = $this->pedeo->queryTable("SELECT * FROM dmsn WHERE trim(dms_card_code) = :dms_card_code", array(':dms_card_code' => trim($detail['ac1_legal_num'])));

				if (!isset($ValidateDmun[0])) {
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'    => $ValidateDmun,
						'mensaje'	=> 'No existe la unidad de negocio ' . $detail['ac1_uncode']
					);

					$this->response($respuesta);

					return;
				}

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
					':ac1_accperiod' => is_numeric($detail['ac1_accperiod']) ? $detail['ac1_accperiod'] : NULL,
					':ac1_close' => is_numeric($detail['ac1_close']) ? $detail['ac1_close'] : 0,
					':ac1_cord' => is_numeric($detail['ac1_cord']) ? $detail['ac1_cord'] : 0,
					':ac1_ven_debit' => is_numeric($detail['ac1_debit']) ? round($detail['ac1_debit'], $DECI_MALES) : 0,
					':ac1_ven_credit' => is_numeric($detail['ac1_credit']) ? round($detail['ac1_credit'], $DECI_MALES) : 0,
					':ac1_fiscal_acct' => is_numeric($detail['ac1_fiscal_acct']) ? $detail['ac1_fiscal_acct'] : 0,
					':ac1_taxid' => is_numeric($detail['ac1_taxid']) ? $detail['ac1_taxid'] : 0,
					':ac1_isrti' => is_numeric($detail['ac1_isrti']) ? $detail['ac1_isrti'] : 0,
					':ac1_basert' => is_numeric($detail['ac1_basert']) ? $detail['ac1_basert'] : 0,
					':ac1_mmcode' => is_numeric($detail['ac1_mmcode']) ? $detail['ac1_mmcode'] : 0,
					':ac1_legal_num' => isset($detail['ac1_legal_num']) ? $detail['ac1_legal_num'] : NULL,
					':ac1_codref' => is_numeric($detail['ac1_codref']) ? $detail['ac1_codref'] : 0,
					':ac1_card_type' => isset($detail['ac1_card_type']) ? $detail['ac1_card_type'] : 0,
					':business' => $detail['business'],
					':branch'   => $detail['branch']
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
					':ac1_legal_num' => NULL,
					':ac1_codref' => 1,
					':ac1_card_type' => 0,
					':business' => $detail['business'],
					':branch'   => $detail['branch']
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
						'mensaje'	=> 'No se pudo registrar la conciliación de bancos, occurio un error al insertar el detalle del asiento diferencia en cambio'
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
					':ac1_legal_num' => NULL,
					':ac1_codref' => 1,
					':ac1_card_type' => 0,
					':business' => $detail['business'],
					':branch'   => $detail['branch']

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
						'mensaje'	=> 'No se pudo registrar la conciliación de bancos, occurio un error al insertar el detalle del asiento diferencia en cambio'
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
			$validateCont = $this->generic->validateAccountingAccent($resInsert);


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
		WHERE t0.business = :business AND t0.branch = :branch";

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
											and dacc.acc_enabled = 1
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
											and dacc.acc_enabled = 1
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
											and dacc.acc_enabled = 1
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
											and dacc.acc_enabled = 1
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
											and dacc.acc_enabled = 1
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
											and dacc.acc_enabled = 1
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
											and dacc.acc_enabled = 1
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
											and dacc.acc_enabled = 1
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
											and dacc.acc_enabled = 1
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
											and dacc.acc_enabled = 1
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
											and dacc.acc_enabled = 1
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
											and dacc.acc_enabled = 1
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
											and dacc.acc_enabled = 1
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
											and dacc.acc_enabled = 1
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
											and dacc.acc_enabled = 1
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
											and dacc.acc_enabled = 1
											--- REVALORIZACION DE INVERNTARIO
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
											and dacc.acc_enabled = 1";

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
		dmdt.mdt_docname as origen, tsa_value,
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
		end  as currency
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
		LEFT JOIN tasa t16 ON mac_doc_date = tsa_date
		LEFT JOIN dcrc t17 ON t0.mac_base_entry = t17.crc_docentry AND t0.mac_base_type= t17.crc_doctype
		LEFT JOIN diri t18 ON t0.mac_base_entry = t18.iri_docentry AND t0.mac_base_type= t18.iri_doctype
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
				'mensaje'	=> 'busqueda sin resultados'
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
}
