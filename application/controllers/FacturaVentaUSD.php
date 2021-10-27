<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class FacturaVentaUSD extends REST_Controller {

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


	public function FacturaVentaUSD_post(){

        $Data = $this->post();
				$Data = $Data['DVF_DOCENTRY'];

				$formatter = new NumeroALetras();


        $mpdf = new \Mpdf\Mpdf(['setAutoBottomMargin' => 'stretch','setAutoTopMargin' => 'stretch','default_font' => 'arial']);

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
													concat(T0.dvf_cardname,' ',T2.dms_card_last_name) Cliente,
													T0.dvf_cardcode Nit,
													concat(T3.dmd_adress,' ',T3.dmd_city) Direccion,
												    T3.dmd_state_mm ciudad,
												    t3.dmd_state estado,
													T2.dms_phone1 Telefono,
													T2.dms_email Email,
													ConCAT(T6.pgs_pref_num,' ',T0.dvf_docnum) NumeroDocumento,
													T0.dvf_docdate FechaDocumento,
													T0.dvf_duedate FechaVenDocumento,
													trim('COP' from t0.dvf_currency) MonedaDocumento,
													T7.pgm_name_moneda NOMBREMonEDA,
													T5.mev_names Vendedor,
													t8.mpf_name CondPago,
													T1.fv1_itemcode Referencia,
													T1.fv1_itemname descripcion,
													T1.fv1_whscode Almacen,
													T1.fv1_uom UM,
													T1.fv1_quantity Cantidad,
													T1.fv1_price VrUnit,
													T1.fv1_discount PrcDes,
													T1.fv1_vatsum IVAP,
													T1.fv1_linetotal ValorTotalL,
													T0.dvf_baseamnt base,
													T0.dvf_discount Descuento,
													(T0.dvf_baseamnt - T0.dvf_discount) subtotal,
													T0.dvf_taxtotal Iva,
													T0.dvf_doctotal TotalDoc,
													T0.dvf_comment Comentarios,
													t0.dvf_ref referencia,
													(select t9.dma_uom_weight from dmar t9 where  t9.dma_item_code = t1.fv1_itemcode) peso,
													t11.dmu_code unidad
												from dvfv t0
												inner join vfv1 T1 on t0.dvf_docentry = t1.fv1_docentry
												left join dmsn T2 on t0.dvf_cardcode = t2.dms_card_code
												left join dmsd T3 on T0.dvf_cardcode = t3.dmd_card_code
												left join dmsc T4 on T0.dvf_cardcode = t4.dmc_card_code
												left join dmev T5 on T0.dvf_slpcode = T5.mev_id
												left join pgdn T6 on T0.dvf_doctype = T6.pgs_id_doc_type and T0.dvf_series = T6.pgs_id
												left join pgec T7 on T0.dvf_currency = T7.pgm_symbol
												left join dmpf t8 on t2.dms_pay_type = cast(t8.mpf_id as varchar)
												left join dmar t10 on t1.fv1_itemcode = t10.dma_item_code
												left join dmum t11 on t10.dma_uom_umweight = t11.dmu_id
												where T0.dvf_docentry = :DVF_DOCENTRY";

				$contenidoFV = $this->pedeo->queryTable($sqlcotizacion,array(':DVF_DOCENTRY'=>$Data));

				if(!isset($contenidoFV[0])){
						$respuesta = array(
							 'error' => true,
							 'data'  => $empresa,
							 'mensaje' =>'no se encontro el documento'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}
				// print_r();exit();die();
				//obtener tasa en dolar para el formato de dolar

				$sqlTasa = "SELECT tsa_value  from tasa where tsa_date = current_date";
				$resTasa = $this->pedeo->queryTable($sqlTasa, array());
				// print_r($resTasa);exit();die();

				$VieneTasa = 0;
				if(is_numeric($resTasa[0]['tsa_value']) && is_numeric($resTasa[0]['tsa_value']) > 0){

						$VieneTasa = $resTasa[0]['tsa_value'];
				}
	// print_r($VieneTasa);exit();die();
				//obtener el numero de pedido y de ENTREGA
				$Entrega = "SELECT
																t0.vem_docnum entrega,1 pedido,t2.*
																from dvem t0
																left join dvfv t2 on t0.vem_docentry = t2.dvf_baseentry and t0.vem_doctype = t2.dvf_basetype
																where t2.dvf_docentry = :DVF_DOCENTRY
																order by entrega asc";
			  $resEntrega = $this->pedeo->queryTable($Entrega, array(':DVF_DOCENTRY' => $Data));

				$VieneEntrega = 0;
				$VienePedido = 0;

				if(isset($resEntrega[0])){
						$VieneEntrega = $resEntrega[0]['entrega'];
						$VienePedido = $resEntrega[0]['pedido'];

				}
				//INFORMACION DE LA DESCRIPCION FINAL DEL FORMATO
				$CommentFinal = "SELECT t0.*
												 FROM cfdm t0
												 LEFT JOIN dvfv t1 ON t0.cdm_type = CAST(t1.dvf_doctype AS VARCHAR)
												 WHERE t1.dvf_docentry = :DVF_DOCENTRY";
				$CommentFinal = $this->pedeo->queryTable($CommentFinal,array(':DVF_DOCENTRY' => $Data));


				$totaldetalle = '';
				$TotalCantidad = 0;
				$TotalPeso = 0;

				foreach ($contenidoFV as $key => $value) {
					// code...<td>'.$value['um'].'</td>
					//<td>'.number_format($value['ivap'], 2, ',', '.').'</td>

				$detalle = '	<td>'.$value['cantidad'].'</td>
											<td>'.$value['referencia'].'</td>
											<td>'.$value['descripcion'].'</td>
											<td>USD '.number_format(($value['vrunit']  * 1.25) / $VieneTasa , 2, ',', '.').'</td>
											<td>USD '.number_format(($value['valortotall'] * 1.25) / $VieneTasa, 2, ',', '.').'</td>';

				 $totaldetalle = $totaldetalle.'<tr>'.$detalle.'</tr>';
				 $TotalCantidad = ($TotalCantidad + ($value['cantidad']));
				 $TotalPeso = ($TotalPeso + ($value['peso']));
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
                <p>FACTURA DE VENTA</p>
                <p class="">'.$contenidoFV[0]['numerodocumento'].'</p>

            </th>
        </tr>

        </table>';

        $footer = '
        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th style="text-align: center;">

                </th>
            </tr>
        </table>
        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th class="" width="33%">Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
            </tr>
        </table>';


        $html = '

				<table class="" style="width:100%">
				<tr>
          <th style="text-align: left;">
            <p class="">NIT/RUC: </p>
          </th>
          <th style="text-align: left;">
            <p> '.$contenidoFV[0]['nit'].'</p>
          </th>
					<th style="text-align: right;">
						<p class="">FACTURA: </p>
					</th>
					<th style="text-align: right;">
						<p> '.$contenidoFV[0]['numerodocumento'].'</p>
					</th>
        </tr>
        <tr>
          <th style="text-align: left;">
          	<p class="">NOMBRE: </p>
          </th>
          <th style="text-align: left;">
          	<p> '.$contenidoFV[0]['cliente'].'</p>
          </th>
					<th style="text-align: right;">
						<p class="">CLIENTE: </p>
					</th>
					<th style="text-align: right;">
						<p> '.$contenidoFV[0]['nit'].'</p>
					</th>
        </tr>
        <tr>
					<th style="text-align: left;">
						<p class="">DIRECCIÓN: </p>
					</th>
					<th style="text-align: left;">
						<p> '.$contenidoFV[0]['direccion'].'</p>
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
						<p> '.$contenidoFV[0]['ciudad'].'</p>
					</th>
					<th style="text-align: right;">
						<p class="">OC: </p>
					</th>
					<th style="text-align: right;">
						<p> '.$contenidoFV[0]['referencia'].'</p>
					</th>
        </tr>
				<tr>
					<th style="text-align: left;">
						<p class="">ESTADO: </p>
					</th>
					<th style="text-align: left;">
						<p> '.$contenidoFV[0]['estado'].'</p>
					</th>
					<th style="text-align: right;">
						<p class="">PEDIDO: </p>
					</th>
					<th style="text-align: right;">
						<p>'.$VienePedido[0]['pedido'].'</p>
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
						<p class="">ENTREGA: </p>
					</th>
					<th style="text-align: right;">
						<p>'.$VieneEntrega[0]['entrega'].'</p>
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
						<p class="">COND. PAGO: </p>
					</th>
					<th style="text-align: right;">
						<p>'.$contenidoFV[0]['condpago'].'</p>
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
						<p>'.date("d-m-Y", strtotime($contenidoFV[0]['fechadocumento'])).'</p>
					</th>
				</tr>
        </table>
        <br>

        <table width="100%">
        <tr class="">
          <th class="border_bottom" >CANT.</th>
          <th class="border_bottom">MATERIAL</th>
          <th class="border_bottom">DESCRIPCION</th>
          <th class="border_bottom">PRECIO UNITARIO</th>
          <th class="border_bottom">TOTAL</th>
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

        <br>
				<br>

				<table width="100%">
						<tr>
								<th style="text-align: center;">Sello Nro.: <span>0</span></th>
								<th style="text-align: center;">Total Peso Bruto: <span>0</span></th>
						</tr>
						<tr>
								<th style="text-align: center;">Container Nro.: <span>0</span></th>
								<th style="text-align: center;">Contenedor: <span>0</span></th>
						</tr>
						<tr>
								<th style="text-align: center;">Naviera Buque: <span>0</span></th>
								<th style="text-align: center;">Fecha de Embarque: <span>0</span></th>
						</tr>
				</table>
				<br>
				<br>
				<table width="100%">
						<tr>
								<th style="text-align: left;">Total Cantidad: <span>'.$TotalCantidad.'</span></th>
								<th style="text-align: left;">Total Peso: <span>'.$TotalPeso.' '.$contenidoFV[0]['unidad'].' </span></th>
						</tr>
				</table>


				<table width="100%" style="border-bottom: solid 1px black;">
				<tr>
						<th style="text-align: left;"></span></th>
				</tr>
				</table>

        <table width="100%">
						<tr>
								<th>
								<table border=1 width="100%">
									<tr>
											<th  style="width: 100px;">PLACA</th>
											<th style="width: 100px;">PRECINTOS</th>
									</tr>
									<tr>
									<td style="height: 50px;" ></td>
									<td style="height: 50px;"></td>
									</tr>
								</table>
								</th>
								<th>
											<table width="100%">
													<tr>
															<td style="text-align: right;">Sub Total: <span>USD '.number_format(($contenidoFV[0]['subtotal'] * 1.25) / $VieneTasa, 2, ',', '.').'</span></td>
													</tr>
													<tr>
															<td style="text-align: right;">Flete (E): <span>USD 0</span></td>
													</tr>
													<tr>
															<td style="text-align: right;">Base Imponible: <span>USD '.number_format(($contenidoFV[0]['base'] * 1.25) / $VieneTasa, 2, ',', '.').'</span></td>
													</tr>
													<tr>
															<td style="text-align: right;">Monto total excento o exonerado:<span>USD 0</span></td>
													</tr>
													<tr>
															<td style="text-align: right;">IVA 16% Sobre '.number_format($contenidoFV[0]['base'] * 1.25 / $VieneTasa , 2, ',', '.').': <span>USD '.number_format($contenidoFV[0]['iva'] * 1.25 / $VieneTasa, 2, ',', '.').'</span></td>
													</tr>
													<tr>
															<td style="text-align: right;">Valor Total: <span>USD  '.number_format($contenidoFV[0]['totaldoc'] * 1.25 / $VieneTasa, 2, ',', '.').'</span></td>
													</tr>
											</table>
								</th>
						</tr>
        </table>


        <table width="100%" style="vertical-align: bottom;">
            <tr>
                <th style="text-align: left;" class="">
                    <p>'.$formatter->toWords($contenidoFV[0]['totaldoc'] * 1.25 / $VieneTasa,2).' USD</p>
                </th>
            </tr>
        </table>

        <br><br>
        <table width="100%" style="vertical-align: bottom;">
            <tr>
                <th style="text-align: left;">
                    <span>'.$CommentFinal[0]['cdm_comments'].'</span>
                </th>
            </tr>
        </table>';

        $stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');

        // $mpdf->SetHTMLHeader($header);
        // $mpdf->SetHTMLFooter($footer);
//print_r($html);exit();die();

        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);


        $mpdf->Output('Doc.pdf', 'D');

				header('Content-type: application/force-download');
				header('Content-Disposition: attachment; filename='.$filename);


	}




}
