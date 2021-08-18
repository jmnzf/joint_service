<?php
// COTIZACIONES
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Quotation extends REST_Controller {

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
            'mensaje' =>'No se encontro el detalle de la cotización'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }

        $sqlInsert = "INSERT INTO dvct(dvc_docnum, dvc_docdate, dvc_duedate, dvc_duedev, dvc_pricelist, dvc_cardcode,
                      dvc_cardname, dvc_currency, dvc_contacid, dvc_slpcode, dvc_empid, dvc_comment, dvc_doctotal, dvc_baseamnt, dvc_taxtotal,
                      dvc_discprofit, dvc_discount, dvc_createat, dvc_baseentry, dvc_basetype, dvc_doctype, dvc_idadd, dvc_adress, dvc_paytype,
                      dvc_attch)VALUES(:dvc_docnum, :dvc_docdate, :dvc_duedate, :dvc_duedev, :dvc_pricelist, :dvc_cardcode, :dvc_cardname,
                      :dvc_currency, :dvc_contacid, :dvc_slpcode, :dvc_empid, :dvc_comment, :dvc_doctotal, :dvc_baseamnt, :dvc_taxtotal, :dvc_discprofit, :dvc_discount,
                      :dvc_createat, :dvc_baseentry, :dvc_basetype, :dvc_doctype, :dvc_idadd, :dvc_adress, :dvc_paytype, :dvc_attch)";


				// Se Inicia la transaccion,
				// Todas las consultas de modificacion siguientes
				// aplicaran solo despues que se confirme la transaccion,
				// de lo contrario no se aplicaran los cambios y se devolvera
				// la base de datos a su estado original.

			  $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':dvc_docnum' => is_numeric($Data['dvc_docnum'])?$Data['dvc_docnum']:0,
              ':dvc_docdate' => $this->validateDate($Data['dvc_docdate'].' '."00:00:00")?$Data['dvc_docdate']:NULL,
              ':dvc_duedate' => $this->validateDate($Data['dvc_duedate'].' '."00:00:00")?$Data['dvc_duedate']:NULL,
              ':dvc_duedev' => $this->validateDate($Data['dvc_duedev'].' '."00:00:00")?$Data['dvc_duedev']:NULL,
              ':dvc_pricelist' => is_numeric($Data['dvc_pricelist'])?$Data['dvc_pricelist']:0,
              ':dvc_cardcode' => isset($Data['dvc_pricelist'])?$Data['dvc_pricelist']:NULL,
              ':dvc_cardname' => isset($Data['dvc_cardname'])?$Data['dvc_cardname']:NULL,
              ':dvc_currency' => is_numeric($Data['dvc_currency'])?$Data['dvc_currency']:0,
              ':dvc_contacid' => isset($Data['dvc_contacid'])?$Data['dvc_contacid']:NULL,
              ':dvc_slpcode' => is_numeric($Data['dvc_slpcode'])?$Data['dvc_slpcode']:0,
              ':dvc_empid' => is_numeric($Data['dvc_empid'])?$Data['dvc_empid']:0,
              ':dvc_comment' => isset($Data['dvc_comment'])?$Data['dvc_comment']:NULL,
              ':dvc_doctotal' => is_numeric($Data['dvc_doctotal'])?$Data['dvc_doctotal']:0,
              ':dvc_baseamnt' => is_numeric($Data['dvc_baseamnt'])?$Data['dvc_baseamnt']:0,
              ':dvc_taxtotal' => is_numeric($Data['dvc_taxtotal'])?$Data['dvc_taxtotal']:0,
              ':dvc_discprofit' => is_numeric($Data['dvc_discprofit'])?$Data['dvc_discprofit']:0,
              ':dvc_discount' => is_numeric($Data['dvc_discount'])?$Data['dvc_discount']:0,
              ':dvc_createat' => $this->validateDate($Data['dvc_createat'])?$Data['dvc_createat']:NULL,
              ':dvc_baseentry' => is_numeric($Data['dvc_baseentry'])?$Data['dvc_baseentry']:0,
              ':dvc_basetype' => is_numeric($Data['dvc_basetype'])?$Data['dvc_basetype']:0,
              ':dvc_doctype' => is_numeric($Data['dvc_doctype'])?$Data['dvc_doctype']:0,
              ':dvc_idadd' => isset($Data['dvc_idadd'])?$Data['dvc_idadd']:NULL,
              ':dvc_adress' => isset($Data['dvc_adress'])?$Data['dvc_adress']:NULL,
              ':dvc_paytype' => is_numeric($Data['dvc_paytype'])?$Data['dvc_paytype']:0,
              ':dvc_attch' => $this->getUrl(count(trim(($Data['dvc_attch']))) > 0 ? $Data['dvc_attch']:NULL)
						));

        if(is_numeric($resInsert) && $resInsert > 0){


          foreach ($ContenidoDetalle as $key => $detail) {

                $sqlInsertDetail = "INSERT INTO vct1(vc1_docentry, vc1_itemcode, vc1_itemname, vc1_quantity, vc1_uom, vc1_whscode,
                                    vc1_price, vc1_vat, vc1_vatsum, vc1_discount, vc1_linetotal, vc1_costcode, vc1_ubusiness, vc1_project,
                                    vc1_acctcode, vc1_basetype, vc1_doctype, vc1_avprice, vc1_inventory)VALUES(:vc1_docentry, :vc1_itemcode, :vc1_itemname, :vc1_quantity,
                                    :vc1_uom, :vc1_whscode,:vc1_price, :vc1_vat, :vc1_vatsum, :vc1_discount, :vc1_linetotal, :vc1_costcode, :vc1_ubusiness, :vc1_project,
                                    :vc1_acctcode, :vc1_basetype, :vc1_doctype, :vc1_avprice, :vc1_inventory)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':vc1_docentry' => $resInsert,
                        ':vc1_itemcode' => isset($detail['vc1_itemcode'])?$detail['vc1_itemcode']:NULL,
                        ':vc1_itemname' => isset($detail['vc1_itemname'])?$detail['vc1_itemname']:NULL,
                        ':vc1_quantity' => is_numeric($detail['vc1_quantity'])?$detail['vc1_quantity']:0,
                        ':vc1_uom' => isset($detail['vc1_uom'])?$detail['vc1_uom']:NULL,
                        ':vc1_whscode' => isset($detail['vc1_whscode'])?$detail['vc1_whscode']:NULL,
                        ':vc1_price' => is_numeric($detail['vc1_price'])?$detail['vc1_price']:0,
                        ':vc1_vat' => is_numeric($detail['vc1_vat'])?$detail['vc1_vat']:0,
                        ':vc1_vatsum' => is_numeric($detail['vc1_vatsum'])?$detail['vc1_vatsum']:0,
                        ':vc1_discount' => is_numeric($detail['vc1_discount'])?$detail['vc1_discount']:0,
                        ':vc1_linetotal' => is_numeric($detail['vc1_linetotal'])?$detail['vc1_linetotal']:0,
                        ':vc1_costcode' => isset($detail['vc1_costcode'])?$detail['vc1_costcode']:NULL,
                        ':vc1_ubusiness' => isset($detail['vc1_ubusiness'])?$detail['vc1_ubusiness']:NULL,
                        ':vc1_project' => isset($detail['vc1_project'])?$detail['vc1_project']:NULL,
                        ':vc1_acctcode' => is_numeric($detail['vc1_acctcode'])?$detail['vc1_acctcode']:0,
                        ':vc1_basetype' => is_numeric($detail['vc1_basetype'])?$detail['vc1_basetype']:0,
                        ':vc1_doctype' => is_numeric($detail['vc1_doctype'])?$detail['vc1_doctype']:0,
                        ':vc1_avprice' => is_numeric($detail['vc1_avprice'])?$detail['vc1_avprice']:0,
                        ':vc1_inventory' => is_numeric($detail['vc1_inventory'])?$detail['vc1_inventory']:NULL
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

  //ACTUALIZAR COTIZACION
  public function updateQuotation_post(){

      $Data = $this->post();

			if(!isset($Data['dvc_docentry']) OR !isset($Data['dvc_docnum']) OR
				 !isset($Data['dvc_docdate']) OR !isset($Data['dvc_duedate']) OR
				 !isset($Data['dvc_duedev']) OR !isset($Data['dvc_pricelist']) OR
				 !isset($Data['dvc_cardcode']) OR !isset($Data['dvc_cardname']) OR
				 !isset($Data['dvc_currency']) OR !isset($Data['dvc_contacid']) OR
				 !isset($Data['dvc_slpcode']) OR !isset($Data['dvc_empid']) OR
				 !isset($Data['dvc_comment']) OR !isset($Data['dvc_doctotal']) OR
				 !isset($Data['dvc_baseamnt']) OR !isset($Data['dvc_taxtotal']) OR
				 !isset($Data['dvc_discprofit']) OR !isset($Data['dvc_discount']) OR
				 !isset($Data['dvc_createat']) OR !isset($Data['dvc_baseentry']) OR
				 !isset($Data['dvc_basetype']) OR !isset($Data['dvc_doctype']) OR
				 !isset($Data['dvc_idadd']) OR !isset($Data['dvc_adress']) OR
				 !isset($Data['dvc_paytype']) OR !isset($Data['dvc_attch']) OR
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

      $sqlUpdate = "UPDATE dvct	SET dvc_docdate=:dvc_docdate,dvc_duedate=:dvc_duedate, dvc_duedev=:dvc_duedev, dvc_pricelist=:dvc_pricelist, dvc_cardcode=:dvc_cardcode,
			  						dvc_cardname=:dvc_cardname, dvc_currency=:dvc_currency, dvc_contacid=:dvc_contacid, dvc_slpcode=:dvc_slpcode,
										dvc_empid=:dvc_empid, dvc_comment=:dvc_comment, dvc_doctotal=:dvc_doctotal, dvc_baseamnt=:dvc_baseamnt,
										dvc_taxtotal=:dvc_taxtotal, dvc_discprofit=:dvc_discprofit, dvc_discount=:dvc_discount, dvc_createat=:dvc_createat,
										dvc_baseentry=:dvc_baseentry, dvc_basetype=:dvc_basetype, dvc_doctype=:dvc_doctype, dvc_idadd=:dvc_idadd,
										dvc_adress=:dvc_adress, dvc_paytype=:dvc_paytype, dvc_attch=:dvc_attch WHERE dvc_docentry=:dvc_docentry";

      $this->pedeo->trans_begin();

      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
							':dvc_docnum' => is_numeric($Data['dvc_docnum'])?$Data['dvc_docnum']:0,
							':dvc_docdate' => $this->validateDate($Data['dvc_docdate'.' '."00:00:00"])?$Data['dvc_docdate']:NULL,
							':dvc_duedate' => $this->validateDate($Data['dvc_duedate'.' '."00:00:00"])?$Data['dvc_duedate']:NULL,
							':dvc_duedev' => $this->validateDate($Data['dvc_duedev'.' '."00:00:00"])?$Data['dvc_duedev']:NULL,
							':dvc_pricelist' => is_numeric($Data['dvc_pricelist'])?$Data['dvc_pricelist']:0,
							':dvc_cardcode' => isset($Data['dvc_pricelist'])?$Data['dvc_pricelist']:NULL,
							':dvc_cardname' => isset($Data['dvc_cardname'])?$Data['dvc_cardname']:NULL,
							':dvc_currency' => is_numeric($Data['dvc_currency'])?$Data['dvc_currency']:0,
							':dvc_contacid' => isset($Data['dvc_contacid'])?$Data['dvc_contacid']:NULL,
							':dvc_slpcode' => is_numeric($Data['dvc_slpcode'])?$Data['dvc_slpcode']:0,
							':dvc_empid' => is_numeric($Data['dvc_empid'])?$Data['dvc_empid']:0,
							':dvc_comment' => isset($Data['dvc_comment'])?$Data['dvc_comment']:NULL,
							':dvc_doctotal' => is_numeric($Data['dvc_doctotal'])?$Data['dvc_doctotal']:0,
							':dvc_baseamnt' => is_numeric($Data['dvc_baseamnt'])?$Data['dvc_baseamnt']:0,
							':dvc_taxtotal' => is_numeric($Data['dvc_taxtotal'])?$Data['dvc_taxtotal']:0,
							':dvc_discprofit' => is_numeric($Data['dvc_discprofit'])?$Data['dvc_discprofit']:0,
							':dvc_discount' => is_numeric($Data['dvc_discount'])?$Data['dvc_discount']:0,
							':dvc_createat' => $this->validateDate($Data['dvc_createat'])?$Data['dvc_createat']:NULL,
							':dvc_baseentry' => is_numeric($Data['dvc_baseentry'])?$Data['dvc_baseentry']:0,
							':dvc_basetype' => is_numeric($Data['dvc_basetype'])?$Data['dvc_basetype']:0,
							':dvc_doctype' => is_numeric($Data['dvc_doctype'])?$Data['dvc_doctype']:0,
							':dvc_idadd' => isset($Data['dvc_idadd'])?$Data['dvc_idadd']:NULL,
							':dvc_adress' => isset($Data['dvc_adress'])?$Data['dvc_adress']:NULL,
							':dvc_paytype' => is_numeric($Data['dvc_paytype'])?$Data['dvc_paytype']:0,
							':dvc_attch' => $this->getUrl(count(trim(($Data['dvc_attch']))) > 0 ? $Data['dvc_attch']:NULL),
							':dvc_docentry' => $Data['dvc_docentry']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

						$this->pedeo->queryTable("DELETE FROM vct1 WHERE vc1_docentry=:vc1_docentry", array(':vc1_docentry' => $Data['dvc_docentry']));

						foreach ($ContenidoDetalle as $key => $detail) {

									$sqlInsertDetail = "INSERT INTO vct1(vc1_docentry, vc1_itemcode, vc1_itemname, vc1_quantity, vc1_uom, vc1_whscode,
																			vc1_price, vc1_vat, vc1_vatsum, vc1_discount, vc1_linetotal, vc1_costcode, vc1_ubusiness, vc1_project,
																			vc1_acctcode, vc1_basetype, vc1_doctype, vc1_avprice, vc1_inventory)VALUES(:vc1_docentry, :vc1_itemcode, :vc1_itemname, :vc1_quantity,
																			:vc1_uom, :vc1_whscode,:vc1_price, :vc1_vat, :vc1_vatsum, :vc1_discount, :vc1_linetotal, :vc1_costcode, :vc1_ubusiness, :vc1_project,
																			:vc1_acctcode, :vc1_basetype, :vc1_doctype, :vc1_avprice, :vc1_inventory)";

									$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
											':vc1_docentry' => $resInsert,
											':vc1_itemcode' => isset($detail['vc1_itemcode'])?$detail['vc1_itemcode']:NULL,
											':vc1_itemname' => isset($detail['vc1_itemname'])?$detail['vc1_itemname']:NULL,
											':vc1_quantity' => is_numeric($detail['vc1_quantity'])?$detail['vc1_quantity']:0,
											':vc1_uom' => isset($detail['vc1_uom'])?$detail['vc1_uom']:NULL,
											':vc1_whscode' => isset($detail['vc1_whscode'])?$detail['vc1_whscode']:NULL,
											':vc1_price' => is_numeric($detail['vc1_price'])?$detail['vc1_price']:0,
											':vc1_vat' => is_numeric($detail['vc1_vat'])?$detail['vc1_vat']:0,
											':vc1_vatsum' => is_numeric($detail['vc1_vatsum'])?$detail['vc1_vatsum']:0,
											':vc1_discount' => is_numeric($detail['vc1_discount'])?$detail['vc1_discount']:0,
											':vc1_linetotal' => is_numeric($detail['vc1_linetotal'])?$detail['vc1_linetotal']:0,
											':vc1_costcode' => isset($detail['vc1_costcode'])?$detail['vc1_costcode']:NULL,
											':vc1_ubusiness' => isset($detail['vc1_ubusiness'])?$detail['vc1_ubusiness']:NULL,
											':vc1_project' => isset($detail['vc1_project'])?$detail['vc1_project']:NULL,
											':vc1_acctcode' => is_numeric($detail['vc1_acctcode'])?$detail['vc1_acctcode']:0,
											':vc1_basetype' => is_numeric($detail['vc1_basetype'])?$detail['vc1_basetype']:0,
											':vc1_doctype' => is_numeric($detail['vc1_doctype'])?$detail['vc1_doctype']:0,
											':vc1_avprice' => is_numeric($detail['vc1_avprice'])?$detail['vc1_avprice']:0,
											':vc1_inventory' => is_numeric($detail['vc1_inventory'])?$detail['vc1_inventory']:NULL
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
  public function getQuotation_get(){

        $sqlSelect = " SELECT * FROM dvct";

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

				if(!isset($Data['dvc_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM dvct WHERE dvc_docentry =:dvc_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":dvc_docentry" => $Data['dvc_docentry']));

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

				if(!isset($Data['vc1_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM vct1 WHERE vc1_docentry =:vc1_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":vc1_docentry" => $Data['vc1_docentry']));

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

	private function validateDate($date, $format = 'Y-m-d H:i:s'){
	    $d = DateTime::createFromFormat($format, $date);
	    return $d && $d->format($format) == $date;
	}




}
