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

				':mau_doctype'   => $Data['vov_doctype'],
				':pgu_code_user' => $Data['vov_createby'],
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
					$TotalDocumento = $Data['vov_doctotal'];
					$doctype =  $value['doctype'];
					$modelo = $value['modelo'];

					$sqlTasaMonedaModelo = "SELECT COALESCE(get_dynamic_conversion(:mau_currency,:doc_currency,:doc_date,:doc_total,get_localcur()), 0) AS monto"; 
					$resTasaMonedaModelo = $this->pedeo->queryTable($sqlTasaMonedaModelo, array(
						':mau_currency' => $value['mau_currency'],
						':doc_currency' => $Data['vov_currency'],
						':doc_date' 	=> $Data['vov_docdate'],
						':doc_total' 	=> $TotalDocumento
					));

					if ( $resTasaMonedaModelo[0]['monto'] == 0 ){
						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' => 'No se encrontro la tasa de cambio para la moneda del modelo :'. $value['mau_currency'].'en la fecha del documento '.$Data['vov_docdate']
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
							
							$resAprobacion = $this->aprobacion->setAprobacion($Data, $ContenidoDetalle, 'vov', 'ov1', $ressq[0]['mau_quantity'], count(explode(',', $ressq[0]['mau_approvers'])), $ressq[0]['mau_docentry'], $Data['business'], $Data['branch']);
							
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

							':doctotal' 	  => $TotalDocumento,
							':mau_doctype'  => $doctype,
							':mau_docentry' => $modelo
						));

						if (isset($ressq[0])) {
							
							$resAprobacion = $this->aprobacion->setAprobacion($Data, $ContenidoDetalle, 'vov', 'ov1', $ressq[0]['mau_quantity'], count(explode(',', $ressq[0]['mau_approvers'])), $ressq[0]['mau_docentry'], $Data['business'], $Data['branch']);
				
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




		$sqlInsert = "INSERT INTO dvov(vov_series, vov_docnum, vov_docdate, vov_duedate, vov_duedev, vov_pricelist, vov_cardcode,
                      vov_cardname, vov_currency, vov_contacid, vov_slpcode, vov_empid, vov_comment, vov_doctotal, vov_baseamnt, vov_taxtotal,
                      vov_discprofit, vov_discount, vov_createat, vov_baseentry, vov_basetype, vov_doctype, vov_idadd, vov_adress, vov_paytype,
                      vov_createby,business,branch, vov_internal_comments)VALUES(:vov_series, :vov_docnum, :vov_docdate, :vov_duedate, :vov_duedev, :vov_pricelist, :vov_cardcode, :vov_cardname,
                      :vov_currency, :vov_contacid, :vov_slpcode, :vov_empid, :vov_comment, :vov_doctotal, :vov_baseamnt, :vov_taxtotal, :vov_discprofit, :vov_discount,
                      :vov_createat, :vov_baseentry, :vov_basetype, :vov_doctype, :vov_idadd, :vov_adress, :vov_paytype,:vov_createby,:business,:branch, :vov_internal_comments)";


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
			':vov_internal_comments' => isset($Data['vov_internal_comments']) ? $Data['vov_internal_comments'] : NULL
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
                                    ov1_acctcode, ov1_basetype, ov1_doctype, ov1_avprice, ov1_inventory, ov1_acciva, ov1_codimp,ov1_ubication,ote_code,ov1_baseline,detalle_modular)
									VALUES(:ov1_docentry, :ov1_linenum,:ov1_itemcode, :ov1_itemname, :ov1_quantity,:ov1_uom, :ov1_whscode,:ov1_price, :ov1_vat, 
									:ov1_vatsum,:ov1_discount, :ov1_linetotal, :ov1_costcode, :ov1_ubusiness, :ov1_project,:ov1_acctcode, :ov1_basetype, :ov1_doctype, 
									:ov1_avprice, :ov1_inventory, :ov1_acciva, :ov1_codimp,:ov1_ubication,:ote_code,:ov1_baseline,:detalle_modular)";

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
					':detalle_modular' => isset($detail['detalle_modular']) ? $detail['detalle_modular'] : NULL
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
								coalesce(sum(c.sn1_quantity),0) as cantidad
							from tcsn t 
							inner join csn1 c on t.csn_docentry = c.sn1_docentry 
							where t.csn_doctype = :csn_doctype and t.csn_docentry = :csn_docentry";
					$resSql1 = $this->pedeo->queryTable($sql1,array(
						':csn_doctype' => $Data['vov_basetype'],
						':csn_docentry' => $Data['vov_basentry']
					));

					$sql2 = "SELECT 
								coalesce(count(distinct v.ov1_baseline),0) as item,
								coalesce(sum(v.ov1_quantity),0) as cantidad
							from tcsn t 
							inner join csn1 c on t.csn_docentry = c.sn1_docentry
							left join dvov d on t.csn_docentry = d.vov_baseentry and t.csn_doctype = d.vov_basetype 
							left join vov1 v on d.vov_docentry = v.ov1_docentry and c.sn1_itemcode = v.ov1_itemcode and c.sn1_linenum = v.ov1_baseline 
							where d.vov_basetype  = :csn_doctype and d.vov_baseentry  = :csn_docentry";

					$resSql2 = $this->pedeo->queryTable($sql2,array(
						':csn_doctype' => $Data['vov_basetype'],
						':csn_docentry' => $Data['vov_basentry']
					));

					$item_1 = isset($resSql1[0]) ? $resSql1[0]['item'] : $item_1;
					$cant_1 = isset($resSql1[0]) ? $resSql1[0]['cantidad'] : $cant_1;
					$item_2 = isset($resSql2[0]) ? $resSql2[0]['item'] : $item_2;
					$item_2 = isset($resSql2[0]) ? $resSql2[0]['cantidad'] : $item_2;

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
				'mensaje' => 'Pedido registrado con exito'
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
										vov_adress=:vov_adress, vov_paytype=:vov_paytype, business = :business,branch = :branch, vov_internal_comments = :vov_internal_comments
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
			':vov_docentry' => $Data['vov_docentry']
		));

		if (is_numeric($resUpdate) && $resUpdate == 1) {

			$this->pedeo->queryTable("DELETE FROM vct1 WHERE ov1_docentry=:ov1_docentry", array(':ov1_docentry' => $Data['vov_docentry']));

			foreach ($ContenidoDetalle as $key => $detail) {

				$sqlInsertDetail = "INSERT INTO vov1(ov1_docentry, ov1_itemcode, ov1_itemname, ov1_quantity, ov1_uom, ov1_whscode,
																			ov1_price, ov1_vat, ov1_vatsum, ov1_discount, ov1_linetotal, ov1_costcode, ov1_ubusiness, ov1_project,
																			ov1_acctcode, ov1_basetype, ov1_doctype, ov1_avprice, ov1_inventory, ov1_acciva,ov1_ubication,ov1_linenum)VALUES(:ov1_docentry, :ov1_itemcode, :ov1_itemname, :ov1_quantity,
																			:ov1_uom, :ov1_whscode,:ov1_price, :ov1_vat, :ov1_vatsum, :ov1_discount, :ov1_linetotal, :ov1_costcode, :ov1_ubusiness, :ov1_project,
																			:ov1_acctcode, :ov1_basetype, :ov1_doctype, :ov1_avprice, :ov1_inventory, :ov1_acciva,:ov1_ubication,:ov1_linenum)";

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
					':ov1_linenum' => isset($detail['ov1_linenum']) ? $detail['ov1_linenum'] : NULL
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

		$sqlSelect = self::getColumn('dvov', 'vov', '', '', $DECI_MALES, $Data['business'], $Data['branch']);

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

		if (!isset($Data['vov_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM dvov WHERE vov_docentry =:vov_docentry";

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


		$copy = $this->documentcopy->Copy($Data['ov1_docentry'],'dvov','vov1','vov','ov1','detalle_modular::jsonb');

		
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
}