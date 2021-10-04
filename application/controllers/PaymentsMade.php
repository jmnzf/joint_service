<?php
// COTIZACIONES
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PaymentsMade extends REST_Controller {

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

	// Obtener numeracion de documento
  public function getPaymentsMade_get(){
    //
    $sqlSelect = "SELECT * FROM gbpe";

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

  //CREAR NUEVO PAGO
	public function createPaymentsMade_post(){

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
            'mensaje' =>'No se encontro el detalle del pago'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }
				//BUSCANDO LA NUMERACION DEL DOCUMENTO
			  $sqlNumeracion = " SELECT pgs_nextnum,pgs_last_num FROM  pgdn WHERE pgs_id = :pgs_id";

				$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['bpe_series']));

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

        $sqlInsert = "INSERT INTO
                            	gbpe (bpe_cardcode,bpe_doctype,bpe_cardname,bpe_address,bpe_perscontact,bpe_series,bpe_docnum,bpe_docdate,bpe_taxdate,bpe_ref,bpe_transid,
                                    bpe_comments,bpe_memo,bpe_acctransfer,bpe_datetransfer,bpe_reftransfer,bpe_doctotal,bpe_vlrpaid,bpe_project,bpe_createby,
                                    bpe_createat,bpe_payment)
                      VALUES (:bpe_cardcode,:bpe_doctype,:bpe_cardname,:bpe_address,:bpe_perscontact,:bpe_series,:bpe_docnum,:bpe_docdate,:bpe_taxdate,:bpe_ref,:bpe_transid,
                              :bpe_comments,:bpe_memo,:bpe_acctransfer,:bpe_datetransfer,:bpe_reftransfer,:bpe_doctotal,:bpe_vlrpaid,:bpe_project,:bpe_createby,
                              :bpe_createat,:bpe_payment)";


				// Se Inicia la transaccion,
				// Todas las consultas de modificacion siguientes
				// aplicaran solo despues que se confirme la transaccion,
				// de lo contrario no se aplicaran los cambios y se devolvera
				// la base de datos a su estado original.

			  $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':bpe_cardcode' => isset($Data['bpe_cardcode'])?$Data['bpe_cardcode']:NULL,
							':bpe_doctype' => is_numeric($Data['bpe_doctype'])?$Data['bpe_doctype']:0,
              ':bpe_cardname' => isset($Data['bpe_cardname'])?$Data['bpe_cardname']:NULL,
              ':bpe_address' => isset($Data['bpe_address'])?$Data['bpe_address']:NULL,
              ':bpe_perscontact' => is_numeric($Data['bpe_perscontact'])?$Data['bpe_perscontact']:0,
              ':bpe_series' => is_numeric($Data['bpe_series'])?$Data['bpe_series']:0,
              ':bpe_docnum' => $DocNumVerificado,
              ':bpe_docdate' => $this->validateDate($Data['bpe_docdate'])?$Data['bpe_docdate']:NULL,
              ':bpe_taxdate' => $this->validateDate($Data['bpe_taxdate'])?$Data['bpe_taxdate']:NULL,
              ':bpe_ref' => isset($Data['bpe_ref'])?$Data['bpe_ref']:NULL,
              ':bpe_transid' => is_numeric($Data['bpe_transid'])?$Data['bpe_transid']:0,
              ':bpe_comments' => isset($Data['bpe_comments'])?$Data['bpe_comments']:NULL,
              ':bpe_memo' => isset($Data['bpe_memo'])?$Data['bpe_memo']:NULL,
              ':bpe_acctransfer' => isset($Data['bpe_acctransfer'])?$Data['bpe_acctransfer']:NULL,
              ':bpe_datetransfer' => $this->validateDate($Data['bpe_datetransfer'])?$Data['bpe_datetransfer']:NULL,
              ':bpe_reftransfer' => isset($Data['bpe_reftransfer'])?$Data['bpe_reftransfer']:NULL,
              ':bpe_doctotal' => is_numeric($Data['bpe_doctotal'])?$Data['bpe_doctotal']:0,
              ':bpe_vlrpaid' => is_numeric($Data['bpe_vlrpaid'])?$Data['bpe_vlrpaid']:0,
              ':bpe_project' => isset($Data['bpe_project'])?$Data['bpe_project']:NULL,
              ':bpe_createby' => isset($Data['bpe_createby'])?$Data['bpe_createby']:NULL,
              ':bpe_createat' => $this->validateDate($Data['bpe_createat'])?$Data['bpe_createat']:NULL,
              ':bpe_payment' => isset($Data['bpe_payment'])?$Data['bpe_payment']:NULL)

						);

        if(is_numeric($resInsert) && $resInsert > 0){

					// Se actualiza el valor pagado en la factura de compra

					$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
					$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
							':pgs_nextnum' => $DocNumVerificado,
							':pgs_id'      => $Data['bpe_series']
					));


					if(is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1){

					}else{
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resActualizarNumeracion,
									'mensaje'	=> 'No se pudo crear el pago'
								);

								$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

								return;
					}










          foreach ($ContenidoDetalle as $key => $detail) {
// print_r($detail);exit();die();
								//VALIDAR EL VALOR QUE SE ESTA PAGANDO NO SEA MAYOR AL SALDO DE LA FACTURA
								$VlrPayFact = "SELECT COALESCE(cfc_paytoday,0) as cfc_paytoday,cfc_doctotal from dcfc WHERE cfc_docentry = :cfc_docentry and cfc_doctype = :cfc_doctype";
								$resVlrPayFact = $this->pedeo->queryTable($VlrPayFact, array(
									':cfc_docentry' => $detail['pe1_docentry'],
									':cfc_doctype' => $detail['pe1_doctype']
								));

									$VlrPaidActual = $detail['pe1_vlrpaid'];
									$VlrPaidFact = $resVlrPayFact[0]['cfc_paytoday'];

									$SumVlr =  $VlrPaidActual + $VlrPaidFact ;
								if(isset($resVlrPayFact[0])){

									if($SumVlr <= $resVlrPayFact[0]['cfc_doctotal'] ){


									}else{
										$this->pedeo->trans_rollback();

										$respuesta = array(
											'error'   => true,
											'data' => '',
											'mensaje'	=> 'El valor a pagar no puede ser mayor al saldo de la factura'
										);

										 $this->response($respuesta);

										 return;
									}

								}
								else{
									$this->pedeo->trans_rollback();
									$respuesta = array(
										'error'   => true,
										'data' => $resVlrPayFact,
										'mensaje'	=> 'No tiene valor para realizar la operacion');

										$this->response($respuesta);

 									 return;

								}







                $sqlInsertDetail = "INSERT INTO
                                        	bpe1 (pe1_docnum,pe1_docentry,pe1_numref,pe1_docdate,pe1_vlrtotal,pe1_vlrpaid,pe1_comments,pe1_porcdiscount,pe1_doctype,
                                            pe1_docduedate,pe1_daysbackw,pe1_vlrdiscount,pe1_ocrcode)
                                    VALUES (:pe1_docnum,:pe1_docentry,:pe1_numref,:pe1_docdate,:pe1_vlrtotal,:pe1_vlrpaid,:pe1_comments,:pe1_porcdiscount,
                                            :pe1_doctype,:pe1_docduedate,:pe1_daysbackw,:pe1_vlrdiscount,:pe1_ocrcode)";



                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':pe1_docnum' => $resInsert,
                        ':pe1_docentry' => is_numeric($detail['pe1_docentry'])?$detail['pe1_docentry']:0,
                        ':pe1_numref' => isset($detail['pe1_numref'])?$detail['pe1_numref']:NULL,
                        ':pe1_docdate' =>  $this->validateDate($detail['pe1_docdate'])?$detail['pe1_docdate']:NULL,
                        ':pe1_vlrtotal' => is_numeric($detail['pe1_vlrtotal'])?$detail['pe1_vlrtotal']:0,
                        ':pe1_vlrpaid' => is_numeric($detail['pe1_vlrpaid'])?$detail['pe1_vlrpaid']:0,
                        ':pe1_comments' => isset($detail['pe1_comments'])?$detail['pe1_comments']:NULL,
                        ':pe1_porcdiscount' => is_numeric($detail['pe1_porcdiscount'])?$detail['pe1_porcdiscount']:0,
                        ':pe1_doctype' => is_numeric($detail['pe1_doctype'])?$detail['pe1_doctype']:0,
                        ':pe1_docduedate' => $this->validateDate($detail['pe1_docduedate'])?$detail['pe1_docduedate']:NULL,
                        ':pe1_daysbackw' => is_numeric($detail['pe1_daysbackw'])?$detail['pe1_daysbackw']:0,
                        ':pe1_vlrdiscount' => is_numeric($detail['pe1_vlrdiscount'])?$detail['pe1_vlrdiscount']:0,
                        ':pe1_ocrcode' => isset($detail['pe1_ocrcode'])?$detail['pe1_ocrcode']:NULL
                        // ':pe1_ocrcode1' => isset($detail['pe1_ocrcode1'])?$detail['pe1_ocrcode1']:NULL
                ));


								if(is_numeric($resInsertDetail) && $resInsertDetail > 0){



									$sqlUpdateFactPay = "UPDATE  dcfc  SET cfc_paytoday = COALESCE(cfc_paytoday,0)+:cfc_paytoday WHERE cfc_docentry = :cfc_docentry and cfc_doctype = :cfc_doctype";

									$resUpdateFactPay = $this->pedeo->updateRow($sqlUpdateFactPay,array(

										':cfc_paytoday' => $detail['pe1_vlrpaid'],
										':cfc_docentry' => $detail['pe1_docentry'],
										':cfc_doctype' => $detail['pe1_doctype']


									));
// print_r($resUpdateFactPay);exit();die();
									if(is_numeric($resUpdateFactPay) && $resUpdateFactPay > 0){

									}else{
										$this->pedeo->trans_rollback();

										$respuesta = array(
											'error'   => true,
											'data' => $resUpdateFactPay,
											'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura '.$detail['pe1_docentry']
										);

										 $this->response($respuesta);

										 return;
									}

										// Se verifica que el detalle no de error insertando //
								}else{

										// si falla algun insert del detalle de la cotizacion se devuelven los cambios realizados por la transaccion,
										// se retorna el error y se detiene la ejecucion del codigo restante.
											$this->pedeo->trans_rollback();

											$respuesta = array(
												'error'   => true,
												'data' => $resInsertDetail,
												'mensaje'	=> 'No se pudo registrar el pago'
											);

											 $this->response($respuesta);

											 return;
								}


								// Fin de la actualizacion de la numeracion del documento

          }


					//FIN DETALLE COTIZACION



					// Si todo sale bien despues de insertar el detalle de la cotizacion
					// se confirma la trasaccion  para que los cambios apliquen permanentemente
					// en la base de datos y se confirma la operacion exitosa.
					$this->pedeo->trans_commit();

          $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' =>'Pago registrado con exito'
          );


        }else{
					// Se devuelven los cambios realizados en la transaccion
					// si occurre un error  y se muestra devuelve el error.
							$this->pedeo->trans_rollback();

              $respuesta = array(
                'error'   => true,
                'data' => $resInsert,
                'mensaje'	=> 'No se pudo registrar el pago'
              );

        }

         $this->response($respuesta);
	}

  // //ACTUALIZAR COTIZACION
  // public function updateQuotation_post(){
  //
  //     $Data = $this->post();
  //
	// 		if(!isset($Data['detail'])){
  //
  //       $respuesta = array(
  //         'error' => true,
  //         'data'  => array(),
  //         'mensaje' =>'La informacion enviada no es valida'
  //       );
  //
  //       $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
  //
  //       return;
  //     }
  //
	// 		$ContenidoDetalle = json_decode($Data['detail'], true);
  //
  //
  //     if(!is_array($ContenidoDetalle)){
  //         $respuesta = array(
  //           'error' => true,
  //           'data'  => array(),
  //           'mensaje' =>'No se encontro el detalle de la cotización'
  //         );
  //
  //         $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
  //
  //         return;
  //     }
  //
  //
	// 		//Obtener Carpeta Principal del Proyecto
	// 		$sqlMainFolder = " SELECT * FROM params";
	// 		$resMainFolder = $this->pedeo->queryTable($sqlMainFolder, array());
  //
	// 		if(!isset($resMainFolder[0])){
	// 				$respuesta = array(
	// 				'error' => true,
	// 				'data'  => array(),
	// 				'mensaje' =>'No se encontro la caperta principal del proyecto'
	// 				);
  //
	// 				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
  //
	// 				return;
	// 		}
  //
  //     $sqlUpdate = "UPDATE dvct	SET dvc_docdate=:dvc_docdate,dvc_duedate=:dvc_duedate, dvc_duedev=:dvc_duedev, dvc_pricelist=:dvc_pricelist, dvc_cardcode=:dvc_cardcode,
	// 		  						dvc_cardname=:dvc_cardname, dvc_currency=:dvc_currency, dvc_contacid=:dvc_contacid, dvc_slpcode=:dvc_slpcode,
	// 									dvc_empid=:dvc_empid, dvc_comment=:dvc_comment, dvc_doctotal=:dvc_doctotal, dvc_baseamnt=:dvc_baseamnt,
	// 									dvc_taxtotal=:dvc_taxtotal, dvc_discprofit=:dvc_discprofit, dvc_discount=:dvc_discount, dvc_createat=:dvc_createat,
	// 									dvc_baseentry=:dvc_baseentry, dvc_basetype=:dvc_basetype, dvc_doctype=:dvc_doctype, dvc_idadd=:dvc_idadd,
	// 									dvc_adress=:dvc_adress, dvc_paytype=:dvc_paytype, dvc_attch=:dvc_attch WHERE dvc_docentry=:dvc_docentry";
  //
  //     $this->pedeo->trans_begin();
  //
  //     $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
	// 						':dvc_docdate' => $this->validateDate($Data['dvc_docdate'])?$Data['dvc_docdate']:NULL,
	// 						':dvc_duedate' => $this->validateDate($Data['dvc_duedate'])?$Data['dvc_duedate']:NULL,
	// 						':dvc_duedev' => $this->validateDate($Data['dvc_duedev'])?$Data['dvc_duedev']:NULL,
	// 						':dvc_pricelist' => is_numeric($Data['dvc_pricelist'])?$Data['dvc_pricelist']:0,
	// 						':dvc_cardcode' => isset($Data['dvc_cardcode'])?$Data['dvc_cardcode']:NULL,
	// 						':dvc_cardname' => isset($Data['dvc_cardname'])?$Data['dvc_cardname']:NULL,
	// 						':dvc_currency' => isset($Data['dvc_currency'])?$Data['dvc_currency']:NULL,
	// 						':dvc_contacid' => isset($Data['dvc_contacid'])?$Data['dvc_contacid']:NULL,
	// 						':dvc_slpcode' => is_numeric($Data['dvc_slpcode'])?$Data['dvc_slpcode']:0,
	// 						':dvc_empid' => is_numeric($Data['dvc_empid'])?$Data['dvc_empid']:0,
	// 						':dvc_comment' => isset($Data['dvc_comment'])?$Data['dvc_comment']:NULL,
	// 						':dvc_doctotal' => is_numeric($Data['dvc_doctotal'])?$Data['dvc_doctotal']:0,
	// 						':dvc_baseamnt' => is_numeric($Data['dvc_baseamnt'])?$Data['dvc_baseamnt']:0,
	// 						':dvc_taxtotal' => is_numeric($Data['dvc_taxtotal'])?$Data['dvc_taxtotal']:0,
	// 						':dvc_discprofit' => is_numeric($Data['dvc_discprofit'])?$Data['dvc_discprofit']:0,
	// 						':dvc_discount' => is_numeric($Data['dvc_discount'])?$Data['dvc_discount']:0,
	// 						':dvc_createat' => $this->validateDate($Data['dvc_createat'])?$Data['dvc_createat']:NULL,
	// 						':dvc_baseentry' => is_numeric($Data['dvc_baseentry'])?$Data['dvc_baseentry']:0,
	// 						':dvc_basetype' => is_numeric($Data['dvc_basetype'])?$Data['dvc_basetype']:0,
	// 						':dvc_doctype' => is_numeric($Data['dvc_doctype'])?$Data['dvc_doctype']:0,
	// 						':dvc_idadd' => isset($Data['dvc_idadd'])?$Data['dvc_idadd']:NULL,
	// 						':dvc_adress' => isset($Data['dvc_adress'])?$Data['dvc_adress']:NULL,
	// 						':dvc_paytype' => is_numeric($Data['dvc_paytype'])?$Data['dvc_paytype']:0,
	// 						':dvc_attch' => $this->getUrl(count(trim(($Data['dvc_attch']))) > 0 ? $Data['dvc_attch']:NULL, $resMainFolder[0]['main_folder']),
	// 						':dvc_docentry' => $Data['dvc_docentry']
  //     ));
  //
  //     if(is_numeric($resUpdate) && $resUpdate == 1){
  //
	// 					$this->pedeo->queryTable("DELETE FROM vct1 WHERE vc1_docentry=:vc1_docentry", array(':vc1_docentry' => $Data['dvc_docentry']));
  //
	// 					foreach ($ContenidoDetalle as $key => $detail) {
  //
	// 								$sqlInsertDetail = "INSERT INTO vct1(vc1_docentry, vc1_itemcode, vc1_itemname, vc1_quantity, vc1_uom, vc1_whscode,
	// 																		vc1_price, vc1_vat, vc1_vatsum, vc1_discount, vc1_linetotal, vc1_costcode, vc1_ubusiness, vc1_project,
	// 																		vc1_acctcode, vc1_basetype, vc1_doctype, vc1_avprice, vc1_inventory, vc1_acciva, vc1_linenum)VALUES(:vc1_docentry, :vc1_itemcode, :vc1_itemname, :vc1_quantity,
	// 																		:vc1_uom, :vc1_whscode,:vc1_price, :vc1_vat, :vc1_vatsum, :vc1_discount, :vc1_linetotal, :vc1_costcode, :vc1_ubusiness, :vc1_project,
	// 																		:vc1_acctcode, :vc1_basetype, :vc1_doctype, :vc1_avprice, :vc1_inventory, :vc1_acciva,:vc1_linenum)";
  //
	// 								$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
	// 										':vc1_docentry' => $Data['dvc_docentry'],
	// 										':vc1_itemcode' => isset($detail['vc1_itemcode'])?$detail['vc1_itemcode']:NULL,
	// 										':vc1_itemname' => isset($detail['vc1_itemname'])?$detail['vc1_itemname']:NULL,
	// 										':vc1_quantity' => is_numeric($detail['vc1_quantity'])?$detail['vc1_quantity']:0,
	// 										':vc1_uom' => isset($detail['vc1_uom'])?$detail['vc1_uom']:NULL,
	// 										':vc1_whscode' => isset($detail['vc1_whscode'])?$detail['vc1_whscode']:NULL,
	// 										':vc1_price' => is_numeric($detail['vc1_price'])?$detail['vc1_price']:0,
	// 										':vc1_vat' => is_numeric($detail['vc1_vat'])?$detail['vc1_vat']:0,
	// 										':vc1_vatsum' => is_numeric($detail['vc1_vatsum'])?$detail['vc1_vatsum']:0,
	// 										':vc1_discount' => is_numeric($detail['vc1_discount'])?$detail['vc1_discount']:0,
	// 										':vc1_linetotal' => is_numeric($detail['vc1_linetotal'])?$detail['vc1_linetotal']:0,
	// 										':vc1_costcode' => isset($detail['vc1_costcode'])?$detail['vc1_costcode']:NULL,
	// 										':vc1_ubusiness' => isset($detail['vc1_ubusiness'])?$detail['vc1_ubusiness']:NULL,
	// 										':vc1_project' => isset($detail['vc1_project'])?$detail['vc1_project']:NULL,
	// 										':vc1_acctcode' => is_numeric($detail['vc1_acctcode'])?$detail['vc1_acctcode']:0,
	// 										':vc1_basetype' => is_numeric($detail['vc1_basetype'])?$detail['vc1_basetype']:0,
	// 										':vc1_doctype' => is_numeric($detail['vc1_doctype'])?$detail['vc1_doctype']:0,
	// 										':vc1_avprice' => is_numeric($detail['vc1_avprice'])?$detail['vc1_avprice']:0,
	// 										':vc1_inventory' => is_numeric($detail['vc1_inventory'])?$detail['vc1_inventory']:NULL,
	// 										':vc1_acciva' => is_numeric($detail['vc1_acciva'])?$detail['vc1_acciva']:NULL,
	// 										':vc1_linenum' => is_numeric($detail['vc1_linenum'])?$detail['vc1_linenum']:NULL
	// 								));
  //
	// 								if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
	// 										// Se verifica que el detalle no de error insertando //
	// 								}else{
  //
	// 										// si falla algun insert del detalle de la cotizacion se devuelven los cambios realizados por la transaccion,
	// 										// se retorna el error y se detiene la ejecucion del codigo restante.
	// 											$this->pedeo->trans_rollback();
  //
	// 											$respuesta = array(
	// 												'error'   => true,
	// 												'data' => $resInsert,
	// 												'mensaje'	=> 'No se pudo registrar la cotización'
	// 											);
  //
	// 											 $this->response($respuesta);
  //
	// 											 return;
	// 								}
	// 					}
  //
  //
	// 					$this->pedeo->trans_commit();
  //
  //           $respuesta = array(
  //             'error' => false,
  //             'data' => $resUpdate,
  //             'mensaje' =>'Cotización actualizada con exito'
  //           );
  //
  //
  //     }else{
  //
	// 					$this->pedeo->trans_rollback();
  //
  //           $respuesta = array(
  //             'error'   => true,
  //             'data'    => $resUpdate,
  //             'mensaje'	=> 'No se pudo actualizar la cotización'
  //           );
  //
  //     }
  //
  //      $this->response($respuesta);
  // }


  //OBTENER COTIZACIONES
  public function getPaymentsMade1_get(){

        $sqlSelect = " SELECT * FROM gbpe";

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
	public function getPymentsById_get(){

				$Data = $this->get();

				if(!isset($Data['bpe_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM gbpe WHERE bpe_docentry =:bpe_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":bpe_docentry" => $Data['bpe_docentry']));

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

				if(!isset($Data['pe1_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM bpe1 WHERE pe1_docentry =:pe1_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":pe1_docentry" => $Data['pe1_docentry']));

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
	public function getPymentsBySN_get(){

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

				$sqlSelect = " SELECT * FROM gbpe WHERE bpe_cardcode =:bpe_cardcode";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":bpe_cardcode" => $Data['dms_card_code']));

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









	// private function getUrl($data, $caperta){
  //     $url = "";
  //
  //     if ($data == NULL){
  //
  //       return $url;
  //
  //     }
  //
	// 		$ruta = '/var/www/html/'.$caperta.'/assets/img/anexos/';
  //
  //     $milliseconds = round(microtime(true) * 1000);
  //
  //
  //     $nombreArchivo = $milliseconds.".pdf";
  //
  //     touch($ruta.$nombreArchivo);
  //
  //     $file = fopen($ruta.$nombreArchivo,"wb");
  //
  //     if(!empty($data)){
  //
  //       fwrite($file, base64_decode($data));
  //
  //       fclose($file);
  //
  //       $url = "assets/img/anexos/".$nombreArchivo;
  //     }
  //
  //     return $url;
  // }

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
