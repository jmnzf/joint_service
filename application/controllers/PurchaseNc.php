<?php
// COMPRAS NOTA CREDITO
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PurchaseNc extends REST_Controller {

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

// CREAR NOTA CREDITO DE COMPRAS
    	public function createPurchaseNc_post(){

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

          $sqlInsert = "INSERT INTO dcnc(cnc_docnum,cnc_docdate, cnc_duedate, cnc_duedev, cnc_pricelist, cnc_cardcode, cnc_cardname, cnc_currency, cnc_contacid, cnc_slpcode, cnc_empid, cnc_comment, cnc_doctotal, cnc_baseamnt, cnc_taxtotal, cnc_discprofit, cnc_discount, cnc_createat, cnc_baseentry, cnc_basetype, cnc_doctype, cnc_idadd, cnc_adress, cnc_paytype, cnc_attch, cnc_docentry, cnc_series, cnc_createby)
                      	VALUES (:cnc_docnum,:cnc_docdate, :cnc_duedate, :cnc_duedev, :cnc_pricelist, :cnc_cardcode, :cnc_cardname, :cnc_currency, :cnc_contacid, :cnc_slpcode, :cnc_empid, :cnc_comment, :cnc_doctotal, :cnc_baseamnt, :cnc_taxtotal, :cnc_discprofit, :cnc_discount, :cnc_createat, :cnc_baseentry, :cnc_basetype, :cnc_doctype, :cnc_idadd, :cnc_adress, :cnc_paytype, :cnc_attch, :cnc_docentry, :cnc_series,:cnc_createby)";


          // Se Inicia la transaccion,
          // Todas las consultas de modificacion siguientes
          // aplicaran solo despues que se confirme la transaccion,
          // de lo contrario no se aplicaran los cambios y se devolvera
          // la base de datos a su estado original.

          $this->pedeo->trans_begin();

          $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            ':cnc_docnum' => $DocNumVerificado,
            ':cnc_docdate' => $this->validateDate($Data['cnc_docdate'])?$Data['cnc_docdate']:NULL,
            ':cnc_duedate' => $this->validateDate($Data['cnc_duedate'])?$Data['cnc_duedate']:NULL,
            ':cnc_duedev' =>  $this->validateDate($Data['cnc_duedev'])?$Data['cnc_duedev']:NULL,
            ':cnc_pricelist' => is_numeric($Data['cnc_pricelist'])?$Data['cnc_pricelist']:0,
            ':cnc_cardcode' => isset($Data['cnc_cardcode'])?$Data['cnc_cardcode']:NULL,
            ':cnc_cardname' =>  isset($Data['cnc_cardname'])?$Data['cnc_cardname']:NULL,
            ':cnc_currency' => is_numeric($Data['cnc_currency'])?$Data['cnc_currency']:0,,
            ':cnc_contacid' => isset($Data['cnc_contacid'])?$Data['cnc_contacid']:NULL,
            ':cnc_slpcode' => is_numeric($Data['cnc_slpcode'])?$Data['cnc_slpcode']:0,
            ':cnc_empid' => is_numeric($Data['cnc_empid'])?$Data['cnc_empid']:0,
            ':cnc_comment' =>  isset($Data['cnc_comment'])?$Data['cnc_comment']:NULL,
            ':cnc_doctotal' => is_numeric($Data['cnc_doctotal'])?$Data['cnc_doctotal']:0,
            ':cnc_baseamnt' => is_numeric($Data['cnc_baseamnt'])?$Data['cnc_baseamnt']:0,
            ':cnc_taxtotal' => is_numeric($Data['cnc_taxtotal'])?$Data['cnc_taxtotal']:0,
            ':cnc_discprofit' => is_numeric($Data['cnc_discprofit'])?$Data['cnc_discprofit']:0,
            ':cnc_discount' => is_numeric($Data['cnc_discount'])?$Data['cnc_discount']:0,
            ':cnc_createat' => $this->validateDate($Data['cnc_createat'])?$Data['cnc_createat']:NULL,
            ':cnc_baseentry' => is_numeric($Data['cnc_baseentry'])?$Data['cnc_baseentry']:0,
            ':cnc_basetype' =>  is_numeric($Data['cnc_basetype'])?$Data['cnc_basetype']:0,
            ':cnc_doctype' => is_numeric($Data['cnc_doctype'])?$Data['cnc_doctype']:0,
            ':cnc_idadd' => isset($Data['cnc_idadd'])?$Data['cnc_idadd']:NULL,
            ':cnc_adress' => isset($Data['cnc_adress'])?$Data['cnc_adress']:NULL,
            ':cnc_paytype' => is_numeric($Data['cnc_paytype'])?$Data['cnc_paytype']:0,
            ':cnc_attch' => $this->getUrl(count(trim(($Data['dpo_attch']))) > 0 ? $Data['dpo_attch']:NULL, $resMainFolder[0]['main_folder']),
            ':cnc_series' => is_numeric($Data['cnc_series'])?$Data['cnc_series']:0,
            ':cnc_createby' => isset($Data['cnc_createby'])?$Data['cnc_createby']:NULL

         ));

         if(is_numeric($resInsert) && $resInsert > 0){

           // INICIO DETALLE

           foreach ($ContenidoDetalle as $key => $detail) {

                 $sqlInsertDetail = "INSERT INTO cnc1(nc1_docentry, nc1_itemcode, nc1_itemname, nc1_quantity, nc1_uom, nc1_whscode, nc1_price, nc1_vat, nc1_vatsum, nc1_discount, nc1_linetotal, nc1_costcode, nc1_ubusiness, nc1_project, nc1_acctcode, nc1_basetype, nc1_doctype, nc1_avprice, nc1_inventory, nc1_linenum)
                              	     VALUES (:nc1_docentry, :nc1_itemcode, :nc1_itemname, :nc1_quantity, :nc1_uom, :nc1_whscode, :nc1_price, :nc1_vat, :nc1_vatsum, :nc1_discount, :nc1_linetotal, :nc1_costcode, :nc1_ubusiness, :nc1_project, :nc1_acctcode, :nc1_basetype, :nc1_doctype, :nc1_avprice, :nc1_inventory, :nc1_linenum)";

                 $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(


                         ':nc1_docentry' => $resInsert,
                         ':nc1_itemcode' => isset($detail['nc1_itemcode'])?$detail['nc1_itemcode']:NULL,
                         ':nc1_itemname' => isset($detail['nc1_itemname'])?$detail['nc1_itemname']:NULL,
                         ':nc1_quantity' => is_numeric($detail['nc1_quantity'])?$detail['nc1_quantity']:0,
                         ':nc1_uom' => isset($detail['nc1_uom'])?$detail['nc1_uom']:NULL,
                         ':nc1_whscode' => isset($detail['nc1_whscode'])?$detail['nc1_whscode']:NULL,
                         ':nc1_price' => is_numeric($detail['nc1_price'])?$detail['nc1_price']:0,
                         ':nc1_vat' => is_numeric($detail['nc1_vat'])?$detail['nc1_vat']:0,
                         ':nc1_vatsum' => is_numeric($detail['nc1_vatsum'])?$detail['nc1_vatsum']:0,
                         ':nc1_discount' => is_numeric($detail['nc1_discount'])?$detail['nc1_discount']:0,
                         ':nc1_linetotal' => is_numeric($detail['nc1_linetotal'])?$detail['nc1_linetotal']:0,
                         ':nc1_costcode' => isset($detail['nc1_costcode'])?$detail['nc1_costcode']:NULL,
                         ':nc1_ubusiness' => isset($detail['nc1_ubusiness'])?$detail['nc1_ubusiness']:NULL,
                         ':nc1_project' => isset($detail['nc1_project'])?$detail['nc1_project']:NULL,
                         ':nc1_acctcode' => is_numeric($detail['nc1_acctcode'])?$detail['nc1_acctcode']:0,
                         ':nc1_basetype' => is_numeric($detail['nc1_basetype'])?$detail['nc1_basetype']:0,
                         ':nc1_doctype' => is_numeric($detail['nc1_doctype'])?$detail['nc1_doctype']:0,
                         ':nc1_avprice' => is_numeric($detail['nc1_avprice'])?$detail['nc1_avprice']:0,
                         ':nc1_inventory' => is_numeric($detail['nc1_inventory'])?$detail['nc1_inventory']:NULL,
                         ':nc1_linenum' => is_numeric($detail['nc1_inventory'])?$detail['nc1_inventory']:NULL
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
              'mensaje' =>'Nota credito registrada con exito'
            );

         }else{
 					// Se devuelven los cambios realizados en la transaccion
 					// si occurre un error  y se muestra devuelve el error.
 							$this->pedeo->trans_rollback();

               $respuesta = array(
                 'error'   => true,
                 'data' => $resInsert,
                 'mensaje'	=> 'No se pudo registrar la nota credito'
               );

         }

          $this->response($respuesta);


      }


      //ACTUALIZAR NOTA CREDITO DE COMPRA
      public function updatePurchaseNc_post(){

          $Data = $this->post();

    			if(!isset($Data['detail']) OR !isset($Data['cnc_docentry'])){

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

          $sqlUpdate = "UPDATE dcnc	SET cnc_docdate=:cnc_docdate, cnc_duedate=:cnc_duedate, cnc_duedev=:cnc_duedev,
          cnc_pricelist=:cnc_pricelist,cnc_cardcode=:cnc_cardcode, cnc_cardname=:cnc_cardname, cnc_contacid=:cnc_contacid,
          cnc_slpcode=:cnc_slpcode, cnc_empid=:cnc_empid, cnc_comment=:cnc_comment, cnc_doctotal=:cnc_doctotal,
          cnc_baseamnt=:cnc_baseamnt, cnc_taxtotal=:cnc_taxtotal, cnc_discprofit=:cnc_discprofit, cnc_discount=:cnc_discount,
          cnc_createat=:cnc_createat, cnc_baseentry=:cnc_baseentry, cnc_basetype=:cnc_basetype, cnc_doctype=:cnc_doctype,
          cnc_idadd=:cnc_idadd, cnc_adress=:cnc_adress, cnc_paytype=:cnc_paytype, cnc_attch=:cnc_attch,
          cnc_series=:cnc_series, cnc_createby=:cnc_createby, cnc_currency=:cnc_currency WHERE cnc_docentry = :cnc_docentry";

          $this->pedeo->trans_begin();

          $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
    							':cnc_docdate' => $this->validateDate($Data['cnc_docdate'])?$Data['cnc_docdate']:NULL,
    							':cnc_duedate' => $this->validateDate($Data['cnc_duedate'])?$Data['cnc_duedate']:NULL,
    							':cnc_duedev' => $this->validateDate($Data['cnc_duedev'])?$Data['cnc_duedev']:NULL,
    							':cnc_pricelist' => is_numeric($Data['cnc_pricelist'])?$Data['cnc_pricelist']:0,
    							':cnc_cardcode' => isset($Data['cnc_cardcode'])?$Data['cnc_cardcode']:NULL,
    							':cnc_cardname' => isset($Data['cnc_cardname'])?$Data['cnc_cardname']:NULL,
    							':cnc_currency' => is_numeric($Data['cnc_currency'])?$Data['cnc_currency']:0,
    							':cnc_contacid' => isset($Data['cnc_contacid'])?$Data['cnc_contacid']:NULL,
    							':cnc_slpcode' => is_numeric($Data['cnc_slpcode'])?$Data['cnc_slpcode']:0,
    							':cnc_empid' => is_numeric($Data['cnc_empid'])?$Data['cnc_empid']:0,
    							':cnc_comment' => isset($Data['cnc_comment'])?$Data['cnc_comment']:NULL,
    							':cnc_doctotal' => is_numeric($Data['cnc_doctotal'])?$Data['cnc_doctotal']:0,
    							':cnc_baseamnt' => is_numeric($Data['cnc_baseamnt'])?$Data['cnc_baseamnt']:0,
    							':cnc_taxtotal' => is_numeric($Data['cnc_taxtotal'])?$Data['cnc_taxtotal']:0,
    							':cnc_discprofit' => is_numeric($Data['cnc_discprofit'])?$Data['cnc_discprofit']:0,
    							':cnc_discount' => is_numeric($Data['cnc_discount'])?$Data['cnc_discount']:0,
    							':cnc_createat' => $this->validateDate($Data['cnc_createat'])?$Data['cnc_createat']:NULL,
    							':cnc_baseentry' => is_numeric($Data['cnc_baseentry'])?$Data['cnc_baseentry']:0,
    							':cnc_basetype' => is_numeric($Data['cnc_basetype'])?$Data['cnc_basetype']:0,
    							':cnc_doctype' => is_numeric($Data['cnc_doctype'])?$Data['cnc_doctype']:0,
    							':cnc_idadd' => isset($Data['cnc_idadd'])?$Data['cnc_idadd']:NULL,
    							':cnc_adress' => isset($Data['cnc_adress'])?$Data['cnc_adress']:NULL,
    							':cnc_paytype' => is_numeric($Data['cnc_paytype'])?$Data['cnc_paytype']:0,
    							':cnc_attch' => $this->getUrl(count(trim(($Data['cnc_attch']))) > 0 ? $Data['cnc_attch']:NULL),
    							':cnc_docentry' => $Data['cnc_docentry'],
                  ':cnc_series' => $Data['cnc_series'],
                  ':cnc_createby' => $Data['cnc_createby']
          ));

          if(is_numeric($resUpdate) && $resUpdate == 1){

    						$this->pedeo->queryTable("DELETE FROM cnc1 WHERE nc1_docentry=:nc1_docentry", array(':nc1_docentry' => $Data['nc1_docentry']));

    						foreach ($ContenidoDetalle as $key => $detail) {

    									$sqlInsertDetail = "INSERT INTO cnc1(nc1_docentry, nc1_itemcode, nc1_itemname, nc1_quantity, nc1_uom, nc1_whscode, nc1_price, nc1_vat, nc1_vatsum, nc1_discount, nc1_linetotal, nc1_costcode, nc1_ubusiness, nc1_project, nc1_acctcode, nc1_basetype, nc1_doctype, nc1_avprice, nc1_inventory,nc1_linenum)
	                                        VALUES (:nc1_docentry, :nc1_itemcode, :nc1_itemname, :nc1_quantity, :nc1_uom, :nc1_whscode, :nc1_price, :nc1_vat, :nc1_vatsum, :nc1_discount, :nc1_linetotal, :nc1_costcode, :nc1_ubusiness, :nc1_project, :nc1_acctcode, :nc1_basetype, :nc1_doctype, :nc1_avprice, :nc1_inventory, :nc1_linenum)";

    									$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
    											':nc1_docentry' => $resInsert,
    											':nc1_itemcode' => isset($detail['nc1_itemcode'])?$detail['nc1_itemcode']:NULL,
    											':nc1_itemname' => isset($detail['nc1_itemname'])?$detail['nc1_itemname']:NULL,
    											':nc1_quantity' => is_numeric($detail['nc1_quantity'])?$detail['nc1_quantity']:0,
    											':nc1_uom' => isset($detail['nc1_uom'])?$detail['nc1_uom']:NULL,
    											':nc1_whscode' => isset($detail['nc1_whscode'])?$detail['nc1_whscode']:NULL,
    											':nc1_price' => is_numeric($detail['nc1_price'])?$detail['nc1_price']:0,
    											':nc1_vat' => is_numeric($detail['nc1_vat'])?$detail['nc1_vat']:0,
    											':nc1_vatsum' => is_numeric($detail['nc1_vatsum'])?$detail['nc1_vatsum']:0,
    											':nc1_discount' => is_numeric($detail['nc1_discount'])?$detail['nc1_discount']:0,
    											':nc1_linetotal' => is_numeric($detail['nc1_linetotal'])?$detail['nc1_linetotal']:0,
    											':nc1_costcode' => isset($detail['nc1_costcode'])?$detail['nc1_costcode']:NULL,
    											':nc1_ubusiness' => isset($detail['nc1_ubusiness'])?$detail['nc1_ubusiness']:NULL,
    											':nc1_project' => isset($detail['nc1_project'])?$detail['nc1_project']:NULL,
    											':nc1_acctcode' => is_numeric($detail['nc1_acctcode'])?$detail['nc1_acctcode']:0,
    											':nc1_basetype' => is_numeric($detail['nc1_basetype'])?$detail['nc1_basetype']:0,
    											':nc1_doctype' => is_numeric($detail['nc1_doctype'])?$detail['nc1_doctype']:0,
    											':nc1_avprice' => is_numeric($detail['nc1_avprice'])?$detail['nc1_avprice']:0,
    											':nc1_inventory' => is_numeric($detail['nc1_inventory'])?$detail['nc1_inventory']:NULL,
                          ':nc1_linenum' => is_numeric($detail['nc1_linenum'])?$detail['nc1_linenum']:NULL
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
    													'mensaje'	=> 'No se pudo registrar la nota credito'
    												);

    												 $this->response($respuesta);

    												 return;
    									}
    						}


    						$this->pedeo->trans_commit();

                $respuesta = array(
                  'error' => false,
                  'data' => $resUpdate,
                  'mensaje' =>'Nota credito actualizada con exito'
                );


          }else{

    						$this->pedeo->trans_rollback();

                $respuesta = array(
                  'error'   => true,
                  'data'    => $resUpdate,
                  'mensaje'	=> 'No se pudo actualizar la nota credito'
                );

          }

           $this->response($respuesta);
      }


      //OBTENER NOTA CREDITO DE COMPRAS
      public function getPurchaseNc_get(){

            $sqlSelect = " SELECT * FROM dcnc ";

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


      //OBTENER NOTA CREDITO DE COMPRA POR ID
    	public function getPurchaseNcById_get(){

    				$Data = $this->get();

    				if(!isset($Data['cnc_docentry'])){

    					$respuesta = array(
    						'error' => true,
    						'data'  => array(),
    						'mensaje' =>'La informacion enviada no es valida'
    					);

    					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

    					return;
    				}

    				$sqlSelect = " SELECT * FROM dcnc  WHERE cnc_docentry =:cnc_docentry";

    				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":cnc_docentry" => $Data['cnc_docentry']));

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



      //OBTENER NOTA CREDITO DE COMPRA DETALLE POR ID
      public function getPurchaseNcDetail_get(){

            $Data = $this->get();

            if(!isset($Data['nc1_docentry'])){

              $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' =>'La informacion enviada no es valida'
              );

              $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

              return;
            }

            $sqlSelect = " SELECT * FROM cnc1 WHERE nc1_docentry =:nc1_docentry";

            $resSelect = $this->pedeo->queryTable($sqlSelect, array(":nc1_docentry" => $Data['nc1_docentry']));

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
