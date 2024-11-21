<?php
// NOTAS DE DEBITO DE VENTAS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class SaleDebitNote extends REST_Controller {

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

  //CREAR NUEVA NOTA DE DEBITO
	public function createSaleDebitNote_post(){

      $Data = $this->post();

      if(!isset($Data['vnd_doc_num']) OR !isset($Data['vnd_doc_date']) OR
         !isset($Data['vnd_doc_duedate']) OR !isset($Data['vnd_doc_duedev']) OR
         !isset($Data['vnd_price_list']) OR !isset($Data['vnd_card_code']) OR
         !isset($Data['vnd_card_name']) OR !isset($Data['vnd_currency']) OR
         !isset($Data['vnd_contacid']) OR !isset($Data['vnd_slp_code']) OR
         !isset($Data['vnd_empid']) OR !isset($Data['vnd_comment']) OR
         !isset($Data['vnd_doc_total']) OR !isset($Data['vnd_base_amnt']) OR
         !isset($Data['vnd_tax_total']) OR !isset($Data['vnd_disc_profit']) OR
         !isset($Data['vnd_discount']) OR !isset($Data['vnd_createat']) OR
         !isset($Data['vnd_base_entry']) OR !isset($Data['vnd_base_type']) OR
         !isset($Data['vnd_id_add']) OR !isset($Data['vnd_adress']) OR
         !isset($Data['vnd_pay_type']) OR !isset($Data['vnd_attch']) OR
         !isset($Data['detail']) OR !isset($Data['vnd_doc_type'])){

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

        $sqlInsert = "INSERT INTO dvnd(vnd_doc_num, vnd_doc_date, vnd_doc_duedate, vnd_doc_duedev, vnd_price_list, vnd_card_code, vnd_card_name,
                      vnd_currency, vnd_contacid, vnd_slp_code, vnd_empid, vnd_comment, vnd_doc_total, vnd_base_amnt, vnd_tax_total, vnd_disc_profit,
                      vnd_discount, vnd_createat, vnd_base_entry, vnd_base_type, vnd_doc_type, vnd_id_add, vnd_adress, vnd_pay_type, vnd_attch,
                      business,branch)
	                    VALUES (:vnd_doc_num,:vnd_doc_date,:vnd_doc_duedate,:vnd_doc_duedev,:vnd_price_list,:vnd_card_code,:vnd_card_name,:vnd_currency,
                      :vnd_contacid,:vnd_slp_code,:vnd_empid,:vnd_comment,:vnd_doc_total,:vnd_base_amnt,:vnd_tax_total,:vnd_disc_profit,:vnd_discount,
                      :vnd_createat,:vnd_base_entry,:vnd_base_type,:vnd_doc_type,:vnd_id_add,:vnd_adress,:vnd_pay_type,:vnd_attch,:business,:branch)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':vnd_doc_num' => $Data['vnd_doc_num'],
              ':vnd_doc_date' => $Data['vnd_doc_date'],
              ':vnd_doc_duedate' => $Data['vnd_doc_duedate'],
              ':vnd_doc_duedev' => $Data['vnd_doc_duedev'],
              ':vnd_price_list' => $Data['vnd_price_list'],
              ':vnd_card_code' => $Data['vnd_card_code'],
              ':vnd_card_name' => $Data['vnd_card_name'],
              ':vnd_currency' => $Data['vnd_currency'],
              ':vnd_contacid' => $Data['vnd_contacid'],
              ':vnd_slp_code' => $Data['vnd_slp_code'],
              ':vnd_empid' => $Data['vnd_empid'],
              ':vnd_comment' => $Data['vnd_comment'],
              ':vnd_doc_total' => $Data['vnd_doc_total'],
              ':vnd_base_amnt' => $Data['vnd_base_amnt'],
              ':vnd_tax_total' => $Data['vnd_tax_total'],
              ':vnd_disc_profit' => $Data['vnd_disc_profit'],
              ':vnd_discount' => $Data['vnd_discount'],
              ':vnd_createat' => $Data['vnd_createat'],
              ':vnd_base_entry' => $Data['vnd_base_entry'],
              ':vnd_base_type' => $Data['vnd_base_type'],
              ':vnd_doc_type' => $Data['vnd_doc_type'],
              ':vnd_id_add' => $Data['vnd_id_add'],
              ':vnd_adress' => $Data['vnd_adress'],
              ':vnd_pay_type' => $Data['vnd_pay_type'],
              ':vnd_attch' => $this->getUrl($Data['vnd_attch']),
              ':business' => $Data['business'],
              ':branch' => $Data['branch']
              

        ));

        if(is_numeric($resInsert) && $resInsert > 0){


          foreach ($ContenidoDetalle as $key => $detail) {



                $sqlInsertDetail = "INSERT INTO vnd1(nd1_doc_entry, nd1_item_code, nd1_quantity, nd1_uom, nd1_whscode, nd1_price, nd1_vat, nd1_vat_sum,
                                    nd1_discount, nd1_line_total, nd1_cost_code, nd1_ubusiness, nd1_project, nd1_acct_code, nd1_base_type, nd1_doc_type,
                                    nd1_avprice, nd1_inventory, nd1_item_name,nd1_ubication, nd1_codmunicipality)VALUES(:nd1_doc_entry,:nd1_item_code,:nd1_quantity,:nd1_uom,:nd1_whscode,:nd1_price,:nd1_vat,
                                    :nd1_vat_sum,:nd1_discount,:nd1_line_total,:nd1_cost_code,:nd1_ubusiness,:nd1_project,:nd1_acct_code,:nd1_base_type,
                                    :nd1_doc_type,:nd1_avprice,:nd1_inventory,nd1_item_name,:nd1_ubication, :nd1_codmunicipality)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':nd1_doc_entry' => $resInsert,
                        ':nd1_item_code' => $Data['nd1_item_code'],
                        ':nd1_quantity' => $Data['nd1_quantity'],
                        ':nd1_uom' => $Data['nd1_uom'],
                        ':nd1_whscode' => $Data['nd1_whscode'],
                        ':nd1_price' => $Data['nd1_price'],
                        ':nd1_vat' => $Data['nd1_vat'],
                        ':nd1_vat_sum' => $Data['nd1_vat_sum'],
                        ':nd1_discount' => $Data['nd1_discount'],
                        ':nd1_line_total' => $Data['nd1_line_total'],
                        ':nd1_cost_code' => $Data['nd1_cost_code'],
                        ':nd1_ubusiness' => $Data['nd1_ubusiness'],
                        ':nd1_project' => $Data['nd1_project'],
                        ':nd1_acct_code' => $Data['nd1_acct_code'],
                        ':nd1_base_type' => $Data['nd1_base_type'],
                        ':nd1_doc_type' => $Data['nd1_doc_type'],
                        ':nd1_avprice' => $Data['nd1_avprice'],
                        ':nd1_inventory' => $Data['nd1_inventory'],
                        ':nd1_item_name' => $Data['nd1_item_name'],
                        ':nd1_ubication' => isset($Data['nd1_ubication']) ? $Data['nd1_ubication'] : NULL,
                        ':nd1_codmunicipality' => isset($detail['fc1_codmunicipality']) ? $detail['fc1_codmunicipality'] : NULL
                ));
          }

          $respuesta = array(
            'error' 	=> false,
            'data' 		=> $resInsert,
            'mensaje' =>'Nota de credito registrada con exito'
          );


        }else{

              $respuesta = array(
                'error'   => true,
                'data' 		=> $resInsert,
                'mensaje'	=> 'No se pudo registrar la nota de credito'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR FACTURA DE VENTA
  public function updateSaleDebitNote_post(){

      $Data = $this->post();

      if(!isset($Data['vnd_doc_num']) OR !isset($Data['vnd_doc_date']) OR
         !isset($Data['vnd_doc_duedate']) OR !isset($Data['vnd_doc_duedev']) OR
         !isset($Data['vnd_price_list']) OR !isset($Data['vnd_card_code']) OR
         !isset($Data['vnd_card_name']) OR !isset($Data['vnd_currency']) OR
         !isset($Data['vnd_contacid']) OR !isset($Data['vnd_slp_code']) OR
         !isset($Data['vnd_empid']) OR !isset($Data['vnd_comment']) OR
         !isset($Data['vnd_doc_total']) OR !isset($Data['vnd_base_amnt']) OR
         !isset($Data['vnd_tax_total']) OR !isset($Data['vnd_disc_profit']) OR
         !isset($Data['vnd_discount']) OR !isset($Data['vnd_createat']) OR
         !isset($Data['vnd_base_entry']) OR !isset($Data['vnd_base_type']) OR
         !isset($Data['vnd_id_add']) OR !isset($Data['vnd_adress']) OR
         !isset($Data['vnd_pay_type']) OR !isset($Data['vnd_attch']) OR
         !isset($Data['detail']) OR !isset($Data['vnd_docentry'])){

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

      $sqlUpdate = "UPDATE dvnd SET  vnd_doc_num = :vnd_doc_num, vnd_doc_date =:vnd_doc_date, vnd_doc_duedate =:vnd_doc_duedate, vnd_doc_duedev =:vnd_doc_duedev,
                    vnd_price_list =:vnd_price_list, vnd_card_code =:vnd_card_code, vnd_card_name =:vnd_card_name, vnd_currency =:vnd_currency,
                    vnd_contacid =:vnd_contacid, vnd_slp_code =:vnd_slp_code, vnd_empid =:vnd_empid, vnd_comment =:vnd_comment, vnd_doc_total =:vnd_doc_total,
                    vnd_base_amnt =:vnd_base_amnt, vnd_tax_total =:vnd_tax_total, vnd_disc_profit =:vnd_disc_profit, vnd_discount =:vnd_discount, vnd_createat =:vnd_createat,
                    vnd_base_entry =:vnd_base_entry, vnd_base_type =:vnd_base_type, vnd_doc_type =:vnd_doc_type, vnd_id_add =:vnd_id_add, vnd_adress =:vnd_adress,
                    vnd_pay_type =:vnd_pay_type, vnd_attch =:vnd_attch,business = :business,branch = branch,vnd_ubication = :vnd_ubication
	                  WHERE vnd_doc_entry = :vnd_doc_entry ";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

              ':vnd_doc_num' => $Data['vnd_doc_num'],
              ':vnd_doc_date' => $Data['vnd_doc_date'],
              ':vnd_doc_duedate' => $Data['vnd_doc_duedate'],
              ':vnd_doc_duedev' => $Data['vnd_doc_duedev'],
              ':vnd_price_list' => $Data['vnd_price_list'],
              ':vnd_card_code' => $Data['vnd_card_code'],
              ':vnd_card_name' => $Data['vnd_card_name'],
              ':vnd_currency' => $Data['vnd_currency'],
              ':vnd_contacid' => $Data['vnd_contacid'],
              ':vnd_slp_code' => $Data['vnd_slp_code'],
              ':vnd_empid' => $Data['vnd_empid'],
              ':vnd_comment' => $Data['vnd_comment'],
              ':vnd_doc_total' => $Data['vnd_doc_total'],
              ':vnd_base_amnt' => $Data['vnd_base_amnt'],
              ':vnd_tax_total' => $Data['vnd_tax_total'],
              ':vnd_disc_profit' => $Data['vnd_disc_profit'],
              ':vnd_discount' => $Data['vnd_discount'],
              ':vnd_createat' => $Data['vnd_createat'],
              ':vnd_base_entry' => $Data['vnd_base_entry'],
              ':vnd_base_type' => $Data['vnd_base_type'],
              ':vnd_doc_type' => $Data['vnd_doc_type'],
              ':vnd_id_add' => $Data['vnd_id_add'],
              ':vnd_adress' => $Data['vnd_adress'],
              ':vnd_pay_type' => $Data['vnd_pay_type'],
              ':vnd_attch' => $this->getUrl($Data['vnd_attch']),
							':vnd_doc_entry' => $Data['vnd_doc_entry'],
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

						$this->pedeo->queryTable("DELETE FROM vnd1 WHERE nd1_doc_entry=:nd1_doc_entry", array(':nd1_doc_entry' => $Data['vnd_docentry']));

						foreach ($ContenidoDetalle as $key => $detail) {

                $sqlInsertDetail = "INSERT INTO vnd1(nd1_doc_entry, nd1_item_code, nd1_quantity, nd1_uom, nd1_whscode, nd1_price, nd1_vat, nd1_vat_sum,
                                    nd1_discount, nd1_line_total, nd1_cost_code, nd1_ubusiness, nd1_project, nd1_acct_code, nd1_base_type, nd1_doc_type,
                                    nd1_avprice, nd1_inventory, nd1_item_name)VALUES(:nd1_doc_entry,:nd1_item_code,:nd1_quantity,:nd1_uom,:nd1_whscode,:nd1_price,:nd1_vat,
                                    :nd1_vat_sum,:nd1_discount,:nd1_line_total,:nd1_cost_code,:nd1_ubusiness,:nd1_project,:nd1_acct_code,:nd1_base_type,
                                    :nd1_doc_type,:nd1_avprice,:nd1_inventory,nd1_item_name)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':nd1_doc_entry' => $resInsert,
                        ':nd1_item_code' => $Data['nd1_item_code'],
                        ':nd1_quantity' => $Data['nd1_quantity'],
                        ':nd1_uom' => $Data['nd1_uom'],
                        ':nd1_whscode' => $Data['nd1_whscode'],
                        ':nd1_price' => $Data['nd1_price'],
                        ':nd1_vat' => $Data['nd1_vat'],
                        ':nd1_vat_sum' => $Data['nd1_vat_sum'],
                        ':nd1_discount' => $Data['nd1_discount'],
                        ':nd1_line_total' => $Data['nd1_line_total'],
                        ':nd1_cost_code' => $Data['nd1_cost_code'],
                        ':nd1_ubusiness' => $Data['nd1_ubusiness'],
                        ':nd1_project' => $Data['nd1_project'],
                        ':nd1_acct_code' => $Data['nd1_acct_code'],
                        ':nd1_base_type' => $Data['nd1_base_type'],
                        ':nd1_doc_type' => $Data['nd1_doc_type'],
                        ':nd1_avprice' => $Data['nd1_avprice'],
                        ':nd1_inventory' => $Data['nd1_inventory'],
                        ':nd1_item_name' => $Data['nd1_item_name'],
                        ':nd1_codmunicipality' => isset($detail['nd1_codmunicipality']) ? $detail['nd1_codmunicipality'] : NULL
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
  public function getSaleDebitNote_get(){

        $sqlSelect = " SELECT * FROM dvnd";

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
	public function getSaleDebitNoteById_get(){

				$Data = $this->get();

				if(!isset($Data['vnd_doc_entry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM dvnd WHERE vnd_doc_entry =:vnd_doc_entry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":vnd_doc_entry" => $Data['vnd_doc_entry']));

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
	public function getSaleDebitNoteDetail_get(){

				$Data = $this->get();

				if(!isset($Data['nd1_doc_entry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM vnd1 WHERE nd1_doc_entry =:nd1_doc_entry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":nd1_doc_entry" => $Data['nd1_doc_entry']));

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
