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

	}


	public function PdfPaymentMade_post(){

        $Data = $this->post();
				$Data = $Data['bpe_docentry'];

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
													CONCAT(T0.bpe_CARDNAME,' ',T2.DMS_CARD_LAST_NAME) Cliente,
													T0.bpe_CARDCODE Nit,
													CONCAT(T3.DMD_ADRESS,' ',T3.DMD_CITY) AS Direccion,
													T3.dmd_state_mm ciudad,
													t3.dmd_state estado,
													T4.DMC_PHONE1 Telefono,
													T4.DMC_EMAIL Email,
													T0.bpe_DOCNUM,
													T0.bpe_DOCNUM NumeroDocumento,
													T0.bpe_DOCDATE FechaDocumento,
													T0.bpe_DUEDATE FechaVenDocumento,
													T0.bpe_duedev fechaentrga,
													trim('COP' FROM t0.bpe_CURRENCY) MonedaDocumento,
													T7.PGM_NAME_MONEDA NOMBREMONEDA,
													T5.MEV_NAMES Vendedor,
													'' MedioPago,
													'' CondPago,
													T1.pe1_ITEMCODE Referencia,
													T1.pe1_ITEMNAME descripcion,
													T1.pe1_WHSCODE Almacen,
													T1.pe1_UOM UM,
													T1.pe1_QUANTITY Cantidad,
													T1.pe1_PRICE VrUnit,
													T1.pe1_DISCOUNT PrcDes,
													T1.pe1_VATSUM IVAP,
													T1.pe1_LINETOTAL ValorTotalL,
													T0.bpe_BASEAMNT base,
													T0.bpe_DISCOUNT Descuento,
													(T0.bpe_BASEAMNT - T0.bpe_DISCOUNT) subtotal,
													T0.bpe_TAXTOTAL Iva,
													T0.bpe_DOCTOTAL TotalDoc,
													T0.bpe_COMMENT Comentarios,
													t6.pgs_mde,
													t6.pgs_mpfn,
													T8.MPF_NAME cond_pago,
													t3.dmd_tonw lugar_entrega,
													t5.mev_names nombre_contacto,
											    t5.mev_mail correo_contacto,
											    t4.dmc_phone1 telefono_contacto,
													t0.bpe_date_inv fecha_fact_pro,
													t0.bpe_date_del fecha_entre,
													t0.bpe_place_del lugar_entre
												FROM gbpe  t0
												INNER JOIN bpe1 T1 ON t0.bpe_docentry = t1.pe1_docnum
												LEFT JOIN DMSN T2 ON t0.bpe_cardcode = t2.dms_card_code
												LEFT JOIN DMSD T3 ON t2.dms_card_code = t3.dmd_card_code AND t3.dmd_ppal = 1
												LEFT JOIN DMSC T4 ON T0.bpe_CONTACID = CAST(T4.DMC_ID AS VARCHAR)
												LEFT JOIN DMEV T5 ON T0.bpe_SLPCODE = T5.MEV_ID
												LEFT JOIN PGDN T6 ON T0.bpe_DOCTYPE = T6.PGS_ID_DOC_TYPE AND T0.bpe_SERIES = T6.PGS_ID
												LEFT JOIN PGEC T7 ON T0.bpe_CURRENCY = T7.PGM_SYMBOL
												LEFT JOIN DMPF T8 ON CAST(T2.DMS_PAY_TYPE AS INT) = T8.MPF_ID
												WHERE T0.bpe_docentry = :bpe_docentry and t2.dms_card_type = '2'";

				$contenidoOC = $this->pedeo->queryTable($sqlcotizacion,array(':bpe_docentry'=>$Data));
print_r($sqlcotizacion);exit();die();
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

				if($contenidoOC[0]['pgs_mpfn'] == 1){
					$consecutivo = $contenidoOC[0]['numerodocumento'];
				}else{
					$consecutivo = $contenidoOC[0]['bpe_docnum'];
				}


				$totaldetalle = '';
				foreach ($contenidoOC as $key => $value) {
					// code...
					$detalle = '<td>'.$value['referencia'].'</td>
											<td>'.$value['descripcion'].'</td>
											<td>'.$value['um'].'</td>
											<td>'.$value['monedadocumento']." ".number_format($value['vrunit'], 2, ',', '.').'</td>
											<td>'.$value['cantidad'].'</td>
											<td>'.$value['prcdes'].'</td>
											<td>'.$value['monedadocumento']." ".number_format($value['ivap'], 2, ',', '.').'</td>
											<td>'.$value['monedadocumento']." ".number_format($value['valortotall'], 2, ',', '.').'</td>';
				 $totaldetalle = $totaldetalle.'<tr>'.$detalle.'</tr>';
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
                <p><b>ORDEN DE COMPRA</b></p>
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
				<table  width="100%">
        <tr>

            <th style="text-align: left;">
								<p><b>'.$empresa[0]['pge_small_name'].'</b></p>
								<p>'.$empresa[0]['pge_add_soc'].'</p>
                <p>'.$empresa[0]['pge_id_type'].'</p>
                <p>TELEFONOS: '.$empresa[0]['pge_phone1'].'</p>
                <p>'.$empresa[0]['pge_web_site'].'</p>
                <p>'.$empresa[0]['pge_mail'].'</p>
            </th>
            <th style="text-align: right;">
								<p><b>OC: </b></p>
						</th>
						<th style="text-align: left;">
                <p >'.$contenidoOC[0]['numerodocumento'].'</p>
						</th>
						<th style="text-align: right;">
								<th style="text-align: right;">
									<p><b>FECHA DE EMISIÓN: </b></p>
						</th>
									<th style="text-align: left;">
									<p>'.date("d-m-Y", strtotime($contenidoOC[0]['fechadocumento'])).'</p>

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

				<table  width="100%" font-family: serif>
				<tr>
					<th><b>PROVEEDOR</b><th>
					<th><b>'.$empresa[0]['pge_small_name'].'</b><th>
       	</th>
        </tr>
				<tr>
					<td><b>RIF:</b> <span>'.$contenidoOC[0]['nit'].'</span></p></td>
					<td></td>
					<td><b>nombre contacto:</b> <span>'.$contenidoOC[0]['nombre_contacto'].'</span></p></td>
				</tr>
				<tr>
					<td><b>nombre proveedor:</b> <span>'.$contenidoOC[0]['cliente'].'</span></p></td>
					<td></td>
					<td><b>correo contacto:</b> <span>'.$contenidoOC[0]['correo_contacto'].'</span></p></td>
				</tr>
				<tr>
					<td><b>direccion:</b> <span>'.$contenidoOC[0]['direccion'].'</span></p></td>
					<td></td>
					<td><b>telefono contacto:</b> <span>'.$contenidoOC[0]['telefono_contacto'].'</span></p></td>
				</tr>
				<tr>
					<td><b>ciudad:</b> <span>'.$contenidoOC[0]['ciudad'].'</span></p></td>
				</tr>
				<tr>
					<td><b>estado:</b> <span>'.$contenidoOC[0]['estado'].'</span></p></td>
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
				<table table  width="100%">
				<tr>
				<th style="text-align: center;"><b>CONDICION DE PAGO:</b></th>
				<th style="text-align: center;"><b>Lugar de Entrega:</b></th>
				<th style="text-align: center;"><b>FECHA DE ENTREGA:</b></th>
				<th style="text-align: center;"><b>FECHA FACTURA PROVEEDOR:</b></th>
				</tr>
				<tr>
				<td>'.$contenidoOC[0]['cond_pago'].'</td>
				<td>'.$contenidoOC[0]['lugar_entre'].'</td>
				<td>'.date("d-m-Y", strtotime($contenidoOC[0]['fecha_entre'])).'</td>
				<td>'.$contenidoOC[0]['fecha_fact_pro'].'</td>
				</tr>

				</table>
				<br>

        <table class="borde" style="width:100%">



        <tr>
          <th><b>ITEM</b></th>
          <th><b>REFERENCIA</b></th>
          <th><b>UNIDAD</b></th>
          <th><b>PRECIO</b></th>
          <th><b>CANTIDAD</b></th>
          <th><b>DESCUENTO</b></th>
          <th><b>IVA</b></th>
          <th><b>TOTAL</b></th>
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
            <td style="text-align: right;"><b>Base Documento:</b>  <span>'.$contenidoOC[0]['monedadocumento']." ".number_format($contenidoOC[0]['base'], 2, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;"><b>Descuento:</b>  <span>'.$contenidoOC[0]['monedadocumento']." ".number_format($contenidoOC[0]['descuento'], 2, ',', '.').'</span></p></td>
        </tr>
				<tr>
            <td style="text-align: right;"><b>Sub Total:</b>  <span>'.$contenidoOC[0]['monedadocumento']." ".number_format($contenidoOC[0]['subtotal'], 2, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;"><b>Impuestos:</b>  <span>'.$contenidoOC[0]['monedadocumento']." ".number_format($contenidoOC[0]['iva'], 2, ',', '.').'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: right;"><b>Total:</b>  <span>'.$contenidoOC[0]['monedadocumento']." ".number_format($contenidoOC[0]['totaldoc'], 2, ',', '.').'</span></p></td>
        </tr>
				</table>
				<table  width="100%">
				<tr>
            <td style="text-align: left;"><b>comentarios (ver adjunto de instruccion de envio):</b>
						<br><p>'.$contenidoOC[0]['comentarios'].'</p>
						</p>
						</td>
        </tr>


        </table>
				<br>
				<br>

        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th style="text-align: left;" >
										<p><b>VALOR EN LETRAS:</b></p><br>
                    <p>'.$formatter->toWords($contenidoOC[0]['totaldoc'],2)." ".$contenidoOC[0]['nombremoneda'].'</p>
                </th>
            </tr>
        </table>
				</html>';

        $stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');

        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);


        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);

				$filename = 'OC_'.$contenidoOC[0]['numerodocumento'].'.pdf';
        $mpdf->Output($filename, 'D');


				header('Content-type: application/force-download');
				header('Content-Disposition: attachment; filename='.$filename);


	}




}
