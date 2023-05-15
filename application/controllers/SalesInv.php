<?php
// FACTURA DE VENTAS
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class SalesInv extends REST_Controller
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
	}

	//CREAR NUEVA FACTURA DE VENTAS
	public function createSalesInv_post()
	{
		$Data = $this->post();
		$TasaDocLoc = 0;
		$TasaLocSys = 0;
		$MONEDALOCAL = "";
		$MONEDASYS = "";

		// print_r($Data);exit;
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
		$ManejaSerial = 0;
		$ManejaLote = 0;
		$IVASINTASAFIJA = 0;
		$AC1LINE = 1;
		$SUMALINEAFIXRATE = 0;
		$TOTALCXCLOC = 0;
		$TOTALCXCSYS = 0;
		$TOTALCXCLOCIVA = 0;
		$TOTALCXCSYSIVA = 0;
		$AgregarAsiento = true;
		$CANTUOMSALE = 0; //CANTIDAD DE LA EQUIVALENCIA SEGUN LA UNIDAD DE MEDIDA DEL ITEM PARA VENTA
		$DetalleIgtf = 0; // DETALLE DE DIVISAS APLICADAS IGTF
		$LineCXC = 0; // SE USA CUANDO SE DEBE HACER EL PAGO RECIBIDO DESPUES DE GUARDAR LA FACTURA ( FACTURA DE CONTADO ) LINEA DEL ASIENTO CONTABLE;
		$AcctLine = 0;// SE USA CUANDO SE DEBE HACER EL PAGO RECIBIDO DESPUES DE GUARDAR LA FACTURA ( FACTURA DE CONTADO ) CUENTA DE LA LINEA CXC;
		$TotalAcuRentencion = 0;
		$inArrayRetencion = array();
		$DetalleRetencion = new stdClass();
		$DetalleConsolidadoRetencion = [];
		$llaveRetencion = "";
		$posicionRetencion = 0;
		$DETALLE_GIFT = [];
		$VALUE_GIFT = 0;




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
				'mensaje' => 'No se encontro el detalle de la factura de ventas'
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
		$periodo = $this->generic->ValidatePeriod($Data['dvf_duedev'], $Data['dvf_docdate'], $Data['dvf_duedate'], 1);

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
		$DocNumVerificado = $this->documentnumbering->NumberDoc($Data['dvf_series'],$Data['dvf_docdate'],$Data['dvf_duedate']);
      
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
		// FIN PROCESO PARA OBTENER LA CARPETA PRINCIPAL DEL PROYECTO

		//PROCESO DE TASA
		$dataTasa = $this->tasa->Tasa($Data['dvf_currency'],$Data['dvf_docdate']);

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

		//VERIFICAR TASA FIJA DE DESCUENTO
		if (!isset($resMainFolder[0]['fixrate'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se ha establecido la tasa fija'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		} else {

			$monto = $resMainFolder[0]['fixrate'];
			$TasaFija = $monto;

			if (!is_numeric($monto) || $monto < 0) {
				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'No se ha establecido un valor valido para la tasa fija'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
		}
		//VERIFICAR TASA FIJA DE DESCUENTO

	
		$sqlInsert = "INSERT INTO dvfv(dvf_series, dvf_docnum, dvf_docdate, dvf_duedate, dvf_duedev, dvf_pricelist, dvf_cardcode,
						dvf_cardname, dvf_currency, dvf_contacid, dvf_slpcode, dvf_empid, dvf_comment, dvf_doctotal, dvf_baseamnt, dvf_taxtotal,
						dvf_discprofit, dvf_discount, dvf_createat, dvf_baseentry, dvf_basetype, dvf_doctype, dvf_idadd, dvf_adress, dvf_paytype,
						dvf_createby, dvf_correl,dvf_transport,dvf_sub_transport,dvf_ci,dvf_t_vehiculo,dvf_guia,dvf_placa,dvf_precinto,dvf_placav,
						dvf_modelv,dvf_driverv,dvf_driverid,dvf_igtf,dvf_taxigtf,dvf_igtfapplyed,dvf_igtfcode,business,branch,dvf_totalret,dvf_totalretiva)
						VALUES(:dvf_series, :dvf_docnum, :dvf_docdate, :dvf_duedate, :dvf_duedev, :dvf_pricelist, :dvf_cardcode, :dvf_cardname,
						:dvf_currency, :dvf_contacid, :dvf_slpcode, :dvf_empid, :dvf_comment, :dvf_doctotal, :dvf_baseamnt, :dvf_taxtotal, :dvf_discprofit, :dvf_discount,
						:dvf_createat, :dvf_baseentry, :dvf_basetype, :dvf_doctype, :dvf_idadd, :dvf_adress, :dvf_paytype, :dvf_createby,:dvf_correl,:dvf_transport,:dvf_sub_transport,:dvf_ci,:dvf_t_vehiculo,
						:dvf_guia,:dvf_placa,:dvf_precinto,:dvf_placav,:dvf_modelv,:dvf_driverv,:dvf_driverid,:dvf_igtf,:dvf_taxigtf,:dvf_igtfapplyed,
						:dvf_igtfcode,:business,:branch,:dvf_totalret,:dvf_totalretiva)";


		// Se Inicia la transaccion,
		// Todas las consultas de modificacion siguientes
		// aplicaran solo despues que se confirme la transaccion,
		// de lo contrario no se aplicaran los cambios y se devolvera
		// la base de datos a su estado original.

		$this->pedeo->trans_begin();

		try {


			$resInsert = $this->pedeo->insertRow($sqlInsert, array(
				':dvf_docnum' => $DocNumVerificado,
				':dvf_series' => is_numeric($Data['dvf_series']) ? $Data['dvf_series'] : 0,
				':dvf_docdate' => $this->validateDate($Data['dvf_docdate']) ? $Data['dvf_docdate'] : NULL,
				':dvf_duedate' => $this->validateDate($Data['dvf_duedate']) ? $Data['dvf_duedate'] : NULL,
				':dvf_duedev' => $this->validateDate($Data['dvf_duedev']) ? $Data['dvf_duedev'] : NULL,
				':dvf_pricelist' => is_numeric($Data['dvf_pricelist']) ? $Data['dvf_pricelist'] : 0,
				':dvf_cardcode' => isset($Data['dvf_cardcode']) ? $Data['dvf_cardcode'] : NULL,
				':dvf_cardname' => isset($Data['dvf_cardname']) ? $Data['dvf_cardname'] : NULL,
				':dvf_currency' => isset($Data['dvf_currency']) ? $Data['dvf_currency'] : NULL,
				':dvf_contacid' => isset($Data['dvf_contacid']) ? $Data['dvf_contacid'] : NULL,
				':dvf_slpcode' => is_numeric($Data['dvf_slpcode']) ? $Data['dvf_slpcode'] : 0,
				':dvf_empid' => is_numeric($Data['dvf_empid']) ? $Data['dvf_empid'] : 0,
				':dvf_comment' => isset($Data['dvf_comment']) ? $Data['dvf_comment'] : NULL,
				':dvf_doctotal' => is_numeric($Data['dvf_doctotal']) ? $Data['dvf_doctotal'] : 0,
				':dvf_baseamnt' => is_numeric($Data['dvf_baseamnt']) ? $Data['dvf_baseamnt'] : 0,
				':dvf_taxtotal' => is_numeric($Data['dvf_taxtotal']) ? $Data['dvf_taxtotal'] : 0,
				':dvf_discprofit' => is_numeric($Data['dvf_discprofit']) ? $Data['dvf_discprofit'] : 0,
				':dvf_discount' => is_numeric($Data['dvf_discount']) ? $Data['dvf_discount'] : 0,
				':dvf_createat' => $this->validateDate($Data['dvf_createat']) ? $Data['dvf_createat'] : NULL,
				':dvf_baseentry' => is_numeric($Data['dvf_baseentry']) ? $Data['dvf_baseentry'] : 0,
				':dvf_basetype' => is_numeric($Data['dvf_basetype']) ? $Data['dvf_basetype'] : 0,
				':dvf_doctype' => is_numeric($Data['dvf_doctype']) ? $Data['dvf_doctype'] : 0,
				':dvf_idadd' => isset($Data['dvf_idadd']) ? $Data['dvf_idadd'] : NULL,
				':dvf_adress' => isset($Data['dvf_adress']) ? $Data['dvf_adress'] : NULL,
				':dvf_paytype' => is_numeric($Data['dvf_paytype']) ? $Data['dvf_paytype'] : 0,
				':dvf_createby' => isset($Data['dvf_createby']) ? $Data['dvf_createby'] : NULL,
				':dvf_correl' => isset($Data['dvf_correl']) && is_numeric($Data['dvf_correl']) ? $Data['dvf_correl'] : 0,
				':dvf_transport' => isset($Data['dvf_transport']) ? $Data['dvf_transport'] : NULL,
				':dvf_sub_transport' => isset($Data['dvf_sub_transport']) ? $Data['dvf_sub_transport'] : NULL,
				':dvf_ci' => isset($Data['dvf_ci']) ? $Data['dvf_ci'] : NULL,
				':dvf_t_vehiculo' => isset($Data['dvf_t_vehiculo']) ? $Data['dvf_t_vehiculo'] : NULL,
				':dvf_guia' => isset($Data['dvf_guia']) ? $Data['dvf_guia'] : NULL,
				':dvf_placa' => isset($Data['dvf_placa']) ? $Data['dvf_placa'] : NULL,
				':dvf_precinto' => isset($Data['dvf_precinto']) ? $Data['dvf_precinto'] : NULL,
				':dvf_placav' => isset($Data['dvf_placav']) ? $Data['dvf_placav'] : NULL,
				':dvf_modelv' => isset($Data['dvf_modelv']) ? $Data['dvf_modelv'] : NULL,
				':dvf_driverv' => isset($Data['dvf_driverv']) ? $Data['dvf_driverv'] : NULL,
				':dvf_driverid'  => isset($Data['dvf_driverid']) ? $Data['dvf_driverid'] : NULL,
				':dvf_igtf'  =>  isset($Data['dvf_igtf']) ? $Data['dvf_igtf'] : NULL,
				':dvf_taxigtf' => isset($Data['dvf_taxigtf']) ? $Data['dvf_taxigtf'] : NULL,
				':dvf_igtfapplyed' => isset($Data['dvf_igtfapplyed']) ? $Data['dvf_igtfapplyed'] : NULL,
				':dvf_igtfcode' => isset($Data['dvf_igtfcode']) ? $Data['dvf_igtfcode'] : NULL,
				':business' => isset($Data['business']) ? $Data['business'] : NULL,
				':branch' => isset($Data['branch']) ? $Data['branch'] : NULL,
				':dvf_totalret' => isset($Data['dvf_totalret']) && is_numeric($Data['dvf_totalret']) ? $Data['dvf_totalret'] : 0,
				':dvf_totalretiva' => isset($Data['dvf_totalretiva']) && is_numeric($Data['dvf_totalretiva']) ? $Data['dvf_totalretiva'] : 0,
			));

			if (is_numeric($resInsert) && $resInsert > 0) {

				// Se actualiza la serie de la numeracion del documento

				$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																				 WHERE pgs_id = :pgs_id";
				$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
					':pgs_nextnum' => $DocNumVerificado,
					':pgs_id'      => $Data['dvf_series']
				));


				if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
				} else {
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'    => $resActualizarNumeracion,
						'mensaje'	=> 'No se pudo crear la factura de ventas'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}
				// Fin de la actualizacion de la numeracion del documento


				//Se agrega encabezado del asiento contable


				$sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user, business, branch)
																 VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user, :business, :branch)";


				$resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(
					':mac_doc_num' => 1,
					':mac_status' => 1,
					':mac_base_type' => is_numeric($Data['dvf_doctype']) ? $Data['dvf_doctype'] : 0,
					':mac_base_entry' => $resInsert,
					':mac_doc_date' => $this->validateDate($Data['dvf_docdate']) ? $Data['dvf_docdate'] : NULL,
					':mac_doc_duedate' => $this->validateDate($Data['dvf_duedate']) ? $Data['dvf_duedate'] : NULL,
					':mac_legal_date' => $this->validateDate($Data['dvf_docdate']) ? $Data['dvf_docdate'] : NULL,
					':mac_ref1' => is_numeric($Data['dvf_doctype']) ? $Data['dvf_doctype'] : 0,
					':mac_ref2' => "",
					':mac_ref3' => "",
					':mac_loc_total' => is_numeric($Data['dvf_doctotal']) ? $Data['dvf_doctotal'] : 0,
					':mac_fc_total' => is_numeric($Data['dvf_doctotal']) ? $Data['dvf_doctotal'] : 0,
					':mac_sys_total' => is_numeric($Data['dvf_doctotal']) ? $Data['dvf_doctotal'] : 0,
					':mac_trans_dode' => 1,
					':mac_beline_nume' => 1,
					':mac_vat_date' => $this->validateDate($Data['dvf_docdate']) ? $Data['dvf_docdate'] : NULL,
					':mac_serie' => 1,
					':mac_number' => 1,
					':mac_bammntsys' => is_numeric($Data['dvf_baseamnt']) ? $Data['dvf_baseamnt'] : 0,
					':mac_bammnt' => is_numeric($Data['dvf_baseamnt']) ? $Data['dvf_baseamnt'] : 0,
					':mac_wtsum' => 1,
					':mac_vatsum' => is_numeric($Data['dvf_taxtotal']) ? $Data['dvf_taxtotal'] : 0,
					':mac_comments' => isset($Data['dvf_comment']) ? $Data['dvf_comment'] : NULL,
					':mac_create_date' => $this->validateDate($Data['dvf_createat']) ? $Data['dvf_createat'] : NULL,
					':mac_made_usuer' => isset($Data['dvf_createby']) ? $Data['dvf_createby'] : NULL,
					':mac_update_date' => date("Y-m-d"),
					':mac_update_user' => isset($Data['dvf_createby']) ? $Data['dvf_createby'] : NULL,
					':business' => $Data['business'],
					':branch' 	=> $Data['branch']
				));


				if (is_numeric($resInsertAsiento) && $resInsertAsiento > 0) {
					$AgregarAsiento = false;
				} else {

					// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $resInsertAsiento,
						'mensaje'	=> 'No se pudo registrar la factura de ventas'
					);

					$this->response($respuesta);

					return;
				} // FINN
				

				//SE INSERTA EL ESTADO DEL DOCUMENTO

				$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

				$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(
					':bed_docentry' => $resInsert,
					':bed_doctype' => $Data['dvf_doctype'],
					':bed_status' => 1, //ESTADO CERRADO
					':bed_createby' => $Data['dvf_createby'],
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
						'mensaje'	=> 'No se pudo registrar la Factura de ventas'
					);


					$this->response($respuesta);

					return;
				}

				//FIN PROCESO ESTADO DEL DOCUMENTO


				//SE APLICA PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS
				if (isset($Data['dvf_baseentry']) && is_numeric($Data['dvf_baseentry']) && isset($Data['dvf_basetype']) && is_numeric($Data['dvf_basetype'])) {

					$sqlDocInicio = "SELECT bmd_tdi, bmd_ndi FROM tbmd WHERE  bmd_doctype = :bmd_doctype AND bmd_docentry = :bmd_docentry";
					$resDocInicio = $this->pedeo->queryTable($sqlDocInicio, array(
						':bmd_doctype' => $Data['dvf_basetype'],
						':bmd_docentry' => $Data['dvf_baseentry']
					));


					if (isset($resDocInicio[0])) {

						 $sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
														bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency,business)
														VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
														:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency,:business)";

						$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

							':bmd_doctype' => is_numeric($Data['dvf_doctype']) ? $Data['dvf_doctype'] : 0,
							':bmd_docentry' => $resInsert,
							':bmd_createat' => $this->validateDate($Data['dvf_createat']) ? $Data['dvf_createat'] : NULL,
							':bmd_doctypeo' => is_numeric($Data['dvf_basetype']) ? $Data['dvf_basetype'] : 0, //ORIGEN
							':bmd_docentryo' => is_numeric($Data['dvf_baseentry']) ? $Data['dvf_baseentry'] : 0,  //ORIGEN
							':bmd_tdi' => $resDocInicio[0]['bmd_tdi'], // DOCUMENTO INICIAL
							':bmd_ndi' => $resDocInicio[0]['bmd_ndi'], // DOCUMENTO INICIAL
							':bmd_docnum' => $DocNumVerificado,
							':bmd_doctotal' => is_numeric($Data['dvf_doctotal']) ? $Data['dvf_doctotal'] : 0,
							':bmd_cardcode' => isset($Data['dvf_cardcode']) ? $Data['dvf_cardcode'] : NULL,
							':bmd_cardtype' => 1,
					  		':bmd_currency' => isset($Data['dvf_currency'])?$Data['dvf_currency']:NULL,
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


							$this->response($respuesta);

							return;
						}
					} else {

						$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
						bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency,business)
						VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
						:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency,:business)";

						$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

							':bmd_doctype' => is_numeric($Data['dvf_doctype']) ? $Data['dvf_doctype'] : 0,
							':bmd_docentry' => $resInsert,
							':bmd_createat' => $this->validateDate($Data['dvf_createat']) ? $Data['dvf_createat'] : NULL,
							':bmd_doctypeo' => is_numeric($Data['dvf_basetype']) ? $Data['dvf_basetype'] : 0, //ORIGEN
							':bmd_docentryo' => is_numeric($Data['dvf_baseentry']) ? $Data['dvf_baseentry'] : 0,  //ORIGEN
							':bmd_tdi' => is_numeric($Data['dvf_doctype']) ? $Data['dvf_doctype'] : 0, // DOCUMENTO INICIAL
							':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
							':bmd_docnum' => $DocNumVerificado,
							':bmd_doctotal' => is_numeric($Data['dvf_doctotal']) ? $Data['dvf_doctotal'] : 0,
							':bmd_cardcode' => isset($Data['dvf_cardcode']) ? $Data['dvf_cardcode'] : NULL,
							':bmd_cardtype' => 1,
					  		':bmd_currency' => isset($Data['dvf_currency'])?$Data['dvf_currency']:NULL,
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


							$this->response($respuesta);

							return;
						}
					}
				} else {

					$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
					bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency,business)
					VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
					:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency,:business)";

					$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

						':bmd_doctype' => is_numeric($Data['dvf_doctype']) ? $Data['dvf_doctype'] : 0,
						':bmd_docentry' => $resInsert,
						':bmd_createat' => $this->validateDate($Data['dvf_createat']) ? $Data['dvf_createat'] : NULL,
						':bmd_doctypeo' => is_numeric($Data['dvf_basetype']) ? $Data['dvf_basetype'] : 0, //ORIGEN
						':bmd_docentryo' => is_numeric($Data['dvf_baseentry']) ? $Data['dvf_baseentry'] : 0,  //ORIGEN
						':bmd_tdi' => is_numeric($Data['dvf_doctype']) ? $Data['dvf_doctype'] : 0, // DOCUMENTO INICIAL
						':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
						':bmd_docnum' => $DocNumVerificado,
						':bmd_doctotal' => is_numeric($Data['dvf_doctotal']) ? $Data['dvf_doctotal'] : 0,
						':bmd_cardcode' => isset($Data['dvf_cardcode']) ? $Data['dvf_cardcode'] : NULL,
						':bmd_cardtype' => 1,
						':bmd_currency' => isset($Data['dvf_currency'])?$Data['dvf_currency']:NULL,
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


						$this->response($respuesta);

						return;
					}
				}
				//FIN PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS


				foreach ($ContenidoDetalle as $key => $detail) {


					$CANTUOMSALE = $this->generic->getUomSale($detail['fv1_itemcode']);

					if ($CANTUOMSALE == 0) {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' 		=> $detail['fv1_itemcode'],
							'mensaje'	=> 'No se encontro la equivalencia de la unidad de medida para el item: ' . $detail['fv1_itemcode']
						);

						$this->response($respuesta);

						return;
					}

					if ( isset($detail['fv1_linenum']) && is_numeric($detail['fv1_linenum']) && $detail['fv1_linenum'] > 0 ){

					}else{
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'    => true,
							'data' 	   => $detail['fv1_itemcode'],
							'mensaje'  => 'No se encontro el numero de linea para el item: ' . $detail['fv1_itemcode']
						);

						$this->response($respuesta);

						return;
					}

					$sqlInsertDetail = "INSERT INTO vfv1(fv1_docentry, fv1_itemcode, fv1_itemname, fv1_quantity, fv1_uom, fv1_whscode,
										fv1_price, fv1_vat, fv1_vatsum, fv1_discount, fv1_linetotal, fv1_costcode, fv1_ubusiness, fv1_project,
										fv1_acctcode, fv1_basetype, fv1_doctype, fv1_avprice, fv1_inventory, fv1_acciva, fv1_fixrate, fv1_codimp,fv1_ubication,
										fv1_linenum,fv1_baseline,ote_code,fv1_gift)VALUES(:fv1_docentry, :fv1_itemcode, :fv1_itemname, :fv1_quantity,:fv1_uom, :fv1_whscode,:fv1_price, :fv1_vat, 
										:fv1_vatsum, :fv1_discount, :fv1_linetotal, :fv1_costcode, :fv1_ubusiness, :fv1_project,:fv1_acctcode, :fv1_basetype, 
										:fv1_doctype, :fv1_avprice, :fv1_inventory, :fv1_acciva, :fv1_fixrate, :fv1_codimp,:fv1_ubication,:fv1_linenum,
										:fv1_baseline,:ote_code,:fv1_gift)";

					$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
						':fv1_docentry' => $resInsert,
						':fv1_itemcode' => isset($detail['fv1_itemcode']) ? $detail['fv1_itemcode'] : NULL,
						':fv1_itemname' => isset($detail['fv1_itemname']) ? $detail['fv1_itemname'] : NULL,
						':fv1_quantity' => is_numeric($detail['fv1_quantity']) ? $detail['fv1_quantity'] : 0,
						':fv1_uom' => isset($detail['fv1_uom']) ? $detail['fv1_uom'] : NULL,
						':fv1_whscode' => isset($detail['fv1_whscode']) ? $detail['fv1_whscode'] : NULL,
						':fv1_price' => is_numeric($detail['fv1_price']) ? $detail['fv1_price'] : 0,
						':fv1_vat' => is_numeric($detail['fv1_vat']) ? $detail['fv1_vat'] : 0,
						':fv1_vatsum' => is_numeric($detail['fv1_vatsum']) ? $detail['fv1_vatsum'] : 0,
						':fv1_discount' => is_numeric($detail['fv1_discount']) ? $detail['fv1_discount'] : 0,
						':fv1_linetotal' => is_numeric($detail['fv1_linetotal']) ? $detail['fv1_linetotal'] : 0,
						':fv1_costcode' => isset($detail['fv1_costcode']) ? $detail['fv1_costcode'] : NULL,
						':fv1_ubusiness' => isset($detail['fv1_ubusiness']) ? $detail['fv1_ubusiness'] : NULL,
						':fv1_project' => isset($detail['fv1_project']) ? $detail['fv1_project'] : NULL,
						':fv1_acctcode' => is_numeric($detail['fv1_acctcode']) ? $detail['fv1_acctcode'] : 0,
						':fv1_basetype' => is_numeric($detail['fv1_basetype']) ? $detail['fv1_basetype'] : 0,
						':fv1_doctype' => is_numeric($detail['fv1_doctype']) ? $detail['fv1_doctype'] : 0,
						':fv1_avprice' => is_numeric($detail['fv1_avprice']) ? $detail['fv1_avprice'] : 0,
						':fv1_inventory' => is_numeric($detail['fv1_inventory']) ? $detail['fv1_inventory'] : NULL,
						':fv1_acciva'  => is_numeric($detail['fv1_cuentaIva']) ? $detail['fv1_cuentaIva'] : 0,
						':fv1_fixrate' => is_numeric($detail['fv1_fixrate']) ? $detail['fv1_fixrate'] : 0,
						':fv1_codimp' => isset($detail['fv1_codimp']) ? $detail['fv1_codimp'] : 0,
						':fv1_ubication' => isset($detail['fv1_ubication']) ? $detail['fv1_ubication'] : NULL,
						':fv1_linenum' => isset($detail['fv1_linenum']) && is_numeric($detail['fv1_linenum']) ? $detail['fv1_linenum'] : 0,
						':fv1_baseline' => isset($detail['fv1_baseline']) && is_numeric($detail['fv1_baseline']) ? $detail['fv1_baseline'] : 0,
						':ote_code' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
						':fv1_gift' => isset($detail['fv1_gift']) && is_numeric($detail['fv1_gift']) ? $detail['fv1_gift'] : 0
					));

					if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
						// Se verifica que el detalle no de error insertando //
						//validar que lo facturado no se mayor a lo entregado menos devuelto
						//VALIDAR SI LOS ITEMS SON IGUALES A LOS DEL DOCUMENTO DE ORIGEN
						if($Data['dvf_basetype'] == 2){
							$sqlDev = "SELECT
												t1.ov1_itemcode,
												t1.ov1_quantity  cantidad
										from dvov t0
										left join vov1 t1 on t0.vov_docentry = t1.ov1_docentry
										where t0.vov_docentry = :vov_docentry and t0.vov_doctype = :vov_doctype 
										and t1.ov1_itemcode = :ov1_itemcode
										group by t1.ov1_itemcode";
							$resSqlDev = $this->pedeo->queryTable($sqlDev, array(
								':vov_docentry' => $Data['dvf_baseentry'],
								':vov_doctype' => $Data['dvf_basetype'],
								':ov1_itemcode' => $detail['fv1_itemcode']
							));

							if (isset($resSqlDev[0]['cantidad']) && ($detail['fv1_quantity']) > $resSqlDev[0]['cantidad']) {
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $resSqlDev,
									'mensaje'	=> 'La cantidad a facturar no puede ser mayor a la del pedido'
								);
								$this->response($respuesta);

								return;
							}
							//OBTENER NUMERO DOCUMENTO ORIGEN
							$DOC = "SELECT vov_docnum FROM dvov WHERE vov_doctype = :vov_doctype AND vov_docentry = :vov_docentry";
							$RESULT_DOC = $this->pedeo->queryTable($DOC,array(':vov_docentry' =>$Data['dvf_baseentry'],':vov_doctype' => $Data['dvf_basetype']));
							foreach ($ContenidoDetalle as $key => $value) {
								# code...
								$sql = "SELECT dvov.vov_docnum,vov1.ov1_itemcode FROM dvov INNER JOIN vov1 ON dvov.vov_docentry = vov1.ov1_docentry 
								WHERE dvov.vov_docentry = :vov_docentry AND dvov.vov_doctype = :vov_doctype AND vov1.ov1_itemcode = :ov1_itemcode";
								$resSql = $this->pedeo->queryTable($sql,array(
									':vov_docentry' =>$Data['dvf_baseentry'],
									':vov_doctype' => $Data['dvf_basetype'],
									':ov1_itemcode' => $value['fv1_itemcode']
								));
								
									if(isset($resSql[0])){
									
									}else {
										$this->pedeo->trans_rollback();

										$respuesta = array(
											'error'   => true,
											'data' => $value['fv1_itemcode'],
											'mensaje'	=> 'El Item '.$value['fv1_itemcode'].' no existe en el documento origen (Pedido #'.$RESULT_DOC[0]['vov_docnum'].')'
										);

										$this->response($respuesta);

										return;
									}
								}

						}else if ($Data['dvf_basetype'] == 3) {
							$sqlDev = "SELECT
											t1.em1_itemcode,
											t1.em1_quantity - COALESCE(sum(t3.dv1_quantity),0) cantidad
									from dvem t0
									left join vem1 t1 on t0.vem_docentry = t1.em1_docentry
									left join dvdv t2 on t0.vem_docentry = t2.vdv_baseentry and t0.vem_doctype = t2.vdv_basetype
									left join vdv1 t3 on t2.vdv_docentry = t3.dv1_docentry and t1.em1_itemcode = t3.dv1_itemcode
									where t0.vem_docentry = :vem_docentry and t0.vem_doctype = :vem_doctype and t1.em1_itemcode = :em1_itemcode
									group by t1.em1_itemcode,t1.em1_quantity";
							$resSqlDev = $this->pedeo->queryTable($sqlDev, array(
								':vem_docentry' => $Data['dvf_baseentry'],
								':vem_doctype' => $Data['dvf_basetype'],
								':em1_itemcode' => $detail['fv1_itemcode']
							));

							if (isset($resSqlDev[0]['cantidad']) && ($detail['fv1_quantity']) > $resSqlDev[0]['cantidad']) {
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $resSqlDev,
									'mensaje'	=> 'La cantidad a facturar no puede ser mayor a la entregada menos la devuelta'
								);
								$this->response($respuesta);

								return;
							}

							//OBTENER NUMERO DOCUMENTO ORIGEN
							$DOC = "SELECT vem_docnum FROM dvem WHERE vem_doctype = :vem_doctype AND vem_docentry = :vem_docentry";
							$RESULT_DOC = $this->pedeo->queryTable($DOC,array(':vem_docentry' =>$Data['dvf_baseentry'],':vem_doctype' => $Data['dvf_basetype']));
							foreach ($ContenidoDetalle as $key => $value) {
								# code...
								$sql = "SELECT vem1.em1_itemcode FROM dvem INNER JOIN vem1 ON dvem.vem_docentry = vem1.em1_docentry 
								WHERE dvem.vem_docentry = :vem_docentry AND dvem.vem_doctype = :vem_doctype AND vem1.em1_itemcode = :em1_itemcode";
								$resSql = $this->pedeo->queryTable($sql,array(
									':vem_docentry' =>$Data['dvf_baseentry'],
									':vem_doctype' => $Data['dvf_basetype'],
									':em1_itemcode' => $value['fv1_itemcode']
								));
								
									if(isset($resSql[0])){
									
									}else {
										$this->pedeo->trans_rollback();

										$respuesta = array(
											'error'   => true,
											'data' => $value['fv1_itemcode'],
											'mensaje'	=> 'El Item '.$value['fv1_itemcode'].' no existe en el documento origen (Entrega #'.$RESULT_DOC[0]['vem_docnum'].')'
										);

										$this->response($respuesta);

										return;
									}
								}
						}
					} else {

						// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
						// se retorna el error y se detiene la ejecucion del codigo restante.
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' => $resInsert,
							'mensaje'	=> 'No se pudo registrar la factura de ventas'
						);

						$this->response($respuesta);

						return;
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
										':crt_basetype'  => $Data['dvf_doctype'],
										':crt_typert'    => $value['crt_typert'],
										':crt_basert'    => $value['crt_basert'],
										':crt_profitrt'  => $value['crt_profitrt'],
										':crt_totalrt'   => $value['crt_totalrt'],
										':crt_base'		 => $value['crt_base'],
										':crt_type'		 => $value['crt_type'],
										':crt_linenum'   => $detail['fv1_linenum'],
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



					if (!isset($detail['fv1_fixrate'])) {
						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' => 'no se encontro el descuento aplicado'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
					}

					$SUMALINEAFIXRATE = ($SUMALINEAFIXRATE + is_numeric($detail['fv1_fixrate']));

					// SE VERIFICA SI EL ARTICULO ESTA MARCADO PARA MANEJARSE EN INVENTARIO
					$sqlItemINV = "SELECT dma_item_inv FROM dmar WHERE dma_item_code = :dma_item_code AND dma_item_inv = :dma_item_inv";
					$resItemINV = $this->pedeo->queryTable($sqlItemINV, array(

						':dma_item_code' => $detail['fv1_itemcode'],
						':dma_item_inv'  => 1
					));
					//


					if (isset($resItemINV[0])) {

						// CONSULTA PARA VERIFICAR SI EL ALMACEN MANEJA UBICACION

						$sqlubicacion = "SELECT * FROM dmws WHERE dws_ubication = :dws_ubication AND dws_code = :dws_code AND business = :business";
						$resubicacion = $this->pedeo->queryTable($sqlubicacion, array(
							':dws_ubication' => 1,
							':dws_code' => $detail['fv1_whscode'],
							':business' => $Data['business']
						));


						if ( isset($resubicacion[0]) ){
							$ManejaUbicacion = 1;
						}else{
							$ManejaUbicacion = 0;
						}

						// SE VERIFICA SI EL ARTICULO MANEJA LOTE

						$sqlLote = "SELECT dma_lotes_code FROM dmar WHERE dma_item_code = :dma_item_code AND dma_lotes_code = :dma_lotes_code";
						$resLote = $this->pedeo->queryTable($sqlLote, array(

							':dma_item_code' => $detail['fv1_itemcode'],
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

					// FIN PROCESO ITEM MANEJA INVENTARIO


					if ($ManejaInvetario == 1) {


						// se verifica de donde viene  el documento
	
						if ( $Data['dvf_basetype'] != 3 ) {


							//SE VERIFICA SI EL ARTICULO MANEJA SERIAL
							$sqlItemSerial = "SELECT dma_series_code FROM dmar WHERE  dma_item_code = :dma_item_code AND dma_series_code = :dma_series_code";
							$resItemSerial = $this->pedeo->queryTable($sqlItemSerial, array(

								':dma_item_code' => $detail['fv1_itemcode'],
								':dma_series_code'  => 1
							));

							if (isset($resItemSerial[0])) {
								$ManejaSerial = 1;

								$AddSerial = $this->generic->addSerial($detail['serials'], $detail['fv1_itemcode'], $Data['dvf_doctype'], $resInsert, $DocNumVerificado, $Data['dvf_docdate'], 2, $Data['dvf_comment'], $detail['fv1_whscode'], $detail['fv1_quantity'], $Data['dvf_createby'], $resInsertDetail, $Data['business']);

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

							// se busca el costo del item en el momento de la creacion del documento de venta
							// para almacenar en el movimiento de inventario

							// SI EL ALMACEN MANEJA UBICACION

							$sqlCostoMomentoRegistro = "";
							$resCostoMomentoRegistro = [];

							if ( $ManejaUbicacion == 1 ) {
								if( $ManejaLote == 1 ){
									$sqlCostoMomentoRegistro =  "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_ubication = :bdi_ubication  AND bdi_lote = :bdi_lote AND business = :business";
									$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
										':bdi_whscode'   => $detail['fv1_whscode'], 
										':bdi_itemcode'  => $detail['fv1_itemcode'],
										':bdi_ubication' => $detail['fv1_ubication'],
									    ':bdi_lote' 	 => $detail['ote_code'],
										':business' 	 => $Data['business']
									));
								}else{
									$sqlCostoMomentoRegistro =  "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_ubication = :bdi_ubication AND business = :business";
									$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
										':bdi_whscode'   => $detail['fv1_whscode'], 
										':bdi_itemcode'  => $detail['fv1_itemcode'],
										':bdi_ubication' => $detail['fv1_ubication'],
										':business' 	 => $Data['business']
									));
								}

							}else{
								if( $ManejaLote == 1 ){
									$sqlCostoMomentoRegistro =  "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_lote = :bdi_lote AND business = :business";

									$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
										':bdi_whscode'  => $detail['fv1_whscode'], 
										':bdi_itemcode' => $detail['fv1_itemcode'],
										':bdi_lote' 	=> $detail['ote_code'],
										':business' 	 => $Data['business']
									));	
								}else{
									$sqlCostoMomentoRegistro =  "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND business = :business";

									$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
										':bdi_whscode'  => $detail['fv1_whscode'], 
										':bdi_itemcode' => $detail['fv1_itemcode'],
										':business' 	 => $Data['business']
									));
		
								}

							}
						
							
							if (isset($resCostoMomentoRegistro[0])) {

								//VALIDANDO CANTIDAD DE ARTICULOS
								if($Data['dvf_doctype'] == 34){

								}else{

									$CANT_ARTICULOEX = $resCostoMomentoRegistro[0]['bdi_quantity'];
									
									$CANT_ARTICULOLN = is_numeric($detail['fv1_quantity']) ? ($detail['fv1_quantity'] * $CANTUOMSALE) : 0;

									if (($CANT_ARTICULOEX - $CANT_ARTICULOLN) < 0) {

										$this->pedeo->trans_rollback();

										$respuesta = array(
											'error'   => true,
											'data' => [],
											'mensaje'	=> 'no puede crear el documento porque el articulo ' . $detail['fv1_itemcode'] . ' recae en inventario negativo (' . ($CANT_ARTICULOEX - $CANT_ARTICULOLN) . ')'
										);

										$this->response($respuesta);

										return;
									}
								}
								

								//VALIDANDO CANTIDAD DE ARTICULOS



								//Se aplica el movimiento de inventario
								$sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode,bmi_quantity,bmi_whscode,bmi_createat,bmi_createby,bmy_doctype,bmy_baseentry,
								bmi_cost,bmi_currequantity,bmi_basenum,bmi_docdate,bmi_duedate,bmi_duedev,bmi_comment,bmi_ubication,bmi_lote,business)
								VALUES (:bmi_itemcode,:bmi_quantity, :bmi_whscode,:bmi_createat,:bmi_createby,:bmy_doctype,:bmy_baseentry,:bmi_cost,:bmi_currequantity,
								:bmi_basenum,:bmi_docdate,:bmi_duedate,:bmi_duedev,:bmi_comment,:bmi_ubication,:bmi_lote,:business)";

								$sqlInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(

									':bmi_itemcode' => isset($detail['fv1_itemcode']) ? $detail['fv1_itemcode'] : NULL,
									':bmi_quantity' => is_numeric($detail['fv1_quantity']) ? (($detail['fv1_quantity'] * $CANTUOMSALE) * $Data['invtype']) : 0,
									':bmi_whscode'  => isset($detail['fv1_whscode']) ? $detail['fv1_whscode'] : NULL,
									':bmi_createat' => $this->validateDate($Data['dvf_createat']) ? $Data['dvf_createat'] : NULL,
									':bmi_createby' => isset($Data['dvf_createby']) ? $Data['dvf_createby'] : NULL,
									':bmy_doctype'  => is_numeric($Data['dvf_doctype']) ? $Data['dvf_doctype'] : 0,
									':bmy_baseentry' => $resInsert,
									':bmi_cost'      => $resCostoMomentoRegistro[0]['bdi_avgprice'],
									':bmi_currequantity' 	=> $resCostoMomentoRegistro[0]['bdi_quantity'],
									':bmi_basenum'			=> $DocNumVerificado,
									':bmi_docdate' => $this->validateDate($Data['dvf_docdate']) ? $Data['dvf_docdate'] : NULL,
									':bmi_duedate' => $this->validateDate($Data['dvf_duedate']) ? $Data['dvf_duedate'] : NULL,
									':bmi_duedev'  => $this->validateDate($Data['dvf_duedev']) ? $Data['dvf_duedev'] : NULL,
									':bmi_comment' => isset($Data['dvf_comment']) ? $Data['dvf_comment'] : NULL,
									':bmi_ubication' => isset($detail['fv1_ubication']) ? $detail['fv1_ubication'] : NULL,
									':bmi_lote' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
									':business' => isset($Data['business']) ? $Data['business'] : NULL

								));

								if (is_numeric($sqlInserMovimiento) && $sqlInserMovimiento > 0) {
									// Se verifica que el detalle no de error insertando //
								} else {

									// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
									// se retorna el error y se detiene la ejecucion del codigo restante.
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data' => $sqlInserMovimiento,
										'mensaje'	=> 'No se pudo registrar la factura de ventas'
									);

									$this->response($respuesta);

									return;
								}
							} else {

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $resCostoMomentoRegistro,
									'mensaje'	=> 'No se pudo registrar la factura de ventas, no se encontro el costo del articulo'
								);

								$this->response($respuesta);

								return;
							}

							//FIN aplicacion de movimiento de inventario


							//Se Aplica el movimiento en stock ***************
							// Buscando item en el stock
							$sqlCostoCantidad = "";
							$resCostoCantidad = [];

							// SI EL ALMACEN MANEJA UBICACION

							if ( $ManejaUbicacion == 1 ){

								if ( $ManejaLote == 1 ) {
									$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
									FROM tbdi
									WHERE bdi_itemcode = :bdi_itemcode
									AND bdi_whscode = :bdi_whscode
									AND bdi_ubication = :bdi_ubication
									AND bdi_lote = :bdi_lote
									AND business = :business";

									$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(
										':bdi_itemcode'  => $detail['fv1_itemcode'],
										':bdi_whscode'   => $detail['fv1_whscode'],
										':bdi_ubication' => $detail['fv1_ubication'],
										':bdi_lote' => $detail['ote_code'],
										':business' => $Data['business']
									));
								}else{
									$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
									FROM tbdi
									WHERE bdi_itemcode = :bdi_itemcode
									AND bdi_whscode = :bdi_whscode
									AND bdi_ubication = :bdi_ubication
									AND business = :business";


									$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(
										':bdi_itemcode'  => $detail['fv1_itemcode'],
										':bdi_whscode'   => $detail['fv1_whscode'],
										':bdi_ubication' => $detail['fv1_ubication'],
										':business' => $Data['business']
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
										':bdi_itemcode' => $detail['fv1_itemcode'],
										':bdi_whscode'  => $detail['fv1_whscode'],
										':bdi_lote' => $detail['ote_code'],
										':business' => $Data['business']
									));
								}else{
									$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
									FROM tbdi
									WHERE bdi_itemcode = :bdi_itemcode
									AND bdi_whscode = :bdi_whscode
									AND business = :business";

									$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(
										':bdi_itemcode' => $detail['fv1_itemcode'],
										':bdi_whscode'  => $detail['fv1_whscode'],
										':business' => $Data['business']
									));
								}

							}
							

						

							if (isset($resCostoCantidad[0])) {

								if ($resCostoCantidad[0]['bdi_quantity'] > 0) {

									$CantidadActual = $resCostoCantidad[0]['bdi_quantity'];
									$CantidadNueva = ($detail['fv1_quantity'] * $CANTUOMSALE);


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
											'mensaje'	=> 'No se pudo crear la factura de Ventas'
										);


										$this->response($respuesta);

										return;
									}
								} else {

									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'    => $resCostoCantidad,
										'mensaje' => 'No hay existencia para el item: ' . $detail['fv1_itemcode']
									);


									$this->response($respuesta);

									return;
								}
							} else {

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' 		=> $resCostoCantidad,
									'mensaje'	=> 'El item no existe en el stock ' . $detail['fv1_itemcode']
								);

								$this->response($respuesta);

								return;
							}

							//FIN de  Aplicacion del movimiento en stock

						} // EN CASO CONTRARIO NO SE MUEVE INVENTARIO

					}


					//LLENANDO DETALLE ASIENTO CONTABLES
					$DetalleAsientoIngreso = new stdClass();
					$DetalleAsientoIva = new stdClass();
					$DetalleCostoInventario = new stdClass();
					$DetalleCostoCosto = new stdClass();


					$DetalleAsientoIngreso->ac1_account = is_numeric($detail['fv1_acctcode']) ? $detail['fv1_acctcode'] : 0;
					$DetalleAsientoIngreso->ac1_prc_code = isset($detail['fv1_costcode']) ? $detail['fv1_costcode'] : NULL;
					$DetalleAsientoIngreso->ac1_uncode = isset($detail['fv1_ubusiness']) ? $detail['fv1_ubusiness'] : NULL;
					$DetalleAsientoIngreso->ac1_prj_code = isset($detail['fv1_project']) ? $detail['fv1_project'] : NULL;
					$DetalleAsientoIngreso->fv1_linetotal = is_numeric($detail['fv1_linetotal']) ? $detail['fv1_linetotal'] : 0;
					$DetalleAsientoIngreso->fv1_vat = is_numeric($detail['fv1_vat']) ? $detail['fv1_vat'] : 0;
					$DetalleAsientoIngreso->fv1_vatsum = is_numeric($detail['fv1_vatsum']) ? $detail['fv1_vatsum'] : 0;
					$DetalleAsientoIngreso->fv1_price = is_numeric($detail['fv1_price']) ? $detail['fv1_price'] : 0;
					$DetalleAsientoIngreso->fv1_itemcode = isset($detail['fv1_itemcode']) ? $detail['fv1_itemcode'] : NULL;
					$DetalleAsientoIngreso->fv1_quantity = is_numeric($detail['fv1_quantity']) ? $detail['fv1_quantity'] : 0;
					$DetalleAsientoIngreso->em1_whscode = isset($detail['fv1_whscode']) ? $detail['fv1_whscode'] : NULL;
					$DetalleAsientoIngreso->fv1_fixrate = is_numeric($detail['fv1_fixrate']) ? $detail['fv1_fixrate'] : 0;
					$DetalleAsientoIngreso->gift = isset($detail['fv1_gift']) && is_numeric($detail['fv1_gift']) ? $detail['fv1_gift'] : 0;

					$DetalleAsientoIva->ac1_account = is_numeric($detail['fv1_acctcode']) ? $detail['fv1_acctcode'] : 0;
					$DetalleAsientoIva->ac1_prc_code = isset($detail['fv1_costcode']) ? $detail['fv1_costcode'] : NULL;
					$DetalleAsientoIva->ac1_uncode = isset($detail['fv1_ubusiness']) ? $detail['fv1_ubusiness'] : NULL;
					$DetalleAsientoIva->ac1_prj_code = isset($detail['fv1_project']) ? $detail['fv1_project'] : NULL;
					$DetalleAsientoIva->fv1_linetotal = is_numeric($detail['fv1_linetotal']) ? $detail['fv1_linetotal'] : 0;
					$DetalleAsientoIva->fv1_vat = is_numeric($detail['fv1_vat']) ? $detail['fv1_vat'] : 0;
					$DetalleAsientoIva->fv1_vatsum = is_numeric($detail['fv1_vatsum']) ? $detail['fv1_vatsum'] : 0;
					$DetalleAsientoIva->fv1_price = is_numeric($detail['fv1_price']) ? $detail['fv1_price'] : 0;
					$DetalleAsientoIva->fv1_itemcode = isset($detail['fv1_itemcode']) ? $detail['fv1_itemcode'] : NULL;
					$DetalleAsientoIva->fv1_quantity = is_numeric($detail['fv1_quantity']) ? $detail['fv1_quantity'] : 0;
					$DetalleAsientoIva->fv1_cuentaIva = is_numeric($detail['fv1_cuentaIva']) ? $detail['fv1_cuentaIva'] : NULL;
					$DetalleAsientoIva->em1_whscode = isset($detail['fv1_whscode']) ? $detail['fv1_whscode'] : NULL;
					$DetalleAsientoIva->fv1_fixrate = is_numeric($detail['fv1_fixrate']) ? $detail['fv1_fixrate'] : 0;
					$DetalleAsientoIva->codimp = isset($detail['fv1_codimp']) ? $detail['fv1_codimp'] : NULL;
					$DetalleAsientoIva->gift = isset($detail['fv1_gift']) && is_numeric($detail['fv1_gift']) ? $detail['fv1_gift'] : 0;

					// VALIDANDO ITEM INVENTARIABLE
					if ($ManejaInvetario == 1) {
						$DetalleCostoInventario->ac1_account = is_numeric($detail['fv1_acctcode']) ? $detail['fv1_acctcode'] : 0;
						$DetalleCostoInventario->ac1_prc_code = isset($detail['fv1_costcode']) ? $detail['fv1_costcode'] : NULL;
						$DetalleCostoInventario->ac1_uncode = isset($detail['fv1_ubusiness']) ? $detail['fv1_ubusiness'] : NULL;
						$DetalleCostoInventario->ac1_prj_code = isset($detail['fv1_project']) ? $detail['fv1_project'] : NULL;
						$DetalleCostoInventario->fv1_linetotal = is_numeric($detail['fv1_linetotal']) ? $detail['fv1_linetotal'] : 0;
						$DetalleCostoInventario->fv1_vat = is_numeric($detail['fv1_vat']) ? $detail['fv1_vat'] : 0;
						$DetalleCostoInventario->fv1_vatsum = is_numeric($detail['fv1_vatsum']) ? $detail['fv1_vatsum'] : 0;
						$DetalleCostoInventario->fv1_price = is_numeric($detail['fv1_price']) ? $detail['fv1_price'] : 0;
						$DetalleCostoInventario->fv1_itemcode = isset($detail['fv1_itemcode']) ? $detail['fv1_itemcode'] : NULL;
						$DetalleCostoInventario->fv1_quantity = is_numeric($detail['fv1_quantity']) ? $detail['fv1_quantity'] : 0;
						$DetalleCostoInventario->em1_whscode = isset($detail['fv1_whscode']) ? $detail['fv1_whscode'] : NULL;
						$DetalleCostoInventario->fv1_fixrate = is_numeric($detail['fv1_fixrate']) ? $detail['fv1_fixrate'] : 0;


						$DetalleCostoCosto->ac1_account = is_numeric($detail['fv1_acctcode']) ? $detail['fv1_acctcode'] : 0;
						$DetalleCostoCosto->ac1_prc_code = isset($detail['fv1_costcode']) ? $detail['fv1_costcode'] : NULL;
						$DetalleCostoCosto->ac1_uncode = isset($detail['fv1_ubusiness']) ? $detail['fv1_ubusiness'] : NULL;
						$DetalleCostoCosto->ac1_prj_code = isset($detail['fv1_project']) ? $detail['fv1_project'] : NULL;
						$DetalleCostoCosto->fv1_linetotal = is_numeric($detail['fv1_linetotal']) ? $detail['fv1_linetotal'] : 0;
						$DetalleCostoCosto->fv1_vat = is_numeric($detail['fv1_vat']) ? $detail['fv1_vat'] : 0;
						$DetalleCostoCosto->fv1_vatsum = is_numeric($detail['fv1_vatsum']) ? $detail['fv1_vatsum'] : 0;
						$DetalleCostoCosto->fv1_price = is_numeric($detail['fv1_price']) ? $detail['fv1_price'] : 0;
						$DetalleCostoCosto->fv1_itemcode = isset($detail['fv1_itemcode']) ? $detail['fv1_itemcode'] : NULL;
						$DetalleCostoCosto->fv1_quantity = is_numeric($detail['fv1_quantity']) ? $detail['fv1_quantity'] : 0;
						$DetalleCostoCosto->em1_whscode = isset($detail['fv1_whscode']) ? $detail['fv1_whscode'] : NULL;
						$DetalleCostoCosto->fv1_fixrate = is_numeric($detail['fv1_fixrate']) ? $detail['fv1_fixrate'] : 0;
						$DetalleCostoCosto->gift = isset($detail['fv1_gift']) && is_numeric($detail['fv1_gift']) ? $detail['fv1_gift'] : 0;
					}
					//ITEM INVENTARIABLE



					$codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);

					$DetalleAsientoIngreso->codigoCuenta = $codigoCuenta;
					$DetalleAsientoIva->codigoCuenta = $codigoCuenta;

					//ITEM INVENTARIABLE
					if ($ManejaInvetario == 1) {

						$DetalleCostoInventario->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);
						$DetalleCostoInventario->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);

						$llaveCostoInventario = $DetalleCostoInventario->ac1_account;
						$llaveCostoCosto = $DetalleCostoCosto->ac1_account;
					}	//ITEM INVENTARIABLE


					$llave = $DetalleAsientoIngreso->ac1_uncode . $DetalleAsientoIngreso->ac1_prc_code . $DetalleAsientoIngreso->ac1_prj_code . $DetalleAsientoIngreso->ac1_account;
					$llaveIva = $DetalleAsientoIva->fv1_vat;




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
				// FIN INSERSION DETALLE FACTURA


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

							$granTotalIngreso = ($granTotalIngreso + $value->fv1_linetotal);
							$granTotalTasaFija = ($granTotalTasaFija + ($value->fv1_fixrate * $value->fv1_quantity));
							$codigoCuentaIngreso = $value->codigoCuenta;
							$prc = $value->ac1_prc_code;
							$unidad = $value->ac1_uncode;
							$proyecto = $value->ac1_prj_code;
							$cuenta = $value->ac1_account;

						} else {

							// $VALUE_GIFT = $VALUE_GIFT + ($VALUE_GIFT + $value->fv1_linetotal);

						}

					}


					$debito = 0;
					$credito = 0;
					$MontoSysDB = 0;
					$MontoSysCR = 0;
					$granTotalIngresoOriginal = $granTotalIngreso;



					if (trim($Data['dvf_currency']) != $MONEDALOCAL) {
						$granTotalIngreso = ($granTotalIngreso * $TasaDocLoc);
					}


					$ti = $granTotalIngreso;

					switch ($codigoCuentaIngreso) {
						case 1:
							$credito = $granTotalIngreso;

							if (trim($Data['dvf_currency']) != $MONEDASYS) {
								$ti = ($ti + $granTotalTasaFija);
								$MontoSysCR = ($ti / $TasaLocSys);
							} else {
								$MontoSysCR = $granTotalIngresoOriginal;
							}
							break;

						case 2:
							$credito = $granTotalIngreso;

							if (trim($Data['dvf_currency']) != $MONEDASYS) {
								$ti = ($ti + $granTotalTasaFija);
								$MontoSysCR = ($ti / $TasaLocSys);
							} else {
								$MontoSysCR = $granTotalIngresoOriginal;
							}
							break;

						case 3:
							$credito = $granTotalIngreso;

							if (trim($Data['dvf_currency']) != $MONEDASYS) {
								$ti = ($ti + $granTotalTasaFija);
								$MontoSysCR = ($ti / $TasaLocSys);
							} else {
								$MontoSysCR = $granTotalIngresoOriginal;
							}
							break;

						case 4:
							$credito = $granTotalIngreso;

							if (trim($Data['dvf_currency']) != $MONEDASYS) {
								$ti = ($ti + $granTotalTasaFija);
								$MontoSysCR = ($ti / $TasaLocSys);
							} else {
								$MontoSysCR = $granTotalIngresoOriginal;
							}
							break;

						case 5:
							$credito = $granTotalIngreso;

							if (trim($Data['dvf_currency']) != $MONEDASYS) {
								$ti = ($ti + $granTotalTasaFija);
								$MontoSysCR = ($ti / $TasaLocSys);
							} else {
								$MontoSysCR = $granTotalIngresoOriginal;
							}
							break;

						case 6:
							$credito = $granTotalIngreso;

							if (trim($Data['dvf_currency']) != $MONEDASYS) {
								$ti = ($ti + $granTotalTasaFija);
								$MontoSysCR = ($ti / $TasaLocSys);
							} else {
								$MontoSysCR = $granTotalIngresoOriginal;
							}
							break;

						case 7:
							$credito = $granTotalIngreso;

							if (trim($Data['dvf_currency']) != $MONEDASYS) {
								$ti = ($ti + $granTotalTasaFija);
								$MontoSysCR = ($ti / $TasaLocSys);
							} else {
								$MontoSysCR = $granTotalIngresoOriginal;
							}
							break;

						case 9:
							$credito = $granTotalIngreso;

							if (trim($Data['dvf_currency']) != $MONEDASYS) {
								$ti = ($ti + $granTotalTasaFija);
								$MontoSysCR = ($ti / $TasaLocSys);
							} else {
								$MontoSysCR = $granTotalIngresoOriginal;
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
							':ac1_doc_date' => $this->validateDate($Data['dvf_docdate']) ? $Data['dvf_docdate'] : NULL,
							':ac1_doc_duedate' => $this->validateDate($Data['dvf_duedate']) ? $Data['dvf_duedate'] : NULL,
							':ac1_debit_import' => 0,
							':ac1_credit_import' => 0,
							':ac1_debit_importsys' => 0,
							':ac1_credit_importsys' => 0,
							':ac1_font_key' => $resInsert,
							':ac1_font_line' => 1,
							':ac1_font_type' => is_numeric($Data['dvf_doctype']) ? $Data['dvf_doctype'] : 0,
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
							':ac1_made_user' => isset($Data['dvf_createby']) ? $Data['dvf_createby'] : NULL,
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
							':ac1_legal_num' => isset($Data['dvf_cardcode']) ? $Data['dvf_cardcode'] : NULL,
							':ac1_codref' => 1,
							':ac1_line'   => 	$AC1LINE,
							':ac1_base_tax' => 0,
							':business' => $Data['business'],
							':branch' 	=> $Data['branch'],
							':ac1_codret' => 0
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

						$granTotalIva = round($granTotalIva + $value->fv1_vatsum, $DECI_MALES);

						$v1 = ($value->fv1_linetotal + ($value->fv1_quantity * $value->fv1_fixrate));
						$granTotalIva2 = round($granTotalIva2 + ($v1 * ($value->fv1_vat / 100)), $DECI_MALES);

						$LineTotal = ($LineTotal + $value->fv1_linetotal);
						$CodigoImp = $value->codimp;
						$Vat = $value->fv1_vat;

						if ( $value->gift == 1) {

							array_push($DETALLE_GIFT, array( "monto" => round($value->fv1_vatsum, $DECI_MALES), "item" => $value->fv1_itemcode, "proyecto" =>$value->ac1_prj_code , "centrocosto" =>$value->ac1_prc_code, "unidadnegocio" => $value->ac1_uncode, "whscode" => $value->em1_whscode ));
							$VALUE_GIFT_IVA = $VALUE_GIFT_IVA + round($value->fv1_vatsum, $DECI_MALES);
						}
					}

					$granTotalIvaOriginal = $granTotalIva;
					$VALUE_GIFT_IVA_ORG = $VALUE_GIFT_IVA;


					if (trim($Data['dvf_currency']) != $MONEDALOCAL) {

						$granTotalIva = ($granTotalIva * $TasaDocLoc);

						$VALUE_GIFT_IVA = ($VALUE_GIFT_IVA * $TasaDocLoc) ;
					
						$LineTotal = ($LineTotal * $TasaDocLoc);
					}



					$TIva = $granTotalIva2;

					if (trim($Data['dvf_currency']) != $MONEDASYS) {
		
						$MontoSysCR = ($TIva / $TasaLocSys);

						$VALUE_GIFT_IVA_SYS = ( $VALUE_GIFT_IVA / $TasaLocSys );
												
					} else {
						
						$MontoSysCR = $granTotalIvaOriginal;						
						$VALUE_GIFT_IVA_SYS = $VALUE_GIFT_IVA_ORG;
						
					}


					$SumaCreditosSYS = ( $SumaCreditosSYS + round($MontoSysCR, $DECI_MALES) - round( $VALUE_GIFT_IVA_SYS, $DECI_MALES ) );
					$AC1LINE = $AC1LINE + 1;


					$TOTALCXCLOCIVA = ($TOTALCXCLOCIVA + $granTotalIva) - $VALUE_GIFT_IVA;
					$TOTALCXCSYSIVA = ($TOTALCXCSYSIVA + $MontoSysCR) - $VALUE_GIFT_IVA_SYS;
					

					$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

						':ac1_trans_id' => $resInsertAsiento,
						':ac1_account' => $value->fv1_cuentaIva,
						':ac1_debit' => 0,
						':ac1_credit' => round($granTotalIva, $DECI_MALES),
						':ac1_debit_sys' => 0,
						':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
						':ac1_currex' => 0,
						':ac1_doc_date' => $this->validateDate($Data['dvf_docdate']) ? $Data['dvf_docdate'] : NULL,
						':ac1_doc_duedate' => $this->validateDate($Data['dvf_duedate']) ? $Data['dvf_duedate'] : NULL,
						':ac1_debit_import' => 0,
						':ac1_credit_import' => 0,
						':ac1_debit_importsys' => 0,
						':ac1_credit_importsys' => 0,
						':ac1_font_key' => $resInsert,
						':ac1_font_line' => 1,
						':ac1_font_type' => is_numeric($Data['dvf_doctype']) ? $Data['dvf_doctype'] : 0,
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
						':ac1_made_user' => isset($Data['dvf_createby']) ? $Data['dvf_createby'] : NULL,
						':ac1_accperiod' => 1,
						':ac1_close' => 0,
						':ac1_cord' => 0,
						':ac1_ven_debit' => 0,
						':ac1_ven_credit' => round($granTotalIva, $DECI_MALES),
						':ac1_fiscal_acct' => 0,
						':ac1_taxid' => $CodigoImp,
						':ac1_isrti' => $Vat,
						':ac1_basert' => 0,
						':ac1_mmcode' => 0,
						':ac1_legal_num' => isset($Data['dvf_cardcode']) ? $Data['dvf_cardcode'] : NULL,
						':ac1_codref' => 1,
						':ac1_line'   => 	$AC1LINE,
						':ac1_base_tax' => round($LineTotal, $DECI_MALES),
						':business' => $Data['business'],
						':branch' 	=> $Data['branch'],
						':ac1_codret' => 0 
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
				//FIN Procedimiento para llenar Impuestos


				if ( $Data['dvf_doctype'] ==  34 ){
					$DetalleConsolidadoCostoInventario = [];
				}

				// solo si el documento no viene de una entrega
				if ( $Data['dvf_basetype'] != 3 ) { 
					//Procedimiento para llenar costo inventario
					foreach ($DetalleConsolidadoCostoInventario as $key => $posicion) {
						$grantotalCostoInventario = 0;
						$grantotalCostoInventarioOriginal = 0;
						$cuentaInventario = "";
						foreach ($posicion as $key => $value) {

							$CUENTASINV = $this->account->getAccountItem($value->fv1_itemcode, $value->em1_whscode);

							if ( isset($CUENTASINV['error']) && $CUENTASINV['error'] == false ) {
								$dbito = 0;
								$cdito = 0;

								$MontoSysDB = 0;
								$MontoSysCR = 0;

								$sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode";

								$resCosto = $this->pedeo->queryTable($sqlCosto, array(':bdi_itemcode' => $value->fv1_itemcode, ':bdi_whscode' => $value->em1_whscode));

								if (isset($resCosto[0])) {

									$cuentaInventario = $CUENTASINV['data']['acct_inv'];


									$costoArticulo = $resCosto[0]['bdi_avgprice'];
									$cantidadArticulo = $value->fv1_quantity;
									$grantotalCostoInventario = ($grantotalCostoInventario + ($costoArticulo * $cantidadArticulo));
								} else {

									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resCosto,
										'mensaje'	=> 'No se encontro el costo para el item: ' . $value->fv1_itemcode
									);

									$this->response($respuesta);

									return;
								}
							} else {
								// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $CUENTASINV,
									'mensaje'	=> 'No se encontro la cuenta de inventario y costo para el item ' . $value->fv1_itemcode
								);

								$this->response($respuesta);

								return;
							}
						}

						$codigo3 = substr($cuentaInventario, 0, 1);

						$grantotalCostoInventarioOriginal = $grantotalCostoInventario;

						if (trim($Data['dvf_currency']) != $MONEDALOCAL) {

							$grantotalCostoInventario = ($grantotalCostoInventario * $TasaLocSys);
						}

						if ($codigo3 == 1 || $codigo3 == "1") {
							$cdito = $grantotalCostoInventario;
							if (trim($Data['dvf_currency']) != $MONEDASYS) {
								$MontoSysCR = ($cdito / $TasaLocSys);
							} else {
								$MontoSysCR = $grantotalCostoInventarioOriginal;
							}
						} else if ($codigo3 == 2 || $codigo3 == "2") {
							$cdito = $grantotalCostoInventario;
							if (trim($Data['dvf_currency']) != $MONEDASYS) {
								$MontoSysCR = ($cdito / $TasaLocSys);
							} else {
								$MontoSysCR = $grantotalCostoInventarioOriginal;
							}
						} else if ($codigo3 == 3 || $codigo3 == "3") {
							$cdito = $grantotalCostoInventario;
							if (trim($Data['dvf_currency']) != $MONEDASYS) {
								$MontoSysCR = ($cdito / $TasaLocSys);
							} else {
								$MontoSysCR = $grantotalCostoInventarioOriginal;
							}
						} else if ($codigo3 == 4 || $codigo3 == "4") {
							$cdito = $grantotalCostoInventario;
							if (trim($Data['dvf_currency']) != $MONEDASYS) {
								$MontoSysCR = ($cdito / $TasaLocSys);
							} else {
								$MontoSysCR = $grantotalCostoInventarioOriginal;
							}
						} else if ($codigo3 == 5  || $codigo3 == "5") {
							$dbito = $grantotalCostoInventario;
							if (trim($Data['dvf_currency']) != $MONEDASYS) {
								$MontoSysDB = ($dbito / $TasaLocSys);
							} else {
								$MontoSysDB = $grantotalCostoInventarioOriginal;
							}
						} else if ($codigo3 == 6 || $codigo3 == "6") {
							$dbito = $grantotalCostoInventario;
							if (trim($Data['dvf_currency']) != $MONEDASYS) {
								$MontoSysDB = ($dbito / $TasaLocSys);
							} else {
								$MontoSysDB = $grantotalCostoInventarioOriginal;
							}
						} else if ($codigo3 == 7 || $codigo3 == "7") {
							$dbito = $grantotalCostoInventario;
							if (trim($Data['dvf_currency']) != $MONEDASYS) {
								$MontoSysDB = ($dbito / $TasaLocSys);
							} else {
								$MontoSysDB = $grantotalCostoInventarioOriginal;
							}
						} else if ($codigo3 == 9 || $codigo3 == "9") {
							$cdito = $grantotalCostoInventario;
							if (trim($Data['dvf_currency']) != $MONEDASYS) {
								$MontoSysCR = ($cdito / $TasaLocSys);
							} else {
								$MontoSysCR = $grantotalCostoInventarioOriginal;
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
							':ac1_doc_date' => $this->validateDate($Data['dvf_docdate']) ? $Data['dvf_docdate'] : NULL,
							':ac1_doc_duedate' => $this->validateDate($Data['dvf_duedate']) ? $Data['dvf_duedate'] : NULL,
							':ac1_debit_import' => 0,
							':ac1_credit_import' => 0,
							':ac1_debit_importsys' => 0,
							':ac1_credit_importsys' => 0,
							':ac1_font_key' => $resInsert,
							':ac1_font_line' => 1,
							':ac1_font_type' => is_numeric($Data['dvf_doctype']) ? $Data['dvf_doctype'] : 0,
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
							':ac1_made_user' => isset($Data['dvf_createby']) ? $Data['dvf_createby'] : NULL,
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
							':ac1_legal_num' => isset($Data['dvf_cardcode']) ? $Data['dvf_cardcode'] : NULL,
							':ac1_codref' => 1,
							':ac1_line'   => 	$AC1LINE,
							':ac1_base_tax' => 0,
							':business' => $Data['business'],
							':branch' 	=> $Data['branch'],
							':ac1_codret' => 0
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
				}	//FIN Procedimiento para llenar costo inventario


				foreach ($DetalleConsolidadoCostoCosto as $key => $posicion) {
					$grantotalCostoCosto = 0;
					$grantotalCostoCostoOriginal = 0;
					$cuentaCosto = "";
					$dbito = 0;
					$cdito = 0;
					$MontoSysDB = 0;
					$MontoSysCR = 0;
					foreach ($posicion as $key => $value) {

                        // SI NO VIENE DE UNA ENTREGA
						if ( $Data['dvf_basetype'] != 3 && $Data['dvf_doctype'] == 5 ) {

							$CUENTASINV = $this->account->getAccountItem($value->fv1_itemcode, $value->em1_whscode);

							if ( isset($CUENTASINV['error']) && $CUENTASINV['error'] == false ) {
								$dbito = 0;
								$cdito = 0;
								$MontoSysDB = 0;
								$MontoSysCR = 0;

								$sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode";

								$resCosto = $this->pedeo->queryTable($sqlCosto, array(":bdi_itemcode" => $value->fv1_itemcode, ':bdi_whscode' => $value->em1_whscode));

								if (isset($resCosto[0])) {

									if ( $value->gift == 0 ){

										$cuentaCosto = $CUENTASINV['data']['acct_cost'];

									
										$costoArticulo = $resCosto[0]['bdi_avgprice'];
										$cantidadArticulo = $value->fv1_quantity;
										$grantotalCostoCosto = ($grantotalCostoCosto + ($costoArticulo * $cantidadArticulo));

									}else{

										$costoArticulo = $resCosto[0]['bdi_avgprice'];
										$cantidadArticulo = $value->fv1_quantity;
	
										$VALUE_GIFT = $VALUE_GIFT + ($costoArticulo * $cantidadArticulo);

										array_push($DETALLE_GIFT, array( "monto" => $costoArticulo * $cantidadArticulo, "item" => $value->fv1_itemcode, "proyecto" =>$value->ac1_prj_code , "centrocosto" =>$value->ac1_prc_code, "unidadnegocio" => $value->ac1_uncode, "whscode" => $value->em1_whscode ));

									}

	

									
								} else {

									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => '',
										'mensaje'	=> 'No se encontro el costo para el item: ' . $value->fv1_itemcode
									);

									$this->response($respuesta);

									return;
								}
							} else {
								// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $CUENTASINV,
									'mensaje'	=> 'No se encontro la cuenta puente para costo'
								);

								$this->response($respuesta);

								return;
							}

							//Procedimiento cuando sea tipo documento 3 (Entrega)

						} else if ( $Data['dvf_basetype'] == 3  && $Data['dvf_doctype'] == 5  ) { 

							$sqlArticulo = "SELECT pge_bridge_inv FROM pgem WHERE pge_id = :business"; // Cuenta costo puente
							$resArticulo = $this->pedeo->queryTable($sqlArticulo, array(':business' => $Data['business'])); // Cuenta costo puente

							if (isset($resArticulo[0])) {
								$dbito = 0;
								$cdito = 0;
								$MontoSysDB = 0;
								$MontoSysCR = 0;


								$sqlCosto = "SELECT
												CASE
													WHEN bmi_quantity < 0 THEN bmi_quantity * -1
													ELSE bmi_quantity
												END AS cantidad, bmi_cost,bmy_baseentry,bmy_doctype
											FROM tbmi
											WHERE bmy_doctype = :bmy_doctype
											AND bmy_baseentry = :bmy_baseentry
											AND bmi_itemcode  = :bmi_itemcode";

								$resCosto = $this->pedeo->queryTable($sqlCosto, array(':bmi_itemcode' => $value->fv1_itemcode, ':bmy_doctype' => $Data['dvf_basetype'], ':bmy_baseentry' => $Data['dvf_baseentry']));

								if (isset($resCosto[0])) {

									$cuentaCosto = $resArticulo[0]['pge_bridge_inv'];
									$costoArticulo = $resCosto[0]['bmi_cost'];

									// SE VALIDA QUE LA CANTIDAD DEL ITEM A FACTURAR NO SUPERE LA CANTIDAD EN EL DOCUMENTO DE ENTREGA

									if ($value->fv1_quantity > $resCosto[0]['cantidad']) {
										//Se devuelve la transaccion
										$this->pedeo->trans_rollback();

										$respuesta = array(
											'error'   => true,
											'data'	  => $resArticulo,
											'mensaje'	=> 'La cantidad a facturar  mayor a la entregada, para el item: ' . $value->fv1_itemcode
										);

										$this->response($respuesta);

										return;
									}

									//SE VALIDA QUE EL TOTAL FACTURADO NO SUPERE EL TOTAL ENTEGRADO

									$sqlFacturadoItem = "SELECT coalesce((SUM(fv1_quantity)), 0) AS cantidaditem
														FROM dvfv
														INNER JOIN vfv1
														ON dvf_docentry = fv1_docentry
														WHERE dvf_baseentry = :dvf_baseentry
														AND fv1_itemcode = :fv1_itemcode
														AND dvf_basetype = :dvf_basetype";


									$resFacturadoItem = $this->pedeo->queryTable($sqlFacturadoItem, array(
										':dvf_baseentry' =>  $resCosto[0]['bmy_baseentry'],
										':fv1_itemcode'  =>  $value->fv1_itemcode,
										':dvf_basetype'  =>  $resCosto[0]['bmy_doctype']
									));


									if (isset($resFacturadoItem[0])) {

										$CantidadOriginal = ($resFacturadoItem[0]['cantidaditem'] - $value->fv1_quantity);

										if ($CantidadOriginal >= $resCosto[0]['cantidad']) {
											//Se devuelve la transaccion
											$this->pedeo->trans_rollback();
											$respuesta = array(
												'error'   => true,
												'data'	  => $resArticulo,
												'mensaje'	=> 'No se puede facturar una cantidad mayor a la entregada, para el item: ' . $value->fv1_itemcode
											);

											$this->response($respuesta);

											return;
										} else {

											$resto = ($resCosto[0]['cantidad'] - $CantidadOriginal);

											if ($value->fv1_quantity > $resto) {

												$this->pedeo->trans_rollback();
												$respuesta = array(
													'error'   => true,
													'data'	  => $resArticulo,
													'mensaje'	=> 'No se puede facturar una cantidad mayor a la entregada, para el item: ' . $value->fv1_itemcode
												);

												$this->response($respuesta);

												return;
											}
										}
									}

									$cantidadArticulo = $value->fv1_quantity;
									$grantotalCostoCosto = ($grantotalCostoCosto + ($costoArticulo * $cantidadArticulo));
								} else {
									//Se devuelve la transaccion
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => 'hola2',
										'mensaje'	=> 'No se encontro el costo para el item: ' . $value->fv1_itemcode
									);

									$this->response($respuesta);

									return;
								}
							} else {
								// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $resArticulo,
									'mensaje'	=> 'No se encontro la cuenta puente para costo del item: ' . $value->fv1_itemcode
								);

								$this->response($respuesta);

								return;
							}

						} else if ( $Data['dvf_doctype'] == 34  ){
							
							$sqlArticulo = "SELECT pge_bridge_inv FROM pgem WHERE pge_id = :business"; // Cuenta costo puente
							$resArticulo = $this->pedeo->queryTable($sqlArticulo, array(':business' => $Data['business'])); // Cuenta costo puente

							if (isset($resArticulo[0])) {
								$dbito = 0;
								$cdito = 0;
								$MontoSysDB = 0;
								$MontoSysCR = 0;


								$sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode";

								$resCosto = $this->pedeo->queryTable($sqlCosto, array(":bdi_itemcode" => $value->fv1_itemcode, ':bdi_whscode' => $value->em1_whscode));

								if (isset($resCosto[0])) {

									$cuentaCosto = $resArticulo[0]['pge_bridge_inv'];
									$costoArticulo = $resCosto[0]['bdi_avgprice'];

								


									$cantidadArticulo = $value->fv1_quantity;
									$grantotalCostoCosto = ($grantotalCostoCosto + ($costoArticulo * $cantidadArticulo));
								} else {
									//Se devuelve la transaccion
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => 'hola2',
										'mensaje'	=> 'No se encontro el costo para el item: ' . $value->fv1_itemcode
									);

									$this->response($respuesta);

									return;
								}
							} else {
								// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $resArticulo,
									'mensaje'	=> 'No se encontro la cuenta puente para costo del item: ' . $value->fv1_itemcode
								);

								$this->response($respuesta);

								return;
							}

						} 
					}



					$codigo3 = substr($cuentaCosto, 0, 1);

					$grantotalCostoCostoOriginal = $grantotalCostoCosto;

					if (trim($Data['dvf_currency']) != $MONEDALOCAL) {

						$grantotalCostoCosto = ($grantotalCostoCosto * $TasaLocSys);
					}


					if ($codigo3 == 1 || $codigo3 == "1") {
						$cdito = 	$grantotalCostoCosto; //Se voltearon las cuenta
						if (trim($Data['dvf_currency']) != $MONEDASYS) {
							$MontoSysCR = ($cdito / $TasaLocSys); //Se voltearon las cuenta
						} else {
							$MontoSysCR = $grantotalCostoCostoOriginal;
						}
					} else if ($codigo3 == 2 || $codigo3 == "2") {
						$cdito = 	$grantotalCostoCosto;
						if (trim($Data['dvf_currency']) != $MONEDASYS) {
							$MontoSysCR = ($cdito / $TasaLocSys); //Se voltearon las cuenta
						} else {
							$MontoSysCR = $grantotalCostoCostoOriginal;
						}
					} else if ($codigo3 == 3 || $codigo3 == "3") {
						$cdito = 	$grantotalCostoCosto;
						if (trim($Data['dvf_currency']) != $MONEDASYS) {
							$MontoSysCR = ($cdito / $TasaLocSys); //Se voltearon las cuenta
						} else {
							$MontoSysCR = $grantotalCostoCostoOriginal;
						}
					} else if ($codigo3 == 4 || $codigo3 == "4") {
						$cdito = 	$grantotalCostoCosto;
						if (trim($Data['dvf_currency']) != $MONEDASYS) {
							$MontoSysCR = ($cdito / $TasaLocSys); //Se voltearon las cuenta
						} else {
							$MontoSysCR = $grantotalCostoCostoOriginal;
						}
					} else if ($codigo3 == 5  || $codigo3 == "5") {
						$dbito = 	$grantotalCostoCosto;
						if (trim($Data['dvf_currency']) != $MONEDASYS) {
							$MontoSysDB = ($dbito / $TasaLocSys); //Se voltearon las cuenta
						} else {
							$MontoSysDB = $grantotalCostoCostoOriginal;
						}
					} else if ($codigo3 == 6 || $codigo3 == "6") {
						$dbito = 	$grantotalCostoCosto;
						if (trim($Data['dvf_currency']) != $MONEDASYS) {
							$MontoSysDB = ($dbito / $TasaLocSys); //Se voltearon las cuenta
						} else {
							$MontoSysDB = $grantotalCostoCostoOriginal;
						}
					} else if ($codigo3 == 7 || $codigo3 == "7") {
						$dbito = 	$grantotalCostoCosto;
						if (trim($Data['dvf_currency']) != $MONEDASYS) {
							$MontoSysDB = ($dbito / $TasaLocSys); //Se voltearon las cuenta
						} else {
							$MontoSysDB = $grantotalCostoCostoOriginal;
						}
					} else if ($codigo3 == 9 || $codigo3 == "9") {
						$cdito = 	$grantotalCostoCosto;
						if (trim($Data['dvf_currency']) != $MONEDASYS) {
							$MontoSysCR = ($cdito / $TasaLocSys); //Se voltearon las cuenta
						} else {
							$MontoSysCR = $grantotalCostoCostoOriginal;
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
							':ac1_doc_date' => $this->validateDate($Data['dvf_docdate']) ? $Data['dvf_docdate'] : NULL,
							':ac1_doc_duedate' => $this->validateDate($Data['dvf_duedate']) ? $Data['dvf_duedate'] : NULL,
							':ac1_debit_import' => 0,
							':ac1_credit_import' => 0,
							':ac1_debit_importsys' => 0,
							':ac1_credit_importsys' => 0,
							':ac1_font_key' => $resInsert,
							':ac1_font_line' => 1,
							':ac1_font_type' => is_numeric($Data['dvf_doctype']) ? $Data['dvf_doctype'] : 0,
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
							':ac1_made_user' => isset($Data['dvf_createby']) ? $Data['dvf_createby'] : NULL,
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
							':ac1_legal_num' => isset($Data['dvf_cardcode']) ? $Data['dvf_cardcode'] : NULL,
							':ac1_codref' => 1,
							':ac1_line'   => 	$AC1LINE,
							':ac1_base_tax' => 0,
							':business' => $Data['business'],
							':branch' 	=> $Data['branch'],
							':ac1_codret' => 0
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
								'mensaje'	=> 'No se pudo registrar la factura de venta'
							);

							$this->response($respuesta);

							return;
						}
					}
				}

				// SOLO SI ES UNA FACTURA ANTICIPADA
				
				$DetalleConsolidadoCostoCostoEntrega = [];

				if ( $Data['dvf_doctype'] == 5 ){
					$DetalleConsolidadoCostoCostoEntrega = $DetalleConsolidadoCostoCosto;
					$DetalleConsolidadoCostoCosto = [];
				}

				foreach ($DetalleConsolidadoCostoCosto as $key => $posicion) {
					$grantotalCostoCosto = 0;
					$grantotalCostoCostoOriginal = 0;
					$cuentaCosto = "";
					$dbito = 0;
					$cdito = 0;
					$MontoSysDB = 0;
					$MontoSysCR = 0;
					foreach ($posicion as $key => $value) {

						if (  $Data['dvf_doctype'] == 34 ) {

							$CUENTASINV = $this->account->getAccountItem($value->fv1_itemcode, $value->em1_whscode);

							if ( isset($CUENTASINV['error']) && $CUENTASINV['error'] == false ) {
								$dbito = 0;
								$cdito = 0;
								$MontoSysDB = 0;
								$MontoSysCR = 0;

								$sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode";

								$resCosto = $this->pedeo->queryTable($sqlCosto, array(":bdi_itemcode" => $value->fv1_itemcode, ':bdi_whscode' => $value->em1_whscode));

								if (isset($resCosto[0])) {
									// CUENTA DE COSTO

									if ( $value->gift == 0 ){

										$cuentaCosto = $CUENTASINV['data']['acct_cost'];


										$costoArticulo = $resCosto[0]['bdi_avgprice'];
										$cantidadArticulo = $value->fv1_quantity;
										$grantotalCostoCosto = ($grantotalCostoCosto + ($costoArticulo * $cantidadArticulo));

									}else{

										$costoArticulo = $resCosto[0]['bdi_avgprice'];
										$cantidadArticulo = $value->fv1_quantity;
										array_push($DETALLE_GIFT, array( "monto" => $costoArticulo * $cantidadArticulo, "item" => $value->fv1_itemcode, "proyecto" =>$value->ac1_prj_code , "centrocosto" =>$value->ac1_prc_code, "unidadnegocio" => $value->ac1_uncode, "whscode" => $value->em1_whscode ));
										$VALUE_GIFT = $VALUE_GIFT + ($costoArticulo * $cantidadArticulo);
										
									}
									


								} else {

									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => '',
										'mensaje'	=> 'No se encontro el costo para el item: ' . $value->fv1_itemcode
									);

									$this->response($respuesta);

									return;
								}
							} else {
								// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $CUENTASINV,
									'mensaje'	=> 'No se encontro la cuenta puente para costo'
								);

								$this->response($respuesta);

								return;
							}


						} 
					}



					$codigo3 = substr($cuentaCosto, 0, 1);

					$grantotalCostoCostoOriginal = $grantotalCostoCosto;

					if (trim($Data['dvf_currency']) != $MONEDALOCAL) {

						$grantotalCostoCosto = ($grantotalCostoCosto * $TasaLocSys);
					}


					if ($codigo3 == 1 || $codigo3 == "1") {
						$cdito = 	$grantotalCostoCosto; //Se voltearon las cuenta
						if (trim($Data['dvf_currency']) != $MONEDASYS) {
							$MontoSysCR = ($cdito / $TasaLocSys); //Se voltearon las cuenta
						} else {
							$MontoSysCR = $grantotalCostoCostoOriginal;
						}
					} else if ($codigo3 == 2 || $codigo3 == "2") {
						$cdito = 	$grantotalCostoCosto;
						if (trim($Data['dvf_currency']) != $MONEDASYS) {
							$MontoSysCR = ($cdito / $TasaLocSys); //Se voltearon las cuenta
						} else {
							$MontoSysCR = $grantotalCostoCostoOriginal;
						}
					} else if ($codigo3 == 3 || $codigo3 == "3") {
						$cdito = 	$grantotalCostoCosto;
						if (trim($Data['dvf_currency']) != $MONEDASYS) {
							$MontoSysCR = ($cdito / $TasaLocSys); //Se voltearon las cuenta
						} else {
							$MontoSysCR = $grantotalCostoCostoOriginal;
						}
					} else if ($codigo3 == 4 || $codigo3 == "4") {
						$cdito = 	$grantotalCostoCosto;
						if (trim($Data['dvf_currency']) != $MONEDASYS) {
							$MontoSysCR = ($cdito / $TasaLocSys); //Se voltearon las cuenta
						} else {
							$MontoSysCR = $grantotalCostoCostoOriginal;
						}
					} else if ($codigo3 == 5  || $codigo3 == "5") {
						$dbito = 	$grantotalCostoCosto;
						if (trim($Data['dvf_currency']) != $MONEDASYS) {
							$MontoSysDB = ($dbito / $TasaLocSys); //Se voltearon las cuenta
						} else {
							$MontoSysDB = $grantotalCostoCostoOriginal;
						}
					} else if ($codigo3 == 6 || $codigo3 == "6") {
						$dbito = 	$grantotalCostoCosto;
						if (trim($Data['dvf_currency']) != $MONEDASYS) {
							$MontoSysDB = ($dbito / $TasaLocSys); //Se voltearon las cuenta
						} else {
							$MontoSysDB = $grantotalCostoCostoOriginal;
						}
					} else if ($codigo3 == 7 || $codigo3 == "7") {
						$dbito = 	$grantotalCostoCosto;
						if (trim($Data['dvf_currency']) != $MONEDASYS) {
							$MontoSysDB = ($dbito / $TasaLocSys); //Se voltearon las cuenta
						} else {
							$MontoSysDB = $grantotalCostoCostoOriginal;
						}
					} else if ($codigo3 == 9 || $codigo3 == "9") {
						$cdito = 	$grantotalCostoCosto;
						if (trim($Data['dvf_currency']) != $MONEDASYS) {
							$MontoSysCR = ($cdito / $TasaLocSys); //Se voltearon las cuenta
						} else {
							$MontoSysCR = $grantotalCostoCostoOriginal;
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
							':ac1_doc_date' => $this->validateDate($Data['dvf_docdate']) ? $Data['dvf_docdate'] : NULL,
							':ac1_doc_duedate' => $this->validateDate($Data['dvf_duedate']) ? $Data['dvf_duedate'] : NULL,
							':ac1_debit_import' => 0,
							':ac1_credit_import' => 0,
							':ac1_debit_importsys' => 0,
							':ac1_credit_importsys' => 0,
							':ac1_font_key' => $resInsert,
							':ac1_font_line' => 1,
							':ac1_font_type' => is_numeric($Data['dvf_doctype']) ? $Data['dvf_doctype'] : 0,
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
							':ac1_made_user' => isset($Data['dvf_createby']) ? $Data['dvf_createby'] : NULL,
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
							':ac1_legal_num' => isset($Data['dvf_cardcode']) ? $Data['dvf_cardcode'] : NULL,
							':ac1_codref' => 1,
							':ac1_line'   => 	$AC1LINE,
							':ac1_base_tax' => 0,
							':business' => $Data['business'],
							':branch' 	=> $Data['branch'],
							':ac1_codret' => 0
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
								'mensaje'	=> 'No se pudo registrar la factura de venta'
							);
	
							$this->response($respuesta);
	
							return;
						}
					}
				}


				// SOLO SI ES CUENTA 3 SI VIENE DE UNA ENTRADA
				if ( $Data['dvf_basetype'] == 3 ) {

					foreach ($DetalleConsolidadoCostoCostoEntrega as $key => $posicion) {
						$grantotalCostoCosto = 0;
						$grantotalCostoCostoOriginal = 0;
						$cuentaCosto = "";
						$dbito = 0;
						$cdito = 0;
						$MontoSysDB = 0;
						$MontoSysCR = 0;
						foreach ($posicion as $key => $value) {

							

							$CUENTASINV = $this->account->getAccountItem($value->fv1_itemcode, $value->em1_whscode);


							if ( isset($CUENTASINV['error']) && $CUENTASINV['error'] == false ) {
								$dbito = 0;
								$cdito = 0;
								$MontoSysDB = 0;
								$MontoSysCR = 0;

								$sqlCosto = "";
								$resCosto = [];


								// CASO COPIAR DE ENTREGA
								if ( $Data['dvf_basetype'] == 3 ) {

									$sqlCosto = "SELECT
												CASE
													WHEN bmi_quantity < 0 THEN bmi_quantity * -1
													ELSE bmi_quantity
													END AS cantidad, bmi_cost,bmy_baseentry,bmy_doctype
												FROM tbmi
												WHERE bmy_doctype = :bmy_doctype
												AND bmy_baseentry = :bmy_baseentry
												AND bmi_itemcode  = :bmi_itemcode";

									$resCosto = $this->pedeo->queryTable($sqlCosto, array(':bmi_itemcode' => $value->fv1_itemcode, ':bmy_doctype' => $Data['dvf_basetype'], ':bmy_baseentry' => $Data['dvf_baseentry']));
								
							
								}



								if (isset($resCosto[0])) {

									if ( $value->gift == 0 ){

										$cuentaCosto = $CUENTASINV['data']['acct_cost'];


										$costoArticulo =  $resCosto[0]['bmi_cost'];
										$cantidadArticulo = $value->fv1_quantity;
										$grantotalCostoCosto = ($grantotalCostoCosto + ($costoArticulo * $cantidadArticulo));

									}else{

										$costoArticulo =  $resCosto[0]['bmi_cost'];
										$cantidadArticulo = $value->fv1_quantity;
										array_push($DETALLE_GIFT, array( "monto" => $costoArticulo * $cantidadArticulo, "item" => $value->fv1_itemcode, "proyecto" =>$value->ac1_prj_code , "centrocosto" =>$value->ac1_prc_code, "unidadnegocio" => $value->ac1_uncode,"whscode" => $value->em1_whscode ));
										$VALUE_GIFT = $VALUE_GIFT + ($costoArticulo * $cantidadArticulo);
									}



								} else {

									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resCosto,
										'mensaje'	=> 'No se encontro el costo para el item: ' . $value->fv1_itemcode
									);

									$this->response($respuesta);

									return;
								}
							} else {
								// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $CUENTASINV,
									'mensaje'	=> 'No se encontro la cuenta para costo para el item: ' . $value->fv1_itemcode
								);

								$this->response($respuesta);

								return;
							}
						}

						$codigo3 = substr($cuentaCosto, 0, 1);

						$grantotalCostoCostoOriginal = $grantotalCostoCosto;

						if (trim($Data['dvf_currency']) != $MONEDALOCAL) {

							$grantotalCostoCosto = ($grantotalCostoCosto * $TasaLocSys);
						}

						if ($codigo3 == 1 || $codigo3 == "1") {
							$dbito = 	$grantotalCostoCosto;
							if (trim($Data['dvf_currency']) != $MONEDASYS) {
								$MontoSysDB = ($dbito / $TasaLocSys);
							} else {
								$MontoSysDB = $grantotalCostoCostoOriginal;
							}
						} else if ($codigo3 == 2 || $codigo3 == "2") {
							$cdito = 	$grantotalCostoCosto;
							if (trim($Data['dvf_currency']) != $MONEDASYS) {
								$MontoSysCR = ($cdito / $TasaLocSys);
							} else {
								$MontoSysCR = $grantotalCostoCostoOriginal;
							}
						} else if ($codigo3 == 3 || $codigo3 == "3") {
							$cdito = 	$grantotalCostoCosto;
							if (trim($Data['dvf_currency']) != $MONEDASYS) {
								$MontoSysCR = ($cdito / $TasaLocSys);
							} else {
								$MontoSysCR = $grantotalCostoCostoOriginal;
							}
						} else if ($codigo3 == 4 || $codigo3 == "4") {
							$cdito = 	$grantotalCostoCosto;
							if (trim($Data['dvf_currency']) != $MONEDASYS) {
								$MontoSysCR = ($cdito / $TasaLocSys);
							} else {
								$MontoSysCR = $grantotalCostoCostoOriginal;
							}
						} else if ($codigo3 == 5  || $codigo3 == "5") {
							$dbito = 	$grantotalCostoCosto;
							if (trim($Data['dvf_currency']) != $MONEDASYS) {
								$MontoSysDB = ($dbito / $TasaLocSys);
							} else {
								$MontoSysDB = $grantotalCostoCostoOriginal;
							}
						} else if ($codigo3 == 6 || $codigo3 == "6") {
							$dbito = 	$grantotalCostoCosto;
							if (trim($Data['dvf_currency']) != $MONEDASYS) {
								$MontoSysDB = ($dbito / $TasaLocSys);
							} else {
								$MontoSysDB = $grantotalCostoCostoOriginal;
							}
						} else if ($codigo3 == 7 || $codigo3 == "7") {
							$dbito = 	$grantotalCostoCosto;
							if (trim($Data['dvf_currency']) != $MONEDASYS) {
								$MontoSysDB = ($dbito / $TasaLocSys);
							} else {
								$MontoSysDB = $grantotalCostoCostoOriginal;
							}
						} else if ($codigo3 == 9 || $codigo3 == "9") {
							$cdito = 	$grantotalCostoCosto;
							if (trim($Data['dvf_currency']) != $MONEDASYS) {
								$MontoSysCR = ($cdito / $TasaLocSys);
							} else {
								$MontoSysCR = $grantotalCostoCostoOriginal;
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
								':ac1_doc_date' => $this->validateDate($Data['dvf_docdate']) ? $Data['dvf_docdate'] : NULL,
								':ac1_doc_duedate' => $this->validateDate($Data['dvf_duedate']) ? $Data['dvf_duedate'] : NULL,
								':ac1_debit_import' => 0,
								':ac1_credit_import' => 0,
								':ac1_debit_importsys' => 0,
								':ac1_credit_importsys' => 0,
								':ac1_font_key' => $resInsert,
								':ac1_font_line' => 1,
								':ac1_font_type' => is_numeric($Data['dvf_doctype']) ? $Data['dvf_doctype'] : 0,
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
								':ac1_made_user' => isset($Data['dvf_createby']) ? $Data['dvf_createby'] : NULL,
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
								':ac1_legal_num' => isset($Data['dvf_cardcode']) ? $Data['dvf_cardcode'] : NULL,
								':ac1_codref' => 1,
								':ac1_line'   => 	$AC1LINE,
								':ac1_base_tax' => 0,
								':business' => $Data['business'],
								':branch' 	=> $Data['branch'],
								':ac1_codret' => 0
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
					}
				}

				//SOLO SI ES CUENTA 3

				//FIN Procedimiento para llenar costo costo

				//Procedimiento para llenar cuentas por cobrar

				$TasaIGTF = 0;

				$sqlcuentaCxC = "SELECT  f1.dms_card_code, f2.mgs_acct FROM dmsn AS f1
														 JOIN dmgs  AS f2
														 ON CAST(f2.mgs_id AS varchar(100)) = f1.dms_group_num
														 WHERE  f1.dms_card_code = :dms_card_code
														 AND f1.dms_card_type = '1'"; //1 para clientes";

				$rescuentaCxC = $this->pedeo->queryTable($sqlcuentaCxC, array(":dms_card_code" => $Data['dvf_cardcode']));


				if (isset($Data['dvf_igtf']) && $Data['dvf_igtf'] > 0) {

					$sqlTasaIGTF = "SELECT COALESCE( get_tax_currency(:moneda, :fecha), 0) AS tasa";
					$resTasaIGTF = $this->pedeo->queryTable($sqlTasaIGTF, array(


						':moneda' => $Data['dvf_currency'],
						':fecha'  => $Data['dvf_docdate']

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

						$this->response($respuesta);

						return;
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


						if (trim($Data['dvf_currency']) != $MONEDALOCAL) {

							$MontoIGTF = ($MontoIGTF * $TasaDocLoc);
							$MontoIGTFSYS = ($MontoIGTF / $TasaLocSys);
						}

						if (trim($Data['dvf_currency']) != $MONEDASYS) {

							$MontoIGTFSYS = ($MontoIGTF / $TasaLocSys);
						} else {

							$MontoIGTFSYS = $Data['dvf_igtf'];
						}
					}


					$cuentaCxC = $rescuentaCxC[0]['mgs_acct'];
					$codigo2 = substr($rescuentaCxC[0]['mgs_acct'], 0, 1);

					$MontoIGTFSYS = round(($MontoIGTF / $TasaLocSys), $DECI_MALES);


					if ($codigo2 == 1 || $codigo2 == "1") {

						$debitoo = ($TOTALCXCLOC + $TOTALCXCLOCIVA);
						$MontoSysDB =	($TOTALCXCSYS + $TOTALCXCSYSIVA);

						$debitoo = ($debitoo + $MontoIGTF);
						$MontoSysDB =	($MontoSysDB + $MontoIGTFSYS);
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

						$debitoo = ($TOTALCXCLOC + $TOTALCXCLOCIVA);
						$MontoSysDB =	($TOTALCXCSYS + $TOTALCXCSYSIVA);

						$debitoo = ($debitoo + $MontoIGTF);
						$MontoSysDB =	($MontoSysDB + $MontoIGTFSYS);
					} else if ($codigo2 == 6 || $codigo2 == "6") {

						$debitoo = ($TOTALCXCLOC + $TOTALCXCLOCIVA);
						$MontoSysDB =	($TOTALCXCSYS + $TOTALCXCSYSIVA);


						$debitoo = ($debitoo + $MontoIGTF);
						$MontoSysDB =	($MontoSysDB + $MontoIGTFSYS);
					} else if ($codigo2 == 7 || $codigo2 == "7") {

						$debitoo = ($TOTALCXCLOC + $TOTALCXCLOCIVA);
						$MontoSysDB =	($TOTALCXCSYS + $TOTALCXCSYSIVA);


						$debitoo = ($debitoo + $MontoIGTF);
						$MontoSysDB =	($MontoSysDB + $MontoIGTFSYS);
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


					$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
					$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));




					$RetencionDescuentoSYS = 0;
					$RetencionDescuentoLOC = 0;

					if (is_array($ContenidoRentencion)) {
						if (intval(count($ContenidoRentencion)) > 0) {
							
							if (isset($Data['dvf_totalretiva']) && is_numeric($Data['dvf_totalretiva']) && ( $Data['dvf_totalretiva'] * -1  > 0 ) ){

								if (trim($Data['dvf_currency']) != $MONEDALOCAL) {

									$RetencionDescuentoLOC = $RetencionDescuentoLOC + ( ( $Data['dvf_totalretiva'] * -1 ) * $TasaDocLoc);
								
								}else{
									$RetencionDescuentoLOC = $Data['dvf_totalretiva'] * -1;
								}

				

								if (trim($Data['dvf_currency']) != $MONEDASYS) {

									$RetencionDescuentoSYS = $RetencionDescuentoSYS + ( ( $Data['dvf_totalretiva'] * -1 ) / $TasaLocSys );
								} else {
		
									$RetencionDescuentoSYS = ( $Data['dvf_totalretiva'] * -1 );
								}

		
							}


							if (isset($Data['dvf_totalret']) && is_numeric($Data['dvf_totalret']) && ( $Data['dvf_totalret'] * -1  > 0 ) ){

								if (trim($Data['dvf_currency']) != $MONEDALOCAL) {

									$RetencionDescuentoLOC = $RetencionDescuentoLOC + ( ( $Data['dvf_totalret'] * -1 ) * $TasaDocLoc);
								
								}else{
									$RetencionDescuentoLOC = $RetencionDescuentoLOC + ($Data['dvf_totalret'] * -1);
								}


								if (trim($Data['dvf_currency']) != $MONEDASYS) {

									$RetencionDescuentoSYS = $RetencionDescuentoSYS + ( ( $Data['dvf_totalret'] * -1 ) / $TasaLocSys );
								} else {
		
									$RetencionDescuentoSYS =  $Data['dvf_totalret'] * -1 ;
								}
							}
							
						}
					}

					if ( $debitoo > 0 || $creditoo > 0 ){

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
	
						$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(
	
							':ac1_trans_id' => $resInsertAsiento,
							':ac1_account' => $cuentaCxC,
							':ac1_debit' => round($debitoo, $DECI_MALES),
							':ac1_credit' => round($creditoo, $DECI_MALES),
							':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
							':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
							':ac1_currex' => 0,
							':ac1_doc_date' => $this->validateDate($Data['dvf_docdate']) ? $Data['dvf_docdate'] : NULL,
							':ac1_doc_duedate' => $this->validateDate($Data['dvf_duedate']) ? $Data['dvf_duedate'] : NULL,
							':ac1_debit_import' => 0,
							':ac1_credit_import' => 0,
							':ac1_debit_importsys' => 0,
							':ac1_credit_importsys' => 0,
							':ac1_font_key' => $resInsert,
							':ac1_font_line' => 1,
							':ac1_font_type' => is_numeric($Data['dvf_doctype']) ? $Data['dvf_doctype'] : 0,
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
							':ac1_made_user' => isset($Data['dvf_createby']) ? $Data['dvf_createby'] : NULL,
							':ac1_accperiod' => 1,
							':ac1_close' => 0,
							':ac1_cord' => 0,
							':ac1_ven_debit' => round($debitoo, $DECI_MALES),
							':ac1_ven_credit' => round($creditoo, $DECI_MALES),
							':ac1_fiscal_acct' => 0,
							':ac1_taxid' => 0,
							':ac1_isrti' => 0,
							':ac1_basert' => 0,
							':ac1_mmcode' => 0,
							':ac1_legal_num' => isset($Data['dvf_cardcode']) ? $Data['dvf_cardcode'] : NULL,
							':ac1_codref' => 1,
							':ac1_line'   => 	$AC1LINE,
							':ac1_base_tax' => 0,
							':business' => $Data['business'],
							':branch' 	=> $Data['branch'],
							':ac1_codret' => 0
						));
	
						if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
							// Se verifica que el detalle no de error insertando //
							$LineCXC = $resDetalleAsiento;
							$AcctLine = $cuentaCxC;
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
						'data'	  => $resDetalleAsiento,
						'mensaje'	=> 'No se pudo registrar la factura de ventas, el tercero no tiene cuenta asociada'
					);

					$this->response($respuesta);

					return;
				}
				//FIN Procedimiento para llenar cuentas por cobrar


				// SE VALIDA DIFERENCIA POR DECIMALES
				// Y SE AGREGA UN ASIENTO DE DIFERENCIA EN DECIMALES
				// AJUSTE AL PESO
				// SEGUN SEA EL CASO
				///

				$debito  = 0;
				$credito = 0;
				if ($SumaCreditosSYS > $SumaDebitosSYS || $SumaDebitosSYS > $SumaCreditosSYS) {

					$sqlCuentaDiferenciaDecimal = "SELECT pge_acc_ajp FROM pgem WHERE pge_id = :business";
					$resCuentaDiferenciaDecimal = $this->pedeo->queryTable($sqlCuentaDiferenciaDecimal, array( ':business' => $Data['business'] ));

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
								':ac1_doc_date' => $this->validateDate($Data['dvf_docdate']) ? $Data['dvf_docdate'] : NULL,
								':ac1_doc_duedate' => $this->validateDate($Data['dvf_duedate']) ? $Data['dvf_duedate'] : NULL,
								':ac1_debit_import' => 0,
								':ac1_credit_import' => 0,
								':ac1_debit_importsys' => 0,
								':ac1_credit_importsys' => 0,
								':ac1_font_key' => $resInsert,
								':ac1_font_line' => 1,
								':ac1_font_type' => is_numeric($Data['dvf_doctype']) ? $Data['dvf_doctype'] : 0,
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
								':ac1_made_user' => isset($Data['dvf_createby']) ? $Data['dvf_createby'] : NULL,
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
								':ac1_legal_num' => isset($Data['dvf_cardcode']) ? $Data['dvf_cardcode'] : NULL,
								':ac1_codref' => 1,
								':ac1_line'   => 	$AC1LINE,
								':ac1_base_tax' => 0,
								':business' => $Data['business'],
								':branch' 	=> $Data['branch'],
								':ac1_codret' => 0
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

				// FIN VALIDACION DIFERENCIA EN DECIMALES



				// VALIDANDO IMPUESTO IGTF CASO PARA VENEZUELA

				if (isset($Data['dvf_igtf']) && $Data['dvf_igtf'] > 0) {


					foreach ($DetalleIgtf as $key => $val) {
						$sqlInsertIgtf = "INSERT INTO igtf(gtf_currency, gtf_docentry, gtf_doctype, gtf_value, gtf_tax, gtf_taxdivisa, gft_docvalue, gtf_collected, gtf_balancer)VALUES(:gtf_currency, :gtf_docentry, :gtf_doctype, :gtf_value, :gtf_tax, :gtf_taxdivisa, :gft_docvalue, :gtf_collected, :gtf_balancer)";
						$resInserIgtf = $this->pedeo->insertRow($sqlInsertIgtf, array(
							':gtf_currency'  => $val['gtf_currency'],
							':gtf_docentry'  => $resInsert,
							':gtf_doctype'   => $Data['dvf_doctype'],
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

							$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

							return;
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


						$cdito = $Data['dvf_igtf'];



						if (trim($Data['dvf_currency']) != $MONEDALOCAL) {

							$cdito = ($cdito * $TasaDocLoc);
							$BaseIgtf = ($BaseIgtf * $TasaDocLoc);
						}



						if (trim($Data['dvf_currency']) != $MONEDASYS) {
							$MontoSysCR = ($cdito / $TasaLocSys);
						} else {
							$MontoSysCR = $Data['dvf_igtf'];
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
							':ac1_doc_date' => $this->validateDate($Data['dvf_docdate']) ? $Data['dvf_docdate'] : NULL,
							':ac1_doc_duedate' => $this->validateDate($Data['dvf_duedate']) ? $Data['dvf_duedate'] : NULL,
							':ac1_debit_import' => 0,
							':ac1_credit_import' => 0,
							':ac1_debit_importsys' => 0,
							':ac1_credit_importsys' => 0,
							':ac1_font_key' => $resInsert,
							':ac1_font_line' => 1,
							':ac1_font_type' => is_numeric($Data['dvf_doctype']) ? $Data['dvf_doctype'] : 0,
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
							':ac1_made_user' => isset($Data['dvf_createby']) ? $Data['dvf_createby'] : NULL,
							':ac1_accperiod' => 1,
							':ac1_close' => 0,
							':ac1_cord' => 0,
							':ac1_ven_debit' => round($dbito, $DECI_MALES),
							':ac1_ven_credit' => round($cdito, $DECI_MALES),
							':ac1_fiscal_acct' => 0,
							':ac1_taxid' => isset($Data['dvf_igtfcode']) ? $Data['dvf_igtfcode'] : NULL,
							':ac1_isrti' => isset($Data['dvf_taxigtf']) ? $Data['dvf_taxigtf'] : NULL,
							':ac1_basert' => 0,
							':ac1_mmcode' => 0,
							':ac1_legal_num' => isset($Data['dvf_cardcode']) ? $Data['dvf_cardcode'] : NULL,
							':ac1_codref' => 1,
							':ac1_line'   => 	$AC1LINE,
							':ac1_base_tax' => $BaseIgtf,
							':business' => $Data['business'],
							':branch' 	=> $Data['branch'],
							':ac1_codret' => 0
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
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'	  => $resCuentaIGTF,
							'mensaje'	=> 'No se encontro la cuenta contable para el impuesto IGTF'
						);

						$this->response($respuesta);

						return;
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
								'mensaje'	=> 'No se pudo registrar la factura de ventas, no se encontro la cuenta para la retencion ' . $value->crt_typert
							);

							$this->response($respuesta);

							return;
						}
					}

					$Basert = $BaseLineaRet;
					$totalRetencionOriginal = $totalRetencion;

					if (trim($Data['dvf_currency']) != $MONEDALOCAL) {
						$totalRetencion = ($totalRetencion * $TasaDocLoc);
						$BaseLineaRet = ($BaseLineaRet * $TasaDocLoc);
						$Basert = $BaseLineaRet;
					}


					if (trim($Data['dvf_currency']) != $MONEDASYS) {
						$MontoSysDB = ($totalRetencion / $TasaLocSys);
					} else {
						$MontoSysDB = 	$totalRetencionOriginal;
					}

					$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
					$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));

					$AC1LINE = $AC1LINE + 1;
					$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

						':ac1_trans_id' => $resInsertAsiento,
						':ac1_account' => $cuenta,
						':ac1_debit' => round($totalRetencion, $DECI_MALES),
						':ac1_credit' => 0,
						':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
						':ac1_credit_sys' => 0,
						':ac1_currex' => 0,
						':ac1_doc_date' => $this->validateDate($Data['dvf_docdate']) ? $Data['dvf_docdate'] : NULL,
						':ac1_doc_duedate' => $this->validateDate($Data['dvf_duedate']) ? $Data['dvf_duedate'] : NULL,
						':ac1_debit_import' => 0,
						':ac1_credit_import' => 0,
						':ac1_debit_importsys' => 0,
						':ac1_credit_importsys' => 0,
						':ac1_font_key' => $resInsert,
						':ac1_font_line' => 1,
						':ac1_font_type' => is_numeric($Data['dvf_doctype']) ? $Data['dvf_doctype'] : 0,
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
						':ac1_made_user' => isset($Data['dvf_createby']) ? $Data['dvf_createby'] : NULL,
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
						':ac1_legal_num' => isset($Data['dvf_cardcode']) ? $Data['dvf_cardcode'] : NULL,
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
							'mensaje'	=> 'No se pudo registrar la factura de ventas'
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
		
								$this->response($respuesta);
		
								return;
							}

							$montoorig  = $monto;

							if (trim($Data['dvf_currency']) != $MONEDALOCAL) {

								$monto = ($monto * $TasaDocLoc);
					
							}
		
		
							if (trim($Data['dvf_currency']) != $MONEDASYS) {
								$montosys = ($montoorig / $TasaLocSys);
							} else {
								$montosys = $montoorig;
							}

							$AC1LINE = $AC1LINE + 1;

							$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

								':ac1_trans_id' => $resInsertAsiento,
								':ac1_account' => $cuenta,
								':ac1_debit' => round($monto, $DECI_MALES),
								':ac1_credit' => 0,
								':ac1_debit_sys' => round($montosys, $DECI_MALES),
								':ac1_credit_sys' => 0,
								':ac1_currex' => 0,
								':ac1_doc_date' => $this->validateDate($Data['dvf_docdate']) ? $Data['dvf_docdate'] : NULL,
								':ac1_doc_duedate' => $this->validateDate($Data['dvf_duedate']) ? $Data['dvf_duedate'] : NULL,
								':ac1_debit_import' => 0,
								':ac1_credit_import' => 0,
								':ac1_debit_importsys' => 0,
								':ac1_credit_importsys' => 0,
								':ac1_font_key' => $resInsert,
								':ac1_font_line' => 1,
								':ac1_font_type' => is_numeric($Data['dvf_doctype']) ? $Data['dvf_doctype'] : 0,
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
								':ac1_made_user' => isset($Data['dvf_createby']) ? $Data['dvf_createby'] : NULL,
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
								':ac1_legal_num' => isset($Data['dvf_cardcode']) ? $Data['dvf_cardcode'] : NULL,
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
									'mensaje'	=> 'No se pudo registrar la factura de ventas'
								);
		
								$this->response($respuesta);
		
								return;
							}

						}
					}

				}
				// FIN ASIENTO PARA MOSTRAR CASO ITEM  OBSEQUIO


				// FIN DE OPERACIONES VITALES

				// VALIDANDO ESTADOS DE DOCUMENTOS
				if ($Data['dvf_basetype'] == 1) {


					$sqlEstado1 = "SELECT
								count(t1.vc1_linenum) item,
								sum(t1.vc1_quantity) cantidad
								from dvct t0
								inner join vct1 t1 on t0.dvc_docentry = t1.vc1_docentry
								where t0.dvc_docentry = :dvc_docentry and t0.dvc_doctype = :dvc_doctype";


					$resEstado1 = $this->pedeo->queryTable($sqlEstado1, array(
						':dvc_docentry' => $Data['dvf_baseentry'],
						':dvc_doctype' => $Data['dvf_basetype']
						// ':vc1_itemcode' => $detail['ov1_itemcode']
					));

					$sqlEstado2 = "SELECT
										coalesce(count(distinct t3.fv1_baseline),0) item,
										coalesce(sum(t3.fv1_quantity),0) cantidad
								from dvct t0
								left join vct1 t1 on t0.dvc_docentry = t1.vc1_docentry
								left join dvfv t2 on t0.dvc_docentry = t2.dvf_baseentry
								left join vfv1 t3 on t2.dvf_docentry = t3.fv1_docentry and t1.vc1_itemcode = t3.fv1_itemcode and t1.vc1_linenum = t3.fv1_baseline
								where t0.dvc_docentry = :dvc_docentry and t0.dvc_doctype = :dvc_doctype";


					$resEstado2 = $this->pedeo->queryTable($sqlEstado2, array(
						':dvc_docentry' => $Data['dvf_baseentry'],
						':dvc_doctype' => $Data['dvf_basetype']
						// ':vc1_itemcode' => $detail['ov1_itemcode']
					));

					$item_cot = $resEstado1[0]['item'];
					$item_ord = $resEstado2[0]['item'];
					$cantidad_cot = $resEstado1[0]['cantidad'];
					$cantidad_ord = $resEstado2[0]['cantidad'];

					if ($item_ord >= $item_cot &&   $cantidad_ord >= $cantidad_cot) {

						$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
											VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

						$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


							':bed_docentry' => $Data['dvf_baseentry'],
							':bed_doctype' => $Data['dvf_basetype'],
							':bed_status' => 3, //ESTADO CERRADO
							':bed_createby' => $Data['dvf_createby'],
							':bed_date' => date('Y-m-d'),
							':bed_baseentry' => $resInsert,
							':bed_basetype' => $Data['dvf_doctype']
						));


						if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
						} else {

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' => $resInsertEstado,
								'mensaje'	=> 'No se pudo registrar la orden de venta'
							);


							$this->response($respuesta);

							return;
						}
					}
				} else if ($Data['dvf_basetype'] == 2) {


					$sqlEstado1 = "SELECT
									count(t1.ov1_itemcode) item,
									sum(t1.ov1_quantity) cantidad
								from dvov t0
								inner join vov1 t1 on t0.vov_docentry = t1.ov1_docentry
								where t0.vov_docentry = :vov_docentry and t0.vov_doctype = :vov_doctype";


					$resEstado1 = $this->pedeo->queryTable($sqlEstado1, array(
						':vov_docentry' => $Data['dvf_baseentry'],
						':vov_doctype' => $Data['dvf_basetype']
					));


					$sqlEstado2 = "SELECT
									coalesce(count(distinct t3.fv1_baseline),0) item,
									coalesce(sum(t3.fv1_quantity),0) cantidad
								from dvov t0
								left join vov1 t1 on t0.vov_docentry = t1.ov1_docentry
								left join dvfv t2 on t0.vov_docentry = t2.dvf_baseentry
								left join vfv1 t3 on t2.dvf_docentry = t3.fv1_docentry and t1.ov1_itemcode = t3.fv1_itemcode and t1.ov1_linenum = t3.fv1_baseline
								where t0.vem_docentry = :vem_docentry and t0.vem_doctype = :vem_doctype";
					$resEstado2 = $this->pedeo->queryTable($sqlEstado2, array(
						':vem_docentry' => $Data['dvf_baseentry'],
						':vem_doctype' => $Data['dvf_basetype']
					));

					$item_ord = $resEstado1[0]['item'];
					$item_del = $resEstado2[0]['item'];
					$cantidad_ord = $resEstado1[0]['cantidad'];
					$cantidad_del = $resEstado2[0]['cantidad'];

					if ($item_ord == $item_del  &&  $cantidad_ord == $cantidad_del) {

						$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
											VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

						$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


							':bed_docentry' => $Data['dvf_baseentry'],
							':bed_doctype' => $Data['dvf_basetype'],
							':bed_status' => 3, //ESTADO CERRADO
							':bed_createby' => $Data['dvf_createby'],
							':bed_date' => date('Y-m-d'),
							':bed_baseentry' => $resInsert,
							':bed_basetype' => $Data['dvf_doctype']
						));


						if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
						} else {

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' => $resInsertEstado,
								'mensaje'	=> 'No se pudo registrar la entrega de venta'
							);


							$this->response($respuesta);

							return;
						}
					}
				} else if ($Data['dvf_basetype'] == 3) {

					$sqlEstado1 = 'SELECT
								count(t1.em1_linenum) item,
								coalesce(sum(t1.em1_quantity),0) cantidad
								from dvem t0
								inner join vem1 t1 on t0.vem_docentry = t1.em1_docentry
								where t0.vem_docentry = :vem_docentry and t0.vem_doctype = :vem_doctype';

					$resEstado1 = $this->pedeo->queryTable($sqlEstado1, array(
						':vem_docentry' => $Data['dvf_baseentry'],
						':vem_doctype' => $Data['dvf_basetype']
					));

					$sqlDev = "SELECT
								count(t3.dv1_baseline) item,
								coalesce(sum(t3.dv1_quantity),0) cantidad
								from dvem t0
								left join vem1 t1 on t0.vem_docentry = t1.em1_docentry
								left join dvdv t2 on t0.vem_docentry = t2.vdv_baseentry and t0.vem_doctype = t2.vdv_basetype
								left join vdv1 t3 on t2.vdv_docentry = t3.dv1_docentry and t1.em1_itemcode = t3.dv1_itemcode and t1.em1_linenum = t3.dv1_baseline
								where t0.vem_docentry = :vem_docentry and t0.vem_doctype = :vem_doctype";

					$resDev = $this->pedeo->queryTable($sqlDev, array(
						':vem_docentry' => $Data['dvf_baseentry'],
						':vem_doctype' => $Data['dvf_basetype']
					));

					$resta_cantidad = $resEstado1[0]['cantidad'] - $resDev[0]['cantidad'];
					$resta_item = $resEstado1[0]['item'] - $resDev[0]['item'];

					$sqlEstado2 = "SELECT
								coalesce(count(distinct t3.fv1_baseline),0) item,
								coalesce(sum(t3.fv1_quantity),0) cantidad
								from dvem t0
								left join vem1 t1 on t0.vem_docentry = t1.em1_docentry
								left join dvfv t2 on t0.vem_docentry = t2.dvf_baseentry and t0.vem_doctype = t2.dvf_basetype
								left join vfv1 t3 on t2.dvf_docentry = t3.fv1_docentry and t1.em1_itemcode = t3.fv1_itemcode and t1.em1_linenum = t3.fv1_baseline
								where t0.vem_docentry = :vem_docentry and t0.vem_doctype = :vem_doctype";
					$resEstado2 = $this->pedeo->queryTable($sqlEstado2, array(
						':vem_docentry' => $Data['dvf_baseentry'],
						':vem_doctype' => $Data['dvf_basetype']
					));

					if (is_numeric($resta_item) && $resta_item == 0) {
						$item_del = abs($resEstado1[0]['item']);
					} else {
						$item_del = abs($resta_item);
					}

					$item_fact = abs($resEstado2[0]['item']);
					$cantidad_del = abs($resta_cantidad);
					$cantidad_fact = abs($resEstado2[0]['cantidad']);

					if ($item_del == $item_fact && $cantidad_del == $cantidad_fact) {

						$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
											VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

						$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


							':bed_docentry' => $Data['dvf_baseentry'],
							':bed_doctype' => $Data['dvf_basetype'],
							':bed_status' => 3, //ESTADO CERRADO
							':bed_createby' => $Data['dvf_createby'],
							':bed_date' => date('Y-m-d'),
							':bed_baseentry' => $resInsert,
							':bed_basetype' => $Data['dvf_doctype']
						));


						if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {

							if ($Data['dvf_basetype'] == 3) {
								$sqlEstado1 = 'SELECT
												count(t1.em1_linenum) item,
												coalesce(sum(t1.em1_quantity),0) cantidad
											from dvem t0
											inner join vem1 t1 on t0.vem_docentry = t1.em1_docentry
											where t0.vem_docentry = :vem_docentry and t0.vem_doctype = :vem_doctype';

								$resEstado1 = $this->pedeo->queryTable($sqlEstado1, array(
									':vem_docentry' => $Data['dvf_baseentry'],
									':vem_doctype' => $Data['dvf_basetype']
								));

								$sqlDev1 = "SELECT
												count(t3.dv1_baseline) item,
												coalesce(sum(t3.dv1_quantity),0) cantidad
											from dvem t0
											left join vem1 t1 on t0.vem_docentry = t1.em1_docentry
											left join dvdv t2 on t0.vem_docentry = t2.vdv_baseentry and t0.vem_doctype = t2.vdv_basetype
											left join vdv1 t3 on t2.vdv_docentry = t3.dv1_docentry and t1.em1_itemcode = t3.dv1_itemcode and t1.em1_linenum = t3.dv1_baseline
											where t0.vem_docentry = :vem_docentry and t0.vem_doctype = :vem_doctype";

								$resDev1 = $this->pedeo->queryTable($sqlDev1, array(
									':vem_docentry' => $Data['dvf_baseentry'],
									':vem_doctype' => $Data['dvf_basetype']
								));

								$resta_cantidad1 = $resEstado1[0]['cantidad'] - $resDev1[0]['cantidad'];
								$resta_item1 = $resEstado1[0]['item'] - $resDev1[0]['item'];

								$sqlDev2 = "SELECT DISTINCT
											t2.*
											from dvem t0
											left join vem1 t1 on t0.vem_docentry = t1.em1_docentry
											left join dvdv t2 on t0.vem_docentry = t2.vdv_baseentry and t0.vem_doctype = t2.vdv_basetype
											left join vdv1 t3 on t2.vdv_docentry = t3.dv1_docentry and t1.em1_itemcode = t3.dv1_itemcode and t1.em1_linenum = t3.dv1_baseline
											where t0.vem_docentry = :vem_docentry and t0.vem_doctype = :vem_doctype";

								$resDev2 = $this->pedeo->queryTable($sqlDev2, array(
									':vem_docentry' => $Data['dvf_baseentry'],
									':vem_doctype' => $Data['dvf_basetype']
								));

								$sqlEstado2 = "SELECT
												coalesce(count(distinct t3.fv1_baseline),0) item,
												coalesce(sum(t3.fv1_quantity),0) cantidad
											from dvem t0
											left join vem1 t1 on t0.vem_docentry = t1.em1_docentry
											left join dvfv t2 on t0.vem_docentry = t2.dvf_baseentry and t0.vem_doctype = t2.dvf_basetype
											left join vfv1 t3 on t2.dvf_docentry = t3.fv1_docentry and t1.em1_itemcode = t3.fv1_itemcode and t1.em1_linenum = t3.fv1_baseline
											where t0.vem_docentry = :vem_docentry and t0.vem_doctype = :vem_doctype";
								$resEstado2 = $this->pedeo->queryTable($sqlEstado2, array(
									':vem_docentry' => $Data['dvf_baseentry'],
									':vem_doctype' => $Data['dvf_basetype']
								));

								if (is_numeric($resta_item1) && $resta_item1 == 0) {
									$item_del1 = abs($resEstado1[0]['item']);
								} else {
									$item_del1 = abs($resta_item1);
								}

								//  $item_del1 = $resEstado1[0]['item'];
								$item_fact1 = abs($resEstado2[0]['item']);
								$cantidad_del1 = abs($resta_cantidad1);
								$cantidad_fact1 = abs($resEstado2[0]['cantidad']);


								if ($item_del1 == $item_fact1  &&  $cantidad_del1 ==  $cantidad_fact1) {

									foreach ($resDev2 as $key => $value) {
										// code...


										$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
															VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

										$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


											':bed_docentry' => $value['vdv_docentry'],
											':bed_doctype' => $value['vdv_doctype'],
											':bed_status' => 3, //ESTADO CERRADO
											':bed_createby' => $Data['dvf_createby'],
											':bed_date' => date('Y-m-d'),
											':bed_baseentry' => $resInsert,
											':bed_basetype' => $Data['dvf_doctype']
										));

										if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
										} else {


											$this->pedeo->trans_rollback();

											$respuesta = array(
												'error'   => true,
												'data' => $resInsertEstado,
												'mensaje'	=> 'No se pudo registrar la devolucion de venta'
											);


											$this->response($respuesta);

											return;
										}
									}
								}
							}
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

					$ressqlmac1 = [];
					$sqlmac1 = "SELECT acc_name, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys FROM  mac1 inner join dacc on ac1_account = acc_code WHERE ac1_trans_id = :ac1_trans_id";
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

				if ( $Data['dvf_doctype'] == 5 || $Data['dvf_doctype'] == 34 ){

					if (isset($Data['pay_detail'])){ // SI LA FACTURA ES DE CONTADO

						$resultPago = $this->generic->createPaymentReceived($Data, $resInsert, $LineCXC, $AcctLine);


						if ($resultPago['error'] == false){
	
						}else{
	
							$this->pedeo->trans_rollback();
	
							$respuesta = array(
								'error'   => true,
								'data' 	  => $resultPago['data'],
								'mensaje' => $resultPago['mensaje'],
								
							);
	
							$this->response($respuesta);
	
							return;
						}
					}

				}
				
				/**
				 * INSERTAR MOVIMIENTO GLOBAL.
				 */
				$this->generic->insertMG([
					['type' => 'dmsn', 'code' => $Data['dvf_cardcode']],
					['type' => 'pgdn', 'code' => $Data['dvf_series']],
					['type' => 'pgec', 'code' => $Data['dvf_currency']],
					['type' => 'dmpl', 'code' => $Data['dvf_pricelist']],
				]);


				if ( isset($Data['preview']) && $Data['preview'] == 1 ) {

					$respuesta = $this->account->getAcounting($resInsertAsiento);

					$this->pedeo->trans_rollback();

					return $this->response($respuesta);

				} else {
					// Si todo sale bien despues de insertar el detalle de la factura de Ventas
					// se confirma la trasaccion  para que los cambios apliquen permanentemente
					// en la base de datos y se confirma la operacion exitosa.
					$this->pedeo->trans_commit();
				}
				

				$respuesta = array(
					'error' => false,
					'data' => $resInsert,
					'mensaje' => 'Factura de ventas registrada con exito'
				);


			} else {
				// Se devuelven los cambios realizados en la transaccion
				// si occurre un error  y se muestra devuelve el error.
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data' => $resInsert,
					'mensaje'	=> 'No se pudo registrar la Factura de ventas'
				);

				$this->response($respuesta);

				return;
			}
		} catch (\Exception $e) {

			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data' 		=> $e,
				'mensaje'	=> 'No se pudo registrar la Factura de ventas'
			);

			$this->response($respuesta);

			return;
		}

		$this->response($respuesta);
	}

	//ACTUALIZAR Factura de Ventas
	public function updateSalesInv_post()
	{

		$Data = $this->post();

		if (
			!isset($Data['dvf_docentry']) or !isset($Data['dvf_docnum']) or
			!isset($Data['dvf_docdate']) or !isset($Data['dvf_duedate']) or
			!isset($Data['dvf_duedev']) or !isset($Data['dvf_pricelist']) or
			!isset($Data['dvf_cardcode']) or !isset($Data['dvf_cardname']) or
			!isset($Data['dvf_currency']) or !isset($Data['dvf_contacid']) or
			!isset($Data['dvf_slpcode']) or !isset($Data['dvf_empid']) or
			!isset($Data['dvf_comment']) or !isset($Data['dvf_doctotal']) or
			!isset($Data['dvf_baseamnt']) or !isset($Data['dvf_taxtotal']) or
			!isset($Data['dvf_discprofit']) or !isset($Data['dvf_discount']) or
			!isset($Data['dvf_createat']) or !isset($Data['dvf_baseentry']) or
			!isset($Data['dvf_basetype']) or !isset($Data['dvf_doctype']) or
			!isset($Data['dvf_idadd']) or !isset($Data['dvf_adress']) or
			!isset($Data['dvf_paytype']) or !isset($Data['dvf_attch']) or
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
				'mensaje' => 'No se encontro el detalle de la factura de ventas'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlUpdate = "UPDATE dvfv	SET dvf_docdate=:dvf_docdate,dvf_duedate=:dvf_duedate, dvf_duedev=:dvf_duedev, dvf_pricelist=:dvf_pricelist, dvf_cardcode=:dvf_cardcode,
			  						dvf_cardname=:dvf_cardname, dvf_currency=:dvf_currency, dvf_contacid=:dvf_contacid, dvf_slpcode=:dvf_slpcode,
										dvf_empid=:dvf_empid, dvf_comment=:dvf_comment, dvf_doctotal=:dvf_doctotal, dvf_baseamnt=:dvf_baseamnt,
										dvf_taxtotal=:dvf_taxtotal, dvf_discprofit=:dvf_discprofit, dvf_discount=:dvf_discount, dvf_createat=:dvf_createat,
										dvf_baseentry=:dvf_baseentry, dvf_basetype=:dvf_basetype, dvf_doctype=:dvf_doctype, dvf_idadd=:dvf_idadd,
										dvf_adress=:dvf_adress, dvf_paytype=:dvf_paytype,business = :business,branch = :branch
										WHERE dvf_docentry=:dvf_docentry";

		$this->pedeo->trans_begin();

		$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
			':dvf_docnum' => is_numeric($Data['dvf_docnum']) ? $Data['dvf_docnum'] : 0,
			':dvf_docdate' => $this->validateDate($Data['dvf_docdate']) ? $Data['dvf_docdate'] : NULL,
			':dvf_duedate' => $this->validateDate($Data['dvf_duedate']) ? $Data['dvf_duedate'] : NULL,
			':dvf_duedev' => $this->validateDate($Data['dvf_duedev']) ? $Data['dvf_duedev'] : NULL,
			':dvf_pricelist' => is_numeric($Data['dvf_pricelist']) ? $Data['dvf_pricelist'] : 0,
			':dvf_cardcode' => isset($Data['dvf_pricelist']) ? $Data['dvf_pricelist'] : NULL,
			':dvf_cardname' => isset($Data['dvf_cardname']) ? $Data['dvf_cardname'] : NULL,
			':dvf_currency' => isset($Data['dvf_currency']) ? $Data['dvf_currency'] : NULL,
			':dvf_contacid' => isset($Data['dvf_contacid']) ? $Data['dvf_contacid'] : NULL,
			':dvf_slpcode' => is_numeric($Data['dvf_slpcode']) ? $Data['dvf_slpcode'] : 0,
			':dvf_empid' => is_numeric($Data['dvf_empid']) ? $Data['dvf_empid'] : 0,
			':dvf_comment' => isset($Data['dvf_comment']) ? $Data['dvf_comment'] : NULL,
			':dvf_doctotal' => is_numeric($Data['dvf_doctotal']) ? $Data['dvf_doctotal'] : 0,
			':dvf_baseamnt' => is_numeric($Data['dvf_baseamnt']) ? $Data['dvf_baseamnt'] : 0,
			':dvf_taxtotal' => is_numeric($Data['dvf_taxtotal']) ? $Data['dvf_taxtotal'] : 0,
			':dvf_discprofit' => is_numeric($Data['dvf_discprofit']) ? $Data['dvf_discprofit'] : 0,
			':dvf_discount' => is_numeric($Data['dvf_discount']) ? $Data['dvf_discount'] : 0,
			':dvf_createat' => $this->validateDate($Data['dvf_createat']) ? $Data['dvf_createat'] : NULL,
			':dvf_baseentry' => is_numeric($Data['dvf_baseentry']) ? $Data['dvf_baseentry'] : 0,
			':dvf_basetype' => is_numeric($Data['dvf_basetype']) ? $Data['dvf_basetype'] : 0,
			':dvf_doctype' => is_numeric($Data['dvf_doctype']) ? $Data['dvf_doctype'] : 0,
			':dvf_idadd' => isset($Data['dvf_idadd']) ? $Data['dvf_idadd'] : NULL,
			':dvf_adress' => isset($Data['dvf_adress']) ? $Data['dvf_adress'] : NULL,
			':dvf_paytype' => is_numeric($Data['dvf_paytype']) ? $Data['dvf_paytype'] : 0,
			':business' => isset($Data['business']) ? $Data['business'] : NULL,
			':branch' => isset($Data['branch']) ? $Data['branch'] : NULL,
			':dvf_docentry' => $Data['dvf_docentry']
		));

		if (is_numeric($resUpdate) && $resUpdate == 1) {

			$this->pedeo->queryTable("DELETE FROM vfv1 WHERE fv1_docentry=:fv1_docentry", array(':fv1_docentry' => $Data['dvf_docentry']));

			foreach ($ContenidoDetalle as $key => $detail) {

				$sqlInsertDetail = "INSERT INTO vfv1(fv1_docentry, fv1_itemcode, fv1_itemname, fv1_quantity, fv1_uom, fv1_whscode,
																			fv1_price, fv1_vat, fv1_vatsum, fv1_discount, fv1_linetotal, fv1_costcode, fv1_ubusiness, fv1_project,
																			fv1_acctcode, fv1_basetype, fv1_doctype, fv1_avprice, fv1_inventory, fv1_acciva,fv1_ubication,fv1_lote)VALUES(:fv1_docentry, :fv1_itemcode, :fv1_itemname, :fv1_quantity,
																			:fv1_uom, :fv1_whscode,:fv1_price, :fv1_vat, :fv1_vatsum, :fv1_discount, :fv1_linetotal, :fv1_costcode, :fv1_ubusiness, :fv1_project,
																			:fv1_acctcode, :fv1_basetype, :fv1_doctype, :fv1_avprice, :fv1_inventory, :fv1_acciva,:fv1_ubication,:fv1_lote)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':fv1_docentry' => $Data['dvf_docentry'],
					':fv1_itemcode' => isset($detail['fv1_itemcode']) ? $detail['fv1_itemcode'] : NULL,
					':fv1_itemname' => isset($detail['fv1_itemname']) ? $detail['fv1_itemname'] : NULL,
					':fv1_quantity' => is_numeric($detail['fv1_quantity']) ? $detail['fv1_quantity'] : 0,
					':fv1_uom' => isset($detail['fv1_uom']) ? $detail['fv1_uom'] : NULL,
					':fv1_whscode' => isset($detail['fv1_whscode']) ? $detail['fv1_whscode'] : NULL,
					':fv1_price' => is_numeric($detail['fv1_price']) ? $detail['fv1_price'] : 0,
					':fv1_vat' => is_numeric($detail['fv1_vat']) ? $detail['fv1_vat'] : 0,
					':fv1_vatsum' => is_numeric($detail['fv1_vatsum']) ? $detail['fv1_vatsum'] : 0,
					':fv1_discount' => is_numeric($detail['fv1_discount']) ? $detail['fv1_discount'] : 0,
					':fv1_linetotal' => is_numeric($detail['fv1_linetotal']) ? $detail['fv1_linetotal'] : 0,
					':fv1_costcode' => isset($detail['fv1_costcode']) ? $detail['fv1_costcode'] : NULL,
					':fv1_ubusiness' => isset($detail['fv1_ubusiness']) ? $detail['fv1_ubusiness'] : NULL,
					':fv1_project' => isset($detail['fv1_project']) ? $detail['fv1_project'] : NULL,
					':fv1_acctcode' => is_numeric($detail['fv1_acctcode']) ? $detail['fv1_acctcode'] : 0,
					':fv1_basetype' => is_numeric($detail['fv1_basetype']) ? $detail['fv1_basetype'] : 0,
					':fv1_doctype' => is_numeric($detail['fv1_doctype']) ? $detail['fv1_doctype'] : 0,
					':fv1_avprice' => is_numeric($detail['fv1_avprice']) ? $detail['fv1_avprice'] : 0,
					':fv1_inventory' => is_numeric($detail['fv1_inventory']) ? $detail['fv1_inventory'] : NULL,
					':fv1_acciva' => is_numeric($detail['fv1_cuentaIva']) ? $detail['fv1_cuentaIva'] : 0,
					':fv1_ubication' => isset($detail['fv1_ubication']) ? $detail['fv1_ubication'] : NULL,
					':fv1_lote' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL
				));

				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertDetail,
						'mensaje'	=> 'No se pudo registrar la factura de ventas'
					);

					$this->response($respuesta);

					return;
				}
			}


			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resUpdate,
				'mensaje' => 'Factura de ventas actualizada con exito'
			);
		} else {

			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data'    => $resUpdate,
				'mensaje'	=> 'No se pudo actualizar la factura de ventas'
			);

			$this->response($respuesta);

			return;
		}

		$this->response($respuesta);
	}


	//OBTENER Factura de VentasES
	public function getSalesInv_get()
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

		$sqlSelect = self::getColumn('dvfv', 'dvf', '', '', $DECI_MALES, $Data['business'], $Data['branch'], $Data['docnum']);


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


	//OBTENER Factura de Ventas POR ID
	public function getSalesInvById_get()
	{

		$Data = $this->get();

		if (!isset($Data['dvf_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM dvfv WHERE dvf_docentry =:dvf_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":dvf_docentry" => $Data['dvf_docentry']));

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


	//OBTENER Factura de Ventas DETALLE POR ID
	public function getSalesInvDetail_get()
	{

		$Data = $this->get();

		if (!isset($Data['fv1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT 
						vfv1.*, 
						dmar.dma_series_code
					FROM vfv1
					INNER JOIN dmar ON vfv1.fv1_itemcode = dmar.dma_item_code
					WHERE fv1_docentry =:fv1_docentry";

		$sqlSelectFv = "SELECT round(get_dynamic_conversion(dvf_currency,dvf_currency,dvf_docdate,dvf_igtf,get_localcur()), get_decimals()) as dvf_igtf, round(get_dynamic_conversion(dvf_currency,dvf_currency,dvf_docdate,dvf_igtfapplyed,get_localcur()), get_decimals()) as dvf_igtfapplyed,dvf_igtfcode, igtf.*
						FROM dvfv
						LEFT JOIN igtf
						ON dvf_docentry = gtf_docentry
						AND dvf_doctype = gtf_doctype
						WHERE dvf_docentry = :dvf_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":fv1_docentry" => $Data['fv1_docentry']));

		$resSelectFv = $this->pedeo->queryTable($sqlSelectFv, array(':dvf_docentry' => $Data['fv1_docentry']));

		if (isset($resSelectFv[0])) {

			if (isset($resSelect[0])) {

				$arr = [];

				$arr['detalle'] = $resSelect;
				$arr['complemento'] = $resSelectFv;

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

	//OBTENER Factura de Ventas DETALLE POR ID
	public function getSalesInvDetailCopy_get()
	{

		$Data = $this->get();

		if (!isset($Data['fv1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}
		//CONSULTA PARA VALIDAR DOCTYPE DE FACTURA PARA EL COPIAR DE

		$copyBy = "SELECT * FROM dvfv WHERE dvfv.dvf_docentry = :dvf_docentry";
		$resCopyBy = $this->pedeo->queryTable($copyBy,array(':dvf_docentry' => $Data['fv1_docentry']));
		if(isset($resCopyBy[0])){
			if($resCopyBy[0]['dvf_doctype'] == 34){
				
				$copy = $this->documentcopy->Copy($Data['fv1_docentry'],'dvfv','vfv1','dvf','fv1','gift');

				$sqlSelectFv = "SELECT round(get_dynamic_conversion(dvf_currency,dvf_currency,dvf_docdate,dvf_igtf,get_localcur()), get_decimals()) as dvf_igtf, round(get_dynamic_conversion(dvf_currency,dvf_currency,dvf_docdate,dvf_igtfapplyed,get_localcur()), get_decimals()) as dvf_igtfapplyed,dvf_igtfcode, igtf.*
								FROM dvfv
								LEFT JOIN igtf
								ON dvf_docentry = gtf_docentry
								AND dvf_doctype = gtf_doctype
								WHERE dvf_docentry = :dvf_docentry";


				$resSelectFv = $this->pedeo->queryTable($sqlSelectFv, array(':dvf_docentry' => $Data['fv1_docentry']));

				foreach ($copy as $key => $value) {

					$sqlSelect2 = "SELECT fc.crt_typert,fc.crt_type,fc.crt_basert,fc.crt_profitrt,fc.crt_totalrt,fc.crt_base,fc.crt_linenum,dmar.dma_series_code
									FROM vfv1
									INNER JOIN dvfv ON vfv1.fv1_docentry = dvfv.dvf_docentry
									INNER JOIN dmar ON vfv1.fv1_itemcode = dmar.dma_item_code
									INNER JOIN fcrt fc ON vfv1.fv1_docentry = fc.crt_baseentry and dvfv.dvf_doctype = fc.crt_basetype
									AND vfv1.fv1_linenum = fc.crt_linenum
									WHERE fv1_docentry = :fv1_docentry";
	
					$resSelect2 = $this->pedeo->queryTable($sqlSelect2, array(':fv1_docentry' => $value['fv1_docentry']));

					if (isset($resSelect2[0])) {
						$copy[$key]['retenciones'] = $resSelect2;
					}
				}

				if (isset($copy[0])) {

					if (isset($resSelectFv[0])) {

						$arr = [];

						$arr['detalle'] = $copy;
						$arr['complemento'] = $resSelectFv;

						if(empty($arr['complemento'][0]['dvf_igtfcode']));{
							unset($arr['complemento']);
						}
						
						$respuesta = array(
							'error' => false,
							'data'  => $arr,
							'mensaje' => ''
						);
					} else{

						$arr['detalle'] = $copy;
						unset($arr['complemento']);
					}
				} else {

					$respuesta = array(
						'error'   => true,
						'data' => array(),
						'mensaje'	=> 'busqueda sin resultados'
					);
				}

			}else if($resCopyBy[0]['dvf_doctype'] == 5){
				
				$copy = $this->documentcopy->Copy($Data['fv1_docentry'],'dvfv','vfv1','dvf','fv1','gift');
				
				$sqlSelectFv = "SELECT round(get_dynamic_conversion(dvf_currency,dvf_currency,dvf_docdate,dvf_igtf,get_localcur()), get_decimals()) as dvf_igtf, round(get_dynamic_conversion(dvf_currency,dvf_currency,dvf_docdate,dvf_igtfapplyed,get_localcur()), get_decimals()) as dvf_igtfapplyed,dvf_igtfcode, igtf.*
								FROM dvfv
								LEFT JOIN igtf
								ON dvf_docentry = gtf_docentry
								AND dvf_doctype = gtf_doctype
								WHERE dvf_docentry = :dvf_docentry";


				$resSelectFv = $this->pedeo->queryTable($sqlSelectFv, array(':dvf_docentry' => $Data['fv1_docentry']));

				foreach ($copy as $key => $value) {

					$sqlSelect2 = "SELECT fc.crt_typert,fc.crt_type,fc.crt_basert,fc.crt_profitrt,fc.crt_totalrt,fc.crt_base,fc.crt_linenum,dmar.dma_series_code
					FROM vfv1
					INNER JOIN dvfv ON vfv1.fv1_docentry = dvfv.dvf_docentry
					INNER JOIN dmar ON vfv1.fv1_itemcode = dmar.dma_item_code
					INNER JOIN fcrt fc ON vfv1.fv1_docentry = fc.crt_baseentry and dvfv.dvf_doctype = fc.crt_basetype
					AND vfv1.fv1_linenum = fc.crt_linenum
					WHERE fv1_docentry = :fv1_docentry";
	
					$resSelect2 = $this->pedeo->queryTable($sqlSelect2, array(':fv1_docentry' => $value['fv1_docentry']));

					if (isset($resSelect2[0])) {
						$copy[$key]['retenciones'] = $resSelect2;
					}
				}

				if (isset($copy[0])) {

					if (isset($resSelectFv[0])) {

						$arr = [];
						$arr['detalle'] = $copy;
						$arr['complemento'] = $resSelectFv;

						if(empty($arr['complemento'][0]['dvf_igtfcode']));{
							unset($arr['complemento']);
						}

						$respuesta = array(
							'error' => false,
							'data'  => $arr,
							'mensaje' => ''
						);
					} else{

						$arr['detalle'] = $copy;
					}
				} else {

					$respuesta = array(
						'error'   => true,
						'data' => array(),
						'mensaje'	=> 'busqueda sin resultados'
					);
				}
			}
		}

		



		$this->response($respuesta);
	}

	//OBTENER FACTURA DE VENTA POR ID SOCIO DE NEGOCIO
	public function getSalesInvoiceBySN_get()
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

		$copyData = $this->documentcopy->copyData('dvfv','dvf',$Data['dms_card_code'],$Data['business'],$Data['branch']);

		if (isset($copyData[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $copyData,
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