<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class PdfOfertaCompra extends REST_Controller {

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


	public function OfCompra_post(){

				$DECI_MALES =  $this->generic->getDecimals();

        $Data = $this->post();
				$Data = $Data['COC_DOCENTRY'];

				$formatter = new NumeroALetras();




        $mpdf = new \Mpdf\Mpdf(['setAutoBottomMargin' => 'stretch','setAutoTopMargin' => 'stretch']);

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
		           'mensaje' =>'no esta registrada la información de la contenidoCoc'
		        );

	          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	          return;
				}

				$sqlcotizacion = "SELECT
                          CONCAT(T0.COC_CARDNAME,' ',T2.DMS_CARD_LAST_NAME) Cliente,
                          T0.COC_CARDCODE Nit,
                          CONCAT(T3.DMD_ADRESS,' ',T3.DMD_CITY) Direccion,
                          T3.dmd_state_mm ciudad,
                          t3.dmd_state estado,
                          T4.DMC_PHONE1 Telefono,
                          T4.DMC_EMAIL Email,
                          T0.COC_DOCNUM,
                          CONCAT(T6.PGS_PREF_NUM,' ',T0.COC_DOCNUM) NumeroDocumento,
                          to_char(T0.COC_DOCDATE,'DD-MM-YYYY') FechaDocumento,
                          to_char(T0.COC_DUEDATE,'DD-MM-YYYY') FechaVenDocumento,
                          trim('COP' FROM t0.COC_CURRENCY) MonedaDocumento,
                          T7.PGM_NAME_MONEDA NOMBREMONEDA,
                          T5.MEV_NAMES Vendedor,
                          '' MedioPago,
                          '' CondPago,
                          T1.OC1_ITEMCODE Referencia,
                          T1.OC1_ITEMNAME descripcion,
                          T1.OC1_WHSCODE Almacen,
                          T1.OC1_UOM UM,
                          T1.OC1_QUANTITY Cantidad,
                          T1.OC1_PRICE VrUnit,
                          T1.OC1_DISCOUNT PrcDes,
                          T1.OC1_VATSUM IVAP,
                          T1.OC1_LINETOTAL ValorTotalL,
                          T0.COC_BASEAMNT base,
                          T0.COC_DISCOUNT Descuento,
                          (T0.COC_BASEAMNT - T0.COC_DISCOUNT) subtotal,
                          T0.COC_TAXTOTAL Iva,
                          T0.COC_DOCTOTAL TotalDoc,
                          T0.COC_COMMENT Comentarios,
                          t6.pgs_mde,
                          t6.pgs_mpfn
                          FROM DCOC t0
                          INNER JOIN COC1 T1 ON t0.COC_docentry = t1.OC1_docentry
                          LEFT JOIN DMSN T2 ON t0.COC_cardcode = t2.dms_card_code
                          LEFT JOIN DMSD T3 ON T0.COC_cardcode = T3.DMD_card_code AND t3.dmd_ppal = 1
                          LEFT JOIN DMSC T4 ON T0.COC_CONTACID = CAST(T4.DMC_ID AS VARCHAR)
                          LEFT JOIN DMEV T5 ON T0.COC_SLPCODE = T5.MEV_ID
                          LEFT JOIN PGDN T6 ON T0.COC_DOCTYPE = T6.PGS_ID_DOC_TYPE AND T0.COC_SERIES = T6.PGS_ID
                          LEFT JOIN PGEC T7 ON T0.COC_CURRENCY = T7.PGM_SYMBOL
                          WHERE T0.COC_DOCENTRY = :COC_DOCENTRY";

				$contenidoCoc = $this->pedeo->queryTable($sqlcotizacion,array(':COC_DOCENTRY'=>$Data));

				if(!isset($contenidoCoc[0])){
						$respuesta = array(
							 'error' => true,
							 'data'  => $contenidoCoc,
							 'mensaje' =>'no se encontro el documento'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}
				// print_r($contenidoCoc);exit();die();

				$consecutivo = '';

				if($contenidoCoc[0]['pgs_mpfn'] == 1){
					$consecutivo = $contenidoCoc[0]['numerodocumento'];
				}else{
					$consecutivo = $contenidoCoc[0]['coc_docnum'];
				}

				$totaldetalle = '';
				foreach ($contenidoCoc as $key => $value) {
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
                <p>OFERTA DE COMPRA</p>
                <p class="fondo">'.$contenidoCoc[0]['numerodocumento'].'</p>

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

				<table class="bordew" style="width:100%">
				<tr>
					<th style="text-align: left;">
						<p class="">RIF: </p>
					</th>
					<th style="text-align: left;">
						<p> '.$contenidoCoc[0]['nit'].'</p>
					</th>
				</tr>
				<tr>
					<th style="text-align: left;">
						<p class="">NOMBRE: </p>
					</th>
					<th style="text-align: left;">
						<p> '.$contenidoCoc[0]['cliente'].'</p>
					</th>
				</tr>
				<tr>
					<th style="text-align: left;">
						<p class="">DIRECCIÓN: </p>
					</th>
					<th style="text-align: left;">
						<p> '.$contenidoCoc[0]['direccion'].'</p>
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
						<p> '.$contenidoCoc[0]['ciudad'].'</p>
					</th>
				</tr>
				<tr>
					<th style="text-align: left;">
						<p class="">ESTADO: </p>
					</th>
					<th style="text-align: left;">
						<p> '.$contenidoCoc[0]['estado'].'</p>
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
						<p>'.date("d-m-Y", strtotime($contenidoCoc[0]['fechadocumento'])).'</p>
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
            <td style="text-align: right;">Base Documento: <span>'.$contenidoCoc[0]['monedadocumento']." ".number_format($contenidoCoc[0]['base'], $DECI_MALES, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;">Descuento: <span>'.$contenidoCoc[0]['monedadocumento']." ".number_format($contenidoCoc[0]['descuento'], $DECI_MALES, ',', '.').'</span></p></td>
        </tr>
				<tr>
            <td style="text-align: right;">Sub Total: <span>'.$contenidoCoc[0]['monedadocumento']." ".number_format($contenidoCoc[0]['subtotal'], $DECI_MALES, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;">Impuestos: <span>'.$contenidoCoc[0]['monedadocumento']." ".number_format($contenidoCoc[0]['iva'], $DECI_MALES, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;">Total: <span>'.$contenidoCoc[0]['monedadocumento']." ".number_format($contenidoCoc[0]['totaldoc'], $DECI_MALES, ',', '.').'</span></p></td>
        </tr>

        </table>


        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th style="text-align: left;" class="fondo">
                    <p>'.$formatter->toWords($contenidoCoc[0]['totaldoc'],2)." ".$contenidoCoc[0]['nombremoneda'].'</p>
                </th>
            </tr>
        </table>

      ';

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
