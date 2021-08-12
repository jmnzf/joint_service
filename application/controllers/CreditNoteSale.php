<?php
// NOTAS DE CREDITO DE VENTAS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class CreditNoteSale extends REST_Controller {

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

  //CREAR NUEVA NOTA DE CREDITO
	public function createCreditNoteSale_post(){

      $Data = $this->post();

      if(!isset($Data['vnc_doc_num']) OR !isset($Data['vnc_doc_date']) OR
         !isset($Data['vnc_doc_duedate']) OR !isset($Data['vnc_doc_duedev']) OR
         !isset($Data['vnc_price_list']) OR !isset($Data['vnc_card_code']) OR
         !isset($Data['vnc_card_name']) OR !isset($Data['vnc_currency']) OR
         !isset($Data['vnc_contacid']) OR !isset($Data['vnc_slp_code']) OR
         !isset($Data['vnc_empid']) OR !isset($Data['vnc_comment']) OR
         !isset($Data['vnc_doc_total']) OR !isset($Data['vnc_base_amnt']) OR
         !isset($Data['vnc_tax_total']) OR !isset($Data['vnc_disc_profit']) OR
         !isset($Data['vnc_discount']) OR !isset($Data['vnc_createat']) OR
         !isset($Data['vnc_base_entry']) OR !isset($Data['vnc_base_type']) OR
         !isset($Data['vnc_id_add']) OR !isset($Data['vnc_adress']) OR
         !isset($Data['vnc_pay_type']) OR !isset($Data['vnc_attch']) OR
         !isset($Data['detail']) OR !isset($Data['vnc_doc_type'])){

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
            'mensaje' =>'No se encontro el detalle de la nota de credito'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }

        $sqlInsert = "INSERT INTO dvnc(vnc_doc_num, vnc_doc_date, vnc_doc_duedate, vnc_doc_duedev, vnc_price_list, vnc_card_code, vnc_card_name,
                      vnc_currency, vnc_contacid, vnc_slp_code, vnc_empid, vnc_comment, vnc_doc_total, vnc_base_amnt, vnc_tax_total, vnc_disc_profit,
                      vnc_discount, vnc_createat, vnc_base_entry, vnc_base_type, vnc_doc_type, vnc_id_add, vnc_adress, vnc_pay_type, vnc_attch)
	                    VALUES (:vnc_doc_num,:vnc_doc_date,:vnc_doc_duedate,:vnc_doc_duedev,:vnc_price_list,:vnc_card_code,:vnc_card_name,:vnc_currency,
                      :vnc_contacid,:vnc_slp_code,:vnc_empid,:vnc_comment,:vnc_doc_total,:vnc_base_amnt,:vnc_tax_total,:vnc_disc_profit,:vnc_discount,
                      :vnc_createat,:vnc_base_entry,:vnc_base_type,:vnc_doc_type,:vnc_id_add,:vnc_adress,:vnc_pay_type,:vnc_attch)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':vnc_doc_num' => $Data['vnc_doc_num'],
              ':vnc_doc_date' => $Data['vnc_doc_date'],
              ':vnc_doc_duedate' => $Data['vnc_doc_duedate'],
              ':vnc_doc_duedev' => $Data['vnc_doc_duedev'],
              ':vnc_price_list' => $Data['vnc_price_list'],
              ':vnc_card_code' => $Data['vnc_card_code'],
              ':vnc_card_name' => $Data['vnc_card_name'],
              ':vnc_currency' => $Data['vnc_currency'],
              ':vnc_contacid' => $Data['vnc_contacid'],
              ':vnc_slp_code' => $Data['vnc_slp_code'],
              ':vnc_empid' => $Data['vnc_empid'],
              ':vnc_comment' => $Data['vnc_comment'],
              ':vnc_doc_total' => $Data['vnc_doc_total'],
              ':vnc_base_amnt' => $Data['vnc_base_amnt'],
              ':vnc_tax_total' => $Data['vnc_tax_total'],
              ':vnc_disc_profit' => $Data['vnc_disc_profit'],
              ':vnc_discount' => $Data['vnc_discount'],
              ':vnc_createat' => $Data['vnc_createat'],
              ':vnc_base_entry' => $Data['vnc_base_entry'],
              ':vnc_base_type' => $Data['vnc_base_type'],
              ':vnc_doc_type' => $Data['vnc_doc_type'],
              ':vnc_id_add' => $Data['vnc_id_add'],
              ':vnc_adress' => $Data['vnc_adress'],
              ':vnc_pay_type' => $Data['vnc_pay_type'],
              ':vnc_attch' => $this->getUrl($Data['vnc_attch'])

        ));

        if($resInsert > 0 ){


          foreach ($ContenidoDetalle as $key => $detail) {



                $sqlInsertDetail = "INSERT INTO vnc1(nc1_doc_entry, nc1_item_code, nc1_quantity, nc1_uom, nc1_whscode, nc1_price, nc1_vat, nc1_vat_sum,
                                    nc1_discount, nc1_line_total, nc1_cost_code, nc1_ubusiness, nc1_project, nc1_acct_code, nc1_base_type, nc1_doc_type,
                                    nc1_avprice, nc1_inventory, nc1_item_name)VALUES(:nc1_doc_entry,:nc1_item_code,:nc1_quantity,:nc1_uom,:nc1_whscode,:nc1_price,:nc1_vat,
                                    :nc1_vat_sum,:nc1_discount,:nc1_line_total,:nc1_cost_code,:nc1_ubusiness,:nc1_project,:nc1_acct_code,:nc1_base_type,
                                    :nc1_doc_type,:nc1_avprice,:nc1_inventory,nc1_item_name)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':nc1_doc_entry' => $resInsert,
                        ':nc1_item_code' => $Data['nc1_item_code'],
                        ':nc1_quantity' => $Data['nc1_quantity'],
                        ':nc1_uom' => $Data['nc1_uom'],
                        ':nc1_whscode' => $Data['nc1_whscode'],
                        ':nc1_price' => $Data['nc1_price'],
                        ':nc1_vat' => $Data['nc1_vat'],
                        ':nc1_vat_sum' => $Data['nc1_vat_sum'],
                        ':nc1_discount' => $Data['nc1_discount'],
                        ':nc1_line_total' => $Data['nc1_line_total'],
                        ':nc1_cost_code' => $Data['nc1_cost_code'],
                        ':nc1_ubusiness' => $Data['nc1_ubusiness'],
                        ':nc1_project' => $Data['nc1_project'],
                        ':nc1_acct_code' => $Data['nc1_acct_code'],
                        ':nc1_base_type' => $Data['nc1_base_type'],
                        ':nc1_doc_type' => $Data['nc1_doc_type'],
                        ':nc1_avprice' => $Data['nc1_avprice'],
                        ':nc1_inventory' => $Data['nc1_inventory'],
                        ':nc1_item_name' => $Data['nc1_item_name']
                ));
          }

          $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' =>'Nota de credito registrada con exito'
          );


        }else{

              $respuesta = array(
                'error'   => true,
                'data' => array(),
                'mensaje'	=> 'No se pudo registrar la nota de credito'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR FACTURA DE VENTA
  public function updateCreditNoteSale_post(){

      $Data = $this->post();

      if(!isset($Data['vnc_doc_num']) OR !isset($Data['vnc_doc_date']) OR
         !isset($Data['vnc_doc_duedate']) OR !isset($Data['vnc_doc_duedev']) OR
         !isset($Data['vnc_price_list']) OR !isset($Data['vnc_card_code']) OR
         !isset($Data['vnc_card_name']) OR !isset($Data['vnc_currency']) OR
         !isset($Data['vnc_contacid']) OR !isset($Data['vnc_slp_code']) OR
         !isset($Data['vnc_empid']) OR !isset($Data['vnc_comment']) OR
         !isset($Data['vnc_doc_total']) OR !isset($Data['vnc_base_amnt']) OR
         !isset($Data['vnc_tax_total']) OR !isset($Data['vnc_disc_profit']) OR
         !isset($Data['vnc_discount']) OR !isset($Data['vnc_createat']) OR
         !isset($Data['vnc_base_entry']) OR !isset($Data['vnc_base_type']) OR
         !isset($Data['vnc_id_add']) OR !isset($Data['vnc_adress']) OR
         !isset($Data['vnc_pay_type']) OR !isset($Data['vnc_attch']) OR
         !isset($Data['detail']) OR !isset($Data['vnc_docentry'])){

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
            'mensaje' =>'No se encontro el detalle de la nota de credito'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }

      $sqlUpdate = "UPDATE dvnc SET  vnc_doc_num = :vnc_doc_num, vnc_doc_date =:vnc_doc_date, vnc_doc_duedate =:vnc_doc_duedate, vnc_doc_duedev =:vnc_doc_duedev,
                    vnc_price_list =:vnc_price_list, vnc_card_code =:vnc_card_code, vnc_card_name =:vnc_card_name, vnc_currency =:vnc_currency,
                    vnc_contacid =:vnc_contacid, vnc_slp_code =:vnc_slp_code, vnc_empid =:vnc_empid, vnc_comment =:vnc_comment, vnc_doc_total =:vnc_doc_total,
                    vnc_base_amnt =:vnc_base_amnt, vnc_tax_total =:vnc_tax_total, vnc_disc_profit =:vnc_disc_profit, vnc_discount =:vnc_discount, vnc_createat =:vnc_createat,
                    vnc_base_entry =:vnc_base_entry, vnc_base_type =:vnc_base_type, vnc_doc_type =:vnc_doc_type, vnc_id_add =:vnc_id_add, vnc_adress =:vnc_adress,
                    vnc_pay_type =:vnc_pay_type, vnc_attch =:vnc_attch
	                  WHERE vnc_doc_entry = :vnc_doc_entry ";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

              ':vnc_doc_num' => $Data['vnc_doc_num'],
              ':vnc_doc_date' => $Data['vnc_doc_date'],
              ':vnc_doc_duedate' => $Data['vnc_doc_duedate'],
              ':vnc_doc_duedev' => $Data['vnc_doc_duedev'],
              ':vnc_price_list' => $Data['vnc_price_list'],
              ':vnc_card_code' => $Data['vnc_card_code'],
              ':vnc_card_name' => $Data['vnc_card_name'],
              ':vnc_currency' => $Data['vnc_currency'],
              ':vnc_contacid' => $Data['vnc_contacid'],
              ':vnc_slp_code' => $Data['vnc_slp_code'],
              ':vnc_empid' => $Data['vnc_empid'],
              ':vnc_comment' => $Data['vnc_comment'],
              ':vnc_doc_total' => $Data['vnc_doc_total'],
              ':vnc_base_amnt' => $Data['vnc_base_amnt'],
              ':vnc_tax_total' => $Data['vnc_tax_total'],
              ':vnc_disc_profit' => $Data['vnc_disc_profit'],
              ':vnc_discount' => $Data['vnc_discount'],
              ':vnc_createat' => $Data['vnc_createat'],
              ':vnc_base_entry' => $Data['vnc_base_entry'],
              ':vnc_base_type' => $Data['vnc_base_type'],
              ':vnc_doc_type' => $Data['vnc_doc_type'],
              ':vnc_id_add' => $Data['vnc_id_add'],
              ':vnc_adress' => $Data['vnc_adress'],
              ':vnc_pay_type' => $Data['vnc_pay_type'],
              ':vnc_attch' => $this->getUrl($Data['vnc_attch'])
							':vnc_doc_entry' => $Data['vnc_doc_entry'],
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

						$this->pedeo->queryTable("DELETE FROM vnc1 WHERE nc1_doc_entry=:nc1_doc_entry", array(':nc1_doc_entry' => $Data['vnc_docentry']));

						foreach ($ContenidoDetalle as $key => $detail) {

                $sqlInsertDetail = "INSERT INTO vev1(nc1_doc_entry, nc1_item_code, nc1_quantity, nc1_uom, nc1_whscode, nc1_price, nc1_vat, nc1_vat_sum,
                                    nc1_discount, nc1_line_total, nc1_cost_code, nc1_ubusiness, nc1_project, nc1_acct_code, nc1_base_type, nc1_doc_type,
                                    nc1_avprice, nc1_inventory, nc1_item_name)VALUES(:nc1_doc_entry,:nc1_item_code,:nc1_quantity,:nc1_uom,:nc1_whscode,:nc1_price,:nc1_vat,
                                    :nc1_vat_sum,:nc1_discount,:nc1_line_total,:nc1_cost_code,:nc1_ubusiness,:nc1_project,:nc1_acct_code,:nc1_base_type,
                                    :nc1_doc_type,:nc1_avprice,:nc1_inventory,nc1_item_name)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':nc1_doc_entry' => $resInsert,
                        ':nc1_item_code' => $Data['nc1_item_code'],
                        ':nc1_quantity' => $Data['nc1_quantity'],
                        ':nc1_uom' => $Data['nc1_uom'],
                        ':nc1_whscode' => $Data['nc1_whscode'],
                        ':nc1_price' => $Data['nc1_price'],
                        ':nc1_vat' => $Data['nc1_vat'],
                        ':nc1_vat_sum' => $Data['nc1_vat_sum'],
                        ':nc1_discount' => $Data['nc1_discount'],
                        ':nc1_line_total' => $Data['nc1_line_total'],
                        ':nc1_cost_code' => $Data['nc1_cost_code'],
                        ':nc1_ubusiness' => $Data['nc1_ubusiness'],
                        ':nc1_project' => $Data['nc1_project'],
                        ':nc1_acct_code' => $Data['nc1_acct_code'],
                        ':nc1_base_type' => $Data['nc1_base_type'],
                        ':nc1_doc_type' => $Data['nc1_doc_type'],
                        ':nc1_avprice' => $Data['nc1_avprice'],
                        ':nc1_inventory' => $Data['nc1_inventory'],
                        ':nc1_item_name' => $Data['nc1_item_name']
                ));
  						}

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Nota de credito actualizada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la nota de credito'
            );

      }

       $this->response($respuesta);
  }


  //OBTENER NOTAS DE CREDITOS
  public function getCreditNoteSale_get(){

        $sqlSelect = " SELECT * FROM dvnc";

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


	//OBTENER NOTAS DE CREDITOS POR ID
	public function getCreditNoteSaleById_get(){

				$Data = $this->get();

				if(!isset($Data['vnc_doc_entry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM dvnc WHERE vnc_doc_entry =:vnc_doc_entry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":vnc_doc_entry" => $Data['vnc_doc_entry']));

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


	//OBTENER DETALLE NOTAS DE CREDITOS POR ID
	public function getCreditNoteSaleDetail_get(){

				$Data = $this->get();

				if(!isset($Data['nc1_doc_entry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM vnc1 WHERE nc1_doc_entry =:nc1_doc_entry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":nc1_doc_entry" => $Data['nc1_doc_entry']));

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
