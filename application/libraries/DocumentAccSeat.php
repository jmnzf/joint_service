<?php
defined('BASEPATH') OR exit('No direct script access allowed');
use Luecano\NumeroALetras\NumeroALetras;

class DocumentAccSeat {

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
    public function format($Data,$type_mpdf = "D"){

		$formatter = new NumeroALetras();
		$DECI_MALES =  $this->ci->generic->getDecimals();

		// $mpdf = new \Mpdf\Mpdf(['setAutoBottomMargin' => 'stretch','setAutoTopMargin' => 'stretch']);
        $mpdf = new \Mpdf\Mpdf(['setAutoBottomMargin' => 'stretch','setAutoTopMargin' => 'stretch','default_font' => 'dejavusans']);
		//RUTA DE CARPETA EMPRESA
        $company = $this->ci->pedeo->queryTable("SELECT main_folder company FROM PARAMS",array());

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
		$sqlEmpresa = "SELECT pge_id, pge_name_soc, pge_small_name, pge_add_soc, pge_state_soc, pge_city_soc,
		pge_cou_soc, CONCAT(pge_id_type,' ',pge_id_soc) AS pge_id_type , pge_web_site, pge_logo,
		CONCAT(pge_phone1,' ',pge_phone2,' ',pge_cel) AS pge_phone1, pge_branch, pge_mail,
		pge_curr_first, pge_curr_sys, pge_cou_bank, pge_bank_def,pge_bank_acct, pge_acc_type,pge_id_soc,pge_phone2,pge_page_social
		FROM pgem WHERE pge_id = :pge_id";

		$empresa = $this->ci->pedeo->queryTable($sqlEmpresa, array(':pge_id' => $Data['business']));

		if(!isset($empresa[0])){
			$respuesta = array(
				'error' => true,
		        'data'  => $empresa,
	       		'mensaje' =>'no esta registrada la información de la empresa'
			);

	         $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	        return;
		}

		//CONSULTA PARA OBTENER NOMBRE DE DOCUMENTO
		$sql = "SELECT DISTINCT
					t0.mac_trans_id as trans_id,
					t0.mac_doc_num as doc_num,
					t0.mac_doc_date as doc_date,
					t0.mac_legal_date as legal_date,
					'' cal_imp,
					t0.mac_ref1 as ref1,
					t0.mac_ref2 as ref2,
					t0.mac_ref3 as ref3,
					'BS o USD' moneda_cab,
					extract(year from t0.mac_doc_date) ejercicio,
					extract(month from t0.mac_doc_date) periodo,
					t1.ac1_line_num as line_num,
					case
					when (t1.ac1_debit > 0 or t1.ac1_debit_sys > 0)
					then 'DB'
					when (t1.ac1_credit > 0 or t1.ac1_credit_sys > 0)
					then 'CR'
					end ct,
					t1.ac1_account as account,
					t2.acc_name,
					coalesce(concat(t16.dmi_code,' - ',t16.dmi_name_tax), '-') impuesto,
					coalesce(concat(t17.mrt_code,' - ',t17.mrt_name), '-') retencion,
					case
					when t3.cfc_doctype = t0.mac_base_type then (select aa.fc1_costcode from cfc1 aa where aa.fc1_docentry =
					t3.cfc_docentry)
					when t4.cnd_doctype = t0.mac_base_type then (select aa.nd1_costcode from cnd1 aa where aa.nd1_docentry =
					t4.cnd_docentry)
					when t5.cnc_doctype = t0.mac_base_type then (select aa.nc1_costcode from cnc1 aa where aa.nc1_docentry = t5.cnc_docentry
					)
					when t6.dvf_doctype = t0.mac_base_type then (select aa.fv1_costcode from vfv1 aa where aa.fv1_docentry =
					t6.dvf_docentry)
					when t7.vnd_doctype = t0.mac_base_type then (select aa.nd1_costcode from vnd1 aa where aa.nd1_docentry =
					t7.vnd_docentry)
					when t8.vnc_doctype = t0.mac_base_type then (select aa.nc1_costcode from vnc1 aa where aa.nc1_docentry =
					t8.vnc_docentry)
					when t9.vem_doctype = t0.mac_base_type then (select aa.em1_costcode from vem1 aa where aa.em1_docentry =
					t9.vem_docentry)
					when t10.cec_doctype = t0.mac_base_type then (select aa.ec1_costcode from cec1 aa where aa.ec1_docentry =
					t10.cec_docentry )
					when t11.vdv_doctype = t0.mac_base_type then (select aa.dv1_costcode from vdv1 aa where aa.dv1_docentry =
					t11.vdv_docentry)
					when t12.cdc_doctype = t0.mac_base_type then (select aa.dc1_costcode from cdc1 aa where aa.dc1_docentry =
					t12.cdc_docentry)
					else t1.ac1_uncode
					end uncode,
					t1.ac1_debit,
					t1.ac1_credit,
					t1.ac1_debit_sys,
					t1.ac1_credit_sys,
					t1.ac1_base_tax,
					t1.ac1_taxid,
					t1.ac1_isrti,
					t1.ac1_prc_code,
					t1.ac1_prj_code,
					case
					when t3.cfc_doctype = t0.mac_base_type then t3.cfc_currency
					when t4.cnd_doctype = t0.mac_base_type then t4.cnd_currency
					when t5.cnc_doctype = t0.mac_base_type then t5.cnc_currency
					when t6.dvf_doctype = t0.mac_base_type then t6.dvf_currency
					when t7.vnd_doctype = t0.mac_base_type then t7.vnd_currency
					when t8.vnc_doctype = t0.mac_base_type then t8.vnc_currency
					when t9.vem_doctype = t0.mac_base_type then t9.vem_currency
					when t10.cec_doctype = t0.mac_base_type then t10.cec_currency
					when t11.vdv_doctype = t0.mac_base_type then t11.vdv_currency
					when t12.cdc_doctype = t0.mac_base_type then t12.cdc_currency
					when t14.crc_doctype = t0.mac_base_type then t14.crc_currency
					else t0.mac_currency
					end moneda,
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
					end numero,
					t18.dcc_prc_code
					from tmac t0
					LEFT join mac1 t1 on t0.mac_trans_id = t1.ac1_trans_id
					left join dacc t2 on t1.ac1_account = t2.acc_code
					left join dcfc t3 on t0.mac_base_entry = t3.cfc_docentry and t0.mac_base_type = t3.cfc_doctype and t1.business = t3.business
					left join dcnd t4 on t0.mac_base_entry = t4.cnd_docentry and t0.mac_base_type = t4.cnd_doctype and t1.business = t4.business
					left join dcnc t5 on t0.mac_base_entry = t5.cnc_docentry and t0.mac_base_type = t5.cnc_doctype and t1.business = t5.business
					left join dvfv t6 on t0.mac_base_entry = t6.dvf_docentry and t0.mac_base_type = t6.dvf_doctype and t1.business = t6.business
					left join dvnd t7 on t0.mac_base_entry = t7.vnd_docentry and t0.mac_base_type = t7.vnd_doctype and t1.business = t7.business
					left join dvnc t8 on t0.mac_base_entry = t8.vnc_docentry and t0.mac_base_type = t8.vnc_doctype and t1.business = t8.business
					left join dvem t9 on t0.mac_base_entry = t9.vem_docentry and t0.mac_base_type = t9.vem_doctype and t1.business = t9.business
					left join dcec t10 on t0.mac_base_entry = t10.cec_docentry and t0.mac_base_type = t10.cec_doctype and t1.business = t10.business
					left join dvdv t11 on t0.mac_base_entry = t11.vdv_docentry and t0.mac_base_type = t11.vdv_doctype and t1.business = t11.business
					left join dcdc t12 on t0.mac_base_entry = t12.cdc_docentry and t0.mac_base_type = t12.cdc_doctype and t1.business = t12.business
					left join dmdt t13 on t0.mac_base_type = t13.mdt_doctype
					left join dcrc t14 on t0.mac_base_type = t14.crc_doctype and t0.mac_base_entry = t14.crc_docentry
					left join dmtx t16 on t1.ac1_taxid = t16.dmi_code
					Left join dmrt t17 on t1.ac1_codret = t17.mrt_id::text
					Left join dmcc t18 on t1.ac1_prc_code = t18.dcc_prc_code
					where t0.mac_trans_id = :mac_trans_id and t1.business = :business";

				
				//REEMPLAZAR PARAMETROS DE CONSULTA

				$sql = str_replace('{table}','tmac',$sql);//TABLA CABECERA
				$sql = str_replace('{table1}','mac1',$sql);//TABLA DETALLE
				$sql = str_replace('{prefijo}','mac',$sql);//PREFIJO CABECERA
				$sql = str_replace('{prefijo1}','ac1',$sql);//PREFIJO DETALLE

				$resSql = $this->ci->pedeo->queryTable($sql,array(
                    ':mac_trans_id'=>$Data['docentry'], 
                    ':business' => $Data['business']));

				if(!isset($resSql[0])){
						$respuesta = array(
							 'error' => true,
							 'data'  => $resSql,
							 'mensaje' =>'no se encontro el documento'
						);

						return $respuesta;
				}

				$consecutivo = '';

				// if($resSql[0]['pgs_mpfn'] == 1){
					$consecutivo = $resSql[0]['trans_id'];
				// }else{
					$consecutivo = $resSql[0]['trans_id'];
				// }


				$totaldetalle = '';
				$sumDebito = 0;
				$sumCredito = 0;
				foreach ($resSql as $key => $value) {
					// code...<td>'.$value['monedadocumento']." ".number_format($value['valortotall'], $DECI_MALES, ',', '.').'</td>'
					$detalle = '<td style="text-align: left; padding-left: 10px; padding-right: 10px;padding-top: 10px;border-bottom: dotted">'.$value['line_num'].'</td>
											<td style="text-align: left; padding-left: 10px; padding-right: 10px;padding-top: 10px;border-bottom: dotted">'.$value['account'].'</td>
											<td style="text-align: left; padding-left: 10px; padding-right: 10px;padding-top: 10px;border-bottom: dotted">'.$value['acc_name'].'</td>
											<td style="text-align: center; padding-left: 10px; padding-right: 10px;padding-top: 10px;border-bottom: dotted">'.number_format($value['ac1_debit'],$DECI_MALES, ',', '.').'</td>
											<td style="text-align: center; padding-left: 10px; padding-right: 10px;padding-top: 10px;border-bottom: dotted">'.number_format($value['ac1_credit'],$DECI_MALES, ',', '.').'</td>
											<td style="text-align: center; padding-left: 10px; padding-right: 10px;padding-top: 10px;border-bottom: dotted">'.number_format($value['ac1_debit_sys'],$DECI_MALES, ',', '.').'</td>
											<td style="text-align: center; padding-left: 10px; padding-right: 10px;padding-top: 10px;border-bottom: dotted">'.number_format($value['ac1_credit_sys'],$DECI_MALES, ',', '.').'</td>
											<td style="text-align: center; padding-left: 10px; padding-right: 10px;padding-top: 10px;border-bottom: dotted">'.$value['ac1_taxid'].'</td>
											<td style="text-align: center; padding-left: 10px; padding-right: 10px;padding-top: 10px;border-bottom: dotted">'.number_format(($value['ac1_base_tax'] * $value['ac1_isrti']) / 100,$DECI_MALES, ',', '.').'</td>
											<td style="text-align: center; padding-left: 10px; padding-right: 10px;padding-top: 10px;border-bottom: dotted">'.$value['ac1_prj_code'].'</td>
											<td style="text-align: center; padding-left: 10px; padding-right: 10px;padding-top: 10px;border-bottom: dotted">'.$value['ac1_prc_code'].'</td>
											<td style="text-align: center; padding-left: 10px; padding-right: 10px;padding-top: 10px;border-bottom: dotted">'.$value['uncode'].'</td>
											';
				 $totaldetalle = $totaldetalle.'<tr>'.$detalle.'</tr>';
				 $sumDebito += $value['ac1_debit'];
				 $sumCredito += $value['ac1_credit'];
				}


        $header = '
				<table width="100%" style="text-align: left;">
        			<tr>
					<th style="text-align: left;" width ="50">
						<img src="/var/www/html/'.$company[0]['company'].'/'.$empresa[0]['pge_logo'].'" 
						width ="185" height ="120"></img>
					</th>
            			<th>
							<p><b>'.$empresa[0]['pge_name_soc'].'</b></p>
							<p><b>'.$empresa[0]['pge_id_type'].'</b></p>
							<p><b>'.$empresa[0]['pge_add_soc'].'</b></p>
						</th>
					/tr>
				</table>';

		$footer = '
        	<table width="100%" style="vertical-align: bottom; font-family: serif; font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            	<tr>
                	<th  width="33%" style="font-size: 8px;"> Documento generado por <span style="color: #3FA9F5; font-weight: bolder;"> JOINTERP </span> para: '.$empresa[0]['pge_small_name'].'. Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
            	</tr>
        	</table>';


        $html = '
			<table  width="100%" >
        		<tr>
		            <th style="text-align: left;">
						<p><b>'.$empresa[0]['pge_small_name'].'</b></p>
						<p>'.$empresa[0]['pge_add_soc'].'</p>
						<p>'.$empresa[0]['pge_id_type'].'</p>
						<p>'.$empresa[0]['pge_state_soc'].'</p>
						<p>TELEFONO: '.$empresa[0]['pge_phone2'].'</p>
						<p>website: '.$empresa[0]['pge_web_site'].'</p>
						<p>Instagram: '.$empresa[0]['pge_page_social'].'</p>
            		</th>
            		<th style="text-align: right;">
						<p><b># ASIENTO: </b></p>
					</th>
					<th style="text-align: left;">
                		<p >'.$resSql[0]['trans_id'].'</p>
					</th>
					<th style="text-align: right;">
						<th style="text-align: right;">
							<p><b>FECHA DE ASIENTO: </b></p>
						</th>
					<th style="text-align: left;">
						<p>'.$this->ci->dateformat->Date($resSql[0]['legal_date']).'</p>
            		</th>
        		</tr>
        	</table>
			<table width="100%" style="vertical-align: bottom; font-family: serif;font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            	<tr>
                	<th class="fondo">
                    	<p></p>
                	</th>
            	</tr>
        	</table>
			<table  width="100%" font-family: serif>
				</table>
				<table  width="100%" font-family: serif>
				<tr>
					<th style="text-align: center;"><b>TIPO DOCUMENTO:</b><span>'.$resSql[0]['mdt_docname'].'</span></p></th>
					<th style="text-align: center;"><b>NUMERO DOCUMENTO:</b><span>'.$resSql[0]['numero'].'</span></p></th>
				</tr>
				<tr>
					<th style="text-align: center;"><b>MONEDA DOC:</b><span>'.$resSql[0]['moneda'].'</span></p></th>
					<th style="text-align: center;"><b>FECHA DOCUMENTO:</b><span>'.$resSql[0]['doc_date'].'</span></p></th>
				</tr>
				<tr>
					<th style="text-align: center;"><b>PERIODO:</b><span>'.$resSql[0]['periodo'].'</span></p></th>
					<th style="text-align: center;"><b>EJERCICIO:</b><span>'.$resSql[0]['ejercicio'].'</span></p></th>
				</tr>
			</table>
			<table width="100%" style="vertical-align: bottom; font-family: serif;font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            	<tr>
                	<th class="fondo">
                    	<p></p>
                	</th>
            	</tr>
        	</table>
        	<table class="borde" style="width:100%">
				<tr>
					<th><b>#</b></th>
					<th><b>CUENTA</b></th>
					<th><b>NOMBRE CUENTA</b></th>
					<th><b>D</b></th>
					<th><b>C</b></th>
					<th><b>D.SYS</b></th>
					<th><b>C.SYS</b></th>
					<th><b>COD.IMP </b></th>
					<th><b>V.IMP</b></th>
					<th><b>PROY</b></th>
					<th><b>CC</b></th>
					<th><b>UN</b></th>
				</tr>
				'.$totaldetalle.'
       	 	</table>			
        	<br>
			<table width="100%" style="vertical-align: bottom; font-family: serif;font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
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
		$mpdf->SetDefaultBodyCSS('background', "url('/var/www/html/".$company[0]['company']."/assets/img/W-background.png')");
        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);

		$filename = 'ASIENTO_'.$resSql[0]['trans_id'].'.pdf';
        if($type_mpdf == "D"){
			$mpdf->Output($filename,$type_mpdf);
			header('Content-type: application/force-download');
			header('Content-Disposition: attachment; filename='.$filename);
		}else if($type_mpdf == "F"){
			$mpdf->Output(ROUTE_LOCAL . $filename, \Mpdf\Output\Destination::FILE);
			return array(
				'file' => ROUTE_LOCAL.$filename,
				'data' => array(
					'docnum' => $consecutivo,
					'cardname' => $resSql[0]['cliente'],
					'total' => $resSql[0]['monedadocumento']." ".number_format($resSql[0]['totaldoc'], $DECI_MALES, ',', '.'),
					'type' => $Type
				)
			);
		}
	}
}