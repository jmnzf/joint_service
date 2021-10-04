<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class EstadoCuentaPro extENDs REST_Controller {

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


	public function EstadoCuentaPro_post(){

        $Data = $this->post();
				$Data = $Data['fecha'];

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

				$sqlestadocuenta = "SELECT
                        	t0.cfc_cardcode CodigoProveedor,
                        	t0.cfc_cardname NombreProveedor,
                        	t0.cfc_docnum NumeroDocumento,
                        	t0.cfc_docdate FechaDocumento,
                        	t0.cfc_duedate FechaVencimiento,
                        	'2021-09-30' FechaCorte,
                        	CASE
                        		WHEN (t0.cfc_duedate - '2021-09-30') >=1 and (t0.cfc_duedate - '2021-09-30') <=30
                        			then t0.cfc_paytoday
                        	END uno_treinta,
                        	CASE
                        		WHEN (t0.cfc_duedate - '2021-09-30') >=31 and (t0.cfc_duedate - '2021-09-30') <=60
                        			then t0.cfc_paytoday
                        	END treinta_uno_secenta,
                        	CASE
                        		WHEN (t0.cfc_duedate - '2021-09-30') >=61 and (t0.cfc_duedate - '2021-09-30') <=90
                        			then t0.cfc_paytoday
                        	END secenta_uno_noventa,
                        	CASE
                        		WHEN (t0.cfc_duedate - '2021-09-30') >=91
                        			then t0.cfc_paytoday
                        	END mayor_noventa

                        FROM dcfc t0
                        WHERE t0.cfc_duedate <= '2021-09-30'";

				$contenidoestadocuenta = $this->pedeo->queryTable($sqlestadocuenta,array());
        // print_r($Data);exit();die();
				if(!isset($contenidoestadocuenta[0])){
						$respuesta = array(
							 'error' => true,
							 'data'  => $contenidoestadocuenta,
							 'mensaje' =>'no se encontro el documento'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}
				// print_r($contenidoestadocuenta);exit();die();

				$totaldetalle = '';
				foreach ($contenidoestadocuenta as $key => $value) {
					// code...
					$detalle = '<td>'.$value['codigoproveedor'].'</td>
											<td>'.$value['nombreproveedor'].'</td>
											<td>'.$value['numerodocumento'].'</td>
											<td>'.$value['fechadocumento'].'</td>
											<td>'.$value['fechavencimiento'].'</td>
											<td>'.$value['fechacorte'].'</td>
											<td>'.$value['uno_treinta'].'</td>
											<td>'.$value['treinta_uno_secenta'].'</td>
                      <td>'.$value['secenta_uno_noventa'].'</td>
                      <td>'.$value['mayor_noventa'].'</td>';
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
                <p>INFORME ESTADO DE CUENTA PROVEEDOR</p>
                <p class="fondo">'.$contenidoestadocuenta[0]['numerodocumento'].'</p>

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
          	<p>'.$contenidoestadocuenta[0]['NombreProveedor'].'</p>
          </th>
        </tr>
        </table>*/

        <br>

        <table class="borde" style="width:100%">
        <tr>
          <th class="fondo">Codigo RIF</th>
          <th class="fondo">Nombre Proveedor</th>
          <th class="fondo">Numero Documento</th>
          <th class="fondo">Fecha Documento</th>
          <th class="fondo">Fecha Ven Documento</th>
          <th class="fondo">Fecha Corte</th>
          <th class="fondo">1-30</th>
          <th class="fondo">31-60</th>
          <th class="fondo">61-90</th>
          <th class="fondo">+90</th>
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
        ';

        $stylesheet = file_get_contents(APPPATH.'/asset/vENDor/style.css');

        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);


        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);


        $mpdf->Output('Doc.pdf', 'D');

				header('Content-type: application/force-download');
				header('Content-Disposition: attachment; filename='.$filename);


	}




}
