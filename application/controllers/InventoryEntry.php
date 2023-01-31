<?php
// ENTRADA DE INVENTARIO
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class InventoryEntry extends REST_Controller
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
		$this->load->library('account');
	}

	//CREAR NUEVA ENTRADA
	public function createInventoryEntry_post()
	{

		$DECI_MALES =  $this->generic->getDecimals();
		$Data = $this->post();
		$DocNumVerificado = 0;
		$DetalleCuentaLineaDocumento = new stdClass();
		$DetalleConsolidadoCuentaLineaDocumento = [];
		$inArrayCuentaLineaDocumento = array();
		$llaveCuentaLineaDocumento = "";
		$posicionCuentaLineaDocumento = 0;
		$DetalleCuentaGrupo = new stdClass();
		$DetalleConsolidadoCuentaGrupo = [];
		$inArrayCuentaGrupo = array();
		$llaveCuentaGrupo = "";
		$posicionCuentaGrupo = 0;
		$ManejaInvetario = 0;
		$ManejaLote = 0;
		$ManejaUbicacion = 0;
		$ManejaSerial = 0;
		// Se globaliza la variable sqlDetalleAsiento
		$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
													ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
													ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
													ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, business, branch)VALUES (:ac1_trans_id, :ac1_account,
													:ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
													:ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
													:ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
													:ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref, :business, :branch)";

		if (!isset($Data['detail']) OR !isset($Data['business']) OR !isset($Data['branch'])) {

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
				'mensaje' => 'No se encontro el detalle de la entrada'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}
		//
		//VALIDANDO PERIODO CONTABLE
		$periodo = $this->generic->ValidatePeriod($Data['iei_docdate'], $Data['iei_docdate'], $Data['iei_docdate'], 0);

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

		$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['iei_series']));

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

		//OBTENER CARPETA PRINCIPAL DEL PROYECTO
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

		//SE BUSCA LA TASA DE CAMBIO CON RESPECTO A LA MONEDA QUE TRAE EL DOCUMENTO A CREAR CON LA MONEDA LOCAL
		// Y EN LA MISMA FECHA QUE TRAE EL DOCUMENTO


		$sqlBusTasa = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
		$resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $Data['iei_currency'], ':tsa_date' => $Data['iei_docdate']));

		if (isset($resBusTasa[0])) {
		} else {

			if (trim($Data['iei_currency']) != $MONEDALOCAL) {

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'No se encrontro la tasa de cambio para la moneda: ' . $Data['iei_currency'] . ' en la actual fecha del documento: ' . $Data['iei_docdate'] . ' y la moneda local: ' . $resMonedaLoc[0]['pgm_symbol']
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
		}

		$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
		$resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $Data['iei_docdate']));

		if (isset($resBusTasa2[0])) {
		} else {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :' . $Data['iei_docdate']
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$TasaDocLoc = isset($resBusTasa[0]['tsa_value']) ? $resBusTasa[0]['tsa_value'] : 1;
		$TasaLocSys = $resBusTasa2[0]['tsa_value'];

		// FIN DEL PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO


		$sqlInsert = "INSERT INTO miei (iei_docnum, iei_docdate, iei_duedate, iei_duedev, iei_pricelist, iei_cardcode, iei_cardname, iei_contacid, iei_slpcode, iei_empid, iei_comment, iei_doctotal,
                      iei_taxtotal, iei_discprofit, iei_discount, iei_createat, iei_baseentry, iei_basetype, iei_doctype, iei_idadd, iei_adress, iei_paytype,
                      iei_baseamnt, iei_series, iei_createby, iei_currency, business)
                      VALUES
                      (:iei_docnum, :iei_docdate, :iei_duedate, :iei_duedev, :iei_pricelist, :iei_cardcode, :iei_cardname, :iei_contacid, :iei_slpcode, :iei_empid, :iei_comment, :iei_doctotal,
                      :iei_taxtotal, :iei_discprofit, :iei_discount, :iei_createat, :iei_baseentry, :iei_basetype, :iei_doctype, :iei_idadd, :iei_adress, :iei_paytype,
                      :iei_baseamnt, :iei_series, :iei_createby, :iei_currency, :business)";


		// Se Inicia la transaccion,
		// Todas las consultas de modificacion siguientes
		// aplicaran solo despues que se confirme la transaccion,
		// de lo contrario no se aplicaran los cambios y se devolvera
		// la base de datos a su estado original.

		$this->pedeo->trans_begin();

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(

			':iei_docnum' => $DocNumVerificado,
			':iei_docdate'  => $this->validateDate($Data['iei_docdate']) ? $Data['iei_docdate'] : NULL,
			':iei_duedate' => $this->validateDate($Data['iei_duedate']) ? $Data['iei_duedate'] : NULL,
			':iei_duedev' => $this->validateDate($Data['iei_duedev']) ? $Data['iei_duedev'] : NULL,
			':iei_pricelist' => is_numeric($Data['iei_pricelist']) ? $Data['iei_pricelist'] : 0,
			':iei_cardcode' => isset($Data['iei_cardcode']) ? $Data['iei_cardcode'] : NULL,
			':iei_cardname' => isset($Data['iei_cardname']) ? $Data['iei_cardname'] : NULL,
			':iei_contacid' => is_numeric($Data['iei_contacid']) ? $Data['iei_contacid'] : 0,
			':iei_slpcode' => is_numeric($Data['iei_slpcode']) ? $Data['iei_slpcode'] : 0,
			':iei_empid' => is_numeric($Data['iei_empid']) ? $Data['iei_empid'] : 0,
			':iei_comment' => isset($Data['iei_comment']) ? $Data['iei_comment'] : NULL,
			':iei_doctotal' => is_numeric($Data['iei_doctotal']) ? $Data['iei_doctotal'] : 0,
			':iei_baseamnt' => is_numeric($Data['iei_baseamnt']) ? $Data['iei_baseamnt'] : 0,
			':iei_taxtotal' => is_numeric($Data['iei_taxtotal']) ? $Data['iei_taxtotal'] : 0,
			':iei_discprofit' => is_numeric($Data['iei_discprofit']) ? $Data['iei_discprofit'] : 0,
			':iei_discount' => is_numeric($Data['iei_discount']) ? $Data['iei_discount'] : 0,
			':iei_createat' => $this->validateDate($Data['iei_createat']) ? $Data['iei_createat'] : NULL,
			':iei_baseentry' => is_numeric($Data['iei_baseentry']) ? $Data['iei_baseentry'] : 0,
			':iei_basetype' => is_numeric($Data['iei_basetype']) ? $Data['iei_basetype'] : 0,
			':iei_doctype' => is_numeric($Data['iei_doctype']) ? $Data['iei_doctype'] : 0,
			':iei_idadd' => isset($Data['iei_idadd']) ? $Data['iei_idadd'] : NULL,
			':iei_adress' => isset($Data['iei_adress']) ? $Data['iei_adress'] : NULL,
			':iei_paytype' => is_numeric($Data['iei_paytype']) ? $Data['iei_paytype'] : 0,
			':iei_series' => is_numeric($Data['iei_series']) ? $Data['iei_series'] : 0,
			':iei_createby' => isset($Data['iei_createby']) ? $Data['iei_createby'] : NULL,
			':iei_currency' => isset($Data['iei_currency']) ? $Data['iei_currency'] : NULL,
			':business' => isset($Data['business']) ? $Data['business'] : NULL

		));

		if (is_numeric($resInsert) && $resInsert > 0) {

			//Se agregan los asientos contables*/*******

			$sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user, business, branch)
								VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user, :business, :branch)";


			$resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

				':mac_doc_num' => 1,
				':mac_status' => 1,
				':mac_base_type' => is_numeric($Data['iei_doctype']) ? $Data['iei_doctype'] : 0,
				':mac_base_entry' => $resInsert,
				':mac_doc_date' => $this->validateDate($Data['iei_docdate']) ? $Data['iei_docdate'] : NULL,
				':mac_doc_duedate' => $this->validateDate($Data['iei_duedate']) ? $Data['iei_duedate'] : NULL,
				':mac_legal_date' => $this->validateDate($Data['iei_docdate']) ? $Data['iei_docdate'] : NULL,
				':mac_ref1' => is_numeric($Data['iei_doctype']) ? $Data['iei_doctype'] : 0,
				':mac_ref2' => "",
				':mac_ref3' => "",
				':mac_loc_total' => is_numeric($Data['iei_doctotal']) ? $Data['iei_doctotal'] : 0,
				':mac_fc_total' => is_numeric($Data['iei_doctotal']) ? $Data['iei_doctotal'] : 0,
				':mac_sys_total' => is_numeric($Data['iei_doctotal']) ? $Data['iei_doctotal'] : 0,
				':mac_trans_dode' => 1,
				':mac_beline_nume' => 1,
				':mac_vat_date' => $this->validateDate($Data['iei_docdate']) ? $Data['iei_docdate'] : NULL,
				':mac_serie' => 1,
				':mac_number' => 1,
				':mac_bammntsys' => is_numeric($Data['iei_baseamnt']) ? $Data['iei_baseamnt'] : 0,
				':mac_bammnt' => is_numeric($Data['iei_baseamnt']) ? $Data['iei_baseamnt'] : 0,
				':mac_wtsum' => 1,
				':mac_vatsum' => is_numeric($Data['iei_taxtotal']) ? $Data['iei_taxtotal'] : 0,
				':mac_comments' => isset($Data['iei_comment']) ? $Data['iei_comment'] : NULL,
				':mac_create_date' => $this->validateDate($Data['iei_createat']) ? $Data['iei_createat'] : NULL,
				':mac_made_usuer' => isset($Data['iei_createby']) ? $Data['iei_createby'] : NULL,
				':mac_update_date' => date("Y-m-d"),
				':mac_update_user' => isset($Data['iei_createby']) ? $Data['iei_createby'] : NULL,
				':business' => $Data['business'],
				':branch' => $Data['branch']
			));


			if (is_numeric($resInsertAsiento) && $resInsertAsiento > 0) {
				// Se verifica que el detalle no de error insertando //
			} else {

				// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
				// se retorna el error y se detiene la ejecucion del codigo restante.
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'	  => $resInsertAsiento,
					'mensaje'	=> 'No se pudo registrar la entrada de inventario'
				);

				$this->response($respuesta);

				return;
			}


			// Se actualiza la serie de la numeracion del documento
			$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id'      => $Data['iei_series']
			));


			if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
			} else {
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'    => $resActualizarNumeracion,
					'mensaje'	=> 'No se pudo crear la entrada  '
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			// Fin de la actualizacion de la numeracion del documento



			foreach ($ContenidoDetalle as $key => $detail) {

				$sqlInsertDetail = "INSERT INTO iei1 (ei1_docentry, ei1_itemcode, ei1_itemname, ei1_quantity, ei1_uom, ei1_whscode, ei1_price, ei1_vat, ei1_vatsum, ei1_discount, ei1_linetotal,
									ei1_costcode, ei1_ubusiness,ei1_project, ei1_acctcode, ei1_basetype, ei1_doctype, ei1_avprice, ei1_inventory,  ei1_linenum, ei1_acciva,ei1_concept, ei1_ubication)
                                    VALUES(:ei1_docentry, :ei1_itemcode, :ei1_itemname, :ei1_quantity, :ei1_uom, :ei1_whscode, :ei1_price, :ei1_vat, :ei1_vatsum, :ei1_discount, :ei1_linetotal,
									:ei1_costcode, :ei1_ubusiness,:ei1_project, :ei1_acctcode, :ei1_basetype, :ei1_doctype, :ei1_avprice, :ei1_inventory, :ei1_linenum, :ei1_acciva,:ei1_concept, 
									:ei1_ubication)";
				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(

					':ei1_docentry'  => $resInsert,
					':ei1_itemcode' => isset($detail['ei1_itemcode']) ? $detail['ei1_itemcode'] : NULL,
					':ei1_itemname' => isset($detail['ei1_itemname']) ? $detail['ei1_itemname'] : NULL,
					':ei1_quantity' => is_numeric($detail['ei1_quantity']) ? $detail['ei1_quantity'] : 0,
					':ei1_uom' => isset($detail['ei1_uom']) ? $detail['ei1_uom'] : NULL,
					':ei1_whscode' => isset($detail['ei1_whscode']) ? $detail['ei1_whscode'] : NULL,
					':ei1_price' => is_numeric($detail['ei1_price']) ? $detail['ei1_price'] : 0,
					':ei1_vat' => is_numeric($detail['ei1_vat']) ? $detail['ei1_vat'] : 0,
					':ei1_vatsum' => is_numeric($detail['ei1_vatsum']) ? $detail['ei1_vatsum'] : 0,
					':ei1_discount' => is_numeric($detail['ei1_discount']) ? $detail['ei1_discount'] : 0,
					':ei1_linetotal' => is_numeric($detail['ei1_linetotal']) ? $detail['ei1_linetotal'] : 0,
					':ei1_costcode' => isset($detail['ei1_costcode']) ? $detail['ei1_costcode'] : NULL,
					':ei1_ubusiness' => isset($detail['ei1_ubusiness']) ? $detail['ei1_ubusiness'] : NULL,
					':ei1_project' => isset($detail['ei1_project']) ? $detail['ei1_project'] : NULL,
					':ei1_acctcode' => is_numeric($detail['ei1_acctcode']) ? $detail['ei1_acctcode'] : 0,
					':ei1_basetype' => is_numeric($detail['ei1_basetype']) ? $detail['ei1_basetype'] : 0,
					':ei1_doctype' => is_numeric($detail['ei1_doctype']) ? $detail['ei1_doctype'] : 0,
					':ei1_avprice' => is_numeric($detail['ei1_avprice']) ? $detail['ei1_avprice'] : 0,
					':ei1_inventory' => is_numeric($detail['ei1_inventory']) ? $detail['ei1_inventory'] : 0,
					':ei1_linenum' => is_numeric($detail['ei1_linenum']) ? $detail['ei1_linenum'] : 0,
					':ei1_acciva' => is_numeric($detail['ei1_acciva']) ? $detail['ei1_acciva'] : 0,
					':ei1_concept' => is_numeric($detail['ei1_concept']) ? $detail['ei1_concept'] : 0,
					':ei1_ubication' => isset($detail['ei1_ubication']) ? $detail['ei1_ubication'] : NULL
				));

				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle de la cotizacion se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertDetail,
						'mensaje'	=> 'No se pudo registrar la entrada '
					);

					$this->response($respuesta);

					return;
				}

				// SI EL ITEM ES INVETARIABLE
				// SE VERIFICA SI EL ARTICULO ESTA MARCADO PARA MANEJARSE EN INVENTARIO
				// Y A SU VES SI MANEJA LOTE
				$sqlItemINV = "SELECT dma_item_inv FROM dmar WHERE dma_item_code = :dma_item_code AND dma_item_inv = :dma_item_inv";
				$resItemINV = $this->pedeo->queryTable($sqlItemINV, array(

					':dma_item_code' => $detail['ei1_itemcode'],
					':dma_item_inv'  => 1
				));

				if (isset($resItemINV[0])) {


					// CONSULTA PARA VERIFICAR SI EL ALMACEN MANEJA UBICACION
					$sqlubicacion = "SELECT * FROM dmws WHERE dws_ubication = :dws_ubication AND dws_code = :dws_code AND business = :business";
					$resubicacion = $this->pedeo->queryTable($sqlubicacion, array(
						':dws_ubication' => 1,
						':dws_code' => $detail['ei1_whscode'],
						':business' => $Data['business']
					));


					if ( isset($resubicacion[0]) ){
						$ManejaUbicacion = 1;
					}else{
						$ManejaUbicacion = 0;
					}

					// SI EL ARTICULO MANEJA LOTE
					$sqlLote = "SELECT dma_lotes_code FROM dmar WHERE dma_item_code = :dma_item_code AND dma_lotes_code = :dma_lotes_code";
					$resLote = $this->pedeo->queryTable($sqlLote, array(
	
						':dma_item_code' => $detail['ei1_itemcode'],
						':dma_lotes_code'  => 1
					));
	
					if (isset($resLote[0])) {
						$ManejaLote = 1;
					} else {
						$ManejaLote = 0;
					}

					$ManejaInvetario = 1;
				} else {
					$ManejaInvetario = 0;
				}

			

				// FIN PROCESO ITEM MANEJA INVENTARIO Y LOTE
				// si el item es inventariable
				if ($ManejaInvetario == 1) {

					//SE VERIFICA SI EL ARTICULO MANEJA SERIAL
					$sqlItemSerial = "SELECT dma_series_code FROM dmar WHERE  dma_item_code = :dma_item_code AND dma_series_code = :dma_series_code";
					$resItemSerial = $this->pedeo->queryTable($sqlItemSerial, array(

						':dma_item_code' => $detail['ei1_itemcode'],
						':dma_series_code'  => 1
					));

					if (isset($resItemSerial[0])) {
						$ManejaSerial = 1;

						if (!isset($detail['serials'])) {
							$respuesta = array(
								'error'   => true,
								'data'    => [],
								'mensaje' => 'No se encontraron los seriales para el articulo: ' . $detail['ei1_itemcode']
							);

							$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

							return;
						}

						$AddSerial = $this->generic->addSerial($detail['serials'], $detail['ei1_itemcode'], $Data['iei_doctype'], $resInsert, $DocNumVerificado, $Data['iei_docdate'], 1, $Data['iei_comment'], $detail['ei1_whscode'], $detail['ei1_quantity'], $Data['iei_createby'], $resInsertDetail, $Data['business']);

						if (isset($AddSerial['error']) && $AddSerial['error'] == false) {
						} else {
							$respuesta = array(
								'error'   => true,
								'data'    => $AddSerial['data'],
								'mensaje' => $AddSerial['mensaje']
							);

							$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

							return;
						}
					} else {
						$ManejaSerial = 0;
					}

					//

					//se busca el costo del item en el momento de la creacion del documento de venta
					//para almacenar en el movimiento de inventario
					//SI EL ARTICULO MANEJA LOTE SE BUSCA POR LOTE Y ALMACEN

					$sqlCostoMomentoRegistro = '';
					$resCostoMomentoRegistro = [];
					// SI EL ALMACEN MANEJA UBICACION
					if ( $ManejaUbicacion == 1 ){
						if ($ManejaLote == 1) {

							$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_lote = :bdi_lote AND bdi_ubication = :bdi_ubication AND business = :business";
							$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
								':bdi_whscode'   => $detail['ei1_whscode'],
								':bdi_itemcode'  => $detail['ei1_itemcode'],
								':bdi_lote'      => $detail['ote_code'],
								':bdi_ubication' => $detail['ei1_ubication'],
								':business' 	 => $Data['business']
							));
						} else {
							$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_ubication = :bdi_ubication AND business = :business";
							$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
								':bdi_whscode'   => $detail['ei1_whscode'],
								':bdi_itemcode'  => $detail['ei1_itemcode'],
								':bdi_ubication' => $detail['ei1_ubication'],
								':business' 	 => $Data['business']
							));
						}
					}else{

						if ($ManejaLote == 1) {

							$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_lote = :bdi_lote AND business = :business";
							$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
								':bdi_whscode' 	=> $detail['ei1_whscode'],
								':bdi_itemcode' => $detail['ei1_itemcode'],
								':bdi_lote'	 	=> $detail['ote_code'],
								':business' 	=> $Data['business']
							));
						} else {
							$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND business = :business";
							$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
								':bdi_whscode' 	=> $detail['ei1_whscode'],
								':bdi_itemcode' => $detail['ei1_itemcode'],
								':business' 	=> $Data['business']
							));
						}
	
					}
				


					if (isset($resCostoMomentoRegistro[0])) {

						$NuevoCostoPonderado = 0;
						// se aplica costo ponderado
						if ($resCostoMomentoRegistro[0]['bdi_quantity'] > 0) {


							$CantidadActual = $resCostoMomentoRegistro[0]['bdi_quantity'];
							$CostoActual = 	$resCostoMomentoRegistro[0]['bdi_avgprice'];


							$CantidadNueva = $detail['ei1_quantity'];
							$CostoNuevo = $detail['ei1_price'];

							$CantidadTotal = ($CantidadActual + $CantidadNueva);

							if (trim($Data['iei_currency']) != $MONEDALOCAL) {
								$CostoNuevo = ($CostoNuevo * $TasaDocLoc);
							}

							$NuevoCostoPonderado = ($CantidadActual  *  $CostoActual) + ($CantidadNueva * $CostoNuevo);
							$NuevoCostoPonderado = round(($NuevoCostoPonderado / $CantidadTotal), $DECI_MALES);
						} else {

							$CostoNuevo = $detail['ei1_price'];

							if (trim($Data['iei_currency']) != $MONEDALOCAL) {
								$CostoNuevo = ($CostoNuevo * $TasaDocLoc);
							}


							$NuevoCostoPonderado = $CostoNuevo;
						}

						$sqlInserMovimiento = '';
						$resInserMovimiento = [];
						
						//Se aplica el movimiento de inventario
						$sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode,bmi_quantity,bmi_whscode,bmi_createat,bmi_createby,bmy_doctype,bmy_baseentry,bmi_cost,bmi_currequantity,bmi_basenum,bmi_docdate,bmi_duedate,bmi_duedev,bmi_comment, bmi_lote, bmi_ubication,business)
											VALUES (:bmi_itemcode,:bmi_quantity, :bmi_whscode,:bmi_createat,:bmi_createby,:bmy_doctype,:bmy_baseentry,:bmi_cost,:bmi_currequantity,:bmi_basenum,:bmi_docdate,:bmi_duedate,:bmi_duedev,:bmi_comment,:bmi_lote, :bmi_ubication,:business)";

						$resInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(

							':bmi_itemcode' => isset($detail['ei1_itemcode']) ? $detail['ei1_itemcode'] : NULL,
							':bmi_quantity' => is_numeric($detail['ei1_quantity']) ? $detail['ei1_quantity'] * $Data['invtype'] : 0,
							':bmi_whscode'  => isset($detail['ei1_whscode']) ? $detail['ei1_whscode'] : NULL,
							':bmi_createat' => $this->validateDate($Data['iei_createat']) ? $Data['iei_createat'] : NULL,
							':bmi_createby' => isset($Data['iei_createby']) ? $Data['iei_createby'] : NULL,
							':bmy_doctype'  => is_numeric($Data['iei_doctype']) ? $Data['iei_doctype'] : 0,
							':bmy_baseentry' => $resInsert,
							':bmi_cost'      => $NuevoCostoPonderado,
							':bmi_currequantity' => $resCostoMomentoRegistro[0]['bdi_quantity'],
							':bmi_basenum'			=> $DocNumVerificado,
							':bmi_docdate' => $this->validateDate($Data['iei_docdate']) ? $Data['iei_docdate'] : NULL,
							':bmi_duedate' => $this->validateDate($Data['iei_duedate']) ? $Data['iei_duedate'] : NULL,
							':bmi_duedev'  => $this->validateDate($Data['iei_duedev']) ? $Data['iei_duedev'] : NULL,
							':bmi_comment' => isset($Data['iei_comment']) ? $Data['iei_comment'] : NULL,
							':bmi_lote' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
							':bmi_ubication' => isset($detail['ei1_ubication']) ? $detail['ei1_ubication'] : NULL,
							':business' => isset($Data['business']) ? $Data['business'] : NULL
						));
						
					

						if (is_numeric($resInserMovimiento) && $resInserMovimiento > 0) {
							// Se verifica que el detalle no de error insertando //
						} else {

							// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
							// se retorna el error y se detiene la ejecucion del codigo restante.
							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' => $resInserMovimiento,
								'mensaje'	=> 'No se pudo registra la entrada de inventario'
							);

							$this->response($respuesta);

							return;
						}
					} else {

						// SE COLOCA EL PRECIO DE LA LINEA COMO EL COSTO
						//Se aplica el movimiento de inventario
						//SE VALIDA SI EL ARTICULO MANEJA LOTE
						$sqlInserMovimiento = '';
						$resInserMovimiento = [];

						
						$sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode,bmi_quantity,bmi_whscode,bmi_createat,bmi_createby,bmy_doctype,bmy_baseentry,bmi_cost,bmi_currequantity,bmi_basenum,bmi_docdate,bmi_duedate,bmi_duedev,bmi_comment,bmi_lote, bmi_ubication,business)
															VALUES (:bmi_itemcode,:bmi_quantity, :bmi_whscode,:bmi_createat,:bmi_createby,:bmy_doctype,:bmy_baseentry,:bmi_cost,:bmi_currequantity,:bmi_basenum,:bmi_docdate,:bmi_duedate,:bmi_duedev,:bmi_comment,:bmi_lote, :bmi_ubication,:business)";
						$resInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(

							':bmi_itemcode'  => isset($detail['ei1_itemcode']) ? $detail['ei1_itemcode'] : NULL,
							':bmi_quantity'  => is_numeric($detail['ei1_quantity']) ? $detail['ei1_quantity'] * $Data['invtype'] : 0,
							':bmi_whscode'   => isset($detail['ei1_whscode']) ? $detail['ei1_whscode'] : NULL,
							':bmi_createat'  => $this->validateDate($Data['iei_createat']) ? $Data['iei_createat'] : NULL,
							':bmi_createby'  => isset($Data['iei_createby']) ? $Data['iei_createby'] : NULL,
							':bmy_doctype'   => is_numeric($Data['iei_doctype']) ? $Data['iei_doctype'] : 0,
							':bmy_baseentry' => $resInsert,
							':bmi_cost'      => $detail['ei1_price'],
							':bmi_currequantity' => $detail['ei1_quantity'],
							':bmi_basenum'			=> $DocNumVerificado,
							':bmi_docdate' => $this->validateDate($Data['iei_docdate']) ? $Data['iei_docdate'] : NULL,
							':bmi_duedate' => $this->validateDate($Data['iei_duedate']) ? $Data['iei_duedate'] : NULL,
							':bmi_duedev'  => $this->validateDate($Data['iei_duedev']) ? $Data['iei_duedev'] : NULL,
							':bmi_comment' => isset($Data['iei_comment']) ? $Data['iei_comment'] : NULL,
							':bmi_lote' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
							':bmi_ubication' => isset($detail['ei1_ubication']) ? $detail['ei1_ubication'] : NULL,
							':business' => isset($Data['business']) ? $Data['business'] : NULL

						));
						


						if (is_numeric($resInserMovimiento) && $resInserMovimiento > 0) {
							// Se verifica que el detalle no de error insertando //
						} else {

							// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
							// se retorna el error y se detiene la ejecucion del codigo restante.
							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' => $resInserMovimiento,
								'mensaje'	=> 'No se pudo registra la entrada de inventario'
							);

							$this->response($respuesta);

							return;
						}
					}

					//FIN aplicacion de movimiento de inventario


					//Se Aplica el movimiento en stock ***************
					//Buscando item en el stock
					//SE VALIDA SI EL ARTICULO MANEJA LOTE
					$sqlCostoCantidad = '';
					$resCostoCantidad = [];
					$CantidadPorAlmacen = 0;
					$CostoPorAlmacen = 0;

					// SI EL ALMACEN MANEJA UBICACION

					if ( $ManejaUbicacion == 1 ){
						if ($ManejaLote == 1) {

							$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
											FROM tbdi
											WHERE bdi_itemcode = :bdi_itemcode
											AND bdi_whscode = :bdi_whscode
											AND bdi_lote = :bdi_lote
											AND bdi_ubication = :bdi_ubication
											AND business = :business";
	
							$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(
	
								':bdi_itemcode'  => $detail['ei1_itemcode'],
								':bdi_whscode'   => $detail['ei1_whscode'],
								':bdi_lote' 	 => $detail['ote_code'],
								':bdi_ubication' => $detail['ei1_ubication'],
								':business' 	 => $Data['business']
							));
							// se busca la cantidad general del articulo agrupando todos los almacenes y lotes
							$sqlCGA = "SELECT sum(COALESCE(bdi_quantity, 0)) as bdi_quantity, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode AND business = :business GROUP BY bdi_whscode, bdi_avgprice";
							$resCGA = $this->pedeo->queryTable($sqlCGA, array(
								':bdi_itemcode' => $detail['ei1_itemcode'],
								':bdi_whscode'  => $detail['ei1_whscode'],
								':business' 	=> $Data['business']
							));
	
							if (isset($resCGA[0]['bdi_quantity']) && is_numeric($resCGA[0]['bdi_quantity'])) {
	
								$CantidadPorAlmacen = $resCGA[0]['bdi_quantity'];
								$CostoPorAlmacen = $resCGA[0]['bdi_avgprice'];
							} else {
	
								$CantidadPorAlmacen = 0;
								$CostoPorAlmacen = 0;
							}
						} else {

							$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
											FROM tbdi
											WHERE bdi_itemcode = :bdi_itemcode
											AND bdi_whscode = :bdi_whscode
											AND bdi_ubication = :bdi_ubication
											AND business = :business";
	
							$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(
	
								':bdi_itemcode'  => $detail['ei1_itemcode'],
								':bdi_whscode'   => $detail['ei1_whscode'],
								':bdi_ubication' => $detail['ei1_ubication'],
								':business' 	 => $Data['business']
							));
							// se busca la cantidad general del articulo agrupando todos los almacenes y lotes
							$sqlCGA = "SELECT sum(COALESCE(bdi_quantity, 0)) as bdi_quantity, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode AND business = :business GROUP BY bdi_whscode, bdi_avgprice";
							$resCGA = $this->pedeo->queryTable($sqlCGA, array(
								':bdi_itemcode' => $detail['ei1_itemcode'],
								':bdi_whscode'  => $detail['ei1_whscode'],
								':business' 	=> $Data['business']
							));
	
							if (isset($resCGA[0]['bdi_quantity']) && is_numeric($resCGA[0]['bdi_quantity'])) {
	
								$CantidadPorAlmacen = $resCGA[0]['bdi_quantity'];
								$CostoPorAlmacen = $resCGA[0]['bdi_avgprice'];
							} else {
	
								$CantidadPorAlmacen = 0;
								$CostoPorAlmacen = 0;
							}
						}

					}else{
						if ($ManejaLote == 1) {

							$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
											FROM tbdi
											WHERE bdi_itemcode = :bdi_itemcode
											AND bdi_whscode = :bdi_whscode
											AND bdi_lote = :bdi_lote
											AND business = :business";
	
							$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(
	
								':bdi_itemcode' => $detail['ei1_itemcode'],
								':bdi_whscode'  => $detail['ei1_whscode'],
								':bdi_lote' 	=> $detail['ote_code'],
								':business'	 	=> $Data['business']
							));
							// se busca la cantidad general del articulo agrupando todos los almacenes y lotes
							$sqlCGA = "SELECT sum(COALESCE(bdi_quantity, 0)) as bdi_quantity, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode AND business = :business GROUP BY bdi_whscode, bdi_avgprice";
							$resCGA = $this->pedeo->queryTable($sqlCGA, array(
								':bdi_itemcode' => $detail['ei1_itemcode'],
								':bdi_whscode'  => $detail['ei1_whscode'],
								':business' 	=> $Data['business']
							));
	
							if (isset($resCGA[0]['bdi_quantity']) && is_numeric($resCGA[0]['bdi_quantity'])) {
	
								$CantidadPorAlmacen = $resCGA[0]['bdi_quantity'];
								$CostoPorAlmacen = $resCGA[0]['bdi_avgprice'];
							} else {
	
								$CantidadPorAlmacen = 0;
								$CostoPorAlmacen = 0;
							}
						} else {
							$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
											FROM tbdi
											WHERE bdi_itemcode = :bdi_itemcode
											AND bdi_whscode = :bdi_whscode
											AND business = :business";
	
							$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(
	
								':bdi_itemcode' => $detail['ei1_itemcode'],
								':bdi_whscode'  => $detail['ei1_whscode'],
								':business' 	=> $Data['business']

							));
	
							$CantidadPorAlmacen = isset($resCostoCantidad[0]['bdi_quantity']) ? $resCostoCantidad[0]['bdi_quantity'] : 0;
							$CostoPorAlmacen = isset($resCostoCantidad[0]['bdi_avgprice']) ? $resCostoCantidad[0]['bdi_avgprice'] : 0;
						}
					}

					// SI EXISTE EN EL STOCK
					if (isset($resCostoCantidad[0])) {
						//SI TIENE CANTIDAD POSITIVA
						if ($resCostoCantidad[0]['bdi_quantity'] > 0 && $CantidadPorAlmacen > 0) {

							$CantidadItem = $resCostoCantidad[0]['bdi_quantity'];
							$CantidadActual = $CantidadPorAlmacen;
							$CostoActual = $CostoPorAlmacen;


							$CantidadNueva = $detail['ei1_quantity'];
							$CostoNuevo = $detail['ei1_price'];


							$CantidadTotal = ($CantidadActual + $CantidadNueva);
							$CantidadTotalItemSolo = ($CantidadItem + $CantidadNueva);

							if (trim($Data['iei_currency']) != $MONEDALOCAL) {
								$CostoNuevo = ($CostoNuevo * $TasaDocLoc);
							}

							$NuevoCostoPonderado = ($CantidadActual  *  $CostoActual) + ($CantidadNueva * $CostoNuevo);
							$NuevoCostoPonderado = round(($NuevoCostoPonderado / $CantidadTotal), $DECI_MALES);

							$sqlUpdateCostoCantidad = "UPDATE tbdi
													SET bdi_quantity = :bdi_quantity
													,bdi_avgprice = :bdi_avgprice
													WHERE  bdi_id = :bdi_id";

							$resUpdateCostoCantidad = $this->pedeo->updateRow($sqlUpdateCostoCantidad, array(

								':bdi_quantity' => $CantidadTotalItemSolo,
								':bdi_avgprice' => $NuevoCostoPonderado,
								':bdi_id' 		=> $resCostoCantidad[0]['bdi_id']
							));

							if (is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad == 1) {
							} else {

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resUpdateCostoCantidad,
									'mensaje'	=> 'No se pudo registrar el movimiento en el stock'
								);


								$this->response($respuesta);

								return;
							}

							// SE ACTUALIZA EL COSTO PONDERADO EN EL ALMACEN DEL ARTICULO
							// SIN MIRAR LA UBICACION O LOTE
							$sqlAlmacenMasivo = "UPDATE tbdi
												SET bdi_avgprice = :bdi_avgprice
												WHERE bdi_itemcode = :bdi_itemcode
												AND bdi_whscode = :bdi_whscode
												AND business = :business";
							
							$resAlmacenMasivo = $this->pedeo->updateRow($sqlAlmacenMasivo, array(
								':bdi_avgprice' => $NuevoCostoPonderado,
								':bdi_itemcode' => $detail['ei1_itemcode'],
								':bdi_whscode'  => $detail['ei1_whscode'],
								':business' 	=> $Data['business']
							));		
							
							if (is_numeric($resAlmacenMasivo) && $resAlmacenMasivo > 0 || $resAlmacenMasivo == 0) {
							} else {

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resAlmacenMasivo,
									'mensaje'	=> 'No se pudo registrar el movimiento en el stock'
								);


								$this->response($respuesta);

								return;
							}

							// SE ACTUALZAN TODOS LOS COSTOS PONDERADOS DE LOS ARTICULOS EN EL ALMACEN
							if ($ManejaLote == 1) {
								$sqlUpdateCostoCantidad = "UPDATE tbdi
													SET bdi_avgprice = :bdi_avgprice
													WHERE bdi_itemcode = :bdi_itemcode
													AND bdi_whscode = :bdi_whscode
													AND business = :business";

								$resUpdateCostoCantidad = $this->pedeo->updateRow($sqlUpdateCostoCantidad, array(

									':bdi_avgprice' => $NuevoCostoPonderado,
									':bdi_itemcode' => $detail['ei1_itemcode'],
									':bdi_whscode'  => $detail['ei1_whscode'],
									':business' 	=> $Data['business']
								));

								if (is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad > 0) {
								} else {

									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'    => $resUpdateCostoCantidad,
										'mensaje'	=> 'No se pudo crear la Entrada de inventario'
									);

									$this->response($respuesta);

									return;
								}
							}
						} else {

							$CantidadActual = $resCostoCantidad[0]['bdi_quantity'];
							$CantidadNueva = $detail['ei1_quantity'];
							$CostoNuevo = $detail['ei1_price'];

							if (trim($Data['iei_currency']) != $MONEDALOCAL) {
								$CostoNuevo = ($CostoNuevo * $TasaDocLoc);
							}

							$CantidadTotal = ($CantidadActual + $CantidadNueva);

							$sqlUpdateCostoCantidad = "UPDATE tbdi
													SET bdi_quantity = :bdi_quantity
													,bdi_avgprice = :bdi_avgprice
													WHERE  bdi_id = :bdi_id";

							$resUpdateCostoCantidad = $this->pedeo->updateRow($sqlUpdateCostoCantidad, array(

								':bdi_quantity' => $CantidadTotal,
								':bdi_avgprice' => $CostoNuevo,
								':bdi_id' 		=> $resCostoCantidad[0]['bdi_id']
							));

							if (is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad == 1) {
							} else {

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resUpdateCostoCantidad,
									'mensaje'	=> 'No se pudo registrar el movimiento en el stock'
								);


								$this->response($respuesta);

								return;
							}

							// SE ACTUALIZA EL COSTO PONDERADO EN EL ALMACEN DEL ARTICULO
							// SIN MIRAR LA UBICACION O LOTE
							$sqlAlmacenMasivo = "UPDATE tbdi
												SET bdi_avgprice = :bdi_avgprice
												WHERE bdi_itemcode = :bdi_itemcode
												AND bdi_whscode = :bdi_whscode
												AND business = :business";
							
							$resAlmacenMasivo = $this->pedeo->updateRow($sqlAlmacenMasivo, array(
								':bdi_avgprice' => $NuevoCostoPonderado,
								':bdi_itemcode' => $detail['ei1_itemcode'],
								':bdi_whscode'  => $detail['ei1_whscode'],
								':business' 	=> $Data['business']
							));		
							
							if (is_numeric($resAlmacenMasivo) && $resAlmacenMasivo > 0 || $resAlmacenMasivo == 0) {
							} else {

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resAlmacenMasivo,
									'mensaje'	=> 'No se pudo registrar el movimiento en el stock'
								);


								$this->response($respuesta);

								return;
							}

							// SE ACTUALZAN TODOS LOS COSTOS PONDERADOS DE LOS ARTICULOS EN EL ALMACEN
							if ($ManejaLote == 1) {
								$sqlUpdateCostoCantidad = "UPDATE tbdi
														SET bdi_avgprice = :bdi_avgprice
														WHERE bdi_itemcode = :bdi_itemcode
														AND bdi_whscode = :bdi_whscode
														AND business = :business";

								$resUpdateCostoCantidad = $this->pedeo->updateRow($sqlUpdateCostoCantidad, array(

									':bdi_avgprice' => $CostoNuevo,
									':bdi_itemcode' => $detail['ei1_itemcode'],
									':bdi_whscode'  => $detail['ei1_whscode'],
									':business' 	=> $Data['business']
								));

								if (is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad  > 0) {
								} else {

									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'    => $resUpdateCostoCantidad,
										'mensaje'	=> 'No se pudo crear la Entrada de Compra 6'
									);

									$this->response($respuesta);

									return;
								}
							}
						}

						// En caso de que no exista el item en el stock
						// Se inserta en el stock con el precio de compra

					} else {

						if ($CantidadPorAlmacen > 0) {
							$CantidadItem = 0;
							$CantidadActual = $CantidadPorAlmacen;
							$CostoActual = $CostoPorAlmacen;

							$CantidadNueva = $detail['ei1_quantity'];
							$CostoNuevo = $detail['ei1_price'];

							$CantidadTotal = ($CantidadActual + $CantidadNueva);
							$CantidadTotalItemSolo = ($CantidadItem + $CantidadNueva);

							if (trim($Data['iei_currency']) != $MONEDALOCAL) {
								$CostoNuevo = ($CostoNuevo * $TasaDocLoc);
							}

							$NuevoCostoPonderado = ($CantidadActual  *  $CostoActual) + ($CantidadNueva * $CostoNuevo);
							$NuevoCostoPonderado = round(($NuevoCostoPonderado / $CantidadTotal), $DECI_MALES);


							$sqlInsertCostoCantidad = '';
							$resInsertCostoCantidad =	[];

							// SI EL ALMACEN MANEJA UBICACION
							if ( $ManejaUbicacion == 1 ){
								// SI EL ARTICULO MANEJA LOTE
								if ($ManejaLote == 1) {
									$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_lote, bdi_ubication, business)
															VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_lote, :bdi_ubication, :business)";


									$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

										':bdi_itemcode'  => $detail['ei1_itemcode'],
										':bdi_whscode'   => $detail['ei1_whscode'],
										':bdi_quantity'  => $detail['ei1_quantity'],
										':bdi_avgprice'  => $NuevoCostoPonderado,
										':bdi_lote' 	 => $detail['ote_code'],
										':bdi_ubication' => $detail['ei1_ubication'],
										':business' 	 => $Data['business']
									));
								} else {
									$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_ubication, business)
															VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_ubication, :business)";


									$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

										':bdi_itemcode'  => $detail['ei1_itemcode'],
										':bdi_whscode'   => $detail['ei1_whscode'],
										':bdi_quantity'  => $detail['ei1_quantity'],
										':bdi_avgprice'  => $NuevoCostoPonderado,
										':bdi_ubication' => $detail['ei1_ubication'],
										':business' 	 => $Data['business']
									));
								}
							}else{
								// SI EL ALMACEN MANEJA UBICACION 
								if ( $ManejaUbicacion == 1 ) {
									// SI EL ARTICULO MANEJA LOTE
									if ($ManejaLote == 1) {
										$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_lote, bdi_ubication, business)
																VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_lote, :bdi_ubication, :business)";


										$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

											':bdi_itemcode' => $detail['ei1_itemcode'],
											':bdi_whscode'  => $detail['ei1_whscode'],
											':bdi_quantity' => $detail['ei1_quantity'],
											':bdi_avgprice' => $NuevoCostoPonderado,
											':bdi_lote' 	=> $detail['ote_code'],
											':bdi_ubication' => $detail['ei1_ubication'],
											':business'		 => $Data['business']
										));
									} else {
										$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_ubication, business)
										VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_ubication, :business)";


										$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

											':bdi_itemcode' => $detail['ei1_itemcode'],
											':bdi_whscode'  => $detail['ei1_whscode'],
											':bdi_quantity' => $detail['ei1_quantity'],
											':bdi_avgprice' => $NuevoCostoPonderado,
											':bdi_ubication' => $detail['ei1_ubication'],
											':business'		 => $Data['business']
										));
									}
								}else{
									// SI EL ARTICULO MANEJA LOTE
									if ($ManejaLote == 1) {
										$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_lote, business)
																VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_lote, :business)";
		
		
										$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(
		
											':bdi_itemcode' => $detail['ei1_itemcode'],
											':bdi_whscode'  => $detail['ei1_whscode'],
											':bdi_quantity' => $detail['ei1_quantity'],
											':bdi_avgprice' => $NuevoCostoPonderado,
											':bdi_lote' 	=> $detail['ote_code'],
											':business' 	=> $Data['business']
										));
									} else {
		
										$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, business)
																VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :business)";
		
		
										$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(
		
											':bdi_itemcode' => $detail['ei1_itemcode'],
											':bdi_whscode'  => $detail['ei1_whscode'],
											':bdi_quantity' => $detail['ei1_quantity'],
											':bdi_avgprice' => $NuevoCostoPonderado,
											':business' 	=> $Data['business']
										));
									}
								}
							}



							if (is_numeric($resInsertCostoCantidad) && $resInsertCostoCantidad > 0) {
								// Se verifica que el detalle no de error insertando //
							} else {

								// si falla algun insert del detalle de la orden de compra se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' 	  => $resInsertCostoCantidad,
									'mensaje' => 'No se pudo registrar la Entrada por inventario'
								);

								$this->response($respuesta);

								return;
							}

							// SE ACTUALIZA EL COSTO PONDERADO EN EL ALMACEN DEL ARTICULO
							// SIN MIRAR LA UBICACION O LOTE
							$sqlAlmacenMasivo = "UPDATE tbdi
												SET bdi_avgprice = :bdi_avgprice
												WHERE bdi_itemcode = :bdi_itemcode
												AND bdi_whscode = :bdi_whscode
												AND business = :business";
							
							$resAlmacenMasivo = $this->pedeo->updateRow($sqlAlmacenMasivo, array(
								':bdi_avgprice' => $NuevoCostoPonderado,
								':bdi_itemcode' => $detail['ei1_itemcode'],
								':bdi_whscode'  => $detail['ei1_whscode'],
								':business' 	=> $Data['business']
							));		
							
							if (is_numeric($resAlmacenMasivo) && $resAlmacenMasivo > 0 || $resAlmacenMasivo == 0) {
							} else {

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resAlmacenMasivo,
									'mensaje'	=> 'No se pudo registrar el movimiento en el stock'
								);


								$this->response($respuesta);

								return;
							}

							$sqlInsertCostoCantidad = '';
							$resInsertCostoCantidad = [];
							// SE ACTUALZAN TODOS LOS COSTOS PONDERADOS DE LOS ARTICULOS EN EL ALMACEN
							if ($ManejaLote == 1) {
								$sqlUpdateCostoCantidad = "UPDATE tbdi
														SET bdi_avgprice = :bdi_avgprice
														WHERE bdi_itemcode = :bdi_itemcode
														AND bdi_whscode = :bdi_whscode
														AND business = :business";

								$resUpdateCostoCantidad = $this->pedeo->updateRow($sqlUpdateCostoCantidad, array(

									':bdi_avgprice' => $NuevoCostoPonderado,
									':bdi_itemcode' => $detail['ei1_itemcode'],
									':bdi_whscode'  => $detail['ei1_whscode'],
									':business' 	=> $Data['business']
								));



								if (is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad > 0) {
								} else {

									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'    => $resUpdateCostoCantidad,
										'mensaje'	=> 'No se pudo crear la Entrada por inventario'
									);

									$this->response($respuesta);

									return;
								}
							}
						} else {
							$CostoNuevo =  $detail['ei1_price'];

							if (trim($Data['iei_currency']) != $MONEDALOCAL) {
								$CostoNuevo = ($CostoNuevo * $TasaDocLoc);
							}

							
							$sqlInsertCostoCantidad = '';
							$resInsertCostoCantidad =	[];

							// SI EL ALMACEN MANEJA UBICACION
							if ( $ManejaUbicacion == 1 ){
								//SE VALIDA SI EL ARTICULO MANEJA LOTE
								if ($ManejaLote == 1) {

									$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_lote, bdi_ubication, business)
															VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_lote, :bdi_ubication, :business)";


									$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

										':bdi_itemcode'  => $detail['ei1_itemcode'],
										':bdi_whscode'   => $detail['ei1_whscode'],
										':bdi_quantity'  => $detail['ei1_quantity'],
										':bdi_avgprice'  => $CostoNuevo,
										':bdi_lote' 	 => $detail['ote_code'],
										':bdi_ubication' => $detail['ei1_ubication'],
										':business'		 => $Data['business']
									));
								} else {
									$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_ubication, business)
									VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_ubication, :business)";


									$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

										':bdi_itemcode'  => $detail['ei1_itemcode'],
										':bdi_whscode'   => $detail['ei1_whscode'],
										':bdi_quantity'  => $detail['ei1_quantity'],
										':bdi_avgprice'  => $CostoNuevo,
										':bdi_ubication' => $detail['ei1_ubication'],
										':business' 	 => $Data['business']
									));
								}
							}else{
								//SE VALIDA SI EL ARTICULO MANEJA LOTE
								if ($ManejaLote == 1) {

									$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_lote, business)
															VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_lote, :business)";


									$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

										':bdi_itemcode' => $detail['ei1_itemcode'],
										':bdi_whscode'  => $detail['ei1_whscode'],
										':bdi_quantity' => $detail['ei1_quantity'],
										':bdi_avgprice' => $CostoNuevo,
										':bdi_lote' 	=> $detail['ote_code'],
										':business' 	=> $Data['business']
									));
								} else {

									$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, business)
																		VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :business)";


									$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

										':bdi_itemcode' => $detail['ei1_itemcode'],
										':bdi_whscode'  => $detail['ei1_whscode'],
										':bdi_quantity' => $detail['ei1_quantity'],
										':bdi_avgprice' => $CostoNuevo,
										':business'		=> $Data['business']
									));
								}

							}
					


							if (is_numeric($resInsertCostoCantidad) && $resInsertCostoCantidad > 0) {
								// Se verifica que el detalle no de error insertando //
							} else {

								// si falla algun insert del detalle de la orden de compra se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' 		=> $resInsertCostoCantidad,
									'mensaje'	=> 'No se pudo registrar la entrada de inventario'
								);

								$this->response($respuesta);

								return;
							}

							// SE ACTUALIZA EL COSTO PONDERADO EN EL ALMACEN DEL ARTICULO
							// SIN MIRAR LA UBICACION O LOTE
							$sqlAlmacenMasivo = "UPDATE tbdi
												SET bdi_avgprice = :bdi_avgprice
												WHERE bdi_itemcode = :bdi_itemcode
												AND bdi_whscode = :bdi_whscode
												AND business = :business";
							
							$resAlmacenMasivo = $this->pedeo->updateRow($sqlAlmacenMasivo, array(
								':bdi_avgprice' => $CostoNuevo,
								':bdi_itemcode' => $detail['ei1_itemcode'],
								':bdi_whscode'  => $detail['ei1_whscode'],
								':business' 	=> $Data['business']
							));		
							
							if (is_numeric($resAlmacenMasivo) && $resAlmacenMasivo > 0 || $resAlmacenMasivo == 0) {
							} else {

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resAlmacenMasivo,
									'mensaje'	=> 'No se pudo registrar el movimiento en el stock'
								);


								$this->response($respuesta);

								return;
							}

							$sqlInsertCostoCantidad = '';
							$resInsertCostoCantidad =	[];
							// SE ACTUALZAN TODOS LOS COSTOS PONDERADOS DE LOS ARTICULOS EN EL ALMACEN
							if ($ManejaLote == 1) {
								$sqlUpdateCostoCantidad = "UPDATE tbdi
														SET bdi_avgprice = :bdi_avgprice
														WHERE bdi_itemcode = :bdi_itemcode
														AND bdi_whscode = :bdi_whscode
														AND business = :business";


								$resUpdateCostoCantidad = $this->pedeo->updateRow($sqlUpdateCostoCantidad, array(

									':bdi_avgprice' => $CostoNuevo,
									':bdi_itemcode' => $detail['ei1_itemcode'],
									':bdi_whscode'  => $detail['ei1_whscode'],
									':business' => $Data['business']
									
								));



								if (is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad > 0) {
								} else {

									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'    => $resUpdateCostoCantidad,
										'mensaje'	=> 'No se pudo crear la Entrada por inventario'
									);

									$this->response($respuesta);

									return;
								}
							}
						}
					}

					//FIN de  Aplicacion del movimiento en stock

					//SE VALIDA SI EXISTE EL LOTE

					if ($ManejaLote == 1) {
						$sqlFindLote = "SELECT ote_code FROM lote WHERE ote_code = :ote_code";
						$resFindLote = $this->pedeo->queryTable($sqlFindLote, array(':ote_code' => $detail['ote_code']));

						if (!isset($resFindLote[0])) {
							// SI NO SE HA CREADO EL LOTE SE INGRESA
							$sqlInsertLote = "INSERT INTO lote(ote_code, ote_createdate, ote_duedate, ote_createby, ote_date, ote_baseentry, ote_basetype, ote_docnum)
																					VALUES(:ote_code, :ote_createdate, :ote_duedate, :ote_createby, :ote_date, :ote_baseentry, :ote_basetype, :ote_docnum)";
							$resInsertLote = $this->pedeo->insertRow($sqlInsertLote, array(

								':ote_code' => $detail['ote_code'],
								':ote_createdate' => $detail['ote_createdate'],
								':ote_duedate' => $detail['ote_duedate'],
								':ote_createby' => $Data['iei_createby'],
								':ote_date' => date('Y-m-d'),
								':ote_baseentry' => $resInsert,
								':ote_basetype' => $Data['iei_doctype'],
								':ote_docnum' => $DocNumVerificado
							));


							if (is_numeric($resInsertLote) && $resInsertLote > 0) {
							} else {
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' 		=> $resInsertLote,
									'mensaje'	=> 'No se pudo registrar la entrada de inventario'
								);

								$this->response($respuesta);

								return;
							}
						}
					}
					//FIN VALIDACION DEL LOTE
				}else{
					$respuesta = array(
						'error'   => true,
						'data'    => [],
						'mensaje' => 'El articulo: ' . $detail['ei1_itemcode'].' no esta marcado para realizar  operaciones en el stock'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}


				//LLENANDO AGRUPADOS
				$DetalleCuentaLineaDocumento = new stdClass();
				$DetalleCuentaGrupo = new stdClass();

				$DetalleCuentaLineaDocumento->ei1_acctcode = is_numeric($detail['ei1_acctcode']) ? $detail['ei1_acctcode'] : 0;
				$DetalleCuentaLineaDocumento->ei1_costcode = isset($detail['ei1_costcode']) ? $detail['ei1_costcode'] : NULL;
				$DetalleCuentaLineaDocumento->ei1_ubusiness = isset($detail['ei1_ubusiness']) ? $detail['ei1_ubusiness'] : NULL;
				$DetalleCuentaLineaDocumento->ei1_project = isset($detail['ei1_project']) ? $detail['ei1_project'] : NULL;
				$DetalleCuentaLineaDocumento->ei1_linetotal = is_numeric($detail['ei1_linetotal']) ? $detail['ei1_linetotal'] : 0;
				$DetalleCuentaLineaDocumento->ei1_vat = is_numeric($detail['ei1_vat']) ? $detail['ei1_vat'] : 0;
				$DetalleCuentaLineaDocumento->ei1_vatsum = is_numeric($detail['ei1_vatsum']) ? $detail['ei1_vatsum'] : 0;
				$DetalleCuentaLineaDocumento->ei1_price = is_numeric($detail['ei1_price']) ? $detail['ei1_price'] : 0;
				$DetalleCuentaLineaDocumento->ei1_itemcode = isset($detail['ei1_itemcode']) ? $detail['ei1_itemcode'] : NULL;
				$DetalleCuentaLineaDocumento->ei1_quantity = is_numeric($detail['ei1_quantity']) ? $detail['ei1_quantity'] : 0;
				$DetalleCuentaLineaDocumento->ei1_whscode = isset($detail['ei1_whscode']) ? $detail['ei1_whscode'] : NULL;




				$codigoCuenta = substr($DetalleCuentaLineaDocumento->ei1_acctcode, 0, 1);
				$llaveCuentaLineaDocumento = $DetalleCuentaLineaDocumento->ei1_ubusiness . $DetalleCuentaLineaDocumento->ei1_costcode . $DetalleCuentaLineaDocumento->ei1_project . $DetalleCuentaLineaDocumento->ei1_acctcode;

				$DetalleCuentaLineaDocumento->codigoCuenta = $codigoCuenta;

				//-***************************
				if (in_array($llaveCuentaLineaDocumento, $inArrayCuentaLineaDocumento)) {

					$posicionCuentaLineaDocumento = $this->buscarPosicion($llaveCuentaLineaDocumento, $inArrayCuentaLineaDocumento);
				} else {

					array_push($inArrayCuentaLineaDocumento, $llaveCuentaLineaDocumento);
					$posicionCuentaLineaDocumento = $this->buscarPosicion($llaveCuentaLineaDocumento, $inArrayCuentaLineaDocumento);
				}


				if (isset($DetalleConsolidadoCuentaLineaDocumento[$posicionCuentaLineaDocumento])) {

					if (!is_array($DetalleConsolidadoCuentaLineaDocumento[$posicionCuentaLineaDocumento])) {
						$DetalleConsolidadoCuentaLineaDocumento[$posicionCuentaLineaDocumento] = array();
					}
				} else {
					$DetalleConsolidadoCuentaLineaDocumento[$posicionCuentaLineaDocumento] = array();
				}

				array_push($DetalleConsolidadoCuentaLineaDocumento[$posicionCuentaLineaDocumento], $DetalleCuentaLineaDocumento);
				//****************************-

				$DetalleCuentaGrupo->ei1_itemcode = isset($detail['ei1_itemcode']) ? $detail['ei1_itemcode'] : NULL;
				$DetalleCuentaGrupo->ei1_acctcode = is_numeric($detail['ei1_acctcode']) ? $detail['ei1_acctcode'] : 0;
				$DetalleCuentaGrupo->ei1_quantity = is_numeric($detail['ei1_quantity']) ? $detail['ei1_quantity'] : 0;

				$llaveCuentaGrupo = $DetalleCuentaGrupo->ei1_acctcode;
				//********************************
				if (in_array($llaveCuentaGrupo, $inArrayCuentaGrupo)) {

					$posicionCuentaGrupo = $this->buscarPosicion($llaveCuentaGrupo, $inArrayCuentaGrupo);
				} else {

					array_push($inArrayCuentaGrupo, $llaveCuentaGrupo);
					$posicionCuentaGrupo = $this->buscarPosicion($llaveCuentaGrupo, $inArrayCuentaGrupo);
				}

				if (isset($DetalleConsolidadoCuentaGrupo[$posicionCuentaGrupo])) {

					if (!is_array($DetalleConsolidadoCuentaGrupo[$posicionCuentaGrupo])) {
						$DetalleConsolidadoCuentaGrupo[$posicionCuentaGrupo] = array();
					}
				} else {
					$DetalleConsolidadoCuentaGrupo[$posicionCuentaGrupo] = array();
				}

				array_push($DetalleConsolidadoCuentaGrupo[$posicionCuentaGrupo], $DetalleCuentaGrupo);

				//*******************************************

			}

			//FIN DETALLE ENTRADA




			//PROCEDIMIENTO PARA LLENAR CUENTA LINEA DOCUMENTO
			foreach ($DetalleConsolidadoCuentaLineaDocumento as $key => $posicion) {
				$grantotalLinea = 0;
				$grantotalLineaOriginal = 0;
				$codigoCuentaLinea = "";
				$cuenta = "";
				$proyecto = "";
				$prc = "";
				$unidad = "";
				foreach ($posicion as $key => $value) {
					$grantotalLinea = ($grantotalLinea + $value->ei1_linetotal);
					$codigoCuentaLinea = $value->codigoCuenta;
					$prc = $value->ei1_costcode;
					$unidad = $value->ei1_ubusiness;
					$proyecto = $value->ei1_project;
					$cuenta = $value->ei1_acctcode;
				}


				$debito = 0;
				$credito = 0;
				$MontoSysDB = 0;
				$MontoSysCR = 0;
				$grantotalLineaOriginal = $grantotalLinea;



				switch ($codigoCuentaLinea) {
					case 1:
						$credito = $grantotalLinea;
						$MontoSysCR = ($credito / $TasaLocSys);
						break;
					case 2:
						$credito = $grantotalLinea;
						$MontoSysCR = ($credito / $TasaLocSys);
						break;
					case 3:
						$credito = $grantotalLinea;
						$MontoSysCR = ($credito / $TasaLocSys);
						break;
					case 4:
						$credito = $grantotalLinea;
						$MontoSysCR = ($credito / $TasaLocSys);
						break;
					case 5:
						$credito = $grantotalLinea;
						$MontoSysCR = ($credito / $TasaLocSys);
						break;
					case 6:
						$credito = $grantotalLinea;
						$MontoSysCR = ($credito / $TasaLocSys);
						break;
					case 7:
						$credito = $grantotalLinea;
						$MontoSysCR = ($credito / $TasaLocSys);
						break;
				}


				$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

					':ac1_trans_id' => $resInsertAsiento,
					':ac1_account' => $cuenta,
					':ac1_debit' => round($debito , $DECI_MALES),
					':ac1_credit' => round($credito, $DECI_MALES),
					':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
					':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['iei_docdate']) ? $Data['iei_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['iei_duedate']) ? $Data['iei_duedate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => is_numeric($Data['iei_doctype']) ? $Data['iei_doctype'] : 0,
					':ac1_accountvs' => 1,
					':ac1_doctype' => 18,
					':ac1_ref1' => "",
					':ac1_ref2' => "",
					':ac1_ref3' => "",
					':ac1_prc_code' => $prc,
					':ac1_uncode' => $unidad,
					':ac1_prj_code' => $proyecto,
					':ac1_rescon_date' => NULL,
					':ac1_recon_total' => 0,
					':ac1_made_user' => isset($Data['iei_createby']) ? $Data['iei_createby'] : NULL,
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
					':ac1_legal_num' => isset($Data['iei_cardcode']) ? $Data['iei_cardcode'] : NULL,
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
						'mensaje'	=> 'No se pudo registrar la entrada de inventariio'
					);

					$this->response($respuesta);

					return;
				}
			}
			//FIN PROCEDIMIENTO PARA LLENAR CUENTA LINEA DOCUMENTO





			// PROCEDIMIENTO PARA LLENAR CUENTA GRUPO
			foreach ($DetalleConsolidadoCuentaLineaDocumento as $key => $posicion) {
				$grantotalLinea = 0;
				$grantotalLineaOriginal = 0;
				$codigoCuentaLinea = "";
				$cuenta = "";
				$proyecto = "";
				$prc = "";
				$unidad = "";
				foreach ($posicion as $key => $value) {
					$grantotalLinea = ($grantotalLinea + $value->ei1_linetotal);
					$codigoCuentaLinea = $value->codigoCuenta;
					$prc = $value->ei1_costcode;
					$unidad = $value->ei1_ubusiness;
					$proyecto = $value->ei1_project;

					$CUENTASINV = $this->account->getAccountItem($value->ei1_itemcode, $value->ei1_whscode);

					if ( isset($CUENTASINV['error']) && $CUENTASINV['error'] == false ) {

						$cuenta = $CUENTASINV['data']['acct_inv'];
					} else {
						// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
						// se retorna el error y se detiene la ejecucion del codigo restante.
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'	  => $CUENTASINV,
							'mensaje'	=> 'No se encontro la cuenta del grupo de articulo para el item ' . $value->ei1_itemcode
						);

						$this->response($respuesta);

						return;
					}
				}


				$debito = 0;
				$credito = 0;
				$MontoSysDB = 0;
				$MontoSysCR = 0;
				$grantotalLineaOriginal = $grantotalLinea;



				switch ($codigoCuentaLinea) {
					case 1:
						$debito = $grantotalLinea;
						$MontoSysDB = ($debito / $TasaLocSys);
						break;
					case 2:
						$debito = $grantotalLinea;
						$MontoSysDB = ($debito / $TasaLocSys);
						break;
					case 3:
						$debito = $grantotalLinea;
						$MontoSysDB = ($debito / $TasaLocSys);
						break;
					case 4:
						$debito = $grantotalLinea;
						$MontoSysDB = ($debito / $TasaLocSys);
						break;
					case 5:
						$debito = $grantotalLinea;
						$MontoSysDB = ($debito / $TasaLocSys);
						break;
					case 6:
						$debito = $grantotalLinea;
						$MontoSysDB = ($debito / $TasaLocSys);
						break;
					case 7:
						$debito = $grantotalLinea;
						$MontoSysDB = ($debito / $TasaLocSys);
						break;
				}


				$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

					':ac1_trans_id' => $resInsertAsiento,
					':ac1_account' => $cuenta,
					':ac1_debit' => round($debito, $DECI_MALES),
					':ac1_credit' => round($credito, $DECI_MALES),
					':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
					':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['iei_docdate']) ? $Data['iei_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['iei_duedate']) ? $Data['iei_duedate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => is_numeric($Data['iei_doctype']) ? $Data['iei_doctype'] : 0,
					':ac1_accountvs' => 1,
					':ac1_doctype' => 18,
					':ac1_ref1' => "",
					':ac1_ref2' => "",
					':ac1_ref3' => "",
					':ac1_prc_code' => $prc,
					':ac1_uncode' => $unidad,
					':ac1_prj_code' => $proyecto,
					':ac1_rescon_date' => NULL,
					':ac1_recon_total' => 0,
					':ac1_made_user' => isset($Data['iei_createby']) ? $Data['iei_createby'] : NULL,
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
					':ac1_legal_num' => isset($Data['iei_cardcode']) ? $Data['iei_cardcode'] : NULL,
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
						'mensaje'	=> 'No se pudo registrar la entrada de inventariio'
					);

					$this->response($respuesta);

					return;
				}
			}
			//FIN PROCEDIMIENTO PARA LLENAR CUENTA GRUPO

			// $sqlmac1 = "SELECT * FROM  mac1 WHERE ac1_trans_id = :ac1_trans_id";
			// $ressqlmac1 = $this->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $resInsertAsiento ));
			// print_r(json_encode($ressqlmac1));
			// exit;
		

			//SE VALIDA LA CONTABILIDAD CREADA
			$validateCont = $this->generic->validateAccountingAccent($resInsertAsiento);


			if (isset($validateCont['error']) && $validateCont['error'] == false) {
			} else {

				$ressqlmac1 = [];
				$sqlmac1 = "SELECT acc_name,ac1_account,ac1_debit,ac1_credit FROM  mac1 inner join dacc on ac1_account = acc_code WHERE ac1_trans_id = :ac1_trans_id";
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
			//



			// Si todo sale bien despues de insertar el detalle de la entrada de
			// se confirma la trasaccion  para que los cambios apliquen permanentemente
			// en la base de datos y se confirma la operacion exitosa.
			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'Entrada registrada con exito'
			);
		} else {
			// Se devuelven los cambios realizados en la transaccion
			// si occurre un error  y se muestra devuelve el error.
			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data' => $resInsert,
				'mensaje'	=> 'No se pudo registrar la entrada'
			);
		}

		$this->response($respuesta);
	}



	//OBTENER ENTRADAS DE
	public function getInventoryEntry_get()
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

		$sqlSelect = "SELECT
					t0.iei_docentry,
					t0.iei_currency,
					t2.mdt_docname,
					t0.iei_docnum,
					t0.iei_docdate,
					t0.iei_cardname,
					t0.iei_comment,
					CONCAT(T0.iei_currency,' ',to_char(t0.iei_baseamnt,'999,999,999,999.00')) iei_baseamnt,
					CONCAT(T0.iei_currency,' ',to_char(t0.iei_doctotal,'999,999,999,999.00')) iei_doctotal,
					t1.mev_names iei_slpcode
					FROM miei t0
					LEFT JOIN dmev t1 on t0.iei_slpcode = t1.mev_id
					LEFT JOIN dmdt t2 on t0.iei_doctype = t2.mdt_doctype
					WHERE t0.business = :business";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':business' => $Data['business']));

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


	//OBTENER ENTRADA DE  POR ID
	public function getInventoryEntryById_get()
	{

		$Data = $this->get();

		if (!isset($Data['iei_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM miei WHERE iei_docentry =:iei_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":iei_docentry" => $Data['iei_docentry']));

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


	//OBTENER ENTADA DE  DETALLE POR ID
	public function getInventoryEntryDetail_get()
	{

		$Data = $this->get();

		if (!isset($Data['ei1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT iei1.*, dmws.dws_name FROM iei1 INNER JOIN dmws ON dmws.dws_code = iei1.ei1_whscode WHERE ei1_docentry =:ei1_docentry AND dmws.business = :business";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":ei1_docentry" => $Data['ei1_docentry'], ':business' => $Data['business']));

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



	//OBTENER ENTRADAS DE  POR SOCIO DE NEGOCIO
	public function getInventoryEntryBySN_get()
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

		$sqlSelect = " SELECT * FROM miei WHERE iei_cardcode =:iei_cardcode AND business = :business AND branch = :branch";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":iei_cardcode" => $Data['dms_card_code']));

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
}
