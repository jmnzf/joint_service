<?php
defined('BASEPATH') OR exit('No direct script access allowed');
use Luecano\NumeroALetras\NumeroALetras;

class DocumentCash {

    private $ci;
    private $pdo;

	public function __construct(){

        header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
        header("Access-Control-Allow-Origin: *");

        $this->ci =& get_instance();
        $this->ci->load->database();
        $this->pdo = $this->ci->load->database('pdo', true)->conn_id;
        $this->ci->load->library('pedeo', [$this->pdo]);
		$this->ci->load->library('generic');
		$this->ci->load->library('DateFormat');

	}

	public function formatCash($Data)
    {

		$DECI_MALES =  $this->ci->generic->getDecimals();

        $formatter = new NumeroALetras();

        //'setAutoTopMargin' => 'stretch','setAutoBottomMargin' => 'stretch',

        $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8',
            'format' => [58,210],
            'default_font_size' => 0,
            'margin_left' => 2,
            'margin_right' => 4,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'margin_header' => 2,
            'margin_footer' => 2,
            'default_font' => 'arial'
        ]);
        

        //RUTA DE CARPETA EMPRESA
        $company = $this->ci->pedeo->queryTable("SELECT main_folder company FROM PARAMS",array());

        if(!isset($company[0])){
                $respuesta = array(
                        'error' => true,
                        'data'  => $company,
                        'mensaje' =>'no esta registrada la ruta de la empresa'
                );

                return $respuesta;
        }

				//INFORMACION DE LA EMPRESA

				$empresa = $this->ci->pedeo->queryTable("SELECT pge_id, pge_name_soc, pge_small_name, pge_add_soc, pge_state_soc, pge_city_soc,
				pge_cou_soc, pge_id_soc AS pge_id_type , pge_web_site, pge_logo,
				CONCAT(pge_phone1,' ',pge_phone2,' ',pge_cel) AS pge_phone1, pge_branch, pge_mail,
				pge_curr_first, pge_curr_sys, pge_cou_bank, pge_bank_def,pge_bank_acct, pge_acc_type
				FROM pgem WHERE pge_id = :pge_id", array(':pge_id' => $Data['business']));

				if(!isset($empresa[0])){
						$respuesta = array(
		           'error' => true,
		           'data'  => $empresa,
		           'mensaje' =>'no esta registrada la información de la empresa'
		        );

				return $respuesta;
				}

				$sql = "SELECT distinct 
							tt.bco_date as fechai,
							tt.bco_time as timei,
							t2.bcc_description ,
							t.*
						from tbco t 
						inner join tbcc t2 on t.bco_boxid = t2.bcc_id 
						inner join (
						select a.bco_boxid , a.bco_date,a.bco_time from tbco a where a.bco_status = 1
						) as tt on t.bco_boxid = tt.bco_boxid and t.bco_date = tt.bco_date
						where t.bco_status = 0 and t.bco_date ='2023-10-20' and t.bco_boxid = 1;";
				// print_r($sqlcotizacion);exit;
				$resSql = $this->ci->pedeo->queryTable($sql,array());
                // print_r($resSql);exit;

				if(!isset($resSql[0])){
						$respuesta = array(
							 'error' => true,
							 'data'  => $empresa,
							 'mensaje' =>'no se encontro el documento'
						);

						return $respuesta;
				}

        $header = '
        <table width="100%">
        <tr>
            <th>
				<p>'.$empresa[0]['pge_name_soc'].'</p>
                <p>'.$resSql[0]['bcc_description'].'</p>
				<br>
            </th>
        </tr>
        </table>';

        $footer = '
        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th style="text-align: center;">

                </th>
            </tr>
        </table>
        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th class="" width="33%">Factura generada por JOINTERP Para LA RAZON <br> Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
            </tr>
        </table>';


        $html = '
        <br>
        <br>
		<h5 style="text-align: center;">CIERRE DE CAJA</h5>
            <table width="100%">
                <tr class="">
                    <th>CAJA</th>
					<th>CAJERO.</th>
                    <th>INICIO</th>
                    <th>FIN</th>
                </tr>
				<tr>
					<td>'.$resSql[0]['bco_id'].'</td>
					<td>'.$resSql[0]['bcc_description'].'</td>
					<td>'.$resSql[0]['fechai'].'<br><spam>'.$resSql[0]['timei'].'</spam></td>
					<td>'.$resSql[0]['bco_date'].'<br><spam>'.$resSql[0]['bco_time'].'</spam></td>
				</tr>
            </table>
			<h5>RESUMEN DE MEDIOS DE PAGO</h5>
			<table width="100%">
				<tr>
					<th>EF</th>
					<th>BS '.$resSql[0]['bco_total'].'</th>
				</tr>
			</table>
			<h5 style="text-align: center;">RESUMEN DE CAJA</h5>
			<table width="100%">
				<tr>
					<!-- Columna Izquierda DATOS DEL CLIENTE-->
					<td style="width: 50%; heigth="100%"; vertical-align: top;">
						<table style="width:100%">
							<tr>
								<td style="text-align: left;">TOTAL CONSIG:</td>
							</tr>
							<tr>
								<td style="text-align: left;">TOTAL DEV. CONSIG:</td>
							</tr>
							<tr>
								<td style="text-align: left;">TOTAL CIERRE:</td>
							</tr>
							
						</table>
					</td>
					<!-- Columna dERECHA DATOS DEL CONTACTO-->
					<td style="width: 40%; vertical-align: top;">
						<table style="width:100%">
							<tr>
								<td style="text-align: right;">BS '.number_format($resSql[0]['bco_amount'], $DECI_MALES, ',', '.').'</td>
							</tr>
							<tr>
								<td style="text-align: right;">BS '.number_format($resSql[0]['bco_total'] - $resSql[0]['bco_amount'], $DECI_MALES, ',', '.').'</td>
							</tr>
							<tr>
								<td style="text-align: right;">BS '.number_format($resSql[0]['bco_total'], $DECI_MALES, ',', '.').'</td>
							</tr>
							
						</table>
					</td>
				</tr>
			</table>
        ';
				// print_r($html);exit();die(); 
        $stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');

        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);

        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html);
		
        $mpdf->Output('Doc.pdf', 'D');

		header('Content-type: application/force-download');
		header('Content-Disposition: attachment; filename='.$filename);


	}
}