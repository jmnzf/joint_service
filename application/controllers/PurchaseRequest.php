<?php
// PurchaseRequest DE COMPRAS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PurchaseRequest extends REST_Controller {

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

  //CREAR NUEVA solicitud DE compras
	public function createPurchaseRequest_post(){

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


			// Se globaliza la variable sqlDetalleAsiento
			$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
													ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
													ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
													ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref)VALUES (:ac1_trans_id, :ac1_account,
													:ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
													:ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
													:ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
													:ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref)";


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
            'mensaje' =>'No se encontro el detalle de la solicitud de compras'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }
				//BUSCANDO LA NUMERACION DEL DOCUMENTO
			  $sqlNumeracion = " SELECT pgs_nextnum,pgs_last_num FROM  pgdn WHERE pgs_id = :pgs_id";

				$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['csc_series']));

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


				$sqlMonedaSys = "SELECT tasa.tsa_value
													FROM  pgec
													INNER JOIN tasa
													ON trim(tasa.tsa_currd) = trim(pgec.pgm_symbol)
													WHERE pgec.pgm_system = :pgm_system AND tasa.tsa_date = :tsa_date";

				$resMonedaSys = $this->pedeo->queryTable($sqlMonedaSys, array(':pgm_system' => 1, ':tsa_date' => $Data['csc_docdate']));

				if(isset($resMonedaSys[0])){

				}else{
						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encontro la moneda de sistema para el documento'
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

        $sqlInsert = "INSERT INTO dcsc(csc_series, csc_docnum, csc_docdate, csc_duedate, csc_duedev, csc_pricelist, csc_cardcode,
                      csc_cardname, csc_currency, csc_contacid, csc_slpcode, csc_empid, csc_comment, csc_doctotal, csc_baseamnt, csc_taxtotal,
                      csc_discprofit, csc_discount, csc_createat, csc_baseentry, csc_basetype, csc_doctype, csc_idadd, csc_adress, csc_paytype,
                      csc_attch,csc_createby)VALUES(:csc_series, :csc_docnum, :csc_docdate, :csc_duedate, :csc_duedev, :csc_pricelist, :csc_cardcode, :csc_cardname,
                      :csc_currency, :csc_contacid, :csc_slpcode, :csc_empid, :csc_comment, :csc_doctotal, :csc_baseamnt, :csc_taxtotal, :csc_discprofit, :csc_discount,
                      :csc_createat, :csc_baseentry, :csc_basetype, :csc_doctype, :csc_idadd, :csc_adress, :csc_paytype, :csc_attch,:csc_createby)";


				// Se Inicia la transaccion,
				// Todas las consultas de modificacion siguientes
				// aplicaran solo despues que se confirme la transaccion,
				// de lo contrario no se aplicaran los cambios y se devolvera
				// la base de datos a su estado original.

			  $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':csc_docnum' => $DocNumVerificado,
              ':csc_series' => is_numeric($Data['csc_series'])?$Data['csc_series']:0,
              ':csc_docdate' => $this->validateDate($Data['csc_docdate'])?$Data['csc_docdate']:NULL,
              ':csc_duedate' => $this->validateDate($Data['csc_duedate'])?$Data['csc_duedate']:NULL,
              ':csc_duedev' => $this->validateDate($Data['csc_duedev'])?$Data['csc_duedev']:NULL,
              ':csc_pricelist' => is_numeric($Data['csc_pricelist'])?$Data['csc_pricelist']:0,
              ':csc_cardcode' => isset($Data['csc_cardcode'])?$Data['csc_cardcode']:NULL,
              ':csc_cardname' => isset($Data['csc_cardname'])?$Data['csc_cardname']:NULL,
              ':csc_currency' => isset($Data['csc_currency'])?$Data['csc_currency']:NULL,
              ':csc_contacid' => isset($Data['csc_contacid'])?$Data['csc_contacid']:NULL,
              ':csc_slpcode' => is_numeric($Data['csc_slpcode'])?$Data['csc_slpcode']:0,
              ':csc_empid' => is_numeric($Data['csc_empid'])?$Data['csc_empid']:0,
              ':csc_comment' => isset($Data['csc_comment'])?$Data['csc_comment']:NULL,
              ':csc_doctotal' => is_numeric($Data['csc_doctotal'])?$Data['csc_doctotal']:0,
              ':csc_baseamnt' => is_numeric($Data['csc_baseamnt'])?$Data['csc_baseamnt']:0,
              ':csc_taxtotal' => is_numeric($Data['csc_taxtotal'])?$Data['csc_taxtotal']:0,
              ':csc_discprofit' => is_numeric($Data['csc_discprofit'])?$Data['csc_discprofit']:0,
              ':csc_discount' => is_numeric($Data['csc_discount'])?$Data['csc_discount']:0,
              ':csc_createat' => $this->validateDate($Data['csc_createat'])?$Data['csc_createat']:NULL,
              ':csc_baseentry' => is_numeric($Data['csc_baseentry'])?$Data['csc_baseentry']:0,
              ':csc_basetype' => is_numeric($Data['csc_basetype'])?$Data['csc_basetype']:0,
              ':csc_doctype' => is_numeric($Data['csc_doctype'])?$Data['csc_doctype']:0,
              ':csc_idadd' => isset($Data['csc_idadd'])?$Data['csc_idadd']:NULL,
              ':csc_adress' => isset($Data['csc_adress'])?$Data['csc_adress']:NULL,
              ':csc_paytype' => is_numeric($Data['csc_paytype'])?$Data['csc_paytype']:0,
							':csc_createby' => isset($Data['csc_createby'])?$Data['csc_createby']:NULL,
              ':csc_attch' => $this->getUrl(count(trim(($Data['csc_attch']))) > 0 ? $Data['csc_attch']:NULL, $resMainFolder[0]['main_folder'])
						));

        if(is_numeric($resInsert) && $resInsert > 0){

					// Se actualiza la serie de la numeracion del documento

					$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
					$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
							':pgs_nextnum' => $DocNumVerificado,
							':pgs_id'      => $Data['csc_series']
					));


					if(is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1){

					}else{
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
										':bed_status' => 1, //ESTADO CERRADO
										':bed_createby' => $Data['csc_createby'],
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
									'mensaje'	=> 'No se pudo registrar la solicitud de compras',
									'proceso' => 'Insertar estado documento'
								);


								$this->response($respuesta);

								return;
					}

					//FIN PROCESO ESTADO DEL DOCUMENTO


          foreach ($ContenidoDetalle as $key => $detail) {

                $sqlInsertDetail = "INSERT INTO csc1(sc1_docentry, sc1_itemcode, sc1_itemname, sc1_quantity, sc1_uom, sc1_whscode,
                                    sc1_price, sc1_vat, sc1_vatsum, sc1_discount, sc1_linetotal, sc1_costcode, sc1_ubusiness, sc1_project,
                                    sc1_acctcode, sc1_basetype, sc1_doctype, sc1_avprice, sc1_inventory, sc1_acciva)VALUES(:sc1_docentry, :sc1_itemcode, :sc1_itemname, :sc1_quantity,
                                    :sc1_uom, :sc1_whscode,:sc1_price, :sc1_vat, :sc1_vatsum, :sc1_discount, :sc1_linetotal, :sc1_costcode, :sc1_ubusiness, :sc1_project,
                                    :sc1_acctcode, :sc1_basetype, :sc1_doctype, :sc1_avprice, :sc1_inventory, :sc1_acciva)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':sc1_docentry' => $resInsert,
                        ':sc1_itemcode' => isset($detail['sc1_itemcode'])?$detail['sc1_itemcode']:NULL,
                        ':sc1_itemname' => isset($detail['sc1_itemname'])?$detail['sc1_itemname']:NULL,
                        ':sc1_quantity' => is_numeric($detail['sc1_quantity'])?$detail['sc1_quantity']:0,
                        ':sc1_uom' => isset($detail['sc1_uom'])?$detail['sc1_uom']:NULL,
                        ':sc1_whscode' => isset($detail['sc1_whscode'])?$detail['sc1_whscode']:NULL,
                        ':sc1_price' => is_numeric($detail['sc1_price'])?$detail['sc1_price']:0,
                        ':sc1_vat' => is_numeric($detail['sc1_vat'])?$detail['sc1_vat']:0,
                        ':sc1_vatsum' => is_numeric($detail['sc1_vatsum'])?$detail['sc1_vatsum']:0,
                        ':sc1_discount' => is_numeric($detail['sc1_discount'])?$detail['sc1_discount']:0,
                        ':sc1_linetotal' => is_numeric($detail['sc1_linetotal'])?$detail['sc1_linetotal']:0,
                        ':sc1_costcode' => isset($detail['sc1_costcode'])?$detail['sc1_costcode']:NULL,
                        ':sc1_ubusiness' => isset($detail['sc1_ubusiness'])?$detail['sc1_ubusiness']:NULL,
                        ':sc1_project' => isset($detail['sc1_project'])?$detail['sc1_project']:NULL,
                        ':sc1_acctcode' => is_numeric($detail['sc1_acctcode'])?$detail['sc1_acctcode']:0,
                        ':sc1_basetype' => is_numeric($detail['sc1_basetype'])?$detail['sc1_basetype']:0,
                        ':sc1_doctype' => is_numeric($detail['sc1_doctype'])?$detail['sc1_doctype']:0,
                        ':sc1_avprice' => is_numeric($detail['sc1_avprice'])?$detail['sc1_avprice']:0,
                        ':sc1_inventory' => is_numeric($detail['sc1_inventory'])?$detail['sc1_inventory']:NULL,
												':sc1_acciva'  => is_numeric($detail['sc1_cuentaIva'])?$detail['sc1_cuentaIva']:0,
                ));

								if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
										// Se verifica que el detalle no de error insertando //
								}else{

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
            'mensaje' =>'Solicitud de compras registrada con exito'
          );


        }else{
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
  public function updatePurchaseRequest_post(){

      $Data = $this->post();

			if(!isset($Data['csc_docentry']) OR !isset($Data['csc_docnum']) OR
				 !isset($Data['csc_docdate']) OR !isset($Data['csc_duedate']) OR
				 !isset($Data['csc_duedev']) OR !isset($Data['csc_pricelist']) OR
				 !isset($Data['csc_cardcode']) OR !isset($Data['csc_cardname']) OR
				 !isset($Data['csc_currency']) OR !isset($Data['csc_contacid']) OR
				 !isset($Data['csc_slpcode']) OR !isset($Data['csc_empid']) OR
				 !isset($Data['csc_comment']) OR !isset($Data['csc_doctotal']) OR
				 !isset($Data['csc_baseamnt']) OR !isset($Data['csc_taxtotal']) OR
				 !isset($Data['csc_discprofit']) OR !isset($Data['csc_discount']) OR
				 !isset($Data['csc_createat']) OR !isset($Data['csc_baseentry']) OR
				 !isset($Data['csc_basetype']) OR !isset($Data['csc_doctype']) OR
				 !isset($Data['csc_idadd']) OR !isset($Data['csc_adress']) OR
				 !isset($Data['csc_paytype']) OR !isset($Data['csc_attch']) OR
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
            'mensaje' =>'No se encontro el detalle de la solicitud de compras'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }

      $sqlUpdate = "UPDATE dcsc	SET csc_docdate=:csc_docdate,csc_duedate=:csc_duedate, csc_duedev=:csc_duedev, csc_pricelist=:csc_pricelist, csc_cardcode=:csc_cardcode,
			  						csc_cardname=:csc_cardname, csc_currency=:csc_currency, csc_contacid=:csc_contacid, csc_slpcode=:csc_slpcode,
										csc_empid=:csc_empid, csc_comment=:csc_comment, csc_doctotal=:csc_doctotal, csc_baseamnt=:csc_baseamnt,
										csc_taxtotal=:csc_taxtotal, csc_discprofit=:csc_discprofit, csc_discount=:csc_discount, csc_createat=:csc_createat,
										csc_baseentry=:csc_baseentry, csc_basetype=:csc_basetype, csc_doctype=:csc_doctype, csc_idadd=:csc_idadd,
										csc_adress=:csc_adress, csc_paytype=:csc_paytype, csc_attch=:csc_attch WHERE csc_docentry=:csc_docentry";

      $this->pedeo->trans_begin();

      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
							':csc_docnum' => is_numeric($Data['csc_docnum'])?$Data['csc_docnum']:0,
							':csc_docdate' => $this->validateDate($Data['csc_docdate'])?$Data['csc_docdate']:NULL,
							':csc_duedate' => $this->validateDate($Data['csc_duedate'])?$Data['csc_duedate']:NULL,
							':csc_duedev' => $this->validateDate($Data['csc_duedev'])?$Data['csc_duedev']:NULL,
							':csc_pricelist' => is_numeric($Data['csc_pricelist'])?$Data['csc_pricelist']:0,
							':csc_cardcode' => isset($Data['csc_pricelist'])?$Data['csc_pricelist']:NULL,
							':csc_cardname' => isset($Data['csc_cardname'])?$Data['csc_cardname']:NULL,
							':csc_currency' => isset($Data['csc_currency'])?$Data['csc_currency']:NULL,
							':csc_contacid' => isset($Data['csc_contacid'])?$Data['csc_contacid']:NULL,
							':csc_slpcode' => is_numeric($Data['csc_slpcode'])?$Data['csc_slpcode']:0,
							':csc_empid' => is_numeric($Data['csc_empid'])?$Data['csc_empid']:0,
							':csc_comment' => isset($Data['csc_comment'])?$Data['csc_comment']:NULL,
							':csc_doctotal' => is_numeric($Data['csc_doctotal'])?$Data['csc_doctotal']:0,
							':csc_baseamnt' => is_numeric($Data['csc_baseamnt'])?$Data['csc_baseamnt']:0,
							':csc_taxtotal' => is_numeric($Data['csc_taxtotal'])?$Data['csc_taxtotal']:0,
							':csc_discprofit' => is_numeric($Data['csc_discprofit'])?$Data['csc_discprofit']:0,
							':csc_discount' => is_numeric($Data['csc_discount'])?$Data['csc_discount']:0,
							':csc_createat' => $this->validateDate($Data['csc_createat'])?$Data['csc_createat']:NULL,
							':csc_baseentry' => is_numeric($Data['csc_baseentry'])?$Data['csc_baseentry']:0,
							':csc_basetype' => is_numeric($Data['csc_basetype'])?$Data['csc_basetype']:0,
							':csc_doctype' => is_numeric($Data['csc_doctype'])?$Data['csc_doctype']:0,
							':csc_idadd' => isset($Data['csc_idadd'])?$Data['csc_idadd']:NULL,
							':csc_adress' => isset($Data['csc_adress'])?$Data['csc_adress']:NULL,
							':csc_paytype' => is_numeric($Data['csc_paytype'])?$Data['csc_paytype']:0,
							':csc_attch' => $this->getUrl(count(trim(($Data['csc_attch']))) > 0 ? $Data['csc_attch']:NULL, $resMainFolder[0]['main_folder']),
							':csc_docentry' => $Data['csc_docentry']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

						$this->pedeo->queryTable("DELETE FROM csc1 WHERE sc1_docentry=:sc1_docentry", array(':sc1_docentry' => $Data['csc_docentry']));

						foreach ($ContenidoDetalle as $key => $detail) {

									$sqlInsertDetail = "INSERT INTO csc1(sc1_docentry, sc1_itemcode, sc1_itemname, sc1_quantity, sc1_uom, sc1_whscode,
																			sc1_price, sc1_vat, sc1_vatsum, sc1_discount, sc1_linetotal, sc1_costcode, sc1_ubusiness, sc1_project,
																			sc1_acctcode, sc1_basetype, sc1_doctype, sc1_avprice, sc1_inventory, sc1_acciva)VALUES(:sc1_docentry, :sc1_itemcode, :sc1_itemname, :sc1_quantity,
																			:sc1_uom, :sc1_whscode,:sc1_price, :sc1_vat, :sc1_vatsum, :sc1_discount, :sc1_linetotal, :sc1_costcode, :sc1_ubusiness, :sc1_project,
																			:sc1_acctcode, :sc1_basetype, :sc1_doctype, :sc1_avprice, :sc1_inventory, :sc1_acciva)";

									$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
											':sc1_docentry' => $Data['csc_docentry'],
											':sc1_itemcode' => isset($detail['sc1_itemcode'])?$detail['sc1_itemcode']:NULL,
											':sc1_itemname' => isset($detail['sc1_itemname'])?$detail['sc1_itemname']:NULL,
											':sc1_quantity' => is_numeric($detail['sc1_quantity'])?$detail['sc1_quantity']:0,
											':sc1_uom' => isset($detail['sc1_uom'])?$detail['sc1_uom']:NULL,
											':sc1_whscode' => isset($detail['sc1_whscode'])?$detail['sc1_whscode']:NULL,
											':sc1_price' => is_numeric($detail['sc1_price'])?$detail['sc1_price']:0,
											':sc1_vat' => is_numeric($detail['sc1_vat'])?$detail['sc1_vat']:0,
											':sc1_vatsum' => is_numeric($detail['sc1_vatsum'])?$detail['sc1_vatsum']:0,
											':sc1_discount' => is_numeric($detail['sc1_discount'])?$detail['sc1_discount']:0,
											':sc1_linetotal' => is_numeric($detail['sc1_linetotal'])?$detail['sc1_linetotal']:0,
											':sc1_costcode' => isset($detail['sc1_costcode'])?$detail['sc1_costcode']:NULL,
											':sc1_ubusiness' => isset($detail['sc1_ubusiness'])?$detail['sc1_ubusiness']:NULL,
											':sc1_project' => isset($detail['sc1_project'])?$detail['sc1_project']:NULL,
											':sc1_acctcode' => is_numeric($detail['sc1_acctcode'])?$detail['sc1_acctcode']:0,
											':sc1_basetype' => is_numeric($detail['sc1_basetype'])?$detail['sc1_basetype']:0,
											':sc1_doctype' => is_numeric($detail['sc1_doctype'])?$detail['sc1_doctype']:0,
											':sc1_avprice' => is_numeric($detail['sc1_avprice'])?$detail['sc1_avprice']:0,
											':sc1_inventory' => is_numeric($detail['sc1_inventory'])?$detail['sc1_inventory']:NULL,
											':sc1_acciva' => is_numeric($detail['sc1_cuentaIva'])?$detail['sc1_cuentaIva']:0
									));

									if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
											// Se verifica que el detalle no de error insertando //
									}else{

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
              'mensaje' =>'Solicitud de compras actualizada con exito'
            );


      }else{

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
  public function getPurchaseRequest_get(){

        $sqlSelect = self::getColumn('dcsc','csc');
			

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


	//OBTENER solicitud de compras POR ID
	public function getPurchaseRequestById_get(){

				$Data = $this->get();

				if(!isset($Data['csc_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM dcsc WHERE csc_docentry =:csc_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":csc_docentry" => $Data['csc_docentry']));

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


	//OBTENER solicitud de compras DETALLE POR ID
	public function getPurchaseRequestDetail_get(){

				$Data = $this->get();

				if(!isset($Data['sc1_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM csc1 WHERE sc1_docentry =:sc1_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":sc1_docentry" => $Data['sc1_docentry']));

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




	//OBTENER solicitud DE VENTA POR ID SOCIO DE NEGOCIO
	public function getPurchaseRequestBySN_get(){

				$Data = $this->get();

				if(!isset($Data['csc_slpcode'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM dcsc WHERE csc_slpcode =:csc_slpcode";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":csc_slpcode" => $Data['csc_slpcode']));

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
