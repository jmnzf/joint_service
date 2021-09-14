<?php
// PEDIDO DE VENTAS (OFERTA PEDIDO)
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class SalesOrder extends REST_Controller {

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

  //CREAR NUEVO PEDIDO
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
              ':vov_currency' => is_numeric($Data['vov_currency'])?$Data['vov_currency']:0,
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


          foreach ($ContenidoDetalle as $key => $detail) {

                $sqlInsertDetail = "INSERT INTO vov1(ov1_docentry, ov1_itemcode, ov1_itemname, ov1_quantity, ov1_uom, ov1_whscode,
                                    ov1_price, ov1_vat, ov1_vatsum, ov1_discount, ov1_linetotal, ov1_costcode, ov1_ubusiness, ov1_project,
                                    ov1_acctcode, ov1_basetype, ov1_doctype, ov1_avprice, ov1_inventory)VALUES(:ov1_docentry, :ov1_itemcode, :ov1_itemname, :ov1_quantity,
                                    :ov1_uom, :ov1_whscode,:ov1_price, :ov1_vat, :ov1_vatsum, :ov1_discount, :ov1_linetotal, :ov1_costcode, :ov1_ubusiness, :ov1_project,
                                    :ov1_acctcode, :ov1_basetype, :ov1_doctype, :ov1_avprice, :ov1_inventory)";

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
                        ':ov1_inventory' => is_numeric($detail['ov1_inventory'])?$detail['ov1_inventory']:NULL
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
												'mensaje'	=> 'No se pudo registrar la cotización'
											);

											 $this->response($respuesta);

											 return;
								}

          }

					//FIN DETALLE PEDIDO



					// Si todo sale bien despues de insertar el detalle de la cotizacion
					// se confirma la trasaccion  para que los cambios apliquen permanentemente
					// en la base de datos y se confirma la operacion exitosa.
					$this->pedeo->trans_commit();

          $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' =>'Cotización registrada con exito'
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

  //ACTUALIZAR PEDIDO
  public function updateSalesOrder_post(){

      $Data = $this->post();

			if(!isset($Data['vov_docentry']) OR !isset($Data['vov_docnum']) OR
				 !isset($Data['vov_docdate']) OR !isset($Data['vov_duedate']) OR
				 !isset($Data['vov_duedev']) OR !isset($Data['vov_pricelist']) OR
				 !isset($Data['vov_cardcode']) OR !isset($Data['vov_cardname']) OR
				 !isset($Data['vov_currency']) OR !isset($Data['vov_contacid']) OR
				 !isset($Data['vov_slpcode']) OR !isset($Data['vov_empid']) OR
				 !isset($Data['vov_comment']) OR !isset($Data['vov_doctotal']) OR
				 !isset($Data['vov_baseamnt']) OR !isset($Data['vov_taxtotal']) OR
				 !isset($Data['vov_discprofit']) OR !isset($Data['vov_discount']) OR
				 !isset($Data['vov_createat']) OR !isset($Data['vov_baseentry']) OR
				 !isset($Data['vov_basetype']) OR !isset($Data['vov_doctype']) OR
				 !isset($Data['vov_idadd']) OR !isset($Data['vov_adress']) OR
				 !isset($Data['vov_paytype']) OR !isset($Data['vov_attch']) OR
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
            'mensaje' =>'No se encontro el detalle de la cotización'
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

      $sqlUpdate = "UPDATE dvov	SET vov_docdate=:vov_docdate,vov_duedate=:vov_duedate, vov_duedev=:vov_duedev, vov_pricelist=:vov_pricelist, vov_cardcode=:vov_cardcode,
			  						vov_cardname=:vov_cardname, vov_currency=:vov_currency, vov_contacid=:vov_contacid, vov_slpcode=:vov_slpcode,
										vov_empid=:vov_empid, vov_comment=:vov_comment, vov_doctotal=:vov_doctotal, vov_baseamnt=:vov_baseamnt,
										vov_taxtotal=:vov_taxtotal, vov_discprofit=:vov_discprofit, vov_discount=:vov_discount, vov_createat=:vov_createat,
										vov_baseentry=:vov_baseentry, vov_basetype=:vov_basetype, vov_doctype=:vov_doctype, vov_idadd=:vov_idadd,
										vov_adress=:vov_adress, vov_paytype=:vov_paytype, vov_attch=:vov_attch WHERE vov_docentry=:vov_docentry";

      $this->pedeo->trans_begin();

      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
							':vov_docnum' => is_numeric($Data['vov_docnum'])?$Data['vov_docnum']:0,
							':vov_docdate' => $this->validateDate($Data['vov_docdate'])?$Data['vov_docdate']:NULL,
							':vov_duedate' => $this->validateDate($Data['vov_duedate'])?$Data['vov_duedate']:NULL,
							':vov_duedev' => $this->validateDate($Data['vov_duedev'])?$Data['vov_duedev']:NULL,
							':vov_pricelist' => is_numeric($Data['vov_pricelist'])?$Data['vov_pricelist']:0,
							':vov_cardcode' => isset($Data['vov_pricelist'])?$Data['vov_pricelist']:NULL,
							':vov_cardname' => isset($Data['vov_cardname'])?$Data['vov_cardname']:NULL,
							':vov_currency' => is_numeric($Data['vov_currency'])?$Data['vov_currency']:0,
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
							':vov_attch' => $this->getUrl(count(trim(($Data['vov_attch']))) > 0 ? $Data['vov_attch']:NULL , $resMainFolder[0]['main_folder']),
							':vov_docentry' => $Data['vov_docentry']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

						$this->pedeo->queryTable("DELETE FROM vct1 WHERE ov1_docentry=:ov1_docentry", array(':ov1_docentry' => $Data['vov_docentry']));

						foreach ($ContenidoDetalle as $key => $detail) {

									$sqlInsertDetail = "INSERT INTO vov1(ov1_docentry, ov1_itemcode, ov1_itemname, ov1_quantity, ov1_uom, ov1_whscode,
																			ov1_price, ov1_vat, ov1_vatsum, ov1_discount, ov1_linetotal, ov1_costcode, ov1_ubusiness, ov1_project,
																			ov1_acctcode, ov1_basetype, ov1_doctype, ov1_avprice, ov1_inventory)VALUES(:ov1_docentry, :ov1_itemcode, :ov1_itemname, :ov1_quantity,
																			:ov1_uom, :ov1_whscode,:ov1_price, :ov1_vat, :ov1_vatsum, :ov1_discount, :ov1_linetotal, :ov1_costcode, :ov1_ubusiness, :ov1_project,
																			:ov1_acctcode, :ov1_basetype, :ov1_doctype, :ov1_avprice, :ov1_inventory)";

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
											':ov1_inventory' => is_numeric($detail['ov1_inventory'])?$detail['ov1_inventory']:NULL
									));

									if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
											// Se verifica que el detalle no de error insertando //
									}else{

											// si falla algun insert del detalle de la cotizacion se devuelven los cambios realizados por la transaccion,
											// se retorna el error y se detiene la ejecucion del codigo restante.
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data'    => $resInsert,
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
              'mensaje' =>'Cotización actualizada con exito'
            );


      }else{

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
  public function getSalesOrder_get(){

        $sqlSelect = " SELECT * FROM dvov";

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
	public function getSalesOrderById_get(){

				$Data = $this->get();

				if(!isset($Data['vov_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM dvov WHERE vov_docentry =:vov_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":vov_docentry" => $Data['vov_docentry']));

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


	//OBTENER DETALLE PEDIDO POR ID
	public function getSalesOrderDetail_get(){

				$Data = $this->get();

				if(!isset($Data['ov1_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM vov1 WHERE ov1_docentry =:ov1_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":ov1_docentry" => $Data['ov1_docentry']));

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



	//OBTENER PEDIDO DE VENTAS POR SOCIO DE NEGOCIO
	public function getSalesOrderBySN_get(){

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

				$sqlSelect = " SELECT * FROM dvov WHERE vov_cardcode =:vov_cardcode";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":vov_cardcode" => $Data['dms_card_code']));

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
