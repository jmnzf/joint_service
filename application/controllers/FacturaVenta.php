<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class FacturaVenta extends REST_Controller {

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


	public function FacturaVenta_post(){

				$DECI_MALES =  $this->generic->getDecimals();

        $Data = $this->post();

				$formatter = new NumeroALetras();

				//'setAutoTopMargin' => 'stretch','setAutoBottomMargin' => 'stretch',

        $mpdf = new \Mpdf\Mpdf(['margin-top' => 500,'default_font' => 'arial']);

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
									to_char(T0.dvf_docdate,'DD-MM-YYYY') FechaDocumento,
									to_char(T0.dvf_duedate,'DD-MM-YYYY') FechaVenDocumento,
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
									0 peso,
									t10.dmu_code um,
									T0.dvf_precinto precintos,
									t0.dvf_placav placa,
									t0.dvf_docdate,
									t6.pgs_mpfn,
									t6.pgs_mde,
									t1.fv1_quantity,
									t2.dms_rtype regimen,
									T0.dvf_taxigtf,
									T0.dvf_igtf,
									t0.dvf_igtfapplyed,
									get_dynamic_conversion(get_localcur(),t0.dvf_igtfcurrency,t0.dvf_docdate,t0.dvf_igtfapplyed,get_localcur()) as base_igtf,
									get_dynamic_conversion(get_localcur(),t0.dvf_igtfcurrency,t0.dvf_docdate,t0.dvf_igtf,get_localcur()) as imp_igtf
								from dvfv t0
								inner join vfv1 T1 on t0.dvf_docentry = t1.fv1_docentry
								left join dmsn T2 on t0.dvf_cardcode = t2.dms_card_code
								left join dmsd T3 on T0.dvf_cardcode = t3.dmd_card_code AND t3.dmd_ppal = 1
								left join dmsc T4 on T0.dvf_cardcode = t4.dmc_card_code
								left join dmev T5 on T0.dvf_slpcode = T5.mev_id
								left join pgdn T6 on T0.dvf_doctype = T6.pgs_id_doc_type and T0.dvf_series = T6.pgs_id
								left join pgec T7 on T0.dvf_currency = T7.pgm_symbol
								left join dmpf t8 on t2.dms_pay_type = cast(t8.mpf_id as varchar)
								left join dmar t9 on t1.fv1_itemcode = t9.dma_item_code
								left join dmum t10 on t9.dma_uom_umweight = t10.dmu_id
								where T0.dvf_docentry = :DVF_DOCENTRY AND t0.business = :business
								and t2.dms_card_type = '1'";

				$contenidoFV = $this->pedeo->queryTable($sqlcotizacion,array(
					':DVF_DOCENTRY'=> $Data['DVF_DOCENTRY'],
					':business' => $Data['business']
				));

				if(!isset($contenidoFV[0])){
						$respuesta = array(
							 'error' => true,
							 'data'  => $empresa,
							 'mensaje' =>'no se encontro el documento'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}

				//
				$detalleSerial = "";
				$serialAct = "";
				$detalleS = "";
				$aver = 0;
				$sqlSerial = "SELECT msn_itemcode,msn_whscode,msn_sn,msn_quantity FROM tmsn WHERE business = :business AND msn_basetype = :msn_basetype AND msn_baseentry = :msn_baseentry  ORDER BY msn_itemcode ASC";
				$tablasSerial = "";

				$resSerial = $this->pedeo->queryTable($sqlSerial, array(
					':business' 	 => $Data['business'],
					':msn_basetype'  => 5,
					':msn_baseentry' => $Data['DVF_DOCENTRY']
				));

				if ( isset($resSerial[0]) ) {
					
					foreach( $resSerial as $key => $element){
					
						if ($serialAct == "" ) {
							
							$serialAct = $element['msn_itemcode'];

							$detalleS = '<td>'.$element['msn_whscode'].'</td>
											<td>'.$element['msn_sn'].'</td>
											<td>'.$element['msn_quantity'].'</td>';

							$detalleSerial.= '<tr>'.$detalleS.'</tr>';				
							$aver = 1;
						} else {


							if ( $serialAct == $element['msn_itemcode'] ){

								$detalleS = '<td>'.$element['msn_whscode'].'</td>
											<td>'.$element['msn_sn'].'</td>
											<td>'.$element['msn_quantity'].'</td>';

								$detalleSerial.= '<tr>'.$detalleS.'</tr>';

								$aver = 1;

							}else{
						
								$tablasSerial.= '<table  width="100%"><tr><th class="fondo">CODIGO ITEM '.$serialAct.'</th></tr></table>';
								$tablasSerial.= '<table class="borde" width="100%"><tr><th  style="text-align: center;">ALMACEN</th><th  style="text-align: center;">SERIAL</th><th  style="text-align: center;">CANTIDAD</th></tr>'.$detalleSerial.'</table>';

								$detalleS = "";

								$detalleSerial = "";

								$serialAct = $element['msn_itemcode'];
							}

						} 

					}

					if ($aver == 1 && $tablasSerial == ""){
						$tablasSerial.= '<table width="100%"><tr><th class="fondo">CODIGO ITEM '.$serialAct.'</th></tr></table>';
						$tablasSerial.= '<table class="borde" width="100%"><tr><th  style="text-align: center;">ALMACEN</th><th  style="text-align: center;">SERIAL</th><th  style="text-align: center;">CANTIDAD</th></tr>'.$detalleSerial.'</table>';
					}
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

			  $resrelacionsql = $this->pedeo->queryTable($relacionsql, array(':DVF_DOCENTRY' => $Data['DVF_DOCENTRY']));

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
				$comentarioFinal = "";
				$SqlCommentFinal = "SELECT t0.*
												 FROM cfdm t0
												 LEFT JOIN dvfv t1 ON t0.cdm_type = CAST(t1.dvf_doctype AS VARCHAR)
												 WHERE t1.dvf_docentry = :DVF_DOCENTRY";
				$CommentFinal = $this->pedeo->queryTable($SqlCommentFinal,array(':DVF_DOCENTRY' => $Data['DVF_DOCENTRY']));

				if(isset($CommentFinal[0])){
					$CommentFinal[0]['cdm_comments'];
				}

				$totaldetalle = '';
				$TotalCantidad = 0;
				$TotalPeso = 0;

				foreach ($contenidoFV as $key => $value) {
								// code...<td>'.$value['um'].'</td>
								//<td>'.$value['monedadocumento']." ".number_format($value['ivap'], 2, ',', '.').'</td>


							$valorUnitario = $value['vrunit'];
							$valortotalLinea = $value['valortotall'];



							if( $value['monedadocumento'] != $MONEDALOCAL ){

									$valorUnitario = ($valorUnitario  * $TasaDocLoc);
									$valortotalLinea = ($valortotalLinea  * $TasaDocLoc);

							}



							$detalle = '	<td>'.$value['cantidad'].'</td>
														<td>'.$value['referencia'].'</td>
														<td>'.$value['descripcion'].'</td>
														<td>'.$value['monedadocumento']." ".number_format($valorUnitario , $DECI_MALES, ',', '.').'</td>
														<td>'.$value['monedadocumento']." ".number_format($valortotalLinea , $DECI_MALES, ',', '.').'</td>';

							 $totaldetalle = $totaldetalle.'<tr>'.$detalle.'</tr>';
							 $TotalCantidad = ($TotalCantidad + ($value['cantidad']));
							 $TotalPeso = ($TotalPeso + ($value['peso'] * $value['cantidad']));
				}

				$valorTotalBase = $contenidoFV[0]['base'];
				$valorTotalSubtotal = $contenidoFV[0]['subtotal'];
				$valorTotalIva = $contenidoFV[0]['iva'];
				$valorTotalDoc = $contenidoFV[0]['totaldoc'];

				if( $value['monedadocumento'] != $MONEDALOCAL ){

						$valorTotalBase = ($valorTotalBase * $TasaDocLoc);
						$valorTotalSubtotal = ($valorTotalSubtotal * $TasaDocLoc);
						$valorTotalIva = ($valorTotalIva * $TasaDocLoc);
						$valorTotalDoc = ($valorTotalDoc * $TasaDocLoc);

				}


                $sqlIgtf = "SELECT 
				gtf_value,
				tsa_value,
				igtf.*,
				round((gtf_taxdivisa * tsa_value),get_decimals()) as imp_value
				from dvfv
				inner join igtf on igtf.gtf_docentry  = dvf_docentry and igtf.gtf_doctype = dvf_doctype
				inner join tasa on tsa_date = dvf_docdate and tsa_currd = gtf_currency and tsa_curro = get_localcur()
				where dvf_docentry = :DVF_DOCENTRY and dvf_doctype =  5 order by gtf_balancer desc";

                $resIgtf = $this->pedeo->queryTable($sqlIgtf,array(':DVF_DOCENTRY' => $Data['DVF_DOCENTRY']));
                $igtfTable = "<table width='50%' style='vertical-align: bottom;'>
                <tr style='border-bottom:1px solid #000; text-align: center;'>
                    <th style='border-bottom:1px solid #000; text-align: center;'>Divisa</th>
                    <th style='border-bottom:1px solid #000; text-align: center;'>Tasa</th>
                    <th style='border-bottom:1px solid #000; text-align: center;'>Monto Divisa</th>
                    <th style='border-bottom:1px solid #000; text-align: center;'>Monto Recibido</th>
                    <th style='border-bottom:1px solid #000; text-align: center;'>V. impuesto</th>
                    <th style='border-bottom:1px solid #000; text-align: center;'>Saldo de factura</th>

                </tr>";
                $igtfTotal = 0;
				$impIgtf = 0;
                if(isset($resIgtf[0])){
					$restante = $contenidoFV[0]['totaldoc'];
                    foreach ($resIgtf as $key => $value) {
						$restante-= $value['gtf_value'];
                        $igtfTable.="<tr >
                        <td>{$value['gtf_currency']}</td>
                        <td>{$value['tsa_value']}</td>
                        <td>{$value['gtf_value']}</td>
                        <td>{$value['gtf_collected']}</td>
                        <td>{$value['imp_value']}</td>
                        <td>{$value['gtf_balancer']}</td>
                    </tr>";

                    $igtfTotal+= $value['gtf_collected'];
                    $impIgtf+= $value['gtf_taxdivisa'];
                        
                    }
                    // print_r($contenidoFV[0]);exit;
                    $igtfTable.= "<tr style=\"border-top:1px solid #000;\">
					<td colspan=\"5\" style='border-top:1px solid #000; text-align: left;'><p>monto recibido en BS</p></td>
                    <td  style=\"text-align: center; border-top:1px solid #000;\"> ".round(($contenidoFV[0]['totaldoc'] - $igtfTotal),$DECI_MALES)."</td>
                    </tr>";
                    
                }

                $igtfTable.= "</table>";

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

                $regimen = $igtfTable;


				


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


        $html = '<br>
				<br>
				<br>
				<br>
				<br>
				<br>
				<br>
				<br>

				<table class="" style="width:100%">
				<tr>
          <th style="text-align: left;">
            <p class="">RIF: </p>
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
						<p> '.$VieneCotizacion.'</p>
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
						<p>'.$this->dateformat->Date($contenidoFV[0]['fechadocumento']).'</p>
					</th>
				</tr>
        </table>
        <br>

        <table width="100%">
        <tr class="">
          <th class="border_bottom" >CANT.</th>
          <th class="border_bottom">artículo</th>
          <th class="border_bottom">descrición</th>
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

        		<table width="100%">
					<tr>
						<th>
							<table width="100%" style="vertical-align: bottom;">
								<tr><td>&nbsp;</td></tr>
								<tr><td>&nbsp;</td></tr>
								<tr><td>&nbsp;</td></tr>
								<tr><td>&nbsp;</td></tr>
								<tr><td>&nbsp;</td></tr>
								<tr><td>&nbsp;</td></tr>
								<tr><td>&nbsp;</td></tr>
								<tr><td>&nbsp;</td></tr>
								<tr>
									<td style="text-align: left;" class="">
										<p>'.$formatter->toWords($valorTotalDoc,2)." ".$contenidoFV[0]['nombremoneda'].'</p>
									</td>
								</tr>
								<br>
								<tr>
									<td style="text-align: left;" class="">
										<p>COMENTARIOS:  '.$contenidoFV[0]['comentarios'].'</p>
									</td>
								</tr>
								<br>
							</table>
						</th>
						<th>
							<table width="100%">
								<tr>
									<td style="text-align: right;">Sub Total: <span>'.$contenidoFV[0]['monedadocumento']." ".number_format($valorTotalSubtotal, $DECI_MALES, ',', '.').'</span></td>
								</tr>
								<tr>
									<td style="text-align: right;">Flete (E): <span>'.$contenidoFV[0]['monedadocumento']." 0".'</span></td>
								</tr>
								<tr>
									<td style="text-align: right;">Base Imponible: <span>'.$contenidoFV[0]['monedadocumento']." ".number_format($valorTotalBase, $DECI_MALES, ',', '.').'</span></td>
								</tr>
								<tr>
									<td style="text-align: right;">Monto total excento o exonerado:<span>'.$contenidoFV[0]['monedadocumento']." 0".'</span></td>
								</tr>
								<tr>
									<td style="text-align: right;">IVA 16% Sobre '.number_format($contenidoFV[0]['base'], 2, ',', '.').': <span>'.$contenidoFV[0]['monedadocumento']." ".number_format($valorTotalIva, 2, ',', '.').'</span></td>
								</tr>
								<tr>
									<td style="text-align: right;">Valor Factura: <span>'.$contenidoFV[0]['monedadocumento']." ".number_format(($contenidoFV[0]['base']+$valorTotalIva), 2, ',', '.').'</span></td>
								</tr>
								<tr>
									<td style="text-align: right;">IGTF: <span>'.$contenidoFV[0]['monedadocumento'].' '.$contenidoFV[0]['dvf_igtf'].'</span></td>
								</tr>
								<tr>
									<td style="text-align: right;">Valor Total: <span>'.$contenidoFV[0]['monedadocumento']." ".number_format($valorTotalDoc,$DECI_MALES , ',', '.').'</span></td>
								</tr>
							</table>
						</th>
					</tr>
        		</table>
				<br>
				<table width="100%" style="vertical-align: bottom;">
				<tr>
					<th style="text-align: justify;">
					<br>

					<p>NOTA: EL PAGO, RECIBIDO DE ESTE DOCUMENTO, EN MONEDA DISTINTA A LA DE CURSO LEGAL EN EL PAIS Y FUERA DEL SISTEMA
						BANCARIO, GENERA UN 3% POR CONCEPTO DE IMPUESTOS A LAS GRANDES TRANSACCIONES FINANCIERAS (IGTF) CONSIDERANDO
						LO ESTABLECIDO EN LOS ART. 4 NUMERAL 6 , ART. 24, AMBOS DE LA GACETA 6.687 Y ART.1 DE LA GACETA 42.339.
						</p>
					</th>
				</tr>
				</table>
				<br>
				'.$regimen.'
				<br>
        		<br>
				<table width="100%" style="vertical-align: bottom;">
					<tr>
						<th style="text-align: justify;">
							<span>'.$comentarioFinal.'</span>
						</th>
					</tr>
					<br>
					<br>
				</table>';
				// print_r($html);exit();die(); 
        $stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');

        // $mpdf->SetHTMLHeader($header);
        // $mpdf->SetHTMLFooter($footer);
				//print_r($html);exit();die();

        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);

		if ($aver){

            $mpdf->AddPage();
            $mpdf->WriteHTML($tablasSerial, \Mpdf\HTMLParserMode::HTML_BODY);
        }


        $mpdf->Output('Doc.pdf', 'D');

		header('Content-type: application/force-download');
		header('Content-Disposition: attachment; filename='.$filename);


	}




}
