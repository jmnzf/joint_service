<?php
// REPORTES DE INVENTARIO
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
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

	}

  //INFORME AUDITORIA STOCK
	public function getAuditoriaStock_post(){

      	$request = $this->post();

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

		$result = $this->pedeo->queryTable("SELECT tbmi.bmi_itemcode AS codigoarticulo,
																			dmar.dma_item_name AS nombrearticulo,
																			tbdi.bdi_whscode AS codigoalmacen,
																			dmws.dws_name AS nombrealmacen,
																			dmdt.mdt_docname AS docorigen,
																			tbmi.bmi_basenum docnum,
																			tbmi.bmi_createat AS fechadocnum,
																			tbmi.bmi_quantity AS cantidadmovida,
																			tbmi.bmi_cost AS costo,
																			tbmi.bmi_currequantity + tbmi.bmi_quantity AS cantidadrestante,
																			tbmi.bmi_currequantity AS cantidadantesdemovimiento,
																			(tbmi.bmi_cost * (tbmi.bmi_quantity + tbmi.bmi_currequantity)) costoacumulado,
																			tbmi.bmi_createby AS creadopor,tbmi.bmi_docdate AS fechadoc,tbmi.bmi_comment AS comentario
																			FROM tbmi
																			INNER JOIN tbdi ON tbmi.bmi_itemcode = tbdi.bdi_itemcode AND tbmi.bmi_whscode  = tbdi.bdi_whscode
																			INNER JOIN dmar ON tbmi.bmi_itemcode = dmar.dma_item_code
																			INNER JOIN dmdt ON tbmi.bmy_doctype = dmdt.mdt_doctype
																			INNER JOIN dmws ON tbmi.bmi_whscode = dmws.dws_code
																			WHERE 1=1".$sql." ORDER BY tbmi.bmi_createat DESC", $where);
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
						LEFT JOIN TBMI T4 ON T0.DVF_DOCTYPE = T4.BMY_DOCTYPE and t0.DVF_DOCENTRY = T4.BMY_BASEENTRY';


			$respuesta = $this->pedeo->queryTable($sql, array());

			if(isset($respuesta[0])){

				 $respuesta = array(
						'error'   => false,
						'data'    => $respuesta,
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


	//INFORME ANALISIS DE VENTAS DETALLADO
	public function getAnalisisVentaDetallado_post(){

			$Data = $this->post();

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
							LEFT JOIN DMWS T2 ON T1.FV1_WHSCODE = T2.DWS_CODE';


			$respuesta = $this->pedeo->queryTable($sql, array());

			if(isset($respuesta[0])){

				 $respuesta = array(
						'error'   => false,
						'data'    => $respuesta,
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

			$sql = 'SELECT
							T3.MDT_DOCNAME "TipoDocumento",
							T0.DPO_DOCNUM "NumeroFactura",
							T0.DPO_CARDCODE "CodigoCLiente",
							T0.DPO_CARDNAME "NombreCliente",
							T0.DPO_DOCDATE "FechaFactura",
							T0.DPO_BASEAMNT "BaseFactura",
							ROUND((T0.DPO_BASEAMNT * 19) / 100) "IvaFactura",
							COALESCE(T0.DPO_PAYTODAY,0) "ValorRecaudado",
							T2.MGS_NAME "GrupoCliente"
						FROM DCPO T0
						LEFT JOIN DMSN T1 ON T0.DPO_CARDCODE = T1.DMS_CARD_CODE
						LEFT JOIN DMGS T2 ON T1.DMS_GROUP_NUM = CAST(T2.MGS_ID AS VARCHAR)
						LEFT JOIN DMDT T3 ON T0.DPO_DOCTYPE = T3.MDT_DOCTYPE
						LEFT JOIN TBMI T4 ON T0.DPO_DOCTYPE = T4.BMY_DOCTYPE and t0.DPO_DOCENTRY = T4.BMY_BASEENTRY';


			$respuesta = $this->pedeo->queryTable($sql, array());

			if(isset($respuesta[0])){

				 $respuesta = array(
						'error'   => false,
						'data'    => $respuesta,
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

	//INFORME ANALISIS DE COMPRAS DETALLADO
	public function getAnalisisCompraDetallado_post(){

			$Data = $this->post();

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
						LEFT JOIN DMWS T2 ON T1.PO1_WHSCODE = T2.DWS_CODE';


			$respuesta = $this->pedeo->queryTable($sql, array());

			if(isset($respuesta[0])){

				 $respuesta = array(
						'error'   => false,
						'data'    => $respuesta,
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

			// $sql = "SELECT
			// 				T0.MAC_DOC_DATE,
			// 				T0.MAC_SERIE,
			// 				T0.MAC_DOC_NUM,
			// 				18 Tipo,
			// 				t0.MAC_TRANS_ID,
			// 				CONCAT(T2.PGU_NAME_USER,' ',T2.PGU_LNAME_USER) usuario,
			// 				T1.AC1_ACCOUNT,
			// 				T3.ACC_NAME,
			// 				T1.AC1_DEBIT,
			// 				T1.AC1_CREDIT,
			// 				T1.AC1_PRC_CODE,
			// 				T1.AC1_UNCODE,
			// 				T1.AC1_PRJ_CODE
			// 				FROM TMAC T0
			// 				LEFT JOIN MAC1 T1 ON T0.MAC_TRANS_ID = T1.AC1_TRANS_ID
			// 				LEFT JOIN PGUS T2 ON T0.MAC_MADE_USUER = T2.PGU_CODE_USER
			// 				LEFT JOIN DACC T3 ON T1.AC1_ACCOUNT = T3.ACC_CODE";
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
															left join dcnd t15 on t0.ac1_font_key = t15.cnd_docentry and t0.ac1_font_type = t15.cnd_doctype";

			$respuesta = $this->pedeo->queryTable($sql, array());

			if(isset($respuesta[0])){

				 $respuesta = array(
						'error'   => false,
						'data'    => $respuesta,
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

		if(isset($Data['fil_almacenId']) && !empty($Data['fil_almacenId'])){
			// ADD WHERE QUERY
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
						trim(t2.dma_item_name),
						t3.dmu_nameum,
						t1.bdi_whscode,
						t1.bdi_quantity,
						t1.bdi_avgprice,
						(t1.bdi_quantity * t1.bdi_avgprice) as costo
				from tbdi t1
				join dmar t2
				on t1.bdi_itemcode = t2.dma_item_code
				left join dmum t3 on t3.dmu_id =  t2.dma_uom_sale
				where 1 = 1".$where;

			$respuesta = $this->pedeo->queryTable($sql, $array);

			if(isset($respuesta[0])){

				 $respuesta = array(
						'error'   => false,
						'data'    => $respuesta,
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
			// print_r($Data);exit();die();

// print_r($where);exit();die();
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
									 WHERE '".$Data['fecha']."' >= t0.dvf_duedate
									ORDER BY CodigoCliente asc";

// print_r($sql);exit();
			$respuesta = $this->pedeo->queryTable($sql, array());

			if(isset($respuesta[0])){

				 $respuesta = array(
						'error'   => false,
						'data'    => $respuesta,
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



	public function EstadoCuentaCl_post(){

				$Data = $this->post();

				$where1 = '';
				$where2 = '';
				$where3 = '';

				if(isset($Data['cardcode']) && !empty($Data['cardcode'])){
					$where1 = '  and t0.dvf_cardcode in ('.$Data['cardcode'].')';
					$where2 = '  and t0.vnc_cardcode in ('.$Data['cardcode'].')';
					$where3 = '  and t0.vnd_cardcode in ('.$Data['cardcode'].')';

				$sql = "SELECT distinct
									    'Factura' as tipo,
										t0.dvf_docentry docentry,
										t0.dvf_doctype doctype,
										t0.dvf_cardcode CodigoProveedor,
										t0.dvf_cardname NombreProveedor,
										t0.dvf_docnum NumeroDocumento,
										t0.dvf_docdate FechaDocumento,
										t0.dvf_duedate FechaVencimiento,
										(t0.dvf_doctotal - coalesce(T0.dvf_paytoday,0)) totalfactura,
										trim('COP' FROM t0.dvf_currency) MonedaDocumento,
										'".$Data['fecha']."'  FechaCorte,
										('".$Data['fecha']."'  - t0.dvf_duedate) dias,
										CASE
											WHEN ( '".$Data['fecha']."'  - t0.dvf_duedate) >=0 and ( '".$Data['fecha']."'  - t0.dvf_duedate) <=30
												then (t0.dvf_doctotal - COALESCE(t0.dvf_paytoday,0))
												ELSE 0
										END uno_treinta,
										CASE
											WHEN ( '".$Data['fecha']."'  - t0.dvf_duedate) >=31 and ( '".$Data['fecha']."'  - t0.dvf_duedate) <=60
												then (t0.dvf_doctotal - COALESCE(t0.dvf_paytoday,0))
												ELSE 0
										END treinta_uno_secenta,
										CASE
											WHEN ( '".$Data['fecha']."'  - t0.dvf_duedate) >=61 and ( '".$Data['fecha']."'  - t0.dvf_duedate) <=90
												then (t0.dvf_doctotal - COALESCE(t0.dvf_paytoday,0))
												ELSE 0
										END secenta_uno_noventa,
										CASE
											WHEN ( '".$Data['fecha']."'  - t0.dvf_duedate) >=91
												then (t0.dvf_doctotal - COALESCE(t0.dvf_paytoday,0))
												ELSE 0
										END mayor_noventa,
									    T1.ac1_account,
									    T1.ac1_legal_num

									FROM dvfv t0
									left join mac1 t1 on t0.dvf_docentry = t1.ac1_font_key and t0.dvf_doctype = t1.ac1_font_type
									join dacc t2 on t2.acc_code = t1.ac1_account and t2.acc_businessp = '1'
									WHERE '".$Data['fecha']."'  >= t0.dvf_duedate
									and (t0.dvf_doctotal - coalesce(T0.dvf_paytoday,0))  <> 0 and 1 = 1 ".$where1."

									union all

									SELECT distinct
									    'NotaCredito' as tipo,
											t0.vnc_docentry,
											t0.vnc_doctype,
										t0.vnc_cardcode CodigoProveedor,
										t0.vnc_cardname NombreProveedor,
										t0.vnc_docnum NumeroDocumento,
										t0.vnc_docdate FechaDocumento,
										t0.vnc_duedate FechaVencimiento,
									(t0.vnc_doctotal - coalesce(T0.vnc_paytoday,0))  * -1  totalfactura,
										trim('COP' FROM t0.vnc_currency) MonedaDocumento,
										'".$Data['fecha']."'  FechaCorte,
										('".$Data['fecha']."'  - t0.vnc_duedate) dias,
										CASE
											WHEN ( '".$Data['fecha']."'  - t0.vnc_duedate) >=0 and ( '".$Data['fecha']."'  - t0.vnc_duedate) <=30
												then (t0.vnc_doctotal * -1)
												ELSE 0
										END uno_treinta,
										CASE
											WHEN ( '".$Data['fecha']."'  - t0.vnc_duedate) >=31 and ( '".$Data['fecha']."'  - t0.vnc_duedate) <=60
												then (t0.vnc_doctotal * -1)
												ELSE 0
										END treinta_uno_secenta,
										CASE
											WHEN ( '".$Data['fecha']."'  - t0.vnc_duedate) >=61 and ( '".$Data['fecha']."'  - t0.vnc_duedate) <=90
												then (t0.vnc_doctotal * -1)
												ELSE 0
										END secenta_uno_noventa,
										CASE
											WHEN ( '".$Data['fecha']."'  - t0.vnc_duedate) >=91
												then (t0.vnc_doctotal * -1)
												ELSE 0
										END mayor_noventa,
									    T1.ac1_account,
									    T1.ac1_legal_num

									FROM dvnc t0
									left join mac1 t1 on t0.vnc_docentry = t1.ac1_font_key and t0.vnc_doctype = t1.ac1_font_type
									join dacc t2 on t2.acc_code = t1.ac1_account and t2.acc_businessp = '1'
									WHERE '".$Data['fecha']."'  >= t0.vnc_duedate and ((t0.vnc_doctotal ) - (coalesce(T0.vnc_paytoday,0))) <> 0 and 1 = 1 ".$where2."

									union all

									SELECT distinct
									    'NotaDebito' as tipo,
											t0.vnd_docentry,
											t0.vnd_doctype,
										t0.vnd_cardcode CodigoProveedor,
										t0.vnd_cardname NombreProveedor,
										t0.vnd_docnum NumeroDocumento,
										t0.vnd_docdate FechaDocumento,
										t0.vnd_duedate FechaVencimiento,
										(t0.vnd_doctotal - coalesce(T0.vnd_paytoday,0))  totalfactura,
										trim('COP' FROM t0.vnd_currency) MonedaDocumento,
										'".$Data['fecha']."'  FechaCorte,
										('".$Data['fecha']."'  - t0.vnd_duedate) dias,
										CASE
											WHEN ( '".$Data['fecha']."'  - t0.vnd_duedate) >=0 and ( '".$Data['fecha']."'  - t0.vnd_duedate) <=30
												then (t0.vnd_doctotal )
												ELSE 0
										END uno_treinta,
										CASE
											WHEN ( '".$Data['fecha']."'  - t0.vnd_duedate) >=31 and ( '".$Data['fecha']."'  - t0.vnd_duedate) <=60
												then (t0.vnd_doctotal )
												ELSE 0
										END treinta_uno_secenta,
										CASE
											WHEN ( '".$Data['fecha']."'  - t0.vnd_duedate) >=61 and ( '".$Data['fecha']."'  - t0.vnd_duedate) <=90
												then (t0.vnd_doctotal )
												ELSE 0
										END secenta_uno_noventa,
										CASE
											WHEN ( '".$Data['fecha']."'  - t0.vnd_duedate) >=91
												then (t0.vnd_doctotal )
												ELSE 0
										END mayor_noventa,
									    T1.ac1_account,
									    T1.ac1_legal_num

									FROM dvnd t0
									left join mac1 t1 on t0.vnd_docentry = t1.ac1_font_key and t0.vnd_doctype = t1.ac1_font_type
									join dacc t2 on t2.acc_code = t1.ac1_account and t2.acc_businessp = '1'
									WHERE '".$Data['fecha']."'  >= t0.vnd_duedate and (t0.vnd_doctotal - coalesce(T0.vnd_paytoday,0)) <> 0 and 1 = 1 ".$where3."

									ORDER BY NumeroDocumento";
		// ID ARTICULO.
// print_r($sql);exit();die();

				$result = $this->pedeo->queryTable($sql,array());
// print_r($sql);exit();die();
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
}


	// OBTENER ACIENTO CONTABLE POR ID
	public function getLedger_get(){

				$Data = $this->get();
				$where = '';


				if(isset($Data['cardcode']) && !empty($Data['cardcode'])){
					$where = '  and t0.ac1_legal_num in ('.$Data['cardcode'].')';

				}if(isset($Data['account']) && !empty($Data['account'])){
					$where = $where. '  and t0.ac1_account in ('.$Data['account'].')';

				}if(isset($Data['fechaini']) && isset($Data['fechafin']) ){
					$where = $where.'  and t0.ac1_doc_date between '.$Data['fechaini'].' and '.$Data['fechafin'];
				}if(isset($Data['costCenter']) && !empty($Data['costCenter'])){
					$where = $where. '  and t0.ac1_prc_code in ('.$Data['costCenter'].')';

				}if(isset($Data['unitB']) && !empty($Data['unitB'])){
					$where = $where. '  and t0.ac1_uncode in ('.$Data['unitB'].')';

				}if(isset($Data['project']) && !empty($Data['project'])){
					$where = $where. '  and t0.ac1_prj_code in ('.$Data['project'].')';

				}


//
//
// 				if(!isset($Data['cardcode']) && !empty($Data['cardcode']) OR
// 			    !isset($Data['fechaini']) && !isset($Data['fechafin']) OR
// 					!isset($Data['account']) && !empty($Data['account'])){
// 					$array = array();
// 					$array1 = array();
// 					foreach (explode(',',$Data['cardcode']) as $key => $value) {
// 						array_push($array,"'".$value."'");
// 					}
//
// 					$where = '  and T0.ac1_legal_num IN ('.implode(',',$array).') and t0.ac1_account in ('.$Data['account'].') and t0.ac1_doc_date between '.$Data['fechaini'].' and '.$Data['fechafin'];
//
// }

				$sqlSelect = "SELECT
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
																COALESCE(t4.acc_name,'CUENTA PUENTE') nombre_cuenta,t0.*
																from mac1 t0
																left join dvem t1 on t0.ac1_font_key = t1.vem_docentry and t0.ac1_font_type = t1.vem_doctype
																left join dvdv t2 on t0.ac1_font_key = t2.vdv_docentry and t0.ac1_font_type = t2.vdv_doctype
																left join dvfv t3 on t0.ac1_font_key = t3.dvf_docentry and t0.ac1_font_type = t3.dvf_doctype
																Left join dacc t4 on t0.ac1_account = t4.acc_code
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
																WHERE 1=1 ".$where;

				$resSelect = $this->pedeo->queryTable($sqlSelect,array());
// print_r($sqlSelect);exit();die();
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


	// PARA REPORTE LIBRO DIARIO DE VENTAS
	public function  logBook_post(){
		$Data = $this->post();

    if( !isset($Data['dvf_docdate']) OR
        !isset($Data['dvf_duedate'])
      ){
      $this->response(array(
        'error'  => true,
        'data'   => [],
        'mensaje'=>'La informacion enviada no es valida'
      ), REST_Controller::HTTP_BAD_REQUEST);

      return ;
    }

		$sqlSelect = "SELECT
									fv.dvf_docentry, fv.dvf_docdate, dmdt.mdt_docname as dvf_doctype, fv.dvf_docnum, fv.dvf_correl,
									fv.dvf_cardcode, fv.dvf_cardname, dvf_doctotal, '' excentas, fv.dvf_baseamnt, fv.dvf_taxtotal
									from dvfv fv
									inner join dmdt
									on dvf_doctype = mdt_doctype
									WHERE fv.dvf_docdate >= :dvf_docdate and fv.dvf_duedate <=:dvf_duedate";

		$resSelect = $this->pedeo->queryTable($sqlSelect,array(":dvf_docdate" =>$Data['dvf_docdate'],":dvf_duedate"=>$Data['dvf_duedate']));
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

		if( !isset($Data['cfc_docdate']) OR
				!isset($Data['cfc_duedate'])
			){
			$this->response(array(
				'error'  => true,
				'data'   => [],
				'mensaje'=>'La informacion enviada no es valida'
			), REST_Controller::HTTP_BAD_REQUEST);

			return ;
		}

		$sqlSelect = "SELECT
									fc.cfc_docentry, fc.cfc_docdate, dmdt.mdt_docname as cfc_doctype, fc.cfc_docnum, 0 as cfc_correl,
									fc.cfc_cardcode, fc.cfc_cardname, cfc_doctotal, '' excentas, fc.cfc_baseamnt, fc.cfc_taxtotal
									from dcfc fc
									inner join dmdt
									on cfc_doctype = mdt_doctype
									WHERE fc.cfc_docdate >= :cfc_docdate and fc.cfc_duedate <=:cfc_duedate";

		$resSelect = $this->pedeo->queryTable($sqlSelect,array(":cfc_docdate" =>$Data['cfc_docdate'],":cfc_duedate"=>$Data['cfc_duedate']));
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

}
