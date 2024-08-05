<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class SolCompra extends REST_Controller {

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


	public function SolCompra_post(){

				$DECI_MALES =  $this->generic->getDecimals();

        $Data = $this->post();
				$Data = $Data['CSC_DOCENTRY'];

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
		           'mensaje' =>'no esta registrada la información de la contenidosolcompra'
		        );

	          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	          return;
				}

				$sqlcotizacion = "SELECT
													CONCAT(T0.CSC_CARDNAME,' ',T2.DMS_CARD_LAST_NAME) Cliente,
													T0.CSC_CARDCODE Nit,
													CONCAT(T3.DMD_ADRESS,' ',T3.DMD_CITY) Direccion,
													T4.DMC_PHONE1 Telefono,
													T4.DMC_EMAIL Email,
													CONCAT(T6.PGS_PREF_NUM,' ',T0.CSC_DOCNUM) NumeroDocumento,
													T0.CSC_DOCDATE FechaDocumento,
													T0.CSC_DUEDATE FechaVenDocumento,
													trim('COP' FROM t0.CSC_CURRENCY) MonedaDocumento,
													T7.PGM_NAME_MONEDA NOMBREMONEDA,
													T5.MEV_NAMES solicitante,
													'' MedioPago,
													'' CondPago,
													T1.SC1_ITEMCODE Referencia,
													T1.SC1_ITEMNAME descripcion,
													T1.SC1_WHSCODE Almacen,
													T1.SC1_UOM UM,
													T1.SC1_QUANTITY Cantidad,
													T1.SC1_PRICE VrUnit,
													T1.SC1_DISCOUNT PrcDes,
													T1.SC1_VATSUM IVAP,
													T1.SC1_LINETOTAL ValorTotalL,
													T0.CSC_BASEAMNT base,
													T0.CSC_DISCOUNT Descuento,
													(T0.CSC_BASEAMNT - T0.CSC_DISCOUNT) subtotal,
													T0.CSC_TAXTOTAL Iva,
													T0.CSC_DOCTOTAL TotalDoc,
													T0.CSC_COMMENT Comentarios
												FROM dcsc  t0
												INNER JOIN CSC1 T1 ON t0.CSC_docentry = t1.SC1_docentry
												LEFT JOIN DMSN T2 ON t0.CSC_cardcode = t2.dms_card_code
												LEFT JOIN DMSD T3 ON T0.CSC_ADRESS = CAST(T3.DMD_ID AS VARCHAR)
												LEFT JOIN DMSC T4 ON T0.CSC_CONTACID = CAST(T4.DMC_ID AS VARCHAR)
												LEFT JOIN DMEV T5 ON T0.CSC_SLPCODE = T5.MEV_ID
												LEFT JOIN PGDN T6 ON T0.CSC_DOCTYPE = T6.PGS_ID_DOC_TYPE AND T0.CSC_SERIES = T6.PGS_ID
												LEFT JOIN PGEC T7 ON T0.CSC_CURRENCY = T7.PGM_SYMBOL
												WHERE T0.CSC_DOCENTRY = :CSC_DOCENTRY";

				$contenidosolcompra = $this->pedeo->queryTable($sqlcotizacion,array(':CSC_DOCENTRY'=>$Data));

				if(!isset($contenidosolcompra[0])){
						$respuesta = array(
							 'error' => true,
							 'data'  => $contenidosolcompra,
							 'mensaje' =>'no se encontro el documento'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}
				// print_r($contenidosolcompra);exit();die();

				$totaldetalle = '';
				foreach ($contenidosolcompra as $key => $value) {
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
                <p>SOLICITUD DE COMPRA</p>
                <p class="fondo">'.$contenidosolcompra[0]['numerodocumento'].'</p>

            </th>
        </tr>

        </table>';

        $footer = '
        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">

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
          	<p class="fondo">solicitante:</p>
          </th>
          <th style="text-align: left;">
          	<p>'.$contenidosolcompra[0]['solicitante'].'</p>
          </th>
          <th>
            <p class="fondo">FECHA DE SOLICITUD </p>
            <p>'.$contenidosolcompra[0]['fechadocumento'].'</p>
          </th>
        </tr>

        <tr>


          <th>
            <p class="fondo">FECHA DE REQUERIDA </p>
            <p>'.$contenidosolcompra[0]['fechavendocumento'].'</p>
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
            <td style="text-align: right;">Base Documento: <span>'.$contenidosolcompra[0]['monedadocumento']." ".number_format($contenidosolcompra[0]['base'], $DECI_MALES, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;">Descuento: <span>'.$contenidosolcompra[0]['monedadocumento']." ".number_format($contenidosolcompra[0]['descuento'], $DECI_MALES, ',', '.').'</span></p></td>
        </tr>
				<tr>
            <td style="text-align: right;">Sub Total: <span>'.$contenidosolcompra[0]['monedadocumento']." ".number_format($contenidosolcompra[0]['subtotal'], $DECI_MALES, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;">Impuestos: <span>'.$contenidosolcompra[0]['monedadocumento']." ".number_format($contenidosolcompra[0]['iva'], $DECI_MALES, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;">Total: <span>'.$contenidosolcompra[0]['monedadocumento']." ".number_format($contenidosolcompra[0]['totaldoc'], $DECI_MALES, ',', '.').'</span></p></td>
        </tr>

        </table>


        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th style="text-align: left;" class="fondo">
                    <p>'.$formatter->toWords($contenidosolcompra[0]['totaldoc'],2)." ".$contenidosolcompra[0]['nombremoneda'].'</p>
                </th>
            </tr>
        </table>

        <br><br>
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
