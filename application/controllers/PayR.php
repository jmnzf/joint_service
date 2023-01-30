<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class PayR extends REST_Controller {

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


	public function PayR_post(){

				$DECI_MALES =  $this->generic->getDecimals();

        $Data = $this->post();
				$Data = $Data['BPR_DOCENTRY'];

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
		           'mensaje' =>'no esta registrada la información de la empresa'
		        );

	          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	          return;
				}

				$sqlpagoE = "SELECT
        t0.bpr_docentry id,
        t0.bpr_cardcode nit,
        trim(t0.bpr_cardname) proveedor,
        t0.bpr_address direccion,
        '' telefono,
        t0.bpr_perscontact contacto,
        t0.bpr_series serie,
        t0.bpr_docnum numero_doc,
        t0.bpr_docdate fecha_doc,
        cast(t0.bpr_createat as date) fecha_cre,
        t0.bpr_comments referencia,
        t0.bpr_memo comentarios,
        t0.bpr_datetransfer fecha_transf,
        t0.bpr_doctotal total_doc,
        t0.bpr_currency moneda,
				t0.bpr_docentry,
				t0.bpr_doctype,
        t1.pr1_doctype origen,
				t0.bpr_comments,
				t0.bpr_reftransfer,
        substr(t2.mdt_docname,1,1)||substr(t2.mdt_docname,7,1) tipo,
        case
          when t1.pr1_doctype = 15 then (select aa.dvf_docnum from dvfv aa where aa.dvf_docentry = t1.pr1_docentry)
          else 0
        end docnum,
        t1.pr1_docdate fecha_origen,
        t1.pr1_docduedate fecha_ven,
        t1.pr1_daysbackw dias_ven,
        t1.pr1_vlrtotal total_doc_origen,
        t1.pr1_vlrpaid total_apli,
        (t1.pr1_vlrtotal - t1.pr1_vlrpaid) saldo,
        t1.pr1_comments coments_origen,
				case
						when t3.dvf_doctype = t1.pr1_doctype then t3.dvf_docnum
						when t4.vnc_doctype = t1.pr1_doctype then t4.vnc_docnum
						when t5.vnd_doctype =  t1.pr1_doctype then t5.vnd_docnum
						when t6.bpr_doctype = t1.pr1_doctype then t6.bpr_docnum
				end docnumorg

      from gbpr t0
      left join bpr1 t1 on t0.bpr_docentry = t1.pr1_docnum
      join dmdt t2 on t1.pr1_doctype = t2.mdt_doctype
			left join dvfv t3 on t1.pr1_docentry = t3.dvf_docentry and t1.pr1_doctype = t3.dvf_doctype
			left join dvnc t4 on t1.pr1_docentry = t4.vnc_docentry and t1.pr1_doctype = t4.vnc_doctype
			left join dvnd t5 on t1.pr1_docentry = t5.vnd_docentry and t1.pr1_doctype = t5.vnd_doctype
			left join gbpr t6 on t1.pr1_docnum = t6.bpr_docentry and t1.pr1_doctype = t6.bpr_doctype
      where t0.bpr_docentry = :bpr_docentry";

				$contenidoPAYE = $this->pedeo->queryTable($sqlpagoE,array(':bpr_docentry'=>$Data));

				if(!isset($contenidoPAYE[0])){
						$respuesta = array(
							 'error' => true,
							 'data'  => $contenidoPAYE,
							 'mensaje' =>'no se encontro el documento'
						);

						$this->response($respuesta, REST_Controller);

						return;
				}

				$sqlCuentaContable = "SELECT acc_name, acc_code
															FROM mac1
															INNER JOIN dacc
															ON ac1_account  = acc_code
															WHERE ac1_font_key = :ac1_font_key
															AND ac1_font_type = :ac1_font_type
															AND ac1_debit != :ac1_debit";

				$resSqlCuentaContable = $this->pedeo->queryTable($sqlCuentaContable, array(
					 ':ac1_font_key'  => $contenidoPAYE[0]['bpr_docentry'],
					 ':ac1_font_type' => $contenidoPAYE[0]['bpr_doctype'],
					 ':ac1_debit' 		=> 0
				));

				if(!isset($resSqlCuentaContable[0])){
						$respuesta = array(
							 'error' => true,
							 'data'  => $resSqlCuentaContable,
							 'mensaje' =>'no se encontro el documento'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}


				$totaldetalle = '';
				foreach ($contenidoPAYE as $key => $value) {
					// code...
					$detalle = '<td>'.$value['tipo'].'</td>
                      <td>'.$value['docnumorg'].'</td>
											<td>'.$this->dateformat->Date($value['fecha_origen']).'</td>
											<td>'.$this->dateformat->Date($value['fecha_ven']).'</td>
											<td>'.$value['dias_ven'].'</td>
											<td>$'.number_format($value['total_doc_origen'], $DECI_MALES, ',', '.').'</td>
											<td>$'.number_format($value['total_apli'], $DECI_MALES, ',', '.').'</td>
                      <td>$'.number_format($value['saldo'], $DECI_MALES, ',', '.').'</td>';
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
                <p><b>PAGO RECIBIDO<b></p>
                <p>'.$contenidoPAYE[0]['numero_doc'].'</p>

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

        <table style="width:100%">
        <tr>
          <th>
          	<p><b>SEÑOR(ES):<b></p>
          </th>
          <th style="text-align: left;">
          	<p>'.$contenidoPAYE[0]['proveedor'].'</p>
          </th>
          <th>
            <p><b>FECHA DE DOCUMENTO<b></p>
            <p>'.$this->dateformat->Date($contenidoPAYE[0]['fecha_doc']).'</p>
          </th>
        </tr>
        <tr>
          <th>
            <p><b>DIRECCIÓN:<b></p>
          </th>
          <th style="text-align: left;">
            <p>'.$contenidoPAYE[0]['direccion'].'</p>
          </th>

        </tr>
        <tr>
          <th>
            <p><b>TELÉFONO:<b></p>
          </th>
          <th style="text-align: left;">
            <p>
            	<span>'.$contenidoPAYE[0]['telefono'].'</span>
                <span><b>RIF:<b></span>
                <span>'.$contenidoPAYE[0]['nit'].'</span>
            </p>
          </th>
          <th>
            <p><b>FECHA DE CREACION <b></p>
            <p>'.$this->dateformat->Date($contenidoPAYE[0]['fecha_cre']).'</p>
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
          <th><b>TIPO</b></th>
          <th><b>DOCUMENTO</b></th>
          <th><b>FECHA DOCUMENTO</b></th>
          <th><b>FECHA VENCIMIENTO</b></th>
          <th><b>DIAS VENCIDOS</b></th>
          <th><b>VALOR TOTAL</b></th>
          <th><b>VALOR APLICADO</b></th>
          <th><b>SALDO</b></th>
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
            <td style="text-align: left;"><b>TOTAL PAGADO: </b><span>$'.number_format($contenidoPAYE[0]['total_doc'], $DECI_MALES, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: left;"><b>REFERENCIA: </b><span>'.$contenidoPAYE[0]['bpr_reftransfer'].'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: left;"><b>COMENTARIO APLICADO: </b><span>'.$contenidoPAYE[0]['comentarios']." / ".$contenidoPAYE[0]['bpr_comments'].'</span></span></p></td>
        </tr>
        <tr>
            <td style="text-align: left;"><b>FECHA DE TRANSFERENCIA: </b><span>'.$this->dateformat->Date($contenidoPAYE[0]['fecha_transf']).'</span></p></td>
        </tr>

				<tr>
            <td style="text-align: left;"><b>CUENTA: </b><span>'.$resSqlCuentaContable[0]['acc_code']." ".$resSqlCuentaContable[0]['acc_name'].'</span></p></td>
        </tr>

        </table>


        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th style="text-align: left;">
                    <p><b>Valor en letras:</b></p>
                    <p>'.$formatter->toWords($contenidoPAYE[0]['total_doc'],2)." ".$contenidoPAYE[0]['moneda'].'</p>
                </th>
            </tr>
        </table>

        <br><br>
        ';
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
