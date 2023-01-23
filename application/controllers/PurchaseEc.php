<?php
// ENTRADA COMPRAS
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class PurchaseEc extends REST_Controller
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
		$this->load->library('DocumentCopy');
	}

	//CREAR ENTRADA COMPRAS
	public function createPurchaseEc_post()
	{
		$Data = $this->post();
		
		if (!isset($Data['business']) OR
			!isset($Data['branch'])) {

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

		$DetalleAsientoIngreso = new stdClass(); // Cada objeto de las linea del detalle consolidado
		$DetalleAsientoIva = new stdClass();
		$DetalleCostoInventario = new stdClass();
		$DetalleCostoCosto = new stdClass();
		$DetalleConsolidadoIngreso = []; // Array Final con los datos del asiento solo ingreso
		$DetalleConsolidadoCostoInventario = [];
		$DetalleConsolidadoCostoCosto = [];
		$DetalleConsolidadoIva = []; // Array Final con los datos del asiento segun el iva
		$inArrayIngreso = array(); // Array para mantener el indice de las llaves para ingreso
		$inArrayIva = array(); // Array para mantener el indice de las llaves para iva
		$inArrayCostoInventario = array();
		$inArrayCostoCosto = array();
		$llave = ""; // la comnbinacion entre la cuenta contable,proyecto, unidad de negocio y centro de costo
		$llaveIva = ""; //segun tipo de iva
		$llaveCostoInventario = "";
		$llaveCostoCosto = "";
		$posicion = 0; // contiene la posicion con que se creara en el array DetalleConsolidado
		$posicionIva = 0;
		$posicionCostoInventario = 0;
		$posicionCostoCosto = 0;
		$codigoCuenta = ""; //para saber la naturaleza
		$grantotalCostoInventario = 0;
		$ManejaInvetario = 0;
		$ManejaLote = 0;
		$ManejaSerial = 0;
		$ManejaUbicacion = 0;
		$TasaDocLoc = 0; // MANTIENE EL VALOR DE LA TASA DE CONVERSION ENTRE LA MONEDA LOCAL Y LA MONEDA DEL DOCUMENTO
		$TasaLocSys = 0; // MANTIENE EL VALOR DE LA TASA DE CONVERSION ENTRE LA MONEDA LOCAL Y LA MONEDA DEL SISTEMA
		$MONEDALOCAL = 0;
		$resInsertAsiento = "";
		$ResultadoInv = 0; // INDICA SI EXISTE AL MENOS UN ITEM QUE MANEJA INVENTARIO
		$CANTUOMPURCHASE = 0; //CANTIDAD EN UNIDAD DE MEDIDA
		$CANTUOMSALE = 0;


		// Se globaliza la variable sqlDetalleAsiento
		$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
												ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
												ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
												ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref)VALUES (:ac1_trans_id, :ac1_account,
												:ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
												:ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
												:ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
												:ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref)";


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
				'mensaje' => 'No se encontro el detalle de la entrada de compra'
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
		$periodo = $this->generic->ValidatePeriod($Data['cec_duedev'], $Data['cec_docdate'], $Data['cec_duedate'], 0);

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

		$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['cec_series']));

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
		//FINALIZA PROCESO PARA BUSCAR LA NUMERACION DEL DOCUMENTO LA NUMERACION DEL DOCUMENTO

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
		$resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $Data['cec_currency'], ':tsa_date' => $Data['cec_docdate']));

		if (isset($resBusTasa[0])) {
		} else {

			if (trim($Data['cec_currency']) != $MONEDALOCAL) {

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'No se encrontro la tasa de cambio para la moneda: ' . $Data['cec_currency'] . ' en la actual fecha del documento: ' . $Data['cec_docdate'] . ' y la moneda local: ' . $resMonedaLoc[0]['pgm_symbol']
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
		}

		$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
		$resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $Data['cec_docdate']));

		if (isset($resBusTasa2[0])) {
		} else {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :' . $Data['cec_docdate']
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$TasaDocLoc = isset($resBusTasa[0]['tsa_value']) ? $resBusTasa[0]['tsa_value'] : 1;
		$TasaLocSys = $resBusTasa2[0]['tsa_value'];

		// FIN DEL PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO


		$sqlInsert = "INSERT INTO dcec(cec_series, cec_docnum, cec_docdate, cec_duedate, cec_duedev, cec_pricelist, cec_cardcode,
                      cec_cardname, cec_currency, cec_contacid, cec_slpcode, cec_empid, cec_comment, cec_doctotal, cec_baseamnt, cec_taxtotal,
                      cec_discprofit, cec_discount, cec_createat, cec_baseentry, cec_basetype, cec_doctype, cec_idadd, cec_adress, cec_paytype,
                      cec_createby,cec_correl,cec_api,business,branch)VALUES(:cec_series, :cec_docnum, :cec_docdate, :cec_duedate, :cec_duedev, :cec_pricelist, :cec_cardcode, :cec_cardname,
                      :cec_currency, :cec_contacid, :cec_slpcode, :cec_empid, :cec_comment, :cec_doctotal, :cec_baseamnt, :cec_taxtotal, :cec_discprofit, :cec_discount,
                      :cec_createat, :cec_baseentry, :cec_basetype, :cec_doctype, :cec_idadd, :cec_adress, :cec_paytype,:cec_createby,:cec_correl,
					  :cec_api,:business,:branch)";


		// Se Inicia la transaccion,
		// Todas las consultas de modificacion siguientes
		// aplicaran solo despues que se confirme la transaccion,
		// de lo contrario no se aplicaran los cambios y se devolvera
		// la base de datos a su estado original.

		$this->pedeo->trans_begin();

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			':cec_docnum' => $DocNumVerificado,
			':cec_series' => is_numeric($Data['cec_series']) ? $Data['cec_series'] : 0,
			':cec_docdate' => $this->validateDate($Data['cec_docdate']) ? $Data['cec_docdate'] : NULL,
			':cec_duedate' => $this->validateDate($Data['cec_duedate']) ? $Data['cec_duedate'] : NULL,
			':cec_duedev' => $this->validateDate($Data['cec_duedev']) ? $Data['cec_duedev'] : NULL,
			':cec_pricelist' => is_numeric($Data['cec_pricelist']) ? $Data['cec_pricelist'] : 0,
			':cec_cardcode' => isset($Data['cec_cardcode']) ? $Data['cec_cardcode'] : NULL,
			':cec_cardname' => isset($Data['cec_cardname']) ? $Data['cec_cardname'] : NULL,
			':cec_currency' => isset($Data['cec_currency']) ? $Data['cec_currency'] : NULL,
			':cec_contacid' => isset($Data['cec_contacid']) ? $Data['cec_contacid'] : NULL,
			':cec_slpcode' => is_numeric($Data['cec_slpcode']) ? $Data['cec_slpcode'] : 0,
			':cec_empid' => is_numeric($Data['cec_empid']) ? $Data['cec_empid'] : 0,
			':cec_comment' => isset($Data['cec_comment']) ? $Data['cec_comment'] : NULL,
			':cec_doctotal' => is_numeric($Data['cec_doctotal']) ? $Data['cec_doctotal'] : 0,
			':cec_baseamnt' => is_numeric($Data['cec_baseamnt']) ? $Data['cec_baseamnt'] : 0,
			':cec_taxtotal' => is_numeric($Data['cec_taxtotal']) ? $Data['cec_taxtotal'] : 0,
			':cec_discprofit' => is_numeric($Data['cec_discprofit']) ? $Data['cec_discprofit'] : 0,
			':cec_discount' => is_numeric($Data['cec_discount']) ? $Data['cec_discount'] : 0,
			':cec_createat' => $this->validateDate($Data['cec_createat']) ? $Data['cec_createat'] : NULL,
			':cec_baseentry' => is_numeric($Data['cec_baseentry']) ? $Data['cec_baseentry'] : 0,
			':cec_basetype' => is_numeric($Data['cec_basetype']) ? $Data['cec_basetype'] : 0,
			':cec_doctype' => is_numeric($Data['cec_doctype']) ? $Data['cec_doctype'] : 0,
			':cec_idadd' => isset($Data['cec_idadd']) ? $Data['cec_idadd'] : NULL,
			':cec_adress' => isset($Data['cec_adress']) ? $Data['cec_adress'] : NULL,
			':cec_paytype' => is_numeric($Data['cec_paytype']) ? $Data['cec_paytype'] : 0,
			':cec_createby' => isset($Data['cec_createby']) ? $Data['cec_createby'] : NULL,
			':cec_correl' => isset($Data['cec_correl']) ? $Data['cec_correl'] : NULL,
			':cec_api' => isset($Data['cec_api']) ? $Data['cec_api'] : 0,
			':business' => isset($Data['business']) ? $Data['business'] : NULL,
			':branch' => isset($Data['branch']) ? $Data['branch'] : NULL

		));

		if (is_numeric($resInsert) && $resInsert > 0) {

			// Se actualiza la serie de la numeracion del documento

			$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id'      => $Data['cec_series']
			));


			if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
			} else {
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'    => $resActualizarNumeracion,
					'mensaje'	=> 'No se pudo crear la entrada de compra 1'
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
				':bed_doctype' => $Data['cec_doctype'],
				':bed_status' => 1, //ESTADO CERRADO
				':bed_createby' => $Data['cec_createby'],
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
					'mensaje'	=> 'No se pudo registrar la Entrega de ventas'
				);


				$this->response($respuesta);

				return;
			}

			//FIN PROCESO ESTADO DEL DOCUMENTO


			//Se agregan los asientos contables*/*******

			$sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user)
															 VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user)";


			$resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

				':mac_doc_num' => 1,
				':mac_status' => 1,
				':mac_base_type' => is_numeric($Data['cec_doctype']) ? $Data['cec_doctype'] : 0,
				':mac_base_entry' => $resInsert,
				':mac_doc_date' => $this->validateDate($Data['cec_docdate']) ? $Data['cec_docdate'] : NULL,
				':mac_doc_duedate' => $this->validateDate($Data['cec_duedate']) ? $Data['cec_duedate'] : NULL,
				':mac_legal_date' => $this->validateDate($Data['cec_docdate']) ? $Data['cec_docdate'] : NULL,
				':mac_ref1' => is_numeric($Data['cec_doctype']) ? $Data['cec_doctype'] : 0,
				':mac_ref2' => "",
				':mac_ref3' => "",
				':mac_loc_total' => is_numeric($Data['cec_doctotal']) ? $Data['cec_doctotal'] : 0,
				':mac_fc_total' => is_numeric($Data['cec_doctotal']) ? $Data['cec_doctotal'] : 0,
				':mac_sys_total' => is_numeric($Data['cec_doctotal']) ? $Data['cec_doctotal'] : 0,
				':mac_trans_dode' => 1,
				':mac_beline_nume' => 1,
				':mac_vat_date' => $this->validateDate($Data['cec_docdate']) ? $Data['cec_docdate'] : NULL,
				':mac_serie' => 1,
				':mac_number' => 1,
				':mac_bammntsys' => is_numeric($Data['cec_baseamnt']) ? $Data['cec_baseamnt'] : 0,
				':mac_bammnt' => is_numeric($Data['cec_baseamnt']) ? $Data['cec_baseamnt'] : 0,
				':mac_wtsum' => 1,
				':mac_vatsum' => is_numeric($Data['cec_taxtotal']) ? $Data['cec_taxtotal'] : 0,
				':mac_comments' => isset($Data['cec_comment']) ? $Data['cec_comment'] : NULL,
				':mac_create_date' => $this->validateDate($Data['cec_createat']) ? $Data['cec_createat'] : NULL,
				':mac_made_usuer' => isset($Data['cec_createby']) ? $Data['cec_createby'] : NULL,
				':mac_update_date' => date("Y-m-d"),
				':mac_update_user' => isset($Data['cec_createby']) ? $Data['cec_createby'] : NULL
			));


			if (is_numeric($resInsertAsiento) && $resInsertAsiento > 0) {
				// Se verifica que el detalle no de error insertando //
			} else {

				// si falla algun insert del detalle de la entrada de compras se devuelven los cambios realizados por la transaccion,
				// se retorna el error y se detiene la ejecucion del codigo restante.
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'	  => $resInsertAsiento,
					'mensaje'	=> 'No se pudo registrar la entrada de compras 1'
				);

				$this->response($respuesta);

				return;
			}

			//SE APLICA PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS
			if (isset($Data['cec_baseentry']) && is_numeric($Data['cec_baseentry']) && isset($Data['cec_basetype']) && is_numeric($Data['cec_basetype'])) {

				$sqlDocInicio = "SELECT bmd_tdi, bmd_ndi FROM tbmd WHERE  bmd_doctype = :bmd_doctype AND bmd_docentry = :bmd_docentry";
				$resDocInicio = $this->pedeo->queryTable($sqlDocInicio, array(
					':bmd_doctype' => $Data['cec_basetype'],
					':bmd_docentry' => $Data['cec_baseentry']
				));


				if (isset($resDocInicio[0])) {

					$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
															bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype)
															VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
															:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype)";

					$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

						':bmd_doctype' => is_numeric($Data['cec_doctype']) ? $Data['cec_doctype'] : 0,
						':bmd_docentry' => $resInsert,
						':bmd_createat' => $this->validateDate($Data['cec_createat']) ? $Data['cec_createat'] : NULL,
						':bmd_doctypeo' => is_numeric($Data['cec_basetype']) ? $Data['cec_basetype'] : 0, //ORIGEN
						':bmd_docentryo' => is_numeric($Data['cec_baseentry']) ? $Data['cec_baseentry'] : 0,  //ORIGEN
						':bmd_tdi' => $resDocInicio[0]['bmd_tdi'], // DOCUMENTO INICIAL
						':bmd_ndi' => $resDocInicio[0]['bmd_ndi'], // DOCUMENTO INICIAL
						':bmd_docnum' => $DocNumVerificado,
						':bmd_doctotal' => is_numeric($Data['cec_doctotal']) ? $Data['cec_doctotal'] : 0,
						':bmd_cardcode' => isset($Data['cec_cardcode']) ? $Data['cec_cardcode'] : NULL,
						':bmd_cardtype' => 2
					));

					if (is_numeric($resInsertMD) && $resInsertMD > 0) {
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' => $resInsertEstado,
							'mensaje'	=> 'No se pudo registrar el movimiento del documento'
						);


						$this->response($respuesta);

						return;
					}
				} else {

					$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
															bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype)
															VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
															:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype)";

					$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

						':bmd_doctype' => is_numeric($Data['cec_doctype']) ? $Data['cec_doctype'] : 0,
						':bmd_docentry' => $resInsert,
						':bmd_createat' => $this->validateDate($Data['cec_createat']) ? $Data['cec_createat'] : NULL,
						':bmd_doctypeo' => is_numeric($Data['cec_basetype']) ? $Data['cec_basetype'] : 0, //ORIGEN
						':bmd_docentryo' => is_numeric($Data['cec_baseentry']) ? $Data['cec_baseentry'] : 0,  //ORIGEN
						':bmd_tdi' => is_numeric($Data['cec_doctype']) ? $Data['cec_doctype'] : 0, // DOCUMENTO INICIAL
						':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
						':bmd_docnum' => $DocNumVerificado,
						':bmd_doctotal' => is_numeric($Data['cec_doctotal']) ? $Data['cec_doctotal'] : 0,
						':bmd_cardcode' => isset($Data['cec_cardcode']) ? $Data['cec_cardcode'] : NULL,
						':bmd_cardtype' => 2
					));

					if (is_numeric($resInsertMD) && $resInsertMD > 0) {
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' => $resInsertEstado,
							'mensaje'	=> 'No se pudo registrar el movimiento del documento'
						);


						$this->response($respuesta);

						return;
					}
				}
			} else {

				$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
														bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype)
														VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
														:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype)";

				$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

					':bmd_doctype' => is_numeric($Data['cec_doctype']) ? $Data['cec_doctype'] : 0,
					':bmd_docentry' => $resInsert,
					':bmd_createat' => $this->validateDate($Data['cec_createat']) ? $Data['cec_createat'] : NULL,
					':bmd_doctypeo' => is_numeric($Data['cec_basetype']) ? $Data['cec_basetype'] : 0, //ORIGEN
					':bmd_docentryo' => is_numeric($Data['cec_baseentry']) ? $Data['cec_baseentry'] : 0,  //ORIGEN
					':bmd_tdi' => is_numeric($Data['cec_doctype']) ? $Data['cec_doctype'] : 0, // DOCUMENTO INICIAL
					':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
					':bmd_docnum' => $DocNumVerificado,
					':bmd_doctotal' => is_numeric($Data['cec_doctotal']) ? $Data['cec_doctotal'] : 0,
					':bmd_cardcode' => isset($Data['cec_cardcode']) ? $Data['cec_cardcode'] : NULL,
					':bmd_cardtype' => 2
				));

				if (is_numeric($resInsertMD) && $resInsertMD > 0) {
				} else {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertEstado,
						'mensaje'	=> 'No se pudo registrar el movimiento del documento'
					);


					$this->response($respuesta);

					return;
				}
			}
			//FIN PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS



			foreach ($ContenidoDetalle as $key => $detail) {


				$CANTUOMPURCHASE = $this->generic->getUomPurchase($detail['ec1_itemcode']);
				$CANTUOMSALE = $this->generic->getUomSale($detail['ec1_itemcode']);



				if ($CANTUOMPURCHASE == 0 || $CANTUOMSALE == 0) {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' 		=> $detail['ec1_itemcode'],
						'mensaje'	=> 'No se encontro la equivalencia de la unidad de medida para el item: ' . $detail['ec1_itemcode']
					);

					$this->response($respuesta);

					return;
				}

				$sqlInsertDetail = "INSERT INTO cec1(ec1_docentry,ec1_itemcode, ec1_itemname, ec1_quantity, ec1_uom, ec1_whscode,
                                    ec1_price, ec1_vat, ec1_vatsum, ec1_discount, ec1_linetotal, ec1_costcode, ec1_ubusiness, ec1_project,
                                    ec1_acctcode, ec1_basetype, ec1_doctype, ec1_avprice, ec1_inventory, ec1_linenum, ec1_acciva, ec1_codimp, ec1_ubication,ec1_baseline)VALUES(:ec1_docentry, :ec1_itemcode, :ec1_itemname, :ec1_quantity,
                                    :ec1_uom, :ec1_whscode,:ec1_price, :ec1_vat, :ec1_vatsum, :ec1_discount, :ec1_linetotal, :ec1_costcode, :ec1_ubusiness, :ec1_project,
                                    :ec1_acctcode, :ec1_basetype, :ec1_doctype, :ec1_avprice, :ec1_inventory,:ec1_linenum,:ec1_acciva,:ec1_codimp, 
									:ec1_ubication,:ec1_baseline)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':ec1_docentry' => $resInsert,
					':ec1_itemcode' => isset($detail['ec1_itemcode']) ? $detail['ec1_itemcode'] : NULL,
					':ec1_itemname' => isset($detail['ec1_itemname']) ? $detail['ec1_itemname'] : NULL,
					':ec1_quantity' => is_numeric($detail['ec1_quantity']) ? $detail['ec1_quantity'] : 0,
					':ec1_uom' => isset($detail['ec1_uom']) ? $detail['ec1_uom'] : NULL,
					':ec1_whscode' => isset($detail['ec1_whscode']) ? $detail['ec1_whscode'] : NULL,
					':ec1_price' => is_numeric($detail['ec1_price']) ? $detail['ec1_price'] : 0,
					':ec1_vat' => is_numeric($detail['ec1_vat']) ? $detail['ec1_vat'] : 0,
					':ec1_vatsum' => is_numeric($detail['ec1_vatsum']) ? $detail['ec1_vatsum'] : 0,
					':ec1_discount' => is_numeric($detail['ec1_discount']) ? $detail['ec1_discount'] : 0,
					':ec1_linetotal' => is_numeric($detail['ec1_linetotal']) ? $detail['ec1_linetotal'] : 0,
					':ec1_costcode' => isset($detail['ec1_costcode']) ? $detail['ec1_costcode'] : NULL,
					':ec1_ubusiness' => isset($detail['ec1_ubusiness']) ? $detail['ec1_ubusiness'] : NULL,
					':ec1_project' => isset($detail['ec1_project']) ? $detail['ec1_project'] : NULL,
					':ec1_acctcode' => is_numeric($detail['ec1_acctcode']) ? $detail['ec1_acctcode'] : 0,
					':ec1_basetype' => is_numeric($detail['ec1_basetype']) ? $detail['ec1_basetype'] : 0,
					':ec1_doctype' => is_numeric($detail['ec1_doctype']) ? $detail['ec1_doctype'] : 0,
					':ec1_avprice' => is_numeric($detail['ec1_avprice']) ? $detail['ec1_avprice'] : 0,
					':ec1_inventory' => is_numeric($detail['ec1_inventory']) ? $detail['ec1_inventory'] : NULL,
					':ec1_linenum' => is_numeric($detail['ec1_linenum']) ? $detail['ec1_linenum'] : NULL,
					':ec1_acciva' => is_numeric($detail['ec1_acciva']) ? $detail['ec1_acciva'] : NULL,
					':ec1_codimp' => isset($detail['ec1_codimp']) ? $detail['ec1_codimp'] : NULL,
					':ec1_ubication' => isset($detail['ec1_ubication']) ? $detail['ec1_ubication'] : NULL,
					':ec1_baseline' => is_numeric($detail['ec1_baseline']) ? $detail['ec1_baseline'] : 0,
				));

				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// Se verifica que el detalle no de error insertando //
					//VALIDAR SI LOS ITEMS SON IGUALES A LOS DEL DOCUMENTO DE ORIGEN SIEMPRE QUE VENGA DE UN COPIAR DE
					if($Data['cdc_basetype'] == 12){
						//OBTENER NUMERO DOCUMENTO ORIGEN
						$DOC = "SELECT cpo_docnum FROM dcpo WHERE cpo_doctype = :cpo_doctype AND cpo_docentry = :cpo_docentry";
						$RESULT_DOC = $this->pedeo->queryTable($DOC,array(':cpo_docentry' =>$Data['cpo_baseentry'],':cpo_doctype' => $Data['cpo_basetype']));
						foreach ($ContenidoDetalle as $key => $value) {
							# code...
							//VALIDAR SI EL ARTICULO DEL DOCUMENTO ACTUAL EXISTE EN EL DOCUMENTO DE ORIGEN
							$sql = "SELECT dcpo.cpo_docnum,cpo1.po1_itemcode FROM dcpo INNER JOIN cpo1 ON dcpo.cpo_docentry = cpo1.po1_docentry 
							WHERE dcpo.cpo_docentry = :cpo_docentry AND dcpo.cpo_doctype = :cpo_doctype AND cpo1.po1_itemcode = :po1_itemcode";
							$resSql = $this->pedeo->queryTable($sql,array(
								':cpo_docentry' =>$Data['cec_baseentry'],
								':cpo_doctype' => $Data['cec_basetype'],
								':po1_itemcode' => $value['ec1_itemcode']
							));
							
								if(isset($resSql[0])){
									//EL ARTICULO EXISTE EN EL DOCUMENTO DE ORIGEN
								}else {
									//EL ARTICULO NO EXISTE EN EL DOCUEMENTO DE ORIGEN
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data' => $value['ec1_itemcode'],
										'mensaje'	=> 'El Item '.$value['ec1_itemcode'].' no existe en el documento origen (Orden #'.$RESULT_DOC[0]['cpo_docnum'].')'
									);

									$this->response($respuesta);

									return;
								}
							}

					}
				} else {

					// si falla algun insert del detalle de la cotizacion se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertDetail,
						'mensaje'	=> 'No se pudo registrar la entrada de compra'
					);

					$this->response($respuesta);

					return;
				}


				// si el item es inventariable
				// SE VERIFICA SI EL ARTICULO ESTA MARCADO PARA MANEJARSE EN INVENTARIO
				// Y A SU VES SI MANEJA LOTE
				$sqlItemINV = "SELECT dma_item_inv FROM dmar WHERE dma_item_code = :dma_item_code AND dma_item_inv = :dma_item_inv";
				$resItemINV = $this->pedeo->queryTable($sqlItemINV, array(

					':dma_item_code' => $detail['ec1_itemcode'],
					':dma_item_inv'  => 1
				));

				if (isset($resItemINV[0])) {

					$ManejaInvetario = 1;
					$ResultadoInv  = 1;
					
					// CONSULTA PARA VERIFICAR SI EL ALMACEN MANEJA UBICACION
					$sqlubicacion = "SELECT * FROM dmws WHERE dws_ubication = :dws_ubication AND dws_code = :dws_code AND business = :business";
					$resubicacion = $this->pedeo->queryTable($sqlubicacion, array(
						':dws_ubication' => 1,
						':dws_code' => $detail['ec1_whscode'],
						':business' => $Data['business']
					));


					if ( isset($resubicacion[0]) ){
						$ManejaUbicacion = 1;
					}else{
						$ManejaUbicacion = 0;
					}

					// SI MANEJA LOTE
					$sqlLote = "SELECT dma_lotes_code FROM dmar WHERE dma_item_code = :dma_item_code AND dma_lotes_code = :dma_lotes_code";
					$resLote = $this->pedeo->queryTable($sqlLote, array(

						':dma_item_code' => $detail['ec1_itemcode'],
						':dma_lotes_code'  => 1
					));

					if (isset($resLote[0])) {
						$ManejaLote = 1;
					} else {
						$ManejaLote = 0;
					}

				} else {
					$ManejaInvetario = 0;
				}

				
				// FIN PROCESO ITEM MANEJA INVENTARIO Y LOTE
				// si el item es inventariable



				//AGREGAR ITEM Y CANTIDAD AL STOCK SI NO EXISTE
				// //Se aplica el movimiento de inventario
				// //Solo si el item es inventariable
				if ($ManejaInvetario == 1) {

					//SE VERIFICA SI EL ARTICULO MANEJA SERIAL
					$sqlItemSerial = "SELECT dma_series_code FROM dmar WHERE  dma_item_code = :dma_item_code AND dma_series_code = :dma_series_code";
					$resItemSerial = $this->pedeo->queryTable($sqlItemSerial, array(

						':dma_item_code' => $detail['ec1_itemcode'],
						':dma_series_code'  => 1
					));

					if (isset($resItemSerial[0])) {
						$ManejaSerial = 1;

						$AddSerial = $this->generic->addSerial($detail['serials'], $detail['ec1_itemcode'], $Data['cec_doctype'], $resInsert, $DocNumVerificado, $Data['cec_docdate'], 1, $Data['cec_comment'], $detail['ec1_whscode'], $detail['ec1_quantity'], $Data['cec_createby'], $resInsertDetail, $Data['business']);

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



					$sqlCostoMomentoRegistro = '';
					$resCostoMomentoRegistro = [];


					if ( $ManejaUbicacion == 1 ){

						if ( $ManejaLote == 1 ) {

							$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_ubication = :bdi_ubication AND bdi_lote = :bdi_lote AND business = :business";
							$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
								':bdi_whscode'   => $detail['ec1_whscode'],
								':bdi_itemcode'  => $detail['ec1_itemcode'],
								':bdi_ubication' => $detail['ec1_ubication'],
								':bdi_lote'      => $detail['ote_code'],
								':business' 	 => $Data['business']
							));
						} else {
	
							$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_ubication = :bdi_ubication AND business = :business";
							$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
								':bdi_whscode' => $detail['ec1_whscode'],
								':bdi_itemcode' => $detail['ec1_itemcode'],
								':bdi_ubication' => $detail['ec1_itemcode'],
								':business' => $Data['business']
							));
						}
	

					}else{
						if ( $ManejaLote == 1 ) {

							$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_lote = :bdi_lote AND business = :business";
							$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
								':bdi_whscode' => $detail['ec1_whscode'],
								':bdi_itemcode' => $detail['ec1_itemcode'],
								':bdi_lote' => $detail['ote_code'],
								':business' => $Data['business']
							));
						} else {
	
							$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND business = :business";
							$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
								':bdi_whscode' => $detail['ec1_whscode'],
								':bdi_itemcode' => $detail['ec1_itemcode'],
								':business' => $Data['business']
							));
						}
	
					}




					if (!isset($resCostoMomentoRegistro[0])) {
						// SE COLOCA EL PRECIO DE LA LINEA COMO EL COSTO
						// SE aplica el movimiento de inventario
						$sqlInserMovimiento = '';
						$resInserMovimiento = [];
						
					
						$sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode,bmi_quantity,bmi_whscode,bmi_createat,bmi_createby,bmy_doctype,bmy_baseentry,bmi_cost,bmi_currequantity,bmi_basenum,bmi_docdate,bmi_duedate,bmi_duedev,bmi_comment,bmi_lote,bmi_ubication)
												VALUES (:bmi_itemcode,:bmi_quantity, :bmi_whscode,:bmi_createat,:bmi_createby,:bmy_doctype,:bmy_baseentry,:bmi_cost,:bmi_currequantity,:bmi_basenum,:bmi_docdate,:bmi_duedate,:bmi_duedev,:bmi_comment,:bmi_lote,:bmi_ubication)";
						$resInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(

							':bmi_itemcode'  => isset($detail['ec1_itemcode']) ? $detail['ec1_itemcode'] : NULL,
							':bmi_quantity'  => ($this->generic->getCantInv($detail['ec1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE) * $Data['invtype']),
							':bmi_whscode'   => isset($detail['ec1_whscode']) ? $detail['ec1_whscode'] : NULL,
							':bmi_createat'  => $this->validateDate($Data['cec_createat']) ? $Data['cec_createat'] : NULL,
							':bmi_createby'  => isset($Data['cec_createby']) ? $Data['cec_createby'] : NULL,
							':bmy_doctype'   => is_numeric($Data['cec_doctype']) ? $Data['cec_doctype'] : 0,
							':bmy_baseentry' => $resInsert,
							':bmi_cost'      => (($detail['ec1_price'] / $CANTUOMPURCHASE) * $CANTUOMSALE),
							':bmi_currequantity' 	=> 0,
							':bmi_basenum'			=> $DocNumVerificado,
							':bmi_docdate' => $this->validateDate($Data['cec_docdate']) ? $Data['cec_docdate'] : NULL,
							':bmi_duedate' => $this->validateDate($Data['cec_duedate']) ? $Data['cec_duedate'] : NULL,
							':bmi_duedev'  => $this->validateDate($Data['cec_duedev']) ? $Data['cec_duedev'] : NULL,
							':bmi_comment' => isset($Data['cec_comment']) ? $Data['cec_comment'] : NULL,
							':bmi_lote' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
							':bmi_ubication' => isset($detail['ec1_ubication']) ? $detail['ec1_ubication'] : NULL

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
								'mensaje'	=> 'No se pudo registrar la entrada en compras'
							);

							$this->response($respuesta);

							return;
						}
					} else {
						//SE VALIDA SI EL ARTICULO MANEJA LOTE
						$sqlInserMovimiento = '';
						$resInserMovimiento = [];

					
						$sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode,bmi_quantity,bmi_whscode,bmi_createat,bmi_createby,bmy_doctype,bmy_baseentry,bmi_cost,bmi_currequantity,bmi_basenum,bmi_docdate,bmi_duedate,bmi_duedev,bmi_comment,bmi_lote,bmi_ubication)
												VALUES (:bmi_itemcode,:bmi_quantity, :bmi_whscode,:bmi_createat,:bmi_createby,:bmy_doctype,:bmy_baseentry,:bmi_cost,:bmi_currequantity,:bmi_basenum,:bmi_docdate,:bmi_duedate,:bmi_duedev,:bmi_comment,:bmi_lote,:bmi_ubication)";

						$sqlInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(

							':bmi_itemcode' => isset($detail['ec1_itemcode']) ? $detail['ec1_itemcode'] : NULL,
							':bmi_quantity' => ($this->generic->getCantInv($detail['ec1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE) * $Data['invtype']),
							':bmi_whscode'  => isset($detail['ec1_whscode']) ? $detail['ec1_whscode'] : NULL,
							':bmi_createat' => $this->validateDate($Data['cec_createat']) ? $Data['cec_createat'] : NULL,
							':bmi_createby' => isset($Data['cec_createby']) ? $Data['cec_createby'] : NULL,
							':bmy_doctype'  => is_numeric($Data['cec_doctype']) ? $Data['cec_doctype'] : 0,
							':bmy_baseentry' => $resInsert,
							':bmi_cost'      => $resCostoMomentoRegistro[0]['bdi_avgprice'],
							':bmi_currequantity' 	=> $resCostoMomentoRegistro[0]['bdi_quantity'],
							':bmi_basenum'			=> $DocNumVerificado,
							':bmi_docdate' => $this->validateDate($Data['cec_docdate']) ? $Data['cec_docdate'] : NULL,
							':bmi_duedate' => $this->validateDate($Data['cec_duedate']) ? $Data['cec_duedate'] : NULL,
							':bmi_duedev'  => $this->validateDate($Data['cec_duedev']) ? $Data['cec_duedev'] : NULL,
							':bmi_comment' => isset($Data['cec_comment']) ? $Data['cec_comment'] : NULL,
							':bmi_lote' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
							':bmi_ubication' => isset($detail['ec1_ubication']) ? $detail['ec1_ubication'] : NULL

						));
						

						if (is_numeric($sqlInserMovimiento) && $sqlInserMovimiento > 0) {
							// Se verifica que el detalle no de error insertando //
						} else {

							// si falla algun insert del detalle de la orden de compra se devuelven los cambios realizados por la transaccion,
							// se retorna el error y se detiene la ejecucion del codigo restante.
							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' => $sqlInserMovimiento,
								'mensaje'	=> 'No se pudo registrar la Entrada de Compra 2'
							);

							$this->response($respuesta);

							return;
						}
					}
				}


				//Se Aplica el movimiento en stock y se cambia el costo ponderado
				//Solo si el articulo es inventariable
				//SE VALIDA SI EL ARTICULO MANEJA LOTE
				$sqlCostoCantidad = '';
				$resCostoCantidad = [];
				$CantidadPorAlmacen = 0;
				$CostoPorAlmacen = 0;

				if ($ManejaInvetario  == 1) {

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
	
								':bdi_itemcode'  => $detail['ec1_itemcode'],
								':bdi_whscode'   => $detail['ec1_whscode'],
								':bdi_lote' 	 => $detail['ote_code'],
								':bdi_ubication' => $detail['ec1_ubication'],
								':business' => $Data['business']
							));
	
							// se busca la cantidad general del articulo agrupando todos los almacenes  lotes y ubicaciones
							$sqlCGA = "SELECT sum(COALESCE(bdi_quantity, 0)) as bdi_quantity, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode AND business = :business GROUP BY bdi_whscode, bdi_avgprice";
							$resCGA = $this->pedeo->queryTable($sqlCGA, array(
								':bdi_itemcode' => $detail['ec1_itemcode'],
								':bdi_whscode'  => $detail['ec1_whscode'],
								':business' => $Data['business']
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
	
								':bdi_itemcode'  => $detail['ec1_itemcode'],
								':bdi_whscode'   => $detail['ec1_whscode'],
								':bdi_ubication' => $detail['ec1_ubication'],
								':business' => $Data['business']
							));


							// se busca la cantidad general del articulo agrupando todos los almacenes  lotes y ubicaciones
							$sqlCGA = "SELECT sum(COALESCE(bdi_quantity, 0)) as bdi_quantity, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode AND business = :business GROUP BY bdi_whscode, bdi_avgprice";
							$resCGA = $this->pedeo->queryTable($sqlCGA, array(
								':bdi_itemcode' => $detail['ec1_itemcode'],
								':bdi_whscode'  => $detail['ec1_whscode'],
								':business' => $Data['business']
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
	
								':bdi_itemcode'  => $detail['ec1_itemcode'],
								':bdi_whscode'   => $detail['ec1_whscode'],
								':bdi_lote' 	 => $detail['ote_code'],
								':business' => $Data['business']
							));

							// se busca la cantidad general del articulo agrupando todos los almacenes  lotes y ubicaciones
							$sqlCGA = "SELECT sum(COALESCE(bdi_quantity, 0)) as bdi_quantity, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode AND business = :business GROUP BY bdi_whscode, bdi_avgprice";
							$resCGA = $this->pedeo->queryTable($sqlCGA, array(
								':bdi_itemcode' => $detail['ec1_itemcode'],
								':bdi_whscode'  => $detail['ec1_whscode'],
								':business' => $Data['business']
							));
	
							if (isset($resCGA[0]['bdi_quantity']) && is_numeric($resCGA[0]['bdi_quantity'])) {
	
								$CantidadPorAlmacen = $resCGA[0]['bdi_quantity'];
								$CostoPorAlmacen = $resCGA[0]['bdi_avgprice'];
							} else {
	
								$CantidadPorAlmacen = 0;
								$CostoPorAlmacen = 0;
							}
	

						}else{
							$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
												FROM tbdi
												WHERE bdi_itemcode = :bdi_itemcode
												AND bdi_whscode = :bdi_whscode
												AND business = :business";
		
							$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(
	
								':bdi_itemcode' => $detail['ec1_itemcode'],
								':bdi_whscode'  => $detail['ec1_whscode'],
								':business' => $Data['business']
							));
	
							$CantidadPorAlmacen = isset($resCostoCantidad[0]['bdi_quantity']) ? $resCostoCantidad[0]['bdi_quantity'] : 0;
							$CostoPorAlmacen = isset($resCostoCantidad[0]['bdi_avgprice']) ? $resCostoCantidad[0]['bdi_avgprice'] : 0;
						}
					}



					// SI EXISTE EL ITEM EN EL STOCK
					if (isset($resCostoCantidad[0])) {

						//SI TIENE CANTIDAD POSITIVA
						if ($resCostoCantidad[0]['bdi_quantity'] > 0 && $CantidadPorAlmacen > 0) {

							$CantidadItem = $resCostoCantidad[0]['bdi_quantity'];
							$CantidadActual = $CantidadPorAlmacen;
							$CostoActual = $resCostoCantidad[0]['bdi_avgprice'];

							$CantidadNueva = $this->generic->getCantInv($detail['ec1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE);

							//SE CALCULA EL PRECIO SEGUN LA CONVERSION DE UNIDADES
							$CostoNuevo = (($detail['ec1_price'] / $CANTUOMPURCHASE) * $CANTUOMSALE);
							//
							$CantidadTotal = ($CantidadActual + $CantidadNueva);
							$CantidadTotalItemSolo = ($CantidadItem + $CantidadNueva);

							if (trim($Data['cec_currency']) != $MONEDALOCAL) {
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
									'mensaje'	=> 'No se pudo crear la Entrada de Compra'
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
								':bdi_itemcode' => $detail['ec1_itemcode'],
								':bdi_whscode'  => $detail['ec1_whscode'],
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
									':bdi_itemcode' => $detail['ec1_itemcode'],
									':bdi_whscode'  => $detail['ec1_whscode'],
									':business' 	=> $Data['business']
								));

								if (is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad > 0) {
								} else {

									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'    => $resUpdateCostoCantidad,
										'mensaje'	=> 'No se pudo crear la Entrada de Compra'
									);

									$this->response($respuesta);

									return;
								}
							}
						} else {

							$CantidadActual = $resCostoCantidad[0]['bdi_quantity'];
							$CantidadNueva =  $this->generic->getCantInv($detail['ec1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE);
							$CostoNuevo = $detail['ec1_price'];


							$CantidadTotal = ($CantidadActual + $CantidadNueva);

							if (trim($Data['cec_currency']) != $MONEDALOCAL) {
								$CostoNuevo = ($CostoNuevo * $TasaDocLoc);
							}

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
								':bdi_avgprice' => $CostoNuevo,
								':bdi_itemcode' => $detail['ec1_itemcode'],
								':bdi_whscode'  => $detail['ec1_whscode'],
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
									':bdi_itemcode' => $detail['ec1_itemcode'],
									':bdi_whscode'  => $detail['ec1_whscode'],
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

							$CantidadNueva =  $this->generic->getCantInv($detail['ec1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE);

							//SE CALCULA EL PRECIO SEGUN LA CONVERSION DE UNIDADES
							$CostoNuevo = (($detail['ec1_price'] / $CANTUOMPURCHASE) * $CANTUOMSALE);
							//

							$CantidadTotal = ($CantidadActual + $CantidadNueva);
							$CantidadTotalItemSolo = ($CantidadItem + $CantidadNueva);

							if (trim($Data['cec_currency']) != $MONEDALOCAL) {
								$CostoNuevo = ($CostoNuevo * $TasaDocLoc);
							}

							$NuevoCostoPonderado = ($CantidadActual  *  $CostoActual) + ($CantidadNueva * $CostoNuevo);
							$NuevoCostoPonderado = round(($NuevoCostoPonderado / $CantidadTotal), $DECI_MALES);


							$sqlInsertCostoCantidad = '';
							$resInsertCostoCantidad =	[];

							if ( $ManejaUbicacion == 1 ){

								if ($ManejaLote == 1) {
									$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_lote, bdi_ubication, business)
														 VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_lote, :bdi_ubication, :business)";
	
	
									$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(
	
										':bdi_itemcode' => $detail['ec1_itemcode'],
										':bdi_whscode'  => $detail['ec1_whscode'],
										':bdi_quantity' => ($detail['ec1_quantity'] * $CANTUOMPURCHASE),
										':bdi_avgprice' => $NuevoCostoPonderado,
										':bdi_lote' 	=> $detail['ote_code'],
										':bdi_ubication'=> $detail['ec1_ubication'],
										':business' => $Data['business']
									));
								} else {
									$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_ubication, business)
									VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_ubication, :business)";


									$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

										':bdi_itemcode' => $detail['ec1_itemcode'],
										':bdi_whscode'  => $detail['ec1_whscode'],
										':bdi_quantity' => ($detail['ec1_quantity'] * $CANTUOMPURCHASE),
										':bdi_avgprice' => $NuevoCostoPonderado,
										':bdi_ubication'=> $detail['ec1_ubication'],
										':business' => $Data['business']
									));
								}
							}else{

								if ($ManejaLote == 1) {
									$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_lote, business)
														 VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_lote, :business)";
	
	
									$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(
	
										':bdi_itemcode' => $detail['ec1_itemcode'],
										':bdi_whscode'  => $detail['ec1_whscode'],
										':bdi_quantity' => ($detail['ec1_quantity'] * $CANTUOMPURCHASE),
										':bdi_avgprice' => $NuevoCostoPonderado,
										':bdi_lote' 	=> $detail['ote_code'],
										':business' 	=> $Data['business']
									));
								}else{
									$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, business)
									VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :business)";
	
	
									$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(
	
									':bdi_itemcode' => $detail['ec1_itemcode'],
									':bdi_whscode'  => $detail['ec1_whscode'],
									':bdi_quantity' => ($detail['ec1_quantity'] * $CANTUOMPURCHASE),
									':bdi_avgprice' => $NuevoCostoPonderado,
									':business' 	=> $Data['business']
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
									'mensaje'	=> 'No se pudo registrar la Entrada de Compra'
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

									':bdi_avgprice' => $NuevoCostoPonderado,
									':bdi_itemcode' => $detail['ec1_itemcode'],
									':bdi_whscode'  => $detail['ec1_whscode'],
									':business' => $Data['business']
								));



								if (is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad > 0) {
								} else {

									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'    => $resUpdateCostoCantidad,
										'mensaje'	=> 'No se pudo crear la Entrada de Compra'
									);

									$this->response($respuesta);

									return;
								}
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
								':bdi_itemcode' => $detail['ec1_itemcode'],
								':bdi_whscode'  => $detail['ec1_whscode'],
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

						} else {
							//SE CALCULA EL PRECIO SEGUN LA CONVERSION DE UNIDADES
							$CostoNuevo = (($detail['ec1_price'] / $CANTUOMPURCHASE) * $CANTUOMSALE);
							//
							if (trim($Data['cec_currency']) != $MONEDALOCAL) {
								$CostoNuevo = ($CostoNuevo * $TasaDocLoc);
							}

							//SE VALIDA SI EL ARTICULO MANEJA LOTE
							$sqlInsertCostoCantidad = '';
							$resInsertCostoCantidad =	[];


							if ( $ManejaUbicacion == 1 ){
								if ($ManejaLote == 1) {
									$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_lote, bdi_ubication, business)
															VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_lote, :bdi_ubication, :business)";
	
	
									$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(
	
										':bdi_itemcode'  => $detail['ec1_itemcode'],
										':bdi_whscode'   => $detail['ec1_whscode'],
										':bdi_quantity'  => $this->generic->getCantInv($detail['ec1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
										':bdi_avgprice'  => $CostoNuevo,
										':bdi_lote' 	 => $detail['ote_code'],
										':bdi_ubication' => $detail['ec1_ubication'],
										':business' 	 => $Data['business']
									));
								}else{
									$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_ubication, business)
									VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_ubication, :business)";


									$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

										':bdi_itemcode'  => $detail['ec1_itemcode'],
										':bdi_whscode'   => $detail['ec1_whscode'],
										':bdi_quantity'  => $this->generic->getCantInv($detail['ec1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
										':bdi_avgprice'  => $CostoNuevo,
										':bdi_ubication' => $detail['ec1_ubication'],
										':business' 	=> $Data['business']
									));
								}
							}else{
								if ($ManejaLote == 1) {
									$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_lote, business)
															VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_lote, :business)";
	
	
									$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(
	
										':bdi_itemcode' => $detail['ec1_itemcode'],
										':bdi_whscode'  => $detail['ec1_whscode'],
										':bdi_quantity' => $this->generic->getCantInv($detail['ec1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
										':bdi_avgprice' => $CostoNuevo,
										':bdi_lote' 	=> $detail['ote_code'],
										':business' 	=> $Data['business']
									));
								} else {
	
									$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, business)
															VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :business)";
	
	
									$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(
	
										':bdi_itemcode' => $detail['ec1_itemcode'],
										':bdi_whscode'  => $detail['ec1_whscode'],
										':bdi_quantity' => ($detail['ec1_quantity'] * $CANTUOMPURCHASE),
										':bdi_avgprice' => $CostoNuevo,
										':business' => $Data['business']
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
									'mensaje'	=> 'No se pudo registrar la Entrada de Compra'
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
								':bdi_itemcode' => $detail['ec1_itemcode'],
								':bdi_whscode'  => $detail['ec1_whscode'],
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
									':bdi_itemcode' => $detail['ec1_itemcode'],
									':bdi_whscode'  => $detail['ec1_whscode'],
									':business' 	=> $Data['business']
								));



								if (is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad > 0) {
								} else {

									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'    => $resUpdateCostoCantidad,
										'mensaje'	=> 'No se pudo crear la Entrada de Compra'
									);

									$this->response($respuesta);

									return;
								}
							}
						}
					}
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
								':ote_createby' => $Data['cec_createby'],
								':ote_date' => date('Y-m-d'),
								':ote_baseentry' => $resInsert,
								':ote_basetype' => $Data['cec_doctype'],
								':ote_docnum' => $DocNumVerificado
							));


							if (is_numeric($resInsertLote) && $resInsertLote > 0) {
							} else {
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' 		=> $resInsertLote,
									'mensaje'	=> 'No se pudo registrar la entrada en compras'
								);

								$this->response($respuesta);

								return;
							}
						}
					}
					//FIN VALIDACION DEL LOTE

				}

				// TERMINA LA AGREGACION DEL ITEM, COSTO PONDERADO

				//LLENANDO DETALLE ASIENTO CONTABLES

				$DetalleAsientoIngreso = new stdClass();
				$DetalleAsientoIva = new stdClass();


				$DetalleAsientoIngreso->ac1_account = is_numeric($detail['ec1_acctcode']) ? $detail['ec1_acctcode'] : 0;
				$DetalleAsientoIngreso->ac1_prc_code = isset($detail['ec1_costcode']) ? $detail['ec1_costcode'] : NULL;
				$DetalleAsientoIngreso->ac1_uncode = isset($detail['ec1_ubusiness']) ? $detail['ec1_ubusiness'] : NULL;
				$DetalleAsientoIngreso->ac1_prj_code = isset($detail['ec1_project']) ? $detail['ec1_project'] : NULL;
				$DetalleAsientoIngreso->ec1_linetotal = is_numeric($detail['ec1_linetotal']) ? $detail['ec1_linetotal'] : 0;
				$DetalleAsientoIngreso->ec1_vat = is_numeric($detail['ec1_vat']) ? $detail['ec1_vat'] : 0;
				$DetalleAsientoIngreso->ec1_vatsum = is_numeric($detail['ec1_vatsum']) ? $detail['ec1_vatsum'] : 0;
				$DetalleAsientoIngreso->ec1_price = is_numeric($detail['ec1_price']) ? $detail['ec1_price'] : 0;
				$DetalleAsientoIngreso->ec1_itemcode = isset($detail['ec1_itemcode']) ? $detail['ec1_itemcode'] : NULL;
				$DetalleAsientoIngreso->ec1_quantity = is_numeric($detail['ec1_quantity']) ? ($detail['ec1_quantity'] * $CANTUOMPURCHASE) : 0;
				$DetalleAsientoIngreso->ec1_whscode = isset($detail['ec1_whscode']) ? $detail['ec1_whscode'] : NULL;



				$DetalleAsientoIva->ac1_account = is_numeric($detail['ec1_acctcode']) ? $detail['ec1_acctcode'] : 0;
				$DetalleAsientoIva->ac1_prc_code = isset($detail['ec1_costcode']) ? $detail['ec1_costcode'] : NULL;
				$DetalleAsientoIva->ac1_uncode = isset($detail['ec1_ubusiness']) ? $detail['ec1_ubusiness'] : NULL;
				$DetalleAsientoIva->ac1_prj_code = isset($detail['ec1_project']) ? $detail['ec1_project'] : NULL;
				$DetalleAsientoIva->ec1_linetotal = is_numeric($detail['ec1_linetotal']) ? $detail['ec1_linetotal'] : 0;
				$DetalleAsientoIva->ec1_vat = is_numeric($detail['ec1_vat']) ? $detail['ec1_vat'] : 0;
				$DetalleAsientoIva->ec1_vatsum = is_numeric($detail['ec1_vatsum']) ? $detail['ec1_vatsum'] : 0;
				$DetalleAsientoIva->ec1_price = is_numeric($detail['ec1_price']) ? $detail['ec1_price'] : 0;
				$DetalleAsientoIva->ec1_itemcode = isset($detail['ec1_itemcode']) ? $detail['ec1_itemcode'] : NULL;
				$DetalleAsientoIva->ec1_quantity = is_numeric($detail['ec1_quantity']) ? ($detail['ec1_quantity'] * $CANTUOMPURCHASE) : 0;
				$DetalleAsientoIva->ec1_cuentaIva = is_numeric($detail['ec1_cuentaIva']) ? $detail['ec1_cuentaIva'] : NULL;
				$DetalleAsientoIva->ec1_whscode = isset($detail['ec1_whscode']) ? $detail['ec1_whscode'] : NULL;


				if ($ManejaInvetario  == 1) {
					$DetalleCostoInventario = new stdClass();
					$DetalleCostoCosto = new stdClass();


					$DetalleCostoInventario->ac1_account = is_numeric($detail['ec1_acctcode']) ? $detail['ec1_acctcode'] : 0;
					$DetalleCostoInventario->ac1_prc_code = isset($detail['ec1_costcode']) ? $detail['ec1_costcode'] : NULL;
					$DetalleCostoInventario->ac1_uncode = isset($detail['ec1_ubusiness']) ? $detail['ec1_ubusiness'] : NULL;
					$DetalleCostoInventario->ac1_prj_code = isset($detail['ec1_project']) ? $detail['ec1_project'] : NULL;
					$DetalleCostoInventario->ec1_linetotal = is_numeric($detail['ec1_linetotal']) ? $detail['ec1_linetotal'] : 0;
					$DetalleCostoInventario->ec1_vat = is_numeric($detail['ec1_vat']) ? $detail['ec1_vat'] : 0;
					$DetalleCostoInventario->ec1_vatsum = is_numeric($detail['ec1_vatsum']) ? $detail['ec1_vatsum'] : 0;
					$DetalleCostoInventario->ec1_price = is_numeric($detail['ec1_price']) ? $detail['ec1_price'] : 0;
					$DetalleCostoInventario->ec1_itemcode = isset($detail['ec1_itemcode']) ? $detail['ec1_itemcode'] : NULL;
					$DetalleCostoInventario->ec1_quantity = is_numeric($detail['ec1_quantity']) ? ($detail['ec1_quantity'] * $CANTUOMPURCHASE) : 0;
					$DetalleCostoInventario->ec1_whscode = isset($detail['ec1_whscode']) ? $detail['ec1_whscode'] : NULL;
					$DetalleCostoInventario->ec1_inventory = 	$ManejaInvetario;


					$DetalleCostoCosto->ac1_account = is_numeric($detail['ec1_acctcode']) ? $detail['ec1_acctcode'] : 0;
					$DetalleCostoCosto->ac1_prc_code = isset($detail['ec1_costcode']) ? $detail['ec1_costcode'] : NULL;
					$DetalleCostoCosto->ac1_uncode = isset($detail['ec1_ubusiness']) ? $detail['ec1_ubusiness'] : NULL;
					$DetalleCostoCosto->ac1_prj_code = isset($detail['ec1_project']) ? $detail['ec1_project'] : NULL;
					$DetalleCostoCosto->ec1_linetotal = is_numeric($detail['ec1_linetotal']) ? $detail['ec1_linetotal'] : 0;
					$DetalleCostoCosto->ec1_vat = is_numeric($detail['ec1_vat']) ? $detail['ec1_vat'] : 0;
					$DetalleCostoCosto->ec1_vatsum = is_numeric($detail['ec1_vatsum']) ? $detail['ec1_vatsum'] : 0;
					$DetalleCostoCosto->ec1_price = is_numeric($detail['ec1_price']) ? $detail['ec1_price'] : 0;
					$DetalleCostoCosto->ec1_itemcode = isset($detail['ec1_itemcode']) ? $detail['ec1_itemcode'] : NULL;
					$DetalleCostoCosto->ec1_quantity = is_numeric($detail['ec1_quantity']) ? ($detail['ec1_quantity'] * $CANTUOMPURCHASE) : 0;
					$DetalleCostoCosto->ec1_whscode = isset($detail['ec1_whscode']) ? $detail['ec1_whscode'] : NULL;
					$DetalleCostoCosto->ec1_inventory = 	$ManejaInvetario;
				}


				$codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);
				$DetalleAsientoIngreso->codigoCuenta = $codigoCuenta;
				$DetalleAsientoIva->codigoCuenta = $codigoCuenta;

				if ($ManejaInvetario == 1) {
					$DetalleCostoInventario->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);
					$DetalleCostoCosto->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);

					$llaveCostoInventario = $DetalleCostoInventario->ac1_account;
					$llaveCostoCosto = $DetalleCostoCosto->ac1_account;
				}




				$llave = $DetalleAsientoIngreso->ac1_uncode . $DetalleAsientoIngreso->ac1_prc_code . $DetalleAsientoIngreso->ac1_prj_code . $DetalleAsientoIngreso->ac1_account;
				$llaveIva = $DetalleAsientoIva->ec1_vat;



				if (in_array($llave, $inArrayIngreso)) {

					$posicion = $this->buscarPosicion($llave, $inArrayIngreso);
				} else {

					array_push($inArrayIngreso, $llave);
					$posicion = $this->buscarPosicion($llave, $inArrayIngreso);
				}


				if (in_array($llaveIva, $inArrayIva)) {

					$posicionIva = $this->buscarPosicion($llaveIva, $inArrayIva);
				} else {

					array_push($inArrayIva, $llaveIva);
					$posicionIva = $this->buscarPosicion($llaveIva, $inArrayIva);
				}


				if ($ManejaInvetario == 1) {
					if (in_array($llaveCostoInventario, $inArrayCostoInventario)) {

						$posicionCostoInventario = $this->buscarPosicion($llaveCostoInventario, $inArrayCostoInventario);
					} else {

						array_push($inArrayCostoInventario, $llaveCostoInventario);
						$posicionCostoInventario = $this->buscarPosicion($llaveCostoInventario, $inArrayCostoInventario);
					}


					if (in_array($llaveCostoCosto, $inArrayCostoCosto)) {

						$posicionCostoCosto = $this->buscarPosicion($llaveCostoCosto, $inArrayCostoCosto);
					} else {

						array_push($inArrayCostoCosto, $llaveCostoCosto);
						$posicionCostoCosto = $this->buscarPosicion($llaveCostoCosto, $inArrayCostoCosto);
					}
				}





				if (isset($DetalleConsolidadoIva[$posicionIva])) {

					if (!is_array($DetalleConsolidadoIva[$posicionIva])) {
						$DetalleConsolidadoIva[$posicionIva] = array();
					}
				} else {
					$DetalleConsolidadoIva[$posicionIva] = array();
				}

				array_push($DetalleConsolidadoIva[$posicionIva], $DetalleAsientoIva);


				if (isset($DetalleConsolidadoIngreso[$posicion])) {

					if (!is_array($DetalleConsolidadoIngreso[$posicion])) {
						$DetalleConsolidadoIngreso[$posicion] = array();
					}
				} else {
					$DetalleConsolidadoIngreso[$posicion] = array();
				}

				array_push($DetalleConsolidadoIngreso[$posicion], $DetalleAsientoIngreso);



				if ($ManejaInvetario == 1) {
					if (isset($DetalleConsolidadoCostoInventario[$posicionCostoInventario])) {

						if (!is_array($DetalleConsolidadoCostoInventario[$posicionCostoInventario])) {
							$DetalleConsolidadoCostoInventario[$posicionCostoInventario] = array();
						}
					} else {
						$DetalleConsolidadoCostoInventario[$posicionCostoInventario] = array();
					}

					array_push($DetalleConsolidadoCostoInventario[$posicionCostoInventario], $DetalleCostoInventario);


					if (isset($DetalleConsolidadoCostoCosto[$posicionCostoCosto])) {

						if (!is_array($DetalleConsolidadoCostoCosto[$posicionCostoCosto])) {
							$DetalleConsolidadoCostoCosto[$posicionCostoCosto] = array();
						}
					} else {
						$DetalleConsolidadoCostoCosto[$posicionCostoCosto] = array();
					}

					array_push($DetalleConsolidadoCostoCosto[$posicionCostoCosto], $DetalleCostoCosto);
				}
			}

			//FIN PROCEDIMEINTO PARA INGRESAR EL DETALLE DE LA ENTRADA DE COMPRA



			//Procedimiento para llenar costo inventario
			foreach ($DetalleConsolidadoCostoInventario as $key => $posicion) {
				$grantotalCostoInventario = 0;
				$cuentaInventario = "";
				$sinDatos = 0;
				$dbito = 0;
				$cdito = 0;
				$MontoSysDB = 0;
				$MontoSysCR = 0;

				foreach ($posicion as $key => $value) {

					// SE ACEPTAN SOLO LOS ARTICULOS QUE SON INVENTARIABLES
					if ($value->ec1_inventory == 1 || $value->ec1_inventory  == '1') {

						$sinDatos++;
						$cuentaInventario = $value->ac1_account;
						$grantotalCostoInventario = ($grantotalCostoInventario + $value->ec1_linetotal);
					}
				}

				// SE VALIDA QUE EXISTA UN ARTICULO INVENTARIABLE
				if ($sinDatos > 0) {

					$codigo3 = substr($cuentaInventario, 0, 1);

					if ($codigo3 == 1 || $codigo3 == "1") {

						if (trim($Data['cec_currency']) != $MONEDALOCAL) {
							$grantotalCostoInventario = ($grantotalCostoInventario * $TasaDocLoc);
						}

						$dbito = $grantotalCostoInventario;  // se invierte la naturaleza
						$MontoSysDB = ($dbito / $TasaLocSys);  // se invierte la naturaleza

					} else if ($codigo3 == 2 || $codigo3 == "2") {

						if (trim($Data['cec_currency']) != $MONEDALOCAL) {
							$grantotalCostoInventario = ($grantotalCostoInventario * $TasaDocLoc);
						}

						$dbito = $grantotalCostoInventario;
						$MontoSysDB = ($dbito / $TasaLocSys);
					} else if ($codigo3 == 3 || $codigo3 == "3") {

						if (trim($Data['cec_currency']) != $MONEDALOCAL) {
							$grantotalCostoInventario = ($grantotalCostoInventario * $TasaDocLoc);
						}

						$dbito = $grantotalCostoInventario;
						$MontoSysDB = ($dbito / $TasaLocSys);
					} else if ($codigo3 == 4 || $codigo3 == "4") {

						if (trim($Data['cec_currency']) != $MONEDALOCAL) {
							$grantotalCostoInventario = ($grantotalCostoInventario * $TasaDocLoc);
						}

						$dbito = $grantotalCostoInventario;
						$MontoSysDB = ($dbito / $TasaLocSys);
					} else if ($codigo3 == 5  || $codigo3 == "5") {

						if (trim($Data['cec_currency']) != $MONEDALOCAL) {
							$grantotalCostoInventario = ($grantotalCostoInventario * $TasaDocLoc);
						}

						$dbito = $grantotalCostoInventario;
						$MontoSysDB = ($dbito / $TasaLocSys);
					} else if ($codigo3 == 6 || $codigo3 == "6") {

						if (trim($Data['cec_currency']) != $MONEDALOCAL) {
							$grantotalCostoInventario = ($grantotalCostoInventario * $TasaDocLoc);
						}

						$dbito = $grantotalCostoInventario;
						$MontoSysDB = ($dbito /  $TasaLocSys);
					} else if ($codigo3 == 7 || $codigo3 == "7") {

						if (trim($Data['cec_currency']) != $MONEDALOCAL) {
							$grantotalCostoInventario = ($grantotalCostoInventario * $TasaDocLoc);
						}

						$dbito = $grantotalCostoInventario;
						$MontoSysDB = ($dbito / $TasaLocSys);
					}

					$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

						':ac1_trans_id' => $resInsertAsiento,
						':ac1_account' => $cuentaInventario,
						':ac1_debit' => round($dbito, $DECI_MALES),
						':ac1_credit' => round($cdito, $DECI_MALES),
						':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
						':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
						':ac1_currex' => 0,
						':ac1_doc_date' => $this->validateDate($Data['cec_docdate']) ? $Data['cec_docdate'] : NULL,
						':ac1_doc_duedate' => $this->validateDate($Data['cec_duedate']) ? $Data['cec_duedate'] : NULL,
						':ac1_debit_import' => 0,
						':ac1_credit_import' => 0,
						':ac1_debit_importsys' => 0,
						':ac1_credit_importsys' => 0,
						':ac1_font_key' => $resInsert,
						':ac1_font_line' => 1,
						':ac1_font_type' => is_numeric($Data['cec_doctype']) ? $Data['cec_doctype'] : 0,
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
						':ac1_made_user' => isset($Data['cec_createby']) ? $Data['cec_createby'] : NULL,
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
						':ac1_legal_num' => isset($Data['cec_cardcode']) ? $Data['cec_cardcode'] : NULL,
						':ac1_codref' => 1
					));

					if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
						// Se verifica que el detalle no de error insertando //
					} else {

						// si falla algun insert del detalle de la Entrada de Compras se devuelven los cambios realizados por la transaccion,
						// se retorna el error y se detiene la ejecucion del codigo restante.
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'	  => $resDetalleAsiento,
							'mensaje'	=> 'No se pudo registrar la entrada de compras 3'
						);

						$this->response($respuesta);

						return;
					}
				}
			}

			//FIN Procedimiento para llenar costo inventario

			// Procedimiento para llenar costo costo

			//se busca la cuenta puente de inventario
			$sqlArticulo = "SELECT coalesce(pge_bridge_inv_purch, 0) as pge_bridge_inv_purch, coalesce(pge_bridge_purch_int, 0) as pge_bridge_purch_int FROM pgem WHERE pge_id = :business";
			$resArticulo = $this->pedeo->queryTable($sqlArticulo, array(':business' => $Data['business']));
			$cuentaCosto = "";
			if (isset($resArticulo[0]) && $resArticulo[0]['pge_bridge_inv_purch'] != 0) {

				if (isset($Data['cec_api']) && $Data['cec_api'] == 1) {
					$cuentaCosto = $resArticulo[0]['pge_bridge_purch_int'];
				} else {
					$cuentaCosto = $resArticulo[0]['pge_bridge_inv_purch'];
				}
			} else {
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'	  => $resArticulo,
					'mensaje'	=> 'No se pudo registrar la entrada de compras, no se encontro la cuenta puente de inventario'
				);

				$this->response($respuesta);

				return;
			}

			foreach ($DetalleConsolidadoCostoCosto as $key => $posicion) {
				$grantotalCostoCosto = 0;
				$dbito = 0;
				$cdito = 0;
				$MontoSysDB = 0;
				$MontoSysCR = 0;

				$sinDatos = 0; // SE ULTILIZA PARA VALIDAR QUE NO EXISTA NINGUN ITEM INVENTARIO
				foreach ($posicion as $key => $value) {

					// ENTRA SOLO SI EL ARTICULO ES INVENTARIABLE

					if ($value->ec1_inventory == 1 || $value->ec1_inventory  == '1') {

						$sinDatos++;
						$grantotalCostoCosto = ($grantotalCostoCosto + $value->ec1_linetotal);
					}
				}

				// SE VALIDA QUE EXISTA MINIMO UN ARTICULO
				if ($sinDatos > 0) {

					$codigo3 = substr($cuentaCosto, 0, 1);

					if ($codigo3 == 1 || $codigo3 == "1") {

						if (trim($Data['cec_currency']) != $MONEDALOCAL) {
							$grantotalCostoCosto = ($grantotalCostoCosto * $TasaDocLoc);
						}

						$cdito = 	$grantotalCostoCosto;  // Se cambio la naturaleza
						$MontoSysCR = ($cdito / $TasaLocSys); // Se cambio la naturaleza

					} else if ($codigo3 == 2 || $codigo3 == "2") {

						if (trim($Data['cec_currency']) != $MONEDALOCAL) {
							$grantotalCostoCosto = ($grantotalCostoCosto * $TasaDocLoc);
						}

						$cdito = 	$grantotalCostoCosto;
						$MontoSysCR = ($cdito / $TasaLocSys);
					} else if ($codigo3 == 3 || $codigo3 == "3") {

						if (trim($Data['cec_currency']) != $MONEDALOCAL) {
							$grantotalCostoCosto = ($grantotalCostoCosto * $TasaDocLoc);
						}

						$cdito = 	$grantotalCostoCosto;
						$MontoSysCR = ($cdito / $TasaLocSys);
					} else if ($codigo3 == 4 || $codigo3 == "4") {

						if (trim($Data['cec_currency']) != $MONEDALOCAL) {
							$grantotalCostoCosto = ($grantotalCostoCosto * $TasaDocLoc);
						}

						$cdito = 	$grantotalCostoCosto;
						$MontoSysCR = ($cdito / $TasaLocSys);
					} else if ($codigo3 == 5  || $codigo3 == "5") {

						if (trim($Data['cec_currency']) != $MONEDALOCAL) {
							$grantotalCostoCosto = ($grantotalCostoCosto * $TasaDocLoc);
						}

						$cdito = 	$grantotalCostoCosto;
						$MontoSysCR = ($cdito / $TasaLocSys);
					} else if ($codigo3 == 6 || $codigo3 == "6") {

						if (trim($Data['cec_currency']) != $MONEDALOCAL) {
							$grantotalCostoCosto = ($grantotalCostoCosto * $TasaDocLoc);
						}

						$cdito = 	$grantotalCostoCosto;
						$MontoSysCR = ($cdito / $TasaLocSys);
					} else if ($codigo3 == 7 || $codigo3 == "7") {

						if (trim($Data['cec_currency']) != $MONEDALOCAL) {
							$grantotalCostoCosto = ($grantotalCostoCosto * $TasaDocLoc);
						}

						$cdito = 	$grantotalCostoCosto;
						$MontoSysCR = ($cdito / $TasaLocSys);
					}

					$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

						':ac1_trans_id' => $resInsertAsiento,
						':ac1_account' => $cuentaCosto,
						':ac1_debit' => round($dbito, $DECI_MALES),
						':ac1_credit' => round($cdito, $DECI_MALES),
						':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
						':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
						':ac1_currex' => 0,
						':ac1_doc_date' => $this->validateDate($Data['cec_docdate']) ? $Data['cec_docdate'] : NULL,
						':ac1_doc_duedate' => $this->validateDate($Data['cec_duedate']) ? $Data['cec_duedate'] : NULL,
						':ac1_debit_import' => 0,
						':ac1_credit_import' => 0,
						':ac1_debit_importsys' => 0,
						':ac1_credit_importsys' => 0,
						':ac1_font_key' => $resInsert,
						':ac1_font_line' => 1,
						':ac1_font_type' => is_numeric($Data['cec_doctype']) ? $Data['cec_doctype'] : 0,
						':ac1_accountvs' => 1,
						':ac1_doctype' => 18,
						':ac1_ref1' => "",
						':ac1_ref2' => "",
						':ac1_ref3' => "",
						':ac1_prc_code' => $value->ac1_prc_code,
						':ac1_uncode' => $value->ac1_uncode,
						':ac1_prj_code' => $value->ac1_prj_code,
						':ac1_rescon_date' => NULL,
						':ac1_recon_total' => 0,
						':ac1_made_user' => isset($Data['cec_createby']) ? $Data['cec_createby'] : NULL,
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
						':ac1_legal_num' => isset($Data['cec_cardcode']) ? $Data['cec_cardcode'] : NULL,
						':ac1_codref' => 1
					));

					if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
						// Se verifica que el detalle no de error insertando //
					} else {

						// si falla algun insert del detalle de la entrada de compras se devuelven los cambios realizados por la transaccion,
						// se retorna el error y se detiene la ejecucion del codigo restante.
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'	  => $resDetalleAsiento,
							'mensaje'	=> 'No se pudo registrar la entrada de compras 4'
						);

						$this->response($respuesta);

						return;
					}
				}
			}
			//FIN Procedimiento para llenar costo costo
			//PROCEDIMIENTO PARA CERRAR ESTADO DE DOCUMENTO DE ORIGEN

			if ($Data['cec_basetype'] == 12) {


				$sqlEstado1 = "SELECT distinct
												 count(t1.po1_itemcode) item,
												 sum(t1.po1_quantity) cantidad
												 from dcpo t0
												 inner join cpo1 t1 on t0.cpo_docentry = t1.po1_docentry
												where t0.cpo_docentry = :cpo_docentry and t0.cpo_doctype = :cpo_doctype";


				$resEstado1 = $this->pedeo->queryTable($sqlEstado1, array(
					':cpo_docentry' => $Data['cec_baseentry'],
					':cpo_doctype' => $Data['cec_basetype']
				));

				$sqlEstado2 = "SELECT distinct
												coalesce(count(distinct t3.ec1_itemcode),0) item,
												coalesce(sum(t3.ec1_quantity),0) cantidad
												from dcpo t0
												left join cpo1 t1 on t0.cpo_docentry = t1.po1_docentry
												left join dcec t2 on t0.cpo_docentry = t2.cec_baseentry  and t0.cpo_doctype = t2.cec_basetype
												left join cec1 t3 on t2.cec_docentry = t3.ec1_docentry and t1.po1_itemcode = t3.ec1_itemcode
											 where t0.cpo_docentry = :cpo_docentry and t0.cpo_doctype = :cpo_doctype";


				$resEstado2 = $this->pedeo->queryTable($sqlEstado2, array(
					':cpo_docentry' => $Data['cec_baseentry'],
					':cpo_doctype' => $Data['cec_basetype']
				));


				$item_ord = $resEstado1[0]['item'];
				$item_ec = $resEstado2[0]['item'];
				$cantidad_ord = $resEstado1[0]['cantidad'];
				$cantidad_ec = $resEstado2[0]['cantidad'];


				if ($item_ord == $item_ec  && $cantidad_ord == $cantidad_ec) {

					$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																		 VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

					$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


						':bed_docentry' => $Data['cec_baseentry'],
						':bed_doctype' => $Data['cec_basetype'],
						':bed_status' => 3, //ESTADO CERRADO
						':bed_createby' => $Data['cec_createby'],
						':bed_date' => date('Y-m-d'),
						':bed_baseentry' => $resInsert,
						':bed_basetype' => $Data['cec_doctype']
					));


					if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' => $resInsertEstado,
							'mensaje'	=> 'No se pudo registrar la entrada de compras 6'
						);


						$this->response($respuesta);

						return;
					}
				}
			}

			//SE VALIDA LA CONTABILIDAD CREADA
			if ($ResultadoInv == 1) {
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
			}
			//

			// Si todo sale bien despues de insertar el detalle de la cotizacion
			// se confirma la trasaccion  para que los cambios apliquen permanentemente
			// en la base de datos y se confirma la operacion exitosa.
			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'Entrada de compra registrada con exito'
			);
		} else {
			// Se devuelven los cambios realizados en la transaccion
			// si occurre un error  y se muestra devuelve el error.
			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data' => $resInsert,
				'mensaje'	=> 'No se pudo registrar la entrada de compra'
			);


			$this->response($respuesta);

			return;
		}

		$this->response($respuesta);
	}

	//ACTUALIZAR ORDEN DE COMPRA
	public function updatePurchaseEc_post()
	{

		$Data = $this->post();

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
				'mensaje' => 'No se encontro el detalle de la entrada de compra'
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

		$sqlUpdate = "UPDATE dcec	SET cec_docdate=:cec_docdate,cec_duedate=:cec_duedate, cec_duedev=:cec_duedev, cec_pricelist=:cec_pricelist, cec_cardcode=:cec_cardcode,
			  						cec_cardname=:cec_cardname, cec_currency=:cec_currency, cec_contacid=:cec_contacid, cec_slpcode=:cec_slpcode,
										cec_empid=:cec_empid, cec_comment=:cec_comment, cec_doctotal=:cec_doctotal, cec_baseamnt=:cec_baseamnt,
										cec_taxtotal=:cec_taxtotal, cec_discprofit=:cec_discprofit, cec_discount=:cec_discount, cec_createat=:cec_createat,
										cec_baseentry=:cec_baseentry, cec_basetype=:cec_basetype, cec_doctype=:cec_doctype, cec_idadd=:cec_idadd,
										cec_adress=:cec_adress, cec_paytype=:cec_paytype WHERE cec_docentry=:cec_docentry";

		$this->pedeo->trans_begin();

		$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
			':cec_docdate' => $this->validateDate($Data['cec_docdate']) ? $Data['cec_docdate'] : NULL,
			':cec_duedate' => $this->validateDate($Data['cec_duedate']) ? $Data['cec_duedate'] : NULL,
			':cec_duedev' => $this->validateDate($Data['cec_duedev']) ? $Data['cec_duedev'] : NULL,
			':cec_pricelist' => is_numeric($Data['cec_pricelist']) ? $Data['cec_pricelist'] : 0,
			':cec_cardcode' => isset($Data['cec_cardcode']) ? $Data['cec_cardcode'] : NULL,
			':cec_cardname' => isset($Data['cec_cardname']) ? $Data['cec_cardname'] : NULL,
			':cec_currency' => isset($Data['cec_currency']) ? $Data['cec_currency'] : NULL,
			':cec_contacid' => isset($Data['cec_contacid']) ? $Data['cec_contacid'] : NULL,
			':cec_slpcode' => is_numeric($Data['cec_slpcode']) ? $Data['cec_slpcode'] : 0,
			':cec_empid' => is_numeric($Data['cec_empid']) ? $Data['cec_empid'] : 0,
			':cec_comment' => isset($Data['cec_comment']) ? $Data['cec_comment'] : NULL,
			':cec_doctotal' => is_numeric($Data['cec_doctotal']) ? $Data['cec_doctotal'] : 0,
			':cec_baseamnt' => is_numeric($Data['cec_baseamnt']) ? $Data['cec_baseamnt'] : 0,
			':cec_taxtotal' => is_numeric($Data['cec_taxtotal']) ? $Data['cec_taxtotal'] : 0,
			':cec_discprofit' => is_numeric($Data['cec_discprofit']) ? $Data['cec_discprofit'] : 0,
			':cec_discount' => is_numeric($Data['cec_discount']) ? $Data['cec_discount'] : 0,
			':cec_createat' => $this->validateDate($Data['cec_createat']) ? $Data['cec_createat'] : NULL,
			':cec_baseentry' => is_numeric($Data['cec_baseentry']) ? $Data['cec_baseentry'] : 0,
			':cec_basetype' => is_numeric($Data['cec_basetype']) ? $Data['cec_basetype'] : 0,
			':cec_doctype' => is_numeric($Data['cec_doctype']) ? $Data['cec_doctype'] : 0,
			':cec_idadd' => isset($Data['cec_idadd']) ? $Data['cec_idadd'] : NULL,
			':cec_adress' => isset($Data['cec_adress']) ? $Data['cec_adress'] : NULL,
			':cec_paytype' => is_numeric($Data['cec_paytype']) ? $Data['cec_paytype'] : 0,
			':cec_docentry' => $Data['cec_docentry']
		));

		if (is_numeric($resUpdate) && $resUpdate == 1) {

			$this->pedeo->queryTable("DELETE FROM cec1 WHERE ec1_docentry=:ec1_docentry", array(':ec1_docentry' => $Data['cec_docentry']));

			foreach ($ContenidoDetalle as $key => $detail) {

				$sqlInsertDetail = "INSERT INTO cec1(ec1_docentry, ec1_itemcode, ec1_itemname, ec1_quantity, ec1_uom, ec1_whscode,
																			ec1_price, ec1_vat, ec1_vatsum, ec1_discount, ec1_linetotal, ec1_costcode, ec1_ubusiness, ec1_project,
																			ec1_acctcode, ec1_basetype, ec1_doctype, ec1_avprice, ec1_inventory, ec1_acciva, ec1_linenum, ec1_ubication)VALUES(:ec1_docentry, :ec1_itemcode, :ec1_itemname, :ec1_quantity,
																			:ec1_uom, :ec1_whscode,:ec1_price, :ec1_vat, :ec1_vatsum, :ec1_discount, :ec1_linetotal, :ec1_costcode, :ec1_ubusiness, :ec1_project,
																			:ec1_acctcode, :ec1_basetype, :ec1_doctype, :ec1_avprice, :ec1_inventory, :ec1_acciva,:ec1_linenum, :ec1_ubication)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':ec1_docentry' => $Data['cec_docentry'],
					':ec1_itemcode' => isset($detail['ec1_itemcode']) ? $detail['ec1_itemcode'] : NULL,
					':ec1_itemname' => isset($detail['ec1_itemname']) ? $detail['ec1_itemname'] : NULL,
					':ec1_quantity' => is_numeric($detail['ec1_quantity']) ? $detail['ec1_quantity'] : 0,
					':ec1_uom' => isset($detail['ec1_uom']) ? $detail['ec1_uom'] : NULL,
					':ec1_whscode' => isset($detail['ec1_whscode']) ? $detail['ec1_whscode'] : NULL,
					':ec1_price' => is_numeric($detail['ec1_price']) ? $detail['ec1_price'] : 0,
					':ec1_vat' => is_numeric($detail['ec1_vat']) ? $detail['ec1_vat'] : 0,
					':ec1_vatsum' => is_numeric($detail['ec1_vatsum']) ? $detail['ec1_vatsum'] : 0,
					':ec1_discount' => is_numeric($detail['ec1_discount']) ? $detail['ec1_discount'] : 0,
					':ec1_linetotal' => is_numeric($detail['ec1_linetotal']) ? $detail['ec1_linetotal'] : 0,
					':ec1_costcode' => isset($detail['ec1_costcode']) ? $detail['ec1_costcode'] : NULL,
					':ec1_ubusiness' => isset($detail['ec1_ubusiness']) ? $detail['ec1_ubusiness'] : NULL,
					':ec1_project' => isset($detail['ec1_project']) ? $detail['ec1_project'] : NULL,
					':ec1_acctcode' => is_numeric($detail['ec1_acctcode']) ? $detail['ec1_acctcode'] : 0,
					':ec1_basetype' => is_numeric($detail['ec1_basetype']) ? $detail['ec1_basetype'] : 0,
					':ec1_doctype' => is_numeric($detail['ec1_doctype']) ? $detail['ec1_doctype'] : 0,
					':ec1_avprice' => is_numeric($detail['ec1_avprice']) ? $detail['ec1_avprice'] : 0,
					':ec1_inventory' => is_numeric($detail['ec1_inventory']) ? $detail['ec1_inventory'] : NULL,
					':ec1_acciva' => is_numeric($detail['ec1_acciva']) ? $detail['ec1_acciva'] : NULL,
					':ec1_linenum' => is_numeric($detail['ec1_linenum']) ? $detail['ec1_linenum'] : NULL,
					':ec1_ubication' => is_numeric($detail['ec1_ubication']) ? $detail['ec1_ubication'] : NULL
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
						'mensaje'	=> 'No se pudo registrar la entrada de compra 8'
					);

					$this->response($respuesta);

					return;
				}
			}


			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resUpdate,
				'mensaje' => 'Entrada de compra actualizada con exito'
			);
		} else {

			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data'    => $resUpdate,
				'mensaje'	=> 'No se pudo actualizar la entrada de compra'
			);

			$this->response($respuesta);

			return;
		}

		$this->response($respuesta);
	}


	//OBTENER orden de compra
	public function getPurchaseEc_get()
	{
		$Data = $this->get();

		if ( !isset($Data['business']) OR !isset($Data['branch']) ) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$DECI_MALES =  $this->generic->getDecimals();

		$sqlSelect = self::getColumn('dcec', 'cec', '', '', $DECI_MALES, $Data['business'], $Data['branch']);

		$resSelect = $this->pedeo->queryTable($sqlSelect, array());

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


	//OBTENER orden de compra POR ID
	public function getPurchaseEcById_get()
	{

		$Data = $this->get();

		if (!isset($Data['cec_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM dcec WHERE cec_docentry =:cec_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":cec_docentry" => $Data['cec_docentry']));

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


	//OBTENER orden de compra DETALLE POR ID
	public function getPurchaseEcDetail_get()
	{

		$Data = $this->get();

		if (!isset($Data['ec1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT
								t1.ec1_acciva,
								t1.ec1_acctcode,
								t1.ec1_avprice,
								t1.ec1_basetype,
								t1.ec1_costcode,
								t1.ec1_discount,
								t1.ec1_docentry,
								t1.ec1_doctype,
								t1.ec1_id,
								t1.ec1_inventory,
								t1.ec1_itemcode,
								t1.ec1_itemname,
								t1.ec1_linenum,
								t1.ec1_linetotal linetotal_real,
								(t1.ec1_quantity - (coalesce(SUM(t3.dc1_quantity),0) + coalesce(SUM(t5.fc1_quantity),0))) * t1.ec1_price ec1_linetotal,
								t1.ec1_price,
								t1.ec1_project,
								t1.ec1_quantity can_real,
								coalesce(SUM(t3.dc1_quantity),0) devolucion,
								coalesce(SUM(t5.fc1_quantity),0) facturado,
								t1.ec1_quantity - (coalesce(SUM(t3.dc1_quantity),0) + coalesce(SUM(t5.fc1_quantity),0)) ec1_quantity,
								t1.ec1_ubusiness,
								t1.ec1_uom,
								t1.ec1_vat,
								t1.ec1_vatsum iva_entrada,
								(((t1.ec1_quantity - (coalesce(SUM(t3.dc1_quantity),0) + coalesce(SUM(t5.fc1_quantity),0))) * t1.ec1_price) * t1.ec1_vat) / 100 ec1_vatsum,
								t1.ec1_whscode,
								dmar.dma_series_code
								from dcec t0
								left join cec1 t1 on t0.cec_docentry = t1.ec1_docentry
								left join dcdc t2 on t0.cec_docentry = t2.cdc_baseentry and t0.cec_doctype = t2.cdc_basetype
								left join cdc1 t3 on t2.cdc_docentry = t3.dc1_docentry and t1.ec1_itemcode = t3.dc1_itemcode
								left join dcfc t4 on t0.cec_docentry = t4.cfc_baseentry and t0.cec_doctype = t4.cfc_basetype
								left join cfc1 t5 on t4.cfc_docentry = t5.fc1_docentry and t1.ec1_itemcode = t5.fc1_itemcode
								INNER JOIN dmar	ON t1.ec1_itemcode = dmar.dma_item_code
								WHERE t1.ec1_docentry =:ec1_docentry
								GROUP BY
								t1.ec1_acciva,
								t1.ec1_acctcode,
								t1.ec1_avprice,
								t1.ec1_basetype,
								t1.ec1_costcode,
								t1.ec1_discount,
								t1.ec1_docentry,
								t1.ec1_doctype,
								t1.ec1_id,
								t1.ec1_inventory,
								t1.ec1_itemcode,
								t1.ec1_itemname,
								t1.ec1_linenum,
								t1.ec1_linetotal,
								t1.ec1_price,
								t1.ec1_project,
								t1.ec1_ubusiness,
								t1.ec1_uom,
								t1.ec1_vat,
								t1.ec1_vatsum,
								t1.ec1_whscode,
								t1.ec1_quantity,
								dmar.dma_series_code";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":ec1_docentry" => $Data['ec1_docentry']));

		$seriales = "SELECT msn_line, msn_itemcode, string_agg(msn_sn, ',') AS serials FROM tmsn  WHERE msn_baseentry = :msn_baseentry AND msn_basetype = :msn_basetype GROUP BY  msn_itemcode,msn_line";


		$resseriales = $this->pedeo->queryTable($seriales, array(":msn_baseentry" => $Data['ec1_docentry'], ":msn_basetype" => 13));


		if (isset($resseriales[0]) && isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => array("detalle" => $resSelect, "complemento" => $resseriales),
				'mensaje' => ''
			);
		} else if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => array("detalle" => $resSelect),
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' 	  =>  array(),
				'mensaje' => 'busqueda sin resultados'
			);
		}



		$this->response($respuesta);
	}

	//OBTENER orden de compra DETALLE POR ID
	public function getPurchaseEcDetailCopy_get()
	{

		$Data = $this->get();

		if (!isset($Data['ec1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$copy = $this->documentcopy->Copy($Data['ec1_docentry'],'dcec','cec1','cec','ec1');

		$seriales = "SELECT msn_line, msn_itemcode, string_agg(msn_sn, ',') AS serials FROM tmsn  WHERE msn_baseentry = :msn_baseentry AND msn_basetype = :msn_basetype GROUP BY  msn_itemcode,msn_line";


		$resseriales = $this->pedeo->queryTable($seriales, array(":msn_baseentry" => $Data['ec1_docentry'], ":msn_basetype" => 13));


		if (isset($resseriales[0]) && isset($copy[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => array("detalle" => $copy, "complemento" => $resseriales),
				'mensaje' => ''
			);
		} else if (isset($copy[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => array("detalle" => $copy),
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' 	  =>  array(),
				'mensaje' => 'busqueda sin resultados'
			);
		}



		$this->response($respuesta);
	}


	//OBTENER DETALLE ENTRADA EN COMPRAS ESPECIAL ASISTENTE DE COMPRAS INTERNACIONALES
	public function getPurchaseEcDetailCI_get()
	{

		$Data = $this->get();

		if (!isset($Data['ec1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT
					t1.ec1_acciva,
					t1.ec1_acctcode,
					t1.ec1_avprice,
					t1.ec1_basetype,
					t1.ec1_costcode,
					t1.ec1_discount,
					t1.ec1_docentry,
					t1.ec1_doctype,
					t1.ec1_id,
					t1.ec1_inventory,
					t1.ec1_itemcode,
					t1.ec1_itemname,
					t1.ec1_linenum,
					t1.ec1_linetotal,
					t1.ec1_price,
					t1.ec1_project,
					t1.ec1_quantity - (coalesce(SUM(t3.dc1_quantity),0) + coalesce(SUM(t5.fc1_quantity),0)) ec1_quantity,
					t1.ec1_ubusiness,
					t1.ec1_uom,
					t1.ec1_vat,
					t1.ec1_vatsum,
					t1.ec1_whscode,
					t6.dma_uom_weight as peso,
					t6.dma_uom_vqty as metrocubico
					from dcec t0
					left join cec1 t1 on t0.cec_docentry = t1.ec1_docentry
					left join dcdc t2 on t0.cec_docentry = t2.cdc_baseentry and t0.cec_doctype = t2.cdc_basetype
					left join cdc1 t3 on t2.cdc_docentry = t3.dc1_docentry and t1.ec1_itemcode = t3.dc1_itemcode
					left join dcfc t4 on t0.cec_docentry = t4.cfc_baseentry and t0.cec_doctype = t4.cfc_basetype
					left join cfc1 t5 on t4.cfc_docentry = t5.fc1_docentry and t1.ec1_itemcode = t5.fc1_itemcode
					inner join dmar t6 on t1.ec1_itemcode = t6.dma_item_code
					WHERE t1.ec1_docentry = :ec1_docentry
					GROUP BY
					t1.ec1_acciva,
					t1.ec1_acctcode,
					t1.ec1_avprice,
					t1.ec1_basetype,
					t1.ec1_costcode,
					t1.ec1_discount,
					t1.ec1_docentry,
					t1.ec1_doctype,
					t1.ec1_id,
					t1.ec1_inventory,
					t1.ec1_itemcode,
					t1.ec1_itemname,
					t1.ec1_linenum,
					t1.ec1_linetotal,
					t1.ec1_price,
					t1.ec1_project,
					t1.ec1_ubusiness,
					t1.ec1_uom,
					t1.ec1_vat,
					t1.ec1_vatsum,
					t1.ec1_whscode,
					t1.ec1_quantity,
					t6.dma_uom_weight,
					t6.dma_uom_vqty";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":ec1_docentry" => $Data['ec1_docentry']));

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



	//OBTENER ENTRADAS POR SOCIO DE NEGOCIO
	public function getPurchaseEcBySN_get()
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

		$sqlSelect = "SELECT
					t0.*
				FROM dcec t0
				left join estado_doc t1 on t0.cec_docentry = t1.entry and t0.cec_doctype = t1.tipo
				left join responsestatus t2 on t1.entry = t2.id and t1.tipo = t2.tipo
				where t2.estado = 'Abierto' and t0.cec_cardcode =:cec_cardcode
				and t0.business = :business and t0.branch = :branch";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":cec_cardcode" => $Data['dms_card_code'],":business" => $Data['business'],":branch" => $Data['branch']));

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



	// SELECT cec1.*, dma_uom_weight,dma_uom_vqty FROM cec1
	// INNER JOIN dmar
	// ON  ec1_itemcode = dma_item_code


	private function getUrl($data, $caperta)
	{
		$url = "";

		if ($data == NULL) {

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
