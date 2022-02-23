<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class NcVenta extends REST_Controller {

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


	public function NcVenta_post(){

        $Data = $this->post();
				$Data = $Data['VNC_DOCENTRY'];

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
		           'mensaje' =>'no esta registrada la información de la empresa'
		        );

	          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	          return;
				}

				$sqlcotizacion = "SELECT
													CONCAT(T0.VNC_CARDNAME,' ',T2.DMS_CARD_LAST_NAME) Cliente,
													TRIM('CN' FROM T0.VNC_CARDCODE) Nit,
													CONCAT(T3.DMD_ADRESS,' ',T3.DMD_CITY) Direccion,
													T4.DMC_PHONE1 Telefono,
													T4.DMC_EMAIL Email,
													CONCAT(T6.PGS_PREF_NUM,' ',T0.VNC_DOCNUM) NumeroDocumento,
													to_char(T0.VNC_DOCDATE,'DD-MM-YYYY') FechaDocumento,
													to_char(T0.VNC_DUEDATE,'DD-MM-YYYY') FechaVenDocumento,
													trim('COP' FROM t0.VNC_CURRENCY) MonedaDocumento,
													T7.PGM_NAME_MONEDA NOMBREMONEDA,
													T5.MEV_NAMES Vendedor,
													'' MedioPago,
													'' CondPago,
													T1.NC1_ITEMCODE Referencia,
													T1.NC1_ITEMNAME descripcion,
													T1.NC1_WHSCODE Almacen,
													T1.NC1_UOM UM,
													T1.NC1_QUANTITY Cantidad,
													T1.NC1_PRICE VrUnit,
													T1.NC1_DISCOUNT PrcDes,
													T1.NC1_VATSUM IVAP,
													T1.NC1_LINETOTAL ValorTotalL,
													T0.VNC_BASEAMNT base,
													T0.VNC_DISCOUNT Descuento,
													(T0.VNC_BASEAMNT - T0.VNC_DISCOUNT) subtotal,
													T0.VNC_TAXTOTAL Iva,
													T0.VNC_DOCTOTAL TotalDoc,
													T0.VNC_COMMENT Comentarios
												FROM DVNC t0
												INNER JOIN VNC1 T1 ON t0.VNC_docentry = t1.NC1_docentry
												LEFT JOIN DMSN T2 ON t0.VNC_cardcode = t2.dms_card_code
												LEFT JOIN DMSD T3 ON T0.VNC_ADRESS = CAST(T3.DMD_ID AS VARCHAR) AND t3.dmd_ppal = 1
												LEFT JOIN DMSC T4 ON T0.VNC_CONTACID = CAST(T4.DMC_ID AS VARCHAR)
												LEFT JOIN DMEV T5 ON T0.VNC_SLPCODE = T5.MEV_ID
												LEFT JOIN PGDN T6 ON T0.VNC_DOCTYPE = T6.PGS_ID_DOC_TYPE AND T0.VNC_SERIES = T6.PGS_ID
												LEFT JOIN PGEC T7 ON T0.VNC_CURRENCY = T7.PGM_SYMBOL
												WHERE T0.VNC_DOCENTRY = :VNC_DOCENTRY
												and t2.dms_card_type = '1'";

				$contenidoNcV = $this->pedeo->queryTable($sqlcotizacion,array(':VNC_DOCENTRY'=>$Data));

				if(!isset($contenidoNcV[0])){
						$respuesta = array(
							 'error' => true,
							 'data'  => $empresa,
							 'mensaje' =>'no se encontro el documento'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}
				// print_r($contenidoNcV);exit();die();

				$totaldetalle = '';
				foreach ($contenidoNcV as $key => $value) {
					// code...
					$detalle = '<td>'.$value['referencia'].'</td>
											<td>'.$value['descripcion'].'</td>
											<td>'.$value['um'].'</td>
											<td>'.$value['monedadocumento']." ".number_format($value['vrunit'], 2, ',', '.').'</td>
											<td>'.$value['cantidad'].'</td>
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
                <p>NOTA CREDITO DE VENTA</p>
                <p class="fondo">'.$contenidoNcV[0]['numerodocumento'].'</p>

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
					<th>
						<p class="fondo">SEÑOR(ES):</p>
					</th>
					<th style="text-align: left;">
						<p>'.$contenidoNcV[0]['cliente'].'</p>
					</th>
					<th>
						<p class="fondo">FECHA DE EXPEDICIÓN </p>
						<p>'.$contenidoNcV[0]['fechadocumento'].'</p>
					</th>
				</tr>
				<tr>
					<th>
						<p class="fondo">DIRECCIÓN:</p>
					</th>
					<th style="text-align: left;">
						<p>'.$contenidoNcV[0]['direccion'].'</p>
					</th>

				</tr>
				<tr>
					<th>
						<p class="fondo">TELÉFONO:</p>
					</th>
					<th style="text-align: left;">
						<p>
							<span>'.$contenidoNcV[0]['telefono'].'</span>
								<span class ="fondo">RIF:</span>
								<span>'.$contenidoNcV[0]['nit'].'</span>
						</p>
					</th>
					<th>
						<p class="fondo">FECHA DE VENCIMIENTO </p>
						<p>'.$contenidoNcV[0]['fechavendocumento'].'</p>
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
            <td style="text-align: right;">Base Documento: <span>'.$contenidoNcV[0]['monedadocumento']." ".number_format($contenidoNcV[0]['base'], 2, ',', '.').'</span></p></td>
        </tr>
				<tr>
            <td style="text-align: right;">Sub Total: <span>'.$contenidoNcV[0]['monedadocumento']." ".number_format($contenidoNcV[0]['subtotal'], 2, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;">Impuestos: <span>'.$contenidoNcV[0]['monedadocumento']." ".number_format($contenidoNcV[0]['iva'], 2, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;">Total: <span>'.$contenidoNcV[0]['monedadocumento']." ".number_format($contenidoNcV[0]['totaldoc'], 2, ',', '.').'</span></p></td>
        </tr>

        </table>


        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th style="text-align: left;" class="fondo">
                    <p>'.$formatter->toWords($contenidoNcV[0]['totaldoc'],2)." ".$contenidoNcV[0]['nombremoneda'].'</p>
                </th>
            </tr>
        </table>
		<br>
		<table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;" >
				<tr><th style="text-align: left;">Comentarios:</th></tr>
				<tr><td style="text-align: left;">'.$contenidoNcV[0]['comentarios'].'</td></tr>
		</table>
		';

        $stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');

        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);


        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);


        $mpdf->Output('Doc.pdf', 'D');

				header('Content-type: application/force-download');
				header('Content-Disposition: attachment; filename='.$filename);


	}




}
