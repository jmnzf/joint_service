<?php
// PEDIDO DE VENTAS (OFERTA PEDIDO) (ORDEN DE VENTA)
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class SalesOrder extends REST_Controller
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
		$this->load->library('DocumentCopy');
		$this->load->library('aprobacion');
		$this->load->library('DocumentNumbering');
		$this->load->library('Tasa');
		$this->load->library('DocumentDuplicate');
	}

	//CREAR NUEVO PEDIDO
	public function createSalesOrder_post()
	{

		$Data = $this->post();
		$DocNumVerificado = 0;
		$TasaDocLoc = 0;
		$TasaLocSys = 0;
		$MONEDALOCAL = "";
		$MONEDASYS = "";
		$CANTUOMSALE = 0; //CANTIDAD DE LA EQUIVALENCIA SEGUN LA UNIDAD DE MEDIDA DEL ITEM PARA VENTA

		if (
			!isset($Data['business']) or
			!isset($Data['branch'])
		) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

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
		// //BUSCANDO LA NUMERACION DEL DOCUMENTO
		$DocNumVerificado = $this->documentnumbering->NumberDoc($Data['vov_series'],$Data['vov_docdate'],$Data['vov_duedate']);
		
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
		$dataTasa = $this->tasa->Tasa($Data['vov_currency'],$Data['vov_docdate']);

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

			':bed_docentry' => $Data['vov_baseentry'],
			':bed_doctype'  => $Data['vov_basetype'],
			':bed_status'   => 4
		));

		// VERIFICA EL MODELO DE APROBACION
		if (!isset($resVerificarAprobacion[0])) {

			$aprobacion = $this->aprobacion->validmodelaprobacion($Data,$ContenidoDetalle,'vov','ov1',$Data['business'],$Data['branch']);
	
			if ( isset($aprobacion['error']) && $aprobacion['error'] == false && $aprobacion['data'] == 1 ) {
				
				return $this->response($aprobacion);

			} else  if ( isset($aprobacion['error']) && $aprobacion['error'] == true ) {
				
				return $this->response($aprobacion);
			}
		}

		// FIN PROESO DE VERIFICAR SI EL DOCUMENTO A CREAR NO  VIENE DE UN PROCESO DE APROBACION Y NO ESTE APROBADO

		if($Data['vov_duedate'] < $Data['vov_docdate']){
			$respuesta = array(
				'error' => true,
				'data' => [],
				'mensaje' => 'La fecha de vencimiento ('.$Data['vov_duedate'].') no puede ser inferior a la fecha del documento ('.$Data['vov_docdate'].')'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}


		$sqlInsert = "INSERT INTO dvov(vov_series, vov_docnum, vov_docdate, vov_duedate, vov_duedev, vov_pricelist, vov_cardcode,
                      vov_cardname, vov_currency, vov_contacid, vov_slpcode, vov_empid, vov_comment, vov_doctotal, vov_baseamnt, vov_taxtotal,
                      vov_discprofit, vov_discount, vov_createat, vov_baseentry, vov_basetype, vov_doctype, vov_idadd, vov_adress, vov_paytype,
                      vov_createby,business,branch, vov_internal_comments, vov_taxtotal_ad)VALUES(:vov_series, :vov_docnum, :vov_docdate, :vov_duedate, :vov_duedev, :vov_pricelist, :vov_cardcode, :vov_cardname,
                      :vov_currency, :vov_contacid, :vov_slpcode, :vov_empid, :vov_comment, :vov_doctotal, :vov_baseamnt, :vov_taxtotal, :vov_discprofit, :vov_discount,
                      :vov_createat, :vov_baseentry, :vov_basetype, :vov_doctype, :vov_idadd, :vov_adress, :vov_paytype,:vov_createby,:business,:branch,
					  :vov_internal_comments, :vov_taxtotal_ad)";


		// Se Inicia la transaccion,
		// Todas las consultas de modificacion siguientes
		// aplicaran solo despues que se confirme la transaccion,
		// de lo contrario no se aplicaran los cambios y se devolvera
		// la base de datos a su estado original.

		$this->pedeo->trans_begin();

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			':vov_docnum' => $DocNumVerificado,
			':vov_series' => is_numeric($Data['vov_series']) ? $Data['vov_series'] : 0,
			':vov_docdate' => $this->validateDate($Data['vov_docdate']) ? $Data['vov_docdate'] : NULL,
			':vov_duedate' => $this->validateDate($Data['vov_duedate']) ? $Data['vov_duedate'] : NULL,
			':vov_duedev' => $this->validateDate($Data['vov_duedev']) ? $Data['vov_duedev'] : NULL,
			':vov_pricelist' => is_numeric($Data['vov_pricelist']) ? $Data['vov_pricelist'] : 0,
			':vov_cardcode' => isset($Data['vov_cardcode']) ? $Data['vov_cardcode'] : NULL,
			':vov_cardname' => isset($Data['vov_cardname']) ? $Data['vov_cardname'] : NULL,
			':vov_currency' => isset($Data['vov_currency']) ? $Data['vov_currency'] : NULL,
			':vov_contacid' => isset($Data['vov_contacid']) ? $Data['vov_contacid'] : NULL,
			':vov_slpcode' => is_numeric($Data['vov_slpcode']) ? $Data['vov_slpcode'] : 0,
			':vov_empid' => is_numeric($Data['vov_empid']) ? $Data['vov_empid'] : 0,
			':vov_comment' => isset($Data['vov_comment']) ? $Data['vov_comment'] : NULL,
			':vov_doctotal' => is_numeric($Data['vov_doctotal']) ? $Data['vov_doctotal'] : 0,
			':vov_baseamnt' => is_numeric($Data['vov_baseamnt']) ? $Data['vov_baseamnt'] : 0,
			':vov_taxtotal' => is_numeric($Data['vov_taxtotal']) ? $Data['vov_taxtotal'] : 0,
			':vov_discprofit' => is_numeric($Data['vov_discprofit']) ? $Data['vov_discprofit'] : 0,
			':vov_discount' => is_numeric($Data['vov_discount']) ? $Data['vov_discount'] : 0,
			':vov_createat' => $this->validateDate($Data['vov_createat']) ? $Data['vov_createat'] : NULL,
			':vov_baseentry' => is_numeric($Data['vov_baseentry']) ? $Data['vov_baseentry'] : 0,
			':vov_basetype' => is_numeric($Data['vov_basetype']) ? $Data['vov_basetype'] : 0,
			':vov_doctype' => is_numeric($Data['vov_doctype']) ? $Data['vov_doctype'] : 0,
			':vov_idadd' => isset($Data['vov_idadd']) ? $Data['vov_idadd'] : NULL,
			':vov_adress' => isset($Data['vov_adress']) ? $Data['vov_adress'] : NULL,
			':vov_paytype' => is_numeric($Data['vov_paytype']) ? $Data['vov_paytype'] : 0,
			':vov_createby' => isset($Data['vov_createby']) ? $Data['vov_createby'] : NULL,
			':business' => isset($Data['business']) ? $Data['business'] : NULL,
			':branch' => isset($Data['branch']) ? $Data['branch'] : NULL,
			':vov_internal_comments' => isset($Data['vov_internal_comments']) ? $Data['vov_internal_comments'] : NULL,
			':vov_taxtotal_ad'  => is_numeric($Data['vov_taxtotal_ad']) ? $Data['vov_taxtotal_ad'] : 0
		));

		if (is_numeric($resInsert) && $resInsert > 0) {

			// Se actualiza la serie de la numeracion del documento

			$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id'      => $Data['vov_series']
			));


			if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
			} else {
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'    => $resActualizarNumeracion,
					'mensaje'	=> 'No se pudo crear la cotización'
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
				':bed_doctype' => $Data['vov_doctype'],
				':bed_status' => 1, //ESTADO CERRADO
				':bed_createby' => $Data['vov_createby'],
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

			// SE CIERRA EL DOCUMENTO PRELIMINAR SI VIENE DE UN MODELO DE APROBACION
			// SI EL DOCTYPE = 21
			if ($Data['vov_basetype'] == 21) {

				// SE VALIDA SI HAY ANEXOS EN EL DOCUMENTO APROBADO 
				// SE CAMBIEN AL DOCUMENTO EN CREACION
				$anexo = $this->aprobacion->CambiarAnexos($Data,'vov',$DocNumVerificado);
	
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


					':bed_docentry' => $Data['vov_baseentry'],
					':bed_doctype' => $Data['vov_basetype'],
					':bed_status' => 3, //ESTADO CERRADO
					':bed_createby' => $Data['vov_createby'],
					':bed_date' => date('Y-m-d'),
					':bed_baseentry' => $resInsert,
					':bed_basetype' => $Data['vov_doctype']
				));


				if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
				} else {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertEstado,
						'mensaje'	=> 'No se pudo registrar la orden de compras',
						'proceso' => 'Insertar estado documento'
					);


					$this->response($respuesta);

					return;
				}
			}

			//FIN PROCESO ESTADO DEL DOCUMENTO


			//SE APLICA PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS
			if (isset($Data['vov_baseentry']) && is_numeric($Data['vov_baseentry']) && isset($Data['vov_basetype']) && is_numeric($Data['vov_basetype'])) {

				$sqlDocInicio = "SELECT bmd_tdi, bmd_ndi FROM tbmd WHERE  bmd_doctype = :bmd_doctype AND bmd_docentry = :bmd_docentry";
				$resDocInicio = $this->pedeo->queryTable($sqlDocInicio, array(
					':bmd_doctype' => $Data['vov_basetype'],
					':bmd_docentry' => $Data['vov_baseentry']
				));


				if (isset($resDocInicio[0])) {

					$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
														bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency,business)
														VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
														:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency,:business)";

					$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

						':bmd_doctype' => is_numeric($Data['vov_doctype']) ? $Data['vov_doctype'] : 0,
						':bmd_docentry' => $resInsert,
						':bmd_createat' => $this->validateDate($Data['vov_createat']) ? $Data['vov_createat'] : NULL,
						':bmd_doctypeo' => is_numeric($Data['vov_basetype']) ? $Data['vov_basetype'] : 0, //ORIGEN
						':bmd_docentryo' => is_numeric($Data['vov_baseentry']) ? $Data['vov_baseentry'] : 0,  //ORIGEN
						':bmd_tdi' => $resDocInicio[0]['bmd_tdi'], // DOCUMENTO INICIAL
						':bmd_ndi' => $resDocInicio[0]['bmd_ndi'], // DOCUMENTO INICIAL
						':bmd_docnum' => $DocNumVerificado,
						':bmd_doctotal' => is_numeric($Data['vov_doctotal']) ? $Data['vov_doctotal'] : 0,
						':bmd_cardcode' => isset($Data['vov_cardcode']) ? $Data['vov_cardcode'] : NULL,
						':bmd_cardtype' => 1,
						':bmd_currency' => isset($Data['vov_currency'])?$Data['vov_currency']:NULL,
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

						':bmd_doctype' => is_numeric($Data['vov_doctype']) ? $Data['vov_doctype'] : 0,
						':bmd_docentry' => $resInsert,
						':bmd_createat' => $this->validateDate($Data['vov_createat']) ? $Data['vov_createat'] : NULL,
						':bmd_doctypeo' => is_numeric($Data['vov_basetype']) ? $Data['vov_basetype'] : 0, //ORIGEN
						':bmd_docentryo' => is_numeric($Data['vov_baseentry']) ? $Data['vov_baseentry'] : 0,  //ORIGEN
						':bmd_tdi' => is_numeric($Data['vov_doctype']) ? $Data['vov_doctype'] : 0, // DOCUMENTO INICIAL
						':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
						':bmd_docnum' => $DocNumVerificado,
						':bmd_doctotal' => is_numeric($Data['vov_doctotal']) ? $Data['vov_doctotal'] : 0,
						':bmd_cardcode' => isset($Data['vov_cardcode']) ? $Data['vov_cardcode'] : NULL,
						':bmd_cardtype' => 1,
						':bmd_currency' => isset($Data['vov_currency'])?$Data['vov_currency']:NULL,
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

					':bmd_doctype' => is_numeric($Data['vov_doctype']) ? $Data['vov_doctype'] : 0,
					':bmd_docentry' => $resInsert,
					':bmd_createat' => $this->validateDate($Data['vov_createat']) ? $Data['vov_createat'] : NULL,
					':bmd_doctypeo' => is_numeric($Data['vov_basetype']) ? $Data['vov_basetype'] : 0, //ORIGEN
					':bmd_docentryo' => is_numeric($Data['vov_baseentry']) ? $Data['vov_baseentry'] : 0,  //ORIGEN
					':bmd_tdi' => is_numeric($Data['vov_doctype']) ? $Data['vov_doctype'] : 0, // DOCUMENTO INICIAL
					':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
					':bmd_docnum' => $DocNumVerificado,
					':bmd_doctotal' => is_numeric($Data['vov_doctotal']) ? $Data['vov_doctotal'] : 0,
					':bmd_cardcode' => isset($Data['vov_cardcode']) ? $Data['vov_cardcode'] : NULL,
					':bmd_cardtype' => 1,
					':bmd_currency' => isset($Data['vov_currency'])?$Data['vov_currency']:NULL,
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

				$CANTUOMSALE = $this->generic->getUomSale($detail['ov1_itemcode']);

				if ($CANTUOMSALE == 0) {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' 		=> $detail['ov1_itemcode'],
						'mensaje'	=> 'No se encontro la equivalencia de la unidad de medida para el item: ' . $detail['ov1_itemcode']
					);

					$this->response($respuesta);

					return;
				}
				
				$sqlInsertDetail = "INSERT INTO vov1(ov1_docentry,ov1_linenum, ov1_itemcode, ov1_itemname, ov1_quantity, ov1_uom, ov1_whscode,
                                    ov1_price, ov1_vat, ov1_vatsum, ov1_discount, ov1_linetotal, ov1_costcode, ov1_ubusiness, ov1_project,
                                    ov1_acctcode, ov1_basetype, ov1_doctype, ov1_avprice, ov1_inventory, ov1_acciva, ov1_codimp,ov1_ubication,ote_code,ov1_baseline,
									detalle_modular,ov1_tax_base,detalle_anuncio,imponible, business, ov1_clean_quantity, ov1_vat_ad, ov1_vatsum_ad, ov1_accimp_ad,
									ov1_codimp_ad, ov1_codmunicipality)
									VALUES(:ov1_docentry, :ov1_linenum,:ov1_itemcode, :ov1_itemname, :ov1_quantity,:ov1_uom, :ov1_whscode,:ov1_price, :ov1_vat, 
									:ov1_vatsum,:ov1_discount, :ov1_linetotal, :ov1_costcode, :ov1_ubusiness, :ov1_project,:ov1_acctcode, :ov1_basetype, :ov1_doctype, 
									:ov1_avprice, :ov1_inventory, :ov1_acciva, :ov1_codimp,:ov1_ubication,:ote_code,:ov1_baseline,:detalle_modular,:ov1_tax_base,:detalle_anuncio,:imponible, :business,
									:ov1_clean_quantity,:ov1_vat_ad,:ov1_vatsum_ad,:ov1_accimp_ad,:ov1_codimp_ad, :ov1_codmunicipality)";
				// print_r($detail['detalle_anuncio']);exit;
				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':ov1_docentry' => $resInsert,
					':ov1_linenum' => is_numeric($detail['ov1_linenum']) ? $detail['ov1_linenum'] : 0,
					':ov1_itemcode' => isset($detail['ov1_itemcode']) ? $detail['ov1_itemcode'] : NULL,
					':ov1_itemname' => isset($detail['ov1_itemname']) ? $detail['ov1_itemname'] : NULL,
					':ov1_quantity' => is_numeric($detail['ov1_quantity']) ? ($detail['ov1_quantity'] * $CANTUOMSALE) : 0,
					':ov1_uom' => isset($detail['ov1_uom']) ? $detail['ov1_uom'] : NULL,
					':ov1_whscode' => isset($detail['ov1_whscode']) ? $detail['ov1_whscode'] : NULL,
					':ov1_price' => is_numeric($detail['ov1_price']) ? $detail['ov1_price'] : 0,
					':ov1_vat' => is_numeric($detail['ov1_vat']) ? $detail['ov1_vat'] : 0,
					':ov1_vatsum' => is_numeric($detail['ov1_vatsum']) ? $detail['ov1_vatsum'] : 0,
					':ov1_discount' => is_numeric($detail['ov1_discount']) ? $detail['ov1_discount'] : 0,
					':ov1_linetotal' => is_numeric($detail['ov1_linetotal']) ? $detail['ov1_linetotal'] : 0,
					':ov1_costcode' => isset($detail['ov1_costcode']) ? $detail['ov1_costcode'] : NULL,
					':ov1_ubusiness' => isset($detail['ov1_ubusiness']) ? $detail['ov1_ubusiness'] : NULL,
					':ov1_project' => isset($detail['ov1_project']) ? $detail['ov1_project'] : NULL,
					':ov1_acctcode' => is_numeric($detail['ov1_acctcode']) ? $detail['ov1_acctcode'] : 0,
					':ov1_basetype' => is_numeric($detail['ov1_basetype']) ? $detail['ov1_basetype'] : 0,
					':ov1_doctype' => is_numeric($detail['ov1_doctype']) ? $detail['ov1_doctype'] : 0,
					':ov1_avprice' => is_numeric($detail['ov1_avprice']) ? $detail['ov1_avprice'] : 0,
					':ov1_inventory' => is_numeric($detail['ov1_inventory']) ? $detail['ov1_inventory'] : NULL,
					':ov1_acciva' => is_numeric($detail['ov1_acciva']) ? $detail['ov1_acciva'] : NULL,
					':ov1_codimp' => isset($detail['ov1_codimp']) ? $detail['ov1_codimp'] : NULL,
					':ov1_ubication' => isset($detail['ov1_ubication']) ? $detail['ov1_ubication'] : NULL,
					':ote_code' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
					':ov1_baseline' => is_numeric($detail['ov1_baseline']) ? $detail['ov1_baseline'] : 0,
					':ov1_tax_base' => is_numeric($detail['ov1_tax_base']) ? $detail['ov1_tax_base'] : 0,
					':detalle_modular' => (isset($detail['detalle_modular']) && is_string($detail['detalle_modular'])) ? json_encode(json_decode($detail['detalle_modular'],true)) : NULL,
					':detalle_anuncio' => (isset($detail['detalle_anuncio']) && is_string($detail['detalle_anuncio'])) ? json_encode(json_decode($detail['detalle_anuncio'],true)) : NULL,
					':imponible' => isset($detail['imponible']) ? $detail['imponible'] : NULL,
					':business' => $Data['business'],
					':ov1_clean_quantity' => isset($detail['ov1_clean_quantity']) && is_numeric($detail['ov1_clean_quantity']) ? $detail['ov1_clean_quantity'] : 0,

					':ov1_vat_ad' => is_numeric($detail['ov1_vat_ad']) ? $detail['ov1_vat_ad'] : 0,
					':ov1_vatsum_ad' => is_numeric($detail['ov1_vatsum_ad']) ? $detail['ov1_vatsum_ad'] : 0,
					':ov1_accimp_ad' => is_numeric($detail['ov1_accimp_ad']) ? $detail['ov1_accimp_ad'] : NULL,
					':ov1_codimp_ad' => isset($detail['ov1_codimp_ad']) ? $detail['ov1_codimp_ad'] : NULL,
					':ov1_codmunicipality' => isset($detail['ov1_codmunicipality']) ? $detail['ov1_codmunicipality'] : NULL
				));

				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle de el pedido se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsert,
						'mensaje'	=> 'No se pudo registrar el pedido'
					);

					$this->response($respuesta);

					return;
				}
			}

			//FIN DETALLE PEDIDO

			if ($Data['vov_basetype'] == 1) {


				$sqlEstado1 = "SELECT
								count(t1.vc1_linenum) item,
								sum(t1.vc1_quantity) cantidad
						from dvct t0
						inner join vct1 t1 on t0.dvc_docentry = t1.vc1_docentry
						where t0.dvc_docentry = :dvc_docentry and t0.dvc_doctype = :dvc_doctype";


				$resEstado1 = $this->pedeo->queryTable($sqlEstado1, array(
					':dvc_docentry' => $Data['vov_baseentry'],
					':dvc_doctype' => $Data['vov_basetype']

				));
				
				$sqlEstado2 = "SELECT
									coalesce(count(distinct t3.ov1_baseline),0) item,
									coalesce(sum(t3.ov1_quantity),0) cantidad
							from dvct t0
							inner join vct1 t1 on t0.dvc_docentry = t1.vc1_docentry
							left join dvov t2 on t0.dvc_docentry = t2.vov_baseentry
							left join vov1 t3 on t2.vov_docentry = t3.ov1_docentry and t1.vc1_itemcode = t3.ov1_itemcode and t1.vc1_linenum = t3.ov1_baseline
							where t0.dvc_docentry = :dvc_docentry and t0.dvc_doctype = :dvc_doctype";


				$resEstado2 = $this->pedeo->queryTable($sqlEstado2, array(
					':dvc_docentry' => $Data['vov_baseentry'],
					':dvc_doctype' => $Data['vov_basetype'],

				));
				// print_r($sqlEstado2);exit;
				$item_cot = $resEstado1[0]['item'];
				$cantidad_cot = $resEstado1[0]['cantidad'];
				$item_ord = $resEstado2[0]['item'];
				$cantidad_ord = $resEstado2[0]['cantidad'];

				

				if ($item_ord >= $item_cot &&   $cantidad_ord >= $cantidad_cot) {

					$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																			VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

					$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


						':bed_docentry' => $Data['vov_baseentry'],
						':bed_doctype' => $Data['vov_basetype'],
						':bed_status' => 3, //ESTADO CERRADO
						':bed_createby' => $Data['vov_createby'],
						':bed_date' => date('Y-m-d'),
						':bed_baseentry' => $resInsert,
						':bed_basetype' => $Data['vov_doctype']
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
			}else if ($Data['vov_basetype'] == 32){

				$sql_contract = "SELECT dcsn.csn_typeagreement,tcsn.* FROM tcsn INNER JOIN dcsn ON tcsn.csn_docentry = dcsn.csn_docentry WHERE tcsn.csn_doctype = :csn_doctype AND tcsn.csn_docentry = :csn_docentry";
				$resSql_contract = $this->pedeo->queryTable($sql_contract,array(
					':csn_doctype' => $Data['vov_basetype'],
					':csn_docentry' => $Data['vov_baseentry']
				));

				if(isset($resSql_contract[0]) && $resSql_contract[0]['csn_typeagreement'] == 1){
					$sqlOrder = "SELECT SUM(dvov.vov_doctotal) as total FROM dvov WHERE dvov.vov_basetype = :vov_basetype AND dvov.vov_baseentry = :vov_baseentry";
					$resOrder = $this->pedeo->queryTable($sqlOrder,array(
						':vov_basetype' => $Data['vov_basetype'],
						':vov_baseentry' => $Data['vov_baseentry']
					));

					$totalOrder = is_numeric($resOrder[0]['total']) ? $resOrder[0]['total'] : 0;
					$totalContract = is_numeric($resSql_contract[0]['csn_doctotal']) ? $resSql_contract[0]['csn_doctotal'] : 0;

					if($totalContract == $totalOrder){
						$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
											VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

						$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(
							':bed_docentry' => $Data['vov_baseentry'],
							':bed_doctype' => $Data['vov_basetype'],
							':bed_status' => 3, //ESTADO CERRADO
							':bed_createby' => $Data['vov_createby'],
							':bed_date' => date('Y-m-d'),
							':bed_baseentry' => $resInsert,
							':bed_basetype' => $Data['vov_doctype']
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
				}else {
					$item_1 = 0;
					$cant_1 = 0;
					$item_2 = 0;
					$cant_2 = 0;
					
					$sql1 = "SELECT 
								coalesce(count(distinct c.sn1_linenum),0) as item,
								cast(coalesce(sum(c.sn1_quantity),0) as int) as cantidad
							from tcsn t 
							inner join csn1 c on t.csn_docentry = c.sn1_docentry 
							where t.csn_doctype = :csn_doctype and t.csn_docentry = :csn_docentry";
					$resSql1 = $this->pedeo->queryTable($sql1,array(
						':csn_doctype' => $Data['vov_basetype'],
						':csn_docentry' => $Data['vov_baseentry']
					));

					$sql2 = "SELECT 
								coalesce(count(distinct v.ov1_baseline),0) as item,
								cast(coalesce(sum(v.ov1_quantity),0) as int) as cantidad
							from tcsn t 
							inner join csn1 c on t.csn_docentry = c.sn1_docentry
							left join dvov d on t.csn_docentry = d.vov_baseentry and t.csn_doctype = d.vov_basetype 
							left join vov1 v on d.vov_docentry = v.ov1_docentry and c.sn1_itemcode = v.ov1_itemcode and c.sn1_linenum = v.ov1_baseline 
							where d.vov_basetype  = :csn_doctype and d.vov_baseentry  = :csn_docentry";

					$resSql2 = $this->pedeo->queryTable($sql2,array(
						':csn_doctype' => $Data['vov_basetype'],
						':csn_docentry' => $Data['vov_baseentry']
					));

					$item_1 = isset($resSql1[0]) ? $resSql1[0]['item'] : $item_1;
					$cant_1 = isset($resSql1[0]) ? $resSql1[0]['cantidad'] : $cant_1;
					$item_2 = isset($resSql2[0]) ? $resSql2[0]['item'] : $item_2;
					$cant_2 = isset($resSql2[0]) ? $resSql2[0]['cantidad'] : $cant_2;

					if($item_1 == $item_2 && $cant_1 == $cant_2){
						$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
											VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

						$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(
							':bed_docentry' => $Data['vov_baseentry'],
							':bed_doctype' => $Data['vov_basetype'],
							':bed_status' => 3, //ESTADO CERRADO
							':bed_createby' => $Data['vov_createby'],
							':bed_date' => date('Y-m-d'),
							':bed_baseentry' => $resInsert,
							':bed_basetype' => $Data['vov_doctype']
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
				}


			}


			// Si todo sale bien despues de insertar el detalle de el pedido
			// se confirma la trasaccion  para que los cambios apliquen permanentemente
			// en la base de datos y se confirma la operacion exitosa.
			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'Pedido # '.$DocNumVerificado.' registrado con exito'
			);
		} else {
			// Se devuelven los cambios realizados en la transaccion
			// si occurre un error  y se muestra devuelve el error.
			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data' => $resInsert,
				'mensaje'	=> 'No se pudo registrar la cotización'
			);
		}

		$this->response($respuesta);
	}

	//ACTUALIZAR PEDIDO
	public function updateSalesOrder_post()
	{

		$Data = $this->post();

		if (
			!isset($Data['vov_docentry']) or !isset($Data['vov_docnum']) or
			!isset($Data['vov_docdate']) or !isset($Data['vov_duedate']) or
			!isset($Data['vov_duedev']) or !isset($Data['vov_pricelist']) or
			!isset($Data['vov_cardcode']) or !isset($Data['vov_cardname']) or
			!isset($Data['vov_currency']) or !isset($Data['vov_contacid']) or
			!isset($Data['vov_slpcode']) or !isset($Data['vov_empid']) or
			!isset($Data['vov_comment']) or !isset($Data['vov_doctotal']) or
			!isset($Data['vov_baseamnt']) or !isset($Data['vov_taxtotal']) or
			!isset($Data['vov_discprofit']) or !isset($Data['vov_discount']) or
			!isset($Data['vov_createat']) or !isset($Data['vov_baseentry']) or
			!isset($Data['vov_basetype']) or !isset($Data['vov_doctype']) or
			!isset($Data['vov_idadd']) or !isset($Data['vov_adress']) or
			!isset($Data['vov_paytype']) or !isset($Data['vov_attch']) or
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
				'mensaje' => 'No se encontro el detalle de la cotización'
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

		$sqlUpdate = "UPDATE dvov	SET vov_docdate=:vov_docdate,vov_duedate=:vov_duedate, vov_duedev=:vov_duedev, vov_pricelist=:vov_pricelist, vov_cardcode=:vov_cardcode,
					vov_cardname=:vov_cardname, vov_currency=:vov_currency, vov_contacid=:vov_contacid, vov_slpcode=:vov_slpcode,
					vov_empid=:vov_empid, vov_comment=:vov_comment, vov_doctotal=:vov_doctotal, vov_baseamnt=:vov_baseamnt,
					vov_taxtotal=:vov_taxtotal, vov_discprofit=:vov_discprofit, vov_discount=:vov_discount, vov_createat=:vov_createat,
					vov_baseentry=:vov_baseentry, vov_basetype=:vov_basetype, vov_doctype=:vov_doctype, vov_idadd=:vov_idadd,
					vov_adress=:vov_adress, vov_paytype=:vov_paytype, business = :business,branch = :branch, vov_internal_comments = :vov_internal_comments,
					vov_taxtotal_ad = :vov_taxtotal_ad
					WHERE vov_docentry=:vov_docentry";

		$this->pedeo->trans_begin();

		$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
			':vov_docnum' => is_numeric($Data['vov_docnum']) ? $Data['vov_docnum'] : 0,
			':vov_docdate' => $this->validateDate($Data['vov_docdate']) ? $Data['vov_docdate'] : NULL,
			':vov_duedate' => $this->validateDate($Data['vov_duedate']) ? $Data['vov_duedate'] : NULL,
			':vov_duedev' => $this->validateDate($Data['vov_duedev']) ? $Data['vov_duedev'] : NULL,
			':vov_pricelist' => is_numeric($Data['vov_pricelist']) ? $Data['vov_pricelist'] : 0,
			':vov_cardcode' => isset($Data['vov_pricelist']) ? $Data['vov_pricelist'] : NULL,
			':vov_cardname' => isset($Data['vov_cardname']) ? $Data['vov_cardname'] : NULL,
			':vov_currency' => is_numeric($Data['vov_currency']) ? $Data['vov_currency'] : 0,
			':vov_contacid' => isset($Data['vov_contacid']) ? $Data['vov_contacid'] : NULL,
			':vov_slpcode' => is_numeric($Data['vov_slpcode']) ? $Data['vov_slpcode'] : 0,
			':vov_empid' => is_numeric($Data['vov_empid']) ? $Data['vov_empid'] : 0,
			':vov_comment' => isset($Data['vov_comment']) ? $Data['vov_comment'] : NULL,
			':vov_doctotal' => is_numeric($Data['vov_doctotal']) ? $Data['vov_doctotal'] : 0,
			':vov_baseamnt' => is_numeric($Data['vov_baseamnt']) ? $Data['vov_baseamnt'] : 0,
			':vov_taxtotal' => is_numeric($Data['vov_taxtotal']) ? $Data['vov_taxtotal'] : 0,
			':vov_discprofit' => is_numeric($Data['vov_discprofit']) ? $Data['vov_discprofit'] : 0,
			':vov_discount' => is_numeric($Data['vov_discount']) ? $Data['vov_discount'] : 0,
			':vov_createat' => $this->validateDate($Data['vov_createat']) ? $Data['vov_createat'] : NULL,
			':vov_baseentry' => is_numeric($Data['vov_baseentry']) ? $Data['vov_baseentry'] : 0,
			':vov_basetype' => is_numeric($Data['vov_basetype']) ? $Data['vov_basetype'] : 0,
			':vov_doctype' => is_numeric($Data['vov_doctype']) ? $Data['vov_doctype'] : 0,
			':vov_idadd' => isset($Data['vov_idadd']) ? $Data['vov_idadd'] : NULL,
			':vov_adress' => isset($Data['vov_adress']) ? $Data['vov_adress'] : NULL,
			':vov_paytype' => is_numeric($Data['vov_paytype']) ? $Data['vov_paytype'] : 0,
			':business' => isset($Data['business']) ? $Data['business'] : NULL,
			':branch' => isset($Data['branch']) ? $Data['branch'] : NULL,
			':vov_internal_comments' => isset($Data['vov_internal_comments']) ? $Data['vov_internal_comments'] : NULL,
			':vov_taxtotal_ad' => is_numeric($Data['vov_taxtotal_ad']) ? $Data['vov_taxtotal_ad'] : 0,
			':vov_docentry' => $Data['vov_docentry']
		));

		if (is_numeric($resUpdate) && $resUpdate == 1) {

			$this->pedeo->queryTable("DELETE FROM vct1 WHERE ov1_docentry=:ov1_docentry", array(':ov1_docentry' => $Data['vov_docentry']));

			foreach ($ContenidoDetalle as $key => $detail) {

				$sqlInsertDetail = "INSERT INTO vov1(ov1_docentry,ov1_linenum, ov1_itemcode, ov1_itemname, ov1_quantity, ov1_uom, ov1_whscode,
							ov1_price, ov1_vat, ov1_vatsum, ov1_discount, ov1_linetotal, ov1_costcode, ov1_ubusiness, ov1_project,
							ov1_acctcode, ov1_basetype, ov1_doctype, ov1_avprice, ov1_inventory, ov1_acciva, ov1_codimp,ov1_ubication,ote_code,ov1_baseline,
							detalle_modular,ov1_tax_base,detalle_anuncio,imponible, business, ov1_clean_quantity, ov1_vat_ad, ov1_vatsum_ad, ov1_accimp_ad,
							ov1_codimp_ad, ov1_codmunicipality)
							VALUES(:ov1_docentry, :ov1_linenum,:ov1_itemcode, :ov1_itemname, :ov1_quantity,:ov1_uom, :ov1_whscode,:ov1_price, :ov1_vat, 
							:ov1_vatsum,:ov1_discount, :ov1_linetotal, :ov1_costcode, :ov1_ubusiness, :ov1_project,:ov1_acctcode, :ov1_basetype, :ov1_doctype, 
							:ov1_avprice, :ov1_inventory, :ov1_acciva, :ov1_codimp,:ov1_ubication,:ote_code,:ov1_baseline,:detalle_modular,:ov1_tax_base,:detalle_anuncio,:imponible, :business,
							:ov1_clean_quantity,:ov1_vat_ad,:ov1_vatsum_ad,:ov1_accimp_ad,:ov1_codimp_ad, ov1_codmunicipality)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':ov1_docentry' => $Data['vov_docentry'],
					':ov1_itemcode' => isset($detail['ov1_itemcode']) ? $detail['ov1_itemcode'] : NULL,
					':ov1_itemname' => isset($detail['ov1_itemname']) ? $detail['ov1_itemname'] : NULL,
					':ov1_quantity' => is_numeric($detail['ov1_quantity']) ? $detail['ov1_quantity'] : 0,
					':ov1_uom' => isset($detail['ov1_uom']) ? $detail['ov1_uom'] : NULL,
					':ov1_whscode' => isset($detail['ov1_whscode']) ? $detail['ov1_whscode'] : NULL,
					':ov1_price' => is_numeric($detail['ov1_price']) ? $detail['ov1_price'] : 0,
					':ov1_vat' => is_numeric($detail['ov1_vat']) ? $detail['ov1_vat'] : 0,
					':ov1_vatsum' => is_numeric($detail['ov1_vatsum']) ? $detail['ov1_vatsum'] : 0,
					':ov1_discount' => is_numeric($detail['ov1_discount']) ? $detail['ov1_discount'] : 0,
					':ov1_linetotal' => is_numeric($detail['ov1_linetotal']) ? $detail['ov1_linetotal'] : 0,
					':ov1_costcode' => isset($detail['ov1_costcode']) ? $detail['ov1_costcode'] : NULL,
					':ov1_ubusiness' => isset($detail['ov1_ubusiness']) ? $detail['ov1_ubusiness'] : NULL,
					':ov1_project' => isset($detail['ov1_project']) ? $detail['ov1_project'] : NULL,
					':ov1_acctcode' => is_numeric($detail['ov1_acctcode']) ? $detail['ov1_acctcode'] : 0,
					':ov1_basetype' => is_numeric($detail['ov1_basetype']) ? $detail['ov1_basetype'] : 0,
					':ov1_doctype' => is_numeric($detail['ov1_doctype']) ? $detail['ov1_doctype'] : 0,
					':ov1_avprice' => is_numeric($detail['ov1_avprice']) ? $detail['ov1_avprice'] : 0,
					':ov1_inventory' => is_numeric($detail['ov1_inventory']) ? $detail['ov1_inventory'] : NULL,
					':ov1_acciva' => is_numeric($detail['ov1_acciva']) ? $detail['ov1_acciva'] : NULL,
					':ov1_ubication' => is_numeric($detail['ov1_ubication']) ? $detail['ov1_ubication'] : NULL,
					':ov1_linenum' => isset($detail['ov1_linenum']) ? $detail['ov1_linenum'] : NULL,
					':ov1_lote' => isset($detail['ov1_lote']) && !empty($detail['ov1_lote']) ? $detail['ov1_lote'] : NULL,
					':ov1_clean_quantity' => isset($detail['ov1_clean_quantity']) && is_numeric($detail['ov1_clean_quantity']) ? $detail['ov1_clean_quantity'] : NULL,

					
					':ov1_vat_ad' => is_numeric($detail['ov1_vat_ad']) ? $detail['ov1_vat_ad'] : 0,
					':ov1_vatsum_ad' => is_numeric($detail['ov1_vatsum_ad']) ? $detail['ov1_vatsum_ad'] : 0,
					':ov1_accimp_ad' => is_numeric($detail['ov1_accimp_ad']) ? $detail['ov1_accimp_ad'] : NULL,
					':ov1_codimp_ad' => isset($detail['ov1_codimp_ad']) ? $detail['ov1_codimp_ad'] : NULL,
					':ov1_codmunicipality' => isset($detail['ov1_codmunicipality']) ? $detail['ov1_codmunicipality'] : NULL
				));

				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle de el pedido se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'    => $resInsertDetail,
						'mensaje'	=> 'No se pudo registrar el pedido'
					);

					$this->response($respuesta);

					return;
				}
			}


			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resUpdate,
				'mensaje' => 'Cotización actualizada con exito'
			);
		} else {

			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data'    => $resUpdate,
				'mensaje'	=> 'No se pudo actualizar la cotización'
			);
		}

		$this->response($respuesta);
	}


	//OBTENER PEDIDOS
	public function getSalesOrder_get()
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

		$sqlSelect = self::getColumn('dvov', 'vov', $campos, '', $DECI_MALES, $Data['business'], $Data['branch']);

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


	//OBTENER PEDIDO POR ID
	public function getSalesOrderById_get()
	{

		$Data = $this->get();
		$DECI_MALES =  $this->generic->getDecimals();
		if (!isset($Data['vov_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$campos = ",T4.dms_phone1, T4.dms_phone2, T4.dms_cel";

		$sqlSelect = self::getColumn('dvov', 'vov', $campos, '', $DECI_MALES, $Data['business'], $Data['branch'],0,0,0," AND vov_docentry = :vov_docentry");
		// print_r($sqlSelect);exit;
		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":vov_docentry" => $Data['vov_docentry']));

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


	//OBTENER DETALLE PEDIDO POR ID
	public function getSalesOrderDetailCopy_get()
	{

		$Data = $this->get();

		if (!isset($Data['ov1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}


		$copy = $this->documentcopy->Copy($Data['ov1_docentry'],'dvov','vov1','vov','ov1','detalle_modular::jsonb,imponible,clean_quantity');
		
		$array = [];

		if ( isset($Data['ubication']) && $Data['ubication'] == 'COMPRAS' ) {

			$acu = 1;
			foreach ($copy as $key => $item) {

				if ( $item['dma_item_mat'] == 1 ) {

					$sqlLm = "SELECT rlm1.lm1_quantity,dmum.dmu_code,rlm1.lm1_whscode,dmar.*
					FROM prlm
					inner join rlm1 on prlm.rlm_id = rlm1.lm1_iddoc 
					inner join dmar on rlm1.lm1_itemcode = dmar.dma_item_code 
					inner join dmum on dmar.dma_uom_sale = dmum.dmu_id  
					WHERE rlm1.lm1_type = :lm1_type
					AND prlm.rlm_item_code = :rlm_item_code";
					
					$resLm = $this->pedeo->queryTable($sqlLm, array(
						':lm1_type' => '2',
						':rlm_item_code' => $item['ov1_itemcode']
					));

					if (!isset($resLm[0])){

						$respuesta = array(
							'error'   => true,
							'data' => array(),
							'mensaje'	=> 'busqueda sin resultados'
						);

						return $this->response($respuesta);
					}

					foreach ($resLm as $key => $item2) {

						if ((isset($item['dma_sup_set']) && $item['dma_sup_set'] == $item2['dma_sup_set']) || (isset($item['dma_sup_set']) && empty($item['dma_sup_set']))) {
							
							$newArray = array (
								"ov1_linenum" => $acu,
								"ov1_acciva" => 0,
								"ov1_acctcode" => 0,
								"ov1_avprice" => 0,
								"ov1_basetype" => 0,
								"ov1_costcode" => $item['ov1_costcode'],
								"ov1_discount" => 0,
								"ov1_docentry" => 0,
								"ov1_doctype" => 0,
								"ov1_id" => 0,
								"ov1_inventory" => $item2['dma_item_inv'],
								"ov1_itemcode" => $item2['dma_item_code'],
								"ov1_itemname" => $item2['dma_item_name'],
								"ov1_linetotal" => 0,
								"ov1_price" => 0,
								"ov1_project" => $item['ov1_project'],
								"ov1_quantity" => $item['ov1_quantity'] * $item2['lm1_quantity'],
								"ov1_ubusiness" => $item['ov1_ubusiness'],
								"ov1_uom" => $item2['dmu_code'],
								"ov1_vat" => 0,
								"vatsum_real" => 0.00,
								"ov1_vatsum" => 0.00,
								"ov1_whscode" => $item2['lm1_whscode'],
								"dma_series_code" => 0,
								"ov1_ubication" => 0,
								"ov1_codimp" => $item2['dma_tax_purch_code'],
								"fun_ubication" => 0,
								"fun_lote" => 0,
								"dma_advertisement" => 0,
								"dma_modular" => $item2['dma_modular'],
								"dma_item_mat" => $item2['dma_item_mat'],
								"dmi_use_fc" => 0,
								"dmi_rate_fc" => 0,
								"ote_code" => 0,
								"ote_date" => 0,
								"ote_duedate" => null,
								"ov1_whscode_dest" => null,
								"ov1_ubication2" => null,
								"dma_use_tbase" => 0,
								"dma_tasa_base" => 0,
								"detalle_modular" => null,
								"imponible" => 0
							);
		
							array_push($array, $newArray);
		
							$acu++;
						}
					}

				} else {
					$item['ov1_linenum'] = $acu;
					array_push($array, $item);
					$acu++;
				}
				
			}

			$copy = $array;

		}

		
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

	public function getSalesOrderDetail_get()
	{

		$Data = $this->get();

		if (!isset($Data['ov1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT * FROM vov1 WHERE ov1_docentry =:ov1_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":ov1_docentry" => $Data['ov1_docentry']));

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



	//OBTENER PEDIDO DE VENTAS POR SOCIO DE NEGOCIO
	public function getSalesOrderBySN_get()
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

		$copyData = $this->documentcopy->copyData('dvov','vov',$Data['dms_card_code'],$Data['business'],$Data['branch']);

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

	public function getOpenSalesOrder_get()
	{

		$request = $this->get();
		//
		$where = "";
		$array = [':tipo' => 2, ':estado' => 'Abierto'];
		//
		if (isset($request['cardcode']) && !empty($request['cardcode'])) {
			# code...
			$where = "and vov_cardcode = :cardcode";
			$array[':cardcode'] = $request['cardcode'];
		}

		$sqlSelect = "SELECT vov_docnum, vov_docentry, vov_cardcode,vov_cardname
						from responsestatus
						join dvov on vov_doctype = tipo and vov_docentry = id
						where tipo = :tipo and estado = :estado $where";

		$resSelect = $this->pedeo->queryTable($sqlSelect, $array);

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

		$duplicateData = $this->documentduplicate->getDuplicate('dvov','vov',$Data['dms_card_code'],$Data['business']);


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

		if (!isset($Data['ov1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

			$copy = $this->documentduplicate->getDuplicateDt($Data['ov1_docentry'],'dvov','vov1','vov','ov1','detalle_modular::jsonb,detalle_anuncio::jsonb,imponible,clean_quantity');

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

		$update = "UPDATE dvov SET vov_comment = :vov_comment, vov_internal_comments = :vov_internal_comments WHERE vov_docentry = :vov_docentry";
		$resUpdate = $this->pedeo->updateRow($update,array(
			':vov_comment' => $Data['vov_comment'],
			':vov_internal_comments' => $Data['vov_internal_comments'],
			':vov_docentry' => $Data['vov_docentry']
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


	// OBTERNER ARTICULOS DE PEDIDOS PARA ORDEN DE FABRICACION
	public function getArtManOrd_post(){


		$Data = $this->post();

		$sqlSelect = "SELECT dmar.dma_item_code, dmar.dma_item_inv, dmar.dma_price_list, dmar.dma_price, dmar.dma_avprice,
						dmar.dma_tax_sales, dmar.dma_tax_purch, dmar.dma_item_name, (get_code_dmum(dmar.dma_uom_sale::integer)) as dma_uom_sale, 
						(get_code_dmum(dmar.dma_uom_purch::integer)) as dma_uom_purch, dmar.dma_item_sales, dmga.mga_acctin, dmga.mga_acct_inv,
						concat(dmar.dma_item_code, ' - ', dmar.dma_item_name) as dma_item_name2, dmar.dma_lotes_code, dmar.dma_emisionmethod,
						dmar.dma_subscription, dmar.dma_series_code, dmar.dma_tax_purch_code, dmar.dma_tax_sales_code, dmar.dma_group_code, 
						dmar.dma_use_tbase, dmar.dma_tasa_base, dmar.dma_uom_pemb, dmar.dma_uom_semb, row_to_json(prlm.*) as mat_l, vov1.ov1_quantity
						FROM vov1 
						inner join dmar on vov1.ov1_itemcode = dmar.dma_item_code 
						left join dmga on dmga.mga_id = dmar.dma_group_code 
						inner join prlm on dmar.dma_item_code = prlm.rlm_item_code
						where ov1_docentry = :ov1_docentry
						and vov1.business = :business
						and dma_item_mat = :dma_item_mat";


		$resSelect = $this->pedeo->queryTable( $sqlSelect, array( ":ov1_docentry" => $Data['docentry'], ":business" => $Data['business'], ":dma_item_mat" => 1 ) );


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
}