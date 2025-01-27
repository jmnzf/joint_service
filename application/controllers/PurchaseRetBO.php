<?php
// DEVOLUCION DE COMPRAS
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class PurchaseRetBO extends REST_Controller
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
		$this->load->library('DocumentNumbering');
		$this->load->library('Tasa');
		$this->load->library('DocumentDuplicate');
		$this->load->library('CostoBO');
	}

	//CREAR NUEVA DEVOLUCION DE COMPRAS
	public function createPurchaseRet_post()
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
		
		$DetalleCuentaPuente = new stdClass();
		$DetalleCuentaInvetario = new stdClass();
		$DetalleConsolidadoCuentaPuente = [];
		$DetalleConsolidadoCuentaInventario = [];
		$inArrayCuentaPuente = array();
		$inArrayCuentaInvetario = array();
		$llaveCuentaPuente = "";
		$llaveCuentaInvetario = "";
		$posicionCuentaPuente = 0;
		$posicionCuentaInvetario = 0;
		$codigoCuenta = ""; //para saber la naturaleza
		$ManejaInvetario = 0;
		$ManejaLote = 0;
		$ManejaUbicacion = 0;
		$DocNumVerificado = 0;

		$AC1LINE = 1;
		$resInsertAsiento = "";
		$ResultadoInv = 0; // INDICA SI EXISTE AL MENOS UN ITEM QUE MANEJA INVENTARIO
		$CANTUOMPURCHASE = 0; //CANTIDAD EN UNIDAD DE MEDIDA
		$CANTUOMSALE = 0;

		$ManejaTasa = 0;
		$MontoTasa = 0;

		$ContadorItenmnoInv = 0;


		// Se globaliza la variable sqlDetalleAsiento
		$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
							ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
							ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
							ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref,ac1_line,business,branch)VALUES (:ac1_trans_id, :ac1_account,
							:ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
							:ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
							:ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
							:ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref,:ac1_line,:business,:branch)";


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
				'mensaje' => 'No se encontro el detalle de la devolucion de compras'
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
		$periodo = $this->generic->ValidatePeriod($Data['cdc_duedev'], $Data['cdc_docdate'], $Data['cdc_duedate'], 0);

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
		$DocNumVerificado = $this->documentnumbering->NumberDoc($Data['cdc_series'],$Data['cdc_docdate'],$Data['cdc_duedate']);
		
		if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0){

		}else if ($DocNumVerificado['error']){

			return $this->response($DocNumVerificado, REST_Controller::HTTP_BAD_REQUEST);
		}

		//PROCESO DE TASA
		$dataTasa = $this->tasa->Tasa($Data['cdc_currency'],$Data['cdc_docdate']);

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
		if($Data['cdc_duedate'] < $Data['cdc_docdate']){
			$respuesta = array(
				'error' => true,
				'data' => [],
				'mensaje' => 'La fecha de vencimiento ('.$Data['cdc_duedate'].') no puede ser inferior a la fecha del documento ('.$Data['cdc_docdate'].')'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}
		$sqlInsert = "INSERT INTO dcdc(cdc_series, cdc_docnum, cdc_docdate, cdc_duedate, cdc_duedev, cdc_pricelist, cdc_cardcode,
                      cdc_cardname, cdc_currency, cdc_contacid, cdc_slpcode, cdc_empid, cdc_comment, cdc_doctotal, cdc_baseamnt, cdc_taxtotal,
                      cdc_discprofit, cdc_discount, cdc_createat, cdc_baseentry, cdc_basetype, cdc_doctype, cdc_idadd, cdc_adress, cdc_paytype,
                      cdc_createby,business,branch,cdc_internal_comments)VALUES(:cdc_series, :cdc_docnum, :cdc_docdate, :cdc_duedate, :cdc_duedev, :cdc_pricelist, :cdc_cardcode, :cdc_cardname,
                      :cdc_currency, :cdc_contacid, :cdc_slpcode, :cdc_empid, :cdc_comment, :cdc_doctotal, :cdc_baseamnt, :cdc_taxtotal, :cdc_discprofit, :cdc_discount,
                      :cdc_createat, :cdc_baseentry, :cdc_basetype, :cdc_doctype, :cdc_idadd, :cdc_adress, :cdc_paytype, :cdc_createby,:business,:branch,:cdc_internal_comments)";


		// Se Inicia la transaccion,
		// Todas las consultas de modificacion siguientes
		// aplicaran solo despues que se confirme la transaccion,
		// de lo contrario no se aplicaran los cambios y se devolvera
		// la base de datos a su estado original.

		$this->pedeo->trans_begin();

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			':cdc_docnum' => $DocNumVerificado,
			':cdc_series' => is_numeric($Data['cdc_series']) ? $Data['cdc_series'] : 0,
			':cdc_docdate' => $this->validateDate($Data['cdc_docdate']) ? $Data['cdc_docdate'] : NULL,
			':cdc_duedate' => $this->validateDate($Data['cdc_duedate']) ? $Data['cdc_duedate'] : NULL,
			':cdc_duedev' => $this->validateDate($Data['cdc_duedev']) ? $Data['cdc_duedev'] : NULL,
			':cdc_pricelist' => is_numeric($Data['cdc_pricelist']) ? $Data['cdc_pricelist'] : 0,
			':cdc_cardcode' => isset($Data['cdc_cardcode']) ? $Data['cdc_cardcode'] : NULL,
			':cdc_cardname' => isset($Data['cdc_cardname']) ? $Data['cdc_cardname'] : NULL,
			':cdc_currency' => isset($Data['cdc_currency']) ? $Data['cdc_currency'] : NULL,
			':cdc_contacid' => isset($Data['cdc_contacid']) ? $Data['cdc_contacid'] : NULL,
			':cdc_slpcode' => is_numeric($Data['cdc_slpcode']) ? $Data['cdc_slpcode'] : 0,
			':cdc_empid' => is_numeric($Data['cdc_empid']) ? $Data['cdc_empid'] : 0,
			':cdc_comment' => isset($Data['cdc_comment']) ? $Data['cdc_comment'] : NULL,
			':cdc_doctotal' => is_numeric($Data['cdc_doctotal']) ? $Data['cdc_doctotal'] : 0,
			':cdc_baseamnt' => is_numeric($Data['cdc_baseamnt']) ? $Data['cdc_baseamnt'] : 0,
			':cdc_taxtotal' => is_numeric($Data['cdc_taxtotal']) ? $Data['cdc_taxtotal'] : 0,
			':cdc_discprofit' => is_numeric($Data['cdc_discprofit']) ? $Data['cdc_discprofit'] : 0,
			':cdc_discount' => is_numeric($Data['cdc_discount']) ? $Data['cdc_discount'] : 0,
			':cdc_createat' => $this->validateDate($Data['cdc_createat']) ? $Data['cdc_createat'] : NULL,
			':cdc_baseentry' => is_numeric($Data['cdc_baseentry']) ? $Data['cdc_baseentry'] : 0,
			':cdc_basetype' => is_numeric($Data['cdc_basetype']) ? $Data['cdc_basetype'] : 0,
			':cdc_doctype' => is_numeric($Data['cdc_doctype']) ? $Data['cdc_doctype'] : 0,
			':cdc_idadd' => isset($Data['cdc_idadd']) ? $Data['cdc_idadd'] : NULL,
			':cdc_adress' => isset($Data['cdc_adress']) ? $Data['cdc_adress'] : NULL,
			':cdc_paytype' => is_numeric($Data['cdc_paytype']) ? $Data['cdc_paytype'] : 0,
			':cdc_createby' => isset($Data['cdc_createby']) ? $Data['cdc_createby'] : NULL,
			':business' => isset($Data['business']) ? $Data['business'] : NULL,
			':branch' => isset($Data['branch']) ? $Data['branch'] : NULL,
			':cdc_internal_comments' => isset($Data['cdc_internal_comments']) ? $Data['cdc_internal_comments'] : NULL
		));

		if (is_numeric($resInsert) && $resInsert > 0) {

			// Se actualiza la serie de la numeracion del documento

			$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id'      => $Data['cdc_series']
			));


			if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
			} else {
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'    => $resActualizarNumeracion,
					'mensaje'	=> 'No se pudo crear la devolucion de compras'
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
				':bed_doctype' => $Data['cdc_doctype'],
				':bed_status' => 3, //ESTADO CERRADO
				':bed_createby' => $Data['cdc_createby'],
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
					'mensaje'	=> 'No se pudo registrar la devolucion de compras'
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
				':mac_base_type' => is_numeric($Data['cdc_doctype']) ? $Data['cdc_doctype'] : 0,
				':mac_base_entry' => $resInsert,
				':mac_doc_date' => $this->validateDate($Data['cdc_docdate']) ? $Data['cdc_docdate'] : NULL,
				':mac_doc_duedate' => $this->validateDate($Data['cdc_duedate']) ? $Data['cdc_duedate'] : NULL,
				':mac_legal_date' => $this->validateDate($Data['cdc_docdate']) ? $Data['cdc_docdate'] : NULL,
				':mac_ref1' => is_numeric($Data['cdc_doctype']) ? $Data['cdc_doctype'] : 0,
				':mac_ref2' => "",
				':mac_ref3' => "",
				':mac_loc_total' => is_numeric($Data['cdc_doctotal']) ? $Data['cdc_doctotal'] : 0,
				':mac_fc_total' => is_numeric($Data['cdc_doctotal']) ? $Data['cdc_doctotal'] : 0,
				':mac_sys_total' => is_numeric($Data['cdc_doctotal']) ? $Data['cdc_doctotal'] : 0,
				':mac_trans_dode' => 1,
				':mac_beline_nume' => 1,
				':mac_vat_date' => $this->validateDate($Data['cdc_docdate']) ? $Data['cdc_docdate'] : NULL,
				':mac_serie' => 1,
				':mac_number' => 1,
				':mac_bammntsys' => is_numeric($Data['cdc_baseamnt']) ? $Data['cdc_baseamnt'] : 0,
				':mac_bammnt' => is_numeric($Data['cdc_baseamnt']) ? $Data['cdc_baseamnt'] : 0,
				':mac_wtsum' => 1,
				':mac_vatsum' => is_numeric($Data['cdc_taxtotal']) ? $Data['cdc_taxtotal'] : 0,
				':mac_comments' => isset($Data['cdc_comment']) ? $Data['cdc_comment'] : NULL,
				':mac_create_date' => $this->validateDate($Data['cdc_createat']) ? $Data['cdc_createat'] : NULL,
				':mac_made_usuer' => isset($Data['cdc_createby']) ? $Data['cdc_createby'] : NULL,
				':mac_update_date' => date("Y-m-d"),
				':mac_update_user' => isset($Data['cdc_createby']) ? $Data['cdc_createby'] : NULL,
				':mac_accperiod' => $periodo['data'],
				':business' => $Data['business'],
				':branch' => $Data['branch']
			));


			if (is_numeric($resInsertAsiento) && $resInsertAsiento > 0) {
				// Se verifica que el detalle no de error insertando //
				
			} else {

				// si falla algun insert del detalle de la devolucion de compras se devuelven los cambios realizados por la transaccion,
				// se retorna el error y se detiene la ejecucion del codigo restante.
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'	  => $resInsertAsiento,
					'mensaje'	=> 'No se pudo registrar la devolucion de compras'
				);

				$this->response($respuesta);

				return;
			}


			//SE APLICA PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS
			if (isset($Data['cdc_baseentry']) && is_numeric($Data['cdc_baseentry']) && isset($Data['cdc_basetype']) && is_numeric($Data['cdc_basetype'])) {

				$sqlDocInicio = "SELECT bmd_tdi, bmd_ndi FROM tbmd WHERE  bmd_doctype = :bmd_doctype AND bmd_docentry = :bmd_docentry";
				$resDocInicio = $this->pedeo->queryTable($sqlDocInicio, array(
					':bmd_doctype' => $Data['cdc_basetype'],
					':bmd_docentry' => $Data['cdc_baseentry']
				));


				if (isset($resDocInicio[0])) {

					$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
									bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency)
									VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
									:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency)";

					$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

						':bmd_doctype' => is_numeric($Data['cdc_doctype']) ? $Data['cdc_doctype'] : 0,
						':bmd_docentry' => $resInsert,
						':bmd_createat' => $this->validateDate($Data['cdc_createat']) ? $Data['cdc_createat'] : NULL,
						':bmd_doctypeo' => is_numeric($Data['cdc_basetype']) ? $Data['cdc_basetype'] : 0, //ORIGEN
						':bmd_docentryo' => is_numeric($Data['cdc_baseentry']) ? $Data['cdc_baseentry'] : 0,  //ORIGEN
						':bmd_tdi' => $resDocInicio[0]['bmd_tdi'], // DOCUMENTO INICIAL
						':bmd_ndi' => $resDocInicio[0]['bmd_ndi'], // DOCUMENTO INICIAL
						':bmd_docnum' => $DocNumVerificado,
						':bmd_doctotal' => is_numeric($Data['cdc_doctotal']) ? $Data['cdc_doctotal'] : 0,
						':bmd_cardcode' => isset($Data['cdc_cardcode']) ? $Data['cdc_cardcode'] : NULL,
						':bmd_cardtype' => 2,
						':bmd_currency' => isset($Data['cdc_currency'])?$Data['cdc_currency']:NULL,
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

						':bmd_doctype' => is_numeric($Data['cdc_doctype']) ? $Data['cdc_doctype'] : 0,
						':bmd_docentry' => $resInsert,
						':bmd_createat' => $this->validateDate($Data['cdc_createat']) ? $Data['cdc_createat'] : NULL,
						':bmd_doctypeo' => is_numeric($Data['cdc_basetype']) ? $Data['cdc_basetype'] : 0, //ORIGEN
						':bmd_docentryo' => is_numeric($Data['cdc_baseentry']) ? $Data['cdc_baseentry'] : 0,  //ORIGEN
						':bmd_tdi' => is_numeric($Data['cdc_doctype']) ? $Data['cdc_doctype'] : 0, // DOCUMENTO INICIAL
						':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
						':bmd_docnum' => $DocNumVerificado,
						':bmd_doctotal' => is_numeric($Data['cdc_doctotal']) ? $Data['cdc_doctotal'] : 0,
						':bmd_cardcode' => isset($Data['cdc_cardcode']) ? $Data['cdc_cardcode'] : NULL,
						':bmd_cardtype' => 2,
						':bmd_currency' => isset($Data['cdc_currency'])?$Data['cdc_currency']:NULL,
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
					':bmd_doctype' => is_numeric($Data['cdc_doctype']) ? $Data['cdc_doctype'] : 0,
					':bmd_docentry' => $resInsert,
					':bmd_createat' => $this->validateDate($Data['cdc_createat']) ? $Data['cdc_createat'] : NULL,
					':bmd_doctypeo' => is_numeric($Data['cdc_basetype']) ? $Data['cdc_basetype'] : 0, //ORIGEN
					':bmd_docentryo' => is_numeric($Data['cdc_baseentry']) ? $Data['cdc_baseentry'] : 0,  //ORIGEN
					':bmd_tdi' => is_numeric($Data['cdc_doctype']) ? $Data['cdc_doctype'] : 0, // DOCUMENTO INICIAL
					':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
					':bmd_docnum' => $DocNumVerificado,
					':bmd_doctotal' => is_numeric($Data['cdc_doctotal']) ? $Data['cdc_doctotal'] : 0,
					':bmd_cardcode' => isset($Data['cdc_cardcode']) ? $Data['cdc_cardcode'] : NULL,
					':bmd_cardtype' => 2,
					':bmd_currency' => isset($Data['cdc_currency'])?$Data['cdc_currency']:NULL,
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

				$CANTUOMPURCHASE = $this->generic->getUomPurchase($detail['dc1_itemcode']);
				$CANTUOMSALE = $this->generic->getUomSale($detail['dc1_itemcode']);

				if ($CANTUOMPURCHASE == 0) {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' 		=> $detail['dc1_quantity'],
						'mensaje'	=> 'No se encontro la equivalencia de la unidad de medida para el item: ' . $detail['dc1_itemcode']
					);

					$this->response($respuesta);

					return;
				}

				$sqlInsertDetail = "INSERT INTO cdc1(dc1_docentry,dc1_itemcode, dc1_itemname, dc1_quantity, dc1_uom, dc1_whscode,
                                    dc1_price, dc1_vat, dc1_vatsum, dc1_discount, dc1_linetotal, dc1_costcode, dc1_ubusiness, dc1_project,
                                    dc1_acctcode, dc1_basetype, dc1_doctype, dc1_avprice, dc1_inventory, dc1_acciva, dc1_linenum, dc1_codimp,
									dc1_baseline,ote_code,dc1_gift,dc1_tax_base,deducible, dc1_codmunicipality)
									VALUES(:dc1_docentry,:dc1_itemcode, :dc1_itemname, :dc1_quantity,:dc1_uom, :dc1_whscode,:dc1_price, :dc1_vat, 
									:dc1_vatsum, :dc1_discount, :dc1_linetotal, :dc1_costcode, :dc1_ubusiness, :dc1_project,:dc1_acctcode, 
									:dc1_basetype, :dc1_doctype, :dc1_avprice, :dc1_inventory, :dc1_acciva, :dc1_linenum, :dc1_codimp,
									:dc1_baseline,:ote_code,:dc1_gift,:dc1_tax_base,:deducible, :dc1_codmunicipality)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':dc1_docentry' => $resInsert,
					':dc1_itemcode' => isset($detail['dc1_itemcode']) ? $detail['dc1_itemcode'] : NULL,
					':dc1_itemname' => isset($detail['dc1_itemname']) ? $detail['dc1_itemname'] : NULL,
					':dc1_quantity' => is_numeric($detail['dc1_quantity']) ? $detail['dc1_quantity'] : 0,
					':dc1_uom' => isset($detail['dc1_uom']) ? $detail['dc1_uom'] : NULL,
					':dc1_whscode' => isset($detail['dc1_whscode']) ? $detail['dc1_whscode'] : NULL,
					':dc1_price' => is_numeric($detail['dc1_price']) ? $detail['dc1_price'] : 0,
					':dc1_vat' => is_numeric($detail['dc1_vat']) ? $detail['dc1_vat'] : 0,
					':dc1_vatsum' => is_numeric($detail['dc1_vatsum']) ? $detail['dc1_vatsum'] : 0,
					':dc1_discount' => is_numeric($detail['dc1_discount']) ? $detail['dc1_discount'] : 0,
					':dc1_linetotal' => is_numeric($detail['dc1_linetotal']) ? $detail['dc1_linetotal'] : 0,
					':dc1_costcode' => isset($detail['dc1_costcode']) ? $detail['dc1_costcode'] : NULL,
					':dc1_ubusiness' => isset($detail['dc1_ubusiness']) ? $detail['dc1_ubusiness'] : NULL,
					':dc1_project' => isset($detail['dc1_project']) ? $detail['dc1_project'] : NULL,
					':dc1_acctcode' => is_numeric($detail['dc1_acctcode']) ? $detail['dc1_acctcode'] : 0,
					':dc1_basetype' => is_numeric($detail['dc1_basetype']) ? $detail['dc1_basetype'] : 0,
					':dc1_doctype' => is_numeric($detail['dc1_doctype']) ? $detail['dc1_doctype'] : 0,
					':dc1_avprice' => is_numeric($detail['dc1_avprice']) ? $detail['dc1_avprice'] : 0,
					':dc1_inventory' => is_numeric($detail['dc1_inventory']) ? $detail['dc1_inventory'] : NULL,
					':dc1_acciva'  => is_numeric($detail['dc1_cuentaIva']) ? $detail['dc1_cuentaIva'] : 0,
					':dc1_linenum' => is_numeric($detail['dc1_linenum']) ? $detail['dc1_linenum'] : 0,
					':dc1_codimp' => isset($detail['dc1_codimp']) ? $detail['dc1_codimp'] : NULL,
					':dc1_baseline' => is_numeric($detail['dc1_baseline']) ? $detail['dc1_baseline'] : 0,
					':ote_code' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
					':dc1_gift' => is_numeric($detail['dc1_gift']) ? $detail['dc1_gift'] : 0,
					':dc1_tax_base' => is_numeric($detail['dc1_tax_base']) ? $detail['dc1_tax_base'] : 0,
					':deducible' => isset($detail['deducible']) ? $detail['deducible'] : NULL,
					':dc1_codmunicipality' => isset($detail['dc1_codmunicipality']) ? $detail['dc1_codmunicipality'] : NULL
				));

				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// Se verifica que el detalle no de error insertando //
					//VALIDAR SI LOS ITEMS SON IGUALES A LOS DEL DOCUMENTO DE ORIGEN SIEMPRE QUE VENGA DE UN COPIAR DE
					if($Data['cdc_basetype'] == 13){
						//OBTENER NUMERO DOCUMENTO ORIGEN
						$DOC = "SELECT cec_docnum FROM dcec WHERE cec_doctype = :cec_doctype AND cec_docentry = :cec_docentry";
						$RESULT_DOC = $this->pedeo->queryTable($DOC,array(':cec_docentry' =>$Data['cdc_baseentry'],':cec_doctype' => $Data['cdc_basetype']));
						foreach ($ContenidoDetalle as $key => $value) {
							# code...
							//VALIDAR SI EL ARTICULO DEL DOCUMENTO ACTUAL EXISTE EN EL DOCUMENTO DE ORIGEN
							$sql = "SELECT dcec.cec_docnum,cec1.ec1_itemcode FROM dcec INNER JOIN cec1 ON dcec.cec_docentry = cec1.ec1_docentry 
							WHERE dcec.cec_docentry = :cec_docentry AND dcec.cec_doctype = :cec_doctype AND cec1.ec1_itemcode = :ec1_itemcode";
							$resSql = $this->pedeo->queryTable($sql,array(
								':cec_docentry' =>$Data['cdc_baseentry'],
								':cec_doctype' => $Data['cdc_basetype'],
								':ec1_itemcode' => $value['dc1_itemcode']
							));
							
								if(isset($resSql[0])){
									//EL ARTICULO EXISTE EN EL DOCUMENTO DE ORIGEN
								}else {
									//EL ARTICULO NO EXISTE EN EL DOCUEMENTO DE ORIGEN
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data' => $value['dc1_itemcode'],
										'mensaje'	=> 'El Item '.$value['dc1_itemcode'].' no existe en el documento origen (Orden #'.$RESULT_DOC[0]['cec_docnum'].')'
									);

									$this->response($respuesta);

									return;
								}
							}

					}
				} else {

					// si falla algun insert del detalle de la devolucion de compras se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertDetail,
						'mensaje'	=> 'No se pudo registrar la devolucion de compras'
					);

					$this->response($respuesta);

					return;
				}


				// SE VERIFICA SI EL ARTICULO ESTA MARCADO PARA MANEJARSE EN INVENTARIO
				// Y LOTE
				$sqlItemINV = "SELECT coalesce(dma_item_inv, '0') as dma_item_inv,coalesce(dma_use_tbase,0) as dma_use_tbase, coalesce(dma_tasa_base,0) as dma_tasa_base FROM dmar WHERE dma_item_code = :dma_item_code";
				$resItemINV = $this->pedeo->queryTable($sqlItemINV, array(

					':dma_item_code' => $detail['dc1_itemcode']
				));

				if ( isset($resItemINV[0]) && $resItemINV[0]['dma_item_inv'] == '1' ) {

					$ManejaInvetario = 1;
					$ResultadoInv  = 1;


					// CONSULTA PARA VERIFICAR SI EL ALMACEN MANEJA UBICACION
					$sqlubicacion = "SELECT * FROM dmws WHERE dws_ubication = :dws_ubication AND dws_code = :dws_code AND business = :business";
					$resubicacion = $this->pedeo->queryTable($sqlubicacion, array(
						':dws_ubication' => 1,
						':dws_code' => $detail['dc1_whscode'],
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

						':dma_item_code' => $detail['dc1_itemcode'],
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
				// FIN PROCESO ITEM MANEJA INVENTARIO
				// Y LOTE

				// SE VERIFICA SI EL ARTICULO MANEJA TASA
				if ( isset($resItemINV[0]) && $resItemINV[0]['dma_use_tbase'] == 1 ) {
					$ManejaTasa = 1;
					$MontoTasa = $resItemINV[0]['dma_tasa_base'];
				}else{
					$ManejaTasa = 0;
					$MontoTasa = 0;
				}
				// FIN PROCESO MANEJA TASA

				// si el item es inventariable
				if ($ManejaInvetario == 1) {

					//SE VERIFICA SI EL ARTICULO MANEJA SERIAL
					$sqlItemSerial = "SELECT dma_series_code FROM dmar WHERE  dma_item_code = :dma_item_code AND dma_series_code = :dma_series_code";
					$resItemSerial = $this->pedeo->queryTable($sqlItemSerial, array(

						':dma_item_code' => $detail['dc1_itemcode'],
						':dma_series_code'  => 1
					));

					if (isset($resItemSerial[0])) {
						$ManejaSerial = 1;

						$AddSerial = $this->generic->addSerial($detail['serials'], $detail['dc1_itemcode'], $Data['cdc_doctype'], $resInsert, $DocNumVerificado, $Data['cdc_docdate'], 2, $Data['cdc_comment'], $detail['dc1_whscode'], $detail['dc1_quantity'], $Data['cdc_createby'],$resInsertDetail,$Data['business']);

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


					//se busca el costo del item en el momento de la creacion del documento de compra
					// para almacenar en el movimiento de inventario
					$sqlCostoMomentoRegistro = '';
					$resCostoMomentoRegistro = [];


					// SI MANEJA UBICACION EL ALMACEN

					if ( $ManejaUbicacion == 1 ){
						if ($ManejaLote == 1) {
							$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_lote = :bdi_lote AND bdi_ubication = :bdi_ubication AND business = :business";
							$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
								':bdi_whscode' => $detail['dc1_whscode'],
								':bdi_itemcode' => $detail['dc1_itemcode'],
								':bdi_lote' => $detail['ote_code'],
								':bdi_ubication' => $detail['dc1_ubication'],
								':business' => $Data['business']
							));
						}else{
							$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode  AND bdi_ubication = :bdi_ubication AND business = :business";
							$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
								':bdi_whscode' => $detail['dc1_whscode'],
								':bdi_itemcode' => $detail['dc1_itemcode'],
								':bdi_ubication' => $detail['dc1_ubication'],
								':business' => $Data['business']
							));
						}
					}else{
						//SI MANEJA LOTE
						if ($ManejaLote == 1) {

							$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_lote = :bdi_lote AND business = :business";
							$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
								':bdi_whscode' => $detail['dc1_whscode'],
								':bdi_itemcode' => $detail['dc1_itemcode'],
								':bdi_lote' => $detail['ote_code'],
								':business' => $Data['business']
							));
						} else {
							$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND business = :business";
							$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(':bdi_whscode' => $detail['dc1_whscode'], ':bdi_itemcode' => $detail['dc1_itemcode'], ':business' => $Data['business']));
						}
					}

				



					if (isset($resCostoMomentoRegistro[0])) {
						//VALIDANDO CANTIDAD DE ARTICULOS

						$CANT_ARTICULOEX = $resCostoMomentoRegistro[0]['bdi_quantity'];
						$CANT_ARTICULOLN = is_numeric($detail['dc1_quantity']) ? $this->generic->getCantInv($detail['dc1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE) : 0;

						if (($CANT_ARTICULOEX - $CANT_ARTICULOLN) < 0) {

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'    => true,
								'data'     => [],
								'mensaje'  => 'no puede crear el documento porque el articulo ' . $detail['dc1_itemcode'] . ' recae en inventario negativo (' . ($CANT_ARTICULOEX - $CANT_ARTICULOLN) . ')'
							);

							$this->response($respuesta);

							return;
						}

						//VALIDANDO CANTIDAD DE ARTICULOS
						$sqlInserMovimiento = '';
						$resInserMovimiento = [];
						
						//Se aplica el movimiento de inventario
						$sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode,bmi_quantity,bmi_whscode,bmi_createat,bmi_createby,bmy_doctype,bmy_baseentry,bmi_cost,bmi_currequantity,bmi_basenum,bmi_docdate,bmi_duedate,bmi_duedev,bmi_comment,bmi_lote,bmi_ubication)
							VALUES (:bmi_itemcode,:bmi_quantity, :bmi_whscode,:bmi_createat,:bmi_createby,:bmy_doctype,:bmy_baseentry,:bmi_cost,:bmi_currequantity,:bmi_basenum,:bmi_docdate,:bmi_duedate,:bmi_duedev,:bmi_comment,:bmi_lote,:bmi_ubication)";

						$resInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(

							':bmi_itemcode'  => isset($detail['dc1_itemcode']) ? $detail['dc1_itemcode'] : NULL,
							':bmi_quantity'  => ($this->generic->getCantInv($detail['dc1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE) * $Data['invtype']),
							// ':bmi_quantity'  => is_numeric($detail['dc1_quantity']) ? (($detail['dc1_quantity'] * $CANTUOMPURCHASE)  * $Data['invtype']) : 0,
							':bmi_whscode'   => isset($detail['dc1_whscode']) ? $detail['dc1_whscode'] : NULL,
							':bmi_createat'  => $this->validateDate($Data['cdc_createat']) ? $Data['cdc_createat'] : NULL,
							':bmi_createby'  => isset($Data['cdc_createby']) ? $Data['cdc_createby'] : NULL,
							':bmy_doctype'   => is_numeric($Data['cdc_doctype']) ? $Data['cdc_doctype'] : 0,
							':bmy_baseentry' => $resInsert,
							':bmi_cost'      => $resCostoMomentoRegistro[0]['bdi_avgprice'],
							':bmi_currequantity' 	=> $resCostoMomentoRegistro[0]['bdi_quantity'],
							':bmi_basenum'			=> $DocNumVerificado,
							':bmi_docdate' => $this->validateDate($Data['cdc_docdate']) ? $Data['cdc_docdate'] : NULL,
							':bmi_duedate' => $this->validateDate($Data['cdc_duedate']) ? $Data['cdc_duedate'] : NULL,
							':bmi_duedev'  => $this->validateDate($Data['cdc_duedev']) ? $Data['cdc_duedev'] : NULL,
							':bmi_comment' => isset($Data['cdc_comment']) ? $Data['cdc_comment'] : NULL,
							':bmi_lote'    => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
							':bmi_ubication' => isset($detail['dc1_ubication']) ? $detail['dc1_ubication'] : NULL
						));
				
						
					


						if (is_numeric($resInserMovimiento) && $resInserMovimiento > 0) {
						
						} else {

							
							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' => $resInserMovimiento,
								'mensaje'	=> 'No se pudo registrar la devolucion de compras'
							);

							$this->response($respuesta);

							return;
						}
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' => $resCostoMomentoRegistro,
							'mensaje'	=> 'No se pudo registrar la devolucion de compras, no se encontro el costo del articulo'
						);

						$this->response($respuesta);

						return;
					}

					//FIN aplicacion de movimiento de inventario


					//Se Aplica el movimiento en stock ***************
					// Buscando item en el stock
					$sqlCostoCantidad = '';
					$resCostoCantidad = [];
					$CantidadPorAlmacen = 0;
					$CostoPorAlmacen = 0;

					// SE VALIDA SI EL ALMACEN MANEJA UBICACION
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

								':bdi_itemcode'  => $detail['dc1_itemcode'],
								':bdi_whscode'   => $detail['dc1_whscode'],
								':bdi_lote'      => $detail['ote_code'],
								':bdi_ubication' => $detail['dc1_ubication'],
								':business' 	 => $Data['business']
							));
						}else{
							$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
							FROM tbdi
							WHERE bdi_itemcode = :bdi_itemcode
							AND bdi_whscode = :bdi_whscode
							AND bdi_ubication = :bdi_ubication
							AND business = :business";

							$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(

								':bdi_itemcode'  => $detail['dc1_itemcode'],
								':bdi_whscode'   => $detail['dc1_whscode'],
								':bdi_ubication' => $detail['dc1_ubication'], 
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

								':bdi_itemcode' => $detail['dc1_itemcode'],
								':bdi_whscode'  => $detail['dc1_whscode'],
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

								':bdi_itemcode' => $detail['dc1_itemcode'],
								':bdi_whscode'  => $detail['dc1_whscode'],
								':business'		=> $Data['business']
							));
						}
					}

					// se busca la cantidad general del articulo agrupando todos los almacenes y lotes
					$sqlCGA = "SELECT sum(COALESCE(bdi_quantity, 0)) as bdi_quantity, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode AND business = :business GROUP BY bdi_whscode, bdi_avgprice";
					$resCGA = $this->pedeo->queryTable($sqlCGA, array(
						':bdi_itemcode' => $detail['dc1_itemcode'],
						':bdi_whscode'  => $detail['dc1_whscode'],
						':business' 	=> $Data['business']
					));

					if (isset($resCGA[0]['bdi_quantity']) && is_numeric($resCGA[0]['bdi_quantity'])) {

						$CantidadPorAlmacen = $resCGA[0]['bdi_quantity'];
						$CostoPorAlmacen = $resCGA[0]['bdi_avgprice'];
					} else {

						$CantidadPorAlmacen = 0;
						$CostoPorAlmacen = 0;
					}
					


					if (isset($resCostoCantidad[0])) {

						if ( $resCostoCantidad[0]['bdi_quantity'] > 0 && $CantidadPorAlmacen > 0 ) {

							$CantidadActual = $CantidadPorAlmacen;
							// $CantidadDevolucion = ($detail['dc1_quantity'] * $CANTUOMPURCHASE);
							$CantidadDevolucion = $this->generic->getCantInv($detail['dc1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE);
							
							$CostoActual = $CostoPorAlmacen;
							$CantidadTotal = ($CantidadActual - $CantidadDevolucion);
							$CantidadArticulo = $resCostoCantidad[0]['bdi_quantity'];
							$CantidadTotalArticulo = ($CantidadArticulo - $CantidadDevolucion);

						


							if ( $CantidadTotalArticulo < 0 ){

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' 		=> $resCostoCantidad,
									'mensaje'	=> 'El item ' . $detail['dc1_itemcode'].' recae en inventario negativo '.$CantidadTotalArticulo
								);

								$this->response($respuesta);

								return;
							}



							$sqlUpdateCostoCantidad =  "UPDATE tbdi
													SET bdi_quantity = :bdi_quantity
													WHERE  bdi_id = :bdi_id";

							$resUpdateCostoCantidad = $this->pedeo->updateRow($sqlUpdateCostoCantidad, array(

								':bdi_quantity' => $CantidadTotalArticulo,
								':bdi_id' 		=> $resCostoCantidad[0]['bdi_id']
							));

							if (is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad == 1) {
							} else {

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resUpdateCostoCantidad,
									'mensaje'	=> 'No se pudo crear la devolucion de compras'
								);

								$this->response($respuesta);

								return;
							}


						} else {

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data'    => $resCostoCantidad,
								'mensaje' => 'No hay existencia para el item: ' . $detail['dc1_itemcode']
							);

							$this->response($respuesta);

							return;
						}
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' 		=> $resCostoCantidad,
							'mensaje'	=> 'El item no existe en el stock ' . $detail['dc1_itemcode']
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
								':ote_createby' => $Data['cdc_createby'],
								':ote_date' => date('Y-m-d'),
								':ote_baseentry' => $resInsert,
								':ote_basetype' => $Data['cdc_doctype'],
								':ote_docnum' => $DocNumVerificado
							));


							if (is_numeric($resInsertLote) && $resInsertLote > 0) {
							} else {
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' 		=> $resInsertLote,
									'mensaje'	=> 'No se pudo registrar la devolucion de compras'
								);

								$this->response($respuesta);

								return;
							}
						}
					}
					//FIN VALIDACION DEL LOTE
				} else { // VALIDA QUE NO SE ESTA HACIENDO DEVOLUCION A SOLO SERVICIOS, EN ESE CASO SE QUITA LA CABECERA GENERADA EN LA TMAC 

					$ContadorItenmnoInv++;
						
					if ( $ContadorItenmnoInv == count($ContenidoDetalle) ){

						
						$deleteRes = $this->pedeo->deleteRow("DELETE FROM tmac WHERE mac_trans_id = :mac_trans_id", array("mac_trans_id" => $resInsertAsiento));

						if ( is_numeric($deleteRes) && $deleteRes == 1 ){

						}else{


							$this->pedeo->trans_rollback();

							$respuesta = array(
									'error'   => true,
									'data'    => $deleteRes,
									'mensaje'	=> 'No se pudo crear la devolución de compra'
							);

							$this->response($respuesta);

							return;
						}
						
					}
				}

				//LLENANDO DETALLE ASIENTO CONTABLES
				$DetalleCuentaPuente = new stdClass();
				$DetalleCuentaInvetario = new stdClass();
				// SI EL ITEM ES INVENTARIABLE
				if ($ManejaInvetario == 1) {

					$DetalleCuentaPuente->dc1_account = is_numeric($detail['dc1_acctcode']) ? $detail['dc1_acctcode'] : 0;
					$DetalleCuentaPuente->dc1_prc_code = isset($detail['dc1_costcode']) ? $detail['dc1_costcode'] : NULL;
					$DetalleCuentaPuente->dc1_uncode = isset($detail['dc1_ubusiness']) ? $detail['dc1_ubusiness'] : NULL;
					$DetalleCuentaPuente->dc1_prj_code = isset($detail['dc1_project']) ? $detail['dc1_project'] : NULL;
					$DetalleCuentaPuente->dc1_linetotal = is_numeric($detail['dc1_linetotal']) ? $detail['dc1_linetotal'] : 0;
					$DetalleCuentaPuente->dc1_vat = is_numeric($detail['dc1_vat']) ? $detail['dc1_vat'] : 0;
					$DetalleCuentaPuente->dc1_vatsum = is_numeric($detail['dc1_vatsum']) ? $detail['dc1_vatsum'] : 0;
					$DetalleCuentaPuente->dc1_price = is_numeric($detail['dc1_price']) ? ( ( $this->costobo->validateCost( $ManejaTasa,$MontoTasa,$detail['dc1_price'],$detail['dc1_vat'],$detail['dc1_discount'],$detail['dc1_quantity'] ) /  $CANTUOMPURCHASE ) * $CANTUOMSALE ) : 0;
					// $DetalleCuentaPuente->dc1_price = is_numeric($detail['dc1_price']) ? $detail['dc1_price'] : 0;
					$DetalleCuentaPuente->dc1_itemcode = isset($detail['dc1_itemcode']) ? $detail['dc1_itemcode'] : NULL;
					$DetalleCuentaPuente->dc1_quantity = $this->generic->getCantInv($detail['dc1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE);
					$DetalleCuentaPuente->dc1_whscode = isset($detail['dc1_whscode']) ? $detail['dc1_whscode'] : NULL;


					$DetalleCuentaInvetario->dc1_account = is_numeric($detail['dc1_acctcode']) ? $detail['dc1_acctcode'] : 0;
					$DetalleCuentaInvetario->dc1_prc_code = isset($detail['dc1_costcode']) ? $detail['dc1_costcode'] : NULL;
					$DetalleCuentaInvetario->dc1_uncode = isset($detail['dc1_ubusiness']) ? $detail['dc1_ubusiness'] : NULL;
					$DetalleCuentaInvetario->dc1_prj_code = isset($detail['dc1_project']) ? $detail['dc1_project'] : NULL;
					$DetalleCuentaInvetario->dc1_linetotal = is_numeric($detail['dc1_linetotal']) ? $detail['dc1_linetotal'] : 0;
					$DetalleCuentaInvetario->dc1_vat = is_numeric($detail['dc1_vat']) ? $detail['dc1_vat'] : 0;
					$DetalleCuentaInvetario->dc1_vatsum = is_numeric($detail['dc1_vatsum']) ? $detail['dc1_vatsum'] : 0;
					$DetalleCuentaInvetario->dc1_price = is_numeric($detail['dc1_price']) ? ( ( $this->costobo->validateCost( $ManejaTasa,$MontoTasa,$detail['dc1_price'],$detail['dc1_vat'],$detail['dc1_discount'],$detail['dc1_quantity'] ) /  $CANTUOMPURCHASE ) * $CANTUOMSALE ) : 0;
					// $DetalleCuentaInvetario->dc1_price = is_numeric($detail['dc1_price']) ? $detail['dc1_price'] : 0;
					$DetalleCuentaInvetario->dc1_itemcode = isset($detail['dc1_itemcode']) ? $detail['dc1_itemcode'] : NULL;
					$DetalleCuentaInvetario->dc1_quantity = $this->generic->getCantInv($detail['dc1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE);
					$DetalleCuentaInvetario->dc1_whscode = isset($detail['dc1_whscode']) ? $detail['dc1_whscode'] : NULL;



					$llaveCuentaPuente = $DetalleCuentaPuente->dc1_uncode . $DetalleCuentaPuente->dc1_prc_code . $DetalleCuentaPuente->dc1_prj_code . $DetalleCuentaPuente->dc1_account;
					$llaveCuentaInvetario = $DetalleCuentaInvetario->dc1_uncode . $DetalleCuentaInvetario->dc1_prc_code . $DetalleCuentaInvetario->dc1_prj_code . $DetalleCuentaInvetario->dc1_account;


					if (in_array($llaveCuentaPuente, $inArrayCuentaPuente)) {

						$posicionCuentaPuente = $this->buscarPosicion($llaveCuentaPuente, $inArrayCuentaPuente);
					} else {

						array_push($inArrayCuentaPuente, $llaveCuentaPuente);
						$posicionCuentaPuente = $this->buscarPosicion($llaveCuentaPuente, $inArrayCuentaPuente);
					}


					if (in_array($llaveCuentaInvetario, $inArrayCuentaInvetario)) {

						$posicionCuentaInvetario = $this->buscarPosicion($llaveCuentaInvetario, $inArrayCuentaInvetario);
					} else {

						array_push($inArrayCuentaInvetario, $llaveCuentaInvetario);
						$posicionCuentaInvetario = $this->buscarPosicion($llaveCuentaInvetario, $inArrayCuentaInvetario);
					}

					if (isset($DetalleConsolidadoCuentaPuente[$posicionCuentaPuente])) {

						if (!is_array($DetalleConsolidadoCuentaPuente[$posicionCuentaPuente])) {
							$DetalleConsolidadoCuentaPuente[$posicionCuentaPuente] = array();
						}
					} else {
						$DetalleConsolidadoCuentaPuente[$posicionCuentaPuente] = array();
					}

					array_push($DetalleConsolidadoCuentaPuente[$posicionCuentaPuente], $DetalleCuentaPuente);


					if (isset($DetalleConsolidadoCuentaInventario[$posicionCuentaInvetario])) {

						if (!is_array($DetalleConsolidadoCuentaInventario[$posicionCuentaInvetario])) {
							$DetalleConsolidadoCuentaInventario[$posicionCuentaInvetario] = array();
						}
					} else {
						$DetalleConsolidadoCuentaInventario[$posicionCuentaInvetario] = array();
					}

					array_push($DetalleConsolidadoCuentaInventario[$posicionCuentaInvetario], $DetalleCuentaInvetario);
				}
			}

			// PROCEDIMEINTO PARA LLENAR LA CUENTA PUENTE INVENTARIO
			foreach ($DetalleConsolidadoCuentaInventario as $key => $posicion) {
				$grantotalCuentaPuente = 0;
				$grantotalCuentaPuenteOriginal = 0;
				$cuentaPuente = "";
				$dbito = 0;
				$cdito = 0;
				$MontoSysDB = 0;
				$MontoSysCR = 0;
				$centroCosto = '';
				$unidadNegocio = '';
				$codigoProyecto = '';
				foreach ($posicion as $key => $value) {



					$sqlArticulo = "SELECT coalesce(pge_bridge_inv_purch, 0) as pge_bridge_inv_purch, coalesce(pge_bridge_purch_int, 0) as pge_bridge_purch_int FROM pgem WHERE pge_id = :business"; // Cuenta  puente inventario
					$resArticulo = $this->pedeo->queryTable($sqlArticulo, array(':business' => $Data['business'])); // Cuenta costo puente

					$centroCosto = $value->dc1_prc_code;
					$unidadNegocio = $value->dc1_uncode;
					$codigoProyecto = $value->dc1_prj_code;

					if (isset($resArticulo[0])) {

						$dbito = 0;
						$cdito = 0;
						$MontoSysDB = 0;
						$MontoSysCR = 0;

						if (isset($Data['cdc_api']) && $Data['cdc_api'] == 1) {
							$cuentaPuente  = $resArticulo[0]['pge_bridge_purch_int'];
						} else {
							$cuentaPuente  = $resArticulo[0]['pge_bridge_inv_purch'];
						}



						$costoArticulo = $value->dc1_price;
						$cantidadArticulo = $value->dc1_quantity;
						$grantotalCuentaPuente = $grantotalCuentaPuente + ($costoArticulo * $cantidadArticulo);
					} else {
						// si falla algun insert del detalle de la devolucion de compras se devuelven los cambios realizados por la transaccion,
						// se retorna el error y se detiene la ejecucion del codigo restante.
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'	  => $resArticulo,
							'mensaje'	=> 'No se encontro la cuenta puente para inventario'
						);

						$this->response($respuesta);

						return;
					}
				}

				$codigo3 = substr($cuentaPuente, 0, 1);

				$grantotalCuentaPuenteOriginal = 	$grantotalCuentaPuente;

				if (trim($Data['cdc_currency']) != $MONEDALOCAL) {

					$grantotalCuentaPuente = ($grantotalCuentaPuente * $TasaDocLoc);
				}

				if ($codigo3 == 1 || $codigo3 == "1") {
					$dbito = 	$grantotalCuentaPuente;
					if (trim($Data['cdc_currency']) != $MONEDASYS) {
						$MontoSysDB = ($dbito / $TasaLocSys);
					} else {
						$MontoSysDB = $grantotalCuentaPuenteOriginal;
					}
				} else if ($codigo3 == 2 || $codigo3 == "2") {
					$dbito = 	$grantotalCuentaPuente;
					if (trim($Data['cdc_currency']) != $MONEDASYS) {
						$MontoSysDB = ($dbito / $TasaLocSys);
					} else {
						$MontoSysDB = $grantotalCuentaPuenteOriginal;
					}
				} else if ($codigo3 == 3 || $codigo3 == "3") {
					$dbito = 	$grantotalCuentaPuente;
					if (trim($Data['cdc_currency']) != $MONEDASYS) {
						$MontoSysDB = ($dbito / $TasaLocSys);
					} else {
						$MontoSysDB = $grantotalCuentaPuenteOriginal;
					}
				} else if ($codigo3 == 4 || $codigo3 == "4") {
					$dbito = 	$grantotalCuentaPuente;
					if (trim($Data['cdc_currency']) != $MONEDASYS) {
						$MontoSysDB = ($dbito / $TasaLocSys);
					} else {
						$MontoSysDB = $grantotalCuentaPuenteOriginal;
					}
				} else if ($codigo3 == 5  || $codigo3 == "5") {
					$dbito = 	$grantotalCuentaPuente;
					if (trim($Data['cdc_currency']) != $MONEDASYS) {
						$MontoSysDB = ($dbito / $TasaLocSys);
					} else {
						$MontoSysDB = $grantotalCuentaPuenteOriginal;
					}
				} else if ($codigo3 == 6 || $codigo3 == "6") {
					$dbito = 	$grantotalCuentaPuente;
					if (trim($Data['cdc_currency']) != $MONEDASYS) {
						$MontoSysDB = ($dbito / $TasaLocSys);
					} else {
						$MontoSysDB = $grantotalCuentaPuenteOriginal;
					}
				} else if ($codigo3 == 7 || $codigo3 == "7") {
					$dbito = 	$grantotalCuentaPuente;
					if (trim($Data['cdc_currency']) != $MONEDASYS) {
						$MontoSysDB = ($dbito / $TasaLocSys);
					} else {
						$MontoSysDB = $grantotalCuentaPuenteOriginal;
					}
				}

				// SE AGREGA AL BALANCE
				$BALANCE = $this->account->addBalance($periodo['data'], round($dbito, $DECI_MALES), $cuentaPuente, 1, $Data['cdc_docdate'], $Data['business'], $Data['branch']);
				if (isset($BALANCE['error']) && $BALANCE['error'] == true){
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error' => true,
						'data' => $BALANCE,
						'mensaje' => $BALANCE['mensaje']
					);

					return $this->response($respuesta);
				}
				$BUDGET = $this->account->validateBudgetAmount( $cuentaPuente, $Data['cdc_docdate'], $centroCosto, $unidadNegocio, $codigoProyecto, round($dbito, $DECI_MALES), 1, $Data['business'] );
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
					':ac1_account' => $cuentaPuente,
					':ac1_debit' => round($dbito, $DECI_MALES),
					':ac1_credit' => 0,
					':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
					':ac1_credit_sys' => 0,
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['cdc_docdate']) ? $Data['cdc_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['cdc_duedate']) ? $Data['cdc_duedate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => is_numeric($Data['cdc_doctype']) ? $Data['cdc_doctype'] : 0,
					':ac1_accountvs' => 1,
					':ac1_doctype' => 18,
					':ac1_ref1' => "",
					':ac1_ref2' => "",
					':ac1_ref3' => "",
					':ac1_prc_code' => $centroCosto,
					':ac1_uncode' => $unidadNegocio,
					':ac1_prj_code' => $codigoProyecto,
					':ac1_rescon_date' => NULL,
					':ac1_recon_total' => 0,
					':ac1_made_user' => isset($Data['cdc_createby']) ? $Data['cdc_createby'] : NULL,
					':ac1_accperiod' => $periodo['data'],
					':ac1_close' => 0,
					':ac1_cord' => 0,
					':ac1_ven_debit' => 1,
					':ac1_ven_credit' => 1,
					':ac1_fiscal_acct' => 0,
					':ac1_taxid' => 1,
					':ac1_isrti' => 0,
					':ac1_basert' => 0,
					':ac1_mmcode' => 0,
					':ac1_legal_num' => isset($Data['cdc_cardcode']) ? $Data['cdc_cardcode'] : NULL,
					':ac1_codref' => 1,
					':ac1_line'   => $AC1LINE ,//new line
					':business' => $Data['business'],
					':branch' => $Data['branch']
				));

				if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
					// Se verifica que el detalle no de error insertando //

				} else {

					// si falla algun insert del detalle de la devolucion de compras se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $resDetalleAsiento,
						'mensaje'	=> 'No se pudo registrar la devolucion de compra'
					);

					$this->response($respuesta);

					return;
				}
			}
			// FIN DEL PROCEDIEMIENTO PARA LLENAR LA CUENTA PUENTE


			//PROCEDIMIENTO PARA LLENAR CUENTA INVENTARIO

			foreach ($DetalleConsolidadoCuentaInventario as $key => $posicion) {
				$grantotalCuentaIventario = 0;
				$grantotalCuentaIventarioOriginal = 0;
				$cuentaInventario = "";
				$dbito = 0;
				$cdito = 0;
				$MontoSysDB = 0;
				$MontoSysCR = 0;
				$centroCosto = '';
				$unidadNegocio = '';
				$codigoProyecto = '';
				foreach ($posicion as $key => $value) {

					$CUENTASINV = $this->account->getAccountItem($value->dc1_itemcode, $value->dc1_whscode);

					$centroCosto = $value->dc1_prc_code;
					$unidadNegocio = $value->dc1_uncode;
					$codigoProyecto = $value->dc1_prj_code;

					if ( isset($CUENTASINV['error']) && $CUENTASINV['error'] == false ) {

						$dbito = 0;
						$cdito = 0;
						$MontoSysDB = 0;
						$MontoSysCR = 0;

						$cuentaInventario = $CUENTASINV['data']['acct_inv'];

						$costoArticulo =  $value->dc1_price;
						$cantidadArticulo = $value->dc1_quantity;
						$grantotalCuentaIventario = $grantotalCuentaIventario + ($costoArticulo * $cantidadArticulo);
					} else {
						// si falla algun insert del detalle de la devolucion de compras se devuelven los cambios realizados por la transaccion,
						// se retorna el error y se detiene la ejecucion del codigo restante.
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'	  => $CUENTASINV,
							'mensaje'	=> 'No se encontro la cuenta de inventario'
						);

						$this->response($respuesta);

						return;
					}
				}


				$codigo3 = substr($cuentaInventario, 0, 1);

				$grantotalCuentaIventarioOriginal = $grantotalCuentaIventario;

				if (trim($Data['cdc_currency']) != $MONEDALOCAL) {

					$grantotalCuentaIventario = ($grantotalCuentaIventario * $TasaDocLoc);
				}

				if ($codigo3 == 1 || $codigo3 == "1") {
					$cdito = 	$grantotalCuentaIventario;
					if (trim($Data['cdc_currency']) != $MONEDASYS) {
						$MontoSysCR = ($cdito / $TasaLocSys);
					} else {
						$MontoSysCR = $grantotalCuentaIventarioOriginal;
					}
				} else if ($codigo3 == 2 || $codigo3 == "2") {
					$cdito = 	$grantotalCuentaIventario;
					if (trim($Data['cdc_currency']) != $MONEDASYS) {
						$MontoSysCR = ($cdito / $TasaLocSys);
					} else {
						$MontoSysCR = $grantotalCuentaIventarioOriginal;
					}
				} else if ($codigo3 == 3 || $codigo3 == "3") {
					$cdito = 	$grantotalCuentaIventario;
					if (trim($Data['cdc_currency']) != $MONEDASYS) {
						$MontoSysCR = ($cdito / $TasaLocSys);
					} else {
						$MontoSysCR = $grantotalCuentaIventarioOriginal;
					}
				} else if ($codigo3 == 4 || $codigo3 == "4") {
					$cdito = 	$grantotalCuentaIventario;
					if (trim($Data['cdc_currency']) != $MONEDASYS) {
						$MontoSysCR = ($cdito / $TasaLocSys);
					} else {
						$MontoSysCR = $grantotalCuentaIventarioOriginal;
					}
				} else if ($codigo3 == 5  || $codigo3 == "5") {
					$cdito = 	$grantotalCuentaIventario;
					if (trim($Data['cdc_currency']) != $MONEDASYS) {
						$MontoSysCR = ($cdito / $TasaLocSys);
					} else {
						$MontoSysCR = $grantotalCuentaIventarioOriginal;
					}
				} else if ($codigo3 == 6 || $codigo3 == "6") {
					$cdito = 	$grantotalCuentaIventario;
					if (trim($Data['cdc_currency']) != $MONEDASYS) {
						$MontoSysCR = ($cdito / $TasaLocSys);
					} else {
						$MontoSysCR = $grantotalCuentaIventarioOriginal;
					}
				} else if ($codigo3 == 7 || $codigo3 == "7") {
					$cdito = 	$grantotalCuentaIventario;
					if (trim($Data['cdc_currency']) != $MONEDASYS) {
						$MontoSysCR = ($cdito / $TasaLocSys);
					} else {
						$MontoSysCR = $grantotalCuentaIventarioOriginal;
					}
				}
				$AC1LINE = $AC1LINE + 1;
				// SE AGREGA AL BALANCE
				$BALANCE = $this->account->addBalance($periodo['data'], round($cdito, $DECI_MALES), $cuentaInventario, 2, $Data['cdc_docdate'], $Data['business'], $Data['branch']);
				if (isset($BALANCE['error']) && $BALANCE['error'] == true){
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error' => true,
						'data' => $BALANCE,
						'mensaje' => $BALANCE['mensaje']
					);

					return $this->response($respuesta);
				}
				$BUDGET = $this->account->validateBudgetAmount( $cuentaInventario, $Data['cdc_docdate'], $centroCosto, $unidadNegocio, $codigoProyecto, round($cdito, $DECI_MALES), 2, $Data['business'] );
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
					':ac1_doc_date' => $this->validateDate($Data['cdc_docdate']) ? $Data['cdc_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['cdc_duedate']) ? $Data['cdc_duedate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => is_numeric($Data['cdc_doctype']) ? $Data['cdc_doctype'] : 0,
					':ac1_accountvs' => 1,
					':ac1_doctype' => 18,
					':ac1_ref1' => "",
					':ac1_ref2' => "",
					':ac1_ref3' => "",
					':ac1_prc_code' => $centroCosto,
					':ac1_uncode' => $unidadNegocio,
					':ac1_prj_code' => $codigoProyecto,
					':ac1_rescon_date' => NULL,
					':ac1_recon_total' => 0,
					':ac1_made_user' => isset($Data['cdc_createby']) ? $Data['cdc_createby'] : NULL,
					':ac1_accperiod' => $periodo['data'],
					':ac1_close' => 0,
					':ac1_cord' => 0,
					':ac1_ven_debit' => 1,
					':ac1_ven_credit' => 1,
					':ac1_fiscal_acct' => 0,
					':ac1_taxid' => 1,
					':ac1_isrti' => 0,
					':ac1_basert' => 0,
					':ac1_mmcode' => 0,
					':ac1_legal_num' => isset($Data['cdc_cardcode']) ? $Data['cdc_cardcode'] : NULL,
					':ac1_codref' => 1,
					':ac1_line'   => $AC1LINE ,//new line
					':business' => $Data['business'],
					':branch' => $Data['branch']
				));

				if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle de la devolucion de compras se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $resDetalleAsiento,
						'mensaje'	=> 'No se pudo registrar la devolucion de compras'
					);

					$this->response($respuesta);

					return;
				}
			}

			//FIN PROCEDIMIENTO PARA LLENAR CUENTA INVENTARIO

			// $sqlmac1 = "SELECT * FROM  mac1 WHERE ac1_trans_id = :ac1_trans_id";
			// $ressqlmac1 = $this->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $resInsertAsiento ));
			// print_r(json_encode($ressqlmac1));
			// exit;

			if ( $ResultadoInv == 0 ) {

				$deleteTmac = "DELETE FROM tmac WHERE mac_trans_id = :mac_trans_id";
				$resDeleteTmac = $this->pedeo->deleteRow($deleteTmac, array(
					':mac_trans_id' => $resInsertAsiento
				));

				if ( is_numeric($resDeleteTmac) && $resDeleteTmac  == 1 ){

				}else{

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' 	  => $resDeleteTmac,
						'mensaje' => 'No se pudo reversar el encabezado de la contabilidad'
					);

					$this->response($respuesta);

					return;
				}


				if ( isset($Data['preview']) && $Data['preview'] == 1 ) {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => false,
						'data' 	  => [],
						'mensaje' => 'Este documento no afectó la contabilidad'
					);
	
					return $this->response($respuesta);
	
				}


			}else{
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
			}


			//PROCEDIMIENTO PARA CERRAR ESTADO DE DOCUMENTO DE ORIGEN
			$cerrarDoc = true;
			if ($Data['cdc_basetype'] == 13) {

				
				$sqlCantidadDocOrg = "SELECT
									ec1_itemcode,
									coalesce(sum(t1.ec1_quantity),0) cantidad
								from dcec t0
								inner join cec1 t1 on t0.cec_docentry = t1.ec1_docentry
								where t0.cec_docentry = :cec_docentry and t0.cec_doctype = :cec_doctype
								group by ec1_itemcode";


				$resCantidadDocOrg = $this->pedeo->queryTable($sqlCantidadDocOrg, array(
					':cec_docentry' => $Data['cdc_baseentry'],
					':cec_doctype' => $Data['cdc_basetype']
				));

				if ( isset($resCantidadDocOrg[0]) ) {

					$ItemCantOrg = $resCantidadDocOrg; // OBTIENE EL DETALLE DEL DOCUEMENTO ORIGINAL

					// REVISANDO OTROS DOCUMENTOS
					foreach ( $resCantidadDocOrg as $key => $linea ) {

						// CASO PARA DEVOLUCION DE COMPRAS 
						$sqlDev = "SELECT
									dc1_itemcode,
									coalesce(sum(t3.dc1_quantity),0) cantidad
								from dcec t0
								left join cec1 t1 on t0.cec_docentry = t1.ec1_docentry
								left join dcdc t2 on t0.cec_docentry = t2.cdc_baseentry and t0.cec_doctype = t2.cdc_basetype
								left join cdc1 t3 on t2.cdc_docentry = t3.dc1_docentry and t1.ec1_itemcode = t3.dc1_itemcode
								where t0.cec_docentry = :cec_docentry and t0.cec_doctype = :cec_doctype
								and dc1_itemcode = :dc1_itemcode
								group by dc1_itemcode";
				
						$resDev = $this->pedeo->queryTable($sqlDev, array(
							':cec_docentry' => $Data['cdc_baseentry'],
							':cec_doctype'  => $Data['cdc_basetype'],
							':dc1_itemcode' => $linea['ec1_itemcode']
						));

						if ( isset($resDev[0])) {

							foreach ( $resDev as $key => $detalle ) {

								foreach ( $ItemCantOrg as $key => $value ) {
									if ($detalle['dc1_itemcode'] == $value['ec1_itemcode']) {

										$ItemCantOrg[$key]['cantidad'] = ( $ItemCantOrg[$key]['cantidad'] - $detalle['cantidad'] );
									}
								}
								
							}

						}

					}

					foreach ($ItemCantOrg as $key => $item) {

						if ( $item['cantidad'] > 0 ) {
							$cerrarDoc = false;
						}
						
					}

					if($cerrarDoc) {
	  
	  
						$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
						VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";
		  
						$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(
						':bed_docentry' => $Data['cdc_baseentry'],
						':bed_doctype' => $Data['cdc_basetype'],
						':bed_status' => 3, //ESTADO CERRADO
						':bed_createby' => $Data['cdc_createby'],
						':bed_date' => date('Y-m-d'),
						':bed_baseentry' => $resInsert,
						':bed_basetype' => $Data['cdc_doctype']
						));
		  
						if(is_numeric($resInsertEstado) && $resInsertEstado > 0){
		  
						}else{
		  
		  
						$this->pedeo->trans_rollback();
		  
						$respuesta = array(
						  'error'   => true,
						  'data'    => $resInsertEstado,
						  'mensaje' => 'No se pudo registrar la devolucion de compra'
						  );
						$this->response($respuesta);
		  
						return;
					  }
		  
					}


				} else {
					
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resCantidadDocOrg,
						'mensaje'	=> 'No se pudo evaluar el cierre del documento'
					);

					return $this->response($respuesta);
				}
	  
			}



			// $sqlmac1 = "SELECT * FROM  mac1 WHERE ac1_trans_id = :ac1_trans_id";
			// $ressqlmac1 = $this->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $resInsertAsiento ));
			// print_r(json_encode($ressqlmac1));
			// exit;


			//FIN DE OPERACIONES VITALES
			// Si todo sale bien despues de insertar el detalle de la devolucion de compras
			// se confirma la trasaccion  para que los cambios apliquen permanentemente
			// en la base de datos y se confirma la operacion exitosa.
			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'Devolucion de compras #'.$DocNumVerificado.' registrada con exito'
			);
		} else {
			// Se devuelven los cambios realizados en la transaccion
			// si occurre un error  y se muestra devuelve el error.
			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data' => $resInsert,
				'mensaje'	=> 'No se pudo registrar la devolucion de compras'
			);
		}



		$this->response($respuesta);
	}

	//ACTUALIZAR devolucion de compras
	public function updatePurchaseRet_post()
	{

		$Data = $this->post();

		if (
			!isset($Data['cdc_docentry']) or !isset($Data['cdc_docnum']) or
			!isset($Data['cdc_docdate']) or !isset($Data['cdc_duedate']) or
			!isset($Data['cdc_duedev']) or !isset($Data['cdc_pricelist']) or
			!isset($Data['cdc_cardcode']) or !isset($Data['cdc_cardname']) or
			!isset($Data['cdc_currency']) or !isset($Data['cdc_contacid']) or
			!isset($Data['cdc_slpcode']) or !isset($Data['cdc_empid']) or
			!isset($Data['cdc_comment']) or !isset($Data['cdc_doctotal']) or
			!isset($Data['cdc_baseamnt']) or !isset($Data['cdc_taxtotal']) or
			!isset($Data['cdc_discprofit']) or !isset($Data['cdc_discount']) or
			!isset($Data['cdc_createat']) or !isset($Data['cdc_baseentry']) or
			!isset($Data['cdc_basetype']) or !isset($Data['cdc_doctype']) or
			!isset($Data['cdc_idadd']) or !isset($Data['cdc_adress']) or
			!isset($Data['cdc_paytype']) or !isset($Data['cdc_attch']) or
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
				'mensaje' => 'No se encontro el detalle de la devolucion de compras'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlUpdate = "UPDATE dcdc	SET cdc_docdate=:cdc_docdate,cdc_duedate=:cdc_duedate, cdc_duedev=:cdc_duedev, cdc_pricelist=:cdc_pricelist, cdc_cardcode=:cdc_cardcode,
			  						cdc_cardname=:cdc_cardname, cdc_currency=:cdc_currency, cdc_contacid=:cdc_contacid, cdc_slpcode=:cdc_slpcode,
										cdc_empid=:cdc_empid, cdc_comment=:cdc_comment, cdc_doctotal=:cdc_doctotal, cdc_baseamnt=:cdc_baseamnt,
										cdc_taxtotal=:cdc_taxtotal, cdc_discprofit=:cdc_discprofit, cdc_discount=:cdc_discount, cdc_createat=:cdc_createat,
										cdc_baseentry=:cdc_baseentry, cdc_basetype=:cdc_basetype, cdc_doctype=:cdc_doctype, cdc_idadd=:cdc_idadd,
										cdc_adress=:cdc_adress, cdc_paytype=:cdc_paytype WHERE cdc_docentry=:cdc_docentry";

		$this->pedeo->trans_begin();

		$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
			':cdc_docnum' => is_numeric($Data['cdc_docnum']) ? $Data['cdc_docnum'] : 0,
			':cdc_docdate' => $this->validateDate($Data['cdc_docdate']) ? $Data['cdc_docdate'] : NULL,
			':cdc_duedate' => $this->validateDate($Data['cdc_duedate']) ? $Data['cdc_duedate'] : NULL,
			':cdc_duedev' => $this->validateDate($Data['cdc_duedev']) ? $Data['cdc_duedev'] : NULL,
			':cdc_pricelist' => is_numeric($Data['cdc_pricelist']) ? $Data['cdc_pricelist'] : 0,
			':cdc_cardcode' => isset($Data['cdc_pricelist']) ? $Data['cdc_pricelist'] : NULL,
			':cdc_cardname' => isset($Data['cdc_cardname']) ? $Data['cdc_cardname'] : NULL,
			':cdc_currency' => isset($Data['cdc_currency']) ? $Data['cdc_currency'] : NULL,
			':cdc_contacid' => isset($Data['cdc_contacid']) ? $Data['cdc_contacid'] : NULL,
			':cdc_slpcode' => is_numeric($Data['cdc_slpcode']) ? $Data['cdc_slpcode'] : 0,
			':cdc_empid' => is_numeric($Data['cdc_empid']) ? $Data['cdc_empid'] : 0,
			':cdc_comment' => isset($Data['cdc_comment']) ? $Data['cdc_comment'] : NULL,
			':cdc_doctotal' => is_numeric($Data['cdc_doctotal']) ? $Data['cdc_doctotal'] : 0,
			':cdc_baseamnt' => is_numeric($Data['cdc_baseamnt']) ? $Data['cdc_baseamnt'] : 0,
			':cdc_taxtotal' => is_numeric($Data['cdc_taxtotal']) ? $Data['cdc_taxtotal'] : 0,
			':cdc_discprofit' => is_numeric($Data['cdc_discprofit']) ? $Data['cdc_discprofit'] : 0,
			':cdc_discount' => is_numeric($Data['cdc_discount']) ? $Data['cdc_discount'] : 0,
			':cdc_createat' => $this->validateDate($Data['cdc_createat']) ? $Data['cdc_createat'] : NULL,
			':cdc_baseentry' => is_numeric($Data['cdc_baseentry']) ? $Data['cdc_baseentry'] : 0,
			':cdc_basetype' => is_numeric($Data['cdc_basetype']) ? $Data['cdc_basetype'] : 0,
			':cdc_doctype' => is_numeric($Data['cdc_doctype']) ? $Data['cdc_doctype'] : 0,
			':cdc_idadd' => isset($Data['cdc_idadd']) ? $Data['cdc_idadd'] : NULL,
			':cdc_adress' => isset($Data['cdc_adress']) ? $Data['cdc_adress'] : NULL,
			':cdc_paytype' => is_numeric($Data['cdc_paytype']) ? $Data['cdc_paytype'] : 0,
			':cdc_docentry' => $Data['cdc_docentry']
		));

		if (is_numeric($resUpdate) && $resUpdate == 1) {

			$this->pedeo->queryTable("DELETE FROM cdc1 WHERE dc1_docentry=:dc1_docentry", array(':dc1_docentry' => $Data['cdc_docentry']));

			foreach ($ContenidoDetalle as $key => $detail) {

				$sqlInsertDetail = "INSERT INTO cdc1(dc1_docentry, dc1_itemcode, dc1_itemname, dc1_quantity, dc1_uom, dc1_whscode,
																			dc1_price, dc1_vat, dc1_vatsum, dc1_discount, dc1_linetotal, dc1_costcode, dc1_ubusiness, dc1_project,
																			dc1_acctcode, dc1_basetype, dc1_doctype, dc1_avprice, dc1_inventory, dc1_acciva, dc1_linenum, dc1_codmunicipality)VALUES(:dc1_docentry, :dc1_itemcode, :dc1_itemname, :dc1_quantity,
																			:dc1_uom, :dc1_whscode,:dc1_price, :dc1_vat, :dc1_vatsum, :dc1_discount, :dc1_linetotal, :dc1_costcode, :dc1_ubusiness, :dc1_project,
																			:dc1_acctcode, :dc1_basetype, :dc1_doctype, :dc1_avprice, :dc1_inventory, :dc1_acciva, :dc1_linenum, :dc1_codmunicipality)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':dc1_docentry' => $Data['cdc_docentry'],
					':dc1_itemcode' => isset($detail['dc1_itemcode']) ? $detail['dc1_itemcode'] : NULL,
					':dc1_itemname' => isset($detail['dc1_itemname']) ? $detail['dc1_itemname'] : NULL,
					':dc1_quantity' => is_numeric($detail['dc1_quantity']) ? $detail['dc1_quantity'] : 0,
					':dc1_uom' => isset($detail['dc1_uom']) ? $detail['dc1_uom'] : NULL,
					':dc1_whscode' => isset($detail['dc1_whscode']) ? $detail['dc1_whscode'] : NULL,
					':dc1_price' => is_numeric($detail['dc1_price']) ? $detail['dc1_price'] : 0,
					':dc1_vat' => is_numeric($detail['dc1_vat']) ? $detail['dc1_vat'] : 0,
					':dc1_vatsum' => is_numeric($detail['dc1_vatsum']) ? $detail['dc1_vatsum'] : 0,
					':dc1_discount' => is_numeric($detail['dc1_discount']) ? $detail['dc1_discount'] : 0,
					':dc1_linetotal' => is_numeric($detail['dc1_linetotal']) ? $detail['dc1_linetotal'] : 0,
					':dc1_costcode' => isset($detail['dc1_costcode']) ? $detail['dc1_costcode'] : NULL,
					':dc1_ubusiness' => isset($detail['dc1_ubusiness']) ? $detail['dc1_ubusiness'] : NULL,
					':dc1_project' => isset($detail['dc1_project']) ? $detail['dc1_project'] : NULL,
					':dc1_acctcode' => is_numeric($detail['dc1_acctcode']) ? $detail['dc1_acctcode'] : 0,
					':dc1_basetype' => is_numeric($detail['dc1_basetype']) ? $detail['dc1_basetype'] : 0,
					':dc1_doctype' => is_numeric($detail['dc1_doctype']) ? $detail['dc1_doctype'] : 0,
					':dc1_avprice' => is_numeric($detail['dc1_avprice']) ? $detail['dc1_avprice'] : 0,
					':dc1_inventory' => is_numeric($detail['dc1_inventory']) ? $detail['dc1_inventory'] : NULL,
					':dc1_acciva' => is_numeric($detail['dc1_cuentaIva']) ? $detail['dc1_cuentaIva'] : 0,
					':dc1_linenum' => is_numeric($detail['dc1_linenum']) ? $detail['dc1_linenum'] : 0,
					':dc1_codmunicipality' => isset($detail['dc1_codmunicipality']) ? $detail['dc1_codmunicipality'] : NULL
				));

				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle de la devolucion de compras se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertDetail,
						'mensaje'	=> 'No se pudo registrar la devolucion de compras'
					);

					$this->response($respuesta);

					return;
				}
			}


			if ($Data['cdc_basetype'] == 13) {


				$sqlEstado1 = "SELECT
							count(t1.ec1_itemcode) item,
							sum(t1.ec1_quantity) cantidad
							from dcec t0
							inner join cec1 t1 on t0.cec_docentry = t1.ec1_docentry
							where t0.cec_docentry = :cec_docentry and t0.cec_doctype = :cec_doctype";


				$resEstado1 = $this->pedeo->queryTable($sqlEstado1, array(
					':cec_docentry' => $Data['cdc_baseentry'],
					':cec_doctype' => $Data['cdc_basetype']
				));


				$sqlEstado2 = "SELECT
							coalesce(count(t3.dv1_itemcode),0) item,
							coalesce(sum(t3.dv1_quantity),0) cantidad
							from dcec t0
							left join cec1 t1 on t0.cec_docentry = t1.ec1_docentry
							left join dcdc t2 on t0.cec_docentry = t2.cdc_baseentry
							left join cdc1 t3 on t2.cec_docentry = t3.dc1_docentry and t1.ec1_itemcode = t3.dc1_itemcode
							where t0.cec_docentry = :cec_docentry and t0.cec_doctype = :cec_doctype";
				$resEstado2 = $this->pedeo->queryTable($sqlEstado2, array(
					':cec_docentry' => $Data['cdc_baseentry'],
					':cec_doctype' => $Data['cdc_basetype']
				));

				$item_ec = $resEstado1[0]['item'];
				$item_dev = $resEstado2[0]['item'];
				$cantidad_ec = $resEstado1[0]['cantidad'];
				$cantidad_dev = $resEstado2[0]['cantidad'];

				if ($item_ec == $item_dev  &&  $cantidad_ec == $cantidad_dev) {


					$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
										VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

					$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


						':bed_docentry' => $Data['cdc_baseentry'],
						':bed_doctype' => $Data['cdc_basetype'],
						':bed_status' => 3, //ESTADO CERRADO
						':bed_createby' => $Data['cdc_createby'],
						':bed_date' => date('Y-m-d'),
						':bed_baseentry' => $Data['cdc_docentry'],
						':bed_basetype' => $Data['cdc_doctype']
					));

					if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
					} else {


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







			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resUpdate,
				'mensaje' => 'Devolucion de compras actualizada con exito'
			);
		} else {

			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data'    => $resUpdate,
				'mensaje'	=> 'No se pudo actualizar la devolucion de compras'
			);
		}

		$this->response($respuesta);
	}


	//OBTENER devolucion de compras
	public function getPurchaseRet_get()
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

		$sqlSelect = self::getColumn('dcdc', 'cdc', $campos, '', $DECI_MALES, $Data['business'], $Data['branch']);


		$resSelect = $this->pedeo->queryTable($sqlSelect, array());
		// print_r($sqlSelect);exit();die();
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


	//OBTENER devolucion de compras POR ID
	public function getPurchaseRetById_get()
	{

		$Data = $this->get();

		if (!isset($Data['cdc_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM dcdc WHERE cdc_docentry =:cdc_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":cdc_docentry" => $Data['cdc_docentry']));

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


	//OBTENER devolucion de compras DETALLE POR ID
	public function getPurchaseRetDetail_get()
	{

		$Data = $this->get();

		if (!isset($Data['dc1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT cdc1.*,dmar.dma_series_code
											 FROM cdc1
											 INNER JOIN dmar
											 ON cdc1.dc1_itemcode = dmar.dma_item_code
											 WHERE dc1_docentry = :dc1_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":dc1_docentry" => $Data['dc1_docentry']));

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




	//OBTENER devolucion de compra POR ID SOCIO DE NEGOCIO
	public function getPurchaseRetoiceBySN_get()
	{

		$Data = $this->get();

		if ( !isset($Data['dms_card_code']) OR !isset($Data['business']) OR !isset($Data['branch']) ) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM dcdc WHERE cdc_cardcode =:cdc_cardcode AND business = :business AND branch = :branch";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":cdc_cardcode" => $Data['dms_card_code'],":business" => $Data['business'],":branch" => $Data['branch']));

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

		$duplicateData = $this->documentduplicate->getDuplicate('dcdc','cdc',$Data['dms_card_code'],$Data['business']);


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

		if (!isset($Data['dc1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

			$copy = $this->documentduplicate->getDuplicateDt($Data['dc1_docentry'],'dcdc','cdc1','cdc','dc1','deducible');

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