<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class PdfPaymentRecived extends REST_Controller {

	private $pdo;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
    $this->load->library('pedeo', [$this->pdo]);
			$this->load->library('DateFormat');

	}


	public function PdfPaymentRecived_post(){

        $Data = $this->post();
				$Data = $Data['bpr_docentry'];

				$formatter = new NumeroALetras();

				// $mpdf = new \Mpdf\Mpdf(['setAutoBottomMargin' => 'stretch','setAutoTopMargin' => 'stretch']);
        $mpdf = new \Mpdf\Mpdf(['setAutoBottomMargin' => 'stretch','setAutoTopMargin' => 'stretch','default_font' => 'dejavusans']);

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

				$empresa = $this->pedeo->queryTable("SELECT * FROM pgem", array());
															// print_r($empresa);exit();die();

				if(!isset($empresa[0])){
						$respuesta = array(
		           'error' => true,
		           'data'  => $empresa,
		           'mensaje' =>'no esta registrada la información de la empresa'
		        );

	          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	          return;
				}


				$sqlcotizacion = "SELECT
				t0.bpr_cardcode,
				t0.bpr_cardname,
				t0.bpr_docdate,
				t0.bpr_datetransfer,
				t0.bpr_docnum,
				0 cuenta_bene,
				t1.pr1_docnum ,
				t1.pr1_doctype,
				case
						when t6.bpr_doctype = t1.pr1_doctype then 'Anticipo'
						else t5.mdt_docname
					end tipo,
				case
					when t2.dvf_doctype = t1.pr1_doctype then t2.dvf_docnum
					when t3.vnc_doctype = t1.pr1_doctype then t3.vnc_docnum
					when t4.vnd_doctype =  t1.pr1_doctype then t4.vnd_docnum
					when t6.bpr_doctype = t1.pr1_doctype then t6.bpr_docnum
				end docnum,
				case
					when t2.dvf_doctype = t1.pr1_doctype then t2.dvf_docdate
					when t3.vnc_doctype = t1.pr1_doctype then t3.vnc_docdate
					when t4.vnd_doctype =  t1.pr1_doctype then t4.vnd_docdate
					when t6.bpr_doctype = t1.pr1_doctype then t6.bpr_docdate
				end docdate,
				case
					when t2.dvf_doctype = t1.pr1_doctype then t2.dvf_docdate
					when t3.vnc_doctype = t1.pr1_doctype then t3.vnc_docdate
					when t4.vnd_doctype =  t1.pr1_doctype then t4.vnd_docdate
					when t6.bpr_doctype = t1.pr1_doctype then t6.bpr_docdate
				end docdate,
				case
					when t2.dvf_doctype = t1.pr1_doctype then t2.dvf_comment
					when t3.vnc_doctype = t1.pr1_doctype then t3.vnc_comment
					when t4.vnd_doctype =  t1.pr1_doctype then t4.vnd_comment
					when t6.bpr_doctype = t1.pr1_doctype then t6.bpr_comments
				end comentario,
				case
					when t2.dvf_doctype = t1.pr1_doctype then t2.dvf_baseamnt
					when t3.vnc_doctype = t1.pr1_doctype then t3.vnc_baseamnt
					when t4.vnd_doctype =  t1.pr1_doctype then t4.vnd_baseamnt
					when t6.bpr_doctype = t1.pr1_doctype then t6.bpr_doctotal * -1
				end base,
				case
					when t2.dvf_doctype = t1.pr1_doctype then t2.dvf_taxtotal
					when t3.vnc_doctype = t1.pr1_doctype then t3.vnc_taxtotal
					when t4.vnd_doctype =  t1.pr1_doctype then t4.vnd_taxtotal
					when t6.bpr_doctype = t1.pr1_doctype then 0
				end iva,
				coalesce((select sum(a.crt_basert) from fcrt a inner join dmrt b on a.crt_typert = b.mrt_id where b.mrt_tasa = 0
					and a.crt_baseentry = t2.dvf_docentry and a.crt_basetype = t2.dvf_doctype),0) exento,
				case
					when t2.dvf_doctype = t1.pr1_doctype then t2.dvf_baseamnt + t2.dvf_taxtotal
					when t3.vnc_doctype = t1.pr1_doctype then t3.vnc_baseamnt + t3.vnc_taxtotal
					when t4.vnd_doctype =  t1.pr1_doctype then t4.vnd_baseamnt + t4.vnd_taxtotal
					when t6.bpr_doctype = t1.pr1_doctype then t6.bpr_doctotal * -1
				end neto,
				coalesce((select sum(a.crt_basert) from fcrt a
				where a.crt_baseentry = t2.dvf_docentry and a.crt_basetype = t2.dvf_doctype and a.crt_type = 3),0)  base_ret_iva,
				(select a.mrt_tasa from dmrt a inner join fcrt b on a.mrt_id = b.crt_typert
				where b.crt_baseentry = t2.dvf_docentry and b.crt_basetype = t2.dvf_doctype and b.crt_type = 3 ) porcentaje_ret_iva,
				coalesce((select sum(a.crt_basert) from fcrt a
				where a.crt_baseentry = t2.dvf_docentry and a.crt_basetype = t2.dvf_doctype and a.crt_type = 2),0) base_ret_islr,
				(select a.mrt_tasa from dmrt a inner join fcrt b on a.mrt_id = b.crt_typert
				where b.crt_baseentry = t2.dvf_docentry and b.crt_basetype = t2.dvf_doctype and b.crt_type = 2) porcentaje_ret_islr,
				coalesce((select sum(a.crt_basert) from fcrt a
				where a.crt_baseentry = t2.dvf_docentry and a.crt_basetype = t2.dvf_doctype and a.crt_type = 1),0) base_ret_ipm,
				(select a.mrt_tasa from dmrt a inner join fcrt b on a.mrt_id = b.crt_typert
				where b.crt_baseentry = t2.dvf_docentry and b.crt_basetype = t2.dvf_doctype and b.crt_type = 1) porcentaje_ret_ipm,
				case
					when t2.dvf_doctype = t1.pr1_doctype then t2.dvf_doctotal
					when t3.vnc_doctype = t1.pr1_doctype then t3.vnc_doctotal
					when t4.vnd_doctype =  t1.pr1_doctype then t4.vnd_doctotal
					when t6.bpr_doctype = t1.pr1_doctype then t6.bpr_doctotal * -1
				end total,
					case
						when t6.bpr_doctype = t1.pr1_doctype then t1.pr1_vlrpaid * -1
						else t1.pr1_vlrpaid
					end pr1_vlrpaid,
				t0.bpr_memo,
				t7.tsa_value tasa
			from gbpr t0
			inner join bpr1 t1 on t0.bpr_docentry = t1.pr1_docnum
			left join dvfv t2 on t1.pr1_docentry = t2.dvf_docentry and t1.pr1_doctype = t2.dvf_doctype
			left join dvnc t3 on t1.pr1_docentry = t3.vnc_docentry and t1.pr1_doctype = t3.vnc_doctype
			left join dvnd t4 on t1.pr1_docentry = t4.vnd_docentry and t1.pr1_doctype = t4.vnd_doctype
			left join gbpr t6 on t1.pr1_docentry = t6.bpr_docentry and t1.pr1_doctype = t6.bpr_doctype
			inner join dmdt t5 on t1.pr1_doctype = t5.mdt_doctype
			left join tasa t7 on t0.bpr_currency = t7.tsa_curro and t0.bpr_docdate = t7.tsa_date
			where t0.bpr_docentry = :bpr_docentry";

				$contenidoOC = $this->pedeo->queryTable($sqlcotizacion,array(':bpr_docentry'=>$Data));
// print_r($sqlcotizacion);exit();die();
				if(!isset($contenidoOC[0])){
						$respuesta = array(
							 'error' => true,
							 'data'  => $contenidoOC,
							 'mensaje' =>'no se encontro el documento'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}
				// print_r($contenidoOC);exit();die();

				$totaldetalle = '';
				$totales = 0;
				foreach ($contenidoOC as $key => $value) {
					// code...
					$detalle = '<td>'.$value['tipo'].'</td>
											<td>'.$value['docnum'].'</td>
											<td>'.$this->dateformat->Date($value['docdate']).'</td>
											<td>'.$value['comentario'].'</td>
											<td>'.number_format($value['base'], 2, ',', '.').'</td>
											<td>'.number_format($value['iva'], 2, ',', '.').'</td>
											<td>'.number_format($value['exento'], 2, ',', '.').'</td>
											<td>'.$value['porcentaje_ret_iva'].'</td>
											<td>'.number_format($value['base_ret_iva'], 2, ',', '.').'</td>
											<td>'.number_format(($value['neto'] + $value['exento']) - $value['base_ret_iva'] , 2, ',', '.').'</td>
											<td>'.number_format($value['base'], 2, ',', '.').'</td>
											<td>'.$value['porcentaje_ret_islr'].'</td>
											<td>'.number_format($value['base_ret_islr'], 2, ',', '.').'</td>
											<td>'.$value['porcentaje_ret_ipm'].'</td>
											<td>'.number_format($value['base_ret_ipm'], 2, ',', '.').'</td>
											<td>'.number_format($value['total'], 2, ',', '.').'</td>
											<td>'.number_format($value['pr1_vlrpaid'], 2, ',', '.').'</td>';
				 $totaldetalle = $totaldetalle.'<tr>'.$detalle.'</tr>';
				 $totales = $totales + ($value['pr1_vlrpaid']);
				}


        $header = '
				<table width="100%" style="text-align: left;">
        			<tr>
            			<th style="text-align: left;">
							<img src="/var/www/html/'.$company[0]['company'].'/'.$empresa[0]['pge_logo'].'" 
							width ="100" height ="40"></img>
						</th>
            			<th>
							<p><b>'.$empresa[0]['pge_name_soc'].'</b></p>
							<p><b>'.$empresa[0]['pge_id_type'].'</b></p>
							<p><b>'.$empresa[0]['pge_add_soc'].'</b></p>

						</th>
						<th>
						    <p><b>PAGO Recibido</b></p>
						</th>
					/tr>

				</table>';
					// print_r($header);exit();die();
		$footer = '
        		<table width="100%" style="vertical-align: bottom; font-family: serif;
            		font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            		<tr>
                		<th  width="33%">Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y} </th>
            		</tr>
        		</table>';


        $html = '
				<table  width="100%">
        			<tr>
            			<th style="text-align: left;">
							<p><b>'.$empresa[0]['pge_small_name'].'</b></p>
							<p>'.$empresa[0]['pge_add_soc'].'</p>
                			<p>'.$empresa[0]['pge_id_type'].'</p>
							<p>'.$empresa[0]['pge_state_soc'].'</p>
                			<p>TELEFONO:'.$empresa[0]['pge_phone1'].' / '.$empresa[0]['pge_phone2'].'</p>
                			<p>website: '.$empresa[0]['pge_web_site'].'</p>
                			<p>Instagram: '.$empresa[0]['pge_page_social'].'</p>
            			</th>
						<th style="text-align: right;">
							<p><b>PAGO: </b></p>
							<p><b>FECHA DE EMISIÓN: </b></p>
							<p><b>FECHA DE PAGO: </b></p>
						</th>
						<th style="text-align: left;">
							<p >'.$contenidoOC[0]['bpr_docnum'].'</p>
							<p>'.$this->dateformat->Date($contenidoOC[0]['bpr_docdate']).'</p>
							<p>'.$this->dateformat->Date($contenidoOC[0]['bpr_datetransfer']).'</p>

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

				<table  width="100%">
				<tr>
					<td><b>RIF:</b> <span>'.$contenidoOC[0]['bpr_cardcode'].'</span></p></td>
					<td></td>
					<td><b>nombre cliente:</b> <span>'.$contenidoOC[0]['bpr_cardname'].'</span></p></td>
				</tr>
				<tr>
					<td><b>cuenta:</b> <span>'.$contenidoOC[0]['cuenta_bene'].'</span></p></td>
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

				<table  class="borde" style="width:100%">

        <tr>
          <th><b>TIPO DOC</b></th>
					<th><b># DOC</b></th>
					<th><b>FECHA DOC</b></th>
					<th><b>COMENTARIO</b></th>
					<th><b>TOTAL BASE</b></th>
					<th><b>TOTAL IVA</b></th>
					<th><b>TOTAL EXE</b></th>
					<th><b>% R.IVA</b></th>
					<th><b>TOTAL R.IVA</b></th>
					<th><b>TOTAL NETO</b></th>
					<th><b>BASE RET</b></th>
					<th><b>% ISRL</b></th>
					<th><b>TOTAL R.ISRL</b></th>
					<th><b>% R.IPM</b></th>
					<th><b>TOTAL R.IPM</b></th>
					<th><b>TOTAL A PAGAR</b></th>
					<th><b>TOTAL APLICADO</b></th>
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

				<table width="100%">
					<tr>
						<td style="text-align: left;"><b>COMENTARIO:</b><span>'.$contenidoOC[0]['bpr_memo'].'</td>
						<td></td>
						<td style="text-align: left;"><b>TASA:</b><span>'.number_format($contenidoOC[0]['tasa'], 2, ',', '.').'</td>
					</tr>
					<tr>
						<td></td>
						<td></td>
						<td style="text-align: left;"><b>TOTAL BS:</b><span>'.number_format($totales, 2, ',', '.').'</td>
					</tr>
					<tr>
						<td></td>
						<td></td>
						<td style="text-align: left;"><b>TOTAL USD:</b><span>'.number_format(($totales / $contenidoOC[0]['tasa']), 2, ',', '.').'</td>
					</tr>
				</table>
				</html>';
// print_r($html);exit();die();
        $stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');

        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);


        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);

				$filename = 'PE_'.$contenidoOC[0]['bpr_docnum'].'.pdf';
				// print_r($contenidoOC);
        $mpdf->Output($filename, 'D');


				header('Content-type: application/force-download');
				header('Content-Disposition: attachment; filename='.$filename);


	}




}
