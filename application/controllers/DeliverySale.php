<?php
// ENTREGAS DE VENTAS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class DeliverySale extends REST_Controller {

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

  //CREAR NUEVA ENTREGA DE VENTA
	public function createDeliverySale_post(){

      $Data = $this->post();

      if(!isset($Data['dve_doc_num']) OR !isset($Data['dve_doc_date']) OR
         !isset($Data['dve_doc_duedate']) OR !isset($Data['dve_doc_duedev']) OR
         !isset($Data['dve_price_list']) OR !isset($Data['dve_card_code']) OR
         !isset($Data['dve_card_name']) OR !isset($Data['dve_currency']) OR
         !isset($Data['dve_contacid']) OR !isset($Data['dve_slp_code']) OR
         !isset($Data['dve_empid']) OR !isset($Data['dve_comment']) OR
         !isset($Data['dve_doc_total']) OR !isset($Data['dve_base_amnt']) OR
         !isset($Data['dve_tax_total']) OR !isset($Data['dve_disc_profit']) OR
         !isset($Data['dve_discount']) OR !isset($Data['dve_createat']) OR
         !isset($Data['dve_base_entry']) OR !isset($Data['dve_base_type']) OR
         !isset($Data['dve_id_add']) OR !isset($Data['dve_adress']) OR
         !isset($Data['dve_pay_type']) OR !isset($Data['dve_attch']) OR
         !isset($Data['detail']) OR !isset($Data['dve_doc_type'])){

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

        $sqlInsert = "INSERT INTO dvev(dve_doc_num, dve_doc_date, dve_doc_duedate, dve_doc_duedev, dve_price_list, dve_card_code, dve_card_name,
                      dve_currency, dve_contacid, dve_slp_code, dve_empid, dve_comment, dve_doc_total, dve_base_amnt, dve_tax_total, dve_disc_profit,
                      dve_discount, dve_createat, dve_base_entry, dve_base_type, dve_doc_type, dve_id_add, dve_adress, dve_pay_type, dve_attch)
	                    VALUES (:dve_doc_num,:dve_doc_date,:dve_doc_duedate,:dve_doc_duedev,:dve_price_list,:dve_card_code,:dve_card_name,:dve_currency,
                      :dve_contacid,:dve_slp_code,:dve_empid,:dve_comment,:dve_doc_total,:dve_base_amnt,:dve_tax_total,:dve_disc_profit,:dve_discount,
                      :dve_createat,:dve_base_entry,:dve_base_type,:dve_doc_type,:dve_id_add,:dve_adress,:dve_pay_type,:dve_attch)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':dve_doc_num' => $Data['dve_doc_num'],
              ':dve_doc_date' => $Data['dve_doc_date'],
              ':dve_doc_duedate' => $Data['dve_doc_duedate'],
              ':dve_doc_duedev' => $Data['dve_doc_duedev'],
              ':dve_price_list' => $Data['dve_price_list'],
              ':dve_card_code' => $Data['dve_card_code'],
              ':dve_card_name' => $Data['dve_card_name'],
              ':dve_currency' => $Data['dve_currency'],
              ':dve_contacid' => $Data['dve_contacid'],
              ':dve_slp_code' => $Data['dve_slp_code'],
              ':dve_empid' => $Data['dve_empid'],
              ':dve_comment' => $Data['dve_comment'],
              ':dve_doc_total' => $Data['dve_doc_total'],
              ':dve_base_amnt' => $Data['dve_base_amnt'],
              ':dve_tax_total' => $Data['dve_tax_total'],
              ':dve_disc_profit' => $Data['dve_disc_profit'],
              ':dve_discount' => $Data['dve_discount'],
              ':dve_createat' => $Data['dve_createat'],
              ':dve_base_entry' => $Data['dve_base_entry'],
              ':dve_base_type' => $Data['dve_base_type'],
              ':dve_doc_type' => $Data['dve_doc_type'],
              ':dve_id_add' => $Data['dve_id_add'],
              ':dve_adress' => $Data['dve_adress'],
              ':dve_pay_type' => $Data['dve_pay_type'],
              ':dve_attch' => $this->getUrl($Data['dve_attch'])

        ));

        if(is_numeric($resInsert) && $resInsert > 0){


          foreach ($ContenidoDetalle as $key => $detail) {



                $sqlInsertDetail = "INSERT INTO vev1(ve1_doc_entry, ve1_item_code, ve1_quantity, ve1_uom, ve1_whscode, ve1_price, ve1_vat, ve1_vat_sum,
                                    ve1_discount, ve1_line_total, ve1_cost_code, ve1_ubusiness, ve1_project, ve1_acct_code, ve1_base_type, ve1_doc_type,
                                    ve1_avprice, ve1_inventory, ve1_item_name)VALUES(:ve1_doc_entry,:ve1_item_code,:ve1_quantity,:ve1_uom,:ve1_whscode,:ve1_price,:ve1_vat,
                                    :ve1_vat_sum,:ve1_discount,:ve1_line_total,:ve1_cost_code,:ve1_ubusiness,:ve1_project,:ve1_acct_code,:ve1_base_type,
                                    :ve1_doc_type,:ve1_avprice,:ve1_inventory,ve1_item_name)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':ve1_doc_entry' => $resInsert,
                        ':ve1_item_code' => $Data['ve1_item_code'],
                        ':ve1_quantity' => $Data['ve1_quantity'],
                        ':ve1_uom' => $Data['ve1_uom'],
                        ':ve1_whscode' => $Data['ve1_whscode'],
                        ':ve1_price' => $Data['ve1_price'],
                        ':ve1_vat' => $Data['ve1_vat'],
                        ':ve1_vat_sum' => $Data['ve1_vat_sum'],
                        ':ve1_discount' => $Data['ve1_discount'],
                        ':ve1_line_total' => $Data['ve1_line_total'],
                        ':ve1_cost_code' => $Data['ve1_cost_code'],
                        ':ve1_ubusiness' => $Data['ve1_ubusiness'],
                        ':ve1_project' => $Data['ve1_project'],
                        ':ve1_acct_code' => $Data['ve1_acct_code'],
                        ':ve1_base_type' => $Data['ve1_base_type'],
                        ':ve1_doc_type' => $Data['ve1_doc_type'],
                        ':ve1_avprice' => $Data['ve1_avprice'],
                        ':ve1_inventory' => $Data['ve1_inventory'],
                        ':ve1_item_name' => $Data['ve1_item_name']
                ));
          }

          $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' =>'Entrega registrada con exito'
          );


        }else{

              $respuesta = array(
                'error'   => true,
                'data' 		=> $resInsert,
                'mensaje'	=> 'No se pudo registrar la entrega'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR ENTREGA DE VENTA
  public function updateDeliverySale_post(){

      $Data = $this->post();

      if(!isset($Data['dve_doc_num']) OR !isset($Data['dve_doc_date']) OR
         !isset($Data['dve_doc_duedate']) OR !isset($Data['dve_doc_duedev']) OR
         !isset($Data['dve_price_list']) OR !isset($Data['dve_card_code']) OR
         !isset($Data['dve_card_name']) OR !isset($Data['dve_currency']) OR
         !isset($Data['dve_contacid']) OR !isset($Data['dve_slp_code']) OR
         !isset($Data['dve_empid']) OR !isset($Data['dve_comment']) OR
         !isset($Data['dve_doc_total']) OR !isset($Data['dve_base_amnt']) OR
         !isset($Data['dve_tax_total']) OR !isset($Data['dve_disc_profit']) OR
         !isset($Data['dve_discount']) OR !isset($Data['dve_createat']) OR
         !isset($Data['dve_base_entry']) OR !isset($Data['dve_base_type']) OR
         !isset($Data['dve_id_add']) OR !isset($Data['dve_adress']) OR
         !isset($Data['dve_pay_type']) OR !isset($Data['dve_attch']) OR
         !isset($Data['detail']) OR !isset($Data['dve_docentry'])){

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
            'mensaje' =>'No se encontro el detalle de la entrega'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }

      $sqlUpdate = "UPDATE dvev SET  dve_doc_num = :dve_doc_num, dve_doc_date =:dve_doc_date, dve_doc_duedate =:dve_doc_duedate, dve_doc_duedev =:dve_doc_duedev,
                    dve_price_list =:dve_price_list, dve_card_code =:dve_card_code, dve_card_name =:dve_card_name, dve_currency =:dve_currency,
                    dve_contacid =:dve_contacid, dve_slp_code =:dve_slp_code, dve_empid =:dve_empid, dve_comment =:dve_comment, dve_doc_total =:dve_doc_total,
                    dve_base_amnt =:dve_base_amnt, dve_tax_total =:dve_tax_total, dve_disc_profit =:dve_disc_profit, dve_discount =:dve_discount, dve_createat =:dve_createat,
                    dve_base_entry =:dve_base_entry, dve_base_type =:dve_base_type, dve_doc_type =:dve_doc_type, dve_id_add =:dve_id_add, dve_adress =:dve_adress,
                    dve_pay_type =:dve_pay_type, dve_attch =:dve_attch
	                  WHERE dve_doc_entry = :dve_doc_entry ";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

              ':dve_doc_num' => $Data['dve_doc_num'],
              ':dve_doc_date' => $Data['dve_doc_date'],
              ':dve_doc_duedate' => $Data['dve_doc_duedate'],
              ':dve_doc_duedev' => $Data['dve_doc_duedev'],
              ':dve_price_list' => $Data['dve_price_list'],
              ':dve_card_code' => $Data['dve_card_code'],
              ':dve_card_name' => $Data['dve_card_name'],
              ':dve_currency' => $Data['dve_currency'],
              ':dve_contacid' => $Data['dve_contacid'],
              ':dve_slp_code' => $Data['dve_slp_code'],
              ':dve_empid' => $Data['dve_empid'],
              ':dve_comment' => $Data['dve_comment'],
              ':dve_doc_total' => $Data['dve_doc_total'],
              ':dve_base_amnt' => $Data['dve_base_amnt'],
              ':dve_tax_total' => $Data['dve_tax_total'],
              ':dve_disc_profit' => $Data['dve_disc_profit'],
              ':dve_discount' => $Data['dve_discount'],
              ':dve_createat' => $Data['dve_createat'],
              ':dve_base_entry' => $Data['dve_base_entry'],
              ':dve_base_type' => $Data['dve_base_type'],
              ':dve_doc_type' => $Data['dve_doc_type'],
              ':dve_id_add' => $Data['dve_id_add'],
              ':dve_adress' => $Data['dve_adress'],
              ':dve_pay_type' => $Data['dve_pay_type'],
              ':dve_attch' => $this->getUrl($Data['dve_attch']),
							':dve_doc_entry' => $Data['dve_doc_entry'],
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

						$this->pedeo->queryTable("DELETE FROM vev1 WHERE ve1_doc_entry=:ve1_doc_entry", array(':ve1_doc_entry' => $Data['dvp_docentry']));

						foreach ($ContenidoDetalle as $key => $detail) {

                $sqlInsertDetail = "INSERT INTO vev1(ve1_doc_entry, ve1_item_code, ve1_quantity, ve1_uom, ve1_whscode, ve1_price, ve1_vat, ve1_vat_sum,
                                    ve1_discount, ve1_line_total, ve1_cost_code, ve1_ubusiness, ve1_project, ve1_acct_code, ve1_base_type, ve1_doc_type,
                                    ve1_avprice, ve1_inventory, ve1_item_name)VALUES(:ve1_doc_entry,:ve1_item_code,:ve1_quantity,:ve1_uom,:ve1_whscode,:ve1_price,:ve1_vat,
                                    :ve1_vat_sum,:ve1_discount,:ve1_line_total,:ve1_cost_code,:ve1_ubusiness,:ve1_project,:ve1_acct_code,:ve1_base_type,
                                    :ve1_doc_type,:ve1_avprice,:ve1_inventory,ve1_item_name)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':ve1_doc_entry' => $resInsert,
                        ':ve1_item_code' => $Data['ve1_item_code'],
                        ':ve1_quantity' => $Data['ve1_quantity'],
                        ':ve1_uom' => $Data['ve1_uom'],
                        ':ve1_whscode' => $Data['ve1_whscode'],
                        ':ve1_price' => $Data['ve1_price'],
                        ':ve1_vat' => $Data['ve1_vat'],
                        ':ve1_vat_sum' => $Data['ve1_vat_sum'],
                        ':ve1_discount' => $Data['ve1_discount'],
                        ':ve1_line_total' => $Data['ve1_line_total'],
                        ':ve1_cost_code' => $Data['ve1_cost_code'],
                        ':ve1_ubusiness' => $Data['ve1_ubusiness'],
                        ':ve1_project' => $Data['ve1_project'],
                        ':ve1_acct_code' => $Data['ve1_acct_code'],
                        ':ve1_base_type' => $Data['ve1_base_type'],
                        ':ve1_doc_type' => $Data['ve1_doc_type'],
                        ':ve1_avprice' => $Data['ve1_avprice'],
                        ':ve1_inventory' => $Data['ve1_inventory'],
                        ':ve1_item_name' => $Data['ve1_item_name']
                ));
  						}

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Entrega actualizada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la entrega'
            );

      }

       $this->response($respuesta);
  }


  //OBTENER ENTREGAS DE VENTAS
  public function getDeliverySale_get(){

        $sqlSelect = " SELECT * FROM dvev";

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


	//OBTENER ENTREGA DE VENTA POR ID
	public function getDeliverySaleById_get(){

				$Data = $this->get();

				if(!isset($Data['dve_doc_entry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM dvev WHERE dve_doc_entry =:dve_doc_entry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":dve_doc_entry" => $Data['dve_doc_entry']));

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


	//OBTENER DETALLE ENTREGA DE VENTA POR ID
	public function getDeliverySaleDetail_get(){

				$Data = $this->get();

				if(!isset($Data['ve1_doc_entry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM vev1 WHERE ve1_doc_entry =:ve1_doc_entry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":ve1_doc_entry" => $Data['ve1_doc_entry']));

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
