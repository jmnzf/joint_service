<?php
// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/La_Paz');


require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
require_once(APPPATH . '/asset/vendor/autoload.php');


class Febo extends REST_Controller {

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

	}

	// ENVIA FACTURAS ELECTRONICAS
    public function syncInvoice_post() {

		$sql = "SELECT dvfv.*, dms_id_type, dms_email, dms_cel, dms_poscode
				FROM dvfv 
				inner join dmsn on dvfv.dvf_cardcode = dms_card_code  
				where dvf_cufe is null 
				and dvf_send_dian is null 
				and dvf_doctype_sector > 0
				and dms_card_type = :dms_card_type
				and dvf_doctype_sector != :dvf_doctype_sector";

		$facturas = $this->pedeo->queryTable($sql, array(
			':dms_card_type' => '1',
			':dvf_doctype_sector' => 2
		));


		foreach ($facturas as $key => $factura) {

			$detalle = new stdClass();
			$cabecera = new stdClass();

			$fecha = new DateTime();

			
			$empresa  = $this->pedeo->queryTable("SELECT pge_emisor_erp,pge_id_soc,pge_name_soc,pge_cel FROM pgem WHERE pge_id = :pge_id", array(":pge_id" => $factura['business']));
			$sucursal = $this->pedeo->queryTable("SELECT pgs_sucursal_erp FROM pges WHERE pgs_id = :pgs_id", array(":pgs_id" => $factura['branch']));
			$fdetalle = $this->pedeo->queryTable("SELECT vfv1.*, dmu_codigo_dim FROM vfv1 inner join dmum on vfv1.fv1_uom = dmum.dmu_code  WHERE fv1_docentry = :fv1_docentry", array(":fv1_docentry" => $factura['dvf_docentry']));

			if ( isset($empresa[0]) ) {

				if ( isset($sucursal[0]) ) {

					if ( isset($fdetalle[0]) ) {
							
						// SE LLENA EL DETALLE 
						$baseImponible = 0;
						$array = [];
						foreach ($fdetalle as $key => $det) {
							$detalle = new stdClass();

							$detalle->codigo_producto_erp = $det['fv1_itemcode'];
							$detalle->descripcion = $det['fv1_itemname'];
							$detalle->cantidad = floatval($det['fv1_quantity']);
							$detalle->unidad_medida = $det['dmu_codigo_dim'];
							$detalle->precio_unitario = floatval($det['fv1_price']);
							$detalle->monto_descuento = floatval($det['fv1_discount']);
							$detalle->sub_total = floatval($det['fv1_linetotal']);

							$baseImponible = $baseImponible + $det['fv1_tax_base'];

							array_push( $array, $detalle );
						}

						// SE LLENA LA CABECERA
						$cabecera->codigo_sucursal_erp = $sucursal[0]['pgs_sucursal_erp'];
						$cabecera->codigo_emisor_erp = $empresa[0]['pge_emisor_erp'];
						$cabecera->codigo_documento_sector = $factura['dvf_doctype_sector'];
						$cabecera->correo_cliente = $factura['dms_email'];
						$cabecera->telefono_cliente = $factura['dms_cel'];
						$cabecera->nit_emisor = $empresa[0]['pge_id_soc'];
						$cabecera->razon_social_emisor =  $empresa[0]['pge_name_soc'];
						$cabecera->telefono_emisor = $empresa[0]['pge_cel'];
						$cabecera->nro_factura = $factura['dvf_docnum'];
						$cabecera->fecha_emision = $fecha->format('Y-m-d\TH:i:s.v\Z');
						$cabecera->nombre_razon_social = $factura['dvf_cardname'];
						$cabecera->codigo_tipo_documento_identidad = $factura['dms_id_type'];
						$cabecera->nro_documento = $factura['dvf_cardcode'];
						$cabecera->complemento = "";
						$cabecera->codigo_cliente = $factura['dms_poscode'];
						$cabecera->codigo_metodo_pago = $factura['dvf_payment_method'];
						$cabecera->nro_tarjeta = "";
						$cabecera->monto_total = floatval($factura['dvf_doctotal']);
						$cabecera->monto_total_sujeto_iva = floatval($baseImponible);
						$cabecera->codigo_moneda = 1;
						$cabecera->tipo_cambio = 1;
						$cabecera->monto_total_moneda = floatval($factura['dvf_doctotal']);
						$cabecera->monto_gift_card = 0;
						$cabecera->descuento_adicional = 0;
						$cabecera->cafc = "";
						$cabecera->usuario = "TEST";
						$cabecera->codigo_excepcion = "0";
						$cabecera->listaDetalleFactura = $array;
						
					}
				}
			}

			
			//
			$client = new GuzzleHttp\Client();
			$cooKies = new GuzzleHttp\Cookie\CookieJar();

			// SE ENVIA LA FACTURA 
			try {
				$response = $client->request('POST', 'https://dim.la-razon.com/dimlarazon/ServiceFacturaDualbiz/Recepcion', [
					'headers' => [
						'Accept' => 'application/json',
						'Content-Type' => 'application/json',
					],
					'json' => json_decode(json_encode($cabecera), true),
					'cookies' => $cooKies // Habilita el manejo de cookies
				]);
			
				// Verifica el código de estado HTTP
				if ($response->getStatusCode() == 200) {
					$body = $response->getBody();
					$data = json_decode($body, true);
					$sqlUpdate = "";
					$array = [];

					if ( isset($data['transaccion']) ) {

						if ( $data['transaccion'] == false ) {

							$sqlUpdate = "UPDATE dvfv set dvf_response_dian = :dvf_response_dian WHERE dvf_docentry = :dvf_docentry";
							
							$array[':dvf_response_dian'] = $data['listaErrores'][0]['descripcion'];
							$array[':dvf_docentry'] = $factura['dvf_docentry'];

						} else {

							$sqlUpdate = "UPDATE dvfv set 
									dvf_cufe = :dvf_cufe,
									dvf_send_dian = :dvf_send_dian,
									dvf_ruta_pdf = :dvf_ruta_pdf
									WHERE dvf_docentry = :dvf_docentry";

							$array['dvf_cufe'] = $data['cuf'];
							$array['dvf_send_dian'] = 1;
							$array['dvf_ruta_pdf'] = $data['url_siat'];
							$array[':dvf_docentry'] = $factura['dvf_docentry'];
						}

						$resUpdate = $this->pedeo->updateRow($sqlUpdate, $array);

					}
					// print_r($data);
					// print_r(json_encode($data));
				} else {
					echo 'Error en la solicitud: ' . $response->getStatusCode();
				}
			} catch (\GuzzleHttp\Exception\ClientException $e) {
				echo 'Excepción capturada: ',  $e->getMessage(), "\n";
				echo 'Respuesta del servidor: ', $e->getResponse()->getBody()->getContents();
			}	
		}

		$respuesta = array(
			"error"   => false,
			"data"    => [],
			"mensaje" => "Proceso finalizado con exito"
		);

		return $this->response($respuesta);
		
    }

	// ENVIA NOTAS CREDITO Y DEBITO ELECTRONICAS
	public function syncNote_post() {

		$sql = "--NOTAS CREDITO
				SELECT distinct on(vnc_docnum) 24 sectordoc,
				vnc_docnum as numberdoc,
				vnc_cardname as nombre_socio,
				vnc_cardcode as codigo_socio,
				vnc_doctotal as totaldoc, 
				dms_id_type, dms_email, dms_cel, dms_poscode, dvnc.business, dvnc.branch,
				vnc_baseentry as origen,
				vnc_docentry as idactual,
				6 as tipo_origen,
				round(((vnc_doctotal * 13) / 100)::numeric, get_decimals()) as monto_efectivo
				FROM dvnc 
				inner join dmsn on dvnc.vnc_cardcode = dms_card_code  
				where vnc_cufe is null 
				and vnc_send_dian is null 
				and dms_card_type = :dms_card_type
				--NOTAS DEBITO
				UNION ALL 
				SELECT distinct on(vnd_docnum) 24 sectordoc,
				vnd_docnum as numberdoc,
				vnd_cardname as nombre_socio,
				vnd_cardcode as codigo_socio,
				vnd_doctotal as totaldoc, 
				dms_id_type, dms_email, dms_cel, dms_poscode,  dvnd.business, dvnd.branch,
				vnd_baseentry as origen,
				vnd_docentry as idactual,
				7 as tipo_origen,
				round(((vnd_doctotal * 13) / 100)::numeric, get_decimals()) as monto_efectivo
				FROM dvnd 
				inner join dmsn on dvnd.vnd_cardcode = dms_card_code  
				where vnd_cufe is null 
				and vnd_send_dian is null 
				and dms_card_type = :dms_card_type";

		$notas = $this->pedeo->queryTable($sql, array(
			':dms_card_type' => '1'
		));

		foreach ($notas as $key => $nota) {

			$detalle = new stdClass();
			$cabecera = new stdClass();

			$fecha = new DateTime();

			
			$empresa  = $this->pedeo->queryTable("SELECT pge_emisor_erp,pge_id_soc,pge_name_soc,pge_cel FROM pgem WHERE pge_id = :pge_id", array(":pge_id" => $nota['business']));
			$sucursal = $this->pedeo->queryTable("SELECT pgs_sucursal_erp FROM pges WHERE pgs_id = :pgs_id", array(":pgs_id" => $nota['branch']));
			$fdetalle = $this->pedeo->queryTable("--NOTA CREDITO
										select nc1_itemcode as itemcode, 
										nc1_itemname as itemname, 
										nc1_quantity as quantity, 
										nc1_price as price, 
										nc1_discount as discount,  
										nc1_linetotal as linetotal, 
										nc1_tax_base as tax_base,
										dmu_codigo_dim 
										FROM vnc1 
										inner join dvnc on vnc1.nc1_docentry = dvnc.vnc_docentry 
										inner join dmum on vnc1.nc1_uom = dmum.dmu_code  
										WHERE vnc_docentry = :docentry
										and vnc_doctype = :doctype
										--NOTA DEBITO
										union all 
										select nd1_itemcode as itemcode, 
										nd1_itemname as itemname, 
										nd1_quantity as quantity, 
										nd1_price as price, 
										nd1_discount as discount,  
										nd1_linetotal as linetotal, 
										nd1_tax_base as tax_base,
										dmu_codigo_dim 
										FROM vnd1 
										inner join dvnd on vnd1.nd1_docentry = dvnd.vnd_docentry 
										inner join dmum on vnd1.nd1_uom = dmum.dmu_code  
										WHERE vnd_docentry = :docentry
										and vnd_doctype = :doctype
										", array(":docentry" => $nota['idactual'], ":doctype" => $nota['tipo_origen']));

			$cfactura = $this->pedeo->queryTable("SELECT * FROM dvfv WHERE dvf_docentry = :dvf_docentry", array(":dvf_docentry" => $nota['origen']));

			if ( isset($empresa[0]) ) {

				if ( isset($sucursal[0]) ) {

					if (isset($cfactura[0])){

						if ( isset($fdetalle[0]) ) {
							
							// SE LLENA EL DETALLE 
							$baseImponible = 0;
							$totalDescuento = 0;
							$array = [];
							foreach ($fdetalle as $key => $det) {
								$detalle = new stdClass();
	
								$detalle->codigo_producto_erp = $det['itemcode'];
								$detalle->descripcion = $det['itemname'];
								$detalle->cantidad = floatval($det['quantity']);
								$detalle->unidad_medida = $det['dmu_codigo_dim'];
								$detalle->precio_unitario = floatval($det['price']);
								$detalle->monto_descuento = floatval($det['discount']);
								$detalle->sub_total = floatval($det['linetotal']);
								$detalle->codigo_detalle_transaccion = 0;
	
								$baseImponible = $baseImponible + $det['tax_base'];
								$totalDescuento = $totalDescuento + $det['discount'];
	
								array_push( $array, $detalle );
							}
	
							// SE LLENA LA CABECERA
							$cabecera->codigo_sucursal_erp = $sucursal[0]['pgs_sucursal_erp'];
							$cabecera->codigo_emisor_erp = $empresa[0]['pge_emisor_erp'];
							$cabecera->tipo_emision = 0;
							$cabecera->codigo_documento_sector = $nota['sectordoc'];
							$cabecera->nit_emisor = $empresa[0]['pge_id_soc'];
							$cabecera->razon_social_emisor =  $empresa[0]['pge_name_soc'];
							$cabecera->telefono_emisor = $empresa[0]['pge_cel'];
							$cabecera->nro_nota_credito_debito = $nota['numberdoc'];
							$cabecera->nro_factura = $cfactura[0]['dvf_docnum'];
							$cabecera->fecha_emision = $fecha->format('Y-m-d\TH:i:s.v\Z');
							$cabecera->nombre_razon_social = $nota['nombre_socio'];
							$cabecera->codigo_tipo_documento_identidad = $nota['dms_id_type'];
							$cabecera->nro_documento = $nota['codigo_socio'];
							$cabecera->complemento = "";
							$cabecera->codigo_cliente = $nota['dms_poscode'];
							$cabecera->correo_cliente = $nota['dms_email'];
							$cabecera->telefono_cliente = $nota['dms_cel'];
							$cabecera->numero_autorizacion_cuf = $cfactura[0]['dvf_cufe'];
							$cabecera->fecha_emision_factura = $this->getFecha($cfactura[0]['dvf_createat']);
							$cabecera->monto_total_original = $cfactura[0]['dvf_doctotal'];
							$cabecera->monto_total_devuelto = $nota['totaldoc'];
							$cabecera->monto_descuento_credito_debito = $totalDescuento;
							$cabecera->monto_efectivo_credito_debito = $nota['monto_efectivo'];
							
							$cabecera->usuario = "TEST";
							$cabecera->codigo_excepcion = "0";
							$cabecera->listaDetalleFactura = $array;
							
						}
					}
				}
			}
			//
			$client = new GuzzleHttp\Client();
			$cooKies = new GuzzleHttp\Cookie\CookieJar();

			// SE ENVIA LA FACTURA 
			try {
				$response = $client->request('POST', 'https://dim.la-razon.com/dimlarazon/ServiceCreditoDebito/RecepcionDualbiz', [
					'headers' => [
						'Accept' => 'application/json',
						'Content-Type' => 'application/json',
					],
					'json' => json_decode(json_encode($cabecera), true),
					'cookies' => $cooKies // Habilita el manejo de cookies
				]);
			
				// Verifica el código de estado HTTP
				if ($response->getStatusCode() == 200) {
					$body = $response->getBody();
					$data = json_decode($body, true);
					$sqlUpdate = "";
					$array = [];

					if ( isset($data['transaccion']) ) {

						if ( $data['transaccion'] == false ) {

							if ( $nota['tipo_origen'] == 6 ) {

								$sqlUpdate = "UPDATE dvnc set vnc_response_dian = :response_dian WHERE vnc_docentry = :docentry";

							} else if ( $nota['tipo_origen'] == 7 ) {
								$sqlUpdate = "UPDATE dvnd set vnd_response_dian = :response_dian WHERE vnd_docentry = :docentry";
							}
							
							$array[':response_dian'] = $data['listaErrores'][0]['descripcion'];
							$array[':docentry'] = $nota['idactual'];

						} else {

							if ( $nota['tipo_origen'] == 6 ) {

								$sqlUpdate = "UPDATE dvnc set 
									vnc_cufe = :cufe,
									vnc_send_dian = :send_dian,
									vnc_ruta_pdf = :ruta_pdf,
									vnc_codigo_dim = :codigo_dim
									WHERE dvf_docentry = :docentry";

							} else if ( $nota['tipo_origen'] == 7 ) {

								$sqlUpdate = "UPDATE dvnd set 
									vnd_cufe = :cufe,
									vnd_send_dian = :send_dian,
									vnd_ruta_pdf = :ruta_pdf,
									vnd_codigo_dim = :codigo_dim
									WHERE dvd_docentry = :docentry";
							}

							

							$array[':cufe'] = $data['cuf'];
							$array[':send_dian'] = 1;
							$array[':ruta_pdf'] = $data['url'];
							$array[':docentry'] = $nota['idactual'];
							$array[':codigo_dim'] = $data['codigo_recepcion'];
						}

						$resUpdate = $this->pedeo->updateRow($sqlUpdate, $array);

					}
					print_r($data);
					print_r(json_encode($data));
				} else {
					echo 'Error en la solicitud: ' . $response->getStatusCode();
				}
			} catch (\GuzzleHttp\Exception\ClientException $e) {
				echo 'Excepción capturada: ',  $e->getMessage(), "\n";
				echo 'Respuesta del servidor: ', $e->getResponse()->getBody()->getContents();
			}	
		}

		$respuesta = array(
			"error"   => false,
			"data"    => [],
			"mensaje" => "Proceso finalizado con exito"
		);

		return $this->response($respuesta);
		
    }

	// ANULA FACTURA ELECTRONICAS
	public function cancelInvoice_get() {
		
		$Data = $this->get();

		if ( !isset($Data['cuf']) || !isset($Data['motivo']) || !isset($Data['user']) ) {

			$respuesta = array(
				"error"   => true,
				"data"    => [],
				"mensaje" => "Faltan parametros"
			);
	
			return $this->response($respuesta);
		}

		// SE VALIDA QUE EXISTA LA FACTURA CON EL CUFE
		$resFactura = $this->pedeo->queryTable("SELECT * FROM dvfv WHERE dvf_cufe = :dvf_cufe", array(
			":dvf_cufe" => $Data['cuf']
		));

		if ( !isset($resFactura[0]) ) {

			$respuesta = array (
				"error"   => true,
				"data"    => [],
				"mensaje" => "No se encontro el documento"
			);

			return $this->response($respuesta);
		}
		// 
		$resFactura = $resFactura[0];

		// SE VALIDA EL PERIODO CONTABLE
		//VALIDANDO PERIODO CONTABLE
		$periodo = $this->generic->ValidatePeriod($resFactura['dvf_duedev'], $resFactura['dvf_docdate'], $resFactura['dvf_duedate'], 1);

		if (isset($periodo['error']) && $periodo['error'] == false) {
		} else {
			$respuesta = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => isset($periodo['mensaje']) ? $periodo['mensaje'] : 'no se pudo validar el periodo contable'
			);

			$this->response($respuesta);

			return;
		}
		//PERIODO CONTABLE

		//
		$client = new GuzzleHttp\Client();
		$cooKies = new GuzzleHttp\Cookie\CookieJar();
		

		// SE ENVIA LA FACTURA 
		try {
			$response = $client->request('POST', 'https://dim.la-razon.com/dimlarazon/ServiceFactura/Anulacion/'.$Data['cuf'].'/'.$Data['motivo'], [
				'headers' => [
					'Accept' => 'application/json',
					'Content-Type' => 'application/json',
				],
				'cookies' => $cooKies // Habilita el manejo de cookies
			]);
		
			// Verifica el código de estado HTTP
			if ($response->getStatusCode() == 200) {
				$body = $response->getBody();
				$data = json_decode($body, true);
				
				if ( isset($data['transaccion']) ) {

					if ( $data['transaccion'] == false ) {

						$respuesta = array(
							"error"   => true,
							"data"    => [],
							"mensaje" => $data['listaErrores'][0]['descripcion']
						);

					} else {


						// SE BUSCA EL DETALLE DEL ASIENTO
						$resDetalleAsiento = $this->pedeo->queryTable("SELECT * FROM mac1 WHERE ac1_font_key = :ac1_font_key AND ac1_font_type = :ac1_font_type", array(

							":ac1_font_type" => $resFactura['dvf_doctype'],
							":ac1_font_key"  => $resFactura['dvf_docentry']

						));

						if ( !isset($resDetalleAsiento[0]) ) {

							$respuesta = array (
								"error"   => true,
								"data"    => [],
								"mensaje" => "No se encontro el detalle del asiento"
							);
				
							return $this->response($respuesta);
						}
						//

						// SE BUSCA EL ASIENTO ACTUAL
						$resAsiento = $this->pedeo->queryTable("SELECT * FROM tmac WHERE mac_trans_id = :mac_trans_id", array(

							":mac_trans_id" => $resDetalleAsiento[0]['ac1_trans_id']
						));

						if ( !isset($resAsiento[0]) ) {

							$respuesta = array (
								"error"   => true,
								"data"    => [],
								"mensaje" => "No se encontro el asiento actual"
							);
				
							return $this->response($respuesta);
						}
						//

						// SE CREA EL ASIENTO DE CONTRA PARTIDA AL DE LA FACTURA

						$this->pedeo->trans_begin();

						$sqlInsertHeader = "INSERT INTO tmac( mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_update_date, mac_series, mac_made_usuer, mac_update_user, mac_currency, mac_doctype, business, branch, mac_accperiod)
										VALUES(:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_update_date, :mac_series, :mac_made_usuer, :mac_update_user, :mac_currency, :mac_doctype, :business, :branch, :mac_accperiod)";
					
						$resSqlInsertHeader = $this->pedeo->insertRow($sqlInsertHeader, array(
							'mac_doc_num' => 0,
							':mac_status' => 2, 
							':mac_base_type' => 5, 
							':mac_base_entry' => $resFactura['dvf_docentry'], 
							':mac_doc_date' => $resFactura['dvf_duedev'], 
							':mac_doc_duedate' => $resFactura['dvf_duedate'], 
							':mac_legal_date' => $resFactura['dvf_docdate'], 
							':mac_ref1' => $resAsiento[0]['mac_ref1'], 
							':mac_ref2' => $resAsiento[0]['mac_ref2'], 
							':mac_ref3' => $resAsiento[0]['mac_ref3'], 
							':mac_loc_total' => $resAsiento[0]['mac_loc_total'], 
							':mac_fc_total' => $resAsiento[0]['mac_fc_total'], 
							':mac_sys_total' => $resAsiento[0]['mac_sys_total'], 
							':mac_trans_dode' => $resAsiento[0]['mac_trans_dode'], 
							':mac_beline_nume' => $resAsiento[0]['mac_beline_nume'], 
							':mac_vat_date' => $resAsiento[0]['mac_vat_date'], 
							':mac_serie' => $resAsiento[0]['mac_serie'], 
							':mac_number' => $resAsiento[0]['mac_number'], 
							':mac_bammntsys' => $resAsiento[0]['mac_bammntsys'], 
							':mac_bammnt' => $resAsiento[0]['mac_bammnt'], 
							':mac_wtsum' => $resAsiento[0]['mac_wtsum'], 
							':mac_vatsum' => $resAsiento[0]['mac_vatsum'], 
							':mac_comments' => 'Anulación de factura '.$resFactura['dvf_docnum'], 
							':mac_create_date' => date('Y-m-d H:i:s'), 
							':mac_update_date' => date('Y-m-d H:i:s'), 
							':mac_series' => 0, 
							':mac_made_usuer' => $Data['user'], 
							':mac_update_user' => $resAsiento[0]['mac_update_user'], 
							':mac_currency' => $resAsiento[0]['mac_currency'], 
							':mac_doctype' => $resAsiento[0]['mac_doctype'], 
							':business' => $resAsiento[0]['business'], 
							':branch' => $resAsiento[0]['branch'], 
							':mac_accperiod' => $periodo['data']
						));

						if ( is_numeric($resSqlInsertHeader) && $resSqlInsertHeader > 0 ){

						}else{

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error' => true,
								'data' => $resSqlInsertHeader,
								'mensaje' => 'Error al insertar la copia del asiento'
							);
	
							return $respuesta;
						}
						//

						// SE INSERTA EL DETALLE DEL ASIENTO NUEVO 
						foreach ($resDetalleAsiento as $key => $detalle) {

							$sqlInsertDetalle = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate, ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype, ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord, ac1_ven_debit, ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, ac1_card_type, business, branch, ac1_codret, ac1_base_tax)
												 VALUES (:ac1_trans_id, :ac1_account, :ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys, :ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode, :ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct, :ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref, :ac1_card_type, :business, :branch, :ac1_codret, :ac1_base_tax)";
							
							$debito = 0;
							$credito = 0;

							$debitosys = 0;
							$creditosys= 0;

							$vendebito = 0;
							$vencredito = 0;

							$oldVen = 0;

							if ( $detalle['ac1_debit'] > 0 ){
							
								$debito = 0;
								$credito = $detalle['ac1_debit'];

								$oldVen = $detalle['ac1_debit'];
								
							}

							if ( $detalle['ac1_credit'] > 0 ){
							
								$debito = $detalle['ac1_credit'];;
								$credito = 0;
								
								$oldVen = $detalle['ac1_credit'];
							}

							if ( $detalle['ac1_debit_sys'] > 0 ){
							
								$debitosys = 0;
								$creditosys = $detalle['ac1_debit_sys'];
								
							}

							if ( $detalle['ac1_credit_sys'] > 0 ){
							
								$debitosys = $detalle['ac1_credit_sys'];
								$creditosys = 0;
								
							}

							$resInsertDetalle = $this->pedeo->insertRow($sqlInsertDetalle, array(

								':ac1_trans_id' => $resSqlInsertHeader,
								':ac1_account' => $detalle['ac1_account'], 
								':ac1_debit' => $debito, 
								':ac1_credit' => $credito, 
								':ac1_debit_sys' => $debitosys, 
								':ac1_credit_sys' => $creditosys, 
								':ac1_currex' => $detalle['ac1_currex'], 
								':ac1_doc_date' => $resFactura['dvf_docdate'], 
								':ac1_doc_duedate' => $resFactura['dvf_duedate'], 
								':ac1_debit_import' => $detalle['ac1_debit_import'], 
								':ac1_credit_import' => $detalle['ac1_credit_import'], 
								':ac1_debit_importsys' => $detalle['ac1_debit_importsys'], 
								':ac1_credit_importsys' => $detalle['ac1_credit_importsys'], 
								':ac1_font_key' => $resSqlInsertHeader,
								':ac1_font_line' => $detalle['ac1_font_line'], 
								':ac1_font_type' => 18,
								':ac1_accountvs' => $detalle['ac1_accountvs'], 
								':ac1_doctype' => $detalle['ac1_doctype'],
								':ac1_ref1' => $detalle['ac1_ref1'], 
								':ac1_ref2' => $detalle['ac1_ref2'], 
								':ac1_ref3' => $detalle['ac1_ref3'], 
								':ac1_prc_code' => $detalle['ac1_prc_code'], 
								':ac1_uncode' => $detalle['ac1_uncode'], 
								':ac1_prj_code' => $detalle['ac1_prj_code'], 
								':ac1_rescon_date' => $detalle['ac1_rescon_date'],
								':ac1_recon_total' => $detalle['ac1_recon_total'], 
								':ac1_made_user' => $detalle['ac1_made_user'], 
								':ac1_accperiod' => $periodo['data'],
								':ac1_close' => $detalle['ac1_close'], 
								':ac1_cord' => $detalle['ac1_cord'], 
								':ac1_ven_debit' => 0, 
								':ac1_ven_credit' => 0, 
								':ac1_fiscal_acct' => $detalle['ac1_fiscal_acct'], 
								':ac1_taxid' => $detalle['ac1_taxid'], 
								':ac1_isrti' => $detalle['ac1_isrti'],
								':ac1_basert' => $detalle['ac1_basert'],
								':ac1_mmcode' => $detalle['ac1_mmcode'],
								':ac1_legal_num' => $detalle['ac1_legal_num'], 
								':ac1_codref' => $detalle['ac1_codref'], 
								':ac1_card_type' => $detalle['ac1_card_type'], 
								':business' => $detalle['business'],
								':branch'   => $detalle['branch'], 
								':ac1_codret' => $detalle['ac1_codret'], 
								':ac1_base_tax' => $detalle['ac1_base_tax'] 
							));

							if (is_numeric($resInsertDetalle) && $resInsertDetalle > 0 ) {

							
							}else{

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error' => true,
									'data' => $resInsertDetalle,
									'mensaje' => 'Error al insertar la copia del detalle del asiento'
								);

								return $respuesta;
							}
						}

						//

						// ACTUALIZAR VEN DEBIT Y CREDIT DEL ASIENTO VIEJO

						$sqlUpdate = "UPDATE mac1 SET ac1_ven_debit = :ac1_ven_debit, ac1_ven_credit = :ac1_ven_credit WHERE ac1_trans_id = :ac1_trans_id";

						$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
							':ac1_ven_credit' => 0,
							':ac1_ven_debit'  => 0,
							':ac1_trans_id'   => $resDetalleAsiento[0]['ac1_trans_id']

						));


						if (is_numeric($resUpdate) && $resUpdate > 0 ) {

						
						} else {

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error' => true,
								'data' => $resUpdate,
								'mensaje' => 'Error al actualizar el asiento viejo'
							);

							return $respuesta;
						}

						// SE CAMBIA DE LA CABECERA DEL ASIENTO VIEJO
						$update = $this->pedeo->updateRow("UPDATE tmac SET mac_status = :mac_status WHERE mac_trans_id = :mac_trans_id", array(":mac_status" => 2, ":mac_trans_id" => $resDetalleAsiento[0]['ac1_trans_id'] ));

						if ( is_numeric($update) && $update == 1 ) {

						} else {

							$respuesta = array(
								'error' => true,
								'data' => $update,
								'mensaje' => 'No se pudo cambiar el estado del documento'
							);
	
							return $respuesta;
						}
						//

						// SE INSERTA LA ANULACION  DE LA FACTURA

						$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
						VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

						$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(
							':bed_docentry' => $resFactura['dvf_docentry'],
							':bed_doctype' => $resFactura['dvf_doctype'],
							':bed_status' => 2, //ESTADO ANULADO
							':bed_createby' => $Data['user'],
							':bed_date' => date('Y-m-d'),
							':bed_baseentry' => NULL,
							':bed_basetype' => NULL
						));


						if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
						} else {

							$this->pedeo->trans_rollback();

							$respuesta = array(
							'error'   => true,
							'data' => $resInsertEstado,
							'mensaje'	=> 'No se pudo inserta el nuevo estado del documento'
							);


							$this->response($respuesta);

							return;
						}

						//FIN PROCESO ESTADO DEL DOCUMENTO
						//

						$this->pedeo->trans_commit();

						$respuesta = array(
							"error"   => false,
							"data"    => [],
							"mensaje" => "Documento anulado con exito"
						);
					}

				} else {

					$respuesta = array(
						"error"   => true,
						"data"    => [],
						"mensaje" => "Error desconocido"
					);

					return $this->response($respuesta);
				}
				// print_r($data);
				// print_r(json_encode($data));
			} else {
				//echo 'Error en la solicitud: ' . $response->getStatusCode();
				$respuesta = array(
					"error"   => true,
					"data"    => [],
					"mensaje" => "Error en la solicitud ".$response->getStatusCode()
				);

				return $this->response($respuesta);
			}
		} catch (\GuzzleHttp\Exception\ClientException $e) {
			// echo 'Excepción capturada: ',  $e->getMessage(), "\n";
			// echo 'Respuesta del servidor: ', $e->getResponse()->getBody()->getContents();

			$respuesta = array(
				"error"   => true,
				"data"    => [],
				"mensaje" => $e->getMessage()
			);

			return $this->response($respuesta);
		}	


		return $this->response($respuesta);
	}


	// CONVIERTE LA FECHA EN FORMATO UTC extendido
	private function getFecha($fecha){
		$fechaOriginal = $fecha;
		$fechaDateTime = new DateTime($fechaOriginal);
		$fechaFormateada = $fechaDateTime->format('Y-m-d\TH:i:s.v\Z');
		return $fechaFormateada;
	}
}