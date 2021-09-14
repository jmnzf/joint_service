<?php
// SOLICITUD DE COMPRAS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PurchaseRequest extends REST_Controller {

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


    	public function createPurchaseRequest_post(){

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

          $sqlInsert = "INSERT INTO dcsc(csc_docnum,csc_docdate, csc_duedate, csc_duedev, csc_pricelist, csc_cardcode, csc_cardname, csc_currency, csc_contacid, csc_slpcode, csc_empid, csc_comment, csc_doctotal, csc_baseamnt, csc_taxtotal, csc_discprofit, csc_discount, csc_createat, csc_baseentry, csc_basetype, csc_doctype, csc_idadd, csc_adress, csc_paytype, csc_attch, csc_docentry, csc_series, csc_createby)
                      	VALUES (:csc_docnum,:csc_docdate, :csc_duedate, :csc_duedev, :csc_pricelist, :csc_cardcode, :csc_cardname, :csc_currency, :csc_contacid, :csc_slpcode, :csc_empid, :csc_comment, :csc_doctotal, :csc_baseamnt, :csc_taxtotal, :csc_discprofit, :csc_discount, :csc_createat, :csc_baseentry, :csc_basetype, :csc_doctype, :csc_idadd, :csc_adress, :csc_paytype, :csc_attch, :csc_docentry, :csc_series,:csc_createby)";


          // Se Inicia la transaccion,
          // Todas las consultas de modificacion siguientes
          // aplicaran solo despues que se confirme la transaccion,
          // de lo contrario no se aplicaran los cambios y se devolvera
          // la base de datos a su estado original.

          $this->pedeo->trans_begin();

          $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            ':csc_docnum' => $DocNumVerificado,
            ':csc_docdate' => $this->validateDate($Data['csc_docdate'])?$Data['csc_docdate']:NULL,
            ':csc_duedate' => $this->validateDate($Data['csc_duedate'])?$Data['csc_duedate']:NULL,
            ':csc_duedev' =>  $this->validateDate($Data['csc_duedev'])?$Data['csc_duedev']:NULL,
            ':csc_pricelist' => is_numeric($Data['csc_pricelist'])?$Data['csc_pricelist']:0,
            ':csc_cardcode' => isset($Data['csc_cardcode'])?$Data['csc_cardcode']:NULL,
            ':csc_cardname' =>  isset($Data['csc_cardname'])?$Data['csc_cardname']:NULL,
            ':csc_currency' => is_numeric($Data['csc_currency'])?$Data['csc_currency']:0,,
            ':csc_contacid' => isset($Data['csc_contacid'])?$Data['csc_contacid']:NULL,
            ':csc_slpcode' => is_numeric($Data['csc_slpcode'])?$Data['csc_slpcode']:0,
            ':csc_empid' => is_numeric($Data['csc_empid'])?$Data['csc_empid']:0,
            ':csc_comment' =>  isset($Data['csc_comment'])?$Data['csc_comment']:NULL,
            ':csc_doctotal' => is_numeric($Data['csc_doctotal'])?$Data['csc_doctotal']:0,
            ':csc_baseamnt' => is_numeric($Data['csc_baseamnt'])?$Data['csc_baseamnt']:0,
            ':csc_taxtotal' => is_numeric($Data['csc_taxtotal'])?$Data['csc_taxtotal']:0,
            ':csc_discprofit' => is_numeric($Data['csc_discprofit'])?$Data['csc_discprofit']:0,
            ':csc_discount' => is_numeric($Data['csc_discount'])?$Data['csc_discount']:0,
            ':csc_createat' => $this->validateDate($Data['csc_createat'])?$Data['csc_createat']:NULL,
            ':csc_baseentry' => is_numeric($Data['csc_baseentry'])?$Data['csc_baseentry']:0,
            ':csc_basetype' =>  is_numeric($Data['csc_basetype'])?$Data['csc_basetype']:0,
            ':csc_doctype' => is_numeric($Data['csc_doctype'])?$Data['csc_doctype']:0,
            ':csc_idadd' => isset($Data['csc_idadd'])?$Data['csc_idadd']:NULL,
            ':csc_adress' => isset($Data['csc_adress'])?$Data['csc_adress']:NULL,
            ':csc_paytype' => is_numeric($Data['csc_paytype'])?$Data['csc_paytype']:0,
            ':csc_attch' => $this->getUrl(count(trim(($Data['dpo_attch']))) > 0 ? $Data['dpo_attch']:NULL, $resMainFolder[0]['main_folder']),
            ':csc_series' => is_numeric($Data['csc_series'])?$Data['csc_series']:0,
            ':csc_createby' => isset($Data['csc_createby'])?$Data['csc_createby']:NULL

         ));

         if(is_numeric($resInsert) && $resInsert > 0){

           // INICIO DETALLE

           foreach ($ContenidoDetalle as $key => $detail) {

                 $sqlInsertDetail = "INSERT INTO csc1(sc1_docentry, sc1_itemcode, sc1_itemname, sc1_quantity, sc1_uom, sc1_whscode, sc1_price, sc1_vat, sc1_vatsum, sc1_discount, sc1_linetotal, sc1_costcode, sc1_ubusiness, sc1_project, sc1_acctcode, sc1_basetype, sc1_doctype, sc1_avprice, sc1_inventory, sc1_linenum)
                              	     VALUES (:sc1_docentry, :sc1_itemcode, :sc1_itemname, :sc1_quantity, :sc1_uom, :sc1_whscode, :sc1_price, :sc1_vat, :sc1_vatsum, :sc1_discount, :sc1_linetotal, :sc1_costcode, :sc1_ubusiness, :sc1_project, :sc1_acctcode, :sc1_basetype, :sc1_doctype, :sc1_avprice, :sc1_inventory, :sc1_linenum)";

                 $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(


                         ':sc1_docentry' => $resInsert,
                         ':sc1_itemcode' => isset($detail['ov1_itemcode'])?$detail['ov1_itemcode']:NULL,
                         ':sc1_itemname' => isset($detail['ov1_itemname'])?$detail['ov1_itemname']:NULL,
                         ':sc1_quantity' => is_numeric($detail['ov1_quantity'])?$detail['ov1_quantity']:0,
                         ':sc1_uom' => isset($detail['ov1_uom'])?$detail['ov1_uom']:NULL,
                         ':sc1_whscode' => isset($detail['ov1_whscode'])?$detail['ov1_whscode']:NULL,
                         ':sc1_price' => is_numeric($detail['ov1_price'])?$detail['ov1_price']:0,
                         ':sc1_vat' => is_numeric($detail['ov1_vat'])?$detail['ov1_vat']:0,
                         ':sc1_vatsum' => is_numeric($detail['ov1_vatsum'])?$detail['ov1_vatsum']:0,
                         ':sc1_discount' => is_numeric($detail['ov1_discount'])?$detail['ov1_discount']:0,
                         ':sc1_linetotal' => is_numeric($detail['ov1_linetotal'])?$detail['ov1_linetotal']:0,
                         ':sc1_costcode' => isset($detail['ov1_costcode'])?$detail['ov1_costcode']:NULL,
                         ':sc1_ubusiness' => isset($detail['ov1_ubusiness'])?$detail['ov1_ubusiness']:NULL,
                         ':sc1_project' => isset($detail['ov1_project'])?$detail['ov1_project']:NULL,
                         ':sc1_acctcode' => is_numeric($detail['ov1_acctcode'])?$detail['ov1_acctcode']:0,
                         ':sc1_basetype' => is_numeric($detail['ov1_basetype'])?$detail['ov1_basetype']:0,
                         ':sc1_doctype' => is_numeric($detail['ov1_doctype'])?$detail['ov1_doctype']:0,
                         ':sc1_avprice' => is_numeric($detail['ov1_avprice'])?$detail['ov1_avprice']:0,
                         ':sc1_inventory' => is_numeric($detail['ov1_inventory'])?$detail['ov1_inventory']:NULL,
                         ':sc1_linenum' => is_numeric($detail['ov1_inventory'])?$detail['ov1_inventory']:NULL
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
                         'mensaje'	=> 'No se pudo registrar la solicitud de compra'
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
              'mensaje' =>'Solicitud de compra registrada con exito'
            );

         }else{
 					// Se devuelven los cambios realizados en la transaccion
 					// si occurre un error  y se muestra devuelve el error.
 							$this->pedeo->trans_rollback();

               $respuesta = array(
                 'error'   => true,
                 'data' => $resInsert,
                 'mensaje'	=> 'No se pudo registrar la solicitud de compra'
               );

         }

          $this->response($respuesta);


      }


      //ACTUALIZAR SOLICTUD DE COMPRA
      public function updatePurchaseRequest_post(){

          $Data = $this->post();

    			if(!isset($Data['detail']) OR !isset($Data['csc_docentry'])){

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

          $sqlUpdate = "UPDATE dcsc	SET csc_docdate=:csc_docdate, csc_duedate=:csc_duedate, csc_duedev=:csc_duedev,
          csc_pricelist=:csc_pricelist,csc_cardcode=:csc_cardcode, csc_cardname=:csc_cardname, csc_contacid=:csc_contacid,
          csc_slpcode=:csc_slpcode, csc_empid=:csc_empid, csc_comment=:csc_comment, csc_doctotal=:csc_doctotal,
          csc_baseamnt=:csc_baseamnt, csc_taxtotal=:csc_taxtotal, csc_discprofit=:csc_discprofit, csc_discount=:csc_discount,
          csc_createat=:csc_createat, csc_baseentry=:csc_baseentry, csc_basetype=:csc_basetype, csc_doctype=:csc_doctype,
          csc_idadd=:csc_idadd, csc_adress=:csc_adress, csc_paytype=:csc_paytype, csc_attch=:csc_attch,
          csc_series=:csc_series, csc_createby=:csc_createby, csc_currency=:csc_currency WHERE csc_docentry = :csc_docentry";

          $this->pedeo->trans_begin();

          $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
    							':csc_docdate' => $this->validateDate($Data['csc_docdate'])?$Data['csc_docdate']:NULL,
    							':csc_duedate' => $this->validateDate($Data['csc_duedate'])?$Data['csc_duedate']:NULL,
    							':csc_duedev' => $this->validateDate($Data['csc_duedev'])?$Data['csc_duedev']:NULL,
    							':csc_pricelist' => is_numeric($Data['csc_pricelist'])?$Data['csc_pricelist']:0,
    							':csc_cardcode' => isset($Data['csc_cardcode'])?$Data['csc_cardcode']:NULL,
    							':csc_cardname' => isset($Data['csc_cardname'])?$Data['csc_cardname']:NULL,
    							':csc_currency' => is_numeric($Data['csc_currency'])?$Data['csc_currency']:0,
    							':csc_contacid' => isset($Data['csc_contacid'])?$Data['csc_contacid']:NULL,
    							':csc_slpcode' => is_numeric($Data['csc_slpcode'])?$Data['csc_slpcode']:0,
    							':csc_empid' => is_numeric($Data['csc_empid'])?$Data['csc_empid']:0,
    							':csc_comment' => isset($Data['csc_comment'])?$Data['csc_comment']:NULL,
    							':csc_doctotal' => is_numeric($Data['csc_doctotal'])?$Data['csc_doctotal']:0,
    							':csc_baseamnt' => is_numeric($Data['csc_baseamnt'])?$Data['csc_baseamnt']:0,
    							':csc_taxtotal' => is_numeric($Data['csc_taxtotal'])?$Data['csc_taxtotal']:0,
    							':csc_discprofit' => is_numeric($Data['csc_discprofit'])?$Data['csc_discprofit']:0,
    							':csc_discount' => is_numeric($Data['csc_discount'])?$Data['csc_discount']:0,
    							':csc_createat' => $this->validateDate($Data['csc_createat'])?$Data['csc_createat']:NULL,
    							':csc_baseentry' => is_numeric($Data['csc_baseentry'])?$Data['csc_baseentry']:0,
    							':csc_basetype' => is_numeric($Data['csc_basetype'])?$Data['csc_basetype']:0,
    							':csc_doctype' => is_numeric($Data['csc_doctype'])?$Data['csc_doctype']:0,
    							':csc_idadd' => isset($Data['csc_idadd'])?$Data['csc_idadd']:NULL,
    							':csc_adress' => isset($Data['csc_adress'])?$Data['csc_adress']:NULL,
    							':csc_paytype' => is_numeric($Data['csc_paytype'])?$Data['csc_paytype']:0,
    							':csc_attch' => $this->getUrl(count(trim(($Data['csc_attch']))) > 0 ? $Data['csc_attch']:NULL, $resMainFolder[0]['main_folder']),
    							':csc_docentry' => $Data['csc_docentry'],
                  ':csc_series' => $Data['csc_series'],
                  ':csc_createby' => $Data['csc_createby']
          ));

          if(is_numeric($resUpdate) && $resUpdate == 1){

    						$this->pedeo->queryTable("DELETE FROM csc1 WHERE sc1_docentry=:sc1_docentry", array(':sc1_docentry' => $Data['sc1_docentry']));

    						foreach ($ContenidoDetalle as $key => $detail) {

    									$sqlInsertDetail = "INSERT INTO csc1(sc1_docentry, sc1_itemcode, sc1_itemname, sc1_quantity, sc1_uom, sc1_whscode, sc1_price, sc1_vat, sc1_vatsum, sc1_discount, sc1_linetotal, sc1_costcode, sc1_ubusiness, sc1_project, sc1_acctcode, sc1_basetype, sc1_doctype, sc1_avprice, sc1_inventory,sc1_linenum)
	                                        VALUES (:sc1_docentry, :sc1_itemcode, :sc1_itemname, :sc1_quantity, :sc1_uom, :sc1_whscode, :sc1_price, :sc1_vat, :sc1_vatsum, :sc1_discount, :sc1_linetotal, :sc1_costcode, :sc1_ubusiness, :sc1_project, :sc1_acctcode, :sc1_basetype, :sc1_doctype, :sc1_avprice, :sc1_inventory, :sc1_linenum)";

    									$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
    											':sc1_docentry' => $resInsert,
    											':sc1_itemcode' => isset($detail['sc1_itemcode'])?$detail['sc1_itemcode']:NULL,
    											':sc1_itemname' => isset($detail['sc1_itemname'])?$detail['sc1_itemname']:NULL,
    											':sc1_quantity' => is_numeric($detail['sc1_quantity'])?$detail['sc1_quantity']:0,
    											':sc1_uom' => isset($detail['sc1_uom'])?$detail['sc1_uom']:NULL,
    											':sc1_whscode' => isset($detail['sc1_whscode'])?$detail['sc1_whscode']:NULL,
    											':sc1_price' => is_numeric($detail['sc1_price'])?$detail['sc1_price']:0,
    											':sc1_vat' => is_numeric($detail['sc1_vat'])?$detail['sc1_vat']:0,
    											':sc1_vatsum' => is_numeric($detail['sc1_vatsum'])?$detail['sc1_vatsum']:0,
    											':sc1_discount' => is_numeric($detail['sc1_discount'])?$detail['sc1_discount']:0,
    											':sc1_linetotal' => is_numeric($detail['sc1_linetotal'])?$detail['sc1_linetotal']:0,
    											':sc1_costcode' => isset($detail['sc1_costcode'])?$detail['sc1_costcode']:NULL,
    											':sc1_ubusiness' => isset($detail['sc1_ubusiness'])?$detail['sc1_ubusiness']:NULL,
    											':sc1_project' => isset($detail['sc1_project'])?$detail['sc1_project']:NULL,
    											':sc1_acctcode' => is_numeric($detail['sc1_acctcode'])?$detail['sc1_acctcode']:0,
    											':sc1_basetype' => is_numeric($detail['sc1_basetype'])?$detail['sc1_basetype']:0,
    											':sc1_doctype' => is_numeric($detail['sc1_doctype'])?$detail['sc1_doctype']:0,
    											':sc1_avprice' => is_numeric($detail['sc1_avprice'])?$detail['sc1_avprice']:0,
    											':sc1_inventory' => is_numeric($detail['sc1_inventory'])?$detail['sc1_inventory']:NULL,
                          ':sc1_linenum' => is_numeric($detail['sc1_linenum'])?$detail['sc1_linenum']:NULL
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
    													'mensaje'	=> 'No se pudo registrar la solicitud de compra'
    												);

    												 $this->response($respuesta);

    												 return;
    									}
    						}


    						$this->pedeo->trans_commit();

                $respuesta = array(
                  'error' => false,
                  'data' => $resUpdate,
                  'mensaje' =>'Solictud de compra actualizada con exito'
                );


          }else{

    						$this->pedeo->trans_rollback();

                $respuesta = array(
                  'error'   => true,
                  'data'    => $resUpdate,
                  'mensaje'	=> 'No se pudo actualizar la solicitud de compra'
                );

          }

           $this->response($respuesta);
      }


      //OBTENER SOLICTUD DE COMPRAS
      public function getPurchaseRequest_get(){

            $sqlSelect = " SELECT * FROM dcsc ";

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


      //OBTENER ORDEN DE COMPRA POR ID
    	public function getPurchaseRequestById_get(){

    				$Data = $this->get();

    				if(!isset($Data['csc_docentry'])){

    					$respuesta = array(
    						'error' => true,
    						'data'  => array(),
    						'mensaje' =>'La informacion enviada no es valida'
    					);

    					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

    					return;
    				}

    				$sqlSelect = " SELECT * FROM dcsc  WHERE csc_docentry =:csc_docentry";

    				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":csc_docentry" => $Data['csc_docentry']));

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



      //OBTENER SOLICTUD DE COMPRA DETALLE POR ID
      public function getPurchaseRequestDetail_get(){

            $Data = $this->get();

            if(!isset($Data['sc1_docentry'])){

              $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' =>'La informacion enviada no es valida'
              );

              $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

              return;
            }

            $sqlSelect = " SELECT * FROM csc1 WHERE sc1_docentry =:sc1_docentry";

            $resSelect = $this->pedeo->queryTable($sqlSelect, array(":sc1_docentry" => $Data['sc1_docentry']));

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
