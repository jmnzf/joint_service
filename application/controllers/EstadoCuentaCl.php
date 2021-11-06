<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class EstadoCuentaCl extENDs REST_Controller {

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


	public function EstadoCuentaCl_post(){

        $Data = $this->post();
				// $Data = $Data['fecha'];
				$totalfactura = 0;

				$formatter = new NumeroALetras();




        $mpdf = new \Mpdf\Mpdf(['setAutoBottomMargin' => 'stretch','setAutoTopMargin' => 'stretch','orientation' => 'L']);

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
                        	t0.dvf_cardcode CodigoProveedor,
                        	t0.dvf_cardname NombreProveedor,
                        	t0.dvf_docnum NumeroDocumento,
                        	t0.dvf_docdate FechaDocumento,
                        	t0.dvf_duedate FechaVencimiento,
													t0.dvf_doctotal totalfactura,
                          T0.dvf_paytoday saldo,
													trim('COP' FROM t0.dvf_currency) MonedaDocumento,
                        	'".$Data['fecha']."' FechaCorte,
													('".$Data['fecha']."' - t0.dvf_duedate) dias,
                        	CASE
                        		WHEN ( '".$Data['fecha']."' - t0.dvf_duedate) >=0 and ( '".$Data['fecha']."' - t0.dvf_duedate) <=30
                        			then (t0.dvf_doctotal - COALESCE(t0.dvf_paytoday,0))
															ELSE 0
                        	END uno_treinta,
                        	CASE
                        		WHEN ( '".$Data['fecha']."' - t0.dvf_duedate) >=31 and ( '".$Data['fecha']."' - t0.dvf_duedate) <=60
                        			then (t0.dvf_doctotal - COALESCE(t0.dvf_paytoday,0))
															ELSE 0
                        	END treinta_uno_secenta,
                        	CASE
                        		WHEN ( '".$Data['fecha']."' - t0.dvf_duedate) >=61 and ( '".$Data['fecha']."' - t0.dvf_duedate) <=90
                        			then (t0.dvf_doctotal - COALESCE(t0.dvf_paytoday,0))
															ELSE 0
                        	END secenta_uno_noventa,
                        	CASE
                        		WHEN ( '".$Data['fecha']."' - t0.dvf_duedate) >=91
                        			then (t0.dvf_doctotal - COALESCE(t0.dvf_paytoday,0))
													ELSE 0
                        	END mayor_noventa

                        FROM dvfv t0
                        WHERE '".$Data['fecha']."' >= t0.dvf_duedate  and t0.dvf_cardcode = '".$Data['cardcode']."'
												ORDER BY NumeroDocumento";

				$contenidoestadocuenta = $this->pedeo->queryTable($sqlestadocuenta,array());
         // print_r($contenidoestadocuenta);exit();die();
				if(!isset($contenidoestadocuenta[0])){
						$respuesta = array(
							 'error' => true,
							 'data'  => $contenidoestadocuenta,
							 'mensaje' =>'No tiene pagos realizados'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
						var_dump($contenidoestadocuenta);exit();die();
						return;
				}


				$totaldetalle = '';
				foreach ($contenidoestadocuenta as $key => $value) {
					// code...
					$detalle = '<td class="centro">'.$value['numerodocumento'].'</td>
											<td class="centro">'.$value['fechadocumento'].'</td>
											<td class="centro">'.$value['monedadocumento']." ".number_format($value['totalfactura'], 2, ',', '.').'</td>
											<td class="centro">'.$value['fechavencimiento'].'</td>
											<td class="centro">'.$value['fechacorte'].'</td>
											<td class="centro">'.$value['dias'].'</td>
											<td class="centro">'.$value['monedadocumento']." ".number_format($value['uno_treinta'], 2, ',', '.').'</td>
											<td class="centro">'.$value['monedadocumento']." ".number_format($value['treinta_uno_secenta'], 2, ',', '.').'</td>
                      <td class="centro">'.$value['monedadocumento']." ".number_format($value['secenta_uno_noventa'], 2, ',', '.').'</td>
                      <td class="centro">'.$value['monedadocumento']." ".number_format($value['mayor_noventa'], 2, ',', '.').'</td>';
				 $totaldetalle = $totaldetalle.'<tr>'.$detalle.'</tr>';
				 $totalfactura = ($totalfactura + ($value['totalfactura'] - $value['saldo']));
				}


        $header = '
        <table width="100%">
        <tr>
            <th style="text-align: left;"><img src="/var/www/html/'.$company[0]['company'].'/'.$empresa[0]['pge_logo'].'" width ="100" height ="40"></img></th>
            <th style="text-align: center; margin-left: 600px;">
                <p>INFORME ESTADO DE CUENTA CLIENTE</p>

            </th>
						<th>
							&nbsp;
							&nbsp;
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
					 <p class="fondo" style="text-align: left;">RIF:</p>
				 </th>
				 <th style="text-align: left;">
					 <p>'.$contenidoestadocuenta[0]['codigoproveedor'].'</p>
				 </th>
				</tr>
				<tr>
				 <th >
					 <p class="fondo" >Nombre Cliente</p>
	 			 <th style="text-align: left;">

					 <p style="text-align: left;">'.$contenidoestadocuenta[0]['nombreproveedor'].'</p>

	 			 </th>
			 	</tr>
			 <tr>
				 <th>
					 <p class="fondo">Saldo</p>
				 </th>
				 <th style="text-align: left;">
					 <p>'.$value['monedadocumento']." ".number_format($totalfactura, 2, ',', '.').'</p>
				 </th>

			 </tr>

			 </table>

        <br>

        <table class="borde" style="width:100%">
        <tr>
          <th class="fondo">Numero Documento</th>
          <th class="fondo">Fecha Documento</th>
					<th class="fondo">Total Documento</th>
          <th class="fondo">Fecha Ven Documento</th>
          <th class="fondo">Fecha Corte</th>
					<th class="fondo">Dias Vencidos</th>
          <th class="fondo">0-30</th>
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
