<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class EstadoCarteraPro extENDs REST_Controller {

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


	public function EstadoCarteraPro_post(){

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

		$empresa = $this->pedeo->queryTable("SELECT pge_id, pge_name_soc, pge_small_name, pge_add_soc, pge_state_soc, pge_city_soc,pge_cou_soc, CONCAT(pge_id_type,' ',pge_id_soc) AS pge_id_type , pge_web_site, pge_logo, CONCAT(pge_phone1,' ',pge_phone2,' ',pge_cel) AS pge_phone1, pge_branch, pge_mail, pge_curr_first, pge_curr_sys, pge_cou_bank, pge_bank_def,pge_bank_acct, pge_acc_type FROM pgem", array());

		if(!isset($empresa[0])){
			$respuesta = array(
	           'error' => true,
	           'data'  => $empresa,
	           'mensaje' =>'no esta registrada la información de la empresa'
	        );

          	$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	        return;
		}

		$sqlestadocuenta = "SELECT distinct
												dmdt.mdt_docname,
												mac1.ac1_font_key,
												mac1.ac1_legal_num as CodigoCliente,
												dmsn.dms_card_name NombreCliente,
												mac1.ac1_account as cuenta,
												dcfc.cfc_currency monedadocumento,
												'".$Data['fecha']."' fechacorte,
												'".$Data['fecha']."' - cfc_duedate dias,
												dcfc.cfc_comment,
												dcfc.cfc_currency,
												mac1.ac1_font_key as cfc_docentry,
												dcfc.cfc_docnum,
												dcfc.cfc_docdate as FechaDocumento,
												dcfc.cfc_duedate as FechaVencimiento,
												cfc_docnum as NumeroDocumento,
												mac1.ac1_font_type as numtype,
												mdt_docname as tipo,
												case
												when mac1.ac1_font_type = 15 then mac1.ac1_debit
												else mac1.ac1_credit
												end as totalfactura,
												(mac1.ac1_debit) - (mac1.ac1_credit) as saldo,
												'' retencion,
												tasa.tsa_value as tasa_dia,
												CASE
													WHEN ( '".$Data['fecha']."' - dcfc.cfc_duedate) >=0 and ( '".$Data['fecha']."' - dcfc.cfc_duedate) <=30
														then (mac1.ac1_debit) - (mac1.ac1_credit)
														ELSE 0
												END uno_treinta,
												CASE
													WHEN ( '".$Data['fecha']."' - dcfc.cfc_duedate) >=31 and ( '".$Data['fecha']."' - dcfc.cfc_duedate) <=60
														then (mac1.ac1_debit) - (mac1.ac1_credit)
														ELSE 0
												END treinta_uno_secenta,
												CASE
													WHEN ( '".$Data['fecha']."' - dcfc.cfc_duedate) >=61 and ( '".$Data['fecha']."' - dcfc.cfc_duedate) <=90
														then (mac1.ac1_debit) - (mac1.ac1_credit)
														ELSE 0
												END secenta_uno_noventa,
												CASE
													WHEN ( '".$Data['fecha']."' - dcfc.cfc_duedate) >=91
														then (mac1.ac1_debit) - (mac1.ac1_credit)
												ELSE 0
												END mayor_noventa


												from mac1
												inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
												inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
												inner join dcfc on dcfc.cfc_doctype = mac1.ac1_font_type and dcfc.cfc_docentry = mac1.ac1_font_key
												inner join tasa on dcfc.cfc_currency = tasa.tsa_curro and dcfc.cfc_docdate = tasa.tsa_date
												inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
												where ABS((mac1.ac1_debit) - (mac1.ac1_credit) ) > 0 and dmsn.dms_card_type = '2'


												union all
												select distinct
												dmdt.mdt_docname,
												mac1.ac1_font_key,
												mac1.ac1_legal_num as CodigoCliente,
												dmsn.dms_card_name NombreCliente,
												mac1.ac1_account as cuenta,
												gbpe.bpe_currency monedadocumento,
												'".$Data['fecha']."' fechacorte,
												'".$Data['fecha']."' - gbpe.bpe_docdate as dias,
												gbpe.bpe_comments as bpe_comment,
												gbpe.bpe_currency,
												mac1.ac1_font_key as cfc_docentry,
												gbpe.bpe_docnum,
												gbpe.bpe_docdate as FechaDocumento,
												gbpe.bpe_docdate as FechaVencimiento,
												gbpe.bpe_docnum as NumeroDocumento,
												mac1.ac1_font_type as numtype,
												mdt_docname as tipo,
												case
												when mac1.ac1_font_type = 15 then mac1.ac1_debit
												else mac1.ac1_debit
												end as totalfactura,
												(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) as saldo,
												'' retencion,
												tasa.tsa_value as tasa_dia,
												CASE
													WHEN ( '".$Data['fecha']."' - gbpe.bpe_docdate) >=0 and ( '".$Data['fecha']."' - gbpe.bpe_docdate) <=30
														then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
														ELSE 0
												END uno_treinta,
												CASE
													WHEN ( '".$Data['fecha']."' - gbpe.bpe_docdate) >=31 and ( '".$Data['fecha']."' - gbpe.bpe_docdate) <=60
														then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
														ELSE 0
												END treinta_uno_secenta,
												CASE
													WHEN ( '".$Data['fecha']."' - gbpe.bpe_docdate) >=61 and ( '".$Data['fecha']."' - gbpe.bpe_docdate) <=90
														then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
														ELSE 0
												END secenta_uno_noventa,
												CASE
													WHEN ( '".$Data['fecha']."' - gbpe.bpe_docdate) >=91
														then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
												ELSE 0
												END mayor_noventa

												from mac1
												inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
												inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
												inner join gbpe on gbpe.bpe_doctype = mac1.ac1_font_type and gbpe.bpe_docentry = mac1.ac1_font_key
												inner join tasa on gbpe.bpe_currency = tasa.tsa_curro and gbpe.bpe_docdate = tasa.tsa_date
												inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
												where ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0 and dmsn.dms_card_type = '2'

												union all
												select distinct
												dmdt.mdt_docname,
												mac1.ac1_font_key,
												mac1.ac1_legal_num as CodigoCliente,
												dmsn.dms_card_name NombreCliente,
												mac1.ac1_account as cuenta,
												dcnc.cnc_currency monedadocumento,
												'".$Data['fecha']."' fechacorte,
												'".$Data['fecha']."' - dcnc.cnc_docdate as dias,
												dcnc.cnc_comment as bpe_comment,
												dcnc.cnc_currency,
												mac1.ac1_font_key as cfc_docentry,
												dcnc.cnc_docnum,
												dcnc.cnc_docdate as FechaDocumento,
												dcnc.cnc_duedate as FechaVencimiento,
												dcnc.cnc_docnum as NumeroDocumento,
												mac1.ac1_font_type as numtype,
												mdt_docname as tipo,
												case
												when mac1.ac1_font_type = 15 then mac1.ac1_debit
												else mac1.ac1_debit
												end as totalfactura,
												(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) as saldo,
												'' retencion,
												tasa.tsa_value as tasa_dia,
												CASE
													WHEN ( '".$Data['fecha']."' - dcnc.cnc_duedate) >=0 and ( '".$Data['fecha']."' - dcnc.cnc_duedate) <=30
														then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
														ELSE 0
												END uno_treinta,
												CASE
													WHEN ( '".$Data['fecha']."' - dcnc.cnc_duedate) >=31 and ( '".$Data['fecha']."' - dcnc.cnc_duedate) <=60
														then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
														ELSE 0
												END treinta_uno_secenta,
												CASE
													WHEN ( '".$Data['fecha']."' - dcnc.cnc_duedate) >=61 and ( '".$Data['fecha']."' - dcnc.cnc_duedate) <=90
														then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
														ELSE 0
												END secenta_uno_noventa,
												CASE
													WHEN ( '".$Data['fecha']."' - dcnc.cnc_duedate) >=91
														then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
												ELSE 0
												END mayor_noventa

												from mac1
												inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
												inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
												inner join dcnc on dcnc.cnc_doctype = mac1.ac1_font_type and dcnc.cnc_docentry = mac1.ac1_font_key
												inner join tasa on dcnc.cnc_currency = tasa.tsa_curro and dcnc.cnc_docdate = tasa.tsa_date
												inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
												where ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0 and dmsn.dms_card_type = '2'

												union all
												select distinct
												dmdt.mdt_docname,
												mac1.ac1_font_key,
												mac1.ac1_legal_num as CodigoCliente,
												dmsn.dms_card_name NombreCliente,
												mac1.ac1_account as cuenta,
												dcnd.cnd_currency monedadocumento,
												'".$Data['fecha']."' fechacorte,
												'".$Data['fecha']."' - dcnd.cnd_docdate as dias,
												dcnd.cnd_comment as bpe_comment,
												dcnd.cnd_currency,
												mac1.ac1_font_key as cfc_docentry,
												dcnd.cnd_docnum,
												dcnd.cnd_docdate as FechaDocumento,
												dcnd.cnd_duedate as FechaVencimiento,
												dcnd.cnd_docnum as NumeroDocumento,
												mac1.ac1_font_type as numtype,
												mdt_docname as tipo,
												case
												when mac1.ac1_font_type = 5 then mac1.ac1_debit
												else mac1.ac1_credit
												end as totalfactura,
												(mac1.ac1_ven_credit) - (mac1.ac1_credit) as saldo,
												'' retencion,
												tasa.tsa_value as tasa_dia,
												CASE
													WHEN ( '".$Data['fecha']."' - dcnd.cnd_duedate) >=0 and ( '".$Data['fecha']."' - dcnd.cnd_duedate) <=30
														then (mac1.ac1_debit) - (mac1.ac1_credit)
														ELSE 0
												END uno_treinta,
												CASE
													WHEN ( '".$Data['fecha']."' - dcnd.cnd_duedate) >=31 and ( '".$Data['fecha']."' - dcnd.cnd_duedate) <=60
														then (mac1.ac1_debit) - (mac1.ac1_credit)
														ELSE 0
												END treinta_uno_secenta,
												CASE
													WHEN ( '".$Data['fecha']."' - dcnd.cnd_duedate) >=61 and ( '".$Data['fecha']."' - dcnd.cnd_duedate) <=90
														then (mac1.ac1_debit) - (mac1.ac1_credit)
														ELSE 0
												END secenta_uno_noventa,
												CASE
													WHEN ( '".$Data['fecha']."' - dcnd.cnd_duedate) >=91
														then (mac1.ac1_debit) - (mac1.ac1_credit)
												ELSE 0
												END mayor_noventa

												from mac1
												inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
												inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
												inner join dcnd on dcnd.cnd_doctype = mac1.ac1_font_type and dcnd.cnd_docentry = mac1.ac1_font_key
												inner join tasa on dcnd.cnd_currency = tasa.tsa_curro and dcnd.cnd_docdate = tasa.tsa_date
												inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
												where ABS((mac1.ac1_debit) - (mac1.ac1_credit) ) > 0 and dmsn.dms_card_type = '2'";

		$contenidoestadocuenta = $this->pedeo->queryTable($sqlestadocuenta,array());
// print_r($sqlestadocuenta);exit();die();
		if(!isset($contenidoestadocuenta[0])){
			$respuesta = array(
				 'error' => true,
				 'data'  => $contenidoestadocuenta,
				 'mensaje' =>'No tiene pagos realizados'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

        $cliente = "";
        $encabezado = "";
        $detalle = "";
        $totaldetalle = "";
        $cuerpo = "";
        $cabecera = "";
		$gCliente  = "";
		$hacer = true;

		$total_0_30 = 0;
		$total_30_60 = 0;
		$total_60_90 = 0;
		$total_mayor_90 = 0;
		$monedadocumento = '';

        foreach ($contenidoestadocuenta as $key => $value) {
			// print_r($value['codigocliente']);
			if( trim($cliente) == trim($value['codigocliente']) ){
					$hacer = false;
			}else{
				$cliente = $value['codigocliente'];

				$gCliente = '<th class=""><b>RIF:</b> '.$value['codigocliente'].'</th>
										 <th class=""><b>PROVEEDOR:</b> '.$value['nombrecliente'].'</th>
										 <th class=""><b>FECHA CORTE:</b> '.$value['fechacorte'].'</th>

										 ';
				$hacer = true;
			}

			$encabezado = '<table width="100%"><tr>'.$gCliente.'</tr></table>
			<table width="100%" style="vertical-align: bottom; font-family: serif;
					font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
					<tr>
							<th class="fondo">
									<p></p>
							</th>
					</tr>
			</table>';

			$totaldetalle = "";
			$detail_0_30 = 0;
			$detail_30_60 = 0;
			$detail_60_90 = 0;
			$detail_mayor_90 = 0;

			if($hacer){

				$cabecera = '
									<th class=""><b>Tipo Documento</b></th>
									<th class=""><b>Numero Documento</b></th>
									<th class=""><b>F. Documento</b></th>
									<th class=""><b>Total Documento</b></th>
									<th class=""><b>F. Ven Documento</b></th>
									<th class=""><b>F. Corte</b></th>
									<th class=""><b>Dias Vencidos</b></th>
									<th class=""><b>0-30</b></th>
									<th class=""><b>31-60</b></th>
									<th class=""><b>61-90</b></th>
									<th class=""><b>+90</b></th>';
				foreach ($contenidoestadocuenta as $key => $value1) {

					if( $cliente == $value1['codigocliente']){
						//
						$monedadocumento = $value1['monedadocumento'];


						$detalle = '
									<td class="centro">'.$value1['mdt_docname'].'</td>
									<td style="width: 15%;" class="centro">'.$value1['numerodocumento'].'</td>
									<td class="centro">'.$value1['fechadocumento'].'</td>
									<td class="centro">'.$value1['monedadocumento']." ".number_format($value1['totalfactura'], 2, ',', '.').'</td>
									<td class="centro">'.$value1['fechavencimiento'].'</td>
									<td class="centro">'.$value1['fechacorte'].'</td>
									<td class="centro">'.$value1['dias'].'</td>
									<td class="centro">'.$value1['monedadocumento']." ".number_format($value1['uno_treinta'], 2, ',', '.').'</td>
									<td class="centro">'.$value1['monedadocumento']." ".number_format($value1['treinta_uno_secenta'], 2, ',', '.').'</td>
									<td class="centro">'.$value1['monedadocumento']." ".number_format($value1['secenta_uno_noventa'], 2, ',', '.').'</td>
									<td class="centro">'.$value1['monedadocumento']." ".number_format($value1['mayor_noventa'], 2, ',', '.').'</td>';



						$totaldetalle .= '<tr>'.$detalle.'</tr>';

						$detail_0_30 =  $detail_0_30 + ($value1['uno_treinta']);
 					   	$detail_30_60 =  $detail_30_60 + ($value1['treinta_uno_secenta']);
 						$detail_60_90 =  $detail_60_90 + ($value1['secenta_uno_noventa']);
 						$detail_mayor_90 =  $detail_mayor_90 + ($value1['mayor_noventa']);
					}
				}

				$total_0_30 =  $total_0_30 + $detail_0_30;
			   	$total_30_60 =  $total_30_60 + $detail_30_60;
				$total_60_90 =  $total_60_90 + $detail_60_90;
				$total_mayor_90 =  $total_mayor_90 + $detail_mayor_90;

				$detail_total = '
							<tr>
							<th>&nbsp;</th>
							<th>&nbsp;</th>
							<th>&nbsp;</th>
							<th>&nbsp;</th>
							<th>&nbsp;</th>
							<th><b>Total</b></th>
							<th style="width: 10%;" class=" centro"><b>'.$monedadocumento.' '.number_format(($detail_0_30+$detail_30_60+$detail_60_90+$detail_mayor_90), 2, ',', '.').'</b></th>
							<th class=" centro"><b>'.$monedadocumento.' '.number_format($detail_0_30, 2, ',', '.').'</b></th>
							<th class=" centro"><b>'.$monedadocumento.' '.number_format($detail_30_60, 2, ',', '.').'</b></th>
							<th class=" centro"><b>'.$monedadocumento.' '.number_format($detail_60_90, 2, ',', '.').'</b></th>
							<th class=" centro"><b>'.$monedadocumento.' '.number_format($detail_mayor_90, 2, ',', '.').'</b></th>
							</tr>';

				$cuerpo .= $encabezado."<table width='100%'><tr>".$cabecera."</tr>".$totaldetalle.$detail_total."</table><br><br>";
			}
        }

        $cuerpo .= '
        			<table width="100%">
						<tr>
							<th style="width: 10%;">&nbsp;</th>
							<th style="width: 10%;">&nbsp;</th>
							<th style="width: 10%;">&nbsp;</th>
							<th style="width: 24%;">&nbsp;</th>
							<th><b>Total</th>
							<th class=" centro"><b>'.$monedadocumento.' '.number_format(($total_0_30+$total_30_60+$total_60_90+$total_mayor_90), 2, ',', '.').'</b></th>
							<th class=" centro"><b>'.$monedadocumento.' '.number_format($total_0_30, 2, ',', '.').'</b></th>
							<th class=" centro"><b>'.$monedadocumento.' '.number_format($total_30_60, 2, ',', '.').'</b></th>
							<th class=" centro"><b>'.$monedadocumento.' '.number_format($total_60_90, 2, ',', '.').'</b></th>
							<th class=" centro"><b>'.$monedadocumento.' '.number_format($total_mayor_90, 2, ',', '.').'</b></th>
							</tr>
					</table>';

        $header = '
        <table width="100%">
	        <tr>
	            <th style="text-align: left;"><img src="/var/www/html/'.$company[0]['company'].'/'.$empresa[0]['pge_logo'].'" width ="100" height ="40"></img></th>
	            <th style="text-align: center; margin-left: 600px;">
	                <p><b>INFORME ESTADO DE CUENTA PROVEEDORES</b></p>
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
		            <th class="" width="33%">Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
		        </tr>
		    </table>';

		$html = ''.$cuerpo.'';

        $stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');

        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);


        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);


        $mpdf->Output('EstadoCartera_'.$contenidoestadocuenta[0]['fechacorte'].'.pdf', 'D');

		header('Content-type: application/force-download');
		header('Content-Disposition: attachment; filename='.$filename);
	}

}
