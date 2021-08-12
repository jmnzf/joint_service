<?php
// DEVOLUCIONES VENTAS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class ReturnSale extends REST_Controller {

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

  //CREAR DEVOLUCION
	public function createReturnSale_post(){

      $Data = $this->post();

      if(!isset($Data['vdv_doc_num']) OR !isset($Data['vdv_doc_date']) OR
         !isset($Data['vdv_doc_duedate']) OR !isset($Data['vdv_doc_duedev']) OR
         !isset($Data['vdv_price_list']) OR !isset($Data['vdv_card_code']) OR
         !isset($Data['vdv_card_name']) OR !isset($Data['vdv_currency']) OR
         !isset($Data['vdv_contacid']) OR !isset($Data['vdv_slp_code']) OR
         !isset($Data['vdv_empid']) OR !isset($Data['vdv_comment']) OR
         !isset($Data['vdv_doc_total']) OR !isset($Data['vdv_base_amnt']) OR
         !isset($Data['vdv_tax_total']) OR !isset($Data['vdv_disc_profit']) OR
         !isset($Data['vdv_discount']) OR !isset($Data['vdv_createat']) OR
         !isset($Data['vdv_base_entry']) OR !isset($Data['vdv_base_type']) OR
         !isset($Data['vdv_id_add']) OR !isset($Data['vdv_adress']) OR
         !isset($Data['vdv_pay_type']) OR !isset($Data['vdv_attch']) OR
         !isset($Data['detail']) OR !isset($Data['vdv_doc_type'])){

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
            'mensaje' =>'No se encontro el detalle de la devolución'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }

        $sqlInsert = "INSERT INTO dvdv(vdv_doc_num, vdv_doc_date, vdv_doc_duedate, vdv_doc_duedev, vdv_price_list, vdv_card_code, vdv_card_name,
                      vdv_currency, vdv_contacid, vdv_slp_code, vdv_empid, vdv_comment, vdv_doc_total, vdv_base_amnt, vdv_tax_total, vdv_disc_profit,
                      vdv_discount, vdv_createat, vdv_base_entry, vdv_base_type, vdv_doc_type, vdv_id_add, vdv_adress, vdv_pay_type, vdv_attch)
	                    VALUES (:vdv_doc_num,:vdv_doc_date,:vdv_doc_duedate,:vdv_doc_duedev,:vdv_price_list,:vdv_card_code,:vdv_card_name,:vdv_currency,
                      :vdv_contacid,:vdv_slp_code,:vdv_empid,:vdv_comment,:vdv_doc_total,:vdv_base_amnt,:vdv_tax_total,:vdv_disc_profit,:vdv_discount,
                      :vdv_createat,:vdv_base_entry,:vdv_base_type,:vdv_doc_type,:vdv_id_add,:vdv_adress,:vdv_pay_type,:vdv_attch)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':vdv_doc_num' => $Data['vdv_doc_num'],
              ':vdv_doc_date' => $Data['vdv_doc_date'],
              ':vdv_doc_duedate' => $Data['vdv_doc_duedate'],
              ':vdv_doc_duedev' => $Data['vdv_doc_duedev'],
              ':vdv_price_list' => $Data['vdv_price_list'],
              ':vdv_card_code' => $Data['vdv_card_code'],
              ':vdv_card_name' => $Data['vdv_card_name'],
              ':vdv_currency' => $Data['vdv_currency'],
              ':vdv_contacid' => $Data['vdv_contacid'],
              ':vdv_slp_code' => $Data['vdv_slp_code'],
              ':vdv_empid' => $Data['vdv_empid'],
              ':vdv_comment' => $Data['vdv_comment'],
              ':vdv_doc_total' => $Data['vdv_doc_total'],
              ':vdv_base_amnt' => $Data['vdv_base_amnt'],
              ':vdv_tax_total' => $Data['vdv_tax_total'],
              ':vdv_disc_profit' => $Data['vdv_disc_profit'],
              ':vdv_discount' => $Data['vdv_discount'],
              ':vdv_createat' => $Data['vdv_createat'],
              ':vdv_base_entry' => $Data['vdv_base_entry'],
              ':vdv_base_type' => $Data['vdv_base_type'],
              ':vdv_doc_type' => $Data['vdv_doc_type'],
              ':vdv_id_add' => $Data['vdv_id_add'],
              ':vdv_adress' => $Data['vdv_adress'],
              ':vdv_pay_type' => $Data['vdv_pay_type'],
              ':vdv_attch' => $this->getUrl($Data['vdv_attch'])

        ));

        if($resInsert > 0 ){


          foreach ($ContenidoDetalle as $key => $detail) {



                $sqlInsertDetail = "INSERT INTO vdv1(dv1_doc_entry, dv1_item_code, dv1_quantity, dv1_uom, dv1_whscode, dv1_price, dv1_vat, dv1_vat_sum,
                                    dv1_discount, dv1_line_total, dv1_cost_code, dv1_ubusiness, dv1_project, dv1_acct_code, dv1_base_type, dv1_doc_type,
                                    dv1_avprice, dv1_inventory, dv1_item_name)VALUES(:dv1_doc_entry,:dv1_item_code,:dv1_quantity,:dv1_uom,:dv1_whscode,:dv1_price,:dv1_vat,
                                    :dv1_vat_sum,:dv1_discount,:dv1_line_total,:dv1_cost_code,:dv1_ubusiness,:dv1_project,:dv1_acct_code,:dv1_base_type,
                                    :dv1_doc_type,:dv1_avprice,:dv1_inventory,dv1_item_name)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':dv1_doc_entry' => $resInsert,
                        ':dv1_item_code' => $Data['dv1_item_code'],
                        ':dv1_quantity' => $Data['dv1_quantity'],
                        ':dv1_uom' => $Data['dv1_uom'],
                        ':dv1_whscode' => $Data['dv1_whscode'],
                        ':dv1_price' => $Data['dv1_price'],
                        ':dv1_vat' => $Data['dv1_vat'],
                        ':dv1_vat_sum' => $Data['dv1_vat_sum'],
                        ':dv1_discount' => $Data['dv1_discount'],
                        ':dv1_line_total' => $Data['dv1_line_total'],
                        ':dv1_cost_code' => $Data['dv1_cost_code'],
                        ':dv1_ubusiness' => $Data['dv1_ubusiness'],
                        ':dv1_project' => $Data['dv1_project'],
                        ':dv1_acct_code' => $Data['dv1_acct_code'],
                        ':dv1_base_type' => $Data['dv1_base_type'],
                        ':dv1_doc_type' => $Data['dv1_doc_type'],
                        ':dv1_avprice' => $Data['dv1_avprice'],
                        ':dv1_inventory' => $Data['dv1_inventory'],
                        ':dv1_item_name' => $Data['dv1_item_name']
                ));
          }

          $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' =>'Devolución registrada con exito'
          );


        }else{

              $respuesta = array(
                'error'   => true,
                'data' => array(),
                'mensaje'	=> 'No se pudo registrar la devolución'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR DEVOLUCION
  public function updateReturnSale_post(){

      $Data = $this->post();

      if(!isset($Data['vdv_doc_num']) OR !isset($Data['vdv_doc_date']) OR
         !isset($Data['vdv_doc_duedate']) OR !isset($Data['vdv_doc_duedev']) OR
         !isset($Data['vdv_price_list']) OR !isset($Data['vdv_card_code']) OR
         !isset($Data['vdv_card_name']) OR !isset($Data['vdv_currency']) OR
         !isset($Data['vdv_contacid']) OR !isset($Data['vdv_slp_code']) OR
         !isset($Data['vdv_empid']) OR !isset($Data['vdv_comment']) OR
         !isset($Data['vdv_doc_total']) OR !isset($Data['vdv_base_amnt']) OR
         !isset($Data['vdv_tax_total']) OR !isset($Data['vdv_disc_profit']) OR
         !isset($Data['vdv_discount']) OR !isset($Data['vdv_createat']) OR
         !isset($Data['vdv_base_entry']) OR !isset($Data['vdv_base_type']) OR
         !isset($Data['vdv_id_add']) OR !isset($Data['vdv_adress']) OR
         !isset($Data['vdv_pay_type']) OR !isset($Data['vdv_attch']) OR
         !isset($Data['detail']) OR !isset($Data['vdv_docentry'])){

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
            'mensaje' =>'No se encontro el detalle de la devolución'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }

      $sqlUpdate = "UPDATE dvdv SET  vdv_doc_num = :vdv_doc_num, vdv_doc_date =:vdv_doc_date, vdv_doc_duedate =:vdv_doc_duedate, vdv_doc_duedev =:vdv_doc_duedev,
                    vdv_price_list =:vdv_price_list, vdv_card_code =:vdv_card_code, vdv_card_name =:vdv_card_name, vdv_currency =:vdv_currency,
                    vdv_contacid =:vdv_contacid, vdv_slp_code =:vdv_slp_code, vdv_empid =:vdv_empid, vdv_comment =:vdv_comment, vdv_doc_total =:vdv_doc_total,
                    vdv_base_amnt =:vdv_base_amnt, vdv_tax_total =:vdv_tax_total, vdv_disc_profit =:vdv_disc_profit, vdv_discount =:vdv_discount, vdv_createat =:vdv_createat,
                    vdv_base_entry =:vdv_base_entry, vdv_base_type =:vdv_base_type, vdv_doc_type =:vdv_doc_type, vdv_id_add =:vdv_id_add, vdv_adress =:vdv_adress,
                    vdv_pay_type =:vdv_pay_type, vdv_attch =:vdv_attch
	                  WHERE vdv_doc_entry = :vdv_doc_entry ";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

              ':vdv_doc_num' => $Data['vdv_doc_num'],
              ':vdv_doc_date' => $Data['vdv_doc_date'],
              ':vdv_doc_duedate' => $Data['vdv_doc_duedate'],
              ':vdv_doc_duedev' => $Data['vdv_doc_duedev'],
              ':vdv_price_list' => $Data['vdv_price_list'],
              ':vdv_card_code' => $Data['vdv_card_code'],
              ':vdv_card_name' => $Data['vdv_card_name'],
              ':vdv_currency' => $Data['vdv_currency'],
              ':vdv_contacid' => $Data['vdv_contacid'],
              ':vdv_slp_code' => $Data['vdv_slp_code'],
              ':vdv_empid' => $Data['vdv_empid'],
              ':vdv_comment' => $Data['vdv_comment'],
              ':vdv_doc_total' => $Data['vdv_doc_total'],
              ':vdv_base_amnt' => $Data['vdv_base_amnt'],
              ':vdv_tax_total' => $Data['vdv_tax_total'],
              ':vdv_disc_profit' => $Data['vdv_disc_profit'],
              ':vdv_discount' => $Data['vdv_discount'],
              ':vdv_createat' => $Data['vdv_createat'],
              ':vdv_base_entry' => $Data['vdv_base_entry'],
              ':vdv_base_type' => $Data['vdv_base_type'],
              ':vdv_doc_type' => $Data['vdv_doc_type'],
              ':vdv_id_add' => $Data['vdv_id_add'],
              ':vdv_adress' => $Data['vdv_adress'],
              ':vdv_pay_type' => $Data['vdv_pay_type'],
              ':vdv_attch' => $this->getUrl($Data['vdv_attch'])
							':vdv_doc_entry' => $Data['vdv_doc_entry'],
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

						$this->pedeo->queryTable("DELETE FROM vdv1 WHERE dv1_doc_entry=:dv1_doc_entry", array(':dv1_doc_entry' => $Data['vdv_docentry']));

						foreach ($ContenidoDetalle as $key => $detail) {

                $sqlInsertDetail = "INSERT INTO vev1(dv1_doc_entry, dv1_item_code, dv1_quantity, dv1_uom, dv1_whscode, dv1_price, dv1_vat, dv1_vat_sum,
                                    dv1_discount, dv1_line_total, dv1_cost_code, dv1_ubusiness, dv1_project, dv1_acct_code, dv1_base_type, dv1_doc_type,
                                    dv1_avprice, dv1_inventory, dv1_item_name)VALUES(:dv1_doc_entry,:dv1_item_code,:dv1_quantity,:dv1_uom,:dv1_whscode,:dv1_price,:dv1_vat,
                                    :dv1_vat_sum,:dv1_discount,:dv1_line_total,:dv1_cost_code,:dv1_ubusiness,:dv1_project,:dv1_acct_code,:dv1_base_type,
                                    :dv1_doc_type,:dv1_avprice,:dv1_inventory,dv1_item_name)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':dv1_doc_entry' => $resInsert,
                        ':dv1_item_code' => $Data['dv1_item_code'],
                        ':dv1_quantity' => $Data['dv1_quantity'],
                        ':dv1_uom' => $Data['dv1_uom'],
                        ':dv1_whscode' => $Data['dv1_whscode'],
                        ':dv1_price' => $Data['dv1_price'],
                        ':dv1_vat' => $Data['dv1_vat'],
                        ':dv1_vat_sum' => $Data['dv1_vat_sum'],
                        ':dv1_discount' => $Data['dv1_discount'],
                        ':dv1_line_total' => $Data['dv1_line_total'],
                        ':dv1_cost_code' => $Data['dv1_cost_code'],
                        ':dv1_ubusiness' => $Data['dv1_ubusiness'],
                        ':dv1_project' => $Data['dv1_project'],
                        ':dv1_acct_code' => $Data['dv1_acct_code'],
                        ':dv1_base_type' => $Data['dv1_base_type'],
                        ':dv1_doc_type' => $Data['dv1_doc_type'],
                        ':dv1_avprice' => $Data['dv1_avprice'],
                        ':dv1_inventory' => $Data['dv1_inventory'],
                        ':dv1_item_name' => $Data['dv1_item_name']
                ));
  						}

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Devolución actualizada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la devolución'
            );

      }

       $this->response($respuesta);
  }


  //OBTENER DEVOLUCIONES
  public function getReturnSale_get(){

        $sqlSelect = " SELECT * FROM dvdv";

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


	//OBTENER DEVOLUCION POR ID
	public function getReturnSaleById_get(){

				$Data = $this->get();

				if(!isset($Data['vdv_doc_entry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM dvdv WHERE vdv_doc_entry =:vdv_doc_entry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":vdv_doc_entry" => $Data['vdv_doc_entry']));

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


	//OBTENER DETALLE DEVOLUCION POR ID
	public function getReturnSaleDetail_get(){

				$Data = $this->get();

				if(!isset($Data['dv1_doc_entry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM vdv1 WHERE dv1_doc_entry =:dv1_doc_entry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":dv1_doc_entry" => $Data['dv1_doc_entry']));

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
