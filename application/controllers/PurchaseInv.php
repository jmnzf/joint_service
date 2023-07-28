<?php
// FACTURA DE COMPRAS
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class PurchaseInv extends REST_Controller
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
		$this->load->library('DocumentNumbering');
		$this->load->library('Tasa');
		$this->load->library('DocumentDuplicate');
	}

	//CREAR NUEVA FACTURA DE compras
	public function createPurchaseInv_post()
	{
		$Data = $this->post();
		$TasaDocLoc = 0;
		$TasaLocSys = 0;
		$MONEDALOCAL = "";
		$MONEDASYS = "";

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
		$DetalleAsientoIngreso = new stdClass(); // Cada objeto de las linea del detalle consolidado
		$DetalleAsientoIva = new stdClass();
		$DetalleCostoInventario = new stdClass();
		$DetalleCostoCosto = new stdClass();
		$DetalleRetencion = new stdClass();
		$DetalleItemNoInventariable = new stdClass();
		$DetalleConsolidadoIngreso = []; // Array Final con los datos del asiento solo ingreso
		$DetalleConsolidadoCostoInventario = [];
		$DetalleConsolidadoCostoCosto = [];
		$DetalleConsolidadoIva = []; // Array Final con los datos del asiento segun el iva
		$DetalleConsolidadoRetencion = [];
		$DetalleConsolidadoItemNoInventariable = [];
		$inArrayIngreso = array(); // Array para mantener el indice de las llaves para ingreso
		$inArrayIva = array(); // Array para mantener el indice de las llaves para iva
		$inArrayCostoInventario = array();
		$inArrayCostoCosto = array();
		$inArrayRetencion = array();
		$inArrayItemNoInventariable = array();
		$llave = ""; // la comnbinacion entre la cuenta contable,proyecto, unidad de negocio y centro de costo
		$llaveIva = ""; //segun tipo de iva
		$llaveCostoInventario = "";
		$llaveCostoCosto = "";
		$llaveRetencion = "";
		$llaveItemNoInventariable = "";
		$posicion = 0; // contiene la posicion con que se creara en el array DetalleConsolidado
		$posicionIva = 0;
		$posicionCostoInventario = 0;
		$posicionCostoCosto = 0;
		$posicionRetencion = 0;
		$posicionItemNoInventariable = 0;
		$codigoCuenta = ""; //para saber la naturaleza
		$grantotalCostoInventario = 0;
		$DocNumVerificado = 0;
		$ManejaInvetario = 0;
		$TotalAcuRentencion = 0;
		$ManejaLote = 0;
		$ManejaUbicacion = 0;
		$ManejaSerial = 0;
		$TasaDocLoc = 0; // MANTIENE EL VALOR DE LA TASA DE CONVERSION ENTRE LA MONEDA LOCAL Y LA MONEDA DEL DOCUMENTO
		$TasaLocSys = 0; // MANTIENE EL VALOR DE LA TASA DE CONVERSION ENTRE LA MONEDA LOCAL Y LA MONEDA DEL SISTEMA
		$MONEDALOCAL = 0;
		$CANTUOMPURCHASE = 0; //CANTIDAD EN UNIDAD DE MEDIDA
		$CANTUOMSALE = 0;
		//
		$DetalleAsientoDescuento = new stdClass();
		$DetalleAsientoIvaDescuento = new stdClass();
		$DetalleConsolidadoDescuento = [];
		$DetalleConsolidadoIvaDescuento = [];


		$IvaDescuentoAcumulado = 0;
		$DescuentoAcumulado = 0;
		

		//
		$FactorC = false; // Impuesto con factor de conversion
		// VARIABLES PARA SUMAS
		$SumaCreditosSYS = 0;
		$SumaDebitosSYS = 0;

		$AC1LINE = 1;

		// Se globaliza la variable sqlDetalleAsiento
		$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
													ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
													ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
													ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, ac1_line, ac1_base_tax, business, branch,ac1_codret)VALUES (:ac1_trans_id, :ac1_account,
													:ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
													:ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
													:ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
													:ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref,:ac1_line, :ac1_base_tax, :business, :branch, :ac1_codret)";


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
				'mensaje' => 'No se encontro el detalle de la factura de compras'
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
		$periodo = $this->generic->ValidatePeriod($Data['cfc_duedev'], $Data['cfc_docdate'], $Data['cfc_duedate'], 0);

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
		$DocNumVerificado = $this->documentnumbering->NumberDoc($Data['cfc_series'],$Data['cfc_docdate'],$Data['cfc_duedate']);
		
		if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0){

		}else if ($DocNumVerificado['error']){

			return $this->response($DocNumVerificado, REST_Controller::HTTP_BAD_REQUEST);
		}

		//PROCESO DE TASA
		$dataTasa = $this->tasa->Tasa($Data['cfc_currency'],$Data['cfc_docdate']);

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

		$sqlInsert = "INSERT INTO dcfc(cfc_series, cfc_docnum, cfc_docdate, cfc_duedate, cfc_duedev, cfc_pricelist, cfc_cardcode,
                      cfc_cardname, cfc_currency, cfc_contacid, cfc_slpcode, cfc_empid, cfc_comment, cfc_doctotal, cfc_baseamnt, cfc_taxtotal,
                      cfc_discprofit, cfc_discount, cfc_createat, cfc_baseentry, cfc_basetype, cfc_doctype, cfc_idadd, cfc_adress, cfc_paytype,
                      cfc_createby,cfc_totalret,cfc_totalretiva,cfc_correl, cfc_tax_control_num, business, branch,cfc_bankable,cfc_internal_comments,cfc_correl2)
					  VALUES(:cfc_series, :cfc_docnum, :cfc_docdate, :cfc_duedate, :cfc_duedev, :cfc_pricelist, :cfc_cardcode, :cfc_cardname,
                      :cfc_currency, :cfc_contacid, :cfc_slpcode, :cfc_empid, :cfc_comment, :cfc_doctotal, :cfc_baseamnt, :cfc_taxtotal, :cfc_discprofit, :cfc_discount,
                      :cfc_createat, :cfc_baseentry, :cfc_basetype, :cfc_doctype, :cfc_idadd, :cfc_adress, :cfc_paytype,:cfc_createby,:cfc_totalret,:cfc_totalretiva,
					  :cfc_correl, :cfc_tax_control_num, :business, :branch,:cfc_bankable,:cfc_internal_comments,:cfc_correl2)";


		// Se Inicia la transaccion,
		// Todas las consultas de modificacion siguientes
		// aplicaran solo despues que se confirme la transaccion,
		// de lo contrario no se aplicaran los cambios y se devolvera
		// la base de datos a su estado original.

		$this->pedeo->trans_begin();

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			':cfc_docnum' => $DocNumVerificado,
			':cfc_series' => is_numeric($Data['cfc_series']) ? $Data['cfc_series'] : 0,
			':cfc_docdate' => $this->validateDate($Data['cfc_docdate']) ? $Data['cfc_docdate'] : NULL,
			':cfc_duedate' => $this->validateDate($Data['cfc_duedate']) ? $Data['cfc_duedate'] : NULL,
			':cfc_duedev' => $this->validateDate($Data['cfc_duedev']) ? $Data['cfc_duedev'] : NULL,
			':cfc_pricelist' => is_numeric($Data['cfc_pricelist']) ? $Data['cfc_pricelist'] : 0,
			':cfc_cardcode' => isset($Data['cfc_cardcode']) ? $Data['cfc_cardcode'] : NULL,
			':cfc_cardname' => isset($Data['cfc_cardname']) ? $Data['cfc_cardname'] : NULL,
			':cfc_currency' => isset($Data['cfc_currency']) ? $Data['cfc_currency'] : NULL,
			':cfc_contacid' => isset($Data['cfc_contacid']) ? $Data['cfc_contacid'] : NULL,
			':cfc_slpcode' => is_numeric($Data['cfc_slpcode']) ? $Data['cfc_slpcode'] : 0,
			':cfc_empid' => is_numeric($Data['cfc_empid']) ? $Data['cfc_empid'] : 0,
			':cfc_comment' => isset($Data['cfc_comment']) ? $Data['cfc_comment'] : NULL,
			':cfc_doctotal' => is_numeric($Data['cfc_doctotal']) ? $Data['cfc_doctotal'] : 0,
			':cfc_baseamnt' => is_numeric($Data['cfc_baseamnt']) ? $Data['cfc_baseamnt'] : 0,
			':cfc_taxtotal' => is_numeric($Data['cfc_taxtotal']) ? $Data['cfc_taxtotal'] : 0,
			':cfc_discprofit' => is_numeric($Data['cfc_discprofit']) ? $Data['cfc_discprofit'] : 0,
			':cfc_discount' => is_numeric($Data['cfc_discount']) ? $Data['cfc_discount'] : 0,
			':cfc_createat' => $this->validateDate($Data['cfc_createat']) ? $Data['cfc_createat'] : NULL,
			':cfc_baseentry' => is_numeric($Data['cfc_baseentry']) ? $Data['cfc_baseentry'] : 0,
			':cfc_basetype' => is_numeric($Data['cfc_basetype']) ? $Data['cfc_basetype'] : 0,
			':cfc_doctype' => is_numeric($Data['cfc_doctype']) ? $Data['cfc_doctype'] : 0,
			':cfc_idadd' => isset($Data['cfc_idadd']) ? $Data['cfc_idadd'] : NULL,
			':cfc_adress' => isset($Data['cfc_adress']) ? $Data['cfc_adress'] : NULL,
			':cfc_paytype' => is_numeric($Data['cfc_paytype']) ? $Data['cfc_paytype'] : 0,
			':cfc_createby' => isset($Data['cfc_createby']) ? $Data['cfc_createby'] : NULL,
			':cfc_totalret' => is_numeric($Data['cfc_totalret']) ? $Data['cfc_totalret'] : 0,
			':cfc_totalretiva' => is_numeric($Data['cfc_totalretiva']) ? $Data['cfc_totalretiva'] : 0,
			':cfc_correl' => isset($Data['cfc_correl']) ? $Data['cfc_correl'] : NULL,
			':cfc_tax_control_num' => isset($Data['cfc_tax_control_num']) ? $Data['cfc_tax_control_num'] : NULL,
			':business' => $Data['business'],
			':branch' => $Data['branch'],
			':cfc_bankable' => is_numeric($Data['cfc_bankable']) ? $Data['cfc_bankable'] : 0,
			':cfc_internal_comments' => isset($Data['cfc_internal_comments']) ? $Data['cfc_internal_comments'] : NULL,
			':cfc_correl2' => isset($Data['cfc_correl2']) ? $Data['cfc_correl2'] : NULL,
		));

		if (is_numeric($resInsert) && $resInsert > 0) {

			// Se actualiza la serie de la numeracion del documento

			$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id'      => $Data['cfc_series']
			));


			if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
			} else {
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'    => $resActualizarNumeracion,
					'mensaje'	=> 'No se pudo crear la factura de compras'
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
				':bed_doctype' => $Data['cfc_doctype'],
				':bed_status' => 1, // Estado Abierto
				':bed_createby' => $Data['cfc_createby'],
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
					'mensaje'	=> 'No se pudo registrar la factura de compras'
				);


				$this->response($respuesta);

				return;
			}

			//FIN PROCESO ESTADO DEL DOCUMENTO


			//Se agregan los asientos contables*/*******

			$sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user,mac_accperiod,business,branch)
															 VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user,:mac_accperiod,:business,:branch)";


			$resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

				':mac_doc_num' => 1,
				':mac_status' => 1,
				':mac_base_type' => is_numeric($Data['cfc_doctype']) ? $Data['cfc_doctype'] : 0,
				':mac_base_entry' => $resInsert,
				':mac_doc_date' => $this->validateDate($Data['cfc_docdate']) ? $Data['cfc_docdate'] : NULL,
				':mac_doc_duedate' => $this->validateDate($Data['cfc_duedate']) ? $Data['cfc_duedate'] : NULL,
				':mac_legal_date' => $this->validateDate($Data['cfc_docdate']) ? $Data['cfc_docdate'] : NULL,
				':mac_ref1' => is_numeric($Data['cfc_doctype']) ? $Data['cfc_doctype'] : 0,
				':mac_ref2' => "",
				':mac_ref3' => "",
				':mac_loc_total' => is_numeric($Data['cfc_doctotal']) ? $Data['cfc_doctotal'] : 0,
				':mac_fc_total' => is_numeric($Data['cfc_doctotal']) ? $Data['cfc_doctotal'] : 0,
				':mac_sys_total' => is_numeric($Data['cfc_doctotal']) ? $Data['cfc_doctotal'] : 0,
				':mac_trans_dode' => 1,
				':mac_beline_nume' => 1,
				':mac_vat_date' => $this->validateDate($Data['cfc_docdate']) ? $Data['cfc_docdate'] : NULL,
				':mac_serie' => 1,
				':mac_number' => 1,
				':mac_bammntsys' => is_numeric($Data['cfc_baseamnt']) ? $Data['cfc_baseamnt'] : 0,
				':mac_bammnt' => is_numeric($Data['cfc_baseamnt']) ? $Data['cfc_baseamnt'] : 0,
				':mac_wtsum' => 1,
				':mac_vatsum' => is_numeric($Data['cfc_taxtotal']) ? $Data['cfc_taxtotal'] : 0,
				':mac_comments' => isset($Data['cfc_comment']) ? $Data['cfc_comment'] : NULL,
				':mac_create_date' => $this->validateDate($Data['cfc_createat']) ? $Data['cfc_createat'] : NULL,
				':mac_made_usuer' => isset($Data['cfc_createby']) ? $Data['cfc_createby'] : NULL,
				':mac_update_date' => date("Y-m-d"),
				':mac_update_user' => isset($Data['cfc_createby']) ? $Data['cfc_createby'] : NULL,
				':mac_accperiod' => $periodo['data'],
				':business' => $Data['business'],
				':branch' => $Data['branch']
				
			));


			if (is_numeric($resInsertAsiento) && $resInsertAsiento > 0) {
				// Se verifica que el detalle no de error insertando //
			} else {

				// si falla algun insert del detalle de la factura de compras se devuelven los cambios realizados por la transaccion,
				// se retorna el error y se detiene la ejecucion del codigo restante.
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'	  => $resInsertAsiento,
					'mensaje'	=> 'No se pudo registrar la factura de compras'
				);

				$this->response($respuesta);

				return;
			}

			//SE APLICA PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS
			if (isset($Data['cfc_baseentry']) && is_numeric($Data['cfc_baseentry']) && isset($Data['cfc_basetype']) && is_numeric($Data['cfc_basetype'])) {

				$sqlDocInicio = "SELECT bmd_tdi, bmd_ndi FROM tbmd WHERE  bmd_doctype = :bmd_doctype AND bmd_docentry = :bmd_docentry";
				$resDocInicio = $this->pedeo->queryTable($sqlDocInicio, array(
					':bmd_doctype' => $Data['cfc_basetype'],
					':bmd_docentry' => $Data['cfc_baseentry']
				));


				if (isset($resDocInicio[0])) {

					$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
															bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency)
															VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
															:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency)";

					$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

						':bmd_doctype' => is_numeric($Data['cfc_doctype']) ? $Data['cfc_doctype'] : 0,
						':bmd_docentry' => $resInsert,
						':bmd_createat' => $this->validateDate($Data['cfc_createat']) ? $Data['cfc_createat'] : NULL,
						':bmd_doctypeo' => is_numeric($Data['cfc_basetype']) ? $Data['cfc_basetype'] : 0, //ORIGEN
						':bmd_docentryo' => is_numeric($Data['cfc_baseentry']) ? $Data['cfc_baseentry'] : 0,  //ORIGEN
						':bmd_tdi' => $resDocInicio[0]['bmd_tdi'], // DOCUMENTO INICIAL
						':bmd_ndi' => $resDocInicio[0]['bmd_ndi'], // DOCUMENTO INICIAL
						':bmd_docnum' => $DocNumVerificado,
						':bmd_doctotal' => is_numeric($Data['cfc_doctotal']) ? $Data['cfc_doctotal'] : 0,
						':bmd_cardcode' => isset($Data['cfc_cardcode']) ? $Data['cfc_cardcode'] : NULL,
						':bmd_cardtype' => 2,
						':bmd_currency' => isset($Data['cfc_currency'])?$Data['cfc_currency']:NULL,
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

						':bmd_doctype' => is_numeric($Data['cfc_doctype']) ? $Data['cfc_doctype'] : 0,
						':bmd_docentry' => $resInsert,
						':bmd_createat' => $this->validateDate($Data['cfc_createat']) ? $Data['cfc_createat'] : NULL,
						':bmd_doctypeo' => is_numeric($Data['cfc_basetype']) ? $Data['cfc_basetype'] : 0, //ORIGEN
						':bmd_docentryo' => is_numeric($Data['cfc_baseentry']) ? $Data['cfc_baseentry'] : 0,  //ORIGEN
						':bmd_tdi' => is_numeric($Data['cfc_doctype']) ? $Data['cfc_doctype'] : 0, // DOCUMENTO INICIAL
						':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
						':bmd_docnum' => $DocNumVerificado,
						':bmd_doctotal' => is_numeric($Data['cfc_doctotal']) ? $Data['cfc_doctotal'] : 0,
						':bmd_cardcode' => isset($Data['cfc_cardcode']) ? $Data['cfc_cardcode'] : NULL,
						':bmd_cardtype' => 2,
						':bmd_currency' => isset($Data['cfc_currency'])?$Data['cfc_currency']:NULL,
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

					':bmd_doctype' => is_numeric($Data['cfc_doctype']) ? $Data['cfc_doctype'] : 0,
					':bmd_docentry' => $resInsert,
					':bmd_createat' => $this->validateDate($Data['cfc_createat']) ? $Data['cfc_createat'] : NULL,
					':bmd_doctypeo' => is_numeric($Data['cfc_basetype']) ? $Data['cfc_basetype'] : 0, //ORIGEN
					':bmd_docentryo' => is_numeric($Data['cfc_baseentry']) ? $Data['cfc_baseentry'] : 0,  //ORIGEN
					':bmd_tdi' => is_numeric($Data['cfc_doctype']) ? $Data['cfc_doctype'] : 0, // DOCUMENTO INICIAL
					':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
					':bmd_docnum' => $DocNumVerificado,
					':bmd_doctotal' => is_numeric($Data['cfc_doctotal']) ? $Data['cfc_doctotal'] : 0,
					':bmd_cardcode' => isset($Data['cfc_cardcode']) ? $Data['cfc_cardcode'] : NULL,
					':bmd_cardtype' => 2,
					':bmd_currency' => isset($Data['cfc_currency'])?$Data['cfc_currency']:NULL,
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


				$CANTUOMPURCHASE = $this->generic->getUomPurchase($detail['fc1_itemcode']);
				$CANTUOMSALE = $this->generic->getUomSale($detail['fc1_itemcode']);

				if ($CANTUOMPURCHASE == 0 || $CANTUOMSALE == 0) {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' 		=> $detail['fc1_itemcode'],
						'mensaje'	=> 'No se encontro la equivalencia de la unidad de medida para el item: ' . $detail['fc1_itemcode']
					);

					$this->response($respuesta);

					return;
				}

				$sqlInsertDetail = "INSERT INTO cfc1(fc1_docentry,fc1_itemcode, fc1_itemname, fc1_quantity, fc1_uom, fc1_whscode,
                                    fc1_price, fc1_vat, fc1_vatsum, fc1_discount, fc1_linetotal, fc1_costcode, fc1_ubusiness, fc1_project,
                                    fc1_acctcode, fc1_basetype, fc1_doctype, fc1_avprice, fc1_inventory, fc1_acciva, fc1_linenum, fc1_codimp, 
									fc1_ubication,fc1_baseline,ote_code)VALUES(:fc1_docentry,:fc1_itemcode, :fc1_itemname, :fc1_quantity,
                                    :fc1_uom, :fc1_whscode,:fc1_price, :fc1_vat, :fc1_vatsum, :fc1_discount, :fc1_linetotal, :fc1_costcode, :fc1_ubusiness, :fc1_project,
                                    :fc1_acctcode, :fc1_basetype, :fc1_doctype, :fc1_avprice, :fc1_inventory, :fc1_acciva, :fc1_linenum,:fc1_codimp, 
									:fc1_ubication,:fc1_baseline,:ote_code)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':fc1_docentry' => $resInsert,
					':fc1_itemcode' => isset($detail['fc1_itemcode']) ? $detail['fc1_itemcode'] : NULL,
					':fc1_itemname' => isset($detail['fc1_itemname']) ? $detail['fc1_itemname'] : NULL,
					':fc1_quantity' => is_numeric($detail['fc1_quantity']) ? $detail['fc1_quantity'] : 0,
					':fc1_uom' => isset($detail['fc1_uom']) ? $detail['fc1_uom'] : NULL,
					':fc1_whscode' => isset($detail['fc1_whscode']) ? $detail['fc1_whscode'] : NULL,
					':fc1_price' => is_numeric($detail['fc1_price']) ? $detail['fc1_price'] : 0,
					':fc1_vat' => is_numeric($detail['fc1_vat']) ? $detail['fc1_vat'] : 0,
					':fc1_vatsum' => is_numeric($detail['fc1_vatsum']) ? $detail['fc1_vatsum'] : 0,
					':fc1_discount' => is_numeric($detail['fc1_discount']) ? $detail['fc1_discount'] : 0,
					':fc1_linetotal' => is_numeric($detail['fc1_linetotal']) ? $detail['fc1_linetotal'] : 0,
					':fc1_costcode' => isset($detail['fc1_costcode']) ? $detail['fc1_costcode'] : NULL,
					':fc1_ubusiness' => isset($detail['fc1_ubusiness']) ? $detail['fc1_ubusiness'] : NULL,
					':fc1_project' => isset($detail['fc1_project']) ? $detail['fc1_project'] : NULL,
					':fc1_acctcode' => is_numeric($detail['fc1_acctcode']) ? $detail['fc1_acctcode'] : 0,
					':fc1_basetype' => is_numeric($detail['fc1_basetype']) ? $detail['fc1_basetype'] : 0,
					':fc1_doctype' => is_numeric($detail['fc1_doctype']) ? $detail['fc1_doctype'] : 0,
					':fc1_avprice' => is_numeric($detail['fc1_avprice']) ? $detail['fc1_avprice'] : 0,
					':fc1_inventory' => is_numeric($detail['fc1_inventory']) ? $detail['fc1_inventory'] : NULL,
					':fc1_acciva'  => is_numeric($detail['fc1_cuentaIva']) ? $detail['fc1_cuentaIva'] : 0,
					':fc1_linenum'  => is_numeric($detail['fc1_linenum']) ? $detail['fc1_linenum'] : 0,
					':fc1_codimp'  => isset($detail['fc1_codimp']) ? $detail['fc1_codimp'] : NULL,
					':fc1_ubication'  => isset($detail['fc1_ubication']) ? $detail['fc1_ubication'] : NULL,
					':fc1_baseline' => is_numeric($detail['fc1_baseline']) ? $detail['fc1_baseline'] : 0,
					':ote_code'  => isset($detail['ote_code']) ? $detail['ote_code'] : NULL
				));

				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// Se verifica que el detalle no de error insertando //
					//VALIDAR SI LOS ITEMS SON IGUALES A LOS DEL DOCUMENTO DE ORIGEN SIEMPRE QUE VENGA DE UN COPIAR DE
					if($Data['cfc_basetype'] == 12){
						//OBTENER NUMERO DOCUMENTO ORIGEN
						$DOC = "SELECT cpo_docnum FROM dcpo WHERE cpo_doctype = :cpo_doctype AND cpo_docentry = :cpo_docentry";
						$RESULT_DOC = $this->pedeo->queryTable($DOC,array(':cpo_docentry' =>$Data['cfc_baseentry'],':cpo_doctype' => $Data['cfc_basetype']));
						foreach ($ContenidoDetalle as $key => $value) {
							# code...
							//VALIDAR SI EL ARTICULO DEL DOCUMENTO ACTUAL EXISTE EN EL DOCUMENTO DE ORIGEN
							$sql = "SELECT dcpo.cpo_docnum,cpo1.po1_itemcode FROM dcpo INNER JOIN cpo1 ON dcpo.cpo_docentry = cpo1.po1_docentry 
							WHERE dcpo.cpo_docentry = :cpo_docentry AND dcpo.cpo_doctype = :cpo_doctype AND cpo1.po1_itemcode = :po1_itemcode";
							$resSql = $this->pedeo->queryTable($sql,array(
								':cpo_docentry' =>$Data['cfc_baseentry'],
								':cpo_doctype' => $Data['cfc_basetype'],
								':po1_itemcode' => $value['fc1_itemcode']
							));
							
								if(isset($resSql[0])){
									//EL ARTICULO EXISTE EN EL DOCUMENTO DE ORIGEN
								}else {
									//EL ARTICULO NO EXISTE EN EL DOCUEMENTO DE ORIGEN
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data' => $value['fc1_itemcode'],
										'mensaje'	=> 'El Item '.$value['fc1_itemcode'].' no existe en el documento origen (Orden #'.$RESULT_DOC[0]['cpo_docnum'].')'
									);

									$this->response($respuesta);

									return;
								}
							}

					}else if($Data['cfc_basetype'] == 13){
						//OBTENER NUMERO DOCUMENTO ORIGEN
						$DOC = "SELECT cec_docnum FROM dcec WHERE cec_doctype = :cec_doctype AND cec_docentry = :cec_docentry";
						$RESULT_DOC = $this->pedeo->queryTable($DOC,array(':cec_docentry' =>$Data['cfc_baseentry'],':cec_doctype' => $Data['cfc_basetype']));
						foreach ($ContenidoDetalle as $key => $value) {
							# code...
							//VALIDAR SI EL ARTICULO DEL DOCUMENTO ACTUAL EXISTE EN EL DOCUMENTO DE ORIGEN
							$sql = "SELECT dcec.cec_docnum,cec1.ec1_itemcode FROM dcec INNER JOIN cec1 ON dcec.cec_docentry = cec1.ec1_docentry 
							WHERE dcec.cec_docentry = :cec_docentry AND dcec.cec_doctype = :cec_doctype AND cec1.ec1_itemcode = :ec1_itemcode";
							$resSql = $this->pedeo->queryTable($sql,array(
								':cec_docentry' =>$Data['cfc_baseentry'],
								':cec_doctype' => $Data['cfc_basetype'],
								':ec1_itemcode' => $value['fc1_itemcode']
							));
							
								if(isset($resSql[0])){
									//EL ARTICULO EXISTE EN EL DOCUMENTO DE ORIGEN
								}else {
									//EL ARTICULO NO EXISTE EN EL DOCUEMENTO DE ORIGEN
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data' => $value['fc1_itemcode'],
										'mensaje'	=> 'El Item '.$value['fc1_itemcode'].' no existe en el documento origen (Entrada #'.$RESULT_DOC[0]['cec_docnum'].')'
									);

									$this->response($respuesta);

									return;
								}
							}

					}
				} else {

					// si falla algun insert del detalle de la factura de compras se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertDetail,
						'mensaje'	=> 'No se pudo registrar la factura de compras'
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

								$sqlInsertRetenciones = "INSERT INTO fcrt(crt_baseentry, crt_basetype, crt_typert, crt_basert, crt_profitrt, crt_totalrt, crt_base, crt_type, crt_linenum, crt_codret)
														VALUES (:crt_baseentry, :crt_basetype, :crt_typert, :crt_basert, :crt_profitrt, :crt_totalrt, :crt_base, :crt_type, :crt_linenum, :crt_codret)";

								$resInsertRetenciones = $this->pedeo->insertRow($sqlInsertRetenciones, array(

									':crt_baseentry' => $resInsert,
									':crt_basetype'  => $Data['cfc_doctype'],
									':crt_typert'    => $value['crt_typert'],
									':crt_basert'    => $value['crt_basert'],
									':crt_profitrt'  => $value['crt_profitrt'],
									':crt_totalrt'   => $value['crt_totalrt'],
									':crt_base'		 => $value['crt_base'],
									':crt_type'		 => $value['crt_type'],
									':crt_linenum'   => $detail['fc1_linenum'],
									':crt_codret'	 => $value['crt_codret']
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
				// Y A SU VES SI MANEJA LOTE
				$sqlItemINV = "SELECT dma_item_inv FROM dmar WHERE dma_item_code = :dma_item_code AND dma_item_inv = :dma_item_inv";
				$resItemINV = $this->pedeo->queryTable($sqlItemINV, array(

					':dma_item_code' => $detail['fc1_itemcode'],
					':dma_item_inv'  => 1
				));

				if (isset($resItemINV[0])) {
					$ManejaInvetario = 1;


					// CONSULTA PARA VERIFICAR SI EL ALMACEN MANEJA UBICACION
					$sqlubicacion = "SELECT * FROM dmws WHERE dws_ubication = :dws_ubication AND dws_code = :dws_code AND business = :business";
					$resubicacion = $this->pedeo->queryTable($sqlubicacion, array(
						':dws_ubication' => 1,
						':dws_code' => $detail['fc1_whscode'],
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

					':dma_item_code' => $detail['fc1_itemcode'],
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
				if ($ManejaInvetario == 1) {

					// se verifica de donde viene  el documento
					// si el documento no viene de una entrada de COMPRAS
					// se hace todo el proceso
					if ($Data['cfc_basetype'] != 13) {

						//SE VERIFICA SI EL ARTICULO MANEJA SERIAL
						$sqlItemSerial = "SELECT dma_series_code FROM dmar WHERE  dma_item_code = :dma_item_code AND dma_series_code = :dma_series_code";
						$resItemSerial = $this->pedeo->queryTable($sqlItemSerial, array(

							':dma_item_code' => $detail['fc1_itemcode'],
							':dma_series_code'  => 1
						));

						if (isset($resItemSerial[0])) {
							$ManejaSerial = 1;

							$AddSerial = $this->generic->addSerial($detail['serials'], $detail['fc1_itemcode'], $Data['cfc_doctype'], $resInsert, $DocNumVerificado, $Data['cfc_docdate'], 1, $Data['cfc_comment'], $detail['fc1_whscode'], $detail['fc1_quantity'], $Data['cfc_createby'], $resInsertDetail, $Data['business']);

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

						//se busca el costo del item en el momento de la creacion del documento de venta
						// para almacenar en el movimiento de inventario


						// SI EL ALMACEN MANEJA UBICACION
						if ( $ManejaUbicacion == 1 ){
							if ($ManejaLote == 1) {
								$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_lote = :bdi_lote AND bdi_ubication = :bdi_ubication AND business = :business";
								$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
									':bdi_whscode' => $detail['fc1_whscode'],
									':bdi_itemcode' => $detail['fc1_itemcode'],
									':bdi_lote' => $detail['ote_code'],
									':bdi_ubication' => $detail['fc1_ubication'],
									':business' => $Data['business']
								));
							}else{
								$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_ubication = :bdi_ubication AND business = :business";
								$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
									':bdi_whscode' => $detail['fc1_whscode'],
									':bdi_itemcode' => $detail['fc1_itemcode'],
									':bdi_ubication' => $detail['fc1_ubication'],
									':business' => $Data['business']
								));
							}
						}else{
							//SI MANEJA LOTE
							if ($ManejaLote == 1) {
								$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_lote = :bdi_lote AND business = :business";
								$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
									':bdi_whscode' => $detail['fc1_whscode'],
									':bdi_itemcode' => $detail['fc1_itemcode'],
									':bdi_lote' => $detail['ote_code'],
									':business' => $Data['business']
								));
							} else {
								$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND business = :business";
								$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(':bdi_whscode' => $detail['fc1_whscode'], ':bdi_itemcode' => $detail['fc1_itemcode'], ':business' => $Data['business']));
							}
						}


						$CostoArticuloMv = (($detail['fc1_price'] / $CANTUOMPURCHASE) * $CANTUOMSALE);
						$CantidadMBI = 0;

						if (isset($resCostoMomentoRegistro[0])) {
							$CostoArticuloMv = $resCostoMomentoRegistro[0]['bdi_avgprice'];
							$CantidadMBI = $resCostoMomentoRegistro[0]['bdi_quantity'];
						}

						$sqlInserMovimiento = '';
						$resInserMovimiento = [];
						//SI EL ARTICULO MANEJA LOTE
					
						//Se aplica el movimiento de inventario
						$sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode,bmi_quantity,bmi_whscode,bmi_createat,bmi_createby,bmy_doctype,bmy_baseentry,bmi_cost,bmi_currequantity,bmi_basenum,bmi_docdate,bmi_duedate,bmi_duedev,bmi_comment,bmi_lote,bmi_ubication)
											VALUES (:bmi_itemcode,:bmi_quantity, :bmi_whscode,:bmi_createat,:bmi_createby,:bmy_doctype,:bmy_baseentry,:bmi_cost,:bmi_currequantity,:bmi_basenum,:bmi_docdate,:bmi_duedate,:bmi_duedev,:bmi_comment,:bmi_lote,:bmi_ubication)";

						$resInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(

							':bmi_itemcode' => isset($detail['fc1_itemcode']) ? $detail['fc1_itemcode'] : NULL,
							':bmi_quantity' => ($this->generic->getCantInv($detail['fc1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE) * $Data['invtype']),
							':bmi_whscode'  => isset($detail['fc1_whscode']) ? $detail['fc1_whscode'] : NULL,
							':bmi_createat' => $this->validateDate($Data['cfc_createat']) ? $Data['cfc_createat'] : NULL,
							':bmi_createby' => isset($Data['cfc_createby']) ? $Data['cfc_createby'] : NULL,
							':bmy_doctype'  => is_numeric($Data['cfc_doctype']) ? $Data['cfc_doctype'] : 0,
							':bmy_baseentry' => $resInsert,
							':bmi_cost'      => $CostoArticuloMv,
							':bmi_currequantity' 	=> $CantidadMBI,
							':bmi_basenum'			=> $DocNumVerificado,
							':bmi_docdate' => $this->validateDate($Data['cfc_docdate']) ? $Data['cfc_docdate'] : NULL,
							':bmi_duedate' => $this->validateDate($Data['cfc_duedate']) ? $Data['cfc_duedate'] : NULL,
							':bmi_duedev'  => $this->validateDate($Data['cfc_duedev']) ? $Data['cfc_duedev'] : NULL,
							':bmi_comment' => isset($Data['cfc_comment']) ? $Data['cfc_comment'] : NULL,
							':bmi_lote' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
							':bmi_ubication' => isset($detail['fc1_ubication']) ? $detail['fc1_ubication'] : NULL

						));
					
							



						if (is_numeric($resInserMovimiento) && $resInserMovimiento > 0) {
							// Se verifica que el detalle no de error insertando //
						} else {

							// si falla algun insert del detalle de la factura de compras se devuelven los cambios realizados por la transaccion,
							// se retorna el error y se detiene la ejecucion del codigo restante.
							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' => $resInserMovimiento,
								'mensaje'	=> 'No se pudo registrar la factura de compras'
							);

							$this->response($respuesta);

							return;
						}
						//FIN aplicacion de movimiento de inventario



						if ($ManejaInvetario == 1) {
							// Se Aplica el movimiento en stock ***************
							// Buscando item en el stock
							$sqlCostoCantidad = '';
							$resCostoCantidad = [];
							$CantidadPorAlmacen = 0;
							$CostoPorAlmacen = 0;

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

										':bdi_itemcode'  => $detail['fc1_itemcode'],
										':bdi_whscode'   => $detail['fc1_whscode'],
										':bdi_lote' 	 => $detail['ote_code'],
										':bdi_ubication' => $detail['fc1_ubication'],
										':business'		 => $Data['business']
									));
									// se busca la cantidad general del articulo agrupando todos los almacenes y lotes
									$sqlCGA = "SELECT sum(COALESCE(bdi_quantity, 0)) as bdi_quantity, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode AND business = :business GROUP BY bdi_whscode, bdi_avgprice";
									$resCGA = $this->pedeo->queryTable($sqlCGA, array(
										':bdi_itemcode' => $detail['fc1_itemcode'],
										':bdi_whscode'  => $detail['fc1_whscode'],
										':business' 	=> $Data['business']
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
									AND bdi_ubication = :bdi_ubication
									AND business = :business";


									$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(

										':bdi_itemcode'  => $detail['fc1_itemcode'],
										':bdi_whscode'   => $detail['fc1_whscode'],
										':bdi_ubication' => $detail['fc1_ubication'],
										':business'		 => $Data['business']
									));
									// se busca la cantidad general del articulo agrupando todos los almacenes y lotes
									$sqlCGA = "SELECT sum(COALESCE(bdi_quantity, 0)) as bdi_quantity, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode AND business = :business GROUP BY bdi_whscode, bdi_avgprice";
									$resCGA = $this->pedeo->queryTable($sqlCGA, array(
										':bdi_itemcode' => $detail['fc1_itemcode'],
										':bdi_whscode'  => $detail['fc1_whscode'],
										':business'		=> $Data['business']

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
									//SE VALIDA SI EL ARTICULO MANEJA LOTE
									if ($ManejaLote == 1) {
										$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
															FROM tbdi
															WHERE bdi_itemcode = :bdi_itemcode
															AND bdi_whscode = :bdi_whscode
															AND bdi_lote = :bdi_lote
															AND business = :business";
															

										$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(

											':bdi_itemcode' => $detail['fc1_itemcode'],
											':bdi_whscode'  => $detail['fc1_whscode'],
											':bdi_lote' 	=> $detail['ote_code'],
											':business' 	=> $Data['business']

										));
										// se busca la cantidad general del articulo agrupando todos los almacenes y lotes
										$sqlCGA = "SELECT sum(COALESCE(bdi_quantity, 0)) as bdi_quantity, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode AND business = :business GROUP BY bdi_whscode, bdi_avgprice";
										$resCGA = $this->pedeo->queryTable($sqlCGA, array(
											':bdi_itemcode' => $detail['fc1_itemcode'],
											':bdi_whscode'  => $detail['fc1_whscode'],
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
															AND business = :business";

										$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(

											':bdi_itemcode' => $detail['fc1_itemcode'],
											':bdi_whscode'  => $detail['fc1_whscode'],
											':business' 	=> $Data['business']
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

									$CantidadNueva = $this->generic->getCantInv($detail['fc1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE);
									//SE CALCULA EL PRECIO SEGUN LA CONVERSION DE UNIDADES
									$CostoNuevo = (($detail['fc1_price'] / $CANTUOMPURCHASE) * $CANTUOMSALE);
									//
									$CantidadTotal = ($CantidadActual + $CantidadNueva);
									$CantidadTotalItemSolo = ($CantidadItem + $CantidadNueva);


									if (trim($Data['cfc_currency']) != $MONEDALOCAL) {
										$CostoNuevo = ($CostoNuevo * $TasaDocLoc);
									}

									$NuevoCostoPonderado = ($CantidadActual  *  $CostoActual) + ($CantidadNueva * $CostoNuevo);
									$NuevoCostoPonderado = round(($NuevoCostoPonderado / $CantidadTotal), $DECI_MALES);

									$sqlUpdateCostoCantidad =  "UPDATE tbdi
																SET bdi_quantity = :bdi_quantity
																,bdi_avgprice = :bdi_avgprice
																WHERE  bdi_id = :bdi_id
																AND business = :business";

									$resUpdateCostoCantidad = $this->pedeo->updateRow($sqlUpdateCostoCantidad, array(

										':bdi_quantity' => $CantidadTotalItemSolo,
										':bdi_avgprice' => $NuevoCostoPonderado,
										':bdi_id' 		=> $resCostoCantidad[0]['bdi_id'],
										':business' 	=> $Data['business']
									));

									if (is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad == 1) {
									} else {

										$this->pedeo->trans_rollback();

										$respuesta = array(
											'error'   => true,
											'data'    => $resUpdateCostoCantidad,
											'mensaje'	=> 'No se pudo crear la factura de compras'
										);
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
										':bdi_itemcode' => $detail['fc1_itemcode'],
										':bdi_whscode'  => $detail['fc1_whscode'],
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
											':bdi_itemcode' => $detail['fc1_itemcode'],
											':bdi_whscode'  => $detail['fc1_whscode'],
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
									$CantidadNueva = $this->generic->getCantInv($detail['fc1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE);
									//SE CALCULA EL PRECIO SEGUN LA CONVERSION DE UNIDADES
									$CostoNuevo = (($detail['fc1_price'] / $CANTUOMPURCHASE) * $CANTUOMSALE);
									//
									$CantidadTotal = ($CantidadActual + $CantidadNueva);

									if (trim($Data['cfc_currency']) != $MONEDALOCAL) {
										$CostoNuevo = ($CostoNuevo * $TasaDocLoc);
									}

									$sqlUpdateCostoCantidad = "UPDATE tbdi
															SET bdi_quantity = :bdi_quantity
															,bdi_avgprice = :bdi_avgprice
															WHERE  bdi_id = :bdi_id
															AND business = :business";

									$resUpdateCostoCantidad = $this->pedeo->updateRow($sqlUpdateCostoCantidad, array(

										':bdi_quantity' => $CantidadTotal,
										':bdi_avgprice' => $CostoNuevo,
										':bdi_id' 	    => $resCostoCantidad[0]['bdi_id'],
										':business' 	=> $Data['business']
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
										':bdi_itemcode' => $detail['fc1_itemcode'],
										':bdi_whscode'  => $detail['fc1_whscode'],
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
											':bdi_itemcode' => $detail['fc1_itemcode'],
											':bdi_whscode'  => $detail['fc1_whscode'],
											':business' => $Data['business']
										));

										if (is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad  > 0) {
										} else {

											$this->pedeo->trans_rollback();

											$respuesta = array(
												'error'   => true,
												'data'    => $resUpdateCostoCantidad,
												'mensaje'	=> 'No se pudo crear la Factura de compras'
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
									//SE CALCULA EL PRECIO SEGUN LA CONVERSION DE UNIDADES
									$CostoNuevo = (($detail['fc1_price'] / $CANTUOMPURCHASE) * $CANTUOMSALE);
									//
									$CantidadItem = 0;
									$CantidadActual = $CantidadPorAlmacen;
									$CostoActual = $CostoPorAlmacen;

									$CantidadNueva = $this->generic->getCantInv($detail['fc1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE);

									$CantidadTotal = ($CantidadActual + $CantidadNueva);
									$CantidadTotalItemSolo = ($CantidadItem + $CantidadNueva);

									if (trim($Data['cfc_currency']) != $MONEDALOCAL) {
										$CostoNuevo = ($CostoNuevo * $TasaDocLoc);
									}

									$NuevoCostoPonderado = ($CantidadActual  *  $CostoActual) + ($CantidadNueva * $CostoNuevo);
									$NuevoCostoPonderado = round(($NuevoCostoPonderado / $CantidadTotal), $DECI_MALES);

									
									$sqlInsertCostoCantidad = '';
									$resInsertCostoCantidad =	[];

									// SI EL ALMACEN MANEJA UBICACION
									if( $ManejaUbicacion == 1 ){
										//SE VALIDA SI EL ARTICULO MANEJA LOTE
										if ($ManejaLote == 1) {
											$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_lote, bdi_ubication, business)
											VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_lote, :bdi_ubication, :business)";


											$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

												':bdi_itemcode' => $detail['fc1_itemcode'],
												':bdi_whscode'  => $detail['fc1_whscode'],
												':bdi_quantity' => ($detail['fc1_quantity'] * $CANTUOMPURCHASE),
												':bdi_avgprice' => $NuevoCostoPonderado,
												':bdi_lote' 	=> $detail['ote_code'],
												':bdi_ubication' => $detail['fc1_ubication'],
												':business' 	=> $Data['business']
											));
										}else{
											$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_ubication, business)
											VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_ubication, :business)";


											$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

												':bdi_itemcode' => $detail['fc1_itemcode'],
												':bdi_whscode'  => $detail['fc1_whscode'],
												':bdi_quantity' => ($detail['fc1_quantity'] * $CANTUOMPURCHASE),
												':bdi_avgprice' => $NuevoCostoPonderado,
												':bdi_ubication' => $detail['fc1_ubication'],
												':business' => $Data['business']
											));
										}


									}else{
										//SE VALIDA SI EL ARTICULO MANEJA LOTE
										if ($ManejaLote == 1) {
											$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_lote, business)
																	VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_lote, :business)";


											$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

												':bdi_itemcode' => $detail['fc1_itemcode'],
												':bdi_whscode'  => $detail['fc1_whscode'],
												':bdi_quantity' => ($detail['fc1_quantity'] * $CANTUOMPURCHASE),
												':bdi_avgprice' => $NuevoCostoPonderado,
												':bdi_lote' 	=> $detail['ote_code'],
												':business'		=> $Data['business']
											));
										} else {
											$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, business)
																	VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :business)";


											$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

												':bdi_itemcode' => $detail['fc1_itemcode'],
												':bdi_whscode'  => $detail['fc1_whscode'],
												':bdi_quantity' => ($detail['fc1_quantity'] * $CANTUOMPURCHASE),
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
											'mensaje'	=> 'No se pudo registrar la Factura de Compra'
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
										':bdi_itemcode' => $detail['fc1_itemcode'],
										':bdi_whscode'  => $detail['fc1_whscode'],
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
											':bdi_itemcode' => $detail['fc1_itemcode'],
											':bdi_whscode'  => $detail['fc1_whscode'],
											':business' => $Data['business']
										));



										if (is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad > 0) {
										} else {

											$this->pedeo->trans_rollback();

											$respuesta = array(
												'error'   => true,
												'data'    => $resUpdateCostoCantidad,
												'mensaje'	=> 'No se pudo crear la Factura de Compras'
											);

											$this->response($respuesta);

											return;
										}
									}
								} else {
									//SE CALCULA EL PRECIO SEGUN LA CONVERSION DE UNIDADES
									$CostoNuevo = (($detail['fc1_price'] / $CANTUOMPURCHASE) * $CANTUOMSALE);
									//

									if (trim($Data['cfc_currency']) != $MONEDALOCAL) {
										$CostoNuevo = ($CostoNuevo * $TasaDocLoc);
									}

									
									$sqlInsertCostoCantidad = '';
									$resInsertCostoCantidad =	[];

									// SI EL ALMACEN MANEJA UBICAION
									if ( $ManejaUbicacion == 1 ){
										// SI SE MANEJA LOTE
										if ($ManejaLote == 1) {
											$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_lote, bdi_ubication, business)
																	VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_lote, :bdi_ubication, :business)";


											$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

												':bdi_itemcode'  => $detail['fc1_itemcode'],
												':bdi_whscode'   => $detail['fc1_whscode'],
												':bdi_quantity'  => $this->generic->getCantInv($detail['fc1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
												':bdi_avgprice'  => $CostoNuevo,
												':bdi_lote' 	 => $detail['ote_code'],
												':bdi_ubication' => $detail['fc1_ubication'],
												':business' 	 => $Data['business']
											));
										}else{
											$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_ubication, business)
																	VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_ubication, :business)";


											$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

												':bdi_itemcode'  => $detail['fc1_itemcode'],
												':bdi_whscode'   => $detail['fc1_whscode'],
												':bdi_quantity'  => $this->generic->getCantInv($detail['fc1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
												':bdi_avgprice'  => $CostoNuevo,
												':bdi_ubication' => $detail['fc1_ubication'],
												':business' => $Data['business']
											));
										}
									}else{
										//SE VALIDA SI EL ARTICULO MANEJA LOTE
										if ($ManejaLote == 1) {
											$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_lote, business)
																	VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_lote, :business)";


											$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

												':bdi_itemcode' => $detail['fc1_itemcode'],
												':bdi_whscode'  => $detail['fc1_whscode'],
												':bdi_quantity' => $this->generic->getCantInv($detail['fc1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
												':bdi_avgprice' => $CostoNuevo,
												':bdi_lote' 	=> $detail['ote_code'],
												':business'	 	=> $Data['business']
											));
										} else {

											$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, business)
																	VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :business)";


											$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

												':bdi_itemcode' => $detail['fc1_itemcode'],
												':bdi_whscode'  => $detail['fc1_whscode'],
												':bdi_quantity' => $this->generic->getCantInv($detail['fc1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
												':bdi_avgprice' => $CostoNuevo,
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
											'mensaje'	=> 'No se pudo registrar la Factura de Compras'
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
										':bdi_itemcode' => $detail['fc1_itemcode'],
										':bdi_whscode'  => $detail['fc1_whscode'],
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
											':bdi_itemcode' => $detail['fc1_itemcode'],
											':bdi_whscode'  => $detail['fc1_whscode'],
											':business' => $Data['business']
										));



										if (is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad > 0) {
										} else {

											$this->pedeo->trans_rollback();

											$respuesta = array(
												'error'   => true,
												'data'    => $resUpdateCostoCantidad,
												'mensaje' => 'No se pudo crear la Factura de Compras'
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
										':ote_createby' => $Data['cfc_createby'],
										':ote_date' => date('Y-m-d'),
										':ote_baseentry' => $resInsert,
										':ote_basetype' => $Data['cfc_doctype'],
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
						// EN CASO CONTRARIO NO SE MUEVE INVENTARIO
						// ADICIONAL SE VERIFICA SI EL ARTICULO MANEJA SERIAL
					} else {
						//SE VERIFICA SI EL ARTICULO MANEJA SERIAL
						$sqlItemSerial = "SELECT dma_series_code FROM dmar WHERE  dma_item_code = :dma_item_code AND dma_series_code = :dma_series_code";
						$resItemSerial = $this->pedeo->queryTable($sqlItemSerial, array(

							':dma_item_code' => $detail['fc1_itemcode'],
							':dma_series_code'  => 1
						));

						if (isset($resItemSerial[0])) {
							$ManejaSerial = 1;

							$AddSerial = $this->generic->addSerial($detail['serials'], $detail['fc1_itemcode'], $Data['cfc_doctype'], $resInsert, $DocNumVerificado, $Data['cfc_docdate'], 1, $Data['cfc_comment'], $detail['fc1_whscode'], $detail['fc1_quantity'], $Data['cfc_createby'], $resInsertDetail, $Data['business']);

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
					}
				}

				// se valida si se esta usando factor de conversion en alguna de las lineas del documento
				if (!$FactorC){

					foreach ($ContenidoDetalle as $key => $detail) {

						$sqlFactorC = "SELECT * FROM dmtx WHERE dmi_type = :dmi_type AND dmi_use_fc = :dmi_use_fc AND dmi_code = :dmi_code";
						$resFactorC = $this->pedeo->queryTable($sqlFactorC, array(':dmi_type' => '2', ':dmi_use_fc' => 1, ':dmi_code' => $detail['fc1_codimp']));

						if (isset($resFactorC[0])){
							$FactorC =  true;
							break;
						}
					}
				}
				//

				//LLENANDO DETALLE ASIENTO CONTABLES
				$DetalleAsientoIngreso = new stdClass();
				$DetalleAsientoIva = new stdClass();
				$DetalleCostoInventario = new stdClass();
				$DetalleCostoCosto = new stdClass();
				$DetalleItemNoInventariable = new stdClass();


				// ESTO SOLO APLICA PARA CUANDO SE MANEJA IMPUESTO CON FACTOR DE CONVERSION
				// CASO PARA BOLIVIA
				if ($FactorC) {

					if ( isset( $detail['fc1_discount'] ) &&  isset( $detail['fc1_discount'] ) && $detail['fc1_discount'] > 0 ) {
						
						$DetalleAsientoDescuento = new stdClass();
						$DetalleAsientoIvaDescuento = new stdClass();

						$DetalleAsientoDescuento->descuento = $detail['fc1_discount'] - ( ( ( $detail['fc1_discount'] * $detail['fc1_vat'] ) ) / 100 ) ;

						$DescuentoAcumulado = $DescuentoAcumulado + $DetalleAsientoDescuento->descuento;


						$DetalleAsientoIvaDescuento->ivadescuento =  (  $detail['fc1_discount'] * $detail['fc1_vat'] ) / 100 ;


						$IvaDescuentoAcumulado = $IvaDescuentoAcumulado + $DetalleAsientoIvaDescuento->ivadescuento;


						array_push($DetalleConsolidadoDescuento, $DetalleAsientoDescuento);
						array_push($DetalleConsolidadoIvaDescuento, $DetalleAsientoIvaDescuento);

					}

				}
				//

				$DetalleAsientoIngreso->ac1_account = is_numeric($detail['fc1_acctcode']) ? $detail['fc1_acctcode'] : 0;
				$DetalleAsientoIngreso->ac1_prc_code = isset($detail['fc1_costcode']) ? $detail['fc1_costcode'] : NULL;
				$DetalleAsientoIngreso->ac1_uncode = isset($detail['fc1_ubusiness']) ? $detail['fc1_ubusiness'] : NULL;
				$DetalleAsientoIngreso->ac1_prj_code = isset($detail['fc1_project']) ? $detail['fc1_project'] : NULL;
				$DetalleAsientoIngreso->fc1_linetotal = is_numeric($detail['fc1_linetotal']) ? $detail['fc1_linetotal'] : 0;
				$DetalleAsientoIngreso->fc1_vat = is_numeric($detail['fc1_vat']) ? $detail['fc1_vat'] : 0;
				$DetalleAsientoIngreso->fc1_vatsum = is_numeric($detail['fc1_vatsum']) ? $detail['fc1_vatsum'] : 0;
				$DetalleAsientoIngreso->fc1_price = is_numeric($detail['fc1_price']) ? $detail['fc1_price'] : 0;
				$DetalleAsientoIngreso->fc1_itemcode = isset($detail['fc1_itemcode']) ? $detail['fc1_itemcode'] : NULL;
				$DetalleAsientoIngreso->fc1_quantity = is_numeric($detail['fc1_quantity']) ? ($detail['fc1_quantity'] * $CANTUOMPURCHASE) : 0;
				$DetalleAsientoIngreso->ec1_whscode = isset($detail['fc1_whscode']) ? $detail['fc1_whscode'] : NULL;



				$DetalleAsientoIva->ac1_account = is_numeric($detail['fc1_acctcode']) ? $detail['fc1_acctcode'] : 0;
				$DetalleAsientoIva->ac1_prc_code = isset($detail['fc1_costcode']) ? $detail['fc1_costcode'] : NULL;
				$DetalleAsientoIva->ac1_uncode = isset($detail['fc1_ubusiness']) ? $detail['fc1_ubusiness'] : NULL;
				$DetalleAsientoIva->ac1_prj_code = isset($detail['fc1_project']) ? $detail['fc1_project'] : NULL;
				$DetalleAsientoIva->fc1_linetotal = is_numeric($detail['fc1_linetotal']) ? $detail['fc1_linetotal'] : 0;
				$DetalleAsientoIva->fc1_vat = is_numeric($detail['fc1_vat']) ? $detail['fc1_vat'] : 0;
				$DetalleAsientoIva->fc1_vatsum = is_numeric($detail['fc1_vatsum']) ? $detail['fc1_vatsum'] : 0;
				$DetalleAsientoIva->fc1_price = is_numeric($detail['fc1_price']) ? $detail['fc1_price'] : 0;
				$DetalleAsientoIva->fc1_itemcode = isset($detail['fc1_itemcode']) ? $detail['fc1_itemcode'] : NULL;
				$DetalleAsientoIva->fc1_quantity = is_numeric($detail['fc1_quantity']) ? ($detail['fc1_quantity'] * $CANTUOMPURCHASE) : 0;
				$DetalleAsientoIva->fc1_cuentaIva = is_numeric($detail['fc1_cuentaIva']) ? $detail['fc1_cuentaIva'] : NULL;
				$DetalleAsientoIva->ec1_whscode = isset($detail['fc1_whscode']) ? $detail['fc1_whscode'] : NULL;
				$DetalleAsientoIva->codimp = isset($detail['fc1_codimp']) ? $detail['fc1_codimp'] : NULL;



				// se busca la cuenta contable del costoInventario y costoCosto
				$sqlArticulo = "SELECT f2.dma_item_code,  f1.mga_acct_inv, f1.mga_acct_cost, f1.mga_acct_out FROM dmga f1 JOIN dmar f2 ON f1.mga_id  = f2.dma_group_code WHERE dma_item_code = :dma_item_code";

				$resArticulo = $this->pedeo->queryTable($sqlArticulo, array(":dma_item_code" => $detail['fc1_itemcode']));

				if (!isset($resArticulo[0])) {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resArticulo,
						'mensaje'	=> 'No se pudo registrar la factura de compras'
					);

					$this->response($respuesta);

					return;
				}



				if ($ManejaInvetario == 1) {

					$DetalleCostoInventario->ac1_account = is_numeric($detail['fc1_acctcode']) ? $detail['fc1_acctcode'] : 0;
					$DetalleCostoInventario->ac1_prc_code = isset($detail['fc1_costcode']) ? $detail['fc1_costcode'] : NULL;
					$DetalleCostoInventario->ac1_uncode = isset($detail['fc1_ubusiness']) ? $detail['fc1_ubusiness'] : NULL;
					$DetalleCostoInventario->ac1_prj_code = isset($detail['fc1_project']) ? $detail['fc1_project'] : NULL;
					$DetalleCostoInventario->fc1_linetotal = is_numeric($detail['fc1_linetotal']) ? $detail['fc1_linetotal'] : 0;
					$DetalleCostoInventario->fc1_vat = is_numeric($detail['fc1_vat']) ? $detail['fc1_vat'] : 0;
					$DetalleCostoInventario->fc1_vatsum = is_numeric($detail['fc1_vatsum']) ? $detail['fc1_vatsum'] : 0;
					$DetalleCostoInventario->fc1_price = is_numeric($detail['fc1_price']) ? $detail['fc1_price'] : 0;
					$DetalleCostoInventario->fc1_itemcode = isset($detail['fc1_itemcode']) ? $detail['fc1_itemcode'] : NULL;
					$DetalleCostoInventario->fc1_quantity = is_numeric($detail['fc1_quantity']) ? ($detail['fc1_quantity'] * $CANTUOMPURCHASE) : 0;
					$DetalleCostoInventario->ec1_whscode = isset($detail['fc1_whscode']) ? $detail['fc1_whscode'] : NULL;
					$DetalleCostoInventario->fc1_inventory = $ManejaInvetario;


					$DetalleCostoCosto->ac1_account = is_numeric($detail['fc1_acctcode']) ? $detail['fc1_acctcode'] : 0;
					$DetalleCostoCosto->ac1_prc_code = isset($detail['fc1_costcode']) ? $detail['fc1_costcode'] : NULL;
					$DetalleCostoCosto->ac1_uncode = isset($detail['fc1_ubusiness']) ? $detail['fc1_ubusiness'] : NULL;
					$DetalleCostoCosto->ac1_prj_code = isset($detail['fc1_project']) ? $detail['fc1_project'] : NULL;
					$DetalleCostoCosto->fc1_linetotal = is_numeric($detail['fc1_linetotal']) ? $detail['fc1_linetotal'] : 0;
					$DetalleCostoCosto->fc1_vat = is_numeric($detail['fc1_vat']) ? $detail['fc1_vat'] : 0;
					$DetalleCostoCosto->fc1_vatsum = is_numeric($detail['fc1_vatsum']) ? $detail['fc1_vatsum'] : 0;
					$DetalleCostoCosto->fc1_price = is_numeric($detail['fc1_price']) ? $detail['fc1_price'] : 0;
					$DetalleCostoCosto->fc1_itemcode = isset($detail['fc1_itemcode']) ? $detail['fc1_itemcode'] : NULL;
					$DetalleCostoCosto->fc1_quantity = is_numeric($detail['fc1_quantity']) ? ($detail['fc1_quantity'] * $CANTUOMPURCHASE) : 0;
					$DetalleCostoCosto->ec1_whscode = isset($detail['fc1_whscode']) ? $detail['fc1_whscode'] : NULL;
					$DetalleCostoCosto->fc1_inventory = $ManejaInvetario;
				} else {
					$DetalleItemNoInventariable->ac1_account = is_numeric($detail['fc1_acctcode']) ? $detail['fc1_acctcode'] : 0;
					$DetalleItemNoInventariable->ac1_prc_code = isset($detail['fc1_costcode']) ? $detail['fc1_costcode'] : NULL;
					$DetalleItemNoInventariable->ac1_uncode = isset($detail['fc1_ubusiness']) ? $detail['fc1_ubusiness'] : NULL;
					$DetalleItemNoInventariable->ac1_prj_code = isset($detail['fc1_project']) ? $detail['fc1_project'] : NULL;
					$DetalleItemNoInventariable->nc1_linetotal = is_numeric($detail['fc1_linetotal']) ? $detail['fc1_linetotal'] : 0;
					$DetalleItemNoInventariable->nc1_vat = is_numeric($detail['fc1_vat']) ? $detail['fc1_vat'] : 0;
					$DetalleItemNoInventariable->nc1_vatsum = is_numeric($detail['fc1_vatsum']) ? $detail['fc1_vatsum'] : 0;
					$DetalleItemNoInventariable->nc1_price = is_numeric($detail['fc1_price']) ? $detail['fc1_price'] : 0;
					$DetalleItemNoInventariable->nc1_itemcode = isset($detail['fc1_itemcode']) ? $detail['fc1_itemcode'] : NULL;
					$DetalleItemNoInventariable->nc1_quantity = is_numeric($detail['fc1_quantity']) ? ($detail['fc1_quantity'] * $CANTUOMPURCHASE) : 0;
					$DetalleItemNoInventariable->ec1_whscode = isset($detail['fc1_whscode']) ? $detail['fc1_whscode'] : NULL;
				}




				$codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);
				$DetalleAsientoIngreso->codigoCuenta = $codigoCuenta;
				$DetalleAsientoIva->codigoCuenta = $codigoCuenta;

				if ($ManejaInvetario == 1) {
					$DetalleCostoInventario->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);
					$DetalleCostoCosto->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);

					$llaveCostoInventario = $DetalleCostoInventario->ac1_account;
					$llaveCostoCosto = $DetalleCostoCosto->ac1_account;
				} else {
					$llaveItemNoInventariable = $DetalleItemNoInventariable->ac1_uncode . $DetalleItemNoInventariable->ac1_prc_code . $DetalleItemNoInventariable->ac1_prj_code . $DetalleItemNoInventariable->ac1_account;
				}

				$llave = $DetalleAsientoIngreso->ac1_uncode . $DetalleAsientoIngreso->ac1_prc_code . $DetalleAsientoIngreso->ac1_prj_code . $DetalleAsientoIngreso->ac1_account;
				$llaveIva = $DetalleAsientoIva->fc1_vat;



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
				} else {

					if (in_array($llaveItemNoInventariable, $inArrayItemNoInventariable)) {

						$posicionItemNoInventariable = $this->buscarPosicion($llaveItemNoInventariable, $inArrayItemNoInventariable);
					} else {

						array_push($inArrayItemNoInventariable, $llaveItemNoInventariable);
						$posicionItemNoInventariable = $this->buscarPosicion($llaveItemNoInventariable, $inArrayItemNoInventariable);
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

				foreach ($posicion as $key => $value) {
					$granTotalIva = $granTotalIva + $value->fc1_vatsum;
					$Vat = $value->fc1_vat;
					// if( $Vat > 0 ){
					$LineTotal = ($LineTotal + $value->fc1_linetotal);
					// }
					$CodigoImp = $value->codimp;
				}
				// EN BASE A FACTOR DE CONVERSION CASO BOLIVIA
				if ($FactorC) {
					$granTotalIva = $granTotalIva ;

					if ( $DescuentoAcumulado > 0 ){
						$LineTotal = ( $Data['cfc_doctotal']  + $DescuentoAcumulado + $IvaDescuentoAcumulado);
					}
					
				}

				$granTotalIvaOriginal = $granTotalIva;

				if (trim($Data['cfc_currency']) != $MONEDALOCAL) {
					$granTotalIva = ($granTotalIva * $TasaDocLoc);
					$LineTotal = ($LineTotal * $TasaDocLoc);
				}


				if (trim($Data['cfc_currency']) != $MONEDASYS) {

					$MontoSysDB = ($granTotalIva / $TasaLocSys);
				} else {
					$MontoSysDB = $granTotalIvaOriginal;
				}

				$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
				$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));

				$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

					':ac1_trans_id' => $resInsertAsiento,
					':ac1_account' => $value->fc1_cuentaIva,
					':ac1_debit' => round($granTotalIva, $DECI_MALES),
					':ac1_credit' => 0,
					':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
					':ac1_credit_sys' => 0,
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['cfc_docdate']) ? $Data['cfc_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['cfc_duedate']) ? $Data['cfc_duedate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => is_numeric($Data['cfc_doctype']) ? $Data['cfc_doctype'] : 0,
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
					':ac1_made_user' => isset($Data['cfc_createby']) ? $Data['cfc_createby'] : NULL,
					':ac1_accperiod' => $periodo['data'],
					':ac1_close' => 0,
					':ac1_cord' => 0,
					':ac1_ven_debit' => round($granTotalIva, $DECI_MALES),
					':ac1_ven_credit' => 0,
					':ac1_fiscal_acct' => 0,
					':ac1_taxid' => $CodigoImp,
					':ac1_isrti' => $Vat,
					':ac1_basert' => 0,
					':ac1_mmcode' => 0,
					':ac1_legal_num' => isset($Data['cfc_cardcode']) ? $Data['cfc_cardcode'] : NULL,
					':ac1_codref' => 1,
					':ac1_line' => $AC1LINE,
					':ac1_base_tax' => round($LineTotal, $DECI_MALES),
					':business' => $Data['business'],
					':branch' 	=> $Data['branch'],
					':ac1_codret' => NULL
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

			//FIN Procedimiento para llenar Impuestos

			//Procedimiento para llenar costo inventario CUANDO ES FACTURA DIRECTA
			if ($Data['cfc_basetype'] != 13) { // solo si el documento no viene de una entrada
				
				foreach ($DetalleConsolidadoCostoInventario as $key => $posicion) {
					$grantotalCostoInventario = 0;
					$grantotalCostoInventarioOriginal = 0;
					$cuentaInventario = "";
					$sinDatos = 0;
					$dbito = 0;
					$cdito = 0;
					$MontoSysDB = 0;
					$MontoSysCR = 0;
					foreach ($posicion as $key => $value) {

						// SE ACEPTAN SOLO LOS ARTICULOS QUE SON INVENTARIABLES
						// POR TAL MOTIVO SE VERIFICA QUE ESTADO DE _INVENTORI SEA 1
						if ($value->fc1_inventory == 1 || $value->fc1_inventory  == '1') {

							$sinDatos++;
							$cuentaInventario = $value->ac1_account;
							$grantotalCostoInventario = ($grantotalCostoInventario + $value->fc1_linetotal);
						}
					}
					// SE VALIDA QUE EXISTA UN ARTICULO INVENTARIABLE
					// SE COMPRUEBA QUE LA VARIABLE SINDATOS SEA MAYOR A 0
					// CON ESTA SABEMOS QUE ENTRO POR LO MENOS UNA VES EN LA CONDICION
					// ANTERIOR OSEA QUE HAY UN ITEM INVENTARIABLE
					if ($sinDatos > 0) {

						// if ( $FactorC ) {
						// 	$grantotalCostoInventario = $grantotalCostoInventario +  $DescuentoAcumulado;
						// }

						$grantotalCostoInventarioOriginal = $grantotalCostoInventario;

						if (trim($Data['cfc_currency']) != $MONEDALOCAL) {

							$grantotalCostoInventario = ($grantotalCostoInventario * $TasaDocLoc);
						}

						$codigo3 = substr($cuentaInventario, 0, 1);

						if ($codigo3 == 1 || $codigo3 == "1") {
							$dbito = $grantotalCostoInventario;
							if (trim($Data['cfc_currency']) != $MONEDASYS) {
								$MontoSysDB = ($dbito / $TasaLocSys);
							} else {
								$MontoSysDB = ($grantotalCostoInventarioOriginal);
							}
						} else if ($codigo3 == 2 || $codigo3 == "2") {
							$dbito = $grantotalCostoInventario;
							if (trim($Data['cfc_currency']) != $MONEDASYS) {
								$MontoSysDB = ($dbito / $TasaLocSys);
							} else {
								$MontoSysDB = ($grantotalCostoInventarioOriginal);
							}
						} else if ($codigo3 == 3 || $codigo3 == "3") {
							$dbito = $grantotalCostoInventario;
							if (trim($Data['cfc_currency']) != $MONEDASYS) {
								$MontoSysDB = ($dbito / $TasaLocSys);
							} else {
								$MontoSysDB = ($grantotalCostoInventarioOriginal);
							}
						} else if ($codigo3 == 4 || $codigo3 == "4") {
							$dbito = $grantotalCostoInventario;
							if (trim($Data['cfc_currency']) != $MONEDASYS) {
								$MontoSysDB = ($dbito / $TasaLocSys);
							} else {
								$MontoSysDB = ($grantotalCostoInventarioOriginal);
							}
						} else if ($codigo3 == 5  || $codigo3 == "5") {
							$dbito = $grantotalCostoInventario;
							if (trim($Data['cfc_currency']) != $MONEDASYS) {
								$MontoSysDB = ($dbito / $TasaLocSys);
							} else {
								$MontoSysDB = ($grantotalCostoInventarioOriginal);
							}
						} else if ($codigo3 == 6 || $codigo3 == "6") {
							$dbito = $grantotalCostoInventario;
							if (trim($Data['cfc_currency']) != $MONEDASYS) {
								$MontoSysDB = ($dbito / $TasaLocSys);
							} else {
								$MontoSysDB = ($grantotalCostoInventarioOriginal);
							}
						} else if ($codigo3 == 7 || $codigo3 == "7") {
							$dbito = $grantotalCostoInventario;
							if (trim($Data['cfc_currency']) != $MONEDASYS) {
								$MontoSysDB = ($dbito / $TasaLocSys);
							} else {
								$MontoSysDB = ($grantotalCostoInventarioOriginal);
							}
						}

						$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
						$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));


						$AC1LINE = $AC1LINE + 1;
						$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

							':ac1_trans_id' => $resInsertAsiento,
							':ac1_account' => $cuentaInventario,
							':ac1_debit' => round($dbito, $DECI_MALES),
							':ac1_credit' => round($cdito, $DECI_MALES),
							':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
							':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
							':ac1_currex' => 0,
							':ac1_doc_date' => $this->validateDate($Data['cfc_docdate']) ? $Data['cfc_docdate'] : NULL,
							':ac1_doc_duedate' => $this->validateDate($Data['cfc_duedate']) ? $Data['cfc_duedate'] : NULL,
							':ac1_debit_import' => 0,
							':ac1_credit_import' => 0,
							':ac1_debit_importsys' => 0,
							':ac1_credit_importsys' => 0,
							':ac1_font_key' => $resInsert,
							':ac1_font_line' => 1,
							':ac1_font_type' => is_numeric($Data['cfc_doctype']) ? $Data['cfc_doctype'] : 0,
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
							':ac1_made_user' => isset($Data['cfc_createby']) ? $Data['cfc_createby'] : NULL,
							':ac1_accperiod' => 1,
							':ac1_close' => 0,
							':ac1_cord' => 0,
							':ac1_ven_debit' => round($dbito, $DECI_MALES),
							':ac1_ven_credit' => round($cdito, $DECI_MALES),
							':ac1_fiscal_acct' => 0,
							':ac1_taxid' => 0,
							':ac1_isrti' => 0,
							':ac1_basert' => 0,
							':ac1_mmcode' => 0,
							':ac1_legal_num' => isset($Data['cfc_cardcode']) ? $Data['cfc_cardcode'] : NULL,
							':ac1_codref' => 1,
							':ac1_line' => $AC1LINE,
							':ac1_base_tax' => 0,
							':business' => $Data['business'],
							':branch' 	=> $Data['branch'],
							':ac1_codret' => NULL
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
				}
			}
			//FIN Procedimiento para llenar costo inventario CUANDO ES FACTURA DIRECTA

			//Procedimiento para llenar costo inventario Y NO ES FACTURA DIRECTA
			if ($Data['cfc_basetype'] == 13) { // CUANDO VIENE DE UNA ENTRADA
				
				//CUENTA PUENTE DE INVENTARIO

				$sqlcuentainventario = "SELECT coalesce(pge_bridge_inv_purch, 0) as pge_bridge_inv_purch, coalesce(pge_bridge_purch_int, 0) as pge_bridge_purch_int FROM pgem WHERE pge_id = :business";
				$rescuentainventario = $this->pedeo->queryTable($sqlcuentainventario, array(':business' => $Data['business']));

				if (isset($rescuentainventario[0]) && $rescuentainventario[0]['pge_bridge_inv_purch'] != 0) {
				} else {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $rescuentainventario,
						'mensaje'	=> 'No se pudo registrar la factura de compras, no se ha configurado la cuenta puente de inventario para compras'
					);

					$this->response($respuesta);

					return;
				}

				//FIN CUENTA PUENTE DE INVENTARIO
				foreach ($DetalleConsolidadoCostoInventario as $key => $posicion) {
					$grantotalCostoInventario = 0;
					$grantotalCostoInventarioOriginal = 0;
					$cuentaInventario = "";
					$sinDatos = 0;
					$dbito = 0;
					$cdito = 0;
					$MontoSysDB = 0;
					$MontoSysCR = 0;
					foreach ($posicion as $key => $value) {

						// SE ACEPTAN SOLO LOS ARTICULOS QUE SON INVENTARIABLES
						// POR TAL MOTIVO SE VERIFICA QUE ESTADO DE _INVENTORI SEA 1
						if ($value->fc1_inventory == 1 || $value->fc1_inventory  == '1') {

							$sinDatos++;

							if (isset($Data['cfc_api']) && $Data['cfc_api'] == 1) {
								$cuentaInventario = $rescuentainventario[0]['pge_bridge_purch_int'];
							} else {
								$cuentaInventario = $rescuentainventario[0]['pge_bridge_inv_purch'];
							}

							$grantotalCostoInventario = ($grantotalCostoInventario + $value->fc1_linetotal);
						}
					}
					// SE VALIDA QUE EXISTA UN ARTICULO INVENTARIABLE
					// SE COMPRUEBA QUE LA VARIABLE SINDATOS SEA MAYOR A 0
					// CON ESTA SABEMOS QUE ENTRO POR LO MENOS UNA VES EN LA CONDICION
					// ANTERIOR OSEA QUE HAY UN ITEM INVENTARIABLE
					if ($sinDatos > 0) {

						// if ( $FactorC ){
						// 	$grantotalCostoInventario = $grantotalCostoInventario + $DescuentoAcumulado;
						// }

						$grantotalCostoInventarioOriginal = $grantotalCostoInventario;

						if (trim($Data['cfc_currency']) != $MONEDALOCAL) {

							$grantotalCostoInventario = ($grantotalCostoInventario * $TasaDocLoc);
						}

						$codigo3 = substr($cuentaInventario, 0, 1);

						if ($codigo3 == 1 || $codigo3 == "1") {
							$dbito = $grantotalCostoInventario;
							if (trim($Data['cfc_currency']) != $MONEDASYS) {
								$MontoSysDB = ($dbito / $TasaLocSys);
							} else {
								$MontoSysDB = ($grantotalCostoInventarioOriginal);
							}
						} else if ($codigo3 == 2 || $codigo3 == "2") {
							$dbito = $grantotalCostoInventario;
							if (trim($Data['cfc_currency']) != $MONEDASYS) {
								$MontoSysDB = ($dbito / $TasaLocSys);
							} else {
								$MontoSysDB = ($grantotalCostoInventarioOriginal);
							}
						} else if ($codigo3 == 3 || $codigo3 == "3") {
							$dbito = $grantotalCostoInventario;
							if (trim($Data['cfc_currency']) != $MONEDASYS) {
								$MontoSysDB = ($dbito / $TasaLocSys);
							} else {
								$MontoSysDB = ($grantotalCostoInventarioOriginal);
							}
						} else if ($codigo3 == 4 || $codigo3 == "4") {
							$dbito = $grantotalCostoInventario;
							if (trim($Data['cfc_currency']) != $MONEDASYS) {
								$MontoSysDB = ($dbito / $TasaLocSys);
							} else {
								$MontoSysDB = ($grantotalCostoInventarioOriginal);
							}
						} else if ($codigo3 == 5  || $codigo3 == "5") {
							$dbito = $grantotalCostoInventario;
							if (trim($Data['cfc_currency']) != $MONEDASYS) {
								$MontoSysDB = ($dbito / $TasaLocSys);
							} else {
								$MontoSysDB = ($grantotalCostoInventarioOriginal);
							}
						} else if ($codigo3 == 6 || $codigo3 == "6") {
							$dbito = $grantotalCostoInventario;
							if (trim($Data['cfc_currency']) != $MONEDASYS) {
								$MontoSysDB = ($dbito / $TasaLocSys);
							} else {
								$MontoSysDB = ($grantotalCostoInventarioOriginal);
							}
						} else if ($codigo3 == 7 || $codigo3 == "7") {
							$dbito = $grantotalCostoInventario;
							if (trim($Data['cfc_currency']) != $MONEDASYS) {
								$MontoSysDB = ($dbito / $TasaLocSys);
							} else {
								$MontoSysDB = ($grantotalCostoInventarioOriginal);
							}
						}

						$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
						$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));

						$AC1LINE = $AC1LINE + 1;
						$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

							':ac1_trans_id' => $resInsertAsiento,
							':ac1_account' => $cuentaInventario,
							':ac1_debit' => round($dbito, $DECI_MALES),
							':ac1_credit' => round($cdito, $DECI_MALES),
							':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
							':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
							':ac1_currex' => 0,
							':ac1_doc_date' => $this->validateDate($Data['cfc_docdate']) ? $Data['cfc_docdate'] : NULL,
							':ac1_doc_duedate' => $this->validateDate($Data['cfc_duedate']) ? $Data['cfc_duedate'] : NULL,
							':ac1_debit_import' => 0,
							':ac1_credit_import' => 0,
							':ac1_debit_importsys' => 0,
							':ac1_credit_importsys' => 0,
							':ac1_font_key' => $resInsert,
							':ac1_font_line' => 1,
							':ac1_font_type' => is_numeric($Data['cfc_doctype']) ? $Data['cfc_doctype'] : 0,
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
							':ac1_made_user' => isset($Data['cfc_createby']) ? $Data['cfc_createby'] : NULL,
							':ac1_accperiod' => 1,
							':ac1_close' => 0,
							':ac1_cord' => 0,
							':ac1_ven_debit' => round($dbito, $DECI_MALES),
							':ac1_ven_credit' => round($cdito, $DECI_MALES),
							':ac1_fiscal_acct' => 0,
							':ac1_taxid' => 0,
							':ac1_isrti' => 0,
							':ac1_basert' => 0,
							':ac1_mmcode' => 0,
							':ac1_legal_num' => isset($Data['cfc_cardcode']) ? $Data['cfc_cardcode'] : NULL,
							':ac1_codref' => 1,
							":ac1_line" => $AC1LINE,
							':ac1_base_tax' => 0,
							':business' => $Data['business'],
							':branch' 	=> $Data['branch'],
							':ac1_codret' => NULL
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
				}
			}

			// FIN

			// PROCEDIMIENTO PARA LLENAR ASIENTO ARTICULO NO INVENTARIABLE

			foreach ($DetalleConsolidadoItemNoInventariable as $key => $posicion) {
				$grantotalItemNoInventariable = 0;
				$grantotalItemNoInventariableOriginal = 0;
				$CuentaItemNoInventariable = "";
				$cdito = 0;
				$dbito = 0;
				$MontoSysDB = 0;
				$MontoSysCR = 0;
				$sinDatos = 0;

				foreach ($posicion as $key => $value) {


					$sinDatos++;
					$CuentaItemNoInventariable = $value->ac1_account;
					$grantotalItemNoInventariable = ($grantotalItemNoInventariable + $value->nc1_linetotal);
				}

				// if ( $FactorC ){
				// 	$grantotalItemNoInventariable = $grantotalItemNoInventariable + $DescuentoAcumulado;
				// }

				$grantotalItemNoInventariableOriginal = $grantotalItemNoInventariable;

				if (trim($Data['cfc_currency']) != $MONEDALOCAL) {
					$grantotalItemNoInventariable = ($grantotalItemNoInventariable * $TasaDocLoc);
				}

				$dbito = $grantotalItemNoInventariable;

				if (trim($Data['cfc_currency']) != $MONEDASYS) {
					$MontoSysDB = ($dbito / $TasaLocSys);
				} else {
					$MontoSysDB = $grantotalItemNoInventariableOriginal;
				}

				$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
				$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));


				$AC1LINE = $AC1LINE + 1;
				$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

					':ac1_trans_id' => $resInsertAsiento,
					':ac1_account' => $CuentaItemNoInventariable,
					':ac1_debit' =>  round($dbito, $DECI_MALES),
					':ac1_credit' => 0,
					':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
					':ac1_credit_sys' => 0,
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['cfc_docdate']) ? $Data['cfc_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['cfc_duedate']) ? $Data['cfc_duedate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => is_numeric($Data['cfc_doctype']) ? $Data['cfc_doctype'] : 0,
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
					':ac1_made_user' => isset($Data['cfc_createby']) ? $Data['cfc_createby'] : NULL,
					':ac1_accperiod' => 1,
					':ac1_close' => 0,
					':ac1_cord' => 0,
					':ac1_ven_debit' => round($dbito, $DECI_MALES),
					':ac1_ven_credit' => 0,
					':ac1_fiscal_acct' => 0,
					':ac1_taxid' => 0,
					':ac1_isrti' => 0,
					':ac1_basert' => 0,
					':ac1_mmcode' => 0,
					':ac1_legal_num' => isset($Data['cfc_cardcode']) ? $Data['cfc_cardcode'] : NULL,
					':ac1_codref' => 1,
					':ac1_line'   => 	$AC1LINE,
					':ac1_base_tax' => 0,
					':business' => $Data['business'],
					':branch' 	=> $Data['branch'],
					':ac1_codret' => NULL
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
			//FIN PROCEDIMIENTO PARA LLENAR ASIENTO ARTICULO NO INVENTARIABLE


			//Procedimiento para llenar cuentas por pagar

			$sqlcuentaCxP = "SELECT  f1.dms_card_code, f2.mgs_acct FROM dmsn AS f1
							JOIN dmgs  AS f2
							ON CAST(f2.mgs_id AS varchar(100)) = f1.dms_group_num
							WHERE  f1.dms_card_code = :dms_card_code
							AND f1.dms_card_type = '2'"; //2 para proveedores";


			$rescuentaCxP = $this->pedeo->queryTable($sqlcuentaCxP, array(":dms_card_code" => $Data['cfc_cardcode']));


			if (isset($rescuentaCxP[0])) {

				$debitoo = 0;
				$creditoo = 0;
				$MontoSysDB = 0;
				$MontoSysCR = 0;
				$TotalDoc = $Data['cfc_doctotal'];
				$TotalDocOri = $TotalDoc;

				$cuentaCxP = $rescuentaCxP[0]['mgs_acct'];

				$codigo2 = substr($rescuentaCxP[0]['mgs_acct'], 0, 1);


				if (trim($Data['cfc_currency']) != $MONEDALOCAL) {
					$TotalDoc = ($TotalDoc * $TasaDocLoc);
				}


				if (trim($Data['cfc_currency']) != $MONEDASYS) {
					$MontoSysCR = ($TotalDoc / $TasaLocSys);
				} else {
					$MontoSysCR = $TotalDocOri;
				}

				$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
				$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));


				$AC1LINE = $AC1LINE + 1;
				$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

					':ac1_trans_id' => $resInsertAsiento,
					':ac1_account' => $cuentaCxP,
					':ac1_debit' => 0,
					':ac1_credit' => round($TotalDoc, $DECI_MALES),
					':ac1_debit_sys' => 0,
					':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['cfc_docdate']) ? $Data['cfc_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['cfc_duedate']) ? $Data['cfc_duedate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => is_numeric($Data['cfc_doctype']) ? $Data['cfc_doctype'] : 0,
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
					':ac1_made_user' => isset($Data['cfc_createby']) ? $Data['cfc_createby'] : NULL,
					':ac1_accperiod' => 1,
					':ac1_close' => 0,
					':ac1_cord' => 0,
					':ac1_ven_debit' => 0,
					':ac1_ven_credit' => round($TotalDoc, $DECI_MALES),
					':ac1_fiscal_acct' => 0,
					':ac1_taxid' => 0,
					':ac1_isrti' => 0,
					':ac1_basert' => 0,
					':ac1_mmcode' => 0,
					':ac1_legal_num' => isset($Data['cfc_cardcode']) ? $Data['cfc_cardcode'] : NULL,
					':ac1_codref' => 1,
					":ac1_line" => $AC1LINE,
					':ac1_base_tax' => 0,
					':business' => $Data['business'],
					':branch' 	=> $Data['branch'],
					':ac1_codret' => NULL
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
			} else {

				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'	  => $rescuentaCxP,
					'mensaje'	=> 'No se pudo registrar la factura de compras, el tercero no tiene cuenta asociada'
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
				$CodRet = "";
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

				if (trim($Data['cfc_currency']) != $MONEDALOCAL) {
					$totalRetencion = ($totalRetencion * $TasaDocLoc);
					$BaseLineaRet = ($BaseLineaRet * $TasaDocLoc);
					$Basert = $BaseLineaRet;
				}


				if (trim($Data['cfc_currency']) != $MONEDASYS) {
					$MontoSysCR = ($totalRetencion / $TasaLocSys);
				} else {
					$MontoSysCR = 	$totalRetencionOriginal;
				}

				$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
				$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));

				$AC1LINE = $AC1LINE + 1;
				$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

					':ac1_trans_id' => $resInsertAsiento,
					':ac1_account' => $cuenta,
					':ac1_debit' => 0,
					':ac1_credit' => round($totalRetencion, $DECI_MALES),
					':ac1_debit_sys' => 0,
					':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['cfc_docdate']) ? $Data['cfc_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['cfc_duedate']) ? $Data['cfc_duedate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => is_numeric($Data['cfc_doctype']) ? $Data['cfc_doctype'] : 0,
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
					':ac1_made_user' => isset($Data['cfc_createby']) ? $Data['cfc_createby'] : NULL,
					':ac1_accperiod' => 1,
					':ac1_close' => 0,
					':ac1_cord' => 0,
					':ac1_ven_debit' => 0,
					':ac1_ven_credit' => round($totalRetencion, $DECI_MALES),
					':ac1_fiscal_acct' => 0,
					':ac1_taxid' => 0,
					':ac1_isrti' => $Profitrt,
					':ac1_basert' => round($Basert, $DECI_MALES),
					':ac1_mmcode' => 0,
					':ac1_legal_num' => isset($Data['cfc_cardcode']) ? $Data['cfc_cardcode'] : NULL,
					':ac1_codref' => 1,
					":ac1_line" => $AC1LINE,
					':ac1_base_tax' => 0,
					':business' => $Data['business'],
					':branch' 	=> $Data['branch'],
					':ac1_codret' => $CodRet
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

			// FIN PROCEDIMIENTO PARA LLENAR ASIENTO DE RENTENCIONES


			// ASIENTO PARA DESCUENTO
			if ($FactorC) {

				if (isset($DetalleConsolidadoDescuento[0])){

					$descuento = 0;
					$cuentadescuento = 0;
					$totalDescuento = 0;
					$totalDescuentoOriginal = 0;

					// BUSCANDO CUENTA DE DESCUENTO PARA COMPRAS

					$cuentaDescuento = "SELECT coalesce(pge_shopping_discount_account, 0) as cuenta FROM pgem WHERE pge_id = :pge_id";
					$rescuentaDescuento = $this->pedeo->queryTable($cuentaDescuento, array(':pge_id' => $Data['business']));

					if (isset( $rescuentaDescuento[0] ) && $rescuentaDescuento[0]['cuenta'] > 0 ) {

						$cuentadescuento = $rescuentaDescuento[0]['cuenta'];

					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'	  => $rescuentaDescuento,
							'mensaje'	=> 'No se pudo registrar la factura de compras, el no se encontro la cuenta para el descuento'
						);

						return $this->response($respuesta);

						
					}

					foreach ($DetalleConsolidadoDescuento as $key => $posicion) {

						$descuento = ( $descuento + $posicion->descuento );
						
		
					}

					$totalDescuento = $descuento;
					$totalDescuentoOriginal  = $totalDescuento;


					if (trim($Data['cfc_currency']) != $MONEDALOCAL) {
						$totalDescuento = ($totalDescuento * $TasaDocLoc);
					}
	
	
					if (trim($Data['cfc_currency']) != $MONEDASYS) {
						$MontoSysCR = ($totalDescuento / $TasaLocSys);
					} else {
						$MontoSysCR = 	$totalDescuentoOriginal;
					}
	
					$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
					$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));

					$AC1LINE = $AC1LINE + 1;
					$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(
	
						':ac1_trans_id' => $resInsertAsiento,
						':ac1_account' => $cuentadescuento,
						':ac1_debit' => 0,
						':ac1_credit' => round($totalDescuento, $DECI_MALES),
						':ac1_debit_sys' => 0,
						':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
						':ac1_currex' => 0,
						':ac1_doc_date' => $this->validateDate($Data['cfc_docdate']) ? $Data['cfc_docdate'] : NULL,
						':ac1_doc_duedate' => $this->validateDate($Data['cfc_duedate']) ? $Data['cfc_duedate'] : NULL,
						':ac1_debit_import' => 0,
						':ac1_credit_import' => 0,
						':ac1_debit_importsys' => 0,
						':ac1_credit_importsys' => 0,
						':ac1_font_key' => $resInsert,
						':ac1_font_line' => 1,
						':ac1_font_type' => is_numeric($Data['cfc_doctype']) ? $Data['cfc_doctype'] : 0,
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
						':ac1_made_user' => isset($Data['cfc_createby']) ? $Data['cfc_createby'] : NULL,
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
						':ac1_legal_num' => isset($Data['cfc_cardcode']) ? $Data['cfc_cardcode'] : NULL,
						':ac1_codref' => 1,
						":ac1_line" => $AC1LINE,
						':ac1_base_tax' => 0,
						':business' => $Data['business'],
						':branch' 	=> $Data['branch'],
						':ac1_codret' => '0'
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

			}
			// FIN ASIENTO PARA DESCUENTO

			// ASIENTO PARA IVA DEL DESCUENTO
			if ($FactorC) {

				if (isset($DetalleConsolidadoIvaDescuento[0])){

					$ivadescuento = 0;
					$cuentaivadescuento = 0;
					$totalIvaDescuento = 0;
					$totalIvaDescuentoOriginal = 0;

					// BUSCANDO CUENTA DE DESCUENTO PARA COMPRAS

					$cuentaIvaDescuento = "SELECT coalesce(pge_tax_debit_account, 0) as cuenta FROM pgem WHERE pge_id = :pge_id";
					$rescuentaIvaDescuento = $this->pedeo->queryTable($cuentaDescuento, array(':pge_id' => $Data['business']));

					if (isset( $rescuentaIvaDescuento[0] ) && $rescuentaIvaDescuento[0]['cuenta'] > 0 ) {

						$cuentaivadescuento = $rescuentaIvaDescuento[0]['cuenta'];

					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'	  => $rescuentaIvaDescuento,
							'mensaje'	=> 'No se pudo registrar la factura de compras, el no se encontro la cuenta para asociar el iva del descuento'
						);

						return $this->response($respuesta);

						
					}

					foreach ($DetalleConsolidadoIvaDescuento as $key => $posicion) {

						$ivadescuento = ( $ivadescuento + $posicion->ivadescuento );
						
		
					}

					$totalIvaDescuento = $ivadescuento;
					$totalIvaDescuentoOriginal  = $totalIvaDescuento;


					if (trim($Data['cfc_currency']) != $MONEDALOCAL) {
						$totalIvaDescuento = ($totalIvaDescuento * $TasaDocLoc);
					}
	
	
					if (trim($Data['cfc_currency']) != $MONEDASYS) {
						$MontoSysCR = ($totalIvaDescuento / $TasaLocSys);
					} else {
						$MontoSysCR = 	$totalIvaDescuentoOriginal;
					}
	
					$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
					$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));

					$AC1LINE = $AC1LINE + 1;
					$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(
	
						':ac1_trans_id' => $resInsertAsiento,
						':ac1_account' => $cuentaivadescuento,
						':ac1_debit' => 0,
						':ac1_credit' => round($totalIvaDescuento, $DECI_MALES),
						':ac1_debit_sys' => 0,
						':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
						':ac1_currex' => 0,
						':ac1_doc_date' => $this->validateDate($Data['cfc_docdate']) ? $Data['cfc_docdate'] : NULL,
						':ac1_doc_duedate' => $this->validateDate($Data['cfc_duedate']) ? $Data['cfc_duedate'] : NULL,
						':ac1_debit_import' => 0,
						':ac1_credit_import' => 0,
						':ac1_debit_importsys' => 0,
						':ac1_credit_importsys' => 0,
						':ac1_font_key' => $resInsert,
						':ac1_font_line' => 1,
						':ac1_font_type' => is_numeric($Data['cfc_doctype']) ? $Data['cfc_doctype'] : 0,
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
						':ac1_made_user' => isset($Data['cfc_createby']) ? $Data['cfc_createby'] : NULL,
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
						':ac1_legal_num' => isset($Data['cfc_cardcode']) ? $Data['cfc_cardcode'] : NULL,
						':ac1_codref' => 1,
						":ac1_line" => $AC1LINE,
						':ac1_base_tax' => 0,
						':business' => $Data['business'],
						':branch' 	=> $Data['branch'],
						':ac1_codret' => '0'
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

			}
			// FIN ASIENTO PARA IVA DEL DESCUENTO


			// FIN DE OPERACIONES VITALES

			// FIN VALIDACION DE ESTADOS

			$debito  = 0;
			$credito = 0;


			if ($SumaCreditosSYS > $SumaDebitosSYS || $SumaDebitosSYS > $SumaCreditosSYS) {

				$sqlCuentaDiferenciaDecimal = "SELECT pge_acc_ajp FROM pgem WHERE pge_id = :business";
				$resCuentaDiferenciaDecimal = $this->pedeo->queryTable($sqlCuentaDiferenciaDecimal, array(':business' => $Data['business']));

				if (isset($resCuentaDiferenciaDecimal[0]) && is_numeric($resCuentaDiferenciaDecimal[0]['pge_acc_ajp'])) {

					if ($SumaCreditosSYS > $SumaDebitosSYS) { // DIFERENCIA EN CREDITO EL VALOR SE COLOCA EN DEBITO

						$debito = ($SumaCreditosSYS - $SumaDebitosSYS);
					} else { // DIFERENCIA EN DEBITO EL VALOR SE COLOCA EN CREDITO

						$credito = ($SumaDebitosSYS - $SumaCreditosSYS);
					}

					if (round($debito + $credito, $DECI_MALES) > 0) {
						$AC1LINE = $AC1LINE + 1;
						$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

							':ac1_trans_id' => $resInsertAsiento,
							':ac1_account' => $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'],
							':ac1_debit' => 0,
							':ac1_credit' => 0,
							':ac1_debit_sys' => round($debito, $DECI_MALES),
							':ac1_credit_sys' => round($credito, $DECI_MALES),
							':ac1_currex' => 0,
							':ac1_doc_date' => $this->validateDate($Data['cfc_docdate']) ? $Data['cfc_docdate'] : NULL,
							':ac1_doc_duedate' => $this->validateDate($Data['cfc_duedate']) ? $Data['cfc_duedate'] : NULL,
							':ac1_debit_import' => 0,
							':ac1_credit_import' => 0,
							':ac1_debit_importsys' => 0,
							':ac1_credit_importsys' => 0,
							':ac1_font_key' => $resInsert,
							':ac1_font_line' => 1,
							':ac1_font_type' => is_numeric($Data['cfc_doctype']) ? $Data['cfc_doctype'] : 0,
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
							':ac1_made_user' => isset($Data['cfc_createby']) ? $Data['cfc_createby'] : NULL,
							':ac1_accperiod' => 1,
							':ac1_close' => 0,
							':ac1_cord' => 0,
							':ac1_ven_debit' => round($debito, $DECI_MALES),
							':ac1_ven_credit' => round($credito, $DECI_MALES),
							':ac1_fiscal_acct' => 0,
							':ac1_taxid' => 0,
							':ac1_isrti' => 0,
							':ac1_basert' => 0,
							':ac1_mmcode' => 0,
							':ac1_legal_num' => isset($Data['cfc_cardcode']) ? $Data['cfc_cardcode'] : NULL,
							':ac1_codref' => 1,
							':ac1_line'   => 	$AC1LINE,
							':ac1_base_tax' => 0,
							':business' => $Data['business'],
							':branch' 	=> $Data['branch'],
							':ac1_codret' => NULL
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

			// $sqlmac1 = "SELECT * FROM  mac1 WHERE ac1_trans_id = :ac1_trans_id";
			// $ressqlmac1 = $this->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $resInsertAsiento ));
			// print_r(json_encode($ressqlmac1));
			// exit;
			// $sqlserial = "select * from tmsn order by msn_id desc";
			// $ressqlserial = $this->pedeo->queryTable($sqlserial, array());
			// print_r(json_encode($ressqlserial));
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





			// VALIDANDO ESTADOS DE DOCUMENTOS

			if ($Data['cfc_basetype'] == 12) {


				$sqlEstado1 = "SELECT
							count(t1.po1_itemcode) item,
							sum(t1.po1_quantity) cantidad
						from dcpo t0
						inner join cpo1 t1 on t0.cpo_docentry = t1.po1_docentry
						where t0.cpo_docentry = :cpo_docentry and t0.cpo_doctype = :cpo_doctype";


				$resEstado1 = $this->pedeo->queryTable($sqlEstado1, array(
					':cpo_docentry' => $Data['cfc_baseentry'],
					':cpo_doctype' => $Data['cfc_basetype']
				));


				$sqlEstado2 = "SELECT
								coalesce(count(distinct t3.fc1_itemcode),0) item,
								coalesce(sum(t3.fc1_quantity),0) cantidad
							from dcpo t0
							left join cpo1 t1 on t0.cpo_docentry = t1.po1_docentry
							left join dcfc t2 on t0.cpo_docentry = t2.cfc_baseentry
							left join cfc1 t3 on t2.cfc_docentry = t3.fc1_docentry and t1.po1_itemcode = t3.fc1_itemcode
							where t0.cpo_docentry = :cpo_docentry and t0.cpo_doctype = :cpo_doctype";
				$resEstado2 = $this->pedeo->queryTable($sqlEstado2, array(
					':cpo_docentry' => $Data['cfc_baseentry'],
					':cpo_doctype' => $Data['cfc_basetype']
				));

				$item_ord = $resEstado1[0]['item'];
				$item_fact = $resEstado2[0]['item'];
				$cantidad_ord = $resEstado1[0]['cantidad'];
				$cantidad_fact = $resEstado2[0]['cantidad'];

				if ($item_ord == $item_fact  &&  $item_fact == $cantidad_fact) {

					$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
										VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

					$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


						':bed_docentry' => $Data['cfc_baseentry'],
						':bed_doctype' => $Data['cfc_basetype'],
						':bed_status' => 3, //ESTADO CERRADO
						':bed_createby' => $Data['cfc_createby'],
						':bed_date' => date('Y-m-d'),
						':bed_baseentry' => $resInsert,
						':bed_basetype' => $Data['cfc_doctype']
					));


					if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' => $resInsertEstado,
							'mensaje'	=> 'No se pudo registrar la factura de compra'
						);


						$this->response($respuesta);

						return;
					}
				}
			} else if ($Data['cfc_basetype'] == 13) {

				$sqlEstado1 = "SELECT
									count(t1.ec1_itemcode) item,
									coalesce(sum(t1.ec1_quantity),0) cantidad
								from dcec t0
								inner join cec1 t1 on t0.cec_docentry = t1.ec1_docentry
								where t0.cec_docentry = :cec_docentry and t0.cec_doctype = :cec_doctype";

				$resEstado1 = $this->pedeo->queryTable($sqlEstado1, array(
					':cec_docentry' => $Data['cfc_baseentry'],
					':cec_doctype' => $Data['cfc_basetype']
				));

				$sqlDev = "SELECT
								count(t3.dc1_itemcode) item,
								coalesce(sum(t3.dc1_quantity),0) cantidad
							from dcec t0
							left join cec1 t1 on t0.cec_docentry = t1.ec1_docentry
							left join dcdc t2 on t0.cec_docentry = t2.cdc_baseentry and t0.cec_doctype = t2.cdc_basetype
							left join cdc1 t3 on t2.cdc_docentry = t3.dc1_docentry and t1.ec1_itemcode = t3.dc1_itemcode
							where t0.cec_docentry = :cec_docentry and t0.cec_doctype = :cec_doctype";

				$resDev = $this->pedeo->queryTable($sqlDev, array(
					':cec_docentry' => $Data['cfc_baseentry'],
					':cec_doctype' => $Data['cfc_basetype']
				));

				$resta_cantidad = abs($resEstado1[0]['cantidad'] - $resDev[0]['cantidad']);
				$resta_item = abs($resEstado1[0]['item'] - $resDev[0]['item']);

				$sqlEstado2 = "SELECT
									coalesce(count(distinct t3.fc1_itemcode),0) item,
									coalesce(sum(t3.fc1_quantity),0) cantidad
								from dcec t0
								left join cec1 t1 on t0.cec_docentry = t1.ec1_docentry
								left join dcfc t2 on t0.cec_docentry = t2.cfc_baseentry and t0.cec_doctype = t2.cfc_basetype
								left join cfc1 t3 on t2.cfc_docentry = t3.fc1_docentry and t1.ec1_itemcode = t3.fc1_itemcode
								where t0.cec_docentry = :cec_docentry and t0.cec_doctype = :cec_doctype";
				$resEstado2 = $this->pedeo->queryTable($sqlEstado2, array(
					':cec_docentry' => $Data['cfc_baseentry'],
					':cec_doctype' => $Data['cfc_basetype']
				));

				if (is_numeric($resta_item) && $resta_item == 0) {
					$item_del = abs($resEstado1[0]['item']);
				} else {
					$item_del = abs($resta_item);
				}
				// $item_del = $resta_item;
				$item_fact = abs($resEstado2[0]['item']);

				$cantidad_del = abs($resta_cantidad);
				$cantidad_fact = abs($resEstado2[0]['cantidad']);

				


				if ($item_del == $item_fact && $cantidad_del == $cantidad_fact) {
					

						$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
											VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

						$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(
							':bed_docentry' => $Data['cfc_baseentry'],
							':bed_doctype' => $Data['cfc_basetype'],
							':bed_status' => 3, //ESTADO CERRADO
							':bed_createby' => $Data['cfc_createby'],
							':bed_date' => date('Y-m-d'),
							':bed_baseentry' => $resInsert,
							':bed_basetype' => $Data['cfc_doctype']
						));


						if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {

					}else {
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' => $resInsertEstado,
							'mensaje'	=> 'No se pudo registrar la devolucion de compra'
						);
						$this->response($respuesta);
						return;
					}
				}
			}
			// print_r("validacion");exit();die();
			// Si todo sale bien despues de insertar el detalle de la factura de compras
			// se confirma la trasaccion  para que los cambios apliquen permanentemente
			// en la base de datos y se confirma la operacion exitosa.
			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'Factura de compras registrada con exito'
			);
		} else {
			// Se devuelven los cambios realizados en la transaccion
			// si occurre un error  y se muestra devuelve el error.
			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data' => $resInsert,
				'mensaje'	=> 'No se pudo registrar la Factura de compras'
			);
		}

		$this->response($respuesta);
	}

	//ACTUALIZAR Factura de compras
	public function updatePurchaseInv_post()
	{

		$Data = $this->post();

		if (
			!isset($Data['cfc_docentry']) or !isset($Data['cfc_docnum']) or
			!isset($Data['cfc_docdate']) or !isset($Data['cfc_duedate']) or
			!isset($Data['cfc_duedev']) or !isset($Data['cfc_pricelist']) or
			!isset($Data['cfc_cardcode']) or !isset($Data['cfc_cardname']) or
			!isset($Data['cfc_currency']) or !isset($Data['cfc_contacid']) or
			!isset($Data['cfc_slpcode']) or !isset($Data['cfc_empid']) or
			!isset($Data['cfc_comment']) or !isset($Data['cfc_doctotal']) or
			!isset($Data['cfc_baseamnt']) or !isset($Data['cfc_taxtotal']) or
			!isset($Data['cfc_discprofit']) or !isset($Data['cfc_discount']) or
			!isset($Data['cfc_createat']) or !isset($Data['cfc_baseentry']) or
			!isset($Data['cfc_basetype']) or !isset($Data['cfc_doctype']) or
			!isset($Data['cfc_idadd']) or !isset($Data['cfc_adress']) or
			!isset($Data['cfc_paytype']) or !isset($Data['cfc_attch']) or
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
				'mensaje' => 'No se encontro el detalle de la factura de compras'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlUpdate = "UPDATE dcfc	SET cfc_docdate=:cfc_docdate,cfc_duedate=:cfc_duedate, cfc_duedev=:cfc_duedev, cfc_pricelist=:cfc_pricelist, cfc_cardcode=:cfc_cardcode,
			  						cfc_cardname=:cfc_cardname, cfc_currency=:cfc_currency, cfc_contacid=:cfc_contacid, cfc_slpcode=:cfc_slpcode,
										cfc_empid=:cfc_empid, cfc_comment=:cfc_comment, cfc_doctotal=:cfc_doctotal, cfc_baseamnt=:cfc_baseamnt,
										cfc_taxtotal=:cfc_taxtotal, cfc_discprofit=:cfc_discprofit, cfc_discount=:cfc_discount, cfc_createat=:cfc_createat,
										cfc_baseentry=:cfc_baseentry, cfc_basetype=:cfc_basetype, cfc_doctype=:cfc_doctype, cfc_idadd=:cfc_idadd,
										cfc_adress=:cfc_adress, cfc_paytype=:cfc_paytype, cfc_internal_comments=:cfc_internal_comments WHERE cfc_docentry=:cfc_docentry";

		$this->pedeo->trans_begin();

		$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
			':cfc_docnum' => is_numeric($Data['cfc_docnum']) ? $Data['cfc_docnum'] : 0,
			':cfc_docdate' => $this->validateDate($Data['cfc_docdate']) ? $Data['cfc_docdate'] : NULL,
			':cfc_duedate' => $this->validateDate($Data['cfc_duedate']) ? $Data['cfc_duedate'] : NULL,
			':cfc_duedev' => $this->validateDate($Data['cfc_duedev']) ? $Data['cfc_duedev'] : NULL,
			':cfc_pricelist' => is_numeric($Data['cfc_pricelist']) ? $Data['cfc_pricelist'] : 0,
			':cfc_cardcode' => isset($Data['cfc_pricelist']) ? $Data['cfc_pricelist'] : NULL,
			':cfc_cardname' => isset($Data['cfc_cardname']) ? $Data['cfc_cardname'] : NULL,
			':cfc_currency' => isset($Data['cfc_currency']) ? $Data['cfc_currency'] : NULL,
			':cfc_contacid' => isset($Data['cfc_contacid']) ? $Data['cfc_contacid'] : NULL,
			':cfc_slpcode' => is_numeric($Data['cfc_slpcode']) ? $Data['cfc_slpcode'] : 0,
			':cfc_empid' => is_numeric($Data['cfc_empid']) ? $Data['cfc_empid'] : 0,
			':cfc_comment' => isset($Data['cfc_comment']) ? $Data['cfc_comment'] : NULL,
			':cfc_doctotal' => is_numeric($Data['cfc_doctotal']) ? $Data['cfc_doctotal'] : 0,
			':cfc_baseamnt' => is_numeric($Data['cfc_baseamnt']) ? $Data['cfc_baseamnt'] : 0,
			':cfc_taxtotal' => is_numeric($Data['cfc_taxtotal']) ? $Data['cfc_taxtotal'] : 0,
			':cfc_discprofit' => is_numeric($Data['cfc_discprofit']) ? $Data['cfc_discprofit'] : 0,
			':cfc_discount' => is_numeric($Data['cfc_discount']) ? $Data['cfc_discount'] : 0,
			':cfc_createat' => $this->validateDate($Data['cfc_createat']) ? $Data['cfc_createat'] : NULL,
			':cfc_baseentry' => is_numeric($Data['cfc_baseentry']) ? $Data['cfc_baseentry'] : 0,
			':cfc_basetype' => is_numeric($Data['cfc_basetype']) ? $Data['cfc_basetype'] : 0,
			':cfc_doctype' => is_numeric($Data['cfc_doctype']) ? $Data['cfc_doctype'] : 0,
			':cfc_idadd' => isset($Data['cfc_idadd']) ? $Data['cfc_idadd'] : NULL,
			':cfc_adress' => isset($Data['cfc_adress']) ? $Data['cfc_adress'] : NULL,
			':cfc_paytype' => is_numeric($Data['cfc_paytype']) ? $Data['cfc_paytype'] : 0,
			':cfc_internal_comments' => isset($Data['cfc_internal_comments']) ? $Data['cfc_internal_comments'] : NULL,
			':cfc_docentry' => $Data['cfc_docentry']
		));

		if (is_numeric($resUpdate) && $resUpdate == 1) {

			$this->pedeo->queryTable("DELETE FROM cfc1 WHERE fc1_docentry=:fc1_docentry", array(':fc1_docentry' => $Data['cfc_docentry']));

			foreach ($ContenidoDetalle as $key => $detail) {

				$sqlInsertDetail = "INSERT INTO cfc1(fc1_docentry, fc1_itemcode, fc1_itemname, fc1_quantity, fc1_uom, fc1_whscode,
																			fc1_price, fc1_vat, fc1_vatsum, fc1_discount, fc1_linetotal, fc1_costcode, fc1_ubusiness, fc1_project,
																			fc1_acctcode, fc1_basetype, fc1_doctype, fc1_avprice, fc1_inventory, fc1_acciva, fc1_ubication)VALUES(:fc1_docentry, :fc1_itemcode, :fc1_itemname, :fc1_quantity,
																			:fc1_uom, :fc1_whscode,:fc1_price, :fc1_vat, :fc1_vatsum, :fc1_discount, :fc1_linetotal, :fc1_costcode, :fc1_ubusiness, :fc1_project,
																			:fc1_acctcode, :fc1_basetype, :fc1_doctype, :fc1_avprice, :fc1_inventory, :fc1_acciva, :fc1_ubication)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':fc1_docentry' => $Data['cfc_docentry'],
					':fc1_itemcode' => isset($detail['fc1_itemcode']) ? $detail['fc1_itemcode'] : NULL,
					':fc1_itemname' => isset($detail['fc1_itemname']) ? $detail['fc1_itemname'] : NULL,
					':fc1_quantity' => is_numeric($detail['fc1_quantity']) ? $detail['fc1_quantity'] : 0,
					':fc1_uom' => isset($detail['fc1_uom']) ? $detail['fc1_uom'] : NULL,
					':fc1_whscode' => isset($detail['fc1_whscode']) ? $detail['fc1_whscode'] : NULL,
					':fc1_price' => is_numeric($detail['fc1_price']) ? $detail['fc1_price'] : 0,
					':fc1_vat' => is_numeric($detail['fc1_vat']) ? $detail['fc1_vat'] : 0,
					':fc1_vatsum' => is_numeric($detail['fc1_vatsum']) ? $detail['fc1_vatsum'] : 0,
					':fc1_discount' => is_numeric($detail['fc1_discount']) ? $detail['fc1_discount'] : 0,
					':fc1_linetotal' => is_numeric($detail['fc1_linetotal']) ? $detail['fc1_linetotal'] : 0,
					':fc1_costcode' => isset($detail['fc1_costcode']) ? $detail['fc1_costcode'] : NULL,
					':fc1_ubusiness' => isset($detail['fc1_ubusiness']) ? $detail['fc1_ubusiness'] : NULL,
					':fc1_project' => isset($detail['fc1_project']) ? $detail['fc1_project'] : NULL,
					':fc1_acctcode' => is_numeric($detail['fc1_acctcode']) ? $detail['fc1_acctcode'] : 0,
					':fc1_basetype' => is_numeric($detail['fc1_basetype']) ? $detail['fc1_basetype'] : 0,
					':fc1_doctype' => is_numeric($detail['fc1_doctype']) ? $detail['fc1_doctype'] : 0,
					':fc1_avprice' => is_numeric($detail['fc1_avprice']) ? $detail['fc1_avprice'] : 0,
					':fc1_inventory' => is_numeric($detail['fc1_inventory']) ? $detail['fc1_inventory'] : NULL,
					':fc1_acciva' => is_numeric($detail['fc1_cuentaIva']) ? $detail['fc1_cuentaIva'] : 0,
					':fc1_ubication' => ($detail['fc1_ubication']) ? $detail['fc1_ubication'] : NULL
				));

				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle de la factura de compras se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertDetail,
						'mensaje'	=> 'No se pudo registrar la factura de compras'
					);

					$this->response($respuesta);

					return;
				}
			}


			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resUpdate,
				'mensaje' => 'Factura de compras actualizada con exito'
			);
		} else {

			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data'    => $resUpdate,
				'mensaje'	=> 'No se pudo actualizar la factura de compras'
			);
		}

		$this->response($respuesta);
	}


	//OBTENER Factura de compras
	public function getPurchaseInv_get()
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
		// CAMPOS DE RETENCIONES
		$DECI_MALES =  $this->generic->getDecimals();

		$campos = ",CONCAT(T0.{prefix}_CURRENCY,' ',TRIM(TO_CHAR(t0.{prefix}_totalret,'999,999,999,999.00'))) {prefix}_totalret,
		CONCAT(T0.{prefix}_CURRENCY,' ',TRIM(TO_CHAR(t0.{prefix}_totalretiva,'999,999,999,999.00'))) {prefix}_totalretiva";

		$sqlSelect = self::getColumn('dcfc', 'cfc', $campos, '', $DECI_MALES, $Data['business'], $Data['branch'], 15);

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


	//OBTENER Factura de compras POR ID
	public function getPurchaseInvById_get()
	{

		$Data = $this->get();

		if (!isset($Data['cfc_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		// SE VALIDA DIFERENCIA POR DECIMALES
		// Y SE AGREGA UN ASIENTO DE DIFERENCIA EN DECIMALES
		// SEGUN SEA EL CASO

		// FIN VALIDACION DIFERENCIA EN DECIMALES

		$sqlSelect = " SELECT * FROM dcfc WHERE cfc_docentry =:cfc_docentry ";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":cfc_docentry" => $Data['cfc_docentry']));

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


	//OBTENER Factura de compras DETALLE POR ID
	public function getPurchaseInvDetail_get()
	{

		$Data = $this->get();

		if (!isset($Data['fc1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT cfc1.*,dc.cfc_cardcode,dc.cfc_docentry
											FROM cfc1
											INNER  JOIN dcfc dc ON cfc1.fc1_docentry = dc.cfc_docentry
											WHERE cfc1.fc1_docentry =:fc1_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":fc1_docentry" => $Data['fc1_docentry']));



		$seriales = "SELECT msn_line, msn_itemcode, string_agg(msn_sn, ',') AS serials FROM tmsn  WHERE msn_baseentry = :msn_baseentry AND msn_basetype = :msn_basetype GROUP BY  msn_itemcode,msn_line";


		$resseriales = $this->pedeo->queryTable($seriales, array(":msn_baseentry" => $Data['fc1_docentry'], ":msn_basetype" => 15));


		if (isset($resSelect[0]) && isset($resseriales[0])) {

			foreach ($resSelect as $key => $value) {

				$sqlSelect2 = "SELECT fc.crt_typert,fc.crt_type,fc.crt_basert,fc.crt_profitrt,fc.crt_totalrt,fc.crt_base,fc.crt_linenum,dmar.dma_series_code
														FROM cfc1
														INNER JOIN dmar
														ON cfc1.fc1_itemcode = dmar.dma_item_code
														INNER JOIN fcrt fc
														ON cfc1.fc1_docentry = fc.crt_baseentry
														AND cfc1.fc1_linenum = fc.crt_linenum
														WHERE fc1_docentry = :fc1_docentry";

				$resSelect2 = $this->pedeo->queryTable($sqlSelect2, array(':fc1_docentry' => $Data['fc1_docentry']));

				if (isset($resSelect2[0])) {
					$resSelect[$key]['retenciones'] = $resSelect2;
				}
			}



			$respuesta = array(
				'error' => false,
				'data'  =>  array("detalle" => $resSelect, "complemento" => $resseriales),
				'mensaje' => ''
			);
		} else if (isset($resSelect[0])) {

			foreach ($resSelect as $key => $value) {

				$sqlSelect2 = "SELECT fc.crt_typert,fc.crt_type,fc.crt_basert,fc.crt_profitrt,fc.crt_totalrt,fc.crt_base,fc.crt_linenum,dmar.dma_series_code
														FROM cfc1
														INNER JOIN dmar
														ON cfc1.fc1_itemcode = dmar.dma_item_code
														INNER JOIN fcrt fc
														ON cfc1.fc1_docentry = fc.crt_baseentry
														AND cfc1.fc1_linenum = fc.crt_linenum
														WHERE fc1_docentry = :fc1_docentry";

				$resSelect2 = $this->pedeo->queryTable($sqlSelect2, array(':fc1_docentry' => $Data['fc1_docentry']));

				if (isset($resSelect2[0])) {
					$resSelect[$key]['retenciones'] = $resSelect2;
				}
			}

			$respuesta = array(
				'error'     => false,
				'data'		=> $resSelect,
				'mensaje'	=> 'busqueda sin resultados'
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

	public function getPurchaseInvDetailCopy_get()
	{

		$Data = $this->get();

		if (!isset($Data['fc1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$copy = $this->documentcopy->Copy($Data['fc1_docentry'],'dcfc','cfc1','cfc','fc1');


		$seriales = "SELECT msn_line, msn_itemcode, string_agg(msn_sn, ',') AS serials FROM tmsn  WHERE msn_baseentry = :msn_baseentry AND msn_basetype = :msn_basetype GROUP BY  msn_itemcode,msn_line";


		$resseriales = $this->pedeo->queryTable($seriales, array(":msn_baseentry" => $Data['fc1_docentry'], ":msn_basetype" => 15));


		if (isset($copy[0]) && isset($resseriales[0])) {

			foreach ($copy as $key => $value) {

				$sqlSelect2 = "SELECT fc.crt_typert,fc.crt_type,fc.crt_basert,fc.crt_profitrt,fc.crt_totalrt,fc.crt_base,fc.crt_linenum,dmar.dma_series_code
														FROM cfc1
														INNER JOIN dcfc ON cfc1.fc1_docentry = dcfc.cfc_docentry
														INNER JOIN dmar ON cfc1.fc1_itemcode = dmar.dma_item_code
														INNER JOIN fcrt fc ON cfc1.fc1_docentry = fc.crt_baseentry and dcfc.cfc_doctype = fc.crt_basetype
														AND cfc1.fc1_linenum = fc.crt_linenum
														WHERE fc1_docentry = :fc1_docentry";

				$resSelect2 = $this->pedeo->queryTable($sqlSelect2, array(':fc1_docentry' => $Data['fc1_docentry']));

				if (isset($resSelect2[0])) {
					$copy[$key]['retenciones'] = $resSelect2;
				}
			}



			$respuesta = array(
				'error' => false,
				'data'  =>  array("detalle" => $copy, "complemento" => $resseriales),
				'mensaje' => ''
			);
		} else if (isset($copy[0])) {

			foreach ($copy as $key => $value) {

				$sqlSelect2 = "SELECT fc.crt_typert,fc.crt_type,fc.crt_basert,fc.crt_profitrt,fc.crt_totalrt,fc.crt_base,fc.crt_linenum,dmar.dma_series_code
														FROM cfc1
														INNER JOIN dmar
														ON cfc1.fc1_itemcode = dmar.dma_item_code
														INNER JOIN fcrt fc
														ON cfc1.fc1_docentry = fc.crt_baseentry
														AND cfc1.fc1_linenum = fc.crt_linenum
														WHERE fc1_docentry = :fc1_docentry";

				$resSelect2 = $this->pedeo->queryTable($sqlSelect2, array(':fc1_docentry' => $Data['fc1_docentry']));

				if (isset($resSelect2[0])) {
					$copy[$key]['retenciones'] = $resSelect2;
				}
			}

			$respuesta = array(
				'error'     => false,
				'data'		=> $copy,
				'mensaje'	=> 'busqueda sin resultados'
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

	//OBTENER FACTURA DE VENTA POR ID SOCIO DE NEGOCIO
	// getPurchaseInvoiceBySN_get
	public function getPurchaseInvBySN_get()
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

		$copy = $this->documentcopy->CopyData('dcfc','cfc',$Data['dms_card_code'],$Data['business'],$Data['branch']);

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

		$duplicateData = $this->documentduplicate->getDuplicate('dcfc','cfc',$Data['dms_card_code'],$Data['business']);


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

		if (!isset($Data['fc1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

			$copy = $this->documentduplicate->getDuplicateDt($Data['fc1_docentry'],'dcfc','cfc1','cfc','fc1','');

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
}