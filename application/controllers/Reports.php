<?php
// REPORTES DE INVENTARIO
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'/libraries/REST_Controller.php');
require_once(APPPATH.'/asset/vendor/autoload.php');
use Restserver\libraries\REST_Controller;

class Reports extends REST_Controller {

	private $pdo;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
    	$this->load->library('pedeo', [$this->pdo]);
		$this->load->library('generic');
		$this->load->library('AccountStatus');

	}

  //INFORME AUDITORIA STOCK
	public function getAuditoriaStock_post(){

      	$request = $this->post();

		  if (!isset($request['business']) or empty($request['business'])){

		  $respuesta = array(
			  'error' => true,
			  'data'  => array(),
			  'mensaje' => 'La informacion enviada no es valida'
		  );

		  $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

		  return;
	  }
	  	$where = [];
      	$sql = '';

		// ID ARTICULO.
		if (!empty($request['fil_acticuloId'])) {
			//
			$where[':fil_acticuloId'] = $request['fil_acticuloId'];
			// CONDICIÓN SQL.
			$sql .= " AND tbmi.bmi_itemcode = :fil_acticuloId";
		}
		// ID ALMACEN.
		if (!empty($request['fil_almacenId'])) {
			//
			$where[':fil_almacenId'] = $request['fil_almacenId'];
			// CONDICIÓN SQL.
			$sql .= " AND dmws.dws_code = :fil_almacenId";
		}
		// FECHA INICIO Y FIN.
		if (!empty($request['flt_dateint']) && !empty($request['flt_dateend'])) {
			// CONDICIÓN SQL.
			if( isset($request['flt_dateby']) && $request['flt_dateby'] == 1 ){ // SEGUN FECHA DE CONTABILIZACION
				$sql .= " AND DATE(tbmi.bmi_docdate) BETWEEN '".$request['flt_dateint']."' AND '".$request['flt_dateend']."'";

			}else if( isset($request['flt_dateby']) && $request['flt_dateby'] == 2 ){ // SEGUN FECHA DE VENCIMIENTO
				$sql .= " AND DATE(tbmi.bmi_duedate) BETWEEN '".$request['flt_dateint']."' AND '".$request['flt_dateend']."'";

			}else	if( isset($request['flt_dateby']) && $request['flt_dateby'] == 3 ){ // SEGUN FECHA DE DOCUMENTO
				$sql .= " AND DATE(tbmi.bmi_duedev) BETWEEN '".$request['flt_dateint']."' AND '".$request['flt_dateend']."'";

			}else{
				// SEGUN FECHA DE CREACION
				$sql .= " AND DATE(tbmi.bmi_createat) BETWEEN '".$request['flt_dateint']."' AND '".$request['flt_dateend']."'";
			}

		}

		$sqlAnalitic = "SELECT distinct
						tbmi.bmi_itemcode AS codigoarticulo,
						dmar.dma_item_name AS nombrearticulo,
						tbmi.bmi_ubication as ubicacion,
						tbdi.bdi_whscode AS codigoalmacen,
						dmws.dws_name AS nombrealmacen,
						dmdt.mdt_docname AS docorigen,
						tbmi.bmi_basenum docnum,
						cast(tbmi.bmi_createat as date) AS fechadocnum,
						trim(to_char(tbmi.bmi_quantity, '999G999G999G999G999D'||lpad('9',get_decimals(),'9'))) AS cantidadmovida,
						get_localcur()||' '||trim(to_char( tbmi.bmi_cost,'999G999G999G999G999D'||lpad('9',get_decimals(),'9') )) AS costo,
						trim(to_char(tbmi.bmi_currequantity + tbmi.bmi_quantity,'999G999G999G999G999D'||lpad('9',get_decimals(),'9') )) AS cantidadrestante,
						trim(to_char(tbmi.bmi_currequantity, '999G999G999G999G999D'||lpad('9',get_decimals(),'9'))) AS cantidadantesdemovimiento,
						get_localcur()||' '||trim(to_char( (tbmi.bmi_cost * (tbmi.bmi_quantity + tbmi.bmi_currequantity)), '999G999G999G999G999D'||lpad('9',get_decimals(),'9'))) costoacumulado,
						tbmi.bmi_createby AS creadopor,tbmi.bmi_docdate AS fechadoc,tbmi.bmi_comment AS comentario
						FROM tbmi
						INNER JOIN tbdi ON tbmi.bmi_itemcode = tbdi.bdi_itemcode AND tbmi.bmi_whscode  = tbdi.bdi_whscode
						INNER JOIN dmar ON tbmi.bmi_itemcode = dmar.dma_item_code
						INNER JOIN dmdt ON tbmi.bmy_doctype = dmdt.mdt_doctype
						INNER JOIN dmws ON tbmi.bmi_whscode = dmws.dws_code 
						WHERE 1=1 ".$sql." 
						ORDER BY cast(tbmi.bmi_createat as date) DESC";

		$result = $this->pedeo->queryTable($sqlAnalitic,$where);
		
		if(isset($result[0])){

			$respuesta = array(

				'error'   => false,
				'data'    => $result,
				'mensaje' =>''
			);

		}else{

			$respuesta = array(
				'error' => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);

		}

      	$this->response($respuesta);
	}

	//INFORME ANALISIS DE VENTAS
	public function getAnalisisVenta_post(){

			$Data = $this->post();

			if(!isset($Data['business']) or empty($Data['business'])){

				$respuesta = array(
					'error'   => true,
					'data' => [],
					'mensaje'	=> 'Falta parametro de empresa'
				);
				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
				return;
			}

			$sql = 'SELECT
						T3.MDT_DOCNAME "TipoDocumento",
						T0.DVF_DOCNUM "NumeroFactura",
						T0.DVF_CARDCODE "CodigoCLiente",
						T0.DVF_CARDNAME "NombreCliente",
						T0.DVF_DOCDATE "FechaFactura",
						T0.DVF_BASEAMNT "BaseFactura",
						ROUND((T0.DVF_BASEAMNT * 19) / 100) "IvaFactura",
						COALESCE(T0.DVF_PAYTODAY,0) "ValorRecaudado",
						T2.MGS_NAME "GrupoCliente"
					FROM DVFV T0
					LEFT JOIN DMSN T1 ON T0.DVF_CARDCODE = T1.DMS_CARD_CODE
					LEFT JOIN DMGS T2 ON T1.DMS_GROUP_NUM = CAST(T2.MGS_ID AS VARCHAR)
					LEFT JOIN DMDT T3 ON T0.DVF_DOCTYPE = T3.MDT_DOCTYPE
					LEFT JOIN TBMI T4 ON T0.DVF_DOCTYPE = T4.BMY_DOCTYPE and t0.DVF_DOCENTRY = T4.BMY_BASEENTRY
					WHERE T0.BUSINESS = :BUSINESS';


			$result = $this->pedeo->queryTable($sql, array(
				':BUSINESS' => $Data['business']
			));

			if(isset($result[0])){

				 $respuesta = array(
						'error'   => false,
						'data'    => $result,
						'mensaje' =>''
				 );

			}else{

				$respuesta = array(
					'error'   => true,
					'data' => [],
					'mensaje'	=> 'busqueda sin resultados'
				);

			}

			$this->response($respuesta);
	}

	//INFORME ANALISIS DE VENTAS DETALLADO
	public function getAnalisisVentaDetallado_post(){

			$Data = $this->post();

			if(!isset($Data['business']) or empty($Data['business'])){

				$respuesta = array(
					'error'   => true,
					'data' => [],
					'mensaje'	=> 'Falta parametro de empresa'
				);
				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
				return;
			}
			
			$sql = 'SELECT
								T0.DVF_CARDCODE "CodigoCliente",
								T0.DVF_CARDNAME "NombreCliente",
								T0.DVF_DOCNUM "NumeroFactura",
								T0.DVF_DOCDATE "FechaFactura",
								T1.FV1_ITEMCODE "CodigoArticulo",
								T1.FV1_ITEMNAME "NombreArticulo",
								T1.FV1_WHSCODE "CodigoAlmacen",
								T2.DWS_NAME "NombreAlmacen",
								T1.FV1_QUANTITY "Cantidad",
								T1.FV1_PRICE "PrecioUnitario",
								T1.FV1_VATSUM "IvaUnitario",
								T1.FV1_DISCOUNT "DescuentoUnitario",
								T1.FV1_LINETOTAL "TotalUnitario",
								T1.FV1_UBUSINESS "UnidadNegocio",
								T1.FV1_PROJECT "Proyecto",
								T1.FV1_AVPRICE "Null"
							FROM DVFV T0
							LEFT JOIN VFV1 T1 ON T0.DVF_DOCENTRY = T1.FV1_DOCENTRY
							LEFT JOIN DMWS T2 ON T1.FV1_WHSCODE = T2.DWS_CODE
							WHERE T0.BUSINESS = :BUSINESS';


			$result = $this->pedeo->queryTable($sql, array(
				':BUSINESS' => $Data['business']
			));

			if(isset($result[0])){

				 $respuesta = array(
						'error'   => false,
						'data'    => $result,
						'mensaje' =>''
				 );

			}else{

				$respuesta = array(
					'error'   => true,
					'data' => array(),
					'mensaje'	=> 'busqueda sin resultados'
				);

			}

			$this->response($respuesta);
	}

	//INFORME ANALISIS DE COMPRAS
	public function getAnalisisCompra_post(){

			$Data = $this->post();
			if(!isset($Data['business']) or empty($Data['business'])){

				$respuesta = array(
					'error'   => true,
					'data' => [],
					'mensaje'	=> 'Falta parametro de empresa'
				);
				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
				return;
			}

			$sql = 'SELECT
							T3.MDT_DOCNAME "TipoDocumento",
							T0.cpo_DOCNUM "NumeroFactura",
							T0.cpo_CARDCODE "CodigoCLiente",
							T0.cpo_CARDNAME "NombreCliente",
							T0.cpo_DOCDATE "FechaFactura",
							T0.cpo_BASEAMNT "BaseFactura",
							ROUND((T0.cpo_BASEAMNT * 19) / 100) "IvaFactura",
							COALESCE(T0.cpo_PAYTODAY,0) "ValorRecaudado",
							T2.MGS_NAME "GrupoCliente"
						FROM DCPO T0
						LEFT JOIN DMSN T1 ON T0.cpo_CARDCODE = T1.DMS_CARD_CODE
						LEFT JOIN DMGS T2 ON T1.DMS_GROUP_NUM = CAST(T2.MGS_ID AS VARCHAR)
						LEFT JOIN DMDT T3 ON T0.cpo_DOCTYPE = T3.MDT_DOCTYPE
						LEFT JOIN TBMI T4 ON T0.cpo_DOCTYPE = T4.BMY_DOCTYPE and t0.DPO_DOCENTRY = T4.BMY_BASEENTRY
						where t0.business = :business';


			$result = $this->pedeo->queryTable($sql, array(
				':business' => $Data['business']
			));

			if(isset($result[0])){

				 $respuesta = array(
						'error'   => false,
						'data'    => $result,
						'mensaje' =>''
				 );

			}else{

				$respuesta = array(
					'error'   => true,
					'data' => [],
					'mensaje'	=> 'busqueda sin resultados'
				);

			}

			$this->response($respuesta);
	}

	//INFORME ANALISIS DE COMPRAS DETALLADO
	public function getAnalisisCompraDetallado_post(){

			$Data = $this->post();

			if(!isset($Data['business']) or empty($Data['business'])){

				$respuesta = array(
					'error'   => true,
					'data' => [],
					'mensaje'	=> 'Falta parametro de empresa'
				);
				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
				return;
			}

			$sql = 'SELECT
							T0.DVF_CARDCODE "CodigoCliente",
							T0.DVF_CARDNAME "NombreCliente",
							T0.DVF_DOCNUM "NumeroFactura",
							T0.DVF_DOCDATE "FechaFactura",
							T1.PO1_ITEMCODE "CodigoArticulo",
							T1.PO1_ITEMNAME "NombreArticulo",
							T1.PO1_WHSCODE "CodigoAlmacen",
							T2.DWS_NAME "NombreAlmacen",
							T1.PO1_QUANTITY "Cantidad",
							T1.PO1_PRICE "PrecioUnitario",
							T1.PO1_VATSUM "IvaUnitario",
							T1.PO1_DISCOUNT "DescuentoUnitario",
							T1.PO1_LINETOTAL "TotalUnitario",
							T1.PO1_UBUSINESS "UnidadNegocio",
							T1.PO1_PROJECT "Proyecto",
							T1.PO1_AVPRICE "Costo Ponderado"
						FROM DCPO T0
						LEFT JOIN CPO1 T1 ON T0.DVF_DOCENTRY = T1.PO1_DOCENTRY
						LEFT JOIN DMWS T2 ON T1.PO1_WHSCODE = T2.DWS_CODE
						where t0.business = :business';


			$result = $this->pedeo->queryTable($sql, array(
				':business' => $Data['business']
			));

			if(isset($result[0])){

				 $respuesta = array(
						'error'   => false,
						'data'    => $result,
						'mensaje' =>''
				 );

			}else{

				$respuesta = array(
					'error'   => true,
					'data' => array(),
					'mensaje'	=> 'busqueda sin resultados'
				);

			}

			$this->response($respuesta);
	}

	//INFORME OPERACIONES DIARIAS
	public function getOperacionesDiarias_post(){

			$Data = $this->post();

			if(!isset($Data['business']) or empty($Data['business'])){

				$respuesta = array(
					'error'   => true,
					'data' => [],
					'mensaje'	=> 'Falta parametro de empresa'
				);
				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
				return;
			}

			$sql = "SELECT
						t0.ac1_trans_id docnum,
						t0.ac1_trans_id numero_transaccion,
						case
							when coalesce(t0.ac1_font_type,0) = 3 then 'Entrega'
							when coalesce(t0.ac1_font_type,0) = 4 then 'Devolucion'
							when coalesce(t0.ac1_font_type,0) = 5 then 'Factura Cliente'
							when coalesce(t0.ac1_font_type,0) = 6 then 'Nota Credito Cliente'
							when coalesce(t0.ac1_font_type,0) = 7 then 'Nota Debito Cliente'
							when coalesce(t0.ac1_font_type,0) = 8 then 'Salida Mercancia'
							when coalesce(t0.ac1_font_type,0) = 9 then 'Entrada Mercancia'
							when coalesce(t0.ac1_font_type,0) = 13 then 'Entrada Compras'
							when coalesce(t0.ac1_font_type,0) = 14 then 'Devolucion Compra'
							when coalesce(t0.ac1_font_type,0) = 15 then 'Factura Proveedores'
							when coalesce(t0.ac1_font_type,0) = 16 then 'Nota Credito Compras'
							when coalesce(t0.ac1_font_type,0) = 17 then 'Nota Debito Compras'
							when coalesce(t0.ac1_font_type,0) = 18 then 'Asiento Manual'
							when coalesce(t0.ac1_font_type,0) = 19 then 'Pagos Efectuado'
							when coalesce(t0.ac1_font_type,0) = 20 then 'Pagos Recibidos'
						end origen,
						case
							when coalesce(t0.ac1_font_type,0) = 3 then t1.vem_docnum
							when coalesce(t0.ac1_font_type,0) = 4 then t2.vdv_docnum
							when coalesce(t0.ac1_font_type,0) = 5 then t3.dvf_docnum
							when coalesce(t0.ac1_font_type,0) = 6 then t10.vnc_docnum
							when coalesce(t0.ac1_font_type,0) = 6 then t11.vnd_docnum
							when coalesce(t0.ac1_font_type,0) = 8 then t5.isi_docnum
							when coalesce(t0.ac1_font_type,0) = 9 then t6.iei_docnum
							when coalesce(t0.ac1_font_type,0) = 13 then t12.cec_docnum
							when coalesce(t0.ac1_font_type,0) = 14 then t13.cdc_docnum
							when coalesce(t0.ac1_font_type,0) = 15 then t14.cnc_docnum
							when coalesce(t0.ac1_font_type,0) = 16 then t15.cnd_docnum
							when coalesce(t0.ac1_font_type,0) = 17 then t12.cec_docnum
							when coalesce(t0.ac1_font_type,0) = 18 then t0.ac1_trans_id
							when coalesce(t0.ac1_font_type,0) = 19 then t8.bpe_docnum
							when coalesce(t0.ac1_font_type,0) = 20 then t9.bpr_docnum
						end numero_origen,
						t4.acc_name nombre_cuenta,t0.*
					from mac1 t0
					left join dvem t1 on t0.ac1_font_key = t1.vem_docentry and t0.ac1_font_type = t1.vem_doctype
					left join dvdv t2 on t0.ac1_font_key = t2.vdv_docentry and t0.ac1_font_type = t2.vdv_doctype
					left join dvfv t3 on t0.ac1_font_key = t3.dvf_docentry and t0.ac1_font_type = t3.dvf_doctype
					inner join dacc t4 on t0.ac1_account = t4.acc_code
					left join misi t5 on t0.ac1_font_key = t5.isi_docentry and t0.ac1_font_type = t5.isi_doctype
					left join miei t6 on t0.ac1_font_key = t6.iei_docentry and t0.ac1_font_type = t6.iei_doctype
					left join dcfc t7 on t0.ac1_font_key = t7.cfc_docentry and t0.ac1_font_type = t7.cfc_doctype
					left join gbpe t8 on t0.ac1_font_key = t8.bpe_docentry and t0.ac1_font_type = t8.bpe_doctype
					left join gbpr t9 on t0.ac1_font_key = t9.bpr_docentry and t0.ac1_font_type = t9.bpr_doctype
					left join dvnc t10 on t0.ac1_font_key = t10.vnc_docentry and t0.ac1_font_type = t10.vnc_doctype
					left join dvnd t11 on t0.ac1_font_key = t11.vnd_docentry and t0.ac1_font_type = t11.vnd_doctype
					left join dcec t12 on t0.ac1_font_key = t12.cec_docentry and t0.ac1_font_type = t12.cec_doctype
					left join dcdc t13 on t0.ac1_font_key = t13.cdc_docentry and t0.ac1_font_type = t13.cdc_doctype
					left join dcnc t14 on t0.ac1_font_key = t14.cnc_docentry and t0.ac1_font_type = t14.cnc_doctype
					left join dcnd t15 on t0.ac1_font_key = t15.cnd_docentry and t0.ac1_font_type = t15.cnd_doctype
					where t0.business = :business";

			$result = $this->pedeo->queryTable($sql, array(
				':business' => $Data['business']
			));

			if(isset($result[0])){

				 $respuesta = array(
						'error'   => false,
						'data'    => $result,
						'mensaje' =>''
				 );

			}else{

				$respuesta = array(
					'error'   => true,
					'data' => array(),
					'mensaje'	=> 'busqueda sin resultados'
				);

			}

			$this->response($respuesta);
	}
	//INFORME STATUS DE STOCK
	public function getStatusStock_post(){

		$Data = $this->post();
		$where = '';
		$array = [];

		if(!isset($Data['business']) or empty($Data['business'])){

			$respuesta = array(
				'error'   => true,
				'data' => [],
				'mensaje'	=> 'Falta parametro de empresa'
			);
			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
			return;
		}else {
			// ADD WHERE QUERY
			$array[':business'] = $Data['business'];
			$where .= " and t1.business = :business";
		}

		if(isset($Data['fil_almacenId']) && !empty($Data['fil_almacenId'])){
			
			$where .= ' and T1.bdi_whscode IN ('.$Data['fil_almacenId'].')';

		}if(isset($Data['fil_acticuloId']) && !empty($Data['fil_acticuloId'])){
			// ADD WHERE QUERY
			$where .= ' and t2.dma_item_code = :dma_item_code';
			// ADD WHERE
			$array[':dma_item_code'] = $Data['fil_acticuloId'];

		}if(isset($Data['fil_grupoId']) && !empty($Data['fil_grupoId']) ){
			// ADD WHERE QUERY
			$where .= ' and t2.dma_group_code IN ('.$Data['fil_grupoId'].')';
		}

		$sql = "SELECT
					t2.dma_item_code,
					trim(t2.dma_item_name) as dma_item_name,
					t2.dma_item_mat,
					t3.dmu_nameum,
					t1.bdi_whscode,
					trim(to_char(t1.bdi_quantity, '999G999G999G999G999D'||lpad('9',get_decimals(),'9'))) as bdi_quantity,
					t1.bdi_ubication,
					t1.bdi_lote,
					get_localcur()||' '||trim(to_char(t1.bdi_avgprice, '999G999G999G999G999D'||lpad('9',get_decimals(),'9'))) as bdi_avgprice,
					get_localcur()||' '||trim(to_char(t1.bdi_quantity * t1.bdi_avgprice, '999G999G999G999G999D'||lpad('9',get_decimals(),'9')))  as costo
				from tbdi t1
				join dmar t2
				on t1.bdi_itemcode = t2.dma_item_code
				left join dmum t3 on t3.dmu_id =  t2.dma_uom_sale
				where 1 = 1".$where;
			$result = $this->pedeo->queryTable($sql, $array);

			if(isset($result[0])){

				 $respuesta = array(
						'error'   => false,
						'data'    => $result,
						'mensaje' =>''
				 );

			}else{

				$respuesta = array(
					'error'   => true,
					'data' => array(),
					'mensaje'	=> 'busqueda sin resultados'
				);

			}

			$this->response($respuesta);
	}
	//INFORME STATUS DE LISTA DE VENTAS
	public function getStatusStockLv_post(){

		$Data = $this->post();
		$where = '';
		$array = [];

		if(!isset($Data['business']) or empty($Data['business'])){

			$respuesta = array(
				'error'   => true,
				'data' => [],
				'mensaje'	=> 'Falta parametro de empresa'
			);
			
			return $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
			
		}else {
			// ADD WHERE QUERY
			$array[':business'] = $Data['business'];
			$where .= " and t4.business = :business";
		}

		if(isset($Data['fil_almacenId']) && !empty($Data['fil_almacenId'])){
			
			$array[':wshcode'] = $Data['fil_almacenId'];

		}if(isset($Data['fil_acticuloId']) && !empty($Data['fil_acticuloId'])){
			// ADD WHERE QUERY
			$where .= ' and t2.dma_item_code = :dma_item_code';
			// ADD WHERE
			$array[':dma_item_code'] = $Data['fil_acticuloId'];

		}if(isset($Data['fil_grupoId']) && !empty($Data['fil_grupoId']) ){
			// ADD WHERE QUERY
			$where .= ' and t2.dma_group_code IN ('.$Data['fil_grupoId'].')';
		}

		$sql = "SELECT
				t2.dma_item_code as  codigo_articulo,
				trim(t2.dma_item_name) as articulo,
				t2.dma_item_mat,
				t3.dmu_nameum as nombre_unidad,
				trim(to_char(coalesce(get_stock_lista_ventas(dma_item_code, :wshcode),0), '999G999G999G999G999D'||lpad('9',get_decimals(),'9'))) as cantidad,
				get_localcur()||' '||trim(to_char(coalesce(get_costo_lista_ventas(dma_item_code, :wshcode), 0), '999G999G999G999G999D'||lpad('9',get_decimals(),'9'))) as costo,
				get_localcur()||' '||trim(to_char(coalesce(get_stock_lista_ventas(dma_item_code, :wshcode),0) * coalesce(get_costo_lista_ventas(dma_item_code, :wshcode),0), '999G999G999G999G999D'||lpad('9',get_decimals(),'9')))  as costo_total
			from dmar t2
			left join dmum t3 on t3.dmu_id =  t2.dma_uom_sale
			inner join prlm t4 on t2.dma_item_code  = t4.rlm_item_code 
			where 1 = 1
			and t4.rlm_bom_type = 3 ".$where;

			$result = $this->pedeo->queryTable($sql, $array);

			if(isset($result[0])){

					$respuesta = array(
						'error'   => false,
						'data'    => $result,
						'mensaje' =>''
					);

			}else{

				$respuesta = array(
					'error'   => true,
					'data' => array(),
					'mensaje'	=> 'busqueda sin resultados'
				);

			}

			$this->response($respuesta);
	}

	//INFORME ESTADO DE CARTERA
	public function getPortfolioStatus_post(){

			$Data = $this->post();
			if(!isset($Data['business']) or empty($Data['business'])){

				$respuesta = array(
					'error'   => true,
					'data' => [],
					'mensaje'	=> 'Falta parametro de empresa'
				);
				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
				return;
			}

			$sql = "SELECT
									 	t0.dvf_cardcode CodigoCliente,
									 	t0.dvf_cardname NombreCliente,
									 	t0.dvf_docnum NumeroDocumento,
									 	t0.dvf_docdate FechaDocumento,
									 	t0.dvf_duedate FechaVencimiento,
										t0.dvf_doctotal totalfactura,
									 T0.dvf_paytoday saldo,
										trim('COP' FROM t0.dvf_currency) MonedaDocumento,
									 	'".$Data['fecha']."' FechaCorte,
										('".$Data['fecha']."' - t0.dvf_duedate) dias,
									 	CASE
									 		WHEN ( '".$Data['fecha']."' - t0.dvf_duedate) >=0 and ( '".$Data['fecha']."' - t0.dvf_duedate) <=30
									 			then (t0.dvf_doctotal - COALESCE(t0.dvf_paytoday,0))
																								ELSE 0
									 	END uno_treinta,
									 	CASE
									 		WHEN ( '".$Data['fecha']."' - t0.dvf_duedate) >=31 and ( '".$Data['fecha']."' - t0.dvf_duedate) <=60
									 			then (t0.dvf_doctotal - COALESCE(t0.dvf_paytoday,0))
																								ELSE 0
									 	END treinta_uno_secenta,
									 	CASE
									 		WHEN ( '".$Data['fecha']."' - t0.dvf_duedate) >=61 and ( '".$Data['fecha']."' - t0.dvf_duedate) <=90
									 			then (t0.dvf_doctotal - COALESCE(t0.dvf_paytoday,0))
																								ELSE 0
									 	END secenta_uno_noventa,
									 	CASE
									 		WHEN ( '".$Data['fecha']."' - t0.dvf_duedate) >=91
									 			then (t0.dvf_doctotal - COALESCE(t0.dvf_paytoday,0))
																						ELSE 0
									 	END mayor_noventa

									 FROM dvfv t0
									 WHERE '".$Data['fecha']."' >= t0.dvf_duedate and t0.business = :business
									ORDER BY CodigoCliente asc";

			$result = $this->pedeo->queryTable($sql, array(
				':business' => $Data['business']
			));

			if(isset($result[0])){

				 $respuesta = array(
						'error'   => false,
						'data'    => $result,
						'mensaje' =>''
				 );

			}else{

				$respuesta = array(
					'error'   => true,
					'data' => array(),
					'mensaje'	=> 'busqueda sin resultados'
				);

			}

			$this->response($respuesta);
	}

	// ESTADO DE CUENTA PARA CLIENTE Y PROVEEDOR
	public function EstadoCuentaCl_post(){

		$Data = $this->post();

		if(!isset($Data['business']) or empty($Data['business']) or
			!isset($Data['cardcode']) or empty($Data['cardcode']) or 
			!isset($Data['currency']) or empty($Data['currency']) or
			!isset($Data['cardtype']) or empty($Data['cardtype'])){

			$respuesta = array(
				'error'   => true,
				'data' => [],
				'mensaje'	=> 'Falta alguno de los siguientes parametros (business,cardcode,currency,cardtype)'
			);
			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
			return;
		}

		$fecha = "";

		$cliente = "";
		
		$proveedor = "";


		

		if ( is_array( $Data['cardcode'] ) && is_array( $Data['cardtype'] ) ) {

			if ( isset( $Data['cardcode'][2] ) ){

				$respuesta = array(
					'error'   => true,
					'data' => [],
					'mensaje'	=> 'El numero de socios a conciliar es mayor del permitido, (2)'
				);

				return $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
			}

		}else{

			$respuesta = array(
				'error'   => true,
				'data' => [],
				'mensaje'	=> 'El formato de la información enviada no es valido'
			);

			return $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
			
		}

		if(isset($Data['cardcode']) && !empty($Data['cardcode']) && isset($Data['cardtype'])){

			if( isset($Data['fecha']) ){
				$fecha = $Data['fecha'];
			}else{
				$fecha = 'CURRENT_DATE';
			}

			$array = [];
			$array2 = [];
			$result = null;

			for ($i=0; $i < count($Data['cardtype']); $i++) { 


				$result = null;

				if ( $Data['cardtype'][$i] == 1 ){

					$cliente = $Data['cardcode'][$i];

					$result = $this->accountstatus->getECC($cliente,$fecha,$Data['currency'],$Data['business']);
					

				}else if ( $Data['cardtype'][$i] == 2 ){

					$proveedor = $Data['cardcode'][$i];

					$result = $this->accountstatus->getECP($proveedor,$fecha,$Data['currency'],$Data['business']);
					
				}

				if ( isset($result[0]) ){

					array_push($array2, $result);
				}
				
			}

			if ( isset($array2[0]) && isset($array2[1]) ){
				$array = array_merge($array2[0], $array2[1]);
			} else {
				$array = $result;
			}


			if(isset($array[0])){

				$respuesta = array(
					'error'   => false,
					'data'    => $array,
					'mensaje' =>''
				);

			}else{

				$respuesta = array(
					'error'   => true,
					'data' => array(),
					'mensaje'	=> 'busqueda sin resultados'
				);

			}

			$this->response($respuesta);
		}
	}	

	// OBTENER ACIENTO CONTABLE POR ID
	public function getLedger_get(){

				$Data = $this->get();
				$where = '';

				if(!isset($Data['business']) or empty($Data['business'])){
					$respuesta = array(
						'error'   => true,
						'data' => [],
						'mensaje'	=> 'Faltan parametro de empresa'
					);
					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
					return;
				}else {
					//ADD WHERE QUERY
					$where = " AND t0.business = ".$Data['business'];
				}

				if(isset($Data['cardcode']) && !empty($Data['cardcode'])){
					$where = ' and t0.ac1_legal_num in ('.$Data['cardcode'].')';

				}if(isset($Data['account']) && !empty($Data['account'])){
					$where = $where. ' and t0.ac1_account in ('.$Data['account'].')';

				}if(isset($Data['fechaini']) && isset($Data['fechafin']) ){
					$where = $where.' and t0.ac1_doc_date between '.$Data['fechaini'].' and '.$Data['fechafin'];
				}if(isset($Data['costCenter']) && !empty($Data['costCenter'])){
					$where = $where. ' and t0.ac1_prc_code in ('.$Data['costCenter'].')';

				}if(isset($Data['unitB']) && !empty($Data['unitB'])){
					$where = $where. ' and t0.ac1_uncode in ('.$Data['unitB'].')';

				}if(isset($Data['project']) && !empty($Data['project'])){
					$where = $where. ' and t0.ac1_prj_code in ('.$Data['project'].')';
				}if( isset($Data['anulados']) && $Data['anulados'] == 0 ){
					$where = $where. ' and tmac.mac_status != 2';
				}

				$sqlSelect = "SELECT
					t0.ac1_trans_id docnum,
					t0.ac1_trans_id numero_transaccion,
					t16.mdt_docname origen,
					case
					when coalesce(t0.ac1_font_type,0) = 3 then (SELECT row_to_json(dvem.*) FROM dvem WHERE t0.ac1_font_key = dvem.vem_docentry and t0.ac1_font_type = dvem.vem_doctype)
					when coalesce(t0.ac1_font_type,0) = 4 then (SELECT row_to_json(dvdv.*) FROM dvdv WHERE t0.ac1_font_key = dvdv.vdv_docentry and t0.ac1_font_type = dvdv.vdv_doctype)
					when coalesce(t0.ac1_font_type,0) = 5 then (SELECT row_to_json(dvfv.*) FROM dvfv WHERE t0.ac1_font_key = dvfv.dvf_docentry and t0.ac1_font_type = dvfv.dvf_doctype)
					when coalesce(t0.ac1_font_type,0) = 6 then (SELECT row_to_json(dvnc.*) FROM dvnc WHERE t0.ac1_font_key = dvnc.vnc_docentry and t0.ac1_font_type = dvnc.vnc_doctype)
					when coalesce(t0.ac1_font_type,0) = 7 then (SELECT row_to_json(dvnd.*) FROM dvnd WHERE t0.ac1_font_key = dvnd.vnd_docentry and t0.ac1_font_type = dvnd.vnd_doctype)
					when coalesce(t0.ac1_font_type,0) = 8 then (SELECT row_to_json(misi.*) FROM misi WHERE t0.ac1_font_key = misi.isi_docentry and t0.ac1_font_type = misi.isi_doctype)
					when coalesce(t0.ac1_font_type,0) = 9 then (SELECT row_to_json(miei.*) FROM miei WHERE t0.ac1_font_key = miei.iei_docentry and t0.ac1_font_type = miei.iei_doctype)
					when coalesce(t0.ac1_font_type,0) = 13 then (SELECT row_to_json(dcec.*) FROM dcec WHERE t0.ac1_font_key = dcec.cec_docentry and t0.ac1_font_type = dcec.cec_doctype)
					when coalesce(t0.ac1_font_type,0) = 14 then (SELECT row_to_json(dcdc.*) FROM dcdc WHERE t0.ac1_font_key = dcdc.cdc_docentry and t0.ac1_font_type = dcdc.cdc_doctype)
					when coalesce(t0.ac1_font_type,0) = 15 then (SELECT row_to_json(dcfc.*) FROM dcfc WHERE t0.ac1_font_key = dcfc.cfc_docentry and t0.ac1_font_type = dcfc.cfc_doctype)
					when coalesce(t0.ac1_font_type,0) = 16 then (SELECT row_to_json(dcnc.*) FROM dcnc WHERE t0.ac1_font_key = dcnc.cnc_docentry and t0.ac1_font_type = dcnc.cnc_doctype)
					when coalesce(t0.ac1_font_type,0) = 17 then (SELECT row_to_json(dcnd.*) FROM dcnd WHERE t0.ac1_font_key = dcnd.cnd_docentry and t0.ac1_font_type = dcnd.cnd_doctype)
					when coalesce(t0.ac1_font_type,0) = 18 then row_to_json(t0.*)
					when coalesce(t0.ac1_font_type,0) = 19 then (SELECT row_to_json(gbpe.*) FROM gbpe WHERE t0.ac1_font_key = gbpe.bpe_docentry and t0.ac1_font_type = gbpe.bpe_doctype)
					when coalesce(t0.ac1_font_type,0) = 20 then (SELECT row_to_json(gbpr.*) FROM gbpr WHERE t0.ac1_font_key = gbpr.bpr_docentry and t0.ac1_font_type = gbpr.bpr_doctype)
					when coalesce(t0.ac1_font_type,0) = 22 then (SELECT row_to_json(dcrc.*) FROM dcrc WHERE t0.ac1_font_key = dcrc.crc_docentry and t0.ac1_font_type = dcrc.crc_doctype)
					end extras,
					COALESCE(t4.acc_name,'CUENTA PUENTE') nombre_cuenta,t0.*,
					dmsn.dms_card_name as cardname
					FROM mac1 t0
					INNER JOIN dacc t4 on t0.ac1_account = t4.acc_code
					INNER JOIN dmdt t16 on coalesce(t0.ac1_font_type,0) = t16.mdt_doctype
					inner join tmac  on t0.ac1_trans_id = tmac.mac_trans_id 
					inner join dmsn on t0.ac1_legal_num = dmsn.dms_card_code
					WHERE 1=1 ".$where;

				$resSelect = $this->pedeo->queryTable($sqlSelect,array());

				if(isset($resSelect[0])){
					//
					$newData = [];
					// RECORRER DATOS DE LA CONSULTA.
					foreach ($resSelect as $key => $data) {
						// VALIDATE
						$json = json_decode($data['extras'], true);
						// ELIMINAR DATA DEL ARRAY
						unset($data['extras']);
						// VALIDAR SI ES UNARRAY
						if (is_array($json)) {	
							// OBJETO
							$newObj = [];
							// RECORRER JSON
							foreach($json as $item => $obj) {
								// DIVIDIR NOMBRE DE LA VARIABLE.
								$prefijo = explode("_", $item);
								// VALIDAR PREFIJO
								if (isset($prefijo[1])) {
									// VALIDAR SI EL CAMPO ES DE COMENTARIO.
									if ($prefijo[1] === 'comments') {
										// RENOMBRAR EL CAMPO Y SASIGNAR VALOR.
										$newObj['mac_comment'] = $obj;
									}else {
										//
										$newObj['mac_'.$prefijo[1]] = $obj;
									}
									if ($prefijo[1] === 'docnum') {
										$newObj['docnumorg'] = $obj;
									}
					
								}
							}
							/**
							 * VALIDACIONES PARA CUANDO EL REGISTRO VIENE DE UN ASIENTO.
							 */
							if (!isset($newObj['mac_cardcode']) OR !isset($newObj['mac_cardname'])) {
								// RENOMBRAR EL CAMPO Y SASIGNAR VALOR.
								$newObj['mac_cardcode'] = $data['ac1_legal_num'];
								$newObj['mac_cardname'] = $data['cardname'];
							}
							if (!isset($newObj['mac_comment'])) {
								// RENOMBRAR EL CAMPO Y SASIGNAR VALOR.
								$newObj['mac_comment'] = $data['origen'];
							}
							if (!isset($newObj['mac_createby'])) {
								// RENOMBRAR EL CAMPO Y SASIGNAR VALOR.
								$newObj['mac_createby'] = $data['ac1_doc_date'];
							}
							/**
							 * FIN
							 */
							// AGREGAR NUEVO ARRAY.
							$newData[] = array_merge($data, $newObj);
						}
					}
					$respuesta = array(
						'error' => false,
						'data'  => $newData,
						'mensaje' => ''
					);
				}else{
						$respuesta = array(
							'error'   => true,
							'data' => array(),
							'mensaje'	=> 'busqueda sin resultados'
						);
				}

				 $this->response($respuesta);
	}

	// PARA REPORTE LIBRO DIARIO DE VENTAS
	public function  logBook_post(){
		$Data = $this->post();

		if( !isset($Data['inicio']) OR
			!isset($Data['fin'])OR
			!isset($Data['tipo']) or
			!isset($Data['business'])
		){
		$this->response(array(
			'error'  => true,
			'data'   => [],
			'mensaje'=>'La informacion enviada no es valida'
		), REST_Controller::HTTP_BAD_REQUEST);

		return ;
		}

		$sqlSelect = "SELECT
									fv.dvf_docentry as docentry, fv.dvf_docdate as docdate, dmdt.mdt_docname as doctype, fv.dvf_docnum as docnum , fv.dvf_correl as correl,
									fv.dvf_cardcode as cardcode, fv.dvf_cardname as cardname, dvf_doctotal as doctotal, '' excentas, fv.dvf_baseamnt as baseamnt, fv.dvf_taxtotal as taxtotal,
									fv.dvf_currency as moneda
									from dvfv fv
									inner join dmdt
									on dvf_doctype = mdt_doctype
									WHERE fv.dvf_".$Data['tipo']." between :dvf_docdate and :dvf_duedate
									-- NOTA CREDITO
									union all
									SELECT
									nc.vnc_docentry as docentry, nc.vnc_docdate as docdate, dmdt.mdt_docname as doctype, nc.vnc_docnum as docnum, 0 as correl,
									nc.vnc_cardcode as cardcode, nc.vnc_cardname as cardname, vnc_doctotal as doctotal, '' excentas, nc.vnc_baseamnt as baseamnt, nc.vnc_taxtotal as taxtotal,
									nc.vnc_currency as moneda
									from dvnc nc
									inner join dmdt
									on vnc_doctype = mdt_doctype
									WHERE nc.vnc_".$Data['tipo']." between :dvf_docdate and :dvf_duedate
									-- NOTA DEBITO
									union all
									SELECT
									nd.vnd_docentry as docentry, nd.vnd_docdate as docdate, dmdt.mdt_docname as doctype, nd.vnd_docnum as docnum, 0 as correl,
									nd.vnd_cardcode as cardcode, nd.vnd_cardname as cardname, vnd_doctotal as doctotal, '' excentas, nd.vnd_baseamnt as baseamnt, nd.vnd_taxtotal as taxtotal,
									nd.vnd_currency as moneda
									from dvnd nd
									inner join dmdt
									on vnd_doctype = mdt_doctype
									WHERE nd.vnd_".$Data['tipo']." between :dvf_docdate and :dvf_duedate";

		$resSelect = $this->pedeo->queryTable($sqlSelect,array(":dvf_docdate" =>$Data['inicio'],":dvf_duedate"=>$Data['fin']));
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

	// PARA REPORTE LIBRO DIARIO DE COMPRAS
	public function  logShoppingBook_post(){
		$Data = $this->post();

		if( !isset($Data['inicio']) OR
			!isset($Data['fin']) OR
			!isset($Data['tipo']) or
			!isset($Data['business'])){

			$respuesta = array(
				'error'  => true,
				'data'   => [],
				'mensaje'=>'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return ;
		}

		$sqlSelect = "SELECT fc.cfc_docentry as docentry, fc.cfc_docdate as docdate, dmdt.mdt_docname as cfc_doctype, fc.cfc_docnum as docnum, 0 as cfc_correl,
									fc.cfc_cardcode as cardcode, fc.cfc_cardname as cardname, round(cfc_doctotal, get_decimals()) as  doctotal, '' excentas, round(fc.cfc_baseamnt, get_decimals()) as baseamnt, round(fc.cfc_taxtotal, get_decimals()) as taxtotal,
									fc.cfc_correl as referencia,fc.cfc_tax_control_num as numerofiscal,
									abs(coalesce(round(get_ret(fc.cfc_doctype,fc.cfc_docentry),get_decimals()), 0 ) - coalesce(round(get_retiva(fc.cfc_doctype,fc.cfc_docentry),get_decimals()), 0 ))as totalretencion,
									get_retname(fc.cfc_doctype,fc.cfc_docentry) as retenciones,
									coalesce(round(get_retiva(fc.cfc_doctype,fc.cfc_docentry),get_decimals()), 0 ) as retencioniva,
									fc.cfc_currency  as moneda
									from dcfc fc
									inner join dmdt
									on cfc_doctype = mdt_doctype
									WHERE  fc.cfc_".$Data['tipo']." between :cfc_docdate and :cfc_duedate
									and fc.business = :business
									--NOTAS CREDITO
									union all
									SELECT nc.cnc_docentry as docentry, nc.cnc_docdate as docdate, dmdt.mdt_docname as cnc_doctype, nc.cnc_docnum as docnum, 0 as cnc_correl,
									nc.cnc_cardcode as cardcode, nc.cnc_cardname as cardname, round(cnc_doctotal, get_decimals()) as doctotal, '' excentas, round(nc.cnc_baseamnt, get_decimals()) as baseamnt, round(nc.cnc_taxtotal, get_decimals()) as taxtotal,
									'' as referencia, '' as numerofiscal,
									abs(coalesce(round(get_ret(nc.cnc_doctype,nc.cnc_docentry),get_decimals()), 0 ) - 	coalesce(round(get_retiva(nc.cnc_doctype,nc.cnc_docentry),get_decimals()), 0 )) as totalretencion,
									get_retname(nc.cnc_doctype,nc.cnc_docentry) as retenciones,
									coalesce(round(get_retiva(nc.cnc_doctype,nc.cnc_docentry),get_decimals()), 0 ) as retencioniva,
									nc.cnc_currency  as moneda
									from dcnc nc
									left join dmdt
									on cnc_doctype = mdt_doctype
									WHERE  nc.cnc_".$Data['tipo']." between :cfc_docdate and :cfc_duedate
									and nc.business = :business
									--NOTAS DEBITO
									union all
									SELECT nd.cnd_docentry as docentry, nd.cnd_docdate as docdate, dmdt.mdt_docname as cnd_doctype, nd.cnd_docnum as docnum, 0 as cnd_correl,
									nd.cnd_cardcode as cardcode, nd.cnd_cardname as cardname, cnd_doctotal as doctotal, '' excentas, nd.cnd_baseamnt as baseamnt, nd.cnd_taxtotal as taxtotal,
									'' as referencia, '' as numerofiscal,
									abs(coalesce(round(get_ret(nd.cnd_doctype,nd.cnd_docentry),get_decimals()), 0 ) - coalesce(round(get_retiva(nd.cnd_doctype,nd.cnd_docentry),get_decimals()), 0 )) as totalretencion,
									get_retname(nd.cnd_doctype,nd.cnd_docentry) as retenciones,
									coalesce(round(get_retiva(nd.cnd_doctype,nd.cnd_docentry),get_decimals()), 0 ) as retencioniva,
									nd.cnd_currency  as moneda
									from dcnd nd
									left join dmdt
									on cnd_doctype = mdt_doctype
									WHERE  nd.cnd_".$Data['tipo']." between :cfc_docdate and :cfc_duedate
									and nd.business = :business";

		$resSelect = $this->pedeo->queryTable($sqlSelect,array(":cfc_docdate" =>$Data['inicio'],":cfc_duedate"=>$Data['fin'],":business" => $Data['business']));
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

	//LISTA DE PARTIDAS ABIERTAS
	//DOCUMENTOS PENDIENTES POR COMPENSAR
	public function  ListDocumentCompensate_post(){
		
		$Data = $this->post();
		
		if( !isset($Data['business'])){

			$respuesta = array(
				'error'  => true,
				'data'   => [],
				'mensaje'=>'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return ;
		}
		
		$sn = "";
		$ac = "";
		$sql = "";
		$fi = "";
		$ff = "";
		
		if( isset( $Data['ldc_sn'] )  && !empty($Data['ldc_sn']) ){
			$sn = $Data['ldc_sn'];
		}

		if( isset( $Data['ldc_ac'] )  && !empty($Data['ldc_ac']) ){
			$ac = $Data['ldc_ac'];
		}

		if( isset( $Data['ldc_ini'] )  && !empty($Data['ldc_ini']) ){
			$fi = "'".$Data['ldc_ini']."'";
			if( isset( $Data['ldc_end'] )  && !empty($Data['ldc_end']) ){
				$ff = "'".$Data['ldc_end']."'";

				$sql .=' AND  mac1.ac1_doc_date BETWEEN '.$fi.' AND '.$ff.'';
			}
		}


		if( is_array($sn) ){
			$s = "";
			foreach ($sn as $key => $value) {
				$s.="'".$value."',";
			}
			$sn = substr($s, 0, (strlen($s) -1));
			$sql .= ' AND mac1.ac1_legal_num IN('.$sn.')';
		}

		if( is_array($ac) ){
			$ac = implode(",",$ac);
			$sql .= ' AND mac1.ac1_account IN('.$ac.')';
		}

		// print_r($sql);
		// exit;

		$sqlSelect = "SELECT distinct
									mac1.ac1_font_key,
									concat('C-',mac1.ac1_legal_num) as codigo_proveedor,
									mac1.ac1_account as cuenta,
									CURRENT_DATE - dvf_duedate dias_atrasado,
									dvfv.dvf_comment,
									dvfv.dvf_currency,
									mac1.ac1_font_key as dvf_docentry,
									dvfv.dvf_docnum,
									dvfv.dvf_docdate as fecha_doc,
									dvfv.dvf_duedate as fecha_ven,
									dvf_docnum as id_origen,
									mac1.ac1_font_type as numtype,
									mdt_docname as tipo,
									case
										when mac1.ac1_font_type = 5 then mac1.ac1_debit
										else mac1.ac1_credit
									end	 as total_doc,
									(mac1.ac1_debit) - (mac1.ac1_ven_credit) as saldo_venc,
									'' retencion,
									get_tax_currency(dvfv.dvf_currency, dvfv.dvf_docdate) as tasa_dia
									from  mac1
									inner join dacc
									on mac1.ac1_account = dacc.acc_code
									and acc_businessp = '1'
									inner join dmdt
									on mac1.ac1_font_type = dmdt.mdt_doctype
									inner join dvfv
									on dvfv.dvf_doctype = mac1.ac1_font_type
									and dvfv.dvf_docentry = mac1.ac1_font_key
									where 1 = 1 and mac1.business = :business
									".$sql."
									and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
									-- ANTCIPOS CLIENTES
									union all
									select distinct
									mac1.ac1_font_key,
									concat('C-',mac1.ac1_legal_num) as codigo_proveedor,
									mac1.ac1_account as cuenta,
									CURRENT_DATE - gbpr.bpr_docdate as dias_atrasado,
									gbpr.bpr_comments as bpr_comment,
									gbpr.bpr_currency,
									mac1.ac1_font_key as dvf_docentry,
									gbpr.bpr_docnum,
									gbpr.bpr_docdate as fecha_doc,
									gbpr.bpr_docdate as fecha_ven,
									gbpr.bpr_docnum as id_origen,
									mac1.ac1_font_type as numtype,
									mdt_docname as tipo,
									case
										when mac1.ac1_font_type = 5 then mac1.ac1_debit
										else mac1.ac1_credit
									end	 as total_doc,
									(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) as saldo_venc,
									'' retencion,
									get_tax_currency(gbpr.bpr_currency, gbpr.bpr_docdate) as tasa_dia
									from  mac1
									inner join dacc
									on mac1.ac1_account = dacc.acc_code
									and acc_businessp = '1'
									inner join dmdt
									on mac1.ac1_font_type = dmdt.mdt_doctype
									inner join gbpr
									on gbpr.bpr_doctype = mac1.ac1_font_type
									and gbpr.bpr_docentry = mac1.ac1_font_key
									where 1 = 1 and mac1.business = :business
									".$sql."
									and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
									--NOTA CREDITO
									union all
									select distinct
									mac1.ac1_font_key,
									concat('C-',mac1.ac1_legal_num) as codigo_proveedor,
									mac1.ac1_account as cuenta,
									CURRENT_DATE - dvnc.vnc_docdate as dias_atrasado,
									dvnc.vnc_comment as bpr_comment,
									dvnc.vnc_currency,
									mac1.ac1_font_key as dvf_docentry,
									dvnc.vnc_docnum,
									dvnc.vnc_docdate as fecha_doc,
									dvnc.vnc_duedate as fecha_ven,
									dvnc.vnc_docnum as id_origen,
									mac1.ac1_font_type as numtype,
									mdt_docname as tipo,
									case
										when mac1.ac1_font_type = 5 then mac1.ac1_debit
										else mac1.ac1_credit
									end	 as total_doc,
									(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) as saldo_venc,
									'' retencion,
									get_tax_currency(dvnc.vnc_currency, dvnc.vnc_docdate) as tasa_dia
									from  mac1
									inner join dacc
									on mac1.ac1_account = dacc.acc_code
									and acc_businessp = '1'
									inner join dmdt
									on mac1.ac1_font_type = dmdt.mdt_doctype
									inner join dvnc
									on dvnc.vnc_doctype = mac1.ac1_font_type
									and dvnc.vnc_docentry = mac1.ac1_font_key
									where 1 = 1 and mac1.business = :business
									".$sql."
									and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
									--NOTA DEBITO
									union all
									select distinct
									mac1.ac1_font_key,
									concat('C-',mac1.ac1_legal_num) as codigo_proveedor,
									mac1.ac1_account as cuenta,
									CURRENT_DATE - dvnd.vnd_docdate as dias_atrasado,
									dvnd.vnd_comment as bpr_comment,
									dvnd.vnd_currency,
									mac1.ac1_font_key as dvf_docentry,
									dvnd.vnd_docnum,
									dvnd.vnd_docdate as fecha_doc,
									dvnd.vnd_duedate as fecha_ven,
									dvnd.vnd_docnum as id_origen,
									mac1.ac1_font_type as numtype,
									mdt_docname as tipo,
									case
										when mac1.ac1_font_type = 5 then mac1.ac1_debit
										else mac1.ac1_credit
									end	 as total_doc,
									(mac1.ac1_debit) - (mac1.ac1_ven_credit) as saldo_venc,
									'' retencion,
									get_tax_currency(dvnd.vnd_currency, dvnd.vnd_docdate) as tasa_dia
									from  mac1
									inner join dacc
									on mac1.ac1_account = dacc.acc_code
									and acc_businessp = '1'
									inner join dmdt
									on mac1.ac1_font_type = dmdt.mdt_doctype
									inner join dvnd
									on dvnd.vnd_doctype = mac1.ac1_font_type
									and dvnd.vnd_docentry = mac1.ac1_font_key
									where 1 = 1 and mac1.business = :business
									".$sql."
									and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0

									--COMPRAS
									--FACTURA DE COMPRAS
									union all
									select distinct
									mac1.ac1_font_key,
								  concat('P-',mac1.ac1_legal_num) as codigo_proveedor,
									mac1.ac1_account as cuenta,
									CURRENT_DATE - cfc_duedate dias_atrasado,
									dcfc.cfc_comment,
									dcfc.cfc_currency,
									mac1.ac1_font_key as dvf_docentry,
									dcfc.cfc_docnum,
									dcfc.cfc_docdate as fecha_doc,
									dcfc.cfc_duedate as fecha_ven,
									cfc_docnum as id_origen,
									mac1.ac1_font_type as numtype,
									mdt_docname as tipo,
									case
										when mac1.ac1_font_type = 15 then mac1.ac1_credit
										else mac1.ac1_debit
									end	 as total_doc,
									(mac1.ac1_ven_debit) - (mac1.ac1_credit)  as saldo_venc,
									'' retencion,
									get_tax_currency(dcfc.cfc_currency, dcfc.cfc_docdate) as tasa_dia
									from  mac1
									inner join dacc
									on mac1.ac1_account = dacc.acc_code
									and acc_businessp = '1'
									inner join dmdt
									on mac1.ac1_font_type = dmdt.mdt_doctype
									inner join dcfc
									on dcfc.cfc_doctype = mac1.ac1_font_type
									and dcfc.cfc_docentry = mac1.ac1_font_key
									where 1 = 1 and mac1.business = :business
									".$sql."
									and ABS((mac1.ac1_ven_credit) - (mac1.ac1_ven_debit)) > 0
									-- PAGO EFECTUADO
									union all
									select distinct
									mac1.ac1_font_key,
									concat('P-',mac1.ac1_legal_num) as codigo_proveedor,
									mac1.ac1_account as cuenta,
									CURRENT_DATE - gbpe.bpe_docdate as dias_atrasado,
									gbpe.bpe_comments as bpr_comment,
									gbpe.bpe_currency,
									mac1.ac1_font_key as dvf_docentry,
									gbpe.bpe_docnum,
									gbpe.bpe_docdate as fecha_doc,
									gbpe.bpe_docdate as fecha_ven,
									gbpe.bpe_docnum as id_origen,
									mac1.ac1_font_type as numtype,
									mdt_docname as tipo,
									case
										when mac1.ac1_font_type = 15 then mac1.ac1_debit
										else mac1.ac1_debit
									end	 as total_doc,
									(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) as saldo_venc,
									'' retencion,
									get_tax_currency(gbpe.bpe_currency, gbpe.bpe_docdate) as tasa_dia
									from  mac1
									inner join dacc
									on mac1.ac1_account = dacc.acc_code
									and acc_businessp = '1'
									inner join dmdt
									on mac1.ac1_font_type = dmdt.mdt_doctype
									inner join gbpe
									on gbpe.bpe_doctype = mac1.ac1_font_type
									and gbpe.bpe_docentry = mac1.ac1_font_key
									where 1 = 1 and mac1.business = :business
									".$sql."
									and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
									--NOTA CREDITO
									union all
									select  distinct
									mac1.ac1_font_key,
									concat('P-',mac1.ac1_legal_num) as codigo_proveedor,
									mac1.ac1_account as cuenta,
									CURRENT_DATE - dcnc.cnc_docdate as dias_atrasado,
									dcnc.cnc_comment as bpr_comment,
									dcnc.cnc_currency,
									mac1.ac1_font_key as dvf_docentry,
									dcnc.cnc_docnum,
									dcnc.cnc_docdate as fecha_doc,
									dcnc.cnc_duedate as fecha_ven,
									dcnc.cnc_docnum as id_origen,
									mac1.ac1_font_type as numtype,
									mdt_docname as tipo,
									case
										when mac1.ac1_font_type = 15 then mac1.ac1_debit
										else mac1.ac1_debit
									end	 as total_doc,
									(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) as saldo_venc,
									'' retencion,
									get_tax_currency(dcnc.cnc_currency, dcnc.cnc_docdate) as tasa_dia
									from  mac1
									inner join dacc
									on mac1.ac1_account = dacc.acc_code
									and acc_businessp = '1'
									inner join dmdt
									on mac1.ac1_font_type = dmdt.mdt_doctype
									inner join dcnc
									on dcnc.cnc_doctype = mac1.ac1_font_type
									and dcnc.cnc_docentry = mac1.ac1_font_key
									where 1 = 1 and mac1.business = :business
									".$sql."
									and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
									--NOTA DEBITO
									union all
									select distinct
									mac1.ac1_font_key,
									concat('P-',mac1.ac1_legal_num) as codigo_proveedor,
									mac1.ac1_account as cuenta,
									CURRENT_DATE - dcnd.cnd_docdate as dias_atrasado,
									dcnd.cnd_comment as bpr_comment,
									dcnd.cnd_currency,
									mac1.ac1_font_key as dvf_docentry,
									dcnd.cnd_docnum,
									dcnd.cnd_docdate as fecha_doc,
									dcnd.cnd_duedate as fecha_ven,
									dcnd.cnd_docnum as id_origen,
									mac1.ac1_font_type as numtype,
									mdt_docname as tipo,
									case
										when mac1.ac1_font_type = 15 then mac1.ac1_debit
										else mac1.ac1_credit
									end	 as total_doc,
									(mac1.ac1_ven_credit) - (mac1.ac1_debit) as saldo_venc,
									'' retencion,
									get_tax_currency(dcnd.cnd_currency, dcnd.cnd_docdate) as tasa_dia
									from  mac1
									inner join dacc
									on mac1.ac1_account = dacc.acc_code
									and acc_businessp = '1'
									inner join dmdt
									on mac1.ac1_font_type = dmdt.mdt_doctype
									inner join dcnd
									on dcnd.cnd_doctype = mac1.ac1_font_type
									and dcnd.cnd_docentry = mac1.ac1_font_key
									where 1 = 1 and mac1.business = :business
									".$sql."
									and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
									--ASIENTOS MANUALES
									union all
									select distinct
									mac1.ac1_font_key,
									case
										when ac1_card_type = '1' then concat('C-',mac1.ac1_legal_num)
										when ac1_card_type = '2' then concat('P-',mac1.ac1_legal_num)
									end as codigoproveedor,
									mac1.ac1_account as cuenta,
									CURRENT_DATE - tmac.mac_doc_duedate dias_atrasado,
									tmac.mac_comments,
									tmac.mac_currency,
									0 as dvf_docentry,
									0 as docnum,
									tmac.mac_doc_date as fecha_doc,
									tmac.mac_doc_duedate as fecha_ven,
									0 as id_origen,
									18 as numtype,
									mdt_docname as tipo,
									case
										when mac1.ac1_cord = 0 then mac1.ac1_debit
										when mac1.ac1_cord = 1 then mac1.ac1_credit
									end	 as total_doc,
									(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) as saldo_venc,
									'' retencion,
									get_tax_currency(tmac.mac_currency, tmac.mac_doc_date) as tasa_dia
									from  mac1
									inner join dacc
									on mac1.ac1_account = dacc.acc_code
									and acc_businessp = '1'
									inner join dmdt
									on mac1.ac1_font_type = dmdt.mdt_doctype
									inner join tmac
									on tmac.mac_trans_id = mac1.ac1_font_key
									and tmac.mac_doctype = mac1.ac1_font_type
									inner join dmsn
									on mac1.ac1_card_type = dmsn.dms_card_type
									and mac1.ac1_legal_num = dmsn.dms_card_code
									where 1 = 1 and mac1.business = :business
									".$sql."
									and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":business" => $Data['business']));


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

	//listado de cuentas debito - credito POR año seleccionado
	public function AccountBalance_post(){

		$Data = $this->post();

		$PARAMS =  $this->generic->getParams();

		$decimalsystem = $this->generic->getDecimals();
		$decimals = str_pad(0, $decimalsystem, "0", STR_PAD_LEFT);
		$fields = array();

		$ac = "";
		$sql = " WHERE 1=1 ";
	 	$yr  = "";
		$inner = "";



		if( isset( $Data['lac_ac'] )  && !empty($Data['lac_ac']) ){
			$ac = $Data['lac_ac'];
		}

		// if( isset( $Data['lac_sn'] )  && !empty($Data['lac_sn']) ){
		// 	$sn = $Data['lac_sn'];
		// }

		if( isset( $Data['lac_yr'] )  && !empty($Data['lac_yr']) ){
			$yr = $Data['lac_yr'];
		}

		if( is_array($ac) ){
			$ac = implode(",",$ac);
			$sql .= ' AND dacc.acc_code IN('.$ac.')';
		}
		

		// if( isset( $Data['lac_sn'] )  && !empty($Data['lac_sn']) ){

		// 	$inner = "inner join mac1 on ac1_account = acc_code";
		// 	$sql .= " AND ac1_legal_num in ({$Data['lac_sn']})";
		// }

		if(isset( $Data['lac_family']) && !empty($Data['lac_family'])){
			$sql .= " AND acc_type in ({$Data['lac_family']})";
		}

		$sql .= ' AND dacc.acc_level = '.$PARAMS['acc_level'];


		$sqlSelect="SELECT distinct acc_code as codigocuenta,
				acc_name as nombredecuenta,
				to_char(coalesce(get_saldocuentames(acc_code,1,".$yr."),0), '{format}') as enero,
				to_char(coalesce(get_saldocuentames(acc_code,2,".$yr."),0), '{format}') as febrero,
				to_char(coalesce(get_saldocuentames(acc_code,3,".$yr."),0), '{format}') as marzo,
				to_char(coalesce(get_saldocuentames(acc_code,4,".$yr."),0), '{format}') as abril,
				to_char(coalesce(get_saldocuentames(acc_code,5,".$yr."),0), '{format}') as mayo,
				to_char(coalesce(get_saldocuentames(acc_code,6,".$yr."),0), '{format}') as junio,
				to_char(coalesce(get_saldocuentames(acc_code,7,".$yr."),0), '{format}') as julio,
				to_char(coalesce(get_saldocuentames(acc_code,8,".$yr."),0), '{format}') as agosto,
				to_char(coalesce(get_saldocuentames(acc_code,9,".$yr."),0), '{format}') as septiembre,
				to_char(coalesce(get_saldocuentames(acc_code,10,".$yr."),0), '{format}') as octubre,
				to_char(coalesce(get_saldocuentames(acc_code,11,".$yr."),0), '{format}') as noviembre,
				to_char(coalesce(get_saldocuentames(acc_code,12,".$yr."),0), '{format}') as diciembre,
				to_char(coalesce(get_saldocuentaano(acc_code,".$yr."),0), '{format}') as saldo
				from dacc {$inner} ".$sql;

		$sqlSelect = str_replace("{format}", "999,999,999,999.".$decimals, $sqlSelect);


		$resSelect = $this->pedeo->queryTable($sqlSelect, $fields);


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

	//listado de cuentas debito - credito por
	//rango de fechas
	public function AccountBalanceByDateRange_post(){

		$Data = $this->post();

		$ac = ""; // CUENTA CONTABLES
		$sn  = ""; // SOCIOS DE NEGOCIO
		$sql = "WHERE 1 = 1";
		$fi = "";	// FECHA INICIO
		$ff = ""; // FECHA FINAL
		$td = ""; // TIPO DOCUMENTO
		$gb = "";

		if( isset( $Data['lac_sn'] )  && !empty($Data['lac_sn']) ){
			$sn = json_decode($Data['lac_sn'], true);
		}

		if( isset( $Data['lac_ac'] )  && !empty($Data['lac_ac']) ){
			$ac = $Data['lac_ac'];
		}

		if( isset( $Data['lac_td'] )  && !empty($Data['lac_td']) ){
			$td = json_decode($Data['lac_td'], true);
		}


		if( isset( $Data['lac_ini'] )  && !empty($Data['lac_ini']) ){
			$fi = "'".$Data['lac_ini']."'";
			if( isset( $Data['lac_end'] )  && !empty($Data['lac_end']) ){
				$ff = "'".$Data['lac_end']."'";

				$sql .=' AND  mac1.ac1_doc_date BETWEEN '.$fi.' AND '.$ff.'';
			}
		}

		if( is_array($ac) ){
			$ac = implode($ac,",");
			$sql .= ' AND dacc.acc_code IN('.$ac.')';
		}


		if( is_array($td) ){
			$td = implode($td,",");
			$sql .= ' AND mac1.ac1_font_type IN('.$td.')';
		}

		if( is_array($sn) ){
			$opt = "";
			foreach ($sn as $key => $item) {
				$opt.= "'".$item."',";
			}
			$opt = substr($opt,0,strlen($opt) - 1);
			$sql .= ' AND mac1.ac1_legal_num IN('.$opt.')';
		}


		$sqlSelect="SELECT sum(ac1_debit) as valordebito,
								sum(ac1_credit) as valorcredito,
								sum(ac1_debit) - sum(ac1_credit) as saldo,
								ac1_legal_num as codigosn,
								acc_code,
								acc_name
								from mac1
								inner join dacc
								on  ac1_account = acc_code
								".$sql."
								group by ac1_account,ac1_legal_num,acc_code,acc_name";

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

	public function getPurch_get()
	{
		$request = $this->get();

		if(!isset($request['cardcode']) && !isset($request['business']) && !isset($request['branch'])){
			$respuesta = array(
				'error' => true,
				'data' => [],
				'mensaje' => 'Informacion enviada es invalida'
			);

			$this->response($respuesta);
			return;
		}

		$DECI_MALES =  $this->generic->getDecimals();

		$campos = ",T4.dms_phone1, T4.dms_phone2, T4.dms_cel";

		$sqlSelect = self::getColumn('dcpo', 'cpo', $campos, '', $DECI_MALES, $request['business'], $request['branch'],0,0,0," AND cpo_cardcode = :cpo_cardcode");

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(
			':cpo_cardcode' => $request['cardcode']
		));

		if(isset($resSelect[0])){
			$respuesta = array(
				'error' => false,
				'data' => $resSelect,
				'mensaje' => 'OK'
			);
		}else{
			$respuesta = array(
				'error' => true,
				'data' => [],
				'mensaje' => 'No se encontraron datos en la busqueda'
			);
		}

		$this->response($respuesta);
	}

	public function generatePdf_post(){
		$Data = $this->post();
		$mpdf = new \Mpdf\Mpdf(['setAutoBottomMargin' => 'stretch','setAutoTopMargin' => 'stretch','default_font' => 'dejavusans']);
		//INFORMACION DE LA EMPRESA
		$sqlEmpresa = "SELECT pge_id, pge_name_soc, pge_small_name, pge_add_soc, pge_state_soc, pge_city_soc,
		pge_cou_soc, CONCAT(bti_name,' ',pge_id_soc) AS pge_id_type , pge_web_site, pge_logo,
		CONCAT(pge_phone1,' ',pge_phone2,' ',pge_cel) AS pge_phone1, pge_branch, pge_mail,
		pge_curr_first, pge_curr_sys, pge_cou_bank, pge_bank_def,pge_bank_acct, pge_acc_type,pge_id_soc,pge_phone2,pge_page_social,
		concat(main_folder,'/',pge_logo) AS \"companyLogo\"
		FROM pgem 
		inner join tbti on pge_id_type = tbti.bti_id 
		left join params  on 1=1
		WHERE pge_id = :pge_id";

		$empresa = $this->pedeo->queryTable($sqlEmpresa, array(':pge_id' => $Data['business']));

		if(!isset($empresa[0])){
			$respuesta = array(
				'error' => true,
		        'data'  => $empresa,
	       		'mensaje' =>'no esta registrada la información de la empresa'
			);

	         $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	        return;
		}

		$pdfBloques = $this->pedeo->queryTable("SELECT * FROM tpdf where pdf_doctype = :pdf_doctype", array(':pdf_doctype' => $Data['doctype']));
		
		if(!isset($pdfBloques[0])){
			$respuesta = array(
				'error' => true,
				'data'  => $pdfBloques,
				'mensaje' =>'no se encontro la plantilla para este documento'
			);

			return $respuesta;
		}
		$stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');

		$sections = self::getSections($pdfBloques);
		
		$mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
		$header = self::makeHtml($sections["top"], $empresa);
		
		$footer = self::makeHtml($sections["footer"], $empresa);
		$detailsql = $this->pedeo->queryTable("SELECT pe1_vlrtotal as total from bpe1 b where pe1_docentry =  :docentry ", array(':docentry' => $Data['docentry']));

		$detail = self::makeDetail($sections["body"],$sections["table-detail"], $detailsql );
		//print_r($detail);exit;
		$mpdf->SetHTMLHeader($header);
		$mpdf->SetHTMLFooter($footer);
		$html = "<!DOCTYPE html>
				<html lang='en'>
				<head>
					<meta charset='UTF-8'>
					<meta name='viewport' content='width=device-width, initial-scale=1.0'>
				</head>
				<body>
					".$detail."
				</body>
				</html>";

		$mpdf->WriteHTML($html.$footer,\Mpdf\HTMLParserMode::HTML_BODY);

		$filename = 'Doc.pdf';
		print_r($mpdf->output());exit;
	}

	private function makeHtml($section, $data){
		$html = $section;
		// print_r($data);exit;
		$dataKeys = array_keys($data[0]);
		foreach ($data as $key => $info) {
			foreach ($dataKeys as $key => $value) {
			
				$html = str_replace("{".$value."}",$info[$value],$html);
			}
		}	

		return $html;

	}

	private function makeDetail($detailTable,$detailContent,$detailData){
		$html = $detailTable;

		$content = self::makeHtml($detailContent, $detailData);

		$html = str_replace("{detail_body}",$content,$html);

		return $html;
	}

	private function getSections($sections){
		$resutl = array();
		foreach ($sections as $key => $value) {
			$resutl[$value['pdf_section']] = $value["pdf_template"];
		}

		return $resutl;

	}

}
