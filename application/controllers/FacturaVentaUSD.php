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
													t0.dvf_docnum,
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
													t0.dvf_ref oc,
													(select t9.dma_uom_weight from dmar t9 where  t9.dma_item_code = t1.fv1_itemcode) peso,
												    t10.dmu_code um,
														T0.dvf_precinto precintos,
														t0.dvf_placav placa,
														t1.fv1_fixrate fixrate,
														t0.dvf_docdate,
														t6.pgs_mpfn,
														t6.pgs_mde
												from dvfv t0
												inner join vfv1 T1 on t0.dvf_docentry = t1.fv1_docentry
												left join dmsn T2 on t0.dvf_cardcode = t2.dms_card_code
												left join dmsd T3 on T0.dvf_cardcode = t3.dmd_card_code
												left join dmsc T4 on T0.dvf_cardcode = t4.dmc_card_code
												left join dmev T5 on T0.dvf_slpcode = T5.mev_id
												left join pgdn T6 on T0.dvf_doctype = T6.pgs_id_doc_type and T0.dvf_series = T6.pgs_id
												left join pgec T7 on T0.dvf_currency = T7.pgm_symbol
												left join dmpf t8 on t2.dms_pay_type = cast(t8.mpf_id as varchar)
												left join dmar t9 on t1.fv1_itemcode = t9.dma_item_code
												left join dmum t10 on t9.dma_uom_umweight = t10.dmu_id
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


				// PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO
				// SE BUSCA LA MONEDA LOCAL PARAMETRIZADA
				$sqlMonedaLoc = "SELECT pgm_symbol FROM pgec WHERE pgm_principal = :pgm_principal";
				$resMonedaLoc = $this->pedeo->queryTable($sqlMonedaLoc, array(':pgm_principal' => 1));

				if(isset($resMonedaLoc[0])){

				}else{
						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encontro la moneda local.'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}

				$MONEDALOCAL = trim($resMonedaLoc[0]['pgm_symbol']);

				// SE BUSCA LA MONEDA DE SISTEMA PARAMETRIZADA
				$sqlMonedaSys = "SELECT pgm_symbol FROM pgec WHERE pgm_system = :pgm_system";
				$resMonedaSys = $this->pedeo->queryTable($sqlMonedaSys, array(':pgm_system' => 1));

				if(isset($resMonedaSys[0])){

				}else{

						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encontro la moneda de sistema.'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}


				$MONEDASYS = trim($resMonedaSys[0]['pgm_symbol']);

				//SE BUSCA LA TASA DE CAMBIO CON RESPECTO A LA MONEDA QUE TRAE EL DOCUMENTO A CREAR CON LA MONEDA LOCAL
				// Y EN LA MISMA FECHA QUE TRAE EL DOCUMENTO
				$sqlBusTasa = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
				$resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $contenidoFV[0]['monedadocumento'], ':tsa_date' => $contenidoFV[0]['dvf_docdate']));

				if(isset($resBusTasa[0])){

				}else{

						if(trim($contenidoFV[0]['monedadocumento']) != $MONEDALOCAL ){

							$respuesta = array(
								'error' => true,
								'data'  => array(),
								'mensaje' =>'No se encrontro la tasa de cambio para la moneda: '.$contenidoFV[0]['monedadocumento'].' en la actual fecha del documento: '.$contenidoFV[0]['dvf_docdate'].' y la moneda local: '.$resMonedaLoc[0]['pgm_symbol']
							);

							$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

							return;
						}
				}


				$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
				$resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $contenidoFV[0]['dvf_docdate']));

				if(isset($resBusTasa2[0])){

				}else{
						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :'.$contenidoFV[0]['dvf_docdate']
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}

				$TasaDocLoc = isset($resBusTasa[0]['tsa_value']) ? $resBusTasa[0]['tsa_value'] : 1;
				$TasaLocSys = $resBusTasa2[0]['tsa_value'];

				// FIN DEL PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO


				$VieneTasa = 0;

				//OBTENER RELACION DE DOCUMENTOS
				$relacionsql = "SELECT dvct.dvc_docnum AS cotizacion, dvov.vov_docnum AS pedido, dvem.vem_docnum AS entrega
										FROM dvfv
										LEFT JOIN dvem
										ON dvfv.dvf_baseentry = dvem.vem_docentry
										LEFT JOIN dvov
										ON dvem.vem_baseentry = dvov.vov_docentry
										LEFT JOIN dvct
										ON dvov.vov_baseentry = dvct.dvc_docentry
										WHERE dvfv.dvf_docentry = :DVF_DOCENTRY";

				$resrelacionsql = $this->pedeo->queryTable($relacionsql, array(':DVF_DOCENTRY' => $Data));

				$VieneEntrega = 0;
				$VienePedido = 0;
				$VieneCotizacion = 0;

				if(isset($resrelacionsql[0])){
						$VieneEntrega = $resrelacionsql[0]['entrega'];
						$VienePedido = $resrelacionsql[0]['pedido'];
						$VieneCotizacion = $resrelacionsql[0]['cotizacion'];

				}
				//FIN BUSQUEDA REALAZION DE DOCUMENTOS

				//INFORMACION DE LA DESCRIPCION FINAL DEL FORMATO
				$CommentFinal = "SELECT t0.*
												 FROM cfdm t0
												 LEFT JOIN dvfv t1 ON t0.cdm_type = CAST(t1.dvf_doctype AS VARCHAR)
												 WHERE t1.dvf_docentry = :DVF_DOCENTRY";
				$CommentFinal = $this->pedeo->queryTable($CommentFinal,array(':DVF_DOCENTRY' => $Data));


				$totaldetalle = '';
				$TotalCantidad = 0;
				$TotalPeso = 0;
				$TOTALFIXRATE = 0;

				foreach ($contenidoFV as $key => $value) {
							// code...<td>'.$value['um'].'</td>
							//<td>'.number_format($value['ivap'], 2, ',', '.').'</td>


						$valorUnitario = $value['vrunit'];
						$valorBase = $value['base'];
						$vfx = $value['fixrate'];

						if( $value['monedadocumento'] != $MONEDASYS ){

							if( $value['monedadocumento'] != $MONEDALOCAL ){

									$valorUnitario = (($valorUnitario + $vfx) * $TasaDocLoc);
									$valorUnitario = ($valorUnitario  / $TasaLocSys);

									$valorBase = (($valorBase + $vfx) * $TasaDocLoc);
									$valorBase = ($valorBase / $TasaLocSys);

									$TOTALFIXRATE = $TOTALFIXRATE + (($vfx * $TasaDocLoc) / $TasaLocSys);

							}else{

									$valorUnitario = (($valorUnitario + $value['fixrate']) / $TasaLocSys);
									$valorBase = (($valorBase + $value['fixrate']) / $TasaLocSys);
									$TOTALFIXRATE = $TOTALFIXRATE + ($value['fixrate'] / $TasaLocSys);

									// print_r($valorUnitario);exit();die();
							}

						}



						$detalle = '	<td>'.$value['cantidad'].'</td>
													<td>'.$value['referencia'].'</td>
													<td>'.$value['descripcion'].'</td>
													<td>USD '.number_format($valorUnitario, 2, ',', '.').'</td>
													<td>USD '.number_format($valorBase , 2, ',', '.').'</td>';

						 $totaldetalle = $totaldetalle.'<tr>'.$detalle.'</tr>';
						 $TotalCantidad = ($TotalCantidad + ($value['cantidad']));
						 $TotalPeso = ($TotalPeso + ($value['peso']));
				}

				$valorSubtotal = $contenidoFV[0]['subtotal'];
				if( $value['monedadocumento'] != $MONEDASYS ){

					if( $value['monedadocumento'] != $MONEDALOCAL ){

							$valorSubtotal = ($valorSubtotal * $TasaDocLoc);
							$valorSubtotal = ($valorSubtotal / $TasaLocSys);

					}else{
							$valorSubtotal = ($valorSubtotal / $TasaLocSys);

					}

				}

				$consecutivo = '';

				if($contenidoFV[0]['pgs_mpfn'] == 1){
					$consecutivo = $contenidoFV[0]['numerodocumento'];
				}else{
					$consecutivo = $contenidoFV[0]['dvf_docnum'];
				}


				$DatosExportacion = '';

				if($contenidoFV[0]['pgs_mde'] == 1){

					$DatosExportacion = '<table width="100%">
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
														</table>';
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
                <p class="">'.$consecutivo.'</p>

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
						<p> '.$consecutivo.'</p>
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
						<p> '.$contenidoFV[0]['oc'].'</p>
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
						<p>'.$VienePedido.'</p>
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
						<p>'.$VieneEntrega.'</p>
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

				'.$DatosExportacion.'

				<br>
				<br>
				<table width="100%">
						<tr>
								<th style="text-align: left;">Total Cantidad: <span>'.$TotalCantidad.'</span></th>
								<th style="text-align: left;">Total Peso: <span>'.$TotalPeso." ".$contenidoFV[0]['um'].'</span></th>
						</tr>
				</table>


				<table width="100%" style="border-bottom: solid 1px black;">
				<tr>
						<th style="text-align: left;"></span></th>
				</tr>
				</table>

				<table width="100%" style="vertical-align: bottom;">
						<tr>
								<th style="text-align: left;" class="">
										<p>'.$formatter->toWords(($valorSubtotal + $TOTALFIXRATE),2).' DOLARES</p>
								</th>
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
									<td style="height: 50px;" >'.$contenidoFV[0]['placa'].'</td>
									<td style="height: 50px;">'.$contenidoFV[0]['precintos'].'</td>
									</tr>
								</table>
								</th>
								<th>
											<table width="100%">
													<tr>
															<td style="text-align: right;">Valor Total: <span>USD  '.number_format(($valorSubtotal + $TOTALFIXRATE), 2, ',', '.').'</span></td>
													</tr>
											</table>
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
// print_r($contenidoFV[0]['subtotal']);
// print_r($TOTALFIXRATE);
// exit();die();

        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);


        $mpdf->Output('Doc.pdf', 'D');

				header('Content-type: application/force-download');
				header('Content-Disposition: attachment; filename='.$filename);


	}




}
