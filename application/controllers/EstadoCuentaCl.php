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

				$sqlestadocuenta = "SELECT distinct
														dmdt.mdt_docname,
														mac1.ac1_font_key,
														mac1.ac1_legal_num as codigoproveedor,
														dmsn.dms_card_name nombreproveedor,
														mac1.ac1_account as cuenta,
														dvfv.dvf_currency monedadocumento,
														'".$Data['fecha']."' fechacorte,
														'".$Data['fecha']."' - dvf_duedate dias,
														dvfv.dvf_comment,
														dvfv.dvf_currency,
														mac1.ac1_font_key as dvf_docentry,
														dvfv.dvf_docnum,
														dvfv.dvf_docdate as FechaDocumento,
														dvfv.dvf_duedate as FechaVencimiento,
														dvf_docnum as NumeroDocumento,
														mac1.ac1_font_type as numtype,
														mdt_docname as tipo,
														case
														when mac1.ac1_font_type = 5 then mac1.ac1_debit
														else mac1.ac1_credit
														end as totalfactura,
														(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) as saldo,
														'' retencion,
														get_tax_currency(dvfv.dvf_currency,dvfv.dvf_docdate) as tasa_dia,
														CASE
															WHEN ( '".$Data['fecha']."' - dvfv.dvf_duedate) >=0 and ( '".$Data['fecha']."' - dvfv.dvf_duedate) <=30
																then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
																ELSE 0
														END uno_treinta,
														CASE
															WHEN ( '".$Data['fecha']."' - dvfv.dvf_duedate) >=31 and ( '".$Data['fecha']."' - dvfv.dvf_duedate) <=60
																then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
																ELSE 0
														END treinta_uno_secenta,
														CASE
															WHEN ( '".$Data['fecha']."' - dvfv.dvf_duedate) >=61 and ( '".$Data['fecha']."' - dvfv.dvf_duedate) <=90
																then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
																ELSE 0
														END secenta_uno_noventa,
														CASE
															WHEN ( '".$Data['fecha']."' - dvfv.dvf_duedate) >=91
																then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
														ELSE 0
														END mayor_noventa


														from mac1
														inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
														inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
														inner join dvfv on dvfv.dvf_doctype = mac1.ac1_font_type and dvfv.dvf_docentry = mac1.ac1_font_key
														inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
														where ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
														and mac1.ac1_legal_num = '".$Data['cardcode']."' and dmsn.dms_card_type = '1'


														union all
														select distinct
														dmdt.mdt_docname,
														mac1.ac1_font_key,
														mac1.ac1_legal_num as codigoproveedor,
														dmsn.dms_card_name nombreproveedor,
														mac1.ac1_account as cuenta,
														gbpr.bpr_currency monedadocumento,
														'".$Data['fecha']."' fechacorte,
														'".$Data['fecha']."' - gbpr.bpr_docdate as dias,
														gbpr.bpr_comments as bpr_comment,
														gbpr.bpr_currency,
														mac1.ac1_font_key as dvf_docentry,
														gbpr.bpr_docnum,
														gbpr.bpr_docdate as FechaDocumento,
														gbpr.bpr_docdate as FechaVencimiento,
														gbpr.bpr_docnum as NumeroDocumento,
														mac1.ac1_font_type as numtype,
														mdt_docname as tipo,
														gbpr.bpr_doctotal as totalfactura,
														(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) as saldo,
														'' retencion,
														get_tax_currency(gbpr.bpr_currency,gbpr.bpr_docdate) as tasa_dia,
														CASE
															WHEN ( '".$Data['fecha']."' - gbpr.bpr_docdate) >=0 and ( '".$Data['fecha']."' - gbpr.bpr_docdate) <=30
																then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
																ELSE 0
														END uno_treinta,
														CASE
															WHEN ( '".$Data['fecha']."' - gbpr.bpr_docdate) >=31 and ( '".$Data['fecha']."' - gbpr.bpr_docdate) <=60
																then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
																ELSE 0
														END treinta_uno_secenta,
														CASE
															WHEN ( '".$Data['fecha']."' - gbpr.bpr_docdate) >=61 and ( '".$Data['fecha']."' - gbpr.bpr_docdate) <=90
																then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
																ELSE 0
														END secenta_uno_noventa,
														CASE
															WHEN ( '".$Data['fecha']."' - gbpr.bpr_docdate) >=91
																then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
														ELSE 0
														END mayor_noventa

														from mac1
														inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
														inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
														inner join gbpr on gbpr.bpr_doctype = mac1.ac1_font_type and gbpr.bpr_docentry = mac1.ac1_font_key
														inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
														where ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
														and mac1.ac1_legal_num = '".$Data['cardcode']."' and dmsn.dms_card_type = '1'

														union all
														select distinct
														dmdt.mdt_docname,
														mac1.ac1_font_key,
														mac1.ac1_legal_num as codigoproveedor,
														dmsn.dms_card_name nombreproveedor,
														mac1.ac1_account as cuenta,
														dvnc.vnc_currency monedadocumento,
														'".$Data['fecha']."' fechacorte,
														'".$Data['fecha']."' - dvnc.vnc_docdate as dias,
														dvnc.vnc_comment as bpr_comment,
														dvnc.vnc_currency,
														mac1.ac1_font_key as dvf_docentry,
														dvnc.vnc_docnum,
														dvnc.vnc_docdate as FechaDocumento,
														dvnc.vnc_duedate as FechaVencimiento,
														dvnc.vnc_docnum as NumeroDocumento,
														mac1.ac1_font_type as numtype,
														mdt_docname as tipo,
														case
														when mac1.ac1_font_type = 5 then mac1.ac1_debit
														else mac1.ac1_credit
														end as totalfactura,
														(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) as saldo,
														'' retencion,
														get_tax_currency(dvnc.vnc_currency,dvnc.vnc_docdate) as tasa_dia,
														CASE
															WHEN ( '".$Data['fecha']."' - dvnc.vnc_duedate) >=0 and ( '".$Data['fecha']."' - dvnc.vnc_duedate) <=30
																then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
																ELSE 0
														END uno_treinta,
														CASE
															WHEN ( '".$Data['fecha']."' - dvnc.vnc_duedate) >=31 and ( '".$Data['fecha']."' - dvnc.vnc_duedate) <=60
																then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
																ELSE 0
														END treinta_uno_secenta,
														CASE
															WHEN ( '".$Data['fecha']."' - dvnc.vnc_duedate) >=61 and ( '".$Data['fecha']."' - dvnc.vnc_duedate) <=90
																then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
																ELSE 0
														END secenta_uno_noventa,
														CASE
															WHEN ( '".$Data['fecha']."' - dvnc.vnc_duedate) >=91
																then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
														ELSE 0
														END mayor_noventa

														from mac1
														inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
														inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
														inner join dvnc on dvnc.vnc_doctype = mac1.ac1_font_type and dvnc.vnc_docentry = mac1.ac1_font_key
														inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
														where ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
														and mac1.ac1_legal_num = '".$Data['cardcode']."' and dmsn.dms_card_type = '1'

														union all
														
														select distinct
														dmdt.mdt_docname,
														mac1.ac1_font_key,
														mac1.ac1_legal_num as CodigoCliente,
														dmsn.dms_card_name NombreCliente,
														mac1.ac1_account as cuenta,
														dvnd.vnd_currency monedadocumento,
														'".$Data['fecha']."' fechacorte,
														'".$Data['fecha']."' - dvnd.vnd_docdate as dias,
														dvnd.vnd_comment as bpr_comment,
														dvnd.vnd_currency,
														mac1.ac1_font_key as dvf_docentry,
														dvnd.vnd_docnum,
														dvnd.vnd_docdate as FechaDocumento,
														dvnd.vnd_duedate as FechaVencimiento,
														dvnd.vnd_docnum as NumeroDocumento,
														mac1.ac1_font_type as numtype,
														mdt_docname as tipo,
														case
														when mac1.ac1_font_type = 5 then mac1.ac1_debit
														else mac1.ac1_credit
														end as totalfactura,
														(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) as saldo,
														'' retencion,
														get_tax_currency(dvnd.vnd_currency,dvnd.vnd_docdate) as tasa_dia,
														CASE
															WHEN ( '".$Data['fecha']."' - dvnd.vnd_duedate) >=0 and ( '".$Data['fecha']."' - dvnd.vnd_duedate) <=30
																then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
																ELSE 0
														END uno_treinta,
														CASE
															WHEN ( '".$Data['fecha']."' - dvnd.vnd_duedate) >=31 and ( '".$Data['fecha']."' - dvnd.vnd_duedate) <=60
																then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
																ELSE 0
														END treinta_uno_secenta,
														CASE
															WHEN ( '".$Data['fecha']."' - dvnd.vnd_duedate) >=61 and ( '".$Data['fecha']."' - dvnd.vnd_duedate) <=90
																then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
																ELSE 0
														END secenta_uno_noventa,
														CASE
															WHEN ( '".$Data['fecha']."' - dvnd.vnd_duedate) >=91
																then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
														ELSE 0
														END mayor_noventa

														from mac1
														inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
														inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
														inner join dvnd on dvnd.vnd_doctype = mac1.ac1_font_type and dvnd.vnd_docentry = mac1.ac1_font_key
														inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
														where ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) ) > 0
														and mac1.ac1_legal_num = '".$Data['cardcode']."' and dmsn.dms_card_type = '1'

														union all
														select distinct
														dmdt.mdt_docname,
														mac1.ac1_font_key,
														case
														    when ac1_card_type = '1'
														        then mac1.ac1_legal_num
														    when ac1_card_type = '2'
														        then mac1.ac1_legal_num
														end as codigoproveedor,
														dmsn.dms_card_name NombreCliente,
														mac1.ac1_account as cuenta,
														tmac.mac_currency,
														'".$Data['fecha']."' fechacorte,
														CURRENT_DATE - tmac.mac_doc_duedate dias_atrasado,
														tmac.mac_comments,
														tmac.mac_currency,
														mac_trans_id as dvf_docentry,
														0 as docnum,
														tmac.mac_doc_date as fecha_doc,
														tmac.mac_doc_duedate as fecha_ven,
														mac_trans_id as id_origen,
														18 as numtype,
														mdt_docname as tipo,
														case
														    when mac1.ac1_cord = 0
														        then mac1.ac1_debit
														    when mac1.ac1_cord = 1
														        then mac1.ac1_credit
														end as total_doc,
														(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) as saldo_venc,
														'' retencion,
														get_tax_currency(tmac.mac_currency,tmac.mac_doc_date) tasa_dia,
														CASE
														    WHEN ( '".$Data['fecha']."' - tmac.mac_doc_duedate) >=0 and ( '".$Data['fecha']."' - tmac.mac_doc_duedate) <=30
														        then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
														    ELSE 0
														END uno_treinta,
														CASE
														    WHEN ( '".$Data['fecha']."' - tmac.mac_doc_duedate)>=31 and ( '".$Data['fecha']."' - tmac.mac_doc_duedate) <=60
														        then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
														    ELSE 0
														END treinta_uno_secenta,
														CASE
														    WHEN ( '".$Data['fecha']."' - tmac.mac_doc_duedate)>=61 and ( '".$Data['fecha']."' - tmac.mac_doc_duedate) <=90
														        then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
														    ELSE 0
														END secenta_uno_noventa,
														CASE
														    WHEN ( '".$Data['fecha']."' - tmac.mac_doc_duedate)>=91
														        then (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
														    ELSE 0
														END mayor_noventa
														from mac1
														inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
														inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
														inner join tmac on tmac.mac_trans_id = mac1.ac1_font_key and tmac.mac_doctype = mac1.ac1_font_type
														inner join dmsn on mac1.ac1_card_type = dmsn.dms_card_type and mac1.ac1_legal_num = dmsn.dms_card_code
														where dmsn.dms_card_type = '1' and and mac1.ac1_legal_num = '".$Data['cardcode']."'
														and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0";

				$contenidoestadocuenta = $this->pedeo->queryTable($sqlestadocuenta,array());
         // print_r($sqlestadocuenta);exit();die();
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
				$detail_0_30 = 0;
				$detail_30_60 = 0;
				$detail_60_90 = 0;
				$detail_mayor_90 = 0;
				$total_valores = '';

				foreach ($contenidoestadocuenta as $key => $value) {
					// code...
					$detalle = '
											<td class="centro">'.$value['mdt_docname'].'</td>
											<td style="width: 12%;" class="centro">'.$value['numerodocumento'].'</td>
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

				  $detail_0_30 =  $detail_0_30 + ($value['uno_treinta']);
				  $detail_30_60 =  $detail_30_60 + ($value['treinta_uno_secenta']);
				  $detail_60_90 =  $detail_60_90 + ($value['secenta_uno_noventa']);
				  $detail_mayor_90 =  $detail_mayor_90 + ($value['mayor_noventa']);
					$total_saldo = $detail_0_30+$detail_30_60+$detail_60_90+$detail_mayor_90;
					$total_valores = '
								<tr>
								<th>&nbsp;</th>
								<th>&nbsp;</th>
								<th>&nbsp;</th>
								<th>&nbsp;</th>
								<th>&nbsp;</th>
								<th><b>Total</b></th>
								<th style="width: 10%;" class=" centro"><b>'.$value['monedadocumento'].' '.number_format(($total_saldo), 2, ',', '.').'</b></th>
								<th class=" centro"><b>'.$value['monedadocumento'].' '.number_format($detail_0_30, 2, ',', '.').'</b></th>
								<th class=" centro"><b>'.$value['monedadocumento'].' '.number_format($detail_30_60, 2, ',', '.').'</b></th>
								<th class=" centro"><b>'.$value['monedadocumento'].' '.number_format($detail_60_90, 2, ',', '.').'</b></th>
								<th class=" centro"><b>'.$value['monedadocumento'].' '.number_format($detail_mayor_90, 2, ',', '.').'</b></th>
								</tr>';

				  $totalfactura = ($total_saldo);
				}


        $header = '
        <table width="100%">
        <tr>
            <th style="text-align: left;"><img src="/var/www/html/'.$company[0]['company'].'/'.$empresa[0]['pge_logo'].'" width ="100" height ="40"></img></th>
            <th style="text-align: center; margin-left: 600px;">
                <p><b>INFORME ESTADO DE CUENTA CLIENTE</b></p>

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


				$html = '

				<table class="" style="width:100%">
			 <tr>
				 <th>
					 <p class="" style="text-align: left;"><b>RIF:</b></p>
				 </th>
				 <th style="text-align: left;">
					 <p>'.$contenidoestadocuenta[0]['codigoproveedor'].'</p>
				 </th>
				</tr>
				<tr>
				 <th >
					 <p class="" ><b>Nombre Cliente:</b></p>
	 			 <th style="text-align: left;">

					 <p style="text-align: left;">'.$contenidoestadocuenta[0]['nombreproveedor'].'</p>

	 			 </th>
			 	</tr>
			 <tr>
				 <th>
					 <p class=""><b>Saldo:</b></p>
				 </th>
				 <th style="text-align: left;">
					 <p>'.$value['monedadocumento']." ".number_format($totalfactura, 2, ',', '.').'</p>
				 </th>

			 </tr>

			 </table>
			 <table width="100%" style="vertical-align: bottom; font-family: serif;
					 font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
					 <tr>
							 <th class="fondo">
									 <p></p>
							 </th>
					 </tr>
			 </table>
        <br>

        <table class="borde" style="width:100%">
        <tr>
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
          <th class=""><b>+90</b></th>
        </tr>
      	'.$totaldetalle.$total_valores.'
        </table>
        <br>
        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th class="">
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


        $mpdf->Output('EstadoCuenta_'.$contenidoestadocuenta[0]['codigoproveedor'].'-'.$contenidoestadocuenta[0]['nombreproveedor'].'.pdf', 'D');

				header('Content-type: application/force-download');
				header('Content-Disposition: attachment; filename='.$filename);


	}




}
