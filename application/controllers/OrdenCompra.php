<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class OrdenCompra extends REST_Controller {

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


	public function OrdenCompra_post(){

        $Data = $this->post();
				$Data = $Data['CPO_DOCENTRY'];

				$formatter = new NumeroALetras();

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

				$empresa = $this->pedeo->queryTable("SELECT pge_id, pge_name_soc, pge_small_name, pge_add_soc, pge_state_soc, pge_city_soc,
																					   pge_cou_soc, CONCAT(pge_id_type,' ',pge_id_soc) AS pge_id_type , pge_web_site, pge_logo,
																					   CONCAT(pge_phone1,' ',pge_phone2,' ',pge_cel) AS pge_phone1, pge_branch, pge_mail,
																					   pge_curr_first, pge_curr_sys, pge_cou_bank, pge_bank_def,pge_bank_acct, pge_acc_type
																						 FROM pgem", array());

				if(!isset($empresa[0])){
						$respuesta = array(
		           'error' => true,
		           'data'  => $empresa,
		           'mensaje' =>'no esta registrada la información de la empresa'
		        );

	          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	          return;
				}

				$observaciones = '<p style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; ">1) El Proveedor deberá cumplir, sin retardo, con la entrega de los productos o materiales y/o ejecución de servicios que forman parte de la presente orden de compra.
				<br>
				<br>
2) Cualquier modificación en lo que respecta a los términos y condiciones de la presente orden de compra, deberá contar con la aprobación previa y por escrito por parte de Andino Pneus de Venezuela, C.A.
	<br>
	<br>
3) El Proveedor deberá garantizar la calidad y el buen funcionamiento de los productos/materiales/servicios, objeto de la presente orden de compra, a partir :
	<br>
	<br>
FACTURAR EXACTAMENTE COMO SE INDICA A CONTINUACION:
	<br>
	<br>
ANDINO PNEUS DE VENEZUELA, C.A.
	<br>
	<br>
FABRICA DE CAUCHOS, GUACARA ESTADO CARABOBO,
	<br>
	<br>
VENEZUELA.GUACARA VE
	<br>
	<br>
Informaciones Generales:
	<br>
	<br>
- Los gastos en los cuales incurra Andino Pneus de Venezuela, C.A. como consecuencia del incumplimiento de los términos de la garantía por parte del Proveedor, correrán por cuenta de este último.
	<br>
	<br>
- Todos los productos adquiridos por Andino Pneus de Venezuela, C.A. en virtud de la presente orden de compra, serán utilizados o consumidos exclusivamente por esta última, dentro o fuera de sus instalaciones.
	<br>
	<br>
Condiciones generales:
	<br>
	<br>
Las condiciones generales de esta orden de compra prevalecerán sobre cualquier otra oferta efectuada con anterioridad por el Proveedor.
	<br>
	<br>
Los términos y condiciones generales establecidos en esta orden de compra, se tendrán como aceptados, salvo que dentro de los primeros 5 días continuos siguientes de haberse emitido la orden de compra, el proveedor manifieste expresamente alguna observación que sea aceptada por Andino Pneus de Venezuela, C.A.
	<br>
	<br>
1) PRECIOS
	<br>
	<br>
Los precios indicados en la presente orden de compra se mantendrán fijos, al menos que las partes acuerden, por escrito, algún ajuste.
	<br>
	<br>
2) IMPUESTOS
	<br>
	<br>
Los precios indicados en la presente orden de compra no incluyen el impuesto al valor agregado (IVA), solo aplica para compras Nacionales.
<br>
<br>
3) ENTREGA:
<br>
<br>
Los plazos de entrega han sido establecidos de común acuerdo entre las partes y se entiende que cada plazo es el máximo para que el material, equipo y/o servicio sea entregado y/o culminado en fábrica.
<br>
<br>
En caso de cualquier incumplimiento de este plazo de entrega, el proveedor deberá anunciar las causas del retraso y asumir las pérdidas ocasionadas, dará derecho a
Andino Pneus de Venezuela, C.A.de cancelar (dejar sin efecto), la presente orden de compra, sin responsabilidad para esta última sobre eventuales daños y perjuicios que pudieran afectar al proveedor, igualmente será bloqueado en nuestro sistema
de registro de Proveedores.
<br>
<br>
4) FECHAS Y HORARIO DE ENTREGA,
<br>
<br>
Las entregas de materiales y/ o equipos se realizara dentro del siguiente horario:
<br>
<br>
De lunes a viernes de 07:00 a.m. a 2:30 p.m. Días y horas distintas a las señaladas anteriormente, deberán ser especificadas en las correspondientes órdenes de compra.
<br>
<br>
Para el caso de servicios, éstos de acuerdo a su naturaleza; serán ejecutados en horario convenido en mutuo acuerdo con el Solicitante y el Departamento de Compras.
<br>
<br>
5) TRANSPORTE Y EMBALAJE
<br>
<br>
Será responsabilidad única del proveedor las condiciones de flete y embalaje, de modo que garanticen la integridad de los equipos y Materia Prima suministrados.
<br>
<br>
Únicamente Andino Pneus será responsable de estas condiciones, siempre y cuando así quede expresado en el texto de la solicitud.
<br>
<br>
6) DISEÑOS MUESTRAS Y MODELOS
<br>
<br>
Los diseños, muestras y modelos que sean suministrados por Andino Pneus al proveedor para la realización de una determinada actividad, deberán ser devueltas a esta última conjuntamente con los equipos y/o materiales suministrados, lo cual será condición indispensable para aprobar y realizar el respectivo pago.
<br>
<br>
7) PARA TODOS LOS PEDIDOS:
<br>
<br>
El proveedor deberá entregar a Andino Pneus, conjuntamente con los equipos y / o componentes requeridos por ésta, toda la documentación técnica, catálogos, diseños y certificaciones de inspección de control de calidad y de garantía. Andino Pneus de Venezuela, C.A. se reserva el derecho de devolver la mercancía cuando este procedimiento no se cumpla.
<br>
<br>
8) SUMINISTRO Y USO (SERVICIOS) DE MATERIALES Y SUSTANCIAS PELIGROSAS:
<br>
<br>
En caso que la orden de compra contemple la entrega, suministro y/o utilización de Materiales o sustancias peligrosas, el proveedor deberá proporcionar las Fichas técnicas (MSDS) previo al ingreso y/o ejecución de los servicios, así como toda la documentación (TDS) y permisos exigidos por las Leyes para su respectivo traslado y manejo, en caso que aplique.
<br>
<br>
9) DISPOSICIÓN DE RESIDUOS:
<br>
<br>
En referencia a los servicios que incluyan la disposición de escombros, material de suelo y material vegetal, es obligación del proveedor consignar la documentación correspondiente al lugar de disposición, ante el Departamento de Seguridad Industrial,
Salud Ocupacional y Ambiente.
<br>
<br>
10) FACTURACIÓN
<br>
<br>
El procedimiento de facturación es conforme a la Providencia Administrativa N°/SNAT/2008 0257 de fecha 19/08/2008 según Gaceta Oficial N° 38.997, la cual establece las normas generales de emisión de facturas y otros documentos.
<br>
<br>
Las facturas que emita el proveedor deberán contener exactamente los mismos datos de cada orden de compra en lo que respecta a : cantidad, unidad de medida, precio, material, marca, entre otros.
Andino Pneus de Venezuela, C.A.se reserva el derecho de no aceptar la factura que contenga una divergencia que no haya sido previamente comunicada y autorizada por escrito.
<br>
<br>
11) ENTREGA DE FACTURA:
<br>
<br>
El proveedor deberá entregar la factura dentro de los 7 días después de su emisión, la cual deberá contener:
<br>
<br>
-Para el caso de Suministros: Comprobante o sello de ingreso del almacén de repuestos.
<br>
<br>
-Para el caso de Servicios: Copia de la Hoja de Servicio emitida por el usuario solicitante con fecha anterior a la factura y copia de la orden de compra.
<br>
<br>
- Para el caso de Pedidos Mixtos (Suministro y servicios). Debe consignar toda la documentación anterior.
<br>
<br>
Dicha factura deberá ser consignada dentro del plazo señalado anteriormente, en el área de Finanzas de Andino Pneus de Venezuela, C.A.
<br>
<br>
Requisitos de la Facturación:
<br>
<br>
-El proveedor deberá incluir en la factura el número de la orden de compra o Pedido.
<br>
<br>
-Cualquier cambio en los precios, deberá ser debidamente justificado, procesado y notificado a Andino Pneus de Venezuela, C.A. previo a la presentación de la factura.
<br>
<br>
- Andino Pneus de Venezuela, C.A. se reserva el derecho de devolver toda documentación que no esté conforme a lo señalado, y todos los gastos en que se incurra por ésta devolución serán por cuenta del Proveedor.
<br>
<br>
12) INFORMACIÓN PARA AUDITORÍA
<br>
<br>
El proveedor deberá consignar sin demora, toda la información y documentación que requiera Auditoria Interna de Andino Pneus de Venezuela, C.A.
<br>
<br>
13) SANCIÓN:
<br>
<br>
Queda expresamente prohibida la venta y/o utilización de los productos de marca Andino Pneus por parte de los proveedores.
<br>
<br>
Los infractores estarán sujetos a sanciones legales.
<br>
<br>
Se eximirá de responsabilidad a Andino Pneus de Venezuela, C.A., de las sanciones y/o el cobro de gastos o intereses, que genere el Proveedor por el incumplimiento de la presente orden de compra.
<br>
<br>
Nota Final:
<br>
<br>
El proveedor que incumpla alguna de la cláusula y/o condiciones señaladas en la presente orden de compra, podrá ser excluido del registro de Proveedores de Andino Pneus de Venezuela, C.A.
</p>';

$observacionesIngles = '

<p style="vertical-align: bottom; font-family: serif;
		font-size: 8pt; color: #000000; ">
Andino Pneus Purchase order (P.O) for international agreements
<br>
<br>
1)	The Supplier must comply, without delay, with the delivery of the products or materials and / or execution of services that are part of this purchase order.
<br>
<br>
2)	 Any modification regarding the terms and conditions of this purchase order must have the prior written approval of Andino Pneus de Venezuela, C.A.
<br>
<br>
3)	The Supplier must guarantee the quality and proper functioning of the products / materials / services, object of this purchase order, starting from:
<br>
<br>
 BILL EXACTLY AS INDICATED BELOW:
 <br>
 <br>
•	ANDINO PNEUS DE VENEZUELA, C.A.
<br>
<br>
•	TIRE MANUFACTORY, GUACARA CARABOBO STATE, VENEZUELA.GUACARA VE.
<br>
<br>
General information:
	<br>
	<br>
1.	- The expenses incurred by Andino Pneus de Venezuela, C.A. As a consequence of the breach of the terms of the guarantee by the Supplier, they will be borne by the latter.
<br>
<br>
2.	- All products purchased by Andino Pneus de Venezuela, C.A. By virtue of this purchase order, they will be used or consumed exclusively by the latter, inside of their facilities to produce tires.
<br>
General conditions:
1.	The general conditions of this purchase order will prevail over any other offer made previously by the Supplier.
<br>
<br>
2.	The general terms and conditions established in this purchase order will be considered accepted, except that within the first 5 continuous days after the purchase order has been issued, the supplier expressly states any observation that is accepted by Andino Pneus de Venezuela, C.A.
<br>
<br>
PRICES:
<br>
<br>
The prices indicated in this purchase order will remain fixed, unless the parties agree, in writing, to an adjustment.
<br>
<br>
TAXES:
<br>
<br>
The prices indicated in this purchase order do not include value added tax (VAT), it only applies to National purchases. (This only apply in Local P.O)
<br>
<br>
DELIVERY:
<br>
<br>
The delivery times have been established by mutual agreement between the parties and it is understood that each term is the maximum for the material, equipment and / or service to be delivered and / or completed at the factory. In case of any breach of this delivery period, the supplier must announce the causes of the delay and assume the losses caused, will give the right to Andino Pneus de Venezuela, C.A. to cancel (leave without effect), this purchase order, without responsibility for the latter for possible damages that could affect the supplier, it will also be blocked in our system Supplier registration.
<br>
<br>
DELIVERY DATES AND TIME:
<br>
<br>
Deliveries of materials and / or equipment will be made within the following hours:
Monday through Friday from 07:00 a.m. at 2:30 p.m. Days and hours other than those indicated above, must be specified in the corresponding purchase orders. In the case of services, these according to their nature; they will be executed at a time agreed upon in mutual agreement with the Applicant and the Purchasing Department.
<br>
<br>
TRANSPORTATION AND PACKAGING:
<br>
<br>
The supplier will be solely responsible for the conditions of freight and packaging, so as to guarantee the integrity of the equipment and Raw Material supplied. Only Andino Pneus will be responsible for these conditions, as long as it is stated in the text of the application.
<br>
<br>
DESIGNS SAMPLES AND MODELS:
<br>
<br>
The designs, samples and models that are supplied by Andino Pneus to the supplier to carry out a certain activity, must be returned to the latter together with the equipment and / or materials supplied, which will be an essential condition to approve and make the respective payment.
<br>
<br>
FOR ALL ORDERS:
<br>
<br>
The supplier must deliver to Andino Pneus, together with the equipment and / or components required by it, all the technical documentation, catalogs, designs and quality control inspection and guarantee certifications. Andino Pneus de Venezuela, C.A. reserves the right to return the merchandise when this procedure is not followed.
<br>
<br>
SUPPLY AND USE (SERVICES) OF HAZARDOUS MATERIALS AND SUBSTANCES:
<br>
<br>
In the event that the purchase order contemplates the delivery, supply and / or use of materials or dangerous substances, the supplier must provide the technical sheets (MSDS) prior to the entry and / or execution of the services, as well as all the documentation (TDS) and permits required by law for their respective transfer and handling, if applicable.
<br>
<br>
DISPOSAL OF WASTE:
<br>
<br>
In reference to the services that include the disposal of rubble, soil material and plant material, it is the supplier s obligation to consign the documentation corresponding to the place of disposal, before the Department of Industrial Safety, Occupational Health and Environment.
<br>
<br>
BILLING:
<br>
<br>
The billing procedure is in accordance with Administrative Ruling No. / SNAT / 2011 071 dated 08/11/2011 according to Official Gazette No. 39,795, which establishes the general rules for issuing invoices and other documents. The invoices issued by the supplier must contain exactly the same data for each purchase order in regards to: quantity, unit of measure, price, material, brand, among others.
<br>
<br>
Andino Pneus de Venezuela, C.A. reserves the right not to accept the invoice that contains a divergence that has not been previously communicated and authorized in writing.
<br>
<br>
DELIVERY OF INVOICE:
<br>
<br>
The supplier must deliver the invoice signed and stamped within 7 days after its issuance, which must contain:
<br>
<br>
-	In The case of Supplies: Proof or entry stamp from the spare parts store.
<br>
<br>
-	In the case of Services: Copy of the Service Sheet issued by the requesting user with a date prior to the invoice and a copy of the purchase order.
<br>
<br>
-	In the case of Mixed Orders (Supply and services). You must consign all the previous documentation.
<br>
<br>
-	Said invoice must be consigned within the period indicated above, in the Finance area of Andino Pneus de Venezuela, C.A.
<br>
<br>
BILLING REQUIREMENTS:
<br>
<br>
-The supplier must include in the invoice the number of the purchase order or Order.
<br>
<br>
-Any change in prices must be duly justified, processed and notified to Andino Pneus de Venezuela, C.A. prior to the presentation of the invoice.
<br>
<br>
- Andino Pneus de Venezuela, C.A. reserves the right to return any documentation that is not in accordance with the provisions, and all expenses incurred for this return will be paid by the Supplier.
<br>
<br>
INFORMATION FOR AUDIT:
<br>
<br>
The supplier must consign without delay, all the information and documentation required by Internal Audit of Andino Pneus de Venezuela, C.A.
<br>
<br>
SANCTION:
<br>
<br>
The sale and / or use of Andino Pneus brand products by suppliers are expressly prohibited. Violators will be subject to legal sanctions. Andino Pneus de Venezuela, C.A., will be exempted from responsibility of the sanctions and / or the collection of expenses or interests, generated by the Supplier for the breach of this purchase order.
<br>
<br>
Final note:
<br>
<br>
The supplier that breaches any of the clause and / or conditions indicated in this purchase order may be excluded from the registry of Suppliers of Andino Pneus de Venezuela, C.A.</p>';
				$sqlcotizacion = "SELECT
													CONCAT(T0.CPO_CARDNAME,' ',T2.DMS_CARD_LAST_NAME) Cliente,
													T0.CPO_CARDCODE Nit,
													CONCAT(T3.DMD_ADRESS,' ',T3.DMD_CITY) Direccion,
													T3.dmd_state_mm ciudad,
													t3.dmd_state estado,
													T4.DMC_PHONE1 Telefono,
													T4.DMC_EMAIL Email,
													T0.CPO_DOCNUM,
													CONCAT(T6.PGS_PREF_NUM,' ',T0.CPO_DOCNUM) NumeroDocumento,
													T0.CPO_DOCDATE FechaDocumento,
													T0.CPO_DUEDATE FechaVenDocumento,
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
													t6.pgs_mpfn
												FROM dcpo  t0
												INNER JOIN CPO1 T1 ON t0.CPO_docentry = t1.PO1_docentry
												LEFT JOIN DMSN T2 ON t0.CPO_cardcode = t2.dms_card_code
												LEFT JOIN DMSD T3 ON T0.CPO_ADRESS = CAST(T3.DMD_ID AS VARCHAR) AND t3.dmd_ppal = 1
												LEFT JOIN DMSC T4 ON T0.CPO_CONTACID = CAST(T4.DMC_ID AS VARCHAR)
												LEFT JOIN DMEV T5 ON T0.CPO_SLPCODE = T5.MEV_ID
												LEFT JOIN PGDN T6 ON T0.CPO_DOCTYPE = T6.PGS_ID_DOC_TYPE AND T0.CPO_SERIES = T6.PGS_ID
												LEFT JOIN PGEC T7 ON T0.CPO_CURRENCY = T7.PGM_SYMBOL
												WHERE T0.CPO_DOCENTRY = :CPO_DOCENTRY";

				$contenidoOC = $this->pedeo->queryTable($sqlcotizacion,array(':CPO_DOCENTRY'=>$Data));

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
											<td>'.$value['monedadocumento']." ".number_format($value['vrunit'], 2, ',', '.').'</td>
											<td>'.$value['cantidad'].'</td>
											<td>'.$value['prcdes'].'</td>
											<td>'.$value['monedadocumento']." ".number_format($value['ivap'], 2, ',', '.').'</td>
											<td>'.$value['monedadocumento']." ".number_format($value['valortotall'], 2, ',', '.').'</td>';
				 $totaldetalle = $totaldetalle.'<tr>'.$detalle.'</tr>';
				}


        $header = '
        <table width="100%">
        <tr>
            <th style="text-align: left;"><img src="/var/www/html/'.$company[0]['company'].'/'.$empresa[0]['pge_logo'].'" width ="100" height ="40"></img></th>
            <th>
                <p>'.$empresa[0]['pge_name_soc'].'</p>
                <p>'.$empresa[0]['pge_id_type'].'</p>
                <p>'.$empresa[0]['pge_add_soc'].'</p>
                <p>'.$empresa[0]['pge_phone1'].'</p>
                <p>'.$empresa[0]['pge_web_site'].'</p>
                <p>'.$empresa[0]['pge_mail'].'</p>
            </th>
            <th>
                <p>ORDEN DE COMPRA</p>
                <p class="fondo">'.$contenidoOC[0]['numerodocumento'].'</p>

            </th>
        </tr>

        </table>';

				$footer = '
        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th class="fondo" width="33%">Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
            </tr>
        </table>';


        $html = '
				<table class="bordew" width="100%" >
				<tr>
					<th style="text-align: left;">
						<p class="">RIF: </p>
					</th>
					<th style="text-align: left;">
						<p> '.$contenidoOC[0]['nit'].'</p>
					</th>
				</tr>
				<tr>
					<th style="text-align: left;">
						<p class="">NOMBRE: </p>
					</th>
					<th style="text-align: left;">
						<p> '.$contenidoOC[0]['cliente'].'</p>
					</th>
				</tr>
				<tr>
					<th style="text-align: left;">
						<p class="">DIRECCIÓN: </p>
					</th>
					<th style="text-align: left;">
						<p> '.$contenidoOC[0]['direccion'].'</p>
					</th>
					<th style="text-align: right;">
						<p class=""></p>
					</th>
					<th style="text-align: right;">
						<p></p>
					</th>
				</tr>
				<tr>
					<th style="text-align: left;">
						<p class="">CIUDAD: </p>
					</th>
					<th style="text-align: left;">
						<p> '.$contenidoOC[0]['ciudad'].'</p>
					</th>
				</tr>
				<tr>
					<th style="text-align: left;">
						<p class="">ESTADO: </p>
					</th>
					<th style="text-align: left;">
						<p> '.$contenidoOC[0]['estado'].'</p>
					</th>
				</tr>
				<tr>
					<th style="text-align: left;">
						<p class=""></p>
					</th>
					<th style="text-align: left;">
						<p></p>
					</th>
				</tr>
				<tr>
					<th style="text-align: left;">
						<p class=""></p>
					</th>
					<th style="text-align: left;">
						<p></p>
					</th>
				</tr>
				<tr>
					<th style="text-align: left;">
						<p class=""></p>
					</th>
					<th style="text-align: left;">
						<p></p>
					</th>
					<th style="text-align: right;">
						<p class="">FECHA DE EMISIÓN: </p>
					</th>
					<th style="text-align: right;">
						<p>'.date("d-m-Y", strtotime($contenidoOC[0]['fechadocumento'])).'</p>
					</th>
				</tr>
				</table>

        <br>

        <table class="borde" style="width:100%">
        <tr>
          <th class="fondo">ITEM</th>
          <th class="fondo">REFERENCIA</th>
          <th class="fondo">UNIDAD</th>
          <th class="fondo">PRECIO</th>
          <th class="fondo">CANTIDAD</th>
          <th class="fondo">DESCUENTO</th>
          <th class="fondo">IVA</th>
          <th class="fondo">TOTAL</th>
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
            <td style="text-align: right;">Base Documento: <span>'.$contenidoOC[0]['monedadocumento']." ".number_format($contenidoOC[0]['base'], 2, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;">Descuento: <span>'.$contenidoOC[0]['monedadocumento']." ".number_format($contenidoOC[0]['descuento'], 2, ',', '.').'</span></p></td>
        </tr>
				<tr>
            <td style="text-align: right;">Sub Total: <span>'.$contenidoOC[0]['monedadocumento']." ".number_format($contenidoOC[0]['subtotal'], 2, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;">Impuestos: <span>'.$contenidoOC[0]['monedadocumento']." ".number_format($contenidoOC[0]['iva'], 2, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;">Total: <span>'.$contenidoOC[0]['monedadocumento']." ".number_format($contenidoOC[0]['totaldoc'], 2, ',', '.').'</span></p></td>
        </tr>

        </table>


        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th style="text-align: left;" class="fondo">
                    <p>'.$formatter->toWords($contenidoOC[0]['totaldoc'],2)." ".$contenidoOC[0]['nombremoneda'].'</p>
                </th>
            </tr>
        </table>


				<pagebreak/>'
				.$observaciones.
				'<br>
				<br>'
				.$observacionesIngles.'</html>';

        $stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');

        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);


        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);

				$filename = 'Doc.pdf';
        $mpdf->Output('Doc.pdf', 'D');


				header('Content-type: application/force-download');
				header('Content-Disposition: attachment; filename='.$filename);


	}




}
