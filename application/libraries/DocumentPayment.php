<?php
defined('BASEPATH') OR exit('No direct script access allowed');
use Luecano\NumeroALetras\NumeroALetras;

class DocumentPayment {

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

		$Type = "";
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
		pge_cou_soc, CONCAT(bti_name,' ',pge_id_soc) AS pge_id_type , pge_web_site, pge_logo,
		CONCAT(pge_phone1,' ',pge_phone2,' ',pge_cel) AS pge_phone1, pge_branch, pge_mail,
		pge_curr_first, pge_curr_sys, pge_cou_bank, pge_bank_def,pge_bank_acct, pge_acc_type,pge_id_soc,pge_phone2,pge_page_social,
		concat(main_folder,'/',pge_logo) AS \"companyLogo\"
		FROM pgem 
		inner join tbti on pge_id_type = tbti.bti_id 
		left join params  on 1=1
		WHERE pge_id = :pge_id";

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
		$sql = "SELECT
                    ROW_NUMBER() OVER( partition by t1.{prefijo1}_docnum ORDER BY t1.{prefijo1}_docnum) as linea,
                    t0.{prefijo}_cardcode nit,
                    t0.{prefijo}_cardname as name,
                    t0.{prefijo}_docdate fecha,
                    t0.{prefijo}_datetransfer as fecha_pago,
                    t0.{prefijo}_docnum as numerodocumento,
                    t0.{prefijo}_reftransfer,
                    0 cuenta_bene,
                    t1.{prefijo1}_docnum ,
                    t1.{prefijo1}_doctype,
                    t0.{prefijo}_comments,
                    t0.{prefijo}_doctype,
                    t0.{prefijo}_docentry,
					abs(t0.{prefijo}_doctotal) as total,
					tt.mdt_docname,
					coalesce(t2.{prefijo2}_tax_control_num,'') as refFiscal,
                    case
                            when t6.{prefijo}_doctype = t1.{prefijo1}_doctype then 'Anticipo'
                            else t5.mdt_docname
                        end tipo,
                    case
                        when t2.{prefijo2}_doctype = t1.{prefijo1}_doctype then t2.{prefijo2}_docnum
                        when t3.{prefijo3}_doctype = t1.{prefijo1}_doctype then t3.{prefijo3}_docnum
                        when t4.{prefijo4}_doctype =  t1.{prefijo1}_doctype then t4.{prefijo4}_docnum
                        when t6.{prefijo}_doctype = t1.{prefijo1}_doctype then t6.{prefijo}_docnum
						when t7.{prefijo5}_doctype = t1.{prefijo1}_doctype then t7.{prefijo5}_docnum
                    end docnum,
                    case
                        when t2.{prefijo2}_doctype = t1.{prefijo1}_doctype then t2.{prefijo2}_docdate
                        when t3.{prefijo3}_doctype = t1.{prefijo1}_doctype then t3.{prefijo3}_docdate
                        when t4.{prefijo4}_doctype =  t1.{prefijo1}_doctype then t4.{prefijo4}_docdate
                        when t6.{prefijo}_doctype = t1.{prefijo1}_doctype then t6.{prefijo}_docdate
						when t7.{prefijo5}_doctype = t1.{prefijo1}_doctype then t7.{prefijo5}_docdate
                    end docdate,
                    case
                        when t2.{prefijo2}_doctype = t1.{prefijo1}_doctype then t2.{prefijo2}_comment
                        when t3.{prefijo3}_doctype = t1.{prefijo1}_doctype then t3.{prefijo3}_comment
                        when t4.{prefijo4}_doctype =  t1.{prefijo1}_doctype then t4.{prefijo4}_comment
                        when t6.{prefijo}_doctype = t1.{prefijo1}_doctype then t6.{prefijo}_comments
						when t7.{prefijo5}_doctype = t1.{prefijo1}_doctype then ''
                    end comentario,
                    case
                        when t2.{prefijo2}_doctype = t1.{prefijo1}_doctype then t2.{prefijo2}_baseamnt
                        when t3.{prefijo3}_doctype = t1.{prefijo1}_doctype then t3.{prefijo3}_baseamnt
                        when t4.{prefijo4}_doctype =  t1.{prefijo1}_doctype then t4.{prefijo4}_baseamnt
                        when t6.{prefijo}_doctype = t1.{prefijo1}_doctype then 0
						when t7.{prefijo5}_doctype = t1.{prefijo1}_doctype then t7.{prefijo5}_baseamnt
                    end base,
                    case
                        when t2.{prefijo2}_doctype = t1.{prefijo1}_doctype then t2.{prefijo2}_taxtotal
                        when t3.{prefijo3}_doctype = t1.{prefijo1}_doctype then t3.{prefijo3}_taxtotal
                        when t4.{prefijo4}_doctype =  t1.{prefijo1}_doctype then t4.{prefijo4}_taxtotal
                        when t6.{prefijo}_doctype = t1.{prefijo1}_doctype then 0
						when t7.{prefijo5}_doctype = t1.{prefijo1}_doctype then t7.{prefijo5}_taxtotal
                    end iva,
                    coalesce((select sum(a.crt_basert) from fcrt a inner join dmrt b on a.crt_typert = b.mrt_id where b.mrt_tasa = 0
                        and a.crt_baseentry = t2.{prefijo2}_docentry and a.crt_basetype = t2.{prefijo2}_doctype),0) exento,
                    case
                        when t2.{prefijo2}_doctype = t1.{prefijo1}_doctype then t2.{prefijo2}_baseamnt + t2.{prefijo2}_taxtotal
                        when t3.{prefijo3}_doctype = t1.{prefijo1}_doctype then t3.{prefijo3}_baseamnt + t3.{prefijo3}_taxtotal
                        when t4.{prefijo4}_doctype =  t1.{prefijo1}_doctype then t4.{prefijo4}_baseamnt + t4.{prefijo4}_taxtotal
                        when t6.{prefijo}_doctype = t1.{prefijo1}_doctype then 0
                    end neto,
                    coalesce((select sum(a.crt_basert) from fcrt a
                    where a.crt_baseentry = t2.{prefijo2}_docentry and a.crt_basetype = t2.{prefijo2}_doctype and a.crt_type = 3),0)  base_ret_iva,
                        coalesce((select sum(a.crt_totalrt) from fcrt a
                        where a.crt_baseentry = t2.{prefijo2}_docentry and a.crt_basetype = t2.{prefijo2}_doctype and a.crt_type = 3),0)  crt_totalrtiva,
                    (select a.mrt_tasa from dmrt a inner join fcrt b on a.mrt_id = b.crt_typert
                    where b.crt_baseentry = t2.{prefijo2}_docentry and b.crt_basetype = t2.{prefijo2}_doctype and b.crt_type = 3 ) porcentaje_ret_iva,
                    coalesce((select sum(a.crt_basert) from fcrt a
                    where a.crt_baseentry = t2.{prefijo2}_docentry and a.crt_basetype = t2.{prefijo2}_doctype and a.crt_type = 2),0) base_ret_islr,
                        coalesce((select sum(a.crt_totalrt) from fcrt a
                        where a.crt_baseentry = t2.{prefijo2}_docentry and a.crt_basetype = t2.{prefijo2}_doctype and a.crt_type = 2),0) crt_totalrtislr,
                    (select a.mrt_tasa from dmrt a inner join fcrt b on a.mrt_id = b.crt_typert
                    where b.crt_baseentry = t2.{prefijo2}_docentry and b.crt_basetype = t2.{prefijo2}_doctype and b.crt_type = 2) porcentaje_ret_islr,
                    coalesce((select sum(a.crt_basert) from fcrt a
                    where a.crt_baseentry = t2.{prefijo2}_docentry and a.crt_basetype = t2.{prefijo2}_doctype and a.crt_type = 1),0) base_ret_ipm,
                        coalesce((select sum(a.crt_totalrt) from fcrt a
                        where a.crt_baseentry = t2.{prefijo2}_docentry and a.crt_basetype = t2.{prefijo2}_doctype and a.crt_type = 1),0) crt_totalrtipm,
                    (select a.mrt_tasa from dmrt a inner join fcrt b on a.mrt_id = b.crt_typert
                    where b.crt_baseentry = t2.{prefijo2}_docentry and b.crt_basetype = t2.{prefijo2}_doctype and b.crt_type = 1) porcentaje_ret_ipm,
                    case
                        when t2.{prefijo2}_doctype = t1.{prefijo1}_doctype then t2.{prefijo2}_doctotal
                        when t3.{prefijo3}_doctype = t1.{prefijo1}_doctype then t3.{prefijo3}_doctotal
                        when t4.{prefijo4}_doctype =  t1.{prefijo1}_doctype then t4.{prefijo4}_doctotal
                        when t6.{prefijo}_doctype = t1.{prefijo1}_doctype then t6.{prefijo}_doctotal * -1
						when t7.{prefijo5}_doctype = t1.{prefijo1}_doctype then t7.{prefijo5}_doctotal
                    end total,
                        case
                            when t6.{prefijo}_doctype = t1.{prefijo1}_doctype then t1.{prefijo1}_vlrpaid * -1
                            else t1.{prefijo1}_vlrpaid
                        end pe1_vlrpaid,
                    t0.{prefijo}_memo,
                    get_tax_currency(t0.{prefijo}_currency,t0.{prefijo}_docdate) tasa
                from {table} t0
                inner join {table1} t1 on t0.{prefijo}_docentry = t1.{prefijo1}_docnum
                left join {table2} t2 on t1.{prefijo1}_docentry = t2.{prefijo2}_docentry and t1.{prefijo1}_doctype = t2.{prefijo2}_doctype
                left join {table3} t3 on t1.{prefijo1}_docentry = t3.{prefijo3}_docentry and t1.{prefijo1}_doctype = t3.{prefijo3}_doctype
                left join {table4} t4 on t1.{prefijo1}_docentry = t4.{prefijo4}_docentry and t1.{prefijo1}_doctype = t4.{prefijo4}_doctype
                left join {table} t6 on t1.{prefijo1}_docnum = t6.{prefijo}_docentry and t1.{prefijo1}_doctype = t6.{prefijo}_doctype
				left join {table5} t7 on t1.{prefijo1}_docentry = t7.{prefijo5}_docentry and t1.{prefijo1}_doctype = t7.{prefijo5}_doctype
                inner join dmdt t5 on t1.{prefijo1}_doctype = t5.mdt_doctype
				inner join dmdt tt on t0.{prefijo}_doctype = tt.mdt_doctype
                where t0.{prefijo}_docentry = :docentry and t0.business = :business";

				
				//REEMPLAZAR PARAMETROS DE CONSULTA
				if($Data['doctype'] == 19){
					$sql = str_replace('{table}','gbpe',$sql);//TABLA CABECERA
					$sql = str_replace('{table1}','bpe1',$sql);//TABLA DETALLE
					$sql = str_replace('{table2}','dcfc',$sql);//TABLA FACTURA
					$sql = str_replace('{table3}','dcnc',$sql);//TABLA NOTA CREDITO
					$sql = str_replace('{table4}','dcnd',$sql);//TABLA NOTA DEBITO
					$sql = str_replace('{table5}','dcsa',$sql);//TABLA SOLICITUD ANTICIPO PROVEEDOR
					$sql = str_replace('{prefijo}','bpe',$sql);//PREFIJO CABECERA
					$sql = str_replace('{prefijo1}','pe1',$sql);//PREFIJO DETALLE
					$sql = str_replace('{prefijo2}','cfc',$sql);//PREFIJO FACTURA
					$sql = str_replace('{prefijo3}','cnc',$sql);//PREFIJO NOTA CREDITO
					$sql = str_replace('{prefijo4}','cnd',$sql);//PREFIJO NOTA DEBITO
					$sql = str_replace('{prefijo5}','csa',$sql);//TABLA SOLICITUD ANTICIPO PROVEEDOR
					//ASGINAR DATOS A VARIABLES
					$Type = "PROVEEDOR";
				}else if($Data['doctype'] == 20){
					$sql = str_replace('{table}','gbpr',$sql);//TABLA CABECERA
					$sql = str_replace('{table1}','bpr1',$sql);//TABLA DETALLE
					$sql = str_replace('{table2}','dvfv',$sql);//TABLA FACTURA
					$sql = str_replace('{table3}','dvnc',$sql);//TABLA NOTA CREDITO
					$sql = str_replace('{table4}','dvnd',$sql);//TABLA NOTA DEBITO
					$sql = str_replace('{table5}','dcsa',$sql);//TABLA SOLICITUD ANTICIPO PROVEEDOR
					$sql = str_replace('{prefijo}','bpr',$sql);//PREFIJO CABECERA
					$sql = str_replace('{prefijo1}','pr1',$sql);//PREFIJO DETALLE
					$sql = str_replace('{prefijo2}','dvf',$sql);//PREFIJO FACTURA
					$sql = str_replace('{prefijo3}','vnc',$sql);//PREFIJO NOTA CREDITO
					$sql = str_replace('{prefijo4}','vnd',$sql);//PREFIJO NOTA DEBITO
					$sql = str_replace('{prefijo5}','csa',$sql);//TABLA SOLICITUD ANTICIPO PROVEEDOR
					//ASGINAR DATOS A VARIABLES
					$Type = "CLIENTE";
				}
				
				$resSql = $this->ci->pedeo->queryTable($sql,array(
                    ':docentry'=>$Data['docentry'], 
                    ':business' => $Data['business']));
				
				if(!isset($resSql[0])){
						$respuesta = array(
							 'error' => true,
							 'data'  => $resSql,
							 'mensaje' =>'no se encontro el documento'
						);

						return $respuesta;
				}

				$total = is_numeric($resSql[0]['total']) ? abs($resSql[0]['total']) : 0;

				$consecutivo = '';

				// if($resSql[0]['pgs_mpfn'] == 1){
					$consecutivo = $resSql[0]['numerodocumento'];
				// }else{
					$consecutivo = $resSql[0]['numerodocumento'];
				// }


				$totaldetalle = '';
				foreach ($resSql as $key => $value) {
					// code...
					$docN = $value['docnum'];
					
					$detalle = '<td>'.$value['linea'].'</td>
											<td>'.$value['tipo'].'</td>
											<td>'.$docN.'</td>
											<td>'.$value['docdate'].'</td>
											<td>'.$value['comentario'].'</td>
											<td>'.$value['base'].'</td>
											<td>'.$value['iva'].'</td>
                                            <td>'.$value['neto'].'</td>
                                            <td>'.abs($value['total']).'</td>';
				 $totaldetalle = $totaldetalle.'<tr>'.$detalle.'</tr>';
				}

				
				//$sections['top']['companyLogo'] = $company[0]['company'].'/'.$empresa[0]['pge_logo'];
				
				$header = '
				<table width="100%" style="text-align: left;" >
        			<tr>
            			<th style="text-align: left;" width ="50">
							<img class="logo_img" src="/var/www/html/'.$company[0]['company'].'/'.$empresa[0]['pge_logo'].'" 
							width ="185" height ="120"></img>
						</th>
            			<th>
							<p><b>'.$empresa[0]['pge_name_soc'].'</b></p>
							<p><b>'.$empresa[0]['pge_id_type'].'</b></p>
							<p><b>'.$empresa[0]['pge_add_soc'].'</b></p>
						</th>
					</tr>
				</table>';

		$footer = '
        	<table width="100%" style="vertical-align: bottom; font-family: serif; font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            	<tr>
                	<th  width="33%" style="font-size: 8px;" > Documento generado por <span style="color: orange; font-weight: bolder;"> Joint ERP  </span> para: '.$empresa[0]['pge_small_name'].'. Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
            	</tr>
        	</table>';
		


        $html = '
			<table  width="100%" >
        		<tr>
		            <th style="text-align: left;">
						<p><b>'.$empresa[0]['pge_small_name'].'</b></p>
						<p>'.$empresa[0]['pge_add_soc'].'</p>
						<p>'.$empresa[0]['pge_id_soc'].' / '.$empresa[0]['pge_id_type'].'</p>
						<p>'.$empresa[0]['pge_state_soc'].'</p>
						<p>TELEFONO:'.$empresa[0]['pge_phone1'].' / '.$empresa[0]['pge_phone2'].'</p>
						<p>website: '.$empresa[0]['pge_web_site'].'</p>
						<p>Instagram: '.$empresa[0]['pge_page_social'].'</p>
            		</th>
            		<th style="text-align: right;">
						<p><b>'.$resSql[0]['mdt_docname'].' #: </b></p>
					</th>
					<th style="text-align: left;">
                		<p >'.$resSql[0]['numerodocumento'].'</p>
					</th>
					<th style="text-align: right;">
						<th style="text-align: right;">
							<p><b>FECHA DE EMISIÓN: </b></p>
						</th>
					<th style="text-align: left;">
						<p>'.$this->ci->dateformat->Date($resSql[0]['fecha']).'</p>
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
				<tr>
					<th style="text-align: center;"><b>'.$Type.'</b><th>
				</tr>
				</table>
				<table  width="100%" font-family: serif>
				<tr>
					<th style="text-align: center;"><b>nit:</b><span>'.$resSql[0]['nit'].'</span></p></th>
					<th style="text-align: center;"><b>nombre '.$Type.':</b> <span>'.$resSql[0]['name'].'</span></p></th>
					<th style="text-align: center;"><b>fecha de pago:</b><span>'.$resSql[0]['fecha_pago'].'</span></p></th>
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
					<th><b>linea</b></th>
					<th><b>tipo</b></th>
					<th><b>numero</b></th>
					<th><b>fecha</b></th>
					<th><b>comentario</b></th>
					<th><b>base</b></th>
					<th><b>iva</b></th>
					<th><b>neto</b></th>
                    <th><b>total</b></th>
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

			<table  width="100%">
				<tr>
            		<td style="text-align: left;"><b>comentarios:</b><br><p>0</p></td>
        		</tr>
        	</table>
			<br>
			<br>
        	<table width="100%" style="vertical-align: bottom; font-family: serif; font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            	<tr>
                	<th style="text-align: left;" >
						<p><b>VALOR EN LETRAS:</b></p><br>
                    	<p>'.$formatter->toWords($total,$DECI_MALES).'</p>
                	</th>
            	</tr>
        	</table>
		</html>';

        $stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');

        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);

		$mpdf->SetDefaultBodyCSS('background', "url('/var/www/html/".$company[0]['company']."/assets/img/W-background.png')");
        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);

		$filename = 'PAGO_'.$resSql[0]['numerodocumento'].'.pdf';
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

	public function makeHtml($section, $data){

		$dataKeys = array_keys($data);	
		foreach ($dataKeys as $key => $value) {

			$section = str_replace("{".$value."}",$data[$value],$section);
		}
		return $section;

	}	

	public function getSections($sections){
		$resutl = array();
		foreach ($sections as $key => $value) {
			$resutl[$value['pdf_section']] = $value["pdf_template"];
		}

		return $resutl;

	}
}