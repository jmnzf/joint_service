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
         !isset($Data['dmlp_profit'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

				$sqlProductos = "SELECT tbdi.bdi_itemcode, tbdi.bdi_avgprice, dmar.dma_item_name FROM tbdi
													INNER JOIN dmar
													ON tbdi.bdi_itemcode = dmar.dma_item_code
													WHERE dmar.dma_enabled = :dma_enabled
													AND dma_item_sales = :dma_item_sales
													GROUP BY tbdi.bdi_itemcode,tbdi.bdi_avgprice, dmar.dma_item_name";


        $resProductos = $this->pedeo->queryTable($sqlProductos, array(":dma_enabled" => 1,':dma_item_sales' => '1'));

        if(!isset($resProductos[0])){

            $respuesta = array(
              'error' => true,
              'data'  => array(),
              'mensaje' =>'No hay productos para asignar a la lista'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }


				// Se Inicia la transaccion,
				// Todas las consultas de modificacion siguientes
				// aplicaran solo despues que se confirme la transaccion,
				// de lo contrario no se aplicaran los cambios y se devolvera
				// la base de datos a su estado original.
				$this->pedeo->trans_begin();

				try {

									$sqlInsert = "INSERT INTO dmpl(dmlp_name_list, dmlp_profit, dmlp_baselist)
																VALUES(:dmlp_name_list, :dmlp_profit, :dmlp_baselist)";

									$resInsert = $this->pedeo->insertRow($sqlInsert, array(

												':dmlp_name_list' => $Data['dmlp_name_list'],
												':dmlp_profit'    => $Data['dmlp_profit'],
												':dmlp_baselist'  => isset($Data['dmlp_baselist'])?$Data['dmlp_baselist']:0
									));

									if(is_numeric($resInsert) && $resInsert > 0){

												$sqlDetail = "INSERT INTO mpl1(pl1_id_price_list, pl1_item_code, pl1_item_name, pl1_profit, pl1_price)
																			VALUES(:pl1_id_price_list, :pl1_item_code, :pl1_item_name, :pl1_profit, :pl1_price)";

												$BaseList = isset($Data['dmlp_baselist'])?$Data['dmlp_baselist']:0;
												$Precio = 0;

												foreach ($resProductos as $key => $prod) {
													$Precio = 0;

													if($BaseList == "0" || $BaseList == 0){

																$valor = $prod['bdi_avgprice'];
																$porcent = 0;
																$subtt = 0;

																$valorProfit = isset($Data['dmlp_profit'])?$Data['dmlp_profit']:0;

																if($valorProfit > 0){
																		$porcent = ($valorProfit / 100);
																		$subtt = ($valor * $porcent);
																}

																$Precio = ($subtt + $valor);

													}else{

																$res = $this->getPrecio($prod['bdi_itemcode'], $BaseList);
																if(isset($res[0]['pl1_price'])){
																		$valor = $res[0]['pl1_price'];
																		$porcent = 0;
																		$subtt = 0;
																		$valorProfit = isset($Data['dmlp_profit'])?$Data['dmlp_profit']:0;

																		if($valorProfit > 0){
																			$porcent = ($valorProfit / 100);
																			$subtt = ($valor * $porcent);
																		}

																		$Precio = ($subtt + $valor);

																}else{

																		$Precio = 0;

																}
													}

													$resInsertDetail = $this->pedeo->insertRow($sqlDetail, array(

																':pl1_id_price_list' => $resInsert,
																':pl1_item_code' => $prod['bdi_itemcode'],
																':pl1_item_name' => $prod['dma_item_name'],
																':pl1_profit' => $Data['dmlp_profit'],
																':pl1_price' => $Precio
													));


													if(is_numeric($resInsertDetail) && $resInsertDetail > 0){

													}else{

														$this->pedeo->trans_rollback();

														$respuesta = array(
															'error'   => true,
															'data' 		=> $resInsertDetail,
															'mensaje'	=> 'No se pudo registrar la lista'
														);

														$this->response($respuesta);

														return;
													}


												}

												// Si todo sale bien despues de insertar el detalle de la cotizacion
												// se confirma la trasaccion  para que los cambios apliquen permanentemente
												// en la base de datos y se confirma la operacion exitosa.
												$this->pedeo->trans_commit();

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
									
				} catch (\Exception $e) {

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' 		=> $e,
								'mensaje'	=> 'No se pudo registrar la lista'
							);

							$this->response($respuesta);

							return;
				}



         $this->response($respuesta);
	}

  //ACTUALIZAR LISTA DE PRECIOS
  public function updatePriceList_post(){

      $Data = $this->post();

			if(!isset($Data['dmlp_name_list']) OR
         !isset($Data['dmlp_profit']) OR
			 	 !isset($Data['dmlp_id'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

			$sqlProductos = "SELECT tbdi.bdi_itemcode, tbdi.bdi_avgprice, dmar.dma_item_name FROM tbdi
												INNER JOIN dmar
												ON tbdi.bdi_itemcode = dmar.dma_item_code
												WHERE dmar.dma_enabled = :dma_enabled
												AND dma_item_sales = :dma_item_sales
												GROUP BY tbdi.bdi_itemcode,tbdi.bdi_avgprice, dmar.dma_item_name";


			$resProductos = $this->pedeo->queryTable($sqlProductos, array(":dma_enabled" => 1,':dma_item_sales' => '1'));

			if(!isset($resProductos[0])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'No hay productos para asignar a la lista'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
			}

			// Se Inicia la transaccion,
			// Todas las consultas de modificacion siguientes
			// aplicaran solo despues que se confirme la transaccion,
			// de lo contrario no se aplicaran los cambios y se devolvera
			// la base de datos a su estado original.
			$this->pedeo->trans_begin();
			try {

							$sqlUpdate = "UPDATE dmpl SET dmlp_name_list = :dmlp_name_list, dmlp_profit = :dmlp_profit, dmlp_baselist = :dmlp_baselist  WHERE dmlp_id = :dmlp_id";


							$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

											':dmlp_name_list' => $Data['dmlp_name_list'],
											':dmlp_profit' 		=> $Data['dmlp_profit'],
											':dmlp_baselist'  => isset($Data['dmlp_baselist'])?$Data['dmlp_baselist']:0,
											':dmlp_id'   			=> $Data['dmlp_id']
							));

							if(is_numeric($resUpdate) && $resUpdate == 1){

										$sqlDetail = "INSERT INTO mpl1(pl1_id_price_list, pl1_item_code, pl1_item_name, pl1_profit, pl1_price)
																	VALUES(:pl1_id_price_list, :pl1_item_code, :pl1_item_name, :pl1_profit, :pl1_price)";

										$this->pedeo->queryTable("DELETE FROM mpl1 WHERE pl1_id_price_list = :pl1_id_price_list",array(':pl1_id_price_list' => $Data['dmlp_id']));

										$BaseList = isset($Data['dmlp_baselist'])?$Data['dmlp_baselist']:0;
										$Precio = 0;

										foreach ($resProductos as $key => $prod) {
											$Precio = 0;

											if($BaseList == "0" || $BaseList == 0){

														$valor = $prod['bdi_avgprice'];
														$porcent = 0;
														$subtt = 0;

														$valorProfit = isset($Data['dmlp_profit'])?$Data['dmlp_profit']:0;

														if($valorProfit > 0){
																$porcent = ($valorProfit / 100);
																$subtt = ($valor * $porcent);
														}

														$Precio = ($subtt + $valor);

											}else{

														$res = $this->getPrecio($prod['bdi_itemcode'], $BaseList);
														if(isset($res[0]['pl1_price'])){
																$valor = $res[0]['pl1_price'];
																$porcent = 0;
																$subtt = 0;

																$valorProfit = isset($Data['dmlp_profit'])?$Data['dmlp_profit']:0;

																if($valorProfit > 0){
																		$porcent = ($valorProfit / 100);
																		$subtt = ($valor * $porcent);
																}


																$Precio = ($subtt + $valor);

														}else{

																$Precio = 0;

														}
											}

											$resInsertDetail = $this->pedeo->insertRow($sqlDetail, array(

														':pl1_id_price_list' => $Data['dmlp_id'],
														':pl1_item_code' => $prod['bdi_itemcode'],
														':pl1_item_name' => $prod['dma_item_name'],
														':pl1_profit' => $Data['dmlp_profit'],
														':pl1_price' => $Precio
											));

											if(is_numeric($resInsertDetail) && $resInsertDetail > 0){

											}else{

												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data' 		=> $resInsertDetail,
													'mensaje'	=> 'No se pudo actualizar la lista'
												);

												$this->response($respuesta);

												return;
											}

										}

										// Si todo sale bien despues de insertar el detalle de la cotizacion
										// se confirma la trasaccion  para que los cambios apliquen permanentemente
										// en la base de datos y se confirma la operacion exitosa.
										$this->pedeo->trans_commit();

										$respuesta = array(
											'error' => false,
											'data' => $resUpdate,
											'mensaje' =>'Lista actualizada con exito'
										);

							}else{

										$this->pedeo->trans_rollback();

										$respuesta = array(
											'error'   => true,
											'data'    => $resUpdate,
											'mensaje'	=> 'No se pudo actualizar la lista'
										);

										$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

										return;
							}
			} catch (\Exception $e) {
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'    => $e,
							'mensaje'	=> 'No se pudo actualizar la lista'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
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


	public function getListaPrecioDetalle_get(){

			$Data = $this->get();

			if(!isset($Data['dmlp_id'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'falto el codigo de la lista'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
			}


			$sqlSelect = "SELECT DISTINCT dmlp_id, dmlp_name_list,
										dma_item_code, dma_item_name,
										pl1_price,pl1_id
										FROM dmpl
										INNER JOIN mpl1
										ON dmpl.dmlp_id = mpl1.pl1_id_price_list
										INNER JOIN dmar
										ON trim(mpl1.pl1_item_code) =  trim(dma_item_code)
										WHERE dmlp_id = :dmlp_id";

			$resSelect = $this->pedeo->queryTable($sqlSelect, array(

									':dmlp_id' => $Data['dmlp_id']
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

	public function setChangePrice_post(){

				$Data = $this->post();

				if(!isset($Data['detail'])){

						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'La informacion enviada no es valida'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}

				$ContenidoDetalle = json_decode($Data['detail'], true);

				// SE VALIDA QUE EL DOCUMENTO SEA UN ARRAY
	      if(!is_array($ContenidoDetalle)){
	          $respuesta = array(
	            'error' => true,
	            'data'  => array(),
	            'mensaje' =>'No se encontro el detalle de la Devolución de clientes'
	          );

	          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	          return;
	      }
				//


				// SE VALIDA QUE EL DOCUMENTO TENGA CONTENIDO
				if(!intval(count($ContenidoDetalle)) > 0 ){
						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'Documento sin detalle'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}
				//

				try {
						$this->pedeo->trans_begin();

						$sqlUpdate = "UPDATE mpl1	SET pl1_price = :pl1_price
													WHERE pl1_id = :pl1_id";

						foreach ($ContenidoDetalle as $key => $value) {


								$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

											':pl1_price' => is_numeric($value['pl1_price'])?$value['pl1_price']:'',
											':pl1_id' 	 => is_numeric($value['pl1_id'])?$value['pl1_id']:''
								));


								if(is_numeric($resUpdate) && $resUpdate == 1){

								}else{

											$this->pedeo->trans_rollback();

											$respuesta = array(
												'error'   => true,
												'data' 		=> $resUpdate,
												'mensaje'	=> 'no se pudo actualizar el precio de la lista '.$value['pl1_id']
											);

											 $this->response($respuesta);

											 return;
								}

						}

						$this->pedeo->trans_commit();

						$respuesta = array(
							'error' => false,
							'data' => $resUpdate,
							'mensaje' =>'Lista de precio actualiza con exito'
						);

				} catch (\Exception $e) {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' 		=> $e,
							'mensaje'	=> 'no se pudo actualizar la lista de precios'
						);

						 $this->response($respuesta);

						 return;
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
