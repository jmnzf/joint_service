<?php
// ORDEN DE COMPRA
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PurchOrder extends REST_Controller {

	private $pdo;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
    $this->load->library('pedeo', [$this->pdo]);

	}

  //CREAR NUEVA ORDEN DE COMPRA
	public function createPurchOrder_post(){

      $Data = $this->post();
			$DocNumVerificado = 0;

      if(!isset($Data['detail'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $ContenidoDetalle = json_decode($Data['detail'], true);


      if(!is_array($ContenidoDetalle)){
          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'No se encontro el detalle de la orden de compra'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }

			// SE VALIDA QUE EL DOCUMENTO TENGA CONTENIDO
			if(!intval(count($ContenidoDetalle)) > 0 ){
					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'Documento sin detalle'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
			}
			//
				//BUSCANDO LA NUMERACION DEL DOCUMENTO
			  $sqlNumeracion = " SELECT pgs_nextnum,pgs_last_num FROM  pgdn WHERE pgs_id = :pgs_id";

				$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['cpo_series']));

				if(isset($resNumeracion[0])){

						$numeroActual = $resNumeracion[0]['pgs_nextnum'];
						$numeroFinal  = $resNumeracion[0]['pgs_last_num'];
						$numeroSiguiente = ($numeroActual + 1);

						if( $numeroSiguiente <= $numeroFinal ){

								$DocNumVerificado = $numeroSiguiente;

						}	else {

								$respuesta = array(
									'error' => true,
									'data'  => array(),
									'mensaje' =>'La serie de la numeración esta llena'
								);

								$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

								return;
						}

				}else{

						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encontro la serie de numeración para el documento'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}

				//Obtener Carpeta Principal del Proyecto
				$sqlMainFolder = " SELECT * FROM params";
				$resMainFolder = $this->pedeo->queryTable($sqlMainFolder, array());

				if(!isset($resMainFolder[0])){
						$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'No se encontro la caperta principal del proyecto'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}

				// FIN PROCEDIMIENTO PARA OBTENER CARPETA Principal


				// PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO
				// SE BUSCA LA MONEDA LOCAL PARAMETRIZADA
				$sqlMonedaLoc = "SELECT pgm_symbol FROM pgec WHERE pgm_principal = :pgm_principal";
				$resMonedaLoc = $this->pedeo->queryTable($sqlMonedaLoc, array(':pgm_principal' => 1));

				if(isset($resMonedaLoc[0])){

				}else{
						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encontro la moneda local.'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}

				$MONEDALOCAL = trim($resMonedaLoc[0]['pgm_symbol']);

				// SE BUSCA LA MONEDA DE SISTEMA PARAMETRIZADA
				$sqlMonedaSys = "SELECT pgm_symbol FROM pgec WHERE pgm_system = :pgm_system";
				$resMonedaSys = $this->pedeo->queryTable($sqlMonedaSys, array(':pgm_system' => 1));

				if(isset($resMonedaSys[0])){

				}else{

						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encontro la moneda de sistema.'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}


				$MONEDASYS = trim($resMonedaSys[0]['pgm_symbol']);

				//SE BUSCA LA TASA DE CAMBIO CON RESPECTO A LA MONEDA QUE TRAE EL DOCUMENTO A CREAR CON LA MONEDA LOCAL
				// Y EN LA MISMA FECHA QUE TRAE EL DOCUMENTO
				$sqlBusTasa = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
				$resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $Data['cpo_currency'], ':tsa_date' => $Data['cpo_docdate']));

				if(isset($resBusTasa[0])){

				}else{

						if(trim($Data['cpo_currency']) != $MONEDALOCAL ){

							$respuesta = array(
								'error' => true,
								'data'  => array(),
								'mensaje' =>'No se encrontro la tasa de cambio para la moneda: '.$Data['cpo_currency'].' en la actual fecha del documento: '.$Data['cpo_docdate'].' y la moneda local: '.$resMonedaLoc[0]['pgm_symbol']
							);

							$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

							return;
						}
				}


				$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
				$resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $Data['cpo_docdate']));

				if(isset($resBusTasa2[0])){

				}else{
						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :'.$Data['cpo_docdate']
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

									':bed_docentry' => $Data['cpo_baseentry'],
									':bed_doctype'  => $Data['cpo_basetype'],
									':bed_status'   => 4 // 4 APROBADO SEGUN MODELO DE APROBACION
				));



				if(!isset($resVerificarAprobacion[0])){

							$sqlDocModelo = "SELECT mau_docentry as modelo, mau_doctype as doctype, mau_quantity as cantidad,
																au1_doctotal as doctotal,au1_doctotal2 as doctotal2, au1_c1 as condicion
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
																AND aus_status = :aus_status";

							$resDocModelo = $this->pedeo->queryTable($sqlDocModelo, array(

										':mau_doctype'   => $Data['cpo_doctype'],
										':pgu_code_user' => $Data['cpo_createby'],
										':mau_status' 	 => 1,
										':aus_status' 	 => 1

							));

							if(isset($resDocModelo[0])){

											foreach ($resDocModelo as $key => $value) {

													//VERIFICAR MODELO DE APROBACION
													$condicion = $value['condicion'];
													$valorDocTotal1 = $value['doctotal'];
													$valorDocTotal2 = $value['doctotal2'];
													$TotalDocumento = $Data['cpo_doctotal'];
													$doctype =  $value['doctype'];
													$modelo = $value['modelo'];

													if(trim($Data['cpo_currency']) != $MONEDASYS){

														  if(trim($Data['cpo_currency']) != $MONEDALOCAL){

																$TotalDocumento = round(($TotalDocumento * $TasaDocLoc), 2);
																$TotalDocumento = round(($TotalDocumento / $TasaLocSys), 2);

															}else{

																$TotalDocumento = round(($TotalDocumento / $TasaLocSys), 2);

															}

													}

													if( $condicion == ">" ){

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

																if( isset($ressq[0]) ){
																	$this->setAprobacion($Data,$ContenidoDetalle,$resMainFolder[0]['main_folder'],'cpo','po1',$ressq[0]['mau_quantity'],count(explode(',', $ressq[0]['mau_approvers'])),$ressq[0]['mau_docentry']);
																}


													}else if( $condicion == "BETWEEN" ){

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

																if( isset($ressq[0]) ){
																	$this->setAprobacion($Data,$ContenidoDetalle,$resMainFolder[0]['main_folder'],'cpo','po1',$ressq[0]['mau_quantity'],count(explode(',', $ressq[0]['mau_approvers'])),$ressq[0]['mau_docentry']);
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
                      cpo_attch,cpo_createby)VALUES(:cpo_series, :cpo_docnum, :cpo_docdate, :cpo_duedate, :cpo_duedev, :cpo_pricelist, :cpo_cardcode, :cpo_cardname,
                      :cpo_currency, :cpo_contacid, :cpo_slpcode, :cpo_empid, :cpo_comment, :cpo_doctotal, :cpo_baseamnt, :cpo_taxtotal, :cpo_discprofit, :cpo_discount,
                      :cpo_createat, :cpo_baseentry, :cpo_basetype, :cpo_doctype, :cpo_idadd, :cpo_adress, :cpo_paytype, :cpo_attch,:cpo_createby)";


				// Se Inicia la transaccion,
				// Todas las consultas de modificacion siguientes
				// aplicaran solo despues que se confirme la transaccion,
				// de lo contrario no se aplicaran los cambios y se devolvera
				// la base de datos a su estado original.

			  $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':cpo_docnum' => $DocNumVerificado,
              ':cpo_series' => is_numeric($Data['cpo_series'])?$Data['cpo_series']:0,
              ':cpo_docdate' => $this->validateDate($Data['cpo_docdate'])?$Data['cpo_docdate']:NULL,
              ':cpo_duedate' => $this->validateDate($Data['cpo_duedate'])?$Data['cpo_duedate']:NULL,
              ':cpo_duedev' => $this->validateDate($Data['cpo_duedev'])?$Data['cpo_duedev']:NULL,
              ':cpo_pricelist' => is_numeric($Data['cpo_pricelist'])?$Data['cpo_pricelist']:0,
              ':cpo_cardcode' => isset($Data['cpo_cardcode'])?$Data['cpo_cardcode']:NULL,
              ':cpo_cardname' => isset($Data['cpo_cardname'])?$Data['cpo_cardname']:NULL,
              ':cpo_currency' => isset($Data['cpo_currency'])?$Data['cpo_currency']:NULL,
              ':cpo_contacid' => isset($Data['cpo_contacid'])?$Data['cpo_contacid']:NULL,
              ':cpo_slpcode' => is_numeric($Data['cpo_slpcode'])?$Data['cpo_slpcode']:0,
              ':cpo_empid' => is_numeric($Data['cpo_empid'])?$Data['cpo_empid']:0,
              ':cpo_comment' => isset($Data['cpo_comment'])?$Data['cpo_comment']:NULL,
              ':cpo_doctotal' => is_numeric($Data['cpo_doctotal'])?$Data['cpo_doctotal']:0,
              ':cpo_baseamnt' => is_numeric($Data['cpo_baseamnt'])?$Data['cpo_baseamnt']:0,
              ':cpo_taxtotal' => is_numeric($Data['cpo_taxtotal'])?$Data['cpo_taxtotal']:0,
              ':cpo_discprofit' => is_numeric($Data['cpo_discprofit'])?$Data['cpo_discprofit']:0,
              ':cpo_discount' => is_numeric($Data['cpo_discount'])?$Data['cpo_discount']:0,
              ':cpo_createat' => $this->validateDate($Data['cpo_createat'])?$Data['cpo_createat']:NULL,
              ':cpo_baseentry' => is_numeric($Data['cpo_baseentry'])?$Data['cpo_baseentry']:0,
              ':cpo_basetype' => is_numeric($Data['cpo_basetype'])?$Data['cpo_basetype']:0,
              ':cpo_doctype' => is_numeric($Data['cpo_doctype'])?$Data['cpo_doctype']:0,
              ':cpo_idadd' => isset($Data['cpo_idadd'])?$Data['cpo_idadd']:NULL,
              ':cpo_adress' => isset($Data['cpo_adress'])?$Data['cpo_adress']:NULL,
              ':cpo_paytype' => is_numeric($Data['cpo_paytype'])?$Data['cpo_paytype']:0,
							':cpo_createby' => isset($Data['cpo_createby'])?$Data['cpo_createby']:NULL,
              ':cpo_attch' => $this->getUrl(count(trim(($Data['cpo_attch']))) > 0 ? $Data['cpo_attch']:NULL, $resMainFolder[0]['main_folder'])

						));

        if(is_numeric($resInsert) && $resInsert > 0){

					// Se actualiza la serie de la numeracion del documento

					$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
					$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
							':pgs_nextnum' => $DocNumVerificado,
							':pgs_id'      => $Data['cpo_series']
					));


					if(is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1){

					}else{
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


					if(is_numeric($resInsertEstado) && $resInsertEstado > 0){

					}else{

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
					if( $Data['cpo_basetype'] == 21){

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


						if(is_numeric($resInsertEstado) && $resInsertEstado > 0){

						}else{

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
					if( isset($Data['cpo_baseentry']) && is_numeric($Data['cpo_baseentry']) && isset($Data['cpo_basetype']) && is_numeric($Data['cpo_basetype']) ){

						$sqlDocInicio = "SELECT bmd_tdi, bmd_ndi FROM tbmd WHERE  bmd_doctype = :bmd_doctype AND bmd_docentry = :bmd_docentry";
						$resDocInicio = $this->pedeo->queryTable($sqlDocInicio, array(
							 ':bmd_doctype' => $Data['cpo_basetype'],
							 ':bmd_docentry' => $Data['cpo_baseentry']
						));


						if ( isset(	$resDocInicio[0] ) ){

							$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
															bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype)
															VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
															:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype)";

							$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

								':bmd_doctype' => is_numeric($Data['cpo_doctype'])?$Data['cpo_doctype']:0,
								':bmd_docentry' => $resInsert,
								':bmd_createat' => $this->validateDate($Data['cpo_createat'])?$Data['cpo_createat']:NULL,
								':bmd_doctypeo' => is_numeric($Data['cpo_basetype'])?$Data['cpo_basetype']:0, //ORIGEN
								':bmd_docentryo' => is_numeric($Data['cpo_baseentry'])?$Data['cpo_baseentry']:0,  //ORIGEN
								':bmd_tdi' => $resDocInicio[0]['bmd_tdi'], // DOCUMENTO INICIAL
								':bmd_ndi' => $resDocInicio[0]['bmd_ndi'], // DOCUMENTO INICIAL
								':bmd_docnum' => $DocNumVerificado,
								':bmd_doctotal' => is_numeric($Data['cpo_doctotal'])?$Data['cpo_doctotal']:0,
								':bmd_cardcode' => isset($Data['cpo_cardcode'])?$Data['cpo_cardcode']:NULL,
								':bmd_cardtype' => 2
							));

							if( is_numeric($resInsertMD) && $resInsertMD > 0 ){

							}else{

								$this->pedeo->trans_rollback();

								 $respuesta = array(
									 'error'   => true,
									 'data' => $resInsertEstado,
									 'mensaje'	=> 'No se pudo registrar el movimiento del documento'
								 );


								 $this->response($respuesta);

								 return;
							}

						}else{

							$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
															bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype)
															VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
															:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype)";

							$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

								':bmd_doctype' => is_numeric($Data['cpo_doctype'])?$Data['cpo_doctype']:0,
								':bmd_docentry' => $resInsert,
								':bmd_createat' => $this->validateDate($Data['cpo_createat'])?$Data['cpo_createat']:NULL,
								':bmd_doctypeo' => is_numeric($Data['cpo_basetype'])?$Data['cpo_basetype']:0, //ORIGEN
								':bmd_docentryo' => is_numeric($Data['cpo_baseentry'])?$Data['cpo_baseentry']:0,  //ORIGEN
								':bmd_tdi' => is_numeric($Data['cpo_doctype'])?$Data['cpo_doctype']:0, // DOCUMENTO INICIAL
								':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
								':bmd_docnum' => $DocNumVerificado,
								':bmd_doctotal' => is_numeric($Data['cpo_doctotal'])?$Data['cpo_doctotal']:0,
								':bmd_cardcode' => isset($Data['cpo_cardcode'])?$Data['cpo_cardcode']:NULL,
								':bmd_cardtype' => 2
							));

							if( is_numeric($resInsertMD) && $resInsertMD > 0 ){

							}else{

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

					}else{

						$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
														bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype)
														VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
														:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype)";

						$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

							':bmd_doctype' => is_numeric($Data['cpo_doctype'])?$Data['cpo_doctype']:0,
							':bmd_docentry' => $resInsert,
							':bmd_createat' => $this->validateDate($Data['cpo_createat'])?$Data['cpo_createat']:NULL,
							':bmd_doctypeo' => is_numeric($Data['cpo_basetype'])?$Data['cpo_basetype']:0, //ORIGEN
							':bmd_docentryo' => is_numeric($Data['cpo_baseentry'])?$Data['cpo_baseentry']:0,  //ORIGEN
							':bmd_tdi' => is_numeric($Data['cpo_doctype'])?$Data['cpo_doctype']:0, // DOCUMENTO INICIAL
							':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
							':bmd_docnum' => $DocNumVerificado,
							':bmd_doctotal' => is_numeric($Data['cpo_doctotal'])?$Data['cpo_doctotal']:0,
							':bmd_cardcode' => isset($Data['cpo_cardcode'])?$Data['cpo_cardcode']:NULL,
							':bmd_cardtype' => 2
						));

						if( is_numeric($resInsertMD) && $resInsertMD > 0 ){

						}else{

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

                $sqlInsertDetail = "INSERT INTO cpo1(po1_docentry, po1_itemcode, po1_itemname, po1_quantity, po1_uom, po1_whscode,
                                    po1_price, po1_vat, po1_vatsum, po1_discount, po1_linetotal, po1_costcode, po1_ubusiness, po1_project,
                                    po1_acctcode, po1_basetype, po1_doctype, po1_avprice, po1_inventory, po1_linenum, po1_acciva)VALUES(:po1_docentry, :po1_itemcode, :po1_itemname, :po1_quantity,
                                    :po1_uom, :po1_whscode,:po1_price, :po1_vat, :po1_vatsum, :po1_discount, :po1_linetotal, :po1_costcode, :po1_ubusiness, :po1_project,
                                    :po1_acctcode, :po1_basetype, :po1_doctype, :po1_avprice, :po1_inventory,:po1_linenum,:po1_acciva)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':po1_docentry' => $resInsert,
                        ':po1_itemcode' => isset($detail['po1_itemcode'])?$detail['po1_itemcode']:NULL,
                        ':po1_itemname' => isset($detail['po1_itemname'])?$detail['po1_itemname']:NULL,
                        ':po1_quantity' => is_numeric($detail['po1_quantity'])?$detail['po1_quantity']:0,
                        ':po1_uom' => isset($detail['po1_uom'])?$detail['po1_uom']:NULL,
                        ':po1_whscode' => isset($detail['po1_whscode'])?$detail['po1_whscode']:NULL,
                        ':po1_price' => is_numeric($detail['po1_price'])?$detail['po1_price']:0,
                        ':po1_vat' => is_numeric($detail['po1_vat'])?$detail['po1_vat']:0,
                        ':po1_vatsum' => is_numeric($detail['po1_vatsum'])?$detail['po1_vatsum']:0,
                        ':po1_discount' => is_numeric($detail['po1_discount'])?$detail['po1_discount']:0,
                        ':po1_linetotal' => is_numeric($detail['po1_linetotal'])?$detail['po1_linetotal']:0,
                        ':po1_costcode' => isset($detail['po1_costcode'])?$detail['po1_costcode']:NULL,
                        ':po1_ubusiness' => isset($detail['po1_ubusiness'])?$detail['po1_ubusiness']:NULL,
                        ':po1_project' => isset($detail['po1_project'])?$detail['po1_project']:NULL,
                        ':po1_acctcode' => is_numeric($detail['po1_acctcode'])?$detail['po1_acctcode']:0,
                        ':po1_basetype' => is_numeric($detail['po1_basetype'])?$detail['po1_basetype']:0,
                        ':po1_doctype' => is_numeric($detail['po1_doctype'])?$detail['po1_doctype']:0,
                        ':po1_avprice' => is_numeric($detail['po1_avprice'])?$detail['po1_avprice']:0,
                        ':po1_inventory' => is_numeric($detail['po1_inventory'])?$detail['po1_inventory']:NULL,
												':po1_linenum' => is_numeric($detail['po1_linenum'])?$detail['po1_linenum']:NULL,
												':po1_acciva' => is_numeric($detail['po1_acciva'])?$detail['po1_acciva']:NULL
                ));

								if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
										// Se verifica que el detalle no de error insertando //
								}else{

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


						$sqlEstado1 = "SELECT
																					 count(t1.sc1_itemcode) item,
																					 sum(t1.sc1_quantity) cantidad
																		from dcsc t0
																		inner join csc1 t1 on t0.csc_docentry = t1.sc1_docentry
																		where t0.csc_docentry = :csc_docentry and t0.csc_doctype = :csc_doctype";


						$resEstado1 = $this->pedeo->queryTable($sqlEstado1, array(
							':csc_docentry' => $Data['cpo_baseentry'],
							':csc_doctype' => $Data['cpo_basetype']
							// ':vc1_itemcode' => $detail['ov1_itemcode']
						));

						$sqlEstado2 = "SELECT
																					 coalesce(count(distinct t3.po1_itemcode),0) item,
																					 coalesce(sum(t3.po1_quantity),0) cantidad
																		from dcsc t0
																		inner join csc1 t1 on t0.csc_docentry = t1.sc1_docentry
																		left join dcpo t2 on t0.csc_docentry = t2.cpo_baseentry and t0.csc_doctype = t2.cpo_basetype
																		left join cpo1 t3 on t2.cpo_docentry = t3.po1_docentry and t1.sc1_itemcode = t3.po1_itemcode
																		where t0.csc_docentry = :csc_docentry and t0.csc_doctype = :csc_doctype";


						$resEstado2 = $this->pedeo->queryTable($sqlEstado2, array(
							':csc_docentry' => $Data['cpo_baseentry'],
							':csc_doctype' => $Data['cpo_basetype']

						));

						$item_cot = $resEstado1[0]['item'];
						$cantidad_cot = $resEstado1[0]['cantidad'];
						$item_ord = $resEstado2[0]['item'];
						$cantidad_ord = $resEstado2[0]['cantidad'];


						if($item_sol == $item_ord  &&  $cantidad_sol == $cantidad_ord){

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


									if(is_numeric($resInsertEstado) && $resInsertEstado > 0){

									}else{

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

						$resEstado2 = $this->pedeo->queryTable($sqlEstado2,array(
							':coc_docentry' => $Data['cpo_baseentry'],
							':coc_doctype' => $Data['cpo_basetype']
						));

						$item_sol = $resEstado1[0]['item'];
						$cantidad_sol = $resEstado1[0]['cantidad'];
							$item_ord = $resEstado2[0]['item'];
							$cantidad_ord = $resEstado2[0]['cantidad'];




						// print_r($item_sol);
						// print_r($item_ord);
						// print_r($cantidad_sol);
						// print_r($cantidad_ord);exit();die();

						if($item_sol == $item_ord  &&  $cantidad_sol == $cantidad_ord){

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
									if(is_numeric($resInsertEstado) && $resInsertEstado > 0){

									}else{

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
            'mensaje' =>'Orden de compra registrada con exito'
          );


        }else{
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
  public function updatePurchOrder_post(){

      $Data = $this->post();

			if(!isset($Data['detail'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

			$ContenidoDetalle = json_decode($Data['detail'], true);


      if(!is_array($ContenidoDetalle)){
          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'No se encontro el detalle de la orden de compra'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }


			//Obtener Carpeta Principal del Proyecto
			$sqlMainFolder = " SELECT * FROM params";
			$resMainFolder = $this->pedeo->queryTable($sqlMainFolder, array());

			if(!isset($resMainFolder[0])){
					$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' =>'No se encontro la caperta principal del proyecto'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
			}

      $sqlUpdate = "UPDATE dcpo	SET cpo_docdate=:cpo_docdate,cpo_duedate=:cpo_duedate, cpo_duedev=:cpo_duedev, cpo_pricelist=:cpo_pricelist, cpo_cardcode=:cpo_cardcode,
			  						cpo_cardname=:cpo_cardname, cpo_currency=:cpo_currency, cpo_contacid=:cpo_contacid, cpo_slpcode=:cpo_slpcode,
										cpo_empid=:cpo_empid, cpo_comment=:cpo_comment, cpo_doctotal=:cpo_doctotal, cpo_baseamnt=:cpo_baseamnt,
										cpo_taxtotal=:cpo_taxtotal, cpo_discprofit=:cpo_discprofit, cpo_discount=:cpo_discount, cpo_createat=:cpo_createat,
										cpo_baseentry=:cpo_baseentry, cpo_basetype=:cpo_basetype, cpo_doctype=:cpo_doctype, cpo_idadd=:cpo_idadd,
										cpo_adress=:cpo_adress, cpo_paytype=:cpo_paytype, cpo_attch=:cpo_attch WHERE cpo_docentry=:cpo_docentry";

      $this->pedeo->trans_begin();

      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
							':cpo_docdate' => $this->validateDate($Data['cpo_docdate'])?$Data['cpo_docdate']:NULL,
							':cpo_duedate' => $this->validateDate($Data['cpo_duedate'])?$Data['cpo_duedate']:NULL,
							':cpo_duedev' => $this->validateDate($Data['cpo_duedev'])?$Data['cpo_duedev']:NULL,
							':cpo_pricelist' => is_numeric($Data['cpo_pricelist'])?$Data['cpo_pricelist']:0,
							':cpo_cardcode' => isset($Data['cpo_cardcode'])?$Data['cpo_cardcode']:NULL,
							':cpo_cardname' => isset($Data['cpo_cardname'])?$Data['cpo_cardname']:NULL,
							':cpo_currency' => isset($Data['cpo_currency'])?$Data['cpo_currency']:NULL,
							':cpo_contacid' => isset($Data['cpo_contacid'])?$Data['cpo_contacid']:NULL,
							':cpo_slpcode' => is_numeric($Data['cpo_slpcode'])?$Data['cpo_slpcode']:0,
							':cpo_empid' => is_numeric($Data['cpo_empid'])?$Data['cpo_empid']:0,
							':cpo_comment' => isset($Data['cpo_comment'])?$Data['cpo_comment']:NULL,
							':cpo_doctotal' => is_numeric($Data['cpo_doctotal'])?$Data['cpo_doctotal']:0,
							':cpo_baseamnt' => is_numeric($Data['cpo_baseamnt'])?$Data['cpo_baseamnt']:0,
							':cpo_taxtotal' => is_numeric($Data['cpo_taxtotal'])?$Data['cpo_taxtotal']:0,
							':cpo_discprofit' => is_numeric($Data['cpo_discprofit'])?$Data['cpo_discprofit']:0,
							':cpo_discount' => is_numeric($Data['cpo_discount'])?$Data['cpo_discount']:0,
							':cpo_createat' => $this->validateDate($Data['cpo_createat'])?$Data['cpo_createat']:NULL,
							':cpo_baseentry' => is_numeric($Data['cpo_baseentry'])?$Data['cpo_baseentry']:0,
							':cpo_basetype' => is_numeric($Data['cpo_basetype'])?$Data['cpo_basetype']:0,
							':cpo_doctype' => is_numeric($Data['cpo_doctype'])?$Data['cpo_doctype']:0,
							':cpo_idadd' => isset($Data['cpo_idadd'])?$Data['cpo_idadd']:NULL,
							':cpo_adress' => isset($Data['cpo_adress'])?$Data['cpo_adress']:NULL,
							':cpo_paytype' => is_numeric($Data['cpo_paytype'])?$Data['cpo_paytype']:0,
							':cpo_attch' => $this->getUrl(count(trim(($Data['cpo_attch']))) > 0 ? $Data['cpo_attch']:NULL, $resMainFolder[0]['main_folder']),
							':cpo_docentry' => $Data['cpo_docentry']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

						$this->pedeo->queryTable("DELETE FROM cpo1 WHERE po1_docentry=:po1_docentry", array(':po1_docentry' => $Data['cpo_docentry']));

						foreach ($ContenidoDetalle as $key => $detail) {

									$sqlInsertDetail = "INSERT INTO cpo1(po1_docentry, po1_itemcode, po1_itemname, po1_quantity, po1_uom, po1_whscode,
																			po1_price, po1_vat, po1_vatsum, po1_discount, po1_linetotal, po1_costcode, po1_ubusiness, po1_project,
																			po1_acctcode, po1_basetype, po1_doctype, po1_avprice, po1_inventory, po1_acciva, po1_linenum)VALUES(:po1_docentry, :po1_itemcode, :po1_itemname, :po1_quantity,
																			:po1_uom, :po1_whscode,:po1_price, :po1_vat, :po1_vatsum, :po1_discount, :po1_linetotal, :po1_costcode, :po1_ubusiness, :po1_project,
																			:po1_acctcode, :po1_basetype, :po1_doctype, :po1_avprice, :po1_inventory, :po1_acciva,:po1_linenum)";

									$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
											':po1_docentry' => $Data['cpo_docentry'],
											':po1_itemcode' => isset($detail['po1_itemcode'])?$detail['po1_itemcode']:NULL,
											':po1_itemname' => isset($detail['po1_itemname'])?$detail['po1_itemname']:NULL,
											':po1_quantity' => is_numeric($detail['po1_quantity'])?$detail['po1_quantity']:0,
											':po1_uom' => isset($detail['po1_uom'])?$detail['po1_uom']:NULL,
											':po1_whscode' => isset($detail['po1_whscode'])?$detail['po1_whscode']:NULL,
											':po1_price' => is_numeric($detail['po1_price'])?$detail['po1_price']:0,
											':po1_vat' => is_numeric($detail['po1_vat'])?$detail['po1_vat']:0,
											':po1_vatsum' => is_numeric($detail['po1_vatsum'])?$detail['po1_vatsum']:0,
											':po1_discount' => is_numeric($detail['po1_discount'])?$detail['po1_discount']:0,
											':po1_linetotal' => is_numeric($detail['po1_linetotal'])?$detail['po1_linetotal']:0,
											':po1_costcode' => isset($detail['po1_costcode'])?$detail['po1_costcode']:NULL,
											':po1_ubusiness' => isset($detail['po1_ubusiness'])?$detail['po1_ubusiness']:NULL,
											':po1_project' => isset($detail['po1_project'])?$detail['po1_project']:NULL,
											':po1_acctcode' => is_numeric($detail['po1_acctcode'])?$detail['po1_acctcode']:0,
											':po1_basetype' => is_numeric($detail['po1_basetype'])?$detail['po1_basetype']:0,
											':po1_doctype' => is_numeric($detail['po1_doctype'])?$detail['po1_doctype']:0,
											':po1_avprice' => is_numeric($detail['po1_avprice'])?$detail['po1_avprice']:0,
											':po1_inventory' => is_numeric($detail['po1_inventory'])?$detail['po1_inventory']:NULL,
											':po1_acciva' => is_numeric($detail['po1_acciva'])?$detail['po1_acciva']:NULL,
											':po1_linenum' => is_numeric($detail['po1_linenum'])?$detail['po1_linenum']:NULL
									));

									if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
											// Se verifica que el detalle no de error insertando //
									}else{

											// si falla algun insert del detalle de la cotizacion se devuelven los cambios realizados por la transaccion,
											// se retorna el error y se detiene la ejecucion del codigo restante.
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data' => $resInsert,
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
              'mensaje' =>'Orden de compra actualizada con exito'
            );


      }else{

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
  public function getPurchOrder_get(){

        $sqlSelect = self::getColumn('dcpo','cpo');


        $resSelect = $this->pedeo->queryTable($sqlSelect, array());

        if(isset($resSelect[0])){

          $respuesta = array(
            'error' => false,
            'data'  => $resSelect,
            'mensaje' => '');

        }else{

            $respuesta = array(
              'error'   => true,
              'data' => array(),
              'mensaje'	=> 'busqueda sin resultados'
            );

        }

         $this->response($respuesta);
  }


	//OBTENER orden de compra POR ID
	public function getPurchOrderById_get(){

				$Data = $this->get();

				if(!isset($Data['cpo_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM dcpo WHERE cpo_docentry =:cpo_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":cpo_docentry" => $Data['cpo_docentry']));

				if(isset($resSelect[0])){

					$respuesta = array(
						'error' => false,
						'data'  => $resSelect,
						'mensaje' => '');

				}else{

						$respuesta = array(
							'error'   => true,
							'data' => array(),
							'mensaje'	=> 'busqueda sin resultados'
						);

				}

				 $this->response($respuesta);
	}


	//OBTENER orden de compra DETALLE POR ID
	public function getPurchOrderDetail_get(){

				$Data = $this->get();

				if(!isset($Data['po1_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM cpo1 WHERE po1_docentry =:po1_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":po1_docentry" => $Data['po1_docentry']));

				if(isset($resSelect[0])){

					$respuesta = array(
						'error' => false,
						'data'  => $resSelect,
						'mensaje' => '');

				}else{

						$respuesta = array(
							'error'   => true,
							'data' => array(),
							'mensaje'	=> 'busqueda sin resultados'
						);

				}

				 $this->response($respuesta);
	}



	//OBTENER COTIZACIONES POR SOCIO DE NEGOCIO
	public function getPurchOrderBySN_get(){

				$Data = $this->get();

				if(!isset($Data['dms_card_code'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = "SELECT
												t0.*
											FROM dcpo t0
											left join estado_doc t1 on t0.cpo_docentry = t1.entry and t0.cpo_doctype = t1.tipo
											left join responsestatus t2 on t1.entry = t2.id and t1.tipo = t2.tipo
											where t2.estado = 'Abierto' and t0.cpo_cardcode =:cpo_cardcode";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":cpo_cardcode" => $Data['dms_card_code']));

				if(isset($resSelect[0])){

					$respuesta = array(
						'error' => false,
						'data'  => $resSelect,
						'mensaje' => '');

				}else{

						$respuesta = array(
							'error'   => true,
							'data' => array(),
							'mensaje'	=> 'busqueda sin resultados'
						);

				}

				 $this->response($respuesta);
	}









	private function getUrl($data, $caperta){
      $url = "";

      if ($data == NULL){

        return $url;

      }

			if (!base64_decode($data, true) ){
					return $url;
			}

			$ruta = '/var/www/html/'.$caperta.'/assets/img/anexos/';

      $milliseconds = round(microtime(true) * 1000);


      $nombreArchivo = $milliseconds.".pdf";

      touch($ruta.$nombreArchivo);

      $file = fopen($ruta.$nombreArchivo,"wb");

      if(!empty($data)){

        fwrite($file, base64_decode($data));

        fclose($file);

        $url = "assets/img/anexos/".$nombreArchivo;
      }

      return $url;
  }

	private function buscarPosicion($llave, $inArray){
			$res = 0;
	  	for($i = 0; $i < count($inArray); $i++) {
					if($inArray[$i] == "$llave"){
								$res =  $i;
								break;
					}
			}

			return $res;
	}

	private function validateDate($fecha){
			if(strlen($fecha) == 10 OR strlen($fecha) > 10){
				return true;
			}else{
				return false;
			}
	}



	private function setAprobacion($Encabezado, $Detalle, $Carpeta, $prefijoe, $prefijod,$Cantidad,$CantidadAP,$Model){

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
					':pap_series' => is_numeric($Encabezado[$prefijoe.'_series'])?$Encabezado[$prefijoe.'_series']:0,
					':pap_docdate' => $this->validateDate($Encabezado[$prefijoe.'_docdate'])?$Encabezado[$prefijoe.'_docdate']:NULL,
					':pap_duedate' => $this->validateDate($Encabezado[$prefijoe.'_duedate'])?$Encabezado[$prefijoe.'_duedate']:NULL,
					':pap_duedev' => $this->validateDate($Encabezado[$prefijoe.'_duedev'])?$Encabezado[$prefijoe.'_duedev']:NULL,
					':pap_pricelist' => is_numeric($Encabezado[$prefijoe.'_pricelist'])?$Encabezado[$prefijoe.'_pricelist']:0,
					':pap_cardcode' => isset($Encabezado[$prefijoe.'_cardcode'])?$Encabezado[$prefijoe.'_cardcode']:NULL,
					':pap_cardname' => isset($Encabezado[$prefijoe.'_cardname'])?$Encabezado[$prefijoe.'_cardname']:NULL,
					':pap_currency' => isset($Encabezado[$prefijoe.'_currency'])?$Encabezado[$prefijoe.'_currency']:NULL,
					':pap_contacid' => isset($Encabezado[$prefijoe.'_contacid'])?$Encabezado[$prefijoe.'_contacid']:NULL,
					':pap_slpcode' => is_numeric($Encabezado[$prefijoe.'_slpcode'])?$Encabezado[$prefijoe.'_slpcode']:0,
					':pap_empid' => is_numeric($Encabezado[$prefijoe.'_empid'])?$Encabezado[$prefijoe.'_empid']:0,
					':pap_comment' => isset($Encabezado[$prefijoe.'_comment'])?$Encabezado[$prefijoe.'_comment']:NULL,
					':pap_doctotal' => is_numeric($Encabezado[$prefijoe.'_doctotal'])?$Encabezado[$prefijoe.'_doctotal']:0,
					':pap_baseamnt' => is_numeric($Encabezado[$prefijoe.'_baseamnt'])?$Encabezado[$prefijoe.'_baseamnt']:0,
					':pap_taxtotal' => is_numeric($Encabezado[$prefijoe.'_taxtotal'])?$Encabezado[$prefijoe.'_taxtotal']:0,
					':pap_discprofit' => is_numeric($Encabezado[$prefijoe.'_discprofit'])?$Encabezado[$prefijoe.'_discprofit']:0,
					':pap_discount' => is_numeric($Encabezado[$prefijoe.'_discount'])?$Encabezado[$prefijoe.'_discount']:0,
					':pap_createat' => $this->validateDate($Encabezado[$prefijoe.'_createat'])?$Encabezado[$prefijoe.'_createat']:NULL,
					':pap_baseentry' => is_numeric($Encabezado[$prefijoe.'_baseentry'])?$Encabezado[$prefijoe.'_baseentry']:0,
					':pap_basetype' => is_numeric($Encabezado[$prefijoe.'_basetype'])?$Encabezado[$prefijoe.'_basetype']:0,
					':pap_doctype' => 21,
					':pap_idadd' => isset($Encabezado[$prefijoe.'_idadd'])?$Encabezado[$prefijoe.'_idadd']:NULL,
					':pap_adress' => isset($Encabezado[$prefijoe.'_adress'])?$Encabezado[$prefijoe.'_adress']:NULL,
					':pap_paytype' => is_numeric($Encabezado[$prefijoe.'_paytype'])?$Encabezado[$prefijoe.'_paytype']:0,
					':pap_createby' => isset($Encabezado[$prefijoe.'_createby'])?$Encabezado[$prefijoe.'_createby']:NULL,
					':pap_attch' => $this->getUrl(count(trim(($Encabezado[$prefijoe.'_attch']))) > 0 ? $Encabezado[$prefijoe.'_attch']:NULL, $Carpeta),
					':pap_origen' => is_numeric($Encabezado[$prefijoe.'_doctype'])?$Encabezado[$prefijoe.'_doctype']:0,
					':pap_qtyrq' => $Cantidad,
					':pap_qtyap' => $CantidadAP,
					':pap_model' => $Model

				));


				if(is_numeric($resInsert) && $resInsert > 0){

						//SE INSERTA EL ESTADO DEL DOCUMENTO

						$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

						$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


											':bed_docentry' => $resInsert,
											':bed_doctype' =>  21,
											':bed_status' => 5, //ESTADO CERRADO
											':bed_createby' => $Encabezado[$prefijoe.'_createby'],
											':bed_date' => date('Y-m-d'),
											':bed_baseentry' => NULL,
											':bed_basetype' => NULL
						));


						if(is_numeric($resInsertEstado) && $resInsertEstado > 0){

						}else{

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
																			ap1_acctcode, ap1_basetype, ap1_doctype, ap1_avprice, ap1_inventory, ap1_linenum, ap1_acciva)VALUES(:ap1_docentry, :ap1_itemcode, :ap1_itemname, :ap1_quantity,
																			:ap1_uom, :ap1_whscode,:ap1_price, :ap1_vat, :ap1_vatsum, :ap1_discount, :ap1_linetotal, :ap1_costcode, :ap1_ubusiness, :ap1_project,
																			:ap1_acctcode, :ap1_basetype, :ap1_doctype, :ap1_avprice, :ap1_inventory,:ap1_linenum,:ap1_acciva)";

									$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
													':ap1_docentry' => $resInsert,
													':ap1_itemcode' => isset($detail[$prefijod.'_itemcode'])?$detail[$prefijod.'_itemcode']:NULL,
													':ap1_itemname' => isset($detail[$prefijod.'_itemname'])?$detail[$prefijod.'_itemname']:NULL,
													':ap1_quantity' => is_numeric($detail[$prefijod.'_quantity'])?$detail[$prefijod.'_quantity']:0,
													':ap1_uom' => isset($detail[$prefijod.'_uom'])?$detail[$prefijod.'_uom']:NULL,
													':ap1_whscode' => isset($detail[$prefijod.'_whscode'])?$detail[$prefijod.'_whscode']:NULL,
													':ap1_price' => is_numeric($detail[$prefijod.'_price'])?$detail[$prefijod.'_price']:0,
													':ap1_vat' => is_numeric($detail[$prefijod.'_vat'])?$detail[$prefijod.'_vat']:0,
													':ap1_vatsum' => is_numeric($detail[$prefijod.'_vatsum'])?$detail[$prefijod.'_vatsum']:0,
													':ap1_discount' => is_numeric($detail[$prefijod.'_discount'])?$detail[$prefijod.'_discount']:0,
													':ap1_linetotal' => is_numeric($detail[$prefijod.'_linetotal'])?$detail[$prefijod.'_linetotal']:0,
													':ap1_costcode' => isset($detail[$prefijod.'_costcode'])?$detail[$prefijod.'_costcode']:NULL,
													':ap1_ubusiness' => isset($detail[$prefijod.'_ubusiness'])?$detail[$prefijod.'_ubusiness']:NULL,
													':ap1_project' => isset($detail[$prefijod.'_project'])?$detail[$prefijod.'_project']:NULL,
													':ap1_acctcode' => is_numeric($detail[$prefijod.'_acctcode'])?$detail[$prefijod.'_acctcode']:0,
													':ap1_basetype' => is_numeric($detail[$prefijod.'_basetype'])?$detail[$prefijod.'_basetype']:0,
													':ap1_doctype' => is_numeric($detail[$prefijod.'_doctype'])?$detail[$prefijod.'_doctype']:0,
													':ap1_avprice' => is_numeric($detail[$prefijod.'_avprice'])?$detail[$prefijod.'_avprice']:0,
													':ap1_inventory' => is_numeric($detail[$prefijod.'_inventory'])?$detail[$prefijod.'_inventory']:NULL,
													':ap1_linenum' => is_numeric($detail[$prefijod.'_linenum'])?$detail[$prefijod.'_linenum']:NULL,
													':ap1_acciva' => is_numeric($detail[$prefijod.'_acciva'])?$detail[$prefijod.'_acciva']:NULL
									));

									if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
											// Se verifica que el detalle no de error insertando //
									}else{

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
							'mensaje' =>'El documento fue creado, pero es necesario que sea aprobado'
						);

						$this->response($respuesta);

						return;


				}else{

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
