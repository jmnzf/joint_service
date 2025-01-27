<?php
// COTIZACIONES U OFERTA DE VENTAS
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class Quotation extends REST_Controller
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
		$this->load->library('DocUpdate');
		$this->load->library('Tasa');
		$this->load->library('DocumentDuplicate');
	}

	//CREAR NUEVA COTIZACION
	public function createQuotation_post()
	{
		$Data = $this->post();
		$DECI_MALES =  $this->generic->getDecimals();
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

	
		$DocNumVerificado = 0;
		$CANTUOMSALE = 0; //CANTIDAD DE LA EQUIVALENCIA SEGUN LA UNIDAD DE MEDIDA DEL ITEM PARA VENTA


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
		$dataTasa = $this->tasa->Tasa($Data['dvc_currency'],$Data['dvc_docdate']);

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

			':bed_docentry' => $Data['dvc_baseentry'],
			':bed_doctype'  => $Data['dvc_basetype'],
			':bed_status'   => 4 // 4 APROBADO SEGUN MODELO DE APROBACION

		));

		// VERIFICA EL MODELO DE APROBACION
		if (!isset($resVerificarAprobacion[0])) {

			$aprobacion = $this->aprobacion->validmodelaprobacion($Data,$ContenidoDetalle,'dvc','vc1',$Data['business'],$Data['branch']);
	
			if ( isset($aprobacion['error']) && $aprobacion['error'] == false && $aprobacion['data'] == 1 ) {
				
				return $this->response($aprobacion);

			} else  if ( isset($aprobacion['error']) && $aprobacion['error'] == true ) {
				
				return $this->response($aprobacion);
			}
		}
	
		// FIN PROESO DE VERIFICAR SI EL DOCUMENTO A CREAR NO  VIENE DE UN PROCESO DE APROBACION Y NO ESTE APROBADO

		// BUSCANDO LA NUMERACION DEL DOCUMENTO
		$DocNumVerificado = $this->documentnumbering->NumberDoc($Data['dvc_series'],$Data['dvc_docdate'],$Data['dvc_duedate']);
		
		if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0){

		}else if ($DocNumVerificado['error']){

			return $this->response($DocNumVerificado, REST_Controller::HTTP_BAD_REQUEST);
		}

		if($Data['dvc_duedate'] < $Data['dvc_docdate']){
			$respuesta = array(
				'error' => true,
				'data' => [],
				'mensaje' => 'La fecha de vencimiento ('.$Data['dvc_duedate'].') no puede ser inferior a la fecha del documento ('.$Data['dvc_docdate'].')'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlInsert = "INSERT INTO dvct(dvc_series, dvc_docnum, dvc_docdate, dvc_duedate, dvc_duedev, dvc_pricelist, dvc_cardcode,
                      dvc_cardname, dvc_currency, dvc_contacid, dvc_slpcode, dvc_empid, dvc_comment, dvc_doctotal, dvc_baseamnt, dvc_taxtotal,
                      dvc_discprofit, dvc_discount, dvc_createat, dvc_baseentry, dvc_basetype, dvc_doctype, dvc_idadd, dvc_adress, dvc_paytype,
                      dvc_createby,business,branch, dvc_internal_comments, dvc_taxtotal_ad)VALUES(:dvc_series, :dvc_docnum, :dvc_docdate, :dvc_duedate, :dvc_duedev, :dvc_pricelist, :dvc_cardcode, :dvc_cardname,
                      :dvc_currency, :dvc_contacid, :dvc_slpcode, :dvc_empid, :dvc_comment, :dvc_doctotal, :dvc_baseamnt, :dvc_taxtotal, :dvc_discprofit, :dvc_discount,
                      :dvc_createat, :dvc_baseentry, :dvc_basetype, :dvc_doctype, :dvc_idadd, :dvc_adress, :dvc_paytype,:dvc_createby,:business,:branch,
					  :dvc_internal_comments, :dvc_taxtotal_ad)";


		// Se Inicia la transaccion,
		// Todas las consultas de modificacion siguientes
		// aplicaran solo despues que se confirme la transaccion,
		// de lo contrario no se aplicaran los cambios y se devolvera
		// la base de datos a su estado original.

		$this->pedeo->trans_begin();

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			':dvc_docnum' => $DocNumVerificado,
			':dvc_series' => is_numeric($Data['dvc_series']) ? $Data['dvc_series'] : 0,
			':dvc_docdate' => $this->validateDate($Data['dvc_docdate']) ? $Data['dvc_docdate'] : NULL,
			':dvc_duedate' => $this->validateDate($Data['dvc_duedate']) ? $Data['dvc_duedate'] : NULL,
			':dvc_duedev' => $this->validateDate($Data['dvc_duedev']) ? $Data['dvc_duedev'] : NULL,
			':dvc_pricelist' => is_numeric($Data['dvc_pricelist']) ? $Data['dvc_pricelist'] : 0,
			':dvc_cardcode' => isset($Data['dvc_cardcode']) ? $Data['dvc_cardcode'] : NULL,
			':dvc_cardname' => isset($Data['dvc_cardname']) ? $Data['dvc_cardname'] : NULL,
			':dvc_currency' => isset($Data['dvc_currency']) ? $Data['dvc_currency'] : NULL,
			':dvc_contacid' => isset($Data['dvc_contacid']) ? $Data['dvc_contacid'] : NULL,
			':dvc_slpcode' => is_numeric($Data['dvc_slpcode']) ? $Data['dvc_slpcode'] : 0,
			':dvc_empid' => is_numeric($Data['dvc_empid']) ? $Data['dvc_empid'] : 0,
			':dvc_comment' => isset($Data['dvc_comment']) ? $Data['dvc_comment'] : NULL,
			':dvc_doctotal' => is_numeric($Data['dvc_doctotal']) ? $Data['dvc_doctotal'] : 0,
			':dvc_baseamnt' => is_numeric($Data['dvc_baseamnt']) ? $Data['dvc_baseamnt'] : 0,
			':dvc_taxtotal' => is_numeric($Data['dvc_taxtotal']) ? $Data['dvc_taxtotal'] : 0,
			':dvc_discprofit' => is_numeric($Data['dvc_discprofit']) ? $Data['dvc_discprofit'] : 0,
			':dvc_discount' => is_numeric($Data['dvc_discount']) ? $Data['dvc_discount'] : 0,
			':dvc_createat' => $this->validateDate($Data['dvc_createat']) ? $Data['dvc_createat'] : NULL,
			':dvc_baseentry' => is_numeric($Data['dvc_baseentry']) ? $Data['dvc_baseentry'] : 0,
			':dvc_basetype' => is_numeric($Data['dvc_basetype']) ? $Data['dvc_basetype'] : 0,
			':dvc_doctype' => is_numeric($Data['dvc_doctype']) ? $Data['dvc_doctype'] : 0,
			':dvc_idadd' => isset($Data['dvc_idadd']) ? $Data['dvc_idadd'] : NULL,
			':dvc_adress' => isset($Data['dvc_adress']) ? $Data['dvc_adress'] : NULL,
			':dvc_paytype' => is_numeric($Data['dvc_paytype']) ? $Data['dvc_paytype'] : 0,
			':dvc_createby' => isset($Data['dvc_createby']) ? $Data['dvc_createby'] : NULL,
			':business' => isset($Data['business']) ? $Data['business'] : NULL,
			':branch' => isset($Data['branch']) ? $Data['branch'] : NULL,
			':dvc_internal_comments' => isset($Data['dvc_internal_comments']) ? $Data['dvc_internal_comments'] : NULL,
			':dvc_taxtotal_ad' => is_numeric($Data['dvc_taxtotal_ad']) ? $Data['dvc_taxtotal_ad'] : 0
		
		));

		if (is_numeric($resInsert) && $resInsert > 0) {

			// Se actualiza la serie de la numeracion del documento

			$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id'      => $Data['dvc_series']
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
				':bed_doctype' => $Data['dvc_doctype'],
				':bed_status' => 1, //ESTADO ABIERTO
				':bed_createby' => $Data['dvc_createby'],
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
			// SE CIERRA EL DOCUMENTO PRELIMINAR SI VIENE DE UN MODELO DE APROBACION
			// SI EL DOCTYPE = 21
			if ($Data['dvc_basetype'] == 21) {

				// SE VALIDA SI HAY ANEXOS EN EL DOCUMENTO APROBADO 
				// SE CAMBIEN AL DOCUMENTO EN CREACION
				$anexo = $this->aprobacion->CambiarAnexos($Data,'dvc',$DocNumVerificado);
	
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


					':bed_docentry' => $Data['dvc_baseentry'],
					':bed_doctype' => $Data['dvc_basetype'],
					':bed_status' => 3, //ESTADO CERRADO
					':bed_createby' => $Data['dvc_createby'],
					':bed_date' => date('Y-m-d'),
					':bed_baseentry' => $resInsert,
					':bed_basetype' => $Data['dvc_doctype']
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


					return $this->response($respuesta);
				}
			}

			//FIN PROCESO ESTADO DEL DOCUMENTO


			//SE APLICA PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS
			if (isset($Data['dvc_baseentry']) && is_numeric($Data['dvc_baseentry']) && isset($Data['dvc_basetype']) && is_numeric($Data['dvc_basetype'])) {

				$sqlDocInicio = "SELECT bmd_tdi, bmd_ndi FROM tbmd WHERE  bmd_doctype = :bmd_doctype AND bmd_docentry = :bmd_docentry";
				$resDocInicio = $this->pedeo->queryTable($sqlDocInicio, array(
					':bmd_doctype' => $Data['dvc_basetype'],
					':bmd_docentry' => $Data['dvc_baseentry']
				));


				if (isset($resDocInicio[0])) {

					$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
														bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency,business)
														VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
														:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency,:business)";

					$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

						':bmd_doctype' => is_numeric($Data['dvc_doctype']) ? $Data['dvc_doctype'] : 0,
						':bmd_docentry' => $resInsert,
						':bmd_createat' => $this->validateDate($Data['dvc_createat']) ? $Data['dvc_createat'] : NULL,
						':bmd_doctypeo' => is_numeric($Data['dvc_basetype']) ? $Data['dvc_basetype'] : 0, //ORIGEN
						':bmd_docentryo' => is_numeric($Data['dvc_baseentry']) ? $Data['dvc_baseentry'] : 0,  //ORIGEN
						':bmd_tdi' => $resDocInicio[0]['bmd_tdi'], // DOCUMENTO INICIAL
						':bmd_ndi' => $resDocInicio[0]['bmd_ndi'], // DOCUMENTO INICIAL
						':bmd_docnum' => $DocNumVerificado,
						':bmd_doctotal' => is_numeric($Data['dvc_doctotal']) ? $Data['dvc_doctotal'] : 0,
						':bmd_cardcode' => isset($Data['dvc_cardcode']) ? $Data['dvc_cardcode'] : NULL,
						':bmd_cardtype' => 1,
						':bmd_currency' => isset($Data['dvc_currency'])?$Data['dvc_currency']:NULL,
						':business' => isset($Data['business'])?$Data['business']:NULL
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
						':bmd_doctype' => is_numeric($Data['dvc_doctype']) ? $Data['dvc_doctype'] : 0,
						':bmd_docentry' => $resInsert,
						':bmd_createat' => $this->validateDate($Data['dvc_createat']) ? $Data['dvc_createat'] : NULL,
						':bmd_doctypeo' => is_numeric($Data['dvc_basetype']) ? $Data['dvc_basetype'] : 0, //ORIGEN
						':bmd_docentryo' => is_numeric($Data['dvc_baseentry']) ? $Data['dvc_baseentry'] : 0,  //ORIGEN
						':bmd_tdi' => is_numeric($Data['dvc_doctype']) ? $Data['dvc_doctype'] : 0, // DOCUMENTO INICIAL
						':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
						':bmd_docnum' => $DocNumVerificado,
						':bmd_doctotal' => is_numeric($Data['dvc_doctotal']) ? $Data['dvc_doctotal'] : 0,
						':bmd_cardcode' => isset($Data['dvc_cardcode']) ? $Data['dvc_cardcode'] : NULL,
						':bmd_cardtype' => 1,
						':bmd_currency' => isset($Data['dvc_currency'])?$Data['dvc_currency']:NULL,
						':business' => isset($Data['business'])?$Data['business']:NULL,
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
					':bmd_doctype' => is_numeric($Data['dvc_doctype']) ? $Data['dvc_doctype'] : 0,
					':bmd_docentry' => $resInsert,
					':bmd_createat' => $this->validateDate($Data['dvc_createat']) ? $Data['dvc_createat'] : NULL,
					':bmd_doctypeo' => is_numeric($Data['dvc_basetype']) ? $Data['dvc_basetype'] : 0, //ORIGEN
					':bmd_docentryo' => is_numeric($Data['dvc_baseentry']) ? $Data['dvc_baseentry'] : 0,  //ORIGEN
					':bmd_tdi' => is_numeric($Data['dvc_doctype']) ? $Data['dvc_doctype'] : 0, // DOCUMENTO INICIAL
					':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
					':bmd_docnum' => $DocNumVerificado,
					':bmd_doctotal' => is_numeric($Data['dvc_doctotal']) ? $Data['dvc_doctotal'] : 0,
					':bmd_cardcode' => isset($Data['dvc_cardcode']) ? $Data['dvc_cardcode'] : NULL,
					':bmd_cardtype' => 1,
					':bmd_currency' => isset($Data['dvc_currency'])?$Data['dvc_currency']:NULL,
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

				$CANTUOMSALE = $this->generic->getUomSale($detail['vc1_itemcode']);

				if ($CANTUOMSALE == 0) {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' 		=> $detail['vc1_itemcode'],
						'mensaje'	=> 'No se encontro la equivalencia de la unidad de medida para el item: ' . $detail['vc1_itemcode']
					);

					$this->response($respuesta);

					return;
				}
				// print_r($detail['imponible']);exit;
				$sqlInsertDetail = "INSERT INTO vct1(vc1_docentry,vc1_itemcode, vc1_itemname, vc1_quantity, vc1_uom, vc1_whscode,
                                    vc1_price, vc1_vat, vc1_vatsum, vc1_discount, vc1_linetotal, vc1_costcode, vc1_ubusiness, vc1_project,
                                    vc1_acctcode, vc1_basetype, vc1_doctype, vc1_avprice, vc1_inventory, vc1_linenum, vc1_acciva, vc1_codimp, vc1_ubication, 
									ote_code,vc1_baseline,detalle_modular,vc1_tax_base,detalle_anuncio,imponible,vc1_clean_quantity,vc1_vat_ad,vc1_vatsum_ad,vc1_accimp_ad,vc1_codimp_ad, vc1_codmunicipality)
									VALUES(:vc1_docentry,:vc1_itemcode, :vc1_itemname, :vc1_quantity,:vc1_uom, :vc1_whscode,:vc1_price, :vc1_vat, :vc1_vatsum, :vc1_discount, 
									:vc1_linetotal, :vc1_costcode, :vc1_ubusiness, :vc1_project,:vc1_acctcode, :vc1_basetype, :vc1_doctype, :vc1_avprice, :vc1_inventory,
									:vc1_linenum,:vc1_acciva,:vc1_codimp, :vc1_ubication, :ote_code,:vc1_baseline,:detalle_modular,:vc1_tax_base,:detalle_anuncio,:imponible,
									:vc1_clean_quantity, :vc1_vat_ad, :vc1_vatsum_ad, :vc1_accimp_ad, :vc1_codimp_ad, :vc1_codmunicipality)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':vc1_docentry' => $resInsert,
					':vc1_itemcode' => isset($detail['vc1_itemcode']) ? $detail['vc1_itemcode'] : NULL,
					':vc1_itemname' => isset($detail['vc1_itemname']) ? $detail['vc1_itemname'] : NULL,
					':vc1_quantity' => is_numeric($detail['vc1_quantity']) ? $detail['vc1_quantity'] : 0,
					':vc1_uom' => isset($detail['vc1_uom']) ? $detail['vc1_uom'] : NULL,
					':vc1_whscode' => isset($detail['vc1_whscode']) ? $detail['vc1_whscode'] : NULL,
					':vc1_price' => is_numeric($detail['vc1_price']) ? $detail['vc1_price'] : 0,
					':vc1_vat' => is_numeric($detail['vc1_vat']) ? $detail['vc1_vat'] : 0,
					':vc1_vatsum' => is_numeric($detail['vc1_vatsum']) ? $detail['vc1_vatsum'] : 0,
					':vc1_discount' => is_numeric($detail['vc1_discount']) ? $detail['vc1_discount'] : 0,
					':vc1_linetotal' => is_numeric($detail['vc1_linetotal']) ? $detail['vc1_linetotal'] : 0,
					':vc1_costcode' => isset($detail['vc1_costcode']) ? $detail['vc1_costcode'] : NULL,
					':vc1_ubusiness' => isset($detail['vc1_ubusiness']) ? $detail['vc1_ubusiness'] : NULL,
					':vc1_project' => isset($detail['vc1_project']) ? $detail['vc1_project'] : NULL,
					':vc1_acctcode' => is_numeric($detail['vc1_acctcode']) ? $detail['vc1_acctcode'] : 0,
					':vc1_basetype' => is_numeric($detail['vc1_basetype']) ? $detail['vc1_basetype'] : 0,
					':vc1_doctype' => is_numeric($detail['vc1_doctype']) ? $detail['vc1_doctype'] : 0,
					':vc1_avprice' => is_numeric($detail['vc1_avprice']) ? $detail['vc1_avprice'] : 0,
					':vc1_inventory' => is_numeric($detail['vc1_inventory']) ? $detail['vc1_inventory'] : NULL,
					':vc1_linenum' => is_numeric($detail['vc1_linenum']) ? $detail['vc1_linenum'] : 0,
					':vc1_acciva' => is_numeric($detail['vc1_acciva']) ? $detail['vc1_acciva'] : NULL,
					':vc1_codimp' => isset($detail['vc1_codimp']) ? $detail['vc1_codimp'] : NULL,
					':vc1_ubication' => isset($detail['vc1_ubication']) ? $detail['vc1_ubication'] : NULL,
					':ote_code' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
					':vc1_baseline' => is_numeric($detail['vc1_baseline']) ? $detail['vc1_baseline'] : 0,
					':vc1_tax_base' => is_numeric($detail['vc1_tax_base']) ? $detail['vc1_tax_base'] : 0,
					':detalle_modular' => isset($detail['detalle_modular']) && is_string($detail['detalle_modular']) ? json_encode(json_decode($detail['detalle_modular'],true)) : NULL,
					':detalle_anuncio' => isset($detail['detalle_anuncio']) && is_string($detail['detalle_anuncio']) ? json_encode(json_decode($detail['detalle_anuncio'],true)) : NULL,
					':imponible' => isset($detail['imponible']) ? $detail['imponible'] : NULL,
					':vc1_clean_quantity' => isset($detail['vc1_clean_quantity']) && is_numeric($detail['vc1_clean_quantity']) ? $detail['vc1_clean_quantity'] : 0,

					':vc1_vat_ad' => is_numeric($detail['vc1_vat_ad']) ? $detail['vc1_vat_ad'] : 0,
					':vc1_vatsum_ad' => is_numeric($detail['vc1_vatsum_ad']) ? $detail['vc1_vatsum_ad'] : 0,
					':vc1_accimp_ad' => is_numeric($detail['vc1_accimp_ad']) ? $detail['vc1_accimp_ad'] : NULL,
					':vc1_codimp_ad' => isset($detail['vc1_codimp_ad']) ? $detail['vc1_codimp_ad'] : NULL,
					':vc1_codmunicipality' => isset($detail['vc1_codmunicipality']) ? $detail['vc1_codmunicipality'] : NULL
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

			//FIN DETALLE COTIZACION



			// Si todo sale bien despues de insertar el detalle de la cotizacion
			// se confirma la trasaccion  para que los cambios apliquen permanentemente
			// en la base de datos y se confirma la operacion exitosa.
			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'Cotización # '.$DocNumVerificado.' registrada con exito'
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

	//ACTUALIZAR COTIZACION
	public function updateQuotation_post()
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
		
		$sqlBefore = "SELECT dvc_docdate ,
						dvc_duedate, 
						dvc_duedev, 
						dvc_pricelist, 
						dvc_cardcode,
						dvc_cardname, 
						dvc_currency, 
						dvc_contacid, 
						dvc_slpcode,
						dvc_empid, 
						dvc_comment, 
						dvc_doctotal, 
						dvc_baseamnt,
						dvc_taxtotal, 
						dvc_discprofit,
						dvc_discount, 
						dvc_createat,
						dvc_baseentry, 
						dvc_basetype, 
						dvc_doctype, 
						dvc_idadd,
						dvc_adress, 
						dvc_paytype ,
						business,
						branch , 
						dvc_internal_comments,
						(SELECT json_agg(json_build_object('vc1_docentry', campo1, 'vc1_itemcode', campo2,'vc1_itemname', campo3,'vc1_quantity',campo4,'vc1_uom',campo5,'vc1_whscode',campo6,
								'vc1_price',campo7,'vc1_vat',campo8,'vc1_vatsum',campo9,'vc1_linetotal',campo10,'vc1_linetotal',campo11,'vc1_costcode',campo12,
								'vc1_ubusiness',campo13,'vc1_project',campo14,'vc1_project',campo15,'vc1_basetype',campo16,'vc1_doctype',campo17,'vc1_avprice',campo18,
								'vc1_inventory',campo19,'vc1_acciva',campo20,'vc1_linenum',campo21,'vc1_ubication',campo22,campo23,campo24,campo25,campo26,campo27,campo28)) AS detail
						FROM (SELECT 
									vc1_docentry as campo1, 
									vc1_itemcode as campo2, 
									vc1_itemname as campo3, 
									vc1_quantity as campo4, 
									vc1_uom as campo5, 
									vc1_whscode as campo6,
									vc1_price as campo7, 
									vc1_vat as campo8, 
									vc1_vatsum as campo9, 
									vc1_discount as campo10, 
									vc1_linetotal as campo11, 
									vc1_costcode as campo12, 
									vc1_ubusiness as campo13, 
									vc1_project as campo14,
									vc1_acctcode as campo15, 
									vc1_basetype as campo16, 
									vc1_doctype as campo17, 
									vc1_avprice as campo18, 
									vc1_inventory as campo19, 
									vc1_acciva as campo20, 
									vc1_linenum as campo21, 
									vc1_ubication as campo22 ,
									vc1_codimp as campo23,
									ote_code as campo24,
									vc1_baseline as campo25,
									detalle_anuncio as campo26,
									detalle_modular as campo27,
									vc1_tax_base as campo28
								FROM vct1 Where vct1.vc1_docentry = dvc_docentry) as subconsulta)
					FROM dvct
					WHERE dvc_docentry = :dvc_docentry";
		$resSqlBefore = $this->pedeo->queryTable($sqlBefore,array(':dvc_docentry' => $Data['dvc_docentry']));

		$json_Before = json_encode($resSqlBefore);
		$json_after = json_encode($Data);
		

		$sqlUpdate = "UPDATE dvct	SET dvc_docdate=:dvc_docdate,dvc_duedate=:dvc_duedate, dvc_duedev=:dvc_duedev, dvc_pricelist=:dvc_pricelist, dvc_cardcode=:dvc_cardcode,
					dvc_cardname=:dvc_cardname, dvc_currency=:dvc_currency, dvc_contacid=:dvc_contacid, dvc_slpcode=:dvc_slpcode,
					dvc_empid=:dvc_empid, dvc_comment=:dvc_comment, dvc_doctotal=:dvc_doctotal, dvc_baseamnt=:dvc_baseamnt,
					dvc_taxtotal=:dvc_taxtotal, dvc_discprofit=:dvc_discprofit, dvc_discount=:dvc_discount, dvc_baseentry=:dvc_baseentry, 
					dvc_basetype=:dvc_basetype, dvc_doctype=:dvc_doctype, dvc_idadd=:dvc_idadd,dvc_adress=:dvc_adress, dvc_paytype=:dvc_paytype ,
					business = :business,branch = :branch, dvc_internal_comments = :dvc_internal_comments, dvc_taxtotal_ad =:dvc_taxtotal_ad
					WHERE dvc_docentry=:dvc_docentry";

		$this->pedeo->trans_begin();
		$doc_update = $this->docupdate->updatedDoc($Data['dvc_doctype'],$Data['dvc_docentry'],$json_Before,$json_after,$Data['dvc_createby']);

		if($doc_update['error'] == true){
			$this->pedeo->trans_rollback();
			return $this->response($doc_update);
		}

		// print_r($Data['dvc_duedev']);exit;die;
		$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
			':dvc_docdate' => $this->validateDate($Data['dvc_docdate']) ? $Data['dvc_docdate'] : NULL,
			':dvc_duedate' => $this->validateDate($Data['dvc_duedate']) ? $Data['dvc_duedate'] : NULL,
			':dvc_duedev' => $this->validateDate($Data['dvc_duedev']) ? $Data['dvc_duedev'] : NULL,
			':dvc_pricelist' => is_numeric($Data['dvc_pricelist']) ? $Data['dvc_pricelist'] : 0,
			':dvc_cardcode' => isset($Data['dvc_cardcode']) ? $Data['dvc_cardcode'] : NULL,
			':dvc_cardname' => isset($Data['dvc_cardname']) ? $Data['dvc_cardname'] : NULL,
			':dvc_currency' => isset($Data['dvc_currency']) ? $Data['dvc_currency'] : NULL,
			':dvc_contacid' => isset($Data['dvc_contacid']) ? $Data['dvc_contacid'] : NULL,
			':dvc_slpcode' => is_numeric($Data['dvc_slpcode']) ? $Data['dvc_slpcode'] : 0,
			':dvc_empid' => is_numeric($Data['dvc_empid']) ? $Data['dvc_empid'] : 0,
			':dvc_comment' => isset($Data['dvc_comment']) ? $Data['dvc_comment'] : NULL,
			':dvc_doctotal' => is_numeric($Data['dvc_doctotal']) ? $Data['dvc_doctotal'] : 0,
			':dvc_baseamnt' => is_numeric($Data['dvc_baseamnt']) ? $Data['dvc_baseamnt'] : 0,
			':dvc_taxtotal' => is_numeric($Data['dvc_taxtotal']) ? $Data['dvc_taxtotal'] : 0,
			':dvc_discprofit' => is_numeric($Data['dvc_discprofit']) ? $Data['dvc_discprofit'] : 0,
			':dvc_discount' => is_numeric($Data['dvc_discount']) ? $Data['dvc_discount'] : 0,
			':dvc_baseentry' => is_numeric($Data['dvc_baseentry']) ? $Data['dvc_baseentry'] : 0,
			':dvc_basetype' => is_numeric($Data['dvc_basetype']) ? $Data['dvc_basetype'] : 0,
			':dvc_doctype' => is_numeric($Data['dvc_doctype']) ? $Data['dvc_doctype'] : 0,
			':dvc_idadd' => isset($Data['dvc_idadd']) ? $Data['dvc_idadd'] : NULL,
			':dvc_adress' => isset($Data['dvc_adress']) ? $Data['dvc_adress'] : NULL,
			':dvc_paytype' => is_numeric($Data['dvc_paytype']) ? $Data['dvc_paytype'] : 0,
			':business' => isset($Data['business']) ? $Data['business'] : NULL,
			':branch' => isset($Data['branch']) ? $Data['branch'] : NULL,
			':dvc_internal_comments' => isset($Data['dvc_internal_comments']) ? $Data['dvc_internal_comments'] : NULL,
			':dvc_docentry' => $Data['dvc_docentry'],
			':dvc_taxtotal_ad' => is_numeric($Data['dvc_taxtotal_ad']) ? $Data['dvc_taxtotal_ad'] : 0
		));
		
		if (is_numeric($resUpdate) && $resUpdate == 1) {

			$this->pedeo->queryTable("DELETE FROM vct1 WHERE vc1_docentry=:vc1_docentry", array(':vc1_docentry' => $Data['dvc_docentry']));

			foreach ($ContenidoDetalle as $key => $detail) {
				$sqlInsertDetail = "INSERT INTO vct1(vc1_docentry,vc1_itemcode, vc1_itemname, vc1_quantity, vc1_uom, vc1_whscode,
                                    vc1_price, vc1_vat, vc1_vatsum, vc1_discount, vc1_linetotal, vc1_costcode, vc1_ubusiness, vc1_project,
                                    vc1_acctcode, vc1_basetype, vc1_doctype, vc1_avprice, vc1_inventory, vc1_linenum, vc1_acciva, vc1_codimp, vc1_ubication, 
									ote_code,vc1_baseline,detalle_modular,vc1_tax_base,detalle_anuncio,imponible,vc1_clean_quantity,vc1_vat_ad,vc1_vatsum_ad,vc1_accimp_ad,vc1_codimp_ad, vc1_codmunicipality)
									VALUES(:vc1_docentry,:vc1_itemcode, :vc1_itemname, :vc1_quantity,:vc1_uom, :vc1_whscode,:vc1_price, :vc1_vat, :vc1_vatsum, :vc1_discount, 
									:vc1_linetotal, :vc1_costcode, :vc1_ubusiness, :vc1_project,:vc1_acctcode, :vc1_basetype, :vc1_doctype, :vc1_avprice, :vc1_inventory,
									:vc1_linenum,:vc1_acciva,:vc1_codimp, :vc1_ubication, :ote_code,:vc1_baseline,:detalle_modular,:vc1_tax_base,:detalle_anuncio,:imponible,
									:vc1_clean_quantity, :vc1_vat_ad, :vc1_vatsum_ad, :vc1_accimp_ad, :vc1_codimp_ad, :vc1_codmunicipality)";


				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':vc1_docentry' => $Data['dvc_docentry'],
					':vc1_itemcode' => isset($detail['vc1_itemcode']) ? $detail['vc1_itemcode'] : NULL,
					':vc1_itemname' => isset($detail['vc1_itemname']) ? $detail['vc1_itemname'] : NULL,
					':vc1_quantity' => is_numeric($detail['vc1_quantity']) ? $detail['vc1_quantity'] : 0,
					':vc1_uom' => isset($detail['vc1_uom']) ? $detail['vc1_uom'] : NULL,
					':vc1_whscode' => isset($detail['vc1_whscode']) ? $detail['vc1_whscode'] : NULL,
					':vc1_price' => is_numeric($detail['vc1_price']) ? $detail['vc1_price'] : 0,
					':vc1_vat' => is_numeric($detail['vc1_vat']) ? $detail['vc1_vat'] : 0,
					':vc1_vatsum' => is_numeric($detail['vc1_vatsum']) ? $detail['vc1_vatsum'] : 0,
					':vc1_discount' => is_numeric($detail['vc1_discount']) ? $detail['vc1_discount'] : 0,
					':vc1_linetotal' => is_numeric($detail['vc1_linetotal']) ? $detail['vc1_linetotal'] : 0,
					':vc1_costcode' => isset($detail['vc1_costcode']) ? $detail['vc1_costcode'] : NULL,
					':vc1_ubusiness' => isset($detail['vc1_ubusiness']) ? $detail['vc1_ubusiness'] : NULL,
					':vc1_project' => isset($detail['vc1_project']) ? $detail['vc1_project'] : NULL,
					':vc1_acctcode' => is_numeric($detail['vc1_acctcode']) ? $detail['vc1_acctcode'] : 0,
					':vc1_basetype' => is_numeric($detail['vc1_basetype']) ? $detail['vc1_basetype'] : 0,
					':vc1_doctype' => is_numeric($detail['vc1_doctype']) ? $detail['vc1_doctype'] : 0,
					':vc1_avprice' => is_numeric($detail['vc1_avprice']) ? $detail['vc1_avprice'] : 0,
					':vc1_inventory' => is_numeric($detail['vc1_inventory']) ? $detail['vc1_inventory'] : NULL,
					':vc1_acciva' => is_numeric($detail['vc1_acciva']) ? $detail['vc1_acciva'] : NULL,
					':vc1_linenum' => is_numeric($detail['vc1_linenum']) ? $detail['vc1_linenum'] : NULL,
					':vc1_codimp' => isset($detail['vc1_codimp']) ? $detail['vc1_codimp'] : NULL,
					':vc1_ubication' => isset($detail['vc1_ubication']) ? $detail['vc1_ubication'] : NULL,
					':ote_code' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
					':vc1_baseline' => is_numeric($detail['vc1_baseline']) ? $detail['vc1_baseline'] : 0,
					':detalle_modular' => (isset($detail['detalle_modular']) && !empty($detail['detalle_modular'])) ? json_encode($detail['detalle_modular']) : NULL,
					':vc1_tax_base' => is_numeric($detail['vc1_tax_base']) ? $detail['vc1_tax_base'] : 0,
					':detalle_anuncio' => (isset($detail['detalle_anuncio']) && !empty($detail['detalle_anuncio'])) ? json_encode($detail['detalle_anuncio']) : NULL,
					':imponible' => is_numeric($detail['imponible']) ? $detail['imponible'] : 0,
					':vc1_clean_quantity' =>  isset($detail['vc1_clean_quantity']) && is_numeric($detail['vc1_clean_quantity']) ? $detail['vc1_clean_quantity'] : 0,

					':vc1_vat_ad' => is_numeric($detail['vc1_vat_ad']) ? $detail['vc1_vat_ad'] : 0,
					':vc1_vatsum_ad' => is_numeric($detail['vc1_vatsum_ad']) ? $detail['vc1_vatsum_ad'] : 0,
					':vc1_accimp_ad' => is_numeric($detail['vc1_accimp_ad']) ? $detail['vc1_accimp_ad'] : NULL,
					':vc1_codimp_ad' => isset($detail['vc1_codimp_ad']) ? $detail['vc1_codimp_ad'] : NULL,
					':vc1_codmunicipality' => isset($detail['vc1_codmunicipality']) ? $detail['vc1_codmunicipality'] : NULL
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


	//OBTENER COTIZACIONES
	public function getQuotation_get()
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

		$sqlSelect = self::getColumn('dvct', 'dvc', $campos, '', $DECI_MALES, $Data['business'], $Data['branch']);

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

	//OBTENER COTIZACIONES PAGINADAS
	public function getPaginatedQuotation_get()
	{
		
		$Data = $this->get();


		if ( !isset($Data['business']) OR !isset($Data['branch']) OR !isset($Data['page']) ) {

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

		$sqlSelect = self::getColumn('dvct', 'dvc', $campos, '', $DECI_MALES, $Data['business'], $Data['branch'], 0, $Data['page'], 1);

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


	//OBTENER COTIZACION POR ID
	public function getQuotationById_get()
	{

		$Data = $this->get();
		$DECI_MALES =  $this->generic->getDecimals();
		if (!isset($Data['dvc_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		// $sqlSelect = self::getColumn('dvct', 'dvc', '', '', $DECI_MALES, $Data['business'], $Data['branch'],0,0,0," AND dvc_docentry = :dvc_docentry");
		$sqlSelect = "SELECT dvct.* FROM dvct WHERE dvc_docentry =:dvc_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":dvc_docentry" => $Data['dvc_docentry']));

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

	public function getQuotationByIdTr_get()
	{

		$Data = $this->get();
		$DECI_MALES =  $this->generic->getDecimals();
		if (!isset($Data['dvc_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$campos = ",T4.dms_phone1, T4.dms_phone2, T4.dms_cel";

		$sqlSelect = self::getColumn('dvct', 'dvc', $campos, '', $DECI_MALES, $Data['business'], $Data['branch'],0,0,0," AND dvc_docentry = :dvc_docentry");
		// $sqlSelect = "SELECT dvct.* FROM dvct WHERE dvc_docentry =:dvc_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":dvc_docentry" => $Data['dvc_docentry']));

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

		$duplicateData = $this->documentduplicate->getDuplicate('dvct','dvc',$Data['dms_card_code'],$Data['business']);


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

		if (!isset($Data['vc1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

			$copy = $this->documentduplicate->getDuplicateDt($Data['vc1_docentry'],'dvct','vct1','dvc','vc1','detalle_modular::jsonb,imponible,clean_quantity');

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
	

	//OBTENER COTIZACION DETALLE POR ID
	public function getQuotationDetailCopy_get()
	{

		$Data = $this->get();

		if (!isset($Data['vc1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

			$copy = $this->documentcopy->Copy($Data['vc1_docentry'],'dvct','vct1','dvc','vc1','detalle_modular::jsonb,imponible,clean_quantity',1);

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

	public function getQuotationDetail_get()
	{

		$Data = $this->get();

		if (!isset($Data['vc1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT vct1.*, dmar.dma_modular, dmar.dma_advertisement,dma_series_code,
						dma_item_inv,
						dma_lotes_code,
						dma_modular,
						dma_advertisement from vct1
						inner join dmar on dmar.dma_item_code = vct1.vc1_itemcode WHERE vc1_docentry =:vc1_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":vc1_docentry" => $Data['vc1_docentry']));

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



	//OBTENER COTIZACIONES POR SOCIO DE NEGOCIO
	public function getQuotationBySN_get()
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

		$copyData = $this->documentcopy->copyData('dvct','dvc',$Data['dms_card_code'],$Data['business'],$Data['branch']);

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


	private function setAprobacion($Encabezado, $Detalle, $Carpeta, $prefijoe, $prefijod, $Cantidad, $CantidadAP)
	{

		$sqlInsert = "INSERT INTO dpap(pap_series, pap_docnum, pap_docdate, pap_duedate, pap_duedev, pap_pricelist, pap_cardcode,
									pap_cardname, pap_currency, pap_contacid, pap_slpcode, pap_empid, pap_comment, pap_doctotal, pap_baseamnt, pap_taxtotal,
									pap_discprofit, pap_discount, pap_createat, pap_baseentry, pap_basetype, pap_doctype, pap_idadd, pap_adress, pap_paytype,
									pap_createby,pap_origen,pap_qtyrq,pap_qtyap)VALUES(:pap_series, :pap_docnum, :pap_docdate, :pap_duedate, :pap_duedev, :pap_pricelist, :pap_cardcode, :pap_cardname,
									:pap_currency, :pap_contacid, :pap_slpcode, :pap_empid, :pap_comment, :pap_doctotal, :pap_baseamnt, :pap_taxtotal, :pap_discprofit, :pap_discount,
									:pap_createat, :pap_baseentry, :pap_basetype, :pap_doctype, :pap_idadd, :pap_adress, :pap_paytype,:pap_createby,:pap_origen,:pap_qtyrq,:pap_qtyap)";

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
			':pap_qtyap' => $CantidadAP

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
																			ap1_acctcode, ap1_basetype, ap1_doctype, ap1_avprice, ap1_inventory, ap1_linenum, ap1_acciva, ap1_codimp)VALUES(:ap1_docentry, :ap1_itemcode, :ap1_itemname, :ap1_quantity,
																			:ap1_uom, :ap1_whscode,:ap1_price, :ap1_vat, :ap1_vatsum, :ap1_discount, :ap1_linetotal, :ap1_costcode, :ap1_ubusiness, :ap1_project,
																			:ap1_acctcode, :ap1_basetype, :ap1_doctype, :ap1_avprice, :ap1_inventory,:ap1_linenum,:ap1_acciva,:ap1_codimp)";

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
					':ap1_codimp' => isset($detail[$prefijod . '_codimp']) ? $detail[$prefijod . '_codimp'] : NULL
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

	//ACTUALIZAR COMENTARIOS INTERNOS Y NORMAL
	public function updateComments_post ()
	{
		$Data = $this->post();

		$update = "UPDATE dvct SET dvc_comment = :dvc_comment, dvc_internal_comments = :dvc_internal_comments WHERE dvc_docentry = :dvc_docentry";
		$resUpdate = $this->pedeo->updateRow($update,array(
			':dvc_comment' => $Data['dvc_comment'],
			':dvc_internal_comments' => $Data['dvc_internal_comments'],
			':dvc_docentry' => $Data['dvc_docentry']
		));

		if(is_numeric($resUpdate) && $resUpdate > 0){
			$respuesta = array(
				'error' => false,
				'data' => $resUpdate,
				'mensaje' => 'Comentarios actualizados correctamente.'
			);
		}else{
			$respuesta = array(
				'error' => true,
				'data' => $resUpdate,
				'mensaje' => 'No se pudo realizar la actualizacion de los comentarios'
			);
		}

		$this->response($respuesta);
	}
}