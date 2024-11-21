<?php
// Artículos
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

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
		$this->load->library('account');
		
	}

	//Crear nuevo articulo
	public function createItems_post()
	{

		$Data = $this->post();
		//ASIGNAR UNIDAD DE MEDIDA SI EL ARTICULO NO SE MARCA COMO IVENTARIO
		$unidad = 0;
		$cantidad1 = 0;
		$cantidad2 = 0;
		if(isset($Data['dma_item_inv']) && $Data['dma_item_inv'] == "0"){
			$unidad = 8;
			$cantidad1 = 1;
			$cantidad2 = 1;
		}

		$sqlSelect = "SELECT dma_item_code FROM dmar WHERE UPPER(trim(dma_item_code)) = UPPER(trim(:dma_item_code))";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(

			':dma_item_code' => $Data['dma_item_code']

		));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => true,
				'data'  => $resSelect,
				'mensaje' => 'ya existe un artículo con es código '. $resSelect[0]['dma_item_code']
			);

			$this->response($respuesta);

			return;
		}

		// VALIDACION NUMERO DE SERIE FABRICANTE
		if (isset($Data['dma_serial_number']) && !empty($Data['dma_serial_number']) && $Data['dma_serial_number'] != 0){
			$sqlSelect = "SELECT dma_serial_number, dma_item_code FROM dmar WHERE UPPER(trim(dma_serial_number)) = UPPER(trim(:dma_serial_number))";

			$resSelect = $this->pedeo->queryTable($sqlSelect, array(
	
				':dma_serial_number' => $Data['dma_serial_number']
	
			));
	
			if (isset($resSelect[0])) {
	
				$respuesta = array(
					'error' => true,
					'data'  => $resSelect,
					'mensaje' => 'ya existe un artículo con ese código de fabrica '. $resSelect[0]['dma_serial_number']
				);
	
				$this->response($respuesta);
	
				return;
			}
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
						dma_acct_cost, dma_acct_return, dma_uom_width, dma_uom_tall, dma_uom_length, dma_uom_vol, dma_um_inventory, dma_tax_sales_code, dma_tax_purch_code,dma_acct_invproc,
						dma_modular, dma_advertisement, dma_subscription, dma_use_tbase, dma_tasa_base, dma_type_art, dma_serial_number,deductible_spent,taxable_income,
						dma_asset,dma_clean,dma_multipletax,dma_multipletax_purchcode,dma_multipletax_salescode,dma_item_asset, dma_last_cardcode,
						dma_last_purchase_date, dma_wscode,dma_ubication, dma_old_code , dma_act_ec_siat, dma_itemcode_siat)
						VALUES(:dma_item_code,:dma_item_name, :dma_generic_name, :dma_item_purch,
						:dma_item_inv, :dma_item_sales, :dma_group_code, :dma_attach,:dma_enabled, :dma_firm_code, :dma_series_code, :dma_sup_set,
						:dma_sku_sup, :dma_uom_purch, :dma_uom_pqty, :dma_uom_pemb,:dma_uom_pembqty, :dma_tax_purch, :dma_price_list, :dma_price, :dma_uom_sale, :dma_uom_sqty,
						:dma_uom_semb, :dma_uom_embqty, :dma_tax_sales, :dma_acct_type,:dma_avprice,:dma_uom_weight, :dma_uom_umvol, :dma_uom_vqty, :dma_uom_weightn,
						:dma_uom_sizedim,:dma_lotes_code, :dma_emisionmethod, :dma_long_description, :dma_item_mat,
						:dma_accounting, :dma_acctin, :dma_acct_out, :dma_acct_inv, :dma_acct_stockn, :dma_acct_stockp, :dma_acct_redu, :dma_acct_amp,
						:dma_acct_cost, :dma_acct_return, :dma_uom_width, :dma_uom_tall, :dma_uom_length, :dma_uom_vol, :dma_um_inventory, :dma_tax_sales_code, :dma_tax_purch_code,:dma_acct_invproc,
						:dma_modular, :dma_advertisement, :dma_subscription, :dma_use_tbase, :dma_tasa_base, :dma_type_art, :dma_serial_number,:deductible_spent,:taxable_income,:dma_asset,:dma_clean,
						:dma_multipletax,:dma_multipletax_purchcode,:dma_multipletax_salescode,:dma_item_asset, :dma_last_cardcode,
						:dma_last_purchase_date, :dma_wscode,:dma_ubication, :dma_old_code , :dma_act_ec_siat, :dma_itemcode_siat)";


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
				':dma_firm_code' => isset($Data['dma_firm_code']) ? $Data['dma_firm_code'] : '',
				':dma_series_code' => isset($Data['dma_series_code']) ? $Data['dma_series_code'] : NULL,
				':dma_sup_set' => isset($Data['dma_sup_set']) ? $Data['dma_sup_set'] : '',
				':dma_sku_sup' => isset($Data['dma_sku_sup']) ? $Data['dma_sku_sup'] : '',
				':dma_uom_purch' => $unidad == 0 && is_numeric($Data['dma_uom_purch']) ? $Data['dma_uom_purch'] : $unidad,
				':dma_uom_pqty' => $cantidad1 == 0 && is_numeric($Data['dma_uom_pqty']) ? $Data['dma_uom_pqty'] : $cantidad1,
				':dma_uom_pemb' => $cantidad1 == 0 && is_numeric($Data['dma_uom_pemb']) ? $Data['dma_uom_pemb'] : $cantidad1,
				':dma_uom_pembqty' => $cantidad2 == 0 && is_numeric($Data['dma_uom_pembqty']) ? $Data['dma_uom_pembqty']  : $cantidad2,
				':dma_tax_purch' => is_numeric($Data['dma_tax_purch']) ? $Data['dma_tax_purch'] : 0,
				':dma_price_list' => isset($Data['dma_price_list']) ? $Data['dma_price_list'] : 0,
				':dma_price' => is_numeric($Data['dma_price']) ? $Data['dma_price'] : 0,
				':dma_uom_sale' => $unidad == 0 &&  is_numeric($Data['dma_uom_sale']) ? $Data['dma_uom_sale'] : $unidad,
				':dma_uom_sqty' => $cantidad1 == 0 &&  is_numeric($Data['dma_uom_sqty']) ? $Data['dma_uom_sqty'] : $cantidad1,
				':dma_uom_semb' => $cantidad1 == 0 &&  is_numeric($Data['dma_uom_semb']) ? $Data['dma_uom_semb'] : $cantidad1,
				':dma_uom_embqty' => $cantidad2 == 0 &&  is_numeric($Data['dma_uom_embqty']) ? $Data['dma_uom_embqty'] : $cantidad2,
				':dma_tax_sales' => is_numeric($Data['dma_tax_sales']) ? $Data['dma_tax_sales'] : 0,
				':dma_acct_type' => is_numeric($Data['dma_acct_type']) ? $Data['dma_acct_type'] : 0,
				':dma_avprice' => is_numeric($Data['dma_avprice']) ? $Data['dma_avprice'] : 0,
				':dma_uom_weight' => is_numeric($Data['dma_uom_weight']) ? $Data['dma_uom_weight'] : 0,
				':dma_uom_umvol'    => isset($Data['dma_uom_umvol']) && is_numeric($Data['dma_uom_umvol']) ? $Data['dma_uom_umvol'] : 0,
				':dma_uom_vqty'     => isset($Data['dma_uom_vqty']) && is_numeric($Data['dma_uom_vqty']) ? $Data['dma_uom_vqty'] : 0,
				':dma_uom_weightn'  => isset($Data['dma_uom_weightn']) && is_numeric($Data['dma_uom_weightn']) ? $Data['dma_uom_weightn'] : 0,
				':dma_uom_sizedim'  => isset($Data['dma_uom_sizedim']) ? $Data['dma_uom_sizedim'] : 0,
				':dma_lotes_code' => isset($Data['dma_lotes_code']) ? $Data['dma_lotes_code'] : '0',
				':dma_emisionmethod' => isset($Data['dma_emisionmethod']) ? $Data['dma_emisionmethod'] : 0,
				':dma_long_description' => isset($Data['dma_long_description']) ? $Data['dma_long_description'] : NULL,
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
				':dma_uom_width' => isset($Data['dma_uom_width']) && is_numeric($Data['dma_uom_width']) ? $Data['dma_uom_width'] : 0,
				':dma_uom_tall' => isset($Data['dma_uom_tall']) && is_numeric($Data['dma_uom_tall']) ? $Data['dma_uom_tall'] : 0,
				':dma_uom_length' => isset($Data['dma_uom_length']) && is_numeric($Data['dma_uom_length']) ? $Data['dma_uom_length'] : 0,
				':dma_uom_vol' => isset($Data['dma_uom_vol']) && is_numeric($Data['dma_uom_vol']) ? $Data['dma_uom_vol'] : 0,
				':dma_um_inventory' => $unidad == 0 && is_numeric($Data['dma_um_inventory']) ? $Data['dma_um_inventory'] : $unidad,
				//CODIGO DE IMPUESTOS
				':dma_tax_sales_code' => isset($Data['dma_tax_sales_code']) ? $Data['dma_tax_sales_code'] : NULL,
				':dma_tax_purch_code' => isset($Data['dma_tax_purch_code']) ? $Data['dma_tax_purch_code'] : NULL,
				//CUENTA INVENTARIO EN PROCESO
				':dma_acct_invproc' => isset($Data['dma_acct_invproc']) && is_numeric($Data['dma_acct_invproc']) ? $Data['dma_acct_invproc'] : 0,
				':dma_modular'  => isset($Data['dma_modular']) && is_numeric($Data['dma_modular']) ? $Data['dma_modular'] : 0,
				':dma_advertisement'  => isset($Data['dma_advertisement']) && is_numeric($Data['dma_advertisement']) ? $Data['dma_advertisement'] : 0,
				':dma_subscription'  => isset($Data['dma_subscription']) && is_numeric($Data['dma_subscription']) ? $Data['dma_subscription'] : 0,
				// TASA DE IMPUESTOS
				':dma_use_tbase' => isset($Data['dma_use_tbase']) ? $Data['dma_use_tbase'] : 0,
				':dma_tasa_base' => isset($Data['dma_tasa_base']) ? $Data['dma_tasa_base'] : 0,
				//CAMPO PARA ALMACENAR SI ES ARTICULO DE ACTIVO FIJO
				':dma_type_art' => is_numeric($Data['dma_type_art']) ? $Data['dma_type_art'] : 0,
				//
				':dma_serial_number' => is_numeric($Data['dma_serial_number']) ? $Data['dma_serial_number'] : 0,
				//CUENTAS DEDUCIBLES
				':deductible_spent' => isset($Data['deductible_spent']) ? $Data['deductible_spent'] : 0,
				':taxable_income' => isset($Data['taxable_income']) ? $Data['taxable_income'] : 0,
				':dma_asset' => isset($Data['dma_asset']) && is_numeric($Data['dma_asset']) ? $Data['dma_asset'] : 0,
				':dma_clean' => isset($Data['dma_clean']) && is_numeric($Data['dma_clean']) ? $Data['dma_clean'] : 0,
				// MULTIPLE IMPUESTO
				':dma_multipletax' => is_numeric($Data['dma_multipletax']) ? $Data['dma_multipletax'] : 0,
				':dma_multipletax_purchcode' => isset($Data['dma_multipletax_purchcode']) ? $Data['dma_multipletax_purchcode'] : 0,
				':dma_multipletax_salescode' => isset($Data['dma_multipletax_salescode']) ? $Data['dma_multipletax_salescode'] : 0,

				':dma_item_asset' => isset($Data['dma_item_asset']) ? $Data['dma_item_asset'] : NULL,
				// 
				':dma_last_cardcode' =>  (isset($Data['dma_last_cardcode'])) ? $Data['dma_last_cardcode'] : NULL,
				':dma_last_purchase_date' =>  (isset($Data['dma_last_purchase_date']) && !empty($Data['dma_last_purchase_date'])) ? $Data['dma_last_purchase_date'] : NULL,
				':dma_wscode' =>  (isset($Data['dma_wscode']) && !empty($Data['dma_wscode']) )? $Data['dma_wscode'] : NULL,
				':dma_ubication' =>  (isset($Data['dma_ubication']) && !empty($Data['dma_ubication'])) ? $Data['dma_ubication'] : NULL,
				':dma_old_code' => (isset($Data['dma_old_code']) && !empty($Data['dma_old_code'])) ? $Data['dma_old_code'] : NULL ,
				// 
				':dma_act_ec_siat' =>  (isset($Data['dma_act_ec_siat']) && !empty($Data['dma_act_ec_siat'])) ? $Data['dma_ubication'] : NULL,
				':dma_itemcode_siat' => (isset($Data['dma_itemcode_siat']) && !empty($Data['dma_itemcode_siat'])) ? $Data['dma_old_code'] : NULL 
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

		// VALIDACION NUMERO DE SERIE FABRICANTE
		if (isset($Data['dma_serial_number']) && !empty($Data['dma_serial_number']) && $Data['dma_serial_number'] != 0){
			$sqlSerie = "SELECT dma_serial_number, dma_item_code FROM dmar WHERE UPPER(trim(dma_serial_number)) = UPPER(trim(:dma_serial_number)) AND dma_id != :dma_id";

			$resSerie = $this->pedeo->queryTable($sqlSerie, array(
	
				':dma_serial_number' => $Data['dma_serial_number'],
				':dma_id' => $Data['dma_id'],
	
			));
	
			if (isset($resSerie[0])) {
	
				$respuesta = array(
					'error' => true,
					'data'  => $resSerie,
					'mensaje' => 'ya existe un artículo con ese código de fabrica  '. $resSerie[0]['dma_serial_number']
				);
	
				$this->response($respuesta);
	
				return;
			}
		}



		$this->pedeo->trans_begin();

		try {
			$sqlUpdate = "UPDATE dmar
						SET dma_item_name = :dma_item_name, dma_generic_name = :dma_generic_name,
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
						dma_tax_sales_code = :dma_tax_sales_code, dma_tax_purch_code = :dma_tax_purch_code,dma_acct_invproc = :dma_acct_invproc, dma_modular = :dma_modular, dma_advertisement = :dma_advertisement,
						dma_subscription = :dma_subscription, dma_use_tbase = :dma_use_tbase, dma_tasa_base = :dma_tasa_base, dma_type_art = :dma_type_art, dma_serial_number = :dma_serial_number,
						deductible_spent = :deductible_spent,taxable_income = :taxable_income,
						dma_asset = :dma_asset , dma_clean = :dma_clean, dma_multipletax =:dma_multipletax, dma_multipletax_purchcode =:dma_multipletax_purchcode,
						dma_multipletax_salescode = :dma_multipletax_salescode, dma_item_asset = :dma_item_asset,
						dma_last_cardcode = :dma_last_cardcode , dma_last_purchase_date = :dma_last_purchase_date ,
						dma_wscode = :dma_wscode , dma_ubication = :dma_ubication ,	dma_old_code = :dma_old_code,
						dma_act_ec_siat = :dma_act_ec_siat, dma_itemcode_siat = :dma_itemcode_siat
						WHERE dma_id = :dma_id";

			$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
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
				':dma_uom_purch' => isset($Data['dma_uom_purch']) && is_numeric($Data['dma_uom_purch']) ? $Data['dma_uom_purch'] : 0,
				':dma_uom_pqty' => isset($Data['dma_uom_pqty']) && is_numeric($Data['dma_uom_pqty']) ? $Data['dma_uom_pqty'] : 0,
				':dma_uom_pemb' => isset($Data['dma_uom_pemb']) && is_numeric($Data['dma_uom_pemb']) ? $Data['dma_uom_pemb'] : 0,
				':dma_uom_pembqty' => is_numeric($Data['dma_uom_pembqty']) ? $Data['dma_uom_pembqty'] : 0,
				':dma_tax_purch' => is_numeric($Data['dma_tax_purch']) ? $Data['dma_tax_purch'] : 0,
				':dma_price_list' => isset($Data['dma_price_list']) ? $Data['dma_price_list'] : 0,
				':dma_price' => is_numeric($Data['dma_price']) ? $Data['dma_price'] : 0,
				':dma_uom_sale' => isset($Data['dma_uom_sale']) && is_numeric($Data['dma_uom_sale']) ? $Data['dma_uom_sale'] : 0,
				':dma_uom_sqty' => isset($Data['dma_uom_sqty']) &&  is_numeric($Data['dma_uom_sqty']) ? $Data['dma_uom_sqty'] : 0,
				':dma_uom_semb' => isset($Data['dma_uom_semb']) && is_numeric($Data['dma_uom_semb']) ? $Data['dma_uom_semb'] : 0,
				':dma_uom_embqty' => is_numeric($Data['dma_uom_embqty']) ? $Data['dma_uom_embqty'] : 0,
				':dma_tax_sales' => is_numeric($Data['dma_tax_sales']) ? $Data['dma_tax_sales'] : 0,
				':dma_acct_type' => is_numeric($Data['dma_acct_type']) ? $Data['dma_acct_type'] : 0,
				':dma_avprice' => is_numeric($Data['dma_avprice']) ? $Data['dma_avprice'] : 0,
				':dma_uom_weight' => is_numeric($Data['dma_uom_weight']) ? $Data['dma_uom_weight'] : 0,
				':dma_uom_umvol'    => isset($Data['dma_uom_umvol']) && is_numeric($Data['dma_uom_umvol']) ? $Data['dma_uom_umvol'] : 0,
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
				':dma_uom_width' => isset($Data['dma_uom_width']) && is_numeric($Data['dma_uom_width']) ? $Data['dma_uom_width'] : 0,
				':dma_uom_tall' => isset($Data['dma_uom_tall']) && is_numeric($Data['dma_uom_tall']) ? $Data['dma_uom_tall'] : 0,
				':dma_uom_length' => isset($Data['dma_uom_length']) && is_numeric($Data['dma_uom_length']) ? $Data['dma_uom_length'] : 0,
				':dma_uom_vol' => isset($Data['dma_uom_vol']) && is_numeric($Data['dma_uom_vol']) ? $Data['dma_uom_vol'] : 0,
				//CODIGOS DE IMPUESTOS
				':dma_tax_purch_code' => isset($Data['dma_tax_purch_code']) ? $Data['dma_tax_purch_code'] : NULL,
				':dma_tax_sales_code' => isset($Data['dma_tax_sales_code']) ? $Data['dma_tax_sales_code'] : NULL,
				//CUENTA DE INVENTARIO EN PROCESO
				':dma_acct_invproc' => isset($Data['dma_acct_invproc']) ? $Data['dma_acct_invproc'] : NULL,
				':dma_modular'  => isset($Data['dma_modular']) && is_numeric($Data['dma_modular']) ? $Data['dma_modular'] : 0,
				':dma_advertisement'  => isset($Data['dma_advertisement']) && is_numeric($Data['dma_advertisement']) ? $Data['dma_advertisement'] : 0,
				':dma_subscription'  => isset($Data['dma_subscription']) && is_numeric($Data['dma_subscription']) ? $Data['dma_subscription'] : 0,
				// TASA DE IMPUESTOS
				':dma_use_tbase' => isset($Data['dma_use_tbase']) ? $Data['dma_use_tbase'] : 0,
				':dma_tasa_base' => isset($Data['dma_tasa_base']) ? $Data['dma_tasa_base'] : 0,
				//SI ES ACTIVO FIJO
				':dma_type_art' => is_numeric($Data['dma_type_art']) ? $Data['dma_type_art'] : 0,
				//
				':dma_serial_number' => is_numeric($Data['dma_serial_number']) ? $Data['dma_serial_number'] : 0,
				//CUENTAS DEDUCIBLES
				':deductible_spent' =>  isset($Data['deductible_spent']) ? $Data['deductible_spent'] : 0,
				':taxable_income' =>  isset($Data['taxable_income']) ? $Data['taxable_income'] : 0,
				':dma_asset' => isset($Data['dma_asset']) && is_numeric($Data['dma_asset']) ? $Data['dma_asset'] : 0,
				':dma_clean' => isset($Data['dma_clean']) && is_numeric($Data['dma_clean']) ? $Data['dma_clean'] : 0,
				// MULTIPLE IMPUESTO
				':dma_multipletax' => is_numeric($Data['dma_multipletax']) ? $Data['dma_multipletax'] : 0,
				':dma_multipletax_purchcode' => isset($Data['dma_multipletax_purchcode']) ? $Data['dma_multipletax_purchcode'] : 0,
				':dma_multipletax_salescode' => isset($Data['dma_multipletax_salescode']) ? $Data['dma_multipletax_salescode'] : 0,

				':dma_item_asset' => isset($Data['dma_item_asset']) ? $Data['dma_item_asset'] : NULL,
				// 
				':dma_last_cardcode' =>  (isset($Data['dma_last_cardcode'])) ? $Data['dma_last_cardcode'] : NULL,
				':dma_last_purchase_date' =>  (isset($Data['dma_last_purchase_date']) && !empty($Data['dma_last_purchase_date'])) ? $Data['dma_last_purchase_date'] : NULL,
				':dma_wscode' =>  (isset($Data['dma_wscode']) && !empty($Data['dma_wscode']) )? $Data['dma_wscode'] : NULL,
				':dma_ubication' =>  (isset($Data['dma_ubication']) && !empty($Data['dma_ubication'])) ? $Data['dma_ubication'] : NULL,
				':dma_old_code' => (isset($Data['dma_old_code']) && !empty($Data['dma_old_code'])) ? $Data['dma_old_code'] : NULL ,
				// 
				':dma_act_ec_siat' =>  (isset($Data['dma_act_ec_siat']) && !empty($Data['dma_act_ec_siat'])) ? $Data['dma_ubication'] : NULL,
				':dma_itemcode_siat' => (isset($Data['dma_itemcode_siat']) && !empty($Data['dma_itemcode_siat'])) ? $Data['dma_old_code'] : NULL 
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

		if(isset($request['is_asset']) &&  !empty($request['is_asset']) ){
			$variableSql .= " AND t0.dma_item_asset = '1'";
		}
		// OBTENER NÚMERO DE REGISTROS DE LA TABLA.
		$numRows = $this->pedeo->queryTable("select get_numrows('dmar') as numrows", []);
		// COLUMNAS DEL DATATABLE
		$columns = array(
			'cast(t0.dma_id as varchar)',
			't0.dma_item_code',
			'UPPER(t0.dma_item_name)',
			't2.mga_name',
			'cast(t0.dma_enabled as varchar)'
		);
		//
		if (!empty($request['search']['value'])) {
			// OBTENER CONDICIONALES.
			$variableSql .= " AND  " . self::get_Filter($columns, strtoupper($request['search']['value']));
		}
		
		//
		$sqlSelect = "SELECT t0.*, t2.mga_name FROM dmar t0 LEFT JOIN dmga t2 on t0.dma_group_code = t2.mga_id $variableSql";
		//
		$sqlSelect .=" ORDER BY ".$columns[$request['order'][0]['column']]." ".$request['order'][0]['dir']." LIMIT ".$request['length']." OFFSET ".$request['start'];
		// print_r($sqlSelect);exit;
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

		if (!isset($Data['item_code']) or
			!isset($Data['business']) or empty($Data['business'])){

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT
						dmar.dma_item_code,
						dmws.dws_name,
						dmws.business,
						dmum.dmu_nameum,
						dmws.dws_name,
						sum(bdi_quantity) total
					from dmar
					inner join dmum on dmar.dma_uom_sale = dmum.dmu_id
					inner join tbdi on dmar.dma_item_code = tbdi.bdi_itemcode
					inner join dmws on tbdi.bdi_whscode = dmws.dws_code and dmws.business = :business
					where dmar.dma_item_code = :dma_item_code and tbdi.business = :business
					group by dmar.dma_item_code,dmum.dmu_nameum,dmws.dws_name,dmws.business
					order by dmar.dma_item_code";

		
		$resSelect = $this->pedeo->queryTable($sqlSelect, array(
			':dma_item_code' => $Data['item_code'],
			':business' => $Data['business']
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

	// BUSCA CUENTAS CONTABLES DEL ARTICULO SEGUN LA CONFIGURACION
	// ESTABLECIDA EN SU CREACION:
	// ARTICULO, GRUPO DE ARTICULO, ALMACEN
	public function getAcounting_post(){

		$Data = $this->post();

		if (!isset($Data['itemcode']) || !isset($Data['wshcode'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$respuesta = $this->account->getAccountItem($Data['itemcode'], $Data['wshcode']);

		$this->response($respuesta);

	}

	// BUSCA LAS CANTIDADES MAXIMAS Y MINIMAS PARA UN ITEM DENTRO DE UN ALMACEN 
	public function getStockMM_post() {

		$Data = $this->post();

		if (!isset($Data['business']) || !isset($Data['itemcode'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT dws_id, dws_code, concat(dws_code,' - ',dws_name) as dws_name,
						coalesce(smm_min, 0) as smm_min, coalesce(smm_max, 0) as smm_max
						FROM dmws 
						LEFT JOIN tsmm
						ON dmws.dws_code = tsmm.smm_store
						AND tsmm.smm_itemcode = :smm_itemcode
						WHERE dmws.business = :business
						ORDER BY dws_name asc";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(
			':smm_itemcode' => $Data['itemcode'],
			':business' 	=> $Data['business']
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

	public function getItemBySC_get() {

		$Data = $this->get();

		if ( !isset($Data['code']) OR !isset($Data['type'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$complemento = "";

		switch($Data['type']){
			case 1:
				$complemento = " AND dma_item_sales = :vc";
				break;
			case 2:
				$complemento = " AND dma_item_purch = :vc";
				break;
		}


		$sql1 = "SELECT * FROM dmar WHERE 1=1 ".$complemento."  AND trim(dma_item_code) = trim(:dma_item_code)";

		$res1 = $this->pedeo->queryTable($sql1, array(
			':vc' => '1',
			':dma_item_code' => $Data['code']
		));

		if ( isset($res1[0]) ){

			$respuesta = array(
				'error'   => false,
				'data'    => $res1,
				'mensaje' => ''
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}else{

			$sql2 = "SELECT * FROM dmar WHERE 1=1 ".$complemento."  AND trim(dma_serial_number) = trim(:dma_serial_number)";

			$res2 = $this->pedeo->queryTable($sql2, array(
				':vc' => '1',
				':dma_serial_number' => $Data['code']
			));

			if ( isset($res2[0]) ) {

				$respuesta = array(
					'error'   => false,
					'data'    => $res2,
					'mensaje' => ''
				);
	
				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
	
				return;
			}else{

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'Busquda sin resultaos'
				);
			}
		}



		$this->response($respuesta);
	}

	// RELACION DE SERVICIO Y ARTICULOS
	// SOLO 
	public function setItemR_post(){

		$Data = $this->post();

		if (!isset($Data['rsa_itemcode']) || !isset($Data['detail'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$Contenido = $Data['detail'];


		if ( !is_array($Contenido) || count($Contenido) == 0 ){

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontraron los articulos relacionados'
			);

			$this->response($respuesta);

			return;
		}

		$sqlInsert = "INSERT INTO trsa(rsa_itemcode,rsa_itemcode2)VALUES(:rsa_itemcode,:rsa_itemcode2)";

		$this->pedeo->trans_begin();

		$this->pedeo->deleteRow("DELETE FROM trsa WHERE rsa_itemcode = :rsa_itemcode", array(":rsa_itemcode" => $Data['rsa_itemcode']));

		foreach ($Contenido as $key => $item) {

			$resInsert = $this->pedeo->insertRow($sqlInsert, array(

				':rsa_itemcode' => $Data['rsa_itemcode'],
				':rsa_itemcode2' => $item
			));


			if (is_numeric($resInsert) && $resInsert > 0 ){

			}else{

				$this->pedeo->trans_rollback();
				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'No se pudo insertar la relación'
				);
			}
		}


		$this->pedeo->trans_commit();

		$respuesta = array(
			'error' => false,
			'data' => $resInsert,
			'mensaje' => 'Relación registrada con exito'
		);


		$this->response($respuesta);

	}
	// LISTAR RELACION DE SERVICIOS Y ARTICULOS	
	public function getItemR_post() {

		$Data = $this->post();

		$sqlSelect = "SELECT * FROM trsa WHERE rsa_itemcode = :rsa_itemcode";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(
			':rsa_itemcode' => $Data['rsa_itemcode']
		));


		if ( isset($resSelect[0])){

			$respuesta = array(
				'error' => false,
				'data' => $resSelect,
				'mensaje' => ''
			);

		}else{
			$respuesta = array(
				'error' => true,
				'data' => [],
				'mensaje' => 'Sin resultados'
			);
		}

	


		$this->response($respuesta);
	}

	// OBTENER LOS 10 ULTIMOS PRECIOS DE COMPRA PARA UN ARTICULO
	public function getItemsPrice_get()
	{

		$Data = $this->get();


		$sqlSelect = "SELECT cfc_cardcode AS codigo_proveedor, 
					cfc_cardname AS nombre_proveedor, 
					cfc_docnum AS numero_documento, 
					fc1_price AS precio, 
					cfc_duedev AS fecha_documento
					FROM dcfc
					INNER JOIN cfc1 ON fc1_docentry = cfc_docentry
					WHERE fc1_itemcode = :fc1_itemcode 
					AND dcfc.business = :business
					ORDER BY cfc_docentry DESC LIMIT 10";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(
			':fc1_itemcode' => $Data['fc1_itemcode'],
			':business' 	=> $Data['business']
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

	// GUARDAR PRECIOS CON ARTICULOS DE TERCEROS
	public function setPitemsExt_post() {


		$respuesta = array(
			'error'   => true,
			'data' => array(),
			'mensaje'	=> 'Error al subir el archivo de excel'
		);


		// Verificar si se subió el archivo
		if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
			$nombreTmp = $_FILES['archivo']['tmp_name'];
			$nombreArchivo = $_FILES['archivo']['name'];
			$extension = pathinfo($nombreArchivo, PATHINFO_EXTENSION);

			// Verificar que el archivo tenga una extensión válida
			if (in_array($extension, ['xls', 'xlsx'])) {
				// Procesar el archivo
				// importarExcelABaseDeDatos($nombreTmp, $conexion);

				// Cargar el archivo Excel
				$spreadsheet = IOFactory::load($nombreTmp);
				$sheet = $spreadsheet->getActiveSheet();
				$datos = $sheet->toArray(null, true, true, true);
				
				//
				$this->pedeo->trans_begin();
				//
				$this->pedeo->queryTable('truncate table tblp', array());
				// Iterar sobre las filas del Excel
				foreach ($datos as $key => $fila) {
					// Omitir la primera fila si es un encabezado
					if ($key == 1) continue;
		
					// Obtener los valores de las columnas
					$codigo = $fila['A'];
					$nombre = $fila['B'];
					$precio = $fila['C'];
		
					// Validar datos antes de insertarlos
					if (!empty($codigo) && !empty($nombre) && is_numeric($precio)) {
						// Insertar en la tabla
						$sql = "INSERT INTO tblp (blp_itemcode, blp_itemname, blp_price, blp_session) VALUES (:blp_itemcode, :blp_itemname, :blp_price, :blp_session)";
						
						$resp = $this->pedeo->insertRow($sql, array(
							':blp_itemcode' => $codigo, 
							':blp_itemname' => $nombre, 
							':blp_price'  	=> $precio,  
							':blp_session'  => isset($Data['sesion']) && !empty($Data['sesion']) ? $Data['sesion'] : 0
						));

						if (is_numeric($resp) && $resp > 0){

						}else{

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data'    => $resp,
								'mensaje' => 'Error al insertar el item: '.$codigo
							);

							return $this->response($respuesta);
						}
					}
				}

				$this->pedeo->trans_commit();
		
				$respuesta = array(
					'error'   => false,
					'data'    => [],
					'mensaje' => 'proceso finalizado con exito'
				);

				return $this->response($respuesta);

			} else {

				$respuesta = array(
					'error'   => true,
					'data'    => [],
					'mensaje' => 'Formato de archivo no válido. Solo se aceptan archivos .xls o .xlsx'
				);

				return $this->response($respuesta);
			}
		} else {
			return $this->response($respuesta);
		}

	}

}