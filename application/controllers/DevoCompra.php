<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class DevoCompra extends REST_Controller {

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


	public function DevoCompra_post(){

				$DECI_MALES =  $this->generic->getDecimals();

        $Data = $this->post();

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
													FROM pgem WHERE pge_id = :pge_id", array(':pge_id' => $Data['business']));

				if(!isset($empresa[0])){
						$respuesta = array(
		           'error' => true,
		           'data'  => $empresa,
		           'mensaje' =>'no esta registrada la información de la contenido'
		        );

	          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	          return;
				}

				$sqlcotizacion = "SELECT
													CONCAT(T0.CDC_CARDNAME,' ',T2.DMS_CARD_LAST_NAME) Cliente,
													T0.CDC_CARDCODE Nit,
													CONCAT(T3.DMD_ADRESS,' ',T3.DMD_CITY) Direccion,
													T4.DMC_PHONE1 Telefono,
													T4.DMC_EMAIL Email,
													CONCAT(T6.PGS_PREF_NUM,' ',T0.CDC_DOCNUM) NumeroDocumento,
													T0.CDC_DOCDATE FechaDocumento,
													T0.CDC_DUEDATE FechaVenDocumento,
													trim('COP' FROM t0.CDC_CURRENCY) MonedaDocumento,
													T7.PGM_NAME_MONEDA NOMBREMONEDA,
													T5.MEV_NAMES Vendedor,
													'' MedioPago,
													'' CondPago,
													T1.DC1_ITEMCODE Referencia,
													T1.DC1_ITEMNAME descripcion,
													T1.DC1_WHSCODE Almacen,
													T1.DC1_UOM UM,
													T1.DC1_QUANTITY Cantidad,
													T1.DC1_PRICE VrUnit,
													T1.DC1_DISCOUNT PrcDes,
													T1.DC1_VATSUM IVAP,
													T1.DC1_LINETOTAL ValorTotalL,
													T0.CDC_BASEAMNT base,
													T0.CDC_DISCOUNT Descuento,
													(T0.CDC_BASEAMNT - T0.CDC_DISCOUNT) subtotal,
													T0.CDC_TAXTOTAL Iva,
													T0.CDC_DOCTOTAL TotalDoc,
													T0.CDC_COMMENT Comentarios
												FROM DCDC t0
												INNER JOIN CDC1 T1 ON t0.CDC_docentry = t1.DC1_docentry
												LEFT JOIN DMSN T2 ON t0.CDC_cardcode = t2.dms_card_code
												LEFT JOIN DMSD T3 ON T0.CDC_ADRESS = CAST(T3.DMD_ID AS VARCHAR) AND t3.dmd_ppal = 1
												LEFT JOIN DMSC T4 ON T0.CDC_CONTACID = CAST(T4.DMC_ID AS VARCHAR)
												LEFT JOIN DMEV T5 ON T0.CDC_SLPCODE = T5.MEV_ID
												LEFT JOIN PGDN T6 ON T0.CDC_DOCTYPE = T6.PGS_ID_DOC_TYPE AND T0.CDC_SERIES = T6.PGS_ID
												LEFT JOIN PGEC T7 ON T0.CDC_CURRENCY = T7.PGM_SYMBOL
												WHERE T0.CDC_DOCENTRY = :CDC_DOCENTRY AND t0.business = :business";

				$contenidoDC = $this->pedeo->queryTable($sqlcotizacion,array(
          ':CDC_DOCENTRY' => $Data['CDC_DOCENTRY'],
          ':business' => $Data['business']
        ));

				if(!isset($contenidoDC[0])){
						$respuesta = array(
							 'error' => true,
							 'data'  => $contenidoDC,
							 'mensaje' =>'no se encontro el documento'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}
				//
				$detalleSerial = "";
				$serialAct = "";
				$detalleS = "";
				$aver = 0;
				$sqlSerial = "SELECT msn_itemcode,msn_whscode,msn_sn,msn_quantity FROM tmsn WHERE business = :business AND msn_basetype = :msn_basetype AND msn_baseentry = :msn_baseentry  ORDER BY msn_itemcode ASC";
				$tablasSerial = "";

				$resSerial = $this->pedeo->queryTable($sqlSerial, array(
					':business' 	 => $Data['business'],
					':msn_basetype'  => 14,
					':msn_baseentry' => $Data['CDC_DOCENTRY']
				));

				if ( isset($resSerial[0]) ) {
					
					foreach( $resSerial as $key => $element){
					
						if ($serialAct == "" ) {
							
							$serialAct = $element['msn_itemcode'];

							$detalleS = '<td>'.$element['msn_whscode'].'</td>
											<td>'.$element['msn_sn'].'</td>
											<td>'.$element['msn_quantity'].'</td>';

							$detalleSerial.= '<tr>'.$detalleS.'</tr>';				
										
						} else {


							if ( $serialAct == $element['msn_itemcode'] ){

								$detalleS = '<td>'.$element['msn_whscode'].'</td>
											<td>'.$element['msn_sn'].'</td>
											<td>'.$element['msn_quantity'].'</td>';

								$detalleSerial.= '<tr>'.$detalleS.'</tr>';

								$aver = 1;

							}else{
						
								$tablasSerial.= '<table  width="100%"><tr><th class="fondo">CODIGO ITEM '.$serialAct.'</th></tr></table>';
								$tablasSerial.= '<table class="borde" width="100%"><tr><th  style="text-align: center;">ALMACEN</th><th  style="text-align: center;">SERIAL</th><th  style="text-align: center;">CANTIDAD</th></tr>'.$detalleSerial.'</table>';

								$detalleS = "";

								$detalleSerial = "";

								$serialAct = $element['msn_itemcode'];
							}

						} 

					}

					if ($aver == 1 && $tablasSerial == ""){
						$tablasSerial.= '<table width="100%"><tr><th class="fondo">CODIGO ITEM '.$serialAct.'</th></tr></table>';
						$tablasSerial.= '<table class="borde" width="100%"><tr><th  style="text-align: center;">ALMACEN</th><th  style="text-align: center;">SERIAL</th><th  style="text-align: center;">CANTIDAD</th></tr>'.$detalleSerial.'</table>';
					}
				}

				$totaldetalle = '';
				foreach ($contenidoDC as $key => $value) {
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
                <p>DEVOLUCION DE COMPRA</p>
                <p class="fondo">'.$contenidoDC[0]['numerodocumento'].'</p>

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
          	<p>'.$contenidoDC[0]['cliente'].'</p>
          </th>
          <th>
            <p class="fondo">FECHA DE EXPEDICIÓN </p>
            <p>'.$contenidoDC[0]['fechadocumento'].'</p>
          </th>
        </tr>
        <tr>
          <th>
            <p class="fondo">DIRECCIÓN:</p>
          </th>
          <th style="text-align: left;">
            <p>'.$contenidoDC[0]['direccion'].'</p>
          </th>

        </tr>
        <tr>
          <th>
            <p class="fondo">TELÉFONO:</p>
          </th>
          <th style="text-align: left;">
            <p>
            	<span>'.$contenidoDC[0]['telefono'].'</span>
                <span class ="fondo">CC</span>
                <span>683012</span>
            </p>
          </th>
          <th>
            <p class="fondo">FECHA DE VENCIMIENTO </p>
            <p>'.$contenidoDC[0]['fechavendocumento'].'</p>
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
            <td style="text-align: right;">Base Documento: <span>'.$contenidoDC[0]['monedadocumento']." ".number_format($contenidoDC[0]['base'], $DECI_MALES, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;">Descuento: <span>'.$contenidoDC[0]['monedadocumento']." ".number_format($contenidoDC[0]['descuento'], $DECI_MALES, ',', '.').'</span></p></td>
        </tr>
				<tr>
            <td style="text-align: right;">Sub Total: <span>'.$contenidoDC[0]['monedadocumento']." ".number_format($contenidoDC[0]['subtotal'], $DECI_MALES, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;">Impuestos: <span>'.$contenidoDC[0]['monedadocumento']." ".number_format($contenidoDC[0]['iva'], $DECI_MALES, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;">Total: <span>'.$contenidoDC[0]['monedadocumento']." ".number_format($contenidoDC[0]['totaldoc'], $DECI_MALES, ',', '.').'</span></p></td>
        </tr>

        </table>


        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th style="text-align: left;" class="fondo">
                    <p>'.$formatter->toWords($contenidoDC[0]['totaldoc'],2)." ".$contenidoDC[0]['nombremoneda'].'</p>
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

        if ($aver){
          $mpdf->AddPage();
          $mpdf->WriteHTML($tablasSerial, \Mpdf\HTMLParserMode::HTML_BODY);
        }

				$filename = 'Doc.pdf';
        $mpdf->Output('Doc.pdf', 'D');

				header('Content-type: application/force-download');
				header('Content-Disposition: attachment; filename='.$filename);


	}




}
