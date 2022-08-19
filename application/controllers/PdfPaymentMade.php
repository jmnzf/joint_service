<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class PdfPaymentMade extends REST_Controller {

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
		$this->load->library('generic');

	}


	public function PdfPaymentMade_post(){

				$DECI_MALES =  $this->generic->getDecimals();

        $Data = $this->post();
				$Data = $Data['BPE_DOCENTRY'];

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
											    t0.bpe_cardcode,
											    t0.bpe_cardname,
											    t0.bpe_docdate,
													t0.bpe_datetransfer,
											    t0.bpe_docnum,
											    0 cuenta_bene,
											    t1.pe1_docnum ,
											    t1.pe1_doctype,
											    case
														when t6.bpe_doctype = t1.pe1_doctype then 'Anticipo'
														else t5.mdt_docname
													end tipo,
											    case
											        when t2.cfc_doctype = t1.pe1_doctype then t2.cfc_docnum
											        when t3.cnc_doctype = t1.pe1_doctype then t3.cnc_docnum
											        when t4.cnd_doctype =  t1.pe1_doctype then t4.cnd_docnum
											        when t6.bpe_doctype = t1.pe1_doctype then t6.bpe_docnum
											    end docnum,
											    case
											        when t2.cfc_doctype = t1.pe1_doctype then t2.cfc_docdate
											        when t3.cnc_doctype = t1.pe1_doctype then t3.cnc_docdate
											        when t4.cnd_doctype =  t1.pe1_doctype then t4.cnd_docdate
											        when t6.bpe_doctype = t1.pe1_doctype then t6.bpe_docdate
											    end docdate,
											    case
											        when t2.cfc_doctype = t1.pe1_doctype then t2.cfc_docdate
											        when t3.cnc_doctype = t1.pe1_doctype then t3.cnc_docdate
											        when t4.cnd_doctype =  t1.pe1_doctype then t4.cnd_docdate
											        when t6.bpe_doctype = t1.pe1_doctype then t6.bpe_docdate
											    end docdate,
											    case
											        when t2.cfc_doctype = t1.pe1_doctype then t2.cfc_comment
											        when t3.cnc_doctype = t1.pe1_doctype then t3.cnc_comment
											        when t4.cnd_doctype =  t1.pe1_doctype then t4.cnd_comment
											        when t6.bpe_doctype = t1.pe1_doctype then t6.bpe_comments
											    end comentario,
											    case
											        when t2.cfc_doctype = t1.pe1_doctype then t2.cfc_baseamnt
											        when t3.cnc_doctype = t1.pe1_doctype then t3.cnc_baseamnt
											        when t4.cnd_doctype =  t1.pe1_doctype then t4.cnd_baseamnt
											        when t6.bpe_doctype = t1.pe1_doctype then t6.bpe_doctotal * -1
											    end base,
											    case
											        when t2.cfc_doctype = t1.pe1_doctype then t2.cfc_taxtotal
											        when t3.cnc_doctype = t1.pe1_doctype then t3.cnc_taxtotal
											        when t4.cnd_doctype =  t1.pe1_doctype then t4.cnd_taxtotal
											        when t6.bpe_doctype = t1.pe1_doctype then 0
											    end iva,
											    coalesce((select sum(a.crt_basert) from fcrt a inner join dmrt b on a.crt_typert = b.mrt_id where b.mrt_tasa = 0
											        and a.crt_baseentry = t2.cfc_docentry and a.crt_basetype = t2.cfc_doctype),0) exento,
											    case
											        when t2.cfc_doctype = t1.pe1_doctype then t2.cfc_baseamnt + t2.cfc_taxtotal
											        when t3.cnc_doctype = t1.pe1_doctype then t3.cnc_baseamnt + t3.cnc_taxtotal
											        when t4.cnd_doctype =  t1.pe1_doctype then t4.cnd_baseamnt + t4.cnd_taxtotal
											        when t6.bpe_doctype = t1.pe1_doctype then t6.bpe_doctotal * -1
											    end neto,
											    coalesce((select sum(a.crt_basert) from fcrt a
											    where a.crt_baseentry = t2.cfc_docentry and a.crt_basetype = t2.cfc_doctype and a.crt_type = 3),0)  base_ret_iva,
											    (select a.mrt_tasa from dmrt a inner join fcrt b on a.mrt_id = b.crt_typert
											    where b.crt_baseentry = t2.cfc_docentry and b.crt_basetype = t2.cfc_doctype and b.crt_type = 3 ) porcentaje_ret_iva,
											    coalesce((select sum(a.crt_basert) from fcrt a
											    where a.crt_baseentry = t2.cfc_docentry and a.crt_basetype = t2.cfc_doctype and a.crt_type = 2),0) base_ret_islr,
											    (select a.mrt_tasa from dmrt a inner join fcrt b on a.mrt_id = b.crt_typert
											    where b.crt_baseentry = t2.cfc_docentry and b.crt_basetype = t2.cfc_doctype and b.crt_type = 2) porcentaje_ret_islr,
											    coalesce((select sum(a.crt_basert) from fcrt a
											    where a.crt_baseentry = t2.cfc_docentry and a.crt_basetype = t2.cfc_doctype and a.crt_type = 1),0) base_ret_ipm,
											    (select a.mrt_tasa from dmrt a inner join fcrt b on a.mrt_id = b.crt_typert
											    where b.crt_baseentry = t2.cfc_docentry and b.crt_basetype = t2.cfc_doctype and b.crt_type = 1) porcentaje_ret_ipm,
											    case
											        when t2.cfc_doctype = t1.pe1_doctype then t2.cfc_doctotal
											        when t3.cnc_doctype = t1.pe1_doctype then t3.cnc_doctotal
											        when t4.cnd_doctype =  t1.pe1_doctype then t4.cnd_doctotal
											        when t6.bpe_doctype = t1.pe1_doctype then t6.bpe_doctotal * -1
											    end total,
													case
														when t6.bpe_doctype = t1.pe1_doctype then t1.pe1_vlrpaid * -1
														else t1.pe1_vlrpaid
													end pe1_vlrpaid,
												t0.bpe_memo,
												get_tax_currency(t0.bpe_currency,t0.bpe_docdate) tasa
											from gbpe t0
											inner join bpe1 t1 on t0.bpe_docentry = t1.pe1_docnum
											left join dcfc t2 on t1.pe1_docentry = t2.cfc_docentry and t1.pe1_doctype = t2.cfc_doctype
											left join dcnc t3 on t1.pe1_docentry = t3.cnc_docentry and t1.pe1_doctype = t3.cnc_doctype
											left join dcnd t4 on t1.pe1_docentry = t4.cnd_docentry and t1.pe1_doctype = t4.cnd_doctype
											left join gbpe t6 on t1.pe1_docentry = t6.bpe_docentry and t1.pe1_doctype = t6.bpe_doctype
											inner join dmdt t5 on t1.pe1_doctype = t5.mdt_doctype
											where t0.bpe_docentry = :bpe_docentry";

				$contenidoOC = $this->pedeo->queryTable($sqlcotizacion,array(':bpe_docentry'=>$Data));
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
											<td>'.number_format($value['base'], $DECI_MALES, ',', '.').'</td>
											<td>'.number_format($value['iva'], $DECI_MALES, ',', '.').'</td>
											<td>'.number_format($value['exento'], $DECI_MALES, ',', '.').'</td>
											<td>'.$value['porcentaje_ret_iva'].'</td>
											<td>'.number_format($value['base_ret_iva'], $DECI_MALES, ',', '.').'</td>
											<td>'.number_format(($value['neto'] + $value['exento']) - $value['base_ret_iva'] , $DECI_MALES, ',', '.').'</td>
											<td>'.number_format($value['base'], $DECI_MALES, ',', '.').'</td>
											<td>'.$value['porcentaje_ret_islr'].'</td>
											<td>'.number_format($value['base_ret_islr'], $DECI_MALES, ',', '.').'</td>
											<td>'.$value['porcentaje_ret_ipm'].'</td>
											<td>'.number_format($value['base_ret_ipm'], $DECI_MALES, ',', '.').'</td>
											<td>'.number_format($value['total'], $DECI_MALES, ',', '.').'</td>
											<td>'.number_format($value['pe1_vlrpaid'], $DECI_MALES, ',', '.').'</td>';
				 $totaldetalle = $totaldetalle.'<tr>'.$detalle.'</tr>';
				 $totales = $totales + ($value['pe1_vlrpaid']);
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
					<p><b>PAGO EFECTUADO</b></p>
				</th>
			/tr>

		</table>';
				$footer = '
        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th  width="33%">Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
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
						<p >'.$contenidoOC[0]['bpe_docnum'].'</p>
						<p>'.$this->dateformat->Date($contenidoOC[0]['bpe_docdate']).'</p>
						<p>'.$this->dateformat->Date($contenidoOC[0]['bpe_datetransfer']).'</p>
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
					<td><b>RIF:</b> <span>'.$contenidoOC[0]['bpe_cardcode'].'</span></p></td>
					<td></td>
					<td><b>nombre proveedor:</b> <span>'.$contenidoOC[0]['bpe_cardname'].'</span></p></td>
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
						<td style="text-align: left;"><b>COMENTARIO:</b><span>'.$contenidoOC[0]['bpe_memo'].'</td>
						<td></td>
						<td style="text-align: left;"><b>TASA:</b><span>'.number_format($contenidoOC[0]['tasa'], $DECI_MALES, ',', '.').'</td>
					</tr>
					<tr>
						<td></td>
						<td></td>
						<td style="text-align: left;"><b>TOTAL BS:</b><span>'.number_format($totales, $DECI_MALES, ',', '.').'</td>
					</tr>
					<tr>
						<td></td>
						<td></td>
						<td style="text-align: left;"><b>TOTAL USD:</b><span>'.number_format(($totales / $contenidoOC[0]['tasa']), $DECI_MALES, ',', '.').'</td>
					</tr>
				</table>
				</html>';
// print_r($html);exit();die();
        $stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');

        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);


        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);

				$filename = 'PE_'.$contenidoOC[0]['bpe_docnum'].'.pdf';
				// print_r($contenidoOC);
        $mpdf->Output($filename, 'D');


				header('Content-type: application/force-download');
				header('Content-Disposition: attachment; filename='.$filename);


	}




}
