<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PdfDocument extends REST_Controller {

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


	public function GenerateQuote_get(){

        $Data = $this->get();

        $mpdf = new \Mpdf\Mpdf(['setAutoBottomMargin' => 'stretch','setAutoTopMargin' => 'stretch']);

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

        $header = '
        <table width="100%">
        <tr>
            <th style="text-align: left;"><img src="http://zupordesk.com/serpent/assets/img/logo.jpeg" width ="100" height ="40"></img></th>
            <th>
                <p>'.$empresa[0]['pge_name_soc'].'</p>
                <p>'.$empresa[0]['pge_id_type'].'</p>
                <p>'.$empresa[0]['pge_add_soc'].'</p>
                <p>'.$empresa[0]['pge_phone1'].'</p>
                <p>'.$empresa[0]['pge_web_site'].'</p>
                <p>'.$empresa[0]['pge_mail'].'</p>
            </th>
            <th>
                <p>FACTURA DE VENTA</p>
                <p class="fondo">No. WT5083</p>
                <p>Responsable del IVA</p>
            </th>
        </tr>

        </table>';

        $footer = '
        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th style="text-align: center;">
                    <p>Autorización de numeración de facturación N°18764009111647 de 2020-12-22 Modalidad Factura Electrónica Desde N° WT5000 hasta WT10000 con
                    vigencia hasta 2021-12-22.
                    </p>
                </th>
            </tr>
        </table>
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
          	<p>DEOFANOR GONZALEZ ESCUDERO</p>
          </th>
          <th>
            <p class="fondo">FECHA DE EXPEDICIÓN (DD/MM/AA)</p>
            <p>26/07/2021</p>
          </th>
        </tr>
        <tr>
          <th>
            <p class="fondo">DIRECCIÓN:</p>
          </th>
          <th style="text-align: left;">
            <p>Cra 45 #70-237, Colombia, Barranquilla, Atlantico</p>
          </th>

        </tr>
        <tr>
          <th>
            <p class="fondo">TELÉFONO:</p>
          </th>
          <th style="text-align: left;">
            <p>
            	<span>+57 3006525858</span>
                <span class ="fondo">CC</span>
                <span>683012</span>
            </p>
          </th>
          <th>
            <p class="fondo">FECHA DE VENCIMIENTO (DD/MM/AA)</p>
            <p>26/07/2021</p>
          </th>
        </tr>
        </table>

        <br>

        <table class="borde" style="width:100%">
        <tr>
          <th class="fondo">ID</th>
          <th class="fondo">ÍTEM</th>
          <th class="fondo">UNIDAD</th>
          <th class="fondo">PRECIO</th>
          <th class="fondo">CANTIDAD</th>
          <th class="fondo">DESCUENTO</th>
          <th class="fondo">IVA</th>
          <th class="fondo">TOTAL</th>
        </tr>
        <tr>
            <td>COD0001</td>
            <td>Monitor 14 pulgadas Full HD</td>
            <td>U</td>
            <td>$500.000,00</td>
            <td>1</td>
            <td>0</td>
            <td>$95.000</td>
            <td>$500.000,00</td>

        </tr>

        <tr>
            <td>COD0001</td>
            <td>Monitor 14 pulgadas Full HD</td>
            <td>U</td>
            <td>$500.000,00</td>
            <td>1</td>
            <td>0</td>
            <td>$95.000</td>
            <td>$500.000,00</td>
        </tr>

        <tr>
            <td>COD0001</td>
            <td>Monitor 14 pulgadas Full HD</td>
            <td>U</td>
            <td>$500.000,00</td>
            <td>1</td>
            <td>0</td>
            <td>$95.000</td>
            <td>$500.000,00</td>
        </tr>




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
            <td style="text-align: right;">Base documento: <span>$500.000</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;">Descuento: <span>$0</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;">Subtotal: <span>$595.000</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;">Impuestos: <span>$285.000</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;">Total: <span>$595.000</span></p></td>
        </tr>

        </table>


        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th style="text-align: left;" class="fondo">
                    <p>Quinientos noventa y cinco mil</p>
                </th>
            </tr>
        </table>

        <br><br>
        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th style="text-align: left;">
                    <p>Esta factura se asimila en todos sus efectos a una letra de cambio de conformidad con el Art. 774 del código de
                    comercio. Autorizo que en caso de incumplimiento de esta obligación sea reportado a las centrales de riesgo, se
                    cobraran intereses por mora.
                    </p>
                </th>
            </tr>
        </table>';

        $stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');

        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);


        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);


        $mpdf->Output();


	}




}
