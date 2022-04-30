<?php
// REVALORIZACION DE INVENTARIO

defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class InventoryRevaluation extends REST_Controller {

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

  //CREAR NUEVA REVALORIZACION
	public function createSalesOrder_post(){

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
            'mensaje' =>'No se encontro el detalle de la cotización'
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

				$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['vov_series']));

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
				$resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $Data['vov_currency'], ':tsa_date' => $Data['vov_docdate']));

				if(isset($resBusTasa[0])){

				}else{

						if(trim($Data['vov_currency']) != $MONEDALOCAL ){

							$respuesta = array(
								'error' => true,
								'data'  => array(),
								'mensaje' =>'No se encrontro la tasa de cambio para la moneda: '.$Data['vov_currency'].' en la actual fecha del documento: '.$Data['vov_docdate'].' y la moneda local: '.$resMonedaLoc[0]['pgm_symbol']
							);

							$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

							return;
						}
				}


				$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
				$resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $Data['vov_docdate']));

				if(isset($resBusTasa2[0])){

				}else{
						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :'.$Data['vov_docdate']
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

									':bed_docentry' => $Data['vov_baseentry'],
									':bed_doctype'  => $Data['vov_basetype'],
									':bed_status'   => 4
				));

				if(!isset($resVerificarAprobacion[0])){

								//VERIFICAR MODELO DE APROBACION

								$sqlDocModelo = " SELECT * FROM tmau inner join mau1 on mau_docentry = au1_docentry where mau_doctype = :mau_doctype";
								$resDocModelo = $this->pedeo->queryTable($sqlDocModelo, array(':mau_doctype' => $Data['vov_doctype']));

								if(isset($resDocModelo[0])){

										$sqlModUser = "SELECT aus_id FROM taus
																	 INNER JOIN pgus
																	 ON aus_id_usuario = pgu_id_usuario
																	 WHERE aus_id_model = :aus_id_model
																	 AND pgu_code_user = :pgu_code_user";

										$resModUser = $this->pedeo->queryTable($sqlModUser, array(':aus_id_model' =>$resDocModelo[0]['mau_docentry'], ':pgu_code_user' =>$Data['vov_createby']));

										if(isset($resModUser[0])){
													// VALIDACION DE APROBACION

													$condicion1 = $resDocModelo[0]['au1_c1']; // ESTO ME DICE SI LA CONDICION DEL DOCTOTAL ES 1 MAYOR 2 MENOR
													$valorDocTotal = $resDocModelo[0]['au1_doctotal'];
													$valorSociosNegocio = $resDocModelo[0]['au1_sn'];
													$TotalDocumento = $Data['vov_doctotal'];

													if(trim($Data['vov_currency']) != $TasaDocLoc){
															$TotalDocumento = ($TotalDocumento * $TasaDocLoc);
													}


													if(is_numeric($valorDocTotal) && $valorDocTotal > 0){ //SI HAY UN VALOR Y SI ESTE ES MAYOR A CERO

															if( !empty($valorSociosNegocio ) ){ // CON EL SOCIO DE NEGOCIO

																	if($condicion1 == 1){

																		if( $TotalDocumento >= $valorDocTotal ){

																			if( in_array($Data['vov_cardcode'], explode(",", $valorSociosNegocio) )){

																					$this->setAprobacion($Data, $ContenidoDetalle,$resMainFolder[0]['main_folder'],'vov','ov1');
																			}
																		}
																	}else if($condicion1 == 2){

																		if($TotalDocumento <= $valorDocTotal  ){
																			if( in_array($Data['vov_cardcode'], explode(",", $valorSociosNegocio) )){

																					 $this->setAprobacion($Data, $ContenidoDetalle, $resMainFolder[0]['main_folder'],'vov','ov1');
																			}
																		}
																	}
															}else{ // SIN EL SOCIO DE NEGOCIO


																		if($condicion1 == 1){
																			if($TotalDocumento >= $valorDocTotal){

																				 $this->setAprobacion($Data, $ContenidoDetalle, $resMainFolder[0]['main_folder'],'vov','ov1');

																			}
																		}else if($condicion1 == 2){
																			if($TotalDocumento <= $valorDocTotal ){

																					$this->setAprobacion($Data, $ContenidoDetalle, $resMainFolder[0]['main_folder'],'vov','ov1');

																			}
																		}
															}
													}else{ // SI NO SE COMPARA EL TOTAL DEL DOCUMENTO

															if( !empty($valorSociosNegocio) ){

																if( in_array($Data['vov_cardcode'], explode(",", $valorSociosNegocio) )){

																		$respuesta = $this->setAprobacion($Data, $ContenidoDetalle, $resMainFolder[0]['main_folder'],'vov','ov1');
																}
															}else{

																		$respuesta = array(
																			'error' => true,
																			'data'  => array(),
																			'mensaje' =>'No se ha encontraro condiciones en el modelo de aprobacion, favor contactar con su administrador del sistema'
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




        $sqlInsert = "INSERT INTO dvov(vov_series, vov_docnum, vov_docdate, vov_duedate, vov_duedev, vov_pricelist, vov_cardcode,
                      vov_cardname, vov_currency, vov_contacid, vov_slpcode, vov_empid, vov_comment, vov_doctotal, vov_baseamnt, vov_taxtotal,
                      vov_discprofit, vov_discount, vov_createat, vov_baseentry, vov_basetype, vov_doctype, vov_idadd, vov_adress, vov_paytype,
                      vov_attch,vov_createby)VALUES(:vov_series, :vov_docnum, :vov_docdate, :vov_duedate, :vov_duedev, :vov_pricelist, :vov_cardcode, :vov_cardname,
                      :vov_currency, :vov_contacid, :vov_slpcode, :vov_empid, :vov_comment, :vov_doctotal, :vov_baseamnt, :vov_taxtotal, :vov_discprofit, :vov_discount,
                      :vov_createat, :vov_baseentry, :vov_basetype, :vov_doctype, :vov_idadd, :vov_adress, :vov_paytype, :vov_attch,:vov_createby)";


				// Se Inicia la transaccion,
				// Todas las consultas de modificacion siguientes
				// aplicaran solo despues que se confirme la transaccion,
				// de lo contrario no se aplicaran los cambios y se devolvera
				// la base de datos a su estado original.

			  $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':vov_docnum' => $DocNumVerificado,
              ':vov_series' => is_numeric($Data['vov_series'])?$Data['vov_series']:0,
              ':vov_docdate' => $this->validateDate($Data['vov_docdate'])?$Data['vov_docdate']:NULL,
              ':vov_duedate' => $this->validateDate($Data['vov_duedate'])?$Data['vov_duedate']:NULL,
              ':vov_duedev' => $this->validateDate($Data['vov_duedev'])?$Data['vov_duedev']:NULL,
              ':vov_pricelist' => is_numeric($Data['vov_pricelist'])?$Data['vov_pricelist']:0,
              ':vov_cardcode' => isset($Data['vov_cardcode'])?$Data['vov_cardcode']:NULL,
              ':vov_cardname' => isset($Data['vov_cardname'])?$Data['vov_cardname']:NULL,
              ':vov_currency' => isset($Data['vov_currency'])?$Data['vov_currency']:NULL,
              ':vov_contacid' => isset($Data['vov_contacid'])?$Data['vov_contacid']:NULL,
              ':vov_slpcode' => is_numeric($Data['vov_slpcode'])?$Data['vov_slpcode']:0,
              ':vov_empid' => is_numeric($Data['vov_empid'])?$Data['vov_empid']:0,
              ':vov_comment' => isset($Data['vov_comment'])?$Data['vov_comment']:NULL,
              ':vov_doctotal' => is_numeric($Data['vov_doctotal'])?$Data['vov_doctotal']:0,
              ':vov_baseamnt' => is_numeric($Data['vov_baseamnt'])?$Data['vov_baseamnt']:0,
              ':vov_taxtotal' => is_numeric($Data['vov_taxtotal'])?$Data['vov_taxtotal']:0,
              ':vov_discprofit' => is_numeric($Data['vov_discprofit'])?$Data['vov_discprofit']:0,
              ':vov_discount' => is_numeric($Data['vov_discount'])?$Data['vov_discount']:0,
              ':vov_createat' => $this->validateDate($Data['vov_createat'])?$Data['vov_createat']:NULL,
              ':vov_baseentry' => is_numeric($Data['vov_baseentry'])?$Data['vov_baseentry']:0,
              ':vov_basetype' => is_numeric($Data['vov_basetype'])?$Data['vov_basetype']:0,
              ':vov_doctype' => is_numeric($Data['vov_doctype'])?$Data['vov_doctype']:0,
              ':vov_idadd' => isset($Data['vov_idadd'])?$Data['vov_idadd']:NULL,
              ':vov_adress' => isset($Data['vov_adress'])?$Data['vov_adress']:NULL,
              ':vov_paytype' => is_numeric($Data['vov_paytype'])?$Data['vov_paytype']:0,
							':vov_createby' => isset($Data['vov_createby'])?$Data['vov_createby']:NULL,
              ':vov_attch' => $this->getUrl(count(trim(($Data['vov_attch']))) > 0 ? $Data['vov_attch']:NULL, $resMainFolder[0]['main_folder'])
						));

        if(is_numeric($resInsert) && $resInsert > 0){

					// Se actualiza la serie de la numeracion del documento

					$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
					$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
							':pgs_nextnum' => $DocNumVerificado,
							':pgs_id'      => $Data['vov_series']
					));


					if(is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1){

					}else{
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


					if(is_numeric($resInsertEstado) && $resInsertEstado > 0){



					}else{

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
					if( isset($Data['vov_baseentry']) && is_numeric($Data['vov_baseentry']) && isset($Data['vov_basetype']) && is_numeric($Data['vov_basetype']) ){

						$sqlDocInicio = "SELECT bmd_tdi, bmd_ndi FROM tbmd WHERE  bmd_doctype = :bmd_doctype AND bmd_docentry = :bmd_docentry";
						$resDocInicio = $this->pedeo->queryTable($sqlDocInicio, array(
							 ':bmd_doctype' => $Data['vov_basetype'],
							 ':bmd_docentry' => $Data['vov_baseentry']
						));


						if ( isset(	$resDocInicio[0] ) ){

							$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
															bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype)
															VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
															:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype)";

							$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

								':bmd_doctype' => is_numeric($Data['vov_doctype'])?$Data['vov_doctype']:0,
								':bmd_docentry' => $resInsert,
								':bmd_createat' => $this->validateDate($Data['vov_createat'])?$Data['vov_createat']:NULL,
								':bmd_doctypeo' => is_numeric($Data['vov_basetype'])?$Data['vov_basetype']:0, //ORIGEN
								':bmd_docentryo' => is_numeric($Data['vov_baseentry'])?$Data['vov_baseentry']:0,  //ORIGEN
								':bmd_tdi' => $resDocInicio[0]['bmd_tdi'], // DOCUMENTO INICIAL
								':bmd_ndi' => $resDocInicio[0]['bmd_ndi'], // DOCUMENTO INICIAL
								':bmd_docnum' => $DocNumVerificado,
								':bmd_doctotal' => is_numeric($Data['vov_doctotal'])?$Data['vov_doctotal']:0,
								':bmd_cardcode' => isset($Data['vov_cardcode'])?$Data['vov_cardcode']:NULL,
								':bmd_cardtype' => 1
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

								':bmd_doctype' => is_numeric($Data['vov_doctype'])?$Data['vov_doctype']:0,
								':bmd_docentry' => $resInsert,
								':bmd_createat' => $this->validateDate($Data['vov_createat'])?$Data['vov_createat']:NULL,
								':bmd_doctypeo' => is_numeric($Data['vov_basetype'])?$Data['vov_basetype']:0, //ORIGEN
								':bmd_docentryo' => is_numeric($Data['vov_baseentry'])?$Data['vov_baseentry']:0,  //ORIGEN
								':bmd_tdi' => is_numeric($Data['vov_doctype'])?$Data['vov_doctype']:0, // DOCUMENTO INICIAL
								':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
								':bmd_docnum' => $DocNumVerificado,
								':bmd_doctotal' => is_numeric($Data['vov_doctotal'])?$Data['vov_doctotal']:0,
								':bmd_cardcode' => isset($Data['vov_cardcode'])?$Data['vov_cardcode']:NULL,
								':bmd_cardtype' => 1
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

							':bmd_doctype' => is_numeric($Data['vov_doctype'])?$Data['vov_doctype']:0,
							':bmd_docentry' => $resInsert,
							':bmd_createat' => $this->validateDate($Data['vov_createat'])?$Data['vov_createat']:NULL,
							':bmd_doctypeo' => is_numeric($Data['vov_basetype'])?$Data['vov_basetype']:0, //ORIGEN
							':bmd_docentryo' => is_numeric($Data['vov_baseentry'])?$Data['vov_baseentry']:0,  //ORIGEN
							':bmd_tdi' => is_numeric($Data['vov_doctype'])?$Data['vov_doctype']:0, // DOCUMENTO INICIAL
							':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
							':bmd_docnum' => $DocNumVerificado,
							':bmd_doctotal' => is_numeric($Data['vov_doctotal'])?$Data['vov_doctotal']:0,
							':bmd_cardcode' => isset($Data['vov_cardcode'])?$Data['vov_cardcode']:NULL,
							':bmd_cardtype' => 1
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

                $sqlInsertDetail = "INSERT INTO vov1(ov1_docentry, ov1_itemcode, ov1_itemname, ov1_quantity, ov1_uom, ov1_whscode,
                                    ov1_price, ov1_vat, ov1_vatsum, ov1_discount, ov1_linetotal, ov1_costcode, ov1_ubusiness, ov1_project,
                                    ov1_acctcode, ov1_basetype, ov1_doctype, ov1_avprice, ov1_inventory, ov1_acciva)VALUES(:ov1_docentry, :ov1_itemcode, :ov1_itemname, :ov1_quantity,
                                    :ov1_uom, :ov1_whscode,:ov1_price, :ov1_vat, :ov1_vatsum, :ov1_discount, :ov1_linetotal, :ov1_costcode, :ov1_ubusiness, :ov1_project,
                                    :ov1_acctcode, :ov1_basetype, :ov1_doctype, :ov1_avprice, :ov1_inventory, :ov1_acciva)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':ov1_docentry' => $resInsert,
                        ':ov1_itemcode' => isset($detail['ov1_itemcode'])?$detail['ov1_itemcode']:NULL,
                        ':ov1_itemname' => isset($detail['ov1_itemname'])?$detail['ov1_itemname']:NULL,
                        ':ov1_quantity' => is_numeric($detail['ov1_quantity'])?$detail['ov1_quantity']:0,
                        ':ov1_uom' => isset($detail['ov1_uom'])?$detail['ov1_uom']:NULL,
                        ':ov1_whscode' => isset($detail['ov1_whscode'])?$detail['ov1_whscode']:NULL,
                        ':ov1_price' => is_numeric($detail['ov1_price'])?$detail['ov1_price']:0,
                        ':ov1_vat' => is_numeric($detail['ov1_vat'])?$detail['ov1_vat']:0,
                        ':ov1_vatsum' => is_numeric($detail['ov1_vatsum'])?$detail['ov1_vatsum']:0,
                        ':ov1_discount' => is_numeric($detail['ov1_discount'])?$detail['ov1_discount']:0,
                        ':ov1_linetotal' => is_numeric($detail['ov1_linetotal'])?$detail['ov1_linetotal']:0,
                        ':ov1_costcode' => isset($detail['ov1_costcode'])?$detail['ov1_costcode']:NULL,
                        ':ov1_ubusiness' => isset($detail['ov1_ubusiness'])?$detail['ov1_ubusiness']:NULL,
                        ':ov1_project' => isset($detail['ov1_project'])?$detail['ov1_project']:NULL,
                        ':ov1_acctcode' => is_numeric($detail['ov1_acctcode'])?$detail['ov1_acctcode']:0,
                        ':ov1_basetype' => is_numeric($detail['ov1_basetype'])?$detail['ov1_basetype']:0,
                        ':ov1_doctype' => is_numeric($detail['ov1_doctype'])?$detail['ov1_doctype']:0,
                        ':ov1_avprice' => is_numeric($detail['ov1_avprice'])?$detail['ov1_avprice']:0,
                        ':ov1_inventory' => is_numeric($detail['ov1_inventory'])?$detail['ov1_inventory']:NULL,
												':ov1_acciva' => is_numeric($detail['ov1_acciva'])?$detail['ov1_acciva']:NULL
                ));

								if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
										// Se verifica que el detalle no de error insertando //
								}else{

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
																		       count(t1.vc1_itemcode) item,
																		       sum(t1.vc1_quantity) cantidad
																		from dvct t0
																		inner join vct1 t1 on t0.dvc_docentry = t1.vc1_docentry
																		where t0.dvc_docentry = :dvc_docentry and t0.dvc_doctype = :dvc_doctype";


						$resEstado1 = $this->pedeo->queryTable($sqlEstado1, array(
							':dvc_docentry' => $Data['vov_baseentry'],
							':dvc_doctype' => $Data['vov_basetype']
							// ':vc1_itemcode' => $detail['ov1_itemcode']
						));

						$sqlEstado2 = "SELECT
																		       coalesce(count(distinct t1.vc1_itemcode),0) item,
																		       coalesce(sum(t3.ov1_quantity),0) cantidad
																		from dvct t0
																		inner join vct1 t1 on t0.dvc_docentry = t1.vc1_docentry
																		left join dvov t2 on t0.dvc_docentry = t2.vov_baseentry
																		left join vov1 t3 on t2.vov_docentry = t3.ov1_docentry and t1.vc1_itemcode = t3.ov1_itemcode
																		where t0.dvc_docentry = :dvc_docentry and t0.dvc_doctype = :dvc_doctype and t1.vc1_itemcode = :vc1_itemcode";


						$resEstado2 = $this->pedeo->queryTable($sqlEstado2, array(
							':dvc_docentry' => $Data['vov_baseentry'],
							':dvc_doctype' => $Data['vov_basetype'],
							':vc1_itemcode' => $detail['ov1_itemcode']

						));

						$item_cot = $resEstado1[0]['item'];
					  $cantidad_cot = $resEstado1[0]['cantidad'];
						$item_ord = $resEstado2[0]['item'];
						$cantidad_ord = $resEstado2[0]['cantidad'];



//
// print_r($item_cot);
// print_r($item_ord);
// print_r($cantidad_cot);
// print_r($cantidad_ord);exit();die();

						if($item_cot == $item_ord  &&  $cantidad_cot == $cantidad_ord){

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


									if(is_numeric($resInsertEstado) && $resInsertEstado > 0){

									}else{

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


					// Si todo sale bien despues de insertar el detalle de el pedido
					// se confirma la trasaccion  para que los cambios apliquen permanentemente
					// en la base de datos y se confirma la operacion exitosa.
					$this->pedeo->trans_commit();

          $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' =>'Pedido registrado con exito'
          );


        }else{
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
}
