<?php
// ORDEN DE COMPRA
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class PurchSolAntProv extends REST_Controller
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
		$this->load->library('Tasa');
		$this->load->library('DocumentNumbering');
	}

	//CREAR NUEVA ORDEN DE COMPRA
	public function createPurchSolAntProv_post()
	{
		$Data = $this->post();
		
		$DECI_MALES =  $this->generic->getDecimals();

		$TasaDocLoc = 0;
		$TasaLocSys = 0;
		$MONEDALOCAL = "";
		$MONEDASYS = "";

		$DocNumVerificado = 0;
		$CANTUOMPURCHASE = 0; //CANTIDAD EN UNIDAD DE MEDIDA

		if (!isset($Data['detail']) OR 
			!isset($Data['business']) OR
			!isset($Data['branch'])) {

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
				'mensaje' => 'No se encontro el detalle de la orden de compra'
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
		// BUSCANDO LA NUMERACION DEL DOCUMENTO
		$DocNumVerificado = $this->documentnumbering->NumberDoc($Data['csa_series'],$Data['csa_docdate'],$Data['csa_duedate']);
		
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

		// FIN PROCEDIMIENTO PARA OBTENER CARPETA Principal

		//PROCESO DE TASA
		$dataTasa = $this->tasa->Tasa($Data['csa_currency'],$Data['csa_docdate']);

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

			':bed_docentry' => $Data['csa_baseentry'],
			':bed_doctype'  => $Data['csa_basetype'],
			':bed_status'   => 4 // 4 APROBADO SEGUN MODELO DE APROBACION
		));


		// VERIFICA EL MODELO DE APROBACION
		if (!isset($resVerificarAprobacion[0])) {

			$sqlDocModelo = "SELECT mau_docentry as modelo, mau_doctype as doctype, mau_quantity as cantidad,
							au1_doctotal as doctotal,au1_doctotal2 as doctotal2, au1_c1 as condicion, mau_currency
							FROM tmau
							INNER JOIN mau1
							ON mau_docentry =  au1_docentry
							INNER JOIN taus
							ON mau_docentry  = aus_id_model
							INNER JOIN pgus
							ON aus_id_usuario = pgu_id_usuario
							WHERE mau_doctype = :mau_doctype
							AND pgu_code_user = :pgu_code_user
							AND mau_status = :mau_status
							AND tmau.business = :business
							AND aus_status = :aus_status";

			$resDocModelo = $this->pedeo->queryTable($sqlDocModelo, array(

				':mau_doctype'   => $Data['csa_doctype'],
				':pgu_code_user' => $Data['csa_createby'],
				':mau_status' 	 => 1,
				':aus_status' 	 => 1,
				':business'		 => $Data['business']

			));

			if (isset($resDocModelo[0])) {

				foreach ($resDocModelo as $key => $value) {

					//VERIFICAR MODELO DE APROBACION
					$condicion = $value['condicion'];
					$valorDocTotal1 = $value['doctotal'];
					$valorDocTotal2 = $value['doctotal2'];
					$TotalDocumento = $Data['csa_doctotal'];
					$doctype =  $value['doctype'];
					$modelo = $value['modelo'];

					$sqlTasaMonedaModelo = "SELECT COALESCE(get_dynamic_conversion(:mau_currency,:doc_currency,:doc_date,:doc_total,get_localcur()), 0) AS monto"; 
					$resTasaMonedaModelo = $this->pedeo->queryTable($sqlTasaMonedaModelo, array(
						':mau_currency' => $value['mau_currency'],
						':doc_currency' => $Data['csa_currency'],
						':doc_date' 	=> $Data['csa_docdate'],
						':doc_total' 	=> $TotalDocumento
					));

					if ( $resTasaMonedaModelo[0]['monto'] == 0 ){
						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' => 'No se encrontro la tasa de cambio para la moneda del modelo :'. $value['mau_currency'].'en la fecha del documento '.$Data['csa_docdate']
						);
			
						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
			
						return;
					}

					$TotalDocumento =  $resTasaMonedaModelo[0]['monto'];

					if ($condicion == ">") {

						$sq = " SELECT mau_quantity,mau_approvers,mau_docentry
								FROM tmau
								INNER JOIN  mau1
								on mau_docentry =  au1_docentry
								AND :au1_doctotal > au1_doctotal
								AND mau_doctype = :mau_doctype
								AND mau_docentry = :mau_docentry";

						$ressq = $this->pedeo->queryTable($sq, array(

							':au1_doctotal' => $TotalDocumento,
							':mau_doctype'  => $doctype,
							':mau_docentry' => $modelo
						));

						if (isset($ressq[0])) {

							$resAprobacion = $this->aprobacion->setAprobacion($Data, $ContenidoDetalle, 'csa', 'sa1', $ressq[0]['mau_quantity'], count(explode(',', $ressq[0]['mau_approvers'])), $ressq[0]['mau_docentry'], $Data['business'], $Data['branch']);

							if ($resAprobacion['error'] == false){

								$respuesta = array(
									'error'   => false,
									'data'    => [],
									'mensaje' => $resAprobacion['mensaje'],
									
								);

								return $this->response($respuesta);

							}else{

								$respuesta = array(
									'error'   => true,
									'data'    => $resAprobacion,
									'mensaje' => $resAprobacion['mensaje'],
									
								);

								return $this->response($respuesta);

							}

						}
					} else if ($condicion == "BETWEEN") {

						$sq = " SELECT mau_quantity,mau_approvers,mau_docentry
								FROM tmau
								INNER JOIN  mau1
								on mau_docentry =  au1_docentry
								AND cast(:doctotal as numeric) between au1_doctotal AND au1_doctotal2
								AND mau_doctype = :mau_doctype
								AND mau_docentry = :mau_docentry";

						$ressq = $this->pedeo->queryTable($sq, array(

							':doctotal' 	 => $TotalDocumento,
							':mau_doctype' => $doctype,
							':mau_docentry' => $modelo
						));

						if (isset($ressq[0])) {

							$resAprobacion =  $this->aprobacion->setAprobacion($Data, $ContenidoDetalle, 'csa', 'sa1', $ressq[0]['mau_quantity'], count(explode(',', $ressq[0]['mau_approvers'])), $ressq[0]['mau_docentry'], $Data['business'], $Data['branch']);

							if ($resAprobacion['error'] == false){

								$respuesta = array(
									'error'   => false,
									'data'    => [],
									'mensaje' => $resAprobacion['mensaje'],
									
								);

								return $this->response($respuesta);

							}else{

								$respuesta = array(
									'error'   => true,
									'data'    => $resAprobacion,
									'mensaje' => $resAprobacion['mensaje'],
									
								);

								return $this->response($respuesta);

							}
						}
					}
					//VERIFICAR MODELO DE PROBACION
				}
			}
		}
		// FIN PROESO DE VERIFICAR SI EL DOCUMENTO A CREAR NO  VIENE DE UN PROCESO DE APROBACION Y NO ESTE APROBADO
		$sqlInsert = "INSERT INTO dcsa(csa_series, csa_docnum, csa_docdate, csa_duedate, csa_duedev, csa_pricelist, csa_cardcode,
                      csa_cardname, csa_currency, csa_contacid, csa_slpcode, csa_empid, csa_comment, csa_doctotal, csa_baseamnt, csa_taxtotal,
                      csa_discprofit, csa_discount, csa_createat, csa_baseentry, csa_basetype, csa_doctype, csa_idadd, csa_adress, csa_paytype,
                      csa_createby,csa_correl, csa_date_inv, csa_date_del, csa_place_del,business,branch, csa_anticipate_total, csa_anticipate_type, 
					  csa_anticipate_value, csa_paytoday,csa_taxtotal_ad)VALUES(:csa_series, :csa_docnum, :csa_docdate, :csa_duedate, :csa_duedev, :csa_pricelist, :csa_cardcode, :csa_cardname,
                      :csa_currency, :csa_contacid, :csa_slpcode, :csa_empid, :csa_comment, :csa_doctotal, :csa_baseamnt, :csa_taxtotal, :csa_discprofit, :csa_discount,
                      :csa_createat, :csa_baseentry, :csa_basetype, :csa_doctype, :csa_idadd, :csa_adress, :csa_paytype,:csa_createby,:csa_correl, 
					  :csa_date_inv, :csa_date_del, :csa_place_del,:business,:branch, :csa_anticipate_total, :csa_anticipate_type, :csa_anticipate_value , 
					  :csa_paytoday,:csa_taxtotal_ad)";


		// Se Inicia la transaccion,
		// Todas las consultas de modificacion siguientes
		// aplicaran solo despues que se confirme la transaccion,
		// de lo contrario no se aplicaran los cambios y se devolvera
		// la base de datos a su estado original.

		$this->pedeo->trans_begin();

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			':csa_docnum' => $DocNumVerificado,
			':csa_series' => is_numeric($Data['csa_series']) ? $Data['csa_series'] : 0,
			':csa_docdate' => $this->validateDate($Data['csa_docdate']) ? $Data['csa_docdate'] : NULL,
			':csa_duedate' => $this->validateDate($Data['csa_duedate']) ? $Data['csa_duedate'] : NULL,
			':csa_duedev' => $this->validateDate($Data['csa_duedev']) ? $Data['csa_duedev'] : NULL,
			':csa_pricelist' => is_numeric($Data['csa_pricelist']) ? $Data['csa_pricelist'] : 0,
			':csa_cardcode' => isset($Data['csa_cardcode']) ? $Data['csa_cardcode'] : NULL,
			':csa_cardname' => isset($Data['csa_cardname']) ? $Data['csa_cardname'] : NULL,
			':csa_currency' => isset($Data['csa_currency']) ? $Data['csa_currency'] : NULL,
			':csa_contacid' => isset($Data['csa_contacid']) ? $Data['csa_contacid'] : NULL,
			':csa_slpcode' => is_numeric($Data['csa_slpcode']) ? $Data['csa_slpcode'] : 0,
			':csa_empid' => is_numeric($Data['csa_empid']) ? $Data['csa_empid'] : 0,
			':csa_comment' => isset($Data['csa_comment']) ? $Data['csa_comment'] : NULL,
			':csa_doctotal' => is_numeric($Data['csa_doctotal']) ? $Data['csa_doctotal'] : 0,
			':csa_baseamnt' => is_numeric($Data['csa_baseamnt']) ? $Data['csa_baseamnt'] : 0,
			':csa_taxtotal' => is_numeric($Data['csa_taxtotal']) ? $Data['csa_taxtotal'] : 0,
			':csa_discprofit' => is_numeric($Data['csa_discprofit']) ? $Data['csa_discprofit'] : 0,
			':csa_discount' => is_numeric($Data['csa_discount']) ? $Data['csa_discount'] : 0,
			':csa_createat' => $this->validateDate($Data['csa_createat']) ? $Data['csa_createat'] : NULL,
			':csa_baseentry' => is_numeric($Data['csa_baseentry']) ? $Data['csa_baseentry'] : 0,
			':csa_basetype' => is_numeric($Data['csa_basetype']) ? $Data['csa_basetype'] : 0,
			':csa_doctype' => is_numeric($Data['csa_doctype']) ? $Data['csa_doctype'] : 0,
			':csa_idadd' => isset($Data['csa_idadd']) ? $Data['csa_idadd'] : NULL,
			':csa_adress' => isset($Data['csa_adress']) ? $Data['csa_adress'] : NULL,
			':csa_paytype' => is_numeric($Data['csa_paytype']) ? $Data['csa_paytype'] : 0,
			':csa_createby' => isset($Data['csa_createby']) ? $Data['csa_createby'] : NULL,
			':csa_correl' => isset($Data['csa_correl']) ? $Data['csa_correl'] : NULL,
			':csa_date_inv' => $this->validateDate($Data['csa_date_inv']) ? $Data['csa_date_inv'] : NULL,
			':csa_date_del' => $this->validateDate($Data['csa_date_del']) ? $Data['csa_date_del'] : NULL,
			':csa_place_del' => isset($Data['csa_place_del']) ? $Data['csa_place_del'] : NULL,
			':business' => isset($Data['business']) ? $Data['business'] : NULL,
			':branch' => isset($Data['branch']) ? $Data['branch'] : NULL,
			':csa_anticipate_total' => is_numeric($Data['csa_anticipate_total']) ? $Data['csa_anticipate_total'] : 0,
			':csa_anticipate_type' => is_numeric($Data['csa_anticipate_type']) ? $Data['csa_anticipate_type'] : 0,
			':csa_anticipate_value' => is_numeric($Data['csa_anticipate_value']) ? $Data['csa_anticipate_value'] : 0,
			':csa_paytoday' => 0,

			':csa_taxtotal_ad' => is_numeric($Data['csa_taxtotal_ad']) ? $Data['csa_taxtotal_ad'] : 0

		));

		if (is_numeric($resInsert) && $resInsert > 0) {

			// Se actualiza la serie de la numeracion del documento

			$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id'      => $Data['csa_series']
			));


			if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
			} else {
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'    => $resActualizarNumeracion,
					'mensaje'	=> 'No se pudo crear la orden de compra'
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
				':bed_doctype' => $Data['csa_doctype'],
				':bed_status' => 1, //ESTADO ABIERTO
				':bed_createby' => $Data['csa_createby'],
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
			if ($Data['csa_basetype'] == 21) {

				// SE VALIDA SI HAY ANEXOS EN EL DOCUMENTO APROBADO 
				// SE CAMBIEN AL DOCUMENTO EN CREACION
				$anexo = $this->aprobacion->CambiarAnexos($Data,'csa',$DocNumVerificado);
	
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


					':bed_docentry' => $Data['csa_baseentry'],
					':bed_doctype' => $Data['csa_basetype'],
					':bed_status' => 3, //ESTADO CERRADO
					':bed_createby' => $Data['csa_createby'],
					':bed_date' => date('Y-m-d'),
					':bed_baseentry' => $resInsert,
					':bed_basetype' => $Data['csa_doctype']
				));


				if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
				} else {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertEstado,
						'mensaje'	=> 'No se pudo registrar la solicitud de anticipo de proveedor',
						'proceso' => 'Insertar estado documento'
					);


					$this->response($respuesta);

					return;
				}
			}
			//FIN SE CIERRA EL DOCUMENTO PRELIMINAR SI VIENE DE UN MODELO DE APROBACION

			//FIN PROCESO ESTADO DEL DOCUMENTO

			//SE APLICA PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS
			if (isset($Data['csa_baseentry']) && is_numeric($Data['csa_baseentry']) && isset($Data['csa_basetype']) && is_numeric($Data['csa_basetype'])) {


				if ($Data['csa_basetype'] == 21) {

					$sqlDOrigen = "SELECT *
														 FROM dpap
														 WHERE pap_doctype = :pap_doctype AND pap_docentry = :pap_docentry";

					$resDOrigen = $this->pedeo->queryTable($sqlDOrigen, array(
						':pap_doctype'  => $Data['csa_basetype'],
						':pap_docentry' => $Data['csa_baseentry']
					));

					if (isset($resDOrigen[0])) {

						$sqlDInicio = "SELECT *
															 FROM tbmd
															 WHERE bmd_doctype = :bmd_doctype AND bmd_docentry = :bmd_docentry";

						$resDInicio = $this->pedeo->queryTable($sqlDInicio, array(
							':bmd_doctype'  => $resDOrigen[0]['pap_basetype'],
							':bmd_docentry' => $resDOrigen[0]['pap_baseentry']

						));

						$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
														bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency)
														VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
														:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency)";

						$bmd_tdi = 0;
						$bmd_ndi = 0;
						$bmd_doctypeo = 0;
						$bmd_docentryo = 0;

						if (!isset($resDInicio[0]['bmd_tdi'])) {

							$bmd_tdi = $Data['csa_basetype'];
							$bmd_ndi = $Data['csa_baseentry'];
							$bmd_doctypeo = 0;
							$bmd_docentryo = 0;
						} else {

							$bmd_doctypeo  = $resDOrigen[0]['pap_basetype']; //ORIGEN
							$bmd_docentryo = $resDOrigen[0]['pap_baseentry'];  //ORIGEN
							$bmd_tdi = $resDInicio[0]['bmd_tdi']; // DOCUMENTO INICIAL
							$bmd_ndi = $resDInicio[0]['bmd_ndi']; // DOCUMENTO INICIAL

						}

						$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(
							':bmd_doctype' => is_numeric($Data['csa_doctype']) ? $Data['csa_doctype'] : 0,
							':bmd_docentry' => $resInsert,
							':bmd_createat' => $this->validateDate($Data['csa_createat']) ? $Data['csa_createat'] : NULL,
							':bmd_doctypeo' => $bmd_doctypeo, //ORIGEN
							':bmd_docentryo' => $bmd_docentryo,  //ORIGEN
							':bmd_tdi' => $bmd_tdi, // DOCUMENTO INICIAL
							':bmd_ndi' => $bmd_ndi, // DOCUMENTO INICIAL
							':bmd_docnum' => $DocNumVerificado,
							':bmd_doctotal' => is_numeric($Data['csa_doctotal']) ? $Data['csa_doctotal'] : 0,
							':bmd_cardcode' => isset($Data['csa_cardcode']) ? $Data['csa_cardcode'] : NULL,
							':bmd_cardtype' => 2,
							':bmd_currency' => isset($Data['csa_currency'])?$Data['csa_currency']:NULL,
						));

						if (is_numeric($resInsertMD) && $resInsertMD > 0) {
						} else {

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' => $resInsertMD,
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

							':bmd_doctype' => is_numeric($Data['csa_doctype']) ? $Data['csa_doctype'] : 0,
							':bmd_docentry' => $resInsert,
							':bmd_createat' => $this->validateDate($Data['csa_createat']) ? $Data['csa_createat'] : NULL,
							':bmd_doctypeo' => 0, //ORIGEN
							':bmd_docentryo' => 0,  //ORIGEN
							':bmd_tdi' => is_numeric($Data['csa_doctype']) ? $Data['csa_doctype'] : 0, // DOCUMENTO INICIAL
							':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
							':bmd_docnum' => $DocNumVerificado,
							':bmd_doctotal' => is_numeric($Data['csa_doctotal']) ? $Data['csa_doctotal'] : 0,
							':bmd_cardcode' => isset($Data['csa_cardcode']) ? $Data['csa_cardcode'] : NULL,
							':bmd_cardtype' => 2,
							':bmd_currency' => isset($Data['csa_currency'])?$Data['csa_currency']:NULL,
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
					$sqlDocInicio = "SELECT bmd_tdi, bmd_ndi FROM tbmd WHERE  bmd_doctype = :bmd_doctype AND bmd_docentry = :bmd_docentry";
					$resDocInicio = $this->pedeo->queryTable($sqlDocInicio, array(
						':bmd_doctype' => $Data['csa_basetype'],
						':bmd_docentry' => $Data['csa_baseentry']
					));


					if (isset($resDocInicio[0])) {

						$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
														bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency)
														VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
														:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency)";

						$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

							':bmd_doctype' => is_numeric($Data['csa_doctype']) ? $Data['csa_doctype'] : 0,
							':bmd_docentry' => $resInsert,
							':bmd_createat' => $this->validateDate($Data['csa_createat']) ? $Data['csa_createat'] : NULL,
							':bmd_doctypeo' => is_numeric($Data['csa_basetype']) ? $Data['csa_basetype'] : 0, //ORIGEN
							':bmd_docentryo' => is_numeric($Data['csa_baseentry']) ? $Data['csa_baseentry'] : 0,  //ORIGEN
							':bmd_tdi' => $resDocInicio[0]['bmd_tdi'], // DOCUMENTO INICIAL
							':bmd_ndi' => $resDocInicio[0]['bmd_ndi'], // DOCUMENTO INICIAL
							':bmd_docnum' => $DocNumVerificado,
							':bmd_doctotal' => is_numeric($Data['csa_doctotal']) ? $Data['csa_doctotal'] : 0,
							':bmd_cardcode' => isset($Data['csa_cardcode']) ? $Data['csa_cardcode'] : NULL,
							':bmd_cardtype' => 2,
							':bmd_currency' => isset($Data['csa_currency'])?$Data['csa_currency']:NULL,
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

							':bmd_doctype' => is_numeric($Data['csa_doctype']) ? $Data['csa_doctype'] : 0,
							':bmd_docentry' => $resInsert,
							':bmd_createat' => $this->validateDate($Data['csa_createat']) ? $Data['csa_createat'] : NULL,
							':bmd_doctypeo' => is_numeric($Data['csa_basetype']) ? $Data['csa_basetype'] : 0, //ORIGEN
							':bmd_docentryo' => is_numeric($Data['csa_baseentry']) ? $Data['csa_baseentry'] : 0,  //ORIGEN
							':bmd_tdi' => is_numeric($Data['csa_doctype']) ? $Data['csa_doctype'] : 0, // DOCUMENTO INICIAL
							':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
							':bmd_docnum' => $DocNumVerificado,
							':bmd_doctotal' => is_numeric($Data['csa_doctotal']) ? $Data['csa_doctotal'] : 0,
							':bmd_cardcode' => isset($Data['csa_cardcode']) ? $Data['csa_cardcode'] : NULL,
							':bmd_cardtype' => 2,
							':bmd_currency' => isset($Data['csa_currency'])?$Data['csa_currency']:NULL,
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
				}
			} else {

				$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
														bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency)
														VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
														:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency)";

				$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

					':bmd_doctype' => is_numeric($Data['csa_doctype']) ? $Data['csa_doctype'] : 0,
					':bmd_docentry' => $resInsert,
					':bmd_createat' => $this->validateDate($Data['csa_createat']) ? $Data['csa_createat'] : NULL,
					':bmd_doctypeo' => is_numeric($Data['csa_basetype']) ? $Data['csa_basetype'] : 0, //ORIGEN
					':bmd_docentryo' => is_numeric($Data['csa_baseentry']) ? $Data['csa_baseentry'] : 0,  //ORIGEN
					':bmd_tdi' => is_numeric($Data['csa_doctype']) ? $Data['csa_doctype'] : 0, // DOCUMENTO INICIAL
					':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
					':bmd_docnum' => $DocNumVerificado,
					':bmd_doctotal' => is_numeric($Data['csa_doctotal']) ? $Data['csa_doctotal'] : 0,
					':bmd_cardcode' => isset($Data['csa_cardcode']) ? $Data['csa_cardcode'] : NULL,
					':bmd_cardtype' => 2,
					':bmd_currency' => isset($Data['csa_currency'])?$Data['csa_currency']:NULL,
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

				$CANTUOMPURCHASE = $this->generic->getUomPurchase($detail['sa1_itemcode']);

				if ($CANTUOMPURCHASE == 0) {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' 		=> $detail['sa1_itemcode'],
						'mensaje'	=> 'No se encontro la equivalencia de la unidad de medida para el item: ' . $detail['sa1_itemcode']
					);

					$this->response($respuesta);

					return;
				}

				$sqlInsertDetail = "INSERT INTO csa1(sa1_docentry, sa1_itemcode, sa1_itemname, sa1_quantity, sa1_uom, sa1_whscode,
                sa1_price, sa1_vat, sa1_vatsum, sa1_discount, sa1_linetotal, sa1_costcode, sa1_ubusiness, sa1_project,
                sa1_acctcode, sa1_basetype, sa1_doctype, sa1_avprice, sa1_inventory, sa1_linenum, sa1_acciva, sa1_codimp, sa1_ubication,sa1_baseline,ote_code,
				sa1_vat_ad,sa1_vatsum_ad,sa1_accimp_ad,sa1_codimp_ad, sa1_codmunicipality)
				VALUES(:sa1_docentry, :sa1_itemcode, :sa1_itemname, :sa1_quantity,:sa1_uom, :sa1_whscode,:sa1_price, :sa1_vat, :sa1_vatsum, 
				:sa1_discount, :sa1_linetotal, :sa1_costcode, :sa1_ubusiness, :sa1_project,:sa1_acctcode, :sa1_basetype, :sa1_doctype, :sa1_avprice, 
				:sa1_inventory,:sa1_linenum,:sa1_acciva, :sa1_codimp, :sa1_ubication,:sa1_baseline,:ote_code,:sa1_vat_ad,:sa1_vatsum_ad,:sa1_accimp_ad,:sa1_codimp_ad, 
				:sa1_codmunicipality)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':sa1_docentry' => $resInsert,
					':sa1_itemcode' => isset($detail['sa1_itemcode']) ? $detail['sa1_itemcode'] : NULL,
					':sa1_itemname' => isset($detail['sa1_itemname']) ? $detail['sa1_itemname'] : NULL,
					':sa1_quantity' => is_numeric($detail['sa1_quantity']) ? $detail['sa1_quantity']  : 0,
					':sa1_uom' => isset($detail['sa1_uom']) ? $detail['sa1_uom'] : NULL,
					':sa1_whscode' => isset($detail['sa1_whscode']) ? $detail['sa1_whscode'] : NULL,
					':sa1_price' => is_numeric($detail['sa1_price']) ? $detail['sa1_price'] : 0,
					':sa1_vat' => is_numeric($detail['sa1_vat']) ? $detail['sa1_vat'] : 0,
					':sa1_vatsum' => is_numeric($detail['sa1_vatsum']) ? $detail['sa1_vatsum'] : 0,
					':sa1_discount' => is_numeric($detail['sa1_discount']) ? $detail['sa1_discount'] : 0,
					':sa1_linetotal' => is_numeric($detail['sa1_linetotal']) ? $detail['sa1_linetotal'] : 0,
					':sa1_costcode' => isset($detail['sa1_costcode']) ? $detail['sa1_costcode'] : NULL,
					':sa1_ubusiness' => isset($detail['sa1_ubusiness']) ? $detail['sa1_ubusiness'] : NULL,
					':sa1_project' => isset($detail['sa1_project']) ? $detail['sa1_project'] : NULL,
					':sa1_acctcode' => is_numeric($detail['sa1_acctcode']) ? $detail['sa1_acctcode'] : 0,
					':sa1_basetype' => is_numeric($detail['sa1_basetype']) ? $detail['sa1_basetype'] : 0,
					':sa1_doctype' => is_numeric($detail['sa1_doctype']) ? $detail['sa1_doctype'] : 0,
					':sa1_avprice' => is_numeric($detail['sa1_avprice']) ? $detail['sa1_avprice'] : 0,
					':sa1_inventory' => is_numeric($detail['sa1_inventory']) ? $detail['sa1_inventory'] : NULL,
					':sa1_linenum' => is_numeric($detail['sa1_linenum']) ? $detail['sa1_linenum'] : NULL,
					':sa1_acciva' => is_numeric($detail['sa1_acciva']) ? $detail['sa1_acciva'] : NULL,
					':sa1_codimp' => isset($detail['sa1_codimp']) ? $detail['sa1_codimp'] : NULL,
					':sa1_ubication' => isset($detail['sa1_ubication']) ? $detail['sa1_ubication'] : NULL,
					':sa1_baseline' => is_numeric($detail['sa1_baseline']) ? $detail['sa1_baseline'] : 0,
					':ote_code' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,

					':sa1_vat_ad'    => is_numeric($detail['sa1_vat_ad']) ? $detail['sa1_vat_ad'] : 0,
					':sa1_vatsum_ad' => is_numeric($detail['sa1_vatsum_ad']) ? $detail['sa1_vatsum_ad'] : 0,
					':sa1_accimp_ad' => is_numeric($detail['sa1_accimp_ad']) ? $detail['sa1_accimp_ad'] : NULL,
					':sa1_codimp_ad' => isset($detail['sa1_codimp_ad']) ? $detail['sa1_codimp_ad'] : NULL,
					':sa1_codmunicipality' => isset($detail['sa1_codmunicipality']) ? $detail['sa1_codmunicipality'] : NULL
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
						'mensaje'	=> 'No se pudo registrar la orden de compra'
					);

					$this->response($respuesta);

					return;
				}
			}

			//FIN DETALLE COTIZACION
			if ($Data['csa_basetype'] == 12) {


				$sqlEstado1 = " SELECT
									(t0.cpo_doctotal) as total
								from dcpo t0
								where t0.cpo_docentry = :cpo_docentry
								and t0.cpo_doctype = :cpo_doctype";


				$resEstado1 = $this->pedeo->queryTable($sqlEstado1, array(
					':cpo_docentry' => $Data['csa_baseentry'],
					':cpo_doctype' => $Data['csa_basetype']

				));

				$sqlEstado2 = "SELECT
									(coalesce(sum(t0.csa_anticipate_total - coalesce(t0.csa_paytoday,0)),0)) total
								from dcsa t0
								where t0.csa_baseentry = :csa_baseentry
								and t0.csa_basetype = :csa_basetype";

				// print_r($sqlEstado2);exit;
				$resEstado2 = $this->pedeo->queryTable($sqlEstado2, array(
					':csa_baseentry' => $Data['csa_baseentry'],
					':csa_basetype' => $Data['csa_basetype']

				));

				$total_order = isset($resEstado1[0]) ? $resEstado1[0]['total'] : 0;
				$total_sa = isset($resEstado2[0]) ? $resEstado2[0]['total'] : 0;				

				if (($total_sa > $total_order) ) {

					$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' => $total_order."-".$total_sa,
							'mensaje'	=> 'La suma de las solicitudes de anticipo no pueden superar al valor total del documento base'
						);


						$this->response($respuesta);

						return;
					
				}
			}else if ($Data['csa_basetype'] == 21) {

				
				$sqlAprov = "SELECT * FROM dpap WHERE pap_doctype = :pap_doctype AND pap_docentry = :pap_docentry";
				$resAprov = $this->pedeo->queryTable($sqlAprov,array(
					':pap_doctype' => $Data['csa_basetype'],
					':pap_docentry' => $Data['csa_baseentry']
				));

				$sqlEstado1 = " SELECT
									t0.cpo_doctotal as total
								from dcpo t0
								where t0.cpo_docentry = :cpo_docentry
								and t0.cpo_doctype = :cpo_doctype";


				$resEstado1 = $this->pedeo->queryTable($sqlEstado1, array(
					':cpo_docentry' => $resAprov[0]['pap_baseentry'],
					':cpo_doctype' => $resAprov[0]['pap_basetype']

				));

				$sqlEstado2 = "SELECT
									coalesce(sum(t0.csa_anticipate_total - coalesce(t0.csa_paytoday,0)),0) total
								from dcsa t0
								where t0.csa_baseentry = :csa_baseentry
								and t0.csa_basetype = :csa_basetype";
				$resEstado2 = $this->pedeo->queryTable($sqlEstado2, array(
					':csa_baseentry' => $Data['csa_baseentry'],
					':csa_basetype' => $Data['csa_basetype']

				));

				$total_order = isset($resEstado1[0]) ? $resEstado1[0]['total'] : 0;
				$total_sa = isset($resEstado2[0]) ? $resEstado2[0]['total'] : 0;			

				if (($total_sa > $total_order)) {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' => $total_order."-".$total_sa,
							'mensaje'	=> 'La suma de las solicitudes de anticipo no pueden superar al valor total del documento base'
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
				'mensaje' => 'Solicitud de anticipo de proveedor #'.$DocNumVerificado.' registrada con exito'
			);
		} else {
			// Se devuelven los cambios realizados en la transaccion
			// si occurre un error  y se muestra devuelve el error.
			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data' => $resInsert,
				'mensaje'	=> 'No se pudo registrar la orden de compra'
			);
		}

		$this->response($respuesta);
	}

	//ACTUALIZAR ORDEN DE COMPRA
	public function updatePurchSolAntProv_post()
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
				'mensaje' => 'No se encontro el detalle de la orden de compra'
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

		$sqlUpdate = "UPDATE dcsa	SET csa_docdate=:csa_docdate,csa_duedate=:csa_duedate, csa_duedev=:csa_duedev, csa_pricelist=:csa_pricelist, csa_cardcode=:csa_cardcode,
			  						csa_cardname=:csa_cardname, csa_currency=:csa_currency, csa_contacid=:csa_contacid, csa_slpcode=:csa_slpcode,
										csa_empid=:csa_empid, csa_comment=:csa_comment, csa_doctotal=:csa_doctotal, csa_baseamnt=:csa_baseamnt,
										csa_taxtotal=:csa_taxtotal, csa_discprofit=:csa_discprofit, csa_discount=:csa_discount, csa_createat=:csa_createat,
										csa_baseentry=:csa_baseentry, csa_basetype=:csa_basetype, csa_doctype=:csa_doctype, csa_idadd=:csa_idadd,
										csa_adress=:csa_adress, csa_paytype=:csa_paytype, csa_anticipate_type=:csa_ancitipate_type, csa_anticipate_value=:csa_ancitipate_value,
										csa_anticipate_total=:csa_anticipate_total  WHERE csa_docentry=:csa_docentry";

		$this->pedeo->trans_begin();

		$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
			':csa_docdate' => $this->validateDate($Data['csa_docdate']) ? $Data['csa_docdate'] : NULL,
			':csa_duedate' => $this->validateDate($Data['csa_duedate']) ? $Data['csa_duedate'] : NULL,
			':csa_duedev' => $this->validateDate($Data['csa_duedev']) ? $Data['csa_duedev'] : NULL,
			':csa_pricelist' => is_numeric($Data['csa_pricelist']) ? $Data['csa_pricelist'] : 0,
			':csa_cardcode' => isset($Data['csa_cardcode']) ? $Data['csa_cardcode'] : NULL,
			':csa_cardname' => isset($Data['csa_cardname']) ? $Data['csa_cardname'] : NULL,
			':csa_currency' => isset($Data['csa_currency']) ? $Data['csa_currency'] : NULL,
			':csa_contacid' => isset($Data['csa_contacid']) ? $Data['csa_contacid'] : NULL,
			':csa_slpcode' => is_numeric($Data['csa_slpcode']) ? $Data['csa_slpcode'] : 0,
			':csa_empid' => is_numeric($Data['csa_empid']) ? $Data['csa_empid'] : 0,
			':csa_comment' => isset($Data['csa_comment']) ? $Data['csa_comment'] : NULL,
			':csa_doctotal' => is_numeric($Data['csa_doctotal']) ? $Data['csa_doctotal'] : 0,
			':csa_baseamnt' => is_numeric($Data['csa_baseamnt']) ? $Data['csa_baseamnt'] : 0,
			':csa_taxtotal' => is_numeric($Data['csa_taxtotal']) ? $Data['csa_taxtotal'] : 0,
			':csa_discprofit' => is_numeric($Data['csa_discprofit']) ? $Data['csa_discprofit'] : 0,
			':csa_discount' => is_numeric($Data['csa_discount']) ? $Data['csa_discount'] : 0,
			':csa_createat' => $this->validateDate($Data['csa_createat']) ? $Data['csa_createat'] : NULL,
			':csa_baseentry' => is_numeric($Data['csa_baseentry']) ? $Data['csa_baseentry'] : 0,
			':csa_basetype' => is_numeric($Data['csa_basetype']) ? $Data['csa_basetype'] : 0,
			':csa_doctype' => is_numeric($Data['csa_doctype']) ? $Data['csa_doctype'] : 0,
			':csa_idadd' => isset($Data['csa_idadd']) ? $Data['csa_idadd'] : NULL,
			':csa_adress' => isset($Data['csa_adress']) ? $Data['csa_adress'] : NULL,
			':csa_paytype' => is_numeric($Data['csa_paytype']) ? $Data['csa_paytype'] : 0,
			':csa_anticipate_total' => is_numeric($Data['csa_anticipate_total']) ? $Data['csa_anticipate_total'] : 0,
			':csa_anticipate_type' => is_numeric($Data['csa_anticipate_type']) ? $Data['csa_anticipate_type'] : 0,
			':csa_anticipate_value' => is_numeric($Data['csa_anticipate_value']) ? $Data['csa_anticipate_value'] : 0,
			':csa_docentry' => $Data['csa_docentry']
		));

		if (is_numeric($resUpdate) && $resUpdate == 1) {

			$this->pedeo->queryTable("DELETE FROM csa1 WHERE sa1_docentry=:sa1_docentry", array(':sa1_docentry' => $Data['csa_docentry']));

			foreach ($ContenidoDetalle as $key => $detail) {

				$sqlInsertDetail = "INSERT INTO csa1(sa1_docentry, sa1_itemcode, sa1_itemname, sa1_quantity, sa1_uom, sa1_whscode,
																			sa1_price, sa1_vat, sa1_vatsum, sa1_discount, sa1_linetotal, sa1_costcode, sa1_ubusiness, sa1_project,
																			sa1_acctcode, sa1_basetype, sa1_doctype, sa1_avprice, sa1_inventory, sa1_acciva, sa1_linenum, sa1_ubication, sa1_codmunicipality)VALUES(:sa1_docentry, :sa1_itemcode, :sa1_itemname, :sa1_quantity,
																			:sa1_uom, :sa1_whscode,:sa1_price, :sa1_vat, :sa1_vatsum, :sa1_discount, :sa1_linetotal, :sa1_costcode, :sa1_ubusiness, :sa1_project,
																			:sa1_acctcode, :sa1_basetype, :sa1_doctype, :sa1_avprice, :sa1_inventory, :sa1_acciva,:sa1_linenum, :sa1_ubication, :sa1_codmunicipality)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':sa1_docentry' => $Data['csa_docentry'],
					':sa1_itemcode' => isset($detail['sa1_itemcode']) ? $detail['sa1_itemcode'] : NULL,
					':sa1_itemname' => isset($detail['sa1_itemname']) ? $detail['sa1_itemname'] : NULL,
					':sa1_quantity' => is_numeric($detail['sa1_quantity']) ? $detail['sa1_quantity'] : 0,
					':sa1_uom' => isset($detail['sa1_uom']) ? $detail['sa1_uom'] : NULL,
					':sa1_whscode' => isset($detail['sa1_whscode']) ? $detail['sa1_whscode'] : NULL,
					':sa1_price' => is_numeric($detail['sa1_price']) ? $detail['sa1_price'] : 0,
					':sa1_vat' => is_numeric($detail['sa1_vat']) ? $detail['sa1_vat'] : 0,
					':sa1_vatsum' => is_numeric($detail['sa1_vatsum']) ? $detail['sa1_vatsum'] : 0,
					':sa1_discount' => is_numeric($detail['sa1_discount']) ? $detail['sa1_discount'] : 0,
					':sa1_linetotal' => is_numeric($detail['sa1_linetotal']) ? $detail['sa1_linetotal'] : 0,
					':sa1_costcode' => isset($detail['sa1_costcode']) ? $detail['sa1_costcode'] : NULL,
					':sa1_ubusiness' => isset($detail['sa1_ubusiness']) ? $detail['sa1_ubusiness'] : NULL,
					':sa1_project' => isset($detail['sa1_project']) ? $detail['sa1_project'] : NULL,
					':sa1_acctcode' => is_numeric($detail['sa1_acctcode']) ? $detail['sa1_acctcode'] : 0,
					':sa1_basetype' => is_numeric($detail['sa1_basetype']) ? $detail['sa1_basetype'] : 0,
					':sa1_doctype' => is_numeric($detail['sa1_doctype']) ? $detail['sa1_doctype'] : 0,
					':sa1_avprice' => is_numeric($detail['sa1_avprice']) ? $detail['sa1_avprice'] : 0,
					':sa1_inventory' => is_numeric($detail['sa1_inventory']) ? $detail['sa1_inventory'] : NULL,
					':sa1_acciva' => is_numeric($detail['sa1_acciva']) ? $detail['sa1_acciva'] : NULL,
					':sa1_linenum' => is_numeric($detail['sa1_linenum']) ? $detail['sa1_linenum'] : NULL,
					':sa1_ubication' => is_numeric($detail['sa1_ubication']) ? $detail['sa1_ubication'] : NULL,
					':sa1_codmunicipality' => isset($detail['sa1_codmunicipality']) ? $detail['sa1_codmunicipality'] : NULL
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
						'mensaje'	=> 'No se pudo registrar la orden de compra'
					);

					$this->response($respuesta);

					return;
				}
			}


			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resUpdate,
				'mensaje' => 'Solicitud de anticipo de proveedor actualizada con exito'
			);
		} else {

			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data'    => $resUpdate,
				'mensaje'	=> 'No se pudo actualizar la orden de compra'
			);
		}

		$this->response($respuesta);
	}


	//OBTENER orden de compra
	public function getPurchSolAntProv_get()
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

		

		$sqlSelect = self::getColumn('dcsa', 'csa', ",CONCAT(T0.csa_CURRENCY,' ',TRIM(TO_CHAR(csa_anticipate_total,'{format}'))) as csa_anticipate_total ,T4.dms_phone1, T4.dms_phone2, T4.dms_cel", '', $DECI_MALES, $Data['business'], $Data['branch'],36);
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


	//OBTENER orden de compra POR ID
	public function getPurchSolAntProvById_get()
	{

		$Data = $this->get();

		if (!isset($Data['csa_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM dcsa WHERE csa_docentry =:csa_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":csa_docentry" => $Data['csa_docentry']));

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


	//OBTENER orden de compra DETALLE POR ID
	public function getPurchSolAntProvDetail_get()
	{

		$Data = $this->get();

		if (!isset($Data['sa1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT csa1.*,dmar.dma_series_code
											 FROM csa1
											 INNER JOIN dmar	ON csa1.sa1_itemcode = dmar.dma_item_code
											 WHERE sa1_docentry =:sa1_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":sa1_docentry" => $Data['sa1_docentry']));

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

	//OBTENER orden de compra DETALLE POR ID
	public function getPurchSolAntProvDetailCopy_get()
	{

		$Data = $this->get();

		if (!isset($Data['sa1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$copy = $this->documentcopy->Copy($Data['sa1_docentry'],'dcsa','csa1','csa','sa1');

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



	//OBTENER COTIZACIONES POR SOCIO DE NEGOCIO
	public function getPurchSolAntProvBySN_get()
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

		$copy = $this->documentcopy->CopyData('dcsa','csa',$Data['dms_card_code'],$Data['business'],$Data['branch']);

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