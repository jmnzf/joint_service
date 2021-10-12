<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class CmpImpRet extENDs REST_Controller {

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


	public function CmpImp_post(){

        $Data = $this->post();
				$Data = $Data['CFC_DOCENTRY'];
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

				$sqlCmpImp = "SELECT
                            	t0.cfc_cardcode rif,
                            	TRIM(t0.cfc_cardname) beneficiario,
                            	'nombreretencion' concepto,
                            	CONCAT(CASE
                            		WHEN TO_CHAR(t0.cfc_docdate,'MM') = '1' THEN 'Enero'
                            		WHEN TO_CHAR(t0.cfc_docdate,'MM') = '2' THEN 'Febrero'
                            		WHEN TO_CHAR(t0.cfc_docdate,'MM') = '3' THEN 'Marzo'
                            		WHEN TO_CHAR(t0.cfc_docdate,'MM') = '4' THEN 'Abril'
                            		WHEN TO_CHAR(t0.cfc_docdate,'MM') = '5' THEN 'Mayo'
                            		WHEN TO_CHAR(t0.cfc_docdate,'MM') = '6' THEN 'Junio'
                            		WHEN TO_CHAR(t0.cfc_docdate,'MM') = '7' THEN 'Julio'
                            		WHEN TO_CHAR(t0.cfc_docdate,'MM') = '8' THEN 'Agosto'
                            		WHEN TO_CHAR(t0.cfc_docdate,'MM') = '9' THEN 'Septiembre'
                            		WHEN TO_CHAR(t0.cfc_docdate,'MM') = '10' THEN 'Octubre'
                            		WHEN TO_CHAR(t0.cfc_docdate,'MM') = '11' THEN 'Noviembre'
                            		WHEN TO_CHAR(t0.cfc_docdate,'MM') = '12' THEN 'Diciembre'
                            	END,'/',EXTRACT(YEAR FROM t0.cfc_docdate)) mes_año,
                            	t0.cfc_docnum docnum,
                            	CASE
                            		WHEN (t0.cfc_doctotal - t0.cfc_paytoday) = 0
                            			THEN t0.cfc_paytoday
                            			ELSE t0.cfc_doctotal
                            	END doctotal,
                            	0 base,--'valortotal-valorretencion' base,
                            	0 porc_ret,--'%ret' porc_ret,
                            	0 vlr_ret--'valorretencion' vlr_ret
                            FROM dcfc t0
                            WHERE t0.cfc_docentry = :CFC_DOCENTRY";

				$contenidoCmpImp = $this->pedeo->queryTable($sqlCmpImp,array(':CFC_DOCENTRY'=>$Data));
         // print_r($contenidoCmpImp);exit();die();
				if(!isset($contenidoCmpImp[0])){
						$respuesta = array(
							 'error' => true,
							 'data'  => $contenidoCmpImp,
							 'mensaje' =>'No tiene informacion de impuesto'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
						// var_dump($contenidoCmpImp);exit();die();
						return;
				}


				$totaldetalle = '';
				foreach ($contenidoCmpImp as $key => $value) {
					// code...
					$detalle = '<td class="centro">'.$value['rif'].'</td>
											<td class="centro">'.$value['beneficiario'].'</td>
                      <td class="centro">'.$value['concepto'].'</td>
                      <td class="centro">'.$value['mes_año'].'</td>
                      <td class="centro">'.$value['docnum'].'</td>
											<td class="centro">'.number_format($value['doctotal'], 2, ',', '.').'</td>
											<td class="centro">'.number_format($value['base'], 2, ',', '.').'</td>
											<td class="centro">'.$value['porc_ret'].'</td>
                      <td class="centro">'.number_format($value['vlr_ret'], 2, ',', '.').'</td>';
				 $totaldetalle = $totaldetalle.'<tr>'.$detalle.'</tr>';
				 // $totalfactura = ($totalfactura + $value['totalfactura']);
				}


        $header = '
        <table width="100%">
        <tr>
            <th style="text-align: left;"><img src="/var/www/html/'.$company[0]['company'].'/'.$empresa[0]['pge_logo'].'" width ="100" height ="40"></img></th>
            <th style="text-align: center; margin-left: 600px;">
                <p>COMPROBANTE DE RETENCIONES</p>

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
					 <p class="fondo" >NOMBRE O RAZÓN SOCIAL:</p>
				 </th>
				 <th style="text-align: left;">
					 <p>'.$empresa[0]['pge_name_soc'].'</p>
				 </th>
				</tr>
				<tr>
				 <th>
					 <p class="fondo" >N°. R.I.F:</p>
	 			 <th style="text-align: left;">
					 <p>'.$empresa[0]['pge_id_type'].'</p>
	 			 </th>
			 	</tr>
        <tr>
         <th >
           <p class="fondo" >TIPO DE AGENTE O DE RETENCION:</p>
         <th style="text-align: left;">
           <p style="text-align: left;"></p>
         </th>
        </tr>
        <tr>
         <th>
           <p class="fondo" >DIRECCION:</p>
         <th style="text-align: left;">
           <p style="text-align: left;">'.$empresa[0]['pge_add_soc'].'</p>
         </th>
        </tr>
        <tr>
         <th >
           <p class="fondo" >Fecha de Ejercicio:</p>
         <th style="text-align: left;">
           <p style="text-align: left;">'.date('d/m/y').'</p>
         </th>
        </tr>


			 </table>

        <br>

        <table class="borde" style="width:100%">
        <tr>
          <th class="fondo">RIF</th>
          <th class="fondo">BENEFICIARIO</th>
					<th class="fondo">CONCEPTO</th>
          <th class="fondo">MES / AÑO</th>
          <th class="fondo">N° FACTURA</th>
					<th class="fondo">VLR P/A</th>
          <th class="fondo">VLR DE RET</th>
          <th class="fondo">%</th>
          <th class="fondo">IMPUESTO RETENIDO</th>
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
