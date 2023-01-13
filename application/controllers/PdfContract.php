<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class PdfContract extends REST_Controller {

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


	public function PdfContract_post(){

        $Data = $this->post();
		$Data = $Data['CSN_DOCENTRY'];

		$DECI_MALES =  $this->generic->getDecimals();

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

				if(!isset($empresa[0])){
						$respuesta = array(
		           'error' => true,
		           'data'  => $empresa,
		           'mensaje' =>'no esta registrada la información de la empresa'
		        );

	          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	          return;
				}

				$observaciones = '';
				$sqlcotizacion = "SELECT
				CONCAT(T0.csn_CARDNAME,' ',T2.DMS_CARD_LAST_NAME) Cliente,
				T0.csn_CARDCODE Nit,
				CONCAT(T3.DMD_ADRESS,' ',T3.DMD_CITY) AS Direccion,
				T3.dmd_state_mm ciudad,
				t3.dmd_state estado,
				T4.DMC_PHONE1 Telefono,
				T4.DMC_EMAIL Email,
				T0.csn_DOCNUM,
				T0.csn_DOCNUM NumeroDocumento,
				T0.csn_DOCDATE FechaDocumento,
				T0.csn_DUEDATE FechaVenDocumento,
				T0.csn_duedev fechaentrga,
				trim('COP' FROM t0.csn_CURRENCY) MonedaDocumento,
				T7.PGM_NAME_MONEDA NOMBREMONEDA,
				T5.MEV_NAMES Vendedor,
				'' MedioPago,
				'' CondPago,
				T1.sn1_ITEMCODE Referencia,
				T1.sn1_ITEMNAME descripcion,
				T1.sn1_WHSCODE Almacen,
				T1.sn1_UOM UM,
				T1.sn1_QUANTITY Cantidad,
				T1.sn1_PRICE VrUnit,
				T1.sn1_DISCOUNT PrcDes,
				T1.sn1_VATSUM IVAP,
				T1.sn1_LINETOTAL ValorTotalL,
				T0.csn_BASEAMNT base,
				T0.csn_DISCOUNT Descuento,
				(T0.csn_BASEAMNT - T0.csn_DISCOUNT) subtotal,
				T0.csn_TAXTOTAL Iva,
				T0.csn_DOCTOTAL TotalDoc,
				T0.csn_COMMENT Comentarios,
				t6.pgs_mde,
				t6.pgs_mpfn,
				T8.MPF_NAME cond_pago,
				t3.dmd_tonw lugar_entrega,
				t5.mev_names nombre_contacto,
				t5.mev_mail correo_contacto,
				t5.mev_phone telefono_contacto,
				concat(t9.dmc_name,' ',t9.dmc_last_name) nombre_contacto_p,
				t9.dmc_email correo_contacto_p,
				t9.dmc_cel telefono_contacto_p,
				t10.sn2_itemdescription as itemEntrega,
				t10.sn2_daycode as day,
				t10.sn2_price valorday,
				t11.smd_description dayname,
   				t10.sn2_period periodo
			FROM tcsn  t0
			INNER JOIN csn1 T1 ON t0.csn_docentry = t1.sn1_docentry
			LEFT JOIN csn2 T10 on t0.csn_docentry = t10.sn2_docentry
			LEFT JOIN DMSN T2 ON t0.csn_cardcode = t2.dms_card_code
			LEFT JOIN DMSD T3 ON t2.dms_card_code = t3.dmd_card_code AND t3.dmd_ppal = 1
			LEFT JOIN DMSC T4 ON T0.csn_CONTACID = CAST(T4.DMC_ID AS VARCHAR)
			LEFT JOIN DMEV T5 ON T0.csn_SLPCODE = T5.MEV_ID
			LEFT JOIN PGDN T6 ON T0.csn_DOCTYPE = T6.PGS_ID_DOC_TYPE AND T0.csn_SERIES = T6.PGS_ID
			LEFT JOIN PGEC T7 ON T0.csn_CURRENCY = T7.PGM_SYMBOL
			LEFT JOIN DMPF T8 ON T2.DMS_PAY_TYPE = cast(T8.MPF_ID as  varchar)
			Left join dmsc t9 on t0.csn_cardcode = t9.dmc_card_code
			left join csmd t11 on t10.sn2_daycode = t11.smd_id
			WHERE T0.csn_docentry = :csn_docentry
			ORDER BY day ASC";

			$contenidoContrato = $this->pedeo->queryTable($sqlcotizacion,array(':csn_docentry'=>$Data));

				if(!isset($contenidoContrato[0])){
						$respuesta = array(
							 'error' => true,
							 'data'  => $contenidoContrato,
							 'mensaje' =>'no se encontro el documento'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}
				// print_r($contenidoContrato);exit();die();
				$checkContrato = '';
				
				$consecutivo = '';

				if($contenidoContrato[0]['pgs_mpfn'] == 1){
					$consecutivo = $contenidoContrato[0]['numerodocumento'];
				}else{
					$consecutivo = $contenidoContrato[0]['csn_docnum'];
				}

				$day = [];
				$dayUnique = [];
				$totaldetalle = '';
				//VALIDAR PERIODO DE PAGO
				$razon = "";
				$gaceta = "";
				$otros = "";
				$valorRazon = 0;
				$valorGaceta = 0;
				$valorOtros = 0;
				$suma = 0;
				foreach ($contenidoContrato as $key => $value) {
					// code...
					$dayWeek = "";

					$detalle = '<td>'.$value['referencia'].'</td>
											<td>'.$value['descripcion'].'</td>
											<td>'.$value['um'].'</td>
											<td>'.$value['monedadocumento']." ".number_format($value['vrunit'], 2, ',', '.').'</td>
											<td>'.$value['cantidad'].'</td>
											<td>'.$value['prcdes'].'</td>
											<td>'.$value['monedadocumento']." ".number_format($value['ivap'], 2, ',', '.').'</td>
											<td>'.$value['monedadocumento']." ".number_format($value['valortotall'], 2, ',', '.').'</td>';
				$totaldetalle = $totaldetalle.'<tr>'.$detalle.'</tr>';
				
				switch ($value['day']){
					case '1':
						$dayWeek = $value['dayname'];
						array_push($day,$dayWeek);
						break;
					case '2':
						$dayWeek = $value['dayname'];
						array_push($day,$dayWeek);
						break;
					case '3':
						$dayWeek = $value['dayname'];
						array_push($day,$dayWeek);
						break;
					case '4':
						$dayWeek = $value['dayname'];
						array_push($day,$dayWeek);
						break;
					case '5':
						$dayWeek = $value['dayname'];
						array_push($day,$dayWeek);
						break;
					case '6':
						$dayWeek = $value['dayname'];
						array_push($day,$dayWeek);
						break;
					case '7':
						$dayWeek = $value['dayname'];
						array_push($day,$dayWeek);
						break;
					default:
						$dayWeek;
					
				 }

				 $dayUnique = array_unique($day);

				 

				if($value['itementrega'] == 1){
					$razon = 'checked="checked"';

				}else if ($value['itementrega'] == 2){
					$gaceta = 'checked="checked"';

				}else if ($value['itementrega'] == 3){
					$otros = 'checked="checked"';

				}
			}


			$td = '<td style="text-align: center;">0</td>
			<td style="text-align: center;">0</td>
			<td style="text-align: center;">0</td>
			<td style="text-align: center;">0</td>
			<td style="text-align: center;">0</td>';

			if($contenidoContrato[0]['periodo'] == 1){
				$suma = $contenidoContrato[0]['totaldoc'];
				$td = '<td style="text-align: center;">'.number_format($suma,$DECI_MALES,',','.').'</td>
				<td style="text-align: center;">0</td>
				<td style="text-align: center;">0</td>
				<td style="text-align: center;">0</td>
				<td style="text-align: center;">0</td>';
			}else if ($contenidoContrato[0]['periodo'] == 2){
				$suma = $contenidoContrato[0]['totaldoc'];
				$td = '<td style="text-align: center;">0</td>
				<td style="text-align: center;">'.number_format($suma,$DECI_MALES,',','.').'</td>
				<td style="text-align: center;"></td>
				<td style="text-align: center;"></td>
				<td style="text-align: center;"></td>';
			}
			else if ($contenidoContrato[0]['periodo'] == 3){
				$suma = $contenidoContrato[0]['totaldoc'];
				$td = '<td style="text-align: center;">0</td>
				<td style="text-align: center;">0</td>
				<td style="text-align: center;">'.number_format($suma,$DECI_MALES,',','.').'</td>
				<td style="text-align: center;">0</td>
				<td style="text-align: center;">0</td>';
			}
			else if ($contenidoContrato[0]['periodo'] == 4){
				$suma = $contenidoContrato[0]['totaldoc'];
				$td = '<td style="text-align: center;">0</td>
				<td style="text-align: center;">0</td>
				<td style="text-align: center;">0</td>
				<td style="text-align: center;">'.number_format($suma,$DECI_MALES,',','.').'</td>
				<td style="text-align: center;">0</td>';
			}
			else if ($contenidoContrato[0]['periodo'] == 5){
				$suma = $contenidoContrato[0]['totaldoc'];
				$td = '<td style="text-align: center;">0</td>
				<td style="text-align: center;">0</td>
				<td style="text-align: center;">0</td>
				<td style="text-align: center;">0</td>
				<td style="text-align: center;">'.number_format($suma,$DECI_MALES,',','.').'</td>';
			}

			
			$convertArray = "";
			$convertArray = implode(',',$dayUnique);
				

		$texto = "<p>En caso de suscripciones a m&aacute;s de un ejempiar y que deban ser entregados en domicilios diferentes, el SUSCRIPTOR se compromete a 
		enviara<br />COMUNICACIONES EL PAIS S.A por escrito un listado de los destinatarios y sus direcciones. Cualquier cambio de domicilio deber&aacute; 
		ser comunicado con<br />72 horas de anticipaci&oacute;n.<br /><b>QUINTA (DERECHO A RECLAMO)</b> - EL SUSCRIPTOR tendra derecho a reciamar la no 
		entrega del peri&oacute;dico en el domicilio seralado, en el plazo minimo de veinticuatro horas. Pasado este t&eacute;rmino, no podr&aacute; 
		reclamar la reposici&oacute;n del ejemplar. Una vez verificada la falla de distribuci&oacute;n COMUNICACIONES EL PAIS S.A., se compromete a 
		hacer entrega inmediata del ejemplar faltante.<br /><b>SEXTA (RENOVACI&Oacute;N T&Aacute;CITA)</b> - En caso de que el cliente no hubiese mandado 
		una comunicaci&oacute;n escrita de no renovaci&oacute;n del contrato, se aplicar&aacute; la tacita reconducci&oacute;n, bajo los mismos t&eacute;rminos 
		y condiciones Aceptando el SUSCRIPTOR que en este caso COMUNICACIONES EL PA&Iacute;S S.A podra realizar<br />variaciones en el precio de acuerdo a 
		sus tarifas vigentes.<br /><b>SEPTIMA (CONFORMIDAD).</b>- Las partes descritasen la cl&aacute;usula primera, declaramos nuestra total y absoluta conformidad 
		con todas y cada una de las clausulas que anteceden, obligandonos a su fiel cumplimiento</p>";

        $header = '
		<table width="100%" style="text-align: left;">
        	<tr>
            	<th style="text-align: left;">
					<img src="/var/www/html/'.$company[0]['company'].'/'.$empresa[0]['pge_logo'].'" width ="100" height ="40"></img>
				</th>
            	<th>
					<p><b>'.$empresa[0]['pge_name_soc'].'</b></p>
					<p><b>'.$empresa[0]['pge_id_type'].'</b></p>
					<p><b>'.$empresa[0]['pge_add_soc'].'</b></p>
				</th>
				<th>
				    <p><b>CONTRATO - #'.$consecutivo.'</b></p>
				</th>
			/tr>
		</table>';
		
		$dateDoc = strtotime($contenidoContrato[0]['fechadocumento']);
		$dateVen = strtotime($contenidoContrato[0]['fechavendocumento']);
		//DIFERENCIAS DE AÑOS
		$difYears = date('Y',$dateVen) - date('Y',$dateDoc);
		//DIFERENCIAS DE MESES
		$difMonth =  date('m',$dateVen) - date('m',$dateDoc);
		//DIFERENCIAS DE DIAS
		$difDay =  date('d',$dateVen) - date('d',$dateDoc);

		$footer = '
		<table  width="100%" font-family: serif>
			<tr>
				<th style="text-align: left;"> LA PAZ '.date('d',$dateDoc).' DE '.date('M',$dateDoc).' DE '.date('Y',$dateDoc).'</th>
			</tr>
		</table>
		<br>
		<br>
		<table  width="100%" font-family: serif>
			<tr>
				<th style="text-align: center;"></th>
				<th style="text-align: center;">'.$contenidoContrato[0]['cliente'].'</th>
			</tr>
			<tr>
				<td style="text-align: center;"><b>COMUNICACIONES DEL PAIS S.A</b></td>
				<td style="text-align: center;"><b>SUSCRIPTOR</b></td>
			</tr>
		</table>
		<br>
		<br>
        <table width="100%" style="vertical-align: bottom; font-family: serif; font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
        	<tr>
            	<th  width="33%">Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
            </tr>
        </table>';

        $html = '
		<table  width="100%" font-family: serif>
			<tr>
				<th style="text-align: left;"><b>PRIMERA(PARTES)</b></th>
			</tr>
			<tr>
				<th style="text-align: left;"><b>a. COMUNICACIONES EL PAIS S.A</b></th>
			</tr>
			<tr>
				<th style="text-align: left;"><b>b. SUSCRIPTOR</b></th>
			</tr>
			<br>
			<br>
			<tr>
				<th style="text-align: left;"><b>NOMBRE O RAZÓN SOCIAL:</b> <span>'.$contenidoContrato[0]['cliente'].'</span></p></th>
			</tr>
			<tr>
				<th style="text-align: left;"><b>DOMICILIADO EN :</b> <span>'.$contenidoContrato[0]['direccion'].'</span></p></th>
			</tr>
			<tr>
				<th style="text-align: left;"><b>CIUDAD:</b> <span>'.$contenidoContrato[0]['ciudad'].'</span></p></th>
			</tr>
			<tr>
				<th style="text-align: left;"><b>TELÉFONO:</b> <span>'.$contenidoContrato[0]['telefono_contacto_p'].'</span></p></th>
			</tr>
			<tr>
				<th style="text-align: left;"><b>EMAIL:</b> <span>'.$contenidoContrato[0]['correo_contacto_p'].'</span></p></th>
			</tr>
			<tr>
				<th style="text-align: left;"><b>PRESENTADO POR:</b> <span>'.$contenidoContrato[0]['correo_contacto_p'].'</span></p></th>
			</tr>
		</table>
		<table width="100%" style="vertical-align: bottom; font-family: serif; font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th class="fondo">
                    <p></p>
                </th>
            </tr>
        </table>
		<table  width="100%" font-family: serif>
			<tr>
				<th style="text-align: left;"><b>SEGUNDA(SUSCRIPCIÓN)</b> - COMUNICACIONES EL PAIS S.A ACUERDA CON EL SUSCRIPTOR LA ENTREGA DE:</th>
			</tr>
		</table>
		<table width="100%" font-family: serif>
			<tr>
				<th style="text-align: left;">LA RAZON <span><input type="checkbox" '.$razon.'/></span></th>

				<th style="text-align: left;">GACETA JURIDICA  <span><input type="checkbox" '.$gaceta.'/></span></th>

				<th style="text-align: left;">OTROS  <span><input type="checkbox" '.$otros.'/></span></th>
			</tr>
		</table>
		<table width="100%" font-family: serif>
			<tr>
				<th style="text-align: left;">POR EL TÉRMINO DE '.$difYears.' AÑO(S) '.$difMonth.' MES(ES) Y '.$difDay.' DIAS, DEL '.date('d',$dateDoc).'/'.date('m',$dateDoc).'/'.date('Y',$dateDoc).' AL '.date('d',$dateVen).'/'.date('m',$dateVen).'/'.date('Y',$dateVen).'</th>
			</tr>
		</table>
		<table width="100%" style="vertical-align: bottom; font-family: serif; font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th class="fondo">
                    <p></p>
                </th>
            </tr>
        </table>
		<table  width="100%" font-family: serif>
			<tr>
				<th style="text-align: left;"><b>TERCERA(MODALIDAD Y PRECIO):</b></th>
			</tr>
			<br>
			<tr>
				<th style="text-align: left;"><b>MODALIDAD</b></th>
				<th style="text-align: center;"><b>MENSUAL</b></th>
				<th style="text-align: center;"><b>TRIMESTRAL</b></th>
				<th style="text-align: center;"><b>SEMESTRAL</b></th>
				<th style="text-align: center;"><b>ANUAL</b></th>
				<th style="text-align: center;"><b>BIANUAL</b></th>
			</tr>
			<tr>
				<td style="text-align: left;">'.$convertArray.'</span></td>
				'.$td.'
			</tr>
			<br>
			<tr>
				<th style="text-align: left;">EL PRECIO, SEGUN LA MODALIDAD ELEGIDA, SERÁ PAGADO POR ADELANTADO CONTRA ENTREGA DE LA RESPECTIVA NOTA FISCAL.<br>
				SON '.number_format($contenidoContrato[0]['totaldoc'],$DECI_MALES,',','.').' BOLIVARIANOS</th>
			</tr>
		</table>
		<table width="100%" style="vertical-align: bottom; font-family: serif; font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th class="fondo">
                    <p></p>
                </th>
            </tr>
        </table>
		<table  width="100%" font-family: serif>
			<tr>
				<th style="text-align: left;"><b>CUARTA(OBLIGACIÓN DE ENTREGA)</b> - LA RAZON SE OBLIGA A ENTREGAR LOS EJEMPLARES EN LOS SIGUIENTES DOMICILIOS</th>
			</tr>
			<br>
			<tr>
				<th style="text-align: left;"><b>DOMICILIADO EN :</b> <span>'.$contenidoContrato[0]['direccion'].'</span></p></th>
			</tr>
			<tr>
				<th style="text-align: left;"><b>CIUDAD:</b> <span>'.$contenidoContrato[0]['ciudad'].'</span></p></th>
			</tr>
			<tr>
				<th style="text-align: left;"><b>TELÉFONO:</b> <span>'.$contenidoContrato[0]['telefono_contacto_p'].'</span></p></th>
			</tr>
			<tr>
				<th style="text-align: left;"><b>EMAIL:</b> <span>'.$contenidoContrato[0]['correo_contacto_p'].'</span></p></th>
			</tr>
			<br>
			<table  width="100%" font-family: serif>
				<tr>
					<th style="text-align: left;">'.$texto.'</th>
				</tr>
			</table>
		</table>
		<table width="100%" style="vertical-align: bottom; font-family: serif; font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th class="fondo">
                    <p></p>
                </th>
            </tr>
        </table>
		
		</html>';
		// print_r($html);exit;
        $stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');

        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);


        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);

		$filename = 'CONTRATO_'.$contenidoContrato[0]['numerodocumento'].'.pdf';
        $mpdf->Output($filename, 'D');
	
		header('Content-type: application/force-download');
		header('Content-Disposition: attachment; filename='.$filename);

	}
}
