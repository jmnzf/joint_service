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
         !isset($Data['detail'])
       ){

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

        $sqlInsert = "INSERT INTO dvct(dvc_docentry, dvc_docnum, dvc_docdate, dvc_duedate, dvc_duedev, dvc_pricelist, dvc_cardcode,
                      dvc_cardname, dvc_currency, dvc_contacid, dvc_slpcode, dvc_empid, dvc_comment, dvc_doctotal, dvc_baseamnt, dvc_taxtotal,
                      dvc_discprofit, dvc_discount, dvc_createat, dvc_baseentry, dvc_basetype, dvc_doctype, dvc_idadd, dvc_adress, dvc_paytype,
                      dvc_attch)VALUES(:dvc_docentry, :dvc_docnum, :dvc_docdate, :dvc_duedate, :dvc_duedev, :dvc_pricelist, :dvc_cardcode, :dvc_cardname,
                      :dvc_currency, :dvc_contacid, :dvc_slpcode, :dvc_empid, :dvc_comment, :dvc_doctotal, :dvc_baseamnt, :dvc_taxtotal, :dvc_discprofit, :dvc_discount,
                      :dvc_createat, :dvc_baseentry, :dvc_basetype, :dvc_doctype, :dvc_idadd, :dvc_adress, :dvc_paytype, :dvc_attch)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':dvc_docentry' => $Data['dvc_docentry'],
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
              ':dvc_attch' => $this->getUrl($Data['dvc_attch'])

        ));

        if($resInsert > 0 ){


          foreach ($ContenidoDetalle as $key => $detail) {

                $sqlInsertDetail = "INSERT INTO vct1(vc1_docentry, vc1_itemcode, vc1_itemname, vc1_quantity, vc1_uom, vc1_whscode,
                                    vc1_price, vc1_vat, vc1_vatsum, vc1_discount, vc1_linetotal, vc1_costcode, vc1_ubusiness, vc1_project,
                                    vc1_acctcode, vc1_basetype, vc1_doctype, vc1_avprice, vc1_inventory)VALUES(:vc1_docentry, :vc1_itemcode, :vc1_itemname, :vc1_quantity,
                                    :vc1_uom, :vc1_whscode,:vc1_price, :vc1_vat, :vc1_vatsum, :vc1_discount, :vc1_linetotal, :vc1_costcode, :vc1_ubusiness, :vc1_project,
                                    :vc1_acctcode, :vc1_basetype, :vc1_doctype, :vc1_avprice, :vc1_inventory)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':vc1_docentry' => $resInsert,
                        ':vc1_itemcode' => $detail['vc1_itemcode'],
                        ':vc1_itemname' => $detail['vc1_itemname'],
                        ':vc1_quantity' => $detail['vc1_quantity'],
                        ':vc1_uom' => $detail['vc1_uom'],
                        ':vc1_whscode' => $detail['vc1_whscode'],
                        ':vc1_price' => $detail['vc1_price'],
                        ':vc1_vat' => $detail['vc1_vat'],
                        ':vc1_vatsum' => $detail['vc1_vatsum'],
                        ':vc1_discount' => $detail['vc1_discount'],
                        ':vc1_linetotal' => $detail['vc1_linetotal'],
                        ':vc1_costcode' => $detail['vc1_costcode'],
                        ':vc1_ubusiness' => $detail['vc1_ubusiness'],
                        ':vc1_project' => $detail['vc1_project'],
                        ':vc1_acctcode' => $detail['vc1_acctcode'],
                        ':vc1_basetype' => $detail['vc1_basetype'],
                        ':vc1_doctype' => $detail['vc1_doctype'],
                        ':vc1_avprice' => $detail['vc1_avprice'],
                        ':vc1_inventory' => $detail['vc1_inventory']
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
                'data' => array(),
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
				 !isset($Data['detail']) OR !isset($Data['dvc_id'])){

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

      $sqlUpdate = "UPDATE dvct	SET dvc_docentry=:dvc_docentry, dvc_docnum=:dvc_docnum, dvc_docdate=:dvc_docdate,
			 							dvc_duedate=:dvc_duedate, dvc_duedev=:dvc_duedev, dvc_pricelist=:dvc_pricelist, dvc_cardcode=:dvc_cardcode,
			  						dvc_cardname=:dvc_cardname, dvc_currency=:dvc_currency, dvc_contacid=:dvc_contacid, dvc_slpcode=:dvc_slpcode,
										dvc_empid=:dvc_empid, dvc_comment=:dvc_comment, dvc_doctotal=:dvc_doctotal, dvc_baseamnt=:dvc_baseamnt,
										dvc_taxtotal=:dvc_taxtotal, dvc_discprofit=:dvc_discprofit, dvc_discount=:dvc_discount, dvc_createat=:dvc_createat,
										dvc_baseentry=:dvc_baseentry, dvc_basetype=:dvc_basetype, dvc_doctype=:dvc_doctype, dvc_idadd=:dvc_idadd,
										dvc_adress=:dvc_adress, dvc_paytype=:dvc_paytype, dvc_attch=:dvc_attch WHERE dvc_id=:dvc_id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

							':dvc_docentry' => $Data['dvc_docentry'],
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
							':dvc_id' => $Data['dvc_id'],
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

						$this->pedeo->queryTable("DELETE FROM vct1 WHERE vc1_docentry=:vc1_docentry", array(':vc1_docentry' => $Data['dvc_id']));

						foreach ($ContenidoDetalle as $key => $detail) {

									$sqlInsertDetail = "INSERT INTO vct1(vc1_docentry, vc1_itemcode, vc1_itemname, vc1_quantity, vc1_uom, vc1_whscode,
																			vc1_price, vc1_vat, vc1_vatsum, vc1_discount, vc1_linetotal, vc1_costcode, vc1_ubusiness, vc1_project,
																			vc1_acctcode, vc1_basetype, vc1_doctype, vc1_avprice, vc1_inventory)VALUES(:vc1_docentry, :vc1_itemcode, :vc1_itemname, :vc1_quantity,
																			:vc1_uom, :vc1_whscode,:vc1_price, :vc1_vat, :vc1_vatsum, :vc1_discount, :vc1_linetotal, :vc1_costcode, :vc1_ubusiness, :vc1_project,
																			:vc1_acctcode, :vc1_basetype, :vc1_doctype, :vc1_avprice, :vc1_inventory)";

									$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
													':vc1_docentry' => $Data['dvc_id'],
													':vc1_itemcode' => $detail['vc1_itemcode'],
													':vc1_itemname' => $detail['vc1_itemname'],
													':vc1_quantity' => $detail['vc1_quantity'],
													':vc1_uom' => $detail['vc1_uom'],
													':vc1_whscode' => $detail['vc1_whscode'],
													':vc1_price' => $detail['vc1_price'],
													':vc1_vat' => $detail['vc1_vat'],
													':vc1_vatsum' => $detail['vc1_vatsum'],
													':vc1_discount' => $detail['vc1_discount'],
													':vc1_linetotal' => $detail['vc1_linetotal'],
													':vc1_costcode' => $detail['vc1_costcode'],
													':vc1_ubusiness' => $detail['vc1_ubusiness'],
													':vc1_project' => $detail['vc1_project'],
													':vc1_acctcode' => $detail['vc1_acctcode'],
													':vc1_basetype' => $detail['vc1_basetype'],
													':vc1_doctype' => $detail['vc1_doctype'],
													':vc1_avprice' => $detail['vc1_avprice'],
													':vc1_inventory' => $detail['vc1_inventory']
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

				if(!isset($Data['dvc_id'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM dvct WHERE dvc_id =:dvc_id";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":dvc_id" => $Data['dvc_id']));

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
      $ruta = '/var/www/html/serpent/assets/img/anexos/';
      $milliseconds = round(microtime(true) * 1000);
      $url = "";

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
