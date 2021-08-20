<?php
// ORDEN DE COMPRA
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PurchaseOrder extends REST_Controller {

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

  //CREAR NUEVA COTIZACION
	public function createQuotation_post(){

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

        $sqlInsert = "INSERT INTO dcpo (dpo_series, dpo_docnum, dpo_docdate, dpo_duedate, dpo_duedev, dpo_pricelist, dpo_cardcode,
                      dpo_cardname, dpo_currency, dpo_contacid, dpo_slpcode, dpo_empid, dpo_comment, dpo_doctotal, dpo_baseamnt, dpo_taxtotal,
                      dpo_discprofit, dpo_discount, dpo_createat, dpo_baseentry, dpo_basetype, dpo_doctype, dpo_idadd, dpo_adress, dpo_paytype,
                      dpo_attch,dpo_createby)VALUES(:dpo_series, :dpo_docnum, :dpo_docdate, :dpo_duedate, :dpo_duedev, :dpo_pricelist, :dpo_cardcode, :dpo_cardname,
                      :dpo_currency, :dpo_contacid, :dpo_slpcode, :dpo_empid, :dpo_comment, :dpo_doctotal, :dpo_baseamnt, :dpo_taxtotal, :dpo_discprofit, :dpo_discount,
                      :dpo_createat, :dpo_baseentry, :dpo_basetype, :dpo_doctype, :dpo_idadd, :dpo_adress, :dpo_paytype, :dpo_attch,:dpo_createby)";


				// Se Inicia la transaccion,
				// Todas las consultas de modificacion siguientes
				// aplicaran solo despues que se confirme la transaccion,
				// de lo contrario no se aplicaran los cambios y se devolvera
				// la base de datos a su estado original.

			  $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':dpo_docnum' => is_numeric($Data['dpo_docnum'])?$Data['dpo_docnum']:0,
              ':dpo_series' => is_numeric($Data['dpo_series'])?$Data['dpo_series']:0,
              ':dpo_docdate' => $this->validateDate($Data['dpo_docdate'])?$Data['dpo_docdate']:NULL,
              ':dpo_duedate' => $this->validateDate($Data['dpo_duedate'])?$Data['dpo_duedate']:NULL,
              ':dpo_duedev' => $this->validateDate($Data['dpo_duedev'])?$Data['dpo_duedev']:NULL,
              ':dpo_pricelist' => is_numeric($Data['dpo_pricelist'])?$Data['dpo_pricelist']:0,
              ':dpo_cardcode' => isset($Data['dpo_pricelist'])?$Data['dpo_pricelist']:NULL,
              ':dpo_cardname' => isset($Data['dpo_cardname'])?$Data['dpo_cardname']:NULL,
              ':dpo_currency' => is_numeric($Data['dpo_currency'])?$Data['dpo_currency']:0,
              ':dpo_contacid' => isset($Data['dpo_contacid'])?$Data['dpo_contacid']:NULL,
              ':dpo_slpcode' => is_numeric($Data['dpo_slpcode'])?$Data['dpo_slpcode']:0,
              ':dpo_empid' => is_numeric($Data['dpo_empid'])?$Data['dpo_empid']:0,
              ':dpo_comment' => isset($Data['dpo_comment'])?$Data['dpo_comment']:NULL,
              ':dpo_doctotal' => is_numeric($Data['dpo_doctotal'])?$Data['dpo_doctotal']:0,
              ':dpo_baseamnt' => is_numeric($Data['dpo_baseamnt'])?$Data['dpo_baseamnt']:0,
              ':dpo_taxtotal' => is_numeric($Data['dpo_taxtotal'])?$Data['dpo_taxtotal']:0,
              ':dpo_discprofit' => is_numeric($Data['dpo_discprofit'])?$Data['dpo_discprofit']:0,
              ':dpo_discount' => is_numeric($Data['dpo_discount'])?$Data['dpo_discount']:0,
              ':dpo_createat' => $this->validateDate($Data['dpo_createat'])?$Data['dpo_createat']:NULL,
              ':dpo_baseentry' => is_numeric($Data['dpo_baseentry'])?$Data['dpo_baseentry']:0,
              ':dpo_basetype' => is_numeric($Data['dpo_basetype'])?$Data['dpo_basetype']:0,
              ':dpo_doctype' => is_numeric($Data['dpo_doctype'])?$Data['dpo_doctype']:0,
              ':dpo_idadd' => isset($Data['dpo_idadd'])?$Data['dpo_idadd']:NULL,
              ':dpo_adress' => isset($Data['dpo_adress'])?$Data['dpo_adress']:NULL,
              ':dpo_paytype' => is_numeric($Data['dpo_paytype'])?$Data['dpo_paytype']:0,
							':dpo_createby' => isset($Data['dpo_createby'])?$Data['dpo_createby']:NULL,
              ':dpo_attch' => $this->getUrl(count(trim(($Data['dpo_attch']))) > 0 ? $Data['dpo_attch']:NULL)
						));

        if(is_numeric($resInsert) && $resInsert > 0){


          foreach ($ContenidoDetalle as $key => $detail) {

                $sqlInsertDetail = "INSERT INTO cpo1(po1_docentry, po1_itemcode, po1_itemname, po1_quantity, po1_uom, po1_whscode,
                                    po1_price, po1_vat, po1_vatsum, po1_discount, po1_linetotal, po1_costcode, po1_ubusiness, po1_project,
                                    po1_acctcode, po1_basetype, po1_doctype, po1_avprice, po1_inventory)VALUES(:po1_docentry, :po1_itemcode, :po1_itemname, :po1_quantity,
                                    :po1_uom, :po1_whscode,:po1_price, :po1_vat, :po1_vatsum, :po1_discount, :po1_linetotal, :po1_costcode, :po1_ubusiness, :po1_project,
                                    :po1_acctcode, :po1_basetype, :po1_doctype, :po1_avprice, :po1_inventory)";

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
                        ':po1_inventory' => is_numeric($detail['po1_inventory'])?$detail['po1_inventory']:NULL
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
												'mensaje'	=> 'No se pudo registrar la Orden de Compra'
											);

											 $this->response($respuesta);

											 return;
								}

								//Se aplica el movimiento de inventario
								$sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode, bmi_quantity, bmi_whscode, bmi_createat, bmi_createby, bmy_doctype, bmy_baseentry)
																			 VALUES (:bmi_itemcode, :bmi_quantity, :bmi_whscode, :bmi_createat, :bmi_createby, :bmy_doctype, :bmy_baseentry)";

								$sqlInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(

										 ':bmi_itemcode' => isset($detail['po1_itemcode'])?$detail['po1_itemcode']:NULL,
										 ':bmi_quantity' => is_numeric($detail['po1_quantity'])? $detail['po1_quantity'] * $Data['invtype']:0,
										 ':bmi_whscode'  => isset($detail['po1_whscode'])?$detail['po1_whscode']:NULL,
										 ':bmi_createat' => $this->validateDate($Data['dpo_createat'])?$Data['dpo_createat']:NULL,
										 ':bmi_createby' => isset($Data['dpo_createby'])?$Data['dpo_createby']:NULL,
										 ':bmy_doctype'  => is_numeric($Data['dpo_doctype'])?$Data['dpo_doctype']:0,
										 ':bmy_baseentry' => $resInsert

								));

								if(is_numeric($sqlInserMovimiento) && $sqlInserMovimiento > 0){
										// Se verifica que el detalle no de error insertando //
								}else{

										// si falla algun insert del detalle de la cotizacion se devuelven los cambios realizados por la transaccion,
										// se retorna el error y se detiene la ejecucion del codigo restante.
											$this->pedeo->trans_rollback();

											$respuesta = array(
												'error'   => true,
												'data' => $sqlInserMovimiento,
												'mensaje'	=> 'No se pudo registrar la Orden de Compra'
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
            'mensaje' =>'Orden de Compra registrada con exito'
          );


        }else{
					// Se devuelven los cambios realizados en la transaccion
					// si occurre un error  y se muestra devuelve el error.
							$this->pedeo->trans_rollback();

              $respuesta = array(
                'error'   => true,
                'data' => $resInsert,
                'mensaje'	=> 'No se pudo registrar la Orden de Compra'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR COTIZACION
  public function updateQuotation_post(){

      $Data = $this->post();

			if(!isset($Data['dpo_docentry']) OR !isset($Data['dpo_docnum']) OR
				 !isset($Data['dpo_docdate']) OR !isset($Data['dpo_duedate']) OR
				 !isset($Data['dpo_duedev']) OR !isset($Data['dpo_pricelist']) OR
				 !isset($Data['dpo_cardcode']) OR !isset($Data['dpo_cardname']) OR
				 !isset($Data['dpo_currency']) OR !isset($Data['dpo_contacid']) OR
				 !isset($Data['dpo_slpcode']) OR !isset($Data['dpo_empid']) OR
				 !isset($Data['dpo_comment']) OR !isset($Data['dpo_doctotal']) OR
				 !isset($Data['dpo_baseamnt']) OR !isset($Data['dpo_taxtotal']) OR
				 !isset($Data['dpo_discprofit']) OR !isset($Data['dpo_discount']) OR
				 !isset($Data['dpo_createat']) OR !isset($Data['dpo_baseentry']) OR
				 !isset($Data['dpo_basetype']) OR !isset($Data['dpo_doctype']) OR
				 !isset($Data['dpo_idadd']) OR !isset($Data['dpo_adress']) OR
				 !isset($Data['dpo_paytype']) OR !isset($Data['dpo_attch']) OR
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
            'mensaje' =>'No se encontro el detalle de la Orden de Compra'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }

      $sqlUpdate = "UPDATE dcpo 	SET dpo_docdate=:dpo_docdate,dpo_duedate=:dpo_duedate, dpo_duedev=:dpo_duedev, dpo_pricelist=:dpo_pricelist, dpo_cardcode=:dpo_cardcode,
			  						dpo_cardname=:dpo_cardname, dpo_currency=:dpo_currency, dpo_contacid=:dpo_contacid, dpo_slpcode=:dpo_slpcode,
										dpo_empid=:dpo_empid, dpo_comment=:dpo_comment, dpo_doctotal=:dpo_doctotal, dpo_baseamnt=:dpo_baseamnt,
										dpo_taxtotal=:dpo_taxtotal, dpo_discprofit=:dpo_discprofit, dpo_discount=:dpo_discount, dpo_createat=:dpo_createat,
										dpo_baseentry=:dpo_baseentry, dpo_basetype=:dpo_basetype, dpo_doctype=:dpo_doctype, dpo_idadd=:dpo_idadd,
										dpo_adress=:dpo_adress, dpo_paytype=:dpo_paytype, dpo_attch=:dpo_attch WHERE dpo_docentry=:dpo_docentry";

      $this->pedeo->trans_begin();

      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
							':dpo_docnum' => is_numeric($Data['dpo_docnum'])?$Data['dpo_docnum']:0,
							':dpo_docdate' => $this->validateDate($Data['dpo_docdate'])?$Data['dpo_docdate']:NULL,
							':dpo_duedate' => $this->validateDate($Data['dpo_duedate'])?$Data['dpo_duedate']:NULL,
							':dpo_duedev' => $this->validateDate($Data['dpo_duedev'])?$Data['dpo_duedev']:NULL,
							':dpo_pricelist' => is_numeric($Data['dpo_pricelist'])?$Data['dpo_pricelist']:0,
							':dpo_cardcode' => isset($Data['dpo_pricelist'])?$Data['dpo_pricelist']:NULL,
							':dpo_cardname' => isset($Data['dpo_cardname'])?$Data['dpo_cardname']:NULL,
							':dpo_currency' => is_numeric($Data['dpo_currency'])?$Data['dpo_currency']:0,
							':dpo_contacid' => isset($Data['dpo_contacid'])?$Data['dpo_contacid']:NULL,
							':dpo_slpcode' => is_numeric($Data['dpo_slpcode'])?$Data['dpo_slpcode']:0,
							':dpo_empid' => is_numeric($Data['dpo_empid'])?$Data['dpo_empid']:0,
							':dpo_comment' => isset($Data['dpo_comment'])?$Data['dpo_comment']:NULL,
							':dpo_doctotal' => is_numeric($Data['dpo_doctotal'])?$Data['dpo_doctotal']:0,
							':dpo_baseamnt' => is_numeric($Data['dpo_baseamnt'])?$Data['dpo_baseamnt']:0,
							':dpo_taxtotal' => is_numeric($Data['dpo_taxtotal'])?$Data['dpo_taxtotal']:0,
							':dpo_discprofit' => is_numeric($Data['dpo_discprofit'])?$Data['dpo_discprofit']:0,
							':dpo_discount' => is_numeric($Data['dpo_discount'])?$Data['dpo_discount']:0,
							':dpo_createat' => $this->validateDate($Data['dpo_createat'])?$Data['dpo_createat']:NULL,
							':dpo_baseentry' => is_numeric($Data['dpo_baseentry'])?$Data['dpo_baseentry']:0,
							':dpo_basetype' => is_numeric($Data['dpo_basetype'])?$Data['dpo_basetype']:0,
							':dpo_doctype' => is_numeric($Data['dpo_doctype'])?$Data['dpo_doctype']:0,
							':dpo_idadd' => isset($Data['dpo_idadd'])?$Data['dpo_idadd']:NULL,
							':dpo_adress' => isset($Data['dpo_adress'])?$Data['dpo_adress']:NULL,
							':dpo_paytype' => is_numeric($Data['dpo_paytype'])?$Data['dpo_paytype']:0,
							':dpo_attch' => $this->getUrl(count(trim(($Data['dpo_attch']))) > 0 ? $Data['dpo_attch']:NULL),
							':dpo_docentry' => $Data['dpo_docentry']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

						$this->pedeo->queryTable("DELETE FROM vct1 WHERE po1_docentry=:po1_docentry", array(':po1_docentry' => $Data['dpo_docentry']));

						foreach ($ContenidoDetalle as $key => $detail) {

									$sqlInsertDetail = "INSERT INTO vct1(po1_docentry, po1_itemcode, po1_itemname, po1_quantity, po1_uom, po1_whscode,
																			po1_price, po1_vat, po1_vatsum, po1_discount, po1_linetotal, po1_costcode, po1_ubusiness, po1_project,
																			po1_acctcode, po1_basetype, po1_doctype, po1_avprice, po1_inventory)VALUES(:po1_docentry, :po1_itemcode, :po1_itemname, :po1_quantity,
																			:po1_uom, :po1_whscode,:po1_price, :po1_vat, :po1_vatsum, :po1_discount, :po1_linetotal, :po1_costcode, :po1_ubusiness, :po1_project,
																			:po1_acctcode, :po1_basetype, :po1_doctype, :po1_avprice, :po1_inventory)";

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
											':po1_inventory' => is_numeric($detail['po1_inventory'])?$detail['po1_inventory']:NULL
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
													'mensaje'	=> 'No se pudo registrar la Orden de Compra'
												);

												 $this->response($respuesta);

												 return;
									}
						}


						$this->pedeo->trans_commit();

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Orden de Compra actualizada con exito'
            );


      }else{

						$this->pedeo->trans_rollback();

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la Orden de Compra'
            );

      }

       $this->response($respuesta);
  }


  //OBTENER COTIZACIONES
  public function getQuotation_get(){

        $sqlSelect = " SELECT * FROM dcpo ";

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


	//OBTENER COTIZACION POR ID
	public function getQuotationById_get(){

				$Data = $this->get();

				if(!isset($Data['dpo_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM dcpo  WHERE dpo_docentry =:dpo_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":dpo_docentry" => $Data['dpo_docentry']));

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


	//OBTENER COTIZACION DETALLE POR ID
	public function getQuotationDetail_get(){

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

				$sqlSelect = " SELECT * FROM vct1 WHERE po1_docentry =:po1_docentry";

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








  private function getUrl($data){
      $url = "";

      if ($data == NULL){

        return $url;

      }

      $ruta = '/var/www/html/serpent/assets/img/anexos/';
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

	// private function validateDate($date, $format = 'Y-m-d H:i:s'){
	//     $d = DateTime::createFromFormat($format, $date);
	//     return $d && $d->format($format) == $date;
	// }

	private function validateDate($fecha){
			if(strlen($fecha) == 10 OR strlen($fecha) > 10){
				return true;
			}else{
				return false;
			}
	}




}
