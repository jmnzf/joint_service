<?php
// Entrega de Ventas
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class SalesDel extends REST_Controller {

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

  //CREAR NUEVA Entrega de Ventas
	public function createSalesDel_post(){

      $Data = $this->post();
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
			$posicion = 0;// contiene la posicion con que se creara en el array DetalleConsolidado
			$posicionIva = 0;
			$posicionCostoInventario = 0;
			$posicionCostoCosto = 0;
			$codigoCuenta = ""; //para saber la naturaleza
			$grantotalCostoInventario = 0;
			$DocNumVerificado = 0;
			$ManejaInvetario = 0;
			$AC1LINE = 1;
			$AgregarAsiento = true;


			// Se globaliza la variable sqlDetalleAsiento
			$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
													ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
													ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
													ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, ac1_line)VALUES (:ac1_trans_id, :ac1_account,
													:ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
													:ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
													:ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
													:ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref, :ac1_line)";



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
            'mensaje' =>'No se encontro el detalle de la Entrega de ventas'
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

				$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['vem_series']));

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

				// FIN DEL PROCEDIMIENTO PARA BUSCAR LA CARPETA DEL sistema


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
				$resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $Data['vem_currency'], ':tsa_date' => $Data['vem_docdate']));

				if(isset($resBusTasa[0])){

				}else{

						if(trim($Data['vem_currency']) != $MONEDALOCAL ){

							$respuesta = array(
								'error' => true,
								'data'  => array(),
								'mensaje' =>'No se encrontro la tasa de cambio para la moneda: '.$Data['vem_currency'].' en la actual fecha del documento: '.$Data['vem_docdate'].' y la moneda local: '.$resMonedaLoc[0]['pgm_symbol']
							);

							$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

							return;
						}
				}


				$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
				$resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $Data['vem_docdate']));

				if(isset($resBusTasa2[0])){

				}else{
						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :'.$Data['vem_docdate']
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

									':bed_docentry' => $Data['vem_baseentry'],
									':bed_doctype'  => $Data['vem_basetype'],
									':bed_status'   => 4
				));

				if(!isset($resVerificarAprobacion[0])){

								//VERIFICAR MODELO DE APROBACION

								$sqlDocModelo = " SELECT * FROM tmau inner join mau1 on mau_docentry = au1_docentry where mau_doctype = :mau_doctype";
								$resDocModelo = $this->pedeo->queryTable($sqlDocModelo, array(':mau_doctype' => $Data['vem_doctype']));

								if(isset($resDocModelo[0])){

										$sqlModUser = "SELECT aus_id FROM taus
																	 INNER JOIN pgus
																	 ON aus_id_usuario = pgu_id_usuario
																	 WHERE aus_id_model = :aus_id_model
																	 AND pgu_code_user = :pgu_code_user";

										$resModUser = $this->pedeo->queryTable($sqlModUser, array(':aus_id_model' =>$resDocModelo[0]['mau_docentry'], ':pgu_code_user' =>$Data['vem_createby']));

										if(isset($resModUser[0])){
													// VALIDACION DE APROBACION

													$condicion1 = $resDocModelo[0]['au1_c1']; // ESTO ME DICE SI LA CONDICION DEL DOCTOTAL ES 1 MAYOR 2 MENOR
													$valorDocTotal = $resDocModelo[0]['au1_doctotal'];
													$valorSociosNegocio = $resDocModelo[0]['au1_sn'];
													$TotalDocumento = $Data['vem_doctotal'];

													if(trim($Data['vem_currency']) != $TasaDocLoc){
															$TotalDocumento = ($TotalDocumento * $TasaDocLoc);
													}


													if(is_numeric($valorDocTotal) && $valorDocTotal > 0){ //SI HAY UN VALOR Y SI ESTE ES MAYOR A CERO

															if( !empty($valorSociosNegocio ) ){ // CON EL SOCIO DE NEGOCIO

																	if($condicion1 == 1){

																		if( $TotalDocumento >= $valorDocTotal ){

																			if( in_array($Data['vem_cardcode'], explode(",", $valorSociosNegocio) )){

																					$this->setAprobacion($Data, $ContenidoDetalle,$resMainFolder[0]['main_folder'],'vem','em1');
																			}
																		}
																	}else if($condicion1 == 2){

																		if($TotalDocumento <= $valorDocTotal  ){
																			if( in_array($Data['vem_cardcode'], explode(",", $valorSociosNegocio) )){

																					 $this->setAprobacion($Data, $ContenidoDetalle, $resMainFolder[0]['main_folder'],'vem','em1');
																			}
																		}
																	}
															}else{ // SIN EL SOCIO DE NEGOCIO


																		if($condicion1 == 1){
																			if($TotalDocumento >= $valorDocTotal){

																				 $this->setAprobacion($Data, $ContenidoDetalle, $resMainFolder[0]['main_folder'],'vem','em1');

																			}
																		}else if($condicion1 == 2){
																			if($TotalDocumento <= $valorDocTotal ){

																					$this->setAprobacion($Data, $ContenidoDetalle, $resMainFolder[0]['main_folder'],'vem','em1');

																			}
																		}
															}
													}else{ // SI NO SE COMPARA EL TOTAL DEL DOCUMENTO

															if( !empty($valorSociosNegocio) ){

																if( in_array($Data['vem_cardcode'], explode(",", $valorSociosNegocio) )){

																		$respuesta = $this->setAprobacion($Data, $ContenidoDetalle, $resMainFolder[0]['main_folder'],'vem','em1');
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


        $sqlInsert = "INSERT INTO dvem(vem_series, vem_docnum, vem_docdate, vem_duedate, vem_duedev, vem_pricelist, vem_cardcode,
                      vem_cardname, vem_currency, vem_contacid, vem_slpcode, vem_empid, vem_comment, vem_doctotal, vem_baseamnt, vem_taxtotal,
                      vem_discprofit, vem_discount, vem_createat, vem_baseentry, vem_basetype, vem_doctype, vem_idadd, vem_adress, vem_paytype,
                      vem_attch,vem_createby,vem_transport,vem_sup_transport,vem_ci,vem_t_vehiculo,vem_guia,vem_opl,vem_placa,vem_precinto,vem_placav,vem_modelv,vem_driverv,vem_driverid)
											VALUES(:vem_series, :vem_docnum, :vem_docdate, :vem_duedate, :vem_duedev, :vem_pricelist, :vem_cardcode, :vem_cardname,
                      :vem_currency, :vem_contacid, :vem_slpcode, :vem_empid, :vem_comment, :vem_doctotal, :vem_baseamnt, :vem_taxtotal, :vem_discprofit, :vem_discount,
                      :vem_createat, :vem_baseentry, :vem_basetype, :vem_doctype, :vem_idadd, :vem_adress, :vem_paytype, :vem_attch,:vem_createby,
										  :vem_transport,:vem_sup_transport,:vem_ci,:vem_t_vehiculo,:vem_guia,:vem_opl,:vem_placa,:vem_precinto,:vem_placav,:vem_modelv,:vem_driverv,:vem_driverid)";






				// Se Inicia la transaccion,
				// Todas las consultas de modificacion siguientes
				// aplicaran solo despues que se confirme la transaccion,
				// de lo contrario no se aplicaran los cambios y se devolvera
				// la base de datos a su estado original.

			  $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':vem_docnum' => $DocNumVerificado,
              ':vem_series' => is_numeric($Data['vem_series'])?$Data['vem_series']:0,
              ':vem_docdate' => $this->validateDate($Data['vem_docdate'])?$Data['vem_docdate']:NULL,
              ':vem_duedate' => $this->validateDate($Data['vem_duedate'])?$Data['vem_duedate']:NULL,
              ':vem_duedev' => $this->validateDate($Data['vem_duedev'])?$Data['vem_duedev']:NULL,
              ':vem_pricelist' => is_numeric($Data['vem_pricelist'])?$Data['vem_pricelist']:0,
              ':vem_cardcode' => isset($Data['vem_cardcode'])?$Data['vem_cardcode']:NULL,
              ':vem_cardname' => isset($Data['vem_cardname'])?$Data['vem_cardname']:NULL,
              ':vem_currency' => isset($Data['vem_currency'])?$Data['vem_currency']:NULL,
              ':vem_contacid' => isset($Data['vem_contacid'])?$Data['vem_contacid']:NULL,
              ':vem_slpcode' => is_numeric($Data['vem_slpcode'])?$Data['vem_slpcode']:0,
              ':vem_empid' => is_numeric($Data['vem_empid'])?$Data['vem_empid']:0,
              ':vem_comment' => isset($Data['vem_comment'])?$Data['vem_comment']:NULL,
              ':vem_doctotal' => is_numeric($Data['vem_doctotal'])?$Data['vem_doctotal']:0,
              ':vem_baseamnt' => is_numeric($Data['vem_baseamnt'])?$Data['vem_baseamnt']:0,
              ':vem_taxtotal' => is_numeric($Data['vem_taxtotal'])?$Data['vem_taxtotal']:0,
              ':vem_discprofit' => is_numeric($Data['vem_discprofit'])?$Data['vem_discprofit']:0,
              ':vem_discount' => is_numeric($Data['vem_discount'])?$Data['vem_discount']:0,
              ':vem_createat' => $this->validateDate($Data['vem_createat'])?$Data['vem_createat']:NULL,
              ':vem_baseentry' => is_numeric($Data['vem_baseentry'])?$Data['vem_baseentry']:0,
              ':vem_basetype' => is_numeric($Data['vem_basetype'])?$Data['vem_basetype']:0,
              ':vem_doctype' => is_numeric($Data['vem_doctype'])?$Data['vem_doctype']:0,
              ':vem_idadd' => isset($Data['vem_idadd'])?$Data['vem_idadd']:NULL,
              ':vem_adress' => isset($Data['vem_adress'])?$Data['vem_adress']:NULL,
              ':vem_paytype' => is_numeric($Data['vem_paytype'])?$Data['vem_paytype']:0,
							':vem_createby' => isset($Data['vem_createby'])?$Data['vem_createby']:NULL,
							':vem_transport' => isset($Data['vem_transport'])?$Data['vem_transport']:NULL,
							':vem_sup_transport' => isset($Data['vem_sup_transport'])?$Data['vem_sup_transport']:NULL,
							':vem_ci' => isset($Data['vem_ci'])?$Data['vem_ci']:NULL,
							':vem_t_vehiculo' => isset($Data['vem_t_vehiculo'])?$Data['vem_t_vehiculo']:NULL,
							':vem_guia' => isset($Data['vem_guia'])?$Data['vem_guia']:NULL,
							':vem_opl' => isset($Data['vem_opl'])?$Data['vem_opl']:NULL,
							':vem_placa' => isset($Data['vem_placa'])?$Data['vem_placa']:NULL,
              ':vem_attch' => $this->getUrl(count(trim(($Data['vem_attch']))) > 0 ? $Data['vem_attch']:NULL, $resMainFolder[0]['main_folder']),

							':vem_precinto' => isset($Data['vem_precinto'])?$Data['vem_precinto']:NULL,
							':vem_placav' => isset($Data['vem_placav'])?$Data['vem_placav']:NULL,
							':vem_modelv' => isset($Data['vem_modelv'])?$Data['vem_modelv']:NULL,
							':vem_driverv' => isset($Data['vem_driverv'])?$Data['vem_driverv']:NULL,
							':vem_driverid' => isset($Data['vem_driverid'])?$Data['vem_driverid']:NULL





						));

        if(is_numeric($resInsert) && $resInsert > 0){

					// Se actualiza la serie de la numeracion del documento

					$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
					$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
							':pgs_nextnum' => $DocNumVerificado,
							':pgs_id'      => $Data['vem_series']
					));


					if(is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1){

					}else{
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resActualizarNumeracion,
									'mensaje'	=> 'No se pudo crear la Entrega de ventas'
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
										':bed_doctype' => $Data['vem_doctype'],
										':bed_status' => 1, //ESTADO CERRADO
										':bed_createby' => $Data['vem_createby'],
										':bed_date' => date('Y-m-d'),
										':bed_baseentry' => NULL,
										':bed_basetype' =>NULL
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




          foreach ($ContenidoDetalle as $key => $detail) {

                $sqlInsertDetail = "INSERT INTO vem1(em1_docentry, em1_itemcode, em1_itemname, em1_quantity, em1_uom, em1_whscode,
                                    em1_price, em1_vat, em1_vatsum, em1_discount, em1_linetotal, em1_costcode, em1_ubusiness, em1_project,
                                    em1_acctcode, em1_basetype, em1_doctype, em1_avprice, em1_inventory, em1_acciva, em1_linenum)VALUES(:em1_docentry, :em1_itemcode, :em1_itemname, :em1_quantity,
                                    :em1_uom, :em1_whscode,:em1_price, :em1_vat, :em1_vatsum, :em1_discount, :em1_linetotal, :em1_costcode, :em1_ubusiness, :em1_project,
                                    :em1_acctcode, :em1_basetype, :em1_doctype, :em1_avprice, :em1_inventory, :em1_acciva, :em1_linenum)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':em1_docentry' => $resInsert,
                        ':em1_itemcode' => isset($detail['em1_itemcode'])?$detail['em1_itemcode']:NULL,
                        ':em1_itemname' => isset($detail['em1_itemname'])?$detail['em1_itemname']:NULL,
                        ':em1_quantity' => is_numeric($detail['em1_quantity'])?$detail['em1_quantity']:0,
                        ':em1_uom' => isset($detail['em1_uom'])?$detail['em1_uom']:NULL,
                        ':em1_whscode' => isset($detail['em1_whscode'])?$detail['em1_whscode']:NULL,
                        ':em1_price' => is_numeric($detail['em1_price'])?$detail['em1_price']:0,
                        ':em1_vat' => is_numeric($detail['em1_vat'])?$detail['em1_vat']:0,
                        ':em1_vatsum' => is_numeric($detail['em1_vatsum'])?$detail['em1_vatsum']:0,
                        ':em1_discount' => is_numeric($detail['em1_discount'])?$detail['em1_discount']:0,
                        ':em1_linetotal' => is_numeric($detail['em1_linetotal'])?$detail['em1_linetotal']:0,
                        ':em1_costcode' => isset($detail['em1_costcode'])?$detail['em1_costcode']:NULL,
                        ':em1_ubusiness' => isset($detail['em1_ubusiness'])?$detail['em1_ubusiness']:NULL,
                        ':em1_project' => isset($detail['em1_project'])?$detail['em1_project']:NULL,
                        ':em1_acctcode' => is_numeric($detail['em1_acctcode'])?$detail['em1_acctcode']:0,
                        ':em1_basetype' => is_numeric($detail['em1_basetype'])?$detail['em1_basetype']:0,
                        ':em1_doctype' => is_numeric($detail['em1_doctype'])?$detail['em1_doctype']:0,
                        ':em1_avprice' => is_numeric($detail['em1_avprice'])?$detail['em1_avprice']:0,
                        ':em1_inventory' => is_numeric($detail['em1_inventory'])?$detail['em1_inventory']:NULL,
												':em1_acciva' => is_numeric($detail['em1_cuentaIva'])?$detail['em1_cuentaIva']:0,
												':em1_linenum'=> is_numeric($detail['em1_linenum'])?$detail['em1_linenum']:0
                ));

								if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
										// Se verifica que el detalle no de error insertando //
								}else{

										// si falla algun insert del detalle de la Entrega de Ventas se devuelven los cambios realizados por la transaccion,
										// se retorna el error y se detiene la ejecucion del codigo restante.
											$this->pedeo->trans_rollback();

											$respuesta = array(
												'error'   => true,
												'data' => $resInsert,
												'mensaje'	=> 'No se pudo registrar la Entrega de ventas'
											);

											 $this->response($respuesta);

											 return;
								}

								// SE VERIFICA SI EL ARTICULO ESTA MARCADO PARA MANEJARSE EN INVENTARIO
								$sqlItemINV = "SELECT dma_item_inv FROM dmar WHERE dma_item_code = :dma_item_code AND dma_item_inv = :dma_item_inv";
								$resItemINV = $this->pedeo->queryTable($sqlItemINV, array(

												':dma_item_code' => $detail['em1_itemcode'],
												':dma_item_inv'  => 1
								));

								if(isset($resItemINV[0])){

										$ManejaInvetario = 1;

								}

								// FIN PROCESO ITEM MANEJA INVENTARIO



								// si el item es inventariable
								if( $ManejaInvetario  == 1){

										//Se agregan los asientos contables si almenos existe un item inventariable
										//pero solo una ves

										if( $AgregarAsiento ){

											$sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user)
																					 VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user)";


											$resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

													':mac_doc_num' => 1,
													':mac_status' => 1,
													':mac_base_type' => is_numeric($Data['vem_doctype'])?$Data['vem_doctype']:0,
													':mac_base_entry' => $resInsert,
													':mac_doc_date' => $this->validateDate($Data['vem_docdate'])?$Data['vem_docdate']:NULL,
													':mac_doc_duedate' => $this->validateDate($Data['vem_duedate'])?$Data['vem_duedate']:NULL,
													':mac_legal_date' => $this->validateDate($Data['vem_docdate'])?$Data['vem_docdate']:NULL,
													':mac_ref1' => is_numeric($Data['vem_doctype'])?$Data['vem_doctype']:0,
													':mac_ref2' => "",
													':mac_ref3' => "",
													':mac_loc_total' => is_numeric($Data['vem_doctotal'])?$Data['vem_doctotal']:0,
													':mac_fc_total' => is_numeric($Data['vem_doctotal'])?$Data['vem_doctotal']:0,
													':mac_sys_total' => is_numeric($Data['vem_doctotal'])?$Data['vem_doctotal']:0,
													':mac_trans_dode' => 1,
													':mac_beline_nume' => 1,
													':mac_vat_date' => $this->validateDate($Data['vem_docdate'])?$Data['vem_docdate']:NULL,
													':mac_serie' => 1,
													':mac_number' => 1,
													':mac_bammntsys' => is_numeric($Data['vem_baseamnt'])?$Data['vem_baseamnt']:0,
													':mac_bammnt' => is_numeric($Data['vem_baseamnt'])?$Data['vem_baseamnt']:0,
													':mac_wtsum' => 1,
													':mac_vatsum' => is_numeric($Data['vem_taxtotal'])?$Data['vem_taxtotal']:0,
													':mac_comments' => isset($Data['vem_comment'])?$Data['vem_comment']:NULL,
													':mac_create_date' => $this->validateDate($Data['vem_createat'])?$Data['vem_createat']:NULL,
													':mac_made_usuer' => isset($Data['vem_createby'])?$Data['vem_createby']:NULL,
													':mac_update_date' => date("Y-m-d"),
													':mac_update_user' => isset($Data['vem_createby'])?$Data['vem_createby']:NULL
											));


											if(is_numeric($resInsertAsiento) && $resInsertAsiento > 0){
													$AgregarAsiento = false;
											}else{

													// si falla algun insert del detalle de la Entrega de Ventas se devuelven los cambios realizados por la transaccion,
													// se retorna el error y se detiene la ejecucion del codigo restante.
														$this->pedeo->trans_rollback();

														$respuesta = array(
															'error'   => true,
															'data'	  => $resInsertAsiento,
															'mensaje'	=> 'No se pudo registrar la Entrega de ventas'
														);

														 $this->response($respuesta);

														 return;
											}

										}
										// FIN DEL PROCEDIMIENTO PARA AGREGAR LOS ASIENTOS CONTABLES


										//se busca el costo del item en el momento de la creacion del documento de venta
										// para almacenar en el movimiento de inventario

										$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode";
										$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(':bdi_whscode' => $detail['em1_whscode'], ':bdi_itemcode' => $detail['em1_itemcode']));


										if(isset($resCostoMomentoRegistro[0])){


											//VALIDANDO CANTIDAD DE ARTICULOS

													$CANT_ARTICULOEX = $resCostoMomentoRegistro[0]['bdi_quantity'];
													$CANT_ARTICULOLN = is_numeric($detail['em1_quantity'])? $detail['em1_quantity'] : 0;

													if( ($CANT_ARTICULOEX - $CANT_ARTICULOLN) < 0){

															$this->pedeo->trans_rollback();

															$respuesta = array(
																'error'   => true,
																'data' => [],
																'mensaje'	=> 'no puede crear el documento porque el articulo '.$detail['em1_itemcode'].' recae en inventario negativo ('.($CANT_ARTICULOEX - $CANT_ARTICULOLN).')'
															);

															 $this->response($respuesta);

															 return;

													}

											//VALIDANDO CANTIDAD DE ARTICULOS

											//Se aplica el movimiento de inventario
											$sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode, bmi_quantity, bmi_whscode, bmi_createat, bmi_createby, bmy_doctype, bmy_baseentry,bmi_cost)
																						 VALUES (:bmi_itemcode, :bmi_quantity, :bmi_whscode, :bmi_createat, :bmi_createby, :bmy_doctype, :bmy_baseentry, :bmi_cost)";

											$sqlInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(

													 ':bmi_itemcode'  => isset($detail['em1_itemcode'])?$detail['em1_itemcode']:NULL,
													 ':bmi_quantity'  => is_numeric($detail['em1_quantity'])? $detail['em1_quantity'] * $Data['invtype']:0,
													 ':bmi_whscode'   => isset($detail['em1_whscode'])?$detail['em1_whscode']:NULL,
													 ':bmi_createat'  => $this->validateDate($Data['vem_createat'])?$Data['vem_createat']:NULL,
													 ':bmi_createby'  => isset($Data['vem_createby'])?$Data['vem_createby']:NULL,
													 ':bmy_doctype'   => is_numeric($Data['vem_doctype'])?$Data['vem_doctype']:0,
													 ':bmy_baseentry' => $resInsert,
													 ':bmi_cost'      => $resCostoMomentoRegistro[0]['bdi_avgprice']

											));

											if(is_numeric($sqlInserMovimiento) && $sqlInserMovimiento > 0){
													// Se verifica que el detalle no de error insertando //
											}else{

													// si falla algun insert del detalle de la Entrega de Ventas se devuelven los cambios realizados por la transaccion,
													// se retorna el error y se detiene la ejecucion del codigo restante.
														$this->pedeo->trans_rollback();

														$respuesta = array(
															'error'   => true,
															'data' => $sqlInserMovimiento,
															'mensaje'	=> 'No se pudo registrar la Entrega de ventas'
														);

														 $this->response($respuesta);

														 return;
											}


										}else{

												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data' => $resCostoMomentoRegistro,
													'mensaje'	=> 'No se pudo registrar la Entrega de ventas, no se encontro el costo del articulo'
												);

												 $this->response($respuesta);

												 return;
										}

											//FIN aplicacion de movimiento de inventario


											//Se Aplica el movimiento en stock ***************
											// Buscando item en el stock
											$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
																						FROM tbdi
																						WHERE bdi_itemcode = :bdi_itemcode
																						AND bdi_whscode = :bdi_whscode";

											$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(

														':bdi_itemcode' => $detail['em1_itemcode'],
														':bdi_whscode'  => $detail['em1_whscode']
											));

											if(isset($resCostoCantidad[0])){

												if($resCostoCantidad[0]['bdi_quantity'] > 0){

														 $CantidadActual = $resCostoCantidad[0]['bdi_quantity'];
														 $CantidadNueva = $detail['em1_quantity'];


														 $CantidadTotal = ($CantidadActual - $CantidadNueva);

														 $sqlUpdateCostoCantidad =  "UPDATE tbdi
																												 SET bdi_quantity = :bdi_quantity
																												 WHERE  bdi_id = :bdi_id";

														 $resUpdateCostoCantidad = $this->pedeo->updateRow($sqlUpdateCostoCantidad, array(

																	 ':bdi_quantity' => $CantidadTotal,
																	 ':bdi_id' 			 => $resCostoCantidad[0]['bdi_id']
														 ));

														 if(is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad == 1){

														 }else{

																 $this->pedeo->trans_rollback();

																 $respuesta = array(
																	 'error'   => true,
																	 'data'    => $resUpdateCostoCantidad,
																	 'mensaje'	=> 'No se pudo crear la Entrega de Ventas'
																 );
														 }

												}else{

																 $this->pedeo->trans_rollback();

																 $respuesta = array(
																	 'error'   => true,
																	 'data'    => $resUpdateCostoCantidad,
																	 'mensaje' => 'No hay existencia para el item: '.$detail['em1_itemcode']
																 );
												}

											}else{

														$this->pedeo->trans_rollback();

														$respuesta = array(
															'error'   => true,
															'data' 		=> $resInsertCostoCantidad,
															'mensaje'	=> 'El item no existe en el stock '.$detail['em1_itemcode']
														);

														 $this->response($respuesta);

														 return;
											}
											//FIN de  Aplicacion del movimiento en stock
								}

								//SOLO PARA LOS ITEMS INVENTATIABLES
								if( $ManejaInvetario == 1){

										//LLENANDO DETALLE ASIENTO CONTABLES

										$DetalleAsientoIngreso = new stdClass();
										$DetalleAsientoIva = new stdClass();
										$DetalleCostoInventario = new stdClass();
										$DetalleCostoCosto = new stdClass();


										$DetalleAsientoIngreso->ac1_account = is_numeric($detail['em1_acctcode'])?$detail['em1_acctcode']: 0;
										$DetalleAsientoIngreso->ac1_prc_code = isset($detail['em1_costcode'])?$detail['em1_costcode']:NULL;
										$DetalleAsientoIngreso->ac1_uncode = isset($detail['em1_ubusiness'])?$detail['em1_ubusiness']:NULL;
										$DetalleAsientoIngreso->ac1_prj_code = isset($detail['em1_project'])?$detail['em1_project']:NULL;
										$DetalleAsientoIngreso->em1_linetotal = is_numeric($detail['em1_linetotal'])?$detail['em1_linetotal']:0;
										$DetalleAsientoIngreso->em1_vat = is_numeric($detail['em1_vat'])?$detail['em1_vat']:0;
										$DetalleAsientoIngreso->em1_vatsum = is_numeric($detail['em1_vatsum'])?$detail['em1_vatsum']:0;
										$DetalleAsientoIngreso->em1_price = is_numeric($detail['em1_price'])?$detail['em1_price']:0;
										$DetalleAsientoIngreso->em1_itemcode = isset($detail['em1_itemcode'])?$detail['em1_itemcode']:NULL;
										$DetalleAsientoIngreso->em1_quantity = is_numeric($detail['em1_quantity'])?$detail['em1_quantity']:0;
										$DetalleAsientoIngreso->em1_whscode = isset($detail['em1_whscode'])?$detail['em1_whscode']:NULL;



										$DetalleAsientoIva->ac1_account = is_numeric($detail['em1_acctcode'])?$detail['em1_acctcode']: 0;
										$DetalleAsientoIva->ac1_prc_code = isset($detail['em1_costcode'])?$detail['em1_costcode']:NULL;
										$DetalleAsientoIva->ac1_uncode = isset($detail['em1_ubusiness'])?$detail['em1_ubusiness']:NULL;
										$DetalleAsientoIva->ac1_prj_code = isset($detail['em1_project'])?$detail['em1_project']:NULL;
										$DetalleAsientoIva->em1_linetotal = is_numeric($detail['em1_linetotal'])?$detail['em1_linetotal']:0;
										$DetalleAsientoIva->em1_vat = is_numeric($detail['em1_vat'])?$detail['em1_vat']:0;
										$DetalleAsientoIva->em1_vatsum = is_numeric($detail['em1_vatsum'])?$detail['em1_vatsum']:0;
										$DetalleAsientoIva->em1_price = is_numeric($detail['em1_price'])?$detail['em1_price']:0;
										$DetalleAsientoIva->em1_itemcode = isset($detail['em1_itemcode'])?$detail['em1_itemcode']:NULL;
										$DetalleAsientoIva->em1_quantity = is_numeric($detail['em1_quantity'])?$detail['em1_quantity']:0;
										$DetalleAsientoIva->em1_cuentaIva = is_numeric($detail['em1_cuentaIva'])?$detail['em1_cuentaIva']:NULL;
										$DetalleAsientoIva->em1_whscode = isset($detail['em1_whscode'])?$detail['em1_whscode']:NULL;



										// se busca la cuenta contable del costoInventario y costoCosto
										$sqlArticulo = "SELECT f2.dma_item_code,  f1.mga_acct_inv, f1.mga_acct_cost FROM dmga f1 JOIN dmar f2 ON f1.mga_id  = f2.dma_group_code WHERE dma_item_code = :dma_item_code";

										$resArticulo = $this->pedeo->queryTable($sqlArticulo, array(':dma_item_code' => $detail['em1_itemcode']));

										if(!isset($resArticulo[0])){

													$this->pedeo->trans_rollback();

													$respuesta = array(
														'error'   => true,
														'data' => $resArticulo,
														'mensaje'	=> 'No se pudo registrar la Entrega de ventas'
													);

													 $this->response($respuesta);

													 return;
										}


										$DetalleCostoInventario->ac1_account = $resArticulo[0]['mga_acct_inv'];
										$DetalleCostoInventario->ac1_prc_code = isset($detail['em1_costcode'])?$detail['em1_costcode']:NULL;
										$DetalleCostoInventario->ac1_uncode = isset($detail['em1_ubusiness'])?$detail['em1_ubusiness']:NULL;
										$DetalleCostoInventario->ac1_prj_code = isset($detail['em1_project'])?$detail['em1_project']:NULL;
										$DetalleCostoInventario->em1_linetotal = is_numeric($detail['em1_linetotal'])?$detail['em1_linetotal']:0;
										$DetalleCostoInventario->em1_vat = is_numeric($detail['em1_vat'])?$detail['em1_vat']:0;
										$DetalleCostoInventario->em1_vatsum = is_numeric($detail['em1_vatsum'])?$detail['em1_vatsum']:0;
										$DetalleCostoInventario->em1_price = is_numeric($detail['em1_price'])?$detail['em1_price']:0;
										$DetalleCostoInventario->em1_itemcode = isset($detail['em1_itemcode'])?$detail['em1_itemcode']:NULL;
										$DetalleCostoInventario->em1_quantity = is_numeric($detail['em1_quantity'])?$detail['em1_quantity']:0;
										$DetalleCostoInventario->em1_whscode = isset($detail['em1_whscode'])?$detail['em1_whscode']:NULL;


										$DetalleCostoCosto->ac1_account = $resArticulo[0]['mga_acct_cost'];
										$DetalleCostoCosto->ac1_prc_code = isset($detail['em1_costcode'])?$detail['em1_costcode']:NULL;
										$DetalleCostoCosto->ac1_uncode = isset($detail['em1_ubusiness'])?$detail['em1_ubusiness']:NULL;
										$DetalleCostoCosto->ac1_prj_code = isset($detail['em1_project'])?$detail['em1_project']:NULL;
										$DetalleCostoCosto->em1_linetotal = is_numeric($detail['em1_linetotal'])?$detail['em1_linetotal']:0;
										$DetalleCostoCosto->em1_vat = is_numeric($detail['em1_vat'])?$detail['em1_vat']:0;
										$DetalleCostoCosto->em1_vatsum = is_numeric($detail['em1_vatsum'])?$detail['em1_vatsum']:0;
										$DetalleCostoCosto->em1_price = is_numeric($detail['em1_price'])?$detail['em1_price']:0;
										$DetalleCostoCosto->em1_itemcode = isset($detail['em1_itemcode'])?$detail['em1_itemcode']:NULL;
										$DetalleCostoCosto->em1_quantity = is_numeric($detail['em1_quantity'])?$detail['em1_quantity']:0;
										$DetalleCostoCosto->em1_whscode = isset($detail['em1_whscode'])?$detail['em1_whscode']:NULL;

										$codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);

										$DetalleAsientoIngreso->codigoCuenta = $codigoCuenta;
										$DetalleAsientoIva->codigoCuenta = $codigoCuenta;
										$DetalleCostoInventario->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);
										$DetalleCostoInventario->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);


										$llave = $DetalleAsientoIngreso->ac1_uncode.$DetalleAsientoIngreso->ac1_prc_code.$DetalleAsientoIngreso->ac1_prj_code.$DetalleAsientoIngreso->ac1_account;
										$llaveIva = $DetalleAsientoIva->em1_vat;
										$llaveCostoInventario = $DetalleCostoInventario->ac1_account;
										$llaveCostoCosto = $DetalleCostoCosto->ac1_account;


										if(in_array( $llave, $inArrayIngreso )){

												$posicion = $this->buscarPosicion( $llave, $inArrayIngreso );

										}else{

												array_push( $inArrayIngreso, $llave );
												$posicion = $this->buscarPosicion( $llave, $inArrayIngreso );

										}


										if(in_array( $llaveIva, $inArrayIva )){

												$posicionIva = $this->buscarPosicion( $llaveIva, $inArrayIva );

										}else{

												array_push( $inArrayIva, $llaveIva );
												$posicionIva = $this->buscarPosicion( $llaveIva, $inArrayIva );

										}


										if(in_array( $llaveCostoInventario, $inArrayCostoInventario )){

												$posicionCostoInventario = $this->buscarPosicion( $llaveCostoInventario, $inArrayCostoInventario );

										}else{

												array_push( $inArrayCostoInventario, $llaveCostoInventario );
												$posicionCostoInventario = $this->buscarPosicion( $llaveCostoInventario, $inArrayCostoInventario );

										}


										if(in_array( $llaveCostoCosto, $inArrayCostoCosto )){

												$posicionCostoCosto = $this->buscarPosicion( $llaveCostoCosto, $inArrayCostoCosto );

										}else{

												array_push( $inArrayCostoCosto, $llaveCostoCosto );
												$posicionCostoCosto = $this->buscarPosicion( $llaveCostoCosto, $inArrayCostoCosto );

										}



										if( isset($DetalleConsolidadoIva[$posicionIva])){

											if(!is_array($DetalleConsolidadoIva[$posicionIva])){
												$DetalleConsolidadoIva[$posicionIva] = array();
											}

										}else{
											$DetalleConsolidadoIva[$posicionIva] = array();
										}

										array_push( $DetalleConsolidadoIva[$posicionIva], $DetalleAsientoIva);


										if( isset($DetalleConsolidadoIngreso[$posicion])){

											if(!is_array($DetalleConsolidadoIngreso[$posicion])){
												$DetalleConsolidadoIngreso[$posicion] = array();
											}

										}else{
											$DetalleConsolidadoIngreso[$posicion] = array();
										}

										array_push( $DetalleConsolidadoIngreso[$posicion], $DetalleAsientoIngreso);


										if( isset($DetalleConsolidadoCostoInventario[$posicionCostoInventario])){

											if(!is_array($DetalleConsolidadoCostoInventario[$posicionCostoInventario])){
												$DetalleConsolidadoCostoInventario[$posicionCostoInventario] = array();
											}

										}else{
											$DetalleConsolidadoCostoInventario[$posicionCostoInventario] = array();
										}

										array_push( $DetalleConsolidadoCostoInventario[$posicionCostoInventario], $DetalleCostoInventario );


										if( isset($DetalleConsolidadoCostoCosto[$posicionCostoCosto])){

											if(!is_array($DetalleConsolidadoCostoCosto[$posicionCostoCosto])){
												$DetalleConsolidadoCostoCosto[$posicionCostoCosto] = array();
											}

										}else{
											$DetalleConsolidadoCostoCosto[$posicionCostoCosto] = array();
										}

										array_push( $DetalleConsolidadoCostoCosto[$posicionCostoCosto], $DetalleCostoCosto );


								}

          }

					//Procedimiento para llenar costo inventario
					foreach ($DetalleConsolidadoCostoInventario as $key => $posicion) {
							$grantotalCostoInventario = 0 ;
							$cuentaInventario = "";
							foreach ($posicion as $key => $value) {

										$sqlArticulo = "SELECT f2.dma_item_code,  f1.mga_acct_inv, f1.mga_acct_cost FROM dmga f1 JOIN dmar f2 ON f1.mga_id  = f2.dma_group_code WHERE dma_item_code = :dma_item_code";

										$resArticulo = $this->pedeo->queryTable($sqlArticulo, array(':dma_item_code' => $value->em1_itemcode));

										if(isset($resArticulo[0])){
												$dbito = 0;
												$cdito = 0;

												$MontoSysDB = 0;
												$MontoSysCR = 0;

												$sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode";

												$resCosto = $this->pedeo->queryTable($sqlCosto, array(':bdi_itemcode' => $value->em1_itemcode, ':bdi_whscode' => $value->em1_whscode));

												if( isset( $resCosto[0] ) ){

															$cuentaInventario = $resArticulo[0]['mga_acct_inv'];


															$costoArticulo = $resCosto[0]['bdi_avgprice'];
															$cantidadArticulo = $value->em1_quantity;
															$grantotalCostoInventario = ($grantotalCostoInventario + ($costoArticulo * $cantidadArticulo));

												}else{

															$this->pedeo->trans_rollback();

															$respuesta = array(
																'error'   => true,
																'data'	  => $resArticulo,
																'mensaje'	=> 'No se encontro el costo para el item: '.$value->em1_itemcode
															);

															 $this->response($respuesta);

															 return;
												}

										}else{
												// si falla algun insert del detalle de la Entrega de Ventas se devuelven los cambios realizados por la transaccion,
												// se retorna el error y se detiene la ejecucion del codigo restante.
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data'	  => $resArticulo,
													'mensaje'	=> 'No se encontro la cuenta de inventario y costo para el item '.$value->em1_itemcode
												);

												 $this->response($respuesta);

												 return;
										}
							}

							$codigo3 = substr($cuentaInventario, 0, 1);

							if(trim($Data['vem_currency']) != $MONEDALOCAL ){
								 $grantotalCostoInventario = ($grantotalCostoInventario * $TasaDocLoc);
							}

							if( $codigo3 == 1 || $codigo3 == "1" ){
									$cdito = $grantotalCostoInventario;
									$MontoSysCR = ($cdito / $TasaLocSys);
							}else if( $codigo3 == 2 || $codigo3 == "2" ){
									$cdito = $grantotalCostoInventario;
									$MontoSysCR = ($cdito / $TasaLocSys);
							}else if( $codigo3 == 3 || $codigo3 == "3" ){
									$cdito = $grantotalCostoInventario;
									$MontoSysCR = ($cdito / $TasaLocSys);
							}else if( $codigo3 == 4 || $codigo3 == "4" ){
									$cdito = $grantotalCostoInventario;
									$MontoSysCR = ($cdito / $TasaLocSys);
							}else if( $codigo3 == 5  || $codigo3 == "5" ){
									$dbito = $grantotalCostoInventario;
									$MontoSysDB = ($dbito / $TasaLocSys);
							}else if( $codigo3 == 6 || $codigo3 == "6" ){
									$dbito = $grantotalCostoInventario;
									$MontoSysDB = ($dbito / $TasaLocSys);
							}else if( $codigo3 == 7 || $codigo3 == "7" ){
									$dbito = $grantotalCostoInventario;
									$MontoSysDB = ($dbito / $TasaLocSys);
							}

							$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

									':ac1_trans_id' => $resInsertAsiento,
									':ac1_account' => $cuentaInventario,
									':ac1_debit' => $dbito,
									':ac1_credit' => $cdito,
									':ac1_debit_sys' => round($MontoSysDB,2),
									':ac1_credit_sys' => round($MontoSysCR,2),
									':ac1_currex' => 0,
									':ac1_doc_date' => $this->validateDate($Data['vem_docdate'])?$Data['vem_docdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['vem_duedate'])?$Data['vem_duedate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => $resInsert,
									':ac1_font_line' => 1,
									':ac1_font_type' => is_numeric($Data['vem_doctype'])?$Data['vem_doctype']:0,
									':ac1_accountvs' => 1,
									':ac1_doctype' => 18,
									':ac1_ref1' => "",
									':ac1_ref2' => "",
									':ac1_ref3' => "",
									':ac1_prc_code' => NULL,
									':ac1_uncode' => NULL,
									':ac1_prj_code' => NULL,
									':ac1_rescon_date' => NULL,
									':ac1_recon_total' => 0,
									':ac1_made_user' => isset($Data['vem_createby'])?$Data['vem_createby']:NULL,
									':ac1_accperiod' => 1,
									':ac1_close' => 0,
									':ac1_cord' => 0,
									':ac1_ven_debit' => 1,
									':ac1_ven_credit' => 1,
									':ac1_fiscal_acct' => 0,
									':ac1_taxid' => 1,
									':ac1_isrti' => 0,
									':ac1_basert' => 0,
									':ac1_mmcode' => 0,
									':ac1_legal_num' => isset($Data['vem_cardcode'])?$Data['vem_cardcode']:NULL,
									':ac1_codref' => 1,
									':ac1_line'   => $AC1LINE
						));

						if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
						}else{

								// si falla algun insert del detalle de la Entrega de Ventas se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resDetalleAsiento,
										'mensaje'	=> 'No se pudo registrar la Entrega de ventas'
									);

									 $this->response($respuesta);

									 return;
						}

					}
					//FIN Procedimiento para llenar costo inventario


					// Procedimiento para llenar costo costo
					foreach ($DetalleConsolidadoCostoCosto as $key => $posicion) {
							$grantotalCostoCosto = 0 ;
							$cuentaCosto = "";
							foreach ($posicion as $key => $value) {

										// $sqlArticulo = "SELECT f2.dma_item_code,  f1.mga_acct_inv, f1.mga_acct_cost FROM dmga f1 JOIN dmar f2 ON f1.mga_id  = f2.dma_group_code WHERE dma_item_code = :dma_item_code";
										// $resArticulo = $this->pedeo->queryTable($sqlArticulo, array(":dma_item_code" => $value->em1_itemcode));

										$sqlArticulo = "SELECT pge_bridge_inv FROM pgem";
										$resArticulo = $this->pedeo->queryTable($sqlArticulo, array());


										if(isset($resArticulo[0])){
												$dbito = 0;
												$cdito = 0;
												$MontoSysDB = 0;
												$MontoSysCR = 0;

												$sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode";

												$resCosto = $this->pedeo->queryTable($sqlCosto, array(':bdi_itemcode' => $value->em1_itemcode, ':bdi_whscode' => $value->em1_whscode));


												if( isset( $resCosto[0] ) ){

															$cuentaCosto = $resArticulo[0]['pge_bridge_inv']; // En la entrega se coloca la cuenta puente


															$costoArticulo = $resCosto[0]['bdi_avgprice'];
															$cantidadArticulo = $value->em1_quantity;
															$grantotalCostoCosto = ($grantotalCostoCosto + ($costoArticulo * $cantidadArticulo));

												}else{

															$this->pedeo->trans_rollback();

															$respuesta = array(
																'error'   => true,
																'data'	  => $resArticulo,
																'mensaje'	=> 'No se encontro el costo para el item: '.$value->em1_itemcode
															);

															 $this->response($respuesta);

															 return;
												}

										}else{
												// si falla algun insert del detalle de la Entrega de Ventas se devuelven los cambios realizados por la transaccion,
												// se retorna el error y se detiene la ejecucion del codigo restante.
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data'	  => $resArticulo,
													'mensaje'	=> 'No se encontro la cuenta puente para costo');

												 $this->response($respuesta);

												 return;
										}
							}

								$codigo3 = substr($cuentaCosto, 0, 1);

								if(trim($Data['vem_currency']) != $MONEDALOCAL ){
									 $grantotalCostoCosto = ($grantotalCostoCosto * $TasaDocLoc);
								}


								if( $codigo3 == 1 || $codigo3 == "1" ){
									$dbito = 	$grantotalCostoCosto;
									$MontoSysDB = ($dbito / $TasaLocSys);
								}else if( $codigo3 == 2 || $codigo3 == "2" ){
									$cdito = 	$grantotalCostoCosto;
									$MontoSysCR = ($cdito / $TasaLocSys);
								}else if( $codigo3 == 3 || $codigo3 == "3" ){
									$cdito = 	$grantotalCostoCosto;
									$MontoSysCR = ($cdito / $TasaLocSys);
								}else if( $codigo3 == 4 || $codigo3 == "4" ){
									$cdito = 	$grantotalCostoCosto;
									$MontoSysCR = ($cdito / $TasaLocSys);
								}else if( $codigo3 == 5  || $codigo3 == "5" ){
									$dbito = 	$grantotalCostoCosto;
									$MontoSysDB = ($dbito / $TasaLocSys);
								}else if( $codigo3 == 6 || $codigo3 == "6" ){
									$dbito = 	$grantotalCostoCosto;
									$MontoSysDB = ($dbito / $TasaLocSys);
								}else if( $codigo3 == 7 || $codigo3 == "7" ){
									$dbito = 	$grantotalCostoCosto;
									$MontoSysDB = ($dbito / $TasaLocSys);
								}

								$AC1LINE = $AC1LINE+1;
								$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

								':ac1_trans_id' => $resInsertAsiento,
								':ac1_account' => $cuentaCosto,
								':ac1_debit' => $dbito,
								':ac1_credit' => $cdito,
								':ac1_debit_sys' => round($MontoSysDB,2),
								':ac1_credit_sys' => round($MontoSysCR,2),
								':ac1_currex' => 0,
								':ac1_doc_date' => $this->validateDate($Data['vem_docdate'])?$Data['vem_docdate']:NULL,
								':ac1_doc_duedate' => $this->validateDate($Data['vem_duedate'])?$Data['vem_duedate']:NULL,
								':ac1_debit_import' => 0,
								':ac1_credit_import' => 0,
								':ac1_debit_importsys' => 0,
								':ac1_credit_importsys' => 0,
								':ac1_font_key' => $resInsert,
								':ac1_font_line' => 1,
								':ac1_font_type' => is_numeric($Data['vem_doctype'])?$Data['vem_doctype']:0,
								':ac1_accountvs' => 1,
								':ac1_doctype' => 18,
								':ac1_ref1' => "",
								':ac1_ref2' => "",
								':ac1_ref3' => "",
								':ac1_prc_code' => $value->ac1_prc_code,
								':ac1_uncode' => $value->ac1_uncode,
								':ac1_prj_code' => $value->ac1_prj_code,
								':ac1_rescon_date' => NULL,
								':ac1_recon_total' => 0,
								':ac1_made_user' => isset($Data['vem_createby'])?$Data['vem_createby']:NULL,
								':ac1_accperiod' => 1,
								':ac1_close' => 0,
								':ac1_cord' => 0,
								':ac1_ven_debit' => 1,
								':ac1_ven_credit' => 1,
								':ac1_fiscal_acct' => 0,
								':ac1_taxid' => 1,
								':ac1_isrti' => 0,
								':ac1_basert' => 0,
								':ac1_mmcode' => 0,
								':ac1_legal_num' => isset($Data['vem_cardcode'])?$Data['vem_cardcode']:NULL,
								':ac1_codref' => 1,
								':ac1_line'   => $AC1LINE
								));

								if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
								}else{

								// si falla algun insert del detalle de la Entrega de Ventas se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $resDetalleAsiento,
									'mensaje'	=> 'No se pudo registrar la Entrega de ventas'
								);

								 $this->response($respuesta);

								 return;
								}

					}


				 //FIN Procedimiento para llenar costo costo


					// Si todo sale bien despues de insertar el detalle de la Entrega de Ventas
					// se confirma la trasaccion  para que los cambios apliquen permanentemente
					// en la base de datos y se confirma la operacion exitosa.
					if ($Data['vem_basetype'] == 1) {


						$sqlEstado1 = "SELECT
																					 count(t1.vc1_itemcode) item,
																					 sum(t1.vc1_quantity) cantidad
																		from dvct t0
																		inner join vct1 t1 on t0.dvc_docentry = t1.vc1_docentry
																		where t0.dvc_docentry = :dvc_docentry and t0.dvc_doctype = :dvc_doctype";


						$resEstado1 = $this->pedeo->queryTable($sqlEstado1, array(
							':dvc_docentry' => $Data['vem_baseentry'],
							':dvc_doctype' => $Data['vem_basetype']
							// ':vc1_itemcode' => $detail['ov1_itemcode']
						));

						$sqlEstado2 = "SELECT
																					 coalesce(count(t3.em1_itemcode),0) item,
																					 coalesce(sum(t3.em1_quantity),0) cantidad
																		from dvct t0
																		left join vct1 t1 on t0.dvc_docentry = t1.vc1_docentry
																		left join dvem t2 on t0.dvc_docentry = t2.vem_baseentry
																		left join vem1 t3 on t2.vem_docentry = t3.em1_docentry and t1.vc1_itemcode = t3.em1_itemcode
																		where t0.dvc_docentry = :dvc_docentry and t0.dvc_doctype = :dvc_doctype";


						$resEstado2 = $this->pedeo->queryTable($sqlEstado2, array(
							':dvc_docentry' => $Data['vem_baseentry'],
							':dvc_doctype' => $Data['vem_basetype']
							// ':vc1_itemcode' => $detail['ov1_itemcode']
						));

						$item_cot = $resEstado1[0]['item'];
						$item_del = $resEstado2[0]['item'];
						$cantidad_cot = $resEstado1[0]['cantidad'];
						$cantidad_del = $resEstado2[0]['cantidad'];



	// print_r($item_cot);
	// print_r($item_ord);
	// print_r($cantidad_cot);
	// print_r($cantidad_ord);exit();die();
						if($item_cot == $item_del  &&  $cantidad_cot == $cantidad_del){

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

					}else if ($Data['vem_basetype'] == 2) {


								$sqlEstado1 = "SELECT
																			count(t1.ov1_itemcode) item,
																			sum(t1.ov1_quantity) cantidad
																			from dvov t0
																			inner join vov1 t1 on t0.vov_docentry = t1.ov1_docentry
																			where t0.vov_docentry = :vov_docentry and t0.vov_doctype = :vov_doctype";


								$resEstado1 = $this->pedeo->queryTable($sqlEstado1, array(
									':vov_docentry' => $Data['vem_baseentry'],
									':vov_doctype' => $Data['vem_basetype']
								));


								$sqlEstado2 = "SELECT
																				coalesce(count(t3.em1_itemcode),0) item,
																				coalesce(sum(t3.em1_quantity),0) cantidad
																				from dvov t0
																				left join vov1 t1 on t0.vov_docentry = t1.ov1_docentry
																				left join dvem t2 on t0.vov_docentry = t2.vem_baseentry
																				left join vem1 t3 on t2.vem_docentry = t3.em1_docentry and t1.ov1_itemcode = t3.em1_itemcode
																			  where t0.vov_docentry = :vov_docentry and t0.vov_doctype = :vov_doctype";
							$resEstado2 = $this->pedeo->queryTable($sqlEstado2,array(
								':vov_docentry' => $Data['vem_baseentry'],
								':vov_doctype' => $Data['vem_basetype']
							));

							$item_ord = $resEstado1[0]['item'];
							$item_del = $resEstado2[0]['item'];
							$cantidad_ord = $resEstado1[0]['cantidad'];
							$cantidad_del = $resEstado2[0]['cantidad'];



		// print_r($item_cot);
		// print_r($item_ord);
		// print_r($cantidad_cot);
		// print_r($cantidad_ord);exit();die();
							if($item_ord == $item_del  &&  $cantidad_ord == $cantidad_del){

											$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																					VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

											$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


																':bed_docentry' => $Data['vem_baseentry'],
																':bed_doctype' => $Data['vem_basetype'],
																':bed_status' => 3, //ESTADO CERRADO
																':bed_createby' => $Data['vem_createby'],
																':bed_date' => date('Y-m-d'),
																':bed_baseentry' => $resInsert,
																':bed_basetype' => $Data['vem_doctype']
											));


											if(is_numeric($resInsertEstado) && $resInsertEstado > 0){

											}else{

													 $this->pedeo->trans_rollback();

														$respuesta = array(
															'error'   => true,
															'data' => $resInsertEstado,
															'mensaje'	=> 'No se pudo registrar la entrega de venta'
														);


														$this->response($respuesta);

														return;
											}

								}

					}


					$this->pedeo->trans_commit();

          $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' =>'Entrega de ventas registrada con exito'
          );


        }else{
					// Se devuelven los cambios realizados en la transaccion
					// si occurre un error  y se muestra devuelve el error.
							$this->pedeo->trans_rollback();

              $respuesta = array(
                'error'   => true,
                'data' => $resInsert,
                'mensaje'	=> 'No se pudo registrar la Entrega de ventas'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR Entrega de Ventas
  public function updateSalesDel_post(){

      $Data = $this->post();

			if(!isset($Data['vem_docentry']) OR !isset($Data['vem_docnum']) OR
				 !isset($Data['vem_docdate']) OR !isset($Data['vem_duedate']) OR
				 !isset($Data['vem_duedev']) OR !isset($Data['vem_pricelist']) OR
				 !isset($Data['vem_cardcode']) OR !isset($Data['vem_cardname']) OR
				 !isset($Data['vem_currency']) OR !isset($Data['vem_contacid']) OR
				 !isset($Data['vem_slpcode']) OR !isset($Data['vem_empid']) OR
				 !isset($Data['vem_comment']) OR !isset($Data['vem_doctotal']) OR
				 !isset($Data['vem_baseamnt']) OR !isset($Data['vem_taxtotal']) OR
				 !isset($Data['vem_discprofit']) OR !isset($Data['vem_discount']) OR
				 !isset($Data['vem_createat']) OR !isset($Data['vem_baseentry']) OR
				 !isset($Data['vem_basetype']) OR !isset($Data['vem_doctype']) OR
				 !isset($Data['vem_idadd']) OR !isset($Data['vem_adress']) OR
				 !isset($Data['vem_paytype']) OR !isset($Data['vem_attch']) OR
				 !isset($Data['detail'])){

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
            'mensaje' =>'No se encontro el detalle de la Entrega de ventas'
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

      $sqlUpdate = "UPDATE dvem	SET vem_docdate=:vem_docdate,vem_duedate=:vem_duedate, vem_duedev=:vem_duedev, vem_pricelist=:vem_pricelist, vem_cardcode=:vem_cardcode,
			  						vem_cardname=:vem_cardname, vem_currency=:vem_currency, vem_contacid=:vem_contacid, vem_slpcode=:vem_slpcode,
										vem_empid=:vem_empid, vem_comment=:vem_comment, vem_doctotal=:vem_doctotal, vem_baseamnt=:vem_baseamnt,
										vem_taxtotal=:vem_taxtotal, vem_discprofit=:vem_discprofit, vem_discount=:vem_discount, vem_createat=:vem_createat,
										vem_baseentry=:vem_baseentry, vem_basetype=:vem_basetype, vem_doctype=:vem_doctype, vem_idadd=:vem_idadd,
										vem_adress=:vem_adress, vem_paytype=:vem_paytype, vem_attch=:vem_attch WHERE vem_docentry=:vem_docentry";

      $this->pedeo->trans_begin();

      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
							':vem_docnum' => is_numeric($Data['vem_docnum'])?$Data['vem_docnum']:0,
							':vem_docdate' => $this->validateDate($Data['vem_docdate'])?$Data['vem_docdate']:NULL,
							':vem_duedate' => $this->validateDate($Data['vem_duedate'])?$Data['vem_duedate']:NULL,
							':vem_duedev' => $this->validateDate($Data['vem_duedev'])?$Data['vem_duedev']:NULL,
							':vem_pricelist' => is_numeric($Data['vem_pricelist'])?$Data['vem_pricelist']:0,
							':vem_cardcode' => isset($Data['vem_pricelist'])?$Data['vem_pricelist']:NULL,
							':vem_cardname' => isset($Data['vem_cardname'])?$Data['vem_cardname']:NULL,
							':vem_currency' => isset($Data['vem_currency'])?$Data['vem_currency']:NULL,
							':vem_contacid' => isset($Data['vem_contacid'])?$Data['vem_contacid']:NULL,
							':vem_slpcode' => is_numeric($Data['vem_slpcode'])?$Data['vem_slpcode']:0,
							':vem_empid' => is_numeric($Data['vem_empid'])?$Data['vem_empid']:0,
							':vem_comment' => isset($Data['vem_comment'])?$Data['vem_comment']:NULL,
							':vem_doctotal' => is_numeric($Data['vem_doctotal'])?$Data['vem_doctotal']:0,
							':vem_baseamnt' => is_numeric($Data['vem_baseamnt'])?$Data['vem_baseamnt']:0,
							':vem_taxtotal' => is_numeric($Data['vem_taxtotal'])?$Data['vem_taxtotal']:0,
							':vem_discprofit' => is_numeric($Data['vem_discprofit'])?$Data['vem_discprofit']:0,
							':vem_discount' => is_numeric($Data['vem_discount'])?$Data['vem_discount']:0,
							':vem_createat' => $this->validateDate($Data['vem_createat'])?$Data['vem_createat']:NULL,
							':vem_baseentry' => is_numeric($Data['vem_baseentry'])?$Data['vem_baseentry']:0,
							':vem_basetype' => is_numeric($Data['vem_basetype'])?$Data['vem_basetype']:0,
							':vem_doctype' => is_numeric($Data['vem_doctype'])?$Data['vem_doctype']:0,
							':vem_idadd' => isset($Data['vem_idadd'])?$Data['vem_idadd']:NULL,
							':vem_adress' => isset($Data['vem_adress'])?$Data['vem_adress']:NULL,
							':vem_paytype' => is_numeric($Data['vem_paytype'])?$Data['vem_paytype']:0,
							':vem_attch' => $this->getUrl(count(trim(($Data['vem_attch']))) > 0 ? $Data['vem_attch']:NULL, $resMainFolder[0]['main_folder']),
							':vem_docentry' => $Data['vem_docentry']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

						$this->pedeo->queryTable("DELETE FROM vem1 WHERE em1_docentry=:em1_docentry", array(':em1_docentry' => $Data['vem_docentry']));

						foreach ($ContenidoDetalle as $key => $detail) {

									$sqlInsertDetail = "INSERT INTO vem1(em1_docentry, em1_itemcode, em1_itemname, em1_quantity, em1_uom, em1_whscode,
																			em1_price, em1_vat, em1_vatsum, em1_discount, em1_linetotal, em1_costcode, em1_ubusiness, em1_project,
																			em1_acctcode, em1_basetype, em1_doctype, em1_avprice, em1_inventory,em1_acciva)VALUES(:em1_docentry, :em1_itemcode, :em1_itemname, :em1_quantity,
																			:em1_uom, :em1_whscode,:em1_price, :em1_vat, :em1_vatsum, :em1_discount, :em1_linetotal, :em1_costcode, :em1_ubusiness, :em1_project,
																			:em1_acctcode, :em1_basetype, :em1_doctype, :em1_avprice, :em1_inventory, :em1_acciva)";

									$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
											':em1_docentry' => $Data['vem_docentry'],
											':em1_itemcode' => isset($detail['em1_itemcode'])?$detail['em1_itemcode']:NULL,
											':em1_itemname' => isset($detail['em1_itemname'])?$detail['em1_itemname']:NULL,
											':em1_quantity' => is_numeric($detail['em1_quantity'])?$detail['em1_quantity']:0,
											':em1_uom' => isset($detail['em1_uom'])?$detail['em1_uom']:NULL,
											':em1_whscode' => isset($detail['em1_whscode'])?$detail['em1_whscode']:NULL,
											':em1_price' => is_numeric($detail['em1_price'])?$detail['em1_price']:0,
											':em1_vat' => is_numeric($detail['em1_vat'])?$detail['em1_vat']:0,
											':em1_vatsum' => is_numeric($detail['em1_vatsum'])?$detail['em1_vatsum']:0,
											':em1_discount' => is_numeric($detail['em1_discount'])?$detail['em1_discount']:0,
											':em1_linetotal' => is_numeric($detail['em1_linetotal'])?$detail['em1_linetotal']:0,
											':em1_costcode' => isset($detail['em1_costcode'])?$detail['em1_costcode']:NULL,
											':em1_ubusiness' => isset($detail['em1_ubusiness'])?$detail['em1_ubusiness']:NULL,
											':em1_project' => isset($detail['em1_project'])?$detail['em1_project']:NULL,
											':em1_acctcode' => is_numeric($detail['em1_acctcode'])?$detail['em1_acctcode']:0,
											':em1_basetype' => is_numeric($detail['em1_basetype'])?$detail['em1_basetype']:0,
											':em1_doctype' => is_numeric($detail['em1_doctype'])?$detail['em1_doctype']:0,
											':em1_avprice' => is_numeric($detail['em1_avprice'])?$detail['em1_avprice']:0,
											':em1_inventory' => is_numeric($detail['em1_inventory'])?$detail['em1_inventory']:NULL,
											':em1_acciva' => is_numeric($detail['em1_cuentaIva'])?$detail['em1_cuentaIva']:0
									));

									if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
											// Se verifica que el detalle no de error insertando //


									}else{

											// si falla algun insert del detalle de la Entrega de Ventas se devuelven los cambios realizados por la transaccion,
											// se retorna el error y se detiene la ejecucion del codigo restante.
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data' => $resInsert,
													'mensaje'	=> 'No se pudo registrar la Entrega de ventas'
												);

												 $this->response($respuesta);

												 return;
									}
						}

				}else{

						$this->pedeo->trans_rollback();

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la Entrega de ventas'
            );

      }

       $this->response($respuesta);
  }


  //OBTENER Entrega de VentasES
  public function getSalesDel_get(){

        $sqlSelect = self::getColumn('dvem','vem');


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


	//OBTENER Entrega de Ventas POR ID
	public function getSalesDelById_get(){

				$Data = $this->get();

				if(!isset($Data['vem_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM dvem WHERE vem_docentry =:vem_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":vem_docentry" => $Data['vem_docentry']));

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


	//OBTENER Entrega de Ventas DETALLE POR ID
	public function getSalesDelDetail_get(){

				$Data = $this->get();

				if(!isset($Data['em1_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT
											t1.em1_acciva,
											t1.em1_acctcode,
											t1.em1_avprice,
											t1.em1_basetype,
											t1.em1_costcode,
											t1.em1_discount,
											t1.em1_docentry,
											t1.em1_doctype,
											t1.em1_id,
											t1.em1_inventory,
											t1.em1_itemcode,
											t1.em1_itemname,
											t1.em1_linenum,
											t1.em1_linetotal,
											t1.em1_price,
											t1.em1_project,
											t1.em1_quantity - (coalesce(SUM(t3.dv1_quantity),0) + coalesce(SUM(t5.fv1_quantity),0)) em1_quantity,
											t1.em1_ubusiness,
											t1.em1_uom,
											t1.em1_vat,
											t1.em1_vatsum,
											t1.em1_whscode
											from dvem t0
											left join vem1 t1 on t0.vem_docentry = t1.em1_docentry
											left join dvdv t2 on t0.vem_docentry = t2.vdv_baseentry and t0.vem_doctype = t2.vdv_basetype
											left join vdv1 t3 on t2.vdv_docentry = t3.dv1_docentry and t1.em1_itemcode = t3.dv1_itemcode
											left join dvfv t4 on t0.vem_docentry = t4.dvf_baseentry and t0.vem_doctype = t4.dvf_basetype
											left join vfv1 t5 on t4.dvf_docentry = t5.fv1_docentry and t1.em1_itemcode = t5.fv1_itemcode
											 WHERE t1.em1_docentry = :em1_docentry
											 GROUP BY
											t1.em1_acciva,
											t1.em1_acctcode,
											t1.em1_avprice,
											t1.em1_basetype,
											t1.em1_costcode,
											t1.em1_discount,
											t1.em1_docentry,
											t1.em1_doctype,
											t1.em1_id,
											t1.em1_inventory,
											t1.em1_itemcode,
											t1.em1_itemname,
											t1.em1_linenum,
											t1.em1_linetotal,
											t1.em1_price,
											t1.em1_project,
											t1.em1_ubusiness,
											t1.em1_uom,
											t1.em1_vat,
											t1.em1_vatsum,
											t1.em1_whscode,
											t1.em1_quantity";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(':em1_docentry' => $Data['em1_docentry']));

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




	//OBTENER ENTREGA DE VENTAS POR ID DE SOCIO DE NEGOCIO
	public function getSalesDelBySN_get(){

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
											FROM dvem t0
											left join estado_doc t1 on t0.vem_docentry = t1.entry and t0.vem_doctype = t1.tipo
											left join responsestatus t2 on t1.entry = t2.id and t1.tipo = t2.tipo
											where t2.estado = 'Abierto' and t0.vem_cardcode =:vem_cardcode";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(':vem_cardcode' => $Data['dms_card_code']));

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




	private function setAprobacion($Encabezado, $Detalle, $Carpeta, $prefijoe, $prefijod){

		$sqlInsert = "INSERT INTO dpap(pap_series, pap_docnum, pap_docdate, pap_duedate, pap_duedev, pap_pricelist, pap_cardcode,
									pap_cardname, pap_currency, pap_contacid, pap_slpcode, pap_empid, pap_comment, pap_doctotal, pap_baseamnt, pap_taxtotal,
									pap_discprofit, pap_discount, pap_createat, pap_baseentry, pap_basetype, pap_doctype, pap_idadd, pap_adress, pap_paytype,
									pap_attch,pap_createby,pap_origen)VALUES(:pap_series, :pap_docnum, :pap_docdate, :pap_duedate, :pap_duedev, :pap_pricelist, :pap_cardcode, :pap_cardname,
									:pap_currency, :pap_contacid, :pap_slpcode, :pap_empid, :pap_comment, :pap_doctotal, :pap_baseamnt, :pap_taxtotal, :pap_discprofit, :pap_discount,
									:pap_createat, :pap_baseentry, :pap_basetype, :pap_doctype, :pap_idadd, :pap_adress, :pap_paytype, :pap_attch,:pap_createby,:pap_origen)";

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
