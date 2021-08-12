<?php
// PEDIDOS DE VENTAS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class OrderSale extends REST_Controller {

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

  //CREAR NUEVO PEDIDO DE VENTA
	public function createOrderSale_post(){

      $Data = $this->post();

      if(!isset($Data['dvp_doc_num']) OR !isset($Data['dvp_doc_date']) OR
         !isset($Data['dvp_doc_duedate']) OR !isset($Data['dvp_doc_duedev']) OR
         !isset($Data['dvp_price_list']) OR !isset($Data['dvp_card_code']) OR
         !isset($Data['dvp_card_name']) OR !isset($Data['dvp_currency']) OR
         !isset($Data['dvp_contacid']) OR !isset($Data['dvp_slp_code']) OR
         !isset($Data['dvp_empid']) OR !isset($Data['dvp_comment']) OR
         !isset($Data['dvp_doc_total']) OR !isset($Data['dvp_base_amnt']) OR
         !isset($Data['dvp_tax_total']) OR !isset($Data['dvp_disc_profit']) OR
         !isset($Data['dvp_discount']) OR !isset($Data['dvp_createat']) OR
         !isset($Data['dvp_base_entry']) OR !isset($Data['dvp_base_type']) OR
         !isset($Data['dvp_id_add']) OR !isset($Data['dvp_adress']) OR
         !isset($Data['dvp_pay_type']) OR !isset($Data['dvp_attch']) OR
         !isset($Data['detail']) OR !isset($Data['dvp_doc_type'])){

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

        $sqlInsert = "INSERT INTO dvpv(dvp_doc_num, dvp_doc_date, dvp_doc_duedate, dvp_doc_duedev, dvp_price_list, dvp_card_code, dvp_card_name,
                      dvp_currency, dvp_contacid, dvp_slp_code, dvp_empid, dvp_comment, dvp_doc_total, dvp_base_amnt, dvp_tax_total, dvp_disc_profit,
                      dvp_discount, dvp_createat, dvp_base_entry, dvp_base_type, dvp_doc_type, dvp_id_add, dvp_adress, dvp_pay_type, dvp_attch)
	                    VALUES (:dvp_doc_num,:dvp_doc_date,:dvp_doc_duedate,:dvp_doc_duedev,:dvp_price_list,:dvp_card_code,:dvp_card_name,:dvp_currency,
                      :dvp_contacid,:dvp_slp_code,:dvp_empid,:dvp_comment,:dvp_doc_total,:dvp_base_amnt,:dvp_tax_total,:dvp_disc_profit,:dvp_discount,
                      :dvp_createat,:dvp_base_entry,:dvp_base_type,:dvp_doc_type,:dvp_id_add,:dvp_adress,:dvp_pay_type,:dvp_attch)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':dvp_doc_num' => $Data['dvp_doc_num'],
              ':dvp_doc_date' => $Data['dvp_doc_date'],
              ':dvp_doc_duedate' => $Data['dvp_doc_duedate'],
              ':dvp_doc_duedev' => $Data['dvp_doc_duedev'],
              ':dvp_price_list' => $Data['dvp_price_list'],
              ':dvp_card_code' => $Data['dvp_card_code'],
              ':dvp_card_name' => $Data['dvp_card_name'],
              ':dvp_currency' => $Data['dvp_currency'],
              ':dvp_contacid' => $Data['dvp_contacid'],
              ':dvp_slp_code' => $Data['dvp_slp_code'],
              ':dvp_empid' => $Data['dvp_empid'],
              ':dvp_comment' => $Data['dvp_comment'],
              ':dvp_doc_total' => $Data['dvp_doc_total'],
              ':dvp_base_amnt' => $Data['dvp_base_amnt'],
              ':dvp_tax_total' => $Data['dvp_tax_total'],
              ':dvp_disc_profit' => $Data['dvp_disc_profit'],
              ':dvp_discount' => $Data['dvp_discount'],
              ':dvp_createat' => $Data['dvp_createat'],
              ':dvp_base_entry' => $Data['dvp_base_entry'],
              ':dvp_base_type' => $Data['dvp_base_type'],
              ':dvp_doc_type' => $Data['dvp_doc_type'],
              ':dvp_id_add' => $Data['dvp_id_add'],
              ':dvp_adress' => $Data['dvp_adress'],
              ':dvp_pay_type' => $Data['dvp_pay_type'],
              ':dvp_attch' => $this->getUrl($Data['dvp_attch'])

        ));

        if($resInsert > 0 ){


          foreach ($ContenidoDetalle as $key => $detail) {

                $sqlInsertDetail = "INSERT INTO vpv1(vp1_doc_entry, vp1_item_code, vp1_quantity, vp1_uom, vp1_whscode, vp1_price, vp1_vat, vp1_vat_sum,
                                    vp1_discount, vp1_line_total, vp1_cost_code, vp1_ubusiness, vp1_project, vp1_acct_code, vp1_base_type, vp1_doc_type,
                                    vp1_avprice, vp1_inventory, vp1_item_name)VALUES(:vp1_doc_entry,:vp1_item_code,:vp1_quantity,:vp1_uom,:vp1_whscode,:vp1_price,:vp1_vat,
                                    :vp1_vat_sum,:vp1_discount,:vp1_line_total,:vp1_cost_code,:vp1_ubusiness,:vp1_project,:vp1_acct_code,:vp1_base_type,
                                    :vp1_doc_type,:vp1_avprice,:vp1_inventory,:vp1_item_name)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                      ':vp1_doc_entry' => $resInsert,
                      ':vp1_item_code' => $Data['vp1_item_code'],
                      ':vp1_quantity' => $Data['vp1_quantity'],
                      ':vp1_uom' => $Data['vp1_uom'],
                      ':vp1_whscode' => $Data['vp1_whscode'],
                      ':vp1_price' => $Data['vp1_price'],
                      ':vp1_vat' => $Data['vp1_vat'],
                      ':vp1_vat_sum' => $Data['vp1_vat_sum'],
                      ':vp1_discount' => $Data['vp1_discount'],
                      ':vp1_line_total' => $Data['vp1_line_total'],
                      ':vp1_cost_code' => $Data['vp1_cost_code'],
                      ':vp1_ubusiness' => $Data['vp1_ubusiness'],
                      ':vp1_project' => $Data['vp1_project'],
                      ':vp1_acct_code' => $Data['vp1_acct_code'],
                      ':vp1_base_type' => $Data['vp1_base_type'],
                      ':vp1_doc_type' => $Data['vp1_doc_type'],
                      ':vp1_avprice' => $Data['vp1_avprice'],
                      ':vp1_inventory' => $Data['vp1_inventory'],
                      ':vp1_item_name' => $Data['vp1_item_name']
                ));
          }

          $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' =>'Pedido registrado con exito'
          );


        }else{

              $respuesta = array(
                'error'   => true,
                'data' => array(),
                'mensaje'	=> 'No se pudo registrar el pedido'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR PEDIDO DE VENTA
  public function updateOrderSale_post(){

      $Data = $this->post();

      if(!isset($Data['dvp_doc_num']) OR !isset($Data['dvp_doc_date']) OR
         !isset($Data['dvp_doc_duedate']) OR !isset($Data['dvp_doc_duedev']) OR
         !isset($Data['dvp_price_list']) OR !isset($Data['dvp_card_code']) OR
         !isset($Data['dvp_card_name']) OR !isset($Data['dvp_currency']) OR
         !isset($Data['dvp_contacid']) OR !isset($Data['dvp_slp_code']) OR
         !isset($Data['dvp_empid']) OR !isset($Data['dvp_comment']) OR
         !isset($Data['dvp_doc_total']) OR !isset($Data['dvp_base_amnt']) OR
         !isset($Data['dvp_tax_total']) OR !isset($Data['dvp_disc_profit']) OR
         !isset($Data['dvp_discount']) OR !isset($Data['dvp_createat']) OR
         !isset($Data['dvp_base_entry']) OR !isset($Data['dvp_base_type']) OR
         !isset($Data['dvp_id_add']) OR !isset($Data['dvp_adress']) OR
         !isset($Data['dvp_pay_type']) OR !isset($Data['dvp_attch']) OR
         !isset($Data['dvp_doc_type']) OR !isset($Data['detail']) OR
         !isset($Data['dvp_docentry'])){

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

      $sqlUpdate = "UPDATE dvpv SET  dvp_doc_num = :dvp_doc_num, dvp_doc_date =:dvp_doc_date, dvp_doc_duedate =:dvp_doc_duedate, dvp_doc_duedev =:dvp_doc_duedev,
                    dvp_price_list =:dvp_price_list, dvp_card_code =:dvp_card_code, dvp_card_name =:dvp_card_name, dvp_currency =:dvp_currency,
                    dvp_contacid =:dvp_contacid, dvp_slp_code =:dvp_slp_code, dvp_empid =:dvp_empid, dvp_comment =:dvp_comment, dvp_doc_total =:dvp_doc_total,
                    dvp_base_amnt =:dvp_base_amnt, dvp_tax_total =:dvp_tax_total, dvp_disc_profit =:dvp_disc_profit, dvp_discount =:dvp_discount, dvp_createat =:dvp_createat,
                    dvp_base_entry =:dvp_base_entry, dvp_base_type =:dvp_base_type, dvp_doc_type =:dvp_doc_type, dvp_id_add =:dvp_id_add, dvp_adress =:dvp_adress,
                    dvp_pay_type =:dvp_pay_type, dvp_attch =:dvp_attch
	                  WHERE dvp_docentry = :dvp_docentry";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

              ':dvp_doc_num' => $Data['dvp_doc_num'],
              ':dvp_doc_date' => $Data['dvp_doc_date'],
              ':dvp_doc_duedate' => $Data['dvp_doc_duedate'],
              ':dvp_doc_duedev' => $Data['dvp_doc_duedev'],
              ':dvp_price_list' => $Data['dvp_price_list'],
              ':dvp_card_code' => $Data['dvp_card_code'],
              ':dvp_card_name' => $Data['dvp_card_name'],
              ':dvp_currency' => $Data['dvp_currency'],
              ':dvp_contacid' => $Data['dvp_contacid'],
              ':dvp_slp_code' => $Data['dvp_slp_code'],
              ':dvp_empid' => $Data['dvp_empid'],
              ':dvp_comment' => $Data['dvp_comment'],
              ':dvp_doc_total' => $Data['dvp_doc_total'],
              ':dvp_base_amnt' => $Data['dvp_base_amnt'],
              ':dvp_tax_total' => $Data['dvp_tax_total'],
              ':dvp_disc_profit' => $Data['dvp_disc_profit'],
              ':dvp_discount' => $Data['dvp_discount'],
              ':dvp_createat' => $Data['dvp_createat'],
              ':dvp_base_entry' => $Data['dvp_base_entry'],
              ':dvp_base_type' => $Data['dvp_base_type'],
              ':dvp_doc_type' => $Data['dvp_doc_type'],
              ':dvp_id_add' => $Data['dvp_id_add'],
              ':dvp_adress' => $Data['dvp_adress'],
              ':dvp_pay_type' => $Data['dvp_pay_type'],
              ':dvp_attch' => $this->getUrl($Data['dvp_attch'])
							':dvp_docentry' => $Data['dvp_docentry'],
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

						$this->pedeo->queryTable("DELETE FROM vpv1 WHERE vp1_doc_entry=:vp1_doc_entry", array(':vp1_doc_entry' => $Data['dvp_docentry']));

						foreach ($ContenidoDetalle as $key => $detail) {

                $sqlInsertDetail = "INSERT INTO vpv1(vp1_doc_entry, vp1_item_code, vp1_quantity, vp1_uom, vp1_whscode, vp1_price, vp1_vat, vp1_vat_sum,
                                    vp1_discount, vp1_line_total, vp1_cost_code, vp1_ubusiness, vp1_project, vp1_acct_code, vp1_base_type, vp1_doc_type,
                                    vp1_avprice, vp1_inventory, vp1_item_name)VALUES(:vp1_doc_entry,:vp1_item_code,:vp1_quantity,:vp1_uom,:vp1_whscode,:vp1_price,:vp1_vat,
                                    :vp1_vat_sum,:vp1_discount,:vp1_line_total,:vp1_cost_code,:vp1_ubusiness,:vp1_project,:vp1_acct_code,:vp1_base_type,
                                    :vp1_doc_type,:vp1_avprice,:vp1_inventory,:vp1_item_name)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':vp1_doc_entry' => $resInsert,
                        ':vp1_item_code' => $Data['vp1_item_code'],
                        ':vp1_quantity' => $Data['vp1_quantity'],
                        ':vp1_uom' => $Data['vp1_uom'],
                        ':vp1_whscode' => $Data['vp1_whscode'],
                        ':vp1_price' => $Data['vp1_price'],
                        ':vp1_vat' => $Data['vp1_vat'],
                        ':vp1_vat_sum' => $Data['vp1_vat_sum'],
                        ':vp1_discount' => $Data['vp1_discount'],
                        ':vp1_line_total' => $Data['vp1_line_total'],
                        ':vp1_cost_code' => $Data['vp1_cost_code'],
                        ':vp1_ubusiness' => $Data['vp1_ubusiness'],
                        ':vp1_project' => $Data['vp1_project'],
                        ':vp1_acct_code' => $Data['vp1_acct_code'],
                        ':vp1_base_type' => $Data['vp1_base_type'],
                        ':vp1_doc_type' => $Data['vp1_doc_type'],
                        ':vp1_avprice' => $Data['vp1_avprice'],
                        ':vp1_inventory' => $Data['vp1_inventory'],
                        ':vp1_item_name' => $Data['vp1_item_name']
                ));
  						}

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Pedido actualizado con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar el pedido'
            );

      }

       $this->response($respuesta);
  }


  //OBTENER PEDIDOS DE VENTA
  public function getOrderSale_get(){

        $sqlSelect = " SELECT * FROM dvpv";

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


	//OBTENER PEDIDO DE VENTA POR ID
	public function getOrderSaleById_get(){

				$Data = $this->get();

				if(!isset($Data['dvp_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM dvpv WHERE dvp_docentry =:dvp_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":dvp_docentry" => $Data['dvp_docentry']));

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


	//OBTENER DETALLE PEDIDO DE VENTA POR ID
	public function getOrderSaleDetail_get(){

				$Data = $this->get();

				if(!isset($Data['vp1_doc_entry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM vpv1 WHERE vp1_doc_entry =:vp1_doc_entry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":vp1_doc_entry" => $Data['vp1_doc_entry']));

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
