<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class PdfOrdenCompraEs extends REST_Controller {

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
		$this->load->library('DateFormat');

	}


	public function PdfOrdenCompraEs_post(){

        $Data = $this->post();
				$Data = $Data['CPO_DOCENTRY'];

				$formatter = new NumeroALetras();
				$DECI_MALES =  $this->generic->getDecimals();

				// $mpdf = new \Mpdf\Mpdf(['setAutoBottomMargin' => 'stretch','setAutoTopMargin' => 'stretch']);
        $mpdf = new \Mpdf\Mpdf(['setAutoBottomMargin' => 'stretch','setAutoTopMargin' => 'stretch','default_font' => 'dejavusans']);

				//RUTA DE CARPETA EMPRESA
        $company = $this->pedeo->queryTable("SELECT main_folder company FROM PARAMS",array());

        if(!isset($company[0])){
						$respuesta = array(
		           'error' => true,
		           'data'  => $company,
		           'mensaje' =>'no esta registrada la ruta de la empresa'
		        );

	          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	          return;
				}

        //INFORMACION DE LA EMPRESA

				$empresa = $this->pedeo->queryTable("SELECT * FROM pgem", array());

				if(!isset($empresa[0])){
						$respuesta = array(
		           'error' => true,
		           'data'  => $empresa,
		           'mensaje' =>'no esta registrada la información de la empresa'
		        );

	          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	          return;
				}

				$observaciones = '	<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">1) El Proveedor deber&aacute; cumplir, sin retardo, con la entrega de los productos o materiales y/o ejecuci&oacute;n de servicios que forman parte de la presente orden de compra.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">2) Cualquier modificaci&oacute;n en lo que respecta a los t&eacute;rminos y condiciones de la presente orden de compra, deber&aacute; contar con la aprobaci&oacute;n previa y por escrito por parte de Andino Pneus de Venezuela, C.A.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">3) El Proveedor deber&aacute; garantizar la calidad y el buen funcionamiento de los productos/materiales/servicios, objeto de la presente orden de compra, a partir:</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">&nbsp;</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">FACTURAR EXACTAMENTE COMO SE INDICA A CONTINUACION:</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">ANDINO PNEUS DE VENEZUELA, C.A.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">FABRICA DE CAUCHOS, GUACARA ESTADO CARABOBO,</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">VENEZUELA.GUACARA VE</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">&nbsp;</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">Informaciones Generales:</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">&nbsp;</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">- Los gastos en los cuales incurra Andino Pneus de Venezuela, C.A. como consecuencia del incumplimiento de los t&eacute;rminos de la garant&iacute;a por parte del Proveedor, correr&aacute;n por cuenta de este &uacute;ltimo.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">- Todos los productos adquiridos por Andino Pneus de Venezuela, C.A. en virtud de la presente orden de compra, ser&aacute;n utilizados o consumidos exclusivamente por esta &uacute;ltima, dentro o fuera de sus instalaciones.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">&nbsp;</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">Condiciones generales:</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">&nbsp;</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">Las condiciones generales de esta orden de compra prevalecer&aacute;n sobre cualquier otra oferta efectuada con anterioridad por el Proveedor.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">Los t&eacute;rminos y condiciones generales establecidos en esta orden de compra, se tendr&aacute;n como aceptados, salvo que dentro de los primeros 5 d&iacute;as continuos siguientes de haberse emitido la orden de compra, el proveedor manifieste expresamente alguna observaci&oacute;n que sea aceptada por Andino Pneus de Venezuela, C.A.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">&nbsp;</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">1) PRECIOS</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">Los precios indicados en la presente orden de compra se mantendr&aacute;n fijos, al menos que las partes acuerden, por escrito, alg&uacute;n ajuste.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">&nbsp;</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">2) IMPUESTOS</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">Los precios indicados en la presente orden de compra no incluyen el impuesto al valor agregado (IVA), solo aplica para compras Nacionales.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">&nbsp;</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">3) ENTREGA:</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">Los plazos de entrega han sido establecidos de com&uacute;n acuerdo entre las partes y se entiende que cada plazo es el m&aacute;ximo para que el material, equipo y/o servicio sea entregado y/o culminado en f&aacute;brica.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">&nbsp;</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">En caso de cualquier incumplimiento de este plazo de entrega, el proveedor deber&aacute; anunciar las causas del retraso y asumir las p&eacute;rdidas ocasionadas, dar&aacute; derecho a</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">Andino Pneus de Venezuela, C.A.de cancelar (dejar sin efecto), la presente orden de compra, sin responsabilidad para esta &uacute;ltima sobre eventuales da&ntilde;os y perjuicios que pudieran afectar al proveedor, igualmente ser&aacute; bloqueado en nuestro sistemade registro de Proveedores.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">&nbsp;</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">4) FECHAS Y HORARIO DE ENTREGA,</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">Las entregas de materiales y/ o equipos se realizara dentro del siguiente horario:</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">De lunes a viernes de 07:00 a.m. a 2:30 p.m. D&iacute;as y horas distintas a las se&ntilde;aladas anteriormente, deber&aacute;n ser especificadas en las correspondientes &oacute;rdenes de compra.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">Para el caso de servicios, &eacute;stos de acuerdo a su naturaleza; ser&aacute;n ejecutados en horario convenido en mutuo acuerdo con el Solicitante y el Departamento de Compras.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">&nbsp;</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">5) TRANSPORTE Y EMBALAJE</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">Ser&aacute; responsabilidad &uacute;nica del proveedor las condiciones de flete y embalaje, de modo que garanticen la integridad de los equipos y Materia Prima suministrados.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">&Uacute;nicamente Andino Pneus ser&aacute; responsable de estas condiciones, siempre y cuando as&iacute; quede expresado en el texto de la solicitud.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">&nbsp;</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">6) DISE&Ntilde;OS MUESTRAS Y MODELOS</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">Los dise&ntilde;os, muestras y modelos que sean suministrados por Andino Pneus al proveedor para la realizaci&oacute;n de una determinada actividad, deber&aacute;n ser devueltas a esta &uacute;ltima conjuntamente con los equipos y/o materiales suministrados, lo cual ser&aacute; condici&oacute;n indispensable para aprobar y realizar el respectivo pago.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">&nbsp;</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">7) PARA TODOS LOS PEDIDOS:</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">El proveedor deber&aacute; entregar a Andino Pneus, conjuntamente con los equipos y / o componentes requeridos por &eacute;sta, toda la documentaci&oacute;n t&eacute;cnica, cat&aacute;logos, dise&ntilde;os y certificaciones de inspecci&oacute;n de control de calidad y de garant&iacute;a. Andino Pneus de Venezuela, C.A. se reserva el derecho de devolver la mercanc&iacute;a cuando este procedimiento no se cumpla.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">&nbsp;</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">8) SUMINISTRO Y USO (SERVICIOS) DE MATERIALES Y SUSTANCIAS PELIGROSAS:</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">En caso que la orden de compra contemple la entrega, suministro y/o utilizaci&oacute;n de Materiales o sustancias peligrosas, el proveedor deber&aacute; proporcionar las Fichas t&eacute;cnicas (MSDS) previo al ingreso y/o ejecuci&oacute;n de los servicios, as&iacute; como toda la documentaci&oacute;n (TDS) y permisos exigidos por las Leyes para su respectivo traslado y manejo, en caso que aplique.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">&nbsp;</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">9- DISPOSICI&Oacute;N DE RESIDUOS:</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">En referencia a los servicios que incluyan la disposici&oacute;n de escombros, material de suelo y material vegetal, es obligaci&oacute;n del proveedor consignar la documentaci&oacute;n correspondiente al lugar de disposici&oacute;n, ante el Departamento de Seguridad Industrial,</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">Salud Ocupacional y Ambiente.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">&nbsp;</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">10) FACTURACI&Oacute;N</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">El procedimiento de facturaci&oacute;n es conforme a la Providencia Administrativa N&deg;/SNAT/2011 071 de fecha 08/11/2011 seg&uacute;n Gaceta Oficial N&deg; 39.795, la cual establece las normas generales de emisi&oacute;n de facturas y otros documentos.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">&nbsp;</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">&nbsp;</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">Las facturas que emita el proveedor deber&aacute;n contener exactamente los mismos datos de cada orden de compra en lo que respecta a : cantidad, unidad de medida, precio, material, marca, entre otros.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">Andino Pneus de Venezuela, C.A.se reserva el derecho de no aceptar la factura que contenga una divergencia que no haya sido previamente comunicada y autorizada por escrito.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">&nbsp;</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">11) ENTREGA DE FACTURA:</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">El proveedor deber&aacute; entregar la factura &nbsp;firmada y sellada dentro de los 7 d&iacute;as despu&eacute;s de su emisi&oacute;n, la cual deber&aacute; contener:</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">-Para el caso de Suministros: Comprobante o sello de ingreso del almac&eacute;n de repuestos.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">-Para el caso de Servicios: Copia de la Hoja de Servicio emitida por el usuario solicitante con fecha anterior a la factura y copia de la orden de compra.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">- Para el caso de Pedidos Mixtos (Suministro y servicios). Debe consignar toda la documentaci&oacute;n anterior.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">&nbsp;</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">Dicha factura deber&aacute; ser consignada dentro del plazo se&ntilde;alado anteriormente, en el &aacute;rea de Finanzas de Andino Pneus de Venezuela, C.A.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">Requisitos de la Facturaci&oacute;n:</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">-El proveedor deber&aacute; incluir en la factura el n&uacute;mero de la orden de compra o Pedido.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">-Cualquier cambio en los precios, deber&aacute; ser debidamente justificado, procesado y notificado a Andino Pneus de Venezuela, C.A. previo a la presentaci&oacute;n de la factura.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">- Andino Pneus de Venezuela, C.A. se reserva el derecho de devolver toda documentaci&oacute;n que no est&eacute; conforme a lo se&ntilde;alado, y todos los gastos en que se incurra por &eacute;sta devoluci&oacute;n ser&aacute;n por cuenta del Proveedor.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">&nbsp;</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">12) INFORMACI&Oacute;N PARA AUDITOR&Iacute;A</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">El proveedor deber&aacute; consignar sin demora, toda la informaci&oacute;n y documentaci&oacute;n que requiera Auditoria Interna de Andino Pneus de Venezuela, C.A.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">&nbsp;</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">13) SANCI&Oacute;N:</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">Queda expresamente prohibida la venta y/o utilizaci&oacute;n de los productos de marca Andino Pneus por parte de los proveedores.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">Los infractores estar&aacute;n sujetos a sanciones legales.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">Se eximir&aacute; de responsabilidad a Andino Pneus de Venezuela, C.A., de las sanciones y/o el cobro de gastos o intereses, que genere el Proveedor por el incumplimiento de la presente orden de compra.</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">&nbsp;</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">Nota Final:</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">&nbsp;</p>
									<p style="vertical-align: bottom; font-family: serif;
									font-size: 8pt; color: #000000; ">El proveedor que incumpla alguna de la cl&aacute;usula y/o condiciones se&ntilde;aladas en la presente orden de compra, podr&aacute; ser excluido del registro de Proveedores de Andino Pneus de Venezuela, C.A.</p>
									';
				$sqlcotizacion = "SELECT
													CONCAT(T0.CPO_CARDNAME,' ',T2.DMS_CARD_LAST_NAME) Cliente,
													T0.CPO_CARDCODE Nit,
													CONCAT(T3.DMD_ADRESS,' ',T3.DMD_CITY) AS Direccion,
													T3.dmd_state_mm ciudad,
													t3.dmd_state estado,
													T4.DMC_PHONE1 Telefono,
													T4.DMC_EMAIL Email,
													T0.CPO_DOCNUM,
													T0.CPO_DOCNUM NumeroDocumento,
													T0.CPO_DOCDATE FechaDocumento,
													T0.CPO_DUEDATE FechaVenDocumento,
													T0.cpo_duedev fechaentrga,
													trim('COP' FROM t0.CPO_CURRENCY) MonedaDocumento,
													T7.PGM_NAME_MONEDA NOMBREMONEDA,
													T5.MEV_NAMES Vendedor,
													'' MedioPago,
													'' CondPago,
													T1.PO1_ITEMCODE Referencia,
													T1.PO1_ITEMNAME descripcion,
													T1.PO1_WHSCODE Almacen,
													T1.PO1_UOM UM,
													T1.PO1_QUANTITY Cantidad,
													T1.PO1_PRICE VrUnit,
													T1.PO1_DISCOUNT PrcDes,
													T1.PO1_VATSUM IVAP,
													T1.PO1_LINETOTAL ValorTotalL,
													T0.CPO_BASEAMNT base,
													T0.CPO_DISCOUNT Descuento,
													(T0.CPO_BASEAMNT - T0.CPO_DISCOUNT) subtotal,
													T0.CPO_TAXTOTAL Iva,
													T0.CPO_DOCTOTAL TotalDoc,
													T0.CPO_COMMENT Comentarios,
													t6.pgs_mde,
													t6.pgs_mpfn,
													T8.MPF_NAME cond_pago,
													t3.dmd_tonw lugar_entrega,
													t5.mev_names nombre_contacto,
											    t5.mev_mail correo_contacto,
											    t5.mev_phone telefono_contacto,
													t0.cpo_date_inv fecha_fact_pro,
													t0.cpo_date_del fecha_entre,
													t0.cpo_place_del lugar_entre,
													concat(t9.dmc_name,' ',t9.dmc_last_name) nombre_contacto_p,
													t9.dmc_email correo_contacto_p,
													t9.dmc_cel telefono_contacto_p
												FROM dcpo  t0
												INNER JOIN CPO1 T1 ON t0.CPO_docentry = t1.PO1_docentry
												LEFT JOIN DMSN T2 ON t0.CPO_cardcode = t2.dms_card_code
												LEFT JOIN DMSD T3 ON t2.dms_card_code = t3.dmd_card_code AND t3.dmd_ppal = 1
												LEFT JOIN DMSC T4 ON T0.CPO_CONTACID = CAST(T4.DMC_ID AS VARCHAR)
												LEFT JOIN DMEV T5 ON T0.CPO_SLPCODE = T5.MEV_ID
												LEFT JOIN PGDN T6 ON T0.CPO_DOCTYPE = T6.PGS_ID_DOC_TYPE AND T0.CPO_SERIES = T6.PGS_ID
												LEFT JOIN PGEC T7 ON T0.CPO_CURRENCY = T7.PGM_SYMBOL
												LEFT JOIN DMPF T8 ON T2.DMS_PAY_TYPE = cast(T8.MPF_ID as  varchar)
												left join dmsc t9 on t0.cpo_cardcode = t9.dmc_card_code
												WHERE T0.CPO_DOCENTRY = :CPO_DOCENTRY and t2.dms_card_type = '2'";

				$contenidoOC = $this->pedeo->queryTable($sqlcotizacion,array(':CPO_DOCENTRY'=>$Data));
// print_r($sqlcotizacion);exit();die();
				if(!isset($contenidoOC[0])){
						$respuesta = array(
							 'error' => true,
							 'data'  => $contenidoOC,
							 'mensaje' =>'no se encontro el documento'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}
				// print_r($contenidoOC);exit();die();
				$fecha_entrega = '';

				if(empty($contenidoOC[0]['fecha_entre'])){
					$fecha_entrega = '';
				}else{
					$fecha_entrega = $this->dateformat->Date($contenidoOC[0]['fecha_entre']);
				}
				$fecha_fact_pro = '';

				if(empty($contenidoOC[0]['fecha_fact_pro'])){
					$fecha_fact_pro = '';
				}else{
					$fecha_fact_pro = $this->dateformat->Date($contenidoOC[0]['fecha_fact_pro']);
				}



				$consecutivo = '';

				if($contenidoOC[0]['pgs_mpfn'] == 1){
					$consecutivo = $contenidoOC[0]['numerodocumento'];
				}else{
					$consecutivo = $contenidoOC[0]['cpo_docnum'];
				}


				$totaldetalle = '';
				foreach ($contenidoOC as $key => $value) {
					// code...
					$detalle = '<td>'.$value['referencia'].'</td>
											<td>'.$value['descripcion'].'</td>
											<td>'.$value['um'].'</td>
											<td>'.$value['monedadocumento']." ".number_format($value['vrunit'], $DECI_MALES, ',', '.').'</td>
											<td>'.$value['cantidad'].'</td>
											<td>'.$value['prcdes'].'</td>
											<td>'.$value['monedadocumento']." ".number_format($value['ivap'], $DECI_MALES, ',', '.').'</td>
											<td>'.$value['monedadocumento']." ".number_format($value['valortotall'], $DECI_MALES, ',', '.').'</td>';
				 $totaldetalle = $totaldetalle.'<tr>'.$detalle.'</tr>';
				}


        $header = '
				<table width="100%" style="text-align: left;">
        			<tr>
            			<th style="text-align: left;">
							<img src="/var/www/html/'.$company[0]['company'].'/'.$empresa[0]['pge_logo'].'" 
							width ="100" height ="40"></img>
						</th>
            			<th>
							<p><b>'.$empresa[0]['pge_name_soc'].'</b></p>
							<p><b>'.$empresa[0]['pge_id_type'].'</b></p>
							<p><b>'.$empresa[0]['pge_add_soc'].'</b></p>
						</th>
						<th>
						    <p><b>Orden de Compra</b></p>
						</th>
					/tr>
				</table>';

				$footer = '
        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th  width="33%">Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
            </tr>
        </table>';


        $html = '
				<table  width="100%">
        <tr>

            <th style="text-align: left;">
						<p><b>'.$empresa[0]['pge_small_name'].'</b></p>
						<p>'.$empresa[0]['pge_add_soc'].'</p>
						<p>'.$empresa[0]['pge_id_soc'].' / '.$empresa[0]['pge_id_type'].'</p>
						<p>'.$empresa[0]['pge_state_soc'].'</p>
						<p>TELEFONO:'.$empresa[0]['pge_phone1'].' / '.$empresa[0]['pge_phone2'].'</p>
						<p>website: '.$empresa[0]['pge_web_site'].'</p>
						<p>Instagram: '.$empresa[0]['pge_page_social'].'</p>
            </th>
            <th style="text-align: right;">
								<p><b>OC: </b></p>
						</th>
						<th style="text-align: left;">
                <p >'.$contenidoOC[0]['numerodocumento'].'</p>
						</th>
						<th style="text-align: right;">
								<th style="text-align: right;">
									<p><b>FECHA DE EMISIÓN: </b></p>
						</th>
									<th style="text-align: left;">
									<p>'.$this->dateformat->Date($contenidoOC[0]['fechadocumento']).'</p>

            </th>
        </tr>

        </table>
				<table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th class="fondo">
                    <p></p>
                </th>
            </tr>
        </table>

				<table  width="100%" font-family: serif>
				<tr>
					<th style="text-align: left;"><b>PROVEEDOR</b><th>
					<th style="text-align: right;"><b>'.$empresa[0]['pge_small_name'].'</b><th>
				</tr>
				</table>
				<table  width="100%" font-family: serif>
				<tr>
					<th style="text-align: left;"><b>RIF:</b><span>'.$contenidoOC[0]['nit'].'</span></p></th>
					<th style="text-align: right;"><b>nombre contacto:</b> <span >'.$contenidoOC[0]['nombre_contacto'].'</span></p></th>
				</tr>
				<tr>
					<th style="text-align: left;"><b>nombre proveedor:</b> <span>'.$contenidoOC[0]['cliente'].'</span></p></th>
					<th style="text-align: right;"><b>correo contacto:</b> <span>'.$contenidoOC[0]['correo_contacto'].'</span></p></th>
				</tr>
				<tr>
					<th style="text-align: left;"><b>direccion:</b> <span>'.$contenidoOC[0]['direccion'].'</span></p></th>
					<th style="text-align: right;"><b>telefono contacto:</b> <span>'.$contenidoOC[0]['telefono_contacto'].'</span></p></th>
				</tr>
				<tr>
					<th style="text-align: left;"><b>ciudad:</b> <span>'.$contenidoOC[0]['ciudad'].'</span></p></th>
				</tr>
				<tr>
					<th style="text-align: left;"><b>estado:</b> <span>'.$contenidoOC[0]['estado'].'</span></p></th>
				</tr>
				<tr>
					<th style="text-align: left;"><b>nombre contacto:</b> <span>'.$contenidoOC[0]['nombre_contacto_p'].'</span></p></th>
				</tr>
				<tr>
					<th style="text-align: left;"><b>telefono contacto:</b> <span>'.$contenidoOC[0]['telefono_contacto_p'].'</span></p></th>
				</tr>
				<tr>
					<th style="text-align: left;"><b>correo contacto:</b> <span>'.$contenidoOC[0]['correo_contacto_p'].'</span></p></th>
				</tr>



				</table>
				<table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th class="fondo">
                    <p></p>
                </th>
            </tr>
        </table>
				<table table  width="100%">
				<tr>
				<th style="text-align: center;"><b>CONDICION DE PAGO:</b></th>
				<th style="text-align: center;"><b>Lugar de Entrega:</b></th>
				<th style="text-align: center;"><b>FECHA DE ENTREGA:</b></th>
				<th style="text-align: center;"><b>FECHA FACTURA PROVEEDOR:</b></th>
				</tr>
				<tr>
				<td>'.$contenidoOC[0]['cond_pago'].'</td>
				<td>'.$contenidoOC[0]['lugar_entre'].'</td>
				<td>'.$fecha_entrega.'</td>
				<td>'.$fecha_fact_pro.'</td>
				</tr>

				</table>
				<br>

        <table class="borde" style="width:100%">



        <tr>
          <th><b>ITEM</b></th>
          <th><b>REFERENCIA</b></th>
          <th><b>UNIDAD</b></th>
          <th><b>PRECIO</b></th>
          <th><b>CANTIDAD</b></th>
          <th><b>DESCUENTO</b></th>
          <th><b>IVA</b></th>
          <th><b>TOTAL</b></th>
        </tr>
      	'.$totaldetalle.'
        </table>
        <br>
				<table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th class="fondo">
                    <p></p>
                </th>
            </tr>
        </table>

        <br>
        <table width="100%">

        <tr>
            <td style="text-align: right;"><b>Base Documento:</b>  <span>'.$contenidoOC[0]['monedadocumento']." ".number_format($contenidoOC[0]['base'], $DECI_MALES, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;"><b>Descuento:</b>  <span>'.$contenidoOC[0]['monedadocumento']." ".number_format($contenidoOC[0]['descuento'], $DECI_MALES, ',', '.').'</span></p></td>
        </tr>
				<tr>
            <td style="text-align: right;"><b>Sub Total:</b>  <span>'.$contenidoOC[0]['monedadocumento']." ".number_format($contenidoOC[0]['subtotal'], $DECI_MALES, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;"><b>Impuestos:</b>  <span>'.$contenidoOC[0]['monedadocumento']." ".number_format($contenidoOC[0]['iva'], $DECI_MALES, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;"><b>Total:</b>  <span>'.$contenidoOC[0]['monedadocumento']." ".number_format($contenidoOC[0]['totaldoc'], $DECI_MALES, ',', '.').'</span></p></td>
        </tr>
				</table>
				<table  width="100%">
				<tr>
            <td style="text-align: left;"><b>comentarios (ver adjunto de instruccion de envio):</b>
						<br><p>'.$contenidoOC[0]['comentarios'].'</p>
						</p>
						</td>
        </tr>


        </table>
				<br>
				<br>

        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th style="text-align: left;" >
										<p><b>VALOR EN LETRAS:</b></p><br>
                    <p>'.$formatter->toWords($contenidoOC[0]['totaldoc'],$DECI_MALES)." ".$contenidoOC[0]['nombremoneda'].'</p>
                </th>
            </tr>
        </table>


				<pagebreak/>'
				.$observaciones.
				'</html>';

        $stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');

        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);


        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);

				$filename = 'OC_'.$contenidoOC[0]['numerodocumento'].'.pdf';
        $mpdf->Output($filename, 'D');


				header('Content-type: application/force-download');
				header('Content-Disposition: attachment; filename='.$filename);


	}




}
