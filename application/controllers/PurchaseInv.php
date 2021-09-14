<?php
//FACTURA DE COMPRA
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PurchaseInv extends REST_Controller {

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

// CREAR FACTURA DE COMPRAS
    	public function createPurchaseInv_post(){

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

          $sqlInsert = "INSERT INTO dcfc(cfc_docnum,cfc_docdate, cfc_duedate, cfc_duedev, cfc_pricelist, cfc_cardcode, cfc_cardname, cfc_currency, cfc_contacid, cfc_slpcode, cfc_empid, cfc_comment, cfc_doctotal, cfc_baseamnt, cfc_taxtotal, cfc_discprofit, cfc_discount, cfc_createat, cfc_baseentry, cfc_basetype, cfc_doctype, cfc_idadd, cfc_adress, cfc_paytype, cfc_attch, cfc_docentry, cfc_series, cfc_createby)
                      	VALUES (:cfc_docnum,:cfc_docdate, :cfc_duedate, :cfc_duedev, :cfc_pricelist, :cfc_cardcode, :cfc_cardname, :cfc_currency, :cfc_contacid, :cfc_slpcode, :cfc_empid, :cfc_comment, :cfc_doctotal, :cfc_baseamnt, :cfc_taxtotal, :cfc_discprofit, :cfc_discount, :cfc_createat, :cfc_baseentry, :cfc_basetype, :cfc_doctype, :cfc_idadd, :cfc_adress, :cfc_paytype, :cfc_attch, :cfc_docentry, :cfc_series,:cfc_createby)";


          // Se Inicia la transaccion,
          // Todas las consultas de modificacion siguientes
          // aplicaran solo despues que se confirme la transaccion,
          // de lo contrario no se aplicaran los cambios y se devolvera
          // la base de datos a su estado original.

          $this->pedeo->trans_begin();

          $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            ':cfc_docnum' => $DocNumVerificado,
            ':cfc_docdate' => $this->validateDate($Data['cfc_docdate'])?$Data['cfc_docdate']:NULL,
            ':cfc_duedate' => $this->validateDate($Data['cfc_duedate'])?$Data['cfc_duedate']:NULL,
            ':cfc_duedev' =>  $this->validateDate($Data['cfc_duedev'])?$Data['cfc_duedev']:NULL,
            ':cfc_pricelist' => is_numeric($Data['cfc_pricelist'])?$Data['cfc_pricelist']:0,
            ':cfc_cardcode' => isset($Data['cfc_cardcode'])?$Data['cfc_cardcode']:NULL,
            ':cfc_cardname' =>  isset($Data['cfc_cardname'])?$Data['cfc_cardname']:NULL,
            ':cfc_currency' => is_numeric($Data['cfc_currency'])?$Data['cfc_currency']:0,,
            ':cfc_contacid' => isset($Data['cfc_contacid'])?$Data['cfc_contacid']:NULL,
            ':cfc_slpcode' => is_numeric($Data['cfc_slpcode'])?$Data['cfc_slpcode']:0,
            ':cfc_empid' => is_numeric($Data['cfc_empid'])?$Data['cfc_empid']:0,
            ':cfc_comment' =>  isset($Data['cfc_comment'])?$Data['cfc_comment']:NULL,
            ':cfc_doctotal' => is_numeric($Data['cfc_doctotal'])?$Data['cfc_doctotal']:0,
            ':cfc_baseamnt' => is_numeric($Data['cfc_baseamnt'])?$Data['cfc_baseamnt']:0,
            ':cfc_taxtotal' => is_numeric($Data['cfc_taxtotal'])?$Data['cfc_taxtotal']:0,
            ':cfc_discprofit' => is_numeric($Data['cfc_discprofit'])?$Data['cfc_discprofit']:0,
            ':cfc_discount' => is_numeric($Data['cfc_discount'])?$Data['cfc_discount']:0,
            ':cfc_createat' => $this->validateDate($Data['cfc_createat'])?$Data['cfc_createat']:NULL,
            ':cfc_baseentry' => is_numeric($Data['cfc_baseentry'])?$Data['cfc_baseentry']:0,
            ':cfc_basetype' =>  is_numeric($Data['cfc_basetype'])?$Data['cfc_basetype']:0,
            ':cfc_doctype' => is_numeric($Data['cfc_doctype'])?$Data['cfc_doctype']:0,
            ':cfc_idadd' => isset($Data['cfc_idadd'])?$Data['cfc_idadd']:NULL,
            ':cfc_adress' => isset($Data['cfc_adress'])?$Data['cfc_adress']:NULL,
            ':cfc_paytype' => is_numeric($Data['cfc_paytype'])?$Data['cfc_paytype']:0,
            ':cfc_attch' => $this->getUrl(count(trim(($Data['dpo_attch']))) > 0 ? $Data['dpo_attch']:NULL, $resMainFolder[0]['main_folder']),
            ':cfc_series' => is_numeric($Data['cfc_series'])?$Data['cfc_series']:0,
            ':cfc_createby' => isset($Data['cfc_createby'])?$Data['cfc_createby']:NULL

         ));

         if(is_numeric($resInsert) && $resInsert > 0){

           // INICIO DETALLE

           foreach ($ContenidoDetalle as $key => $detail) {

                 $sqlInsertDetail = "INSERT INTO cfc1(fc1_docentry, fc1_itemcode, fc1_itemname, fc1_quantity, fc1_uom, fc1_whscode, fc1_price, fc1_vat, fc1_vatsum, fc1_discount, fc1_linetotal, fc1_costcode, fc1_ubusiness, fc1_project, fc1_acctcode, fc1_basetype, fc1_doctype, fc1_avprice, fc1_inventory, fc1_linenum)
                              	     VALUES (:fc1_docentry, :fc1_itemcode, :fc1_itemname, :fc1_quantity, :fc1_uom, :fc1_whscode, :fc1_price, :fc1_vat, :fc1_vatsum, :fc1_discount, :fc1_linetotal, :fc1_costcode, :fc1_ubusiness, :fc1_project, :fc1_acctcode, :fc1_basetype, :fc1_doctype, :fc1_avprice, :fc1_inventory, :fc1_linenum)";

                 $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(


                         ':fc1_docentry' => $resInsert,
                         ':fc1_itemcode' => isset($detail['fc1_itemcode'])?$detail['fc1_itemcode']:NULL,
                         ':fc1_itemname' => isset($detail['fc1_itemname'])?$detail['fc1_itemname']:NULL,
                         ':fc1_quantity' => is_numeric($detail['fc1_quantity'])?$detail['fc1_quantity']:0,
                         ':fc1_uom' => isset($detail['fc1_uom'])?$detail['fc1_uom']:NULL,
                         ':fc1_whscode' => isset($detail['fc1_whscode'])?$detail['fc1_whscode']:NULL,
                         ':fc1_price' => is_numeric($detail['fc1_price'])?$detail['fc1_price']:0,
                         ':fc1_vat' => is_numeric($detail['fc1_vat'])?$detail['fc1_vat']:0,
                         ':fc1_vatsum' => is_numeric($detail['fc1_vatsum'])?$detail['fc1_vatsum']:0,
                         ':fc1_discount' => is_numeric($detail['fc1_discount'])?$detail['fc1_discount']:0,
                         ':fc1_linetotal' => is_numeric($detail['fc1_linetotal'])?$detail['fc1_linetotal']:0,
                         ':fc1_costcode' => isset($detail['fc1_costcode'])?$detail['fc1_costcode']:NULL,
                         ':fc1_ubusiness' => isset($detail['fc1_ubusiness'])?$detail['fc1_ubusiness']:NULL,
                         ':fc1_project' => isset($detail['fc1_project'])?$detail['fc1_project']:NULL,
                         ':fc1_acctcode' => is_numeric($detail['fc1_acctcode'])?$detail['fc1_acctcode']:0,
                         ':fc1_basetype' => is_numeric($detail['fc1_basetype'])?$detail['fc1_basetype']:0,
                         ':fc1_doctype' => is_numeric($detail['fc1_doctype'])?$detail['fc1_doctype']:0,
                         ':fc1_avprice' => is_numeric($detail['fc1_avprice'])?$detail['fc1_avprice']:0,
                         ':fc1_inventory' => is_numeric($detail['fc1_inventory'])?$detail['fc1_inventory']:NULL,
                         ':fc1_linenum' => is_numeric($detail['fc1_inventory'])?$detail['fc1_inventory']:NULL
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
                         'mensaje'	=> 'No se pudo registrar la nota de credito'
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
              'mensaje' =>'Factura de compra registrada con exito'
            );

         }else{
 					// Se devuelven los cambios realizados en la transaccion
 					// si occurre un error  y se muestra devuelve el error.
 							$this->pedeo->trans_rollback();

               $respuesta = array(
                 'error'   => true,
                 'data' => $resInsert,
                 'mensaje'	=> 'No se pudo registrar la factura de compra'
               );

         }

          $this->response($respuesta);


      }


      //ACTUALIZAR NOTA CREDITO DE COMPRA
      public function updatePurchaseInv_post(){

          $Data = $this->post();

    			if(!isset($Data['detail']) OR !isset($Data['cfc_docentry'])){

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
                'mensaje' =>'No se encontro el detalle de la  factura de compra'
              );

              $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

              return;
          }

          $sqlUpdate = "UPDATE dcfc	SET cfc_docdate=:cfc_docdate, cfc_duedate=:cfc_duedate, cfc_duedev=:cfc_duedev,
          cfc_pricelist=:cfc_pricelist,cfc_cardcode=:cfc_cardcode, cfc_cardname=:cfc_cardname, cfc_contacid=:cfc_contacid,
          cfc_slpcode=:cfc_slpcode, cfc_empid=:cfc_empid, cfc_comment=:cfc_comment, cfc_doctotal=:cfc_doctotal,
          cfc_baseamnt=:cfc_baseamnt, cfc_taxtotal=:cfc_taxtotal, cfc_discprofit=:cfc_discprofit, cfc_discount=:cfc_discount,
          cfc_createat=:cfc_createat, cfc_baseentry=:cfc_baseentry, cfc_basetype=:cfc_basetype, cfc_doctype=:cfc_doctype,
          cfc_idadd=:cfc_idadd, cfc_adress=:cfc_adress, cfc_paytype=:cfc_paytype, cfc_attch=:cfc_attch,
          cfc_series=:cfc_series, cfc_createby=:cfc_createby, cfc_currency=:cfc_currency WHERE cfc_docentry = :cfc_docentry";

          $this->pedeo->trans_begin();

          $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
    							':cfc_docdate' => $this->validateDate($Data['cfc_docdate'])?$Data['cfc_docdate']:NULL,
    							':cfc_duedate' => $this->validateDate($Data['cfc_duedate'])?$Data['cfc_duedate']:NULL,
    							':cfc_duedev' => $this->validateDate($Data['cfc_duedev'])?$Data['cfc_duedev']:NULL,
    							':cfc_pricelist' => is_numeric($Data['cfc_pricelist'])?$Data['cfc_pricelist']:0,
    							':cfc_cardcode' => isset($Data['cfc_cardcode'])?$Data['cfc_cardcode']:NULL,
    							':cfc_cardname' => isset($Data['cfc_cardname'])?$Data['cfc_cardname']:NULL,
    							':cfc_currency' => is_numeric($Data['cfc_currency'])?$Data['cfc_currency']:0,
    							':cfc_contacid' => isset($Data['cfc_contacid'])?$Data['cfc_contacid']:NULL,
    							':cfc_slpcode' => is_numeric($Data['cfc_slpcode'])?$Data['cfc_slpcode']:0,
    							':cfc_empid' => is_numeric($Data['cfc_empid'])?$Data['cfc_empid']:0,
    							':cfc_comment' => isset($Data['cfc_comment'])?$Data['cfc_comment']:NULL,
    							':cfc_doctotal' => is_numeric($Data['cfc_doctotal'])?$Data['cfc_doctotal']:0,
    							':cfc_baseamnt' => is_numeric($Data['cfc_baseamnt'])?$Data['cfc_baseamnt']:0,
    							':cfc_taxtotal' => is_numeric($Data['cfc_taxtotal'])?$Data['cfc_taxtotal']:0,
    							':cfc_discprofit' => is_numeric($Data['cfc_discprofit'])?$Data['cfc_discprofit']:0,
    							':cfc_discount' => is_numeric($Data['cfc_discount'])?$Data['cfc_discount']:0,
    							':cfc_createat' => $this->validateDate($Data['cfc_createat'])?$Data['cfc_createat']:NULL,
    							':cfc_baseentry' => is_numeric($Data['cfc_baseentry'])?$Data['cfc_baseentry']:0,
    							':cfc_basetype' => is_numeric($Data['cfc_basetype'])?$Data['cfc_basetype']:0,
    							':cfc_doctype' => is_numeric($Data['cfc_doctype'])?$Data['cfc_doctype']:0,
    							':cfc_idadd' => isset($Data['cfc_idadd'])?$Data['cfc_idadd']:NULL,
    							':cfc_adress' => isset($Data['cfc_adress'])?$Data['cfc_adress']:NULL,
    							':cfc_paytype' => is_numeric($Data['cfc_paytype'])?$Data['cfc_paytype']:0,
    							':cfc_attch' => $this->getUrl(count(trim(($Data['cfc_attch']))) > 0 ? $Data['cfc_attch']:NULL),
    							':cfc_docentry' => $Data['cfc_docentry'],
                  ':cfc_series' => $Data['cfc_series'],
                  ':cfc_createby' => $Data['cfc_createby']
          ));

          if(is_numeric($resUpdate) && $resUpdate == 1){

    						$this->pedeo->queryTable("DELETE FROM cfc1 WHERE fc1_docentry=:fc1_docentry", array(':fc1_docentry' => $Data['fc1_docentry']));

    						foreach ($ContenidoDetalle as $key => $detail) {

    									$sqlInsertDetail = "INSERT INTO cfc1(fc1_docentry, fc1_itemcode, fc1_itemname, fc1_quantity, fc1_uom, fc1_whscode, fc1_price, fc1_vat, fc1_vatsum, fc1_discount, fc1_linetotal, fc1_costcode, fc1_ubusiness, fc1_project, fc1_acctcode, fc1_basetype, fc1_doctype, fc1_avprice, fc1_inventory,fc1_linenum)
	                                        VALUES (:fc1_docentry, :fc1_itemcode, :fc1_itemname, :fc1_quantity, :fc1_uom, :fc1_whscode, :fc1_price, :fc1_vat, :fc1_vatsum, :fc1_discount, :fc1_linetotal, :fc1_costcode, :fc1_ubusiness, :fc1_project, :fc1_acctcode, :fc1_basetype, :fc1_doctype, :fc1_avprice, :fc1_inventory, :fc1_linenum)";

    									$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
    											':fc1_docentry' => $resInsert,
    											':fc1_itemcode' => isset($detail['fc1_itemcode'])?$detail['fc1_itemcode']:NULL,
    											':fc1_itemname' => isset($detail['fc1_itemname'])?$detail['fc1_itemname']:NULL,
    											':fc1_quantity' => is_numeric($detail['fc1_quantity'])?$detail['fc1_quantity']:0,
    											':fc1_uom' => isset($detail['fc1_uom'])?$detail['fc1_uom']:NULL,
    											':fc1_whscode' => isset($detail['fc1_whscode'])?$detail['fc1_whscode']:NULL,
    											':fc1_price' => is_numeric($detail['fc1_price'])?$detail['fc1_price']:0,
    											':fc1_vat' => is_numeric($detail['fc1_vat'])?$detail['fc1_vat']:0,
    											':fc1_vatsum' => is_numeric($detail['fc1_vatsum'])?$detail['fc1_vatsum']:0,
    											':fc1_discount' => is_numeric($detail['fc1_discount'])?$detail['fc1_discount']:0,
    											':fc1_linetotal' => is_numeric($detail['fc1_linetotal'])?$detail['fc1_linetotal']:0,
    											':fc1_costcode' => isset($detail['fc1_costcode'])?$detail['fc1_costcode']:NULL,
    											':fc1_ubusiness' => isset($detail['fc1_ubusiness'])?$detail['fc1_ubusiness']:NULL,
    											':fc1_project' => isset($detail['fc1_project'])?$detail['fc1_project']:NULL,
    											':fc1_acctcode' => is_numeric($detail['fc1_acctcode'])?$detail['fc1_acctcode']:0,
    											':fc1_basetype' => is_numeric($detail['fc1_basetype'])?$detail['fc1_basetype']:0,
    											':fc1_doctype' => is_numeric($detail['fc1_doctype'])?$detail['fc1_doctype']:0,
    											':fc1_avprice' => is_numeric($detail['fc1_avprice'])?$detail['fc1_avprice']:0,
    											':fc1_inventory' => is_numeric($detail['fc1_inventory'])?$detail['fc1_inventory']:NULL,
                          ':fc1_linenum' => is_numeric($detail['fc1_linenum'])?$detail['fc1_linenum']:NULL
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
    													'mensaje'	=> 'No se pudo registrar la  factura de compra'
    												);

    												 $this->response($respuesta);

    												 return;
    									}
    						}


    						$this->pedeo->trans_commit();

                $respuesta = array(
                  'error' => false,
                  'data' => $resUpdate,
                  'mensaje' =>'Factura de compra actualizada con exito'
                );


          }else{

    						$this->pedeo->trans_rollback();

                $respuesta = array(
                  'error'   => true,
                  'data'    => $resUpdate,
                  'mensaje'	=> 'No se pudo actualizar la factura de compra'
                );

          }

           $this->response($respuesta);
      }


      //OBTENER FACTURA DE COMPRAS
      public function getPurchaseInv_get(){

            $sqlSelect = " SELECT * FROM dcfc ";

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


      //OBTENER FACTURA COMPRA POR ID
    	public function getPurchaseInvById_get(){

    				$Data = $this->get();

    				if(!isset($Data['cfc_docentry'])){

    					$respuesta = array(
    						'error' => true,
    						'data'  => array(),
    						'mensaje' =>'La informacion enviada no es valida'
    					);

    					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

    					return;
    				}

    				$sqlSelect = " SELECT * FROM dcfc  WHERE cfc_docentry =:cfc_docentry";

    				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":cfc_docentry" => $Data['cfc_docentry']));

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



      //OBTENER FACTURA DE COMPRA DETALLE POR ID
      public function getPurchaseInvDetail_get(){

            $Data = $this->get();

            if(!isset($Data['fc1_docentry'])){

              $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' =>'La informacion enviada no es valida'
              );

              $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

              return;
            }

            $sqlSelect = " SELECT * FROM cfc1 WHERE fc1_docentry =:fc1_docentry";

            $resSelect = $this->pedeo->queryTable($sqlSelect, array(":fc1_docentry" => $Data['fc1_docentry']));

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
