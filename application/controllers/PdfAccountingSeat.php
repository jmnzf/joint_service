<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class PdfAccountingSeat extends REST_Controller {

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


	public function PdfAccountingSeat_post(){

        $Data = $this->post();
				$Data = $Data['mac_trans_id'];

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

				$empresa = $this->pedeo->queryTable("SELECT pge_id, pge_name_soc, pge_small_name, pge_add_soc, pge_state_soc, pge_city_soc,
																					   pge_cou_soc, CONCAT(pge_id_soc,' / ',pge_id_type) AS pge_id_type , pge_web_site, pge_logo,
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

				$sqlcotizacion = "SELECT
                          t0.mac_trans_id,
                          t0.mac_doc_num,
                          t0.mac_doc_date,
                          t0.mac_legal_date,
                          '' cal_imp,
                          t0.mac_ref1,
                          t0.mac_ref2,
                          t0.mac_ref3,
                          'BS o USD' moneda_cab,
                          extract(year from t0.mac_doc_date) ejercicio,
                          extract(month from t0.mac_doc_date) periodo,
                          t1.ac1_line_num,
                          case
                              when t1.ac1_debit > 0
                                  then 'DB'
                              when t1.ac1_credit > 0
                                  then 'CR'
                          end ct,
                          t1.ac1_account,
                          t2.acc_name,
                          concat(t16.dmi_code,' - ',t16.dmi_name_tax) impuesto,
                          t1.ac1_uncode,
                          t1.ac1_debit,
                          t1.ac1_credit,
                          t1.ac1_debit_sys,
                          t1.ac1_credit_sys,
                          '' moneda,
                          t13.mdt_docname,
                          case
                              when t3.cfc_doctype = t0.mac_base_type then t3.cfc_docnum
                              when t4.cnd_doctype = t0.mac_base_type then t4.cnd_docnum
                              when t5.cnc_doctype = t0.mac_base_type then t5.cnc_docnum
                              when t6.dvf_doctype = t0.mac_base_type then t6.dvf_docnum
                              when t7.vnd_doctype = t0.mac_base_type then t7.vnd_docnum
                              when t8.vnc_doctype = t0.mac_base_type then t8.vnc_docnum
                              when t9.vem_doctype = t0.mac_base_type then t9.vem_docnum
                              when t10.cec_doctype = t0.mac_base_type then t10.cec_docnum
                              when t11.vdv_doctype = t0.mac_base_type then t11.vdv_docnum
                              when t12.cdc_doctype = t0.mac_base_type then t12.cdc_docnum
                              when t14.crc_doctype = t0.mac_base_type then t14.crc_docnum
                              else t0.mac_doc_num
                          end numero
                      from tmac t0
                      inner join mac1 t1 on t0.mac_trans_id = t1.ac1_trans_id
                      left join dacc t2 on t1.ac1_account = t2.acc_code
                      left join dcfc t3 on t0.mac_base_entry = t3.cfc_docentry and t0.mac_base_type = t3.cfc_doctype
                      left join dcnd t4 on t0.mac_base_entry = t4.cnd_docentry and t0.mac_base_type = t4.cnd_doctype
                      left join dcnc t5 on t0.mac_base_entry = t5.cnc_docentry and t0.mac_base_type = t5.cnc_doctype
                      left join dvfv t6 on t0.mac_base_entry = t6.dvf_docentry and t0.mac_base_type = t6.dvf_doctype
                      left join dvnd t7 on t0.mac_base_entry = t7.vnd_docentry and t0.mac_base_type = t7.vnd_doctype
                      left join dvnc t8 on t0.mac_base_entry = t8.vnc_docentry and t0.mac_base_type = t8.vnc_doctype
                      left join dvem t9 on t0.mac_base_entry = t9.vem_docentry and t0.mac_base_type = t9.vem_doctype
                      left join dcec t10 on t0.mac_base_entry = t10.cec_docentry and t0.mac_base_type = t10.cec_doctype
                      left join dvdv t11 on t0.mac_base_entry = t11.vdv_docentry and t0.mac_base_type = t11.vdv_doctype
                      left join dcdc t12 on t0.mac_base_entry = t12.cdc_docentry and t0.mac_base_type = t12.cdc_doctype
                      left join dmdt t13 on t0.mac_base_type = t13.mdt_doctype
                      left join dcrc t14 on t0.mac_base_type = t14.crc_doctype and t0.mac_base_entry = t14.crc_docentry
                      left join dmtx t16 on t1.ac1_taxid = t16.dmi_id
                      where t0.mac_trans_id = :mac_trans_id
                      order by t1.ac1_line_num ASC";

				$contenidoOC = $this->pedeo->queryTable($sqlcotizacion,array(':mac_trans_id'=>$Data));
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

				$consecutivo = '';

				// if($contenidoOC[0]['pgs_mpfn'] == 1){
					$consecutivo = $contenidoOC[0]['mac_doc_num'];
				// }else{
					$consecutivo = $contenidoOC[0]['mac_doc_num'];
				// }


				$totaldetalle = '';
				$totaldeb = 0;
				$totalcre = 0;
				$totaldebsys = 0;
				$totalcresys = 0;
				foreach ($contenidoOC as $key => $value) {
					// code...
					$detalle = '<td>'.$value['ac1_line_num'].'</td>
											<td>'.$value['ct'].'</td>
											<td>'.$value['ac1_account'].'</td>
											<td>'.$value['acc_name'].'</td>
											<td>'.$value['impuesto'].'</td>
											<td>'.$value['ac1_uncode'].'</td>
                      <td>'.$value['ac1_debit'].'</td>
                      <td>'.$value['ac1_credit'].'</td>
                      <td>'.$value['ac1_debit_sys'].'</td>
                      <td>'.$value['ac1_credit_sys'].'</td>
                      <td>'.$value['moneda'].'</td>';
				 $totaldetalle = $totaldetalle.'<tr>'.$detalle.'</tr>';

				 $totaldeb = $totaldeb + ($value['ac1_debit']);
				 $totalcre = $totalcre + ($value['ac1_credit']);
				 $totaldebsys = $totaldebsys + ($value['ac1_debit_sys']);
				 $totalcresys = $totalcresys + ($value['ac1_credit_sys']);

				 $cuerpo = '
				 						<tr>
										<th>&nbsp;</th>
				 						<th>&nbsp;</th>
										<th>&nbsp;</th>
										<th>&nbsp;</th>
										<th>&nbsp;</th>
										<th><b>total:</b></th>
										<th><b>'.$totaldeb.'</b></th>
										<th><b>'.$totalcre.'</b></th>
										<th><b>'.$totaldebsys.'</b></th>
										<th><b>'.$totalcresys.'</b></th>
										</tr>';

				}


        $header = '
				<table width="100%" style="text-align: left;">
        <tr>
            <th style="text-align: left;"><img src="/var/www/html/'.$company[0]['company'].'/'.$empresa[0]['pge_logo'].'" width ="100" height ="40"></img></th>
            <th>
                <p><b>Andino Pneus de Venezuela, C.A.</b></p>
                <p><b>Rif: J-00328174</b></p>
                <p><b>Carretera Nacional Guacara-Los Guayos, Fabrica de Cauchos.</b></p>
                <p><b>Guaraca, Estados Carabobo, Venezuela</b></p>

            </th>
            <th>
                <p><b>asiento contable</b></p>
            </th>
        </tr>

</table>
        ';

				$footer = '
        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th  width="33%">Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
            </tr>
        </table>';


        $html = '

				<table  width="100%" font-family: serif>
				<tr>
					<td><b># asiento:</b> <span>'.$contenidoOC[0]['mac_doc_num'].'</span></p></td>
					<td></td>
					<td><b>fecha contabilizacion:</b> <span>'.$contenidoOC[0]['mac_doc_date'].'</span></p></td>
				</tr>
				<tr>
					<td><b>EJERCICIO:</b> <span>'.$contenidoOC[0]['ejercicio'].'</span></p></td>
					<td></td>
					<td><b>PERIODO:</b> <span>'.$contenidoOC[0]['periodo'].'</span></p></td>
				</tr>
				<tr>
					<td><b>fecha documento:</b> <span>'.$contenidoOC[0]['mac_legal_date'].'</span></p></td>
					<td></td>
					<td><b>ref1:</b> <span>'.$contenidoOC[0]['mac_ref1'].'</span></p></td>
				</tr>
				<tr>
					<td><b>ref2:</b> <span>'.$contenidoOC[0]['mac_ref2'].'</span></p></td>
					<td></td>
					<td><b>ref3:</b> <span>'.$contenidoOC[0]['mac_ref3'].'</span></p></td>
				</tr>
				<tr>
					<td><b>moneda:</b> <span>'.$contenidoOC[0]['moneda_cab'].'</span></p></td>
					<td></td>
					<td><b>doc origen:</b> <span>'.$contenidoOC[0]['numero'].'</span></p></td>
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
          <th><b>#</b></th>
          <th><b>CT</b></th>
          <th><b>Cuneta</b></th>
          <th><b>Nombre Cuenta</b></th>
          <th><b>Impuesto</b></th>
          <th><b>Centro Costos</b></th>
          <th><b>Debito</b></th>
          <th><b>Credito</b></th>
					<th><b>Deb Sys</b></th>
					<th><b>Cre Sys</b></th>
					<th><b>Moneda</b></th>
        </tr>
      	'.$totaldetalle.$cuerpo.'
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
				<br>
				<br>
				</html>';

        $stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');

        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);


        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);

				$filename = 'AS_'.$contenidoOC[0]['mac_doc_num'].'.pdf';
        $mpdf->Output($filename, 'D');


				header('Content-type: application/force-download');
				header('Content-Disposition: attachment; filename='.$filename);


	}




}
