<?php
// REVALORIZACION DE INVENTARIO

defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class InventoryRevaluation extends REST_Controller
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

	//CREAR NUEVA REVALORIZACION
	public function getinventoryRevaluation_get()
	{
		$sqlSelect = " SELECT * FROM diri";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array());

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data' => $resSelect,
				'mensaje' => ''
			);

		} else {

			$respuesta = array(
				'error' => true,
				'data' => array(),
				'mensaje' => 'busqueda sin resultados'
			);

		}
		$this->response($respuesta);
	}

	public function createRevaluation_post()
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
		$periodo = $this->generic->ValidatePeriod($Data['iri_docdate'], $Data['iri_docdate'], $Data['iri_docdate'], 0);

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

		$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['iri_series']));

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
		$resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $Data['iri_currency'], ':tsa_date' => $Data['iri_docdate']));

		if (isset($resBusTasa[0])) {
		} else {

			if (trim($Data['iri_currency']) != $MONEDALOCAL) {

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'No se encrontro la tasa de cambio para la moneda: ' . $Data['iri_currency'] . ' en la actual fecha del documento: ' . $Data['iri_docdate'] . ' y la moneda local: ' . $resMonedaLoc[0]['pgm_symbol']
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
		}

		$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
		$resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $Data['iri_docdate']));

		if (isset($resBusTasa2[0])) {
		} else {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :' . $Data['iri_docdate']
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$TasaDocLoc = isset($resBusTasa[0]['tsa_value']) ? $resBusTasa[0]['tsa_value'] : 1;
		$TasaLocSys = $resBusTasa2[0]['tsa_value'];

		// FIN DEL PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO

		$sqlInset = "INSERT INTO diri (iri_docnum,iri_doctype, iri_series, iri_cardcode, iri_cardname, iri_docdate, iri_duedate, iri_duedev, iri_comment, 
									   iri_currency, iri_slpcode, iri_empid, business, branch)
									   VALUES
									  (:iri_docnum, :iri_doctype, :iri_series, :iri_cardcode, :iri_cardname, :iri_docdate, :iri_duedate, :iri_duedev, :iri_comment,
									  :iri_currency, :iri_slpcode, :iri_empid, :business, :branch)";
		$this->pedeo->trans_begin();
		$resInsert = $this->pedeo->insertRow($sqlInset, array(
			":iri_docnum" => $DocNumVerificado,
			":iri_doctype" => $Data['iri_doctype'],
			":iri_series" => $Data['iri_series'],
			":iri_cardcode" => $Data['iri_cardcode'],
			":iri_cardname" => $Data['iri_cardname'],
			":iri_docdate" => $Data['iri_docdate'],
			":iri_duedate" => $Data['iri_duedate'],
			":iri_duedev" => $Data['iri_duedev'],
			":iri_comment" => $Data['iri_comment'],
			":iri_currency" => $Data['iri_currency'],
			":iri_slpcode" => $Data['iri_slpcode'],
			":iri_empid" => $Data['iri_empid'],
			":business" => $Data['business'],
			":branch" => $Data['branch']
		)
		);

		if (is_numeric($resInsert) && $resInsert > 0) {

			//Se agregan los asientos contables*/*******

			$sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user, business, branch)
								VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user, :business, :branch)";


			$resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

				':mac_doc_num' => 1,
				':mac_status' => 1,
				':mac_base_type' => is_numeric($Data['iri_doctype']) ? $Data['iri_doctype'] : 0,
				':mac_base_entry' => $resInsert,
				':mac_doc_date' => $this->validateDate($Data['iri_docdate']) ? $Data['iri_docdate'] : NULL,
				':mac_doc_duedate' => $this->validateDate($Data['iri_duedate']) ? $Data['iri_duedate'] : NULL,
				':mac_legal_date' => $this->validateDate($Data['iri_docdate']) ? $Data['iri_docdate'] : NULL,
				':mac_ref1' => is_numeric($Data['iri_doctype']) ? $Data['iri_doctype'] : 0,
				':mac_ref2' => "",
				':mac_ref3' => "",
				':mac_loc_total' => is_numeric($Data['iri_doctotal']) ? $Data['iri_doctotal'] : 0,
				':mac_fc_total' => is_numeric($Data['iri_doctotal']) ? $Data['iri_doctotal'] : 0,
				':mac_sys_total' => is_numeric($Data['iri_doctotal']) ? $Data['iri_doctotal'] : 0,
				':mac_trans_dode' => 1,
				':mac_beline_nume' => 1,
				':mac_vat_date' => $this->validateDate($Data['iri_docdate']) ? $Data['iri_docdate'] : NULL,
				':mac_serie' => 1,
				':mac_number' => 1,
				':mac_bammntsys' => is_numeric($Data['iri_baseamnt']) ? $Data['iri_baseamnt'] : 0,
				':mac_bammnt' => is_numeric($Data['iri_baseamnt']) ? $Data['iri_baseamnt'] : 0,
				':mac_wtsum' => 1,
				':mac_vatsum' => is_numeric($Data['iri_taxtotal']) ? $Data['iri_taxtotal'] : 0,
				':mac_comments' => isset($Data['iri_comment']) ? $Data['iri_comment'] : NULL,
				':mac_create_date' => $this->validateDate($Data['iri_createat']) ? $Data['iri_createat'] : NULL,
				':mac_made_usuer' => isset($Data['iri_createby']) ? $Data['iri_createby'] : NULL,
				':mac_update_date' => date("Y-m-d"),
				':mac_update_user' => isset($Data['iri_createby']) ? $Data['iri_createby'] : NULL,
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
					'mensaje'	=> 'No se pudo registrar la revalorización de inventario'
				);

				$this->response($respuesta);

				return;
			}
			// Se actualiza la serie de la numeracion del documento
			$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id'      => $Data['iri_series']
			));


			if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
			} else {
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'    => $resActualizarNumeracion,
					'mensaje'	=> 'No se pudo crear la revalorización  '
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			// Fin de la actualizacion de la numeracion del documento

			foreach ($ContenidoDetalle as $key => $detail) {
				
				$sqlDetail = "INSERT INTO iri1 (ri1_itemcode,ri1_itemname,ri1_whscode,ri1_quantity,ri1_actualcost,ri1_newcost,ri1_increase_account,ri1_declining_account,ri1_costcode,ri1_ubusiness,ri1_project, ri1_docentry, ri1_ubication, ri1_linetotal) VALUES
						(:ri1_itemcode,:ri1_itemname,:ri1_whscode,:ri1_quantity,:ri1_actualcost,:ri1_newcost,:ri1_increase_account,:ri1_declining_account,:ri1_costcode,:ri1_ubusiness,:ri1_project, :ri1_docentry , :ri1_ubication, :ri1_linetotal)";
				
				$resInsertDetail = $this->pedeo->insertRow($sqlDetail, array(
					":ri1_itemcode" => $detail['ri1_itemcode'],
					":ri1_itemname" => $detail['ri1_itemname'],
					":ri1_whscode" => $detail['ri1_whscode'],
					":ri1_quantity" => $detail['ri1_quantity'],
					":ri1_actualcost" => $detail['ri1_actualcost'],
					":ri1_newcost" => $detail['ri1_newcost'],
					":ri1_increase_account" => $detail['ri1_increase_account'],
					":ri1_declining_account" => $detail['ri1_declining_account'],
					":ri1_costcode" => $detail['ri1_ccost'],
					":ri1_ubusiness" => $detail['ri1_ubussines'],
					":ri1_project" => $detail['ri1_project'],
					":ri1_docentry" => $resInsert,
					":ri1_ubication" => $detail['ri1_ubication'],
					":ri1_linetotal" => $detail['ri1_total']
				)
				);


				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle de la cotizacion se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error' => true,
						'data' => $resInsertDetail,
						'mensaje' => 'No se pudo registrar la revalorizacion'

					);

					$this->response($respuesta);

					return;
				}
				// SE OBTINE EL MANEJO DE EL ITEM EN LA LINEA
				$itemProperties= $this->itemProperties($detail['ri1_itemcode'],$detail['ri1_whscode'],$Data['business']);
				$ManejaInvetario = $itemProperties["inventory"];
				$ManejaUbicacion = $itemProperties["ubication"];
				$ManejaLote = $itemProperties["lote"];
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
								':bdi_whscode'   => $detail['ri1_whscode'],
								':bdi_itemcode'  => $detail['ri1_itemcode'],
								':bdi_lote'      => $detail['ote_code'],
								':bdi_ubication' => $detail['ri1_ubication'],
								':business' 	 => $Data['business']
							));
						} else {
							$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_ubication = :bdi_ubication AND business = :business";
							$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
								':bdi_whscode'   => $detail['ri1_whscode'],
								':bdi_itemcode'  => $detail['ri1_itemcode'],
								':bdi_ubication' => $detail['ri1_ubication'],
								':business' 	 => $Data['business']
							));
						}
					}else{

						if ($ManejaLote == 1) {

							$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_lote = :bdi_lote AND business = :business";
							$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
								':bdi_whscode' 	=> $detail['ri1_whscode'],
								':bdi_itemcode' => $detail['ri1_itemcode'],
								':bdi_lote'	 	=> $detail['ote_code'],
								':business' 	=> $Data['business']
							));
						} else {
							$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND business = :business";
							$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
								':bdi_whscode' 	=> $detail['ri1_whscode'],
								':bdi_itemcode' => $detail['ri1_itemcode'],
								':business' 	=> $Data['business']
							));
						}
	
					}
			}

			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'Operacion exitosa'
			);

			$this->response($respuesta);
		} else {
			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error' => true,
				'data' => $resInsert,
				'mensaje' => 'No se pudo registrar la revalorizacion'
			);

			$this->response($respuesta);

			return;
		}



	}

	public function getInvDetailDetail_get()
	{
		$Data = $this->get();
		$sqlSelect = " SELECT * FROM iri1 WHERE ri1_docentry = :r1_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':r1_docentry' => $Data['ri1_docentry']));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data' => $resSelect,
				'mensaje' => ''
			);

		} else {

			$respuesta = array(
				'error' => true,
				'data' => array(),
				'mensaje' => 'busqueda sin resultados'
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
	/**
	 * Summary of itemProperties
	 * @param mixed $item codigo del item de la linea
	 * @param mixed $whscode codigo de almacen 
	 * @param mixed $business empresa
	 * @return array<int> 
	 */
	private function itemProperties($item,$whscode,$business){
		$result = array(
			"inventory" => 0,
			"ubication" => 0,
			"lote" => 0
		);
		$sqlItemINV = "SELECT dma_item_inv FROM dmar WHERE dma_item_code = :dma_item_code AND dma_item_inv = :dma_item_inv";
				$resItemINV = $this->pedeo->queryTable($sqlItemINV, array(
					':dma_item_code' => $item,
					':dma_item_inv'  => 1
				));

				if (isset($resItemINV[0])) {
					// CONSULTA PARA VERIFICAR SI EL ALMACEN MANEJA UBICACION
					$sqlubicacion = "SELECT * FROM dmws WHERE dws_ubication = :dws_ubication AND dws_code = :dws_code AND business = :business";
					$resubicacion = $this->pedeo->queryTable($sqlubicacion, array(
						':dws_ubication' => 1,
						':dws_code' => $whscode,
						':business' => $business
					));

					if ( isset($resubicacion[0]) ){
						$result["ubication"] = 1;
					}else{
						$result["ubication"] = 0;
					}

					// SI EL ARTICULO MANEJA LOTE
					$sqlLote = "SELECT dma_lotes_code FROM dmar WHERE dma_item_code = :dma_item_code AND dma_lotes_code = :dma_lotes_code";
					$resLote = $this->pedeo->queryTable($sqlLote, array(
	
						':dma_item_code' => $item,
						':dma_lotes_code'  => 1
					));
	
					if (isset($resLote[0])) {
						$result["lote"] = 1;
					} else {
						$result["lote"] = 0;
					}

					$result["inventory"] = 1;
				} else {
					$result["inventory"] = 0;
				}

		return $result;
	}

}

