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
					cast(t1.bdi_quantity as decimal(15,2)) bdi_quantity,
					cast(t1.bdi_avgprice as decimal(15,2)) bdi_avgprice,
					cast((t1.bdi_quantity * t1.bdi_avgprice) as decimal(15,2)) as costo
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


	// ESTADO DE CUENTA PARA CLIENTE Y PROVEEDOR
	public function EstadoCuentaCl_post(){

				$Data = $this->post();
				$fecha = "";

				if(isset($Data['cardcode']) && !empty($Data['cardcode']) && isset($Data['cardtype'])){

					if( isset($Data['fecha']) ){
						$fecha = $Data['fecha'];
					}else{
						$fecha = 'CURRENT_DATE';
					}

					$sql = "";

					if( $Data['cardtype'] == 1 ){

						$sql = "SELECT distinct
						mac1.ac1_font_key,
						mac1.ac1_legal_num,
						mac1.ac1_legal_num as codigoproveedor,
						mac1.ac1_account as cuenta,
						mac1.ac1_account,
						CURRENT_DATE - dvf_duedate dias,
						CURRENT_DATE - dvf_duedate dias_atrasado,
						dvfv.dvf_comment,
						get_localcur() as currency,
						mac1.ac1_font_key as dvf_docentry,
						mac1.ac1_font_key as docentry,
						dvfv.dvf_docnum,
						dvfv.dvf_docnum as numerodocumento,
						dvfv.dvf_docdate as fecha_doc,
						dvfv.dvf_docdate as fechadocumento,
						dvfv.dvf_duedate as fecha_ven,
						dvfv.dvf_duedate as fechavencimiento,
						dvf_docnum as id_origen,
						mac1.ac1_font_type as numtype,
						mac1.ac1_font_type as doctype,
						mdt_docname as tipo,
						case
							when mac1.ac1_font_type = 5 then  get_dynamic_conversion(:currency, dvf_currency,dvf_docdate,mac1.ac1_debit ,get_localcur())
							else get_dynamic_conversion(:currency, dvf_currency,dvf_docdate,mac1.ac1_credit ,get_localcur())
						end	 as total_doc,
						case
							when mac1.ac1_font_type = 5 then get_dynamic_conversion(:currency, dvf_currency,dvf_docdate,mac1.ac1_debit ,get_localcur())
							else get_dynamic_conversion(:currency, dvf_currency,dvf_docdate,mac1.ac1_credit ,get_localcur())
						end	 as totalfactura,
						get_dynamic_conversion(:currency, dvf_currency,dvf_docdate,(mac1.ac1_debit) - (mac1.ac1_ven_credit) ,get_localcur()) as saldo_venc,
						'' retencion,
						get_tax_currency(dvfv.dvf_currency, dvfv.dvf_docdate) as tasa_dia,
						dvfv.dvf_cardname as nombreproveedor,
						get_localcur() as monedadocumento,
						:fecha as  fechacorte,
						ac1_line_num,
						ac1_cord
						from  mac1
						inner join dacc
						on mac1.ac1_account = dacc.acc_code
						and acc_businessp = '1'
						inner join dmdt
						on mac1.ac1_font_type = dmdt.mdt_doctype
						inner join dvfv
						on dvfv.dvf_doctype = mac1.ac1_font_type
						and dvfv.dvf_docentry = mac1.ac1_font_key
						where mac1.ac1_legal_num = :cardcode
						and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
						--ANTICIPO CLIENTE
						union all
						select distinct
						mac1.ac1_font_key,
						mac1.ac1_legal_num,
						mac1.ac1_legal_num as codigoproveedor,
						mac1.ac1_account as cuenta,
						mac1.ac1_account,
						0 as dias,
						0 as dias_atrasado,
						gbpr.bpr_comments,
						get_localcur() as currency,
						mac1.ac1_font_key as dvf_docentry,
						mac1.ac1_font_key as docentry,
						gbpr.bpr_docnum,
						gbpr.bpr_docnum as numerodocumento,
						gbpr.bpr_docdate as fecha_doc,
						gbpr.bpr_docdate as fechadocumento,
						gbpr.bpr_docdate as fecha_ven,
						gbpr.bpr_docdate as fechavencimiento,
						bpr_docnum as id_origen,
						mac1.ac1_font_type as numtype,
						mac1.ac1_font_type as doctype,
						mdt_docname as tipo,
						case
							when mac1.ac1_font_type = 5 then  get_dynamic_conversion(:currency, get_localcur(),bpr_docdate,mac1.ac1_debit ,get_localcur())
							else get_dynamic_conversion(:currency, get_localcur(),bpr_docdate,mac1.ac1_credit ,get_localcur())
						end	 as total_doc,
						case
							when mac1.ac1_font_type = 5 then get_dynamic_conversion(:currency, get_localcur(),bpr_docdate,mac1.ac1_debit ,get_localcur()) * -1
							else  get_dynamic_conversion(:currency, get_localcur(),bpr_docdate,mac1.ac1_credit ,get_localcur()) * -1
						end	 as totalfactura,
						 get_dynamic_conversion(:currency, get_localcur(),bpr_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) ,get_localcur()) as saldo_venc,
						'' retencion,
						get_tax_currency(gbpr.bpr_currency, gbpr.bpr_docdate) as tasa_dia,
						gbpr.bpr_cardname as nombreproveedor,
						get_localcur() as monedadocumento,
						:fecha as  fechacorte,
						ac1_line_num,
						ac1_cord
						from  mac1
						inner join dacc
						on mac1.ac1_account = dacc.acc_code
						and acc_businessp = '1'
						inner join dmdt
						on mac1.ac1_font_type = dmdt.mdt_doctype
						inner join gbpr
						on gbpr.bpr_doctype = mac1.ac1_font_type
						and gbpr.bpr_docentry = mac1.ac1_font_key
						where mac1.ac1_legal_num = :cardcode
						and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
						--NOTA CREDITO
						union all
						select distinct
						mac1.ac1_font_key,
						mac1.ac1_legal_num,
						mac1.ac1_legal_num as codigoproveedor,
						mac1.ac1_account as cuenta,
						mac1.ac1_account,
						CURRENT_DATE - vnc_duedate dias,
						CURRENT_DATE - vnc_duedate dias_atrasado,
						dvnc.vnc_comment,
						get_localcur() as currency,
						mac1.ac1_font_key as dvf_docentry,
						mac1.ac1_font_key as docentry,
						dvnc.vnc_docnum,
						dvnc.vnc_docnum as numerodocumento,
						dvnc.vnc_docdate as fecha_doc,
						dvnc.vnc_docdate as fechadocumento,
						dvnc.vnc_duedate as fecha_ven,
						dvnc.vnc_duedate as fechavencimiento,
						vnc_docnum as id_origen,
						mac1.ac1_font_type as numtype,
						mac1.ac1_font_type as doctype,
						mdt_docname as tipo,
						case
							when mac1.ac1_font_type = 5 then get_dynamic_conversion(:currency, get_localcur(),vnc_docdate,mac1.ac1_debit ,get_localcur())
							else get_dynamic_conversion(:currency, get_localcur(),vnc_docdate,mac1.ac1_credit ,get_localcur())
						end	 as total_doc,
						case
							when mac1.ac1_font_type = 5 then  get_dynamic_conversion(:currency, get_localcur(),vnc_docdate,mac1.ac1_debit,get_localcur()) * -1
							else  get_dynamic_conversion(:currency, get_localcur(),vnc_docdate,mac1.ac1_credit ,get_localcur()) * -1
						end	 as totalfactura,
						get_dynamic_conversion(:currency, get_localcur(),vnc_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) ,get_localcur())  as saldo_venc,
						'' retencion,
						get_tax_currency(dvnc.vnc_currency,	dvnc.vnc_docdate) as tasa_dia,
						dvnc.vnc_cardname as nombreproveedor,
						get_localcur() as monedadocumento,
						:fecha as  fechacorte,
						ac1_line_num,
						ac1_cord
						from  mac1
						inner join dacc
						on mac1.ac1_account = dacc.acc_code
						and acc_businessp = '1'
						inner join dmdt
						on mac1.ac1_font_type = dmdt.mdt_doctype
						inner join dvnc
						on dvnc.vnc_doctype = mac1.ac1_font_type
						and dvnc.vnc_docentry = mac1.ac1_font_key
						where mac1.ac1_legal_num = :cardcode
						and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
						--NOTA DEBITO
						union all
						select distinct
						mac1.ac1_font_key,
						mac1.ac1_legal_num,
						mac1.ac1_legal_num as codigoproveedor,
						mac1.ac1_account as cuenta,
						mac1.ac1_account,
						CURRENT_DATE - vnd_duedate dias,
						CURRENT_DATE - vnd_duedate dias_atrasado,
						dvnd.vnd_comment,
						get_localcur() as currency,
						mac1.ac1_font_key as dvf_docentry,
						mac1.ac1_font_key as docentry,
						dvnd.vnd_docnum,
						dvnd.vnd_docnum as numerodocumento,
						dvnd.vnd_docdate as fecha_doc,
						dvnd.vnd_docdate as fechadocumento,
						dvnd.vnd_duedate as fecha_ven,
						dvnd.vnd_duedate as fechavencimiento,
						vnd_docnum as id_origen,
						mac1.ac1_font_type as numtype,
						mac1.ac1_font_type as doctype,
						mdt_docname as tipo,
						case
							when mac1.ac1_font_type = 5 then  get_dynamic_conversion(:currency, get_localcur(),vnd_docdate,mac1.ac1_debit ,get_localcur())
							else get_dynamic_conversion(:currency, get_localcur(),vnd_docdate,mac1.ac1_credit ,get_localcur())
						end	 as total_doc,
						case
							when mac1.ac1_font_type = 5 then  get_dynamic_conversion(:currency, get_localcur(),vnd_docdate,mac1.ac1_debit ,get_localcur())
							else get_dynamic_conversion(:currency, get_localcur(),vnd_docdate,mac1.ac1_credit ,get_localcur())
						end	 as totalfactura,
						get_dynamic_conversion(:currency, get_localcur(),vnd_docdate,(mac1.ac1_debit) - (mac1.ac1_ven_credit) ,get_localcur()) as saldo_venc,
						'' retencion,
						get_tax_currency(dvnd.vnd_currency, dvnd.vnd_docdate) as tasa_dia,
						dvnd.vnd_cardname as nombreproveedor,
						get_localcur() as monedadocumento,
						:fecha as  fechacorte,
						ac1_line_num,
						ac1_cord
						from  mac1
						inner join dacc
						on mac1.ac1_account = dacc.acc_code
						and acc_businessp = '1'
						inner join dmdt
						on mac1.ac1_font_type = dmdt.mdt_doctype
						inner join dvnd
						on dvnd.vnd_doctype = mac1.ac1_font_type
						and dvnd.vnd_docentry = mac1.ac1_font_key
						where mac1.ac1_legal_num = :cardcode
						and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
						--ASIENTOS MANUALES
						union all
						select distinct
						mac1.ac1_font_key,
						mac1.ac1_legal_num,
						mac1.ac1_legal_num as codigoproveedor,
						mac1.ac1_account as cuenta,
						mac1.ac1_account,
						CURRENT_DATE - tmac.mac_doc_duedate dias,
						CURRENT_DATE - tmac.mac_doc_duedate dias_atrasado,
						tmac.mac_comments,
						get_localcur() as currency,
						0 as dvf_docentry,
						0 as docentry,
						0 as docnum,
						0 as numerodocumento,
						tmac.mac_doc_date as fecha_doc,
						tmac.mac_doc_date as fechadocumento,
						tmac.mac_doc_duedate as fecha_ven,
						tmac.mac_doc_duedate as fechavencimiento,
						0 as id_origen,
						18 as numtype,
						18 as doctype,
						mdt_docname as tipo,
						case
							when mac1.ac1_cord = 0 then get_dynamic_conversion(:currency, get_localcur(),mac_doc_date, mac1.ac1_debit ,get_localcur())
							when mac1.ac1_cord = 1 then get_dynamic_conversion(:currency, get_localcur(),mac_doc_date, mac1.ac1_credit ,get_localcur())
						end	 as total_doc,
						case
							when mac1.ac1_cord = 0 then get_dynamic_conversion(:currency, get_localcur(),mac_doc_date, mac1.ac1_debit ,get_localcur())
							when mac1.ac1_cord = 1 then get_dynamic_conversion(:currency, get_localcur(),mac_doc_date, mac1.ac1_credit ,get_localcur())
						end	 as totalfactura,
						 get_dynamic_conversion(:currency, get_localcur(),mac_doc_date, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) ,get_localcur()) as saldo_venc,
						'' retencion,
						get_tax_currency(tmac.mac_currency, tmac.mac_doc_date) as tasa_dia,
						dmsn.dms_card_name as nombreproveedor,
						get_localcur() as monedadocumento,
						:fecha as  fechacorte,
						ac1_line_num,
						ac1_cord
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
						where mac1.ac1_legal_num = :cardcode
						and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0";

					}else if ( $Data['cardtype'] == 2 ){

						$sql = "SELECT distinct
						mac1.ac1_font_key,
						mac1.ac1_legal_num,
						mac1.ac1_legal_num as codigoproveedor,
						mac1.ac1_account as cuenta,
						mac1.ac1_account,
						CURRENT_DATE - cfc_duedate dias,
						CURRENT_DATE - cfc_duedate dias_atrasado,
						dcfc.cfc_comment,
						get_localcur() as currency,
						mac1.ac1_font_key as dvf_docentry,
						mac1.ac1_font_key as docentry,
						dcfc.cfc_docnum,
						dcfc.cfc_docnum as numerodocumento,
						dcfc.cfc_docdate as fecha_doc,
						dcfc.cfc_docdate as fechadocumento,
						dcfc.cfc_duedate as fecha_ven,
						dcfc.cfc_duedate as fechavencimiento,
						cfc_docnum as id_origen,
						mac1.ac1_font_type as numtype,
						mac1.ac1_font_type as doctype,
						mdt_docname as tipo,
						case
							when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency, get_localcur(),cfc_docdate,mac1.ac1_credit,get_localcur())
							else get_dynamic_conversion(:currency, get_localcur(),cfc_docdate,mac1.ac1_debit,get_localcur())
						end	 as total_doc,
						case
							when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency, get_localcur(),cfc_docdate,mac1.ac1_credit,get_localcur())
							else get_dynamic_conversion(:currency, get_localcur(),cfc_docdate,mac1.ac1_debit,get_localcur())
						end	 as totalfactura,
						 get_dynamic_conversion(:currency, get_localcur(),cfc_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_credit) ,get_localcur())  as saldo_venc,
						'' retencion,
						get_tax_currency(dcfc.cfc_currency,dcfc.cfc_docdate) as tasa_dia,
						dcfc.cfc_cardname as nombreproveedor,
						get_localcur() as monedadocumento,
						:fecha as  fechacorte,
						ac1_line_num,
						ac1_cord
						from  mac1
						inner join dacc
						on mac1.ac1_account = dacc.acc_code
						and acc_businessp = '1'
						inner join dmdt
						on mac1.ac1_font_type = dmdt.mdt_doctype
						inner join dcfc
						on dcfc.cfc_doctype = mac1.ac1_font_type
						and dcfc.cfc_docentry = mac1.ac1_font_key
						where mac1.ac1_legal_num = :cardcode
						and ABS((mac1.ac1_ven_credit) - (mac1.ac1_ven_debit)) > 0
						--PAGO EFECTUADO
						union all
						select distinct
						mac1.ac1_font_key,
						mac1.ac1_legal_num,
						mac1.ac1_legal_num as codigoproveedor,
						mac1.ac1_account as cuenta,
						mac1.ac1_account,
						CURRENT_DATE - gbpe.bpe_docdate as dias,
						CURRENT_DATE - gbpe.bpe_docdate as dias_atrasado,
						gbpe.bpe_comments as bpr_comment,
						get_localcur() as currency,
						mac1.ac1_font_key as dvf_docentry,
						mac1.ac1_font_key as docentry,
						gbpe.bpe_docnum,
						gbpe.bpe_docnum as numerodocumento,
						gbpe.bpe_docdate as fecha_doc,
						gbpe.bpe_docdate as fechadocumento,
						gbpe.bpe_docdate as fecha_ven,
						gbpe.bpe_docdate as fechavencimiento,
						gbpe.bpe_docnum as id_origen,
						mac1.ac1_font_type as numtype,
						mac1.ac1_font_type as doctype,
						mdt_docname as tipo,
						case
							when mac1.ac1_font_type = 15 then  get_dynamic_conversion(:currency, get_localcur(),bpe_docdate,mac1.ac1_debit ,get_localcur())
							else get_dynamic_conversion(:currency, get_localcur(),bpe_docdate,mac1.ac1_debit ,get_localcur())
						end	 as total_doc,
						case
							when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency, get_localcur(),bpe_docdate,mac1.ac1_debit ,get_localcur())
							else get_dynamic_conversion(:currency, get_localcur(),bpe_docdate,mac1.ac1_debit ,get_localcur())
						end	 as totalfactura,
						get_dynamic_conversion(:currency, get_localcur(),bpe_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) ,get_localcur()) as saldo_venc,
						'' retencion,
						get_tax_currency(gbpe.bpe_currency, gbpe.bpe_docdate) as tasa_dia,
						gbpe.bpe_cardname as nombreproveedor,
						get_localcur() as monedadocumento,
						:fecha as  fechacorte,
						ac1_line_num,
						ac1_cord
						from  mac1
						inner join dacc
						on mac1.ac1_account = dacc.acc_code
						and acc_businessp = '1'
						inner join dmdt
						on mac1.ac1_font_type = dmdt.mdt_doctype
						inner join gbpe
						on gbpe.bpe_doctype = mac1.ac1_font_type
						and gbpe.bpe_docentry = mac1.ac1_font_key
						where mac1.ac1_legal_num = :cardcode
						and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
						--NOTA CREDITO
						union all
						select distinct
						mac1.ac1_font_key,
						mac1.ac1_legal_num,
						mac1.ac1_legal_num as codigoproveedor,
						mac1.ac1_account as cuenta,
						mac1.ac1_account,
						CURRENT_DATE - dcnc.cnc_docdate dias,
						CURRENT_DATE - dcnc.cnc_docdate as dias_atrasado,
						dcnc.cnc_comment as bpr_comment,
						get_localcur() as currency,
						mac1.ac1_font_key as dvf_docentry,
						mac1.ac1_font_key as docentry,
						dcnc.cnc_docnum,
						dcnc.cnc_docnum as numerodocumento,
						dcnc.cnc_docdate as fecha_doc,
						dcnc.cnc_docdate as fechadocumento,
						dcnc.cnc_duedate as fecha_ven,
						dcnc.cnc_duedate as fechavencimiento,
						dcnc.cnc_docnum as id_origen,
						mac1.ac1_font_type as numtype,
						mac1.ac1_font_type as doctype,
						mdt_docname as tipo,
						case
							when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency, get_localcur(),cnc_docdate,mac1.ac1_debit ,get_localcur())
							else get_dynamic_conversion(:currency, get_localcur(),cnc_docdate,mac1.ac1_debit ,get_localcur())
						end	 as total_doc,
						case
							when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency, get_localcur(),cnc_docdate,mac1.ac1_debit ,get_localcur())
							else get_dynamic_conversion(:currency, get_localcur(),cnc_docdate,mac1.ac1_debit ,get_localcur())
						end	 as totalfactura,
						get_dynamic_conversion(:currency, get_localcur(),cnc_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)  ,get_localcur()) as saldo_venc,
						'' retencion,
						get_tax_currency(dcnc.cnc_currency, dcnc.cnc_docdate) as tasa_dia,
						dcnc.cnc_cardname as nombreproveedor,
						get_localcur() as monedadocumento,
						:fecha as  fechacorte,
						ac1_line_num,
						ac1_cord
						from  mac1
						inner join dacc
						on mac1.ac1_account = dacc.acc_code
						and acc_businessp = '1'
						inner join dmdt
						on mac1.ac1_font_type = dmdt.mdt_doctype
						inner join dcnc
						on dcnc.cnc_doctype = mac1.ac1_font_type
						and dcnc.cnc_docentry = mac1.ac1_font_key
						where mac1.ac1_legal_num = :cardcode
						and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
						--NOTA DEBITO
						union all
						select distinct
						mac1.ac1_font_key,
						mac1.ac1_legal_num,
						mac1.ac1_legal_num as codigoproveedor,
						mac1.ac1_account as cuenta,
						mac1.ac1_account,
						CURRENT_DATE - dcnd.cnd_docdate as dias,
						CURRENT_DATE - dcnd.cnd_docdate as dias_atrasado,
						dcnd.cnd_comment as bpr_comment,
						get_localcur() as currency,
						mac1.ac1_font_key as dvf_docentry,
						mac1.ac1_font_key as docentry,
						dcnd.cnd_docnum,
						dcnd.cnd_docnum as numerodocumento,
						dcnd.cnd_docdate as fecha_doc,
						dcnd.cnd_docdate as fechadocumento,
						dcnd.cnd_duedate as fecha_ven,
						dcnd.cnd_duedate as fechavencimiento,
						dcnd.cnd_docnum as id_origen,
						mac1.ac1_font_type as numtype,
						mac1.ac1_font_type as doctype,
						mdt_docname as tipo,
						case
							when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency, get_localcur(),cnd_docdate,mac1.ac1_debit ,get_localcur())
							else get_dynamic_conversion(:currency, get_localcur(),cnd_docdate,mac1.ac1_credit ,get_localcur())
						end	 as total_doc,
						case
							when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency, get_localcur(),cnd_docdate,mac1.ac1_debit ,get_localcur())
							else get_dynamic_conversion(:currency, get_localcur(),cnd_docdate,mac1.ac1_credit ,get_localcur())
						end	 as totalfactura,
						get_dynamic_conversion(:currency, get_localcur(),cnd_docdate,(mac1.ac1_ven_credit) - (mac1.ac1_debit) ,get_localcur()) as saldo_venc,
						'' retencion,
						get_tax_currency(dcnd.cnd_currency, dcnd.cnd_docdate) as tasa_dia,
						dcnd.cnd_cardname as nombreproveedor,
						get_localcur() as monedadocumento,
						:fecha as  fechacorte,
						ac1_line_num,
						ac1_cord
						from  mac1
						inner join dacc
						on mac1.ac1_account = dacc.acc_code
						and acc_businessp = '1'
						inner join dmdt
						on mac1.ac1_font_type = dmdt.mdt_doctype
						inner join dcnd
						on dcnd.cnd_doctype = mac1.ac1_font_type
						and dcnd.cnd_docentry = mac1.ac1_font_key
						where mac1.ac1_legal_num = :cardcode
						and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
						--ASIENTOS MANUALES
						union all
						select distinct
						mac1.ac1_font_key,
						mac1.ac1_legal_num,
						mac1.ac1_legal_num as codigoproveedor,
						mac1.ac1_account as cuenta,
						mac1.ac1_account,
						CURRENT_DATE - tmac.mac_doc_duedate dias,
						CURRENT_DATE - tmac.mac_doc_duedate dias_atrasado,
						tmac.mac_comments,
						get_localcur() as currency,
						0 as dvf_docentry,
						0 as docentry,
						0 as docnum,
						0 as numerodocumento,
						tmac.mac_doc_date as fecha_doc,
						tmac.mac_doc_date as fechadocumento,
						tmac.mac_doc_duedate as fecha_ven,
						tmac.mac_doc_duedate as fechavencimiento,
						0 as id_origen,
						18 as numtype,
						18 as doctype,
						mdt_docname as tipo,
						case
							when mac1.ac1_cord = 0 then get_dynamic_conversion(:currency, get_localcur(),mac_doc_date,mac1.ac1_debit ,get_localcur())
							when mac1.ac1_cord = 1 then get_dynamic_conversion(:currency, get_localcur(),mac_doc_date,mac1.ac1_credit ,get_localcur())
						end	 as total_doc,
						case
							when mac1.ac1_cord = 0 then get_dynamic_conversion(:currency, get_localcur(),mac_doc_date,mac1.ac1_debit ,get_localcur())
							when mac1.ac1_cord  = 1 then get_dynamic_conversion(:currency, get_localcur(),mac_doc_date,mac1.ac1_credit ,get_localcur())
						end	 as totalfactura,
						get_dynamic_conversion(:currency, get_localcur(),mac_doc_date,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)  ,get_localcur()) as saldo_venc,
						'' retencion,
						get_tax_currency(tmac.mac_currency, tmac.mac_doc_date) as tasa_dia,
						dmsn.dms_card_name as nombreproveedor,
						get_localcur() as monedadocumento,
						:fecha as  fechacorte,
						ac1_line_num,
						ac1_cord
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
						where mac1.ac1_legal_num = :cardcode
						and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0";
					}

					$result = $this->pedeo->queryTable($sql,array(":cardcode" => $Data['cardcode'],
																  ":fecha" => $fecha,
																  ":currency" => $Data['currency']));
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
					end extras,
					COALESCE(t4.acc_name,'CUENTA PUENTE') nombre_cuenta,t0.*
					FROM mac1 t0
					INNER JOIN dacc t4 on t0.ac1_account = t4.acc_code
					INNER JOIN dmdt t16 on coalesce(t0.ac1_font_type,0) = t16.mdt_doctype
					WHERE 1=1 ".$where;

				$resSelect = $this->pedeo->queryTable($sqlSelect,array());
// print_r($sqlSelect);exit();die();
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
										$newObj['mac_comment'] = $obj;;
									}else {
										//
										$newObj['mac_'.$prefijo[1]] = $obj;
									}
								}
							}
							/**
							 * VALIDACIONES PARA CUANDO EL REGISTRO VIENE DE UN ASIENTO.
							 */
							if (!isset($newObj['mac_cardcode'])) {
								// RENOMBRAR EL CAMPO Y SASIGNAR VALOR.
								$newObj['mac_cardcode'] = $data['ac1_legal_num'];
								$newObj['mac_cardname'] = $data['ac1_legal_num'];
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

	//LISTA DE PARTIDAS ABIERTAS
	//DOCUMENTOS PENDIENTES POR COMPENSAR
	public function  ListDocumentCompensate_post(){

		$Data = $this->post();
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
			$ac = implode($ac,",");
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
									where 1 = 1
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
									where 1 = 1
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
									where 1 = 1
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
									where 1 = 1
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
									where 1 = 1
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
									where 1 = 1
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
									where 1 = 1
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
									where 1 = 1
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
									where 1 = 1
									".$sql."
									and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0";
// print_r($sqlSelect);exit;
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

	//listado de cuentas debito - credito POR año seleccionado
	public function AccountBalance_post(){

		$Data = $this->post();

		$ac = "";
		$sql = " WHERE 1=1 ";
	 	$yr  = "";



		if( isset( $Data['lac_ac'] )  && !empty($Data['lac_ac']) ){
			$ac = $Data['lac_ac'];
		}

		if( isset( $Data['lac_yr'] )  && !empty($Data['lac_yr']) ){
			$yr = $Data['lac_yr'];
		}

		if( is_array($ac) ){
			$ac = implode($ac,",");
			$sql .= ' AND dacc.acc_code IN('.$ac.')';
		}

		$sql .= ' AND dacc.acc_level = 6';


		$sqlSelect="SELECT acc_code as codigocuenta,
				acc_name as nombredecuenta,
				coalesce(get_saldocuentames(acc_code,1,".$yr."),0) as enero,
				coalesce(get_saldocuentames(acc_code,2,".$yr."),0) as febrero,
				coalesce(get_saldocuentames(acc_code,3,".$yr."),0) as marzo,
				coalesce(get_saldocuentames(acc_code,4,".$yr."),0) as abril,
				coalesce(get_saldocuentames(acc_code,5,".$yr."),0) as mayo,
				coalesce(get_saldocuentames(acc_code,6,".$yr."),0) as junio,
				coalesce(get_saldocuentames(acc_code,7,".$yr."),0) as julio,
				coalesce(get_saldocuentames(acc_code,8,".$yr."),0) as agosto,
				coalesce(get_saldocuentames(acc_code,9,".$yr."),0) as septiembre,
				coalesce(get_saldocuentames(acc_code,10,".$yr."),0) as octubre,
				coalesce(get_saldocuentames(acc_code,11,".$yr."),0) as noviembre,
				coalesce(get_saldocuentames(acc_code,12,".$yr."),0) as diciembre,
				coalesce(get_saldocuentaano(acc_code,".$yr."),0) as saldo
				from dacc ".$sql;

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

}
