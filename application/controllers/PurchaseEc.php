<?php
//ENTRADA EN COMPRA
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PurchaseEc extends REST_Controller {

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

// CREAR ENTRADA EN COMPRAS
    	public function createPurchaseEc_post(){

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
                'mensaje' =>'No se encontro el detalle de la factura de compra'
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

          $sqlInsert = "INSERT INTO dcec(cec_docnum,cec_docdate, cec_duedate, cec_duedev, cec_pricelist, cec_cardcode, cec_cardname, cec_currency, cec_contacid, cec_slpcode, cec_empid, cec_comment, cec_doctotal, cec_baseamnt, cec_taxtotal, cec_discprofit, cec_discount, cec_createat, cec_baseentry, cec_basetype, cec_doctype, cec_idadd, cec_adress, cec_paytype, cec_attch, cec_docentry, cec_series, cec_createby)
                      	VALUES (:cec_docnum,:cec_docdate, :cec_duedate, :cec_duedev, :cec_pricelist, :cec_cardcode, :cec_cardname, :cec_currency, :cec_contacid, :cec_slpcode, :cec_empid, :cec_comment, :cec_doctotal, :cec_baseamnt, :cec_taxtotal, :cec_discprofit, :cec_discount, :cec_createat, :cec_baseentry, :cec_basetype, :cec_doctype, :cec_idadd, :cec_adress, :cec_paytype, :cec_attch, :cec_docentry, :cec_series,:cec_createby)";


          // Se Inicia la transaccion,
          // Todas las consultas de modificacion siguientes
          // aplicaran solo despues que se confirme la transaccion,
          // de lo contrario no se aplicaran los cambios y se devolvera
          // la base de datos a su estado original.

          $this->pedeo->trans_begin();

          $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            ':cec_docnum' => $DocNumVerificado,
            ':cec_docdate' => $this->validateDate($Data['cec_docdate'])?$Data['cec_docdate']:NULL,
            ':cec_duedate' => $this->validateDate($Data['cec_duedate'])?$Data['cec_duedate']:NULL,
            ':cec_duedev' =>  $this->validateDate($Data['cec_duedev'])?$Data['cec_duedev']:NULL,
            ':cec_pricelist' => is_numeric($Data['cec_pricelist'])?$Data['cec_pricelist']:0,
            ':cec_cardcode' => isset($Data['cec_cardcode'])?$Data['cec_cardcode']:NULL,
            ':cec_cardname' =>  isset($Data['cec_cardname'])?$Data['cec_cardname']:NULL,
            ':cec_currency' => is_numeric($Data['cec_currency'])?$Data['cec_currency']:0,,
            ':cec_contacid' => isset($Data['cec_contacid'])?$Data['cec_contacid']:NULL,
            ':cec_slpcode' => is_numeric($Data['cec_slpcode'])?$Data['cec_slpcode']:0,
            ':cec_empid' => is_numeric($Data['cec_empid'])?$Data['cec_empid']:0,
            ':cec_comment' =>  isset($Data['cec_comment'])?$Data['cec_comment']:NULL,
            ':cec_doctotal' => is_numeric($Data['cec_doctotal'])?$Data['cec_doctotal']:0,
            ':cec_baseamnt' => is_numeric($Data['cec_baseamnt'])?$Data['cec_baseamnt']:0,
            ':cec_taxtotal' => is_numeric($Data['cec_taxtotal'])?$Data['cec_taxtotal']:0,
            ':cec_discprofit' => is_numeric($Data['cec_discprofit'])?$Data['cec_discprofit']:0,
            ':cec_discount' => is_numeric($Data['cec_discount'])?$Data['cec_discount']:0,
            ':cec_createat' => $this->validateDate($Data['cec_createat'])?$Data['cec_createat']:NULL,
            ':cec_baseentry' => is_numeric($Data['cec_baseentry'])?$Data['cec_baseentry']:0,
            ':cec_basetype' =>  is_numeric($Data['cec_basetype'])?$Data['cec_basetype']:0,
            ':cec_doctype' => is_numeric($Data['cec_doctype'])?$Data['cec_doctype']:0,
            ':cec_idadd' => isset($Data['cec_idadd'])?$Data['cec_idadd']:NULL,
            ':cec_adress' => isset($Data['cec_adress'])?$Data['cec_adress']:NULL,
            ':cec_paytype' => is_numeric($Data['cec_paytype'])?$Data['cec_paytype']:0,
            ':cec_attch' => $this->getUrl(count(trim(($Data['dpo_attch']))) > 0 ? $Data['dpo_attch']:NULL, $resMainFolder[0]['main_folder']),
            ':cec_series' => is_numeric($Data['cec_series'])?$Data['cec_series']:0,
            ':cec_createby' => isset($Data['cec_createby'])?$Data['cec_createby']:NULL

         ));

         if(is_numeric($resInsert) && $resInsert > 0){

           // INICIO DETALLE

           foreach ($ContenidoDetalle as $key => $detail) {

                 $sqlInsertDetail = "INSERT INTO cec1(ec1_docentry, ec1_itemcode, ec1_itemname, ec1_quantity, ec1_uom, ec1_whscode, ec1_price, ec1_vat, ec1_vatsum, ec1_discount, ec1_linetotal, ec1_costcode, ec1_ubusiness, ec1_project, ec1_acctcode, ec1_basetype, ec1_doctype, ec1_avprice, ec1_inventory, ec1_linenum)
                              	     VALUES (:ec1_docentry, :ec1_itemcode, :ec1_itemname, :ec1_quantity, :ec1_uom, :ec1_whscode, :ec1_price, :ec1_vat, :ec1_vatsum, :ec1_discount, :ec1_linetotal, :ec1_costcode, :ec1_ubusiness, :ec1_project, :ec1_acctcode, :ec1_basetype, :ec1_doctype, :ec1_avprice, :ec1_inventory, :ec1_linenum)";

                 $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(


                         ':ec1_docentry' => $resInsert,
                         ':ec1_itemcode' => isset($detail['ec1_itemcode'])?$detail['ec1_itemcode']:NULL,
                         ':ec1_itemname' => isset($detail['ec1_itemname'])?$detail['ec1_itemname']:NULL,
                         ':ec1_quantity' => is_numeric($detail['ec1_quantity'])?$detail['ec1_quantity']:0,
                         ':ec1_uom' => isset($detail['ec1_uom'])?$detail['ec1_uom']:NULL,
                         ':ec1_whscode' => isset($detail['ec1_whscode'])?$detail['ec1_whscode']:NULL,
                         ':ec1_price' => is_numeric($detail['ec1_price'])?$detail['ec1_price']:0,
                         ':ec1_vat' => is_numeric($detail['ec1_vat'])?$detail['ec1_vat']:0,
                         ':ec1_vatsum' => is_numeric($detail['ec1_vatsum'])?$detail['ec1_vatsum']:0,
                         ':ec1_discount' => is_numeric($detail['ec1_discount'])?$detail['ec1_discount']:0,
                         ':ec1_linetotal' => is_numeric($detail['ec1_linetotal'])?$detail['ec1_linetotal']:0,
                         ':ec1_costcode' => isset($detail['ec1_costcode'])?$detail['ec1_costcode']:NULL,
                         ':ec1_ubusiness' => isset($detail['ec1_ubusiness'])?$detail['ec1_ubusiness']:NULL,
                         ':ec1_project' => isset($detail['ec1_project'])?$detail['ec1_project']:NULL,
                         ':ec1_acctcode' => is_numeric($detail['ec1_acctcode'])?$detail['ec1_acctcode']:0,
                         ':ec1_basetype' => is_numeric($detail['ec1_basetype'])?$detail['ec1_basetype']:0,
                         ':ec1_doctype' => is_numeric($detail['ec1_doctype'])?$detail['ec1_doctype']:0,
                         ':ec1_avprice' => is_numeric($detail['ec1_avprice'])?$detail['ec1_avprice']:0,
                         ':ec1_inventory' => is_numeric($detail['ec1_inventory'])?$detail['ec1_inventory']:NULL,
                         ':ec1_linenum' => is_numeric($detail['ec1_inventory'])?$detail['ec1_inventory']:NULL
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
                         'mensaje'	=> 'No se pudo registrar la entrada'
                       );

                        $this->response($respuesta);

                        return;
                 }

           }

           //FIN DETALLE

           // Si todo sale bien despues de insertar el detalle de la cotizacion
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


      //ACTUALIZAR ENTRADA EN COMPRA
      public function updatePurchaseEc_post(){

          $Data = $this->post();

    			if(!isset($Data['detail']) OR !isset($Data['cec_docentry'])){

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
                'mensaje' =>'No se encontro el detalle de la  entrada'
              );

              $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

              return;
          }

          $sqlUpdate = "UPDATE dcec	SET cec_docdate=:cec_docdate, cec_duedate=:cec_duedate, cec_duedev=:cec_duedev,
          cec_pricelist=:cec_pricelist,cec_cardcode=:cec_cardcode, cec_cardname=:cec_cardname, cec_contacid=:cec_contacid,
          cec_slpcode=:cec_slpcode, cec_empid=:cec_empid, cec_comment=:cec_comment, cec_doctotal=:cec_doctotal,
          cec_baseamnt=:cec_baseamnt, cec_taxtotal=:cec_taxtotal, cec_discprofit=:cec_discprofit, cec_discount=:cec_discount,
          cec_createat=:cec_createat, cec_baseentry=:cec_baseentry, cec_basetype=:cec_basetype, cec_doctype=:cec_doctype,
          cec_idadd=:cec_idadd, cec_adress=:cec_adress, cec_paytype=:cec_paytype, cec_attch=:cec_attch,
          cec_series=:cec_series, cec_createby=:cec_createby, cec_currency=:cec_currency WHERE cec_docentry = :cec_docentry";

          $this->pedeo->trans_begin();

          $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
    							':cec_docdate' => $this->validateDate($Data['cec_docdate'])?$Data['cec_docdate']:NULL,
    							':cec_duedate' => $this->validateDate($Data['cec_duedate'])?$Data['cec_duedate']:NULL,
    							':cec_duedev' => $this->validateDate($Data['cec_duedev'])?$Data['cec_duedev']:NULL,
    							':cec_pricelist' => is_numeric($Data['cec_pricelist'])?$Data['cec_pricelist']:0,
    							':cec_cardcode' => isset($Data['cec_cardcode'])?$Data['cec_cardcode']:NULL,
    							':cec_cardname' => isset($Data['cec_cardname'])?$Data['cec_cardname']:NULL,
    							':cec_currency' => is_numeric($Data['cec_currency'])?$Data['cec_currency']:0,
    							':cec_contacid' => isset($Data['cec_contacid'])?$Data['cec_contacid']:NULL,
    							':cec_slpcode' => is_numeric($Data['cec_slpcode'])?$Data['cec_slpcode']:0,
    							':cec_empid' => is_numeric($Data['cec_empid'])?$Data['cec_empid']:0,
    							':cec_comment' => isset($Data['cec_comment'])?$Data['cec_comment']:NULL,
    							':cec_doctotal' => is_numeric($Data['cec_doctotal'])?$Data['cec_doctotal']:0,
    							':cec_baseamnt' => is_numeric($Data['cec_baseamnt'])?$Data['cec_baseamnt']:0,
    							':cec_taxtotal' => is_numeric($Data['cec_taxtotal'])?$Data['cec_taxtotal']:0,
    							':cec_discprofit' => is_numeric($Data['cec_discprofit'])?$Data['cec_discprofit']:0,
    							':cec_discount' => is_numeric($Data['cec_discount'])?$Data['cec_discount']:0,
    							':cec_createat' => $this->validateDate($Data['cec_createat'])?$Data['cec_createat']:NULL,
    							':cec_baseentry' => is_numeric($Data['cec_baseentry'])?$Data['cec_baseentry']:0,
    							':cec_basetype' => is_numeric($Data['cec_basetype'])?$Data['cec_basetype']:0,
    							':cec_doctype' => is_numeric($Data['cec_doctype'])?$Data['cec_doctype']:0,
    							':cec_idadd' => isset($Data['cec_idadd'])?$Data['cec_idadd']:NULL,
    							':cec_adress' => isset($Data['cec_adress'])?$Data['cec_adress']:NULL,
    							':cec_paytype' => is_numeric($Data['cec_paytype'])?$Data['cec_paytype']:0,
    							':cec_attch' => $this->getUrl(count(trim(($Data['cec_attch']))) > 0 ? $Data['cec_attch']:NULL),
    							':cec_docentry' => $Data['cec_docentry'],
                  ':cec_series' => $Data['cec_series'],
                  ':cec_createby' => $Data['cec_createby']
          ));

          if(is_numeric($resUpdate) && $resUpdate == 1){

    						$this->pedeo->queryTable("DELETE FROM cec1 WHERE ec1_docentry=:ec1_docentry", array(':ec1_docentry' => $Data['ec1_docentry']));

    						foreach ($ContenidoDetalle as $key => $detail) {

    									$sqlInsertDetail = "INSERT INTO cec1(ec1_docentry, ec1_itemcode, ec1_itemname, ec1_quantity, ec1_uom, ec1_whscode, ec1_price, ec1_vat, ec1_vatsum, ec1_discount, ec1_linetotal, ec1_costcode, ec1_ubusiness, ec1_project, ec1_acctcode, ec1_basetype, ec1_doctype, ec1_avprice, ec1_inventory,ec1_linenum)
	                                        VALUES (:ec1_docentry, :ec1_itemcode, :ec1_itemname, :ec1_quantity, :ec1_uom, :ec1_whscode, :ec1_price, :ec1_vat, :ec1_vatsum, :ec1_discount, :ec1_linetotal, :ec1_costcode, :ec1_ubusiness, :ec1_project, :ec1_acctcode, :ec1_basetype, :ec1_doctype, :ec1_avprice, :ec1_inventory, :ec1_linenum)";

    									$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
    											':ec1_docentry' => $resInsert,
    											':ec1_itemcode' => isset($detail['ec1_itemcode'])?$detail['ec1_itemcode']:NULL,
    											':ec1_itemname' => isset($detail['ec1_itemname'])?$detail['ec1_itemname']:NULL,
    											':ec1_quantity' => is_numeric($detail['ec1_quantity'])?$detail['ec1_quantity']:0,
    											':ec1_uom' => isset($detail['ec1_uom'])?$detail['ec1_uom']:NULL,
    											':ec1_whscode' => isset($detail['ec1_whscode'])?$detail['ec1_whscode']:NULL,
    											':ec1_price' => is_numeric($detail['ec1_price'])?$detail['ec1_price']:0,
    											':ec1_vat' => is_numeric($detail['ec1_vat'])?$detail['ec1_vat']:0,
    											':ec1_vatsum' => is_numeric($detail['ec1_vatsum'])?$detail['ec1_vatsum']:0,
    											':ec1_discount' => is_numeric($detail['ec1_discount'])?$detail['ec1_discount']:0,
    											':ec1_linetotal' => is_numeric($detail['ec1_linetotal'])?$detail['ec1_linetotal']:0,
    											':ec1_costcode' => isset($detail['ec1_costcode'])?$detail['ec1_costcode']:NULL,
    											':ec1_ubusiness' => isset($detail['ec1_ubusiness'])?$detail['ec1_ubusiness']:NULL,
    											':ec1_project' => isset($detail['ec1_project'])?$detail['ec1_project']:NULL,
    											':ec1_acctcode' => is_numeric($detail['ec1_acctcode'])?$detail['ec1_acctcode']:0,
    											':ec1_basetype' => is_numeric($detail['ec1_basetype'])?$detail['ec1_basetype']:0,
    											':ec1_doctype' => is_numeric($detail['ec1_doctype'])?$detail['ec1_doctype']:0,
    											':ec1_avprice' => is_numeric($detail['ec1_avprice'])?$detail['ec1_avprice']:0,
    											':ec1_inventory' => is_numeric($detail['ec1_inventory'])?$detail['ec1_inventory']:NULL,
                          ':ec1_linenum' => is_numeric($detail['ec1_linenum'])?$detail['ec1_linenum']:NULL
    									));

    									if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
    											// Se verifica que el detalle no de error insertando //
    									}else{

    											// si falla algun insert del detalle de la orden de compra se devuelven los cambios realizados por la transaccion,
    											// se retorna el error y se detiene la ejecucion del codigo restante.
    												$this->pedeo->trans_rollback();

    												$respuesta = array(
    													'error'   => true,
    													'data' => $resInsert,
    													'mensaje'	=> 'No se pudo registrar la entrada'
    												);

    												 $this->response($respuesta);

    												 return;
    									}
    						}


    						$this->pedeo->trans_commit();

                $respuesta = array(
                  'error' => false,
                  'data' => $resUpdate,
                  'mensaje' =>'Entrada actualizada con exito'
                );


          }else{

    						$this->pedeo->trans_rollback();

                $respuesta = array(
                  'error'   => true,
                  'data'    => $resUpdate,
                  'mensaje'	=> 'No se pudo actualizar la entrada'
                );

          }

           $this->response($respuesta);
      }


      //OBTENER ENTRADA EN COMPRAS
      public function getPurchaseEc_get(){

            $sqlSelect = " SELECT * FROM dcec ";

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


      //OBTENER ENTRADA EN COMPRA POR ID
    	public function getPurchaseEcById_get(){

    				$Data = $this->get();

    				if(!isset($Data['cec_docentry'])){

    					$respuesta = array(
    						'error' => true,
    						'data'  => array(),
    						'mensaje' =>'La informacion enviada no es valida'
    					);

    					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

    					return;
    				}

    				$sqlSelect = " SELECT * FROM dcec  WHERE cec_docentry =:cec_docentry";

    				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":cec_docentry" => $Data['cec_docentry']));

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



      //OBTENER ENTRADA EN COMPRA DETALLE POR ID
      public function getPurchaseEcDetail_get(){

            $Data = $this->get();

            if(!isset($Data['ec1_docentry'])){

              $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' =>'La informacion enviada no es valida'
              );

              $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

              return;
            }

            $sqlSelect = " SELECT * FROM cec1 WHERE ec1_docentry =:ec1_docentry";

            $resSelect = $this->pedeo->queryTable($sqlSelect, array(":ec1_docentry" => $Data['ec1_docentry']));

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











      //**************************************//************************

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

?>
