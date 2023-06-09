<?php
// ORDEN DE COMPRA
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class PurchOrder extends REST_Controller
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
	}

	//CREAR NUEVA ORDEN DE COMPRA
	public function createPurchOrder_post()
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
		// //BUSCANDO LA NUMERACION DEL DOCUMENTO
		$DocNumVerificado = $this->documentnumbering->NumberDoc($Data['cpo_series'],$Data['cpo_docdate'],$Data['cpo_duedate']);
		
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
		$dataTasa = $this->tasa->Tasa($Data['cpo_currency'],$Data['cpo_docdate']);

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

			':bed_docentry' => $Data['cpo_baseentry'],
			':bed_doctype'  => $Data['cpo_basetype'],
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

				':mau_doctype'   => $Data['cpo_doctype'],
				':pgu_code_user' => $Data['cpo_createby'],
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
					$TotalDocumento = $Data['cpo_doctotal'];
					$doctype =  $value['doctype'];
					$modelo = $value['modelo'];

					$sqlTasaMonedaModelo = "SELECT COALESCE(get_dynamic_conversion(:mau_currency,:doc_currency,:doc_date,:doc_total,get_localcur()), 0) AS monto"; 
					$resTasaMonedaModelo = $this->pedeo->queryTable($sqlTasaMonedaModelo, array(
						':mau_currency' => $value['mau_currency'],
						':doc_currency' => $Data['cpo_currency'],
						':doc_date' 	=> $Data['cpo_docdate'],
						':doc_total' 	=> $TotalDocumento
					));

					if ( $resTasaMonedaModelo[0]['monto'] == 0 ){
						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' => 'No se encrontro la tasa de cambio para la moneda del modelo :'. $value['mau_currency'].'en la fecha del documento '.$Data['cpo_docdate']
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

							$resAprobacion = $this->aprobacion->setAprobacion($Data, $ContenidoDetalle, 'cpo', 'po1', $ressq[0]['mau_quantity'], count(explode(',', $ressq[0]['mau_approvers'])), $ressq[0]['mau_docentry'], $Data['business'], $Data['branch']);

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

							$resAprobacion =  $this->aprobacion->setAprobacion($Data, $ContenidoDetalle, 'cpo', 'po1', $ressq[0]['mau_quantity'], count(explode(',', $ressq[0]['mau_approvers'])), $ressq[0]['mau_docentry'], $Data['business'], $Data['branch']);

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
		$sqlInsert = "INSERT INTO dcpo(cpo_series, cpo_docnum, cpo_docdate, cpo_duedate, cpo_duedev, cpo_pricelist, cpo_cardcode,
                      cpo_cardname, cpo_currency, cpo_contacid, cpo_slpcode, cpo_empid, cpo_comment, cpo_doctotal, cpo_baseamnt, cpo_taxtotal,
                      cpo_discprofit, cpo_discount, cpo_createat, cpo_baseentry, cpo_basetype, cpo_doctype, cpo_idadd, cpo_adress, cpo_paytype,
                      cpo_createby,cpo_correl, cpo_date_inv, cpo_date_del, cpo_place_del,business,branch, cpo_internal_comments)VALUES(:cpo_series, :cpo_docnum, :cpo_docdate, :cpo_duedate, :cpo_duedev, :cpo_pricelist, :cpo_cardcode, :cpo_cardname,
                      :cpo_currency, :cpo_contacid, :cpo_slpcode, :cpo_empid, :cpo_comment, :cpo_doctotal, :cpo_baseamnt, :cpo_taxtotal, :cpo_discprofit, :cpo_discount,
                      :cpo_createat, :cpo_baseentry, :cpo_basetype, :cpo_doctype, :cpo_idadd, :cpo_adress, :cpo_paytype,:cpo_createby,:cpo_correl, 
					  :cpo_date_inv, :cpo_date_del, :cpo_place_del,:business,:branch, :cpo_internal_comments)";


		// Se Inicia la transaccion,
		// Todas las consultas de modificacion siguientes
		// aplicaran solo despues que se confirme la transaccion,
		// de lo contrario no se aplicaran los cambios y se devolvera
		// la base de datos a su estado original.

		$this->pedeo->trans_begin();

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			':cpo_docnum' => $DocNumVerificado,
			':cpo_series' => is_numeric($Data['cpo_series']) ? $Data['cpo_series'] : 0,
			':cpo_docdate' => $this->validateDate($Data['cpo_docdate']) ? $Data['cpo_docdate'] : NULL,
			':cpo_duedate' => $this->validateDate($Data['cpo_duedate']) ? $Data['cpo_duedate'] : NULL,
			':cpo_duedev' => $this->validateDate($Data['cpo_duedev']) ? $Data['cpo_duedev'] : NULL,
			':cpo_pricelist' => is_numeric($Data['cpo_pricelist']) ? $Data['cpo_pricelist'] : 0,
			':cpo_cardcode' => isset($Data['cpo_cardcode']) ? $Data['cpo_cardcode'] : NULL,
			':cpo_cardname' => isset($Data['cpo_cardname']) ? $Data['cpo_cardname'] : NULL,
			':cpo_currency' => isset($Data['cpo_currency']) ? $Data['cpo_currency'] : NULL,
			':cpo_contacid' => isset($Data['cpo_contacid']) ? $Data['cpo_contacid'] : NULL,
			':cpo_slpcode' => is_numeric($Data['cpo_slpcode']) ? $Data['cpo_slpcode'] : 0,
			':cpo_empid' => is_numeric($Data['cpo_empid']) ? $Data['cpo_empid'] : 0,
			':cpo_comment' => isset($Data['cpo_comment']) ? $Data['cpo_comment'] : NULL,
			':cpo_doctotal' => is_numeric($Data['cpo_doctotal']) ? $Data['cpo_doctotal'] : 0,
			':cpo_baseamnt' => is_numeric($Data['cpo_baseamnt']) ? $Data['cpo_baseamnt'] : 0,
			':cpo_taxtotal' => is_numeric($Data['cpo_taxtotal']) ? $Data['cpo_taxtotal'] : 0,
			':cpo_discprofit' => is_numeric($Data['cpo_discprofit']) ? $Data['cpo_discprofit'] : 0,
			':cpo_discount' => is_numeric($Data['cpo_discount']) ? $Data['cpo_discount'] : 0,
			':cpo_createat' => $this->validateDate($Data['cpo_createat']) ? $Data['cpo_createat'] : NULL,
			':cpo_baseentry' => is_numeric($Data['cpo_baseentry']) ? $Data['cpo_baseentry'] : 0,
			':cpo_basetype' => is_numeric($Data['cpo_basetype']) ? $Data['cpo_basetype'] : 0,
			':cpo_doctype' => is_numeric($Data['cpo_doctype']) ? $Data['cpo_doctype'] : 0,
			':cpo_idadd' => isset($Data['cpo_idadd']) ? $Data['cpo_idadd'] : NULL,
			':cpo_adress' => isset($Data['cpo_adress']) ? $Data['cpo_adress'] : NULL,
			':cpo_paytype' => is_numeric($Data['cpo_paytype']) ? $Data['cpo_paytype'] : 0,
			':cpo_createby' => isset($Data['cpo_createby']) ? $Data['cpo_createby'] : NULL,
			':cpo_correl' => isset($Data['cpo_correl']) ? $Data['cpo_correl'] : NULL,
			':cpo_date_inv' => $this->validateDate($Data['cpo_date_inv']) ? $Data['cpo_date_inv'] : NULL,
			':cpo_date_del' => $this->validateDate($Data['cpo_date_del']) ? $Data['cpo_date_del'] : NULL,
			':cpo_place_del' => isset($Data['cpo_place_del']) ? $Data['cpo_place_del'] : NULL,
			':business' => isset($Data['business']) ? $Data['business'] : NULL,
			':branch' => isset($Data['branch']) ? $Data['branch'] : NULL,
			':cpo_internal_comments' => isset($Data['cpo_internal_comments']) ? $Data['cpo_internal_comments'] : NULL

		));

		if (is_numeric($resInsert) && $resInsert > 0) {

			// Se actualiza la serie de la numeracion del documento

			$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id'      => $Data['cpo_series']
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
				':bed_doctype' => $Data['cpo_doctype'],
				':bed_status' => 1, //ESTADO ABIERTO
				':bed_createby' => $Data['cpo_createby'],
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
			if ($Data['cpo_basetype'] == 21) {

				$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

				$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


					':bed_docentry' => $Data['cpo_baseentry'],
					':bed_doctype' => $Data['cpo_basetype'],
					':bed_status' => 3, //ESTADO CERRADO
					':bed_createby' => $Data['cpo_createby'],
					':bed_date' => date('Y-m-d'),
					':bed_baseentry' => $resInsert,
					':bed_basetype' => $Data['cpo_doctype']
				));


				if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
				} else {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertEstado,
						'mensaje'	=> 'No se pudo registrar la solicitud de compras',
						'proceso' => 'Insertar estado documento'
					);


					$this->response($respuesta);

					return;
				}
			}
			//FIN SE CIERRA EL DOCUMENTO PRELIMINAR SI VIENE DE UN MODELO DE APROBACION

			//FIN PROCESO ESTADO DEL DOCUMENTO

			//SE APLICA PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS
			if (isset($Data['cpo_baseentry']) && is_numeric($Data['cpo_baseentry']) && isset($Data['cpo_basetype']) && is_numeric($Data['cpo_basetype'])) {


				if ($Data['cpo_basetype'] == 21) {

					$sqlDOrigen = "SELECT *
														 FROM dpap
														 WHERE pap_doctype = :pap_doctype AND pap_docentry = :pap_docentry";

					$resDOrigen = $this->pedeo->queryTable($sqlDOrigen, array(
						':pap_doctype'  => $Data['cpo_basetype'],
						':pap_docentry' => $Data['cpo_baseentry']
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

							$bmd_tdi = $Data['cpo_basetype'];
							$bmd_ndi = $Data['cpo_baseentry'];
							$bmd_doctypeo = 0;
							$bmd_docentryo = 0;
						} else {

							$bmd_doctypeo  = $resDOrigen[0]['pap_basetype']; //ORIGEN
							$bmd_docentryo = $resDOrigen[0]['pap_baseentry'];  //ORIGEN
							$bmd_tdi = $resDInicio[0]['bmd_tdi']; // DOCUMENTO INICIAL
							$bmd_ndi = $resDInicio[0]['bmd_ndi']; // DOCUMENTO INICIAL

						}

						$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(
							':bmd_doctype' => is_numeric($Data['cpo_doctype']) ? $Data['cpo_doctype'] : 0,
							':bmd_docentry' => $resInsert,
							':bmd_createat' => $this->validateDate($Data['cpo_createat']) ? $Data['cpo_createat'] : NULL,
							':bmd_doctypeo' => $bmd_doctypeo, //ORIGEN
							':bmd_docentryo' => $bmd_docentryo,  //ORIGEN
							':bmd_tdi' => $bmd_tdi, // DOCUMENTO INICIAL
							':bmd_ndi' => $bmd_ndi, // DOCUMENTO INICIAL
							':bmd_docnum' => $DocNumVerificado,
							':bmd_doctotal' => is_numeric($Data['cpo_doctotal']) ? $Data['cpo_doctotal'] : 0,
							':bmd_cardcode' => isset($Data['cpo_cardcode']) ? $Data['cpo_cardcode'] : NULL,
							':bmd_cardtype' => 2,
							':bmd_currency' => isset($Data['cpo_currency'])?$Data['cpo_currency']:NULL,
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

							':bmd_doctype' => is_numeric($Data['cpo_doctype']) ? $Data['cpo_doctype'] : 0,
							':bmd_docentry' => $resInsert,
							':bmd_createat' => $this->validateDate($Data['cpo_createat']) ? $Data['cpo_createat'] : NULL,
							':bmd_doctypeo' => 0, //ORIGEN
							':bmd_docentryo' => 0,  //ORIGEN
							':bmd_tdi' => is_numeric($Data['cpo_doctype']) ? $Data['cpo_doctype'] : 0, // DOCUMENTO INICIAL
							':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
							':bmd_docnum' => $DocNumVerificado,
							':bmd_doctotal' => is_numeric($Data['cpo_doctotal']) ? $Data['cpo_doctotal'] : 0,
							':bmd_cardcode' => isset($Data['cpo_cardcode']) ? $Data['cpo_cardcode'] : NULL,
							':bmd_cardtype' => 2,
							':bmd_currency' => isset($Data['cpo_currency'])?$Data['cpo_currency']:NULL,
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
						':bmd_doctype' => $Data['cpo_basetype'],
						':bmd_docentry' => $Data['cpo_baseentry']
					));


					if (isset($resDocInicio[0])) {

						$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
														bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency)
														VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
														:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency)";

						$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

							':bmd_doctype' => is_numeric($Data['cpo_doctype']) ? $Data['cpo_doctype'] : 0,
							':bmd_docentry' => $resInsert,
							':bmd_createat' => $this->validateDate($Data['cpo_createat']) ? $Data['cpo_createat'] : NULL,
							':bmd_doctypeo' => is_numeric($Data['cpo_basetype']) ? $Data['cpo_basetype'] : 0, //ORIGEN
							':bmd_docentryo' => is_numeric($Data['cpo_baseentry']) ? $Data['cpo_baseentry'] : 0,  //ORIGEN
							':bmd_tdi' => $resDocInicio[0]['bmd_tdi'], // DOCUMENTO INICIAL
							':bmd_ndi' => $resDocInicio[0]['bmd_ndi'], // DOCUMENTO INICIAL
							':bmd_docnum' => $DocNumVerificado,
							':bmd_doctotal' => is_numeric($Data['cpo_doctotal']) ? $Data['cpo_doctotal'] : 0,
							':bmd_cardcode' => isset($Data['cpo_cardcode']) ? $Data['cpo_cardcode'] : NULL,
							':bmd_cardtype' => 2,
							':bmd_currency' => isset($Data['cpo_currency'])?$Data['cpo_currency']:NULL,
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

							':bmd_doctype' => is_numeric($Data['cpo_doctype']) ? $Data['cpo_doctype'] : 0,
							':bmd_docentry' => $resInsert,
							':bmd_createat' => $this->validateDate($Data['cpo_createat']) ? $Data['cpo_createat'] : NULL,
							':bmd_doctypeo' => is_numeric($Data['cpo_basetype']) ? $Data['cpo_basetype'] : 0, //ORIGEN
							':bmd_docentryo' => is_numeric($Data['cpo_baseentry']) ? $Data['cpo_baseentry'] : 0,  //ORIGEN
							':bmd_tdi' => is_numeric($Data['cpo_doctype']) ? $Data['cpo_doctype'] : 0, // DOCUMENTO INICIAL
							':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
							':bmd_docnum' => $DocNumVerificado,
							':bmd_doctotal' => is_numeric($Data['cpo_doctotal']) ? $Data['cpo_doctotal'] : 0,
							':bmd_cardcode' => isset($Data['cpo_cardcode']) ? $Data['cpo_cardcode'] : NULL,
							':bmd_cardtype' => 2,
							':bmd_currency' => isset($Data['cpo_currency'])?$Data['cpo_currency']:NULL,
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

					':bmd_doctype' => is_numeric($Data['cpo_doctype']) ? $Data['cpo_doctype'] : 0,
					':bmd_docentry' => $resInsert,
					':bmd_createat' => $this->validateDate($Data['cpo_createat']) ? $Data['cpo_createat'] : NULL,
					':bmd_doctypeo' => is_numeric($Data['cpo_basetype']) ? $Data['cpo_basetype'] : 0, //ORIGEN
					':bmd_docentryo' => is_numeric($Data['cpo_baseentry']) ? $Data['cpo_baseentry'] : 0,  //ORIGEN
					':bmd_tdi' => is_numeric($Data['cpo_doctype']) ? $Data['cpo_doctype'] : 0, // DOCUMENTO INICIAL
					':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
					':bmd_docnum' => $DocNumVerificado,
					':bmd_doctotal' => is_numeric($Data['cpo_doctotal']) ? $Data['cpo_doctotal'] : 0,
					':bmd_cardcode' => isset($Data['cpo_cardcode']) ? $Data['cpo_cardcode'] : NULL,
					':bmd_cardtype' => 2,
					':bmd_currency' => isset($Data['cpo_currency'])?$Data['cpo_currency']:NULL,
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

				$CANTUOMPURCHASE = $this->generic->getUomPurchase($detail['po1_itemcode']);

				if ($CANTUOMPURCHASE == 0) {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' 		=> $detail['po1_itemcode'],
						'mensaje'	=> 'No se encontro la equivalencia de la unidad de medida para el item: ' . $detail['po1_itemcode']
					);

					$this->response($respuesta);

					return;
				}

				$sqlInsertDetail = "INSERT INTO cpo1(po1_docentry, po1_itemcode, po1_itemname, po1_quantity, po1_uom, po1_whscode,
                po1_price, po1_vat, po1_vatsum, po1_discount, po1_linetotal, po1_costcode, po1_ubusiness, po1_project,
                po1_acctcode, po1_basetype, po1_doctype, po1_avprice, po1_inventory, po1_linenum, po1_acciva, po1_codimp, po1_ubication,po1_baseline,ote_code)
				VALUES(:po1_docentry, :po1_itemcode, :po1_itemname, :po1_quantity,:po1_uom, :po1_whscode,:po1_price, :po1_vat, :po1_vatsum, 
				:po1_discount, :po1_linetotal, :po1_costcode, :po1_ubusiness, :po1_project,:po1_acctcode, :po1_basetype, :po1_doctype, :po1_avprice, 
				:po1_inventory,:po1_linenum,:po1_acciva, :po1_codimp, :po1_ubication,:po1_baseline,:ote_code)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':po1_docentry' => $resInsert,
					':po1_itemcode' => isset($detail['po1_itemcode']) ? $detail['po1_itemcode'] : NULL,
					':po1_itemname' => isset($detail['po1_itemname']) ? $detail['po1_itemname'] : NULL,
					':po1_quantity' => is_numeric($detail['po1_quantity']) ? $detail['po1_quantity']  : 0,
					':po1_uom' => isset($detail['po1_uom']) ? $detail['po1_uom'] : NULL,
					':po1_whscode' => isset($detail['po1_whscode']) ? $detail['po1_whscode'] : NULL,
					':po1_price' => is_numeric($detail['po1_price']) ? $detail['po1_price'] : 0,
					':po1_vat' => is_numeric($detail['po1_vat']) ? $detail['po1_vat'] : 0,
					':po1_vatsum' => is_numeric($detail['po1_vatsum']) ? $detail['po1_vatsum'] : 0,
					':po1_discount' => is_numeric($detail['po1_discount']) ? $detail['po1_discount'] : 0,
					':po1_linetotal' => is_numeric($detail['po1_linetotal']) ? $detail['po1_linetotal'] : 0,
					':po1_costcode' => isset($detail['po1_costcode']) ? $detail['po1_costcode'] : NULL,
					':po1_ubusiness' => isset($detail['po1_ubusiness']) ? $detail['po1_ubusiness'] : NULL,
					':po1_project' => isset($detail['po1_project']) ? $detail['po1_project'] : NULL,
					':po1_acctcode' => is_numeric($detail['po1_acctcode']) ? $detail['po1_acctcode'] : 0,
					':po1_basetype' => is_numeric($detail['po1_basetype']) ? $detail['po1_basetype'] : 0,
					':po1_doctype' => is_numeric($detail['po1_doctype']) ? $detail['po1_doctype'] : 0,
					':po1_avprice' => is_numeric($detail['po1_avprice']) ? $detail['po1_avprice'] : 0,
					':po1_inventory' => is_numeric($detail['po1_inventory']) ? $detail['po1_inventory'] : NULL,
					':po1_linenum' => is_numeric($detail['po1_linenum']) ? $detail['po1_linenum'] : NULL,
					':po1_acciva' => is_numeric($detail['po1_acciva']) ? $detail['po1_acciva'] : NULL,
					':po1_codimp' => isset($detail['po1_codimp']) ? $detail['po1_codimp'] : NULL,
					':po1_ubication' => isset($detail['po1_ubication']) ? $detail['po1_ubication'] : NULL,
					':po1_baseline' => is_numeric($detail['po1_baseline']) ? $detail['po1_baseline'] : 0,
					':ote_code' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL
				));

				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// Se verifica que el detalle no de error insertando //
					//VALIDAR SI LOS ITEMS SON IGUALES A LOS DEL DOCUMENTO DE ORIGEN SIEMPRE QUE VENGA DE UN COPIAR DE
					if($Data['cpo_basetype'] == 11){
						//OBTENER NUMERO DOCUMENTO ORIGEN
						$DOC = "SELECT coc_docnum FROM dcoc WHERE coc_doctype = :coc_doctype AND coc_docentry = :coc_docentry";
						$RESULT_DOC = $this->pedeo->queryTable($DOC,array(':coc_docentry' =>$Data['cpo_baseentry'],':coc_doctype' => $Data['cpo_basetype']));
						foreach ($ContenidoDetalle as $key => $value) {
							# code...
							//VALIDAR SI EL ARTICULO DEL DOCUMENTO ACTUAL EXISTE EN EL DOCUMENTO DE ORIGEN
							$sql = "SELECT dcoc.coc_docnum,coc1.oc1_itemcode FROM dcoc INNER JOIN coc1 ON dcoc.coc_docentry = coc1.oc1_docentry 
							WHERE dcoc.coc_docentry = :coc_docentry AND dcoc.coc_doctype = :coc_doctype AND coc1.oc1_itemcode = :oc1_itemcode";
							$resSql = $this->pedeo->queryTable($sql,array(
								':coc_docentry' =>$Data['cpo_baseentry'],
								':coc_doctype' => $Data['cpo_basetype'],
								':oc1_itemcode' => $value['po1_itemcode']
							));
							
								if(isset($resSql[0])){
									//EL ARTICULO EXISTE EN EL DOCUMENTO DE ORIGEN
								}else {
									//EL ARTICULO NO EXISTE EN EL DOCUEMENTO DE ORIGEN
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data' => $value['em1_itemcode'],
										'mensaje'	=> 'El Item '.$value['em1_itemcode'].' no existe en el documento origen (Oferta #'.$RESULT_DOC[0]['coc_docnum'].')'
									);

									$this->response($respuesta);

									return;
								}
							}

					}
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
			if ($Data['cpo_basetype'] == 10) {


				$sqlEstado1 = " SELECT
									count(t1.sc1_itemcode) item,
									sum(t1.sc1_quantity) cantidad
								from dcsc t0
								inner join csc1 t1 on t0.csc_docentry = t1.sc1_docentry
								where t0.csc_docentry = :csc_docentry
								and t0.csc_doctype = :csc_doctype";


				$resEstado1 = $this->pedeo->queryTable($sqlEstado1, array(
					':csc_docentry' => $Data['cpo_baseentry'],
					':csc_doctype' => $Data['cpo_basetype']

				));

				$sqlEstado2 = "SELECT
									coalesce(count(distinct t3.po1_itemcode),0) item,
									coalesce(sum(t3.po1_quantity),0) cantidad
								from dcsc t0
								inner join csc1 t1 on t0.csc_docentry = t1.sc1_docentry
								left join dcpo t2 on t0.csc_docentry = t2.cpo_baseentry and t0.csc_doctype = t2.cpo_basetype
								left join cpo1 t3 on t2.cpo_docentry = t3.po1_docentry and t1.sc1_itemcode = t3.po1_itemcode
								where t0.csc_docentry = :csc_docentry
								and t0.csc_doctype = :csc_doctype";


				$resEstado2 = $this->pedeo->queryTable($sqlEstado2, array(
					':csc_docentry' => $Data['cpo_baseentry'],
					':csc_doctype' => $Data['cpo_basetype']

				));

				$item_sol = $resEstado1[0]['item'];
				$cantidad_sol = $resEstado1[0]['cantidad'];
				$item_ord = $resEstado2[0]['item'];
				$cantidad_ord = $resEstado2[0]['cantidad'];

				if ($item_sol == $item_ord  &&  $cantidad_sol == $cantidad_ord) {

					$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
										VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

					$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


						':bed_docentry' => $Data['cpo_baseentry'],
						':bed_doctype' => $Data['cpo_basetype'],
						':bed_status' => 3, //ESTADO CERRADO
						':bed_createby' => $Data['cpo_createby'],
						':bed_date' => date('Y-m-d'),
						':bed_baseentry' => $resInsert,
						':bed_basetype' => $Data['cpo_doctype']
					));


					if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' => $resInsertEstado,
							'mensaje'	=> 'No se pudo registrar la orden de compra'
						);


						$this->response($respuesta);

						return;
					}
				}
			}
			if ($Data['cpo_basetype'] == 11) {


				$sqlEstado1 = "SELECT distinct
													count(t1.oc1_itemcode) item,
													sum(t1.oc1_quantity) cantidad
													from dcoc t0
													inner join coc1 t1 on t0.coc_docentry = t1.oc1_docentry
													where t0.coc_docentry = :coc_docentry and t0.coc_doctype = :coc_doctype";


				$resEstado1 = $this->pedeo->queryTable($sqlEstado1, array(
					':coc_docentry' => $Data['cpo_baseentry'],
					':coc_doctype' => $Data['cpo_basetype']
				));

				$sqlEstado2 = "SELECT
																			coalesce(count(distinct t3.po1_itemcode),0) item,
																			coalesce(sum(t3.po1_quantity),0) cantidad
																			FROM dcoc t0
																			inner join coc1 t1 on t0.coc_docentry = t1.oc1_docentry
																			left join dcpo t2 on t0.coc_docentry = t2.cpo_baseentry and t0.coc_doctype = t2.cpo_basetype
																			left join cpo1 t3 on t2.cpo_docentry = t3.po1_docentry and t1.oc1_itemcode = t3.po1_itemcode
																			where t0.coc_docentry = :coc_docentry and t0.coc_doctype = :coc_doctype";

				$resEstado2 = $this->pedeo->queryTable($sqlEstado2, array(
					':coc_docentry' => $Data['cpo_baseentry'],
					':coc_doctype' => $Data['cpo_basetype']
				));

				$item_oc = abs($resEstado1[0]['item']);
				$cantidad_oc = abs($resEstado1[0]['cantidad']);
				$item_ord = abs($resEstado2[0]['item']);
				$cantidad_ord = abs($resEstado2[0]['cantidad']);


				if ($item_oc == $item_ord  &&  $cantidad_oc == $cantidad_ord) {

					$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																			VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

					$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


						':bed_docentry' => $Data['cpo_baseentry'],
						':bed_doctype' => $Data['cpo_basetype'],
						':bed_status' => 3, //ESTADO CERRADO
						':bed_createby' => $Data['cpo_createby'],
						':bed_date' => date('Y-m-d'),
						':bed_baseentry' => $resInsert,
						':bed_basetype' => $Data['cpo_doctype']
					));

					// print_r($resInsertEstado);exit();die();
					if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' => $resInsertEstado,
							'mensaje'	=> 'No se pudo registrar la la factura de compra'
						);


						$this->response($respuesta);

						return;
					}
				}
			}


			// Si todo sale bien despues de insertar el detalle de la cotizacion
			// se confirma la trasaccion  para que los cambios apliquen permanentemente
			// en la base de datos y se confirma la operacion exitosa.
			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'Orden de compra registrada con exito'
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
	public function updatePurchOrder_post()
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

		$sqlUpdate = "UPDATE dcpo	SET cpo_docdate=:cpo_docdate,cpo_duedate=:cpo_duedate, cpo_duedev=:cpo_duedev, cpo_pricelist=:cpo_pricelist, cpo_cardcode=:cpo_cardcode,
			  						cpo_cardname=:cpo_cardname, cpo_currency=:cpo_currency, cpo_contacid=:cpo_contacid, cpo_slpcode=:cpo_slpcode,
										cpo_empid=:cpo_empid, cpo_comment=:cpo_comment, cpo_doctotal=:cpo_doctotal, cpo_baseamnt=:cpo_baseamnt,
										cpo_taxtotal=:cpo_taxtotal, cpo_discprofit=:cpo_discprofit, cpo_discount=:cpo_discount, cpo_createat=:cpo_createat,
										cpo_baseentry=:cpo_baseentry, cpo_basetype=:cpo_basetype, cpo_doctype=:cpo_doctype, cpo_idadd=:cpo_idadd,
										cpo_adress=:cpo_adress, cpo_paytype=:cpo_paytype, cpo_internal_comments = :cpo_internal_comments WHERE cpo_docentry=:cpo_docentry";

		$this->pedeo->trans_begin();

		$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
			':cpo_docdate' => $this->validateDate($Data['cpo_docdate']) ? $Data['cpo_docdate'] : NULL,
			':cpo_duedate' => $this->validateDate($Data['cpo_duedate']) ? $Data['cpo_duedate'] : NULL,
			':cpo_duedev' => $this->validateDate($Data['cpo_duedev']) ? $Data['cpo_duedev'] : NULL,
			':cpo_pricelist' => is_numeric($Data['cpo_pricelist']) ? $Data['cpo_pricelist'] : 0,
			':cpo_cardcode' => isset($Data['cpo_cardcode']) ? $Data['cpo_cardcode'] : NULL,
			':cpo_cardname' => isset($Data['cpo_cardname']) ? $Data['cpo_cardname'] : NULL,
			':cpo_currency' => isset($Data['cpo_currency']) ? $Data['cpo_currency'] : NULL,
			':cpo_contacid' => isset($Data['cpo_contacid']) ? $Data['cpo_contacid'] : NULL,
			':cpo_slpcode' => is_numeric($Data['cpo_slpcode']) ? $Data['cpo_slpcode'] : 0,
			':cpo_empid' => is_numeric($Data['cpo_empid']) ? $Data['cpo_empid'] : 0,
			':cpo_comment' => isset($Data['cpo_comment']) ? $Data['cpo_comment'] : NULL,
			':cpo_doctotal' => is_numeric($Data['cpo_doctotal']) ? $Data['cpo_doctotal'] : 0,
			':cpo_baseamnt' => is_numeric($Data['cpo_baseamnt']) ? $Data['cpo_baseamnt'] : 0,
			':cpo_taxtotal' => is_numeric($Data['cpo_taxtotal']) ? $Data['cpo_taxtotal'] : 0,
			':cpo_discprofit' => is_numeric($Data['cpo_discprofit']) ? $Data['cpo_discprofit'] : 0,
			':cpo_discount' => is_numeric($Data['cpo_discount']) ? $Data['cpo_discount'] : 0,
			':cpo_createat' => $this->validateDate($Data['cpo_createat']) ? $Data['cpo_createat'] : NULL,
			':cpo_baseentry' => is_numeric($Data['cpo_baseentry']) ? $Data['cpo_baseentry'] : 0,
			':cpo_basetype' => is_numeric($Data['cpo_basetype']) ? $Data['cpo_basetype'] : 0,
			':cpo_doctype' => is_numeric($Data['cpo_doctype']) ? $Data['cpo_doctype'] : 0,
			':cpo_idadd' => isset($Data['cpo_idadd']) ? $Data['cpo_idadd'] : NULL,
			':cpo_adress' => isset($Data['cpo_adress']) ? $Data['cpo_adress'] : NULL,
			':cpo_paytype' => is_numeric($Data['cpo_paytype']) ? $Data['cpo_paytype'] : 0,
			':cpo_internal_comments' => is_numeric($Data['cpo_internal_comments']) ? $Data['cpo_internal_comments'] : NULL,
			':cpo_docentry' => $Data['cpo_docentry']
		));

		if (is_numeric($resUpdate) && $resUpdate == 1) {

			$this->pedeo->queryTable("DELETE FROM cpo1 WHERE po1_docentry=:po1_docentry", array(':po1_docentry' => $Data['cpo_docentry']));

			foreach ($ContenidoDetalle as $key => $detail) {

				$sqlInsertDetail = "INSERT INTO cpo1(po1_docentry, po1_itemcode, po1_itemname, po1_quantity, po1_uom, po1_whscode,
																			po1_price, po1_vat, po1_vatsum, po1_discount, po1_linetotal, po1_costcode, po1_ubusiness, po1_project,
																			po1_acctcode, po1_basetype, po1_doctype, po1_avprice, po1_inventory, po1_acciva, po1_linenum, po1_ubication)VALUES(:po1_docentry, :po1_itemcode, :po1_itemname, :po1_quantity,
																			:po1_uom, :po1_whscode,:po1_price, :po1_vat, :po1_vatsum, :po1_discount, :po1_linetotal, :po1_costcode, :po1_ubusiness, :po1_project,
																			:po1_acctcode, :po1_basetype, :po1_doctype, :po1_avprice, :po1_inventory, :po1_acciva,:po1_linenum, :po1_ubication)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':po1_docentry' => $Data['cpo_docentry'],
					':po1_itemcode' => isset($detail['po1_itemcode']) ? $detail['po1_itemcode'] : NULL,
					':po1_itemname' => isset($detail['po1_itemname']) ? $detail['po1_itemname'] : NULL,
					':po1_quantity' => is_numeric($detail['po1_quantity']) ? $detail['po1_quantity'] : 0,
					':po1_uom' => isset($detail['po1_uom']) ? $detail['po1_uom'] : NULL,
					':po1_whscode' => isset($detail['po1_whscode']) ? $detail['po1_whscode'] : NULL,
					':po1_price' => is_numeric($detail['po1_price']) ? $detail['po1_price'] : 0,
					':po1_vat' => is_numeric($detail['po1_vat']) ? $detail['po1_vat'] : 0,
					':po1_vatsum' => is_numeric($detail['po1_vatsum']) ? $detail['po1_vatsum'] : 0,
					':po1_discount' => is_numeric($detail['po1_discount']) ? $detail['po1_discount'] : 0,
					':po1_linetotal' => is_numeric($detail['po1_linetotal']) ? $detail['po1_linetotal'] : 0,
					':po1_costcode' => isset($detail['po1_costcode']) ? $detail['po1_costcode'] : NULL,
					':po1_ubusiness' => isset($detail['po1_ubusiness']) ? $detail['po1_ubusiness'] : NULL,
					':po1_project' => isset($detail['po1_project']) ? $detail['po1_project'] : NULL,
					':po1_acctcode' => is_numeric($detail['po1_acctcode']) ? $detail['po1_acctcode'] : 0,
					':po1_basetype' => is_numeric($detail['po1_basetype']) ? $detail['po1_basetype'] : 0,
					':po1_doctype' => is_numeric($detail['po1_doctype']) ? $detail['po1_doctype'] : 0,
					':po1_avprice' => is_numeric($detail['po1_avprice']) ? $detail['po1_avprice'] : 0,
					':po1_inventory' => is_numeric($detail['po1_inventory']) ? $detail['po1_inventory'] : NULL,
					':po1_acciva' => is_numeric($detail['po1_acciva']) ? $detail['po1_acciva'] : NULL,
					':po1_linenum' => is_numeric($detail['po1_linenum']) ? $detail['po1_linenum'] : NULL,
					':po1_ubication' => is_numeric($detail['po1_ubication']) ? $detail['po1_ubication'] : NULL
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
				'mensaje' => 'Orden de compra actualizada con exito'
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
	public function getPurchOrder_get()
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

		$sqlSelect = self::getColumn('dcpo', 'cpo', '', '', $DECI_MALES, $Data['business'], $Data['branch']);

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
	public function getPurchOrderById_get()
	{

		$Data = $this->get();

		if (!isset($Data['cpo_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM dcpo WHERE cpo_docentry =:cpo_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":cpo_docentry" => $Data['cpo_docentry']));

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
	public function getPurchOrderDetail_get()
	{

		$Data = $this->get();

		if (!isset($Data['po1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT cpo1.*,dmar.dma_series_code
											 FROM cpo1
											 INNER JOIN dmar	ON cpo1.po1_itemcode = dmar.dma_item_code
											 WHERE po1_docentry =:po1_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":po1_docentry" => $Data['po1_docentry']));

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
	public function getPurchOrderDetailCopy_get()
	{

		$Data = $this->get();

		if (!isset($Data['po1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$copy = $this->documentcopy->Copy($Data['po1_docentry'],'dcpo','cpo1','cpo','po1');

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
	public function getPurchOrderBySN_get()
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

		$copy = $this->documentcopy->CopyData('dcpo','cpo',$Data['dms_card_code'],$Data['business'],$Data['branch'],36);


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