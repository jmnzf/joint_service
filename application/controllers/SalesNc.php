<?php
// Nota crédito de clientesES
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class SalesNc extends REST_Controller
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

	}

	//CREAR NUEVA Nota crédito de clientes
	public function createSalesNc_post()
	{
		$Data = $this->post();
		$TasaDocLoc = 0;
		$TasaLocSys = 0;

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
		$DocNumVerificado = 0;
		$TasaFija = 0;
		$TotalDiferenciaSYS = 0;
		$TotalDiferenciaLOC = 0;
		$SumaCreditosSYS = 0;
		$SumaDebitosSYS = 0;
		$SumaCreditosLOC = 0;
		$SumaDebitoLOC = 0;
		$ManejaInvetario = 0;
		$ManejaUbicacion = 0;
		$ManejaLote = 0;
		$ManejaSerial = 0;
		$IVASINTASAFIJA = 0;
		$AC1LINE = 1;
		$SUMALINEAFIXRATE = 0;
		$TOTALCXCLOC = 0;
		$TOTALCXCSYS = 0;
		$TOTALCXCLOCIVA = 0;
		$TOTALCXCSYSIVA = 0;
		$exc_inv = 0;
		$CANTUOMSALE = 0; //CANTIDAD DE LA EQUIVALENCIA SEGUN LA UNIDAD DE MEDIDA DEL ITEM PARA VENTA
		$DetalleIgtf = 0; // DETALLE DE DIVISAS APLICADAS IGTF
		$DETALLE_GIFT = []; // CONTIENE EL IVA Y EL COSTO DE LOS ARTICULOS CON IMPUESTO 
		$VALUE_GIFT = 0; // ACUMULA EL VALOR PARA EL GASTO SOLO SI HAY ARTICULOS DE OBSEQUIO

		$TotalAcuRentencion = 0;
		$inArrayRetencion = array();
		$DetalleRetencion = new stdClass();
		$DetalleConsolidadoRetencion = [];
		$llaveRetencion = "";
		$posicionRetencion = 0;

		// Se globaliza la variable sqlDetalleAsiento
		$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
													ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
													ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
													ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, ac1_line, ac1_base_tax, business, branch, ac1_codret)VALUES (:ac1_trans_id, :ac1_account,
													:ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
													:ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
													:ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
													:ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref, :ac1_line, :ac1_base_tax, :business, :branch, :ac1_codret)";



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
				'mensaje' => 'No se encontro el detalle de la Nota crédito de clientes'
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

		// VALIDANDO IMPUESTO IGTF CASO PARA VENEZUELA

		if (isset($Data['dvf_igtf']) && $Data['dvf_igtf'] > 0) {

			if (!isset($Data['detailigtf'])) {

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'La informacion enviada no es valida'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}

			$DetalleIgtf = json_decode($Data['detailigtf'], true);


			if (!is_array($DetalleIgtf)) {
				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'No se pudo validar el monto en IGTF'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
		}
		//

		//VALIDANDO PERIODO CONTABLE
		$periodo = $this->generic->ValidatePeriod($Data['vnc_duedev'], $Data['vnc_docdate'], $Data['vnc_duedate'], 1);

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
		$DocNumVerificado = $this->documentnumbering->NumberDoc($Data['vnc_series'],$Data['vnc_docdate'],$Data['vnc_duedate']);
      
		if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0){
  
		}else if ($DocNumVerificado['error']){
  
		  return $this->response($DocNumVerificado, REST_Controller::HTTP_BAD_REQUEST);
		}

		//PROCESO DE TASA
		$dataTasa = $this->tasa->Tasa($Data['vnc_currency'],$Data['vnc_docdate']);

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


		$sqlInsert = "INSERT INTO dvnc(vnc_series, vnc_docnum, vnc_docdate, vnc_duedate, vnc_duedev, vnc_pricelist, vnc_cardcode,
                      vnc_cardname, vnc_currency, vnc_contacid, vnc_slpcode, vnc_empid, vnc_comment, vnc_doctotal, vnc_baseamnt, vnc_taxtotal,
                      vnc_discprofit, vnc_discount, vnc_createat, vnc_baseentry, vnc_basetype, vnc_doctype, vnc_idadd, vnc_adress, vnc_paytype,
                      vnc_createby, vnc_igtf, vnc_taxigtf, vnc_igtfapplyed, vnc_igtfcode,business,branch)VALUES(:vnc_series, :vnc_docnum, :vnc_docdate, :vnc_duedate, :vnc_duedev, :vnc_pricelist, :vnc_cardcode, :vnc_cardname,
                      :vnc_currency, :vnc_contacid, :vnc_slpcode, :vnc_empid, :vnc_comment, :vnc_doctotal, :vnc_baseamnt, :vnc_taxtotal, :vnc_discprofit, :vnc_discount,
                      :vnc_createat, :vnc_baseentry, :vnc_basetype, :vnc_doctype, :vnc_idadd, :vnc_adress, :vnc_paytype,:vnc_createby,:vnc_igtf, 
					  :vnc_taxigtf, :vnc_igtfapplyed, :vnc_igtfcode,:business,:branch)";


		// Se Inicia la transaccion,
		// Todas las consultas de modificacion siguientes
		// aplicaran solo despues que se confirme la transaccion,
		// de lo contrario no se aplicaran los cambios y se devolvera
		// la base de datos a su estado original.

		try {


			$this->pedeo->trans_begin();

			$resInsert = $this->pedeo->insertRow($sqlInsert, array(
				':vnc_docnum' => $DocNumVerificado,
				':vnc_series' => is_numeric($Data['vnc_series']) ? $Data['vnc_series'] : 0,
				':vnc_docdate' => $this->validateDate($Data['vnc_docdate']) ? $Data['vnc_docdate'] : NULL,
				':vnc_duedate' => $this->validateDate($Data['vnc_duedate']) ? $Data['vnc_duedate'] : NULL,
				':vnc_duedev' => $this->validateDate($Data['vnc_duedev']) ? $Data['vnc_duedev'] : NULL,
				':vnc_pricelist' => is_numeric($Data['vnc_pricelist']) ? $Data['vnc_pricelist'] : 0,
				':vnc_cardcode' => isset($Data['vnc_cardcode']) ? $Data['vnc_cardcode'] : NULL,
				':vnc_cardname' => isset($Data['vnc_cardname']) ? $Data['vnc_cardname'] : NULL,
				':vnc_currency' => isset($Data['vnc_currency']) ? $Data['vnc_currency'] : NULL,
				':vnc_contacid' => isset($Data['vnc_contacid']) ? $Data['vnc_contacid'] : NULL,
				':vnc_slpcode' => is_numeric($Data['vnc_slpcode']) ? $Data['vnc_slpcode'] : 0,
				':vnc_empid' => is_numeric($Data['vnc_empid']) ? $Data['vnc_empid'] : 0,
				':vnc_comment' => isset($Data['vnc_comment']) ? $Data['vnc_comment'] : NULL,
				':vnc_doctotal' => is_numeric($Data['vnc_doctotal']) ? $Data['vnc_doctotal'] : 0,
				':vnc_baseamnt' => is_numeric($Data['vnc_baseamnt']) ? $Data['vnc_baseamnt'] : 0,
				':vnc_taxtotal' => is_numeric($Data['vnc_taxtotal']) ? $Data['vnc_taxtotal'] : 0,
				':vnc_discprofit' => is_numeric($Data['vnc_discprofit']) ? $Data['vnc_discprofit'] : 0,
				':vnc_discount' => is_numeric($Data['vnc_discount']) ? $Data['vnc_discount'] : 0,
				':vnc_createat' => $this->validateDate($Data['vnc_createat']) ? $Data['vnc_createat'] : NULL,
				':vnc_baseentry' => is_numeric($Data['vnc_baseentry']) ? $Data['vnc_baseentry'] : 0,
				':vnc_basetype' => is_numeric($Data['vnc_basetype']) ? $Data['vnc_basetype'] : 0,
				':vnc_doctype' => is_numeric($Data['vnc_doctype']) ? $Data['vnc_doctype'] : 0,
				':vnc_idadd' => isset($Data['vnc_idadd']) ? $Data['vnc_idadd'] : NULL,
				':vnc_adress' => isset($Data['vnc_adress']) ? $Data['vnc_adress'] : NULL,
				':vnc_paytype' => is_numeric($Data['vnc_paytype']) ? $Data['vnc_paytype'] : 0,
				':vnc_createby' => isset($Data['vnc_createby']) ? $Data['vnc_createby'] : NULL,
				':vnc_igtf'  =>  isset($Data['dvf_igtf']) ? $Data['dvf_igtf'] : NULL,
				':vnc_taxigtf' => isset($Data['dvf_taxigtf']) ? $Data['dvf_taxigtf'] : NULL,
				':vnc_igtfapplyed' => isset($Data['dvf_igtfapplyed']) ? $Data['dvf_igtfapplyed'] : NULL,
				':vnc_igtfcode' => isset($Data['dvf_igtfcode']) ? $Data['dvf_igtfcode'] : NULL,
				':business' => isset($Data['business']) ? $Data['business'] : NULL,
				':branch' => isset($Data['branch']) ? $Data['branch'] : NULL

			));

			if (is_numeric($resInsert) && $resInsert > 0) {

				// Se actualiza la serie de la numeracion del documento

				$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																				 WHERE pgs_id = :pgs_id";
				$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
					':pgs_nextnum' => $DocNumVerificado,
					':pgs_id'      => $Data['vnc_series']
				));


				if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
				} else {
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'    => $resActualizarNumeracion,
						'mensaje'	=> 'No se pudo crear la Nota crédito de clientes'
					);

					return $this->response($respuesta);
				}
				// Fin de la actualizacion de la numeracion del documento

				//SE INSERTA EL ESTADO DEL DOCUMENTO

				$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

				$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


					':bed_docentry' => $resInsert,
					':bed_doctype' => $Data['vnc_doctype'],
					':bed_status' => 1, //ESTADO ABIERTO
					':bed_createby' => $Data['vnc_createby'],
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
						'mensaje'	=> 'No se pudo registrar la nota credito de ventas'
					);


					return $this->response($respuesta);
				}

				//FIN PROCESO ESTADO DEL DOCUMENTO


				//Se agregan los asientos contables*/*******

				$sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user, business, branch)
								 VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user, :business, :branch)";


				$resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

					':mac_doc_num' => 1,
					':mac_status' => 1,
					':mac_base_type' => is_numeric($Data['vnc_doctype']) ? $Data['vnc_doctype'] : 0,
					':mac_base_entry' => $resInsert,
					':mac_doc_date' => $this->validateDate($Data['vnc_docdate']) ? $Data['vnc_docdate'] : NULL,
					':mac_doc_duedate' => $this->validateDate($Data['vnc_duedate']) ? $Data['vnc_duedate'] : NULL,
					':mac_legal_date' => $this->validateDate($Data['vnc_docdate']) ? $Data['vnc_docdate'] : NULL,
					':mac_ref1' => is_numeric($Data['vnc_doctype']) ? $Data['vnc_doctype'] : 0,
					':mac_ref2' => "",
					':mac_ref3' => "",
					':mac_loc_total' => is_numeric($Data['vnc_doctotal']) ? $Data['vnc_doctotal'] : 0,
					':mac_fc_total' => is_numeric($Data['vnc_doctotal']) ? $Data['vnc_doctotal'] : 0,
					':mac_sys_total' => is_numeric($Data['vnc_doctotal']) ? $Data['vnc_doctotal'] : 0,
					':mac_trans_dode' => 1,
					':mac_beline_nume' => 1,
					':mac_vat_date' => $this->validateDate($Data['vnc_docdate']) ? $Data['vnc_docdate'] : NULL,
					':mac_serie' => 1,
					':mac_number' => 1,
					':mac_bammntsys' => is_numeric($Data['vnc_baseamnt']) ? $Data['vnc_baseamnt'] : 0,
					':mac_bammnt' => is_numeric($Data['vnc_baseamnt']) ? $Data['vnc_baseamnt'] : 0,
					':mac_wtsum' => 1,
					':mac_vatsum' => is_numeric($Data['vnc_taxtotal']) ? $Data['vnc_taxtotal'] : 0,
					':mac_comments' => isset($Data['vnc_comment']) ? $Data['vnc_comment'] : NULL,
					':mac_create_date' => $this->validateDate($Data['vnc_createat']) ? $Data['vnc_createat'] : NULL,
					':mac_made_usuer' => isset($Data['vnc_createby']) ? $Data['vnc_createby'] : NULL,
					':mac_update_date' => date("Y-m-d"),
					':mac_update_user' => isset($Data['vnc_createby']) ? $Data['vnc_createby'] : NULL,
					':business' => $Data['business'],
					':branch' 	=> $Data['branch']
				));


				if (is_numeric($resInsertAsiento) && $resInsertAsiento > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle de la Nota crédito de clientes se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $resInsertAsiento,
						'mensaje'	=> 'No se pudo registrar la Nota crédito de clientes'
					);

					return $this->response($respuesta);
				}

				//SE APLICA PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS
				if (isset($Data['vnc_baseentry']) && is_numeric($Data['vnc_baseentry']) && isset($Data['vnc_basetype']) && is_numeric($Data['vnc_basetype'])) {

					$sqlDocInicio = "SELECT bmd_tdi, bmd_ndi FROM tbmd WHERE  bmd_doctype = :bmd_doctype AND bmd_docentry = :bmd_docentry";
					$resDocInicio = $this->pedeo->queryTable($sqlDocInicio, array(
						':bmd_doctype' => $Data['vnc_basetype'],
						':bmd_docentry' => $Data['vnc_baseentry']
					));


					if (isset($resDocInicio[0])) {

						$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
						bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency,business)
						VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
						:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency,:business)";

						$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(
							':bmd_doctype' => is_numeric($Data['vnc_doctype']) ? $Data['vnc_doctype'] : 0,
							':bmd_docentry' => $resInsert,
							':bmd_createat' => $this->validateDate($Data['vnc_createat']) ? $Data['vnc_createat'] : NULL,
							':bmd_doctypeo' => is_numeric($Data['vnc_basetype']) ? $Data['vnc_basetype'] : 0, //ORIGEN
							':bmd_docentryo' => is_numeric($Data['vnc_baseentry']) ? $Data['vnc_baseentry'] : 0,  //ORIGEN
							':bmd_tdi' => $resDocInicio[0]['bmd_tdi'], // DOCUMENTO INICIAL
							':bmd_ndi' => $resDocInicio[0]['bmd_ndi'], // DOCUMENTO INICIAL
							':bmd_docnum' => $DocNumVerificado,
							':bmd_doctotal' => is_numeric($Data['vnc_doctotal']) ? $Data['vnc_doctotal'] : 0,
							':bmd_cardcode' => isset($Data['vnc_cardcode']) ? $Data['vnc_cardcode'] : NULL,
							':bmd_cardtype' => 1,
							':bmd_currency' => isset($Data['vnc_currency'])?$Data['vnc_currency']:NULL,
							':business' => isset($Data['business']) ? $Data['business'] : NULL
						));

						if (is_numeric($resInsertMD) && $resInsertMD > 0) {
						} else {

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' => $resInsertEstado,
								'mensaje'	=> 'No se pudo registrar el movimiento del documento'
							);


							return $this->response($respuesta);
						}
					} else {

						$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
						bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency,business)
						VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
						:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency,:business)";

						$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(
							':bmd_doctype' => is_numeric($Data['vnc_doctype']) ? $Data['vnc_doctype'] : 0,
							':bmd_docentry' => $resInsert,
							':bmd_createat' => $this->validateDate($Data['vnc_createat']) ? $Data['vnc_createat'] : NULL,
							':bmd_doctypeo' => is_numeric($Data['vnc_basetype']) ? $Data['vnc_basetype'] : 0, //ORIGEN
							':bmd_docentryo' => is_numeric($Data['vnc_baseentry']) ? $Data['vnc_baseentry'] : 0,  //ORIGEN
							':bmd_tdi' => is_numeric($Data['vnc_doctype']) ? $Data['vnc_doctype'] : 0, // DOCUMENTO INICIAL
							':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
							':bmd_docnum' => $DocNumVerificado,
							':bmd_doctotal' => is_numeric($Data['vnc_doctotal']) ? $Data['vnc_doctotal'] : 0,
							':bmd_cardcode' => isset($Data['vnc_cardcode']) ? $Data['vnc_cardcode'] : NULL,
							':bmd_cardtype' => 1,
							':bmd_currency' => isset($Data['vnc_currency'])?$Data['vnc_currency']:NULL,
							':business' => isset($Data['business']) ? $Data['business'] : NULL
						));

						if (is_numeric($resInsertMD) && $resInsertMD > 0) {
						} else {

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' => $resInsertEstado,
								'mensaje'	=> 'No se pudo registrar el movimiento del documento'
							);


							return $this->response($respuesta);
						}
					}
				} else {

					$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
					bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency,business)
					VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
					:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency,:business)";

					$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(
						':bmd_doctype' => is_numeric($Data['vnc_doctype']) ? $Data['vnc_doctype'] : 0,
						':bmd_docentry' => $resInsert,
						':bmd_createat' => $this->validateDate($Data['vnc_createat']) ? $Data['vnc_createat'] : NULL,
						':bmd_doctypeo' => is_numeric($Data['vnc_basetype']) ? $Data['vnc_basetype'] : 0, //ORIGEN
						':bmd_docentryo' => is_numeric($Data['vnc_baseentry']) ? $Data['vnc_baseentry'] : 0,  //ORIGEN
						':bmd_tdi' => is_numeric($Data['vnc_doctype']) ? $Data['vnc_doctype'] : 0, // DOCUMENTO INICIAL
						':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
						':bmd_docnum' => $DocNumVerificado,
						':bmd_doctotal' => is_numeric($Data['vnc_doctotal']) ? $Data['vnc_doctotal'] : 0,
						':bmd_cardcode' => isset($Data['vnc_cardcode']) ? $Data['vnc_cardcode'] : NULL,
						':bmd_cardtype' => 1,
						':bmd_currency' => isset($Data['vnc_currency'])?$Data['vnc_currency']:NULL,
						':business' => isset($Data['business']) ? $Data['business'] : NULL
					));

					if (is_numeric($resInsertMD) && $resInsertMD > 0) {
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' => $resInsertEstado,
							'mensaje'	=> 'No se pudo registrar el movimiento del documento'
						);


						return $this->response($respuesta);
					}
				}
				//FIN PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS


				foreach ($ContenidoDetalle as $key => $detail) {


					$CANTUOMSALE = $this->generic->getUomSale($detail['nc1_itemcode']);

					if ($CANTUOMSALE == 0) {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' 		=> $detail['nc1_itemcode'],
							'mensaje'	=> 'No se encontro la equivalencia de la unidad de medida para el item: ' . $detail['nc1_itemcode']
						);

						return $this->response($respuesta);
					}

					$sqlInsertDetail = "INSERT INTO vnc1(nc1_docentry, nc1_itemcode, nc1_itemname, nc1_quantity, nc1_uom, nc1_whscode,
										nc1_price, nc1_vat, nc1_vatsum, nc1_discount, nc1_linetotal, nc1_costcode, nc1_ubusiness, nc1_project,
										nc1_acctcode, nc1_basetype, nc1_doctype, nc1_avprice, nc1_inventory, nc1_exc_inv,nc1_acciva,nc1_linenum,nc1_codimp,
										nc1_ubication,nc1_baseline,ote_code)VALUES(:nc1_docentry, :nc1_itemcode, :nc1_itemname, :nc1_quantity,:nc1_uom, :nc1_whscode,:nc1_price, 
										:nc1_vat, :nc1_vatsum, :nc1_discount, :nc1_linetotal, :nc1_costcode, :nc1_ubusiness, :nc1_project,:nc1_acctcode, 
										:nc1_basetype, :nc1_doctype, :nc1_avprice, :nc1_inventory, :nc1_exc_inv, :nc1_acciva,:nc1_linenum,:nc1_codimp,
										:nc1_ubication,:nc1_baseline,:ote_code)";

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
						':nc1_exc_inv' => is_numeric($detail['nc1_exc_inv']) ? $detail['nc1_exc_inv'] : 0,
						':nc1_acciva' => is_numeric($detail['nc1_acciva']) ? $detail['nc1_acciva'] : 0,
						':nc1_linenum' => is_numeric($detail['nc1_linenum']) ? $detail['nc1_linenum'] : 0,
						':nc1_codimp'  => isset($detail['nc1_codimp']) ? $detail['nc1_codimp'] : NULL,
						':nc1_ubication'  => isset($detail['nc1_ubication']) ? $detail['nc1_ubication'] : NULL,
						':nc1_baseline' => isset($detail['nc1_baseline']) && is_numeric($detail['nc1_baseline']) ? $detail['nc1_baseline'] : 0,
						':ote_code' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL
					));

					if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
						// Se verifica que el detalle no de error insertando //
						if($Data['vnc_basetype'] == 5){
							//OBTENER NUMERO DOCUMENTO ORIGEN
							$DOC = "SELECT dvf_docnum FROM dvfv WHERE dvf_doctype = :dvf_doctype AND dvf_docentry = :dvf_docentry";
							$RESULT_DOC = $this->pedeo->queryTable($DOC,array(':dvf_docentry' =>$Data['vnc_baseentry'],':dvf_doctype' => $Data['vnc_basetype']));
							foreach ($ContenidoDetalle as $key => $value) {
							# code...
							$sql = "SELECT dvfv.dvf_docnum,vfv1.fv1_itemcode FROM dvfv INNER JOIN vfv1 ON dvfv.dvf_docentry = vfv1.fv1_docentry 
							WHERE dvfv.dvf_docentry = :dvf_docentry AND dvfv.dvf_doctype = :dvf_doctype AND vfv1.fv1_itemcode = :fv1_itemcode";
							$resSql = $this->pedeo->queryTable($sql,array(
								':dvf_docentry' =>$Data['vnc_baseentry'],
								':dvf_doctype' => $Data['vnc_basetype'],
								':fv1_itemcode' => $value['nc1_itemcode']
							));
							
								if(isset($resSql[0])){
								
								}else {
									$this->pedeo->trans_rollback();
				
									$respuesta = array(
										'error'   => true,
										'data' => $value['nc1_itemcode'],
										'mensaje'	=> 'El Item '.$value['nc1_itemcode'].' no existe en el documento origen (Factura #'.$RESULT_DOC[0]['dvf_docnum'].')'
									);
				
									return $this->response($respuesta);
								}
							}
						}
					} else {

						// si falla algun insert del detalle de la Nota crédito de clientes se devuelven los cambios realizados por la transaccion,
						// se retorna el error y se detiene la ejecucion del codigo restante.
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' => $resInsert,
							'mensaje'	=> 'No se pudo registrar la Nota crédito de clientes'
						);

						return $this->response($respuesta);
					}


					// PROCESO PARA INSERTAR RETENCIONES VENTAS

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
										':crt_basetype'  => $Data['vnc_doctype'],
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

							if ( !strlen(trim($detail['nc1_ubication'])) > 0 ){

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $resInsert,
									'mensaje'	=> 'Codigo de ubicación no valido para el item '.$detail['nc1_itemcode']
								);

								return $this->response($respuesta);
							}

						}else{
							$ManejaUbicacion = 0;
						}

						// SE VERIFICA SI EL ARTICULO MANEJA LOTE

						$sqlLote = "SELECT dma_lotes_code FROM dmar WHERE dma_item_code = :dma_item_code AND dma_lotes_code = :dma_lotes_code";
						$resLote = $this->pedeo->queryTable($sqlLote, array(
							':dma_item_code' => $detail['nc1_itemcode'],
							':dma_lotes_code'  => 1
						));

						if (isset($resLote[0])) {


							$ManejaLote = 1;

							if ( !strlen(trim($detail['ote_code'])) > 0 ){

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $resInsert,
									'mensaje'	=> 'Codigo de lote no valido para el item '.$detail['nc1_itemcode']
								);
								return $this->response($respuesta);
							}


						} else {

							$ManejaLote = 0;
						}




						$ManejaInvetario = 1;
					} else {
						$ManejaInvetario = 0;
					}

					// FIN PROCESO ITEM MANEJA INVENTARIO

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

								$AddSerial = $this->generic->addSerial($detail['serials'], $detail['nc1_itemcode'], $Data['vnc_doctype'], $resInsert, $DocNumVerificado, $Data['vnc_docdate'], 1, $Data['vnc_comment'], $detail['nc1_whscode'], $detail['nc1_quantity'], $Data['vnc_createby'], $Data['business']);

								if (isset($AddSerial['error']) && $AddSerial['error'] == false) {
								} else {
									$respuesta = array(
										'error'   => true,
										'data'    => $AddSerial['data'],
										'mensaje' => $AddSerial['mensaje']
									);

									return $this->response($respuesta);
								}
							} else {
								$ManejaSerial = 0;
							}

							//



							//Se aplica el movimiento de inventario
							//BUSCANDO COSTO DE ARTICULO PARA RIGISTRARLO EN EL MOVIMIENTO

							$sqlCostoMomentoRegistro = "";
							$resCostoMomentoRegistro = [];
		
		
							if ( $ManejaUbicacion == 1 ) {
								if ( $ManejaLote == 1 ) {
									$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_ubication = :bdi_ubication AND bdi_lote = :bdi_lote AND business = :business";
									$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(':bdi_whscode' => $detail['nc1_whscode'], ':bdi_itemcode' => $detail['nc1_itemcode'], ':bdi_ubication' => $detail['nc1_ubication'], ':bdi_lote' => $detail['ote_code'], ':business' => $Data['business']));
								}else{
									$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_ubication = :bdi_ubication AND business = :business";
									$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(':bdi_whscode' => $detail['nc1_whscode'], ':bdi_itemcode' => $detail['nc1_itemcode'], ':bdi_ubication' => $detail['nc1_ubication'], ':business' => $Data['business']));
								}
							}else{
								if ( $ManejaLote == 1 ) {
									$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_lote = :bdi_lote AND business = :business";
									$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(':bdi_whscode' => $detail['nc1_whscode'], ':bdi_itemcode' => $detail['nc1_itemcode'], ':bdi_lote' => $detail['ote_code'], ':business' => $Data['business']));
								}else{
									$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND business = :business";
									$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(':bdi_whscode' => $detail['nc1_whscode'], ':bdi_itemcode' => $detail['nc1_itemcode'], ':business' => $Data['business']));
								}

							}
						

							if (!isset($resCostoMomentoRegistro[0])) {

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => [],
									'mensaje'	=> 'no se encontro el costo del articulo ' . $detail['nc1_itemcode']
								);

								return $this->response($respuesta);
							}

							$sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode,bmi_quantity,bmi_whscode,bmi_createat,bmi_createby,bmy_doctype,bmy_baseentry,bmi_cost,
							bmi_currequantity,bmi_basenum,bmi_docdate,bmi_duedate,bmi_duedev,bmi_comment,bmi_ubication,bmi_lote,business)
							VALUES (:bmi_itemcode,:bmi_quantity, :bmi_whscode,:bmi_createat,:bmi_createby,:bmy_doctype,:bmy_baseentry,:bmi_cost,:bmi_currequantity,
							:bmi_basenum,:bmi_docdate,:bmi_duedate,:bmi_duedev,:bmi_comment,:bmi_ubication,:bmi_lote,:business)";

							$sqlInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(

								':bmi_itemcode'  => isset($detail['nc1_itemcode']) ? $detail['nc1_itemcode'] : NULL,
								':bmi_quantity'  => is_numeric($detail['nc1_quantity']) ?  (($detail['nc1_quantity'] * $CANTUOMSALE) * $Data['invtype']) : 0,
								':bmi_whscode'   => isset($detail['nc1_whscode']) ? $detail['nc1_whscode'] : NULL,
								':bmi_createat'  => $this->validateDate($Data['vnc_createat']) ? $Data['vnc_createat'] : NULL,
								':bmi_createby'  => isset($Data['vnc_createby']) ? $Data['vnc_createby'] : NULL,
								':bmy_doctype'   => is_numeric($Data['vnc_doctype']) ? $Data['vnc_doctype'] : 0,
								':bmy_baseentry' => $resInsert,
								':bmi_cost'      => $resCostoMomentoRegistro[0]['bdi_avgprice'],
								':bmi_currequantity' => $resCostoMomentoRegistro[0]['bdi_quantity'],
								':bmi_basenum'			  => $DocNumVerificado,
								':bmi_docdate' => $this->validateDate($Data['vnc_docdate']) ? $Data['vnc_docdate'] : NULL,
								':bmi_duedate' => $this->validateDate($Data['vnc_duedate']) ? $Data['vnc_duedate'] : NULL,
								':bmi_duedev'  => $this->validateDate($Data['vnc_duedev']) ? $Data['vnc_duedev'] : NULL,
								':bmi_comment' => isset($Data['vnc_comment']) ? $Data['vnc_comment'] : NULL,
								':bmi_ubication' => isset($detail['nc1_ubication']) ? $detail['nc1_ubication'] : NULL,
								':bmi_lote' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
								':business' => isset($Data['business']) ? $Data['business'] : NULL
							));

							if (is_numeric($sqlInserMovimiento) && $sqlInserMovimiento > 0) {
								// Se verifica que el detalle no de error insertando //
							} else {

								// si falla algun insert del detalle de la Nota crédito de clientes se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $sqlInserMovimiento,
									'mensaje'	=> 'No se pudo registrar la Nota crédito de clientes'
								);

								return $this->response($respuesta);
							}

							//FIN aplicacion de movimiento de inventario


							//Se Aplica el movimiento en stock ***************
							// Buscando item en el stock
							$sqlCostoCantidad = "";
							$resCostoCantidad = [];
		
							// SI EL ALMACEN MANEJA UBICACION
		
							if ( $ManejaUbicacion == 1 ) {
								if ( $ManejaLote == 1 ) {
									$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
									FROM tbdi
									WHERE bdi_itemcode = :bdi_itemcode
									AND bdi_whscode = :bdi_whscode
									AND bdi_ubication = :bdi_ubication
									AND bdi_lote = :bdi_lote
									AND business = :business";

									$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(

										':bdi_itemcode'   => $detail['nc1_itemcode'],
										':bdi_whscode'    => $detail['nc1_whscode'],
										':bdi_ubication'  => $detail['nc1_ubication'],
										':bdi_lote' 	  => $detail['ote_code'],
										':business' 	  => $Data['business']
									));
								}else{

									$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
									FROM tbdi
									WHERE bdi_itemcode = :bdi_itemcode
									AND bdi_whscode = :bdi_whscode
									AND bdi_ubication = :bdi_ubication
									AND business = :business";

									$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(

										':bdi_itemcode' => $detail['nc1_itemcode'],
										':bdi_whscode'  => $detail['nc1_whscode'],
										':bdi_ubication'  => $detail['nc1_ubication'],
										':business' 	  => $Data['business']
									));
								}

							}else{
								if ( $ManejaLote == 1 ) {
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
								}else{
									$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
									FROM tbdi
									WHERE bdi_itemcode = :bdi_itemcode
									AND bdi_whscode = :bdi_whscode
									AND business = :business";

									$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(

										':bdi_itemcode' => $detail['nc1_itemcode'],
										':bdi_whscode'  => $detail['nc1_whscode'],
										':business'     => $Data['business']
									));
								}

							}
							if (isset($resCostoCantidad[0])) {

								$CantidadActual = $resCostoCantidad[0]['bdi_quantity'];
								$CantidadNueva =  ($detail['nc1_quantity'] * $CANTUOMSALE);


								$CantidadTotal = ($CantidadActual + $CantidadNueva);

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
										'mensaje'	=> 'No se pudo crear la Nota crédito de clientes'
									);

									return $this->response($respuesta);
								}
								
							} else {

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' 		=> $resCostoCantidad,
									'mensaje'	=> 'El item no existe en el stock ' . $detail['nc1_itemcode']
								);

								return $this->response($respuesta);
							}

							//FIN de  Aplicacion del movimiento en stock
						}
					}
					//FIN SI LA NOTA APLICA SIN MOVER INVENTARIO




					//LLENANDO DETALLE ASIENTO CONTABLES



					$DetalleAsientoIngreso = new stdClass();
					$DetalleAsientoIva = new stdClass();
					$DetalleCostoInventario = new stdClass();
					$DetalleCostoCosto = new stdClass();


					$DetalleAsientoIngreso->ac1_account = is_numeric($detail['nc1_acctcode']) ? $detail['nc1_acctcode'] : 0;
					$DetalleAsientoIngreso->ac1_prc_code = isset($detail['nc1_costcode']) ? $detail['nc1_costcode'] : NULL;
					$DetalleAsientoIngreso->ac1_uncode = isset($detail['nc1_ubusiness']) ? $detail['nc1_ubusiness'] : NULL;
					$DetalleAsientoIngreso->ac1_prj_code = isset($detail['nc1_project']) ? $detail['nc1_project'] : NULL;
					$DetalleAsientoIngreso->nc1_linetotal = is_numeric($detail['nc1_linetotal']) ? $detail['nc1_linetotal'] : 0;
					$DetalleAsientoIngreso->nc1_vat = is_numeric($detail['nc1_vat']) ? $detail['nc1_vat'] : 0;
					$DetalleAsientoIngreso->nc1_vatsum = is_numeric($detail['nc1_vatsum']) ? $detail['nc1_vatsum'] : 0;
					$DetalleAsientoIngreso->nc1_price = is_numeric($detail['nc1_price']) ? $detail['nc1_price'] : 0;
					$DetalleAsientoIngreso->nc1_itemcode = isset($detail['nc1_itemcode']) ? $detail['nc1_itemcode'] : NULL;
					$DetalleAsientoIngreso->nc1_quantity = is_numeric($detail['nc1_quantity']) ? $detail['nc1_quantity'] : 0;
					$DetalleAsientoIngreso->nc1_whscode = isset($detail['nc1_whscode']) ? $detail['nc1_whscode'] : NULL;
					$DetalleAsientoIngreso->nc1_fixrate = is_numeric($detail['nc1_fixrate']) ? $detail['nc1_fixrate'] : 0;
					$DetalleAsientoIngreso->gift = isset($detail['nc1_gift']) && is_numeric($detail['nc1_gift']) ? $detail['nc1_gift'] : 0;



					$DetalleAsientoIva->ac1_account = is_numeric($detail['nc1_acctcode']) ? $detail['nc1_acctcode'] : 0;
					$DetalleAsientoIva->ac1_prc_code = isset($detail['nc1_costcode']) ? $detail['nc1_costcode'] : NULL;
					$DetalleAsientoIva->ac1_uncode = isset($detail['nc1_ubusiness']) ? $detail['nc1_ubusiness'] : NULL;
					$DetalleAsientoIva->ac1_prj_code = isset($detail['nc1_project']) ? $detail['nc1_project'] : NULL;
					$DetalleAsientoIva->nc1_linetotal = is_numeric($detail['nc1_linetotal']) ? $detail['nc1_linetotal'] : 0;
					$DetalleAsientoIva->nc1_vat = is_numeric($detail['nc1_vat']) ? $detail['nc1_vat'] : 0;
					$DetalleAsientoIva->nc1_vatsum = is_numeric($detail['nc1_vatsum']) ? $detail['nc1_vatsum'] : 0;
					$DetalleAsientoIva->nc1_price = is_numeric($detail['nc1_price']) ? $detail['nc1_price'] : 0;
					$DetalleAsientoIva->nc1_itemcode = isset($detail['nc1_itemcode']) ? $detail['nc1_itemcode'] : NULL;
					$DetalleAsientoIva->nc1_quantity = is_numeric($detail['nc1_quantity']) ? $detail['nc1_quantity'] : 0;
					$DetalleAsientoIva->nc1_cuentaIva = is_numeric($detail['nc1_cuentaIva']) ? $detail['nc1_cuentaIva'] : NULL;
					$DetalleAsientoIva->nc1_whscode = isset($detail['nc1_whscode']) ? $detail['nc1_whscode'] : NULL;
					$DetalleAsientoIva->nc1_fixrate = is_numeric($detail['nc1_fixrate']) ? $detail['nc1_fixrate'] : 0;
					$DetalleAsientoIva->codimp = isset($detail['nc1_codimp']) ? $detail['nc1_codimp'] : NULL;
					$DetalleAsientoIva->gift = isset($detail['nc1_gift']) && is_numeric($detail['nc1_gift']) ? $detail['nc1_gift'] : 0;



					if ($exc_inv == 1) {
						// VALIDANDO ITEM INVENTARIABLE
						if ($ManejaInvetario == 1) {
							$DetalleCostoInventario->ac1_account = is_numeric($detail['nc1_acctcode']) ? $detail['nc1_acctcode'] : 0;
							$DetalleCostoInventario->ac1_prc_code = isset($detail['nc1_costcode']) ? $detail['nc1_costcode'] : NULL;
							$DetalleCostoInventario->ac1_uncode = isset($detail['nc1_ubusiness']) ? $detail['nc1_ubusiness'] : NULL;
							$DetalleCostoInventario->ac1_prj_code = isset($detail['nc1_project']) ? $detail['nc1_project'] : NULL;
							$DetalleCostoInventario->nc1_linetotal = is_numeric($detail['nc1_linetotal']) ? $detail['nc1_linetotal'] : 0;
							$DetalleCostoInventario->nc1_vat = is_numeric($detail['nc1_vat']) ? $detail['nc1_vat'] : 0;
							$DetalleCostoInventario->nc1_vatsum = is_numeric($detail['nc1_vatsum']) ? $detail['nc1_vatsum'] : 0;
							$DetalleCostoInventario->nc1_price = is_numeric($detail['nc1_price']) ? $detail['nc1_price'] : 0;
							$DetalleCostoInventario->nc1_itemcode = isset($detail['nc1_itemcode']) ? $detail['nc1_itemcode'] : NULL;
							$DetalleCostoInventario->nc1_quantity = is_numeric($detail['nc1_quantity']) ? $detail['nc1_quantity'] : 0;
							$DetalleCostoInventario->nc1_whscode = isset($detail['nc1_whscode']) ? $detail['nc1_whscode'] : NULL;
							$DetalleCostoInventario->nc1_fixrate = is_numeric($detail['nc1_fixrate']) ? $detail['nc1_fixrate'] : 0;


							$DetalleCostoCosto->ac1_account = is_numeric($detail['nc1_acctcode']) ? $detail['nc1_acctcode'] : 0;
							$DetalleCostoCosto->ac1_prc_code = isset($detail['nc1_costcode']) ? $detail['nc1_costcode'] : NULL;
							$DetalleCostoCosto->ac1_uncode = isset($detail['nc1_ubusiness']) ? $detail['nc1_ubusiness'] : NULL;
							$DetalleCostoCosto->ac1_prj_code = isset($detail['nc1_project']) ? $detail['nc1_project'] : NULL;
							$DetalleCostoCosto->nc1_linetotal = is_numeric($detail['nc1_linetotal']) ? $detail['nc1_linetotal'] : 0;
							$DetalleCostoCosto->nc1_vat = is_numeric($detail['nc1_vat']) ? $detail['nc1_vat'] : 0;
							$DetalleCostoCosto->nc1_vatsum = is_numeric($detail['nc1_vatsum']) ? $detail['nc1_vatsum'] : 0;
							$DetalleCostoCosto->nc1_price = is_numeric($detail['nc1_price']) ? $detail['nc1_price'] : 0;
							$DetalleCostoCosto->nc1_itemcode = isset($detail['nc1_itemcode']) ? $detail['nc1_itemcode'] : NULL;
							$DetalleCostoCosto->nc1_quantity = is_numeric($detail['nc1_quantity']) ? $detail['nc1_quantity'] : 0;
							$DetalleCostoCosto->nc1_whscode = isset($detail['nc1_whscode']) ? $detail['nc1_whscode'] : NULL;
							$DetalleCostoCosto->nc1_fixrate = is_numeric($detail['nc1_fixrate']) ? $detail['nc1_fixrate'] : 0;
							$DetalleCostoCosto->gift = isset($detail['nc1_gift']) && is_numeric($detail['nc1_gift']) ? $detail['nc1_gift'] : 0;
						} //ITEM INVENTARIABLE
					}



					$codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);

					$DetalleAsientoIngreso->codigoCuenta = $codigoCuenta;
					$DetalleAsientoIva->codigoCuenta = $codigoCuenta;


					if ($exc_inv == 1) {
						//ITEM INVENTARIABLE
						if ($ManejaInvetario == 1 || $exc_inv == 1) {

							$DetalleCostoInventario->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);
							$DetalleCostoInventario->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);

							$llaveCostoInventario = $DetalleCostoInventario->ac1_account;
							$llaveCostoCosto = $DetalleCostoCosto->ac1_account;
						} //ITEM INVENTARIABLE
					}



					$llave = $DetalleAsientoIngreso->ac1_uncode . $DetalleAsientoIngreso->ac1_prc_code . $DetalleAsientoIngreso->ac1_prj_code . $DetalleAsientoIngreso->ac1_account;
					$llaveIva = $DetalleAsientoIva->nc1_vat;




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


					if ($exc_inv == 1) {
						//ITEM INVENTARIABLE
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
						} //ITEM INVENTARIABLE
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
						//ITEM INVENTARIABLE
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
						} //ITEM INVENTARIABLE
					}
				}
				// FIN INSERSION DETALLE NOTA CREDITO

				//Procedimiento para llenar Ingreso

				foreach ($DetalleConsolidadoIngreso as $key => $posicion) {
					$granTotalIngreso = 0;
					$granTotalIngresoOriginal = 0;
					$granTotalTasaFija = 0;
					$codigoCuentaIngreso = "";
					$cuenta = "";
					$proyecto = "";
					$prc = "";
					$unidad = "";
					foreach ($posicion as $key => $value) {

						if (  $value->gift == 0 ) {

							$granTotalIngreso = ($granTotalIngreso + $value->nc1_linetotal);
							$granTotalTasaFija = ($granTotalTasaFija + ($value->nc1_fixrate * $value->nc1_quantity));
							$codigoCuentaIngreso = $value->codigoCuenta;
							$prc = $value->ac1_prc_code;
							$unidad = $value->ac1_uncode;
							$proyecto = $value->ac1_prj_code;
							$cuenta = $value->ac1_account;
						
						}else{



						}

					}


					$debito = 0;
					$credito = 0;
					$MontoSysDB = 0;
					$MontoSysCR = 0;
					$granTotalIngresoOriginal = $granTotalIngreso;

					if (trim($Data['vnc_currency']) != $MONEDALOCAL) {
						$granTotalIngreso = ($granTotalIngreso * $TasaDocLoc);
					}

					$MONEDASYS = trim($dataTasa['curSys']);


					$ti = $granTotalIngreso;
					
					switch ($codigoCuentaIngreso) {
						case 1:
							
							$debito = $granTotalIngreso;

							if (trim($Data['vnc_currency']) != $MONEDASYS) {
								$ti = ($ti + $granTotalTasaFija);
								$MontoSysDB = ($ti / $TasaLocSys);
							} else {
								$MontoSysDB = $granTotalIngresoOriginal;
							}
							break;

						case 2:
							$debito = $granTotalIngreso;
							
							if (trim($Data['vnc_currency']) != $MONEDASYS) {
								$ti = ($ti + $granTotalTasaFija);
								$MontoSysDB = ($ti / $TasaLocSys);
							} else {
								$MontoSysDB = $granTotalIngresoOriginal;
							}
							break;

						case 3:
							$debito = $granTotalIngreso;
							
							if (trim($Data['vnc_currency']) != $MONEDASYS) {
								$ti = ($ti + $granTotalTasaFija);
								$MontoSysDB = ($ti / $TasaLocSys);
							} else {
								$MontoSysDB = $granTotalIngresoOriginal;
							}
							break;

						case 4:
							$debito = $granTotalIngreso;
							
							if (trim($Data['vnc_currency']) != $MONEDASYS) {
								$ti = ($ti + $granTotalTasaFija);
								$MontoSysDB = ($ti / $TasaLocSys);
							} else {
								$MontoSysDB = $granTotalIngresoOriginal;
							}
							break;

						case 5:
							$debito = $granTotalIngreso;
							
							if (trim($Data['vnc_currency']) != $MONEDASYS) {
								$ti = ($ti + $granTotalTasaFija);
								$MontoSysDB = ($ti / $TasaLocSys);
							} else {
								$MontoSysDB = $granTotalIngresoOriginal;
							}
							break;

						case 6:
							$debito = $granTotalIngreso;
							
							if (trim($Data['vnc_currency']) != $MONEDASYS) {
								$ti = ($ti + $granTotalTasaFija);
								$MontoSysDB = ($ti / $TasaLocSys);
							} else {
								$MontoSysDB = $granTotalIngresoOriginal;
							}
							break;

						case 7:
							$debito = $granTotalIngreso;
							
							if (trim($Data['vnc_currency']) != $MONEDASYS) {
								$ti = ($ti + $granTotalTasaFija);
								$MontoSysDB = ($ti / $TasaLocSys);
							} else {
								$MontoSysDB = $granTotalIngresoOriginal;
							}
							break;

						case 9:
							$debito = $granTotalIngreso;
							
							if (trim($Data['vnc_currency']) != $MONEDASYS) {
								$ti = ($ti + $granTotalTasaFija);
								$MontoSysDB = ($ti / $TasaLocSys);
							} else {
								$MontoSysDB = $granTotalIngresoOriginal;
							}
							break;
					}
			
					if ( $debito > 0 ||  $credito > 0 ) {

						$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
						$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));
	
	
						$TOTALCXCLOC = ($TOTALCXCLOC + ($debito + $credito));
						$TOTALCXCSYS = ($TOTALCXCSYS + ($MontoSysDB + $MontoSysCR));
	
	
						$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(
	
							':ac1_trans_id' => $resInsertAsiento,
							':ac1_account' => $cuenta,
							':ac1_debit' => round($debito, $DECI_MALES),
							':ac1_credit' => round($credito, $DECI_MALES),
							':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
							':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
							':ac1_currex' => 0,
							':ac1_doc_date' => $this->validateDate($Data['vnc_docdate']) ? $Data['vnc_docdate'] : NULL,
							':ac1_doc_duedate' => $this->validateDate($Data['vnc_duedate']) ? $Data['vnc_duedate'] : NULL,
							':ac1_debit_import' => 0,
							':ac1_credit_import' => 0,
							':ac1_debit_importsys' => 0,
							':ac1_credit_importsys' => 0,
							':ac1_font_key' => $resInsert,
							':ac1_font_line' => 1,
							':ac1_font_type' => is_numeric($Data['vnc_doctype']) ? $Data['vnc_doctype'] : 0,
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
							':ac1_made_user' => isset($Data['vnc_createby']) ? $Data['vnc_createby'] : NULL,
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
							':ac1_legal_num' => isset($Data['vnc_cardcode']) ? $Data['vnc_cardcode'] : NULL,
							':ac1_codref' => 1,
							':ac1_line'   => $AC1LINE,
							':ac1_base_tax' => 0,
							':business' => $Data['business'],
							':branch' 	=> $Data['branch'],
							':ac1_codret' => NULL
						));
	
	
	
						if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
							// Se verifica que el detalle no de error insertando //
						} else {
							// si falla algun insert del detalle de la Nota crédito de clientes se devuelven los cambios realizados por la transaccion,
							// se retorna el error y se detiene la ejecucion del codigo restante.
							$this->pedeo->trans_rollback();
	
							$respuesta = array(
								'error'   => true,
								'data'	  => $resDetalleAsiento,
								'mensaje'	=> 'No se pudo registrar la Nota crédito de clientes'
							);
	
							return $this->response($respuesta);
						}
					}
				}
				//FIN Procedimiento para llenar Ingreso


				//Procedimiento para llenar Impuestos

				$granTotalIva = 0;

				$VALUE_GIFT_IVA = 0;
				$VALUE_GIFT_IVA_SYS = 0;
				$VALUE_GIFT_IVA_ORG = 0;

				foreach ($DetalleConsolidadoIva as $key => $posicion) {
					$granTotalIva = 0;
					$granTotalIva2 = 0;
					$granTotalIvaOriginal = 0;
					$MontoSysCR = 0;
					$CodigoImp = 0;
					$LineTotal = 0;
					$Vat = 0;
		
					foreach ($posicion as $key => $value) {
						$valueGift = 0;
						$granTotalIva = round($granTotalIva + $value->nc1_vatsum, $DECI_MALES);

						$v1 = ($value->nc1_linetotal + ($value->nc1_quantity * $value->nc1_fixrate));
						$granTotalIva2 = round($granTotalIva2 + ($v1 * ($value->nc1_vat / 100)), $DECI_MALES);

						$LineTotal = ($LineTotal + $value->nc1_linetotal);
						$CodigoImp = $value->codimp;
						$Vat = $value->nc1_vat;

						if ( $value->gift == 1) {

							array_push($DETALLE_GIFT, array( "monto" => round($value->nc1_vatsum, $DECI_MALES), "item" => $value->nc1_itemcode, "proyecto" =>$value->ac1_prj_code , "centrocosto" =>$value->ac1_prc_code, "unidadnegocio" => $value->ac1_uncode, "whscode" => $value->nc1_whscode ));
						
							$VALUE_GIFT_IVA = $VALUE_GIFT_IVA + round($value->nc1_vatsum, $DECI_MALES);
							

						}
					}

					$granTotalIvaOriginal = $granTotalIva;

					$VALUE_GIFT_IVA_ORG = $VALUE_GIFT_IVA;



					if (trim($Data['vnc_currency']) != $MONEDALOCAL) {
						$granTotalIva = ($granTotalIva * $TasaDocLoc);
						
						$VALUE_GIFT_IVA = ($VALUE_GIFT_IVA * $TasaDocLoc);
						
						$LineTotal = ($LineTotal * $TasaDocLoc);
					}

					

					$TIva = $granTotalIva2;

					if (trim($Data['vnc_currency']) != $MONEDASYS) {
						// $TIva = (($TIva * $TasaFija) / 100) + $TIva;
						$MontoSysDB = ($TIva / $TasaLocSys);

						
						$VALUE_GIFT_IVA_SYS = ( $VALUE_GIFT_IVA / $TasaLocSys );
						

					} else {
						$MontoSysDB = $granTotalIvaOriginal;
					
						$VALUE_GIFT_IVA_SYS =  $VALUE_GIFT_IVA_ORG;
						
					}


					$SumaDebitosSYS = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES) - round( $VALUE_GIFT_IVA_SYS, $DECI_MALES ));
					$AC1LINE = $AC1LINE + 1;


					$TOTALCXCLOCIVA = ($TOTALCXCLOCIVA + $granTotalIva) - $VALUE_GIFT_IVA;
					$TOTALCXCSYSIVA = ($TOTALCXCSYSIVA + $MontoSysDB) - $VALUE_GIFT_IVA_SYS;

					$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

						':ac1_trans_id' => $resInsertAsiento,
						':ac1_account' => $value->nc1_cuentaIva,
						':ac1_debit' => round($granTotalIva, $DECI_MALES),
						':ac1_credit' => 0,
						':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
						':ac1_credit_sys' => 0,
						':ac1_currex' => 0,
						':ac1_doc_date' => $this->validateDate($Data['vnc_docdate']) ? $Data['vnc_docdate'] : NULL,
						':ac1_doc_duedate' => $this->validateDate($Data['vnc_duedate']) ? $Data['vnc_duedate'] : NULL,
						':ac1_debit_import' => 0,
						':ac1_credit_import' => 0,
						':ac1_debit_importsys' => 0,
						':ac1_credit_importsys' => 0,
						':ac1_font_key' => $resInsert,
						':ac1_font_line' => 1,
						':ac1_font_type' => is_numeric($Data['vnc_doctype']) ? $Data['vnc_doctype'] : 0,
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
						':ac1_made_user' => isset($Data['vnc_createby']) ? $Data['vnc_createby'] : NULL,
						':ac1_accperiod' => 1,
						':ac1_close' => 0,
						':ac1_cord' => 0,
						':ac1_ven_debit' => round($granTotalIva, $DECI_MALES),
						':ac1_ven_credit' => 0,
						':ac1_fiscal_acct' => 0,
						':ac1_taxid' => $CodigoImp,
						':ac1_isrti' => $Vat,
						':ac1_basert' => 0,
						':ac1_mmcode' => 0,
						':ac1_legal_num' => isset($Data['vnc_cardcode']) ? $Data['vnc_cardcode'] : NULL,
						':ac1_codref' => 1,
						':ac1_line'   => $AC1LINE,
						':ac1_base_tax' => round($LineTotal, $DECI_MALES),
						':business' => $Data['business'],
						':branch' 	=> $Data['branch'],
						':ac1_codret' => NULL
					));



					if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
						// Se verifica que el detalle no de error insertando //
					} else {

						// si falla algun insert del detalle de la Nota crédito de clientes se devuelven los cambios realizados por la transaccion,
						// se retorna el error y se detiene la ejecucion del codigo restante.
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'	  => $resDetalleAsiento,
							'mensaje'	=> 'No se pudo registrar la Nota crédito de clientes'
						);

						return $this->response($respuesta);
					}
				}

				//FIN Procedimiento para llenar Impuestos
				//Procedimiento para llenar costo inventario
				foreach ($DetalleConsolidadoCostoInventario as $key => $posicion) {
					$grantotalCostoInventario = 0;
					$grantotalCostoInventarioOriginal = 0;
					$cuentaInventario = "";
					foreach ($posicion as $key => $value) {

						$CUENTASINV = $this->account->getAccountItem($value->nc1_itemcode, $value->nc1_whscode);
						
						if ( isset($CUENTASINV['error']) && $CUENTASINV['error'] == false ) {
							$dbito = 0;
							$cdito = 0;

							$MontoSysDB = 0;
							$MontoSysCR = 0;

							$sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode";

							$resCosto = $this->pedeo->queryTable($sqlCosto, array(':bdi_itemcode' => $value->nc1_itemcode));

							if (isset($resCosto[0])) {

								$cuentaInventario = $CUENTASINV['data']['acct_inv'];


								$costoArticulo = $resCosto[0]['bdi_avgprice'];
								$cantidadArticulo = $value->nc1_quantity;
								$grantotalCostoInventario = ($grantotalCostoInventario + ($costoArticulo * $cantidadArticulo));
							} else {

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $resCosto,
									'mensaje'	=> 'No se encontro el costo para el item: ' . $value->nc1_itemcode
								);

								return $this->response($respuesta);
							}
						} else {
							// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
							// se retorna el error y se detiene la ejecucion del codigo restante.
							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data'	  => $CUENTASINV,
								'mensaje'	=> 'No se encontro la cuenta de inventario y costo para el item ' . $value->nc1_itemcode
							);

							return $this->response($respuesta);
						}
					}

					$codigo3 = substr($cuentaInventario, 0, 1);

					$grantotalCostoInventarioOriginal = $grantotalCostoInventario;

					if (trim($Data['vnc_currency']) != $MONEDALOCAL) {
						$grantotalCostoInventario = ($grantotalCostoInventario * $TasaLocSys);
					}

					if ($codigo3 == 1 || $codigo3 == "1") {
						$dbito = $grantotalCostoInventario;
						if (trim($Data['vnc_currency']) != $MONEDASYS) {
							$MontoSysDB = ($dbito / $TasaLocSys);
						} else {
							$MontoSysDB = ($grantotalCostoInventarioOriginal);
						}
					} else if ($codigo3 == 2 || $codigo3 == "2") {
						$dbito = $grantotalCostoInventario;
						if (trim($Data['vnc_currency']) != $MONEDASYS) {
							$MontoSysDB = ($dbito / $TasaLocSys);
						} else {
							$MontoSysDB = ($grantotalCostoInventarioOriginal);
						}
					} else if ($codigo3 == 3 || $codigo3 == "3") {
						$dbito = $grantotalCostoInventario;
						if (trim($Data['vnc_currency']) != $MONEDASYS) {
							$MontoSysDB = ($dbito / $TasaLocSys);
						} else {
							$MontoSysDB = ($grantotalCostoInventarioOriginal);
						}
					} else if ($codigo3 == 4 || $codigo3 == "4") {
						$dbito = $grantotalCostoInventario;
						if (trim($Data['vnc_currency']) != $MONEDASYS) {
							$MontoSysDB = ($dbito / $TasaLocSys);
						} else {
							$MontoSysDB = ($grantotalCostoInventarioOriginal);
						}
					} else if ($codigo3 == 5  || $codigo3 == "5") {
						$cdito = $grantotalCostoInventario;
						if (trim($Data['vnc_currency']) != $MONEDASYS) {
							$MontoSysCR = ($cdito / $TasaLocSys);
						} else {
							$MontoSysCR = ($grantotalCostoInventarioOriginal);
						}
					} else if ($codigo3 == 6 || $codigo3 == "6") {
						$cdito = $grantotalCostoInventario;
						if (trim($Data['vnc_currency']) != $MONEDASYS) {
							$MontoSysCR = ($cdito / $TasaLocSys);
						} else {
							$MontoSysCR = ($grantotalCostoInventarioOriginal);
						}
					} else if ($codigo3 == 7 || $codigo3 == "7") {
						$cdito = $grantotalCostoInventario;
						if (trim($Data['vnc_currency']) != $MONEDASYS) {
							$MontoSysCR = ($cdito / $TasaLocSys);
						} else {
							$MontoSysCR = ($grantotalCostoInventarioOriginal);
						}
					} else if ($codigo3 == 9 || $codigo3 == "9") {
						$dbito = $grantotalCostoInventario;
						if (trim($Data['vnc_currency']) != $MONEDASYS) {
							$MontoSysDB = ($dbito / $TasaLocSys);
						} else {
							$MontoSysDB = ($grantotalCostoInventarioOriginal);
						}
					}

					$AC1LINE = $AC1LINE + 1;


					$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

						':ac1_trans_id' => $resInsertAsiento,
						':ac1_account' => $cuentaInventario,
						':ac1_debit' => round($dbito, $DECI_MALES),
						':ac1_credit' => round($cdito, $DECI_MALES),
						':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
						':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
						':ac1_currex' => 0,
						':ac1_doc_date' => $this->validateDate($Data['vnc_docdate']) ? $Data['vnc_docdate'] : NULL,
						':ac1_doc_duedate' => $this->validateDate($Data['vnc_duedate']) ? $Data['vnc_duedate'] : NULL,
						':ac1_debit_import' => 0,
						':ac1_credit_import' => 0,
						':ac1_debit_importsys' => 0,
						':ac1_credit_importsys' => 0,
						':ac1_font_key' => $resInsert,
						':ac1_font_line' => 1,
						':ac1_font_type' => is_numeric($Data['vnc_doctype']) ? $Data['vnc_doctype'] : 0,
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
						':ac1_made_user' => isset($Data['vnc_createby']) ? $Data['vnc_createby'] : NULL,
						':ac1_accperiod' => 0,
						':ac1_close' => 0,
						':ac1_cord' => 0,
						':ac1_ven_debit' => round($dbito, $DECI_MALES),
						':ac1_ven_credit' => round($cdito, $DECI_MALES),
						':ac1_fiscal_acct' => 0,
						':ac1_taxid' => 1,
						':ac1_isrti' => 0,
						':ac1_basert' => 0,
						':ac1_mmcode' => 0,
						':ac1_legal_num' => isset($Data['vnc_cardcode']) ? $Data['vnc_cardcode'] : NULL,
						':ac1_codref' => 1,
						':ac1_line'   => $AC1LINE,
						':ac1_base_tax' => 0,
						':business' => $Data['business'],
						':branch' 	=> $Data['branch'],
						':ac1_codret' => NULL
					));

					if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
						// Se verifica que el detalle no de error insertando //
					} else {

						// si falla algun insert del detalle de la Nota crédito de clientes se devuelven los cambios realizados por la transaccion,
						// se retorna el error y se detiene la ejecucion del codigo restante.
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'	  => $resDetalleAsiento,
							'mensaje'	=> 'No se pudo registrar la Nota crédito de clientes'
						);

						return $this->response($respuesta);
					}
				}

				//FIN Procedimiento para llenar costo inventario




				// Procedimiento para llenar costo costo
				
				foreach ($DetalleConsolidadoCostoCosto as $key => $posicion) {
					$grantotalCostoCosto = 0;
					$grantotalCostoCostoOriginal = 0;
					$cuentaCosto = "";
					$dbito = 0;
					$cdito = 0;
					$MontoSysDB = 0;
					$MontoSysCR = 0;
					foreach ($posicion as $key => $value) {

						$CUENTASINV = $this->account->getAccountItem($value->nc1_itemcode, $value->nc1_whscode);
						// print_r($CUENTASINV);exit;
						if ( isset($CUENTASINV['error']) && $CUENTASINV['error'] == false ) {
							$dbito = 0;
							$cdito = 0;
							$MontoSysDB = 0;
							$MontoSysCR = 0;

							$sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode";

							$resCosto = $this->pedeo->queryTable($sqlCosto, array(":bdi_itemcode" => $value->nc1_itemcode));

							if (isset($resCosto[0])) {

								if ( $value->gift == 0 ){
									$cuentaCosto = $CUENTASINV['data']['acct_cost'];


									$costoArticulo = $resCosto[0]['bdi_avgprice'];
									$cantidadArticulo = $value->nc1_quantity;
									$grantotalCostoCosto = ($grantotalCostoCosto + ($costoArticulo * $cantidadArticulo));

								}else{
									$costoArticulo = $resCosto[0]['bdi_avgprice'];
									$cantidadArticulo = $value->nc1_quantity;

									$VALUE_GIFT = $VALUE_GIFT + ($costoArticulo * $cantidadArticulo);

									array_push($DETALLE_GIFT, array( "monto" => $costoArticulo * $cantidadArticulo, "item" => $value->nc1_itemcode, "proyecto" =>$value->ac1_prj_code , "centrocosto" =>$value->ac1_prc_code, "unidadnegocio" => $value->ac1_uncode, "whscode" => $value->nc1_whscode ));
								}

								
							} else {

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => 'hola1',
									'mensaje'	=> 'No se encontro el costo para el item: ' . $value->nc1_itemcode
								);

								return $this->response($respuesta);
							}
						} else {
							// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
							// se retorna el error y se detiene la ejecucion del codigo restante.
							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data'	  => $CUENTASINV,
								'mensaje'	=> 'No se encontro la cuenta para el item ' . $value->nc1_itemcode
							);

							return $this->response($respuesta);
						}
					}

					$codigo3 = substr($cuentaCosto, 0, 1);

					$grantotalCostoCostoOriginal = $grantotalCostoCosto;

					if (trim($Data['vnc_currency']) != $MONEDALOCAL) {

						$grantotalCostoCosto = ($grantotalCostoCosto / $TasaLocSys);
					}

					if ($codigo3 == 1 || $codigo3 == "1") {
						$dbito = 	$grantotalCostoCosto; //Se voltearon las cuenta
						if (trim($Data['vnc_currency']) != $MONEDASYS) {
							$MontoSysDB = ($dbito / $TasaLocSys); //Se voltearon las cuenta
						} else {
							$MontoSysDB = ($grantotalCostoCostoOriginal);
						}
					} else if ($codigo3 == 2 || $codigo3 == "2") {
						$dbito = 	$grantotalCostoCosto;
						if (trim($Data['vnc_currency']) != $MONEDASYS) {
							$MontoSysDB = ($dbito / $TasaLocSys); //Se voltearon las cuenta
						} else {
							$MontoSysDB = ($grantotalCostoCostoOriginal);
						}
					} else if ($codigo3 == 3 || $codigo3 == "3") {
						$dbito = 	$grantotalCostoCosto;
						if (trim($Data['vnc_currency']) != $MONEDASYS) {
							$MontoSysDB = ($dbito / $TasaLocSys); //Se voltearon las cuenta
						} else {
							$MontoSysDB = ($grantotalCostoCostoOriginal);
						}
					} else if ($codigo3 == 4 || $codigo3 == "4") {
						$dbito = 	$grantotalCostoCosto;
						if (trim($Data['vnc_currency']) != $MONEDASYS) {
							$MontoSysDB = ($dbito / $TasaLocSys); //Se voltearon las cuenta
						} else {
							$MontoSysDB = ($grantotalCostoCostoOriginal);
						}
					} else if ($codigo3 == 5  || $codigo3 == "5") {
						$cdito = 	$grantotalCostoCosto;
						if (trim($Data['vnc_currency']) != $MONEDASYS) {
							$MontoSysCR = ($cdito / $TasaLocSys); //Se voltearon las cuenta
						} else {
							$MontoSysCR = ($grantotalCostoCostoOriginal);
						}
					} else if ($codigo3 == 6 || $codigo3 == "6") {
						$cdito = 	$grantotalCostoCosto;
						if (trim($Data['vnc_currency']) != $MONEDASYS) {
							$MontoSysCR = ($cdito / $TasaLocSys); //Se voltearon las cuenta
						} else {
							$MontoSysCR = ($grantotalCostoCostoOriginal);
						}
					} else if ($codigo3 == 7 || $codigo3 == "7") {
						$cdito = 	$grantotalCostoCosto;
						if (trim($Data['vnc_currency']) != $MONEDASYS) {
							$MontoSysCR = ($cdito / $TasaLocSys); //Se voltearon las cuenta
						} else {
							$MontoSysCR = ($grantotalCostoCostoOriginal);
						}
					} else if ($codigo3 == 9 || $codigo3 == "9") {
						$dbito = 	$grantotalCostoCosto;
						if (trim($Data['vnc_currency']) != $MONEDASYS) {
							$MontoSysDB = ($dbito / $TasaLocSys); //Se voltearon las cuenta
						} else {
							$MontoSysDB = ($grantotalCostoCostoOriginal);
						}
					}

					if ( $dbito > 0 || $cdito > 0 ){

						$AC1LINE = $AC1LINE + 1;

						$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

							':ac1_trans_id' => $resInsertAsiento,
							':ac1_account' => $cuentaCosto,
							':ac1_debit' => round($dbito, $DECI_MALES),
							':ac1_credit' => round($cdito, $DECI_MALES),
							':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
							':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
							':ac1_currex' => 0,
							':ac1_doc_date' => $this->validateDate($Data['vnc_docdate']) ? $Data['vnc_docdate'] : NULL,
							':ac1_doc_duedate' => $this->validateDate($Data['vnc_duedate']) ? $Data['vnc_duedate'] : NULL,
							':ac1_debit_import' => 0,
							':ac1_credit_import' => 0,
							':ac1_debit_importsys' => 0,
							':ac1_credit_importsys' => 0,
							':ac1_font_key' => $resInsert,
							':ac1_font_line' => 1,
							':ac1_font_type' => is_numeric($Data['vnc_doctype']) ? $Data['vnc_doctype'] : 0,
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
							':ac1_made_user' => isset($Data['vnc_createby']) ? $Data['vnc_createby'] : NULL,
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
							':ac1_legal_num' => isset($Data['vnc_cardcode']) ? $Data['vnc_cardcode'] : NULL,
							':ac1_codref' => 1,
							':ac1_line'   => $AC1LINE,
							':ac1_base_tax' => 0,
							':business' => $Data['business'],
							':branch' 	=> $Data['branch'],
							':ac1_codret' => NULL
						));

						if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
							// Se verifica que el detalle no de error insertando //
						} else {

							// si falla algun insert del detalle de la Nota crédito de clientes se devuelven los cambios realizados por la transaccion,
							// se retorna el error y se detiene la ejecucion del codigo restante.
							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data'	  => $resDetalleAsiento,
								'mensaje'	=> 'No se pudo registrar la Nota crédito de clientes'
							);

							return $this->response($respuesta);
						}
					}
				}
				//FIN Procedimiento para llenar costo costo

				//Procedimiento para llenar cuentas por cobrar
				$sqlDetailFv = "SELECT * FROM vfv1 WHERE vfv1.fv1_docentry = :fv1_docentry AND vfv1.fv1_itemcode = :fv1_itemcode AND vfv1.fv1_linenum = :fv1_linenum  AND vfv1.fv1_gift = :fv1_gift";
				$resDetailFV = $this->pedeo->queryTable($sqlDetailFv,array(':fv1_gift' => 0,':fv1_docentry' => $Data['vnc_baseentry'],':fv1_itemcode' => $detail['nc1_itemcode'],':fv1_linenum' => $detail['nc1_baseline']));
				$TasaIGTF = 0;
				if(isset($resDetailFV[0])){
					$sqlcuentaCxC = "SELECT  f1.dms_card_code, f2.mgs_acct FROM dmsn AS f1
															JOIN dmgs  AS f2
															ON CAST(f2.mgs_id AS varchar(100)) = f1.dms_group_num
															WHERE  f1.dms_card_code = :dms_card_code
															AND f1.dms_card_type = '1'"; //1 para clientes";

					$rescuentaCxC = $this->pedeo->queryTable($sqlcuentaCxC, array(":dms_card_code" => $Data['vnc_cardcode']));
					
					if (isset($Data['dvf_igtf']) && $Data['dvf_igtf'] > 0) {

						$sqlTasaIGTF = "SELECT COALESCE( get_tax_currency(:moneda, :fecha), 0) AS tasa";
						$resTasaIGTF = $this->pedeo->queryTable($sqlTasaIGTF, array(


							':moneda' => $Data['vnc_currency'],
							':fecha'  => $Data['vnc_docdate']

						));

						if (isset($resTasaIGTF[0]) && $resTasaIGTF[0]['tasa'] > 0) {


							$TasaIGTF = $resTasaIGTF[0]['tasa'];
						} else {


							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data'	  => $resTasaIGTF,
								'mensaje'	=> 'No se encontro la tasa para el impuesto IGTF'
							);

							return $this->response($respuesta);
						}
					}

					if (isset($rescuentaCxC[0])) {

						$debitoo = 0;
						$creditoo = 0;
						$MontoSysDB = 0;
						$MontoSysCR = 0;
						$docTotal = 0;
						$docTotalOriginal = 0;
						$MontoIGTF = 0;
						$MontoIGTFSYS = 0;

						if (isset($Data['dvf_igtf']) && $Data['dvf_igtf']  > 0) {

							$MontoIGTF = $Data['dvf_igtf'];
							$MontoIGTFSYS  = $Data['dvf_igtf'];


							if (trim($Data['vnc_currency']) != $MONEDALOCAL) {

								$MontoIGTF = ($MontoIGTF * $TasaDocLoc);
								$MontoIGTFSYS = ($MontoIGTF / $TasaLocSys);
							}

							if (trim($Data['vnc_currency']) != $MONEDASYS) {

								$MontoIGTFSYS = ($MontoIGTF / $TasaLocSys);
							} else {

								$MontoIGTFSYS = $Data['dvf_igtf'];
							}
						}

						$cuentaCxC = $rescuentaCxC[0]['mgs_acct'];
						$codigo2 = substr($rescuentaCxC[0]['mgs_acct'], 0, 1);

						$MontoIGTFSYS = round(($MontoIGTF / $TasaLocSys), $DECI_MALES);


						if ($codigo2 == 1 || $codigo2 == "1") {

							$creditoo = ($TOTALCXCLOC + $TOTALCXCLOCIVA);
							$MontoSysCR =	($TOTALCXCSYS + $TOTALCXCSYSIVA);

							$creditoo = ($creditoo + $MontoIGTF);
							$MontoSysCR =	($MontoSysCR + $MontoIGTFSYS);
						} else if ($codigo2 == 2 || $codigo2 == "2") {

							$creditoo = ($TOTALCXCLOC + $TOTALCXCLOCIVA);
							$MontoSysCR =	($TOTALCXCSYS + $TOTALCXCSYSIVA);

							$creditoo = ($creditoo + $MontoIGTF);
							$MontoSysCR =	($MontoSysCR + $MontoIGTFSYS);
						} else if ($codigo2 == 3 || $codigo2 == "3") {

							$creditoo = ($TOTALCXCLOC + $TOTALCXCLOCIVA);
							$MontoSysCR = ($TOTALCXCSYS + $TOTALCXCSYSIVA);

							$creditoo = ($creditoo + $MontoIGTF);
							$MontoSysCR =	($MontoSysCR + $MontoIGTFSYS);
						} else if ($codigo2 == 4 || $codigo2 == "4") {

							$creditoo = ($TOTALCXCLOC + $TOTALCXCLOCIVA);
							$MontoSysCR =	($TOTALCXCSYS + $TOTALCXCSYSIVA);

							$creditoo = ($creditoo + $MontoIGTF);
							$MontoSysCR =	($MontoSysCR + $MontoIGTFSYS);
						} else if ($codigo2 == 5  || $codigo2 == "5") {

							$creditoo = ($TOTALCXCLOC + $TOTALCXCLOCIVA);
							$MontoSysCR =	($TOTALCXCSYS + $TOTALCXCSYSIVA);


							$creditoo = ($creditoo + $MontoIGTF);
							$MontoSysCR =	($MontoSysCR + $MontoIGTFSYS);
						} else if ($codigo2 == 6 || $codigo2 == "6") {

							$creditoo = ($TOTALCXCLOC + $TOTALCXCLOCIVA);
							$MontoSysCR =	($TOTALCXCSYS + $TOTALCXCSYSIVA);


							$creditoo = ($creditoo + $MontoIGTF);
							$MontoSysCR =	($MontoSysCR + $MontoIGTFSYS);
						} else if ($codigo2 == 7 || $codigo2 == "7") {

							$creditoo = ($TOTALCXCLOC + $TOTALCXCLOCIVA);
							$MontoSysCR =	($TOTALCXCSYS + $TOTALCXCSYSIVA);


							$creditoo = ($creditoo + $MontoIGTF);
							$MontoSysCR =	($MontoSysCR + $MontoIGTFSYS);
						} else if ($codigo2 == 9 || $codigo2 == "9") {

							$creditoo = ($TOTALCXCLOC + $TOTALCXCLOCIVA);
							$MontoSysCR =	($TOTALCXCSYS + $TOTALCXCSYSIVA);

							$creditoo = ($creditoo + $MontoIGTF);
							$MontoSysCR =	($MontoSysCR + $MontoIGTFSYS);
						}

						if ($creditoo == 0) {
							$SumaCreditosSYS = ($SumaCreditosSYS + $MontoIGTFSYS);
						} else {
							$SumaDebitosSYS = ($SumaDebitosSYS + $MontoIGTFSYS);
						}


						$RetencionDescuentoSYS = 0;
						$RetencionDescuentoLOC = 0;
	
						if (is_array($ContenidoRentencion)) {
							if (intval(count($ContenidoRentencion)) > 0) {
								
								if (isset($Data['vnc_totalretiva']) && is_numeric($Data['vnc_totalretiva']) && ( $Data['vnc_totalretiva'] * -1  > 0 ) ){
	
									if (trim($Data['vnc_currency']) != $MONEDALOCAL) {
	
										$RetencionDescuentoLOC = $RetencionDescuentoLOC + ( ( $Data['vnc_totalretiva'] * -1 ) * $TasaDocLoc);
									
									}else{
										$RetencionDescuentoLOC = $Data['vnc_totalretiva'] * -1;
									}
	
					
	
									if (trim($Data['vnc_currency']) != $MONEDASYS) {
	
										$RetencionDescuentoSYS = $RetencionDescuentoSYS + ( ( $Data['vnc_totalretiva'] * -1 ) / $TasaLocSys );
									} else {
			
										$RetencionDescuentoSYS = ( $Data['vnc_totalretiva'] * -1 );
									}
	
			
								}
	
	
								if (isset($Data['vnc_totalret']) && is_numeric($Data['vnc_totalret']) && ( $Data['vnc_totalret'] * -1  > 0 ) ){
	
									if (trim($Data['vnc_currency']) != $MONEDALOCAL) {
	
										$RetencionDescuentoLOC = $RetencionDescuentoLOC + ( ( $Data['vnc_totalret'] * -1 ) * $TasaDocLoc);
									
									}else{
										$RetencionDescuentoLOC = $RetencionDescuentoLOC + ($Data['vnc_totalret'] * -1);
									}
	
	
									if (trim($Data['vnc_currency']) != $MONEDASYS) {
	
										$RetencionDescuentoSYS = $RetencionDescuentoSYS + ( ( $Data['vnc_totalret'] * -1 ) / $TasaLocSys );
									} else {
			
										$RetencionDescuentoSYS =  $Data['vnc_totalret'] * -1 ;
									}
								}
								
							}
						}
	
						$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
						$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));

						if ( $debitoo > 0 ){

							$SumaDebitosSYS  = $SumaDebitosSYS - round($RetencionDescuentoSYS, $DECI_MALES);
							$SumaCreditosSYS = $SumaCreditosSYS - round($RetencionDescuentoSYS, $DECI_MALES);
	
							$debitoo   = $debitoo - round($RetencionDescuentoLOC, $DECI_MALES);
							$MontoSysDB = $MontoSysDB - round($RetencionDescuentoSYS, $DECI_MALES);
	
						}else{
						
							$SumaCreditosSYS = $SumaCreditosSYS - round($RetencionDescuentoSYS, $DECI_MALES);
							$SumaDebitosSYS  = $SumaDebitosSYS - round($RetencionDescuentoSYS, $DECI_MALES);
							$creditoo  = $creditoo - round($RetencionDescuentoLOC, $DECI_MALES);
							$MontoSysCR = $MontoSysCR - round($RetencionDescuentoSYS, $DECI_MALES);
						}
	
		



						$AC1LINE = $AC1LINE + 1;

						$ValorVenDebito = 0;

						//PARA COMPESAR LA NOTA DE CREDITO CON LA FACTURA
						//SI VIENE DE UN COPIAR FACTURA
						if ($Data['vnc_basetype'] == 5) {
							$ValorVenDebito = $creditoo;
						}

						$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

							':ac1_trans_id' => $resInsertAsiento,
							':ac1_account' => $cuentaCxC,
							':ac1_debit' => round($debitoo, $DECI_MALES),
							':ac1_credit' => round($creditoo, $DECI_MALES),
							':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
							':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
							':ac1_currex' => 0,
							':ac1_doc_date' => $this->validateDate($Data['vnc_docdate']) ? $Data['vnc_docdate'] : NULL,
							':ac1_doc_duedate' => $this->validateDate($Data['vnc_duedate']) ? $Data['vnc_duedate'] : NULL,
							':ac1_debit_import' => 0,
							':ac1_credit_import' => 0,
							':ac1_debit_importsys' => 0,
							':ac1_credit_importsys' => 0,
							':ac1_font_key' => $resInsert,
							':ac1_font_line' => 1,
							':ac1_font_type' => is_numeric($Data['vnc_doctype']) ? $Data['vnc_doctype'] : 0,
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
							':ac1_made_user' => isset($Data['vnc_createby']) ? $Data['vnc_createby'] : NULL,
							':ac1_accperiod' => 1,
							':ac1_close' => 0,
							':ac1_cord' => 0,
							':ac1_ven_debit' => round($ValorVenDebito, $DECI_MALES),
							':ac1_ven_credit' => round($creditoo, $DECI_MALES),
							':ac1_fiscal_acct' => 0,
							':ac1_taxid' => 0,
							':ac1_isrti' => 0,
							':ac1_basert' => 0,
							':ac1_mmcode' => 0,
							':ac1_legal_num' => isset($Data['vnc_cardcode']) ? $Data['vnc_cardcode'] : NULL,
							':ac1_codref' => 1,
							':ac1_line'   => $AC1LINE,
							':ac1_base_tax' => 0,
							':business' => $Data['business'],
							':branch' 	=> $Data['branch'],
							':ac1_codret' => NULL
						));

						if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
							// Se verifica que el detalle no de error insertando //
						} else {

							// si falla algun insert del detalle de la Nota crédito de clientes se devuelven los cambios realizados por la transaccion,
							// se retorna el error y se detiene la ejecucion del codigo restante.
							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data'	  => $resDetalleAsiento,
								'mensaje'	=> 'No se pudo registrar la Nota crédito de clientes'
							);

							return $this->response($respuesta);
						}
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'	  => $resDetalleAsiento,
							'mensaje'	=> 'No se pudo registrar la Nota crédito de clientes, el tercero no tiene cuenta asociada'
						);

						return $this->response($respuesta);
					}
				//FIN Procedimiento para llenar cuentas por cobrar
				}
					


				// SE VALIDA DIFERENCIA POR DECIMALES
				// Y SE AGREGA UN ASIENTO DE DIFERENCIA EN DECIMALES
				// SEGUN SEA EL CASO
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
								':ac1_doc_date' => $this->validateDate($Data['vnc_docdate']) ? $Data['vnc_docdate'] : NULL,
								':ac1_doc_duedate' => $this->validateDate($Data['vnc_duedate']) ? $Data['vnc_duedate'] : NULL,
								':ac1_debit_import' => 0,
								':ac1_credit_import' => 0,
								':ac1_debit_importsys' => 0,
								':ac1_credit_importsys' => 0,
								':ac1_font_key' => $resInsert,
								':ac1_font_line' => 1,
								':ac1_font_type' => is_numeric($Data['vnc_doctype']) ? $Data['vnc_doctype'] : 0,
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
								':ac1_made_user' => isset($Data['vnc_createby']) ? $Data['vnc_createby'] : NULL,
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
								':ac1_legal_num' => isset($Data['vnc_cardcode']) ? $Data['vnc_cardcode'] : NULL,
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

								return $this->response($respuesta);
							}
						}
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'	  => $resCuentaDiferenciaDecimal,
							'mensaje'	=> 'No se encontro la cuenta para adicionar la diferencia en decimales'
						);

						return $this->response($respuesta);
					}
				}
				// FIN VALIDACION DIFERENCIA EN DECIMALES

				// VALIDANDO IMPUESTO IGTF CASO PARA VENEZUELA



				if (isset($Data['dvf_igtf']) && $Data['dvf_igtf'] > 0) {

					foreach ($DetalleIgtf as $key => $val) {
						$sqlInsertIgtf = "INSERT INTO igtf(gtf_currency, gtf_docentry, gtf_doctype, gtf_value, gtf_tax, gtf_taxdivisa, gft_docvalue, gtf_collected, gtf_balancer)VALUES(:gtf_currency, :gtf_docentry, :gtf_doctype, :gtf_value, :gtf_tax, :gtf_taxdivisa, :gft_docvalue, :gtf_collected, :gtf_balancer)";
						$resInserIgtf = $this->pedeo->insertRow($sqlInsertIgtf, array(
							':gtf_currency'  => $val['gtf_currency'],
							':gtf_docentry'  => $resInsert,
							':gtf_doctype'   => $Data['vnc_doctype'],
							':gtf_value'	   => $val['gtf_value'],
							':gtf_tax'		   => $Data['dvf_taxigtf'],
							':gtf_taxdivisa' => $val['gtf_taxdivisa'],
							':gft_docvalue'  => $val['gft_docvalue'],
							':gtf_collected' => $val['gtf_collected'],
							':gtf_balancer'  => $val['gtf_balancer']

						));

						if (is_numeric($resInserIgtf) && $resInserIgtf > 0) {
						} else {

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error' => true,
								'data'  => $resInserIgtf,
								'mensaje' => 'Error al insertar el detalle del IGTF'
							);

							return $this->response($respuesta);
						}
					}



					$sqlCuentaIGTF = "SELECT imm_acctcode FROM timm WHERE imm_code = :imm_code";
					$resCuentaIGTF = $this->pedeo->queryTable($sqlCuentaIGTF, array(
						':imm_code' => $Data['dvf_igtfcode']
					));

					if (isset($resCuentaIGTF[0]) && $resCuentaIGTF[0]['imm_acctcode'] > 0) {

						$cdito = 0;
						$dbito = 0;
						$MontoSysCR = 0;
						$MontoSysDB = 0;
						$BaseIgtf = $Data['dvf_igtfapplyed'];


						$dbito = $Data['dvf_igtf'];



						if (trim($Data['vnc_currency']) != $MONEDALOCAL) {

							$dbito = ($dbito * $TasaDocLoc);
							$BaseIgtf = ($BaseIgtf * $TasaDocLoc);
						}



						if (trim($Data['vnc_currency']) != $MONEDASYS) {
							$MontoSysDB = ($dbito / $TasaLocSys);
						} else {
							$MontoSysDB = $Data['dvf_igtf'];
						}

						$AC1LINE = $AC1LINE + 1;
						$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

							':ac1_trans_id' => $resInsertAsiento,
							':ac1_account' => $resCuentaIGTF[0]['imm_acctcode'],
							':ac1_debit' => round($dbito, $DECI_MALES),
							':ac1_credit' => round($cdito, $DECI_MALES),
							':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
							':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
							':ac1_currex' => 0,
							':ac1_doc_date' => $this->validateDate($Data['vnc_docdate']) ? $Data['vnc_docdate'] : NULL,
							':ac1_doc_duedate' => $this->validateDate($Data['vnc_duedate']) ? $Data['vnc_duedate'] : NULL,
							':ac1_debit_import' => 0,
							':ac1_credit_import' => 0,
							':ac1_debit_importsys' => 0,
							':ac1_credit_importsys' => 0,
							':ac1_font_key' => $resInsert,
							':ac1_font_line' => 1,
							':ac1_font_type' => is_numeric($Data['vnc_doctype']) ? $Data['vnc_doctype'] : 0,
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
							':ac1_made_user' => isset($Data['vnc_createby']) ? $Data['vnc_createby'] : NULL,
							':ac1_accperiod' => 1,
							':ac1_close' => 0,
							':ac1_cord' => 0,
							':ac1_ven_debit' => round($dbito, $DECI_MALES),
							':ac1_ven_credit' => round($cdito, $DECI_MALES),
							':ac1_fiscal_acct' => 0,
							':ac1_taxid' => isset($Data['vnc_igtfcode']) ? $Data['vnc_igtfcode'] : NULL,
							':ac1_isrti' => isset($Data['vnc_taxigtf']) ? $Data['vnc_taxigtf'] : NULL,
							':ac1_basert' => 0,
							':ac1_mmcode' => 0,
							':ac1_legal_num' => isset($Data['vnc_cardcode']) ? $Data['vnc_cardcode'] : NULL,
							':ac1_codref' => 1,
							':ac1_line'   => 	$AC1LINE,
							':ac1_base_tax' => $BaseIgtf,
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

							return $this->response($respuesta);
						}
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'	  => $resCuentaIGTF,
							'mensaje'	=> 'No se encontro la cuenta contable para el impuesto IGTF'
						);

						return $this->response($respuesta);
					}
				}
				//

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
								'mensaje'	=> 'No se pudo registrar la nota credito, no se encontro la cuenta para la retencion ' . $value->crt_typert
							);

							$this->response($respuesta);

							return;
						}
					}

					$Basert = $BaseLineaRet;
					$totalRetencionOriginal = $totalRetencion;

					if (trim($Data['vnc_currency']) != $MONEDALOCAL) {
						$totalRetencion = ($totalRetencion * $TasaDocLoc);
						$BaseLineaRet = ($BaseLineaRet * $TasaDocLoc);
						$Basert = $BaseLineaRet;
					}


					if (trim($Data['vnc_currency']) != $MONEDASYS) {
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
						':ac1_credit_sys' =>  round($MontoSysCR, $DECI_MALES),
						':ac1_currex' => 0,
						':ac1_doc_date' => $this->validateDate($Data['vnc_docdate']) ? $Data['vnc_docdate'] : NULL,
						':ac1_doc_duedate' => $this->validateDate($Data['vnc_duedate']) ? $Data['vnc_duedate'] : NULL,
						':ac1_debit_import' => 0,
						':ac1_credit_import' => 0,
						':ac1_debit_importsys' => 0,
						':ac1_credit_importsys' => 0,
						':ac1_font_key' => $resInsert,
						':ac1_font_line' => 1,
						':ac1_font_type' => is_numeric($Data['vnc_doctype']) ? $Data['vnc_doctype'] : 0,
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
						':ac1_made_user' => isset($Data['vnc_createby']) ? $Data['vnc_createby'] : NULL,
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
						':ac1_legal_num' => isset($Data['vnc_cardcode']) ? $Data['vnc_cardcode'] : NULL,
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
							'mensaje' => 'No se pudo registrar la nota credito'
						);

						$this->response($respuesta);

						return;
					}
				}
				//FIN PROCEDIMIENTO PARA LLENAR ASIENTO DE RENTENCIONES


				// ASIENTO PARA MOSTRAR CASO ITEM  OBSEQUIO
				if ( isset($DETALLE_GIFT[0]) ) {

					$ITEM_PROCESADO = [];
					foreach ($DETALLE_GIFT as $key => $element) {

						$itemm = $element['item'];
						$whscode = $element['whscode'];

						if (!in_array($itemm, $ITEM_PROCESADO)){

							$monto = 0;
							$montosys = 0;
							$montoorig = 0;
							$cuenta = "";
							$unidad = "";
							$proyecto = "";
							$ccosto = "";
	
							array_push($ITEM_PROCESADO, $itemm);
	
							foreach( $DETALLE_GIFT as $key => $item ){
	
								if ( $item['item'] == $itemm ){
	
									$monto = $monto + $item['monto'];
									$unidad = $item['unidadnegocio'];
									$proyecto = $item['proyecto'];
									$ccosto = $item['centrocosto'];
	
								}
	
							}

							$CUENTASINV = $this->account->getAccountItem($itemm, $whscode);
			
				
							if ( isset($CUENTASINV['error']) && $CUENTASINV['error'] == false ) {

								$cuenta = $CUENTASINV['data']['acct_out'];

							}else{

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $resDetalleAsiento,
									'mensaje'	=> 'No se encontro la cuenta parametrizada para el artículo '.$item
								);
		
								return $this->response($respuesta);
							}

							$montoorig  = $monto;

							if (trim($Data['vnc_currency']) != $MONEDALOCAL) {

								$monto = ($monto * $TasaDocLoc);
					
							}
		
		
							if (trim($Data['vnc_currency']) != $MONEDASYS) {
								$montosys = ($montoorig / $TasaLocSys);
							} else {
								$montosys = $montoorig;
							}

							$AC1LINE = $AC1LINE + 1;

							$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

								':ac1_trans_id' => $resInsertAsiento,
								':ac1_account' => $cuenta,
								':ac1_debit' => 0,
								':ac1_credit' => round($monto, $DECI_MALES),
								':ac1_debit_sys' => 0,
								':ac1_credit_sys' => round($montosys, $DECI_MALES),
								':ac1_currex' => 0,
								':ac1_doc_date' => $this->validateDate($Data['vnc_docdate']) ? $Data['vnc_docdate'] : NULL,
								':ac1_doc_duedate' => $this->validateDate($Data['vnc_duedate']) ? $Data['vnc_duedate'] : NULL,
								':ac1_debit_import' => 0,
								':ac1_credit_import' => 0,
								':ac1_debit_importsys' => 0,
								':ac1_credit_importsys' => 0,
								':ac1_font_key' => $resInsert,
								':ac1_font_line' => 1,
								':ac1_font_type' => is_numeric($Data['vnc_doctype']) ? $Data['vnc_doctype'] : 0,
								':ac1_accountvs' => 1,
								':ac1_doctype' => 18,
								':ac1_ref1' => "",
								':ac1_ref2' => "",
								':ac1_ref3' => "",
								':ac1_prc_code' => $ccosto,
								':ac1_uncode' => $unidad,
								':ac1_prj_code' => $proyecto,
								':ac1_rescon_date' => NULL,
								':ac1_recon_total' => 0,
								':ac1_made_user' => isset($Data['vnc_createby']) ? $Data['vnc_createby'] : NULL,
								':ac1_accperiod' => 1,
								':ac1_close' => 0,
								':ac1_cord' => 0,
								':ac1_ven_debit' => 0,
								':ac1_ven_credit' => NULL,
								':ac1_fiscal_acct' => 0,
								':ac1_taxid' => 0,
								':ac1_isrti' => NULL,
								':ac1_basert' => NULL,
								':ac1_mmcode' => 0,
								':ac1_legal_num' => isset($Data['vnc_cardcode']) ? $Data['vnc_cardcode'] : NULL,
								':ac1_codref' => 0,
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
									'mensaje'	=> 'No se pudo registrar la nota credito de ventas'
								);
		
								return $this->response($respuesta);
							}

						}
					}

				}
				// FIN ASIENTO PARA MOSTRAR CASO ITEM  OBSEQUIO

				// SE VALIDA QUE LA FACTURA BASE NO SEA MENOR QUE LA NOTA CREDITO
				if ($Data['vnc_basetype'] == 5) {

					$sqlBaseFactura = "SELECT * FROM dvfv WHERE dvf_docentry = :dvf_docentry";
					$resBaseFactura = $this->pedeo->queryTable($sqlBaseFactura, array(':dvf_docentry' => $Data['vnc_baseentry']));

					if (isset($resBaseFactura[0])) {

						$tFC = $resBaseFactura[0]['dvf_doctotal'];

						$tNC =  $Data['vnc_doctotal'];

						if (trim($resBaseFactura[0]['dvf_currency']) != $MONEDALOCAL) {
							$tFC = ($tFC * $TasaDocLoc);
						}

						if (trim($Data['vnc_currency']) != $MONEDALOCAL) {
							$tNC  = ($tNC * $TasaDocLoc);
						}


						if ($tNC > $tFC) {

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data'	  => $resBaseFactura,
								'mensaje'	=> 'La valor total de nota credito es mayor a la factura base'
							);

							return $this->response($respuesta);
						}
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'	  => $resBaseFactura,
							'mensaje'	=> 'No se encontro la factura base'
						);

						return $this->response($respuesta);
					}
				}

				//SE ACTUALIZA EL PAY TO DAY DE LA FACTURA
				if ($Data['vnc_basetype'] == 5) { // SOLO CUANDO ES UNA FACTURA

					$valorAppl =  $Data['vnc_doctotal'];

					if (trim($Data['vnc_currency']) != $MONEDALOCAL) {
						$valorAppl = $Data['vnc_doctotal'] * $TasaDocLoc;
					}

					$sqlUpdateFactPay = "UPDATE  dvfv  SET dvf_paytoday = COALESCE(dvf_paytoday,0)+:dvf_paytoday WHERE dvf_docentry = :dvf_docentry and dvf_doctype = :dvf_doctype";

					$resUpdateFactPay = $this->pedeo->updateRow($sqlUpdateFactPay, array(

						':dvf_paytoday' => $valorAppl,
						':dvf_docentry' => $Data['vnc_baseentry'],
						':dvf_doctype'  => $Data['vnc_basetype']

					));
					
					if (is_numeric($resUpdateFactPay) && $resUpdateFactPay == 1) {

					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' => $resUpdateFactPay,
							'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura ' . $Data['vnc_baseentry']
						);

						return $this->response($respuesta);
					}
				}



				// SE ACTUALIZA VALOR EN EL ASIENTO CONTABLE
				// GENERADO EN LA FACTURA
				if ($Data['vnc_basetype'] == 5) { // SOLO CUANDO ES UNA FACTURA

					$valorAppl =  $Data['vnc_doctotal'];

					if (trim($Data['vnc_currency']) != $MONEDALOCAL) {
						$valorAppl = $Data['vnc_doctotal'] * $TasaDocLoc;
					}
					$sqlDetailFv = "SELECT * FROM vfv1 WHERE vfv1.fv1_docentry = :fv1_docentry AND vfv1.fv1_itemcode = :fv1_itemcode AND vfv1.fv1_linenum = :fv1_linenum  AND vfv1.fv1_gift = :fv1_gift";
					$resDetailFV = $this->pedeo->queryTable($sqlDetailFv,array(':fv1_gift' => 0,':fv1_docentry' => $Data['vnc_baseentry'],':fv1_itemcode' => $detail['nc1_itemcode'],':fv1_linenum' => $detail['nc1_baseline']));
					if(isset($resDetailFV[0])){
						$sqlcuentaCxC = "SELECT  f1.dms_card_code, f2.mgs_acct FROM dmsn AS f1
																JOIN dmgs  AS f2
																ON CAST(f2.mgs_id AS varchar(100)) = f1.dms_group_num
																WHERE  f1.dms_card_code = :dms_card_code
																AND f1.dms_card_type = '1'"; //1 para clientes";

						$rescuentaCxC = $this->pedeo->queryTable($sqlcuentaCxC, array(":dms_card_code" => $Data['vnc_cardcode']));

						if (!isset($rescuentaCxC[0])) {
							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data'	  => $rescuentaCxC,
								'mensaje'	=> 'No se pudo registrar la factura de ventas, el tercero no tiene cuenta asociada'
							);

							return $this->response($respuesta);
						}

						$cuentaCxC = $rescuentaCxC[0]['mgs_acct'];

						$slqUpdateVenDebit = "UPDATE mac1
																			SET ac1_ven_credit = ac1_ven_credit + :ac1_ven_credit
																			WHERE ac1_legal_num = :ac1_legal_num
																			AND ac1_font_key = :ac1_font_key
																			AND ac1_font_type = :ac1_font_type
																			AND ac1_account = :ac1_account";
						$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

							':ac1_ven_credit' => $valorAppl,
							':ac1_legal_num'  => $Data['vnc_cardcode'],
							':ac1_font_key'   => $Data['vnc_baseentry'],
							':ac1_font_type'  => $Data['vnc_basetype'],
							':ac1_account'    => $cuentaCxC

						));
						// print_r($resUpdateVenDebit);exit;
						if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
						} else {
							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' => $resUpdateVenDebit,
								'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura ' . $Data['vnc_baseentry']
							);

							return $this->response($respuesta);
						}
					}
				}
				//
				//SE CIERRA LA NOTA CREADA
				if ($Data['vnc_basetype'] == 5) { // SOLO CUANDO ES UNA FACTURA

					$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																	VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

					$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


						':bed_docentry' => $resInsert,
						':bed_doctype' =>  $Data['vnc_doctype'],
						':bed_status' => 3, //ESTADO CERRADO
						':bed_createby' => $Data['vnc_createby'],
						':bed_date' => date('Y-m-d'),
						':bed_baseentry' => $Data['vnc_baseentry'],
						':bed_basetype' => $Data['vnc_basetype']
					));

					if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
					} else {
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' => $resInsertEstado,
							'mensaje'	=> 'No se pudo registrar la nota credito'
						);


						return $this->response($respuesta);
					}
				}


				// SE VALIDA SALDOS PARA CERRAR FACTURA
				if ($Data['vnc_basetype'] == 5) {

					$resEstado = $this->generic->validateBalanceAndClose($Data['vnc_baseentry'], $Data['vnc_basetype'], 'dvfv', 'dvf');

					if (isset($resEstado['error']) && $resEstado['error'] === true) {
						$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																							VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

						$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


							':bed_docentry' => $Data['vnc_baseentry'],
							':bed_doctype' => $Data['vnc_basetype'],
							':bed_status' => 3, //ESTADO CERRADO
							':bed_createby' => $Data['vnc_createby'],
							':bed_date' => date('Y-m-d'),
							':bed_baseentry' => $resInsert,
							':bed_basetype' => $Data['vnc_doctype']
						));


						if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
						} else {

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' => $resInsertEstado,
								'mensaje'	=> 'No se pudo registrar el pago'
							);


							return $this->response($respuesta);
						}
					}
				}

				// FIN DE OPERACIONES VITALES


				// $sqlmac1 = "SELECT * FROM  mac1 WHERE ac1_trans_id = :ac1_trans_id";
				// $ressqlmac1 = $this->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $resInsertAsiento ));
				// print_r(json_encode($ressqlmac1));
				// exit;

				//SE VALIDA LA CONTABILIDAD CREADA
				$validateCont = $this->generic->validateAccountingAccent($resInsertAsiento);

				// print_r($validateCont);exit;
				if (isset($validateCont['error']) && $validateCont['error'] == false) {
				} else {

					$ressqlmac1 = [];
					$sqlmac1 = "SELECT acc_name,ac1_account,ac1_debit,ac1_credit,ac1_debit_sys,ac1_credit_sys FROM  mac1 inner join dacc on ac1_account = acc_code WHERE ac1_trans_id = :ac1_trans_id";
					$ressqlmac1['contabilidad'] = $this->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $resInsertAsiento ));
		

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' 	  => $ressqlmac1,
						'mensaje' => $validateCont['mensaje']
					);

					return $this->response($respuesta);
				}
				//

				if ( isset($Data['preview']) && $Data['preview'] == 1 ) {

					$respuesta = $this->account->getAcounting($resInsertAsiento);

					$this->pedeo->trans_rollback();

					return $this->response($respuesta);

				} else {
					
					$this->pedeo->trans_commit();
				}

				$respuesta = array(
					'error' => false,
					'data' => $resInsert,
					'mensaje' => 'Nota crédito de clientes registrada con exito'
				);
			} else {
				// Se devuelven los cambios realizados en la transaccion
				// si occurre un error  y se muestra devuelve el error.
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data' => $resInsert,
					'mensaje'	=> 'No se pudo registrar la Nota crédito de clientes'
				);

				return $this->response($respuesta);
			}

		} catch (\Exception $e) {

			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data' 		=> $e,
				'mensaje'	=> 'No se pudo registrar la Nota crédito de clientes'
			);
		}

		$this->response($respuesta);
	}

	//ACTUALIZAR Nota crédito de clientes
	public function updateSalesNc_post()
	{

		$Data = $this->post();

		if (
			!isset($Data['vnc_docentry']) or !isset($Data['vnc_docnum']) or
			!isset($Data['vnc_docdate']) or !isset($Data['vnc_duedate']) or
			!isset($Data['vnc_duedev']) or !isset($Data['vnc_pricelist']) or
			!isset($Data['vnc_cardcode']) or !isset($Data['vnc_cardname']) or
			!isset($Data['vnc_currency']) or !isset($Data['vnc_contacid']) or
			!isset($Data['vnc_slpcode']) or !isset($Data['vnc_empid']) or
			!isset($Data['vnc_comment']) or !isset($Data['vnc_doctotal']) or
			!isset($Data['vnc_baseamnt']) or !isset($Data['vnc_taxtotal']) or
			!isset($Data['vnc_discprofit']) or !isset($Data['vnc_discount']) or
			!isset($Data['vnc_createat']) or !isset($Data['vnc_baseentry']) or
			!isset($Data['vnc_basetype']) or !isset($Data['vnc_doctype']) or
			!isset($Data['vnc_idadd']) or !isset($Data['vnc_adress']) or
			!isset($Data['vnc_paytype']) or !isset($Data['vnc_attch']) or
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
				'mensaje' => 'No se encontro el detalle de la Nota crédito de clientes'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlUpdate = "UPDATE dvnc	SET vnc_docdate=:vnc_docdate,vnc_duedate=:vnc_duedate, vnc_duedev=:vnc_duedev, vnc_pricelist=:vnc_pricelist, vnc_cardcode=:vnc_cardcode,
				vnc_cardname=:vnc_cardname, vnc_currency=:vnc_currency, vnc_contacid=:vnc_contacid, vnc_slpcode=:vnc_slpcode,
				vnc_empid=:vnc_empid, vnc_comment=:vnc_comment, vnc_doctotal=:vnc_doctotal, vnc_baseamnt=:vnc_baseamnt,
				vnc_taxtotal=:vnc_taxtotal, vnc_discprofit=:vnc_discprofit, vnc_discount=:vnc_discount, vnc_createat=:vnc_createat,
				vnc_baseentry=:vnc_baseentry, vnc_basetype=:vnc_basetype, vnc_doctype=:vnc_doctype, vnc_idadd=:vnc_idadd,
				vnc_adress=:vnc_adress, vnc_paytype=:vnc_paytype WHERE vnc_docentry=:vnc_docentry";

		$this->pedeo->trans_begin();

		$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
			':vnc_docnum' => is_numeric($Data['vnc_docnum']) ? $Data['vnc_docnum'] : 0,
			':vnc_docdate' => $this->validateDate($Data['vnc_docdate']) ? $Data['vnc_docdate'] : NULL,
			':vnc_duedate' => $this->validateDate($Data['vnc_duedate']) ? $Data['vnc_duedate'] : NULL,
			':vnc_duedev' => $this->validateDate($Data['vnc_duedev']) ? $Data['vnc_duedev'] : NULL,
			':vnc_pricelist' => is_numeric($Data['vnc_pricelist']) ? $Data['vnc_pricelist'] : 0,
			':vnc_cardcode' => isset($Data['vnc_pricelist']) ? $Data['vnc_pricelist'] : NULL,
			':vnc_cardname' => isset($Data['vnc_cardname']) ? $Data['vnc_cardname'] : NULL,
			':vnc_currency' => isset($Data['vnc_currency']) ? $Data['vnc_currency'] : NULL,
			':vnc_contacid' => isset($Data['vnc_contacid']) ? $Data['vnc_contacid'] : NULL,
			':vnc_slpcode' => is_numeric($Data['vnc_slpcode']) ? $Data['vnc_slpcode'] : 0,
			':vnc_empid' => is_numeric($Data['vnc_empid']) ? $Data['vnc_empid'] : 0,
			':vnc_comment' => isset($Data['vnc_comment']) ? $Data['vnc_comment'] : NULL,
			':vnc_doctotal' => is_numeric($Data['vnc_doctotal']) ? $Data['vnc_doctotal'] : 0,
			':vnc_baseamnt' => is_numeric($Data['vnc_baseamnt']) ? $Data['vnc_baseamnt'] : 0,
			':vnc_taxtotal' => is_numeric($Data['vnc_taxtotal']) ? $Data['vnc_taxtotal'] : 0,
			':vnc_discprofit' => is_numeric($Data['vnc_discprofit']) ? $Data['vnc_discprofit'] : 0,
			':vnc_discount' => is_numeric($Data['vnc_discount']) ? $Data['vnc_discount'] : 0,
			':vnc_createat' => $this->validateDate($Data['vnc_createat']) ? $Data['vnc_createat'] : NULL,
			':vnc_baseentry' => is_numeric($Data['vnc_baseentry']) ? $Data['vnc_baseentry'] : 0,
			':vnc_basetype' => is_numeric($Data['vnc_basetype']) ? $Data['vnc_basetype'] : 0,
			':vnc_doctype' => is_numeric($Data['vnc_doctype']) ? $Data['vnc_doctype'] : 0,
			':vnc_idadd' => isset($Data['vnc_idadd']) ? $Data['vnc_idadd'] : NULL,
			':vnc_adress' => isset($Data['vnc_adress']) ? $Data['vnc_adress'] : NULL,
			':vnc_paytype' => is_numeric($Data['vnc_paytype']) ? $Data['vnc_paytype'] : 0,
			':business' => isset($Data['business']) ? $Data['business'] : NULL,
			':branch' => isset($Data['branch']) ? $Data['branch'] : NULL,
			':vnc_docentry' => $Data['vnc_docentry']
		));

		if (is_numeric($resUpdate) && $resUpdate == 1) {

			$this->pedeo->queryTable("DELETE FROM vnc1 WHERE nc1_docentry=:nc1_docentry", array(':nc1_docentry' => $Data['vnc_docentry']));

			foreach ($ContenidoDetalle as $key => $detail) {

				$sqlInsertDetail = "INSERT INTO vnc1(nc1_docentry, nc1_itemcode, nc1_itemname, nc1_quantity, nc1_uom, nc1_whscode,
																			nc1_price, nc1_vat, nc1_vatsum, nc1_discount, nc1_linetotal, nc1_costcode, nc1_ubusiness, nc1_project,
																			nc1_acctcode, nc1_basetype, nc1_doctype, nc1_avprice, nc1_inventory,nc1_cuentaIva,nc1_ubication,ote_code)VALUES(:nc1_docentry, :nc1_itemcode, :nc1_itemname, :nc1_quantity,
																			:nc1_uom, :nc1_whscode,:nc1_price, :nc1_vat, :nc1_vatsum, :nc1_discount, :nc1_linetotal, :nc1_costcode, :nc1_ubusiness, :nc1_project,
																			:nc1_acctcode, :nc1_basetype, :nc1_doctype, :nc1_avprice, :nc1_inventory,:nc1_cuentaIva,:nc1_ubication,:ote_code)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':nc1_docentry' => $Data['vnc_docentry'],
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
					':nc1_ubication' => is_numeric($detail['nc1_ubication']) ? $detail['nc1_ubication'] : NULL,
					':ote_code' => is_numeric($detail['ote_code']) ? $detail['ote_code'] : NULL,
				));

				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle de la Nota crédito de clientes se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertDetail,
						'mensaje'	=> 'No se pudo registrar la Nota crédito de clientes'
					);

					$this->response($respuesta);

					return;
				}
			}


			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resUpdate,
				'mensaje' => 'Nota crédito de clientes actualizada con exito'
			);
		} else {

			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data'    => $resUpdate,
				'mensaje'	=> 'No se pudo actualizar la Nota crédito de clientes'
			);
		}

		$this->response($respuesta);
	}


	//OBTENER Nota crédito de clientesES
	public function getSalesNc_get()
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

		$sqlSelect = self::getColumn('dvnc', 'vnc', '', '', $DECI_MALES, $Data['business'], $Data['branch']);


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


	//OBTENER Nota crédito de clientes POR ID
	public function getSalesNcById_get()
	{

		$Data = $this->get();

		if (!isset($Data['vnc_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM dvnc WHERE vnc_docentry =:vnc_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":vnc_docentry" => $Data['vnc_docentry']));

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


	//OBTENER Nota crédito de clientes DETALLE POR ID
	public function getSalesNcDetail_get()
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

		$sqlSelect = "SELECT vnc1.*, dmar.dma_series_code
					FROM vnc1
					INNER JOIN dmar
					ON vnc1.nc1_itemcode = dmar.dma_item_code
					WHERE nc1_docentry =:nc1_docentry";

		$sqlSelectNcv = "SELECT round(get_dynamic_conversion(vnc_currency,vnc_currency,vnc_docdate,vnc_igtf,get_localcur()), get_decimals()) as dvf_igtf, round(get_dynamic_conversion(vnc_currency,vnc_currency,vnc_docdate,vnc_igtfapplyed,get_localcur()), get_decimals()) as dvf_igtfapplyed, vnc_igtfcode as dvf_igtfcode, igtf.*
						FROM dvnc
						LEFT JOIN igtf
						ON vnc_docentry = gtf_docentry
						AND vnc_doctype = gtf_doctype
						WHERE vnc_docentry = :nc1_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":nc1_docentry" => $Data['nc1_docentry']));
		$resSelectNcv = $this->pedeo->queryTable($sqlSelectNcv, array(':nc1_docentry' => $Data['nc1_docentry']));

		if (isset($resSelectNcv[0])) {

			if (isset($resSelect[0])) {

				$arr = [];

				$arr['detalle'] = $resSelect;
				$arr['complemento'] = $resSelectNcv;

				$respuesta = array(
					'error' => false,
					'data'  => $arr,
					'mensaje' => ''
				);
			} else {

				$respuesta = array(
					'error'   => true,
					'data' => array(),
					'mensaje'	=> 'busqueda sin resultados'
				);
			}
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}



	//OBTENER NOTA CREDITO POR ID SOCIO DE NEGOCIO
	public function getSalesNcBySN_get()
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

		$sqlSelect = " SELECT * 
					FROM dvnc 
					left join estado_doc t1 
                    on t0.vnc_docentry = t1.entry and t0.vnc_doctype = t1.tipo
					left join responsestatus t2 
                    on t1.entry = t2.id and t1.tipo = t2.tipo
					WHERE t2.estado = 'Abierto' and t0.vnc_cardcode =:vnc_cardcode
					AND t0.business = :business 
					AND t0.branch = :branch";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":vnc_cardcode" => $Data['dms_card_code'],":business" => $Data['business'],":branch" => $Data['branch']));

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

	private function getUrl($data)
	{
		$url = "";

		if ($data == NULL) {

			return $url;
		}

		$ruta = '/var/www/html/serpent/assets/img/anexos/';
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