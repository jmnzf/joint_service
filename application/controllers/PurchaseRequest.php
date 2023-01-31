<?php
// SOLICITUD DE COMPRAS
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class PurchaseRequest extends REST_Controller
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
		$this->load->library('Aprobacion');
	}

	//CREAR NUEVA solicitud DE compras
	public function createPurchaseRequest_post()
	{

		$DECI_MALES =  $this->generic->getDecimals();
		
		$Data = $this->post();

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
		$CANTUOMPURCHASE = 0; //CANTIDAD EN UNIDAD DE MEDIDA


		// Se globaliza la variable sqlDetalleAsiento
		$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
												ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
												ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
												ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref)VALUES (:ac1_trans_id, :ac1_account,
												:ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
												:ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
												:ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
												:ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref)";


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
				'mensaje' => 'No se encontro el detalle de la solicitud de compras'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}
		//BUSCANDO LA NUMERACION DEL DOCUMENTO
		$sqlNumeracion = " SELECT pgs_nextnum,pgs_last_num FROM  pgdn WHERE pgs_id = :pgs_id";

		$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['csc_series']));

		if (isset($resNumeracion[0])) {

			$numeroActual = $resNumeracion[0]['pgs_nextnum'];
			$numeroFinal  = $resNumeracion[0]['pgs_last_num'];
			$numeroSiguiente = ($numeroActual + 1);

			if ($numeroSiguiente <= $numeroFinal) {

				$DocNumVerificado = $numeroSiguiente;
			} else {

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'La serie de la numeración esta llena'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
		} else {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro la serie de numeración para el documento'
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

		// FIN PROCESO PARA OBTENRE LA CARPETA Principal

		// PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO
		// SE BUSCA LA MONEDA LOCAL PARAMETRIZADA
		$sqlMonedaLoc = "SELECT pgm_symbol FROM pgec WHERE pgm_principal = :pgm_principal";
		$resMonedaLoc = $this->pedeo->queryTable($sqlMonedaLoc, array(':pgm_principal' => 1));

		if (isset($resMonedaLoc[0])) {
		} else {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro la moneda local.'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$MONEDALOCAL = trim($resMonedaLoc[0]['pgm_symbol']);

		// SE BUSCA LA MONEDA DE SISTEMA PARAMETRIZADA
		$sqlMonedaSys = "SELECT pgm_symbol FROM pgec WHERE pgm_system = :pgm_system";
		$resMonedaSys = $this->pedeo->queryTable($sqlMonedaSys, array(':pgm_system' => 1));

		if (isset($resMonedaSys[0])) {
		} else {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro la moneda de sistema.'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}


		$MONEDASYS = trim($resMonedaSys[0]['pgm_symbol']);

		//SE BUSCA LA TASA DE CAMBIO CON RESPECTO A LA MONEDA QUE TRAE EL DOCUMENTO A CREAR CON LA MONEDA LOCAL
		// Y EN LA MISMA FECHA QUE TRAE EL DOCUMENTO
		$sqlBusTasa = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
		$resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $Data['csc_currency'], ':tsa_date' => $Data['csc_docdate']));

		if (isset($resBusTasa[0])) {
		} else {

			if (trim($Data['csc_currency']) != $MONEDALOCAL) {

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'No se encrontro la tasa de cambio para la moneda: ' . $Data['csc_currency'] . ' en la actual fecha del documento: ' . $Data['csc_docdate'] . ' y la moneda local: ' . $resMonedaLoc[0]['pgm_symbol']
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
		}


		$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
		$resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $Data['csc_docdate']));

		if (isset($resBusTasa2[0])) {
		} else {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :' . $Data['csc_docdate']
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$TasaDocLoc = isset($resBusTasa[0]['tsa_value']) ? $resBusTasa[0]['tsa_value'] : 1;
		$TasaLocSys = $resBusTasa2[0]['tsa_value'];

		// FIN DEL PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO




		// SE VERIFICA SI EL DOCUMENTO A CREAR NO  VIENE DE UN PROCESO DE APROBACION Y NO ESTE APROBADO

		$sqlVerificarAprobacion = "SELECT * FROM tbed WHERE bed_docentry =:bed_docentry AND bed_doctype =:bed_doctype AND bed_status =:bed_status";
		$resVerificarAprobacion = $this->pedeo->queryTable($sqlVerificarAprobacion, array(

			':bed_docentry' => $Data['csc_baseentry'],
			':bed_doctype'  => $Data['csc_basetype'],
			':bed_status'   => 4 // 4 APROBADO SEGUN MODELO DE APROBACION
		));



		if (!isset($resVerificarAprobacion[0])) {

			$sqlDocModelo = "SELECT mau_docentry as modelo, mau_doctype as doctype, mau_quantity as cantidad,
							au1_doctotal as doctotal,au1_doctotal2 as doctotal2, au1_c1 as condicion,mau_currency
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

				':mau_doctype'   => $Data['csc_doctype'],
				':pgu_code_user' => $Data['csc_createby'],
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
					$TotalDocumento = $Data['csc_doctotal'];
					$doctype =  $value['doctype'];
					$modelo = $value['modelo'];

					$sqlTasaMonedaModelo = "SELECT COALESCE(get_dynamic_conversion(:mau_currency,:doc_currency,:doc_date,:doc_total,get_localcur()), 0) AS monto"; 
					$resTasaMonedaModelo = $this->pedeo->queryTable($sqlTasaMonedaModelo, array(
						':mau_currency' => $value['mau_currency'],
						':doc_currency' => $Data['csc_currency'],
						':doc_date' 	=> $Data['csc_docdate'],
						':doc_total' 	=> $TotalDocumento
					));

					if ( $resTasaMonedaModelo[0]['monto'] == 0 ){
						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' => 'No se encrontro la tasa de cambio para la moneda del modelo :'. $value['mau_currency'].'en la fecha del documento '.$Data['csc_docdate']
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
							
							$resAprobacion = $this->aprobacion->setAprobacion($Data, $ContenidoDetalle, 'csc', 'sc1', $ressq[0]['mau_quantity'], count(explode(',', $ressq[0]['mau_approvers'])), $ressq[0]['mau_docentry'], $Data['business'], $Data['branch']);
							
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
							
							$resAprobacion = $this->aprobacion->setAprobacion($Data, $ContenidoDetalle, 'csc', 'sc1', $ressq[0]['mau_quantity'], count(explode(',', $ressq[0]['mau_approvers'])), $ressq[0]['mau_docentry'], $Data['business'], $Data['branch']);
				
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

		$sqlInsert = "INSERT INTO dcsc(csc_series, csc_docnum, csc_docdate, csc_duedate, csc_duedev, csc_pricelist, csc_cardcode,
                      csc_cardname, csc_currency, csc_contacid, csc_slpcode, csc_empid, csc_comment, csc_doctotal, csc_baseamnt, csc_taxtotal,
                      csc_discprofit, csc_discount, csc_createat, csc_baseentry, csc_basetype, csc_doctype, csc_idadd, csc_adress, csc_paytype,
                      csc_attch,csc_createby,business,branch)VALUES(:csc_series, :csc_docnum, :csc_docdate, :csc_duedate, :csc_duedev, :csc_pricelist, :csc_cardcode, :csc_cardname,
                      :csc_currency, :csc_contacid, :csc_slpcode, :csc_empid, :csc_comment, :csc_doctotal, :csc_baseamnt, :csc_taxtotal, :csc_discprofit, :csc_discount,
                      :csc_createat, :csc_baseentry, :csc_basetype, :csc_doctype, :csc_idadd, :csc_adress, :csc_paytype, :csc_attch,:csc_createby,:business,:branch)";


		// Se Inicia la transaccion,
		// Todas las consultas de modificacion siguientes
		// aplicaran solo despues que se confirme la transaccion,
		// de lo contrario no se aplicaran los cambios y se devolvera
		// la base de datos a su estado original.

		$this->pedeo->trans_begin();

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			':csc_docnum' => $DocNumVerificado,
			':csc_series' => is_numeric($Data['csc_series']) ? $Data['csc_series'] : 0,
			':csc_docdate' => $this->validateDate($Data['csc_docdate']) ? $Data['csc_docdate'] : NULL,
			':csc_duedate' => $this->validateDate($Data['csc_duedate']) ? $Data['csc_duedate'] : NULL,
			':csc_duedev' => $this->validateDate($Data['csc_duedev']) ? $Data['csc_duedev'] : NULL,
			':csc_pricelist' => 1,
			':csc_cardcode' => isset($Data['csc_cardcode']) ? $Data['csc_cardcode'] : NULL,
			':csc_cardname' => isset($Data['csc_cardname']) ? $Data['csc_cardname'] : NULL,
			':csc_currency' => isset($Data['csc_currency']) ? $Data['csc_currency'] : NULL,
			':csc_contacid' => isset($Data['csc_contacid']) ? $Data['csc_contacid'] : NULL,
			':csc_slpcode' => is_numeric($Data['csc_slpcode']) ? $Data['csc_slpcode'] : 0,
			':csc_empid' => is_numeric($Data['csc_empid']) ? $Data['csc_empid'] : 0,
			':csc_comment' => isset($Data['csc_comment']) ? $Data['csc_comment'] : NULL,
			':csc_doctotal' => is_numeric($Data['csc_doctotal']) ? $Data['csc_doctotal'] : 0,
			':csc_baseamnt' => is_numeric($Data['csc_baseamnt']) ? $Data['csc_baseamnt'] : 0,
			':csc_taxtotal' => is_numeric($Data['csc_taxtotal']) ? $Data['csc_taxtotal'] : 0,
			':csc_discprofit' => is_numeric($Data['csc_discprofit']) ? $Data['csc_discprofit'] : 0,
			':csc_discount' => is_numeric($Data['csc_discount']) ? $Data['csc_discount'] : 0,
			':csc_createat' => $this->validateDate($Data['csc_createat']) ? $Data['csc_createat'] : NULL,
			':csc_baseentry' => is_numeric($Data['csc_baseentry']) ? $Data['csc_baseentry'] : 0,
			':csc_basetype' => is_numeric($Data['csc_basetype']) ? $Data['csc_basetype'] : 0,
			':csc_doctype' => is_numeric($Data['csc_doctype']) ? $Data['csc_doctype'] : 0,
			':csc_idadd' => isset($Data['csc_idadd']) ? $Data['csc_idadd'] : NULL,
			':csc_adress' => isset($Data['csc_adress']) ? $Data['csc_adress'] : NULL,
			':csc_paytype' => is_numeric($Data['csc_paytype']) ? $Data['csc_paytype'] : 0,
			':csc_createby' => isset($Data['csc_createby']) ? $Data['csc_createby'] : NULL,
			':business' => isset($Data['business']) ? $Data['business'] : NULL,
			':branch' => isset($Data['branch']) ? $Data['branch'] : NULL,
			':csc_attch' => $this->getUrl(count(trim(($Data['csc_attch']))) > 0 ? $Data['csc_attch'] : NULL, $resMainFolder[0]['main_folder'])
		));

		if (is_numeric($resInsert) && $resInsert > 0) {

			// Se actualiza la serie de la numeracion del documento

			$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id'      => $Data['csc_series']
			));


			if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
			} else {
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'    => $resActualizarNumeracion,
					'mensaje'	=> 'No se pudo crear la solicitud de compras',
					'proceso' => 'Actualización de serie'
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
				':bed_doctype' => $Data['csc_doctype'],
				':bed_status' => 1, //ESTADO ABIERTO
				':bed_createby' => $Data['csc_createby'],
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
					'mensaje'	=> 'No se pudo registrar la solicitud de compras',
					'proceso' => 'Insertar estado documento'
				);


				$this->response($respuesta);

				return;
			}


			// SE CIERRA EL DOCUMENTO PRELIMINAR SI VIENE DE UN MODELO DE APROBACION
			// SI EL DOCTYPE = 21
			if ($Data['csc_basetype'] == 21) {

				$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

				$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


					':bed_docentry' => $Data['csc_baseentry'],
					':bed_doctype' => $Data['csc_basetype'],
					':bed_status' => 3, //ESTADO CERRADO
					':bed_createby' => $Data['csc_createby'],
					':bed_date' => date('Y-m-d'),
					':bed_baseentry' => $resInsert,
					':bed_basetype' => $Data['csc_doctype']
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

			//FIN PROCESO GUARDAR ESTADO DEL DOCUMENTO


			//SE APLICA PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS
			if (isset($Data['csc_baseentry']) && is_numeric($Data['csc_baseentry']) && isset($Data['csc_basetype']) && is_numeric($Data['csc_basetype'])) {

				// SE VERIFICA SI EL DOCUMENTO VIENE DE UN DOCUMENTO APROBADO
				if ($Data['csc_basetype'] == 21) {

					//BUSCANDO EL DOCUMENTO APROBADO

					$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
															bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency)
															VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
															:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency)";

					$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

						':bmd_doctype' => is_numeric($Data['csc_doctype']) ? $Data['csc_doctype'] : 0,
						':bmd_docentry' => $resInsert,
						':bmd_createat' => $this->validateDate($Data['csc_createat']) ? $Data['csc_createat'] : NULL,
						':bmd_doctypeo' =>  0, //ORIGEN
						':bmd_docentryo' => 0,  //ORIGEN
						':bmd_tdi' => $Data['csc_basetype'], // DOCUMENTO INICIAL
						':bmd_ndi' => $Data['csc_baseentry'], // DOCUMENTO INICIAL
						':bmd_docnum' => $DocNumVerificado,
						':bmd_doctotal' => is_numeric($Data['csc_doctotal']) ? $Data['csc_doctotal'] : 0,
						':bmd_cardcode' => isset($Data['csc_cardcode']) ? $Data['csc_cardcode'] : NULL,
						':bmd_cardtype' => 2,
						':bmd_currency' => isset($Data['csc_currency'])?$Data['csc_currency']:NULL,
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
					////
				} else {

					$sqlDocInicio = "SELECT bmd_tdi, bmd_ndi FROM tbmd WHERE  bmd_doctype = :bmd_doctype AND bmd_docentry = :bmd_docentry";
					$resDocInicio = $this->pedeo->queryTable($sqlDocInicio, array(
						':bmd_doctype' => $Data['csc_basetype'],
						':bmd_docentry' => $Data['csc_baseentry']
					));


					if (isset($resDocInicio[0])) {

						$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
																bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency)
																VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
																:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency)";

						$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

							':bmd_doctype' => is_numeric($Data['csc_doctype']) ? $Data['csc_doctype'] : 0,
							':bmd_docentry' => $resInsert,
							':bmd_createat' => $this->validateDate($Data['csc_createat']) ? $Data['csc_createat'] : NULL,
							':bmd_doctypeo' => is_numeric($Data['csc_basetype']) ? $Data['csc_basetype'] : 0, //ORIGEN
							':bmd_docentryo' => is_numeric($Data['csc_baseentry']) ? $Data['csc_baseentry'] : 0,  //ORIGEN
							':bmd_tdi' => $resDocInicio[0]['bmd_tdi'], // DOCUMENTO INICIAL
							':bmd_ndi' => $resDocInicio[0]['bmd_ndi'], // DOCUMENTO INICIAL
							':bmd_docnum' => $DocNumVerificado,
							':bmd_doctotal' => is_numeric($Data['csc_doctotal']) ? $Data['csc_doctotal'] : 0,
							':bmd_cardcode' => isset($Data['csc_cardcode']) ? $Data['csc_cardcode'] : NULL,
							':bmd_cardtype' => 2,
							':bmd_currency' => isset($Data['csc_currency'])?$Data['csc_currency']:NULL,
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

							':bmd_doctype' => is_numeric($Data['csc_doctype']) ? $Data['csc_doctype'] : 0,
							':bmd_docentry' => $resInsert,
							':bmd_createat' => $this->validateDate($Data['csc_createat']) ? $Data['csc_createat'] : NULL,
							':bmd_doctypeo' => is_numeric($Data['csc_basetype']) ? $Data['csc_basetype'] : 0, //ORIGEN
							':bmd_docentryo' => is_numeric($Data['csc_baseentry']) ? $Data['csc_baseentry'] : 0,  //ORIGEN
							':bmd_tdi' => is_numeric($Data['csc_doctype']) ? $Data['csc_doctype'] : 0, // DOCUMENTO INICIAL
							':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
							':bmd_docnum' => $DocNumVerificado,
							':bmd_doctotal' => is_numeric($Data['csc_doctotal']) ? $Data['csc_doctotal'] : 0,
							':bmd_cardcode' => isset($Data['csc_cardcode']) ? $Data['csc_cardcode'] : NULL,
							':bmd_cardtype' => 2,
							':bmd_currency' => isset($Data['csc_currency'])?$Data['csc_currency']:NULL,
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

					':bmd_doctype' => is_numeric($Data['csc_doctype']) ? $Data['csc_doctype'] : 0,
					':bmd_docentry' => $resInsert,
					':bmd_createat' => $this->validateDate($Data['csc_createat']) ? $Data['csc_createat'] : NULL,
					':bmd_doctypeo' => is_numeric($Data['csc_basetype']) ? $Data['csc_basetype'] : 0, //ORIGEN
					':bmd_docentryo' => is_numeric($Data['csc_baseentry']) ? $Data['csc_baseentry'] : 0,  //ORIGEN
					':bmd_tdi' => is_numeric($Data['csc_doctype']) ? $Data['csc_doctype'] : 0, // DOCUMENTO INICIAL
					':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
					':bmd_docnum' => $DocNumVerificado,
					':bmd_doctotal' => is_numeric($Data['csc_doctotal']) ? $Data['csc_doctotal'] : 0,
					':bmd_cardcode' => isset($Data['csc_cardcode']) ? $Data['csc_cardcode'] : NULL,
					':bmd_cardtype' => 2,
					':bmd_currency' => isset($Data['csc_currency'])?$Data['csc_currency']:NULL,
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

				$CANTUOMPURCHASE = $this->generic->getUomPurchase($detail['sc1_itemcode']);

				if ($CANTUOMPURCHASE == 0) {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' 		=> $detail['sc1_itemcode'],
						'mensaje'	=> 'No se encontro la equivalencia de la unidad de medida para el item: ' + $detail['sc1_itemcode']
					);

					$this->response($respuesta);

					return;
				}

				$sqlInsertDetail = "INSERT INTO csc1(sc1_docentry, sc1_linenum,sc1_itemcode, sc1_itemname, sc1_quantity, sc1_uom, sc1_whscode,
                                    sc1_price, sc1_vat, sc1_vatsum, sc1_discount, sc1_linetotal, sc1_costcode, sc1_ubusiness, sc1_project,
                                    sc1_acctcode, sc1_basetype, sc1_doctype, sc1_avprice, sc1_inventory, sc1_acciva, sc1_codimp, sc1_ubication,sc1_fechaentrega,ote_code)
									VALUES(:sc1_docentry, :sc1_linenum,:sc1_itemcode, :sc1_itemname, :sc1_quantity,:sc1_uom, :sc1_whscode,:sc1_price,
									:sc1_vat, :sc1_vatsum, :sc1_discount, :sc1_linetotal, :sc1_costcode, :sc1_ubusiness, :sc1_project,:sc1_acctcode, 
									:sc1_basetype, :sc1_doctype, :sc1_avprice, :sc1_inventory, :sc1_acciva,:sc1_codimp, :sc1_ubication,:sc1_fechaentrega,:ote_code)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':sc1_docentry' => $resInsert,
					':sc1_linenum' => isset($detail['sc1_linenum']) ? $detail['sc1_linenum'] : NULL,
					':sc1_itemcode' => isset($detail['sc1_itemcode']) ? $detail['sc1_itemcode'] : NULL,
					':sc1_itemname' => isset($detail['sc1_itemname']) ? $detail['sc1_itemname'] : NULL,
					':sc1_quantity' => is_numeric($detail['sc1_quantity']) ? $detail['sc1_quantity'] : 0,
					':sc1_uom' => isset($detail['sc1_uom']) ? $detail['sc1_uom'] : NULL,
					':sc1_whscode' => isset($detail['sc1_whscode']) ? $detail['sc1_whscode'] : NULL,
					':sc1_price' => is_numeric($detail['sc1_price']) ? $detail['sc1_price'] : 0,
					':sc1_vat' => is_numeric($detail['sc1_vat']) ? $detail['sc1_vat'] : 0,
					':sc1_vatsum' => is_numeric($detail['sc1_vatsum']) ? $detail['sc1_vatsum'] : 0,
					':sc1_discount' => is_numeric($detail['sc1_discount']) ? $detail['sc1_discount'] : 0,
					':sc1_linetotal' => is_numeric($detail['sc1_linetotal']) ? $detail['sc1_linetotal'] : 0,
					':sc1_costcode' => isset($detail['sc1_costcode']) ? $detail['sc1_costcode'] : NULL,
					':sc1_ubusiness' => isset($detail['sc1_ubusiness']) ? $detail['sc1_ubusiness'] : NULL,
					':sc1_project' => isset($detail['sc1_project']) ? $detail['sc1_project'] : NULL,
					':sc1_acctcode' => is_numeric($detail['sc1_acctcode']) ? $detail['sc1_acctcode'] : 0,
					':sc1_basetype' => is_numeric($detail['sc1_basetype']) ? $detail['sc1_basetype'] : 0,
					':sc1_doctype' => is_numeric($detail['sc1_doctype']) ? $detail['sc1_doctype'] : 0,
					':sc1_avprice' => is_numeric($detail['sc1_avprice']) ? $detail['sc1_avprice'] : 0,
					':sc1_inventory' => is_numeric($detail['sc1_inventory']) ? $detail['sc1_inventory'] : NULL,
					':sc1_acciva'  => is_numeric($detail['sc1_cuentaIva']) ? $detail['sc1_cuentaIva'] : 0,
					':sc1_codimp'  => isset($detail['sc1_codimp']) ? $detail['sc1_codimp'] : NULL,
					':sc1_ubication'  => isset($detail['sc1_ubication']) ? $detail['sc1_ubication'] : NULL,
					':sc1_fechaentrega'  => isset($detail['sc1_fechaentrega']) ? $detail['sc1_fechaentrega'] : NULL,
					':ote_code'  => isset($detail['ote_code']) ? $detail['ote_code'] : NULL
				));

				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle de la solicitud de compras se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertDetail,
						'mensaje'	=> 'No se pudo registrar la solicitud de compras',
						'proceso' => 'Insert detalle solicitud'
					);

					$this->response($respuesta);

					return;
				}
			}
			//FIN DE OPERACIONES VITALES

			// Si todo sale bien despues de insertar el detalle de la solicitud de compras
			// se confirma la trasaccion  para que los cambios apliquen permanentemente
			// en la base de datos y se confirma la operacion exitosa.
			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'Solicitud de compras registrada con exito'
			);
		} else {
			// Se devuelven los cambios realizados en la transaccion
			// si occurre un error  y se muestra devuelve el error.
			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data' => $resInsert,
				'mensaje'	=> 'No se pudo registrar la solicitud de compras',
				'proceso' => 'Insert --- 8'
			);
		}

		$this->response($respuesta);
	}

	//ACTUALIZAR solicitud de compras
	public function updatePurchaseRequest_post()
	{

		$Data = $this->post();

		if (
			!isset($Data['csc_docentry']) or !isset($Data['csc_docnum']) or
			!isset($Data['csc_docdate']) or !isset($Data['csc_duedate']) or
			!isset($Data['csc_duedev']) or !isset($Data['csc_pricelist']) or
			!isset($Data['csc_cardcode']) or !isset($Data['csc_cardname']) or
			!isset($Data['csc_currency']) or !isset($Data['csc_contacid']) or
			!isset($Data['csc_slpcode']) or !isset($Data['csc_empid']) or
			!isset($Data['csc_comment']) or !isset($Data['csc_doctotal']) or
			!isset($Data['csc_baseamnt']) or !isset($Data['csc_taxtotal']) or
			!isset($Data['csc_discprofit']) or !isset($Data['csc_discount']) or
			!isset($Data['csc_createat']) or !isset($Data['csc_baseentry']) or
			!isset($Data['csc_basetype']) or !isset($Data['csc_doctype']) or
			!isset($Data['csc_idadd']) or !isset($Data['csc_adress']) or
			!isset($Data['csc_paytype']) or !isset($Data['csc_attch']) or
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
				'mensaje' => 'No se encontro el detalle de la solicitud de compras'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlUpdate = "UPDATE dcsc	SET csc_docdate=:csc_docdate,csc_duedate=:csc_duedate, csc_duedev=:csc_duedev, csc_pricelist=:csc_pricelist, csc_cardcode=:csc_cardcode,
			  						csc_cardname=:csc_cardname, csc_currency=:csc_currency, csc_contacid=:csc_contacid, csc_slpcode=:csc_slpcode,
										csc_empid=:csc_empid, csc_comment=:csc_comment, csc_doctotal=:csc_doctotal, csc_baseamnt=:csc_baseamnt,
										csc_taxtotal=:csc_taxtotal, csc_discprofit=:csc_discprofit, csc_discount=:csc_discount, csc_createat=:csc_createat,
										csc_baseentry=:csc_baseentry, csc_basetype=:csc_basetype, csc_doctype=:csc_doctype, csc_idadd=:csc_idadd,
										csc_adress=:csc_adress, csc_paytype=:csc_paytype,business = :business,branch = :branch, csc_attch=:csc_attch WHERE csc_docentry=:csc_docentry";

		$this->pedeo->trans_begin();

		$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
			':csc_docnum' => is_numeric($Data['csc_docnum']) ? $Data['csc_docnum'] : 0,
			':csc_docdate' => $this->validateDate($Data['csc_docdate']) ? $Data['csc_docdate'] : NULL,
			':csc_duedate' => $this->validateDate($Data['csc_duedate']) ? $Data['csc_duedate'] : NULL,
			':csc_duedev' => $this->validateDate($Data['csc_duedev']) ? $Data['csc_duedev'] : NULL,
			':csc_pricelist' => is_numeric($Data['csc_pricelist']) ? $Data['csc_pricelist'] : 0,
			':csc_cardcode' => isset($Data['csc_pricelist']) ? $Data['csc_pricelist'] : NULL,
			':csc_cardname' => isset($Data['csc_cardname']) ? $Data['csc_cardname'] : NULL,
			':csc_currency' => isset($Data['csc_currency']) ? $Data['csc_currency'] : NULL,
			':csc_contacid' => isset($Data['csc_contacid']) ? $Data['csc_contacid'] : NULL,
			':csc_slpcode' => is_numeric($Data['csc_slpcode']) ? $Data['csc_slpcode'] : 0,
			':csc_empid' => is_numeric($Data['csc_empid']) ? $Data['csc_empid'] : 0,
			':csc_comment' => isset($Data['csc_comment']) ? $Data['csc_comment'] : NULL,
			':csc_doctotal' => is_numeric($Data['csc_doctotal']) ? $Data['csc_doctotal'] : 0,
			':csc_baseamnt' => is_numeric($Data['csc_baseamnt']) ? $Data['csc_baseamnt'] : 0,
			':csc_taxtotal' => is_numeric($Data['csc_taxtotal']) ? $Data['csc_taxtotal'] : 0,
			':csc_discprofit' => is_numeric($Data['csc_discprofit']) ? $Data['csc_discprofit'] : 0,
			':csc_discount' => is_numeric($Data['csc_discount']) ? $Data['csc_discount'] : 0,
			':csc_createat' => $this->validateDate($Data['csc_createat']) ? $Data['csc_createat'] : NULL,
			':csc_baseentry' => is_numeric($Data['csc_baseentry']) ? $Data['csc_baseentry'] : 0,
			':csc_basetype' => is_numeric($Data['csc_basetype']) ? $Data['csc_basetype'] : 0,
			':csc_doctype' => is_numeric($Data['csc_doctype']) ? $Data['csc_doctype'] : 0,
			':csc_idadd' => isset($Data['csc_idadd']) ? $Data['csc_idadd'] : NULL,
			':csc_adress' => isset($Data['csc_adress']) ? $Data['csc_adress'] : NULL,
			':csc_paytype' => is_numeric($Data['csc_paytype']) ? $Data['csc_paytype'] : 0,
			':business' => isset($Data['business']) ? $Data['business'] : NULL,
			':branch' => isset($Data['branch']) ? $Data['branch'] : NULL,
			':csc_attch' => $this->getUrl(count(trim(($Data['csc_attch']))) > 0 ? $Data['csc_attch'] : NULL, $resMainFolder[0]['main_folder']),
			':csc_docentry' => $Data['csc_docentry']
		));

		if (is_numeric($resUpdate) && $resUpdate == 1) {

			$this->pedeo->queryTable("DELETE FROM csc1 WHERE sc1_docentry=:sc1_docentry", array(':sc1_docentry' => $Data['csc_docentry']));

			foreach ($ContenidoDetalle as $key => $detail) {

				$sqlInsertDetail = "INSERT INTO csc1(sc1_docentry, sc1_itemcode, sc1_itemname, sc1_quantity, sc1_uom, sc1_whscode,
																			sc1_price, sc1_vat, sc1_vatsum, sc1_discount, sc1_linetotal, sc1_costcode, sc1_ubusiness, sc1_project,
																			sc1_acctcode, sc1_basetype, sc1_doctype, sc1_avprice, sc1_inventory, sc1_acciva, sc1_ubication)VALUES(:sc1_docentry, :sc1_itemcode, :sc1_itemname, :sc1_quantity,
																			:sc1_uom, :sc1_whscode,:sc1_price, :sc1_vat, :sc1_vatsum, :sc1_discount, :sc1_linetotal, :sc1_costcode, :sc1_ubusiness, :sc1_project,
																			:sc1_acctcode, :sc1_basetype, :sc1_doctype, :sc1_avprice, :sc1_inventory, :sc1_acciva, :sc1_ubication)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':sc1_docentry' => $Data['csc_docentry'],
					':sc1_itemcode' => isset($detail['sc1_itemcode']) ? $detail['sc1_itemcode'] : NULL,
					':sc1_itemname' => isset($detail['sc1_itemname']) ? $detail['sc1_itemname'] : NULL,
					':sc1_quantity' => is_numeric($detail['sc1_quantity']) ? $detail['sc1_quantity'] : 0,
					':sc1_uom' => isset($detail['sc1_uom']) ? $detail['sc1_uom'] : NULL,
					':sc1_whscode' => isset($detail['sc1_whscode']) ? $detail['sc1_whscode'] : NULL,
					':sc1_price' => is_numeric($detail['sc1_price']) ? $detail['sc1_price'] : 0,
					':sc1_vat' => is_numeric($detail['sc1_vat']) ? $detail['sc1_vat'] : 0,
					':sc1_vatsum' => is_numeric($detail['sc1_vatsum']) ? $detail['sc1_vatsum'] : 0,
					':sc1_discount' => is_numeric($detail['sc1_discount']) ? $detail['sc1_discount'] : 0,
					':sc1_linetotal' => is_numeric($detail['sc1_linetotal']) ? $detail['sc1_linetotal'] : 0,
					':sc1_costcode' => isset($detail['sc1_costcode']) ? $detail['sc1_costcode'] : NULL,
					':sc1_ubusiness' => isset($detail['sc1_ubusiness']) ? $detail['sc1_ubusiness'] : NULL,
					':sc1_project' => isset($detail['sc1_project']) ? $detail['sc1_project'] : NULL,
					':sc1_acctcode' => is_numeric($detail['sc1_acctcode']) ? $detail['sc1_acctcode'] : 0,
					':sc1_basetype' => is_numeric($detail['sc1_basetype']) ? $detail['sc1_basetype'] : 0,
					':sc1_doctype' => is_numeric($detail['sc1_doctype']) ? $detail['sc1_doctype'] : 0,
					':sc1_avprice' => is_numeric($detail['sc1_avprice']) ? $detail['sc1_avprice'] : 0,
					':sc1_inventory' => is_numeric($detail['sc1_inventory']) ? $detail['sc1_inventory'] : NULL,
					':sc1_acciva' => is_numeric($detail['sc1_cuentaIva']) ? $detail['sc1_cuentaIva'] : 0,
					':sc1_ubication' => isset($detail['sc1_ubication']) ? $detail['sc1_ubication'] : NULL
				));

				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle de la solicitud de compras se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsert,
						'mensaje'	=> 'No se pudo registrar la solicitud de compras'
					);

					$this->response($respuesta);

					return;
				}
			}


			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resUpdate,
				'mensaje' => 'Solicitud de compras actualizada con exito'
			);
		} else {

			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data'    => $resUpdate,
				'mensaje'	=> 'No se pudo actualizar la solicitud de compras'
			);
		}

		$this->response($respuesta);
	}


	//OBTENER solicitud de compras
	public function getPurchaseRequest_get()
	{
		
		$Data = $this->get();
		$DECI_MALES =  $this->generic->getDecimals();

		$sqlSelect = self::getColumn('dcsc', 'csc', '', '', $DECI_MALES,$Data['business'], $Data['branch']);


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


	//OBTENER solicitud de compras POR ID
	public function getPurchaseRequestById_get()
	{

		$Data = $this->get();

		if (!isset($Data['csc_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM dcsc WHERE csc_docentry =:csc_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":csc_docentry" => $Data['csc_docentry']));

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


	//OBTENER solicitud de compras DETALLE POR ID
	public function getPurchaseRequestDetail_get()
	{

		$Data = $this->get();

		if (!isset($Data['sc1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT csc1.*, dmar.dma_series_code
											 FROM csc1
											 INNER JOIN dmar	ON csc1.sc1_itemcode = dmar.dma_item_code
											 WHERE sc1_docentry =:sc1_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":sc1_docentry" => $Data['sc1_docentry']));

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

	public function getPurchaseRequestDetailCopy_get()
	{

		$Data = $this->get();

		if (!isset($Data['sc1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$copy = $this->documentcopy->Copy($Data['sc1_docentry'],'dcsc','csc1','csc','sc1');

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

	//OBTENER solicitud DE VENTA POR ID SOCIO DE NEGOCIO
	public function getPurchaseRequestBySN_get()
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

		// $sqlSelect = "SELECT * FROM dcsc WHERE csc_slpcode =:dms_card_code";

		$sqlSelect = "SELECT
												t0.*
											FROM dcsc t0
											left join estado_doc t1 on t0.csc_docentry = t1.entry and t0.csc_doctype = t1.tipo
											left join responsestatus t2 on t1.entry = t2.id and t1.tipo = t2.tipo
											where t2.estado = 'Abierto' and t0.csc_slpcode =:dms_card_code";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":dms_card_code" => $Data['dms_card_code']));

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


	private function setAprobacion($Encabezado, $Detalle, $Carpeta, $prefijoe, $prefijod, $Cantidad, $CantidadAP, $Model)
	{

		$sqlInsert = "INSERT INTO dpap(pap_series, pap_docnum, pap_docdate, pap_duedate, pap_duedev, pap_pricelist, pap_cardcode,
									pap_cardname, pap_currency, pap_contacid, pap_slpcode, pap_empid, pap_comment, pap_doctotal, pap_baseamnt, pap_taxtotal,
									pap_discprofit, pap_discount, pap_createat, pap_baseentry, pap_basetype, pap_doctype, pap_idadd, pap_adress, pap_paytype,
									pap_attch,pap_createby,pap_origen,pap_qtyrq,pap_qtyap,pap_model)VALUES(:pap_series, :pap_docnum, :pap_docdate, :pap_duedate, :pap_duedev, :pap_pricelist, :pap_cardcode, :pap_cardname,
									:pap_currency, :pap_contacid, :pap_slpcode, :pap_empid, :pap_comment, :pap_doctotal, :pap_baseamnt, :pap_taxtotal, :pap_discprofit, :pap_discount,
									:pap_createat, :pap_baseentry, :pap_basetype, :pap_doctype, :pap_idadd, :pap_adress, :pap_paytype, :pap_attch,:pap_createby,:pap_origen,:pap_qtyrq,:pap_qtyap,:pap_model)";

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
			':pap_attch' => $this->getUrl(count(trim(($Encabezado[$prefijoe . '_attch']))) > 0 ? $Encabezado[$prefijoe . '_attch'] : NULL, $Carpeta),
			':pap_origen' => is_numeric($Encabezado[$prefijoe . '_doctype']) ? $Encabezado[$prefijoe . '_doctype'] : 0,
			':pap_qtyrq' => $Cantidad,
			':pap_qtyap' => $CantidadAP,
			':pap_model' => $Model

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