<?php
// CONTRATOS DE SOCIOS DE NEGOCIO
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class BpContracts extends REST_Controller
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
		$this->load->library('documentCopy');
		$this->load->library('DocumentNumbering');
		$this->load->library('Tasa');
	}

	//CREAR NUEVO CONTRATO
	public function createBpContracts_post()
	{

		$Data = $this->post();
		$DocNumVerificado = 0;
		$TasaDocLoc = 0;
		$TasaLocSys = 0;
		$MONEDALOCAL = "";
		$MONEDASYS = "";
		$DatosCS = ""; // DATOS DEL CONTRATO DE SUSCRIPCION
		$TotalAcuRentencion = 0;
		$inArrayRetencion = array();
		$DetalleRetencion = new stdClass();
		$DetalleConsolidadoRetencion = [];


		if (!isset($Data['detail']) OR !isset($Data['business']) OR !isset($Data['branch'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		if (isset($Data['detailcs'])) {

			$DatosCS = json_decode($Data['detailcs'], true);
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
		$DocNumVerificado = $this->documentnumbering->NumberDoc($Data['csn_series'],$Data['csn_docdate'],$Data['csn_duedate']);
		
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
		$dataTasa = $this->tasa->Tasa($Data['csn_currency'],$Data['csn_docdate']);

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

			':bed_docentry' => $Data['csn_baseentry'],
			':bed_doctype'  => $Data['csn_basetype'],
			':bed_status'   => 4
		));

		if (!isset($resVerificarAprobacion[0])) {

			//VERIFICAR MODELO DE APROBACION

			$sqlDocModelo = " SELECT * FROM tmau inner join mau1 on mau_docentry = au1_docentry where mau_doctype = :mau_doctype";
			$resDocModelo = $this->pedeo->queryTable($sqlDocModelo, array(':mau_doctype' => $Data['csn_doctype']));

			if (isset($resDocModelo[0])) {

				$sqlModUser = "SELECT aus_id FROM taus
							INNER JOIN pgus
							ON aus_id_usuario = pgu_id_usuario
							WHERE aus_id_model = :aus_id_model
							AND pgu_code_user = :pgu_code_user";

				$resModUser = $this->pedeo->queryTable($sqlModUser, array(':aus_id_model' => $resDocModelo[0]['mau_docentry'], ':pgu_code_user' => $Data['csn_createby']));

				if (isset($resModUser[0])) {
					// VALIDACION DE APROBACION

					$condicion1 = $resDocModelo[0]['au1_c1']; // ESTO ME DICE SI LA CONDICION DEL DOCTOTAL ES 1 MAYOR 2 MENOR
					$valorDocTotal = $resDocModelo[0]['au1_doctotal'];
					$valorSociosNegocio = $resDocModelo[0]['au1_sn'];
					$TotalDocumento = $Data['csn_doctotal'];

					if (trim($Data['csn_currency']) != $TasaDocLoc) {
						$TotalDocumento = ($TotalDocumento * $TasaDocLoc);
					}


					if (is_numeric($valorDocTotal) && $valorDocTotal > 0) { //SI HAY UN VALOR Y SI ESTE ES MAYOR A CERO

						if (!empty($valorSociosNegocio)) { // CON EL SOCIO DE NEGOCIO

							if ($condicion1 == 1) {

								if ($TotalDocumento >= $valorDocTotal) {

									if (in_array($Data['csn_cardcode'], explode(",", $valorSociosNegocio))) {

										$this->setAprobacion($Data, $ContenidoDetalle, $resMainFolder[0]['main_folder'], 'csn', 'sn1');
									}
								}
							} else if ($condicion1 == 2) {

								if ($TotalDocumento <= $valorDocTotal) {
									if (in_array($Data['csn_cardcode'], explode(",", $valorSociosNegocio))) {

										$this->setAprobacion($Data, $ContenidoDetalle, $resMainFolder[0]['main_folder'], 'csn', 'sn1');
									}
								}
							}
						} else { // SIN EL SOCIO DE NEGOCIO


							if ($condicion1 == 1) {
								if ($TotalDocumento >= $valorDocTotal) {

									$this->setAprobacion($Data, $ContenidoDetalle, $resMainFolder[0]['main_folder'], 'csn', 'sn1');
								}
							} else if ($condicion1 == 2) {
								if ($TotalDocumento <= $valorDocTotal) {

									$this->setAprobacion($Data, $ContenidoDetalle, $resMainFolder[0]['main_folder'], 'csn', 'sn1');
								}
							}
						}
					} else { // SI NO SE COMPARA EL TOTAL DEL DOCUMENTO

						if (!empty($valorSociosNegocio)) {

							if (in_array($Data['csn_cardcode'], explode(",", $valorSociosNegocio))) {

								$respuesta = $this->setAprobacion($Data, $ContenidoDetalle, $resMainFolder[0]['main_folder'], 'csn', 'sn1');
							}
						} else {

							$respuesta = array(
								'error' => true,
								'data'  => array(),
								'mensaje' => 'No se ha encontraro condiciones en el modelo de aprobacion, favor contactar con su administrador del sistema'
							);

							$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

							return;
						}
					}
				}
			}

			//VERIFICAR MODELO DE PROBACION
		}
		// FIN PROESO DE VERIFICAR SI EL DOCUMENTO A CREAR NO  VIENE DE UN PROCESO DE APROBACION Y NO ESTE APROBADO




		$sqlInsert = "INSERT INTO tcsn(	csn_docnum, csn_docdate, csn_duedate, csn_duedev, csn_pricelist, csn_cardcode, csn_cardname, csn_contacid, csn_slpcode, 
					csn_empid, csn_comment, csn_doctotal, csn_baseamnt, csn_taxtotal, csn_discprofit, csn_discount, csn_createat, csn_baseentry, csn_basetype, 
					csn_doctype, csn_idadd, csn_adress, csn_paytype, csn_series, csn_createby, csn_currency, csn_origen, csn_ref, csn_canceled, csn_enddate, 
					csn_signaturedate, csn_description, csn_prjcode, business,branch,csn_bankable,csn_internal_comments,csn_taxtotal_ad,csn_billingday,
					csn_cleantotal, csn_weeklytoilets,csn_totalret, csn_totalretiva )
					VALUES (:csn_docnum, :csn_docdate, :csn_duedate, :csn_duedev, :csn_pricelist, :csn_cardcode, :csn_cardname, :csn_contacid, :csn_slpcode,
					:csn_empid, :csn_comment, :csn_doctotal, :csn_baseamnt, :csn_taxtotal, :csn_discprofit, :csn_discount, :csn_createat, :csn_baseentry,
					:csn_basetype, :csn_doctype, :csn_idadd, :csn_adress, :csn_paytype, :csn_series, :csn_createby, :csn_currency, :csn_origen, :csn_ref,
					:csn_canceled, :csn_enddate, :csn_signaturedate, :csn_description, :csn_prjcode, :business,:branch,:csn_bankable,:csn_internal_comments,
					:csn_taxtotal_ad,:csn_billingday, :csn_cleantotal, :csn_weeklytoilets, :csn_totalret, :csn_totalretiva)";


		// Se Inicia la transaccion,
		// Todas las consultas de modificacion siguientes
		// aplicaran solo despues que se confirme la transaccion,
		// de lo contrario no se aplicaran los cambios y se devolvera
		// la base de datos a su estado original.

		$this->pedeo->trans_begin();

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			':csn_docnum' => $DocNumVerificado,
			':csn_docdate' => $this->validateDate($Data['csn_docdate']) ? $Data['csn_docdate'] : NULL,
			':csn_duedate' => $this->validateDate($Data['csn_duedate']) ? $Data['csn_duedate'] : NULL,
			':csn_duedev' => $this->validateDate($Data['csn_duedev']) ? $Data['csn_duedev'] : NULL,
			':csn_pricelist' => is_numeric($Data['csn_pricelist']) ? $Data['csn_pricelist'] : 0,
			':csn_cardcode' => isset($Data['csn_cardcode']) ? $Data['csn_cardcode'] : NULL,
			':csn_cardname' => isset($Data['csn_cardname']) ? $Data['csn_cardname'] : NULL,
			':csn_contacid' => isset($Data['csn_contacid']) ? $Data['csn_contacid'] : NULL,
			':csn_slpcode' => is_numeric($Data['csn_slpcode']) ? $Data['csn_slpcode'] : 0,
			':csn_empid' => is_numeric($Data['csn_empid']) ? $Data['csn_empid'] : 0,
			':csn_comment' => isset($Data['csn_comment']) ? $Data['csn_comment'] : NULL,
			':csn_doctotal' =>  is_numeric($Data['csn_doctotal']) ? $Data['csn_doctotal'] : 0,
			':csn_baseamnt' => is_numeric($Data['csn_baseamnt']) ? $Data['csn_baseamnt'] : 0,
			':csn_taxtotal' => is_numeric($Data['csn_taxtotal']) ? $Data['csn_taxtotal'] : 0,
			':csn_discprofit' => is_numeric($Data['csn_discprofit']) ? $Data['csn_discprofit'] : 0,
			':csn_discount' => is_numeric($Data['csn_discount']) ? $Data['csn_discount'] : 0,
			':csn_createat' => $this->validateDate($Data['csn_createat']) ? $Data['csn_createat'] : NULL,
			':csn_baseentry' => is_numeric($Data['csn_baseentry']) ? $Data['csn_baseentry'] : 0,
			':csn_basetype' => is_numeric($Data['csn_basetype']) ? $Data['csn_basetype'] : 0,
			':csn_doctype' =>  is_numeric($Data['csn_doctype']) ? $Data['csn_doctype'] : 0,
			':csn_idadd' => isset($Data['csn_idadd']) ? $Data['csn_idadd'] : NULL,
			':csn_adress' => isset($Data['csn_adress']) ? $Data['csn_adress'] : NULL,
			':csn_paytype' => is_numeric($Data['csn_paytype']) ? $Data['csn_paytype'] : 0,
			':csn_series' => is_numeric($Data['csn_series']) ? $Data['csn_series'] : 0,
			':csn_createby' => isset($Data['csn_createby']) ? $Data['csn_createby'] : NULL,
			':csn_currency' => isset($Data['csn_currency']) ? $Data['csn_currency'] : NULL,
			':csn_origen' => isset($Data['csn_origen']) ? $Data['csn_origen'] : NULL,
			':csn_ref' => isset($Data['csn_ref']) ? $Data['csn_ref'] : NULL,
			':csn_canceled' => isset($Data['csn_canceled']) ? $Data['csn_canceled'] : 0,
			':csn_enddate' => $this->validateDate($Data['csn_enddate']) ? $Data['csn_enddate'] : NULL,
			':csn_signaturedate' => $this->validateDate($Data['csn_signaturedate']) ? $Data['csn_signaturedate'] : NULL,
			':csn_description' => isset($Data['csn_description']) ? $Data['csn_description'] : NULL,
			':csn_prjcode' => isset($Data['csn_prjcode']) ? $Data['csn_prjcode'] : NULL,
			':business' => $Data['business'],
			':branch' => isset($Data['branch']),
			':csn_bankable' => is_numeric($Data['csn_bankable']) ? $Data['csn_bankable'] : 0,
			':csn_internal_comments'  => isset($Data['csn_internal_comments']) ? $Data['csn_internal_comments'] : NULL,
			':csn_taxtotal_ad' => isset($Data['csn_taxtotal_ad']) && is_numeric($Data['csn_taxtotal_ad']) ? $Data['csn_taxtotal_ad'] : 0,
			':csn_billingday' => isset($Data['csn_billingday']) && is_numeric($Data['csn_billingday']) ? $Data['csn_billingday'] : 0,
			':csn_cleantotal' => isset($Data['csn_cleantotal']) && is_numeric($Data['csn_cleantotal']) ? $Data['csn_cleantotal'] : 0,
			':csn_weeklytoilets' => isset($Data['csn_weeklytoilets']) && is_numeric($Data['csn_weeklytoilets']) ? $Data['csn_weeklytoilets'] : 0,
			':csn_totalretiva' => isset($Data['csn_totalretiva']) && is_numeric($Data['csn_totalretiva']) ? $Data['csn_totalretiva'] : 0,
			':csn_totalret' => isset($Data['csn_totalret']) && is_numeric($Data['csn_totalret']) ? $Data['csn_totalret'] : 0
			
		));

		if (is_numeric($resInsert) && $resInsert > 0) {

			// Se actualiza la serie de la numeracion del documento

			$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id'      => $Data['csn_series']
			));


			if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
			} else {
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'    => $resActualizarNumeracion,
					'mensaje'	=> 'No pudo generar el contrato'
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
				':bed_doctype' => $Data['csn_doctype'],
				':bed_status' => 1, //ESTADO CERRADO
				':bed_createby' => $Data['csn_createby'],
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


			//SE APLICA PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS
			if (isset($Data['csn_baseentry']) && is_numeric($Data['csn_baseentry']) && isset($Data['csn_basetype']) && is_numeric($Data['csn_basetype'])) {

				$sqlDocInicio = "SELECT bmd_tdi, bmd_ndi FROM tbmd WHERE  bmd_doctype = :bmd_doctype AND bmd_docentry = :bmd_docentry";
				$resDocInicio = $this->pedeo->queryTable($sqlDocInicio, array(
					':bmd_doctype' => $Data['csn_basetype'],
					':bmd_docentry' => $Data['csn_baseentry']
				));


				if (isset($resDocInicio[0])) {

					$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
					bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency,business)
					VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, 
					:bmd_cardcode, :bmd_cardtype, :bmd_currency,:business)";

					$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

						':bmd_doctype' => is_numeric($Data['csn_doctype']) ? $Data['csn_doctype'] : 0,
						':bmd_docentry' => $resInsert,
						':bmd_createat' => $this->validateDate($Data['csn_createat']) ? $Data['csn_createat'] : NULL,
						':bmd_doctypeo' => is_numeric($Data['csn_basetype']) ? $Data['csn_basetype'] : 0, //ORIGEN
						':bmd_docentryo' => is_numeric($Data['csn_baseentry']) ? $Data['csn_baseentry'] : 0,  //ORIGEN
						':bmd_tdi' => $resDocInicio[0]['bmd_tdi'], // DOCUMENTO INICIAL
						':bmd_ndi' => $resDocInicio[0]['bmd_ndi'], // DOCUMENTO INICIAL
						':bmd_docnum' => $DocNumVerificado,
						':bmd_doctotal' => is_numeric($Data['csn_doctotal']) ? $Data['csn_doctotal'] : 0,
						':bmd_cardcode' => isset($Data['csn_cardcode']) ? $Data['csn_cardcode'] : NULL,
						':bmd_cardtype' => $Data['csn_cardtype'],
						':bmd_currency' => isset($Data['csn_currency'])?$Data['csn_currency']:NULL,
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
					VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, 
					:bmd_cardcode, :bmd_cardtype, :bmd_currency,:business)";

					$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

						':bmd_doctype' => is_numeric($Data['csn_doctype']) ? $Data['csn_doctype'] : 0,
						':bmd_docentry' => $resInsert,
						':bmd_createat' => $this->validateDate($Data['csn_createat']) ? $Data['csn_createat'] : NULL,
						':bmd_doctypeo' => is_numeric($Data['csn_basetype']) ? $Data['csn_basetype'] : 0, //ORIGEN
						':bmd_docentryo' => is_numeric($Data['csn_baseentry']) ? $Data['csn_baseentry'] : 0,  //ORIGEN
						':bmd_tdi' => is_numeric($Data['csn_doctype']) ? $Data['csn_doctype'] : 0, // DOCUMENTO INICIAL
						':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
						':bmd_docnum' => $DocNumVerificado,
						':bmd_doctotal' => is_numeric($Data['csn_doctotal']) ? $Data['csn_doctotal'] : 0,
						':bmd_cardcode' => isset($Data['csn_cardcode']) ? $Data['csn_cardcode'] : NULL,
						':bmd_cardtype' => $Data['csn_cardtype'],
						':bmd_currency' => isset($Data['csn_currency'])?$Data['csn_currency']:NULL,
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
				VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,:bmd_docentryo, :bmd_tdi, :bmd_ndi, 
				:bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency,:business)";

				$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

					':bmd_doctype' => is_numeric($Data['csn_doctype']) ? $Data['csn_doctype'] : 0,
					':bmd_docentry' => $resInsert,
					':bmd_createat' => $this->validateDate($Data['csn_createat']) ? $Data['csn_createat'] : NULL,
					':bmd_doctypeo' => is_numeric($Data['csn_basetype']) ? $Data['csn_basetype'] : 0, //ORIGEN
					':bmd_docentryo' => is_numeric($Data['csn_baseentry']) ? $Data['csn_baseentry'] : 0,  //ORIGEN
					':bmd_tdi' => is_numeric($Data['csn_doctype']) ? $Data['csn_doctype'] : 0, // DOCUMENTO INICIAL
					':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
					':bmd_docnum' => $DocNumVerificado,
					':bmd_doctotal' => is_numeric($Data['csn_doctotal']) ? $Data['csn_doctotal'] : 0,
					':bmd_cardcode' => isset($Data['csn_cardcode']) ? $Data['csn_cardcode'] : NULL,
					':bmd_cardtype' => $Data['csn_cardtype'],
					':bmd_currency' => isset($Data['csn_currency'])?$Data['csn_currency']:NULL,
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
			}
			//FIN PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS


			foreach ($ContenidoDetalle as $key => $detail) {

				$sqlInsertDetail = "INSERT INTO csn1(sn1_docentry, sn1_linenum,sn1_itemcode, sn1_itemname, sn1_quantity, sn1_uom, sn1_whscode,
                                    sn1_price, sn1_vat, sn1_vatsum, sn1_discount, sn1_linetotal, sn1_costcode, sn1_ubusiness, sn1_project,
                                    sn1_acctcode, sn1_basetype, sn1_doctype, sn1_avprice, sn1_inventory, sn1_acciva, sn1_codimp,sn1_ubication,detalle_modular,sn1_tax_base,
									sn1_clean_quantity,sn1_vat_ad,sn1_vatsum_ad,sn1_accimp_ad,sn1_codimp_ad)VALUES(:sn1_docentry,:sn1_linenum, :sn1_itemcode, :sn1_itemname, :sn1_quantity,
                                    :sn1_uom, :sn1_whscode,:sn1_price, :sn1_vat, :sn1_vatsum, :sn1_discount, :sn1_linetotal, :sn1_costcode, :sn1_ubusiness, :sn1_project,
                                    :sn1_acctcode, :sn1_basetype, :sn1_doctype, :sn1_avprice, :sn1_inventory, :sn1_acciva, :sn1_codimp,:sn1_ubication,:detalle_modular,:sn1_tax_base,
									:sn1_clean_quantity,:sn1_vat_ad,:sn1_vatsum_ad,:sn1_accimp_ad,:sn1_codimp_ad)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':sn1_docentry' => $resInsert,
					':sn1_linenum' => isset($detail['sn1_linenum']) ? $detail['sn1_linenum'] : NULL,
					':sn1_itemcode' => isset($detail['sn1_itemcode']) ? $detail['sn1_itemcode'] : NULL,
					':sn1_itemname' => isset($detail['sn1_itemname']) ? $detail['sn1_itemname'] : NULL,
					':sn1_quantity' => is_numeric($detail['sn1_quantity']) ? $detail['sn1_quantity'] : 0,
					':sn1_uom' => isset($detail['sn1_uom']) ? $detail['sn1_uom'] : NULL,
					':sn1_whscode' => isset($detail['sn1_whscode']) ? $detail['sn1_whscode'] : NULL,
					':sn1_price' => is_numeric($detail['sn1_price']) ? $detail['sn1_price'] : 0,
					':sn1_vat' => is_numeric($detail['sn1_vat']) ? $detail['sn1_vat'] : 0,
					':sn1_vatsum' => is_numeric($detail['sn1_vatsum']) ? $detail['sn1_vatsum'] : 0,
					':sn1_discount' => is_numeric($detail['sn1_discount']) ? $detail['sn1_discount'] : 0,
					':sn1_linetotal' => is_numeric($detail['sn1_linetotal']) ? $detail['sn1_linetotal'] : 0,
					':sn1_costcode' => isset($detail['sn1_costcode']) ? $detail['sn1_costcode'] : NULL,
					':sn1_ubusiness' => isset($detail['sn1_ubusiness']) ? $detail['sn1_ubusiness'] : NULL,
					':sn1_project' => isset($detail['sn1_project']) ? $detail['sn1_project'] : NULL,
					':sn1_acctcode' => is_numeric($detail['sn1_acctcode']) ? $detail['sn1_acctcode'] : 0,
					':sn1_basetype' => is_numeric($detail['sn1_basetype']) ? $detail['sn1_basetype'] : 0,
					':sn1_doctype' => is_numeric($detail['sn1_doctype']) ? $detail['sn1_doctype'] : 0,
					':sn1_avprice' => is_numeric($detail['sn1_avprice']) ? $detail['sn1_avprice'] : 0,
					':sn1_inventory' => is_numeric($detail['sn1_inventory']) ? $detail['sn1_inventory'] : NULL,
					':sn1_acciva' => is_numeric($detail['sn1_acciva']) ? $detail['sn1_acciva'] : NULL,
					':sn1_codimp' => isset($detail['sn1_codimp']) ? $detail['sn1_codimp'] : NULL,
					':sn1_ubication' => isset($detail['sn1_ubication']) ? $detail['sn1_ubication'] : NULL,
					':detalle_modular' => (json_encode($detail['detalle_modular'])) ? json_encode($detail['detalle_modular']) : NULL,
					':sn1_tax_base' => is_numeric($detail['sn1_tax_base']) ? $detail['sn1_tax_base'] : 0,
					':sn1_clean_quantity' => isset($detail['sn1_clean_quantity']) && is_numeric($detail['sn1_clean_quantity']) ? $detail['sn1_clean_quantity'] : 0,

					':sn1_vat_ad' => is_numeric($detail['sn1_vat_ad']) ? $detail['sn1_vat_ad'] : 0,
					':sn1_vatsum_ad' => is_numeric($detail['sn1_vatsum_ad']) ? $detail['sn1_vatsum_ad'] : 0,
					':sn1_accimp_ad' => is_numeric($detail['sn1_accimp_ad']) ? $detail['sn1_accimp_ad'] : NULL,
					':sn1_codimp_ad' => isset($detail['sn1_codimp_ad']) ? $detail['sn1_codimp_ad'] : NULL,
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
						'mensaje'	=> 'No se pudo registrar el contrato'
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
									':crt_basetype'  => $Data['csn_doctype'],
									':crt_typert'    => $value['crt_typert'],
									':crt_basert'    => $value['crt_basert'],
									':crt_profitrt'  => $value['crt_profitrt'],
									':crt_totalrt'   => $value['crt_totalrt'],
									':crt_base'		 => $value['crt_base'],
									':crt_type'		 => $value['crt_type'],
									':crt_linenum'   => $detail['sn1_linenum'],
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
										'mensaje'	=> 'No se pudo registrar el contrato, fallo el proceso para insertar las retenciones'
									);
									$this->response($respuesta);
									return;
								}
							}
						}
					}
				}

				// FIN PROCESO PARA INSERTAR RETENCIONES
			}
			//FIN DETALLE CONTRATO

			// DATOS COMPLEMNETARIO DEL CONTRATOS
			$sqlInsertDetail2 = "INSERT INTO dcsn(csn_docentry, csn_doctype, csn_typeagreement, csn_paymentcondition, csn_waypay, csn_shippingway, csn_probabilitypercentage, csn_status, csn_renewal, csn_remember)
							 VALUES (:csn_docentry, :csn_doctype, :csn_typeagreement, :csn_paymentcondition, :csn_waypay, :csn_shippingway, :csn_probabilitypercentage, :csn_status, :csn_renewal, :csn_remember)";

			$resInsertDetail2 = $this->pedeo->insertRow($sqlInsertDetail2, array(

				':csn_docentry' => $resInsert,
				':csn_doctype' => isset($Data['csn_doctype']) ? $Data['csn_doctype'] : NULL,
				':csn_typeagreement' =>  is_numeric($Data['csn_typeagreement']) ? $Data['csn_typeagreement'] : NULL,
				':csn_paymentcondition' =>  is_numeric($Data['csn_paymentcondition']) ? $Data['csn_paymentcondition'] : NULL,
				':csn_waypay' =>  is_numeric($Data['csn_waypay']) ? $Data['csn_waypay'] : NULL,
				':csn_shippingway' =>  is_numeric($Data['csn_shippingway']) ? $Data['csn_shippingway'] : NULL,
				':csn_probabilitypercentage' =>  is_numeric($Data['csn_probabilitypercentage']) ? $Data['csn_probabilitypercentage'] : NULL,
				':csn_status' =>  is_numeric($Data['csn_status']) ? $Data['csn_status'] : NULL,
				':csn_renewal' =>  is_numeric($Data['csn_renewal']) ? $Data['csn_renewal'] : NULL,
				':csn_remember' =>  is_numeric($Data['csn_remember']) ? $Data['csn_remember'] : NULL

			));


			if (is_numeric($resInsertDetail2) && $resInsertDetail2 > 0) {
				// Se verifica que el detalle no de error insertando //
			} else {

				// si falla algun insert del se devuelven los cambios realizados por la transaccion,
				// se retorna el error y se detiene la ejecucion del codigo restante.
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data' => $resInsertDetail2,
					'mensaje'	=> 'No se pudo registrar el contrato'
				);

				$this->response($respuesta);

				return;
			}
			//
			//DATOS COMPLEMENTARIOS SUSCRIPCION
			if (is_array($DatosCS) && isset($DatosCS[0])) {
				foreach ($DatosCS as $key => $value) {
					foreach ($value['detalle'] as $key => $item) {
						
						$sqlInsertDetail3 = "INSERT INTO csn2(sn2_docentry, sn2_doctype, sn2_itemcode, sn2_itemdescription, sn2_daycode, sn2_period, sn2_price, sn2_susitemcode)VALUES(:sn2_docentry, :sn2_doctype, :sn2_itemcode, :sn2_itemdescription, :sn2_daycode, :sn2_period, :sn2_price, :sn2_susitemcode)";

						$resInsertDetail3 = $this->pedeo->insertRow($sqlInsertDetail3, array(

							':sn2_docentry' => $resInsert,
							':sn2_doctype' => isset($Data['csn_doctype']) ? $Data['csn_doctype'] : NULL,
							':sn2_itemcode' => $value['articulo'],
							':sn2_itemdescription' => explode("||", $item)[1],
							':sn2_daycode' => explode("||", $item)[0],
							':sn2_period' => explode("||", $value['periodo'])[0],
							':sn2_price' => explode("||", $item)[2],
							':sn2_susitemcode' => explode("||", $item)[5]

						));

						if (is_numeric($resInsertDetail3) && $resInsertDetail3 > 0) {
							// Se verifica que el detalle no de error insertando //
						} else {

							// si falla algun insert del se devuelven los cambios realizados por la transaccion,
							// se retorna el error y se detiene la ejecucion del codigo restante.
							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' => $resInsertDetail3,
								'mensaje'	=> 'No se pudo registrar el contrato'
							);

							$this->response($respuesta);

							return;
						}
					}
				}
			}
			//

			//CERRANDO LA OFERTA DE VENTA EN CASO DE COPY DE
			if ($Data['csn_basetype'] == 1) {
				$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

				$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


					':bed_docentry' => $Data['csn_baseentry'],
					':bed_doctype' => $Data['csn_basetype'],
					':bed_status' => 3, //ESTADO CERRADO
					':bed_createby' => $Data['csn_createby'],
					':bed_date' => date('Y-m-d'),
					':bed_baseentry' => $resInsert,
					':bed_basetype' => $Data['csn_doctype']
				));


				if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
				} else {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertEstado,
						'mensaje'	=> 'No se pudo registrar el contrato'
					);


					$this->response($respuesta);

					return;
				}
			}
			//



			// Si todo sale bien despues de insertar el detalle de el pedido
			// se confirma la trasaccion  para que los cambios apliquen permanentemente
			// en la base de datos y se confirma la operacion exitosa.
			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'Contrato registrado con exito'
			);
		} else {
			// Se devuelven los cambios realizados en la transaccion
			// si occurre un error  y se muestra devuelve el error.
			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data' => $resInsert,
				'mensaje'	=> 'No se pudo registrar el contrato'
			);
		}

		$this->response($respuesta);
	}

	//OBTENER CONTRATOS
	public function getContracts_get()
	{

		$Data = $this->get();

		if (!isset($Data['business']) or !isset($Data['branch'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}


		$DECI_MALES =  $this->generic->getDecimals();

		$campos = ",T4.dms_phone1, T4.dms_phone2, T4.dms_cel, coalesce(get_geoubicaciones(t0.csn_docentry, t0.csn_doctype), 0) as ubicado, t0.csn_contacid, t0.csn_idadd, t0.csn_duedev, t0.csn_enddate, t0.csn_signaturedate, t0.csn_prjcode, t0.csn_description, t0.csn_billingday, t0.csn_cleantotal, t0.csn_weeklytoilets, t0.csn_totalret, t0.csn_totalretiva";

		$variableSql = "";
		$innerjoin = "";

		if(isset($Data['slt_paycondition']) && !empty($Data['slt_paycondition'])){
			$campos .= ", d.csn_paymentcondition";
			$variableSql .= "AND d.csn_paymentcondition = ".$Data['slt_paycondition'];
		}

		if(isset($Data['slt_typeagreement']) && !empty($Data['slt_typeagreement'])){
			$campos .= ", d.csn_typeagreement";
			$variableSql .= "AND d.csn_typeagreement = ".$Data['slt_typeagreement'];
		}
		if(!empty($variableSql)){
			$innerjoin = "INNER JOIN dcsn d ON t0.csn_docentry = d.csn_docentry";
		}

		$sqlSelect = self::getColumn('tcsn', 'csn', $campos, $innerjoin, $DECI_MALES, $Data['business'], $Data['branch'], $Data['docnum'],0,0,$variableSql);
		
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

	//OBTENER CONTRATO DE SOCIO DE NEGOCIO POR ID
	// para el procso de copiar  de
	public function getContractsBySN_get()
	{

		$Data = $this->get();

		if (!isset($Data['dms_card_code']) or !isset($Data['business']) or !isset($Data['branch'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$copyData = $this->documentcopy->copyData('tcsn','csn',$Data['dms_card_code'],$Data['business'],$Data['branch'],32);

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

	//OBTENER DETALLE CONTRATO POR ID
	public function getContractsDetail_get()
	{

		$Data = $this->get();

		if (!isset($Data['sn1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT csn1.*,tcsn.csn_doctype  from csn1
						left join tcsn  on sn1_docentry = csn_docentry and sn1_doctype = tcsn.csn_doctype 
						where csn1.sn1_docentry  = :sn1_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":sn1_docentry" => $Data['sn1_docentry']));


		$sqlSelect2 = "SELECT tcsn.csn_comment,
					tcsn.csn_enddate,
					tcsn.csn_signaturedate,
					tcsn.csn_description,
					tcsn.csn_prjcode,
					ctpa.tpa_description AS csn_typeagreement,
					ccdp.cdp_description AS csn_paymentcondition,
					tmdp.mdp_name AS csn_waypay,
					cfev.fev_description AS csn_shippingway,
					cesc.esc_description AS csn_status,
					dcsn.csn_probabilitypercentage,
					dcsn.csn_renewal,
					dcsn.csn_remember
					FROM tcsn
					INNER JOIN dcsn ON tcsn.csn_docentry = dcsn.csn_docentry
					INNER JOIN ctpa ON ctpa.tpa_id = dcsn.csn_typeagreement
					INNER JOIN ccdp ON ccdp.cdp_id = dcsn.csn_paymentcondition
					INNER JOIN tmdp ON tmdp.mdp_id = dcsn.csn_waypay
					INNER JOIN cfev ON cfev.fev_id = dcsn.csn_shippingway
					INNER JOIN cesc ON cesc.esc_id = dcsn.csn_status
					WHERE  tcsn.csn_docentry = :csn_docentry";
		$resSelect2 = $this->pedeo->queryTable($sqlSelect2, array(
			':csn_docentry' => $Data['sn1_docentry']
		));

		
		foreach ($resSelect as $key => $value) {
			$sqlRetenciones = "SELECT crt_baseentry, crt_basetype, crt_typert, crt_basert, crt_profitrt, crt_totalrt, crt_base, crt_type, crt_linenum, crt_codret FROM fcrt 
			WHERE crt_baseentry = :crt_baseentry 
			and crt_basetype = :crt_basetype 
			and crt_linenum = :crt_linenum";
			$resSelectRetenciones = $this->pedeo->queryTable($sqlRetenciones, array(
				':crt_baseentry' => $Data['sn1_docentry'],
				':crt_basetype' => 32,
				':crt_linenum' => $value['sn1_linenum']
			));

			$resSelect[$key]['ret'] = $resSelectRetenciones;
		}


		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'detallec' => $resSelect2,
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

	public function getContractsDetailCopy_get()
	{

		$Data = $this->get();

		if (!isset($Data['sn1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$copy = $this->documentcopy->Copy($Data['sn1_docentry'],'tcsn','csn1','csn','sn1','clean_quantity,enabled');


		$sqlSelect2 = "SELECT tcsn.csn_comment,
				tcsn.csn_enddate,
				tcsn.csn_signaturedate,
				tcsn.csn_description,
				tcsn.csn_prjcode,
				ctpa.tpa_description AS csn_typeagreement,
				ccdp.cdp_description AS csn_paymentcondition,
				dmpf.mpf_name AS csn_waypay,
				cfev.fev_description AS csn_shippingway,
				cesc.esc_description AS csn_status,
				dcsn.csn_probabilitypercentage,
				dcsn.csn_renewal,
				dcsn.csn_remember
				FROM tcsn
				INNER JOIN dcsn ON tcsn.csn_docentry = dcsn.csn_docentry
				INNER JOIN ctpa ON ctpa.tpa_id = dcsn.csn_typeagreement
				INNER JOIN ccdp ON ccdp.cdp_id = dcsn.csn_paymentcondition
				INNER JOIN dmpf ON dmpf.mpf_id = dcsn.csn_waypay
				INNER JOIN cfev ON cfev.fev_id = dcsn.csn_shippingway
				INNER JOIN cesc ON cesc.esc_id = dcsn.csn_status
				WHERE  tcsn.csn_docentry = :csn_docentry";
		$resSelect2 = $this->pedeo->queryTable($sqlSelect2, array(
			':csn_docentry' => $Data['sn1_docentry']
		));

		if (isset($copy[0])) {

			
			foreach ($copy as $key => $value) {

				$artic = $this->pedeo->queryTable("select t1.*, dmum.dmu_code, dmtx.dmi_code, dmtx.dmi_rate_tax,
					costo_por_almacen(:item, :almacen) as costo
					from dmar as t1
					inner join dmum on t1.dma_uom_sale = dmum.dmu_id 
					inner join dmtx on t1.dma_tax_sales_code  = dmtx.dmi_code 
					where t1.dma_item_code in (select t0.dma_item_asset from dmar as t0 where  t0.dma_item_code =:dma_item_code)"
					, array(":dma_item_code" => $value['sn1_itemcode'], ":item" => $value['sn1_itemcode'], ":almacen" => $value['sn1_whscode']));

				if ( isset($artic[0]) ) {
					$copy[$key]['item_asociado'] = $artic[0]['dma_item_name'];
					$copy[$key]['item_asociado_codigo'] = $artic[0]['dma_item_code'];
					$copy[$key]['item_asociado_codigo_impuesto'] = $artic[0]['dmi_code'];
					$copy[$key]['item_asociado_codigo_unidad'] = $artic[0]['dmu_code'];
					$copy[$key]['item_asociado_codigo_tasa_impuesto'] = $artic[0]['dmi_rate_tax'];
					$copy[$key]['item_asociado_menejainventario'] = $artic[0]['dma_item_inv'];
					$copy[$key]['item_asociado_manejalote'] = $artic[0]['dma_lotes_code'];
					$copy[$key]['item_asociado_manejaserial'] = $artic[0]['dma_series_code'];
					$copy[$key]['item_asociado_costo'] = $artic[0]['costo'];
				}


				$sqlRetenciones = "SELECT crt_baseentry, crt_basetype, crt_typert, crt_basert, crt_profitrt, crt_totalrt, crt_base, crt_type, crt_linenum, crt_codret FROM fcrt 
				WHERE crt_baseentry = :crt_baseentry 
				and crt_basetype = :crt_basetype
				and crt_linenum = :crt_linenum";

				$resSelectRetenciones = $this->pedeo->queryTable($sqlRetenciones, array(
					':crt_baseentry' => $Data['sn1_docentry'],
					':crt_basetype' => 32,
					':crt_linenum' => $value['sn1_linenum']
				));
	
				$copy[$key]['retenciones'] = $resSelectRetenciones;
				
			}

			$respuesta = array(
				'error' => false,
				'data'  => $copy,
				'detallec' => $resSelect2,
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

	public function getDaysPrice_post()
	{
		$Data = $this->post();

		$Data = json_decode($Data['data'], true);

		$respuesta = array(
			'error'   => false,
			'data' 		=> [],
			'mensaje'	=> 'busqueda sin resultados'
		);
		$result = [];

		if (!is_array($Data)) {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'El filtro no es valido'
			);
		}


		foreach ($Data as $key => $value) {


			$sql = "SELECT ded_smdid, ded_sdeid, sde_description, smd_description, ded_price,
					dma_item_code,dma_item_name
					FROM rded
					INNER JOIN csmd
					ON ded_smdid = csmd.smd_id
					INNER JOIN csde
					ON ded_sdeid = csde.sde_id
					INNER JOIN dmar
					ON csde.sde_itemcode = dma_item_code
					WHERE ded_sdeid = :ded_sdeid
					AND rded.ded_status = :ded_status";
			$ressql = $this->pedeo->queryTable($sql, array(
				':ded_sdeid'  => $value,
				':ded_status' => 1
			));

			if (isset($ressql[0])) {

				$result["'" . $ressql[0]['sde_description'] . "'"] = $ressql;
			}

			$respuesta = array(
				'error'   => false,
				'data' 		=> $result,
				'mensaje'	=> 'busqueda sin resultados'
			);
		}


		$this->response($respuesta);
	}

	public function getContractsById_get()
	{

		$Data = $this->get();

		if (!isset($Data['csn_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM tcsn WHERE csn_docentry =:csn_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":csn_docentry" => $Data['csn_docentry']));

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

	private function validateDate($fecha)
	{
		if (strlen($fecha) == 10 or strlen($fecha) > 10) {
			return true;
		} else {
			return false;
		}
	}

	private function setAprobacion($Encabezado, $Detalle, $Carpeta, $prefijoe, $prefijod)
	{

		$sqlInsert = "INSERT INTO dpap(pap_series, pap_docnum, pap_docdate, pap_duedate, pap_duedev, pap_pricelist, pap_cardcode,
									pap_cardname, pap_currency, pap_contacid, pap_slpcode, pap_empid, pap_comment, pap_doctotal, pap_baseamnt, pap_taxtotal,
									pap_discprofit, pap_discount, pap_createat, pap_baseentry, pap_basetype, pap_doctype, pap_idadd, pap_adress, pap_paytype,
									pap_createby,pap_origen)VALUES(:pap_series, :pap_docnum, :pap_docdate, :pap_duedate, :pap_duedev, :pap_pricelist, :pap_cardcode, :pap_cardname,
									:pap_currency, :pap_contacid, :pap_slpcode, :pap_empid, :pap_comment, :pap_doctotal, :pap_baseamnt, :pap_taxtotal, :pap_discprofit, :pap_discount,
									:pap_createat, :pap_baseentry, :pap_basetype, :pap_doctype, :pap_idadd, :pap_adress, :pap_paytype,:pap_createby,:pap_origen)";

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

	public function notificationContracts_post()
	{
		$Data = $this->post();
		

		if(!isset($Data['business']) or !isset($Data['branch'])){
			
			$respuesta = array(
				'error' => true,
				'data' => [],
				'mensaje' => 'Informacion invalida'
			);

			return $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);;
		}


		$sql = "SELECT
					tcsn.csn_cardcode,
					tcsn.csn_cardname,
					tcsn.csn_docnum,
					dcsn.csn_remember,
					abs(tcsn.csn_enddate - current_date) dif_day,
					tcsn.csn_docentry,
					tcsn.csn_doctype
				from tcsn
				inner join dcsn on tcsn.csn_docentry = dcsn.csn_docentry
				where ((tcsn.csn_enddate - current_date)) <= dcsn.csn_remember
				and tcsn.business = :business and tcsn.branch = :branch;";
				
		$resSql = $this->pedeo->queryTable($sql,array(
			':business' => $Data['business'],
			':branch' => $Data['branch']
		));

		if(isset($resSql[0])){
			$respuesta = array(
				'error' => false,
				'data' => $resSql,
				'mensaje' => 'OK'
			);
		}else{

			$respuesta = array(
				'error' => true,
				'data' => [],
				'mensaje' => 'No se encontraron datos en la busqueda'
			);

			return $respuesta;
		}

		$this->response($respuesta);
	}

	public function createSuspensionContracts_post() 
	{
		//
		$Data = $this->post();
		//
		if ( !isset($Data['scs_doctype']) OR !isset($Data['scs_docentry']) OR !isset($Data['scs_start_date']) OR !isset($Data['scs_end_date']) OR !isset($Data['scs_createdby']) ){
			//
			$respuesta = array(
				'error' => true,
				'data' => [],
				'mensaje' => 'Faltan parametros'
			);
			//
			return $this->response($respuesta);
		}
		//
		$sqlValidate = "SELECT * FROM hscs WHERE scs_end_date > current_date AND scs_docentry = :scs_docentry AND scs_doctype =:scs_doctype AND scs_status = :scs_status";
		//
		$resValidate = $this->pedeo->queryTable($sqlValidate, array(':scs_docentry' => $Data['scs_docentry'], ':scs_doctype' => $Data['scs_doctype'], ':scs_status' => 1));

		if ( isset($resValidate[0]) ){
			//
			$respuesta = array(
				'error' => true,
				'data' => [],
				'mensaje' => 'Ya existe una suspensión activa para este contrato'
			);
			//
			return $this->response($respuesta);
		}
		//
		$sqlInsert = "INSERT INTO hscs(scs_doctype, scs_docentry, scs_reason, scs_start_date, scs_end_date, scs_createdby, scs_createdat, scs_status)VALUES(:scs_doctype, :scs_docentry, :scs_reason, :scs_start_date, :scs_end_date, :scs_createdby, :scs_createdat, :scs_status)";
		//
		$this->pedeo->trans_begin();
		//
		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			
			':scs_doctype' => $Data['scs_doctype'], 
			':scs_docentry' => $Data['scs_docentry'], 
			':scs_reason' => $Data['scs_reason'], 
			':scs_start_date' => $Data['scs_start_date'], 
			':scs_end_date' => $Data['scs_end_date'], 
			':scs_createdby' => $Data['scs_createdby'], 
			':scs_createdat' => date("Y-m-d"), 
			':scs_status' => 1
		));
		//
		if ( is_numeric($resInsert) && $resInsert > 0 ){
			//
			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'Operación exitosa'
			);
			//
		}else{
			//
			$this->pedeo->trans_rollback();
			//
			$respuesta = array(
				'error' => true,
				'data' => [],
				'mensaje' => 'No se pudo crear la suspensión del contrato'
			);
			//
			$this->response($respuesta);
			//
			return;
		}
		//
		//SE INSERTA EL ESTADO DEL DOCUMENTO
		$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
		VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";
		//
		$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(
			':bed_docentry' => $Data['scs_docentry'],
			':bed_doctype' => $Data['scs_doctype'],
			':bed_status' => 9, //ESTADO CERRADO
			':bed_createby' => $Data['scs_createdby'],
			':bed_date' => date('Y-m-d'),
			':bed_baseentry' => NULL,
			':bed_basetype' => NULL
		));
		//
		if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
			//
		}else {
			//
			$this->pedeo->trans_rollback();
			//
			$respuesta = array(
				'error'   => true,
				'data' => $resInsertEstado,
				'mensaje'	=> 'No se pudo registrar la Entrega de ventas'
			);
			//
			$this->response($respuesta);
			//
			return;
		}
		//
		$this->pedeo->trans_commit();
		//
		return $this->response($respuesta);
	}

	// ACTUALIZAR CONDICIONES DEL CONTRATO DE SOCIOS
	public function updateContractConditions_post() {


		$Data = $this->post();

		if(
            !isset($Data['csn_docentry']) OR
            !isset($Data['csn_doctype']) OR
            !isset($Data['csn_typeagreement']) OR
            !isset($Data['csn_paymentcondition']) OR
			!isset($Data['csn_waypay']) OR
			!isset($Data['csn_shippingway']) OR
			!isset($Data['csn_probabilitypercentage']) OR
			!isset($Data['csn_status']) OR
			!isset($Data['csn_renewal']) OR
			!isset($Data['csn_remember']) OR
			!isset($Data['detail'])
        ){

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'Faltan parametros'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;

        }


		$ContenidoDetalle = json_decode($Data['detail'], true);

		if ( is_array($ContenidoDetalle) && count($ContenidoDetalle) > 0 ) {

		} else {

			$respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'No se encontro el detalle'
            );

            $this->response($respuesta);

            return;

		}

		$this->pedeo->trans_begin();

		$this->pedeo->deleteRow("DELETE FROM dcsn WHERE csn_docentry = :csn_docentry", array(":csn_docentry" => $Data['csn_docentry']));


		// DATOS COMPLEMNETARIO DEL CONTRATOS
		$sqlInsertDetail2 = "INSERT INTO dcsn(csn_docentry, csn_doctype, csn_typeagreement, csn_paymentcondition, csn_waypay, csn_shippingway, csn_probabilitypercentage, csn_status, csn_renewal, csn_remember)
							VALUES (:csn_docentry, :csn_doctype, :csn_typeagreement, :csn_paymentcondition, :csn_waypay, :csn_shippingway, :csn_probabilitypercentage, :csn_status, :csn_renewal, :csn_remember)";

		$resInsertDetail2 = $this->pedeo->insertRow($sqlInsertDetail2, array(

			':csn_docentry' => $Data['csn_docentry'],
			':csn_doctype' => isset($Data['csn_doctype']) ? $Data['csn_doctype'] : NULL,
			':csn_typeagreement' =>  is_numeric($Data['csn_typeagreement']) ? $Data['csn_typeagreement'] : NULL,
			':csn_paymentcondition' =>  is_numeric($Data['csn_paymentcondition']) ? $Data['csn_paymentcondition'] : NULL,
			':csn_waypay' =>  is_numeric($Data['csn_waypay']) ? $Data['csn_waypay'] : NULL,
			':csn_shippingway' =>  is_numeric($Data['csn_shippingway']) ? $Data['csn_shippingway'] : NULL,
			':csn_probabilitypercentage' =>  is_numeric($Data['csn_probabilitypercentage']) ? $Data['csn_probabilitypercentage'] : NULL,
			':csn_status' =>  is_numeric($Data['csn_status']) ? $Data['csn_status'] : NULL,
			':csn_renewal' =>  is_numeric($Data['csn_renewal']) ? $Data['csn_renewal'] : NULL,
			':csn_remember' =>  is_numeric($Data['csn_remember']) ? $Data['csn_remember'] : NULL

		));


		if (is_numeric($resInsertDetail2) && $resInsertDetail2 > 0) {

			$respuesta = array (
				'error'   => false,
				'data' 	  => [],
				'mensaje' => 'Condiciones actualizadas con exito'
			);

			
		} else {
			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data' => $resInsertDetail2,
				'mensaje'	=> 'No se pudo actualizar la condición del contrato'
			);

			$this->response($respuesta);

			return;

		}

		$datosCabera = "UPDATE tcsn SET  csn_docdate = :csn_docdate, csn_duedate = :csn_duedate, csn_duedev = :csn_duedev, csn_contacid = :csn_contacid, 
				csn_comment = :csn_comment, csn_idadd = :csn_idadd, csn_adress = :csn_adress, csn_enddate = :csn_enddate, csn_signaturedate = :csn_signaturedate,
				csn_description = :csn_description, csn_prjcode = :csn_prjcode, csn_internal_comments = :csn_internal_comments, csn_billingday = :csn_billingday,
				csn_cleantotal = :csn_cleantotal, csn_weeklytoilets = :csn_weeklytoilets
				WHERE csn_docentry = :csn_docentry";

		$resDatosCabecera = $this->pedeo->updateRow($datosCabera, array(
			
			':csn_docdate' => $Data['csn_docdate'], 
			':csn_duedate' => $Data['csn_duedate'], 
			':csn_duedev' => $Data['csn_duedev'], 
			':csn_contacid' => $Data['csn_contacid'], 
			':csn_comment' => $Data['csn_comment'], 
			':csn_idadd' => $Data['csn_idadd'], 
			':csn_adress' => $Data['csn_adress'], 
			':csn_enddate' => $Data['csn_enddate'], 
			':csn_signaturedate' => $Data['csn_signaturedate'], 
			':csn_description' => $Data['csn_description'], 
			':csn_prjcode' => $Data['csn_prjcode'], 
			':csn_internal_comments' => $Data['csn_internal_comments'], 
			':csn_billingday' => $Data['csn_billingday'],
			':csn_cleantotal' => $Data['csn_cleantotal'],
			':csn_docentry' => $Data['csn_docentry'],
			':csn_weeklytoilets' => $Data['csn_weeklytoilets']
		));

		if ( is_numeric($resDatosCabecera) &&  $resDatosCabecera == 1 ) {

			$respuesta = array (
				'error'   => false,
				'data' 	  => [],
				'mensaje' => 'Contrato actualizado con exito'
			);
		
		} else {

			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data' => $resInsertDetail2,
				'mensaje'	=> 'No se pudo actualizar el contrato'
			);

			return $this->response($respuesta);
			
		}

		foreach ($ContenidoDetalle as $key => $detail) {
			
			$sqlUpdate = "UPDATE csn1 set  sn1_clean_quantity = :sn1_clean_quantity, sn1_enabled = :sn1_enabled WHERE sn1_id = :sn1_id";


			$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
				
				':sn1_id' => $detail['sn1_id'], 
				':sn1_clean_quantity' => $detail['sn1_clean_quantity'], 
				':sn1_enabled' => $detail['sn1_enabled']
			));

			if ( is_numeric($resUpdate) &&  $resUpdate == 1 ) {

				$respuesta = array (
					'error'   => false,
					'data' 	  => [],
					'mensaje' => 'Contrato actualizado con exito'
				);
			
			} else {
	
				$this->pedeo->trans_rollback();
	
				$respuesta = array(
					'error'   => true,
					'data' => $resInsertDetail2,
					'mensaje'	=> 'No se pudo actualizar el contrato'
				);
	
				return $this->response($respuesta);
				
			}
		}

		$this->pedeo->trans_commit();


		$this->response($respuesta);

	}

	// OBTENER CONDICIONES DE CONTRATO
	public function getContractConditions_post() {


		$Data = $this->post();

		if(
			!isset($Data['csn_docentry'])
		){

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'Faltan parametros'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;

		}

		$sqlSelect = "SELECT * FROM dcsn WHERE csn_docentry = :csn_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array (

			':csn_docentry' => $Data['csn_docentry']
		));


		if ( isset($resSelect[0]) ) {

			$respuesta = array (
				'error'   => false,
				'data' 	  => $resSelect,
				'mensaje' => ''
			);

			
		} else {

			$respuesta = array(
				'error'   => true,
				'data' 	  => [],
				'mensaje' => 'Busqueda sin resultados'
			);

		}


		$this->response($respuesta);

	}

	// MOSTRAR CONTRATOS VS FACTURAS
	public function getContractInvoice_post(){

		$Data = $this->post();

		$complementoSQL = "";


		if ( $Data['s_f'] == 1 ){

			$complementoSQL = " AND dvf_docdate is null ";

			if( isset($Data['fi']) && !empty($Data['fi']) ) {

				if( isset($Data['ff']) && !empty($Data['ff']) ) {
	
					$complementoSQL.= " AND csn_docdate between '".$Data['fi']."' AND '".$Data['ff']."' ";
	
				}
			}

		}else{

			if( isset($Data['fi']) && !empty($Data['fi']) ) {

				if( isset($Data['ff']) && !empty($Data['ff']) ) {
	
					if ( $Data['f_c'] == 1 ){
						$complementoSQL.= " AND csn_docdate between '".$Data['fi']."' AND '".$Data['ff']."' ";
	
					}else{
						$complementoSQL.= " AND dvf_docdate between '".$Data['fi']."' AND '".$Data['ff']."' ";
					}
				
				}
			}
		}

		

		$sqlSelect = "SELECT csn_docnum as numero_contrato,
					csn_docentry as codigo_contrato,
					csn_cardcode as identificacion_cliente,
					csn_cardname  as nombre_cliente,
					dvf_docnum as numero_factura,
					dvf_docentry as codigo_factura,
					dvf_docdate as fecha_factura
					FROM tcsn
					LEFT JOIN dvfv  on dvf_baseentry = csn_docentry and dvf_basetype = csn_doctype 
					INNER JOIN responsestatus on responsestatus.id = tcsn.csn_docentry and responsestatus.tipo = tcsn.csn_doctype 
					WHERE csn_doctype = 32
					AND responsestatus.estado = '".$Data['estado']."'
					AND tcsn.business = ".$Data['business']."
					".$complementoSQL."";

					
		
		$resSelect = $this->pedeo->queryTable($sqlSelect, array());


		if ( isset($resSelect[0]) ) {

			$respuesta = array (
				'error'   => false,
				'data' 	  => $resSelect,
				'mensaje' => ''
			);

			
		} else {

			$respuesta = array(
				'error'   => true,
				'data' 	  => [],
				'mensaje' => 'Busqueda sin resultados'
			);

		}


		$this->response($respuesta);

	}

	// MOSTRAR VISTA PREVIA DE LOS CONTRATOS A FACTURAR AUTOMATICAMENTE
	public function getContratToInvoice_post() {
		$Data = $this->post();


		$sqlSelect = "SELECT tcsn.*,
					case 
						when csn_billingday is null OR csn_billingday = 0 then 0
						else 1
					end as sefactura
					FROM tcsn
					inner join responsestatus on csn_docentry = id and csn_doctype = tipo
					inner join tbed on bed_docentry = csn_docentry and bed_doctype = csn_doctype
					inner join dcsn on  tcsn.csn_docentry = dcsn.csn_docentry
					WHERE tcsn.csn_cardcode in (select dmsn.dms_card_code  
					FROM dmsn inner join dmgs on dmsn.dms_group_num = dmgs.mgs_id::text where dmgs.mgs_code != '99' and dmsn.dms_card_type  = '1')
					AND responsestatus.estado = 'Abierto'
					AND tcsn.csn_doctype = 32
					AND dcsn.csn_typeagreement = 3
					AND tcsn.csn_docentry = 10045";
		
		$resSelect = $this->pedeo->queryTable($sqlSelect, array());

	

		foreach ($resSelect as $key => $value) {

			$totalReteFuente = 0;
			$totalReteIva = 0;
			$totaDoc = 0;

			if ($value['sefactura'] == 1) {

				$valido = false;
				$des = 0;
				$acu = 0;
				while (!$valido) {

					$fecha = date("Y").'-'.date("m").'-'.str_pad(( $value['csn_billingday'] - $des ).'', 2, '0', STR_PAD_LEFT);

					if ( self::esFechaValida($fecha) ) {

						$resSelect[$key]['comentariosistema'] = "La Facturación de este documento se ejecutara de forma automatica en el dia: ".$fecha;
						$resSelect[$key]['fechafacturacion']  = $fecha;
						$valido = true;

					} else {

						$des++;
					}

					$acu++;

					if ( $acu > 9 ) {

						$valido = true;
						$resSelect[$key]['comentariosistema'] = "No es posible facturar este documento, no posee el dia de factura";
						$resSelect[$key]['fechafacturacion']  = "";
					}
					
				}

			} else {

				$resSelect[$key]['comentariosistema'] = "No es posible facturar este documento, no posee el dia de factura";
				$resSelect[$key]['fechafacturacion']  = "";
			}



			if ($value['sefactura'] == 1) {

				// VARIFICAR SI LA LINEA TIENE RETENCIONES APLICADAS

				$sqlRetencion = "SELECT fc.crt_codret,fc.crt_typert,fc.crt_type,fc.crt_basert,fc.crt_profitrt,
				fc.crt_base,crt_totalrt
				FROM csn1
				INNER JOIN fcrt fc ON csn1.sn1_docentry = fc.crt_baseentry 
				AND csn1.sn1_linenum = fc.crt_linenum
				AND fc.crt_basetype = 32
				AND sn1_enabled = 1
				WHERE sn1_docentry = :sn1_docentry";

				$resRetencion = $this->pedeo->queryTable($sqlRetencion, array(':sn1_docentry' => $value['csn_docentry']));

				if (isset($resRetencion[0])) {

					foreach ($resRetencion as $key2 => $ret) {
						// RETENCION DE FUENTE E ICA
						if ( $ret['crt_type'] == 1 || $ret['crt_type'] == 2 ) {

							$totalReteFuente = $totalReteFuente +  $ret['crt_totalrt'];

						// RETENCION DE IVA    
						} else if ($ret['crt_type'] == 3) {

							$totalReteIva = $totalReteIva +  $ret['crt_totalrt'];
						}
					}
				}

				// VERIFICAR MONTO TOTAL A APLICAR
				$resDetalle = $this->pedeo->queryTable('SELECT * FROM csn1 WHERE sn1_enabled = 1 AND sn1_docentry = :sn1_docentry', array (

					':sn1_docentry' => $value['csn_docentry']

				) );

				if ( isset($resDetalle[0]) ) {

					$resSelect[$key]['facturando'] = 'SI'; 


					foreach ($resDetalle as $key3 => $detalleF) {
						$totaDoc = $totaDoc + ( $detalleF['sn1_linetotal'] + $detalleF['sn1_vatsum'] );
					}

					$resSelect[$key]['csn_doctotal'] = ( ( $totaDoc - $totalReteFuente ) - $totalReteIva ) ;

				} else {
					$resSelect[$key]['facturando'] = 'NO'; 
				}

			}else{
				$resSelect[$key]['facturando'] = 'NO'; 
			}
		}

		if ( isset($resSelect[0]) ) {

			$respuesta = array (
				'error'   => false,
				'data' 	  => $resSelect,
				'mensaje' => ''
			);

			
		} else {

			$respuesta = array(
				'error'   => true,
				'data' 	  => [],
				'mensaje' => 'Busqueda sin resultados'
			);

		}


		$this->response($respuesta);
	}

	public function updateContratRetentions_post(){
		$Data = $this->post();

		if (
			!isset($Data['csn_docentry']) or
			!isset($Data['csn_doctype']) or
			!isset($Data['csn_typeagreement']) or
			!isset($Data['csn_paymentcondition']) or
			!isset($Data['csn_waypay']) or
			!isset($Data['csn_shippingway']) or
			!isset($Data['csn_probabilitypercentage']) or
			!isset($Data['csn_status']) or
			!isset($Data['csn_renewal']) or
			!isset($Data['csn_remember']) or
			!isset($Data['detail'])
		) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'Faltan parametros'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$ContenidoDetalle = json_decode($Data['detail'], true);
		
		if ( is_array($ContenidoDetalle) && count($ContenidoDetalle) > 0 ) {

		} else {

			$respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'No se encontro el detalle'
            );

            $this->response($respuesta);

            return;

		}

		$sqlSelect = "SELECT * from tcsn where csn_docentry =:csn_docentry and business = :business and branch =:branch";
		$resSelect = $this->pedeo->queryTable($sqlSelect, array(
			":csn_docentry" => $Data['csn_docentry'],
			":business" => $Data['business'],
			":branch" => $Data['branch']
		));

		if (!isset($resSelect[0])) {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No existe el contrato asociado'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$this->pedeo->trans_begin();

		$updateContrat = "UPDATE tcsn SET 
		csn_totalret = :csn_totalret,
		csn_totalretiva = :csn_totalretiva,
		csn_doctotal = :csn_doctotal,
		csn_baseamnt = :csn_baseamnt,
		csn_taxtotal = :csn_taxtotal,
		csn_discprofit = :csn_discprofit,
		csn_discount = :csn_discount,
		csn_taxtotal_ad = :csn_taxtotal_ad
		where 
		csn_docentry =:csn_docentry";


		$resUpdateContrat = $this->pedeo->updateRow($updateContrat, array(
			":csn_totalret" => $Data['csn_totalret'],
			":csn_totalretiva" => $Data['csn_totalretiva'],
			":csn_doctotal" => $Data['csn_doctotal'],
			":csn_baseamnt" => $Data['csn_baseamnt'],
			":csn_taxtotal" => $Data['csn_taxtotal'],
			":csn_discprofit" => $Data['csn_discprofit'],
			":csn_discount" => $Data['csn_discount'],
			":csn_taxtotal_ad" => $Data['csn_taxtotal_ad'],
			":csn_docentry" => $Data['csn_docentry'],
		));

		if (is_numeric($resUpdateContrat) &&  $resUpdateContrat == 1) {

			$this->pedeo->DeleteRow(
				"DELETE FROM fcrt where crt_baseentry = :crt_baseentry and crt_basetype =:crt_basetype",
				array(
					':crt_baseentry' => $Data['csn_docentry'],
					':crt_basetype'  => $Data['csn_doctype']
				)
			);

			

			foreach ($ContenidoDetalle as $key => $detail) {
				if (isset($detail['detail'])) {
					$retenciones = $detail['detail'];
					foreach ($retenciones as $key => $value) {
						$sqlInsertRetenciones = "INSERT INTO fcrt(crt_baseentry, crt_basetype, crt_typert, crt_basert, crt_profitrt, crt_totalrt, crt_base, crt_type, crt_linenum, crt_codret)
											VALUES (:crt_baseentry, :crt_basetype, :crt_typert, :crt_basert, :crt_profitrt, :crt_totalrt, :crt_base, :crt_type, :crt_linenum, :crt_codret)";

						$resInsertRetenciones = $this->pedeo->insertRow($sqlInsertRetenciones, array(

							':crt_baseentry' => $Data['csn_docentry'],
							':crt_basetype'  => $Data['csn_doctype'],
							':crt_typert'    => $value['crt_typert'],
							':crt_basert'    => $value['crt_basert'],
							':crt_profitrt'  => $value['crt_profitrt'],
							':crt_totalrt'   => $value['crt_totalrt'],
							':crt_base'		 => $value['crt_base'],
							':crt_type'		 => $value['crt_type'],
							':crt_linenum'   => $detail['sn1_linenum'],
							':crt_codret'	 => $value['crt_codret']
						));

						if (is_numeric($resInsertRetenciones) && $resInsertRetenciones > 0) {
						} else {
							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error' => true,
								'data'  => array(),
								'mensaje' => 'No se puede registrar retencion'
							);

							$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

							return;
						}
					}
				}
			}
			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data'  => [],
				'mensaje' => 'Contrato actualizado con exito'
			);
		} else {
			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se pudo actualizar contrato'
			);

			return;
		}

		$this->response($respuesta);

	}



	// VALIDA SI UNA FECHA ESTA EN EL CALENDARIO
	private static function esFechaValida($fecha) {
		// Separa la fecha en componentes (día, mes, año)
		$componentes = explode('-', $fecha);
	
		// Verifica si la fecha tiene el formato correcto (YYYY-MM-DD)
		if (count($componentes) !== 3) {
			return false;
		}
	
		$año = (int)$componentes[0];
		$mes = (int)$componentes[1];
		$día = (int)$componentes[2];
	
		// Usa checkdate para verificar si la fecha es válida
		return checkdate($mes, $día, $año);
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



}