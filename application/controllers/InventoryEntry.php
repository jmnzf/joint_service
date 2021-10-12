<?php
// COTIZACIONES
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class InventoryEntry extends REST_Controller {

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

  //CREAR NUEVA ENTRADA
	public function createInventoryEntry_post(){

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
            'mensaje' =>'No se encontro el detalle de la entrada'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }
				//BUSCANDO LA NUMERACION DEL DOCUMENTO
			  $sqlNumeracion = " SELECT pgs_nextnum,pgs_last_num FROM  pgdn WHERE pgs_id = :pgs_id";

				$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['iei_series']));

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

        $sqlInsert = "INSERT INTO miei (iei_docnum, iei_docdate, iei_duedate, iei_duedev, iei_pricelist, iei_cardcode, iei_cardname, iei_contacid, iei_slpcode, iei_empid, iei_comment, iei_doctotal,
                      iei_taxtotal, iei_discprofit, iei_discount, iei_createat, iei_baseentry, iei_basetype, iei_doctype, iei_idadd, iei_adress, iei_paytype, iei_attch,
                      iei_baseamnt, iei_series, iei_createby, iei_currency)
                      VALUES
                      (:iei_docnum, :iei_docdate, :iei_duedate, :iei_duedev, :iei_pricelist, :iei_cardcode, :iei_cardname, :iei_contacid, :iei_slpcode, :iei_empid, :iei_comment, :iei_doctotal,
                      :iei_taxtotal, :iei_discprofit, :iei_discount, :iei_createat, :iei_baseentry, :iei_basetype, :iei_doctype, :iei_idadd, :iei_adress, :iei_paytype, :iei_attch,
                      :iei_baseamnt, :iei_series, :iei_createby, :iei_currency)";


				// Se Inicia la transaccion,
				// Todas las consultas de modificacion siguientes
				// aplicaran solo despues que se confirme la transaccion,
				// de lo contrario no se aplicaran los cambios y se devolvera
				// la base de datos a su estado original.

			  $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(

                              ':iei_docnum' => $DocNumVerificado,
                              ':iei_docdate'  => $this->validateDate($Data['iei_docdate'])?$Data['iei_docdate']:NULL,
                              ':iei_duedate' => $this->validateDate($Data['iei_duedate'])?$Data['iei_duedate']:NULL,
                              ':iei_duedev' => $this->validateDate($Data['iei_duedev'])?$Data['iei_duedev']:NULL,
                              ':iei_pricelist' => is_numeric($Data['iei_pricelist'])?$Data['iei_pricelist']:0,
                              ':iei_cardcode' => isset($Data['iei_cardcode'])?$Data['iei_cardcode']:NULL,
                              ':iei_cardname' => isset($Data['iei_cardname'])?$Data['iei_cardname']:NULL,
                              ':iei_contacid' => is_numeric($Data['iei_contacid'])?$Data['iei_contacid']:0,
                              ':iei_slpcode' => is_numeric($Data['iei_slpcode'])?$Data['iei_slpcode']:0,
                              ':iei_empid' => is_numeric($Data['iei_empid'])?$Data['iei_empid']:0,
                              ':iei_comment' => isset($Data['iei_comment'])?$Data['iei_comment']:NULL,
                              ':iei_doctotal' => is_numeric($Data['iei_doctotal'])?$Data['iei_doctotal']:0,
                              ':iei_baseamnt' => is_numeric($Data['iei_baseamnt'])?$Data['iei_baseamnt']:0,
                              ':iei_taxtotal' => is_numeric($Data['iei_taxtotal'])?$Data['iei_taxtotal']:0,
                              ':iei_discprofit' => is_numeric($Data['iei_discprofit'])?$Data['iei_discprofit']:0,
                              ':iei_discount' => is_numeric($Data['iei_discount'])?$Data['iei_discount']:0,
                              ':iei_createat' => $this->validateDate($Data['iei_createat'])?$Data['iei_createat']:NULL,
                              ':iei_baseentry' => is_numeric($Data['iei_baseentry'])?$Data['iei_baseentry']:0,
                              ':iei_basetype' => is_numeric($Data['iei_basetype'])?$Data['iei_basetype']:0,
                              ':iei_doctype' => is_numeric($Data['iei_doctype'])?$Data['iei_doctype']:0,
                              ':iei_idadd' => isset($Data['iei_idadd'])?$Data['iei_idadd']:NULL,
                              ':iei_adress' => isset($Data['iei_adress'])?$Data['iei_adress']:NULL,
                              ':iei_paytype' => is_numeric($Data['iei_paytype'])?$Data['iei_paytype']:0,
                              ':iei_attch' => $this->getUrl(count(trim(($Data['iei_attch']))) > 0 ? $Data['iei_attch']:NULL, $resMainFolder[0]['main_folder']),
                              ':iei_series' => is_numeric($Data['iei_series'])?$Data['iei_series']:0,
                              ':iei_createby' => isset($Data['iei_createby'])?$Data['iei_createby']:NULL,
                              ':iei_currency' => isset($Data['iei_currency'])?$Data['iei_currency']:NULL

						));

        if(is_numeric($resInsert) && $resInsert > 0){

					// Se actualiza la serie de la numeracion del documento

					$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
					$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
							':pgs_nextnum' => $DocNumVerificado,
							':pgs_id'      => $Data['iei_series']
					));


					if(is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1){

					}else{
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resActualizarNumeracion,
									'mensaje'	=> 'No se pudo crear la entrada  '
								);

								$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

								return;
					}
					// Fin de la actualizacion de la numeracion del documento



          foreach ($ContenidoDetalle as $key => $detail) {

                $sqlInsertDetail = "INSERT INTO iei1 (ei1_docentry, ei1_itemcode, ei1_itemname, ei1_quantity, ei1_uom, ei1_whscode, ei1_price, ei1_vat, ei1_vatsum, ei1_discount, ei1_linetotal,
																		ei1_costcode, ei1_ubusiness,ei1_project, ei1_acctcode, ei1_basetype, ei1_doctype, ei1_avprice, ei1_inventory,  ei1_linenum, ei1_acciva)
                                    VALUES
                                    (:ei1_docentry, :ei1_itemcode, :ei1_itemname, :ei1_quantity, :ei1_uom, :ei1_whscode, :ei1_price, :ei1_vat, :ei1_vatsum, :ei1_discount, :ei1_linetotal,
																		 :ei1_costcode, :ei1_ubusiness,:ei1_project, :ei1_acctcode, :ei1_basetype, :ei1_doctype, :ei1_avprice, :ei1_inventory, :ei1_linenum, :ei1_acciva)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(

                                    ':ei1_docentry'  => $resInsert,
                                    ':ei1_itemcode' => isset($detail['ei1_itemcode'])?$detail['ei1_itemcode']:NULL,
                                    ':ei1_itemname' => isset($detail['ei1_itemname'])?$detail['ei1_itemname']:NULL,
                                    ':ei1_quantity' => is_numeric($detail['ei1_quantity'])?$detail['ei1_quantity']:0,
                                    ':ei1_uom' => isset($detail['ei1_uom'])?$detail['ei1_uom']:NULL,
                                    ':ei1_whscode' => isset($detail['ei1_whscode'])?$detail['ei1_whscode']:NULL,
                                    ':ei1_price' => is_numeric($detail['ei1_price'])?$detail['ei1_price']:0,
                                    ':ei1_vat' => is_numeric($detail['ei1_vat'])?$detail['ei1_vat']:0,
                                    ':ei1_vatsum' => is_numeric($detail['ei1_vatsum'])?$detail['ei1_vatsum']:0,
                                    ':ei1_discount' => is_numeric($detail['ei1_discount'])?$detail['ei1_discount']:0,
                                    ':ei1_linetotal' => is_numeric($detail['ei1_linetotal'])?$detail['ei1_linetotal']:0,
                                    ':ei1_costcode' => isset($detail['ei1_costcode'])?$detail['ei1_costcode']:NULL,
                                    ':ei1_ubusiness' => isset($detail['ei1_ubusiness'])?$detail['ei1_ubusiness']:NULL,
                                    ':ei1_project' => isset($detail['ei1_project'])?$detail['ei1_project']:NULL,
                                    ':ei1_acctcode' => is_numeric($detail['ei1_acctcode'])?$detail['ei1_acctcode']:0,
                                    ':ei1_basetype' => is_numeric($detail['ei1_basetype'])?$detail['ei1_basetype']:0,
                                    ':ei1_doctype' => is_numeric($detail['ei1_doctype'])?$detail['ei1_doctype']:0,
                                    ':ei1_avprice' => is_numeric($detail['ei1_avprice'])?$detail['ei1_avprice']:0,
                                    ':ei1_inventory' => is_numeric($detail['ei1_inventory'])?$detail['ei1_inventory']:0,
                                    ':ei1_linenum' => is_numeric($detail['ei1_linenum'])?$detail['ei1_linenum']:0,
                                    ':ei1_acciva'=> is_numeric($detail['ei1_acciva'])?$detail['ei1_acciva']:0
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
												'mensaje'	=> 'No se pudo registrar la entrada '
											);

											 $this->response($respuesta);

											 return;
								}


          }

					//FIN DETALLE ENTRADA



					// Si todo sale bien despues de insertar el detalle de la entrada de
					// se confirma la trasaccion  para que los cambios apliquen permanentemente
					// en la base de datos y se confirma la operacion exitosa.
					$this->pedeo->trans_commit();

          $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' =>'Entrada registrada con exito'
          );


        }else{
					// Se devuelven los cambios realizados en la transaccion
					// si occurre un error  y se muestra devuelve el error.
							$this->pedeo->trans_rollback();

              $respuesta = array(
                'error'   => true,
                'data' => $resInsert,
                'mensaje'	=> 'No se pudo registrar la entrada'
              );

        }

         $this->response($respuesta);
	}



  //OBTENER ENTRADAS DE
  public function getInventoryEntry_get(){

        $sqlSelect = "SELECT
											t2.mdt_docname,
											t0.iei_docnum,
											t0.iei_docdate,
											t0.iei_cardname,
											t0.iei_comment,
											CONCAT(T0.iei_currency,' ',to_char(t0.iei_doctotal,'999,999,999,999.00')) iei_doctotal,
											t1.mev_names iei_slpcode
										 FROM miei t0
										 LEFT JOIN dmev t1 on t0.iei_slpcode = t1.mev_id
										 LEFT JOIN dmdt t2 on t0.iei_doctype = t2.mdt_doctype";

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


	//OBTENER ENTRADA DE  POR ID
	public function getInventoryEntryById_get(){

				$Data = $this->get();

				if(!isset($Data['iei_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM miei WHERE iei_docentry =:iei_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":iei_docentry" => $Data['iei_docentry']));

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


	//OBTENER ENTADA DE  DETALLE POR ID
	public function getInventoryEntryDetail_get(){

				$Data = $this->get();

				if(!isset($Data['ei1_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM iei WHERE ei1_docentry =:ei1_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":ei1_docentry" => $Data['ei1_docentry']));

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



	//OBTENER ENTRADAS DE  POR SOCIO DE NEGOCIO
	public function getInventoryEntryBySN_get(){

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

				$sqlSelect = " SELECT * FROM miei WHERE iei_cardcode =:iei_cardcode";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":iei_cardcode" => $Data['dms_card_code']));

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
