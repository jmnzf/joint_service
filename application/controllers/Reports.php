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

      	$sql = 'SELECT
			T0.BMI_ITEMCODE CodigoArticulo,
			T14.DMA_ITEM_NAME NombreArticulo,
			T1.DWS_CODE CodigoAlmacen,
			T1.DWS_NAME NombreAlmacen,
			T2.MDT_DOCNAME DocOrigen,
			CASE
				WHEN T0.BMY_DOCTYPE = 3 THEN T4.VEM_DOCNUM
				WHEN T0.BMY_DOCTYPE = 5 THEN T5.DVF_DOCNUM
				WHEN T0.BMY_DOCTYPE = 4 THEN T6.VDV_DOCNUM
				WHEN T0.BMY_DOCTYPE = 6 THEN T7.VNC_DOCNUM
				WHEN T0.BMY_DOCTYPE = 7 THEN T8.VND_DOCNUM
				WHEN T0.BMY_DOCTYPE = 13 THEN T9.CEC_DOCNUM
				WHEN T0.BMY_DOCTYPE = 14 THEN T10.CDC_DOCNUM
				WHEN T0.BMY_DOCTYPE = 15 THEN T11.CFC_DOCNUM
				WHEN T0.BMY_DOCTYPE = 16 THEN T12.CNC_DOCNUM
				WHEN T0.BMY_DOCTYPE = 17 THEN T13.CND_DOCNUM
			END DocNum,
			T0.BMI_CREATEAT FechaDocNum,
			T0.BMI_QUANTITY CantidadMovida,
			T3.BDI_AVGPRICE * T0.BMI_QUANTITY  Costo,
			T3.BDI_QUANTITY CantidadAcumulada,
			T3.BDI_AVGPRICE * T3.BDI_QUANTITY CostoAcumulado
		FROM TBMI T0
		LEFT JOIN DMWS T1 ON T0.BMI_WHSCODE = T1.DWS_CODE
		LEFT JOIN DMDT T2 ON T0.BMY_DOCTYPE = T2.MDT_DOCTYPE
		LEFT JOIN TBDI T3 ON T0.BMI_ITEMCODE = T3.BDI_ITEMCODE
		LEFT JOIN DVEM T4 ON T0.BMY_BASEENTRY = T4.VEM_DOCENTRY
		LEFT JOIN DVFV T5 ON T0.BMY_BASEENTRY = T5.DVF_DOCENTRY
		LEFT JOIN DVDV T6 ON T0.BMY_BASEENTRY = T6.VDV_DOCENTRY
		LEFT JOIN DVNC T7 ON T0.BMY_BASEENTRY = T7.VNC_DOCENTRY
		LEFT JOIN DVND T8 ON T0.BMY_BASEENTRY = T8.VND_DOCENTRY
		LEFT JOIN DCEC T9 ON T0.BMY_BASEENTRY = T9.CEC_DOCENTRY
		LEFT JOIN DCDC T10 ON T0.BMY_BASEENTRY = T10.CDC_DOCENTRY
		LEFT JOIN DCFC T11 ON T0.BMY_BASEENTRY = T11.CFC_DOCENTRY
		LEFT JOIN DCNC T12 ON T0.BMY_BASEENTRY = T12.CNC_DOCENTRY
		LEFT JOIN DCND T13 ON T0.BMY_BASEENTRY = T13.CND_DOCENTRY
		INNER JOIN DMAR T14 ON T0.BMI_ITEMCODE = T14.DMA_ITEM_CODE
		WHERE T0.BMY_DOCTYPE NOT IN (1,2,11,12,18) ';
		// ID ARTICULO.
		if (!empty($request['fil_acticuloId'])) {
			//
			$where[':fil_acticuloId'] = $request['fil_acticuloId'];
			// CONDICIÓN SQL.
			$sql .= " AND T0.BMI_ITEMCODE = :fil_acticuloId";
		}
		// ID ALMACEN.
		if (!empty($request['fil_almacenId'])) {
			//
			$where[':fil_almacenId'] = $request['fil_almacenId'];
			// CONDICIÓN SQL.
			$sql .= " AND T1.DWS_CODE = :fil_almacenId";
		}
		// FECHA INICIO Y FIN.
		if (!empty($request['flt_dateint']) && !empty($request['flt_dateend'])) {
			// CONDICIÓN SQL.
			$sql .= " AND T0.BMI_CREATEAT BETWEEN '".$request['flt_dateint']."' AND '".$request['flt_dateend']."'";
		}

		$sql .=' ORDER BY FechaDocNum DESC';

      	$result = $this->pedeo->queryTable($sql, $where);

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

			$sql = 'SELECT
							T0.MAC_DOC_DATE,
							T0.MAC_SERIE,
							T0.MAC_DOC_NUM,
							18 "Tipo",
							t0.MAC_TRANS_ID,
							CONCAT(T2.PGU_NAME_USER," ",T2.PGU_LNAME_USER),
							T1.AC1_ACCOUNT,
							T3.ACC_NAME,
							T1.AC1_DEBIT,
							T1.AC1_CREDIT,
							T1.AC1_PRC_CODE,
							T1.AC1_UNCODE,
							T1.AC1_PRJ_CODE
							FROM TMAC T0
							JOIN MAC1 T1 ON T0.MAC_TRANS_ID = T1.AC1_TRANS_ID
							LEFT JOIN PGUS T2 ON T0.MAC_MADE_USUER = T2.PGU_CODE_USER
							LEFT JOIN DACC T3 ON T1.AC1_ACCOUNT = T3.ACC_CODE';


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
			// print_r($Data);exit();die();
			$where = '';

			 // print_r($Data);exit();die();
			if(isset($Data['fil_almacenId']) && !empty($Data['fil_almacenId'])){
				$array = array();
				foreach (explode(',',$Data['fil_almacenId']) as $key => $value) {
					array_push($array,"'".$value."'");
				}
				$where = ' and T1.bdi_whscode IN ('.implode(',',$array).')';

			}if(isset($Data['fil_acticuloId']) && !empty($Data['fil_acticuloId'])){
				$where = $where.' and t2.dma_item_code = '.$Data['fil_acticuloId'];

			}if(isset($Data['fil_grupoId']) && !empty($Data['fil_grupoId']) ){
				$array = array();
				foreach (explode(',',$Data['fil_grupoId']) as $key => $value) {
					array_push($array,"'".$value."'");
				}
				$where = $where.' and t2.dma_group_code IN ('.implode(',',$array).')';
			}
// print_r($where);exit();die();
			$sql = 'SELECT
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
							where 1 = 1'.$where;

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

}
