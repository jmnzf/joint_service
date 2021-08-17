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

<<<<<<< HEAD
      if(!isset($Data['detail'])){
=======
      if(!isset($Data['dvc_docdate']) OR !isset($Data['dvc_duedate']) OR
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
         !isset($Data['detail'])
       ){
>>>>>>> babdd59be34f980a041d6b1c4449ced718c7144f

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

<<<<<<< HEAD
        $sqlInsert = "INSERT INTO dvct(dvc_docnum, dvc_docdate, dvc_duedate, dvc_duedev, dvc_pricelist, dvc_cardcode,
=======
        $sqlInsert = "INSERT INTO dvct (dvc_docnum, dvc_docdate, dvc_duedate, dvc_duedev, dvc_pricelist, dvc_cardcode,
>>>>>>> babdd59be34f980a041d6b1c4449ced718c7144f
                      dvc_cardname, dvc_currency, dvc_contacid, dvc_slpcode, dvc_empid, dvc_comment, dvc_doctotal, dvc_baseamnt, dvc_taxtotal,
                      dvc_discprofit, dvc_discount, dvc_createat, dvc_baseentry, dvc_basetype, dvc_doctype, dvc_idadd, dvc_adress, dvc_paytype,
                      dvc_attch)VALUES(:dvc_docnum, :dvc_docdate, :dvc_duedate, :dvc_duedev, :dvc_pricelist, :dvc_cardcode, :dvc_cardname,
                      :dvc_currency, :dvc_contacid, :dvc_slpcode, :dvc_empid, :dvc_comment, :dvc_doctotal, :dvc_baseamnt, :dvc_taxtotal, :dvc_discprofit, :dvc_discount,
                      :dvc_createat, :dvc_baseentry, :dvc_basetype, :dvc_doctype, :dvc_idadd, :dvc_adress, :dvc_paytype, :dvc_attch)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
<<<<<<< HEAD
              
              ':dvc_docnum' => isset($Data['dvc_docnum'])?$Data['dvc_docnum']:NULL,
              ':dvc_docdate' => isset($Data['dvc_docdate'])?$Data['dvc_docdate']:NULL,
              ':dvc_duedate' => isset($Data['dvc_duedate'])?$Data['dvc_duedate']:NULL,
              ':dvc_duedev' => isset($Data['dvc_duedev'])?$Data['dvc_duedev']:NULL,
              ':dvc_pricelist' => isset($Data['dvc_pricelist'])?$Data['dvc_pricelist']:NULL,
              ':dvc_cardcode' => isset($Data['dvc_pricelist'])?$Data['dvc_pricelist']:NULL,
              ':dvc_cardname' => isset($Data['dvc_cardname'])?$Data['dvc_cardname']:NULL,
              ':dvc_currency' => isset($Data['dvc_currency'])?$Data['dvc_currency']:NULL,
              ':dvc_contacid' => isset($Data['dvc_contacid'])?$Data['dvc_contacid']:NULL,
              ':dvc_slpcode' => isset($Data['dvc_slpcode'])?$Data['dvc_slpcode']:NULL,
              ':dvc_empid' => isset($Data['dvc_empid'])?$Data['dvc_empid']:NULL,
              ':dvc_comment' => isset($Data['dvc_comment'])?$Data['dvc_comment']:NULL,
              ':dvc_doctotal' => isset($Data['dvc_doctotal'])?$Data['dvc_doctotal']:NULL,
              ':dvc_baseamnt' => isset($Data['dvc_baseamnt'])?$Data['dvc_baseamnt']:NULL,
              ':dvc_taxtotal' => isset($Data['dvc_taxtotal'])?$Data['dvc_taxtotal']:NULL,
              ':dvc_discprofit' => isset($Data['dvc_discprofit'])?$Data['dvc_discprofit']:NULL,
              ':dvc_discount' => isset($Data['dvc_discount'])?$Data['dvc_discount']:NULL,
              ':dvc_createat' => isset($Data['dvc_createat'])?$Data['dvc_createat']:NULL,
              ':dvc_baseentry' => isset($Data['dvc_baseentry'])?$Data['dvc_baseentry']:NULL,
              ':dvc_basetype' => isset($Data['dvc_basetype'])?$Data['dvc_basetype']:NULL,
              ':dvc_doctype' => isset($Data['dvc_doctype'])?$Data['dvc_doctype']:NULL,
              ':dvc_idadd' => isset($Data['dvc_idadd'])?$Data['dvc_idadd']:NULL,
              ':dvc_adress' => isset($Data['dvc_adress'])?$Data['dvc_adress']:NULL,
              ':dvc_paytype' => isset($Data['dvc_paytype'])?$Data['dvc_paytype']:NULL,
              ':dvc_attch' => $this->getUrl(isset($Data['dvc_attch'])?$Data['dvc_attch']:NULL)
=======
              ':dvc_docnum' => isset($Data['dvc_docnum']) ? $Data['dvc_docnum'] : 12,
              ':dvc_docdate' => $Data['dvc_docdate'],
              ':dvc_duedate' => $Data['dvc_duedate'],
              ':dvc_duedev' => $Data['dvc_duedev'],
              ':dvc_pricelist' => $Data['dvc_pricelist'],
              ':dvc_cardcode' => $Data['dvc_cardcode'],
              ':dvc_cardname' => $Data['dvc_cardname'],
              ':dvc_currency' => $Data['dvc_currency'],
              ':dvc_contacid' => $Data['dvc_contacid'],
              ':dvc_slpcode' => $Data['dvc_slpcode'],
              ':dvc_empid' => $Data['dvc_empid'],
              ':dvc_comment' => $Data['dvc_comment'],
              ':dvc_doctotal' => $Data['dvc_doctotal'],
              ':dvc_baseamnt' => $Data['dvc_baseamnt'],
              ':dvc_taxtotal' => $Data['dvc_taxtotal'],
              ':dvc_discprofit' => $Data['dvc_discprofit'],
              ':dvc_discount' => $Data['dvc_discount'],
              ':dvc_createat' => $Data['dvc_createat'],
              ':dvc_baseentry' => $Data['dvc_baseentry'],
              ':dvc_basetype' => $Data['dvc_basetype'],
              ':dvc_doctype' => $Data['dvc_doctype'],
              ':dvc_idadd' => $Data['dvc_idadd'],
              ':dvc_adress' => $Data['dvc_adress'],
              ':dvc_paytype' => $Data['dvc_paytype'],
              ':dvc_attch' => $this->getUrl($Data['dvc_attch'])
>>>>>>> babdd59be34f980a041d6b1c4449ced718c7144f

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
                        ':vc1_quantity' => isset($detail['vc1_quantity'])?$detail['vc1_quantity']:NULL,
                        ':vc1_uom' => isset($detail['vc1_uom'])?$detail['vc1_uom']:NULL,
                        ':vc1_whscode' => isset($detail['vc1_whscode'])?$detail['vc1_whscode']:NULL,
                        ':vc1_price' => isset($detail['vc1_price'])?$detail['vc1_price']:NULL,
                        ':vc1_vat' => isset($detail['vc1_vat'])?$detail['vc1_vat']:NULL,
                        ':vc1_vatsum' => isset($detail['vc1_vatsum'])?$detail['vc1_vatsum']:NULL,
                        ':vc1_discount' => isset($detail['vc1_discount'])?$detail['vc1_discount']:NULL,
                        ':vc1_linetotal' => isset($detail['vc1_linetotal'])?$detail['vc1_linetotal']:NULL,
                        ':vc1_costcode' => isset($detail['vc1_costcode'])?$detail['vc1_costcode']:NULL,
                        ':vc1_ubusiness' => isset($detail['vc1_ubusiness'])?$detail['vc1_ubusiness']:NULL,
                        ':vc1_project' => isset($detail['vc1_project'])?$detail['vc1_project']:NULL,
                        ':vc1_acctcode' => isset($detail['vc1_acctcode'])?$detail['vc1_acctcode']:NULL,
                        ':vc1_basetype' => isset($detail['vc1_basetype'])?$detail['vc1_basetype']:NULL,
                        ':vc1_doctype' => isset($detail['vc1_doctype'])?$detail['vc1_doctype']:NULL,
                        ':vc1_avprice' => isset($detail['vc1_avprice'])?$detail['vc1_avprice']:NULL,
                        ':vc1_inventory' => isset($detail['vc1_inventory'])?$detail['vc1_inventory']:NULL
                ));
          }

          $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' =>'Cotización registrada con exito'
          );


        }else{

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


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
							':dvc_docnum' => $Data['dvc_docnum'],
							':dvc_docdate' => $Data['dvc_docdate'],
							':dvc_duedate' => $Data['dvc_duedate'],
							':dvc_duedev' => $Data['dvc_duedev'],
							':dvc_pricelist' => $Data['dvc_pricelist'],
							':dvc_cardcode' => $Data['dvc_cardcode'],
							':dvc_cardname' => $Data['dvc_cardname'],
							':dvc_currency' => $Data['dvc_currency'],
							':dvc_contacid' => $Data['dvc_contacid'],
							':dvc_slpcode' => $Data['dvc_slpcode'],
							':dvc_empid' => $Data['dvc_empid'],
							':dvc_comment' => $Data['dvc_comment'],
							':dvc_doctotal' => $Data['dvc_doctotal'],
							':dvc_baseamnt' => $Data['dvc_baseamnt'],
							':dvc_taxtotal' => $Data['dvc_taxtotal'],
							':dvc_discprofit' => $Data['dvc_discprofit'],
							':dvc_discount' => $Data['dvc_discount'],
							':dvc_createat' => $Data['dvc_createat'],
							':dvc_baseentry' => $Data['dvc_baseentry'],
							':dvc_basetype' => $Data['dvc_basetype'],
							':dvc_doctype' => $Data['dvc_doctype'],
							':dvc_idadd' => $Data['dvc_idadd'],
							':dvc_adress' => $Data['dvc_adress'],
							':dvc_paytype' => $Data['dvc_paytype'],
							':dvc_attch' => $this->getUrl($Data['dvc_attch']),
							':dvc_docentry' => $Data['dvc_docentry'],
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
													':vc1_docentry' => $Data['dvc_docentry'],
												  ':vc1_itemcode' => isset($detail['vc1_itemcode'])?$detail['vc1_itemcode']:NULL,
                          ':vc1_itemname' => isset($detail['vc1_itemname'])?$detail['vc1_itemname']:NULL,
                          ':vc1_quantity' => isset($detail['vc1_quantity'])?$detail['vc1_quantity']:NULL,
                          ':vc1_uom' => isset($detail['vc1_uom'])?$detail['vc1_uom']:NULL,
                          ':vc1_whscode' => isset($detail['vc1_whscode'])?$detail['vc1_whscode']:NULL,
                          ':vc1_price' => isset($detail['vc1_price'])?$detail['vc1_price']:NULL,
                          ':vc1_vat' => isset($detail['vc1_vat'])?$detail['vc1_vat']:NULL,
                          ':vc1_vatsum' => isset($detail['vc1_vatsum'])?$detail['vc1_vatsum']:NULL,
                          ':vc1_discount' => isset($detail['vc1_discount'])?$detail['vc1_discount']:NULL,
                          ':vc1_linetotal' => isset($detail['vc1_linetotal'])?$detail['vc1_linetotal']:NULL,
                          ':vc1_costcode' => isset($detail['vc1_costcode'])?$detail['vc1_costcode']:NULL,
                          ':vc1_ubusiness' => isset($detail['vc1_ubusiness'])?$detail['vc1_ubusiness']:NULL,
                          ':vc1_project' => isset($detail['vc1_project'])?$detail['vc1_project']:NULL,
                          ':vc1_acctcode' => isset($detail['vc1_acctcode'])?$detail['vc1_acctcode']:NULL,
                          ':vc1_basetype' => isset($detail['vc1_basetype'])?$detail['vc1_basetype']:NULL,
                          ':vc1_doctype' => isset($detail['vc1_doctype'])?$detail['vc1_doctype']:NULL,
                          ':vc1_avprice' => isset($detail['vc1_avprice'])?$detail['vc1_avprice']:NULL,
                          ':vc1_inventory' => isset($detail['vc1_inventory'])?$detail['vc1_inventory']:NULL
									));
						}

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Cotización actualizada con exito'
            );


      }else{

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




}
