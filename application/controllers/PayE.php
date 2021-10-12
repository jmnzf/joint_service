<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class PayE extends REST_Controller {

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


	public function PayE_post(){

        $Data = $this->post();
				$Data = $Data['BPE_DOCENTRY'];

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

				$sqlpagoE = "SELECT
                            	t0.bpe_docentry id,
                            	t0.bpe_cardcode nit,
                            	trim(t0.bpe_cardname) proveedor,
                            	t0.bpe_address direccion,
                              0 telefono,
                            	t0.bpe_perscontact contacto,
                            	t0.bpe_series serie,
                            	t0.bpe_docnum numero_doc,
                            	t0.bpe_docdate fecha_doc,
                            	cast(t0.bpe_createat as date) fecha_cre,
                            	t0.bpe_comments referencia,
                            	t0.bpe_memo comentarios,
                            	t0.bpe_datetransfer fecha_transf,
                            	t0.bpe_doctotal total_doc,
                            	t0.bpe_currency moneda,
                            	t1.pe1_doctype origen,
                              case
                            		when t1.pe1_doctype = 15 then 'FC'
                            		else ''
                            	end tipo,
                            	case
                            		when t1.pe1_doctype = 15 then (select aa.cfc_docnum from dcfc aa where aa.cfc_docentry = t1.pe1_docentry)
                            		else 0
                            	end docnum,
                            	t1.pe1_docdate fecha_origen,
                            	t1.pe1_docduedate fecha_ven,
                            	t1.pe1_daysbackw dias_ven,
                            	t1.pe1_vlrtotal total_doc_origen,
                            	t1.pe1_vlrpaid total_apli,
                              (t1.pe1_vlrtotal - t1.pe1_vlrpaid) saldo,
                            	t1.pe1_comments coments_origen

                            from gbpe t0
                            left join bpe1 t1 on t0.bpe_docentry = t1.pe1_docnum
                            where t0.bpe_docentry = :BPE_DOCENTRY";

				$contenidoPAYE = $this->pedeo->queryTable($sqlpagoE,array(':BPE_DOCENTRY'=>$Data));

				if(!isset($contenidoPAYE[0])){
						$respuesta = array(
							 'error' => true,
							 'data'  => $contenidoPAYE,
							 'mensaje' =>'no se encontro el documento'
						);

						$this->response($respuesta, REST_Controller);

						return;
				}


				$totaldetalle = '';
				foreach ($contenidoPAYE as $key => $value) {
					// code...
					$detalle = '<td>'.$value['tipo'].'</td>
                      <td>'.$value['docnum'].'</td>
											<td>'.$value['fecha_origen'].'</td>
											<td>'.$value['fecha_ven'].'</td>
											<td>'.$value['dias_ven'].'</td>
											<td>$'.number_format($value['total_doc_origen'], 2, ',', '.').'</td>
											<td>$'.number_format($value['total_apli'], 2, ',', '.').'</td>
                      <td>$'.number_format($value['saldo'], 2, ',', '.').'</td>';
				 $totaldetalle = $totaldetalle.'<tr>'.$detalle.'</tr>';
				}

// print_r($contenidoPAYE);exit();die();
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
                <p>PAGO EFECTUADO</p>
                <p class="fondo">'.$contenidoPAYE[0]['numero_doc'].'</p>

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
          	<p>'.$contenidoPAYE[0]['proveedor'].'</p>
          </th>
          <th>
            <p class="fondo">FECHA DE DOCUMENTO </p>
            <p>'.$contenidoPAYE[0]['fecha_doc'].'</p>
          </th>
        </tr>
        <tr>
          <th>
            <p class="fondo">DIRECCIÓN:</p>
          </th>
          <th style="text-align: left;">
            <p>'.$contenidoPAYE[0]['direccion'].'</p>
          </th>

        </tr>
        <tr>
          <th>
            <p class="fondo">TELÉFONO:</p>
          </th>
          <th style="text-align: left;">
            <p>
            	<span>'.$contenidoPAYE[0]['telefono'].'</span>
                <span class ="fondo">RIF:</span>
                <span>'.$contenidoPAYE[0]['nit'].'</span>
            </p>
          </th>
          <th>
            <p class="fondo">FECHA DE CREACION </p>
            <p>'.$contenidoPAYE[0]['fecha_cre'].'</p>
          </th>
        </tr>
        </table>

        <br>

        <table class="borde" style="width:100%">
        <tr>
          <th class="fondo">TIPO</th>
          <th class="fondo">DOCUMENTO</th>
          <th class="fondo">FECHA DOCUMENTO</th>
          <th class="fondo">FECHA VENCIMIENTO</th>
          <th class="fondo">DIAS VENCIDOS</th>
          <th class="fondo">VALOR TOTAL</th>
          <th class="fondo">VALOR APLICADO</th>
          <th class="fondo">SALDO</th>
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
            <td style="text-align: left;">TOTAL PAGADO: <span>$'.number_format($contenidoPAYE[0]['total_doc'], 2, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: left;">REFERENCIA: <span>'.$contenidoPAYE[0]['referencia'].'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: left;">COMENTARIO APLICADO: <span>'.$contenidoPAYE[0]['comentarios'].'</span></span></p></td>
        </tr>
        <tr>
            <td style="text-align: left;">FECHA DE TRANSFERENCIA: <span>'.$contenidoPAYE[0]['fecha_transf'].'</span></p></td>
        </tr>

        </table>


        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th style="text-align: left;" class="fondo">
                    <p>'.$formatter->toWords($contenidoPAYE[0]['total_doc'],2)." ".$contenidoPAYE[0]['moneda'].'</p>
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
// print_r($html);exit();die();
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
