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
		$this->load->library('Tasa');
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

				':mau_doctype'   => $Data['dvc_doctype'],
				':pgu_code_user' => $Data['dvc_createby'],
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
					$TotalDocumento = $Data['dvc_doctotal'];
					$doctype =  $value['doctype'];
					$modelo = $value['modelo'];

					$sqlTasaMonedaModelo = "SELECT COALESCE(get_dynamic_conversion(:mau_currency,:doc_currency,:doc_date,:doc_total,get_localcur()), 0) AS monto"; 
					$resTasaMonedaModelo = $this->pedeo->queryTable($sqlTasaMonedaModelo, array(
						':mau_currency' => $value['mau_currency'],
						':doc_currency' => $Data['dvc_currency'],
						':doc_date' 	=> $Data['dvc_docdate'],
						':doc_total' 	=> $TotalDocumento
					));

					if ( $resTasaMonedaModelo[0]['monto'] == 0 ){
						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' => 'No se encrontro la tasa de cambio para la moneda del modelo :'. $value['mau_currency'].'en la fecha del documento '.$Data['dvc_docdate']
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
							
							$resAprobacion = $this->aprobacion->setAprobacion($Data, $ContenidoDetalle, 'dvc', 'vc1', $ressq[0]['mau_quantity'], count(explode(',', $ressq[0]['mau_approvers'])), $ressq[0]['mau_docentry'], $Data['business'], $Data['branch']);
							
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
							
							$resAprobacion = $this->aprobacion->setAprobacion($Data, $ContenidoDetalle, 'dvc', 'vc1', $ressq[0]['mau_quantity'], count(explode(',', $ressq[0]['mau_approvers'])), $ressq[0]['mau_docentry'], $Data['business'], $Data['branch']);
				
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

		// //BUSCANDO LA NUMERACION DEL DOCUMENTO
		$DocNumVerificado = $this->documentnumbering->NumberDoc($Data['dvc_series'],$Data['dvc_docdate'],$Data['dvc_duedate']);
		
		if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0){

		}else if ($DocNumVerificado['error']){

			return $this->response($DocNumVerificado, REST_Controller::HTTP_BAD_REQUEST);
		}



		$sqlInsert = "INSERT INTO dvct(dvc_series, dvc_docnum, dvc_docdate, dvc_duedate, dvc_duedev, dvc_pricelist, dvc_cardcode,
                      dvc_cardname, dvc_currency, dvc_contacid, dvc_slpcode, dvc_empid, dvc_comment, dvc_doctotal, dvc_baseamnt, dvc_taxtotal,
                      dvc_discprofit, dvc_discount, dvc_createat, dvc_baseentry, dvc_basetype, dvc_doctype, dvc_idadd, dvc_adress, dvc_paytype,
                      dvc_createby,business,branch)VALUES(:dvc_series, :dvc_docnum, :dvc_docdate, :dvc_duedate, :dvc_duedev, :dvc_pricelist, :dvc_cardcode, :dvc_cardname,
                      :dvc_currency, :dvc_contacid, :dvc_slpcode, :dvc_empid, :dvc_comment, :dvc_doctotal, :dvc_baseamnt, :dvc_taxtotal, :dvc_discprofit, :dvc_discount,
                      :dvc_createat, :dvc_baseentry, :dvc_basetype, :dvc_doctype, :dvc_idadd, :dvc_adress, :dvc_paytype,:dvc_createby,:business,:branch)";


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
			':branch' => isset($Data['branch']) ? $Data['branch'] : NULL
		
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
				':bed_status' => 1, //ESTADO CERRADO
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


					$this->response($respuesta);

					return;
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

				$sqlInsertDetail = "INSERT INTO vct1(vc1_docentry,vc1_itemcode, vc1_itemname, vc1_quantity, vc1_uom, vc1_whscode,
                                    vc1_price, vc1_vat, vc1_vatsum, vc1_discount, vc1_linetotal, vc1_costcode, vc1_ubusiness, vc1_project,
                                    vc1_acctcode, vc1_basetype, vc1_doctype, vc1_avprice, vc1_inventory, vc1_linenum, vc1_acciva, vc1_codimp, vc1_ubication, 
									ote_code,vc1_baseline)
									VALUES(:vc1_docentry,:vc1_itemcode, :vc1_itemname, :vc1_quantity,:vc1_uom, :vc1_whscode,:vc1_price, :vc1_vat, :vc1_vatsum, :vc1_discount, 
									:vc1_linetotal, :vc1_costcode, :vc1_ubusiness, :vc1_project,:vc1_acctcode, :vc1_basetype, :vc1_doctype, :vc1_avprice, :vc1_inventory,
									:vc1_linenum,:vc1_acciva,:vc1_codimp, :vc1_ubication, :ote_code,:vc1_baseline)";

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
					':vc1_baseline' => is_numeric($detail['vc1_baseline']) ? $detail['vc1_baseline'] : 0
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
				'mensaje' => 'Cotización registrada con exito'
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

		$sqlUpdate = "UPDATE dvct	SET dvc_docdate=:dvc_docdate,dvc_duedate=:dvc_duedate, dvc_duedev=:dvc_duedev, dvc_pricelist=:dvc_pricelist, dvc_cardcode=:dvc_cardcode,
			  						dvc_cardname=:dvc_cardname, dvc_currency=:dvc_currency, dvc_contacid=:dvc_contacid, dvc_slpcode=:dvc_slpcode,
										dvc_empid=:dvc_empid, dvc_comment=:dvc_comment, dvc_doctotal=:dvc_doctotal, dvc_baseamnt=:dvc_baseamnt,
										dvc_taxtotal=:dvc_taxtotal, dvc_discprofit=:dvc_discprofit, dvc_discount=:dvc_discount, dvc_createat=:dvc_createat,
										dvc_baseentry=:dvc_baseentry, dvc_basetype=:dvc_basetype, dvc_doctype=:dvc_doctype, dvc_idadd=:dvc_idadd,
										dvc_adress=:dvc_adress, dvc_paytype=:dvc_paytype ,business = :business,branch = :branch
										WHERE dvc_docentry=:dvc_docentry";

		$this->pedeo->trans_begin();

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
			':dvc_createat' => $this->validateDate($Data['dvc_createat']) ? $Data['dvc_createat'] : NULL,
			':dvc_baseentry' => is_numeric($Data['dvc_baseentry']) ? $Data['dvc_baseentry'] : 0,
			':dvc_basetype' => is_numeric($Data['dvc_basetype']) ? $Data['dvc_basetype'] : 0,
			':dvc_doctype' => is_numeric($Data['dvc_doctype']) ? $Data['dvc_doctype'] : 0,
			':dvc_idadd' => isset($Data['dvc_idadd']) ? $Data['dvc_idadd'] : NULL,
			':dvc_adress' => isset($Data['dvc_adress']) ? $Data['dvc_adress'] : NULL,
			':dvc_paytype' => is_numeric($Data['dvc_paytype']) ? $Data['dvc_paytype'] : 0,
			':business' => isset($Data['business']) ? $Data['business'] : NULL,
			':branch' => isset($Data['branch']) ? $Data['branch'] : NULL,
			':dvc_docentry' => $Data['dvc_docentry']
		));

		if (is_numeric($resUpdate) && $resUpdate == 1) {

			$this->pedeo->queryTable("DELETE FROM vct1 WHERE vc1_docentry=:vc1_docentry", array(':vc1_docentry' => $Data['dvc_docentry']));

			foreach ($ContenidoDetalle as $key => $detail) {

				$sqlInsertDetail = "INSERT INTO vct1(vc1_docentry, vc1_itemcode, vc1_itemname, vc1_quantity, vc1_uom, vc1_whscode,
								vc1_price, vc1_vat, vc1_vatsum, vc1_discount, vc1_linetotal, vc1_costcode, vc1_ubusiness, vc1_project,
								vc1_acctcode, vc1_basetype, vc1_doctype, vc1_avprice, vc1_inventory, vc1_acciva, vc1_linenum, vc1_ubication)VALUES(:vc1_docentry, :vc1_itemcode, :vc1_itemname, :vc1_quantity,
								:vc1_uom, :vc1_whscode,:vc1_price, :vc1_vat, :vc1_vatsum, :vc1_discount, :vc1_linetotal, :vc1_costcode, :vc1_ubusiness, :vc1_project,
								:vc1_acctcode, :vc1_basetype, :vc1_doctype, :vc1_avprice, :vc1_inventory, :vc1_acciva,:vc1_linenum, :vc1_ubication)";

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
					':vc1_ubication' => is_numeric($detail['vc1_ubication']) ? $detail['vc1_ubication'] : NULL
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

		$sqlSelect = self::getColumn('dvct', 'dvc', '', '', $DECI_MALES, $Data['business'], $Data['branch']);

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

		$sqlSelect = self::getColumn('dvct', 'dvc', '', '', $DECI_MALES, $Data['business'], $Data['branch'], 0, $Data['page'], 1);

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

		if (!isset($Data['dvc_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

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

			$copy = $this->documentcopy->Copy($Data['vc1_docentry'],'dvct','vct1','dvc','vc1');

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
}