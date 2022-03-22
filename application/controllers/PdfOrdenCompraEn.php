<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class PdfOrdenCompraEn extends REST_Controller {

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


	public function PdfOrdenCompraEn_post(){

        $Data = $this->post();
				$Data = $Data['CPO_DOCENTRY'];

				$formatter = new NumeroALetras();

				// $mpdf = new \Mpdf\Mpdf(['setAutoBottomMargin' => 'stretch','setAutoTopMargin' => 'stretch']);
				// $mpdf = new \Mpdf\Mpdf('utf-8');
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
   font-size: 8pt; color: #000000; "><u style="vertical-align: bottom; font-family: serif;">Andino Pneus Purchase order (P.O) for international agreements</u></p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<ul style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">
   <li style="vertical-align: bottom; font-family: serif;">The Supplier must comply, without delay, with the delivery of the products or materials and / or execution of services that are part of this purchase order.</li>
   <li style="vertical-align: bottom; font-family: serif;">Any modification regarding the terms and conditions of this purchase order must have the prior written approval of Andino Pneus de Venezuela, C.A.</li>
   <li style="vertical-align: bottom; font-family: serif;">The Supplier must guarantee the quality and proper functioning of the products / materials / services, object of this purchase order, starting from:</li>
</ul>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; BILL EXACTLY AS INDICATED BELOW:</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<ul style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">
   <li style="vertical-align: bottom; font-family: serif;">ANDINO PNEUS DE VENEZUELA, C.A.</li>
   <li style="vertical-align: bottom; font-family: serif;">TIRE MANUFACTORY, GUACARA CARABOBO STATE, VENEZUELA.GUACARA VE.</li>
</ul>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">General information:</p>
<ol style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">
   <li style="vertical-align: bottom; font-family: serif;">- The expenses incurred by Andino Pneus de Venezuela, C.A. As a consequence of the breach of the terms of the guarantee by the Supplier, they will be borne by the latter.</li>
   <li style="vertical-align: bottom; font-family: serif;">- All products purchased by Andino Pneus de Venezuela, C.A. By virtue of this purchase order, they will be used or consumed exclusively by the latter, inside of their facilities to produce tires.</li>
</ol>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">General conditions:</p>
<ol style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">
   <li style="vertical-align: bottom; font-family: serif;">The general conditions of this purchase order will prevail over any other offer made previously by the Supplier.</li>
   <li style="vertical-align: bottom; font-family: serif;">The general terms and conditions established in this purchase order will be considered accepted, except that within the first 5 continuous days after the purchase order has been issued, the supplier expressly states any observation that is accepted by Andino Pneus de Venezuela, C.A.</li>
</ol>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">PRICES:</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">The prices indicated in this purchase order will remain fixed, unless the parties agree, in writing, to an adjustment.</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">TAXES:</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">The prices indicated in this purchase order do not include value added tax (VAT), it only applies to National purchases. <em><u style="vertical-align: bottom; font-family: serif;">(This only apply in Local P.O)</u></em></p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">DELIVERY:</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">The delivery times have been established by mutual agreement between the parties and it is understood that each term is the maximum for the material, equipment and / or service to be delivered and / or completed at the factory. In case of any breach of this delivery period, the supplier must announce the causes of the delay and assume the losses caused, will give the right to Andino Pneus de Venezuela, C.A. to cancel (leave without effect), this purchase order, without responsibility for the latter for possible damages that could affect the supplier, it will also be blocked in our system Supplier registration.</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">DELIVERY DATES AND TIME:</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">Deliveries of materials and / or equipment will be made within the following hours:</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">Monday through Friday from 07:00 a.m. at 2:30 p.m. Days and hours other than those indicated above, must be specified in the corresponding purchase orders. In the case of services, these according to their nature; they will be executed at a time agreed upon in mutual agreement with the Applicant and the Purchasing Department.</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">TRANSPORTATION AND PACKAGING:</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">The supplier will be solely responsible for the conditions of freight and packaging, so as to guarantee the integrity of the equipment and Raw Material supplied. Only Andino Pneus will be responsible for these conditions, as long as it is stated in the text of the application.</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">DESIGNS SAMPLES AND MODELS:</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">The designs, samples and models that are supplied by Andino Pneus to the supplier to carry out a certain activity, must be returned to the latter together with the equipment and / or materials supplied, which will be an essential condition to approve and make the respective payment.</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">FOR ALL ORDERS:</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">The supplier must deliver to Andino Pneus, together with the equipment and / or components required by it, all the technical documentation, catalogs, designs and quality control inspection and guarantee certifications. Andino Pneus de Venezuela, C.A. reserves the right to return the merchandise when this procedure is not followed.</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">SUPPLY AND USE (SERVICES) OF HAZARDOUS MATERIALS AND SUBSTANCES:</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">In the event that the purchase order contemplates the delivery, supply and / or use of materials or dangerous substances, the supplier must provide the technical sheets (MSDS) prior to the entry and / or execution of the services, as well as all the documentation (TDS) and permits required by law for their respective transfer and handling, if applicable.</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">DISPOSAL OF WASTE:</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">In reference to the services that include the disposal of rubble, soil material and plant material, it is the supplier\'s obligation to consign the documentation corresponding to the place of disposal, before the Department of Industrial Safety, Occupational Health and Environment.</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">BILLING:</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">The billing procedure is in accordance with Administrative Ruling No. / SNAT / 2011 071 dated 08/11/2011 according to Official Gazette No. 39,795, which establishes the general rules for issuing invoices and other documents. The invoices issued by the supplier must contain exactly the same data for each purchase order in regards to: quantity, unit of measure, price, material, brand, among others.</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">Andino Pneus de Venezuela, C.A. reserves the right not to accept the invoice that contains a divergence that has not been previously communicated and authorized in writing.</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">DELIVERY OF INVOICE:</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">The supplier must deliver the invoice signed and stamped within 7 days after its issuance, which must contain:</p>
<ul style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">
   <li style="vertical-align: bottom; font-family: serif;">In The case of Supplies: Proof or entry stamp from the spare parts store.</li>
   <li style="vertical-align: bottom; font-family: serif;">In the case of Services: Copy of the Service Sheet issued by the requesting user with a date prior to the invoice and a copy of the purchase order.</li>
   <li style="vertical-align: bottom; font-family: serif;">In the case of Mixed Orders (Supply and services). You must consign all the previous documentation.</li>
   <li style="vertical-align: bottom; font-family: serif;">Said invoice must be consigned within the period indicated above, in the Finance area of ​​Andino Pneus de Venezuela, C.A.</li>
</ul>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">BILLING REQUIREMENTS:</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">-The supplier must include in the invoice the number of the purchase order or Order.</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">-Any change in prices must be duly justified, processed and notified to Andino Pneus de Venezuela, C.A. prior to the presentation of the invoice.</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">- Andino Pneus de Venezuela, C.A. reserves the right to return any documentation that is not in accordance with the provisions, and all expenses incurred for this return will be paid by the Supplier.</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">INFORMATION FOR AUDIT:</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">The supplier must consign without delay, all the information and documentation required by Internal Audit of Andino Pneus de Venezuela, C.A.</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">SANCTION:</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">&nbsp;</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; ">The sale and / or use of Andino Pneus brand products by suppliers are expressly prohibited. Violators will be subject to legal sanctions. Andino Pneus de Venezuela, C.A., will be exempted from responsibility of the sanctions and / or the collection of expenses or interests, generated by the Supplier for the breach of this purchase order.</p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; "><em>&nbsp;</em></p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; "><em>Final note:</em></p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; "><em>&nbsp;</em></p>
<p style="vertical-align: bottom; font-family: serif;
   font-size: 8pt; color: #000000; "><em>The supplier that breaches any of the clause and / or conditions indicated in this purchase order may be excluded from the registry of Suppliers of Andino Pneus de Venezuela, C.A.</em></p>';
				$sqlcotizacion = "SELECT
													CONCAT(T0.CPO_CARDNAME,' ',T2.DMS_CARD_LAST_NAME) Cliente,
													T0.CPO_CARDCODE Nit,
													CONCAT(T3.DMD_ADRESS,' ',T3.DMD_CITY) AS Direccion,
													T3.dmd_state_mm ciudad,
													t3.dmd_state estado,
													T4.DMC_PHONE1 Telefono,
													T4.DMC_EMAIL Email,
													T0.CPO_DOCNUM,
													CONCAT('PO',' ',T0.CPO_DOCNUM) NumeroDocumento,
													T0.CPO_DOCDATE FechaDocumento,
													T0.CPO_DUEDATE FechaVenDocumento,
													T0.cpo_duedev fechaentrga,
													trim('COP' FROM t0.CPO_CURRENCY) MonedaDocumento,
													T7.PGM_NAME_MONEDA NOMBREMONEDA,
													T5.MEV_NAMES Vendedor,
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
													t3.dmd_tonw lugar_entrega
												FROM dcpo  t0
												INNER JOIN CPO1 T1 ON t0.CPO_docentry = t1.PO1_docentry
												LEFT JOIN DMSN T2 ON t0.CPO_cardcode = t2.dms_card_code
												LEFT JOIN DMSD T3 ON t2.dms_card_code = t3.dmd_card_code AND t3.dmd_ppal = 1
												LEFT JOIN DMSC T4 ON T0.CPO_CONTACID = CAST(T4.DMC_ID AS VARCHAR)
												LEFT JOIN DMEV T5 ON T0.CPO_SLPCODE = T5.MEV_ID
												LEFT JOIN PGDN T6 ON T0.CPO_DOCTYPE = T6.PGS_ID_DOC_TYPE AND T0.CPO_SERIES = T6.PGS_ID
												LEFT JOIN PGEC T7 ON T0.CPO_CURRENCY = T7.PGM_SYMBOL
												LEFT JOIN DMPF T8 ON CAST(T2.DMS_PAY_TYPE AS INT) = T8.MPF_ID
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
                <p>PURCHASE ORDER</p>
                <p>'.$contenidoOC[0]['numerodocumento'].'</p>

            </th>
        </tr>

        </table>';

				$footer = '
        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th  width="33%">Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
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
						<p class="">Name: </p>
					</th>
					<th style="text-align: left;">
						<p>'.$contenidoOC[0]['cliente'].'</p>
					</th>
				</tr>
				<tr>
					<th style="text-align: left;">
						<p class="">email contact: </p>
					</th>
					<th style="text-align: left;">
						<p> '.$contenidoOC[0]['email'].'</p>
					</th>
				</tr>
				<tr>
					<th style="text-align: left;">
						<p class="">address: </p>
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
						<p class="">Town: </p>
					</th>
					<th style="text-align: left;">
						<p> '.$contenidoOC[0]['ciudad'].'</p>
					</th>
				</tr>
				<tr>
					<th style="text-align: left;">
						<p class="">state: </p>
					</th>
					<th style="text-align: left;">
						<p> '.$contenidoOC[0]['estado'].'</p>
					</th>
				</tr>
				<tr>
					<th style="text-align: left;">
						<p class="">SHIPPING TERMS: </p>
					</th>
					<th style="text-align: left;">
						<p>'.$contenidoOC[0]['cond_pago'].'</p>
					</th>
				</tr>
				<tr>
					<th style="text-align: left;">
						<p class="">SHIPPING METHOD: </p>
					</th>
					<th style="text-align: left;">
						<p>'.$contenidoOC[0]['lugar_entrega'].'</p>
					</th>
				</tr>
				<tr>
					<th style="text-align: left;">
						<p class="">DELIVERY DATE:</p>
					</th>
					<th style="text-align: left;">
						<p>'.date("d-m-Y", strtotime($contenidoOC[0]['fechaentrga'])).'</p>
					</th>
					<th style="text-align: right;">
						<p class="">DATE: </p>
					</th>

					<th style="text-align: right;">
						<p>'.date("d-m-Y", strtotime($contenidoOC[0]['fechadocumento'])).'</p>
					</th>
				</tr>
				</table>

        <br>

        <table class="borde" style="width:100%">
        <tr>
          <th>ITEM</th>
          <th>DESCRIPTION</th>
          <th>UND</th>
          <th>UNIT PRICE</th>
          <th>QUANTITY</th>
          <th>DISCOUNT</th>
          <th>VAT</th>
          <th>TOTAL</th>
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


        <table width="100%" >


        <tr>

            <td style="text-align: right;">DOCUMENT BASIS: <span>'.$contenidoOC[0]['monedadocumento']." ".number_format($contenidoOC[0]['base'], 2, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;">DISCOUNT: <span>'.$contenidoOC[0]['monedadocumento']." ".number_format($contenidoOC[0]['descuento'], 2, ',', '.').'</span></p></td>
        </tr>
				<tr>
            <td style="text-align: right;">SUBTOTAL: <span>'.$contenidoOC[0]['monedadocumento']." ".number_format($contenidoOC[0]['subtotal'], 2, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;">Taxes: <span>'.$contenidoOC[0]['monedadocumento']." ".number_format($contenidoOC[0]['iva'], 2, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;">Total: <span>'.$contenidoOC[0]['monedadocumento']." ".number_format($contenidoOC[0]['totaldoc'], 2, ',', '.').'</span></p></td>
        </tr>
				</table>
				<table class="bordew" width="100%">
				<tr>
            <td style="text-align: left;">Comments or Special Instructions:
						<p>'.$contenidoOC[0]['comentarios'].'</p>
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
                    <p> '.$this->traslate($formatter->toWords($contenidoOC[0]['totaldoc'],2)." ".$contenidoOC[0]['nombremoneda']).'</p>
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
			//	$mpdf->WriteHTML($html,\Mpdf\ScriptToLang);

				$filename = $contenidoOC[0]['numerodocumento'].'.pdf';
        $mpdf->Output($filename, 'D');


				header('Content-type: application/force-download');
				header('Content-Disposition: attachment; filename='.$filename);


	}

	private function traslate($text_to_translate){

	  $encoded_text = urlencode(strip_tags($text_to_translate));

	  $url = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=es&tl=en&dt=t&q=' . $encoded_text;

	  $ch = curl_init();
	  curl_setopt($ch, CURLOPT_URL, $url);
	  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	  curl_setopt($ch, CURLOPT_PROXYPORT, 3128);
	  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	  curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
	  curl_setopt($ch, CURLOPT_USERAGENT, 'AndroidTranslate/5.3.0.RC02.130475354-53000263 5.1 phone TRANSLATE_OPM5_TEST_1');
	  $output = curl_exec($ch);
	  curl_close($ch);


	  $response_a = json_decode($output);

	  if( isset( $response_a[0][0][0] ) ){
	    return $response_a[0][0][0];
	  }else{
	    return $text;
	  }

	}

}