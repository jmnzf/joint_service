<?php
// COTIZACIONES
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class ExitInventory extends REST_Controller {

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
	public function createExitInventory_post(){

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
            'mensaje' =>'No se encontro el detalle de la salida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }

				//BUSCANDO LA NUMERACION DEL DOCUMENTO
			  $sqlNumeracion = " SELECT pgs_nextnum,pgs_last_num FROM  pgdn WHERE pgs_id = :pgs_id";

				$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['isi_series']));

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

				$sqlInsert = "INSERT INTO misi (isi_docnum, isi_docdate, isi_duedate, isi_duedev, isi_pricelist, isi_cardcode, isi_cardname, isi_contacid, isi_slpcode, isi_empid, isi_comment, isi_doctotal, isi_baseamnt,
                      isi_taxtotal, isi_discprofit, isi_discount, isi_createat, isi_baseentry, isi_basetype, isi_doctype, isi_idadd, isi_adress, isi_paytype, isi_attch,
                      isi_series, isi_createby, isi_currency)
                      VALUES
                      (:isi_docnum, :isi_docdate, :isi_duedate, :isi_duedev, :isi_pricelist, :isi_cardcode, :isi_cardname, :isi_contacid, :isi_slpcode, :isi_empid, :isi_comment, :isi_doctotal, :isi_baseamnt,
                      :isi_taxtotal, :isi_discprofit, :isi_discount, :isi_createat, :isi_baseentry, :isi_basetype, :isi_doctype, :isi_idadd, :isi_adress, :isi_paytype, :isi_attch,
                      :isi_series, :isi_createby, :isi_currency)";


				// Se Inicia la transaccion,
				// Todas las consultas de modificacion siguientes
				// aplicaran solo despues que se confirme la transaccion,
				// de lo contrario no se aplicaran los cambios y se devolvera
				// la base de datos a su estado original.

			  $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(

                              ':isi_docnum' => $DocNumVerificado,
                              ':isi_docdate'  => $this->validateDate($Data['isi_docdate'])?$Data['isi_docdate']:NULL,
                              ':isi_duedate' => $this->validateDate($Data['isi_duedate'])?$Data['isi_duedate']:NULL,
                              ':isi_duedev' => $this->validateDate($Data['isi_duedev'])?$Data['isi_duedev']:NULL,
                              ':isi_pricelist' => is_numeric($Data['isi_pricelist'])?$Data['isi_pricelist']:0,
                              ':isi_cardcode' => isset($Data['isi_cardcode'])?$Data['isi_cardcode']:NULL,
                              ':isi_cardname' => isset($Data['isi_cardname'])?$Data['isi_cardname']:NULL,
                              ':isi_contacid' => isset($Data['isi_contacid'])?$Data['isi_contacid']:NULL,
                              ':isi_slpcode' => is_numeric($Data['isi_slpcode'])?$Data['isi_slpcode']:0,
                              ':isi_empid' => is_numeric($Data['isi_empid'])?$Data['isi_empid']:0,
                              ':isi_comment' => isset($Data['isi_comment'])?$Data['isi_comment']:NULL,
                              ':isi_doctotal' => is_numeric($Data['isi_doctotal'])?$Data['isi_doctotal']:NULL,
                              ':isi_baseamnt' => is_numeric($Data['isi_baseamnt'])?$Data['isi_baseamnt']:NULL,
                              ':isi_taxtotal' => is_numeric($Data['isi_taxtotal'])?$Data['isi_taxtotal']:NULL,
                              ':isi_discprofit' => is_numeric($Data['isi_discprofit'])?$Data['isi_discprofit']:0,
                              ':isi_discount' => is_numeric($Data['isi_discount'])?$Data['isi_discount']:NULL,
                              ':isi_createat' => $this->validateDate($Data['isi_createat'])?$Data['isi_createat']:NULL,
                              ':isi_baseentry' => is_numeric($Data['isi_baseentry'])?$Data['isi_baseentry']:NULL,
                              ':isi_basetype' => is_numeric($Data['isi_basetype'])?$Data['isi_basetype']:NULL,
                              ':isi_doctype' => is_numeric($Data['isi_cardcode'])?$Data['isi_cardcode']:NULL,
                              ':isi_idadd' => isset($Data['isi_idadd'])?$Data['isi_idadd']:NULL,
                              ':isi_adress' => isset($Data['isi_adress'])?$Data['isi_adress']:NULL,
                              ':isi_paytype' => is_numeric($Data['isi_cardcode'])?$Data['isi_cardcode']:NULL,
                              ':isi_attch' => $this->getUrl(count(trim(($Data['isi_attch']))) > 0 ? $Data['isi_attch']:NULL, $resMainFolder[0]['main_folder']),
                              ':isi_series' => is_numeric($Data['isi_series'])?$Data['isi_series']:0,
                              ':isi_createby' => isset($Data['isi_createby'])?$Data['isi_createby']:NULL,
                              ':isi_currency' => isset($Data['isi_currency'])?$Data['isi_currency']:NULL

						));

        if(is_numeric($resInsert) && $resInsert > 0){

					// Se actualiza la serie de la numeracion del documento

					$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
					$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
							':pgs_nextnum' => $DocNumVerificado,
							':pgs_id'      => $Data['isi_series']
					));


					if(is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1){

					}else{
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resActualizarNumeracion,
									'mensaje'	=> 'No se pudo crear la salida  '
								);

								$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

								return;
					}
					// Fin de la actualizacion de la numeracion del documento



          foreach ($ContenidoDetalle as $key => $detail) {

						$sqlInsertDetail = "INSERT INTO isi1 (si1_docentry, si1_itemcode, si1_itemname, si1_quantity, si1_uom, si1_whscode, si1_price, si1_vat, si1_vatsum, si1_discount, si1_linetotal,
																si1_costcode, si1_ubusiness,si1_project, si1_acctcode, si1_basetype, si1_doctype, si1_avprice, si1_inventory, si1_linenum, si1_acciva)
																VALUES
																(:si1_docentry, :si1_itemcode, :si1_itemname, :si1_quantity, :si1_uom, :si1_whscode, :si1_price, :si1_vat, :si1_vatsum, :si1_discount, :si1_linetotal,
																 :si1_costcode, :si1_ubusiness,:si1_project, :si1_acctcode, :si1_basetype, :si1_doctype, :si1_avprice, :si1_inventory, :si1_linenum, :si1_acciva)";

						$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(

																':si1_docentry'  => $resInsert,
																':si1_itemcode' => isset($detail['si1_itemcode'])?$detail['si1_itemcode']:NULL,
																':si1_itemname' => isset($detail['si1_itemname'])?$detail['si1_itemname']:NULL,
																':si1_quantity' => is_numeric($detail['si1_quantity'])?$detail['si1_quantity']:0,
																':si1_uom' => isset($detail['si1_uom'])?$detail['si1_uom']:NULL,
																':si1_whscode' => isset($detail['si1_whscode'])?$detail['si1_whscode']:NULL,
																':si1_price' => is_numeric($detail['si1_price'])?$detail['si1_price']:0,
																':si1_vat' => is_numeric($detail['si1_vat'])?$detail['si1_vat']:0,
																':si1_vatsum' => is_numeric($detail['si1_vatsum'])?$detail['si1_vatsum']:0,
																':si1_discount' => is_numeric($detail['si1_discount'])?$detail['si1_discount']:0,
																':si1_linetotal' => is_numeric($detail['si1_linetotal'])?$detail['si1_linetotal']:0,
																':si1_costcode' => isset($detail['si1_costcode'])?$detail['si1_costcode']:NULL,
																':si1_ubusiness' => isset($detail['si1_ubusiness'])?$detail['si1_ubusiness']:NULL,
																':si1_project' => isset($detail['si1_project'])?$detail['si1_project']:NULL,
																':si1_acctcode' => is_numeric($detail['si1_acctcode'])?$detail['si1_acctcode']:0,
																':si1_basetype' => is_numeric($detail['si1_basetype'])?$detail['si1_basetype']:0,
																':si1_doctype' => is_numeric($detail['si1_doctype'])?$detail['si1_doctype']:0,
																':si1_avprice' => is_numeric($detail['si1_avprice'])?$detail['si1_avprice']:0,
																':si1_inventory' => is_numeric($detail['si1_inventory'])?$detail['si1_inventory']:0,
																':si1_linenum' => is_numeric($detail['si1_linenum'])?$detail['si1_linenum']:0,
																':si1_acciva'=> is_numeric($detail['si1_acciva'])?$detail['si1_acciva']:0
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
												'mensaje'	=> 'No se pudo registrar la salida '
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
            'mensaje' =>'Salida registrada con exito'
          );


        }else{
					// Se devuelven los cambios realizados en la transaccion
					// si occurre un error  y se muestra devuelve el error.
							$this->pedeo->trans_rollback();

              $respuesta = array(
                'error'   => true,
                'data' => $resInsert,
                'mensaje'	=> 'No se pudo registrar la salida'
              );

        }

         $this->response($respuesta);
	}




  //OBTENER ENTRADAS DE
  public function getExitInventory_get(){

        $sqlSelect = "SELECT
											t2.mdt_docname,
											t0.isi_docnum,
											t0.isi_docdate,
											t0.isi_cardname,
											t0.isi_comment,
											CONCAT(T0.isi_currency,' ',to_char(t0.isi_doctotal,'999,999,999,999.00')) isi_doctotal,
											t1.mev_names isi_slpcode
										 FROM misi t0
										 LEFT JOIN dmev t1 on t0.isi_slpcode = t1.mev_id
										 LEFT JOIN dmdt t2 on t0.isi_doctype = t2.mdt_doctype";

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
	public function getExitInventoryById_get(){

				$Data = $this->get();

				if(!isset($Data['isi_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM misi WHERE isi_docentry =:isi_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":isi_docentry" => $Data['isi_docentry']));

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
	public function getExitInventoryDetail_get(){

				$Data = $this->get();

				if(!isset($Data['si1_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM isi WHERE si1_docentry =:si1_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":si1_docentry" => $Data['si1_docentry']));

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
	public function getExitInventoryBySN_get(){

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

				$sqlSelect = " SELECT * FROM misi WHERE isi_cardcode =:isi_cardcode";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":isi_cardcode" => $Data['dms_card_code']));

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
