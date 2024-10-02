<?php
// DATOS LISTA DE PRECIOS
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class PriceList extends REST_Controller
{

	private $pdo;

	public function __construct()
	{

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
		$this->load->library('pedeo', [$this->pdo]);
	}

	//CREAR NUEVA LISTA DE PRECIO
	public function createPriceList_post()
	{

		$Data = $this->post();

		if (
			!isset($Data['dmlp_name_list']) or
			!isset($Data['dmlp_profit']) or
			!isset($Data['business']) or
			!isset($Data['branch'])
		) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}
		// SE BUSCAN LOS PRODUCTOS
		// INVENTARIABLES
		$sqlProductos = " SELECT dma_item_code, dma_price, dmar.dma_item_name FROM dmar
											WHERE dmar.dma_enabled = :dma_enabled
											AND dma_item_sales = :dma_item_sales
											AND dma_item_inv = :dma_item_inv
											GROUP BY dma_item_code, dma_price, dmar.dma_item_name";


		$resProductos = $this->pedeo->queryTable($sqlProductos, array(":dma_enabled" => 1, ':dma_item_sales' => '1', 'dma_item_inv' => '1'));

		// NO INVENTARIABLES
		$sqlProductosNinv = "SELECT DISTINCT dma_item_name, dma_item_code, 0 AS costo, dma_price
							FROM dmar
							WHERE dma_item_sales = :dma_item_sales
							AND dma_enabled = :dma_enabled
							AND dma_item_inv = :dma_item_inv";

		$resProductosNinv = $this->pedeo->queryTable($sqlProductosNinv, array(':dma_item_sales' => '1', ':dma_enabled' => 1, ':dma_item_inv' => '0'));

		if (!isset($resProductos[0]) && !isset($resProductosNinv[0])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No hay productos para asignar a la lista'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}
		//FIN BUSQUEDA PRODUCTOS

		// Se Inicia la transaccion,
		// Todas las consultas de modificacion siguientes
		// aplicaran solo despues que se confirme la transaccion,
		// de lo contrario no se aplicaran los cambios y se devolvera
		// la base de datos a su estado original.
		$this->pedeo->trans_begin();

		try {

			$sqlInsert = "INSERT INTO dmpl(dmlp_name_list, dmlp_profit, dmlp_baselist, dmpl_currency, business, branch)
						VALUES(:dmlp_name_list, :dmlp_profit, :dmlp_baselist, :dmpl_currency, :business, :branch)";

			$resInsert = $this->pedeo->insertRow($sqlInsert, array(

				':dmlp_name_list' => $Data['dmlp_name_list'],
				':dmlp_profit'    => $Data['dmlp_profit'],
				':dmlp_baselist'  => isset($Data['dmlp_baselist']) ? $Data['dmlp_baselist'] : 0,
				':dmpl_currency'  => isset($Data['dmpl_currency']) ? $Data['dmpl_currency'] : null,
				':business'  	  => $Data['business'],
				':branch'		  => $Data['branch']
			));

			if (is_numeric($resInsert) && $resInsert > 0) {

				$sqlDetail = "INSERT INTO mpl1(pl1_id_price_list, pl1_item_code, pl1_item_name, pl1_profit, pl1_price)
							VALUES(:pl1_id_price_list, :pl1_item_code, :pl1_item_name, :pl1_profit, :pl1_price)";

				$BaseList = isset($Data['dmlp_baselist']) ? $Data['dmlp_baselist'] : 0;
				$Precio = 0;


				if (isset($resProductos[0])) {

					foreach ($resProductos as $key => $prod) {
						$Precio = 0;

						if ($BaseList == "0" || $BaseList == 0) {

							$valor = $prod['dma_price'];
							$porcent = 0;
							$subtt = 0;

							$valorProfit = isset($Data['dmlp_profit']) ? $Data['dmlp_profit'] : 0;

							if ($valorProfit > 0) {
								$porcent = ($valorProfit / 100);
								$subtt = ($valor * $porcent);
							}

							$Precio = ($subtt + $valor);
						} else {

							$res = $this->getPrecio($prod['dma_item_code'], $BaseList);
							if (isset($res[0]['pl1_price'])) {
								$valor = $res[0]['pl1_price'];
								$porcent = 0;
								$subtt = 0;
								$valorProfit = isset($Data['dmlp_profit']) ? $Data['dmlp_profit'] : 0;

								if ($valorProfit > 0) {
									$porcent = ($valorProfit / 100);
									$subtt = ($valor * $porcent);
								}

								$Precio = ($subtt + $valor);
							} else {

								$Precio = 0;
							}
						}

						$resInsertDetail = $this->pedeo->insertRow($sqlDetail, array(

							':pl1_id_price_list' => $resInsert,
							':pl1_item_code' => $prod['dma_item_code'],
							':pl1_item_name' => $prod['dma_item_name'],
							':pl1_profit' => $Data['dmlp_profit'],
							':pl1_price' => $Precio
						));


						if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
						} else {

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
				}

				if (isset($resProductosNinv[0])) {

					$BaseList = isset($Data['dmlp_baselist']) ? $Data['dmlp_baselist'] : 0;
					$Precio = 0;
					
					foreach ($resProductosNinv as $key => $prod) {
						$Precio = 0;
						if ($BaseList == "0" || $BaseList == 0) {

							$valor = $prod['dma_price'];
							$porcent = 0;
							$subtt = 0;
	
							$valorProfit = isset($Data['dmlp_profit']) ? $Data['dmlp_profit'] : 0;
	
							if ($valorProfit > 0) {
								$porcent = ($valorProfit / 100);
								$subtt = ($valor * $porcent);
							}
	
							$Precio = ($subtt + $valor);
						} else {
	
							$res = $this->getPrecio($prod['dma_item_code'], $BaseList);
							if (isset($res[0]['pl1_price'])) {
								$valor = $res[0]['pl1_price'];
								$porcent = 0;
								$subtt = 0;
								$valorProfit = isset($Data['dmlp_profit']) ? $Data['dmlp_profit'] : 0;
	
								if ($valorProfit > 0) {
									$porcent = ($valorProfit / 100);
									$subtt = ($valor * $porcent);
								}
	
								$Precio = ($subtt + $valor);
							} else {
	
								$Precio = 0;
							}
						}
	
						$resInsertDetail = $this->pedeo->insertRow($sqlDetail, array(

							':pl1_id_price_list' => $resInsert,
							':pl1_item_code' => $prod['dma_item_code'],
							':pl1_item_name' => $prod['dma_item_name'],
							':pl1_profit' => $Data['dmlp_profit'],
							':pl1_price' => $Precio
						));


						if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
						} else {

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
				}
				// Si todo sale bien despues de insertar el detalle de la cotizacion
				// se confirma la trasaccion  para que los cambios apliquen permanentemente
				// en la base de datos y se confirma la operacion exitosa.
				$this->pedeo->trans_commit();

				$respuesta = array(
					'error' => false,
					'data' => $resInsert,
					'mensaje' => 'Lista registrada con exito'
				);
			} else {

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
	public function updatePriceList_post()
	{

		$Data = $this->post();

		if (
			!isset($Data['dmlp_name_list']) or
			!isset($Data['dmlp_profit']) or
			!isset($Data['dmlp_id']) or
			!isset($Data['business']) or
			!isset($Data['branch'])
		) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		// SE BUSCAN LOS PRODUCTOS
		// INVENTARIABLES
		$sqlProductos = "SELECT dma_item_code, dma_price, dmar.dma_item_name FROM dmar
											WHERE dmar.dma_enabled = :dma_enabled
											AND dma_item_sales = :dma_item_sales
											AND dma_item_inv = :dma_item_inv
											AND dma_item_code NOT IN (SELECT pl1_item_code FROM mpl1 WHERE pl1_id_price_list = :pl1_id_price_list)
											GROUP BY dma_item_code, dma_price, dmar.dma_item_name";


		$resProductos = $this->pedeo->queryTable($sqlProductos, array(":dma_enabled" => 1, ':dma_item_sales' => '1', ':dma_item_inv' => '1', ':pl1_id_price_list' => $Data['dmlp_id']));

		// NO INVENTARIABLES
		$sqlProductosNinv = "SELECT DISTINCT dma_item_name, dma_price, dma_item_code, 0 AS costo
								FROM dmar
								WHERE dma_item_sales = :dma_item_sales
								AND dma_enabled = :dma_enabled
								AND dma_item_code NOT IN (SELECT pl1_item_code FROM mpl1 WHERE pl1_id_price_list = :pl1_id_price_list)
								AND dma_item_inv = :dma_item_inv";

		$resProductosNinv = $this->pedeo->queryTable($sqlProductosNinv, array(':dma_item_sales' => '1', ':dma_enabled' => 1, ':dma_item_inv' => '0', ':pl1_id_price_list' => $Data['dmlp_id']));


		// EXISTENTES
		$sqlProductosExist = "SELECT pl1_id, pl1_item_code, pl1_item_name, pl1_price FROM mpl1 WHERE pl1_id_price_list = :pl1_id_price_list";
		$resProductosExist = $this->pedeo->queryTable($sqlProductosExist, array(':pl1_id_price_list' => $Data['dmlp_id']));


		if (!isset($resProductos[0]) && !isset($resProductosNinv[0])) {


			$sqlUpdate = "UPDATE dmpl SET dmlp_name_list = :dmlp_name_list, dmlp_profit = :dmlp_profit, dmlp_baselist = :dmlp_baselist, dmpl_currency = :dmpl_currency WHERE dmlp_id = :dmlp_id";


			$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

				':dmlp_name_list' => $Data['dmlp_name_list'],
				':dmlp_profit' 	  => $Data['dmlp_profit'],
				':dmlp_baselist'  => isset($Data['dmlp_baselist']) ? $Data['dmlp_baselist'] : 0,
				':dmlp_id'   	  => $Data['dmlp_id'],
				':dmpl_currency'  => $Data['dmpl_currency']
			));

			if (is_numeric($resUpdate) && $resUpdate == 1) {

				$respuesta = array(
					'error' => false,
					'data'  => array(),
					'mensaje' => 'No hay productos nuevos para agregar a la lista, solo se actualizo el encabezado de la lista seleccionada'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			} else {

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'No hay productos para asignar a la lista'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
		}

		
		$this->pedeo->trans_begin();

		try {

			$sqlUpdate = "UPDATE dmpl SET dmlp_name_list = :dmlp_name_list, dmlp_profit = :dmlp_profit, dmlp_baselist = :dmlp_baselist, dmpl_currency = :dmpl_currency  WHERE dmlp_id = :dmlp_id";


			$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

				':dmlp_name_list' => $Data['dmlp_name_list'],
				':dmlp_profit' 	  => $Data['dmlp_profit'],
				':dmlp_baselist'  => isset($Data['dmlp_baselist']) ? $Data['dmlp_baselist'] : 0,
				':dmlp_id'   	  => $Data['dmlp_id'],
				':dmpl_currency'  => $Data['dmpl_currency']
			));

			if (is_numeric($resUpdate) && $resUpdate == 1) {

				$sqlDetail = "INSERT INTO mpl1(pl1_id_price_list, pl1_item_code, pl1_item_name, pl1_profit, pl1_price)
							VALUES(:pl1_id_price_list, :pl1_item_code, :pl1_item_name, :pl1_profit, :pl1_price)";

				$sqlDetailUpdate = "UPDATE mpl1 SET pl1_id_price_list = :pl1_id_price_list, pl1_profit = :pl1_profit, pl1_price =:pl1_price WHERE pl1_id =:pl1_id)";
			



				// $this->pedeo->queryTable("DELETE FROM mpl1 WHERE pl1_id_price_list = :pl1_id_price_list",array(':pl1_id_price_list' => $Data['dmlp_id']));
				if (isset($resProductos[0])) {

					$BaseList = isset($Data['dmlp_baselist']) ? $Data['dmlp_baselist'] : 0;
					$Precio = 0;

					foreach ($resProductos as $key => $prod) {
						$Precio = 0;

						if ($BaseList == "0" || $BaseList == 0) {

							$valor = $prod['dma_price'];
							$porcent = 0;
							$subtt = 0;

							$valorProfit = isset($Data['dmlp_profit']) ? $Data['dmlp_profit'] : 0;

							if ($valorProfit > 0) {
								$porcent = ($valorProfit / 100);
								$subtt = ($valor * $porcent);
							}

							$Precio = ($subtt + $valor);
						} else {

							$res = $this->getPrecio($prod['dma_item_code'], $BaseList);
							if (isset($res[0]['pl1_price'])) {
								$valor = $res[0]['pl1_price'];
								$porcent = 0;
								$subtt = 0;

								$valorProfit = isset($Data['dmlp_profit']) ? $Data['dmlp_profit'] : 0;

								if ($valorProfit > 0) {
									$porcent = ($valorProfit / 100);
									$subtt = ($valor * $porcent);
								}


								$Precio = ($subtt + $valor);
							} else {

								$Precio = 0;
							}
						}

						$resInsertDetail = $this->pedeo->insertRow($sqlDetail, array(

							':pl1_id_price_list' => $Data['dmlp_id'],
							':pl1_item_code' => $prod['dma_item_code'],
							':pl1_item_name' => $prod['dma_item_name'],
							':pl1_profit' => $Data['dmlp_profit'],
							':pl1_price' => $Precio
						));

						if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
						} else {

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
				}

				if (isset($resProductosNinv[0])) {

					$BaseList = isset($Data['dmlp_baselist']) ? $Data['dmlp_baselist'] : 0;
					$Precio = 0;

					foreach ($resProductosNinv as $key => $prod) {

						$Precio = 0;

						if ($BaseList == "0" || $BaseList == 0) {

							$valor = $prod['dma_price'];
							$porcent = 0;
							$subtt = 0;

							$valorProfit = isset($Data['dmlp_profit']) ? $Data['dmlp_profit'] : 0;

							if ($valorProfit > 0) {
								$porcent = ($valorProfit / 100);
								$subtt = ($valor * $porcent);
							}

							$Precio = ($subtt + $valor);
						} else {

							$res = $this->getPrecio($prod['dma_item_code'], $BaseList);
							if (isset($res[0]['pl1_price'])) {
								$valor = $res[0]['pl1_price'];
								$porcent = 0;
								$subtt = 0;

								$valorProfit = isset($Data['dmlp_profit']) ? $Data['dmlp_profit'] : 0;

								if ($valorProfit > 0) {
									$porcent = ($valorProfit / 100);
									$subtt = ($valor * $porcent);
								}


								$Precio = ($subtt + $valor);
							} else {

								$Precio = 0;
							}
						}

						$resInsertDetail = $this->pedeo->insertRow($sqlDetail, array(

							':pl1_id_price_list' => $Data['dmlp_id'],
							':pl1_item_code' => $prod['dma_item_code'],
							':pl1_item_name' => $prod['dma_item_name'],
							':pl1_profit' => $Data['dmlp_profit'],
							':pl1_price' => $Precio
						));


						if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
						} else {

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
				}


				if ( isset($Data['change_profit']) && $Data['change_profit'] == 1 ){
					if ( isset($resProductosExist[0]) ) {

						$BaseList = isset($Data['dmlp_baselist']) ? $Data['dmlp_baselist'] : 0;
						$Precio = 0;
	
						foreach ($resProductosExist as $key => $prod) {
							$Precio = 0;
	
							if ($BaseList == "0" || $BaseList == 0) {
	
								$valor = $prod['pl1_price'];
								$porcent = 0;
								$subtt = 0;
	
								$valorProfit = isset($Data['dmlp_profit']) ? $Data['dmlp_profit'] : 0;
	
								if ($valorProfit > 0) {
									$porcent = ($valorProfit / 100);
									$subtt = ($valor * $porcent);
								}
	
								$Precio = ($subtt + $valor);
							} else {
	
								$res = $this->getPrecio($prod['pl1_item_code'], $BaseList);
								if (isset($res[0]['pl1_price'])) {
									$valor = $res[0]['pl1_price'];
									$porcent = 0;
									$subtt = 0;
	
									$valorProfit = isset($Data['dmlp_profit']) ? $Data['dmlp_profit'] : 0;
	
									if ($valorProfit > 0) {
										$porcent = ($valorProfit / 100);
										$subtt = ($valor * $porcent);
									}
	
	
									$Precio = ($subtt + $valor);
								} else {
	
									$Precio = 0;
								}
							}
	
							$resDetailUpdate = $this->pedeo->updateRow($sqlDetailUpdate, array(
								':pl1_id_price_list' => $Data['pl1_id_price_list'],
								':pl1_profit' => $Data['dmlp_profit'],
								':pl1_price' => $Precio,
								':pl1_id' => $prod['pl1_id']
	
							));
	
							if ( is_numeric($rersDetailUpdate) && $resDetailUpdate == 1 ){
	
							}else{
								$this->pedeo->trans_rollback();
	
								$respuesta = array(
									'error'   => true,
									'data' 		=> $resDetailUpdate,
									'mensaje'	=> 'No se pudo actualizar la lista de precios'
								);
	
								$this->response($respuesta);
	
								return;
							}
	
						}
					}
				}

				// Si todo sale bien despues de insertar el detalle de la cotizacion
				// se confirma la trasaccion  para que los cambios apliquen permanentemente
				// en la base de datos y se confirma la operacion exitosa.
				$this->pedeo->trans_commit();

				$respuesta = array(
					'error' => false,
					'data' => $resUpdate,
					'mensaje' => 'Lista actualizada con exito'
				);
			} else {

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

	// ACTUALIZAR MASIVAMENTE LAS LISTA DE PRECIOS
	//ACTUALIZAR LISTA DE PRECIOS
	public function updatePriceListMs_post()
	{

		$Data = $this->post();
	
	
		$sqlPL = " SELECT * FROM  dmpl WHERE business = :business AND branch = :branch";
		$resPL = $this->pedeo->queryTable($sqlPL, array(
			':business' => $Data['business'],
			':branch'   => $Data['branch']
		));
		
		if ( !isset($resPL[0]) ){

			$respuesta = array(
				'error'     => true,
				'data' 		=> $resPL,
				'mensaje'	=> ' El articulo fue creado, pero no hay listas de precio disponibles'
			);

			$this->response($respuesta);

			return;
		}

		$this->pedeo->trans_begin();

		try {

			
			foreach ($resPL as $key => $list) {

				// $this->pedeo->queryTable("DELETE FROM mpl1 WHERE pl1_id_price_list = :pl1_id_price_list",array(':pl1_id_price_list' => $list['dmlp_id']));

				// SE BUSCAN LOS PRODUCTOS
				// INVENTARIABLES
				$sqlProductos = "SELECT dma_item_code, dma_price, dmar.dma_item_name FROM dmar
									WHERE dmar.dma_enabled = :dma_enabled
									AND dma_item_sales = :dma_item_sales
									AND dma_item_inv = :dma_item_inv
									AND dma_item_code NOT IN (SELECT pl1_item_code FROM mpl1 WHERE pl1_id_price_list = :pl1_id_price_list)
									GROUP BY dma_item_code, dma_price, dmar.dma_item_name";


				$resProductos = $this->pedeo->queryTable($sqlProductos, array(":dma_enabled" => 1, ':dma_item_sales' => '1', ':dma_item_inv' => '1', ':pl1_id_price_list' => $list['dmlp_id']));

				// NO INVENTARIABLES
				$sqlProductosNinv = "SELECT DISTINCT dma_item_name, dma_item_code, 0 AS costo,dma_price
				FROM dmar
				WHERE dma_item_sales = :dma_item_sales
				AND dma_enabled = :dma_enabled
				AND dma_item_code NOT IN (SELECT pl1_item_code FROM mpl1 WHERE pl1_id_price_list = :pl1_id_price_list)
				AND dma_item_inv = :dma_item_inv";

				$resProductosNinv = $this->pedeo->queryTable($sqlProductosNinv, array(':dma_item_sales' => '1', ':dma_enabled' => 1, ':dma_item_inv' => '0', ':pl1_id_price_list' => $list['dmlp_id']));



				$sqlDetail = "INSERT INTO mpl1(pl1_id_price_list, pl1_item_code, pl1_item_name, pl1_profit, pl1_price)
						VALUES(:pl1_id_price_list, :pl1_item_code, :pl1_item_name, :pl1_profit, :pl1_price)";

				
				if (isset($resProductos[0])) {

					$BaseList = isset($list['dmlp_baselist']) ? $list['dmlp_baselist'] : 0;
					$Precio = 0;

					foreach ($resProductos as $key => $prod) {
						$Precio = 0;

						if ($BaseList == "0" || $BaseList == 0) {

							$valor = is_numeric($prod['dma_price']) ? $prod['dma_price'] : 0;
							$porcent = 0;
							$subtt = 0;

							$valorProfit = isset($list['dmlp_profit']) ? $list['dmlp_profit'] : 0;

							if ($valorProfit > 0) {
								$porcent = ($valorProfit / 100);
								$subtt = ($valor * $porcent);
							}

							$Precio = ($subtt + $valor);
						} else {

							$res = $this->getPrecio($prod['dma_item_code'], $BaseList);
							if (isset($res[0]['pl1_price'])) {
								$valor = $res[0]['pl1_price'];
								$porcent = 0;
								$subtt = 0;

								$valorProfit = isset($list['dmlp_profit']) ? $list['dmlp_profit'] : 0;

								if ($valorProfit > 0) {
									$porcent = ($valorProfit / 100);
									$subtt = ($valor * $porcent);
								}


								$Precio = ($subtt + $valor);
							} else {

								$Precio = 0;
							}
						}

						$resInsertDetail = $this->pedeo->insertRow($sqlDetail, array(

							':pl1_id_price_list' => $list['dmlp_id'],
							':pl1_item_code' => $prod['dma_item_code'],
							':pl1_item_name' => $prod['dma_item_name'],
							':pl1_profit' => $list['dmlp_profit'],
							':pl1_price' => $Precio
						));

						if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
						} else {

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
				}

				if (isset($resProductosNinv[0])) {

					$BaseList = isset($list['dmlp_baselist']) ? $list['dmlp_baselist'] : 0;
					$Precio = 0;

					foreach ($resProductosNinv as $key => $prod) {

						$Precio = is_numeric($prod['dma_price']) ? $prod['dma_price'] : 0;

						$resInsertDetail = $this->pedeo->insertRow($sqlDetail, array(

							':pl1_id_price_list' => $list['dmlp_id'],
							':pl1_item_code' => $prod['dma_item_code'],
							':pl1_item_name' => $prod['dma_item_name'],
							':pl1_profit' => $list['dmlp_profit'],
							':pl1_price' => $Precio
						));


						if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
						} else {

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
				}

				
			}
	
			
			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => [],
				'mensaje' => 'El articulo fue creado, y todas las listas de precio fueron actualizadas'
			);
			


		} catch (\Exception $e) {
			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data'    => $e,
				'mensaje'	=> 'No se pudo actualizar las listas de precio'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}



		$this->response($respuesta);
	}


	// OBTENER LISTA DE PRECIOS
	public function getPriceList_get()
	{

		$Data = $this->get();

		if (!isset($Data['business']) OR !isset($Data['branch'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM dmpl WHERE dmpl.business = :business AND  dmpl.branch = :branch";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':business' => $Data['business'], ':branch' => $Data['branch']));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}

	// OBTENER LISTA DE PRECIOS POR ID
	public function getPriceListById_get()
	{

		$Data = $this->get();

		if (!isset($Data['business']) OR !isset($Data['branch']) OR !isset($Data['dmlp_id']) ) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}


		$sqlSelect = " SELECT * FROM dmpl WHERE dmlp_id = :dmlp_id AND dmpl.business = :business";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':dmlp_id' => $Data['dmlp_id'], ':business' => $Data['business']));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}

	// OBTENER PRECIO PRODUCTO PUBLICO
	public function getPrecioByListEItem_post()
	{

		$Data = $this->post();

		if (
			!isset($Data['pl1_id_price_list']) or
			!isset($Data['pl1_item_code'])
		) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT pl1_price FROM mpl1 WHERE pl1_id_price_list = :pl1_id_price_list AND pl1_item_code = :pl1_item_code";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(

			':pl1_id_price_list' => $Data['pl1_id_price_list'],
			':pl1_item_code' => $Data['pl1_item_code']
		));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}


	// OBTENER PRECIO DE PRODUCTOS POR LISTA
	public function getPrecioByList_get()
	{

		$Data = $this->get();

		if (!isset($Data['pl1_id_price_list'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT * FROM mpl1 WHERE pl1_id_price_list = :pl1_id_price_list";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(

			':pl1_id_price_list' => $Data['pl1_id_price_list']
		));


		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}


	public function getListaPrecioDetalle_get()
	{

		$Data = $this->get();

		if (!isset($Data['business']) OR !isset($Data['branch']) OR !isset($Data['dmlp_id']) ) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'falto el codigo de la lista'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}
		$columns = array(
			"dmpl.dmlp_id",
			"dmpl.dmlp_name_list",
			"dmar.dma_item_code",
			"dmar.dma_item_name",
			"mpl1.pl1_price",
			"mpl1.pl1_id"
		);
		$variableSql = "";
		if (!empty($Data['search']['value'])) {
			// OBTENER CONDICIONALES.
			$variableSql .= " AND  " . self::get_Filter($columns, strtoupper($Data['search']['value']));
		}

		$sqlSelect = "SELECT DISTINCT dmlp_id, dmlp_name_list,
										dma_item_code, dma_item_name,
										pl1_price,pl1_id
										FROM dmpl
										INNER JOIN mpl1
										ON dmpl.dmlp_id = mpl1.pl1_id_price_list
										INNER JOIN dmar
										ON trim(mpl1.pl1_item_code) =  trim(dma_item_code)
										WHERE dmlp_id = :dmlp_id
										AND dmpl.business = :business ";
		// OBTENER NÚMERO DE REGISTROS DE LA TABLA.
		$numRows = $this->pedeo->queryTable($sqlSelect.$variableSql, [
											':dmlp_id'  => $Data['dmlp_id'],
											':business' => $Data['business']
										]);	
		
		$sqlSelect.= $variableSql;

		$sqlSelect .=" ORDER BY ".$columns[$Data['order'][0]['column']]." ".$Data['order'][0]['dir']." LIMIT ".$Data['length']." OFFSET ".$Data['start'];

		//print_r($sqlSelect);exit;
		$resSelect = $this->pedeo->queryTable($sqlSelect, array(

			':dmlp_id'  => $Data['dmlp_id'],
			':business' => $Data['business']
		));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'rows'  => count($numRows),
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}

	public function setChangePrice_post()
	{

		$Data = $this->post();

		if (!isset($Data['detail'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$ContenidoDetalle = json_decode($Data['detail'], true);

		// SE VALIDA QUE EL DOCUMENTO SEA UN ARRAY
		if (!is_array($ContenidoDetalle)) {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'Formato no valido'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}
		//


		// SE VALIDA QUE EL DOCUMENTO TENGA CONTENIDO
		if (!intval(count($ContenidoDetalle)) > 0) {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'Documento sin detalle'
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

					':pl1_price' => is_numeric($value['pl1_price']) ? $value['pl1_price'] : '',
					':pl1_id' 	 => is_numeric($value['pl1_id']) ? $value['pl1_id'] : ''
				));


				if (is_numeric($resUpdate) && $resUpdate == 1) {
				} else {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' 		=> $resUpdate,
						'mensaje'	=> 'no se pudo actualizar el precio de la lista ' . $value['pl1_id']
					);

					$this->response($respuesta);

					return;
				}
			}

			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resUpdate,
				'mensaje' => 'Lista de precio actualiza con exito'
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
	private function getPrecio($itemCode, $idPriceList)
	{

		$sqlSelect = "SELECT pl1_price FROM mpl1 WHERE pl1_id_price_list = :pl1_id_price_list AND pl1_item_code = :pl1_item_code";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(

			':pl1_id_price_list' => $idPriceList,
			':pl1_item_code' => $itemCode
		));

		return $resSelect;
	}

	/** * FUNCTION PARA CONTRUIR EL CONDICIONAL DEL FILTRO DEL DATATABLE. */
	public function get_Filter($columns, $value)
	{
		//
		$resultSet = "";
		// CONDICIONAL.
		$where = " {campo}::text LIKE '%" . $value . "%' OR";
		//
		try {
			//
			foreach ($columns as $column) {
				// REEMPLAZAR CAMPO.
				$resultSet .= str_replace('{campo}', $column, $where);
			}
			// REMOVER ULTIMO OR DE LA CADENA.
			$resultSet = substr($resultSet, 0, -2);
		} catch (Exception $e) {
			$resultSet = $e->getMessage();
		}
		//
		return $resultSet;
	}
}
