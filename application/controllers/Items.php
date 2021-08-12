<?php
// Artículos
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Items extends REST_Controller {

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

   //Crear nuevo articulo
	public function createItems_post(){

      $Data = $this->post();

      if(!isset($Data['dma_item_code']) OR
         !isset($Data['dma_item_name']) OR
         !isset($Data['dma_generic_name']) OR
         !isset($Data['dma_item_purch']) OR
         !isset($Data['dma_item_inv']) OR
         !isset($Data['dma_item_sales']) OR
         !isset($Data['dma_group_code']) OR
         !isset($Data['dma_attach']) OR
         !isset($Data['dma_enabled']) OR
         !isset($Data['dma_firm_code']) OR
         !isset($Data['dma_series_code']) OR
         !isset($Data['dma_sup_set']) OR
         !isset($Data['dma_sku_sup']) OR
         !isset($Data['dma_uom_purch']) OR
         !isset($Data['dma_uom_pqty']) OR
         !isset($Data['dma_uom_pemb']) OR
         !isset($Data['dma_uom_pembqty']) OR
         !isset($Data['dma_tax_purch']) OR
         !isset($Data['dma_price_list']) OR
         !isset($Data['dma_price']) OR
         !isset($Data['dma_uom_sale']) OR
         !isset($Data['dma_uom_sqty']) OR
         !isset($Data['dma_uom_semb']) OR
         !isset($Data['dma_uom_embqty']) OR
         !isset($Data['dma_tax_sales']) OR
         !isset($Data['dma_acct_type']) OR
         !isset($Data['dma_avprice'])){

         $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
         );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }


      $sqlSelect = "SELECT dma_item_code FROM dmar WHERE dma_item_code = :dma_item_code";

      $resSelect = $this->pedeo->queryTable($sqlSelect, array(

          ':dma_item_code' => $Data['dma_item_code']

      ));


      if(isset($resSelect[0])){

          $respuesta = array(
            'error' => true,
            'data'  => array($Data['dms_card_code'], $Data['dms_card_type']),
            'mensaje' => 'ya existe un artículo con es codigo');

          $this->response($respuesta);

          return;

      }

      $sqlInsert = "INSERT INTO dmar(dma_item_code, dma_item_name, dma_generic_name, dma_item_purch, dma_item_inv, dma_item_sales,
                    dma_group_code, dma_attach, dma_enabled, dma_firm_code, dma_series_code, dma_sup_set, dma_sku_sup, dma_uom_purch,
                    dma_uom_pqty, dma_uom_pemb, dma_uom_pembqty, dma_tax_purch, dma_price_list, dma_price, dma_uom_sale,
                    dma_uom_sqty, dma_uom_semb, dma_uom_embqty, dma_tax_sales, dma_acct_type, dma_avprice)VALUES(:dma_item_code,
                    :dma_item_name, :dma_generic_name, :dma_item_purch, :dma_item_inv, :dma_item_sales, :dma_group_code, :dma_attach,
                    :dma_enabled, :dma_firm_code, :dma_series_code, :dma_sup_set, :dma_sku_sup, :dma_uom_purch, :dma_uom_pqty, :dma_uom_pemb,
                    :dma_uom_pembqty, :dma_tax_purch, :dma_price_list, :dma_price, :dma_uom_sale, :dma_uom_sqty, :dma_uom_semb,
                    :dma_uom_embqty, :dma_tax_sales, :dma_acct_type,:dma_avprice)";


      $resInsert = $this->pedeo->insertRow($sqlInsert, array(

            ':dma_item_code' => $Data['dma_item_code'],
            ':dma_item_name' => $Data['dma_item_name'],
            ':dma_generic_name' => $Data['dma_generic_name'],
            ':dma_item_purch' => $Data['dma_item_purch'],
            ':dma_item_inv' => $Data['dma_item_inv'],
            ':dma_item_sales' => $Data['dma_item_sales'],
            ':dma_group_code' => $Data['dma_group_code'],
            ':dma_attach' => $Data['dma_attach'],
            ':dma_enabled' => $Data['dma_enabled'],
            ':dma_firm_code' => $Data['dma_firm_code'],
            ':dma_series_code' => $Data['dma_series_code'],
            ':dma_sup_set' => $Data['dma_sup_set'],
            ':dma_sku_sup' => $Data['dma_sku_sup'],
            ':dma_uom_purch' => $Data['dma_uom_purch'],
            ':dma_uom_pqty' => $Data['dma_uom_pqty'],
            ':dma_uom_pemb' => $Data['dma_uom_pemb'],
            ':dma_uom_pembqty' => $Data['dma_uom_pembqty'],
            ':dma_tax_purch' => $Data['dma_tax_purch'],
            ':dma_price_list' => $Data['dma_price_list'],
            ':dma_price' => $Data['dma_price'],
            ':dma_uom_sale' => $Data['dma_uom_sale'],
            ':dma_uom_sqty' => $Data['dma_uom_sqty'],
            ':dma_uom_semb' => $Data['dma_uom_semb'],
            ':dma_uom_embqty' => $Data['dma_uom_embqty'],
            ':dma_tax_sales' => $Data['dma_tax_sales'],
            ':dma_acct_type' => $Data['dma_acct_type'],
            ':dma_avprice' => $Data['dma_avprice']
      ));


      if($resInsert > 0 ){
         $respuesta = array(
           'error' => false,
           'data' => $resInsert,
           'mensaje' =>'Artículo registrado con exito'
         );
      }else{

        $respuesta = array(
          'error'   => true,
          'data' => array(),
          'mensaje' => 'No se pudo registrar el artículo'
        );

      }

      $this->response($respuesta);
	}

  //Actualizar articulo
  public function updateItems_post(){

      $Data = $this->post();

      if(!isset($Data['dma_item_code']) OR
         !isset($Data['dma_item_name']) OR
         !isset($Data['dma_generic_name']) OR
         !isset($Data['dma_item_purch']) OR
         !isset($Data['dma_item_inv']) OR
         !isset($Data['dma_item_sales']) OR
         !isset($Data['dma_group_code']) OR
         !isset($Data['dma_attach']) OR
         !isset($Data['dma_enabled']) OR
         !isset($Data['dma_firm_code']) OR
         !isset($Data['dma_series_code']) OR
         !isset($Data['dma_sup_set']) OR
         !isset($Data['dma_sku_sup']) OR
         !isset($Data['dma_uom_purch']) OR
         !isset($Data['dma_uom_pqty']) OR
         !isset($Data['dma_uom_pemb']) OR
         !isset($Data['dma_uom_pembqty']) OR
         !isset($Data['dma_tax_purch']) OR
         !isset($Data['dma_price_list']) OR
         !isset($Data['dma_price']) OR
         !isset($Data['dma_uom_sale']) OR
         !isset($Data['dma_uom_sqty']) OR
         !isset($Data['dma_uom_semb']) OR
         !isset($Data['dma_uom_embqty']) OR
         !isset($Data['dma_tax_sales']) OR
         !isset($Data['dma_acct_type']) OR
         !isset($Data['dma_avprice']) OR
         !isset($Data['dma_id'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlSelect = "SELECT dma_item_code FROM dmar WHERE dma_item_code = :dma_item_code
                    AND dma_id != :dma_id";

      $resSelect = $this->pedeo->queryTable($sqlSelect, array(

          ':dma_item_code' => $Data['dma_item_code'],
          ':dma_id'        => $Data['dma_id']

      ));


      if(isset($resSelect[0])){

          $respuesta = array(
            'error' => true,
            'data'  => [],
            'mensaje' => 'ya existe un artículo con es codigo, no es posible actualizar ');

          $this->response($respuesta);

          return;

      }


      $sqlUpdate = "UPDATE dmar
                  	SET dma_item_code = :dma_item_code, dma_item_name = :dma_item_name, dma_generic_name = :dma_generic_name,
                    dma_item_purch = :dma_item_purch, dma_item_inv = :dma_item_inv, dma_item_sales = :dma_item_sales,
                    dma_group_code = :dma_group_code, dma_attach = :dma_attach, dma_enabled = :dma_enabled, dma_firm_code = :dma_firm_code,
                    dma_series_code = :dma_series_code, dma_sup_set = :dma_sup_set, dma_sku_sup = :dma_sku_sup, dma_uom_purch = :dma_uom_purch,
                    dma_uom_pqty = :dma_uom_pqty, dma_uom_pemb = :dma_uom_pemb, dma_uom_pembqty = :dma_uom_pembqty, dma_tax_purch = :dma_tax_purch,
                    dma_price_list = :dma_price_list, dma_price = :dma_price, dma_uom_sale = :dma_uom_sale, dma_uom_sqty = :dma_uom_sqty,
                    dma_uom_semb = :dma_uom_semb, dma_uom_embqty = :dma_uom_embqty, dma_tax_sales = :dma_tax_sales, dma_acct_type = :dma_acct_type,
                    dma_avprice = :dma_avprice WHERE dma_id = :dma_id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

            ':dma_item_code' => $Data['dma_item_code'],
            ':dma_item_name' => $Data['dma_item_name'],
            ':dma_generic_name' => $Data['dma_generic_name'],
            ':dma_item_purch' => $Data['dma_item_purch'],
            ':dma_item_inv' => $Data['dma_item_inv'],
            ':dma_item_sales' => $Data['dma_item_sales'],
            ':dma_group_code' => $Data['dma_group_code'],
            ':dma_attach' => $Data['dma_attach'],
            ':dma_enabled' => $Data['dma_enabled'],
            ':dma_firm_code' => $Data['dma_firm_code'],
            ':dma_series_code' => $Data['dma_series_code'],
            ':dma_sup_set' => $Data['dma_sup_set'],
            ':dma_sku_sup' => $Data['dma_sku_sup'],
            ':dma_uom_purch' => $Data['dma_uom_purch'],
            ':dma_uom_pqty' => $Data['dma_uom_pqty'],
            ':dma_uom_pemb' => $Data['dma_uom_pemb'],
            ':dma_uom_pembqty' => $Data['dma_uom_pembqty'],
            ':dma_tax_purch' => $Data['dma_tax_purch'],
            ':dma_price_list' => $Data['dma_price_list'],
            ':dma_price' => $Data['dma_price'],
            ':dma_uom_sale' => $Data['dma_uom_sale'],
            ':dma_uom_sqty' => $Data['dma_uom_sqty'],
            ':dma_uom_semb' => $Data['dma_uom_semb'],
            ':dma_uom_embqty' => $Data['dma_uom_embqty'],
            ':dma_tax_sales' => $Data['dma_tax_sales'],
            ':dma_acct_type' => $Data['dma_acct_type'],
            ':dma_avprice' => $Data['dma_avprice'],
            ':dma_id' => $Data['dma_id'],
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Artículo actualizado con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data' => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar el artículo'
            );

      }

       $this->response($respuesta);
  }

  // Obtener articulos
  public function getItems_get(){

        $sqlSelect = "SELECT * FROM dmar";

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

//Obtener articulo id del articulo
  public function getItemsById_get(){

        $Data = $this->get();

        if(!isset($Data['dma_id'])){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = " SELECT * FROM dmar WHERE dma_id = :dma_id";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':dma_id' => $Data['dma_id']));

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


	//Inactivar o Activar Articulo
  public function updateItemsStatus_post(){

        $Data = $this->post();

        if(!isset($Data['dma_id']) || !isset($Data['dma_enabled'])){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }


        $sqlUpdate = "UPDATE dmar
                    	SET dma_enabled = :dma_enabled WHERE dma_id = :dma_id";


        $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

              ':dma_enabled' => $Data['dma_enabled'],
              ':dma_id'      => $Data['dma_id'],
        ));

        if(is_numeric($resUpdate) && $resUpdate == 1){

              $respuesta = array(
                'error' => false,
                'data' => $resUpdate,
                'mensaje' =>'Artículo actualizado con exito'
              );


        }else{

              $respuesta = array(
                'error'   => true,
                'data' => $resUpdate,
                'mensaje'	=> 'No se pudo actualizar el artículo'
              );

        }

        $this->response($respuesta);
  }


}
