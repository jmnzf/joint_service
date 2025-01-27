<?php
// NOTA CREDITO DE COMPRAS
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class PurchaseNc extends REST_Controller
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
		$this->load->library('Tasa');
		$this->load->library('DocumentDuplicate');
	}

	//CREAR NUEVA nota credito
	public function createPurchaseNc_post()
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

		$TasaDocLoc = 0;
		$TasaLocSys = 0;
		$MONEDALOCAL = "";
		$MONEDASYS = "";	

		$DetalleAsientoIngreso = new stdClass(); // Cada objeto de las linea del detalle consolidado
		$DetalleAsientoIva = new stdClass();
		$DetalleAsientoImpMulti = new stdClass();
		$DetalleCostoInventario = new stdClass();
		$DetalleCostoCosto = new stdClass();
		$DetalleRetencion = new stdClass();
		$DetalleItemNoInventariable = new stdClass();
		$DetalleConsolidadoIngreso = []; // Array Final con los datos del asiento solo ingreso
		$DetalleConsolidadoCostoInventario = [];
		$DetalleConsolidadoCostoCosto = [];
		$DetalleConsolidadoRetencion = [];
		$DetalleConsolidadoIva = []; // Array Final con los datos del asiento segun el iva
		$DetalleConsolidadoImpMulti = [];
		$DetalleConsolidadoItemNoInventariable = [];
		$inArrayIngreso = array(); // Array para mantener el indice de las llaves para ingreso
		$inArrayIva = array(); // Array para mantener el indice de las llaves para iva
		$inArrayImpMulti = array();
		$inArrayCostoInventario = array();
		$inArrayCostoCosto = array();
		$inArrayRetencion = array();
		$inArrayItemNoInventariable = array();
		$llave = ""; // la comnbinacion entre la cuenta contable,proyecto, unidad de negocio y centro de costo
		$llaveIva = ""; //segun tipo de iva
		$llaveImpMulti = "";
		$llaveCostoInventario = "";
		$llaveCostoCosto = "";
		$llaveRetencion = "";
		$llaveItemNoInventariable = "";
		$posicion = 0; // contiene la posicion con que se creara en el array DetalleConsolidado
		$posicionIva = 0;
		$posicionImpMulti = 0;
		$posicionCostoInventario = 0;
		$posicionCostoCosto = 0;
		$posicionRetencion = 0;
		$posicionItemNoInventariable = 0;
		$codigoCuenta = ""; //para saber la naturaleza
		$grantotalCostoInventario = 0;
		$DocNumVerificado = 0;
		$ManejaInvetario  = 0;
		$ManejaLote = 0;
		$ManejaUbicaion = 0;
		$ManejaSerial = 0;
		$TasaDocLoc = 0; // MANTIENE EL VALOR DE LA TASA DE CONVERSION ENTRE LA MONEDA LOCAL Y LA MONEDA DEL DOCUMENTO
		$TasaLocSys = 0; // MANTIENE EL VALOR DE LA TASA DE CONVERSION ENTRE LA MONEDA LOCAL Y LA MONEDA DEL SISTEMA
		$MONEDALOCAL = '';
		// DIFERENECIA DE
		$SumaCreditosSYS = 0;
		$SumaDebitosSYS = 0;
		$AC1LINE = 1;
		$TotalAcuRentencion = 0;
		$CANTUOMPURCHASE = 0; //CANTIDAD EN UNIDAD DE MEDIDA
		$CANTUOMSALE = 0;


		// Se globaliza la variable sqlDetalleAsiento
		$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
													ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
													ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
													ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, ac1_line, ac1_base_tax, ac1_codret,business,branch)VALUES (:ac1_trans_id, :ac1_account,
													:ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
													:ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
													:ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
													:ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref,:ac1_line,:ac1_base_tax, :ac1_codret,:business,:branch)";


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
				'mensaje' => 'No se encontro el detalle  de nota credito'
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
		$periodo = $this->generic->ValidatePeriod($Data['cnc_duedev'], $Data['cnc_docdate'], $Data['cnc_duedate'], 0);

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
		$DocNumVerificado = $this->documentnumbering->NumberDoc($Data['cnc_series'],$Data['cnc_docdate'],$Data['cnc_duedate']);
		
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

		//PROCESO DE TASA
		$dataTasa = $this->tasa->Tasa($Data['cnc_currency'],$Data['cnc_docdate']);

		if(isset($dataTasa['tasaLocal'])){
			
			$TasaDocLoc = $dataTasa['tasaLocal'];
			$TasaLocSys = $dataTasa['tasaSys'];
			$MONEDALOCAL = $dataTasa['curLocal'];
			$MONEDASYS = $dataTasa['curSys'];		
			
		}else if($dataTasa['error'] == true){

			$this->response($dataTasa, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}		
		//FIN DE PROCESO DE TASA		
		
		$sqlInsert = "INSERT INTO dcnc(cnc_series, cnc_docnum, cnc_docdate, cnc_duedate, cnc_duedev, cnc_pricelist, cnc_cardcode,
                      cnc_cardname, cnc_currency, cnc_contacid, cnc_slpcode, cnc_empid, cnc_comment, cnc_doctotal, cnc_baseamnt, cnc_taxtotal,
                      cnc_discprofit, cnc_discount, cnc_createat, cnc_baseentry, cnc_basetype, cnc_doctype, cnc_idadd, cnc_adress, cnc_paytype,
                      cnc_createby, cnc_totalret, cnc_totalretiva,business,branch,cnc_internal_comments,cnc_taxtotal_ad)
					  VALUES(:cnc_series, :cnc_docnum, :cnc_docdate, :cnc_duedate, :cnc_duedev, :cnc_pricelist, :cnc_cardcode, :cnc_cardname,
                      :cnc_currency, :cnc_contacid, :cnc_slpcode, :cnc_empid, :cnc_comment, :cnc_doctotal, :cnc_baseamnt, :cnc_taxtotal, :cnc_discprofit, :cnc_discount,
                      :cnc_createat, :cnc_baseentry, :cnc_basetype, :cnc_doctype, :cnc_idadd, :cnc_adress, :cnc_paytype,:cnc_createby, :cnc_totalret, :cnc_totalretiva,
					  :business,:branch,:cnc_internal_comments,:cnc_taxtotal_ad)";


		// Se Inicia la transaccion,
		// Todas las consultas de modificacion siguientes
		// aplicaran solo despues que se confirme la transaccion,
		// de lo contrario no se aplicaran los cambios y se devolvera
		// la base de datos a su estado original.

		$this->pedeo->trans_begin();

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			':cnc_docnum' => $DocNumVerificado,
			':cnc_series' => is_numeric($Data['cnc_series']) ? $Data['cnc_series'] : 0,
			':cnc_docdate' => $this->validateDate($Data['cnc_docdate']) ? $Data['cnc_docdate'] : NULL,
			':cnc_duedate' => $this->validateDate($Data['cnc_duedate']) ? $Data['cnc_duedate'] : NULL,
			':cnc_duedev' => $this->validateDate($Data['cnc_duedev']) ? $Data['cnc_duedev'] : NULL,
			':cnc_pricelist' => is_numeric($Data['cnc_pricelist']) ? $Data['cnc_pricelist'] : 0,
			':cnc_cardcode' => isset($Data['cnc_cardcode']) ? $Data['cnc_cardcode'] : NULL,
			':cnc_cardname' => isset($Data['cnc_cardname']) ? $Data['cnc_cardname'] : NULL,
			':cnc_currency' => isset($Data['cnc_currency']) ? $Data['cnc_currency'] : NULL,
			':cnc_contacid' => isset($Data['cnc_contacid']) ? $Data['cnc_contacid'] : NULL,
			':cnc_slpcode' => is_numeric($Data['cnc_slpcode']) ? $Data['cnc_slpcode'] : 0,
			':cnc_empid' => is_numeric($Data['cnc_empid']) ? $Data['cnc_empid'] : 0,
			':cnc_comment' => isset($Data['cnc_comment']) ? $Data['cnc_comment'] : NULL,
			':cnc_doctotal' => is_numeric($Data['cnc_doctotal']) ? $Data['cnc_doctotal'] : 0,
			':cnc_baseamnt' => is_numeric($Data['cnc_baseamnt']) ? $Data['cnc_baseamnt'] : 0,
			':cnc_taxtotal' => is_numeric($Data['cnc_taxtotal']) ? $Data['cnc_taxtotal'] : 0,
			':cnc_discprofit' => is_numeric($Data['cnc_discprofit']) ? $Data['cnc_discprofit'] : 0,
			':cnc_discount' => is_numeric($Data['cnc_discount']) ? $Data['cnc_discount'] : 0,
			':cnc_createat' => $this->validateDate($Data['cnc_createat']) ? $Data['cnc_createat'] : NULL,
			':cnc_baseentry' => is_numeric($Data['cnc_baseentry']) ? $Data['cnc_baseentry'] : 0,
			':cnc_basetype' => is_numeric($Data['cnc_basetype']) ? $Data['cnc_basetype'] : 0,
			':cnc_doctype' => is_numeric($Data['cnc_doctype']) ? $Data['cnc_doctype'] : 0,
			':cnc_idadd' => isset($Data['cnc_idadd']) ? $Data['cnc_idadd'] : NULL,
			':cnc_adress' => isset($Data['cnc_adress']) ? $Data['cnc_adress'] : NULL,
			':cnc_paytype' => is_numeric($Data['cnc_paytype']) ? $Data['cnc_paytype'] : 0,
			':cnc_createby' => isset($Data['cnc_createby']) ? $Data['cnc_createby'] : NULL,
			':cnc_totalret' => is_numeric($Data['cnc_totalret']) ? $Data['cnc_totalret'] : 0,
			':cnc_totalretiva' => is_numeric($Data['cnc_totalretiva']) ? $Data['cnc_totalretiva'] : 0,
			':business' => isset($Data['business']) ? $Data['business'] : NULL,
			':branch' => isset($Data['branch']) ? $Data['branch'] : NULL,
			':cnc_internal_comments' => isset($Data['cnc_internal_comments']) ? $Data['cnc_internal_comments'] : NULL,
			':cnc_taxtotal_ad' => is_numeric($Data['cnc_taxtotal_ad']) ? $Data['cnc_taxtotal_ad'] : 0
		));

		if (is_numeric($resInsert) && $resInsert > 0) {

			// Se actualiza la serie de la numeracion del documento

			$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id'      => $Data['cnc_series']
			));


			if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
			} else {
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'    => $resActualizarNumeracion,
					'mensaje'	=> 'No se pudo crear la nota credito'
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
				':bed_doctype' => $Data['cnc_doctype'],
				':bed_status' => 1, //ESTADO ABIERTO
				':bed_createby' => $Data['cnc_createby'],
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
					'mensaje'	=> 'No se pudo registrar la nota credito'
				);


				$this->response($respuesta);

				return;
			}

			//FIN PROCESO ESTADO DEL DOCUMENTO


			//Se agregan los asientos contables*/*******

			$sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user,business,branch,mac_accperiod)
															 VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user,:business,:branch,:mac_accperiod)";


			$resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

				':mac_doc_num' => 1,
				':mac_status' => 1,
				':mac_base_type' => is_numeric($Data['cnc_doctype']) ? $Data['cnc_doctype'] : 0,
				':mac_base_entry' => $resInsert,
				':mac_doc_date' => $this->validateDate($Data['cnc_docdate']) ? $Data['cnc_docdate'] : NULL,
				':mac_doc_duedate' => $this->validateDate($Data['cnc_duedate']) ? $Data['cnc_duedate'] : NULL,
				':mac_legal_date' => $this->validateDate($Data['cnc_docdate']) ? $Data['cnc_docdate'] : NULL,
				':mac_ref1' => is_numeric($Data['cnc_doctype']) ? $Data['cnc_doctype'] : 0,
				':mac_ref2' => "",
				':mac_ref3' => "",
				':mac_loc_total' => is_numeric($Data['cnc_doctotal']) ? $Data['cnc_doctotal'] : 0,
				':mac_fc_total' => is_numeric($Data['cnc_doctotal']) ? $Data['cnc_doctotal'] : 0,
				':mac_sys_total' => is_numeric($Data['cnc_doctotal']) ? $Data['cnc_doctotal'] : 0,
				':mac_trans_dode' => 1,
				':mac_beline_nume' => 1,
				':mac_vat_date' => $this->validateDate($Data['cnc_docdate']) ? $Data['cnc_docdate'] : NULL,
				':mac_serie' => 1,
				':mac_number' => 1,
				':mac_bammntsys' => is_numeric($Data['cnc_baseamnt']) ? $Data['cnc_baseamnt'] : 0,
				':mac_bammnt' => is_numeric($Data['cnc_baseamnt']) ? $Data['cnc_baseamnt'] : 0,
				':mac_wtsum' => 1,
				':mac_vatsum' => is_numeric($Data['cnc_taxtotal']) ? $Data['cnc_taxtotal'] : 0,
				':mac_comments' => isset($Data['cnc_comment']) ? $Data['cnc_comment'] : NULL,
				':mac_create_date' => $this->validateDate($Data['cnc_createat']) ? $Data['cnc_createat'] : NULL,
				':mac_made_usuer' => isset($Data['cnc_createby']) ? $Data['cnc_createby'] : NULL,
				':mac_update_date' => date("Y-m-d"),
				':mac_update_user' => isset($Data['cnc_createby']) ? $Data['cnc_createby'] : NULL,
				':business' => $Data['business'],
				':branch' => $Data['branch'],
				':mac_accperiod' => $periodo['data'],
			));


			if (is_numeric($resInsertAsiento) && $resInsertAsiento > 0) {
				// Se verifica que el detalle no de error insertando //
			} else {

				// si falla algun insert del detalle  de nota credito se devuelven los cambios realizados por la transaccion,
				// se retorna el error y se detiene la ejecucion del codigo restante.
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'	  => $resInsertAsiento,
					'mensaje'	=> 'No se pudo registrar la nota credito'
				);

				$this->response($respuesta);

				return;
			}


			//SE APLICA PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS
			if (isset($Data['cnc_baseentry']) && is_numeric($Data['cnc_baseentry']) && isset($Data['cnc_basetype']) && is_numeric($Data['cnc_basetype'])) {

				$sqlDocInicio = "SELECT bmd_tdi, bmd_ndi FROM tbmd WHERE  bmd_doctype = :bmd_doctype AND bmd_docentry = :bmd_docentry";
				$resDocInicio = $this->pedeo->queryTable($sqlDocInicio, array(
					':bmd_doctype' => $Data['cnc_basetype'],
					':bmd_docentry' => $Data['cnc_baseentry']
				));


				if (isset($resDocInicio[0])) {

					$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
															bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency)
															VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
															:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency)";

					$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

						':bmd_doctype' => is_numeric($Data['cnc_doctype']) ? $Data['cnc_doctype'] : 0,
						':bmd_docentry' => $resInsert,
						':bmd_createat' => $this->validateDate($Data['cnc_createat']) ? $Data['cnc_createat'] : NULL,
						':bmd_doctypeo' => is_numeric($Data['cnc_basetype']) ? $Data['cnc_basetype'] : 0, //ORIGEN
						':bmd_docentryo' => is_numeric($Data['cnc_baseentry']) ? $Data['cnc_baseentry'] : 0,  //ORIGEN
						':bmd_tdi' => $resDocInicio[0]['bmd_tdi'], // DOCUMENTO INICIAL
						':bmd_ndi' => $resDocInicio[0]['bmd_ndi'], // DOCUMENTO INICIAL
						':bmd_docnum' => $DocNumVerificado,
						':bmd_doctotal' => is_numeric($Data['cnc_doctotal']) ? $Data['cnc_doctotal'] : 0,
						':bmd_cardcode' => isset($Data['cnc_cardcode']) ? $Data['cnc_cardcode'] : NULL,
						':bmd_cardtype' => 2,
						':bmd_currency' => isset($Data['cnc_currency'])?$Data['cnc_currency']:NULL,
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
															bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency)
															VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
															:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency)";

					$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

						':bmd_doctype' => is_numeric($Data['cnc_doctype']) ? $Data['cnc_doctype'] : 0,
						':bmd_docentry' => $resInsert,
						':bmd_createat' => $this->validateDate($Data['cnc_createat']) ? $Data['cnc_createat'] : NULL,
						':bmd_doctypeo' => is_numeric($Data['cnc_basetype']) ? $Data['cnc_basetype'] : 0, //ORIGEN
						':bmd_docentryo' => is_numeric($Data['cnc_baseentry']) ? $Data['cnc_baseentry'] : 0,  //ORIGEN
						':bmd_tdi' => is_numeric($Data['cnc_doctype']) ? $Data['cnc_doctype'] : 0, // DOCUMENTO INICIAL
						':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
						':bmd_docnum' => $DocNumVerificado,
						':bmd_doctotal' => is_numeric($Data['cnc_doctotal']) ? $Data['cnc_doctotal'] : 0,
						':bmd_cardcode' => isset($Data['cnc_cardcode']) ? $Data['cnc_cardcode'] : NULL,
						':bmd_cardtype' => 2,
						':bmd_currency' => isset($Data['cnc_currency'])?$Data['cnc_currency']:NULL,
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
														bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency)
														VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
														:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency)";

				$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

					':bmd_doctype' => is_numeric($Data['cnc_doctype']) ? $Data['cnc_doctype'] : 0,
					':bmd_docentry' => $resInsert,
					':bmd_createat' => $this->validateDate($Data['cnc_createat']) ? $Data['cnc_createat'] : NULL,
					':bmd_doctypeo' => is_numeric($Data['cnc_basetype']) ? $Data['cnc_basetype'] : 0, //ORIGEN
					':bmd_docentryo' => is_numeric($Data['cnc_baseentry']) ? $Data['cnc_baseentry'] : 0,  //ORIGEN
					':bmd_tdi' => is_numeric($Data['cnc_doctype']) ? $Data['cnc_doctype'] : 0, // DOCUMENTO INICIAL
					':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
					':bmd_docnum' => $DocNumVerificado,
					':bmd_doctotal' => is_numeric($Data['cnc_doctotal']) ? $Data['cnc_doctotal'] : 0,
					':bmd_cardcode' => isset($Data['cnc_cardcode']) ? $Data['cnc_cardcode'] : NULL,
					':bmd_cardtype' => 2,
					':bmd_currency' => isset($Data['cnc_currency'])?$Data['cnc_currency']:NULL,
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

				$CANTUOMPURCHASE = $this->generic->getUomPurchase($detail['nc1_itemcode']);
				$CANTUOMSALE = $this->generic->getUomSale($detail['nc1_itemcode']);

				if ($CANTUOMPURCHASE == 0) {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' 		=> $detail['nc1_itemcode'],
						'mensaje'	=> 'No se encontro la equivalencia de la unidad de medida para el item: ' . $detail['nc1_itemcode']
					);

					$this->response($respuesta);

					return;
				}

				$sqlInsertDetail = "INSERT INTO cnc1(nc1_docentry,nc1_itemcode, nc1_itemname, nc1_quantity, nc1_uom, nc1_whscode,
                                    nc1_price, nc1_vat, nc1_vatsum, nc1_discount, nc1_linetotal, nc1_costcode, nc1_ubusiness, nc1_project,
                                    nc1_acctcode, nc1_basetype, nc1_doctype, nc1_avprice, nc1_inventory, nc1_acciva,nc1_linenum,nc1_codimp, 
									nc1_ubication,nc1_baseline,ote_code,nc1_tax_base,deducible,nc1_vat_ad,nc1_vatsum_ad,nc1_accimp_ad,nc1_codimp_ad,
									nc1_codmunicipality)
									VALUES(:nc1_docentry,:nc1_itemcode, :nc1_itemname, :nc1_quantity,
                                    :nc1_uom, :nc1_whscode,:nc1_price, :nc1_vat, :nc1_vatsum, :nc1_discount, :nc1_linetotal, :nc1_costcode, :nc1_ubusiness, :nc1_project,
                                    :nc1_acctcode, :nc1_basetype, :nc1_doctype, :nc1_avprice, :nc1_inventory, :nc1_acciva, :nc1_linenum,:nc1_codimp, 
									:nc1_ubication,:nc1_baseline,:ote_code,:nc1_tax_base,:deducible,:nc1_vat_ad,:nc1_vatsum_ad,:nc1_accimp_ad,:nc1_codimp_ad,
									:nc1_codmunicipality)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':nc1_docentry' => $resInsert,
					':nc1_itemcode' => isset($detail['nc1_itemcode']) ? $detail['nc1_itemcode'] : NULL,
					':nc1_itemname' => isset($detail['nc1_itemname']) ? $detail['nc1_itemname'] : NULL,
					':nc1_quantity' => is_numeric($detail['nc1_quantity']) ? $detail['nc1_quantity'] : 0,
					':nc1_uom' => isset($detail['nc1_uom']) ? $detail['nc1_uom'] : NULL,
					':nc1_whscode' => isset($detail['nc1_whscode']) ? $detail['nc1_whscode'] : NULL,
					':nc1_price' => is_numeric($detail['nc1_price']) ? $detail['nc1_price'] : 0,
					':nc1_vat' => is_numeric($detail['nc1_vat']) ? $detail['nc1_vat'] : 0,
					':nc1_vatsum' => is_numeric($detail['nc1_vatsum']) ? $detail['nc1_vatsum'] : 0,
					':nc1_discount' => is_numeric($detail['nc1_discount']) ? $detail['nc1_discount'] : 0,
					':nc1_linetotal' => is_numeric($detail['nc1_linetotal']) ? $detail['nc1_linetotal'] : 0,
					':nc1_costcode' => isset($detail['nc1_costcode']) ? $detail['nc1_costcode'] : NULL,
					':nc1_ubusiness' => isset($detail['nc1_ubusiness']) ? $detail['nc1_ubusiness'] : NULL,
					':nc1_project' => isset($detail['nc1_project']) ? $detail['nc1_project'] : NULL,
					':nc1_acctcode' => is_numeric($detail['nc1_acctcode']) ? $detail['nc1_acctcode'] : 0,
					':nc1_basetype' => is_numeric($detail['nc1_basetype']) ? $detail['nc1_basetype'] : 0,
					':nc1_doctype' => is_numeric($detail['nc1_doctype']) ? $detail['nc1_doctype'] : 0,
					':nc1_avprice' => is_numeric($detail['nc1_avprice']) ? $detail['nc1_avprice'] : 0,
					':nc1_inventory' => is_numeric($detail['nc1_inventory']) ? $detail['nc1_inventory'] : NULL,
					':nc1_acciva'  => is_numeric($detail['nc1_cuentaIva']) ? $detail['nc1_cuentaIva'] : 0,
					':nc1_linenum' => is_numeric($detail['nc1_linenum']) ? $detail['nc1_linenum'] : 0,
					':nc1_codimp'  => isset($detail['nc1_codimp']) ? $detail['nc1_codimp'] : NULL,
					':nc1_ubication'  => isset($detail['nc1_ubication']) ? $detail['nc1_ubication'] : NULL,
					':nc1_baseline' => is_numeric($detail['nc1_baseline']) ? $detail['nc1_baseline'] : 0,
					':ote_code'  => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
					':nc1_tax_base' => is_numeric($detail['nc1_tax_base']) ? $detail['nc1_tax_base'] : 0,
					':deducible' => isset($detail['deducible']) ? $detail['deducible'] : NULL,

					':nc1_vat_ad' => is_numeric($detail['nc1_vat_ad']) ? $detail['nc1_vat_ad'] : 0,
					':nc1_vatsum_ad' => is_numeric($detail['nc1_vatsum_ad']) ? $detail['nc1_vatsum_ad'] : 0,
					':nc1_accimp_ad'  => is_numeric($detail['nc1_accimp_ad']) ? $detail['nc1_accimp_ad'] : 0,
					':nc1_codimp_ad'  => isset($detail['nc1_codimp_ad']) ? $detail['nc1_codimp_ad'] : NULL,
					':nc1_codmunicipality' => isset($detail['nc1_codmunicipality']) ? $detail['nc1_codmunicipality'] : NULL
				));

				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle  de nota credito se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertDetail,
						'mensaje'	=> 'No se pudo registrar la nota credito'
					);

					$this->response($respuesta);

					return;
				}

				// PROCESO PARA INSERTAR RETENCIONES
				if (isset($detail['detail'])) {



					$ContenidoRentencion = $detail['detail'];

					if (is_array($ContenidoRentencion)) {
						if (intval(count($ContenidoRentencion)) > 0) {

							foreach ($ContenidoRentencion as $key => $value) {

								$DetalleRetencion = new stdClass();

								$sqlInsertRetenciones = "INSERT INTO fcrt(crt_baseentry, crt_basetype, crt_typert, crt_basert, crt_profitrt, crt_totalrt,crt_base, crt_type, crt_linenum, crt_codret)
																											 VALUES (:crt_baseentry, :crt_basetype, :crt_typert, :crt_basert, :crt_profitrt, :crt_totalrt, :crt_base, :crt_type, :crt_linenum, :crt_codret)";

								$resInsertRetenciones = $this->pedeo->insertRow($sqlInsertRetenciones, array(

									':crt_baseentry' => $resInsert,
									':crt_basetype'  => $Data['cnc_doctype'],
									':crt_typert'    => $value['crt_typert'],
									':crt_basert'    => $value['crt_basert'],
									':crt_profitrt'  => $value['crt_profitrt'],
									':crt_totalrt'   => $value['crt_totalrt'],
									':crt_base'		 => $value['crt_base'],
									':crt_type'		 => $value['crt_type'],
									':crt_linenum'   => $detail['nc1_linenum'],
									':crt_codret'	 => $value['crt_typert']
								));



								if (is_numeric($resInsertRetenciones) && $resInsertRetenciones > 0) {

									$TotalAcuRentencion = $TotalAcuRentencion + $value['crt_totalrt'];

									$DetalleRetencion->crt_typert   = $value['crt_typert'];
									$DetalleRetencion->crt_basert   = $value['crt_totalrt'];
									$DetalleRetencion->crt_profitrt = $value['crt_profitrt'];
									$DetalleRetencion->crt_totalrt  = $value['crt_totalrt'];
									$DetalleRetencion->crt_codret   = $value['crt_typert'];
									$DetalleRetencion->crt_baseln 	= $value['crt_basert'];

									$DetalleRetencion->ac1_prc_code = $detail['nc1_costcode'];
									$DetalleRetencion->ac1_uncode = $detail['nc1_ubusiness'];
									$DetalleRetencion->ac1_prj_code = $detail['nc1_project'];


									$llaveRetencion = $DetalleRetencion->crt_typert . $DetalleRetencion->crt_profitrt;

									if (in_array($llaveRetencion, $inArrayRetencion)) {

										$posicionRetencion = $this->buscarPosicion($llaveRetencion, $inArrayRetencion);
									} else {

										array_push($inArrayRetencion, $llaveRetencion);
										$posicionRetencion = $this->buscarPosicion($llaveRetencion, $inArrayRetencion);
									}

									if (isset($DetalleConsolidadoRetencion[$posicionRetencion])) {

										if (!is_array($DetalleConsolidadoRetencion[$posicionRetencion])) {
											$DetalleConsolidadoRetencion[$posicionRetencion] = array();
										}
									} else {
										$DetalleConsolidadoRetencion[$posicionRetencion] = array();
									}

									array_push($DetalleConsolidadoRetencion[$posicionRetencion], $DetalleRetencion);
								} else {
									// si falla algun insert del detalle de la factura de compras se devuelven los cambios realizados por la transaccion,
									// se retorna el error y se detiene la ejecucion del codigo restante.
									$this->pedeo->trans_rollback();
									$respuesta = array(
										'error'   => true,
										'data' => $resInsertDetail,
										'mensaje'	=> 'No se pudo registrar la nota credito de compras, fallo el proceso para insertar las retenciones'
									);
									$this->response($respuesta);
									return;
								}
							}
						}
					}
				}
				// SE VERIFICA SI EL ARTICULO ESTA MARCADO PARA MANEJARSE EN INVENTARIO Y LOTE
				$sqlItemINV = "SELECT dma_item_inv FROM dmar WHERE dma_item_code = :dma_item_code AND dma_item_inv = :dma_item_inv";
				$resItemINV = $this->pedeo->queryTable($sqlItemINV, array(

					':dma_item_code' => $detail['nc1_itemcode'],
					':dma_item_inv'  => 1
				));

				if (isset($resItemINV[0])) {

					
					// CONSULTA PARA VERIFICAR SI EL ALMACEN MANEJA UBICACION
					$sqlubicacion = "SELECT * FROM dmws WHERE dws_ubication = :dws_ubication AND dws_code = :dws_code AND business = :business";
					$resubicacion = $this->pedeo->queryTable($sqlubicacion, array(
						':dws_ubication' => 1,
						':dws_code' => $detail['nc1_whscode'],
						':business' => $Data['business']
					));


					if ( isset($resubicacion[0]) ){
						$ManejaUbicacion = 1;
					}else{
						$ManejaUbicacion = 0;
					}


					// SI ARTICULO MANEJA LOTE
					$sqlLote = "SELECT dma_lotes_code FROM dmar WHERE dma_item_code = :dma_item_code AND dma_lotes_code = :dma_lotes_code";
					$resLote = $this->pedeo->queryTable($sqlLote, array(

						':dma_item_code' => $detail['nc1_itemcode'],
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

				$exc_inv = is_numeric($detail['nc1_exc_inv']) ? $detail['nc1_exc_inv'] : 0;



				// SI LA NOTA APLICA MOVIENDO INVENTARIO
				if ($exc_inv == 1) {
					// si el item es inventariable
					if ($ManejaInvetario == 1) {

						//SE VERIFICA SI EL ARTICULO MANEJA SERIAL
						$sqlItemSerial = "SELECT dma_series_code FROM dmar WHERE  dma_item_code = :dma_item_code AND dma_series_code = :dma_series_code";
						$resItemSerial = $this->pedeo->queryTable($sqlItemSerial, array(

							':dma_item_code' => $detail['nc1_itemcode'],
							':dma_series_code'  => 1
						));

						if (isset($resItemSerial[0])) {
							$ManejaSerial = 1;

							$AddSerial = $this->generic->addSerial($detail['serials'], $detail['nc1_itemcode'], $Data['cnc_doctype'], $resInsert, $DocNumVerificado, $Data['cnc_docdate'], 2, $Data['cnc_comment'], $detail['nc1_whscode'], $detail['nc1_quantity'], $Data['cnc_createby'],$Data['business']);

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

						$sqlCostoMomentoRegistro = '';
						$resCostoMomentoRegistro = [];

						// SI EL ALMACEN MANEJA UBICACION

						if ( $ManejaUbicacion == 1 ){
							//SI MANEJA LOTE
							if ($ManejaLote == 1) {
								$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_lote = :bdi_lote AND bdi_ubication = :bdi_ubication AND business = :business";
								$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
									':bdi_whscode'   => $detail['nc1_whscode'],
									':bdi_itemcode'  => $detail['nc1_itemcode'],
									':bdi_lote' 	 => $detail['ote_code'],
									':bdi_ubication' => $detail['nc1_ubication'],
									':business'		 => $Data['business']
								));
							}else{
								$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode  AND bdi_ubication = :bdi_ubication AND business = :business";
								$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
									':bdi_whscode'   => $detail['nc1_whscode'],
									':bdi_itemcode'  => $detail['nc1_itemcode'],
									':bdi_ubication' => $detail['nc1_ubication'],
									':business' 	 => $Data['business']
								));
							}
						}else{

							//SI MANEJA LOTE
							if ($ManejaLote == 1) {
								$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_lote = :bdi_lote AND business = :business";
								$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
									':bdi_whscode' => $detail['nc1_whscode'],
									':bdi_itemcode' => $detail['nc1_itemcode'],
									':bdi_lote' => $detail['ote_code'],
									':business' => $Data['business']
								));
							} else {
								$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND business = :business";
								$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(':bdi_whscode' => $detail['nc1_whscode'], ':bdi_itemcode' => $detail['nc1_itemcode'], ':business' => $Data['business']));
							}
						}



						if (isset($resCostoMomentoRegistro[0])) {

							$sqlInserMovimiento = '';
							$resInserMovimiento = [];
					
							
							//Se aplica el movimiento de inventario
							$sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode,bmi_quantity,bmi_whscode,bmi_createat,bmi_createby,bmy_doctype,bmy_baseentry,bmi_cost,bmi_currequantity,bmi_basenum,bmi_docdate,bmi_duedate,bmi_duedev,bmi_comment,bmi_lote,bmi_ubication)
												VALUES (:bmi_itemcode,:bmi_quantity, :bmi_whscode,:bmi_createat,:bmi_createby,:bmy_doctype,:bmy_baseentry,:bmi_cost,:bmi_currequantity,:bmi_basenum,:bmi_docdate,:bmi_duedate,:bmi_duedev,:bmi_comment,:bmi_lote,:bmi_ubication)";

							$sqlInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(

								':bmi_itemcode' => isset($detail['nc1_itemcode']) ? $detail['nc1_itemcode'] : NULL,
								':bmi_quantity' => ($this->generic->getCantInv($detail['nc1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE) * $Data['invtype']),
								':bmi_whscode'  => isset($detail['nc1_whscode']) ? $detail['nc1_whscode'] : NULL,
								':bmi_createat' => $this->validateDate($Data['cnc_createat']) ? $Data['cnc_createat'] : NULL,
								':bmi_createby' => isset($Data['cnc_createby']) ? $Data['cnc_createby'] : NULL,
								':bmy_doctype'  => is_numeric($Data['cnc_doctype']) ? $Data['cnc_doctype'] : 0,
								':bmy_baseentry' => $resInsert,
								':bmi_cost'      => $resCostoMomentoRegistro[0]['bdi_avgprice'],
								':bmi_currequantity' 	=> $resCostoMomentoRegistro[0]['bdi_quantity'],
								':bmi_basenum'			=> $DocNumVerificado,
								':bmi_docdate' => $this->validateDate($Data['cnc_docdate']) ? $Data['cnc_docdate'] : NULL,
								':bmi_duedate' => $this->validateDate($Data['cnc_duedate']) ? $Data['cnc_duedate'] : NULL,
								':bmi_duedev'  => $this->validateDate($Data['cnc_duedev']) ? $Data['cnc_duedev'] : NULL,
								':bmi_comment' => isset($Data['cnc_comment']) ? $Data['cnc_comment'] : NULL,
								':bmi_lote' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
								':bmi_ubication' => isset($detail['nc1_ubication']) ? $detail['nc1_ubication'] : NULL


							));
							
							


							if (is_numeric($sqlInserMovimiento) && $sqlInserMovimiento > 0) {
								// Se verifica que el detalle no de error insertando //
							} else {

								// si falla algun insert del detalle  de nota credito se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $sqlInserMovimiento,
									'mensaje'	=> 'No se pudo registrar la nota credito'
								);

								$this->response($respuesta);

								return;
							}
						} else {

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' => $resCostoMomentoRegistro,
								'mensaje'	=> 'No se pudo registrar la nota credito, no se encontro el costo del articulo'
							);

							$this->response($respuesta);

							return;
						}

						//FIN aplicacion de movimiento de inventario


						//Se Aplica el movimiento en stock ***************
						// Buscando item en el stock
						$sqlCostoCantidad = '';
						$resCostoCantidad = [];

						// SI EL ALMACEN MANEJA UBICACION
						if ( $ManejaUbicacion == 1 ){
							//SE VALIDA SI EL ARTICULO MANEJA LOTE
							if ($ManejaLote == 1) {
								$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
												FROM tbdi
												WHERE bdi_itemcode = :bdi_itemcode
												AND bdi_whscode = :bdi_whscode
												AND bdi_lote = :bdi_lote
												AND bdi_ubication = :bdi_ubication
												AND business = :business";

								$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(

									':bdi_itemcode'  => $detail['nc1_itemcode'],
									':bdi_whscode'   => $detail['nc1_whscode'],
									':bdi_lote' 	 => $detail['ote_code'],
									':bdi_ubication' => $detail['nc1_ubication'],
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

									':bdi_itemcode'  => $detail['nc1_itemcode'],
									':bdi_whscode'   => $detail['nc1_whscode'],
									':bdi_ubication' => $detail['nc1_ubication'],
									':business' 	 => $Data['business']
								));
							}

						}else{
							//SE VALIDA SI EL ARTICULO MANEJA LOTE
							if ($ManejaLote == 1) {
								$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
												FROM tbdi
												WHERE bdi_itemcode = :bdi_itemcode
												AND bdi_whscode = :bdi_whscode
												AND bdi_lote = :bdi_lote
												AND business = :business";

								$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(

									':bdi_itemcode' => $detail['nc1_itemcode'],
									':bdi_whscode'  => $detail['nc1_whscode'],
									':bdi_lote' 	=> $detail['ote_code'],
									':business' 	=> $Data['business']
								));
							} else {
								$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
												FROM tbdi
												WHERE bdi_itemcode = :bdi_itemcode
												AND bdi_whscode = :bdi_whscode
												AND business = :business";

								$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(

									':bdi_itemcode' => $detail['nc1_itemcode'],
									':bdi_whscode'  => $detail['nc1_whscode'],
									':business' 	=> $Data['business']
								));
							}
						}
						


						if (isset($resCostoCantidad[0])) {

							if ($resCostoCantidad[0]['bdi_quantity'] > 0) {

								$CantidadActual = $resCostoCantidad[0]['bdi_quantity'];

								$CantidadNueva = $this->generic->getCantInv($detail['nc1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE);


								$CantidadTotal = ($CantidadActual - $CantidadNueva);

								$sqlUpdateCostoCantidad =  "UPDATE tbdi
															SET bdi_quantity = :bdi_quantity
															WHERE  bdi_id = :bdi_id";

								$resUpdateCostoCantidad = $this->pedeo->updateRow($sqlUpdateCostoCantidad, array(

									':bdi_quantity' => $CantidadTotal,
									':bdi_id' 			 => $resCostoCantidad[0]['bdi_id']
								));

								if (is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad == 1) {
								} else {

									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'    => $resUpdateCostoCantidad,
										'mensaje'	=> 'No se pudo crear la nota credito'
									);

									$this->response($respuesta);

									return;
								}
							} else {

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resCostoCantidad,
									'mensaje' => 'No hay existencia para el item: ' . $detail['nc1_itemcode']
								);

								$this->response($respuesta);

								return;
							}
						} else {

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' 		=> $resCostoCantidad,
								'mensaje'	=> 'El item no existe en el stock ' . $detail['nc1_itemcode']
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
									':ote_createby' => $Data['cnc_createby'],
									':ote_date' => date('Y-m-d'),
									':ote_baseentry' => $resInsert,
									':ote_basetype' => $Data['cnc_doctype'],
									':ote_docnum' => $DocNumVerificado
								));


								if (is_numeric($resInsertLote) && $resInsertLote > 0) {
								} else {
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data' 		=> $resInsertLote,
										'mensaje'	=> 'No se pudo registrar la factura de compras'
									);

									$this->response($respuesta);

									return;
								}
							}
						}
						//FIN VALIDACION DEL LOTE


					}
				}
				//FIN SI LA NOTA APLICA SIN MOVER INVENTARIO


				//LLENANDO DETALLE ASIENTO CONTABLES
				$DetalleAsientoIngreso = new stdClass();
				$DetalleAsientoIva = new stdClass();
				$DetalleCostoInventario = new stdClass();
				$DetalleCostoCosto = new stdClass();
				$DetalleItemNoInventariable = new stdClass();
				$DetalleAsientoImpMulti = new stdClass();


				$DetalleAsientoIngreso->ac1_account = is_numeric($detail['nc1_acctcode']) ? $detail['nc1_acctcode'] : 0;
				$DetalleAsientoIngreso->ac1_prc_code = isset($detail['nc1_costcode']) ? $detail['nc1_costcode'] : NULL;
				$DetalleAsientoIngreso->ac1_uncode = isset($detail['nc1_ubusiness']) ? $detail['nc1_ubusiness'] : NULL;
				$DetalleAsientoIngreso->ac1_prj_code = isset($detail['nc1_project']) ? $detail['nc1_project'] : NULL;
				$DetalleAsientoIngreso->nc1_linetotal = is_numeric($detail['nc1_linetotal']) ? $detail['nc1_linetotal'] : 0;
				$DetalleAsientoIngreso->nc1_vat = is_numeric($detail['nc1_vat']) ? $detail['nc1_vat'] : 0;
				$DetalleAsientoIngreso->nc1_vatsum = is_numeric($detail['nc1_vatsum']) ? $detail['nc1_vatsum'] : 0;
				$DetalleAsientoIngreso->nc1_price = (($detail['nc1_price'] / $CANTUOMPURCHASE) * $CANTUOMSALE);
				$DetalleAsientoIngreso->nc1_itemcode = isset($detail['nc1_itemcode']) ? $detail['nc1_itemcode'] : NULL;
				$DetalleAsientoIngreso->nc1_quantity = $this->generic->getCantInv($detail['nc1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE);
				$DetalleAsientoIngreso->em1_whscode = isset($detail['nc1_whscode']) ? $detail['nc1_whscode'] : NULL;



				$DetalleAsientoIva->ac1_account = is_numeric($detail['nc1_acctcode']) ? $detail['nc1_acctcode'] : 0;
				$DetalleAsientoIva->ac1_prc_code = isset($detail['nc1_costcode']) ? $detail['nc1_costcode'] : NULL;
				$DetalleAsientoIva->ac1_uncode = isset($detail['nc1_ubusiness']) ? $detail['nc1_ubusiness'] : NULL;
				$DetalleAsientoIva->ac1_prj_code = isset($detail['nc1_project']) ? $detail['nc1_project'] : NULL;
				$DetalleAsientoIva->nc1_linetotal = is_numeric($detail['nc1_linetotal']) ? $detail['nc1_linetotal'] : 0;
				$DetalleAsientoIva->nc1_vat = is_numeric($detail['nc1_vat']) ? $detail['nc1_vat'] : 0;
				$DetalleAsientoIva->nc1_vatsum = is_numeric($detail['nc1_vatsum']) ? $detail['nc1_vatsum'] : 0;
				$DetalleAsientoIva->nc1_price = (($detail['nc1_price'] / $CANTUOMPURCHASE) * $CANTUOMSALE);
				$DetalleAsientoIva->nc1_itemcode = isset($detail['nc1_itemcode']) ? $detail['nc1_itemcode'] : NULL;
				$DetalleAsientoIva->nc1_quantity = $this->generic->getCantInv($detail['nc1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE);
				$DetalleAsientoIva->nc1_cuentaIva = is_numeric($detail['nc1_cuentaIva']) ? $detail['nc1_cuentaIva'] : NULL;
				$DetalleAsientoIva->em1_whscode = isset($detail['nc1_whscode']) ? $detail['nc1_whscode'] : NULL;
				$DetalleAsientoIva->codimp = isset($detail['nc1_codimp']) ? $detail['nc1_codimp'] : NULL;

				// IMPUESTO MULTIPLE
				if ( is_numeric($detail['nc1_vatsum_ad']) && $detail['nc1_vatsum_ad'] > 0 ) {
					$DetalleAsientoImpMulti->ac1_account = is_numeric($detail['nc1_accimp_ad']) ? $detail['nc1_accimp_ad'] : 0;
					$DetalleAsientoImpMulti->ac1_prc_code = isset($detail['nc1_costcode']) ? $detail['nc1_costcode'] : NULL;
					$DetalleAsientoImpMulti->ac1_uncode = isset($detail['nc1_ubusiness']) ? $detail['nc1_ubusiness'] : NULL;
					$DetalleAsientoImpMulti->ac1_prj_code = isset($detail['nc1_project']) ? $detail['nc1_project'] : NULL;
					$DetalleAsientoImpMulti->nc1_linetotal = is_numeric($detail['nc1_linetotal']) ? $detail['nc1_linetotal'] : 0;
					$DetalleAsientoImpMulti->nc1_vat = is_numeric($detail['nc1_vat_ad']) ? $detail['nc1_vat_ad'] : 0;
					$DetalleAsientoImpMulti->nc1_vatsum = is_numeric($detail['nc1_vatsum_ad']) ? $detail['nc1_vatsum_ad'] : 0;
					$DetalleAsientoImpMulti->nc1_price = (($detail['nc1_price'] / $CANTUOMPURCHASE) * $CANTUOMSALE);
					$DetalleAsientoImpMulti->nc1_itemcode = isset($detail['nc1_itemcode']) ? $detail['nc1_itemcode'] : NULL;
					$DetalleAsientoImpMulti->nc1_quantity = $this->generic->getCantInv($detail['nc1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE);
					$DetalleAsientoImpMulti->nc1_cuentaIva = is_numeric($detail['nc1_accimp_ad']) ? $detail['nc1_accimp_ad'] : NULL;
					$DetalleAsientoImpMulti->em1_whscode = isset($detail['nc1_whscode']) ? $detail['nc1_whscode'] : NULL;
					$DetalleAsientoImpMulti->codimp = isset($detail['nc1_codimp_ad']) ? $detail['nc1_codimp_ad'] : NULL;
				}


				// se busca la cuenta contable del costoInventario y costoCosto
				// $sqlArticulo = "SELECT f2.dma_item_code,  f1.mga_acct_inv, f1.mga_acct_cost, f1.mga_accretpurch FROM dmga f1 JOIN dmar f2 ON f1.mga_id  = f2.dma_group_code WHERE dma_item_code = :dma_item_code";
				//
				// $resArticulo = $this->pedeo->queryTable($sqlArticulo, array(":dma_item_code" => $detail['nc1_itemcode']));
				//
				// if(!isset($resArticulo[0])){
				//
				// 			$this->pedeo->trans_rollback();
				//
				// 			$respuesta = array(
				// 				'error'   => true,
				// 				'data' => $resArticulo,
				// 				'mensaje'	=> 'No se pudo registrar la nota credito'
				// 			);
				//
				// 			 $this->response($respuesta);
				//
				// 			 return;
				// }



				if ($exc_inv == 1) {
					// VALIDANDO ITEM INVENTARIABLE
					if ($ManejaInvetario == 1) {


						$DetalleCostoInventario->ac1_account =  is_numeric($detail['nc1_acctcode']) ? $detail['nc1_acctcode'] : 0;
						$DetalleCostoInventario->ac1_prc_code = isset($detail['nc1_costcode']) ? $detail['nc1_costcode'] : NULL;
						$DetalleCostoInventario->ac1_uncode = isset($detail['nc1_ubusiness']) ? $detail['nc1_ubusiness'] : NULL;
						$DetalleCostoInventario->ac1_prj_code = isset($detail['nc1_project']) ? $detail['nc1_project'] : NULL;
						$DetalleCostoInventario->nc1_linetotal = is_numeric($detail['nc1_linetotal']) ? $detail['nc1_linetotal'] : 0;
						$DetalleCostoInventario->nc1_vat = is_numeric($detail['nc1_vat']) ? $detail['nc1_vat'] : 0;
						$DetalleCostoInventario->nc1_vatsum = is_numeric($detail['nc1_vatsum']) ? $detail['nc1_vatsum'] : 0;
						$DetalleCostoInventario->nc1_price = (($detail['nc1_price'] / $CANTUOMPURCHASE) * $CANTUOMSALE);
						$DetalleCostoInventario->nc1_itemcode = isset($detail['nc1_itemcode']) ? $detail['nc1_itemcode'] : NULL;
						$DetalleCostoInventario->nc1_quantity = $this->generic->getCantInv($detail['nc1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE);
						$DetalleCostoInventario->em1_whscode = isset($detail['nc1_whscode']) ? $detail['nc1_whscode'] : NULL;
						$DetalleCostoInventario->ac1_inventory = $ManejaInvetario;


						$DetalleCostoCosto->ac1_account =  is_numeric($detail['nc1_acctcode']) ? $detail['nc1_acctcode'] : 0;
						$DetalleCostoCosto->ac1_prc_code = isset($detail['nc1_costcode']) ? $detail['nc1_costcode'] : NULL;
						$DetalleCostoCosto->ac1_uncode = isset($detail['nc1_ubusiness']) ? $detail['nc1_ubusiness'] : NULL;
						$DetalleCostoCosto->ac1_prj_code = isset($detail['nc1_project']) ? $detail['nc1_project'] : NULL;
						$DetalleCostoCosto->nc1_linetotal = is_numeric($detail['nc1_linetotal']) ? $detail['nc1_linetotal'] : 0;
						$DetalleCostoCosto->nc1_vat = is_numeric($detail['nc1_vat']) ? $detail['nc1_vat'] : 0;
						$DetalleCostoCosto->nc1_vatsum = is_numeric($detail['nc1_vatsum']) ? $detail['nc1_vatsum'] : 0;
						$DetalleCostoCosto->nc1_price = (($detail['nc1_price'] / $CANTUOMPURCHASE) * $CANTUOMSALE);
						$DetalleCostoCosto->nc1_itemcode = isset($detail['nc1_itemcode']) ? $detail['nc1_itemcode'] : NULL;
						$DetalleCostoCosto->nc1_quantity = $this->generic->getCantInv($detail['nc1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE);
						$DetalleCostoCosto->em1_whscode = isset($detail['nc1_whscode']) ? $detail['nc1_whscode'] : NULL;
						$DetalleCostoCosto->ac1_inventory = $ManejaInvetario;
					} else {
						$DetalleItemNoInventariable->ac1_account =  is_numeric($detail['nc1_acctcode']) ? $detail['nc1_acctcode'] : 0;
						$DetalleItemNoInventariable->ac1_prc_code = isset($detail['nc1_costcode']) ? $detail['nc1_costcode'] : NULL;
						$DetalleItemNoInventariable->ac1_uncode = isset($detail['nc1_ubusiness']) ? $detail['nc1_ubusiness'] : NULL;
						$DetalleItemNoInventariable->ac1_prj_code = isset($detail['nc1_project']) ? $detail['nc1_project'] : NULL;
						$DetalleItemNoInventariable->nc1_linetotal = is_numeric($detail['nc1_linetotal']) ? $detail['nc1_linetotal'] : 0;
						$DetalleItemNoInventariable->nc1_vat = is_numeric($detail['nc1_vat']) ? $detail['nc1_vat'] : 0;
						$DetalleItemNoInventariable->nc1_vatsum = is_numeric($detail['nc1_vatsum']) ? $detail['nc1_vatsum'] : 0;
						$DetalleItemNoInventariable->nc1_price = (($detail['nc1_price'] / $CANTUOMPURCHASE) * $CANTUOMSALE);
						$DetalleItemNoInventariable->nc1_itemcode = isset($detail['nc1_itemcode']) ? $detail['nc1_itemcode'] : NULL;
						$DetalleItemNoInventariable->nc1_quantity = $this->generic->getCantInv($detail['nc1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE);
						$DetalleItemNoInventariable->em1_whscode = isset($detail['nc1_whscode']) ? $detail['nc1_whscode'] : NULL;
					}
				} else {

					if ($ManejaInvetario == 1) {
						$DetalleCostoCosto->ac1_account =  is_numeric($detail['nc1_acctcode']) ? $detail['nc1_acctcode'] : 0;
						$DetalleCostoCosto->ac1_prc_code = isset($detail['nc1_costcode']) ? $detail['nc1_costcode'] : NULL;
						$DetalleCostoCosto->ac1_uncode = isset($detail['nc1_ubusiness']) ? $detail['nc1_ubusiness'] : NULL;
						$DetalleCostoCosto->ac1_prj_code = isset($detail['nc1_project']) ? $detail['nc1_project'] : NULL;
						$DetalleCostoCosto->nc1_linetotal = is_numeric($detail['nc1_linetotal']) ? $detail['nc1_linetotal'] : 0;
						$DetalleCostoCosto->nc1_vat = is_numeric($detail['nc1_vat']) ? $detail['nc1_vat'] : 0;
						$DetalleCostoCosto->nc1_vatsum = is_numeric($detail['nc1_vatsum']) ? $detail['nc1_vatsum'] : 0;
						$DetalleCostoCosto->nc1_price = (($detail['nc1_price'] / $CANTUOMPURCHASE) * $CANTUOMSALE);
						$DetalleCostoCosto->nc1_itemcode = isset($detail['nc1_itemcode']) ? $detail['nc1_itemcode'] : NULL;
						$DetalleCostoCosto->nc1_quantity = $this->generic->getCantInv($detail['nc1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE);
						$DetalleCostoCosto->em1_whscode = isset($detail['nc1_whscode']) ? $detail['nc1_whscode'] : NULL;
						$DetalleCostoCosto->ac1_inventory = $ManejaInvetario;
					} else {
						$DetalleItemNoInventariable->ac1_account =  is_numeric($detail['nc1_acctcode']) ? $detail['nc1_acctcode'] : 0;
						$DetalleItemNoInventariable->ac1_prc_code = isset($detail['nc1_costcode']) ? $detail['nc1_costcode'] : NULL;
						$DetalleItemNoInventariable->ac1_uncode = isset($detail['nc1_ubusiness']) ? $detail['nc1_ubusiness'] : NULL;
						$DetalleItemNoInventariable->ac1_prj_code = isset($detail['nc1_project']) ? $detail['nc1_project'] : NULL;
						$DetalleItemNoInventariable->nc1_linetotal = is_numeric($detail['nc1_linetotal']) ? $detail['nc1_linetotal'] : 0;
						$DetalleItemNoInventariable->nc1_vat = is_numeric($detail['nc1_vat']) ? $detail['nc1_vat'] : 0;
						$DetalleItemNoInventariable->nc1_vatsum = is_numeric($detail['nc1_vatsum']) ? $detail['nc1_vatsum'] : 0;
						$DetalleItemNoInventariable->nc1_price = (($detail['nc1_price'] / $CANTUOMPURCHASE) * $CANTUOMSALE);
						$DetalleItemNoInventariable->nc1_itemcode = isset($detail['nc1_itemcode']) ? $detail['nc1_itemcode'] : NULL;
						$DetalleItemNoInventariable->nc1_quantity = $this->generic->getCantInv($detail['nc1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE);
						$DetalleItemNoInventariable->em1_whscode = isset($detail['nc1_whscode']) ? $detail['nc1_whscode'] : NULL;
					}
				}






				$codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);
				$DetalleAsientoIngreso->codigoCuenta = $codigoCuenta;
				$DetalleAsientoIva->codigoCuenta = $codigoCuenta;

				$llave = $DetalleAsientoIngreso->ac1_uncode . $DetalleAsientoIngreso->ac1_prc_code . $DetalleAsientoIngreso->ac1_prj_code . $DetalleAsientoIngreso->ac1_account;
				$llaveIva = $DetalleAsientoIva->nc1_cuentaIva;

				// IMPUESTO MULTIPLE
				if ( is_numeric($detail['nc1_vatsum_ad']) && $detail['nc1_vatsum_ad'] > 0 ) {

					$llaveImpMulti = $DetalleAsientoImpMulti->nc1_cuentaIva;
				}


				if ($exc_inv == 1) {
					// VALIDANDO ITEM INVENTARIABLE
					if ($ManejaInvetario == 1) {

						$DetalleCostoInventario->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);
						$DetalleCostoInventario->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);

						$llaveCostoInventario = $DetalleCostoInventario->ac1_account;
						$llaveCostoCosto = $DetalleCostoCosto->ac1_account;
					} else {
						$llaveItemNoInventariable = $DetalleItemNoInventariable->ac1_uncode . $DetalleItemNoInventariable->ac1_prc_code . $DetalleItemNoInventariable->ac1_prj_code . $DetalleItemNoInventariable->ac1_account;
					}
				} else {

					if ($ManejaInvetario == 1) {
						$DetalleCostoCosto->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);
						$llaveCostoCosto = $DetalleCostoCosto->ac1_account;
					} else {
						$llaveItemNoInventariable = $DetalleItemNoInventariable->ac1_uncode . $DetalleItemNoInventariable->ac1_prc_code . $DetalleItemNoInventariable->ac1_prj_code . $DetalleItemNoInventariable->ac1_account;
					}
				}


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


				// IMPUESTO MULTIPLE
				if ( is_numeric($detail['nc1_vatsum_ad']) && $detail['nc1_vatsum_ad'] > 0 ) {

					if (in_array($llaveImpMulti, $inArrayImpMulti)) {

						$posicionImpMulti = $this->buscarPosicion($llaveImpMulti, $inArrayImpMulti);
					} else {
	
						array_push($inArrayImpMulti, $llaveImpMulti);
						$posicionImpMulti = $this->buscarPosicion($llaveImpMulti, $inArrayImpMulti);
					}


					if (isset($DetalleConsolidadoImpMulti[$posicionImpMulti])) {

						if (!is_array($DetalleConsolidadoImpMulti[$posicionImpMulti])) {
							$DetalleConsolidadoImpMulti[$posicionImpMulti] = array();
						}
					} else {
						$DetalleConsolidadoImpMulti[$posicionImpMulti] = array();
					}

					array_push($DetalleConsolidadoImpMulti[$posicionImpMulti], $DetalleAsientoImpMulti);
				}



				if ($exc_inv == 1) {
					// VALIDANDO ITEM INVENTARIABLE
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
					} else {
						if (in_array($llaveItemNoInventariable, $inArrayItemNoInventariable)) {

							$posicionItemNoInventariable = $this->buscarPosicion($llaveItemNoInventariable, $inArrayItemNoInventariable);
						} else {

							array_push($inArrayItemNoInventariable, $llaveItemNoInventariable);
							$posicionItemNoInventariable = $this->buscarPosicion($llaveItemNoInventariable, $inArrayItemNoInventariable);
						}
					}
				} else {
					if ($ManejaInvetario == 1) {
						if (in_array($llaveCostoCosto, $inArrayCostoCosto)) {

							$posicionCostoCosto = $this->buscarPosicion($llaveCostoCosto, $inArrayCostoCosto);
						} else {

							array_push($inArrayCostoCosto, $llaveCostoCosto);
							$posicionCostoCosto = $this->buscarPosicion($llaveCostoCosto, $inArrayCostoCosto);
						}
						// ITEM NO INVENTARIABLE
					} else {

						if (in_array($llaveItemNoInventariable, $inArrayItemNoInventariable)) {

							$posicionItemNoInventariable = $this->buscarPosicion($llaveItemNoInventariable, $inArrayItemNoInventariable);
						} else {

							array_push($inArrayItemNoInventariable, $llaveItemNoInventariable);
							$posicionItemNoInventariable = $this->buscarPosicion($llaveItemNoInventariable, $inArrayItemNoInventariable);
						}
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


				if ($exc_inv == 1) {
					// VALIDANDO ITEM INVENTARIABLE
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
					} else {

						if (isset($DetalleConsolidadoItemNoInventariable[$posicionItemNoInventariable])) {

							if (!is_array($DetalleConsolidadoItemNoInventariable[$posicionItemNoInventariable])) {
								$DetalleConsolidadoItemNoInventariable[$posicionItemNoInventariable] = array();
							}
						} else {
							$DetalleConsolidadoItemNoInventariable[$posicionItemNoInventariable] = array();
						}

						array_push($DetalleConsolidadoItemNoInventariable[$posicionItemNoInventariable], $DetalleItemNoInventariable);
					}
				} else {
					if ($ManejaInvetario == 1) {
						if (isset($DetalleConsolidadoCostoCosto[$posicionCostoCosto])) {

							if (!is_array($DetalleConsolidadoCostoCosto[$posicionCostoCosto])) {
								$DetalleConsolidadoCostoCosto[$posicionCostoCosto] = array();
							}
						} else {
							$DetalleConsolidadoCostoCosto[$posicionCostoCosto] = array();
						}

						array_push($DetalleConsolidadoCostoCosto[$posicionCostoCosto], $DetalleCostoCosto);
					} else {

						if (isset($DetalleConsolidadoItemNoInventariable[$posicionItemNoInventariable])) {

							if (!is_array($DetalleConsolidadoItemNoInventariable[$posicionItemNoInventariable])) {
								$DetalleConsolidadoItemNoInventariable[$posicionItemNoInventariable] = array();
							}
						} else {
							$DetalleConsolidadoItemNoInventariable[$posicionItemNoInventariable] = array();
						}

						array_push($DetalleConsolidadoItemNoInventariable[$posicionItemNoInventariable], $DetalleItemNoInventariable);
					}
				}
			}

			//Procedimiento para llenar Impuestos
			$granTotalIva = 0;
			$MontoSysCR = 0;
			$MontoSysDB = 0;
			foreach ($DetalleConsolidadoIva as $key => $posicion) {
				$granTotalIva = 0;
				$granTotalIvaOriginal = 0;
				$CodigoImp = 0;
				$LineTotal = 0;
				$Vat = 0;

				$prc_code = '';
				$uncode   = '';
				$prj_code = '';

				foreach ($posicion as $key => $value) {
					$granTotalIva = $granTotalIva + $value->nc1_vatsum;
					$Vat = $value->nc1_vat;
					
					$LineTotal = ($LineTotal + $value->nc1_linetotal);
					
					$CodigoImp = $value->codimp;

					$prc_code = $value->ac1_prc_code;
					$uncode   = $value->ac1_uncode;
					$prj_code = $value->ac1_prj_code;
				}

				$granTotalIvaOriginal = $granTotalIva;

				if (trim($Data['cnc_currency']) != $MONEDALOCAL) {
					$granTotalIva = ($granTotalIva * $TasaDocLoc);
					$LineTotal = ($LineTotal * $TasaDocLoc);
				}

				if (trim($Data['cnc_currency']) != $MONEDASYS) {

					$MontoSysCR = ($granTotalIva / $TasaLocSys);
				} else {
					$MontoSysCR = $granTotalIvaOriginal;
				}

				$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
				$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));

				// SE AGREGA AL BALANCE
				$BALANCE = $this->account->addBalance($periodo['data'], round($granTotalIva, $DECI_MALES), $value->nc1_cuentaIva, 2, $Data['cnc_docdate'], $Data['business'], $Data['branch']);
				if (isset($BALANCE['error']) && $BALANCE['error'] == true){
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error' => true,
						'data' => $BALANCE,
						'mensaje' => $BALANCE['mensaje']
					);

					return $this->response($respuesta);
				}
				$BUDGET = $this->account->validateBudgetAmount( $value->nc1_cuentaIva, $Data['cnc_docdate'], $prc_code, $uncode, $prj_code, round($granTotalIva, $DECI_MALES), 2, $Data['business'] );
				if (isset($BUDGET['error']) && $BUDGET['error'] == true){
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error' => true,
						'data' => $BUDGET,
						'mensaje' => $BUDGET['mensaje']
					);

					return $this->response($respuesta);
				}
				//
				$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

					':ac1_trans_id' => $resInsertAsiento,
					':ac1_account' => $value->nc1_cuentaIva,
					':ac1_debit' => 0,
					':ac1_credit' => round($granTotalIva, $DECI_MALES),
					':ac1_debit_sys' => 0,
					':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['cnc_docdate']) ? $Data['cnc_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['cnc_duedate']) ? $Data['cnc_duedate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => is_numeric($Data['cnc_doctype']) ? $Data['cnc_doctype'] : 0,
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
					':ac1_made_user' => isset($Data['cnc_createby']) ? $Data['cnc_createby'] : NULL,
					':ac1_accperiod' => $periodo['data'],
					':ac1_close' => 0,
					':ac1_cord' => 0,
					':ac1_ven_debit' => 0,
					':ac1_ven_credit' => 0,
					':ac1_fiscal_acct' => 0,
					':ac1_taxid' => $CodigoImp,
					':ac1_isrti' => $Vat,
					':ac1_basert' => 0,
					':ac1_mmcode' => 0,
					':ac1_legal_num' => isset($Data['cnc_cardcode']) ? $Data['cnc_cardcode'] : NULL,
					':ac1_codref' => 1,
					':ac1_line'   => 	$AC1LINE,
					':ac1_base_tax' => round($LineTotal, $DECI_MALES),
					':ac1_codret' => 0,
					':business' => $Data['business'],
					':branch' => $Data['branch']
				));

				if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle  de nota credito se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $resDetalleAsiento,
						'mensaje'	=> 'No se pudo registrar la nota credito'
					);

					$this->response($respuesta);

					return;
				}
			}
			//FIN Procedimiento para llenar Impuestos

			//Procedimiento para llenar Impuestos
			$granTotalIva = 0;
			$MontoSysCR = 0;
			$MontoSysDB = 0;
			foreach ($DetalleConsolidadoImpMulti as $key => $posicion) {
				$granTotalIva = 0;
				$granTotalIvaOriginal = 0;
				$CodigoImp = 0;
				$LineTotal = 0;
				$Vat = 0;

				$prc_code = '';
				$uncode   = '';
				$prj_code = '';

				foreach ($posicion as $key => $value) {
					$granTotalIva = $granTotalIva + $value->nc1_vatsum;
					$Vat = $value->nc1_vat;
					
					$LineTotal = ($LineTotal + $value->nc1_linetotal);
					
					$CodigoImp = $value->codimp;

					$prc_code = $value->ac1_prc_code;
					$uncode   = $value->ac1_uncode;
					$prj_code = $value->ac1_prj_code;
				}

				$granTotalIvaOriginal = $granTotalIva;

				if (trim($Data['cnc_currency']) != $MONEDALOCAL) {
					$granTotalIva = ($granTotalIva * $TasaDocLoc);
					$LineTotal = ($LineTotal * $TasaDocLoc);
				}

				if (trim($Data['cnc_currency']) != $MONEDASYS) {

					$MontoSysCR = ($granTotalIva / $TasaLocSys);
				} else {
					$MontoSysCR = $granTotalIvaOriginal;
				}

				$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
				$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));

				// SE AGREGA AL BALANCE
				$BALANCE = $this->account->addBalance($periodo['data'], round($granTotalIva, $DECI_MALES), $value->nc1_cuentaIva, 2, $Data['cnc_docdate'], $Data['business'], $Data['branch']);
				if (isset($BALANCE['error']) && $BALANCE['error'] == true){
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error' => true,
						'data' => $BALANCE,
						'mensaje' => $BALANCE['mensaje']
					);

					return $this->response($respuesta);
				}
				$BUDGET = $this->account->validateBudgetAmount( $value->nc1_cuentaIva, $Data['cnc_docdate'], $prc_code, $uncode, $prj_code, round($granTotalIva, $DECI_MALES), 2, $Data['business'] );
				if (isset($BUDGET['error']) && $BUDGET['error'] == true){
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error' => true,
						'data' => $BUDGET,
						'mensaje' => $BUDGET['mensaje']
					);

					return $this->response($respuesta);
				}
				//
				$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

					':ac1_trans_id' => $resInsertAsiento,
					':ac1_account' => $value->nc1_cuentaIva,
					':ac1_debit' => 0,
					':ac1_credit' => round($granTotalIva, $DECI_MALES),
					':ac1_debit_sys' => 0,
					':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['cnc_docdate']) ? $Data['cnc_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['cnc_duedate']) ? $Data['cnc_duedate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => is_numeric($Data['cnc_doctype']) ? $Data['cnc_doctype'] : 0,
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
					':ac1_made_user' => isset($Data['cnc_createby']) ? $Data['cnc_createby'] : NULL,
					':ac1_accperiod' => $periodo['data'],
					':ac1_close' => 0,
					':ac1_cord' => 0,
					':ac1_ven_debit' => 0,
					':ac1_ven_credit' => 0,
					':ac1_fiscal_acct' => 0,
					':ac1_taxid' => $CodigoImp,
					':ac1_isrti' => $Vat,
					':ac1_basert' => 0,
					':ac1_mmcode' => 0,
					':ac1_legal_num' => isset($Data['cnc_cardcode']) ? $Data['cnc_cardcode'] : NULL,
					':ac1_codref' => 1,
					':ac1_line'   => 	$AC1LINE,
					':ac1_base_tax' => round($LineTotal, $DECI_MALES),
					':ac1_codret' => 0,
					':business' => $Data['business'],
					':branch' => $Data['branch']
				));

				if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle  de nota credito se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $resDetalleAsiento,
						'mensaje'	=> 'No se pudo registrar la nota credito'
					);

					$this->response($respuesta);

					return;
				}
			}
			//FIN Procedimiento para llenar Impuestos

			if ($Data['cnc_basetype'] != 13) { // solo si el documento no viene de una factura
				//Procedimiento para llenar costo inventario
				foreach ($DetalleConsolidadoCostoInventario as $key => $posicion) {
					$grantotalCostoInventario = 0;
					$grantotalCostoInventarioOriginal = 0;
					$cuentaInventario = "";
					$cdito = 0;
					$dbito = 0;
					$MontoSysDB = 0;
					$MontoSysCR = 0;
					$sinDatos = 0;

					$prc_code = '';
					$uncode   = '';
					$prj_code = '';

					foreach ($posicion as $key => $value) {

						if ($value->ac1_inventory == 1 || $value->ac1_inventory  == '1') {
							$sinDatos++;
							$cuentaInventario = $value->ac1_account;
							$grantotalCostoInventario = ($grantotalCostoInventario + $value->nc1_linetotal);

							$prc_code = $value->ac1_prc_code;
							$uncode   = $value->ac1_uncode;
							$prj_code = $value->ac1_prj_code;
						}
					}



					$grantotalCostoInventarioOriginal = $grantotalCostoInventario;

					if (trim($Data['cnc_currency']) != $MONEDALOCAL) {
						$grantotalCostoInventario = ($grantotalCostoInventario * $TasaDocLoc);
					}

					$cdito = $grantotalCostoInventario;

					if (trim($Data['cnc_currency']) != $MONEDASYS) {
						$MontoSysCR = ($cdito / $TasaLocSys);
					} else {
						$MontoSysCR = $grantotalCostoInventarioOriginal;
					}

					$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
					$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));
					$AC1LINE = $AC1LINE + 1;
					// SE AGREGA AL BALANCE
					$BALANCE = $this->account->addBalance($periodo['data'], round($cdito, $DECI_MALES), $cuentaInventario, 2, $Data['cnc_docdate'], $Data['business'], $Data['branch']);
					if (isset($BALANCE['error']) && $BALANCE['error'] == true){
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error' => true,
							'data' => $BALANCE,
							'mensaje' => $BALANCE['mensaje']
						);

						return $this->response($respuesta);
					}
					$BUDGET = $this->account->validateBudgetAmount( $cuentaInventario, $Data['cnc_docdate'], $prc_code, $uncode, $prj_code, round($cdito, $DECI_MALES), 2, $Data['business'] );
					if (isset($BUDGET['error']) && $BUDGET['error'] == true){
						$this->pedeo->trans_rollback();
	
						$respuesta = array(
							'error' => true,
							'data' => $BUDGET,
							'mensaje' => $BUDGET['mensaje']
						);
	
						return $this->response($respuesta);
					}
					//
					$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

						':ac1_trans_id' => $resInsertAsiento,
						':ac1_account' => $cuentaInventario,
						':ac1_debit' => 0,
						':ac1_credit' => round($cdito, $DECI_MALES),
						':ac1_debit_sys' => 0,
						':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
						':ac1_currex' => 0,
						':ac1_doc_date' => $this->validateDate($Data['cnc_docdate']) ? $Data['cnc_docdate'] : NULL,
						':ac1_doc_duedate' => $this->validateDate($Data['cnc_duedate']) ? $Data['cnc_duedate'] : NULL,
						':ac1_debit_import' => 0,
						':ac1_credit_import' => 0,
						':ac1_debit_importsys' => 0,
						':ac1_credit_importsys' => 0,
						':ac1_font_key' => $resInsert,
						':ac1_font_line' => 1,
						':ac1_font_type' => is_numeric($Data['cnc_doctype']) ? $Data['cnc_doctype'] : 0,
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
						':ac1_made_user' => isset($Data['cnc_createby']) ? $Data['cnc_createby'] : NULL,
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
						':ac1_legal_num' => isset($Data['cnc_cardcode']) ? $Data['cnc_cardcode'] : NULL,
						':ac1_codref' => 1,
						':ac1_line'   => 	$AC1LINE,
						':ac1_base_tax' => 0,
						':ac1_codret' => 0,
						':business' => $Data['business'],
						':branch' => $Data['branch']
					));

					if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
						// Se verifica que el detalle no de error insertando //
					} else {

						// si falla algun insert del detalle  de nota credito se devuelven los cambios realizados por la transaccion,
						// se retorna el error y se detiene la ejecucion del codigo restante.
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'	  => $resDetalleAsiento,
							'mensaje'	=> 'No se pudo registrar la nota credito'
						);

						$this->response($respuesta);

						return;
					}
				}
			}	//FIN Procedimiento para llenar costo inventario
			// PROCEDIMIENTO PARA LLENAR ASIENTO DELVOLUCION COMPRA ARTICULO NO INVENTARIABLE


			// ASIENTO PARA GASTO CUANDO NO MUEVE INVENTARIO
			if (!isset($DetalleConsolidadoCostoInventario[0])) {
				foreach ($DetalleConsolidadoCostoCosto as $key => $posicion) {
				$grantotalCostoInventario = 0;
				$grantotalCostoInventarioOriginal = 0;
				$cuentaInventario = "";
				$cdito = 0;
				$dbito = 0;
				$MontoSysDB = 0;
				$MontoSysCR = 0;
				$sinDatos = 0;

				$prc_code = '';
				$uncode   = '';
				$prj_code = '';

				foreach ($posicion as $key => $value) {

					if ($value->ac1_inventory == 1 || $value->ac1_inventory  == '1') {
							$sinDatos++;
							$cuentaInventario = $value->ac1_account;
							$grantotalCostoInventario = ($grantotalCostoInventario + $value->nc1_linetotal);
			
							$prc_code = $value->ac1_prc_code;
							$uncode   = $value->ac1_uncode;
							$prj_code = $value->ac1_prj_code;
					}
				}

					$grantotalCostoInventarioOriginal = $grantotalCostoInventario;

					if (trim($Data['cnc_currency']) != $MONEDALOCAL) {
						$grantotalCostoInventario = ($grantotalCostoInventario * $TasaDocLoc);
					}

					$cdito = $grantotalCostoInventario;

					if (trim($Data['cnc_currency']) != $MONEDASYS) {
					$MontoSysCR = ($cdito / $TasaLocSys);
					} else {
					$MontoSysCR = $grantotalCostoInventarioOriginal;
					}

					$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
					$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));
					$AC1LINE = $AC1LINE + 1;
					// SE AGREGA AL BALANCE
					$BALANCE = $this->account->addBalance($periodo['data'], round($cdito, $DECI_MALES), $cuentaInventario, 2, $Data['cnc_docdate'], $Data['business'], $Data['branch']);
					if (isset($BALANCE['error']) && $BALANCE['error'] == true){
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error' => true,
							'data' => $BALANCE,
							'mensaje' => $BALANCE['mensaje']
						);

						return $this->response($respuesta);
					}
					$BUDGET = $this->account->validateBudgetAmount( $cuentaInventario, $Data['cnc_docdate'], $prc_code, $uncode, $prj_code, round($cdito, $DECI_MALES), 2, $Data['business'] );
					if (isset($BUDGET['error']) && $BUDGET['error'] == true){
						$this->pedeo->trans_rollback();
	
						$respuesta = array(
							'error' => true,
							'data' => $BUDGET,
							'mensaje' => $BUDGET['mensaje']
						);
	
						return $this->response($respuesta);
					}
					//
					$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

						':ac1_trans_id' => $resInsertAsiento,
						':ac1_account' => $cuentaInventario,
						':ac1_debit' => 0,
						':ac1_credit' => round($cdito, $DECI_MALES),
						':ac1_debit_sys' => 0,
						':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
						':ac1_currex' => 0,
						':ac1_doc_date' => $this->validateDate($Data['cnc_docdate']) ? $Data['cnc_docdate'] : NULL,
						':ac1_doc_duedate' => $this->validateDate($Data['cnc_duedate']) ? $Data['cnc_duedate'] : NULL,
						':ac1_debit_import' => 0,
						':ac1_credit_import' => 0,
						':ac1_debit_importsys' => 0,
						':ac1_credit_importsys' => 0,
						':ac1_font_key' => $resInsert,
						':ac1_font_line' => 1,
						':ac1_font_type' => is_numeric($Data['cnc_doctype']) ? $Data['cnc_doctype'] : 0,
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
						':ac1_made_user' => isset($Data['cnc_createby']) ? $Data['cnc_createby'] : NULL,
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
						':ac1_legal_num' => isset($Data['cnc_cardcode']) ? $Data['cnc_cardcode'] : NULL,
						':ac1_codref' => 1,
						':ac1_line'   => 	$AC1LINE,
						':ac1_base_tax' => 0,
						':ac1_codret' => 0,
						':business' => $Data['business'],
						':branch' => $Data['branch']
					));

					if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
						// Se verifica que el detalle no de error insertando //
					} else {

						// si falla algun insert del detalle  de nota credito se devuelven los cambios realizados por la transaccion,
						// se retorna el error y se detiene la ejecucion del codigo restante.
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'	  => $resDetalleAsiento,
							'mensaje'	=> 'No se pudo registrar la nota credito'
						);

						$this->response($respuesta);

						return;
					}
				}
			}
			

			foreach ($DetalleConsolidadoItemNoInventariable as $key => $posicion) {
				$grantotalItemNoInventariable = 0;
				$grantotalItemNoInventariableOriginal = 0;
				$CuentaItemNoInventariable = "";
				$cdito = 0;
				$dbito = 0;
				$MontoSysDB = 0;
				$MontoSysCR = 0;
				$sinDatos = 0;

				$prc_code = '';
				$uncode   = '';
				$prj_code = '';

				foreach ($posicion as $key => $value) {


					$sinDatos++;
					$CuentaItemNoInventariable = $value->ac1_account;
					$grantotalItemNoInventariable = ($grantotalItemNoInventariable + $value->nc1_linetotal);

					
					$prc_code = $value->ac1_prc_code;
					$uncode   = $value->ac1_uncode;
					$prj_code = $value->ac1_prj_code;
				}



				$grantotalItemNoInventariableOriginal = $grantotalItemNoInventariable;

				if (trim($Data['cnc_currency']) != $MONEDALOCAL) {
					$grantotalItemNoInventariable = ($grantotalItemNoInventariable * $TasaDocLoc);
				}

				$cdito = $grantotalItemNoInventariable;

				if (trim($Data['cnc_currency']) != $MONEDASYS) {
					$MontoSysCR = ($cdito / $TasaLocSys);
				} else {
					$MontoSysCR = $grantotalItemNoInventariableOriginal;
				}

				$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
				$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));
				$AC1LINE = $AC1LINE + 1;
				// SE AGREGA AL BALANCE
				$BALANCE = $this->account->addBalance($periodo['data'], round($cdito, $DECI_MALES), $CuentaItemNoInventariable, 2, $Data['cnc_docdate'], $Data['business'], $Data['branch']);
				if (isset($BALANCE['error']) && $BALANCE['error'] == true){
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error' => true,
						'data' => $BALANCE,
						'mensaje' => $BALANCE['mensaje']
					);

					return $this->response($respuesta);
				}
				$BUDGET = $this->account->validateBudgetAmount( $CuentaItemNoInventariable, $Data['cnc_docdate'], $prc_code, $uncode, $prj_code, round($cdito, $DECI_MALES), 2, $Data['business'] );
				if (isset($BUDGET['error']) && $BUDGET['error'] == true){
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error' => true,
						'data' => $BUDGET,
						'mensaje' => $BUDGET['mensaje']
					);

					return $this->response($respuesta);
				}
				//
				$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

					':ac1_trans_id' => $resInsertAsiento,
					':ac1_account' => $CuentaItemNoInventariable,
					':ac1_debit' => 0,
					':ac1_credit' => round($cdito, $DECI_MALES),
					':ac1_debit_sys' => 0,
					':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['cnc_docdate']) ? $Data['cnc_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['cnc_duedate']) ? $Data['cnc_duedate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => is_numeric($Data['cnc_doctype']) ? $Data['cnc_doctype'] : 0,
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
					':ac1_made_user' => isset($Data['cnc_createby']) ? $Data['cnc_createby'] : NULL,
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
					':ac1_legal_num' => isset($Data['cnc_cardcode']) ? $Data['cnc_cardcode'] : NULL,
					':ac1_codref' => 1,
					':ac1_line'   => 	$AC1LINE,
					':ac1_base_tax' => 0,
					':ac1_codret' => 0,
					':business' => $Data['business'],
					':branch' => $Data['branch']
				));

				if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle  de nota credito se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $resDetalleAsiento,
						'mensaje'	=> 'No se pudo registrar la nota credito'
					);

					$this->response($respuesta);

					return;
				}
			}
			//FIN PROCEDIMIENTO PARA LLENAR ASIENTO DELVOLUCION COMPRA ARTICULO NO INVENTARIABLE


			if ($Data['cnc_basetype'] == 13) {

				//CUENTA PUENTE DE INVENTARIO

				$sqlcuentainventario = "SELECT coalesce(pge_bridge_inv_purch, 0) as pge_bridge_inv_purch, coalesce(pge_bridge_purch_int, 0) as pge_bridge_purch_int FROM pgem WHERE pge_id = :business";
				$rescuentainventario = $this->pedeo->queryTable($sqlcuentainventario, array(':business' => $Data['business']));

				if (isset($rescuentainventario[0]) && $rescuentainventario[0]['pge_bridge_inv_purch'] != 0) {
				} else {
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $rescuentainventario,
						'mensaje'	=> 'No se pudo registrar la nota credito de compras'
					);

					$this->response($respuesta);

					return;
				}

				foreach ($DetalleConsolidadoCostoCosto as $key => $posicion) {
					$grantotalCostoInventario = 0;
					$grantotalCostoInventarioOriginal = 0;
					$dbito = 0;
					$cdito = 0;
					$sinDatos = 0;
					$MontoSysDB = 0;
					$MontoSysCR = 0;
					$cuentaInventario = "";

					$prc_code = '';
					$uncode   = '';
					$prj_code = '';
					foreach ($posicion as $key => $value) {

						if ($value->ac1_inventory == 1 || $value->ac1_inventory  == '1') {

							$sinDatos++;

							if (isset($Data['cnc_api']) && $Data['cnc_api'] == 1) {
								$cuentaInventario = $rescuentainventario[0]['pge_bridge_purch_int'];
							} else {
								$cuentaInventario = $rescuentainventario[0]['pge_bridge_inv_purch'];
							}

							$grantotalCostoInventario = ($grantotalCostoInventario + $value->nc1_linetotal);

							$prc_code = $value->ac1_prc_code;
							$uncode   = $value->ac1_uncode;
							$prj_code = $value->ac1_prj_code;
						}
					}


					if ($sinDatos > 0) {

						$grantotalCostoInventarioOriginal = $grantotalCostoInventario;

						if (trim($Data['cnc_currency']) != $MONEDALOCAL) {

							$grantotalCostoInventario = ($grantotalCostoInventario * $TasaDocLoc);
						}

						$cdito = $grantotalCostoInventario;

						if (trim($Data['cnc_currency']) != $MONEDASYS) {
							$MontoSysCR = ($cdito / $TasaLocSys);
						} else {
							$MontoSysCR = $grantotalCostoInventarioOriginal;
						}
						$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
						$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));
						$AC1LINE = $AC1LINE + 1;
						// SE AGREGA AL BALANCE
						$BALANCE = $this->account->addBalance($periodo['data'], round($cdito, $DECI_MALES), $cuentaInventario, 2, $Data['cnc_docdate'], $Data['business'], $Data['branch']);
						if (isset($BALANCE['error']) && $BALANCE['error'] == true){
							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error' => true,
								'data' => $BALANCE,
								'mensaje' => $BALANCE['mensaje']
							);

							return $this->response($respuesta);
						}
						$BUDGET = $this->account->validateBudgetAmount( $cuentaInventario, $Data['cnc_docdate'], $prc_code, $uncode, $prj_code, round($cdito, $DECI_MALES), 2, $Data['business'] );
						if (isset($BUDGET['error']) && $BUDGET['error'] == true){
							$this->pedeo->trans_rollback();
		
							$respuesta = array(
								'error' => true,
								'data' => $BUDGET,
								'mensaje' => $BUDGET['mensaje']
							);
		
							return $this->response($respuesta);
						}
						//
						$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

							':ac1_trans_id' => $resInsertAsiento,
							':ac1_account' => $cuentaInventario,
							':ac1_debit' => 0,
							':ac1_credit' => round($cdito, $DECI_MALES),
							':ac1_debit_sys' => 0,
							':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
							':ac1_currex' => 0,
							':ac1_doc_date' => $this->validateDate($Data['cnc_docdate']) ? $Data['cnc_docdate'] : NULL,
							':ac1_doc_duedate' => $this->validateDate($Data['cnc_duedate']) ? $Data['cnc_duedate'] : NULL,
							':ac1_debit_import' => 0,
							':ac1_credit_import' => 0,
							':ac1_debit_importsys' => 0,
							':ac1_credit_importsys' => 0,
							':ac1_font_key' => $resInsert,
							':ac1_font_line' => 1,
							':ac1_font_type' => is_numeric($Data['cnc_doctype']) ? $Data['cnc_doctype'] : 0,
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
							':ac1_made_user' => isset($Data['cnc_createby']) ? $Data['cnc_createby'] : NULL,
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
							':ac1_legal_num' => isset($Data['cnc_cardcode']) ? $Data['cnc_cardcode'] : NULL,
							':ac1_codref' => 1,
							':ac1_line'   => 	$AC1LINE,
							':ac1_base_tax' => 0,
							':ac1_codret' => 0,
							':business' => $Data['business'],
							':branch' => $Data['branch']
						));

						if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
							// Se verifica que el detalle no de error insertando //
						} else {

							// si falla algun insert del detalle de la nota debito de compras se devuelven los cambios realizados por la transaccion,
							// se retorna el error y se detiene la ejecucion del codigo restante.
							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data'	  => $resDetalleAsiento,
								'mensaje'	=> 'No se pudo registrar la nota debito de compras 7'
							);

							$this->response($respuesta);

							return;
						}
					} //aaa

				}
			}

			//FIN Procedimiento para llenar costo costo

			//Procedimiento para llenar cuentas por cobrar

			$sqlcuentaCxP = "SELECT  f1.dms_card_code, f2.mgs_acct FROM dmsn AS f1
													 JOIN dmgs  AS f2
													 ON CAST(f2.mgs_id AS varchar(100)) = f1.dms_group_num
													 WHERE  f1.dms_card_code = :dms_card_code
													 AND f1.dms_card_type = '2'"; //2 para proveedores";


			$rescuentaCxP = $this->pedeo->queryTable($sqlcuentaCxP, array(":dms_card_code" => $Data['cnc_cardcode']));

			if (isset($rescuentaCxP[0])) {

				$debitoo = 0;
				$creditoo = 0;
				$MontoSysDB = 0;
				$MontoSysCR = 0;
				$TotalDoc = $Data['cnc_doctotal'];
				$TotalDoc2 = 0;
				$TotalDocOri = $TotalDoc;

				$cuentaCxP = $rescuentaCxP[0]['mgs_acct'];

				if (trim($Data['cnc_currency']) != $MONEDALOCAL) {
					$TotalDoc = ($TotalDoc * $TasaDocLoc);
				}


				if (trim($Data['cnc_currency']) != $MONEDASYS) {
					$MontoSysDB = ($TotalDoc / $TasaLocSys);
				} else {
					$MontoSysDB = $TotalDocOri;
				}

				$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
				$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));

				$AC1LINE = $AC1LINE + 1;

				$refFiscal = "";
				if ($Data['cnc_basetype'] == 15) {
					$TotalDoc2 = $TotalDoc;				
					$refFiscal = (isset($Data['cnc_tax_control_num'])) ? $Data['cnc_tax_control_num'] :  "";
					
				} else {
					$TotalDoc2 = 0;
				}

				// SE AGREGA AL BALANCE
				$BALANCE = $this->account->addBalance($periodo['data'], round($TotalDoc, $DECI_MALES), $cuentaCxP, 1, $Data['cnc_docdate'], $Data['business'], $Data['branch']);
				if (isset($BALANCE['error']) && $BALANCE['error'] == true){
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error' => true,
						'data' => $BALANCE,
						'mensaje' => $BALANCE['mensaje']
					);

					return $this->response($respuesta);
				}
				$BUDGET = $this->account->validateBudgetAmount( $cuentaCxP, $Data['cnc_docdate'], '', '', '', round($TotalDoc, $DECI_MALES), 1, $Data['business'] );
				if (isset($BUDGET['error']) && $BUDGET['error'] == true){
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error' => true,
						'data' => $BUDGET,
						'mensaje' => $BUDGET['mensaje']
					);

					return $this->response($respuesta);
				}
				//			
				
				$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

					':ac1_trans_id' => $resInsertAsiento,
					':ac1_account' => $cuentaCxP,
					':ac1_debit' => round($TotalDoc, $DECI_MALES),
					':ac1_credit' => 0,
					':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
					':ac1_credit_sys' => 0,
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['cnc_docdate']) ? $Data['cnc_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['cnc_duedate']) ? $Data['cnc_duedate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => is_numeric($Data['cnc_doctype']) ? $Data['cnc_doctype'] : 0,
					':ac1_accountvs' => 1,
					':ac1_doctype' => 18,
					':ac1_ref1' => "",
					':ac1_ref2' => $refFiscal,
					':ac1_ref3' => "",
					':ac1_prc_code' => NULL,
					':ac1_uncode' => NULL,
					':ac1_prj_code' => NULL,
					':ac1_rescon_date' => NULL,
					':ac1_recon_total' => 0,
					':ac1_made_user' => isset($Data['cnc_createby']) ? $Data['cnc_createby'] : NULL,
					':ac1_accperiod' => $periodo['data'],
					':ac1_close' => 0,
					':ac1_cord' => 0,
					':ac1_ven_debit' => round($TotalDoc, $DECI_MALES),
					':ac1_ven_credit' => round($TotalDoc2, $DECI_MALES),
					':ac1_fiscal_acct' => 0,
					':ac1_taxid' => 0,
					':ac1_isrti' => 0,
					':ac1_basert' => 0,
					':ac1_mmcode' => 0,
					':ac1_legal_num' => isset($Data['cnc_cardcode']) ? $Data['cnc_cardcode'] : NULL,
					':ac1_codref' => 1,
					':ac1_line'   => 	$AC1LINE,
					':ac1_base_tax' => 0,
					':ac1_codret' => 0,
					':business' => $Data['business'],
					':branch' => $Data['branch']
				));

				if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle  de nota credito se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $resDetalleAsiento,
						'mensaje'	=> 'No se pudo registrar la nota credito'
					);

					$this->response($respuesta);

					return;
				}
			} else {

				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'	  => $rescuentaCxP,
					'mensaje'	=> 'No se pudo registrar la nota credito, el tercero no tiene cuenta asociada'
				);

				$this->response($respuesta);

				return;
			}
			//FIN Procedimiento para llenar cuentas por cobrar


			//PROCEDIMIENTO PARA LLENAR ASIENTO DE RETENCIONES
			foreach ($DetalleConsolidadoRetencion as $key => $posicion) {
				$totalRetencion = 0;
				$BaseLineaRet = 0;
				$totalRetencionOriginal = 0;
				$dbito = 0;
				$cdito = 0;
				$MontoSysDB = 0;
				$MontoSysCR = 0;
				$cuenta = '';
				$Basert = 0;
				$Profitrt = 0;
				$CodRet = 0;
				foreach ($posicion as $key => $value) {

					$sqlcuentaretencion = "SELECT mrt_acctcode, mrt_code FROM dmrt WHERE mrt_id = :mrt_id";
					$rescuentaretencion = $this->pedeo->queryTable($sqlcuentaretencion, array(
						'mrt_id' => $value->crt_typert
					));

					if (isset($rescuentaretencion[0])) {

						$cuenta = $rescuentaretencion[0]['mrt_acctcode'];
						$totalRetencion = $totalRetencion + $value->crt_basert;
						$Profitrt =  $value->crt_profitrt;
						$CodRet = $rescuentaretencion[0]['mrt_code'];
						$BaseLineaRet = $BaseLineaRet + $value->crt_baseln;
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'	  => $rescuentaretencion,
							'mensaje'	=> 'No se pudo registrar la nota credito de compras, no se encontro la cuenta para la retención ' . $value->crt_typert
						);

						$this->response($respuesta);

						return;
					}
				}

				$Basert = $BaseLineaRet;
				$totalRetencionOriginal = $totalRetencion;

				if (trim($Data['cnc_currency']) != $MONEDALOCAL) {
					$totalRetencion = ($totalRetencion * $TasaDocLoc);
					$BaseLineaRet = ($BaseLineaRet * $TasaDocLoc);
					$Basert = $BaseLineaRet;
				}


				if (trim($Data['cnc_currency']) != $MONEDASYS) {
					$MontoSysDB = ($totalRetencion / $TasaLocSys);
				} else {
					$MontoSysDB = 	$totalRetencionOriginal;
				}

				$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
				$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));
				$AC1LINE = $AC1LINE + 1;
				// SE AGREGA AL BALANCE
				$BALANCE = $this->account->addBalance($periodo['data'], round($totalRetencion, $DECI_MALES), $cuenta, 1, $Data['cnc_docdate'], $Data['business'], $Data['branch']);
				if (isset($BALANCE['error']) && $BALANCE['error'] == true){
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error' => true,
						'data' => $BALANCE,
						'mensaje' => $BALANCE['mensaje']
					);

					return $this->response($respuesta);
				}
				$BUDGET = $this->account->validateBudgetAmount( $cuenta, $Data['cnc_docdate'], '', '', '', round($totalRetencion, $DECI_MALES), 1, $Data['business'] );
				if (isset($BUDGET['error']) && $BUDGET['error'] == true){
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error' => true,
						'data' => $BUDGET,
						'mensaje' => $BUDGET['mensaje']
					);

					return $this->response($respuesta);
				}
				//
				$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

					':ac1_trans_id' => $resInsertAsiento,
					':ac1_account' => $cuenta,
					':ac1_debit' => round($totalRetencion, $DECI_MALES),
					':ac1_credit' => 0,
					':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
					':ac1_credit_sys' => 0,
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['cnc_docdate']) ? $Data['cnc_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['cnc_duedate']) ? $Data['cnc_duedate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => is_numeric($Data['cnc_doctype']) ? $Data['cnc_doctype'] : 0,
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
					':ac1_made_user' => isset($Data['cnc_createby']) ? $Data['cnc_createby'] : NULL,
					':ac1_accperiod' => $periodo['data'],
					':ac1_close' => 0,
					':ac1_cord' => 0,
					':ac1_ven_debit' => 0,
					':ac1_ven_credit' => 0,
					':ac1_fiscal_acct' => 0,
					':ac1_taxid' => 0,
					':ac1_isrti' => $Profitrt,
					':ac1_basert' => round($Basert, $DECI_MALES),
					':ac1_mmcode' => 0,
					':ac1_legal_num' => isset($Data['cnc_cardcode']) ? $Data['cnc_cardcode'] : NULL,
					':ac1_codref' => 1,
					':ac1_line'   => 	$AC1LINE,
					':ac1_base_tax' => 0,
					':ac1_codret' => $CodRet,
					':business' => $Data['business'],
					':branch' => $Data['branch']
				));



				if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle de la factura de compras se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $resDetalleAsiento,
						'mensaje'	=> 'No se pudo registrar la factura de compras'
					);

					$this->response($respuesta);

					return;
				}
			}


			//FIN DE OPERACIONES VITALES


			// SE VALIDA DIFERENCIA POR DECIMALES
			// Y SE AGREGA UN ASIENTO DE DIFERENCIA EN DECIMALES
			// SEGUN SEA EL CASO
			$credito = 0;
			$debito = 0;

			if ($SumaCreditosSYS > $SumaDebitosSYS || $SumaDebitosSYS > $SumaCreditosSYS) {

				$sqlCuentaDiferenciaDecimal = "SELECT pge_acc_ajp FROM pgem WHERE pge_id = :business";
				$resCuentaDiferenciaDecimal = $this->pedeo->queryTable($sqlCuentaDiferenciaDecimal, array(':business' => $Data['business']));

				if (isset($resCuentaDiferenciaDecimal[0]) && is_numeric($resCuentaDiferenciaDecimal[0]['pge_acc_ajp'])) {

					if ($SumaCreditosSYS > $SumaDebitosSYS) { // DIFERENCIA EN CREDITO EL VALOR SE COLOCA EN DEBITO

						$debito = ($SumaCreditosSYS - $SumaDebitosSYS);
					} else { // DIFERENCIA EN DEBITO EL VALOR SE COLOCA EN CREDITO

						$credito = ($SumaDebitosSYS - $SumaCreditosSYS);
					}


					if (round($debito + $credito, 2) > 0) {
						$AC1LINE = $AC1LINE + 1;
						$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

							':ac1_trans_id' => $resInsertAsiento,
							':ac1_account' => $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'],
							':ac1_debit' => 0,
							':ac1_credit' => 0,
							':ac1_debit_sys' => round($debito, $DECI_MALES),
							':ac1_credit_sys' => round($credito, $DECI_MALES),
							':ac1_currex' => 0,
							':ac1_doc_date' => $this->validateDate($Data['cnc_docdate']) ? $Data['cnc_docdate'] : NULL,
							':ac1_doc_duedate' => $this->validateDate($Data['cnc_duedate']) ? $Data['cnc_duedate'] : NULL,
							':ac1_debit_import' => 0,
							':ac1_credit_import' => 0,
							':ac1_debit_importsys' => 0,
							':ac1_credit_importsys' => 0,
							':ac1_font_key' => $resInsert,
							':ac1_font_line' => 1,
							':ac1_font_type' => is_numeric($Data['cnc_doctype']) ? $Data['cnc_doctype'] : 0,
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
							':ac1_made_user' => isset($Data['cnc_createby']) ? $Data['cnc_createby'] : NULL,
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
							':ac1_legal_num' => isset($Data['cnc_cardcode']) ? $Data['cnc_cardcode'] : NULL,
							':ac1_codref' => 1,
							':ac1_line'   => 	$AC1LINE,
							':ac1_base_tax' => 0,
							':ac1_codret' => 0,
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
			}


			// VALIDANDO ESTADOS DE DOCUMENTOS

			// SE VALIDA QUE LA FACTURA BASE NO SEA MENOR QUE LA NOTA CREDITO
			if ($Data['cnc_basetype'] == 15) {

				$sqlBaseFactura = "SELECT * FROM dcfc WHERE cfc_docentry = :cfc_docentry";
				$resBaseFactura = $this->pedeo->queryTable($sqlBaseFactura, array(':cfc_docentry' => $Data['cnc_baseentry']));

				if (isset($resBaseFactura[0])) {

					$tFC = $resBaseFactura[0]['cfc_doctotal'];

					$tNC =  $Data['cnc_doctotal'];

					if (trim($resBaseFactura[0]['cfc_currency']) != $MONEDALOCAL) {
						$tFC = ($tFC * $TasaDocLoc);
					}

					if (trim($Data['cnc_currency']) != $MONEDALOCAL) {
						$tNC  = ($tNC * $TasaDocLoc);
					}


					if ($tNC > $tFC) {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'	  => $resBaseFactura,
							'mensaje'	=> 'La valor total de nota credito es mayor a la factura base'
						);

						$this->response($respuesta);

						return;
					}
				} else {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $resBaseFactura,
						'mensaje'	=> 'No se encontro la factura base'
					);

					$this->response($respuesta);

					return;
				}
			}


			//SE VALIDA QUE EL PAY TO DAY DE LA FACTURA
			if ($Data['cnc_basetype'] == 15) { // SOLO CUANDO ES UNA FACTURA


				$valorAppl =  $Data['cnc_doctotal'];

				if (trim($Data['cnc_currency']) != $MONEDALOCAL) {
					$valorAppl = $Data['cnc_doctotal'] * $TasaDocLoc;
				}


				$sqlUpdateFactPay = "UPDATE  dcfc  SET cfc_paytoday = COALESCE(cfc_paytoday,0)+:cfc_paytoday WHERE cfc_docentry = :cfc_docentry and cfc_doctype = :cfc_doctype";

				$resUpdateFactPay = $this->pedeo->updateRow($sqlUpdateFactPay, array(

					':cfc_paytoday' => $valorAppl,
					':cfc_docentry' => $Data['cnc_baseentry'],
					':cfc_doctype'  => $Data['cnc_basetype']

				));

				if (is_numeric($resUpdateFactPay) && $resUpdateFactPay == 1) {

					// SE VALIDA QUE EL VALOR NO SEA MAYOR QUE EL DOCUMENTO
					//  Y QUE NO ESTA POR DEBAJO DEL DOCUMENTO

					$valorF = $this->pedeo->queryTable("SELECT * FROM dcfc WHERE cfc_docentry = :cfc_docentry AND cfc_doctype = :cfc_doctype", array(
						':cfc_docentry' => $Data['cnc_baseentry'],
						':cfc_doctype'  => $Data['cnc_basetype']
					));

					if ( isset($valorF[0]) ) {

						if ( $valorF[0]['cfc_paytoday'] > $valorF[0]['cfc_doctotal'] ) {

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' => $resUpdateFactPay,
								'mensaje'	=> 'El monto aplicado supera el monto total de la factura ID # '.$Data['cnc_baseentry']
							);

							return $this->response($respuesta);

						}


						
						if ( $valorF[0]['cfc_paytoday'] < 0 ) {

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' => $resUpdateFactPay,
								'mensaje'	=> 'No es posible bajar el valor total del documento factura ID # '.$Data['vnc_baseentry']
							);

							return $this->response($respuesta);

						}

					}else{

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' => $resUpdateFactPay,
							'mensaje'	=> 'No se pudo verificar el valor aplicado por la nota credito a la factura ID # '.$Data['cnc_baseentry']
						);

						return $this->response($respuesta);

					}
				} else {
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resUpdateFactPay,
						'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura ' . $Data['cnc_baseentry']
					);

					$this->response($respuesta);

					return;
				}
			}

			// SE ACTUALIZA VALOR EN EL ASIENTO CONTABLE
			// GENERADO EN LA FACTURA
			if ($Data['cnc_basetype'] == 15) { // SOLO CUANDO ES UNA FACTURA

				$valorAppl =  $Data['cnc_doctotal'];

				if (trim($Data['cnc_currency']) != $MONEDALOCAL) {
					$valorAppl = $Data['cnc_doctotal'] * $TasaDocLoc;
				}


				$sqlcuentaCxP = "SELECT  f1.dms_card_code, f2.mgs_acct FROM dmsn AS f1
														 JOIN dmgs  AS f2
														 ON CAST(f2.mgs_id AS varchar(100)) = f1.dms_group_num
														 WHERE  f1.dms_card_code = :dms_card_code
														 AND f1.dms_card_type = '2'"; //2 para proveedores";


				$rescuentaCxP = $this->pedeo->queryTable($sqlcuentaCxP, array(":dms_card_code" => $Data['cnc_cardcode']));

				if (!isset($rescuentaCxP[0])) {
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $rescuentaCxP,
						'mensaje'	=> 'No se pudo registrar la factura de ventas, el socio de negocio no tiene cuenta asociada'
					);

					$this->response($respuesta);

					return;
				}

				$cuentaCxP = $rescuentaCxP[0]['mgs_acct'];

				$slqUpdateVenDebit = "UPDATE mac1
								SET ac1_ven_debit = ac1_ven_debit + :ac1_ven_debit
								WHERE ac1_legal_num = :ac1_legal_num
								AND ac1_font_key = :ac1_font_key
								AND ac1_font_type = :ac1_font_type
								AND ac1_account = :ac1_account";
				$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

					':ac1_ven_debit'  => $valorAppl,
					':ac1_legal_num'  => $Data['cnc_cardcode'],
					':ac1_font_key'   => $Data['cnc_baseentry'],
					':ac1_font_type'  => $Data['cnc_basetype'],
					':ac1_account'    => $cuentaCxP

				));

				if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
				} else {
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resUpdateVenDebit,
						'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura ' . $Data['cnc_baseentry']
					);

					$this->response($respuesta);

					return;
				}
			}
			//
			//SE CIERRA LA NOTA CREADA
			if ($Data['cnc_basetype'] == 15) { // SOLO CUANDO ES UNA FACTURA

				$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

				$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


					':bed_docentry' => $resInsert,
					':bed_doctype' =>  $Data['cnc_doctype'],
					':bed_status' => 3, //ESTADO CERRADO
					':bed_createby' => $Data['cnc_createby'],
					':bed_date' => date('Y-m-d'),
					':bed_baseentry' => $Data['cnc_baseentry'],
					':bed_basetype' => $Data['cnc_basetype']
				));

				if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
				} else {
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertEstado,
						'mensaje'	=> 'No se pudo registrar la nota debito'
					);


					$this->response($respuesta);

					return;
				}
			}

			// SE VALIDA SALDOS PARA CERRAR FACTURA
			if ($Data['cnc_basetype'] == 15) {

				$resEstado = $this->generic->validateBalanceAndClose($Data['cnc_baseentry'], $Data['cnc_basetype'], 'dcfc', 'cfc');

				if (isset($resEstado['error']) && $resEstado['error'] === true) {
					$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																						VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

					$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


						':bed_docentry' => $Data['cnc_baseentry'],
						':bed_doctype' => $Data['cnc_basetype'],
						':bed_status' => 3, //ESTADO CERRADO
						':bed_createby' => $Data['cnc_createby'],
						':bed_date' => date('Y-m-d'),
						':bed_baseentry' => $resInsert,
						':bed_basetype' => $Data['cnc_doctype']
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

			// FIN VALIDACION DE ESTADOS

			// $sqlmac1 = "SELECT * FROM  mac1 WHERE ac1_trans_id = :ac1_trans_id";
			// $ressqlmac1 = $this->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $resInsertAsiento ));
			// print_r(json_encode($ressqlmac1));
			// exit;

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


			// Si todo sale bien despues de insertar el detalle  de nota credito
			// se confirma la trasaccion  para que los cambios apliquen permanentemente
			// en la base de datos y se confirma la operacion exitosa.
			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'Nota credito registrada con exito'
			);
		} else {
			// Se devuelven los cambios realizados en la transaccion
			// si occurre un error  y se muestra devuelve el error.
			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data' => $resInsert,
				'mensaje'	=> 'No se pudo registrar la nota credito'
			);
		}

		$this->response($respuesta);
	}

	//ACTUALIZAR  de nota credito
	public function updatePurchaseNc_post()
	{

		$Data = $this->post();

		if (
			!isset($Data['cnc_docentry']) or !isset($Data['cnc_docnum']) or
			!isset($Data['cnc_docdate']) or !isset($Data['cnc_duedate']) or
			!isset($Data['cnc_duedev']) or !isset($Data['cnc_pricelist']) or
			!isset($Data['cnc_cardcode']) or !isset($Data['cnc_cardname']) or
			!isset($Data['cnc_currency']) or !isset($Data['cnc_contacid']) or
			!isset($Data['cnc_slpcode']) or !isset($Data['cnc_empid']) or
			!isset($Data['cnc_comment']) or !isset($Data['cnc_doctotal']) or
			!isset($Data['cnc_baseamnt']) or !isset($Data['cnc_taxtotal']) or
			!isset($Data['cnc_discprofit']) or !isset($Data['cnc_discount']) or
			!isset($Data['cnc_createat']) or !isset($Data['cnc_baseentry']) or
			!isset($Data['cnc_basetype']) or !isset($Data['cnc_doctype']) or
			!isset($Data['cnc_idadd']) or !isset($Data['cnc_adress']) or
			!isset($Data['cnc_paytype']) or !isset($Data['cnc_attch']) or
			!isset($Data['detail'])
		) {

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
				'mensaje' => 'No se encontro el detalle  de nota credito'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlUpdate = "UPDATE dcnc	SET cnc_docdate=:cnc_docdate,cnc_duedate=:cnc_duedate, cnc_duedev=:cnc_duedev, cnc_pricelist=:cnc_pricelist, cnc_cardcode=:cnc_cardcode,
			  						cnc_cardname=:cnc_cardname, cnc_currency=:cnc_currency, cnc_contacid=:cnc_contacid, cnc_slpcode=:cnc_slpcode,
										cnc_empid=:cnc_empid, cnc_comment=:cnc_comment, cnc_doctotal=:cnc_doctotal, cnc_baseamnt=:cnc_baseamnt,
										cnc_taxtotal=:cnc_taxtotal, cnc_discprofit=:cnc_discprofit, cnc_discount=:cnc_discount, cnc_createat=:cnc_createat,
										cnc_baseentry=:cnc_baseentry, cnc_basetype=:cnc_basetype, cnc_doctype=:cnc_doctype, cnc_idadd=:cnc_idadd,
										cnc_adress=:cnc_adress, cnc_paytype=:cnc_paytype,cnc_internal_comments=:cnc_internal_comments WHERE cnc_docentry=:cnc_docentry";

		$this->pedeo->trans_begin();

		$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
			':cnc_docnum' => is_numeric($Data['cnc_docnum']) ? $Data['cnc_docnum'] : 0,
			':cnc_docdate' => $this->validateDate($Data['cnc_docdate']) ? $Data['cnc_docdate'] : NULL,
			':cnc_duedate' => $this->validateDate($Data['cnc_duedate']) ? $Data['cnc_duedate'] : NULL,
			':cnc_duedev' => $this->validateDate($Data['cnc_duedev']) ? $Data['cnc_duedev'] : NULL,
			':cnc_pricelist' => is_numeric($Data['cnc_pricelist']) ? $Data['cnc_pricelist'] : 0,
			':cnc_cardcode' => isset($Data['cnc_pricelist']) ? $Data['cnc_pricelist'] : NULL,
			':cnc_cardname' => isset($Data['cnc_cardname']) ? $Data['cnc_cardname'] : NULL,
			':cnc_currency' => isset($Data['cnc_currency']) ? $Data['cnc_currency'] : NULL,
			':cnc_contacid' => isset($Data['cnc_contacid']) ? $Data['cnc_contacid'] : NULL,
			':cnc_slpcode' => is_numeric($Data['cnc_slpcode']) ? $Data['cnc_slpcode'] : 0,
			':cnc_empid' => is_numeric($Data['cnc_empid']) ? $Data['cnc_empid'] : 0,
			':cnc_comment' => isset($Data['cnc_comment']) ? $Data['cnc_comment'] : NULL,
			':cnc_doctotal' => is_numeric($Data['cnc_doctotal']) ? $Data['cnc_doctotal'] : 0,
			':cnc_baseamnt' => is_numeric($Data['cnc_baseamnt']) ? $Data['cnc_baseamnt'] : 0,
			':cnc_taxtotal' => is_numeric($Data['cnc_taxtotal']) ? $Data['cnc_taxtotal'] : 0,
			':cnc_discprofit' => is_numeric($Data['cnc_discprofit']) ? $Data['cnc_discprofit'] : 0,
			':cnc_discount' => is_numeric($Data['cnc_discount']) ? $Data['cnc_discount'] : 0,
			':cnc_createat' => $this->validateDate($Data['cnc_createat']) ? $Data['cnc_createat'] : NULL,
			':cnc_baseentry' => is_numeric($Data['cnc_baseentry']) ? $Data['cnc_baseentry'] : 0,
			':cnc_basetype' => is_numeric($Data['cnc_basetype']) ? $Data['cnc_basetype'] : 0,
			':cnc_doctype' => is_numeric($Data['cnc_doctype']) ? $Data['cnc_doctype'] : 0,
			':cnc_idadd' => isset($Data['cnc_idadd']) ? $Data['cnc_idadd'] : NULL,
			':cnc_adress' => isset($Data['cnc_adress']) ? $Data['cnc_adress'] : NULL,
			':cnc_paytype' => is_numeric($Data['cnc_paytype']) ? $Data['cnc_paytype'] : 0,
			':cnc_internal_comments' => isset($Data['cnc_internal_comments']) ? $Data['cnc_internal_comments'] : NULL,
			':cnc_docentry' => $Data['cnc_docentry']
		));

		if (is_numeric($resUpdate) && $resUpdate == 1) {

			$this->pedeo->queryTable("DELETE FROM cnc1 WHERE nc1_docentry=:nc1_docentry", array(':nc1_docentry' => $Data['cnc_docentry']));

			foreach ($ContenidoDetalle as $key => $detail) {

				$sqlInsertDetail = "INSERT INTO cnc1(nc1_docentry, nc1_itemcode, nc1_itemname, nc1_quantity, nc1_uom, nc1_whscode,
																			nc1_price, nc1_vat, nc1_vatsum, nc1_discount, nc1_linetotal, nc1_costcode, nc1_ubusiness, nc1_project,
																			nc1_acctcode, nc1_basetype, nc1_doctype, nc1_avprice, nc1_inventory, nc1_acciva, nc1_ubication, nc1_codmunicipality)VALUES(:nc1_docentry, :nc1_itemcode, :nc1_itemname, :nc1_quantity,
																			:nc1_uom, :nc1_whscode,:nc1_price, :nc1_vat, :nc1_vatsum, :nc1_discount, :nc1_linetotal, :nc1_costcode, :nc1_ubusiness, :nc1_project,
																			:nc1_acctcode, :nc1_basetype, :nc1_doctype, :nc1_avprice, :nc1_inventory, :nc1_acciva, :nc1_ubication, :nc1_codmunicipality)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':nc1_docentry' => $Data['cnc_docentry'],
					':nc1_itemcode' => isset($detail['nc1_itemcode']) ? $detail['nc1_itemcode'] : NULL,
					':nc1_itemname' => isset($detail['nc1_itemname']) ? $detail['nc1_itemname'] : NULL,
					':nc1_quantity' => is_numeric($detail['nc1_quantity']) ? $detail['nc1_quantity'] : 0,
					':nc1_uom' => isset($detail['nc1_uom']) ? $detail['nc1_uom'] : NULL,
					':nc1_whscode' => isset($detail['nc1_whscode']) ? $detail['nc1_whscode'] : NULL,
					':nc1_price' => is_numeric($detail['nc1_price']) ? $detail['nc1_price'] : 0,
					':nc1_vat' => is_numeric($detail['nc1_vat']) ? $detail['nc1_vat'] : 0,
					':nc1_vatsum' => is_numeric($detail['nc1_vatsum']) ? $detail['nc1_vatsum'] : 0,
					':nc1_discount' => is_numeric($detail['nc1_discount']) ? $detail['nc1_discount'] : 0,
					':nc1_linetotal' => is_numeric($detail['nc1_linetotal']) ? $detail['nc1_linetotal'] : 0,
					':nc1_costcode' => isset($detail['nc1_costcode']) ? $detail['nc1_costcode'] : NULL,
					':nc1_ubusiness' => isset($detail['nc1_ubusiness']) ? $detail['nc1_ubusiness'] : NULL,
					':nc1_project' => isset($detail['nc1_project']) ? $detail['nc1_project'] : NULL,
					':nc1_acctcode' => is_numeric($detail['nc1_acctcode']) ? $detail['nc1_acctcode'] : 0,
					':nc1_basetype' => is_numeric($detail['nc1_basetype']) ? $detail['nc1_basetype'] : 0,
					':nc1_doctype' => is_numeric($detail['nc1_doctype']) ? $detail['nc1_doctype'] : 0,
					':nc1_avprice' => is_numeric($detail['nc1_avprice']) ? $detail['nc1_avprice'] : 0,
					':nc1_inventory' => is_numeric($detail['nc1_inventory']) ? $detail['nc1_inventory'] : NULL,
					':nc1_acciva' => is_numeric($detail['nc1_cuentaIva']) ? $detail['nc1_cuentaIva'] : 0,
					':nc1_ubication' => isset($detail['nc1_ubication']) ? $detail['nc1_ubication'] : NULL,
					':nc1_codmunicipality' => isset($detail['nc1_codmunicipality']) ? $detail['nc1_codmunicipality'] : NULL
				));

				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle  de nota credito se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertDetail,
						'mensaje'	=> 'No se pudo registrar la nota credito'
					);

					$this->response($respuesta);

					return;
				}
			}


			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resUpdate,
				'mensaje' => 'Nota credito actualizada con exito'
			);
		} else {

			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data'    => $resUpdate,
				'mensaje'	=> 'No se pudo actualizar la nota credito'
			);
		}

		$this->response($respuesta);
	}


	//OBTENER  de nota credito
	public function getPurchaseNc_get()
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
		$campos = ",CONCAT(T0.{prefix}_CURRENCY,' ',TRIM(TO_CHAR(t0.{prefix}_totalret,'999,999,999,999.00'))) {prefix}_totalret,
		CONCAT(T0.{prefix}_CURRENCY,' ',TRIM(TO_CHAR(t0.{prefix}_totalretiva,'999,999,999,999.00'))) {prefix}_totalretiva, T4.dms_phone1, T4.dms_phone2, T4.dms_cel";
		$sqlSelect = self::getColumn('dcnc', 'cnc', $campos, '', $DECI_MALES, $Data['business'], $Data['branch'],0,0,0,"",2);


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


	//OBTENER  de nota credito POR ID
	public function getPurchaseNcById_get()
	{

		$Data = $this->get();
		$DECI_MALES =  $this->generic->getDecimals();
		if (!isset($Data['cnc_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$campos = ",T4.dms_phone1, T4.dms_phone2, T4.dms_cel";

		$sqlSelect = self::getColumn('dcnc', 'cnc', $campos, '', $DECI_MALES, $Data['business'], $Data['branch'],0,0,0," AND cnc_docentry = :cnc_docentry");

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":cnc_docentry" => $Data['cnc_docentry']));

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


	//OBTENER  de nota credito DETALLE POR ID
	public function getPurchaseNcDetail_get()
	{

		$Data = $this->get();

		if (!isset($Data['nc1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT cnc1.*,dmar.dma_series_code
											 FROM cnc1
											 INNER JOIN dmar
											 ON cnc1.nc1_itemcode = dmar.dma_item_code
											 WHERE nc1_docentry =:nc1_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":nc1_docentry" => $Data['nc1_docentry']));

		if (isset($resSelect[0])) {
			foreach ($resSelect as $key => $value) {
				$sqlSelect2 = "SELECT fc.crt_typert,fc.crt_type,fc.crt_basert,fc.crt_profitrt,fc.crt_totalrt,fc.crt_base,fc.crt_linenum,dmar.dma_series_code
														FROM cnc1
														INNER JOIN dmar
														ON cnc1.nc1_itemcode = dmar.dma_item_code
														INNER JOIN fcrt fc
														ON cnc1.nc1_docentry = fc.crt_baseentry
														AND cnc1.nc1_linenum = fc.crt_linenum
														WHERE nc1_docentry = :nc1_docentry";

				$resSelect2 = $this->pedeo->queryTable($sqlSelect2, array(':nc1_docentry' => $Data['nc1_docentry']));

				if (isset($resSelect2[0])) {
					$resSelect[$key]['retenciones'] = $resSelect2;
				}
			}

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




	//OBTENER  DE VENTA POR ID SOCIO DE NEGOCIO
	public function getPurchaseNcBySN_get()
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

		$sqlSelect = " SELECT * FROM dcnc WHERE cnc_cardcode =:cnc_cardcode AND business = :business AND branch = :branch ";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":cnc_cardcode" => $Data['dms_card_code']));

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

	// OBTENER ENCABEZADO PARA EL DUPLICADO
	public function getDuplicateFrom_get() {

		$Data = $this->get();

		if (!isset($Data['dms_card_code'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$duplicateData = $this->documentduplicate->getDuplicate('dcnc','cnc',$Data['dms_card_code'],$Data['business']);


		if (isset($duplicateData[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $duplicateData,
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

	//OBTENER DETALLE PARA DUPLICADO
	public function getDuplicateDt_get()
	{

		$Data = $this->get();

		if (!isset($Data['nc1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

			$copy = $this->documentduplicate->getDuplicateDt($Data['nc1_docentry'],'dcnc','cnc1','cnc','nc1','deducible');

			if (isset($copy[0])) {

				$respuesta = array(
					'error' => false,
					'data'  => $copy,
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

	//ACTUALIZAR COMENTARIOS INTERNOS Y NORMAL
	public function updateComments_post ()
	{
		$Data = $this->post();

		$update = "UPDATE dcnc SET cnc_comment = :cnc_comment, cnc_internal_comments = :cnc_internal_comments WHERE cnc_docentry = :cnc_docentry";
		$resUpdate = $this->pedeo->updateRow($update,array(
			':cnc_comment' => $Data['cnc_comment'],
			':cnc_internal_comments' => $Data['cnc_internal_comments'],
			':cnc_docentry' => $Data['cnc_docentry']
		));

		if(is_numeric($resUpdate) && $resUpdate > 0){
			$respuesta = array(
				'error' => false,
				'data' => $resUpdate,
				'mensaje' => 'Comentarios actualizados correctamente.'
			);
		}else{
			$respuesta = array(
				'error' => false,
				'data' => $resUpdate,
				'mensaje' => 'Comentarios actualizados correctamente.'
			);
		}

		$this->response($respuesta);
	}
}