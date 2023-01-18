<?php
// SALIDAS DE INVENTARIO
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class ExitInventory extends REST_Controller
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

	//CREAR NUEVA SALIDA
	public function createExitInventory_post()
	{

		$DECI_MALES =  $this->generic->getDecimals();

		$Data = $this->post();


		if (!isset($Data['business'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}
		
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
		$ManejaSerial = 0;
		$ManejaUbicacion = 0;
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
				'mensaje' => 'No se encontro el detalle de la salida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}
		//
		//VALIDANDO PERIODO CONTABLE
		$periodo = $this->generic->ValidatePeriod($Data['isi_docdate'], $Data['isi_docdate'], $Data['isi_docdate'], 0);

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

		$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['isi_series']));

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
		$resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $Data['isi_docdate']));

		if (isset($resBusTasa[0])) {
		} else {

			if (trim($Data['isi_currency']) != $MONEDALOCAL) {

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'No se encrontro la tasa de cambio para la moneda: ' . $Data['isi_currency'] . ' en la actual fecha del documento: ' . $Data['isi_docdate'] . ' y la moneda local: ' . $resMonedaLoc[0]['pgm_symbol']
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
		}

		$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
		$resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $Data['isi_docdate']));

		if (isset($resBusTasa2[0])) {
		} else {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :' . $Data['isi_docdate']
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$TasaDocLoc = isset($resBusTasa[0]['tsa_value']) ? $resBusTasa[0]['tsa_value'] : 1;
		$TasaLocSys = $resBusTasa2[0]['tsa_value'];

		// FIN DEL PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO


		$sqlInsert = "INSERT INTO misi (isi_docnum, isi_docdate, isi_duedate, isi_duedev, isi_pricelist, isi_cardcode, isi_cardname, isi_contacid, isi_slpcode, isi_empid, isi_comment, isi_doctotal, isi_baseamnt,
                      isi_taxtotal, isi_discprofit, isi_discount, isi_createat, isi_baseentry, isi_basetype, isi_doctype, isi_idadd, isi_adress, isi_paytype,
                      isi_series, isi_createby, isi_currency, business)
                      VALUES
                      (:isi_docnum, :isi_docdate, :isi_duedate, :isi_duedev, :isi_pricelist, :isi_cardcode, :isi_cardname, :isi_contacid, :isi_slpcode, :isi_empid, :isi_comment, :isi_doctotal, :isi_baseamnt,
                      :isi_taxtotal, :isi_discprofit, :isi_discount, :isi_createat, :isi_baseentry, :isi_basetype, :isi_doctype, :isi_idadd, :isi_adress, :isi_paytype,
                      :isi_series, :isi_createby, :isi_currency, :business)";


		// Se Inicia la transaccion,
		// Todas las consultas de modificacion siguientes
		// aplicaran solo despues que se confirme la transaccion,
		// de lo contrario no se aplicaran los cambios y se devolvera
		// la base de datos a su estado original.

		$this->pedeo->trans_begin();

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(

			':isi_docnum' => $DocNumVerificado,
			':isi_docdate'  => $this->validateDate($Data['isi_docdate']) ? $Data['isi_docdate'] : NULL,
			':isi_duedate' => $this->validateDate($Data['isi_duedate']) ? $Data['isi_duedate'] : NULL,
			':isi_duedev' => $this->validateDate($Data['isi_duedev']) ? $Data['isi_duedev'] : NULL,
			':isi_pricelist' => is_numeric($Data['isi_pricelist']) ? $Data['isi_pricelist'] : 0,
			':isi_cardcode' => isset($Data['isi_cardcode']) ? $Data['isi_cardcode'] : NULL,
			':isi_cardname' => isset($Data['isi_cardname']) ? $Data['isi_cardname'] : NULL,
			':isi_contacid' => isset($Data['isi_contacid']) ? $Data['isi_contacid'] : NULL,
			':isi_slpcode' => is_numeric($Data['isi_slpcode']) ? $Data['isi_slpcode'] : 0,
			':isi_empid' => is_numeric($Data['isi_empid']) ? $Data['isi_empid'] : 0,
			':isi_comment' => isset($Data['isi_comment']) ? $Data['isi_comment'] : NULL,
			':isi_doctotal' => is_numeric($Data['isi_doctotal']) ? $Data['isi_doctotal'] : NULL,
			':isi_baseamnt' => is_numeric($Data['isi_baseamnt']) ? $Data['isi_baseamnt'] : NULL,
			':isi_taxtotal' => is_numeric($Data['isi_taxtotal']) ? $Data['isi_taxtotal'] : NULL,
			':isi_discprofit' => is_numeric($Data['isi_discprofit']) ? $Data['isi_discprofit'] : 0,
			':isi_discount' => is_numeric($Data['isi_discount']) ? $Data['isi_discount'] : NULL,
			':isi_createat' => $this->validateDate($Data['isi_createat']) ? $Data['isi_createat'] : NULL,
			':isi_baseentry' => is_numeric($Data['isi_baseentry']) ? $Data['isi_baseentry'] : NULL,
			':isi_basetype' => is_numeric($Data['isi_basetype']) ? $Data['isi_basetype'] : NULL,
			':isi_doctype' => is_numeric($Data['isi_doctype']) ? $Data['isi_doctype'] : NULL,
			':isi_idadd' => isset($Data['isi_idadd']) ? $Data['isi_idadd'] : NULL,
			':isi_adress' => isset($Data['isi_adress']) ? $Data['isi_adress'] : NULL,
			':isi_paytype' => is_numeric($Data['isi_paytype']) ? $Data['isi_paytype'] : NULL,
			':isi_series' => is_numeric($Data['isi_series']) ? $Data['isi_series'] : 0,
			':isi_createby' => isset($Data['isi_createby']) ? $Data['isi_createby'] : NULL,
			':isi_currency' => isset($Data['isi_currency']) ? $Data['isi_currency'] : NULL,
			':business' => $Data['business']

		));

		if (is_numeric($resInsert) && $resInsert > 0) {

			//Se agregan los asientos contables*/*******

			$sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user, business, branch)
								VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user, :business, :branch)";


			$resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

				':mac_doc_num' => 1,
				':mac_status' => 1,
				':mac_base_type' => is_numeric($Data['isi_doctype']) ? $Data['isi_doctype'] : 0,
				':mac_base_entry' => $resInsert,
				':mac_doc_date' => $this->validateDate($Data['isi_docdate']) ? $Data['isi_docdate'] : NULL,
				':mac_doc_duedate' => $this->validateDate($Data['isi_duedate']) ? $Data['isi_duedate'] : NULL,
				':mac_legal_date' => $this->validateDate($Data['isi_docdate']) ? $Data['isi_docdate'] : NULL,
				':mac_ref1' => is_numeric($Data['isi_doctype']) ? $Data['isi_doctype'] : 0,
				':mac_ref2' => "",
				':mac_ref3' => "",
				':mac_loc_total' => is_numeric($Data['isi_doctotal']) ? $Data['isi_doctotal'] : 0,
				':mac_fc_total' => is_numeric($Data['isi_doctotal']) ? $Data['isi_doctotal'] : 0,
				':mac_sys_total' => is_numeric($Data['isi_doctotal']) ? $Data['isi_doctotal'] : 0,
				':mac_trans_dode' => 1,
				':mac_beline_nume' => 1,
				':mac_vat_date' => $this->validateDate($Data['isi_docdate']) ? $Data['isi_docdate'] : NULL,
				':mac_serie' => 1,
				':mac_number' => 1,
				':mac_bammntsys' => is_numeric($Data['isi_baseamnt']) ? $Data['isi_baseamnt'] : 0,
				':mac_bammnt' => is_numeric($Data['isi_baseamnt']) ? $Data['isi_baseamnt'] : 0,
				':mac_wtsum' => 1,
				':mac_vatsum' => is_numeric($Data['isi_taxtotal']) ? $Data['isi_taxtotal'] : 0,
				':mac_comments' => isset($Data['isi_comment']) ? $Data['isi_comment'] : NULL,
				':mac_create_date' => $this->validateDate($Data['isi_createat']) ? $Data['isi_createat'] : NULL,
				':mac_made_usuer' => isset($Data['isi_createby']) ? $Data['isi_createby'] : NULL,
				':mac_update_date' => date("Y-m-d"),
				':mac_update_user' => isset($Data['isi_createby']) ? $Data['isi_createby'] : NULL,
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
					'mensaje'	=> 'No se pudo registrar la salida de inventario'
				);

				$this->response($respuesta);

				return;
			}

			// Se actualiza la serie de la numeracion del documento

			$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id'      => $Data['isi_series']
			));


			if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
			} else {
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'    => $resActualizarNumeracion,
					'mensaje'	=> 'No se pudo crear la salida'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			// Fin de la actualizacion de la numeracion del documento



			foreach ($ContenidoDetalle as $key => $detail) {

				$sqlInsertDetail = "INSERT INTO isi1 (si1_docentry, si1_itemcode, si1_itemname, si1_quantity, si1_uom, si1_whscode, si1_price, si1_vat, si1_vatsum, si1_discount, si1_linetotal,
									si1_costcode, si1_ubusiness,si1_project, si1_acctcode, si1_basetype, si1_doctype, si1_avprice, si1_inventory, si1_linenum, si1_acciva,si1_concept, si1_ubication)
									VALUES
									(:si1_docentry, :si1_itemcode, :si1_itemname, :si1_quantity, :si1_uom, :si1_whscode, :si1_price, :si1_vat, :si1_vatsum, :si1_discount, :si1_linetotal,
										:si1_costcode, :si1_ubusiness,:si1_project, :si1_acctcode, :si1_basetype, :si1_doctype, :si1_avprice, :si1_inventory, :si1_linenum, :si1_acciva,:si1_concept, :si1_ubication)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(

					':si1_docentry'  => $resInsert,
					':si1_itemcode' => isset($detail['si1_itemcode']) ? $detail['si1_itemcode'] : NULL,
					':si1_itemname' => isset($detail['si1_itemname']) ? $detail['si1_itemname'] : NULL,
					':si1_quantity' => is_numeric($detail['si1_quantity']) ? $detail['si1_quantity'] : 0,
					':si1_uom' => isset($detail['si1_uom']) ? $detail['si1_uom'] : NULL,
					':si1_whscode' => isset($detail['si1_whscode']) ? $detail['si1_whscode'] : NULL,
					':si1_price' => is_numeric($detail['si1_price']) ? $detail['si1_price'] : 0,
					':si1_vat' => is_numeric($detail['si1_vat']) ? $detail['si1_vat'] : 0,
					':si1_vatsum' => is_numeric($detail['si1_vatsum']) ? $detail['si1_vatsum'] : 0,
					':si1_discount' => is_numeric($detail['si1_discount']) ? $detail['si1_discount'] : 0,
					':si1_linetotal' => is_numeric($detail['si1_linetotal']) ? $detail['si1_linetotal'] : 0,
					':si1_costcode' => isset($detail['si1_costcode']) ? $detail['si1_costcode'] : NULL,
					':si1_ubusiness' => isset($detail['si1_ubusiness']) ? $detail['si1_ubusiness'] : NULL,
					':si1_project' => isset($detail['si1_project']) ? $detail['si1_project'] : NULL,
					':si1_acctcode' => is_numeric($detail['si1_acctcode']) ? $detail['si1_acctcode'] : 0,
					':si1_basetype' => is_numeric($detail['si1_basetype']) ? $detail['si1_basetype'] : 0,
					':si1_doctype' => is_numeric($detail['si1_doctype']) ? $detail['si1_doctype'] : 0,
					':si1_avprice' => is_numeric($detail['si1_avprice']) ? $detail['si1_avprice'] : 0,
					':si1_inventory' => is_numeric($detail['si1_inventory']) ? $detail['si1_inventory'] : 0,
					':si1_linenum' => is_numeric($detail['si1_linenum']) ? $detail['si1_linenum'] : 0,
					':si1_acciva' => is_numeric($detail['si1_acciva']) ? $detail['si1_acciva'] : 0,
					':si1_concept' => is_numeric($detail['si1_concept']) ? $detail['si1_concept'] : 0,
					':si1_ubication' => isset($detail['si1_ubication']) ? $detail['si1_ubication'] : 0
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
						'mensaje'	=> 'No se pudo registrar la salida '
					);

					$this->response($respuesta);

					return;
				}


				// si el item es inventariable
				// SE VERIFICA SI EL ARTICULO ESTA MARCADO PARA MANEJARSE EN INVENTARIO
				// Y A SU VES SI MANEJA LOTE
				$sqlItemINV = "SELECT dma_item_inv FROM dmar WHERE dma_item_code = :dma_item_code AND dma_item_inv = :dma_item_inv";
				$resItemINV = $this->pedeo->queryTable($sqlItemINV, array(

					':dma_item_code' => $detail['si1_itemcode'],
					':dma_item_inv'  => 1
				));

				if (isset($resItemINV[0])) {


					// CONSULTA PARA VERIFICAR SI EL ALMACEN MANEJA UBICACION
					$sqlubicacion = "SELECT * FROM dmws WHERE dws_ubication = :dws_ubication AND dws_code = :dws_code AND business = :business";
					$resubicacion = $this->pedeo->queryTable($sqlubicacion, array(
						':dws_ubication' => 1,
						':dws_code' => $detail['si1_whscode'],
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
	
						':dma_item_code' => $detail['si1_itemcode'],
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

						':dma_item_code' => $detail['si1_itemcode'],
						':dma_series_code'  => 1
					));

					if (isset($resItemSerial[0])) {
						$ManejaSerial = 1;

						if (!isset($detail['serials'])) {
							$respuesta = array(
								'error'   => true,
								'data'    => [],
								'mensaje' => 'No se encontraron los seriales para el articulo: ' . $detail['si1_itemcode']
							);

							$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

							return;
						}

						$AddSerial = $this->generic->addSerial($detail['serials'], $detail['si1_itemcode'], $Data['isi_doctype'], $resInsert, $DocNumVerificado, $Data['isi_docdate'], 2, $Data['isi_comment'], $detail['si1_whscode'], $detail['si1_quantity'], $Data['isi_createby'], $Data['business']);

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
					// para almacenar en el movimiento de inventario
					//SI EL ARTICULO MANEJA LOTE SE BUSCA POR LOTE Y ALMACEN
					$sqlCostoMomentoRegistro = '';
					$resCostoMomentoRegistro = [];

					// SI EL ALMACEN MANEJA UBICACION
					if ( $ManejaUbicacion == 1 ){
						// SI EL ARTICULO MANEJA LOTE
						if ($ManejaLote == 1) {
							$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_lote = :bdi_lote AND bdi_ubication = :bdi_ubication AND business = :business";
							$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
								':bdi_whscode'   => $detail['si1_whscode'],
								':bdi_itemcode'  => $detail['si1_itemcode'],
								':bdi_lote'      => $detail['ote_code'],
								':bdi_ubication' => $detail['si1_ubication'],
								':business' 	 => $Data['business']
							));
						} else {
							$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_ubication = :bdi_ubication AND business = :business";
							$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
								':bdi_whscode'   => $detail['si1_whscode'],
								':bdi_itemcode'  => $detail['si1_itemcode'],
								':bdi_ubication' => $detail['si1_ubication'],
								':business' 	 => $Data['business']
							));
						}
					}else{
						// SI EL ARTICULO MANEJA LOTE
						if ($ManejaLote == 1) {
							$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_lote = :bdi_lote AND business = :business";
							$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
								':bdi_whscode'  => $detail['si1_whscode'],
								':bdi_itemcode' => $detail['si1_itemcode'],
								':bdi_lote' 	=> $detail['ote_code'],
								':business' 	=> $Data['business']
							));
						} else {
							$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND business = :business";
							$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
								':bdi_whscode' 	=> $detail['si1_whscode'],
								':bdi_itemcode' => $detail['si1_itemcode'],
								':business' 	=> $Data['business']
							));
						}
					}




					if (isset($resCostoMomentoRegistro[0])) {


						//VALIDANDO CANTIDAD DE ARTICULOS

						$CANT_ARTICULOEX = $resCostoMomentoRegistro[0]['bdi_quantity'];
						$CANT_ARTICULOLN = is_numeric($detail['si1_quantity']) ? $detail['si1_quantity'] : 0;

						if (($CANT_ARTICULOEX - $CANT_ARTICULOLN) < 0) {

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' => [],
								'mensaje'	=> 'no puede crear el documento porque el articulo ' . $detail['si1_itemcode'] . ' recae en inventario negativo (' . ($CANT_ARTICULOEX - $CANT_ARTICULOLN) . ')'
							);

							$this->response($respuesta);

							return;
						}

						//VALIDANDO CANTIDAD DE ARTICULOS

						$sqlInserMovimiento = '';
						$resInserMovimiento = [];

					
						//Se aplica el movimiento de inventario
						$sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode,bmi_quantity,bmi_whscode,bmi_createat,bmi_createby,bmy_doctype,bmy_baseentry,bmi_cost,bmi_currequantity,bmi_basenum,bmi_docdate,bmi_duedate,bmi_duedev,bmi_comment, bmi_lote, bmi_ubication)
											VALUES (:bmi_itemcode,:bmi_quantity, :bmi_whscode,:bmi_createat,:bmi_createby,:bmy_doctype,:bmy_baseentry,:bmi_cost,:bmi_currequantity,:bmi_basenum,:bmi_docdate,:bmi_duedate,:bmi_duedev,:bmi_comment,:bmi_lote, :bmi_ubication)";

						$resInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(

							':bmi_itemcode' => isset($detail['si1_itemcode']) ? $detail['si1_itemcode'] : NULL,
							':bmi_quantity' => is_numeric($detail['si1_quantity']) ? $detail['si1_quantity'] * $Data['invtype'] : 0,
							':bmi_whscode'  => isset($detail['si1_whscode']) ? $detail['si1_whscode'] : NULL,
							':bmi_createat' => $this->validateDate($Data['isi_createat']) ? $Data['isi_createat'] : NULL,
							':bmi_createby' => isset($Data['isi_createby']) ? $Data['isi_createby'] : NULL,
							':bmy_doctype'  => is_numeric($Data['isi_doctype']) ? $Data['isi_doctype'] : 0,
							':bmy_baseentry' => $resInsert,
							':bmi_cost'      => $resCostoMomentoRegistro[0]['bdi_avgprice'],
							':bmi_currequantity' => $resCostoMomentoRegistro[0]['bdi_quantity'],
							':bmi_basenum'			=> $DocNumVerificado,
							':bmi_docdate' => $this->validateDate($Data['isi_docdate']) ? $Data['isi_docdate'] : NULL,
							':bmi_duedate' => $this->validateDate($Data['isi_duedate']) ? $Data['isi_duedate'] : NULL,
							':bmi_duedev'  => $this->validateDate($Data['isi_duedev']) ? $Data['isi_duedev'] : NULL,
							':bmi_comment' => isset($Data['isi_comment']) ? $Data['isi_comment'] : NULL,
							':bmi_lote' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
							':bmi_ubication' => isset($detail['si1_ubication']) ? $detail['si1_ubication'] : NULL
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
								'mensaje'	=> 'No se pudo registra la salida de inventario'
							);

							$this->response($respuesta);

							return;
						}
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' => $resCostoMomentoRegistro,
							'mensaje'	=> 'No se pudo relizar el movimiento, no se encontro el costo del articulo'
						);

						$this->response($respuesta);

						return;
					}

					//FIN aplicacion de movimiento de inventario


					//Se Aplica el movimiento en stock ***************
					// Buscando item en el stock
					//SE VALIDA SI EL ARTICULO MANEJA LOTE
					$sqlCostoCantidad = '';
					$resCostoCantidad = [];

					// SI EL ALMACEN MANEJA UBICACION
					if ( $ManejaUbicacion == 1 ){
						// SI EL ARTICULO MANEJA LOTE
						if ($ManejaLote == 1) {
							$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
											FROM tbdi
											WHERE bdi_itemcode = :bdi_itemcode
											AND bdi_whscode = :bdi_whscode
											AND bdi_lote = :bdi_lote
											AND bdi_ubication = :bdi_ubication
											AND business = :business";
	
							$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(
	
								':bdi_itemcode'  => $detail['si1_itemcode'],
								':bdi_whscode'   => $detail['si1_whscode'],
								':bdi_lote'		 => $detail['ote_code'],
								':bdi_ubication' => $detail['si1_ubication'],
								':business' 	 => $Data['business']
							));
						} else {
							$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
							FROM tbdi
							WHERE bdi_itemcode = :bdi_itemcode
							AND bdi_whscode = :bdi_whscode
							AND bdi_ubication = :bdi_ubication
							AND business = :business";

							$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(

								':bdi_itemcode'  => $detail['si1_itemcode'],
								':bdi_whscode'   => $detail['si1_whscode'],
								':bdi_ubication' => $detail['si1_ubication'],
								':business' 	 => $Data['business']
							));
						}
					}else{
						// SI EL ARTICULO MANEJA LOTE
						if ($ManejaLote == 1) {
							$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
											FROM tbdi
											WHERE bdi_itemcode = :bdi_itemcode
											AND bdi_whscode = :bdi_whscode
											AND bdi_lote = :bdi_lote
											AND business = :business";
	
							$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(
	
								':bdi_itemcode' => $detail['si1_itemcode'],
								':bdi_whscode'  => $detail['si1_whscode'],
								':bdi_lote'		=> $detail['ote_code'],
								':business' 	=> $Data['business']
							));
						} else {
							$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
											FROM tbdi
											WHERE bdi_itemcode = :bdi_itemcode
											AND bdi_whscode = :bdi_whscode
											AND business = :business";
	
							$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(
	
								':bdi_itemcode' => $detail['si1_itemcode'],
								':bdi_whscode'  => $detail['si1_whscode'],
								':business' => $Data['business']
							));
						}
					}


					if (isset($resCostoCantidad[0])) {

						$CantidadActual = $resCostoCantidad[0]['bdi_quantity'];
						$CostoActual    = $resCostoCantidad[0]['bdi_avgprice'];

						$CantidadDevolucion = $detail['si1_quantity'];
						$CostoDevolucion = $detail['si1_price'];

						$CantidadTotal = ($CantidadActual - $CantidadDevolucion);

						// $CostoPonderado = (($CostoActual * $CantidadActual) + ($CostoDevolucion * $CantidadDevolucion)) / $CantidadTotal;
						// NO SE MUEVE EL COSTO PONDERADO
						$sqlUpdateCostoCantidad =  "UPDATE tbdi
												SET bdi_quantity = :bdi_quantity
												WHERE  bdi_id = :bdi_id";

						$resUpdateCostoCantidad = $this->pedeo->updateRow($sqlUpdateCostoCantidad, array(

							':bdi_quantity' => $CantidadTotal,
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
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' 		=> $resCostoCantidad,
							'mensaje'	=> 'El item no existe en el stock ' . $detail['si1_itemcode']
						);

						$this->response($respuesta);

						return;
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
								':ote_createby' => $Data['isi_createby'],
								':ote_date' => date('Y-m-d'),
								':ote_baseentry' => $resInsert,
								':ote_basetype' => $Data['isi_doctype'],
								':ote_docnum' => $DocNumVerificado
							));

							if (is_numeric($resInsertLote) && $resInsertLote > 0) {
							} else {
								$this->pedeo->trans_rollback();
								$respuesta = array(
									'error'   => true,
									'data' 		=> $resInsertLote,
									'mensaje'	=> 'No se pudo registrar la salida de inventario'
								);

								$this->response($respuesta);
								return;
							}
						}
					}
					//FIN VALIDACION DEL LOTE
				}




				//LLENANDO AGRUPADOS
				$DetalleCuentaLineaDocumento = new stdClass();
				$DetalleCuentaGrupo = new stdClass();

				$DetalleCuentaLineaDocumento->si1_acctcode = is_numeric($detail['si1_acctcode']) ? $detail['si1_acctcode'] : 0;
				$DetalleCuentaLineaDocumento->si1_costcode = isset($detail['si1_costcode']) ? $detail['si1_costcode'] : NULL;
				$DetalleCuentaLineaDocumento->si1_ubusiness = isset($detail['si1_ubusiness']) ? $detail['si1_ubusiness'] : NULL;
				$DetalleCuentaLineaDocumento->si1_project = isset($detail['si1_project']) ? $detail['si1_project'] : NULL;
				$DetalleCuentaLineaDocumento->si1_linetotal = is_numeric($detail['si1_linetotal']) ? $detail['si1_linetotal'] : 0;
				$DetalleCuentaLineaDocumento->si1_vat = is_numeric($detail['si1_vat']) ? $detail['si1_vat'] : 0;
				$DetalleCuentaLineaDocumento->si1_vatsum = is_numeric($detail['si1_vatsum']) ? $detail['si1_vatsum'] : 0;
				$DetalleCuentaLineaDocumento->si1_price = is_numeric($detail['si1_price']) ? $detail['si1_price'] : 0;
				$DetalleCuentaLineaDocumento->si1_itemcode = isset($detail['si1_itemcode']) ? $detail['si1_itemcode'] : NULL;
				$DetalleCuentaLineaDocumento->si1_quantity = is_numeric($detail['si1_quantity']) ? $detail['si1_quantity'] : 0;
				$DetalleCuentaLineaDocumento->si1_whscode = isset($detail['si1_whscode']) ? $detail['si1_whscode'] : NULL;




				$codigoCuenta = substr($DetalleCuentaLineaDocumento->si1_acctcode, 0, 1);
				$llaveCuentaLineaDocumento = $DetalleCuentaLineaDocumento->si1_ubusiness . $DetalleCuentaLineaDocumento->si1_costcode . $DetalleCuentaLineaDocumento->si1_project . $DetalleCuentaLineaDocumento->si1_acctcode;

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

				$DetalleCuentaGrupo->si1_itemcode = isset($detail['si1_itemcode']) ? $detail['si1_itemcode'] : NULL;
				$DetalleCuentaGrupo->si1_whscode = isset($detail['si1_whscode']) ? $detail['si1_whscode'] : NULL;
				$DetalleCuentaGrupo->si1_acctcode = is_numeric($detail['si1_acctcode']) ? $detail['si1_acctcode'] : 0;
				$DetalleCuentaGrupo->si1_quantity = is_numeric($detail['si1_quantity']) ? $detail['si1_quantity'] : 0;
				$DetalleCuentaGrupo->si1_price = is_numeric($detail['si1_price']) ? $detail['si1_price'] : 0;
				$DetalleCuentaGrupo->si1_linetotal = is_numeric($detail['si1_linetotal']) ? $detail['si1_linetotal'] : 0;

				$llaveCuentaGrupo = $DetalleCuentaGrupo->si1_acctcode;
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

			//FIN DETALLE SALIDA


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
					$grantotalLinea = ($grantotalLinea + $value->si1_linetotal);
					$codigoCuentaLinea = $value->codigoCuenta;
					$prc = $value->si1_costcode;
					$unidad = $value->si1_ubusiness;
					$proyecto = $value->si1_project;
					$cuenta = $value->si1_acctcode;
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
					':ac1_doc_date' => $this->validateDate($Data['isi_docdate']) ? $Data['isi_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['isi_docdate']) ? $Data['isi_docdate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => is_numeric($Data['isi_doctype']) ? $Data['isi_doctype'] : 0,
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
					':ac1_made_user' => isset($Data['isi_createby']) ? $Data['isi_createby'] : NULL,
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
					':ac1_legal_num' => isset($Data['isi_cardcode']) ? $Data['isi_cardcode'] : NULL,
					':ac1_codref' => 0,
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
						'mensaje'	=> 'No se pudo registrar la factura de ventas'
					);

					$this->response($respuesta);

					return;
				}
			}
			//FIN PROCEDIMIENTO PARA LLENAR CUENTA LINEA DOCUMENTO


			// PROCEDIMIENTO PARA LLENAR CUENTA GRUPO

			foreach ($DetalleConsolidadoCuentaGrupo as $key => $posicion) {
				$grantotalCuentaGrupo = 0;
				$grantotalCuentaGrupoOriginal = 0;
				$cuentaGrupo = "";
				$dbito = 0;
				$cdito = 0;
				$MontoSysDB = 0;
				$MontoSysCR = 0;
				foreach ($posicion as $key => $value) {

					$CUENTASINV = $this->account->getAccountItem($value->si1_itemcode, $value->si1_whscode);

					if ( isset($CUENTASINV['error']) && $CUENTASINV['error'] == false ) {

						$cuentaGrupo = $CUENTASINV['data']['acct_inv'];
						$grantotalCuentaGrupo = ($grantotalCuentaGrupo + $value->si1_linetotal);
					} else {
						// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
						// se retorna el error y se detiene la ejecucion del codigo restante.
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'	  => $CUENTASINV,
							'mensaje'	=> 'No se encontro la cuenta del grupo de articulo para el item ' . $value->si1_itemcode
						);

						$this->response($respuesta);

						return;
					}
				}

				$codigo3 = substr($cuentaGrupo, 0, 1);

				$grantotalCuentaGrupoOriginal = $grantotalCuentaGrupo;


				if ($codigo3 == 1 || $codigo3 == "1") {
					$cdito = $grantotalCuentaGrupo;
					$MontoSysCR = ($cdito / $TasaLocSys);
				} else if ($codigo3 == 2 || $codigo3 == "2") {
					$cdito = $grantotalCuentaGrupo;
					$MontoSysCR = ($cdito / $TasaLocSys);
				} else if ($codigo3 == 3 || $codigo3 == "3") {
					$cdito = $grantotalCuentaGrupo;
					$MontoSysCR = ($cdito / $TasaLocSys);
				} else if ($codigo3 == 4 || $codigo3 == "4") {
					$cdito = $grantotalCuentaGrupo;
					$MontoSysCR = ($cdito / $TasaLocSys);
				} else if ($codigo3 == 5  || $codigo3 == "5") {
					$cdito = $grantotalCuentaGrupo;
					$MontoSysCR = ($cdito / $TasaLocSys);
				} else if ($codigo3 == 6 || $codigo3 == "6") {
					$cdito = $grantotalCuentaGrupo;
					$MontoSysCR = ($cdito / $TasaLocSys);
				} else if ($codigo3 == 7 || $codigo3 == "7") {
					$cdito = $grantotalCuentaGrupo;
					$MontoSysCR = ($cdito / $TasaLocSys);
				}

				$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

					':ac1_trans_id' => $resInsertAsiento,
					':ac1_account' => $cuentaGrupo,
					':ac1_debit' => round($dbito, $DECI_MALES),
					':ac1_credit' => round($cdito, $DECI_MALES),
					':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
					':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['isi_docdate']) ? $Data['isi_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['isi_docdate']) ? $Data['isi_docdate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => is_numeric($Data['isi_doctype']) ? $Data['isi_doctype'] : 0,
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
					':ac1_made_user' => isset($Data['isi_createby']) ? $Data['isi_createby'] : NULL,
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
					':ac1_legal_num' => isset($Data['isi_cardcode']) ? $Data['isi_cardcode'] : NULL,
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
						'mensaje'	=> 'No se pudo registrar la factura de ventas'
					);

					$this->response($respuesta);

					return;
				}
			}
			//FIN PROCEDIMIENTO PARA LLENAR CUENTA GRUPO


			//SE VALIDA LA CONTABILIDAD CREADA
			$validateCont = $this->generic->validateAccountingAccent($resInsertAsiento);


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

			// Si todo sale bien despues de insertar el detalle de la salida de inventaro
			// se confirma la trasaccion  para que los cambios apliquen permanentemente
			// en la base de datos y se confirma la operacion exitosa.
			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'Salida registrada con exito'
			);
		} else {
			// Se devuelven los cambios realizados en la transaccion
			// si occurre un error  y se muestra devuelve el error.
			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data' => $resInsert,
				'mensaje'	=> 'No se pudo registrar la salida'
			);
		}

		$this->response($respuesta);
	}




	//OBTENER SALIDAS DE INVETARIO
	public function getExitInventory_get()
	{

		$Data = $this->get();

		if ( !isset($Data['business']) ) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT
					t0.isi_docentry,
					t0.isi_currency,
					t2.mdt_docname,
					t0.isi_docnum,
					t0.isi_docdate,
					t0.isi_cardname,
					t0.isi_comment,
					CONCAT(T0.isi_currency,' ',to_char(t0.isi_baseamnt,'999,999,999,999.00')) isi_baseamnt,
					CONCAT(T0.isi_currency,' ',to_char(t0.isi_doctotal,'999,999,999,999.00')) isi_doctotal,
					t1.mev_names isi_slpcode
					FROM misi t0
					LEFT JOIN dmev t1 on t0.isi_slpcode = t1.mev_id
					LEFT JOIN dmdt t2 on t0.isi_doctype = t2.mdt_doctype
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


	//OBTENER SALIDA DE INVENTARIO  POR ID
	public function getExitInventoryById_get()
	{

		$Data = $this->get();

		if (!isset($Data['isi_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM misi WHERE isi_docentry =:isi_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":isi_docentry" => $Data['isi_docentry']));

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
	public function getExitInventoryDetail_get()
	{

		$Data = $this->get();

		if (!isset($Data['si1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT isi1.*, dmws.dws_name FROM isi1 INNER JOIN dmws ON dmws.dws_code = isi1.si1_whscode WHERE si1_docentry =:si1_docentry AND dmws.business = :business";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":si1_docentry" => $Data['si1_docentry'], ':business' => $Data['business']));

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



	//OBTENER SALIDAS DE INVENTARIO  POR SOCIO DE NEGOCIO
	public function getExitInventoryBySN_get()
	{

		$Data = $this->get();

		if ( !isset($Data['dms_card_code']) OR !isset($Data['business']) OR !isset($Data['branch'] )) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM misi WHERE isi_cardcode =:isi_cardcode AND business = :business AND branch = :branch";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":isi_cardcode" => $Data['dms_card_code'], ":business" => $Data['business'], ":branch" => $Data['branch']));

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









	private function getUrl($data, $caperta)
	{
		$url = "";

		if ($data == NULL) {

			return $url;
		}

		if (!base64_decode($data, true)) {
			return $url;
		}

		$ruta = '/var/www/html/' . $caperta . '/assets/img/anexos/';

		$milliseconds = round(microtime(true) * 1000);


		$nombreArchivo = $milliseconds . ".pdf";

		touch($ruta . $nombreArchivo);

		$file = fopen($ruta . $nombreArchivo, "wb");

		if (!empty($data)) {

			fwrite($file, base64_decode($data));

			fclose($file);

			$url = "assets/img/anexos/" . $nombreArchivo;
		}

		return $url;
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
