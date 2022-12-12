<?php
// Artículos
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class Items extends REST_Controller
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

	//Crear nuevo articulo
	public function createItems_post()
	{

		$Data = $this->post();

		$sqlSelect = "SELECT dma_item_code FROM dmar WHERE dma_item_code = :dma_item_code";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(

			':dma_item_code' => $Data['dma_item_code']

		));


		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => true,
				'data'  => array($Data['dms_card_code'], $Data['dms_card_type']),
				'mensaje' => 'ya existe un artículo con es codigo'
			);

			$this->response($respuesta);

			return;
		}

		//VALIDACION ITEM MANEJA SERIAL
		if (isset($Data['dma_series_code']) && $Data['dma_series_code'] == 1) {
			if ($Data['dma_uom_purch'] == $Data['dma_uom_sale']) {

				if ($Data['dma_uom_pemb'] == 1 && $Data['dma_uom_semb'] == 1) {
				} else {
					$respuesta = array(
						'error' => true,
						'data'  => [],
						'mensaje' => 'El articulo maneja serial, la cantidad de embalaje debe ser uno (1 Compras y Ventas)'
					);

					$this->response($respuesta);

					return;
				}
			} else {
				$respuesta = array(
					'error' => true,
					'data'  => [],
					'mensaje' => 'Si el articulo maneja serial, las unidades con que se vende y se compra no pueden tener equivalencias'
				);

				$this->response($respuesta);

				return;
			}
		}
		//

		$this->pedeo->trans_begin();

		try {

			$sqlInsert = "INSERT INTO dmar(dma_item_code, dma_item_name, dma_generic_name, dma_item_purch, dma_item_inv, dma_item_sales,
						dma_group_code, dma_attach, dma_enabled, dma_firm_code, dma_series_code, dma_sup_set, dma_sku_sup, dma_uom_purch,
						dma_uom_pqty, dma_uom_pemb, dma_uom_pembqty, dma_tax_purch, dma_price_list, dma_price, dma_uom_sale,
						dma_uom_sqty, dma_uom_semb, dma_uom_embqty, dma_tax_sales, dma_acct_type, dma_avprice,dma_uom_weight,dma_uom_umvol,
						dma_uom_vqty, dma_uom_weightn, dma_uom_sizedim,dma_lotes_code, dma_emisionmethod, dma_long_description, dma_item_mat,
						dma_accounting, dma_acctin, dma_acct_out, dma_acct_inv, dma_acct_stockn, dma_acct_stockp, dma_acct_redu, dma_acct_amp,
						dma_acct_cost, dma_acct_return, dma_uom_width, dma_uom_tall, dma_uom_length, dma_uom_vol, dma_um_inventory, dma_tax_sales_code, dma_tax_purch_code)
						VALUES(:dma_item_code,:dma_item_name, :dma_generic_name, :dma_item_purch,
						:dma_item_inv, :dma_item_sales, :dma_group_code, :dma_attach,:dma_enabled, :dma_firm_code, :dma_series_code, :dma_sup_set,
						:dma_sku_sup, :dma_uom_purch, :dma_uom_pqty, :dma_uom_pemb,:dma_uom_pembqty, :dma_tax_purch, :dma_price_list, :dma_price, :dma_uom_sale, :dma_uom_sqty,
						:dma_uom_semb, :dma_uom_embqty, :dma_tax_sales, :dma_acct_type,:dma_avprice,:dma_uom_weight, :dma_uom_umvol, :dma_uom_vqty, :dma_uom_weightn,
						:dma_uom_sizedim,:dma_lotes_code, :dma_emisionmethod, :dma_long_description, :dma_item_mat,
						:dma_accounting, :dma_acctin, :dma_acct_out, :dma_acct_inv, :dma_acct_stockn, :dma_acct_stockp, :dma_acct_redu, :dma_acct_amp,
						:dma_acct_cost, :dma_acct_return, :dma_uom_width, :dma_uom_tall, :dma_uom_length, :dma_uom_vol, :dma_um_inventory, :dma_tax_sales_code, :dma_tax_purch_code)";


			$resInsert = $this->pedeo->insertRow($sqlInsert, array(

				':dma_item_code' => isset($Data['dma_item_code']) ? $Data['dma_item_code'] : NULL,
				':dma_item_name' => isset($Data['dma_item_name']) ? $Data['dma_item_name'] : NULL,
				':dma_generic_name' => isset($Data['dma_generic_name']) ? $Data['dma_generic_name'] : NULL,
				':dma_item_purch' => isset($Data['dma_item_purch']) ? $Data['dma_item_purch'] : NULL,
				':dma_item_inv' => isset($Data['dma_item_inv']) ? $Data['dma_item_inv'] : NULL,
				':dma_item_sales' => isset($Data['dma_item_sales']) ? $Data['dma_item_sales'] : NULL,
				':dma_group_code' => is_numeric($Data['dma_group_code']) ? $Data['dma_group_code'] : 0,
				':dma_attach' => is_numeric($Data['dma_attach']) ? $Data['dma_attach'] : 0,
				':dma_enabled' => is_numeric($Data['dma_enabled']) ? $Data['dma_enabled'] : 0,
				':dma_firm_code' => isset($Data['dma_firm_code']) ? $Data['dma_firm_code'] : NULL,
				':dma_series_code' => isset($Data['dma_series_code']) ? $Data['dma_series_code'] : NULL,
				':dma_sup_set' => isset($Data['dma_sup_set']) ? $Data['dma_sup_set'] : NULL,
				':dma_sku_sup' => isset($Data['dma_sku_sup']) ? $Data['dma_sku_sup'] : NULL,
				':dma_uom_purch' => is_numeric($Data['dma_uom_purch']) ? $Data['dma_uom_purch'] : 0,
				':dma_uom_pqty' => is_numeric($Data['dma_uom_pqty']) ? $Data['dma_uom_pqty'] : 0,
				':dma_uom_pemb' => is_numeric($Data['dma_uom_pemb']) ? $Data['dma_uom_pemb'] : 0,
				':dma_uom_pembqty' => is_numeric($Data['dma_uom_pembqty']) ? $Data['dma_uom_pembqty'] : 0,
				':dma_tax_purch' => is_numeric($Data['dma_tax_purch']) ? $Data['dma_tax_purch'] : 0,
				':dma_price_list' => isset($Data['dma_price_list']) ? $Data['dma_price_list'] : 0,
				':dma_price' => is_numeric($Data['dma_price']) ? $Data['dma_price'] : 0,
				':dma_uom_sale' => is_numeric($Data['dma_uom_sale']) ? $Data['dma_uom_sale'] : 0,
				':dma_uom_sqty' => is_numeric($Data['dma_uom_sqty']) ? $Data['dma_uom_sqty'] : 0,
				':dma_uom_semb' => is_numeric($Data['dma_uom_semb']) ? $Data['dma_uom_semb'] : 0,
				':dma_uom_embqty' => is_numeric($Data['dma_uom_embqty']) ? $Data['dma_uom_embqty'] : 0,
				':dma_tax_sales' => is_numeric($Data['dma_tax_sales']) ? $Data['dma_tax_sales'] : 0,
				':dma_acct_type' => is_numeric($Data['dma_acct_type']) ? $Data['dma_acct_type'] : 0,
				':dma_avprice' => is_numeric($Data['dma_avprice']) ? $Data['dma_avprice'] : 0,
				':dma_uom_weight' => is_numeric($Data['dma_uom_weight']) ? $Data['dma_uom_weight'] : 0,
				':dma_uom_umvol'    => is_numeric($Data['dma_uom_umvol']) ? $Data['dma_uom_umvol'] : 0,
				':dma_uom_vqty'     => is_numeric($Data['dma_uom_vqty']) ? $Data['dma_uom_vqty'] : 0,
				':dma_uom_weightn'  => is_numeric($Data['dma_uom_weightn']) ? $Data['dma_uom_weightn'] : 0,
				':dma_uom_sizedim'  => isset($Data['dma_uom_sizedim']) ? $Data['dma_uom_sizedim'] : 0,
				':dma_lotes_code' => isset($Data['dma_lotes_code']) ? $Data['dma_lotes_code'] : '0',
				':dma_emisionmethod' => isset($Data['dma_emisionmethod']) ? $Data['dma_emisionmethod'] : 0,
				':dma_long_description' => isset($Data['dma_long_description']) ? $Data['dma_long_description'] : NULL,
				':dma_um_inventory' => isset($Data['dma_um_inventory']) ? $Data['dma_um_inventory'] : NULL,
				':dma_item_mat' => isset($Data['dma_item_mat']) ? $Data['dma_item_mat'] : 0,
				//CUENTAS CONTABLES
				':dma_accounting' => isset($Data['dma_accounting']) ? $Data['dma_accounting'] : NULL,
				':dma_acctin' => isset($Data['dma_acctin']) ? $Data['dma_acctin'] : 0,
				':dma_acct_out' => isset($Data['dma_acct_out']) ? $Data['dma_acct_out'] : 0,
				':dma_acct_inv' => isset($Data['dma_acct_inv']) ? $Data['dma_acct_inv'] : 0,
				':dma_acct_stockn' => isset($Data['dma_acct_stockn']) ? $Data['dma_acct_stockn'] : 0,
				':dma_acct_stockp' => isset($Data['dma_acct_stockp']) ? $Data['dma_acct_stockp'] : 0,
				':dma_acct_redu' => isset($Data['dma_acct_redu']) ? $Data['dma_acct_redu'] : 0,
				':dma_acct_amp' => isset($Data['dma_acct_amp']) ? $Data['dma_acct_amp'] : 0,
				':dma_acct_cost' => isset($Data['dma_acct_cost']) ? $Data['dma_acct_cost'] : 0,
				':dma_acct_return' => isset($Data['dma_acct_return']) ? $Data['dma_acct_return'] : 0,
				// VOLUMEN
				':dma_uom_width' => isset($Data['dma_uom_width']) ? $Data['dma_uom_width'] : 0,
				':dma_uom_tall' => isset($Data['dma_uom_tall']) ? $Data['dma_uom_tall'] : 0,
				':dma_uom_length' => isset($Data['dma_uom_length']) ? $Data['dma_uom_length'] : 0,
				':dma_uom_vol' => isset($Data['dma_uom_vol']) ? $Data['dma_uom_vol'] : 0,
				//CODIGO DE IMPUESTOS
				':dma_tax_purch_code' => isset($Data['dma_tax_purch_code']) ? $Data['dma_tax_purch_code'] : NULL,
				':dma_tax_sales_code' => isset($Data['dma_tax_sales_code']) ? $Data['dma_tax_sales_code'] : NULL,


			));


			if (is_numeric($resInsert) && $resInsert > 0) {

				$this->pedeo->trans_commit();

				$respuesta = array(
					'error' 	 => false,
					'data' 	 => $resInsert,
					'mensaje' => 'Artículo registrado con exito'
				);
			} else {

				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data' 		=> $resInsert,
					'mensaje' => 'No se pudo registrar el artículo'
				);
			}
		} catch (\Exception $e) {

			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data' 		=> $e,
				'mensaje' => 'No se pudo registrar el artículo'
			);

			$this->response($respuesta);

			return;
		}



		$this->response($respuesta);
	}

	//Actualizar articulo
	public function updateItems_post()
	{

		$Data = $this->post();

		$sqlSelect = "SELECT dma_item_code FROM dmar WHERE dma_item_code = :dma_item_code
                    AND dma_id != :dma_id";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(

			':dma_item_code' => $Data['dma_item_code'],
			':dma_id'        => $Data['dma_id']

		));


		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => true,
				'data'  => [],
				'mensaje' => 'ya existe un artículo con es codigo, no es posible actualizar '
			);

			$this->response($respuesta);

			return;
		}


		//VALIDACION ITEM MANEJA SERIAL
		if (isset($Data['dma_series_code']) && $Data['dma_series_code'] == 1) {
			if ($Data['dma_uom_purch'] == $Data['dma_uom_sale']) {

				if ($Data['dma_uom_pemb'] == 1 && $Data['dma_uom_semb'] == 1) {
				} else {
					$respuesta = array(
						'error' => true,
						'data'  => [],
						'mensaje' => 'El articulo maneja serial, la cantidad de embalaje debe ser uno (1 Compras y Ventas)'
					);

					$this->response($respuesta);

					return;
				}
			} else {
				$respuesta = array(
					'error' => true,
					'data'  => [],
					'mensaje' => 'Si el articulo maneja serial, las unidades con que se vende y se compra no pueden tener equivalencias'
				);

				$this->response($respuesta);

				return;
			}
		}
		//


		$this->pedeo->trans_begin();

		try {
			$sqlUpdate = "UPDATE dmar
						SET dma_item_code = :dma_item_code, dma_item_name = :dma_item_name, dma_generic_name = :dma_generic_name,
						dma_item_purch = :dma_item_purch, dma_item_inv = :dma_item_inv, dma_item_sales = :dma_item_sales,
						dma_group_code = :dma_group_code, dma_attach = :dma_attach, dma_enabled = :dma_enabled, dma_firm_code = :dma_firm_code,
						dma_series_code = :dma_series_code, dma_sup_set = :dma_sup_set, dma_sku_sup = :dma_sku_sup, dma_uom_purch = :dma_uom_purch,
						dma_uom_pqty = :dma_uom_pqty, dma_uom_pemb = :dma_uom_pemb, dma_uom_pembqty = :dma_uom_pembqty, dma_tax_purch = :dma_tax_purch,
						dma_price_list = :dma_price_list, dma_price = :dma_price, dma_uom_sale = :dma_uom_sale, dma_uom_sqty = :dma_uom_sqty,
						dma_uom_semb = :dma_uom_semb, dma_uom_embqty = :dma_uom_embqty, dma_tax_sales = :dma_tax_sales, dma_acct_type = :dma_acct_type,
						dma_avprice = :dma_avprice,dma_uom_weight = :dma_uom_weight, dma_uom_umvol = :dma_uom_umvol, dma_uom_vqty = :dma_uom_vqty,
						dma_uom_weightn = :dma_uom_weightn, dma_uom_sizedim = :dma_uom_sizedim, dma_lotes_code = :dma_lotes_code, dma_emisionmethod = :dma_emisionmethod,
						dma_long_description = :dma_long_description, dma_item_mat = :dma_item_mat,
						dma_accounting = :dma_accounting, dma_acctin = :dma_acctin, dma_acct_out = :dma_acct_out, dma_acct_inv = :dma_acct_inv,
						dma_acct_stockn = :dma_acct_stockn, dma_acct_stockp = :dma_acct_stockp, dma_acct_redu = :dma_acct_redu, 
						dma_acct_amp = :dma_acct_amp, dma_acct_cost = :dma_acct_cost, dma_acct_return = :dma_acct_return,
						dma_uom_width = :dma_uom_width, dma_uom_tall = :dma_uom_tall, dma_uom_length = :dma_uom_length, dma_uom_vol = :dma_uom_vol, dma_um_inventory = :dma_um_inventory,
						dma_tax_sales_code = :dma_tax_sales_code, dma_tax_purch_code = :dma_tax_purch_code
						WHERE dma_id = :dma_id";

			$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

				':dma_item_code' => isset($Data['dma_item_code']) ? $Data['dma_item_code'] : NULL,
				':dma_item_name' => isset($Data['dma_item_name']) ? $Data['dma_item_name'] : NULL,
				':dma_generic_name' => isset($Data['dma_generic_name']) ? $Data['dma_generic_name'] : NULL,
				':dma_item_purch' => isset($Data['dma_item_purch']) ? $Data['dma_item_purch'] : NULL,
				':dma_item_inv' => isset($Data['dma_item_inv']) ? $Data['dma_item_inv'] : NULL,
				':dma_item_sales' => isset($Data['dma_item_sales']) ? $Data['dma_item_sales'] : NULL,
				':dma_group_code' => is_numeric($Data['dma_group_code']) ? $Data['dma_group_code'] : 0,
				':dma_attach' => is_numeric($Data['dma_attach']) ? $Data['dma_attach'] : 0,
				':dma_enabled' => is_numeric($Data['dma_enabled']) ? $Data['dma_enabled'] : 0,
				':dma_firm_code' => isset($Data['dma_firm_code']) ? $Data['dma_firm_code'] : NULL,
				':dma_series_code' => isset($Data['dma_series_code']) ? $Data['dma_series_code'] : NULL,
				':dma_sup_set' => isset($Data['dma_sup_set']) ? $Data['dma_sup_set'] : NULL,
				':dma_sku_sup' => isset($Data['dma_sku_sup']) ? $Data['dma_sku_sup'] : NULL,
				':dma_uom_purch' => is_numeric($Data['dma_uom_purch']) ? $Data['dma_uom_purch'] : 0,
				':dma_uom_pqty' => is_numeric($Data['dma_uom_pqty']) ? $Data['dma_uom_pqty'] : 0,
				':dma_uom_pemb' => is_numeric($Data['dma_uom_pemb']) ? $Data['dma_uom_pemb'] : 0,
				':dma_uom_pembqty' => is_numeric($Data['dma_uom_pembqty']) ? $Data['dma_uom_pembqty'] : 0,
				':dma_tax_purch' => is_numeric($Data['dma_tax_purch']) ? $Data['dma_tax_purch'] : 0,
				':dma_price_list' => isset($Data['dma_price_list']) ? $Data['dma_price_list'] : 0,
				':dma_price' => is_numeric($Data['dma_price']) ? $Data['dma_price'] : 0,
				':dma_uom_sale' => is_numeric($Data['dma_uom_sale']) ? $Data['dma_uom_sale'] : 0,
				':dma_uom_sqty' => is_numeric($Data['dma_uom_sqty']) ? $Data['dma_uom_sqty'] : 0,
				':dma_uom_semb' => is_numeric($Data['dma_uom_semb']) ? $Data['dma_uom_semb'] : 0,
				':dma_uom_embqty' => is_numeric($Data['dma_uom_embqty']) ? $Data['dma_uom_embqty'] : 0,
				':dma_tax_sales' => is_numeric($Data['dma_tax_sales']) ? $Data['dma_tax_sales'] : 0,
				':dma_acct_type' => is_numeric($Data['dma_acct_type']) ? $Data['dma_acct_type'] : 0,
				':dma_avprice' => is_numeric($Data['dma_avprice']) ? $Data['dma_avprice'] : 0,
				':dma_uom_weight' => is_numeric($Data['dma_uom_weight']) ? $Data['dma_uom_weight'] : 0,
				':dma_uom_umvol'    => isset($Data['dma_uom_umvol']) ? $Data['dma_uom_umvol'] : 0,
				':dma_uom_vqty'     => is_numeric($Data['dma_uom_vqty']) ? $Data['dma_uom_vqty'] : 0,
				':dma_uom_weightn'  => is_numeric($Data['dma_uom_weightn']) ? $Data['dma_uom_weightn'] : 0,
				':dma_uom_sizedim'  => isset($Data['dma_uom_sizedim']) ? $Data['dma_uom_sizedim'] : 0,
				':dma_lotes_code' => isset($Data['dma_lotes_code']) ? $Data['dma_lotes_code'] : '0',
				':dma_emisionmethod' => isset($Data['dma_emisionmethod']) ? $Data['dma_emisionmethod'] : 0,
				':dma_long_description' => isset($Data['dma_long_description']) ? $Data['dma_long_description'] : NULL,
				':dma_item_mat' => isset($Data['dma_item_mat']) ? $Data['dma_item_mat'] : 0,
				':dma_um_inventory' => isset($Data['dma_um_inventory']) ? $Data['dma_um_inventory'] : NULL,
				':dma_id' => $Data['dma_id'],
				//CUENTAS CONTABLES
				':dma_accounting' => isset($Data['dma_accounting']) ? $Data['dma_accounting'] : NULL,
				':dma_acctin' => isset($Data['dma_acctin']) && is_numeric($Data['dma_acctin']) ? $Data['dma_acctin'] : 0,
				':dma_acct_out' => isset($Data['dma_acct_out']) && is_numeric($Data['dma_acct_out']) ? $Data['dma_acct_out'] : 0,
				':dma_acct_inv' => isset($Data['dma_acct_inv']) && is_numeric($Data['dma_acct_inv']) ? $Data['dma_acct_inv'] : 0,
				':dma_acct_stockn' => isset($Data['dma_acct_stockn']) && is_numeric($Data['dma_acct_stockn']) ? $Data['dma_acct_stockn'] : 0,
				':dma_acct_stockp' => isset($Data['dma_acct_stockp']) && is_numeric($Data['dma_acct_stockp']) ? $Data['dma_acct_stockp'] : 0,
				':dma_acct_redu' => isset($Data['dma_acct_redu']) && is_numeric($Data['dma_acct_redu']) ? $Data['dma_acct_redu'] : 0,
				':dma_acct_amp' => isset($Data['dma_acct_amp']) && is_numeric($Data['dma_acct_amp']) ? $Data['dma_acct_amp'] : 0,
				':dma_acct_cost' => isset($Data['dma_acct_cost']) && is_numeric($Data['dma_acct_cost']) ? $Data['dma_acct_cost'] : 0,
				':dma_acct_return' => isset($Data['dma_acct_return']) && is_numeric($Data['dma_acct_return']) ? $Data['dma_acct_return'] : 0,
				// VOLUMEN
				':dma_uom_width' => is_numeric($Data['dma_uom_width']) ? $Data['dma_uom_width'] : 0,
				':dma_uom_tall' => is_numeric($Data['dma_uom_tall']) ? $Data['dma_uom_tall'] : 0,
				':dma_uom_length' => is_numeric($Data['dma_uom_length']) ? $Data['dma_uom_length'] : 0,
				':dma_uom_vol' => is_numeric($Data['dma_uom_vol']) ? $Data['dma_uom_vol'] : 0,
				//CODIGOS DE IMPUESTOS
				':dma_tax_purch_code' => isset($Data['dma_tax_purch_code']) ? $Data['dma_tax_purch_code'] : NULL,
				':dma_tax_sales_code' => isset($Data['dma_tax_sales_code']) ? $Data['dma_tax_sales_code'] : NULL,
			));

			if (is_numeric($resUpdate) && $resUpdate == 1) {

				$this->pedeo->trans_commit();

				$respuesta = array(
					'error' => false,
					'data' => $resUpdate,
					'mensaje' => 'Artículo actualizado con exito'
				);
			} else {

				$respuesta = array(
					'error'   => true,
					'data' => $resUpdate,
					'mensaje'	=> 'No se pudo actualizar el artículo'
				);
			}
		} catch (\Exception $e) {

			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data' => $resUpdate,
				'mensaje'	=> 'No se pudo actualizar el artículo'
			);
			$this->response($respuesta);

			return;
		}



		$this->response($respuesta);
	}

	// Obtener articulos
	public function getItems_get()
	{

		$Data = $this->get();

		$variableSql = ' WHERE 1=1';

		if (isset($Data['sub_artic']) && !empty($Data['sub_artic'])) {

			$variableSql = $variableSql . " AND cast(t0.dma_group_code as varchar) LIKE '%" . $Data['sub_artic'] . "%'";
		}
		if (isset($Data['cod_artic']) &&  !empty($Data['cod_artic'])) {

			$variableSql = $variableSql . " AND t0.dma_item_code LIKE '%" . $Data['cod_artic'] . "%'";
		}

		if (isset($Data['nom_artic']) &&  !empty($Data['nom_artic'])) {

			$variableSql = $variableSql . " AND t0.dma_item_name LIKE '%" . $Data['nom_artic'] . "%'";
		}

		$sqlSelect = "SELECT distinct
												t0.*,
												t2.mga_name,
												sum(t1.bdi_quantity) stock
											from dmar t0
											left join tbdi t1 on t0.dma_item_code = t1.bdi_itemcode
											left join dmga t2 on t0.dma_group_code = t2.mga_id
											" . $variableSql . "
											group by
											t0.dma_id, t0.dma_item_code, t0.dma_item_name, t0.dma_generic_name, t0.dma_item_purch, t0.dma_item_inv,
											t0.dma_item_sales, t0.dma_group_code, t0.dma_attach, t0.dma_enabled, t0.dma_firm_code, t0.dma_series_code,
											t0.dma_sup_set, t0.dma_sku_sup, t0.dma_uom_purch, t0.dma_uom_pqty, t0.dma_uom_pemb, t0.dma_uom_pembqty,
											t0.dma_tax_purch, t0.dma_price_list,t0.dma_price, t0.dma_uom_sale, t0.dma_uom_sqty, t0.dma_uom_semb,
											t0.dma_uom_embqty, t0.dma_tax_sales, t0.dma_acct_type, t0.dma_avprice, t0.dma_uom_weight, t0.dma_uom_umvol,
											t0.dma_uom_vqty, t0.dma_uom_weightn, t0.dma_uom_sizedim,t2.mga_name LIMIT 500";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array());

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

	// Obtener articulos
	public function getDtItems_post()
	{

		$request = $this->post();

		$variableSql = 'WHERE 1=1';

		if (isset($request['sub_artic']) && !empty($request['sub_artic'])) {

			$variableSql .= " AND cast(t0.dma_group_code as varchar) LIKE '%" . $request['sub_artic'] . "%'";
		}
		if (isset($request['cod_artic']) &&  !empty($request['cod_artic'])) {

			$variableSql .= " AND t0.dma_item_code LIKE '%" . $request['cod_artic'] . "%'";
		}

		if (isset($request['nom_artic']) &&  !empty($request['nom_artic'])) {

			$variableSql .= " AND t0.dma_item_name LIKE '%" . $request['nom_artic'] . "%'";
		}
		// OBTENER NÚMERO DE REGISTROS DE LA TABLA.
		$numRows = $this->pedeo->queryTable("select get_numrows('dmar') as numrows", []);
		// COLUMNAS DEL DATATABLE
		$columns = array(
			'cast(t0.dma_id as varchar)',
			't0.dma_item_code',
			't0.dma_item_name',
			't2.mga_name',
			'cast(t0.dma_enabled as varchar)'
		);
		//
		if (!empty($request['search']['value'])) {
			// OBTENER CONDICIONALES.
			$variableSql .= " AND  " . self::get_Filter($columns, $request['search']['value']);
		}
		//

		$sqlSelect = "SELECT t0.*, t2.mga_name, COALESCE(SUM(t1.bdi_quantity),0) stock,t1.bdi_lote,t3.ote_createdate,t3.ote_duedate FROM dmar t0 LEFT JOIN tbdi t1 on t0.dma_item_code = t1.bdi_itemcode LEFT JOIN dmga t2 on t0.dma_group_code = t2.mga_id left join lote t3 on t1.bdi_lote = t3.ote_code  $variableSql GROUP BY t0.dma_id, t0.dma_item_code, t0.dma_item_name, t0.dma_generic_name, t0.dma_item_purch, t0.dma_item_inv, t0.dma_item_sales, t0.dma_group_code, t0.dma_attach, t0.dma_enabled, t0.dma_firm_code, t0.dma_series_code, t0.dma_sup_set, t0.dma_sku_sup, t0.dma_uom_purch, t0.dma_uom_pqty, t0.dma_uom_pemb, t0.dma_uom_pembqty, t0.dma_tax_purch, t0.dma_price_list,t0.dma_price, t0.dma_uom_sale, t0.dma_uom_sqty, t0.dma_uom_semb, t0.dma_uom_embqty, t0.dma_tax_sales, t0.dma_acct_type, t0.dma_avprice, t0.dma_uom_weight, t0.dma_uom_umvol, t0.dma_uom_vqty, t0.dma_uom_weightn, t0.dma_uom_sizedim,t2.mga_name,t1.bdi_lote,t3.ote_createdate,t3.ote_duedate";

		//
		$sqlSelect .= " ORDER BY " . $columns[$request['order'][0]['column']] . " " . $request['order'][0]['dir'];
		if ($request['length'] != '0') {
			$sqlSelect .= " LIMIT " . $request['length'] . " OFFSET " . $request['start'];
		}
		$resSelect = $this->pedeo->queryTable($sqlSelect, array());


		$respuesta = array(
			'error' => false,
			'data'  => $resSelect,
			'rows'  => $numRows[0]['numrows'],
			'mensaje' => ''
		);

		$this->response($respuesta);
	}
	// Obtener costo del articulo
	public function getItemsCost_get()
	{

		$Data = $this->get();


		$sqlSelect = "SELECT distinct
											bdi_itemcode,
											bdi_whscode,
											bdi_avgprice
											from tbdi
											WHERE bdi_itemcode = :bdi_itemcode and
											bdi_whscode = :bdi_whscode
											GROUP BY bdi_whscode, bdi_itemcode, bdi_avgprice";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(
			':bdi_itemcode' => $Data['bdi_itemcode'],
			':bdi_whscode' => $Data['bdi_whscode']
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
	//Obtener articulo id del articulo
	public function getItemsById_get()
	{

		$Data = $this->get();

		if (!isset($Data['dma_id'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM dmar WHERE dma_id = :dma_id";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':dma_id' => $Data['dma_id']));

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
	//Obtener articulo id del articulo
	public function getTotalWarehouseByItem_get()
	{

		$Data = $this->get();

		if (!isset($Data['item_code'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT sum(ei1_quantity) as total, ei1_whscode, dmws.dws_name, ei1_uom
					from iei1
					left join dmws on dmws.dws_code = iei1.ei1_whscode
					where ei1_itemcode = :item_code
					group by ei1_whscode, dmws.dws_name, ei1_uom";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':item_code' => $Data['item_code']));

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

	//Inactivar o Activar Articulo
	public function updateItemsStatus_post()
	{

		$Data = $this->post();

		if (!isset($Data['dma_id']) || !isset($Data['dma_enabled'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
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

		if (is_numeric($resUpdate) && $resUpdate == 1) {

			$respuesta = array(
				'error' => false,
				'data' => $resUpdate,
				'mensaje' => 'Artículo actualizado con exito'
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => $resUpdate,
				'mensaje'	=> 'No se pudo actualizar el artículo'
			);
		}

		$this->response($respuesta);
	}

	/** * FUNCTION PARA CONTRUIR EL CONDICIONAL DEL FILTRO DEL DATATABLE. */
	public function get_Filter($columns, $value)
	{
		//
		$resultSet = "";
		// CONDICIONAL.
		$where = " {campo} LIKE '%" . $value . "%' OR";
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
