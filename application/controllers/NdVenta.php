<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class NdVenta extends REST_Controller {

	private $pdo;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
    $this->load->library('pedeo', [$this->pdo]);
		$this->load->library('generic');

	}


	public function NdVenta_post(){
				$DECI_MALES =  $this->generic->getDecimals();

        $Data = $this->post();
				$Data = $Data['VND_DOCENTRY'];

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
													CONCAT(T0.VND_CARDNAME,' ',T2.DMS_CARD_LAST_NAME) Cliente,
													TRIM('CN' FROM T0.VND_CARDCODE) Nit,
													CONCAT(T3.DMD_ADRESS,' ',T3.DMD_CITY) Direccion,
													T3.dmd_state_mm ciudad,
													t3.dmd_state estado,
													T4.DMC_PHONE1 Telefono,
													T4.DMC_EMAIL Email,
													T0.VND_DOCNUM,
													CONCAT(T6.PGS_PREF_NUM,' ',T0.VND_DOCNUM) NumeroDocumento,
													to_char(T0.VND_DOCDATE,'DD-MM-YYYY') FechaDocumento,
													to_char(T0.VND_DUEDATE,'DD-MM-YYYY') FechaVenDocumento,
													trim('COP' FROM t0.VND_CURRENCY) MonedaDocumento,
													T7.PGM_NAME_MONEDA NOMBREMONEDA,
													T5.MEV_NAMES Vendedor,
													'' MedioPago,
													'' CondPago,
													T1.ND1_ITEMCODE Referencia,
													T1.ND1_ITEMNAME descripcion,
													T1.ND1_WHSCODE Almacen,
													T1.ND1_UOM UM,
													T1.ND1_QUANTITY Cantidad,
													T1.ND1_PRICE VrUnit,
													T1.ND1_DISCOUNT PrcDes,
													T1.ND1_VATSUM IVAP,
													T1.ND1_LINETOTAL ValorTotalL,
													T0.VND_BASEAMNT base,
													T0.VND_DISCOUNT Descuento,
													(T0.VND_BASEAMNT - T0.VND_DISCOUNT) subtotal,
													T0.VND_TAXTOTAL Iva,
													T0.VND_DOCTOTAL TotalDoc,
													T0.VND_COMMENT Comentarios,
													t6.pgs_mde,
													t6.pgs_mpfn
												FROM DVND t0
												INNER JOIN VND1 T1 ON t0.VND_docentry = t1.ND1_docentry
												LEFT JOIN DMSN T2 ON t0.VND_cardcode = t2.dms_card_code
												LEFT JOIN DMSD T3 ON T0.VND_cardcode = T3.DMD_card_code AND t3.dmd_ppal = 1
												LEFT JOIN DMSC T4 ON T0.VND_CONTACID = CAST(T4.DMC_ID AS VARCHAR)
												LEFT JOIN DMEV T5 ON T0.VND_SLPCODE = T5.MEV_ID
												LEFT JOIN PGDN T6 ON T0.VND_DOCTYPE = T6.PGS_ID_DOC_TYPE AND T0.VND_SERIES = T6.PGS_ID
												LEFT JOIN PGEC T7 ON T0.VND_CURRENCY = T7.PGM_SYMBOL
												WHERE T0.VND_DOCENTRY = :VND_DOCENTRY
												and t2.dms_card_type = '1'";

				$contenidoNdV = $this->pedeo->queryTable($sqlcotizacion,array(':VND_DOCENTRY'=>$Data));

				if(!isset($contenidoNdV[0])){
						$respuesta = array(
							 'error' => true,
							 'data'  => $empresa,
							 'mensaje' =>'no se encontro el documento'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}
				// print_r($contenidoNdV);exit();die();

				$consecutivo = '';

				if($contenidoNdV[0]['pgs_mpfn'] == 1){
					$consecutivo = $contenidoNdV[0]['numerodocumento'];
				}else{
					$consecutivo = $contenidoNdV[0]['vnd_docnum'];
				}

				$totaldetalle = '';
				foreach ($contenidoNdV as $key => $value) {
					// code...
					$detalle = '<td>'.$value['referencia'].'</td>
											<td>'.$value['descripcion'].'</td>
											<td>'.$value['um'].'</td>
											<td>'.$value['monedadocumento']." ".number_format($value['vrunit'], $DECI_MALES, ',', '.').'</td>
											<td>'.$value['cantidad'].'</td>
											<td>'.$value['monedadocumento']." ".number_format($value['ivap'], $DECI_MALES, ',', '.').'</td>
											<td>'.$value['monedadocumento']." ".number_format($value['valortotall'], $DECI_MALES, ',', '.').'</td>';
				 $totaldetalle = $totaldetalle.'<tr>'.$detalle.'</tr>';
				}


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
                <p>NOTA DEBITO DE VENTA</p>
                <p class="">'.$contenidoNdV[0]['numerodocumento'].'</p>

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

				<table class="bordew" style="width:100%">
				<tr>
					<th style="text-align: left;">
						<p class="">RIF: </p>
					</th>
					<th style="text-align: left;">
						<p> '.$contenidoNdV[0]['nit'].'</p>
					</th>
				</tr>
				<tr>
					<th style="text-align: left;">
						<p class="">NOMBRE: </p>
					</th>
					<th style="text-align: left;">
						<p> '.$contenidoNdV[0]['cliente'].'</p>
					</th>
				</tr>
				<tr>
					<th style="text-align: left;">
						<p class="">DIRECCIÓN: </p>
					</th>
					<th style="text-align: left;">
						<p> '.$contenidoNdV[0]['direccion'].'</p>
					</th>
					<th style="text-align: right;">
						<p class=""></p>
					</th>
					<th style="text-align: right;">
						<p></p>
					</th>
				</tr>
				<tr>
					<th style="text-align: left;">
						<p class="">CIUDAD: </p>
					</th>
					<th style="text-align: left;">
						<p> '.$contenidoNdV[0]['ciudad'].'</p>
					</th>
				</tr>
				<tr>
					<th style="text-align: left;">
						<p class="">ESTADO: </p>
					</th>
					<th style="text-align: left;">
						<p> '.$contenidoNdV[0]['estado'].'</p>
					</th>
				</tr>
				<tr>
					<th style="text-align: left;">
						<p class=""></p>
					</th>
					<th style="text-align: left;">
						<p></p>
					</th>
				</tr>
				<tr>
					<th style="text-align: left;">
						<p class=""></p>
					</th>
					<th style="text-align: left;">
						<p></p>
					</th>
				</tr>
				<tr>
					<th style="text-align: left;">
						<p class=""></p>
					</th>
					<th style="text-align: left;">
						<p></p>
					</th>
					<th style="text-align: right;">
						<p class="">FECHA DE EMISIÓN: </p>
					</th>
					<th style="text-align: right;">
						<p>'.date("d-m-Y", strtotime($contenidoNdV[0]['fechadocumento'])).'</p>
					</th>
				</tr>
				</table>

        <br>

        <table class="borde" style="width:100%">
        <tr>
          <th class="">ITEM</th>
          <th class="">REFERENCIA</th>
          <th class="">UNIDAD</th>
          <th class="">PRECIO</th>
          <th class="">CANTIDAD</th>
          <th class="">IVA</th>
          <th class="">TOTAL</th>
        </tr>
      	'.$totaldetalle.'
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
				<table width="100%" style="vertical-align: bottom; font-family: serif;
								font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;" >
						<tr><th style="text-align: left;">Comentarios:</th></tr>
						<tr><td style="text-align: left;">'.$contenidoNdV[0]['comentarios'].'</td></tr>
				</table>

        <br>
        <table width="100%">
        <tr>
            <td style="text-align: right;">Base Documento: <span>'.$contenidoNdV[0]['monedadocumento']." ".number_format($contenidoNdV[0]['base'], $DECI_MALES, ',', '.').'</span></p></td>
        </tr>

				<tr>
            <td style="text-align: right;">Sub Total: <span>'.$contenidoNdV[0]['monedadocumento']." ".number_format($contenidoNdV[0]['subtotal'], $DECI_MALES, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;">Impuestos: <span>'.$contenidoNdV[0]['monedadocumento']." ".number_format($contenidoNdV[0]['iva'], $DECI_MALES, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;">Total: <span>'.$contenidoNdV[0]['monedadocumento']." ".number_format($contenidoNdV[0]['totaldoc'], $DECI_MALES, ',', '.').'</span></p></td>
        </tr>

        </table>


        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th style="text-align: left;" class="">
                    <p>'.$formatter->toWords($contenidoNdV[0]['totaldoc'],2)." ".$contenidoNdV[0]['nombremoneda'].'</p>
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

		$filename = 'Doc.pdf';
        $mpdf->Output('Doc.pdf', 'D');

				header('Content-type: application/force-download');
				header('Content-Disposition: attachment; filename='.$filename);


	}




}
