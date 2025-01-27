<?php
// NOTA DEBITO DE COMPRAS
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class PurchaseNd extends REST_Controller
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

	//CREAR NUEVA nota debito DE compras
	public function createPurchaseNd_post()
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
		$DetalleConsolidadoIngreso = []; // Array Final con los datos del asiento solo ingreso
		$DetalleConsolidadoCostoInventario = [];
		$DetalleConsolidadoCostoCosto = [];
		$DetalleConsolidadoIva = []; // Array Final con los datos del asiento segun el iva
		$DetalleConsolidadoImpMulti = [];
		$DetalleConsolidadoRetencion = [];
		$inArrayIngreso = array(); // Array para mantener el indice de las llaves para ingreso
		$inArrayIva = array(); // Array para mantener el indice de las llaves para iva
		$inArrayImpMulti = array();
		$inArrayCostoInventario = array();
		$inArrayCostoCosto = array();
		$inArrayRetencion = array();
		$llave = ""; // la comnbinacion entre la cuenta contable,proyecto, unidad de negocio y centro de costo
		$llaveIva = ""; //segun tipo de iva
		$llaveImpMulti = "";
		$llaveCostoInventario = "";
		$llaveCostoCosto = "";
		$llaveRetencion = "";
		$posicion = 0; // contiene la posicion con que se creara en el array DetalleConsolidado
		$posicionIva = 0;
		$posicionImpMulti = 0;
		$posicionCostoInventario = 0;
		$posicionCostoCosto = 0;
		$posicionRetencion = 0;
		$codigoCuenta = ""; //para saber la naturaleza
		$grantotalCostoInventario = 0;
		$DocNumVerificado = 0;
		$ManejaInvetario = 0;

		$TasaDocLoc = 0; // MANTIENE EL VALOR DE LA TASA DE CONVERSION ENTRE LA MONEDA LOCAL Y LA MONEDA DEL DOCUMENTO
		$TasaLocSys = 0; // MANTIENE EL VALOR DE LA TASA DE CONVERSION ENTRE LA MONEDA LOCAL Y LA MONEDA DEL SISTEMA
		$MONEDALOCAL = '';

		// Se globaliza la variable sqlDetalleAsiento
		$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
												ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
												ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
												ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, ac1_line, ac1_base_tax, ac1_codret,business,branch)VALUES (:ac1_trans_id, :ac1_account,
												:ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
												:ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
												:ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
												:ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref,:ac1_line, :ac1_base_tax, :ac1_codret,:business,:branch)";


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
				'mensaje' => 'No se encontro el detalle de la nota debito de compras'
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
		$periodo = $this->generic->ValidatePeriod($Data['cnd_duedev'], $Data['cnd_docdate'], $Data['cnd_duedate'], 0);

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
		$DocNumVerificado = $this->documentnumbering->NumberDoc($Data['cnd_series'],$Data['cnd_docdate'],$Data['cnd_duedate']);
		
		if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0){

		}else if ($DocNumVerificado['error']){

			return $this->response($DocNumVerificado, REST_Controller::HTTP_BAD_REQUEST);
		}
		
		//PROCESO DE TASA
		$dataTasa = $this->tasa->Tasa($Data['cnd_currency'],$Data['cnd_docdate']);

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
		if($Data['cnd_duedate'] < $Data['cnd_docdate']){
			$respuesta = array(
				'error' => true,
				'data' => [],
				'mensaje' => 'La fecha de vencimiento ('.$Data['cnd_duedate'].') no puede ser inferior a la fecha del documento ('.$Data['cnd_docdate'].')'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}
		$sqlInsert = "INSERT INTO dcnd(cnd_series, cnd_docnum, cnd_docdate, cnd_duedate, cnd_duedev, cnd_pricelist, cnd_cardcode,
                      cnd_cardname, cnd_currency, cnd_contacid, cnd_slpcode, cnd_empid, cnd_comment, cnd_doctotal, cnd_baseamnt, cnd_taxtotal,
                      cnd_discprofit, cnd_discount, cnd_createat, cnd_baseentry, cnd_basetype, cnd_doctype, cnd_idadd, cnd_adress, cnd_paytype,
                      cnd_createby, business, branch, cnd_internal_comments,cnd_taxtotal_ad)VALUES(:cnd_series, :cnd_docnum, :cnd_docdate, :cnd_duedate, :cnd_duedev, :cnd_pricelist, :cnd_cardcode, :cnd_cardname,
                      :cnd_currency, :cnd_contacid, :cnd_slpcode, :cnd_empid, :cnd_comment, :cnd_doctotal, :cnd_baseamnt, :cnd_taxtotal, :cnd_discprofit, :cnd_discount,
                      :cnd_createat, :cnd_baseentry, :cnd_basetype, :cnd_doctype, :cnd_idadd, :cnd_adress, :cnd_paytype, :cnd_createby, :business, :branch,
					  :cnd_internal_comments,:cnd_taxtotal_ad)";


		// Se Inicia la transaccion,
		// Todas las consultas de modificacion siguientes
		// aplicaran solo despues que se confirme la transaccion,
		// de lo contrario no se aplicaran los cambios y se devolvera
		// la base de datos a su estado original.

		$this->pedeo->trans_begin();

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			':cnd_docnum' => $DocNumVerificado,
			':cnd_series' => is_numeric($Data['cnd_series']) ? $Data['cnd_series'] : 0,
			':cnd_docdate' => $this->validateDate($Data['cnd_docdate']) ? $Data['cnd_docdate'] : NULL,
			':cnd_duedate' => $this->validateDate($Data['cnd_duedate']) ? $Data['cnd_duedate'] : NULL,
			':cnd_duedev' => $this->validateDate($Data['cnd_duedev']) ? $Data['cnd_duedev'] : NULL,
			':cnd_pricelist' => is_numeric($Data['cnd_pricelist']) ? $Data['cnd_pricelist'] : 0,
			':cnd_cardcode' => isset($Data['cnd_cardcode']) ? $Data['cnd_cardcode'] : NULL,
			':cnd_cardname' => isset($Data['cnd_cardname']) ? $Data['cnd_cardname'] : NULL,
			':cnd_currency' => isset($Data['cnd_currency']) ? $Data['cnd_currency'] : NULL,
			':cnd_contacid' => isset($Data['cnd_contacid']) ? $Data['cnd_contacid'] : NULL,
			':cnd_slpcode' => is_numeric($Data['cnd_slpcode']) ? $Data['cnd_slpcode'] : 0,
			':cnd_empid' => is_numeric($Data['cnd_empid']) ? $Data['cnd_empid'] : 0,
			':cnd_comment' => isset($Data['cnd_comment']) ? $Data['cnd_comment'] : NULL,
			':cnd_doctotal' => is_numeric($Data['cnd_doctotal']) ? $Data['cnd_doctotal'] : 0,
			':cnd_baseamnt' => is_numeric($Data['cnd_baseamnt']) ? $Data['cnd_baseamnt'] : 0,
			':cnd_taxtotal' => is_numeric($Data['cnd_taxtotal']) ? $Data['cnd_taxtotal'] : 0,
			':cnd_discprofit' => is_numeric($Data['cnd_discprofit']) ? $Data['cnd_discprofit'] : 0,
			':cnd_discount' => is_numeric($Data['cnd_discount']) ? $Data['cnd_discount'] : 0,
			':cnd_createat' => $this->validateDate($Data['cnd_createat']) ? $Data['cnd_createat'] : NULL,
			':cnd_baseentry' => is_numeric($Data['cnd_baseentry']) ? $Data['cnd_baseentry'] : 0,
			':cnd_basetype' => is_numeric($Data['cnd_basetype']) ? $Data['cnd_basetype'] : 0,
			':cnd_doctype' => is_numeric($Data['cnd_doctype']) ? $Data['cnd_doctype'] : 0,
			':cnd_idadd' => isset($Data['cnd_idadd']) ? $Data['cnd_idadd'] : NULL,
			':cnd_adress' => isset($Data['cnd_adress']) ? $Data['cnd_adress'] : NULL,
			':cnd_paytype' => is_numeric($Data['cnd_paytype']) ? $Data['cnd_paytype'] : 0,
			':cnd_createby' => isset($Data['cnd_createby']) ? $Data['cnd_createby'] : NULL,
			':business' => $Data['business'],
			':branch' => $Data['branch'],
			':cnd_internal_comments' => isset($Data['cnd_internal_comments']) ? $Data['cnd_internal_comments'] : NULL,

			':cnd_taxtotal_ad' => is_numeric($Data['cnd_taxtotal_ad']) ? $Data['cnd_taxtotal_ad'] : 0
		));

		if (is_numeric($resInsert) && $resInsert > 0) {

			// Se actualiza la serie de la numeracion del documento

			$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																					 WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id'      => $Data['cnd_series']
			));


			if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
			} else {
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'    => $resActualizarNumeracion,
					'mensaje'	=> 'No se pudo crear la nota debito de compras'
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
				':bed_doctype' => $Data['cnd_doctype'],
				':bed_status' => 1, //ESTADO ABIERTO
				':bed_createby' => $Data['cnd_createby'],
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
					'mensaje'	=> 'No se pudo registrar la nota debito de compras 1'
				);


				$this->response($respuesta);

				return;
			}

			//FIN PROCESO ESTADO DEL DOCUMENTO


			//Se agregan los asientos contables*/*******

			$sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user,business,:branch)
					 VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user,:business,:branch)";


			$resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

				':mac_doc_num' => 1,
				':mac_status' => 1,
				':mac_base_type' => is_numeric($Data['cnd_doctype']) ? $Data['cnd_doctype'] : 0,
				':mac_base_entry' => $resInsert,
				':mac_doc_date' => $this->validateDate($Data['cnd_docdate']) ? $Data['cnd_docdate'] : NULL,
				':mac_doc_duedate' => $this->validateDate($Data['cnd_duedate']) ? $Data['cnd_duedate'] : NULL,
				':mac_legal_date' => $this->validateDate($Data['cnd_docdate']) ? $Data['cnd_docdate'] : NULL,
				':mac_ref1' => is_numeric($Data['cnd_doctype']) ? $Data['cnd_doctype'] : 0,
				':mac_ref2' => "",
				':mac_ref3' => "",
				':mac_loc_total' => is_numeric($Data['cnd_doctotal']) ? $Data['cnd_doctotal'] : 0,
				':mac_fc_total' => is_numeric($Data['cnd_doctotal']) ? $Data['cnd_doctotal'] : 0,
				':mac_sys_total' => is_numeric($Data['cnd_doctotal']) ? $Data['cnd_doctotal'] : 0,
				':mac_trans_dode' => 1,
				':mac_beline_nume' => 1,
				':mac_vat_date' => $this->validateDate($Data['cnd_docdate']) ? $Data['cnd_docdate'] : NULL,
				':mac_serie' => 1,
				':mac_number' => 1,
				':mac_bammntsys' => is_numeric($Data['cnd_baseamnt']) ? $Data['cnd_baseamnt'] : 0,
				':mac_bammnt' => is_numeric($Data['cnd_baseamnt']) ? $Data['cnd_baseamnt'] : 0,
				':mac_wtsum' => 1,
				':mac_vatsum' => is_numeric($Data['cnd_taxtotal']) ? $Data['cnd_taxtotal'] : 0,
				':mac_comments' => isset($Data['cnd_comment']) ? $Data['cnd_comment'] : NULL,
				':mac_create_date' => $this->validateDate($Data['cnd_createat']) ? $Data['cnd_createat'] : NULL,
				':mac_made_usuer' => isset($Data['cnd_createby']) ? $Data['cnd_createby'] : NULL,
				':mac_update_date' => date("Y-m-d"),
				':mac_update_user' => isset($Data['cnd_createby']) ? $Data['cnd_createby'] : NULL,
				':business' => $Data['business'],
				':branch' => $Data['branch']
			));


			if (is_numeric($resInsertAsiento) && $resInsertAsiento > 0) {
				// Se verifica que el detalle no de error insertando //
			} else {

				// si falla algun insert del detalle de la nota debito de compras se devuelven los cambios realizados por la transaccion,
				// se retorna el error y se detiene la ejecucion del codigo restante.
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'	  => $resInsertAsiento,
					'mensaje'	=> 'No se pudo registrar la nota debito de compras 2'
				);

				$this->response($respuesta);

				return;
			}

			//SE APLICA PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS
			if (isset($Data['cnd_baseentry']) && is_numeric($Data['cnd_baseentry']) && isset($Data['cnd_basetype']) && is_numeric($Data['cnd_basetype'])) {

				$sqlDocInicio = "SELECT bmd_tdi, bmd_ndi FROM tbmd WHERE  bmd_doctype = :bmd_doctype AND bmd_docentry = :bmd_docentry";
				$resDocInicio = $this->pedeo->queryTable($sqlDocInicio, array(
					':bmd_doctype' => $Data['cnd_basetype'],
					':bmd_docentry' => $Data['cnd_baseentry']
				));


				if (isset($resDocInicio[0])) {

					$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
																	bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype)
																	VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
																	:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype)";

					$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

						':bmd_doctype' => is_numeric($Data['cnd_doctype']) ? $Data['cnd_doctype'] : 0,
						':bmd_docentry' => $resInsert,
						':bmd_createat' => $this->validateDate($Data['cnd_createat']) ? $Data['cnd_createat'] : NULL,
						':bmd_doctypeo' => is_numeric($Data['cnd_basetype']) ? $Data['cnd_basetype'] : 0, //ORIGEN
						':bmd_docentryo' => is_numeric($Data['cnd_baseentry']) ? $Data['cnd_baseentry'] : 0,  //ORIGEN
						':bmd_tdi' => $resDocInicio[0]['bmd_tdi'], // DOCUMENTO INICIAL
						':bmd_ndi' => $resDocInicio[0]['bmd_ndi'], // DOCUMENTO INICIAL
						':bmd_docnum' => $DocNumVerificado,
						':bmd_doctotal' => is_numeric($Data['cnd_doctotal']) ? $Data['cnd_doctotal'] : 0,
						':bmd_cardcode' => isset($Data['cnd_cardcode']) ? $Data['cnd_cardcode'] : NULL,
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

						':bmd_doctype' => is_numeric($Data['cnd_doctype']) ? $Data['cnd_doctype'] : 0,
						':bmd_docentry' => $resInsert,
						':bmd_createat' => $this->validateDate($Data['cnd_createat']) ? $Data['cnd_createat'] : NULL,
						':bmd_doctypeo' => is_numeric($Data['cnd_basetype']) ? $Data['cnd_basetype'] : 0, //ORIGEN
						':bmd_docentryo' => is_numeric($Data['cnd_baseentry']) ? $Data['cnd_baseentry'] : 0,  //ORIGEN
						':bmd_tdi' => is_numeric($Data['cnd_doctype']) ? $Data['cnd_doctype'] : 0, // DOCUMENTO INICIAL
						':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
						':bmd_docnum' => $DocNumVerificado,
						':bmd_doctotal' => is_numeric($Data['cnd_doctotal']) ? $Data['cnd_doctotal'] : 0,
						':bmd_cardcode' => isset($Data['cnd_cardcode']) ? $Data['cnd_cardcode'] : NULL,
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

					':bmd_doctype' => is_numeric($Data['cnd_doctype']) ? $Data['cnd_doctype'] : 0,
					':bmd_docentry' => $resInsert,
					':bmd_createat' => $this->validateDate($Data['cnd_createat']) ? $Data['cnd_createat'] : NULL,
					':bmd_doctypeo' => is_numeric($Data['cnd_basetype']) ? $Data['cnd_basetype'] : 0, //ORIGEN
					':bmd_docentryo' => is_numeric($Data['cnd_baseentry']) ? $Data['cnd_baseentry'] : 0,  //ORIGEN
					':bmd_tdi' => is_numeric($Data['cnd_doctype']) ? $Data['cnd_doctype'] : 0, // DOCUMENTO INICIAL
					':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
					':bmd_docnum' => $DocNumVerificado,
					':bmd_doctotal' => is_numeric($Data['cnd_doctotal']) ? $Data['cnd_doctotal'] : 0,
					':bmd_cardcode' => isset($Data['cnd_cardcode']) ? $Data['cnd_cardcode'] : NULL,
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

				$sqlInsertDetail = "INSERT INTO cnd1(nd1_docentry, nd1_linenum,nd1_itemcode, nd1_itemname, nd1_quantity, nd1_uom, nd1_whscode,
		                                    nd1_price, nd1_vat, nd1_vatsum, nd1_discount, nd1_linetotal, nd1_costcode, nd1_ubusiness, nd1_project,
		                                    nd1_acctcode, nd1_basetype, nd1_doctype, nd1_avprice, nd1_inventory, nd1_acciva, nd1_codimp, nd1_ubication,
											nd1_baseline,ote_code,nd1_tax_base,deducible,nd1_vat_ad,nd1_vatsum_ad,nd1_accimp_ad,nd1_codimp_ad, nd1_codmunicipality)
											VALUES(:nd1_docentry, :nd1_linenum,:nd1_itemcode, :nd1_itemname, :nd1_quantity,
		                                    :nd1_uom, :nd1_whscode,:nd1_price, :nd1_vat, :nd1_vatsum, :nd1_discount, :nd1_linetotal, :nd1_costcode, :nd1_ubusiness, :nd1_project,
		                                    :nd1_acctcode, :nd1_basetype, :nd1_doctype, :nd1_avprice, :nd1_inventory, :nd1_acciva, :nd1_codimp, 
											:nd1_ubication,:nd1_baseline,:ote_code,:nd1_tax_base,:deducible,:nd1_vat_ad,:nd1_vatsum_ad,:nd1_accimp_ad,:nd1_codimp_ad, :nd1_codmunicipality)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':nd1_docentry' => $resInsert,
					':nd1_linenum' => is_numeric($detail['nd1_linenum']) ? $detail['nd1_linenum'] : 0,
					':nd1_itemcode' => isset($detail['nd1_itemcode']) ? $detail['nd1_itemcode'] : NULL,
					':nd1_itemname' => isset($detail['nd1_itemname']) ? $detail['nd1_itemname'] : NULL,
					':nd1_quantity' => is_numeric($detail['nd1_quantity']) ? $detail['nd1_quantity'] : 0,
					':nd1_uom' => isset($detail['nd1_uom']) ? $detail['nd1_uom'] : NULL,
					':nd1_whscode' => isset($detail['nd1_whscode']) ? $detail['nd1_whscode'] : NULL,
					':nd1_price' => is_numeric($detail['nd1_price']) ? $detail['nd1_price'] : 0,
					':nd1_vat' => is_numeric($detail['nd1_vat']) ? $detail['nd1_vat'] : 0,
					':nd1_vatsum' => is_numeric($detail['nd1_vatsum']) ? $detail['nd1_vatsum'] : 0,
					':nd1_discount' => is_numeric($detail['nd1_discount']) ? $detail['nd1_discount'] : 0,
					':nd1_linetotal' => is_numeric($detail['nd1_linetotal']) ? $detail['nd1_linetotal'] : 0,
					':nd1_costcode' => isset($detail['nd1_costcode']) ? $detail['nd1_costcode'] : NULL,
					':nd1_ubusiness' => isset($detail['nd1_ubusiness']) ? $detail['nd1_ubusiness'] : NULL,
					':nd1_project' => isset($detail['nd1_project']) ? $detail['nd1_project'] : NULL,
					':nd1_acctcode' => is_numeric($detail['nd1_acctcode']) ? $detail['nd1_acctcode'] : 0,
					':nd1_basetype' => is_numeric($detail['nd1_basetype']) ? $detail['nd1_basetype'] : 0,
					':nd1_doctype' => is_numeric($detail['nd1_doctype']) ? $detail['nd1_doctype'] : 0,
					':nd1_avprice' => is_numeric($detail['nd1_avprice']) ? $detail['nd1_avprice'] : 0,
					':nd1_inventory' => is_numeric($detail['nd1_inventory']) ? $detail['nd1_inventory'] : NULL,
					':nd1_acciva'  => is_numeric($detail['nd1_cuentaIva']) ? $detail['nd1_cuentaIva'] : 0,
					':nd1_codimp'  => isset($detail['nd1_codimp']) ? $detail['nd1_codimp'] : NULL,
					':nd1_ubication'  => isset($detail['nd1_ubication']) ? $detail['nd1_ubication'] : NULL,
					':nd1_baseline' => is_numeric($detail['nd1_baseline']) ? $detail['nd1_baseline'] : 0,
					':ote_code'  => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
					':nd1_tax_base' => is_numeric($detail['nd1_tax_base']) ? $detail['nd1_tax_base'] : 0,
					':deducible' => isset($detail['deducible']) ? $detail['deducible'] : NULL,

					':nd1_vat_ad' => is_numeric($detail['nd1_vat_ad']) ? $detail['nd1_vat_ad'] : 0,
					':nd1_vatsum_ad' => is_numeric($detail['nd1_vatsum_ad']) ? $detail['nd1_vatsum_ad'] : 0,
					':nd1_accimp_ad'  => is_numeric($detail['nd1_accimp_ad']) ? $detail['nd1_accimp_ad'] : 0,
					':nd1_codimp_ad'  => isset($detail['nd1_codimp_ad']) ? $detail['nd1_codimp_ad'] : NULL,
					':nd1_codmunicipality' => isset($detail['nd1_codmunicipality']) ? $detail['nd1_codmunicipality'] : NULL
				));

				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle de la nota debito de compras se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertDetail,
						'mensaje'	=> 'No se pudo registrar la nota debito de compras 3'
					);

					$this->response($respuesta);

					return;
				}

				// PROCESO PARA INSERTAR RETENCIONES
				if (isset($detail['detail'])) {



					$ContenidoRentencion = $detail['detail'];

					if (is_array($ContenidoRentencion)) {
						if (intval(count($ContenidoRentencion)) > 0) {
							$TotalAcuRentencion = 0;
							foreach ($ContenidoRentencion as $key => $value) {

								$DetalleRetencion = new stdClass();

								$sqlInsertRetenciones = "INSERT INTO fcrt(crt_baseentry, crt_basetype, crt_typert, crt_basert, crt_profitrt, crt_totalrt, crt_base, crt_type, crt_linenum, crt_codret)
												 		VALUES (:crt_baseentry, :crt_basetype, :crt_typert, :crt_basert, :crt_profitrt, :crt_totalrt, :crt_base, :crt_type, :crt_linenum, :crt_codret)";
								$resInsertRetenciones = $this->pedeo->insertRow($sqlInsertRetenciones, array(

									':crt_baseentry' => $resInsert,
									':crt_basetype'  => $Data['cnd_doctype'],
									':crt_typert'    => $value['crt_typert'],
									':crt_basert'    => $value['crt_basert'],
									':crt_profitrt'  => $value['crt_profitrt'],
									':crt_totalrt'   => $value['crt_totalrt'],
									':crt_linenum'   => $detail['fc1_linenum'],
									':crt_codret'	   => $value['crt_typert']
								));


								if (is_numeric($resInsertRetenciones) && $resInsertRetenciones > 0) {

									$TotalAcuRentencion = $TotalAcuRentencion + $value['crt_totalrt'];

									$DetalleRetencion->crt_typert   = $value['crt_typert'];
									$DetalleRetencion->crt_basert   = $value['crt_totalrt'];
									$DetalleRetencion->crt_profitrt = $value['crt_profitrt'];
									$DetalleRetencion->crt_totalrt  = $value['crt_totalrt'];
									$DetalleRetencion->crt_codret   = $value['crt_typert'];
									$DetalleRetencion->crt_baseln 	= $value['crt_basert'];


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
										'mensaje'	=> 'No se pudo registrar la factura de compras, fallo el proceso para insertar las retenciones'
									);
									$this->response($respuesta);
									return;
								}
							}
						}
					}
				}

				// FIN PROCESO PARA INSERTAR RETENCIONES


				// SE VERIFICA SI EL ARTICULO ESTA MARCADO PARA MANEJARSE EN INVENTARIO
				$sqlItemINV = "SELECT dma_item_inv FROM dmar WHERE dma_item_code = :dma_item_code AND dma_item_inv = :dma_item_inv";
				$resItemINV = $this->pedeo->queryTable($sqlItemINV, array(

					':dma_item_code' => $detail['nd1_itemcode'],
					':dma_item_inv'  => 1
				));

				if (isset($resItemINV[0])) {

					$ManejaInvetario = 1;
				} else {
					$ManejaInvetario = 0;
				}

				// FIN PROCESO ITEM MANEJA INVENTARIO

				// si el item es inventariable
				if ($ManejaInvetario == 1) {

					//LLENANDO DETALLE ASIENTO CONTABLES
					$DetalleAsientoIngreso = new stdClass();
					$DetalleAsientoIva = new stdClass();
					$DetalleCostoInventario = new stdClass();
					$DetalleCostoCosto = new stdClass();
					$DetalleAsientoImpMulti = new stdClass();


					$DetalleAsientoIngreso->ac1_account = is_numeric($detail['nd1_acctcode']) ? $detail['nd1_acctcode'] : 0;
					$DetalleAsientoIngreso->ac1_prc_code = isset($detail['nd1_costcode']) ? $detail['nd1_costcode'] : NULL;
					$DetalleAsientoIngreso->ac1_uncode = isset($detail['nd1_ubusiness']) ? $detail['nd1_ubusiness'] : NULL;
					$DetalleAsientoIngreso->ac1_prj_code = isset($detail['nd1_project']) ? $detail['nd1_project'] : NULL;
					$DetalleAsientoIngreso->nd1_linetotal = is_numeric($detail['nd1_linetotal']) ? $detail['nd1_linetotal'] : 0;
					$DetalleAsientoIngreso->nd1_vat = is_numeric($detail['nd1_vat']) ? $detail['nd1_vat'] : 0;
					$DetalleAsientoIngreso->nd1_vatsum = is_numeric($detail['nd1_vatsum']) ? $detail['nd1_vatsum'] : 0;
					$DetalleAsientoIngreso->nd1_price = is_numeric($detail['nd1_price']) ? $detail['nd1_price'] : 0;
					$DetalleAsientoIngreso->nd1_itemcode = isset($detail['nd1_itemcode']) ? $detail['nd1_itemcode'] : NULL;
					$DetalleAsientoIngreso->nd1_quantity = is_numeric($detail['nd1_quantity']) ? $detail['nd1_quantity'] : 0;
					$DetalleAsientoIngreso->em1_whscode = isset($detail['nd1_whscode']) ? $detail['nd1_whscode'] : NULL;



					$DetalleAsientoIva->ac1_account = is_numeric($detail['nd1_acctcode']) ? $detail['nd1_acctcode'] : 0;
					$DetalleAsientoIva->ac1_prc_code = isset($detail['nd1_costcode']) ? $detail['nd1_costcode'] : NULL;
					$DetalleAsientoIva->ac1_uncode = isset($detail['nd1_ubusiness']) ? $detail['nd1_ubusiness'] : NULL;
					$DetalleAsientoIva->ac1_prj_code = isset($detail['nd1_project']) ? $detail['nd1_project'] : NULL;
					$DetalleAsientoIva->nd1_linetotal = is_numeric($detail['nd1_linetotal']) ? $detail['nd1_linetotal'] : 0;
					$DetalleAsientoIva->nd1_vat = is_numeric($detail['nd1_vat']) ? $detail['nd1_vat'] : 0;
					$DetalleAsientoIva->nd1_vatsum = is_numeric($detail['nd1_vatsum']) ? $detail['nd1_vatsum'] : 0;
					$DetalleAsientoIva->nd1_price = is_numeric($detail['nd1_price']) ? $detail['nd1_price'] : 0;
					$DetalleAsientoIva->nd1_itemcode = isset($detail['nd1_itemcode']) ? $detail['nd1_itemcode'] : NULL;
					$DetalleAsientoIva->nd1_quantity = is_numeric($detail['nd1_quantity']) ? $detail['nd1_quantity'] : 0;
					$DetalleAsientoIva->nd1_cuentaIva = is_numeric($detail['nd1_cuentaIva']) ? $detail['nd1_cuentaIva'] : NULL;
					$DetalleAsientoIva->em1_whscode = isset($detail['nd1_whscode']) ? $detail['nd1_whscode'] : NULL;
					$DetalleAsientoIva->codimp = isset($detail['fc1_codimp']) ? $detail['fc1_codimp'] : NULL;


					// IMPUESTO MULTIPLE
					if ( is_numeric($detail['nd1_vatsum_ad']) && $detail['nd1_vatsum_ad'] > 0 ) {
						$DetalleAsientoIva->ac1_account = is_numeric($detail['nd1_accimp_ad']) ? $detail['nd1_accimp_ad'] : 0;
						$DetalleAsientoIva->ac1_prc_code = isset($detail['nd1_costcode']) ? $detail['nd1_costcode'] : NULL;
						$DetalleAsientoIva->ac1_uncode = isset($detail['nd1_ubusiness']) ? $detail['nd1_ubusiness'] : NULL;
						$DetalleAsientoIva->ac1_prj_code = isset($detail['nd1_project']) ? $detail['nd1_project'] : NULL;
						$DetalleAsientoIva->nd1_linetotal = is_numeric($detail['nd1_linetotal']) ? $detail['nd1_linetotal'] : 0;
						$DetalleAsientoIva->nd1_vat = is_numeric($detail['nd1_vat_ad']) ? $detail['nd1_vat_ad'] : 0;
						$DetalleAsientoIva->nd1_vatsum = is_numeric($detail['nd1_vatsum_ad']) ? $detail['nd1_vatsum_ad'] : 0;
						$DetalleAsientoIva->nd1_price = is_numeric($detail['nd1_price']) ? $detail['nd1_price'] : 0;
						$DetalleAsientoIva->nd1_itemcode = isset($detail['nd1_itemcode']) ? $detail['nd1_itemcode'] : NULL;
						$DetalleAsientoIva->nd1_quantity = is_numeric($detail['nd1_quantity']) ? $detail['nd1_quantity'] : 0;
						$DetalleAsientoIva->nd1_cuentaIva = is_numeric($detail['nd1_accimp_ad']) ? $detail['nd1_accimp_ad'] : NULL;
						$DetalleAsientoIva->em1_whscode = isset($detail['nd1_whscode']) ? $detail['nd1_whscode'] : NULL;
						$DetalleAsientoIva->codimp = isset($detail['nd1_codimp_ad']) ? $detail['nd1_codimp_ad'] : NULL;
	
					}



					// se busca la cuenta contable del costoInventario y costoCosto
					// $sqlArticulo = "SELECT f2.dma_item_code,  f1.mga_acct_inv, f1.mga_acct_cost FROM dmga f1 JOIN dmar f2 ON f1.mga_id  = f2.dma_group_code WHERE dma_item_code = :dma_item_code";
					//
					// $resArticulo = $this->pedeo->queryTable($sqlArticulo, array(":dma_item_code" => $detail['nd1_itemcode']));
					//
					// if(!isset($resArticulo[0])){
					//
					// 			$this->pedeo->trans_rollback();
					//
					// 			$respuesta = array(
					// 				'error'   => true,
					// 				'data' => $resArticulo,
					// 				'mensaje'	=> 'No se pudo registrar la nota debito de compras 4'
					// 			);
					//
					// 			 $this->response($respuesta);
					//
					// 			 return;
					// }


					$DetalleCostoInventario->ac1_account = is_numeric($detail['nd1_acctcode']) ? $detail['nd1_acctcode'] : 0;
					$DetalleCostoInventario->ac1_prc_code = isset($detail['nd1_costcode']) ? $detail['nd1_costcode'] : NULL;
					$DetalleCostoInventario->ac1_uncode = isset($detail['nd1_ubusiness']) ? $detail['nd1_ubusiness'] : NULL;
					$DetalleCostoInventario->ac1_prj_code = isset($detail['nd1_project']) ? $detail['nd1_project'] : NULL;
					$DetalleCostoInventario->nd1_linetotal = is_numeric($detail['nd1_linetotal']) ? $detail['nd1_linetotal'] : 0;
					$DetalleCostoInventario->nd1_vat = is_numeric($detail['nd1_vat']) ? $detail['nd1_vat'] : 0;
					$DetalleCostoInventario->nd1_vatsum = is_numeric($detail['nd1_vatsum']) ? $detail['nd1_vatsum'] : 0;
					$DetalleCostoInventario->nd1_price = is_numeric($detail['nd1_price']) ? $detail['nd1_price'] : 0;
					$DetalleCostoInventario->nd1_itemcode = isset($detail['nd1_itemcode']) ? $detail['nd1_itemcode'] : NULL;
					$DetalleCostoInventario->nd1_quantity = is_numeric($detail['nd1_quantity']) ? $detail['nd1_quantity'] : 0;
					$DetalleCostoInventario->em1_whscode = isset($detail['nd1_whscode']) ? $detail['nd1_whscode'] : NULL;
					$DetalleCostoInventario->ac1_inventory = $ManejaInvetario;


					$DetalleCostoCosto->ac1_account = is_numeric($detail['nd1_acctcode']) ? $detail['nd1_acctcode'] : 0;
					$DetalleCostoCosto->ac1_prc_code = isset($detail['nd1_costcode']) ? $detail['nd1_costcode'] : NULL;
					$DetalleCostoCosto->ac1_uncode = isset($detail['nd1_ubusiness']) ? $detail['nd1_ubusiness'] : NULL;
					$DetalleCostoCosto->ac1_prj_code = isset($detail['nd1_project']) ? $detail['nd1_project'] : NULL;
					$DetalleCostoCosto->nd1_linetotal = is_numeric($detail['nd1_linetotal']) ? $detail['nd1_linetotal'] : 0;
					$DetalleCostoCosto->nd1_vat = is_numeric($detail['nd1_vat']) ? $detail['nd1_vat'] : 0;
					$DetalleCostoCosto->nd1_vatsum = is_numeric($detail['nd1_vatsum']) ? $detail['nd1_vatsum'] : 0;
					$DetalleCostoCosto->nd1_price = is_numeric($detail['nd1_price']) ? $detail['nd1_price'] : 0;
					$DetalleCostoCosto->nd1_itemcode = isset($detail['nd1_itemcode']) ? $detail['nd1_itemcode'] : NULL;
					$DetalleCostoCosto->nd1_quantity = is_numeric($detail['nd1_quantity']) ? $detail['nd1_quantity'] : 0;
					$DetalleCostoCosto->em1_whscode = isset($detail['nd1_whscode']) ? $detail['nd1_whscode'] : NULL;
					$DetalleCostoCosto->ac1_inventory = $ManejaInvetario;

					$codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);

					$DetalleAsientoIngreso->codigoCuenta = $codigoCuenta;
					$DetalleAsientoIva->codigoCuenta = $codigoCuenta;
					$DetalleCostoInventario->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);
					$DetalleCostoInventario->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);


					$llave = $DetalleAsientoIngreso->ac1_uncode . $DetalleAsientoIngreso->ac1_prc_code . $DetalleAsientoIngreso->ac1_prj_code . $DetalleAsientoIngreso->ac1_account;
					$llaveIva = $DetalleAsientoIva->nd1_cuentaIva;
					$llaveCostoInventario = $DetalleCostoInventario->ac1_account;
					$llaveCostoCosto = $DetalleCostoCosto->ac1_account;

					// IMPUESTO MULTIPLE
					if ( is_numeric($detail['nd1_vatsum_ad']) && $detail['nd1_vatsum_ad'] > 0 ) {

						$llaveImpMulti = $DetalleAsientoImpMulti->nd1_cuentaIva;
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


					// IMPUESTO MULTIPLE
					if ( is_numeric($detail['nd1_vatsum_ad']) && $detail['nd1_vatsum_ad'] > 0 ) {

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

			//Procedimiento para llenar Impuestos
			$granTotalIva = 0;

			foreach ($DetalleConsolidadoIva as $key => $posicion) {
				$granTotalIva = 0;
				$granTotalIvaOriginal = 0;
				$CodigoImp = 0;
				$LineTotal = 0;
				$Vat = 0;

				foreach ($posicion as $key => $value) {
					$granTotalIva = $granTotalIva + $value->nd1_vatsum;
					$Vat = $value->fc1_vat;
					// if( $Vat > 0 ){
					$LineTotal = ($LineTotal + $value->fc1_linetotal);
					// }
					$CodigoImp = $value->codimp;
				}

				$granTotalIvaOriginal = $granTotalIva;

				if (trim($Data['cnd_currency']) != $MONEDALOCAL) {

					$granTotalIva = ($granTotalIva * $TasaDocLoc);
					$LineTotal = ($LineTotal * $TasaDocLoc);
				}

				if (trim($Data['cnd_currency']) != $MONEDASYS) {

					$MontoSysDB = ($granTotalIva / $TasaLocSys);
				} else {
					$MontoSysDB = $granTotalIvaOriginal;
				}

				// SE AGREGA AL BALANCE
				$BALANCE = $this->account->addBalance($periodo['data'], round($granTotalIva, $DECI_MALES), $value->nd1_cuentaIva, 1, $Data['cnd_docdate'], $Data['business'], $Data['branch']);
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
					':ac1_account' => $value->nd1_cuentaIva,
					':ac1_debit' => round($granTotalIva, $DECI_MALES),
					':ac1_credit' => 0,
					':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
					':ac1_credit_sys' => 0,
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['cnd_docdate']) ? $Data['cnd_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['cnd_duedate']) ? $Data['cnd_duedate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => is_numeric($Data['cnd_doctype']) ? $Data['cnd_doctype'] : 0,
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
					':ac1_made_user' => isset($Data['cnd_createby']) ? $Data['cnd_createby'] : NULL,
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
					':ac1_legal_num' => isset($Data['cnd_cardcode']) ? $Data['cnd_cardcode'] : NULL,
					':ac1_codref' => 1,
					':ac1_base_tax' => round($LineTotal, $DECI_MALES),
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
						'mensaje'	=> 'No se pudo registrar la nota debito de compras 5'
					);

					$this->response($respuesta);

					return;
				}
			}
			//FIN Procedimiento para llenar Impuestos


			//Procedimiento para llenar Impuestos multiple
			$granTotalIva = 0;

			foreach ($DetalleConsolidadoImpMulti as $key => $posicion) {
				$granTotalIva = 0;
				$granTotalIvaOriginal = 0;
				$CodigoImp = 0;
				$LineTotal = 0;
				$Vat = 0;

				foreach ($posicion as $key => $value) {
					$granTotalIva = $granTotalIva + $value->nd1_vatsum;
					$Vat = $value->fc1_vat;
					// if( $Vat > 0 ){
					$LineTotal = ($LineTotal + $value->fc1_linetotal);
					// }
					$CodigoImp = $value->codimp;
				}

				$granTotalIvaOriginal = $granTotalIva;

				if (trim($Data['cnd_currency']) != $MONEDALOCAL) {

					$granTotalIva = ($granTotalIva * $TasaDocLoc);
					$LineTotal = ($LineTotal * $TasaDocLoc);
				}

				if (trim($Data['cnd_currency']) != $MONEDASYS) {

					$MontoSysDB = ($granTotalIva / $TasaLocSys);
				} else {
					$MontoSysDB = $granTotalIvaOriginal;
				}

				// SE AGREGA AL BALANCE
				$BALANCE = $this->account->addBalance($periodo['data'], round($granTotalIva, $DECI_MALES), $value->nd1_cuentaIva, 1, $Data['cnd_docdate'], $Data['business'], $Data['branch']);
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
					':ac1_account' => $value->nd1_cuentaIva,
					':ac1_debit' => round($granTotalIva, $DECI_MALES),
					':ac1_credit' => 0,
					':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
					':ac1_credit_sys' => 0,
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['cnd_docdate']) ? $Data['cnd_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['cnd_duedate']) ? $Data['cnd_duedate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => is_numeric($Data['cnd_doctype']) ? $Data['cnd_doctype'] : 0,
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
					':ac1_made_user' => isset($Data['cnd_createby']) ? $Data['cnd_createby'] : NULL,
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
					':ac1_legal_num' => isset($Data['cnd_cardcode']) ? $Data['cnd_cardcode'] : NULL,
					':ac1_codref' => 1,
					':ac1_base_tax' => round($LineTotal, $DECI_MALES),
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
						'mensaje'	=> 'No se pudo registrar la nota debito de compras 5'
					);

					$this->response($respuesta);

					return;
				}
			}
			//FIN Procedimiento para llenar Impuestos multiple

			if ($Data['cnd_basetype'] != 13) { // solo si el documento no viene de una entrada
				//Procedimiento para llenar costo inventario
				foreach ($DetalleConsolidadoCostoInventario as $key => $posicion) {
					$grantotalCostoInventario = 0;
					$grantotalCostoInventarioOriginal = 0;
					$cuentaInventario = "";
					$dbito = 0;
					$cdito = 0;
					$MontoSysDB = 0;
					$MontoSysCR = 0;
					$sinDatos = 0;

					foreach ($posicion as $key => $value) {

						if ($value->ac1_inventory == 1 || $value->ac1_inventory  == '1') {
							$sinDatos++;
							$cuentaInventario = $value->ac1_account;
							$grantotalCostoInventario = ($grantotalCostoInventario + $value->nd1_linetotal);
						}
					}



					$grantotalCostoInventarioOriginal = $grantotalCostoInventario;

					if (trim($Data['cnd_currency']) != $MONEDALOCAL) {
						$grantotalCostoInventario = ($grantotalCostoInventario * $TasaDocLoc);
					}

					$dbito = $grantotalCostoInventario;

					if (trim($Data['cnd_currency']) != $MONEDASYS) {
						$MontoSysDB = ($dbito / $TasaLocSys);
					} else {
						$MontoSysDB = $grantotalCostoInventarioOriginal;
					}
					// SE AGREGA AL BALANCE
					$BALANCE = $this->account->addBalance($periodo['data'], round($dbito, $DECI_MALES), $cuentaInventario, 1, $Data['cnd_docdate'], $Data['business'], $Data['branch']);
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
						':ac1_account' => $cuentaInventario,
						':ac1_debit' => round($dbito, $DECI_MALES),
						':ac1_credit' => 0,
						':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
						':ac1_credit_sys' => 0,
						':ac1_currex' => 0,
						':ac1_doc_date' => $this->validateDate($Data['cnd_docdate']) ? $Data['cnd_docdate'] : NULL,
						':ac1_doc_duedate' => $this->validateDate($Data['cnd_duedate']) ? $Data['cnd_duedate'] : NULL,
						':ac1_debit_import' => 0,
						':ac1_credit_import' => 0,
						':ac1_debit_importsys' => 0,
						':ac1_credit_importsys' => 0,
						':ac1_font_key' => $resInsert,
						':ac1_font_line' => 1,
						':ac1_font_type' => is_numeric($Data['cnd_doctype']) ? $Data['cnd_doctype'] : 0,
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
						':ac1_made_user' => isset($Data['cnd_createby']) ? $Data['cnd_createby'] : NULL,
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
						':ac1_legal_num' => isset($Data['cnd_cardcode']) ? $Data['cnd_cardcode'] : NULL,
						':ac1_codref' => 1,
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
							'mensaje'	=> 'No se pudo registrar la nota debito de compras 6'
						);

						$this->response($respuesta);

						return;
					}
				}
			}	//FIN Procedimiento para llenar costo inventario


			//SOLO SI ES CUENTA 13 OSEA VIENE DE UNA ENTRADA

			if ($Data['cnd_basetype'] == 13) {

				//CUENTA PUENTE DE INVENTARIO

				$sqlcuentainventario = "SELECT coalesce(pge_bridge_inv_purch, 0) as pge_bridge_inv_purch FROM pgem WHERE pge_id = :business ";
				$rescuentainventario = $this->pedeo->queryTable($sqlcuentainventario, array(':business' => $Data['business']));

				if (isset($rescuentainventario[0]) &&  $rescuentainventario[0]['pge_bridge_inv_purch'] != 0) {
				} else {
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $resDetalleAsiento,
						'mensaje'	=> 'No se pudo registrar la factura de compras'
					);

					$this->response($respuesta);

					return;
				}

				foreach ($DetalleConsolidadoCostoCosto as $key => $posicion) {
					$grantotalCostoInventario = 0;
					$grantotalCostoInventarioOriginal = 0;
					$cuentaCosto = "";
					$dbito = 0;
					$cdito = 0;
					$sinDatos = 0;
					$MontoSysDB = 0;
					$MontoSysCR = 0;
					$cuentaInventario = "";
					foreach ($posicion as $key => $value) {

						if ($value->ac1_inventory == 1 || $value->ac1_inventory  == '1') {

							$sinDatos++;
							$cuentaInventario = $rescuentainventario[0]['pge_bridge_inv_purch'];
							$grantotalCostoInventario = ($grantotalCostoInventario + $value->nd1_linetotal);
						}
					}

					if ($sinDatos > 0) {

						$grantotalCostoInventarioOriginal = $grantotalCostoInventario;

						if (trim($Data['cnd_currency']) != $MONEDALOCAL) {

							$grantotalCostoInventario = ($grantotalCostoInventario * $TasaDocLoc);
						}

						$dbito = $grantotalCostoInventario;

						if (trim($Data['cnd_currency']) != $MONEDASYS) {
							$MontoSysDB = ($dbito / $TasaLocSys);
						} else {
							$MontoSysDB = $grantotalCostoInventarioOriginal;
						}
						// SE AGREGA AL BALANCE
						$BALANCE = $this->account->addBalance($periodo['data'], round($dbito, $DECI_MALES), $cuentaCosto, 1, $Data['cnd_docdate'], $Data['business'], $Data['branch']);
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
							':ac1_account' => $cuentaCosto,
							':ac1_debit' => round($dbito, $DECI_MALES),
							':ac1_credit' => 0,
							':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
							':ac1_credit_sys' => 0,
							':ac1_currex' => 0,
							':ac1_doc_date' => $this->validateDate($Data['cnd_docdate']) ? $Data['cnd_docdate'] : NULL,
							':ac1_doc_duedate' => $this->validateDate($Data['cnd_duedate']) ? $Data['cnd_duedate'] : NULL,
							':ac1_debit_import' => 0,
							':ac1_credit_import' => 0,
							':ac1_debit_importsys' => 0,
							':ac1_credit_importsys' => 0,
							':ac1_font_key' => $resInsert,
							':ac1_font_line' => 1,
							':ac1_font_type' => is_numeric($Data['cnd_doctype']) ? $Data['cnd_doctype'] : 0,
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
							':ac1_made_user' => isset($Data['cnd_createby']) ? $Data['cnd_createby'] : NULL,
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
							':ac1_legal_num' => isset($Data['cnd_cardcode']) ? $Data['cnd_cardcode'] : NULL,
							':ac1_codref' => 1,
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
			//SOLO SI ES CUENTA 3

			//FIN Procedimiento para llenar costo costo

			//Procedimiento para llenar cuentas por cobrar

			$sqlcuentaCxP = "SELECT  f1.dms_card_code, f2.mgs_acct FROM dmsn AS f1
															 JOIN dmgs  AS f2
															 ON CAST(f2.mgs_id AS varchar(100)) = f1.dms_group_num
															 WHERE  f1.dms_card_code = :dms_card_code
															 AND f1.dms_card_type = '2'"; //2 para proveedores


			$rescuentaCxP = $this->pedeo->queryTable($sqlcuentaCxP, array(":dms_card_code" => $Data['cnd_cardcode']));




			if (isset($rescuentaCxP[0])) {

				$debitoo = 0;
				$creditoo = 0;
				$MontoSysDB = 0;
				$MontoSysCR = 0;
				$TotalDoc = $Data['cnd_doctotal'];
				$TotalDoc2 = 0;
				$TotalDocOri = $TotalDoc;

				$cuentaCxP = $rescuentaCxP[0]['mgs_acct'];

				if (trim($Data['cnd_currency']) != $MONEDALOCAL) {
					$TotalDoc = ($TotalDoc * $TasaDocLoc);
				}


				if (trim($Data['cnd_currency']) != $MONEDASYS) {
					$MontoSysCR = ($TotalDoc / $TasaLocSys);
				} else {
					$MontoSysCR = $TotalDocOri;
				}

				if ($Data['cnd_basetype'] == 15) {
					$TotalDoc2 = $TotalDoc;
				}
				// SE AGREGA AL BALANCE
				$BALANCE = $this->account->addBalance($periodo['data'], round($totalRetencion, $DECI_MALES), $cuentaCxP, 2, $Data['cnd_docdate'], $Data['business'], $Data['branch']);
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
					':ac1_account' => $cuentaCxP,
					':ac1_debit' => 0,
					':ac1_credit' => round($TotalDoc, $DECI_MALES),
					':ac1_debit_sys' => 0,
					':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['cnd_docdate']) ? $Data['cnd_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['cnd_duedate']) ? $Data['cnd_duedate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => is_numeric($Data['cnd_doctype']) ? $Data['cnd_doctype'] : 0,
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
					':ac1_made_user' => isset($Data['cnd_createby']) ? $Data['cnd_createby'] : NULL,
					':ac1_accperiod' => $periodo['data'],
					':ac1_close' => 0,
					':ac1_cord' => 0,
					':ac1_ven_debit' => round($TotalDoc2, $DECI_MALES),
					':ac1_ven_credit' => round($TotalDoc, $DECI_MALES),
					':ac1_fiscal_acct' => 0,
					':ac1_taxid' => 0,
					':ac1_isrti' => 0,
					':ac1_basert' => 0,
					':ac1_mmcode' => 0,
					':ac1_legal_num' => isset($Data['cnd_cardcode']) ? $Data['cnd_cardcode'] : NULL,
					':ac1_codref' => 1,
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
						'mensaje'	=> 'No se pudo registrar la nota debito de compras 8'
					);

					$this->response($respuesta);

					return;
				}
			} else {

				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'	  => $rescuentaCxP,
					'mensaje'	=> 'No se pudo registrar la nota debito de compras, el tercero no tiene cuenta asociada'
				);

				$this->response($respuesta);

				return;
			}

			//FIN Procedimiento para llenar cuentas por pagar

			//PROCEDIMIENTO PARA LLENAR ASIENTO DE RENTENCIONES

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
							'mensaje'	=> 'No se pudo registrar la factura de compras, no se encontro la cuenta para la retencion ' . $value->crt_typert
						);

						$this->response($respuesta);

						return;
					}
				}

				$Basert = $BaseLineaRet;
				$totalRetencionOriginal = $totalRetencion;

				if (trim($Data['cnd_currency']) != $MONEDALOCAL) {
					$totalRetencion = ($totalRetencion * $TasaDocLoc);
					$BaseLineaRet = ($BaseLineaRet * $TasaDocLoc);
					$Basert = $BaseLineaRet;
				}


				if (trim($Data['cnd_currency']) != $MONEDASYS) {
					$MontoSysDB = ($totalRetencion / $TasaLocSys);
				} else {
					$MontoSysDB = 	$totalRetencionOriginal;
				}

				// SE AGREGA AL BALANCE
				$BALANCE = $this->account->addBalance($periodo['data'], round($totalRetencion, $DECI_MALES), $cuenta, 1, $Data['cnd_docdate'], $Data['business'], $Data['branch']);
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
					':ac1_debit' => round($totalRetencion, $DECI_MALES),
					':ac1_credit' => 0,
					':ac1_debit_sys' => round($MontoSysCR, $DECI_MALES),
					':ac1_credit_sys' => 0,
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['cnd_docdate']) ? $Data['cnd_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['cnd_duedate']) ? $Data['cnd_duedate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => is_numeric($Data['cnd_doctype']) ? $Data['cnd_doctype'] : 0,
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
					':ac1_made_user' => isset($Data['cnd_createby']) ? $Data['cnd_createby'] : NULL,
					':ac1_accperiod' => $periodo['data'],
					':ac1_close' => 0,
					':ac1_cord' => 0,
					':ac1_ven_debit' => 0,
					':ac1_ven_credit' => 0,
					':ac1_fiscal_acct' => 0,
					':ac1_taxid' => 1,
					':ac1_isrti' => $Profitrt,
					':ac1_basert' => round($Basert, $DECI_MALES),
					':ac1_mmcode' => 0,
					':ac1_legal_num' => isset($Data['cnd_cardcode']) ? $Data['cnd_cardcode'] : NULL,
					':ac1_codref' => 1,
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

			//FIN PROCEDIMIENTO PARA LLENAR ASIENTO DE RENTENCIONES


			//FIN DE OPERACIONES VITALES

			// VALIDANDO ESTADOS DE DOCUMENTOS
			//
			// if ($Data['cnd_basetype'] == 1) {
			//
			//
			// 	$sqlEstado = 'SELECT distinct
			// 								case
			// 									when (sum(t3.ov1_quantity) - t1.vc1_quantity) = 0
			// 										then 1
			// 									else 0
			// 								end "estado"
			// 							from dvct t0
			// 							left join vct1 t1 on t0.dvc_docentry = t1.vc1_docentry
			// 							left join dvov t2 on t0.dvc_docentry = t2.vov_baseentry
			// 							left join vov1 t3 on t2.vov_docentry = t3.ov1_docentry and t1.vc1_itemcode = t3.ov1_itemcode
			// 							where t0.dvc_docentry = :dvc_docentry
			// 							group by
			// 								t1.vc1_quantity';
			//
			//
			// 	$resEstado = $this->pedeo->queryTable($sqlEstado, array(':dvc_docentry' => $Data['cnd_baseentry']));
			//
			// 	if(isset($resEstado[0]) && $resEstado[0]['estado'] == 1){
			//
			// 				$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
			// 														VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";
			//
			// 				$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(
			//
			//
			// 									':bed_docentry' => $Data['cnd_baseentry'],
			// 									':bed_doctype' => $Data['cnd_basetype'],
			// 									':bed_status' => 3, //ESTADO CERRADO
			// 									':bed_createby' => $Data['cnd_createby'],
			// 									':bed_date' => date('Y-m-d'),
			// 									':bed_baseentry' => $resInsert,
			// 									':bed_basetype' => $Data['cnd_doctype']
			// 				));
			//
			//
			// 				if(is_numeric($resInsertEstado) && $resInsertEstado > 0){
			//
			// 				}else{
			//
			// 						 $this->pedeo->trans_rollback();
			//
			// 							$respuesta = array(
			// 								'error'   => true,
			// 								'data' => $resInsertEstado,
			// 								'mensaje'	=> 'No se pudo registrar la nota debito de compras'
			// 							);
			//
			//
			// 							$this->response($respuesta);
			//
			// 							return;
			// 				}
			//
			// 	}
			//
			// } else if ($Data['cnd_basetype'] == 2) {
			//
			//
			// 			$sqlEstado = 'SELECT distinct
			// 											case
			// 												when (sum(t3.em1_quantity) - t1.ov1_quantity) = 0
			// 													then 1
			// 												else 0
			// 											end "estado"
			// 										from dvov t0
			// 										left join vov1 t1 on t0.vov_docentry = t1.ov1_docentry
			// 										left join dvem t2 on t0.vov_docentry = t2.vem_baseentry
			// 										left join vem1 t3 on t2.vem_docentry = t3.em1_docentry and t1.ov1_itemcode = t3.em1_itemcode
			// 										where t0.vov_docentry = :vov_docentry
			// 										group by
			// 										t1.ov1_quantity';
			//
			//
			// 			$resEstado = $this->pedeo->queryTable($sqlEstado, array(':vov_docentry' => $Data['cnd_baseentry']));
			//
			// 			if(isset($resEstado[0]) && $resEstado[0]['estado'] == 1){
			//
			// 						$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
			// 																VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";
			//
			// 						$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(
			//
			//
			// 											':bed_docentry' => $Data['cnd_baseentry'],
			// 											':bed_doctype' => $Data['cnd_basetype'],
			// 											':bed_status' => 3, //ESTADO CERRADO
			// 											':bed_createby' => $Data['cnd_createby'],
			// 											':bed_date' => date('Y-m-d'),
			// 											':bed_baseentry' => $resInsert,
			// 											':bed_basetype' => $Data['cnd_doctype']
			// 						));
			//
			//
			// 						if(is_numeric($resInsertEstado) && $resInsertEstado > 0){
			//
			// 						}else{
			//
			// 								 $this->pedeo->trans_rollback();
			//
			// 									$respuesta = array(
			// 										'error'   => true,
			// 										'data' => $resInsertEstado,
			// 										'mensaje'	=> 'No se pudo registrar la nota debito de compras'
			// 									);
			//
			//
			// 									$this->response($respuesta);
			//
			// 									return;
			// 						}
			//
			// 			}
			//
			//
			//
			//
			// } else if ($Data['cnd_basetype'] == 3) {
			//
			// 		 $sqlEstado = 'SELECT distinct
			// 										case
			// 											when (sum(t3.nd1_quantity) - t1.em1_quantity) = 0
			// 												then 1
			// 											else 0
			// 										end "estado"
			// 									from dvem t0
			// 									left join vem1 t1 on t0.vem_docentry = t1.em1_docentry
			// 									left join dcnd t2 on t0.vem_docentry = t2.cnd_baseentry
			// 									left join cnd1 t3 on t2.cnd_docentry = t3.nd1_docentry and t1.em1_itemcode = t3.nd1_itemcode
			// 									where t0.vem_docentry = :vem_docentry
			// 									group by t1.em1_quantity';
			//
			// 			$resEstado = $this->pedeo->queryTable($sqlEstado, array(':vem_docentry' => $Data['cnd_baseentry']));
			//
			// 			if(isset($resEstado[0]) && $resEstado[0]['estado'] == 1){
			//
			// 						$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
			// 																VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";
			//
			// 						$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(
			//
			//
			// 											':bed_docentry' => $Data['cnd_baseentry'],
			// 											':bed_doctype' => $Data['cnd_basetype'],
			// 											':bed_status' => 3, //ESTADO CERRADO
			// 											':bed_createby' => $Data['cnd_createby'],
			// 											':bed_date' => date('Y-m-d'),
			// 											':bed_baseentry' => $resInsert,
			// 											':bed_basetype' => $Data['cnd_doctype']
			// 						));
			//
			//
			// 						if(is_numeric($resInsertEstado) && $resInsertEstado > 0){
			//
			// 						}else{
			//
			// 								 $this->pedeo->trans_rollback();
			//
			// 									$respuesta = array(
			// 										'error'   => true,
			// 										'data' => $resInsertEstado,
			// 										'mensaje'	=> 'No se pudo registrar la nota debito de compras'
			// 									);
			//
			//
			// 									$this->response($respuesta);
			//
			// 									return;
			// 						}
			//
			// 			}
			//
			// }

			// FIN VALIDACION DE ESTADOS


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

			// Si todo sale bien despues de insertar el detalle de la nota debito de compras
			// se confirma la trasaccion  para que los cambios apliquen permanentemente
			// en la base de datos y se confirma la operacion exitosa.
			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'Nota debito de compras #'.$DocNumVerificado.' registrada con exito'
			);
		} else {
			// Se devuelven los cambios realizados en la transaccion
			// si occurre un error  y se muestra devuelve el error.
			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data' => $resInsert,
				'mensaje'	=> 'No se pudo registrar la nota debito de compras 9'
			);
		}

		$this->response($respuesta);
	}
	//ACTUALIZAR nota debito de compras
	public function updatePurchaseNd_post()
	{

		$Data = $this->post();

		if (
			!isset($Data['cnd_docentry']) or !isset($Data['cnd_docnum']) or
			!isset($Data['cnd_docdate']) or !isset($Data['cnd_duedate']) or
			!isset($Data['cnd_duedev']) or !isset($Data['cnd_pricelist']) or
			!isset($Data['cnd_cardcode']) or !isset($Data['cnd_cardname']) or
			!isset($Data['cnd_currency']) or !isset($Data['cnd_contacid']) or
			!isset($Data['cnd_slpcode']) or !isset($Data['cnd_empid']) or
			!isset($Data['cnd_comment']) or !isset($Data['cnd_doctotal']) or
			!isset($Data['cnd_baseamnt']) or !isset($Data['cnd_taxtotal']) or
			!isset($Data['cnd_discprofit']) or !isset($Data['cnd_discount']) or
			!isset($Data['cnd_createat']) or !isset($Data['cnd_baseentry']) or
			!isset($Data['cnd_basetype']) or !isset($Data['cnd_doctype']) or
			!isset($Data['cnd_idadd']) or !isset($Data['cnd_adress']) or
			!isset($Data['cnd_paytype']) or !isset($Data['cnd_attch']) or
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
				'mensaje' => 'No se encontro el detalle de la nota debito de compras'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlUpdate = "UPDATE dcnd	SET cnd_docdate=:cnd_docdate,cnd_duedate=:cnd_duedate, cnd_duedev=:cnd_duedev, cnd_pricelist=:cnd_pricelist, cnd_cardcode=:cnd_cardcode,
			  						cnd_cardname=:cnd_cardname, cnd_currency=:cnd_currency, cnd_contacid=:cnd_contacid, cnd_slpcode=:cnd_slpcode,
										cnd_empid=:cnd_empid, cnd_comment=:cnd_comment, cnd_doctotal=:cnd_doctotal, cnd_baseamnt=:cnd_baseamnt,
										cnd_taxtotal=:cnd_taxtotal, cnd_discprofit=:cnd_discprofit, cnd_discount=:cnd_discount, cnd_createat=:cnd_createat,
										cnd_baseentry=:cnd_baseentry, cnd_basetype=:cnd_basetype, cnd_doctype=:cnd_doctype, cnd_idadd=:cnd_idadd,
										cnd_adress=:cnd_adress, cnd_paytype=:cnd_paytype, business = :business, branch = :branch, cnd_internal_comments = :cnd_internal_comments   WHERE cnd_docentry=:cnd_docentry";

		$this->pedeo->trans_begin();

		$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
			':cnd_docnum' => is_numeric($Data['cnd_docnum']) ? $Data['cnd_docnum'] : 0,
			':cnd_docdate' => $this->validateDate($Data['cnd_docdate']) ? $Data['cnd_docdate'] : NULL,
			':cnd_duedate' => $this->validateDate($Data['cnd_duedate']) ? $Data['cnd_duedate'] : NULL,
			':cnd_duedev' => $this->validateDate($Data['cnd_duedev']) ? $Data['cnd_duedev'] : NULL,
			':cnd_pricelist' => is_numeric($Data['cnd_pricelist']) ? $Data['cnd_pricelist'] : 0,
			':cnd_cardcode' => isset($Data['cnd_pricelist']) ? $Data['cnd_pricelist'] : NULL,
			':cnd_cardname' => isset($Data['cnd_cardname']) ? $Data['cnd_cardname'] : NULL,
			':cnd_currency' => isset($Data['cnd_currency']) ? $Data['cnd_currency'] : NULL,
			':cnd_contacid' => isset($Data['cnd_contacid']) ? $Data['cnd_contacid'] : NULL,
			':cnd_slpcode' => is_numeric($Data['cnd_slpcode']) ? $Data['cnd_slpcode'] : 0,
			':cnd_empid' => is_numeric($Data['cnd_empid']) ? $Data['cnd_empid'] : 0,
			':cnd_comment' => isset($Data['cnd_comment']) ? $Data['cnd_comment'] : NULL,
			':cnd_doctotal' => is_numeric($Data['cnd_doctotal']) ? $Data['cnd_doctotal'] : 0,
			':cnd_baseamnt' => is_numeric($Data['cnd_baseamnt']) ? $Data['cnd_baseamnt'] : 0,
			':cnd_taxtotal' => is_numeric($Data['cnd_taxtotal']) ? $Data['cnd_taxtotal'] : 0,
			':cnd_discprofit' => is_numeric($Data['cnd_discprofit']) ? $Data['cnd_discprofit'] : 0,
			':cnd_discount' => is_numeric($Data['cnd_discount']) ? $Data['cnd_discount'] : 0,
			':cnd_createat' => $this->validateDate($Data['cnd_createat']) ? $Data['cnd_createat'] : NULL,
			':cnd_baseentry' => is_numeric($Data['cnd_baseentry']) ? $Data['cnd_baseentry'] : 0,
			':cnd_basetype' => is_numeric($Data['cnd_basetype']) ? $Data['cnd_basetype'] : 0,
			':cnd_doctype' => is_numeric($Data['cnd_doctype']) ? $Data['cnd_doctype'] : 0,
			':cnd_idadd' => isset($Data['cnd_idadd']) ? $Data['cnd_idadd'] : NULL,
			':cnd_adress' => isset($Data['cnd_adress']) ? $Data['cnd_adress'] : NULL,
			':cnd_paytype' => is_numeric($Data['cnd_paytype']) ? $Data['cnd_paytype'] : 0,
			':business' => $Data['business'],
			':branch' => $Data['branch'],
			':cnd_internal_comments' => isset($Data['cnd_internal_comments']) ? $Data['cnd_internal_comments'] : NULL,
			':cnd_docentry' => $Data['cnd_docentry']
		));

		if (is_numeric($resUpdate) && $resUpdate == 1) {

			$this->pedeo->queryTable("DELETE FROM cnd1 WHERE nd1_docentry=:nd1_docentry", array(':nd1_docentry' => $Data['cnd_docentry']));

			foreach ($ContenidoDetalle as $key => $detail) {

				$sqlInsertDetail = "INSERT INTO cnd1(nd1_docentry, nd1_itemcode, nd1_itemname, nd1_quantity, nd1_uom, nd1_whscode,
																			nd1_price, nd1_vat, nd1_vatsum, nd1_discount, nd1_linetotal, nd1_costcode, nd1_ubusiness, nd1_project,
																			nd1_acctcode, nd1_basetype, nd1_doctype, nd1_avprice, nd1_inventory, nd1_acciva, nd1_ubication, nd1_codmunicipality)VALUES(:nd1_docentry, :nd1_itemcode, :nd1_itemname, :nd1_quantity,
																			:nd1_uom, :nd1_whscode,:nd1_price, :nd1_vat, :nd1_vatsum, :nd1_discount, :nd1_linetotal, :nd1_costcode, :nd1_ubusiness, :nd1_project,
																			:nd1_acctcode, :nd1_basetype, :nd1_doctype, :nd1_avprice, :nd1_inventory, :nd1_acciva, :nd1_ubication, :nd1_codmunicipality)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':nd1_docentry' => $Data['cnd_docentry'],
					':nd1_itemcode' => isset($detail['nd1_itemcode']) ? $detail['nd1_itemcode'] : NULL,
					':nd1_itemname' => isset($detail['nd1_itemname']) ? $detail['nd1_itemname'] : NULL,
					':nd1_quantity' => is_numeric($detail['nd1_quantity']) ? $detail['nd1_quantity'] : 0,
					':nd1_uom' => isset($detail['nd1_uom']) ? $detail['nd1_uom'] : NULL,
					':nd1_whscode' => isset($detail['nd1_whscode']) ? $detail['nd1_whscode'] : NULL,
					':nd1_price' => is_numeric($detail['nd1_price']) ? $detail['nd1_price'] : 0,
					':nd1_vat' => is_numeric($detail['nd1_vat']) ? $detail['nd1_vat'] : 0,
					':nd1_vatsum' => is_numeric($detail['nd1_vatsum']) ? $detail['nd1_vatsum'] : 0,
					':nd1_discount' => is_numeric($detail['nd1_discount']) ? $detail['nd1_discount'] : 0,
					':nd1_linetotal' => is_numeric($detail['nd1_linetotal']) ? $detail['nd1_linetotal'] : 0,
					':nd1_costcode' => isset($detail['nd1_costcode']) ? $detail['nd1_costcode'] : NULL,
					':nd1_ubusiness' => isset($detail['nd1_ubusiness']) ? $detail['nd1_ubusiness'] : NULL,
					':nd1_project' => isset($detail['nd1_project']) ? $detail['nd1_project'] : NULL,
					':nd1_acctcode' => is_numeric($detail['nd1_acctcode']) ? $detail['nd1_acctcode'] : 0,
					':nd1_basetype' => is_numeric($detail['nd1_basetype']) ? $detail['nd1_basetype'] : 0,
					':nd1_doctype' => is_numeric($detail['nd1_doctype']) ? $detail['nd1_doctype'] : 0,
					':nd1_avprice' => is_numeric($detail['nd1_avprice']) ? $detail['nd1_avprice'] : 0,
					':nd1_inventory' => is_numeric($detail['nd1_inventory']) ? $detail['nd1_inventory'] : NULL,
					':nd1_acciva' => is_numeric($detail['nd1_cuentaIva']) ? $detail['nd1_cuentaIva'] : 0,
					':nd1_ubication' => isset($detail['nd1_ubication']) ? $detail['nd1_ubication'] : 0,
					':nd1_codmunicipality' => isset($detail['nd1_codmunicipality']) ? $detail['nd1_codmunicipality'] : NULL
				));

				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle de la nota debito de compras se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertDetail,
						'mensaje'	=> 'No se pudo registrar la nota debito de compras 10'
					);

					$this->response($respuesta);

					return;
				}
			}


			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resUpdate,
				'mensaje' => 'Nota debito de compras actualizada con exito'
			);
		} else {

			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data'    => $resUpdate,
				'mensaje'	=> 'No se pudo actualizar la nota debito de compras'
			);
		}

		$this->response($respuesta);
	}


	//OBTENER nota debito de compras
	public function getPurchaseNd_get()
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
		$campos = ",T4.dms_phone1, T4.dms_phone2, T4.dms_cel";

		$sqlSelect = self::getColumn('dcnd', 'cnd', $campos, '', $DECI_MALES, $Data['business'], $Data['branch'],0,0,0,"",2);


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


	//OBTENER nota debito de compras POR ID
	public function getPurchaseNdById_get()
	{

		$Data = $this->get();
		$DECI_MALES =  $this->generic->getDecimals();
		if (!isset($Data['cnd_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$campos = ",T4.dms_phone1, T4.dms_phone2, T4.dms_cel";

		$sqlSelect = self::getColumn('dcnd', 'cnd', $campos, '', $DECI_MALES, $Data['business'], $Data['branch'],0,0,0," AND cnd_docentry = :cnd_docentry");

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":cnd_docentry" => $Data['cnd_docentry']));

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


	//OBTENER nota debito de compras DETALLE POR ID
	public function getPurchaseNdDetail_get()
	{

		$Data = $this->get();

		if (!isset($Data['nd1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM cnd1 WHERE nd1_docentry =:nd1_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":nd1_docentry" => $Data['nd1_docentry']));

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




	//OBTENER nota debito DE VENTA POR ID SOCIO DE NEGOCIO
	public function getPurchaseNdBySN_get()
	{

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

		$sqlSelect = " SELECT * FROM dcnd WHERE cnd_cardcode =:cnd_cardcode";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":cnd_cardcode" => $Data['dms_card_code']));

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

		$duplicateData = $this->documentduplicate->getDuplicate('dcnd','cnd',$Data['dms_card_code'],$Data['business']);


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

		if (!isset($Data['nd1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

			$copy = $this->documentduplicate->getDuplicateDt($Data['nd1_docentry'],'dcnd','cnd1','cnd','nd1','deducible');

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

		$update = "UPDATE dcnd SET cnd_comment = :cnd_comment, cnd_internal_comments = :cnd_internal_comments WHERE cnd_docentry = :cnd_docentry";
		$resUpdate = $this->pedeo->updateRow($update,array(
			':cnd_comment' => $Data['cnd_comment'],
			':cnd_internal_comments' => $Data['cnd_internal_comments'],
			':cnd_docentry' => $Data['cnd_docentry']
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
