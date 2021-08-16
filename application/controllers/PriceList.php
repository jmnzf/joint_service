<?php
// DATOS LISTA DE PRECIOS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PriceList extends REST_Controller {

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

  //CREAR NUEVA LISTA DE PRECIO
	public function createPriceList_post(){

      $Data = $this->post();

      if(!isset($Data['dmlp_name_list']) OR
         !isset($Data['dmlp_profit']) OR
         !isset($Data['dmlp_baselist'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

        $sqlProductos = "SELECT dma_item_code, dma_item_name, dma_avprice FROM dmar WHERE dma_enabled = :dma_enabled";

        $resProductos = $this->pedeo->queryTable($sqlProductos, array(":dma_enabled" => 1));

        if(!isset($resProductos[0])){

            $respuesta = array(
              'error' => true,
              'data'  => array(),
              'mensaje' =>'No hay productos para asignar a la lista'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $sqlInsert = "INSERT INTO dmpl(dmlp_name_list, dmlp_profit, dmlp_baselist)
                      VALUES(:dmlp_name_list, :dmlp_profit, :dmlp_baselist)";

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(

              ':dmlp_name_list' => $Data['dmlp_name_list'],
              ':dmlp_profit'    => $Data['dmlp_profit'],
              ':dmlp_baselist'  => $Data['dmlp_baselist']
        ));

        if(is_numeric($resInsert) && $resInsert > 0){

              $sqlDetail = "INSERT INTO mpl1(pl1_id_price_list, pl1_item_code, pl1_item_name, pl1_profit, pl1_price)
                            VALUES(:pl1_id_price_list, :pl1_item_code, :pl1_item_name, :pl1_profit, :pl1_price)";

              $BaseList = $Data['dmlp_baselist'];
              $Precio = 0;

              foreach ($resProductos as $key => $prod) {
                $Precio = 0;

                if($BaseList == "0" || $BaseList == 0){

                      $valor = $prod['dma_avprice'];
                      $valorProfit = $Data['dmlp_profit'];
                      $porcent = ($valorProfit / 100);
                      $subtt = ($valor * $porcent);

                      $Precio = ($subtt + $valor);

                }else{

                      $res = $this->getPrecio($prod['dma_item_code'], $BaseList);
                      if(isset($res[0]['pl1_price'])){
                          $valor = $res[0]['pl1_price'];
                          $valorProfit = $Data['dmlp_profit'];
                          $porcent = ($valorProfit / 100);
                          $subtt = ($valor * $porcent);

                          $Precio = ($subtt + $valor);

                      }else{

                          $Precio = 0;

                      }
                }

                $this->pedeo->insertRow($sqlDetail, array(

                      ':pl1_id_price_list' => $resInsert,
                      ':pl1_item_code' => $prod['dma_item_code'],
                      ':pl1_item_name' => $prod['dma_item_name'],
                      ':pl1_profit' => $Data['dmlp_profit'],
                      ':pl1_price' => $Precio
                ));


              }

              $respuesta = array(
                'error' => false,
                'data' => $resInsert,
                'mensaje' =>'Lista registrada con exito'
              );


        }else{

              $respuesta = array(
                'error'   => true,
                'data' 		=> $resInsert,
                'mensaje'	=> 'No se pudo registrar la lista'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR LISTA DE PRECIOS
  public function updatePriceList_post(){

      $Data = $this->post();

			if(!isset($Data['dmlp_name_list']) OR
         !isset($Data['dmlp_profit']) OR
         !isset($Data['dmlp_baselist']) OR
			 	 !isset($Data['dmlp_id'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

			$sqlProductos = "SELECT dma_item_code, dma_item_name, dma_avprice FROM dmar WHERE dma_enabled = :dma_enabled";

			$resProductos = $this->pedeo->queryTable($sqlProductos, array(":dma_enabled" => 1));

			if(!isset($resProductos[0])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'No hay productos para asignar a la lista'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
			}

      $sqlUpdate = "UPDATE dmpl SET dmlp_name_list = :dmlp_name_list, dmlp_profit = :dmlp_profit, dmlp_baselist = :dmlp_baselist  WHERE dmlp_id = :dmlp_id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

              ':dmlp_name_list' => $Data['dmlp_name_list'],
              ':dmlp_profit' 		=> $Data['dmlp_profit'],
              ':dmlp_baselist'  => $Data['dmlp_baselist'],
							':dmlp_id'   			=> $Data['dmlp_id']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

						$sqlDetail = "INSERT INTO mpl1(pl1_id_price_list, pl1_item_code, pl1_item_name, pl1_profit, pl1_price)
													VALUES(:pl1_id_price_list, :pl1_item_code, :pl1_item_name, :pl1_profit, :pl1_price)";

						$this->pedeo->queryTable("DELETE FROM mpl1 WHERE pl1_id_price_list = :pl1_id_price_list",array(':pl1_id_price_list' => $Data['dmlp_id']));

						$BaseList = $Data['dmlp_baselist'];
						$Precio = 0;

						foreach ($resProductos as $key => $prod) {
							$Precio = 0;

							if($BaseList == "0" || $BaseList == 0){

										$valor = $prod['dma_avprice'];
										$valorProfit = $Data['dmlp_profit'];
										$porcent = ($valorProfit / 100);
										$subtt = ($valor * $porcent);

										$Precio = ($subtt + $valor);

							}else{

										$res = $this->getPrecio($prod['dma_item_code'], $BaseList);
										if(isset($res[0]['pl1_price'])){
												$valor = $res[0]['pl1_price'];
												$valorProfit = $Data['dmlp_profit'];
												$porcent = ($valorProfit / 100);
												$subtt = ($valor * $porcent);

												$Precio = ($subtt + $valor);

										}else{

												$Precio = 0;

										}
							}

							$this->pedeo->insertRow($sqlDetail, array(

										':pl1_id_price_list' => $Data['dmlp_id'],
										':pl1_item_code' => $prod['dma_item_code'],
										':pl1_item_name' => $prod['dma_item_name'],
										':pl1_profit' => $Data['dmlp_profit'],
										':pl1_price' => $Precio
							));


						}

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Lista actualizada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la lista'
            );

      }

       $this->response($respuesta);
  }


  // OBTENER LISTA DE PRECIOS
  public function getPriceList_get(){

        $sqlSelect = " SELECT * FROM dmpl";

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

  // OBTENER LISTA DE PRECIOS POR ID
  public function getPriceListById_get(){

        $Data = $this->get();

        if(!isset($Data['dmlp_id'])){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = " SELECT * FROM dmpl WHERE dmlp_id = :dmlp_id";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':dmlp_id' => $Data['dmlp_id']));

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

  // OBTENER PRECIO PRODUCTO PUBLICO
  public function getPrecioByListEItem_post(){

      $Data = $this->post();

      if(!isset($Data['pl1_id_price_list']) OR
         !isset($Data['pl1_item_code'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlSelect = "SELECT pl1_price FROM mpl1 WHERE pl1_id_price_list = :pl1_id_price_list AND pl1_item_code = :pl1_item_code";

      $resSelect = $this->pedeo->queryTable($sqlSelect, array(

                  ':pl1_id_price_list' => $Data['pl1_id_price_list'],
                  ':pl1_item_code' => $Data['pl1_item_code']
      ));

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




	// OBTENER PRECIO DE PRODUCTOS POR LISTA
	public function getPrecioByList_get(){

		  $Data = $this->get();

			if(!isset($Data['pl1_id_price_list'])){

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' =>'La informacion enviada no es valida'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}

			$sqlSelect = "SELECT * FROM mpl1 WHERE pl1_id_price_list = :pl1_id_price_list";

			$resSelect = $this->pedeo->queryTable($sqlSelect, array(

									':pl1_id_price_list' => $Data['pl1_id_price_list']
			));


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



// METODOS PRIVADOS



  // OBTENER PRECIO PRODUCTO
  private function getPrecio($itemCode,$idPriceList){

      $sqlSelect = "SELECT pl1_price FROM mpl1 WHERE pl1_id_price_list = :pl1_id_price_list AND pl1_item_code = :pl1_item_code";

      $resSelect = $this->pedeo->queryTable($sqlSelect, array(

                  ':pl1_id_price_list' => $idPriceList,
                  ':pl1_item_code' => $itemCode
      ));

      return $resSelect;
  }




}
