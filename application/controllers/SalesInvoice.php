<?php
// FACTURAS DE VENTAS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class SalesInvoice extends REST_Controller {

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

  //CREAR NUEVA FACTURA DE VENTA
	public function createSalesInvoice_post(){

      $Data = $this->post();

      if(!isset($Data['dvf_doc_num']) OR !isset($Data['dvf_doc_date']) OR
         !isset($Data['dvf_doc_duedate']) OR !isset($Data['dvf_doc_duedev']) OR
         !isset($Data['dvf_price_list']) OR !isset($Data['dvf_card_code']) OR
         !isset($Data['dvf_card_name']) OR !isset($Data['dvf_currency']) OR
         !isset($Data['dvf_contacid']) OR !isset($Data['dvf_slp_code']) OR
         !isset($Data['dvf_empid']) OR !isset($Data['dvf_comment']) OR
         !isset($Data['dvf_doc_total']) OR !isset($Data['dvf_base_amnt']) OR
         !isset($Data['dvf_tax_total']) OR !isset($Data['dvf_disc_profit']) OR
         !isset($Data['dvf_discount']) OR !isset($Data['dvf_createat']) OR
         !isset($Data['dvf_base_entry']) OR !isset($Data['dvf_base_type']) OR
         !isset($Data['dvf_id_add']) OR !isset($Data['dvf_adress']) OR
         !isset($Data['dvf_pay_type']) OR !isset($Data['dvf_attch']) OR
         !isset($Data['detail']) OR !isset($Data['dvf_doc_type'])){

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

        $sqlInsert = "INSERT INTO dvfv(dvf_doc_num, dvf_doc_date, dvf_doc_duedate, dvf_doc_duedev, dvf_price_list, dvf_card_code, dvf_card_name,
                      dvf_currency, dvf_contacid, dvf_slp_code, dvf_empid, dvf_comment, dvf_doc_total, dvf_base_amnt, dvf_tax_total, dvf_disc_profit,
                      dvf_discount, dvf_createat, dvf_base_entry, dvf_base_type, dvf_doc_type, dvf_id_add, dvf_adress, dvf_pay_type, dvf_attch)
	                    VALUES (:dvf_doc_num,:dvf_doc_date,:dvf_doc_duedate,:dvf_doc_duedev,:dvf_price_list,:dvf_card_code,:dvf_card_name,:dvf_currency,
                      :dvf_contacid,:dvf_slp_code,:dvf_empid,:dvf_comment,:dvf_doc_total,:dvf_base_amnt,:dvf_tax_total,:dvf_disc_profit,:dvf_discount,
                      :dvf_createat,:dvf_base_entry,:dvf_base_type,:dvf_doc_type,:dvf_id_add,:dvf_adress,:dvf_pay_type,:dvf_attch)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':dvf_doc_num' => $Data['dvf_doc_num'],
              ':dvf_doc_date' => $Data['dvf_doc_date'],
              ':dvf_doc_duedate' => $Data['dvf_doc_duedate'],
              ':dvf_doc_duedev' => $Data['dvf_doc_duedev'],
              ':dvf_price_list' => $Data['dvf_price_list'],
              ':dvf_card_code' => $Data['dvf_card_code'],
              ':dvf_card_name' => $Data['dvf_card_name'],
              ':dvf_currency' => $Data['dvf_currency'],
              ':dvf_contacid' => $Data['dvf_contacid'],
              ':dvf_slp_code' => $Data['dvf_slp_code'],
              ':dvf_empid' => $Data['dvf_empid'],
              ':dvf_comment' => $Data['dvf_comment'],
              ':dvf_doc_total' => $Data['dvf_doc_total'],
              ':dvf_base_amnt' => $Data['dvf_base_amnt'],
              ':dvf_tax_total' => $Data['dvf_tax_total'],
              ':dvf_disc_profit' => $Data['dvf_disc_profit'],
              ':dvf_discount' => $Data['dvf_discount'],
              ':dvf_createat' => $Data['dvf_createat'],
              ':dvf_base_entry' => $Data['dvf_base_entry'],
              ':dvf_base_type' => $Data['dvf_base_type'],
              ':dvf_doc_type' => $Data['dvf_doc_type'],
              ':dvf_id_add' => $Data['dvf_id_add'],
              ':dvf_adress' => $Data['dvf_adress'],
              ':dvf_pay_type' => $Data['dvf_pay_type'],
              ':dvf_attch' => $this->getUrl($Data['dvf_attch'])

        ));

        if($resInsert > 0 ){


          foreach ($ContenidoDetalle as $key => $detail) {



                $sqlInsertDetail = "INSERT INTO vfv1(fv1_doc_entry, fv1_item_code, fv1_quantity, fv1_uom, fv1_whscode, fv1_price, fv1_vat, fv1_vat_sum,
                                    fv1_discount, fv1_line_total, fv1_cost_code, fv1_ubusiness, fv1_project, fv1_acct_code, fv1_base_type, fv1_doc_type,
                                    fv1_avprice, fv1_inventory, fv1_item_name)VALUES(:fv1_doc_entry,:fv1_item_code,:fv1_quantity,:fv1_uom,:fv1_whscode,:fv1_price,:fv1_vat,
                                    :fv1_vat_sum,:fv1_discount,:fv1_line_total,:fv1_cost_code,:fv1_ubusiness,:fv1_project,:fv1_acct_code,:fv1_base_type,
                                    :fv1_doc_type,:fv1_avprice,:fv1_inventory,fv1_item_name)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':fv1_doc_entry' => $resInsert,
                        ':fv1_item_code' => $Data['fv1_item_code'],
                        ':fv1_quantity' => $Data['fv1_quantity'],
                        ':fv1_uom' => $Data['fv1_uom'],
                        ':fv1_whscode' => $Data['fv1_whscode'],
                        ':fv1_price' => $Data['fv1_price'],
                        ':fv1_vat' => $Data['fv1_vat'],
                        ':fv1_vat_sum' => $Data['fv1_vat_sum'],
                        ':fv1_discount' => $Data['fv1_discount'],
                        ':fv1_line_total' => $Data['fv1_line_total'],
                        ':fv1_cost_code' => $Data['fv1_cost_code'],
                        ':fv1_ubusiness' => $Data['fv1_ubusiness'],
                        ':fv1_project' => $Data['fv1_project'],
                        ':fv1_acct_code' => $Data['fv1_acct_code'],
                        ':fv1_base_type' => $Data['fv1_base_type'],
                        ':fv1_doc_type' => $Data['fv1_doc_type'],
                        ':fv1_avprice' => $Data['fv1_avprice'],
                        ':fv1_inventory' => $Data['fv1_inventory'],
                        ':fv1_item_name' => $Data['fv1_item_name']
                ));
          }

          $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' =>'Factura registrada con exito'
          );


        }else{

              $respuesta = array(
                'error'   => true,
                'data' => array(),
                'mensaje'	=> 'No se pudo registrar la factura'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR FACTURA DE VENTA
  public function updateSalesInvoice_post(){

      $Data = $this->post();

      if(!isset($Data['dvf_doc_num']) OR !isset($Data['dvf_doc_date']) OR
         !isset($Data['dvf_doc_duedate']) OR !isset($Data['dvf_doc_duedev']) OR
         !isset($Data['dvf_price_list']) OR !isset($Data['dvf_card_code']) OR
         !isset($Data['dvf_card_name']) OR !isset($Data['dvf_currency']) OR
         !isset($Data['dvf_contacid']) OR !isset($Data['dvf_slp_code']) OR
         !isset($Data['dvf_empid']) OR !isset($Data['dvf_comment']) OR
         !isset($Data['dvf_doc_total']) OR !isset($Data['dvf_base_amnt']) OR
         !isset($Data['dvf_tax_total']) OR !isset($Data['dvf_disc_profit']) OR
         !isset($Data['dvf_discount']) OR !isset($Data['dvf_createat']) OR
         !isset($Data['dvf_base_entry']) OR !isset($Data['dvf_base_type']) OR
         !isset($Data['dvf_id_add']) OR !isset($Data['dvf_adress']) OR
         !isset($Data['dvf_pay_type']) OR !isset($Data['dvf_attch']) OR
         !isset($Data['detail']) OR !isset($Data['dvf_docentry'])){

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
            'mensaje' =>'No se encontro el detalle de la factura'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }

      $sqlUpdate = "UPDATE dvfv SET  dvf_doc_num = :dvf_doc_num, dvf_doc_date =:dvf_doc_date, dvf_doc_duedate =:dvf_doc_duedate, dvf_doc_duedev =:dvf_doc_duedev,
                    dvf_price_list =:dvf_price_list, dvf_card_code =:dvf_card_code, dvf_card_name =:dvf_card_name, dvf_currency =:dvf_currency,
                    dvf_contacid =:dvf_contacid, dvf_slp_code =:dvf_slp_code, dvf_empid =:dvf_empid, dvf_comment =:dvf_comment, dvf_doc_total =:dvf_doc_total,
                    dvf_base_amnt =:dvf_base_amnt, dvf_tax_total =:dvf_tax_total, dvf_disc_profit =:dvf_disc_profit, dvf_discount =:dvf_discount, dvf_createat =:dvf_createat,
                    dvf_base_entry =:dvf_base_entry, dvf_base_type =:dvf_base_type, dvf_doc_type =:dvf_doc_type, dvf_id_add =:dvf_id_add, dvf_adress =:dvf_adress,
                    dvf_pay_type =:dvf_pay_type, dvf_attch =:dvf_attch
	                  WHERE dvf_doc_entry = :dvf_doc_entry ";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

              ':dvf_doc_num' => $Data['dvf_doc_num'],
              ':dvf_doc_date' => $Data['dvf_doc_date'],
              ':dvf_doc_duedate' => $Data['dvf_doc_duedate'],
              ':dvf_doc_duedev' => $Data['dvf_doc_duedev'],
              ':dvf_price_list' => $Data['dvf_price_list'],
              ':dvf_card_code' => $Data['dvf_card_code'],
              ':dvf_card_name' => $Data['dvf_card_name'],
              ':dvf_currency' => $Data['dvf_currency'],
              ':dvf_contacid' => $Data['dvf_contacid'],
              ':dvf_slp_code' => $Data['dvf_slp_code'],
              ':dvf_empid' => $Data['dvf_empid'],
              ':dvf_comment' => $Data['dvf_comment'],
              ':dvf_doc_total' => $Data['dvf_doc_total'],
              ':dvf_base_amnt' => $Data['dvf_base_amnt'],
              ':dvf_tax_total' => $Data['dvf_tax_total'],
              ':dvf_disc_profit' => $Data['dvf_disc_profit'],
              ':dvf_discount' => $Data['dvf_discount'],
              ':dvf_createat' => $Data['dvf_createat'],
              ':dvf_base_entry' => $Data['dvf_base_entry'],
              ':dvf_base_type' => $Data['dvf_base_type'],
              ':dvf_doc_type' => $Data['dvf_doc_type'],
              ':dvf_id_add' => $Data['dvf_id_add'],
              ':dvf_adress' => $Data['dvf_adress'],
              ':dvf_pay_type' => $Data['dvf_pay_type'],
              ':dvf_attch' => $this->getUrl($Data['dvf_attch'])
							':dvf_doc_entry' => $Data['dvf_doc_entry'],
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

						$this->pedeo->queryTable("DELETE FROM vfv1 WHERE fv1_doc_entry=:fv1_doc_entry", array(':fv1_doc_entry' => $Data['dvf_docentry']));

						foreach ($ContenidoDetalle as $key => $detail) {

                $sqlInsertDetail = "INSERT INTO vev1(fv1_doc_entry, fv1_item_code, fv1_quantity, fv1_uom, fv1_whscode, fv1_price, fv1_vat, fv1_vat_sum,
                                    fv1_discount, fv1_line_total, fv1_cost_code, fv1_ubusiness, fv1_project, fv1_acct_code, fv1_base_type, fv1_doc_type,
                                    fv1_avprice, fv1_inventory, fv1_item_name)VALUES(:fv1_doc_entry,:fv1_item_code,:fv1_quantity,:fv1_uom,:fv1_whscode,:fv1_price,:fv1_vat,
                                    :fv1_vat_sum,:fv1_discount,:fv1_line_total,:fv1_cost_code,:fv1_ubusiness,:fv1_project,:fv1_acct_code,:fv1_base_type,
                                    :fv1_doc_type,:fv1_avprice,:fv1_inventory,fv1_item_name)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':fv1_doc_entry' => $resInsert,
                        ':fv1_item_code' => $Data['fv1_item_code'],
                        ':fv1_quantity' => $Data['fv1_quantity'],
                        ':fv1_uom' => $Data['fv1_uom'],
                        ':fv1_whscode' => $Data['fv1_whscode'],
                        ':fv1_price' => $Data['fv1_price'],
                        ':fv1_vat' => $Data['fv1_vat'],
                        ':fv1_vat_sum' => $Data['fv1_vat_sum'],
                        ':fv1_discount' => $Data['fv1_discount'],
                        ':fv1_line_total' => $Data['fv1_line_total'],
                        ':fv1_cost_code' => $Data['fv1_cost_code'],
                        ':fv1_ubusiness' => $Data['fv1_ubusiness'],
                        ':fv1_project' => $Data['fv1_project'],
                        ':fv1_acct_code' => $Data['fv1_acct_code'],
                        ':fv1_base_type' => $Data['fv1_base_type'],
                        ':fv1_doc_type' => $Data['fv1_doc_type'],
                        ':fv1_avprice' => $Data['fv1_avprice'],
                        ':fv1_inventory' => $Data['fv1_inventory'],
                        ':fv1_item_name' => $Data['fv1_item_name']
                ));
  						}

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Entrada actualizada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la entrada'
            );

      }

       $this->response($respuesta);
  }


  //OBTENER FACUTURAS DE VENTAS
  public function getSalesInvoice_get(){

        $sqlSelect = " SELECT * FROM dvfv";

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


	//OBTENER FACTURA DE VENTA POR ID
	public function getSalesInvoiceById_get(){

				$Data = $this->get();

				if(!isset($Data['dvf_doc_entry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM dvfv WHERE dvf_doc_entry =:dvf_doc_entry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":dvf_doc_entry" => $Data['dvf_doc_entry']));

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


	//OBTENER DETALLE FACUTURAS DE VENTAS POR ID
	public function getSalesInvoiceDetail_get(){

				$Data = $this->get();

				if(!isset($Data['fv1_doc_entry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM vfv1 WHERE fv1_doc_entry =:fv1_doc_entry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":fv1_doc_entry" => $Data['fv1_doc_entry']));

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
