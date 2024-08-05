<?php
defined('BASEPATH') OR exit('No direct script access allowed');
use Luecano\NumeroALetras\NumeroALetras;

class DocumentContract {

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
		pge_cou_soc, pge_id_soc AS pge_id_type , pge_web_site, pge_logo,
		CONCAT(pge_cel) AS pge_phone1, pge_branch, pge_mail,
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
		$sql = "SELECT
                CONCAT(t0.{prefijo}_CARDNAME,' ',T2.DMS_CARD_LAST_NAME) Cliente,
                t0.{prefijo}_CARDCODE Nit,
                CONCAT(T3.DMD_ADRESS,' ',T3.DMD_CITY) AS Direccion,
                T3.dmd_state_mm ciudad,
                t3.dmd_state estado,
                T4.DMC_PHONE1 Telefono,
                T4.DMC_EMAIL Email,
                t0.{prefijo}_DOCNUM,
                t0.{prefijo}_DOCNUM NumeroDocumento,
                t0.{prefijo}_DOCDATE FechaDocumento,
                t0.{prefijo}_DUEDATE FechaVenDocumento,
                t0.{prefijo}_duedev fechaentrga,
                t0.{prefijo}_enddate fechafin,
                t0.{prefijo}_signaturedate fechafirma,
                t0.{prefijo}_CURRENCY MonedaDocumento,
                T7.PGM_NAME_MONEDA NOMBREMONEDA,
                T5.MEV_NAMES Vendedor,
                '' MedioPago,
                '' CondPago,
                t1.{prefijo1}_ITEMCODE Referencia,
                t1.{prefijo1}_ITEMNAME descripcion,
                t1.{prefijo1}_WHSCODE Almacen,
                t1.{prefijo1}_UOM UM,
                t1.{prefijo1}_QUANTITY Cantidad,
                t1.{prefijo1}_PRICE VrUnit,
                t1.{prefijo1}_DISCOUNT PrcDes,
                t1.{prefijo1}_VATSUM IVAP,
                t1.{prefijo1}_LINETOTAL ValorTotalL,
                t0.{prefijo}_BASEAMNT base,
                t0.{prefijo}_DISCOUNT Descuento,
                (t0.{prefijo}_BASEAMNT - t0.{prefijo}_DISCOUNT) subtotal,
                t0.{prefijo}_TAXTOTAL Iva,
                t0.{prefijo}_DOCTOTAL TotalDoc,
                t0.{prefijo}_COMMENT Comentarios,
                t6.pgs_mde,
                t6.pgs_mpfn,
                T8.MPF_NAME cond_pago,
                t3.dmd_tonw lugar_entrega,
                t5.mev_names nombre_contacto,
                t5.mev_mail correo_contacto,
                t5.mev_phone telefono_contacto,
                case when coalesce(t4.dmc_name,'') != '' then concat(t4.dmc_name,' ',t4.dmc_last_name) else concat(t9.dmc_name,' ',t9.dmc_last_name) end nombre_contacto_p,
				coalesce(t4.dmc_email,t9.dmc_email ) correo_contacto_p,
				coalesce(t4.dmc_phone1,t9.dmc_phone1) telefono_contacto_p,
				coalesce(t4.dmc_cel,t9.dmc_cel) cel_contacto_p,
                t10.mdt_docname,
                t12.tpa_description tipoacuerdo,
                t13.cdp_description tipopago,
                t14.esc_description estado,
                t11.csn_probabilitypercentage porcentaje_cab,
                case coalesce(t11.csn_renewal,0) when 0 then 'NO' when 1 then 'SI' end as renovacion
                FROM {table} t0
                INNER JOIN {table1} T1 ON t0.{prefijo}_docentry = t1.{prefijo1}_docentry
                LEFT JOIN DMSN T2 ON t0.{prefijo}_cardcode = t2.dms_card_code
                LEFT JOIN DMSD T3 ON t2.dms_card_code = t3.dmd_card_code AND t3.dmd_ppal = 1
                LEFT JOIN DMSC T4 ON t0.{prefijo}_CONTACID = CAST(T4.DMC_ID AS VARCHAR)
                LEFT JOIN DMEV T5 ON t0.{prefijo}_SLPCODE = T5.MEV_ID
                LEFT JOIN PGDN T6 ON t0.{prefijo}_DOCTYPE = T6.PGS_ID_DOC_TYPE AND t0.{prefijo}_SERIES = T6.PGS_ID
                LEFT JOIN PGEC T7 ON t0.{prefijo}_CURRENCY = T7.PGM_SYMBOL
                LEFT JOIN DMPF T8 ON T2.DMS_PAY_TYPE = cast(T8.MPF_ID as varchar)
                LEFT JOIN dmsc t9 ON t0.{prefijo}_cardcode = t9.dmc_card_code and T9.dmc_ppal = 1
                INNER JOIN dmdt t10 ON t0.{prefijo}_doctype = t10.mdt_doctype
                LEFT JOIN dcsn t11 ON t0.{prefijo}_doctype = t11.csn_doctype AND t0.{prefijo}_docentry = t11.csn_docentry --DETALLE CABECERA
                LEFT JOIN ctpa t12 ON t11.csn_typeagreement = t12.tpa_id --TIPO DE ACUERDO
                LEFT JOIN ccdp t13 ON t11.csn_paymentcondition = t13.cdp_id --TIPO DE PAGO
                LEFT JOIN cesc t14 ON t11.csn_status = t14.esc_id --ESTADO CONTRATO
                WHERE t0.{prefijo}_docentry= :docentry {where} and t0.business = :business";

				//REEMPLAZAR TABLAS DEPENDIENTO EL TIPO DEL DOCUMENTO
				if($Data['doctype'] == 32){

					//REEMPLAZAR PARAMETROS DE CONSULTA
					$sql = str_replace('{table}','tcsn',$sql);//TABLA CABECERA
					$sql = str_replace('{table1}','csn1',$sql);//TABLA DETALLE
					$sql = str_replace('{prefijo}','csn',$sql);//PREFIJO CABECERA
					$sql = str_replace('{prefijo1}','sn1',$sql);//PREFIJO DETALLE
					$sql = str_replace('{where}',"and t2.dms_card_type = '1'",$sql);//WHERE PARA EL TIPO
					//ASIGNAR TIPO PROVEEDOR O CLIENTE
					$Type = "CLIENTE";
				}else if($Data['doctype'] == 38){

					//REEMPLAZAR PARAMETROS DE CONSULTA
					$sql = str_replace('{table}','tcsn',$sql);//TABLA CABECERA
					$sql = str_replace('{table1}','csn1',$sql);//TABLA DETALLE
					$sql = str_replace('{prefijo}','csn',$sql);//PREFIJO CABECERA
					$sql = str_replace('{prefijo1}','sn1',$sql);//PREFIJO DETALLE
					$sql = str_replace('{where}',"and t2.dms_card_type = '2'",$sql);//WHERE PARA EL TIPO
					//ASIGNAR TIPO PROVEEDOR O CLIENTE
					$Type = "PROVEEDOR";
				}
				// print_r($sql);exit;
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

				$consecutivo = '';

				if($resSql[0]['pgs_mpfn'] == 1){
					$consecutivo = $resSql[0]['numerodocumento'];
				}else{
					$consecutivo = $resSql[0]['numerodocumento'];
				}


				$totaldetalle = '';
				foreach ($resSql as $key => $value) {
					// code...
					$detalle = '<td style="text-align: center; padding-left: 10px; padding-right: 10px;padding-top: 10px;border-bottom: dotted">'.$value['referencia'].'</td>
											<td style="text-align: center; padding-left: 10px; padding-right: 10px;padding-top: 10px;border-bottom: dotted">'.$value['descripcion'].'</td>
											<td style="border-bottom: dotted;padding-top: 10px;">'.$value['monedadocumento']." ".number_format($value['vrunit'], $DECI_MALES, ',', '.').'</td>
											<td style="border-bottom: dotted;padding-top: 10px;">'.$value['cantidad'].'</td>
											<td style="border-bottom: dotted;padding-top: 10px;">'.$value['prcdes'].'%</td>
											<td style="border-bottom: dotted;padding-top: 10px;">'.$value['monedadocumento']." ".number_format($value['ivap'], $DECI_MALES, ',', '.').'</td>
											<td style="border-bottom: dotted;padding-top: 10px;">'.$value['monedadocumento']." ".number_format($value['valortotall'], $DECI_MALES, ',', '.').'</td>';
				 $totaldetalle = $totaldetalle.'<tr style="margin-bottom: 50px;">'.$detalle.'</tr>';
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
                	<th  width="33%" style="font-size: 8px;"> Documento generado por <span style="color: orange; font-weight: bolder;"> Joint ERP  </span> para: '.$empresa[0]['pge_small_name'].'. Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
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
						<p>TELEFONO: '.$empresa[0]['pge_phone1'].'</p>
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
						<p>'.$this->ci->dateformat->Date($resSql[0]['fechadocumento']).'</p>
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
					<th style="text-align: center;"><b>DATOS DEL '.$Type.'</b><th>
				</tr>
			</table>
				<table width="100%"  >
					<tr>
						<td>
							<table width="100%" >
								<tr>
									<td style="text-align:left;"><b>NIT/CC:</b></td>
									<td style="text-align:left;">'.$resSql[0]['nit'].'</td>
								</tr>
								<tr>
									<td style="text-align:left;"><b>ciudad:</b></td>
									<td style="text-align:left;">'.$resSql[0]['ciudad'].'</td>
								</tr>
								<tr>
									<td style="text-align:left;"><b>nombre contacto:</b></td>
									<td style="text-align:left;">'.$resSql[0]['nombre_contacto_p'].'</td>
								</tr>
								<tr>
									<td style="text-align:left;"><b>celular contacto:</b></td>
									<td style="text-align:left;">'.$resSql[0]['cel_contacto_p'].'</td>
								</tr>
							</table>
						</td>
						<td style="width:31%;"></td>
						<td>
							<table width="100%" >
								<tr>
									<td style="text-align:left;"><b>nombre cliente:</b></td>
									<td style="text-align:left;">'.$resSql[0]['nit'].'</td>
								</tr>
								<tr>
									<td style="text-align:left;"><b>Barrio:</b></td>
									<td style="text-align:left;">'.$resSql[0]['ciudad'].'</td>
								</tr>
								<tr>
									<td style="text-align:left;"><b>Telefono contacto:</b></td>
									<td style="text-align:left;">'.$resSql[0]['telefono_contacto_p'].'</td>
								</tr>
								<tr>
									<td style="text-align:left;"><b>Correo contacto:</b></td>
									<td style="text-align:left;">'.$resSql[0]['correo_contacto_p'].'</td>
								</tr>
							</table>
						</td>
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
					<th style="text-align: center;"><b>DATOS DEL CONTRATO</b><th>
				</tr>				
			</table>
			<table width="100%"  >
				<tr>
					<td>
						<table width="100%" >
							<tr>
								<td style="text-align:left;"><b>fecha inicio:</b></td>
								<td style="text-align:left;">'.$resSql[0]['fechaentrga'].'</td>
							</tr>
							<tr>
								<td style="text-align:left;"><b>fecha firma contrato:</b></td>
								<td style="text-align:left;">'.$resSql[0]['fechafirma'].'</td>
							</tr>
							<tr>
								<td style="text-align:left;"><b>condición de pago:</b></td>
								<td style="text-align:left;">'.$resSql[0]['tipopago'].'</td>
							</tr>
							<tr>
								<td style="text-align:left;"><b>estado contrato:</b></td>
								<td style="text-align:left;">'.$resSql[0]['estado'].'</td>
							</tr>
						</table>
					</td>
					<td style="width:31%;"></td>
					<td>
						<table width="100%" >
							<tr>
								<td style="text-align:left;"><b>Fecha Fin:</b></td>
								<td style="text-align:left;">'.$resSql[0]['nit'].'</td>
							</tr>
							<tr>
								<td style="text-align:left;"><b>Tipo de acuerdo:</b></td>
								<td style="text-align:left;">'.$resSql[0]['tipoacuerdo'].'</td>
							</tr>
							<tr>
								<td style="text-align:left;"><b>Renovación:</b></td>
								<td style="text-align:left;">'.$resSql[0]['renovacion'].'</td>
							</tr>
							<tr>
								<td></td>
								<td></td>
							</tr>
						</table>
					</td>
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
				<tr >
					<th style="padding-top: 10px;"><b>ITEM</b></th>
					<th style="padding-top: 10px;"><b>REFERENCIA</b></th>
					<th style="padding-top: 10px;"><b>PRECIO</b></th>
					<th style="padding-top: 10px;"><b>CANT</b></th>
					<th style="padding-top: 10px;"><b>DTO</b></th>
					<th style="padding-top: 10px;"><b>IVA</b></th>
					<th style="padding-top: 10px;"><b>TOTAL</b></th>
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
       		<table width="100%">
				<tr>
					<td style="text-align: right;"><b>Base Documento:</b>  <span>'.$resSql[0]['monedadocumento']." ".number_format($resSql[0]['base'], $DECI_MALES, ',', '.').'</span></p></td>
				</tr>
				<tr>
					<td style="text-align: right;"><b>Descuento:</b>  <span>'.$resSql[0]['monedadocumento']." ".number_format($resSql[0]['descuento'], $DECI_MALES, ',', '.').'</span></p></td>
				</tr>
				<tr>
					<td style="text-align: right;"><b>Sub Total:</b>  <span>'.$resSql[0]['monedadocumento']." ".number_format($resSql[0]['subtotal'], $DECI_MALES, ',', '.').'</span></p></td>
				</tr>
				<tr>
					<td style="text-align: right;"><b>Impuestos:</b>  <span>'.$resSql[0]['monedadocumento']." ".number_format($resSql[0]['iva'], $DECI_MALES, ',', '.').'</span></p></td>
				</tr>
				<tr>
					<td style="text-align: right;"><b>Total:</b>  <span>'.$resSql[0]['monedadocumento']." ".number_format($resSql[0]['totaldoc'], $DECI_MALES, ',', '.').'</span></p></td>
				</tr>
			</table>
			<table  width="100%">
				<tr>
            		<td style="text-align: left;"><b>comentarios:</b><br><p>'.$resSql[0]['comentarios'].'</p></td>
        		</tr>
        	</table>
			<br>
			<br>
        	<table width="100%" style="vertical-align: bottom; font-family: serif; font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            	<tr>
                	<th style="text-align: left;" >
						<p><b>VALOR EN LETRAS:</b></p><br>
                    	<p>'.$formatter->toWords($resSql[0]['totaldoc'],$DECI_MALES).' PESOS</p>
                	</th>
            	</tr>
        	</table>
		</html>';

        $stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');
		$mpdf->SetDefaultBodyCSS('background', "url('/var/www/html/".$company[0]['company']."/assets/img/background.png')");
        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);

        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);

		$filename = $resSql[0]['mdt_docname'].'_'.$resSql[0]['numerodocumento'].'.pdf';
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