<?php
// TRANSFERENCIA DE STOCKS
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class StockTransfer extends REST_Controller
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
		$this->load->library('aprobacion');
		$this->load->library('DocumentCopy');
		$this->load->library('DocumentNumbering');
		$this->load->library('Tasa');
		
	}

	//CREAR NUEVA SOLICITUD TRANSFERENCIA DE STOCKS
	public function createSolStockTransfer_post()
	{

		$DECI_MALES =  $this->generic->getDecimals();
		$Data = $this->post();
		$DetalleInventario = new stdClass();
		$DetalleInventario2 = new stdClass();
		$DetalleConsolidadoInventario = [];
		$DetalleConsolidadoInventario2 = [];
		$inArrayInventario = array();
		$inArrayInventario2 = array();
		$posicionInventario = 0;
		$posicionInventario2 = 0;
		$llaveInventario = "";
		$llaveInventario2 = "";
		$DocNumVerificado = 0;
		$ManejaUbicacion = 0;

		$TasaDocLoc = 0;
		$TasaLocSys = 0;
		$MONEDALOCAL = "";
		$MONEDASYS = "";


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
				'mensaje' => 'No se encontro el detalle de la cotización'
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

		// //BUSCANDO LA NUMERACION DEL DOCUMENTO
        $DocNumVerificado = $this->documentnumbering->NumberDoc($Data['ist_series'],$Data['ist_docdate'],$Data['ist_duedate']);
		
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
		$dataTasa = $this->tasa->Tasa($Data['ist_currency'],$Data['ist_docdate']);

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

		// SE VERIFICA SI EL DOCUMENTO A CREAR NO  VIENE DE UN PROCESO DE APROBACION Y NO ESTE APROBADO

		$sqlVerificarAprobacion = "SELECT * FROM tbed WHERE bed_docentry =:bed_docentry AND bed_doctype =:bed_doctype AND bed_status =:bed_status";
		$resVerificarAprobacion = $this->pedeo->queryTable($sqlVerificarAprobacion, array(

			':bed_docentry' => $Data['ist_baseentry'],
			':bed_doctype'  => $Data['ist_basetype'],
			':bed_status'   => 4 // 4 APROBADO SEGUN MODELO DE APROBACION
		));

		// VERIFICA EL MODELO DE APROBACION
		if (!isset($resVerificarAprobacion[0])) {

			$aprobacion = $this->aprobacion->validmodelaprobacion($Data,$ContenidoDetalle,'ist','st1',$Data['business'],$Data['branch']);
	
			if ( isset($aprobacion['error']) && $aprobacion['error'] == false && $aprobacion['data'] == 1 ) {
				
				return $this->response($aprobacion);

			} else  if ( isset($aprobacion['error']) && $aprobacion['error'] == true ) {
				
				return $this->response($aprobacion);
			}
		}

		// FIN PROESO DE VERIFICAR SI EL DOCUMENTO A CREAR NO  VIENE DE UN PROCESO DE APROBACION Y NO ESTE APROBADO

		$sqlInsert = "INSERT INTO dist(ist_series, ist_docnum, ist_docdate, ist_duedate, ist_duedev, ist_pricelist, ist_cardcode,
							ist_cardname, ist_currency, ist_contacid, ist_slpcode, ist_empid, ist_comment, ist_doctotal, ist_baseamnt, ist_taxtotal,
							ist_discprofit, ist_discount, ist_createat, ist_baseentry, ist_basetype, ist_doctype, ist_idadd, ist_adress, ist_paytype,
							ist_createby, business, branch, ist_user_m, ist_activity_type)VALUES(:ist_series, :ist_docnum, :ist_docdate, :ist_duedate, :ist_duedev, :ist_pricelist, :ist_cardcode, :ist_cardname,
							:ist_currency, :ist_contacid, :ist_slpcode, :ist_empid, :ist_comment, :ist_doctotal, :ist_baseamnt, :ist_taxtotal, :ist_discprofit, :ist_discount,
							:ist_createat, :ist_baseentry, :ist_basetype, :ist_doctype, :ist_idadd, :ist_adress, :ist_paytype,:ist_createby, :business, :branch, :ist_user_m ,:ist_activity_type )";

		// SE INICIA TRANSACCION

		$this->pedeo->trans_begin();

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			':ist_docnum' => $DocNumVerificado,
			':ist_series' => is_numeric($Data['ist_series']) ? $Data['ist_series'] : 0,
			':ist_docdate' => $this->validateDate($Data['ist_docdate']) ? $Data['ist_docdate'] : NULL,
			':ist_duedate' => $this->validateDate($Data['ist_duedate']) ? $Data['ist_duedate'] : NULL,
			':ist_duedev' => $this->validateDate($Data['ist_duedev']) ? $Data['ist_duedev'] : NULL,
			':ist_pricelist' => is_numeric($Data['ist_pricelist']) ? $Data['ist_pricelist'] : 0,
			':ist_cardcode' => isset($Data['ist_cardcode']) ? $Data['ist_cardcode'] : NULL,
			':ist_cardname' => isset($Data['ist_cardname']) ? $Data['ist_cardname'] : NULL,
			':ist_currency' => isset($Data['ist_currency']) ? $Data['ist_currency'] : NULL,
			':ist_contacid' => isset($Data['ist_contacid']) ? $Data['ist_contacid'] : NULL,
			':ist_slpcode' => is_numeric($Data['ist_slpcode']) ? $Data['ist_slpcode'] : 0,
			':ist_empid' => is_numeric($Data['ist_empid']) ? $Data['ist_empid'] : 0,
			':ist_comment' => isset($Data['ist_comment']) ? $Data['ist_comment'] : NULL,
			':ist_doctotal' => is_numeric($Data['ist_doctotal']) ? $Data['ist_doctotal'] : 0,
			':ist_baseamnt' => is_numeric($Data['ist_baseamnt']) ? $Data['ist_baseamnt'] : 0,
			':ist_taxtotal' => is_numeric($Data['ist_taxtotal']) ? $Data['ist_taxtotal'] : 0,
			':ist_discprofit' => is_numeric($Data['ist_discprofit']) ? $Data['ist_discprofit'] : 0,
			':ist_discount' => is_numeric($Data['ist_discount']) ? $Data['ist_discount'] : 0,
			':ist_createat' => $this->validateDate($Data['ist_createat']) ? $Data['ist_createat'] : NULL,
			':ist_baseentry' => is_numeric($Data['ist_baseentry']) ? $Data['ist_baseentry'] : 0,
			':ist_basetype' => is_numeric($Data['ist_basetype']) ? $Data['ist_basetype'] : 0,
			':ist_doctype' => is_numeric($Data['ist_doctype']) ? $Data['ist_doctype'] : 0,
			':ist_idadd' => isset($Data['ist_idadd']) ? $Data['ist_idadd'] : NULL,
			':ist_adress' => isset($Data['ist_adress']) ? $Data['ist_adress'] : NULL,
			':ist_paytype' => is_numeric($Data['ist_paytype']) ? $Data['ist_paytype'] : 0,
			':ist_createby' => isset($Data['ist_createby']) ? $Data['ist_createby'] : NULL,
			':business' => $Data['business'],
			':branch' => $Data['branch'],
			':ist_user_m' => isset($Data['ist_user_m']) ?  $Data['ist_user_m']: NULL,
			':ist_activity_type' => isset($Data['ist_activity_type']) ?  $Data['ist_activity_type']: NULL
		));

		if (is_numeric($resInsert) && $resInsert > 0) {

			// Se actualiza la serie de la numeracion del documento

			$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
										 WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id'      => $Data['ist_series']
			));


			if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
			} else {
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'    => $resActualizarNumeracion,
					'mensaje'	=> 'No se pudo crear la solicitud'
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
				':bed_doctype' => $Data['ist_doctype'],
				':bed_status' => 1, //ESTADO ABIERTO
				':bed_createby' => $Data['ist_createby'],
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
					'mensaje'	=> 'No se pudo registrar la solicitud 1'
				);


				$this->response($respuesta);

				return;
			}


			// SE CIERRA EL DOCUMENTO PRELIMINAR SI VIENE DE UN MODELO DE APROBACION
			// SI EL DOCTYPE = 21
			if ($Data['ist_basetype'] == 21) {


				// SE VALIDA SI HAY ANEXOS EN EL DOCUMENTO APROBADO 
				// SE CAMBIEN AL DOCUMENTO EN CREACION
				$anexo = $this->aprobacion->CambiarAnexos($Data,'ist',$DocNumVerificado);
	
				if ( isset($anexo['error']) && $anexo['error'] == false ) {
				}else{

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $anexo,
						'mensaje'	=> 'No se pudo registrar el documento:'. $anexo['mensaje']
					);


					return $this->response($respuesta);

				}
				// FIN VALIDACION

				$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
															VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

				$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(
					':bed_docentry' => $Data['ist_baseentry'],
					':bed_doctype' => $Data['ist_basetype'],
					':bed_status' => 3, //ESTADO CERRADO
					':bed_createby' => $Data['ist_createby'],
					':bed_date' => date('Y-m-d'),
					':bed_baseentry' => $resInsert,
					':bed_basetype' => $Data['ist_doctype']
				));


				if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
				} else {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertEstado,
						'mensaje'	=> 'No se pudo registrar la transferencia',
						'proceso' => 'Insertar estado documento'
					);


					$this->response($respuesta);

					return;
				}
			}
			//FIN SE CIERRA EL DOCUMENTO PRELIMINAR SI VIENE DE UN MODELO DE APROBACION

			//FIN PROCESO ESTADO DEL DOCUMENTO

			foreach ($ContenidoDetalle as $key => $detail) {

				$sqlInsertDetail = "INSERT INTO ist1(st1_docentry, st1_itemcode, st1_itemname, st1_quantity, st1_uom, st1_whscode,
													st1_price, st1_vat, st1_vatsum, st1_discount, st1_linetotal, st1_costcode, st1_ubusiness, st1_project,
													st1_acctcode, st1_basetype, st1_doctype, st1_avprice, st1_inventory, st1_acciva, st1_whscode_dest,ote_code,
													st1_linenum,st1_ubication2,st1_ubication, st1_serials)
													VALUES(:st1_docentry, :st1_itemcode, :st1_itemname, :st1_quantity,:st1_uom, :st1_whscode,:st1_price, :st1_vat, 
													:st1_vatsum, :st1_discount, :st1_linetotal, :st1_costcode, :st1_ubusiness, :st1_project,:st1_acctcode, 
													:st1_basetype, :st1_doctype, :st1_avprice, :st1_inventory, :st1_acciva, :st1_whscode_dest,:ote_code,
													:st1_linenum,:st1_ubication2,:st1_ubication, :st1_serials)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':st1_docentry' => $resInsert,
					':st1_itemcode' => isset($detail['st1_itemcode']) ? $detail['st1_itemcode'] : NULL,
					':st1_itemname' => isset($detail['st1_itemname']) ? $detail['st1_itemname'] : NULL,
					':st1_quantity' => is_numeric($detail['st1_quantity']) ? $detail['st1_quantity'] : 0,
					':st1_uom' => isset($detail['st1_uom']) ? $detail['st1_uom'] : NULL,
					':st1_whscode' => isset($detail['st1_whscode']) ? $detail['st1_whscode'] : NULL,
					':st1_price' => is_numeric($detail['st1_price']) ? $detail['st1_price'] : 0,
					':st1_vat' => is_numeric($detail['st1_vat']) ? $detail['st1_vat'] : 0,
					':st1_vatsum' => is_numeric($detail['st1_vatsum']) ? $detail['st1_vatsum'] : 0,
					':st1_discount' => is_numeric($detail['st1_discount']) ? $detail['st1_discount'] : 0,
					':st1_linetotal' => is_numeric($detail['st1_linetotal']) ? $detail['st1_linetotal'] : 0,
					':st1_costcode' => isset($detail['st1_costcode']) ? $detail['st1_costcode'] : NULL,
					':st1_ubusiness' => isset($detail['st1_ubusiness']) ? $detail['st1_ubusiness'] : NULL,
					':st1_project' => isset($detail['st1_project']) ? $detail['st1_project'] : NULL,
					':st1_acctcode' => is_numeric($detail['st1_acctcode']) ? $detail['st1_acctcode'] : 0,
					':st1_basetype' => is_numeric($detail['st1_basetype']) ? $detail['st1_basetype'] : 0,
					':st1_doctype' => is_numeric($detail['st1_doctype']) ? $detail['st1_doctype'] : 0,
					':st1_avprice' => is_numeric($detail['st1_avprice']) ? $detail['st1_avprice'] : 0,
					':st1_inventory' => is_numeric($detail['st1_inventory']) ? $detail['st1_inventory'] : NULL,
					':st1_acciva' => is_numeric($detail['st1_acciva']) ? $detail['st1_acciva'] : NULL,
					':st1_whscode_dest' => isset($detail['st1_whscode_dest']) ? $detail['st1_whscode_dest'] : NULL,
					':ote_code' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
					':st1_linenum' => is_numeric($detail['st1_linenum']) ? $detail['st1_linenum'] : NULL,
					':st1_ubication2' => isset($detail['st1_ubication2']) ? $detail['st1_ubication2'] : NULL,
					':st1_ubication' => isset($detail['st1_ubication']) ? $detail['st1_ubication'] : NULL,
					':st1_serials' => isset($detail['serials']) ? join(",",$detail['serials']) : NULL
				));

				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle de el pedido se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertDetail,
						'mensaje'	=> 'No se pudo registrar la solicitud 2'
					);

					$this->response($respuesta);

					return;
				}
			}


			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'Solicitud de transferencia de stock #'.$DocNumVerificado.' registrada con exito'
			);
		} else {
			// Se devuelven los cambios realizados en la transaccion
			// si occurre un error  y se muestra devuelve el error.
			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data' => $resInsert,
				'mensaje'	=> 'No se pudo registrar la solicitud 3'
			);
		}

		$this->response($respuesta);
	}

	//OBTENER SOLICITUD DE TRASLADO
	public function getSolStockTransfer_get()
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

		$sqlSelect = self::getColumn('dist', 'ist', $campos, '', $DECI_MALES, $Data['business'], $Data['branch'],23);
		// print_r($sqlSelect);exit;
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

	//CREAR NUEVA TRANSFERENCIA DE STOCKS
	public function createStockTransfer_post()
	{

		$Data = $this->post();
		$DocNumVerificado = 0;
		$DetalleInventario = new stdClass();
		$DetalleInventario2 = new stdClass();
		$DetalleConsolidadoInventario = [];
		$DetalleConsolidadoInventario2 = [];
		$inArrayInventario = array();
		$inArrayInventario2 = array();
		$posicionInventario = 0;
		$posicionInventario2 = 0;
		$llaveInventario = "";
		$llaveInventario2 = "";
		$ManejaSerial = 0;
		$ManejaUbicacion = 0;
		$ManejaUbicacion2 = 0;

		$CANTUOMPURCHASE = 0; //CANTIDAD EN UNIDAD DE MEDIDA COMPRAS
		$CANTUOMSALE = 0; // CANTIDAD EN UNIDAD DE MEDIDA VENTAS

		$DECI_MALES =  $this->generic->getDecimals();

		$TasaDocLoc = 0;
		$TasaLocSys = 0;
		$MONEDALOCAL = "";
		$MONEDASYS = "";

		// Se globaliza la variable sqlDetalleAsiento
		$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
							ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
							ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
							ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, business, branch)VALUES (:ac1_trans_id, :ac1_account,
							:ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
							:ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
							:ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
							:ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref, :business, :branch)";

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
				'mensaje' => 'No se encontro el detalle de la cotización'
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
		//VALIDANDO PERIODO CONTABLE
		$periodo = $this->generic->ValidatePeriod($Data['its_docdate'], $Data['its_docdate'], $Data['its_docdate'], 0);

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
        $DocNumVerificado = $this->documentnumbering->NumberDoc($Data['its_series'],$Data['its_docdate'],$Data['its_duedate']);
		
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
		$dataTasa = $this->tasa->Tasa($Data['its_currency'],$Data['its_docdate']);

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



		$sqlInsert = "INSERT INTO dits(its_series, its_docnum, its_docdate, its_duedate, its_duedev, its_pricelits, its_cardcode,
		        its_cardname, its_currency, its_contacid, its_slpcode, its_empid, its_comment, its_doctotal, its_baseamnt, its_taxtotal,
		        its_discprofit, its_discount, its_createat, its_baseentry, its_basetype, its_doctype, its_idadd, its_adress, its_paytype,
		        its_createby, business, branch)VALUES(:its_series, :its_docnum, :its_docdate, :its_duedate, :its_duedev, :its_pricelits, :its_cardcode, :its_cardname,
		        :its_currency, :its_contacid, :its_slpcode, :its_empid, :its_comment, :its_doctotal, :its_baseamnt, :its_taxtotal, :its_discprofit, :its_discount,
		        :its_createat, :its_baseentry, :its_basetype, :its_doctype, :its_idadd, :its_adress, :its_paytype,:its_createby, :business, :branch)";

		// SE INICIA TRANSACCION

		$this->pedeo->trans_begin();

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			':its_docnum' => $DocNumVerificado,
			':its_series' => is_numeric($Data['its_series']) ? $Data['its_series'] : 0,
			':its_docdate' => $this->validateDate($Data['its_docdate']) ? $Data['its_docdate'] : NULL,
			':its_duedate' => $this->validateDate($Data['its_duedate']) ? $Data['its_duedate'] : NULL,
			':its_duedev' => $this->validateDate($Data['its_duedev']) ? $Data['its_duedev'] : NULL,
			':its_pricelits' => 0,
			':its_cardcode' => isset($Data['its_cardcode']) ? $Data['its_cardcode'] : NULL,
			':its_cardname' => isset($Data['its_cardname']) ? $Data['its_cardname'] : NULL,
			':its_currency' => isset($Data['its_currency']) ? $Data['its_currency'] : NULL,
			':its_contacid' => isset($Data['its_contacid']) ? $Data['its_contacid'] : NULL,
			':its_slpcode' => is_numeric($Data['its_slpcode']) ? $Data['its_slpcode'] : 0,
			':its_empid' => is_numeric($Data['its_empid']) ? $Data['its_empid'] : 0,
			':its_comment' => isset($Data['its_comment']) ? $Data['its_comment'] : NULL,
			':its_doctotal' => is_numeric($Data['its_doctotal']) ? $Data['its_doctotal'] : 0,
			':its_baseamnt' => is_numeric($Data['its_baseamnt']) ? $Data['its_baseamnt'] : 0,
			':its_taxtotal' => is_numeric($Data['its_taxtotal']) ? $Data['its_taxtotal'] : 0,
			':its_discprofit' => is_numeric($Data['its_discprofit']) ? $Data['its_discprofit'] : 0,
			':its_discount' => is_numeric($Data['its_discount']) ? $Data['its_discount'] : 0,
			':its_createat' => $this->validateDate($Data['its_createat']) ? $Data['its_createat'] : NULL,
			':its_baseentry' => is_numeric($Data['its_baseentry']) ? $Data['its_baseentry'] : 0,
			':its_basetype' => is_numeric($Data['its_basetype']) ? $Data['its_basetype'] : 0,
			':its_doctype' => is_numeric($Data['its_doctype']) ? $Data['its_doctype'] : 0,
			':its_idadd' => isset($Data['its_idadd']) ? $Data['its_idadd'] : NULL,
			':its_adress' => isset($Data['its_adress']) ? $Data['its_adress'] : NULL,
			':its_paytype' => is_numeric($Data['its_paytype']) ? $Data['its_paytype'] : 0,
			':its_createby' => isset($Data['its_createby']) ? $Data['its_createby'] : NULL,
			':business' => $Data['business'],
			':branch'   => $Data['branch']
		));

		if (is_numeric($resInsert) && $resInsert > 0) {

			// Se actualiza la serie de la numeracion del documento

			$sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user,mac_accperiod,business,branch)
															 VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user,:mac_accperiod,:business,:branch)";

			$resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

				':mac_doc_num' => 1,
				':mac_status' => 1,
				':mac_base_type' => is_numeric($Data['its_doctype']) ? $Data['its_doctype'] : 0,
				':mac_base_entry' => $resInsert,
				':mac_doc_date' => $this->validateDate($Data['its_docdate']) ? $Data['its_docdate'] : NULL,
				':mac_doc_duedate' => $this->validateDate($Data['its_duedate']) ? $Data['its_duedate'] : NULL,
				':mac_legal_date' => $this->validateDate($Data['its_docdate']) ? $Data['its_docdate'] : NULL,
				':mac_ref1' => is_numeric($Data['its_doctype']) ? $Data['its_doctype'] : 0,
				':mac_ref2' => "",
				':mac_ref3' => "",
				':mac_loc_total' => is_numeric($Data['its_doctotal']) ? $Data['its_doctotal'] : 0,
				':mac_fc_total' => is_numeric($Data['its_doctotal']) ? $Data['its_doctotal'] : 0,
				':mac_sys_total' => is_numeric($Data['its_doctotal']) ? $Data['its_doctotal'] : 0,
				':mac_trans_dode' => 1,
				':mac_beline_nume' => 1,
				':mac_vat_date' => $this->validateDate($Data['its_docdate']) ? $Data['its_docdate'] : NULL,
				':mac_serie' => 1,
				':mac_number' => 1,
				':mac_bammntsys' => is_numeric($Data['its_baseamnt']) ? $Data['its_baseamnt'] : 0,
				':mac_bammnt' => is_numeric($Data['its_baseamnt']) ? $Data['its_baseamnt'] : 0,
				':mac_wtsum' => 1,
				':mac_vatsum' => is_numeric($Data['its_taxtotal']) ? $Data['its_taxtotal'] : 0,
				':mac_comments' => isset($Data['its_comment']) ? $Data['its_comment'] : NULL,
				':mac_create_date' => $this->validateDate($Data['its_createat']) ? $Data['its_createat'] : NULL,
				':mac_made_usuer' => isset($Data['its_createby']) ? $Data['its_createby'] : NULL,
				':mac_update_date' => date("Y-m-d"),
				':mac_update_user' => isset($Data['its_createby']) ? $Data['its_createby'] : NULL,
				':mac_accperiod' => $periodo['data'],
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

			$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																		 WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id'      => $Data['its_series']
			));


			if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
			} else {
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'    => $resActualizarNumeracion,
					'mensaje'	=> 'No se pudo crear la solicitud'
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
				':bed_doctype' => $Data['its_doctype'],
				':bed_status' => 1, //ESTADO ABIERTO
				':bed_createby' => $Data['its_createby'],
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
					'mensaje'	=> 'No se pudo registrar la solicitud 1'
				);


				$this->response($respuesta);

				return;
			}


			// SE CIERRA LA SOLICTUD DE TRASLADO CREADA Y APROBADA
			// SI EL DOCTYPE = 23
			if ($Data['its_basetype'] == 23) {

				$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
									VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

				$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


					':bed_docentry' => $Data['its_baseentry'],
					':bed_doctype' => $Data['its_basetype'],
					':bed_status' => 3, //ESTADO CERRADO
					':bed_createby' => $Data['its_createby'],
					':bed_date' => date('Y-m-d'),
					':bed_baseentry' => $resInsert,
					':bed_basetype' => $Data['its_doctype']
				));


				if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
				} else {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertEstado,
						'mensaje'	=> 'No se pudo registrar la solicutd',
						'proceso' => 'Insertar estado documento'
					);


					$this->response($respuesta);

					return;
				}
			}
			//FIN SE CIERRA EL DOCUMENTO PRELIMINAR SI VIENE DE UN MODELO DE APROBACION

			//FIN PROCESO ESTADO DEL DOCUMENTO

			foreach ($ContenidoDetalle as $key => $detail) {


				$CANTUOMPURCHASE = $this->generic->getUomPurchase($detail['ts1_itemcode']);
				$CANTUOMSALE = $this->generic->getUomSale($detail['ts1_itemcode']);



				if ($CANTUOMPURCHASE == 0 || $CANTUOMSALE == 0) {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' 		=> $detail['ts1_itemcode'],
						'mensaje'	=> 'No se encontro la equivalencia de la unidad de medida para el item: ' . $detail['ts1_itemcode']
					);

					$this->response($respuesta);

					return;
				}

				$sqlInsertDetail = "INSERT INTO its1(ts1_docentry, ts1_itemcode, ts1_itemname, ts1_quantity, ts1_uom, ts1_whscode,
							                    ts1_price, ts1_vat, ts1_vatsum, ts1_discount, ts1_linetotal, ts1_costcode, ts1_ubusiness, ts1_project,
							                    ts1_acctcode, ts1_basetype, ts1_doctype, ts1_avprice, ts1_inventory, ts1_acciva, ts1_whscode_dest,ote_code,
												ts1_linenum,ts1_baseline,ts1_ubication2,ts1_ubication)
												VALUES(:ts1_docentry, :ts1_itemcode, :ts1_itemname, :ts1_quantity,:ts1_uom, :ts1_whscode,:ts1_price, :ts1_vat, 
												:ts1_vatsum, :ts1_discount, :ts1_linetotal, :ts1_costcode, :ts1_ubusiness, :ts1_project,:ts1_acctcode, 
												:ts1_basetype, :ts1_doctype, :ts1_avprice, :ts1_inventory, :ts1_acciva, :ts1_whscode_dest,:ote_code,
												:ts1_linenum,:ts1_baseline,:ts1_ubication2,:ts1_ubication)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':ts1_docentry' => $resInsert,
					':ts1_itemcode' => isset($detail['ts1_itemcode']) ? $detail['ts1_itemcode'] : NULL,
					':ts1_itemname' => isset($detail['ts1_itemname']) ? $detail['ts1_itemname'] : NULL,
					':ts1_quantity' => is_numeric($detail['ts1_quantity']) ? $detail['ts1_quantity'] : 0,
					':ts1_uom' => isset($detail['ts1_uom']) ? $detail['ts1_uom'] : NULL,
					':ts1_whscode' => isset($detail['ts1_whscode']) ? $detail['ts1_whscode'] : NULL,
					':ts1_price' => is_numeric($detail['ts1_price']) ? $detail['ts1_price'] : 0,
					':ts1_vat' => is_numeric($detail['ts1_vat']) ? $detail['ts1_vat'] : 0,
					':ts1_vatsum' => is_numeric($detail['ts1_vatsum']) ? $detail['ts1_vatsum'] : 0,
					':ts1_discount' => is_numeric($detail['ts1_discount']) ? $detail['ts1_discount'] : 0,
					':ts1_linetotal' => is_numeric($detail['ts1_linetotal']) ? $detail['ts1_linetotal'] : 0,
					':ts1_costcode' => isset($detail['ts1_costcode']) ? $detail['ts1_costcode'] : NULL,
					':ts1_ubusiness' => isset($detail['ts1_ubusiness']) ? $detail['ts1_ubusiness'] : NULL,
					':ts1_project' => isset($detail['ts1_project']) ? $detail['ts1_project'] : NULL,
					':ts1_acctcode' => is_numeric($detail['ts1_acctcode']) ? $detail['ts1_acctcode'] : 0,
					':ts1_basetype' => is_numeric($detail['ts1_basetype']) ? $detail['ts1_basetype'] : 0,
					':ts1_doctype' => is_numeric($detail['ts1_doctype']) ? $detail['ts1_doctype'] : 0,
					':ts1_avprice' => is_numeric($detail['ts1_avprice']) ? $detail['ts1_avprice'] : 0,
					':ts1_inventory' => is_numeric($detail['ts1_inventory']) ? $detail['ts1_inventory'] : NULL,
					':ts1_acciva' => is_numeric($detail['ts1_acciva']) ? $detail['ts1_acciva'] : NULL,
					':ts1_whscode_dest' => isset($detail['ts1_whscode_dest']) ? $detail['ts1_whscode_dest'] : NULL,
					':ote_code' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
					':ts1_linenum' => is_numeric($detail['ts1_linenum']) ? $detail['ts1_linenum'] : 0,
					':ts1_baseline' => is_numeric($detail['ts1_baseline']) ? $detail['ts1_baseline'] : 0,
					':ts1_ubication2' => isset($detail['ts1_ubication2']) ? $detail['ts1_ubication2'] : NULL,
					':ts1_ubication' => isset($detail['ts1_ubication']) ? $detail['ts1_ubication'] : NULL
				));

				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle de el pedido se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertDetail,
						'mensaje'	=> 'No se pudo registrar la transferencia'
					);

					$this->response($respuesta);

					return;
				}




				// VALIDAR PROCESO DE SALIDA


				//SE VERIFICA SI EL ARTICULO MANEJA SERIAL
				$sqlItemSerial = "SELECT dma_series_code FROM dmar WHERE  dma_item_code = :dma_item_code AND dma_series_code = :dma_series_code";
				$resItemSerial = $this->pedeo->queryTable($sqlItemSerial, array(

					':dma_item_code' => $detail['ts1_itemcode'],
					':dma_series_code'  => 1
				));

				if (isset($resItemSerial[0])) {
					$ManejaSerial = 1;

					$AddSerial = $this->generic->addSerial($detail['serials'], $detail['ts1_itemcode'], $Data['its_doctype'], $resInsert, $DocNumVerificado, $Data['its_docdate'], 1, $Data['its_comment'], $detail['ts1_whscode'], $detail['ts1_quantity'], $Data['its_createby'], $Data['business']);

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

				// CONSULTA PARA VERIFICAR SI EL ALMACEN MANEJA UBICACION
				$sqlubicacion = "SELECT * FROM dmws WHERE dws_ubication = :dws_ubication AND dws_code = :dws_code AND business = :business";
				$resubicacion = $this->pedeo->queryTable($sqlubicacion, array(
					':dws_ubication' => 1,
					':dws_code' => $detail['ts1_whscode'],
					':business' => $Data['business']
				));

				$sqlubicacion2 = "SELECT * FROM dmws WHERE dws_ubication = :dws_ubication AND dws_code = :dws_code AND business = :business";
				$resubicacion2 = $this->pedeo->queryTable($sqlubicacion, array(
					':dws_ubication' => 1,
					':dws_code' => $detail['ts1_whscode_dest'],
					':business' => $Data['business']
				));





				if ( isset($resubicacion[0]) ){
					$ManejaUbicacion = 1;
				}else{
					$ManejaUbicacion = 0;
				}


				if ( isset($resubicacion2[0]) ){
					$ManejaUbicacion2 = 1;
				}else{
					$ManejaUbicacion2 = 0;
				}


				//SE VALIDA EL SI EL ARTICULO MANEJA LOTE
				$sqlLote = "SELECT dma_lotes_code FROM dmar WHERE dma_item_code = :dma_item_code AND dma_lotes_code = :dma_lotes_code";
				$resLote = $this->pedeo->queryTable($sqlLote, array(

					':dma_item_code' => $detail['ts1_itemcode'],
					':dma_lotes_code'  => 1
				));

				if (isset($resLote[0])) {
					$ManejaLote = 1;
				} else {
					$ManejaLote = 0;
				}

				//se busca el costo del item en el momento de la creacion del documento de venta
				// para almacenar en el movimiento de inventario
				$sqlCostoMomentoRegistro = '';
				$resCostoMomentoRegistro = [];

				// SI MANEJA UBICACION EL ALMACEN ORIGEN
				if ( $ManejaUbicacion == 1 ){
					//SI MANEJA LOTE EL ARTICULO
					if ( $ManejaLote == 1 ) {

						$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_lote = :bdi_lote AND bdi_ubication = :bdi_ubication AND business = :business";
						$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
							':bdi_whscode'  => $detail['ts1_whscode'],
							':bdi_itemcode' => $detail['ts1_itemcode'],
							':bdi_lote' 	=> $detail['ote_code'],
							':bdi_ubication'=> $detail['ts1_ubication'],
							':business' 	=> $Data['business']
						));

					}else{
						$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_ubication = :bdi_ubication AND business = :business";
						$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
							':bdi_whscode'  => $detail['ts1_whscode'],
							':bdi_itemcode' => $detail['ts1_itemcode'],
							':bdi_ubication'=> $detail['ts1_ubication'],
							':business' 	=> $Data['business']
						));
					}

				}else{
					//SI MANEJA LOTE EL ARTICULO

					if ( $ManejaLote == 1 ){

						$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_lote = :bdi_lote AND business = :business";
						$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
							':bdi_whscode'  => $detail['ts1_whscode'],
							':bdi_itemcode' => $detail['ts1_itemcode'],
							':bdi_lote' 	=> $detail['ote_code'],
							':business' 	=> $Data['business']
						));

					}else{

						$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND business = :business";
						$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(':bdi_whscode' => $detail['ts1_whscode'], ':bdi_itemcode' => $detail['ts1_itemcode'], ':business' => $Data['business']));
						
					}

				}


				if (isset($resCostoMomentoRegistro[0])) {
					//VALIDANDO CANTIDAD DE ARTICULOS

					$CANT_ARTICULOEX = $resCostoMomentoRegistro[0]['bdi_quantity'];
					$CANT_ARTICULOLN =  $this->generic->getCantInv($detail['ts1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE);

					if (($CANT_ARTICULOEX - $CANT_ARTICULOLN) < 0) {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' => [],
							'mensaje'	=> 'no puede crear el documento porque el articulo ' . $detail['ts1_itemcode'] . ' recae en inventario negativo (' . ($CANT_ARTICULOEX - $CANT_ARTICULOLN) . ')'
						);

						$this->response($respuesta);

						return;
					}
				}else{

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'    => $resCostoMomentoRegistro,
						'mensaje' => 'No se encontro el costo del articulo'
					);

					$this->response($respuesta);

					return;
				}

				//Se aplica el movimiento de inventario para el almacen destino
				$sqlInserMovimiento = '';
				$resInserMovimiento = [];
			
				$sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode,bmi_quantity,bmi_whscode,bmi_createat,bmi_createby,bmy_doctype,bmy_baseentry,bmi_cost,bmi_currequantity,bmi_basenum,bmi_docdate,bmi_duedate,bmi_duedev,bmi_comment,bmi_lote,bmi_ubication)
									VALUES (:bmi_itemcode,:bmi_quantity, :bmi_whscode,:bmi_createat,:bmi_createby,:bmy_doctype,:bmy_baseentry,:bmi_cost,:bmi_currequantity,:bmi_basenum,:bmi_docdate,:bmi_duedate,:bmi_duedev,:bmi_comment,:bmi_lote,:bmi_ubication)";

				$resInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(

					':bmi_itemcode' => isset($detail['ts1_itemcode']) ? $detail['ts1_itemcode'] : NULL,
					':bmi_quantity' => $this->generic->getCantInv($detail['ts1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE) * 1,
					':bmi_whscode'  => isset($detail['ts1_whscode_dest']) ? $detail['ts1_whscode_dest'] : NULL,
					':bmi_createat' => $this->validateDate($Data['its_createat']) ? $Data['its_createat'] : NULL,
					':bmi_createby' => isset($Data['its_createby']) ? $Data['its_createby'] : NULL,
					':bmy_doctype'  => is_numeric($Data['its_doctype']) ? $Data['its_doctype'] : 0,
					':bmy_baseentry' => $resInsert,
					':bmi_cost'      => $resCostoMomentoRegistro[0]['bdi_avgprice'],
					':bmi_currequantity' 	=> $resCostoMomentoRegistro[0]['bdi_quantity'],
					':bmi_basenum'			=> $DocNumVerificado,
					':bmi_docdate' => $this->validateDate($Data['its_docdate']) ? $Data['its_docdate'] : NULL,
					':bmi_duedate' => $this->validateDate($Data['its_duedate']) ? $Data['its_duedate'] : NULL,
					':bmi_duedev'  => $this->validateDate($Data['its_duedev']) ? $Data['its_duedev'] : NULL,
					':bmi_comment' => isset($Data['its_comment']) ? $Data['its_comment'] : NULL,
					':bmi_lote' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
					':bmi_ubication' => isset($detail['its_ubication']) ? $detail['its_ubication'] : NULL

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


				//Se aplica el movimiento de inventario para el almacen origen
				$sqlInserMovimiento = '';
				$resInserMovimiento = [];
	
				$sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode,bmi_quantity,bmi_whscode,bmi_createat,bmi_createby,bmy_doctype,bmy_baseentry,bmi_cost,bmi_currequantity,bmi_basenum,bmi_docdate,bmi_duedate,bmi_duedev,bmi_comment,bmi_lote,bmi_ubication)
										VALUES (:bmi_itemcode,:bmi_quantity, :bmi_whscode,:bmi_createat,:bmi_createby,:bmy_doctype,:bmy_baseentry,:bmi_cost,:bmi_currequantity,:bmi_basenum,:bmi_docdate,:bmi_duedate,:bmi_duedev,:bmi_comment,:bmi_lote,:bmi_ubication)";

				$resInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(

					':bmi_itemcode' => isset($detail['ts1_itemcode']) ? $detail['ts1_itemcode'] : NULL,
					':bmi_quantity' => $this->generic->getCantInv($detail['ts1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE) * -1,
					':bmi_whscode'  => isset($detail['ts1_whscode']) ? $detail['ts1_whscode'] : NULL,
					':bmi_createat' => $this->validateDate($Data['its_createat']) ? $Data['its_createat'] : NULL,
					':bmi_createby' => isset($Data['its_createby']) ? $Data['its_createby'] : NULL,
					':bmy_doctype'  => is_numeric($Data['its_doctype']) ? $Data['its_doctype'] : 0,
					':bmy_baseentry' => $resInsert,
					':bmi_cost'      => $resCostoMomentoRegistro[0]['bdi_avgprice'],
					':bmi_currequantity' 	=> $resCostoMomentoRegistro[0]['bdi_quantity'],
					':bmi_basenum'			=> $DocNumVerificado,
					':bmi_docdate' => $this->validateDate($Data['its_docdate']) ? $Data['its_docdate'] : NULL,
					':bmi_duedate' => $this->validateDate($Data['its_duedate']) ? $Data['its_duedate'] : NULL,
					':bmi_duedev'  => $this->validateDate($Data['its_duedev']) ? $Data['its_duedev'] : NULL,
					':bmi_comment' => isset($Data['its_comment']) ? $Data['its_comment'] : NULL,
					':bmi_lote' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
					':bmi_ubication' => isset($detail['its_ubication']) ? $detail['its_ubication'] : NULL

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

				// HACIENDO ENTRADA DE STOCK EN ALMACEN DESTINO
				//SE VALIDA SI EL ARTICULO MANEJA LOTE
				$ProductoDestino = '';
				$ResProductoDestino = '';

				if ( $ManejaUbicacion2 ==  1) {

					if ($ManejaLote == 1) {

						$ProductoDestino = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
											FROM tbdi
											WHERE bdi_itemcode = :bdi_itemcode
											AND bdi_whscode = :bdi_whscode
											AND bdi_lote = :bdi_lote
											AND bdi_ubication = :bdi_ubication
											AND business = :business";
	
						$ResProductoDestino = $this->pedeo->queryTable($ProductoDestino, array(
							':bdi_itemcode' => $detail['ts1_itemcode'],
							':bdi_whscode'  => $detail['ts1_whscode_dest'],
							':bdi_lote' 	=> $detail['ote_code'],
							':bdi_ubication'=> $detail['ts1_ubication2'],
							':business' 	=> $Data['business']
						));

					}else{
						$ProductoDestino = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
						FROM tbdi
						WHERE bdi_itemcode = :bdi_itemcode
						AND bdi_whscode = :bdi_whscode
						AND bdi_ubication = :bdi_ubication
						AND business = :business";

						$ResProductoDestino = $this->pedeo->queryTable($ProductoDestino, array(

							':bdi_itemcode' => $detail['ts1_itemcode'],
							':bdi_whscode'  => $detail['ts1_whscode_dest'],
							':bdi_ubication'=> $detail['ts1_ubication2'],
							':business' 	=> $Data['business']

						));
					}

				} else {

					if ($ManejaLote == 1) {

						$ProductoDestino = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
											FROM tbdi
											WHERE bdi_itemcode = :bdi_itemcode
											AND bdi_whscode = :bdi_whscode
											AND bdi_lote = :bdi_lote
											AND business = :business";
	
						$ResProductoDestino = $this->pedeo->queryTable($ProductoDestino, array(
	
							':bdi_itemcode' => $detail['ts1_itemcode'],
							':bdi_whscode'  => $detail['ts1_whscode_dest'],
							':bdi_lote' 	=> $detail['ote_code'],
							':business' 	=> $Data['business']
						));

					} else {

						$ProductoDestino = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
											FROM tbdi
											WHERE bdi_itemcode = :bdi_itemcode
											AND bdi_whscode = :bdi_whscode
											AND business = :business";
	
						$ResProductoDestino = $this->pedeo->queryTable($ProductoDestino, array(
	
							':bdi_itemcode' => $detail['ts1_itemcode'],
							':bdi_whscode'  => $detail['ts1_whscode_dest'],
							':business' 	=> $Data['business']
						));

					}

				}

			


				// SI EXISTE EN EL STOCK
				if (isset($ResProductoDestino[0])) {

					$sqlUpdateProductoEnAlmacen = "UPDATE tbdi
												SET bdi_quantity = bdi_quantity + :bdi_quantity
												WHERE  bdi_id = :bdi_id";

					$resUpdateProductoEnAlmacen = $this->pedeo->updateRow($sqlUpdateProductoEnAlmacen, array(

						':bdi_quantity' =>  $this->generic->getCantInv($detail['ts1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
						':bdi_id' 		=>  $ResProductoDestino[0]['bdi_id']
					));

					if (is_numeric($resUpdateProductoEnAlmacen) && $resUpdateProductoEnAlmacen == 1) {
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'    => $resUpdateProductoEnAlmacen,
							'mensaje'	=> 'No se pudo registrar el movimiento en el stock'
						);


						$this->response($respuesta);

						return;
					}
				} else { 

					$sqlInsertProductoAlmacenDestino = "";
					$resInsertProductoAlmacenDestino = [];
					//SI NO EXISTE EN EL INVENTARIO
					// SI EL ALMACEN DESTINO MANEJA UBICACION
					if ( $ManejaUbicacion2 == 1 ) {


						if ($ManejaLote == 1) {

							$sqlInsertProductoAlmacenDestino = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_lote, business,bdi_ubication)
																VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_lote, :business,:bdi_ubication)";
	
	
							$resInsertProductoAlmacenDestino	= $this->pedeo->insertRow($sqlInsertProductoAlmacenDestino, array(
	
								':bdi_itemcode' => $detail['ts1_itemcode'],
								':bdi_whscode'  => $detail['ts1_whscode_dest'],
								':bdi_quantity' => $this->generic->getCantInv($detail['ts1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
								':bdi_avgprice' => $detail['ts1_price'],
								':bdi_lote' 	=> $detail['ote_code'],
								':bdi_ubication' => $detail['ts1_ubication2'],
								':business' 	=> $Data['business']
							));

						}else{

							$sqlInsertProductoAlmacenDestino = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, business,bdi_ubication)
							VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :business,:bdi_ubication)";


							$resInsertProductoAlmacenDestino	= $this->pedeo->insertRow($sqlInsertProductoAlmacenDestino, array(

							':bdi_itemcode' => $detail['ts1_itemcode'],
							':bdi_whscode'  => $detail['ts1_whscode_dest'],
							':bdi_quantity' => $this->generic->getCantInv($detail['ts1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
							':bdi_avgprice' => $detail['ts1_price'],
							':bdi_ubication' => $detail['ts1_ubication2'],
							':business' 	=> $Data['business']));
						}

					} else {

						if ($ManejaLote == 1) {
							$sqlInsertProductoAlmacenDestino = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_lote, business)
																VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_lote, :business)";
	
	
							$resInsertProductoAlmacenDestino	= $this->pedeo->insertRow($sqlInsertProductoAlmacenDestino, array(
	
								':bdi_itemcode' => $detail['ts1_itemcode'],
								':bdi_whscode'  => $detail['ts1_whscode_dest'],
								':bdi_quantity' => $this->generic->getCantInv($detail['ts1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
								':bdi_avgprice' => $detail['ts1_price'],
								':bdi_lote' 	=> $detail['ote_code'],
								':business' 	=> $Data['business']
							));
						} else {
							$sqlInsertProductoAlmacenDestino = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, business)
																VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :business)";
	
	
							$resInsertProductoAlmacenDestino	= $this->pedeo->insertRow($sqlInsertProductoAlmacenDestino, array(
	
								':bdi_itemcode' => $detail['ts1_itemcode'],
								':bdi_whscode'  => $detail['ts1_whscode_dest'],
								':bdi_quantity' => $this->generic->getCantInv($detail['ts1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
								':bdi_avgprice' => $detail['ts1_price'],
								':business' 	=> $Data['business']
							));
						}
					}
					

					if (is_numeric($resInsertProductoAlmacenDestino) && $resInsertProductoAlmacenDestino > 0) {
						// Se verifica que el detalle no de error insertando //
					} else {

						// si falla algun insert del detalle de la orden de compra se devuelven los cambios realizados por la transaccion,
						// se retorna el error y se detiene la ejecucion del codigo restante.
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' 		=> $resInsertProductoAlmacenDestino,
							'mensaje'	=> 'No se pudo registrar el item en el stock'
						);

						$this->response($respuesta);

						return;
					}
				}
				// FIN ENTRADA DE STOCK EN ALMACEN DESTINO

				//SALIDA DE STOCK EN ALMACEN ORIGEN
				//SI EL ALMACEN DE ORIGEN MANEJA UBICACION
				$sqlUpdateProductoEnAlmacen = "";
				$resUpdateProductoEnAlmacen = [];
				
				if ( $ManejaUbicacion == 1 ){

					if ($ManejaLote == 1) {
						$sqlUpdateProductoEnAlmacen = "UPDATE tbdi
													SET bdi_quantity = bdi_quantity - :bdi_quantity
													WHERE  bdi_itemcode = :bdi_itemcode
													AND bdi_whscode = :bdi_whscode
													AND bdi_lote = :bdi_lote
													AND bdi_ubication = :bdi_ubication
													AND business = :business";

						$resUpdateProductoEnAlmacen = $this->pedeo->updateRow($sqlUpdateProductoEnAlmacen, array(

							':bdi_quantity' =>  $this->generic->getCantInv($detail['ts1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
							':bdi_itemcode' =>  $detail['ts1_itemcode'],
							':bdi_whscode'  =>  $detail['ts1_whscode'],
							':bdi_lote' 	=>  $detail['ote_code'],
							':bdi_ubication' =>  $detail['ts1_ubication'],
							':business' 	=> $Data['business']
						));
					}else{
						$sqlUpdateProductoEnAlmacen = "UPDATE tbdi
													SET bdi_quantity = bdi_quantity - :bdi_quantity
													WHERE  bdi_itemcode = :bdi_itemcode
													AND bdi_whscode = :bdi_whscode
													AND bdi_ubication = :bdi_ubication
													AND business = :business";

						$resUpdateProductoEnAlmacen = $this->pedeo->updateRow($sqlUpdateProductoEnAlmacen, array(

							':bdi_quantity' =>  $this->generic->getCantInv($detail['ts1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
							':bdi_itemcode' =>  $detail['ts1_itemcode'],
							':bdi_whscode'  =>  $detail['ts1_whscode'],
							':bdi_ubication' =>  $detail['ts1_ubication'],
							':business' 	=> $Data['business']
						));
					}

				}else{
					//SE VALIDA SI EL ARTICULO MANEJA LOTE
					if ($ManejaLote == 1) {
						$sqlUpdateProductoEnAlmacen = "UPDATE tbdi
													SET bdi_quantity = bdi_quantity - :bdi_quantity
													WHERE  bdi_itemcode = :bdi_itemcode
													AND bdi_whscode = :bdi_whscode
													AND bdi_lote = :bdi_lote
													AND business = :business";

						$resUpdateProductoEnAlmacen = $this->pedeo->updateRow($sqlUpdateProductoEnAlmacen, array(

							':bdi_quantity' =>  $this->generic->getCantInv($detail['ts1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
							':bdi_itemcode' =>  $detail['ts1_itemcode'],
							':bdi_whscode'  =>  $detail['ts1_whscode'],
							':bdi_lote' 	=>  $detail['ote_code'],
							':business' 	=> $Data['business']
						));
					} else {
						$sqlUpdateProductoEnAlmacen = "UPDATE tbdi
													SET bdi_quantity = bdi_quantity - :bdi_quantity
													WHERE  bdi_itemcode = :bdi_itemcode
													AND bdi_whscode = :bdi_whscode
													AND business = :business";

						$resUpdateProductoEnAlmacen = $this->pedeo->updateRow($sqlUpdateProductoEnAlmacen, array(

							':bdi_quantity' =>  $this->generic->getCantInv($detail['ts1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
							':bdi_itemcode' =>  $detail['ts1_itemcode'],
							':bdi_whscode'  =>  $detail['ts1_whscode'],
							':business' 	=>  $Data['business']
						));
					}
				}




				if (is_numeric($resUpdateProductoEnAlmacen) && $resUpdateProductoEnAlmacen == 1) {
				} else {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'    => $resUpdateProductoEnAlmacen,
						'mensaje'	=> 'No se pudo registrar el movimiento en el stock'
					);


					$this->response($respuesta);

					return;
				}
				//FIN SALIDA DE STOCK EN ALMACEN ORIGEN


				//AGRUPANDO DETALLE DE LOS ASIENTOS CONTABLES

				$DetalleInventario = new stdClass();
				$DetalleInventario2 = new stdClass();

				$DetalleInventario->account = is_numeric($detail['ts1_acctcode']) ? $detail['ts1_acctcode'] : 0;        // Cuenta Contable
				$DetalleInventario->prc_code = isset($detail['ts1_costcode']) ? $detail['ts1_costcode'] : NULL;          // Centro de Costo
				$DetalleInventario->uncode = isset($detail['ts1_ubusiness']) ? $detail['ts1_ubusiness'] : NULL;          // Unidad de negocio
				$DetalleInventario->prj_code = isset($detail['ts1_project']) ? $detail['ts1_project'] : NULL;  			 // Proyecto
				$DetalleInventario->linetotal = is_numeric($detail['ts1_linetotal']) ? $detail['ts1_linetotal'] : 0; // Total Linea
				$DetalleInventario->whscode = isset($detail['ts1_whscode']) ? $detail['ts1_whscode'] : NULL;         // Almacen


				$DetalleInventario2->account = is_numeric($detail['ts1_acctcode']) ? $detail['ts1_acctcode'] : 0;        // Cuenta Contable
				$DetalleInventario2->prc_code = isset($detail['ts1_costcode']) ? $detail['ts1_costcode'] : NULL;          // Centro de Costo
				$DetalleInventario2->uncode = isset($detail['ts1_ubusiness']) ? $detail['ts1_ubusiness'] : NULL;          // Unidad de negocio
				$DetalleInventario2->prj_code = isset($detail['ts1_project']) ? $detail['ts1_project'] : NULL;  			 // Proyecto
				$DetalleInventario2->linetotal = is_numeric($detail['ts1_linetotal']) ? $detail['ts1_linetotal'] : 0; // Total Linea
				$DetalleInventario2->whscode = isset($detail['ts1_whscode']) ? $detail['ts1_whscode'] : NULL;         // Almacen

				$llaveInventario = $DetalleInventario->uncode . $DetalleInventario->prc_code . $DetalleInventario->prj_code . $DetalleInventario->account;
				$llaveInventario2 = $DetalleInventario->uncode . $DetalleInventario->prc_code . $DetalleInventario->prj_code . $DetalleInventario->account;

				if (in_array($llaveInventario, $inArrayInventario)) {

					$posicionInventario = $this->buscarPosicion($llaveInventario, $inArrayInventario);
				} else {

					array_push($inArrayInventario, $llaveInventario);
					$posicionInventario = $this->buscarPosicion($llaveInventario, $inArrayInventario);
				}
				////
				////
				if (in_array($llaveInventario2, $inArrayInventario2)) {

					$posicionInventario2 = $this->buscarPosicion($llaveInventario2, $inArrayInventario2);
				} else {

					array_push($inArrayInventario2, $llaveInventario2);
					$posicionInventario2 = $this->buscarPosicion($llaveInventario2, $inArrayInventario2);
				}
				////*********
				////*********
				if (isset($DetalleConsolidadoInventario[$posicionInventario])) {

					if (!is_array($DetalleConsolidadoInventario[$posicionInventario])) {
						$DetalleConsolidadoInventario[$posicionInventario] = array();
					}
				} else {
					$DetalleConsolidadoInventario[$posicionInventario] = array();
				}

				array_push($DetalleConsolidadoInventario[$posicionInventario], $DetalleInventario);
				////
				////
				if (isset($DetalleConsolidadoInventario2[$posicionInventario2])) {

					if (!is_array($DetalleConsolidadoInventario2[$posicionInventario2])) {
						$DetalleConsolidadoInventario2[$posicionInventario2] = array();
					}
				} else {
					$DetalleConsolidadoInventario2[$posicionInventario2] = array();
				}

				array_push($DetalleConsolidadoInventario2[$posicionInventario2], $DetalleInventario2);
			}

			// EJECUTANDO LLENADO DEL DETALLE DE LOS ASIENTOS CONTABLES

			// DETALLE INVENTARIO 1
			//
			foreach ($DetalleConsolidadoInventario as $key => $posicion) {
				$debito = 0;
				$credito = 0;
				$MontoSysDB = 0;
				$MontoSysCR = 0;
				$cuentaInventario = 0;
				$grantotalAcumulado = 0;
				$CentroCosto = "";
				$UnidadNegocio = "";
				$Proyecto = "";
				// $MontoSysCR = ($detail['ts1_linetotal'] / $TasaLocSys);

				foreach ($posicion as $key => $value) {

					$cuentaInventario = $value->account;
					$grantotalAcumulado = ($grantotalAcumulado + $value->linetotal);

					$CentroCosto = $value->prc_code;
					$UnidadNegocio = $value->uncode;
					$Proyecto = $value->prj_code;
				}

				$MontoSysCR = ($grantotalAcumulado / $TasaLocSys);
				// SE AGREGA AL BALANCE
				$BALANCE = $this->account->addBalance($periodo['data'], round($grantotalAcumulado, $DECI_MALES), $cuentaInventario, 2, $Data['its_docdate'], $Data['business'], $Data['branch']);
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
					':ac1_debit' => 0,
					':ac1_credit' => round($grantotalAcumulado, $DECI_MALES),
					':ac1_debit_sys' => 0,
					':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['its_docdate']) ? $Data['its_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['its_docdate']) ? $Data['its_docdate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => is_numeric($Data['its_doctype']) ? $Data['its_doctype'] : 0,
					':ac1_accountvs' => 1,
					':ac1_doctype' => 18,
					':ac1_ref1' => "",
					':ac1_ref2' => "",
					':ac1_ref3' => "",
					':ac1_prc_code' => $CentroCosto,
					':ac1_uncode' => $UnidadNegocio,
					':ac1_prj_code' => $Proyecto,
					':ac1_rescon_date' => NULL,
					':ac1_recon_total' => 0,
					':ac1_made_user' => isset($Data['its_createby']) ? $Data['its_createby'] : NULL,
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
					':ac1_legal_num' => isset($Data['its_cardcode']) ? $Data['its_cardcode'] : NULL,
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
						'mensaje'	=> 'No se pudo registrar la transferencia de stock'
					);

					$this->response($respuesta);

					return;
				}
			}


			//DETALLE INVENTARIO 2
			//

			foreach ($DetalleConsolidadoInventario as $key => $posicion) {
				$debito = 0;
				$credito = 0;
				$MontoSysDB = 0;
				$MontoSysCR = 0;
				$cuentaInventario = 0;
				$grantotalAcumulado = 0;
				$CentroCosto = "";
				$UnidadNegocio = "";
				$Proyecto = "";
				// $MontoSysCR = ($detail['ts1_linetotal'] / $TasaLocSys);

				foreach ($posicion as $key => $value) {

					$cuentaInventario = $value->account;
					$grantotalAcumulado = ($grantotalAcumulado + $value->linetotal);

					$CentroCosto = $value->prc_code;
					$UnidadNegocio = $value->uncode;
					$Proyecto = $value->prj_code;
				}

				$MontoSysDB = ($grantotalAcumulado / $TasaLocSys);
				// SE AGREGA AL BALANCE
				$BALANCE = $this->account->addBalance($periodo['data'], round($grantotalAcumulado, $DECI_MALES), $cuentaInventario, 1, $Data['its_docdate'], $Data['business'], $Data['branch']);
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
					':ac1_debit' => round($grantotalAcumulado, $DECI_MALES),
					':ac1_credit' => 0,
					':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
					':ac1_credit_sys' => 0,
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['its_docdate']) ? $Data['its_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['its_docdate']) ? $Data['its_docdate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => is_numeric($Data['its_doctype']) ? $Data['its_doctype'] : 0,
					':ac1_accountvs' => 1,
					':ac1_doctype' => 18,
					':ac1_ref1' => "",
					':ac1_ref2' => "",
					':ac1_ref3' => "",
					':ac1_prc_code' => $CentroCosto,
					':ac1_uncode' => $UnidadNegocio,
					':ac1_prj_code' => $Proyecto,
					':ac1_rescon_date' => NULL,
					':ac1_recon_total' => 0,
					':ac1_made_user' => isset($Data['its_createby']) ? $Data['its_createby'] : NULL,
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
					':ac1_legal_num' => isset($Data['its_cardcode']) ? $Data['its_cardcode'] : NULL,
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
						'mensaje'	=> 'No se pudo registrar la transferencia de stock'
					);

					$this->response($respuesta);

					return;
				}
			}

			///
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
			///

			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'Transferencia de stock #'.$DocNumVerificado.' registrada con exito'
			);
		} else {
			// Se devuelven los cambios realizados en la transaccion
			// si occurre un error  y se muestra devuelve el error.
			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data' => $resInsert,
				'mensaje'	=> 'No se pudo registrar la transferencia'
			);
		}

		$this->response($respuesta);
	}

	//OBTENER  TRASLADO
	public function getStockTransfer_get()
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

		$DECI_MALES =  $this->generic->getDecimals();
		$campos = ",T4.dms_phone1, T4.dms_phone2, T4.dms_cel";

		$sqlSelect = self::getColumn('dits', 'its', $campos, '', $DECI_MALES, $Data['business'], $Data['branch'],24);

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

	//OBTENER SOLICIUD TRASLADO DETALLE POR ID
	public function getSolStockTransferDetail_get()
	{

		$Data = $this->get();

		if (!isset($Data['st1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT ist1.*,dma_lotes_code, dma_series_code FROM ist1 inner join dmar on dma_item_code = st1_itemcode WHERE st1_docentry =:st1_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":st1_docentry" => $Data['st1_docentry']));

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
	public function getSolStockTransferDetailCopy_get()
	{

		$Data = $this->get();

		if (!isset($Data['st1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$copy = $this->documentcopy->Copy($Data['st1_docentry'],'dist','ist1','ist','st1');
		// print_r($)
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

	//OBTENER SOLICIUD TRASLADO DETALLE POR ID
	public function getStockTransferDetail_get()
	{

		$Data = $this->get();

		if (!isset($Data['ts1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM its1 WHERE ts1_docentry =:ts1_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":ts1_docentry" => $Data['ts1_docentry']));

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

	//OBTENER SOLICIUD DE TRASLADO
	public function getSolStockTransferBySN_get()
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
		$copyData = $this->documentcopy->copyData('dist','ist',$Data['dms_card_code'],$Data['business'],$Data['branch']);

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

	private function setAprobacion($Encabezado, $Detalle, $Carpeta, $prefijoe, $prefijod, $Cantidad, $CantidadAP, $Model, $Business, $Branch)
	{

		$sqlInsert = "INSERT INTO dpap(pap_series, pap_docnum, pap_docdate, pap_duedate, pap_duedev, pap_pricelist, pap_cardcode,
									pap_cardname, pap_currency, pap_contacid, pap_slpcode, pap_empid, pap_comment, pap_doctotal, pap_baseamnt, pap_taxtotal,
									pap_discprofit, pap_discount, pap_createat, pap_baseentry, pap_basetype, pap_doctype, pap_idadd, pap_adress, pap_paytype,
									pap_createby,pap_origen,pap_qtyrq,pap_qtyap,pap_model, business, branch)VALUES(:pap_series, :pap_docnum, :pap_docdate, :pap_duedate, :pap_duedev, :pap_pricelist, :pap_cardcode, :pap_cardname,
									:pap_currency, :pap_contacid, :pap_slpcode, :pap_empid, :pap_comment, :pap_doctotal, :pap_baseamnt, :pap_taxtotal, :pap_discprofit, :pap_discount,
									:pap_createat, :pap_baseentry, :pap_basetype, :pap_doctype, :pap_idadd, :pap_adress, :pap_paytype,:pap_createby,:pap_origen,:pap_qtyrq,:pap_qtyap,:pap_model, :business, :branch)";

		// Se Inicia la transaccion,
		// Todas las consultas de modificacion siguientes
		// aplicaran solo despues que se confirme la transaccion,
		// de lo contrario no se aplicaran los cambios y se devolvera
		// la base de datos a su estado original.

		$this->pedeo->trans_begin();

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			':pap_docnum' => 0,
			':pap_series' => is_numeric($Encabezado[$prefijoe . '_series']) ? $Encabezado[$prefijoe . '_series'] : 0,
			':pap_docdate' => $this->validateDate($Encabezado[$prefijoe . '_docdate']) ? $Encabezado[$prefijoe . '_docdate'] : NULL,
			':pap_duedate' => $this->validateDate($Encabezado[$prefijoe . '_duedate']) ? $Encabezado[$prefijoe . '_duedate'] : NULL,
			':pap_duedev' => $this->validateDate($Encabezado[$prefijoe . '_duedev']) ? $Encabezado[$prefijoe . '_duedev'] : NULL,
			':pap_pricelist' => is_numeric($Encabezado[$prefijoe . '_pricelist']) ? $Encabezado[$prefijoe . '_pricelist'] : 0,
			':pap_cardcode' => isset($Encabezado[$prefijoe . '_cardcode']) ? $Encabezado[$prefijoe . '_cardcode'] : NULL,
			':pap_cardname' => isset($Encabezado[$prefijoe . '_cardname']) ? $Encabezado[$prefijoe . '_cardname'] : NULL,
			':pap_currency' => isset($Encabezado[$prefijoe . '_currency']) ? $Encabezado[$prefijoe . '_currency'] : NULL,
			':pap_contacid' => isset($Encabezado[$prefijoe . '_contacid']) ? $Encabezado[$prefijoe . '_contacid'] : NULL,
			':pap_slpcode' => is_numeric($Encabezado[$prefijoe . '_slpcode']) ? $Encabezado[$prefijoe . '_slpcode'] : 0,
			':pap_empid' => is_numeric($Encabezado[$prefijoe . '_empid']) ? $Encabezado[$prefijoe . '_empid'] : 0,
			':pap_comment' => isset($Encabezado[$prefijoe . '_comment']) ? $Encabezado[$prefijoe . '_comment'] : NULL,
			':pap_doctotal' => is_numeric($Encabezado[$prefijoe . '_doctotal']) ? $Encabezado[$prefijoe . '_doctotal'] : 0,
			':pap_baseamnt' => is_numeric($Encabezado[$prefijoe . '_baseamnt']) ? $Encabezado[$prefijoe . '_baseamnt'] : 0,
			':pap_taxtotal' => is_numeric($Encabezado[$prefijoe . '_taxtotal']) ? $Encabezado[$prefijoe . '_taxtotal'] : 0,
			':pap_discprofit' => is_numeric($Encabezado[$prefijoe . '_discprofit']) ? $Encabezado[$prefijoe . '_discprofit'] : 0,
			':pap_discount' => is_numeric($Encabezado[$prefijoe . '_discount']) ? $Encabezado[$prefijoe . '_discount'] : 0,
			':pap_createat' => $this->validateDate($Encabezado[$prefijoe . '_createat']) ? $Encabezado[$prefijoe . '_createat'] : NULL,
			':pap_baseentry' => is_numeric($Encabezado[$prefijoe . '_baseentry']) ? $Encabezado[$prefijoe . '_baseentry'] : 0,
			':pap_basetype' => is_numeric($Encabezado[$prefijoe . '_basetype']) ? $Encabezado[$prefijoe . '_basetype'] : 0,
			':pap_doctype' => 21,
			':pap_idadd' => isset($Encabezado[$prefijoe . '_idadd']) ? $Encabezado[$prefijoe . '_idadd'] : NULL,
			':pap_adress' => isset($Encabezado[$prefijoe . '_adress']) ? $Encabezado[$prefijoe . '_adress'] : NULL,
			':pap_paytype' => is_numeric($Encabezado[$prefijoe . '_paytype']) ? $Encabezado[$prefijoe . '_paytype'] : 0,
			':pap_createby' => isset($Encabezado[$prefijoe . '_createby']) ? $Encabezado[$prefijoe . '_createby'] : NULL,
			':pap_origen' => is_numeric($Encabezado[$prefijoe . '_doctype']) ? $Encabezado[$prefijoe . '_doctype'] : 0,
			':pap_qtyrq' => $Cantidad,
			':pap_qtyap' => $CantidadAP,
			':pap_model' => $Model,
			':business' => $Business,
			':branch' 	=> $Branch

		));


		if (is_numeric($resInsert) && $resInsert > 0) {

			//SE INSERTA EL ESTADO DEL DOCUMENTO

			$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

			$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


				':bed_docentry' => $resInsert,
				':bed_doctype' =>  21,
				':bed_status' => 5, //ESTADO CERRADO
				':bed_createby' => $Encabezado[$prefijoe . '_createby'],
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
					'mensaje'	=> 'No se pudo registrar la cotizacion de ventas'
				);


				$this->response($respuesta);

				return;
			}

			//FIN PROCESO ESTADO DEL DOCUMENTO

			foreach ($Detalle as $key => $detail) {

				$sqlInsertDetail = "INSERT INTO pap1(ap1_docentry, ap1_itemcode, ap1_itemname, ap1_quantity, ap1_uom, ap1_whscode,
																			ap1_price, ap1_vat, ap1_vatsum, ap1_discount, ap1_linetotal, ap1_costcode, ap1_ubusiness, ap1_project,
																			ap1_acctcode, ap1_basetype, ap1_doctype, ap1_avprice, ap1_inventory, ap1_linenum, ap1_acciva, ap1_whscode_dest)VALUES(:ap1_docentry, :ap1_itemcode, :ap1_itemname, :ap1_quantity,
																			:ap1_uom, :ap1_whscode,:ap1_price, :ap1_vat, :ap1_vatsum, :ap1_discount, :ap1_linetotal, :ap1_costcode, :ap1_ubusiness, :ap1_project,
																			:ap1_acctcode, :ap1_basetype, :ap1_doctype, :ap1_avprice, :ap1_inventory,:ap1_linenum,:ap1_acciva,:ap1_whscode_dest)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':ap1_docentry' => $resInsert,
					':ap1_itemcode' => isset($detail[$prefijod . '_itemcode']) ? $detail[$prefijod . '_itemcode'] : NULL,
					':ap1_itemname' => isset($detail[$prefijod . '_itemname']) ? $detail[$prefijod . '_itemname'] : NULL,
					':ap1_quantity' => is_numeric($detail[$prefijod . '_quantity']) ? $detail[$prefijod . '_quantity'] : 0,
					':ap1_uom' => isset($detail[$prefijod . '_uom']) ? $detail[$prefijod . '_uom'] : NULL,
					':ap1_whscode' => isset($detail[$prefijod . '_whscode']) ? $detail[$prefijod . '_whscode'] : NULL,
					':ap1_price' => is_numeric($detail[$prefijod . '_price']) ? $detail[$prefijod . '_price'] : 0,
					':ap1_vat' => is_numeric($detail[$prefijod . '_vat']) ? $detail[$prefijod . '_vat'] : 0,
					':ap1_vatsum' => is_numeric($detail[$prefijod . '_vatsum']) ? $detail[$prefijod . '_vatsum'] : 0,
					':ap1_discount' => is_numeric($detail[$prefijod . '_discount']) ? $detail[$prefijod . '_discount'] : 0,
					':ap1_linetotal' => is_numeric($detail[$prefijod . '_linetotal']) ? $detail[$prefijod . '_linetotal'] : 0,
					':ap1_costcode' => isset($detail[$prefijod . '_costcode']) ? $detail[$prefijod . '_costcode'] : NULL,
					':ap1_ubusiness' => isset($detail[$prefijod . '_ubusiness']) ? $detail[$prefijod . '_ubusiness'] : NULL,
					':ap1_project' => isset($detail[$prefijod . '_project']) ? $detail[$prefijod . '_project'] : NULL,
					':ap1_acctcode' => is_numeric($detail[$prefijod . '_acctcode']) ? $detail[$prefijod . '_acctcode'] : 0,
					':ap1_basetype' => is_numeric($detail[$prefijod . '_basetype']) ? $detail[$prefijod . '_basetype'] : 0,
					':ap1_doctype' => is_numeric($detail[$prefijod . '_doctype']) ? $detail[$prefijod . '_doctype'] : 0,
					':ap1_avprice' => is_numeric($detail[$prefijod . '_avprice']) ? $detail[$prefijod . '_avprice'] : 0,
					':ap1_inventory' => is_numeric($detail[$prefijod . '_inventory']) ? $detail[$prefijod . '_inventory'] : NULL,
					':ap1_linenum' => is_numeric($detail[$prefijod . '_linenum']) ? $detail[$prefijod . '_linenum'] : NULL,
					':ap1_acciva' => is_numeric($detail[$prefijod . '_acciva']) ? $detail[$prefijod . '_acciva'] : NULL,
					':ap1_whscode_dest' => isset($detail[$prefijod . '_whscode_dest']) ? $detail[$prefijod . '_whscode_dest'] : NULL
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
						'mensaje'	=> 'No se pudo registrar la cotización'
					);

					$this->response($respuesta);

					return;
				}
			}


			// Si todo sale bien despues de insertar el detalle de la cotizacion
			// se confirma la trasaccion  para que los cambios apliquen permanentemente
			// en la base de datos y se confirma la operacion exitosa.
			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'El documento fue creado, pero es necesario que sea aprobado'
			);

			$this->response($respuesta);

			return;
		} else {

			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data'    => $resInsert,
				'mensaje'	=> 'No se pudo crear la cotización'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}
	}
}
