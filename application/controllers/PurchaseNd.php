<?php
// COMPRAS NOTA CREDITO
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PurchaseNd extends REST_Controller {

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


    	public function createPurchaseNd_post(){

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
                'mensaje' =>'No se encontro el detalle de la solicitud de compra'
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

          $sqlInsert = "INSERT INTO dcnd(cnd_docnum,cnd_docdate, cnd_duedate, cnd_duedev, cnd_pricelist, cnd_cardcode, cnd_cardname, cnd_currency, cnd_contacid, cnd_slpcode, cnd_empid, cnd_comment, cnd_doctotal, cnd_baseamnt, cnd_taxtotal, cnd_discprofit, cnd_discount, cnd_createat, cnd_baseentry, cnd_basetype, cnd_doctype, cnd_idadd, cnd_adress, cnd_paytype, cnd_attch, cnd_docentry, cnd_series, cnd_createby)
                      	VALUES (:cnd_docnum,:cnd_docdate, :cnd_duedate, :cnd_duedev, :cnd_pricelist, :cnd_cardcode, :cnd_cardname, :cnd_currency, :cnd_contacid, :cnd_slpcode, :cnd_empid, :cnd_comment, :cnd_doctotal, :cnd_baseamnt, :cnd_taxtotal, :cnd_discprofit, :cnd_discount, :cnd_createat, :cnd_baseentry, :cnd_basetype, :cnd_doctype, :cnd_idadd, :cnd_adress, :cnd_paytype, :cnd_attch, :cnd_docentry, :cnd_series,:cnd_createby)";


          // Se Inicia la transaccion,
          // Todas las consultas de modificacion siguientes
          // aplicaran solo despues que se confirme la transaccion,
          // de lo contrario no se aplicaran los cambios y se devolvera
          // la base de datos a su estado original.

          $this->pedeo->trans_begin();

          $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            ':cnd_docnum' => $DocNumVerificado,
            ':cnd_docdate' => $this->validateDate($Data['cnd_docdate'])?$Data['cnd_docdate']:NULL,
            ':cnd_duedate' => $this->validateDate($Data['cnd_duedate'])?$Data['cnd_duedate']:NULL,
            ':cnd_duedev' =>  $this->validateDate($Data['cnd_duedev'])?$Data['cnd_duedev']:NULL,
            ':cnd_pricelist' => is_numeric($Data['cnd_pricelist'])?$Data['cnd_pricelist']:0,
            ':cnd_cardcode' => isset($Data['cnd_cardcode'])?$Data['cnd_cardcode']:NULL,
            ':cnd_cardname' =>  isset($Data['cnd_cardname'])?$Data['cnd_cardname']:NULL,
            ':cnd_currency' => is_numeric($Data['cnd_currency'])?$Data['cnd_currency']:0,,
            ':cnd_contacid' => isset($Data['cnd_contacid'])?$Data['cnd_contacid']:NULL,
            ':cnd_slpcode' => is_numeric($Data['cnd_slpcode'])?$Data['cnd_slpcode']:0,
            ':cnd_empid' => is_numeric($Data['cnd_empid'])?$Data['cnd_empid']:0,
            ':cnd_comment' =>  isset($Data['cnd_comment'])?$Data['cnd_comment']:NULL,
            ':cnd_doctotal' => is_numeric($Data['cnd_doctotal'])?$Data['cnd_doctotal']:0,
            ':cnd_baseamnt' => is_numeric($Data['cnd_baseamnt'])?$Data['cnd_baseamnt']:0,
            ':cnd_taxtotal' => is_numeric($Data['cnd_taxtotal'])?$Data['cnd_taxtotal']:0,
            ':cnd_discprofit' => is_numeric($Data['cnd_discprofit'])?$Data['cnd_discprofit']:0,
            ':cnd_discount' => is_numeric($Data['cnd_discount'])?$Data['cnd_discount']:0,
            ':cnd_createat' => $this->validateDate($Data['cnd_createat'])?$Data['cnd_createat']:NULL,
            ':cnd_baseentry' => is_numeric($Data['cnd_baseentry'])?$Data['cnd_baseentry']:0,
            ':cnd_basetype' =>  is_numeric($Data['cnd_basetype'])?$Data['cnd_basetype']:0,
            ':cnd_doctype' => is_numeric($Data['cnd_doctype'])?$Data['cnd_doctype']:0,
            ':cnd_idadd' => isset($Data['cnd_idadd'])?$Data['cnd_idadd']:NULL,
            ':cnd_adress' => isset($Data['cnd_adress'])?$Data['cnd_adress']:NULL,
            ':cnd_paytype' => is_numeric($Data['cnd_paytype'])?$Data['cnd_paytype']:0,
            ':cnd_attch' => $this->getUrl(count(trim(($Data['dpo_attch']))) > 0 ? $Data['dpo_attch']:NULL, $resMainFolder[0]['main_folder']),
            ':cnd_series' => is_numeric($Data['cnd_series'])?$Data['cnd_series']:0,
            ':cnd_createby' => isset($Data['cnd_createby'])?$Data['cnd_createby']:NULL

         ));

         if(is_numeric($resInsert) && $resInsert > 0){

           // INICIO DETALLE

           foreach ($ContenidoDetalle as $key => $detail) {

                 $sqlInsertDetail = "INSERT INTO cnd1(nd1_docentry, nd1_itemcode, nd1_itemname, nd1_quantity, nd1_uom, nd1_whscode, nd1_price, nd1_vat, nd1_vatsum, nd1_discount, nd1_linetotal, nd1_costcode, nd1_ubusiness, nd1_project, nd1_acctcode, nd1_basetype, nd1_doctype, nd1_avprice, nd1_inventory, nd1_linenum)
                              	     VALUES (:nd1_docentry, :nd1_itemcode, :nd1_itemname, :nd1_quantity, :nd1_uom, :nd1_whscode, :nd1_price, :nd1_vat, :nd1_vatsum, :nd1_discount, :nd1_linetotal, :nd1_costcode, :nd1_ubusiness, :nd1_project, :nd1_acctcode, :nd1_basetype, :nd1_doctype, :nd1_avprice, :nd1_inventory, :nd1_linenum)";

                 $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(


                         ':nd1_docentry' => $resInsert,
                         ':nd1_itemcode' => isset($detail['nd1_itemcode'])?$detail['nd1_itemcode']:NULL,
                         ':nd1_itemname' => isset($detail['nd1_itemname'])?$detail['nd1_itemname']:NULL,
                         ':nd1_quantity' => is_numeric($detail['nd1_quantity'])?$detail['nd1_quantity']:0,
                         ':nd1_uom' => isset($detail['nd1_uom'])?$detail['nd1_uom']:NULL,
                         ':nd1_whscode' => isset($detail['nd1_whscode'])?$detail['nd1_whscode']:NULL,
                         ':nd1_price' => is_numeric($detail['nd1_price'])?$detail['nd1_price']:0,
                         ':nd1_vat' => is_numeric($detail['nd1_vat'])?$detail['nd1_vat']:0,
                         ':nd1_vatsum' => is_numeric($detail['nd1_vatsum'])?$detail['nd1_vatsum']:0,
                         ':nd1_discount' => is_numeric($detail['nd1_discount'])?$detail['nd1_discount']:0,
                         ':nd1_linetotal' => is_numeric($detail['nd1_linetotal'])?$detail['nd1_linetotal']:0,
                         ':nd1_costcode' => isset($detail['nd1_costcode'])?$detail['nd1_costcode']:NULL,
                         ':nd1_ubusiness' => isset($detail['nd1_ubusiness'])?$detail['nd1_ubusiness']:NULL,
                         ':nd1_project' => isset($detail['nd1_project'])?$detail['nd1_project']:NULL,
                         ':nd1_acctcode' => is_numeric($detail['nd1_acctcode'])?$detail['nd1_acctcode']:0,
                         ':nd1_basetype' => is_numeric($detail['nd1_basetype'])?$detail['nd1_basetype']:0,
                         ':nd1_doctype' => is_numeric($detail['nd1_doctype'])?$detail['nd1_doctype']:0,
                         ':nd1_avprice' => is_numeric($detail['nd1_avprice'])?$detail['nd1_avprice']:0,
                         ':nd1_inventory' => is_numeric($detail['nd1_inventory'])?$detail['nd1_inventory']:NULL,
                         ':nd1_linenum' => is_numeric($detail['nd1_inventory'])?$detail['nd1_inventory']:NULL
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
                         'mensaje'	=> 'No se pudo registrar la nota de debito'
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
              'mensaje' =>'Nota debito registrada con exito'
            );

         }else{
 					// Se devuelven los cambios realizados en la transaccion
 					// si occurre un error  y se muestra devuelve el error.
 							$this->pedeo->trans_rollback();

               $respuesta = array(
                 'error'   => true,
                 'data' => $resInsert,
                 'mensaje'	=> 'No se pudo registrar la nota debito'
               );

         }

          $this->response($respuesta);


      }


      //ACTUALIZAR NOTA DEBITO DE COMPRA
      public function updatePurchaseNd_post(){

          $Data = $this->post();

    			if(!isset($Data['detail']) OR !isset($Data['cnd_docentry'])){

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
                'mensaje' =>'No se encontro el detalle de la solicitud de compra'
              );

              $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

              return;
          }

          $sqlUpdate = "UPDATE dcnd	SET cnd_docdate=:cnd_docdate, cnd_duedate=:cnd_duedate, cnd_duedev=:cnd_duedev,
          cnd_pricelist=:cnd_pricelist,cnd_cardcode=:cnd_cardcode, cnd_cardname=:cnd_cardname, cnd_contacid=:cnd_contacid,
          cnd_slpcode=:cnd_slpcode, cnd_empid=:cnd_empid, cnd_comment=:cnd_comment, cnd_doctotal=:cnd_doctotal,
          cnd_baseamnt=:cnd_baseamnt, cnd_taxtotal=:cnd_taxtotal, cnd_discprofit=:cnd_discprofit, cnd_discount=:cnd_discount,
          cnd_createat=:cnd_createat, cnd_baseentry=:cnd_baseentry, cnd_basetype=:cnd_basetype, cnd_doctype=:cnd_doctype,
          cnd_idadd=:cnd_idadd, cnd_adress=:cnd_adress, cnd_paytype=:cnd_paytype, cnd_attch=:cnd_attch,
          cnd_series=:cnd_series, cnd_createby=:cnd_createby, cnd_currency=:cnd_currency WHERE cnd_docentry = :cnd_docentry";

          $this->pedeo->trans_begin();

          $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
    							':cnd_docdate' => $this->validateDate($Data['cnd_docdate'])?$Data['cnd_docdate']:NULL,
    							':cnd_duedate' => $this->validateDate($Data['cnd_duedate'])?$Data['cnd_duedate']:NULL,
    							':cnd_duedev' => $this->validateDate($Data['cnd_duedev'])?$Data['cnd_duedev']:NULL,
    							':cnd_pricelist' => is_numeric($Data['cnd_pricelist'])?$Data['cnd_pricelist']:0,
    							':cnd_cardcode' => isset($Data['cnd_cardcode'])?$Data['cnd_cardcode']:NULL,
    							':cnd_cardname' => isset($Data['cnd_cardname'])?$Data['cnd_cardname']:NULL,
    							':cnd_currency' => is_numeric($Data['cnd_currency'])?$Data['cnd_currency']:0,
    							':cnd_contacid' => isset($Data['cnd_contacid'])?$Data['cnd_contacid']:NULL,
    							':cnd_slpcode' => is_numeric($Data['cnd_slpcode'])?$Data['cnd_slpcode']:0,
    							':cnd_empid' => is_numeric($Data['cnd_empid'])?$Data['cnd_empid']:0,
    							':cnd_comment' => isset($Data['cnd_comment'])?$Data['cnd_comment']:NULL,
    							':cnd_doctotal' => is_numeric($Data['cnd_doctotal'])?$Data['cnd_doctotal']:0,
    							':cnd_baseamnt' => is_numeric($Data['cnd_baseamnt'])?$Data['cnd_baseamnt']:0,
    							':cnd_taxtotal' => is_numeric($Data['cnd_taxtotal'])?$Data['cnd_taxtotal']:0,
    							':cnd_discprofit' => is_numeric($Data['cnd_discprofit'])?$Data['cnd_discprofit']:0,
    							':cnd_discount' => is_numeric($Data['cnd_discount'])?$Data['cnd_discount']:0,
    							':cnd_createat' => $this->validateDate($Data['cnd_createat'])?$Data['cnd_createat']:NULL,
    							':cnd_baseentry' => is_numeric($Data['cnd_baseentry'])?$Data['cnd_baseentry']:0,
    							':cnd_basetype' => is_numeric($Data['cnd_basetype'])?$Data['cnd_basetype']:0,
    							':cnd_doctype' => is_numeric($Data['cnd_doctype'])?$Data['cnd_doctype']:0,
    							':cnd_idadd' => isset($Data['cnd_idadd'])?$Data['cnd_idadd']:NULL,
    							':cnd_adress' => isset($Data['cnd_adress'])?$Data['cnd_adress']:NULL,
    							':cnd_paytype' => is_numeric($Data['cnd_paytype'])?$Data['cnd_paytype']:0,
    							':cnd_attch' => $this->getUrl(count(trim(($Data['cnd_attch']))) > 0 ? $Data['cnd_attch']:NULL),
    							':cnd_docentry' => $Data['cnd_docentry'],
                  ':cnd_series' => $Data['cnd_series'],
                  ':cnd_createby' => $Data['cnd_createby']
          ));

          if(is_numeric($resUpdate) && $resUpdate == 1){

    						$this->pedeo->queryTable("DELETE FROM cnd1 WHERE nd1_docentry=:nd1_docentry", array(':nd1_docentry' => $Data['nd1_docentry']));

    						foreach ($ContenidoDetalle as $key => $detail) {

    									$sqlInsertDetail = "INSERT INTO cnd1(nd1_docentry, nd1_itemcode, nd1_itemname, nd1_quantity, nd1_uom, nd1_whscode, nd1_price, nd1_vat, nd1_vatsum, nd1_discount, nd1_linetotal, nd1_costcode, nd1_ubusiness, nd1_project, nd1_acctcode, nd1_basetype, nd1_doctype, nd1_avprice, nd1_inventory,nd1_linenum)
	                                        VALUES (:nd1_docentry, :nd1_itemcode, :nd1_itemname, :nd1_quantity, :nd1_uom, :nd1_whscode, :nd1_price, :nd1_vat, :nd1_vatsum, :nd1_discount, :nd1_linetotal, :nd1_costcode, :nd1_ubusiness, :nd1_project, :nd1_acctcode, :nd1_basetype, :nd1_doctype, :nd1_avprice, :nd1_inventory, :nd1_linenum)";

    									$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
    											':nd1_docentry' => $resInsert,
    											':nd1_itemcode' => isset($detail['nd1_itemcode'])?$detail['nd1_itemcode']:NULL,
    											':nd1_itemname' => isset($detail['nd1_itemname'])?$detail['nd1_itemname']:NULL,
    											':nd1_quantity' => is_numeric($detail['nd1_quantity'])?$detail['nd1_quantity']:0,
    											':nd1_uom' => isset($detail['nd1_uom'])?$detail['nd1_uom']:NULL,
    											':nd1_whscode' => isset($detail['nd1_whscode'])?$detail['nd1_whscode']:NULL,
    											':nd1_price' => is_numeric($detail['nd1_price'])?$detail['nd1_price']:0,
    											':nd1_vat' => is_numeric($detail['nd1_vat'])?$detail['nd1_vat']:0,
    											':nd1_vatsum' => is_numeric($detail['nd1_vatsum'])?$detail['nd1_vatsum']:0,
    											':nd1_discount' => is_numeric($detail['nd1_discount'])?$detail['nd1_discount']:0,
    											':nd1_linetotal' => is_numeric($detail['nd1_linetotal'])?$detail['nd1_linetotal']:0,
    											':nd1_costcode' => isset($detail['nd1_costcode'])?$detail['nd1_costcode']:NULL,
    											':nd1_ubusiness' => isset($detail['nd1_ubusiness'])?$detail['nd1_ubusiness']:NULL,
    											':nd1_project' => isset($detail['nd1_project'])?$detail['nd1_project']:NULL,
    											':nd1_acctcode' => is_numeric($detail['nd1_acctcode'])?$detail['nd1_acctcode']:0,
    											':nd1_basetype' => is_numeric($detail['nd1_basetype'])?$detail['nd1_basetype']:0,
    											':nd1_doctype' => is_numeric($detail['nd1_doctype'])?$detail['nd1_doctype']:0,
    											':nd1_avprice' => is_numeric($detail['nd1_avprice'])?$detail['nd1_avprice']:0,
    											':nd1_inventory' => is_numeric($detail['nd1_inventory'])?$detail['nd1_inventory']:NULL,
                          ':nd1_linenum' => is_numeric($detail['nd1_linenum'])?$detail['nd1_linenum']:NULL
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
    													'mensaje'	=> 'No se pudo registrar la nota debito'
    												);

    												 $this->response($respuesta);

    												 return;
    									}
    						}


    						$this->pedeo->trans_commit();

                $respuesta = array(
                  'error' => false,
                  'data' => $resUpdate,
                  'mensaje' =>'Nota debito actualizada con exito'
                );


          }else{

    						$this->pedeo->trans_rollback();

                $respuesta = array(
                  'error'   => true,
                  'data'    => $resUpdate,
                  'mensaje'	=> 'No se pudo actualizar la nota debito'
                );

          }

           $this->response($respuesta);
      }


      //OBTENER NOTA DEBITO DE COMPRAS
      public function getPurchaseNd_get(){

            $sqlSelect = " SELECT * FROM dcnd ";

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


      //OBTENER NOTA DEBITO DE COMPRA POR ID
    	public function getPurchaseNdById_get(){

    				$Data = $this->get();

    				if(!isset($Data['cnd_docentry'])){

    					$respuesta = array(
    						'error' => true,
    						'data'  => array(),
    						'mensaje' =>'La informacion enviada no es valida'
    					);

    					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

    					return;
    				}

    				$sqlSelect = " SELECT * FROM dcnd  WHERE cnd_docentry =:cnd_docentry";

    				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":cnd_docentry" => $Data['cnd_docentry']));

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



      //OBTENER NOTA DEBITO DE COMPRA POR ID
      public function getPurchaseNdDetail_get(){

            $Data = $this->get();

            if(!isset($Data['nd1_docentry'])){

              $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' =>'La informacion enviada no es valida'
              );

              $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

              return;
            }

            $sqlSelect = " SELECT * FROM cnd1 WHERE nd1_docentry =:nd1_docentry";

            $resSelect = $this->pedeo->queryTable($sqlSelect, array(":nd1_docentry" => $Data['nd1_docentry']));

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
